<?php
/**
 * Trang danh sách sản phẩm với đầy đủ chức năng CRUD - ĐÃ SỬA LINK BIẾN THỂ
 */

session_start();
require_once '../../config/database.php';
require_once '../../config/config.php';

// Kiểm tra đăng nhập admin
if (!isset($_SESSION['admin_id'])) {
    header('Location: ../login.php');
    exit;
}

// Helper function
function formatPrice($price) {
    return number_format($price, 0, ',', '.') . ' ₫';
}

function formatDate($date) {
    return date('d/m/Y H:i', strtotime($date));
}

// Tạo bảng products nếu chưa có
try {
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS products (
            id INT AUTO_INCREMENT PRIMARY KEY,
            name VARCHAR(255) NOT NULL,
            slug VARCHAR(255),
            description TEXT,
            short_description TEXT,
            price DECIMAL(10,2) NOT NULL,
            sale_price DECIMAL(10,2) DEFAULT NULL,
            sku VARCHAR(100) UNIQUE,
            category_id INT,
            brand VARCHAR(100),
            weight DECIMAL(8,2) DEFAULT 0,
            dimensions VARCHAR(100),
            stock_quantity INT DEFAULT 0,
            min_quantity INT DEFAULT 1,
            status VARCHAR(20) DEFAULT 'active',
            is_featured BOOLEAN DEFAULT FALSE,
            main_image VARCHAR(255),
            gallery_images TEXT,
            meta_title VARCHAR(255),
            meta_description TEXT,
            tags TEXT,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            INDEX idx_status (status),
            INDEX idx_category (category_id),
            INDEX idx_sku (sku)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
    ");
} catch (Exception $e) {
    // Bỏ qua lỗi nếu bảng đã tồn tại
}

// Xử lý xóa sản phẩm
if (isset($_GET['action']) && $_GET['action'] == 'delete' && isset($_GET['id'])) {
    $product_id = (int)$_GET['id'];
    
    try {
        $pdo->beginTransaction();
        
        // Kiểm tra xem sản phẩm có đơn hàng không (sử dụng tên bảng tiếng Anh)
        try {
            $stmt = $pdo->prepare("SELECT COUNT(*) FROM order_items WHERE product_id = ?");
            $stmt->execute([$product_id]);
            $order_count = $stmt->fetchColumn();
        } catch (PDOException $e) {
            // Nếu bảng order_items không tồn tại, giả sử là 0
            $order_count = 0;
        }
        
        if ($order_count > 0) {
            $_SESSION['error_message'] = 'Không thể xóa sản phẩm này vì đã có đơn hàng!';
        } else {
            // Lấy thông tin ảnh để xóa file
            $stmt = $pdo->prepare("SELECT main_image, gallery_images FROM products WHERE id = ?");
            $stmt->execute([$product_id]);
            $product = $stmt->fetch();
            
            // Xóa biến thể trước
            try {
                $stmt = $pdo->prepare("DELETE FROM product_variants WHERE product_id = ?");
                $stmt->execute([$product_id]);
            } catch (PDOException $e) {
                // Bỏ qua nếu bảng không tồn tại
            }
            
            // Xóa sản phẩm
            $stmt = $pdo->prepare("DELETE FROM products WHERE id = ?");
            $stmt->execute([$product_id]);
            
            // Xóa ảnh nếu có
            if ($product && $product['main_image']) {
                $image_path = "../../uploads/products/" . $product['main_image'];
                if (file_exists($image_path)) {
                    unlink($image_path);
                }
            }
            
            if ($product && $product['gallery_images']) {
                $gallery = json_decode($product['gallery_images'], true);
                if (is_array($gallery)) {
                    foreach ($gallery as $image) {
                        $image_path = "../../uploads/products/" . $image;
                        if (file_exists($image_path)) {
                            unlink($image_path);
                        }
                    }
                }
            }
            
            $_SESSION['success_message'] = 'Xóa sản phẩm thành công!';
        }
        
        $pdo->commit();
    } catch (Exception $e) {
        $pdo->rollBack();
        $_SESSION['error_message'] = 'Lỗi: ' . $e->getMessage();
    }
    
    header('Location: index.php');
    exit;
}

// Xử lý thay đổi trạng thái
if (isset($_GET['action']) && $_GET['action'] == 'toggle_status' && isset($_GET['id'])) {
    $product_id = (int)$_GET['id'];
    
    try {
        $stmt = $pdo->prepare("SELECT status FROM products WHERE id = ?");
        $stmt->execute([$product_id]);
        $current_status = $stmt->fetchColumn();
        
        $new_status = ($current_status == 'active') ? 'inactive' : 'active';
        
        $stmt = $pdo->prepare("UPDATE products SET status = ? WHERE id = ?");
        $stmt->execute([$new_status, $product_id]);
        
        $_SESSION['success_message'] = 'Cập nhật trạng thái thành công!';
    } catch (Exception $e) {
        $_SESSION['error_message'] = 'Lỗi: ' . $e->getMessage();
    }
    
    header('Location: index.php');
    exit;
}

// Xử lý tìm kiếm và lọc
$search = $_GET['search'] ?? '';
$status_filter = $_GET['status'] ?? '';
$category_filter = $_GET['category'] ?? '';
$brand_filter = $_GET['brand'] ?? '';

// Pagination
$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$per_page = 20; // Số sản phẩm mỗi trang
$offset = ($page - 1) * $per_page;

// Build WHERE clause
$where_conditions = [];
$params = [];

if (!empty($search)) {
    $where_conditions[] = "(p.name LIKE ? OR p.sku LIKE ? OR p.brand LIKE ?)";
    $search_term = "%{$search}%";
    $params[] = $search_term;
    $params[] = $search_term;
    $params[] = $search_term;
}

if (!empty($status_filter)) {
    $where_conditions[] = "p.status = ?";
    $params[] = $status_filter;
}

if (!empty($category_filter)) {
    $where_conditions[] = "p.category_id = ?";
    $params[] = $category_filter;
}

if (!empty($brand_filter)) {
    $where_conditions[] = "p.brand = ?";
    $params[] = $brand_filter;
}

$where_sql = !empty($where_conditions) ? 'WHERE ' . implode(' AND ', $where_conditions) : '';

// Đếm tổng số sản phẩm
$count_sql = "
    SELECT COUNT(*) 
    FROM products p 
    LEFT JOIN categories c ON p.category_id = c.id 
    {$where_sql}
";

try {
    $stmt = $pdo->prepare($count_sql);
    $stmt->execute($params);
    $total_products = $stmt->fetchColumn();
} catch (PDOException $e) {
    $total_products = 0;
}

$total_pages = ceil($total_products / $per_page);

// Lấy danh sách sản phẩm
$sql = "
    SELECT 
        p.*,
        c.name as category_name,
        COUNT(DISTINCT pv.id) as variant_count,
        SUM(pv.stock_quantity) as total_stock,
        MIN(CASE WHEN pv.price_adjustment IS NOT NULL THEN p.price + pv.price_adjustment ELSE p.price END) as min_price,
        MAX(CASE WHEN pv.price_adjustment IS NOT NULL THEN p.price + pv.price_adjustment ELSE p.price END) as max_price
    FROM products p
    LEFT JOIN categories c ON p.category_id = c.id
    LEFT JOIN product_variants pv ON p.id = pv.product_id AND pv.status = 'active'
    {$where_sql}
    GROUP BY p.id
    ORDER BY p.created_at DESC
    LIMIT {$per_page} OFFSET {$offset}
";

try {
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $products = $stmt->fetchAll();
} catch (PDOException $e) {
    $products = [];
    $error_message = "Lỗi truy vấn: " . $e->getMessage();
}

// Lấy danh sách danh mục cho filter
try {
    $categories = $pdo->query("SELECT * FROM categories WHERE status = 'active' ORDER BY name")->fetchAll();
} catch (PDOException $e) {
    $categories = [];
}

// Lấy danh sách thương hiệu
try {
    $brands = $pdo->query("SELECT DISTINCT brand FROM products WHERE brand IS NOT NULL AND brand != '' ORDER BY brand")->fetchAll();
} catch (PDOException $e) {
    $brands = [];
}

// Thống kê tổng quan
$stats = [
    'total_products' => 0,
    'active_products' => 0,
    'low_stock' => 0,
    'today_added' => 0
];

try {
    $stats['total_products'] = $pdo->query("SELECT COUNT(*) FROM products")->fetchColumn();
    $stats['active_products'] = $pdo->query("SELECT COUNT(*) FROM products WHERE status = 'active'")->fetchColumn();
    $stats['low_stock'] = $pdo->query("SELECT COUNT(*) FROM products WHERE stock_quantity <= min_quantity")->fetchColumn();
    $stats['today_added'] = $pdo->query("SELECT COUNT(*) FROM products WHERE DATE(created_at) = CURDATE()")->fetchColumn();
} catch (PDOException $e) {
    // Giữ nguyên giá trị mặc định
}
?>

<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Quản lý sản phẩm - TKT Shop Admin</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
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

        /* Stats Cards */
        .stats-card {
            transition: transform 0.2s;
            cursor: pointer;
        }

        .stats-card:hover {
            transform: translateY(-2px);
        }

        .stats-card .card-body {
            text-align: center;
            padding: 1.5rem;
        }

        .stats-icon {
            font-size: 2rem;
            margin-bottom: 0.75rem;
        }

        .stats-number {
            font-size: 1.875rem;
            font-weight: 700;
            margin-bottom: 0.25rem;
        }

        .stats-label {
            font-size: 0.875rem;
            color: var(--secondary-color);
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
            vertical-align: middle;
        }

        .table td {
            padding: 1rem;
            vertical-align: middle;
        }

        /* Product Image */
        .product-image {
            width: 60px;
            height: 60px;
            object-fit: cover;
            border-radius: 8px;
            border: 2px solid var(--border-color);
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
        .bg-warning { background: var(--warning-color) !important; color: white; }
        .bg-secondary { background: var(--secondary-color) !important; color: white; }
        .bg-primary { background: var(--primary-color) !important; color: white; }

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

        .btn-success {
            background: var(--success-color);
            color: white;
        }

        .btn-outline-primary {
            background: transparent;
            color: var(--primary-color);
            border: 2px solid var(--primary-color);
        }

        .btn-outline-warning {
            background: transparent;
            color: var(--warning-color);
            border: 2px solid var(--warning-color);
        }

        .btn-outline-danger {
            background: transparent;
            color: var(--danger-color);
            border: 2px solid var(--danger-color);
        }

        .btn-outline-secondary {
            background: transparent;
            color: var(--secondary-color);
            border: 2px solid var(--border-color);
        }

        .btn-outline-info {
            background: transparent;
            color: #0ea5e9;
            border: 2px solid #0ea5e9;
        }

        .btn-sm {
            padding: 0.5rem 1rem;
            font-size: 0.8rem;
        }

        /* Action Buttons */
        .action-buttons {
            display: flex;
            gap: 0.5rem;
            flex-wrap: wrap;
        }

        .action-buttons .btn {
            padding: 0.5rem;
            border-radius: 6px;
            min-width: 36px;
            height: 36px;
            display: flex;
            align-items: center;
            justify-content: center;
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

        .alert-info {
            background: #eff6ff;
            color: #1d4ed8;
            border-color: #bfdbfe;
        }

        /* Form */
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

        /* Empty State */
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

        /* Pagination */
        .pagination {
            justify-content: center;
            margin-top: 2rem;
        }

        .page-link {
            color: var(--primary-color);
            border: 1px solid var(--border-color);
            padding: 0.75rem 1rem;
            margin: 0 2px;
            border-radius: 6px;
        }

        .page-link:hover {
            background: var(--primary-color);
            color: white;
            border-color: var(--primary-color);
        }

        .page-item.active .page-link {
            background: var(--primary-color);
            border-color: var(--primary-color);
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

            .action-buttons {
                flex-direction: column;
                gap: 0.25rem;
            }
        }

        @media (max-width: 768px) {
            .content-body {
                padding: 1rem;
            }

            .card-body {
                padding: 1rem;
            }

            .table-responsive {
                font-size: 0.875rem;
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
                    <h1 class="page-title">Quản lý sản phẩm</h1>
                    <nav aria-label="breadcrumb">
                        <ol class="breadcrumb">
                            <li class="breadcrumb-item"><a href="../dashboard.php">Dashboard</a></li>
                            <li class="breadcrumb-item active">Sản phẩm</li>
                        </ol>
                    </nav>
                </div>
                <a href="add.php" class="btn btn-primary">
                    <i class="fas fa-plus"></i>
                    Thêm sản phẩm
                </a>
            </div>
        </div>

        <!-- Content Body -->
        <div class="content-body">
            <!-- Flash Messages -->
            <?php if (isset($_SESSION['success_message'])): ?>
                <div class="alert alert-success">
                    <i class="fas fa-check-circle"></i>
                    <div><?php echo htmlspecialchars($_SESSION['success_message']); unset($_SESSION['success_message']); ?></div>
                </div>
            <?php endif; ?>

            <?php if (isset($_SESSION['error_message'])): ?>
                <div class="alert alert-danger">
                    <i class="fas fa-exclamation-triangle"></i>
                    <div><?php echo htmlspecialchars($_SESSION['error_message']); unset($_SESSION['error_message']); ?></div>
                </div>
            <?php endif; ?>

            <?php if (isset($error_message)): ?>
                <div class="alert alert-danger">
                    <i class="fas fa-exclamation-triangle"></i>
                    <div><?php echo htmlspecialchars($error_message); ?></div>
                </div>
            <?php endif; ?>

            <!-- Stats Cards -->
            <div class="row mb-4">
                <div class="col-md-3">
                    <div class="card stats-card border-primary">
                        <div class="card-body">
                            <div class="stats-icon text-primary">
                                <i class="fas fa-box"></i>
                            </div>
                            <div class="stats-number text-primary"><?php echo number_format($stats['total_products']); ?></div>
                            <div class="stats-label">Tổng sản phẩm</div>
                        </div>
                    </div>
                </div>
                
                <div class="col-md-3">
                    <div class="card stats-card border-success">
                        <div class="card-body">
                            <div class="stats-icon text-success">
                                <i class="fas fa-eye"></i>
                            </div>
                            <div class="stats-number text-success"><?php echo number_format($stats['active_products']); ?></div>
                            <div class="stats-label">Đang hiển thị</div>
                        </div>
                    </div>
                </div>
                
                <div class="col-md-3">
                    <div class="card stats-card border-warning">
                        <div class="card-body">
                            <div class="stats-icon text-warning">
                                <i class="fas fa-exclamation-triangle"></i>
                            </div>
                            <div class="stats-number text-warning"><?php echo number_format($stats['low_stock']); ?></div>
                            <div class="stats-label">Sắp hết hàng</div>
                        </div>
                    </div>
                </div>
                
                <div class="col-md-3">
                    <div class="card stats-card border-info">
                        <div class="card-body">
                            <div class="stats-icon text-info">
                                <i class="fas fa-plus"></i>
                            </div>
                            <div class="stats-number text-info"><?php echo number_format($stats['today_added']); ?></div>
                            <div class="stats-label">Hôm nay</div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Filters -->
            <div class="card mb-4">
                <div class="card-body">
                    <form method="GET" class="row g-3">
                        <div class="col-md-3">
                            <label class="form-label">Tìm kiếm</label>
                            <input type="text" name="search" class="form-control" 
                                   placeholder="Tên, SKU, thương hiệu..." 
                                   value="<?php echo htmlspecialchars($search); ?>">
                        </div>
                        
                        <div class="col-md-2">
                            <label class="form-label">Trạng thái</label>
                            <select name="status" class="form-control">
                                <option value="">Tất cả</option>
                                <option value="active" <?php echo $status_filter == 'active' ? 'selected' : ''; ?>>Hoạt động</option>
                                <option value="inactive" <?php echo $status_filter == 'inactive' ? 'selected' : ''; ?>>Tạm ẩn</option>
                                <option value="draft" <?php echo $status_filter == 'draft' ? 'selected' : ''; ?>>Bản nháp</option>
                            </select>
                        </div>
                        
                        <div class="col-md-3">
                            <label class="form-label">Danh mục</label>
                            <select name="category" class="form-control">
                                <option value="">Tất cả danh mục</option>
                                <?php foreach ($categories as $cat): ?>
                                    <option value="<?php echo $cat['id']; ?>" <?php echo $category_filter == $cat['id'] ? 'selected' : ''; ?>>
                                        <?php echo htmlspecialchars($cat['name']); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        
                        <div class="col-md-2">
                            <label class="form-label">Thương hiệu</label>
                            <select name="brand" class="form-control">
                                <option value="">Tất cả</option>
                                <?php foreach ($brands as $brand): ?>
                                    <option value="<?php echo $brand['brand']; ?>" <?php echo $brand_filter == $brand['brand'] ? 'selected' : ''; ?>>
                                        <?php echo htmlspecialchars($brand['brand']); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        
                        <div class="col-md-2 d-flex align-items-end">
                            <button type="submit" class="btn btn-primary me-2">
                                <i class="fas fa-search"></i>
                                Tìm
                            </button>
                            <a href="index.php" class="btn btn-outline-secondary">
                                <i class="fas fa-undo"></i>
                                Reset
                            </a>
                        </div>
                    </form>
                </div>
            </div>

            <!-- Products List -->
            <div class="card">
                <div class="card-header">
                    <div class="d-flex justify-content-between align-items-center">
                        <h3 class="card-title">
                            <i class="fas fa-list me-2"></i>
                            Danh sách sản phẩm (<?php echo number_format($total_products); ?>)
                        </h3>
                        <a href="add.php" class="btn btn-success btn-sm">
                            <i class="fas fa-plus"></i>
                            Thêm mới
                        </a>
                    </div>
                </div>
                
                <div class="card-body p-0">
                    <?php if (empty($products)): ?>
                        <div class="empty-state">
                            <i class="fas fa-box-open"></i>
                            <h5>Không có sản phẩm nào</h5>
                            <p class="text-muted">Hãy thêm sản phẩm đầu tiên cho cửa hàng</p>
                            <a href="add.php" class="btn btn-primary">
                                <i class="fas fa-plus me-2"></i>
                                Thêm sản phẩm đầu tiên
                            </a>
                        </div>
                    <?php else: ?>
                        <div class="table-responsive">
                            <table class="table">
                                <thead>
                                    <tr>
                                        <th width="80">Ảnh</th>
                                        <th>Sản phẩm</th>
                                        <th>Danh mục</th>
                                        <th>Giá</th>
                                        <th>Tồn kho</th>
                                        <th>Trạng thái</th>
                                        <th>Ngày tạo</th>
                                        <th width="180">Hành động</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($products as $product): ?>
                                        <tr>
                                            <td>
                                                <?php if ($product['main_image']): ?>
                                                    <img src="../../uploads/products/<?php echo $product['main_image']; ?>" 
                                                         alt="<?php echo htmlspecialchars($product['name']); ?>" 
                                                         class="product-image">
                                                <?php else: ?>
                                                    <div class="product-image bg-light d-flex align-items-center justify-content-center">
                                                        <i class="fas fa-image text-muted"></i>
                                                    </div>
                                                <?php endif; ?>
                                            </td>
                                            
                                            <td>
                                                <div>
                                                    <strong><?php echo htmlspecialchars($product['name']); ?></strong>
                                                    <br>
                                                    <small class="text-muted">
                                                        SKU: <?php echo htmlspecialchars($product['sku']); ?>
                                                    </small>
                                                    <?php if ($product['brand']): ?>
                                                        <br>
                                                        <span class="badge bg-secondary"><?php echo htmlspecialchars($product['brand']); ?></span>
                                                    <?php endif; ?>
                                                    <?php if ($product['variant_count'] > 0): ?>
                                                        <br>
                                                        <small class="text-info">
                                                            <i class="fas fa-cubes"></i> <?php echo $product['variant_count']; ?> biến thể
                                                        </small>
                                                    <?php endif; ?>
                                                </div>
                                            </td>
                                            
                                            <td>
                                                <?php if ($product['category_name']): ?>
                                                    <span class="badge bg-primary"><?php echo htmlspecialchars($product['category_name']); ?></span>
                                                <?php else: ?>
                                                    <span class="text-muted">Chưa phân loại</span>
                                                <?php endif; ?>
                                            </td>
                                            
                                            <td>
                                                <?php if ($product['sale_price']): ?>
                                                    <div>
                                                        <strong class="text-success"><?php echo formatPrice($product['sale_price']); ?></strong>
                                                        <br>
                                                        <small class="text-muted text-decoration-line-through">
                                                            <?php echo formatPrice($product['price']); ?>
                                                        </small>
                                                    </div>
                                                <?php else: ?>
                                                    <strong class="text-success"><?php echo formatPrice($product['price']); ?></strong>
                                                <?php endif; ?>
                                                
                                                <?php if ($product['min_price'] != $product['max_price'] && $product['variant_count'] > 0): ?>
                                                    <br>
                                                    <small class="text-info">
                                                        Biến thể: <?php echo formatPrice($product['min_price']); ?> - <?php echo formatPrice($product['max_price']); ?>
                                                    </small>
                                                <?php endif; ?>
                                            </td>
                                            
                                            <td>
                                                <?php 
                                                $total_stock = $product['total_stock'] ?: $product['stock_quantity'];
                                                $stock_class = $total_stock > 10 ? 'success' : ($total_stock > 0 ? 'warning' : 'danger');
                                                ?>
                                                <span class="badge bg-<?php echo $stock_class; ?>">
                                                    <?php echo $total_stock; ?>
                                                </span>
                                                <?php if ($total_stock <= $product['min_quantity']): ?>
                                                    <br>
                                                    <small class="text-danger">
                                                        <i class="fas fa-exclamation-triangle"></i> Thấp
                                                    </small>
                                                <?php endif; ?>
                                            </td>
                                            
                                            <td>
                                                <?php
                                                $status_class = [
                                                    'active' => 'success',
                                                    'inactive' => 'secondary',
                                                    'draft' => 'warning'
                                                ];
                                                $status_text = [
                                                    'active' => 'Hoạt động',
                                                    'inactive' => 'Tạm ẩn',
                                                    'draft' => 'Bản nháp'
                                                ];
                                                $class = $status_class[$product['status']] ?? 'secondary';
                                                $text = $status_text[$product['status']] ?? $product['status'];
                                                ?>
                                                <span class="badge bg-<?php echo $class; ?>">
                                                    <?php echo $text; ?>
                                                </span>
                                            </td>
                                            
                                            <td>
                                                <small><?php echo formatDate($product['created_at']); ?></small>
                                            </td>
                                            
                                            <td>
                                                <div class="action-buttons">
                                                    <!-- Quản lý biến thể - SỬA LỖI LINK -->
                                                    <a href="variants.php?product_id=<?php echo $product['id']; ?>" 
                                                       class="btn btn-outline-primary btn-sm" 
                                                       title="Quản lý biến thể">
                                                        <i class="fas fa-cubes"></i>
                                                    </a>
                                                    
                                                    <!-- Chỉnh sửa -->
                                                    <a href="edit.php?id=<?php echo $product['id']; ?>" 
                                                       class="btn btn-outline-warning btn-sm" 
                                                       title="Chỉnh sửa">
                                                        <i class="fas fa-edit"></i>
                                                    </a>
                                                    
                                                    <!-- Xem sản phẩm -->
                                                    <a href="../../customer/product_detail.php?id=<?php echo $product['id']; ?>" 
                                                       class="btn btn-outline-info btn-sm" 
                                                       title="Xem sản phẩm" target="_blank">
                                                        <i class="fas fa-eye"></i>
                                                    </a>
                                                    
                                                    <!-- Toggle trạng thái -->
                                                    <a href="?action=toggle_status&id=<?php echo $product['id']; ?>" 
                                                       class="btn btn-outline-secondary btn-sm" 
                                                       title="<?php echo $product['status'] == 'active' ? 'Ẩn sản phẩm' : 'Hiện sản phẩm'; ?>"
                                                       onclick="return confirm('Bạn có chắc muốn thay đổi trạng thái?')">
                                                        <i class="fas fa-toggle-<?php echo $product['status'] == 'active' ? 'on' : 'off'; ?>"></i>
                                                    </a>
                                                    
                                                    <!-- Xóa -->
                                                    <a href="?action=delete&id=<?php echo $product['id']; ?>" 
                                                       class="btn btn-outline-danger btn-sm" 
                                                       title="Xóa sản phẩm"
                                                       onclick="return confirm('Bạn có chắc muốn xóa sản phẩm này?\n\nLưu ý: Sản phẩm đã có đơn hàng sẽ không thể xóa!')">
                                                        <i class="fas fa-trash"></i>
                                                    </a>
                                                </div>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                        
                        <!-- Pagination -->
                        <?php if ($total_pages > 1): ?>
                            <div class="card-footer">
                                <nav aria-label="Product pagination">
                                    <ul class="pagination justify-content-center mb-0">
                                        <?php
                                        $query_params = $_GET;
                                        
                                        // Previous button
                                        if ($page > 1):
                                            $query_params['page'] = $page - 1;
                                            $prev_url = '?' . http_build_query($query_params);
                                        ?>
                                            <li class="page-item">
                                                <a class="page-link" href="<?php echo $prev_url; ?>">
                                                    <i class="fas fa-chevron-left"></i> Trước
                                                </a>
                                            </li>
                                        <?php endif; ?>
                                        
                                        <?php
                                        // Page numbers
                                        $start_page = max(1, $page - 2);
                                        $end_page = min($total_pages, $page + 2);
                                        
                                        for ($i = $start_page; $i <= $end_page; $i++):
                                            $query_params['page'] = $i;
                                            $page_url = '?' . http_build_query($query_params);
                                        ?>
                                            <li class="page-item <?php echo $i == $page ? 'active' : ''; ?>">
                                                <a class="page-link" href="<?php echo $page_url; ?>"><?php echo $i; ?></a>
                                            </li>
                                        <?php endfor; ?>
                                        
                                        <?php
                                        // Next button
                                        if ($page < $total_pages):
                                            $query_params['page'] = $page + 1;
                                            $next_url = '?' . http_build_query($query_params);
                                        ?>
                                            <li class="page-item">
                                                <a class="page-link" href="<?php echo $next_url; ?>">
                                                    Sau <i class="fas fa-chevron-right"></i>
                                                </a>
                                            </li>
                                        <?php endif; ?>
                                    </ul>
                                </nav>
                                
                                <div class="text-center mt-3">
                                    <small class="text-muted">
                                        Hiển thị <?php echo ($offset + 1); ?> - <?php echo min($offset + $per_page, $total_products); ?> 
                                        trong <?php echo number_format($total_products); ?> sản phẩm
                                    </small>
                                </div>
                            </div>
                        <?php endif; ?>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    
    <script>
        // Auto-submit form on filter change
        document.querySelectorAll('select[name="status"], select[name="category"], select[name="brand"]').forEach(select => {
            select.addEventListener('change', function() {
                this.form.submit();
            });
        });
        
        // Search with Enter key
        document.querySelector('input[name="search"]').addEventListener('keypress', function(e) {
            if (e.key === 'Enter') {
                this.form.submit();
            }
        });
        
        // Auto-hide alerts after 5 seconds
        document.querySelectorAll('.alert').forEach(function(alert) {
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
    </script>
</body>
</html>