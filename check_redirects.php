<?php
/**
 * Check original cart.php and products.php for redirect code
 * Save as /tktshop/check_redirects.php
 */

echo "<h1>🔧 Check for PHP Redirects in Original Files</h1>";

$files_to_analyze = [
    '/tktshop/customer/cart.php',
    '/tktshop/customer/products.php',
    '/tktshop/customer/product_detail.php'
];

foreach ($files_to_analyze as $relative_path) {
    $full_path = $_SERVER['DOCUMENT_ROOT'] . $relative_path;
    
    echo "<h2>Analyzing: $relative_path</h2>";
    
    if (file_exists($full_path)) {
        $content = file_get_contents($full_path);
        
        echo "<div style='background: #f0f8ff; padding: 15px; margin: 10px 0; border: 1px solid #0066cc;'>";
        echo "<strong>File size:</strong> " . filesize($full_path) . " bytes<br>";
        echo "<strong>Last modified:</strong> " . date('Y-m-d H:i:s', filemtime($full_path)) . "<br><br>";
        
        // Check for redirect patterns
        $redirect_checks = [
            'header("Location:' => 'PHP Header Redirect',
            'header(\'Location:' => 'PHP Header Redirect (single quotes)',
            'window.location' => 'JavaScript Redirect',
            'location.href' => 'JavaScript Redirect href',
            'redirect(' => 'Redirect Function Call',
            'cart_fixed.php' => 'Reference to cart_fixed.php',
            'checkout.php' => 'Reference to checkout.php',
            'products_fixed.php' => 'Reference to products_fixed.php',
            'product_detail.php' => 'Reference to product_detail.php'
        ];
        
        $found_issues = [];
        foreach ($redirect_checks as $pattern => $description) {
            $count = substr_count(strtolower($content), strtolower($pattern));
            if ($count > 0) {
                $found_issues[] = "$description ($count times)";
            }
        }
        
        if (!empty($found_issues)) {
            echo "<strong>⚠️ Found patterns:</strong><br>";
            foreach ($found_issues as $issue) {
                echo "- $issue<br>";
            }
            echo "<br>";
        }
        
        // Show first 20 lines
        $lines = explode("\n", $content);
        echo "<strong>First 20 lines:</strong><br>";
        echo "<pre style='background: white; padding: 10px; overflow-x: auto; max-height: 300px; border: 1px solid #ddd;'>";
        for ($i = 0; $i < min(20, count($lines)); $i++) {
            echo sprintf("%02d: %s\n", $i + 1, htmlspecialchars($lines[$i]));
        }
        echo "</pre>";
        
        // Show any header() calls
        if (stripos($content, 'header(') !== false) {
            echo "<strong>🚨 Header calls found:</strong><br>";
            echo "<pre style='background: #ffeeee; padding: 10px; border: 1px solid #ff0000;'>";
            
            preg_match_all('/header\s*\([^)]+\)/i', $content, $matches);
            foreach ($matches[0] as $match) {
                echo htmlspecialchars($match) . "\n";
            }
            echo "</pre>";
        }
        
        echo "</div>";
        
    } else {
        echo "<div style='background: #f0f0f0; padding: 10px; margin: 10px 0; border: 1px solid #ccc;'>";
        echo "❌ File NOT found: $full_path";
        echo "</div>";
    }
}

echo "<h2>🔧 Direct Test Links</h2>";
echo "<div style='background: #f8f8f8; padding: 15px; margin: 10px 0; border: 1px solid #888;'>";
echo "<p><strong>Click these to test direct access (should NOT redirect):</strong></p>";
echo "<p><a href='/tktshop/customer/cart_fixed.php?direct_test=1' target='_blank'>Direct cart_fixed.php test</a></p>";
echo "<p><a href='/tktshop/customer/checkout.php?direct_test=1' target='_blank'>Direct checkout.php test</a></p>";
echo "<p><a href='/tktshop/customer/products_fixed.php?direct_test=1' target='_blank'>Direct products_fixed.php test</a></p>";
echo "</div>";

echo "<h2>🔧 Server Info</h2>";
echo "<div style='background: #f0f8ff; padding: 15px; margin: 10px 0; border: 1px solid #0066cc;'>";
echo "<strong>Server Software:</strong> " . ($_SERVER['SERVER_SOFTWARE'] ?? 'Unknown') . "<br>";
echo "<strong>Request Method:</strong> " . ($_SERVER['REQUEST_METHOD'] ?? 'Unknown') . "<br>";
echo "<strong>Query String:</strong> " . ($_SERVER['QUERY_STRING'] ?? 'None') . "<br>";
echo "</div>";
?>