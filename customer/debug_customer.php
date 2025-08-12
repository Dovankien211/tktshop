<?php
/**
 * Customer Debug Tool - Đặt file này trong thư mục customer/
 * File: customer/debug_customer.php
 */

echo "<!DOCTYPE html>
<html lang='vi'>
<head>
    <meta charset='UTF-8'>
    <meta name='viewport' content='width=device-width, initial-scale=1.0'>
    <title>Customer Debug Tool</title>
    <style>
        body { font-family: Arial, sans-serif; margin: 20px; background: #f5f5f5; }
        .container { background: white; padding: 20px; border-radius: 8px; box-shadow: 0 2px 10px rgba(0,0,0,0.1); }
        .success { color: #28a745; background: #d4edda; padding: 10px; border-radius: 4px; margin: 5px 0; }
        .error { color: #dc3545; background: #f8d7da; padding: 10px; border-radius: 4px; margin: 5px 0; }
        .warning { color: #856404; background: #fff3cd; padding: 10px; border-radius: 4px; margin: 5px 0; }
        .info { color: #0c5460; background: #d1ecf1; padding: 10px; border-radius: 4px; margin: 5px 0; }
        .code { background: #2d3748; color: #e2e8f0; padding: 15px; border-radius: 4px; font-family: monospace; margin: 10px 0; }
        h1 { color: #333; }
        h2 { color: #666; border-bottom: 2px solid #007bff; padding-bottom: 5px; }
        .file-path { background: #007bff; color: white; padding: 3px 8px; border-radius: 3px; font-family: monospace; }
    </style>
</head>
<body>
    <div class='container'>
        <h1>🔍 Customer Debug Tool</h1>
        <p>Đang kiểm tra thư mục: <strong>" . __DIR__ . "</strong></p>
        <hr>";

// 1. Kiểm tra đường dẫn config
echo "<h2>1. 📁 Kiểm tra Config Files</h2>";

$config_path = '../config/config.php';
if (file_exists($config_path)) {
    echo "<div class='success'>✅ Config file tồn tại: <span class='file-path'>$config_path</span></div>";
    
    // Thử include config
    try {
        ob_start();
        require_once $config_path;
        ob_end_clean();
        
        if (defined('BASE_URL')) {
            echo "<div class='success'>✅ BASE_URL đã được định nghĩa: <strong>" . BASE_URL . "</strong></div>";
        } else {
            echo "<div class='error'>❌ BASE_URL chưa được định nghĩa trong config</div>";
        }
        
        if (defined('BASE_PATH')) {
            echo "<div class='success'>✅ BASE_PATH: <strong>" . BASE_PATH . "</strong></div>";
        }
        
    } catch (Exception $e) {
        echo "<div class='error'>❌ Lỗi khi load config: " . $e->getMessage() . "</div>";
    }
} else {
    echo "<div class='error'>❌ Config file không tồn tại: <span class='file-path'>$config_path</span></div>";
    echo "<div class='warning'>🔧 Tạo file config.php trong thư mục config/ với nội dung:</div>";
    echo "<div class='code'>&lt;?php
define('BASE_PATH', dirname(__FILE__) . '/../');
define('BASE_URL', 'http://localhost/tktshop');
define('UPLOAD_URL', BASE_URL . '/uploads');
session_start();
?&gt;</div>";
}

// 2. Kiểm tra database
echo "<h2>2. 🗃️ Kiểm tra Database</h2>";

$db_path = '../config/database.php';
if (file_exists($db_path)) {
    echo "<div class='success'>✅ Database file tồn tại: <span class='file-path'>$db_path</span></div>";
    
    try {
        ob_start();
        require_once $db_path;
        ob_end_clean();
        
        // Kiểm tra connection
        if (isset($pdo)) {
            echo "<div class='success'>✅ Database connection thành công</div>";
        } elseif (isset($conn)) {
            echo "<div class='success'>✅ Database connection thành công (mysqli)</div>";
        } else {
            echo "<div class='warning'>⚠️ Không tìm thấy biến connection (\$pdo hoặc \$conn)</div>";
        }
        
    } catch (Exception $e) {
        echo "<div class='error'>❌ Lỗi database: " . $e->getMessage() . "</div>";
    }
} else {
    echo "<div class='error'>❌ Database file không tồn tại: <span class='file-path'>$db_path</span></div>";
}

// 3. Kiểm tra includes
echo "<h2>3. 📂 Kiểm tra Includes</h2>";

$includes_files = [
    'includes/header.php',
    'includes/footer.php',
    'includes/helpers.php'
];

foreach ($includes_files as $file) {
    if (file_exists($file)) {
        echo "<div class='success'>✅ $file tồn tại</div>";
    } else {
        echo "<div class='error'>❌ $file không tồn tại</div>";
    }
}

// 4. Kiểm tra các trang chính
echo "<h2>4. 📄 Kiểm tra Trang Customer</h2>";

$customer_pages = [
    'index.php' => 'Trang chủ',
    'products.php' => 'Danh sách sản phẩm', 
    'product_detail.php' => 'Chi tiết sản phẩm',
    'cart.php' => 'Giỏ hàng',
    'checkout.php' => 'Thanh toán',
    'login.php' => 'Đăng nhập',
    'register.php' => 'Đăng ký'
];

foreach ($customer_pages as $file => $name) {
    if (file_exists($file)) {
        echo "<div class='success'>✅ $file ($name) tồn tại</div>";
        
        // Kiểm tra syntax error
        $check = shell_exec("php -l $file 2>&1");
        if (strpos($check, 'No syntax errors') !== false) {
            echo "<div class='info'>  └── Syntax OK</div>";
        } else {
            echo "<div class='error'>  └── Syntax Error: " . strip_tags($check) . "</div>";
        }
    } else {
        echo "<div class='warning'>⚠️ $file ($name) không tồn tại</div>";
    }
}

// 5. Kiểm tra thư mục uploads
echo "<h2>5. 📁 Kiểm tra Uploads</h2>";

$upload_dirs = [
    '../uploads/' => 'Thư mục uploads chính',
    '../uploads/products/' => 'Ảnh sản phẩm',
    '../uploads/categories/' => 'Ảnh danh mục',
    '../uploads/users/' => 'Avatar người dùng'
];

foreach ($upload_dirs as $dir => $name) {
    if (is_dir($dir)) {
        $is_writable = is_writable($dir);
        if ($is_writable) {
            echo "<div class='success'>✅ $name tồn tại và có thể ghi</div>";
        } else {
            echo "<div class='warning'>⚠️ $name tồn tại nhưng không có quyền ghi</div>";
        }
    } else {
        echo "<div class='error'>❌ $name không tồn tại</div>";
        echo "<div class='info'>  └── Tạo thư mục: mkdir('$dir', 0755, true);</div>";
    }
}

// 6. Kiểm tra URL và đường dẫn
echo "<h2>6. 🌐 Kiểm tra URLs</h2>";

$current_url = 'http://' . $_SERVER['HTTP_HOST'] . $_SERVER['REQUEST_URI'];
echo "<div class='info'>📍 URL hiện tại: <strong>$current_url</strong></div>";

$document_root = $_SERVER['DOCUMENT_ROOT'];
echo "<div class='info'>📁 Document Root: <strong>$document_root</strong></div>";

$script_path = dirname($_SERVER['SCRIPT_FILENAME']);
echo "<div class='info'>📄 Script Path: <strong>$script_path</strong></div>";

// 7. Test các link quan trọng
echo "<h2>7. 🔗 Test Links</h2>";

$base_url = defined('BASE_URL') ? BASE_URL : 'http://' . $_SERVER['HTTP_HOST'] . '/tktshop';

$test_links = [
    $base_url . '/customer/index.php' => 'Trang chủ customer',
    $base_url . '/customer/products.php' => 'Danh sách sản phẩm',
    $base_url . '/admin/dashboard.php' => 'Admin dashboard',
    $base_url . '/uploads/products/' => 'Thư mục ảnh sản phẩm'
];

foreach ($test_links as $url => $name) {
    echo "<div class='info'>🔗 <a href='$url' target='_blank'>$name</a> - $url</div>";
}

// 8. Gợi ý sửa lỗi
echo "<h2>8. 🔧 Gợi ý sửa lỗi</h2>";

echo "<div class='warning'>
<h3>Các bước sửa lỗi thường gặp:</h3>
<ol>
<li><strong>Nếu lỗi config:</strong> Kiểm tra đường dẫn trong require_once</li>
<li><strong>Nếu lỗi 404:</strong> Kiểm tra BASE_URL trong config.php</li>
<li><strong>Nếu lỗi database:</strong> Kiểm tra thông tin DB trong database.php</li>
<li><strong>Nếu lỗi include:</strong> Sử dụng đường dẫn tương đối '../'</li>
</ol>
</div>";

echo "<div class='code'>// Fix include paths trong customer files:
require_once '../config/config.php';
require_once '../config/database.php';

// Fix trong admin files:  
require_once '../../config/config.php';
require_once '../../config/database.php';</div>";

echo "<div class='info'>
<h3>🎯 Next Steps:</h3>
<ul>
<li>Copy các đoạn code fix ở trên</li>
<li>Thay thế trong các file tương ứng</li>
<li>Refresh lại trang debug này để kiểm tra</li>
<li>Test từng trang customer một</li>
</ul>
</div>";

echo "</div></body></html>";
?>