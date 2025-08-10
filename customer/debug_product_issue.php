<?php
/**
 * Debug file - Kiểm tra vấn đề xem chi tiết và thanh toán
 */

require_once '../config/database.php';

echo "<h2>🔍 DEBUG PRODUCT DETAIL & CHECKOUT ISSUES</h2>";

// 1. Kiểm tra cấu trúc bảng products
echo "<h3>1. Cấu trúc bảng PRODUCTS:</h3>";
try {
    $stmt = $pdo->query("DESCRIBE products");
    $columns = $stmt->fetchAll();
    echo "<table border='1' style='border-collapse: collapse;'>";
    echo "<tr><th>Column</th><th>Type</th><th>Null</th><th>Key</th><th>Default</th></tr>";
    foreach ($columns as $col) {
        echo "<tr>";
        echo "<td>{$col['Field']}</td>";
        echo "<td>{$col['Type']}</td>";
        echo "<td>{$col['Null']}</td>";
        echo "<td>{$col['Key']}</td>";
        echo "<td>{$col['Default']}</td>";
        echo "</tr>";
    }
    echo "</table>";
} catch (Exception $e) {
    echo "❌ Lỗi: " . $e->getMessage();
}

// 2. Kiểm tra cấu trúc bảng san_pham_chinh
echo "<h3>2. Cấu trúc bảng SAN_PHAM_CHINH:</h3>";
try {
    $stmt = $pdo->query("DESCRIBE san_pham_chinh");
    $columns = $stmt->fetchAll();
    echo "<table border='1' style='border-collapse: collapse;'>";
    echo "<tr><th>Column</th><th>Type</th><th>Null</th><th>Key</th><th>Default</th></tr>";
    foreach ($columns as $col) {
        echo "<tr>";
        echo "<td>{$col['Field']}</td>";
        echo "<td>{$col['Type']}</td>";
        echo "<td>{$col['Null']}</td>";
        echo "<td>{$col['Key']}</td>";
        echo "<td>{$col['Default']}</td>";
        echo "</tr>";
    }
    echo "</table>";
} catch (Exception $e) {
    echo "❌ Bảng san_pham_chinh không tồn tại: " . $e->getMessage();
}

// 3. Kiểm tra dữ liệu sản phẩm
echo "<h3>3. Dữ liệu sản phẩm trong bảng PRODUCTS:</h3>";
try {
    $stmt = $pdo->query("SELECT id, name, slug, price, sale_price, stock_quantity, status FROM products LIMIT 5");
    $products = $stmt->fetchAll();
    
    if ($products) {
        echo "<table border='1' style='border-collapse: collapse;'>";
        echo "<tr><th>ID</th><th>Name</th><th>Slug</th><th>Price</th><th>Sale Price</th><th>Stock</th><th>Status</th><th>Test Link</th></tr>";
        foreach ($products as $product) {
            $link_id = "product_detail.php?id={$product['id']}";
            $link_slug = $product['slug'] ? "product_detail.php?slug={$product['slug']}" : "No slug";
            
            echo "<tr>";
            echo "<td>{$product['id']}</td>";
            echo "<td>" . htmlspecialchars($product['name']) . "</td>";
            echo "<td>" . htmlspecialchars($product['slug'] ?: 'NULL') . "</td>";
            echo "<td>{$product['price']}</td>";
            echo "<td>{$product['sale_price']}</td>";
            echo "<td>{$product['stock_quantity']}</td>";
            echo "<td>{$product['status']}</td>";
            echo "<td><a href='{$link_id}' target='_blank'>Test ID</a> | <a href='{$link_slug}' target='_blank'>Test Slug</a></td>";
            echo "</tr>";
        }
        echo "</table>";
    } else {
        echo "❌ Không có sản phẩm nào trong bảng products";
    }
} catch (Exception $e) {
    echo "❌ Lỗi: " . $e->getMessage();
}

// 4. Kiểm tra dữ liệu sản phẩm trong san_pham_chinh
echo "<h3>4. Dữ liệu sản phẩm trong bảng SAN_PHAM_CHINH:</h3>";
try {
    $stmt = $pdo->query("SELECT id, ten_san_pham, slug, gia_goc, gia_khuyen_mai, trang_thai FROM san_pham_chinh LIMIT 5");
    $products = $stmt->fetchAll();
    
    if ($products) {
        echo "<table border='1' style='border-collapse: collapse;'>";
        echo "<tr><th>ID</th><th>Tên</th><th>Slug</th><th>Giá gốc</th><th>Giá KM</th><th>Trạng thái</th><th>Test Link</th></tr>";
        foreach ($products as $product) {
            $link_slug = "product_detail.php?slug={$product['slug']}";
            
            echo "<tr>";
            echo "<td>{$product['id']}</td>";
            echo "<td>" . htmlspecialchars($product['ten_san_pham']) . "</td>";
            echo "<td>" . htmlspecialchars($product['slug']) . "</td>";
            echo "<td>{$product['gia_goc']}</td>";
            echo "<td>{$product['gia_khuyen_mai']}</td>";
            echo "<td>{$product['trang_thai']}</td>";
            echo "<td><a href='{$link_slug}' target='_blank'>Test Link</a></td>";
            echo "</tr>";
        }
        echo "</table>";
    } else {
        echo "❌ Không có sản phẩm nào trong bảng san_pham_chinh";
    }
} catch (Exception $e) {
    echo "❌ Bảng san_pham_chinh: " . $e->getMessage();
}

// 5. Kiểm tra file product_detail.php có tồn tại
echo "<h3>5. Kiểm tra file product_detail.php:</h3>";
$product_detail_file = __DIR__ . '/product_detail.php';
if (file_exists($product_detail_file)) {
    echo "✅ File product_detail.php tồn tại<br>";
    echo "📂 Path: " . $product_detail_file . "<br>";
    echo "📊 File size: " . filesize($product_detail_file) . " bytes<br>";
    
    // Kiểm tra phần đầu file
    $content = file_get_contents($product_detail_file, false, null, 0, 500);
    echo "<h4>📝 Nội dung đầu file (500 ký tự đầu):</h4>";
    echo "<pre>" . htmlspecialchars($content) . "</pre>";
} else {
    echo "❌ File product_detail.php KHÔNG tồn tại tại: " . $product_detail_file;
}

// 6. Kiểm tra cấu hình database
echo "<h3>6. Kiểm tra cấu hình database:</h3>";
try {
    echo "✅ Kết nối database thành công<br>";
    echo "📊 PDO Driver: " . $pdo->getAttribute(PDO::ATTR_DRIVER_NAME) . "<br>";
    echo "📊 Server version: " . $pdo->getAttribute(PDO::ATTR_SERVER_VERSION) . "<br>";
} catch (Exception $e) {
    echo "❌ Lỗi database: " . $e->getMessage();
}

// 7. Kiểm tra session
echo "<h3>7. Kiểm tra Session:</h3>";
session_start();
echo "📊 Session ID: " . session_id() . "<br>";
echo "📊 Session data: " . print_r($_SESSION, true) . "<br>";

// 8. Kiểm tra cấu hình config
echo "<h3>8. Kiểm tra config files:</h3>";
$config_file = __DIR__ . '/../config/config.php';
if (file_exists($config_file)) {
    echo "✅ config/config.php tồn tại<br>";
    
    // Hiển thị một số constants
    include_once $config_file;
    if (defined('SITE_NAME')) {
        echo "📊 SITE_NAME: " . SITE_NAME . "<br>";
    }
    if (defined('BASE_URL')) {
        echo "📊 BASE_URL: " . BASE_URL . "<br>";
    }
} else {
    echo "❌ config/config.php KHÔNG tồn tại<br>";
}

$db_file = __DIR__ . '/../config/database.php';
if (file_exists($db_file)) {
    echo "✅ config/database.php tồn tại<br>";
} else {
    echo "❌ config/database.php KHÔNG tồn tại<br>";
}

// 9. Kiểm tra cart functions
echo "<h3>9. Kiểm tra các bảng liên quan đến giỏ hàng:</h3>";
$cart_tables = ['gio_hang', 'cart', 'don_hang', 'orders'];
foreach ($cart_tables as $table) {
    try {
        $stmt = $pdo->query("SELECT COUNT(*) FROM $table");
        $count = $stmt->fetchColumn();
        echo "✅ Bảng '$table': $count records<br>";
    } catch (Exception $e) {
        echo "❌ Bảng '$table' không tồn tại hoặc lỗi: " . $e->getMessage() . "<br>";
    }
}

// 10. Test link trực tiếp
echo "<h3>10. 🔗 Test Links:</h3>";
echo "<p><strong>Hãy click các link này để test:</strong></p>";
echo "<ul>";
echo "<li><a href='products.php' target='_blank'>📋 products.php</a></li>";
echo "<li><a href='product_detail.php?id=1' target='_blank'>👁️ product_detail.php?id=1</a></li>";
echo "<li><a href='product_detail.php?slug=test-product' target='_blank'>👁️ product_detail.php?slug=test-product</a></li>";
echo "<li><a href='cart.php' target='_blank'>🛒 cart.php</a></li>";
echo "<li><a href='checkout.php' target='_blank'>💳 checkout.php</a></li>";
echo "</ul>";

echo "<hr>";
echo "<p><strong>🎯 Hướng dẫn debug:</strong></p>";
echo "<ol>";
echo "<li>Click vào các test links ở trên</li>";
echo "<li>Xem lỗi nào xuất hiện (404, 500, blank page)</li>";
echo "<li>Kiểm tra browser console (F12)</li>";
echo "<li>Báo cáo lại cho tôi kết quả</li>";
echo "</ol>";
?>

<style>
table {
    margin: 10px 0;
    font-size: 12px;
}
th, td {
    padding: 5px;
    text-align: left;
}
pre {
    background: #f5f5f5;
    padding: 10px;
    overflow-x: auto;
    font-size: 11px;
}
</style>