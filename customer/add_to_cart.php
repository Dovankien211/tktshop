<?php
/**
 * ADD TO CART - Thêm sản phẩm vào giỏ hàng
 * File: customer/add_to_cart.php
 * Database: Sử dụng tên bảng tiếng Việt
 */

session_start();
header('Content-Type: application/json');

// Include database
require_once __DIR__ . '/../config/database.php';

try {
    // Kiểm tra method
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        throw new Exception('Invalid request method');
    }
    
    // Lấy data từ POST/JSON
    $input = json_decode(file_get_contents('php://input'), true);
    if (!$input) {
        $input = $_POST;
    }
    
    // Validate input
    $product_id = (int)($input['product_id'] ?? 0);
    $quantity = (int)($input['quantity'] ?? 1);
    $size_id = (int)($input['size_id'] ?? 0);
    $color_id = (int)($input['color_id'] ?? 0);
    
    if ($product_id <= 0) {
        throw new Exception('ID sản phẩm không hợp lệ');
    }
    
    if ($quantity <= 0) {
        throw new Exception('Số lượng không hợp lệ');
    }
    
    // Kiểm tra sản phẩm tồn tại trong bảng san_pham_chinh
    $stmt = $pdo->prepare("SELECT * FROM san_pham_chinh WHERE id = ? AND trang_thai = 'hoat_dong'");
    $stmt->execute([$product_id]);
    $product = $stmt->fetch();
    
    if (!$product) {
        throw new Exception('Sản phẩm không tồn tại hoặc đã ngừng bán');
    }
    
    // Kiểm tra size nếu có
    $size_info = null;
    if ($size_id > 0) {
        $stmt = $pdo->prepare("SELECT * FROM kich_co WHERE id = ? AND trang_thai = 'hoat_dong'");
        $stmt->execute([$size_id]);
        $size_info = $stmt->fetch();
        if (!$size_info) {
            throw new Exception('Kích cỡ không hợp lệ');
        }
    }
    
    // Kiểm tra color nếu có
    $color_info = null;
    if ($color_id > 0) {
        $stmt = $pdo->prepare("SELECT * FROM mau_sac WHERE id = ? AND trang_thai = 'hoat_dong'");
        $stmt->execute([$color_id]);
        $color_info = $stmt->fetch();
        if (!$color_info) {
            throw new Exception('Màu sắc không hợp lệ');
        }
    }
    
    // Kiểm tra biến thể sản phẩm nếu có size và color
    $variant_info = null;
    if ($size_id > 0 && $color_id > 0) {
        $stmt = $pdo->prepare("
            SELECT bsp.*, kc.kich_co, ms.ten_mau, ms.ma_mau
            FROM bien_the_san_pham bsp
            LEFT JOIN kich_co kc ON bsp.kich_co_id = kc.id
            LEFT JOIN mau_sac ms ON bsp.mau_sac_id = ms.id
            WHERE bsp.san_pham_id = ? AND bsp.kich_co_id = ? AND bsp.mau_sac_id = ? 
            AND bsp.trang_thai = 'hoat_dong'
        ");
        $stmt->execute([$product_id, $size_id, $color_id]);
        $variant_info = $stmt->fetch();
        
        if (!$variant_info) {
            throw new Exception('Biến thể sản phẩm không tồn tại');
        }
        
        if ($variant_info['so_luong_ton_kho'] < $quantity) {
            throw new Exception('Số lượng trong kho không đủ. Còn lại: ' . $variant_info['so_luong_ton_kho']);
        }
    }
    
    // Khởi tạo cart trong session nếu chưa có
    if (!isset($_SESSION['cart'])) {
        $_SESSION['cart'] = [];
    }
    
    // Tạo cart item key unique
    $cart_key = $product_id . '_' . $size_id . '_' . $color_id;
    
    // Kiểm tra item đã có trong cart chưa
    $found = false;
    foreach ($_SESSION['cart'] as $key => $item) {
        if ($item['product_id'] == $product_id && 
            $item['size_id'] == $size_id && 
            $item['color_id'] == $color_id) {
            // Update quantity
            $_SESSION['cart'][$key]['quantity'] += $quantity;
            $found = true;
            break;
        }
    }
    
    // Nếu chưa có, thêm mới
    if (!$found) {
        // Tính giá cuối cùng
        $final_price = $product['gia_khuyen_mai'] ?: $product['gia_goc'];
        if ($variant_info && $variant_info['gia_ban']) {
            $final_price = $variant_info['gia_ban'];
        }
        
        $_SESSION['cart'][] = [
            'product_id' => $product_id,
            'product_name' => $product['ten_san_pham'],
            'product_slug' => $product['slug'],
            'product_image' => $product['hinh_anh_chinh'],
            'price' => $final_price,
            'original_price' => $product['gia_goc'],
            'sale_price' => $product['gia_khuyen_mai'],
            'quantity' => $quantity,
            'size_id' => $size_id,
            'size_name' => $size_info['kich_co'] ?? '',
            'color_id' => $color_id,
            'color_name' => $color_info['ten_mau'] ?? '',
            'color_code' => $color_info['ma_mau'] ?? '',
            'variant_id' => $variant_info['id'] ?? null,
            'stock' => $variant_info['so_luong_ton_kho'] ?? 999,
            'added_at' => date('Y-m-d H:i:s')
        ];
    }
    
    // Tính tổng items và amount trong cart
    $total_items = 0;
    $total_amount = 0;
    foreach ($_SESSION['cart'] as $item) {
        $total_items += $item['quantity'];
        $total_amount += $item['price'] * $item['quantity'];
    }
    
    // Lưu cart vào database nếu user đã đăng nhập
    if (isset($_SESSION['customer_id'])) {
        try {
            // Xóa cart cũ trong DB
            $stmt = $pdo->prepare("DELETE FROM gio_hang WHERE khach_hang_id = ?");
            $stmt->execute([$_SESSION['customer_id']]);
            
            // Lưu cart mới vào DB
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
            // Không quan trọng nếu lưu DB thất bại, vẫn có session
            error_log("Save cart to DB failed: " . $e->getMessage());
        }
    }
    
    // Response thành công
    echo json_encode([
        'success' => true,
        'message' => 'Đã thêm sản phẩm vào giỏ hàng',
        'data' => [
            'product_name' => $product['ten_san_pham'],
            'quantity' => $quantity,
            'size' => $size_info['kich_co'] ?? '',
            'color' => $color_info['ten_mau'] ?? '',
            'price' => $final_price,
            'cart_count' => $total_items,
            'cart_total' => $total_amount
        ]
    ]);
    
} catch (Exception $e) {
    // Response lỗi
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage(),
        'debug' => [
            'input' => $input ?? $_POST,
            'session_id' => session_id(),
            'customer_id' => $_SESSION['customer_id'] ?? null,
            'timestamp' => date('Y-m-d H:i:s')
        ]
    ]);
} catch (PDOException $e) {
    // Database error
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'Lỗi hệ thống, vui lòng thử lại',
        'debug' => [
            'db_error' => $e->getMessage(),
            'timestamp' => date('Y-m-d H:i:s')
        ]
    ]);
}
?>