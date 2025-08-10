<?php
// customer/product_detail.php - SMART FIXED VERSION
/**
 * ✅ FIXED: Tự động phát hiện tên cột trong database
 * Không cần biết bảng dùng tiếng Anh hay tiếng Việt
 */
session_start();

require_once '../config/database.php';
require_once '../config/config.php';

// ========================================
// ✅ SMART FIX: XỬ LÝ AJAX VỚI AUTO-DETECT COLUMNS
// ========================================
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['action']) && $_POST['action'] == 'add_to_cart') {
    header('Content-Type: application/json');
    
    try {
        // Lấy thông tin sản phẩm
        $id = (int)($_POST['product_id'] ?? $_GET['id'] ?? 0);
        
        if ($id <= 0) {
            echo json_encode(['success' => false, 'message' => 'ID sản phẩm không hợp lệ!']);
            exit;
        }
        
        // Lấy thông tin sản phẩm
        $stmt = $pdo->prepare("SELECT * FROM products WHERE id = ? AND status = 'active'");
        $stmt->execute([$id]);
        $product_for_cart = $stmt->fetch();
        
        if (!$product_for_cart) {
            echo json_encode(['success' => false, 'message' => 'Không tìm thấy sản phẩm!']);
            exit;
        }
        
        $so_luong = max(1, (int)($_POST['so_luong'] ?? 1));
        
        if ($product_for_cart['stock_quantity'] < $so_luong) {
            echo json_encode(['success' => false, 'message' => 'Không đủ hàng trong kho! Còn lại: ' . $product_for_cart['stock_quantity']]);
            exit;
        }
        
        // ✅ SMART: Tự động phát hiện cấu trúc bảng gio_hang
        $cart_columns = [];
        try {
            $columns_result = $pdo->query("SHOW COLUMNS FROM gio_hang")->fetchAll(PDO::FETCH_COLUMN);
            foreach ($columns_result as $col) {
                $cart_columns[] = $col;
            }
        } catch (Exception $e) {
            // Nếu bảng gio_hang không tồn tại, tạo bảng mới
            $create_cart_table = "
                CREATE TABLE IF NOT EXISTS `gio_hang` (
                  `id` int(11) NOT NULL AUTO_INCREMENT,
                  `customer_id` int(11) DEFAULT NULL,
                  `session_id` varchar(255) DEFAULT NULL,
                  `product_id` int(11) NOT NULL,
                  `variant_id` int(11) DEFAULT NULL,
                  `quantity` int(11) NOT NULL DEFAULT 1,
                  `price` decimal(10,2) NOT NULL,
                  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
                  PRIMARY KEY (`id`),
                  KEY `customer_id` (`customer_id`),
                  KEY `product_id` (`product_id`),
                  KEY `session_id` (`session_id`)
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
            ";
            $pdo->exec($create_cart_table);
            
            // Lấy lại cấu trúc sau khi tạo
            $columns_result = $pdo->query("SHOW COLUMNS FROM gio_hang")->fetchAll(PDO::FETCH_COLUMN);
            foreach ($columns_result as $col) {
                $cart_columns[] = $col;
            }
        }
        
        // ✅ SMART: Xác định tên cột (tiếng Anh hoặc tiếng Việt)
        $customer_col = in_array('customer_id', $cart_columns) ? 'customer_id' : 'khach_hang_id';
        $product_col = in_array('product_id', $cart_columns) ? 'product_id' : 'san_pham_id';
        $quantity_col = in_array('quantity', $cart_columns) ? 'quantity' : 'so_luong';
        $price_col = in_array('price', $cart_columns) ? 'price' : 'gia_tai_thoi_diem';
        $variant_col = in_array('variant_id', $cart_columns) ? 'variant_id' : 'bien_the_id';
        $created_col = in_array('created_at', $cart_columns) ? 'created_at' : 'ngay_them';
        
        // Xử lý session
        $customer_id = $_SESSION['customer_id'] ?? null;
        $session_id = $customer_id ? null : ($_SESSION['session_id'] ?? session_id());
        
        if (!$session_id && !$customer_id) {
            $_SESSION['session_id'] = session_id();
            $session_id = $_SESSION['session_id'];
        }
        
        $current_price = $product_for_cart['sale_price'] ?: $product_for_cart['price'];
        
        // ✅ SMART: Kiểm tra sản phẩm đã có trong giỏ hàng chưa
        $check_sql = "SELECT * FROM gio_hang WHERE {$product_col} = ? AND ({$customer_col} = ? OR session_id = ?)";
        if (in_array($variant_col, $cart_columns)) {
            $check_sql .= " AND {$variant_col} IS NULL";
        }
        
        $check_cart = $pdo->prepare($check_sql);
        $check_cart->execute([$id, $customer_id, $session_id]);
        $existing_item = $check_cart->fetch();
        
        if ($existing_item) {
            // Cập nhật số lượng
            $new_quantity = $existing_item[$quantity_col] + $so_luong;
            if ($new_quantity > $product_for_cart['stock_quantity']) {
                echo json_encode(['success' => false, 'message' => 'Không đủ hàng trong kho!']);
                exit;
            }
            
            $update_sql = "UPDATE gio_hang SET {$quantity_col} = ?, {$price_col} = ? WHERE id = ?";
            $pdo->prepare($update_sql)->execute([$new_quantity, $current_price, $existing_item['id']]);
            
        } else {
            // Thêm mới vào giỏ hàng
            $insert_cols = [$customer_col, 'session_id', $product_col, $quantity_col, $price_col];
            $insert_values = ['?', '?', '?', '?', '?'];
            $insert_data = [$customer_id, $session_id, $id, $so_luong, $current_price];
            
            // Thêm cột created_at/ngay_them nếu có
            if (in_array($created_col, $cart_columns)) {
                $insert_cols[] = $created_col;
                $insert_values[] = 'NOW()';
            }
            
            $insert_sql = "INSERT INTO gio_hang (" . implode(', ', $insert_cols) . ") VALUES (" . implode(', ', $insert_values) . ")";
            $pdo->prepare($insert_sql)->execute($insert_data);
        }
        
        echo json_encode(['success' => true, 'message' => 'Thêm sản phẩm vào giỏ hàng thành công!']);
        
    } catch (Exception $e) {
        error_log("Cart Error: " . $e->getMessage());
        echo json_encode(['success' => false, 'message' => 'Có lỗi xảy ra: ' . $e->getMessage()]);
    }
    exit;
}

// ========================================
// PHẦN CODE CHÍNH - HIỂN THỊ SẢN PHẨM
// ========================================

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

// Kiểm tra bảng products trước
if ($id > 0) {
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
        
        if ($product) {
            $product_table = 'products';
            
            // Sản phẩm liên quan
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

// Nếu không tìm thấy
if (!$product) {
    header('Location: products.php?error=product_not_found');
    exit;
}

// Xử lý ảnh sản phẩm
$product_images = [];
if ($product['gallery_images']) {
    $gallery = json_decode($product['gallery_images'], true);
    if (is_array($gallery)) {
        $product_images = array_filter($gallery);
    }
}
if ($product['hinh_anh_chinh']) {
    array_unshift($product_images, $product['hinh_anh_chinh']);
}
$product_images = array_unique($product_images);

$page_title = htmlspecialchars($product['ten_san_pham']) . ' - TKT Shop';

// Helper functions
function getImageUrl($imageName) {
    if (empty($imageName) || $imageName === 'default-product.jpg') {
        return '/tktshop/uploads/products/no-image.jpg';
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
        
        .price-section {
            background: #f8f9fa;
            border-radius: 10px;
            padding: 20px;
            margin: 20px 0;
        }
        
        .quantity-input {
            max-width: 120px;
        }
        
        .debug-info {
            background: #e3f2fd;
            border: 1px solid #2196f3;
            border-radius: 5px;
            padding: 10px;
            margin: 10px 0;
            font-size: 12px;
        }
    </style>
</head>
<body>
    <div class="container py-4">
        <!-- Debug Info -->
        <div class="debug-info">
            <strong>🔍 DEBUG:</strong> 
            ID: <?= $product['id'] ?> | 
            Tên: <?= htmlspecialchars($product['ten_san_pham']) ?> |
            Stock: <?= $product['stock_quantity'] ?> |
            Giá: <?= formatPrice($product['gia_hien_tai']) ?>
            <?php
            // Hiển thị cấu trúc bảng gio_hang
            try {
                $cart_cols = $pdo->query("SHOW COLUMNS FROM gio_hang")->fetchAll(PDO::FETCH_COLUMN);
                echo " | Cart Columns: " . implode(', ', $cart_cols);
            } catch (Exception $e) {
                echo " | Cart Table: " . $e->getMessage();
            }
            ?>
        </div>

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
                                 src="<?= getImageUrl($product_images[0]) ?>" 
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
                                <img src="<?= getImageUrl($image) ?>" 
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
                        <input type="hidden" name="product_id" value="<?= $product['id'] ?>">
                        
                        <!-- Quantity -->
                        <div class="mb-4">
                            <label class="form-label fw-bold">Số lượng:</label>
                            <div class="d-flex align-items-center gap-3">
                                <div class="input-group quantity-input">
                                    <button class="btn btn-outline-secondary" type="button" onclick="changeQuantity(-1)">-</button>
                                    <input type="number" class="form-control text-center" name="so_luong" id="quantity" value="1" min="1" max="<?= $product['stock_quantity'] ?>">
                                    <button class="btn btn-outline-secondary" type="button" onclick="changeQuantity(1)">+</button>
                                </div>
                                <small class="text-muted">Còn lại: <?= $product['stock_quantity'] ?></small>
                            </div>
                        </div>
                        
                        <!-- Add to Cart Button -->
                        <div class="d-grid gap-2 d-md-flex">
                            <button type="button" class="btn btn-primary btn-lg flex-grow-1" id="addToCartBtn" onclick="addToCartAjax()">
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
                                    <tr>
                                        <td class="fw-bold">Tồn kho:</td>
                                        <td><?= $product['stock_quantity'] ?> sản phẩm</td>
                                    </tr>
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
                                        <img src="<?= getImageUrl($related['hinh_anh_chinh']) ?>" 
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
                                            <a href="product_detail.php?id=<?= $related['id'] ?>" class="text-decoration-none text-dark">
                                                <?= htmlspecialchars($related['ten_san_pham']) ?>
                                            </a>
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
        
        // ✅ SMART AJAX Add to Cart
        function addToCartAjax() {
            console.log('🛒 Adding to cart...');
            
            const quantity = parseInt(document.getElementById('quantity').value);
            const maxStock = parseInt(document.getElementById('quantity').max);
            
            if (quantity > maxStock) {
                showToast('Số lượng vượt quá tồn kho!', 'error');
                return;
            }
            
            // Show loading
            const btn = document.getElementById('addToCartBtn');
            const originalText = btn.innerHTML;
            btn.innerHTML = '<i class="fas fa-spinner fa-spin me-2"></i>Đang thêm...';
            btn.disabled = true;
            
            // Prepare data
            const formData = new FormData();
            formData.append('action', 'add_to_cart');
            formData.append('product_id', '<?= $product['id'] ?>');
            formData.append('so_luong', quantity);
            
            // Send AJAX request
            fetch(window.location.href, {
                method: 'POST',
                body: formData
            })
            .then(response => {
                console.log('Response status:', response.status);
                console.log('Response headers:', response.headers.get('content-type'));
                
                // Check if response is JSON
                const contentType = response.headers.get('content-type');
                if (contentType && contentType.includes('application/json')) {
                    return response.json();
                } else {
                    // If not JSON, get text to debug
                    return response.text().then(text => {
                        console.error('❌ Expected JSON but got HTML/Text:');
                        console.error(text.substring(0, 500) + '...');
                        
                        // Try to extract error message from HTML
                        if (text.includes('SQLSTATE') || text.includes('Error:')) {
                            const errorMatch = text.match(/Error:.*?(?=<|$)/i);
                            const sqlMatch = text.match(/SQLSTATE\[.*?\]:.*?(?=<|$)/i);
                            const message = sqlMatch ? sqlMatch[0] : (errorMatch ? errorMatch[0] : 'Lỗi server không xác định');
                            throw new Error(message);
                        } else {
                            throw new Error('Server trả về dữ liệu không hợp lệ');
                        }
                    });
                }
            })
            .then(data => {
                console.log('✅ Success response:', data);
                
                if (data && data.success) {
                    showToast(data.message || 'Thêm sản phẩm thành công!', 'success');
                    
                    // Optional: Update cart counter in header
                    // updateCartCounter();
                } else {
                    showToast(data.message || 'Có lỗi xảy ra!', 'error');
                }
            })
            .catch(error => {
                console.error('❌ Request failed:', error);
                showToast('Lỗi: ' + error.message, 'error');
            })
            .finally(() => {
                // Restore button
                btn.innerHTML = originalText;
                btn.disabled = false;
            });
        }
        
        // Show toast notification
        function showToast(message, type = 'info') {
            // Remove existing toasts
            const existingToasts = document.querySelectorAll('.toast');
            existingToasts.forEach(toast => toast.remove());
            
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
                <div id="${toastId}" class="toast align-items-center text-white ${bgClass} border-0" role="alert" aria-live="assertive" aria-atomic="true">
                    <div class="d-flex">
                        <div class="toast-body">
                            <i class="fas ${iconClass} me-2"></i>
                            ${message}
                        </div>
                        <button type="button" class="btn-close btn-close-white me-2 m-auto" data-bs-dismiss="toast" aria-label="Close"></button>
                    </div>
                </div>
            `;
            
            toastContainer.insertAdjacentHTML('beforeend', toastHtml);
            
            // Show toast
            const toastElement = document.getElementById(toastId);
            const toast = new bootstrap.Toast(toastElement, {
                autohide: true,
                delay: 5000
            });
            toast.show();
            
            // Remove toast element after it hides
            toastElement.addEventListener('hidden.bs.toast', function() {
                this.remove();
            });
        }
        
        // Wishlist function
        function addToWishlist(productId) {
            showToast('Tính năng yêu thích sẽ được cập nhật sớm!', 'info');
        }
        
        // Initialize page
        document.addEventListener('DOMContentLoaded', function() {
            console.log('✅ Product detail page loaded successfully');
            console.log('Product ID: <?= $product['id'] ?>');
            console.log('Product name: <?= htmlspecialchars($product['ten_san_pham']) ?>');
        });
    </script>
</body>
</html>