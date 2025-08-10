<?php
/**
 * test_final.php - Test cuối cùng sau khi fix
 * Tạo file: tktshop/test_final.php
 */

echo "<h1>🧪 TEST CUỐI CÙNG - SAU KHI FIX</h1>";
echo "<p>Thời gian: " . date('Y-m-d H:i:s') . "</p>";
echo "<hr>";

// Test 1: Database class
echo "<h2>1. Test Database Class</h2>";
try {
    require_once 'config/database.php';
    
    if (class_exists('Database')) {
        echo "✅ Database class exists<br>";
        
        $db = Database::getInstance();
        $pdo = $db->getConnection();
        echo "✅ Database connection successful<br>";
        
        // Test query
        $result = $pdo->query("SELECT COUNT(*) as count FROM products")->fetch();
        echo "✅ Products count: " . $result['count'] . "<br>";
        
    } else {
        echo "❌ Database class not found<br>";
    }
} catch (Exception $e) {
    echo "❌ Error: " . $e->getMessage() . "<br>";
}
echo "<hr>";

// Test 2: Thêm sản phẩm test
echo "<h2>2. Test Thêm Sản phẩm</h2>";
try {
    if (isset($pdo)) {
        // Thêm sản phẩm test
        $stmt = $pdo->prepare("INSERT INTO products (name, sku, description, price, category_id, status, stock_quantity, is_featured, created_at) VALUES (?, ?, ?, ?, 1, 'active', 50, 1, NOW())");
        
        $test_name = "Test Product " . date('H:i:s');
        $test_sku = "TEST" . time();
        
        $result = $stmt->execute([
            $test_name,
            $test_sku,
            "Đây là sản phẩm test để kiểm tra hiển thị",
            299000
        ]);
        
        if ($result) {
            $product_id = $pdo->lastInsertId();
            echo "✅ Thêm sản phẩm test thành công! ID: $product_id<br>";
            echo "📦 Tên: $test_name<br>";
            echo "🏷️ SKU: $test_sku<br>";
            echo "💰 Giá: 299,000₫<br>";
            
            // Kiểm tra sản phẩm có thể query được không
            $check = $pdo->prepare("SELECT * FROM products WHERE id = ? AND status = 'active'");
            $check->execute([$product_id]);
            $product = $check->fetch();
            
            if ($product) {
                echo "✅ Sản phẩm có thể query được từ database<br>";
            } else {
                echo "❌ Không thể query sản phẩm<br>";
            }
            
        } else {
            echo "❌ Không thể thêm sản phẩm test<br>";
        }
    }
} catch (Exception $e) {
    echo "❌ Error adding test product: " . $e->getMessage() . "<br>";
}
echo "<hr>";

// Test 3: Query như customer/products.php
echo "<h2>3. Test Query Customer Products</h2>";
try {
    if (isset($pdo)) {
        $sql = "
            SELECT p.*, c.name as category_name,
                   COALESCE(p.sale_price, p.price) as current_price
            FROM products p
            LEFT JOIN categories c ON p.category_id = c.id
            WHERE p.status = 'active' AND p.stock_quantity > 0
            ORDER BY p.created_at DESC
            LIMIT 5
        ";
        
        $stmt = $pdo->query($sql);
        $products = $stmt->fetchAll();
        
        echo "📊 Sản phẩm active có thể hiển thị: " . count($products) . "<br>";
        
        if (count($products) > 0) {
            echo "✅ <strong style='color:green;'>CÓ SẢN PHẨM HIỂN THỊ!</strong><br>";
            echo "<table border='1' cellpadding='8' style='border-collapse:collapse;'>";
            echo "<tr style='background:#f0f0f0;'><th>ID</th><th>Tên</th><th>SKU</th><th>Giá</th><th>Danh mục</th><th>Tồn kho</th></tr>";
            
            foreach ($products as $p) {
                echo "<tr>";
                echo "<td>" . $p['id'] . "</td>";
                echo "<td>" . htmlspecialchars($p['name']) . "</td>";
                echo "<td>" . htmlspecialchars($p['sku']) . "</td>";
                echo "<td>" . number_format($p['current_price']) . "₫</td>";
                echo "<td>" . htmlspecialchars($p['category_name'] ?? 'N/A') . "</td>";
                echo "<td>" . $p['stock_quantity'] . "</td>";
                echo "</tr>";
            }
            echo "</table>";
        } else {
            echo "❌ <strong style='color:red;'>KHÔNG CÓ SẢN PHẨM HIỂN THỊ</strong><br>";
        }
    }
} catch (Exception $e) {
    echo "❌ Error in customer query: " . $e->getMessage() . "<br>";
}
echo "<hr>";

// Test 4: Check customer/products.php file
echo "<h2>4. Test Customer Products File</h2>";
if (file_exists('customer/products.php')) {
    echo "✅ File customer/products.php exists<br>";
    echo "📄 <a href='customer/products.php' target='_blank'>🔗 Test customer/products.php</a><br>";
    echo "👉 Click link trên để xem có hiển thị sản phẩm không<br>";
} else {
    echo "❌ File customer/products.php not found<br>";
}
echo "<hr>";

// Test 5: Thống kê tổng quan
echo "<h2>5. Thống kê Tổng Quan</h2>";
try {
    if (isset($pdo)) {
        $stats = [
            'total_products' => $pdo->query("SELECT COUNT(*) FROM products")->fetchColumn(),
            'active_products' => $pdo->query("SELECT COUNT(*) FROM products WHERE status = 'active'")->fetchColumn(),
            'in_stock_products' => $pdo->query("SELECT COUNT(*) FROM products WHERE status = 'active' AND stock_quantity > 0")->fetchColumn(),
            'categories' => $pdo->query("SELECT COUNT(*) FROM categories WHERE status = 'active'")->fetchColumn(),
            'featured_products' => $pdo->query("SELECT COUNT(*) FROM products WHERE status = 'active' AND is_featured = 1")->fetchColumn()
        ];
        
        echo "<div style='background:#e8f5e8;padding:15px;border-radius:8px;'>";
        echo "<strong>📊 THỐNG KÊ HỆ THỐNG:</strong><br>";
        echo "🛍️ Tổng sản phẩm: <strong>" . $stats['total_products'] . "</strong><br>";
        echo "✅ Sản phẩm active: <strong>" . $stats['active_products'] . "</strong><br>";
        echo "📦 Sản phẩm còn hàng: <strong>" . $stats['in_stock_products'] . "</strong><br>";
        echo "📂 Danh mục active: <strong>" . $stats['categories'] . "</strong><br>";
        echo "⭐ Sản phẩm nổi bật: <strong>" . $stats['featured_products'] . "</strong><br>";
        echo "</div>";
    }
} catch (Exception $e) {
    echo "❌ Error getting stats: " . $e->getMessage() . "<br>";
}
echo "<hr>";

// Kết luận
echo "<h2>🎯 KẾT LUẬN</h2>";
$issues = [];

if (!class_exists('Database')) {
    $issues[] = "Database class không tồn tại";
}

if (!isset($pdo)) {
    $issues[] = "Không thể kết nối database";
}

if (isset($stats) && $stats['in_stock_products'] == 0) {
    $issues[] = "Không có sản phẩm nào có thể hiển thị (active + in stock)";
}

if (!file_exists('customer/products.php')) {
    $issues[] = "Thiếu file customer/products.php";
}

if (empty($issues)) {
    echo "<div style='background:#e8f5e8;border:2px solid #4caf50;padding:20px;border-radius:10px;'>";
    echo "<h3 style='color:#2e7d32;margin-top:0;'>🎉 THÀNH CÔNG!</h3>";
    echo "<p><strong>Hệ thống đã hoạt động bình thường!</strong></p>";
    echo "<p>✅ Database kết nối OK</p>";
    echo "<p>✅ Có sản phẩm để hiển thị</p>";
    echo "<p>✅ File customer/products.php tồn tại</p>";
    echo "<p><strong>👉 Bây giờ hãy truy cập:</strong></p>";
    echo "<p>🔗 <a href='customer/products.php' style='font-size:18px;'>customer/products.php</a></p>";
    echo "<p>để xem sản phẩm hiển thị!</p>";
    echo "</div>";
} else {
    echo "<div style='background:#ffebee;border:2px solid #f44336;padding:20px;border-radius:10px;'>";
    echo "<h3 style='color:#c62828;margin-top:0;'>❌ VẪN CÓN VẤN ĐỀ</h3>";
    foreach ($issues as $issue) {
        echo "<p>❌ $issue</p>";
    }
    echo "</div>";
}

echo "<br><p><strong>⏰ Hoàn thành lúc:</strong> " . date('Y-m-d H:i:s') . "</p>";
?>