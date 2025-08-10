<?php
// Start session first
session_start();

require_once '../../config/database.php';
require_once '../../config/config.php';

// Helper functions
function setFlashMessage($type, $message) {
    $_SESSION['flash_message'] = [
        'type' => $type,
        'message' => $message
    ];
}

function getFlashMessage() {
    if (isset($_SESSION['flash_message'])) {
        $flash = $_SESSION['flash_message'];
        unset($_SESSION['flash_message']);
        return $flash;
    }
    return null;
}

function formatPrice($price) {
    return number_format($price, 0, ',', '.') . ' ₫';
}

function uploadImage($file, $folder = 'products') {
    $upload_dir = "../../uploads/{$folder}/";
    
    // Create directory if not exists
    if (!file_exists($upload_dir)) {
        mkdir($upload_dir, 0755, true);
    }
    
    // Validate file
    $allowed_types = ['image/jpeg', 'image/png', 'image/gif', 'image/webp'];
    $max_size = 5 * 1024 * 1024; // 5MB
    
    if (!in_array($file['type'], $allowed_types)) {
        throw new Exception('Invalid image format. Please use JPG, PNG, GIF, or WEBP.');
    }
    
    if ($file['size'] > $max_size) {
        throw new Exception('Image size too large. Maximum size is 5MB.');
    }
    
    // Generate unique filename
    $extension = pathinfo($file['name'], PATHINFO_EXTENSION);
    $filename = 'variant_' . uniqid() . '_' . time() . '.' . $extension;
    $filepath = $upload_dir . $filename;
    
    if (move_uploaded_file($file['tmp_name'], $filepath)) {
        return $filename;
    } else {
        throw new Exception('Failed to upload image.');
    }
}

// Kiểm tra quyền admin
if (!isset($_SESSION['admin_id'])) {
    header('Location: ../login.php');
    exit();
}

$product_id = isset($_GET['product_id']) ? (int)$_GET['product_id'] : 0;
if (!$product_id) {
    setFlashMessage('error', 'Không tìm thấy sản phẩm!');
    header('Location: index.php');
    exit();
}

// Lấy thông tin sản phẩm (sử dụng tên bảng chuẩn)
$product = null;
try {
    $stmt = $pdo->prepare("SELECT * FROM products WHERE id = ?");
    $stmt->execute([$product_id]);
    $product = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$product) {
        setFlashMessage('error', 'Không tìm thấy sản phẩm!');
        header('Location: index.php');
        exit();
    }
} catch (PDOException $e) {
    setFlashMessage('error', 'Lỗi database: ' . $e->getMessage());
    header('Location: index.php');
    exit();
}

// Lấy danh sách kích cỡ (sử dụng tên bảng chuẩn)
$sizes = [];
try {
    // Try standard table name first
    $stmt = $pdo->query("SELECT * FROM sizes WHERE status = 'active' ORDER BY sort_order ASC");
    $sizes = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    // If sizes table doesn't exist, create default sizes
    try {
        $pdo->exec("
            CREATE TABLE IF NOT EXISTS sizes (
                id INT AUTO_INCREMENT PRIMARY KEY,
                name VARCHAR(50) NOT NULL,
                sort_order INT DEFAULT 0,
                status VARCHAR(20) DEFAULT 'active',
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
            )
        ");
        
        // Insert default sizes
        $pdo->exec("
            INSERT INTO sizes (name, sort_order) VALUES 
            ('XS', 1), ('S', 2), ('M', 3), ('L', 4), ('XL', 5), ('XXL', 6)
        ");
        
        $stmt = $pdo->query("SELECT * FROM sizes WHERE status = 'active' ORDER BY sort_order ASC");
        $sizes = $stmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (PDOException $e2) {
        $error = "Lỗi khi tạo bảng sizes: " . $e2->getMessage();
    }
}

// Lấy danh sách màu sắc
$colors = [];
try {
    $stmt = $pdo->query("SELECT * FROM colors WHERE status = 'active' ORDER BY name ASC");
    $colors = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    // If colors table doesn't exist, create it
    try {
        $pdo->exec("
            CREATE TABLE IF NOT EXISTS colors (
                id INT AUTO_INCREMENT PRIMARY KEY,
                name VARCHAR(50) NOT NULL,
                color_code VARCHAR(7) DEFAULT '#000000',
                status VARCHAR(20) DEFAULT 'active',
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
            )
        ");
        
        // Insert default colors
        $pdo->exec("
            INSERT INTO colors (name, color_code) VALUES 
            ('Đỏ', '#FF0000'), ('Xanh dương', '#0000FF'), ('Xanh lá', '#00FF00'),
            ('Vàng', '#FFFF00'), ('Đen', '#000000'), ('Trắng', '#FFFFFF'),
            ('Xám', '#808080'), ('Nâu', '#8B4513')
        ");
        
        $stmt = $pdo->query("SELECT * FROM colors WHERE status = 'active' ORDER BY name ASC");
        $colors = $stmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (PDOException $e2) {
        $error = "Lỗi khi tạo bảng colors: " . $e2->getMessage();
    }
}

// Lấy danh sách biến thể hiện có
$variants = [];
try {
    // Check if product_variants table exists
    $stmt = $pdo->prepare("
        SELECT pv.*, s.name as size_name, c.name as color_name, c.color_code 
        FROM product_variants pv
        LEFT JOIN sizes s ON pv.size_id = s.id
        LEFT JOIN colors c ON pv.color_id = c.id
        WHERE pv.product_id = ?
        ORDER BY s.sort_order ASC, c.name ASC
    ");
    $stmt->execute([$product_id]);
    $variants = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    // Create product_variants table if it doesn't exist
    try {
        $pdo->exec("
            CREATE TABLE IF NOT EXISTS product_variants (
                id INT AUTO_INCREMENT PRIMARY KEY,
                product_id INT NOT NULL,
                size_id INT,
                color_id INT,
                sku VARCHAR(100),
                price_adjustment DECIMAL(10,2) DEFAULT 0,
                stock_quantity INT DEFAULT 0,
                sold_quantity INT DEFAULT 0,
                variant_image VARCHAR(255),
                status VARCHAR(20) DEFAULT 'active',
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                FOREIGN KEY (product_id) REFERENCES products(id) ON DELETE CASCADE
            )
        ");
        $variants = [];
    } catch (PDOException $e2) {
        $error = "Lỗi khi tạo bảng product_variants: " . $e2->getMessage();
    }
}

$error = '';
$success = '';

// Xử lý thêm biến thể
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'add_variant') {
    try {
        $size_id = (int)$_POST['size_id'];
        $color_id = (int)$_POST['color_id'];
        $price_adjustment = (float)$_POST['price_adjustment'];
        $stock_quantity = (int)$_POST['stock_quantity'];
        
        // Kiểm tra trùng lặp biến thể
        $stmt = $pdo->prepare("SELECT COUNT(*) FROM product_variants WHERE product_id = ? AND size_id = ? AND color_id = ?");
        $stmt->execute([$product_id, $size_id, $color_id]);
        if ($stmt->fetchColumn() > 0) {
            throw new Exception("Biến thể này đã tồn tại!");
        }
        
        // Tạo SKU
        $size_name = '';
        $color_name = '';
        
        if ($size_id > 0) {
            $stmt = $pdo->prepare("SELECT name FROM sizes WHERE id = ?");
            $stmt->execute([$size_id]);
            $size_name = $stmt->fetchColumn();
        }
        
        if ($color_id > 0) {
            $stmt = $pdo->prepare("SELECT name FROM colors WHERE id = ?");
            $stmt->execute([$color_id]);
            $color_name = $stmt->fetchColumn();
        }
        
        $sku = strtoupper($product['sku'] . '-' . $size_name . '-' . str_replace(' ', '', $color_name));
        
        // Xử lý upload ảnh biến thể
        $variant_image = null;
        if (isset($_FILES['variant_image']) && $_FILES['variant_image']['error'] === UPLOAD_ERR_OK) {
            $variant_image = uploadImage($_FILES['variant_image'], 'products');
        }
        
        // Insert biến thể
        $sql = "INSERT INTO product_variants (
                    product_id, size_id, color_id, sku, price_adjustment, 
                    stock_quantity, variant_image
                ) VALUES (?, ?, ?, ?, ?, ?, ?)";
        
        $stmt = $pdo->prepare($sql);
        $stmt->execute([
            $product_id, $size_id, $color_id, $sku, $price_adjustment, 
            $stock_quantity, $variant_image
        ]);
        
        $success = "Thêm biến thể thành công!";
        
        // Reload trang để cập nhật danh sách
        header("Location: variants.php?product_id=" . $product_id);
        exit();
        
    } catch (Exception $e) {
        $error = "Lỗi: " . $e->getMessage();
    }
}

// Xử lý xóa biến thể
if (isset($_GET['delete_variant'])) {
    try {
        $variant_id = (int)$_GET['delete_variant'];
        
        $stmt = $pdo->prepare("DELETE FROM product_variants WHERE id = ? AND product_id = ?");
        $stmt->execute([$variant_id, $product_id]);
        
        setFlashMessage('success', 'Xóa biến thể thành công!');
        header("Location: variants.php?product_id=" . $product_id);
        exit();
        
    } catch (Exception $e) {
        setFlashMessage('error', 'Lỗi khi xóa biến thể: ' . $e->getMessage());
    }
}

// Include header
include '../layouts/header.php';
?>

<div class="content-area">
    <div class="page-header">
        <h1 class="page-title">Quản lý biến thể - <?php echo htmlspecialchars($product['name']); ?></h1>
        <div class="page-actions">
            <a href="index.php" class="btn btn-light">
                <i class="fas fa-arrow-left"></i> Quay lại
            </a>
            <button type="button" class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#addVariantModal">
                <i class="fas fa-plus"></i> Thêm biến thể
            </button>
        </div>
    </div>

    <!-- Flash Messages -->
    <?php if ($error): ?>
        <div class="alert alert-danger">
            <i class="fas fa-exclamation-triangle"></i> <?php echo $error; ?>
        </div>
    <?php endif; ?>

    <?php if ($success): ?>
        <div class="alert alert-success">
            <i class="fas fa-check-circle"></i> <?php echo $success; ?>
        </div>
    <?php endif; ?>

    <?php
    $flash = getFlashMessage();
    if ($flash):
    ?>
        <div class="alert alert-<?php echo $flash['type'] === 'error' ? 'danger' : $flash['type']; ?>">
            <i class="fas fa-<?php echo $flash['type'] === 'error' ? 'exclamation-triangle' : 'check-circle'; ?>"></i> 
            <?php echo $flash['message']; ?>
        </div>
    <?php endif; ?>

    <!-- Thông tin sản phẩm -->
    <div class="admin-card mb-30">
        <div class="admin-card-header">
            <h3 class="admin-card-title">
                <i class="fas fa-info-circle"></i> Thông tin sản phẩm
            </h3>
        </div>
        <div class="admin-card-body">
            <div class="row">
                <div class="col-2">
                    <?php if ($product['main_image']): ?>
                        <img src="../../uploads/products/<?php echo $product['main_image']; ?>" 
                             alt="<?php echo htmlspecialchars($product['name']); ?>" 
                             class="img-fluid rounded">
                    <?php else: ?>
                        <div class="bg-light p-3 text-center rounded">
                            <i class="fas fa-image fa-3x text-muted"></i>
                        </div>
                    <?php endif; ?>
                </div>
                <div class="col-10">
                    <h4><?php echo htmlspecialchars($product['name']); ?></h4>
                    <p class="text-muted mb-2">
                        <strong>Mã sản phẩm:</strong> <?php echo htmlspecialchars($product['sku']); ?> |
                        <strong>Thương hiệu:</strong> <?php echo htmlspecialchars($product['brand'] ?? 'N/A'); ?> |
                        <strong>Giá:</strong> <?php echo formatPrice($product['price']); ?>
                    </p>
                    <p><?php echo htmlspecialchars($product['short_description'] ?? ''); ?></p>
                </div>
            </div>
        </div>
    </div>

    <!-- Danh sách biến thể -->
    <div class="admin-card">
        <div class="admin-card-header">
            <h3 class="admin-card-title">
                <i class="fas fa-list"></i> Danh sách biến thể (<?php echo count($variants); ?>)
            </h3>
        </div>
        <div class="admin-card-body">
            <?php if (empty($variants)): ?>
                <div class="text-center py-5">
                    <i class="fas fa-box-open fa-3x text-muted mb-3"></i>
                    <h5 class="text-muted">Chưa có biến thể nào</h5>
                    <p class="text-muted">Hãy thêm biến thể đầu tiên cho sản phẩm này</p>
                    <button type="button" class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#addVariantModal">
                        <i class="fas fa-plus"></i> Thêm biến thể
                    </button>
                </div>
            <?php else: ?>
                <div class="table-responsive">
                    <table class="table table-striped">
                        <thead>
                            <tr>
                                <th>SKU</th>
                                <th>Size</th>
                                <th>Màu sắc</th>
                                <th>Điều chỉnh giá</th>
                                <th>Tồn kho</th>
                                <th>Đã bán</th>
                                <th>Trạng thái</th>
                                <th>Thao tác</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($variants as $variant): ?>
                                <tr>
                                    <td><code><?php echo htmlspecialchars($variant['sku']); ?></code></td>
                                    <td><?php echo htmlspecialchars($variant['size_name'] ?? 'N/A'); ?></td>
                                    <td>
                                        <div class="d-flex align-items-center">
                                            <?php if ($variant['color_code']): ?>
                                                <span class="color-preview me-2" style="background-color: <?php echo $variant['color_code']; ?>"></span>
                                            <?php endif; ?>
                                            <?php echo htmlspecialchars($variant['color_name'] ?? 'N/A'); ?>
                                        </div>
                                    </td>
                                    <td><?php echo formatPrice($variant['price_adjustment']); ?></td>
                                    <td>
                                        <span class="badge bg-<?php echo $variant['stock_quantity'] > 0 ? 'success' : 'danger'; ?>">
                                            <?php echo $variant['stock_quantity']; ?>
                                        </span>
                                    </td>
                                    <td><?php echo $variant['sold_quantity']; ?></td>
                                    <td>
                                        <span class="badge bg-<?php echo $variant['status'] === 'active' ? 'success' : 'secondary'; ?>">
                                            <?php echo ucfirst($variant['status']); ?>
                                        </span>
                                    </td>
                                    <td>
                                        <div class="btn-group">
                                            <button class="btn btn-sm btn-outline-primary" onclick="editVariant(<?php echo $variant['id']; ?>)">
                                                <i class="fas fa-edit"></i>
                                            </button>
                                            <button class="btn btn-sm btn-outline-danger" onclick="deleteVariant(<?php echo $variant['id']; ?>)">
                                                <i class="fas fa-trash"></i>
                                            </button>
                                        </div>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php endif; ?>
        </div>
    </div>
</div>

<!-- Modal thêm biến thể -->
<div class="modal fade" id="addVariantModal" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <form method="POST" enctype="multipart/form-data">
                <input type="hidden" name="action" value="add_variant">
                <div class="modal-header">
                    <h5 class="modal-title">
                        <i class="fas fa-plus"></i> Thêm biến thể mới
                    </h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <div class="row">
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label for="size_id" class="form-label">Kích cỡ</label>
                                <select class="form-control" id="size_id" name="size_id">
                                    <option value="0">Không có size</option>
                                    <?php foreach ($sizes as $size): ?>
                                        <option value="<?php echo $size['id']; ?>">
                                            <?php echo htmlspecialchars($size['name']); ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label for="color_id" class="form-label">Màu sắc</label>
                                <select class="form-control" id="color_id" name="color_id">
                                    <option value="0">Không có màu</option>
                                    <?php foreach ($colors as $color): ?>
                                        <option value="<?php echo $color['id']; ?>" data-color="<?php echo $color['color_code']; ?>">
                                            <?php echo htmlspecialchars($color['name']); ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                        </div>
                    </div>
                    
                    <div class="row">
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label for="price_adjustment" class="form-label">Điều chỉnh giá</label>
                                <input type="number" class="form-control" id="price_adjustment" name="price_adjustment" value="0" step="0.01">
                                <small class="text-muted">Số tiền cộng thêm vào giá gốc</small>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label for="stock_quantity" class="form-label">Số lượng tồn kho *</label>
                                <input type="number" class="form-control" id="stock_quantity" name="stock_quantity" required min="0">
                            </div>
                        </div>
                    </div>
                    
                    <div class="mb-3">
                        <label for="variant_image" class="form-label">Ảnh biến thể</label>
                        <input type="file" class="form-control" id="variant_image" name="variant_image" accept="image/*">
                        <small class="text-muted">Ảnh riêng cho biến thể này (tùy chọn)</small>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Hủy</button>
                    <button type="submit" class="btn btn-primary">
                        <i class="fas fa-save"></i> Thêm biến thể
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<style>
.color-preview {
    width: 20px;
    height: 20px;
    border-radius: 50%;
    display: inline-block;
    border: 1px solid #ddd;
}

.modal {
    z-index: 1050;
}

.modal-backdrop {
    z-index: 1040;
}

.btn-group {
    display: flex;
    gap: 5px;
}

.admin-card {
    background: white;
    border-radius: 8px;
    box-shadow: 0 2px 10px rgba(0,0,0,0.1);
    margin-bottom: 20px;
}

.admin-card-header {
    padding: 20px;
    border-bottom: 1px solid #eee;
}

.admin-card-title {
    margin: 0;
    font-size: 1.2rem;
    font-weight: 600;
}

.admin-card-body {
    padding: 20px;
}

.alert {
    padding: 15px;
    border-radius: 6px;
    margin-bottom: 20px;
}

.alert-danger {
    background: #f8d7da;
    color: #721c24;
    border: 1px solid #f5c6cb;
}

.alert-success {
    background: #d4edda;
    color: #155724;
    border: 1px solid #c3e6cb;
}

.badge {
    padding: 4px 8px;
    border-radius: 4px;
    font-size: 0.75rem;
}

.bg-success { background: #28a745 !important; color: white; }
.bg-danger { background: #dc3545 !important; color: white; }
.bg-secondary { background: #6c757d !important; color: white; }

.btn {
    padding: 8px 16px;
    border: none;
    border-radius: 4px;
    cursor: pointer;
    text-decoration: none;
    display: inline-block;
}

.btn-primary { background: #007bff; color: white; }
.btn-secondary { background: #6c757d; color: white; }
.btn-light { background: #f8f9fa; color: #333; border: 1px solid #ddd; }
.btn-outline-primary { background: white; color: #007bff; border: 1px solid #007bff; }
.btn-outline-danger { background: white; color: #dc3545; border: 1px solid #dc3545; }

.table {
    width: 100%;
    border-collapse: collapse;
}

.table th,
.table td {
    padding: 12px;
    text-align: left;
    border-bottom: 1px solid #ddd;
}

.table th {
    background: #f8f9fa;
    font-weight: 600;
}
</style>

<script>
function deleteVariant(variantId) {
    if (confirm('Bạn có chắc chắn muốn xóa biến thể này?')) {
        window.location.href = 'variants.php?product_id=<?php echo $product_id; ?>&delete_variant=' + variantId;
    }
}

function editVariant(variantId) {
    alert('Chức năng sửa biến thể sẽ được cập nhật trong phiên bản tiếp theo');
}

// Bootstrap modal functionality (basic implementation)
document.addEventListener('DOMContentLoaded', function() {
    // Modal functionality
    const modalTriggers = document.querySelectorAll('[data-bs-toggle="modal"]');
    const modals = document.querySelectorAll('.modal');
    const modalCloses = document.querySelectorAll('[data-bs-dismiss="modal"]');
    
    modalTriggers.forEach(trigger => {
        trigger.addEventListener('click', function() {
            const targetId = this.getAttribute('data-bs-target');
            const modal = document.querySelector(targetId);
            if (modal) {
                modal.style.display = 'block';
                modal.classList.add('show');
                document.body.style.overflow = 'hidden';
            }
        });
    });
    
    modalCloses.forEach(close => {
        close.addEventListener('click', function() {
            const modal = this.closest('.modal');
            if (modal) {
                modal.style.display = 'none';
                modal.classList.remove('show');
                document.body.style.overflow = 'auto';
            }
        });
    });
    
    // Close modal when clicking outside
    modals.forEach(modal => {
        modal.addEventListener('click', function(e) {
            if (e.target === this) {
                this.style.display = 'none';
                this.classList.remove('show');
                document.body.style.overflow = 'auto';
            }
        });
    });
    
    // Color preview in select
    const colorSelect = document.getElementById('color_id');
    if (colorSelect) {
        colorSelect.addEventListener('change', function() {
            const selectedOption = this.options[this.selectedIndex];
            const color = selectedOption.getAttribute('data-color');
            if (color && color !== '#000000') {
                this.style.borderLeft = '5px solid ' + color;
            } else {
                this.style.borderLeft = '';
            }
        });
    }
});
</script>

<?php include '../layouts/footer.php'; ?>