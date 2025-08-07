<?php
// customer/orders.php - SIMPLE VERSION
/**
 * Theo dõi đơn hàng - Danh sách đơn hàng của khách hàng
 */

require_once '../config/database.php';
require_once '../config/config.php';

// Kiểm tra đăng nhập
if (!isset($_SESSION['customer_id'])) {
    $_SESSION['redirect_after_login'] = '/tktshop/customer/orders.php';
    alert('Vui lòng đăng nhập để xem đơn hàng của bạn', 'warning');
    redirect('/tktshop/customer/login.php');
}

$customer_id = $_SESSION['customer_id'];

// Lấy danh sách đơn hàng
$page = max(1, (int)($_GET['page'] ?? 1));
$limit = 10;
$offset = ($page - 1) * $limit;

$stmt = $pdo->prepare("
    SELECT dh.*, 
           COUNT(ct.id) as so_san_pham,
           SUM(ct.so_luong) as tong_so_luong
    FROM don_hang dh
    LEFT JOIN chi_tiet_don_hang ct ON dh.id = ct.don_hang_id
    WHERE dh.khach_hang_id = ?
    GROUP BY dh.id
    ORDER BY dh.ngay_dat_hang DESC
    LIMIT ? OFFSET ?
");
$stmt->execute([$customer_id, $limit, $offset]);
$orders = $stmt->fetchAll();

// Đếm tổng số đơn hàng
$count_stmt = $pdo->prepare("SELECT COUNT(*) FROM don_hang WHERE khach_hang_id = ?");
$count_stmt->execute([$customer_id]);
$total_orders = $count_stmt->fetchColumn();
$total_pages = ceil($total_orders / $limit);
?>

<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Đơn hàng của tôi - <?= SITE_NAME ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <style>
        .order-card {
            border: 1px solid #dee2e6;
            border-radius: 10px;
            margin-bottom: 20px;
            transition: all 0.3s ease;
        }
        
        .order-card:hover {
            box-shadow: 0 4px 15px rgba(0,0,0,0.1);
        }
        
        .order-header {
            background: #f8f9fa;
            border-bottom: 1px solid #dee2e6;
            border-radius: 10px 10px 0 0;
            padding: 15px 20px;
        }
        
        .order-body {
            padding: 20px;
        }
        
        .status-badge {
            font-size: 0.85rem;
            padding: 6px 12px;
            border-radius: 20px;
        }
        
        .empty-orders {
            text-align: center;
            padding: 60px 20px;
        }
    </style>
</head>
<body>
    <!-- Header -->
    <?php include 'includes/header.php'; ?>
    
    <div class="container py-4">
        <!-- Breadcrumb -->
        <nav aria-label="breadcrumb" class="mb-4">
            <ol class="breadcrumb">
                <li class="breadcrumb-item"><a href="/tktshop/customer/">Trang chủ</a></li>
                <li class="breadcrumb-item active">Đơn hàng của tôi</li>
            </ol>
        </nav>
        
        <!-- Page Header -->
        <div class="d-flex justify-content-between align-items-center mb-4">
            <div>
                <h2>Đơn hàng của tôi</h2>
                <p class="text-muted mb-0">Tổng cộng: <?= $total_orders ?> đơn hàng</p>
            </div>
        </div>
        
        <?php if (empty($orders)): ?>
            <!-- Empty Orders -->
            <div class="empty-orders">
                <i class="fas fa-shopping-bag fa-4x text-muted mb-4"></i>
                <h4>Bạn chưa có đơn hàng nào</h4>
                <p class="text-muted mb-4">Hãy khám phá và mua sắm những sản phẩm tuyệt vời</p>
                <a href="/tktshop/customer/products.php" class="btn btn-primary">
                    <i class="fas fa-shopping-cart me-2"></i>Mua sắm ngay
                </a>
            </div>
        <?php else: ?>
            <!-- Orders List -->
            <?php foreach ($orders as $order): ?>
                <div class="order-card">
                    <!-- Order Header -->
                    <div class="order-header">
                        <div class="row align-items-center">
                            <div class="col-md-6">
                                <div class="d-flex align-items-center">
                                    <i class="fas fa-shopping-bag me-2 text-primary"></i>
                                    <div>
                                        <strong>Đơn hàng #<?= $order['ma_don_hang'] ?></strong>
                                        <div class="text-muted small">
                                            Đặt hàng: <?= date('d/m/Y H:i', strtotime($order['ngay_dat_hang'])) ?>
                                        </div>
                                    </div>
                                </div>
                            </div>
                            <div class="col-md-6 text-md-end">
                                <?php 
                                $status_classes = [
                                    'cho_xac_nhan' => 'warning',
                                    'da_xac_nhan' => 'info',
                                    'dang_chuan_bi' => 'primary',
                                    'dang_giao' => 'primary',
                                    'da_giao' => 'success',
                                    'da_huy' => 'danger'
                                ];
                                $status_text = [
                                    'cho_xac_nhan' => 'Chờ xác nhận',
                                    'da_xac_nhan' => 'Đã xác nhận',
                                    'dang_chuan_bi' => 'Đang chuẩn bị',
                                    'dang_giao' => 'Đang giao',
                                    'da_giao' => 'Đã giao',
                                    'da_huy' => 'Đã hủy'
                                ];
                                ?>
                                <span class="status-badge bg-<?= $status_classes[$order['trang_thai_don_hang']] ?> text-white">
                                    <?= $status_text[$order['trang_thai_don_hang']] ?>
                                </span>
                            </div>
                        </div>
                    </div>
                    
                    <!-- Order Body -->
                    <div class="order-body">
                        <div class="row">
                            <div class="col-md-8">
                                <div class="d-flex align-items-center mb-3">
                                    <i class="fas fa-box me-2 text-muted"></i>
                                    <span><?= $order['so_san_pham'] ?> sản phẩm (<?= $order['tong_so_luong'] ?> món)</span>
                                    <span class="mx-3">•</span>
                                    <i class="fas fa-credit-card me-2 text-muted"></i>
                                    <span><?= $order['phuong_thuc_thanh_toan'] == 'vnpay' ? 'VNPay' : 'COD' ?></span>
                                </div>
                                
                                <div class="text-muted">
                                    <i class="fas fa-truck me-2"></i>
                                    Giao đến: <?= htmlspecialchars($order['dia_chi_nhan']) ?>
                                </div>
                            </div>
                            
                            <div class="col-md-4 text-md-end">
                                <div class="mb-2">
                                    <div class="text-muted small">Tổng tiền:</div>
                                    <div class="h5 text-primary mb-0"><?= formatPrice($order['tong_thanh_toan']) ?></div>
                                </div>
                                
                                <div class="d-flex gap-2 justify-content-md-end">
                                    <a href="/tktshop/customer/order_detail.php?id=<?= $order['id'] ?>" class="btn btn-outline-primary btn-sm">
                                        <i class="fas fa-eye me-1"></i>Chi tiết
                                    </a>
                                    
                                    <?php if ($order['trang_thai_don_hang'] == 'da_giao'): ?>
                                        <button class="btn btn-primary btn-sm" onclick="reorder(<?= $order['id'] ?>)">
                                            <i class="fas fa-redo me-1"></i>Mua lại
                                        </button>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            <?php endforeach; ?>
            
            <!-- Pagination -->
            <?php if ($total_pages > 1): ?>
                <nav aria-label="Phân trang đơn hàng" class="mt-4">
                    <ul class="pagination justify-content-center">
                        <?php if ($page > 1): ?>
                            <li class="page-item">
                                <a class="page-link" href="?page=<?= $page - 1 ?>">
                                    <i class="fas fa-chevron-left"></i>
                                </a>
                            </li>
                        <?php endif; ?>
                        
                        <?php for ($i = 1; $i <= $total_pages; $i++): ?>
                            <li class="page-item <?= $i == $page ? 'active' : '' ?>">
                                <a class="page-link" href="?page=<?= $i ?>">
                                    <?= $i ?>
                                </a>
                            </li>
                        <?php endfor; ?>
                        
                        <?php if ($page < $total_pages): ?>
                            <li class="page-item">
                                <a class="page-link" href="?page=<?= $page + 1 ?>">
                                    <i class="fas fa-chevron-right"></i>
                                </a>
                            </li>
                        <?php endif; ?>
                    </ul>
                </nav>
            <?php endif; ?>
        <?php endif; ?>
    </div>
    
    <!-- Footer -->
    <?php include 'includes/footer.php'; ?>
    
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
    
    <script>
        function reorder(orderId) {
            if (confirm('Bạn muốn mua lại các sản phẩm trong đơn hàng này?')) {
                alert('Chức năng mua lại sẽ được phát triển sớm!');
            }
        }
    </script>
</body>
</html>