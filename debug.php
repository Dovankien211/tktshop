<?php
/**
 * TKTShop Debug Tool - Kiểm tra toàn diện dự án
 * Phiên bản: 2.0
 * Tác giả: Debug Assistant
 * Ngày tạo: 2025
 */

// Bật hiển thị lỗi
error_reporting(E_ALL);
ini_set('display_errors', 1);

// CSS cho giao diện đẹp
echo "<!DOCTYPE html>
<html lang='vi'>
<head>
    <meta charset='UTF-8'>
    <meta name='viewport' content='width=device-width, initial-scale=1.0'>
    <title>TKTShop Debug Tool</title>
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif; background: #f5f7fa; color: #333; line-height: 1.6; }
        .container { max-width: 1200px; margin: 0 auto; padding: 20px; }
        .header { background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); color: white; padding: 30px; border-radius: 15px; margin-bottom: 30px; text-align: center; box-shadow: 0 10px 30px rgba(0,0,0,0.1); }
        .header h1 { font-size: 2.5em; margin-bottom: 10px; }
        .header p { font-size: 1.2em; opacity: 0.9; }
        .section { background: white; margin: 20px 0; border-radius: 12px; overflow: hidden; box-shadow: 0 5px 15px rgba(0,0,0,0.08); }
        .section-title { background: #4a5568; color: white; padding: 15px 25px; font-size: 1.3em; font-weight: 600; }
        .section-content { padding: 25px; }
        .status { display: inline-block; padding: 8px 15px; border-radius: 25px; font-weight: 600; font-size: 0.9em; margin: 3px; }
        .status.success { background: #d4edda; color: #155724; border: 1px solid #c3e6cb; }
        .status.error { background: #f8d7da; color: #721c24; border: 1px solid #f1aeb5; }
        .status.warning { background: #fff3cd; color: #856404; border: 1px solid #ffeaa7; }
        .status.info { background: #d1ecf1; color: #0c5460; border: 1px solid #bee5eb; }
        .file-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(300px, 1fr)); gap: 15px; margin: 15px 0; }
        .file-item { background: #f8f9fa; border: 1px solid #e9ecef; border-radius: 8px; padding: 15px; }
        .file-item.exists { border-left: 4px solid #28a745; }
        .file-item.missing { border-left: 4px solid #dc3545; }
        .file-item.incomplete { border-left: 4px solid #ffc107; }
        .file-path { font-family: 'Courier New', monospace; font-size: 0.9em; color: #495057; margin-bottom: 8px; }
        .file-info { font-size: 0.85em; color: #6c757d; }
        .summary { display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 20px; margin: 20px 0; }
        .summary-item { background: white; padding: 20px; border-radius: 10px; text-align: center; box-shadow: 0 3px 10px rgba(0,0,0,0.1); }
        .summary-number { font-size: 2.5em; font-weight: bold; margin-bottom: 10px; }
        .summary-label { color: #6c757d; font-size: 0.9em; }
        .recommendations { background: #e3f2fd; border-left: 4px solid #2196f3; padding: 20px; margin: 20px 0; border-radius: 5px; }
        .permissions-table { width: 100%; border-collapse: collapse; margin: 15px 0; }
        .permissions-table th, .permissions-table td { padding: 12px; text-align: left; border-bottom: 1px solid #ddd; }
        .permissions-table th { background: #f8f9fa; font-weight: 600; }
        .progress-bar { background: #e9ecef; border-radius: 10px; overflow: hidden; margin: 10px 0; }
        .progress-fill { height: 8px; background: linear-gradient(90deg, #28a745, #20c997); transition: width 0.3s ease; }
    </style>
</head>
<body>";

class TKTShopDebugger {
    private $baseDir;
    private $results = [];
    private $summary = [
        'total_files' => 0,
        'existing_files' => 0,
        'missing_files' => 0,
        'incomplete_files' => 0,
        'directories' => 0,
        'permissions_ok' => 0,
        'permissions_error' => 0
    ];

    public function __construct() {
        $this->baseDir = dirname(__FILE__);
        echo "<div class='container'>";
        echo "<div class='header'>";
        echo "<h1>🔍 TKTShop Debug Tool</h1>";
        echo "<p>Kiểm tra toàn diện cấu trúc dự án và phát hiện lỗi</p>";
        echo "<p><strong>Thư mục gốc:</strong> " . $this->baseDir . "</p>";
        echo "</div>";
    }

    // Cấu trúc file dự kiến
    private function getExpectedStructure() {
        return [
            // Config files
            'config/database.php' => ['required' => true, 'type' => 'config', 'description' => 'Kết nối MySQL PDO'],
            'config/config.php' => ['required' => true, 'type' => 'config', 'description' => 'Cấu hình chung + Helper functions'],
            
            // Admin files
            'admin/layouts/sidebar.php' => ['required' => true, 'type' => 'layout', 'description' => 'Menu sidebar admin'],
            
            // Admin Colors
            'admin/colors/index.php' => ['required' => true, 'type' => 'admin', 'description' => 'Danh sách màu sắc'],
            'admin/colors/create.php' => ['required' => true, 'type' => 'admin', 'description' => 'Thêm màu sắc'],
            'admin/colors/edit.php' => ['required' => true, 'type' => 'admin', 'description' => 'Sửa màu sắc'],
            
            // Admin Products
            'admin/products/index.php' => ['required' => true, 'type' => 'admin', 'description' => 'Danh sách sản phẩm'],
            'admin/products/create.php' => ['required' => true, 'type' => 'admin', 'description' => 'Thêm sản phẩm mới'],
            'admin/products/variants.php' => ['required' => true, 'type' => 'admin', 'description' => 'Quản lý biến thể'],
            
            // Admin Orders
            'admin/orders/index.php' => ['required' => true, 'type' => 'admin', 'description' => 'Danh sách đơn hàng'],
            'admin/orders/detail.php' => ['required' => true, 'type' => 'admin', 'description' => 'Chi tiết đơn hàng'],
            'admin/orders/update_status.php' => ['required' => true, 'type' => 'admin', 'description' => 'Cập nhật trạng thái'],
            
            // Admin Reviews
            'admin/reviews/index.php' => ['required' => true, 'type' => 'admin', 'description' => 'Quản lý đánh giá'],
            
            // Customer Frontend
            'customer/includes/header.php' => ['required' => true, 'type' => 'frontend', 'description' => 'Header responsive'],
            'customer/includes/footer.php' => ['required' => true, 'type' => 'frontend', 'description' => 'Footer'],
            'customer/index.php' => ['required' => true, 'type' => 'frontend', 'description' => 'Trang chủ'],
            'customer/login.php' => ['required' => true, 'type' => 'frontend', 'description' => 'Đăng nhập'],
            'customer/register.php' => ['required' => true, 'type' => 'frontend', 'description' => 'Đăng ký'],
            'customer/logout.php' => ['required' => true, 'type' => 'frontend', 'description' => 'Đăng xuất'],
            'customer/products.php' => ['required' => true, 'type' => 'frontend', 'description' => 'Danh sách sản phẩm'],
            'customer/product_detail.php' => ['required' => false, 'type' => 'frontend', 'description' => 'Chi tiết sản phẩm (thiếu code)'],
            'customer/cart.php' => ['required' => false, 'type' => 'frontend', 'description' => 'Giỏ hàng (chưa có code)'],
            'customer/checkout.php' => ['required' => true, 'type' => 'frontend', 'description' => 'Thanh toán'],
            'customer/orders.php' => ['required' => true, 'type' => 'frontend', 'description' => 'Theo dõi đơn hàng'],
            
            // VNPay
            'vnpay/create_payment.php' => ['required' => false, 'type' => 'payment', 'description' => 'Tạo thanh toán VNPay (chưa có code)'],
            'vnpay/return.php' => ['required' => false, 'type' => 'payment', 'description' => 'Xử lý kết quả thanh toán'],
            'vnpay/check_status.php' => ['required' => false, 'type' => 'payment', 'description' => 'Kiểm tra trạng thái giao dịch'],
            
            // Database
            'database.sql' => ['required' => true, 'type' => 'database', 'description' => 'File cơ sở dữ liệu'],
        ];
    }

    // Kiểm tra cấu trúc file
    public function checkFileStructure() {
        echo "<div class='section'>";
        echo "<div class='section-title'>📁 Kiểm tra cấu trúc file</div>";
        echo "<div class='section-content'>";
        
        $expectedFiles = $this->getExpectedStructure();
        $this->summary['total_files'] = count($expectedFiles);
        
        echo "<div class='file-grid'>";
        
        foreach ($expectedFiles as $filePath => $info) {
            $fullPath = $this->baseDir . '/' . $filePath;
            $exists = file_exists($fullPath);
            $status = 'missing';
            $statusText = 'Không tồn tại';
            $statusClass = 'error';
            
            if ($exists) {
                $this->summary['existing_files']++;
                $fileSize = filesize($fullPath);
                
                if ($fileSize > 0) {
                    // Kiểm tra nội dung cơ bản
                    $content = file_get_contents($fullPath);
                    if (strpos($content, '<?php') !== false || pathinfo($fullPath, PATHINFO_EXTENSION) === 'sql') {
                        $status = 'exists';
                        $statusText = 'Tồn tại ✓';
                        $statusClass = 'success';
                    } else {
                        $status = 'incomplete';
                        $statusText = 'Chưa hoàn thiện';
                        $statusClass = 'warning';
                        $this->summary['incomplete_files']++;
                    }
                } else {
                    $status = 'incomplete';
                    $statusText = 'File rỗng';
                    $statusClass = 'warning';
                    $this->summary['incomplete_files']++;
                }
            } else {
                $this->summary['missing_files']++;
            }
            
            $requiredText = $info['required'] ? 'Bắt buộc' : 'Tùy chọn';
            $requiredClass = $info['required'] ? 'error' : 'warning';
            
            echo "<div class='file-item {$status}'>";
            echo "<div class='file-path'>{$filePath}</div>";
            echo "<div class='file-info'>";
            echo "<span class='status {$statusClass}'>{$statusText}</span> ";
            echo "<span class='status {$requiredClass}'>{$requiredText}</span><br>";
            echo "📝 {$info['description']}";
            if ($exists && $status === 'exists') {
                echo "<br>📊 Kích thước: " . $this->formatBytes(filesize($fullPath));
                echo "<br>📅 Cập nhật: " . date('d/m/Y H:i:s', filemtime($fullPath));
            }
            echo "</div>";
            echo "</div>";
        }
        
        echo "</div>";
        echo "</div>";
        echo "</div>";
    }

    // Kiểm tra thư mục
    public function checkDirectories() {
        echo "<div class='section'>";
        echo "<div class='section-title'>📂 Kiểm tra thư mục</div>";
        echo "<div class='section-content'>";
        
        $expectedDirs = [
            'config' => 'Cấu hình hệ thống',
            'admin' => 'Quản trị viên',
            'admin/layouts' => 'Layout admin',
            'admin/colors' => 'Quản lý màu sắc',
            'admin/products' => 'Quản lý sản phẩm',
            'admin/orders' => 'Quản lý đơn hàng',
            'admin/reviews' => 'Quản lý đánh giá',
            'admin/users' => 'Quản lý người dùng',
            'admin/categories' => 'Quản lý danh mục',
            'admin/sizes' => 'Quản lý kích cỡ',
            'customer' => 'Frontend khách hàng',
            'customer/includes' => 'Include files',
            'vnpay' => 'Tích hợp VNPay',
            'uploads' => 'Thư mục upload',
            'uploads/products' => 'Ảnh sản phẩm',
            'uploads/categories' => 'Ảnh danh mục',
            'assets' => 'Tài nguyên tĩnh',
            'assets/images' => 'Ảnh hệ thống'
        ];
        
        echo "<div class='file-grid'>";
        
        foreach ($expectedDirs as $dir => $description) {
            $fullPath = $this->baseDir . '/' . $dir;
            $exists = is_dir($fullPath);
            
            if ($exists) {
                $this->summary['directories']++;
                $fileCount = count(glob($fullPath . '/*'));
                $statusClass = 'success';
                $statusText = 'Tồn tại ✓';
            } else {
                $statusClass = 'error';
                $statusText = 'Không tồn tại';
                $fileCount = 0;
            }
            
            echo "<div class='file-item " . ($exists ? 'exists' : 'missing') . "'>";
            echo "<div class='file-path'>{$dir}/</div>";
            echo "<div class='file-info'>";
            echo "<span class='status {$statusClass}'>{$statusText}</span><br>";
            echo "📝 {$description}";
            if ($exists) {
                echo "<br>📊 Chứa: {$fileCount} items";
            }
            echo "</div>";
            echo "</div>";
        }
        
        echo "</div>";
        echo "</div>";
        echo "</div>";
    }

    // Kiểm tra quyền thư mục
    public function checkPermissions() {
        echo "<div class='section'>";
        echo "<div class='section-title'>🔒 Kiểm tra quyền thư mục</div>";
        echo "<div class='section-content'>";
        
        $writableDirs = ['uploads', 'uploads/products', 'uploads/categories'];
        
        echo "<table class='permissions-table'>";
        echo "<thead><tr><th>Thư mục</th><th>Readable</th><th>Writable</th><th>Executable</th><th>Trạng thái</th></tr></thead>";
        echo "<tbody>";
        
        foreach ($writableDirs as $dir) {
            $fullPath = $this->baseDir . '/' . $dir;
            
            if (is_dir($fullPath)) {
                $readable = is_readable($fullPath) ? '✅' : '❌';
                $writable = is_writable($fullPath) ? '✅' : '❌';
                $executable = is_executable($fullPath) ? '✅' : '❌';
                
                $allOk = is_readable($fullPath) && is_writable($fullPath) && is_executable($fullPath);
                $status = $allOk ? '<span class="status success">OK</span>' : '<span class="status error">Lỗi</span>';
                
                if ($allOk) $this->summary['permissions_ok']++;
                else $this->summary['permissions_error']++;
            } else {
                $readable = $writable = $executable = '❌';
                $status = '<span class="status error">Không tồn tại</span>';
                $this->summary['permissions_error']++;
            }
            
            echo "<tr>";
            echo "<td><code>{$dir}</code></td>";
            echo "<td>{$readable}</td>";
            echo "<td>{$writable}</td>";
            echo "<td>{$executable}</td>";
            echo "<td>{$status}</td>";
            echo "</tr>";
        }
        
        echo "</tbody></table>";
        echo "</div>";
        echo "</div>";
    }

    // Kiểm tra kết nối database
    public function checkDatabaseConnection() {
        echo "<div class='section'>";
        echo "<div class='section-title'>🗄️ Kiểm tra kết nối Database</div>";
        echo "<div class='section-content'>";
        
        $configFile = $this->baseDir . '/config/database.php';
        
        if (file_exists($configFile)) {
            echo "<p><span class='status success'>✓ File database.php tồn tại</span></p>";
            
            // Đọc và phân tích file config
            $content = file_get_contents($configFile);
            
            // Tìm các thông tin kết nối
            if (preg_match('/host[\'\"]\s*=>\s*[\'\"](.*?)[\'\"]/', $content, $matches)) {
                echo "<p>🏠 <strong>Host:</strong> " . $matches[1] . "</p>";
            }
            
            if (preg_match('/dbname[\'\"]\s*=>\s*[\'\"](.*?)[\'\"]/', $content, $matches)) {
                echo "<p>🗃️ <strong>Database:</strong> " . $matches[1] . "</p>";
            }
            
            if (preg_match('/username[\'\"]\s*=>\s*[\'\"](.*?)[\'\"]/', $content, $matches)) {
                echo "<p>👤 <strong>Username:</strong> " . $matches[1] . "</p>";
            }
            
            // Kiểm tra syntax PHP
            $syntaxCheck = shell_exec("php -l {$configFile} 2>&1");
            if (strpos($syntaxCheck, 'No syntax errors') !== false) {
                echo "<p><span class='status success'>✓ Syntax PHP hợp lệ</span></p>";
            } else {
                echo "<p><span class='status error'>✗ Lỗi syntax PHP:</span></p>";
                echo "<pre>" . htmlspecialchars($syntaxCheck) . "</pre>";
            }
            
        } else {
            echo "<p><span class='status error'>✗ File database.php không tồn tại</span></p>";
        }
        
        // Kiểm tra file SQL
        $sqlFile = $this->baseDir . '/database.sql';
        if (file_exists($sqlFile)) {
            $sqlSize = filesize($sqlFile);
            echo "<p><span class='status success'>✓ File database.sql tồn tại</span> (Kích thước: " . $this->formatBytes($sqlSize) . ")</p>";
            
            // Đọc và phân tích một phần file SQL
            $sqlContent = file_get_contents($sqlFile, false, null, 0, 2000);
            $tableCount = substr_count($sqlContent, 'CREATE TABLE');
            echo "<p>📊 <strong>Ước tính số bảng:</strong> {$tableCount}+</p>";
        } else {
            echo "<p><span class='status warning'>⚠ File database.sql không tồn tại</span></p>";
        }
        
        echo "</div>";
        echo "</div>";
    }

    // Kiểm tra các include/require
    public function checkIncludes() {
        echo "<div class='section'>";
        echo "<div class='section-title'>🔗 Kiểm tra Include/Require</div>";
        echo "<div class='section-content'>";
        
        $phpFiles = $this->getAllPHPFiles();
        $includeIssues = [];
        
        foreach ($phpFiles as $file) {
            $content = file_get_contents($file);
            
            // Tìm tất cả include/require
            preg_match_all('/(include|require)(_once)?\s*[\(\s]*[\'\"](.*?)[\'\"]/', $content, $matches, PREG_SET_ORDER);
            
            foreach ($matches as $match) {
                $includePath = $match[3];
                $relativeFile = str_replace($this->baseDir . '/', '', $file);
                
                // Tính toán đường dẫn tuyệt đối
                $baseFileDir = dirname($file);
                $absoluteIncludePath = $baseFileDir . '/' . $includePath;
                
                if (!file_exists($absoluteIncludePath)) {
                    $includeIssues[] = [
                        'file' => $relativeFile,
                        'include' => $includePath,
                        'resolved' => $absoluteIncludePath
                    ];
                }
            }
        }
        
        if (empty($includeIssues)) {
            echo "<p><span class='status success'>✓ Tất cả include/require đều hợp lệ</span></p>";
        } else {
            echo "<p><span class='status error'>✗ Phát hiện " . count($includeIssues) . " lỗi include/require:</span></p>";
            echo "<div class='file-grid'>";
            foreach ($includeIssues as $issue) {
                echo "<div class='file-item missing'>";
                echo "<div class='file-path'>🔴 {$issue['file']}</div>";
                echo "<div class='file-info'>";
                echo "Include: <code>{$issue['include']}</code><br>";
                echo "Resolved: <code>{$issue['resolved']}</code>";
                echo "</div>";
                echo "</div>";
            }
            echo "</div>";
        }
        
        echo "</div>";
        echo "</div>";
    }

    // Lấy tất cả file PHP
    private function getAllPHPFiles() {
        $iterator = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($this->baseDir)
        );
        
        $phpFiles = [];
        foreach ($iterator as $file) {
            if ($file->isFile() && $file->getExtension() === 'php') {
                $phpFiles[] = $file->getPathname();
            }
        }
        
        return $phpFiles;
    }

    // Hiển thị tóm tắt
    public function showSummary() {
        echo "<div class='section'>";
        echo "<div class='section-title'>📊 Tóm tắt kết quả</div>";
        echo "<div class='section-content'>";
        
        echo "<div class='summary'>";
        
        echo "<div class='summary-item'>";
        echo "<div class='summary-number' style='color: #28a745;'>{$this->summary['existing_files']}</div>";
        echo "<div class='summary-label'>Files tồn tại</div>";
        echo "</div>";
        
        echo "<div class='summary-item'>";
        echo "<div class='summary-number' style='color: #dc3545;'>{$this->summary['missing_files']}</div>";
        echo "<div class='summary-label'>Files thiếu</div>";
        echo "</div>";
        
        echo "<div class='summary-item'>";
        echo "<div class='summary-number' style='color: #ffc107;'>{$this->summary['incomplete_files']}</div>";
        echo "<div class='summary-label'>Files chưa hoàn thiện</div>";
        echo "</div>";
        
        echo "<div class='summary-item'>";
        echo "<div class='summary-number' style='color: #17a2b8;'>{$this->summary['directories']}</div>";
        echo "<div class='summary-label'>Thư mục OK</div>";
        echo "</div>";
        
        echo "</div>";
        
        // Progress bar
        $completionPercent = round(($this->summary['existing_files'] / $this->summary['total_files']) * 100);
        echo "<div style='margin: 20px 0;'>";
        echo "<h4>🎯 Mức độ hoàn thiện dự án: {$completionPercent}%</h4>";
        echo "<div class='progress-bar'>";
        echo "<div class='progress-fill' style='width: {$completionPercent}%;'></div>";
        echo "</div>";
        echo "</div>";
        
        echo "</div>";
        echo "</div>";
    }

    // Đưa ra khuyến nghị
    public function showRecommendations() {
        echo "<div class='section'>";
        echo "<div class='section-title'>💡 Khuyến nghị và hướng dẫn</div>";
        echo "<div class='section-content'>";
        
        echo "<div class='recommendations'>";
        echo "<h4>🔥 Ưu tiên cao:</h4>";
        echo "<ul>";
        
        if ($this->summary['missing_files'] > 0) {
            echo "<li><strong>Tạo các file bị thiếu:</strong> Có {$this->summary['missing_files']} file bắt buộc chưa tồn tại</li>";
        }
        
        if ($this->summary['incomplete_files'] > 0) {
            echo "<li><strong>Hoàn thiện nội dung:</strong> Có {$this->summary['incomplete_files']} file chưa có nội dung hoặc chưa hoàn thiện</li>";
        }
        
        if ($this->summary['permissions_error'] > 0) {
            echo "<li><strong>Sửa quyền thư mục:</strong> Sử dụng lệnh <code>chmod 755</code> cho thư mục và <code>chmod 644</code> cho file</li>";
        }
        
        echo "</ul>";
        
        echo "<h4>📋 Checklist hoàn thiện:</h4>";
        echo "<ul>";
        echo "<li>✅ Hoàn thiện file <code>customer/product_detail.php</code></li>";
        echo "<li>✅ Hoàn thiện file <code>customer/cart.php</code> với AJAX</li>";
        echo "<li>✅ Tích hợp VNPay payment gateway</li>";
        echo "<li>✅ Tạo các module quản lý admin/users, admin/categories, admin/sizes</li>";
        echo "<li>✅ Kiểm tra và sửa lỗi đường dẫn include/require</li>";
        echo "<li>✅ Thiết lập cơ sở dữ liệu và test kết nối</li>";
        echo "</ul>";
        
        echo "<h4>🚀 Tối ưu hóa:</h4>";
        echo "<ul>";
        echo "<li>Sử dụng <code>.htaccess</code> để tạo URL thân thiện</li>";
        echo "<li>Thêm validation và sanitization cho input</li>";
        echo "<li>Implement caching và optimization</li>";
        echo "<li>Thêm logging và error handling</li>";
        echo "</ul>";
        
        echo "</div>";
        echo "</div>";
        echo "</div>";
    }

    // Format bytes
    private function formatBytes($size, $precision = 2) {
        $units = array('B', 'KB', 'MB', 'GB');
        
        for ($i = 0; $size >= 1024 && $i < count($units) - 1; $i++) {
            $size /= 1024;
        }
        
        return round($size, $precision) . ' ' . $units[$i];
    }

    // Kiểm tra cấu hình PHP
    public function checkPHPConfiguration() {
        echo "<div class='section'>";
        echo "<div class='section-title'>⚙️ Kiểm tra cấu hình PHP</div>";
        echo "<div class='section-content'>";
        
        echo "<table class='permissions-table'>";
        echo "<thead><tr><th>Cấu hình</th><th>Giá trị hiện tại</th><th>Khuyến nghị</th><th>Trạng thái</th></tr></thead>";
        echo "<tbody>";
        
        $phpChecks = [
            'PHP Version' => [
                'current' => phpversion(),
                'recommended' => '≥ 7.4',
                'check' => version_compare(phpversion(), '7.4.0', '>=')
            ],
            'PDO MySQL' => [
                'current' => extension_loaded('pdo_mysql') ? 'Enabled' : 'Disabled',
                'recommended' => 'Enabled',
                'check' => extension_loaded('pdo_mysql')
            ],
            'File Uploads' => [
                'current' => ini_get('file_uploads') ? 'On' : 'Off',
                'recommended' => 'On',
                'check' => ini_get('file_uploads')
            ],
            'Upload Max Filesize' => [
                'current' => ini_get('upload_max_filesize'),
                'recommended' => '≥ 10M',
                'check' => $this->parseSize(ini_get('upload_max_filesize')) >= $this->parseSize('10M')
            ],
            'Post Max Size' => [
                'current' => ini_get('post_max_size'),
                'recommended' => '≥ 10M',
                'check' => $this->parseSize(ini_get('post_max_size')) >= $this->parseSize('10M')
            ],
            'Memory Limit' => [
                'current' => ini_get('memory_limit'),
                'recommended' => '≥ 128M',
                'check' => $this->parseSize(ini_get('memory_limit')) >= $this->parseSize('128M')
            ],
            'Session Auto Start' => [
                'current' => ini_get('session.auto_start') ? 'On' : 'Off',
                'recommended' => 'Off',
                'check' => !ini_get('session.auto_start')
            ]
        ];
        
        foreach ($phpChecks as $setting => $info) {
            $status = $info['check'] ? 
                '<span class="status success">✓ OK</span>' : 
                '<span class="status error">✗ Cần sửa</span>';
                
            echo "<tr>";
            echo "<td><strong>{$setting}</strong></td>";
            echo "<td><code>{$info['current']}</code></td>";
            echo "<td><code>{$info['recommended']}</code></td>";
            echo "<td>{$status}</td>";
            echo "</tr>";
        }
        
        echo "</tbody></table>";
        echo "</div>";
        echo "</div>";
    }

    // Parse size string to bytes
    private function parseSize($size) {
        $unit = strtoupper(substr($size, -1));
        $value = (int) $size;
        
        switch ($unit) {
            case 'G': $value *= 1024;
            case 'M': $value *= 1024;
            case 'K': $value *= 1024;
        }
        
        return $value;
    }

    // Kiểm tra bảo mật cơ bản
    public function checkSecurity() {
        echo "<div class='section'>";
        echo "<div class='section-title'>🔐 Kiểm tra bảo mật cơ bản</div>";
        echo "<div class='section-content'>";
        
        $securityIssues = [];
        
        // Kiểm tra .htaccess
        $htaccessFile = $this->baseDir . '/.htaccess';
        if (!file_exists($htaccessFile)) {
            $securityIssues[] = [
                'type' => 'missing',
                'item' => '.htaccess file',
                'description' => 'Thiếu file .htaccess để bảo vệ và cấu hình Apache'
            ];
        }
        
        // Kiểm tra index.php trong thư mục quan trọng
        $protectedDirs = ['config', 'uploads', 'admin'];
        foreach ($protectedDirs as $dir) {
            $indexFile = $this->baseDir . '/' . $dir . '/index.php';
            if (is_dir($this->baseDir . '/' . $dir) && !file_exists($indexFile)) {
                $securityIssues[] = [
                    'type' => 'directory_listing',
                    'item' => $dir . '/index.php',
                    'description' => "Thiếu file index.php trong thư mục {$dir} - có thể bị liệt kê thư mục"
                ];
            }
        }
        
        // Kiểm tra hardcoded passwords/secrets
        $phpFiles = $this->getAllPHPFiles();
        foreach ($phpFiles as $file) {
            $content = file_get_contents($file);
            $relativeFile = str_replace($this->baseDir . '/', '', $file);
            
            // Tìm các pattern nguy hiểm
            $dangerousPatterns = [
                '/password[\'\"]\s*=>\s*[\'\"]((?![\$_]).{1,})[\'\"]/i',
                '/api_key[\'\"]\s*=>\s*[\'\"]((?![\$_]).{1,})[\'\"]/i',
                '/secret[\'\"]\s*=>\s*[\'\"]((?![\$_]).{1,})[\'\"]/i'
            ];
            
            foreach ($dangerousPatterns as $pattern) {
                if (preg_match($pattern, $content)) {
                    $securityIssues[] = [
                        'type' => 'hardcoded_secret',
                        'item' => $relativeFile,
                        'description' => 'Có thể chứa mật khẩu hoặc secret được hardcode'
                    ];
                    break;
                }
            }
        }
        
        if (empty($securityIssues)) {
            echo "<p><span class='status success'>✓ Không phát hiện vấn đề bảo mật cơ bản</span></p>";
        } else {
            echo "<div class='file-grid'>";
            foreach ($securityIssues as $issue) {
                $iconMap = [
                    'missing' => '⚠️',
                    'directory_listing' => '📂',
                    'hardcoded_secret' => '🔑'
                ];
                
                echo "<div class='file-item missing'>";
                echo "<div class='file-path'>{$iconMap[$issue['type']]} {$issue['item']}</div>";
                echo "<div class='file-info'>{$issue['description']}</div>";
                echo "</div>";
            }
            echo "</div>";
        }
        
        echo "<div class='recommendations'>";
        echo "<h4>🛡️ Khuyến nghị bảo mật:</h4>";
        echo "<ul>";
        echo "<li>Tạo file <code>.htaccess</code> để chặn truy cập trực tiếp vào các file config</li>";
        echo "<li>Thêm file <code>index.php</code> trống vào các thư mục quan trọng</li>";
        echo "<li>Sử dụng environment variables cho các thông tin nhạy cảm</li>";
        echo "<li>Implement CSRF protection cho các form</li>";
        echo "<li>Validate và sanitize tất cả input từ user</li>";
        echo "<li>Sử dụng prepared statements cho database queries</li>";
        echo "</ul>";
        echo "</div>";
        
        echo "</div>";
        echo "</div>";
    }

    // Tạo file .htaccess mẫu
    public function generateSampleFiles() {
        echo "<div class='section'>";
        echo "<div class='section-title'>📝 File mẫu được đề xuất</div>";
        echo "<div class='section-content'>";
        
        echo "<h4>🔧 File .htaccess mẫu:</h4>";
        echo "<pre style='background: #f8f9fa; padding: 15px; border-radius: 5px; overflow-x: auto;'>";
        echo htmlspecialchars("# Ngăn chặn truy cập trực tiếp vào file config
<Files \"*.php\">
    <IfModule mod_rewrite.c>
        RewriteEngine On
        RewriteCond %{REQUEST_FILENAME} -f
        RewriteRule ^config/ - [F,L]
    </IfModule>
</Files>

# Bảo vệ file nhạy cảm
<FilesMatch \"\\.(sql|log|txt)$\">
    Order allow,deny
    Deny from all
</FilesMatch>

# URL Rewrite cho trang sản phẩm
RewriteEngine On
RewriteRule ^product/([0-9]+)/?$ customer/product_detail.php?id=$1 [L,QSA]
RewriteRule ^category/([0-9]+)/?$ customer/products.php?category_id=$1 [L,QSA]

# Gzip compression
<IfModule mod_deflate.c>
    AddOutputFilterByType DEFLATE text/html text/css text/javascript application/javascript
</IfModule>

# Cache static files
<IfModule mod_expires.c>
    ExpiresActive On
    ExpiresByType image/jpg \"access plus 1 month\"
    ExpiresByType image/jpeg \"access plus 1 month\"
    ExpiresByType image/gif \"access plus 1 month\"
    ExpiresByType image/png \"access plus 1 month\"
    ExpiresByType text/css \"access plus 1 month\"
    ExpiresByType application/pdf \"access plus 1 month\"
    ExpiresByType text/javascript \"access plus 1 month\"
    ExpiresByType application/javascript \"access plus 1 month\"
</IfModule>");
        echo "</pre>";
        
        echo "<h4>🗂️ File config/config.php mẫu:</h4>";
        echo "<pre style='background: #f8f9fa; padding: 15px; border-radius: 5px; overflow-x: auto;'>";
        echo htmlspecialchars("<?php
// Ngăn chặn truy cập trực tiếp
if (!defined('TKTSHOP_ACCESS')) {
    die('Direct access denied');
}

// Cấu hình chung
define('SITE_NAME', 'TKTShop');
define('SITE_URL', 'http://localhost/tktshop');
define('UPLOAD_PATH', __DIR__ . '/../uploads/');
define('UPLOAD_URL', SITE_URL . '/uploads/');

// Cấu hình phân trang
define('ITEMS_PER_PAGE', 12);

// Cấu hình upload
define('MAX_FILE_SIZE', 5 * 1024 * 1024); // 5MB
define('ALLOWED_EXTENSIONS', ['jpg', 'jpeg', 'png', 'gif']);

// Helper functions
function formatPrice(\$price) {
    return number_format(\$price, 0, ',', '.') . ' VNĐ';
}

function uploadImage(\$file, \$folder = 'products') {
    // Code upload image
}

function generateSlug(\$string) {
    return strtolower(trim(preg_replace('/[^A-Za-z0-9-]+/', '-', \$string)));
}

// Session management
if (session_status() == PHP_SESSION_NONE) {
    session_start();
}
?>");
        echo "</pre>";
        
        echo "</div>";
        echo "</div>";
    }

    // Chạy tất cả các kiểm tra
    public function runAllChecks() {
        $this->checkFileStructure();
        $this->checkDirectories();
        $this->checkPermissions();
        $this->checkDatabaseConnection();
        $this->checkIncludes();
        $this->checkPHPConfiguration();
        $this->checkSecurity();
        $this->showSummary();
        $this->showRecommendations();
        $this->generateSampleFiles();
        
        echo "<div style='text-align: center; margin: 30px 0;'>";
        echo "<p style='color: #6c757d;'>Debug completed at " . date('d/m/Y H:i:s') . "</p>";
        echo "<p style='color: #6c757d;'>🔄 <a href='" . $_SERVER['PHP_SELF'] . "' style='color: #007bff;'>Chạy lại kiểm tra</a></p>";
        echo "</div>";
        
        echo "</div>"; // Close container
        echo "</body></html>";
    }
}

// Chạy debugger
define('TKTSHOP_ACCESS', true);
$debugger = new TKTShopDebugger();
$debugger->runAllChecks();
?>