<?php
/**
 * TKT Shop - Common Functions (Tương thích database cũ)
 * File: includes/functions.php
 */

if (!defined('ABSPATH')) {
    define('ABSPATH', dirname(__FILE__) . '/');
}

// Include config và database (sử dụng PDO như file cũ)
require_once dirname(__DIR__) . '/config/database.php';

/**
 * Function để format giá tiền VNĐ
 */
function formatPrice($price) {
    return number_format($price, 0, ',', '.') . 'đ';
}

/**
 * Function để sanitize input data
 */
function sanitizeInput($data) {
    if (is_array($data)) {
        return array_map('sanitizeInput', $data);
    }
    $data = trim($data);
    $data = stripslashes($data);
    $data = htmlspecialchars($data, ENT_QUOTES, 'UTF-8');
    return $data;
}

/**
 * Function để format ngày tháng
 */
function formatDate($date, $format = 'd/m/Y H:i') {
    if (empty($date) || $date == '0000-00-00 00:00:00') {
        return '';
    }
    return date($format, strtotime($date));
}

/**
 * Function để tạo slug từ tiêu đề
 */
function createSlug($string) {
    // Chuyển sang chữ thường
    $string = strtolower($string);
    
    // Thay thế ký tự có dấu
    $vietnamese = [
        'à','á','ạ','ả','ã','â','ầ','ấ','ậ','ẩ','ẫ','ă','ằ','ắ','ặ','ẳ','ẵ',
        'è','é','ẹ','ẻ','ẽ','ê','ề','ế','ệ','ể','ễ',
        'ì','í','ị','ỉ','ĩ',
        'ò','ó','ọ','ỏ','õ','ô','ồ','ố','ộ','ổ','ỗ','ơ','ờ','ớ','ợ','ở','ỡ',
        'ù','ú','ụ','ủ','ũ','ư','ừ','ứ','ự','ử','ữ',
        'ỳ','ý','ỵ','ỷ','ỹ','đ'
    ];
    
    $english = [
        'a','a','a','a','a','a','a','a','a','a','a','a','a','a','a','a','a',
        'e','e','e','e','e','e','e','e','e','e','e',
        'i','i','i','i','i',
        'o','o','o','o','o','o','o','o','o','o','o','o','o','o','o','o','o',
        'u','u','u','u','u','u','u','u','u','u','u',
        'y','y','y','y','y','d'
    ];
    
    $string = str_replace($vietnamese, $english, $string);
    
    // Thay thế ký tự đặc biệt bằng dấu gạch ngang
    $string = preg_replace('/[^a-z0-9]+/', '-', $string);
    
    // Loại bỏ dấu gạch ngang ở đầu và cuối
    $string = trim($string, '-');
    
    return $string;
}

/**
 * Function để lấy ảnh sản phẩm (tương thích với database cũ)
 */
function getProductImage($product_id, $size = 'medium') {
    global $pdo;
    
    if (empty($product_id)) {
        return '/tktshop/assets/images/giaythethao.jpg';
    }
    
    try {
        $sql = "SELECT hinh_anh FROM san_pham_chinh WHERE id = ? AND trang_thai = 'hoat_dong'";
        $stmt = $pdo->prepare($sql);
        $stmt->execute([$product_id]);
        $product = $stmt->fetch();
        
        if ($product && !empty($product['hinh_anh'])) {
            return $product['hinh_anh'];
        }
    } catch (Exception $e) {
        // Log error if needed
    }
    
    return '/tktshop/assets/images/giaythethao.jpg';
}

/**
 * Function để tạo breadcrumb
 */
function generateBreadcrumb($items = []) {
    $breadcrumb = '<nav aria-label="breadcrumb" class="mb-4">';
    $breadcrumb .= '<ol class="breadcrumb">';
    
    // Trang chủ luôn là item đầu tiên
    $breadcrumb .= '<li class="breadcrumb-item"><a href="/tktshop/">Trang chủ</a></li>';
    
    foreach ($items as $index => $item) {
        if ($index === count($items) - 1) {
            // Item cuối cùng không có link
            $breadcrumb .= '<li class="breadcrumb-item active" aria-current="page">' . sanitizeInput($item['title']) . '</li>';
        } else {
            $breadcrumb .= '<li class="breadcrumb-item"><a href="' . sanitizeInput($item['url']) . '">' . sanitizeInput($item['title']) . '</a></li>';
        }
    }
    
    $breadcrumb .= '</ol>';
    $breadcrumb .= '</nav>';
    
    return $breadcrumb;
}

/**
 * Function để hiển thị sao đánh giá
 */
function displayStars($rating) {
    $stars = '';
    for ($i = 1; $i <= 5; $i++) {
        if ($i <= $rating) {
            $stars .= '<i class="fas fa-star text-warning"></i>';
        } else {
            $stars .= '<i class="far fa-star text-muted"></i>';
        }
    }
    return $stars;
}

/**
 * Function để generate stars với class CSS
 */
function generateStars($rating, $max_rating = 5) {
    $stars = '<span class="stars">';
    $full_stars = floor($rating);
    $half_star = $rating - $full_stars >= 0.5;
    $empty_stars = $max_rating - $full_stars - ($half_star ? 1 : 0);
    
    // Sao đầy
    for ($i = 0; $i < $full_stars; $i++) {
        $stars .= '<i class="fas fa-star"></i>';
    }
    
    // Sao nửa
    if ($half_star) {
        $stars .= '<i class="fas fa-star-half-alt"></i>';
    }
    
    // Sao rỗng
    for ($i = 0; $i < $empty_stars; $i++) {
        $stars .= '<i class="far fa-star"></i>';
    }
    
    $stars .= '</span>';
    return $stars;
}

/**
 * Function để lấy tất cả categories (tương thích database cũ)
 */
function getAllCategories() {
    global $pdo;
    
    try {
        $sql = "SELECT dm.*, COUNT(sp.id) as so_san_pham
                FROM danh_muc_giay dm
                LEFT JOIN san_pham_chinh sp ON dm.id = sp.danh_muc_id AND sp.trang_thai = 'hoat_dong'
                WHERE dm.trang_thai = 'hoat_dong'
                GROUP BY dm.id
                ORDER BY dm.thu_tu_hien_thi ASC";
        
        $result = $pdo->query($sql);
        return $result->fetchAll(PDO::FETCH_ASSOC);
    } catch (Exception $e) {
        return [];
    }
}

/**
 * Function để lấy sản phẩm theo category (tương thích database cũ)
 */
function getProductsByCategory($category_id, $limit = 12, $offset = 0) {
    global $pdo;
    
    try {
        $sql = "SELECT sp.*, dm.ten_danh_muc,
                COALESCE(sp.gia_khuyen_mai, sp.gia_goc) as gia_hien_tai
                FROM san_pham_chinh sp 
                LEFT JOIN danh_muc_giay dm ON sp.danh_muc_id = dm.id 
                WHERE sp.danh_muc_id = ? AND sp.trang_thai = 'hoat_dong' 
                ORDER BY sp.ngay_tao DESC 
                LIMIT ? OFFSET ?";
        
        $stmt = $pdo->prepare($sql);
        $stmt->execute([$category_id, $limit, $offset]);
        
        $products = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        // Format lại dữ liệu để tương thích
        foreach ($products as &$product) {
            $product['formatted_price'] = formatPrice($product['gia_hien_tai']);
            $product['image'] = getProductImage($product['id']);
        }
        
        return $products;
    } catch (Exception $e) {
        return [];
    }
}

/**
 * Function để lấy sản phẩm mới nhất
 */
function getLatestProducts($limit = 12) {
    global $pdo;
    
    try {
        $sql = "SELECT sp.*, dm.ten_danh_muc,
                COALESCE(sp.gia_khuyen_mai, sp.gia_goc) as gia_hien_tai
                FROM san_pham_chinh sp 
                LEFT JOIN danh_muc_giay dm ON sp.danh_muc_id = dm.id 
                WHERE sp.trang_thai = 'hoat_dong' 
                ORDER BY sp.ngay_tao DESC 
                LIMIT ?";
        
        $stmt = $pdo->prepare($sql);
        $stmt->execute([$limit]);
        
        $products = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        // Format lại dữ liệu
        foreach ($products as &$product) {
            $product['formatted_price'] = formatPrice($product['gia_hien_tai']);
            $product['image'] = getProductImage($product['id']);
        }
        
        return $products;
    } catch (Exception $e) {
        return [];
    }
}

/**
 * Function để lấy sản phẩm nổi bật
 */
function getFeaturedProducts($limit = 8) {
    global $pdo;
    
    try {
        $sql = "SELECT sp.*, dm.ten_danh_muc,
                COALESCE(sp.gia_khuyen_mai, sp.gia_goc) as gia_hien_tai
                FROM san_pham_chinh sp 
                LEFT JOIN danh_muc_giay dm ON sp.danh_muc_id = dm.id 
                WHERE sp.trang_thai = 'hoat_dong' AND sp.san_pham_noi_bat = 1 
                ORDER BY sp.ngay_tao DESC 
                LIMIT ?";
        
        $stmt = $pdo->prepare($sql);
        $stmt->execute([$limit]);
        
        $products = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        // Format lại dữ liệu
        foreach ($products as &$product) {
            $product['formatted_price'] = formatPrice($product['gia_hien_tai']);
            $product['image'] = getProductImage($product['id']);
        }
        
        return $products;
    } catch (Exception $e) {
        return [];
    }
}

/**
 * Function để count products in category
 */
function countProductsInCategory($category_id) {
    global $pdo;
    
    try {
        $sql = "SELECT COUNT(*) FROM san_pham_chinh WHERE danh_muc_id = ? AND trang_thai = 'hoat_dong'";
        $stmt = $pdo->prepare($sql);
        $stmt->execute([$category_id]);
        return $stmt->fetchColumn();
    } catch (Exception $e) {
        return 0;
    }
}

/**
 * Function để count total products
 */
function countTotalProducts() {
    global $pdo;
    
    try {
        $sql = "SELECT COUNT(*) FROM san_pham_chinh WHERE trang_thai = 'hoat_dong'";
        $result = $pdo->query($sql);
        return $result->fetchColumn();
    } catch (Exception $e) {
        return 0;
    }
}

/**
 * Function để tạo pagination
 */
function createPagination($current_page, $total_pages, $base_url, $query_params = []) {
    if ($total_pages <= 1) {
        return '';
    }
    
    $pagination = '<nav aria-label="Product pagination" class="mt-5">';
    $pagination .= '<ul class="pagination justify-content-center">';
    
    // Previous button
    if ($current_page > 1) {
        $query_params['page'] = $current_page - 1;
        $prev_url = $base_url . '?' . http_build_query($query_params);
        $pagination .= '<li class="page-item"><a class="page-link" href="' . $prev_url . '"><i class="fas fa-chevron-left"></i> Trước</a></li>';
    }
    
    // Page numbers
    $start = max(1, $current_page - 2);
    $end = min($total_pages, $current_page + 2);
    
    if ($start > 1) {
        $query_params['page'] = 1;
        $pagination .= '<li class="page-item"><a class="page-link" href="' . $base_url . '?' . http_build_query($query_params) . '">1</a></li>';
        if ($start > 2) {
            $pagination .= '<li class="page-item disabled"><span class="page-link">...</span></li>';
        }
    }
    
    for ($i = $start; $i <= $end; $i++) {
        $class = $i === $current_page ? 'page-item active' : 'page-item';
        $query_params['page'] = $i;
        $page_url = $base_url . '?' . http_build_query($query_params);
        $pagination .= '<li class="' . $class . '"><a class="page-link" href="' . $page_url . '">' . $i . '</a></li>';
    }
    
    if ($end < $total_pages) {
        if ($end < $total_pages - 1) {
            $pagination .= '<li class="page-item disabled"><span class="page-link">...</span></li>';
        }
        $query_params['page'] = $total_pages;
        $pagination .= '<li class="page-item"><a class="page-link" href="' . $base_url . '?' . http_build_query($query_params) . '">' . $total_pages . '</a></li>';
    }
    
    // Next button
    if ($current_page < $total_pages) {
        $query_params['page'] = $current_page + 1;
        $next_url = $base_url . '?' . http_build_query($query_params);
        $pagination .= '<li class="page-item"><a class="page-link" href="' . $next_url . '">Sau <i class="fas fa-chevron-right"></i></a></li>';
    }
    
    $pagination .= '</ul>';
    $pagination .= '</nav>';
    
    return $pagination;
}

/**
 * Function để redirect
 */
function redirect($url, $status_code = 302) {
    header("Location: $url", true, $status_code);
    exit();
}

/**
 * Function để validate email
 */
function validateEmail($email) {
    return filter_var($email, FILTER_VALIDATE_EMAIL) !== false;
}

/**
 * Function để thêm flash message
 */
function addFlashMessage($type, $message) {
    if (session_status() == PHP_SESSION_NONE) {
        session_start();
    }
    
    if (!isset($_SESSION['flash_messages'])) {
        $_SESSION['flash_messages'] = [];
    }
    
    $_SESSION['flash_messages'][] = [
        'type' => $type,
        'message' => $message
    ];
}

/**
 * Function để lấy và xóa flash messages
 */
function getFlashMessages() {
    if (session_status() == PHP_SESSION_NONE) {
        session_start();
    }
    
    $messages = $_SESSION['flash_messages'] ?? [];
    unset($_SESSION['flash_messages']);
    return $messages;
}

/**
 * Function để hiển thị flash messages
 */
function displayFlashMessages() {
    $messages = getFlashMessages();
    $html = '';
    
    foreach ($messages as $message) {
        $alertClass = 'alert-info';
        switch ($message['type']) {
            case 'success':
                $alertClass = 'alert-success';
                break;
            case 'error':
            case 'danger':
                $alertClass = 'alert-danger';
                break;
            case 'warning':
                $alertClass = 'alert-warning';
                break;
        }
        
        $html .= '<div class="alert ' . $alertClass . ' alert-dismissible fade show" role="alert">';
        $html .= sanitizeInput($message['message']);
        $html .= '<button type="button" class="btn-close" data-bs-dismiss="alert"></button>';
        $html .= '</div>';
    }
    
    return $html;
}

/**
 * Function để kiểm tra admin đã đăng nhập
 */
function isAdminLoggedIn() {
    if (session_status() == PHP_SESSION_NONE) {
        session_start();
    }
    return isset($_SESSION['admin_id']) && !empty($_SESSION['admin_id']);
}

/**
 * Function để check admin permission
 */
function checkAdminPermission($permission = null) {
    if (!isAdminLoggedIn()) {
        redirect('/tktshop/admin/login.php');
    }
    return true;
}

/**
 * Function để tính khoảng cách thời gian
 */
function timeAgo($datetime) {
    $time = time() - strtotime($datetime);
    
    if ($time < 60) {
        return 'Vừa xong';
    }
    
    $intervals = [
        31536000 => 'năm',
        2592000 => 'tháng', 
        604800 => 'tuần',
        86400 => 'ngày',
        3600 => 'giờ',
        60 => 'phút'
    ];
    
    foreach ($intervals as $seconds => $label) {
        $interval = floor($time / $seconds);
        if ($interval >= 1) {
            return $interval . ' ' . $label . ' trước';
        }
    }
    
    return 'Vừa xong';
}

/**
 * Function để tạo mã đơn hàng
 */
function generateOrderCode() {
    return 'TKT' . date('Ymd') . rand(1000, 9999);
}

/**
 * Function để lấy chi tiết sản phẩm
 */
function getProductDetail($product_id) {
    global $pdo;
    
    try {
        $sql = "SELECT sp.*, dm.ten_danh_muc,
                COALESCE(sp.gia_khuyen_mai, sp.gia_goc) as gia_hien_tai
                FROM san_pham_chinh sp 
                LEFT JOIN danh_muc_giay dm ON sp.danh_muc_id = dm.id 
                WHERE sp.id = ? AND sp.trang_thai = 'hoat_dong'";
        
        $stmt = $pdo->prepare($sql);
        $stmt->execute([$product_id]);
        $product = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if ($product) {
            $product['formatted_price'] = formatPrice($product['gia_hien_tai']);
            $product['image'] = getProductImage($product['id']);
            return $product;
        }
    } catch (Exception $e) {
        // Log error if needed
    }
    
    return null;
}

/**
 * Function để calculate discount percentage
 */
function calculateDiscountPercentage($original_price, $sale_price) {
    if ($original_price <= 0 || $sale_price >= $original_price) {
        return 0;
    }
    
    return round((($original_price - $sale_price) / $original_price) * 100);
}

/**
 * Function để truncate text
 */
function truncateText($text, $length = 100) {
    if (strlen($text) <= $length) {
        return $text;
    }
    
    $truncated = substr($text, 0, $length);
    $lastSpace = strrpos($truncated, ' ');
    
    if ($lastSpace !== false) {
        $truncated = substr($truncated, 0, $lastSpace);
    }
    
    return $truncated . '...';
}

/**
 * Function để log error to file
 */
function logError($message, $file = '', $line = '') {
    $log_dir = dirname(__DIR__) . '/logs/';
    if (!is_dir($log_dir)) {
        mkdir($log_dir, 0755, true);
    }
    
    $log_file = $log_dir . 'error_' . date('Y-m-d') . '.log';
    $log_message = date('Y-m-d H:i:s') . " - ERROR: $message";
    if ($file) $log_message .= " in $file";
    if ($line) $log_message .= " on line $line";
    $log_message .= "\n";
    
    file_put_contents($log_file, $log_message, FILE_APPEND | LOCK_EX);
}

/**
 * Function để update URL param (giống trong file cũ)
 */
function updateUrlParam($key, $value) {
    $params = $_GET;
    if (empty($value)) {
        unset($params[$key]);
    } else {
        $params[$key] = $value;
    }
    unset($params['page']); // Reset trang khi lọc
    return '?' . http_build_query($params);
}

/**
 * Function để generate random string
 */
function generateRandomString($length = 10) {
    $characters = '0123456789abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ';
    $charactersLength = strlen($characters);
    $randomString = '';
    
    for ($i = 0; $i < $length; $i++) {
        $randomString .= $characters[rand(0, $charactersLength - 1)];
    }
    
    return $randomString;
}

?>