<?php
/**
 * customer/products.php - Fixed Version
 * Danh sách sản phẩm với cấu trúc bảng đúng
 */

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../config/config.php';

// Lấy tham số tìm kiếm và lọc
$search = trim($_GET['search'] ?? '');
$category = (int)($_GET['category'] ?? 0);
$brand = trim($_GET['brand'] ?? '');
$min_price = (int)($_GET['min_price'] ?? 0);
$max_price = (int)($_GET['max_price'] ?? 0);
$featured = isset($_GET['featured']) ? 1 : 0;
$sale = isset($_GET['sale']) ? 1 : 0;

// Tham số sắp xếp và phân trang
$sort = $_GET['sort'] ?? 'newest';
$page = max(1, (int)($_GET['page'] ?? 1));
$limit = 12;
$offset = ($page - 1) * $limit;

// Xây dựng câu truy vấn với tên bảng đúng
$where_conditions = ["p.status = 'active'"];
$params = [];

// Tìm kiếm
if (!empty($search)) {
    $where_conditions[] = "(p.name LIKE ? OR p.description LIKE ? OR p.brand LIKE ?)";
    $params[] = "%$search%";
    $params[] = "%$search%";
    $params[] = "%$search%";
}

// Lọc theo danh mục
if ($category > 0) {
    $where_conditions[] = "p.category_id = ?";
    $params[] = $category;
}

// Lọc theo thương hiệu
if (!empty($brand)) {
    $where_conditions[] = "p.brand = ?";
    $params[] = $brand;
}

// Lọc theo giá
if ($min_price > 0) {
    $where_conditions[] = "COALESCE(p.sale_price, p.price) >= ?";
    $params[] = $min_price;
}
if ($max_price > 0) {
    $where_conditions[] = "COALESCE(p.sale_price, p.price) <= ?";
    $params[] = $max_price;
}

// Lọc sản phẩm đặc biệt
if ($featured) {
    $where_conditions[] = "p.is_featured = 1";
}
if ($sale) {
    $where_conditions[] = "p.sale_price IS NOT NULL AND p.sale_price < p.price";
}

// Sắp xếp
$order_clause = "p.created_at DESC";
switch ($sort) {
    case 'price_asc':
        $order_clause = "COALESCE(p.sale_price, p.price) ASC";
        break;
    case 'price_desc':
        $order_clause = "COALESCE(p.sale_price, p.price) DESC";
        break;
    case 'name_asc':
        $order_clause = "p.name ASC";
        break;
    case 'name_desc':
        $order_clause = "p.name DESC";
        break;
    case 'rating':
        $order_clause = "p.rating_average DESC, p.rating_count DESC";
        break;
    case 'popular':
        $order_clause = "p.view_count DESC, p.sold_count DESC";
        break;
    case 'newest':
    default:
        $order_clause = "p.created_at DESC";
        break;
}

// Câu SQL chính
$base_sql = "
    SELECT p.*, c.name as category_name,
           COALESCE(p.sale_price, p.price) as current_price,
           CASE 
               WHEN p.sale_price IS NOT NULL AND p.sale_price < p.price 
               THEN ROUND(((p.price - p.sale_price) / p.price) * 100, 0)
               ELSE 0
           END as discount_percent
    FROM products p
    LEFT JOIN categories c ON p.category_id = c.id
";

$where_clause = implode(" AND ", $where_conditions);
$main_sql = $base_sql . " WHERE " . $where_clause . " AND p.stock_quantity > 0 ORDER BY " . $order_clause;

// Đếm tổng số sản phẩm
$count_sql = "SELECT COUNT(*) FROM products p LEFT JOIN categories c ON p.category_id = c.id WHERE " . $where_clause . " AND p.stock_quantity > 0";

try {
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
        SELECT c.*, COUNT(p.id) as product_count
        FROM categories c
        LEFT JOIN products p ON c.id = p.category_id AND p.status = 'active'
        WHERE c.status = 'active'
        GROUP BY c.id
        HAVING product_count > 0
        ORDER BY c.sort_order ASC
    ")->fetchAll();

    $brands = $pdo->query("
        SELECT brand, COUNT(*) as product_count
        FROM products 
        WHERE status = 'active' AND brand IS NOT NULL AND brand != ''
        GROUP BY brand
        ORDER BY brand ASC
    ")->fetchAll();

    // Lấy khoảng giá
    $price_range = $pdo->query("
        SELECT 
            MIN(COALESCE(sale_price, price)) as min_price,
            MAX(COALESCE(sale_price, price)) as max_price
        FROM products 
        WHERE status = 'active'
    ")->fetch();

} catch (Exception $e) {
    error_log("Database error in products.php: " . $e->getMessage());
    $products = [];
    $categories = [];
    $brands = [];
    $total_products = 0;
    $total_pages = 0;
    $price_range = ['min_price' => 0, 'max_price' => 0];
}

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
    return number_format($price, 0, ',', '.') . '₫';
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
    <title><?= !empty($search) ? "Tìm kiếm: $search" : "Sản phẩm" ?> - TKT Shop</title>
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
        
        .filter-section {
            margin-bottom: 25px;
            padding-bottom: 20px;
            border-bottom: 1px solid #dee2e6;
        }
        
        .filter-section:last-child {
            border-bottom: none;
        }
        
        .empty-results {
            text-align: center;
            padding: 60px 20px;
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
            <strong>🔍 Debug Info:</strong><br>
            Total products found: <?= $total_products ?><br>
            Current page: <?= $page ?> / <?= $total_pages ?><br>
            Search: "<?= htmlspecialchars($search) ?>"<br>
            Category: <?= $category ?><br>
            Active filters: <?= count(array_filter($_GET)) ?><br>
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
                                    <?= htmlspecialchars($cat['name']) ?>
                                    <span class="badge bg-secondary"><?= $cat['product_count'] ?></span>
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
                                <option value="<?= updateUrlParam('brand', $b['brand']) ?>" 
                                        <?= $brand === $b['brand'] ? 'selected' : '' ?>>
                                    <?= htmlspecialchars($b['brand']) ?> (<?= $b['product_count'] ?>)
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <?php endif; ?>

                    <!-- Price Range -->
                    <?php if ($price_range && $price_range['max_price'] > 0): ?>
                    <div class="filter-section">
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
                                'name_desc' => 'Tên Z-A'
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
                                        
                                        <?php if ($product['is_featured']): ?>
                                            <span class="badge bg-success position-absolute" style="top: 10px; left: 10px; z-index: 1;">Nổi bật</span>
                                        <?php endif; ?>
                                        
                                        <img src="<?= !empty($product['main_image']) ? '/tktshop/uploads/products/' . htmlspecialchars($product['main_image']) : '/tktshop/uploads/products/no-image.jpg' ?>" 
                                             class="card-img-top product-image" 
                                             alt="<?= htmlspecialchars($product['name']) ?>"
                                             loading="lazy"
                                             onerror="this.src='/tktshop/uploads/products/no-image.jpg'">
                                    </div>
                                    
                                    <div class="card-body">
                                        <h6 class="card-title mb-2">
                                            <a href="product_detail.php?id=<?= $product['id'] ?>" 
                                               class="text-decoration-none text-dark">
                                                <?= htmlspecialchars($product['name']) ?>
                                            </a>
                                        </h6>
                                        
                                        <?php if ($product['brand']): ?>
                                        <p class="text-muted small mb-2">
                                            <i class="fas fa-tag"></i> <?= htmlspecialchars($product['brand']) ?>
                                        </p>
                                        <?php endif; ?>
                                        
                                        <?php if ($product['short_description']): ?>
                                            <p class="card-text small text-muted mb-3">
                                                <?= mb_substr(htmlspecialchars($product['short_description']), 0, 80) ?>...
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
                                                <?php if ($product['sale_price'] && $product['sale_price'] < $product['price']): ?>
                                                    <span class="price-sale h6 mb-0"><?= formatPrice($product['sale_price']) ?></span>
                                                    <br>
                                                    <small class="price-original"><?= formatPrice($product['price']) ?></small>
                                                <?php else: ?>
                                                    <span class="h6 mb-0"><?= formatPrice($product['price']) ?></span>
                                                <?php endif; ?>
                                            </div>
                                            
                                            <?php if ($product['stock_quantity'] <= 5): ?>
                                                <small class="text-danger">
                                                    <i class="fas fa-exclamation-triangle"></i> 
                                                    Còn <?= $product['stock_quantity'] ?>
                                                </small>
                                            <?php endif; ?>
                                        </div>
                                        
                                        <!-- Actions -->
                                        <div class="mt-3 d-grid gap-2">
                                            <a href="product_detail.php?id=<?= $product['id'] ?>" class="btn btn-primary btn-sm">
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
                    <div class="empty-results">
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
        // Add hover effect for product cards
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
        });

        // Auto-submit price filter after typing stops
        document.addEventListener('DOMContentLoaded', function() {
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
        });

        // Smooth scroll to top when changing pages
        if (new URLSearchParams(window.location.search).has('page')) {
            window.scrollTo({
                top: 0,
                behavior: 'smooth'
            });
        }
    </script>
</body>
</html>