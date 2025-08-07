<?php
// admin/login.php
/**
 * Trang đăng nhập admin
 */

require_once '../config/database.php';
require_once '../config/config.php';

$errors = [];
$success = false;

// Kiểm tra đã đăng nhập chưa
if (isset($_SESSION['admin_id'])) {
    redirect('/tktshop/admin/');
}

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $ten_dang_nhap = trim($_POST['ten_dang_nhap'] ?? '');
    $mat_khau = $_POST['mat_khau'] ?? '';
    
    // Validate
    if (empty($ten_dang_nhap)) {
        $errors[] = 'Tên đăng nhập không được để trống';
    }
    
    if (empty($mat_khau)) {
        $errors[] = 'Mật khẩu không được để trống';
    }
    
    // Đăng nhập
    if (empty($errors)) {
        try {
            $stmt = $pdo->prepare("
                SELECT * FROM nguoi_dung 
                WHERE (ten_dang_nhap = ? OR email = ?) 
                AND trang_thai = 'hoat_dong'
                AND vai_tro IN ('admin', 'nhan_vien')
            ");
            $stmt->execute([$ten_dang_nhap, $ten_dang_nhap]);
            $user = $stmt->fetch();
            
            // Kiểm tra mật khẩu (không mã hóa - chỉ so sánh trực tiếp)
            if ($user && $user['mat_khau'] === $mat_khau) {
                // Đăng nhập thành công
                $_SESSION['admin_id'] = $user['id'];
                $_SESSION['admin_name'] = $user['ho_ten'];
                $_SESSION['admin_role'] = $user['vai_tro'];
                $_SESSION['admin_username'] = $user['ten_dang_nhap'];
                $_SESSION['admin_email'] = $user['email'];
                
                // Log hoạt động đăng nhập
                if (function_exists('logActivity')) {
                    logActivity('Admin login', [
                        'user_id' => $user['id'],
                        'username' => $user['ten_dang_nhap'],
                        'ip' => getClientIP()
                    ]);
                }
                
                alert('Đăng nhập thành công!', 'success');
                redirect('/tktshop/admin/'); // Chuyển về admin dashboard
            } else {
                $errors[] = 'Tên đăng nhập hoặc mật khẩu không đúng';
            }
        } catch (PDOException $e) {
            $errors[] = 'Lỗi hệ thống: ' . $e->getMessage();
            if (function_exists('logError')) {
                logError('Admin login error: ' . $e->getMessage());
            }
        }
    }
}
?>

<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Login - <?= SITE_NAME ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <style>
        body {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            min-height: 100vh;
            font-family: 'Inter', sans-serif;
        }
        .login-container {
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
        }
        .login-card {
            background: rgba(255, 255, 255, 0.95);
            backdrop-filter: blur(20px);
            border: none;
            box-shadow: 0 20px 40px rgba(0, 0, 0, 0.15);
            border-radius: 20px;
            overflow: hidden;
        }
        .login-header {
            background: linear-gradient(45deg, #667eea, #764ba2);
            color: white;
            padding: 2rem;
            text-align: center;
        }
        .login-body {
            padding: 2rem;
        }
        .form-control {
            border-radius: 12px;
            border: 2px solid #e9ecef;
            padding: 12px 16px;
            transition: all 0.3s ease;
        }
        .form-control:focus {
            border-color: #667eea;
            box-shadow: 0 0 0 0.2rem rgba(102, 126, 234, 0.25);
        }
        .btn-primary {
            background: linear-gradient(45deg, #667eea, #764ba2);
            border: none;
            border-radius: 12px;
            padding: 12px 24px;
            font-weight: 600;
            transition: all 0.3s ease;
        }
        .btn-primary:hover {
            transform: translateY(-2px);
            box-shadow: 0 8px 25px rgba(102, 126, 234, 0.4);
        }
        .input-group {
            border-radius: 12px;
            overflow: hidden;
        }
        .input-group-text {
            background: #f8f9fa;
            border: 2px solid #e9ecef;
            border-right: none;
        }
        .demo-accounts {
            background: #f8f9fa;
            border-radius: 12px;
            padding: 1rem;
            margin-top: 1rem;
        }
        .admin-badge {
            background: linear-gradient(45deg, #ff6b6b, #ee5a24);
            color: white;
            padding: 4px 12px;
            border-radius: 20px;
            font-size: 0.75rem;
            font-weight: 600;
        }
    </style>
</head>
<body>
    <div class="login-container">
        <div class="container">
            <div class="row justify-content-center">
                <div class="col-lg-5 col-md-7 col-sm-9">
                    <div class="card login-card">
                        <div class="login-header">
                            <div class="mb-3">
                                <i class="fas fa-shield-alt fa-3x"></i>
                            </div>
                            <h2 class="mb-2">Admin Panel</h2>
                            <p class="mb-0 opacity-90"><?= SITE_NAME ?> Management System</p>
                            <span class="admin-badge mt-2 d-inline-block">
                                <i class="fas fa-crown me-1"></i>Administrator Only
                            </span>
                        </div>

                        <div class="login-body">
                            <?php if (!empty($errors)): ?>
                                <div class="alert alert-danger alert-dismissible fade show" role="alert">
                                    <i class="fas fa-exclamation-triangle me-2"></i>
                                    <ul class="mb-0 list-unstyled">
                                        <?php foreach ($errors as $error): ?>
                                            <li><?= htmlspecialchars($error) ?></li>
                                        <?php endforeach; ?>
                                    </ul>
                                    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                                </div>
                            <?php endif; ?>

                            <?php showAlert(); ?>

                            <form method="POST" action="<?= $_SERVER['PHP_SELF'] ?>" id="loginForm">
                                <div class="mb-3">
                                    <label for="ten_dang_nhap" class="form-label fw-semibold">
                                        <i class="fas fa-user me-2 text-primary"></i>Tên đăng nhập hoặc Email
                                    </label>
                                    <div class="input-group">
                                        <span class="input-group-text">
                                            <i class="fas fa-at text-muted"></i>
                                        </span>
                                        <input type="text" 
                                               class="form-control" 
                                               id="ten_dang_nhap" 
                                               name="ten_dang_nhap" 
                                               value="<?= htmlspecialchars($_POST['ten_dang_nhap'] ?? '') ?>"
                                               placeholder="Nhập tên đăng nhập hoặc email"
                                               required
                                               autocomplete="username">
                                    </div>
                                </div>
                                
                                <div class="mb-4">
                                    <label for="mat_khau" class="form-label fw-semibold">
                                        <i class="fas fa-lock me-2 text-primary"></i>Mật khẩu
                                    </label>
                                    <div class="input-group">
                                        <span class="input-group-text">
                                            <i class="fas fa-key text-muted"></i>
                                        </span>
                                        <input type="password" 
                                               class="form-control" 
                                               id="mat_khau" 
                                               name="mat_khau" 
                                               placeholder="Nhập mật khẩu"
                                               required
                                               autocomplete="current-password">
                                        <button class="btn btn-outline-secondary" type="button" onclick="togglePassword()">
                                            <i class="fas fa-eye" id="toggleIcon"></i>
                                        </button>
                                    </div>
                                </div>
                                
                                <div class="d-grid mb-3">
                                    <button type="submit" class="btn btn-primary btn-lg" id="loginBtn">
                                        <i class="fas fa-sign-in-alt me-2"></i>Đăng nhập Admin
                                    </button>
                                </div>
                            </form>

                            <!-- Demo accounts -->
                            <div class="demo-accounts">
                                <h6 class="fw-bold text-primary mb-3">
                                    <i class="fas fa-info-circle me-2"></i>Tài khoản demo
                                </h6>
                                
                                <div class="row g-2">
                                    <div class="col-12">
                                        <div class="d-flex justify-content-between align-items-center p-2 bg-white rounded">
                                            <div>
                                                <strong class="text-danger">Admin:</strong>
                                                <small class="text-muted ms-2">tktshop / password</small>
                                            </div>
                                            <button type="button" class="btn btn-sm btn-outline-danger" onclick="quickLogin('tktshop', 'password')">
                                                <i class="fas fa-rocket"></i> Quick Login
                                            </button>
                                        </div>
                                    </div>
                                    
                                    <div class="col-12">
                                        <div class="d-flex justify-content-between align-items-center p-2 bg-white rounded">
                                            <div>
                                                <strong class="text-warning">Staff:</strong>
                                                <small class="text-muted ms-2">nhanvien1 / password</small>
                                            </div>
                                            <button type="button" class="btn btn-sm btn-outline-warning" onclick="quickLogin('nhanvien1', 'password')">
                                                <i class="fas fa-rocket"></i> Quick Login
                                            </button>
                                        </div>
                                    </div>
                                </div>
                                
                                <div class="text-center mt-3">
                                    <small class="text-muted">
                                        <i class="fas fa-shield-alt me-1"></i>
                                        Chỉ dành cho quản trị viên và nhân viên
                                    </small>
                                </div>
                            </div>

                            <hr class="my-4">

                            <!-- Links -->
                            <div class="text-center">
                                <a href="/tktshop/customer/" class="text-decoration-none text-muted">
                                    <i class="fas fa-arrow-left me-1"></i>Về trang chủ
                                </a>
                            </div>
                        </div>
                    </div>

                    <!-- System info -->
                    <div class="text-center mt-4">
                        <small class="text-white-50">
                            <i class="fas fa-server me-1"></i>
                            TKT Shop Admin v1.0 &copy; <?= date('Y') ?>
                        </small>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
    
    <script>
        // Toggle password visibility
        function togglePassword() {
            const passwordField = document.getElementById('mat_khau');
            const toggleIcon = document.getElementById('toggleIcon');
            
            if (passwordField.type === 'password') {
                passwordField.type = 'text';
                toggleIcon.classList.remove('fa-eye');
                toggleIcon.classList.add('fa-eye-slash');
            } else {
                passwordField.type = 'password';
                toggleIcon.classList.remove('fa-eye-slash');
                toggleIcon.classList.add('fa-eye');
            }
        }
        
        // Quick login function
        function quickLogin(username, password) {
            document.getElementById('ten_dang_nhap').value = username;
            document.getElementById('mat_khau').value = password;
            
            // Add loading state
            const btn = event.target;
            const originalText = btn.innerHTML;
            btn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Logging in...';
            btn.disabled = true;
            
            // Submit form after short delay for UX
            setTimeout(() => {
                document.getElementById('loginForm').submit();
            }, 500);
        }
        
        // Auto focus on username field
        document.addEventListener('DOMContentLoaded', function() {
            document.getElementById('ten_dang_nhap').focus();
        });
        
        // Enhanced form submission
        document.getElementById('loginForm').addEventListener('submit', function(e) {
            const btn = document.getElementById('loginBtn');
            const originalText = btn.innerHTML;
            
            btn.innerHTML = '<i class="fas fa-spinner fa-spin me-2"></i>Đang đăng nhập...';
            btn.disabled = true;
            
            // Re-enable button after timeout (in case of errors)
            setTimeout(() => {
                btn.innerHTML = originalText;
                btn.disabled = false;
            }, 5000);
        });
        
        // Enter key support
        document.addEventListener('keypress', function(e) {
            if (e.key === 'Enter') {
                document.getElementById('loginForm').submit();
            }
        });

        // Add some animation on load
        window.addEventListener('load', function() {
            document.querySelector('.login-card').style.opacity = '0';
            document.querySelector('.login-card').style.transform = 'translateY(50px)';
            
            setTimeout(() => {
                document.querySelector('.login-card').style.transition = 'all 0.6s ease';
                document.querySelector('.login-card').style.opacity = '1';
                document.querySelector('.login-card').style.transform = 'translateY(0)';
            }, 100);
        });
    </script>
</body>
</html>