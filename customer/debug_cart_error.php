<?php
/**
 * Debug Add to Cart Error
 */

require_once '../config/database.php';

echo "<h2>🔍 DEBUG ADD TO CART ERROR</h2>";

// 1. Kiểm tra URL và parameters
echo "<h3>1. URL Parameters:</h3>";
echo "Current URL: " . $_SERVER['REQUEST_URI'] . "<br>";
echo "GET parameters: " . print_r($_GET, true) . "<br>";
echo "POST parameters: " . print_r($_POST, true) . "<br>";

// 2. Kiểm tra session
session_start();
echo "<h3>2. Session Status:</h3>";
echo "Session ID: " . session_id() . "<br>";
echo "Session data: <pre>" . print_r($_SESSION, true) . "</pre>";

// 3. Kiểm tra cấu trúc bảng gio_hang
echo "<h3>3. Cấu trúc bảng gio_hang:</h3>";
try {
    $stmt = $pdo->query("DESCRIBE gio_hang");
    $columns = $stmt->fetchAll();
    echo "<table border='1' style='border-collapse: collapse;'>";
    echo "<tr><th>Column</th><th>Type</th><th>Null</th><th>Key</th><th>Default</th><th>Extra</th></tr>";
    foreach ($columns as $col) {
        echo "<tr>";
        echo "<td>{$col['Field']}</td>";
        echo "<td>{$col['Type']}</td>";
        echo "<td>{$col['Null']}</td>";
        echo "<td>{$col['Key']}</td>";
        echo "<td>{$col['Default']}</td>";
        echo "<td>{$col['Extra']}</td>";
        echo "</tr>";
    }
    echo "</table>";
} catch (Exception $e) {
    echo "❌ Lỗi: " . $e->getMessage();
}

// 4. Kiểm tra foreign key constraints
echo "<h3>4. Foreign Key Constraints:</h3>";
try {
    $stmt = $pdo->query("
        SELECT 
            CONSTRAINT_NAME,
            COLUMN_NAME,
            REFERENCED_TABLE_NAME,
            REFERENCED_COLUMN_NAME,
            DELETE_RULE,
            UPDATE_RULE
        FROM information_schema.KEY_COLUMN_USAGE 
        WHERE TABLE_SCHEMA = 'tktshop' 
        AND TABLE_NAME = 'gio_hang' 
        AND REFERENCED_TABLE_NAME IS NOT NULL
    ");
    $constraints = $stmt->fetchAll();
    
    if ($constraints) {
        echo "<table border='1' style='border-collapse: collapse;'>";
        echo "<tr><th>Constraint</th><th>Column</th><th>References Table</th><th>References Column</th><th>Delete Rule</th><th>Update Rule</th></tr>";
        foreach ($constraints as $constraint) {
            echo "<tr>";
            echo "<td>{$constraint['CONSTRAINT_NAME']}</td>";
            echo "<td>{$constraint['COLUMN_NAME']}</td>";
            echo "<td>{$constraint['REFERENCED_TABLE_NAME']}</td>";
            echo "<td>{$constraint['REFERENCED_COLUMN_NAME']}</td>";
            echo "<td>{$constraint['DELETE_RULE']}</td>";
            echo "<td>{$constraint['UPDATE_RULE']}</td>";
            echo "</tr>";
        }
        echo "</table>";
    } else {
        echo "Không có foreign key constraints";
    }
} catch (Exception $e) {
    echo "❌ Lỗi: " . $e->getMessage();
}

// 5. Kiểm tra dữ liệu bảng bien_the_san_pham
echo "<h3>5. Dữ liệu bảng bien_the_san_pham:</h3>";
try {
    $stmt = $pdo->query("SELECT id, san_pham_id, kich_co_id, mau_sac_id, so_luong_ton_kho, trang_thai FROM bien_the_san_pham LIMIT 5");
    $variants = $stmt->fetchAll();
    
    if ($variants) {
        echo "<table border='1' style='border-collapse: collapse;'>";
        echo "<tr><th>ID</th><th>San Pham ID</th><th>Kich Co ID</th><th>Mau Sac ID</th><th>So Luong</th><th>Trang Thai</th></tr>";
        foreach ($variants as $variant) {
            echo "<tr>";
            echo "<td>{$variant['id']}</td>";
            echo "<td>{$variant['san_pham_id']}</td>";
            echo "<td>{$variant['kich_co_id']}</td>";
            echo "<td>{$variant['mau_sac_id']}</td>";
            echo "<td>{$variant['so_luong_ton_kho']}</td>";
            echo "<td>{$variant['trang_thai']}</td>";
            echo "</tr>";
        }
        echo "</table>";
    } else {
        echo "Không có biến thể nào";
    }
} catch (Exception $e) {
    echo "❌ Bảng bien_the_san_pham: " . $e->getMessage();
}

// 6. Test INSERT trực tiếp
echo "<h3>6. Test INSERT vào gio_hang:</h3>";

// Test 1: Insert với bien_the_id = NULL
echo "<h4>Test 1: Insert với bien_the_id = NULL</h4>";
try {
    $test_stmt = $pdo->prepare("
        INSERT INTO gio_hang (khach_hang_id, session_id, san_pham_id, bien_the_id, so_luong, gia_tai_thoi_diem, ngay_them) 
        VALUES (?, ?, ?, ?, ?, ?, NOW())
    ");
    $test_stmt->execute([NULL, 'debug_session', 1, NULL, 1, 50000]);
    echo "✅ SUCCESS: Insert với bien_the_id = NULL thành công<br>";
    
    // Xóa record test
    $pdo->prepare("DELETE FROM gio_hang WHERE session_id = 'debug_session'")->execute();
    echo "✅ Đã xóa record test<br>";
    
} catch (Exception $e) {
    echo "❌ FAILED: " . $e->getMessage() . "<br>";
}

// Test 2: Insert với bien_the_id = 0
echo "<h4>Test 2: Insert với bien_the_id = 0</h4>";
try {
    $test_stmt = $pdo->prepare("
        INSERT INTO gio_hang (khach_hang_id, session_id, san_pham_id, bien_the_id, so_luong, gia_tai_thoi_diem, ngay_them) 
        VALUES (?, ?, ?, ?, ?, ?, NOW())
    ");
    $test_stmt->execute([NULL, 'debug_session2', 1, 0, 1, 50000]);
    echo "✅ SUCCESS: Insert với bien_the_id = 0 thành công<br>";
    
    // Xóa record test
    $pdo->prepare("DELETE FROM gio_hang WHERE session_id = 'debug_session2'")->execute();
    echo "✅ Đã xóa record test<br>";
    
} catch (Exception $e) {
    echo "❌ FAILED: " . $e->getMessage() . "<br>";
}

// Test 3: Insert với bien_the_id = 1 (nếu tồn tại)
echo "<h4>Test 3: Insert với bien_the_id = 1</h4>";
try {
    $test_stmt = $pdo->prepare("
        INSERT INTO gio_hang (khach_hang_id, session_id, san_pham_id, bien_the_id, so_luong, gia_tai_thoi_diem, ngay_them) 
        VALUES (?, ?, ?, ?, ?, ?, NOW())
    ");
    $test_stmt->execute([NULL, 'debug_session3', 1, 1, 1, 50000]);
    echo "✅ SUCCESS: Insert với bien_the_id = 1 thành công<br>";
    
    // Xóa record test
    $pdo->prepare("DELETE FROM gio_hang WHERE session_id = 'debug_session3'")->execute();
    echo "✅ Đã xóa record test<br>";
    
} catch (Exception $e) {
    echo "❌ FAILED: " . $e->getMessage() . "<br>";
}

// 7. Kiểm tra product từ URL
if (isset($_GET['id'])) {
    $product_id = (int)$_GET['id'];
    echo "<h3>7. Kiểm tra Product ID = $product_id:</h3>";
    
    try {
        $stmt = $pdo->prepare("SELECT * FROM products WHERE id = ?");
        $stmt->execute([$product_id]);
        $product = $stmt->fetch();
        
        if ($product) {
            echo "✅ Product found in 'products' table:<br>";
            echo "Name: " . htmlspecialchars($product['name']) . "<br>";
            echo "Price: " . $product['price'] . "<br>";
            echo "Stock: " . $product['stock_quantity'] . "<br>";
            echo "Status: " . $product['status'] . "<br>";
        } else {
            echo "❌ Product not found in 'products' table<br>";
        }
    } catch (Exception $e) {
        echo "❌ Error: " . $e->getMessage();
    }
}

// 8. Test session cart
echo "<h3>8. Test Session Cart:</h3>";
if (!isset($_SESSION['cart'])) {
    $_SESSION['cart'] = [];
}

$_SESSION['cart']['test_product'] = [
    'product_id' => 1,
    'name' => 'Test Product',
    'price' => 50000,
    'quantity' => 1,
    'type' => 'simple_product'
];

echo "✅ Session cart test successful:<br>";
echo "<pre>" . print_r($_SESSION['cart'], true) . "</pre>";

// 9. Gợi ý giải pháp
echo "<h3>9. 🎯 Giải pháp:</h3>";
echo "<ol>";
echo "<li><strong>Nếu Test 1 FAILED:</strong> Constraint không cho phép bien_the_id = NULL</li>";
echo "<li><strong>Nếu Test 2 FAILED:</strong> Không có record nào có id = 0 trong bien_the_san_pham</li>";
echo "<li><strong>Nếu Test 3 SUCCESS:</strong> Cần insert một record dummy có id = 0</li>";
echo "<li><strong>Session Cart luôn hoạt động:</strong> Dùng session thay vì database</li>";
echo "</ol>";

echo "<h3>🔧 Test Links:</h3>";
echo "<ul>";
echo "<li><a href='debug_cart_error.php?id=1' target='_blank'>Test với Product ID = 1</a></li>";
echo "<li><a href='product_detail.php?id=1' target='_blank'>Product Detail Page</a></li>";
echo "</ul>";
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
h3 {
    color: #333;
    border-bottom: 1px solid #ccc;
    padding-bottom: 5px;
}
h4 {
    color: #666;
    margin: 10px 0 5px 0;
}
</style>