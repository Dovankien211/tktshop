<?php
/**
 * customer/product_detail_fixed.php - UNIFIED PRODUCT DETAIL PAGE
 * 🔧 FIXED: Tự động detect database schema và tương thích hoàn toàn
 */

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/product_detail_helper.php';

// 🔧 AUTO-DETECT DATABASE SCHEMA
$db_schema = detectDatabaseSchema($pdo);
$f = $db_schema['fields']; // Field mappings shorthand

// Get product by slug or ID
$product_slug = $_GET['slug'] ?? '';
$product_id = (int)($_GET['id'] ?? 0);

$product = null;
$variants = [];
$sizes = [];
$colors = [];
$related_products = [];
$reviews = [];

try {
    // 🔧 UNIFIED PRODUCT QUERY
    if (!empty($product_slug)) {
        // Get by slug
        $stmt = $pdo->prepare("
            SELECT p.*, c.{$f['category_name']} as category_name
            FROM {$db_schema['table']} p
            LEFT JOIN {$db_schema['category_table']} c ON p.{$f['category_id']} = c.id
            WHERE p.slug = ? AND p.{$f['status']} = '{$f['status_active']}'
        ");
        $stmt->execute([$product_slug]);
    } else {
        // Get by ID
        $stmt = $pdo->prepare("
            SELECT p.*, c.{$f['category_name']} as category_name
            FROM {$db_schema['table']} p
            LEFT JOIN {$db_schema['category_table']} c ON p.{$f['category_id']} = c.id
            WHERE p.id = ? AND p.{$f['status']} = '{$f['status_active']}'
        ");
        $stmt->execute([$product_id]);
    }
    
    $product = $stmt->fetch();
    
    if (!$product) {
        include '404.php';
        exit;
    }
    
    // Update view count
    $pdo->prepare("UPDATE {$db_schema['table']} SET {$f['view_count']} = {$f['view_count']} + 1 WHERE id = ?")
        ->execute([$product['id']]);
    
    // 🔧 UNIFIED VARIANTS, SIZES, COLORS QUERY
    if ($db_schema['has_variants']) {
        // Vietnamese schema with variants
        $stmt = $pdo->prepare("
            SELECT bsp.*, kc.kich_co, ms.ten_mau, ms.ma_mau,
                   kc.id as size_id, ms.id as color_id
            FROM bien_the_san_pham bsp
            JOIN kich_co kc ON bsp.kich_co_id = kc.id
            JOIN mau_sac ms ON bsp.mau_sac_id = ms.id
            WHERE bsp.san_pham_id = ? AND bsp.trang_thai = 'hoat_dong'
            ORDER BY kc.thu_tu_sap_xep ASC, ms.thu_tu_hien_thi ASC
        ");
        $stmt->execute([$product['id']]);
        $variants = $stmt->fetchAll();
        
        // Get unique sizes and colors
        $sizes = $pdo->prepare("
            SELECT DISTINCT kc.* 
            FROM kich_co kc
            JOIN bien_the_san_pham bsp ON kc.id = bsp.kich_co_id
            WHERE bsp.san_pham_id = ? AND bsp.trang_thai = 'hoat_dong'
            ORDER BY kc.thu_tu_sap_xep ASC
        ");
        $sizes->execute([$product['id']]);
        $sizes = $sizes->fetchAll();
        
        $colors = $pdo->prepare("
            SELECT DISTINCT ms.* 
            FROM mau_sac ms
            JOIN bien_the_san_pham bsp ON ms.id = bsp.mau_sac_id
            WHERE bsp.san_pham_id = ? AND bsp.trang_thai = 'hoat_dong'
            ORDER BY ms.thu_tu_hien_thi ASC
        ");
        $colors->execute([$product['id']]);
        $colors = $colors->fetchAll();
    } else {
        // English schema - create dummy variants data
        $variants = [
            [
                'id' => $product['id'],
                'gia_ban' => $product[$f['sale_price']] ?: $product[$f['price']],
                'so_luong_ton_kho' => $product['stock_quantity'] ?? 999,
                'ma_sku' => $product['sku'] ?? 'SKU-' . $product['id'],
                'kich_co' => 'Universal',
                'ten_mau' => 'Standard',
                'ma_mau' => '#000000',
                'size_id' => 0,
                'color_id' => 0
            ]
        ];
    }
    
    // Get related products
    $stmt = $pdo->prepare("
        SELECT p.*, 
               MIN(COALESCE(p.{$f['sale_price']}, p.{$f['price']})) as min_price
        FROM {$db_schema['table']} p
        WHERE p.{$f['category_id']} = ? 
        AND p.id != ? 
        AND p.{$f['status']} = '{$f['status_active']}'
        GROUP BY p.id
        ORDER BY p.{$f['view_count']} DESC
        LIMIT 4
    ");
    $stmt->execute([$product[$f['category_id']], $product['id']]);
    $related_products = $stmt->fetchAll();
    
    // Get reviews (if Vietnamese schema)
    if ($db_schema['has_variants']) {
        $stmt = $pdo->prepare("
            SELECT dg.*, nd.ho_ten, nd.avatar
            FROM danh_gia_san_pham dg
            JOIN nguoi_dung nd ON dg.khach_hang_id = nd.id
            WHERE dg.san_pham_id = ? AND dg.trang_thai = 'da_duyet'
            ORDER BY dg.ngay_tao DESC
            LIMIT 10
        ");
        $stmt->execute([$product['id']]);
        $reviews = $stmt->fetchAll();
    }
    
} catch (Exception $e) {
    error_log("Product detail error: " . $e->getMessage());
    include '404.php';
    exit;
}

// Calculate discount percentage
$discount_percent = 0;
if ($product[$f['sale_price']] && $product[$f['sale_price']] < $product[$f['price']]) {
    $discount_percent = round((($product[$f['price']] - $product[$f['sale_price']]) / $product[$f['price']]) * 100);
}

// Calculate stock status
$total_stock = 0;
if ($db_schema['has_variants']) {
    foreach ($variants as $variant) {
        $total_stock += $variant['so_luong_ton_kho'];
    }
} else {
    $total_stock = $product['stock_quantity'] ?? 999;
}

$page_title = htmlspecialchars($product[$f['name']]) . ' - ' . SITE_NAME;
?>

<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= $page_title ?></title>
    
    <!-- SEO Meta Tags -->
    <meta name="description" content="<?= htmlspecialchars($product[$f['description']] ?? '') ?>">
    <meta name="keywords" content="<?= htmlspecialchars($product[$f['brand']] ?? '') ?>, giày, <?= htmlspecialchars($product[$f['name']]) ?>">
    
    <!-- Open Graph -->
    <meta property="og:title" content="<?= htmlspecialchars($product[$f['name']]) ?>">
    <meta property="og:description" content="<?= htmlspecialchars($product[$f['description']] ?? '') ?>">
    <meta property="og:image" content="<?= getImageUrl($product[$f['image']]) ?>">
    <meta property="og:type" content="product">
    
    <!-- Styles -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/swiper@8/swiper-bundle.min.css" rel="stylesheet">
    
    <style>
        .product-gallery .swiper-slide {
            height: 400px;
        }
        
        .product-gallery img {
            width: 100%;
            height: 100%;
            object-fit: cover;
            border-radius: 10px;
            cursor: pointer;
        }
        
        .thumbnail-gallery .swiper-slide {
            height: 80px;
            opacity: 0.6;
            transition: opacity 0.3s;
        }
        
        .thumbnail-gallery .swiper-slide-thumb-active {
            opacity: 1;
        }
        
        .price-section {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            padding: 20px;
            border-radius: 15px;
            margin: 20px 0;
        }
        
        .variant-option {
            border: 2px solid #dee2e6;
            border-radius: 8px;
            padding: 10px 15px;
            margin: 5px;
            cursor: pointer;
            transition: all 0.3s;
            display: inline-block;
            min-width: 60px;
            text-align: center;
        }
        
        .variant-option:hover,
        .variant-option.selected {
            border-color: #007bff;
            background: #007bff;
            color: white;
        }
        
        .variant-option.disabled {
            background: #f8f9fa;
            color: #6c757d;
            cursor: not-allowed;
            opacity: 0.5;
        }
        
        .color-option {
            width: 40px;
            height: 40px;
            border: 3px solid #dee2e6;
            border-radius: 50%;
            cursor: pointer;
            margin: 5px;
            position: relative;
            transition: all 0.3s;
        }
        
        .color-option:hover,
        .color-option.selected {
            border-color: #007bff;
            transform: scale(1.1);
        }
        
        .color-option.disabled {
            opacity: 0.3;
            cursor: not-allowed;
        }
        
        .quantity-input {
            max-width: 120px;
        }
        
        .stock-info {
            font-size: 14px;
            margin-top: 10px;
        }
        
        .stock-high {
            color: #28a745;
        }
        
        .stock-low {
            color: #ffc107;
        }
        
        .stock-out {
            color: #dc3545;
        }
        
        .review-item {
            border-bottom: 1px solid #eee;
            padding: 20px 0;
        }
        
        .review-item:last-child {
            border-bottom: none;
        }
        
        .stars {
            color: #ffc107;
        }
        
        .debug-info {
            background: #e8f4fd;
            border: 1px solid #bee5eb;
            border-radius: 5px;
            padding: 10px;
            margin: 10px 0;
            font-size: 12px;
        }
        
        .zoom-container {
            position: relative;
            overflow: hidden;
            cursor: zoom-in;
        }
        
        .zoom-container:hover {
            cursor: zoom-out;
        }
        
        @media (max-width: 768px) {
            .product-gallery .swiper-slide {
                height: 300px;
            }
            
            .price-section {
                margin: 15px 0;
                padding: 15px;
            }
        }
    </style>
</head>
<body>
    <!-- Header -->
    <?php include 'includes/header.php'; ?>
    
    <div class="container py-4">
        <!-- 🔧 Debug Info -->
        <div class="debug-info">
            <strong>🔧 Product Detail System Info:</strong><br>
            Database Schema: <?= $db_schema['table'] ?> (<?= $db_schema['has_variants'] ? 'with variants' : 'simple' ?>)<br>
            Product ID: <?= $product['id'] ?><br>
            Variants found: <?= count($variants) ?><br>
            Total stock: <?= $total_stock ?><br>
            Discount: <?= $discount_percent ?>%
        </div>

        <!-- Breadcrumb -->
        <nav aria-label="breadcrumb" class="mb-4">
            <ol class="breadcrumb">
                <li class="breadcrumb-item"><a href="/">Trang chủ</a></li>
                <li class="breadcrumb-item"><a href="products.php">Sản phẩm</a></li>
                <?php if (isset($product['category_name'])): ?>
                    <li class="breadcrumb-item">
                        <a href="products.php?category=<?= $product[$f['category_id']] ?>">
                            <?= htmlspecialchars($product['category_name']) ?>
                        </a>
                    </li>
                <?php endif; ?>
                <li class="breadcrumb-item active"><?= htmlspecialchars($product[$f['name']]) ?></li>
            </ol>
        </nav>
        
        <div class="row">
            <!-- Product Images -->
            <div class="col-lg-6">
                <div class="product-gallery">
                    <!-- Main Gallery -->
                    <div class="swiper product-gallery-main mb-3">
                        <div class="swiper-wrapper">
                            <!-- Main Image -->
                            <div class="swiper-slide">
                                <div class="zoom-container">
                                    <img src="<?= getImageUrl($product[$f['image']]) ?>" 
                                         alt="<?= htmlspecialchars($product[$f['name']]) ?>"
                                         class="img-fluid"
                                         onerror="this.src='/tktshop/uploads/products/no-image.jpg'">
                                </div>
                            </div>
                            
                            <!-- Gallery Images -->
                            <?php 
                            $gallery_images = [];
                            if ($db_schema['has_variants'] && isset($product['album_hinh_anh'])) {
                                $gallery_images = json_decode($product['album_hinh_anh'], true) ?: [];
                            } elseif (isset($product['gallery_images'])) {
                                $gallery_images = json_decode($product['gallery_images'], true) ?: [];
                            }
                            
                            foreach ($gallery_images as $image): 
                            ?>
                                <div class="swiper-slide">
                                    <div class="zoom-container">
                                        <img src="/tktshop/uploads/products/<?= htmlspecialchars($image) ?>" 
                                             alt="<?= htmlspecialchars($product[$f['name']]) ?>"
                                             class="img-fluid"
                                             onerror="this.src='/tktshop/uploads/products/no-image.jpg'">
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                        
                        <!-- Navigation -->
                        <div class="swiper-button-next"></div>
                        <div class="swiper-button-prev"></div>
                    </div>
                    
                    <!-- Thumbnail Gallery -->
                    <?php if (!empty($gallery_images)): ?>
                    <div class="swiper thumbnail-gallery">
                        <div class="swiper-wrapper">
                            <div class="swiper-slide">
                                <img src="<?= getImageUrl($product[$f['image']]) ?>" 
                                     alt="<?= htmlspecialchars($product[$f['name']]) ?>"
                                     class="img-fluid">
                            </div>
                            <?php foreach ($gallery_images as $image): ?>
                                <div class="swiper-slide">
                                    <img src="/tktshop/uploads/products/<?= htmlspecialchars($image) ?>" 
                                         alt="<?= htmlspecialchars($product[$f['name']]) ?>"
                                         class="img-fluid">
                                </div>
                            <?php endforeach; ?>
                        </div>
                    </div>
                    <?php endif; ?>
                </div>
                
                <!-- Product Features -->
                <div class="mt-4">
                    <div class="row text-center">
                        <div class="col-4">
                            <i class="fas fa-shipping-fast fa-2x text-primary mb-2"></i>
                            <div class="small">Giao hàng nhanh</div>
                        </div>
                        <div class="col-4">
                            <i class="fas fa-undo fa-2x text-success mb-2"></i>
                            <div class="small">Đổi trả 7 ngày</div>
                        </div>
                        <div class="col-4">
                            <i class="fas fa-shield-alt fa-2x text-info mb-2"></i>
                            <div class="small">Bảo hành chính hãng</div>
                        </div>
                    </div>
                </div>
            </div>
            
            <!-- Product Info -->
            <div class="col-lg-6">
                <div class="product-info">
                    <!-- Product Title -->
                    <h1 class="h3 mb-3"><?= htmlspecialchars($product[$f['name']]) ?></h1>
                    
                    <!-- Brand -->
                    <?php if ($product[$f['brand']]): ?>
                        <div class="mb-3">
                            <span class="badge bg-primary">
                                <i class="fas fa-tag me-1"></i>
                                <?= htmlspecialchars($product[$f['brand']]) ?>
                            </span>
                        </div>
                    <?php endif; ?>
                    
                    <!-- Ratings -->
                    <?php if ($product[$f['rating']] > 0): ?>
                        <div class="rating mb-3">
                            <div class="stars">
                                <?= displayStars(round($product[$f['rating']])) ?>
                            </div>
                            <span class="text-muted ms-2">
                                (<?= $product[$f['rating_count']] ?> đánh giá)
                            </span>
                        </div>
                    <?php endif; ?>
                    
                    <!-- Price Section -->
                    <div class="price-section">
                        <div class="row align-items-center">
                            <div class="col">
                                <?php if ($discount_percent > 0): ?>
                                    <div class="h4 mb-1 text-white">
                                        <?= formatPrice($product[$f['sale_price']]) ?>
                                        <span class="badge bg-danger ms-2">-<?= $discount_percent ?>%</span>
                                    </div>
                                    <div class="text-decoration-line-through text-light">
                                        Giá gốc: <?= formatPrice($product[$f['price']]) ?>
                                    </div>
                                    <div class="small text-light">
                                        Tiết kiệm: <?= formatPrice($product[$f['price']] - $product[$f['sale_price']]) ?>
                                    </div>
                                <?php else: ?>
                                    <div class="h4 mb-0 text-white">
                                        <?= formatPrice($product[$f['price']]) ?>
                                    </div>
                                <?php endif; ?>
                            </div>
                            <div class="col-auto">
                                <div class="text-end">
                                    <?php if ($total_stock > 20): ?>
                                        <div class="badge bg-success">Còn hàng</div>
                                    <?php elseif ($total_stock > 0): ?>
                                        <div class="badge bg-warning">Sắp hết</div>
                                    <?php else: ?>
                                        <div class="badge bg-danger">Hết hàng</div>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </div>
                    </div>
                    
                    <!-- Add to Cart Form -->
                    <form id="addToCartForm" class="mt-4">
                        <input type="hidden" id="productId" value="<?= $product['id'] ?>">
                        <input type="hidden" id="selectedVariantId" value="">
                        
                        <!-- Size Selection -->
                        <?php if (!empty($sizes)): ?>
                            <div class="mb-4">
                                <label class="form-label fw-bold">Kích cỡ:</label>
                                <div class="size-options">
                                    <?php foreach ($sizes as $size): ?>
                                        <span class="variant-option size-option" 
                                              data-size-id="<?= $size['id'] ?>"
                                              data-size-name="<?= htmlspecialchars($size['kich_co']) ?>">
                                            <?= htmlspecialchars($size['kich_co']) ?>
                                        </span>
                                    <?php endforeach; ?>
                                </div>
                                <div class="form-text">Vui lòng chọn kích cỡ</div>
                            </div>
                        <?php endif; ?>
                        
                        <!-- Color Selection -->
                        <?php if (!empty($colors)): ?>
                            <div class="mb-4">
                                <label class="form-label fw-bold">Màu sắc:</label>
                                <div class="color-options d-flex flex-wrap">
                                    <?php foreach ($colors as $color): ?>
                                        <div class="color-option" 
                                             data-color-id="<?= $color['id'] ?>"
                                             data-color-name="<?= htmlspecialchars($color['ten_mau']) ?>"
                                             style="background-color: <?= htmlspecialchars($color['ma_mau']) ?>"
                                             title="<?= htmlspecialchars($color['ten_mau']) ?>">
                                        </div>
                                    <?php endforeach; ?>
                                </div>
                                <div class="form-text">Chọn màu sắc yêu thích</div>
                            </div>
                        <?php endif; ?>
                        
                        <!-- Quantity and Stock Info -->
                        <div class="row mb-4">
                            <div class="col-md-6">
                                <label class="form-label fw-bold">Số lượng:</label>
                                <div class="input-group quantity-input">
                                    <button type="button" class="btn btn-outline-secondary" onclick="changeQuantity(-1)">-</button>
                                    <input type="number" id="quantity" class="form-control text-center" value="1" min="1" max="999">
                                    <button type="button" class="btn btn-outline-secondary" onclick="changeQuantity(1)">+</button>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="stock-info">
                                    <div id="stockStatus" class="stock-high">
                                        <i class="fas fa-check-circle me-1"></i>
                                        Còn <?= $total_stock ?> sản phẩm
                                    </div>
                                    <div id="selectedVariantInfo" class="mt-2 small text-muted">
                                        Chọn size và màu để xem chi tiết
                                    </div>
                                </div>
                            </div>
                        </div>
                        
                        <!-- Action Buttons -->
                        <div class="d-grid gap-2 d-md-flex">
                            <button type="submit" class="btn btn-primary btn-lg flex-fill me-md-2" id="addToCartBtn">
                                <i class="fas fa-shopping-cart me-2"></i>
                                Thêm vào giỏ hàng
                            </button>
                            <button type="button" class="btn btn-success btn-lg" onclick="buyNow()">
                                <i class="fas fa-bolt me-2"></i>
                                Mua ngay
                            </button>
                        </div>
                    </form>
                    
                    <!-- Product Description -->
                    <?php if ($product[$f['description']]): ?>
                        <div class="mt-4">
                            <h5>Mô tả sản phẩm</h5>
                            <div class="text-muted">
                                <?= nl2br(htmlspecialchars($product[$f['description']])) ?>
                            </div>
                        </div>
                    <?php endif; ?>
                    
                    <!-- Share Buttons -->
                    <div class="mt-4">
                        <h6>Chia sẻ:</h6>
                        <div class="d-flex gap-2">
                            <a href="#" class="btn btn-outline-primary btn-sm share-facebook">
                                <i class="fab fa-facebook-f me-1"></i>Facebook
                            </a>
                            <a href="#" class="btn btn-outline-info btn-sm share-twitter">
                                <i class="fab fa-twitter me-1"></i>Twitter
                            </a>
                            <a href="#" class="btn btn-outline-success btn-sm share-whatsapp">
                                <i class="fab fa-whatsapp me-1"></i>WhatsApp
                            </a>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        
        <!-- Product Details Tabs -->
        <div class="mt-5">
            <ul class="nav nav-tabs" id="productTabs" role="tablist">
                <li class="nav-item" role="presentation">
                    <button class="nav-link active" id="description-tab" data-bs-toggle="tab" data-bs-target="#description" type="button" role="tab">
                        Chi tiết sản phẩm
                    </button>
                </li>
                <li class="nav-item" role="presentation">
                    <button class="nav-link" id="specifications-tab" data-bs-toggle="tab" data-bs-target="#specifications" type="button" role="tab">
                        Thông số kỹ thuật
                    </button>
                </li>
                <?php if (!empty($reviews)): ?>
                <li class="nav-item" role="presentation">
                    <button class="nav-link" id="reviews-tab" data-bs-toggle="tab" data-bs-target="#reviews" type="button" role="tab">
                        Đánh giá (<?= count($reviews) ?>)
                    </button>
                </li>
                <?php endif; ?>
            </ul>
            
            <div class="tab-content" id="productTabsContent">
                <!-- Description Tab -->
                <div class="tab-pane fade show active p-4" id="description" role="tabpanel">
                    <?php if ($db_schema['has_variants'] && isset($product['mo_ta_chi_tiet'])): ?>
                        <div><?= nl2br(htmlspecialchars($product['mo_ta_chi_tiet'])) ?></div>
                    <?php else: ?>
                        <div><?= nl2br(htmlspecialchars($product[$f['description']] ?? 'Thông tin chi tiết sản phẩm sẽ được cập nhật sớm.')) ?></div>
                    <?php endif; ?>
                </div>
                
                <!-- Specifications Tab -->
                <div class="tab-pane fade p-4" id="specifications" role="tabpanel">
                    <div class="row">
                        <div class="col-md-6">
                            <table class="table table-borderless">
                                <tr>
                                    <td class="fw-bold">Thương hiệu:</td>
                                    <td><?= htmlspecialchars($product[$f['brand']] ?? 'Đang cập nhật') ?></td>
                                </tr>
                                <tr>
                                    <td class="fw-bold">Danh mục:</td>
                                    <td><?= htmlspecialchars($product['category_name'] ?? 'Đang cập nhật') ?></td>
                                </tr>
                                <?php if (!empty($sizes)): ?>
                                <tr>
                                    <td class="fw-bold">Kích cỡ có sẵn:</td>
                                    <td>
                                        <?php 
                                        $size_list = array_map(function($s) { return $s['kich_co']; }, $sizes);
                                        echo htmlspecialchars(implode(', ', $size_list));
                                        ?>
                                    </td>
                                </tr>
                                <?php endif; ?>
                                <?php if (!empty($colors)): ?>
                                <tr>
                                    <td class="fw-bold">Màu sắc:</td>
                                    <td>
                                        <?php 
                                        $color_list = array_map(function($c) { return $c['ten_mau']; }, $colors);
                                        echo htmlspecialchars(implode(', ', $color_list));
                                        ?>
                                    </td>
                                </tr>
                                <?php endif; ?>
                            </table>
                        </div>
                        <div class="col-md-6">
                            <table class="table table-borderless">
                                <tr>
                                    <td class="fw-bold">Tình trạng:</td>
                                    <td><?= $total_stock > 0 ? '<span class="text-success">Còn hàng</span>' : '<span class="text-danger">Hết hàng</span>' ?></td>
                                </tr>
                                <tr>
                                    <td class="fw-bold">Lượt xem:</td>
                                    <td><?= number_format($product[$f['view_count']]) ?></td>
                                </tr>
                                <?php if ($product[$f['rating']] > 0): ?>
                                <tr>
                                    <td class="fw-bold">Đánh giá:</td>
                                    <td><?= $product[$f['rating']] ?>/5 sao (<?= $product[$f['rating_count']] ?> lượt)</td>
                                </tr>
                                <?php endif; ?>
                            </table>
                        </div>
                    </div>
                </div>
                
                <!-- Reviews Tab -->
                <?php if (!empty($reviews)): ?>
                <div class="tab-pane fade p-4" id="reviews" role="tabpanel">
                    <div class="reviews-section">
                        <?php foreach ($reviews as $review): ?>
                        <div class="review-item">
                            <div class="d-flex">
                                <div class="me-3">
                                    <?php if ($review['avatar']): ?>
                                        <img src="/tktshop/uploads/users/<?= htmlspecialchars($review['avatar']) ?>" 
                                             alt="Avatar" class="rounded-circle" width="50" height="50">
                                    <?php else: ?>
                                        <div class="bg-primary text-white rounded-circle d-flex align-items-center justify-content-center" style="width: 50px; height: 50px;">
                                            <?= strtoupper(substr($review['ho_ten'], 0, 1)) ?>
                                        </div>
                                    <?php endif; ?>
                                </div>
                                <div class="flex-grow-1">
                                    <div class="d-flex justify-content-between align-items-start">
                                        <div>
                                            <h6 class="mb-1"><?= htmlspecialchars($review['ho_ten']) ?></h6>
                                            <div class="stars mb-2">
                                                <?= displayStars($review['diem_danh_gia']) ?>
                                            </div>
                                        </div>
                                        <small class="text-muted"><?= formatDate($review['ngay_tao']) ?></small>
                                    </div>
                                    
                                    <?php if ($review['tieu_de']): ?>
                                        <h6 class="fw-bold"><?= htmlspecialchars($review['tieu_de']) ?></h6>
                                    <?php endif; ?>
                                    
                                    <?php if ($review['noi_dung']): ?>
                                        <p class="mb-2"><?= nl2br(htmlspecialchars($review['noi_dung'])) ?></p>
                                    <?php endif; ?>
                                    
                                    <?php if ($review['uu_diem']): ?>
                                        <div class="mb-2">
                                            <strong class="text-success">Ưu điểm:</strong>
                                            <?= nl2br(htmlspecialchars($review['uu_diem'])) ?>
                                        </div>
                                    <?php endif; ?>
                                    
                                    <?php if ($review['nhuoc_diem']): ?>
                                        <div class="mb-2">
                                            <strong class="text-warning">Nhược điểm:</strong>
                                            <?= nl2br(htmlspecialchars($review['nhuoc_diem'])) ?>
                                        </div>
                                    <?php endif; ?>
                                    
                                    <?php if ($review['la_mua_hang_xac_thuc']): ?>
                                        <span class="badge bg-success small">
                                            <i class="fas fa-check-circle me-1"></i>Đã mua hàng
                                        </span>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </div>
                        <?php endforeach; ?>
                    </div>
                </div>
                <?php endif; ?>
            </div>
        </div>
        
        <!-- Related Products -->
        <?php if (!empty($related_products)): ?>
        <div class="mt-5">
            <h3 class="mb-4">Sản phẩm liên quan</h3>
            <div class="row">
                <?php foreach ($related_products as $related): ?>
                <div class="col-lg-3 col-md-4 col-sm-6 mb-4">
                    <div class="card h-100 product-card">
                        <div class="position-relative">
                            <a href="<?= getProductUrl($related, $db_schema) ?>">
                                <img src="<?= getImageUrl($related[$f['image']]) ?>" 
                                     class="card-img-top" 
                                     alt="<?= htmlspecialchars($related[$f['name']]) ?>"
                                     style="height: 200px; object-fit: cover;"
                                     onerror="this.src='/tktshop/uploads/products/no-image.jpg'">
                            </a>
                            <?php if ($related[$f['featured']]): ?>
                                <span class="badge bg-success position-absolute" style="top: 10px; left: 10px;">Nổi bật</span>
                            <?php endif; ?>
                        </div>
                        <div class="card-body d-flex flex-column">
                            <h6 class="card-title">
                                <a href="<?= getProductUrl($related, $db_schema) ?>" class="text-decoration-none text-dark">
                                    <?= htmlspecialchars($related[$f['name']]) ?>
                                </a>
                            </h6>
                            <?php if ($related[$f['brand']]): ?>
                                <small class="text-muted mb-2"><?= htmlspecialchars($related[$f['brand']]) ?></small>
                            <?php endif; ?>
                            <div class="mt-auto">
                                <div class="h6 text-primary"><?= formatPrice($related['min_price']) ?></div>
                            </div>
                        </div>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>
        </div>
        <?php endif; ?>
    </div>
    
    <!-- Toast Container -->
    <div class="toast-container position-fixed top-0 end-0 p-3" id="toastContainer"></div>
    
    <!-- Footer -->
    <?php include 'includes/footer.php'; ?>
    
    <!-- Scripts -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/swiper@8/swiper-bundle.min.js"></script>
    
    <script>
        // 🔧 UNIFIED PRODUCT DETAIL SCRIPT
        
        // Product variants data
        const variants = <?= json_encode($variants) ?>;
        const hasVariants = <?= $db_schema['has_variants'] ? 'true' : 'false' ?>;
        let selectedSize = null;
        let selectedColor = null;
        let selectedVariant = null;
        
        // Initialize Swiper galleries
        let mainGallery, thumbGallery;
        
        // Initialize page
        document.addEventListener('DOMContentLoaded', function() {
            initializeGalleries();
            initializeVariantSelection();
            updateAddToCartButton();
            
            console.log('🔧 Product Detail initialized');
            console.log('Has variants:', hasVariants);
            console.log('Variants:', variants);
        });
        
        // Initialize Swiper galleries
        function initializeGalleries() {
            // Thumbnail gallery
            thumbGallery = new Swiper('.thumbnail-gallery', {
                spaceBetween: 10,
                slidesPerView: 4,
                watchSlidesProgress: true,
                breakpoints: {
                    768: {
                        slidesPerView: 6,
                    }
                }
            });
            
            // Main gallery
            mainGallery = new Swiper('.product-gallery-main', {
                spaceBetween: 10,
                navigation: {
                    nextEl: '.swiper-button-next',
                    prevEl: '.swiper-button-prev',
                },
                thumbs: {
                    swiper: thumbGallery,
                },
                zoom: {
                    maxRatio: 3,
                }
            });
        }
        
        // Initialize variant selection
        function initializeVariantSelection() {
            if (!hasVariants) {
                // For simple products, set default variant
                selectedVariant = variants[0];
                document.getElementById('selectedVariantId').value = selectedVariant.id;
                updateStockInfo();
                return;
            }
            
            // Size selection
            document.querySelectorAll('.size-option').forEach(option => {
                option.addEventListener('click', function() {
                    if (this.classList.contains('disabled')) return;
                    
                    // Update selection
                    document.querySelectorAll('.size-option').forEach(o => o.classList.remove('selected'));
                    this.classList.add('selected');
                    
                    selectedSize = {
                        id: parseInt(this.dataset.sizeId),
                        name: this.dataset.sizeName
                    };
                    
                    updateColorOptions();
                    updateVariant();
                });
            });
            
            // Color selection
            document.querySelectorAll('.color-option').forEach(option => {
                option.addEventListener('click', function() {
                    if (this.classList.contains('disabled')) return;
                    
                    // Update selection
                    document.querySelectorAll('.color-option').forEach(o => o.classList.remove('selected'));
                    this.classList.add('selected');
                    
                    selectedColor = {
                        id: parseInt(this.dataset.colorId),
                        name: this.dataset.colorName
                    };
                    
                    updateSizeOptions();
                    updateVariant();
                });
            });
        }
        
        // Update available colors based on selected size
        function updateColorOptions() {
            if (!selectedSize) return;
            
            const availableColors = variants
                .filter(v => v.size_id == selectedSize.id && v.so_luong_ton_kho > 0)
                .map(v => v.color_id);
            
            document.querySelectorAll('.color-option').forEach(option => {
                const colorId = parseInt(option.dataset.colorId);
                if (availableColors.includes(colorId)) {
                    option.classList.remove('disabled');
                } else {
                    option.classList.add('disabled');
                    if (option.classList.contains('selected')) {
                        option.classList.remove('selected');
                        selectedColor = null;
                    }
                }
            });
        }
        
        // Update available sizes based on selected color
        function updateSizeOptions() {
            if (!selectedColor) return;
            
            const availableSizes = variants
                .filter(v => v.color_id == selectedColor.id && v.so_luong_ton_kho > 0)
                .map(v => v.size_id);
            
            document.querySelectorAll('.size-option').forEach(option => {
                const sizeId = parseInt(option.dataset.sizeId);
                if (availableSizes.includes(sizeId)) {
                    option.classList.remove('disabled');
                } else {
                    option.classList.add('disabled');
                    if (option.classList.contains('selected')) {
                        option.classList.remove('selected');
                        selectedSize = null;
                    }
                }
            });
        }
        
        // Update selected variant
        function updateVariant() {
            selectedVariant = null;
            
            if (hasVariants) {
                if (selectedSize && selectedColor) {
                    selectedVariant = variants.find(v => 
                        v.size_id == selectedSize.id && v.color_id == selectedColor.id
                    );
                }
            } else {
                selectedVariant = variants[0];
            }
            
            if (selectedVariant) {
                document.getElementById('selectedVariantId').value = selectedVariant.id;
                updateStockInfo();
                updateQuantityLimits();
            }
            
            updateAddToCartButton();
        }
        
        // Update stock information
        function updateStockInfo() {
            const stockStatus = document.getElementById('stockStatus');
            const variantInfo = document.getElementById('selectedVariantInfo');
            
            if (!selectedVariant) {
                stockStatus.innerHTML = '<i class="fas fa-exclamation-triangle text-warning me-1"></i>Chọn size và màu';
                stockStatus.className = 'stock-info text-warning';
                variantInfo.innerHTML = 'Chọn size và màu để xem chi tiết';
                return;
            }
            
            const stock = selectedVariant.so_luong_ton_kho;
            
            if (stock > 20) {
                stockStatus.innerHTML = `<i class="fas fa-check-circle me-1"></i>Còn ${stock} sản phẩm`;
                stockStatus.className = 'stock-info stock-high';
            } else if (stock > 0) {
                stockStatus.innerHTML = `<i class="fas fa-exclamation-triangle me-1"></i>Chỉ còn ${stock} sản phẩm`;
                stockStatus.className = 'stock-info stock-low';
            } else {
                stockStatus.innerHTML = '<i class="fas fa-times-circle me-1"></i>Hết hàng';
                stockStatus.className = 'stock-info stock-out';
            }
            
            // Update variant info
            if (hasVariants) {
                variantInfo.innerHTML = `
                    SKU: ${selectedVariant.ma_sku}<br>
                    Size: ${selectedVariant.kich_co} | Màu: ${selectedVariant.ten_mau}<br>
                    Giá: ${formatPrice(selectedVariant.gia_ban)}
                `;
            } else {
                variantInfo.innerHTML = `SKU: ${selectedVariant.ma_sku}`;
            }
        }
        
        // Update quantity input limits
        function updateQuantityLimits() {
            const quantityInput = document.getElementById('quantity');
            
            if (selectedVariant) {
                quantityInput.max = selectedVariant.so_luong_ton_kho;
                if (parseInt(quantityInput.value) > selectedVariant.so_luong_ton_kho) {
                    quantityInput.value = selectedVariant.so_luong_ton_kho;
                }
            }
        }
        
        // Update add to cart button state
        function updateAddToCartButton() {
            const addToCartBtn = document.getElementById('addToCartBtn');
            
            if (!selectedVariant || selectedVariant.so_luong_ton_kho <= 0) {
                addToCartBtn.disabled = true;
                addToCartBtn.innerHTML = '<i class="fas fa-times me-2"></i>Không thể mua';
            } else if (hasVariants && (!selectedSize || !selectedColor)) {
                addToCartBtn.disabled = true;
                addToCartBtn.innerHTML = '<i class="fas fa-exclamation-triangle me-2"></i>Chọn size và màu';
            } else {
                addToCartBtn.disabled = false;
                addToCartBtn.innerHTML = '<i class="fas fa-shopping-cart me-2"></i>Thêm vào giỏ hàng';
            }
        }
        
        // Quantity change
        function changeQuantity(delta) {
            const quantityInput = document.getElementById('quantity');
            const currentValue = parseInt(quantityInput.value);
            const newValue = currentValue + delta;
            const maxValue = selectedVariant ? selectedVariant.so_luong_ton_kho : 999;
            
            if (newValue >= 1 && newValue <= maxValue) {
                quantityInput.value = newValue;
            }
        }
        
        // Add to cart form submission
        document.getElementById('addToCartForm').addEventListener('submit', function(e) {
            e.preventDefault();
            
            if (!selectedVariant) {
                showToast('Vui lòng chọn phiên bản sản phẩm', 'error');
                return;
            }
            
            if (hasVariants && (!selectedSize || !selectedColor)) {
                showToast('Vui lòng chọn đầy đủ size và màu', 'error');
                return;
            }
            
            const quantity = parseInt(document.getElementById('quantity').value);
            
            if (quantity > selectedVariant.so_luong_ton_kho) {
                showToast(`Chỉ còn ${selectedVariant.so_luong_ton_kho} sản phẩm trong kho`, 'error');
                return;
            }
            
            // Add to cart
            addToCart({
                variant_id: selectedVariant.id,
                product_id: document.getElementById('productId').value,
                quantity: quantity,
                size_id: selectedSize ? selectedSize.id : 0,
                color_id: selectedColor ? selectedColor.id : 0
            });
        });
        
        // Add to cart function
        function addToCart(data) {
            const addToCartBtn = document.getElementById('addToCartBtn');
            const originalText = addToCartBtn.innerHTML;
            
            // Show loading
            addToCartBtn.disabled = true;
            addToCartBtn.innerHTML = '<i class="fas fa-spinner fa-spin me-2"></i>Đang thêm...';
            
            fetch('add_to_cart.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                },
                body: JSON.stringify(data)
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    showToast('Đã thêm sản phẩm vào giỏ hàng!', 'success');
                    
                    // Update cart count in header
                    if (typeof updateCartCount === 'function') {
                        updateCartCount(data.data.cart_count);
                    }
                    
                    // Update stock after adding
                    selectedVariant.so_luong_ton_kho -= parseInt(document.getElementById('quantity').value);
                    updateStockInfo();
                    updateQuantityLimits();
                } else {
                    showToast(data.message, 'error');
                }
            })
            .catch(error => {
                console.error('Add to cart error:', error);
                showToast('Có lỗi xảy ra khi thêm sản phẩm', 'error');
            })
            .finally(() => {
                // Restore button
                addToCartBtn.disabled = false;
                addToCartBtn.innerHTML = originalText;
                updateAddToCartButton();
            });
        }
        
        // Buy now function
        function buyNow() {
            if (!selectedVariant) {
                showToast('Vui lòng chọn phiên bản sản phẩm', 'error');
                return;
            }
            
            if (hasVariants && (!selectedSize || !selectedColor)) {
                showToast('Vui lòng chọn đầy đủ size và màu', 'error');
                return;
            }
            
            // Add to cart first, then redirect to checkout
            const data = {
                variant_id: selectedVariant.id,
                product_id: document.getElementById('productId').value,
                quantity: parseInt(document.getElementById('quantity').value),
                size_id: selectedSize ? selectedSize.id : 0,
                color_id: selectedColor ? selectedColor.id : 0
            };
            
            addToCart(data);
            
            // Redirect to checkout after a short delay
            setTimeout(() => {
                window.location.href = 'checkout.php';
            }, 1000);
        }
        
        // Format price function
        function formatPrice(amount) {
            return new Intl.NumberFormat('vi-VN').format(amount) + '₫';
        }
        
        // Show toast notification
        function showToast(message, type = 'info') {
            const toastContainer = document.getElementById('toastContainer');
            const toastId = 'toast-' + Date.now();
            
            const toast = document.createElement('div');
            toast.id = toastId;
            toast.className = `toast align-items-center text-white bg-${type === 'error' ? 'danger' : type === 'success' ? 'success' : 'info'} border-0`;
            toast.setAttribute('role', 'alert');
            toast.innerHTML = `
                <div class="d-flex">
                    <div class="toast-body">
                        <i class="fas fa-${type === 'error' ? 'exclamation-circle' : type === 'success' ? 'check-circle' : 'info-circle'} me-2"></i>
                        ${message}
                    </div>
                    <button type="button" class="btn-close btn-close-white me-2 m-auto" data-bs-dismiss="toast"></button>
                </div>
            `;
            
            toastContainer.appendChild(toast);
            
            const bsToast = new bootstrap.Toast(toast);
            bsToast.show();
            
            // Remove after hiding
            toast.addEventListener('hidden.bs.toast', () => {
                toast.remove();
            });
        }
        
        // Share functions
        document.querySelector('.share-facebook')?.addEventListener('click', function(e) {
            e.preventDefault();
            const url = encodeURIComponent(window.location.href);
            window.open(`https://www.facebook.com/sharer/sharer.php?u=${url}`, '_blank', 'width=600,height=400');
        });
        
        document.querySelector('.share-twitter')?.addEventListener('click', function(e) {
            e.preventDefault();
            const url = encodeURIComponent(window.location.href);
            const text = encodeURIComponent(document.title);
            window.open(`https://twitter.com/intent/tweet?url=${url}&text=${text}`, '_blank', 'width=600,height=400');
        });
        
        document.querySelector('.share-whatsapp')?.addEventListener('click', function(e) {
            e.preventDefault();
            const url = encodeURIComponent(window.location.href);
            const text = encodeURIComponent(document.title);
            window.open(`https://wa.me/?text=${text} ${url}`, '_blank');
        });
        
        // Image zoom functionality
        document.querySelectorAll('.zoom-container img').forEach(img => {
            img.addEventListener('click', function() {
                // Simple lightbox functionality
                const modal = document.createElement('div');
                modal.style.cssText = `
                    position: fixed;
                    top: 0;
                    left: 0;
                    width: 100%;
                    height: 100%;
                    background: rgba(0,0,0,0.8);
                    z-index: 9999;
                    display: flex;
                    align-items: center;
                    justify-content: center;
                    cursor: pointer;
                `;
                
                const zoomedImg = this.cloneNode();
                zoomedImg.style.cssText = 'max-width: 90%; max-height: 90%; object-fit: contain;';
                
                modal.appendChild(zoomedImg);
                document.body.appendChild(modal);
                
                modal.addEventListener('click', () => {
                    document.body.removeChild(modal);
                });
            });
        });
        
        console.log('🔧 TKT Shop Product Detail - Unified System loaded successfully');
    </script>
</body>
</html>