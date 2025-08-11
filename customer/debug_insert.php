<?php
// Tạo file: customer/debug_insert.php
session_start();
require_once '../config/database.php';

echo "<h2>🔍 DEBUG INSERT CART ISSUE</h2>";

// 1. Kiểm tra cấu trúc bảng gio_hang
echo "<h3>1. Cấu trúc bảng gio_hang:</h3>";
try {
    $stmt = $pdo->query("DESCRIBE gio_hang");
    $columns = $stmt->fetchAll();
    echo "<table border='1' style='border-collapse: collapse;'>";
    echo "<tr><th>Field</th><th>Type</th><th>Null</th><th>Key</th><th>Default</th><th>Extra</th></tr>";
    foreach ($columns as $col) {
        $nullText = $col['Null'] == 'YES' ? '✅ YES' : '❌ NO';
        $defaultText = $col['Default'] ?: '(none)';
        echo "<tr>";
        echo "<td><strong>{$col['Field']}</strong></td>";
        echo "<td>{$col['Type']}</td>";
        echo "<td>{$nullText}</td>";
        echo "<td>{$col['Key']}</td>";
        echo "<td>{$defaultText}</td>";
        echo "<td>{$col['Extra']}</td>";
        echo "</tr>";
    }
    echo "</table>";
} catch (Exception $e) {
    echo "❌ Error: " . $e->getMessage();
}

// 2. Test INSERT với products (bien_the_id = NULL)
echo "<h3>2. Test INSERT Products (bien_the_id = NULL):</h3>";
try {
    $test_insert = $pdo->prepare("
        INSERT INTO gio_hang (khach_hang_id, session_id, san_pham_id, bien_the_id, so_luong, gia_tai_thoi_diem, ngay_them)
        VALUES (?, ?, ?, NULL, ?, ?, NOW())
    ");
    $test_insert->execute([17, 'debug_session', 1, 1, 50000]);
    echo "✅ SUCCESS: Products insert với bien_the_id = NULL<br>";
    
    // Xóa test record
    $pdo->prepare("DELETE FROM gio_hang WHERE session_id = 'debug_session'")->execute();
    echo "✅ Đã xóa test record<br>";
    
} catch (Exception $e) {
    echo "❌ FAILED: " . $e->getMessage() . "<br>";
}

// 3. Test INSERT với san_pham_chinh (có bien_the_id)
echo "<h3>3. Test INSERT San_pham_chinh (có bien_the_id):</h3>";
try {
    $test_insert = $pdo->prepare("
        INSERT INTO gio_hang (khach_hang_id, session_id, san_pham_id, bien_the_id, so_luong, gia_tai_thoi_diem, ngay_them)
        VALUES (?, ?, ?, ?, ?, ?, NOW())
    ");
    $test_insert->execute([17, 'debug_session2', 3, 6, 1, 75000]); // san_pham_id=3, bien_the_id=6
    echo "✅ SUCCESS: San_pham_chinh insert với bien_the_id<br>";
    
    // Xóa test record
    $pdo->prepare("DELETE FROM gio_hang WHERE session_id = 'debug_session2'")->execute();
    echo "✅ Đã xóa test record<br>";
    
} catch (Exception $e) {
    echo "❌ FAILED: " . $e->getMessage() . "<br>";
}

// 4. Test INSERT chỉ với các field bắt buộc
echo "<h3>4. Test INSERT minimal fields:</h3>";
try {
    $test_insert = $pdo->prepare("
        INSERT INTO gio_hang (san_pham_id, so_luong, gia_tai_thoi_diem)
        VALUES (?, ?, ?)
    ");
    $test_insert->execute([1, 1, 50000]);
    echo "✅ SUCCESS: Minimal insert<br>";
    
    // Xóa test record
    $pdo->prepare("DELETE FROM gio_hang WHERE san_pham_id = 1 AND khach_hang_id IS NULL")->execute();
    echo "✅ Đã xóa test record<br>";
    
} catch (Exception $e) {
    echo "❌ FAILED: " . $e->getMessage() . "<br>";
}

// 5. Kiểm tra có trigger nào không
echo "<h3>5. Kiểm tra Triggers:</h3>";
try {
    $triggers = $pdo->query("SHOW TRIGGERS LIKE 'gio_hang'")->fetchAll();
    if ($triggers) {
        foreach ($triggers as $trigger) {
            echo "⚠️ Trigger: {$trigger['Trigger']} - {$trigger['Event']} - {$trigger['Timing']}<br>";
        }
    } else {
        echo "✅ Không có trigger nào<br>";
    }
} catch (Exception $e) {
    echo "❌ Error: " . $e->getMessage();
}

// 6. Xem CREATE TABLE statement
echo "<h3>6. CREATE TABLE statement:</h3>";
try {
    $create_table = $pdo->query("SHOW CREATE TABLE gio_hang")->fetch();
    echo "<pre>" . htmlspecialchars($create_table['Create Table']) . "</pre>";
} catch (Exception $e) {
    echo "❌ Error: " . $e->getMessage();
}

// 7. Test session data
echo "<h3>7. Session data hiện tại:</h3>";
echo "<pre>";
print_r($_SESSION);
echo "</pre>";

// 8. Kiểm tra product từ URL
if (isset($_GET['product_id'])) {
    $product_id = (int)$_GET['product_id'];
    echo "<h3>8. Test với Product ID = $product_id:</h3>";
    
    // Kiểm tra product có tồn tại không
    $check_product = $pdo->prepare("SELECT id, ten_san_pham FROM san_pham_chinh WHERE id = ?");
    $check_product->execute([$product_id]);
    $product = $check_product->fetch();
    
    if ($product) {
        echo "✅ Product exists: " . htmlspecialchars($product['ten_san_pham']) . "<br>";
        
        // Test thực tế insert
        try {
            $actual_insert = $pdo->prepare("
                INSERT INTO gio_hang (khach_hang_id, session_id, san_pham_id, bien_the_id, so_luong, gia_tai_thoi_diem, ngay_them)
                VALUES (?, ?, ?, ?, ?, ?, NOW())
            ");
            $actual_insert->execute([17, 'actual_test', $product_id, 6, 1, 150000]);
            echo "✅ SUCCESS: Actual insert thành công<br>";
            
            // Xóa
            $pdo->prepare("DELETE FROM gio_hang WHERE session_id = 'actual_test'")->execute();
            echo "✅ Đã xóa actual test record<br>";
            
        } catch (Exception $e) {
            echo "❌ ACTUAL INSERT FAILED: " . $e->getMessage() . "<br>";
        }
    } else {
        echo "❌ Product not found<br>";
    }
}

echo "<h3>🔗 Test với product cụ thể:</h3>";
echo "<a href='debug_insert.php?product_id=3'>Test với Product ID = 3</a><br>";
echo "<a href='product_detail.php?slug=converse-chuck-taylor'>Back to Product Detail</a>";
?>

<style>
table { margin: 10px 0; font-size: 12px; }
th, td { padding: 5px; text-align: left; }
pre { background: #f5f5f5; padding: 10px; overflow-x: auto; font-size: 11px; }
h3 { color: #333; border-bottom: 1px solid #ccc; padding-bottom: 5px; }
</style>