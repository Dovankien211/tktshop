<?php
// customer/register.php
/**
 * Đăng ký tài khoản khách hàng
 */

require_once '../config/database.php';
require_once '../config/config.php';

// Nếu đã đăng nhập thì chuyển hướng về trang chủ
if (isset($_SESSION['customer_id'])) {
    redirect('/customer/');
}

$errors = [];

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $ho_ten = trim($_POST['ho_ten'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $so_dien_thoai = trim($_POST['so_dien_thoai'] ?? '');
    $mat_khau = $_POST['mat_khau'] ?? '';
    $xac_nhan_mat_khau = $_POST['xac_nhan_mat_khau'] ?? '';
    $dong_y_dieu_khoan = isset($_POST['dong_y_dieu_khoan']);
    
    // Validate
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
    
    if (empty($mat_khau)) {
        $errors[] = 'Mật khẩu không được để trống';
    } elseif (strlen($mat_khau) < 6) {
        $errors[] = 'Mật khẩu phải có ít nhất 6 ký tự';
    }
    
    if ($mat_khau !== $xac_nhan_mat_khau) {
        $errors[] = 'Xác nhận mật khẩu không khớp';
    }
    
    if (!$dong_y_dieu_khoan) {
        $errors[] = 'Bạn phải đồng ý với điều khoản sử dụng';
    }
    
    // Kiểm tra email đã tồn tại chưa
    if (!empty($email)) {
        $check = $pdo->prepare("SELECT id FROM nguoi_dung WHERE email = ?");
        $check->execute([$email]);
        if ($check->fetch()) {
            $errors[] = 'Email đã được sử dụng';
        }
    }
    
    // Tạo tài khoản
    if (empty($errors)) {
        try {
            $ten_dang_nhap = strtolower(explode('@', $email)[0]) . '_' . time();
            $hashed_password = password_hash($mat_khau, PASSWORD_DEFAULT);
            
            $stmt = $pdo->prepare("
                INSERT INTO nguoi_dung 
                (ten_dang_nhap, mat_khau, ho_ten, email, so_dien_thoai, vai_tro, trang_thai) 
                VALUES (?, ?, ?, ?, ?, 'khach_hang', 'hoat_dong')
            ");
            
            if ($stmt->execute([$ten_dang_nhap, $hashed_password, $ho_ten, $email, $so_dien_thoai])) {
                alert('Đăng ký thành công! Bạn có thể đăng nhập ngay bây giờ.', 'success');
                redirect('/customer/login.php');
            } else {
                $errors[] = 'Có lỗi xảy ra khi tạo tài khoản';
            }
        } catch (Exception $e) {
            $errors[] = 'Có lỗi xảy ra khi tạo tài khoản';
        }
    }
}
?>

<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Đăng ký tài khoản - <?= SITE_NAME ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <style>
        .register-container {
            min-height: 100vh;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
        }
        
        .register-card {
            background: white;
            border-radius: 15px;
            box-shadow: 0 10px 30px rgba(0,0,0,0.1);
            overflow: hidden;
        }
        
        .register-header {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            padding: 40px;
            text-align: center;
        }
        
        .register-body {
            padding: 40px;
        }
        
        .form-control:focus {
            border-color: #667eea;
            box-shadow: 0 0 0 0.2rem rgba(102, 126, 234, 0.25);
        }
        
        .btn-primary {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            border: none;
            padding: 12px 30px;
            border-radius: 25px;
        }
        
        .btn-primary:hover {
            background: linear-gradient(135deg, #5a6fd8 0%, #6a4190 100%);
            transform: translateY(-2px);
            transition: all 0.3s ease;
        }
        
        .password-strength {
            height: 5px;
            border-radius: 3px;
            transition: all 0.3s;
        }
        
        .strength-weak { background: #dc3545; }
        .strength-medium { background: #ffc107; }
        .strength-strong { background: #28a745; }
    </style>
</head>
<body>
    <div class="register-container d-flex align-items-center justify-content-center">
        <div class="container">
            <div class="row justify-content-center">
                <div class="col-lg-6 col-md-8">
                    <div class="register-card">
                        <div class="register-header">
                            <h3 class="mb-3">
                                <i class="fas fa-store me-2"></i>
                                <?= SITE_NAME ?>
                            </h3>
                            <h4>Tạo tài khoản mới</h4>
                            <p class="mb-0 opacity-75">Trở thành thành viên để nhận ưu đãi đặc biệt!</p>
                        </div>
                        
                        <div class="register-body">
                            <?php if (!empty($errors)): ?>
                                <div class="alert alert-danger">
                                    <ul class="mb-0">
                                        <?php foreach ($errors as $error): ?>
                                            <li><?= $error ?></li>
                                        <?php endforeach; ?>
                                    </ul>
                                </div>
                            <?php endif; ?>
                            
                            <form method="POST" id="registerForm">
                                <div class="row">
                                    <div class="col-md-12">
                                        <div class="mb-3">
                                            <label for="ho_ten" class="form-label">Họ và tên <span class="text-danger">*</span></label>
                                            <div class="input-group">
                                                <span class="input-group-text">
                                                    <i class="fas fa-user"></i>
                                                </span>
                                                <input type="text" 
                                                       class="form-control" 
                                                       id="ho_ten" 
                                                       name="ho_ten" 
                                                       value="<?= htmlspecialchars($_POST['ho_ten'] ?? '') ?>"
                                                       placeholder="Nhập họ và tên đầy đủ"
                                                       required>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                                
                                <div class="row">
                                    <div class="col-md-6">
                                        <div class="mb-3">
                                            <label for="email" class="form-label">Email <span class="text-danger">*</span></label>
                                            <div class="input-group">
                                                <span class="input-group-text">
                                                    <i class="fas fa-envelope"></i>
                                                </span>
                                                <input type="email" 
                                                       class="form-control" 
                                                       id="email" 
                                                       name="email" 
                                                       value="<?= htmlspecialchars($_POST['email'] ?? '') ?>"
                                                       placeholder="Nhập email của bạn"
                                                       required>
                                            </div>
                                        </div>
                                    </div>
                                    <div class="col-md-6">
                                        <div class="mb-3">
                                            <label for="so_dien_thoai" class="form-label">Số điện thoại</label>
                                            <div class="input-group">
                                                <span class="input-group-text">
                                                    <i class="fas fa-phone"></i>
                                                </span>
                                                <input type="tel" 
                                                       class="form-control" 
                                                       id="so_dien_thoai" 
                                                       name="so_dien_thoai" 
                                                       value="<?= htmlspecialchars($_POST['so_dien_thoai'] ?? '') ?>"
                                                       placeholder="Số điện thoại (tuỳ chọn)"
                                                       pattern="[0-9]{10,11}">
                                            </div>
                                        </div>
                                    </div>
                                </div>
                                
                                <div class="row">
                                    <div class="col-md-6">
                                        <div class="mb-3">
                                            <label for="mat_khau" class="form-label">Mật khẩu <span class="text-danger">*</span></label>
                                            <div class="input-group">
                                                <span class="input-group-text">
                                                    <i class="fas fa-lock"></i>
                                                </span>
                                                <input type="password" 
                                                       class="form-control" 
                                                       id="mat_khau" 
                                                       name="mat_khau" 
                                                       placeholder="Nhập mật khẩu"
                                                       required>
                                                <button class="btn btn-outline-secondary" type="button" id="togglePassword">
                                                    <i class="fas fa-eye"></i>
                                                </button>
                                            </div>
                                            <div class="password-strength mt-2" id="passwordStrength"></div>
                                            <small class="text-muted">Tối thiểu 6 ký tự</small>
                                        </div>
                                    </div>
                                    <div class="col-md-6">
                                        <div class="mb-3">
                                            <label for="xac_nhan_mat_khau" class="form-label">Xác nhận mật khẩu <span class="text-danger">*</span></label>
                                            <div class="input-group">
                                                <span class="input-group-text">
                                                    <i class="fas fa-lock"></i>
                                                </span>
                                                <input type="password" 
                                                       class="form-control" 
                                                       id="xac_nhan_mat_khau" 
                                                       name="xac_nhan_mat_khau" 
                                                       placeholder="Nhập lại mật khẩu"
                                                       required>
                                            </div>
                                            <div id="passwordMatch" class="mt-1"></div>
                                        </div>
                                    </div>
                                </div>
                                
                                <div class="mb-4">
                                    <div class="form-check">
                                        <input class="form-check-input" type="checkbox" id="dong_y_dieu_khoan" name="dong_y_dieu_khoan" required>
                                        <label class="form-check-label" for="dong_y_dieu_khoan">
                                            Tôi đồng ý với 
                                            <a href="/customer/terms.php" target="_blank" class="text-decoration-none">
                                                Điều khoản sử dụng
                                            </a> 
                                            và 
                                            <a href="/customer/privacy.php" target="_blank" class="text-decoration-none">
                                                Chính sách bảo mật
                                            </a>
                                        </label>
                                    </div>
                                </div>
                                
                                <div class="d-grid">
                                    <button type="submit" class="btn btn-primary btn-lg">
                                        <i class="fas fa-user-plus me-2"></i>
                                        Tạo tài khoản
                                    </button>
                                </div>
                            </form>
                            
                            <div class="text-center mt-4">
                                <p class="mb-0">
                                    Đã có tài khoản? 
                                    <a href="login.php" class="text-decoration-none fw-bold">
                                        Đăng nhập ngay
                                    </a>
                                </p>
                            </div>
                            
                            <div class="text-center mt-3">
                                <a href="/" class="text-muted text-decoration-none">
                                    <i class="fas fa-arrow-left me-1"></i>
                                    Quay về trang chủ
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
        // Toggle password visibility
        document.getElementById('togglePassword').addEventListener('click', function() {
            const passwordField = document.getElementById('mat_khau');
            const icon = this.querySelector('i');
            
            if (passwordField.type === 'password') {
                passwordField.type = 'text';
                icon.classList.remove('fa-eye');
                icon.classList.add('fa-eye-slash');
            } else {
                passwordField.type = 'password';
                icon.classList.remove('fa-eye-slash');
                icon.classList.add('fa-eye');
            }
        });
        
        // Password strength checker
        document.getElementById('mat_khau').addEventListener('input', function() {
            const password = this.value;
            const strengthBar = document.getElementById('passwordStrength');
            
            let strength = 0;
            if (password.length >= 6) strength++;
            if (password.match(/[a-z]+/)) strength++;
            if (password.match(/[A-Z]+/)) strength++;
            if (password.match(/[0-9]+/)) strength++;
            if (password.match(/[^a-zA-Z0-9]+/)) strength++;
            
            strengthBar.className = 'password-strength mt-2';
            if (strength <= 2) {
                strengthBar.classList.add('strength-weak');
            } else if (strength <= 3) {
                strengthBar.classList.add('strength-medium');
            } else {
                strengthBar.classList.add('strength-strong');
            }
        });
        
        // Password match checker
        document.getElementById('xac_nhan_mat_khau').addEventListener('input', function() {
            const password = document.getElementById('mat_khau').value;
            const confirmPassword = this.value;
            const matchDiv = document.getElementById('passwordMatch');
            
            if (confirmPassword === '') {
                matchDiv.innerHTML = '';
                return;
            }
            
            if (password === confirmPassword) {
                matchDiv.innerHTML = '<small class="text-success"><i class="fas fa-check"></i> Mật khẩu khớp</small>';
            } else {
                matchDiv.innerHTML = '<small class="text-danger"><i class="fas fa-times"></i> Mật khẩu không khớp</small>';
            }
        });
        
        // Form validation
        document.getElementById('registerForm').addEventListener('submit', function(e) {
            const password = document.getElementById('mat_khau').value;
            const confirmPassword = document.getElementById('xac_nhan_mat_khau').value;
            const terms = document.getElementById('dong_y_dieu_khoan').checked;
            
            if (password !== confirmPassword) {
                e.preventDefault();
                alert('Mật khẩu xác nhận không khớp!');
                return false;
            }
            
            if (!terms) {
                e.preventDefault();
                alert('Bạn phải đồng ý với điều khoản sử dụng!');
                return false;
            }
            
            if (password.length < 6) {
                e.preventDefault();
                alert('Mật khẩu phải có ít nhất 6 ký tự!');
                return false;
            }
        });
        
        // Auto focus on first input
        document.addEventListener('DOMContentLoaded', function() {
            document.getElementById('ho_ten').focus();
        });
        
        // Phone number formatting
        document.getElementById('so_dien_thoai').addEventListener('input', function() {
            this.value = this.value.replace(/[^0-9]/g, '');
        });
    </script>
</body>
</html>