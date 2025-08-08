<?php
// customer/login.php
/**
 * Đăng nhập khách hàng - Form đăng nhập cho khách hàng
 * Chức năng: Xác thực thông tin, tạo session, chuyển hướng
 */

require_once '../config/database.php';
require_once '../config/config.php';

// Nếu đã đăng nhập thì chuyển hướng về trang chủ
if (isset($_SESSION['customer_id'])) {
    redirect('/customer/');
}

$errors = [];
$email = '';

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $email = trim($_POST['email'] ?? '');
    $password = $_POST['password'] ?? '';
    $remember = isset($_POST['remember_me']);
    
    // Validate
    if (empty($email)) {
        $errors[] = 'Email không được để trống';
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $errors[] = 'Email không hợp lệ';
    }
    
    if (empty($password)) {
        $errors[] = 'Mật khẩu không được để trống';
    }
    
    // Kiểm tra thông tin đăng nhập
    if (empty($errors)) {
        $stmt = $pdo->prepare("
            SELECT id, ten_dang_nhap, mat_khau, ho_ten, email, trang_thai 
            FROM nguoi_dung 
            WHERE email = ? AND vai_tro = 'khach_hang'
        ");
        $stmt->execute([$email]);
        $user = $stmt->fetch();
        
        if ($user && password_verify($password, $user['mat_khau'])) {
            if ($user['trang_thai'] == 'bi_khoa') {
                $errors[] = 'Tài khoản của bạn đã bị khóa. Vui lòng liên hệ hỗ trợ.';
            } elseif ($user['trang_thai'] == 'chua_kich_hoat') {
                $errors[] = 'Tài khoản chưa được kích hoạt. Vui lòng kiểm tra email để kích hoạt.';
            } else {
                // Đăng nhập thành công
                $_SESSION['customer_id'] = $user['id'];
                $_SESSION['customer_name'] = $user['ho_ten'];
                $_SESSION['customer_email'] = $user['email'];
                
                // Xử lý remember me
                if ($remember) {
                    $token = bin2hex(random_bytes(32));
                    setcookie('remember_token', $token, time() + (86400 * 30), '/', '', false, true); // 30 ngày
                    
                    // Lưu token vào database (cần tạo bảng remember_tokens)
                    // TODO: Implement remember token functionality
                }
                
                // Chuyển hướng về trang được yêu cầu hoặc trang chủ
                $redirect_url = $_SESSION['redirect_after_login'] ?? '/customer/';
                unset($_SESSION['redirect_after_login']);
                redirect($redirect_url);
            }
        } else {
            $errors[] = 'Email hoặc mật khẩu không chính xác';
        }
    }
}
?>

<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Đăng nhập - <?= SITE_NAME ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <style>
        .login-container {
            min-height: 100vh;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
        }
        
        .login-card {
            background: white;
            border-radius: 15px;
            box-shadow: 0 10px 30px rgba(0,0,0,0.1);
            overflow: hidden;
        }
        
        .login-header {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            padding: 40px;
            text-align: center;
        }
        
        .login-body {
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
        
        .social-login {
            border: 1px solid #dee2e6;
            border-radius: 25px;
            padding: 10px 20px;
            transition: all 0.3s ease;
        }
        
        .social-login:hover {
            background: #f8f9fa;
            transform: translateY(-2px);
        }
        
        .divider {
            text-align: center;
            margin: 30px 0;
            position: relative;
        }
        
        .divider::before {
            content: '';
            position: absolute;
            top: 50%;
            left: 0;
            right: 0;
            height: 1px;
            background: #dee2e6;
        }
        
        .divider span {
            background: white;
            padding: 0 20px;
            color: #6c757d;
        }
    </style>
</head>
<body>
    <div class="login-container d-flex align-items-center justify-content-center">
        <div class="container">
            <div class="row justify-content-center">
                <div class="col-lg-5 col-md-7">
                    <div class="login-card">
                        <div class="login-header">
                            <h3 class="mb-3">
                                <i class="fas fa-store me-2"></i>
                                <?= SITE_NAME ?>
                            </h3>
                            <h4>Đăng nhập tài khoản</h4>
                            <p class="mb-0 opacity-75">Chào mừng bạn quay trở lại!</p>
                        </div>
                        
                        <div class="login-body">
                            <?php if (!empty($errors)): ?>
                                <div class="alert alert-danger">
                                    <ul class="mb-0">
                                        <?php foreach ($errors as $error): ?>
                                            <li><?= $error ?></li>
                                        <?php endforeach; ?>
                                    </ul>
                                </div>
                            <?php endif; ?>
                            
                            <form method="POST">
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
                                               value="<?= htmlspecialchars($email) ?>"
                                               placeholder="Nhập email của bạn"
                                               required>
                                    </div>
                                </div>
                                
                                <div class="mb-3">
                                    <label for="password" class="form-label">Mật khẩu <span class="text-danger">*</span></label>
                                    <div class="input-group">
                                        <span class="input-group-text">
                                            <i class="fas fa-lock"></i>
                                        </span>
                                        <input type="password" 
                                               class="form-control" 
                                               id="password" 
                                               name="password" 
                                               placeholder="Nhập mật khẩu"
                                               required>
                                        <button class="btn btn-outline-secondary" type="button" id="togglePassword">
                                            <i class="fas fa-eye"></i>
                                        </button>
                                    </div>
                                </div>
                                
                                <div class="d-flex justify-content-between align-items-center mb-4">
                                    <div class="form-check">
                                        <input class="form-check-input" type="checkbox" id="remember_me" name="remember_me">
                                        <label class="form-check-label" for="remember_me">
                                            Ghi nhớ đăng nhập
                                        </label>
                                    </div>
                                    <a href="/tktshop/customer/forgot_password.php" class="text-decoration-none">
                                        Quên mật khẩu?
                                    </a>
                                </div>
                                
                                <div class="d-grid">
                                    <button type="submit" class="btn btn-primary btn-lg">
                                        <i class="fas fa-sign-in-alt me-2"></i>
                                        Đăng nhập
                                    </button>
                                </div>
                            </form>
                            
                            <div class="divider">
                                <span>Hoặc đăng nhập với</span>
                            </div>
                            
                            <div class="row g-2 mb-4">
                                <div class="col-6">
                                    <button type="button" class="btn social-login w-100">
                                        <i class="fab fa-google text-danger me-2"></i>
                                        Google
                                    </button>
                                </div>
                                <div class="col-6">
                                    <button type="button" class="btn social-login w-100">
                                        <i class="fab fa-facebook text-primary me-2"></i>
                                        Facebook
                                    </button>
                                </div>
                            </div>
                            
                            <div class="text-center">
                                <p class="mb-0">
                                    Chưa có tài khoản? 
                                    <a href="/tktshop/customer/register.php" class="text-decoration-none fw-bold">
                                        Đăng ký ngay
                                    </a>
                                </p>
                            </div>
                            
                            <div class="text-center mt-3">
                                <a href="/tktshop/" class="text-muted text-decoration-none">
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
            const passwordField = document.getElementById('password');
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
        
        // Social login handlers (placeholder)
        document.querySelectorAll('.social-login').forEach(button => {
            button.addEventListener('click', function() {
                alert('Chức năng đăng nhập bằng mạng xã hội sẽ được phát triển trong phiên bản tiếp theo');
            });
        });
        
        // Auto focus on first input
        document.addEventListener('DOMContentLoaded', function() {
            document.getElementById('email').focus();
        });
        
        // Form validation
        document.querySelector('form').addEventListener('submit', function(e) {
            const email = document.getElementById('email').value;
            const password = document.getElementById('password').value;
            
            if (!email || !password) {
                e.preventDefault();
                alert('Vui lòng điền đầy đủ thông tin');
                return false;
            }
            
            if (!email.includes('@')) {
                e.preventDefault();
                alert('Email không hợp lệ');
                return false;
            }
        });
    </script>
</body>
</html>