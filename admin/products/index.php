<?php
// admin/products/index.php
/**
 * Trang danh sách sản phẩm với đầy đủ chức năng CRUD
 */

require_once '../../config/database.php';
require_once '../../config/config.php';

requireLogin();

// Xử lý xóa sản phẩm
if (isset($_GET['action']) && $_GET['action'] == 'delete' && isset($_GET['id'])) {
    $product_id = (int)$_GET['id'];
    
    try {
        $pdo->beginTransaction();
        
        // Kiểm tra xem sản phẩm có đơn hàng không
        $stmt = $pdo->prepare("SELECT COUNT(*) FROM chi_tiet_don_hang WHERE san_pham_id = ?");
        $stmt->execute([$product_id]);
        $order_count = $stmt->fetchColumn();
        
        if ($order_count > 0) {
            alert('Không thể xóa sản phẩm này vì đã có đơn hàng!', 'error');
        } else {
            // Lấy thông tin ảnh để xóa file
            $stmt = $pdo->prepare("SELECT hinh_anh_chinh, album_hinh_anh FROM san_pham_chinh WHERE id = ?");
            $stmt->execute([$product_id]);
            $product = $stmt->fetch();
            
            // Xóa biến thể trước
            $stmt = $pdo->prepare("DELETE FROM bien_the_san_pham WHERE san_pham_id = ?");
            $stmt->execute([$product_id]);
            
            // Xóa sản phẩm
            $stmt = $pdo->prepare("DELETE FROM san_pham_chinh WHERE id = ?");
            $stmt->execute([$product_id]);
            
            // Xóa ảnh
            if ($product && $product['hinh_anh_chinh']) {
                deleteUploadedFile($product['hinh_anh_chinh'], 'products');
            }
            
            if ($product && $product['album_hinh_anh']) {
                $album = json_decode($product['album_hinh_anh'], true);
                if (is_array($album)) {
                    foreach ($album as $image) {
                        deleteUploadedFile($image, 'products');
                    }
                }
            }
            
            $pdo->commit();
            alert('Xóa sản phẩm thành công!', 'success');
        }
    } catch (Exception $e) {
        $pdo->rollBack();
        alert('Lỗi: ' . $e->getMessage(), 'error');
    }
    
    redirect('/tktshop/admin/products/');
}

// Xử lý thay đổi trạng thái
if (isset($_GET['action']) && $_GET['action'] == 'toggle_status' && isset($_GET['id'])) {
    $product_id = (int)$_GET['id'];
    
    try {
        $stmt = $pdo->prepare("SELECT trang_thai FROM san_pham_chinh WHERE id = ?");
        $stmt->execute([$product_id]);
        $current_status = $stmt->fetchColumn();
        
        $new_status = ($current_status == 'hoat_dong') ? 'an' : 'hoat_dong';
        
        $stmt = $pdo->prepare("UPDATE san_pham_chinh SET trang_thai = ? WHERE id = ?");
        $stmt->execute([$new_status, $product_id]);
        
        alert('Cập nhật trạng thái thành công!', 'success');
    } catch (Exception $e) {
        alert('Lỗi: ' . $e->getMessage(), 'error');
    }
    
    redirect('/tktshop/admin/products/');
}

// Xử lý tìm kiếm và lọc
$search = $_GET['search'] ?? '';
$status_filter = $_GET['status'] ?? '';
$category_filter = $_GET['category'] ?? '';
$brand_filter = $_GET['brand'] ?? '';

// Pagination
$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$per_page = ADMIN_ITEMS_PER_PAGE;
$offset = ($page - 1) * $per_page;

// Build WHERE clause
$where_conditions = [];
$params = [];

if (!empty($search)) {
    $where_conditions[] = "(sp.ten_san_pham LIKE ? OR sp.ma_san_pham LIKE ? OR sp.thuong_hieu LIKE ?)";
    $search_term = "%{$search}%";
    $params[] = $search_term;
    $params[] = $search_term;
    $params[] = $search_term;
}

if (!empty($status_filter)) {
    $where_conditions[] = "sp.trang_thai = ?";
    $params[] = $status_filter;
}

if (!empty($category_filter)) {
    $where_conditions[] = "sp.danh_muc_id = ?";
    $params[] = $category_filter;
}

if (!empty($brand_filter)) {
    $where_conditions[] = "sp.thuong_hieu = ?";
    $params[] = $brand_filter;
}

$where_sql = !empty($where_conditions) ? 'WHERE ' . implode(' AND ', $where_conditions) : '';

// Đếm tổng số sản phẩm
$count_sql = "
    SELECT COUNT(*) 
    FROM san_pham_chinh sp 
    LEFT JOIN danh_muc_giay dm ON sp.danh_muc_id = dm.id 
    {$where_sql}
";
$stmt = $pdo->prepare($count_sql);
$stmt->execute($params);
$total_products = $stmt->fetchColumn();
$total_pages = ceil($total_products / $per_page);

// Lấy danh sách sản phẩm
$sql = "
    SELECT 
        sp.*,
        dm.ten_danh_muc,
        COUNT(DISTINCT bt.id) as so_bien_the,
        SUM(bt.so_luong_ton_kho) as tong_ton_kho,
        MIN(bt.gia_ban) as gia_thap_nhat,
        MAX(bt.gia_ban) as gia_cao_nhat
    FROM san_pham_chinh sp
    LEFT JOIN danh_muc_giay dm ON sp.danh_muc_id = dm.id
    LEFT JOIN bien_the_san_pham bt ON sp.id = bt.san_pham_id AND bt.trang_thai = 'hoat_dong'
    {$where_sql}
    GROUP BY sp.id
    ORDER BY sp.ngay_tao DESC
    LIMIT {$per_page} OFFSET {$offset}
";

$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$products = $stmt->fetchAll();

// Lấy danh sách danh mục cho filter
$categories = $pdo->query("SELECT * FROM danh_muc_giay WHERE trang_thai = 'hoat_dong' ORDER BY ten_danh_muc")->fetchAll();

// Lấy danh sách thương hiệu
$brands = $pdo->query("SELECT DISTINCT thuong_hieu FROM san_pham_chinh WHERE thuong_hieu IS NOT NULL ORDER BY thuong_hieu")->fetchAll();

// Thống kê tổng quan
$stats = [
    'tong_san_pham' => $pdo->query("SELECT COUNT(*) FROM san_pham_chinh")->fetchColumn(),
    'dang_hien_thi' => $pdo->query("SELECT COUNT(*) FROM san_pham_chinh WHERE trang_thai = 'hoat_dong'")->fetchColumn(),
    'sap_het_hang' => $pdo->query("SELECT COUNT(DISTINCT sp.id) FROM san_pham_chinh sp JOIN bien_the_san_pham bt ON sp.id = bt.san_pham_id WHERE bt.so_luong_ton_kho <= bt.nguong_canh_bao_het_hang")->fetchColumn(),
    'moi_hom_nay' => $pdo->query("SELECT COUNT(*) FROM san_pham_chinh WHERE DATE(ngay_tao) = CURDATE()")->fetchColumn()
];
?>

<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Quản lý sản phẩm - <?= SITE_NAME ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <link href="<?= BASE_URL ?>/assets/css/admin.css" rel="stylesheet">
    <style>
        .product-image {
            width: 60px;
            height: 60px;
            object-fit: cover;
            border-radius: 8px;
        }
        .status-badge {
            font-size: 0.75rem;
        }
        .action-buttons .btn {
            padding: 0.25rem 0.5rem;
            margin: 0 2px;
        }
        .stats-card {
            transition: transform 0.2s;
        }
        .stats-card:hover {
            transform: translateY(-2px);
        }
    </style>
</head>
<body>
    <div class="container-fluid">
        <div class="row">
            <!-- Sidebar -->
            <?php include '../layouts/sidebar.php'; ?>
            
            <!-- Main content -->
            <div class="col-md-10">
                <div class="container-fluid p-4">
                    
                    <!-- Header -->
                    <div class="d-flex justify-content-between align-items-center mb-4">
                        <div>
                            <h2><i class="fas fa-box me-2"></i>Quản lý sản phẩm</h2>
                            <nav aria-label="breadcrumb">
                                <ol class="breadcrumb">
                                    <li class="breadcrumb-item"><a href="<?= adminUrl() ?>">Dashboard</a></li>
                                    <li class="breadcrumb-item active">Sản phẩm</li>
                                </ol>
                            </nav>
                        </div>
                        <a href="<?= adminUrl('products/add.php') ?>" class="btn btn-primary">
                            <i class="fas fa-plus me-2"></i>Thêm sản phẩm
                        </a>
                    </div>

                    <!-- Thống kê nhanh -->
                    <div class="row mb-4">
                        <div class="col-md-3">
                            <div class="card stats-card border-primary">
                                <div class="card-body text-center">
                                    <i class="fas fa-box fa-2x text-primary mb-2"></i>
                                    <h4 class="text-primary"><?= number_format($stats['tong_san_pham']) ?></h4>
                                    <small class="text-muted">Tổng sản phẩm</small>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-3">
                            <div class="card stats-card border-success">
                                <div class="card-body text-center">
                                    <i class="fas fa-eye fa-2x text-success mb-2"></i>
                                    <h4 class="text-success"><?= number_format($stats['dang_hien_thi']) ?></h4>
                                    <small class="text-muted">Đang hiển thị</small>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-3">
                            <div class="card stats-card border-warning">
                                <div class="card-body text-center">
                                    <i class="fas fa-exclamation-triangle fa-2x text-warning mb-2"></i>
                                    <h4 class="text-warning"><?= number_format($stats['sap_het_hang']) ?></h4>
                                    <small class="text-muted">Sắp hết hàng</small>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-3">
                            <div class="card stats-card border-info">
                                <div class="card-body text-center">
                                    <i class="fas fa-plus fa-2x text-info mb-2"></i>
                                    <h4 class="text-info"><?= number_format($stats['moi_hom_nay']) ?></h4>
                                    <small class="text-muted">Mới hôm nay</small>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Filters -->
                    <div class="card mb-4">
                        <div class="card-body">
                            <form method="GET" class="row g-3">
                                <div class="col-md-3">
                                    <label class="form-label">Tìm kiếm</label>
                                    <input type="text" name="search" class="form-control" 
                                           placeholder="Tên, mã, thương hiệu..." 
                                           value="<?= htmlspecialchars($search) ?>">
                                </div>
                                <div class="col-md-2">
                                    <label class="form-label">Trạng thái</label>
                                    <select name="status" class="form-select">
                                        <option value="">Tất cả</option>
                                        <option value="hoat_dong" <?= $status_filter == 'hoat_dong' ? 'selected' : '' ?>>Hoạt động</option>
                                        <option value="het_hang" <?= $status_filter == 'het_hang' ? 'selected' : '' ?>>Hết hàng</option>
                                        <option value="an" <?= $status_filter == 'an' ? 'selected' : '' ?>>Ẩn</option>
                                    </select>
                                </div>
                                <div class="col-md-3">
                                    <label class="form-label">Danh mục</label>
                                    <select name="category" class="form-select">
                                        <option value="">Tất cả danh mục</option>
                                        <?php foreach ($categories as $cat): ?>
                                            <option value="<?= $cat['id'] ?>" <?= $category_filter == $cat['id'] ? 'selected' : '' ?>>
                                                <?= htmlspecialchars($cat['ten_danh_muc']) ?>
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                                <div class="col-md-2">
                                    <label class="form-label">Thương hiệu</label>
                                    <select name="brand" class="form-select">
                                        <option value="">Tất cả</option>
                                        <?php foreach ($brands as $brand): ?>
                                            <option value="<?= $brand['thuong_hieu'] ?>" <?= $brand_filter == $brand['thuong_hieu'] ? 'selected' : '' ?>>
                                                <?= htmlspecialchars($brand['thuong_hieu']) ?>
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                                <div class="col-md-2 d-flex align-items-end">
                                    <button type="submit" class="btn btn-primary me-2">
                                        <i class="fas fa-search"></i> Tìm kiếm
                                    </button>
                                    <a href="?" class="btn btn-secondary">
                                        <i class="fas fa-undo"></i> Reset
                                    </a>
                                </div>
                            </form>
                        </div>
                    </div>

                    <!-- Alert Messages -->
                    <?php showAlert(); ?>

                    <!-- Danh sách sản phẩm -->
                    <div class="card">
                        <div class="card-header d-flex justify-content-between align-items-center">
                            <h5><i class="fas fa-list me-2"></i>Danh sách sản phẩm (<?= number_format($total_products) ?>)</h5>
                            <a href="<?= adminUrl('products/add.php') ?>" class="btn btn-success btn-sm">
                                <i class="fas fa-plus me-1"></i>Thêm mới
                            </a>
                        </div>
                        <div class="card-body p-0">
                            <?php if (empty($products)): ?>
                                <div class="text-center py-5">
                                    <i class="fas fa-box-open fa-3x text-muted mb-3"></i>
                                    <h5 class="text-muted">Không có sản phẩm nào</h5>
                                    <a href="<?= adminUrl('products/add.php') ?>" class="btn btn-primary">
                                        <i class="fas fa-plus me-2"></i>Thêm sản phẩm đầu tiên
                                    </a>
                                </div>
                            <?php else: ?>
                                <div class="table-responsive">
                                    <table class="table table-hover mb-0">
                                        <thead class="table-light">
                                            <tr>
                                                <th width="80">Ảnh</th>
                                                <th>Sản phẩm</th>
                                                <th>Thương hiệu</th>
                                                <th>Giá</th>
                                                <th>Trạng thái</th>
                                                <th>Ngày tạo</th>
                                                <th width="150">Hành động</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <?php foreach ($products as $product): ?>
                                                <tr>
                                                    <td>
                                                        <?php if ($product['hinh_anh_chinh']): ?>
                                                            <img src="<?= getProductImageUrl($product['hinh_anh_chinh']) ?>" 
                                                                 alt="<?= htmlspecialchars($product['ten_san_pham']) ?>" 
                                                                 class="product-image">
                                                        <?php else: ?>
                                                            <div class="product-image bg-light d-flex align-items-center justify-content-center">
                                                                <i class="fas fa-image text-muted"></i>
                                                            </div>
                                                        <?php endif; ?>
                                                    </td>
                                                    <td>
                                                        <div>
                                                            <strong><?= htmlspecialchars($product['ten_san_pham']) ?></strong>
                                                            <br>
                                                            <small class="text-muted">
                                                                Mã: <?= htmlspecialchars($product['ma_san_pham']) ?>
                                                            </small>
                                                            <br>
                                                            <small class="text-info">
                                                                <?= $product['so_bien_the'] ?> biến thể | 
                                                                <?= $product['tong_ton_kho'] ?: 0 ?> sản phẩm
                                                            </small>
                                                        </div>
                                                    </td>
                                                    <td>
                                                        <span class="badge bg-secondary">
                                                            <?= htmlspecialchars($product['thuong_hieu']) ?>
                                                        </span>
                                                        <br>
                                                        <small class="text-muted">
                                                            <?= htmlspecialchars($product['ten_danh_muc']) ?>
                                                        </small>
                                                    </td>
                                                    <td>
                                                        <?php if ($product['gia_thap_nhat'] && $product['gia_cao_nhat']): ?>
                                                            <?php if ($product['gia_thap_nhat'] == $product['gia_cao_nhat']): ?>
                                                                <strong class="text-success"><?= formatPrice($product['gia_thap_nhat']) ?></strong>
                                                            <?php else: ?>
                                                                <strong class="text-success">
                                                                    <?= formatPrice($product['gia_thap_nhat']) ?> - 
                                                                    <?= formatPrice($product['gia_cao_nhat']) ?>
                                                                </strong>
                                                            <?php endif; ?>
                                                        <?php else: ?>
                                                            <span class="text-warning">Chưa có giá</span>
                                                        <?php endif; ?>
                                                    </td>
                                                    <td>
                                                        <?php
                                                        $status_class = [
                                                            'hoat_dong' => 'success',
                                                            'het_hang' => 'warning', 
                                                            'an' => 'secondary'
                                                        ];
                                                        $status_text = [
                                                            'hoat_dong' => 'Hoạt động',
                                                            'het_hang' => 'Hết hàng',
                                                            'an' => 'Ẩn'
                                                        ];
                                                        $class = $status_class[$product['trang_thai']] ?? 'secondary';
                                                        $text = $status_text[$product['trang_thai']] ?? $product['trang_thai'];
                                                        ?>
                                                        <span class="badge bg-<?= $class ?> status-badge">
                                                            <?= $text ?>
                                                        </span>
                                                    </td>
                                                    <td>
                                                        <small><?= formatDate($product['ngay_tao']) ?></small>
                                                    </td>
                                                    <td>
                                                        <div class="action-buttons">
                                                            <!-- Xem chi tiết -->
                                                            <a href="<?= customerUrl('product_detail.php?id=' . $product['id']) ?>" 
                                                               class="btn btn-outline-info btn-sm" 
                                                               title="Xem trang sản phẩm" target="_blank">
                                                                <i class="fas fa-eye"></i>
                                                            </a>
                                                            
                                                            <!-- Quản lý biến thể -->
                                                            <a href="<?= adminUrl('products/variants.php?id=' . $product['id']) ?>" 
                                                               class="btn btn-outline-primary btn-sm" 
                                                               title="Quản lý biến thể">
                                                                <i class="fas fa-cogs"></i>
                                                            </a>
                                                            
                                                            <!-- Chỉnh sửa -->
                                                            <a href="<?= adminUrl('products/edit.php?id=' . $product['id']) ?>" 
                                                               class="btn btn-outline-warning btn-sm" 
                                                               title="Chỉnh sửa">
                                                                <i class="fas fa-edit"></i>
                                                            </a>
                                                            
                                                            <!-- Toggle trạng thái -->
                                                            <a href="?action=toggle_status&id=<?= $product['id'] ?>" 
                                                               class="btn btn-outline-secondary btn-sm" 
                                                               title="Thay đổi trạng thái"
                                                               onclick="return confirm('Bạn có chắc muốn thay đổi trạng thái?')">
                                                                <i class="fas fa-toggle-<?= $product['trang_thai'] == 'hoat_dong' ? 'on' : 'off' ?>"></i>
                                                            </a>
                                                            
                                                            <!-- Xóa -->
                                                            <a href="?action=delete&id=<?= $product['id'] ?>" 
                                                               class="btn btn-outline-danger btn-sm" 
                                                               title="Xóa sản phẩm"
                                                               onclick="return confirm('Bạn có chắc muốn xóa sản phẩm này?\n\nLưu ý: Sản phẩm đã có đơn hàng sẽ không thể xóa!')">
                                                                <i class="fas fa-trash"></i>
                                                            </a>
                                                        </div>
                                                    </td>
                                                </tr>
                                            <?php endforeach; ?>
                                        </tbody>
                                    </table>
                                </div>
                                
                                <!-- Pagination -->
                                <?php if ($total_pages > 1): ?>
                                    <div class="card-footer">
                                        <nav aria-label="Product pagination">
                                            <ul class="pagination justify-content-center mb-0">
                                                <?php
                                                $query_params = $_GET;
                                                
                                                // Previous button
                                                if ($page > 1):
                                                    $query_params['page'] = $page - 1;
                                                    $prev_url = '?' . http_build_query($query_params);
                                                ?>
                                                    <li class="page-item">
                                                        <a class="page-link" href="<?= $prev_url ?>">
                                                            <i class="fas fa-chevron-left"></i> Trước
                                                        </a>
                                                    </li>
                                                <?php endif; ?>
                                                
                                                <?php
                                                // Page numbers
                                                $start_page = max(1, $page - 2);
                                                $end_page = min($total_pages, $page + 2);
                                                
                                                for ($i = $start_page; $i <= $end_page; $i++):
                                                    $query_params['page'] = $i;
                                                    $page_url = '?' . http_build_query($query_params);
                                                ?>
                                                    <li class="page-item <?= $i == $page ? 'active' : '' ?>">
                                                        <a class="page-link" href="<?= $page_url ?>"><?= $i ?></a>
                                                    </li>
                                                <?php endfor; ?>
                                                
                                                <?php
                                                // Next button
                                                if ($page < $total_pages):
                                                    $query_params['page'] = $page + 1;
                                                    $next_url = '?' . http_build_query($query_params);
                                                ?>
                                                    <li class="page-item">
                                                        <a class="page-link" href="<?= $next_url ?>">
                                                            Sau <i class="fas fa-chevron-right"></i>
                                                        </a>
                                                    </li>
                                                <?php endif; ?>
                                            </ul>
                                        </nav>
                                        
                                        <div class="text-center mt-2">
                                            <small class="text-muted">
                                                Hiển thị <?= ($offset + 1) ?> - <?= min($offset + $per_page, $total_products) ?> 
                                                trong <?= number_format($total_products) ?> sản phẩm
                                            </small>
                                        </div>
                                    </div>
                                <?php endif; ?>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
    <script src="<?= BASE_URL ?>/assets/js/admin.js"></script>
    
    <script>
        // Auto-submit form on filter change
        document.querySelectorAll('select[name="status"], select[name="category"], select[name="brand"]').forEach(select => {
            select.addEventListener('change', function() {
                this.form.submit();
            });
        });
        
        // Enhanced tooltips
        var tooltipTriggerList = [].slice.call(document.querySelectorAll('[title]'));
        var tooltipList = tooltipTriggerList.map(function (tooltipTriggerEl) {
            return new bootstrap.Tooltip(tooltipTriggerEl);
        });
    </script>
</body>
</html>