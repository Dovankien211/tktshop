<?php
// customer/cart.php
/**
 * Giỏ hàng - CRUD AJAX + tính tổng tự động + validate tồn kho
 * Chức năng: Hiển thị, thêm, sửa, xóa sản phẩm trong giỏ hàng
 */

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
                // Kiểm tra sản phẩm và tồn kho
                $stmt = $pdo->prepare("
                    SELECT gh.*, bsp.so_luong_ton_kho, sp.ten_san_pham,
                           bsp.gia_ban, kc.kich_co, ms.ten_mau
                    FROM gio_hang gh
                    JOIN bien_the_san_pham bsp ON gh.bien_the_san_pham_id = bsp.id
                    JOIN san_pham_chinh sp ON bsp.san_pham_id = sp.id
                    JOIN kich_co kc ON bsp.kich_co_id = kc.id
                    JOIN mau_sac ms ON bsp.mau_sac_id = ms.id
                    WHERE gh.id = ? AND (gh.khach_hang_id = ? OR gh.session_id = ?)
                ");
                $stmt->execute([$cart_id, $customer_id, $session_id]);
                $item = $stmt->fetch();
                
                if (!$item) {
                    echo json_encode(['success' => false, 'message' => 'Sản phẩm không tồn tại trong giỏ hàng']);
                    exit;
                }
                
                if ($quantity > $item['so_luong_ton_kho']) {
                    echo json_encode([
                        'success' => false, 
                        'message' => "Chỉ còn {$item['so_luong_ton_kho']} sản phẩm trong kho",
                        'max_quantity' => $item['so_luong_ton_kho']
                    ]);
                    exit;
                }
                
                // Cập nhật số lượng
                $pdo->prepare("UPDATE gio_hang SET so_luong = ? WHERE id = ?")
                    ->execute([$quantity, $cart_id]);
                
                // Tính tổng mới
                $subtotal = $quantity * $item['gia_ban'];
                $cart_totals = calculateCartTotals($pdo, $customer_id, $session_id);
                
                echo json_encode([
                    'success' => true,
                    'message' => 'Cập nhật thành công',
                    'subtotal' => formatPrice($subtotal),
                    'subtotal_raw' => $subtotal,
                    'cart_totals' => $cart_totals
                ]);
                
            } catch (Exception $e) {
                echo json_encode(['success' => false, 'message' => 'Có lỗi xảy ra khi cập nhật']);
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
            
        // XÓA TẤT CẢ (CLEAR CART)
        case 'clear_cart':
            try {
                $stmt = $pdo->prepare("
                    DELETE FROM gio_hang 
                    WHERE khach_hang_id = ? OR session_id = ?
                ");
                $stmt->execute([$customer_id, $session_id]);
                
                echo json_encode([
                    'success' => true,
                    'message' => 'Đã xóa tất cả sản phẩm trong giỏ hàng'
                ]);
                
            } catch (Exception $e) {
                echo json_encode(['success' => false, 'message' => 'Có lỗi xảy ra']);
            }
            exit;
            
        // THÊM SẢN PHẨM (CREATE) - từ product detail
        case 'add_to_cart':
            $variant_id = (int)$_POST['variant_id'];
            $quantity = max(1, (int)$_POST['quantity']);
            
            try {
                // Kiểm tra biến thể
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
                    echo json_encode(['success' => false, 'message' => 'Sản phẩm không tồn tại']);
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
                    WHERE bien_the_san_pham_id = ? 
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
                    
                    $pdo->prepare("UPDATE gio_hang SET so_luong = ? WHERE id = ?")
                        ->execute([$new_quantity, $existing['id']]);
                } else {
                    // Thêm mới
                    $pdo->prepare("
                        INSERT INTO gio_hang (khach_hang_id, session_id, bien_the_san_pham_id, so_luong, ngay_tao)
                        VALUES (?, ?, ?, ?, NOW())
                    ")->execute([$customer_id, $session_id, $variant_id, $quantity]);
                }
                
                $cart_totals = calculateCartTotals($pdo, $customer_id, $session_id);
                
                echo json_encode([
                    'success' => true,
                    'message' => 'Đã thêm vào giỏ hàng',
                    'cart_totals' => $cart_totals
                ]);
                
            } catch (Exception $e) {
                echo json_encode(['success' => false, 'message' => 'Có lỗi xảy ra khi thêm sản phẩm']);
            }
            exit;
    }
}

// Hàm tính tổng giỏ hàng
function calculateCartTotals($pdo, $customer_id, $session_id) {
    $stmt = $pdo->prepare("
        SELECT 
            SUM(gh.so_luong * bsp.gia_ban) as subtotal,
            COUNT(*) as item_count,
            SUM(gh.so_luong) as total_quantity
        FROM gio_hang gh
        JOIN bien_the_san_pham bsp ON gh.bien_the_san_pham_id = bsp.id
        WHERE (gh.khach_hang_id = ? OR gh.session_id = ?)
    ");
    $stmt->execute([$customer_id, $session_id]);
    $totals = $stmt->fetch();
    
    $subtotal = $totals['subtotal'] ?? 0;
    $shipping_fee = $subtotal >= 500000 ? 0 : 30000; // Miễn phí ship từ 500k
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
}

// Lấy danh sách sản phẩm trong giỏ hàng
$customer_id = $_SESSION['customer_id'] ?? null;
$session_id = $customer_id ? null : ($_SESSION['session_id'] ?? null);

$cart_items = [];
$cart_totals = ['subtotal' => 0, 'item_count' => 0];

if ($customer_id || $session_id) {
    $stmt = $pdo->prepare("
        SELECT gh.*, sp.ten_san_pham, sp.slug, sp.hinh_anh_chinh,
               bsp.gia_ban, bsp.so_luong_ton_kho, bsp.sku,
               kc.kich_co, ms.ten_mau, ms.ma_mau
        FROM gio_hang gh
        JOIN bien_the_san_pham bsp ON gh.bien_the_san_pham_id = bsp.id
        JOIN san_pham_chinh sp ON bsp.san_pham_id = sp.id
        JOIN kich_co kc ON bsp.kich_co_id = kc.id
        JOIN mau_sac ms ON bsp.mau_sac_id = ms.id
        WHERE (gh.khach_hang_id = ? OR gh.session_id = ?)
        ORDER BY gh.ngay_tao DESC
    ");
    $stmt->execute([$customer_id, $session_id]);
    $cart_items = $stmt->fetchAll();
    
    $cart_totals = calculateCartTotals($pdo, $customer_id, $session_id);
}

// Sản phẩm gợi ý (nếu giỏ hàng trống hoặc có sản phẩm)
$suggested_products = [];
if (empty($cart_items)) {
    // Gợi ý sản phẩm phổ biến nếu giỏ hàng trống
    $stmt = $pdo->prepare("
        SELECT sp.*, MIN(bsp.gia_ban) as gia_thap_nhat
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
} else {
    // Gợi ý sản phẩm liên quan dựa trên danh mục
    $categories = array_unique(array_column($cart_items, 'danh_muc_id'));
    if (!empty($categories)) {
        $placeholders = str_repeat('?,', count($categories) - 1) . '?';
        $stmt = $pdo->prepare("
            SELECT sp.*, MIN(bsp.gia_ban) as gia_thap_nhat
            FROM san_pham_chinh sp
            JOIN bien_the_san_pham bsp ON sp.id = bsp.san_pham_id
            WHERE sp.danh_muc_id IN ($placeholders)
            AND sp.trang_thai = 'hoat_dong' AND bsp.trang_thai = 'hoat_dong'
            AND bsp.so_luong_ton_kho > 0
            GROUP BY sp.id
            ORDER BY sp.luot_xem DESC
            LIMIT 4
        ");
        $stmt->execute($categories);
        $suggested_products = $stmt->fetchAll();
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
            border-bottom: 1px solid #eee;
            padding: 20px 0;
            transition: all 0.3s ease;
        }
        
        .cart-item:last-child {
            border-bottom: none;
        }
        
        .cart-item.removing {
            opacity: 0.5;
            transform: translateX(-20px);
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
        
        .suggested-products {
            background: #fff;
            border: 1px solid #e9ecef;
            border-radius: 10px;
            padding: 20px;
            margin-top: 30px;
        }
        
        .product-card {
            transition: transform 0.3s ease;
            cursor: pointer;
        }
        
        .product-card:hover {
            transform: translateY(-5px);
        }
        
        .empty-cart {
            text-align: center;
            padding: 60px 20px;
        }
        
        .empty-cart i {
            font-size: 4rem;
            color: #6c757d;
            margin-bottom: 20px;
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
        
        @media (max-width: 768px) {
            .product-image {
                width: 80px;
                height: 80px;
            }
            
            .cart-summary {
                position: static;
                margin-top: 30px;
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
                    <div class="empty-cart">
                        <i class="fas fa-shopping-cart"></i>
                        <h4>Giỏ hàng của bạn đang trống</h4>
                        <p class="text-muted mb-4">Khám phá các sản phẩm tuyệt vời và thêm vào giỏ hàng ngay!</p>
                        <a href="products.php" class="btn btn-primary">
                            <i class="fas fa-shopping-bag me-2"></i>
                            Tiếp tục mua sắm
                        </a>
                    </div>
                <?php else: ?>
                    <!-- Cart Items -->
                    <div id="cartItems">
                        <?php foreach ($cart_items as $item): ?>
                            <div class="cart-item" data-cart-id="<?= $item['id'] ?>">
                                <div class="row align-items-center">
                                    <!-- Product Image -->
                                    <div class="col-md-2 col-3">
                                        <img src="/uploads/products/<?= $item['hinh_anh_chinh'] ?: 'default-product.jpg' ?>" 
                                             alt="<?= htmlspecialchars($item['ten_san_pham']) ?>"
                                             class="product-image">
                                    </div>
                                    
                                    <!-- Product Info -->
                                    <div class="col-md-4 col-9">
                                        <h6 class="mb-1">
                                            <a href="product_detail.php?slug=<?= $item['slug'] ?>" 
                                               class="text-decoration-none text-dark">
                                                <?= htmlspecialchars($item['ten_san_pham']) ?>
                                            </a>
                                        </h6>
                                        <small class="text-muted">
                                            Size: <?= htmlspecialchars($item['kich_co']) ?> | 
                                            Màu: <span style="color: <?= $item['ma_mau'] ?>"><?= htmlspecialchars($item['ten_mau']) ?></span>
                                        </small>
                                        <br>
                                        <small class="text-muted">SKU: <?= htmlspecialchars($item['sku']) ?></small>
                                        
                                        <?php if ($item['so_luong_ton_kho'] <= 5): ?>
                                            <div class="mt-1">
                                                <small class="text-warning">
                                                    <i class="fas fa-exclamation-triangle"></i>
                                                    Chỉ còn <?= $item['so_luong_ton_kho'] ?> sản phẩm
                                                </small>
                                            </div>
                                        <?php endif; ?>
                                    </div>
                                    
                                    <!-- Price -->
                                    <div class="col-md-2 col-4 text-center">
                                        <div class="fw-bold text-primary"><?= formatPrice($item['gia_ban']) ?></div>
                                    </div>
                                    
                                    <!-- Quantity Controls -->
                                    <div class="col-md-2 col-4">
                                        <div class="quantity-controls input-group">
                                            <button class="btn btn-outline-secondary quantity-btn" 
                                                    type="button" 
                                                    onclick="updateQuantity(<?= $item['id'] ?>, <?= $item['so_luong'] - 1 ?>)"
                                                    <?= $item['so_luong'] <= 1 ? 'disabled' : '' ?>>
                                                -
                                            </button>
                                            <input type="number" 
                                                   class="form-control quantity-input" 
                                                   value="<?= $item['so_luong'] ?>"
                                                   min="1" 
                                                   max="<?= $item['so_luong_ton_kho'] ?>"
                                                   onchange="updateQuantity(<?= $item['id'] ?>, this.value)">
                                            <button class="btn btn-outline-secondary quantity-btn" 
                                                    type="button"
                                                    onclick="updateQuantity(<?= $item['id'] ?>, <?= $item['so_luong'] + 1 ?>)"
                                                    <?= $item['so_luong'] >= $item['so_luong_ton_kho'] ? 'disabled' : '' ?>>
                                                +
                                            </button>
                                        </div>
                                    </div>
                                    
                                    <!-- Subtotal -->
                                    <div class="col-md-2 col-4 text-center">
                                        <div class="fw-bold subtotal-price">
                                            <?= formatPrice($item['so_luong'] * $item['gia_ban']) ?>
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
                        <a href="products.php" class="btn btn-outline-primary">
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
                        
                        <!-- Free Shipping Progress -->
                        <?php if ($cart_totals['free_shipping_remaining'] > 0): ?>
                            <div class="mb-3">
                                <div class="d-flex justify-content-between mb-2">
                                    <small>Mua thêm để được miễn phí ship:</small>
                                    <small class="fw-bold text-primary">
                                        <?= formatPrice($cart_totals['free_shipping_remaining']) ?>
                                    </small>
                                </div>
                                <div class="progress mb-2">
                                    <div class="progress-bar bg-success" 
                                         style="width: <?= ($cart_totals['subtotal'] / $cart_totals['free_shipping_threshold']) * 100 ?>%">
                                    </div>
                                </div>
                                <small class="text-muted">
                                    <?= formatPrice($cart_totals['subtotal']) ?> / <?= formatPrice($cart_totals['free_shipping_threshold']) ?>
                                </small>
                            </div>
                        <?php else: ?>
                            <div class="alert alert-success py-2 mb-3">
                                <i class="fas fa-check-circle me-1"></i>
                                <small>Bạn được miễn phí vận chuyển!</small>
                            </div>
                        <?php endif; ?>
                        
                        <!-- Order Summary -->
                        <div class="border-top pt-3">
                            <div class="d-flex justify-content-between mb-2">
                                <span>Tạm tính (<?= $cart_totals['item_count'] ?> sản phẩm):</span>
                                <span id="subtotalAmount"><?= $cart_totals['subtotal_formatted'] ?></span>
                            </div>
                            
                            <div class="d-flex justify-content-between mb-2">
                                <span>Phí vận chuyển:</span>
                                <span id="shippingAmount" class="<?= $cart_totals['shipping_fee'] == 0 ? 'text-success' : '' ?>">
                                    <?= $cart_totals['shipping_fee'] == 0 ? 'Miễn phí' : $cart_totals['shipping_fee_formatted'] ?>
                                </span>
                            </div>
                            
                            <div class="d-flex justify-content-between mb-2">
                                <span>Thuế (10%):</span>
                                <span id="taxAmount"><?= $cart_totals['tax_formatted'] ?></span>
                            </div>
                            
                            <hr>
                            
                            <div class="d-flex justify-content-between mb-3">
                                <strong>Tổng cộng:</strong>
                                <strong class="text-primary fs-5" id="totalAmount">
                                    <?= $cart_totals['total_formatted'] ?>
                                </strong>
                            </div>
                            
                            <div class="d-grid gap-2">
                                <a href="checkout.php" class="btn btn-primary btn-lg">
                                    <i class="fas fa-credit-card me-2"></i>
                                    Thanh toán
                                </a>
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
            <div class="suggested-products">
                <h4 class="mb-4">
                    <?= empty($cart_items) ? 'Sản phẩm nổi bật' : 'Có thể bạn quan tâm' ?>
                </h4>
                <div class="row">
                    <?php foreach ($suggested_products as $product): ?>
                        <div class="col-lg-3 col-md-4 col-sm-6 mb-3">
                            <div class="card product-card h-100" onclick="location.href='product_detail.php?slug=<?= $product['slug'] ?>'">
                                <img src="/uploads/products/<?= $product['hinh_anh_chinh'] ?: 'default-product.jpg' ?>" 
                                     class="card-img-top" 
                                     alt="<?= htmlspecialchars($product['ten_san_pham']) ?>"
                                     style="height: 180px; object-fit: cover;">
                                <div class="card-body d-flex flex-column">
                                    <h6 class="card-title"><?= htmlspecialchars($product['ten_san_pham']) ?></h6>
                                    <div class="text-warning mb-2">
                                        <?php for ($i = 1; $i <= 5; $i++): ?>
                                            <i class="fas fa-star<?= $i <= floor($product['diem_danh_gia_tb']) ? '' : ' text-muted' ?> small"></i>
                                        <?php endfor; ?>
                                        <small class="text-muted ms-1">(<?= $product['so_luong_danh_gia'] ?>)</small>
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
        <?php endif; ?>
    </div>
    
    <!-- Footer -->
    <?php include 'includes/footer.php'; ?>
    
    <!-- Scripts -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
    
    <script>
        // AJAX Functions for Cart CRUD
        
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
            // Create toast element if not exists
            let toastContainer = document.getElementById('toastContainer');
            if (!toastContainer) {
                toastContainer = document.createElement('div');
                toastContainer.id = 'toastContainer';
                toastContainer.className = 'toast-container position-fixed top-0 end-0 p-3';
                toastContainer.style.zIndex = '9999';
                document.body.appendChild(toastContainer);
            }
            
            const toastId = 'toast-' + Date.now();
            const toastHtml = `
                <div id="${toastId}" class="toast align-items-center text-white bg-${type === 'success' ? 'success' : 'danger'} border-0" role="alert">
                    <div class="d-flex">
                        <div class="toast-body">
                            <i class="fas fa-${type === 'success' ? 'check-circle' : 'exclamation-circle'} me-2"></i>
                            ${message}
                        </div>
                        <button type="button" class="btn-close btn-close-white me-2 m-auto" data-bs-dismiss="toast"></button>
                    </div>
                </div>
            `;
            
            toastContainer.insertAdjacentHTML('beforeend', toastHtml);
            const toast = new bootstrap.Toast(document.getElementById(toastId));
            toast.show();
            
            // Remove toast element after it hides
            setTimeout(() => {
                const toastElement = document.getElementById(toastId);
                if (toastElement) {
                    toastElement.remove();
                }
            }, 5000);
        }
        
        // Update cart totals in UI
        function updateCartTotals(totals) {
            if (totals) {
                document.getElementById('subtotalAmount').textContent = totals.subtotal_formatted;
                document.getElementById('shippingAmount').textContent = totals.shipping_fee == 0 ? 'Miễn phí' : totals.shipping_fee_formatted;
                document.getElementById('shippingAmount').className = totals.shipping_fee == 0 ? 'text-success' : '';
                document.getElementById('taxAmount').textContent = totals.tax_formatted;
                document.getElementById('totalAmount').textContent = totals.total_formatted;
                
                // Update free shipping progress
                const progressBar = document.querySelector('.progress-bar');
                if (progressBar) {
                    const percentage = (totals.subtotal / totals.free_shipping_threshold) * 100;
                    progressBar.style.width = Math.min(percentage, 100) + '%';
                }
                
                // Update cart counter in header (if exists)
                const cartCounter = document.querySelector('.cart-counter');
                if (cartCounter) {
                    cartCounter.textContent = totals.item_count;
                    cartCounter.style.display = totals.item_count > 0 ? 'inline' : 'none';
                }
            }
        }
        
        // UPDATE QUANTITY (U in CRUD)
        function updateQuantity(cartId, newQuantity) {
            if (newQuantity < 1) return;
            
            showLoading();
            
            fetch('cart.php', {
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
                    subtotalElement.textContent = data.subtotal;
                    
                    // Update quantity input
                    const quantityInput = cartItem.querySelector('.quantity-input');
                    quantityInput.value = newQuantity;
                    
                    // Update cart totals
                    updateCartTotals(data.cart_totals);
                    
                    // Update quantity buttons state
                    const minusBtn = cartItem.querySelector('.quantity-btn:first-child');
                    const plusBtn = cartItem.querySelector('.quantity-btn:last-child');
                    const maxQuantity = parseInt(quantityInput.max);
                    
                    minusBtn.disabled = newQuantity <= 1;
                    plusBtn.disabled = newQuantity >= maxQuantity;
                    
                    showToast(data.message, 'success');
                } else {
                    showToast(data.message, 'error');
                    
                    // Reset quantity if failed
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
        
        // REMOVE ITEM (D in CRUD)
        function removeItem(cartId) {
            if (!confirm('Bạn có chắc muốn xóa sản phẩm này khỏi giỏ hàng?')) {
                return;
            }
            
            const cartItem = document.querySelector(`[data-cart-id="${cartId}"]`);
            cartItem.classList.add('removing');
            
            fetch('cart.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded',
                },
                body: `action=remove_item&cart_id=${cartId}`
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    // Remove item from DOM
                    cartItem.remove();
                    
                    // Update cart totals
                    updateCartTotals(data.cart_totals);
                    
                    showToast(data.message, 'success');
                    
                    // Check if cart is empty
                    const remainingItems = document.querySelectorAll('.cart-item').length;
                    if (remainingItems === 0) {
                        location.reload(); // Reload to show empty cart message
                    }
                } else {
                    cartItem.classList.remove('removing');
                    showToast(data.message, 'error');
                }
            })
            .catch(error => {
                cartItem.classList.remove('removing');
                showToast('Có lỗi xảy ra khi xóa sản phẩm', 'error');
                console.error('Error:', error);
            });
        }
        
        // CLEAR CART (D in CRUD)
        function clearCart() {
            if (!confirm('Bạn có chắc muốn xóa tất cả sản phẩm trong giỏ hàng?')) {
                return;
            }
            
            showLoading();
            
            fetch('cart.php', {
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
                    setTimeout(() => {
                        location.reload();
                    }, 1000);
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
        
        // ADD TO CART (C in CRUD) - Function for product pages
        function addToCart(variantId, quantity = 1) {
            showLoading();
            
            fetch('cart.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded',
                },
                body: `action=add_to_cart&variant_id=${variantId}&quantity=${quantity}`
            })
            .then(response => response.json())
            .then(data => {
                hideLoading();
                
                if (data.success) {
                    showToast(data.message, 'success');
                    
                    // Update cart counter
                    if (data.cart_totals) {
                        const cartCounter = document.querySelector('.cart-counter');
                        if (cartCounter) {
                            cartCounter.textContent = data.cart_totals.item_count;
                            cartCounter.style.display = data.cart_totals.item_count > 0 ? 'inline' : 'none';
                        }
                    }
                } else {
                    showToast(data.message, 'error');
                }
            })
            .catch(error => {
                hideLoading();
                showToast('Có lỗi xảy ra khi thêm sản phẩm', 'error');
                console.error('Error:', error);
            });
        }
        
        // Save for later (future feature)
        function saveForLater() {
            showToast('Tính năng lưu để mua sau sẽ được cập nhật sớm!', 'success');
        }
        
        // Auto-save cart when quantity changes
        document.addEventListener('DOMContentLoaded', function() {
            // Add debounce to quantity inputs
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
                    }, 500); // Wait 500ms after user stops typing
                });
            });
            
            // Keyboard shortcuts
            document.addEventListener('keydown', function(e) {
                // Ctrl + Delete to clear cart
                if (e.ctrlKey && e.key === 'Delete') {
                    e.preventDefault();
                    clearCart();
                }
            });
        });
        
        // Update cart display every 30 seconds (for stock changes)
        setInterval(function() {
            // Check if page is visible
            if (!document.hidden) {
                fetch('cart.php', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/x-www-form-urlencoded',
                    },
                    body: 'action=get_cart_totals'
                })
                .then(response => response.json())
                .then(data => {
                    if (data.cart_totals) {
                        updateCartTotals(data.cart_totals);
                    }
                })
                .catch(error => {
                    console.error('Background update error:', error);
                });
            }
        }, 30000); // 30 seconds
    </script>
</body>
</html>