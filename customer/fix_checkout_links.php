<?php
/**
 * Sửa lỗi link thanh toán trong cart_fixed.php
 * Tìm và thay thế các đoạn code sau trong file cart_fixed.php
 */

// ❌ ĐOẠN CODE SAI (cần tìm và sửa trong cart_fixed.php):
?>

<!-- Các link có thể bị sai trong cart_fixed.php: -->

<!-- 1. Nút thanh toán chính -->
<!-- ❌ SAI -->
<a href="cart.php" class="btn btn-primary">Thanh toán</a>
<a href="/tktshop/customer/cart.php" class="btn btn-primary">Thanh toán</a>
<button onclick="window.location.href='cart.php'" class="btn btn-primary">Thanh toán</button>

<!-- ✅ ĐÚNG -->
<a href="checkout.php" class="btn btn-primary">Thanh toán</a>
<a href="/tktshop/customer/checkout.php" class="btn btn-primary">Thanh toán</a>
<button onclick="window.location.href='checkout.php'" class="btn btn-primary">Thanh toán</button>

<!-- 2. Form thanh toán -->
<!-- ❌ SAI -->
<form action="cart.php" method="POST">
    <button type="submit" class="btn btn-success">Tiến hành thanh toán</button>
</form>

<!-- ✅ ĐÚNG -->
<form action="checkout.php" method="POST">
    <button type="submit" class="btn btn-success">Tiến hành thanh toán</button>
</form>

<!-- 3. JavaScript redirect -->
<!-- ❌ SAI -->
<script>
function proceedToCheckout() {
    window.location.href = 'cart.php';
}
</script>

<!-- ✅ ĐÚNG -->
<script>
function proceedToCheckout() {
    window.location.href = 'checkout.php';
}
</script>

<!-- 4. Đoạn code thanh toán hoàn chỉnh -->
<div class="checkout-section">
    <div class="row">
        <div class="col-md-8">
            <div class="d-flex justify-content-between">
                <a href="products_fixed.php" class="btn btn-outline-primary">
                    <i class="fas fa-arrow-left me-2"></i>Tiếp tục mua sắm
                </a>
            </div>
        </div>
        <div class="col-md-4">
            <div class="card">
                <div class="card-body">
                    <h5 class="card-title">Tóm tắt đơn hàng</h5>
                    <div class="d-flex justify-content-between">
                        <span>Tạm tính:</span>
                        <span id="subtotal"><?= number_format($tong_tien) ?>đ</span>
                    </div>
                    <div class="d-flex justify-content-between">
                        <span>Phí vận chuyển:</span>
                        <span id="shipping"><?= $mien_phi_ship ? 'Miễn phí' : number_format($phi_ship) . 'đ' ?></span>
                    </div>
                    <hr>
                    <div class="d-flex justify-content-between fw-bold">
                        <span>Tổng cộng:</span>
                        <span id="total"><?= number_format($tong_tien + $phi_ship) ?>đ</span>
                    </div>
                    
                    <!-- ✅ LINK ĐÚNG ĐẾN CHECKOUT -->
                    <a href="checkout.php" class="btn btn-primary w-100 mt-3" id="checkout-btn">
                        <i class="fas fa-credit-card me-2"></i>Thanh toán
                    </a>
                </div>
            </div>
        </div>
    </div>
</div>

<?php
/**
 * SCRIPT TỰ ĐỘNG SỬA FILE CART_FIXED.PHP
 * Tạo file này: customer/fix_checkout_links.php
 */
?>

<!DOCTYPE html>
<html>
<head>
    <title>Fix Checkout Links</title>
    <style>
        body { font-family: Arial; margin: 20px; }
        .success { color: green; background: #d4edda; padding: 10px; border-radius: 5px; margin: 10px 0; }
        .error { color: red; background: #f8d7da; padding: 10px; border-radius: 5px; margin: 10px 0; }
        .code { background: #f8f9fa; padding: 10px; border-radius: 5px; font-family: monospace; }
    </style>
</head>
<body>
    <h1>🔧 Fix Checkout Links</h1>
    
    <?php
    // Script tự động sửa links trong cart_fixed.php
    $cart_file = 'cart_fixed.php';
    
    if (!file_exists($cart_file)) {
        echo "<div class='error'>❌ File $cart_file không tồn tại!</div>";
        exit;
    }
    
    // Backup file trước khi sửa
    copy($cart_file, $cart_file . '.backup');
    echo "<div class='success'>✅ Đã backup file: $cart_file.backup</div>";
    
    // Đọc nội dung file
    $content = file_get_contents($cart_file);
    $original_content = $content;
    
    // Các pattern cần sửa
    $replacements = [
        // Links trực tiếp
        'href="cart.php"' => 'href="checkout.php"',
        "href='cart.php'" => "href='checkout.php'",
        'href="/tktshop/customer/cart.php"' => 'href="/tktshop/customer/checkout.php"',
        "href='/tktshop/customer/cart.php'" => "href='/tktshop/customer/checkout.php'",
        
        // Form actions
        'action="cart.php"' => 'action="checkout.php"',
        "action='cart.php'" => "action='checkout.php'",
        'action="/tktshop/customer/cart.php"' => 'action="/tktshop/customer/checkout.php"',
        
        // JavaScript
        "window.location.href = 'cart.php'" => "window.location.href = 'checkout.php'",
        'window.location.href = "cart.php"' => 'window.location.href = "checkout.php"',
        "location.href = 'cart.php'" => "location.href = 'checkout.php'",
        'location.href = "cart.php"' => 'location.href = "checkout.php"',
        
        // Các pattern khác
        'window.location="cart.php"' => 'window.location="checkout.php"',
        "window.location='cart.php'" => "window.location='checkout.php'",
    ];
    
    $changes_made = 0;
    foreach ($replacements as $search => $replace) {
        $new_content = str_replace($search, $replace, $content);
        if ($new_content !== $content) {
            $content = $new_content;
            $changes_made++;
            echo "<div class='success'>✅ Đã sửa: $search → $replace</div>";
        }
    }
    
    // Lưu file đã sửa
    if ($changes_made > 0) {
        file_put_contents($cart_file, $content);
        echo "<div class='success'>🎉 Đã sửa $changes_made link(s) trong file $cart_file</div>";
        echo "<div class='success'>✅ File đã được cập nhật thành công!</div>";
    } else {
        echo "<div class='error'>⚠️ Không tìm thấy link nào cần sửa</div>";
        echo "<div>Có thể link đã đúng hoặc pattern khác với dự kiến</div>";
    }
    
    echo "<h3>🧪 Test ngay:</h3>";
    echo "<a href='cart_fixed.php' style='background: #007bff; color: white; padding: 10px 20px; text-decoration: none; border-radius: 5px; margin: 5px;'>Test Cart</a>";
    echo "<a href='checkout.php' style='background: #28a745; color: white; padding: 10px 20px; text-decoration: none; border-radius: 5px; margin: 5px;'>Test Checkout</a>";
    
    // Hiển thị nội dung để kiểm tra thủ công
    echo "<h3>🔍 Kiểm tra thủ công:</h3>";
    echo "<div>Tìm các đoạn chứa 'thanh toán', 'checkout', 'tiến hành' trong file:</div>";
    echo "<div class='code'>";
    $lines = explode("\n", $content);
    foreach ($lines as $i => $line) {
        if (stripos($line, 'thanh toán') !== false || 
            stripos($line, 'checkout') !== false || 
            stripos($line, 'tiến hành') !== false ||
            stripos($line, 'href=') !== false) {
            echo "Line " . ($i + 1) . ": " . htmlspecialchars($line) . "<br>";
        }
    }
    echo "</div>";
    ?>
</body>
</html>