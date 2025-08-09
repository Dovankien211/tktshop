<?php
/**
 * UPDATE CART API - Cập nhật số lượng sản phẩm trong giỏ hàng
 * File: customer/update_cart.php
 * Tương thích với hệ thống checkbox và database cart
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
    
    $action = $input['action'] ?? '';
    
    // Lấy thông tin user
    $customer_id = $_SESSION['customer_id'] ?? null;
    $session_id = $customer_id ? null : ($_SESSION['session_id'] ?? session_id());
    
    if (!$session_id && !$customer_id) {
        $_SESSION['session_id'] = session_id();
        $session_id = $_SESSION['session_id'];
    }
    
    switch ($action) {
        case 'update_quantity':
            $cart_id = (int)($input['cart_id'] ?? 0);
            $new_quantity = (int)($input['quantity'] ?? 0);
            
            if ($cart_id <= 0) {
                throw new Exception('ID giỏ hàng không hợp lệ');
            }
            
            if ($new_quantity < 0) {
                throw new Exception('Số lượng không hợp lệ');
            }
            
            // Kiểm tra quyền sở hữu item
            $stmt = $pdo->prepare("
                SELECT gh.*, bsp.so_luong_ton_kho, bsp.gia_ban, sp.ten_san_pham
                FROM gio_hang gh
                JOIN bien_the_san_pham bsp ON gh.bien_the_id = bsp.id
                JOIN san_pham_chinh sp ON bsp.san_pham_id = sp.id
                WHERE gh.id = ? AND (gh.khach_hang_id = ? OR gh.session_id = ?)
            ");
            $stmt->execute([$cart_id, $customer_id, $session_id]);
            $cart_item = $stmt->fetch();
            
            if (!$cart_item) {
                throw new Exception('Sản phẩm không tồn tại trong giỏ hàng');
            }
            
            // Nếu quantity = 0, xóa item
            if ($new_quantity == 0) {
                $stmt = $pdo->prepare("DELETE FROM gio_hang WHERE id = ?");
                $stmt->execute([$cart_id]);
                
                $response_data = [
                    'action' => 'removed',
                    'removed_item' => $cart_item['ten_san_pham']
                ];
            } else {
                // Kiểm tra tồn kho
                if ($new_quantity > $cart_item['so_luong_ton_kho']) {
                    throw new Exception("Số lượng trong kho không đủ. Còn lại: {$cart_item['so_luong_ton_kho']}");
                }
                
                // Cập nhật quantity
                $stmt = $pdo->prepare("UPDATE gio_hang SET so_luong = ? WHERE id = ?");
                $stmt->execute([$new_quantity, $cart_id]);
                
                $subtotal = $new_quantity * $cart_item['gia_ban'];
                $response_data = [
                    'action' => 'updated',
                    'new_quantity' => $new_quantity,
                    'subtotal' => $subtotal,
                    'subtotal_formatted' => formatPrice($subtotal)
                ];
            }
            
            break;
            
        case 'update_multiple':
            $updates = $input['updates'] ?? [];
            
            if (empty($updates) || !is_array($updates)) {
                throw new Exception('Dữ liệu cập nhật không hợp lệ');
            }
            
            $pdo->beginTransaction();
            
            try {
                foreach ($updates as $update) {
                    $cart_id = (int)($update['cart_id'] ?? 0);
                    $new_quantity = (int)($update['quantity'] ?? 0);
                    
                    if ($cart_id <= 0) continue;
                    
                    // Kiểm tra quyền sở hữu
                    $stmt = $pdo->prepare("
                        SELECT gh.*, bsp.so_luong_ton_kho
                        FROM gio_hang gh
                        JOIN bien_the_san_pham bsp ON gh.bien_the_id = bsp.id
                        WHERE gh.id = ? AND (gh.khach_hang_id = ? OR gh.session_id = ?)
                    ");
                    $stmt->execute([$cart_id, $customer_id, $session_id]);
                    $cart_item = $stmt->fetch();
                    
                    if (!$cart_item) continue;
                    
                    if ($new_quantity == 0) {
                        // Xóa item
                        $pdo->prepare("DELETE FROM gio_hang WHERE id = ?")
                            ->execute([$cart_id]);
                    } else {
                        // Kiểm tra tồn kho
                        if ($new_quantity > $cart_item['so_luong_ton_kho']) {
                            throw new Exception("Sản phẩm ID {$cart_id}: Số lượng vượt quá tồn kho");
                        }
                        
                        // Cập nhật
                        $pdo->prepare("UPDATE gio_hang SET so_luong = ? WHERE id = ?")
                            ->execute([$new_quantity, $cart_id]);
                    }
                }
                
                $pdo->commit();
                $response_data = ['action' => 'bulk_updated'];
                
            } catch (Exception $e) {
                $pdo->rollback();
                throw $e;
            }
            
            break;
            
        case 'remove_item':
            $cart_id = (int)($input['cart_id'] ?? 0);
            
            if ($cart_id <= 0) {
                throw new Exception('ID giỏ hàng không hợp lệ');
            }
            
            // Kiểm tra quyền sở hữu và lấy thông tin sản phẩm
            $stmt = $pdo->prepare("
                SELECT sp.ten_san_pham
                FROM gio_hang gh
                JOIN bien_the_san_pham bsp ON gh.bien_the_id = bsp.id
                JOIN san_pham_chinh sp ON bsp.san_pham_id = sp.id
                WHERE gh.id = ? AND (gh.khach_hang_id = ? OR gh.session_id = ?)
            ");
            $stmt->execute([$cart_id, $customer_id, $session_id]);
            $product_name = $stmt->fetchColumn();
            
            if (!$product_name) {
                throw new Exception('Sản phẩm không tồn tại trong giỏ hàng');
            }
            
            // Xóa item
            $stmt = $pdo->prepare("DELETE FROM gio_hang WHERE id = ?");
            $stmt->execute([$cart_id]);
            
            $response_data = [
                'action' => 'removed',
                'removed_item' => $product_name
            ];
            
            break;
            
        case 'remove_multiple':
            $cart_ids = $input['cart_ids'] ?? [];
            
            if (empty($cart_ids) || !is_array($cart_ids)) {
                throw new Exception('Danh sách ID không hợp lệ');
            }
            
            // Kiểm tra quyền sở hữu
            $placeholders = str_repeat('?,', count($cart_ids) - 1) . '?';
            $params = array_merge($cart_ids, [$customer_id, $session_id]);
            
            $stmt = $pdo->prepare("
                SELECT COUNT(*) 
                FROM gio_hang 
                WHERE id IN ($placeholders) AND (khach_hang_id = ? OR session_id = ?)
            ");
            $stmt->execute($params);
            $valid_count = $stmt->fetchColumn();
            
            if ($valid_count != count($cart_ids)) {
                throw new Exception('Một số sản phẩm không thuộc về giỏ hàng của bạn');
            }
            
            // Xóa các items
            $stmt = $pdo->prepare("
                DELETE FROM gio_hang 
                WHERE id IN ($placeholders) AND (khach_hang_id = ? OR session_id = ?)
            ");
            $stmt->execute($params);
            
            $response_data = [
                'action' => 'bulk_removed',
                'removed_count' => count($cart_ids)
            ];
            
            break;
            
        case 'clear_cart':
            $stmt = $pdo->prepare("
                DELETE FROM gio_hang 
                WHERE khach_hang_id = ? OR session_id = ?
            ");
            $stmt->execute([$customer_id, $session_id]);
            
            $response_data = ['action' => 'cleared'];
            break;
            
        case 'get_cart_totals':
            // Chỉ tính tổng, không thay đổi gì
            $response_data = ['action' => 'totals_only'];
            break;
            
        case 'sync_session_to_user':
            // Đồng bộ giỏ hàng từ session sang user khi đăng nhập
            if (!$customer_id) {
                throw new Exception('User chưa đăng nhập');
            }
            
            $old_session_id = $input['old_session_id'] ?? '';
            if (!$old_session_id) {
                throw new Exception('Session ID không hợp lệ');
            }
            
            // Chuyển tất cả items từ session sang user
            $stmt = $pdo->prepare("
                UPDATE gio_hang 
                SET khach_hang_id = ?, session_id = NULL 
                WHERE session_id = ?
            ");
            $stmt->execute([$customer_id, $old_session_id]);
            
            $response_data = [
                'action' => 'synced',
                'synced_items' => $stmt->rowCount()
            ];
            break;
            
        case 'add_to_cart':
            $variant_id = (int)($input['variant_id'] ?? 0);
            $quantity = max(1, (int)($input['quantity'] ?? 1));
            
            if ($variant_id <= 0) {
                throw new Exception('ID biến thể không hợp lệ');
            }
            
            // Kiểm tra biến thể tồn tại và có đủ hàng không
            $stmt = $pdo->prepare("
                SELECT bsp.*, sp.ten_san_pham
                FROM bien_the_san_pham bsp
                JOIN san_pham_chinh sp ON bsp.san_pham_id = sp.id
                WHERE bsp.id = ? AND bsp.trang_thai = 'hoat_dong'
            ");
            $stmt->execute([$variant_id]);
            $variant = $stmt->fetch();
            
            if (!$variant) {
                throw new Exception('Sản phẩm không tồn tại hoặc đã ngừng bán');
            }
            
            if ($variant['so_luong_ton_kho'] < $quantity) {
                throw new Exception("Chỉ còn {$variant['so_luong_ton_kho']} sản phẩm trong kho");
            }
            
            // Kiểm tra đã có trong giỏ hàng chưa
            $stmt = $pdo->prepare("
                SELECT * FROM gio_hang 
                WHERE bien_the_id = ? AND (khach_hang_id = ? OR session_id = ?)
            ");
            $stmt->execute([$variant_id, $customer_id, $session_id]);
            $existing = $stmt->fetch();
            
            if ($existing) {
                // Cập nhật số lượng
                $new_quantity = $existing['so_luong'] + $quantity;
                
                if ($new_quantity > $variant['so_luong_ton_kho']) {
                    throw new Exception("Tổng số lượng vượt quá tồn kho ({$variant['so_luong_ton_kho']})");
                }
                
                $stmt = $pdo->prepare("UPDATE gio_hang SET so_luong = ? WHERE id = ?");
                $stmt->execute([$new_quantity, $existing['id']]);
                
                $response_data = [
                    'action' => 'updated_existing',
                    'cart_id' => $existing['id'],
                    'new_quantity' => $new_quantity,
                    'product_name' => $variant['ten_san_pham']
                ];
            } else {
                // Thêm mới
                $stmt = $pdo->prepare("
                    INSERT INTO gio_hang (khach_hang_id, session_id, bien_the_id, so_luong, gia_tai_thoi_diem, ngay_them)
                    VALUES (?, ?, ?, ?, ?, NOW())
                ");
                $stmt->execute([$customer_id, $session_id, $variant_id, $quantity, $variant['gia_ban']]);
                
                $response_data = [
                    'action' => 'added_new',
                    'cart_id' => $pdo->lastInsertId(),
                    'quantity' => $quantity,
                    'product_name' => $variant['ten_san_pham']
                ];
            }
            
            break;
            
        case 'calculate_selected':
            $cart_ids = $input['cart_ids'] ?? [];
            
            if (empty($cart_ids) || !is_array($cart_ids)) {
                // Trả về totals rỗng
                $response_data = [
                    'action' => 'calculated',
                    'totals' => [
                        'selected_count' => 0,
                        'selected_quantity' => 0,
                        'selected_subtotal' => 0,
                        'selected_subtotal_formatted' => formatPrice(0),
                        'shipping_fee' => 0,
                        'shipping_fee_formatted' => 'Miễn phí',
                        'tax' => 0,
                        'tax_formatted' => formatPrice(0),
                        'total' => 0,
                        'total_formatted' => formatPrice(0),
                        'free_shipping_remaining' => 500000
                    ]
                ];
            } else {
                // Tính tổng cho items đã chọn
                $placeholders = str_repeat('?,', count($cart_ids) - 1) . '?';
                $params = array_merge($cart_ids, [$customer_id, $session_id]);
                
                $stmt = $pdo->prepare("
                    SELECT 
                        COUNT(*) as selected_count,
                        SUM(gh.so_luong) as selected_quantity,
                        SUM(gh.so_luong * bsp.gia_ban) as selected_subtotal
                    FROM gio_hang gh
                    JOIN bien_the_san_pham bsp ON gh.bien_the_id = bsp.id
                    WHERE gh.id IN ($placeholders) AND (gh.khach_hang_id = ? OR gh.session_id = ?)
                ");
                $stmt->execute($params);
                $totals = $stmt->fetch();
                
                $subtotal = $totals['selected_subtotal'] ?? 0;
                $shipping_fee = $subtotal >= 500000 ? 0 : 30000;
                $tax = $subtotal * 0.1;
                $total = $subtotal + $shipping_fee + $tax;
                
                $response_data = [
                    'action' => 'calculated',
                    'totals' => [
                        'selected_count' => $totals['selected_count'] ?? 0,
                        'selected_quantity' => $totals['selected_quantity'] ?? 0,
                        'selected_subtotal' => $subtotal,
                        'selected_subtotal_formatted' => formatPrice($subtotal),
                        'shipping_fee' => $shipping_fee,
                        'shipping_fee_formatted' => $shipping_fee > 0 ? formatPrice($shipping_fee) : 'Miễn phí',
                        'tax' => $tax,
                        'tax_formatted' => formatPrice($tax),
                        'total' => $total,
                        'total_formatted' => formatPrice($total),
                        'free_shipping_remaining' => max(0, 500000 - $subtotal)
                    ]
                ];
            }
            
            break;
            
        case 'set_checkout_items':
            $cart_ids = $input['cart_ids'] ?? [];
            
            if (empty($cart_ids) || !is_array($cart_ids)) {
                throw new Exception('Không có sản phẩm nào được chọn');
            }
            
            // Validate tất cả cart_ids thuộc về user này
            $placeholders = str_repeat('?,', count($cart_ids) - 1) . '?';
            $params = array_merge($cart_ids, [$customer_id, $session_id]);
            
            $stmt = $pdo->prepare("
                SELECT COUNT(*) 
                FROM gio_hang 
                WHERE id IN ($placeholders) AND (khach_hang_id = ? OR session_id = ?)
            ");
            $stmt->execute($params);
            $valid_count = $stmt->fetchColumn();
            
            if ($valid_count != count($cart_ids)) {
                throw new Exception('Một số sản phẩm không thuộc về giỏ hàng của bạn');
            }
            
            // Lưu vào session
            $_SESSION['checkout_items'] = $cart_ids;
            
            $response_data = [
                'action' => 'checkout_prepared',
                'selected_count' => count($cart_ids)
            ];
            
            break;
            
        default:
            throw new Exception('Action không được hỗ trợ: ' . $action);
    }
    
    // Tính tổng giỏ hàng sau khi thực hiện action
    $cart_totals = calculateCartTotals($pdo, $customer_id, $session_id);
    
    // Cập nhật session cart count cho header
    $_SESSION['cart_count'] = $cart_totals['item_count'];
    
    // Response thành công
    echo json_encode([
        'success' => true,
        'message' => getSuccessMessage($action, $response_data),
        'data' => $response_data,
        'cart_totals' => $cart_totals,
        'timestamp' => time()
    ]);
    
} catch (Exception $e) {
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage(),
        'timestamp' => time()
    ]);
    
    // Log lỗi
    error_log("Cart update error: " . $e->getMessage() . " - Request: " . json_encode($input));
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
    $shipping_fee = $subtotal >= 500000 ? 0 : 30000; // Miễn phí ship từ 500k
    $tax = $subtotal * 0.1; // Thuế 10%
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
 * Lấy thông báo thành công theo action
 */
function getSuccessMessage($action, $data) {
    switch ($action) {
        case 'update_quantity':
            return $data['action'] === 'removed' 
                ? 'Đã xóa sản phẩm khỏi giỏ hàng' 
                : 'Đã cập nhật số lượng thành công';
                
        case 'update_multiple':
            return 'Đã cập nhật giỏ hàng thành công';
            
        case 'remove_item':
            return 'Đã xóa "' . $data['removed_item'] . '" khỏi giỏ hàng';
            
        case 'remove_multiple':
            return "Đã xóa {$data['removed_count']} sản phẩm khỏi giỏ hàng";
            
        case 'clear_cart':
            return 'Đã xóa tất cả sản phẩm trong giỏ hàng';
            
        case 'sync_session_to_user':
            return "Đã đồng bộ {$data['synced_items']} sản phẩm vào tài khoản";
            
        case 'add_to_cart':
            return $data['action'] === 'updated_existing'
                ? "Đã cập nhật số lượng \"{$data['product_name']}\""
                : "Đã thêm \"{$data['product_name']}\" vào giỏ hàng";
                
        case 'calculate_selected':
            return 'Đã tính tổng cho sản phẩm đã chọn';
            
        case 'set_checkout_items':
            return "Đã chuẩn bị {$data['selected_count']} sản phẩm để thanh toán";
            
        case 'get_cart_totals':
            return 'Đã tính tổng giỏ hàng';
            
        default:
            return 'Thao tác thành công';
    }
}

/**
 * Format giá tiền
 */
function formatPrice($price) {
    return number_format($price, 0, ',', '.') . 'đ';
}
?>