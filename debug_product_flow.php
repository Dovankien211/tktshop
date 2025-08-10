<?php
/**
 * DEBUG TOÀN BỘ FLOW SẢN PHẨM
 * Tạo file này: tktshop/debug_product_flow.php (thư mục gốc)
 */

error_reporting(E_ALL);
ini_set('display_errors', 1);

echo "<h1>🔍 DEBUG TOÀN BỘ FLOW SẢN PHẨM</h1>";
echo "<p><strong>Thời gian:</strong> " . date('Y-m-d H:i:s') . "</p>";
echo "<hr>";

// 1. Kiểm tra kết nối database
echo "<h2>1. 🗄️ KIỂM TRA DATABASE</h2>";
try {
    require_once 'config/config.php';
    require_once 'config/database.php';
    
    $pdo = Database::getInstance()->getConnection();
    echo "✅ <span style='color:green;'>Database connected successfully</span><br>";
    
    // Thông tin database
    $db_info = $pdo->query("SELECT DATABASE() as db_name")->fetch();
    echo "📊 Database name: <strong>" . $db_info['db_name'] . "</strong><br>";
    
} catch (Exception $e) {
    echo "❌ <span style='color:red;'>Database error: " . $e->getMessage() . "</span><br>";
    die("Cannot continue without database");
}
echo "<hr>";

// 2. Kiểm tra cấu trúc bảng
echo "<h2>2. 📋 KIỂM TRA CẤU TRÚC BẢNG</h2>";
$tables = ['categories', 'products', 'users'];

foreach ($tables as $table) {
    try {
        $stmt = $pdo->query("DESCRIBE $table");
        $columns = $stmt->fetchAll(PDO::FETCH_ASSOC);
        echo "<h3>📝 Bảng: <code>$table</code></h3>";
        echo "<table border='1' cellpadding='5' style='border-collapse:collapse;'>";
        echo "<tr style='background:#f0f0f0;'><th>Column</th><th>Type</th><th>Null</th><th>Key</th><th>Default</th></tr>";
        foreach ($columns as $col) {
            echo "<tr>";
            echo "<td>" . $col['Field'] . "</td>";
            echo "<td>" . $col['Type'] . "</td>";
            echo "<td>" . $col['Null'] . "</td>";
            echo "<td>" . $col['Key'] . "</td>";
            echo "<td>" . $col['Default'] . "</td>";
            echo "</tr>";
        }
        echo "</table><br>";
        
        // Đếm số lượng records
        $count_stmt = $pdo->query("SELECT COUNT(*) FROM $table");
        $count = $count_stmt->fetchColumn();
        echo "📊 <strong>Tổng số records:</strong> $count<br><br>";
        
    } catch (Exception $e) {
        echo "❌ <span style='color:red;'>Lỗi bảng $table: " . $e->getMessage() . "</span><br><br>";
    }
}
echo "<hr>";

// 3. Kiểm tra dữ liệu sản phẩm
echo "<h2>3. 🛍️ KIỂM TRA DỮ LIỆU SẢN PHẨM</h2>";
try {
    // Lấy tất cả sản phẩm
    $products_stmt = $pdo->query("
        SELECT p.*, c.name as category_name 
        FROM products p 
        LEFT JOIN categories c ON p.category_id = c.id 
        ORDER BY p.id DESC 
        LIMIT 10
    ");
    $products = $products_stmt->fetchAll(PDO::FETCH_ASSOC);
    
    echo "<h3>📦 10 sản phẩm mới nhất:</h3>";
    if (empty($products)) {
        echo "<div style='background:#ffebee;padding:15px;border-radius:5px;'>";
        echo "❌ <strong>KHÔNG CÓ SẢN PHẨM NÀO TRONG DATABASE!</strong><br>";
        echo "👉 Đây có thể là nguyên nhân tại sao customer không thấy sản phẩm<br>";
        echo "</div>";
    } else {
        echo "<table border='1' cellpadding='8' style='border-collapse:collapse; width:100%;'>";
        echo "<tr style='background:#f0f0f0;'>";
        echo "<th>ID</th><th>Tên</th><th>SKU</th><th>Giá</th><th>Danh mục</th><th>Status</th><th>Ngày tạo</th>";
        echo "</tr>";
        
        foreach ($products as $product) {
            $status_color = $product['status'] == 'active' ? 'green' : 'red';
            echo "<tr>";
            echo "<td>" . $product['id'] . "</td>";
            echo "<td>" . htmlspecialchars($product['name']) . "</td>";
            echo "<td>" . htmlspecialchars($product['sku']) . "</td>";
            echo "<td>" . number_format($product['price']) . "đ</td>";
            echo "<td>" . htmlspecialchars($product['category_name'] ?? 'N/A') . "</td>";
            echo "<td style='color:$status_color;'><strong>" . $product['status'] . "</strong></td>";
            echo "<td>" . $product['created_at'] . "</td>";
            echo "</tr>";
        }
        echo "</table><br>";
        
        // Thống kê theo status
        $status_stats = $pdo->query("
            SELECT status, COUNT(*) as count 
            FROM products 
            GROUP BY status
        ")->fetchAll(PDO::FETCH_ASSOC);
        
        echo "<h4>📊 Thống kê theo trạng thái:</h4>";
        foreach ($status_stats as $stat) {
            $color = $stat['status'] == 'active' ? 'green' : 'orange';
            echo "<span style='color:$color;'><strong>" . $stat['status'] . ":</strong> " . $stat['count'] . " sản phẩm</span><br>";
        }
    }
} catch (Exception $e) {
    echo "❌ <span style='color:red;'>Lỗi truy vấn sản phẩm: " . $e->getMessage() . "</span><br>";
}
echo "<hr>";

// 4. Kiểm tra file customer
echo "<h2>4. 👥 KIỂM TRA FILE CUSTOMER</h2>";
$customer_files = [
    'customer/index.php' => 'Trang chủ customer',
    'customer/products.php' => 'Danh sách sản phẩm', 
    'customer/product_detail.php' => 'Chi tiết sản phẩm'
];

foreach ($customer_files as $file => $desc) {
    if (file_exists($file)) {
        echo "✅ <span style='color:green;'>$desc</span>: <code>$file</code> - EXISTS<br>";
        
        // Kiểm tra nội dung file có query sản phẩm không
        $content = file_get_contents($file);
        if (strpos($content, 'SELECT') !== false && strpos($content, 'products') !== false) {
            echo "&nbsp;&nbsp;&nbsp;📝 File có chứa query sản phẩm<br>";
        } else {
            echo "&nbsp;&nbsp;&nbsp;⚠️ <span style='color:orange;'>File KHÔNG có query sản phẩm</span><br>";
        }
    } else {
        echo "❌ <span style='color:red;'>$desc</span>: <code>$file</code> - NOT FOUND<br>";
    }
}
echo "<hr>";

// 5. Test query từ customer
echo "<h2>5. 🔍 TEST QUERY TỪ CUSTOMER</h2>";
try {
    // Test query thông thường mà customer sẽ dùng
    $customer_query = "
        SELECT p.*, c.name as category_name 
        FROM products p 
        LEFT JOIN categories c ON p.category_id = c.id 
        WHERE p.status = 'active' 
        ORDER BY p.created_at DESC 
        LIMIT 5
    ";
    
    $customer_stmt = $pdo->query($customer_query);
    $customer_products = $customer_stmt->fetchAll(PDO::FETCH_ASSOC);
    
    echo "<h3>🛒 Sản phẩm customer sẽ thấy (status = 'active'):</h3>";
    if (empty($customer_products)) {
        echo "<div style='background:#ffebee;padding:15px;border-radius:5px;'>";
        echo "❌ <strong>KHÔNG CÓ SẢN PHẨM ACTIVE NÀO!</strong><br>";
        echo "👉 Đây là nguyên nhân customer không thấy sản phẩm<br>";
        echo "👉 Cần thay đổi status sản phẩm thành 'active'<br>";
        echo "</div>";
    } else {
        echo "<ol>";
        foreach ($customer_products as $product) {
            echo "<li>";
            echo "<strong>" . htmlspecialchars($product['name']) . "</strong><br>";
            echo "&nbsp;&nbsp;SKU: " . $product['sku'] . " | ";
            echo "Giá: " . number_format($product['price']) . "đ | ";
            echo "Danh mục: " . ($product['category_name'] ?? 'N/A') . "<br>";
            echo "</li>";
        }
        echo "</ol>";
    }
} catch (Exception $e) {
    echo "❌ <span style='color:red;'>Lỗi test query customer: " . $e->getMessage() . "</span><br>";
}
echo "<hr>";

// 6. Kiểm tra ảnh sản phẩm
echo "<h2>6. 🖼️ KIỂM TRA ẢNH SẢN PHẨM</h2>";
$upload_dirs = [
    'uploads/products/' => 'Thư mục ảnh sản phẩm'
];

foreach ($upload_dirs as $dir => $desc) {
    echo "<h4>$desc: <code>$dir</code></h4>";
    if (is_dir($dir)) {
        echo "✅ <span style='color:green;'>Thư mục tồn tại</span><br>";
        echo "📁 Quyền: " . substr(sprintf('%o', fileperms($dir)), -4) . "<br>";
        echo "✍️ Có thể ghi: " . (is_writable($dir) ? '✅ YES' : '❌ NO') . "<br>";
        
        // Đếm file ảnh
        $files = glob($dir . "*.{jpg,jpeg,png,gif,webp}", GLOB_BRACE);
        echo "🖼️ Số lượng ảnh: " . count($files) . "<br>";
        
        if (count($files) > 0) {
            echo "📋 Một số file ảnh:<br>";
            foreach (array_slice($files, 0, 5) as $file) {
                $size = filesize($file);
                echo "&nbsp;&nbsp;- " . basename($file) . " (" . round($size/1024, 1) . "KB)<br>";
            }
        }
    } else {
        echo "❌ <span style='color:red;'>Thư mục không tồn tại</span><br>";
        echo "👉 Tạo thư mục: ";
        if (mkdir($dir, 0755, true)) {
            echo "✅ Thành công<br>";
        } else {
            echo "❌ Thất bại<br>";
        }
    }
    echo "<br>";
}
echo "<hr>";

// 7. Test tạo sản phẩm mẫu
echo "<h2>7. 🧪 TEST TẠO SẢN PHẨM MẪU</h2>";
try {
    // Kiểm tra có category nào không
    $cat_count = $pdo->query("SELECT COUNT(*) FROM categories WHERE status = 'active'")->fetchColumn();
    
    if ($cat_count == 0) {
        echo "⚠️ Không có category active, tạo category mẫu...<br>";
        $pdo->exec("INSERT INTO categories (name, slug, status) VALUES ('Test Category', 'test-category', 'active')");
        echo "✅ Đã tạo category mẫu<br>";
    }
    
    // Tạo sản phẩm test
    $test_sku = 'TEST_' . time();
    $insert_sql = "INSERT INTO products (name, sku, description, price, category_id, status, stock_quantity, created_at, updated_at) VALUES (?, ?, ?, ?, ?, 'active', 100, NOW(), NOW())";
    
    $stmt = $pdo->prepare($insert_sql);
    $result = $stmt->execute([
        'Sản phẩm test ' . date('H:i:s'),
        $test_sku,
        'Mô tả sản phẩm test để kiểm tra hiển thị',
        150000,
        1
    ]);
    
    if ($result) {
        $test_id = $pdo->lastInsertId();
        echo "✅ <span style='color:green;'>Tạo sản phẩm test thành công! ID: $test_id</span><br>";
        
        // Test query lại xem có hiển thị không
        $check_stmt = $pdo->prepare("SELECT * FROM products WHERE id = ? AND status = 'active'");
        $check_stmt->execute([$test_id]);
        $test_product = $check_stmt->fetch();
        
        if ($test_product) {
            echo "✅ <span style='color:green;'>Sản phẩm test có thể được query thành công</span><br>";
            echo "📦 Tên: " . $test_product['name'] . "<br>";
            echo "💰 Giá: " . number_format($test_product['price']) . "đ<br>";
        } else {
            echo "❌ <span style='color:red;'>Không thể query được sản phẩm test</span><br>";
        }
        
        // Cleanup
        $pdo->prepare("DELETE FROM products WHERE id = ?")->execute([$test_id]);
        echo "🗑️ Đã xóa sản phẩm test<br>";
        
    } else {
        echo "❌ <span style='color:red;'>Không thể tạo sản phẩm test</span><br>";
    }
    
} catch (Exception $e) {
    echo "❌ <span style='color:red;'>Lỗi test tạo sản phẩm: " . $e->getMessage() . "</span><br>";
}
echo "<hr>";

// 8. Kiểm tra session và authentication
echo "<h2>8. 🔐 KIỂM TRA SESSION & AUTH</h2>";
session_start();
echo "🆔 Session ID: " . session_id() . "<br>";
echo "👤 Admin ID: " . ($_SESSION['admin_id'] ?? 'CHƯA LOGIN') . "<br>";
echo "👥 User ID: " . ($_SESSION['user_id'] ?? 'CHƯA LOGIN') . "<br>";
echo "🕒 Session start time: " . ($_SESSION['start_time'] ?? 'N/A') . "<br>";
echo "<hr>";

// 9. KẾT LUẬN VÀ KHUYẾN NGHỊ
echo "<h2>🎯 KẾT LUẬN & KHUYẾN NGHỊ</h2>";

$issues = [];
$recommendations = [];

// Kiểm tra các vấn đề
if (empty($products)) {
    $issues[] = "❌ Không có sản phẩm nào trong database";
    $recommendations[] = "👉 Thêm sản phẩm qua admin/products/add.php";
}

if (!empty($products)) {
    $active_count = 0;
    foreach ($products as $p) {
        if ($p['status'] == 'active') $active_count++;
    }
    if ($active_count == 0) {
        $issues[] = "❌ Không có sản phẩm nào có status = 'active'";
        $recommendations[] = "👉 Thay đổi status sản phẩm thành 'active' trong admin";
    }
}

if (!file_exists('customer/products.php')) {
    $issues[] = "❌ Thiếu file customer/products.php";
    $recommendations[] = "👉 Tạo file hiển thị sản phẩm cho customer";
}

if (!is_dir('uploads/products/')) {
    $issues[] = "❌ Thiếu thư mục uploads/products/";
    $recommendations[] = "👉 Tạo thư mục uploads và set quyền 755";
}

// Hiển thị kết quả
if (empty($issues)) {
    echo "<div style='background:#e8f5e8;border:1px solid #4caf50;padding:15px;border-radius:8px;'>";
    echo "<h3 style='color:#2e7d32;margin-top:0;'>✅ HỆ THỐNG HOẠT ĐỘNG BÌNH THƯỜNG</h3>";
    echo "<p>Tất cả các thành phần đều OK. Sản phẩm có thể hiển thị ở customer.</p>";
    echo "<p><strong>Bước tiếp theo:</strong> Kiểm tra file customer/products.php có query đúng không.</p>";
    echo "</div>";
} else {
    echo "<div style='background:#ffebee;border:1px solid #f44336;padding:15px;border-radius:8px;'>";
    echo "<h3 style='color:#c62828;margin-top:0;'>❌ PHÁT HIỆN CÁC VẤN ĐỀ</h3>";
    echo "<h4>🐛 Vấn đề:</h4>";
    foreach ($issues as $issue) {
        echo "<p>$issue</p>";
    }
    echo "<h4>💡 Khuyến nghị:</h4>";
    foreach ($recommendations as $rec) {
        echo "<p>$rec</p>";
    }
    echo "</div>";
}

echo "<br><p><strong>⏰ Hoàn thành lúc:</strong> " . date('Y-m-d H:i:s') . "</p>";
?>

<style>
body { 
    font-family: Arial, sans-serif; 
    margin: 20px; 
    line-height: 1.6;
}
h1, h2, h3 { color: #333; }
hr { border: 1px solid #ddd; margin: 20px 0; }
table { width: 100%; margin: 10px 0; }
th { background: #f5f5f5; }
code { 
    background: #f0f0f0; 
    padding: 2px 6px; 
    border-radius: 3px; 
    font-family: monospace;
}
.success { color: #4caf50; }
.error { color: #f44336; }
.warning { color: #ff9800; }
</style>