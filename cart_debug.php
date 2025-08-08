<?php
/**
 * CART DEBUG TOOL
 * Debug chức năng giỏ hàng và add to cart
 */

session_start();
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Include database
if (file_exists('config/database.php')) {
    require_once 'config/database.php';
}

echo "<!DOCTYPE html>
<html lang='vi'>
<head>
    <meta charset='UTF-8'>
    <meta name='viewport' content='width=device-width, initial-scale=1.0'>
    <title>Cart Debug Tool - TKTShop</title>
    <style>
        body { font-family: Arial, sans-serif; margin: 20px; background: #f5f5f5; }
        .container { max-width: 1000px; margin: 0 auto; }
        .header { background: #e74c3c; color: white; padding: 20px; border-radius: 10px; margin-bottom: 20px; text-align: center; }
        .section { background: white; margin: 20px 0; border-radius: 10px; padding: 20px; box-shadow: 0 2px 10px rgba(0,0,0,0.1); }
        .success { background: #d4edda; color: #155724; border: 1px solid #c3e6cb; padding: 10px; border-radius: 5px; margin: 10px 0; }
        .error { background: #f8d7da; color: #721c24; border: 1px solid #f1aeb5; padding: 10px; border-radius: 5px; margin: 10px 0; }
        .warning { background: #fff3cd; color: #856404; border: 1px solid #ffeaa7; padding: 10px; border-radius: 5px; margin: 10px 0; }
        .info { background: #d1ecf1; color: #0c5460; border: 1px solid #bee5eb; padding: 10px; border-radius: 5px; margin: 10px 0; }
        table { width: 100%; border-collapse: collapse; margin: 15px 0; }
        th, td { padding: 12px; text-align: left; border-bottom: 1px solid #ddd; }
        th { background: #f8f9fa; }
        .btn { display: inline-block; padding: 10px 20px; background: #007bff; color: white; text-decoration: none; border-radius: 5px; margin: 5px; border: none; cursor: pointer; }
        .btn:hover { background: #0056b3; }
        .btn-success { background: #28a745; }
        .btn-danger { background: #dc3545; }
        .code-block { background: #2d3748; color: #e2e8f0; padding: 15px; border-radius: 8px; font-family: monospace; margin: 10px 0; overflow-x: auto; }
    </style>
</head>
<body>";

echo "<div class='container'>";
echo "<div class='header'>";
echo "<h1>🛒 Cart Debug Tool</h1>";
echo "<p>Debug chức năng giỏ hàng và add to cart của TKTShop</p>";
echo "</div>";

class CartDebugger {
    private $pdo;
    
    public function __construct($pdo = null) {
        $this->pdo = $pdo;
    }
    
    // Kiểm tra session
    public function checkSession() {
        echo "<div class='section'>";
        echo "<h3>🔐 Session Check</h3>";
        
        if (session_status() === PHP_SESSION_ACTIVE) {
            echo "<div class='success'>✅ Session đang hoạt động</div>";
            echo "<p><strong>Session ID:</strong> " . session_id() . "</p>";
            
            // Kiểm tra cart trong session
            if (isset($_SESSION['cart'])) {
                echo "<div class='success'>✅ Cart session tồn tại</div>";
                echo "<p><strong>Items trong cart:</strong> " . count($_SESSION['cart']) . "</p>";
                
                if (!empty($_SESSION['cart'])) {
                    echo "<h4>📋 Cart Contents:</h4>";
                    echo "<table>";
                    echo "<thead><tr><th>Product ID</th><th>Quantity</th><th>Size</th><th>Color</th></tr></thead>";
                    echo "<tbody>";
                    foreach ($_SESSION['cart'] as $item) {
                        echo "<tr>";
                        echo "<td>" . ($item['product_id'] ?? 'N/A') . "</td>";
                        echo "<td>" . ($item['quantity'] ?? 'N/A') . "</td>";
                        echo "<td>" . ($item['size'] ?? 'N/A') . "</td>";
                        echo "<td>" . ($item['color'] ?? 'N/A') . "</td>";
                        echo "</tr>";
                    }
                    echo "</tbody></table>";
                } else {
                    echo "<div class='info'>ℹ️ Cart trống</div>";
                }
            } else {
                echo "<div class='warning'>⚠️ Cart session chưa được khởi tạo</div>";
            }
            
            // Hiển thị all session data
            echo "<h4>🗃️ All Session Data:</h4>";
            echo "<div class='code-block'>";
            echo htmlspecialchars(print_r($_SESSION, true));
            echo "</div>";
            
        } else {
            echo "<div class='error'>❌ Session không hoạt động</div>";
            echo "<p>Session status: " . session_status() . "</p>";
        }
        
        echo "</div>";
    }
    
    // Kiểm tra database
    public function checkDatabase() {
        echo "<div class='section'>";
        echo "<h3>🗄️ Database Check</h3>";
        
        if ($this->pdo) {
            echo "<div class='success'>✅ Database connection OK</div>";
            
            // Kiểm tra các bảng cần thiết cho cart
            $tables = ['products', 'colors', 'sizes', 'product_variants'];
            foreach ($tables as $table) {
                try {
                    $stmt = $this->pdo->query("SELECT COUNT(*) FROM `{$table}`");
                    $count = $stmt->fetchColumn();
                    echo "<div class='success'>✅ Table '{$table}': {$count} records</div>";
                } catch (Exception $e) {
                    echo "<div class='error'>❌ Table '{$table}': " . $e->getMessage() . "</div>";
                }
            }
            
            // Test query sản phẩm
            try {
                $stmt = $this->pdo->query("
                    SELECT p.*, c.name as category_name 
                    FROM products p 
                    LEFT JOIN categories c ON p.category_id = c.id 
                    LIMIT 5
                ");
                $products = $stmt->fetchAll();
                
                echo "<h4>📦 Sample Products:</h4>";
                if ($products) {
                    echo "<table>";
                    echo "<thead><tr><th>ID</th><th>Name</th><th>Price</th><th>Category</th><th>Status</th></tr></thead>";
                    echo "<tbody>";
                    foreach ($products as $product) {
                        echo "<tr>";
                        echo "<td>{$product['id']}</td>";
                        echo "<td>{$product['name']}</td>";
                        echo "<td>" . number_format($product['price']) . " VNĐ</td>";
                        echo "<td>{$product['category_name']}</td>";
                        echo "<td>" . ($product['status'] ? 'Active' : 'Inactive') . "</td>";
                        echo "</tr>";
                    }
                    echo "</tbody></table>";
                } else {
                    echo "<div class='warning'>⚠️ Không có sản phẩm nào trong database</div>";
                }
            } catch (Exception $e) {
                echo "<div class='error'>❌ Lỗi query products: " . $e->getMessage() . "</div>";
            }
            
        } else {
            echo "<div class='error'>❌ Không có kết nối database</div>";
        }
        
        echo "</div>";
    }
    
    // Kiểm tra cart files
    public function checkCartFiles() {
        echo "<div class='section'>";
        echo "<h3>📄 Cart Files Check</h3>";
        
        $cartFiles = [
            'customer/cart.php' => 'Trang giỏ hàng chính',
            'customer/add_to_cart.php' => 'Xử lý thêm vào giỏ hàng',
            'customer/update_cart.php' => 'Cập nhật số lượng',
            'customer/remove_from_cart.php' => 'Xóa khỏi giỏ hàng',
            'customer/checkout.php' => 'Trang thanh toán'
        ];
        
        foreach ($cartFiles as $file => $description) {
            if (file_exists($file)) {
                $size = filesize($file);
                if ($size > 0) {
                    echo "<div class='success'>✅ {$file} - {$description} (Size: " . $this->formatBytes($size) . ")</div>";
                } else {
                    echo "<div class='warning'>⚠️ {$file} - File trống</div>";
                }
            } else {
                echo "<div class='error'>❌ {$file} - File không tồn tại</div>";
            }
        }
        
        echo "</div>";
    }
    
    // Test AJAX endpoints
    public function testAjaxEndpoints() {
        echo "<div class='section'>";
        echo "<h3>⚡ AJAX Endpoints Test</h3>";
        
        echo "<div class='info'>ℹ️ Test các AJAX endpoints cho cart functionality</div>";
        
        // Test add to cart
        echo "<h4>🛒 Test Add to Cart:</h4>";
        echo "<form method='POST' action='test_add_to_cart.php' target='_blank'>";
        echo "<table>";
        echo "<tr><th>Field</th><th>Value</th></tr>";
        echo "<tr><td>Product ID</td><td><input type='number' name='product_id' value='1' style='width:100px;'></td></tr>";
        echo "<tr><td>Quantity</td><td><input type='number' name='quantity' value='1' style='width:100px;'></td></tr>";
        echo "<tr><td>Size ID</td><td><input type='number' name='size_id' value='1' style='width:100px;'></td></tr>";
        echo "<tr><td>Color ID</td><td><input type='number' name='color_id' value='1' style='width:100px;'></td></tr>";
        echo "</table>";
        echo "<button type='submit' class='btn'>Test Add to Cart</button>";
        echo "</form>";
        
        // Test URLs
        echo "<h4>🔗 Test Cart URLs:</h4>";
        $cartUrls = [
            '/tktshop/customer/cart.php' => 'View Cart',
            '/tktshop/customer/products.php' => 'Products Page',
            '/tktshop/customer/checkout.php' => 'Checkout Page',
            '/tktshop/customer/orders.php' => 'Orders Page'
        ];
        
        foreach ($cartUrls as $url => $label) {
            echo "<a href='{$url}' target='_blank' class='btn'>{$label}</a>";
        }
        
        echo "</div>";
    }
    
    // Generate test add to cart script
    public function generateTestScript() {
        echo "<div class='section'>";
        echo "<h3>🧪 Test Add to Cart Script</h3>";
        
        echo "<div class='code-block'>";
        echo htmlspecialchars("
// JavaScript test cho Add to Cart
function testAddToCart() {
    const data = {
        product_id: 1,
        quantity: 1,
        size_id: 1,
        color_id: 1
    };
    
    fetch('/tktshop/customer/add_to_cart.php', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
            'X-Requested-With': 'XMLHttpRequest'
        },
        body: JSON.stringify(data)
    })
    .then(response => response.json())
    .then(data => {
        console.log('Add to cart result:', data);
        if (data.success) {
            alert('Thêm vào giỏ hàng thành công!');
        } else {
            alert('Lỗi: ' + data.message);
        }
    })
    .catch(error => {
        console.error('Error:', error);
        alert('Lỗi kết nối AJAX');
    });
}

// Test trên console browser
console.log('Run testAddToCart() to test');
        ");
        echo "</div>";
        
        echo "<button onclick='testAddToCart()' class='btn btn-success'>Run Test</button>";
        
        echo "<script>";
        echo "function testAddToCart() {
            const data = {
                product_id: 1,
                quantity: 1,
                size_id: 1,
                color_id: 1
            };
            
            fetch('/tktshop/customer/add_to_cart.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-Requested-With': 'XMLHttpRequest'
                },
                body: JSON.stringify(data)
            })
            .then(response => response.text())
            .then(data => {
                console.log('Add to cart result:', data);
                alert('Check console for result');
            })
            .catch(error => {
                console.error('Error:', error);
                alert('Error: ' + error.message);
            });
        }";
        echo "</script>";
        
        echo "</div>";
    }
    
    // Đề xuất fixes
    public function showRecommendations() {
        echo "<div class='section'>";
        echo "<h3>💡 Recommendations</h3>";
        
        echo "<h4>🔧 Common Issues & Fixes:</h4>";
        echo "<div class='warning'>";
        echo "<h5>1. URL Orders Issue:</h5>";
        echo "<p><strong>Problem:</strong> URL missing /tktshop/</p>";
        echo "<p><strong>Fix:</strong> Find and replace in navigation files:</p>";
        echo "<div class='code-block'>❌ href=\"/customer/orders.php\"<br>✅ href=\"/tktshop/customer/orders.php\"</div>";
        echo "</div>";
        
        echo "<div class='warning'>";
        echo "<h5>2. Add to Cart Not Working:</h5>";
        echo "<p><strong>Possible causes:</strong></p>";
        echo "<ul>";
        echo "<li>Missing add_to_cart.php file</li>";
        echo "<li>JavaScript errors</li>";
        echo "<li>AJAX endpoint wrong</li>";
        echo "<li>Session not working</li>";
        echo "<li>Database connection failed</li>";
        echo "</ul>";
        echo "</div>";
        
        echo "<h4>🚀 Next Steps:</h4>";
        echo "<ol>";
        echo "<li>Fix URL issues in navigation</li>";
        echo "<li>Create missing cart files</li>";
        echo "<li>Test AJAX functionality</li>";
        echo "<li>Verify database connections</li>";
        echo "<li>Test end-to-end shopping flow</li>";
        echo "</ol>";
        
        echo "</div>";
    }
    
    private function formatBytes($size, $precision = 2) {
        $units = ['B', 'KB', 'MB', 'GB'];
        for ($i = 0; $size >= 1024 && $i < count($units) - 1; $i++) {
            $size /= 1024;
        }
        return round($size, $precision) . ' ' . $units[$i];
    }
}

// Run debugger
$debugger = new CartDebugger($pdo ?? null);
$debugger->checkSession();
$debugger->checkDatabase();
$debugger->checkCartFiles();
$debugger->testAjaxEndpoints();
$debugger->generateTestScript();
$debugger->showRecommendations();

echo "<div style='text-align: center; margin: 30px 0; padding: 20px; background: #f8f9fa; border-radius: 10px;'>";
echo "<h3>🔗 Quick Links</h3>";
echo "<a href='/tktshop/' class='btn'>🏠 Homepage</a>";
echo "<a href='/tktshop/customer/cart.php' class='btn btn-success'>🛒 View Cart</a>";
echo "<a href='/tktshop/customer/products.php' class='btn'>🛍️ Products</a>";
echo "<a href='/tktshop/quick_test.php' class='btn'>⚡ Quick Test</a>";
echo "<p style='color: #666; margin-top: 20px;'>Cart Debug completed at " . date('d/m/Y H:i:s') . "</p>";
echo "</div>";

echo "</div>"; // Close container
echo "</body></html>";
?>