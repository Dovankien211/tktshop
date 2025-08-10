<?php
// ====================================
// TKT SHOP - CUSTOMER HELPER FUNCTIONS
// ====================================

if (!defined('SHOP_ROOT')) {
    die('Direct access not permitted');
}

/**
 * Format price to Vietnamese currency
 */
function formatPrice($price) {
    return number_format($price, 0, ',', '.') . ' ₫';
}

/**
 * Format date to Vietnamese format
 */
function formatDate($date, $format = 'd/m/Y H:i') {
    if (empty($date)) return 'N/A';
    return date($format, strtotime($date));
}

/**
 * Generate product URL
 */
function productUrl($productId, $slug = '') {
    if (!empty($slug)) {
        return BASE_URL . "/customer/product_detail.php?id={$productId}&slug=" . urlencode($slug);
    }
    return BASE_URL . "/customer/product_detail.php?id={$productId}";
}

/**
 * Generate category URL
 */
function categoryUrl($categoryId, $slug = '') {
    if (!empty($slug)) {
        return BASE_URL . "/customer/products.php?category={$categoryId}&slug=" . urlencode($slug);
    }
    return BASE_URL . "/customer/products.php?category={$categoryId}";
}

/**
 * Get product image URL
 */
function getProductImage($imagePath, $default = 'default-product.jpg') {
    if (empty($imagePath)) {
        return BASE_URL . "/uploads/products/{$default}";
    }
    
    // Check if image exists
    $fullPath = SHOP_ROOT . "/uploads/products/" . $imagePath;
    if (file_exists($fullPath)) {
        return BASE_URL . "/uploads/products/" . $imagePath;
    }
    
    return BASE_URL . "/uploads/products/{$default}";
}

/**
 * Get category image URL
 */
function getCategoryImage($imagePath, $default = 'default-category.jpg') {
    if (empty($imagePath)) {
        return BASE_URL . "/uploads/categories/{$default}";
    }
    
    $fullPath = SHOP_ROOT . "/uploads/categories/" . $imagePath;
    if (file_exists($fullPath)) {
        return BASE_URL . "/uploads/categories/" . $imagePath;
    }
    
    return BASE_URL . "/uploads/categories/{$default}";
}

/**
 * Generate star rating HTML
 */
function generateStars($rating, $maxRating = 5) {
    $fullStars = floor($rating);
    $halfStar = ($rating - $fullStars) >= 0.5;
    $emptyStars = $maxRating - $fullStars - ($halfStar ? 1 : 0);
    
    $html = '';
    
    // Full stars
    for ($i = 0; $i < $fullStars; $i++) {
        $html .= '<i class="fas fa-star"></i>';
    }
    
    // Half star
    if ($halfStar) {
        $html .= '<i class="fas fa-star-half-alt"></i>';
    }
    
    // Empty stars
    for ($i = 0; $i < $emptyStars; $i++) {
        $html .= '<i class="far fa-star"></i>';
    }
    
    return $html;
}

/**
 * Get order status badge HTML
 */
function getOrderStatusBadge($status) {
    $statusClasses = [
        'pending' => 'badge-warning',
        'processing' => 'badge-info',
        'shipping' => 'badge-primary',
        'delivered' => 'badge-success',
        'cancelled' => 'badge-danger',
        'refunded' => 'badge-secondary'
    ];
    
    $class = $statusClasses[$status] ?? 'badge-secondary';
    $text = ucfirst($status);
    
    return "<span class='badge {$class}'>{$text}</span>";
}

/**
 * Get stock status HTML
 */
function getStockStatus($quantity) {
    if ($quantity <= 0) {
        return '<span class="stock-status out-stock">Hết hàng</span>';
    } elseif ($quantity <= 5) {
        return '<span class="stock-status low-stock">Sắp hết</span>';
    } else {
        return '<span class="stock-status in-stock">Còn hàng</span>';
    }
}

/**
 * Calculate discount percentage
 */
function calculateDiscount($originalPrice, $salePrice) {
    if ($originalPrice <= 0 || $salePrice >= $originalPrice) {
        return 0;
    }
    
    return round((($originalPrice - $salePrice) / $originalPrice) * 100);
}

/**
 * Truncate text
 */
function truncateText($text, $length = 100, $suffix = '...') {
    if (strlen($text) <= $length) {
        return $text;
    }
    
    return substr($text, 0, $length) . $suffix;
}

/**
 * Generate breadcrumb
 */
function generateBreadcrumb($items) {
    $html = '<nav aria-label="breadcrumb">';
    $html .= '<ol class="breadcrumb">';
    
    foreach ($items as $index => $item) {
        $isLast = ($index === count($items) - 1);
        
        if ($isLast) {
            $html .= '<li class="breadcrumb-item active" aria-current="page">' . htmlspecialchars($item['title']) . '</li>';
        } else {
            $html .= '<li class="breadcrumb-item">';
            if (isset($item['url'])) {
                $html .= '<a href="' . htmlspecialchars($item['url']) . '">' . htmlspecialchars($item['title']) . '</a>';
            } else {
                $html .= htmlspecialchars($item['title']);
            }
            $html .= '</li>';
        }
    }
    
    $html .= '</ol>';
    $html .= '</nav>';
    
    return $html;
}

/**
 * Check if user is logged in
 */
function isLoggedIn() {
    return isset($_SESSION['user_id']) && !empty($_SESSION['user_id']);
}

/**
 * Get current user info
 */
function getCurrentUser() {
    if (!isLoggedIn()) {
        return null;
    }
    
    global $pdo;
    
    try {
        $stmt = $pdo->prepare("SELECT * FROM users WHERE id = ? AND is_active = 1");
        $stmt->execute([$_SESSION['user_id']]);
        return $stmt->fetch(PDO::FETCH_ASSOC);
    } catch (PDOException $e) {
        return null;
    }
}

/**
 * Redirect to login page
 */
function redirectToLogin($returnUrl = '') {
    $loginUrl = BASE_URL . '/customer/login.php';
    if (!empty($returnUrl)) {
        $loginUrl .= '?return=' . urlencode($returnUrl);
    }
    header("Location: {$loginUrl}");
    exit;
}

/**
 * Get cart items count
 */
function getCartItemsCount() {
    if (!isset($_SESSION['cart']) || empty($_SESSION['cart'])) {
        return 0;
    }
    
    $count = 0;
    foreach ($_SESSION['cart'] as $item) {
        $count += $item['quantity'];
    }
    
    return $count;
}

/**
 * Get cart total
 */
function getCartTotal() {
    if (!isset($_SESSION['cart']) || empty($_SESSION['cart'])) {
        return 0;
    }
    
    $total = 0;
    foreach ($_SESSION['cart'] as $item) {
        $total += $item['price'] * $item['quantity'];
    }
    
    return $total;
}

/**
 * Add item to cart
 */
function addToCart($productId, $quantity = 1, $color = '', $size = '') {
    global $pdo;
    
    // Get product info
    try {
        $stmt = $pdo->prepare("SELECT * FROM products WHERE id = ? AND is_active = 1");
        $stmt->execute([$productId]);
        $product = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$product) {
            return false;
        }
        
        // Check stock
        if ($product['stock_quantity'] < $quantity) {
            return false;
        }
        
        $cartKey = $productId . '_' . $color . '_' . $size;
        
        if (!isset($_SESSION['cart'])) {
            $_SESSION['cart'] = [];
        }
        
        if (isset($_SESSION['cart'][$cartKey])) {
            $_SESSION['cart'][$cartKey]['quantity'] += $quantity;
        } else {
            $_SESSION['cart'][$cartKey] = [
                'product_id' => $productId,
                'name' => $product['name'],
                'price' => $product['sale_price'] ?: $product['price'],
                'image' => $product['main_image'],
                'quantity' => $quantity,
                'color' => $color,
                'size' => $size
            ];
        }
        
        return true;
        
    } catch (PDOException $e) {
        return false;
    }
}

/**
 * Remove item from cart
 */
function removeFromCart($cartKey) {
    if (isset($_SESSION['cart'][$cartKey])) {
        unset($_SESSION['cart'][$cartKey]);
        return true;
    }
    return false;
}

/**
 * Update cart item quantity
 */
function updateCartQuantity($cartKey, $quantity) {
    if (isset($_SESSION['cart'][$cartKey])) {
        if ($quantity <= 0) {
            unset($_SESSION['cart'][$cartKey]);
        } else {
            $_SESSION['cart'][$cartKey]['quantity'] = $quantity;
        }
        return true;
    }
    return false;
}

/**
 * Clear cart
 */
function clearCart() {
    unset($_SESSION['cart']);
}

/**
 * Generate pagination HTML
 */
function generatePagination($currentPage, $totalPages, $baseUrl, $queryParams = []) {
    if ($totalPages <= 1) {
        return '';
    }
    
    $html = '<div class="pagination-wrapper">';
    $html .= '<ul class="pagination">';
    
    // Previous page
    if ($currentPage > 1) {
        $prevUrl = $baseUrl . '?' . http_build_query(array_merge($queryParams, ['page' => $currentPage - 1]));
        $html .= '<li class="page-item"><a class="page-link" href="' . $prevUrl . '">Trước</a></li>';
    }
    
    // Page numbers
    $start = max(1, $currentPage - 2);
    $end = min($totalPages, $currentPage + 2);
    
    if ($start > 1) {
        $firstUrl = $baseUrl . '?' . http_build_query(array_merge($queryParams, ['page' => 1]));
        $html .= '<li class="page-item"><a class="page-link" href="' . $firstUrl . '">1</a></li>';
        if ($start > 2) {
            $html .= '<li class="page-item disabled"><span class="page-link">...</span></li>';
        }
    }
    
    for ($i = $start; $i <= $end; $i++) {
        if ($i == $currentPage) {
            $html .= '<li class="page-item active"><span class="page-link">' . $i . '</span></li>';
        } else {
            $pageUrl = $baseUrl . '?' . http_build_query(array_merge($queryParams, ['page' => $i]));
            $html .= '<li class="page-item"><a class="page-link" href="' . $pageUrl . '">' . $i . '</a></li>';
        }
    }
    
    if ($end < $totalPages) {
        if ($end < $totalPages - 1) {
            $html .= '<li class="page-item disabled"><span class="page-link">...</span></li>';
        }
        $lastUrl = $baseUrl . '?' . http_build_query(array_merge($queryParams, ['page' => $totalPages]));
        $html .= '<li class="page-item"><a class="page-link" href="' . $lastUrl . '">' . $totalPages . '</a></li>';
    }
    
    // Next page
    if ($currentPage < $totalPages) {
        $nextUrl = $baseUrl . '?' . http_build_query(array_merge($queryParams, ['page' => $currentPage + 1]));
        $html .= '<li class="page-item"><a class="page-link" href="' . $nextUrl . '">Sau</a></li>';
    }
    
    $html .= '</ul>';
    $html .= '</div>';
    
    return $html;
}

/**
 * Sanitize input
 */
function sanitizeInput($input) {
    if (is_array($input)) {
        return array_map('sanitizeInput', $input);
    }
    
    return htmlspecialchars(trim($input), ENT_QUOTES, 'UTF-8');
}

/**
 * Validate email
 */
function isValidEmail($email) {
    return filter_var($email, FILTER_VALIDATE_EMAIL) !== false;
}

/**
 * Validate phone number (Vietnamese format)
 */
function isValidPhone($phone) {
    $pattern = '/^(\+84|84|0)(3|5|7|8|9)([0-9]{8})$/';
    return preg_match($pattern, $phone);
}

/**
 * Generate random string
 */
function generateRandomString($length = 10) {
    return substr(str_shuffle('0123456789abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ'), 0, $length);
}

/**
 * Calculate shipping fee
 */
function calculateShippingFee($total, $province = '', $district = '') {
    // Basic shipping calculation
    if ($total >= 500000) { // Free shipping for orders >= 500k
        return 0;
    }
    
    // Base shipping fee
    $fee = 30000;
    
    // Add extra fee for remote areas (simplified)
    $remoteParts = ['Hà Giang', 'Cao Bằng', 'Lai Châu', 'Điện Biên', 'Sơn La'];
    foreach ($remoteParts as $remote) {
        if (stripos($province, $remote) !== false) {
            $fee += 20000;
            break;
        }
    }
    
    return $fee;
}

/**
 * Send email (basic implementation)
 */
function sendEmail($to, $subject, $message, $from = '') {
    if (empty($from)) {
        $from = SITE_EMAIL;
    }
    
    $headers = [
        'From: ' . $from,
        'Reply-To: ' . $from,
        'Content-Type: text/html; charset=UTF-8'
    ];
    
    return mail($to, $subject, $message, implode("\r\n", $headers));
}

/**
 * Log activity
 */
function logActivity($action, $details = '', $userId = null) {
    global $pdo;
    
    if ($userId === null && isLoggedIn()) {
        $userId = $_SESSION['user_id'];
    }
    
    try {
        $stmt = $pdo->prepare("INSERT INTO activity_logs (user_id, action, details, ip_address, created_at) VALUES (?, ?, ?, ?, NOW())");
        $stmt->execute([$userId, $action, $details, $_SERVER['REMOTE_ADDR'] ?? '']);
        return true;
    } catch (PDOException $e) {
        return false;
    }
}

/**
 * Get featured products
 */
function getFeaturedProducts($limit = 8) {
    global $pdo;
    
    try {
        $stmt = $pdo->prepare("
            SELECT p.*, c.name as category_name 
            FROM products p 
            LEFT JOIN categories c ON p.category_id = c.id 
            WHERE p.is_active = 1 AND p.is_featured = 1 
            ORDER BY p.created_at DESC 
            LIMIT ?
        ");
        $stmt->execute([$limit]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (PDOException $e) {
        return [];
    }
}

/**
 * Get latest products
 */
function getLatestProducts($limit = 8) {
    global $pdo;
    
    try {
        $stmt = $pdo->prepare("
            SELECT p.*, c.name as category_name 
            FROM products p 
            LEFT JOIN categories c ON p.category_id = c.id 
            WHERE p.is_active = 1 
            ORDER BY p.created_at DESC 
            LIMIT ?
        ");
        $stmt->execute([$limit]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (PDOException $e) {
        return [];
    }
}

/**
 * Get related products
 */
function getRelatedProducts($productId, $categoryId, $limit = 4) {
    global $pdo;
    
    try {
        $stmt = $pdo->prepare("
            SELECT p.*, c.name as category_name 
            FROM products p 
            LEFT JOIN categories c ON p.category_id = c.id 
            WHERE p.is_active = 1 AND p.category_id = ? AND p.id != ? 
            ORDER BY RAND() 
            LIMIT ?
        ");
        $stmt->execute([$categoryId, $productId, $limit]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (PDOException $e) {
        return [];
    }
}

?>