<?php
/**
 * customer/cart.php - Simple Session-based Cart
 * Giỏ hàng đơn giản dùng session thay vì database phức tạp
 */

session_start();
require_once '../config/database.php';
require_once '../config/config.php';

// Khởi tạo cart trong session
if (!isset($_SESSION['cart'])) {
    $_SESSION['cart'] = [];
}

$errors = [];
$success = '';

// Xử lý AJAX
if ($_SERVER['REQUEST_METHOD'] == 'POST' && !empty($_POST['action'])) {
    header('Content-Type: application/json');
    
    $action = $_POST['action'];
    
    switch ($action) {
        case 'update_quantity':
            $product_id = (int)$_POST['product_id'];
            $quantity = max(1, (int)$_POST['quantity']);
            
            foreach ($_SESSION['cart'] as $key => $item) {
                if ($item['product_id'] == $product_id) {
                    $_SESSION['cart'][$key]['quantity'] = $quantity;
                    break;
                }
            }
            
            echo json_encode(['success' => true, 'message' => 'Cập nhật thành công']);
            exit;
            
        case 'remove_item':
            $product_id = (int)$_POST['product_id'];
            
            foreach ($_SESSION['cart'] as $key => $item) {
                if ($item['product_id'] == $product_id) {
                    unset($_SESSION['cart'][$key]);
                    break;
                }
            }
            
            // Reindex array
            $_SESSION['cart'] = array_values($_SESSION['cart']);
            
            echo json_encode(['success' => true, 'message' => 'Đã xóa sản phẩm']);
            exit;
            
        case 'clear_cart':
            $_SESSION['cart'] = [];
            echo json_encode(['success' => true, 'message' => 'Đã xóa tất cả sản phẩm']);
            exit;
    }
}

// Tính tổng
$total_items = 0;
$total_amount = 0;

foreach ($_SESSION['cart'] as $item) {
    $total_items += $item['quantity'];
    $total_amount += $item['price'] * $item['quantity'];
}

$shipping_fee = $total_amount >= 500000 ? 0 : 30000;
$tax = $total_amount * 0.1;
$final_total = $total_amount + $shipping_fee + $tax;

function formatPrice($price) {
    return number_format($price, 0, ',', '.') . '₫';
}
?>

<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Giỏ hàng (<?= $total_items ?>) - TKT Shop</title>
    
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    
    <style>
        .cart-item {
            border: 1px solid #eee;
            border-radius: 8px;
            padding: 20px;
            margin-bottom: 15px;
            background: white;
        }
        
        .product-image {
            width: 80px;
            height: 80px;
            object-fit: cover;
            border-radius: 8px;
        }
        
        .quantity-controls {
            display: flex;
            align-items: center;
            max-width: 120px;
        }
        
        .quantity-controls input {
            text-align: center;
            border-left: none;
            border-right: none;
        }
        
        .quantity-controls button {
            width: 35px;
            height: 35px;
            padding: 0;
        }
        
        .cart-summary {
            background: #f8f9fa;
            border-radius: 10px;
            padding: 25px;
            position: sticky;
            top: 20px;
        }
    </style>
</head>
<body>
    <div class="container py-4">
        <!-- Breadcrumb -->
        <nav aria-label="breadcrumb" class="mb-4">
            <ol class="breadcrumb">
                <li class="breadcrumb-item"><a href="/tktshop/">Trang chủ</a></li>
                <li class="breadcrumb-item active">Giỏ hàng</li>
            </ol>
        </nav>
        
        <div class="row">
            <div class="col-lg-8">
                <div class="d-flex justify-content-between align-items-center mb-4">
                    <h2>Giỏ hàng của bạn</h2>
                    <?php if (!empty($_SESSION['cart'])): ?>
                        <button class="btn btn-outline-danger btn-sm" onclick="clearCart()">
                            <i class="fas fa-trash me-1"></i>
                            Xóa tất cả
                        </button>
                    <?php endif; ?>
                </div>
                
                <?php if (empty($_SESSION['cart'])): ?>
                    <!-- Empty Cart -->
                    <div class="text-center py-5">
                        <i class="fas fa-shopping-cart fa-4x text-muted mb-4"></i>
                        <h4>Giỏ hàng của bạn đang trống</h4>
                        <p class="text-muted mb-4">Khám phá các sản phẩm tuyệt vời và thêm vào giỏ hàng ngay!</p>
                        <a href="products.php" class="btn btn-primary">
                            <i class="fas fa-shopping-bag me-2"></i>
                            Tiếp tục mua sắm
                        </a>
                    </div>
                <?php else: ?>
                    
                    <!-- Cart Items -->
                    <div id="cartItems">
                        <?php foreach ($_SESSION['cart'] as $index => $item): ?>
                            <div class="cart-item" data-product-id="<?= $item['product_id'] ?>">
                                <div class="row align-items-center">
                                    <!-- Product Image -->
                                    <div class="col-md-2 col-3">
                                        <img src="/tktshop/uploads/products/<?= $item['image'] ?>" 
                                             alt="<?= htmlspecialchars($item['name']) ?>"
                                             class="product-image"
                                             onerror="this.src='/tktshop/uploads/products/no-image.jpg'">
                                    </div>
                                    
                                    <!-- Product Info -->
                                    <div class="col-md-4 col-9">
                                        <h6 class="mb-1">
                                            <a href="product_detail.php?id=<?= $item['product_id'] ?>" 
                                               class="text-decoration-none text-dark">
                                                <?= htmlspecialchars($item['name']) ?>
                                            </a>
                                        </h6>
                                        <small class="text-muted">Còn lại: <?= $item['stock'] ?> sản phẩm</small>
                                    </div>
                                    
                                    <!-- Price -->
                                    <div class="col-md-2 col-4 text-center">
                                        <div class="fw-bold text-primary"><?= formatPrice($item['price']) ?></div>
                                    </div>
                                    
                                    <!-- Quantity Controls -->
                                    <div class="col-md-2 col-4">
                                        <div class="quantity-controls input-group">
                                            <button class="btn btn-outline-secondary" 
                                                    type="button" 
                                                    onclick="updateQuantity(<?= $item['product_id'] ?>, <?= $item['quantity'] - 1 ?>)"
                                                    <?= $item['quantity'] <= 1 ? 'disabled' : '' ?>>
                                                -
                                            </button>
                                            <input type="number" 
                                                   class="form-control" 
                                                   value="<?= $item['quantity'] ?>"
                                                   min="1" 
                                                   max="<?= $item['stock'] ?>"
                                                   onchange="updateQuantity(<?= $item['product_id'] ?>, this.value)">
                                            <button class="btn btn-outline-secondary" 
                                                    type="button"
                                                    onclick="updateQuantity(<?= $item['product_id'] ?>, <?= $item['quantity'] + 1 ?>)"
                                                    <?= $item['quantity'] >= $item['stock'] ? 'disabled' : '' ?>>
                                                +
                                            </button>
                                        </div>
                                    </div>
                                    
                                    <!-- Subtotal & Remove -->
                                    <div class="col-md-2 col-4 text-center">
                                        <div class="fw-bold mb-2"><?= formatPrice($item['price'] * $item['quantity']) ?></div>
                                        <button class="btn btn-sm btn-outline-danger" 
                                                onclick="removeItem(<?= $item['product_id'] ?>)">
                                            <i class="fas fa-trash"></i>
                                        </button>
                                    </div>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                    
                    <!-- Continue Shopping -->
                    <div class="mt-4">
                        <a href="products.php" class="btn btn-outline-primary">
                            <i class="fas fa-arrow-left me-2"></i>
                            Tiếp tục mua sắm
                        </a>
                    </div>
                <?php endif; ?>
            </div>
            
            <?php if (!empty($_SESSION['cart'])): ?>
                <!-- Cart Summary -->
                <div class="col-lg-4">
                    <div class="cart-summary">
                        <h5 class="mb-3">Tóm tắt đơn hàng</h5>
                        
                        <!-- Free Shipping Progress -->
                        <?php if ($total_amount < 500000): ?>
                            <div class="mb-3">
                                <div class="d-flex justify-content-between mb-2">
                                    <small>Mua thêm để được miễn phí ship:</small>
                                    <small class="fw-bold text-primary">
                                        <?= formatPrice(500000 - $total_amount) ?>
                                    </small>
                                </div>
                                <div class="progress mb-2">
                                    <div class="progress-bar bg-success" 
                                         style="width: <?= ($total_amount / 500000) * 100 ?>%">
                                    </div>
                                </div>
                                <small class="text-muted">
                                    <?= formatPrice($total_amount) ?> / 500.000₫
                                </small>
                            </div>
                        <?php else: ?>
                            <div class="alert alert-success py-2 mb-3">
                                <i class="fas fa-check-circle me-1"></i>
                                <small>Bạn được miễn phí vận chuyển!</small>
                            </div>
                        <?php endif; ?>
                        
                        <!-- Order Summary -->
                        <div class="border-top pt-3">
                            <div class="d-flex justify-content-between mb-2">
                                <span>Tạm tính (<?= $total_items ?> sản phẩm):</span>
                                <span><?= formatPrice($total_amount) ?></span>
                            </div>
                            
                            <div class="d-flex justify-content-between mb-2">
                                <span>Phí vận chuyển:</span>
                                <span><?= $shipping_fee == 0 ? 'Miễn phí' : formatPrice($shipping_fee) ?></span>
                            </div>
                            
                            <div class="d-flex justify-content-between mb-2">
                                <span>Thuế (10%):</span>
                                <span><?= formatPrice($tax) ?></span>
                            </div>
                            
                            <hr>
                            
                            <div class="d-flex justify-content-between mb-3">
                                <strong>Tổng cộng:</strong>
                                <strong class="text-primary fs-5"><?= formatPrice($final_total) ?></strong>
                            </div>
                            
                            <div class="d-grid gap-2">
                                <a href="checkout.php" class="btn btn-primary btn-lg">
                                    <i class="fas fa-credit-card me-2"></i>
                                    Thanh toán
                                </a>
                                <button class="btn btn-outline-secondary">
                                    <i class="fas fa-bookmark me-2"></i>
                                    Lưu để mua sau
                                </button>
                            </div>
                        </div>
                    </div>
                </div>
            <?php endif; ?>
        </div>
    </div>
    
    <!-- Scripts -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
    
    <script>
        // Update quantity
        function updateQuantity(productId, newQuantity) {
            if (newQuantity < 1) return;
            
            const formData = new FormData();
            formData.append('action', 'update_quantity');
            formData.append('product_id', productId);
            formData.append('quantity', newQuantity);
            
            fetch('cart.php', {
                method: 'POST',
                body: formData
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    location.reload(); // Simple reload to update totals
                } else {
                    showToast(data.message, 'error');
                }
            })
            .catch(error => {
                console.error('Error:', error);
                showToast('Có lỗi xảy ra', 'error');
            });
        }
        
        // Remove item
        function removeItem(productId) {
            if (!confirm('Bạn có chắc muốn xóa sản phẩm này?')) return;
            
            const formData = new FormData();
            formData.append('action', 'remove_item');
            formData.append('product_id', productId);
            
            fetch('cart.php', {
                method: 'POST',
                body: formData
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    location.reload();
                } else {
                    showToast(data.message, 'error');
                }
            })
            .catch(error => {
                console.error('Error:', error);
                showToast('Có lỗi xảy ra', 'error');
            });
        }
        
        // Clear cart
        function clearCart() {
            if (!confirm('Bạn có chắc muốn xóa tất cả sản phẩm?')) return;
            
            const formData = new FormData();
            formData.append('action', 'clear_cart');
            
            fetch('cart.php', {
                method: 'POST',
                body: formData
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    location.reload();
                } else {
                    showToast(data.message, 'error');
                }
            })
            .catch(error => {
                console.error('Error:', error);
                showToast('Có lỗi xảy ra', 'error');
            });
        }
        
        // Show toast notification
        function showToast(message, type = 'info') {
            const alert = document.createElement('div');
            alert.className = `alert alert-${type === 'error' ? 'danger' : type} alert-dismissible fade show position-fixed`;
            alert.style.cssText = 'top: 20px; right: 20px; z-index: 9999; min-width: 300px;';
            alert.innerHTML = `
                ${message}
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            `;
            
            document.body.appendChild(alert);
            
            setTimeout(() => {
                if (alert.parentNode) {
                    alert.parentNode.removeChild(alert);
                }
            }, 5000);
        }
    </script>
</body>
</html>