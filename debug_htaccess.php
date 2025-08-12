<?php
/**
 * Debug script to check for .htaccess redirect rules
 * Save as /tktshop/debug_htaccess.php and run
 */

echo "<h1>🔧 Debug .htaccess Files</h1>";

$paths_to_check = [
    $_SERVER['DOCUMENT_ROOT'] . '/.htaccess',
    $_SERVER['DOCUMENT_ROOT'] . '/tktshop/.htaccess', 
    $_SERVER['DOCUMENT_ROOT'] . '/tktshop/customer/.htaccess',
    dirname(__FILE__) . '/.htaccess',
    dirname(__FILE__) . '/customer/.htaccess'
];

foreach ($paths_to_check as $path) {
    echo "<h2>Checking: $path</h2>";
    
    if (file_exists($path)) {
        echo "<div style='background: #ffffcc; padding: 10px; margin: 10px 0; border: 1px solid #ffcc00;'>";
        echo "<strong>✅ File EXISTS</strong><br>";
        echo "<strong>Content:</strong><br>";
        echo "<pre style='background: white; padding: 10px; overflow-x: auto;'>";
        echo htmlspecialchars(file_get_contents($path));
        echo "</pre>";
        echo "</div>";
    } else {
        echo "<div style='background: #f0f0f0; padding: 10px; margin: 10px 0; border: 1px solid #ccc;'>";
        echo "❌ File NOT found";
        echo "</div>";
    }
}

echo "<h2>🔧 Current Request Info</h2>";
echo "<div style='background: #e6f3ff; padding: 10px; margin: 10px 0; border: 1px solid #0066cc;'>";
echo "<strong>REQUEST_URI:</strong> " . ($_SERVER['REQUEST_URI'] ?? 'Not set') . "<br>";
echo "<strong>SCRIPT_NAME:</strong> " . ($_SERVER['SCRIPT_NAME'] ?? 'Not set') . "<br>";
echo "<strong>PHP_SELF:</strong> " . ($_SERVER['PHP_SELF'] ?? 'Not set') . "<br>";
echo "<strong>HTTP_HOST:</strong> " . ($_SERVER['HTTP_HOST'] ?? 'Not set') . "<br>";
echo "<strong>DOCUMENT_ROOT:</strong> " . ($_SERVER['DOCUMENT_ROOT'] ?? 'Not set') . "<br>";
echo "<strong>Current file:</strong> " . __FILE__ . "<br>";
echo "</div>";

echo "<h2>🔧 Check for PHP redirect code</h2>";
$files_to_check = [
    dirname(__FILE__) . '/customer/cart.php',
    dirname(__FILE__) . '/customer/products.php',
    dirname(__FILE__) . '/customer/includes/header.php',
    dirname(__FILE__) . '/customer/includes/footer.php'
];

foreach ($files_to_check as $file) {
    if (file_exists($file)) {
        echo "<h3>$file</h3>";
        $content = file_get_contents($file);
        
        // Check for redirect patterns
        $redirect_patterns = [
            'header("Location:',
            'header(\'Location:',
            'wp_redirect',
            'window.location',
            'cart.php',
            'products.php'
        ];
        
        $found_redirects = [];
        foreach ($redirect_patterns as $pattern) {
            if (stripos($content, $pattern) !== false) {
                $found_redirects[] = $pattern;
            }
        }
        
        if (!empty($found_redirects)) {
            echo "<div style='background: #ffeeee; padding: 10px; margin: 10px 0; border: 1px solid #ff0000;'>";
            echo "<strong>⚠️ Found potential redirects:</strong><br>";
            foreach ($found_redirects as $pattern) {
                echo "- $pattern<br>";
            }
            echo "</div>";
        } else {
            echo "<div style='background: #eeffee; padding: 10px; margin: 10px 0; border: 1px solid #00aa00;'>";
            echo "✅ No redirect patterns found";
            echo "</div>";
        }
    }
}

echo "<h2>🔧 Apache modules info</h2>";
if (function_exists('apache_get_modules')) {
    $modules = apache_get_modules();
    echo "<div style='background: #f0f8ff; padding: 10px; margin: 10px 0; border: 1px solid #0066cc;'>";
    echo "<strong>mod_rewrite enabled:</strong> " . (in_array('mod_rewrite', $modules) ? '✅ YES' : '❌ NO') . "<br>";
    echo "<strong>All modules:</strong> " . implode(', ', $modules);
    echo "</div>";
} else {
    echo "<div style='background: #fff0f0; padding: 10px; margin: 10px 0; border: 1px solid #cc0000;'>";
    echo "❌ apache_get_modules() not available";
    echo "</div>";
}

echo "<h2>🔧 Test direct file access</h2>";
echo "<div style='background: #f8f8f8; padding: 10px; margin: 10px 0; border: 1px solid #888;'>";
echo "<a href='/tktshop/customer/cart_fixed.php' target='_blank'>Test cart_fixed.php direct access</a><br>";
echo "<a href='/tktshop/customer/checkout.php' target='_blank'>Test checkout.php direct access</a><br>";
echo "<a href='/tktshop/customer/products_fixed.php' target='_blank'>Test products_fixed.php direct access</a><br>";
echo "<a href='/tktshop/customer/product_detail.php?id=1' target='_blank'>Test product_detail.php direct access</a><br>";
echo "</div>";
?>