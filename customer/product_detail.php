<?php
/**
 * customer/product_detail.php - UNIVERSAL PRODUCT HANDLER
 * 🔧 FIXED: Tự động detect schema và handle cả ID + SLUG từ mọi nguồn
 */

session_start();

require_once '../config/database.php';
require_once '../config/config.php';

// 🔧 COMPLETE: Database schema detection function
function detectDatabaseSchema($pdo) {
    $schema = [
        'table' => null,
        'fields' => [],
        'category_table' => null,
        'has_variants' => false
    ];
    
    try {
        // Check if san_pham_chinh exists (Vietnamese schema)
        $stmt = $pdo->query("SHOW TABLES LIKE 'san_pham_chinh'");
        if ($stmt->rowCount() > 0) {
            $schema['table'] = 'san_pham_chinh';
            $schema['category_table'] = 'danh_muc_giay';
            $schema['has_variants'] = true;
            
            $schema['fields'] = [
                'id' => 'id',
                'name' => 'ten_san_pham',
                'slug' => 'slug',
                'description' => 'mo_ta_ngan',
                'long_description' => 'mo_ta_chi_tiet',
                'price' => 'gia_goc',
                'sale_price' => 'gia_khuyen_mai',
                'brand' => 'thuong_hieu',
                'category_id' => 'danh_muc_id',
                'image' => 'hinh_anh_chinh',
                'gallery' => 'album_hinh_anh',
                'status' => 'trang_thai',
                'status_active' => 'hoat_dong',
                'featured' => 'san_pham_noi_bat',
                'view_count' => 'luot_xem',
                'rating' => 'diem_danh_gia_tb',
                'rating_count' => 'so_luong_danh_gia',
                'created_at' => 'ngay_tao',
                'category_name' => 'ten_danh_muc',
                'category_slug' => 'slug'
            ];
        } else {
            // Fallback to products table (English schema)
            $schema['table'] = 'products';
            $schema['category_table'] = 'categories';
            $schema['has_variants'] = false;
            
            $schema['fields'] = [
                'id' => 'id',
                'name' => 'name',
                'slug' => 'slug',
                'description' => 'short_description',
                'long_description' => 'description',
                'price' => 'price',
                'sale_price' => 'sale_price',
                'brand' => 'brand',
                'category_id' => 'category_id',
                'image' => 'main_image',
                'gallery' => 'gallery_images',
                'status' => 'status',
                'status_active' => 'active',
                'featured' => 'is_featured',
                'view_count' => 'view_count',
                'rating' => 'rating_average',
                'rating_count' => 'rating_count',
                'created_at' => 'created_at',
                'category_name' => 'name',
                'category_slug' => 'slug',
                'stock_quantity' => 'stock_quantity'
            ];
        }
        
        // Add missing slug column if needed
        try {
            $stmt = $pdo->query("SHOW COLUMNS FROM {$schema['table']} LIKE 'slug'");
            if ($stmt->rowCount() == 0) {
                $pdo->exec("ALTER TABLE {$schema['table']} ADD COLUMN slug VARCHAR(255) NULL AFTER {$schema['fields']['name']}");
                
                // Generate slugs for existing records
                $stmt = $pdo->query("SELECT id, {$schema['fields']['name']} FROM {$schema['table']} WHERE slug IS NULL OR slug = ''");
                $update_stmt = $pdo->prepare("UPDATE {$schema['table']} SET slug = ? WHERE id = ?");
                
                while ($row = $stmt->fetch()) {
                    $slug = createSlug($row[$schema['fields']['name']]);
                    $update_stmt->execute([$slug, $row['id']]);
                }
            }
        } catch (Exception $e) {
            error_log("Could not add/update slug column: " . $e->getMessage());
        }
        
    } catch (Exception $e) {
        error_log("Database schema detection failed: " . $e->getMessage());
        // Default fallback
        $schema['table'] = 'products';
        $schema['category_table'] = 'categories';
    }
    
    return $schema;
}

// Detect schema
$db_schema = detectDatabaseSchema($pdo);
$f = $db_schema['fields'];

// Get parameters từ URL - flexible handling
$id = (int)($_GET['id'] ?? 0);
$slug = trim($_GET['slug'] ?? '');

// 🔧 FLEXIBLE PRODUCT LOOKUP
$product = null;
$variants = [];
$reviews = [];
$related_products = [];

// Priority: slug first, then id, then fallback
if (!empty($slug)) {
    // Try by slug first
    try {
        $stmt = $pdo->prepare("
            SELECT p.*, c.{$f['category_name']}, c.{$f['category_slug']} as category_slug,
                   COALESCE(p.{$f['sale_price']}, p.{$f['price']}) as current_price,
                   CASE 
                       WHEN p.{$f['sale_price']} IS NOT NULL AND p.{$f['sale_price']} < p.{$f['price']} 
                       THEN ROUND(((p.{$f['price']} - p.{$f['sale_price']}) / p.{$f['price']}) * 100, 0)
                       ELSE 0
                   END as discount_percent
            FROM {$db_schema['table']} p
            LEFT JOIN {$db_schema['category_table']} c ON p.{$f['category_id']} = c.id
            WHERE p.{$f['slug']} = ? AND p.{$f['status']} = ?
        ");
        $stmt->execute([$slug, $f['status_active']]);
        $product = $stmt->fetch();
        
        if ($product) {
            $id = $product['id']; // Set ID for further processing
        }
    } catch (Exception $e) {
        error_log("Slug lookup failed: " . $e->getMessage());
    }
}

// If not found by slug, try by ID
if (!$product && $id > 0) {
    try {
        $stmt = $pdo->prepare("
            SELECT p.*, c.{$f['category_name']}, c.{$f['category_slug']} as category_slug,
                   COALESCE(p.{$f['sale_price']}, p.{$f['price']}) as current_price,
                   CASE 
                       WHEN p.{$f['sale_price']} IS NOT NULL AND p.{$f['sale_price']} < p.{$f['price']} 
                       THEN ROUND(((p.{$f['price']} - p.{$f['sale_price']}) / p.{$f['price']}) * 100, 0)
                       ELSE 0
                   END as discount_percent
            FROM {$db_schema['table']} p
            LEFT JOIN {$db_schema['category_table']} c ON p.{$f['category_id']} = c.id
            WHERE p.id = ? AND p.{$f['status']} = ?
        ");
        $stmt->execute([$id, $f['status_active']]);
        $product = $stmt->fetch();
    } catch (Exception $e) {
        error_log("ID lookup failed: " . $e->getMessage());
    }
}

// If still no product found, redirect with error
if (!$product) {
    header('Location: products.php?error=product_not_found');
    exit;
}

// 🔧 UPDATE VIEW COUNT
try {
    $pdo->prepare("UPDATE {$db_schema['table']} SET {$f['view_count']} = {$f['view_count']} + 1 WHERE id = ?")
        ->execute([$product['id']]);
} catch (Exception $e) {
    error_log("View count update failed: " . $e->getMessage());
}

// 🔧 GET PRODUCT VARIANTS (if applicable)
$sizes = [];
$colors = [];
$variant_matrix = [];

if ($db_schema['has_variants']) {
    try {
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
        
        // Build variant matrix
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
    } catch (Exception $e) {
        error_log("Variants lookup failed: " . $e->getMessage());
    }
}

// 🔧 GET RELATED PRODUCTS
try {
    if ($db_schema['has_variants']) {
        $stmt = $pdo->prepare("
            SELECT p.*, 
                   COALESCE(p.{$f['sale_price']}, p.{$f['price']}) as current_price,
                   MIN(bsp.gia_ban) as min_price,
                   SUM(bsp.so_luong_ton_kho) as total_stock
            FROM {$db_schema['table']} p
            LEFT JOIN bien_the_san_pham bsp ON p.id = bsp.san_pham_id AND bsp.trang_thai = 'hoat_dong'
            WHERE p.{$f['category_id']} = ? AND p.id != ? AND p.{$f['status']} = ?
            GROUP BY p.id
            HAVING total_stock > 0
            ORDER BY p.{$f['view_count']} DESC
            LIMIT 4
        ");
        $stmt->execute([$product[$f['category_id']], $product['id'], $f['status_active']]);
    } else {
        $stmt = $pdo->prepare("
            SELECT p.*, c.{$f['category_name']},
                   COALESCE(p.{$f['sale_price']}, p.{$f['price']}) as current_price
            FROM {$db_schema['table']} p
            LEFT JOIN {$db_schema['category_table']} c ON p.{$f['category_id']} = c.id
            WHERE p.{$f['category_id']} = ? AND p.id != ? AND p.{$f['status']} = ?
            AND p.{$f['stock_quantity']} > 0
            ORDER BY p.{$f['view_count']} DESC
            LIMIT 4
        ");
        $stmt->execute([$product[$f['category_id']], $product['id'], $f['status_active']]);
    }
    $related_products = $stmt->fetchAll();
} catch (Exception $e) {
    error_log("Related products lookup failed: " . $e->getMessage());
}

// 🔧 PROCESS PRODUCT IMAGES
$product_images = [];
if ($product[$f['gallery']]) {
    $gallery = json_decode($product[$f['gallery']], true);
    if (is_array($gallery)) {
        $product_images = array_filter($gallery, function($img) {
            return !empty($img) && $img !== 'default-product.jpg';
        });
    }
}
if ($product[$f['image']] && $product[$f['image']] !== 'default-product.jpg') {
    array_unshift($product_images, $product[$f['image']]);
}
$product_images = array_unique($product_images);

// 🔧 HANDLE ADD TO CART AJAX
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['action']) && $_POST['action'] == 'add_to_cart') {
    ob_clean();
    header('Content-Type: application/json');
    
    $customer_id = $_SESSION['customer_id'] ?? null;
    $session_id = $customer_id ? null : ($_SESSION['session_id'] ?? session_id());
    
    if (!$session_id && !$customer_id) {
        $_SESSION['session_id'] = session_id();
        $session_id = $_SESSION['session_id'];
    }
    
    try {
        if ($db_schema['has_variants']) {
            // Handle variants
            $kich_co = $_POST['kich_co'] ?? '';
            $mau_sac_id = (int)($_POST['mau_sac_id'] ?? 0);
            $so_luong = max(1, (int)($_POST['so_luong'] ?? 1));
            
            if (empty($kich_co) || $mau_sac_id == 0) {
                echo json_encode(['success' => false, 'message' => 'Vui lòng chọn size và màu sắc!']);
                exit;
            }
            
            // Find variant
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
            
            // Check existing cart item
            $check_cart = $pdo->prepare("
                SELECT * FROM gio_hang 
                WHERE bien_the_id = ? 
                AND (khach_hang_id = ? OR session_id = ?)
            ");
            $check_cart->execute([$selected_variant['id'], $customer_id, $session_id]);
            $existing_item = $check_cart->fetch();
            
            if ($existing_item) {
                $new_quantity = $existing_item['so_luong'] + $so_luong;
                if ($new_quantity > $selected_variant['so_luong_ton_kho']) {
                    echo json_encode(['success' => false, 'message' => 'Không đủ hàng trong kho! Tối đa có thể mua: ' . $selected_variant['so_luong_ton_kho']]);
                    exit;
                }
                
                $pdo->prepare("UPDATE gio_hang SET so_luong = ?, gia_tai_thoi_diem = ? WHERE id = ?")
                    ->execute([$new_quantity, $selected_variant['gia_ban'], $existing_item['id']]);
                
                $message = 'Cập nhật số lượng sản phẩm thành công!';
            } else {
                $pdo->prepare("
                    INSERT INTO gio_hang (khach_hang_id, session_id, san_pham_id, bien_the_id, so_luong, gia_tai_thoi_diem, ngay_them)
                    VALUES (?, ?, ?, ?, ?, ?, NOW())
                ")->execute([
                    $customer_id, 
                    $session_id, 
                    $product['id'],
                    $selected_variant['id'],
                    $so_luong, 
                    $selected_variant['gia_ban']
                ]);
                
                $message = 'Thêm sản phẩm vào giỏ hàng thành công!';
            }
            
        } else {
            // Handle simple products without variants
            $so_luong = max(1, (int)($_POST['so_luong'] ?? 1));
            
            if ($product[$f['stock_quantity']] < $so_luong) {
                echo json_encode(['success' => false, 'message' => 'Không đủ hàng trong kho! Còn lại: ' . $product[$f['stock_quantity']]]);
                exit;
            }
            
            // For simple products, we'll simulate adding to a simple cart table or session
            if (!isset($_SESSION['cart'])) {
                $_SESSION['cart'] = [];
            }
            
            $cart_key = $product['id'];
            if (isset($_SESSION['cart'][$cart_key])) {
                $_SESSION['cart'][$cart_key]['quantity'] += $so_luong;
            } else {
                $_SESSION['cart'][$cart_key] = [
                    'product_id' => $product['id'],
                    'name' => $product[$f['name']],
                    'price' => $product['current_price'],
                    'quantity' => $so_luong,
                    'image' => $product[$f['image']]
                ];
            }
            
            $message = 'Thêm sản phẩm vào giỏ hàng thành công!';
        }
        
        // Count cart items
        if ($db_schema['has_variants']) {
            $count_stmt = $pdo->prepare("SELECT SUM(so_luong) FROM gio_hang WHERE (khach_hang_id = ? OR session_id = ?)");
            $count_stmt->execute([$customer_id, $session_id]);
            $cart_count = $count_stmt->fetchColumn() ?: 0;
        } else {
            $cart_count = array_sum(array_column($_SESSION['cart'] ?? [], 'quantity'));
        }
        
        echo json_encode([
            'success' => true, 
            'message' => $message,
            'cart_count' => $cart_count
        ]);
        
    } catch (Exception $e) {
        echo json_encode(['success' => false, 'message' => 'Có lỗi xảy ra: ' . $e->getMessage()]);
    }
    exit;
}

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
        return "/tktshop/uploads/products/no-image.jpg";
    }
    return "/tktshop/uploads/products/" . htmlspecialchars($imageName);
}

function getCurrentUrl() {
    $protocol = isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? 'https' : 'http';
    return $protocol . '://' . $_SERVER['HTTP_HOST'] . $_SERVER['REQUEST_URI'];
}

function formatPrice($price) {
    return number_format($price, 0, ',', '.') . '₫';
}

$page_title = htmlspecialchars($product[$f['name']]) . ' - TKT Shop';
?>

<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= $page_title ?></title>
    <meta name="description" content="<?= htmlspecialchars($product[$f['description']] ?? '') ?>">
    
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/brands.min.css" rel="stylesheet">
    
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
        
        .debug-info {
            background: #e8f4fd;
            border: 1px solid #bee5eb;
            border-radius: 5px;
            padding: 10px;
            margin: 10px 0;
            font-size: 12px;
        }
    </style>
</head>
<body>
    <?php include 'includes/header.php'; ?>
    
    <div class="container py-4">
        <!-- 🔧 Debug Info -->
        <div class="debug-info">
            <strong>🔧 Product Detail Debug:</strong><br>
            Database Schema: <?= $db_schema['table'] ?> (<?= $db_schema['has_variants'] ? 'with variants' : 'simple' ?>)<br>
            Product ID: <?= $product['id'] ?><br>
            Product Slug: <?= $product[$f['slug']] ?? 'N/A' ?><br>
            URL Params: id=<?= $id ?>, slug=<?= htmlspecialchars($slug) ?><br>
            Variants found: <?= count($variants) ?><br>
            Images found: <?= count($product_images) ?><br>
        </div>
        
        <!-- Breadcrumb -->
        <nav aria-label="breadcrumb" class="mb-4">
            <ol class="breadcrumb">
                <li class="breadcrumb-item"><a href="/tktshop/">Trang chủ</a></li>
                <li class="breadcrumb-item"><a href="/tktshop/customer/products.php">Sản phẩm</a></li>
                <?php if ($product[$f['category_name']]): ?>
                    <li class="breadcrumb-item">
                        <a href="/tktshop/customer/products.php?category=<?= $product[$f['category_id']] ?>">
                            <?= htmlspecialchars($product[$f['category_name']]) ?>
                        </a>
                    </li>
                <?php endif; ?>
                <li class="breadcrumb-item active"><?= htmlspecialchars($product[$f['name']]) ?></li>
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
                                 alt="<?= htmlspecialchars($product[$f['name']]) ?>"
                                 class="main-image"
                                 onerror="this.src='/tktshop/uploads/products/no-image.jpg'">
                        <?php else: ?>
                            <div class="no-image-placeholder">
                                <div class="text-center text-muted">
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
        
        // Image zoom functionality
        function initImageZoom() {
            const mainImage = document.getElementById('mainImage');
            if (mainImage) {
                mainImage.addEventListener('click', function() {
                    const modal = document.createElement('div');
                    modal.className = 'modal fade';
                    modal.innerHTML = `
                        <div class="modal-dialog modal-lg modal-dialog-centered">
                            <div class="modal-content">
                                <div class="modal-header">
                                    <h5 class="modal-title">Xem ảnh chi tiết</h5>
                                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                                </div>
                                <div class="modal-body text-center">
                                    <img src="${this.src}" class="img-fluid" alt="Product Image">
                                </div>
                            </div>
                        </div>
                    `;
                    
                    document.body.appendChild(modal);
                    const bootstrapModal = new bootstrap.Modal(modal);
                    bootstrapModal.show();
                    
                    modal.addEventListener('hidden.bs.modal', function() {
                        modal.remove();
                    });
                });
            }
        }
        
        // Quantity validation
        function validateQuantity() {
            const quantityInput = document.getElementById('quantity');
            if (quantityInput) {
                quantityInput.addEventListener('change', function() {
                    const value = parseInt(this.value);
                    const min = parseInt(this.min) || 1;
                    const max = parseInt(this.max) || 999;
                    
                    if (value < min) {
                        this.value = min;
                        showToast('Số lượng tối thiểu là ' + min, 'error');
                    } else if (value > max) {
                        this.value = max;
                        showToast('Số lượng tối đa là ' + max, 'error');
                    }
                });
            }
        }
        
        // Product sharing
        function shareProduct(platform) {
            const url = encodeURIComponent(window.location.href);
            const title = encodeURIComponent(document.title);
            let shareUrl = '';
            
            switch (platform) {
                case 'facebook':
                    shareUrl = `https://www.facebook.com/sharer/sharer.php?u=${url}`;
                    break;
                case 'twitter':
                    shareUrl = `https://twitter.com/intent/tweet?url=${url}&text=${title}`;
                    break;
                case 'pinterest':
                    const image = encodeURIComponent(document.getElementById('mainImage')?.src || '');
                    shareUrl = `https://pinterest.com/pin/create/button/?url=${url}&media=${image}&description=${title}`;
                    break;
                case 'copy':
                    navigator.clipboard.writeText(window.location.href).then(() => {
                        showToast('Đã copy link sản phẩm!', 'success');
                    });
                    return;
            }
            
            if (shareUrl) {
                window.open(shareUrl, '_blank', 'width=600,height=400');
            }
        }
        
        // Product comparison (future feature)
        function addToCompare(productId) {
            let compareList = JSON.parse(localStorage.getItem('compareProducts') || '[]');
            
            if (compareList.includes(productId)) {
                showToast('Sản phẩm đã có trong danh sách so sánh', 'info');
                return;
            }
            
            if (compareList.length >= 3) {
                showToast('Chỉ có thể so sánh tối đa 3 sản phẩm', 'error');
                return;
            }
            
            compareList.push(productId);
            localStorage.setItem('compareProducts', JSON.stringify(compareList));
            showToast('Đã thêm vào danh sách so sánh', 'success');
            
            updateCompareButton();
        }
        
        function updateCompareButton() {
            const compareList = JSON.parse(localStorage.getItem('compareProducts') || '[]');
            const compareBtn = document.getElementById('compareBtn');
            if (compareBtn) {
                compareBtn.innerHTML = `<i class="fas fa-balance-scale me-1"></i>So sánh (${compareList.length})`;
            }
        }
        
        // Recently viewed products
        function addToRecentlyViewed(productId, productName, productImage, productPrice) {
            let recentProducts = JSON.parse(localStorage.getItem('recentlyViewed') || '[]');
            
            // Remove if already exists
            recentProducts = recentProducts.filter(p => p.id !== productId);
            
            // Add to beginning
            recentProducts.unshift({
                id: productId,
                name: productName,
                image: productImage,
                price: productPrice,
                viewedAt: new Date().toISOString()
            });
            
            // Keep only last 10
            recentProducts = recentProducts.slice(0, 10);
            
            localStorage.setItem('recentlyViewed', JSON.stringify(recentProducts));
        }
        
        // Display recently viewed products
        function displayRecentlyViewed() {
            const recentProducts = JSON.parse(localStorage.getItem('recentlyViewed') || '[]');
            const currentProductId = <?= $product['id'] ?>;
            
            // Filter out current product
            const filteredProducts = recentProducts.filter(p => p.id != currentProductId);
            
            if (filteredProducts.length > 0) {
                const container = document.getElementById('recentlyViewedContainer');
                const section = document.getElementById('recentlyViewedSection');
                
                let html = '';
                filteredProducts.slice(0, 4).forEach(product => {
                    html += `
                        <div class="col-lg-3 col-md-4 col-sm-6 mb-3">
                            <div class="card h-100">
                                <img src="/tktshop/uploads/products/${product.image || 'no-image.jpg'}" 
                                     class="card-img-top" 
                                     alt="${product.name}"
                                     style="height: 150px; object-fit: cover;"
                                     onerror="this.src='/tktshop/uploads/products/no-image.jpg'">
                                <div class="card-body">
                                    <h6 class="card-title">
                                        <a href="product_detail.php?id=${product.id}" class="text-decoration-none text-dark">
                                            ${product.name}
                                        </a>
                                    </h6>
                                    <div class="fw-bold text-primary">${formatPrice(product.price)}</div>
                                    <small class="text-muted">Đã xem ${timeAgo(product.viewedAt)}</small>
                                </div>
                            </div>
                        </div>
                    `;
                });
                
                container.innerHTML = html;
                section.style.display = 'block';
            }
        }
        
        // Time ago helper
        function timeAgo(dateString) {
            const now = new Date();
            const past = new Date(dateString);
            const diffInSeconds = Math.floor((now - past) / 1000);
            
            if (diffInSeconds < 60) return 'vừa xong';
            if (diffInSeconds < 3600) return Math.floor(diffInSeconds / 60) + ' phút trước';
            if (diffInSeconds < 86400) return Math.floor(diffInSeconds / 3600) + ' giờ trước';
            return Math.floor(diffInSeconds / 86400) + ' ngày trước';
        }
        
        // Format price helper
        function formatPrice(price) {
            return new Intl.NumberFormat('vi-VN').format(price) + '₫';
        }
        
        // Initialize all features
        document.addEventListener('DOMContentLoaded', function() {
            console.log('🔧 TKT Shop Product Detail - Universal Handler loaded');
            console.log('📊 Database Schema:', '<?= $db_schema['table'] ?>', hasVariants ? 'with variants' : 'simple');
            console.log('📦 Product ID:', <?= $product['id'] ?>);
            
            <?php if ($db_schema['has_variants']): ?>
            console.log('🎨 Variants:', <?= count($variants) ?>, 'sizes:', <?= count($sizes) ?>, 'colors:', <?= count($colors) ?>);
            <?php endif; ?>
            
            // Initialize features
            initImageZoom();
            validateQuantity();
            updateCompareButton();
            displayRecentlyViewed();
            
            // Add to recently viewed
            addToRecentlyViewed(
                <?= $product['id'] ?>,
                '<?= addslashes($product[$f['name']]) ?>',
                '<?= addslashes($product[$f['image']] ?? '') ?>',
                <?= $product['current_price'] ?>
            );
            
            // Auto-scroll to product if coming from search
            if (window.location.hash === '#product') {
                document.querySelector('.product-info')?.scrollIntoView({ behavior: 'smooth' });
            }
            
            // Keyboard shortcuts
            document.addEventListener('keydown', function(e) {
                // Ctrl + Enter to add to cart
                if (e.ctrlKey && e.key === 'Enter') {
                    e.preventDefault();
                    addToCartAjax();
                }
                
                // Escape to close modals
                if (e.key === 'Escape') {
                    const modals = document.querySelectorAll('.modal.show');
                    modals.forEach(modal => {
                        const bsModal = bootstrap.Modal.getInstance(modal);
                        if (bsModal) bsModal.hide();
                    });
                }
            });
        });
        
        // Handle browser back/forward
        window.addEventListener('popstate', function(e) {
            if (e.state && e.state.productId) {
                // Handle product navigation without page reload
                console.log('Navigating to product:', e.state.productId);
            }
        });
        
        // Update URL without reload when variant changes
        function updateUrlWithVariant(size, colorId) {
            if (history.pushState) {
                const url = new URL(window.location);
                if (size) url.searchParams.set('size', size);
                if (colorId) url.searchParams.set('color', colorId);
                
                history.pushState({
                    productId: <?= $product['id'] ?>,
                    size: size,
                    colorId: colorId
                }, '', url);
            }
        }
        
        // SEO and Social Meta Updates
        function updatePageMeta(productName, productImage, productPrice) {
            // Update page title
            document.title = productName + ' - TKT Shop';
            
            // Update meta description
            const metaDesc = document.querySelector('meta[name="description"]');
            if (metaDesc) {
                metaDesc.content = `Mua ${productName} giá tốt tại TKT Shop. Giá: ${formatPrice(productPrice)}. Giao hàng nhanh, đổi trả dễ dàng.`;
            }
            
            // Update Open Graph meta tags
            const ogTitle = document.querySelector('meta[property="og:title"]');
            const ogDescription = document.querySelector('meta[property="og:description"]');
            const ogImage = document.querySelector('meta[property="og:image"]');
            const ogUrl = document.querySelector('meta[property="og:url"]');
            
            if (ogTitle) ogTitle.content = productName;
            if (ogDescription) ogDescription.content = `Mua ${productName} tại TKT Shop`;
            if (ogImage) ogImage.content = productImage;
            if (ogUrl) ogUrl.content = window.location.href;
        }
        
        // Update page meta on load
        updatePageMeta(
            '<?= addslashes($product[$f['name']]) ?>',
            '<?= getImageUrl($product[$f['image']]) ?>',
            <?= $product['current_price'] ?>
        );
        
        // Analytics tracking (placeholder for future integration)
        function trackProductView(productId, productName, category, price) {
            // Google Analytics 4 event
            if (typeof gtag !== 'undefined') {
                gtag('event', 'view_item', {
                    currency: 'VND',
                    value: price,
                    items: [{
                        item_id: productId,
                        item_name: productName,
                        category: category,
                        price: price
                    }]
                });
            }
            
            // Facebook Pixel event
            if (typeof fbq !== 'undefined') {
                fbq('track', 'ViewContent', {
                    content_type: 'product',
                    content_ids: [productId],
                    content_name: productName,
                    value: price,
                    currency: 'VND'
                });
            }
            
            console.log('📊 Tracked product view:', productId, productName);
        }
        
        // Track product view
        trackProductView(
            <?= $product['id'] ?>,
            '<?= addslashes($product[$f['name']]) ?>',
            '<?= addslashes($product[$f['category_name']] ?? 'Unknown') ?>',
            <?= $product['current_price'] ?>
        );
    </script>
    
    <!-- Add Social Meta Tags -->
    <meta property="og:type" content="product">
    <meta property="og:title" content="<?= htmlspecialchars($product[$f['name']]) ?>">
    <meta property="og:description" content="<?= htmlspecialchars($product[$f['description']] ?? '') ?>">
    <meta property="og:image" content="<?= getImageUrl($product[$f['image']]) ?>">
    <meta property="og:url" content="<?= getCurrentUrl() ?>">
    <meta property="product:price:amount" content="<?= $product['current_price'] ?>">
    <meta property="product:price:currency" content="VND">
    
    <!-- Schema.org Product Markup -->
    <script type="application/ld+json">
    {
        "@context": "https://schema.org/",
        "@type": "Product",
        "name": "<?= addslashes($product[$f['name']]) ?>",
        "image": "<?= getImageUrl($product[$f['image']]) ?>",
        "description": "<?= addslashes($product[$f['description']] ?? '') ?>",
        "brand": {
            "@type": "Brand",
            "name": "<?= addslashes($product[$f['brand']] ?? 'TKT Shop') ?>"
        },
        "offers": {
            "@type": "Offer",
            "url": "<?= getCurrentUrl() ?>",
            "priceCurrency": "VND",
            "price": "<?= $product['current_price'] ?>",
            "availability": "https://schema.org/InStock",
            "seller": {
                "@type": "Organization",
                "name": "TKT Shop"
            }
        },
        "aggregateRating": {
            "@type": "AggregateRating",
            "ratingValue": "<?= $product[$f['rating']] ?>",
            "reviewCount": "<?= $product[$f['rating_count']] ?>"
        }
    }
    </script>
    
    <?php include 'includes/footer.php'; ?>
</body>
</html> fa-image fa-3x mb-3"></i>
                                    <p>Hình ảnh sản phẩm<br>đang được cập nhật</p>
                                </div>
                            </div>
                        <?php endif; ?>
                        
                        <?php if ($product['discount_percent'] > 0): ?>
                            <span class="badge bg-danger position-absolute" style="top: 15px; right: 15px;">-<?= $product['discount_percent'] ?>%</span>
                        <?php endif; ?>
                        
                        <?php if ($product[$f['featured']]): ?>
                            <span class="badge bg-warning text-dark position-absolute" style="top: 15px; left: 15px;">Hot</span>
                        <?php endif; ?>
                    </div>
                    
                    <?php if (count($product_images) > 1): ?>
                        <div class="d-flex gap-2 mt-3 overflow-auto">
                            <?php foreach ($product_images as $index => $image): ?>
                                <img src="<?= getImageUrl($image) ?>" 
                                     alt="<?= htmlspecialchars($product[$f['name']]) ?> - Ảnh <?= $index + 1 ?>"
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
                        <?php if ($product[$f['brand']]): ?>
                            <div class="text-muted mb-2">
                                <i class="fas fa-tag me-1"></i>
                                <?= htmlspecialchars($product[$f['brand']]) ?>
                            </div>
                        <?php endif; ?>
                        <h1 class="h3"><?= htmlspecialchars($product[$f['name']]) ?></h1>
                    </div>
                    
                    <!-- Rating -->
                    <div class="d-flex align-items-center mb-3">
                        <div class="text-warning me-2">
                            <?php for ($i = 1; $i <= 5; $i++): ?>
                                <i class="fas fa-star<?= $i <= floor($product[$f['rating']]) ? '' : ' text-muted' ?>"></i>
                            <?php endfor; ?>
                        </div>
                        <span class="me-2"><?= number_format($product[$f['rating']], 1) ?></span>
                        <span class="text-muted">
                            (<?= $product[$f['rating_count']] ?> đánh giá)
                        </span>
                    </div>
                    
                    <!-- Price -->
                    <div class="price-section">
                        <div class="d-flex align-items-center gap-3 flex-wrap">
                            <?php if ($product[$f['sale_price']] && $product[$f['sale_price']] < $product[$f['price']]): ?>
                                <div class="h4 text-danger mb-0"><?= formatPrice($product[$f['sale_price']]) ?></div>
                                <div class="text-muted text-decoration-line-through"><?= formatPrice($product[$f['price']]) ?></div>
                                <div class="badge bg-danger">Tiết kiệm <?= formatPrice($product[$f['price']] - $product[$f['sale_price']]) ?></div>
                            <?php else: ?>
                                <div class="h4 text-primary mb-0"><?= formatPrice($product[$f['price']]) ?></div>
                            <?php endif; ?>
                        </div>
                    </div>
                    
                    <!-- Short Description -->
                    <?php if ($product[$f['description']]): ?>
                        <div class="mb-4">
                            <p class="text-muted"><?= nl2br(htmlspecialchars($product[$f['description']])) ?></p>
                        </div>
                    <?php endif; ?>
                    
                    <!-- Add to Cart Form -->
                    <form method="POST" id="addToCartForm">
                        <input type="hidden" name="action" value="add_to_cart">
                        
                        <?php if ($db_schema['has_variants'] && !empty($variants)): ?>
                            <!-- Size Selection -->
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
                            
                            <!-- Color Selection -->
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
                                    <input type="number" class="form-control text-center" name="so_luong" id="quantity" value="1" min="1" max="99">
                                    <button class="btn btn-outline-secondary" type="button" onclick="changeQuantity(1)">+</button>
                                </div>
                                <?php if (!$db_schema['has_variants'] && isset($product[$f['stock_quantity']])): ?>
                                    <small class="text-muted">Còn lại: <?= $product[$f['stock_quantity']] ?></small>
                                <?php endif; ?>
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
                            <button type="button" class="btn btn-outline-info btn-lg" id="compareBtn" onclick="addToCompare(<?= $product['id'] ?>)">
                                <i class="fas fa-balance-scale"></i>
                            </button>
                        </div>
                        
                        <!-- Social Sharing -->
                        <div class="mt-3">
                            <small class="text-muted me-2">Chia sẻ:</small>
                            <button type="button" class="btn btn-sm btn-outline-primary me-1" onclick="shareProduct('facebook')">
                                <i class="fab fa-facebook-f"></i>
                            </button>
                            <button type="button" class="btn btn-sm btn-outline-info me-1" onclick="shareProduct('twitter')">
                                <i class="fab fa-twitter"></i>
                            </button>
                            <button type="button" class="btn btn-sm btn-outline-danger me-1" onclick="shareProduct('pinterest')">
                                <i class="fab fa-pinterest"></i>
                            </button>
                            <button type="button" class="btn btn-sm btn-outline-secondary me-1" onclick="shareProduct('copy')">
                                <i class="fas fa-link"></i>
                            </button>
                        </div>
                    </form>
                    
                    <!-- Product Info Icons -->
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
                            <?php if ($product[$f['long_description']]): ?>
                                <div class="product-description">
                                    <?= nl2br(htmlspecialchars($product[$f['long_description']])) ?>
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
                                    <?php if ($product[$f['brand']]): ?>
                                        <tr>
                                            <td class="fw-bold" style="width: 200px;">Thương hiệu:</td>
                                            <td><?= htmlspecialchars($product[$f['brand']]) ?></td>
                                        </tr>
                                    <?php endif; ?>
                                    <tr>
                                        <td class="fw-bold">Tên sản phẩm:</td>
                                        <td><?= htmlspecialchars($product[$f['name']]) ?></td>
                                    </tr>
                                    <tr>
                                        <td class="fw-bold">Danh mục:</td>
                                        <td><?= htmlspecialchars($product[$f['category_name']] ?? 'Chưa phân loại') ?></td>
                                    </tr>
                                    <?php if (!$db_schema['has_variants'] && isset($product[$f['stock_quantity']])): ?>
                                        <tr>
                                            <td class="fw-bold">Tồn kho:</td>
                                            <td><?= $product[$f['stock_quantity']] ?> sản phẩm</td>
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
                                        <img src="<?= getImageUrl($related[$f['image']]) ?>" 
                                             class="card-img-top" 
                                             alt="<?= htmlspecialchars($related[$f['name']]) ?>"
                                             style="height: 200px; object-fit: cover;"
                                             onerror="this.src='/tktshop/uploads/products/no-image.jpg'">
                                        
                                        <?php if ($related[$f['sale_price']] && $related[$f['sale_price']] < $related[$f['price']]): ?>
                                            <?php $discount = round((($related[$f['price']] - $related[$f['sale_price']]) / $related[$f['price']]) * 100); ?>
                                            <span class="badge bg-danger position-absolute" style="top: 10px; right: 10px;">
                                                -<?= $discount ?>%
                                            </span>
                                        <?php endif; ?>
                                    </div>
                                    
                                    <div class="card-body d-flex flex-column">
                                        <h6 class="card-title">
                                            <?php
                                            $related_url = !empty($related[$f['slug']]) 
                                                ? "product_detail.php?slug=" . urlencode($related[$f['slug']]) 
                                                : "product_detail.php?id=" . $related['id'];
                                            ?>
                                            <a href="<?= $related_url ?>" class="text-decoration-none text-dark">
                                                <?= htmlspecialchars($related[$f['name']]) ?>
                                            </a>
                                        </h6>
                                        
                                        <div class="mt-auto">
                                            <div class="d-flex align-items-center justify-content-between">
                                                <div>
                                                    <?php if ($related[$f['sale_price']] && $related[$f['sale_price']] < $related[$f['price']]): ?>
                                                        <div class="fw-bold text-danger"><?= formatPrice($related[$f['sale_price']]) ?></div>
                                                        <small class="text-muted text-decoration-line-through"><?= formatPrice($related[$f['price']]) ?></small>
                                                    <?php else: ?>
                                                        <div class="fw-bold text-primary"><?= formatPrice($related[$f['price']]) ?></div>
                                                    <?php endif; ?>
                                                </div>
                                                <div>
                                                    <?php if ($db_schema['has_variants']): ?>
                                                        <?php if (isset($related['total_stock']) && $related['total_stock'] > 0): ?>
                                                            <small class="text-success">Còn hàng</small>
                                                        <?php else: ?>
                                                            <small class="text-danger">Hết hàng</small>
                                                        <?php endif; ?>
                                                    <?php else: ?>
                                                        <?php if (isset($related[$f['stock_quantity']]) && $related[$f['stock_quantity']] > 0): ?>
                                                            <small class="text-success">Còn hàng</small>
                                                        <?php else: ?>
                                                            <small class="text-danger">Hết hàng</small>
                                                        <?php endif; ?>
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
        
        <!-- Recently Viewed Products Section -->
        <div class="row mt-5" id="recentlyViewedSection" style="display: none;">
            <div class="col-12">
                <h3 class="mb-4">Sản phẩm đã xem</h3>
                <div class="row" id="recentlyViewedContainer">
                    <!-- Will be populated by JavaScript -->
                </div>
            </div>
        </div>
    </div>
    
    <!-- Scripts -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
    
    <script>
        // Variables from PHP
        const hasVariants = <?= $db_schema['has_variants'] ? 'true' : 'false' ?>;
        
        <?php if ($db_schema['has_variants'] && !empty($variant_matrix)): ?>
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
                    colorOption.style.opacity = '1';
                } else {
                    colorOption.classList.add('disabled');
                    colorOption.style.opacity = '0.3';
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
                    sizeOption.style.opacity = '1';
                } else {
                    sizeOption.classList.add('disabled');
                    sizeOption.style.opacity = '0.3';
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
                    addToCartBtn.innerHTML = '<i class="fas fa-ban me-2"></i>Hết hàng';
                }
            } else {
                addToCartBtn.disabled = true;
                addToCartBtn.innerHTML = '<i class="fas fa-shopping-cart me-2"></i>Chọn size và màu';
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
            console.log('🛒 Adding to cart...');
            
            <?php if ($db_schema['has_variants']): ?>
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
            
            <?php if ($db_schema['has_variants']): ?>
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
                    
                    // Update cart count in header
                    if (data.cart_count && document.getElementById('cart-count')) {
                        document.getElementById('cart-count').textContent = data.cart_count;
                    }
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
                            <i class="fasoption {
            border: 2px solid #dee2e6;
            border-radius: 8px;
            padding: 10px 15px;
            margin: 5px;
            cursor: pointer;
            transition: all 0.3s;
            display: inline-block;
        }
        
        .variant-