<div class="container py-4">
        <!-- Error Section -->
        <div class="error-container">
            <div class="error-animation">
                <div class="error-icon">
                    <i class="fas fa-search"></i>
                </div>
                <div class="error-code">404</div>
                <div class="error-message">Không tìm thấy trang</div>
                <p class="text-muted mb-4">
                    Trang bạn tìm kiếm không tồn tại hoặc đã bị di chuyển.<br>
                    Vui lòng kiểm tra lại đường dẫn hoặc quay về trang chủ.
                </p>
                
                <div class="d-flex gap-3 justify-content-center flex-wrap">
                    <a href="/tktshop/customer/" class="btn back-button">
                        <i class="fas fa-home me-2"></i>Về trang chủ
                    </a>
                    <a href="/tktshop/customer/products.php" class="btn btn-outline-primary">
                        <i class="fas fa-shopping-bag me-2"></i>Xem sản phẩm
                    </a>
                </div>
            </div>
        </div>
        
        <!-- Search Suggestions -->
        <div class="search-suggestions">
            <div class="text-center">
                <h5><i class="fas fa-lightbulb me-2"></i>Gợi ý tìm kiếm</h5>
                <form action="/tktshop/customer/products.php" method="GET" class="d-flex justify-content-center mt-3">
                    <div class="input-group" style="max-width: 400px;">
                        <input type="text" name="search" class="form-control" placeholder="Tìm kiếm sản phẩm...">
                        <button type="submit" class="btn btn-primary">
                            <i class="fas fa-search"></i>
                        </button>
                    </div>
                </form>
            </div>
        </div>
        
        <!-- Popular Categories -->
        <?php if (!empty($popular_categories)): ?>
        <div class="help-section">
            <h4 class="text-center mb-4">
                <i class="fas fa-tags me-2"></i>Danh mục phổ biến
            </h4>
            <div class="row">
                <?php foreach ($popular_categories as $category): ?>
                <div class="col-lg-4 col-md-6 mb-3">
                    <a href="/tktshop/customer/products.php?category=<?= $category['id'] ?>" class="category-card">
                        <div class="d-flex align-items-center">
                            <div class="me-3">
                                <i class="fas fa-folder fa-2x"></i>
                            </div>
                            <div>
                                <h6 class="mb-1"><?= htmlspecialchars($category['ten_danh_muc'] ?? $category['name']) ?></h6>
                                <small><?= $category['product_count'] ?> sản phẩm</small>
                            </div>
                        </div>
                    </a>
                </div>
                <?php endforeach; ?>
            </div>
        </div>
        <?php endif; ?>
        
        <!-- Featured Products -->
        <?php if (!empty($suggested_products)): ?>
        <div class="help-section">
            <h4 class="text-center mb-4">
                <i class="fas fa-star me-2"></i>Sản phẩm nổi bật
            </h4>
            <div class="row">
                <?php foreach ($suggested_products as $product): ?>
                <div class="col-lg-3 col-md-4 col-sm-6 mb-4">
                    <div class="card product-card h-100">
                        <div class="position-relative">
                            <?php 
                            $product_url = isset($product['slug']) && !empty($product['slug']) 
                                ? "/tktshop/customer/product_detail_fixed.php?slug=" . urlencode($product['slug'])
                                : "/tktshop/customer/product_detail_fixed.php?id=" . $product['id'];
                            ?>
                            <a href="<?= $product_url ?>">
                                <?php
                                $image_field = isset($product['hinh_anh_chinh']) ? 'hinh_anh_chinh' : 'main_image';
                                $image = $product[$image_field] ?? 'no-image.jpg';
                                ?>
                                <img src="/tktshop/uploads/products/<?= htmlspecialchars($image) ?>" 
                                     class="card-img-top" 
                                     alt="<?= htmlspecialchars($product['ten_san_pham'] ?? $product['name']) ?>"
                                     onerror="this.src='/tktshop/uploads/products/no-image.jpg'">
                            </a>
                            <?php if (($product['san_pham_noi_bat'] ?? $product['is_featured'] ?? 0)): ?>
                                <span class="badge bg-success position-absolute" style="top: 10px; left: 10px;">Nổi bật</span>
                            <?php endif; ?>
                        </div>
                        <div class="card-body d-flex flex-column">
                            <h6 class="card-title">
                                <a href="<?= $product_url ?>" class="text-decoration-none text-dark">
                                    <?= htmlspecialchars($product['ten_san_pham'] ?? $product['name']) ?>
                                </a>
                            </h6>
                            <?php 
                            $brand = $product['thuong_hieu'] ?? $product['brand'] ?? '';
                            if ($brand): 
                            ?>
                                <small class="text-muted mb-2">
                                    <i class="fas fa-tag me-1"></i><?= htmlspecialchars($brand) ?>
                                </small>
                            <?php endif; ?>
                            <div class="mt-auto">
                                <div class="h6 text-primary mb-0">
                                    <?= formatPrice($product['current_price']) ?>
                                </div>
                                <?php if (isset($product['gia_goc']) && $product['gia_khuyen_mai'] && $product['gia_khuyen_mai'] < $product['gia_goc']): ?>
                                    <small class="text-muted text-decoration-line-through">
                                        <?= formatPrice($product['gia_goc']) ?>
                                    </small>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>
        </div>
        <?php endif; ?>
        
        <!-- Help Section -->
        <div class="help-section">
            <div class="text-center">
                <h4 class="mb-4">
                    <i class="fas fa-question-circle me-2"></i>Cần hỗ trợ?
                </h4>
                <div class="row">
                    <div class="col-md-4 mb-3">
                        <div class="text-center">
                            <i class="fas fa-phone fa-2x text-primary mb-3"></i>
                            <h6>Hotline</h6>
                            <p class="text-muted">
                                <a href="tel:0866792996" class="text-decoration-none">
                                    (086) 679-2996
                                </a>
                            </p>
                        </div>
                    </div>
                    <div class="col-md-4 mb-3">
                        <div class="text-center">
                            <i class="fas fa-envelope fa-2x text-success mb-3"></i>
                            <h6>Email</h6>
                            <p class="text-muted">
                                <a href="mailto:Dovankien072211@gmail.com" class="text-decoration-none">
                                    Dovankien072211@gmail.com
                                </a>
                            </p>
                        </div>
                    </div>
                    <div class="col-md-4 mb-3">
                        <div class="text-center">
                            <i class="fas fa-comments fa-2x text-info mb-3"></i>
                            <h6>Chat hỗ trợ</h6>
                            <p class="text-muted">
                                <a href="#" class="text-decoration-none" onclick="openChat()">
                                    Chat trực tuyến
                                </a>
                            </p>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        
        <!-- Back to Top -->
        <div class="text-center mt-4">
            <button onclick="window.scrollTo({top: 0, behavior: 'smooth'})" class="btn btn-outline-secondary">
                <i class="fas fa-chevron-up me-2"></i>Lên đầu trang
            </button>
        </div>
    </div>
    
    <!-- Footer -->
    <?php include 'includes/footer.php'; ?>
    
    <!-- Scripts -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
    
    <script>
        // Error page interactions
        document.addEventListener('DOMContentLoaded', function() {
            // Add some interactive effects
            const errorIcon = document.querySelector('.error-icon i');
            const errorCode = document.querySelector('.error-code');
            
            // Animate error icon on hover
            if (errorIcon) {
                errorIcon.addEventListener('mouseenter', function() {
                    this.style.transform = 'scale(1.2) rotate(10deg)';
                    this.style.transition = 'all 0.3s ease';
                });
                
                errorIcon.addEventListener('mouseleave', function() {
                    this.style.transform = 'scale(1) rotate(0deg)';
                });
            }
            
            // Add click effect to error code
            if (errorCode) {
                errorCode.addEventListener('click', function() {
                    this.style.transform = 'scale(0.95)';
                    setTimeout(() => {
                        this.style.transform = 'scale(1)';
                    }, 100);
                });
            }
            
            // Auto-focus search input
            const searchInput = document.querySelector('input[name="search"]');
            if (searchInput) {
                searchInput.focus();
            }
            
            // Add keyboard shortcut (Ctrl/Cmd + K to focus search)
            document.addEventListener('keydown', function(e) {
                if ((e.ctrlKey || e.metaKey) && e.key === 'k') {
                    e.preventDefault();
                    if (searchInput) {
                        searchInput.focus();
                        searchInput.select();
                    }
                }
            });
            
            // Product card hover effects
            const productCards = document.querySelectorAll('.product-card');
            productCards.forEach(card => {
                card.addEventListener('mouseenter', function() {
                    this.style.transform = 'translateY(-5px)';
                });
                
                card.addEventListener('mouseleave', function() {
                    this.style.transform = 'translateY(0)';
                });
            });
            
            // Category card effects
            const categoryCards = document.querySelectorAll('.category-card');
            categoryCards.forEach(card => {
                card.addEventListener('mouseenter', function() {
                    this.style.transform = 'translateY(-2px)';
                });
                
                card.addEventListener('mouseleave', function() {
                    this.style.transform = 'translateY(0)';
                });
            });
            
            console.log('404 Error Page loaded successfully');
        });
        
        // Chat support function
        function openChat() {
            // Placeholder for chat functionality
            alert('Tính năng chat hỗ trợ sẽ được cập nhật sớm!\n\nVui lòng liên hệ qua hotline: (086) 679-2996');
        }
        
        // Error reporting (optional)
        function reportError() {
            const errorData = {
                url: window.location.href,
                referrer: document.referrer,
                userAgent: navigator.userAgent,
                timestamp: new Date().toISOString()
            };
            
            // Send error report to server
            fetch('/tktshop/api/error-report.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                },
                body: JSON.stringify(errorData)
            }).catch(err => {
                console.log('Error reporting failed:', err);
            });
        }
        
        // Auto-report 404 error
        setTimeout(reportError, 2000);
        
        // Search suggestions
        const searchInput = document.querySelector('input[name="search"]');
        if (searchInput) {
            const popularSearches = [
                'Nike Air Force 1',
                'Adidas Ultraboost',
                'Converse Chuck Taylor',
                'Vans Old Skool',
                'Giày thể thao',
                'Giày chạy bộ',
                'Giày sneaker'
            ];
            
            searchInput.addEventListener('focus', function() {
                if (!this.value) {
                    const randomSearch = popularSearches[Math.floor(Math.random() * popularSearches.length)];
                    this.placeholder = `Thử tìm: ${randomSearch}`;
                }
            });
            
            searchInput.addEventListener('blur', function() {
                this.placeholder = 'Tìm kiếm sản phẩm...';
            });
        }
        
        // Add some Easter eggs
        let konamiCode = [];
        const konamiSequence = [38, 38, 40, 40, 37, 39, 37, 39, 66, 65]; // Up Up Down Down Left Right Left Right B A
        
        document.addEventListener('keydown', function(e) {
            konamiCode.push(e.keyCode);
            if (konamiCode.length > konamiSequence.length) {
                konamiCode.shift();
            }
            
            if (konamiCode.toString() === konamiSequence.toString()) {
                // Easter egg activated
                document.body.style.transform = 'rotate(360deg)';
                document.body.style.transition = 'transform 2s ease';
                
                setTimeout(() => {
                    document.body.style.transform = 'rotate(0deg)';
                    alert('🎉 Bạn đã tìm thấy Easter Egg! Nhận mã giảm giá EASTER404 cho đơn hàng tiếp theo!');
                }, 2000);
                
                konamiCode = [];
            }
        });
    </script>
</body>
</html>        .error-icon {
            font-size: 4rem;
            color: #ffc107;
            margin-bottom: 2rem;
            animation: bounce 2s infinite;
        }
        
        @keyframes bounce {
            0%, 20%, 50%, 80%, 100% {
                transform: translateY(0);
            }
            40% {
                transform: translateY(-10px);
            }
            60% {
                transform: translateY(-5px);
            }
        }
        
        .product-card {
            transition: transform 0.3s ease;
            border: none;
            box-shadow: 0 2px 8px rgba(0,0,0,0.1);
        }
        
        .product-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 4px 15px rgba(0,0,0,0.15);
        }
        
        .product-card img {
            height: 200px;
            object-fit: cover;
        }
        
        .category-card {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            border-radius: 10px;
            padding: 20px;
            text-decoration: none;
            transition: all 0.3s ease;
            display: block;
        }
        
        .category-card:hover {
            color: white;
            transform: translateY(-2px);
            box-shadow: 0 5px 15px rgba(0,0,0,0.2);
        }
        
        .search-suggestions {
            background: #f8f9fa;
            border-radius: 10px;
            padding: 20px;
            margin: 30px 0;
        }
        
        .back-button {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            border: none;
            color: white;
            padding: 12px 25px;
            border-radius: 25px;
            font-weight: 500;
            transition: all 0.3s ease;
        }
        
        .back-button:hover {
            transform: translateY(-2px);
            box-shadow: 0 5px 15px rgba(102, 126, 234, 0.4);
            color: white;
        }
        
        .error-animation {
            animation: fadeInUp 1s ease-out;
        }
        
        @keyframes fadeInUp {
            from {
                opacity: 0;
                transform: translateY(30px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }
        
        .help-section {
            background: #fff;
            border-radius: 15px;
            padding: 30px;
            box-shadow: 0 5px 15px rgba(0,0,0,0.08);
            margin: 30px 0;
        }
        
        @media (max-width: 768px) {
            .error-code {
                font-size: 5rem;
            }
            
            .error-message {
                font-size: 1.2rem;
            }
            
            .error-icon {
                font-size: 3rem;
            }
        }
    </style>
</head>
<body>
    <!-- Header -->
    <?php include 'includes/header.php'; ?>
    
    <div class="container py<?php
/**
 * customer/404.php - ERROR 404 PAGE NOT FOUND
 * Trang báo lỗi 404 khi không tìm thấy sản phẩm hoặc trang
 */

// Set 404 status code
if (!headers_sent()) {
    http_response_code(404);
}

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../config/config.php';

// Get some featured products for suggestions
$suggested_products = [];
try {
    $stmt = $pdo->query("
        SELECT sp.*, 
               COALESCE(sp.gia_khuyen_mai, sp.gia_goc) as current_price
        FROM san_pham_chinh sp
        WHERE sp.trang_thai = 'hoat_dong' 
        AND sp.san_pham_noi_bat = 1
        ORDER BY sp.luot_xem DESC
        LIMIT 4
    ");
    $suggested_products = $stmt->fetchAll();
} catch (Exception $e) {
    // Fallback to products table if Vietnamese schema doesn't exist
    try {
        $stmt = $pdo->query("
            SELECT p.*, 
                   COALESCE(p.sale_price, p.price) as current_price
            FROM products p
            WHERE p.status = 'active' 
            AND p.is_featured = 1
            ORDER BY p.created_at DESC
            LIMIT 4
        ");
        $suggested_products = $stmt->fetchAll();
    } catch (Exception $e2) {
        error_log("404 page products query failed: " . $e2->getMessage());
    }
}

// Get popular categories
$popular_categories = [];
try {
    $stmt = $pdo->query("
        SELECT dm.*, COUNT(sp.id) as product_count
        FROM danh_muc_giay dm
        LEFT JOIN san_pham_chinh sp ON dm.id = sp.danh_muc_id AND sp.trang_thai = 'hoat_dong'
        WHERE dm.trang_thai = 'hoat_dong'
        GROUP BY dm.id
        HAVING product_count > 0
        ORDER BY product_count DESC
        LIMIT 6
    ");
    $popular_categories = $stmt->fetchAll();
} catch (Exception $e) {
    // Fallback to categories table
    try {
        $stmt = $pdo->query("
            SELECT c.*, COUNT(p.id) as product_count
            FROM categories c
            LEFT JOIN products p ON c.id = p.category_id AND p.status = 'active'
            WHERE c.status = 'active'
            GROUP BY c.id
            HAVING product_count > 0
            ORDER BY product_count DESC
            LIMIT 6
        ");
        $popular_categories = $stmt->fetchAll();
    } catch (Exception $e2) {
        error_log("404 page categories query failed: " . $e2->getMessage());
    }
}

$page_title = 'Không tìm thấy trang - ' . SITE_NAME;
?>

<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= $page_title ?></title>
    
    <!-- Meta tags -->
    <meta name="robots" content="noindex, nofollow">
    <meta name="description" content="Trang bạn tìm kiếm không tồn tại hoặc đã bị xóa. Khám phá các sản phẩm khác tại TKT Shop.">
    
    <!-- Styles -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    
    <style>
        .error-container {
            min-height: 60vh;
            display: flex;
            align-items: center;
            justify-content: center;
            text-align: center;
        }
        
        .error-code {
            font-size: 8rem;
            font-weight: bold;
            color: #6c757d;
            line-height: 1;
            margin-bottom: 0;
        }
        
        .error-message {
            font-size: 1.5rem;
            color: #495057;
            margin-bottom: 2rem;
        }
        
        .error-icon {
            font-size: 4rem;
            color: #ffc107;
            margin-bottom: 2rem;
            animation: bounce 2s infinite;