<?php
/**
 * REMOVE FROM CART API - Xóa sản phẩm khỏi giỏ hàng
 * File: customer/remove_from_cart.php
 * Hỗ trợ xóa đơn lẻ và xóa nhiều sản phẩm cùng lúc
 */

session_start();
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../config/config.php';

try {
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        throw new Exception('Invalid request method');
    }
    
    // Xử lý input từ JSON hoặc form data
    $input = json_decode(file_get_contents('php://input'), true);
    if (!$input) {
        $input = $_POST;
    }
    
    $action = $input['action'] ?? 'remove_item';
    
    // Lấy thông tin user
    $customer_id = $_SESSION['customer_id'] ?? null;
    $session_id = $customer_id ? null : ($_SESSION['session_id'] ?? session_id());
    
    if (!$session_id && !$customer_id) {
        $_SESSION['session_id'] = session_id();
        $session_id = $_SESSION['session_id'];
    }
    
    switch ($action) {
        case 'remove_item':
            // Xóa một sản phẩm
            $cart_id = (int)($input['cart_id'] ?? $input['id'] ?? 0);
            
            if ($cart_id <= 0) {
                throw new Exception('ID sản phẩm không hợp lệ');
            }
            
            // Kiểm tra quyền sở hữu và lấy thông tin sản phẩm
            $stmt = $pdo->prepare("
                SELECT gh.id, sp.ten_san_pham, bsp.ma_sku, kc.kich_co, ms.ten_mau
                FROM gio_hang gh
                JOIN bien_the_san_pham bsp ON gh.bien_the_id = bsp.id
                JOIN san_pham_chinh sp ON bsp.san_pham_id = sp.id
                JOIN kich_co kc ON bsp.kich_co_id = kc.id
                JOIN mau_sac ms ON bsp.mau_sac_id = ms.id
                WHERE gh.id = ? AND (gh.khach_hang_id = ? OR gh.session_id = ?)
            ");
            $stmt->execute([$cart_id, $customer_id, $session_id]);
            $item_info = $stmt->fetch();
            
            if (!$item_info) {
                throw new Exception('Sản phẩm không tồn tại trong giỏ hàng của bạn');
            }
            
            // Xóa sản phẩm
            $stmt = $pdo->prepare("
                DELETE FROM gio_hang 
                WHERE id = ? AND (khach_hang_id = ? OR session_id = ?)
            ");
            $result = $stmt->execute([$cart_id, $customer_id, $session_id]);
            
            if (!$result || $stmt->rowCount() === 0) {
                throw new Exception('Không thể xóa sản phẩm');
            }
            
            $response_data = [
                'removed_item' => [
                    'id' => $cart_id,
                    'name' => $item_info['ten_san_pham'],
                    'sku' => $item_info['ma_sku'],
                    'size' => $item_info['kich_co'],
                    'color' => $item_info['ten_mau']
                ]
            ];
            
            break;
            
        case 'remove_multiple':
        case 'remove_selected':
            // Xóa nhiều sản phẩm
            $cart_ids = [];
            
            // Xử lý nhiều format input khác nhau
            if (isset($input['cart_ids']) && is_array($input['cart_ids'])) {
                $cart_ids = $input['cart_ids'];
            } elseif (isset($input['ids']) && is_array($input['ids'])) {
                $cart_ids = $input['ids'];
            } elseif (isset($input['items']) && is_array($input['items'])) {
                $cart_ids = $input['items'];
            }
            
            // Lọc và validate IDs
            $cart_ids = array_filter(array_map('intval', $cart_ids));
            
            if (empty($cart_ids)) {
                throw new Exception('Không có sản phẩm nào được chọn để xóa');
            }
            
            // Kiểm tra quyền sở hữu các items
            $placeholders = str_repeat('?,', count($cart_ids) - 1) . '?';
            $params = array_merge($cart_ids, [$customer_id, $session_id]);
            
            $stmt = $pdo->prepare("
                SELECT gh.id, sp.ten_san_pham, bsp.ma_sku
                FROM gio_hang gh
                JOIN bien_the_san_pham bsp ON gh.bien_the_id = bsp.id
                JOIN san_pham_chinh sp ON bsp.san_pham_id = sp.id
                WHERE gh.id IN ($placeholders) AND (gh.khach_hang_id = ? OR gh.session_id = ?)
            ");
            $stmt->execute($params);
            $valid_items = $stmt->fetchAll();
            
            if (count($valid_items) !== count($cart_ids)) {
                throw new Exception('Một số sản phẩm không thuộc về giỏ hàng của bạn');
            }
            
            // Xóa các items
            $stmt = $pdo->prepare("
                DELETE FROM gio_hang 
                WHERE id IN ($placeholders) AND (khach_hang_id = ? OR session_id = ?)
            ");
            $result = $stmt->execute($params);
            
            if (!$result) {
                throw new Exception('Có lỗi xảy ra khi xóa sản phẩm');
            }
            
            $deleted_count = $stmt->rowCount();
            
            $response_data = [
                'removed_items' => $valid_items,
                'removed_count' => $deleted_count,
                'removed_ids' => $cart_ids
            ];
            
            break;
            
        case 'clear_cart':
            // Xóa toàn bộ giỏ hàng
            $confirm = $input['confirm'] ?? false;
            
            if (!$confirm) {
                throw new Exception('Vui lòng xác nhận để xóa toàn bộ giỏ hàng');
            }
            
            // Đếm số items trước khi xóa
            $stmt = $pdo->prepare("
                SELECT COUNT(*) as item_count 
                FROM gio_hang 
                WHERE khach_hang_id = ? OR session_id = ?
            ");
            $stmt->execute([$customer_id, $session_id]);
            $item_count = $stmt->fetchColumn();
            
            if ($item_count == 0) {
                throw new Exception('Giỏ hàng đã trống');
            }
            
            // Xóa tất cả
            $stmt = $pdo->prepare("
                DELETE FROM gio_hang 
                WHERE khach_hang_id = ? OR session_id = ?
            ");
            $result = $stmt->execute([$customer_id, $session_id]);
            
            if (!$result) {
                throw new Exception('Có lỗi xảy ra khi xóa giỏ hàng');
            }
            
            $response_data = [
                'cleared_count' => $item_count
            ];
            
            break;
            
        case 'remove_by_variant':
            // Xóa tất cả items của một variant
            $variant_id = (int)($input['variant_id'] ?? 0);
            
            if ($variant_id <= 0) {
                throw new Exception('ID biến thể không hợp lệ');
            }
            
            // Lấy thông tin variant
            $stmt = $pdo->prepare("
                SELECT sp.ten_san_pham, bsp.ma_sku, kc.kich_co, ms.ten_mau
                FROM bien_the_san_pham bsp
                JOIN san_pham_chinh sp ON bsp.san_pham_id = sp.id
                JOIN kich_co kc ON bsp.kich_co_id = kc.id
                JOIN mau_sac ms ON bsp.mau_sac_id = ms.id
                WHERE bsp.id = ?
            ");
            $stmt->execute([$variant_id]);
            $variant_info = $stmt->fetch();
            
            if (!$variant_info) {
                throw new Exception('Biến thể sản phẩm không tồn tại');
            }
            
            // Xóa tất cả items của variant này
            $stmt = $pdo->prepare("
                DELETE FROM gio_hang 
                WHERE bien_the_id = ? AND (khach_hang_id = ? OR session_id = ?)
            ");
            $result = $stmt->execute([$variant_id, $customer_id, $session_id]);
            
            $deleted_count = $stmt->rowCount();
            
            if ($deleted_count === 0) {
                throw new Exception('Không tìm thấy sản phẩm này trong giỏ hàng');
            }
            
            $response_data = [
                'removed_variant' => $variant_info,
                'removed_count' => $deleted_count
            ];
            
            break;
            
        default:
            throw new Exception('Hành động không được hỗ trợ');
    }
    
    // Tính tổng giỏ hàng sau khi xóa
    $cart_totals = calculateCartTotals($pdo, $customer_id, $session_id);
    
    // Cập nhật session cart count
    $_SESSION['cart_count'] = $cart_totals['item_count'];
    
    // Response thành công
    echo json_encode([
        'success' => true,
        'message' => getSuccessMessage($action, $response_data),
        'data' => $response_data,
        'cart_totals' => $cart_totals,
        'cart_empty' => $cart_totals['item_count'] == 0,
        'timestamp' => time()
    ]);
    
    // Log action for analytics
    $log_data = [
        'action' => $action,
        'customer_id' => $customer_id,
        'session_id' => $session_id,
        'removed_count' => $response_data['removed_count'] ?? 1,
        'timestamp' => date('Y-m-d H:i:s')
    ];
    error_log("Cart remove action: " . json_encode($log_data));
    
} catch (Exception $e) {
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage(),
        'timestamp' => time()
    ]);
    
    // Log lỗi
    error_log("Cart remove error: " . $e->getMessage() . " - Request: " . json_encode($input));
}

/**
 * Tính tổng giỏ hàng
 */
function calculateCartTotals($pdo, $customer_id, $session_id) {
    $stmt = $pdo->prepare("
        SELECT 
            COUNT(*) as item_count,
            SUM(gh.so_luong) as total_quantity,
            SUM(gh.so_luong * bsp.gia_ban) as subtotal
        FROM gio_hang gh
        JOIN bien_the_san_pham bsp ON gh.bien_the_id = bsp.id
        WHERE (gh.khach_hang_id = ? OR gh.session_id = ?)
    ");
    $stmt->execute([$customer_id, $session_id]);
    $totals = $stmt->fetch();
    
    $subtotal = $totals['subtotal'] ?? 0;
    $shipping_fee = $subtotal >= 500000 ? 0 : 30000;
    $tax = $subtotal * 0.1;
    $total = $subtotal + $shipping_fee + $tax;
    
    return [
        'item_count' => $totals['item_count'] ?? 0,
        'total_quantity' => $totals['total_quantity'] ?? 0,
        'subtotal' => $subtotal,
        'subtotal_formatted' => formatPrice($subtotal),
        'shipping_fee' => $shipping_fee,
        'shipping_fee_formatted' => $shipping_fee > 0 ? formatPrice($shipping_fee) : 'Miễn phí',
        'tax' => $tax,
        'tax_formatted' => formatPrice($tax),
        'total' => $total,
        'total_formatted' => formatPrice($total),
        'free_shipping_threshold' => 500000,
        'free_shipping_remaining' => max(0, 500000 - $subtotal),
        'is_free_shipping' => $subtotal >= 500000
    ];
}

/**
 * Lấy thông báo thành công
 */
function getSuccessMessage($action, $data) {
    switch ($action) {
        case 'remove_item':
            return 'Đã xóa "' . $data['removed_item']['name'] . '" khỏi giỏ hàng';
            
        case 'remove_multiple':
        case 'remove_selected':
            $count = $data['removed_count'];
            return "Đã xóa $count sản phẩm khỏi giỏ hàng";
            
        case 'clear_cart':
            $count = $data['cleared_count'];
            return "Đã xóa tất cả $count sản phẩm trong giỏ hàng";
            
        case 'remove_by_variant':
            $name = $data['removed_variant']['ten_san_pham'];
            $count = $data['removed_count'];
            return "Đã xóa $count items của \"$name\" khỏi giỏ hàng";
            
        default:
            return 'Đã xóa sản phẩm thành công';
    }
}

/**
 * Format giá tiền
 */
function formatPrice($price) {
    return number_format($price, 0, ',', '.') . 'đ';
}
?>