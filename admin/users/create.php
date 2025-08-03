<?php
// admin/users/create.php - ĐÃ SỬA ĐƯỜNG DẪN
/**
 * Thêm người dùng mới (không mã hóa password)
 */

require_once '../../config/database.php';
require_once '../../config/config.php';

requireLogin();

$errors = [];

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
    
    if (empty($mat_khau)) {
        $errors[] = 'Mật khẩu không được để trống';
    } elseif (strlen($mat_khau) < 6) {
        $errors[] = 'Mật khẩu phải có ít nhất 6 ký tự';
    }
    
    if ($mat_khau !== $xac_nhan_mat_khau) {
        $errors[] = 'Xác nhận mật khẩu không khớp';
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
    
    // Kiểm tra trùng lặp
    if (!empty($ten_dang_nhap)) {
        $check = $pdo->prepare("SELECT id FROM nguoi_dung WHERE ten_dang_nhap = ?");
        $check->execute([$ten_dang_nhap]);
        if ($check->fetch()) {
            $errors[] = 'Tên đăng nhập đã tồn tại';
        }
    }
    
    if (!empty($email)) {
        $check = $pdo->prepare("SELECT id FROM nguoi_dung WHERE email = ?");
        $check->execute([$email]);
        if ($check->fetch()) {
            $errors[] = 'Email đã tồn tại';
        }
    }
    
    // Lưu vào database (không mã hóa password)
    if (empty($errors)) {
        $stmt = $pdo->prepare("
            INSERT INTO nguoi_dung 
            (ten_dang_nhap, mat_khau, ho_ten, email, so_dien_thoai, dia_chi, 
             ngay_sinh, gioi_tinh, vai_tro, trang_thai) 
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
        ");
        
        if ($stmt->execute([
            $ten_dang_nhap, $mat_khau, $ho_ten, $email, $so_dien_thoai, 
            $dia_chi, $ngay_sinh ?: null, $gioi_tinh, $vai_tro, $trang_thai
        ])) {
            alert('Thêm người dùng thành công!', 'success');
            redirect('admin/users/'); // ĐÃ SỬA
        } else {
            $errors[] = 'Lỗi khi lưu dữ liệu';
        }
    }
}
?>

<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Thêm người dùng - <?= SITE_NAME ?></title>
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
                    <h2>Thêm người dùng mới</h2>
                    <a href="<?= adminUrl('users/') ?>" class="btn btn-secondary">
                        <i class="fas fa-arrow-left"></i> Quay lại
                    </a>
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
                                               placeholder="Nhập tên đăng nhập"
                                               required>
                                        <div class="form-text">Tối thiểu 3 ký tự, không có khoảng trắng</div>
                                    </div>
                                    
                                    <div class="mb-3">
                                        <label for="mat_khau" class="form-label">Mật khẩu <span class="text-danger">*</span></label>
                                        <div class="input-group">
                                            <input type="password" 
                                                   class="form-control" 
                                                   id="mat_khau" 
                                                   name="mat_khau" 
                                                   placeholder="Nhập mật khẩu"
                                                   required>
                                            <button class="btn btn-outline-secondary" type="button" onclick="togglePassword('mat_khau')">
                                                <i class="fas fa-eye"></i>
                                            </button>
                                        </div>
                                        <div class="form-text">Tối thiểu 6 ký tự</div>
                                    </div>
                                    
                                    <div class="mb-3">
                                        <label for="xac_nhan_mat_khau" class="form-label">Xác nhận mật khẩu <span class="text-danger">*</span></label>
                                        <div class="input-group">
                                            <input type="password" 
                                                   class="form-control" 
                                                   id="xac_nhan_mat_khau" 
                                                   name="xac_nhan_mat_khau" 
                                                   placeholder="Nhập lại mật khẩu"
                                                   required>
                                            <button class="btn btn-outline-secondary" type="button" onclick="togglePassword('xac_nhan_mat_khau')">
                                                <i class="fas fa-eye"></i>
                                            </button>
                                        </div>
                                    </div>
                                    
                                    <div class="mb-3">
                                        <label for="vai_tro" class="form-label">Vai trò <span class="text-danger">*</span></label>
                                        <select class="form-select" id="vai_tro" name="vai_tro" required>
                                            <option value="khach_hang" <?= ($_POST['vai_tro'] ?? 'khach_hang') == 'khach_hang' ? 'selected' : '' ?>>
                                                Khách hàng
                                            </option>
                                            <option value="nhan_vien" <?= ($_POST['vai_tro'] ?? '') == 'nhan_vien' ? 'selected' : '' ?>>
                                                Nhân viên
                                            </option>
                                            <option value="admin" <?= ($_POST['vai_tro'] ?? '') == 'admin' ? 'selected' : '' ?>>
                                                Admin
                                            </option>
                                        </select>
                                    </div>
                                    
                                    <div class="mb-3">
                                        <label for="trang_thai" class="form-label">Trạng thái</label>
                                        <select class="form-select" id="trang_thai" name="trang_thai">
                                            <option value="hoat_dong" <?= ($_POST['trang_thai'] ?? 'hoat_dong') == 'hoat_dong' ? 'selected' : '' ?>>
                                                Hoạt động
                                            </option>
                                            <option value="chua_kich_hoat" <?= ($_POST['trang_thai'] ?? '') == 'chua_kich_hoat' ? 'selected' : '' ?>>
                                                Chưa kích hoạt
                                            </option>
                                            <option value="bi_khoa" <?= ($_POST['trang_thai'] ?? '') == 'bi_khoa' ? 'selected' : '' ?>>
                                                Bị khóa
                                            </option>
                                        </select>
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
                                               placeholder="Nhập họ và tên đầy đủ"
                                               required>
                                    </div>
                                    
                                    <div class="mb-3">
                                        <label for="email" class="form-label">Email <span class="text-danger">*</span></label>
                                        <input type="email" 
                                               class="form-control" 
                                               id="email" 
                                               name="email" 
                                               value="<?= htmlspecialchars($_POST['email'] ?? '') ?>"
                                               placeholder="example@email.com"
                                               required>
                                    </div>
                                    
                                    <div class="mb-3">
                                        <label for="so_dien_thoai" class="form-label">Số điện thoại</label>
                                        <input type="tel" 
                                               class="form-control" 
                                               id="so_dien_thoai" 
                                               name="so_dien_thoai" 
                                               value="<?= htmlspecialchars($_POST['so_dien_thoai'] ?? '') ?>"
                                               placeholder="0987654321"
                                               pattern="[0-9]{10,11}">
                                        <div class="form-text">10-11 chữ số</div>
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
                                            <option value="khac" <?= ($_POST['gioi_tinh'] ?? 'khac') == 'khac' ? 'selected' : '' ?>>
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
                                                  rows="3"
                                                  placeholder="Nhập địa chỉ đầy đủ..."><?= htmlspecialchars($_POST['dia_chi'] ?? '') ?></textarea>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                    
                    <!-- Nút hành động -->
                    <div class="card">
                        <div class="card-body">
                            <div class="d-flex gap-2 justify-content-center">
                                <button type="submit" class="btn btn-primary btn-lg px-4">
                                    <i class="fas fa-save me-2"></i> Lưu người dùng
                                </button>
                                <a href="<?= adminUrl('users/') ?>" class="btn btn-secondary btn-lg px-4">
                                    <i class="fas fa-times me-2"></i> Hủy
                                </a>
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
            
            if (password !== confirmPassword) {
                e.preventDefault();
                alert('Mật khẩu xác nhận không khớp!');
                return false;
            }
        });
        
        // Real-time password match check
        document.getElementById('xac_nhan_mat_khau').addEventListener('input', function() {
            const password = document.getElementById('mat_khau').value;
            const confirmPassword = this.value;
            
            if (confirmPassword && password !== confirmPassword) {
                this.setCustomValidity('Mật khẩu không khớp');
                this.classList.add('is-invalid');
            } else {
                this.setCustomValidity('');
                this.classList.remove('is-invalid');
            }
        });
        
        // Auto-generate username from name
        document.getElementById('ho_ten').addEventListener('input', function() {
            const usernameField = document.getElementById('ten_dang_nhap');
            if (!usernameField.value) {
                let username = this.value.toLowerCase()
                    .replace(/[áàảãạâấầẩẫậăắằẳẵặ]/g, 'a')
                    .replace(/[éèẻẽẹêếềểễệ]/g, 'e')
                    .replace(/[íìỉĩị]/g, 'i')
                    .replace(/[óòỏõọôốồổỗộơớờởỡợ]/g, 'o')
                    .replace(/[úùủũụưứừửữự]/g, 'u')
                    .replace(/[ýỳỷỹỵ]/g, 'y')
                    .replace(/đ/g, 'd')
                    .replace(/[^a-z0-9]/g, '')
                    .substring(0, 20);
                
                if (username.length >= 3) {
                    usernameField.value = username;
                }
            }
        });
    </script>
</body>
</html>