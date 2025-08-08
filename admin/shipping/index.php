<?php
// admin/shipping/index.php
/**
 * Quản lý vận chuyển - COD Shipping Management
 */

require_once '../../config/database.php';
require_once '../../config/config.php';

// Kiểm tra đăng nhập admin
if (!isset($_SESSION['admin_id'])) {
    redirect('/admin/login.php');
}

// Xử lý cập nhật trạng thái vận chuyển
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    $action = $_POST['action'];
    
    switch ($action) {
        case 'update_shipping_status':
            $order_id = (int)$_POST['order_id'];
            $status = $_POST['status'];
            $note = trim($_POST['note'] ?? '');
            
            try {
                $pdo->beginTransaction();
                
                // Cập nhật trạng thái đơn hàng
                $pdo->prepare("
                    UPDATE don_hang SET 
                        trang_thai_don_hang = ?,
                        ghi_chu_admin = CONCAT(COALESCE(ghi_chu_admin, ''), ?),
                        nguoi_xu_ly = ?,
                        ngay_cap_nhat = NOW()
                    WHERE id = ?
                ")->execute([$status, "\n[" . date('Y-m-d H:i:s') . "] " . $note, $_SESSION['admin_id'], $order_id]);
                
                // Nếu giao thành công (COD), cập nhật trạng thái thanh toán
                if ($status === 'da_giao') {
                    $pdo->prepare("
                        UPDATE don_hang SET 
                            trang_thai_thanh_toan = 'da_thanh_toan',
                            ngay_hoan_thanh = NOW()
                        WHERE id = ? AND phuong_thuc_thanh_toan = 'cod'
                    ")->execute([$order_id]);
                }
                
                $pdo->commit();
                alert('Cập nhật trạng thái vận chuyển thành công', 'success');
                
            } catch (Exception $e) {
                $pdo->rollback();
                alert('Có lỗi xảy ra: ' . $e->getMessage(), 'error');
            }
            break;
            
        case 'mark_delivery_failed':
            $order_id = (int)$_POST['order_id'];
            $fail_reason = trim($_POST['fail_reason']);
            $retry_date = $_POST['retry_date'];
            
            try {
                $pdo->prepare("
                    UPDATE don_hang SET 
                        trang_thai_don_hang = 'dang_giao',
                        ghi_chu_admin = CONCAT(COALESCE(ghi_chu_admin, ''), ?),
                        ngay_cap_nhat = NOW()
                    WHERE id = ?
                ")->execute([
                    "\n[THẤT BẠI] " . date('Y-m-d H:i:s') . " - " . $fail_reason . " - Thử lại: " . $retry_date,
                    $order_id
                ]);
                
                alert('Đã ghi nhận giao hàng thất bại', 'warning');
                
            } catch (Exception $e) {
                alert('Có lỗi xảy ra: ' . $e->getMessage(), 'error');
            }
            break;
    }
    
    redirect('/admin/shipping/index.php');
}

// Lấy danh sách đơn hàng đang vận chuyển
$filter_status = $_GET['status'] ?? 'all';
$filter_date = $_GET['date'] ?? '';
$search = trim($_GET['search'] ?? '');

$where_conditions = ["dh.phuong_thuc_thanh_toan = 'cod'"];
$params = [];

if ($filter_status !== 'all') {
    $where_conditions[] = "dh.trang_thai_don_hang = ?";
    $params[] = $filter_status;
}

if ($filter_date) {
    $where_conditions[] = "DATE(dh.ngay_dat_hang) = ?";
    $params[] = $filter_date;
}

if ($search) {
    $where_conditions[] = "(dh.ma_don_hang LIKE ? OR dh.ho_ten_nhan LIKE ? OR dh.so_dien_thoai_nhan LIKE ?)";
    $params[] = "%{$search}%";
    $params[] = "%{$search}%";
    $params[] = "%{$search}%";
}

$where_clause = implode(' AND ', $where_conditions);

try {
    $stmt = $pdo->prepare("
        SELECT dh.*, nd.ho_ten, nd.email, nd.so_dien_thoai,
               COUNT(ctdh.id) as item_count,
               SUM(ctdh.so_luong) as total_quantity
        FROM don_hang dh
        LEFT JOIN nguoi_dung nd ON dh.khach_hang_id = nd.id
        LEFT JOIN chi_tiet_don_hang ctdh ON dh.id = ctdh.don_hang_id
        WHERE {$where_clause}
        GROUP BY dh.id
        ORDER BY 
            CASE dh.trang_thai_don_hang
                WHEN 'dang_giao' THEN 1
                WHEN 'da_xac_nhan' THEN 2
                WHEN 'dang_chuan_bi' THEN 3
                ELSE 4
            END,
            dh.ngay_dat_hang DESC
        LIMIT 50
    ");
    $stmt->execute($params);
    $orders = $stmt->fetchAll();
    
} catch (Exception $e) {
    $orders = [];
    error_log('Shipping Orders Error: ' . $e->getMessage());
}

// Thống kê nhanh
$shipping_stats = [];
try {
    $stmt = $pdo->query("
        SELECT 
            trang_thai_don_hang,
            COUNT(*) as count
        FROM don_hang 
        WHERE phuong_thuc_thanh_toan = 'cod'
        AND trang_thai_don_hang IN ('da_xac_nhan', 'dang_chuan_bi', 'dang_giao', 'da_giao')
        GROUP BY trang_thai_don_hang
    ");
    
    while ($row = $stmt->fetch()) {
        $shipping_stats[$row['trang_thai_don_hang']] = $row['count'];
    }
    
} catch (Exception $e) {
    error_log('Shipping Stats Error: ' . $e->getMessage());
}

$page_title = 'Quản lý vận chuyển COD';
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
        .shipping-card {
            background: white;
            border-radius: 10px;
            padding: 20px;
            margin-bottom: 15px;
            box-shadow: 0 2px 8px rgba(0,0,0,0.1);
            border-left: 4px solid;
            transition: all 0.3s ease;
        }
        
        .shipping-card:hover {
            transform: translateY(-2px);
            box-shadow: 0 4px 12px rgba(0,0,0,0.15);
        }
        
        .shipping-card.ready { border-left-color: #17a2b8; }
        .shipping-card.preparing { border-left-color: #ffc107; }
        .shipping-card.shipping { border-left-color: #fd7e14; }
        .shipping-card.delivered { border-left-color: #28a745; }
        .shipping-card.urgent { 
            border-left-color: #dc3545; 
            background: linear-gradient(135deg, #fff 0%, #fff5f5 100%);
        }
        
        .status-timeline {
            display: flex;
            justify-content: space-between;
            margin: 15px 0;
            position: relative;
        }
        
        .status-timeline::before {
            content: '';
            position: absolute;
            top: 15px;
            left: 0;
            right: 0;
            height: 2px;
            background: #dee2e6;
            z-index: 1;
        }
        
        .timeline-step {
            background: white;
            border: 3px solid #dee2e6;
            border-radius: 50%;
            width: 30px;
            height: 30px;
            display: flex;
            align-items: center;
            justify-content: center;
            position: relative;
            z-index: 2;
            font-size: 12px;
        }
        
        .timeline-step.completed {
            background: #28a745;
            border-color: #28a745;
            color: white;
        }
        
        .timeline-step.active {
            background: #007bff;
            border-color: #007bff;
            color: white;
        }
        
        .quick-actions {
            display: flex;
            gap: 8px;
            flex-wrap: wrap;
        }
        
        .quick-action {
            padding: 6px 12px;
            border: 1px solid #dee2e6;
            border-radius: 4px;
            background: white;
            color: #333;
            text-decoration: none;
            font-size: 0.875rem;
            transition: all 0.3s;
        }
        
        .quick-action:hover {
            background: #f8f9fa;
            transform: translateY(-1px);
        }
        
        .quick-action.primary { background: #007bff; color: white; border-color: #007bff; }
        .quick-action.success { background: #28a745; color: white; border-color: #28a745; }
        .quick-action.warning { background: #ffc107; color: #212529; border-color: #ffc107; }
        .quick-action.danger { background: #dc3545; color: white; border-color: #dc3545; }
        
        .filter-bar {
            background: white;
            padding: 20px;
            border-radius: 10px;
            margin-bottom: 20px;
            box-shadow: 0 2px 5px rgba(0,0,0,0.1);
        }
        
        .stats-row {
            background: white;
            padding: 15px;
            border-radius: 10px;
            margin-bottom: 20px;
            box-shadow: 0 2px 5px rgba(0,0,0,0.1);
        }
        
        .stat-item {
            text-align: center;
            padding: 10px;
        }
        
        .stat-number {
            font-size: 1.5rem;
            font-weight: bold;
            margin-bottom: 5px;
        }
        
        .delivery-info {
            background: #f8f9fa;
            padding: 12px;
            border-radius: 6px;
            margin-top: 10px;
            font-size: 0.875rem;
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
                            <h2><i class="fas fa-shipping-fast me-2"></i><?= $page_title ?></h2>
                            <p class="text-muted">Theo dõi và quản lý vận chuyển đơn hàng COD</p>
                        </div>
                        <div>
                            <a href="/admin/cod/index.php" class="btn btn-outline-primary">
                                <i class="fas fa-money-bill-wave me-1"></i>Quản lý COD
                            </a>
                            <a href="/admin/orders/index.php" class="btn btn-primary">
                                <i class="fas fa-list me-1"></i>Tất cả đơn hàng
                            </a>
                        </div>
                    </div>
                    
                    <!-- Quick Stats -->
                    <div class="stats-row">
                        <div class="row">
                            <div class="col-md-3">
                                <div class="stat-item">
                                    <div class="stat-number text-info"><?= $shipping_stats['da_xac_nhan'] ?? 0 ?></div>
                                    <div class="text-muted">Sẵn sàng giao</div>
                                </div>
                            </div>
                            <div class="col-md-3">
                                <div class="stat-item">
                                    <div class="stat-number text-warning"><?= $shipping_stats['dang_chuan_bi'] ?? 0 ?></div>
                                    <div class="text-muted">Đang chuẩn bị</div>
                                </div>
                            </div>
                            <div class="col-md-3">
                                <div class="stat-item">
                                    <div class="stat-number text-primary"><?= $shipping_stats['dang_giao'] ?? 0 ?></div>
                                    <div class="text-muted">Đang giao</div>
                                </div>
                            </div>
                            <div class="col-md-3">
                                <div class="stat-item">
                                    <div class="stat-number text-success"><?= $shipping_stats['da_giao'] ?? 0 ?></div>
                                    <div class="text-muted">Đã giao</div>
                                </div>
                            </div>
                        </div>
                    </div>
                    
                    <!-- Filter Bar -->
                    <div class="filter-bar">
                        <form method="GET" class="row g-3">
                            <div class="col-md-3">
                                <label class="form-label">Trạng thái:</label>
                                <select name="status" class="form-select">
                                    <option value="all" <?= $filter_status === 'all' ? 'selected' : '' ?>>Tất cả</option>
                                    <option value="da_xac_nhan" <?= $filter_status === 'da_xac_nhan' ? 'selected' : '' ?>>Sẵn sàng giao</option>
                                    <option value="dang_chuan_bi" <?= $filter_status === 'dang_chuan_bi' ? 'selected' : '' ?>>Đang chuẩn bị</option>
                                    <option value="dang_giao" <?= $filter_status === 'dang_giao' ? 'selected' : '' ?>>Đang giao</option>
                                    <option value="da_giao" <?= $filter_status === 'da_giao' ? 'selected' : '' ?>>Đã giao</option>
                                </select>
                            </div>
                            <div class="col-md-3">
                                <label class="form-label">Ngày:</label>
                                <input type="date" name="date" class="form-control" value="<?= htmlspecialchars($filter_date) ?>">
                            </div>
                            <div class="col-md-4">
                                <label class="form-label">Tìm kiếm:</label>
                                <input type="text" name="search" class="form-control" 
                                       placeholder="Mã đơn hàng, tên, SĐT..." value="<?= htmlspecialchars($search) ?>">
                            </div>
                            <div class="col-md-2">
                                <label class="form-label">&nbsp;</label>
                                <button type="submit" class="btn btn-primary d-block w-100">
                                    <i class="fas fa-search me-1"></i>Lọc
                                </button>
                            </div>
                        </form>
                    </div>
                    
                    <!-- Shipping Orders -->
                    <div class="shipping-orders">
                        <?php if (empty($orders)): ?>
                            <div class="text-center py-5">
                                <i class="fas fa-truck fa-3x text-muted mb-3"></i>
                                <h5>Không có đơn hàng nào</h5>
                                <p class="text-muted">Thay đổi bộ lọc để xem các đơn hàng khác</p>
                            </div>
                        <?php else: ?>
                            <?php foreach ($orders as $order): ?>
                                <?php 
                                $is_urgent = (time() - strtotime($order['ngay_dat_hang'])) > 86400; // 24 hours
                                $card_class = [
                                    'da_xac_nhan' => 'ready',
                                    'dang_chuan_bi' => 'preparing',
                                    'dang_giao' => 'shipping',
                                    'da_giao' => 'delivered'
                                ][$order['trang_thai_don_hang']] ?? '';
                                
                                if ($is_urgent && $order['trang_thai_don_hang'] !== 'da_giao') {
                                    $card_class = 'urgent';
                                }
                                ?>
                                
                                <div class="shipping-card <?= $card_class ?>">
                                    <div class="row">
                                        <div class="col-md-8">
                                            <!-- Order Header -->
                                            <div class="d-flex justify-content-between align-items-start mb-3">
                                                <div>
                                                    <h6 class="mb-1">
                                                        <i class="fas fa-receipt me-2"></i>
                                                        #<?= $order['ma_don_hang'] ?>
                                                        <?php if ($is_urgent && $order['trang_thai_don_hang'] !== 'da_giao'): ?>
                                                            <span class="badge bg-danger ms-2">URGENT</span>
                                                        <?php endif; ?>
                                                    </h6>
                                                    <div class="text-muted small">
                                                        Đặt: <?= date('d/m/Y H:i', strtotime($order['ngay_dat_hang'])) ?>
                                                        <?php if ($order['ngay_xac_nhan']): ?>
                                                            | Xác nhận: <?= date('d/m/Y H:i', strtotime($order['ngay_xac_nhan'])) ?>
                                                        <?php endif; ?>
                                                    </div>
                                                </div>
                                                <div class="text-end">
                                                    <div class="fw-bold text-primary"><?= formatPrice($order['tong_thanh_toan']) ?></div>
                                                    <div class="text-muted small"><?= $order['item_count'] ?> sản phẩm</div>
                                                </div>
                                            </div>
                                            
                                            <!-- Customer Info -->
                                            <div class="row mb-3">
                                                <div class="col-md-6">
                                                    <strong><i class="fas fa-user me-2"></i><?= htmlspecialchars($order['ho_ten_nhan']) ?></strong>
                                                    <div class="text-muted small">
                                                        <i class="fas fa-phone me-1"></i><?= htmlspecialchars($order['so_dien_thoai_nhan']) ?>
                                                    </div>
                                                </div>
                                                <div class="col-md-6">
                                                    <div class="text-muted small">
                                                        <i class="fas fa-map-marker-alt me-1"></i>
                                                        <?= htmlspecialchars(substr($order['dia_chi_nhan'], 0, 100)) ?>
                                                        <?= strlen($order['dia_chi_nhan']) > 100 ? '...' : '' ?>
                                                    </div>
                                                </div>
                                            </div>
                                            
                                            <!-- Status Timeline -->
                                            <div class="status-timeline">
                                                <div class="timeline-step <?= in_array($order['trang_thai_don_hang'], ['da_xac_nhan', 'dang_chuan_bi', 'dang_giao', 'da_giao']) ? 'completed' : '' ?>">
                                                    <i class="fas fa-check"></i>
                                                </div>
                                                <div class="timeline-step <?= in_array($order['trang_thai_don_hang'], ['dang_chuan_bi', 'dang_giao', 'da_giao']) ? 'completed' : ($order['trang_thai_don_hang'] === 'da_xac_nhan' ? 'active' : '') ?>">
                                                    <i class="fas fa-box"></i>
                                                </div>
                                                <div class="timeline-step <?= in_array($order['trang_thai_don_hang'], ['dang_giao', 'da_giao']) ? 'completed' : ($order['trang_thai_don_hang'] === 'dang_chuan_bi' ? 'active' : '') ?>">
                                                    <i class="fas fa-truck"></i>
                                                </div>
                                                <div class="timeline-step <?= $order['trang_thai_don_hang'] === 'da_giao' ? 'completed' : ($order['trang_thai_don_hang'] === 'dang_giao' ? 'active' : '') ?>">
                                                    <i class="fas fa-home"></i>
                                                </div>
                                            </div>
                                            
                                            <div class="row text-center small text-muted">
                                                <div class="col-3">Xác nhận</div>
                                                <div class="col-3">Chuẩn bị</div>
                                                <div class="col-3">Đang giao</div>
                                                <div class="col-3">Hoàn thành</div>
                                            </div>
                                            
                                            <!-- Delivery Info -->
                                            <?php if ($order['ghi_chu_admin']): ?>
                                                <div class="delivery-info">
                                                    <strong>Ghi chú vận chuyển:</strong>
                                                    <div><?= nl2br(htmlspecialchars($order['ghi_chu_admin'])) ?></div>
                                                </div>
                                            <?php endif; ?>
                                        </div>
                                        
                                        <!-- Actions -->
                                        <div class="col-md-4">
                                            <div class="quick-actions">
                                                <a href="/admin/orders/detail.php?id=<?= $order['id'] ?>" 
                                                   class="quick-action">
                                                    <i class="fas fa-eye me-1"></i>Chi tiết
                                                </a>
                                                
                                                <a href="tel:<?= $order['so_dien_thoai_nhan'] ?>" 
                                                   class="quick-action success">
                                                    <i class="fas fa-phone me-1"></i>Gọi KH
                                                </a>
                                                
                                                <?php if ($order['trang_thai_don_hang'] === 'da_xac_nhan'): ?>
                                                    <button class="quick-action primary border-0" 
                                                            onclick="updateStatus(<?= $order['id'] ?>, 'dang_chuan_bi', 'Bắt đầu chuẩn bị hàng')">
                                                        <i class="fas fa-box me-1"></i>Chuẩn bị
                                                    </button>
                                                <?php endif; ?>
                                                
                                                <?php if ($order['trang_thai_don_hang'] === 'dang_chuan_bi'): ?>
                                                    <button class="quick-action warning border-0" 
                                                            onclick="updateStatus(<?= $order['id'] ?>, 'dang_giao', 'Bắt đầu giao hàng')">
                                                        <i class="fas fa-truck me-1"></i>Giao hàng
                                                    </button>
                                                <?php endif; ?>
                                                
                                                <?php if ($order['trang_thai_don_hang'] === 'dang_giao'): ?>
                                                    <button class="quick-action success border-0" 
                                                            onclick="updateStatus(<?= $order['id'] ?>, 'da_giao', 'Giao hàng thành công - COD đã thu')">
                                                        <i class="fas fa-check me-1"></i>Hoàn thành
                                                    </button>
                                                    
                                                    <button class="quick-action danger border-0" 
                                                            onclick="markDeliveryFailed(<?= $order['id'] ?>)">
                                                        <i class="fas fa-times me-1"></i>Thất bại
                                                    </button>
                                                <?php endif; ?>
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
    
    <!-- Update Status Modal -->
    <div class="modal fade" id="updateStatusModal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <form method="POST">
                    <div class="modal-header">
                        <h5 class="modal-title">Cập nhật trạng thái vận chuyển</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                    </div>
                    <div class="modal-body">
                        <input type="hidden" name="action" value="update_shipping_status">
                        <input type="hidden" name="order_id" id="update_order_id">
                        <input type="hidden" name="status" id="update_status">
                        
                        <div class="mb-3">
                            <label class="form-label">Trạng thái mới:</label>
                            <input type="text" class="form-control" id="update_status_text" readonly>
                        </div>
                        
                        <div class="mb-3">
                            <label class="form-label">Ghi chú:</label>
                            <textarea class="form-control" name="note" id="update_note" rows="3" required></textarea>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Hủy</button>
                        <button type="submit" class="btn btn-primary">Cập nhật</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
    
    <!-- Delivery Failed Modal -->
    <div class="modal fade" id="deliveryFailedModal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <form method="POST">
                    <div class="modal-header">
                        <h5 class="modal-title">Giao hàng thất bại</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                    </div>
                    <div class="modal-body">
                        <input type="hidden" name="action" value="mark_delivery_failed">
                        <input type="hidden" name="order_id" id="failed_order_id">
                        
                        <div class="mb-3">
                            <label class="form-label">Lý do thất bại:</label>
                            <select class="form-select" name="fail_reason" required>
                                <option value="">Chọn lý do...</option>
                                <option value="Khách không có nhà">Khách không có nhà</option>
                                <option value="Khách từ chối nhận">Khách từ chối nhận</option>
                                <option value="Không liên lạc được">Không liên lạc được</option>
                                <option value="Địa chỉ không đúng">Địa chỉ không đúng</option>
                                <option value="Khác">Lý do khác</option>
                            </select>
                        </div>
                        
                        <div class="mb-3">
                            <label class="form-label">Thời gian thử lại:</label>
                            <input type="datetime-local" class="form-control" name="retry_date" 
                                   value="<?= date('Y-m-d\TH:i', strtotime('+1 day')) ?>" required>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Hủy</button>
                        <button type="submit" class="btn btn-warning">Ghi nhận thất bại</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
    
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
    
    <script>
        function updateStatus(orderId, status, note) {
            document.getElementById('update_order_id').value = orderId;
            document.getElementById('update_status').value = status;
            document.getElementById('update_note').value = note;
            
            const statusTexts = {
                'dang_chuan_bi': 'Đang chuẩn bị',
                'dang_giao': 'Đang giao hàng',
                'da_giao': 'Đã giao hàng'
            };
            
            document.getElementById('update_status_text').value = statusTexts[status] || status;
            
            new bootstrap.Modal(document.getElementById('updateStatusModal')).show();
        }
        
        function markDeliveryFailed(orderId) {
            document.getElementById('failed_order_id').value = orderId;
            new bootstrap.Modal(document.getElementById('deliveryFailedModal')).show();
        }
        
        // Auto refresh every 60 seconds
        setTimeout(function() {
            location.reload();
        }, 60000);
    </script>
</body>
</html>