<?php
/**
 * TKT Shop - Product Variants Management (Fixed)
 * Quản lý biến thể sản phẩm - Đã sửa lỗi điều hướng
 */

// Start session first
session_start();

require_once '../../config/database.php';
require_once '../../config/config.php';

// Auto-create product_variants table if not exists
try {
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS product_variants (
            id INT AUTO_INCREMENT PRIMARY KEY,
            product_id INT NOT NULL,
            size_id INT DEFAULT NULL,
            color_id INT DEFAULT NULL,
            sku VARCHAR(100),
            price_adjustment DECIMAL(10,2) DEFAULT 0,
            stock_quantity INT DEFAULT 0,
            sold_quantity INT DEFAULT 0,
            variant_image VARCHAR(255),
            status VARCHAR(20) DEFAULT 'active',
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            INDEX idx_product_id (product_id),
            INDEX idx_size_id (size_id),
            INDEX idx_color_id (color_id),
            INDEX idx_status (status)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
    ");
} catch (Exception $e) {
    error_log("Auto-create product_variants table error: " . $e->getMessage());
}

// Kiểm tra quyền admin
if (!isset($_SESSION['admin_id'])) {
    header('Location: ../login.php');
    exit();
}

// SỬA LỖI: Lấy product_id từ URL parameter đúng tên
$product_id = isset($_GET['product_id']) ? (int)$_GET['product_id'] : (isset($_GET['id']) ? (int)$_GET['id'] : 0);

if (!$product_id) {
    $_SESSION['error_message'] = 'Không tìm thấy sản phẩm!';
    header('Location: index.php');
    exit();
}

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
    
    if (!file_exists($upload_dir)) {
        mkdir($upload_dir, 0755, true);
    }
    
    $allowed_types = ['image/jpeg', 'image/png', 'image/gif', 'image/webp'];
    $max_size = 5 * 1024 * 1024; // 5MB
    
    if (!in_array($file['type'], $allowed_types)) {
        throw new Exception('Định dạng ảnh không hợp lệ. Vui lòng sử dụng JPG, PNG, GIF hoặc WEBP.');
    }
    
    if ($file['size'] > $max_size) {
        throw new Exception('Kích thước ảnh quá lớn. Tối đa 5MB.');
    }
    
    $extension = pathinfo($file['name'], PATHINFO_EXTENSION);
    $filename = 'variant_' . uniqid() . '_' . time() . '.' . $extension;
    $filepath = $upload_dir . $filename;
    
    if (move_uploaded_file($file['tmp_name'], $filepath)) {
        return $filename;
    } else {
        throw new Exception('Không thể upload ảnh.');
    }
}

// SỬA LỖI: Lấy thông tin sản phẩm từ bảng products (tên chuẩn)
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

// Tạo bảng sizes nếu chưa có
try {
    $pdo->exec("CREATE TABLE IF NOT EXISTS sizes (
        id INT AUTO_INCREMENT PRIMARY KEY,
        name VARCHAR(50) NOT NULL,
        sort_order INT DEFAULT 0,
        status VARCHAR(20) DEFAULT 'active',
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    )");
    
    // Kiểm tra và thêm dữ liệu mẫu
    $count = $pdo->query("SELECT COUNT(*) FROM sizes")->fetchColumn();
    if ($count == 0) {
        $pdo->exec("INSERT INTO sizes (name, sort_order) VALUES 
            ('XS', 1), ('S', 2), ('M', 3), ('L', 4), ('XL', 5), ('XXL', 6),
            ('36', 7), ('37', 8), ('38', 9), ('39', 10), ('40', 11), ('41', 12), ('42', 13), ('43', 14)");
    }
} catch (PDOException $e) {
    // Bỏ qua lỗi nếu không thể tạo bảng
}

// Tạo bảng colors nếu chưa có
try {
    $pdo->exec("CREATE TABLE IF NOT EXISTS colors (
        id INT AUTO_INCREMENT PRIMARY KEY,
        name VARCHAR(50) NOT NULL,
        color_code VARCHAR(7) DEFAULT '#000000',
        status VARCHAR(20) DEFAULT 'active',
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    )");
    
    // Kiểm tra và thêm dữ liệu mẫu
    $count = $pdo->query("SELECT COUNT(*) FROM colors")->fetchColumn();
    if ($count == 0) {
        $pdo->exec("INSERT INTO colors (name, color_code) VALUES 
            ('Đỏ', '#FF0000'), ('Xanh dương', '#0000FF'), ('Xanh lá', '#00FF00'),
            ('Vàng', '#FFFF00'), ('Đen', '#000000'), ('Trắng', '#FFFFFF'),
            ('Xám', '#808080'), ('Nâu', '#8B4513'), ('Hồng', '#FFC0CB'),
            ('Tím', '#800080'), ('Cam', '#FFA500'), ('Xanh navy', '#000080')");
    }
} catch (PDOException $e) {
    // Bỏ qua lỗi nếu không thể tạo bảng
}

// Tạo bảng product_variants nếu chưa có
try {
    $pdo->exec("CREATE TABLE IF NOT EXISTS product_variants (
        id INT AUTO_INCREMENT PRIMARY KEY,
        product_id INT NOT NULL,
        size_id INT DEFAULT NULL,
        color_id INT DEFAULT NULL,
        sku VARCHAR(100),
        price_adjustment DECIMAL(10,2) DEFAULT 0,
        stock_quantity INT DEFAULT 0,
        sold_quantity INT DEFAULT 0,
        variant_image VARCHAR(255),
        status VARCHAR(20) DEFAULT 'active',
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        INDEX idx_product_id (product_id),
        INDEX idx_size_id (size_id),
        INDEX idx_color_id (color_id)
    )");
} catch (PDOException $e) {
    // Bỏ qua lỗi nếu không thể tạo bảng
}

// Lấy danh sách sizes và colors
$sizes = [];
$colors = [];
$variants = [];

try {
    $sizes = $pdo->query("SELECT * FROM sizes WHERE status = 'active' ORDER BY sort_order ASC")->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    $sizes = [];
}

try {
    $colors = $pdo->query("SELECT * FROM colors WHERE status = 'active' ORDER BY name ASC")->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    $colors = [];
}

// Lấy danh sách biến thể hiện có
try {
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
    $variants = [];
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
        
        // Kiểm tra ít nhất phải có size hoặc color
        if ($size_id == 0 && $color_id == 0) {
            throw new Exception("Vui lòng chọn ít nhất một thuộc tính (size hoặc màu)!");
        }
        
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
        
        $sku_parts = [$product['sku']];
        if ($size_name) $sku_parts[] = strtoupper($size_name);
        if ($color_name) $sku_parts[] = strtoupper(str_replace(' ', '', $color_name));
        $sku = implode('-', $sku_parts);
        
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
        header("Location: variants.php?product_id=" . $product_id . "&success=1");
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

// Hiển thị thông báo thành công từ URL
if (isset($_GET['success'])) {
    $success = "Thao tác thành công!";
}
?>

<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Quản lý biến thể - <?php echo htmlspecialchars($product['name']); ?> - TKT Admin</title>
    
    <!-- Bootstrap 5 -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <!-- Font Awesome -->
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
    
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

        body {
            font-family: 'Inter', -apple-system, BlinkMacSystemFont, sans-serif;
            background-color: var(--light-bg);
            color: #334155;
            line-height: 1.6;
        }

        /* Sidebar */
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

        .nav-link:hover, .nav-link.active {
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
            font-size: 1.75rem;
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
            margin-bottom: 1.5rem;
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

        .btn-outline-secondary {
            background: transparent;
            color: var(--secondary-color);
            border: 2px solid var(--border-color);
        }

        .btn-outline-primary {
            background: transparent;
            color: var(--primary-color);
            border: 2px solid var(--primary-color);
        }

        .btn-outline-danger {
            background: transparent;
            color: var(--danger-color);
            border: 2px solid var(--danger-color);
        }

        .btn-sm {
            padding: 0.5rem 1rem;
            font-size: 0.8rem;
        }

        /* Tables */
        .table {
            margin-bottom: 0;
        }

        .table th {
            background: #f8fafc;
            border-top: none;
            font-weight: 600;
            color: var(--dark-color);
            padding: 1rem;
        }

        .table td {
            padding: 1rem;
            vertical-align: middle;
        }

        /* Form */
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
        }

        .form-control:focus {
            border-color: var(--primary-color);
            box-shadow: 0 0 0 3px rgba(59, 130, 246, 0.1);
            outline: none;
        }

        /* Badges */
        .badge {
            padding: 0.375rem 0.75rem;
            border-radius: 6px;
            font-size: 0.75rem;
            font-weight: 600;
        }

        .bg-success { background: var(--success-color) !important; color: white; }
        .bg-danger { background: var(--danger-color) !important; color: white; }
        .bg-secondary { background: var(--secondary-color) !important; color: white; }

        /* Color preview */
        .color-preview {
            width: 24px;
            height: 24px;
            border-radius: 50%;
            display: inline-block;
            border: 2px solid #dee2e6;
            margin-right: 0.5rem;
        }

        /* Modal */
        .modal-content {
            border: none;
            border-radius: 12px;
            box-shadow: 0 10px 40px rgba(0, 0, 0, 0.15);
        }

        .modal-header {
            background: #f8fafc;
            border-bottom: 1px solid var(--border-color);
            border-radius: 12px 12px 0 0;
        }

        .modal-title {
            font-weight: 600;
            color: var(--dark-color);
        }

        /* Product info */
        .product-image {
            width: 80px;
            height: 80px;
            object-fit: cover;
            border-radius: 8px;
        }

        /* Empty state */
        .empty-state {
            text-align: center;
            padding: 3rem 1rem;
            color: var(--secondary-color);
        }

        .empty-state i {
            font-size: 4rem;
            margin-bottom: 1rem;
            opacity: 0.5;
        }

        /* Responsive */
        @media (max-width: 1024px) {
            .admin-sidebar {
                transform: translateX(-100%);
                transition: transform 0.3s ease;
            }

            .main-content {
                margin-left: 0;
            }
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
                    <h1 class="page-title">Quản lý biến thể sản phẩm</h1>
                    <nav aria-label="breadcrumb">
                        <ol class="breadcrumb">
                            <li class="breadcrumb-item"><a href="../dashboard.php">Dashboard</a></li>
                            <li class="breadcrumb-item"><a href="index.php">Sản phẩm</a></li>
                            <li class="breadcrumb-item active">Biến thể</li>
                        </ol>
                    </nav>
                </div>
                <div>
                    <a href="index.php" class="btn btn-outline-secondary me-2">
                        <i class="fas fa-arrow-left"></i>
                        Quay lại
                    </a>
                    <button type="button" class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#addVariantModal">
                        <i class="fas fa-plus"></i>
                        Thêm biến thể
                    </button>
                </div>
            </div>
        </div>

        <!-- Content Body -->
        <div class="content-body">
            <!-- Flash Messages -->
            <?php if ($error): ?>
                <div class="alert alert-danger">
                    <i class="fas fa-exclamation-triangle"></i>
                    <div><?php echo htmlspecialchars($error); ?></div>
                </div>
            <?php endif; ?>

            <?php if ($success): ?>
                <div class="alert alert-success">
                    <i class="fas fa-check-circle"></i>
                    <div><?php echo htmlspecialchars($success); ?></div>
                </div>
            <?php endif; ?>

            <?php
            $flash = getFlashMessage();
            if ($flash):
            ?>
                <div class="alert alert-<?php echo $flash['type'] === 'error' ? 'danger' : $flash['type']; ?>">
                    <i class="fas fa-<?php echo $flash['type'] === 'error' ? 'exclamation-triangle' : 'check-circle'; ?>"></i> 
                    <div><?php echo htmlspecialchars($flash['message']); ?></div>
                </div>
            <?php endif; ?>

            <!-- Thông tin sản phẩm -->
            <div class="card">
                <div class="card-header">
                    <h3 class="card-title">
                        <i class="fas fa-info-circle me-2"></i>
                        Thông tin sản phẩm
                    </h3>
                </div>
                <div class="card-body">
                    <div class="row align-items-center">
                        <div class="col-md-2">
                            <?php if ($product['main_image']): ?>
                                <img src="../../uploads/products/<?php echo $product['main_image']; ?>" 
                                     alt="<?php echo htmlspecialchars($product['name']); ?>" 
                                     class="product-image">
                            <?php else: ?>
                                <div class="product-image bg-light d-flex align-items-center justify-content-center">
                                    <i class="fas fa-image text-muted"></i>
                                </div>
                            <?php endif; ?>
                        </div>
                        <div class="col-md-10">
                            <h4 class="mb-2"><?php echo htmlspecialchars($product['name']); ?></h4>
                            <div class="text-muted mb-2">
                                <strong>SKU:</strong> <?php echo htmlspecialchars($product['sku']); ?> |
                                <strong>Thương hiệu:</strong> <?php echo htmlspecialchars($product['brand'] ?? 'N/A'); ?> |
                                <strong>Giá:</strong> <?php echo formatPrice($product['price']); ?>
                                <?php if ($product['sale_price']): ?>
                                    | <strong>Giá KM:</strong> <?php echo formatPrice($product['sale_price']); ?>
                                <?php endif; ?>
                            </div>
                            <?php if ($product['short_description']): ?>
                                <p class="mb-0"><?php echo htmlspecialchars($product['short_description']); ?></p>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Danh sách biến thể -->
            <div class="card">
                <div class="card-header">
                    <div class="d-flex justify-content-between align-items-center">
                        <h3 class="card-title">
                            <i class="fas fa-list me-2"></i>
                            Danh sách biến thể (<?php echo count($variants); ?>)
                        </h3>
                        <button type="button" class="btn btn-primary btn-sm" data-bs-toggle="modal" data-bs-target="#addVariantModal">
                            <i class="fas fa-plus"></i>
                            Thêm biến thể
                        </button>
                    </div>
                </div>
                <div class="card-body p-0">
                    <?php if (empty($variants)): ?>
                        <div class="empty-state">
                            <i class="fas fa-box-open"></i>
                            <h5>Chưa có biến thể nào</h5>
                            <p class="text-muted">Hãy thêm biến thể đầu tiên cho sản phẩm này</p>
                            <button type="button" class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#addVariantModal">
                                <i class="fas fa-plus"></i>
                                Thêm biến thể
                            </button>
                        </div>
                    <?php else: ?>
                        <div class="table-responsive">
                            <table class="table">
                                <thead>
                                    <tr>
                                        <th>SKU</th>
                                        <th>Size</th>
                                        <th>Màu sắc</th>
                                        <th>Điều chỉnh giá</th>
                                        <th>Tồn kho</th>
                                        <th>Đã bán</th>
                                        <th>Trạng thái</th>
                                        <th width="120">Thao tác</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($variants as $variant): ?>
                                        <tr>
                                            <td>
                                                <code class="bg-light px-2 py-1 rounded">
                                                    <?php echo htmlspecialchars($variant['sku']); ?>
                                                </code>
                                            </td>
                                            <td>
                                                <?php if ($variant['size_name']): ?>
                                                    <span class="badge bg-secondary">
                                                        <?php echo htmlspecialchars($variant['size_name']); ?>
                                                    </span>
                                                <?php else: ?>
                                                    <span class="text-muted">N/A</span>
                                                <?php endif; ?>
                                            </td>
                                            <td>
                                                <?php if ($variant['color_name']): ?>
                                                    <div class="d-flex align-items-center">
                                                        <?php if ($variant['color_code']): ?>
                                                            <span class="color-preview" style="background-color: <?php echo $variant['color_code']; ?>"></span>
                                                        <?php endif; ?>
                                                        <?php echo htmlspecialchars($variant['color_name']); ?>
                                                    </div>
                                                <?php else: ?>
                                                    <span class="text-muted">N/A</span>
                                                <?php endif; ?>
                                            </td>
                                            <td>
                                                <?php if ($variant['price_adjustment'] != 0): ?>
                                                    <span class="<?php echo $variant['price_adjustment'] > 0 ? 'text-success' : 'text-danger'; ?>">
                                                        <?php echo $variant['price_adjustment'] > 0 ? '+' : ''; ?>
                                                        <?php echo formatPrice($variant['price_adjustment']); ?>
                                                    </span>
                                                <?php else: ?>
                                                    <span class="text-muted">0₫</span>
                                                <?php endif; ?>
                                            </td>
                                            <td>
                                                <span class="badge bg-<?php echo $variant['stock_quantity'] > 0 ? 'success' : 'danger'; ?>">
                                                    <?php echo $variant['stock_quantity']; ?>
                                                </span>
                                            </td>
                                            <td>
                                                <span class="text-muted">
                                                    <?php echo $variant['sold_quantity']; ?>
                                                </span>
                                            </td>
                                            <td>
                                                <span class="badge bg-<?php echo $variant['status'] === 'active' ? 'success' : 'secondary'; ?>">
                                                    <?php echo $variant['status'] === 'active' ? 'Hoạt động' : 'Tạm ngưng'; ?>
                                                </span>
                                            </td>
                                            <td>
                                                <div class="d-flex gap-1">
                                                    <button class="btn btn-outline-primary btn-sm" 
                                                            onclick="editVariant(<?php echo $variant['id']; ?>)"
                                                            title="Chỉnh sửa">
                                                        <i class="fas fa-edit"></i>
                                                    </button>
                                                    <button class="btn btn-outline-danger btn-sm" 
                                                            onclick="deleteVariant(<?php echo $variant['id']; ?>)"
                                                            title="Xóa">
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
    </div>

    <!-- Modal thêm biến thể -->
    <div class="modal fade" id="addVariantModal" tabindex="-1">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <form method="POST" enctype="multipart/form-data">
                    <input type="hidden" name="action" value="add_variant">
                    <div class="modal-header">
                        <h5 class="modal-title">
                            <i class="fas fa-plus me-2"></i>
                            Thêm biến thể mới
                        </h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                    </div>
                    <div class="modal-body">
                        <div class="row">
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label for="size_id" class="form-label">Kích cỡ</label>
                                    <select class="form-control" id="size_id" name="size_id">
                                        <option value="0">Không chọn size</option>
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
                                        <option value="0">Không chọn màu</option>
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
                                    <div class="input-group">
                                        <input type="number" class="form-control" id="price_adjustment" 
                                               name="price_adjustment" value="0" step="1000">
                                        <span class="input-group-text">₫</span>
                                    </div>
                                    <small class="text-muted">Số tiền cộng/trừ vào giá gốc (có thể âm)</small>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label for="stock_quantity" class="form-label">Số lượng tồn kho *</label>
                                    <input type="number" class="form-control" id="stock_quantity" 
                                           name="stock_quantity" required min="0" value="0">
                                </div>
                            </div>
                        </div>
                        
                        <div class="mb-3">
                            <label for="variant_image" class="form-label">Ảnh biến thể</label>
                            <input type="file" class="form-control" id="variant_image" 
                                   name="variant_image" accept="image/*">
                            <small class="text-muted">Ảnh riêng cho biến thể này (tùy chọn)</small>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">
                            <i class="fas fa-times"></i>
                            Hủy
                        </button>
                        <button type="submit" class="btn btn-primary">
                            <i class="fas fa-save"></i>
                            Thêm biến thể
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Bootstrap JS -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    
    <script>
        function deleteVariant(variantId) {
            if (confirm('Bạn có chắc chắn muốn xóa biến thể này?\n\nThao tác này không thể hoàn tác!')) {
                window.location.href = 'variants.php?product_id=<?php echo $product_id; ?>&delete_variant=' + variantId;
            }
        }

        function editVariant(variantId) {
            // Chức năng sửa biến thể sẽ được phát triển sau
            alert('Chức năng sửa biến thể đang được phát triển.\n\nHiện tại bạn có thể xóa và tạo lại biến thể mới.');
        }

        // Color preview in select
        document.addEventListener('DOMContentLoaded', function() {
            const colorSelect = document.getElementById('color_id');
            if (colorSelect) {
                colorSelect.addEventListener('change', function() {
                    const selectedOption = this.options[this.selectedIndex];
                    const color = selectedOption.getAttribute('data-color');
                    if (color && color !== '#000000') {
                        this.style.borderLeft = '4px solid ' + color;
                    } else {
                        this.style.borderLeft = '';
                    }
                });
            }

            // Form validation
            const form = document.querySelector('#addVariantModal form');
            if (form) {
                form.addEventListener('submit', function(e) {
                    const sizeId = document.getElementById('size_id').value;
                    const colorId = document.getElementById('color_id').value;
                    
                    if (sizeId == 0 && colorId == 0) {
                        e.preventDefault();
                        alert('Vui lòng chọn ít nhất một thuộc tính (size hoặc màu)!');
                        return false;
                    }
                });
            }

            // Auto-hide alerts after 5 seconds
            const alerts = document.querySelectorAll('.alert');
            alerts.forEach(function(alert) {
                setTimeout(() => {
                    if (alert && alert.parentNode) {
                        alert.style.transition = 'opacity 0.5s ease';
                        alert.style.opacity = '0';
                        setTimeout(() => {
                            alert.remove();
                        }, 500);
                    }
                }, 5000);
            });
        });
    </script>
</body>
</html>