<?php
// customer/cart_fixed.php
/**
 * Giỏ hàng FIXED - CRUD AJAX + tính tổng tự động + validate tồn kho + CHECKBOX SELECTION
 * 🔧 FIXED: Đồng bộ SESSION và DATABASE 
 * 🔧 FIXED: Hỗ trợ cả 2 schema products + san_pham_chinh
 * 🔧 FIXED: Hiển thị sản phẩm từ cả 2 bảng
 * 🔧 FIXED: Checkout redirect to correct checkout.php
 * 🔧 FIXED: Free shipping threshold logic (500,000 VND)
 * 🔧 FIXED: Anti-cache headers to prevent redirect issues
 */

// 🔧 FORCE NO CACHE - Prevent browser cache issues
header("Cache-Control: no-store, no-cache, must-revalidate, max-age=0");
header("Cache-Control: post-check=0, pre-check=0", false);
header("Pragma: no-cache");
header("Expires: Sat, 26 Jul 1997 05:00:00 GMT");

session_start();
require_once '../config/database.php';
require_once '../config/config.php';

// Xử lý AJAX requests
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['action'])) {
    header('Content-Type: application/json');
    
    $customer_id = $_SESSION['customer_id'] ?? null;
    $session_id = $customer_id ? null : ($_SESSION['session_id'] ?? session_id());
    
    if (!$session_id && !$customer_id) {
        $_SESSION['session_id'] = session_id();
        $session_id = $_SESSION['session_id'];
    }
    
    switch ($_POST['action']) {
        // CẬP NHẬT SỐ LƯỢNG (UPDATE)
        case 'update_quantity':
            $cart_id = (int)$_POST['cart_id'];
            $quantity = max(1, (int)$_POST['quantity']);
            
            try {
                // 🔧 UNIFIED QUERY để lấy cart item từ cả 2 schema
                $stmt = $pdo->prepare("
                    SELECT gh.*, 
                           COALESCE(bsp.so_luong_ton_kho, p.stock_quantity, 999) as stock_available,
                           COALESCE(bsp.gia_ban, gh.gia_tai_thoi_diem) as current_price,
                           COALESCE(sp.ten_san_pham, p.name) as product_name,
                           COALESCE(kc.kich_co, '') as size_name,
                           COALESCE(ms.ten_mau, '') as color_name
                    FROM gio_hang gh
                    LEFT JOIN bien_the_san_pham bsp ON gh.bien_the_id = bsp.id
                    LEFT JOIN san_pham_chinh sp ON gh.san_pham_id = sp.id
                    LEFT JOIN products p ON gh.san_pham_id = p.id AND sp.id IS NULL
                    LEFT JOIN kich_co kc ON bsp.kich_co_id = kc.id
                    LEFT JOIN mau_sac ms ON bsp.mau_sac_id = ms.id
                    WHERE gh.id = ? AND (gh.khach_hang_id = ? OR gh.session_id = ?)
                ");
                $stmt->execute([$cart_id, $customer_id, $session_id]);
                $item = $stmt->fetch();
                
                if (!$item) {
                    echo json_encode(['success' => false, 'message' => 'Sản phẩm không tồn tại trong giỏ hàng']);
                    exit;
                }
                
                if ($quantity > $item['stock_available']) {
                    echo json_encode([
                        'success' => false, 
                        'message' => "Chỉ còn {$item['stock_available']} sản phẩm trong kho",
                        'max_quantity' => $item['stock_available']
                    ]);
                    exit;
                }
                
                // Cập nhật số lượng
                $pdo->prepare("UPDATE gio_hang SET so_luong = ?, ngay_cap_nhat = NOW() WHERE id = ?")
                    ->execute([$quantity, $cart_id]);
                
                // Tính tổng mới
                $subtotal = $quantity * $item['current_price'];
                $cart_totals = calculateCartTotals($pdo, $customer_id, $session_id);
                
                echo json_encode([
                    'success' => true,
                    'message' => 'Cập nhật thành công',
                    'subtotal' => formatPrice($subtotal),
                    'subtotal_raw' => $subtotal,
                    'cart_totals' => $cart_totals
                ]);
                
            } catch (Exception $e) {
                echo json_encode(['success' => false, 'message' => 'Có lỗi xảy ra khi cập nhật: ' . $e->getMessage()]);
            }
            exit;
            
        // XÓA SẢN PHẨM (DELETE)
        case 'remove_item':
            $cart_id = (int)$_POST['cart_id'];
            
            try {
                $stmt = $pdo->prepare("
                    DELETE FROM gio_hang 
                    WHERE id = ? AND (khach_hang_id = ? OR session_id = ?)
                ");
                $result = $stmt->execute([$cart_id, $customer_id, $session_id]);
                
                if ($result) {
                    $cart_totals = calculateCartTotals($pdo, $customer_id, $session_id);
                    
                    echo json_encode([
                        'success' => true,
                        'message' => 'Đã xóa sản phẩm khỏi giỏ hàng',
                        'cart_totals' => $cart_totals
                    ]);
                } else {
                    echo json_encode(['success' => false, 'message' => 'Không thể xóa sản phẩm']);
                }
                
            } catch (Exception $e) {
                echo json_encode(['success' => false, 'message' => 'Có lỗi xảy ra khi xóa']);
            }
            exit;

        // XÓA NHIỀU SẢN PHẨM ĐÃ CHỌN
        case 'remove_selected':
            $cart_ids = $_POST['cart_ids'] ?? [];
            
            if (empty($cart_ids) || !is_array($cart_ids)) {
                echo json_encode(['success' => false, 'message' => 'Không có sản phẩm nào được chọn']);
                exit;
            }
            
            try {
                $placeholders = str_repeat('?,', count($cart_ids) - 1) . '?';
                $params = array_merge($cart_ids, [$customer_id, $session_id]);
                
                $stmt = $pdo->prepare("
                    DELETE FROM gio_hang 
                    WHERE id IN ($placeholders) AND (khach_hang_id = ? OR session_id = ?)
                ");
                $result = $stmt->execute($params);
                
                if ($result) {
                    $cart_totals = calculateCartTotals($pdo, $customer_id, $session_id);
                    
                    echo json_encode([
                        'success' => true,
                        'message' => 'Đã xóa ' . count($cart_ids) . ' sản phẩm khỏi giỏ hàng',
                        'cart_totals' => $cart_totals
                    ]);
                } else {
                    echo json_encode(['success' => false, 'message' => 'Không thể xóa sản phẩm']);
                }
                
            } catch (Exception $e) {
                echo json_encode(['success' => false, 'message' => 'Có lỗi xảy ra khi xóa']);
            }
            exit;
            
        // XÓA TẤT CẢ (CLEAR CART)
        case 'clear_cart':
            try {
                $stmt = $pdo->prepare("
                    DELETE FROM gio_hang 
                    WHERE khach_hang_id = ? OR session_id = ?
                ");
                $stmt->execute([$customer_id, $session_id]);
                
                // Clear session cart too
                $_SESSION['cart'] = [];
                
                echo json_encode([
                    'success' => true,
                    'message' => 'Đã xóa tất cả sản phẩm trong giỏ hàng'
                ]);
                
            } catch (Exception $e) {
                echo json_encode(['success' => false, 'message' => 'Có lỗi xảy ra']);
            }
            exit;

        // TÍNH TỔNG CHO SẢN PHẨM ĐÃ CHỌN
        case 'calculate_selected':
            $cart_ids = $_POST['cart_ids'] ?? [];
            
            try {
                if (empty($cart_ids)) {
                    echo json_encode([
                        'success' => true,
                        'totals' => [
                            'selected_count' => 0,
                            'selected_quantity' => 0,
                            'selected_subtotal' => 0,
                            'selected_subtotal_formatted' => formatPrice(0),
                            'shipping_fee' => 0,
                            'shipping_fee_formatted' => formatPrice(0),
                            'tax' => 0,
                            'tax_formatted' => formatPrice(0),
                            'total' => 0,
                            'total_formatted' => formatPrice(0)
                        ]
                    ]);
                    exit;
                }
                
                $placeholders = str_repeat('?,', count($cart_ids) - 1) . '?';
                $params = array_merge($cart_ids, [$customer_id, $session_id]);
                
                $stmt = $pdo->prepare("
                    SELECT 
                        COUNT(*) as selected_count,
                        SUM(gh.so_luong) as selected_quantity,
                        SUM(gh.so_luong * gh.gia_tai_thoi_diem) as selected_subtotal
                    FROM gio_hang gh
                    WHERE gh.id IN ($placeholders) AND (gh.khach_hang_id = ? OR gh.session_id = ?)
                ");
                $stmt->execute($params);
                $totals = $stmt->fetch();
                
                $subtotal = $totals['selected_subtotal'] ?? 0;
                $shipping_fee = $subtotal >= 500000 ? 0 : 30000; // 🔧 FIXED: Free shipping from 500k
                $tax = $subtotal * 0.1;
                $total = $subtotal + $shipping_fee + $tax;
                
                echo json_encode([
                    'success' => true,
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
                ]);
                
            } catch (Exception $e) {
                echo json_encode(['success' => false, 'message' => 'Có lỗi xảy ra khi tính tổng']);
            }
            exit;

        // SET CHECKOUT ITEMS - Lưu sản phẩm đã chọn để checkout
        case 'set_checkout_items':
            $cart_ids = $_POST['cart_ids'] ?? [];
            
            if (empty($cart_ids) || !is_array($cart_ids)) {
                echo json_encode(['success' => false, 'message' => 'Không có sản phẩm nào được chọn']);
                exit;
            }
            
            // Lưu danh sách ID đã chọn vào session để dùng ở checkout
            $_SESSION['checkout_items'] = $cart_ids;
            
            echo json_encode([
                'success' => true,
                'message' => 'Đã chuẩn bị dữ liệu thanh toán',
                'selected_count' => count($cart_ids)
            ]);
            exit;
            
        // THÊM SẢN PHẨM (CREATE) - từ product detail hoặc API
        case 'add_to_cart':
            $variant_id = (int)($_POST['variant_id'] ?? 0);
            $product_id = (int)($_POST['product_id'] ?? 0);
            $quantity = max(1, (int)($_POST['quantity'] ?? 1));
            
            try {
                if ($variant_id > 0) {
                    // Vietnamese schema với variant
                    $stmt = $pdo->prepare("
                        SELECT bsp.*, sp.ten_san_pham, kc.kich_co, ms.ten_mau
                        FROM bien_the_san_pham bsp
                        JOIN san_pham_chinh sp ON bsp.san_pham_id = sp.id
                        JOIN kich_co kc ON bsp.kich_co_id = kc.id
                        JOIN mau_sac ms ON bsp.mau_sac_id = ms.id
                        WHERE bsp.id = ? AND bsp.trang_thai = 'hoat_dong'
                    ");
                    $stmt->execute([$variant_id]);
                    $variant = $stmt->fetch();
                    
                    if (!$variant) {
                        echo json_encode(['success' => false, 'message' => 'Biến thể sản phẩm không tồn tại']);
                        exit;
                    }
                    
                    if ($variant['so_luong_ton_kho'] < $quantity) {
                        echo json_encode([
                            'success' => false, 
                            'message' => "Chỉ còn {$variant['so_luong_ton_kho']} sản phẩm trong kho"
                        ]);
                        exit;
                    }
                    
                    // Kiểm tra đã có trong giỏ hàng chưa
                    $check = $pdo->prepare("
                        SELECT * FROM gio_hang 
                        WHERE bien_the_id = ? 
                        AND (khach_hang_id = ? OR session_id = ?)
                    ");
                    $check->execute([$variant_id, $customer_id, $session_id]);
                    $existing = $check->fetch();
                    
                    if ($existing) {
                        // Cập nhật số lượng
                        $new_quantity = $existing['so_luong'] + $quantity;
                        if ($new_quantity > $variant['so_luong_ton_kho']) {
                            echo json_encode([
                                'success' => false,
                                'message' => "Tổng số lượng vượt quá tồn kho ({$variant['so_luong_ton_kho']})"
                            ]);
                            exit;
                        }
                        
                        $pdo->prepare("UPDATE gio_hang SET so_luong = ?, ngay_cap_nhat = NOW() WHERE id = ?")
                            ->execute([$new_quantity, $existing['id']]);
                    } else {
                        // Thêm mới
                        $pdo->prepare("
                            INSERT INTO gio_hang (khach_hang_id, session_id, san_pham_id, bien_the_id, so_luong, gia_tai_thoi_diem, ngay_them)
                            VALUES (?, ?, ?, ?, ?, ?, NOW())
                        ")->execute([$customer_id, $session_id, $variant['san_pham_id'], $variant_id, $quantity, $variant['gia_ban']]);
                    }
                    
                } else if ($product_id > 0) {
                    // English schema hoặc sản phẩm đơn giản
                    $stmt = $pdo->prepare("
                        SELECT *, 'products' as table_type FROM products WHERE id = ? AND status = 'active'
                        UNION ALL
                        SELECT *, 'san_pham_chinh' as table_type FROM san_pham_chinh WHERE id = ? AND trang_thai = 'hoat_dong'
                        LIMIT 1
                    ");
                    $stmt->execute([$product_id, $product_id]);
                    $product = $stmt->fetch();
                    
                    if (!$product) {
                        echo json_encode(['success' => false, 'message' => 'Sản phẩm không tồn tại']);
                        exit;
                    }
                    
                    $price = $product['table_type'] === 'products' ? 
                        ($product['sale_price'] ?: $product['price']) : 
                        ($product['gia_khuyen_mai'] ?: $product['gia_goc']);
                    
                    $stock = $product['table_type'] === 'products' ? $product['stock_quantity'] : 999;
                    
                    if ($stock < $quantity && $stock != 999) {
                        echo json_encode([
                            'success' => false, 
                            'message' => "Chỉ còn {$stock} sản phẩm trong kho"
                        ]);
                        exit;
                    }
                    
                    // Kiểm tra đã có trong giỏ hàng chưa
                    $check = $pdo->prepare("
                        SELECT * FROM gio_hang 
                        WHERE san_pham_id = ? AND bien_the_id IS NULL
                        AND (khach_hang_id = ? OR session_id = ?)
                    ");
                    $check->execute([$product_id, $customer_id, $session_id]);
                    $existing = $check->fetch();
                    
                    if ($existing) {
                        // Cập nhật số lượng
                        $new_quantity = $existing['so_luong'] + $quantity;
                        if ($stock != 999 && $new_quantity > $stock) {
                            echo json_encode([
                                'success' => false,
                                'message' => "Tổng số lượng vượt quá tồn kho ({$stock})"
                            ]);
                            exit;
                        }
                        
                        $pdo->prepare("UPDATE gio_hang SET so_luong = ?, ngay_cap_nhat = NOW() WHERE id = ?")
                            ->execute([$new_quantity, $existing['id']]);
                    } else {
                        // Thêm mới
                        $pdo->prepare("
                            INSERT INTO gio_hang (khach_hang_id, session_id, san_pham_id, bien_the_id, so_luong, gia_tai_thoi_diem, ngay_them)
                            VALUES (?, ?, ?, NULL, ?, ?, NOW())
                        ")->execute([$customer_id, $session_id, $product_id, $quantity, $price]);
                    }
                } else {
                    throw new Exception('Thiếu thông tin sản phẩm');
                }
                
                $cart_totals = calculateCartTotals($pdo, $customer_id, $session_id);
                
                echo json_encode([
                    'success' => true,
                    'message' => 'Đã thêm vào giỏ hàng',
                    'cart_totals' => $cart_totals
                ]);
                
            } catch (Exception $e) {
                echo json_encode(['success' => false, 'message' => 'Có lỗi xảy ra khi thêm sản phẩm: ' . $e->getMessage()]);
            }
            exit;
    }
}

// Hàm tính tổng giỏ hàng
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
        $shipping_fee = $subtotal >= 500000 ? 0 : 30000; // 🔧 FIXED: Miễn phí ship từ 500k
        $tax = $subtotal * 0.1; // Thuế 10%
        $total = $subtotal + $shipping_fee + $tax;
        
        return [
            'subtotal' => $subtotal,
            'subtotal_formatted' => formatPrice($subtotal),
            'shipping_fee' => $shipping_fee,
            'shipping_fee_formatted' => formatPrice($shipping_fee),
            'tax' => $tax,
            'tax_formatted' => formatPrice($tax),
            'total' => $total,
            'total_formatted' => formatPrice($total),
            'item_count' => $totals['item_count'] ?? 0,
            'total_quantity' => $totals['total_quantity'] ?? 0,
            'free_shipping_threshold' => 500000,
            'free_shipping_remaining' => max(0, 500000 - $subtotal)
        ];
    } catch (Exception $e) {
        return [
            'subtotal' => 0, 'subtotal_formatted' => '0đ',
            'shipping_fee' => 0, 'shipping_fee_formatted' => '0đ',
            'tax' => 0, 'tax_formatted' => '0đ',
            'total' => 0, 'total_formatted' => '0đ',
            'item_count' => 0, 'total_quantity' => 0,
            'free_shipping_threshold' => 500000, 'free_shipping_remaining' => 500000
        ];
    }
}

// 🔧 UNIFIED CART ITEMS - Lấy từ cả 2 schema
function getUnifiedCartItems($pdo, $customer_id, $session_id) {
    try {
        $stmt = $pdo->prepare("
            SELECT gh.*,
                   -- Vietnamese schema fields
                   sp.ten_san_pham, sp.slug as vn_slug, sp.hinh_anh_chinh as vn_image,
                   bsp.gia_ban, bsp.so_luong_ton_kho, bsp.ma_sku,
                   kc.kich_co, ms.ten_mau, ms.ma_mau,
                   
                   -- English schema fields  
                   p.name as en_name, p.slug as en_slug, p.main_image as en_image,
                   p.price as en_price, p.sale_price as en_sale_price, p.stock_quantity as en_stock,
                   
                   -- Determine schema type
                   CASE 
                       WHEN sp.id IS NOT NULL THEN 'vietnamese'
                       WHEN p.id IS NOT NULL THEN 'english'
                       ELSE 'unknown'
                   END as schema_type
                   
            FROM gio_hang gh
            -- Join Vietnamese schema
            LEFT JOIN bien_the_san_pham bsp ON gh.bien_the_id = bsp.id
            LEFT JOIN san_pham_chinh sp ON gh.san_pham_id = sp.id OR bsp.san_pham_id = sp.id
            LEFT JOIN kich_co kc ON bsp.kich_co_id = kc.id
            LEFT JOIN mau_sac ms ON bsp.mau_sac_id = ms.id
            
            -- Join English schema (only if Vietnamese not found)
            LEFT JOIN products p ON gh.san_pham_id = p.id AND sp.id IS NULL
            
            WHERE (gh.khach_hang_id = ? OR gh.session_id = ?)
            ORDER BY gh.ngay_them DESC
        ");
        $stmt->execute([$customer_id, $session_id]);
        $raw_items = $stmt->fetchAll();
        
        // Normalize data structure
        $normalized_items = [];
        foreach ($raw_items as $item) {
            $normalized = [
                'id' => $item['id'],
                'product_id' => $item['san_pham_id'],
                'variant_id' => $item['bien_the_id'],
                'quantity' => $item['so_luong'],
                'price' => $item['gia_tai_thoi_diem'],
                'schema_type' => $item['schema_type'],
                
                // Unified fields
                'name' => $item['schema_type'] === 'vietnamese' ? $item['ten_san_pham'] : $item['en_name'],
                'slug' => $item['schema_type'] === 'vietnamese' ? $item['vn_slug'] : $item['en_slug'],
                'image' => $item['schema_type'] === 'vietnamese' ? $item['vn_image'] : $item['en_image'],
                'size' => $item['kich_co'] ?? '',
                'color' => $item['ten_mau'] ?? '',
                'color_code' => $item['ma_mau'] ?? '',
                'sku' => $item['ma_sku'] ?? '',
                'stock' => $item['schema_type'] === 'vietnamese' ? 
                    ($item['so_luong_ton_kho'] ?? 999) : 
                    ($item['en_stock'] ?? 0),
                'created_at' => $item['ngay_them']
            ];
            
            $normalized_items[] = $normalized;
        }
        
        return $normalized_items;
    } catch (Exception $e) {
        error_log("getUnifiedCartItems error: " . $e->getMessage());
        return [];
    }
}

// Lấy danh sách sản phẩm trong giỏ hàng
$customer_id = $_SESSION['customer_id'] ?? null;
$session_id = $customer_id ? null : ($_SESSION['session_id'] ?? null);

$cart_items = [];
$cart_totals = ['subtotal' => 0, 'item_count' => 0];

if ($customer_id || $session_id) {
    $cart_items = getUnifiedCartItems($pdo, $customer_id, $session_id);
    $cart_totals = calculateCartTotals($pdo, $customer_id, $session_id);
}

// Sản phẩm gợi ý (nếu giỏ hàng trống hoặc có sản phẩm)
$suggested_products = [];
if (empty($cart_items)) {
    // Gợi ý sản phẩm phổ biến nếu giỏ hàng trống
    try {
        $stmt = $pdo->prepare("
            SELECT sp.*, MIN(bsp.gia_ban) as gia_thap_nhat, 'vietnamese' as source_table
            FROM san_pham_chinh sp
            JOIN bien_the_san_pham bsp ON sp.id = bsp.san_pham_id
            WHERE sp.trang_thai = 'hoat_dong' AND bsp.trang_thai = 'hoat_dong'
            AND bsp.so_luong_ton_kho > 0
            GROUP BY sp.id
            ORDER BY sp.luot_xem DESC, sp.so_luong_ban DESC
            LIMIT 4
        ");
        $stmt->execute();
        $suggested_products = $stmt->fetchAll();
    } catch (Exception $e) {
        $suggested_products = [];
    }
}

$page_title = 'Giỏ hàng (' . $cart_totals['item_count'] . ') - ' . SITE_NAME;
?>

<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= $page_title ?></title>
    
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    
    <style>
        .cart-item {
            border: 1px solid #eee;
            border-radius: 8px;
            padding: 20px;
            margin-bottom: 15px;
            background: white;
            transition: all 0.3s ease;
        }
        
        .cart-item.selected {
            border-color: #0d6efd;
            background: #f8f9ff;
            box-shadow: 0 2px 8px rgba(13, 110, 253, 0.15);
        }
        
        .cart-item.removing {
            opacity: 0.5;
            transform: translateX(-20px);
        }
        
        .cart-checkbox {
            transform: scale(1.3);
            margin-right: 10px;
        }
        
        .product-image {
            width: 100px;
            height: 100px;
            object-fit: cover;
            border-radius: 8px;
        }
        
        .quantity-controls {
            display: flex;
            align-items: center;
            max-width: 120px;
        }
        
        .quantity-controls input {
            text-align: center;
            border-left: none;
            border-right: none;
        }
        
        .quantity-controls button {
            width: 35px;
            height: 35px;
            padding: 0;
        }
        
        .cart-summary {
            background: #f8f9fa;
            border-radius: 10px;
            padding: 25px;
            position: sticky;
            top: 20px;
        }
        
        .progress-bar {
            height: 8px;
            border-radius: 4px;
        }
        
        .cart-actions {
            background: #fff;
            border: 1px solid #dee2e6;
            border-radius: 8px;
            padding: 15px;
            margin-bottom: 20px;
        }
        
        .btn-delete-selected {
            background: #dc3545;
            border: none;
            color: white;
        }
        
        .btn-delete-selected:disabled {
            background: #6c757d;
            opacity: 0.6;
        }
        
        .loading-overlay {
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: rgba(0,0,0,0.5);
            display: none;
            z-index: 9999;
            justify-content: center;
            align-items: center;
        }
        
        .loading-spinner {
            color: white;
            font-size: 2rem;
        }
        
        .schema-badge {
            position: absolute;
            top: 5px;
            left: 5px;
            font-size: 10px;
            z-index: 1;
        }
        
        .debug-info {
            background: #e8f4fd;
            border: 1px solid #bee5eb;
            border-radius: 5px;
            padding: 10px;
            margin: 10px 0;
            font-size: 12px;
        }
        
        .free-shipping-alert {
            background: linear-gradient(135deg, #28a745, #20c997);
            color: white;
            border-radius: 8px;
            padding: 10px 15px;
            margin-bottom: 15px;
            border: none;
        }
        
        .shipping-progress {
            background: #fff3cd;
            border: 1px solid #ffeaa7;
            border-radius: 8px;
            padding: 15px;
            margin-bottom: 15px;
        }
        
        @media (max-width: 768px) {
            .product-image {
                width: 80px;
                height: 80px;
            }
            
            .cart-summary {
                position: static;
                margin-top: 30px;
            }
            
            .cart-actions {
                padding: 10px;
            }
        }
    </style>
</head>
<body>
    <!-- Header -->
    <?php include 'includes/header.php'; ?>
    
    <!-- Loading Overlay -->
    <div class="loading-overlay" id="loadingOverlay">
        <div class="loading-spinner">
            <i class="fas fa-spinner fa-spin"></i>
        </div>
    </div>
    
    <div class="container py-4">
        <!-- 🔧 Debug Info -->
        <div class="debug-info">
            <strong>🔧 CART FIXED VERSION - Unified Cart Display</strong><br>
            Cart items: <?= count($cart_items) ?><br>
            Customer ID: <?= $customer_id ?? 'guest' ?><br>
            Session ID: <?= $session_id ?? 'none' ?><br>
            Data sources: Both 'gio_hang' table + unified schema detection<br>
            Free shipping threshold: 500,000 VND
        </div>
        
        <!-- Breadcrumb -->
        <nav aria-label="breadcrumb" class="mb-4">
            <ol class="breadcrumb">
                <li class="breadcrumb-item"><a href="/">Trang chủ</a></li>
                <li class="breadcrumb-item active">Giỏ hàng</li>
            </ol>
        </nav>
        
        <div class="row">
            <div class="col-lg-8">
                <div class="d-flex justify-content-between align-items-center mb-4">
                    <h2>Giỏ hàng của bạn</h2>
                    <?php if (!empty($cart_items)): ?>
                        <button class="btn btn-outline-danger btn-sm" onclick="clearCart()">
                            <i class="fas fa-trash me-1"></i>
                            Xóa tất cả
                        </button>
                    <?php endif; ?>
                </div>
                
                <?php if (empty($cart_items)): ?>
                    <!-- Empty Cart -->
                    <div class="text-center py-5">
                        <i class="fas fa-shopping-cart fa-4x text-muted mb-4"></i>
                        <h4>Giỏ hàng của bạn đang trống</h4>
                        <p class="text-muted mb-4">Khám phá các sản phẩm tuyệt vời và thêm vào giỏ hàng ngay!</p>
                        <a href="products_fixed.php" class="btn btn-primary">
                            <i class="fas fa-shopping-bag me-2"></i>
                            Tiếp tục mua sắm
                        </a>
                    </div>
                <?php else: ?>
                    
                    <!-- Cart Actions -->
                    <div class="cart-actions">
                        <div class="row align-items-center">
                            <div class="col-md-6">
                                <div class="form-check">
                                    <input class="form-check-input cart-checkbox" 
                                           type="checkbox" 
                                           id="selectAll">
                                    <label class="form-check-label fw-bold" for="selectAll">
                                        Chọn tất cả (<span id="totalItems"><?= count($cart_items) ?></span> sản phẩm)
                                    </label>
                                </div>
                            </div>
                            <div class="col-md-6 text-md-end mt-2 mt-md-0">
                                <button type="button" 
                                        class="btn btn-delete-selected btn-sm" 
                                        id="deleteSelected"
                                        onclick="removeSelectedItems()" 
                                        disabled>
                                    <i class="fas fa-trash me-1"></i>
                                    Xóa đã chọn (<span id="selectedCount">0</span>)
                                </button>
                            </div>
                        </div>
                    </div>

                    <!-- Cart Items -->
                    <div id="cartItems">
                        <?php foreach ($cart_items as $item): ?>
                            <div class="cart-item" data-cart-id="<?= $item['id'] ?>">
                                <div class="row align-items-center">
                                    <!-- Checkbox -->
                                    <div class="col-auto">
                                        <input class="form-check-input cart-checkbox item-checkbox" 
                                               type="checkbox" 
                                               data-cart-id="<?= $item['id'] ?>"
                                               onchange="updateSelection()">
                                    </div>
                                    
                                    <!-- Product Image -->
                                    <div class="col-md-2 col-3">
                                        <div class="position-relative">
                                            <span class="badge bg-info schema-badge">
                                                <?= $item['schema_type'] === 'vietnamese' ? 'VN' : 'EN' ?>
                                            </span>
                                            <img src="/tktshop/uploads/products/<?= $item['image'] ?: 'default-product.jpg' ?>" 
                                                 alt="<?= htmlspecialchars($item['name']) ?>"
                                                 class="product-image"
                                                 onerror="this.src='/tktshop/uploads/products/no-image.jpg'">
                                        </div>
                                    </div>
                                    
                                    <!-- Product Info -->
                                    <div class="col-md-3 col-9">
                                        <h6 class="mb-1">
                                            <a href="product_detail.php?<?= $item['slug'] ? 'slug=' . $item['slug'] : 'id=' . $item['product_id'] ?>" 
                                               class="text-decoration-none text-dark">
                                                <?= htmlspecialchars($item['name']) ?>
                                            </a>
                                        </h6>
                                        <?php if ($item['size'] || $item['color']): ?>
                                        <small class="text-muted">
                                            <?php if ($item['size']): ?>Size: <?= htmlspecialchars($item['size']) ?><?php endif; ?>
                                            <?php if ($item['size'] && $item['color']): ?> | <?php endif; ?>
                                            <?php if ($item['color']): ?>
                                                Màu: <span style="color: <?= $item['color_code'] ?: '#000' ?>"><?= htmlspecialchars($item['color']) ?></span>
                                            <?php endif; ?>
                                        </small>
                                        <br>
                                        <?php endif; ?>
                                        <?php if ($item['sku']): ?>
                                        <small class="text-muted">SKU: <?= htmlspecialchars($item['sku']) ?></small>
                                        <?php endif; ?>
                                        
                                        <?php if ($item['stock'] <= 5 && $item['stock'] != 999): ?>
                                            <div class="mt-1">
                                                <small class="text-warning">
                                                    <i class="fas fa-exclamation-triangle"></i> 
                                                    Chỉ còn <?= $item['stock'] ?> sản phẩm
                                                </small>
                                            </div>
                                        <?php endif; ?>
                                    </div>
                                    
                                    <!-- Price -->
                                    <div class="col-md-2 col-4 text-center">
                                        <div class="fw-bold text-primary"><?= formatPrice($item['price']) ?></div>
                                    </div>
                                    
                                    <!-- Quantity Controls -->
                                    <div class="col-md-2 col-4">
                                        <div class="quantity-controls input-group">
                                            <button class="btn btn-outline-secondary quantity-btn" 
                                                    type="button" 
                                                    onclick="updateQuantity(<?= $item['id'] ?>, <?= $item['quantity'] - 1 ?>)"
                                                    <?= $item['quantity'] <= 1 ? 'disabled' : '' ?>>
                                                -
                                            </button>
                                            <input type="number" 
                                                   class="form-control quantity-input" 
                                                   value="<?= $item['quantity'] ?>"
                                                   min="1" 
                                                   max="<?= $item['stock'] != 999 ? $item['stock'] : 9999 ?>"
                                                   onchange="updateQuantity(<?= $item['id'] ?>, this.value)">
                                            <button class="btn btn-outline-secondary quantity-btn" 
                                                    type="button"
                                                    onclick="updateQuantity(<?= $item['id'] ?>, <?= $item['quantity'] + 1 ?>)"
                                                    <?= ($item['stock'] != 999 && $item['quantity'] >= $item['stock']) ? 'disabled' : '' ?>>
                                                +
                                            </button>
                                        </div>
                                    </div>
                                    
                                    <!-- Subtotal -->
                                    <div class="col-md-2 col-4 text-center">
                                        <div class="fw-bold subtotal-price" data-price="<?= $item['price'] ?>" data-quantity="<?= $item['quantity'] ?>">
                                            <?= formatPrice($item['quantity'] * $item['price']) ?>
                                        </div>
                                        <button class="btn btn-sm btn-outline-danger mt-2" 
                                                onclick="removeItem(<?= $item['id'] ?>)">
                                            <i class="fas fa-trash"></i>
                                        </button>
                                    </div>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                    
                    <!-- Continue Shopping -->
                    <div class="mt-4">
                        <a href="products_fixed.php" class="btn btn-outline-primary">
                            <i class="fas fa-arrow-left me-2"></i>
                            Tiếp tục mua sắm
                        </a>
                    </div>
                <?php endif; ?>
            </div>
            
            <?php if (!empty($cart_items)): ?>
                <!-- Cart Summary -->
                <div class="col-lg-4">
                    <div class="cart-summary">
                        <h5 class="mb-3">Tóm tắt đơn hàng</h5>
                        
                        <!-- Selected Items Info -->
                        <div class="alert alert-info py-2 mb-3" id="selectedInfo">
                            <i class="fas fa-info-circle me-1"></i>
                            <small>Chọn sản phẩm để xem tổng tiền</small>
                        </div>
                        
                        <!-- Free Shipping Progress -->
                        <div class="shipping-progress" id="shippingProgress" style="display: none;">
                            <div class="d-flex justify-content-between mb-2">
                                <small><strong>Mua thêm để được miễn phí ship:</strong></small>
                                <small class="fw-bold text-primary" id="remainingForFreeShip">0đ</small>
                            </div>
                            <div class="progress mb-2">
                                <div class="progress-bar bg-primary" id="shippingProgressBar" style="width: 0%"></div>
                            </div>
                            <small class="text-muted" id="shippingProgressText">0đ / 500.000đ</small>
                        </div>
                        
                        <div class="free-shipping-alert" id="freeShippingAlert" style="display: none;">
                            <i class="fas fa-check-circle me-2"></i>
                            <strong>Bạn được miễn phí vận chuyển!</strong>
                        </div>
                        
                        <!-- Order Summary -->
                        <div class="border-top pt-3" id="orderSummary">
                            <div class="d-flex justify-content-between mb-2">
                                <span>Tạm tính (<span id="selectedItemCount">0</span> sản phẩm):</span>
                                <span id="subtotalAmount">0đ</span>
                            </div>
                            
                            <div class="d-flex justify-content-between mb-2">
                                <span>Phí vận chuyển:</span>
                                <span id="shippingAmount">0đ</span>
                            </div>
                            
                            <div class="d-flex justify-content-between mb-2">
                                <span>Thuế (10%):</span>
                                <span id="taxAmount">0đ</span>
                            </div>
                            
                            <hr>
                            
                            <div class="d-flex justify-content-between mb-3">
                                <strong>Tổng cộng:</strong>
                                <strong class="text-primary fs-5" id="totalAmount">0đ</strong>
                            </div>
                            
                            <div class="d-grid gap-2">
                                <button class="btn btn-primary btn-lg" 
                                        id="checkoutBtn" 
                                        onclick="debugProceedToCheckout()" 
                                        disabled>
                                    <i class="fas fa-credit-card me-2"></i>
                                    Thanh toán
                                </button>
                                <button class="btn btn-outline-secondary" onclick="saveForLater()">
                                    <i class="fas fa-bookmark me-2"></i>
                                    Lưu để mua sau
                                </button>
                            </div>
                        </div>
                    </div>
                </div>
            <?php endif; ?>
        </div>
        
        <!-- Suggested Products -->
        <?php if (!empty($suggested_products)): ?>
            <div class="mt-5">
                <div class="bg-white border rounded-3 p-4">
                    <h4 class="mb-4">
                        <?= empty($cart_items) ? 'Sản phẩm nổi bật' : 'Có thể bạn quan tâm' ?>
                    </h4>
                    <div class="row">
                        <?php foreach ($suggested_products as $product): ?>
                            <div class="col-lg-3 col-md-4 col-sm-6 mb-3">
                                <div class="card h-100" style="cursor: pointer;" onclick="location.href='product_detail.php?slug=<?= $product['slug'] ?>'">
                                    <img src="/tktshop/uploads/products/<?= $product['hinh_anh_chinh'] ?: 'default-product.jpg' ?>" 
                                         class="card-img-top" 
                                         alt="<?= htmlspecialchars($product['ten_san_pham']) ?>"
                                         style="height: 180px; object-fit: cover;"
                                         onerror="this.src='/tktshop/uploads/products/no-image.jpg'">
                                    <div class="card-body d-flex flex-column">
                                        <h6 class="card-title"><?= htmlspecialchars($product['ten_san_pham']) ?></h6>
                                        <div class="text-warning mb-2">
                                            <?php for ($i = 1; $i <= 5; $i++): ?>
                                                <i class="fas fa-star<?= $i <= floor($product['diem_danh_gia_tb'] ?? 0) ? '' : ' text-muted' ?> small"></i>
                                            <?php endfor; ?>
                                            <small class="text-muted ms-1">(<?= $product['so_luong_danh_gia'] ?? 0 ?>)</small>
                                        </div>
                                        <div class="mt-auto">
                                            <div class="fw-bold text-primary"><?= formatPrice($product['gia_thap_nhat']) ?></div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </div>
            </div>
        <?php endif; ?>
    </div>
    
    <!-- Scroll to Top Button -->
    <button id="scrollToTop" title="Lên đầu trang" style="position: fixed; bottom: 20px; right: 20px; width: 50px; height: 50px; background: #0d6efd; color: white; border: none; border-radius: 50%; display: none; cursor: pointer; z-index: 1000; transition: all 0.3s ease;">
        <i class="fas fa-chevron-up"></i>
    </button>
    
    <!-- Footer -->
    <?php include 'includes/footer.php'; ?>
    
    <!-- Scripts -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
    
    <script>
        // 🔧 FORCE FRESH LOAD - Add timestamp: <?= time() ?>
        // AJAX Functions for Cart CRUD with Checkbox Selection - FIXED VERSION
        
        let selectedItems = new Set();
        
        // Show loading
        function showLoading() {
            document.getElementById('loadingOverlay').style.display = 'flex';
        }
        
        // Hide loading
        function hideLoading() {
            document.getElementById('loadingOverlay').style.display = 'none';
        }
        
        // Show toast notification
        function showToast(message, type = 'success') {
            let toastContainer = document.getElementById('toastContainer');
            if (!toastContainer) {
                toastContainer = document.createElement('div');
                toastContainer.id = 'toastContainer';
                toastContainer.className = 'toast-container position-fixed top-0 end-0 p-3';
                toastContainer.style.zIndex = '10000';
                document.body.appendChild(toastContainer);
            }
            
            const toastId = 'toast-' + Date.now();
            const iconClass = type === 'success' ? 'check-circle' : 'exclamation-circle';
            const bgClass = type === 'success' ? 'success' : 'danger';
            
            const toastHtml = `
                <div id="${toastId}" class="toast align-items-center text-white bg-${bgClass} border-0 animate-fadeIn" role="alert">
                    <div class="d-flex">
                        <div class="toast-body">
                            <i class="fas fa-${iconClass} me-2"></i>
                            ${message}
                        </div>
                        <button type="button" class="btn-close btn-close-white me-2 m-auto" data-bs-dismiss="toast"></button>
                    </div>
                </div>
            `;
            
            toastContainer.insertAdjacentHTML('beforeend', toastHtml);
            const toast = new bootstrap.Toast(document.getElementById(toastId));
            toast.show();
            
            setTimeout(() => {
                const toastElement = document.getElementById(toastId);
                if (toastElement) toastElement.remove();
            }, 5000);
        }
        
        // Update selection state and calculate totals
        function updateSelection() {
            const checkboxes = document.querySelectorAll('.item-checkbox');
            const selectAllCheckbox = document.getElementById('selectAll');
            const deleteBtn = document.getElementById('deleteSelected');
            const selectedCountSpan = document.getElementById('selectedCount');
            const totalItemsSpan = document.getElementById('totalItems');
            
            selectedItems.clear();
            
            let checkedCount = 0;
            checkboxes.forEach(checkbox => {
                if (checkbox.checked) {
                    selectedItems.add(checkbox.dataset.cartId);
                    checkbox.closest('.cart-item').classList.add('selected');
                    checkedCount++;
                } else {
                    checkbox.closest('.cart-item').classList.remove('selected');
                }
            });
            
            // Update select all checkbox state
            if (checkedCount === 0) {
                selectAllCheckbox.checked = false;
                selectAllCheckbox.indeterminate = false;
            } else if (checkedCount === checkboxes.length) {
                selectAllCheckbox.checked = true;
                selectAllCheckbox.indeterminate = false;
            } else {
                selectAllCheckbox.checked = false;
                selectAllCheckbox.indeterminate = true;
            }
            
            // Update UI elements
            if (deleteBtn) {
                deleteBtn.disabled = checkedCount === 0;
            }
            if (selectedCountSpan) {
                selectedCountSpan.textContent = checkedCount;
            }
            if (totalItemsSpan) {
                totalItemsSpan.textContent = checkboxes.length;
            }
            
            // Calculate totals for selected items
            calculateSelectedTotals();
        }
        
        // Make updateSelection available globally
        window.updateSelection = updateSelection;
        
        // Calculate totals for selected items
        function calculateSelectedTotals() {
            if (selectedItems.size === 0) {
                document.getElementById('selectedInfo').style.display = 'block';
                document.getElementById('shippingProgress').style.display = 'none';
                document.getElementById('freeShippingAlert').style.display = 'none';
                document.getElementById('orderSummary').style.opacity = '0.5';
                document.getElementById('checkoutBtn').disabled = true;
                
                // Reset values
                document.getElementById('selectedItemCount').textContent = '0';
                document.getElementById('subtotalAmount').textContent = '0đ';
                document.getElementById('shippingAmount').textContent = '0đ';
                document.getElementById('taxAmount').textContent = '0đ';
                document.getElementById('totalAmount').textContent = '0đ';
                return;
            }
            
            showLoading();
            
            fetch('cart_fixed.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded',
                },
                body: `action=calculate_selected&cart_ids[]=${Array.from(selectedItems).join('&cart_ids[]=')}`
            })
            .then(response => response.json())
            .then(data => {
                hideLoading();
                
                if (data.success) {
                    const totals = data.totals;
                    
                    document.getElementById('selectedInfo').style.display = 'none';
                    document.getElementById('orderSummary').style.opacity = '1';
                    document.getElementById('checkoutBtn').disabled = false;
                    
                    // Update summary
                    document.getElementById('selectedItemCount').textContent = totals.selected_count;
                    document.getElementById('subtotalAmount').textContent = totals.selected_subtotal_formatted;
                    document.getElementById('shippingAmount').textContent = totals.shipping_fee_formatted;
                    document.getElementById('shippingAmount').className = totals.shipping_fee === 0 ? 'text-success' : '';
                    document.getElementById('taxAmount').textContent = totals.tax_formatted;
                    document.getElementById('totalAmount').textContent = totals.total_formatted;
                    
                    // Update shipping progress
                    if (totals.free_shipping_remaining > 0) {
                        document.getElementById('shippingProgress').style.display = 'block';
                        document.getElementById('freeShippingAlert').style.display = 'none';
                        
                        const percentage = (totals.selected_subtotal / 500000) * 100;
                        document.getElementById('shippingProgressBar').style.width = Math.min(percentage, 100) + '%';
                        document.getElementById('remainingForFreeShip').textContent = formatPrice(totals.free_shipping_remaining);
                        document.getElementById('shippingProgressText').textContent = 
                            `${totals.selected_subtotal_formatted} / 500.000đ`;
                    } else {
                        document.getElementById('shippingProgress').style.display = 'none';
                        document.getElementById('freeShippingAlert').style.display = 'block';
                    }
                } else {
                    showToast(data.message, 'error');
                }
            })
            .catch(error => {
                hideLoading();
                showToast('Có lỗi xảy ra khi tính tổng', 'error');
                console.error('Error:', error);
            });
        }
        
        // Format price function
        function formatPrice(amount) {
            return new Intl.NumberFormat('vi-VN').format(amount) + 'đ';
        }
        
        // Select all functionality
        document.getElementById('selectAll')?.addEventListener('change', function() {
            const checkboxes = document.querySelectorAll('.item-checkbox');
            checkboxes.forEach(checkbox => {
                checkbox.checked = this.checked;
            });
            updateSelection();
        });
        
        // UPDATE QUANTITY
        function updateQuantity(cartId, newQuantity) {
            if (newQuantity < 1) return;
            
            showLoading();
            
            fetch('cart_fixed.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded',
                },
                body: `action=update_quantity&cart_id=${cartId}&quantity=${newQuantity}`
            })
            .then(response => response.json())
            .then(data => {
                hideLoading();
                
                if (data.success) {
                    // Update subtotal for this item
                    const cartItem = document.querySelector(`[data-cart-id="${cartId}"]`);
                    const subtotalElement = cartItem.querySelector('.subtotal-price');
                    const quantityInput = cartItem.querySelector('.quantity-input');
                    const minusBtn = cartItem.querySelector('.quantity-btn:first-child');
                    const plusBtn = cartItem.querySelector('.quantity-btn:last-child');
                    
                    subtotalElement.textContent = data.subtotal;
                    subtotalElement.dataset.quantity = newQuantity;
                    quantityInput.value = newQuantity;
                    
                    const maxQuantity = parseInt(quantityInput.max);
                    minusBtn.disabled = newQuantity <= 1;
                    plusBtn.disabled = newQuantity >= maxQuantity;
                    
                    // Recalculate if item is selected
                    if (selectedItems.has(cartId.toString())) {
                        calculateSelectedTotals();
                    }
                    
                    showToast(data.message, 'success');
                } else {
                    showToast(data.message, 'error');
                    
                    if (data.max_quantity) {
                        const cartItem = document.querySelector(`[data-cart-id="${cartId}"]`);
                        const quantityInput = cartItem.querySelector('.quantity-input');
                        quantityInput.value = data.max_quantity;
                    }
                }
            })
            .catch(error => {
                hideLoading();
                showToast('Có lỗi xảy ra khi cập nhật', 'error');
                console.error('Error:', error);
            });
        }
        
        // REMOVE SINGLE ITEM
        function removeItem(cartId) {
            if (!confirm('Bạn có chắc muốn xóa sản phẩm này khỏi giỏ hàng?')) {
                return;
            }
            
            const cartItem = document.querySelector(`[data-cart-id="${cartId}"]`);
            cartItem.classList.add('animate-slideOut');
            
            fetch('cart_fixed.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded',
                },
                body: `action=remove_item&cart_id=${cartId}`
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    setTimeout(() => {
                        cartItem.remove();
                        selectedItems.delete(cartId.toString());
                        updateSelection();
                        showToast(data.message, 'success');
                        
                        // Check if cart is empty
                        if (document.querySelectorAll('.cart-item').length === 0) {
                            setTimeout(() => location.reload(), 1000);
                        }
                    }, 300);
                } else {
                    cartItem.classList.remove('animate-slideOut');
                    showToast(data.message, 'error');
                }
            })
            .catch(error => {
                cartItem.classList.remove('animate-slideOut');
                showToast('Có lỗi xảy ra khi xóa sản phẩm', 'error');
                console.error('Error:', error);
            });
        }
        
        // REMOVE SELECTED ITEMS
        function removeSelectedItems() {
            if (selectedItems.size === 0) {
                showToast('Vui lòng chọn sản phẩm để xóa', 'error');
                return;
            }
            
            if (!confirm(`Bạn có chắc muốn xóa ${selectedItems.size} sản phẩm đã chọn?`)) {
                return;
            }
            
            showLoading();
            
            fetch('cart_fixed.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded',
                },
                body: `action=remove_selected&cart_ids[]=${Array.from(selectedItems).join('&cart_ids[]=')}`
            })
            .then(response => response.json())
            .then(data => {
                hideLoading();
                
                if (data.success) {
                    // Remove items from DOM with animation
                    selectedItems.forEach(cartId => {
                        const cartItem = document.querySelector(`[data-cart-id="${cartId}"]`);
                        if (cartItem) {
                            cartItem.classList.add('animate-slideOut');
                            setTimeout(() => cartItem.remove(), 300);
                        }
                    });
                    
                    selectedItems.clear();
                    setTimeout(() => {
                        updateSelection();
                        showToast(data.message, 'success');
                        
                        // Check if cart is empty
                        if (document.querySelectorAll('.cart-item').length === 0) {
                            setTimeout(() => location.reload(), 1000);
                        }
                    }, 400);
                } else {
                    showToast(data.message, 'error');
                }
            })
            .catch(error => {
                hideLoading();
                showToast('Có lỗi xảy ra khi xóa sản phẩm', 'error');
                console.error('Error:', error);
            });
        }
        
        // CLEAR CART
        function clearCart() {
            if (!confirm('Bạn có chắc muốn xóa tất cả sản phẩm trong giỏ hàng?')) {
                return;
            }
            
            showLoading();
            
            fetch('cart_fixed.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded',
                },
                body: 'action=clear_cart'
            })
            .then(response => response.json())
            .then(data => {
                hideLoading();
                
                if (data.success) {
                    showToast(data.message, 'success');
                    setTimeout(() => location.reload(), 1000);
                } else {
                    showToast(data.message, 'error');
                }
            })
            .catch(error => {
                hideLoading();
                showToast('Có lỗi xảy ra', 'error');
                console.error('Error:', error);
            });
        }
        
        // 🔧 DEBUG FUNCTION - Detailed checkout debugging
        function debugProceedToCheckout() {
            console.log('🚨 DEBUG START: debugProceedToCheckout() called');
            console.log('🚨 DEBUG: Current URL:', window.location.href);
            console.log('🚨 DEBUG: Selected items:', selectedItems);
            console.log('🚨 DEBUG: Selected items size:', selectedItems.size);
            
            // Check if original function exists
            if (typeof proceedToCheckout === 'function') {
                console.log('🚨 DEBUG: proceedToCheckout function exists');
            } else {
                console.log('🚨 ERROR: proceedToCheckout function NOT found');
            }
            
            // Check for any global redirect functions
            console.log('🚨 DEBUG: Checking for conflicting functions...');
            if (window.redirectToCart) {
                console.log('🚨 WARNING: window.redirectToCart exists:', window.redirectToCart);
            }
            if (window.goToCart) {
                console.log('🚨 WARNING: window.goToCart exists:', window.goToCart);
            }
            if (window.checkoutRedirect) {
                console.log('🚨 WARNING: window.checkoutRedirect exists:', window.checkoutRedirect);
            }
            
            // Test direct redirect
            console.log('🚨 DEBUG: Testing direct redirect to checkout.php...');
            
            setTimeout(() => {
                console.log('🚨 DEBUG: Executing redirect in 2 seconds...');
                window.location.href = '/tktshop/customer/checkout.php';
            }, 2000);
            
            return false; // Prevent any other event handlers
        }
        
        // 🔧 FIXED: PROCEED TO CHECKOUT - Correct redirect path
        function proceedToCheckout() {
            console.log('🔧 DEBUG: proceedToCheckout() called from cart_fixed.php');
            console.log('🔧 DEBUG: Selected items:', selectedItems.size);
            
            if (selectedItems.size === 0) {
                showToast('Vui lòng chọn ít nhất một sản phẩm để thanh toán', 'error');
                return;
            }
            
            console.log('🔧 DEBUG: About to send AJAX request to set checkout items');
            showLoading();
            
            // Store selected items in session for checkout
            fetch('cart_fixed.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded',
                },
                body: `action=set_checkout_items&cart_ids[]=${Array.from(selectedItems).join('&cart_ids[]=')}`
            })
            .then(response => response.json())
            .then(data => {
                hideLoading();
                if (data.success) {
                    // 🔧 FIXED: Correct redirect to checkout.php
                    window.location.href = 'checkout.php';
                } else {
                    showToast('Có lỗi xảy ra khi chuẩn bị thanh toán', 'error');
                }
            })
            .catch(error => {
                hideLoading();
                console.error('Error:', error);
                // 🔧 FIXED: Fallback redirect to correct path
                window.location.href = 'checkout.php';
            });
        }
        
        // Save for later (future feature)
        function saveForLater() {
            showToast('Tính năng lưu để mua sau sẽ được cập nhật sớm!', 'success');
        }
        
        // Scroll to top functionality
        window.addEventListener('scroll', function() {
            const scrollBtn = document.getElementById('scrollToTop');
            if (window.pageYOffset > 300) {
                scrollBtn.style.display = 'block';
            } else {
                scrollBtn.style.display = 'none';
            }
        });
        
        document.getElementById('scrollToTop')?.addEventListener('click', function() {
            window.scrollTo({ top: 0, behavior: 'smooth' });
        });
        
        // Initialize
        document.addEventListener('DOMContentLoaded', function() {
            // Add event listeners to quantity inputs
            const quantityInputs = document.querySelectorAll('.quantity-input');
            quantityInputs.forEach(input => {
                let timeout;
                input.addEventListener('input', function() {
                    clearTimeout(timeout);
                    timeout = setTimeout(() => {
                        const cartId = this.closest('.cart-item').dataset.cartId;
                        const newQuantity = parseInt(this.value);
                        if (newQuantity > 0) {
                            updateQuantity(cartId, newQuantity);
                        }
                    }, 500);
                });
            });
            
            // Add event listeners to item checkboxes
            const itemCheckboxes = document.querySelectorAll('.item-checkbox');
            itemCheckboxes.forEach(checkbox => {
                checkbox.addEventListener('change', updateSelection);
            });
            
            // Initialize selection
            updateSelection();
            
            console.log('🔧 TKT Shop Cart FIXED - Unified Cart System initialized successfully');
            console.log('📊 Cart items loaded:', <?= count($cart_items) ?>);
            console.log('📊 Schema types detected:', document.querySelectorAll('.schema-badge').length);
            console.log('🚀 Fixed checkout redirect to: checkout.php');
            console.log('💰 Free shipping threshold: 500,000 VND');
            
            // 🔧 DEBUG: Check if checkout button exists and its attributes
            const checkoutBtn = document.getElementById('checkoutBtn');
            if (checkoutBtn) {
                console.log('🔧 DEBUG: Checkout button found');
                console.log('🔧 DEBUG: Button onclick:', checkoutBtn.onclick);
                console.log('🔧 DEBUG: Button getAttribute onclick:', checkoutBtn.getAttribute('onclick'));
            } else {
                console.log('🔧 DEBUG: Checkout button NOT found');
            }
        });
        
        // Add CSS for animations
        const style = document.createElement('style');
        style.textContent = `
            .animate-fadeIn {
                animation: fadeIn 0.5s ease-in;
            }
            
            @keyframes fadeIn {
                from { opacity: 0; transform: translateY(-20px); }
                to { opacity: 1; transform: translateY(0); }
            }
            
            .animate-slideOut {
                animation: slideOut 0.3s ease-out forwards;
            }
            
            @keyframes slideOut {
                from { opacity: 1; transform: translateX(0); }
                to { opacity: 0; transform: translateX(-100%); }
            }
        `;
        document.head.appendChild(style);
    </script>
</body>
</html>