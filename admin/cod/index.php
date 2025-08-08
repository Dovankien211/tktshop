<?php
// admin/cod/index.php
/**
 * Quản lý COD - Dashboard tổng quan
 */

require_once '../../config/database.php';
require_once '../../config/config.php';

// Kiểm tra đăng nhập admin
if (!isset($_SESSION['admin_id'])) {
    redirect('/admin/login.php');
}

// Xử lý cập nhật trạng thái COD
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    $action = $_POST['action'];
    
    switch ($action) {
        case 'confirm_order':
            $order_id = (int)$_POST['order_id'];
            $confirmed_amount = (float)$_POST['confirmed_amount'];
            $admin_note = trim($_POST['admin_note'] ?? '');
            
            try {
                $pdo->prepare("
                    UPDATE don_hang SET 
                        trang_thai_don_hang = 'da_xac_nhan',
                        tong_thanh_toan = ?,
                        ghi_chu_admin = ?,
                        nguoi_xu_ly = ?,
                        ngay_xac_nhan = NOW()
                    WHERE id = ? AND phuong_thuc_thanh_toan = 'cod'
                ")->execute([$confirmed_amount, $admin_note, $_SESSION['admin_id'], $order_id]);
                
                alert('Đã xác nhận đơn hàng COD thành công', 'success');
            } catch (Exception $e) {
                alert('Có lỗi xảy ra: ' . $e->getMessage(), 'error');
            }
            break;
            
        case 'assign_shipper':
            $order_id = (int)$_POST['order_id'];
            $shipper_name = trim($_POST['shipper_name']);
            $shipper_phone = trim($_POST['shipper_phone']);
            $estimated_delivery = $_POST['estimated_delivery'];
            
            try {
                $pdo->prepare("
                    UPDATE don_hang SET 
                        trang_thai_don_hang = 'dang_giao',
                        ghi_chu_admin = CONCAT(COALESCE(ghi_chu_admin, ''), ?),
                        ngay_giao_hang = ?
                    WHERE id = ? AND phuong_thuc_thanh_toan = 'cod'
                ")->execute([
                    "\nShipper: {$shipper_name} - {$shipper_phone}",
                    $estimated_delivery,
                    $order_id
                ]);
                
                alert('Đã phân công shipper thành công', 'success');
            } catch (Exception $e) {
                alert('Có lỗi xảy ra: ' . $e->getMessage(), 'error');
            }
            break;
    }
    
    redirect('/admin/cod/index.php');
}

// Lấy thống kê COD
$stats = [
    'pending' => 0,
    'confirmed' => 0,
    'shipping' => 0,
    'completed' => 0,
    'cancelled' => 0,
    'total_amount' => 0,
    'today_orders' => 0
];

try {
    // Thống kê theo trạng thái
    $stmt = $pdo->query("
        SELECT 
            trang_thai_don_hang,
            COUNT(*) as count,
            SUM(tong_thanh_toan) as total
        FROM don_hang 
        WHERE phuong_thuc_thanh_toan = 'cod'
        GROUP BY trang_thai_don_hang
    ");
    
    while ($row = $stmt->fetch()) {
        $status = $row['trang_thai_don_hang'];
        $stats[$status] = $row['count'];
        $stats['total_amount'] += $row['total'];
    }
    
    // Đơn hàng hôm nay
    $stmt = $pdo->query("
        SELECT COUNT(*) as count 
        FROM don_hang 
        WHERE phuong_thuc_thanh_toan = 'cod' 
        AND DATE(ngay_dat_hang) = CURDATE()
    ");
    $stats['today_orders'] = $stmt->fetchColumn();
    
} catch (Exception $e) {
    error_log('COD Stats Error: ' . $e->getMessage());
}

// Lấy đơn hàng COD cần xử lý
$pending_orders = [];
try {
    $stmt = $pdo->prepare("
        SELECT dh.*, nd.ho_ten, nd.email, nd.so_dien_thoai,
               COUNT(ctdh.id) as item_count
        FROM don_hang dh
        LEFT JOIN nguoi_dung nd ON dh.khach_hang_id = nd.id
        LEFT JOIN chi_tiet_don_hang ctdh ON dh.id = ctdh.don_hang_id
        WHERE dh.phuong_thuc_thanh_toan = 'cod' 
        AND dh.trang_thai_don_hang IN ('cho_xac_nhan', 'da_xac_nhan', 'dang_chuan_bi')
        GROUP BY dh.id
        ORDER BY dh.ngay_dat_hang DESC
        LIMIT 20
    ");
    $stmt->execute();
    $pending_orders = $stmt->fetchAll();
} catch (Exception $e) {
    error_log('Pending Orders Error: ' . $e->getMessage());
}

$page_title = 'Quản lý COD';
?>

<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= $page_title ?> - Admin</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <style>
        .stat-card {
            background: white;
            border-radius: 10px;
            padding: 20px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
            border-left: 4px solid;
            margin-bottom: 20px;
        }
        
        .stat-card.pending { border-left-color: #ffc107; }
        .stat-card.confirmed { border-left-color: #17a2b8; }
        .stat-card.shipping { border-left-color: #fd7e14; }
        .stat-card.completed { border-left-color: #28a745; }
        .stat-card.cancelled { border-left-color: #dc3545; }
        
        .stat-number {
            font-size: 2rem;
            font-weight: bold;
            margin-bottom: 5px;
        }
        
        .order-card {
            background: white;
            border-radius: 8px;
            padding: 20px;
            margin-bottom: 15px;
            box-shadow: 0 2px 5px rgba(0,0,0,0.1);
            border-left: 4px solid #007bff;
        }
        
        .order-card.urgent {
            border-left-color: #dc3545;
            background: #fff5f5;
        }
        
        .action-buttons {
            display: flex;
            gap: 10px;
            flex-wrap: wrap;
        }
        
        .quick-action {
            background: #f8f9fa;
            border: 1px solid #dee2e6;
            border-radius: 6px;
            padding: 8px 12px;
            font-size: 0.875rem;
            cursor: pointer;
            transition: all 0.3s;
        }
        
        .quick-action:hover {
            background: #e9ecef;
            transform: translateY(-1px);
        }
        
        .status-badge {
            padding: 6px 12px;
            border-radius: 15px;
            font-size: 0.8rem;
            font-weight: bold;
        }
    </style>
</head>
<body>
    <div class="container-fluid">
        <div class="row">
            <!-- Sidebar -->
            <div class="col-md-2 p-0">
                <?php include '../layouts/sidebar.php'; ?>
            </div>
            
            <!-- Main Content -->
            <div class="col-md-10">
                <div class="p-4">
                    <!-- Header -->
                    <div class="d-flex justify-content-between align-items-center mb-4">
                        <div>
                            <h2><i class="fas fa-money-bill-wave me-2"></i>Quản lý COD</h2>
                            <p class="text-muted">Quản lý đơn hàng thanh toán khi nhận hàng</p>
                        </div>
                        <div>
                            <a href="/admin/orders/index.php" class="btn btn-outline-primary">
                                <i class="fas fa-list me-1"></i>Tất cả đơn hàng
                            </a>
                            <a href="/admin/cod/reports.php" class="btn btn-primary">
                                <i class="fas fa-chart-bar me-1"></i>Báo cáo COD
                            </a>
                        </div>
                    </div>
                    
                    <!-- Statistics Cards -->
                    <div class="row mb-4">
                        <div class="col-md-2">
                            <div class="stat-card pending">
                                <div class="stat-number text-warning"><?= $stats['cho_xac_nhan'] ?? 0 ?></div>
                                <div class="text-muted">Chờ xác nhận</div>
                            </div>
                        </div>
                        <div class="col-md-2">
                            <div class="stat-card confirmed">
                                <div class="stat-number text-info"><?= $stats['da_xac_nhan'] ?? 0 ?></div>
                                <div class="text-muted">Đã xác nhận</div>
                            </div>
                        </div>
                        <div class="col-md-2">
                            <div class="stat-card shipping">
                                <div class="stat-number text-warning"><?= $stats['dang_giao'] ?? 0 ?></div>
                                <div class="text-muted">Đang giao</div>
                            </div>
                        </div>
                        <div class="col-md-2">
                            <div class="stat-card completed">
                                <div class="stat-number text-success"><?= $stats['da_giao'] ?? 0 ?></div>
                                <div class="text-muted">Hoàn thành</div>
                            </div>
                        </div>
                        <div class="col-md-2">
                            <div class="stat-card cancelled">
                                <div class="stat-number text-danger"><?= $stats['da_huy'] ?? 0 ?></div>
                                <div class="text-muted">Đã hủy</div>
                            </div>
                        </div>
                        <div class="col-md-2">
                            <div class="stat-card">
                                <div class="stat-number text-primary"><?= $stats['today_orders'] ?></div>
                                <div class="text-muted">Đơn hôm nay</div>
                            </div>
                        </div>
                    </div>
                    
                    <!-- Total Revenue -->
                    <div class="row mb-4">
                        <div class="col-md-12">
                            <div class="alert alert-info">
                                <i class="fas fa-coins me-2"></i>
                                <strong>Tổng giá trị COD:</strong> 
                                <?= formatPrice($stats['total_amount']) ?>
                            </div>
                        </div>
                    </div>
                    
                    <!-- Pending Orders -->
                    <div class="card">
                        <div class="card-header">
                            <h5 class="mb-0">
                                <i class="fas fa-clock me-2"></i>
                                Đơn hàng COD cần xử lý
                            </h5>
                        </div>
                        <div class="card-body">
                            <?php if (empty($pending_orders)): ?>
                                <div class="text-center py-4">
                                    <i class="fas fa-check-circle fa-3x text-success mb-3"></i>
                                    <h5>Không có đơn hàng cần xử lý</h5>
                                    <p class="text-muted">Tất cả đơn hàng COD đã được xử lý</p>
                                </div>
                            <?php else: ?>
                                <?php foreach ($pending_orders as $order): ?>
                                    <?php 
                                    $is_urgent = (time() - strtotime($order['ngay_dat_hang'])) > 7200; // 2 hours
                                    $status_class = [
                                        'cho_xac_nhan' => 'warning',
                                        'da_xac_nhan' => 'info', 
                                        'dang_chuan_bi' => 'primary',
                                        'dang_giao' => 'warning',
                                        'da_giao' => 'success'
                                    ][$order['trang_thai_don_hang']] ?? 'secondary';
                                    ?>
                                    
                                    <div class="order-card <?= $is_urgent ? 'urgent' : '' ?>">
                                        <div class="row align-items-center">
                                            <div class="col-md-3">
                                                <h6 class="mb-1">
                                                    <i class="fas fa-receipt me-2"></i>
                                                    #<?= $order['ma_don_hang'] ?>
                                                    <?php if ($is_urgent): ?>
                                                        <span class="badge bg-danger ms-2">URGENT</span>
                                                    <?php endif; ?>
                                                </h6>
                                                <div class="text-muted small">
                                                    <?= date('d/m/Y H:i', strtotime($order['ngay_dat_hang'])) ?>
                                                </div>
                                                <span class="status-badge bg-<?= $status_class ?> text-white">
                                                    <?= ucfirst(str_replace('_', ' ', $order['trang_thai_don_hang'])) ?>
                                                </span>
                                            </div>
                                            
                                            <div class="col-md-3">
                                                <strong><?= htmlspecialchars($order['ho_ten_nhan']) ?></strong>
                                                <div class="text-muted small">
                                                    <i class="fas fa-phone me-1"></i>
                                                    <?= htmlspecialchars($order['so_dien_thoai_nhan']) ?>
                                                </div>
                                                <div class="text-muted small">
                                                    <i class="fas fa-box me-1"></i>
                                                    <?= $order['item_count'] ?> sản phẩm
                                                </div>
                                            </div>
                                            
                                            <div class="col-md-2 text-center">
                                                <div class="fw-bold text-primary">
                                                    <?= formatPrice($order['tong_thanh_toan']) ?>
                                                </div>
                                                <div class="text-muted small">
                                                    (<?= formatPrice($order['phi_van_chuyen']) ?> ship)
                                                </div>
                                            </div>
                                            
                                            <div class="col-md-4">
                                                <div class="action-buttons">
                                                    <a href="/admin/orders/detail.php?id=<?= $order['id'] ?>" 
                                                       class="quick-action text-decoration-none">
                                                        <i class="fas fa-eye me-1"></i>Chi tiết
                                                    </a>
                                                    
                                                    <?php if ($order['trang_thai_don_hang'] === 'cho_xac_nhan'): ?>
                                                        <button class="quick-action border-0" 
                                                                onclick="confirmOrder(<?= $order['id'] ?>, '<?= $order['ma_don_hang'] ?>', <?= $order['tong_thanh_toan'] ?>)">
                                                            <i class="fas fa-check me-1"></i>Xác nhận
                                                        </button>
                                                    <?php endif; ?>
                                                    
                                                    <?php if ($order['trang_thai_don_hang'] === 'da_xac_nhan'): ?>
                                                        <button class="quick-action border-0" 
                                                                onclick="assignShipper(<?= $order['id'] ?>, '<?= $order['ma_don_hang'] ?>')">
                                                            <i class="fas fa-truck me-1"></i>Giao hàng
                                                        </button>
                                                    <?php endif; ?>
                                                    
                                                    <a href="tel:<?= $order['so_dien_thoai_nhan'] ?>" 
                                                       class="quick-action text-decoration-none text-success">
                                                        <i class="fas fa-phone me-1"></i>Gọi
                                                    </a>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <!-- Confirm Order Modal -->
    <div class="modal fade" id="confirmOrderModal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <form method="POST">
                    <div class="modal-header">
                        <h5 class="modal-title">Xác nhận đơn hàng COD</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                    </div>
                    <div class="modal-body">
                        <input type="hidden" name="action" value="confirm_order">
                        <input type="hidden" name="order_id" id="confirm_order_id">
                        
                        <div class="mb-3">
                            <label class="form-label">Mã đơn hàng:</label>
                            <input type="text" class="form-control" id="confirm_order_code" readonly>
                        </div>
                        
                        <div class="mb-3">
                            <label class="form-label">Số tiền xác nhận:</label>
                            <input type="number" class="form-control" name="confirmed_amount" id="confirm_amount" required>
                            <div class="form-text">Kiểm tra lại số tiền với khách hàng qua điện thoại</div>
                        </div>
                        
                        <div class="mb-3">
                            <label class="form-label">Ghi chú admin:</label>
                            <textarea class="form-control" name="admin_note" rows="3" 
                                      placeholder="Ghi chú kết quả cuộc gọi xác nhận..."></textarea>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Hủy</button>
                        <button type="submit" class="btn btn-success">
                            <i class="fas fa-check me-1"></i>Xác nhận đơn hàng
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>
    
    <!-- Assign Shipper Modal -->
    <div class="modal fade" id="assignShipperModal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <form method="POST">
                    <div class="modal-header">
                        <h5 class="modal-title">Phân công giao hàng</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                    </div>
                    <div class="modal-body">
                        <input type="hidden" name="action" value="assign_shipper">
                        <input type="hidden" name="order_id" id="assign_order_id">
                        
                        <div class="mb-3">
                            <label class="form-label">Mã đơn hàng:</label>
                            <input type="text" class="form-control" id="assign_order_code" readonly>
                        </div>
                        
                        <div class="row">
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label class="form-label">Tên shipper:</label>
                                    <input type="text" class="form-control" name="shipper_name" required>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label class="form-label">SĐT shipper:</label>
                                    <input type="tel" class="form-control" name="shipper_phone" required>
                                </div>
                            </div>
                        </div>
                        
                        <div class="mb-3">
                            <label class="form-label">Dự kiến giao hàng:</label>
                            <input type="datetime-local" class="form-control" name="estimated_delivery" 
                                   value="<?= date('Y-m-d\TH:i', strtotime('+1 day')) ?>" required>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Hủy</button>
                        <button type="submit" class="btn btn-primary">
                            <i class="fas fa-truck me-1"></i>Phân công giao hàng
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>
    
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
    
    <script>
        function confirmOrder(orderId, orderCode, amount) {
            document.getElementById('confirm_order_id').value = orderId;
            document.getElementById('confirm_order_code').value = orderCode;
            document.getElementById('confirm_amount').value = amount;
            
            new bootstrap.Modal(document.getElementById('confirmOrderModal')).show();
        }
        
        function assignShipper(orderId, orderCode) {
            document.getElementById('assign_order_id').value = orderId;
            document.getElementById('assign_order_code').value = orderCode;
            
            new bootstrap.Modal(document.getElementById('assignShipperModal')).show();
        }
        
        // Auto refresh every 30 seconds
        setTimeout(function() {
            location.reload();
        }, 30000);
    </script>
</body>
</html>