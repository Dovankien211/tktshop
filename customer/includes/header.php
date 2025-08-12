<?php
// customer/includes/header.php - FIXED CART LINKS VERSION
/**
 * Header chung cho website khách hàng - ĐÃ SỬA TẤT CẢ CART LINKS
 * 🔧 FIXED: Tất cả link cart.php → cart_fixed.php
 */

// Lấy danh mục chính cho menu
$main_categories = $pdo->query("
    SELECT dm.*, 
           COUNT(sp.id) as so_san_pham
    FROM danh_muc_giay dm
    LEFT JOIN san_pham_chinh sp ON dm.id = sp.danh_muc_id AND sp.trang_thai = 'hoat_dong'
    WHERE dm.trang_thai = 'hoat_dong' AND dm.danh_muc_cha_id IS NULL
    GROUP BY dm.id
    HAVING so_san_pham > 0
    ORDER BY dm.thu_tu_hien_thi ASC
    LIMIT 8
")->fetchAll();

// Đếm số lượng sản phẩm trong giỏ hàng
$cart_count = 0;
if (isset($_SESSION['customer_id'])) {
    $stmt = $pdo->prepare("SELECT SUM(so_luong) FROM gio_hang WHERE khach_hang_id = ?");
    $stmt->execute([$_SESSION['customer_id']]);
    $cart_count = $stmt->fetchColumn() ?: 0;
} elseif (isset($_SESSION['session_id'])) {
    $stmt = $pdo->prepare("SELECT SUM(so_luong) FROM gio_hang WHERE session_id = ?");
    $stmt->execute([$_SESSION['session_id']]);
    $cart_count = $stmt->fetchColumn() ?: 0;
}
?>

<!-- Top Bar -->
<div class="bg-primary text-white py-2">
    <div class="container">
        <div class="row align-items-center">
            <div class="col-md-6">
                <small>
                    <i class="fas fa-phone me-2"></i>(0866) 792996
                    <span class="ms-4">
                        <i class="fas fa-envelope me-2"></i>Dovankien072211@gmail.com
                    </span>
                </small>
            </div>
            <div class="col-md-6 text-md-end">
                <small>
                    <i class="fas fa-shipping-fast me-2"></i>Miễn phí vận chuyển cho đơn hàng trên 500k
                </small>
            </div>
        </div>
    </div>
</div>

<!-- Main Header -->
<nav class="navbar navbar-expand-lg navbar-light bg-white shadow-sm sticky-top">
    <div class="container">
        <!-- Logo -->
        <a class="navbar-brand fw-bold fs-3" href="/tktshop/customer/">
            <i class="fas fa-store text-primary me-2"></i>
            <span class="text-primary"><?= SITE_NAME ?></span>
        </a>
        
        <!-- Mobile Toggle -->
        <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarNav">
            <span class="navbar-toggler-icon"></span>
        </button>
        
        <div class="collapse navbar-collapse" id="navbarNav">
            <!-- Main Menu -->
            <ul class="navbar-nav me-auto">
                <li class="nav-item">
                    <a class="nav-link <?= basename($_SERVER['PHP_SELF']) == 'index.php' ? 'active' : '' ?>" href="/tktshop/customer/">
                        <i class="fas fa-home me-1"></i>Trang chủ
                    </a>
                </li>
                
                <!-- ✅ SỬA: products.php → products_fixed.php -->
                <li class="nav-item">
                    <a class="nav-link <?= basename($_SERVER['PHP_SELF']) == 'products_fixed.php' ? 'active' : '' ?>" href="/tktshop/customer/products_fixed.php">
                        <i class="fas fa-shopping-bag me-1"></i>Sản phẩm
                    </a>
                </li>
                
                <!-- Categories Dropdown -->
                <li class="nav-item dropdown">
                    <a class="nav-link dropdown-toggle" href="#" id="categoriesDropdown" role="button" data-bs-toggle="dropdown" aria-expanded="false">
                        <i class="fas fa-tags me-1"></i>Danh mục
                    </a>
                    <ul class="dropdown-menu">
                        <!-- ✅ SỬA: products.php → products_fixed.php -->
                        <li><a class="dropdown-item" href="/tktshop/customer/products_fixed.php">
                            <i class="fas fa-th-large me-2"></i>Tất cả sản phẩm
                        </a></li>
                        <li><hr class="dropdown-divider"></li>
                        <?php foreach ($main_categories as $category): ?>
                            <!-- ✅ SỬA: products.php → products_fixed.php -->
                            <li><a class="dropdown-item" href="/tktshop/customer/products_fixed.php?category=<?= $category['id'] ?>">
                                <i class="fas fa-angle-right me-2"></i>
                                <?= htmlspecialchars($category['ten_danh_muc']) ?>
                                <span class="badge bg-light text-dark ms-2"><?= $category['so_san_pham'] ?></span>
                            </a></li>
                        <?php endforeach; ?>
                    </ul>
                </li>
                
                <!-- Brands -->
                <li class="nav-item dropdown">
                    <a class="nav-link dropdown-toggle" href="#" id="brandsDropdown" role="button" data-bs-toggle="dropdown" aria-expanded="false">
                        <i class="fas fa-star me-1"></i>Thương hiệu
                    </a>
                    <ul class="dropdown-menu">
                        <!-- ✅ SỬA: Tất cả products.php → products_fixed.php -->
                        <li><a class="dropdown-item" href="/tktshop/customer/products_fixed.php?brand=Nike">
                            <i class="fas fa-angle-right me-2"></i>Nike
                        </a></li>
                        <li><a class="dropdown-item" href="/tktshop/customer/products_fixed.php?brand=Adidas">
                            <i class="fas fa-angle-right me-2"></i>Adidas
                        </a></li>
                        <li><a class="dropdown-item" href="/tktshop/customer/products_fixed.php?brand=Converse">
                            <i class="fas fa-angle-right me-2"></i>Converse
                        </a></li>
                        <li><a class="dropdown-item" href="/tktshop/customer/products_fixed.php?brand=Vans">
                            <i class="fas fa-angle-right me-2"></i>Vans
                        </a></li>
                        <li><a class="dropdown-item" href="/tktshop/customer/products_fixed.php?brand=Puma">
                            <i class="fas fa-angle-right me-2"></i>Puma
                        </a></li>
                    </ul>
                </li>
            </ul>
            
            <!-- Search Form -->
            <!-- ✅ SỬA: products.php → products_fixed.php -->
            <form class="d-flex me-3" action="/tktshop/customer/products_fixed.php" method="GET">
                <div class="input-group" style="width: 300px;">
                    <input class="form-control" 
                           type="search" 
                           name="search" 
                           placeholder="Tìm kiếm sản phẩm..." 
                           value="<?= htmlspecialchars($_GET['search'] ?? '') ?>"
                           autocomplete="off">
                    <button class="btn btn-outline-primary" type="submit">
                        <i class="fas fa-search"></i>
                    </button>
                </div>
            </form>
            
            <!-- User Actions -->
            <div class="d-flex align-items-center gap-3">
                <!-- Shopping Cart -->
                <!-- 🔧 CRITICAL FIX: cart.php → cart_fixed.php -->
                <a href="/tktshop/customer/cart_fixed.php" class="text-decoration-none position-relative" title="Giỏ hàng">
                    <i class="fas fa-shopping-cart fs-5 text-muted hover-primary"></i>
                    <span class="position-absolute top-0 start-100 translate-middle badge rounded-pill bg-primary" id="cart-count" style="font-size: 0.7rem;">
                        <?= $cart_count ?>
                    </span>
                </a>
                
                <!-- User Menu -->
                <?php if (isset($_SESSION['customer_id'])): ?>
                    <div class="dropdown">
                        <a class="text-decoration-none dropdown-toggle d-flex align-items-center" 
                           href="#" 
                           role="button" 
                           data-bs-toggle="dropdown" 
                           aria-expanded="false">
                            <div class="bg-primary text-white rounded-circle d-flex align-items-center justify-content-center me-2" style="width: 35px; height: 35px;">
                                <i class="fas fa-user"></i>
                            </div>
                            <span class="text-dark"><?= htmlspecialchars($_SESSION['customer_name']) ?></span>
                        </a>
                        <ul class="dropdown-menu dropdown-menu-end">
                            <li><h6 class="dropdown-header">Tài khoản của tôi</h6></li>
                            <li><a class="dropdown-item" href="/tktshop/customer/orders.php">
                                <i class="fas fa-shopping-bag me-2"></i>Đơn hàng của tôi
                            </a></li>
                            <li><hr class="dropdown-divider"></li>
                            <li><a class="dropdown-item text-danger" href="/tktshop/customer/logout.php">
                                <i class="fas fa-sign-out-alt me-2"></i>Đăng xuất
                            </a></li>
                        </ul>
                    </div>
                <?php else: ?>
                    <div class="d-flex gap-2">
                        <a href="/tktshop/customer/login.php" class="btn btn-outline-primary btn-sm">
                            <i class="fas fa-sign-in-alt me-1"></i>Đăng nhập
                        </a>
                        <a href="/tktshop/customer/register.php" class="btn btn-primary btn-sm">
                            <i class="fas fa-user-plus me-1"></i>Đăng ký
                        </a>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
</nav>

<!-- Hot Deals Banner (Optional) -->
<?php
// Kiểm tra có khuyến mãi hot không
$hot_deal = $pdo->query("
    SELECT * FROM san_pham_chinh 
    WHERE gia_khuyen_mai IS NOT NULL 
    AND gia_khuyen_mai < gia_goc 
    AND trang_thai = 'hoat_dong'
    AND ((ngay_bat_dau_km IS NULL OR ngay_bat_dau_km <= NOW()) 
    AND (ngay_ket_thuc_km IS NULL OR ngay_ket_thuc_km >= NOW()))
    ORDER BY ((gia_goc - gia_khuyen_mai) / gia_goc) DESC
    LIMIT 1
")->fetch();

if ($hot_deal && rand(1, 100) <= 30): // 30% chance hiển thị banner
?>
<div class="bg-warning text-dark py-2" id="hotDealBanner">
    <div class="container">
        <div class="row align-items-center">
            <div class="col-md-10">
                <div class="d-flex align-items-center">
                    <i class="fas fa-fire text-danger me-2 fa-lg"></i>
                    <strong>Hot Deal:</strong>
                    <span class="ms-2">
                        <?= htmlspecialchars($hot_deal['ten_san_pham']) ?> 
                        - Giảm <?= round((($hot_deal['gia_goc'] - $hot_deal['gia_khuyen_mai']) / $hot_deal['gia_goc']) * 100) ?>%
                        chỉ còn <?= formatPrice($hot_deal['gia_khuyen_mai']) ?>
                    </span>
                    <!-- ✅ SỬA: Kiểm tra xem dùng product_detail.php hay product_detail_fixed.php -->
                    <a href="/tktshop/customer/product_detail.php?slug=<?= $hot_deal['slug'] ?>" class="btn btn-sm btn-dark ms-3">
                        Mua ngay
                    </a>
                </div>
            </div>
            <div class="col-md-2 text-end">
                <button class="btn btn-sm btn-outline-dark" onclick="closeBanner()">
                    <i class="fas fa-times"></i>
                </button>
            </div>
        </div>
    </div>
</div>
<?php endif; ?>

<style>
.hover-primary:hover {
    color: #0d6efd !important;
}

.navbar-nav .nav-link:hover {
    color: #0d6efd !important;
}

.dropdown-menu {
    border: none;
    box-shadow: 0 5px 15px rgba(0,0,0,0.1);
}

.badge {
    font-size: 0.6rem !important;
    min-width: 18px;
    height: 18px;
    display: flex;
    align-items: center;
    justify-content: center;
}

@media (max-width: 768px) {
    .input-group {
        width: 100% !important;
        margin: 10px 0;
    }
    
    .d-flex.gap-3 {
        justify-content: center;
        margin-top: 10px;
    }
}
</style>

<script>
// 🔧 DEBUG: Add logging to track any conflicting redirects
console.log('🔧 Header loaded - Cart link: /tktshop/customer/cart_fixed.php');

// Close hot deal banner
function closeBanner() {
    document.getElementById('hotDealBanner').style.display = 'none';
    localStorage.setItem('hotDealBannerClosed', 'true');
}

// Check if banner was closed
document.addEventListener('DOMContentLoaded', function() {
    const banner = document.getElementById('hotDealBanner');
    if (banner && localStorage.getItem('hotDealBannerClosed') === 'true') {
        banner.style.display = 'none';
    }
});

// Update cart count function
function updateCartCount(count) {
    document.getElementById('cart-count').textContent = count;
}

// 🔧 DEBUG: Prevent any external cart redirects
document.addEventListener('DOMContentLoaded', function() {
    // Check for any elements that might redirect to cart.php
    const cartLinks = document.querySelectorAll('a[href*="cart.php"]');
    if (cartLinks.length > 0) {
        console.warn('🔧 WARNING: Found cart.php links that should be cart_fixed.php:', cartLinks);
        
        // Auto-fix cart links
        cartLinks.forEach(link => {
            const oldHref = link.href;
            link.href = oldHref.replace('cart.php', 'cart_fixed.php');
            console.log('🔧 AUTO-FIXED cart link:', oldHref, '→', link.href);
        });
    }
    
    // Override any global functions that might redirect to cart.php
    if (window.redirectToCart) {
        const originalRedirectToCart = window.redirectToCart;
        window.redirectToCart = function() {
            console.log('🔧 Intercepted redirectToCart() - redirecting to cart_fixed.php instead');
            window.location.href = '/tktshop/customer/cart_fixed.php';
        };
    }
});
</script>