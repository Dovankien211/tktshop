<?php
/**
 * customer/products.php - UNIFIED DATABASE HANDLER
 * 🔧 FIXED: Tự động detect và tương thích với cả 2 hệ thống database
 */

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../config/config.php';

// 🔧 AUTO-DETECT DATABASE SCHEMA
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
            
            // Get Vietnamese field mappings
            $schema['fields'] = [
                'id' => 'id',
                'name' => 'ten_san_pham',
                'slug' => 'slug',
                'description' => 'mo_ta_ngan',
                'price' => 'gia_goc',
                'sale_price' => 'gia_khuyen_mai',
                'brand' => 'thuong_hieu',
                'category_id' => 'danh_muc_id',
                'image' => 'hinh_anh_chinh',
                'status' => 'trang_thai',
                'status_active' => 'hoat_dong',
                'featured' => 'san_pham_noi_bat',
                'view_count' => 'luot_xem',
                'rating' => 'diem_danh_gia_tb',
                'rating_count' => 'so_luong_danh_gia',
                'created_at' => 'ngay_tao',
                'category_name' => 'ten_danh_muc'
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
                'price' => 'price',
                'sale_price' => 'sale_price',
                'brand' => 'brand',
                'category_id' => 'category_id',
                'image' => 'main_image',
                'status' => 'status',
                'status_active' => 'active',
                'featured' => 'is_featured',
                'view_count' => 'view_count',
                'rating' => 'rating_average',
                'rating_count' => 'rating_count',
                'created_at' => 'created_at',
                'category_name' => 'name'
            ];
        }
        
        // 🔧 FIX: Auto-add slug if missing
        $stmt = $pdo->query("SHOW COLUMNS FROM {$schema['table']} LIKE 'slug'");
        if ($stmt->rowCount() == 0) {
            try {
                $pdo->exec("ALTER TABLE {$schema['table']} ADD COLUMN slug VARCHAR(255) NULL AFTER " . $schema['fields']['name']);
                
                // Generate slugs for existing records
                $stmt = $pdo->query("SELECT id, " . $schema['fields']['name'] . " FROM {$schema['table']} WHERE slug IS NULL OR slug = ''");
                $update_stmt = $pdo->prepare("UPDATE {$schema['table']} SET slug = ? WHERE id = ?");
                
                while ($row = $stmt->fetch()) {
                    $slug = createSlug($row[$schema['fields']['name']]);
                    $update_stmt->execute([$slug, $row['id']]);
                }
            } catch (Exception $e) {
                error_log("Could not add slug column: " . $e->getMessage());
            }
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
$f = $db_schema['fields']; // Field mappings shorthand

// Get search parameters
$search = trim($_GET['search'] ?? '');
$category = (int)($_GET['category'] ?? 0);
$brand = trim($_GET['brand'] ?? '');
$min_price = (int)($_GET['min_price'] ?? 0);
$max_price = (int)($_GET['max_price'] ?? 0);
$featured = isset($_GET['featured']) ? 1 : 0;
$sale = isset($_GET['sale']) ? 1 : 0;

// Pagination and sorting
$sort = $_GET['sort'] ?? 'newest';
$page = max(1, (int)($_GET['page'] ?? 1));
$limit = 12;
$offset = ($page - 1) * $limit;

// 🔧 UNIFIED QUERY BUILDER
$where_conditions = ["{$f['status']} = '{$f['status_active']}'"];
$params = [];

// Search condition
if (!empty($search)) {
    $where_conditions[] = "({$f['name']} LIKE ? OR {$f['brand']} LIKE ?)";
    $params[] = "%$search%";
    $params[] = "%$search%";
}

// Category filter
if ($category > 0) {
    $where_conditions[] = "{$f['category_id']} = ?";
    $params[] = $category;
}

// Brand filter
if (!empty($brand)) {
    $where_conditions[] = "{$f['brand']} = ?";
    $params[] = $brand;
}

// Price filters
if ($min_price > 0) {
    $price_field = "COALESCE({$f['sale_price']}, {$f['price']})";
    $where_conditions[] = "$price_field >= ?";
    $params[] = $min_price;
}
if ($max_price > 0) {
    $price_field = "COALESCE({$f['sale_price']}, {$f['price']})";
    $where_conditions[] = "$price_field <= ?";
    $params[] = $max_price;
}

// Special filters
if ($featured) {
    $where_conditions[] = "{$f['featured']} = 1";
}
if ($sale) {
    $where_conditions[] = "{$f['sale_price']} IS NOT NULL AND {$f['sale_price']} < {$f['price']}";
}

// Stock condition (unified)
if ($db_schema['has_variants']) {
    // For Vietnamese schema with variants
    $stock_condition = "EXISTS (SELECT 1 FROM bien_the_san_pham bsp WHERE bsp.san_pham_id = p.id AND bsp.so_luong_ton_kho > 0)";
} else {
    // For English schema
    $stock_condition = "stock_quantity > 0";
}
$where_conditions[] = $stock_condition;

// Build ORDER BY
$order_options = [
    'newest' => "{$f['created_at']} DESC",
    'price_asc' => "COALESCE({$f['sale_price']}, {$f['price']}) ASC",
    'price_desc' => "COALESCE({$f['sale_price']}, {$f['price']}) DESC",
    'name_asc' => "{$f['name']} ASC",
    'name_desc' => "{$f['name']} DESC",
    'rating' => "{$f['rating']} DESC, {$f['rating_count']} DESC",
    'popular' => "{$f['view_count']} DESC"
];
$order_clause = $order_options[$sort] ?? $order_options['newest'];

$where_clause = implode(" AND ", $where_conditions);

// 🔧 UNIFIED MAIN QUERY
if ($db_schema['has_variants']) {
    // Vietnamese schema with variants
    $main_sql = "
        SELECT p.*, c.{$f['category_name']},
               COALESCE(p.{$f['sale_price']}, p.{$f['price']}) as current_price,
               CASE 
                   WHEN p.{$f['sale_price']} IS NOT NULL AND p.{$f['sale_price']} < p.{$f['price']} 
                   THEN ROUND(((p.{$f['price']} - p.{$f['sale_price']}) / p.{$f['price']}) * 100, 0)
                   ELSE 0
               END as discount_percent,
               MIN(bsp.gia_ban) as min_variant_price,
               SUM(bsp.so_luong_ton_kho) as total_stock
        FROM {$db_schema['table']} p
        LEFT JOIN {$db_schema['category_table']} c ON p.{$f['category_id']} = c.id
        LEFT JOIN bien_the_san_pham bsp ON p.id = bsp.san_pham_id AND bsp.trang_thai = 'hoat_dong'
        WHERE $where_clause
        GROUP BY p.id
        HAVING total_stock > 0
        ORDER BY $order_clause
    ";
} else {
    // English schema
    $main_sql = "
        SELECT p.*, c.{$f['category_name']},
               COALESCE(p.{$f['sale_price']}, p.{$f['price']}) as current_price,
               CASE 
                   WHEN p.{$f['sale_price']} IS NOT NULL AND p.{$f['sale_price']} < p.{$f['price']} 
                   THEN ROUND(((p.{$f['price']} - p.{$f['sale_price']}) / p.{$f['price']}) * 100, 0)
                   ELSE 0
               END as discount_percent
        FROM {$db_schema['table']} p
        LEFT JOIN {$db_schema['category_table']} c ON p.{$f['category_id']} = c.id
        WHERE $where_clause
        ORDER BY $order_clause
    ";
}

// Count query
$count_sql = "SELECT COUNT(*) FROM {$db_schema['table']} p WHERE $where_clause";

try {
    // Get total count
    $count_stmt = $pdo->prepare($count_sql);
    $count_stmt->execute($params);
    $total_products = $count_stmt->fetchColumn();
    $total_pages = ceil($total_products / $limit);

    // Get products with pagination
    $stmt = $pdo->prepare($main_sql . " LIMIT ? OFFSET ?");
    $stmt->execute([...$params, $limit, $offset]);
    $products = $stmt->fetchAll();

    // Get categories for filter
    $categories = $pdo->query("
        SELECT c.*, COUNT(p.id) as product_count
        FROM {$db_schema['category_table']} c
        LEFT JOIN {$db_schema['table']} p ON c.id = p.{$f['category_id']} AND p.{$f['status']} = '{$f['status_active']}'
        GROUP BY c.id
        HAVING product_count > 0
        ORDER BY c." . ($db_schema['table'] == 'san_pham_chinh' ? 'thu_tu_hien_thi' : 'sort_order') . " ASC
    ")->fetchAll();

    // Get brands
    $brands = $pdo->query("
        SELECT {$f['brand']}, COUNT(*) as product_count
        FROM {$db_schema['table']} 
        WHERE {$f['status']} = '{$f['status_active']}' AND {$f['brand']} IS NOT NULL AND {$f['brand']} != ''
        GROUP BY {$f['brand']}
        ORDER BY {$f['brand']} ASC
    ")->fetchAll();

    // Get price range
    $price_range = $pdo->query("
        SELECT 
            MIN(COALESCE({$f['sale_price']}, {$f['price']})) as min_price,
            MAX(COALESCE({$f['sale_price']}, {$f['price']})) as max_price
        FROM {$db_schema['table']} 
        WHERE {$f['status']} = '{$f['status_active']}'
    ")->fetch();

} catch (Exception $e) {
    error_log("Products query error: " . $e->getMessage());
    $products = [];
    $categories = [];
    $brands = [];
    $total_products = 0;
    $total_pages = 0;
    $price_range = ['min_price' => 0, 'max_price' => 0];
}

// Helper functions
function updateUrlParam($key, $value) {
    $params = $_GET;
    if (empty($value)) {
        unset($params[$key]);
    } else {
        $params[$key] = $value;
    }
    unset($params['page']);
    return '?' . http_build_query($params);
}

function displayStars($rating) {
    $stars = '';
    for ($i = 1; $i <= 5; $i++) {
        if ($i <= $rating) {
            $stars .= '<i class="fas fa-star text-warning"></i>';
        } else {
            $stars .= '<i class="far fa-star text-muted"></i>';
        }
    }
    return $stars;
}

// 🔧 UNIFIED PRODUCT URL GENERATOR
function getProductUrl($product, $db_schema) {
    if (!empty($product['slug'])) {
        return "product_detail.php?slug=" . urlencode($product['slug']);
    } else {
        return "product_detail.php?id=" . $product['id'];
    }
}

// 🔧 UNIFIED IMAGE URL
function getImageUrl($image, $default = 'no-image.jpg') {
    if (empty($image) || $image === 'default-product.jpg') {
        return "/tktshop/uploads/products/$default";
    }
    return "/tktshop/uploads/products/" . htmlspecialchars($image);
}
?>

<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= !empty($search) ? "Tìm kiếm: $search" : "Sản phẩm" ?> - <?= SITE_NAME ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    
    <style>
        .product-card {
            transition: all 0.3s ease;
            border: none;
            height: 100%;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
        }
        
        .product-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 8px 25px rgba(0,0,0,0.15);
        }
        
        .product-image {
            height: 250px;
            object-fit: cover;
            background: #f8f9fa;
        }
        
        .price-original {
            text-decoration: line-through;
            color: #6c757d;
            font-size: 0.9em;
        }
        
        .price-sale {
            color: #dc3545;
            font-weight: bold;
        }
        
        .badge-sale {
            position: absolute;
            top: 10px;
            right: 10px;
            z-index: 1;
        }
        
        .filter-sidebar {
            background: #f8f9fa;
            border-radius: 10px;
            padding: 20px;
            position: sticky;
            top: 20px;
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
            <strong>🔧 System Info:</strong><br>
            Database Schema: <?= $db_schema['table'] ?> (<?= $db_schema['has_variants'] ? 'with variants' : 'simple' ?>)<br>
            Total products found: <?= $total_products ?><br>
            Current page: <?= $page ?> / <?= $total_pages ?><br>
            Search: "<?= htmlspecialchars($search) ?>"<br>
            Category: <?= $category ?><br>
        </div>

        <!-- Page Header -->
        <div class="row mb-4">
            <div class="col-12">
                <h1 class="h3 mb-3">
                    <?php if (!empty($search)): ?>
                        Kết quả tìm kiếm: "<?= htmlspecialchars($search) ?>"
                    <?php else: ?>
                        Tất cả sản phẩm
                    <?php endif; ?>
                </h1>
                
                <!-- Quick filters -->
                <div class="d-flex flex-wrap gap-2 mb-3">
                    <a href="<?= updateUrlParam('featured', $featured ? '' : '1') ?>" 
                       class="btn btn-sm <?= $featured ? 'btn-primary' : 'btn-outline-primary' ?>">
                        <i class="fas fa-star"></i> Nổi bật
                    </a>
                    <a href="<?= updateUrlParam('sale', $sale ? '' : '1') ?>" 
                       class="btn btn-sm <?= $sale ? 'btn-danger' : 'btn-outline-danger' ?>">
                        <i class="fas fa-tags"></i> Giảm giá
                    </a>
                    <a href="products.php" class="btn btn-sm btn-outline-secondary">
                        <i class="fas fa-times"></i> Xóa bộ lọc
                    </a>
                </div>
            </div>
        </div>

        <div class="row">
            <!-- Sidebar Filters -->
            <div class="col-lg-3 col-md-4">
                <div class="filter-sidebar">
                    <h5 class="mb-3">Bộ lọc</h5>

                    <!-- Search -->
                    <div class="mb-4">
                        <h6>Tìm kiếm</h6>
                        <form method="GET" class="d-flex">
                            <?php foreach ($_GET as $key => $value): ?>
                                <?php if ($key !== 'search'): ?>
                                    <input type="hidden" name="<?= htmlspecialchars($key) ?>" value="<?= htmlspecialchars($value) ?>">
                                <?php endif; ?>
                            <?php endforeach; ?>
                            <input type="text" name="search" class="form-control form-control-sm" 
                                   placeholder="Tìm sản phẩm..." value="<?= htmlspecialchars($search) ?>">
                            <button type="submit" class="btn btn-primary btn-sm ms-2">
                                <i class="fas fa-search"></i>
                            </button>
                        </form>
                    </div>

                    <!-- Categories -->
                    <?php if (!empty($categories)): ?>
                    <div class="mb-4">
                        <h6>Danh mục</h6>
                        <div class="list-group list-group-flush">
                            <a href="<?= updateUrlParam('category', '') ?>" 
                               class="list-group-item list-group-item-action border-0 px-0 py-2 <?= $category == 0 ? 'active' : '' ?>">
                                Tất cả
                            </a>
                            <?php foreach ($categories as $cat): ?>
                                <a href="<?= updateUrlParam('category', $cat['id']) ?>" 
                                   class="list-group-item list-group-item-action border-0 px-0 py-2 d-flex justify-content-between <?= $category == $cat['id'] ? 'active' : '' ?>">
                                    <?= htmlspecialchars($cat[$f['category_name']]) ?>
                                    <span class="badge bg-secondary"><?= $cat['product_count'] ?></span>
                                </a>
                            <?php endforeach; ?>
                        </div>
                    </div>
                    <?php endif; ?>

                    <!-- Brands -->
                    <?php if (!empty($brands)): ?>
                    <div class="mb-4">
                        <h6>Thương hiệu</h6>
                        <select class="form-select form-select-sm" onchange="window.location.href = this.value">
                            <option value="<?= updateUrlParam('brand', '') ?>">Tất cả thương hiệu</option>
                            <?php foreach ($brands as $b): ?>
                                <option value="<?= updateUrlParam('brand', $b[$f['brand']]) ?>" 
                                        <?= $brand === $b[$f['brand']] ? 'selected' : '' ?>>
                                    <?= htmlspecialchars($b[$f['brand']]) ?> (<?= $b['product_count'] ?>)
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <?php endif; ?>

                    <!-- Price Range -->
                    <?php if ($price_range && $price_range['max_price'] > 0): ?>
                    <div class="mb-4">
                        <h6>Khoảng giá</h6>
                        <form method="GET" class="d-flex align-items-center gap-2">
                            <?php foreach ($_GET as $key => $value): ?>
                                <?php if (!in_array($key, ['min_price', 'max_price'])): ?>
                                    <input type="hidden" name="<?= htmlspecialchars($key) ?>" value="<?= htmlspecialchars($value) ?>">
                                <?php endif; ?>
                            <?php endforeach; ?>
                            <input type="number" name="min_price" class="form-control form-control-sm" style="width:80px;" 
                                   placeholder="Từ" value="<?= $min_price ?: '' ?>" min="0">
                            <span>-</span>
                            <input type="number" name="max_price" class="form-control form-control-sm" style="width:80px;" 
                                   placeholder="Đến" value="<?= $max_price ?: '' ?>" min="0">
                            <button type="submit" class="btn btn-primary btn-sm">OK</button>
                        </form>
                        <small class="text-muted">
                            Từ <?= formatPrice($price_range['min_price']) ?> đến <?= formatPrice($price_range['max_price']) ?>
                        </small>
                    </div>
                    <?php endif; ?>
                </div>
            </div>

            <!-- Products Grid -->
            <div class="col-lg-9 col-md-8">
                <!-- Sort and Results Info -->
                <div class="d-flex justify-content-between align-items-center mb-4">
                    <div>
                        <span class="text-muted">
                            Hiển thị <?= min($offset + 1, $total_products) ?>-<?= min($offset + $limit, $total_products) ?> 
                            trong <?= $total_products ?> sản phẩm
                        </span>
                    </div>
                    <div class="dropdown">
                        <button class="btn btn-outline-secondary dropdown-toggle" type="button" 
                                data-bs-toggle="dropdown">
                            Sắp xếp: 
                            <?php
                            $sort_labels = [
                                'newest' => 'Mới nhất',
                                'price_asc' => 'Giá thấp đến cao',
                                'price_desc' => 'Giá cao đến thấp',
                                'name_asc' => 'Tên A-Z',
                                'name_desc' => 'Tên Z-A',
                                'rating' => 'Đánh giá cao',
                                'popular' => 'Phổ biến'
                            ];
                            echo $sort_labels[$sort] ?? 'Mới nhất';
                            ?>
                        </button>
                        <ul class="dropdown-menu">
                            <?php foreach ($sort_labels as $key => $label): ?>
                                <li>
                                    <a class="dropdown-item <?= $sort === $key ? 'active' : '' ?>" 
                                       href="<?= updateUrlParam('sort', $key) ?>">
                                        <?= $label ?>
                                    </a>
                                </li>
                            <?php endforeach; ?>
                        </ul>
                    </div>
                </div>

                <!-- Products Grid -->
                <?php if (!empty($products)): ?>
                    <div class="row g-4">
                        <?php foreach ($products as $product): ?>
                            <div class="col-xl-4 col-lg-6 col-md-6">
                                <div class="card product-card h-100">
                                    <div class="position-relative">
                                        <?php if ($product['discount_percent'] > 0): ?>
                                            <span class="badge bg-danger badge-sale">-<?= $product['discount_percent'] ?>%</span>
                                        <?php endif; ?>
                                        
                                        <?php if ($product[$f['featured']]): ?>
                                            <span class="badge bg-success position-absolute" style="top: 10px; left: 10px; z-index: 1;">Nổi bật</span>
                                        <?php endif; ?>
                                        
                                        <!-- 🔧 FIXED: Unified product link -->
                                        <a href="<?= getProductUrl($product, $db_schema) ?>">
                                            <img src="<?= getImageUrl($product[$f['image']]) ?>" 
                                                 class="card-img-top product-image" 
                                                 alt="<?= htmlspecialchars($product[$f['name']]) ?>"
                                                 loading="lazy"
                                                 onerror="this.src='/tktshop/uploads/products/no-image.jpg'">
                                        </a>
                                    </div>
                                    
                                    <div class="card-body">
                                        <h6 class="card-title mb-2">
                                            <!-- 🔧 FIXED: Unified product link -->
                                            <a href="<?= getProductUrl($product, $db_schema) ?>" 
                                               class="text-decoration-none text-dark">
                                                <?= htmlspecialchars($product[$f['name']]) ?>
                                            </a>
                                        </h6>
                                        
                                        <?php if ($product[$f['brand']]): ?>
                                        <p class="text-muted small mb-2">
                                            <i class="fas fa-tag"></i> <?= htmlspecialchars($product[$f['brand']]) ?>
                                        </p>
                                        <?php endif; ?>
                                        
                                        <?php if ($product[$f['description']]): ?>
                                            <p class="card-text small text-muted mb-3">
                                                <?= mb_substr(htmlspecialchars($product[$f['description']]), 0, 80) ?>...
                                            </p>
                                        <?php endif; ?>
                                        
                                        <!-- Rating -->
                                        <?php if ($product[$f['rating']] > 0): ?>
                                            <div class="mb-2">
                                                <?= displayStars(round($product[$f['rating']])) ?>
                                                <span class="text-muted small">
                                                    (<?= $product[$f['rating_count']] ?: 0 ?>)
                                                </span>
                                            </div>
                                        <?php endif; ?>
                                        
                                        <!-- Price -->
                                        <div class="d-flex align-items-center justify-content-between">
                                            <div class="price">
                                                <?php if ($product[$f['sale_price']] && $product[$f['sale_price']] < $product[$f['price']]): ?>
                                                    <span class="price-sale h6 mb-0"><?= formatPrice($product[$f['sale_price']]) ?></span>
                                                    <br>
                                                    <small class="price-original"><?= formatPrice($product[$f['price']]) ?></small>
                                                <?php else: ?>
                                                    <span class="h6 mb-0"><?= formatPrice($product[$f['price']]) ?></span>
                                                <?php endif; ?>
                                            </div>
                                            
                                            <!-- Stock Status -->
                                            <?php if ($db_schema['has_variants']): ?>
                                                <?php if (isset($product['total_stock']) && $product['total_stock'] <= 5): ?>
                                                    <small class="text-danger">
                                                        <i class="fas fa-exclamation-triangle"></i> 
                                                        Còn <?= $product['total_stock'] ?>
                                                    </small>
                                                <?php endif; ?>
                                            <?php else: ?>
                                                <?php if (isset($product['stock_quantity']) && $product['stock_quantity'] <= 5): ?>
                                                    <small class="text-danger">
                                                        <i class="fas fa-exclamation-triangle"></i> 
                                                        Còn <?= $product['stock_quantity'] ?>
                                                    </small>
                                                <?php endif; ?>
                                            <?php endif; ?>
                                        </div>
                                        
                                        <!-- Actions -->
                                        <div class="mt-3 d-grid gap-2">
                                            <!-- 🔧 FIXED: Unified product link -->
                                            <a href="<?= getProductUrl($product, $db_schema) ?>" class="btn btn-primary btn-sm">
                                                <i class="fas fa-eye"></i> Xem chi tiết
                                            </a>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>

                    <!-- Pagination -->
                    <?php if ($total_pages > 1): ?>
                        <nav aria-label="Product pagination" class="mt-5">
                            <ul class="pagination justify-content-center">
                                <!-- Previous page -->
                                <?php if ($page > 1): ?>
                                    <li class="page-item">
                                        <a class="page-link" href="<?= updateUrlParam('page', $page - 1) ?>">
                                            <i class="fas fa-chevron-left"></i> Trước
                                        </a>
                                    </li>
                                <?php endif; ?>

                                <!-- Page numbers -->
                                <?php
                                $start_page = max(1, $page - 2);
                                $end_page = min($total_pages, $page + 2);
                                
                                if ($start_page > 1):
                                ?>
                                    <li class="page-item">
                                        <a class="page-link" href="<?= updateUrlParam('page', 1) ?>">1</a>
                                    </li>
                                    <?php if ($start_page > 2): ?>
                                        <li class="page-item disabled">
                                            <span class="page-link">...</span>
                                        </li>
                                    <?php endif; ?>
                                <?php endif; ?>

                                <?php for ($i = $start_page; $i <= $end_page; $i++): ?>
                                    <li class="page-item <?= $i === $page ? 'active' : '' ?>">
                                        <a class="page-link" href="<?= updateUrlParam('page', $i) ?>"><?= $i ?></a>
                                    </li>
                                <?php endfor; ?>

                                <?php if ($end_page < $total_pages): ?>
                                    <?php if ($end_page < $total_pages - 1): ?>
                                        <li class="page-item disabled">
                                            <span class="page-link">...</span>
                                        </li>
                                    <?php endif; ?>
                                    <li class="page-item">
                                        <a class="page-link" href="<?= updateUrlParam('page', $total_pages) ?>"><?= $total_pages ?></a>
                                    </li>
                                <?php endif; ?>

                                <!-- Next page -->
                                <?php if ($page < $total_pages): ?>
                                    <li class="page-item">
                                        <a class="page-link" href="<?= updateUrlParam('page', $page + 1) ?>">
                                            Sau <i class="fas fa-chevron-right"></i>
                                        </a>
                                    </li>
                                <?php endif; ?>
                            </ul>
                        </nav>
                    <?php endif; ?>

                <?php else: ?>
                    <!-- No products found -->
                    <div class="text-center py-5">
                        <i class="fas fa-search fa-3x text-muted mb-3"></i>
                        <h4>Không tìm thấy sản phẩm</h4>
                        <p class="text-muted">
                            <?php if ($total_products == 0): ?>
                                Hiện tại chưa có sản phẩm nào trong hệ thống.
                            <?php else: ?>
                                Không có sản phẩm nào phù hợp với tiêu chí tìm kiếm của bạn.
                            <?php endif; ?>
                        </p>
                        <a href="products.php" class="btn btn-primary">
                            <i class="fas fa-arrow-left"></i> Xem tất cả sản phẩm
                        </a>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <!-- Bootstrap JS -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        // Enhanced product interactions
        document.addEventListener('DOMContentLoaded', function() {
            const productCards = document.querySelectorAll('.product-card');
            
            productCards.forEach(card => {
                card.addEventListener('mouseenter', function() {
                    this.style.transform = 'translateY(-5px)';
                });
                
                card.addEventListener('mouseleave', function() {
                    this.style.transform = 'translateY(0)';
                });
            });
            
            // Auto-submit price filter after typing stops
            const priceInputs = document.querySelectorAll('input[name="min_price"], input[name="max_price"]');
            priceInputs.forEach(input => {
                let timeout;
                input.addEventListener('input', function() {
                    clearTimeout(timeout);
                    timeout = setTimeout(() => {
                        this.closest('form').submit();
                    }, 1000);
                });
            });
            
            // Smooth scroll to top when changing pages
            if (new URLSearchParams(window.location.search).has('page')) {
                window.scrollTo({
                    top: 0,
                    behavior: 'smooth'
                });
            }
            
            console.log('🔧 TKT Shop Products - Unified Database Handler loaded successfully');
            console.log('📊 Database Schema:', '<?= $db_schema['table'] ?>', '<?= $db_schema['has_variants'] ? 'with variants' : 'simple' ?>');
        });
        
        // Search suggestions (future enhancement)
        function initSearchSuggestions() {
            const searchInput = document.querySelector('input[name="search"]');
            if (searchInput) {
                let searchTimeout;
                searchInput.addEventListener('input', function() {
                    clearTimeout(searchTimeout);
                    const query = this.value.trim();
                    
                    if (query.length >= 2) {
                        searchTimeout = setTimeout(() => {
                            // Future: implement AJAX search suggestions
                            console.log('Search suggestions for:', query);
                        }, 300);
                    }
                });
            }
        }
        
        // Initialize search suggestions
        initSearchSuggestions();
    </script>
    
    <?php include 'includes/footer.php'; ?>
</body>
</html>