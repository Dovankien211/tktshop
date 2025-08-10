<?php
/**
 * customer/product_detail.php - FIXED VERSION
 * Chi tiết sản phẩm - Đã sửa lỗi database schema và đường dẫn ảnh
 */
session_start();

require_once '../config/database.php';
require_once '../config/config.php';

// Lấy ID sản phẩm từ URL (thay đổi từ slug sang id cho đơn giản)
$product_id = (int)($_GET['id'] ?? 0);

if ($product_id <= 0) {
    header('Location: products.php');
    exit;
}

// 🔧 FIX: Query với tên bảng đúng
$stmt = $pdo->prepare("
    SELECT p.*, c.name as category_name,
           COALESCE(p.sale_price, p.price) as current_price,
           CASE 
               WHEN p.sale_price IS NOT NULL AND p.sale_price < p.price 
               THEN ROUND(((p.price - p.sale_price) / p.price) * 100, 0)
               ELSE 0
           END as discount_percent
    FROM products p
    LEFT JOIN categories c ON p.category_id = c.id
    WHERE p.id = ? AND p.status = 'active'
");
$stmt->execute([$product_id]);
$product = $stmt->fetch();

if (!$product) {
    header('Location: products.php');
    exit;
}

// 🔧 FIX: Cập nhật lượt xem
$pdo->prepare("UPDATE products SET view_count = view_count + 1 WHERE id = ?")->execute([$product['id']]);

// 🔧 SIMPLE: Không có biến thể, chỉ có sản phẩm đơn giản
$variants = [];
$sizes = [];
$colors = [];

// Xử lý AJAX thêm vào giỏ hàng
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['action']) && $_POST['action'] == 'add_to_cart') {
    header('Content-Type: application/json');
    
    $quantity = max(1, (int)($_POST['quantity'] ?? 1));
    
    try {
        // Kiểm tra tồn kho
        if ($product['stock_quantity'] < $quantity) {
            echo json_encode(['success' => false, 'message' => 'Không đủ hàng trong kho! Còn lại: ' . $product['stock_quantity']]);
            exit;
        }
        
        // Khởi tạo session cart
        if (!isset($_SESSION['cart'])) {
            $_SESSION['cart'] = [];
        }
        
        // Tìm sản phẩm trong cart
        $found = false;
        foreach ($_SESSION['cart'] as $key => $item) {
            if ($item['product_id'] == $product_id) {
                $_SESSION['cart'][$key]['quantity'] += $quantity;
                $found = true;
                break;
            }
        }
        
        // Nếu chưa có, thêm mới
        if (!$found) {
            $_SESSION['cart'][] = [
                'product_id' => $product_id,
                'name' => $product['name'],
                'price' => $product['current_price'],
                'quantity' => $quantity,
                'image' => $product['main_image'] ?: 'no-image.jpg',
                'stock' => $product['stock_quantity']
            ];
        }
        
        // Đếm tổng items
        $total_items = 0;
        foreach ($_SESSION['cart'] as $item) {
            $total_items += $item['quantity'];
        }
        
        echo json_encode([
            'success' => true, 
            'message' => 'Thêm sản phẩm vào giỏ hàng thành công!',
            'cart_count' => $total_items
        ]);
        
    } catch (Exception $e) {
        echo json_encode(['success' => false, 'message' => 'Có lỗi xảy ra: ' . $e->getMessage()]);
    }
    exit;
}

// Sản phẩm liên quan
$stmt = $pdo->prepare("
    SELECT p.*, 
           COALESCE(p.sale_price, p.price) as current_price
    FROM products p
    WHERE p.category_id = ? AND p.id != ? AND p.status = 'active'
    ORDER BY p.view_count DESC
    LIMIT 4
");
$stmt->execute([$product['category_id'], $product['id']]);
$related_products = $stmt->fetchAll();

// Helper functions
function formatPrice($price) {
    return number_format($price, 0, ',', '.') . '₫';
}

function getImageUrl($imageName) {
    if (empty($imageName) || $imageName === 'no-image.jpg') {
        return '/tktshop/uploads/products/no-image.jpg';
    }
    return "/tktshop/uploads/products/" . $imageName;
}
?>

<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= htmlspecialchars($product['name']) ?> - TKT Shop</title>
    
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    
    <style>
        .product-image {
            width: 100%;
            height: 400px;
            object-fit: cover;
            border-radius: 10px;
        }
        
        .no-image-placeholder {
            width: 100%;
            height: 400px;
            background: #f8f9fa;
            display: flex;
            align-items: center;
            justify-content: center;
            border: 2px dashed #dee2e6;
            border-radius: 10px;
        }
        
        .quantity-controls {
            display: flex;
            align-items: center;
            max-width: 150px;
        }
        
        .quantity-controls input {
            text-align: center;
            border-left: none;
            border-right: none;
        }
        
        .quantity-controls button {
            width: 40px;
            height: 40px;
            padding: 0;
        }
        
        .badge-sale {
            position: absolute;
            top: 15px;
            right: 15px;
            z-index: 1;
        }
        
        .product-card {
            transition: transform 0.3s, box-shadow 0.3s;
        }
        
        .product-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 5px 15px rgba(0,0,0,0.1);
        }
    </style>
</head>
<body>
    <div class="container py-4">
        <!-- Breadcrumb -->
        <nav aria-label="breadcrumb" class="mb-4">
            <ol class="breadcrumb">
                <li class="breadcrumb-item"><a href="/tktshop/">Trang chủ</a></li>
                <li class="breadcrumb-item"><a href="products.php">Sản phẩm</a></li>
                <li class="breadcrumb-item active"><?= htmlspecialchars($product['name']) ?></li>
            </ol>
        </nav>
        
        <div class="row">
            <!-- Product Image -->
            <div class="col-lg-6">
                <div class="position-relative">
                    <?php if ($product['discount_percent'] > 0): ?>
                        <span class="badge bg-danger badge-sale fs-6">-<?= $product['discount_percent'] ?>%</span>
                    <?php endif; ?>
                    
                    <?php if ($product['is_featured']): ?>
                        <span class="badge bg-warning text-dark position-absolute" style="top: 15px; left: 15px;">Nổi bật</span>
                    <?php endif; ?>
                    
                    <?php if ($product['main_image']): ?>
                        <img src="<?= getImageUrl($product['main_image']) ?>" 
                             alt="<?= htmlspecialchars($product['name']) ?>"
                             class="product-image"
                             onerror="this.parentNode.innerHTML='<div class=no-image-placeholder><div class=text-center><i class=\"fas fa-image fa-3x text-muted mb-3\"></i><p class=text-muted>Hình ảnh đang cập nhật</p></div></div>'">
                    <?php else: ?>
                        <div class="no-image-placeholder">
                            <div class="text-center text-muted">
                                <i class="fas fa-image fa-3x mb-3"></i>
                                <p>Hình ảnh đang cập nhật</p>
                            </div>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
            
            <!-- Product Information -->
            <div class="col-lg-6">
                <!-- Title -->
                <div class="mb-3">
                    <?php if ($product['brand']): ?>
                        <div class="text-muted mb-2">
                            <i class="fas fa-tag me-1"></i>
                            <?= htmlspecialchars($product['brand']) ?>
                        </div>
                    <?php endif; ?>
                    <h1 class="h3"><?= htmlspecialchars($product['name']) ?></h1>
                    <?php if ($product['sku']): ?>
                        <div class="text-muted small">
                            Mã sản phẩm: <strong><?= htmlspecialchars($product['sku']) ?></strong>
                        </div>
                    <?php endif; ?>
                </div>
                
                <!-- Rating -->
                <?php if ($product['rating_average'] > 0): ?>
                    <div class="d-flex align-items-center mb-3">
                        <div class="text-warning me-2">
                            <?php for ($i = 1; $i <= 5; $i++): ?>
                                <i class="fas fa-star<?= $i <= floor($product['rating_average']) ? '' : ' text-muted' ?>"></i>
                            <?php endfor; ?>
                        </div>
                        <span class="me-2"><?= number_format($product['rating_average'], 1) ?></span>
                        <span class="text-muted">
                            (<?= $product['rating_count'] ?: 0 ?> đánh giá) •
                            <?= $product['view_count'] ?: 0 ?> lượt xem
                        </span>
                    </div>
                <?php endif; ?>
                
                <!-- Price -->
                <div class="mb-4 p-3 bg-light rounded">
                    <div class="d-flex align-items-center gap-3 flex-wrap">
                        <?php if ($product['sale_price'] && $product['sale_price'] < $product['price']): ?>
                            <div class="h4 text-danger mb-0"><?= formatPrice($product['sale_price']) ?></div>
                            <div class="text-muted text-decoration-line-through"><?= formatPrice($product['price']) ?></div>
                            <div class="badge bg-danger">Tiết kiệm <?= formatPrice($product['price'] - $product['sale_price']) ?></div>
                        <?php else: ?>
                            <div class="h4 text-primary mb-0"><?= formatPrice($product['price']) ?></div>
                        <?php endif; ?>
                    </div>
                </div>
                
                <!-- Description -->
                <?php if ($product['short_description']): ?>
                    <div class="mb-4">
                        <p class="text-muted"><?= nl2br(htmlspecialchars($product['short_description'])) ?></p>
                    </div>
                <?php endif; ?>
                
                <!-- Stock Status -->
                <div class="mb-4">
                    <?php if ($product['stock_quantity'] > 0): ?>
                        <div class="text-success">
                            <i class="fas fa-check-circle me-1"></i>
                            Còn <?= $product['stock_quantity'] ?> sản phẩm
                        </div>
                    <?php else: ?>
                        <div class="text-danger">
                            <i class="fas fa-times-circle me-1"></i>
                            Hết hàng
                        </div>
                    <?php endif; ?>
                </div>
                
                <!-- Add to Cart Form -->
                <form method="POST" id="addToCartForm">
                    <!-- Quantity -->
                    <div class="mb-4">
                        <label class="form-label fw-bold">Số lượng:</label>
                        <div class="d-flex align-items-center gap-3">
                            <div class="input-group quantity-controls">
                                <button class="btn btn-outline-secondary" type="button" onclick="changeQuantity(-1)">-</button>
                                <input type="number" class="form-control text-center" name="quantity" id="quantity" value="1" min="1" max="<?= $product['stock_quantity'] ?>">
                                <button class="btn btn-outline-secondary" type="button" onclick="changeQuantity(1)">+</button>
                            </div>
                            <small class="text-muted">Tối đa: <?= $product['stock_quantity'] ?></small>
                        </div>
                    </div>
                    
                    <!-- Add to Cart Button -->
                    <div class="d-grid gap-2 d-md-flex">
                        <button type="button" class="btn btn-primary btn-lg flex-grow-1" 
                                id="addToCartBtn" onclick="addToCart()" 
                                <?= $product['stock_quantity'] <= 0 ? 'disabled' : '' ?>>
                            <i class="fas fa-shopping-cart me-2"></i>
                            <?= $product['stock_quantity'] <= 0 ? 'Hết hàng' : 'Thêm vào giỏ hàng' ?>
                        </button>
                        <button type="button" class="btn btn-outline-secondary btn-lg">
                            <i class="fas fa-heart"></i>
                        </button>
                    </div>
                    
                    <input type="hidden" name="action" value="add_to_cart">
                </form>
                
                <!-- Product Info -->
                <div class="mt-4">
                    <div class="row g-3">
                        <div class="col-6">
                            <div class="d-flex align-items-center text-muted">
                                <i class="fas fa-truck me-2"></i>
                                <small>Miễn phí vận chuyển</small>
                            </div>
                        </div>
                        <div class="col-6">
                            <div class="d-flex align-items-center text-muted">
                                <i class="fas fa-undo me-2"></i>
                                <small>Đổi trả 7 ngày</small>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        
        <!-- Product Description -->
        <?php if ($product['description']): ?>
            <div class="row mt-5">
                <div class="col-12">
                    <div class="card">
                        <div class="card-header">
                            <h5 class="mb-0">Mô tả sản phẩm</h5>
                        </div>
                        <div class="card-body">
                            <?= nl2br(htmlspecialchars($product['description'])) ?>
                        </div>
                    </div>
                </div>
            </div>
        <?php endif; ?>
        
        <!-- Related Products -->
        <?php if (!empty($related_products)): ?>
            <div class="row mt-5">
                <div class="col-12">
                    <h3 class="mb-4">Sản phẩm liên quan</h3>
                    <div class="row">
                        <?php foreach ($related_products as $related): ?>
                            <div class="col-lg-3 col-md-4 col-sm-6 mb-4">
                                <div class="card h-100 product-card">
                                    <div class="position-relative">
                                        <img src="<?= getImageUrl($related['main_image']) ?>" 
                                             class="card-img-top" 
                                             alt="<?= htmlspecialchars($related['name']) ?>"
                                             style="height: 200px; object-fit: cover;"
                                             onerror="this.src='/tktshop/uploads/products/no-image.jpg'">
                                        
                                        <?php if ($related['sale_price'] && $related['sale_price'] < $related['price']): ?>
                                            <?php $discount = round((($related['price'] - $related['sale_price']) / $related['price']) * 100); ?>
                                            <span class="badge bg-danger position-absolute" style="top: 10px; right: 10px;">
                                                -<?= $discount ?>%
                                            </span>
                                        <?php endif; ?>
                                    </div>
                                    
                                    <div class="card-body d-flex flex-column">
                                        <h6 class="card-title">
                                            <a href="product_detail.php?id=<?= $related['id'] ?>" 
                                               class="text-decoration-none text-dark">
                                                <?= htmlspecialchars($related['name']) ?>
                                            </a>
                                        </h6>
                                        
                                        <div class="mt-auto">
                                            <div class="d-flex align-items-center justify-content-between">
                                                <div>
                                                    <?php if ($related['sale_price'] && $related['sale_price'] < $related['price']): ?>
                                                        <div class="fw-bold text-danger"><?= formatPrice($related['sale_price']) ?></div>
                                                        <small class="text-muted text-decoration-line-through"><?= formatPrice($related['price']) ?></small>
                                                    <?php else: ?>
                                                        <div class="fw-bold text-primary"><?= formatPrice($related['price']) ?></div>
                                                    <?php endif; ?>
                                                </div>
                                                <div>
                                                    <?php if ($related['stock_quantity'] > 0): ?>
                                                        <small class="text-success">Còn hàng</small>
                                                    <?php else: ?>
                                                        <small class="text-danger">Hết hàng</small>
                                                    <?php endif; ?>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </div>
            </div>
        <?php endif; ?>
    </div>
    
    <!-- Scripts -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
    
    <script>
        // Quantity controls
        function changeQuantity(delta) {
            const quantityInput = document.getElementById('quantity');
            const currentValue = parseInt(quantityInput.value);
            const maxValue = parseInt(quantityInput.max);
            const newValue = Math.max(1, Math.min(maxValue, currentValue + delta));
            quantityInput.value = newValue;
        }
        
        // Add to cart
        function addToCart() {
            console.log('🛒 Adding to cart...');
            
            const quantity = parseInt(document.getElementById('quantity').value);
            const maxStock = <?= $product['stock_quantity'] ?>;
            
            if (quantity > maxStock) {
                showToast('Số lượng vượt quá tồn kho!', 'error');
                return;
            }
            
            const btn = document.getElementById('addToCartBtn');
            const originalText = btn.innerHTML;
            btn.innerHTML = '<i class="fas fa-spinner fa-spin me-2"></i>Đang thêm...';
            btn.disabled = true;
            
            const formData = new FormData();
            formData.append('action', 'add_to_cart');
            formData.append('quantity', quantity);
            
            fetch(window.location.href, {
                method: 'POST',
                body: formData
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    showToast(data.message, 'success');
                    if (data.cart_count) {
                        updateCartCount(data.cart_count);
                    }
                } else {
                    showToast(data.message, 'error');
                }
            })
            .catch(error => {
                console.error('Error:', error);
                showToast('Có lỗi xảy ra!', 'error');
            })
            .finally(() => {
                btn.innerHTML = originalText;
                btn.disabled = false;
            });
        }
        
        // Show toast notification
        function showToast(message, type = 'info') {
            let toastContainer = document.getElementById('toastContainer');
            if (!toastContainer) {
                toastContainer = document.createElement('div');
                toastContainer.id = 'toastContainer';
                toastContainer.className = 'position-fixed top-0 end-0 p-3';
                toastContainer.style.zIndex = '9999';
                document.body.appendChild(toastContainer);
            }
            
            const toastId = 'toast-' + Date.now();
            const bgClass = type === 'success' ? 'bg-success' : type === 'error' ? 'bg-danger' : 'bg-info';
            const iconClass = type === 'success' ? 'fa-check-circle' : type === 'error' ? 'fa-exclamation-circle' : 'fa-info-circle';
            
            const toastHtml = `
                <div id="${toastId}" class="toast align-items-center text-white ${bgClass} border-0" role="alert">
                    <div class="d-flex">
                        <div class="toast-body">
                            <i class="fas ${iconClass} me-2"></i>
                            ${message}
                        </div>
                        <button type="button" class="btn-close btn-close-white me-2 m-auto" data-bs-dismiss="toast"></button>
                    </div>
                </div>
            `;
            
            toastContainer.insertAdjacentHTML('beforeend', toastHtml);
            const toast = new bootstrap.Toast(document.getElementById(toastId));
            toast.show();
            
            setTimeout(() => {
                const toastElement = document.getElementById(toastId);
                if (toastElement) {
                    toastElement.remove();
                }
            }, 5000);
        }
        
        // Update cart count
        function updateCartCount(count) {
            const cartCountElements = document.querySelectorAll('.cart-count, [id*="cart-count"]');
            cartCountElements.forEach(element => {
                element.textContent = count;
                element.style.display = count > 0 ? 'inline' : 'none';
            });
        }
    </script>
</body>
</html>