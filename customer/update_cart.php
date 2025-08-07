<?php
/**
 * UPDATE CART - Cập nhật số lượng sản phẩm trong giỏ hàng
 * File: customer/update_cart.php
 */

session_start();
header('Content-Type: application/json');

require_once __DIR__ . '/../config/database.php';

try {
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        throw new Exception('Invalid request method');
    }
    
    $input = json_decode(file_get_contents('php://input'), true);
    if (!$input) {
        $input = $_POST;
    }
    
    $cart_index = (int)($input['cart_index'] ?? -1);
    $new_quantity = (int)($input['quantity'] ?? 0);
    
    if ($cart_index < 0) {
        throw new Exception('Index giỏ hàng không hợp lệ');
    }
    
    if ($new_quantity < 0) {
        throw new Exception('Số lượng không hợp lệ');
    }
    
    if (!isset($_SESSION['cart']) || !isset($_SESSION['cart'][$cart_index])) {
        throw new Exception('Sản phẩm không tồn tại trong giỏ hàng');
    }
    
    $cart_item = $_SESSION['cart'][$cart_index];
    
    // Nếu quantity = 0, xóa item
    if ($new_quantity == 0) {
        unset($_SESSION['cart'][$cart_index]);
        $_SESSION['cart'] = array_values($_SESSION['cart']); // Reindex array
    } else {
        // Kiểm tra stock nếu có variant
        if ($cart_item['variant_id']) {
            $stmt = $pdo->prepare("SELECT so_luong_ton_kho FROM bien_the_san_pham WHERE id = ?");
            $stmt->execute([$cart_item['variant_id']]);
            $stock = $stmt->fetchColumn();
            
            if ($new_quantity > $stock) {
                throw new Exception("Số lượng trong kho không đủ. Còn lại: {$stock}");
            }
        }
        
        // Cập nhật quantity
        $_SESSION['cart'][$cart_index]['quantity'] = $new_quantity;
    }
    
    // Tính tổng lại
    $total_items = 0;
    $total_amount = 0;
    foreach ($_SESSION['cart'] as $item) {
        $total_items += $item['quantity'];
        $total_amount += $item['price'] * $item['quantity'];
    }
    
    // Sync với database nếu user đã đăng nhập
    if (isset($_SESSION['customer_id'])) {
        try {
            $stmt = $pdo->prepare("DELETE FROM gio_hang WHERE khach_hang_id = ?");
            $stmt->execute([$_SESSION['customer_id']]);
            
            foreach ($_SESSION['cart'] as $cart_item) {
                $stmt = $pdo->prepare("
                    INSERT INTO gio_hang (
                        khach_hang_id, san_pham_id, bien_the_id, 
                        so_luong, gia_tai_thoi_diem, ngay_them
                    ) VALUES (?, ?, ?, ?, ?, NOW())
                ");
                $stmt->execute([
                    $_SESSION['customer_id'],
                    $cart_item['product_id'],
                    $cart_item['variant_id'],
                    $cart_item['quantity'],
                    $cart_item['price']
                ]);
            }
        } catch (Exception $e) {
            error_log("Sync cart to DB failed: " . $e->getMessage());
        }
    }
    
    echo json_encode([
        'success' => true,
        'message' => $new_quantity == 0 ? 'Đã xóa sản phẩm khỏi giỏ hàng' : 'Đã cập nhật số lượng',
        'data' => [
            'cart_count' => $total_items,
            'cart_total' => $total_amount,
            'updated_quantity' => $new_quantity,
            'cart_items' => $_SESSION['cart']
        ]
    ]);
    
} catch (Exception $e) {
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ]);
}
?>