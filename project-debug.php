<?php
// project-debug.php - Accurate TKT Shop Project Debug Tool
// Đặt file này tại: /tktshop/project-debug.php

error_reporting(E_ALL);
ini_set('display_errors', 1);
session_start();

// Auto-fix mode
$auto_fix = isset($_GET['fix']) && $_GET['fix'] == '1';

echo "<!DOCTYPE html>
<html lang='vi'>
<head>
    <meta charset='UTF-8'>
    <meta name='viewport' content='width=device-width, initial-scale=1.0'>
    <title>TKT Shop - Project Structure Debug Tool</title>
    <link href='https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css' rel='stylesheet'>
    <link href='https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css' rel='stylesheet'>
    <style>
        .debug-section { margin-bottom: 30px; }
        .code-block { background: #f8f9fa; padding: 15px; border-radius: 5px; font-family: monospace; font-size: 14px; }
        .error { color: #dc3545; font-weight: bold; }
        .success { color: #198754; }
        .warning { color: #fd7e14; }
        .info { color: #0dcaf0; }
        .missing { background-color: #fff3cd; border-left: 4px solid #ffc107; padding: 8px; margin: 3px 0; }
        .found { background-color: #d1edff; border-left: 4px solid #0dcaf0; padding: 8px; margin: 3px 0; }
        .created { background-color: #d4edda; border-left: 4px solid #198754; padding: 8px; margin: 3px 0; }
        .folder-icon { color: #ffc107; }
        .file-icon { color: #6c757d; }
        .php-icon { color: #777bb4; }
        .sql-icon { color: #00758f; }
        .sticky-nav { position: sticky; top: 20px; z-index: 1000; }
        .progress-ring { width: 60px; height: 60px; }
        .progress-ring__circle { 
            stroke: #007bff; 
            fill: transparent; 
            stroke-width: 4; 
            stroke-dasharray: 188.5;
            stroke-dashoffset: 188.5;
            transition: stroke-dashoffset 0.5s;
        }
    </style>
</head>
<body>
<div class='container-fluid'>
    <div class='row'>
        <div class='col-md-3'>
            <div class='sticky-nav'>
                <div class='card'>
                    <div class='card-header bg-primary text-white'>
                        <h6><i class='fas fa-tools me-2'></i>TKT Shop Debug</h6>
                    </div>
                    <div class='card-body p-3'>
                        <div class='d-grid gap-2 mb-3'>
                            <a href='#overview' class='btn btn-outline-primary btn-sm'>📊 Tổng quan</a>
                            <a href='#config' class='btn btn-outline-success btn-sm'>⚙️ Config</a>
                            <a href='#admin' class='btn btn-outline-info btn-sm'>🔧 Admin</a>
                            <a href='#customer' class='btn btn-outline-warning btn-sm'>🏠 Customer</a>
                            <a href='#vnpay' class='btn btn-outline-danger btn-sm'>💳 VNPay</a>
                            <a href='#assets' class='btn btn-outline-secondary btn-sm'>📁 Assets</a>
                            <a href='#database' class='btn btn-outline-dark btn-sm'>🗄️ Database</a>
                        </div>
                        <hr>
                        <div class='d-grid gap-2'>
                            <a href='?fix=1' class='btn btn-success btn-sm'>
                                <i class='fas fa-magic'></i> Auto Fix
                            </a>
                            <a href='/tktshop/customer/' class='btn btn-outline-primary btn-sm'>
                                <i class='fas fa-home'></i> Customer
                            </a>
                            <a href='/tktshop/admin/dashboard.php' class='btn btn-outline-secondary btn-sm'>
                                <i class='fas fa-cog'></i> Admin
                            </a>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        <div class='col-md-9'>
            <h1 class='mb-4'><i class='fas fa-search'></i> TKT Shop - Project Structure Debug</h1>";

// Global counters
$total_files = 0;
$found_files = 0;
$missing_files = 0;
$fixes_applied = [];
$errors_found = [];

// Actual project structure based on your specification
$project_structure = [
    // 📁 config/ - Cấu hình hệ thống
    '/tktshop/config/' => ['type' => 'dir', 'desc' => 'Cấu hình hệ thống'],
    '/tktshop/config/database.php' => ['type' => 'file', 'desc' => 'Kết nối MySQL PDO', 'critical' => true],
    '/tktshop/config/config.php' => ['type' => 'file', 'desc' => 'Cấu hình chung + Helper functions', 'critical' => true],
    
    // 📁 admin/ - Quản trị viên (Backend)
    '/tktshop/admin/' => ['type' => 'dir', 'desc' => 'Quản trị viên (Backend)'],
    '/tktshop/admin/layouts/' => ['type' => 'dir', 'desc' => 'Layout admin'],
    '/tktshop/admin/layouts/sidebar.php' => ['type' => 'file', 'desc' => 'Menu sidebar admin', 'critical' => true],
    
    // 📁 admin/colors/ - Quản lý màu sắc
    '/tktshop/admin/colors/' => ['type' => 'dir', 'desc' => 'Quản lý màu sắc'],
    '/tktshop/admin/colors/index.php' => ['type' => 'file', 'desc' => 'Danh sách màu sắc', 'critical' => true],
    '/tktshop/admin/colors/create.php' => ['type' => 'file', 'desc' => 'Thêm màu sắc (Color picker)', 'critical' => true],
    '/tktshop/admin/colors/edit.php' => ['type' => 'file', 'desc' => 'Sửa màu sắc', 'critical' => true],
    
    // 📁 admin/products/ - Quản lý sản phẩm
    '/tktshop/admin/products/' => ['type' => 'dir', 'desc' => 'Quản lý sản phẩm'],
    '/tktshop/admin/products/index.php' => ['type' => 'file', 'desc' => 'Danh sách sản phẩm', 'critical' => true],
    '/tktshop/admin/products/create.php' => ['type' => 'file', 'desc' => 'Thêm sản phẩm mới', 'critical' => true],
    '/tktshop/admin/products/variants.php' => ['type' => 'file', 'desc' => 'Quản lý biến thể (Size + Màu)'],
    
    // 📁 admin/orders/ - Quản lý đơn hàng
    '/tktshop/admin/orders/' => ['type' => 'dir', 'desc' => 'Quản lý đơn hàng'],
    '/tktshop/admin/orders/index.php' => ['type' => 'file', 'desc' => 'Danh sách đơn hàng + Thống kê', 'critical' => true],
    '/tktshop/admin/orders/detail.php' => ['type' => 'file', 'desc' => 'Chi tiết đơn hàng + Timeline', 'critical' => true],
    '/tktshop/admin/orders/update_status.php' => ['type' => 'file', 'desc' => 'Cập nhật trạng thái đơn hàng', 'critical' => true],
    
    // 📁 admin/reviews/ - Quản lý đánh giá
    '/tktshop/admin/reviews/' => ['type' => 'dir', 'desc' => 'Quản lý đánh giá'],
    '/tktshop/admin/reviews/index.php' => ['type' => 'file', 'desc' => 'Duyệt/từ chối đánh giá'],
    
    // 📁 admin/users/ - Quản lý người dùng
    '/tktshop/admin/users/' => ['type' => 'dir', 'desc' => 'Quản lý người dùng'],
    '/tktshop/admin/users/index.php' => ['type' => 'file', 'desc' => 'Danh sách người dùng'],
    
    // 📁 admin/categories/ - Quản lý danh mục
    '/tktshop/admin/categories/' => ['type' => 'dir', 'desc' => 'Quản lý danh mục'],
    '/tktshop/admin/categories/index.php' => ['type' => 'file', 'desc' => 'Danh sách danh mục'],
    
    // 📁 admin/sizes/ - Quản lý kích cỡ
    '/tktshop/admin/sizes/' => ['type' => 'dir', 'desc' => 'Quản lý kích cỡ'],
    '/tktshop/admin/sizes/index.php' => ['type' => 'file', 'desc' => 'Danh sách kích cỡ', 'critical' => true],
    '/tktshop/admin/sizes/create.php' => ['type' => 'file', 'desc' => 'Thêm kích cỡ', 'critical' => true],
    '/tktshop/admin/sizes/edit.php' => ['type' => 'file', 'desc' => 'Sửa kích cỡ', 'critical' => true],
    
    // 📁 customer/ - Khách hàng (Frontend)
    '/tktshop/customer/' => ['type' => 'dir', 'desc' => 'Khách hàng (Frontend)'],
    '/tktshop/customer/includes/' => ['type' => 'dir', 'desc' => 'Include files'],
    '/tktshop/customer/includes/header.php' => ['type' => 'file', 'desc' => 'Header responsive + Menu + Giỏ hàng', 'critical' => true],
    '/tktshop/customer/includes/footer.php' => ['type' => 'file', 'desc' => 'Footer', 'critical' => true],
    '/tktshop/customer/index.php' => ['type' => 'file', 'desc' => 'Trang chủ (Hero, Sản phẩm nổi bật)', 'critical' => true],
    '/tktshop/customer/login.php' => ['type' => 'file', 'desc' => 'Đăng nhập khách hàng', 'critical' => true],
    '/tktshop/customer/register.php' => ['type' => 'file', 'desc' => 'Đăng ký tài khoản', 'critical' => true],
    '/tktshop/customer/logout.php' => ['type' => 'file', 'desc' => 'Đăng xuất'],
    '/tktshop/customer/products.php' => ['type' => 'file', 'desc' => 'Danh sách sản phẩm', 'critical' => true],
    '/tktshop/customer/product_detail.php' => ['type' => 'file', 'desc' => 'Chi tiết sản phẩm (thiếu code)', 'critical' => true],
    '/tktshop/customer/cart.php' => ['type' => 'file', 'desc' => 'Giỏ hàng (AJAX update) (chưa có code)', 'critical' => true],
    '/tktshop/customer/checkout.php' => ['type' => 'file', 'desc' => 'Thanh toán (VNPay + COD)', 'critical' => true],
    '/tktshop/customer/orders.php' => ['type' => 'file', 'desc' => 'Theo dõi đơn hàng'],
    
    // 📁 vnpay/ - Tích hợp VNPay
    '/tktshop/vnpay/' => ['type' => 'dir', 'desc' => 'Tích hợp VNPay (chưa có code)'],
    '/tktshop/vnpay/create_payment.php' => ['type' => 'file', 'desc' => 'Tạo thanh toán VNPay'],
    '/tktshop/vnpay/return.php' => ['type' => 'file', 'desc' => 'Xử lý kết quả thanh toán'],
    '/tktshop/vnpay/check_status.php' => ['type' => 'file', 'desc' => 'Kiểm tra trạng thái giao dịch'],
    
    // 📁 uploads/ - Thư mục upload
    '/tktshop/uploads/' => ['type' => 'dir', 'desc' => 'Thư mục upload', 'critical' => true],
    '/tktshop/uploads/products/' => ['type' => 'dir', 'desc' => 'Ảnh sản phẩm', 'critical' => true],
    '/tktshop/uploads/categories/' => ['type' => 'dir', 'desc' => 'Ảnh danh mục'],
    
    // 📁 assets/ - Tài nguyên tĩnh
    '/tktshop/assets/' => ['type' => 'dir', 'desc' => 'Tài nguyên tĩnh'],
    '/tktshop/assets/images/' => ['type' => 'dir', 'desc' => 'Ảnh hệ thống'],
    
    // 📄 database.sql
    '/tktshop/database.sql' => ['type' => 'file', 'desc' => 'File cấu trúc database', 'critical' => true],
];

// Count totals
foreach ($project_structure as $path => $info) {
    if ($info['type'] == 'file') {
        $total_files++;
    }
}

// 1. OVERVIEW SECTION
echo "<div class='debug-section' id='overview'>
        <h3><i class='fas fa-chart-pie'></i> 1. Tổng quan dự án</h3>
        <div class='row mb-4'>";

$overview_stats = analyzeProjectStructure($project_structure);

echo "<div class='col-md-3'>
        <div class='card text-center border-primary'>
            <div class='card-body'>
                <div class='progress-ring'>
                    <svg class='progress-ring' width='60' height='60'>
                        <circle class='progress-ring__circle' cx='30' cy='30' r='26'></circle>
                    </svg>
                </div>
                <h4 class='text-primary mt-2'>{$overview_stats['completion']}%</h4>
                <small>Hoàn thành</small>
            </div>
        </div>
      </div>
      <div class='col-md-3'>
        <div class='card text-center border-success'>
            <div class='card-body'>
                <h4 class='text-success'>{$overview_stats['found_files']}</h4>
                <small>Files tồn tại</small>
            </div>
        </div>
      </div>
      <div class='col-md-3'>
        <div class='card text-center border-warning'>
            <div class='card-body'>
                <h4 class='text-warning'>{$overview_stats['missing_files']}</h4>
                <small>Files thiếu</small>
            </div>
        </div>
      </div>
      <div class='col-md-3'>
        <div class='card text-center border-info'>
            <div class='card-body'>
                <h4 class='text-info'>{$overview_stats['total_dirs']}</h4>
                <small>Thư mục</small>
            </div>
        </div>
      </div>";

echo "</div></div>";

// 2. CONFIG SECTION
checkSection('config', '⚙️ 2. Config Files', $project_structure, [
    '/tktshop/config/',
    '/tktshop/config/database.php',
    '/tktshop/config/config.php'
]);

// 3. ADMIN SECTION
checkSection('admin', '🔧 3. Admin Backend', $project_structure, array_filter(array_keys($project_structure), function($k) {
    return strpos($k, '/admin/') !== false;
}));

// 4. CUSTOMER SECTION
checkSection('customer', '🏠 4. Customer Frontend', $project_structure, array_filter(array_keys($project_structure), function($k) {
    return strpos($k, '/customer/') !== false;
}));

// 5. VNPAY SECTION
checkSection('vnpay', '💳 5. VNPay Integration', $project_structure, array_filter(array_keys($project_structure), function($k) {
    return strpos($k, '/vnpay/') !== false;
}));

// 6. ASSETS SECTION
checkSection('assets', '📁 6. Assets & Uploads', $project_structure, array_filter(array_keys($project_structure), function($k) {
    return strpos($k, '/uploads/') !== false || strpos($k, '/assets/') !== false;
}));

// 7. DATABASE SECTION
echo "<div class='debug-section' id='database'>
        <h3><i class='fas fa-database'></i> 7. Database</h3>
        <div class='code-block'>";

checkDatabaseFile('/tktshop/database.sql');
checkDatabaseConnection();

echo "</div></div>";

// 8. RECOMMENDATIONS
echo "<div class='debug-section'>
        <h3><i class='fas fa-lightbulb'></i> 8. Khuyến nghị</h3>
        <div class='row'>";

generateRecommendations($overview_stats);

echo "</div></div>";

// 9. QUICK LINKS
echo "<div class='debug-section'>
        <h3><i class='fas fa-external-link-alt'></i> 9. Quick Links</h3>
        <div class='card'>
            <div class='card-body'>
                <div class='row g-3'>";

$quick_links = [
    ['name' => 'Customer Home', 'url' => '/tktshop/customer/', 'color' => 'primary', 'icon' => 'home'],
    ['name' => 'Customer Products', 'url' => '/tktshop/customer/products.php', 'color' => 'info', 'icon' => 'shopping-bag'],
    ['name' => 'Customer Login', 'url' => '/tktshop/customer/login.php', 'color' => 'success', 'icon' => 'sign-in-alt'],
    ['name' => 'Admin Dashboard', 'url' => '/tktshop/admin/dashboard.php', 'color' => 'secondary', 'icon' => 'cog'],
    ['name' => 'Admin Colors', 'url' => '/tktshop/admin/colors/', 'color' => 'warning', 'icon' => 'palette'],
    ['name' => 'Admin Products', 'url' => '/tktshop/admin/products/', 'color' => 'danger', 'icon' => 'box']
];

foreach ($quick_links as $link) {
    $exists = file_exists($_SERVER['DOCUMENT_ROOT'] . $link['url']) || is_dir($_SERVER['DOCUMENT_ROOT'] . $link['url']);
    $status = $exists ? '' : ' (Missing)';
    $disabled = $exists ? '' : ' disabled';
    
    echo "</div>
            </div>
          </div>
          <div class='col-md-6'>
            <div class='card border-success'>
                <div class='card-header bg-success text-white'>
                    <h6><i class='fas fa-lightbulb'></i> Development Tips</h6>
                </div>
                <div class='card-body'>";
    
    if ($stats['completion'] >= 80) {
        echo "<div class='mb-2'>
                <i class='fas fa-rocket text-success'></i> 
                <strong>Great progress!</strong> Most files are in place
              </div>";
    }
    
    echo "<div class='mb-2'>
            <i class='fas fa-shield-alt text-primary'></i> 
            Add .htaccess for URL rewriting and security
          </div>
          <div class='mb-2'>
            <i class='fas fa-lock text-warning'></i> 
            Implement proper authentication and session management
          </div>
          <div class='mb-2'>
            <i class='fas fa-mobile-alt text-info'></i> 
            Ensure responsive design for mobile users
          </div>
          <div class='mb-2'>
            <i class='fas fa-search text-secondary'></i> 
            Add SEO-friendly URLs and meta tags
          </div>";
    
    echo "</div>
            </div>
          </div>";
}

?><div class='col-md-4 col-sm-6 mb-2'>
            <a href='{$link['url']}' target='_blank' class='btn btn-outline-{$link['color']} w-100{$disabled}'>
                <i class='fas fa-{$link['icon']} me-2'></i>{$link['name']}{$status}
            </a>
          </div>";
}

echo "    </div>
        </div>
      </div>";

echo "</div>
        </div>
    </div>
</div>

<script src='https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js'></script>
<script>
    // Smooth scrolling
    document.querySelectorAll('a[href^=\"#\"]').forEach(anchor => {
        anchor.addEventListener('click', function (e) {
            e.preventDefault();
            document.querySelector(this.getAttribute('href')).scrollIntoView({
                behavior: 'smooth'
            });
        });
    });
    
    // Update progress ring
    const completion = {$overview_stats['completion']};
    const circle = document.querySelector('.progress-ring__circle');
    const radius = circle.r.baseVal.value;
    const circumference = radius * 2 * Math.PI;
    const offset = circumference - (completion / 100) * circumference;
    
    circle.style.strokeDasharray = circumference + ' ' + circumference;
    circle.style.strokeDashoffset = offset;
</script>
</body>
</html>";

// HELPER FUNCTIONS
function analyzeProjectStructure($structure) {
    $total_files = 0;
    $found_files = 0;
    $missing_files = 0;
    $total_dirs = 0;
    
    foreach ($structure as $path => $info) {
        $full_path = $_SERVER['DOCUMENT_ROOT'] . $path;
        $exists = ($info['type'] == 'dir') ? is_dir($full_path) : file_exists($full_path);
        
        if ($info['type'] == 'file') {
            $total_files++;
            if ($exists) {
                $found_files++;
            } else {
                $missing_files++;
            }
        } else {
            $total_dirs++;
        }
    }
    
    $completion = $total_files > 0 ? round(($found_files / $total_files) * 100) : 0;
    
    return [
        'total_files' => $total_files,
        'found_files' => $found_files,
        'missing_files' => $missing_files,
        'total_dirs' => $total_dirs,
        'completion' => $completion
    ];
}

function checkSection($id, $title, $structure, $paths) {
    echo "<div class='debug-section' id='{$id}'>
            <h3>{$title}</h3>
            <div class='code-block'>";
    
    $section_found = 0;
    $section_total = 0;
    
    foreach ($paths as $path) {
        if (!isset($structure[$path])) continue;
        
        $info = $structure[$path];
        $full_path = $_SERVER['DOCUMENT_ROOT'] . $path;
        $exists = ($info['type'] == 'dir') ? is_dir($full_path) : file_exists($full_path);
        
        if ($info['type'] == 'file') {
            $section_total++;
            if ($exists) {
                $section_found++;
            }
        }
        
        $icon = getFileIcon($path, $info['type']);
        $status_icon = $exists ? "<span class='success'>✓</span>" : "<span class='error'>✗</span>";
        $critical = isset($info['critical']) && $info['critical'] ? " <span class='badge bg-danger'>Critical</span>" : "";
        $size_info = "";
        
        if ($exists && $info['type'] == 'file') {
            $size = filesize($full_path);
            $size_info = " <small class='text-muted'>({$size} bytes)</small>";
        }
        
        echo "<div class='" . ($exists ? 'found' : 'missing') . "'>
                {$status_icon} {$icon} {$path}{$size_info}{$critical}
                <br><small class='text-muted ms-4'>{$info['desc']}</small>
              </div>";
    }
    
    if ($section_total > 0) {
        $section_completion = round(($section_found / $section_total) * 100);
        echo "<div class='mt-3 p-2 bg-light rounded'>
                <strong>Section Progress: {$section_found}/{$section_total} files ({$section_completion}%)</strong>
              </div>";
    }
    
    echo "</div></div>";
}

function getFileIcon($path, $type) {
    if ($type == 'dir') {
        return "<i class='fas fa-folder folder-icon'></i>";
    }
    
    $extension = pathinfo($path, PATHINFO_EXTENSION);
    switch ($extension) {
        case 'php':
            return "<i class='fab fa-php php-icon'></i>";
        case 'sql':
            return "<i class='fas fa-database sql-icon'></i>";
        default:
            return "<i class='fas fa-file file-icon'></i>";
    }
}

function checkDatabaseFile($path) {
    $full_path = $_SERVER['DOCUMENT_ROOT'] . $path;
    if (file_exists($full_path)) {
        $size = filesize($full_path);
        $content = file_get_contents($full_path);
        $tables = substr_count(strtoupper($content), 'CREATE TABLE');
        echo "<span class='success'>✓</span> {$path} exists ({$size} bytes, {$tables} tables)<br>";
        
        // Check for key tables
        $required_tables = ['nguoi_dung', 'san_pham_chinh', 'don_hang', 'mau_sac', 'kich_co'];
        foreach ($required_tables as $table) {
            if (stripos($content, $table) !== false) {
                echo "<span class='success'>✓</span> Table definition found: {$table}<br>";
            } else {
                echo "<span class='warning'>⚠</span> Table definition missing: {$table}<br>";
            }
        }
    } else {
        echo "<span class='error'>✗</span> {$path} - DATABASE FILE MISSING<br>";
        echo "<div class='alert alert-danger'>
                <strong>Critical:</strong> Database structure file is missing! 
                This file should contain all table definitions for the project.
              </div>";
    }
}

function checkDatabaseConnection() {
    echo "<br><strong>Database Connection Test:</strong><br>";
    
    $config_path = $_SERVER['DOCUMENT_ROOT'] . '/tktshop/config/database.php';
    if (file_exists($config_path)) {
        try {
            include_once $config_path;
            if (isset($pdo)) {
                $stmt = $pdo->query("SELECT 1");
                echo "<span class='success'>✓</span> Database connection: OK<br>";
                
                // Check if database has tables
                $stmt = $pdo->query("SHOW TABLES");
                $tables = $stmt->fetchAll(PDO::FETCH_COLUMN);
                echo "<span class='info'>ℹ</span> Found " . count($tables) . " tables in database<br>";
                
                if (count($tables) > 0) {
                    echo "<small class='text-muted'>Tables: " . implode(', ', array_slice($tables, 0, 5));
                    if (count($tables) > 5) echo " and " . (count($tables) - 5) . " more...";
                    echo "</small><br>";
                }
            } else {
                echo "<span class='error'>✗</span> PDO connection not established<br>";
            }
        } catch (Exception $e) {
            echo "<span class='error'>✗</span> Database connection failed: " . $e->getMessage() . "<br>";
        }
    } else {
        echo "<span class='error'>✗</span> Database config file missing<br>";
    }
}

function generateRecommendations($stats) {
    echo "<div class='col-md-6'>
            <div class='card border-warning'>
                <div class='card-header bg-warning text-dark'>
                    <h6><i class='fas fa-exclamation-triangle'></i> Priority Actions</h6>
                </div>
                <div class='card-body'>";
    
    if ($stats['missing_files'] > 0) {
        echo "<div class='mb-2'>
                <i class='fas fa-file-plus text-danger'></i> 
                <strong>{$stats['missing_files']} files missing</strong> - These need to be created
              </div>";
    }
    
    if ($stats['completion'] < 50) {
        echo "<div class='mb-2'>
                <i class='fas fa-code text-warning'></i> 
                <strong>Low completion rate</strong> - Focus on critical files first
              </div>";
    }
    
    echo "<div class='mb-2'>
            <i class='fas fa-database text-info'></i> 
            Import <code>database.sql</code> into MySQL
          </div>
          <div class='mb-2'>
            <i class='fas fa-cog text-primary'></i> 
            Configure database connection in config files
          </div>";
    
    echo "