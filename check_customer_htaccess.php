<?php
/**
 * Check customer/.htaccess file
 * Save as /tktshop/check_customer_htaccess.php
 */

echo "<h1>🔧 Customer .htaccess File Analysis</h1>";

$htaccess_customer = $_SERVER['DOCUMENT_ROOT'] . '/tktshop/customer/.htaccess';

if (file_exists($htaccess_customer)) {
    $content = file_get_contents($htaccess_customer);
    
    echo "<div style='background: #ffe8e8; padding: 15px; border: 1px solid #f44336;'>";
    echo "<strong>⚠️ PROBLEMATIC FILE FOUND:</strong><br>";
    echo "Path: <code>$htaccess_customer</code><br>";
    echo "Size: " . strlen($content) . " characters<br>";
    echo "</div>";
    
    echo "<h2>Full Content:</h2>";
    echo "<pre style='background: #f8f9fa; padding: 15px; border: 1px solid #333; overflow-x: auto;'>";
    echo htmlspecialchars($content);
    echo "</pre>";
    
    // Analyze problematic rules
    echo "<h2>Analysis:</h2>";
    
    $problematic_lines = [];
    $lines = explode("\n", $content);
    
    foreach ($lines as $i => $line) {
        $line = trim($line);
        if (empty($line) || $line[0] === '#') continue;
        
        if (stripos($line, 'rewriterule') !== false || 
            stripos($line, 'redirect') !== false) {
            $problematic_lines[] = "Line " . ($i + 1) . ": " . $line;
        }
    }
    
    if (!empty($problematic_lines)) {
        echo "<div style='background: #fff3cd; padding: 15px; border: 1px solid #ffc107;'>";
        echo "<strong>🚨 PROBLEMATIC RULES FOUND:</strong><br>";
        foreach ($problematic_lines as $line) {
            echo "<code style='color: #d63384;'>$line</code><br>";
        }
        echo "</div>";
    }
    
    echo "<h2>🔧 SOLUTION:</h2>";
    echo "<div style='background: #e8f4fd; padding: 15px; border: 1px solid #007bff;'>";
    echo "<strong>Option 1 - DELETE the file:</strong><br>";
    echo "<code>Delete /tktshop/customer/.htaccess</code><br><br>";
    
    echo "<strong>Option 2 - REPLACE with safe content:</strong><br>";
    echo "Replace with minimal safe rules (see below)<br>";
    echo "</div>";
    
} else {
    echo "<div style='background: #e8f5e8; padding: 15px; border: 1px solid #4caf50;'>";
    echo "✅ Customer .htaccess file NOT found (good!)";
    echo "</div>";
}

// Show safe replacement content
echo "<h2>🔧 Safe Replacement Content</h2>";
echo "<p><strong>If you want to keep the file, replace its content with this safe version:</strong></p>";
echo "<pre style='background: #e8f5e8; padding: 15px; border: 1px solid #4caf50; overflow-x: auto;'>";
echo htmlspecialchars('# File: customer/.htaccess - SAFE VERSION
# Minimal rules without conflicts

# Set default index file
DirectoryIndex index.php

# Security - Hide sensitive files
<Files "*.php~">
    Order Allow,Deny
    Deny from all
</Files>

# Cache static files
<IfModule mod_expires.c>
    ExpiresActive On
    ExpiresByType image/jpg "access plus 1 month"
    ExpiresByType image/jpeg "access plus 1 month"
    ExpiresByType image/gif "access plus 1 month"
    ExpiresByType image/png "access plus 1 month"
    ExpiresByType text/css "access plus 1 month"
    ExpiresByType application/pdf "access plus 1 month"
    ExpiresByType text/javascript "access plus 1 month"
    ExpiresByType application/javascript "access plus 1 month"
</IfModule>

# NO REWRITE RULES - Let parent .htaccess handle routing');
echo "</pre>";

echo "<h2>🔧 Check Product Data Difference</h2>";

// Check products added from admin vs existing
try {
    require_once 'config/database.php';
    
    echo "<h3>Products from 'products' table (added via admin):</h3>";
    $products_en = $pdo->query("
        SELECT id, name, slug, main_image, created_at 
        FROM products 
        WHERE status = 'active' 
        ORDER BY id DESC 
        LIMIT 5
    ")->fetchAll();
    
    if (!empty($products_en)) {
        echo "<table border='1' style='border-collapse: collapse; width: 100%;'>";
        echo "<tr><th>ID</th><th>Name</th><th>Slug</th><th>Image</th><th>Created</th></tr>";
        foreach ($products_en as $p) {
            echo "<tr>";
            echo "<td>{$p['id']}</td>";
            echo "<td>" . htmlspecialchars($p['name']) . "</td>";
            echo "<td>" . htmlspecialchars($p['slug'] ?? 'NULL') . "</td>";
            echo "<td>" . htmlspecialchars($p['main_image'] ?? 'NULL') . "</td>";
            echo "<td>{$p['created_at']}</td>";
            echo "</tr>";
        }
        echo "</table>";
    }
    
    echo "<h3>Products from 'san_pham_chinh' table (existing):</h3>";
    $products_vn = $pdo->query("
        SELECT id, ten_san_pham, slug, hinh_anh_chinh, ngay_tao 
        FROM san_pham_chinh 
        WHERE trang_thai = 'hoat_dong' 
        ORDER BY id DESC 
        LIMIT 5
    ")->fetchAll();
    
    if (!empty($products_vn)) {
        echo "<table border='1' style='border-collapse: collapse; width: 100%;'>";
        echo "<tr><th>ID</th><th>Name</th><th>Slug</th><th>Image</th><th>Created</th></tr>";
        foreach ($products_vn as $p) {
            echo "<tr>";
            echo "<td>{$p['id']}</td>";
            echo "<td>" . htmlspecialchars($p['ten_san_pham']) . "</td>";
            echo "<td>" . htmlspecialchars($p['slug'] ?? 'NULL') . "</td>";
            echo "<td>" . htmlspecialchars($p['hinh_anh_chinh'] ?? 'NULL') . "</td>";
            echo "<td>{$p['ngay_tao']}</td>";
            echo "</tr>";
        }
        echo "</table>";
    }
    
} catch (Exception $e) {
    echo "<p style='color: red;'>Database error: " . $e->getMessage() . "</p>";
}
?>