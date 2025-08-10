<?php
/**
 * debug_product_detail_link.php - Debug link chi tiết sản phẩm
 * Tạo file: tktshop/debug_product_detail_link.php
 */

echo "<h1>🔍 DEBUG LINK CHI TIẾT SẢN PHẨM</h1>";
echo "<hr>";

require_once 'config/database.php';

// 1. Kiểm tra cấu trúc bảng
echo "<h2>1. Cấu trúc bảng sản phẩm:</h2>";
try {
    $tables = ['products', 'san_pham_chinh'];
    foreach ($tables as $table) {
        try {
            $stmt = $pdo->query("DESCRIBE $table");
            $columns = $stmt->fetchAll();
            echo "<h3>✅ Bảng: $table</h3>";
            echo "<ul>";
            foreach ($columns as $col) {
                echo "<li><strong>{$col['Field']}</strong> ({$col['Type']})</li>";
            }
            echo "</ul>";
        } catch (Exception $e) {
            echo "<h3>❌ Bảng $table: Không tồn tại</h3>";
        }
    }
} catch (Exception $e) {
    echo "Lỗi: " . $e->getMessage();
}
echo "<hr>";

// 2. Lấy sản phẩm mẫu
echo "<h2>2. Sản phẩm mẫu và link:</h2>";
try {
    // Thử query bảng products trước
    $sql = "SELECT id, name, slug, main_image, price FROM products WHERE status = 'active' ORDER BY id DESC LIMIT 5";
    $stmt = $pdo->query($sql);
    $products = $stmt->fetchAll();
    
    if (empty($products)) {
        // Thử bảng cũ
        $sql = "SELECT id, ten_san_pham as name, slug, hinh_anh_chinh as main_image, gia_goc as price FROM san_pham_chinh WHERE trang_thai = 'hoat_dong' ORDER BY id DESC LIMIT 5";
        $stmt = $pdo->query($sql);
        $products = $stmt->fetchAll();
        echo "<p><strong>Đang dùng bảng cũ: san_pham_chinh</strong></p>";
    } else {
        echo "<p><strong>Đang dùng bảng mới: products</strong></p>";
    }
    
    if (empty($products)) {
        echo "<div style='background:#ffebee;padding:15px;'>❌ <strong>KHÔNG CÓ SẢN PHẨM NÀO!</strong></div>";
    } else {
        echo "<table border='1' cellpadding='8' style='border-collapse:collapse;width:100%;'>";
        echo "<tr style='background:#f0f0f0;'><th>ID</th><th>Tên</th><th>Slug</th><th>Link theo ID</th><th>Link theo Slug</th><th>Test Link</th></tr>";
        
        foreach ($products as $product) {
            $link_id = "customer/product_detail.php?id=" . $product['id'];
            $link_slug = "customer/product_detail.php?slug=" . $product['slug'];
            
            echo "<tr>";
            echo "<td>" . $product['id'] . "</td>";
            echo "<td>" . htmlspecialchars($product['name']) . "</td>";
            echo "<td>" . htmlspecialchars($product['slug']) . "</td>";
            echo "<td><code>$link_id</code></td>";
            echo "<td><code>$link_slug</code></td>";
            echo "<td>";
            echo "<a href='$link_id' target='_blank' style='background:#007bff;color:white;padding:3px 8px;text-decoration:none;border-radius:3px;'>Test ID</a> ";
            echo "<a href='$link_slug' target='_blank' style='background:#28a745;color:white;padding:3px 8px;text-decoration:none;border-radius:3px;'>Test Slug</a>";
            echo "</td>";
            echo "</tr>";
        }
        echo "</table>";
    }
    
} catch (Exception $e) {
    echo "<div style='background:#ffebee;padding:15px;'>❌ Lỗi query: " . $e->getMessage() . "</div>";
}
echo "<hr>";

// 3. Kiểm tra file product_detail.php
echo "<h2>3. Kiểm tra file product_detail.php:</h2>";
$file_path = 'customer/product_detail.php';

if (file_exists($file_path)) {
    echo "✅ File tồn tại: <code>$file_path</code><br>";
    
    $content = file_get_contents($file_path);
    $lines = explode("\n", $content);
    echo "📄 Số dòng: " . count($lines) . "<br>";
    
    // Kiểm tra nội dung quan trọng
    if (strpos($content, '$_GET[\'id\']') !== false) {
        echo "✅ File hỗ trợ parameter ?id=<br>";
    }
    if (strpos($content, '$_GET[\'slug\']') !== false) {
        echo "✅ File hỗ trợ parameter ?slug=<br>";
    }
    if (strpos($content, 'products') !== false) {
        echo "✅ File có reference đến bảng 'products'<br>";
    }
    if (strpos($content, 'san_pham_chinh') !== false) {
        echo "✅ File có reference đến bảng 'san_pham_chinh'<br>";
    }
    
    // Tìm lỗi syntax PHP
    $php_check = shell_exec("php -l $file_path 2>&1");
    if (strpos($php_check, 'No syntax errors') !== false) {
        echo "✅ PHP syntax OK<br>";
    } else {
        echo "❌ PHP syntax error:<br><pre style='background:#ffebee;padding:10px;'>$php_check</pre>";
    }
    
} else {
    echo "❌ File không tồn tại: <code>$file_path</code><br>";
}
echo "<hr>";

// 4. Kiểm tra link từ products.php
echo "<h2>4. Link trong products.php:</h2>";
$products_file = 'customer/products.php';

if (file_exists($products_file)) {
    echo "✅ File products.php tồn tại<br>";
    
    $content = file_get_contents($products_file);
    
    // Tìm các pattern link
    $patterns = [
        'product_detail\.php\?id=',
        'product_detail\.php\?slug=',
        'product-detail\.php',
        'chi-tiet-san-pham\.php'
    ];
    
    foreach ($patterns as $pattern) {
        if (preg_match("/$pattern/", $content)) {
            echo "✅ Tìm thấy pattern: <code>$pattern</code><br>";
        }
    }
    
    // Tìm exact link trong file
    preg_match_all('/href=["\']([^"\']*product[^"\']*)["\']/', $content, $matches);
    if (!empty($matches[1])) {
        echo "<strong>📋 Các link product tìm thấy:</strong><br>";
        foreach (array_unique($matches[1]) as $link) {
            echo "- <code>" . htmlspecialchars($link) . "</code><br>";
        }
    }
    
} else {
    echo "❌ File products.php không tồn tại<br>";
}
echo "<hr>";

// 5. Test URL rewrite
echo "<h2>5. Kiểm tra URL Rewrite (.htaccess):</h2>";
if (file_exists('.htaccess')) {
    echo "✅ File .htaccess tồn tại<br>";
    $htaccess = file_get_contents('.htaccess');
    if (strpos($htaccess, 'RewriteEngine') !== false) {
        echo "✅ RewriteEngine được bật<br>";
    }
    if (strpos($htaccess, 'product') !== false) {
        echo "✅ Có rewrite rules cho product<br>";
    }
} else {
    echo "⚠️ File .htaccess không tồn tại<br>";
}
echo "<hr>";

// Kết luận
echo "<h2>🎯 Hướng dẫn khắc phục:</h2>";
echo "<div style='background:#e8f5e8;padding:15px;border-radius:5px;'>";
echo "<h3>Để link 'Xem chi tiết' hoạt động:</h3>";
echo "<ol>";
echo "<li><strong>Nếu dùng bảng 'products':</strong> Link phải là <code>product_detail.php?id=123</code></li>";
echo "<li><strong>Nếu dùng bảng 'san_pham_chinh':</strong> Link phải là <code>product_detail.php?slug=ten-san-pham</code></li>";
echo "<li><strong>Kiểm tra file product_detail.php</strong> có đúng cấu trúc parameter không</li>";
echo "<li><strong>Kiểm tra file products.php</strong> có tạo link đúng không</li>";
echo "</ol>";
echo "</div>";

echo "<br><p><strong>⏰ Thời gian:</strong> " . date('Y-m-d H:i:s') . "</p>";
?>

<style>
body { font-family: Arial, sans-serif; margin: 20px; line-height: 1.6; }
h1, h2, h3 { color: #333; }
hr { border: 1px solid #ddd; margin: 20px 0; }
table { width: 100%; border-collapse: collapse; }
th, td { padding: 8px; border: 1px solid #ddd; text-align: left; }
th { background: #f5f5f5; }
code { background: #f0f0f0; padding: 2px 6px; border-radius: 3px; }
pre { background: #f5f5f5; padding: 10px; border-radius: 5px; overflow-x: auto; }
</style>