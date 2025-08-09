<?php
require_once '../config/database.php';
require_once '../config/config.php';

// Lấy tham số tìm kiếm và filter
$category_id = isset($_GET['category']) ? (int)$_GET['category'] : 0;
$search = isset($_GET['search']) ? trim($_GET['search']) : '';
$page = isset($_GET['page']) ? max(1, (int)$_GET['page']) : 1;
$limit = 12;
$offset = ($page - 1) * $limit;

// Xây dựng WHERE clause
$where_conditions = ["sp.trang_thai = 'hoat_dong'"];
$params = [];

if ($category_id > 0) {
    $where_conditions[] = "sp.danh_muc_id = ?";
    $params[] = $category_id;
}

if (!empty($search)) {
    $where_conditions[] = "(sp.ten_san_pham LIKE ? OR sp.thuong_hieu LIKE ?)";
    $search_param = "%$search%";
    $params[] = $search_param;
    $params[] = $search_param;
}

$where_clause = 'WHERE ' . implode(' AND ', $where_conditions);

// Lấy danh sách sản phẩm
$products = [];
$total_products = 0;

try {
    // Đếm tổng số sản phẩm
    $count_sql = "SELECT COUNT(*) FROM san_pham_chinh sp $where_clause";
    $count_stmt = $pdo->prepare($count_sql);
    $count_stmt->execute($params);
    $total_products = $count_stmt->fetchColumn();
    
    // Lấy sản phẩm cho trang hiện tại
    $sql = "SELECT sp.*, dm.ten_danh_muc 
            FROM san_pham_chinh sp 
            LEFT JOIN danh_muc_giay dm ON sp.danh_muc_id = dm.id 
            $where_clause 
            ORDER BY sp.ngay_tao DESC 
            LIMIT $limit OFFSET $offset";
    
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $products = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
} catch (Exception $e) {
    $error = "Lỗi: " . $e->getMessage();
}

// Lấy danh sách danh mục để filter
$categories = [];
try {
    $stmt = $pdo->query("SELECT * FROM danh_muc_giay WHERE trang_thai = 'hoat_dong' ORDER BY ten_danh_muc");
    $categories = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) {
    // Ignore error
}

$total_pages = ceil($total_products / $limit);
?>

<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Sản phẩm - TKT Shop</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <style>
        .product-card {
            transition: transform 0.3s ease, box-shadow 0.3s ease;
            height: 100%;
        }
        .product-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 8px 25px rgba(0,0,0,0.1);
        }
        .product-image {
            height: 250px;
            object-fit: cover;
            width: 100%;
        }
        .product-price {
            font-size: 1.25rem;
            font-weight: bold;
            color: #dc3545;
        }
        .product-price-old {
            font-size: 1rem;
            color: #6c757d;
            text-decoration: line-through;
        }
        .header-section {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            padding: 3rem 0;
        }
        .no-products {
            padding: 4rem 0;
            text-align: center;
        }
    </style>
</head>
<body>
    <!-- Header -->
    <div class="header-section">
        <div class="container">
            <div class="row align-items-center">
                <div class="col-md-6">
                    <h1 class="mb-2">🛒 TKT Shop</h1>
                    <p class="mb-0">Cửa hàng giày thể thao chính hãng</p>
                </div>
                <div class="col-md-6 text-end">
                    <a href="/tktshop/admin/" class="btn btn-light">
                        <i class="fas fa-cog"></i> Admin
                    </a>
                </div>
            </div>
        </div>
    </div>

    <div class="container my-5">
        <!-- Search & Filter -->
        <div class="row mb-4">
            <div class="col-12">
                <div class="card">
                    <div class="card-body">
                        <form method="GET" class="row g-3">
                            <div class="col-md-4">
                                <input type="text" class="form-control" name="search" 
                                       placeholder="Tìm kiếm sản phẩm..." value="<?= htmlspecialchars($search) ?>">
                            </div>
                            <div class="col-md-4">
                                <select class="form-select" name="category">
                                    <option value="">Tất cả danh mục</option>
                                    <?php foreach ($categories as $category): ?>
                                        <option value="<?= $category['id'] ?>" 
                                                <?= $category_id == $category['id'] ? 'selected' : '' ?>>
                                            <?= htmlspecialchars($category['ten_danh_muc']) ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div class="col-md-4">
                                <button type="submit" class="btn btn-primary me-2">
                                    <i class="fas fa-search"></i> Tìm kiếm
                                </button>
                                <a href="products.php" class="btn btn-outline-secondary">
                                    <i class="fas fa-refresh"></i> Reset
                                </a>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
        </div>

        <!-- Results Info -->
        <div class="d-flex justify-content-between align-items-center mb-4">
            <h3>
                📦 Sản phẩm 
                <?php if ($category_id > 0): ?>
                    - <?php 
                        $selected_category = array_filter($categories, function($c) use ($category_id) {
                            return $c['id'] == $category_id;
                        });
                        echo htmlspecialchars(reset($selected_category)['ten_danh_muc']);
                    ?>
                <?php endif; ?>
            </h3>
            <span class="badge bg-primary fs-6"><?= number_format($total_products) ?> sản phẩm</span>
        </div>

        <?php if (isset($error)): ?>
            <div class="alert alert-danger">
                <i class="fas fa-exclamation-triangle"></i> <?= $error ?>
            </div>
        <?php endif; ?>

        <!-- Products Grid -->
        <?php if (empty($products)): ?>
            <div class="no-products">
                <i class="fas fa-box-open fa-4x text-muted mb-3"></i>
                <h4 class="text-muted">Không tìm thấy sản phẩm nào</h4>
                <p class="text-muted">Thử thay đổi từ khóa tìm kiếm hoặc danh mục</p>
                <a href="products.php" class="btn btn-primary">
                    <i class="fas fa-refresh"></i> Xem tất cả sản phẩm
                </a>
            </div>
        <?php else: ?>
            <div class="row">
                <?php foreach ($products as $product): ?>
                    <div class="col-lg-3 col-md-4 col-sm-6 mb-4">
                        <div class="card product-card">
                            <?php if ($product['hinh_anh_chinh']): ?>
                                <img src="/tktshop/uploads/products/<?= $product['hinh_anh_chinh'] ?>" 
                                     class="card-img-top product-image" 
                                     alt="<?= htmlspecialchars($product['ten_san_pham']) ?>">
                            <?php else: ?>
                                <div class="card-img-top product-image bg-light d-flex align-items-center justify-content-center">
                                    <i class="fas fa-image fa-3x text-muted"></i>
                                </div>
                            <?php endif; ?>
                            
                            <div class="card-body d-flex flex-column">
                                <h6 class="card-title">
                                    <?= htmlspecialchars($product['ten_san_pham']) ?>
                                </h6>
                                
                                <div class="mb-2">
                                    <small class="text-muted">
                                        <i class="fas fa-tag"></i> <?= htmlspecialchars($product['thuong_hieu']) ?>
                                    </small>
                                    <?php if ($product['ten_danh_muc']): ?>
                                        <br>
                                        <small class="text-muted">
                                            <i class="fas fa-folder"></i> <?= htmlspecialchars($product['ten_danh_muc']) ?>
                                        </small>
                                    <?php endif; ?>
                                </div>
                                
                                <p class="card-text flex-grow-1">
                                    <small><?= htmlspecialchars(substr($product['mo_ta_ngan'], 0, 100)) ?>...</small>
                                </p>
                                
                                <div class="mt-auto">
                                    <div class="d-flex justify-content-between align-items-center mb-2">
                                        <div>
                                            <?php if ($product['gia_khuyen_mai'] && $product['gia_khuyen_mai'] < $product['gia_goc']): ?>
                                                <div class="product-price"><?= number_format($product['gia_khuyen_mai']) ?>₫</div>
                                                <div class="product-price-old"><?= number_format($product['gia_goc']) ?>₫</div>
                                            <?php else: ?>
                                                <div class="product-price"><?= number_format($product['gia_goc']) ?>₫</div>
                                            <?php endif; ?>
                                        </div>
                                        <?php if ($product['gia_khuyen_mai'] && $product['gia_khuyen_mai'] < $product['gia_goc']): ?>
                                            <?php $discount = round((($product['gia_goc'] - $product['gia_khuyen_mai']) / $product['gia_goc']) * 100); ?>
                                            <span class="badge bg-danger">-<?= $discount ?>%</span>
                                        <?php endif; ?>
                                    </div>
                                    
                                    <div class="d-grid gap-2">
                                        <button class="btn btn-primary btn-sm" onclick="alert('Chức năng mua hàng đang phát triển!')">
                                            <i class="fas fa-shopping-cart"></i> Thêm vào giỏ
                                        </button>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>

            <!-- Pagination -->
            <?php if ($total_pages > 1): ?>
                <nav aria-label="Products pagination" class="mt-5">
                    <ul class="pagination justify-content-center">
                        <?php if ($page > 1): ?>
                            <li class="page-item">
                                <a class="page-link" href="?page=<?= $page - 1 ?>&<?= http_build_query($_GET) ?>">
                                    <i class="fas fa-chevron-left"></i> Trước
                                </a>
                            </li>
                        <?php endif; ?>
                        
                        <?php
                        $start = max(1, $page - 2);
                        $end = min($total_pages, $page + 2);
                        
                        for ($i = $start; $i <= $end; $i++): ?>
                            <li class="page-item <?= $i == $page ? 'active' : '' ?>">
                                <a class="page-link" href="?page=<?= $i ?>&<?= http_build_query($_GET) ?>"><?= $i ?></a>
                            </li>
                        <?php endfor; ?>
                        
                        <?php if ($page < $total_pages): ?>
                            <li class="page-item">
                                <a class="page-link" href="?page=<?= $page + 1 ?>&<?= http_build_query($_GET) ?>">
                                    Sau <i class="fas fa-chevron-right"></i>
                                </a>
                            </li>
                        <?php endif; ?>
                    </ul>
                </nav>
                
                <div class="text-center mt-3">
                    <small class="text-muted">
                        Hiển thị <?= $offset + 1 ?> - <?= min($offset + $limit, $total_products) ?> 
                        trong tổng số <?= number_format($total_products) ?> sản phẩm
                    </small>
                </div>
            <?php endif; ?>
        <?php endif; ?>

        <!-- Debug Info -->
        <div class="mt-5">
            <div class="card bg-light">
                <div class="card-body">
                    <h6>🔧 Debug Info:</h6>
                    <small>
                        Total products: <?= $total_products ?><br>
                        Current page: <?= $page ?> / <?= $total_pages ?><br>
                        Category filter: <?= $category_id ?: 'All' ?><br>
                        Search: <?= $search ?: 'None' ?><br>
                        Database tables: <?php 
                            try {
                                $stmt = $pdo->query("SHOW TABLES");
                                echo $stmt->rowCount();
                            } catch(Exception $e) {
                                echo "Error";
                            }
                        ?>
                    </small>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>