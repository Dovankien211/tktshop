<?php
/**
 * Step by step debug để tìm chính xác vấn đề
 * Save as /tktshop/step_by_step_debug.php
 */

echo "<h1>🔧 STEP BY STEP DEBUG</h1>";

// 1. Test direct file access với timestamp để bypass cache
echo "<h2>1. Direct File Access Test (with cache bypass)</h2>";
$timestamp = time();

$test_urls = [
    "/tktshop/customer/products_fixed.php?t=$timestamp" => 'Products Fixed',
    "/tktshop/customer/cart_fixed.php?t=$timestamp" => 'Cart Fixed', 
    "/tktshop/customer/checkout.php?t=$timestamp" => 'Checkout',
    "/tktshop/customer/product_detail.php?id=7&t=$timestamp" => 'Product Detail ID 7',
    "/tktshop/customer/product_detail.php?slug=taats&t=$timestamp" => 'Product Detail Slug taats'
];

foreach ($test_urls as $url => $name) {
    echo "<div style='padding: 10px; margin: 5px 0; border: 1px solid #007bff; background: #f8f9ff;'>";
    echo "<strong>$name:</strong><br>";
    echo "<a href='$url' target='_blank' style='color: #007bff;'>$url</a><br>";
    echo "<button onclick=\"testUrl('$url', '$name')\" style='background: #007bff; color: white; border: none; padding: 5px 10px; margin-top: 5px; cursor: pointer;'>Test This URL</button>";
    echo "</div>";
}

// 2. Test product data specifically
echo "<h2>2. Product Data Analysis</h2>";

try {
    require_once 'config/database.php';
    
    // Test sản phẩm có vấn đề
    $problem_product = $pdo->query("SELECT * FROM products WHERE id = 7")->fetch();
    
    if ($problem_product) {
        echo "<div style='background: #fff3cd; padding: 15px; border: 1px solid #ffc107;'>";
        echo "<strong>🔍 Problem Product (ID=7) Data:</strong><br>";
        echo "ID: {$problem_product['id']}<br>";
        echo "Name: " . htmlspecialchars($problem_product['name']) . "<br>";
        echo "Slug: " . htmlspecialchars($problem_product['slug'] ?? 'NULL') . "<br>";
        echo "Status: {$problem_product['status']}<br>";
        echo "Stock: {$problem_product['stock_quantity']}<br>";
        echo "Image: " . htmlspecialchars($problem_product['main_image'] ?? 'NULL') . "<br>";
        
        // Test URLs for this product
        echo "<br><strong>Expected URLs:</strong><br>";
        if (!empty($problem_product['slug'])) {
            echo "• product_detail.php?slug=" . urlencode($problem_product['slug']) . "<br>";
        }
        echo "• product_detail.php?id={$problem_product['id']}<br>";
        echo "</div>";
    }
    
    // Test working product
    $working_product = $pdo->query("SELECT * FROM san_pham_chinh WHERE slug = 'adidas-ultraboost-22'")->fetch();
    
    if ($working_product) {
        echo "<div style='background: #e8f5e8; padding: 15px; border: 1px solid #4caf50; margin-top: 10px;'>";
        echo "<strong>✅ Working Product Data:</strong><br>";
        echo "ID: {$working_product['id']}<br>";
        echo "Name: " . htmlspecialchars($working_product['ten_san_pham']) . "<br>";
        echo "Slug: " . htmlspecialchars($working_product['slug']) . "<br>";
        echo "Status: {$working_product['trang_thai']}<br>";
        echo "Image: " . htmlspecialchars($working_product['hinh_anh_chinh'] ?? 'NULL') . "<br>";
        echo "</div>";
    }
    
} catch (Exception $e) {
    echo "<div style='background: #ffe8e8; padding: 10px; border: 1px solid #f44336;'>";
    echo "Database error: " . $e->getMessage();
    echo "</div>";
}

// 3. Test .htaccess status
echo "<h2>3. Current .htaccess Status</h2>";

$htaccess_main = $_SERVER['DOCUMENT_ROOT'] . '/tktshop/.htaccess';
$htaccess_customer = $_SERVER['DOCUMENT_ROOT'] . '/tktshop/customer/.htaccess';

echo "<div style='background: #f8f9fa; padding: 15px; border: 1px solid #333;'>";
echo "<strong>Main .htaccess:</strong> " . (file_exists($htaccess_main) ? '✅ EXISTS' : '❌ MISSING') . "<br>";
echo "<strong>Customer .htaccess:</strong> " . (file_exists($htaccess_customer) ? '✅ EXISTS' : '❌ MISSING') . "<br>";

if (file_exists($htaccess_customer)) {
    $customer_content = file_get_contents($htaccess_customer);
    $has_rewrite = stripos($customer_content, 'RewriteEngine On') !== false;
    $has_rules = stripos($customer_content, 'RewriteRule') !== false;
    
    echo "<strong>Customer .htaccess analysis:</strong><br>";
    echo "• Has RewriteEngine: " . ($has_rewrite ? '⚠️ YES' : '✅ NO') . "<br>";
    echo "• Has RewriteRule: " . ($has_rules ? '⚠️ YES' : '✅ NO') . "<br>";
    
    if ($has_rewrite || $has_rules) {
        echo "<div style='background: #ffe8e8; padding: 10px; margin: 10px 0; border: 1px solid #f44336;'>";
        echo "🚨 STILL HAS PROBLEMATIC RULES!";
        echo "</div>";
    }
}
echo "</div>";

// 4. Test cart functionality specifically
echo "<h2>4. Cart Functionality Test</h2>";

echo "<div style='background: #f0f8ff; padding: 15px; border: 1px solid #007bff;'>";
echo "<strong>Cart Test Steps:</strong><br>";
echo "1. <a href='/tktshop/customer/cart_fixed.php' target='_blank'>Open cart_fixed.php</a><br>";
echo "2. If cart has items, select some products<br>";
echo "3. Click 'Thanh toán' button<br>";
echo "4. Should redirect to checkout.php<br>";
echo "<br>";
echo "<strong>Expected behavior:</strong><br>";
echo "• cart_fixed.php → loads normally ✅<br>";
echo "• Select products → checkbox works ✅<br>";
echo "• Click 'Thanh toán' → redirect to checkout.php ✅<br>";
echo "</div>";

// 5. JavaScript test
echo "<h2>5. JavaScript Functionality Test</h2>";
?>

<div style='background: #f8f8f8; padding: 15px; border: 1px solid #888;'>
    <button onclick="testProductLinks()" style='background: #28a745; color: white; border: none; padding: 10px; cursor: pointer;'>Test Product Links</button>
    <button onclick="testCartFunctionality()" style='background: #dc3545; color: white; border: none; padding: 10px; cursor: pointer; margin-left: 10px;'>Test Cart Functionality</button>
    
    <div id="testResults" style='margin-top: 15px; padding: 10px; background: white; border: 1px solid #ddd; max-height: 300px; overflow-y: auto;'></div>
</div>

<script>
function testUrl(url, name) {
    const results = document.getElementById('testResults');
    results.innerHTML += `<strong>Testing ${name}...</strong><br>`;
    
    fetch(url, { method: 'HEAD' })
        .then(response => {
            const status = response.status;
            const statusText = response.statusText;
            const finalUrl = response.url;
            
            results.innerHTML += `${name}: ${status} ${statusText}<br>`;
            
            if (finalUrl !== window.location.origin + url) {
                results.innerHTML += `⚠️ REDIRECTED TO: ${finalUrl}<br>`;
            }
            results.innerHTML += '<br>';
        })
        .catch(error => {
            results.innerHTML += `${name}: ERROR - ${error.message}<br><br>`;
        });
}

function testProductLinks() {
    const results = document.getElementById('testResults');
    results.innerHTML = '<strong>Testing Product Links...</strong><br>';
    
    // Test specific product URLs
    const productUrls = [
        '/tktshop/customer/product_detail.php?id=7',
        '/tktshop/customer/product_detail.php?slug=taats', 
        '/tktshop/customer/product_detail.php?slug=adidas-ultraboost-22'
    ];
    
    productUrls.forEach(url => {
        fetch(url, { method: 'HEAD' })
            .then(response => {
                results.innerHTML += `${url}: ${response.status} ${response.statusText}<br>`;
                if (response.url !== window.location.origin + url) {
                    results.innerHTML += `⚠️ REDIRECTED TO: ${response.url}<br>`;
                }
            })
            .catch(error => {
                results.innerHTML += `${url}: ERROR - ${error.message}<br>`;
            });
    });
}

function testCartFunctionality() {
    const results = document.getElementById('testResults');
    results.innerHTML = '<strong>Testing Cart Functionality...</strong><br>';
    
    // Test cart AJAX
    fetch('/tktshop/customer/cart_fixed.php', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/x-www-form-urlencoded',
        },
        body: 'action=test_connection'
    })
    .then(response => {
        results.innerHTML += `Cart AJAX test: ${response.status} ${response.statusText}<br>`;
        results.innerHTML += `Response URL: ${response.url}<br>`;
        
        if (response.url.includes('cart.php') && !response.url.includes('cart_fixed.php')) {
            results.innerHTML += '<span style="color: red;">🚨 REDIRECTED TO WRONG FILE!</span><br>';
        }
        
        return response.text();
    })
    .then(text => {
        results.innerHTML += `Response length: ${text.length} characters<br>`;
        if (text.includes('{"success":') || text.includes('<!DOCTYPE')) {
            results.innerHTML += '✅ Response looks valid<br>';
        } else {
            results.innerHTML += '⚠️ Unexpected response format<br>';
        }
    })
    .catch(error => {
        results.innerHTML += `Cart AJAX ERROR: ${error.message}<br>`;
    });
}

// Auto-run basic test
document.addEventListener('DOMContentLoaded', function() {
    const results = document.getElementById('testResults');
    results.innerHTML = '<em>Ready to test. Click buttons above.</em><br>';
    
    console.log('🔧 Debug page ready');
    console.log('Current URL:', window.location.href);
});
</script>

<?php
// 6. Manual test checklist
echo "<h2>6. Manual Test Checklist</h2>";
echo "<div style='background: #e8f4fd; padding: 15px; border: 1px solid #007bff;'>";
echo "<strong>Please test these manually and report results:</strong><br>";
echo "<ol>";
echo "<li>Go to <a href='/tktshop/customer/products_fixed.php' target='_blank'>products_fixed.php</a></li>";
echo "<li>Find product 'taats' (added from admin)</li>";
echo "<li>Click 'Xem chi tiết' button</li>";
echo "<li>Report: Where does it redirect to?</li>";
echo "<li>Go to <a href='/tktshop/customer/cart_fixed.php' target='_blank'>cart_fixed.php</a></li>";
echo "<li>Add some products to cart if empty</li>";
echo "<li>Select products with checkboxes</li>";
echo "<li>Click 'Thanh toán' button</li>";
echo "<li>Report: Where does it redirect to?</li>";
echo "</ol>";
echo "</div>";

echo "<h2>7. Expected vs Actual Results</h2>";
echo "<div style='background: #f8f9fa; padding: 15px; border: 1px solid #333;'>";
echo "<strong>EXPECTED:</strong><br>";
echo "• Click 'Xem chi tiết' on 'taats' → product_detail.php?slug=taats ✅<br>";
echo "• Click 'Thanh toán' in cart → checkout.php ✅<br>";
echo "<br>";
echo "<strong>ACTUAL (please fill in):</strong><br>";
echo "• Click 'Xem chi tiết' on 'taats' → _______________<br>";
echo "• Click 'Thanh toán' in cart → _______________<br>";
echo "</div>";
?>