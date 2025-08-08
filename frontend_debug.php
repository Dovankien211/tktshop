<?php
/**
 * FRONTEND DEBUG TOOL - HOÀN CHỈNH
 * Phát hiện chính xác lỗi đường dẫn include/require trong frontend TKTShop
 * File: frontend_debug.php
 */

error_reporting(E_ALL);
ini_set('display_errors', 1);

echo "<!DOCTYPE html>
<html lang='vi'>
<head>
    <meta charset='UTF-8'>
    <meta name='viewport' content='width=device-width, initial-scale=1.0'>
    <title>Frontend Debug Tool - TKTShop</title>
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif; background: #f8f9fa; color: #333; line-height: 1.6; }
        .container { max-width: 1400px; margin: 0 auto; padding: 20px; }
        .header { background: linear-gradient(135deg, #e74c3c 0%, #c0392b 100%); color: white; padding: 30px; border-radius: 15px; margin-bottom: 30px; text-align: center; box-shadow: 0 10px 30px rgba(0,0,0,0.1); }
        .header h1 { font-size: 2.5em; margin-bottom: 10px; }
        .section { background: white; margin: 20px 0; border-radius: 12px; overflow: hidden; box-shadow: 0 5px 15px rgba(0,0,0,0.08); }
        .section-title { background: #34495e; color: white; padding: 15px 25px; font-size: 1.3em; font-weight: 600; }
        .section-content { padding: 25px; }
        .file-analysis { background: #f8f9fa; border-left: 4px solid #3498db; padding: 20px; margin: 15px 0; border-radius: 5px; }
        .path-error { background: #ffebee; border-left: 4px solid #f44336; padding: 15px; margin: 10px 0; border-radius: 5px; }
        .path-success { background: #e8f5e8; border-left: 4px solid #4caf50; padding: 15px; margin: 10px 0; border-radius: 5px; }
        .path-warning { background: #fff3e0; border-left: 4px solid #ff9800; padding: 15px; margin: 10px 0; border-radius: 5px; }
        .code-block { background: #2d3748; color: #e2e8f0; padding: 15px; border-radius: 8px; font-family: 'Courier New', monospace; font-size: 14px; overflow-x: auto; margin: 10px 0; }
        .fix-suggestion { background: #e3f2fd; border: 1px solid #2196f3; padding: 20px; border-radius: 8px; margin: 15px 0; }
        .status-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(300px, 1fr)); gap: 15px; margin: 20px 0; }
        .status-item { background: #f8f9fa; border: 1px solid #dee2e6; border-radius: 8px; padding: 20px; }
        .error { color: #dc3545; font-weight: bold; }
        .success { color: #28a745; font-weight: bold; }
        .warning { color: #ffc107; font-weight: bold; }
        table { width: 100%; border-collapse: collapse; margin: 15px 0; }
        th, td { padding: 12px; text-align: left; border-bottom: 1px solid #ddd; }
        th { background: #f8f9fa; font-weight: 600; }
        .highlight { background: #fff3cd; padding: 2px 6px; border-radius: 3px; }
        .summary-stats { display: grid; grid-template-columns: repeat(auto-fit, minmax(150px, 1fr)); gap: 15px; margin: 20px 0; }
        .stat-card { background: white; padding: 20px; border-radius: 10px; text-align: center; box-shadow: 0 3px 10px rgba(0,0,0,0.1); }
        .stat-number { font-size: 2em; font-weight: bold; margin-bottom: 5px; }
        .btn { display: inline-block; padding: 10px 20px; background: #007bff; color: white; text-decoration: none; border-radius: 5px; margin: 5px; }
        .btn:hover { background: #0056b3; }
        .btn-success { background: #28a745; }
        .btn-danger { background: #dc3545; }
        .btn-warning { background: #ffc107; color: #212529; }
    </style>
</head>
<body>";

class FrontendPathDebugger {
    private $baseDir;
    private $errors = [];
    private $warnings = [];
    private $fixes = [];
    private $totalFiles = 0;
    private $totalIssues = 0;
    private $scannedFiles = [];

    public function __construct() {
        $this->baseDir = dirname(__FILE__);
        echo "<div class='container'>";
        echo "<div class='header'>";
        echo "<h1>🔍 Frontend Path Debugger</h1>";
        echo "<p>Phát hiện và sửa lỗi đường dẫn include/require trong frontend TKTShop</p>";
        echo "<p><strong>Thư mục gốc:</strong> " . $this->baseDir . "</p>";
        echo "<div style='margin-top: 20px;'>";
        echo "<a href='quick_test.php' class='btn btn-success'>⚡ Quick Test</a>";
        echo "<a href='debug.php' class='btn'>🔍 Full Debug</a>";
        echo "<a href='customer/index.php' class='btn btn-warning'>🏠 Homepage</a>";
        echo "</div>";
        echo "</div>";
    }

    // Lấy tất cả file frontend
    private function getFrontendFiles() {
        $frontendFiles = [];
        
        $directories = [
            'customer' => $this->baseDir . '/customer',
            'customer/includes' => $this->baseDir . '/customer/includes',
            'vnpay' => $this->baseDir . '/vnpay'
        ];
        
        foreach ($directories as $category => $dir) {
            if (is_dir($dir)) {
                $files = glob($dir . '/*.php');
                foreach ($files as $file) {
                    $frontendFiles[$category][] = $file;
                    $this->totalFiles++;
                }
            }
        }
        
        return $frontendFiles;
    }

    // Hiển thị thống kê tổng quan
    public function showOverview() {
        echo "<div class='section'>";
        echo "<div class='section-title'>📊 Tổng quan Frontend Files</div>";
        echo "<div class='section-content'>";
        
        $frontendFiles = $this->getFrontendFiles();
        
        echo "<div class='summary-stats'>";
        echo "<div class='stat-card'>";
        echo "<div class='stat-number' style='color: #3498db;'>{$this->totalFiles}</div>";
        echo "<div>Total Files</div>";
        echo "</div>";
        
        $existingFiles = 0;
        $missingFiles = 0;
        
        foreach ($frontendFiles as $category => $files) {
            foreach ($files as $file) {
                if (file_exists($file)) {
                    $existingFiles++;
                } else {
                    $missingFiles++;
                }
            }
        }
        
        echo "<div class='stat-card'>";
        echo "<div class='stat-number' style='color: #27ae60;'>{$existingFiles}</div>";
        echo "<div>Existing Files</div>";
        echo "</div>";
        
        echo "<div class='stat-card'>";
        echo "<div class='stat-number' style='color: #e74c3c;'>{$missingFiles}</div>";
        echo "<div>Missing Files</div>";
        echo "</div>";
        
        echo "<div class='stat-card'>";
        echo "<div class='stat-number' style='color: #f39c12;'>" . count($frontendFiles) . "</div>";
        echo "<div>Categories</div>";
        echo "</div>";
        echo "</div>";
        
        // Hiển thị chi tiết từng category
        foreach ($frontendFiles as $category => $files) {
            echo "<h4>📁 " . ucfirst($category) . " (" . count($files) . " files)</h4>";
            echo "<div class='status-grid'>";
            
            foreach ($files as $file) {
                $fileName = basename($file);
                $fileSize = file_exists($file) ? $this->formatBytes(filesize($file)) : 'N/A';
                $status = file_exists($file) ? 'exists' : 'missing';
                $statusClass = file_exists($file) ? 'success' : 'error';
                $statusText = file_exists($file) ? '✅ Exists' : '❌ Missing';
                
                echo "<div class='status-item'>";
                echo "<h5>{$fileName}</h5>";
                echo "<p class='{$statusClass}'>{$statusText}</p>";
                if (file_exists($file)) {
                    echo "<p>Size: {$fileSize}</p>";
                    echo "<p>Modified: " . date('d/m/Y H:i', filemtime($file)) . "</p>";
                }
                echo "</div>";
            }
            echo "</div>";
        }
        
        echo "</div>";
        echo "</div>";
    }

    // Phân tích đường dẫn chi tiết
    public function analyzeFrontendPaths() {
        echo "<div class='section'>";
        echo "<div class='section-title'>🔍 Phân tích đường dẫn Include/Require</div>";
        echo "<div class='section-content'>";
        
        $frontendFiles = $this->getFrontendFiles();
        $this->totalIssues = 0;
        
        foreach ($frontendFiles as $category => $files) {
            echo "<h3>📂 " . ucfirst($category) . " Analysis</h3>";
            
            if (empty($files)) {
                echo "<div class='path-warning'>⚠️ Không tìm thấy file nào trong thư mục {$category}</div>";
                continue;
            }
            
            foreach ($files as $file) {
                if (!file_exists($file)) {
                    echo "<div class='path-error'>❌ File không tồn tại: " . basename($file) . "</div>";
                    continue;
                }
                
                $issues = $this->analyzeFile($file);
                $this->totalIssues += count($issues);
                
                $fileName = basename($file);
                $relativePath = str_replace($this->baseDir . '/', '', $file);
                
                echo "<div class='file-analysis'>";
                echo "<h4>📄 {$fileName}</h4>";
                echo "<p><code>{$relativePath}</code></p>";
                echo "<p><strong>Size:</strong> " . $this->formatBytes(filesize($file)) . " | ";
                echo "<strong>Modified:</strong> " . date('d/m/Y H:i:s', filemtime($file)) . "</p>";
                
                if (empty($issues)) {
                    echo "<div class='path-success'>✅ Không phát hiện lỗi đường dẫn</div>";
                } else {
                    echo "<h5>🚨 Phát hiện " . count($issues) . " vấn đề:</h5>";
                    foreach ($issues as $issue) {
                        echo "<div class='path-error'>";
                        echo "<strong>❌ {$issue['type']}:</strong> {$issue['message']}<br>";
                        echo "<strong>📍 Dòng {$issue['line']}:</strong> <code class='highlight'>" . htmlspecialchars($issue['code']) . "</code><br>";
                        if (isset($issue['resolved_path'])) {
                            echo "<strong>🔍 Resolved to:</strong> <code>" . htmlspecialchars($issue['resolved_path']) . "</code><br>";
                        }
                        if (isset($issue['suggestion'])) {
                            echo "<strong>💡 Đề xuất:</strong> <span style='color: #28a745;'>" . htmlspecialchars($issue['suggestion']) . "</span>";
                        }
                        echo "</div>";
                    }
                }
                
                // Hiển thị thống kê file
                $this->showFileStats($file);
                echo "</div>";
            }
        }
        
        echo "<div class='fix-suggestion'>";
        echo "<h3>📊 Tổng kết phân tích:</h3>";
        echo "<p>🔍 <strong>Tổng số file được scan:</strong> {$this->totalFiles}</p>";
        echo "<p>🚨 <strong>Tổng số vấn đề phát hiện:</strong> {$this->totalIssues}</p>";
        if ($this->totalIssues > 0) {
            echo "<p>⚠️ <strong>Mức độ nghiêm trọng:</strong> " . $this->getSeverityLevel() . "</p>";
        } else {
            echo "<p>✅ <strong>Trạng thái:</strong> <span style='color: #28a745;'>Tất cả đường dẫn đều hợp lệ!</span></p>";
        }
        echo "</div>";
        
        echo "</div>";
        echo "</div>";
    }

    // Phân tích từng file chi tiết
    private function analyzeFile($filePath) {
        $issues = [];
        
        if (!file_exists($filePath)) {
            return [['type' => 'File Error', 'message' => 'File không tồn tại', 'line' => 0, 'code' => '']];
        }
        
        $content = file_get_contents($filePath);
        $lines = explode("\n", $content);
        
        foreach ($lines as $lineNum => $line) {
            $lineNum++; // Bắt đầu từ dòng 1
            
            // Tìm include/require
            if (preg_match('/(include|require)(_once)?\s*[\(\s]*[\'\"](.*?)[\'\"]/', $line, $matches)) {
                $includePath = $matches[3];
                $includeType = $matches[1] . (isset($matches[2]) ? $matches[2] : '');
                
                // Tính toán đường dẫn tuyệt đối
                $baseDir = dirname($filePath);
                $absolutePath = $this->resolvePath($baseDir, $includePath);
                
                if (!file_exists($absolutePath)) {
                    $suggestion = $this->suggestCorrectPath($filePath, $includePath);
                    $issues[] = [
                        'type' => 'Include Error',
                        'message' => "File include không tồn tại: {$includePath}",
                        'line' => $lineNum,
                        'code' => trim($line),
                        'resolved_path' => $absolutePath,
                        'suggestion' => $suggestion
                    ];
                }
            }
            
            // Tìm các đường dẫn CSS/JS
            if (preg_match_all('/(?:href|src)\s*=\s*[\'\"](.*?\.(?:css|js))[\'\"]/i', $line, $matches)) {
                foreach ($matches[1] as $path) {
                    if (!$this->isExternalUrl($path)) {
                        $baseDir = dirname($filePath);
                        $absolutePath = $this->resolvePath($baseDir, $path);
                        
                        if (!file_exists($absolutePath)) {
                            $issues[] = [
                                'type' => 'CSS/JS Path Error',
                                'message' => "File CSS/JS không tồn tại: {$path}",
                                'line' => $lineNum,
                                'code' => trim($line),
                                'resolved_path' => $absolutePath
                            ];
                        }
                    }
                }
            }
            
            // Tìm đường dẫn ảnh
            if (preg_match_all('/(?:src|href)\s*=\s*[\'\"](.*?\.(?:png|jpg|jpeg|gif|svg|ico))[\'\"]/i', $line, $matches)) {
                foreach ($matches[1] as $path) {
                    if (!$this->isExternalUrl($path)) {
                        $baseDir = dirname($filePath);
                        $absolutePath = $this->resolvePath($baseDir, $path);
                        
                        if (!file_exists($absolutePath)) {
                            $issues[] = [
                                'type' => 'Image Path Error',
                                'message' => "File ảnh không tồn tại: {$path}",
                                'line' => $lineNum,
                                'code' => trim($line),
                                'resolved_path' => $absolutePath
                            ];
                        }
                    }
                }
            }
            
            // Kiểm tra action trong form
            if (preg_match('/action\s*=\s*[\'\"](.*?)[\'\"]/i', $line, $matches)) {
                $actionPath = $matches[1];
                if (strpos($actionPath, '.php') !== false && !$this->isExternalUrl($actionPath)) {
                    $baseDir = dirname($filePath);
                    $absolutePath = $this->resolvePath($baseDir, $actionPath);
                    
                    if (!file_exists($absolutePath)) {
                        $issues[] = [
                            'type' => 'Form Action Error',
                            'message' => "File action form không tồn tại: {$actionPath}",
                            'line' => $lineNum,
                            'code' => trim($line),
                            'resolved_path' => $absolutePath
                        ];
                    }
                }
            }
            
            // Kiểm tra redirect trong PHP
            if (preg_match('/(?:header|Location:)\s*.*?[\'\"](.*?\.php.*?)[\'\"]/i', $line, $matches)) {
                $redirectPath = $matches[1];
                if (!$this->isExternalUrl($redirectPath)) {
                    $baseDir = dirname($filePath);
                    $absolutePath = $this->resolvePath($baseDir, $redirectPath);
                    
                    if (!file_exists($absolutePath)) {
                        $issues[] = [
                            'type' => 'Redirect Path Error',
                            'message' => "File redirect không tồn tại: {$redirectPath}",
                            'line' => $lineNum,
                            'code' => trim($line),
                            'resolved_path' => $absolutePath
                        ];
                    }
                }
            }
        }
        
        $this->scannedFiles[] = $filePath;
        return $issues;
    }

    // Hiển thị thống kê file
    private function showFileStats($filePath) {
        $content = file_get_contents($filePath);
        $lines = substr_count($content, "\n") + 1;
        $includeCount = preg_match_all('/(include|require)(_once)?/', $content);
        $formCount = preg_match_all('/<form/i', $content);
        $jsCount = preg_match_all('/\.js[\'\"]/i', $content);
        $cssCount = preg_match_all('/\.css[\'\"]/i', $content);
        
        echo "<div style='background: #f8f9fa; padding: 10px; border-radius: 5px; margin: 10px 0; font-size: 0.9em;'>";
        echo "<strong>📈 File Statistics:</strong> ";
        echo "Lines: {$lines} | ";
        echo "Includes: {$includeCount} | ";
        echo "Forms: {$formCount} | ";
        echo "JS files: {$jsCount} | ";
        echo "CSS files: {$cssCount}";
        echo "</div>";
    }

    // Kiểm tra URL external
    private function isExternalUrl($url) {
        return preg_match('/^https?:\/\//', $url) || preg_match('/^\/\//', $url);
    }

    // Resolve đường dẫn tương đối thành tuyệt đối
    private function resolvePath($baseDir, $relativePath) {
        // Nếu đường dẫn bắt đầu bằng / thì tính từ document root
        if (strpos($relativePath, '/') === 0) {
            return $_SERVER['DOCUMENT_ROOT'] . $relativePath;
        }
        
        // Xử lý đường dẫn tương đối
        $absolutePath = $baseDir . '/' . $relativePath;
        
        // Normalize path
        $parts = explode('/', str_replace('\\', '/', $absolutePath));
        $normalized = [];
        
        foreach ($parts as $part) {
            if ($part === '..') {
                array_pop($normalized);
            } elseif ($part !== '.' && $part !== '') {
                $normalized[] = $part;
            }
        }
        
        return implode('/', $normalized);
    }

    // Đề xuất đường dẫn đúng
    private function suggestCorrectPath($currentFile, $wrongPath) {
        $fileName = basename($wrongPath);
        $currentDir = dirname($currentFile);
        
        // Tìm file trong project
        $foundFiles = $this->findFileInProject($fileName);
        
        if (empty($foundFiles)) {
            return "❌ File {$fileName} không tồn tại trong project";
        }
        
        // Tính đường dẫn tương đối từ file hiện tại
        $suggestions = [];
        foreach ($foundFiles as $foundFile) {
            $relativePath = $this->calculateRelativePath($currentDir, $foundFile);
            $suggestions[] = $relativePath;
        }
        
        return "✅ Có thể sử dụng: " . implode(' hoặc ', array_slice($suggestions, 0, 3));
    }

    // Tìm file trong project
    private function findFileInProject($fileName) {
        $foundFiles = [];
        
        try {
            $iterator = new RecursiveIteratorIterator(
                new RecursiveDirectoryIterator($this->baseDir, RecursiveDirectoryIterator::SKIP_DOTS)
            );
            
            foreach ($iterator as $file) {
                if ($file->isFile() && $file->getFilename() === $fileName) {
                    $foundFiles[] = $file->getPathname();
                }
            }
        } catch (Exception $e) {
            // Ignore errors
        }
        
        return $foundFiles;
    }

    // Tính đường dẫn tương đối
    private function calculateRelativePath($from, $to) {
        $from = rtrim(str_replace('\\', '/', $from), '/');
        $to = str_replace('\\', '/', dirname($to));
        
        $fromParts = explode('/', $from);
        $toParts = explode('/', $to);
        
        // Tìm phần chung
        $commonLength = 0;
        $minLength = min(count($fromParts), count($toParts));
        
        for ($i = 0; $i < $minLength; $i++) {
            if ($fromParts[$i] === $toParts[$i]) {
                $commonLength++;
            } else {
                break;
            }
        }
        
        // Tính số bước lùi
        $upSteps = count($fromParts) - $commonLength;
        $downSteps = array_slice($toParts, $commonLength);
        
        $relativePath = str_repeat('../', $upSteps) . implode('/', $downSteps);
        if ($relativePath === '') {
            $relativePath = '.';
        }
        
        return rtrim($relativePath, '/') . '/' . basename($to);
    }

    // Lấy mức độ nghiêm trọng
    private function getSeverityLevel() {
        if ($this->totalIssues === 0) return "✅ Không có vấn đề";
        if ($this->totalIssues <= 5) return "🟡 Nhẹ";
        if ($this->totalIssues <= 15) return "🟠 Trung bình";
        return "🔴 Nghiêm trọng";
    }

    // Tạo file fix gợi ý
    public function generateFixFiles() {
        echo "<div class='section'>";
        echo "<div class='section-title'>🔧 Hướng dẫn sửa lỗi</div>";
        echo "<div class='section-content'>";
        
        if ($this->totalIssues > 0) {
            echo "<div class='path-error'>";
            echo "<h3>⚠️ Cần sửa {$this->totalIssues} vấn đề được phát hiện</h3>";
            echo "</div>";
        }
        
        echo "<h3>📝 File config/paths.php (Đề xuất tạo mới):</h3>";
        echo "<div class='code-block'>";
        echo htmlspecialchars("<?php
// File: config/paths.php - Định nghĩa đường dẫn chuẩn
if (!defined('ROOT_PATH')) {
    define('ROOT_PATH', dirname(__DIR__));
    define('CONFIG_PATH', ROOT_PATH . '/config');
    define('ADMIN_PATH', ROOT_PATH . '/admin');
    define('CUSTOMER_PATH', ROOT_PATH . '/customer');
    define('UPLOADS_PATH', ROOT_PATH . '/uploads');
    define('VNPAY_PATH', ROOT_PATH . '/vnpay');
    define('ASSETS_PATH', ROOT_PATH . '/assets');
    
    // URL constants
    define('BASE_URL', 'http://localhost/tktshop');
    define('ADMIN_URL', BASE_URL . '/admin');
    define('CUSTOMER_URL', BASE_URL . '/customer');
    define('UPLOADS_URL', BASE_URL . '/uploads');
    define('ASSETS_URL', BASE_URL . '/assets');
}

// Helper functions
function safe_include(\$file) {
    if (file_exists(\$file)) {
        return include \$file;
    }
    throw new Exception('File not found: ' . \$file);
}

function safe_require(\$file) {
    if (file_exists(\$file)) {
        return require \$file;
    }
    throw new Exception('Required file not found: ' . \$file);
}

function get_asset_url(\$path) {
    return ASSETS_URL . '/' . ltrim(\$path, '/');
}

function get_upload_url(\$path) {
    return UPLOADS_URL . '/' . ltrim(\$path, '/');
}
?>");
        echo "</div>";
        
        echo "<h3>📝 Cách sử dụng trong file frontend:</h3>";
        echo "<div class='code-block'>";
        echo htmlspecialchars("<?php
// TRƯỚC (có thể lỗi):
include '../config/database.php';
include '../../config/config.php';

// SAU (an toàn):
require_once __DIR__ . '/../config/paths.php';
safe_require(CONFIG_PATH . '/database.php');
safe_require(CONFIG_PATH . '/config.php');

// Cho HTML:
// TRƯỚC: <link href=\"../assets/css/style.css\">
// SAU: <link href=\"<?= get_asset_url('css/style.css') ?>\">
?>");
        echo "</div>";
        
        echo "<h3>🛠️ Script tự động sửa lỗi:</h3>";
        echo "<div class='code-block'>";
        echo htmlspecialchars("#!/bin/bash
# auto_fix_paths.sh - Script tự động sửa đường dẫn

# Backup files trước khi sửa
mkdir -p backup_" . date('Y-m-d') . "
cp -r customer/ backup_" . date('Y-m-d') . "/
cp -r vnpay/ backup_" . date('Y-m-d') . "/

# Thay thế include paths
find customer/ vnpay/ -name '*.php' -exec sed -i.bak 's|include.*config/database.php|require_once __DIR__ . \"/../config/paths.php\"; safe_require(CONFIG_PATH . \"/database.php\")|g' {} \;
find customer/ vnpay/ -name '*.php' -exec sed -i.bak 's|require.*config/config.php|safe_require(CONFIG_PATH . \"/config.php\")|g' {} \;

echo \"✅ Đã sửa xong! Backup được lưu trong backup_" . date('Y-m-d') . "/\"");
        echo "</div>";
        
        echo "<div class='fix-suggestion'>";
        echo "<h3>🎯 Action Plan:</h3>";
        echo "<ol>";
        echo "<li><strong>Tạo file config/paths.php</strong> với nội dung trên</li>";
        echo "<li><strong>Backup files hiện tại</strong> trước khi sửa</li>";
        echo "<li><strong>Thay thế từng file</strong> theo hướng dẫn</li>";
        echo "<li><strong>Test lại</strong> bằng Quick Test</li>";
        echo "<li><strong>Kiểm tra lại</strong> bằng Frontend Debug</li>";
        echo "</ol>";
        echo "</div>";
        
        echo "</div>";
        echo "</div>";
    }

    // Format bytes
    private function formatBytes($size, $precision = 2) {
        $units = ['B', 'KB', 'MB', 'GB'];
        
        for ($i = 0; $size >= 1024 && $i < count($units) - 1; $i++) {
            $size /= 1024;
        }
        
        return round($size, $precision) . ' ' . $units[$i];
    }

    // Kiểm tra cấu hình include_path
    public function checkIncludePath() {
        echo "<div class='section'>";
        echo "<div class='section-title'>⚙️ Kiểm tra cấu hình Include Path</div>";
        echo "<div class='section-content'>";
        
        $includePath = get_include_path();
        echo "<p><strong>Include Path hiện tại:</strong></p>";
        echo "<div class='code-block'>" . htmlspecialchars($includePath) . "</div>";
        
        // Kiểm tra các đường dẫn trong include_path
        $paths = explode(PATH_SEPARATOR, $includePath);
        echo "<h4>📁 Phân tích các đường dẫn:</h4>";
        
        foreach ($paths as $path) {
            if (is_dir($path)) {
                echo "<div class='path-success'>✅ {$path} - Tồn tại và có thể truy cập</div>";
            } else {
                echo "<div class='path-error'>❌ {$path} - Không tồn tại hoặc không thể truy cập</div>";
            }
        }
        
        // Kiểm tra current working directory
        echo "<h4>📍 Current Working Directory:</h4>";
        echo "<div class='code-block'>" . getcwd() . "</div>";
        
        // Kiểm tra __DIR__ constants
        echo "<h4>🏠 Directory Constants:</h4>";
        echo "<table>";
        echo "<tr><th>Constant</th><th>Value</th></tr>";
        echo "<tr><td>__DIR__</td><td>" . __DIR__ . "</td></tr>";
        echo "<tr><td>__FILE__</td><td>" . __FILE__ . "</td></tr>";
        echo "<tr><td>dirname(__FILE__)</td><td>" . dirname(__FILE__) . "</td></tr>";
        echo "<tr><td>realpath('.')</td><td>" . realpath('.') . "</td></tr>";
        echo "</table>";
        
        echo "</div>";
        echo "</div>";
    }

    // Kiểm tra file permissions
    public function checkFilePermissions() {
        echo "<div class='section'>";
        echo "<div class='section-title'>🔒 Kiểm tra quyền truy cập file</div>";
        echo "<div class='section-content'>";
        
        $frontendFiles = $this->getFrontendFiles();
        $permissionIssues = 0;
        
        echo "<table>";
        echo "<thead>";
        echo "<tr><th>File</th><th>Readable</th><th>Writable</th><th>Executable</th><th>Permissions</th></tr>";
        echo "</thead>";
        echo "<tbody>";
        
        foreach ($frontendFiles as $category => $files) {
            foreach ($files as $file) {
                if (file_exists($file)) {
                    $readable = is_readable($file) ? '✅' : '❌';
                    $writable = is_writable($file) ? '✅' : '❌';
                    $executable = is_executable($file) ? '✅' : '❌';
                    $perms = substr(sprintf('%o', fileperms($file)), -4);
                    
                    $statusClass = (is_readable($file) && !is_writable($file)) ? 'success' : 'warning';
                    if (!is_readable($file)) {
                        $statusClass = 'error';
                        $permissionIssues++;
                    }
                    
                    echo "<tr class='{$statusClass}'>";
                    echo "<td>" . basename($file) . "</td>";
                    echo "<td>{$readable}</td>";
                    echo "<td>{$writable}</td>";
                    echo "<td>{$executable}</td>";
                    echo "<td>{$perms}</td>";
                    echo "</tr>";
                }
            }
        }
        
        echo "</tbody>";
        echo "</table>";
        
        if ($permissionIssues > 0) {
            echo "<div class='path-error'>";
            echo "<h4>⚠️ Phát hiện {$permissionIssues} vấn đề về quyền truy cập</h4>";
            echo "<p>Khuyến nghị: Sử dụng lệnh <code>chmod 644</code> cho file PHP</p>";
            echo "</div>";
        } else {
            echo "<div class='path-success'>";
            echo "<h4>✅ Tất cả file đều có quyền truy cập phù hợp</h4>";
            echo "</div>";
        }
        
        echo "</div>";
        echo "</div>";
    }

    // Kiểm tra syntax errors
    public function checkSyntaxErrors() {
        echo "<div class='section'>";
        echo "<div class='section-title'>🔍 Kiểm tra lỗi Syntax PHP</div>";
        echo "<div class='section-content'>";
        
        $frontendFiles = $this->getFrontendFiles();
        $syntaxErrors = 0;
        
        foreach ($frontendFiles as $category => $files) {
            echo "<h4>📁 {$category}</h4>";
            
            foreach ($files as $file) {
                if (file_exists($file)) {
                    $fileName = basename($file);
                    
                    // Kiểm tra syntax bằng php -l
                    $output = [];
                    $returnCode = 0;
                    exec("php -l " . escapeshellarg($file) . " 2>&1", $output, $returnCode);
                    
                    if ($returnCode === 0) {
                        echo "<div class='path-success'>✅ {$fileName} - Syntax OK</div>";
                    } else {
                        echo "<div class='path-error'>";
                        echo "<strong>❌ {$fileName} - Syntax Error:</strong><br>";
                        echo "<code>" . implode("<br>", $output) . "</code>";
                        echo "</div>";
                        $syntaxErrors++;
                    }
                }
            }
        }
        
        if ($syntaxErrors === 0) {
            echo "<div class='path-success'>";
            echo "<h4>✅ Tất cả file đều không có lỗi syntax</h4>";
            echo "</div>";
        } else {
            echo "<div class='path-error'>";
            echo "<h4>⚠️ Phát hiện {$syntaxErrors} file có lỗi syntax</h4>";
            echo "<p>Cần sửa các lỗi syntax trước khi tiếp tục</p>";
            echo "</div>";
        }
        
        echo "</div>";
        echo "</div>";
    }

    // Tạo báo cáo tổng kết
    public function generateReport() {
        echo "<div class='section'>";
        echo "<div class='section-title'>📋 Báo cáo tổng kết</div>";
        echo "<div class='section-content'>";
        
        $reportTime = date('d/m/Y H:i:s');
        $severity = $this->getSeverityLevel();
        
        echo "<div class='summary-stats'>";
        echo "<div class='stat-card'>";
        echo "<div class='stat-number' style='color: #3498db;'>{$this->totalFiles}</div>";
        echo "<div>Files Scanned</div>";
        echo "</div>";
        
        echo "<div class='stat-card'>";
        echo "<div class='stat-number' style='color: #e74c3c;'>{$this->totalIssues}</div>";
        echo "<div>Issues Found</div>";
        echo "</div>";
        
        echo "<div class='stat-card'>";
        echo "<div class='stat-number' style='color: #f39c12;'>" . count($this->scannedFiles) . "</div>";
        echo "<div>Files Analyzed</div>";
        echo "</div>";
        
        $successRate = $this->totalFiles > 0 ? round((($this->totalFiles - $this->totalIssues) / $this->totalFiles) * 100, 1) : 100;
        echo "<div class='stat-card'>";
        echo "<div class='stat-number' style='color: #27ae60;'>{$successRate}%</div>";
        echo "<div>Success Rate</div>";
        echo "</div>";
        echo "</div>";
        
        echo "<h3>📊 Chi tiết báo cáo:</h3>";
        echo "<table>";
        echo "<tr><th>Thông tin</th><th>Giá trị</th></tr>";
        echo "<tr><td>Thời gian scan</td><td>{$reportTime}</td></tr>";
        echo "<tr><td>Tổng số file</td><td>{$this->totalFiles}</td></tr>";
        echo "<tr><td>Số lỗi phát hiện</td><td>{$this->totalIssues}</td></tr>";
        echo "<tr><td>Mức độ nghiêm trọng</td><td>{$severity}</td></tr>";
        echo "<tr><td>Tỷ lệ thành công</td><td>{$successRate}%</td></tr>";
        echo "</table>";
        
        if ($this->totalIssues === 0) {
            echo "<div class='path-success'>";
            echo "<h3>🎉 Chúc mừng!</h3>";
            echo "<p>Tất cả file frontend đều không có vấn đề về đường dẫn. Website của bạn sẵn sàng hoạt động!</p>";
            echo "</div>";
        } else {
            echo "<div class='path-warning'>";
            echo "<h3>⚠️ Cần hành động</h3>";
            echo "<p>Đã phát hiện {$this->totalIssues} vấn đề cần được khắc phục để website hoạt động tốt.</p>";
            echo "<p>Hãy làm theo hướng dẫn sửa lỗi ở trên.</p>";
            echo "</div>";
        }
        
        echo "</div>";
        echo "</div>";
    }

    // Chạy tất cả các kiểm tra
    public function runFullAnalysis() {
        $this->showOverview();
        $this->analyzeFrontendPaths();
        $this->checkIncludePath();
        $this->checkFilePermissions();
        $this->checkSyntaxErrors();
        $this->generateFixFiles();
        $this->generateReport();
        
        echo "<div style='text-align: center; margin: 30px 0; padding: 20px; background: #f8f9fa; border-radius: 10px;'>";
        echo "<h3>🎯 Next Steps</h3>";
        echo "<div style='margin: 20px 0;'>";
        
        if ($this->totalIssues > 0) {
            echo "<a href='#' onclick='location.reload()' class='btn btn-danger'>🔄 Scan Again</a>";
            echo "<a href='quick_test.php' class='btn btn-warning'>⚡ Quick Test</a>";
        } else {
            echo "<a href='quick_test.php' class='btn btn-success'>⚡ Run Quick Test</a>";
            echo "<a href='customer/index.php' class='btn btn-success'>🏠 View Homepage</a>";
        }
        
        echo "<a href='debug.php' class='btn'>🔍 Full Debug</a>";
        echo "<a href='admin/colors/index.php' class='btn'>🔧 Admin Panel</a>";
        echo "</div>";
        echo "<p style='color: #666; margin-top: 20px;'>Frontend Debug completed at " . date('d/m/Y H:i:s') . "</p>";
        echo "<p style='color: #666;'>📁 Base Directory: " . $this->baseDir . "</p>";
        echo "</div>";
        
        echo "</div>"; // Close container
        echo "</body></html>";
    }
}

// Chạy debugger
$debugger = new FrontendPathDebugger();
$debugger->runFullAnalysis();
?>