<?php
/**
 * Debug Products - Kiểm tra tại sao sản phẩm không hiện ở customer
 */

session_start();

// Tìm file config với nhiều đường dẫn có thể
$config_paths = [
    'config/database.php',
    'config/config.php', 
    'admin/config/database.php',
    'admin/config/config.php'
];

$pdo = null;
$config_found = false;

foreach ($config_paths as $path) {
    if (file_exists($path)) {
        try {
            require_once $path;
            $config_found = true;
            echo "<div style='color: green;'>✅ Tìm thấy config: $path</div>";
            break;
        } catch (Exception $e) {
            echo "<div style='color: orange;'>⚠️ Lỗi load config $path: " . $e->getMessage() . "</div>";
        }
    }
}

if (!$config_found) {
    // Tạo kết nối database trực tiếp
    echo "<div style='color: orange;'>⚠️ Không tìm thấy file config, tạo kết nối trực tiếp...</div>";
    
    // Thông tin database mặc định (có thể thay đổi)
    $host = 'localhost';
    $dbname = 'tktshop';
    $username = 'root';
    $password = '';
    
    try {
        $pdo = new PDO("mysql:host=$host;dbname=$dbname;charset=utf8mb4", $username, $password);
        $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        echo "<div style='color: green;'>✅ Kết nối database thành công!</div>";
    } catch (PDOException $e) {
        echo "<div style='color: red;'>❌ Lỗi kết nối database: " . $e->getMessage() . "</div>";
        echo "<div style='background: #f8f9fa; padding: 10px; margin: 10px 0;'>";
        echo "<strong>Hướng dẫn khắc phục:</strong><br>";
        echo "1. Kiểm tra MySQL đã chạy chưa<br>";
        echo "2. Kiểm tra tên database 'tktshop' đã tồn tại chưa<br>";
        echo "3. Kiểm tra username/password MySQL<br>";
        echo "4. Chỉnh sửa thông tin database ở dòng 25-28 trong file debug này";
        echo "</div>";
        exit;
    }
}

echo "<h2>🔍 DEBUG: Kiểm tra sản phẩm customer</h2>";
echo "<style>
    body { font-family: Arial, sans-serif; margin: 20px; }
    .debug-section { background: #f8f9fa; padding: 15px; margin: 10px 0; border-radius: 5px; }
    .error { color: #dc3545; background: #f8d7da; padding: 10px; border-radius: 5px; }
    .success { color: #155724; background: #d4edda; padding: 10px; border-radius: 5px; }
    .info { color: #0c5460; background: #d1ecf1; padding: 10px; border-radius: 5px; }
    table { border-collapse: collapse; width: 100%; }
    th, td { border: 1px solid #ddd; padding: 8px; text-align: left; }
    th { background: #f2f2f2; }
    pre { background: #f8f9fa; padding: 10px; border-radius: 5px; overflow-x: auto; }
</style>";

try {
    // 1. Kiểm tra tất cả bảng trong database
    echo "<div class='debug-section'>";
    echo "<h3>📋 1. Kiểm tra các bảng trong database:</h3>";
    
    $tables = $pdo->query("SHOW TABLES")->fetchAll(PDO::FETCH_COLUMN);
    echo "<div class='info'>Tìm thấy " . count($tables) . " bảng:</div>";
    echo "<ul>";
    foreach ($tables as $table) {
        echo "<li><strong>$table</strong>";
        
        // Kiểm tra số lượng records
        try {
            $count = $pdo->query("SELECT COUNT(*) FROM `$table`")->fetchColumn();
            echo " (có $count dòng)";
        } catch (Exception $e) {
            echo " (lỗi đếm)";
        }
        echo "</li>";
    }
    echo "</ul>";
    echo "</div>";

    // 2. Kiểm tra sản phẩm trong bảng products
    echo "<div class='debug-section'>";
    echo "<h3>🛍️ 2. Kiểm tra sản phẩm trong bảng 'products':</h3>";
    
    if (in_array('products', $tables)) {
        $products = $pdo->query("SELECT * FROM products ORDER BY created_at DESC LIMIT 5")->fetchAll();
        
        if (empty($products)) {
            echo "<div class='error'>❌ Bảng 'products' không có dữ liệu nào!</div>";
        } else {
            echo "<div class='success'>✅ Tìm thấy " . count($products) . " sản phẩm (hiển thị 5 mới nhất):</div>";
            echo "<table>";
            echo "<tr><th>ID</th><th>Tên</th><th>SKU</th><th>Giá</th><th>Trạng thái</th><th>Danh mục ID</th><th>Ngày tạo</th></tr>";
            
            foreach ($products as $product) {
                echo "<tr>";
                echo "<td>{$product['id']}</td>";
                echo "<td>" . htmlspecialchars($product['name']) . "</td>";
                echo "<td>{$product['sku']}</td>";
                echo "<td>" . number_format($product['price']) . "₫</td>";
                echo "<td><span style='color: " . ($product['status'] == 'active' ? 'green' : 'red') . "'>{$product['status']}</span></td>";
                echo "<td>{$product['category_id']}</td>";
                echo "<td>{$product['created_at']}</td>";
                echo "</tr>";
            }
            echo "</table>";
        }
    } else {
        echo "<div class='error'>❌ Bảng 'products' không tồn tại!</div>";
    }
    echo "</div>";

    // 3. Kiểm tra bảng sản phẩm cũ (nếu có)
    echo "<div class='debug-section'>";
    echo "<h3>🔄 3. Kiểm tra bảng sản phẩm cũ (nếu có):</h3>";
    
    $old_product_tables = ['san_pham_chinh', 'san_pham', 'product'];
    $found_old = false;
    
    foreach ($old_product_tables as $old_table) {
        if (in_array($old_table, $tables)) {
            $found_old = true;
            echo "<div class='info'>📦 Tìm thấy bảng cũ: <strong>$old_table</strong></div>";
            
            try {
                $old_products = $pdo->query("SELECT * FROM `$old_table` LIMIT 3")->fetchAll();
                echo "<p>Có " . count($old_products) . " sản phẩm trong bảng cũ.</p>";
                
                if (!empty($old_products)) {
                    echo "<pre>Cấu trúc bản ghi đầu tiên:</pre>";
                    echo "<pre>" . print_r($old_products[0], true) . "</pre>";
                }
            } catch (Exception $e) {
                echo "<div class='error'>Lỗi đọc bảng $old_table: " . $e->getMessage() . "</div>";
            }
        }
    }
    
    if (!$found_old) {
        echo "<div class='info'>ℹ️ Không tìm thấy bảng sản phẩm cũ nào.</div>";
    }
    echo "</div>";

    // 4. Kiểm tra file customer hiển thị sản phẩm
    echo "<div class='debug-section'>";
    echo "<h3>📁 4. Kiểm tra các file customer:</h3>";
    
    $customer_files = [
        '../customer/index.php',
        '../customer/products.php', 
        '../customer/product_list.php',
        '../index.php'
    ];
    
    foreach ($customer_files as $file) {
        if (file_exists($file)) {
            echo "<div class='success'>✅ Tìm thấy: <strong>$file</strong></div>";
            
            // Đọc nội dung file để tìm tên bảng
            $content = file_get_contents($file);
            if (strpos($content, 'products') !== false) {
                echo "<p>→ File này có sử dụng bảng 'products'</p>";
            }
            if (strpos($content, 'san_pham') !== false) {
                echo "<p>→ File này có sử dụng bảng 'san_pham'</p>";
            }
        } else {
            echo "<div class='error'>❌ Không tìm thấy: <strong>$file</strong></div>";
        }
    }
    echo "</div>";

    // 5. Kiểm tra categories
    echo "<div class='debug-section'>";
    echo "<h3>📂 5. Kiểm tra danh mục:</h3>";
    
    if (in_array('categories', $tables)) {
        $categories = $pdo->query("SELECT * FROM categories WHERE status = 'active'")->fetchAll();
        echo "<div class='success'>✅ Có " . count($categories) . " danh mục active:</div>";
        
        foreach ($categories as $cat) {
            echo "<p>→ ID: {$cat['id']} - {$cat['name']}</p>";
        }
    } else {
        echo "<div class='error'>❌ Bảng categories không tồn tại!</div>";
    }
    echo "</div>";

    // 6. Tạo sản phẩm test
    echo "<div class='debug-section'>";
    echo "<h3>🧪 6. Tạo sản phẩm test:</h3>";
    
    if (isset($_GET['create_test']) && $_GET['create_test'] == '1') {
        try {
            // Tạo danh mục test nếu chưa có
            $pdo->exec("INSERT IGNORE INTO categories (id, name, slug, status) VALUES (999, 'Test Category', 'test-category', 'active')");
            
            // Tạo sản phẩm test
            $stmt = $pdo->prepare("
                INSERT INTO products (name, slug, description, price, sku, category_id, status, stock_quantity) 
                VALUES (?, ?, ?, ?, ?, ?, 'active', 100)
            ");
            
            $test_name = "Sản phẩm test " . date('H:i:s');
            $test_slug = "san-pham-test-" . time();
            $test_description = "Đây là sản phẩm test được tạo lúc " . date('Y-m-d H:i:s');
            $test_price = 100000;
            $test_sku = "TEST" . time();
            
            $stmt->execute([$test_name, $test_slug, $test_description, $test_price, $test_sku, 999]);
            
            echo "<div class='success'>✅ Đã tạo sản phẩm test: <strong>$test_name</strong></div>";
            echo "<p>→ Vào trang customer để xem có hiển thị không!</p>";
            
        } catch (Exception $e) {
            echo "<div class='error'>❌ Lỗi tạo sản phẩm test: " . $e->getMessage() . "</div>";
        }
    } else {
        echo "<a href='?create_test=1' style='background: #007bff; color: white; padding: 10px 15px; text-decoration: none; border-radius: 5px;'>🧪 Tạo sản phẩm test</a>";
    }
    echo "</div>";

    // 7. Gợi ý giải pháp
    echo "<div class='debug-section'>";
    echo "<h3>💡 7. Gợi ý giải pháp:</h3>";
    
    echo "<div class='info'>";
    echo "<h4>Các khả năng và cách khắc phục:</h4>";
    echo "<ol>";
    echo "<li><strong>File customer chưa tồn tại:</strong> Cần tạo file customer/index.php hoặc customer/products.php</li>";
    echo "<li><strong>File customer dùng tên bảng cũ:</strong> Cần sửa từ 'san_pham_chinh' thành 'products'</li>";
    echo "<li><strong>Status field khác nhau:</strong> Sửa từ 'hoat_dong' thành 'active'</li>";
    echo "<li><strong>Cấu trúc field khác nhau:</strong> Cần đồng bộ tên cột</li>";
    echo "<li><strong>Query WHERE sai:</strong> Kiểm tra điều kiện lọc sản phẩm</li>";
    echo "</ol>";
    echo "</div>";
    
    echo "<h4>🔗 Links hữu ích:</h4>";
    echo "<ul>";
    echo "<li><a href='../customer/' target='_blank'>→ Xem trang customer</a></li>";
    echo "<li><a href='../admin/products/' target='_blank'>→ Xem admin products</a></li>";
    echo "<li><a href='?refresh=1'>🔄 Refresh debug</a></li>";
    echo "</ul>";
    echo "</div>";

} catch (Exception $e) {
    echo "<div class='error'>💥 Lỗi debug: " . $e->getMessage() . "</div>";
}

echo "<hr><p><small>Debug completed at " . date('Y-m-d H:i:s') . "</small></p>";
?>