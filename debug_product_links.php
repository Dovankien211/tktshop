<?php
/**
 * Debug sản phẩm và links
 * Save as /tktshop/debug_product_links.php
 */

require_once 'config/database.php';
require_once 'config/config.php';

echo "<h1>🔧 Debug Product Links & Database</h1>";

// 1. Check database schema
echo "<h2>1. Database Schema Check</h2>";
try {
    $columns = $pdo->query("DESCRIBE don_hang")->fetchAll(PDO::FETCH_COLUMN);
    
    echo "<div style='background: #f0f8ff; padding: 15px; border: 1px solid #007bff;'>";
    echo "<strong>don_hang table columns:</strong><br>";
    
    $hasThue = false;
    foreach ($columns as $column) {
        if ($column === 'thue') $hasThue = true;
        echo "- $column" . ($column === 'thue' ? ' ✅' : '') . "<br>";
    }
    echo "</div>";
    
    if (!$hasThue) {
        echo "<div style='background: #ffe8e8; padding: 10px; border: 1px solid #f44336; margin-top: 10px;'>";
        echo "❌ Column 'thue' NOT found! This will cause checkout errors.<br>";
        echo "<strong>Fix SQL:</strong><br>";
        echo "<code>ALTER TABLE don_hang ADD COLUMN thue DECIMAL(15,2) DEFAULT 0.00 AFTER phi_van_chuyen;</code>";
        echo "</div>";
    } else {
        echo "<div style='background: #e8f5e8; padding: 10px; border: 1px solid #4caf50; margin-top: 10px;'>";
        echo "✅ Column 'thue' found! Database schema is correct.";
        echo "</div>";
    }
    
} catch (Exception $e) {
    echo "<div style='background: #ffe8e8; padding: 10px; border: 1px solid #f44336;'>";
    echo "❌ Database error: " . $e->getMessage();
    echo "</div>";
}

// 2. Check sample products from both tables
echo "<h2>2. Sample Products from Both Tables</h2>";

// Products table
echo "<h3>From 'products' table:</h3>";
try {
    $products_en = $pdo->query("
        SELECT id, name, slug, price, sale_price, stock_quantity, status 
        FROM products 
        WHERE status = 'active' 
        LIMIT 3
    ")->fetchAll();
    
    if (!empty($products_en)) {
        echo "<table style='border-collapse: collapse; width: 100%; margin: 10px 0;'>";
        echo "<tr style='background: #f8f9fa;'><th style='border: 1px solid #ddd; padding: 8px;'>ID</th><th style='border: 1px solid #ddd; padding: 8px;'>Name</th><th style='border: 1px solid #ddd; padding: 8px;'>Slug</th><th style='border: 1px solid #ddd; padding: 8px;'>Link</th></tr>";
        
        foreach ($products_en as $product) {
            $link = !empty($product['slug']) ? 
                "product_detail.php?slug=" . urlencode($product['slug']) : 
                "product_detail.php?id=" . $product['id'];
            
            echo "<tr>";
            echo "<td style='border: 1px solid #ddd; padding: 8px;'>{$product['id']}</td>";
            echo "<td style='border: 1px solid #ddd; padding: 8px;'>" . htmlspecialchars($product['name']) . "</td>";
            echo "<td style='border: 1px solid #ddd; padding: 8px;'>" . htmlspecialchars($product['slug'] ?? 'NULL') . "</td>";
            echo "<td style='border: 1px solid #ddd; padding: 8px;'><a href='/tktshop/customer/$link' target='_blank'>$link</a></td>";
            echo "</tr>";
        }
        echo "</table>";
    } else {
        echo "<p style='color: #666;'>No products found in 'products' table</p>";
    }
} catch (Exception $e) {
    echo "<p style='color: red;'>Error querying products table: " . $e->getMessage() . "</p>";
}

// San_pham_chinh table
echo "<h3>From 'san_pham_chinh' table:</h3>";
try {
    $products_vn = $pdo->query("
        SELECT id, ten_san_pham, slug, gia_goc, gia_khuyen_mai, trang_thai 
        FROM san_pham_chinh 
        WHERE trang_thai = 'hoat_dong' 
        LIMIT 3
    ")->fetchAll();
    
    if (!empty($products_vn)) {
        echo "<table style='border-collapse: collapse; width: 100%; margin: 10px 0;'>";
        echo "<tr style='background: #f8f9fa;'><th style='border: 1px solid #ddd; padding: 8px;'>ID</th><th style='border: 1px solid #ddd; padding: 8px;'>Name</th><th style='border: 1px solid #ddd; padding: 8px;'>Slug</th><th style='border: 1px solid #ddd; padding: 8px;'>Link</th></tr>";
        
        foreach ($products_vn as $product) {
            $link = !empty($product['slug']) ? 
                "product_detail.php?slug=" . urlencode($product['slug']) : 
                "product_detail.php?id=" . $product['id'];
            
            echo "<tr>";
            echo "<td style='border: 1px solid #ddd; padding: 8px;'>{$product['id']}</td>";
            echo "<td style='border: 1px solid #ddd; padding: 8px;'>" . htmlspecialchars($product['ten_san_pham']) . "</td>";
            echo "<td style='border: 1px solid #ddd; padding: 8px;'>" . htmlspecialchars($product['slug'] ?? 'NULL') . "</td>";
            echo "<td style='border: 1px solid #ddd; padding: 8px;'><a href='/tktshop/customer/$link' target='_blank'>$link</a></td>";
            echo "</tr>";
        }
        echo "</table>";
    } else {
        echo "<p style='color: #666;'>No products found in 'san_pham_chinh' table</p>";
    }
} catch (Exception $e) {
    echo "<p style='color: red;'>Error querying san_pham_chinh table: " . $e->getMessage() . "</p>";
}

// 3. Test direct links
echo "<h2>3. Test Direct Links</h2>";
echo "<div style='background: #f8f8f8; padding: 15px; border: 1px solid #888;'>";
echo "<p><strong>Click these to test:</strong></p>";
echo "<p><a href='/tktshop/customer/products_fixed.php' target='_blank'>→ products_fixed.php</a></p>";
echo "<p><a href='/tktshop/customer/product_detail.php?id=1' target='_blank'>→ product_detail.php?id=1</a></p>";
echo "<p><a href='/tktshop/customer/product_detail.php?id=7' target='_blank'>→ product_detail.php?id=7</a></p>";
echo "<p><a href='/tktshop/customer/cart_fixed.php' target='_blank'>→ cart_fixed.php</a></p>";
echo "<p><a href='/tktshop/customer/checkout.php' target='_blank'>→ checkout.php</a></p>";
echo "</div>";

// 4. Check .htaccess rules
echo "<h2>4. Current .htaccess Rules</h2>";
$htaccess_path = $_SERVER['DOCUMENT_ROOT'] . '/tktshop/.htaccess';
if (file_exists($htaccess_path)) {
    $htaccess_content = file_get_contents($htaccess_path);
    
    // Check for problematic rules
    $problematic_rules = [
        'RewriteRule ^products/?$ customer/products.php [L,QSA]',
        'RewriteRule ^cart/?$ customer/cart.php [L]'
    ];
    
    echo "<div style='background: #f0f8ff; padding: 15px; border: 1px solid #007bff;'>";
    foreach ($problematic_rules as $rule) {
        if (strpos($htaccess_content, $rule) !== false) {
            echo "<p style='color: red;'>❌ Found problematic rule: <code>$rule</code></p>";
        } else {
            echo "<p style='color: green;'>✅ Rule not found (good): <code>$rule</code></p>";
        }
    }
    echo "</div>";
} else {
    echo "<p style='color: red;'>❌ .htaccess file not found at: $htaccess_path</p>";
}

echo "<h2>5. Request Info</h2>";
echo "<div style='background: #e6f3ff; padding: 10px; margin: 10px 0; border: 1px solid #0066cc;'>";
echo "<strong>Current Request:</strong><br>";
echo "REQUEST_URI: " . ($_SERVER['REQUEST_URI'] ?? 'Not set') . "<br>";
echo "HTTP_HOST: " . ($_SERVER['HTTP_HOST'] ?? 'Not set') . "<br>";
echo "DOCUMENT_ROOT: " . ($_SERVER['DOCUMENT_ROOT'] ?? 'Not set') . "<br>";
echo "</div>";
?>