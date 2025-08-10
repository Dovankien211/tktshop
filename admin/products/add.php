<?php
/**
 * TKT Shop - Add Product Page (Fixed & Beautiful)
 * Trang thêm sản phẩm mới với giao diện đẹp
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

$page_title = "Thêm sản phẩm mới";
$errors = [];
$success = '';

// Helper Functions
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
        return ['success' => false, 'message' => 'Định dạng ảnh không hợp lệ. Vui lòng sử dụng JPG, PNG, GIF hoặc WEBP.'];
    }
    
    if ($file['size'] > $max_size) {
        return ['success' => false, 'message' => 'Kích thước ảnh quá lớn. Tối đa 5MB.'];
    }
    
    // Generate unique filename
    $extension = pathinfo($file['name'], PATHINFO_EXTENSION);
    $filename = 'product_' . uniqid() . '_' . time() . '.' . $extension;
    $filepath = $upload_dir . $filename;
    
    if (move_uploaded_file($file['tmp_name'], $filepath)) {
        return ['success' => true, 'filename' => $filename];
    } else {
        return ['success' => false, 'message' => 'Không thể upload ảnh.'];
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
        $errors[] = "Tên sản phẩm là bắt buộc";
    }
    
    if (empty($description)) {
        $errors[] = "Mô tả sản phẩm là bắt buộc";
    }
    
    if (!is_numeric($price) || $price <= 0) {
        $errors[] = "Giá sản phẩm phải là số dương";
    }
    
    if (!empty($sale_price) && (!is_numeric($sale_price) || $sale_price >= $price)) {
        $errors[] = "Giá khuyến mãi phải nhỏ hơn giá gốc";
    }
    
    if (empty($sku)) {
        $errors[] = "Mã SKU là bắt buộc";
    } else {
        // Kiểm tra SKU trùng lặp
        try {
            $check_sku = $pdo->prepare("SELECT COUNT(*) FROM products WHERE sku = ?");
            $check_sku->execute([$sku]);
            if ($check_sku->fetchColumn() > 0) {
                $errors[] = "Mã SKU đã tồn tại";
            }
        } catch (PDOException $e) {
            $errors[] = "Lỗi kiểm tra SKU";
        }
    }
    
    if (!$category_id) {
        $errors[] = "Vui lòng chọn danh mục";
    }
    
    if (!is_numeric($quantity) || $quantity < 0) {
        $errors[] = "Số lượng phải là số không âm";
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
            
            $pdo->commit();
            $success = "Thêm sản phẩm thành công!";
            
            // Redirect sau khi thành công
            header("Location: index.php?success=" . urlencode($success));
            exit;
            
        } catch (Exception $e) {
            $pdo->rollback();
            $errors[] = "Lỗi database: " . $e->getMessage();
        }
    }
}

// Lấy danh sách categories
try {
    $categories = $pdo->query("SELECT * FROM categories WHERE status = 'active' ORDER BY name")->fetchAll();
} catch (PDOException $e) {
    $categories = [];
    $errors[] = "Không thể tải danh mục";
}
?>

<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo $page_title; ?> - TKT Admin</title>
    
    <!-- Bootstrap 5 -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <!-- Font Awesome -->
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
    <!-- Custom Admin CSS -->
    <style>
        :root {
            --primary-color: #3b82f6;
            --secondary-color: #64748b;
            --success-color: #10b981;
            --warning-color: #f59e0b;
            --danger-color: #ef4444;
            --dark-color: #1e293b;
            --light-bg: #f8fafc;
            --border-color: #e2e8f0;
            --sidebar-width: 280px;
        }

        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: 'Inter', -apple-system, BlinkMacSystemFont, sans-serif;
            background-color: var(--light-bg);
            color: #334155;
            line-height: 1.6;
        }

        /* Sidebar Styles */
        .admin-sidebar {
            position: fixed;
            top: 0;
            left: 0;
            height: 100vh;
            width: var(--sidebar-width);
            background: linear-gradient(180deg, #1e293b 0%, #334155 100%);
            box-shadow: 4px 0 12px rgba(0, 0, 0, 0.15);
            z-index: 1000;
            overflow-y: auto;
        }

        .sidebar-header {
            padding: 1.5rem;
            border-bottom: 1px solid #475569;
            background: rgba(30, 41, 59, 0.8);
        }

        .sidebar-brand {
            color: #f1f5f9;
            font-size: 1.4rem;
            font-weight: 700;
            text-decoration: none;
            display: flex;
            align-items: center;
        }

        .sidebar-nav {
            padding: 1rem 0;
        }

        .nav-link {
            color: #cbd5e1;
            padding: 0.875rem 1.5rem;
            display: flex;
            align-items: center;
            text-decoration: none;
            transition: all 0.3s ease;
            border-left: 3px solid transparent;
        }

        .nav-link:hover {
            background: rgba(59, 130, 246, 0.1);
            color: #60a5fa;
            border-left-color: var(--primary-color);
        }

        .nav-link.active {
            background: rgba(59, 130, 246, 0.15);
            color: #60a5fa;
            border-left-color: var(--primary-color);
        }

        .nav-icon {
            width: 20px;
            margin-right: 0.75rem;
            text-align: center;
        }

        /* Main Content */
        .main-content {
            margin-left: var(--sidebar-width);
            min-height: 100vh;
            background: var(--light-bg);
        }

        .content-header {
            background: white;
            border-bottom: 1px solid var(--border-color);
            padding: 1.5rem 2rem;
            box-shadow: 0 1px 3px rgba(0, 0, 0, 0.1);
        }

        .page-title {
            font-size: 1.875rem;
            font-weight: 700;
            color: var(--dark-color);
            margin: 0;
        }

        .breadcrumb {
            background: none;
            padding: 0;
            margin: 0.5rem 0 0 0;
            font-size: 0.875rem;
        }

        .content-body {
            padding: 2rem;
        }

        /* Cards */
        .card {
            background: white;
            border: 1px solid var(--border-color);
            border-radius: 12px;
            box-shadow: 0 1px 3px rgba(0, 0, 0, 0.1);
            overflow: hidden;
        }

        .card-header {
            background: #f8fafc;
            border-bottom: 1px solid var(--border-color);
            padding: 1.25rem 1.5rem;
        }

        .card-title {
            font-size: 1.125rem;
            font-weight: 600;
            color: var(--dark-color);
            margin: 0;
            display: flex;
            align-items: center;
        }

        .card-body {
            padding: 1.5rem;
        }

        /* Form Styles */
        .form-label {
            font-weight: 600;
            color: var(--dark-color);
            margin-bottom: 0.5rem;
            display: block;
        }

        .form-control {
            border: 2px solid var(--border-color);
            border-radius: 8px;
            padding: 0.75rem 1rem;
            font-size: 0.875rem;
            transition: all 0.3s ease;
            background: white;
        }

        .form-control:focus {
            border-color: var(--primary-color);
            box-shadow: 0 0 0 3px rgba(59, 130, 246, 0.1);
            outline: none;
        }

        .form-control.is-invalid {
            border-color: var(--danger-color);
        }

        /* Buttons */
        .btn {
            padding: 0.75rem 1.5rem;
            border-radius: 8px;
            font-weight: 600;
            font-size: 0.875rem;
            transition: all 0.3s ease;
            border: none;
            cursor: pointer;
            display: inline-flex;
            align-items: center;
            text-decoration: none;
        }

        .btn i {
            margin-right: 0.5rem;
        }

        .btn-primary {
            background: var(--primary-color);
            color: white;
        }

        .btn-primary:hover {
            background: #2563eb;
            transform: translateY(-1px);
            box-shadow: 0 4px 12px rgba(59, 130, 246, 0.4);
        }

        .btn-secondary {
            background: var(--secondary-color);
            color: white;
        }

        .btn-secondary:hover {
            background: #475569;
        }

        .btn-outline-secondary {
            background: transparent;
            color: var(--secondary-color);
            border: 2px solid var(--border-color);
        }

        .btn-outline-secondary:hover {
            background: var(--secondary-color);
            color: white;
        }

        /* Alerts */
        .alert {
            padding: 1rem 1.25rem;
            border-radius: 8px;
            margin-bottom: 1.5rem;
            border: 1px solid transparent;
            display: flex;
            align-items: flex-start;
        }

        .alert i {
            margin-right: 0.75rem;
            margin-top: 0.125rem;
        }

        .alert-danger {
            background: #fef2f2;
            color: #b91c1c;
            border-color: #fecaca;
        }

        .alert-success {
            background: #f0fdf4;
            color: #166534;
            border-color: #bbf7d0;
        }

        /* Image Upload Area */
        .image-upload-area {
            position: relative;
            border: 2px dashed var(--border-color);
            border-radius: 12px;
            padding: 2rem;
            text-align: center;
            transition: all 0.3s ease;
            cursor: pointer;
            background: #fafbfb;
        }

        .image-upload-area:hover {
            border-color: var(--primary-color);
            background: rgba(59, 130, 246, 0.02);
        }

        .image-upload-area.dragover {
            border-color: var(--primary-color);
            background: rgba(59, 130, 246, 0.05);
        }

        .image-input {
            position: absolute;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            opacity: 0;
            cursor: pointer;
        }

        .upload-placeholder {
            color: var(--secondary-color);
        }

        .upload-placeholder i {
            font-size: 3rem;
            margin-bottom: 1rem;
            color: #cbd5e1;
        }

        .image-preview {
            margin-top: 1rem;
        }

        .image-preview img {
            max-width: 200px;
            max-height: 200px;
            border-radius: 8px;
            box-shadow: 0 2px 8px rgba(0, 0, 0, 0.15);
        }

        .gallery-preview {
            display: flex;
            flex-wrap: wrap;
            gap: 0.75rem;
            margin-top: 1rem;
        }

        .gallery-preview img {
            width: 80px;
            height: 80px;
            object-fit: cover;
            border-radius: 6px;
            box-shadow: 0 2px 4px rgba(0, 0, 0, 0.1);
        }

        /* Responsive */
        @media (max-width: 1024px) {
            .admin-sidebar {
                transform: translateX(-100%);
                transition: transform 0.3s ease;
            }

            .admin-sidebar.show {
                transform: translateX(0);
            }

            .main-content {
                margin-left: 0;
            }
        }

        @media (max-width: 768px) {
            .content-body {
                padding: 1rem;
            }

            .card-body {
                padding: 1rem;
            }
        }

        /* Loading States */
        .btn.loading {
            opacity: 0.7;
            pointer-events: none;
        }

        .btn.loading::after {
            content: '';
            width: 16px;
            height: 16px;
            border: 2px solid currentColor;
            border-top-color: transparent;
            border-radius: 50%;
            animation: spin 1s linear infinite;
            margin-left: 0.5rem;
        }

        @keyframes spin {
            to {
                transform: rotate(360deg);
            }
        }

        /* Custom Scrollbar */
        .admin-sidebar::-webkit-scrollbar {
            width: 6px;
        }

        .admin-sidebar::-webkit-scrollbar-track {
            background: #334155;
        }

        .admin-sidebar::-webkit-scrollbar-thumb {
            background: #64748b;
            border-radius: 3px;
        }

        .admin-sidebar::-webkit-scrollbar-thumb:hover {
            background: #94a3b8;
        }
    </style>
</head>
<body>
    <!-- Sidebar -->
    <div class="admin-sidebar">
        <div class="sidebar-header">
            <a href="../dashboard.php" class="sidebar-brand">
                <i class="fas fa-store me-2"></i>
                TKT Admin
            </a>
        </div>
        
        <nav class="sidebar-nav">
            <a href="../dashboard.php" class="nav-link">
                <i class="fas fa-tachometer-alt nav-icon"></i>
                Dashboard
            </a>
            <a href="index.php" class="nav-link active">
                <i class="fas fa-box nav-icon"></i>
                Quản lý sản phẩm
            </a>
            <a href="../categories/index.php" class="nav-link">
                <i class="fas fa-tags nav-icon"></i>
                Danh mục
            </a>
            <a href="../orders/index.php" class="nav-link">
                <i class="fas fa-shopping-cart nav-icon"></i>
                Đơn hàng
            </a>
            <a href="../users/index.php" class="nav-link">
                <i class="fas fa-users nav-icon"></i>
                Người dùng
            </a>
        </nav>
    </div>

    <!-- Main Content -->
    <div class="main-content">
        <!-- Header -->
        <div class="content-header">
            <div class="d-flex justify-content-between align-items-start">
                <div>
                    <h1 class="page-title"><?php echo $page_title; ?></h1>
                    <nav aria-label="breadcrumb">
                        <ol class="breadcrumb">
                            <li class="breadcrumb-item"><a href="../dashboard.php">Dashboard</a></li>
                            <li class="breadcrumb-item"><a href="index.php">Sản phẩm</a></li>
                            <li class="breadcrumb-item active">Thêm mới</li>
                        </ol>
                    </nav>
                </div>
                <a href="index.php" class="btn btn-outline-secondary">
                    <i class="fas fa-arrow-left"></i>
                    Quay lại
                </a>
            </div>
        </div>

        <!-- Content Body -->
        <div class="content-body">
            <!-- Alert Messages -->
            <?php if (!empty($errors)): ?>
                <div class="alert alert-danger">
                    <i class="fas fa-exclamation-triangle"></i>
                    <div>
                        <strong>Có lỗi xảy ra:</strong>
                        <ul class="mb-0 mt-2">
                            <?php foreach ($errors as $error): ?>
                                <li><?php echo htmlspecialchars($error); ?></li>
                            <?php endforeach; ?>
                        </ul>
                    </div>
                </div>
            <?php endif; ?>

            <?php if ($success): ?>
                <div class="alert alert-success">
                    <i class="fas fa-check-circle"></i>
                    <div><?php echo htmlspecialchars($success); ?></div>
                </div>
            <?php endif; ?>

            <!-- Form -->
            <form method="POST" enctype="multipart/form-data" id="productForm">
                <div class="row">
                    <!-- Left Column -->
                    <div class="col-lg-8">
                        <!-- Basic Information -->
                        <div class="card mb-4">
                            <div class="card-header">
                                <h3 class="card-title">
                                    <i class="fas fa-info-circle me-2"></i>
                                    Thông tin cơ bản
                                </h3>
                            </div>
                            <div class="card-body">
                                <div class="row">
                                    <div class="col-12">
                                        <div class="mb-3">
                                            <label class="form-label">Tên sản phẩm *</label>
                                            <input type="text" name="name" class="form-control" 
                                                   value="<?php echo htmlspecialchars($_POST['name'] ?? ''); ?>" 
                                                   placeholder="Nhập tên sản phẩm" required>
                                        </div>
                                    </div>
                                    
                                    <div class="col-md-6">
                                        <div class="mb-3">
                                            <label class="form-label">Mã SKU *</label>
                                            <input type="text" name="sku" class="form-control" 
                                                   value="<?php echo htmlspecialchars($_POST['sku'] ?? ''); ?>" 
                                                   placeholder="VD: SP001" required>
                                            <small class="text-muted">Mã định danh duy nhất cho sản phẩm</small>
                                        </div>
                                    </div>
                                    
                                    <div class="col-md-6">
                                        <div class="mb-3">
                                            <label class="form-label">Thương hiệu</label>
                                            <input type="text" name="brand" class="form-control" 
                                                   value="<?php echo htmlspecialchars($_POST['brand'] ?? ''); ?>" 
                                                   placeholder="VD: Nike, Adidas">
                                        </div>
                                    </div>
                                    
                                    <div class="col-12">
                                        <div class="mb-3">
                                            <label class="form-label">Mô tả ngắn</label>
                                            <textarea name="short_description" class="form-control" rows="3" 
                                                      placeholder="Mô tả ngắn gọn về sản phẩm (tối đa 200 ký tự)"
                                                      maxlength="200"><?php echo htmlspecialchars($_POST['short_description'] ?? ''); ?></textarea>
                                        </div>
                                    </div>
                                    
                                    <div class="col-12">
                                        <div class="mb-3">
                                            <label class="form-label">Mô tả chi tiết *</label>
                                            <textarea name="description" class="form-control" rows="6" 
                                                      placeholder="Mô tả chi tiết về sản phẩm, tính năng, chất liệu..."
                                                      required><?php echo htmlspecialchars($_POST['description'] ?? ''); ?></textarea>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- Pricing -->
                        <div class="card mb-4">
                            <div class="card-header">
                                <h3 class="card-title">
                                    <i class="fas fa-money-bill-wave me-2"></i>
                                    Giá cả
                                </h3>
                            </div>
                            <div class="card-body">
                                <div class="row">
                                    <div class="col-md-6">
                                        <div class="mb-3">
                                            <label class="form-label">Giá bán *</label>
                                            <div class="input-group">
                                                <input type="number" name="price" class="form-control" 
                                                       value="<?php echo $_POST['price'] ?? ''; ?>" 
                                                       step="1000" min="0" placeholder="0" required>
                                                <span class="input-group-text">₫</span>
                                            </div>
                                        </div>
                                    </div>
                                    <div class="col-md-6">
                                        <div class="mb-3">
                                            <label class="form-label">Giá khuyến mãi</label>
                                            <div class="input-group">
                                                <input type="number" name="sale_price" class="form-control" 
                                                       value="<?php echo $_POST['sale_price'] ?? ''; ?>" 
                                                       step="1000" min="0" placeholder="0">
                                                <span class="input-group-text">₫</span>
                                            </div>
                                            <small class="text-muted">Để trống nếu không có khuyến mãi</small>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- Inventory -->
                        <div class="card mb-4">
                            <div class="card-header">
                                <h3 class="card-title">
                                    <i class="fas fa-warehouse me-2"></i>
                                    Kho hàng
                                </h3>
                            </div>
                            <div class="card-body">
                                <div class="row">
                                    <div class="col-md-6">
                                        <div class="mb-3">
                                            <label class="form-label">Số lượng tồn kho *</label>
                                            <input type="number" name="quantity" class="form-control" 
                                                   value="<?php echo $_POST['quantity'] ?? '0'; ?>" 
                                                   min="0" placeholder="0" required>
                                        </div>
                                    </div>
                                    <div class="col-md-6">
                                        <div class="mb-3">
                                            <label class="form-label">Số lượng tối thiểu</label>
                                            <input type="number" name="min_quantity" class="form-control" 
                                                   value="<?php echo $_POST['min_quantity'] ?? '1'; ?>" 
                                                   min="1" placeholder="1">
                                            <small class="text-muted">Cảnh báo khi tồn kho dưới mức này</small>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- Shipping -->
                        <div class="card mb-4">
                            <div class="card-header">
                                <h3 class="card-title">
                                    <i class="fas fa-shipping-fast me-2"></i>
                                    Thông tin vận chuyển
                                </h3>
                            </div>
                            <div class="card-body">
                                <div class="row">
                                    <div class="col-md-6">
                                        <div class="mb-3">
                                            <label class="form-label">Cân nặng (kg)</label>
                                            <input type="number" name="weight" class="form-control" 
                                                   value="<?php echo $_POST['weight'] ?? ''; ?>" 
                                                   step="0.1" min="0" placeholder="0.5">
                                        </div>
                                    </div>
                                    <div class="col-md-6">
                                        <div class="mb-3">
                                            <label class="form-label">Kích thước (D×R×C cm)</label>
                                            <input type="text" name="dimensions" class="form-control" 
                                                   value="<?php echo htmlspecialchars($_POST['dimensions'] ?? ''); ?>"
                                                   placeholder="VD: 30×20×10">
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- SEO -->
                        <div class="card">
                            <div class="card-header">
                                <h3 class="card-title">
                                    <i class="fas fa-search me-2"></i>
                                    Tối ưu SEO
                                </h3>
                            </div>
                            <div class="card-body">
                                <div class="mb-3">
                                    <label class="form-label">Tiêu đề SEO</label>
                                    <input type="text" name="meta_title" class="form-control" 
                                           value="<?php echo htmlspecialchars($_POST['meta_title'] ?? ''); ?>"
                                           maxlength="60" placeholder="Tiêu đề trang cho SEO">
                                    <small class="text-muted">Khuyến nghị: 50-60 ký tự</small>
                                </div>
                                
                                <div class="mb-3">
                                    <label class="form-label">Mô tả SEO</label>
                                    <textarea name="meta_description" class="form-control" rows="3" 
                                              maxlength="160" placeholder="Mô tả ngắn gọn cho công cụ tìm kiếm"><?php echo htmlspecialchars($_POST['meta_description'] ?? ''); ?></textarea>
                                    <small class="text-muted">Khuyến nghị: 150-160 ký tự</small>
                                </div>
                                
                                <div class="mb-3">
                                    <label class="form-label">Từ khóa</label>
                                    <input type="text" name="tags" class="form-control" 
                                           value="<?php echo htmlspecialchars($_POST['tags'] ?? ''); ?>"
                                           placeholder="giày, sneaker, thể thao">
                                    <small class="text-muted">Phân cách bằng dấu phẩy</small>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Right Column -->
                    <div class="col-lg-4">
                        <!-- Product Images -->
                        <div class="card mb-4">
                            <div class="card-header">
                                <h3 class="card-title">
                                    <i class="fas fa-images me-2"></i>
                                    Hình ảnh sản phẩm
                                </h3>
                            </div>
                            <div class="card-body">
                                <div class="mb-4">
                                    <label class="form-label">Ảnh đại diện</label>
                                    <div class="image-upload-area" id="mainImageArea">
                                        <input type="file" name="main_image" class="image-input" accept="image/*">
                                        <div class="image-preview" id="mainImagePreview"></div>
                                        <div class="upload-placeholder">
                                            <i class="fas fa-cloud-upload-alt"></i>
                                            <p class="mb-2"><strong>Chọn ảnh đại diện</strong></p>
                                            <p class="mb-0 small">JPG, PNG, GIF, WEBP (Tối đa 5MB)</p>
                                        </div>
                                    </div>
                                </div>

                                <div class="mb-3">
                                    <label class="form-label">Thư viện ảnh</label>
                                    <div class="image-upload-area" id="galleryArea">
                                        <input type="file" name="gallery_images[]" class="image-input" 
                                               accept="image/*" multiple>
                                        <div class="gallery-preview" id="galleryPreview"></div>
                                        <div class="upload-placeholder">
                                            <i class="fas fa-images"></i>
                                            <p class="mb-2"><strong>Chọn nhiều ảnh</strong></p>
                                            <p class="mb-0 small">Có thể chọn nhiều file cùng lúc</p>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- Category & Status -->
                        <div class="card mb-4">
                            <div class="card-header">
                                <h3 class="card-title">
                                    <i class="fas fa-cog me-2"></i>
                                    Danh mục & Trạng thái
                                </h3>
                            </div>
                            <div class="card-body">
                                <div class="mb-3">
                                    <label class="form-label">Danh mục *</label>
                                    <select name="category_id" class="form-control" required>
                                        <option value="">Chọn danh mục</option>
                                        <?php foreach ($categories as $category): ?>
                                            <option value="<?php echo $category['id']; ?>" 
                                                    <?php echo (($_POST['category_id'] ?? '') == $category['id']) ? 'selected' : ''; ?>>
                                                <?php echo htmlspecialchars($category['name']); ?>
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>

                                <div class="mb-3">
                                    <label class="form-label">Trạng thái</label>
                                    <select name="status" class="form-control">
                                        <option value="active" <?php echo (($_POST['status'] ?? 'active') === 'active') ? 'selected' : ''; ?>>
                                            Hoạt động
                                        </option>
                                        <option value="inactive" <?php echo (($_POST['status'] ?? '') === 'inactive') ? 'selected' : ''; ?>>
                                            Tạm ẩn
                                        </option>
                                        <option value="draft" <?php echo (($_POST['status'] ?? '') === 'draft') ? 'selected' : ''; ?>>
                                            Bản nháp
                                        </option>
                                    </select>
                                </div>

                                <div class="form-check">
                                    <input type="checkbox" name="featured" value="1" class="form-check-input" 
                                           id="featured" <?php echo isset($_POST['featured']) ? 'checked' : ''; ?>>
                                    <label class="form-check-label" for="featured">
                                        <strong>Sản phẩm nổi bật</strong>
                                    </label>
                                    <small class="text-muted d-block">Hiển thị trong khu vực sản phẩm nổi bật</small>
                                </div>
                            </div>
                        </div>

                        <!-- Actions -->
                        <div class="card">
                            <div class="card-body">
                                <button type="submit" class="btn btn-primary w-100 mb-3" id="submitBtn">
                                    <i class="fas fa-save"></i>
                                    Thêm sản phẩm
                                </button>
                                <a href="index.php" class="btn btn-outline-secondary w-100">
                                    <i class="fas fa-times"></i>
                                    Hủy bỏ
                                </a>
                            </div>
                        </div>
                    </div>
                </div>
            </form>
        </div>
    </div>

    <!-- Bootstrap JS -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            // Image preview functionality
            setupImagePreviews();
            
            // Auto-generate SKU from product name
            setupSKUGeneration();
            
            // Form validation
            setupFormValidation();
            
            // Character counters
            setupCharacterCounters();
        });

        function setupImagePreviews() {
            // Main image preview
            const mainImageInput = document.querySelector('input[name="main_image"]');
            const mainImagePreview = document.getElementById('mainImagePreview');
            const mainImageArea = document.getElementById('mainImageArea');
            
            if (mainImageInput) {
                mainImageInput.addEventListener('change', function() {
                    handleImagePreview(this, mainImagePreview, false);
                });
                
                // Drag and drop
                setupDragAndDrop(mainImageArea, mainImageInput);
            }
            
            // Gallery images preview
            const galleryInput = document.querySelector('input[name="gallery_images[]"]');
            const galleryPreview = document.getElementById('galleryPreview');
            const galleryArea = document.getElementById('galleryArea');
            
            if (galleryInput) {
                galleryInput.addEventListener('change', function() {
                    handleImagePreview(this, galleryPreview, true);
                });
                
                // Drag and drop
                setupDragAndDrop(galleryArea, galleryInput);
            }
        }

        function handleImagePreview(input, previewContainer, isMultiple) {
            const files = input.files;
            const placeholder = input.parentNode.querySelector('.upload-placeholder');
            
            previewContainer.innerHTML = '';
            
            if (files.length > 0) {
                for (let i = 0; i < files.length; i++) {
                    const file = files[i];
                    
                    // Validate file type
                    if (!file.type.match('image.*')) {
                        continue;
                    }
                    
                    const reader = new FileReader();
                    reader.onload = function(e) {
                        const img = document.createElement('img');
                        img.src = e.target.result;
                        img.style.maxWidth = isMultiple ? '80px' : '200px';
                        img.style.maxHeight = isMultiple ? '80px' : '200px';
                        img.style.borderRadius = '8px';
                        img.style.objectFit = 'cover';
                        img.style.boxShadow = '0 2px 8px rgba(0,0,0,0.15)';
                        previewContainer.appendChild(img);
                    };
                    reader.readAsDataURL(file);
                    
                    // For single image, break after first
                    if (!isMultiple) break;
                }
                
                placeholder.style.display = 'none';
            } else {
                placeholder.style.display = 'block';
            }
        }

        function setupDragAndDrop(area, input) {
            area.addEventListener('dragover', function(e) {
                e.preventDefault();
                this.classList.add('dragover');
            });
            
            area.addEventListener('dragleave', function(e) {
                e.preventDefault();
                this.classList.remove('dragover');
            });
            
            area.addEventListener('drop', function(e) {
                e.preventDefault();
                this.classList.remove('dragover');
                
                const files = e.dataTransfer.files;
                input.files = files;
                input.dispatchEvent(new Event('change'));
            });
        }

        function setupSKUGeneration() {
            const nameInput = document.querySelector('input[name="name"]');
            const skuInput = document.querySelector('input[name="sku"]');
            
            if (nameInput && skuInput) {
                nameInput.addEventListener('input', function() {
                    if (!skuInput.value) {
                        const name = this.value;
                        const sku = generateSKU(name);
                        skuInput.value = sku;
                    }
                });
            }
        }

        function generateSKU(name) {
            const cleanName = name.toUpperCase()
                                  .replace(/[^A-Z0-9]/g, '')
                                  .substring(0, 6);
            const random = Math.random().toString(36).substr(2, 4).toUpperCase();
            return cleanName + random;
        }

        function setupFormValidation() {
            const form = document.getElementById('productForm');
            const submitBtn = document.getElementById('submitBtn');
            
            if (form) {
                form.addEventListener('submit', function(e) {
                    const isValid = validateForm();
                    
                    if (!isValid) {
                        e.preventDefault();
                        return false;
                    }
                    
                    // Show loading state
                    submitBtn.classList.add('loading');
                    submitBtn.disabled = true;
                });
            }
        }

        function validateForm() {
            let isValid = true;
            const requiredFields = document.querySelectorAll('[required]');
            
            requiredFields.forEach(function(field) {
                if (!field.value.trim()) {
                    field.classList.add('is-invalid');
                    isValid = false;
                } else {
                    field.classList.remove('is-invalid');
                }
            });
            
            // Validate price
            const price = document.querySelector('input[name="price"]');
            const salePrice = document.querySelector('input[name="sale_price"]');
            
            if (price && parseFloat(price.value) <= 0) {
                price.classList.add('is-invalid');
                isValid = false;
            }
            
            if (salePrice && salePrice.value && parseFloat(salePrice.value) >= parseFloat(price.value)) {
                salePrice.classList.add('is-invalid');
                isValid = false;
            }
            
            if (!isValid) {
                // Scroll to first error
                const firstError = document.querySelector('.is-invalid');
                if (firstError) {
                    firstError.scrollIntoView({ behavior: 'smooth', block: 'center' });
                    firstError.focus();
                }
                
                // Show error message
                showNotification('Vui lòng kiểm tra lại thông tin đã nhập', 'error');
            }
            
            return isValid;
        }

        function setupCharacterCounters() {
            const fields = [
                { input: 'input[name="short_description"]', max: 200 },
                { input: 'input[name="meta_title"]', max: 60 },
                { input: 'textarea[name="meta_description"]', max: 160 }
            ];
            
            fields.forEach(function(field) {
                const input = document.querySelector(field.input);
                if (input) {
                    const counter = document.createElement('small');
                    counter.className = 'text-muted d-block mt-1';
                    input.parentNode.appendChild(counter);
                    
                    function updateCounter() {
                        const remaining = field.max - input.value.length;
                        counter.textContent = `${input.value.length}/${field.max} ký tự`;
                        counter.className = remaining < 20 ? 'text-warning d-block mt-1' : 'text-muted d-block mt-1';
                    }
                    
                    input.addEventListener('input', updateCounter);
                    updateCounter();
                }
            });
        }

        function showNotification(message, type = 'info') {
            const alert = document.createElement('div');
            alert.className = `alert alert-${type === 'error' ? 'danger' : type} alert-dismissible fade show`;
            alert.innerHTML = `
                <i class="fas fa-${type === 'error' ? 'exclamation-triangle' : 'info-circle'} me-2"></i>
                ${message}
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            `;
            
            const container = document.querySelector('.content-body');
            container.insertBefore(alert, container.firstChild);
            
            // Auto remove after 5 seconds
            setTimeout(() => {
                if (alert && alert.parentNode) {
                    alert.remove();
                }
            }, 5000);
        }

        // Real-time validation feedback
        document.querySelectorAll('.form-control').forEach(function(input) {
            input.addEventListener('blur', function() {
                if (this.hasAttribute('required') && !this.value.trim()) {
                    this.classList.add('is-invalid');
                } else {
                    this.classList.remove('is-invalid');
                }
            });
            
            input.addEventListener('input', function() {
                if (this.classList.contains('is-invalid') && this.value.trim()) {
                    this.classList.remove('is-invalid');
                }
            });
        });
    </script>
</body>
</html>