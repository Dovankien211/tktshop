<?php
/**
 * Debug file cho admin/products/add.php
 * Tạo file này: admin/products/debug_add.php
 */

// Bật hiển thị lỗi
error_reporting(E_ALL);
ini_set('display_errors', 1);
ini_set('log_errors', 1);

echo "<h2>🔍 DEBUG - Kiểm tra add.php</h2>";
echo "<hr>";

// 1. Kiểm tra session
echo "<h3>1. Kiểm tra Session</h3>";
session_start();
echo "Session ID: " . session_id() . "<br>";
echo "Admin ID trong session: " . ($_SESSION['admin_id'] ?? 'KHÔNG CÓ') . "<br>";
if (!isset($_SESSION['admin_id'])) {
    echo "❌ <strong style='color:red;'>CHƯA ĐĂNG NHẬP ADMIN</strong><br>";
    echo "👉 Bạn cần đăng nhập admin trước<br>";
} else {
    echo "✅ <span style='color:green;'>Đã đăng nhập admin</span><br>";
}
echo "<hr>";

// 2. Kiểm tra đường dẫn file
echo "<h3>2. Kiểm tra đường dẫn files</h3>";
$config_path = '../../config/config.php';
$db_path = '../../config/database.php';

echo "Current directory: " . getcwd() . "<br>";
echo "Config file path: " . $config_path . "<br>";
echo "Config file exists: " . (file_exists($config_path) ? '✅ CÓ' : '❌ KHÔNG') . "<br>";
echo "Database file path: " . $db_path . "<br>";
echo "Database file exists: " . (file_exists($db_path) ? '✅ CÓ' : '❌ KHÔNG') . "<br>";
echo "<hr>";

// 3. Test require files
echo "<h3>3. Test require files</h3>";
try {
    if (file_exists($config_path)) {
        require_once $config_path;
        echo "✅ config.php loaded successfully<br>";
    } else {
        echo "❌ config.php NOT FOUND<br>";
    }
    
    if (file_exists($db_path)) {
        require_once $db_path;
        echo "✅ database.php loaded successfully<br>";
    } else {
        echo "❌ database.php NOT FOUND<br>";
    }
} catch (Exception $e) {
    echo "❌ <strong style='color:red;'>Lỗi require files:</strong> " . $e->getMessage() . "<br>";
}
echo "<hr>";

// 4. Test database connection
echo "<h3>4. Test Database Connection</h3>";
try {
    if (class_exists('Database')) {
        $pdo = Database::getInstance()->getConnection();
        echo "✅ <span style='color:green;'>Database connected successfully</span><br>";
        echo "Database info: " . $pdo->getAttribute(PDO::ATTR_SERVER_INFO) . "<br>";
        
        // Test query
        $stmt = $pdo->query("SELECT VERSION() as version");
        $result = $stmt->fetch();
        echo "MySQL Version: " . $result['version'] . "<br>";
        
    } else {
        echo "❌ <strong style='color:red;'>Database class không tồn tại</strong><br>";
    }
} catch (Exception $e) {
    echo "❌ <strong style='color:red;'>Database connection failed:</strong> " . $e->getMessage() . "<br>";
}
echo "<hr>";

// 5. Kiểm tra tables
echo "<h3>5. Kiểm tra Database Tables</h3>";
try {
    if (isset($pdo)) {
        // Kiểm tra bảng categories
        $tables_check = [
            'categories' => 'SELECT COUNT(*) FROM categories',
            'products' => 'SELECT COUNT(*) FROM products'
        ];
        
        foreach ($tables_check as $table => $query) {
            try {
                $stmt = $pdo->query($query);
                $count = $stmt->fetchColumn();
                echo "✅ Table '$table': $count records<br>";
            } catch (Exception $e) {
                echo "❌ Table '$table': " . $e->getMessage() . "<br>";
            }
        }
    }
} catch (Exception $e) {
    echo "❌ <strong style='color:red;'>Table check error:</strong> " . $e->getMessage() . "<br>";
}
echo "<hr>";

// 6. Kiểm tra thư mục uploads
echo "<h3>6. Kiểm tra thư mục uploads</h3>";
$upload_dir = "../../uploads/products/";
echo "Upload directory: " . $upload_dir . "<br>";
echo "Directory exists: " . (is_dir($upload_dir) ? '✅ CÓ' : '❌ KHÔNG') . "<br>";
echo "Directory writable: " . (is_writable($upload_dir) ? '✅ CÓ' : '❌ KHÔNG') . "<br>";

if (!is_dir($upload_dir)) {
    echo "👉 Tạo thư mục uploads...<br>";
    if (mkdir($upload_dir, 0755, true)) {
        echo "✅ Tạo thư mục thành công<br>";
    } else {
        echo "❌ Không thể tạo thư mục<br>";
    }
}
echo "<hr>";

// 7. Test tạo sản phẩm mẫu (nếu đã kết nối DB)
echo "<h3>7. Test thêm sản phẩm mẫu</h3>";
try {
    if (isset($pdo) && isset($_SESSION['admin_id'])) {
        
        // Kiểm tra có category nào chưa
        $cat_stmt = $pdo->query("SELECT COUNT(*) FROM categories");
        $cat_count = $cat_stmt->fetchColumn();
        
        if ($cat_count == 0) {
            echo "👉 Tạo categories mẫu...<br>";
            $sample_categories = [
                ['Giày thể thao', 'giay-the-thao', 'Giày dành cho hoạt động thể thao'],
                ['Giày da', 'giay-da', 'Giày da cao cấp, sang trọng'],
                ['Nike', 'nike', 'Sản phẩm Nike chính hãng']
            ];
            
            $stmt = $pdo->prepare("INSERT INTO categories (name, slug, description, status) VALUES (?, ?, ?, 'active')");
            foreach ($sample_categories as $cat) {
                $stmt->execute($cat);
            }
            echo "✅ Đã tạo " . count($sample_categories) . " categories<br>";
        }
        
        // Test insert product
        $test_sku = 'TEST_' . time();
        $insert_sql = "INSERT INTO products (name, sku, description, price, category_id, status, created_at) VALUES (?, ?, ?, ?, 1, 'active', NOW())";
        $insert_stmt = $pdo->prepare($insert_sql);
        
        $result = $insert_stmt->execute([
            'Test Product ' . date('H:i:s'), 
            $test_sku,
            'Test description', 
            100000
        ]);
        
        if ($result) {
            $product_id = $pdo->lastInsertId();
            echo "✅ <span style='color:green;'>Test product created successfully! ID: $product_id</span><br>";
            
            // Xóa test product
            $pdo->prepare("DELETE FROM products WHERE id = ?")->execute([$product_id]);
            echo "✅ Test product cleaned up<br>";
        } else {
            echo "❌ Failed to create test product<br>";
        }
        
    } else {
        echo "⚠️ Bỏ qua test - thiếu database hoặc admin session<br>";
    }
} catch (Exception $e) {
    echo "❌ <strong style='color:red;'>Test product error:</strong> " . $e->getMessage() . "<br>";
}
echo "<hr>";

// 8. Kết luận
echo "<h3>🎯 Kết luận & Khuyến nghị</h3>";
if (!isset($_SESSION['admin_id'])) {
    echo "<div style='background:#ffebee;border:1px solid #f44336;padding:15px;border-radius:5px;'>";
    echo "<strong>❌ NGUYÊN NHÂN CHÍNH: CHƯA ĐĂNG NHẬP ADMIN</strong><br>";
    echo "👉 Bạn cần đăng nhập admin trước khi truy cập trang add.php<br>";
    echo "👉 Đi tới: <a href='../login.php'>admin/login.php</a><br>";
    echo "</div>";
} elseif (!file_exists($config_path) || !file_exists($db_path)) {
    echo "<div style='background:#fff3e0;border:1px solid #ff9800;padding:15px;border-radius:5px;'>";
    echo "<strong>⚠️ THIẾU FILE CONFIG</strong><br>";
    echo "👉 Kiểm tra đường dẫn file config.php và database.php<br>";
    echo "</div>";
} elseif (!isset($pdo)) {
    echo "<div style='background:#fff3e0;border:1px solid #ff9800;padding:15px;border-radius:5px;'>";
    echo "<strong>⚠️ LỖI KẾT NỐI DATABASE</strong><br>";
    echo "👉 Kiểm tra thông tin database trong config.php<br>";
    echo "</div>";
} else {
    echo "<div style='background:#e8f5e8;border:1px solid #4caf50;padding:15px;border-radius:5px;'>";
    echo "<strong>✅ SYSTEM OK</strong><br>";
    echo "👉 Hệ thống hoạt động bình thường, có thể test add.php<br>";
    echo "👉 <a href='add.php'>Truy cập add.php</a><br>";
    echo "</div>";
}

echo "<br><strong>Thời gian check:</strong> " . date('Y-m-d H:i:s');
?>

<style>
body { font-family: Arial, sans-serif; margin: 20px; }
h2, h3 { color: #333; }
hr { border: 1px solid #ddd; margin: 15px 0; }
</style>