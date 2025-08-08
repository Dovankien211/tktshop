<?php
// admin/orders/update_status.php
/**
 * Cập nhật trạng thái đơn hàng - Xử lý các trạng thái: Chờ xác nhận → Đã xác nhận → Đang chuẩn bị → Đang giao → Đã giao
 * Chức năng: Cập nhật trạng thái, xử lý thanh toán VNPay, xử lý COD, hủy đơn hết hạn
 */

require_once '../../config/database.php';
require_once '../../config/config.php';

requireLogin();

$order_id = (int)($_GET['id'] ?? 0);
$action = $_GET['action'] ?? '';

// Lấy thông tin đơn hàng
$stmt = $pdo->prepare("SELECT * FROM don_hang WHERE id = ?");
$stmt->execute([$order_id]);
$order = $stmt->fetch();

if (!$order) {
    alert('Đơn hàng không tồn tại!', 'danger');
    redirect('/tktshop/admin/orders/');
}

$errors = [];

// Xử lý hành động nhanh
if (!empty($action)) {
    switch ($action) {
        case 'confirm':
            if ($order['trang_thai_don_hang'] == 'cho_xac_nhan') {
                $stmt = $pdo->prepare("
                    UPDATE don_hang 
                    SET trang_thai_don_hang = 'da_xac_nhan', 
                        ngay_xac_nhan = NOW(), 
                        nguoi_xu_ly = ? 
                    WHERE id = ?
                ");
                if ($stmt->execute([$_SESSION['admin_id'], $order_id])) {
                    alert('Xác nhận đơn hàng thành công!', 'success');
                } else {
                    alert('Lỗi khi xác nhận đơn hàng!', 'danger');
                }
            }
            redirect("detail.php?id=$order_id");
            break;
            
        case 'cancel':
            if (in_array($order['trang_thai_don_hang'], ['cho_xac_nhan', 'da_xac_nhan'])) {
                // Hoàn lại tồn kho
                $stmt = $pdo->prepare("
                    UPDATE bien_the_san_pham btp
                    INNER JOIN chi_tiet_don_hang ct ON btp.id = ct.bien_the_id
                    SET btp.so_luong_ton_kho = btp.so_luong_ton_kho + ct.so_luong
                    WHERE ct.don_hang_id = ?
                ");
                $stmt->execute([$order_id]);
                
                // Cập nhật trạng thái đơn hàng
                $stmt = $pdo->prepare("
                    UPDATE don_hang 
                    SET trang_thai_don_hang = 'da_huy', 
                        ngay_huy = NOW(), 
                        ly_do_huy = 'Hủy bởi admin',
                        nguoi_xu_ly = ? 
                    WHERE id = ?
                ");
                if ($stmt->execute([$_SESSION['admin_id'], $order_id])) {
                    alert('Hủy đơn hàng thành công!', 'success');
                } else {
                    alert('Lỗi khi hủy đơn hàng!', 'danger');
                }
            }
            redirect("detail.php?id=$order_id");
            break;
            
        case 'prepare':
            if ($order['trang_thai_don_hang'] == 'da_xac_nhan') {
                $stmt = $pdo->prepare("
                    UPDATE don_hang 
                    SET trang_thai_don_hang = 'dang_chuan_bi', 
                        nguoi_xu_ly = ? 
                    WHERE id = ?
                ");
                if ($stmt->execute([$_SESSION['admin_id'], $order_id])) {
                    alert('Cập nhật trạng thái thành công!', 'success');
                } else {
                    alert('Lỗi khi cập nhật trạng thái!', 'danger');
                }
            }
            redirect("detail.php?id=$order_id");
            break;
            
        case 'ship':
            if ($order['trang_thai_don_hang'] == 'dang_chuan_bi') {
                $stmt = $pdo->prepare("
                    UPDATE don_hang 
                    SET trang_thai_don_hang = 'dang_giao', 
                        ngay_giao_hang = NOW(),
                        nguoi_xu_ly = ? 
                    WHERE id = ?
                ");
                if ($stmt->execute([$_SESSION['admin_id'], $order_id])) {
                    alert('Cập nhật trạng thái thành công!', 'success');
                } else {
                    alert('Lỗi khi cập nhật trạng thái!', 'danger');
                }
            }
            redirect("detail.php?id=$order_id");
            break;
            
        case 'complete':
            if ($order['trang_thai_don_hang'] == 'dang_giao') {
                $pdo->beginTransaction();
                try {
                    // Cập nhật trạng thái đơn hàng
                    $stmt = $pdo->prepare("
                        UPDATE don_hang 
                        SET trang_thai_don_hang = 'da_giao', 
                            ngay_hoan_thanh = NOW(),
                            trang_thai_thanh_toan = 'da_thanh_toan',
                            nguoi_xu_ly = ? 
                        WHERE id = ?
                    ");
                    $stmt->execute([$_SESSION['admin_id'], $order_id]);
                    
                    // Cập nhật số lượng đã bán
                    $stmt = $pdo->prepare("
                        UPDATE bien_the_san_pham btp
                        INNER JOIN chi_tiet_don_hang ct ON btp.id = ct.bien_the_id
                        SET btp.so_luong_da_ban = btp.so_luong_da_ban + ct.so_luong
                        WHERE ct.don_hang_id = ?
                    ");
                    $stmt->execute([$order_id]);
                    
                    $pdo->commit();
                    alert('Hoàn thành đơn hàng thành công!', 'success');
                } catch (Exception $e) {
                    $pdo->rollback();
                    alert('Lỗi khi hoàn thành đơn hàng!', 'danger');
                }
            }
            redirect("detail.php?id=$order_id");
            break;
    }
}

// Xử lý form cập nhật chi tiết
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $trang_thai_don_hang = $_POST['trang_thai_don_hang'] ?? '';
    $trang_thai_thanh_toan = $_POST['trang_thai_thanh_toan'] ?? '';
    $ma_van_don = trim($_POST['ma_van_don'] ?? '');
    $ghi_chu_admin = trim($_POST['ghi_chu_admin'] ?? '');
    $ly_do_huy = trim($_POST['ly_do_huy'] ?? '');
    
    // Validate
    if (empty($trang_thai_don_hang)) {
        $errors[] = 'Vui lòng chọn trạng thái đơn hàng';
    }
    
    if (empty($trang_thai_thanh_toan)) {
        $errors[] = 'Vui lòng chọn trạng thái thanh toán';
    }
    
    if ($trang_thai_don_hang == 'da_huy' && empty($ly_do_huy)) {
        $errors[] = 'Vui lòng nhập lý do hủy đơn hàng';
    }
    
    // Cập nhật database
    if (empty($errors)) {
        $pdo->beginTransaction();
        try {
            $update_fields = [
                'trang_thai_don_hang' => $trang_thai_don_hang,
                'trang_thai_thanh_toan' => $trang_thai_thanh_toan,
                'ma_van_don' => $ma_van_don,
                'ghi_chu_admin' => $ghi_chu_admin,
                'nguoi_xu_ly' => $_SESSION['admin_id']
            ];
            
            // Xử lý ngày tháng theo trạng thái
            if ($trang_thai_don_hang == 'da_xac_nhan' && $order['trang_thai_don_hang'] != 'da_xac_nhan') {
                $update_fields['ngay_xac_nhan'] = date('Y-m-d H:i:s');
            }
            
            if ($trang_thai_don_hang == 'dang_giao' && $order['trang_thai_don_hang'] != 'dang_giao') {
                $update_fields['ngay_giao_hang'] = date('Y-m-d H:i:s');
            }
            
            if ($trang_thai_don_hang == 'da_giao' && $order['trang_thai_don_hang'] != 'da_giao') {
                $update_fields['ngay_hoan_thanh'] = date('Y-m-d H:i:s');
                // Tự động cập nhật trạng thái thanh toán thành đã thanh toán
                $update_fields['trang_thai_thanh_toan'] = 'da_thanh_toan';
            }
            
            if ($trang_thai_don_hang == 'da_huy') {
                $update_fields['ngay_huy'] = date('Y-m-d H:i:s');
                $update_fields['ly_do_huy'] = $ly_do_huy;
                
                // Hoàn lại tồn kho nếu chưa hoàn
                if ($order['trang_thai_don_hang'] != 'da_huy') {
                    $stmt = $pdo->prepare("
                        UPDATE bien_the_san_pham btp
                        INNER JOIN chi_tiet_don_hang ct ON btp.id = ct.bien_the_id
                        SET btp.so_luong_ton_kho = btp.so_luong_ton_kho + ct.so_luong
                        WHERE ct.don_hang_id = ?
                    ");
                    $stmt->execute([$order_id]);
                }
            }
            
            // Xây dựng câu SQL UPDATE
            $set_clause = [];
            $params = [];
            foreach ($update_fields as $field => $value) {
                $set_clause[] = "$field = ?";
                $params[] = $value;
            }
            $params[] = $order_id;
            
            $sql = "UPDATE don_hang SET " . implode(', ', $set_clause) . " WHERE id = ?";
            $stmt = $pdo->prepare($sql);
            $stmt->execute($params);
            
            $pdo->commit();
            alert('Cập nhật đơn hàng thành công!', 'success');
            redirect("detail.php?id=$order_id");
            
        } catch (Exception $e) {
            $pdo->rollback();
            $errors[] = 'Lỗi khi cập nhật đơn hàng: ' . $e->getMessage();
        }
    }
}

// Lấy thông tin chi tiết để hiển thị
$stmt = $pdo->prepare("
    SELECT dh.*, nd.ho_ten as ten_khach_hang
    FROM don_hang dh 
    LEFT JOIN nguoi_dung nd ON dh.khach_hang_id = nd.id
    WHERE dh.id = ?
");
$stmt->execute([$order_id]);
$order = $stmt->fetch();
?>

<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Cập nhật đơn hàng #<?= $order['ma_don_hang'] ?> - <?= SITE_NAME ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
</head>
<body>
    <div class="container-fluid">
        <div class="row">
            <!-- Sidebar -->
            <?php include '../layouts/sidebar.php'; ?>
            
            <!-- Main content -->
            <div class="col-md-10">
                <div class="d-flex justify-content-between align-items-center py-3">
                    <div>
                        <h2>Cập nhật đơn hàng #<?= $order['ma_don_hang'] ?></h2>
                        <p class="text-muted mb-0">
                            Khách hàng: <strong><?= htmlspecialchars($order['ho_ten_nhan']) ?></strong>
                        </p>
                    </div>
                    <div>
                        <a href="detail.php?id=<?= $order['id'] ?>" class="btn btn-info me-2">
                            <i class="fas fa-eye"></i> Xem chi tiết
                        </a>
                        <a href="/tktshop/admin/orders/" class="btn btn-secondary">
                            <i class="fas fa-arrow-left"></i> Quay lại
                        </a>
                    </div>
                </div>

                <?php if (!empty($errors)): ?>
                    <div class="alert alert-danger">
                        <ul class="mb-0">
                            <?php foreach ($errors as $error): ?>
                                <li><?= $error ?></li>
                            <?php endforeach; ?>
                        </ul>
                    </div>
                <?php endif; ?>

                <div class="row">
                    <!-- Form cập nhật -->
                    <div class="col-md-8">
                        <div class="card">
                            <div class="card-header">
                                <h5>Cập nhật trạng thái đơn hàng</h5>
                            </div>
                            <div class="card-body">
                                <form method="POST">
                                    <div class="row">
                                        <div class="col-md-6">
                                            <div class="mb-3">
                                                <label for="trang_thai_don_hang" class="form-label">Trạng thái đơn hàng <span class="text-danger">*</span></label>
                                                <select class="form-select" id="trang_thai_don_hang" name="trang_thai_don_hang" required>
                                                    <option value="cho_xac_nhan" <?= $order['trang_thai_don_hang'] == 'cho_xac_nhan' ? 'selected' : '' ?>>
                                                        Chờ xác nhận
                                                    </option>
                                                    <option value="da_xac_nhan" <?= $order['trang_thai_don_hang'] == 'da_xac_nhan' ? 'selected' : '' ?>>
                                                        Đã xác nhận
                                                    </option>
                                                    <option value="dang_chuan_bi" <?= $order['trang_thai_don_hang'] == 'dang_chuan_bi' ? 'selected' : '' ?>>
                                                        Đang chuẩn bị
                                                    </option>
                                                    <option value="dang_giao" <?= $order['trang_thai_don_hang'] == 'dang_giao' ? 'selected' : '' ?>>
                                                        Đang giao
                                                    </option>
                                                    <option value="da_giao" <?= $order['trang_thai_don_hang'] == 'da_giao' ? 'selected' : '' ?>>
                                                        Đã giao
                                                    </option>
                                                    <option value="da_huy" <?= $order['trang_thai_don_hang'] == 'da_huy' ? 'selected' : '' ?>>
                                                        Đã hủy
                                                    </option>
                                                    <option value="hoan_tra" <?= $order['trang_thai_don_hang'] == 'hoan_tra' ? 'selected' : '' ?>>
                                                        Hoàn trả
                                                    </option>
                                                </select>
                                            </div>
                                        </div>
                                        
                                        <div class="col-md-6">
                                            <div class="mb-3">
                                                <label for="trang_thai_thanh_toan" class="form-label">Trạng thái thanh toán <span class="text-danger">*</span></label>
                                                <select class="form-select" id="trang_thai_thanh_toan" name="trang_thai_thanh_toan" required>
                                                    <option value="chua_thanh_toan" <?= $order['trang_thai_thanh_toan'] == 'chua_thanh_toan' ? 'selected' : '' ?>>
                                                        Chưa thanh toán
                                                    </option>
                                                    <option value="da_thanh_toan" <?= $order['trang_thai_thanh_toan'] == 'da_thanh_toan' ? 'selected' : '' ?>>
                                                        Đã thanh toán
                                                    </option>
                                                    <option value="cho_thanh_toan" <?= $order['trang_thai_thanh_toan'] == 'cho_thanh_toan' ? 'selected' : '' ?>>
                                                        Chờ thanh toán
                                                    </option>
                                                    <option value="that_bai" <?= $order['trang_thai_thanh_toan'] == 'that_bai' ? 'selected' : '' ?>>
                                                        Thất bại
                                                    </option>
                                                    <option value="het_han" <?= $order['trang_thai_thanh_toan'] == 'het_han' ? 'selected' : '' ?>>
                                                        Hết hạn
                                                    </option>
                                                    <option value="hoan_tien" <?= $order['trang_thai_thanh_toan'] == 'hoan_tien' ? 'selected' : '' ?>>
                                                        Hoàn tiền
                                                    </option>
                                                </select>
                                            </div>
                                        </div>
                                    </div>
                                    
                                    <div class="mb-3">
                                        <label for="ma_van_don" class="form-label">Mã vận đơn</label>
                                        <input type="text" 
                                               class="form-control" 
                                               id="ma_van_don" 
                                               name="ma_van_don" 
                                               value="<?= htmlspecialchars($order['ma_van_don'] ?? '') ?>"
                                               placeholder="Nhập mã vận đơn nếu có">
                                        <div class="form-text">Mã vận đơn từ đơn vị vận chuyển</div>
                                    </div>
                                    
                                    <div class="mb-3">
                                        <label for="ghi_chu_admin" class="form-label">Ghi chú nội bộ</label>
                                        <textarea class="form-control" 
                                                  id="ghi_chu_admin" 
                                                  name="ghi_chu_admin" 
                                                  rows="3"
                                                  placeholder="Ghi chú dành cho nội bộ..."><?= htmlspecialchars($order['ghi_chu_admin'] ?? '') ?></textarea>
                                    </div>
                                    
                                    <div class="mb-3" id="ly_do_huy_group" style="display: none;">
                                        <label for="ly_do_huy" class="form-label">Lý do hủy đơn hàng <span class="text-danger">*</span></label>
                                        <textarea class="form-control" 
                                                  id="ly_do_huy" 
                                                  name="ly_do_huy" 
                                                  rows="3"
                                                  placeholder="Nhập lý do hủy đơn hàng..."><?= htmlspecialchars($order['ly_do_huy'] ?? '') ?></textarea>
                                    </div>
                                    
                                    <div class="d-flex gap-2">
                                        <button type="submit" class="btn btn-primary">
                                            <i class="fas fa-save"></i> Cập nhật đơn hàng
                                        </button>
                                        <a href="detail.php?id=<?= $order['id'] ?>" class="btn btn-secondary">
                                            Hủy
                                        </a>
                                    </div>
                                </form>
                            </div>
                        </div>
                    </div>
                    
                    <!-- Thông tin đơn hàng -->
                    <div class="col-md-4">
                        <div class="card mb-3">
                            <div class="card-header">
                                <h5>Thông tin hiện tại</h5>
                            </div>
                            <div class="card-body">
                                <p><strong>Mã đơn hàng:</strong> <?= $order['ma_don_hang'] ?></p>
                                <p><strong>Trạng thái:</strong> 
                                    <?php 
                                    $order_status_class = [
                                        'cho_xac_nhan' => 'warning',
                                        'da_xac_nhan' => 'info',
                                        'dang_chuan_bi' => 'primary',
                                        'dang_giao' => 'primary',
                                        'da_giao' => 'success',
                                        'da_huy' => 'danger',
                                        'hoan_tra' => 'secondary'
                                    ];
                                    $order_status_text = [
                                        'cho_xac_nhan' => 'Chờ xác nhận',
                                        'da_xac_nhan' => 'Đã xác nhận',
                                        'dang_chuan_bi' => 'Đang chuẩn bị',
                                        'dang_giao' => 'Đang giao',
                                        'da_giao' => 'Đã giao',
                                        'da_huy' => 'Đã hủy',
                                        'hoan_tra' => 'Hoàn trả'
                                    ];
                                    ?>
                                    <span class="badge bg-<?= $order_status_class[$order['trang_thai_don_hang']] ?>">
                                        <?= $order_status_text[$order['trang_thai_don_hang']] ?>
                                    </span>
                                </p>
                                <p><strong>Thanh toán:</strong> 
                                    <?php 
                                    $payment_status_class = [
                                        'chua_thanh_toan' => 'warning',
                                        'da_thanh_toan' => 'success',
                                        'cho_thanh_toan' => 'info',
                                        'that_bai' => 'danger',
                                        'het_han' => 'secondary',
                                        'hoan_tien' => 'secondary'
                                    ];
                                    $payment_status_text = [
                                        'chua_thanh_toan' => 'Chưa thanh toán',
                                        'da_thanh_toan' => 'Đã thanh toán',
                                        'cho_thanh_toan' => 'Chờ thanh toán',
                                        'that_bai' => 'Thất bại',
                                        'het_han' => 'Hết hạn',
                                        'hoan_tien' => 'Hoàn tiền'
                                    ];
                                    ?>
                                    <span class="badge bg-<?= $payment_status_class[$order['trang_thai_thanh_toan']] ?>">
                                        <?= $payment_status_text[$order['trang_thai_thanh_toan']] ?>
                                    </span>
                                </p>
                                <p><strong>Tổng tiền:</strong> <span class="text-success"><?= formatPrice($order['tong_thanh_toan']) ?></span></p>
                                <p><strong>Phương thức:</strong> <?= $order['phuong_thuc_thanh_toan'] == 'vnpay' ? 'VNPay' : 'COD' ?></p>
                                
                                <hr>
                                
                                <p><strong>Khách hàng:</strong> <?= htmlspecialchars($order['ho_ten_nhan']) ?></p>
                                <p><strong>SĐT:</strong> <?= htmlspecialchars($order['so_dien_thoai_nhan']) ?></p>
                                
                                <?php if ($order['ma_van_don']): ?>
                                    <hr>
                                    <p><strong>Mã vận đơn:</strong> <code><?= $order['ma_van_don'] ?></code></p>
                                <?php endif; ?>
                            </div>
                        </div>
                        
                        <!-- Hành động nhanh -->
                        <div class="card">
                            <div class="card-header">
                                <h5>Hành động nhanh</h5>
                            </div>
                            <div class="card-body">
                                <?php if ($order['trang_thai_don_hang'] == 'cho_xac_nhan'): ?>
                                    <a href="?id=<?= $order['id'] ?>&action=confirm" 
                                       class="btn btn-success btn-sm w-100 mb-2"
                                       onclick="return confirm('Xác nhận đơn hàng này?')">
                                        <i class="fas fa-check"></i> Xác nhận ngay
                                    </a>
                                <?php endif; ?>
                                
                                <?php if ($order['trang_thai_don_hang'] == 'da_xac_nhan'): ?>
                                    <a href="?id=<?= $order['id'] ?>&action=prepare" 
                                       class="btn btn-primary btn-sm w-100 mb-2">
                                        <i class="fas fa-box"></i> Chuẩn bị hàng
                                    </a>
                                <?php endif; ?>
                                
                                <?php if ($order['trang_thai_don_hang'] == 'dang_chuan_bi'): ?>
                                    <a href="?id=<?= $order['id'] ?>&action=ship" 
                                       class="btn btn-info btn-sm w-100 mb-2">
                                        <i class="fas fa-shipping-fast"></i> Bắt đầu giao
                                    </a>
                                <?php endif; ?>
                                
                                <?php if ($order['trang_thai_don_hang'] == 'dang_giao'): ?>
                                    <a href="?id=<?= $order['id'] ?>&action=complete" 
                                       class="btn btn-success btn-sm w-100 mb-2"
                                       onclick="return confirm('Xác nhận đã giao hàng thành công?')">
                                        <i class="fas fa-check-circle"></i> Hoàn thành
                                    </a>
                                <?php endif; ?>
                                
                                <hr>
                                
                                <?php if ($order['phuong_thuc_thanh_toan'] == 'vnpay'): ?>
                                    <a href="/tktshop/vnpay/check_status.php?order_id=<?= $order['id'] ?>" 
                                       class="btn btn-outline-info btn-sm w-100 mb-2">
                                        <i class="fas fa-credit-card"></i> Kiểm tra VNPay
                                    </a>
                                <?php endif; ?>
                                
                                <a href="detail.php?id=<?= $order['id'] ?>" 
                                   class="btn btn-outline-primary btn-sm w-100">
                                    <i class="fas fa-eye"></i> Xem chi tiết
                                </a>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
    
    <script>
        // Hiển thị/ẩn trường lý do hủy
        document.getElementById('trang_thai_don_hang').addEventListener('change', function() {
            const lyDoHuyGroup = document.getElementById('ly_do_huy_group');
            const lyDoHuyInput = document.getElementById('ly_do_huy');
            
            if (this.value === 'da_huy') {
                lyDoHuyGroup.style.display = 'block';
                lyDoHuyInput.required = true;
            } else {
                lyDoHuyGroup.style.display = 'none';
                lyDoHuyInput.required = false;
            }
        });
        
        // Khởi tạo trạng thái ban đầu
        document.addEventListener('DOMContentLoaded', function() {
            const trangThaiSelect = document.getElementById('trang_thai_don_hang');
            if (trangThaiSelect.value === 'da_huy') {
                document.getElementById('ly_do_huy_group').style.display = 'block';
                document.getElementById('ly_do_huy').required = true;
            }
        });
        
        // Tự động cập nhật trạng thái thanh toán khi hoàn thành đơn hàng
        document.getElementById('trang_thai_don_hang').addEventListener('change', function() {
            const trangThaiThanhToan = document.getElementById('trang_thai_thanh_toan');
            
            if (this.value === 'da_giao') {
                trangThaiThanhToan.value = 'da_thanh_toan';
            }
        });
    </script>
</body>
</html>