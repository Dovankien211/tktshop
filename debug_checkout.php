<?php
// debug_checkout.php - Debug file để kiểm tra lỗi checkout
require_once 'config/database.php';
require_once 'config/config.php';

echo "<h2>Debug Checkout</h2>";

// Kiểm tra session
echo "<h3>Session Info:</h3>";
echo "<pre>";
print_r($_SESSION);
echo "</pre>";

// Kiểm tra customer_id và session_id
$customer_id = $_SESSION['customer_id'] ?? null;
$session_id = $customer_id ? null : ($_SESSION['session_id'] ?? session_id());

echo "<h3>Customer ID: " . ($customer_id ?? 'NULL') . "</h3>";
echo "<h3>Session ID: " . $session_id . "</h3>";

// Kiểm tra giỏ hàng
echo "<h3>Cart Items:</h3>";
try {
    $stmt = $pdo->prepare("
        SELECT gh.*, sp.id as san_pham_id, sp.ten_san_pham, sp.hinh_anh_chinh, sp.slug, sp.thuong_hieu,
               bsp.ma_sku, bsp.gia_ban as gia_hien_tai, bsp.so_luong_ton_kho,
               kc.kich_co, ms.ten_mau, ms.ma_mau,
               (gh.so_luong * gh.gia_tai_thoi_diem) as thanh_tien
        FROM gio_hang gh
        JOIN bien_the_san_pham bsp ON gh.bien_the_id = bsp.id
        JOIN san_pham_chinh sp ON bsp.san_pham_id = sp.id
        JOIN kich_co kc ON bsp.kich_co_id = kc.id
        JOIN mau_sac ms ON bsp.mau_sac_id = ms.id
        WHERE (gh.khach_hang_id = ? OR gh.session_id = ?) 
        AND bsp.trang_thai = 'hoat_dong' 
        AND sp.trang_thai = 'hoat_dong'
        AND bsp.so_luong_ton_kho >= gh.so_luong
        ORDER BY gh.ngay_them DESC
    ");
    $stmt->execute([$customer_id, $session_id]);
    $cart_items = $stmt->fetchAll();
    
    echo "<pre>";
    print_r($cart_items);
    echo "</pre>";
    
    if (empty($cart_items)) {
        echo "<p style='color: red;'>Giỏ hàng trống!</p>";
    } else {
        echo "<p style='color: green;'>Có " . count($cart_items) . " sản phẩm trong giỏ hàng</p>";
    }
    
} catch (Exception $e) {
    echo "<p style='color: red;'>Lỗi: " . $e->getMessage() . "</p>";
}

// Kiểm tra database connection
echo "<h3>Database Connection:</h3>";
try {
    $pdo->query("SELECT 1");
    echo "<p style='color: green;'>Database connection OK</p>";
} catch (Exception $e) {
    echo "<p style='color: red;'>Database connection failed: " . $e->getMessage() . "</p>";
}

// Kiểm tra các hàm helper
echo "<h3>Helper Functions:</h3>";
echo "<p>formatPrice function exists: " . (function_exists('formatPrice') ? 'YES' : 'NO') . "</p>";
echo "<p>redirect function exists: " . (function_exists('redirect') ? 'YES' : 'NO') . "</p>";
echo "<p>alert function exists: " . (function_exists('alert') ? 'YES' : 'NO') . "</p>";
echo "<p>generateOrderCode function exists: " . (function_exists('generateOrderCode') ? 'YES' : 'NO') . "</p>";

// Test formatPrice
if (function_exists('formatPrice')) {
    echo "<p>formatPrice(1000000): " . formatPrice(1000000) . "</p>";
}

// Test generateOrderCode
if (function_exists('generateOrderCode')) {
    echo "<p>generateOrderCode(): " . generateOrderCode() . "</p>";
}

echo "<h3>PHP Info:</h3>";
echo "<p>PHP Version: " . PHP_VERSION . "</p>";
echo "<p>Session Status: " . session_status() . "</p>";
echo "<p>Current URL: " . $_SERVER['REQUEST_URI'] . "</p>";
?> 