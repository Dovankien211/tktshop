<?php
/**
 * File cấu hình hệ thống và các hàm tiện ích - TKT Shop
 * Tất cả hàm đều có kiểm tra function_exists() để tránh xung đột
 */

// ================================
// CẤU HÌNH CƠ BẢN
// ================================

// Thông tin website
define('SITE_NAME', 'TKT Shop');
define('SITE_DESCRIPTION', 'Cửa hàng giày thể thao chính hãng');
define('SITE_KEYWORDS', 'giày thể thao, nike, adidas, converse, vans');

// URL và đường dẫn - CẬP NHẬT ĐÚNG ĐƯỜNG DẪN
define('BASE_URL', 'http://localhost/tktshop');
define('ADMIN_URL', BASE_URL . '/admin');
define('CUSTOMER_URL', BASE_URL . '/customer');

// Đường dẫn thư mục
define('ROOT_PATH', dirname(__DIR__));
define('UPLOAD_PATH', ROOT_PATH . '/uploads');
define('LOG_PATH', ROOT_PATH . '/logs');

// Cấu hình upload
define('MAX_FILE_SIZE', 2 * 1024 * 1024); // 2MB
define('ALLOWED_IMAGE_TYPES', ['jpg', 'jpeg', 'png', 'gif']);
define('ALLOWED_IMAGE_MIMES', [
    'image/jpeg',
    'image/jpg', 
    'image/png',
    'image/gif'
]);

// Cấu hình phân trang
define('PRODUCTS_PER_PAGE', 12);
define('ADMIN_ITEMS_PER_PAGE', 20);

// Cấu hình session
if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

// Thiết lập timezone
date_default_timezone_set('Asia/Ho_Chi_Minh');

// ================================
// CÁC HÀM TIỆN ÍCH
// ================================

/**
 * Kiểm tra đăng nhập admin
 */
if (!function_exists('requireLogin')) {
    function requireLogin() {
        if (!isset($_SESSION['admin_id'])) {
            header('Location: /tktshop/admin/login.php');
            exit;
        }
    }
}

/**
 * Kiểm tra đăng nhập khách hàng
 */
if (!function_exists('requireCustomerLogin')) {
    function requireCustomerLogin() {
        if (!isset($_SESSION['customer_id'])) {
            header('Location: /tktshop/customer/login.php');
            exit;
        }
    }
}

/**
 * Tạo URL admin
 */
if (!function_exists('adminUrl')) {
    function adminUrl($path = '') {
        return '/tktshop/admin/' . ltrim($path, '/');
    }
}

/**
 * Tạo URL customer
 */
if (!function_exists('customerUrl')) {
    function customerUrl($path = '') {
        return '/tktshop/customer/' . ltrim($path, '/');
    }
}

/**
 * Tạo URL uploads
 */
if (!function_exists('uploadsUrl')) {
    function uploadsUrl($path = '') {
        return '/tktshop/uploads/' . ltrim($path, '/');
    }
}

/**
 * Chuyển hướng - CẬP NHẬT ĐỂ XỬ LÝ ĐÚNG ĐƯỜNG DẪN
 */
if (!function_exists('redirect')) {
    function redirect($url) {
        // Nếu URL bắt đầu bằng http thì redirect trực tiếp
        if (strpos($url, 'http') === 0) {
            header("Location: $url");
            exit;
        }
        
        // Nếu URL bắt đầu bằng /tktshop thì redirect trực tiếp
        if (strpos($url, '/tktshop') === 0) {
            header("Location: $url");
            exit;
        }
        
        // Nếu URL bắt đầu bằng / thì thêm /tktshop vào đầu
        if (strpos($url, '/') === 0) {
            $url = '/tktshop' . $url;
        } else {
            // Nếu không có / ở đầu thì thêm /tktshop/
            $url = '/tktshop/' . ltrim($url, '/');
        }
        
        header("Location: $url");
        exit;
    }
}

/**
 * Lưu thông báo vào session
 */
if (!function_exists('alert')) {
    function alert($message, $type = 'info') {
        $_SESSION['alert'] = [
            'message' => $message,
            'type' => $type,
            'time' => time()
        ];
    }
}

/**
 * Hiển thị thông báo và xóa khỏi session
 */
if (!function_exists('showAlert')) {
    function showAlert() {
        if (isset($_SESSION['alert'])) {
            $alert = $_SESSION['alert'];
            $class = 'alert-' . $alert['type'];
            
            echo "<div class='alert $class alert-dismissible fade show' role='alert'>";
            echo htmlspecialchars($alert['message']);
            echo '<button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>';
            echo '</div>';
            
            unset($_SESSION['alert']);
        }
    }
}

/**
 * Format giá tiền VND
 */
if (!function_exists('formatPrice')) {
    function formatPrice($price) {
        if (empty($price) || $price == 0) {
            return '0₫';
        }
        return number_format($price, 0, ',', '.') . '₫';
    }
}

/**
 * Format giá tiền USD (nếu cần)
 */
if (!function_exists('formatPriceUSD')) {
    function formatPriceUSD($price) {
        return '$' . number_format($price, 2, '.', ',');
    }
}

/**
 * Tạo slug từ tiếng Việt
 */
if (!function_exists('createSlug')) {
    function createSlug($str) {
        $str = trim(mb_strtolower($str, 'UTF-8'));
        
        // Chuyển đổi ký tự có dấu
        $unicode = array(
            'a' => 'áàảãạâấầẩẫậăắằẳẵặ',
            'e' => 'éèẻẽẹêếềểễệ',
            'i' => 'íìỉĩị',
            'o' => 'óòỏõọôốồổỗộơớờởỡợ',
            'u' => 'úùủũụưứừửữự',
            'y' => 'ýỳỷỹỵ',
            'd' => 'đ',
        );
        
        foreach ($unicode as $non_sign => $sign) {
            $str = preg_replace("/[$sign]/u", $non_sign, $str);
        }
        
        // Loại bỏ ký tự đặc biệt và thay thế bằng dấu gạch ngang
        $str = preg_replace('/[^a-z0-9\s-.]/', '', $str);
        $str = preg_replace('/[\s-]+/', '-', $str);
        $str = trim($str, '-');
        
        return $str;
    }
}

/**
 * Upload file với kiểm tra bảo mật
 */
if (!function_exists('uploadFile')) {
    function uploadFile($file, $folder, $allowed_types = null) {
        if ($allowed_types === null) {
            $allowed_types = ALLOWED_IMAGE_TYPES;
        }
        
        // Kiểm tra lỗi upload
        if ($file['error'] !== UPLOAD_ERR_OK) {
            return ['success' => false, 'message' => 'Lỗi upload file: ' . $file['error']];
        }
        
        // Kiểm tra kích thước
        if ($file['size'] > MAX_FILE_SIZE) {
            return ['success' => false, 'message' => 'File quá lớn (tối đa ' . formatFileSize(MAX_FILE_SIZE) . ')'];
        }
        
        // Kiểm tra loại file
        $file_info = pathinfo($file['name']);
        $extension = strtolower($file_info['extension']);
        
        if (!in_array($extension, $allowed_types)) {
            return ['success' => false, 'message' => 'Loại file không được phép. Chỉ chấp nhận: ' . implode(', ', $allowed_types)];
        }
        
        // Kiểm tra MIME type
        $finfo = finfo_open(FILEINFO_MIME_TYPE);
        $mime_type = finfo_file($finfo, $file['tmp_name']);
        finfo_close($finfo);
        
        if (!in_array($mime_type, ALLOWED_IMAGE_MIMES)) {
            return ['success' => false, 'message' => 'MIME type không hợp lệ: ' . $mime_type];
        }
        
        // Tạo tên file unique
        $filename = time() . '_' . uniqid() . '.' . $extension;
        
        // Tạo thư mục nếu chưa tồn tại
        $upload_dir = UPLOAD_PATH . '/' . $folder;
        if (!is_dir($upload_dir)) {
            if (!mkdir($upload_dir, 0755, true)) {
                return ['success' => false, 'message' => 'Không thể tạo thư mục upload'];
            }
        }
        
        // Upload file
        $file_path = $upload_dir . '/' . $filename;
        if (move_uploaded_file($file['tmp_name'], $file_path)) {
            return [
                'success' => true, 
                'filename' => $filename, 
                'path' => $file_path,
                'url' => uploadsUrl($folder . '/' . $filename),
                'size' => $file['size']
            ];
        }
        
        return ['success' => false, 'message' => 'Không thể upload file'];
    }
}

/**
 * Xóa file upload
 */
if (!function_exists('deleteUploadedFile')) {
    function deleteUploadedFile($filename, $folder) {
        if (empty($filename)) return true;
        
        $file_path = UPLOAD_PATH . '/' . $folder . '/' . $filename;
        if (file_exists($file_path)) {
            return unlink($file_path);
        }
        return true;
    }
}

/**
 * Escape HTML
 */
if (!function_exists('e')) {
    function e($string) {
        return htmlspecialchars($string, ENT_QUOTES, 'UTF-8');
    }
}

/**
 * Debug function
 */
if (!function_exists('dd')) {
    function dd($data) {
        echo '<pre style="background: #f4f4f4; padding: 20px; margin: 20px; border-radius: 5px;">';
        var_dump($data);
        echo '</pre>';
        die();
    }
}

/**
 * Debug print (không die)
 */
if (!function_exists('dump')) {
    function dump($data) {
        echo '<pre style="background: #f4f4f4; padding: 10px; margin: 10px; border-radius: 3px;">';
        var_dump($data);
        echo '</pre>';
    }
}

/**
 * Log lỗi
 */
if (!function_exists('logError')) {
    function logError($message, $context = []) {
        $log = date('Y-m-d H:i:s') . ' - ' . $message;
        if (!empty($context)) {
            $log .= ' - Context: ' . json_encode($context, JSON_UNESCAPED_UNICODE);
        }
        $log .= PHP_EOL;
        
        $log_file = LOG_PATH . '/error.log';
        $log_dir = dirname($log_file);
        
        if (!is_dir($log_dir)) {
            mkdir($log_dir, 0755, true);
        }
        
        file_put_contents($log_file, $log, FILE_APPEND | LOCK_EX);
    }
}

/**
 * Log hoạt động
 */
if (!function_exists('logActivity')) {
    function logActivity($message, $data = []) {
        $log = date('Y-m-d H:i:s') . ' - ' . $message;
        if (!empty($data)) {
            $log .= ' - Data: ' . json_encode($data, JSON_UNESCAPED_UNICODE);
        }
        $log .= PHP_EOL;
        
        $log_file = LOG_PATH . '/activity.log';
        $log_dir = dirname($log_file);
        
        if (!is_dir($log_dir)) {
            mkdir($log_dir, 0755, true);
        }
        
        file_put_contents($log_file, $log, FILE_APPEND | LOCK_EX);
    }
}

/**
 * Kiểm tra quyền admin
 */
if (!function_exists('isAdmin')) {
    function isAdmin() {
        return isset($_SESSION['admin_id']) && isset($_SESSION['admin_role']) && $_SESSION['admin_role'] == 'admin';
    }
}

/**
 * Kiểm tra quyền staff (admin hoặc nhân viên)
 */
if (!function_exists('isStaff')) {
    function isStaff() {
        return isset($_SESSION['admin_id']) && isset($_SESSION['admin_role']) && 
               in_array($_SESSION['admin_role'], ['admin', 'nhan_vien']);
    }
}

/**
 * Kiểm tra đăng nhập khách hàng
 */
if (!function_exists('isCustomerLoggedIn')) {
    function isCustomerLoggedIn() {
        return isset($_SESSION['customer_id']);
    }
}

/**
 * Lấy thông tin khách hàng hiện tại
 */
if (!function_exists('getCurrentCustomer')) {
    function getCurrentCustomer() {
        if (!isCustomerLoggedIn()) return null;
        
        return [
            'id' => $_SESSION['customer_id'],
            'name' => $_SESSION['customer_name'] ?? '',
            'email' => $_SESSION['customer_email'] ?? ''
        ];
    }
}

/**
 * Truncate text
 */
if (!function_exists('truncate')) {
    function truncate($text, $length = 100, $suffix = '...') {
        if (mb_strlen($text, 'UTF-8') <= $length) {
            return $text;
        }
        return mb_substr($text, 0, $length, 'UTF-8') . $suffix;
    }
}

/**
 * Format date
 */
if (!function_exists('formatDate')) {
    function formatDate($date, $format = 'd/m/Y H:i') {
        if (empty($date)) return '';
        return date($format, strtotime($date));
    }
}

/**
 * Format ngày tiếng Việt
 */
if (!function_exists('formatDateVN')) {
    function formatDateVN($date) {
        if (empty($date)) return '';
        
        $timestamp = strtotime($date);
        $day = date('d', $timestamp);
        $month = date('m', $timestamp);
        $year = date('Y', $timestamp);
        $hour = date('H:i', $timestamp);
        
        $months = [
            '01' => 'Tháng 1', '02' => 'Tháng 2', '03' => 'Tháng 3',
            '04' => 'Tháng 4', '05' => 'Tháng 5', '06' => 'Tháng 6',
            '07' => 'Tháng 7', '08' => 'Tháng 8', '09' => 'Tháng 9',
            '10' => 'Tháng 10', '11' => 'Tháng 11', '12' => 'Tháng 12'
        ];
        
        return $day . ' ' . $months[$month] . ', ' . $year . ' lúc ' . $hour;
    }
}

/**
 * Get file size in human readable format
 */
if (!function_exists('formatFileSize')) {
    function formatFileSize($bytes, $precision = 2) {
        $units = array('B', 'KB', 'MB', 'GB', 'TB');
        
        for ($i = 0; $bytes > 1024 && $i < count($units) - 1; $i++) {
            $bytes /= 1024;
        }
        
        return round($bytes, $precision) . ' ' . $units[$i];
    }
}

/**
 * Validate email
 */
if (!function_exists('isValidEmail')) {
    function isValidEmail($email) {
        return filter_var($email, FILTER_VALIDATE_EMAIL) !== false;
    }
}

/**
 * Validate phone number (Vietnamese format)
 */
if (!function_exists('isValidPhone')) {
    function isValidPhone($phone) {
        return preg_match('/^(0|\+84)[3|5|7|8|9][0-9]{8}$/', $phone);
    }
}

/**
 * Generate random string
 */
if (!function_exists('generateRandomString')) {
    function generateRandomString($length = 10) {
        return substr(str_shuffle(str_repeat($x='0123456789abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ', ceil($length/strlen($x)))), 1, $length);
    }
}

/**
 * Generate order code
 */
if (!function_exists('generateOrderCode')) {
    function generateOrderCode() {
        return 'TKT' . date('Ymd') . rand(1000, 9999);
    }
}

/**
 * Clean input data
 */
if (!function_exists('cleanInput')) {
    function cleanInput($data) {
        $data = trim($data);
        $data = stripslashes($data);
        $data = htmlspecialchars($data, ENT_QUOTES, 'UTF-8');
        return $data;
    }
}

/**
 * Get client IP
 */
if (!function_exists('getClientIP')) {
    function getClientIP() {
        $ip_keys = ['HTTP_CLIENT_IP', 'HTTP_X_FORWARDED_FOR', 'REMOTE_ADDR'];
        foreach ($ip_keys as $key) {
            if (array_key_exists($key, $_SERVER) === true) {
                foreach (explode(',', $_SERVER[$key]) as $ip) {
                    $ip = trim($ip);
                    if (filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE) !== false) {
                        return $ip;
                    }
                }
            }
        }
        return $_SERVER['REMOTE_ADDR'] ?? '127.0.0.1';
    }
}

/**
 * Check if string contains Vietnamese characters
 */
if (!function_exists('hasVietnamese')) {
    function hasVietnamese($str) {
        return preg_match('/[àáạảãâầấậẩẫăằắặẳẵèéẹẻẽêềếệểễìíịỉĩòóọỏõôồốộổỗơờớợởỡùúụủũưừứựửữỳýỵỷỹđ]/u', $str);
    }
}

/**
 * Convert Vietnamese to ASCII
 */
if (!function_exists('vietnameseToAscii')) {
    function vietnameseToAscii($str) {
        $vietnamese = [
            'à','á','ạ','ả','ã','â','ầ','ấ','ậ','ẩ','ẫ','ă','ằ','ắ','ặ','ẳ','ẵ',
            'è','é','ẹ','ẻ','ẽ','ê','ề','ế','ệ','ể','ễ',
            'ì','í','ị','ỉ','ĩ',
            'ò','ó','ọ','ỏ','õ','ô','ồ','ố','ộ','ổ','ỗ','ơ','ờ','ớ','ợ','ở','ỡ',
            'ù','ú','ụ','ủ','ũ','ư','ừ','ứ','ự','ử','ữ',
            'ỳ','ý','ỵ','ỷ','ỹ',
            'đ',
            'À','Á','Ạ','Ả','Ã','Â','Ầ','Ấ','Ậ','Ẩ','Ẫ','Ă','Ằ','Ắ','Ặ','Ẳ','Ẵ',
            'È','É','Ẹ','Ẻ','Ẽ','Ê','Ề','Ế','Ệ','Ể','Ễ',
            'Ì','Í','Ị','Ỉ','Ĩ',
            'Ò','Ó','Ọ','Ỏ','Õ','Ô','Ồ','Ố','Ộ','Ổ','Ỗ','Ơ','Ờ','Ớ','Ợ','Ở','Ỡ',
            'Ù','Ú','Ụ','Ủ','Ũ','Ư','Ừ','Ứ','Ự','Ử','Ữ',
            'Ỳ','Ý','Ỵ','Ỷ','Ỹ',
            'Đ'
        ];
        
        $ascii = [
            'a','a','a','a','a','a','a','a','a','a','a','a','a','a','a','a','a',
            'e','e','e','e','e','e','e','e','e','e','e',
            'i','i','i','i','i',
            'o','o','o','o','o','o','o','o','o','o','o','o','o','o','o','o','o',
            'u','u','u','u','u','u','u','u','u','u','u',
            'y','y','y','y','y',
            'd',
            'A','A','A','A','A','A','A','A','A','A','A','A','A','A','A','A','A',
            'E','E','E','E','E','E','E','E','E','E','E',
            'I','I','I','I','I',
            'O','O','O','O','O','O','O','O','O','O','O','O','O','O','O','O','O',
            'U','U','U','U','U','U','U','U','U','U','U',
            'Y','Y','Y','Y','Y',
            'D'
        ];
        
        return str_replace($vietnamese, $ascii, $str);
    }
}

/**
 * Tính phần trăm giảm giá
 */
if (!function_exists('calculateDiscountPercent')) {
    function calculateDiscountPercent($original_price, $sale_price) {
        if ($original_price <= 0 || $sale_price >= $original_price) {
            return 0;
        }
        return round((($original_price - $sale_price) / $original_price) * 100);
    }
}

/**
 * Get product image URL
 */
if (!function_exists('getProductImageUrl')) {
    function getProductImageUrl($image, $default = 'no-image.jpg') {
        if (empty($image)) {
            return uploadsUrl('products/' . $default);
        }
        
        $full_path = UPLOAD_PATH . '/products/' . $image;
        if (file_exists($full_path)) {
            return uploadsUrl('products/' . $image);
        }
        
        return uploadsUrl('products/' . $default);
    }
}

/**
 * Get category image URL
 */
if (!function_exists('getCategoryImageUrl')) {
    function getCategoryImageUrl($image, $default = 'no-category.jpg') {
        if (empty($image)) {
            return uploadsUrl('categories/' . $default);
        }
        
        $full_path = UPLOAD_PATH . '/categories/' . $image;
        if (file_exists($full_path)) {
            return uploadsUrl('categories/' . $image);
        }
        
        return uploadsUrl('categories/' . $default);
    }
}

/**
 * Get current URL
 */
if (!function_exists('getCurrentUrl')) {
    function getCurrentUrl() {
        $protocol = isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? 'https' : 'http';
        return $protocol . '://' . $_SERVER['HTTP_HOST'] . $_SERVER['REQUEST_URI'];
    }
}

// ================================
// THIẾT LẬP ERROR HANDLING
// ================================

// Thiết lập error reporting (chỉ trong development)
if (defined('DEBUG') && DEBUG === true) {
    error_reporting(E_ALL);
    ini_set('display_errors', 1);
} else {
    error_reporting(0);
    ini_set('display_errors', 0);
}

// Custom error handler
if (!function_exists('customErrorHandler')) {
    function customErrorHandler($errno, $errstr, $errfile, $errline) {
        $error_message = "Error [$errno]: $errstr in $errfile on line $errline";
        logError($error_message);
        
        // Trong production, không hiển thị lỗi chi tiết
        if (!defined('DEBUG') || DEBUG !== true) {
            echo '<div class="alert alert-danger">Đã xảy ra lỗi hệ thống. Vui lòng thử lại sau.</div>';
        }
        
        return true;
    }
}

set_error_handler('customErrorHandler');

// ================================
// AUTO-CREATE DIRECTORIES
// ================================

// Tạo các thư mục cần thiết
$required_dirs = [
    UPLOAD_PATH,
    UPLOAD_PATH . '/products',
    UPLOAD_PATH . '/categories',
    UPLOAD_PATH . '/users',
    LOG_PATH
];

foreach ($required_dirs as $dir) {
    if (!is_dir($dir)) {
        mkdir($dir, 0755, true);
    }
}

// ================================
// CONSTANTS FOR DEVELOPMENT
// ================================

// Debug mode (set to false in production)
define('DEBUG', true);

// Version cho cache busting
define('ASSETS_VERSION', '1.0.0');

// Timezone
define('TIMEZONE', 'Asia/Ho_Chi_Minh');

?>