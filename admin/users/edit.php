<?php
// admin/users/edit.php - ĐÃ SỬA ĐƯỜNG DẪN
/**
 * Chỉnh sửa thông tin người dùng (không mã hóa password)
 */

require_once '../../config/database.php';
require_once '../../config/config.php';

requireLogin();

$id = (int)($_GET['id'] ?? 0);
$errors = [];

// Lấy thông tin người dùng hiện tại
$stmt = $pdo->prepare("SELECT * FROM nguoi_dung WHERE id = ?");
$stmt->execute([$id]);
$user = $stmt->fetch();

if (!$user) {
    alert('Người dùng không tồn tại!', 'danger');
    redirect('admin/users/'); // ĐÃ SỬA
}

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $ten_dang_nhap = trim($_POST['ten_dang_nhap'] ?? '');
    $mat_khau = $_POST['mat_khau'] ?? '';
    $xac_nhan_mat_khau = $_POST['xac_nhan_mat_khau'] ?? '';
    $ho_ten = trim($_POST['ho_ten'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $so_dien_thoai = trim($_POST['so_dien_thoai'] ?? '');
    $dia_chi = trim($_POST['dia_chi'] ?? '');
    $ngay_sinh = $_POST['ngay_sinh'] ?? '';
    $gioi_tinh = $_POST['gioi_tinh'] ?? 'khac';
    $vai_tro = $_POST['vai_tro'] ?? 'khach_hang';
    $trang_thai = $_POST['trang_thai'] ?? 'hoat_dong';
    
    // Validate
    if (empty($ten_dang_nhap)) {
        $errors[] = 'Tên đăng nhập không được để trống';
    } elseif (strlen($ten_dang_nhap) < 3) {
        $errors[] = 'Tên đăng nhập phải có ít nhất 3 ký tự';
    }
    
    // Validate mật khẩu nếu có thay đổi
    if (!empty($mat_khau)) {
        if (strlen($mat_khau) < 6) {
            $errors[] = 'Mật khẩu phải có ít nhất 6 ký tự';
        }
        if ($mat_khau !== $xac_nhan_mat_khau) {
            $errors[] = 'Xác nhận mật khẩu không khớp';
        }
    }
    
    if (empty($ho_ten)) {
        $errors[] = 'Họ tên không được để trống';
    }
    
    if (empty($email)) {
        $errors[] = 'Email không được để trống';
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $errors[] = 'Email không hợp lệ';
    }
    
    if (!empty($so_dien_thoai) && !preg_match('/^[0-9]{10,11}$/', $so_dien_thoai)) {
        $errors[] = 'Số điện thoại không hợp lệ';
    }
    
    // Kiểm tra trùng lặp (loại trừ bản ghi hiện tại)
    if (!empty($ten_dang_nhap)) {
        $check = $pdo->prepare("SELECT id FROM nguoi_dung WHERE ten_dang_nhap = ? AND id != ?");
        $check->execute([$ten_dang_nhap, $id]);
        if ($check->fetch()) {
            $errors[] = 'Tên đăng nhập đã tồn tại';
        }
    }
    
    if (!empty($email)) {
        $check = $pdo->prepare("SELECT id FROM nguoi_dung WHERE email = ? AND id != ?");
        $check->execute([$email, $id]);
        if ($check->fetch()) {
            $errors[] = 'Email đã tồn tại';
        }
    }
    
    // Không cho phép thay đổi vai trò của chính mình
    if ($id == $_SESSION['admin_id'] && $vai_tro != $user['vai_tro']) {
        $errors[] = 'Không thể thay đổi vai trò của chính mình';
    }
    
    // Không cho phép khóa tài khoản của chính mình
    if ($id == $_SESSION['admin_id'] && $trang_thai != 'hoat_dong') {
        $errors[] = 'Không thể thay đổi trạng thái tài khoản của chính mình';
    }
    
    // Cập nhật database (không mã hóa password)
    if (empty($errors)) {
        if (!empty($mat_khau)) {
            // Cập nhật với mật khẩu mới
            $stmt = $pdo->prepare("
                UPDATE nguoi_dung 
                SET ten_dang_nhap = ?, mat_khau = ?, ho_ten = ?, email = ?, 
                    so_dien_thoai = ?, dia_chi = ?, ngay_sinh = ?, gioi_tinh = ?, 
                    vai_tro = ?, trang_thai = ?
                WHERE id = ?
            ");
            $params = [
                $ten_dang_nhap, $mat_khau, $ho_ten, $email, $so_dien_thoai, 
                $dia_chi, $ngay_sinh ?: null, $gioi_tinh, $vai_tro, $trang_thai, $id
            ];
        } else {
            // Cập nhật không thay đổi mật khẩu
            $stmt = $pdo->prepare("
                UPDATE nguoi_dung 
                SET ten_dang_nhap = ?, ho_ten = ?, email = ?, so_dien_thoai = ?, 
                    dia_chi = ?, ngay_sinh = ?, gioi_tinh = ?, vai_tro = ?, trang_thai = ?
                WHERE id = ?
            ");
            $params = [
                $ten_dang_nhap, $ho_ten, $email, $so_dien_thoai, $dia_chi, 
                $ngay_sinh ?: null, $gioi_tinh, $vai_tro, $trang_thai, $id
            ];
        }
        
        if ($stmt->execute($params)) {
            alert('Cập nhật người dùng thành công!', 'success');
            redirect('admin/users/'); // ĐÃ SỬA
        } else {
            $errors[] = 'Lỗi khi cập nhật dữ liệu';
        }
    }
} else {
    // Hiển thị dữ liệu hiện tại
    $_POST = $user;
    $_POST['ngay_sinh'] = $user['ngay_sinh'] ? date('Y-m-d', strtotime($user['ngay_sinh'])) : '';
}
?>

<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Chỉnh sửa người dùng - <?= SITE_NAME ?></title>
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
                        <h2>Chỉnh sửa người dùng</h2>
                        <p class="text-muted mb-0">ID: <?= $user['id'] ?> - <?= htmlspecialchars($user['ho_ten']) ?></p>
                        <small class="text-muted">Tạo ngày: <?= date('d/m/Y H:i', strtotime($user['ngay_tao'])) ?></small>
                    </div>
                    <a href="<?= adminUrl('users/') ?>" class="btn btn-secondary">
                        <i class="fas fa-arrow-left"></i> Quay lại
                    </a>
                </div>

                <?php if (!empty($errors)): ?>
                    <div class="alert alert-danger">
                        <i class="fas fa-exclamation-triangle me-2"></i>
                        <ul class="mb-0">
                            <?php foreach ($errors as $error): ?>
                                <li><?= $error ?></li>
                            <?php endforeach; ?>
                        </ul>
                    </div>
                <?php endif; ?>

                <form method="POST" action="<?= $_SERVER['PHP_SELF'] ?>">
                    <div class="row">
                        <!-- Thông tin đăng nhập -->
                        <div class="col-md-6">
                            <div class="card mb-3">
                                <div class="card-header">
                                    <h5><i class="fas fa-sign-in-alt me-2"></i>Thông tin đăng nhập</h5>
                                </div>
                                <div class="card-body">
                                    <div class="mb-3">
                                        <label for="ten_dang_nhap" class="form-label">Tên đăng nhập <span class="text-danger">*</span></label>
                                        <input type="text" 
                                               class="form-control" 
                                               id="ten_dang_nhap" 
                                               name="ten_dang_nhap" 
                                               value="<?= htmlspecialchars($_POST['ten_dang_nhap'] ?? '') ?>"
                                               required>
                                    </div>
                                    
                                    <div class="mb-3">
                                        <label for="mat_khau" class="form-label">Mật khẩu mới</label>
                                        <div class="input-group">
                                            <input type="password" 
                                                   class="form-control" 
                                                   id="mat_khau" 
                                                   name="mat_khau" 
                                                   placeholder="Để trống nếu không đổi mật khẩu">
                                            <button class="btn btn-outline-secondary" type="button" onclick="togglePassword('mat_khau')">
                                                <i class="fas fa-eye"></i>
                                            </button>
                                        </div>
                                        <div class="form-text">Để trống nếu không muốn thay đổi mật khẩu</div>
                                    </div>
                                    
                                    <div class="mb-3">
                                        <label for="xac_nhan_mat_khau" class="form-label">Xác nhận mật khẩu mới</label>
                                        <div class="input-group">
                                            <input type="password" 
                                                   class="form-control" 
                                                   id="xac_nhan_mat_khau" 
                                                   name="xac_nhan_mat_khau" 
                                                   placeholder="Xác nhận mật khẩu mới">
                                            <button class="btn btn-outline-secondary" type="button" onclick="togglePassword('xac_nhan_mat_khau')">
                                                <i class="fas fa-eye"></i>
                                            </button>
                                        </div>
                                    </div>
                                    
                                    <div class="mb-3">
                                        <label class="form-label">Mật khẩu hiện tại</label>
                                        <div class="input-group">
                                            <input type="password" 
                                                   class="form-control" 
                                                   id="current_password"
                                                   value="<?= $user['mat_khau'] ?>" 
                                                   readonly>
                                            <button class="btn btn-outline-info" type="button" onclick="togglePassword('current_password')">
                                                <i class="fas fa-eye"></i>
                                            </button>
                                        </div>
                                        <div class="form-text text-info">Mật khẩu hiện tại để tham khảo</div>
                                    </div>
                                    
                                    <div class="mb-3">
                                        <label for="vai_tro" class="form-label">Vai trò <span class="text-danger">*</span></label>
                                        <select class="form-select" id="vai_tro" name="vai_tro" required
                                                <?= $id == $_SESSION['admin_id'] ? 'disabled' : '' ?>>
                                            <option value="khach_hang" <?= ($_POST['vai_tro'] ?? '') == 'khach_hang' ? 'selected' : '' ?>>
                                                Khách hàng
                                            </option>
                                            <option value="nhan_vien" <?= ($_POST['vai_tro'] ?? '') == 'nhan_vien' ? 'selected' : '' ?>>
                                                Nhân viên
                                            </option>
                                            <option value="admin" <?= ($_POST['vai_tro'] ?? '') == 'admin' ? 'selected' : '' ?>>
                                                Admin
                                            </option>
                                        </select>
                                        <?php if ($id == $_SESSION['admin_id']): ?>
                                            <div class="form-text text-warning">
                                                <i class="fas fa-lock"></i> Không thể thay đổi vai trò của chính mình
                                            </div>
                                            <input type="hidden" name="vai_tro" value="<?= $user['vai_tro'] ?>">
                                        <?php endif; ?>
                                    </div>
                                    
                                    <div class="mb-3">
                                        <label for="trang_thai" class="form-label">Trạng thái</label>
                                        <select class="form-select" id="trang_thai" name="trang_thai"
                                                <?= $id == $_SESSION['admin_id'] ? 'disabled' : '' ?>>
                                            <option value="hoat_dong" <?= ($_POST['trang_thai'] ?? '') == 'hoat_dong' ? 'selected' : '' ?>>
                                                Hoạt động
                                            </option>
                                            <option value="chua_kich_hoat" <?= ($_POST['trang_thai'] ?? '') == 'chua_kich_hoat' ? 'selected' : '' ?>>
                                                Chưa kích hoạt
                                            </option>
                                            <option value="bi_khoa" <?= ($_POST['trang_thai'] ?? '') == 'bi_khoa' ? 'selected' : '' ?>>
                                                Bị khóa
                                            </option>
                                        </select>
                                        <?php if ($id == $_SESSION['admin_id']): ?>
                                            <div class="form-text text-warning">
                                                <i class="fas fa-lock"></i> Không thể thay đổi trạng thái của chính mình
                                            </div>
                                            <input type="hidden" name="trang_thai" value="<?= $user['trang_thai'] ?>">
                                        <?php endif; ?>
                                    </div>
                                </div>
                            </div>
                        </div>
                        
                        <!-- Thông tin cá nhân -->
                        <div class="col-md-6">
                            <div class="card mb-3">
                                <div class="card-header">
                                    <h5><i class="fas fa-user-circle me-2"></i>Thông tin cá nhân</h5>
                                </div>
                                <div class="card-body">
                                    <div class="mb-3">
                                        <label for="ho_ten" class="form-label">Họ và tên <span class="text-danger">*</span></label>
                                        <input type="text" 
                                               class="form-control" 
                                               id="ho_ten" 
                                               name="ho_ten" 
                                               value="<?= htmlspecialchars($_POST['ho_ten'] ?? '') ?>"
                                               required>
                                    </div>
                                    
                                    <div class="mb-3">
                                        <label for="email" class="form-label">Email <span class="text-danger">*</span></label>
                                        <input type="email" 
                                               class="form-control" 
                                               id="email" 
                                               name="email" 
                                               value="<?= htmlspecialchars($_POST['email'] ?? '') ?>"
                                               required>
                                    </div>
                                    
                                    <div class="mb-3">
                                        <label for="so_dien_thoai" class="form-label">Số điện thoại</label>
                                        <input type="tel" 
                                               class="form-control" 
                                               id="so_dien_thoai" 
                                               name="so_dien_thoai" 
                                               value="<?= htmlspecialchars($_POST['so_dien_thoai'] ?? '') ?>"
                                               pattern="[0-9]{10,11}">
                                    </div>
                                    
                                    <div class="mb-3">
                                        <label for="ngay_sinh" class="form-label">Ngày sinh</label>
                                        <input type="date" 
                                               class="form-control" 
                                               id="ngay_sinh" 
                                               name="ngay_sinh" 
                                               value="<?= $_POST['ngay_sinh'] ?? '' ?>">
                                    </div>
                                    
                                    <div class="mb-3">
                                        <label for="gioi_tinh" class="form-label">Giới tính</label>
                                        <select class="form-select" id="gioi_tinh" name="gioi_tinh">
                                            <option value="khac" <?= ($_POST['gioi_tinh'] ?? '') == 'khac' ? 'selected' : '' ?>>
                                                Khác
                                            </option>
                                            <option value="nam" <?= ($_POST['gioi_tinh'] ?? '') == 'nam' ? 'selected' : '' ?>>
                                                Nam
                                            </option>
                                            <option value="nu" <?= ($_POST['gioi_tinh'] ?? '') == 'nu' ? 'selected' : '' ?>>
                                                Nữ
                                            </option>
                                        </select>
                                    </div>
                                    
                                    <div class="mb-3">
                                        <label for="dia_chi" class="form-label">Địa chỉ</label>
                                        <textarea class="form-control" 
                                                  id="dia_chi" 
                                                  name="dia_chi" 
                                                  rows="3"><?= htmlspecialchars($_POST['dia_chi'] ?? '') ?></textarea>
                                    </div>
                                </div>
                            </div>
                            
                            <!-- Thống kê -->
                            <?php if ($user['vai_tro'] == 'khach_hang'): ?>
                                <?php
                                $customer_stats = $pdo->prepare("
                                    SELECT 
                                        COUNT(DISTINCT dh.id) as so_don_hang,
                                        SUM(dh.tong_thanh_toan) as tong_chi_tieu,
                                        COUNT(DISTINCT dgsp.id) as so_danh_gia
                                    FROM nguoi_dung nd
                                    LEFT JOIN don_hang dh ON nd.id = dh.khach_hang_id AND dh.trang_thai_don_hang = 'da_giao'
                                    LEFT JOIN danh_gia_san_pham dgsp ON nd.id = dgsp.khach_hang_id
                                    WHERE nd.id = ?
                                ");
                                $customer_stats->execute([$id]);
                                $stats = $customer_stats->fetch();
                                ?>
                                <div class="card">
                                    <div class="card-header">
                                        <h5><i class="fas fa-chart-bar me-2"></i>Thống kê khách hàng</h5>
                                    </div>
                                    <div class="card-body">
                                        <div class="row text-center">
                                            <div class="col-4">
                                                <div class="h4 text-primary"><?= $stats['so_don_hang'] ?></div>
                                                <small class="text-muted">Đơn hàng</small>
                                            </div>
                                            <div class="col-4">
                                                <div class="h4 text-success"><?= formatPrice($stats['tong_chi_tieu'] ?: 0) ?></div>
                                                <small class="text-muted">Tổng chi tiêu</small>
                                            </div>
                                            <div class="col-4">
                                                <div class="h4 text-warning"><?= $stats['so_danh_gia'] ?></div>
                                                <small class="text-muted">Đánh giá</small>
                                            </div>
                                        </div>
                                        
                                        <?php if ($stats['so_don_hang'] > 0): ?>
                                            <hr>
                                            <div class="text-center">
                                                <a href="<?= adminUrl('orders/?khach_hang_id=' . $id) ?>" class="btn btn-sm btn-outline-primary">
                                                    <i class="fas fa-shopping-cart"></i> Xem đơn hàng
                                                </a>
                                            </div>
                                        <?php endif; ?>
                                    </div>
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>
                    
                    <!-- Nút hành động -->
                    <div class="card">
                        <div class="card-body">
                            <div class="d-flex gap-2 justify-content-center">
                                <button type="submit" class="btn btn-primary btn-lg px-4">
                                    <i class="fas fa-save me-2"></i> Cập nhật người dùng
                                </button>
                                <a href="<?= adminUrl('users/') ?>" class="btn btn-secondary btn-lg px-4">
                                    <i class="fas fa-times me-2"></i> Hủy
                                </a>
                                <?php if ($id != $_SESSION['admin_id']): ?>
                                    <a href="<?= adminUrl('users/?delete=' . $id) ?>" 
                                       class="btn btn-danger btn-lg px-4"
                                       onclick="return confirm('Bạn có chắc muốn xóa người dùng này?')">
                                        <i class="fas fa-trash me-2"></i> Xóa
                                    </a>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        // Toggle password visibility
        function togglePassword(fieldId) {
            const field = document.getElementById(fieldId);
            const button = field.nextElementSibling.querySelector('i');
            
            if (field.type === 'password') {
                field.type = 'text';
                button.classList.remove('fa-eye');
                button.classList.add('fa-eye-slash');
            } else {
                field.type = 'password';
                button.classList.remove('fa-eye-slash');
                button.classList.add('fa-eye');
            }
        }
        
        // Validate form
        document.querySelector('form').addEventListener('submit', function(e) {
            const password = document.getElementById('mat_khau').value;
            const confirmPassword = document.getElementById('xac_nhan_mat_khau').value;
            
            if (password && password !== confirmPassword) {
                e.preventDefault();
                alert('Mật khẩu xác nhận không khớp!');
                return false;
            }
        });
        
        // Show/hide confirm password when password is entered
        document.getElementById('mat_khau').addEventListener('input', function() {
            const confirmPasswordField = document.getElementById('xac_nhan_mat_khau');
            const confirmLabel = confirmPasswordField.closest('.mb-3').querySelector('label');
            
            if (this.value) {
                confirmPasswordField.required = true;
                confirmLabel.innerHTML = 'Xác nhận mật khẩu mới <span class="text-danger">*</span>';
            } else {
                confirmPasswordField.required = false;
                confirmPasswordField.value = '';
                confirmLabel.innerHTML = 'Xác nhận mật khẩu mới';
            }
        });
        
        // Warning for self-editing
        <?php if ($id == $_SESSION['admin_id']): ?>
        document.addEventListener('DOMContentLoaded', function() {
            const alertDiv = document.createElement('div');
            alertDiv.className = 'alert alert-info mb-3';
            alertDiv.innerHTML = '<i class="fas fa-info-circle me-2"></i>Bạn đang chỉnh sửa tài khoản của chính mình. Một số trường bị khóa để bảo mật.';
            
            const form = document.querySelector('form');
            form.parentNode.insertBefore(alertDiv, form);
        });
        <?php endif; ?>
    </script>
</body>
</html>