<?php
// customer/product_detail_fixed.php - ENHANCED VERSION
/**
 * Chi tiết sản phẩm - Phiên bản cải tiến với robust error handling
 * 🔧 FIXED: Xử lý toàn diện các trường hợp lỗi và fallback logic
 */
session_start();

require_once '../config/database.php';
require_once '../config/config.php';
require_once 'product_detail_helper.php'; // Include helper functions

// Handle automatic URL redirects
handleProductUrlRedirects();

// Nhận parameters từ URL
$id = (int)($_GET['id'] ?? 0);
$slug = trim($_GET['slug'] ?? '');

// Debug logging
debugProductSearch($slug, $id, null);

// Validate input
if (!$id && !$slug) {
    alert('Vui lòng cung cấp thông tin sản phẩm hợp lệ!', 'error');
    redirect('/customer/products.php');
}

// Tìm sản phẩm với fallback logic
$product_data = findProductWithFallback($pdo, $slug, $id);

if (!$product_data) {
    // Fallback cuối cùng: Tìm sản phẩm đầu tiên có sẵn
    try {
        $fallback = $pdo->query("
            SELECT sp.*, dm.ten_danh_muc,
                   COALESCE(sp.gia_khuyen_mai, sp.gia_goc) as gia_hien_tai
            FROM san_pham_chinh sp
            LEFT JOIN danh_muc_giay dm ON sp.danh_muc_id = dm.id
            WHERE sp.trang_thai = 'hoat_dong'
            ORDER BY sp.san_pham_noi_bat DESC, sp.luot_xem DESC
            LIMIT 1
        ")->fetch();
        
        if ($fallback) {
            alert('Sản phẩm bạn tìm không tồn tại. Đây là sản phẩm gợi ý cho bạn.', 'warning');
            $product_data = ['product' => $fallback, 'table' => 'san_pham_chinh'];
        }
    } catch (Exception $e) {
        error_log("Fallback product search failed: " . $e->getMessage());
    }
}

if (!$product_data) {
    alert('Không tìm thấy sản phẩm. Vui lòng thử lại!', 'error');
    redirect('/customer/products.php');
}

$product = $product_data['product'];
$product_table = $product_data['table'];

// Debug log kết quả
debugProductSearch($slug, $id, $product_data);

// Cập nhật lượt xem
updateProductViews($pdo, $product['id'], $product_table);

// Đảm bảo sản phẩm có slug
if ($product_table === 'san_pham_chinh' && empty($product['slug'])) {
    $new_slug = ensureProductSlug($pdo, $product['id'], $product['ten_san_pham'], $product_table);
    if ($new_slug) {
        $product['slug'] = $new_slug;
    }
}

// Lấy variants sản phẩm
$variant_data = getProductVariants($pdo, $product['id'], $product_table);
$variants = $variant_data['variants'];
$sizes = $variant_data['sizes'];
$colors = $variant_data['colors'];
$variant_matrix = $variant_data['variant_matrix'];

// Lấy sản phẩm liên quan
$related_products = getRelatedProducts($pdo, $product, $product_table);

// Xử lý album ảnh
$product_images = processProductImages($product, $product_table);

// Tạo breadcrumb
$breadcrumb = generateProductBreadcrumb($product, $product_table);

// Xử lý AJAX thêm vào giỏ hàng
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['action']) && $_POST['action'] == 'add_to_cart') {
    ob_clean();
    header('Content-Type: application/json');
    
    $customer_id = $_SESSION['customer_id'] ?? null;
    $session_id = $customer_id ? null : ($_SESSION['session_id'] ?? session_id());
    
    if (!$session_id && !$customer_id) {
        $_SESSION['session_id'] = session_id();
        $session_id = $_SESSION['session_id'];
    }
    
    try {
        if ($product_table == 'products') {
            // Xử lý cho bảng products
            $so_luong = max(1, (int)($_POST['so_luong'] ?? 1));
            
            // Kiểm tra tồn kho
            if (isset($product['stock_quantity']) && $product['stock_quantity'] < $so_luong) {
                echo json_encode([
                    'success' => false, 
                    'message' => 'Không đủ hàng trong kho! Còn lại: ' . $product['stock_quantity']
                ]);
                exit;
            }
            
            // Kiểm tra sản phẩm đã có trong giỏ hàng chưa
            $check_cart = $pdo->prepare("
                SELECT * FROM gio_hang 
                WHERE san_pham_id = ? 
                AND (khach_hang_id = ? OR session_id = ?)
                AND bien_the_id IS NULL
            ");
            $check_cart->execute([$product['id'], $customer_id, $session_id]);
            $existing_item = $check_cart->fetch();
            
            if ($existing_item) {
                // Cập nhật số lượng
                $new_quantity = $existing_item['so_luong'] + $so_luong;
                if (isset($product['stock_quantity']) && $new_quantity > $product['stock_quantity']) {
                    echo json_encode([
                        'success' => false, 
                        'message' => 'Không đủ hàng trong kho! Tối đa có thể mua: ' . $product['stock_quantity']
                    ]);
                    exit;
                }