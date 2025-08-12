<?php
/**
 * customer/products_fixed.php - FIXED VERSION
 * 🔧 FIXED: Hiển thị sản phẩm từ cả 2 bảng products + san_pham_chinh
 */

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../config/config.php';

// 🔧 UNIFIED QUERY để lấy từ cả 2 bảng
function getUnifiedProducts($pdo, $search = '', $category = 0, $brand = '', $min_price = 0, $max_price = 0, $featured = 0, $sale = 0, $sort = 'newest', $limit = 12, $offset = 0) {
    $products = [];
    $total_count = 0;
    
    try {
        // Query từ bảng products (English schema)
        $where_products = ["status = 'active'"];
        $params_products = [];
        
        if (!empty($search)) {
            $where_products[] = "(name LIKE ? OR brand LIKE ?)";
            $params_products[] = "%$search%";
            $params_products[] = "%$search%";
        }
        
        if ($category > 0) {
            $where_products[] = "category_id = ?";
            $params_products[] = $category;
        }
        
        if (!empty($brand)) {
            $where_products[] = "brand = ?";
            $params_products[] = $brand;
        }
        
        if ($min_price > 0) {
            $where_products[] = "COALESCE(sale_price, price) >= ?";
            $params_products[] = $min_price;
        }
        
        if ($max_price > 0) {
            $where_products[] = "COALESCE(sale_price, price) <= ?";
            $params_products[] = $max_price;
        }
        
        if ($featured) {
            $where_products[] = "is_featured = 1";
        }
        
        if ($sale) {
            $where_products[] = "sale_price IS NOT NULL AND sale_price < price";
        }
        
        $where_products[] = "stock_quantity > 0";
        
        $sql_products = "
            SELECT 
                id,
                name,
                slug,
                short_description as description,
                price,
                sale_price,
                brand,
                category_id,
                main_image as image,
                is_featured as featured,
                0 as view_count,
                0 as rating_average,
                0 as rating_count,
                stock_quantity,
                created_at,
                'products' as source_table
            FROM products 
            WHERE " . implode(" AND ", $where_products);
        
        $stmt_products = $pdo->prepare($sql_products);
        $stmt_products->execute($params_products);
        $products_data = $stmt_products->fetchAll();
        
        // Query từ bảng san_pham_chinh (Vietnamese schema)  
        $where_vietnam = ["trang_thai = 'hoat_dong'"];
        $params_vietnam = [];
        
        if (!empty($search)) {
            $where_vietnam[] = "(ten_san_pham LIKE ? OR thuong_hieu LIKE ?)";
            $params_vietnam[] = "%$search%";
            $params_vietnam[] = "%$search%";
        }
        
        if ($category > 0) {
            $where_vietnam[] = "danh_muc_id = ?";
            $params_vietnam[] = $category;
        }
        
        if (!empty($brand)) {
            $where_vietnam[] = "thuong_hieu = ?";
            $params_vietnam[] = $brand;
        }
        
        if ($min_price > 0) {
            $where_vietnam[] = "COALESCE(gia_khuyen_mai, gia_goc) >= ?";
            $params_vietnam[] = $min_price;
        }
        
        if ($max_price > 0) {
            $where_vietnam[] = "COALESCE(gia_khuyen_mai, gia_goc) <= ?";
            $params_vietnam[] = $max_price;
        }
        
        if ($featured) {
            $where_vietnam[] = "san_pham_noi_bat = 1";
        }
        
        if ($sale) {
            $where_vietnam[] = "gia_khuyen_mai IS NOT NULL AND gia_khuyen_mai < gia_goc";
        }
        
        // Check stock từ biến thể
        $where_vietnam[] = "EXISTS (SELECT 1 FROM bien_the_san_pham bsp WHERE bsp.san_pham_id = san_pham_chinh.id AND bsp.so_luong_ton_kho > 0 AND bsp.trang_thai = 'hoat_dong')";
        
        $sql_vietnam = "
            SELECT 
                id,
                ten_san_pham as name,
                slug,
                mo_ta_ngan as description,
                gia_goc as price,
                gia_khuyen_mai as sale_price,
                thuong_hieu as brand,
                danh_muc_id as category_id,
                hinh_anh_chinh as image,
                san_pham_noi_bat as featured,
                luot_xem as view_count,
                diem_danh_gia_tb as rating_average,
                so_luong_danh_gia as rating_count,
                0 as stock_quantity,
                ngay_tao as created_at,
                'san_pham_chinh' as source_table
            FROM san_pham_chinh 
            WHERE " . implode(" AND ", $where_vietnam);
        
        $stmt_vietnam = $pdo->prepare($sql_vietnam);
        $stmt_vietnam->execute($params_vietnam);
        $vietnam_data = $stmt_vietnam->fetchAll();
        
        // Merge dữ liệu từ 2 bảng
        $products = array_merge($products_data, $vietnam_data);
        $total_count = count($products);
        
        // Sort sản phẩm
        $sort_options = [
            'newest' => function($a, $b) { return strtotime($b['created_at']) - strtotime($a['created_at']); },
            'price_asc' => function($a, $b) { 
                $price_a = $a['sale_price'] ?: $a['price'];
                $price_b = $b['sale_price'] ?: $b['price'];
                return $price_a - $price_b;
            },
            'price_desc' => function($a, $b) { 
                $price_a = $a['sale_price'] ?: $a['price'];
                $price_b = $b['sale_price'] ?: $b['price'];
                return $price_b - $price_a;
            },
            'name_asc' => function($a, $b) { return strcmp($a['name'], $b['name']); },
            'name_desc' => function($a, $b) { return strcmp($b['name'], $a['name']); },
            'rating' => function($a, $b) { return $b['rating_average'] - $a['rating_average']; },
            'popular' => function($a, $b) { return $b['view_count'] - $a['view_count']; }
        ];
        
        if (isset($sort_options[$sort])) {
            usort($products, $sort_options[$sort]);
        }
        
        // Pagination
        $products = array_slice($products, $offset, $limit);
        
        return ['products' => $products, 'total' => $total_count];
        
    } catch (Exception $e) {
        error_log("getUnifiedProducts error: " . $e->getMessage());
        return ['products' => [], 'total' => 0];
    }
}

// Get parameters
$search = trim($_GET['search'] ?? '');
$category = (int)($_GET['category'] ?? 0);
$brand = trim($_GET['brand'] ?? '');
$min_price = (int)($_GET['min_price'] ?? 0);
$max_price = (int)($_GET['max_price'] ?? 0);
$featured = isset($_GET['featured']) ? 1 : 0;
$sale = isset($_GET['sale']) ? 1 : 0;
$sort = $_GET['sort'] ?? 'newest';
$page = max(1, (int)($_GET['page'] ?? 1));
$limit = 12;
$offset = ($page - 1) * $limit;

// Get products
$result = getUnifiedProducts($pdo, $search, $category, $brand, $min_price, $max_price, $featured, $sale, $sort, $limit, $offset);
$products = $result['products'];
$total_products = $result['total'];
$total_pages = ceil($total_products / $limit);

// Get categories từ cả 2 bảng
$categories = [];
try {
    // Categories từ bảng categories
    $cats1 = $pdo->query("
        SELECT id, name, 0 as product_count FROM categories WHERE status = 'active'
        UNION ALL
        SELECT id, ten_danh_muc as name, 0 as product_count FROM danh_muc_giay WHERE trang_thai = 'hoat_dong'
        ORDER BY name
    ")->fetchAll();
    
    $categories = $cats1;
} catch (Exception $e) {
    $categories = [];
}

// Get brands từ cả 2 bảng  
$brands = [];
try {
    $brands_query = $pdo->query("
        SELECT brand as brand_name, COUNT(*) as product_count FROM products WHERE status = 'active' AND brand IS NOT NULL GROUP BY brand
        UNION ALL
        SELECT thuong_hieu as brand_name, COUNT(*) as product_count FROM san_pham_chinh WHERE trang_thai = 'hoat_dong' AND thuong_hieu IS NOT NULL GROUP BY thuong_hieu
        ORDER BY brand_name
    ");
    $brands = $brands_query->fetchAll();
} catch (Exception $e) {
    $brands = [];
}

// Get price range
$price_range = ['min_price' => 0, 'max_price' => 0];
try {
    $price_data = $pdo->query("
        SELECT 
            MIN(LEAST(
                COALESCE((SELECT MIN(COALESCE(sale_price, price)) FROM products WHERE status = 'active'), 999999999),
                COALESCE((SELECT MIN(COALESCE(gia_khuyen_mai, gia_goc)) FROM san_pham_chinh WHERE trang_thai = 'hoat_dong'), 999999999)
            )) as min_price,
            MAX(GREATEST(
                COALESCE((SELECT MAX(COALESCE(sale_price, price)) FROM products WHERE status = 'active'), 0),
                COALESCE((SELECT MAX(COALESCE(gia_khuyen_mai, gia_goc)) FROM san_pham_chinh WHERE trang_thai = 'hoat_dong'), 0)
            )) as max_price
    ")->fetch();
    
    if ($price_data) {
        $price_range = $price_data;
    }
} catch (Exception $e) {
    // Keep default values
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

function getProductUrl($product) {
    if (!empty($product['slug'])) {
        return "product_detail.php?slug=" . urlencode($product['slug']);
    } else {
        return "product_detail.php?id=" . $product['id'];
    }
}

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
        
        .source-badge {
            position: absolute;
            top: 10px;
            left: 10px;
            z-index: 1;
            font-size: 10px;
        }
    </style>
</head>
<body>
    <?php include 'includes/header.php'; ?>
    
    <div class="container py-4">
        <!-- 🔧 Debug Info -->
        <div class="debug-info">
            <strong>🔧 FIXED VERSION - Unified Products Display</strong><br>
            Total products found: <?= $total_products ?><br>
            Current page: <?= $page ?> / <?= $total_pages ?><br>
            Search: "<?= htmlspecialchars($search) ?>"<br>
            Category: <?= $category ?><br>
            Data sources: Both 'products' and 'san_pham_chinh' tables
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
                    <a href="products_fixed.php" class="btn btn-sm btn-outline-secondary">
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
                                    <?= htmlspecialchars($cat['name']) ?>
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
                                <option value="<?= updateUrlParam('brand', $b['brand_name']) ?>" 
                                        <?= $brand === $b['brand_name'] ? 'selected' : '' ?>>
                                    <?= htmlspecialchars($b['brand_name']) ?> (<?= $b['product_count'] ?>)
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
                                        <!-- Source badge -->
                                        <span class="badge bg-info source-badge">
                                            <?= $product['source_table'] === 'products' ? 'EN' : 'VN' ?>
                                        </span>
                                        
                                        <?php 
                                        $sale_price = $product['sale_price'];
                                        $original_price = $product['price'];
                                        $discount_percent = 0;
                                        if ($sale_price && $sale_price < $original_price) {
                                            $discount_percent = round((($original_price - $sale_price) / $original_price) * 100);
                                        }
                                        ?>
                                        
                                        <?php if ($discount_percent > 0): ?>
                                            <span class="badge bg-danger badge-sale">-<?= $discount_percent ?>%</span>
                                        <?php endif; ?>
                                        
                                        <?php if ($product['featured']): ?>
                                            <span class="badge bg-success position-absolute" style="top: 30px; left: 10px; z-index: 1;">Nổi bật</span>
                                        <?php endif; ?>
                                        
                                        <a href="<?= getProductUrl($product) ?>">
                                            <img src="<?= getImageUrl($product['image']) ?>" 
                                                 class="card-img-top product-image" 
                                                 alt="<?= htmlspecialchars($product['name']) ?>"
                                                 loading="lazy"
                                                 onerror="this.src='/tktshop/uploads/products/no-image.jpg'">
                                        </a>
                                    </div>
                                    
                                    <div class="card-body">
                                        <h6 class="card-title mb-2">
                                            <a href="<?= getProductUrl($product) ?>" 
                                               class="text-decoration-none text-dark">
                                                <?= htmlspecialchars($product['name']) ?>
                                            </a>
                                        </h6>
                                        
                                        <?php if ($product['brand']): ?>
                                        <p class="text-muted small mb-2">
                                            <i class="fas fa-tag"></i> <?= htmlspecialchars($product['brand']) ?>
                                        </p>
                                        <?php endif; ?>
                                        
                                        <?php if ($product['description']): ?>
                                            <p class="card-text small text-muted mb-3">
                                                <?= mb_substr(htmlspecialchars($product['description']), 0, 80) ?>...
                                            </p>
                                        <?php endif; ?>
                                        
                                        <!-- Rating -->
                                        <?php if ($product['rating_average'] > 0): ?>
                                            <div class="mb-2">
                                                <?= displayStars(round($product['rating_average'])) ?>
                                                <span class="text-muted small">
                                                    (<?= $product['rating_count'] ?: 0 ?>)
                                                </span>
                                            </div>
                                        <?php endif; ?>
                                        
                                        <!-- Price -->
                                        <div class="d-flex align-items-center justify-content-between">
                                            <div class="price">
                                                <?php if ($sale_price && $sale_price < $original_price): ?>
                                                    <span class="price-sale h6 mb-0"><?= formatPrice($sale_price) ?></span>
                                                    <br>
                                                    <small class="price-original"><?= formatPrice($original_price) ?></small>
                                                <?php else: ?>
                                                    <span class="h6 mb-0"><?= formatPrice($original_price) ?></span>
                                                <?php endif; ?>
                                            </div>
                                            
                                            <!-- Stock Status -->
                                            <?php if ($product['source_table'] === 'products' && $product['stock_quantity'] <= 5): ?>
                                                <small class="text-danger">
                                                    <i class="fas fa-exclamation-triangle"></i> 
                                                    Còn <?= $product['stock_quantity'] ?>
                                                </small>
                                            <?php endif; ?>
                                        </div>
                                        
                                        <!-- Actions -->
                                        <div class="mt-3 d-grid gap-2">
                                            <a href="<?= getProductUrl($product) ?>" class="btn btn-primary btn-sm">
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
                        <a href="products_fixed.php" class="btn btn-primary">
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
            
            console.log('🔧 TKT Shop Products - FIXED VERSION loaded successfully');
            console.log('📊 Total products found:', <?= $total_products ?>);
            console.log('📊 Products from both tables: products + san_pham_chinh');
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