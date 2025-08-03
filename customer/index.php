<?php
// customer/index.php
/**
 * Trang chủ website bán hàng - Hiển thị sản phẩm nổi bật, sản phẩm mới nhất, danh mục sản phẩm
 * Chức năng: Slider, sản phẩm nổi bật, sản phẩm mới, danh mục, tìm kiếm
 */

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../config/config.php';

// Lấy sản phẩm nổi bật (6 sản phẩm)
$featured_products = $pdo->query("
    SELECT sp.*, dm.ten_danh_muc,
           COALESCE(sp.gia_khuyen_mai, sp.gia_goc) as gia_hien_tai,
           MIN(bsp.gia_ban) as gia_thap_nhat,
           MAX(bsp.gia_ban) as gia_cao_nhat,
           SUM(bsp.so_luong_ton_kho) as tong_ton_kho
    FROM san_pham_chinh sp
    LEFT JOIN danh_muc_giay dm ON sp.danh_muc_id = dm.id
    LEFT JOIN bien_the_san_pham bsp ON sp.id = bsp.san_pham_id AND bsp.trang_thai = 'hoat_dong'
    WHERE sp.san_pham_noi_bat = 1 AND sp.trang_thai = 'hoat_dong'
    GROUP BY sp.id
    HAVING tong_ton_kho > 0
    ORDER BY sp.luot_xem DESC, sp.ngay_tao DESC
    LIMIT 6
")->fetchAll();

// Lấy sản phẩm mới nhất (8 sản phẩm)
$new_products = $pdo->query("
    SELECT sp.*, dm.ten_danh_muc,
           COALESCE(sp.gia_khuyen_mai, sp.gia_goc) as gia_hien_tai,
           MIN(bsp.gia_ban) as gia_thap_nhat,
           MAX(bsp.gia_ban) as gia_cao_nhat,
           SUM(bsp.so_luong_ton_kho) as tong_ton_kho
    FROM san_pham_chinh sp
    LEFT JOIN danh_muc_giay dm ON sp.danh_muc_id = dm.id
    LEFT JOIN bien_the_san_pham bsp ON sp.id = bsp.san_pham_id AND bsp.trang_thai = 'hoat_dong'
    WHERE sp.trang_thai = 'hoat_dong'
    GROUP BY sp.id
    HAVING tong_ton_kho > 0
    ORDER BY sp.ngay_tao DESC
    LIMIT 8
")->fetchAll();

// Lấy danh mục chính (không có danh mục cha)
$main_categories = $pdo->query("
    SELECT dm.*, 
           COUNT(sp.id) as so_san_pham
    FROM danh_muc_giay dm
    LEFT JOIN san_pham_chinh sp ON dm.id = sp.danh_muc_id AND sp.trang_thai = 'hoat_dong'
    WHERE dm.trang_thai = 'hoat_dong' AND dm.danh_muc_cha_id IS NULL
    GROUP BY dm.id
    HAVING so_san_pham > 0
    ORDER BY dm.thu_tu_hien_thi ASC
    LIMIT 6
")->fetchAll();
?>

<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= SITE_NAME ?> - Giày thể thao chính hãng</title>
    <meta name="description" content="Mua giày thể thao chính hãng Nike, Adidas, Converse... Giao hàng toàn quốc, thanh toán VNPay an toàn.">
    
    <!-- Bootstrap CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <!-- Font Awesome -->
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <!-- Custom CSS -->
    <style>
        .hero-section {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            padding: 100px 0;
        }
        
        .product-card {
            transition: transform 0.3s ease, box-shadow 0.3s ease;
            border: none;
            height: 100%;
        }
        .product-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 8px 25px rgba(0,0,0,0.15);
        }
        
        .product-image {
            height: 250px;
            object-fit: cover;
            background: #f8f9fa;
        }
        
        .price-original {
            text-decoration: line-through;
            color: #6c757d;
            font-size: 0.9em;
        }
        
        .price-sale {
            color: #dc3545;
            font-weight: bold;
        }
        
        .badge-sale {
            position: absolute;
            top: 10px;
            right: 10px;
            z-index: 1;
        }
        
        .category-card {
            transition: all 0.3s ease;
            cursor: pointer;
        }
        .category-card:hover {
            transform: scale(1.05);
        }
        
        .stats-section {
            background: #f8f9fa;
            padding: 60px 0;
        }
        
        .stat-item {
            text-align: center;
            padding: 20px;
        }
        .stat-number {
            font-size: 2.5rem;
            font-weight: bold;
            color: #667eea;
        }
        
        .section-title {
            position: relative;
            text-align: center;
            margin-bottom: 50px;
        }
        .section-title::after {
            content: '';
            position: absolute;
            bottom: -10px;
            left: 50%;
            transform: translateX(-50%);
            width: 60px;
            height: 3px;
            background: #667eea;
        }
    </style>
</head>
<body>
    <!-- Header -->
    <?php include __DIR__ . '/includes/header.php'; ?>

    <!-- Hero Section -->
    <section class="hero-section">
        <div class="container">
            <div class="row align-items-center">
                <div class="col-lg-6">
                    <h1 class="display-4 fw-bold mb-4">
                        Giày thể thao chính hãng
                    </h1>
                    <p class="lead mb-4">
                        Khám phá bộ sưu tập giày thể thao từ các thương hiệu nổi tiếng như Nike, Adidas, Converse... 
                        Chất lượng đảm bảo, giá cả hợp lý, giao hàng toàn quốc.
                    </p>
                    <div class="d-flex gap-3">
                        <a href="/tktshop/customer/products.php" class="btn btn-light btn-lg">
                            <i class="fas fa-shopping-bag me-2"></i>
                            Mua sắm ngay
                        </a>
                        <a href="#featured-products" class="btn btn-outline-light btn-lg">
                            Xem sản phẩm
                        </a>
                    </div>
                </div>
                <div class="col-lg-6 text-center">
                    <img src="/tktshop/assets/images/hero-shoes.png" alt="Giày thể thao" class="img-fluid" style="max-height: 400px;">
                </div>
            </div>
        </div>
    </section>

    <!-- Stats Section -->
    <section class="stats-section">
        <div class="container">
            <div class="row">
                <div class="col-md-3">
                    <div class="stat-item">
                        <div class="stat-number">1000+</div>
                        <div class="text-muted">Sản phẩm</div>
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="stat-item">
                        <div class="stat-number">50K+</div>
                        <div class="text-muted">Khách hàng</div>
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="stat-item">
                        <div class="stat-number">99%</div>
                        <div class="text-muted">Hài lòng</div>
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="stat-item">
                        <div class="stat-number">24/7</div>
                        <div class="text-muted">Hỗ trợ</div>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <!-- Categories Section -->
    <section class="py-5">
        <div class="container">
            <h2 class="section-title">Danh mục sản phẩm</h2>
            <div class="row">
                <?php foreach ($main_categories as $category): ?>
                    <div class="col-lg-4 col-md-6 mb-4">
                        <div class="card category-card h-100" onclick="location.href='/tktshop/customer/products.php?category=<?= $category['id'] ?>'">
                            <?php if ($category['hinh_anh']): ?>
                                <img src="/tktshop/uploads/categories/<?= $category['hinh_anh'] ?>" class="card-img-top" style="height: 200px; object-fit: cover;" alt="<?= htmlspecialchars($category['ten_danh_muc']) ?>">
                            <?php else: ?>
                                <div class="card-img-top bg-light d-flex align-items-center justify-content-center" style="height: 200px;">
                                    <i class="fas fa-image fa-3x text-muted"></i>
                                </div>
                            <?php endif; ?>
                            <div class="card-body text-center">
                                <h5 class="card-title"><?= htmlspecialchars($category['ten_danh_muc']) ?></h5>
                                <p class="text-muted"><?= $category['so_san_pham'] ?> sản phẩm</p>
                                <?php if ($category['mo_ta']): ?>
                                    <p class="card-text text-muted small"><?= htmlspecialchars($category['mo_ta']) ?></p>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        </div>
    </section>

    <!-- Featured Products -->
    <section class="py-5 bg-light" id="featured-products">
        <div class="container">
            <h2 class="section-title">Sản phẩm nổi bật</h2>
            <div class="row">
                <?php foreach ($featured_products as $product): ?>
                    <div class="col-lg-4 col-md-6 mb-4">
                        <div class="card product-card h-100">
                            <div class="position-relative">
                                <?php if ($product['hinh_anh_chinh']): ?>
                                    <img src="/tktshop/uploads/products/<?= $product['hinh_anh_chinh'] ?>" 
                                         class="card-img-top product-image" 
                                         alt="<?= htmlspecialchars($product['ten_san_pham']) ?>">
                                <?php else: ?>
                                    <div class="card-img-top product-image d-flex align-items-center justify-content-center">
                                        <i class="fas fa-image fa-3x text-muted"></i>
                                    </div>
                                <?php endif; ?>
                                
                                <?php if ($product['gia_khuyen_mai'] && $product['gia_khuyen_mai'] < $product['gia_goc']): ?>
                                    <?php 
                                    $discount_percent = round((($product['gia_goc'] - $product['gia_khuyen_mai']) / $product['gia_goc']) * 100);
                                    ?>
                                    <span class="badge bg-danger badge-sale">-<?= $discount_percent ?>%</span>
                                <?php endif; ?>
                                
                                <?php if ($product['san_pham_moi']): ?>
                                    <span class="badge bg-success position-absolute" style="top: 10px; left: 10px;">Mới</span>
                                <?php endif; ?>
                            </div>
                            
                            <div class="card-body d-flex flex-column">
                                <div class="mb-2">
                                    <?php if ($product['thuong_hieu']): ?>
                                        <small class="text-muted"><?= htmlspecialchars($product['thuong_hieu']) ?></small>
                                    <?php endif; ?>
                                    <small class="text-muted"> • <?= htmlspecialchars($product['ten_danh_muc']) ?></small>
                                </div>
                                
                                <h5 class="card-title">
                                    <a href="/tktshop/customer/product_detail.php?slug=<?= $product['slug'] ?>" class="text-decoration-none text-dark">
                                        <?= htmlspecialchars($product['ten_san_pham']) ?>
                                    </a>
                                </h5>
                                
                                <?php if ($product['mo_ta_ngan']): ?>
                                    <p class="card-text text-muted small"><?= htmlspecialchars(substr($product['mo_ta_ngan'], 0, 100)) ?>...</p>
                                <?php endif; ?>
                                
                                <div class="mt-auto">
                                    <div class="d-flex align-items-center justify-content-between mb-3">
                                        <div>
                                            <?php if ($product['gia_khuyen_mai'] && $product['gia_khuyen_mai'] < $product['gia_goc']): ?>
                                                <span class="price-original"><?= formatPrice($product['gia_goc']) ?></span><br>
                                                <span class="price-sale fs-5"><?= formatPrice($product['gia_khuyen_mai']) ?></span>
                                            <?php else: ?>
                                                <span class="fs-5 fw-bold text-primary"><?= formatPrice($product['gia_goc']) ?></span>
                                            <?php endif; ?>
                                        </div>
                                        
                                        <div class="text-end">
                                            <div class="text-warning mb-1">
                                                <?php for ($i = 1; $i <= 5; $i++): ?>
                                                    <i class="fas fa-star<?= $i <= floor($product['diem_danh_gia_tb']) ? '' : ' text-muted' ?>"></i>
                                                <?php endfor; ?>
                                            </div>
                                            <small class="text-muted"><?= $product['so_luong_danh_gia'] ?> đánh giá</small>
                                        </div>
                                    </div>
                                    
                                    <div class="d-flex gap-2">
                                        <a href="/tktshop/customer/product_detail.php?slug=<?= $product['slug'] ?>" class="btn btn-outline-primary btn-sm flex-grow-1">
                                            <i class="fas fa-eye"></i> Xem chi tiết
                                        </a>
                                        <button class="btn btn-primary btn-sm" onclick="addToCart(<?= $product['id'] ?>)">
                                            <i class="fas fa-cart-plus"></i>
                                        </button>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
            
            <div class="text-center mt-4">
                <a href="/tktshop/customer/products.php?featured=1" class="btn btn-primary btn-lg">
                    Xem tất cả sản phẩm nổi bật
                </a>
            </div>
        </div>
    </section>

    <!-- New Products -->
    <section class="py-5">
        <div class="container">
            <h2 class="section-title">Sản phẩm mới nhất</h2>
            <div class="row">
                <?php foreach (array_slice($new_products, 0, 4) as $product): ?>
                    <div class="col-lg-3 col-md-6 mb-4">
                        <div class="card product-card h-100">
                            <div class="position-relative">
                                <?php if ($product['hinh_anh_chinh']): ?>
                                    <img src="/tktshop/uploads/products/<?= $product['hinh_anh_chinh'] ?>" 
                                         class="card-img-top product-image" 
                                         alt="<?= htmlspecialchars($product['ten_san_pham']) ?>">
                                <?php else: ?>
                                    <div class="card-img-top product-image d-flex align-items-center justify-content-center">
                                        <i class="fas fa-image fa-3x text-muted"></i>
                                    </div>
                                <?php endif; ?>
                                
                                <span class="badge bg-info position-absolute" style="top: 10px; left: 10px;">Mới</span>
                                
                                <?php if ($product['gia_khuyen_mai'] && $product['gia_khuyen_mai'] < $product['gia_goc']): ?>
                                    <?php 
                                    $discount_percent = round((($product['gia_goc'] - $product['gia_khuyen_mai']) / $product['gia_goc']) * 100);
                                    ?>
                                    <span class="badge bg-danger badge-sale">-<?= $discount_percent ?>%</span>
                                <?php endif; ?>
                            </div>
                            
                            <div class="card-body d-flex flex-column">
                                <div class="mb-2">
                                    <?php if ($product['thuong_hieu']): ?>
                                        <small class="text-muted"><?= htmlspecialchars($product['thuong_hieu']) ?></small>
                                    <?php endif; ?>
                                </div>
                                
                                <h6 class="card-title">
                                    <a href="/tktshop/customer/product_detail.php?slug=<?= $product['slug'] ?>" class="text-decoration-none text-dark">
                                        <?= htmlspecialchars($product['ten_san_pham']) ?>
                                    </a>
                                </h6>
                                
                                <div class="mt-auto">
                                    <div class="mb-2">
                                        <?php if ($product['gia_khuyen_mai'] && $product['gia_khuyen_mai'] < $product['gia_goc']): ?>
                                            <small class="price-original"><?= formatPrice($product['gia_goc']) ?></small><br>
                                            <span class="price-sale"><?= formatPrice($product['gia_khuyen_mai']) ?></span>
                                        <?php else: ?>
                                            <span class="fw-bold text-primary"><?= formatPrice($product['gia_goc']) ?></span>
                                        <?php endif; ?>
                                    </div>
                                    
                                    <div class="d-flex gap-1">
                                        <a href="/tktshop/customer/product_detail.php?slug=<?= $product['slug'] ?>" class="btn btn-outline-primary btn-sm flex-grow-1">
                                            Xem
                                        </a>
                                        <button class="btn btn-primary btn-sm" onclick="addToCart(<?= $product['id'] ?>)">
                                            <i class="fas fa-cart-plus"></i>
                                        </button>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
            
            <div class="text-center mt-4">
                <a href="/tktshop/customer/products.php?new=1" class="btn btn-outline-primary btn-lg">
                    Xem tất cả sản phẩm mới
                </a>
            </div>
        </div>
    </section>

    <!-- Features Section -->
    <section class="py-5 bg-primary text-white">
        <div class="container">
            <div class="row text-center">
                <div class="col-md-3 mb-4">
                    <div class="feature-item">
                        <i class="fas fa-shipping-fast fa-3x mb-3"></i>
                        <h5>Giao hàng nhanh</h5>
                        <p class="mb-0">Giao hàng toàn quốc trong 1-3 ngày</p>
                    </div>
                </div>
                <div class="col-md-3 mb-4">
                    <div class="feature-item">
                        <i class="fas fa-undo fa-3x mb-3"></i>
                        <h5>Đổi trả dễ dàng</h5>
                        <p class="mb-0">Đổi trả miễn phí trong 7 ngày</p>
                    </div>
                </div>
                <div class="col-md-3 mb-4">
                    <div class="feature-item">
                        <i class="fas fa-shield-alt fa-3x mb-3"></i>
                        <h5>Chính hãng 100%</h5>
                        <p class="mb-0">Cam kết sản phẩm chính hãng</p>
                    </div>
                </div>
                <div class="col-md-3 mb-4">
                    <div class="feature-item">
                        <i class="fas fa-headset fa-3x mb-3"></i>
                        <h5>Hỗ trợ 24/7</h5>
                        <p class="mb-0">Tư vấn và hỗ trợ mọi lúc</p>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <!-- Footer -->
    <?php include __DIR__ . '/includes/footer.php'; ?>

    <!-- Bootstrap JS -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
    
    <script>
        // Load cart count
        function loadCartCount() {
            // TODO: Implement cart count loading from server or localStorage
            // For now, just set to 0
            const cartCountElement = document.getElementById('cart-count');
            if (cartCountElement) {
                cartCountElement.textContent = '0';
            }
        }
        
        // Add to cart function
        function addToCart(productId) {
            // Redirect to product detail để chọn variant
            const productCard = document.querySelector(`[onclick="addToCart(${productId})"]`).closest('.card');
            const productLink = productCard.querySelector('a[href*="product_detail.php"]');
            if (productLink) {
                window.location.href = productLink.href;
            } else {
                // Fallback - redirect to products page
                window.location.href = '/tktshop/customer/products.php';
            }
        }
        
        // Load cart count on page load
        document.addEventListener('DOMContentLoaded', function() {
            loadCartCount();
        });
        
        // Smooth scroll for anchor links
        document.querySelectorAll('a[href^="#"]').forEach(anchor => {
            anchor.addEventListener('click', function (e) {
                e.preventDefault();
                const target = document.querySelector(this.getAttribute('href'));
                if (target) {
                    target.scrollIntoView({
                        behavior: 'smooth',
                        block: 'start'
                    });
                }
            });
        });
    </script>
</body>
</html>