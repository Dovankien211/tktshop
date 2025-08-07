<?php
/**
 * Danh sách sản phẩm - Bộ lọc đầy đủ + phân trang + sắp xếp + tìm kiếm
 * Chức năng: Lọc theo danh mục, thương hiệu, giá, size, màu sắc, đánh giá
 */

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../config/config.php';

// Lấy tham số tìm kiếm và lọc
$search = trim($_GET['search'] ?? '');
$category = (int)($_GET['category'] ?? 0);
$brand = trim($_GET['brand'] ?? '');
$min_price = (int)($_GET['min_price'] ?? 0);
$max_price = (int)($_GET['max_price'] ?? 0);
$size = trim($_GET['size'] ?? '');
$color = (int)($_GET['color'] ?? 0);
$rating = (int)($_GET['rating'] ?? 0);
$featured = isset($_GET['featured']) ? 1 : 0;
$new = isset($_GET['new']) ? 1 : 0;
$sale = isset($_GET['sale']) ? 1 : 0;

// Tham số sắp xếp và phân trang
$sort = $_GET['sort'] ?? 'newest';
$page = max(1, (int)($_GET['page'] ?? 1));
$limit = 12;
$offset = ($page - 1) * $limit;

// Xây dựng câu truy vấn
$where_conditions = ["sp.trang_thai = 'hoat_dong'"];
$params = [];

// Tìm kiếm
if (!empty($search)) {
    $where_conditions[] = "(sp.ten_san_pham LIKE ? OR sp.mo_ta_ngan LIKE ? OR sp.thuong_hieu LIKE ?)";
    $params[] = "%$search%";
    $params[] = "%$search%";
    $params[] = "%$search%";
}

// Lọc theo danh mục
if ($category > 0) {
    $where_conditions[] = "sp.danh_muc_id = ?";
    $params[] = $category;
}

// Lọc theo thương hiệu
if (!empty($brand)) {
    $where_conditions[] = "sp.thuong_hieu = ?";
    $params[] = $brand;
}

// Lọc theo giá
if ($min_price > 0) {
    $where_conditions[] = "COALESCE(sp.gia_khuyen_mai, sp.gia_goc) >= ?";
    $params[] = $min_price;
}
if ($max_price > 0) {
    $where_conditions[] = "COALESCE(sp.gia_khuyen_mai, sp.gia_goc) <= ?";
    $params[] = $max_price;
}

// Lọc sản phẩm đặc biệt
if ($featured) {
    $where_conditions[] = "sp.san_pham_noi_bat = 1";
}
if ($new) {
    $where_conditions[] = "sp.san_pham_moi = 1";
}
if ($sale) {
    $where_conditions[] = "sp.gia_khuyen_mai IS NOT NULL AND sp.gia_khuyen_mai < sp.gia_goc";
}

// Lọc theo đánh giá
if ($rating > 0) {
    $where_conditions[] = "sp.diem_danh_gia_tb >= ?";
    $params[] = $rating;
}

// Lọc theo size và màu sắc (cần join với biến thể)
$join_variant = false;
if (!empty($size) || $color > 0) {
    $join_variant = true;
    if (!empty($size)) {
        $where_conditions[] = "kc.kich_co = ?";
        $params[] = $size;
    }
    if ($color > 0) {
        $where_conditions[] = "ms.id = ?";
        $params[] = $color;
    }
}

// Sắp xếp
$order_clause = "sp.ngay_tao DESC";
switch ($sort) {
    case 'price_asc':
        $order_clause = "COALESCE(sp.gia_khuyen_mai, sp.gia_goc) ASC";
        break;
    case 'price_desc':
        $order_clause = "COALESCE(sp.gia_khuyen_mai, sp.gia_goc) DESC";
        break;
    case 'name_asc':
        $order_clause = "sp.ten_san_pham ASC";
        break;
    case 'name_desc':
        $order_clause = "sp.ten_san_pham DESC";
        break;
    case 'rating':
        $order_clause = "sp.diem_danh_gia_tb DESC, sp.so_luong_danh_gia DESC";
        break;
    case 'popular':
        $order_clause = "sp.luot_xem DESC, sp.so_luong_ban DESC";
        break;
    case 'newest':
    default:
        $order_clause = "sp.ngay_tao DESC";
        break;
}

// Xây dựng câu SQL chính
$base_sql = "
    SELECT sp.*, dm.ten_danh_muc,
           COALESCE(sp.gia_khuyen_mai, sp.gia_goc) as gia_hien_tai,
           MIN(bsp.gia_ban) as gia_thap_nhat,
           MAX(bsp.gia_ban) as gia_cao_nhat,
           SUM(bsp.so_luong_ton_kho) as tong_ton_kho,
           CASE 
               WHEN sp.gia_khuyen_mai IS NOT NULL AND sp.gia_khuyen_mai < sp.gia_goc 
               THEN ROUND(((sp.gia_goc - sp.gia_khuyen_mai) / sp.gia_goc) * 100, 0)
               ELSE 0
           END as phan_tram_giam
    FROM san_pham_chinh sp
    LEFT JOIN danh_muc_giay dm ON sp.danh_muc_id = dm.id
    LEFT JOIN bien_the_san_pham bsp ON sp.id = bsp.san_pham_id AND bsp.trang_thai = 'hoat_dong'
";

if ($join_variant) {
    $base_sql .= "
        LEFT JOIN kich_co kc ON bsp.kich_co_id = kc.id
        LEFT JOIN mau_sac ms ON bsp.mau_sac_id = ms.id
    ";
}

$where_clause = implode(" AND ", $where_conditions);
$main_sql = $base_sql . " WHERE " . $where_clause . " GROUP BY sp.id HAVING tong_ton_kho > 0 ORDER BY " . $order_clause;

// Đếm tổng số sản phẩm
$count_sql = "SELECT COUNT(DISTINCT sp.id) FROM san_pham_chinh sp LEFT JOIN danh_muc_giay dm ON sp.danh_muc_id = dm.id LEFT JOIN bien_the_san_pham bsp ON sp.id = bsp.san_pham_id AND bsp.trang_thai = 'hoat_dong'";
if ($join_variant) {
    $count_sql .= " LEFT JOIN kich_co kc ON bsp.kich_co_id = kc.id LEFT JOIN mau_sac ms ON bsp.mau_sac_id = ms.id";
}
$count_sql .= " WHERE " . $where_clause . " AND bsp.so_luong_ton_kho > 0";

$count_stmt = $pdo->prepare($count_sql);
$count_stmt->execute($params);
$total_products = $count_stmt->fetchColumn();
$total_pages = ceil($total_products / $limit);

// Lấy sản phẩm với phân trang
$stmt = $pdo->prepare($main_sql . " LIMIT ? OFFSET ?");
$stmt->execute([...$params, $limit, $offset]);
$products = $stmt->fetchAll();

// Lấy dữ liệu cho bộ lọc
$categories = $pdo->query("
    SELECT dm.*, COUNT(sp.id) as so_san_pham
    FROM danh_muc_giay dm
    LEFT JOIN san_pham_chinh sp ON dm.id = sp.danh_muc_id AND sp.trang_thai = 'hoat_dong'
    WHERE dm.trang_thai = 'hoat_dong'
    GROUP BY dm.id
    HAVING so_san_pham > 0
    ORDER BY dm.thu_tu_hien_thi ASC
")->fetchAll();

$brands = $pdo->query("
    SELECT thuong_hieu, COUNT(*) as so_san_pham
    FROM san_pham_chinh 
    WHERE trang_thai = 'hoat_dong' AND thuong_hieu IS NOT NULL
    GROUP BY thuong_hieu
    ORDER BY thuong_hieu ASC
")->fetchAll();

$sizes = $pdo->query("
    SELECT kc.*, COUNT(DISTINCT sp.id) as so_san_pham
    FROM kich_co kc
    JOIN bien_the_san_pham bsp ON kc.id = bsp.kich_co_id
    JOIN san_pham_chinh sp ON bsp.san_pham_id = sp.id
    WHERE kc.trang_thai = 'hoat_dong' AND bsp.trang_thai = 'hoat_dong' AND sp.trang_thai = 'hoat_dong'
    GROUP BY kc.id
    ORDER BY kc.thu_tu_sap_xep ASC
")->fetchAll();

$colors = $pdo->query("
    SELECT ms.*, COUNT(DISTINCT sp.id) as so_san_pham
    FROM mau_sac ms
    JOIN bien_the_san_pham bsp ON ms.id = bsp.mau_sac_id
    JOIN san_pham_chinh sp ON bsp.san_pham_id = sp.id
    WHERE ms.trang_thai = 'hoat_dong' AND bsp.trang_thai = 'hoat_dong' AND sp.trang_thai = 'hoat_dong'
    GROUP BY ms.id
    ORDER BY ms.thu_tu_hien_thi ASC
")->fetchAll();

// Lấy khoảng giá
$price_range = $pdo->query("
    SELECT 
        MIN(COALESCE(gia_khuyen_mai, gia_goc)) as min_price,
        MAX(COALESCE(gia_khuyen_mai, gia_goc)) as max_price
    FROM san_pham_chinh 
    WHERE trang_thai = 'hoat_dong'
")->fetch();

// Hàm helper để cập nhật URL
function updateUrlParam($key, $value) {
    $params = $_GET;
    if (empty($value)) {
        unset($params[$key]);
    } else {
        $params[$key] = $value;
    }
    unset($params['page']); // Reset trang khi lọc
    return '?' . http_build_query($params);
}

// Hàm format giá tiền
function formatPrice($price) {
    return number_format($price, 0, ',', '.') . 'đ';
}

// Hàm hiển thị sao đánh giá
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
            max-height: calc(100vh - 40px);
            overflow-y: auto;
        }
        
        .filter-section {
            margin-bottom: 25px;
            padding-bottom: 20px;
            border-bottom: 1px solid #dee2e6;
        }
        
        .filter-section:last-child {
            border-bottom: none;
        }
        
        .color-option {
            width: 30px;
            height: 30px;
            border-radius: 50%;
            border: 2px solid #ccc;
            cursor: pointer;
            margin: 2px;
            display: inline-block;
            transition: all 0.3s;
        }
        
        .color-option:hover,
        .color-option.selected {
            border-color: #007bff;
            transform: scale(1.1);
        }
        
        .size-option {
            width: 45px;
            height: 35px;
            border: 1px solid #ccc;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            margin: 2px;
            cursor: pointer;
            border-radius: 4px;
            transition: all 0.3s;
        }
        
        .size-option:hover,
        .size-option.selected {
            border-color: #007bff;
            background-color: #007bff;
            color: white;
        }
        
        .rating-filter {
            cursor: pointer;
            padding: 5px 0;
            transition: all 0.3s;
        }
        
        .rating-filter:hover {
            color: #007bff;
        }
        
        .sort-dropdown {
            min-width: 200px;
        }
        
        .empty-results {
            text-align: center;
            padding: 60px 20px;
        }
        
        .filter-clear-btn {
            background: #dc3545;
            color: white;
            border: none;
            padding: 5px 10px;
            border-radius: 4px;
            font-size: 0.8em;
        }
        
        .price-range-input {
            width: 80px;
            display: inline-block;
        }
        
        @media (max-width: 768px) {
            .filter-sidebar {
                position: static;
                margin-bottom: 20px;
            }
            
            .product-image {
                height: 200px;
            }
        }
    </style>
</head>
<body>
    <!-- Header -->
    <?php include __DIR__ . '/includes/header.php'; ?>
    
    <div class="container py-4">
        <!-- Breadcrumb -->
        <nav aria-label="breadcrumb" class="mb-4">
            <ol class="breadcrumb">
                <li class="breadcrumb-item"><a href="/tktshop/">Trang chủ</a></li>
                <?php if ($category > 0): ?>
                    <?php
                    $cat_info = array_filter($categories, fn($c) => $c['id'] == $category)[0] ?? null;
                    if ($cat_info):
                    ?>
                        <li class="breadcrumb-item active"><?= htmlspecialchars($cat_info['ten_danh_muc']) ?></li>
                    <?php endif; ?>
                <?php else: ?>
                    <li class="breadcrumb-item active">Sản phẩm</li>
                <?php endif; ?>
            </ol>
        </nav>

        <!-- Page Header -->
        <div class="row mb-4">
            <div class="col-12">
                <h1 class="h3 mb-3">
                    <?php if (!empty($search)): ?>
                        Kết quả tìm kiếm cho: "<strong><?= htmlspecialchars($search) ?></strong>"
                    <?php elseif ($category > 0 && isset($cat_info)): ?>
                        <?= htmlspecialchars($cat_info['ten_danh_muc']) ?>
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
                    <a href="<?= updateUrlParam('new', $new ? '' : '1') ?>" 
                       class="btn btn-sm <?= $new ? 'btn-success' : 'btn-outline-success' ?>">
                        <i class="fas fa-sparkles"></i> Mới
                    </a>
                    <a href="<?= updateUrlParam('sale', $sale ? '' : '1') ?>" 
                       class="btn btn-sm <?= $sale ? 'btn-danger' : 'btn-outline-danger' ?>">
                        <i class="fas fa-tags"></i> Giảm giá
                    </a>
                </div>
            </div>
        </div>

        <div class="row">
            <!-- Sidebar Filters -->
            <div class="col-lg-3 col-md-4">
                <div class="filter-sidebar">
                    <div class="d-flex justify-content-between align-items-center mb-3">
                        <h5 class="mb-0">Bộ lọc</h5>
                        <?php if (!empty(array_filter($_GET))): ?>
                            <a href="products.php" class="filter-clear-btn">
                                <i class="fas fa-times"></i> Xóa bộ lọc
                            </a>
                        <?php endif; ?>
                    </div>

                    <!-- Search -->
                    <div class="filter-section">
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
                    <div class="filter-section">
                        <h6>Danh mục</h6>
                        <div class="list-group list-group-flush">
                            <a href="<?= updateUrlParam('category', '') ?>" 
                               class="list-group-item list-group-item-action border-0 px-0 py-2 <?= $category == 0 ? 'active' : '' ?>">
                                Tất cả
                            </a>
                            <?php foreach ($categories as $cat): ?>
                                <a href="<?= updateUrlParam('category', $cat['id']) ?>" 
                                   class="list-group-item list-group-item-action border-0 px-0 py-2 d-flex justify-content-between <?= $category == $cat['id'] ? 'active' : '' ?>">
                                    <?= htmlspecialchars($cat['ten_danh_muc']) ?>
                                    <span class="badge bg-secondary"><?= $cat['so_san_pham'] ?></span>
                                </a>
                            <?php endforeach; ?>
                        </div>
                    </div>
                    <?php endif; ?>

                    <!-- Brands -->
                    <?php if (!empty($brands)): ?>
                    <div class="filter-section">
                        <h6>Thương hiệu</h6>
                        <select class="form-select form-select-sm" onchange="window.location.href = this.value">
                            <option value="<?= updateUrlParam('brand', '') ?>">Tất cả thương hiệu</option>
                            <?php foreach ($brands as $b): ?>
                                <option value="<?= updateUrlParam('brand', $b['thuong_hieu']) ?>" 
                                        <?= $brand === $b['thuong_hieu'] ? 'selected' : '' ?>>
                                    <?= htmlspecialchars($b['thuong_hieu']) ?> (<?= $b['so_san_pham'] ?>)
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <?php endif; ?>

                    <!-- Price Range -->
                    <?php if ($price_range): ?>
                    <div class="filter-section">
                        <h6>Khoảng giá</h6>
                        <form method="GET" class="d-flex align-items-center gap-2">
                            <?php foreach ($_GET as $key => $value): ?>
                                <?php if (!in_array($key, ['min_price', 'max_price'])): ?>
                                    <input type="hidden" name="<?= htmlspecialchars($key) ?>" value="<?= htmlspecialchars($value) ?>">
                                <?php endif; ?>
                            <?php endforeach; ?>
                            <input type="number" name="min_price" class="form-control form-control-sm price-range-input" 
                                   placeholder="Từ" value="<?= $min_price ?: '' ?>" min="0">
                            <span>-</span>
                            <input type="number" name="max_price" class="form-control form-control-sm price-range-input" 
                                   placeholder="Đến" value="<?= $max_price ?: '' ?>" min="0">
                            <button type="submit" class="btn btn-primary btn-sm">OK</button>
                        </form>
                        <small class="text-muted">
                            Từ <?= formatPrice($price_range['min_price']) ?> đến <?= formatPrice($price_range['max_price']) ?>
                        </small>
                    </div>
                    <?php endif; ?>

                    <!-- Sizes -->
                    <?php if (!empty($sizes)): ?>
                    <div class="filter-section">
                        <h6>Kích cỡ</h6>
                        <div class="d-flex flex-wrap">
                            <?php foreach ($sizes as $s): ?>
                                <a href="<?= updateUrlParam('size', $size === $s['kich_co'] ? '' : $s['kich_co']) ?>" 
                                   class="size-option <?= $size === $s['kich_co'] ? 'selected' : '' ?>" 
                                   title="<?= $s['so_san_pham'] ?> sản phẩm">
                                    <?= htmlspecialchars($s['kich_co']) ?>
                                </a>
                            <?php endforeach; ?>
                        </div>
                    </div>
                    <?php endif; ?>

                    <!-- Colors -->
                    <?php if (!empty($colors)): ?>
                    <div class="filter-section">
                        <h6>Màu sắc</h6>
                        <div class="d-flex flex-wrap">
                            <?php foreach ($colors as $c): ?>
                                <a href="<?= updateUrlParam('color', $color === $c['id'] ? '' : $c['id']) ?>" 
                                   class="color-option <?= $color === $c['id'] ? 'selected' : '' ?>" 
                                   style="background-color: <?= htmlspecialchars($c['ma_mau']) ?>"
                                   title="<?= htmlspecialchars($c['ten_mau']) ?> (<?= $c['so_san_pham'] ?> sản phẩm)">
                                </a>
                            <?php endforeach; ?>
                        </div>
                    </div>
                    <?php endif; ?>

                    <!-- Rating Filter -->
                    <div class="filter-section">
                        <h6>Đánh giá</h6>
                        <?php for ($r = 5; $r >= 1; $r--): ?>
                            <div class="rating-filter <?= $rating === $r ? 'text-primary fw-bold' : '' ?>" 
                                 onclick="window.location.href='<?= updateUrlParam('rating', $rating === $r ? '' : $r) ?>'">
                                <?= displayStars($r) ?> từ <?= $r ?> sao trở lên
                            </div>
                        <?php endfor; ?>
                    </div>
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
                        <button class="btn btn-outline-secondary dropdown-toggle sort-dropdown" type="button" 
                                data-bs-toggle="dropdown">
                            Sắp xếp: 
                            <?php
                            $sort_labels = [
                                'newest' => 'Mới nhất',
                                'price_asc' => 'Giá thấp đến cao',
                                'price_desc' => 'Giá cao đến thấp',
                                'name_asc' => 'Tên A-Z',
                                'name_desc' => 'Tên Z-A',
                                'rating' => 'Đánh giá cao nhất',
                                'popular' => 'Phổ biến nhất'
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
                                        <?php if ($product['phan_tram_giam'] > 0): ?>
                                            <span class="badge bg-danger badge-sale">-<?= $product['phan_tram_giam'] ?>%</span>
                                        <?php endif; ?>
                                        
                                        <?php if ($product['san_pham_moi']): ?>
                                            <span class="badge bg-success position-absolute" style="top: 10px; left: 10px; z-index: 1;">Mới</span>
                                        <?php endif; ?>
                                        
                                        <img src="<?= !empty($product['hinh_anh']) ? htmlspecialchars($product['hinh_anh']) : '/tktshop/assets/images/no-image.jpg' ?>" 
                                             class="card-img-top product-image" 
                                             alt="<?= htmlspecialchars($product['ten_san_pham']) ?>"
                                             loading="lazy">
                                        
                                        <div class="position-absolute bottom-0 start-0 end-0 p-3 opacity-0 transition-opacity" style="background: linear-gradient(transparent, rgba(0,0,0,0.7)); transition: opacity 0.3s;">
                                            <div class="d-flex gap-2">
                                                <a href="product_detail.php?id=<?= $product['id'] ?>" class="btn btn-primary btn-sm flex-fill">
                                                    <i class="fas fa-eye"></i> Xem
                                                </a>
                                                <button class="btn btn-warning btn-sm" onclick="addToWishlist(<?= $product['id'] ?>)">
                                                    <i class="fas fa-heart"></i>
                                                </button>
                                                <button class="btn btn-success btn-sm" onclick="addToCart(<?= $product['id'] ?>)">
                                                    <i class="fas fa-shopping-cart"></i>
                                                </button>
                                            </div>
                                        </div>
                                    </div>
                                    
                                    <div class="card-body">
                                        <h6 class="card-title mb-2">
                                            <a href="product_detail.php?id=<?= $product['id'] ?>" 
                                               class="text-decoration-none text-dark">
                                                <?= htmlspecialchars($product['ten_san_pham']) ?>
                                            </a>
                                        </h6>
                                        
                                        <p class="text-muted small mb-2">
                                            <i class="fas fa-tag"></i> <?= htmlspecialchars($product['thuong_hieu']) ?>
                                        </p>
                                        
                                        <?php if ($product['mo_ta_ngan']): ?>
                                            <p class="card-text small text-muted mb-3">
                                                <?= mb_substr(htmlspecialchars($product['mo_ta_ngan']), 0, 80) ?>...
                                            </p>
                                        <?php endif; ?>
                                        
                                        <!-- Rating -->
                                        <?php if ($product['diem_danh_gia_tb'] > 0): ?>
                                            <div class="mb-2">
                                                <?= displayStars(round($product['diem_danh_gia_tb'])) ?>
                                                <span class="text-muted small">
                                                    (<?= $product['so_luong_danh_gia'] ?: 0 ?> đánh giá)
                                                </span>
                                            </div>
                                        <?php endif; ?>
                                        
                                        <!-- Price -->
                                        <div class="d-flex align-items-center justify-content-between">
                                            <div class="price">
                                                <?php if ($product['gia_khuyen_mai'] && $product['gia_khuyen_mai'] < $product['gia_goc']): ?>
                                                    <span class="price-sale h6 mb-0"><?= formatPrice($product['gia_khuyen_mai']) ?></span>
                                                    <br>
                                                    <small class="price-original"><?= formatPrice($product['gia_goc']) ?></small>
                                                <?php else: ?>
                                                    <span class="h6 mb-0"><?= formatPrice($product['gia_goc']) ?></span>
                                                <?php endif; ?>
                                            </div>
                                            
                                            <?php if ($product['tong_ton_kho'] <= 5): ?>
                                                <small class="text-danger">
                                                    <i class="fas fa-exclamation-triangle"></i> 
                                                    Còn <?= $product['tong_ton_kho'] ?>
                                                </small>
                                            <?php endif; ?>
                                        </div>
                                        
                                        <!-- Special badges -->
                                        <div class="mt-2">
                                            <?php if ($product['san_pham_noi_bat']): ?>
                                                <span class="badge bg-warning text-dark me-1">
                                                    <i class="fas fa-star"></i> Nổi bật
                                                </span>
                                            <?php endif; ?>
                                            
                                            <?php if ($product['luot_xem'] > 1000): ?>
                                                <span class="badge bg-info text-dark me-1">
                                                    <i class="fas fa-fire"></i> Hot
                                                </span>
                                            <?php endif; ?>
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
                    <div class="empty-results">
                        <i class="fas fa-search fa-3x text-muted mb-3"></i>
                        <h4>Không tìm thấy sản phẩm</h4>
                        <p class="text-muted">
                            Không có sản phẩm nào phù hợp với tiêu chí tìm kiếm của bạn.
                        </p>
                        <a href="products.php" class="btn btn-primary">
                            <i class="fas fa-arrow-left"></i> Xem tất cả sản phẩm
                        </a>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <!-- Footer -->
    <?php include __DIR__ . '/includes/footer.php'; ?>

    <!-- Scripts -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        // Add hover effect for product cards
        document.addEventListener('DOMContentLoaded', function() {
            const productCards = document.querySelectorAll('.product-card');
            
            productCards.forEach(card => {
                const hoverActions = card.querySelector('.position-absolute.bottom-0');
                
                card.addEventListener('mouseenter', function() {
                    if (hoverActions) {
                        hoverActions.classList.remove('opacity-0');
                        hoverActions.classList.add('opacity-100');
                    }
                });
                
                card.addEventListener('mouseleave', function() {
                    if (hoverActions) {
                        hoverActions.classList.remove('opacity-100');
                        hoverActions.classList.add('opacity-0');
                    }
                });
            });
        });

        // Add to cart function
        function addToCart(productId) {
            fetch('/tktshop/ajax/add_to_cart.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                },
                body: JSON.stringify({
                    product_id: productId,
                    quantity: 1
                })
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    // Show success message
                    showToast('Đã thêm sản phẩm vào giỏ hàng!', 'success');
                    // Update cart counter if exists
                    updateCartCounter();
                } else {
                    showToast(data.message || 'Có lỗi xảy ra!', 'error');
                }
            })
            .catch(error => {
                console.error('Error:', error);
                showToast('Có lỗi xảy ra!', 'error');
            });
        }

        // Add to wishlist function
        function addToWishlist(productId) {
            fetch('/tktshop/ajax/add_to_wishlist.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                },
                body: JSON.stringify({
                    product_id: productId
                })
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    showToast('Đã thêm vào danh sách yêu thích!', 'success');
                } else {
                    showToast(data.message || 'Có lỗi xảy ra!', 'error');
                }
            })
            .catch(error => {
                console.error('Error:', error);
                showToast('Có lỗi xảy ra!', 'error');
            });
        }

        // Show toast notification
        function showToast(message, type = 'info') {
            // Create toast container if it doesn't exist
            let toastContainer = document.getElementById('toast-container');
            if (!toastContainer) {
                toastContainer = document.createElement('div');
                toastContainer.id = 'toast-container';
                toastContainer.className = 'position-fixed top-0 end-0 p-3';
                toastContainer.style.zIndex = '9999';
                document.body.appendChild(toastContainer);
            }

            // Create toast element
            const toastId = 'toast-' + Date.now();
            const toastBg = type === 'success' ? 'bg-success' : type === 'error' ? 'bg-danger' : 'bg-info';
            
            const toastHtml = `
                <div id="${toastId}" class="toast ${toastBg} text-white" role="alert">
                    <div class="toast-body">
                        <i class="fas fa-${type === 'success' ? 'check' : type === 'error' ? 'times' : 'info'}-circle me-2"></i>
                        ${message}
                    </div>
                </div>
            `;
            
            toastContainer.insertAdjacentHTML('beforeend', toastHtml);
            
            // Show toast
            const toastElement = document.getElementById(toastId);
            const toast = new bootstrap.Toast(toastElement, {
                autohide: true,
                delay: 3000
            });
            toast.show();
            
            // Remove toast after it's hidden
            toastElement.addEventListener('hidden.bs.toast', function() {
                toastElement.remove();
            });
        }

        // Update cart counter
        function updateCartCounter() {
            fetch('/tktshop/ajax/get_cart_count.php')
            .then(response => response.json())
            .then(data => {
                const cartCounter = document.querySelector('.cart-counter');
                if (cartCounter && data.count !== undefined) {
                    cartCounter.textContent = data.count;
                    if (data.count > 0) {
                        cartCounter.style.display = 'inline';
                    }
                }
            })
            .catch(error => console.error('Error updating cart counter:', error));
        }

        // Filter form auto-submit for better UX
        document.addEventListener('DOMContentLoaded', function() {
            const priceInputs = document.querySelectorAll('input[name="min_price"], input[name="max_price"]');
            
            priceInputs.forEach(input => {
                let timeout;
                input.addEventListener('input', function() {
                    clearTimeout(timeout);
                    timeout = setTimeout(() => {
                        // Auto submit after 1 second of no typing
                        this.closest('form').submit();
                    }, 1000);
                });
            });
        });

        // Smooth scroll to top when changing pages
        if (window.location.hash === '') {
            window.scrollTo({
                top: 0,
                behavior: 'smooth'
            });
        }
    </script>

    <style>
        .product-card .position-absolute.bottom-0 {
            transition: opacity 0.3s ease;
        }
        
        .toast {
            min-width: 250px;
        }
        
        .pagination .page-link {
            border-radius: 8px;
            margin: 0 2px;
            border: 1px solid #dee2e6;
        }
        
        .pagination .page-item.active .page-link {
            background-color: #007bff;
            border-color: #007bff;
        }
        
        .filter-sidebar::-webkit-scrollbar {
            width: 6px;
        }
        
        .filter-sidebar::-webkit-scrollbar-track {
            background: #f1f1f1;
            border-radius: 3px;
        }
        
        .filter-sidebar::-webkit-scrollbar-thumb {
            background: #888;
            border-radius: 3px;
        }
        
        .filter-sidebar::-webkit-scrollbar-thumb:hover {
            background: #555;
        }
    </style>
</body>
</html>