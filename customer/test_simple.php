<?php
// File: customer/test_simple.php
// Test cơ bản để kiểm tra từng phần

echo "<!DOCTYPE html>
<html>
<head>
    <title>Simple Test</title>
    <style>
        body { font-family: Arial; margin: 20px; }
        .ok { color: green; }
        .error { color: red; }
        .test { margin: 10px 0; padding: 10px; border: 1px solid #ddd; }
    </style>
</head>
<body>
    <h1>🧪 Simple Test</h1>";

// Test 1: Include config
echo "<div class='test'>";
echo "<h3>Test 1: Include Config</h3>";
try {
    require_once '../config/config.php';
    echo "<div class='ok'>✅ Config loaded successfully</div>";
    echo "<div>BASE_URL: " . BASE_URL . "</div>";
} catch (Exception $e) {
    echo "<div class='error'>❌ Config error: " . $e->getMessage() . "</div>";
}
echo "</div>";

// Test 2: Include database
echo "<div class='test'>";
echo "<h3>Test 2: Database Connection</h3>";
try {
    require_once '../config/database.php';
    echo "<div class='ok'>✅ Database loaded successfully</div>";
    
    // Test query
    if (isset($pdo)) {
        $stmt = $pdo->query("SELECT COUNT(*) as count FROM products LIMIT 1");
        $result = $stmt->fetch();
        echo "<div class='ok'>✅ Database query works - Products table accessible</div>";
    }
} catch (Exception $e) {
    echo "<div class='error'>❌ Database error: " . $e->getMessage() . "</div>";
}
echo "</div>";

// Test 3: Include header
echo "<div class='test'>";
echo "<h3>Test 3: Include Header</h3>";
try {
    ob_start();
    include 'includes/header.php';
    $header_content = ob_get_clean();
    echo "<div class='ok'>✅ Header included successfully</div>";
    echo "<div>Header length: " . strlen($header_content) . " characters</div>";
} catch (Exception $e) {
    echo "<div class='error'>❌ Header error: " . $e->getMessage() . "</div>";
}
echo "</div>";

// Test 4: Session
echo "<div class='test'>";
echo "<h3>Test 4: Session</h3>";
if (session_status() === PHP_SESSION_ACTIVE) {
    echo "<div class='ok'>✅ Session is active</div>";
    echo "<div>Session ID: " . session_id() . "</div>";
} else {
    echo "<div class='error'>❌ Session not active</div>";
}
echo "</div>";

// Test 5: File permissions
echo "<div class='test'>";
echo "<h3>Test 5: File Permissions</h3>";
$upload_path = '../uploads/products/';
if (is_writable($upload_path)) {
    echo "<div class='ok'>✅ Upload directory is writable</div>";
} else {
    echo "<div class='error'>❌ Upload directory not writable</div>";
}
echo "</div>";

// Test 6: Navigation links
echo "<div class='test'>";
echo "<h3>Test 6: Navigation Links</h3>";
$links = [
    'index.php' => 'Home',
    'products.php' => 'Products', 
    'cart.php' => 'Cart',
    'login.php' => 'Login'
];

foreach ($links as $file => $name) {
    if (file_exists($file)) {
        echo "<div class='ok'>✅ <a href='$file'>$name</a> - File exists</div>";
    } else {
        echo "<div class='error'>❌ $name - File missing</div>";
    }
}
echo "</div>";

echo "<h2>🎯 Conclusion:</h2>";
echo "<p>If all tests show ✅, your system is working correctly!</p>";
echo "<p><a href='index.php'>← Back to Homepage</a></p>";

echo "</body></html>";
?>