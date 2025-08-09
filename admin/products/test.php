<?php
echo "<h1>TEST FILE HOẠT ĐỘNG!</h1>";
echo "<p>Đường dẫn: " . __FILE__ . "</p>";
echo "<p>URL hiện tại: " . $_SERVER['REQUEST_URI'] . "</p>";

// Test include
echo "<h3>Test include files:</h3>";
echo "config/database.php: " . (file_exists('../../config/database.php') ? '✅' : '❌') . "<br>";
echo "config/config.php: " . (file_exists('../../config/config.php') ? '✅' : '❌') . "<br>";
echo "layouts/sidebar.php: " . (file_exists('../layouts/sidebar.php') ? '✅' : '❌') . "<br>";

// Test database
try {
    require_once '../../config/database.php';
    echo "Database connection: ✅<br>";
    
    $stmt = $pdo->query("SHOW TABLES");
    echo "Tables found: " . $stmt->rowCount() . "<br>";
} catch (Exception $e) {
    echo "Database error: ❌ " . $e->getMessage() . "<br>";
}
?>