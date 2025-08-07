<?php
// /tktshop/index.php - Trang chủ chính với routing
session_start();
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Kiểm tra xem có file config không
if (file_exists('config/database.php')) {
    require_once 'config/database.php';
}
if (file_exists('config/config.php')) {
    require_once 'config/config.php';
}

// Lấy đường dẫn hiện tại
$request_uri = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
$path = str_replace('/tktshop', '', $request_uri);

// Router với customer frontend support
switch ($path) {
    case '/':
    case '/index.php':
        // Trang chủ
        showHomePage();
        break;
    
    // ADMIN ROUTES
    case '/admin':
        header('Location: /tktshop/admin/colors/index.php');
        exit;
        break;
    
    // CUSTOMER FRONTEND ROUTES  
    case '/products':
    case '/products/':
        header('Location: /tktshop/customer/products.php');
        exit;
        break;
    case '/login':
    case '/login/':
        header('Location: /tktshop/customer/login.php');
        exit;
        break;
    case '/register':
    case '/register/':
        header('Location: /tktshop/customer/register.php');
        exit;
        break;
    case '/cart':
    case '/cart/':
        header('Location: /tktshop/customer/cart.php');
        exit;
        break;
    case '/checkout':
    case '/checkout/':
        header('Location: /tktshop/customer/checkout.php');
        exit;
        break;
    case '/orders':
    case '/orders/':
        header('Location: /tktshop/customer/orders.php');
        exit;
        break;
    case '/account':
    case '/account/':
        header('Location: /tktshop/customer/login.php');
        exit;
        break;
    
    // DIRECT CUSTOMER ACCESS
    case '/customer':
    case '/customer/':
        header('Location: /tktshop/customer/index.php');
        exit;
        break;
    
    // SPECIAL PAGES
    case '/product':
        showProductPage();
        break;
    case '/about':
    case '/about/':
        showAboutPage();
        break;
    case '/contact':
    case '/contact/':
        showContactPage();
        break;
        
    default:
        // 404
        show404Page();
        break;
}

function showHomePage() {
    ?>
    <!DOCTYPE html>
    <html lang="vi">
    <head>
        <meta charset="UTF-8">
        <meta name="viewport" content="width=device-width, initial-scale=1.0">
        <title>TKT Shop - Cửa hàng giày dép online</title>
        <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
        <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
        <style>
            .hero-section {
                background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
                color: white;
                padding: 100px 0;
            }
            .feature-card {
                border: none;
                box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1);
                transition: transform 0.3s;
            }
            .feature-card:hover {
                transform: translateY(-5px);
            }
            .text-purple { color: #6f42c1; }
        </style>
    </head>
    <body>
        <!-- Navigation -->
        <nav class="navbar navbar-expand-lg navbar-dark bg-dark">
            <div class="container">
                <a class="navbar-brand" href="/tktshop/">
                    <i class="fas fa-shoe-prints me-2"></i>TKT Shop
                </a>
                <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarNav">
                    <span class="navbar-toggler-icon"></span>
                </button>
                <div class="collapse navbar-collapse" id="navbarNav">
                    <ul class="navbar-nav me-auto">
                        <li class="nav-item">
                            <a class="nav-link active" href="/tktshop/">Trang chủ</a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link" href="/tktshop/products/">Sản phẩm</a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link" href="/tktshop/about/">Giới thiệu</a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link" href="/tktshop/contact/">Liên hệ</a>
                        </li>
                    </ul>
                    <ul class="navbar-nav">
                        <li class="nav-item">
                            <a class="nav-link" href="/tktshop/customer/">
                                <i class="fas fa-user me-1"></i>Khách hàng
                            </a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link" href="/tktshop/admin/">
                                <i class="fas fa-cog me-1"></i>Admin
                            </a>
                        </li>
                    </ul>
                </div>
            </div>
        </nav>

        <!-- Hero Section -->
        <section class="hero-section">
            <div class="container text-center">
                <h1 class="display-4 fw-bold mb-4">Chào mừng đến với TKT Shop</h1>
                <p class="lead mb-4">Cửa hàng giày dép trực tuyến với đa dạng sản phẩm chất lượng cao</p>
                <div class="row justify-content-center">
                    <div class="col-md-8">
                        <div class="d-flex gap-3 justify-content-center flex-wrap">
                            <a href="/tktshop/products/" class="btn btn-light btn-lg">
                                <i class="fas fa-shopping-bag me-2"></i>Xem sản phẩm
                            </a>
                            <a href="/tktshop/customer/" class="btn btn-outline-light btn-lg">
                                <i class="fas fa-user me-2"></i>Khách hàng
                            </a>
                            <a href="/tktshop/admin/" class="btn btn-outline-light btn-lg">
                                <i class="fas fa-cog me-2"></i>Quản trị
                            </a>
                        </div>
                    </div>
                </div>
            </div>
        </section>

        <!-- Customer Section -->
        <section class="py-5 bg-light">
            <div class="container">
                <div class="row text-center mb-5">
                    <div class="col-12">
                        <h2 class="fw-bold">Khu vực khách hàng</h2>
                        <p class="text-muted">Trải nghiệm mua sắm trực tuyến tuyệt vời</p>
                    </div>
                </div>
                <div class="row g-4">
                    <div class="col-md-3 col-sm-6">
                        <div class="card feature-card h-100 text-center p-3">
                            <div class="card-body">
                                <i class="fas fa-home fa-2x text-primary mb-3"></i>
                                <h6 class="card-title">Trang chủ khách hàng</h6>
                                <a href="/tktshop/customer/" class="btn btn-outline-primary btn-sm">Truy cập</a>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-3 col-sm-6">
                        <div class="card feature-card h-100 text-center p-3">
                            <div class="card-body">
                                <i class="fas fa-sign-in-alt fa-2x text-success mb-3"></i>
                                <h6 class="card-title">Đăng nhập</h6>
                                <a href="/tktshop/login/" class="btn btn-outline-success btn-sm">Đăng nhập</a>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-3 col-sm-6">
                        <div class="card feature-card h-100 text-center p-3">
                            <div class="card-body">
                                <i class="fas fa-user-plus fa-2x text-warning mb-3"></i>
                                <h6 class="card-title">Đăng ký</h6>
                                <a href="/tktshop/register/" class="btn btn-outline-warning btn-sm">Đăng ký</a>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-3 col-sm-6">
                        <div class="card feature-card h-100 text-center p-3">
                            <div class="card-body">
                                <i class="fas fa-shopping-cart fa-2x text-info mb-3"></i>
                                <h6 class="card-title">Giỏ hàng</h6>
                                <a href="/tktshop/cart/" class="btn btn-outline-info btn-sm">Xem giỏ hàng</a>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </section>

        <!-- Admin Features Section -->
        <section class="py-5">
            <div class="container">
                <div class="row text-center mb-5">
                    <div class="col-12">
                        <h2 class="fw-bold">Tính năng quản trị</h2>
                        <p class="text-muted">Hệ thống quản lý cửa hàng giày dép hoàn chỉnh</p>
                    </div>
                </div>
                <div class="row g-4">
                    <div class="col-md-4">
                        <div class="card feature-card h-100 text-center p-4">
                            <div class="card-body">
                                <i class="fas fa-palette fa-3x text-primary mb-3"></i>
                                <h5 class="card-title">Quản lý màu sắc</h5>
                                <p class="card-text">Quản lý đa dạng màu sắc cho sản phẩm với mã màu HEX chính xác</p>
                                <a href="/tktshop/admin/colors/" class="btn btn-outline-primary">Xem chi tiết</a>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-4">
                        <div class="card feature-card h-100 text-center p-4">
                            <div class="card-body">
                                <i class="fas fa-ruler fa-3x text-success mb-3"></i>
                                <h5 class="card-title">Quản lý kích cỡ</h5>
                                <p class="card-text">Hệ thống kích cỡ linh hoạt, hỗ trợ cả size EU và US</p>
                                <a href="/tktshop/admin/sizes/" class="btn btn-outline-success">Xem chi tiết</a>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-4">
                        <div class="card feature-card h-100 text-center p-4">
                            <div class="card-body">
                                <i class="fas fa-box fa-3x text-warning mb-3"></i>
                                <h5 class="card-title">Quản lý sản phẩm</h5>
                                <p class="card-text">Quản lý sản phẩm với nhiều biến thể và thông tin chi tiết</p>
                                <a href="/tktshop/admin/products/" class="btn btn-outline-warning">Xem chi tiết</a>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-4">
                        <div class="card feature-card h-100 text-center p-4">
                            <div class="card-body">
                                <i class="fas fa-shopping-cart fa-3x text-info mb-3"></i>
                                <h5 class="card-title">Quản lý đơn hàng</h5>
                                <p class="card-text">Theo dõi và xử lý đơn hàng từ đặt hàng đến giao hàng</p>
                                <a href="/tktshop/admin/orders/" class="btn btn-outline-info">Xem chi tiết</a>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-4">
                        <div class="card feature-card h-100 text-center p-4">
                            <div class="card-body">
                                <i class="fas fa-star fa-3x text-danger mb-3"></i>
                                <h5 class="card-title">Quản lý đánh giá</h5>
                                <p class="card-text">Hệ thống đánh giá và phản hồi từ khách hàng</p>
                                <a href="/tktshop/admin/reviews/" class="btn btn-outline-danger">Xem chi tiết</a>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-4">
                        <div class="card feature-card h-100 text-center p-4">
                            <div class="card-body">
                                <i class="fas fa-chart-bar fa-3x text-purple mb-3"></i>
                                <h5 class="card-title">Báo cáo thống kê</h5>
                                <p class="card-text">Thống kê doanh thu, sản phẩm bán chạy và nhiều hơn nữa</p>
                                <a href="/tktshop/admin/" class="btn btn-outline-secondary">Xem chi tiết</a>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </section>

        <!-- Quick Access Section -->
        <section class="bg-light py-5">
            <div class="container">
                <div class="row text-center mb-4">
                    <div class="col-12">
                        <h3 class="fw-bold">Truy cập nhanh</h3>
                        <p class="text-muted">Các chức năng phổ biến</p>
                    </div>
                </div>
                <div class="row g-3">
                    <div class="col-md-2 col-sm-4 col-6">
                        <a href="/tktshop/admin/colors/create.php" class="btn btn-outline-primary w-100 py-3">
                            <i class="fas fa-plus-circle d-block mb-2"></i>
                            <small>Thêm màu sắc</small>
                        </a>
                    </div>
                    <div class="col-md-2 col-sm-4 col-6">
                        <a href="/tktshop/admin/sizes/create.php" class="btn btn-outline-success w-100 py-3">
                            <i class="fas fa-plus-circle d-block mb-2"></i>
                            <small>Thêm kích cỡ</small>
                        </a>
                    </div>
                    <div class="col-md-2 col-sm-4 col-6">
                        <a href="/tktshop/admin/products/create.php" class="btn btn-outline-warning w-100 py-3">
                            <i class="fas fa-plus-circle d-block mb-2"></i>
                            <small>Thêm sản phẩm</small>
                        </a>
                    </div>
                    <div class="col-md-2 col-sm-4 col-6">
                        <a href="/tktshop/admin/orders/" class="btn btn-outline-info w-100 py-3">
                            <i class="fas fa-list d-block mb-2"></i>
                            <small>Đơn hàng</small>
                        </a>
                    </div>
                    <div class="col-md-2 col-sm-4 col-6">
                        <a href="/tktshop/admin/reviews/" class="btn btn-outline-danger w-100 py-3">
                            <i class="fas fa-comments d-block mb-2"></i>
                            <small>Đánh giá</small>
                        </a>
                    </div>
                    <div class="col-md-2 col-sm-4 col-6">
                        <a href="/tktshop/debug.php" class="btn btn-outline-secondary w-100 py-3">
                            <i class="fas fa-bug d-block mb-2"></i>
                            <small>Debug</small>
                        </a>
                    </div>
                </div>
            </div>
        </section>

        <!-- Debug Tools Section -->
        <section class="py-4 bg-dark text-white">
            <div class="container">
                <div class="row text-center">
                    <div class="col-12">
                        <h5 class="mb-3">🛠️ Debug Tools</h5>
                        <div class="d-flex gap-2 justify-content-center flex-wrap">
                            <a href="/tktshop/debug.php" class="btn btn-outline-light btn-sm">
                                <i class="fas fa-search me-1"></i>Full Debug
                            </a>
                            <a href="/tktshop/frontend_debug.php" class="btn btn-outline-light btn-sm">
                                <i class="fas fa-code me-1"></i>Frontend Debug
                            </a>
                            <a href="/tktshop/quick_test.php" class="btn btn-outline-light btn-sm">
                                <i class="fas fa-bolt me-1"></i>Quick Test
                            </a>
                        </div>
                    </div>
                </div>
            </div>
        </section>

        <!-- Footer -->
        <footer class="bg-dark text-white py-4">
            <div class="container">
                <div class="row">
                    <div class="col-md-6">
                        <h5><i class="fas fa-shoe-prints me-2"></i>TKT Shop</h5>
                        <p>Hệ thống quản lý cửa hàng giày dép trực tuyến</p>
                    </div>
                    <div class="col-md-6 text-md-end">
                        <p>&copy; 2025 TKT Shop. All rights reserved.</p>
                        <p>
                            <a href="/tktshop/admin/" class="text-white-50">Admin Panel</a> | 
                            <a href="/tktshop/debug.php" class="text-white-50">Debug Tool</a> |
                            <a href="/tktshop/customer/" class="text-white-50">Customer Area</a>
                        </p>
                    </div>
                </div>
            </div>
        </footer>

        <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
    </body>
    </html>
    <?php
}

function showProductPage() {
    ?>
    <!DOCTYPE html>
    <html lang="vi">
    <head>
        <meta charset="UTF-8">
        <meta name="viewport" content="width=device-width, initial-scale=1.0">
        <title>Sản phẩm - TKT Shop</title>
        <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
        <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    </head>
    <body>
        <div class="container mt-5">
            <div class="text-center">
                <i class="fas fa-box-open fa-5x text-muted mb-4"></i>
                <h1>Danh sách sản phẩm</h1>
                <p class="lead">Trang sản phẩm đang được phát triển...</p>
                <div class="mt-4">
                    <a href="/tktshop/" class="btn btn-primary">
                        <i class="fas fa-home me-2"></i>Về trang chủ
                    </a>
                    <a href="/tktshop/customer/products.php" class="btn btn-success">
                        <i class="fas fa-shopping-bag me-2"></i>Trang sản phẩm đầy đủ
                    </a>
                </div>
            </div>
        </div>
    </body>
    </html>
    <?php
}

function showAboutPage() {
    ?>
    <!DOCTYPE html>
    <html lang="vi">
    <head>
        <meta charset="UTF-8">
        <meta name="viewport" content="width=device-width, initial-scale=1.0">
        <title>Giới thiệu - TKT Shop</title>
        <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
        <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    </head>
    <body>
        <div class="container mt-5">
            <div class="text-center">
                <i class="fas fa-info-circle fa-5x text-info mb-4"></i>
                <h1>Giới thiệu TKT Shop</h1>
                <p class="lead">Hệ thống cửa hàng giày dép trực tuyến hiện đại</p>
                <a href="/tktshop/" class="btn btn-primary">Về trang chủ</a>
            </div>
        </div>
    </body>
    </html>
    <?php
}

function showContactPage() {
    ?>
    <!DOCTYPE html>
    <html lang="vi">
    <head>
        <meta charset="UTF-8">
        <meta name="viewport" content="width=device-width, initial-scale=1.0">
        <title>Liên hệ - TKT Shop</title>
        <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
        <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    </head>
    <body>
        <div class="container mt-5">
            <div class="text-center">
                <i class="fas fa-phone fa-5x text-success mb-4"></i>
                <h1>Liên hệ với chúng tôi</h1>
                <p class="lead">Trang liên hệ đang được phát triển...</p>
                <a href="/tktshop/" class="btn btn-primary">Về trang chủ</a>
            </div>
        </div>
    </body>
    </html>
    <?php
}

function show404Page() {
    http_response_code(404);
    ?>
    <!DOCTYPE html>
    <html lang="vi">
    <head>
        <meta charset="UTF-8">
        <meta name="viewport" content="width=device-width, initial-scale=1.0">
        <title>404 - Trang không tồn tại</title>
        <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
        <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    </head>
    <body>
        <div class="container">
            <div class="row justify-content-center align-items-center min-vh-100">
                <div class="col-md-6 text-center">
                    <i class="fas fa-exclamation-triangle fa-5x text-warning mb-4"></i>
                    <h1 class="display-4 fw-bold">404</h1>
                    <h2>Trang bạn tìm kiếm không tồn tại</h2>
                    <p class="lead text-muted mb-4">
                        Có thể trang đã được di chuyển hoặc URL không chính xác.
                    </p>
                    <div class="d-flex gap-3 justify-content-center flex-wrap">
                        <a href="/tktshop/" class="btn btn-primary">
                            <i class="fas fa-home me-2"></i>Về trang chủ
                        </a>
                        <a href="/tktshop/customer/" class="btn btn-success">
                            <i class="fas fa-user me-2"></i>Khách hàng
                        </a>
                        <a href="/tktshop/admin/" class="btn btn-outline-secondary">
                            <i class="fas fa-cog me-2"></i>Admin
                        </a>
                    </div>
                    <div class="mt-4">
                        <small class="text-muted">
                            Mã lỗi: 404 | <?= date('Y-m-d H:i:s') ?> | URI: <?= htmlspecialchars($_SERVER['REQUEST_URI']) ?>
                        </small>
                    </div>
                </div>
            </div>
        </div>
    </body>
    </html>
    <?php
}
?>