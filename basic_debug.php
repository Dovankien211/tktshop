<?php
// BASIC DEBUG - Tạo file: tktshop/basic_debug.php
echo "PHP is working!<br>";
echo "Current time: " . date('Y-m-d H:i:s') . "<br>";
echo "Current directory: " . getcwd() . "<br>";

// Check files
echo "<h3>File Check:</h3>";
echo "config/config.php: " . (file_exists('config/config.php') ? 'EXISTS' : 'NOT FOUND') . "<br>";
echo "config/database.php: " . (file_exists('config/database.php') ? 'EXISTS' : 'NOT FOUND') . "<br>";
echo "customer/index.php: " . (file_exists('customer/index.php') ? 'EXISTS' : 'NOT FOUND') . "<br>";
echo "customer/products.php: " . (file_exists('customer/products.php') ? 'EXISTS' : 'NOT FOUND') . "<br>";

// Try basic database connection
echo "<h3>Database Test:</h3>";
if (file_exists('config/database.php')) {
    try {
        require_once 'config/database.php';
        echo "database.php loaded<br>";
        
        if (class_exists('Database')) {
            echo "Database class found<br>";
            $pdo = Database::getInstance()->getConnection();
            echo "Connection successful<br>";
            
            $count = $pdo->query("SELECT COUNT(*) FROM products")->fetchColumn();
            echo "Products count: $count<br>";
        }
    } catch (Exception $e) {
        echo "Error: " . $e->getMessage() . "<br>";
    }
} else {
    echo "database.php not found<br>";
}

echo "<h3>Directory Structure:</h3>";
$files = scandir('.');
foreach ($files as $file) {
    if ($file != '.' && $file != '..') {
        echo (is_dir($file) ? '[DIR] ' : '[FILE] ') . $file . "<br>";
    }
}
?>