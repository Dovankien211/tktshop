<?php
/**
 * FILE: test_database.php
 * Mục đích: Test kết nối database và các chức năng cơ bản
 */
?>
<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>TKTShop - Database Test</title>
    <style>
        body { font-family: Arial, sans-serif; margin: 20px; background: #f5f5f5; }
        .container { max-width: 800px; margin: 0 auto; background: white; padding: 30px; border-radius: 10px; }
        .success { color: #28a745; background: #d4edda; padding: 10px; border-radius: 5px; margin: 10px 0; }
        .error { color: #dc3545; background: #f8d7da; padding: 10px; border-radius: 5px; margin: 10px 0; }
        .warning { color: #856404; background: #fff3cd; padding: 10px; border-radius: 5px; margin: 10px 0; }
        .test-item { background: #f8f9fa; padding: 15px; margin: 10px 0; border-left: 4px solid #007bff; }
        table { width: 100%; border-collapse: collapse; margin: 15px 0; }
        th, td { padding: 10px; text-align: left; border-bottom: 1px solid #ddd; }
        th { background: #f8f9fa; }
    </style>
</head>
<body>
    <div class="container">
        <h1>🗄️ TKTShop Database Test</h1>
        
        <?php
        // Include database config
        try {
            include_once 'config/database.php';
            echo "<div class='success'>✅ File database.php loaded successfully</div>";
        } catch (Exception $e) {
            echo "<div class='error'>❌ Error loading database.php: " . $e->getMessage() . "</div>";
            exit();
        }

        // Test database connection
        echo "<div class='test-item'>";
        echo "<h3>1. 🔗 Test Database Connection</h3>";
        
        try {
            // Giả sử biến $pdo đã được tạo trong database.php
            if (isset($pdo)) {
                $stmt = $pdo->query("SELECT VERSION() as version");
                $version = $stmt->fetch(PDO::FETCH_ASSOC);
                echo "<div class='success'>✅ Connected to MySQL " . $version['version'] . "</div>";
            } else {
                echo "<div class='error'>❌ PDO object not found in database.php</div>";
            }
        } catch (PDOException $e) {
            echo "<div class='error'>❌ Database connection failed: " . $e->getMessage() . "</div>";
        }
        echo "</div>";

        // Test tables existence
        echo "<div class='test-item'>";
        echo "<h3>2. 📊 Check Database Tables</h3>";
        
        $expectedTables = [
            'users', 'categories', 'products', 'colors', 'sizes', 
            'product_variants', 'orders', 'order_items', 'reviews', 'cart'
        ];
        
        try {
            $stmt = $pdo->query("SHOW TABLES");
            $tables = $stmt->fetchAll(PDO::FETCH_COLUMN);
            
            echo "<table>";
            echo "<thead><tr><th>Table Name</th><th>Status</th><th>Record Count</th></tr></thead>";
            echo "<tbody>";
            
            foreach ($expectedTables as $table) {
                if (in_array($table, $tables)) {
                    try {
                        $countStmt = $pdo->query("SELECT COUNT(*) FROM `$table`");
                        $count = $countStmt->fetchColumn();
                        echo "<tr><td>$table</td><td><span style='color: green;'>✅ Exists</span></td><td>$count records</td></tr>";
                    } catch (Exception $e) {
                        echo "<tr><td>$table</td><td><span style='color: orange;'>⚠️ Exists but error</span></td><td>Error: " . $e->getMessage() . "</td></tr>";
                    }
                } else {
                    echo "<tr><td>$table</td><td><span style='color: red;'>❌ Missing</span></td><td>-</td></tr>";
                }
            }
            
            echo "</tbody></table>";
            
        } catch (Exception $e) {
            echo "<div class='error'>❌ Error checking tables: " . $e->getMessage() . "</div>";
        }
        echo "</div>";

        // Test sample data
        echo "<div class='test-item'>";
        echo "<h3>3. 🎯 Test Sample Queries</h3>";
        
        $testQueries = [
            'Categories' => 'SELECT COUNT(*) as count FROM categories',
            'Products' => 'SELECT COUNT(*) as count FROM products', 
            'Users' => 'SELECT COUNT(*) as count FROM users',
            'Orders' => 'SELECT COUNT(*) as count FROM orders'
        ];
        
        foreach ($testQueries as $name => $query) {
            try {
                $stmt = $pdo->query($query);
                $result = $stmt->fetch(PDO::FETCH_ASSOC);
                echo "<div class='success'>✅ $name: " . $result['count'] . " records</div>";
            } catch (Exception $e) {
                echo "<div class='error'>❌ $name query failed: " . $e->getMessage() . "</div>";
            }
        }
        echo "</div>";

        // Test config values
        echo "<div class='test-item'>";
        echo "<h3>4. ⚙️ Configuration Check</h3>";
        
        $configItems = [
            'PHP Version' => PHP_VERSION,
            'PDO MySQL Extension' => extension_loaded('pdo_mysql') ? 'Enabled' : 'Disabled',
            'Upload Max Filesize' => ini_get('upload_max_filesize'),
            'Post Max Size' => ini_get('post_max_size'),
            'Memory Limit' => ini_get('memory_limit'),
            'Default Timezone' => date_default_timezone_get()
        ];
        
        echo "<table>";
        foreach ($configItems as $key => $value) {
            $status = ($key === 'PDO MySQL Extension' && $value === 'Disabled') ? 'error' : 'success';
            echo "<tr><td><strong>$key</strong></td><td class='$status'>$value</td></tr>";
        }
        echo "</table>";
        echo "</div>";

        // Test file uploads directory
        echo "<div class='test-item'>";
        echo "<h3>5. 📁 File Upload Test</h3>";
        
        $uploadDirs = ['uploads/', 'uploads/products/', 'uploads/categories/'];
        
        foreach ($uploadDirs as $dir) {
            if (is_dir($dir)) {
                $writable = is_writable($dir) ? 'Writable' : 'Not Writable';
                $status = is_writable($dir) ? 'success' : 'error';
                echo "<div class='$status'>📁 $dir - $writable</div>";
            } else {
                echo "<div class='error'>📁 $dir - Directory not found</div>";
            }
        }
        echo "</div>";

        // Test session
        echo "<div class='test-item'>";
        echo "<h3>6. 🔐 Session Test</h3>";
        
        if (session_status() === PHP_SESSION_ACTIVE) {
            echo "<div class='success'>✅ Session is active</div>";
            echo "<div>Session ID: " . session_id() . "</div>";
        } else {
            session_start();
            if (session_status() === PHP_SESSION_ACTIVE) {
                echo "<div class='success'>✅ Session started successfully</div>";
                echo "<div>Session ID: " . session_id() . "</div>";
            } else {
                echo "<div class='error'>❌ Failed to start session</div>";
            }
        }
        echo "</div>";
        ?>
        
        <div style="text-align: center; margin-top: 30px; color: #666;">
            <p>Test completed at <?php echo date('d/m/Y H:i:s'); ?></p>
            <p><a href="customer/index.php" style="color: #007bff;">🏠 Go to Homepage</a> | 
               <a href="admin/colors/index.php" style="color: #007bff;">🔧 Go to Admin</a></p>
        </div>
    </div>
</body>
</html>