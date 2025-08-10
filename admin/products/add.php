<?php
/**
 * TKT Shop - Add Product Page (Fixed)
 * Trang thêm sản phẩm mới
 */

// Start session first
session_start();

require_once '../../config/config.php';
require_once '../../config/database.php';

// Kiểm tra đăng nhập admin
if (!isset($_SESSION['admin_id'])) {
    header('Location: ../login.php');
    exit;
}

$page_title = "Add New Product";
$errors = [];
$success = '';

// Helper Functions (moved to top)
function uploadProductImage($file) {
    $upload_dir = "../../uploads/products/";
    
    // Create directory if not exists
    if (!file_exists($upload_dir)) {
        mkdir($upload_dir, 0755, true);
    }
    
    // Validate file
    $allowed_types = ['image/jpeg', 'image/png', 'image/gif', 'image/webp'];
    $max_size = 5 * 1024 * 1024; // 5MB
    
    if (!in_array($file['type'], $allowed_types)) {
        return ['success' => false, 'message' => 'Invalid image format. Please use JPG, PNG, GIF, or WEBP.'];
    }
    
    if ($file['size'] > $max_size) {
        return ['success' => false, 'message' => 'Image size too large. Maximum size is 5MB.'];
    }
    
    // Generate unique filename
    $extension = pathinfo($file['name'], PATHINFO_EXTENSION);
    $filename = 'product_' . uniqid() . '_' . time() . '.' . $extension;
    $filepath = $upload_dir . $filename;
    
    if (move_uploaded_file($file['tmp_name'], $filepath)) {
        return ['success' => true, 'filename' => $filename];
    } else {
        return ['success' => false, 'message' => 'Failed to upload image.'];
    }
}

function createSlug($string) {
    $slug = strtolower(trim($string));
    // Remove Vietnamese accents
    $slug = preg_replace('/[àáạảãâầấậẩẫăằắặẳẵ]/u', 'a', $slug);
    $slug = preg_replace('/[èéẹẻẽêềếệểễ]/u', 'e', $slug);
    $slug = preg_replace('/[ìíịỉĩ]/u', 'i', $slug);
    $slug = preg_replace('/[òóọỏõôồốộổỗơờớợởỡ]/u', 'o', $slug);
    $slug = preg_replace('/[ùúụủũưừứựửữ]/u', 'u', $slug);
    $slug = preg_replace('/[ỳýỵỷỹ]/u', 'y', $slug);
    $slug = preg_replace('/[đ]/u', 'd', $slug);
    // Replace non-alphanumeric with dash
    $slug = preg_replace('/[^a-z0-9-]/', '-', $slug);
    $slug = preg_replace('/-+/', '-', $slug);
    return trim($slug, '-');
}

function logAdminActivity($action, $details = '') {
    global $pdo;
    
    try {
        // Check if table exists, if not create it
        $stmt = $pdo->query("SHOW TABLES LIKE 'admin_activity_logs'");
        if ($stmt->rowCount() == 0) {
            // Create table if it doesn't exist
            $pdo->exec("
                CREATE TABLE admin_activity_logs (
                    id INT AUTO_INCREMENT PRIMARY KEY,
                    admin_id INT,
                    action VARCHAR(100),
                    details TEXT,
                    ip_address VARCHAR(45),
                    user_agent TEXT,
                    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
                )
            ");
        }
        
        $stmt = $pdo->prepare("
            INSERT INTO admin_activity_logs (admin_id, action, details, ip_address, user_agent, created_at) 
            VALUES (?, ?, ?, ?, ?, NOW())
        ");
        $stmt->execute([
            $_SESSION['admin_id'],
            $action,
            $details,
            $_SERVER['REMOTE_ADDR'] ?? '',
            $_SERVER['HTTP_USER_AGENT'] ?? ''
        ]);
    } catch (Exception $e) {
        error_log("Log activity error: " . $e->getMessage());
    }
}

// Xử lý form submit
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $name = trim($_POST['name'] ?? '');
    $description = trim($_POST['description'] ?? '');
    $short_description = trim($_POST['short_description'] ?? '');
    $price = $_POST['price'] ?? 0;
    $sale_price = $_POST['sale_price'] ?? null;
    $sku = trim($_POST['sku'] ?? '');
    $category_id = $_POST['category_id'] ?? 0;
    $brand = trim($_POST['brand'] ?? '');
    $weight = $_POST['weight'] ?? 0;
    $dimensions = trim($_POST['dimensions'] ?? '');
    $quantity = $_POST['quantity'] ?? 0;
    $min_quantity = $_POST['min_quantity'] ?? 1;
    $status = $_POST['status'] ?? 'active';
    $featured = isset($_POST['featured']) ? 1 : 0;
    $meta_title = trim($_POST['meta_title'] ?? '');
    $meta_description = trim($_POST['meta_description'] ?? '');
    $tags = trim($_POST['tags'] ?? '');
    
    // Validation
    if (empty($name)) {
        $errors[] = "Product name is required";
    }
    
    if (empty($description)) {
        $errors[] = "Product description is required";
    }
    
    if (!is_numeric($price) || $price <= 0) {
        $errors[] = "Valid price is required";
    }
    
    if (!empty($sale_price) && (!is_numeric($sale_price) || $sale_price >= $price)) {
        $errors[] = "Sale price must be less than regular price";
    }
    
    if (empty($sku)) {
        $errors[] = "SKU is required";
    } else {
        // Kiểm tra SKU trùng lặp
        try {
            $check_sku = $pdo->prepare("SELECT COUNT(*) FROM products WHERE sku = ?");
            $check_sku->execute([$sku]);
            if ($check_sku->fetchColumn() > 0) {
                $errors[] = "SKU already exists";
            }
        } catch (PDOException $e) {
            $errors[] = "Database error checking SKU";
        }
    }
    
    if (!$category_id) {
        $errors[] = "Please select a category";
    }
    
    if (!is_numeric($quantity) || $quantity < 0) {
        $errors[] = "Valid quantity is required";
    }
    
    // Xử lý upload ảnh chính
    $main_image = '';
    if (isset($_FILES['main_image']) && $_FILES['main_image']['error'] === UPLOAD_ERR_OK) {
        $upload_result = uploadProductImage($_FILES['main_image']);
        if ($upload_result['success']) {
            $main_image = $upload_result['filename'];
        } else {
            $errors[] = $upload_result['message'];
        }
    }
    
    // Xử lý upload gallery
    $gallery_images = [];
    if (isset($_FILES['gallery_images'])) {
        foreach ($_FILES['gallery_images']['tmp_name'] as $key => $tmp_name) {
            if ($_FILES['gallery_images']['error'][$key] === UPLOAD_ERR_OK) {
                $file = [
                    'name' => $_FILES['gallery_images']['name'][$key],
                    'type' => $_FILES['gallery_images']['type'][$key],
                    'tmp_name' => $tmp_name,
                    'size' => $_FILES['gallery_images']['size'][$key]
                ];
                
                $upload_result = uploadProductImage($file);
                if ($upload_result['success']) {
                    $gallery_images[] = $upload_result['filename'];
                }
            }
        }
    }
    
    // Nếu không có lỗi, thêm sản phẩm vào database
    if (empty($errors)) {
        try {
            $pdo->beginTransaction();
            
            // Tạo slug từ tên sản phẩm
            $slug = createSlug($name);
            
            // Insert sản phẩm
            $stmt = $pdo->prepare("
                INSERT INTO products (
                    name, slug, description, short_description, price, sale_price, 
                    sku, category_id, brand, weight, dimensions, stock_quantity, min_quantity,
                    status, is_featured, main_image, gallery_images, meta_title, meta_description, 
                    tags, created_at, updated_at
                ) VALUES (
                    ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW(), NOW()
                )
            ");
            
            $stmt->execute([
                $name, $slug, $description, $short_description, $price, $sale_price,
                $sku, $category_id, $brand, $weight, $dimensions, $quantity, $min_quantity,
                $status, $featured, $main_image, json_encode($gallery_images), 
                $meta_title, $meta_description, $tags
            ]);
            
            $product_id = $pdo->lastInsertId();
            
            // Xử lý variants nếu có
            if (isset($_POST['variants']) && is_array($_POST['variants'])) {
                foreach ($_POST['variants'] as $variant) {
                    if (!empty($variant['color']) || !empty($variant['size'])) {
                        // Check if product_variants table exists
                        try {
                            $variant_stmt = $pdo->prepare("
                                INSERT INTO product_variants (product_id, color, size, price_adjustment, quantity)
                                VALUES (?, ?, ?, ?, ?)
                            ");
                            $variant_stmt->execute([
                                $product_id,
                                $variant['color'] ?? '',
                                $variant['size'] ?? '',
                                $variant['price_adjustment'] ?? 0,
                                $variant['quantity'] ?? 0
                            ]);
                        } catch (PDOException $e) {
                            // Table might not exist, skip variants for now
                            error_log("Variants table error: " . $e->getMessage());
                        }
                    }
                }
            }
            
            $pdo->commit();
            $success = "Product added successfully!";
            
            // Log admin activity
            logAdminActivity("ADD_PRODUCT", "Added product: $name (ID: $product_id)");
            
            // Redirect sau khi thành công
            header("Location: index.php?success=" . urlencode($success));
            exit;
            
        } catch (Exception $e) {
            $pdo->rollback();
            $errors[] = "Database error: " . $e->getMessage();
        }
    }
}

// Lấy danh sách categories
try {
    $categories = $pdo->query("SELECT * FROM categories WHERE status = 'active' ORDER BY name")->fetchAll();
} catch (PDOException $e) {
    $categories = [];
    $errors[] = "Could not load categories";
}

// Lấy danh sách colors và sizes
try {
    $colors = $pdo->query("SELECT * FROM colors ORDER BY name")->fetchAll();
} catch (PDOException $e) {
    $colors = [];
}

try {
    $sizes = $pdo->query("SELECT * FROM sizes ORDER BY sort_order")->fetchAll();
} catch (PDOException $e) {
    $sizes = [];
}

// Check if header file exists, if not create basic layout
if (file_exists('../layouts/header.php')) {
    include '../layouts/header.php';
} else {
    // Basic HTML structure if header doesn't exist
    echo '<!DOCTYPE html>
    <html lang="vi">
    <head>
        <meta charset="UTF-8">
        <meta name="viewport" content="width=device-width, initial-scale=1.0">
        <title>Add Product - TKT Admin</title>
        <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
        <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    </head>
    <body>
        <div class="container-fluid">
            <div class="row">
                <div class="col-md-2 bg-dark text-white p-3">
                    <h4>TKT Admin</h4>
                    <ul class="nav flex-column">
                        <li class="nav-item">
                            <a class="nav-link text-white" href="../dashboard.php">Dashboard</a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link text-white" href="index.php">Sản phẩm</a>
                        </li>
                    </ul>
                </div>
                <div class="col-md-10">';
}
?>

<div class="content-area p-4">
    <div class="page-header">
        <h1 class="page-title">Add New Product</h1>
        <div class="page-actions">
            <a href="index.php" class="btn btn-light">
                <i class="fas fa-arrow-left"></i> Back to Products
            </a>
        </div>
    </div>

    <?php if (!empty($errors)): ?>
        <div class="alert alert-danger">
            <ul class="mb-0">
                <?php foreach ($errors as $error): ?>
                    <li><?php echo htmlspecialchars($error); ?></li>
                <?php endforeach; ?>
            </ul>
        </div>
    <?php endif; ?>

    <?php if ($success): ?>
        <div class="alert alert-success">
            <?php echo htmlspecialchars($success); ?>
        </div>
    <?php endif; ?>

    <form method="POST" enctype="multipart/form-data" class="product-form" data-ajax="false">
        <div class="row">
            <!-- Left Column -->
            <div class="col-md-8">
                <!-- Basic Information -->
                <div class="admin-card mb-30">
                    <div class="admin-card-header">
                        <h3 class="admin-card-title">Basic Information</h3>
                    </div>
                    <div class="admin-card-body">
                        <div class="form-group">
                            <label class="form-label">Product Name *</label>
                            <input type="text" name="name" class="form-control" 
                                   value="<?php echo htmlspecialchars($_POST['name'] ?? ''); ?>" 
                                   data-validation="required" required>
                        </div>

                        <div class="row">
                            <div class="col-md-6">
                                <label class="form-label">SKU *</label>
                                <input type="text" name="sku" class="form-control" 
                                       value="<?php echo htmlspecialchars($_POST['sku'] ?? ''); ?>" 
                                       data-validation="required" required>
                                <small class="form-text text-muted">Unique product identifier</small>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label">Brand</label>
                                <input type="text" name="brand" class="form-control" 
                                       value="<?php echo htmlspecialchars($_POST['brand'] ?? ''); ?>">
                            </div>
                        </div>

                        <div class="form-group">
                            <label class="form-label">Short Description</label>
                            <textarea name="short_description" class="form-control" rows="3" 
                                      maxlength="200"><?php echo htmlspecialchars($_POST['short_description'] ?? ''); ?></textarea>
                            <small class="form-text text-muted">Brief product summary (max 200 characters)</small>
                        </div>

                        <div class="form-group">
                            <label class="form-label">Full Description *</label>
                            <textarea name="description" class="form-control" rows="8" 
                                      data-validation="required" required><?php echo htmlspecialchars($_POST['description'] ?? ''); ?></textarea>
                        </div>
                    </div>
                </div>

                <!-- Pricing -->
                <div class="admin-card mb-30">
                    <div class="admin-card-header">
                        <h3 class="admin-card-title">Pricing</h3>
                    </div>
                    <div class="admin-card-body">
                        <div class="row">
                            <div class="col-md-6">
                                <label class="form-label">Regular Price *</label>
                                <div class="input-group">
                                    <input type="number" name="price" class="form-control" 
                                           value="<?php echo $_POST['price'] ?? ''; ?>" 
                                           step="0.01" min="0" data-validation="required numeric" required>
                                    <div class="input-group-text">VND</div>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label">Sale Price</label>
                                <div class="input-group">
                                    <input type="number" name="sale_price" class="form-control" 
                                           value="<?php echo $_POST['sale_price'] ?? ''; ?>" 
                                           step="0.01" min="0">
                                    <div class="input-group-text">VND</div>
                                </div>
                                <small class="form-text text-muted">Leave empty if no sale</small>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Inventory -->
                <div class="admin-card mb-30">
                    <div class="admin-card-header">
                        <h3 class="admin-card-title">Inventory</h3>
                    </div>
                    <div class="admin-card-body">
                        <div class="row">
                            <div class="col-md-6">
                                <label class="form-label">Stock Quantity *</label>
                                <input type="number" name="quantity" class="form-control" 
                                       value="<?php echo $_POST['quantity'] ?? '0'; ?>" 
                                       min="0" data-validation="required numeric" required>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label">Minimum Quantity</label>
                                <input type="number" name="min_quantity" class="form-control" 
                                       value="<?php echo $_POST['min_quantity'] ?? '1'; ?>" 
                                       min="1">
                                <small class="form-text text-muted">Alert when stock is below this level</small>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Shipping -->
                <div class="admin-card mb-30">
                    <div class="admin-card-header">
                        <h3 class="admin-card-title">Shipping Information</h3>
                    </div>
                    <div class="admin-card-body">
                        <div class="row">
                            <div class="col-md-6">
                                <label class="form-label">Weight (kg)</label>
                                <input type="number" name="weight" class="form-control" 
                                       value="<?php echo $_POST['weight'] ?? ''; ?>" 
                                       step="0.01" min="0">
                            </div>
                            <div class="col-md-6">
                                <label class="form-label">Dimensions (L x W x H cm)</label>
                                <input type="text" name="dimensions" class="form-control" 
                                       value="<?php echo htmlspecialchars($_POST['dimensions'] ?? ''); ?>"
                                       placeholder="e.g., 20 x 15 x 10">
                            </div>
                        </div>
                    </div>
                </div>

                <!-- SEO -->
                <div class="admin-card">
                    <div class="admin-card-header">
                        <h3 class="admin-card-title">SEO Settings</h3>
                    </div>
                    <div class="admin-card-body">
                        <div class="form-group">
                            <label class="form-label">Meta Title</label>
                            <input type="text" name="meta_title" class="form-control" 
                                   value="<?php echo htmlspecialchars($_POST['meta_title'] ?? ''); ?>"
                                   maxlength="60">
                            <small class="form-text text-muted">Recommended: 50-60 characters</small>
                        </div>
                        
                        <div class="form-group">
                            <label class="form-label">Meta Description</label>
                            <textarea name="meta_description" class="form-control" rows="3" 
                                      maxlength="160"><?php echo htmlspecialchars($_POST['meta_description'] ?? ''); ?></textarea>
                            <small class="form-text text-muted">Recommended: 150-160 characters</small>
                        </div>
                        
                        <div class="form-group">
                            <label class="form-label">Tags</label>
                            <input type="text" name="tags" class="form-control" 
                                   value="<?php echo htmlspecialchars($_POST['tags'] ?? ''); ?>"
                                   placeholder="tag1, tag2, tag3">
                            <small class="form-text text-muted">Separate tags with commas</small>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Right Column -->
            <div class="col-md-4">
                <!-- Product Images -->
                <div class="admin-card mb-30">
                    <div class="admin-card-header">
                        <h3 class="admin-card-title">Product Images</h3>
                    </div>
                    <div class="admin-card-body">
                        <div class="form-group">
                            <label class="form-label">Main Image</label>
                            <div class="image-upload-area">
                                <input type="file" name="main_image" class="image-input" accept="image/*">
                                <div class="image-preview"></div>
                                <div class="upload-placeholder">
                                    <i class="fas fa-cloud-upload-alt"></i>
                                    <p>Click to upload main product image</p>
                                    <small>JPG, PNG, GIF, WEBP (Max: 5MB)</small>
                                </div>
                            </div>
                        </div>

                        <div class="form-group">
                            <label class="form-label">Gallery Images</label>
                            <div class="image-upload-area">
                                <input type="file" name="gallery_images[]" class="image-input" 
                                       accept="image/*" multiple>
                                <div class="image-preview gallery-preview"></div>
                                <div class="upload-placeholder">
                                    <i class="fas fa-images"></i>
                                    <p>Upload additional product images</p>
                                    <small>Multiple files allowed</small>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Category & Status -->
                <div class="admin-card mb-30">
                    <div class="admin-card-header">
                        <h3 class="admin-card-title">Category & Status</h3>
                    </div>
                    <div class="admin-card-body">
                        <div class="form-group">
                            <label class="form-label">Category *</label>
                            <select name="category_id" class="form-control" data-validation="required" required>
                                <option value="">Select Category</option>
                                <?php foreach ($categories as $category): ?>
                                    <option value="<?php echo $category['id']; ?>" 
                                            <?php echo (($_POST['category_id'] ?? '') == $category['id']) ? 'selected' : ''; ?>>
                                        <?php echo htmlspecialchars($category['name']); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>

                        <div class="form-group">
                            <label class="form-label">Status</label>
                            <select name="status" class="form-control">
                                <option value="active" <?php echo (($_POST['status'] ?? 'active') === 'active') ? 'selected' : ''; ?>>
                                    Active
                                </option>
                                <option value="inactive" <?php echo (($_POST['status'] ?? '') === 'inactive') ? 'selected' : ''; ?>>
                                    Inactive
                                </option>
                                <option value="draft" <?php echo (($_POST['status'] ?? '') === 'draft') ? 'selected' : ''; ?>>
                                    Draft
                                </option>
                            </select>
                        </div>

                        <div class="form-group mb-0">
                            <div class="form-check">
                                <input type="checkbox" name="featured" value="1" class="form-check-input" 
                                       id="featured" <?php echo isset($_POST['featured']) ? 'checked' : ''; ?>>
                                <label class="form-check-label" for="featured">
                                    Featured Product
                                </label>
                                <small class="form-text text-muted">Show in featured products section</small>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Actions -->
                <div class="admin-card">
                    <div class="admin-card-body">
                        <button type="submit" class="btn btn-primary w-100 mb-2">
                            <i class="fas fa-save"></i> Add Product
                        </button>
                        <a href="index.php" class="btn btn-outline-secondary w-100">
                            <i class="fas fa-times"></i> Cancel
                        </a>
                    </div>
                </div>
            </div>
        </div>
    </form>
</div>

<?php
// Close the layout if using basic structure
if (!file_exists('../layouts/header.php')) {
    echo '</div></div></div>';
}
?>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>

<style>
.image-upload-area {
    position: relative;
    border: 2px dashed #ddd;
    border-radius: 8px;
    padding: 20px;
    text-align: center;
    transition: border-color 0.3s ease;
    cursor: pointer;
}

.image-upload-area:hover {
    border-color: #3498db;
}

.image-upload-area .image-input {
    position: absolute;
    top: 0;
    left: 0;
    width: 100%;
    height: 100%;
    opacity: 0;
    cursor: pointer;
}

.image-preview img {
    max-width: 100%;
    max-height: 200px;
    border-radius: 6px;
    margin: 5px;
}

.upload-placeholder {
    color: #999;
}

.upload-placeholder i {
    font-size: 2rem;
    margin-bottom: 10px;
    color: #ccc;
}

.variant-item {
    background: #f8f9fa;
}

.gallery-preview {
    display: flex;
    flex-wrap: wrap;
    gap: 10px;
}

.gallery-preview img {
    width: 80px;
    height: 80px;
    object-fit: cover;
}

.form-row {
    display: flex;
    gap: 15px;
}

.form-group {
    flex: 1;
    margin-bottom: 20px;
}

.admin-card {
    background: white;
    border-radius: 8px;
    box-shadow: 0 2px 10px rgba(0,0,0,0.1);
    margin-bottom: 20px;
    border: 1px solid #e9ecef;
}

.admin-card-header {
    padding: 20px;
    border-bottom: 1px solid #eee;
    background: #f8f9fa;
    border-radius: 8px 8px 0 0;
}

.admin-card-title {
    margin: 0;
    font-size: 1.2rem;
    font-weight: 600;
    color: #495057;
}

.admin-card-body {
    padding: 20px;
}

.form-label {
    font-weight: 500;
    margin-bottom: 8px;
    display: block;
    color: #495057;
}

.form-control {
    width: 100%;
    padding: 10px 15px;
    border: 1px solid #ced4da;
    border-radius: 6px;
    font-size: 14px;
    transition: border-color 0.15s ease-in-out;
}

.form-control:focus {
    border-color: #007bff;
    box-shadow: 0 0 0 0.2rem rgba(0,123,255,.25);
    outline: 0;
}

.btn {
    padding: 10px 20px;
    border: none;
    border-radius: 6px;
    cursor: pointer;
    text-decoration: none;
    display: inline-block;
    text-align: center;
    font-weight: 500;
    transition: all 0.15s ease-in-out;
}

.btn-primary {
    background: #007bff;
    color: white;
    border: 1px solid #007bff;
}

.btn-primary:hover {
    background: #0056b3;
    border-color: #0056b3;
}

.btn-outline-secondary {
    background: transparent;
    color: #6c757d;
    border: 1px solid #6c757d;
}

.btn-outline-secondary:hover {
    background: #6c757d;
    color: white;
}

.w-100 { width: 100% !important; }
.mb-2 { margin-bottom: 0.5rem !important; }

.alert {
    padding: 15px;
    border-radius: 6px;
    margin-bottom: 20px;
    border: 1px solid transparent;
}

.alert-danger {
    background: #f8d7da;
    color: #721c24;
    border-color: #f5c6cb;
}

.alert-success {
    background: #d4edda;
    color: #155724;
    border-color: #c3e6cb;
}

.page-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 30px;
    padding-bottom: 20px;
    border-bottom: 1px solid #e9ecef;
}

.page-title {
    font-size: 1.8rem;
    font-weight: 600;
    color: #495057;
    margin: 0;
}

.page-actions .btn {
    margin-left: 10px;
}

.row { margin: 0 -15px; }
.col-md-6, .col-md-8, .col-md-4 { padding: 0 15px; }

textarea.form-control {
    resize: vertical;
    min-height: 100px;
}
</style>

<script>
document.addEventListener('DOMContentLoaded', function() {
    // Image preview functionality
    const imageInputs = document.querySelectorAll('.image-input');
    
    imageInputs.forEach(function(input) {
        input.addEventListener('change', function() {
            const files = this.files;
            const preview = this.parentNode.querySelector('.image-preview');
            const placeholder = this.parentNode.querySelector('.upload-placeholder');
            const isGallery = this.name.includes('gallery');
            
            preview.innerHTML = '';
            
            if (files.length > 0) {
                for (let i = 0; i < files.length; i++) {
                    const file = files[i];
                    const reader = new FileReader();
                    
                    reader.onload = function(e) {
                        const img = document.createElement('img');
                        img.src = e.target.result;
                        preview.appendChild(img);
                    };
                    
                    reader.readAsDataURL(file);
                    
                    // For single image upload, break after first
                    if (!isGallery) break;
                }
                
                placeholder.style.display = 'none';
            } else {
                placeholder.style.display = 'block';
            }
        });
    });
    
    // Auto-generate SKU from product name
    const nameInput = document.querySelector('input[name="name"]');
    const skuInput = document.querySelector('input[name="sku"]');
    
    if (nameInput && skuInput) {
        nameInput.addEventListener('input', function() {
            const name = this.value;
            const sku = generateSKU(name);
            skuInput.value = sku;
        });
    }
    
    function generateSKU(name) {
        return name.toUpperCase()
                  .replace(/[^A-Z0-9]/g, '')
                  .substring(0, 8) + 
               Math.random().toString(36).substr(2, 4).toUpperCase();
    }
    
    // Form validation
    const form = document.querySelector('form');
    if (form) {
        form.addEventListener('submit', function(e) {
            let isValid = true;
            
            // Check required fields
            const requiredFields = this.querySelectorAll('[required]');
            requiredFields.forEach(function(field) {
                if (!field.value.trim()) {
                    field.style.borderColor = '#dc3545';
                    isValid = false;
                } else {
                    field.style.borderColor = '#ddd';
                }
            });
            
            if (!isValid) {
                e.preventDefault();
                alert('Please fill in all required fields');
            }
        });
    }
});
</script>

<?php
// Include footer if exists
if (file_exists('../layouts/footer.php')) {
    include '../layouts/footer.php';
} else {
    echo '</body></html>';
}
?>