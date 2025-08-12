<?php
/**
 * ADD TO CART FIXED - Thêm sản phẩm vào giỏ hàng
 * File: customer/add_to_cart_fixed.php
 * 🔧 FIXED: Đồng bộ hóa SESSION và DATABASE
 * 🔧 FIXED: Hỗ trợ cả 2 schema products + san_pham_chinh
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
    $variant_id = (int)($input['variant_id'] ?? 0); // Support trực tiếp variant
    
    if ($product_id <= 0) {
        throw new Exception('ID sản phẩm không hợp lệ');
    }
    
    if ($quantity <= 0) {
        throw new Exception('Số lượng không hợp lệ');
    }
    
    // 🔧 AUTO-DETECT PRODUCT SCHEMA
    $product = null;
    $product_schema = null;
    
    // Thử tìm trong bảng san_pham_chinh trước (Vietnamese)
    $stmt = $pdo->prepare("SELECT *, 'vietnamese' as schema_type FROM san_pham_chinh WHERE id = ? AND trang_thai = 'hoat_dong'");
    $stmt->execute([$product_id]);
    $product = $stmt->fetch();
    
    if ($product) {
        $product_schema = 'vietnamese';
    } else {
        // Thử tìm trong bảng products (English)
        $stmt = $pdo->prepare("SELECT *, 'english' as schema_type FROM products WHERE id = ? AND status = 'active'");
        $stmt->execute([$product_id]);
        $product = $stmt->fetch();
        
        if ($product) {
            $product_schema = 'english';
        }
    }
    
    if (!$product) {
        throw new Exception('Sản phẩm không tồn tại hoặc đã ngừng bán');
    }
    
    // 🔧 UNIFIED PRODUCT INFO
    $product_info = [
        'id' => $product['id'],
        'name' => $product_schema === 'vietnamese' ? $product['ten_san_pham'] : $product['name'],
        'slug' => $product['slug'],
        'image' => $product_schema === 'vietnamese' ? $product['hinh_anh_chinh'] : $product['main_image'],
        'price' => $product_schema === 'vietnamese' ? $product['gia_goc'] : $product['price'],
        'sale_price' => $product_schema === 'vietnamese' ? $product['gia_khuyen_mai'] : $product['sale_price'],
        'stock' => $product_schema === 'vietnamese' ? 999 : ($product['stock_quantity'] ?? 0),
        'schema' => $product_schema
    ];
    
    // 🔧 HANDLE VARIANTS
    $variant_info = null;
    $final_price = $product_info['sale_price'] ?: $product_info['price'];
    
    if ($product_schema === 'vietnamese') {
        // Vietnamese schema với variants
        if ($variant_id > 0) {
            // Sử dụng variant_id trực tiếp
            $stmt = $pdo->prepare("
                SELECT bsp.*, kc.kich_co, ms.ten_mau, ms.ma_mau
                FROM bien_the_san_pham bsp
                LEFT JOIN kich_co kc ON bsp.kich_co_id = kc.id
                LEFT JOIN mau_sac ms ON bsp.mau_sac_id = ms.id
                WHERE bsp.id = ? AND bsp.trang_thai = 'hoat_dong'
            ");
            $stmt->execute([$variant_id]);
            $variant_info = $stmt->fetch();
        } else if ($size_id > 0 && $color_id > 0) {
            // Tìm variant theo size + color
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
            
            if ($variant_info) {
                $variant_id = $variant_info['id'];
            }
        }
        
        if ($variant_info) {
            if ($variant_info['so_luong_ton_kho'] < $quantity) {
                throw new Exception('Số lượng trong kho không đủ. Còn lại: ' . $variant_info['so_luong_ton_kho']);
            }
            $final_price = $variant_info['gia_ban'];
            $product_info['stock'] = $variant_info['so_luong_ton_kho'];
        }
    } else {
        // English schema - đơn giản hơn
        if ($product_info['stock'] < $quantity) {
            throw new Exception('Số lượng trong kho không đủ. Còn lại: ' . $product_info['stock']);
        }
    }
    
    // 🔧 UNIFIED CART MANAGEMENT
    $customer_id = $_SESSION['customer_id'] ?? null;
    $session_id = $customer_id ? null : ($_SESSION['session_id'] ?? session_id());
    
    if (!$session_id && !$customer_id) {
        $_SESSION['session_id'] = session_id();
        $session_id = $_SESSION['session_id'];
    }
    
    // Kiểm tra item đã có trong database chưa
    $existing_item = null;
    if ($product_schema === 'vietnamese' && $variant_id > 0) {
        // Kiểm tra theo variant_id
        $stmt = $pdo->prepare("
            SELECT * FROM gio_hang 
            WHERE bien_the_id = ? AND (khach_hang_id = ? OR session_id = ?)
        ");
        $stmt->execute([$variant_id, $customer_id, $session_id]);
        $existing_item = $stmt->fetch();
    } else {
        // Kiểm tra theo san_pham_id (fallback)
        $stmt = $pdo->prepare("
            SELECT * FROM gio_hang 
            WHERE san_pham_id = ? AND (khach_hang_id = ? OR session_id = ?)
        ");
        $stmt->execute([$product_id, $customer_id, $session_id]);
        $existing_item = $stmt->fetch();
    }
    
    if ($existing_item) {
        // Cập nhật số lượng
        $new_quantity = $existing_item['so_luong'] + $quantity;
        
        // Kiểm tra stock lần nữa
        if ($variant_info && $new_quantity > $variant_info['so_luong_ton_kho']) {
            throw new Exception('Tổng số lượng vượt quá tồn kho: ' . $variant_info['so_luong_ton_kho']);
        }
        
        $stmt = $pdo->prepare("
            UPDATE gio_hang 
            SET so_luong = ?, gia_tai_thoi_diem = ?, ngay_cap_nhat = NOW() 
            WHERE id = ?
        ");
        $stmt->execute([$new_quantity, $final_price, $existing_item['id']]);
        
        $action_message = 'Đã cập nhật số lượng sản phẩm trong giỏ hàng';
        
    } else {
        // Thêm mới vào database
        $stmt = $pdo->prepare("
            INSERT INTO gio_hang (
                khach_hang_id, session_id, san_pham_id, bien_the_id,
                so_luong, gia_tai_thoi_diem, ngay_them
            ) VALUES (?, ?, ?, ?, ?, ?, NOW())
        ");
        $stmt->execute([
            $customer_id, 
            $session_id, 
            $product_id,
            $variant_id > 0 ? $variant_id : null,
            $quantity, 
            $final_price
        ]);
        
        $action_message = 'Đã thêm sản phẩm vào giỏ hàng';
    }
    
    // 🔧 SYNC SESSION CART (backward compatibility)
    if (!isset($_SESSION['cart'])) {
        $_SESSION['cart'] = [];
    }
    
    // Tạo cart item cho session
    $cart_key = $product_id . '_' . $size_id . '_' . $color_id;
    $session_item_found = false;
    
    foreach ($_SESSION['cart'] as $key => $item) {
        if ($item['product_id'] == $product_id && 
            $item['size_id'] == $size_id && 
            $item['color_id'] == $color_id) {
            $_SESSION['cart'][$key]['quantity'] += $quantity;
            $session_item_found = true;
            break;
        }
    }
    
    if (!$session_item_found) {
        $_SESSION['cart'][] = [
            'product_id' => $product_id,
            'product_name' => $product_info['name'],
            'product_slug' => $product_info['slug'],
            'product_image' => $product_info['image'],
            'price' => $final_price,
            'original_price' => $product_info['price'],
            'sale_price' => $product_info['sale_price'],
            'quantity' => $quantity,
            'size_id' => $size_id,
            'color_id' => $color_id,
            'variant_id' => $variant_id,
            'stock' => $product_info['stock'],
            'added_at' => date('Y-m-d H:i:s'),
            'schema' => $product_schema
        ];
    }
    
    // Tính tổng cart
    $cart_totals = calculateCartTotals($pdo, $customer_id, $session_id);
    
    // Response thành công
    echo json_encode([
        'success' => true,
        'message' => $action_message,
        'data' => [
            'product_name' => $product_info['name'],
            'quantity' => $quantity,
            'price' => $final_price,
            'cart_count' => $cart_totals['total_quantity'],
            'cart_total' => $cart_totals['subtotal'],
            'variant_info' => $variant_info ? [
                'size' => $variant_info['kich_co'] ?? '',
                'color' => $variant_info['ten_mau'] ?? '',
                'stock' => $variant_info['so_luong_ton_kho'] ?? 0
            ] : null
        ],
        'debug' => [
            'product_schema' => $product_schema,
            'variant_id' => $variant_id,
            'final_price' => $final_price,
            'session_id' => $session_id,
            'customer_id' => $customer_id,
            'existing_item' => $existing_item ? 'updated' : 'created'
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
            'timestamp' => date('Y-m-d H:i:s'),
            'line' => $e->getLine()
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

/**
 * Hàm tính tổng giỏ hàng - tương thích với cart.php
 */
function calculateCartTotals($pdo, $customer_id, $session_id) {
    try {
        $stmt = $pdo->prepare("
            SELECT 
                SUM(gh.so_luong * gh.gia_tai_thoi_diem) as subtotal,
                COUNT(*) as item_count,
                SUM(gh.so_luong) as total_quantity
            FROM gio_hang gh
            WHERE (gh.khach_hang_id = ? OR gh.session_id = ?)
        ");
        $stmt->execute([$customer_id, $session_id]);
        $totals = $stmt->fetch();
        
        $subtotal = $totals['subtotal'] ?? 0;
        $shipping_fee = $subtotal >= 500000 ? 0 : 30000;
        $tax = $subtotal * 0.1;
        $total = $subtotal + $shipping_fee + $tax;
        
        return [
            'subtotal' => $subtotal,
            'shipping_fee' => $shipping_fee,
            'tax' => $tax,
            'total' => $total,
            'item_count' => $totals['item_count'] ?? 0,
            'total_quantity' => $totals['total_quantity'] ?? 0
        ];
    } catch (Exception $e) {
        return [
            'subtotal' => 0,
            'shipping_fee' => 0,
            'tax' => 0,
            'total' => 0,
            'item_count' => 0,
            'total_quantity' => 0
        ];
    }
}
?>