<?php
// /tktshop/index_fixed.php - Trang chủ chính với routing FIXED
/**
 * 🔧 FIXED VERSION - Router cập nhật hướng đến fixed files
 * - products → products_fixed.php
 * - cart → cart_fixed.php  
 * - add_to_cart → add_to_cart_fixed.php
 */

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

// Router với customer frontend support - FIXED ROUTES
switch ($path) {
    case '/':
    case '/index.php':
        // Trang chủ
        showHomePage();
        break;
    
    // ADMIN ROUTES (giữ nguyên)
    case '/admin':
        header('Location: /tktshop/admin/colors/index.php');
        exit;
        break;
    
    // 🔧 FIXED CUSTOMER FRONTEND ROUTES
    case '/products':
    case '/products/':
        header('Location: /tktshop/customer/products_fixed.php');
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
        header('Location: /tktshop/customer/cart_fixed.php');
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
    
    // DIRECT CUSTOMER ACCESS - FIXED
    case '/customer':
    case '/customer/':
        header('Location: /tktshop/customer/index_fixed.php');
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
        <title>TKT Shop - Cửa hàng giày dép online (FIXED VERSION)</title>
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
            .fixed-badge {
                position: fixed;
                top: 10px;
                right: 10px;
                z-index: 1000;
                background: #28a745;
                color: white;
                padding: 5px 10px;
                border-radius: 5px;
                font-size: 12px;
            }
        </style>
    </head>
    <body>
        <!-- Fixed Version Badge -->
        <div class="fixed-badge">
            <i class="fas fa-wrench me-1"></i>FIXED VERSION
        </div>
        
        <!-- Navigation -->
        <nav class="navbar navbar-expand-lg navbar-dark bg-dark">
            <div class="container">
                <a class="navbar-brand" href="/tktshop/index_fixed.php">
                    <i class="fas fa-shoe-prints me-2"></i>TKT Shop <small class="text-success">(FIXED)</small>
                </a>
                <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarNav">
                    <span class="navbar-toggler-icon"></span>
                </button>
                <div class="collapse navbar-collapse" id="navbarNav">
                    <ul class="navbar-nav me-auto">
                        <li class="nav-item">
                            <a class="nav-link active" href="/tktshop/index_fixed.php">Trang chủ</a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link" href="/tktshop/customer/products_fixed.php">
                                Sản phẩm <span class="badge bg-success">FIXED</span>
                            </a>
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
                            <a class="nav-link" href="/tktshop/customer/cart_fixed.php">
                                <i class="fas fa-shopping-cart me-1"></i>
                                Giỏ hàng <span class="badge bg-success">FIXED</span>
                            </a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link" href="/tktshop/customer/index_fixed.php">
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
                <h1 class="display-4 fw-bold mb-4">
                    TKT Shop - FIXED VERSION
                    <span class="badge bg-success fs-6">v2.0</span>
                </h1>
                <p class="lead mb-4">
                    Phiên bản đã sửa lỗi với unified database schema, 
                    fixed cart system và improved product display
                </p>
                <div class="row justify-content-center">
                    <div class="col-md-8">
                        <div class="d-flex gap-3 justify-content-center flex-wrap">
                            <a href="/tktshop/customer/products_fixed.php" class="btn btn-light btn-lg">
                                <i class="fas fa-shopping-bag me-2"></i>
                                Xem sản phẩm (FIXED)
                            </a>
                            <a href="/tktshop/customer/cart_fixed.php" class="btn btn-outline-light btn-lg">
                                <i class="fas fa-shopping-cart me-2"></i>
                                Giỏ hàng (FIXED)
                            </a>
                            <a href="/tktshop/customer/index_fixed.php" class="btn btn-outline-light btn-lg">
                                <i class="fas fa-user me-2"></i>
                                Khách hàng
                            </a>
                        </div>
                    </div>
                </div>
            </div>
        </section>

        <!-- Fixed Features Section -->
        <section class="py-5 bg-light">
            <div class="container">
                <div class="row text-center mb-5">
                    <div class="col-12">
                        <h2 class="fw-bold">🔧 Các tính năng đã được sửa</h2>
                        <p class="text-muted">Version mới với unified database schema</p>
                    </div>
                </div>
                <div class="row g-4">
                    <div class="col-md-3 col-sm-6">
                        <div class="card feature-card h-100 text-center p-3">
                            <div class="card-body">
                                <i class="fas fa-database fa-2x text-success mb-3"></i>
                                <h6 class="card-title">Products Fixed</h6>
                                <p class="small text-muted">Hiển thị từ cả 2 bảng: products + san_pham_chinh</p>
                                <a href="/tktshop/customer/products_fixed.php" class="btn btn-outline-success btn-sm">
                                    Test ngay
                                </a>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-3 col-sm-6">
                        <div class="card feature-card h-100 text-center p-3">
                            <div class="card-body">
                                <i class="fas fa-shopping-cart fa-2x text-primary mb-3"></i>
                                <h6 class="card-title">Cart Fixed</h6>
                                <p class="small text-muted">Đồng bộ SESSION + DATABASE, unified schema</p>
                                <a href="/tktshop/customer/cart_fixed.php" class="btn btn-outline-primary btn-sm">
                                    Test ngay
                                </a>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-3 col-sm-6">
                        <div class="card feature-card h-100 text-center p-3">
                            <div class="card-body">
                                <i class="fas fa-plus-circle fa-2x text-warning mb-3"></i>
                                <h6 class="card-title">Add to Cart Fixed</h6>
                                <p class="small text-muted">API thêm giỏ hàng với auto-detect schema</p>
                                <a href="/tktshop/customer/products_fixed.php" class="btn btn-outline-warning btn-sm">
                                    Test thêm SP
                                </a>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-3 col-sm-6">
                        <div class="card feature-card h-100 text-center p-3">
                            <div class="card-body">
                                <i class="fas fa-route fa-2x text-info mb-3"></i>
                                <h6 class="card-title">Router Fixed</h6>
                                <p class="small text-muted">URL routing cập nhật đến files fixed</p>
                                <a href="/tktshop/" class="btn btn-outline-info btn-sm">
                                    So sánh gốc
                                </a>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </section>

        <!-- Comparison Section -->
        <section class="py-5">
            <div class="container">
                <div class="row text-center mb-5">
                    <div class="col-12">
                        <h2 class="fw-bold">So sánh version gốc vs Fixed</h2>
                    </div>
                </div>
                <div class="row">
                    <div class="col-md-6">
                        <div class="card border-danger">
                            <div class="card-header bg-danger text-white">
                                <h5 class="mb-0">
                                    <i class="fas fa-exclamation-triangle me-2"></i>
                                    Version Gốc (có lỗi)
                                </h5>
                            </div>
                            <div class="card-body">
                                <ul class="list-unstyled">
                                    <li><i class="fas fa-times text-danger me-2"></i>Sản phẩm không hiển thị ở products.php</li>
                                    <li><i class="fas fa-times text-danger me-2"></i>Thêm giỏ hàng không hiện</li>
                                    <li><i class="fas fa-times text-danger me-2"></i>Schema database conflict</li>
                                    <li><i class="fas fa-times text-danger me-2"></i>SESSION vs DATABASE sync issue</li>
                                </ul>
                                <a href="/tktshop/" class="btn btn-outline-danger">
                                    Test version gốc
                                </a>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-6">
                        <div class="card border-success">
                            <div class="card-header bg-success text-white">
                                <h5 class="mb-0">
                                    <i class="fas fa-check-circle me-2"></i>
                                    Version Fixed (đã sửa)
                                </h5>
                            </div>
                            <div class="card-body">
                                <ul class="list-unstyled">
                                    <li><i class="fas fa-check text-success me-2"></i>Hiển thị từ cả 2 bảng products</li>
                                    <li><i class="fas fa-check text-success me-2"></i>Add to cart hoạt động perfect</li>
                                    <li><i class="fas fa-check text-success me-2"></i>Auto-detect schema Vietnamese/English</li>
                                    <li><i class="fas fa-check text-success me-2"></i>Sync SESSION + DATABASE hoàn hảo</li>
                                </ul>
                                <a href="/tktshop/customer/products_fixed.php" class="btn btn-success">
                                    Test version fixed
                                </a>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </section>

        <!-- Quick Test Section -->
        <section class="py-5 bg-primary text-white">
            <div class="container">
                <div class="row text-center mb-4">
                    <div class="col-12">
                        <h3 class="fw-bold">🚀 Quick Test Links</h3>
                        <p class="mb-0">Test ngay các tính năng đã fix</p>
                    </div>
                </div>
                <div class="row g-3">
                    <div class="col-md-3 col-sm-6">
                        <a href="/tktshop/customer/products_fixed.php" class="btn btn-light w-100 py-3">
                            <i class="fas fa-list d-block mb-2"></i>
                            <strong>Products Fixed</strong><br>
                            <small>Hiển thị tất cả sản phẩm</small>
                        </a>
                    </div>
                    <div class="col-md-3 col-sm-6">
                        <a href="/tktshop/customer/cart_fixed.php" class="btn btn-light w-100 py-3">
                            <i class="fas fa-shopping-cart d-block mb-2"></i>
                            <strong>Cart Fixed</strong><br>
                            <small>Giỏ hàng unified</small>
                        </a>
                    </div>
                    <div class="col-md-3 col-sm-6">
                        <a href="/tktshop/admin/products/add.php" class="btn btn-light w-100 py-3">
                            <i class="fas fa-plus d-block mb-2"></i>
                            <strong>Add Product</strong><br>
                            <small>Thêm sản phẩm từ admin</small>
                        </a>
                    </div>
                    <div class="col-md-3 col-sm-6">
                        <a href="/tktshop/customer/index_fixed.php" class="btn btn-light w-100 py-3">
                            <i class="fas fa-home d-block mb-2"></i>
                            <strong>Home Fixed</strong><br>
                            <small>Trang chủ customer fixed</small>
                        </a>
                    </div>
                </div>
            </div>
        </section>

        <!-- Footer -->
        <footer class="bg-dark text-white py-4">
            <div class="container">
                <div class="row">
                    <div class="col-md-6">
                        <h5><i class="fas fa-shoe-prints me-2"></i>TKT Shop - FIXED VERSION</h5>
                        <p>Phiên bản đã sửa lỗi với unified database schema</p>
                    </div>
                    <div class="col-md-6 text-md-end">
                        <p>&copy; 2025 TKT Shop Fixed. All rights reserved.</p>
                        <p>
                            <a href="/tktshop/admin/" class="text-white-50">Admin Panel</a> | 
                            <a href="/tktshop/" class="text-white-50">Version Gốc</a> |
                            <a href="/tktshop/customer/index_fixed.php" class="text-white-50">Customer Fixed</a>
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
        <title>Sản phẩm - TKT Shop FIXED</title>
        <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
        <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    </head>
    <body>
        <div class="container mt-5">
            <div class="text-center">
                <i class="fas fa-box-open fa-5x text-success mb-4"></i>
                <h1>Danh sách sản phẩm - FIXED VERSION</h1>
                <p class="lead">Sản phẩm từ cả 2 bảng: products + san_pham_chinh</p>
                <div class="mt-4">
                    <a href="/tktshop/index_fixed.php" class="btn btn-primary">
                        <i class="fas fa-home me-2"></i>Về trang chủ FIXED
                    </a>
                    <a href="/tktshop/customer/products_fixed.php" class="btn btn-success">
                        <i class="fas fa-shopping-bag me-2"></i>Products FIXED
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
        <title>Giới thiệu - TKT Shop FIXED</title>
        <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
        <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    </head>
    <body>
        <div class="container mt-5">
            <div class="text-center">
                <i class="fas fa-info-circle fa-5x text-info mb-4"></i>
                <h1>Giới thiệu TKT Shop - FIXED VERSION</h1>
                <p class="lead">Phiên bản đã sửa lỗi với unified database schema</p>
                <a href="/tktshop/index_fixed.php" class="btn btn-primary">Về trang chủ FIXED</a>
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
        <title>Liên hệ - TKT Shop FIXED</title>
        <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
        <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    </head>
    <body>
        <div class="container mt-5">
            <div class="text-center">
                <i class="fas fa-phone fa-5x text-success mb-4"></i>
                <h1>Liên hệ với chúng tôi - FIXED VERSION</h1>
                <p class="lead">Hỗ trợ technical cho phiên bản fixed</p>
                <a href="/tktshop/index_fixed.php" class="btn btn-primary">Về trang chủ FIXED</a>
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
        <title>404 - Trang không tồn tại (FIXED)</title>
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
                        Có thể bạn đang tìm version fixed?
                    </p>
                    <div class="d-flex gap-3 justify-content-center flex-wrap">
                        <a href="/tktshop/index_fixed.php" class="btn btn-primary">
                            <i class="fas fa-home me-2"></i>Trang chủ FIXED
                        </a>
                        <a href="/tktshop/customer/products_fixed.php" class="btn btn-success">
                            <i class="fas fa-shopping-bag me-2"></i>Products FIXED
                        </a>
                        <a href="/tktshop/customer/cart_fixed.php" class="btn btn-info">
                            <i class="fas fa-shopping-cart me-2"></i>Cart FIXED
                        </a>
                    </div>
                    <div class="mt-4">
                        <small class="text-muted">
                            FIXED VERSION | <?= date('Y-m-d H:i:s') ?> | URI: <?= htmlspecialchars($_SERVER['REQUEST_URI']) ?>
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