<?php
// customer/orders.php
/**
 * Theo dõi đơn hàng - Danh sách đơn hàng của khách hàng, trạng thái vận chuyển
 * Chức năng: Xem lịch sử đơn hàng, chi tiết, trạng thái, hủy đơn hàng
 */

require_once '../config/database.php';
require_once '../config/config.php';

// Kiểm tra đăng nhập
if (!isset($_SESSION['customer_id'])) {
    $_SESSION['redirect_after_login'] = '/customer/orders.php';
    alert('Vui lòng đăng nhập để xem đơn hàng của bạn', 'warning');
    redirect('/customer/login.php');
}

$customer_id = $_SESSION['customer_id'];

// Xử lý hủy đơn hàng
if (isset($_GET['cancel_order'])) {
    $order_id = (int)$_GET['cancel_order'];
    
    // Kiểm tra đơn hàng thuộc về khách hàng và có thể hủy
    $stmt = $pdo->prepare("
        SELECT id, trang_thai_don_hang, trang_thai_thanh_toan 
        FROM don_hang 
        WHERE id = ? AND khach_hang_id = ? 
        AND trang_thai_don_hang IN ('cho_xac_nhan', 'da_xac_nhan')
    ");
    $stmt->execute([$order_id, $customer_id]);
    $order = $stmt->fetch();
    
    if ($order) {
        try {
            $pdo->beginTransaction();
            
            // Cập nhật trạng thái đơn hàng
            $stmt = $pdo->prepare("
                UPDATE don_hang 
                SET trang_thai_don_hang = 'da_huy', 
                    ngay_huy = NOW(), 
                    ly_do_huy = 'Khách hàng yêu cầu hủy'
                WHERE id = ?
            ");
            $stmt->execute([$order_id]);
            
            // Hoàn lại tồn kho
            $stmt = $pdo->prepare("
                UPDATE bien_the_san_pham btp
                INNER JOIN chi_tiet_don_hang ct ON btp.id = ct.bien_the_id
                SET btp.so_luong_ton_kho = btp.so_luong_ton_kho + ct.so_luong
                WHERE ct.don_hang_id = ?
            ");
            $stmt->execute([$order_id]);
            
            $pdo->commit();
            alert('Hủy đơn hàng thành công!', 'success');
            
        } catch (Exception $e) {
            $pdo->rollback();
            alert('Có lỗi xảy ra khi hủy đơn hàng!', 'danger');
        }
    } else {
        alert('Không thể hủy đơn hàng này!', 'danger');
    }
    
    redirect('/customer/orders.php');
}

// Lấy danh sách đơn hàng
$status_filter = $_GET['status'] ?? '';
$page = max(1, (int)($_GET['page'] ?? 1));
$limit = 10;
$offset = ($page - 1) * $limit;

$where_conditions = ["dh.khach_hang_id = ?"];
$params = [$customer_id];

if (!empty($status_filter)) {
    $where_conditions[] = "dh.trang_thai_don_hang = ?";
    $params[] = $status_filter;
}

$sql = "
    SELECT dh.*, 
           COUNT(ct.id) as so_san_pham,
           SUM(ct.so_luong) as tong_so_luong,
           vnp.trang_thai as trang_thai_vnpay
    FROM don_hang dh
    LEFT JOIN chi_tiet_don_hang ct ON dh.id = ct.don_hang_id
    LEFT JOIN thanh_toan_vnpay vnp ON dh.id = vnp.don_hang_id
    WHERE " . implode(" AND ", $where_conditions) . "
    GROUP BY dh.id
    ORDER BY dh.ngay_dat_hang DESC
    LIMIT ? OFFSET ?
";

$params[] = $limit;
$params[] = $offset;

$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$orders = $stmt->fetchAll();

// Đếm tổng số đơn hàng để phân trang
$count_params = array_slice($params, 0, -2); // Bỏ limit và offset
$count_sql = "
    SELECT COUNT(DISTINCT dh.id)
    FROM don_hang dh
    WHERE " . implode(" AND ", $where_conditions);

$count_stmt = $pdo->prepare($count_sql);
$count_stmt->execute($count_params);
$total_orders = $count_stmt->fetchColumn();
$total_pages = ceil($total_orders / $limit);

// Thống kê đơn hàng
$stats = $pdo->prepare("
    SELECT 
        trang_thai_don_hang,
        COUNT(*) as so_luong
    FROM don_hang 
    WHERE khach_hang_id = ?
    GROUP BY trang_thai_don_hang
")->execute([$customer_id]);
$stats = $stats->fetchAll();

$order_stats = [
    'cho_xac_nhan' => 0,
    'da_xac_nhan' => 0,
    'dang_chuan_bi' => 0,
    'dang_giao' => 0,
    'da_giao' => 0,
    'da_huy' => 0
];

foreach ($stats as $stat) {
    if (isset($order_stats[$stat['trang_thai_don_hang']])) {
        $order_stats[$stat['trang_thai_don_hang']] = $stat['so_luong'];
    }
}
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
        
        .order-footer {
            background: #f8f9fa;
            border-top: 1px solid #dee2e6;
            border-radius: 0 0 10px 10px;
            padding: 15px 20px;
        }
        
        .status-badge {
            font-size: 0.85rem;
            padding: 6px 12px;
            border-radius: 20px;
        }
        
        .order-timeline {
            position: relative;
            padding-left: 30px;
        }
        
        .order-timeline::before {
            content: '';
            position: absolute;
            left: 10px;
            top: 0;
            bottom: 0;
            width: 2px;
            background: #dee2e6;
        }
        
        .timeline-item {
            position: relative;
            margin-bottom: 20px;
        }
        
        .timeline-item::before {
            content: '';
            position: absolute;
            left: -35px;
            top: 5px;
            width: 12px;
            height: 12px;
            border-radius: 50%;
            background: #6c757d;
        }
        
        .timeline-item.completed::before {
            background: #28a745;
        }
        
        .timeline-item.current::before {
            background: #007bff;
            animation: pulse 2s infinite;
        }
        
        @keyframes pulse {
            0% { box-shadow: 0 0 0 0 rgba(0, 123, 255, 0.7); }
            70% { box-shadow: 0 0 0 10px rgba(0, 123, 255, 0); }
            100% { box-shadow: 0 0 0 0 rgba(0, 123, 255, 0); }
        }
        
        .product-item {
            border-bottom: 1px solid #eee;
            padding: 15px 0;
        }
        
        .product-item:last-child {
            border-bottom: none;
        }
        
        .product-image {
            width: 60px;
            height: 60px;
            object-fit: cover;
            border-radius: 8px;
        }
        
        .empty-orders {
            text-align: center;
            padding: 60px 20px;
        }
        
        .stats-card {
            border: 1px solid #dee2e6;
            border-radius: 8px;
            padding: 15px;
            text-align: center;
            cursor: pointer;
            transition: all 0.3s ease;
        }
        
        .stats-card:hover,
        .stats-card.active {
            border-color: #007bff;
            background-color: #f8f9ff;
        }
        
        @media (max-width: 768px) {
            .order-header {
                padding: 10px 15px;
            }
            
            .order-body,
            .order-footer {
                padding: 15px;
            }
            
            .stats-card {
                margin-bottom: 10px;
            }
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
                <li class="breadcrumb-item"><a href="/">Trang chủ</a></li>
                <li class="breadcrumb-item active">Đơn hàng của tôi</li>
            </ol>
        </nav>
        
        <!-- Page Header -->
        <div class="d-flex justify-content-between align-items-center mb-4">
            <div>
                <h2>Đơn hàng của tôi</h2>
                <p class="text-muted mb-0">Theo dõi và quản lý đơn hàng của bạn</p>
            </div>
        </div>
        
        <!-- Order Statistics -->
        <div class="row mb-4">
            <div class="col-lg-2 col-md-4 col-6">
                <div class="stats-card <?= empty($status_filter) ? 'active' : '' ?>" 
                     onclick="filterOrders('')">
                    <div class="h4 mb-1 text-primary"><?= array_sum($order_stats) ?></div>
                    <div class="small text-muted">Tất cả</div>
                </div>
            </div>
            <div class="col-lg-2 col-md-4 col-6">
                <div class="stats-card <?= $status_filter == 'cho_xac_nhan' ? 'active' : '' ?>" 
                     onclick="filterOrders('cho_xac_nhan')">
                    <div class="h4 mb-1 text-warning"><?= $order_stats['cho_xac_nhan'] ?></div>
                    <div class="small text-muted">Chờ xác nhận</div>
                </div>
            </div>
            <div class="col-lg-2 col-md-4 col-6">
                <div class="stats-card <?= $status_filter == 'dang_giao' ? 'active' : '' ?>" 
                     onclick="filterOrders('dang_giao')">
                    <div class="h4 mb-1 text-info"><?= $order_stats['dang_giao'] ?></div>
                    <div class="small text-muted">Đang giao</div>
                </div>
            </div>
            <div class="col-lg-2 col-md-4 col-6">
                <div class="stats-card <?= $status_filter == 'da_giao' ? 'active' : '' ?>" 
                     onclick="filterOrders('da_giao')">
                    <div class="h4 mb-1 text-success"><?= $order_stats['da_giao'] ?></div>
                    <div class="small text-muted">Đã giao</div>
                </div>
            </div>
            <div class="col-lg-2 col-md-4 col-6">
                <div class="stats-card <?= $status_filter == 'da_huy' ? 'active' : '' ?>" 
                     onclick="filterOrders('da_huy')">
                    <div class="h4 mb-1 text-danger"><?= $order_stats['da_huy'] ?></div>
                    <div class="small text-muted">Đã hủy</div>
                </div>
            </div>
        </div>
        
        <?php if (empty($orders)): ?>
            <!-- Empty Orders -->
            <div class="empty-orders">
                <i class="fas fa-shopping-bag fa-4x text-muted mb-4"></i>
                <h4>Bạn chưa có đơn hàng nào</h4>
                <p class="text-muted mb-4">Hãy khám phá và mua sắm những sản phẩm tuyệt vời</p>
                <a href="/customer/products.php" class="btn btn-primary">
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
                                    <i class="fas fa-circle me-1" style="font-size: 0.5rem;"></i>
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
                                
                                <?php if ($order['ghi_chu_khach_hang']): ?>
                                    <div class="mb-3">
                                        <i class="fas fa-comment me-2 text-muted"></i>
                                        <small class="text-muted"><?= htmlspecialchars($order['ghi_chu_khach_hang']) ?></small>
                                    </div>
                                <?php endif; ?>
                                
                                <!-- Order Timeline -->
                                <div class="order-timeline">
                                    <div class="timeline-item completed">
                                        <strong>Đơn hàng đã được tạo</strong>
                                        <div class="small text-muted"><?= date('d/m/Y H:i', strtotime($order['ngay_dat_hang'])) ?></div>
                                    </div>
                                    
                                    <?php if ($order['ngay_xac_nhan']): ?>
                                        <div class="timeline-item completed">
                                            <strong>Đơn hàng đã được xác nhận</strong>
                                            <div class="small text-muted"><?= date('d/m/Y H:i', strtotime($order['ngay_xac_nhan'])) ?></div>
                                        </div>
                                    <?php elseif ($order['trang_thai_don_hang'] == 'cho_xac_nhan'): ?>
                                        <div class="timeline-item current">
                                            <strong>Đang chờ xác nhận</strong>
                                            <div class="small text-muted">Chúng tôi sẽ xác nhận đơn hàng trong ít phút</div>
                                        </div>
                                    <?php endif; ?>
                                    
                                    <?php if ($order['ngay_giao_hang']): ?>
                                        <div class="timeline-item completed">
                                            <strong>Đang giao hàng</strong>
                                            <div class="small text-muted"><?= date('d/m/Y H:i', strtotime($order['ngay_giao_hang'])) ?></div>
                                            <?php if ($order['ma_van_don']): ?>
                                                <div class="small text-info">Mã vận đơn: <?= $order['ma_van_don'] ?></div>
                                            <?php endif; ?>
                                        </div>
                                    <?php elseif (in_array($order['trang_thai_don_hang'], ['da_xac_nhan', 'dang_chuan_bi'])): ?>
                                        <div class="timeline-item current">
                                            <strong>Đang chuẩn bị hàng</strong>
                                            <div class="small text-muted">Đơn hàng đang được đóng gói</div>
                                        </div>
                                    <?php endif; ?>
                                    
                                    <?php if ($order['ngay_hoan_thanh']): ?>
                                        <div class="timeline-item completed">
                                            <strong>Giao hàng thành công</strong>
                                            <div class="small text-muted"><?= date('d/m/Y H:i', strtotime($order['ngay_hoan_thanh'])) ?></div>
                                        </div>
                                    <?php elseif ($order['trang_thai_don_hang'] == 'dang_giao'): ?>
                                        <div class="timeline-item current">
                                            <strong>Đang giao đến bạn</strong>
                                            <div class="small text-muted">Shipper đang trên đường giao hàng</div>
                                        </div>
                                    <?php endif; ?>
                                    
                                    <?php if ($order['ngay_huy']): ?>
                                        <div class="timeline-item" style="color: #dc3545;">
                                            <strong>Đơn hàng đã bị hủy</strong>
                                            <div class="small text-muted"><?= date('d/m/Y H:i', strtotime($order['ngay_huy'])) ?></div>
                                            <?php if ($order['ly_do_huy']): ?>
                                                <div class="small text-muted">Lý do: <?= htmlspecialchars($order['ly_do_huy']) ?></div>
                                            <?php endif; ?>
                                        </div>
                                    <?php endif; ?>
                                </div>
                            </div>
                            
                            <div class="col-md-4 text-md-end">
                                <div class="mb-2">
                                    <div class="text-muted small">Tổng tiền:</div>
                                    <div class="h4 text-primary mb-0"><?= formatPrice($order['tong_thanh_toan']) ?></div>
                                </div>
                                
                                <?php if ($order['phuong_thuc_thanh_toan'] == 'vnpay'): ?>
                                    <div class="mb-3">
                                        <?php 
                                        $payment_status_class = [
                                            'chua_thanh_toan' => 'warning',
                                            'da_thanh_toan' => 'success',
                                            'cho_thanh_toan' => 'info',
                                            'that_bai' => 'danger',
                                            'het_han' => 'secondary'
                                        ];
                                        $payment_status_text = [
                                            'chua_thanh_toan' => 'Chưa thanh toán',
                                            'da_thanh_toan' => 'Đã thanh toán',
                                            'cho_thanh_toan' => 'Chờ thanh toán',
                                            'that_bai' => 'Thanh toán thất bại',
                                            'het_han' => 'Hết hạn thanh toán'
                                        ];
                                        ?>
                                        <span class="badge bg-<?= $payment_status_class[$order['trang_thai_thanh_toan']] ?>">
                                            <?= $payment_status_text[$order['trang_thai_thanh_toan']] ?>
                                        </span>
                                    </div>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                    
                    <!-- Order Footer -->
                    <div class="order-footer">
                        <div class="d-flex justify-content-between align-items-center">
                            <div class="text-muted small">
                                <i class="fas fa-truck me-1"></i>
                                Giao đến: <?= htmlspecialchars($order['dia_chi_nhan']) ?>
                            </div>
                            <div class="d-flex gap-2">
                                <a href="/customer/order_detail.php?id=<?= $order['id'] ?>" class="btn btn-outline-primary btn-sm">
                                    <i class="fas fa-eye me-1"></i>Chi tiết
                                </a>
                                
                                <?php if ($order['phuong_thuc_thanh_toan'] == 'vnpay' && $order['trang_thai_thanh_toan'] == 'cho_thanh_toan'): ?>
                                    <a href="/vnpay/payment.php?order_id=<?= $order['id'] ?>" class="btn btn-warning btn-sm">
                                        <i class="fas fa-credit-card me-1"></i>Thanh toán
                                    </a>
                                <?php endif; ?>
                                
                                <?php if (in_array($order['trang_thai_don_hang'], ['cho_xac_nhan', 'da_xac_nhan'])): ?>
                                    <button class="btn btn-outline-danger btn-sm" onclick="cancelOrder(<?= $order['id'] ?>, '<?= $order['ma_don_hang'] ?>')">
                                        <i class="fas fa-times me-1"></i>Hủy đơn
                                    </button>
                                <?php endif; ?>
                                
                                <?php if ($order['trang_thai_don_hang'] == 'da_giao'): ?>
                                    <a href="/customer/review.php?order_id=<?= $order['id'] ?>" class="btn btn-success btn-sm">
                                        <i class="fas fa-star me-1"></i>Đánh giá
                                    </a>
                                    <a href="/customer/reorder.php?order_id=<?= $order['id'] ?>" class="btn btn-primary btn-sm">
                                        <i class="fas fa-redo me-1"></i>Mua lại
                                    </a>
                                <?php endif; ?>
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
                                <a class="page-link" href="?<?= http_build_query(array_merge($_GET, ['page' => $page - 1])) ?>">
                                    <i class="fas fa-chevron-left"></i>
                                </a>
                            </li>
                        <?php endif; ?>
                        
                        <?php
                        $start_page = max(1, $page - 2);
                        $end_page = min($total_pages, $page + 2);
                        
                        for ($i = $start_page; $i <= $end_page; $i++):
                        ?>
                            <li class="page-item <?= $i == $page ? 'active' : '' ?>">
                                <a class="page-link" href="?<?= http_build_query(array_merge($_GET, ['page' => $i])) ?>">
                                    <?= $i ?>
                                </a>
                            </li>
                        <?php endfor; ?>
                        
                        <?php if ($page < $total_pages): ?>
                            <li class="page-item">
                                <a class="page-link" href="?<?= http_build_query(array_merge($_GET, ['page' => $page + 1])) ?>">
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
        // Filter orders by status
        function filterOrders(status) {
            const url = new URL(window.location);
            if (status) {
                url.searchParams.set('status', status);
            } else {
                url.searchParams.delete('status');
            }
            url.searchParams.delete('page'); // Reset to first page
            window.location.href = url.toString();
        }
        
        // Cancel order
        function cancelOrder(orderId, orderCode) {
            if (confirm(`Bạn có chắc muốn hủy đơn hàng #${orderCode}?\n\nLưu ý: Đơn hàng đã hủy không thể hoàn tác.`)) {
                window.location.href = `?cancel_order=${orderId}`;
            }
        }
        
        // Auto refresh for pending orders
        function autoRefreshPendingOrders() {
            const hasPendingOrders = document.querySelector('.timeline-item.current');
            if (hasPendingOrders) {
                // Refresh every 30 seconds if there are pending orders
                setTimeout(() => {
                    window.location.reload();
                }, 30000);
            }
        }
        
        // Initialize
        document.addEventListener('DOMContentLoaded', function() {
            // Enable auto refresh for pending orders
            autoRefreshPendingOrders();
            
            // Add keyboard shortcuts
            document.addEventListener('keydown', function(e) {
                // Press '1' to filter all orders
                if (e.key === '1') {
                    filterOrders('');
                }
                // Press '2' to filter pending orders
                else if (e.key === '2') {
                    filterOrders('cho_xac_nhan');
                }
                // Press '3' to filter shipping orders
                else if (e.key === '3') {
                    filterOrders('dang_giao');
                }
                // Press '4' to filter completed orders
                else if (e.key === '4') {
                    filterOrders('da_giao');
                }
                // Press '5' to filter cancelled orders
                else if (e.key === '5') {
                    filterOrders('da_huy');
                }
            });
            
            // Add tooltips to status badges
            const tooltips = {
                'cho_xac_nhan': 'Đơn hàng đang chờ cửa hàng xác nhận',
                'da_xac_nhan': 'Đơn hàng đã được xác nhận và đang chuẩn bị',
                'dang_chuan_bi': 'Đơn hàng đang được đóng gói',
                'dang_giao': 'Đơn hàng đang được vận chuyển đến bạn',
                'da_giao': 'Đơn hàng đã được giao thành công',
                'da_huy': 'Đơn hàng đã bị hủy'
            };
            
            document.querySelectorAll('.status-badge').forEach(badge => {
                const status = badge.textContent.trim().toLowerCase();
                Object.keys(tooltips).forEach(key => {
                    if (badge.textContent.includes(tooltips[key].split(' ')[2])) {
                        badge.title = tooltips[key];
                    }
                });
            });
        });
        
        // Show order tracking modal (advanced feature)
        function showTrackingModal(orderId, trackingCode) {
            // TODO: Implement order tracking modal with real-time updates
            alert('Chức năng theo dõi chi tiết sẽ được phát triển trong phiên bản tiếp theo');
        }
        
        // Reorder functionality
        function reorder(orderId) {
            if (confirm('Bạn muốn mua lại các sản phẩm trong đơn hàng này?')) {
                // TODO: Add products to cart and redirect to checkout
                fetch(`/customer/api/reorder.php?order_id=${orderId}`, {
                    method: 'POST'
                })
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        alert('Đã thêm sản phẩm vào giỏ hàng!');
                        window.location.href = '/customer/cart.php';
                    } else {
                        alert(data.message || 'Có lỗi xảy ra');
                    }
                })
                .catch(error => {
                    alert('Có lỗi xảy ra khi thêm sản phẩm vào giỏ hàng');
                });
            }
        }
        
        // Print order
        function printOrder(orderId) {
            window.open(`/customer/print_order.php?id=${orderId}`, '_blank');
        }
    </script>
</body>
</html>