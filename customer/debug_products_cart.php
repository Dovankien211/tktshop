<?php
/**
 * DEBUG PRODUCTS & CART ISSUES
 * File: debug_products_cart.php
 * Đặt file này trong thư mục /tktshop/customer/
 */

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../config/config.php';

echo "<h1>🔧 DEBUG PRODUCTS & CART SYSTEM</h1>";
echo "<hr>";

// 1. CHECK DATABASE CONNECTION
echo "<h2>1. 🔗 Database Connection</h2>";
try {
    $pdo->query("SELECT 1");
    echo "✅ Database connection: <span style='color: green'>OK</span><br>";
} catch (Exception $e) {
    echo "❌ Database connection: <span style='color: red'>FAILED - " . $e->getMessage() . "</span><br>";
}

// 2. CHECK TABLES EXIST
echo "<h2>2. 📊 Database Tables</h2>";
$tables_to_check = [
    'san_pham_chinh',
    'products', 
    'bien_the_san_pham',
    'gio_hang',
    'danh_muc_giay',
    'categories',
    'kich_co',
    'mau_sac'
];

foreach ($tables_to_check as $table) {
    try {
        $stmt = $pdo->query("SHOW TABLES LIKE '$table'");
        if ($stmt->rowCount() > 0) {
            echo "✅ Table '$table': <span style='color: green'>EXISTS</span><br>";
        } else {
            echo "❌ Table '$table': <span style='color: red'>NOT FOUND</span><br>";
        }
    } catch (Exception $e) {
        echo "❌ Table '$table': <span style='color: red'>ERROR - " . $e->getMessage() . "</span><br>";
    }
}

// 3. CHECK PRODUCTS DATA
echo "<h2>3. 🏷️ Products Data</h2>";

// Check Vietnamese schema first
try {
    $stmt = $pdo->query("SELECT COUNT(*) as count FROM san_pham_chinh");
    $count = $stmt->fetchColumn();
    echo "📦 Products in 'san_pham_chinh': <strong>$count</strong><br>";
    
    if ($count > 0) {
        echo "<h3>Sample Products (san_pham_chinh):</h3>";
        $stmt = $pdo->query("
            SELECT id, ten_san_pham, slug, trang_thai, danh_muc_id 
            FROM san_pham_chinh 
            LIMIT 5
        ");
        while ($row = $stmt->fetch()) {
            echo "- ID: {$row['id']}, Name: {$row['ten_san_pham']}, Status: {$row['trang_thai']}, Category: {$row['danh_muc_id']}<br>";
        }
    }
} catch (Exception $e) {
    echo "❌ Error checking 'san_pham_chinh': " . $e->getMessage() . "<br>";
}

// Check English schema
try {
    $stmt = $pdo->query("SELECT COUNT(*) as count FROM products");
    $count = $stmt->fetchColumn();
    echo "📦 Products in 'products': <strong>$count</strong><br>";
    
    if ($count > 0) {
        echo "<h3>Sample Products (products):</h3>";
        $stmt = $pdo->query("
            SELECT id, name, slug, status, category_id 
            FROM products 
            LIMIT 5
        ");
        while ($row = $stmt->fetch()) {
            echo "- ID: {$row['id']}, Name: {$row['name']}, Status: {$row['status']}, Category: {$row['category_id']}<br>";
        }
    }
} catch (Exception $e) {
    echo "❌ Error checking 'products': " . $e->getMessage() . "<br>";
}

// 4. CHECK SPECIFIC PRODUCT ID=7
echo "<h2>4. 🔍 Check Product ID=7</h2>";

// Vietnamese schema
try {
    $stmt = $pdo->prepare("SELECT * FROM san_pham_chinh WHERE id = ?");
    $stmt->execute([7]);
    $product = $stmt->fetch();
    
    if ($product) {
        echo "✅ Product ID=7 found in 'san_pham_chinh':<br>";
        echo "- Name: " . htmlspecialchars($product['ten_san_pham']) . "<br>";
        echo "- Status: " . $product['trang_thai'] . "<br>";
        echo "- Category ID: " . $product['danh_muc_id'] . "<br>";
        echo "- Price: " . formatPrice($product['gia_goc']) . "<br>";
    } else {
        echo "❌ Product ID=7 NOT found in 'san_pham_chinh'<br>";
    }
} catch (Exception $e) {
    echo "❌ Error checking product ID=7 in 'san_pham_chinh': " . $e->getMessage() . "<br>";
}

// English schema
try {
    $stmt = $pdo->prepare("SELECT * FROM products WHERE id = ?");
    $stmt->execute([7]);
    $product = $stmt->fetch();
    
    if ($product) {
        echo "✅ Product ID=7 found in 'products':<br>";
        echo "- Name: " . htmlspecialchars($product['name']) . "<br>";
        echo "- Status: " . $product['status'] . "<br>";
        echo "- Category ID: " . $product['category_id'] . "<br>";
        echo "- Price: " . formatPrice($product['price']) . "<br>";
    } else {
        echo "❌ Product ID=7 NOT found in 'products'<br>";
    }
} catch (Exception $e) {
    echo "❌ Error checking product ID=7 in 'products': " . $e->getMessage() . "<br>";
}

// 5. CHECK PRODUCT VARIANTS
echo "<h2>5. 🎨 Product Variants</h2>";
try {
    $stmt = $pdo->prepare("
        SELECT bsp.*, kc.kich_co, ms.ten_mau 
        FROM bien_the_san_pham bsp
        LEFT JOIN kich_co kc ON bsp.kich_co_id = kc.id
        LEFT JOIN mau_sac ms ON bsp.mau_sac_id = ms.id
        WHERE bsp.san_pham_id = ?
    ");
    $stmt->execute([7]);
    $variants = $stmt->fetchAll();
    
    echo "🎯 Variants for Product ID=7: <strong>" . count($variants) . "</strong><br>";
    foreach ($variants as $variant) {
        echo "- Variant ID: {$variant['id']}, Size: {$variant['kich_co']}, Color: {$variant['ten_mau']}, Stock: {$variant['so_luong_ton_kho']}, Status: {$variant['trang_thai']}<br>";
    }
} catch (Exception $e) {
    echo "❌ Error checking variants: " . $e->getMessage() . "<br>";
}

// 6. CHECK CART
echo "<h2>6. 🛒 Shopping Cart Debug</h2>";

$customer_id = $_SESSION['customer_id'] ?? null;
$session_id = $customer_id ? null : ($_SESSION['session_id'] ?? session_id());

echo "Customer ID: " . ($customer_id ?? 'NULL') . "<br>";
echo "Session ID: " . ($session_id ?? 'NULL') . "<br>";

// Check cart items
try {
    $stmt = $pdo->prepare("
        SELECT gh.*, sp.ten_san_pham, bsp.ma_sku, kc.kich_co, ms.ten_mau
        FROM gio_hang gh
        LEFT JOIN bien_the_san_pham bsp ON gh.bien_the_id = bsp.id
        LEFT JOIN san_pham_chinh sp ON bsp.san_pham_id = sp.id
        LEFT JOIN kich_co kc ON bsp.kich_co_id = kc.id
        LEFT JOIN mau_sac ms ON bsp.mau_sac_id = ms.id
        WHERE (gh.khach_hang_id = ? OR gh.session_id = ?)
        ORDER BY gh.ngay_them DESC
    ");
    $stmt->execute([$customer_id, $session_id]);
    $cart_items = $stmt->fetchAll();
    
    echo "🛒 Cart items found: <strong>" . count($cart_items) . "</strong><br>";
    foreach ($cart_items as $item) {
        echo "- Cart ID: {$item['id']}, Product: {$item['ten_san_pham']}, SKU: {$item['ma_sku']}, Qty: {$item['so_luong']}, Added: {$item['ngay_them']}<br>";
    }
} catch (Exception $e) {
    echo "❌ Error checking cart: " . $e->getMessage() . "<br>";
}

// 7. TEST ADD TO CART SIMULATION
echo "<h2>7. 🧪 Test Add to Cart</h2>";

// Find a valid variant
try {
    $stmt = $pdo->query("
        SELECT bsp.*, sp.ten_san_pham 
        FROM bien_the_san_pham bsp
        JOIN san_pham_chinh sp ON bsp.san_pham_id = sp.id
        WHERE bsp.trang_thai = 'hoat_dong' 
        AND bsp.so_luong_ton_kho > 0
        LIMIT 1
    ");
    $test_variant = $stmt->fetch();
    
    if ($test_variant) {
        echo "🎯 Testing with variant ID: {$test_variant['id']} ({$test_variant['ten_san_pham']})<br>";
        
        // Try to add to cart
        $stmt = $pdo->prepare("
            INSERT INTO gio_hang (khach_hang_id, session_id, bien_the_id, so_luong, gia_tai_thoi_diem, ngay_them)
            VALUES (?, ?, ?, 1, ?, NOW())
        ");
        $result = $stmt->execute([$customer_id, $session_id, $test_variant['id'], $test_variant['gia_ban']]);
        
        if ($result) {
            echo "✅ Test add to cart: <span style='color: green'>SUCCESS</span><br>";
        } else {
            echo "❌ Test add to cart: <span style='color: red'>FAILED</span><br>";
        }
    } else {
        echo "❌ No valid variants found for testing<br>";
    }
} catch (Exception $e) {
    echo "❌ Test add to cart error: " . $e->getMessage() . "<br>";
}

// 8. CHECK PRODUCTS.PHP QUERY
echo "<h2>8. 📋 Products.php Query Debug</h2>";

try {
    // Simulate the products.php query
    $stmt = $pdo->prepare("
        SELECT sp.*, dm.ten_danh_muc,
               COALESCE(sp.gia_khuyen_mai, sp.gia_goc) as current_price,
               CASE 
                   WHEN sp.gia_khuyen_mai IS NOT NULL AND sp.gia_khuyen_mai < sp.gia_goc 
                   THEN ROUND(((sp.gia_goc - sp.gia_khuyen_mai) / sp.gia_goc) * 100, 0)
                   ELSE 0
               END as discount_percent,
               MIN(bsp.gia_ban) as min_variant_price,
               SUM(bsp.so_luong_ton_kho) as total_stock
        FROM san_pham_chinh sp
        LEFT JOIN danh_muc_giay dm ON sp.danh_muc_id = dm.id
        LEFT JOIN bien_the_san_pham bsp ON sp.id = bsp.san_pham_id AND bsp.trang_thai = 'hoat_dong'
        WHERE sp.trang_thai = 'hoat_dong'
        GROUP BY sp.id
        HAVING total_stock > 0
        ORDER BY sp.ngay_tao DESC
        LIMIT 12
    ");
    $stmt->execute();
    $products = $stmt->fetchAll();
    
    echo "🏷️ Products found by products.php query: <strong>" . count($products) . "</strong><br>";
    foreach ($products as $product) {
        echo "- Product: {$product['ten_san_pham']}, Stock: {$product['total_stock']}, Price: " . formatPrice($product['current_price']) . "<br>";
    }
} catch (Exception $e) {
    echo "❌ Products.php query error: " . $e->getMessage() . "<br>";
}

// 9. RECOMMENDATIONS
echo "<h2>9. 💡 Recommendations</h2>";

echo "<div style='background: #f0f8ff; padding: 15px; border-radius: 5px;'>";

if (!$customer_id && !$session_id) {
    echo "⚠️ <strong>Session Issue:</strong> No customer_id or session_id found. Cart might not work properly.<br>";
}

echo "<h3>🔧 Quick Fixes:</h3>";
echo "1. <strong>Products not showing:</strong><br>";
echo "   - Check if products have 'trang_thai' = 'hoat_dong'<br>";
echo "   - Check if products have variants with stock > 0<br>";
echo "   - Verify database schema detection<br><br>";

echo "2. <strong>Cart not working:</strong><br>";
echo "   - Make sure session is started<br>";
echo "   - Check add_to_cart.php file exists<br>";
echo "   - Verify AJAX requests are working<br>";
echo "   - Check browser console for errors<br><br>";

echo "3. <strong>Product ID=7 issues:</strong><br>";
echo "   - Check if product exists in correct table<br>";
echo "   - Verify product has active variants<br>";
echo "   - Check if product_detail.php file exists<br>";

echo "</div>";

// 10. SYSTEM INFO
echo "<h2>10. ℹ️ System Information</h2>";
echo "PHP Version: " . PHP_VERSION . "<br>";
echo "MySQL Version: " . $pdo->query('SELECT VERSION()')->fetchColumn() . "<br>";
echo "Session Status: " . (session_status() == PHP_SESSION_ACTIVE ? 'Active' : 'Inactive') . "<br>";
echo "Current Time: " . date('Y-m-d H:i:s') . "<br>";

echo "<hr>";
echo "<p><strong>Debug completed!</strong> Review the results above to identify issues.</p>";
echo "<p><a href='products.php'>← Back to Products</a> | <a href='cart.php'>View Cart →</a></p>";
?>

<style>
body { font-family: Arial, sans-serif; max-width: 1200px; margin: 0 auto; padding: 20px; }
h1, h2, h3 { color: #333; }
hr { margin: 20px 0; }
.error { color: red; }
.success { color: green; }
</style>