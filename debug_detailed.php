<?php
/**
 * DETAILED DEBUG - Find exact problem
 * Save as /tktshop/debug_detailed.php
 */

echo "<h1>🔧 DETAILED DEBUG - Find Exact Problem</h1>";

// 1. Test direct access to files
echo "<h2>1. File Access Test</h2>";

$test_files = [
    '/tktshop/customer/products_fixed.php',
    '/tktshop/customer/cart_fixed.php', 
    '/tktshop/customer/checkout.php',
    '/tktshop/customer/product_detail.php'
];

foreach ($test_files as $file) {
    $full_path = $_SERVER['DOCUMENT_ROOT'] . $file;
    $exists = file_exists($full_path);
    $readable = $exists ? is_readable($full_path) : false;
    
    echo "<div style='padding: 10px; margin: 5px 0; border: 1px solid " . ($exists ? '#4caf50' : '#f44336') . "; background: " . ($exists ? '#e8f5e8' : '#ffe8e8') . ";'>";
    echo "<strong>$file</strong><br>";
    echo "Exists: " . ($exists ? '✅ YES' : '❌ NO') . "<br>";
    if ($exists) {
        echo "Readable: " . ($readable ? '✅ YES' : '❌ NO') . "<br>";
        echo "Size: " . filesize($full_path) . " bytes<br>";
        echo "<a href='$file' target='_blank'>→ Test Direct Access</a>";
    }
    echo "</div>";
}

// 2. Test URL patterns that might be conflicting
echo "<h2>2. URL Pattern Test</h2>";

$current_uri = $_SERVER['REQUEST_URI'] ?? '';
$parsed_url = parse_url($current_uri);

echo "<div style='background: #f0f8ff; padding: 15px; border: 1px solid #007bff;'>";
echo "<strong>Current Request Analysis:</strong><br>";
echo "REQUEST_URI: $current_uri<br>";
echo "PATH: " . ($parsed_url['path'] ?? 'none') . "<br>";
echo "QUERY: " . ($parsed_url['query'] ?? 'none') . "<br>";
echo "</div>";

// 3. Simulate the problematic requests
echo "<h2>3. Simulated Request Test</h2>";

$test_urls = [
    '/tktshop/customer/products_fixed.php' => 'Direct products_fixed.php access',
    '/tktshop/customer/cart_fixed.php' => 'Direct cart_fixed.php access',
    '/tktshop/customer/product_detail.php?id=7' => 'Product detail with ID',
    '/tktshop/customer/checkout.php' => 'Direct checkout access'
];

foreach ($test_urls as $url => $description) {
    echo "<div style='padding: 10px; margin: 5px 0; border: 1px solid #333; background: #f8f9fa;'>";
    echo "<strong>$description</strong><br>";
    echo "URL: <code>$url</code><br>";
    echo "<a href='$url' target='_blank' style='background: #007bff; color: white; padding: 5px 10px; text-decoration: none; border-radius: 3px;'>→ TEST THIS URL</a>";
    echo "</div>";
}

// 4. Check .htaccess content and test rewrite
echo "<h2>4. Current .htaccess Analysis</h2>";

$htaccess_path = $_SERVER['DOCUMENT_ROOT'] . '/tktshop/.htaccess';
if (file_exists($htaccess_path)) {
    $content = file_get_contents($htaccess_path);
    
    echo "<div style='background: #f8f9fa; padding: 15px; border: 1px solid #333;'>";
    echo "<strong>File exists:</strong> ✅ YES<br>";
    echo "<strong>Size:</strong> " . strlen($content) . " characters<br>";
    
    // Check for specific problematic patterns
    $bad_patterns = [
        'RewriteRule ^products/?$ customer/products.php [L,QSA]' => 'Products redirect to old file',
        'RewriteRule ^cart/?$ customer/cart.php [L]' => 'Cart redirect to old file',
        'RewriteRule ^products/?$ customer/products_fixed.php [L,QSA]' => 'Products redirect to fixed file',
        'RewriteRule ^cart/?$ customer/cart_fixed.php [L]' => 'Cart redirect to fixed file'
    ];
    
    echo "<strong>Pattern Analysis:</strong><br>";
    foreach ($bad_patterns as $pattern => $desc) {
        $found = strpos($content, $pattern) !== false;
        $color = $found ? '#e74c3c' : '#27ae60';
        echo "<span style='color: $color;'>" . ($found ? '❌' : '✅') . " $desc</span><br>";
    }
    
    echo "<br><strong>Full .htaccess content:</strong><br>";
    echo "<pre style='background: white; padding: 10px; border: 1px solid #ddd; max-height: 300px; overflow-y: auto; font-size: 12px;'>";
    echo htmlspecialchars($content);
    echo "</pre>";
    echo "</div>";
} else {
    echo "<div style='background: #ffe8e8; padding: 10px; border: 1px solid #f44336;'>";
    echo "❌ .htaccess file NOT found!";
    echo "</div>";
}

// 5. Check if mod_rewrite is working
echo "<h2>5. Apache mod_rewrite Test</h2>";

if (function_exists('apache_get_modules')) {
    $modules = apache_get_modules();
    $rewrite_enabled = in_array('mod_rewrite', $modules);
    
    echo "<div style='background: " . ($rewrite_enabled ? '#e8f5e8' : '#ffe8e8') . "; padding: 10px; border: 1px solid " . ($rewrite_enabled ? '#4caf50' : '#f44336') . ";'>";
    echo "mod_rewrite: " . ($rewrite_enabled ? '✅ ENABLED' : '❌ DISABLED') . "<br>";
    echo "Available modules: " . implode(', ', array_slice($modules, 0, 10)) . "...";
    echo "</div>";
} else {
    echo "<div style='background: #fff3cd; padding: 10px; border: 1px solid #ffc107;'>";
    echo "⚠️ Cannot check Apache modules (function not available)";
    echo "</div>";
}

// 6. Check if there are other .htaccess files
echo "<h2>6. Other .htaccess Files Check</h2>";

$check_paths = [
    $_SERVER['DOCUMENT_ROOT'] . '/.htaccess',
    $_SERVER['DOCUMENT_ROOT'] . '/tktshop/customer/.htaccess'
];

foreach ($check_paths as $path) {
    if (file_exists($path)) {
        echo "<div style='background: #fff3cd; padding: 10px; margin: 5px 0; border: 1px solid #ffc107;'>";
        echo "⚠️ Found additional .htaccess: <code>$path</code><br>";
        
        $content = file_get_contents($path);
        echo "Size: " . strlen($content) . " characters<br>";
        
        // Check for redirects
        if (strpos($content, 'Redirect') !== false || strpos($content, 'RewriteRule') !== false) {
            echo "<strong style='color: #e74c3c;'>⚠️ Contains redirect rules!</strong><br>";
            echo "<details><summary>Show content</summary>";
            echo "<pre style='background: white; padding: 10px; border: 1px solid #ddd; font-size: 11px;'>";
            echo htmlspecialchars($content);
            echo "</pre></details>";
        }
        echo "</div>";
    }
}

// 7. JavaScript test for AJAX behavior
echo "<h2>7. JavaScript Debug Test</h2>";
?>

<div style='background: #f8f9fa; padding: 15px; border: 1px solid #333;'>
    <button onclick="testDirectAccess()" style='background: #007bff; color: white; padding: 10px; border: none; border-radius: 5px; cursor: pointer;'>Test Direct Access via JavaScript</button>
    <button onclick="testAjaxCall()" style='background: #28a745; color: white; padding: 10px; border: none; border-radius: 5px; cursor: pointer; margin-left: 10px;'>Test AJAX Call</button>
    
    <div id="testResults" style='margin-top: 15px; padding: 10px; background: white; border: 1px solid #ddd;'></div>
</div>

<script>
function testDirectAccess() {
    const results = document.getElementById('testResults');
    results.innerHTML = '<strong>Testing direct access...</strong><br>';
    
    const testUrls = [
        '/tktshop/customer/products_fixed.php',
        '/tktshop/customer/cart_fixed.php',
        '/tktshop/customer/checkout.php'
    ];
    
    testUrls.forEach(url => {
        fetch(url, { method: 'HEAD' })
            .then(response => {
                results.innerHTML += `${url}: ${response.status} ${response.statusText}<br>`;
            })
            .catch(error => {
                results.innerHTML += `${url}: ERROR - ${error.message}<br>`;
            });
    });
}

function testAjaxCall() {
    const results = document.getElementById('testResults');
    results.innerHTML = '<strong>Testing AJAX behavior...</strong><br>';
    
    fetch('/tktshop/customer/cart_fixed.php', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/x-www-form-urlencoded',
        },
        body: 'action=test&test=1'
    })
    .then(response => {
        results.innerHTML += `AJAX POST test: ${response.status} ${response.statusText}<br>`;
        results.innerHTML += `Response URL: ${response.url}<br>`;
        return response.text();
    })
    .then(text => {
        results.innerHTML += `Response preview: ${text.substring(0, 200)}...<br>`;
    })
    .catch(error => {
        results.innerHTML += `AJAX ERROR: ${error.message}<br>`;
    });
}

// Auto-run basic test
document.addEventListener('DOMContentLoaded', function() {
    console.log('🔧 Debug page loaded');
    console.log('Current URL:', window.location.href);
    console.log('User Agent:', navigator.userAgent);
});
</script>

<?php
// 8. Summary and recommendations
echo "<h2>8. Summary & Next Steps</h2>";

echo "<div style='background: #e8f4fd; padding: 15px; border: 1px solid #007bff;'>";
echo "<strong>DEBUGGING CHECKLIST:</strong><br>";
echo "1. ✅ Database has 'thue' column<br>";
echo "2. 🔍 Check if .htaccess rules are still problematic<br>";
echo "3. 🔍 Check if files are accessible<br>";
echo "4. 🔍 Check for browser cache issues<br>";
echo "5. 🔍 Check for JavaScript conflicts<br>";
echo "<br>";
echo "<strong>NEXT STEPS:</strong><br>";
echo "1. Click all the test links above<br>";
echo "2. Check browser Developer Tools (F12) → Network tab<br>";
echo "3. Clear browser cache completely<br>";
echo "4. Report back which specific test fails<br>";
echo "</div>";

echo "<h2>9. Quick Actions</h2>";
echo "<div style='background: #f8f8f8; padding: 15px; border: 1px solid #888;'>";
echo "<p><strong>Manual tests you should try:</strong></p>";
echo "<ol>";
echo "<li>Open <a href='/tktshop/customer/products_fixed.php' target='_blank'>/tktshop/customer/products_fixed.php</a> → Should show product list</li>";
echo "<li>Click any 'Xem chi tiết' button → Should go to product_detail.php?id=X or ?slug=X</li>";
echo "<li>Add product to cart → Should work</li>";
echo "<li>Go to <a href='/tktshop/customer/cart_fixed.php' target='_blank'>/tktshop/customer/cart_fixed.php</a> → Should show cart</li>";
echo "<li>Select products and click 'Thanh toán' → Should go to checkout.php</li>";
echo "</ol>";
echo "</div>";
?>