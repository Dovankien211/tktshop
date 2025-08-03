<?php
/**
 * QUICK FRONTEND TEST - ENHANCED VERSION
 * Kiểm tra nhanh từng trang frontend có chạy được không + Advanced Features
 */

// Bật error reporting để debug
error_reporting(E_ALL);
ini_set('display_errors', 1);

echo "<!DOCTYPE html>
<html lang='vi'>
<head>
    <meta charset='UTF-8'>
    <meta name='viewport' content='width=device-width, initial-scale=1.0'>
    <title>Quick Frontend Test - TKTShop Enhanced</title>
    <style>
        body { font-family: Arial, sans-serif; margin: 20px; background: #f5f5f5; }
        .container { max-width: 1200px; margin: 0 auto; }
        .test-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(300px, 1fr)); gap: 20px; margin: 20px 0; }
        .test-item { background: white; border-radius: 10px; padding: 20px; box-shadow: 0 2px 10px rgba(0,0,0,0.1); }
        .test-item h3 { margin-bottom: 15px; color: #333; }
        .test-button { background: #007bff; color: white; padding: 10px 20px; border: none; border-radius: 5px; text-decoration: none; display: inline-block; margin: 5px; cursor: pointer; }
        .test-button:hover { background: #0056b3; }
        .test-result { margin: 10px 0; padding: 10px; border-radius: 5px; }
        .success { background: #d4edda; color: #155724; border: 1px solid #c3e6cb; }
        .error { background: #f8d7da; color: #721c24; border: 1px solid #f1aeb5; }
        .warning { background: #fff3cd; color: #856404; border: 1px solid #ffeaa7; }
        .info { background: #d1ecf1; color: #0c5460; border: 1px solid #bee5eb; }
        .header { background: linear-gradient(135deg, #343a40, #495057); color: white; padding: 30px; border-radius: 10px; margin-bottom: 30px; text-align: center; box-shadow: 0 5px 15px rgba(0,0,0,0.1); }
        iframe { width: 100%; height: 400px; border: 1px solid #ddd; border-radius: 5px; margin-top: 10px; }
        .progress-bar { width: 100%; height: 20px; background: #e9ecef; border-radius: 10px; overflow: hidden; margin: 10px 0; }
        .progress-fill { height: 100%; background: linear-gradient(90deg, #28a745, #20c997); transition: width 0.3s ease; }
        .stats-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(150px, 1fr)); gap: 15px; margin: 20px 0; }
        .stat-card { background: white; padding: 20px; border-radius: 10px; text-align: center; box-shadow: 0 3px 10px rgba(0,0,0,0.1); }
        .stat-number { font-size: 2em; font-weight: bold; margin-bottom: 5px; }
        .console { background: #2d3748; color: #e2e8f0; padding: 15px; border-radius: 8px; font-family: 'Courier New', monospace; font-size: 14px; margin: 10px 0; max-height: 300px; overflow-y: auto; }
        .btn-group { display: flex; gap: 10px; flex-wrap: wrap; margin: 10px 0; }
        .loading { opacity: 0.6; pointer-events: none; }
        .expandable { cursor: pointer; user-select: none; }
        .expandable:hover { background: #f8f9fa; }
        .collapsed { display: none; }
        table { width: 100%; border-collapse: collapse; margin: 15px 0; }
        th, td { padding: 12px; text-align: left; border-bottom: 1px solid #ddd; }
        th { background: #f8f9fa; font-weight: 600; }
        .tooltip { position: relative; display: inline-block; cursor: help; }
        .tooltip .tooltiptext { visibility: hidden; width: 200px; background-color: #555; color: #fff; text-align: center; border-radius: 6px; padding: 5px; position: absolute; z-index: 1; bottom: 125%; left: 50%; margin-left: -100px; opacity: 0; transition: opacity 0.3s; }
        .tooltip:hover .tooltiptext { visibility: visible; opacity: 1; }
        .response-time { font-size: 0.8em; color: #6c757d; }
        .status-indicator { display: inline-block; width: 12px; height: 12px; border-radius: 50%; margin-right: 5px; }
        .status-online { background: #28a745; }
        .status-offline { background: #dc3545; }
        .status-warning { background: #ffc107; }
    </style>
    <script>
        let testStats = {
            total: 0,
            passed: 0,
            failed: 0,
            warnings: 0,
            startTime: null,
            endTime: null
        };

        function updateStats() {
            document.getElementById('stat-total').textContent = testStats.total;
            document.getElementById('stat-passed').textContent = testStats.passed;
            document.getElementById('stat-failed').textContent = testStats.failed;
            document.getElementById('stat-warnings').textContent = testStats.warnings;
            
            if (testStats.total > 0) {
                const successRate = Math.round((testStats.passed / testStats.total) * 100);
                document.getElementById('stat-success-rate').textContent = successRate + '%';
                
                const progressBar = document.querySelector('.progress-fill');
                progressBar.style.width = successRate + '%';
            }
            
            if (testStats.startTime && testStats.endTime) {
                const duration = (testStats.endTime - testStats.startTime) / 1000;
                document.getElementById('stat-duration').textContent = duration.toFixed(2) + 's';
            }
        }

        function testPage(url, resultId, showPreview = true) {
            const resultDiv = document.getElementById(resultId);
            const startTime = performance.now();
            
            resultDiv.innerHTML = '<div class=\"warning\">⏳ Đang test...</div>';
            resultDiv.classList.add('loading');
            
            testStats.total++;
            updateStats();
            
            fetch(url, {
                method: 'GET',
                cache: 'no-cache',
                headers: {
                    'X-Requested-With': 'XMLHttpRequest'
                }
            })
            .then(response => {
                const endTime = performance.now();
                const responseTime = Math.round(endTime - startTime);
                
                if (response.ok) {
                    testStats.passed++;
                    let statusClass = 'success';
                    let statusText = '✅ Trang load thành công';
                    
                    // Kiểm tra content type
                    const contentType = response.headers.get('content-type');
                    if (contentType && !contentType.includes('text/html')) {
                        testStats.warnings++;
                        statusClass = 'warning';
                        statusText = '⚠️ Content type không phải HTML';
                    }
                    
                    resultDiv.innerHTML = `
                        <div class=\"\${statusClass}\">
                            \${statusText} (Status: \${response.status})
                            <span class=\"response-time\">⏱️ \${responseTime}ms</span>
                        </div>
                    `;
                    
                    // Tạo iframe để xem preview nếu cần
                    if (showPreview && response.ok) {
                        const iframe = document.createElement('iframe');
                        iframe.src = url;
                        iframe.style.width = '100%';
                        iframe.style.height = '300px';
                        iframe.style.marginTop = '10px';
                        iframe.onload = function() {
                            try {
                                const iframeDoc = iframe.contentDocument || iframe.contentWindow.document;
                                const title = iframeDoc.title || 'No title';
                                const body = iframeDoc.body;
                                
                                if (body && body.innerHTML.trim() === '') {
                                    resultDiv.querySelector('.success, .warning').innerHTML += '<br>⚠️ Trang trống';
                                } else if (title) {
                                    resultDiv.querySelector('.success, .warning').innerHTML += `<br>📄 Title: \${title}`;
                                }
                            } catch (e) {
                                // Cross-origin restriction
                            }
                        };
                        resultDiv.appendChild(iframe);
                    }
                } else {
                    testStats.failed++;
                    resultDiv.innerHTML = `
                        <div class=\"error\">
                            ❌ Lỗi HTTP: \${response.status} - \${response.statusText}
                            <span class=\"response-time\">⏱️ \${responseTime}ms</span>
                        </div>
                    `;
                }
                
                resultDiv.classList.remove('loading');
                updateStats();
            })
            .catch(error => {
                const endTime = performance.now();
                const responseTime = Math.round(endTime - startTime);
                
                testStats.failed++;
                resultDiv.innerHTML = `
                    <div class=\"error\">
                        ❌ Lỗi kết nối: \${error.message}
                        <span class=\"response-time\">⏱️ \${responseTime}ms</span>
                    </div>
                `;
                resultDiv.classList.remove('loading');
                updateStats();
            });
        }
        
        function testAllPages() {
            testStats = { total: 0, passed: 0, failed: 0, warnings: 0, startTime: performance.now(), endTime: null };
            
            const tests = [
                ['customer/index.php', 'result-home'],
                ['customer/login.php', 'result-login'],
                ['customer/register.php', 'result-register'],
                ['customer/products.php', 'result-products'],
                ['customer/product_detail.php?id=1', 'result-detail'],
                ['customer/cart.php', 'result-cart'],
                ['customer/checkout.php', 'result-checkout'],
                ['customer/orders.php', 'result-orders']
            ];
            
            tests.forEach(([url, resultId], index) => {
                setTimeout(() => {
                    testPage(url, resultId, false); // Không show preview khi test all
                    
                    if (index === tests.length - 1) {
                        setTimeout(() => {
                            testStats.endTime = performance.now();
                            updateStats();
                            showTestSummary();
                        }, 1000);
                    }
                }, index * 800); // Delay 800ms giữa các test
            });
        }
        
        function testVNPayPages() {
            const vnpayTests = [
                ['vnpay/create_payment.php', 'result-vnpay-create'],
                ['vnpay/return.php', 'result-vnpay-return'],
                ['vnpay/check_status.php', 'result-vnpay-check']
            ];
            
            vnpayTests.forEach(([url, resultId], index) => {
                setTimeout(() => {
                    testPage(url, resultId, false);
                }, index * 1000);
            });
        }
        
        function showTestSummary() {
            const summaryDiv = document.getElementById('test-summary');
            if (summaryDiv) {
                let summaryHtml = '<h4>📊 Tóm tắt test:</h4>';
                summaryHtml += `<p>✅ Thành công: \${testStats.passed}/\${testStats.total}</p>`;
                summaryHtml += `<p>❌ Thất bại: \${testStats.failed}/\${testStats.total}</p>`;
                summaryHtml += `<p>⚠️ Cảnh báo: \${testStats.warnings}/\${testStats.total}</p>`;
                
                if (testStats.failed === 0) {
                    summaryHtml += '<div class=\"success\">🎉 Tất cả trang đều hoạt động tốt!</div>';
                } else {
                    summaryHtml += '<div class=\"error\">❌ Một số trang có vấn đề, cần kiểm tra!</div>';
                }
                
                summaryDiv.innerHTML = summaryHtml;
            }
        }
        
        function toggleSection(sectionId) {
            const section = document.getElementById(sectionId);
            section.classList.toggle('collapsed');
        }
        
        function copyToClipboard(text) {
            navigator.clipboard.writeText(text).then(() => {
                alert('Đã copy vào clipboard!');
            });
        }
        
        function exportTestResults() {
            const results = {
                timestamp: new Date().toISOString(),
                stats: testStats,
                browser: navigator.userAgent,
                url: window.location.href
            };
            
            const blob = new Blob([JSON.stringify(results, null, 2)], {type: 'application/json'});
            const url = URL.createObjectURL(blob);
            const a = document.createElement('a');
            a.href = url;
            a.download = 'tktshop_test_results.json';
            a.click();
        }
        
        // Auto-refresh stats every 2 seconds
        setInterval(updateStats, 2000);
    </script>
</head>
<body>";

echo "<div class='container'>";
echo "<div class='header'>";
echo "<h1>⚡ Quick Frontend Test - Enhanced</h1>";
echo "<p>Kiểm tra nhanh tất cả trang frontend của TKTShop với các tính năng nâng cao</p>";

// Real-time stats
echo "<div class='stats-grid'>";
echo "<div class='stat-card'>";
echo "<div class='stat-number' id='stat-total' style='color: #3498db;'>0</div>";
echo "<div>Total Tests</div>";
echo "</div>";
echo "<div class='stat-card'>";
echo "<div class='stat-number' id='stat-passed' style='color: #27ae60;'>0</div>";
echo "<div>Passed</div>";
echo "</div>";
echo "<div class='stat-card'>";
echo "<div class='stat-number' id='stat-failed' style='color: #e74c3c;'>0</div>";
echo "<div>Failed</div>";
echo "</div>";
echo "<div class='stat-card'>";
echo "<div class='stat-number' id='stat-success-rate' style='color: #f39c12;'>0%</div>";
echo "<div>Success Rate</div>";
echo "</div>";
echo "<div class='stat-card'>";
echo "<div class='stat-number' id='stat-duration' style='color: #9b59b6;'>0s</div>";
echo "<div>Duration</div>";
echo "</div>";
echo "</div>";

echo "<div class='progress-bar'>";
echo "<div class='progress-fill' style='width: 0%;'></div>";
echo "</div>";

echo "<div class='btn-group'>";
echo "<button class='test-button' onclick='testAllPages()' style='font-size: 16px; padding: 15px 30px;'>🚀 Test All Pages</button>";
echo "<button class='test-button' onclick='testVNPayPages()' style='background: #6f42c1;'>💰 Test VNPay</button>";
echo "<button class='test-button' onclick='exportTestResults()' style='background: #6c757d;'>📊 Export Results</button>";
echo "<button class='test-button' onclick='location.reload()' style='background: #17a2b8;'>🔄 Reset</button>";
echo "</div>";
echo "</div>";

// Server info section
echo "<div class='test-item' style='margin: 20px 0;'>";
echo "<h3 class='expandable' onclick='toggleSection(\"server-info\")'>🖥️ Server Information <span style='font-size: 0.8em;'>(Click to expand)</span></h3>";
echo "<div id='server-info'>";
echo "<table>";
echo "<tr><th>Property</th><th>Value</th></tr>";
echo "<tr><td>PHP Version</td><td>" . PHP_VERSION . "</td></tr>";
echo "<tr><td>Server Software</td><td>" . ($_SERVER['SERVER_SOFTWARE'] ?? 'Unknown') . "</td></tr>";
echo "<tr><td>Document Root</td><td>" . $_SERVER['DOCUMENT_ROOT'] . "</td></tr>";
echo "<tr><td>Current Directory</td><td>" . __DIR__ . "</td></tr>";
echo "<tr><td>Operating System</td><td>" . PHP_OS . "</td></tr>";
echo "<tr><td>Max Execution Time</td><td>" . ini_get('max_execution_time') . "s</td></tr>";
echo "<tr><td>Memory Limit</td><td>" . ini_get('memory_limit') . "</td></tr>";
echo "<tr><td>Upload Max Filesize</td><td>" . ini_get('upload_max_filesize') . "</td></tr>";
echo "<tr><td>Post Max Size</td><td>" . ini_get('post_max_size') . "</td></tr>";

// Check extensions
$extensions = ['pdo', 'pdo_mysql', 'mysqli', 'curl', 'json', 'mbstring'];
foreach ($extensions as $ext) {
    $status = extension_loaded($ext) ? 
        '<span class="status-indicator status-online"></span>Loaded' : 
        '<span class="status-indicator status-offline"></span>Not Loaded';
    echo "<tr><td>Extension: {$ext}</td><td>{$status}</td></tr>";
}

echo "</table>";
echo "</div>";
echo "</div>";

// Danh sách trang cần test
$pages = [
    'home' => [
        'title' => '🏠 Trang chủ',
        'url' => 'customer/index.php',
        'description' => 'Trang chủ website với sản phẩm nổi bật',
        'critical' => true
    ],
    'login' => [
        'title' => '🔐 Đăng nhập',
        'url' => 'customer/login.php',
        'description' => 'Trang đăng nhập khách hàng',
        'critical' => true
    ],
    'register' => [
        'title' => '📝 Đăng ký',
        'url' => 'customer/register.php', 
        'description' => 'Trang đăng ký tài khoản mới',
        'critical' => true
    ],
    'products' => [
        'title' => '🛍️ Sản phẩm',
        'url' => 'customer/products.php',
        'description' => 'Danh sách tất cả sản phẩm',
        'critical' => true
    ],
    'detail' => [
        'title' => '📋 Chi tiết sản phẩm',
        'url' => 'customer/product_detail.php?id=1',
        'description' => 'Trang chi tiết sản phẩm (cần có sản phẩm ID=1)',
        'critical' => false
    ],
    'cart' => [
        'title' => '🛒 Giỏ hàng',
        'url' => 'customer/cart.php',
        'description' => 'Trang giỏ hàng với AJAX',
        'critical' => false
    ],
    'checkout' => [
        'title' => '💳 Thanh toán',
        'url' => 'customer/checkout.php',
        'description' => 'Trang thanh toán đơn hàng',
        'critical' => true
    ],
    'orders' => [
        'title' => '📦 Đơn hàng',
        'url' => 'customer/orders.php',
        'description' => 'Theo dõi đơn hàng của khách',
        'critical' => false
    ]
];

echo "<h2 style='margin-top: 40px; color: #333;'>🎯 Frontend Pages Test</h2>";
echo "<div class='test-grid'>";

foreach ($pages as $key => $page) {
    $criticalBadge = $page['critical'] ? 
        '<span style="background: #dc3545; color: white; padding: 2px 6px; border-radius: 3px; font-size: 0.8em;">CRITICAL</span>' : 
        '<span style="background: #6c757d; color: white; padding: 2px 6px; border-radius: 3px; font-size: 0.8em;">OPTIONAL</span>';
    
    echo "<div class='test-item'>";
    echo "<h3>{$page['title']} {$criticalBadge}</h3>";
    echo "<p>{$page['description']}</p>";
    echo "<div class='btn-group'>";
    echo "<button class='test-button' onclick=\"testPage('{$page['url']}', 'result-{$key}')\">🧪 Test</button>";
    echo "<a href='{$page['url']}' target='_blank' class='test-button' style='background: #28a745;'>🔗 Open</a>";
    echo "<button class='test-button' style='background: #6c757d;' onclick=\"copyToClipboard('{$page['url']}')\">📋 Copy URL</button>";
    echo "</div>";
    echo "<div id='result-{$key}' class='test-result'>Nhấn Test để kiểm tra</div>";
    echo "</div>";
}

echo "</div>";

// Thêm test VNPay với enhanced features
echo "<h2 style='margin-top: 40px; color: #333;'>💰 VNPay Integration Test</h2>";
echo "<div class='test-grid'>";

$vnpayPages = [
    'create' => [
        'title' => '💳 Tạo thanh toán VNPay',
        'url' => 'vnpay/create_payment.php',
        'description' => 'Test tạo link thanh toán VNPay',
        'method' => 'GET'
    ],
    'return' => [
        'title' => '↩️ VNPay Return',
        'url' => 'vnpay/return.php',
        'description' => 'Xử lý callback từ VNPay',
        'method' => 'GET'
    ],
    'check' => [
        'title' => '🔍 Check Status',
        'url' => 'vnpay/check_status.php',
        'description' => 'Kiểm tra trạng thái giao dịch',
        'method' => 'GET'
    ]
];

foreach ($vnpayPages as $key => $page) {
    echo "<div class='test-item'>";
    echo "<h3>{$page['title']}</h3>";
    echo "<p>{$page['description']}</p>";
    echo "<p><small>Method: {$page['method']}</small></p>";
    echo "<div class='btn-group'>";
    echo "<button class='test-button' onclick=\"testPage('{$page['url']}', 'result-vnpay-{$key}')\">🧪 Test</button>";
    echo "<a href='{$page['url']}' target='_blank' class='test-button' style='background: #28a745;'>🔗 Open</a>";
    echo "</div>";
    echo "<div id='result-vnpay-{$key}' class='test-result'>Nhấn Test để kiểm tra</div>";
    echo "</div>";
}

echo "</div>";

// Enhanced Database Test Section
echo "<h2 style='margin-top: 40px; color: #333;'>🗄️ Database Connection Test</h2>";
echo "<div class='test-item' style='margin: 20px 0;'>";
echo "<h3>Test kết nối cơ sở dữ liệu</h3>";

// Test database connection với enhanced error handling
try {
    if (file_exists('config/database.php')) {
        echo "<div class='success'>✅ File config/database.php tồn tại</div>";
        
        // Include file database với error handling
        ob_start();
        $includeResult = include 'config/database.php';
        $output = ob_get_clean();
        
        if ($includeResult === false) {
            echo "<div class='error'>❌ Không thể include file database.php</div>";
        } else {
            echo "<div class='success'>✅ File database.php được include thành công</div>";
        }
        
        if (isset($pdo)) {
            try {
                $startTime = microtime(true);
                $stmt = $pdo->query("SELECT 1 as test");
                $endTime = microtime(true);
                $queryTime = round(($endTime - $startTime) * 1000, 2);
                
                echo "<div class='success'>✅ Kết nối database thành công! (Query time: {$queryTime}ms)</div>";
                
                // Get database info
                $dbVersion = $pdo->query("SELECT VERSION() as version")->fetch()['version'];
                echo "<div class='info'>📊 MySQL Version: {$dbVersion}</div>";
                
                // Test một số bảng cơ bản với enhanced checking
                $tables = ['users', 'products', 'categories', 'orders', 'colors', 'sizes', 'product_variants'];
                echo "<h4>📊 Kiểm tra bảng cơ sở dữ liệu:</h4>";
                echo "<table>";
                echo "<thead><tr><th>Table</th><th>Records</th><th>Status</th><th>Last Modified</th></tr></thead>";
                echo "<tbody>";
                
                foreach ($tables as $table) {
                    try {
                        $stmt = $pdo->query("SELECT COUNT(*) as count FROM `{$table}`");
                        $count = $stmt->fetchColumn();
                        
                        // Get table status
                        $statusStmt = $pdo->query("SHOW TABLE STATUS LIKE '{$table}'");
                        $tableStatus = $statusStmt->fetch(PDO::FETCH_ASSOC);
                        $updateTime = $tableStatus['Update_time'] ?? 'N/A';
                        
                        $statusClass = $count > 0 ? 'success' : 'warning';
                        $statusText = $count > 0 ? '✅ OK' : '⚠️ Empty';
                        
                        echo "<tr class='{$statusClass}'>";
                        echo "<td><strong>{$table}</strong></td>";
                        echo "<td>{$count}</td>";
                        echo "<td>{$statusText}</td>";
                        echo "<td>{$updateTime}</td>";
                        echo "</tr>";
                    } catch (Exception $e) {
                        echo "<tr class='error'>";
                        echo "<td><strong>{$table}</strong></td>";
                        echo "<td>-</td>";
                        echo "<td>❌ Error</td>";
                        echo "<td>" . htmlspecialchars($e->getMessage()) . "</td>";
                        echo "</tr>";
                    }
                }
                echo "</tbody></table>";
                
            } catch (Exception $e) {
                echo "<div class='error'>❌ Lỗi kết nối database: " . htmlspecialchars($e->getMessage()) . "</div>";
                echo "<div class='console'>Debug info: " . htmlspecialchars($e->getTraceAsString()) . "</div>";
            }
        } else {
            echo "<div class='error'>❌ Biến \$pdo không được tạo trong file database.php</div>";
            echo "<div class='warning'>💡 Kiểm tra xem file database.php có khởi tạo biến \$pdo không</div>";
        }
        
        if ($output) {
            echo "<div class='warning'>⚠️ Output từ database.php:</div>";
            echo "<div class='console'>" . htmlspecialchars($output) . "</div>";
        }
    } else {
        echo "<div class='error'>❌ File config/database.php không tồn tại</div>";
        echo "<div class='info'>💡 Hãy tạo file config/database.php với thông tin kết nối database</div>";
    }
} catch (Exception $e) {
    echo "<div class='error'>❌ Lỗi include database.php: " . htmlspecialchars($e->getMessage()) . "</div>";
    echo "<div class='console'>Error details: " . htmlspecialchars($e->getTraceAsString()) . "</div>";
}

echo "</div>";

// Test Summary Section
echo "<div id='test-summary' class='test-item' style='margin: 20px 0;'>";
echo "<h3>📋 Test Summary</h3>";
echo "<p>Chạy test để xem kết quả tổng hợp...</p>";
echo "</div>";

// Enhanced Common Errors Section
echo "<h2 style='margin-top: 40px; color: #333;'>🔧 Enhanced Error Guide</h2>";
echo "<div class='test-item'>";
echo "<h3>Các lỗi thường gặp và cách khắc phục nâng cao:</h3>";

$commonErrors = [
    "Fatal error: Uncaught Error: Class 'PDO' not found" => [
        "reason" => "Extension PDO MySQL chưa được cài đặt hoặc kích hoạt",
        "solution" => "Kiểm tra php.ini: extension=pdo_mysql hoặc cài đặt lại PHP với PDO support",
        "commands" => [
            "Windows (XAMPP/Laragon): Kiểm tra php.ini",
            "Ubuntu: sudo apt-get install php-mysql php-pdo",
            "CentOS: sudo yum install php-mysql php-pdo"
        ]
    ],
    "include '../config/database.php' failed" => [
        "reason" => "Đường dẫn include sai hoặc file không tồn tại",
        "solution" => "Sử dụng __DIR__ . '/../config/database.php' thay vì đường dẫn tương đối",
        "commands" => [
            "Correct: require_once __DIR__ . '/../config/database.php';",
            "Debug: echo __DIR__; để kiểm tra đường dẫn hiện tại"
        ]
    ],
    "Headers already sent by (output started at...)" => [
        "reason" => "Có output (echo, print, HTML) trước khi gọi header() hoặc setcookie()",
        "solution" => "Sử dụng ob_start() hoặc xóa tất cả output trước header()",
        "commands" => [
            "Thêm ob_start(); ở đầu file PHP",
            "Kiểm tra không có space hoặc text trước <?php",
            "Sử dụng ob_end_flush(); sau khi xử lý xong"
        ]
    ],
    "Undefined variable: \$pdo" => [
        "reason" => "File database.php không được include đúng hoặc \$pdo không được khởi tạo",
        "solution" => "Kiểm tra đường dẫn include và đảm bảo \$pdo được tạo trong database.php",
        "commands" => [
            "Kiểm tra: if(isset(\$pdo)) echo 'PDO OK'; else echo 'PDO not found';",
            "Debug: var_dump(\$pdo); để xem giá trị"
        ]
    ],
    "SQLSTATE[HY000] [1045] Access denied for user" => [
        "reason" => "Username, password hoặc database name sai trong config",
        "solution" => "Kiểm tra thông tin kết nối trong config/database.php",
        "commands" => [
            "Test connection: mysql -u username -p database_name",
            "Reset MySQL password: ALTER USER 'root'@'localhost' IDENTIFIED BY 'new_password';"
        ]
    ],
    "SQLSTATE[HY000] [2002] No such file or directory" => [
        "reason" => "MySQL server không chạy hoặc socket path sai",
        "solution" => "Khởi động MySQL server và kiểm tra host/port",
        "commands" => [
            "Start MySQL: sudo service mysql start",
            "Check status: sudo service mysql status",
            "XAMPP: Start MySQL từ control panel"
        ]
    ],
    "404 Not Found" => [
        "reason" => "File không tồn tại hoặc đường dẫn URL sai",
        "solution" => "Kiểm tra file tồn tại và đường dẫn URL chính xác",
        "commands" => [
            "Check file: if(file_exists('path/to/file.php')) echo 'File exists';",
            "Debug URL: echo \$_SERVER['REQUEST_URI'];"
        ]
    ],
    "500 Internal Server Error" => [
        "reason" => "Lỗi PHP syntax, permission hoặc configuration",
        "solution" => "Kiểm tra error log và file permissions",
        "commands" => [
            "Check syntax: php -l file.php",
            "Check permissions: ls -la file.php",
            "View error log: tail -f /var/log/apache2/error.log"
        ]
    ]
];

echo "<div style='margin: 20px 0;'>";
foreach ($commonErrors as $error => $info) {
    echo "<div class='expandable' onclick='toggleSection(\"error-" . md5($error) . "\")' style='background: #f8f9fa; padding: 15px; margin: 10px 0; border-radius: 8px; border-left: 4px solid #dc3545;'>";
    echo "<h4 style='margin: 0; color: #dc3545;'>❌ " . htmlspecialchars($error) . "</h4>";
    echo "<div id='error-" . md5($error) . "' class='collapsed' style='margin-top: 10px;'>";
    echo "<p><strong>🔍 Nguyên nhân:</strong> " . htmlspecialchars($info['reason']) . "</p>";
    echo "<p><strong>💡 Giải pháp:</strong> " . htmlspecialchars($info['solution']) . "</p>";
    if (isset($info['commands'])) {
        echo "<p><strong>🛠️ Commands:</strong></p>";
        echo "<div class='console'>";
        foreach ($info['commands'] as $cmd) {
            echo htmlspecialchars($cmd) . "<br>";
        }
        echo "</div>";
    }
    echo "</div>";
    echo "</div>";
}
echo "</div>";

echo "</div>";

// Advanced Diagnostics Section
echo "<h2 style='margin-top: 40px; color: #333;'>🔬 Advanced Diagnostics</h2>";
echo "<div class='test-item'>";
echo "<h3>System Health Check</h3>";

// PHP Configuration Check
echo "<h4>⚙️ PHP Configuration</h4>";
echo "<table>";
echo "<thead><tr><th>Setting</th><th>Current Value</th><th>Recommended</th><th>Status</th></tr></thead>";
echo "<tbody>";

$phpChecks = [
    'display_errors' => ['current' => ini_get('display_errors'), 'recommended' => 'On (Development)', 'check' => true],
    'error_reporting' => ['current' => error_reporting(), 'recommended' => 'E_ALL', 'check' => error_reporting() === E_ALL],
    'max_execution_time' => ['current' => ini_get('max_execution_time'), 'recommended' => '≥ 30', 'check' => ini_get('max_execution_time') >= 30],
    'memory_limit' => ['current' => ini_get('memory_limit'), 'recommended' => '≥ 128M', 'check' => true],
    'file_uploads' => ['current' => ini_get('file_uploads') ? 'On' : 'Off', 'recommended' => 'On', 'check' => ini_get('file_uploads')],
    'upload_max_filesize' => ['current' => ini_get('upload_max_filesize'), 'recommended' => '≥ 10M', 'check' => true],
    'post_max_size' => ['current' => ini_get('post_max_size'), 'recommended' => '≥ 10M', 'check' => true],
    'session.auto_start' => ['current' => ini_get('session.auto_start') ? 'On' : 'Off', 'recommended' => 'Off', 'check' => !ini_get('session.auto_start')]
];

foreach ($phpChecks as $setting => $info) {
    $statusClass = $info['check'] ? 'success' : 'error';
    $statusIcon = $info['check'] ? '✅' : '❌';
    
    echo "<tr class='{$statusClass}'>";
    echo "<td><strong>{$setting}</strong></td>";
    echo "<td><code>" . htmlspecialchars($info['current']) . "</code></td>";
    echo "<td><code>" . htmlspecialchars($info['recommended']) . "</code></td>";
    echo "<td>{$statusIcon}</td>";
    echo "</tr>";
}

echo "</tbody></table>";

// File Permissions Check
echo "<h4>🔒 File Permissions Check</h4>";
$pathsToCheck = [
    'uploads/' => 'Should be writable (755)',
    'uploads/products/' => 'Should be writable (755)',
    'uploads/categories/' => 'Should be writable (755)',
    'config/' => 'Should be readable (644)',
    'customer/' => 'Should be readable (644)',
    'admin/' => 'Should be readable (644)'
];

echo "<table>";
echo "<thead><tr><th>Path</th><th>Exists</th><th>Readable</th><th>Writable</th><th>Permissions</th><th>Recommendation</th></tr></thead>";
echo "<tbody>";

foreach ($pathsToCheck as $path => $recommendation) {
    $fullPath = __DIR__ . '/' . $path;
    $exists = file_exists($fullPath);
    $readable = $exists ? is_readable($fullPath) : false;
    $writable = $exists ? is_writable($fullPath) : false;
    $perms = $exists ? substr(sprintf('%o', fileperms($fullPath)), -3) : 'N/A';
    
    $statusClass = $exists && $readable ? 'success' : 'error';
    
    echo "<tr class='{$statusClass}'>";
    echo "<td><strong>{$path}</strong></td>";
    echo "<td>" . ($exists ? '✅' : '❌') . "</td>";
    echo "<td>" . ($readable ? '✅' : '❌') . "</td>";
    echo "<td>" . ($writable ? '✅' : '❌') . "</td>";
    echo "<td><code>{$perms}</code></td>";
    echo "<td><small>{$recommendation}</small></td>";
    echo "</tr>";
}

echo "</tbody></table>";

// Network Connectivity Test
echo "<h4>🌐 Network & Connectivity</h4>";
echo "<div id='network-test'>";
echo "<button class='test-button' onclick='testNetworkConnectivity()'>🔍 Test Network</button>";
echo "<div id='network-results'></div>";
echo "</div>";

echo "</div>";

// Enhanced Checklist
echo "<h2 style='margin-top: 40px; color: #333;'>✅ Enhanced Pre-Test Checklist</h2>";
echo "<div class='test-item'>";
echo "<h3>Trước khi test, hãy đảm bảo:</h3>";

$checklist = [
    'database' => 'Database đã được import từ file database.sql',
    'config' => 'File config/database.php có thông tin kết nối đúng',
    'permissions' => 'Thư mục uploads/ có quyền write (755)',
    'server' => 'Apache/Nginx đang chạy và có thể truy cập localhost',
    'php' => 'PHP version >= 7.4 và có extension PDO, mysqli',
    'syntax' => 'Không có file nào bị syntax error',
    'ssl' => 'HTTPS được cấu hình (nếu cần)',
    'cache' => 'Cache được clear (nếu có)',
    'logs' => 'Error logs được bật để debug'
];

echo "<div style='background: #e3f2fd; padding: 20px; border-radius: 8px; margin: 15px 0;'>";
echo "<div class='stats-grid'>";

foreach ($checklist as $key => $item) {
    echo "<div class='stat-card' style='padding: 15px;'>";
    echo "<input type='checkbox' id='check-{$key}' style='margin-bottom: 10px;'>";
    echo "<label for='check-{$key}'><strong>{$item}</strong></label>";
    echo "</div>";
}

echo "</div>";
echo "</div>";

echo "<h4>🚀 Quick Setup Commands:</h4>";
echo "<div class='console'>";
echo "# Import database (MySQL)<br>";
echo "mysql -u root -p tktshop < database.sql<br><br>";

echo "# Import database (PHPMyAdmin)<br>";
echo "1. Mở http://localhost/phpmyadmin<br>";
echo "2. Tạo database 'tktshop'<br>";
echo "3. Import file database.sql<br><br>";

echo "# Fix permissions (Linux/Mac)<br>";
echo "chmod 755 uploads/<br>";
echo "chmod 755 uploads/products/<br>";
echo "chmod 755 uploads/categories/<br>";
echo "chmod 644 config/*<br><br>";

echo "# Fix permissions (Windows)<br>";
echo "Right-click -> Properties -> Security -> Edit permissions<br><br>";

echo "# Check PHP extensions<br>";
echo "php -m | grep -i pdo<br>";
echo "php -m | grep -i mysql<br>";
echo "php -m | grep -i curl<br><br>";

echo "# Test PHP syntax<br>";
echo "find . -name '*.php' -exec php -l {} \\;<br><br>";

echo "# Start services (Ubuntu)<br>";
echo "sudo service apache2 start<br>";
echo "sudo service mysql start<br><br>";

echo "# Start services (Windows XAMPP)<br>";
echo "Start Apache and MySQL from XAMPP Control Panel<br>";
echo "</div>";

echo "</div>";

// Network connectivity test function
echo "<script>";
echo "function testNetworkConnectivity() {";
echo "    const resultsDiv = document.getElementById('network-results');";
echo "    resultsDiv.innerHTML = '<div class=\"warning\">⏳ Testing network connectivity...</div>';";
echo "    ";
echo "    const tests = [";
echo "        { name: 'Local Server', url: 'http://localhost/' },";
echo "        { name: 'Google DNS', url: 'https://8.8.8.8/' },";
echo "        { name: 'External API', url: 'https://api.github.com/' }";
echo "    ];";
echo "    ";
echo "    Promise.allSettled(tests.map(test => ";
echo "        fetch(test.url, { mode: 'no-cors' })";
echo "            .then(() => ({ name: test.name, status: 'success' }))";
echo "            .catch(() => ({ name: test.name, status: 'failed' }))";
echo "    )).then(results => {";
echo "        let html = '<h5>Network Test Results:</h5>';";
echo "        results.forEach((result, index) => {";
echo "            const test = tests[index];";
echo "            const status = result.status === 'fulfilled' ? 'success' : 'error';";
echo "            const icon = result.status === 'fulfilled' ? '✅' : '❌';";
echo "            html += `<div class=\"\${status}\">\${icon} \${test.name}: \${result.status === 'fulfilled' ? 'OK' : 'Failed'}</div>`;";
echo "        });";
echo "        resultsDiv.innerHTML = html;";
echo "    });";
echo "}";
echo "</script>";

// Footer với enhanced links
echo "<div style='text-align: center; margin: 40px 0; padding: 20px; background: linear-gradient(135deg, #f8f9fa, #e9ecef); border-radius: 10px; box-shadow: 0 3px 10px rgba(0,0,0,0.1);'>";
echo "<h3>🔗 Quick Navigation</h3>";
echo "<div style='margin: 20px 0;'>";
echo "<a href='customer/index.php' class='test-button' style='margin: 5px;'>🏠 Homepage</a>";
echo "<a href='admin/colors/index.php' class='test-button' style='margin: 5px;'>🔧 Admin Panel</a>";
echo "<a href='debug.php' class='test-button' style='margin: 5px; background: #6c757d;'>🔍 Full Debug</a>";
echo "<a href='frontend_debug.php' class='test-button' style='margin: 5px; background: #e74c3c;'>🎯 Path Debug</a>";
echo "<a href='?export=json' class='test-button' style='margin: 5px; background: #17a2b8;'>📊 Export Config</a>";
echo "</div>";
echo "<div style='margin: 15px 0;'>";
echo "<p style='color: #666;'><strong>Quick Actions:</strong></p>";
echo "<button class='test-button' onclick='testAllPages()' style='margin: 3px; padding: 8px 15px;'>🚀 Run All Tests</button>";
echo "<button class='test-button' onclick='location.reload()' style='margin: 3px; padding: 8px 15px; background: #6c757d;'>🔄 Refresh</button>";
echo "<button class='test-button' onclick='exportTestResults()' style='margin: 3px; padding: 8px 15px; background: #28a745;'>💾 Save Results</button>";
echo "</div>";
echo "<p style='color: #666; margin-top: 20px;'>⏰ Enhanced test completed at " . date('d/m/Y H:i:s') . "</p>";
echo "<p style='color: #888; font-size: 0.9em;'>🖥️ User Agent: " . ($_SERVER['HTTP_USER_AGENT'] ?? 'Unknown') . "</p>";
echo "</div>";

echo "</div>"; // Close container
echo "</body></html>";

// Export configuration if requested
if (isset($_GET['export']) && $_GET['export'] === 'json') {
    header('Content-Type: application/json');
    header('Content-Disposition: attachment; filename="tktshop_config.json"');
    
    $config = [
        'timestamp' => date('c'),
        'php_version' => PHP_VERSION,
        'server_software' => $_SERVER['SERVER_SOFTWARE'] ?? 'Unknown',
        'document_root' => $_SERVER['DOCUMENT_ROOT'],
        'current_directory' => __DIR__,
        'extensions' => get_loaded_extensions(),
        'ini_settings' => [
            'max_execution_time' => ini_get('max_execution_time'),
            'memory_limit' => ini_get('memory_limit'),
            'upload_max_filesize' => ini_get('upload_max_filesize'),
            'post_max_size' => ini_get('post_max_size'),
            'display_errors' => ini_get('display_errors'),
            'error_reporting' => error_reporting()
        ]
    ];
    
    echo json_encode($config, JSON_PRETTY_PRINT);
    exit;
}
?>