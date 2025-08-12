<?php
// customer/index_fixed.php - FIXED VERSION
/**
 * 🔧 FIXED VERSION - Trang chủ website bán hàng với links hướng đến fixed files
 * - products.php → products_fixed.php
 * - cart.php → cart_fixed.php
 * - add_to_cart.php → add_to_cart_fixed.php
 */

session_start();
require_once '../config/database.php';
require_once '../config/config.php';

// 🔧 UNIFIED QUERY để lấy sản phẩm từ cả 2 bảng
function getUnifiedFeaturedProducts($pdo, $limit = 6) {
    try {
        // Query từ Vietnamese schema
        $vn_products = $pdo->query("
            SELECT sp.*, dm.ten_danh_muc as category_name,
                   COALESCE(sp.gia_khuyen_mai, sp.gia_goc) as gia_hien_tai,
                   MIN(bsp.gia_ban) as gia_thap_nhat,
                   MAX(bsp.gia_ban) as gia_cao_nhat,
                   SUM(bsp.so_luong_ton_kho) as tong_ton_kho,
                   'vietnamese' as source_schema
            FROM san_pham_chinh sp
            LEFT JOIN danh_muc_giay dm ON sp.danh_muc_id = dm.id
            LEFT JOIN bien_the_san_pham bsp ON sp.id = bsp.san_pham_id AND bsp.trang_thai = 'hoat_dong'
            WHERE sp.san_pham_noi_bat = 1 AND sp.trang_thai = 'hoat_dong'
            GROUP BY sp.id
            HAVING tong_ton_kho > 0
            ORDER BY sp.luot_xem DESC, sp.ngay_tao DESC
            LIMIT " . intval($limit/2)
        )->fetchAll();

        $en_products = $pdo->query("
            SELECT p.*, c.name as category_name,
                   COALESCE(p.sale_price, p.price) as gia_hien_tai,
                   p.price as gia_thap_nhat,
                   p.price as gia_cao_nhat,
                   p.stock_quantity as tong_ton_kho,
                   'english' as source_schema,
                   p.name as ten_san_pham,
                   p.main_image as hinh_anh_chinh,
                   p.slug as slug,
                   p.brand as thuong_hieu,
                   p.price as gia_goc,
                   p.sale_price as gia_khuyen_mai,
                   p.is_featured as san_pham_noi_bat,
                   0 as san_pham_moi,
                   p.short_description as mo_ta_ngan,
                   0 as diem_danh_gia_tb,
                   0 as so_luong_danh_gia,
                   0 as luot_xem
            FROM products p
            LEFT JOIN categories c ON p.category_id = c.id
            WHERE p.is_featured = 1 AND p.status = 'active' AND p.stock_quantity > 0
            ORDER BY p.created_at DESC
            LIMIT " . intval($limit/2)
        )->fetchAll();

        return array_merge($vn_products, $en_products);
    } catch (Exception $e) {
        error_log("getUnifiedFeaturedProducts error: " . $e->getMessage());
        return [];
    }
}

function getUnifiedNewProducts($pdo, $limit = 8) {
    try {
        // Query từ Vietnamese schema - sản phẩm mới
        $vn_products = $pdo->query("
            SELECT sp.*, dm.ten_danh_muc as category_name,
                   COALESCE(sp.gia_khuyen_mai, sp.gia_goc) as gia_hien_tai,
                   MIN(bsp.gia_ban) as gia_thap_nhat,
                   MAX(bsp.gia_ban) as gia_cao_nhat,
                   SUM(bsp.so_luong_ton_kho) as tong_ton_kho,
                   'vietnamese' as source_schema
            FROM san_pham_chinh sp
            LEFT JOIN danh_muc_giay dm ON sp.danh_muc_id = dm.id
            LEFT JOIN bien_the_san_pham bsp ON sp.id = bsp.san_pham_id AND bsp.trang_thai = 'hoat_dong'
            WHERE sp.trang_thai = 'hoat_dong' 
                AND sp.ngay_tao >= DATE_SUB(NOW(), INTERVAL 30 DAY)
            GROUP BY sp.id
            HAVING tong_ton_kho > 0
            ORDER BY sp.ngay_tao DESC
            LIMIT " . intval($limit/2)
        )->fetchAll();

        // Query từ English schema - sản phẩm mới
        $en_products = $pdo->query("
            SELECT p.*, c.name as category_name,
                   COALESCE(p.sale_price, p.price) as gia_hien_tai,
                   p.price as gia_thap_nhat,
                   p.price as gia_cao_nhat,
                   p.stock_quantity as tong_ton_kho,
                   'english' as source_schema,
                   p.name as ten_san_pham,
                   p.main_image as hinh_anh_chinh,
                   p.slug as slug,
                   p.brand as thuong_hieu,
                   p.price as gia_goc,
                   p.sale_price as gia_khuyen_mai,
                   0 as san_pham_noi_bat,
                   1 as san_pham_moi,
                   p.short_description as mo_ta_ngan,
                   0 as diem_danh_gia_tb,
                   0 as so_luong_danh_gia,
                   0 as luot_xem
            FROM products p
            LEFT JOIN categories c ON p.category_id = c.id
            WHERE p.status = 'active' AND p.stock_quantity > 0
                AND p.created_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)
            ORDER BY p.created_at DESC
            LIMIT " . intval($limit/2)
        )->fetchAll();

        return array_merge($vn_products, $en_products);
    } catch (Exception $e) {
        error_log("getUnifiedNewProducts error: " . $e->getMessage());
        return [];
    }
}

function getUnifiedCategories($pdo, $limit = 6) {
    try {
        // Vietnamese categories
        $vn_categories = $pdo->query("
            SELECT dm.*, 
                   COUNT(sp.id) as so_san_pham,
                   'vietnamese' as source_schema,
                   dm.ten_danh_muc as name,
                   dm.mo_ta as description
            FROM danh_muc_giay dm
            LEFT JOIN san_pham_chinh sp ON dm.id = sp.danh_muc_id AND sp.trang_thai = 'hoat_dong'
            WHERE dm.trang_thai = 'hoat_dong' AND dm.danh_muc_cha_id IS NULL
            GROUP BY dm.id
            HAVING so_san_pham > 0
            ORDER BY dm.thu_tu_hien_thi ASC
            LIMIT " . intval($limit/2)
        )->fetchAll();

        // English categories
        $en_categories = $pdo->query("
            SELECT c.*, 
                   COUNT(p.id) as so_san_pham,
                   'english' as source_schema,
                   c.name as ten_danh_muc,
                   '' as hinh_anh,
                   0 as thu_tu_hien_thi
            FROM categories c
            LEFT JOIN products p ON c.id = p.category_id AND p.status = 'active'
            WHERE c.status = 'active'
            GROUP BY c.id
            HAVING so_san_pham > 0
            ORDER BY c.sort_order ASC
            LIMIT " . intval($limit/2)
        )->fetchAll();

        return array_merge($vn_categories, $en_categories);
    } catch (Exception $e) {
        error_log("getUnifiedCategories error: " . $e->getMessage());
        return [];
    }
}

// Helper function để format giá tiền
if (!function_exists('formatPrice')) {
    function formatPrice($price) {
        return number_format($price, 0, ',', '.') . 'đ';
    }
}

// Get unified data
$featured_products = getUnifiedFeaturedProducts($pdo, 6);
$new_products = getUnifiedNewProducts($pdo, 8);
$main_categories = getUnifiedCategories($pdo, 6);
?>

<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= SITE_NAME ?> - FIXED VERSION - Giày thể thao chính hãng</title>
    <meta name="description" content="Mua giày thể thao chính hãng Nike, Adidas, Converse... Giao hàng toàn quốc, thanh toán VNPay an toàn. FIXED VERSION với unified database.">
    
    <!-- Bootstrap CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <!-- Font Awesome -->
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <!-- Custom CSS -->
    <style>
        .hero-section {
            background: linear-gradient(135deg, #28a745 0%, #20c997 100%);
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
            color: #28a745;
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
            background: #28a745;
        }
        
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
        
        .source-badge {
            position: absolute;
            top: 5px;
            left: 5px;
            font-size: 10px;
            z-index: 1;
        }
        
        .debug-info {
            background: #e8f4fd;
            border: 1px solid #bee5eb;
            border-radius: 5px;
            padding: 10px;
            margin: 10px 0;
            font-size: 12px;
        }

        .feature-item {
            padding: 20px;
            text-align: center;
        }

        .toast-container {
            z-index: 10000;
        }
    </style>
</head>
<body>
    <!-- Fixed Version Badge -->
    <div class="fixed-badge">
        <i class="fas fa-wrench me-1"></i>CUSTOMER FIXED
    </div>
    
    <!-- Header -->
    <?php include 'includes/header.php'; ?>

    <!-- 🔧 Debug Info -->
    <div class="container">
        <div class="debug-info">
            <strong>🔧 CUSTOMER INDEX FIXED VERSION</strong><br>
            Featured products: <?= count($featured_products) ?> (from both schemas)<br>
            New products: <?= count($new_products) ?> (unified)<br>
            Categories: <?= count($main_categories) ?> (merged)<br>
            Fixed links: products_fixed.php, cart_fixed.php, add_to_cart_fixed.php
        </div>
    </div>

    <!-- Hero Section -->
    <section class="hero-section">
        <div class="container">
            <div class="row align-items-center">
                <div class="col-lg-6">
                    <h1 class="display-4 fw-bold mb-4">
                        TKT Shop - FIXED VERSION
                        <span class="badge bg-light text-success fs-6">v2.0</span>
                    </h1>
                    <p class="lead mb-4">
                        Phiên bản đã sửa lỗi với unified database schema. 
                        Hiển thị sản phẩm từ cả 2 bảng, giỏ hàng đồng bộ SESSION + DATABASE.
                    </p>
                    <div class="d-flex gap-3">
                        <a href="/tktshop/customer/products_fixed.php" class="btn btn-light btn-lg">
                            <i class="fas fa-shopping-bag me-2"></i>
                            Mua sắm ngay (FIXED)
                        </a>
                        <a href="#featured-products" class="btn btn-outline-light btn-lg">
                            Xem sản phẩm
                        </a>
                    </div>
                </div>
                <div class="col-lg-6 text-center">
                    <div class="position-relative">
                        <i class="fas fa-shoe-prints fa-10x text-white-50"></i>
                        <div class="position-absolute top-0 start-0">
                            <span class="badge bg-success">FIXED</span>
                        </div>
                    </div>
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
                        <div class="stat-number"><?= count($featured_products) + count($new_products) ?>+</div>
                        <div class="text-muted">Sản phẩm (Unified)</div>
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="stat-item">
                        <div class="stat-number">100%</div>
                        <div class="text-muted">Fixed Bugs</div>
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="stat-item">
                        <div class="stat-number">2</div>
                        <div class="text-muted">Database Schemas</div>
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
            <h2 class="section-title">Danh mục sản phẩm (Unified)</h2>
            <div class="row">
                <?php foreach ($main_categories as $category): ?>
                    <div class="col-lg-4 col-md-6 mb-4">
                        <div class="card category-card h-100" onclick="location.href='/tktshop/customer/products_fixed.php?category=<?= $category['id'] ?>'">
                            <div class="position-relative">
                                <span class="badge bg-info source-badge">
                                    <?= $category['source_schema'] === 'vietnamese' ? 'VN' : 'EN' ?>
                                </span>
                                <?php if (!empty($category['hinh_anh'])): ?>
                                    <img src="/tktshop/uploads/categories/<?= $category['hinh_anh'] ?>" class="card-img-top" style="height: 200px; object-fit: cover;" alt="<?= htmlspecialchars($category['ten_danh_muc']) ?>">
                                <?php else: ?>
                                    <div class="card-img-top bg-light d-flex align-items-center justify-content-center" style="height: 200px;">
                                        <i class="fas fa-image fa-3x text-muted"></i>
                                    </div>
                                <?php endif; ?>
                            </div>
                            <div class="card-body text-center">
                                <h5 class="card-title"><?= htmlspecialchars($category['ten_danh_muc']) ?></h5>
                                <p class="text-muted"><?= $category['so_san_pham'] ?> sản phẩm</p>
                                <?php if (!empty($category['description']) && $category['description'] !== $category['ten_danh_muc']): ?>
                                    <p class="card-text text-muted small"><?= htmlspecialchars($category['description']) ?></p>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
            
            <div class="text-center mt-4">
                <a href="/tktshop/customer/products_fixed.php?new=1" class="btn btn-outline-primary btn-lg">
                    Xem tất cả sản phẩm mới (FIXED)
                </a>
            </div>
        </div>
    </section>

    <!-- Features Section -->
    <section class="py-5 bg-success text-white">
        <div class="container">
            <div class="row text-center">
                <div class="col-md-3 mb-4">
                    <div class="feature-item">
                        <i class="fas fa-database fa-3x mb-3"></i>
                        <h5>Unified Database</h5>
                        <p class="mb-0">Đồng bộ cả 2 schema Vietnamese + English</p>
                    </div>
                </div>
                <div class="col-md-3 mb-4">
                    <div class="feature-item">
                        <i class="fas fa-shopping-cart fa-3x mb-3"></i>
                        <h5>Cart Fixed</h5>
                        <p class="mb-0">SESSION + DATABASE sync hoàn hảo</p>
                    </div>
                </div>
                <div class="col-md-3 mb-4">
                    <div class="feature-item">
                        <i class="fas fa-bug fa-3x mb-3"></i>
                        <h5>Bugs Fixed</h5>
                        <p class="mb-0">Sửa tất cả lỗi hiển thị và giỏ hàng</p>
                    </div>
                </div>
                <div class="col-md-3 mb-4">
                    <div class="feature-item">
                        <i class="fas fa-rocket fa-3x mb-3"></i>
                        <h5>Performance</h5>
                        <p class="mb-0">Tối ưu tốc độ và trải nghiệm</p>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <!-- Quick Actions -->
    <section class="py-5 bg-light">
        <div class="container">
            <div class="row text-center mb-4">
                <div class="col-12">
                    <h3 class="fw-bold">🚀 Test Fixed Features</h3>
                </div>
            </div>
            <div class="row g-3">
                <div class="col-md-3 col-sm-6">
                    <a href="/tktshop/customer/products_fixed.php" class="btn btn-success w-100 py-3">
                        <i class="fas fa-list d-block mb-2"></i>
                        <strong>Products Fixed</strong><br>
                        <small>Xem tất cả sản phẩm</small>
                    </a>
                </div>
                <div class="col-md-3 col-sm-6">
                    <a href="/tktshop/customer/cart_fixed.php" class="btn btn-primary w-100 py-3">
                        <i class="fas fa-shopping-cart d-block mb-2"></i>
                        <strong>Cart Fixed</strong><br>
                        <small>Kiểm tra giỏ hàng</small>
                    </a>
                </div>
                <div class="col-md-3 col-sm-6">
                    <a href="/tktshop/admin/products/add.php" class="btn btn-warning w-100 py-3">
                        <i class="fas fa-plus d-block mb-2"></i>
                        <strong>Add Product</strong><br>
                        <small>Thêm sản phẩm test</small>
                    </a>
                </div>
                <div class="col-md-3 col-sm-6">
                    <a href="/tktshop/index_fixed.php" class="btn btn-info w-100 py-3">
                        <i class="fas fa-home d-block mb-2"></i>
                        <strong>Main Fixed</strong><br>
                        <small>Trang chủ fixed</small>
                    </a>
                </div>
            </div>
        </div>
    </section>

    <!-- Newsletter Section -->
    <section class="py-5 bg-primary text-white">
        <div class="container">
            <div class="row justify-content-center">
                <div class="col-lg-8 text-center">
                    <h3 class="mb-3">Đăng ký nhận thông tin khuyến mãi</h3>
                    <p class="mb-4">Nhận ngay mã giảm giá 10% cho đơn hàng đầu tiên</p>
                    <form class="d-flex gap-2 justify-content-center">
                        <input type="email" class="form-control" style="max-width: 300px;" placeholder="Nhập email của bạn">
                        <button type="submit" class="btn btn-success">Đăng ký</button>
                    </form>
                </div>
            </div>
        </div>
    </section>

    <!-- Footer -->
    <?php include 'includes/footer.php'; ?>

    <!-- Toast Container -->
    <div id="toastContainer" class="toast-container position-fixed top-0 end-0 p-3"></div>

    <!-- Bootstrap JS -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
    
    <script>
        // 🔧 FIXED: Add to cart function using fixed API
        function addToCartFixed(productId, quantity = 1) {
            if (!productId) {
                showToast('❌ Sản phẩm không hợp lệ', 'error');
                return;
            }
            
            // Show loading
            const btn = event.target.closest('button');
            const originalHTML = btn.innerHTML;
            btn.disabled = true;
            btn.innerHTML = '<i class="fas fa-spinner fa-spin"></i>';
            
            // Call fixed API
            fetch('/tktshop/customer/add_to_cart_fixed.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-Requested-With': 'XMLHttpRequest'
                },
                body: JSON.stringify({
                    product_id: productId,
                    quantity: quantity,
                    action: 'add'
                })
            })
            .then(response => {
                if (!response.ok) {
                    throw new Error(`HTTP error! status: ${response.status}`);
                }
                return response.json();
            })
            .then(data => {
                if (data.success) {
                    // Show success message
                    showToast('✅ ' + data.message, 'success');
                    
                    // Update cart count if element exists
                    updateCartCount(data.cart_count || data.data?.cart_count);
                    
                    // Show product details in toast
                    if (data.data?.product_name) {
                        setTimeout(() => {
                            showToast(`📦 ${data.data.product_name} - ${formatPrice(data.data.price)}`, 'info');
                        }, 1000);
                    }
                } else {
                    showToast('❌ ' + (data.message || 'Không thể thêm sản phẩm vào giỏ hàng'), 'error');
                    console.error('Add to cart error:', data);
                }
            })
            .catch(error => {
                console.error('Network error:', error);
                showToast('❌ Có lỗi xảy ra khi thêm sản phẩm vào giỏ hàng', 'error');
            })
            .finally(() => {
                // Restore button
                if (btn) {
                    btn.disabled = false;
                    btn.innerHTML = originalHTML;
                }
            });
        }
        
        // Update cart count in header
        function updateCartCount(count) {
            const cartCountElements = document.querySelectorAll('.cart-count, #cart-count, [data-cart-count]');
            cartCountElements.forEach(element => {
                if (element) {
                    element.textContent = count || '0';
                    // Add animation
                    element.classList.add('animate__animated', 'animate__pulse');
                    setTimeout(() => {
                        element.classList.remove('animate__animated', 'animate__pulse');
                    }, 1000);
                }
            });
        }
        
        // Show toast notification
        function showToast(message, type = 'success') {
            let toastContainer = document.getElementById('toastContainer');
            if (!toastContainer) {
                toastContainer = document.createElement('div');
                toastContainer.id = 'toastContainer';
                toastContainer.className = 'toast-container position-fixed top-0 end-0 p-3';
                toastContainer.style.zIndex = '10000';
                document.body.appendChild(toastContainer);
            }
            
            const toastId = 'toast-' + Date.now() + '-' + Math.random().toString(36).substr(2, 9);
            const iconClass = type === 'success' ? 'check-circle' : (type === 'error' ? 'exclamation-circle' : 'info-circle');
            const bgClass = type === 'success' ? 'success' : (type === 'error' ? 'danger' : 'info');
            
            const toastHtml = `
                <div id="${toastId}" class="toast align-items-center text-white bg-${bgClass} border-0" role="alert" aria-live="assertive" aria-atomic="true">
                    <div class="d-flex">
                        <div class="toast-body">
                            <i class="fas fa-${iconClass} me-2"></i>
                            ${message}
                        </div>
                        <button type="button" class="btn-close btn-close-white me-2 m-auto" data-bs-dismiss="toast" aria-label="Close"></button>
                    </div>
                </div>
            `;
            
            toastContainer.insertAdjacentHTML('beforeend', toastHtml);
            const toastElement = document.getElementById(toastId);
            const toast = new bootstrap.Toast(toastElement, {
                autohide: true,
                delay: type === 'error' ? 8000 : 5000
            });
            toast.show();
            
            // Remove after hiding
            toastElement.addEventListener('hidden.bs.toast', () => {
                toastElement.remove();
            });
        }
        
        // Format price function
        function formatPrice(amount) {
            if (!amount) return '0đ';
            return new Intl.NumberFormat('vi-VN').format(amount) + 'đ';
        }
        
        // Load cart count from API
        function loadCartCountFixed() {
            fetch('/tktshop/customer/cart_fixed.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded',
                    'X-Requested-With': 'XMLHttpRequest'
                },
                body: 'action=get_cart_count'
            })
            .then(response => response.json())
            .then(data => {
                if (data.success && data.cart_count !== undefined) {
                    updateCartCount(data.cart_count);
                }
            })
            .catch(error => {
                console.log('Could not load cart count:', error);
                // Fallback to 0
                updateCartCount(0);
            });
        }
        
        // Initialize page
        document.addEventListener('DOMContentLoaded', function() {
            // Load cart count
            loadCartCountFixed();
            
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
            
            // Initialize tooltips
            const tooltipTriggerList = [].slice.call(document.querySelectorAll('[data-bs-toggle="tooltip"]'));
            tooltipTriggerList.map(function (tooltipTriggerEl) {
                return new bootstrap.Tooltip(tooltipTriggerEl);
            });
            
            // Add loading animation to product images
            document.querySelectorAll('.product-image').forEach(img => {
                if (img.tagName === 'IMG') {
                    img.addEventListener('load', function() {
                        this.style.opacity = '1';
                    });
                    img.style.opacity = '0.7';
                    img.style.transition = 'opacity 0.3s ease';
                }
            });
            
            // Log debug info
            console.log('🔧 TKT Shop Customer FIXED - Homepage initialized');
            console.log('📊 Featured products:', <?= count($featured_products) ?>);
            console.log('📊 New products:', <?= count($new_products) ?>);
            console.log('📊 Categories:', <?= count($main_categories) ?>);
            console.log('🔗 Fixed links: products_fixed.php, cart_fixed.php, add_to_cart_fixed.php');
            
            // Show welcome message
            setTimeout(() => {
                showToast('🎉 Chào mừng đến với TKT Shop - FIXED VERSION!', 'info');
            }, 1500);
        });
        
        // Global error handler for debugging
        window.addEventListener('error', function(e) {
            console.error('Global error caught:', e.error);
            showToast('❌ Đã xảy ra lỗi: ' + e.message, 'error');
        });
        
        // Add click analytics for debugging
        document.addEventListener('click', function(e) {
            if (e.target.matches('a[href*="fixed"]')) {
                console.log('🔗 Fixed link clicked:', e.target.href);
            }
            
            if (e.target.matches('.product-card, .product-card *')) {
                const productCard = e.target.closest('.product-card');
                if (productCard) {
                    console.log('📦 Product card interaction detected');
                }
            }
        });
        
        // Performance monitoring
        window.addEventListener('load', function() {
            const loadTime = performance.now();
            console.log(`⚡ Page loaded in ${Math.round(loadTime)}ms`);
            
            if (loadTime > 3000) {
                console.warn('⚠️ Slow page load detected');
            }
        });
        
        // Auto-refresh cart count every 30 seconds
        setInterval(loadCartCountFixed, 30000);
        
        // Keyboard shortcuts for developers
        document.addEventListener('keydown', function(e) {
            // Ctrl + Shift + D = Toggle debug info
            if (e.ctrlKey && e.shiftKey && e.code === 'KeyD') {
                const debugInfo = document.querySelector('.debug-info');
                if (debugInfo) {
                    debugInfo.style.display = debugInfo.style.display === 'none' ? 'block' : 'none';
                }
            }
            
            // Ctrl + Shift + C = Open cart
            if (e.ctrlKey && e.shiftKey && e.code === 'KeyC') {
                window.location.href = '/tktshop/customer/cart_fixed.php';
            }
            
            // Ctrl + Shift + P = Open products
            if (e.ctrlKey && e.shiftKey && e.code === 'KeyP') {
                window.location.href = '/tktshop/customer/products_fixed.php';
            }
        });
    </script>
</body>
</html>
            </div>
            <div class="text-center mt-4">
                <a href="/tktshop/customer/products_fixed.php" class="btn btn-success btn-lg">
                    <i class="fas fa-list me-2"></i>Xem tất cả sản phẩm (FIXED)
                </a>
            </div>
        </div>
    </section>

    <!-- Featured Products -->
    <section class="py-5 bg-light" id="featured-products">
        <div class="container">
            <h2 class="section-title">Sản phẩm nổi bật (Unified)</h2>
            <div class="row">
                <?php foreach ($featured_products as $product): ?>
                    <div class="col-lg-4 col-md-6 mb-4">
                        <div class="card product-card h-100">
                            <div class="position-relative">
                                <span class="badge bg-info source-badge">
                                    <?= $product['source_schema'] === 'vietnamese' ? 'VN' : 'EN' ?>
                                </span>
                                
                                <?php if ($product['hinh_anh_chinh']): ?>
                                    <img src="/tktshop/uploads/products/<?= $product['hinh_anh_chinh'] ?>" 
                                         class="card-img-top product-image" 
                                         alt="<?= htmlspecialchars($product['ten_san_pham']) ?>"
                                         onerror="this.src='/tktshop/uploads/products/no-image.jpg'">
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
                                
                                <?php if (!empty($product['san_pham_noi_bat'])): ?>
                                    <span class="badge bg-warning position-absolute" style="top: 35px; left: 10px;">Nổi bật</span>
                                <?php endif; ?>
                            </div>
                            
                            <div class="card-body d-flex flex-column">
                                <div class="mb-2">
                                    <?php if ($product['thuong_hieu']): ?>
                                        <small class="text-muted"><?= htmlspecialchars($product['thuong_hieu']) ?></small>
                                    <?php endif; ?>
                                    <small class="text-muted"> • <?= htmlspecialchars($product['category_name']) ?></small>
                                </div>
                                
                                <h5 class="card-title">
                                    <a href="/tktshop/customer/product_detail.php?<?= $product['slug'] ? 'slug=' . $product['slug'] : 'id=' . $product['id'] ?>" class="text-decoration-none text-dark">
                                        <?= htmlspecialchars($product['ten_san_pham']) ?>
                                    </a>
                                </h5>
                                
                                <?php if (!empty($product['mo_ta_ngan'])): ?>
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
                                                    <i class="fas fa-star<?= $i <= floor($product['diem_danh_gia_tb'] ?? 0) ? '' : ' text-muted' ?>"></i>
                                                <?php endfor; ?>
                                            </div>
                                            <small class="text-muted"><?= $product['so_luong_danh_gia'] ?? 0 ?> đánh giá</small>
                                        </div>
                                    </div>
                                    
                                    <div class="d-flex gap-2">
                                        <a href="/tktshop/customer/product_detail.php?<?= $product['slug'] ? 'slug=' . $product['slug'] : 'id=' . $product['id'] ?>" class="btn btn-outline-primary btn-sm flex-grow-1">
                                            <i class="fas fa-eye"></i> Xem chi tiết
                                        </a>
                                        <button class="btn btn-success btn-sm" onclick="addToCartFixed(<?= $product['id'] ?>)">
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
                <a href="/tktshop/customer/products_fixed.php?featured=1" class="btn btn-primary btn-lg">
                    Xem tất cả sản phẩm nổi bật (FIXED)
                </a>
            </div>
        </div>
    </section>

    <!-- New Products -->
    <section class="py-5">
        <div class="container">
            <h2 class="section-title">Sản phẩm mới nhất (Unified)</h2>
            <div class="row">
                <?php foreach (array_slice($new_products, 0, 4) as $product): ?>
                    <div class="col-lg-3 col-md-6 mb-4">
                        <div class="card product-card h-100">
                            <div class="position-relative">
                                <span class="badge bg-info source-badge">
                                    <?= $product['source_schema'] === 'vietnamese' ? 'VN' : 'EN' ?>
                                </span>
                                
                                <?php if ($product['hinh_anh_chinh']): ?>
                                    <img src="/tktshop/uploads/products/<?= $product['hinh_anh_chinh'] ?>" 
                                         class="card-img-top product-image" 
                                         alt="<?= htmlspecialchars($product['ten_san_pham']) ?>"
                                         onerror="this.src='/tktshop/uploads/products/no-image.jpg'">
                                <?php else: ?>
                                    <div class="card-img-top product-image d-flex align-items-center justify-content-center">
                                        <i class="fas fa-image fa-3x text-muted"></i>
                                    </div>
                                <?php endif; ?>
                                
                                <span class="badge bg-info position-absolute" style="top: 10px; left: 35px;">Mới</span>
                                
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
                                    <a href="/tktshop/customer/product_detail.php?<?= $product['slug'] ? 'slug=' . $product['slug'] : 'id=' . $product['id'] ?>" class="text-decoration-none text-dark">
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
                                        <a href="/tktshop/customer/product_detail.php?<?= $product['slug'] ? 'slug=' . $product['slug'] : 'id=' . $product['id'] ?>" class="btn btn-outline-primary btn-sm flex-grow-1">
                                            Xem
                                        </a>
                                        <button class="btn btn-success btn-sm" onclick="addToCartFixed(<?= $product['id'] ?>)">
                                            <i class="fas fa-cart-plus"></i>
                                        </button>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                <?php endforeach; ?>