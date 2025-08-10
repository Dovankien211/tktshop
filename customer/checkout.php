<?php
/**
 * customer/checkout.php - Simple Session-based Checkout
 * Thanh toán đơn giản dùng session cart
 */

session_start();
require_once '../config/database.php';
require_once '../config/config.php';

// Kiểm tra cart
if (empty($_SESSION['cart'])) {
    header('Location: cart.php');
    exit;
}

$errors = [];
$form_data = [];

// Tính tổng từ session cart
$total_items = 0;
$total_amount = 0;

foreach ($_SESSION['cart'] as $item) {
    $total_items += $item['quantity'];
    $total_amount += $item['price'] * $item['quantity'];
}

$shipping_fee = $total_amount >= 500000 ? 0 : 30000;
$tax = $total_amount * 0.1;
$final_total = $total_amount + $shipping_fee + $tax;

// Xử lý đặt hàng
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $form_data = [
        'full_name' => trim($_POST['full_name'] ?? ''),
        'phone' => trim($_POST['phone'] ?? ''),
        'email' => trim($_POST['email'] ?? ''),
        'address' => trim($_POST['address'] ?? ''),
        'notes' => trim($_POST['notes'] ?? ''),
        'payment_method' => $_POST['payment_method'] ?? 'cod',
        'shipping_method' => $_POST['shipping_method'] ?? 'standard'
    ];
    
    // Validate
    if (empty($form_data['full_name'])) {
        $errors[] = 'Họ tên không được để trống';
    }
    
    if (empty($form_data['phone'])) {
        $errors[] = 'Số điện thoại không được để trống';
    } elseif (!preg_match('/^[0-9]{10,11}$/', $form_data['phone'])) {
        $errors[] = 'Số điện thoại không hợp lệ';
    }
    
    if (!empty($form_data['email']) && !filter_var($form_data['email'], FILTER_VALIDATE_EMAIL)) {
        $errors[] = 'Email không hợp lệ';
    }
    
    if (empty($form_data['address'])) {
        $errors[] = 'Địa chỉ giao hàng không được để trống';
    }
    
    // Nếu không có lỗi, tạo đơn hàng
    if (empty($errors)) {
        try {
            $pdo->beginTransaction();
            
            $order_code = 'DH' . date('YmdHis') . rand(100, 999);
            
            // Tạo bảng orders nếu chưa có
            $pdo->exec("
                CREATE TABLE IF NOT EXISTS orders (
                    id INT AUTO_INCREMENT PRIMARY KEY,
                    order_code VARCHAR(50) UNIQUE NOT NULL,
                    customer_name VARCHAR(255) NOT NULL,
                    customer_phone VARCHAR(20) NOT NULL,
                    customer_email VARCHAR(255),
                    customer_address TEXT NOT NULL,
                    notes TEXT,
                    subtotal DECIMAL(10,2) NOT NULL,
                    shipping_fee DECIMAL(10,2) NOT NULL,
                    tax DECIMAL(10,2) NOT NULL,
                    total DECIMAL(10,2) NOT NULL,
                    payment_method VARCHAR(20) NOT NULL,
                    shipping_method VARCHAR(20) NOT NULL,
                    payment_status VARCHAR(20) DEFAULT 'pending',
                    order_status VARCHAR(20) DEFAULT 'pending',
                    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
            ");
            
            // Insert đơn hàng
            $stmt = $pdo->prepare("
                INSERT INTO orders 
                (order_code, customer_name, customer_phone, customer_email, customer_address, 
                 notes, subtotal, shipping_fee, tax, total, payment_method, shipping_method, 
                 payment_status, order_status) 
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
            ");
            
            $stmt->execute([
                $order_code,
                $form_data['full_name'],
                $form_data['phone'],
                $form_data['email'],
                $form_data['address'],
                $form_data['notes'],
                $total_amount,
                $shipping_fee,
                $tax,
                $final_total,
                $form_data['payment_method'],
                $form_data['shipping_method'],
                $form_data['payment_method'] == 'vnpay' ? 'pending' : 'unpaid',
                'pending'
            ]);
            
            $order_id = $pdo->lastInsertId();
            
            // Tạo bảng order_items nếu chưa có
            $pdo->exec("
                CREATE TABLE IF NOT EXISTS order_items (
                    id INT AUTO_INCREMENT PRIMARY KEY,
                    order_id INT NOT NULL,
                    product_id INT NOT NULL,
                    product_name VARCHAR(255) NOT NULL,
                    product_image VARCHAR(255),
                    price DECIMAL(10,2) NOT NULL,
                    quantity INT NOT NULL,
                    subtotal DECIMAL(10,2) NOT NULL,
                    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                    FOREIGN KEY (order_id) REFERENCES orders(id) ON DELETE CASCADE
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
            ");
            
            // Insert chi tiết đơn hàng
            $stmt_items = $pdo->prepare("
                INSERT INTO order_items 
                (order_id, product_id, product_name, product_image, price, quantity, subtotal)
                VALUES (?, ?, ?, ?, ?, ?, ?)
            ");
            
            foreach ($_SESSION['cart'] as $item) {
                $stmt_items->execute([
                    $order_id,
                    $item['product_id'],
                    $item['name'],
                    $item['image'],
                    $item['price'],
                    $item['quantity'],
                    $item['price'] * $item['quantity']
                ]);
            }
            
            $pdo->commit();
            
            // Xử lý theo phương thức thanh toán
            if ($form_data['payment_method'] == 'vnpay') {
                // Lưu thông tin để chuyển đến VNPay
                $_SESSION['vnpay_order'] = [
                    'order_id' => $order_id,
                    'order_code' => $order_code,
                    'amount' => $final_total,
                    'order_info' => "Thanh toan don hang #" . $order_code,
                    'customer_name' => $form_data['full_name'],
                    'customer_email' => $form_data['email'],
                    'customer_phone' => $form_data['phone']
                ];
                
                // Xóa cart và chuyển đến VNPay
                $_SESSION['cart'] = [];
                header('Location: ../vnpay/create_payment.php');
                exit;
                
            } else {
                // COD: Cập nhật trạng thái và xóa cart
                $pdo->prepare("UPDATE orders SET order_status = 'confirmed' WHERE id = ?")
                    ->execute([$order_id]);
                
                $_SESSION['cart'] = [];
                $_SESSION['last_order'] = $order_code;
                
                header('Location: order_success.php?order=' . $order_code);
                exit;
            }
            
        } catch (Exception $e) {
            $pdo->rollback();
            $errors[] = 'Có lỗi xảy ra khi tạo đơn hàng: ' . $e->getMessage();
        }
    }
}

function formatPrice($price) {
    return number_format($price, 0, ',', '.') . '₫';
}
?>

<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Thanh toán - TKT Shop</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <style>
        .checkout-header {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            padding: 40px 0;
        }
        
        .checkout-section {
            background: white;
            border-radius: 10px;
            padding: 30px;
            margin-bottom: 20px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
        }
        
        .section-title {
            font-size: 1.25rem;
            font-weight: bold;
            margin-bottom: 20px;
            color: #495057;
            border-bottom: 2px solid #007bff;
            padding-bottom: 10px;
        }
        
        .payment-option {
            border: 2px solid #dee2e6;
            border-radius: 8px;
            padding: 15px;
            margin-bottom: 10px;
            cursor: pointer;
            transition: all 0.3s;
        }
        
        .payment-option:hover,
        .payment-option.selected {
            border-color: #007bff;
            background-color: #f8f9ff;
        }
        
        .order-summary {
            background: #f8f9fa;
            border-radius: 10px;
            padding: 25px;
            position: sticky;
            top: 20px;
        }
        
        .product-image {
            width: 50px;
            height: 50px;
            object-fit: cover;
            border-radius: 8px;
        }
    </style>
</head>
<body>
    <!-- Header -->
    <div class="checkout-header">
        <div class="container">
            <div class="text-center">
                <h2><i class="fas fa-credit-card me-2"></i>Thanh toán</h2>
                <p class="mb-0">Hoàn tất đơn hàng của bạn</p>
            </div>
        </div>
    </div>
    
    <div class="container py-4">
        <?php if (!empty($errors)): ?>
            <div class="alert alert-danger">
                <strong><i class="fas fa-exclamation-triangle me-2"></i>Có lỗi xảy ra:</strong>
                <ul class="mb-0 mt-2">
                    <?php foreach ($errors as $error): ?>
                        <li><?= htmlspecialchars($error) ?></li>
                    <?php endforeach; ?>
                </ul>
            </div>
        <?php endif; ?>
        
        <form method="POST" id="checkoutForm">
            <div class="row">
                <!-- Left Column -->
                <div class="col-lg-8">
                    <!-- Customer Information -->
                    <div class="checkout-section">
                        <div class="section-title">
                            <i class="fas fa-user me-2"></i>Thông tin người nhận
                        </div>
                        
                        <div class="row">
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label class="form-label">Họ và tên <span class="text-danger">*</span></label>
                                    <input type="text" class="form-control" name="full_name" 
                                           value="<?= htmlspecialchars($form_data['full_name'] ?? '') ?>" required>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label class="form-label">Số điện thoại <span class="text-danger">*</span></label>
                                    <input type="tel" class="form-control" name="phone" 
                                           value="<?= htmlspecialchars($form_data['phone'] ?? '') ?>" required>
                                </div>
                            </div>
                        </div>
                        
                        <div class="mb-3">
                            <label class="form-label">Email</label>
                            <input type="email" class="form-control" name="email" 
                                   value="<?= htmlspecialchars($form_data['email'] ?? '') ?>">
                            <div class="form-text">Email để nhận thông báo đơn hàng</div>
                        </div>
                        
                        <div class="mb-3">
                            <label class="form-label">Địa chỉ giao hàng <span class="text-danger">*</span></label>
                            <textarea class="form-control" name="address" rows="3" 
                                      placeholder="Số nhà, tên đường, phường/xã, quận/huyện, tỉnh/thành phố" 
                                      required><?= htmlspecialchars($form_data['address'] ?? '') ?></textarea>
                        </div>
                        
                        <div class="mb-3">
                            <label class="form-label">Ghi chú đơn hàng</label>
                            <textarea class="form-control" name="notes" rows="2" 
                                      placeholder="Ghi chú thêm cho đơn hàng (tuỳ chọn)"><?= htmlspecialchars($form_data['notes'] ?? '') ?></textarea>
                        </div>
                    </div>
                    
                    <!-- Shipping Method -->
                    <div class="checkout-section">
                        <div class="section-title">
                            <i class="fas fa-shipping-fast me-2"></i>Phương thức vận chuyển
                        </div>
                        
                        <div class="payment-option selected">
                            <div class="d-flex justify-content-between align-items-center">
                                <div>
                                    <input type="radio" name="shipping_method" value="standard" checked>
                                    <strong class="ms-2">Giao hàng tiêu chuẩn</strong>
                                    <div class="text-muted small mt-1">Giao trong 2-3 ngày</div>
                                </div>
                                <div class="text-end">
                                    <div class="fw-bold">
                                        <?= $total_amount >= 500000 ? 'Miễn phí' : formatPrice($shipping_fee) ?>
                                    </div>
                                    <div class="text-success small">Miễn phí từ 500k</div>
                                </div>
                            </div>
                        </div>
                    </div>
                    
                    <!-- Payment Method -->
                    <div class="checkout-section">
                        <div class="section-title">
                            <i class="fas fa-credit-card me-2"></i>Phương thức thanh toán
                        </div>
                        
                        <!-- VNPay Payment -->
                        <div class="payment-option selected" onclick="selectPayment('vnpay')">
                            <div class="d-flex align-items-center">
                                <input type="radio" name="payment_method" value="vnpay" checked>
                                <div class="ms-3">
                                    <div class="fw-bold text-primary">Thanh toán VNPay</div>
                                    <div class="text-muted small">
                                        Thanh toán qua ví điện tử, ngân hàng, QR Code
                                    </div>
                                    <div class="mt-1">
                                        <span class="badge bg-success me-1">An toàn</span>
                                        <span class="badge bg-info">Nhanh chóng</span>
                                    </div>
                                </div>
                            </div>
                        </div>
                        
                        <!-- COD Payment -->
                        <div class="payment-option" onclick="selectPayment('cod')">
                            <div class="d-flex align-items-center">
                                <input type="radio" name="payment_method" value="cod">
                                <div class="ms-3">
                                    <div class="fw-bold text-success">Thanh toán khi nhận hàng (COD)</div>
                                    <div class="text-muted small">
                                        Thanh toán tiền mặt khi shipper giao hàng
                                    </div>
                                    <div class="mt-1">
                                        <span class="badge bg-secondary">Tiền mặt</span>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                
                <!-- Right Column - Order Summary -->
                <div class="col-lg-4">
                    <div class="order-summary">
                        <h5 class="mb-4">
                            <i class="fas fa-receipt me-2"></i>Tóm tắt đơn hàng
                        </h5>
                        
                        <!-- Products -->
                        <div class="mb-4">
                            <?php foreach ($_SESSION['cart'] as $item): ?>
                                <div class="d-flex mb-3 pb-3 border-bottom">
                                    <div class="me-3">
                                        <img src="/tktshop/uploads/products/<?= $item['image'] ?>" 
                                             class="product-image" 
                                             alt="<?= htmlspecialchars($item['name']) ?>"
                                             onerror="this.src='/tktshop/uploads/products/no-image.jpg'">
                                    </div>
                                    <div class="flex-grow-1">
                                        <h6 class="mb-1"><?= htmlspecialchars($item['name']) ?></h6>
                                        <div class="d-flex justify-content-between">
                                            <span class="text-muted">x<?= $item['quantity'] ?></span>
                                            <span class="fw-bold"><?= formatPrice($item['price'] * $item['quantity']) ?></span>
                                        </div>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                        
                        <!-- Summary -->
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
                        
                        <?php if ($total_amount >= 500000): ?>
                            <div class="text-success small mb-2">
                                <i class="fas fa-check me-1"></i>Miễn phí vận chuyển
                            </div>
                        <?php endif; ?>
                        
                        <hr>
                        
                        <div class="d-flex justify-content-between mb-3">
                            <strong>Tổng cộng:</strong>
                            <strong class="text-primary fs-5"><?= formatPrice($final_total) ?></strong>
                        </div>
                        
                        <!-- Checkout Button -->
                        <div class="d-grid mt-4">
                            <button type="submit" class="btn btn-primary btn-lg" id="submitBtn">
                                <i class="fas fa-credit-card me-2"></i>
                                <span id="submitText">Thanh toán VNPay</span>
                            </button>
                        </div>
                        
                        <div class="text-center mt-3">
                            <a href="cart.php" class="text-muted text-decoration-none">
                                <i class="fas fa-arrow-left me-1"></i>Quay lại giỏ hàng
                            </a>
                        </div>
                        
                        <!-- Trust Badges -->
                        <div class="mt-4 pt-3 border-top">
                            <div class="row text-center">
                                <div class="col-4">
                                    <i class="fas fa-truck text-primary"></i>
                                    <div class="small text-muted mt-1">Giao hàng nhanh</div>
                                </div>
                                <div class="col-4">
                                    <i class="fas fa-shield-alt text-success"></i>
                                    <div class="small text-muted mt-1">Bảo mật</div>
                                </div>
                                <div class="col-4">
                                    <i class="fas fa-headset text-info"></i>
                                    <div class="small text-muted mt-1">Hỗ trợ 24/7</div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </form>
    </div>
    
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
    
    <script>
        // Select payment method
        function selectPayment(method) {
            // Update UI
            document.querySelectorAll('.payment-option').forEach(el => el.classList.remove('selected'));
            event.currentTarget.classList.add('selected');
            
            // Update radio
            document.querySelector(`input[name="payment_method"][value="${method}"]`).checked = true;
            
            // Update submit button text
            const submitText = document.getElementById('submitText');
            const submitIcon = document.querySelector('#submitBtn i');
            
            if (method === 'vnpay') {
                submitText.textContent = 'Thanh toán VNPay';
                submitIcon.className = 'fas fa-credit-card me-2';
            } else {
                submitText.textContent = 'Đặt hàng COD';
                submitIcon.className = 'fas fa-shopping-bag me-2';
            }
        }
        
        // Form validation
        document.getElementById('checkoutForm').addEventListener('submit', function(e) {
            const requiredFields = ['full_name', 'phone', 'address'];
            let isValid = true;
            
            requiredFields.forEach(fieldName => {
                const field = document.querySelector(`[name="${fieldName}"]`);
                if (!field.value.trim()) {
                    field.classList.add('is-invalid');
                    isValid = false;
                } else {
                    field.classList.remove('is-invalid');
                }
            });
            
            // Validate phone
            const phone = document.querySelector('[name="phone"]').value;
            if (phone && !/^[0-9]{10,11}$/.test(phone)) {
                document.querySelector('[name="phone"]').classList.add('is-invalid');
                isValid = false;
            }
            
            // Validate email if provided
            const email = document.querySelector('[name="email"]').value;
            if (email && !/^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(email)) {
                document.querySelector('[name="email"]').classList.add('is-invalid');
                isValid = false;
            }
            
            if (!isValid) {
                e.preventDefault();
                showToast('Vui lòng điền đầy đủ thông tin bắt buộc', 'error');
                return false;
            }
            
            // Show loading
            const submitBtn = document.getElementById('submitBtn');
            const originalText = submitBtn.innerHTML;
            submitBtn.innerHTML = '<i class="fas fa-spinner fa-spin me-2"></i>Đang xử lý...';
            submitBtn.disabled = true;
            
            // Re-enable after timeout
            setTimeout(() => {
                submitBtn.innerHTML = originalText;
                submitBtn.disabled = false;
            }, 10000);
        });
        
        // Phone number formatting
        document.querySelector('[name="phone"]').addEventListener('input', function() {
            this.value = this.value.replace(/[^0-9]/g, '');
        });
        
        // Real-time validation
        const inputs = document.querySelectorAll('input[required], textarea[required]');
        inputs.forEach(input => {
            input.addEventListener('blur', function() {
                if (!this.value.trim()) {
                    this.classList.add('is-invalid');
                } else {
                    this.classList.remove('is-invalid');
                }
            });
            
            input.addEventListener('input', function() {
                if (this.classList.contains('is-invalid') && this.value.trim()) {
                    this.classList.remove('is-invalid');
                }
            });
        });
        
        // Show toast
        function showToast(message, type = 'info') {
            const toast = document.createElement('div');
            toast.className = `alert alert-${type === 'error' ? 'danger' : type} alert-dismissible fade show position-fixed`;
            toast.style.cssText = 'top: 20px; right: 20px; z-index: 9999; min-width: 300px;';
            toast.innerHTML = `
                ${message}
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            `;
            
            document.body.appendChild(toast);
            
            setTimeout(() => {
                if (toast.parentNode) {
                    toast.parentNode.removeChild(toast);
                }
            }, 5000);
        }
    </script>
</body>
</html>