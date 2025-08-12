<?php
// customer/product_detail.php - FINAL FIXED VERSION
/**
 * Chi tiết sản phẩm - Hỗ trợ cả bảng products và san_pham_chinh
 */
session_start();

require_once '../config/database.php';
require_once '../config/config.php';

// Nhận parameter từ URL
$id = (int)($_GET['id'] ?? 0);
$slug = $_GET['slug'] ?? '';

if (!$id && !$slug) {
    header('Location: products.php');
    exit;
}

$product = null;
$variants = [];
$reviews = [];
$related_products = [];

// 🔧 LOGIC MỚI: Ưu tiên slug trước, sau đó mới đến id
if (!empty($slug)) {
    // THỬ BẢNG SAN_PHAM_CHINH TRƯỚC (vì có slug đầy đủ)
    try {
        $stmt = $pdo->prepare("
            SELECT sp.*, dm.ten_danh_muc, dm.slug as danh_muc_slug,
                   COALESCE(sp.gia_khuyen_mai, sp.gia_goc) as gia_hien_tai,
                   CASE 
                       WHEN sp.gia_khuyen_mai IS NOT NULL AND sp.gia_khuyen_mai < sp.gia_goc 
                       THEN ROUND(((sp.gia_goc - sp.gia_khuyen_mai) / sp.gia_goc) * 100, 0)
                       ELSE 0
                   END as phan_tram_giam
            FROM san_pham_chinh sp
            LEFT JOIN danh_muc_giay dm ON sp.danh_muc_id = dm.id
            WHERE sp.slug = ? AND sp.trang_thai = 'hoat_dong'
        ");
        $stmt->execute([$slug]);
        $product = $stmt->fetch();
        
        if ($product) {
            $product_table = 'san_pham_chinh';
            
            // Cập nhật lượt xem
            $pdo->prepare("UPDATE san_pham_chinh SET luot_xem = luot_xem + 1 WHERE id = ?")->execute([$product['id']]);
            
            // Lấy biến thể sản phẩm
            $stmt = $pdo->prepare("
                SELECT bsp.*, kc.kich_co, ms.ten_mau, ms.ma_mau
                FROM bien_the_san_pham bsp
                JOIN kich_co kc ON bsp.kich_co_id = kc.id
                JOIN mau_sac ms ON bsp.mau_sac_id = ms.id
                WHERE bsp.san_pham_id = ? AND bsp.trang_thai = 'hoat_dong'
                ORDER BY kc.thu_tu_sap_xep, ms.thu_tu_hien_thi
            ");
            $stmt->execute([$product['id']]);
            $variants = $stmt->fetchAll();
            
            // Nhóm biến thể theo size và màu
            $sizes = [];
            $colors = [];
            $variant_matrix = [];
            
            foreach ($variants as $variant) {
                if (!in_array($variant['kich_co'], $sizes)) {
                    $sizes[] = $variant['kich_co'];
                }
                if (!isset($colors[$variant['mau_sac_id']])) {
                    $colors[$variant['mau_sac_id']] = [
                        'id' => $variant['mau_sac_id'],
                        'ten_mau' => $variant['ten_mau'],
                        'ma_mau' => $variant['ma_mau']
                    ];
                }
                $variant_matrix[$variant['kich_co']][$variant['mau_sac_id']] = $variant;
            }
            
            // Sản phẩm liên quan từ san_pham_chinh
            $stmt = $pdo->prepare("
                SELECT sp.*, 
                       COALESCE(sp.gia_khuyen_mai, sp.gia_goc) as gia_hien_tai,
                       MIN(bsp.gia_ban) as gia_thap_nhat,
                       SUM(bsp.so_luong_ton_kho) as tong_ton_kho
                FROM san_pham_chinh sp
                LEFT JOIN bien_the_san_pham bsp ON sp.id = bsp.san_pham_id AND bsp.trang_thai = 'hoat_dong'
                WHERE sp.danh_muc_id = ? AND sp.id != ? AND sp.trang_thai = 'hoat_dong'
                GROUP BY sp.id
                HAVING tong_ton_kho > 0
                ORDER BY sp.luot_xem DESC
                LIMIT 4
            ");
            $stmt->execute([$product['danh_muc_id'], $product['id']]);
            $related_products = $stmt->fetchAll();
        }
    } catch (Exception $e) {
        error_log("Error querying san_pham_chinh table: " . $e->getMessage());
    }
}

// Nếu không tìm thấy bằng slug, thử tìm bằng id trong bảng products
if (!$product && $id > 0) {
    try {
        $stmt = $pdo->prepare("
            SELECT p.*, c.name as category_name,
                   COALESCE(p.sale_price, p.price) as gia_hien_tai,
                   CASE 
                       WHEN p.sale_price IS NOT NULL AND p.sale_price < p.price 
                       THEN ROUND(((p.price - p.sale_price) / p.price) * 100, 0)
                       ELSE 0
                   END as phan_tram_giam,
                   p.name as ten_san_pham,
                   p.description as mo_ta_ngan,
                   p.description as mo_ta_chi_tiet,
                   p.price as gia_goc,
                   p.sale_price as gia_khuyen_mai,
                   p.main_image as hinh_anh_chinh,
                   p.gallery_images as album_hinh_anh,
                   p.brand as thuong_hieu,
                   p.category_id as danh_muc_id,
                   p.is_featured as san_pham_noi_bat,
                   0 as san_pham_moi,
                   0 as san_pham_ban_chay,
                   0 as luot_xem,
                   0 as so_luong_ban,
                   0 as diem_danh_gia_tb,
                   0 as so_luong_danh_gia,
                   c.name as ten_danh_muc,
                   c.slug as danh_muc_slug
            FROM products p
            LEFT JOIN categories c ON p.category_id = c.id
            WHERE p.id = ? AND p.status = 'active'
        ");
        $stmt->execute([$id]);
        $product = $stmt->fetch();
        
        // Nếu tìm thấy trong bảng products
        if ($product) {
            $product_table = 'products';
            
            // Cập nhật lượt xem (giả lập)
            $pdo->prepare("UPDATE products SET stock_quantity = stock_quantity WHERE id = ?")->execute([$id]);
            
            // Không có biến thể cho bảng products (có thể thêm sau)
            $variants = [];
            $sizes = [];
            $colors = [];
            $variant_matrix = [];
            
            // Giả lập reviews rỗng
            $reviews = [];
            $rating_stats = [];
            
            // Sản phẩm liên quan từ bảng products
            $stmt = $pdo->prepare("
                SELECT p.*, c.name as category_name,
                       p.name as ten_san_pham,
                       p.main_image as hinh_anh_chinh,
                       p.price as gia_goc,
                       p.sale_price as gia_khuyen_mai,
                       0 as diem_danh_gia_tb,
                       0 as so_luong_danh_gia,
                       p.stock_quantity as tong_ton_kho
                FROM products p
                LEFT JOIN categories c ON p.category_id = c.id
                WHERE p.category_id = ? AND p.id != ? AND p.status = 'active' AND p.stock_quantity > 0
                ORDER BY p.created_at DESC
                LIMIT 4
            ");
            $stmt->execute([$product['danh_muc_id'], $id]);
            $related_products = $stmt->fetchAll();
        }
    } catch (Exception $e) {
        error_log("Error querying products table: " . $e->getMessage());
    }
}

// Nếu vẫn không tìm thấy sản phẩm
if (!$product) {
    header('Location: products.php?error=product_not_found');
    exit;
}

// Xử lý AJAX thêm vào giỏ hàng
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['action']) && $_POST['action'] == 'add_to_cart') {
    // 🔧 FIX: Đảm bảo chỉ output JSON
    ob_clean(); // Xóa tất cả output trước đó
    header('Content-Type: application/json');
    
    if ($product_table == 'products') {
        // Cho bảng products - thêm giỏ hàng với bien_the_id = NULL
        $so_luong = max(1, (int)($_POST['so_luong'] ?? 1));
        
        if ($product['stock_quantity'] < $so_luong) {
            echo json_encode(['success' => false, 'message' => 'Không đủ hàng trong kho! Còn lại: ' . $product['stock_quantity']]);
            exit;
        }
        
        try {
            $customer_id = $_SESSION['customer_id'] ?? null;
            $session_id = $customer_id ? null : ($_SESSION['session_id'] ?? session_id());
            
            if (!$session_id && !$customer_id) {
                $_SESSION['session_id'] = session_id();
                $session_id = $_SESSION['session_id'];
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
                if ($new_quantity > $product['stock_quantity']) {
                    echo json_encode(['success' => false, 'message' => 'Không đủ hàng trong kho! Tối đa có thể mua: ' . $product['stock_quantity']]);
                    exit;
                }
                
                $pdo->prepare("UPDATE gio_hang SET so_luong = ?, gia_tai_thoi_diem = ? WHERE id = ?")
                    ->execute([$new_quantity, $product['gia_hien_tai'], $existing_item['id']]);
                
                $message = 'Cập nhật số lượng sản phẩm thành công!';
            } else {
                // Thêm mới với bien_the_id = NULL
                $pdo->prepare("
                    INSERT INTO gio_hang (khach_hang_id, session_id, san_pham_id, bien_the_id, so_luong, gia_tai_thoi_diem, ngay_them)
                    VALUES (?, ?, ?, NULL, ?, ?, NOW())
                ")->execute([$customer_id, $session_id, $product['id'], $so_luong, $product['gia_hien_tai']]);
                
                $message = 'Thêm sản phẩm vào giỏ hàng thành công!';
            }
            
            // Đếm tổng số sản phẩm trong giỏ hàng
            $count_stmt = $pdo->prepare("SELECT SUM(so_luong) FROM gio_hang WHERE (khach_hang_id = ? OR session_id = ?)");
            $count_stmt->execute([$customer_id, $session_id]);
            $cart_count = $count_stmt->fetchColumn() ?: 0;
            
            echo json_encode([
                'success' => true, 
                'message' => $message,
                'cart_count' => $cart_count
            ]);
            
        } catch (Exception $e) {
            echo json_encode(['success' => false, 'message' => 'Lỗi database: ' . $e->getMessage()]);
        }
    } else {
        // Logic cho san_pham_chinh với biến thể
        $kich_co = $_POST['kich_co'] ?? '';
        $mau_sac_id = (int)($_POST['mau_sac_id'] ?? 0);
        $so_luong = max(1, (int)($_POST['so_luong'] ?? 1));
        
        if (empty($kich_co) || $mau_sac_id == 0) {
            echo json_encode(['success' => false, 'message' => 'Vui lòng chọn size và màu sắc!']);
            exit;
        }
        
        // Tìm biến thể tương ứng
        $selected_variant = null;
        foreach ($variants as $variant) {
            if ($variant['kich_co'] == $kich_co && $variant['mau_sac_id'] == $mau_sac_id) {
                $selected_variant = $variant;
                break;
            }
        }
        
        if (!$selected_variant) {
            echo json_encode(['success' => false, 'message' => 'Biến thể sản phẩm không tồn tại!']);
            exit;
        }
        
        if ($selected_variant['so_luong_ton_kho'] < $so_luong) {
            echo json_encode(['success' => false, 'message' => 'Không đủ hàng trong kho! Còn lại: ' . $selected_variant['so_luong_ton_kho']]);
            exit;
        }
        
        try {
            $customer_id = $_SESSION['customer_id'] ?? null;
            $session_id = $customer_id ? null : ($_SESSION['session_id'] ?? session_id());
            
            if (!$session_id && !$customer_id) {
                $_SESSION['session_id'] = session_id();
                $session_id = $_SESSION['session_id'];
            }
            
            // Kiểm tra sản phẩm đã có trong giỏ hàng chưa
            $check_cart = $pdo->prepare("
                SELECT * FROM gio_hang 
                WHERE bien_the_id = ? 
                AND (khach_hang_id = ? OR session_id = ?)
            ");
            $check_cart->execute([$selected_variant['id'], $customer_id, $session_id]);
            $existing_item = $check_cart->fetch();
            
            if ($existing_item) {
                // Cập nhật số lượng
                $new_quantity = $existing_item['so_luong'] + $so_luong;
                if ($new_quantity > $selected_variant['so_luong_ton_kho']) {
                    echo json_encode(['success' => false, 'message' => 'Không đủ hàng trong kho! Tối đa có thể mua: ' . $selected_variant['so_luong_ton_kho']]);
                    exit;
                }
                
                $pdo->prepare("UPDATE gio_hang SET so_luong = ?, gia_tai_thoi_diem = ? WHERE id = ?")
                    ->execute([$new_quantity, $selected_variant['gia_ban'], $existing_item['id']]);
                
                $message = 'Cập nhật số lượng sản phẩm thành công!';
            } else {
                // 🔧 FIX FINAL: INSERT với đầy đủ san_pham_id và bien_the_id
                $pdo->prepare("
                    INSERT INTO gio_hang (khach_hang_id, session_id, san_pham_id, bien_the_id, so_luong, gia_tai_thoi_diem, ngay_them)
                    VALUES (?, ?, ?, ?, ?, ?, NOW())
                ")->execute([
                    $customer_id, 
                    $session_id, 
                    $product['id'],              // san_pham_id từ san_pham_chinh
                    $selected_variant['id'],     // bien_the_id từ bien_the_san_pham
                    $so_luong, 
                    $selected_variant['gia_ban']
                ]);
                
                $message = 'Thêm sản phẩm vào giỏ hàng thành công!';
            }
            
            // Đếm tổng số sản phẩm trong giỏ hàng
            $count_stmt = $pdo->prepare("SELECT SUM(so_luong) FROM gio_hang WHERE (khach_hang_id = ? OR session_id = ?)");
            $count_stmt->execute([$customer_id, $session_id]);
            $cart_count = $count_stmt->fetchColumn() ?: 0;
            
            echo json_encode([
                'success' => true, 
                'message' => $message,
                'cart_count' => $cart_count
            ]);
            
        } catch (Exception $e) {
            echo json_encode(['success' => false, 'message' => 'Có lỗi xảy ra: ' . $e->getMessage()]);
        }
    }
    exit;
}

// Album ảnh
$product_images = [];
if ($product_table == 'products') {
    // Xử lý gallery_images cho bảng products
    if ($product['gallery_images']) {
        $gallery = json_decode($product['gallery_images'], true);
        if (is_array($gallery)) {
            $product_images = array_filter($gallery);
        }
    }
    if ($product['hinh_anh_chinh']) {
        array_unshift($product_images, $product['hinh_anh_chinh']);
    }
} else {
    // Xử lý album_hinh_anh cho bảng san_pham_chinh
    if ($product['album_hinh_anh']) {
        $album = json_decode($product['album_hinh_anh'], true);
        if (is_array($album)) {
            foreach ($album as $img) {
                if (!empty($img) && $img !== 'default-product.jpg') {
                    $product_images[] = $img;
                }
            }
        }
    }
    if ($product['hinh_anh_chinh'] && $product['hinh_anh_chinh'] !== 'default-product.jpg') {
        array_unshift($product_images, $product['hinh_anh_chinh']);
    }
}

$product_images = array_unique($product_images);

$page_title = htmlspecialchars($product['ten_san_pham']) . ' - TKT Shop';

// Helper functions
function getContrastColor($hexColor) {
    $hexColor = ltrim($hexColor, '#');
    $r = hexdec(substr($hexColor, 0, 2));
    $g = hexdec(substr($hexColor, 2, 2));
    $b = hexdec(substr($hexColor, 4, 2));
    $luminance = (0.299 * $r + 0.587 * $g + 0.114 * $b) / 255;
    return $luminance > 0.5 ? '#000000' : '#ffffff';
}

function getImageUrl($imageName) {
    if (empty($imageName) || $imageName === 'default-product.jpg') {
        return null;
    }
    return "/tktshop/uploads/products/" . $imageName;
}

function formatPrice($price) {
    return number_format($price, 0, ',', '.') . '₫';
}
?>

<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= $page_title ?></title>
    <meta name="description" content="<?= htmlspecialchars($product['mo_ta_ngan'] ?? '') ?>">
    
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    
    <style>
        .product-gallery {
            position: sticky;
            top: 20px;
        }
        
        .main-image {
            width: 100%;
            height: 500px;
            object-fit: cover;
            border-radius: 10px;
            cursor: zoom-in;
        }
        
        .no-image-placeholder {
            width: 100%;
            height: 500px;
            border-radius: 10px;
            background: #f8f9fa;
            display: flex;
            align-items: center;
            justify-content: center;
            border: 2px dashed #dee2e6;
        }
        
        .thumbnail-images {
            display: flex;
            gap: 10px;
            margin-top: 15px;
            overflow-x: auto;
        }
        
        .thumbnail {
            width: 80px;
            height: 80px;
            object-fit: cover;
            border-radius: 8px;
            cursor: pointer;
            opacity: 0.7;
            transition: all 0.3s;
            flex-shrink: 0;
        }
        
        .thumbnail:hover,
        .thumbnail.active {
            opacity: 1;
            border: 2px solid #007bff;
        }
        
        .variant-option {
            border: 2px solid #dee2e6;
            border-radius: 8px;
            padding: 10px 15px;
            margin: 5px;
            cursor: pointer;
            transition: all 0.3s;
            display: inline-block;
        }
        
        .variant-option:hover,
        .variant-option.selected {
            border-color: #007bff;
            background-color: #f8f9ff;
        }
        
        .color-option {
            width: 40px;
            height: 40px;
            border-radius: 50%;
            border: 2px solid #ccc;
            cursor: pointer;
            margin: 5px;
            position: relative;
            transition: all 0.3s;
            display: inline-block;
        }
        
        .color-option:hover,
        .color-option.selected {
            border-color: #007bff;
            transform: scale(1.1);
        }
        
        .price-section {
            background: #f8f9fa;
            border-radius: 10px;
            padding: 20px;
            margin: 20px 0;
        }
        
        .quantity-input {
            max-width: 120px;
        }
    </style>
</head>
<body>
    <div class="container py-4">
        <!-- Breadcrumb -->
        <nav aria-label="breadcrumb" class="mb-4">
            <ol class="breadcrumb">
                <li class="breadcrumb-item"><a href="/tktshop/">Trang chủ</a></li>
                <li class="breadcrumb-item"><a href="/tktshop/customer/products.php">Sản phẩm</a></li>
                <li class="breadcrumb-item active"><?= htmlspecialchars($product['ten_san_pham']) ?></li>
            </ol>
        </nav>
        
        <div class="row">
            <!-- Product Gallery -->
            <div class="col-lg-6">
                <div class="product-gallery">
                    <div class="position-relative">
                        <?php if (!empty($product_images)): ?>
                            <img id="mainImage" 
                                 src="<?= getImageUrl($product_images[0]) ?: '/tktshop/uploads/products/no-image.jpg' ?>" 
                                 alt="<?= htmlspecialchars($product['ten_san_pham']) ?>"
                                 class="main-image"
                                 onerror="this.src='/tktshop/uploads/products/no-image.jpg'">
                        <?php else: ?>
                            <div class="no-image-placeholder">
                                <div class="text-center text-muted">
                                    <i class="fas fa-image fa-3x mb-3"></i>
                                    <p>Hình ảnh sản phẩm<br>đang được cập nhật</p>
                                </div>
                            </div>
                        <?php endif; ?>
                        
                        <?php if ($product['phan_tram_giam'] > 0): ?>
                            <span class="badge bg-danger position-absolute" style="top: 15px; right: 15px;">-<?= $product['phan_tram_giam'] ?>%</span>
                        <?php endif; ?>
                        
                        <?php if ($product['san_pham_noi_bat']): ?>
                            <span class="badge bg-warning text-dark position-absolute" style="top: 15px; left: 15px;">Hot</span>
                        <?php endif; ?>
                    </div>
                    
                    <?php if (count($product_images) > 1): ?>
                        <div class="thumbnail-images">
                            <?php foreach ($product_images as $index => $image): ?>
                                <img src="<?= getImageUrl($image) ?: '/tktshop/uploads/products/no-image.jpg' ?>" 
                                     alt="<?= htmlspecialchars($product['ten_san_pham']) ?> - Ảnh <?= $index + 1 ?>"
                                     class="thumbnail <?= $index === 0 ? 'active' : '' ?>"
                                     onclick="changeMainImage('<?= $image ?>', this)"
                                     onerror="this.style.display='none'">
                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
            
            <!-- Product Information -->
            <div class="col-lg-6">
                <div class="product-info">
                    <!-- Product Title -->
                    <div class="mb-3">
                        <?php if ($product['thuong_hieu']): ?>
                            <div class="text-muted mb-2">
                                <i class="fas fa-tag me-1"></i>
                                <?= htmlspecialchars($product['thuong_hieu']) ?>
                            </div>
                        <?php endif; ?>
                        <h1 class="h3"><?= htmlspecialchars($product['ten_san_pham']) ?></h1>
                    </div>
                    
                    <!-- Rating -->
                    <div class="d-flex align-items-center mb-3">
                        <div class="text-warning me-2">
                            <?php for ($i = 1; $i <= 5; $i++): ?>
                                <i class="fas fa-star<?= $i <= floor($product['diem_danh_gia_tb']) ? '' : ' text-muted' ?>"></i>
                            <?php endfor; ?>
                        </div>
                        <span class="me-2"><?= number_format($product['diem_danh_gia_tb'], 1) ?></span>
                        <span class="text-muted">
                            (<?= $product['so_luong_danh_gia'] ?> đánh giá)
                        </span>
                    </div>
                    
                    <!-- Price -->
                    <div class="price-section">
                        <div class="d-flex align-items-center gap-3 flex-wrap">
                            <?php if ($product['gia_khuyen_mai'] && $product['gia_khuyen_mai'] < $product['gia_goc']): ?>
                                <div class="h4 text-danger mb-0"><?= formatPrice($product['gia_khuyen_mai']) ?></div>
                                <div class="text-muted text-decoration-line-through"><?= formatPrice($product['gia_goc']) ?></div>
                                <div class="badge bg-danger">Tiết kiệm <?= formatPrice($product['gia_goc'] - $product['gia_khuyen_mai']) ?></div>
                            <?php else: ?>
                                <div class="h4 text-primary mb-0"><?= formatPrice($product['gia_goc']) ?></div>
                            <?php endif; ?>
                        </div>
                    </div>
                    
                    <!-- Short Description -->
                    <?php if ($product['mo_ta_ngan']): ?>
                        <div class="mb-4">
                            <p class="text-muted"><?= nl2br(htmlspecialchars($product['mo_ta_ngan'])) ?></p>
                        </div>
                    <?php endif; ?>
                    
                    <!-- Add to Cart Form -->
                    <form method="POST" id="addToCartForm">
                        <input type="hidden" name="action" value="add_to_cart">
                        
                        <?php if ($product_table == 'san_pham_chinh' && !empty($variants)): ?>
                            <!-- Variant Selection cho san_pham_chinh -->
                            <?php if (!empty($sizes)): ?>
                                <div class="mb-4">
                                    <label class="form-label fw-bold">Kích cỡ:</label>
                                    <div id="sizeOptions">
                                        <?php foreach ($sizes as $size): ?>
                                            <span class="variant-option size-option" data-size="<?= $size ?>">
                                                <?= htmlspecialchars($size) ?>
                                            </span>
                                        <?php endforeach; ?>
                                    </div>
                                    <input type="hidden" name="kich_co" id="selectedSize">
                                </div>
                            <?php endif; ?>
                            
                            <?php if (!empty($colors)): ?>
                                <div class="mb-4">
                                    <label class="form-label fw-bold">Màu sắc:</label>
                                    <div class="d-flex align-items-center gap-2 flex-wrap">
                                        <div id="colorOptions">
                                            <?php foreach ($colors as $color): ?>
                                                <div class="color-option" 
                                                     data-color-id="<?= $color['id'] ?>"
                                                     style="background-color: <?= $color['ma_mau'] ?>"
                                                     title="<?= htmlspecialchars($color['ten_mau']) ?>">
                                                </div>
                                            <?php endforeach; ?>
                                        </div>
                                        <span id="selectedColorName" class="ms-2 text-muted"></span>
                                    </div>
                                    <input type="hidden" name="mau_sac_id" id="selectedColorId">
                                </div>
                            <?php endif; ?>
                        <?php endif; ?>
                        
                        <!-- Quantity -->
                        <div class="mb-4">
                            <label class="form-label fw-bold">Số lượng:</label>
                            <div class="d-flex align-items-center gap-3">
                                <div class="input-group quantity-input">
                                    <button class="btn btn-outline-secondary" type="button" onclick="changeQuantity(-1)">-</button>
                                    <input type="number" class="form-control text-center" name="so_luong" id="quantity" value="1" min="1" max="<?= $product_table == 'products' ? $product['stock_quantity'] : 99 ?>">
                                    <button class="btn btn-outline-secondary" type="button" onclick="changeQuantity(1)">+</button>
                                </div>
                                <?php if ($product_table == 'products'): ?>
                                    <small class="text-muted">Còn lại: <?= $product['stock_quantity'] ?></small>
                                <?php endif; ?>
                            </div>
                        </div>
                        
                        <!-- Add to Cart Button -->
                        <div class="d-grid gap-2 d-md-flex">
                            <button type="button" name="add_to_cart" class="btn btn-primary btn-lg flex-grow-1" id="addToCartBtn" onclick="addToCartAjax()">
                                <i class="fas fa-shopping-cart me-2"></i>
                                Thêm vào giỏ hàng
                            </button>
                            <button type="button" class="btn btn-outline-danger btn-lg" onclick="addToWishlist(<?= $product['id'] ?>)">
                                <i class="fas fa-heart"></i>
                            </button>
                        </div>
                    </form>
                    
                    <!-- Product Info -->
                    <div class="mt-4">
                        <div class="row g-3">
                            <div class="col-6">
                                <div class="d-flex align-items-center text-muted">
                                    <i class="fas fa-truck me-2"></i>
                                    <small>Miễn phí vận chuyển</small>
                                </div>
                            </div>
                            <div class="col-6">
                                <div class="d-flex align-items-center text-muted">
                                    <i class="fas fa-undo me-2"></i>
                                    <small>Đổi trả 7 ngày</small>
                                </div>
                            </div>
                            <div class="col-6">
                                <div class="d-flex align-items-center text-muted">
                                    <i class="fas fa-shield-alt me-2"></i>
                                    <small>Bảo hành chính hãng</small>
                                </div>
                            </div>
                            <div class="col-6">
                                <div class="d-flex align-items-center text-muted">
                                    <i class="fas fa-headset me-2"></i>
                                    <small>Hỗ trợ 24/7</small>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        
        <!-- Product Details Tabs -->
        <div class="row mt-5">
            <div class="col-12">
                <ul class="nav nav-tabs" id="productTabs" role="tablist">
                    <li class="nav-item" role="presentation">
                        <button class="nav-link active" id="description-tab" data-bs-toggle="tab" data-bs-target="#description" type="button" role="tab">
                            Mô tả sản phẩm
                        </button>
                    </li>
                    <li class="nav-item" role="presentation">
                        <button class="nav-link" id="specifications-tab" data-bs-toggle="tab" data-bs-target="#specifications" type="button" role="tab">
                            Thông số kỹ thuật
                        </button>
                    </li>
                </ul>
                
                <div class="tab-content" id="productTabsContent">
                    <!-- Description Tab -->
                    <div class="tab-pane fade show active" id="description" role="tabpanel">
                        <div class="p-4">
                            <?php if ($product['mo_ta_chi_tiet']): ?>
                                <div class="product-description">
                                    <?= nl2br(htmlspecialchars($product['mo_ta_chi_tiet'])) ?>
                                </div>
                            <?php else: ?>
                                <p class="text-muted">Chưa có mô tả chi tiết cho sản phẩm này.</p>
                            <?php endif; ?>
                        </div>
                    </div>
                    
                    <!-- Specifications Tab -->
                    <div class="tab-pane fade" id="specifications" role="tabpanel">
                        <div class="p-4">
                            <div class="table-responsive">
                                <table class="table table-borderless">
                                    <?php if ($product['thuong_hieu']): ?>
                                        <tr>
                                            <td class="fw-bold" style="width: 200px;">Thương hiệu:</td>
                                            <td><?= htmlspecialchars($product['thuong_hieu']) ?></td>
                                        </tr>
                                    <?php endif; ?>
                                    <tr>
                                        <td class="fw-bold">Tên sản phẩm:</td>
                                        <td><?= htmlspecialchars($product['ten_san_pham']) ?></td>
                                    </tr>
                                    <tr>
                                        <td class="fw-bold">Danh mục:</td>
                                        <td><?= htmlspecialchars($product['ten_danh_muc'] ?? 'Chưa phân loại') ?></td>
                                    </tr>
                                    <?php if ($product_table == 'products'): ?>
                                        <tr>
                                            <td class="fw-bold">Tồn kho:</td>
                                            <td><?= $product['stock_quantity'] ?> sản phẩm</td>
                                        </tr>
                                    <?php else: ?>
                                        <?php if (!empty($sizes)): ?>
                                            <tr>
                                                <td class="fw-bold">Kích cỡ có sẵn:</td>
                                                <td><?= implode(', ', $sizes) ?></td>
                                            </tr>
                                        <?php endif; ?>
                                        <?php if (!empty($colors)): ?>
                                            <tr>
                                                <td class="fw-bold">Màu sắc có sẵn:</td>
                                                <td>
                                                    <?php foreach ($colors as $color): ?>
                                                        <span class="badge me-1" style="background-color: <?= $color['ma_mau'] ?>; color: <?= getContrastColor($color['ma_mau']) ?>;">
                                                            <?= htmlspecialchars($color['ten_mau']) ?>
                                                        </span>
                                                    <?php endforeach; ?>
                                                </td>
                                            </tr>
                                        <?php endif; ?>
                                    <?php endif; ?>
                                </table>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        
        <!-- Related Products -->
        <?php if (!empty($related_products)): ?>
            <div class="row mt-5">
                <div class="col-12">
                    <h3 class="mb-4">Sản phẩm liên quan</h3>
                    <div class="row">
                        <?php foreach ($related_products as $related): ?>
                            <div class="col-lg-3 col-md-4 col-sm-6 mb-4">
                                <div class="card h-100">
                                    <div class="position-relative">
                                        <img src="<?= !empty($related['hinh_anh_chinh']) ? '/tktshop/uploads/products/' . htmlspecialchars($related['hinh_anh_chinh']) : '/tktshop/uploads/products/no-image.jpg' ?>" 
                                             class="card-img-top" 
                                             alt="<?= htmlspecialchars($related['ten_san_pham']) ?>"
                                             style="height: 200px; object-fit: cover;"
                                             onerror="this.src='/tktshop/uploads/products/no-image.jpg'">
                                        
                                        <?php if ($related['gia_khuyen_mai'] && $related['gia_khuyen_mai'] < $related['gia_goc']): ?>
                                            <?php $discount = round((($related['gia_goc'] - $related['gia_khuyen_mai']) / $related['gia_goc']) * 100); ?>
                                            <span class="badge bg-danger position-absolute" style="top: 10px; right: 10px;">
                                                -<?= $discount ?>%
                                            </span>
                                        <?php endif; ?>
                                    </div>
                                    
                                    <div class="card-body d-flex flex-column">
                                        <h6 class="card-title">
                                            <?php if ($product_table == 'products'): ?>
                                                <a href="product_detail.php?id=<?= $related['id'] ?>" class="text-decoration-none text-dark">
                                                    <?= htmlspecialchars($related['ten_san_pham']) ?>
                                                </a>
                                            <?php else: ?>
                                                <a href="product_detail.php?slug=<?= $related['slug'] ?>" class="text-decoration-none text-dark">
                                                    <?= htmlspecialchars($related['ten_san_pham']) ?>
                                                </a>
                                            <?php endif; ?>
                                        </h6>
                                        
                                        <div class="mt-auto">
                                            <div class="d-flex align-items-center justify-content-between">
                                                <div>
                                                    <?php if ($related['gia_khuyen_mai'] && $related['gia_khuyen_mai'] < $related['gia_goc']): ?>
                                                        <div class="fw-bold text-danger"><?= formatPrice($related['gia_khuyen_mai']) ?></div>
                                                        <small class="text-muted text-decoration-line-through"><?= formatPrice($related['gia_goc']) ?></small>
                                                    <?php else: ?>
                                                        <div class="fw-bold text-primary"><?= formatPrice($related['gia_goc']) ?></div>
                                                    <?php endif; ?>
                                                </div>
                                                <div>
                                                    <?php if ($related['tong_ton_kho'] > 0): ?>
                                                        <small class="text-success">Còn hàng</small>
                                                    <?php else: ?>
                                                        <small class="text-danger">Hết hàng</small>
                                                    <?php endif; ?>
                                                </div>
                                            </div>
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
    
    <!-- Scripts -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
    
    <script>
        // Variables based on product table
        const productTable = '<?= $product_table ?>';
        
        <?php if ($product_table == 'san_pham_chinh' && !empty($variant_matrix)): ?>
        // Variant matrix for san_pham_chinh
        const variantMatrix = <?= json_encode($variant_matrix) ?>;
        const colors = <?= json_encode(array_values($colors)) ?>;
        
        let selectedSize = null;
        let selectedColorId = null;
        let currentVariant = null;
        
        // Size selection
        document.querySelectorAll('.size-option').forEach(option => {
            option.addEventListener('click', function() {
                const size = this.dataset.size;
                selectSize(size, this);
            });
        });
        
        function selectSize(size, element) {
            selectedSize = size;
            document.getElementById('selectedSize').value = size;
            
            // Update UI
            document.querySelectorAll('.size-option').forEach(opt => opt.classList.remove('selected'));
            element.classList.add('selected');
            
            updateAvailableColors();
            updateVariantInfo();
        }
        
        // Color selection
        document.querySelectorAll('.color-option').forEach(option => {
            option.addEventListener('click', function() {
                const colorId = parseInt(this.dataset.colorId);
                selectColor(colorId, this);
            });
        });
        
        function selectColor(colorId, element) {
            selectedColorId = colorId;
            document.getElementById('selectedColorId').value = colorId;
            
            // Update UI
            document.querySelectorAll('.color-option').forEach(opt => opt.classList.remove('selected'));
            element.classList.add('selected');
            
            // Show color name
            const color = colors.find(c => c.id == colorId);
            document.getElementById('selectedColorName').textContent = color ? color.ten_mau : '';
            
            updateAvailableSizes();
            updateVariantInfo();
        }
        
        function updateAvailableColors() {
            if (!selectedSize) return;
            
            document.querySelectorAll('.color-option').forEach(colorOption => {
                const colorId = parseInt(colorOption.dataset.colorId);
                const hasVariant = variantMatrix[selectedSize] && variantMatrix[selectedSize][colorId];
                
                if (hasVariant && variantMatrix[selectedSize][colorId].so_luong_ton_kho > 0) {
                    colorOption.classList.remove('disabled');
                } else {
                    colorOption.classList.add('disabled');
                }
            });
        }
        
        function updateAvailableSizes() {
            if (!selectedColorId) return;
            
            document.querySelectorAll('.size-option').forEach(sizeOption => {
                const size = sizeOption.dataset.size;
                const hasVariant = variantMatrix[size] && variantMatrix[size][selectedColorId];
                
                if (hasVariant && variantMatrix[size][selectedColorId].so_luong_ton_kho > 0) {
                    sizeOption.classList.remove('disabled');
                } else {
                    sizeOption.classList.add('disabled');
                }
            });
        }
        
        function updateVariantInfo() {
            const addToCartBtn = document.getElementById('addToCartBtn');
            const quantityInput = document.getElementById('quantity');
            
            if (selectedSize && selectedColorId) {
                currentVariant = variantMatrix[selectedSize] && variantMatrix[selectedSize][selectedColorId];
                
                if (currentVariant && currentVariant.so_luong_ton_kho > 0) {
                    addToCartBtn.disabled = false;
                    quantityInput.max = currentVariant.so_luong_ton_kho;
                } else {
                    addToCartBtn.disabled = true;
                }
            } else {
                addToCartBtn.disabled = true;
            }
        }
        <?php endif; ?>
        
        // Change main image
        function changeMainImage(imageSrc, thumbnail) {
            const mainImg = document.getElementById('mainImage');
            if (mainImg) {
                mainImg.src = '/tktshop/uploads/products/' + imageSrc;
                
                // Update thumbnail active state
                document.querySelectorAll('.thumbnail').forEach(t => t.classList.remove('active'));
                thumbnail.classList.add('active');
            }
        }
        
        // Quantity controls
        function changeQuantity(delta) {
            const quantityInput = document.getElementById('quantity');
            const currentValue = parseInt(quantityInput.value);
            const maxValue = parseInt(quantityInput.max) || 99;
            const newValue = Math.max(1, Math.min(maxValue, currentValue + delta));
            quantityInput.value = newValue;
        }
        
        // AJAX Add to Cart
        function addToCartAjax() {
            console.log('🛒 Adding to cart, product table:', productTable);
            
            <?php if ($product_table == 'san_pham_chinh'): ?>
            if (!selectedSize || !selectedColorId) {
                showToast('Vui lòng chọn size và màu sắc!', 'error');
                return;
            }
            
            if (!currentVariant || currentVariant.so_luong_ton_kho <= 0) {
                showToast('Sản phẩm đã hết hàng!', 'error');
                return;
            }
            <?php endif; ?>
            
            const quantity = parseInt(document.getElementById('quantity').value);
            
            // Show loading
            const btn = document.getElementById('addToCartBtn');
            const originalText = btn.innerHTML;
            btn.innerHTML = '<i class="fas fa-spinner fa-spin me-2"></i>Đang thêm...';
            btn.disabled = true;
            
            // Prepare data
            const formData = new FormData();
            formData.append('action', 'add_to_cart');
            formData.append('so_luong', quantity);
            
            <?php if ($product_table == 'san_pham_chinh'): ?>
            formData.append('kich_co', selectedSize);
            formData.append('mau_sac_id', selectedColorId);
            <?php endif; ?>
            
            // Send AJAX request
            fetch(window.location.href, {
                method: 'POST',
                body: formData
            })
            .then(response => response.json())
            .then(data => {
                console.log('Response:', data);
                
                if (data.success) {
                    showToast(data.message, 'success');
                    // Có thể cập nhật giỏ hàng counter ở đây
                } else {
                    showToast(data.message, 'error');
                }
            })
            .catch(error => {
                console.error('Error:', error);
                showToast('Có lỗi xảy ra: ' + error.message, 'error');
            })
            .finally(() => {
                btn.innerHTML = originalText;
                btn.disabled = false;
            });
        }
        
        // Show toast notification
        function showToast(message, type = 'info') {
            // Create toast container if it doesn't exist
            let toastContainer = document.getElementById('toastContainer');
            if (!toastContainer) {
                toastContainer = document.createElement('div');
                toastContainer.id = 'toastContainer';
                toastContainer.className = 'toast-container position-fixed top-0 end-0 p-3';
                toastContainer.style.zIndex = '9999';
                document.body.appendChild(toastContainer);
            }
            
            const toastId = 'toast-' + Date.now();
            const bgClass = type === 'success' ? 'bg-success' : type === 'error' ? 'bg-danger' : 'bg-info';
            const iconClass = type === 'success' ? 'fa-check-circle' : type === 'error' ? 'fa-exclamation-circle' : 'fa-info-circle';
            
            const toastHtml = `
                <div id="${toastId}" class="toast align-items-center text-white ${bgClass} border-0" role="alert">
                    <div class="d-flex">
                        <div class="toast-body">
                            <i class="fas ${iconClass} me-2"></i>
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
        
        // Wishlist function
        function addToWishlist(productId) {
            showToast('Tính năng yêu thích sẽ được cập nhật sớm!', 'info');
        }
    </script>
</body>
</html>