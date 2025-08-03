<?php
// admin/dashboard.php - ĐÃ SỬA ĐƯỜNG DẪN
/**
 * Trang chủ admin - Dashboard với thống kê tổng quan
 */

require_once '../config/database.php';
require_once '../config/config.php';

requireLogin();

// Khởi tạo các biến mặc định
$order_stats = [
    'tong_don_hang' => 0, 
    'cho_xac_nhan' => 0, 
    'da_giao' => 0, 
    'da_huy' => 0, 
    'doanh_thu' => 0
];

$product_stats = [
    'tong_san_pham' => 0, 
    'dang_ban' => 0, 
    'het_hang' => 0, 
    'an_san_pham' => 0
];

$user_stats = [
    'tong_nguoi_dung' => 0, 
    'khach_hang' => 0, 
    'admin' => 0, 
    'nhan_vien' => 0
];

$category_stats = 0;
$color_stats = 0;
$size_stats = 0;
$recent_orders = [];
$top_products = [];

// Thống kê tổng quan
try {
    // Thống kê đơn hàng
    $stmt = $pdo->query("
        SELECT 
            COUNT(*) as tong_don_hang,
            COUNT(CASE WHEN trang_thai_don_hang = 'cho_xac_nhan' THEN 1 END) as cho_xac_nhan,
            COUNT(CASE WHEN trang_thai_don_hang = 'da_giao' THEN 1 END) as da_giao,
            COUNT(CASE WHEN trang_thai_don_hang = 'da_huy' THEN 1 END) as da_huy,
            SUM(CASE WHEN trang_thai_don_hang = 'da_giao' THEN tong_thanh_toan ELSE 0 END) as doanh_thu
        FROM don_hang
    ");
    if ($stmt) {
        $order_stats = $stmt->fetch(PDO::FETCH_ASSOC);
    }

    // Thống kê sản phẩm
    $stmt = $pdo->query("
        SELECT 
            COUNT(*) as tong_san_pham,
            COUNT(CASE WHEN trang_thai = 'hoat_dong' THEN 1 END) as dang_ban,
            COUNT(CASE WHEN trang_thai = 'het_hang' THEN 1 END) as het_hang,
            COUNT(CASE WHEN trang_thai = 'an' THEN 1 END) as an_san_pham
        FROM san_pham_chinh
    ");
    if ($stmt) {
        $product_stats = $stmt->fetch(PDO::FETCH_ASSOC);
    }

    // Thống kê người dùng
    $stmt = $pdo->query("
        SELECT 
            COUNT(*) as tong_nguoi_dung,
            COUNT(CASE WHEN vai_tro = 'khach_hang' THEN 1 END) as khach_hang,
            COUNT(CASE WHEN vai_tro = 'admin' THEN 1 END) as admin,
            COUNT(CASE WHEN vai_tro = 'nhan_vien' THEN 1 END) as nhan_vien
        FROM nguoi_dung
    ");
    if ($stmt) {
        $user_stats = $stmt->fetch(PDO::FETCH_ASSOC);
    }

    // Thống kê danh mục, màu sắc, kích cỡ
    $stmt = $pdo->query("SELECT COUNT(*) FROM danh_muc_giay WHERE trang_thai = 'hoat_dong'");
    if ($stmt) {
        $category_stats = $stmt->fetchColumn();
    }

    $stmt = $pdo->query("SELECT COUNT(*) FROM mau_sac WHERE trang_thai = 'hoat_dong'");
    if ($stmt) {
        $color_stats = $stmt->fetchColumn();
    }

    $stmt = $pdo->query("SELECT COUNT(*) FROM kich_co WHERE trang_thai = 'hoat_dong'");
    if ($stmt) {
        $size_stats = $stmt->fetchColumn();
    }

    // Đơn hàng mới nhất
    $stmt = $pdo->query("
        SELECT dh.*, nd.ho_ten as ten_khach_hang
        FROM don_hang dh
        LEFT JOIN nguoi_dung nd ON dh.khach_hang_id = nd.id
        ORDER BY dh.ngay_dat_hang DESC
        LIMIT 5
    ");
    if ($stmt) {
        $recent_orders = $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    // Sản phẩm bán chạy
    $stmt = $pdo->query("
        SELECT sp.*, COUNT(ct.id) as so_lan_mua
        FROM san_pham_chinh sp
        LEFT JOIN chi_tiet_don_hang ct ON sp.id = ct.san_pham_id
        LEFT JOIN don_hang dh ON ct.don_hang_id = dh.id AND dh.trang_thai_don_hang = 'da_giao'
        GROUP BY sp.id
        ORDER BY so_lan_mua DESC
        LIMIT 5
    ");
    if ($stmt) {
        $top_products = $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

} catch (Exception $e) {
    // Nếu có lỗi, sử dụng giá trị mặc định đã khởi tạo
    error_log("Dashboard error: " . $e->getMessage());
}
?>

<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dashboard - <?= SITE_NAME ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <style>
        .stats-card {
            transition: transform 0.3s ease;
        }
        .stats-card:hover {
            transform: translateY(-5px);
        }
        .stats-number {
            font-size: 2rem;
            font-weight: bold;
        }
        .welcome-card {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
        }
    </style>
</head>
<body>
    <div class="container-fluid">
        <div class="row">
            <!-- Sidebar -->
            <?php include 'layouts/sidebar.php'; ?>
            
            <!-- Main content -->
            <div class="col-md-10">
                <!-- Welcome Header -->
                <div class="card welcome-card mb-4">
                    <div class="card-body py-4">
                        <div class="row align-items-center">
                            <div class="col-md-8">
                                <h2 class="mb-2">Chào mừng trở lại, <?= htmlspecialchars($_SESSION['admin_name']) ?>!</h2>
                                <p class="mb-0">Vai trò: <span class="badge bg-light text-dark"><?= $_SESSION['admin_role'] == 'admin' ? 'Quản trị viên' : 'Nhân viên' ?></span></p>
                                <small class="opacity-75">Hôm nay: <?= date('d/m/Y H:i') ?></small>
                            </div>
                            <div class="col-md-4 text-end">
                                <i class="fas fa-tachometer-alt fa-3x opacity-50"></i>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Thống kê tổng quan -->
                <div class="row mb-4">
                    <!-- Đơn hàng -->
                    <div class="col-xl-3 col-md-6 mb-3">
                        <div class="card stats-card border-primary">
                            <div class="card-body">
                                <div class="row align-items-center">
                                    <div class="col">
                                        <div class="stats-number text-primary"><?= number_format($order_stats['tong_don_hang'] ?? 0) ?></div>
                                        <div class="text-muted">Tổng đơn hàng</div>
                                        <small class="text-warning">
                                            <i class="fas fa-clock"></i> <?= $order_stats['cho_xac_nhan'] ?? 0 ?> chờ xác nhận
                                        </small>
                                    </div>
                                    <div class="col-auto">
                                        <i class="fas fa-shopping-cart fa-2x text-primary"></i>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                    
                    <!-- Doanh thu -->
                    <div class="col-xl-3 col-md-6 mb-3">
                        <div class="card stats-card border-success">
                            <div class="card-body">
                                <div class="row align-items-center">
                                    <div class="col">
                                        <div class="stats-number text-success"><?= formatPrice($order_stats['doanh_thu'] ?? 0) ?></div>
                                        <div class="text-muted">Doanh thu</div>
                                        <small class="text-success">
                                            <i class="fas fa-check"></i> <?= $order_stats['da_giao'] ?? 0 ?> đã giao
                                        </small>
                                    </div>
                                    <div class="col-auto">
                                        <i class="fas fa-money-bill-wave fa-2x text-success"></i>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                    
                    <!-- Sản phẩm -->
                    <div class="col-xl-3 col-md-6 mb-3">
                        <div class="card stats-card border-info">
                            <div class="card-body">
                                <div class="row align-items-center">
                                    <div class="col">
                                        <div class="stats-number text-info"><?= number_format($product_stats['tong_san_pham'] ?? 0) ?></div>
                                        <div class="text-muted">Sản phẩm</div>
                                        <small class="text-info">
                                            <i class="fas fa-eye"></i> <?= $product_stats['dang_ban'] ?? 0 ?> đang bán
                                        </small>
                                    </div>
                                    <div class="col-auto">
                                        <i class="fas fa-box fa-2x text-info"></i>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                    
                    <!-- Người dùng -->
                    <div class="col-xl-3 col-md-6 mb-3">
                        <div class="card stats-card border-warning">
                            <div class="card-body">
                                <div class="row align-items-center">
                                    <div class="col">
                                        <div class="stats-number text-warning"><?= number_format($user_stats['tong_nguoi_dung'] ?? 0) ?></div>
                                        <div class="text-muted">Người dùng</div>
                                        <small class="text-warning">
                                            <i class="fas fa-users"></i> <?= $user_stats['khach_hang'] ?? 0 ?> khách hàng
                                        </small>
                                    </div>
                                    <div class="col-auto">
                                        <i class="fas fa-users fa-2x text-warning"></i>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Thống kê chi tiết -->
                <div class="row mb-4">
                    <div class="col-md-4">
                        <div class="card">
                            <div class="card-header">
                                <h5><i class="fas fa-tags me-2"></i>Danh mục & Thuộc tính</h5>
                            </div>
                            <div class="card-body">
                                <div class="d-flex justify-content-between mb-2">
                                    <span>Danh mục:</span>
                                    <span class="badge bg-primary"><?= $category_stats ?></span>
                                </div>
                                <div class="d-flex justify-content-between mb-2">
                                    <span>Màu sắc:</span>
                                    <span class="badge bg-info"><?= $color_stats ?></span>
                                </div>
                                <div class="d-flex justify-content-between">
                                    <span>Kích cỡ:</span>
                                    <span class="badge bg-secondary"><?= $size_stats ?></span>
                                </div>
                            </div>
                        </div>
                    </div>
                    
                    <div class="col-md-4">
                        <div class="card">
                            <div class="card-header">
                                <h5><i class="fas fa-chart-pie me-2"></i>Trạng thái đơn hàng</h5>
                            </div>
                            <div class="card-body">
                                <div class="d-flex justify-content-between mb-2">
                                    <span>Chờ xác nhận:</span>
                                    <span class="badge bg-warning"><?= $order_stats['cho_xac_nhan'] ?? 0 ?></span>
                                </div>
                                <div class="d-flex justify-content-between mb-2">
                                    <span>Đã giao:</span>
                                    <span class="badge bg-success"><?= $order_stats['da_giao'] ?? 0 ?></span>
                                </div>
                                <div class="d-flex justify-content-between">
                                    <span>Đã hủy:</span>
                                    <span class="badge bg-danger"><?= $order_stats['da_huy'] ?? 0 ?></span>
                                </div>
                            </div>
                        </div>
                    </div>
                    
                    <div class="col-md-4">
                        <div class="card">
                            <div class="card-header">
                                <h5><i class="fas fa-box-open me-2"></i>Trạng thái sản phẩm</h5>
                            </div>
                            <div class="card-body">
                                <div class="d-flex justify-content-between mb-2">
                                    <span>Đang bán:</span>
                                    <span class="badge bg-success"><?= $product_stats['dang_ban'] ?? 0 ?></span>
                                </div>
                                <div class="d-flex justify-content-between mb-2">
                                    <span>Hết hàng:</span>
                                    <span class="badge bg-warning"><?= $product_stats['het_hang'] ?? 0 ?></span>
                                </div>
                                <div class="d-flex justify-content-between">
                                    <span>Ẩn:</span>
                                    <span class="badge bg-secondary"><?= $product_stats['an_san_pham'] ?? 0 ?></span>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="row">
                    <!-- Đơn hàng mới nhất -->
                    <div class="col-md-7">
                        <div class="card">
                            <div class="card-header d-flex justify-content-between align-items-center">
                                <h5><i class="fas fa-shopping-cart me-2"></i>Đơn hàng mới nhất</h5>
                                <a href="<?= adminUrl('orders/') ?>" class="btn btn-sm btn-outline-primary">Xem tất cả</a>
                            </div>
                            <div class="card-body">
                                <?php if (empty($recent_orders)): ?>
                                    <div class="text-center py-4">
                                        <i class="fas fa-inbox fa-3x text-muted mb-3"></i>
                                        <p class="text-muted">Chưa có đơn hàng nào</p>
                                    </div>
                                <?php else: ?>
                                    <div class="table-responsive">
                                        <table class="table table-sm">
                                            <thead>
                                                <tr>
                                                    <th>Mã đơn</th>
                                                    <th>Khách hàng</th>
                                                    <th>Tổng tiền</th>
                                                    <th>Trạng thái</th>
                                                    <th>Thời gian</th>
                                                </tr>
                                            </thead>
                                            <tbody>
                                                <?php foreach ($recent_orders as $order): ?>
                                                    <tr>
                                                        <td>
                                                            <a href="<?= adminUrl('orders/detail.php?id=' . $order['id']) ?>" class="text-decoration-none">
                                                                <?= $order['ma_don_hang'] ?>
                                                            </a>
                                                        </td>
                                                        <td><?= htmlspecialchars($order['ho_ten_nhan']) ?></td>
                                                        <td class="text-success fw-bold"><?= formatPrice($order['tong_thanh_toan']) ?></td>
                                                        <td>
                                                            <?php 
                                                            $status_class = [
                                                                'cho_xac_nhan' => 'warning',
                                                                'da_xac_nhan' => 'info',
                                                                'dang_giao' => 'primary',
                                                                'da_giao' => 'success',
                                                                'da_huy' => 'danger'
                                                            ];
                                                            $status_text = [
                                                                'cho_xac_nhan' => 'Chờ xác nhận',
                                                                'da_xac_nhan' => 'Đã xác nhận',
                                                                'dang_giao' => 'Đang giao',
                                                                'da_giao' => 'Đã giao',
                                                                'da_huy' => 'Đã hủy'
                                                            ];
                                                            $status = $status_class[$order['trang_thai_don_hang']] ?? 'secondary';
                                                            $text = $status_text[$order['trang_thai_don_hang']] ?? $order['trang_thai_don_hang'];
                                                            ?>
                                                            <span class="badge bg-<?= $status ?>">
                                                                <?= $text ?>
                                                            </span>
                                                        </td>
                                                        <td>
                                                            <small><?= date('d/m H:i', strtotime($order['ngay_dat_hang'])) ?></small>
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
                    
                    <!-- Sản phẩm bán chạy -->
                    <div class="col-md-5">
                        <div class="card">
                            <div class="card-header d-flex justify-content-between align-items-center">
                                <h5><i class="fas fa-fire me-2"></i>Sản phẩm bán chạy</h5>
                                <a href="<?= adminUrl('products/') ?>" class="btn btn-sm btn-outline-primary">Xem tất cả</a>
                            </div>
                            <div class="card-body">
                                <?php if (empty($top_products)): ?>
                                    <div class="text-center py-4">
                                        <i class="fas fa-box-open fa-3x text-muted mb-3"></i>
                                        <p class="text-muted">Chưa có dữ liệu bán hàng</p>
                                    </div>
                                <?php else: ?>
                                    <?php foreach ($top_products as $index => $product): ?>
                                        <div class="d-flex align-items-center mb-3">
                                            <div class="bg-primary text-white rounded-circle d-flex align-items-center justify-content-center me-3" 
                                                 style="width: 30px; height: 30px; font-size: 12px;">
                                                <?= $index + 1 ?>
                                            </div>
                                            <div class="flex-grow-1">
                                                <div class="fw-bold"><?= htmlspecialchars($product['ten_san_pham']) ?></div>
                                                <small class="text-muted">
                                                    <?= htmlspecialchars($product['thuong_hieu']) ?> - 
                                                    <?= formatPrice($product['gia_khuyen_mai'] ?: $product['gia_goc']) ?>
                                                </small>
                                            </div>
                                            <div class="text-end">
                                                <span class="badge bg-success"><?= $product['so_lan_mua'] ?></span>
                                                <br><small class="text-muted">lượt mua</small>
                                            </div>
                                        </div>
                                    <?php endforeach; ?>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Quick Actions -->
                <div class="row mt-4">
                    <div class="col-12">
                        <div class="card">
                            <div class="card-header">
                                <h5><i class="fas fa-bolt me-2"></i>Hành động nhanh</h5>
                            </div>
                            <div class="card-body">
                                <div class="row">
                                    <div class="col-md-2 mb-2">
                                        <a href="<?= adminUrl('products/create.php') ?>" class="btn btn-outline-primary w-100">
                                            <i class="fas fa-plus mb-1"></i><br>
                                            <small>Thêm sản phẩm</small>
                                        </a>
                                    </div>
                                    <div class="col-md-2 mb-2">
                                        <a href="<?= adminUrl('orders/') ?>" class="btn btn-outline-warning w-100">
                                            <i class="fas fa-shopping-cart mb-1"></i><br>
                                            <small>Xem đơn hàng</small>
                                        </a>
                                    </div>
                                    <div class="col-md-2 mb-2">
                                        <a href="<?= adminUrl('users/create.php') ?>" class="btn btn-outline-info w-100">
                                            <i class="fas fa-user-plus mb-1"></i><br>
                                            <small>Thêm người dùng</small>
                                        </a>
                                    </div>
                                    <div class="col-md-2 mb-2">
                                        <a href="<?= adminUrl('categories/create.php') ?>" class="btn btn-outline-success w-100">
                                            <i class="fas fa-tags mb-1"></i><br>
                                            <small>Thêm danh mục</small>
                                        </a>
                                    </div>
                                    <div class="col-md-2 mb-2">
                                        <a href="<?= adminUrl('reviews/') ?>" class="btn btn-outline-secondary w-100">
                                            <i class="fas fa-star mb-1"></i><br>
                                            <small>Quản lý đánh giá</small>
                                        </a>
                                    </div>
                                    <div class="col-md-2 mb-2">
                                        <a href="<?= BASE_URL ?>/" target="_blank" class="btn btn-outline-dark w-100">
                                            <i class="fas fa-external-link-alt mb-1"></i><br>
                                            <small>Xem website</small>
                                        </a>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        // Auto refresh page every 5 minutes to update stats
        setTimeout(() => {
            location.reload();
        }, 300000);
        
        // Add loading effect to quick action buttons
        document.querySelectorAll('.btn-outline-primary, .btn-outline-warning, .btn-outline-info, .btn-outline-success, .btn-outline-secondary').forEach(btn => {
            btn.addEventListener('click', function(e) {
                if (!this.href.includes('#')) {
                    this.innerHTML += ' <i class="fas fa-spinner fa-spin"></i>';
                    this.disabled = true;
                }
            });
        });
    </script>
</body>
</html>