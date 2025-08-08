<?php
// admin/shipping/shippers.php
/**
 * Quản lý Shipper - COD Delivery Management
 */

require_once '../../config/database.php';
require_once '../../config/config.php';

// Kiểm tra đăng nhập admin
if (!isset($_SESSION['admin_id'])) {
    redirect('/admin/login.php');
}

// Xử lý CRUD Shipper
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    $action = $_POST['action'];
    
    switch ($action) {
        case 'add_shipper':
            $name = trim($_POST['name']);
            $phone = trim($_POST['phone']);
            $area = trim($_POST['area']);
            $vehicle_type = $_POST['vehicle_type'];
            $max_orders = (int)$_POST['max_orders'];
            
            try {
                $pdo->prepare("
                    INSERT INTO shippers (ten_shipper, so_dien_thoai, khu_vuc, loai_xe, 
                                        don_toi_da_ngay, trang_thai, nguoi_tao, ngay_tao)
                    VALUES (?, ?, ?, ?, ?, 'hoat_dong', ?, NOW())
                ")->execute([$name, $phone, $area, $vehicle_type, $max_orders, $_SESSION['admin_id']]);
                
                alert('Thêm shipper thành công', 'success');
            } catch (Exception $e) {
                alert('Có lỗi xảy ra: ' . $e->getMessage(), 'error');
            }
            break;
            
        case 'assign_orders':
            $shipper_id = (int)$_POST['shipper_id'];
            $order_ids = $_POST['order_ids'] ?? [];
            $delivery_date = $_POST['delivery_date'];
            
            if (empty($order_ids)) {
                alert('Vui lòng chọn ít nhất một đơn hàng', 'warning');
                break;
            }
            
            try {
                $pdo->beginTransaction();
                
                // Cập nhật đơn hàng
                $order_ids_str = implode(',', array_map('intval', $order_ids));
                $pdo->prepare("
                    UPDATE don_hang SET 
                        trang_thai_don_hang = 'dang_giao',
                        shipper_id = ?,
                        ngay_giao_hang = ?,
                        ghi_chu_admin = CONCAT(COALESCE(ghi_chu_admin, ''), ?)
                    WHERE id IN ({$order_ids_str}) AND phuong_thuc_thanh_toan = 'cod'
                ")->execute([
                    $shipper_id, 
                    $delivery_date,
                    "\n[GIAO HÀNG] Đã phân công shipper - " . date('Y-m-d H:i:s')
                ]);
                
                // Tạo lịch trình giao hàng
                foreach ($order_ids as $order_id) {
                    $pdo->prepare("
                        INSERT INTO lich_trinh_giao_hang (shipper_id, don_hang_id, ngay_giao, trang_thai)
                        VALUES (?, ?, ?, 'da_phan_cong')
                    ")->execute([$shipper_id, $order_id, $delivery_date]);
                }
                
                $pdo->commit();
                alert('Phân công giao hàng thành công cho ' . count($order_ids) . ' đơn hàng', 'success');
                
            } catch (Exception $e) {
                $pdo->rollback();
                alert('Có lỗi xảy ra: ' . $e->getMessage(), 'error');
            }
            break;
            
        case 'update_shipper_status':
            $shipper_id = (int)$_POST['shipper_id'];
            $status = $_POST['status'];
            
            try {
                $pdo->prepare("UPDATE shippers SET trang_thai = ? WHERE id = ?")
                    ->execute([$status, $shipper_id]);
                
                alert('Cập nhật trạng thái shipper thành công', 'success');
            } catch (Exception $e) {
                alert('Có lỗi xảy ra: ' . $e->getMessage(), 'error');
            }
            break;
    }
    
    redirect('/admin/shipping/shippers.php');
}

// Lấy danh sách shipper
try {
    $stmt = $pdo->query("
        SELECT s.*, 
               COUNT(CASE WHEN dh.trang_thai_don_hang = 'dang_giao' THEN 1 END) as don_dang_giao,
               COUNT(CASE WHEN DATE(dh.ngay_giao_hang) = CURDATE() THEN 1 END) as don_hom_nay,
               SUM(CASE WHEN dh.trang_thai_don_hang = 'da_giao' AND DATE(dh.ngay_hoan_thanh) = CURDATE() 
                        THEN dh.tong_thanh_toan ELSE 0 END) as tien_thu_hom_nay
        FROM shippers s
        LEFT JOIN don_hang dh ON s.id = dh.shipper_id
        GROUP BY s.id
        ORDER BY s.trang_thai DESC, s.ten_shipper
    ");
    $shippers = $stmt->fetchAll();
    
} catch (Exception $e) {
    $shippers = [];
    error_log('Shippers query error: ' . $e->getMessage());
}

// Lấy đơn hàng sẵn sàng giao
try {
    $stmt = $pdo->query("
        SELECT dh.*, COUNT(ctdh.id) as item_count,
               SUM(ctdh.so_luong) as total_quantity
        FROM don_hang dh
        LEFT JOIN chi_tiet_don_hang ctdh ON dh.id = ctdh.don_hang_id
        WHERE dh.phuong_thuc_thanh_toan = 'cod' 
        AND dh.trang_thai_don_hang IN ('da_xac_nhan', 'dang_chuan_bi')
        AND dh.shipper_id IS NULL
        GROUP BY dh.id
        ORDER BY dh.ngay_dat_hang ASC
        LIMIT 20
    ");
    $ready_orders = $stmt->fetchAll();
    
} catch (Exception $e) {
    $ready_orders = [];
    error_log('Ready orders query error: ' . $e->getMessage());
}

$page_title = 'Quản lý Shipper';
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
        .shipper-card {
            background: white;
            border-radius: 10px;
            padding: 20px;
            margin-bottom: 15px;
            box-shadow: 0 2px 8px rgba(0,0,0,0.1);
            border-left: 4px solid;
            transition: all 0.3s ease;
        }
        
        .shipper-card.active { border-left-color: #28a745; }
        .shipper-card.busy { border-left-color: #ffc107; }
        .shipper-card.offline { border-left-color: #6c757d; }
        
        .shipper-stats {
            background: #f8f9fa;
            border-radius: 8px;
            padding: 15px;
            margin-top: 15px;
        }
        
        .stat-item {
            text-align: center;
            padding: 10px;
        }
        
        .stat-number {
            font-size: 1.25rem;
            font-weight: bold;
            margin-bottom: 5px;
        }
        
        .order-assignment {
            background: white;
            border-radius: 10px;
            padding: 20px;
            box-shadow: 0 2px 8px rgba(0,0,0,0.1);
        }
        
        .order-item {
            background: #f8f9fa;
            border-radius: 6px;
            padding: 15px;
            margin-bottom: 10px;
            border-left: 3px solid #007bff;
        }
        
        .order-item.selected {
            background: #e3f2fd;
            border-left-color: #2196f3;
        }
        
        .delivery-route {
            background: linear-gradient(135deg, #e8f5e8 0%, #f0f8ff 100%);
            border-radius: 8px;
            padding: 15px;
            margin-top: 10px;
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
                            <h2><i class="fas fa-motorcycle me-2"></i><?= $page_title ?></h2>
                            <p class="text-muted">Quản lý đội ngũ giao hàng COD</p>
                        </div>
                        <div>
                            <button class="btn btn-success" data-bs-toggle="modal" data-bs-target="#addShipperModal">
                                <i class="fas fa-plus me-1"></i>Thêm Shipper
                            </button>
                            <a href="/admin/shipping/index.php" class="btn btn-primary">
                                <i class="fas fa-shipping-fast me-1"></i>Quản lý vận chuyển
                            </a>
                        </div>
                    </div>
                    
                    <div class="row">
                        <!-- Shipper List -->
                        <div class="col-lg-8">
                            <h5 class="mb-3">Danh sách Shipper</h5>
                            
                            <?php if (empty($shippers)): ?>
                                <div class="text-center py-5">
                                    <i class="fas fa-motorcycle fa-3x text-muted mb-3"></i>
                                    <h5>Chưa có shipper nào</h5>
                                    <p class="text-muted">Thêm shipper để bắt đầu giao hàng COD</p>
                                    <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#addShipperModal">
                                        <i class="fas fa-plus me-1"></i>Thêm Shipper đầu tiên
                                    </button>
                                </div>
                            <?php else: ?>
                                <?php foreach ($shippers as $shipper): ?>
                                    <?php
                                    $card_class = match($shipper['trang_thai']) {
                                        'hoat_dong' => $shipper['don_dang_giao'] > 0 ? 'busy' : 'active',
                                        'ban' => 'busy',
                                        'nghi_phep' => 'offline',
                                        default => 'offline'
                                    };
                                    ?>
                                    
                                    <div class="shipper-card <?= $card_class ?>">
                                        <div class="row align-items-center">
                                            <div class="col-md-3">
                                                <div class="d-flex align-items-center">
                                                    <div class="me-3">
                                                        <i class="fas fa-user-circle fa-3x text-muted"></i>
                                                    </div>
                                                    <div>
                                                        <h6 class="mb-1"><?= htmlspecialchars($shipper['ten_shipper']) ?></h6>
                                                        <div class="text-muted small">
                                                            <i class="fas fa-phone me-1"></i>
                                                            <?= htmlspecialchars($shipper['so_dien_thoai']) ?>
                                                        </div>
                                                        <span class="badge bg-<?= match($shipper['trang_thai']) {
                                                            'hoat_dong' => 'success',
                                                            'ban' => 'warning', 
                                                            'nghi_phep' => 'secondary',
                                                            default => 'danger'
                                                        } ?>">
                                                            <?= ucfirst($shipper['trang_thai']) ?>
                                                        </span>
                                                    </div>
                                                </div>
                                            </div>
                                            
                                            <div class="col-md-2">
                                                <div class="text-center">
                                                    <div class="fw-bold"><?= htmlspecialchars($shipper['khu_vuc']) ?></div>
                                                    <div class="text-muted small"><?= htmlspecialchars($shipper['loai_xe']) ?></div>
                                                </div>
                                            </div>
                                            
                                            <div class="col-md-4">
                                                <div class="shipper-stats">
                                                    <div class="row">
                                                        <div class="col-4">
                                                            <div class="stat-item">
                                                                <div class="stat-number text-warning"><?= $shipper['don_dang_giao'] ?></div>
                                                                <div class="small text-muted">Đang giao</div>
                                                            </div>
                                                        </div>
                                                        <div class="col-4">
                                                            <div class="stat-item">
                                                                <div class="stat-number text-info"><?= $shipper['don_hom_nay'] ?></div>
                                                                <div class="small text-muted">Hôm nay</div>
                                                            </div>
                                                        </div>
                                                        <div class="col-4">
                                                            <div class="stat-item">
                                                                <div class="stat-number text-success">
                                                                    <?= number_format($shipper['tien_thu_hom_nay'] / 1000) ?>k
                                                                </div>
                                                                <div class="small text-muted">Thu được</div>
                                                            </div>
                                                        </div>
                                                    </div>
                                                </div>
                                            </div>
                                            
                                            <div class="col-md-3">
                                                <div class="d-flex gap-2">
                                                    <a href="tel:<?= $shipper['so_dien_thoai'] ?>" 
                                                       class="btn btn-sm btn-outline-success">
                                                        <i class="fas fa-phone"></i>
                                                    </a>
                                                    
                                                    <button class="btn btn-sm btn-outline-primary" 
                                                            onclick="viewShipperDetails(<?= $shipper['id'] ?>)">
                                                        <i class="fas fa-eye"></i>
                                                    </button>
                                                    
                                                    <?php if ($shipper['trang_thai'] === 'hoat_dong' && $shipper['don_dang_giao'] < $shipper['don_toi_da_ngay']): ?>
                                                        <button class="btn btn-sm btn-primary" 
                                                                onclick="selectShipperForAssign(<?= $shipper['id'] ?>, '<?= htmlspecialchars($shipper['ten_shipper']) ?>')">
                                                            <i class="fas fa-plus"></i> Giao hàng
                                                        </button>
                                                    <?php else: ?>
                                                        <button class="btn btn-sm btn-secondary" disabled>
                                                            <i class="fas fa-ban"></i> Không khả dụng
                                                        </button>
                                                    <?php endif; ?>
                                                </div>
                                            </div>
                                        </div>
                                        
                                        <?php if ($shipper['don_dang_giao'] > 0): ?>
                                            <div class="delivery-route">
                                                <small class="text-muted">
                                                    <i class="fas fa-route me-1"></i>
                                                    Đang giao <?= $shipper['don_dang_giao'] ?> đơn hàng
                                                    | Giới hạn: <?= $shipper['don_toi_da_ngay'] ?> đơn/ngày
                                                </small>
                                            </div>
                                        <?php endif; ?>
                                    </div>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </div>
                        
                        <!-- Order Assignment -->
                        <div class="col-lg-4">
                            <div class="order-assignment">
                                <h5 class="mb-3">
                                    <i class="fas fa-clipboard-list me-2"></i>
                                    Phân công giao hàng
                                </h5>
                                
                                <?php if (empty($ready_orders)): ?>
                                    <div class="text-center py-4">
                                        <i class="fas fa-check-circle fa-2x text-success mb-2"></i>
                                        <p class="text-muted mb-0">Không có đơn hàng cần phân công</p>
                                    </div>
                                <?php else: ?>
                                    <form id="assignOrdersForm" method="POST">
                                        <input type="hidden" name="action" value="assign_orders">
                                        <input type="hidden" name="shipper_id" id="selected_shipper_id">
                                        
                                        <div class="mb-3">
                                            <label class="form-label">Shipper được chọn:</label>
                                            <input type="text" class="form-control" id="selected_shipper_name" 
                                                   placeholder="Chọn shipper từ danh sách bên trái" readonly>
                                        </div>
                                        
                                        <div class="mb-3">
                                            <label class="form-label">Ngày giao hàng:</label>
                                            <input type="date" class="form-control" name="delivery_date" 
                                                   value="<?= date('Y-m-d') ?>" required>
                                        </div>
                                        
                                        <div class="mb-3">
                                            <label class="form-label">Chọn đơn hàng:</label>
                                            <div class="order-list" style="max-height: 300px; overflow-y: auto;">
                                                <?php foreach ($ready_orders as $order): ?>
                                                    <div class="order-item" onclick="toggleOrderSelection(this, <?= $order['id'] ?>)">
                                                        <div class="form-check">
                                                            <input class="form-check-input" type="checkbox" 
                                                                   name="order_ids[]" value="<?= $order['id'] ?>" 
                                                                   id="order_<?= $order['id'] ?>">
                                                            <label class="form-check-label w-100" for="order_<?= $order['id'] ?>">
                                                                <div class="d-flex justify-content-between">
                                                                    <div>
                                                                        <strong>#<?= $order['ma_don_hang'] ?></strong>
                                                                        <div class="text-muted small">
                                                                            <?= htmlspecialchars($order['ho_ten_nhan']) ?>
                                                                        </div>
                                                                        <div class="text-muted small">
                                                                            <?= $order['item_count'] ?> sản phẩm
                                                                        </div>
                                                                    </div>
                                                                    <div class="text-end">
                                                                        <div class="fw-bold text-primary">
                                                                            <?= formatPrice($order['tong_thanh_toan']) ?>
                                                                        </div>
                                                                        <div class="text-muted small">
                                                                            <?= date('d/m H:i', strtotime($order['ngay_dat_hang'])) ?>
                                                                        </div>
                                                                    </div>
                                                                </div>
                                                            </label>
                                                        </div>
                                                    </div>
                                                <?php endforeach; ?>
                                            </div>
                                        </div>
                                        
                                        <button type="submit" class="btn btn-primary w-100" id="assignBtn" disabled>
                                            <i class="fas fa-truck me-1"></i>Phân công giao hàng
                                        </button>
                                    </form>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <!-- Add Shipper Modal -->
    <div class="modal fade" id="addShipperModal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <form method="POST">
                    <div class="modal-header">
                        <h5 class="modal-title">Thêm Shipper mới</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                    </div>
                    <div class="modal-body">
                        <input type="hidden" name="action" value="add_shipper">
                        
                        <div class="row">
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label class="form-label">Tên shipper:</label>
                                    <input type="text" class="form-control" name="name" required>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label class="form-label">Số điện thoại:</label>
                                    <input type="tel" class="form-control" name="phone" required>
                                </div>
                            </div>
                        </div>
                        
                        <div class="row">
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label class="form-label">Khu vực giao hàng:</label>
                                    <input type="text" class="form-control" name="area" 
                                           placeholder="VD: Quận 1, Quận 3" required>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label class="form-label">Loại xe:</label>
                                    <select class="form-select" name="vehicle_type" required>
                                        <option value="">Chọn loại xe...</option>
                                        <option value="xe_may">Xe máy</option>
                                        <option value="xe_dien">Xe điện</option>
                                        <option value="xe_tai">Xe tải nhỏ</option>
                                    </select>
                                </div>
                            </div>
                        </div>
                        
                        <div class="mb-3">
                            <label class="form-label">Số đơn tối đa/ngày:</label>
                            <input type="number" class="form-control" name="max_orders" 
                                   value="15" min="1" max="50" required>
                            <div class="form-text">Số đơn hàng tối đa có thể giao trong một ngày</div>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Hủy</button>
                        <button type="submit" class="btn btn-success">
                            <i class="fas fa-plus me-1"></i>Thêm Shipper
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>
    
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
    
    <script>
        function selectShipperForAssign(shipperId, shipperName) {
            document.getElementById('selected_shipper_id').value = shipperId;
            document.getElementById('selected_shipper_name').value = shipperName;
            
            // Enable form
            document.getElementById('assignBtn').disabled = false;
            
            // Scroll to assignment form
            document.querySelector('.order-assignment').scrollIntoView({
                behavior: 'smooth'
            });
        }
        
        function toggleOrderSelection(element, orderId) {
            const checkbox = element.querySelector('input[type="checkbox"]');
            checkbox.checked = !checkbox.checked;
            
            if (checkbox.checked) {
                element.classList.add('selected');
            } else {
                element.classList.remove('selected');
            }
            
            updateAssignButton();
        }
        
        function updateAssignButton() {
            const checkedBoxes = document.querySelectorAll('input[name="order_ids[]"]:checked');
            const shipperId = document.getElementById('selected_shipper_id').value;
            const assignBtn = document.getElementById('assignBtn');
            
            assignBtn.disabled = !(checkedBoxes.length > 0 && shipperId);
            
            if (checkedBoxes.length > 0) {
                assignBtn.innerHTML = `<i class="fas fa-truck me-1"></i>Phân công ${checkedBoxes.length} đơn hàng`;
            } else {
                assignBtn.innerHTML = '<i class="fas fa-truck me-1"></i>Phân công giao hàng';
            }
        }
        
        function viewShipperDetails(shipperId) {
            // TODO: Implement shipper details modal
            alert('Xem chi tiết shipper #' + shipperId);
        }
        
        // Initialize
        document.addEventListener('DOMContentLoaded', function() {
            // Add event listeners to checkboxes
            document.querySelectorAll('input[name="order_ids[]"]').forEach(checkbox => {
                checkbox.addEventListener('change', updateAssignButton);
            });
        });
    </script>
</body>
</html>