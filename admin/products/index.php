<?php
session_start();
require_once '../../config/database.php';
require_once '../../config/config.php';

// Kiểm tra quyền admin
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    header('Location: ../../admin/login.php');
    exit();
}

// Thiết lập phân trang
$page = isset($_GET['page']) ? max(1, (int)$_GET['page']) : 1;
$limit = ADMIN_ITEMS_PER_PAGE;
$offset = ($page - 1) * $limit;

// Thiết lập bộ lọc
$search = isset($_GET['search']) ? trim($_GET['search']) : '';
$category_filter = isset($_GET['category']) ? (int)$_GET['category'] : 0;
$status_filter = isset($_GET['status']) ? $_GET['status'] : '';
$brand_filter = isset($_GET['brand']) ? trim($_GET['brand']) : '';

// Xây dựng WHERE clause
$where_conditions = [];
$params = [];

if (!empty($search)) {
    $where_conditions[] = "(sp.ten_san_pham LIKE ? OR sp.ma_san_pham LIKE ? OR sp.thuong_hieu LIKE ?)";
    $search_param = "%$search%";
    $params[] = $search_param;
    $params[] = $search_param;
    $params[] = $search_param;
}

if ($category_filter > 0) {
    $where_conditions[] = "sp.danh_muc_id = ?";
    $params[] = $category_filter;
}

if (!empty($status_filter)) {
    $where_conditions[] = "sp.trang_thai = ?";
    $params[] = $status_filter;
}

if (!empty($brand_filter)) {
    $where_conditions[] = "sp.thuong_hieu LIKE ?";
    $params[] = "%$brand_filter%";
}

$where_clause = !empty($where_conditions) ? 'WHERE ' . implode(' AND ', $where_conditions) : '';

try {
    // Lấy tổng số sản phẩm để phân trang
    $count_sql = "SELECT COUNT(*) FROM san_pham_chinh sp $where_clause";
    $count_stmt = $pdo->prepare($count_sql);
    $count_stmt->execute($params);
    $total_products = $count_stmt->fetchColumn();
    $total_pages = ceil($total_products / $limit);

    // Lấy danh sách sản phẩm
    $sql = "SELECT 
                sp.*,
                dm.ten_danh_muc,
                COUNT(btp.id) as so_bien_the,
                SUM(btp.so_luong_ton_kho) as tong_ton_kho,
                SUM(btp.so_luong_da_ban) as tong_da_ban
            FROM san_pham_chinh sp
            LEFT JOIN danh_muc_giay dm ON sp.danh_muc_id = dm.id
            LEFT JOIN bien_the_san_pham btp ON sp.id = btp.san_pham_id
            $where_clause
            GROUP BY sp.id
            ORDER BY sp.ngay_tao DESC
            LIMIT $limit OFFSET $offset";
    
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $products = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Lấy danh sách danh mục để filter
    $categories_stmt = $pdo->query("SELECT * FROM danh_muc_giay WHERE trang_thai = 'hoat_dong' ORDER BY ten_danh_muc");
    $categories = $categories_stmt->fetchAll(PDO::FETCH_ASSOC);

    // Lấy danh sách thương hiệu
    $brands_stmt = $pdo->query("SELECT DISTINCT thuong_hieu FROM san_pham_chinh WHERE thuong_hieu IS NOT NULL AND thuong_hieu != '' ORDER BY thuong_hieu");
    $brands = $brands_stmt->fetchAll(PDO::FETCH_COLUMN);

} catch (Exception $e) {
    $error = "Lỗi truy vấn: " . $e->getMessage();
    $products = [];
    $categories = [];
    $brands = [];
    $total_pages = 1;
}

// Xử lý xóa sản phẩm
if (isset($_GET['action']) && $_GET['action'] === 'delete' && isset($_GET['id'])) {
    $product_id = (int)$_GET['id'];
    try {
        // Kiểm tra xem sản phẩm có đơn hàng nào chưa
        $order_check = $pdo->prepare("SELECT COUNT(*) FROM chi_tiet_don_hang WHERE san_pham_id = ?");
        $order_check->execute([$product_id]);
        
        if ($order_check->fetchColumn() > 0) {
            $alert_message = "Không thể xóa sản phẩm này vì đã có đơn hàng liên quan!";
            $alert_type = "danger";
        } else {
            // Xóa biến thể trước
            $pdo->prepare("DELETE FROM bien_the_san_pham WHERE san_pham_id = ?")->execute([$product_id]);
            
            // Xóa sản phẩm
            $delete_stmt = $pdo->prepare("DELETE FROM san_pham_chinh WHERE id = ?");
            $delete_stmt->execute([$product_id]);
            
            $alert_message = "Xóa sản phẩm thành công!";
            $alert_type = "success";
        }
        
        // Redirect để tránh lặp lại action
        header("Location: index.php?alert=" . urlencode($alert_message) . "&type=" . $alert_type);
        exit();
        
    } catch (Exception $e) {
        $alert_message = "Lỗi khi xóa sản phẩm: " . $e->getMessage();
        $alert_type = "danger";
    }
}

// Xử lý thay đổi trạng thái hàng loạt
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['bulk_action'])) {
    $selected_ids = $_POST['selected_products'] ?? [];
    $bulk_action = $_POST['bulk_action'];
    
    if (!empty($selected_ids) && in_array($bulk_action, ['hoat_dong', 'an', 'het_hang', 'delete'])) {
        try {
            $ids_placeholder = str_repeat('?,', count($selected_ids) - 1) . '?';
            
            if ($bulk_action === 'delete') {
                // Xóa hàng loạt
                $pdo->prepare("DELETE FROM bien_the_san_pham WHERE san_pham_id IN ($ids_placeholder)")->execute($selected_ids);
                $pdo->prepare("DELETE FROM san_pham_chinh WHERE id IN ($ids_placeholder)")->execute($selected_ids);
                $alert_message = "Đã xóa " . count($selected_ids) . " sản phẩm!";
            } else {
                // Cập nhật trạng thái hàng loạt
                $update_sql = "UPDATE san_pham_chinh SET trang_thai = ? WHERE id IN ($ids_placeholder)";
                $update_params = array_merge([$bulk_action], $selected_ids);
                $pdo->prepare($update_sql)->execute($update_params);
                $alert_message = "Đã cập nhật trạng thái cho " . count($selected_ids) . " sản phẩm!";
            }
            
            $alert_type = "success";
            
        } catch (Exception $e) {
            $alert_message = "Lỗi: " . $e->getMessage();
            $alert_type = "danger";
        }
    }
}

// Lấy thông báo từ URL nếu có
if (isset($_GET['alert'])) {
    $alert_message = $_GET['alert'];
    $alert_type = $_GET['type'] ?? 'info';
}

$page_title = "Quản lý sản phẩm";
$breadcrumbs = [
    ['title' => 'Dashboard', 'url' => '../dashboard.php'],
    ['title' => 'Sản phẩm', 'url' => 'index.php']
];
?>

<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= $page_title ?> - TKT Shop Admin</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
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
        .table th {
            background-color: #f8f9fa;
            border-top: none;
            font-weight: 600;
        }
        .filter-section {
            background: #f8f9fa;
            border-radius: 8px;
            padding: 1rem;
            margin-bottom: 1rem;
        }
        .stats-card {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            border-radius: 10px;
        }
        .product-actions .btn {
            padding: 0.25rem 0.5rem;
            font-size: 0.75rem;
        }
    </style>
</head>
<body>
    <div class="container-fluid">
        <div class="row">
            <!-- Sidebar -->
            <div class="col-md-2 p-0">
                <?php include '../layouts/sidebar.php'; ?>
            </div>
            
            <!-- Main content -->
            <div class="col-md-10">
                <!-- Header -->
                <?php include '../layouts/header.php'; ?>
                
                <div class="p-4">
                    <!-- Stats Cards -->
                    <div class="row mb-4">
                        <div class="col-md-3">
                            <div class="card stats-card">
                                <div class="card-body text-center">
                                    <i class="fas fa-box fa-2x mb-2"></i>
                                    <h4><?= number_format($total_products) ?></h4>
                                    <small>Tổng sản phẩm</small>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-3">
                            <div class="card bg-success text-white">
                                <div class="card-body text-center">
                                    <i class="fas fa-eye fa-2x mb-2"></i>
                                    <h4><?php 
                                        $active_count = $pdo->query("SELECT COUNT(*) FROM san_pham_chinh WHERE trang_thai = 'hoat_dong'")->fetchColumn();
                                        echo number_format($active_count);
                                    ?></h4>
                                    <small>Đang hiển thị</small>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-3">
                            <div class="card bg-warning text-white">
                                <div class="card-body text-center">
                                    <i class="fas fa-exclamation-triangle fa-2x mb-2"></i>
                                    <h4><?php 
                                        $low_stock = $pdo->query("SELECT COUNT(DISTINCT sp.id) FROM san_pham_chinh sp JOIN bien_the_san_pham btp ON sp.id = btp.san_pham_id WHERE btp.so_luong_ton_kho <= 5")->fetchColumn();
                                        echo number_format($low_stock);
                                    ?></h4>
                                    <small>Sắp hết hàng</small>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-3">
                            <div class="card bg-info text-white">
                                <div class="card-body text-center">
                                    <i class="fas fa-plus fa-2x mb-2"></i>
                                    <h4><?php 
                                        $new_products = $pdo->query("SELECT COUNT(*) FROM san_pham_chinh WHERE DATE(ngay_tao) >= DATE_SUB(CURDATE(), INTERVAL 7 DAY)")->fetchColumn();
                                        echo number_format($new_products);
                                    ?></h4>
                                    <small>Mới trong tuần</small>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Filter Section -->
                    <div class="filter-section">
                        <form method="GET" class="row g-3">
                            <div class="col-md-3">
                                <input type="text" class="form-control" name="search" placeholder="Tìm kiếm sản phẩm..." value="<?= htmlspecialchars($search) ?>">
                            </div>
                            <div class="col-md-2">
                                <select class="form-select" name="category">
                                    <option value="">Tất cả danh mục</option>
                                    <?php foreach ($categories as $category): ?>
                                        <option value="<?= $category['id'] ?>" <?= $category_filter == $category['id'] ? 'selected' : '' ?>>
                                            <?= htmlspecialchars($category['ten_danh_muc']) ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div class="col-md-2">
                                <select class="form-select" name="status">
                                    <option value="">Tất cả trạng thái</option>
                                    <option value="hoat_dong" <?= $status_filter === 'hoat_dong' ? 'selected' : '' ?>>Hoạt động</option>
                                    <option value="an" <?= $status_filter === 'an' ? 'selected' : '' ?>>Ẩn</option>
                                    <option value="het_hang" <?= $status_filter === 'het_hang' ? 'selected' : '' ?>>Hết hàng</option>
                                </select>
                            </div>
                            <div class="col-md-2">
                                <select class="form-select" name="brand">
                                    <option value="">Tất cả thương hiệu</option>
                                    <?php foreach ($brands as $brand): ?>
                                        <option value="<?= htmlspecialchars($brand) ?>" <?= $brand_filter === $brand ? 'selected' : '' ?>>
                                            <?= htmlspecialchars($brand) ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div class="col-md-3">
                                <div class="btn-group w-100">
                                    <button type="submit" class="btn btn-primary">
                                        <i class="fas fa-search"></i> Tìm kiếm
                                    </button>
                                    <a href="index.php" class="btn btn-outline-secondary">
                                        <i class="fas fa-refresh"></i> Reset
                                    </a>
                                    <a href="create.php" class="btn btn-success">
                                        <i class="fas fa-plus"></i> Thêm mới
                                    </a>
                                </div>
                            </div>
                        </form>
                    </div>

                    <!-- Alert Messages -->
                    <?php if (isset($alert_message)): ?>
                        <div class="alert alert-<?= $alert_type ?> alert-dismissible fade show">
                            <i class="fas fa-<?= $alert_type === 'danger' ? 'exclamation-triangle' : 'check-circle' ?>"></i>
                            <?= htmlspecialchars($alert_message) ?>
                            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                        </div>
                    <?php endif; ?>

                    <!-- Products Table -->
                    <div class="card">
                        <div class="card-header d-flex justify-content-between align-items-center">
                            <h5 class="mb-0">
                                <i class="fas fa-list me-2"></i>Danh sách sản phẩm (<?= number_format($total_products) ?>)
                            </h5>
                            <div class="btn-group">
                                <button type="button" class="btn btn-sm btn-outline-primary" onclick="toggleSelectAll()">
                                    <i class="fas fa-check-square"></i> Chọn tất cả
                                </button>
                                <button type="button" class="btn btn-sm btn-outline-secondary" data-bs-toggle="modal" data-bs-target="#bulkActionModal">
                                    <i class="fas fa-cogs"></i> Hành động hàng loạt
                                </button>
                            </div>
                        </div>
                        <div class="card-body p-0">
                            <?php if (empty($products)): ?>
                                <div class="text-center py-5">
                                    <i class="fas fa-box-open fa-3x text-muted mb-3"></i>
                                    <h5 class="text-muted">Không tìm thấy sản phẩm nào</h5>
                                    <p class="text-muted">Thử thay đổi điều kiện tìm kiếm hoặc thêm sản phẩm mới</p>
                                    <a href="create.php" class="btn btn-primary">
                                        <i class="fas fa-plus"></i> Thêm sản phẩm đầu tiên
                                    </a>
                                </div>
                            <?php else: ?>
                                <div class="table-responsive">
                                    <table class="table table-hover align-middle mb-0">
                                        <thead>
                                            <tr>
                                                <th width="50"><input type="checkbox" id="selectAll"></th>
                                                <th width="80">Ảnh</th>
                                                <th>Sản phẩm</th>
                                                <th>Danh mục</th>
                                                <th>Giá</th>
                                                <th>Biến thể</th>
                                                <th>Tồn kho</th>
                                                <th>Đã bán</th>
                                                <th>Trạng thái</th>
                                                <th width="150">Hành động</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <?php foreach ($products as $product): ?>
                                                <tr>
                                                    <td>
                                                        <input type="checkbox" class="product-checkbox" value="<?= $product['id'] ?>">
                                                    </td>
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
                                                            <strong><?= htmlspecialchars($product['ten_san_pham']) ?></strong><br>
                                                            <small class="text-muted">
                                                                Mã: <?= htmlspecialchars($product['ma_san_pham']) ?><br>
                                                                <?= htmlspecialchars($product['thuong_hieu']) ?>
                                                            </small>
                                                        </div>
                                                    </td>
                                                    <td>
                                                        <span class="badge bg-secondary">
                                                            <?= htmlspecialchars($product['ten_danh_muc'] ?? 'Chưa phân loại') ?>
                                                        </span>
                                                    </td>
                                                    <td>
                                                        <strong class="text-primary"><?= formatPrice($product['gia_goc']) ?></strong>
                                                        <?php if ($product['gia_khuyen_mai'] && $product['gia_khuyen_mai'] < $product['gia_goc']): ?>
                                                            <br><small class="text-success"><?= formatPrice($product['gia_khuyen_mai']) ?></small>
                                                        <?php endif; ?>
                                                    </td>
                                                    <td>
                                                        <span class="badge bg-info"><?= $product['so_bien_the'] ?? 0 ?></span>
                                                    </td>
                                                    <td>
                                                        <span class="badge bg-<?= ($product['tong_ton_kho'] ?? 0) > 0 ? 'success' : 'danger' ?>">
                                                            <?= $product['tong_ton_kho'] ?? 0 ?>
                                                        </span>
                                                    </td>
                                                    <td>
                                                        <strong><?= $product['tong_da_ban'] ?? 0 ?></strong>
                                                    </td>
                                                    <td>
                                                        <?php
                                                        $status_config = [
                                                            'hoat_dong' => ['class' => 'success', 'text' => 'Hoạt động'],
                                                            'an' => ['class' => 'secondary', 'text' => 'Ẩn'],
                                                            'het_hang' => ['class' => 'warning', 'text' => 'Hết hàng']
                                                        ];
                                                        $status = $status_config[$product['trang_thai']] ?? ['class' => 'dark', 'text' => $product['trang_thai']];
                                                        ?>
                                                        <span class="badge bg-<?= $status['class'] ?> status-badge">
                                                            <?= $status['text'] ?>
                                                        </span>
                                                    </td>
                                                    <td>
                                                        <div class="product-actions">
                                                            <a href="variants.php?product_id=<?= $product['id'] ?>" 
                                                               class="btn btn-sm btn-outline-primary" title="Quản lý biến thể">
                                                                <i class="fas fa-cubes"></i>
                                                            </a>
                                                            <a href="edit.php?id=<?= $product['id'] ?>" 
                                                               class="btn btn-sm btn-outline-warning" title="Sửa">
                                                                <i class="fas fa-edit"></i>
                                                            </a>
                                                            <button type="button" class="btn btn-sm btn-outline-danger" 
                                                                    onclick="deleteProduct(<?= $product['id'] ?>)" title="Xóa">
                                                                <i class="fas fa-trash"></i>
                                                            </button>
                                                        </div>
                                                    </td>
                                                </tr>
                                            <?php endforeach; ?>
                                        </tbody>
                                    </table>
                                </div>
                            <?php endif; ?>
                        </div>
                        
                        <!-- Pagination -->
                        <?php if ($total_pages > 1): ?>
                            <div class="card-footer">
                                <nav aria-label="Products pagination">
                                    <ul class="pagination justify-content-center mb-0">
                                        <li class="page-item <?= $page <= 1 ? 'disabled' : '' ?>">
                                            <a class="page-link" href="?page=<?= $page - 1 ?>&<?= http_build_query($_GET) ?>">
                                                <i class="fas fa-chevron-left"></i>
                                            </a>
                                        </li>
                                        
                                        <?php
                                        $start = max(1, $page - 2);
                                        $end = min($total_pages, $page + 2);
                                        
                                        if ($start > 1): ?>
                                            <li class="page-item">
                                                <a class="page-link" href="?page=1&<?= http_build_query($_GET) ?>">1</a>
                                            </li>
                                            <?php if ($start > 2): ?>
                                                <li class="page-item disabled"><span class="page-link">...</span></li>
                                            <?php endif;
                                        endif;
                                        
                                        for ($i = $start; $i <= $end; $i++): ?>
                                            <li class="page-item <?= $i == $page ? 'active' : '' ?>">
                                                <a class="page-link" href="?page=<?= $i ?>&<?= http_build_query($_GET) ?>"><?= $i ?></a>
                                            </li>
                                        <?php endfor;
                                        
                                        if ($end < $total_pages): ?>
                                            <?php if ($end < $total_pages - 1): ?>
                                                <li class="page-item disabled"><span class="page-link">...</span></li>
                                            <?php endif; ?>
                                            <li class="page-item">
                                                <a class="page-link" href="?page=<?= $total_pages ?>&<?= http_build_query($_GET) ?>"><?= $total_pages ?></a>
                                            </li>
                                        <?php endif; ?>
                                        
                                        <li class="page-item <?= $page >= $total_pages ? 'disabled' : '' ?>">
                                            <a class="page-link" href="?page=<?= $page + 1 ?>&<?= http_build_query($_GET) ?>">
                                                <i class="fas fa-chevron-right"></i>
                                            </a>
                                        </li>
                                    </ul>
                                </nav>
                                <div class="text-center mt-2">
                                    <small class="text-muted">
                                        Hiển thị <?= ($offset + 1) ?> - <?= min($offset + $limit, $total_products) ?> 
                                        trong tổng số <?= number_format($total_products) ?> sản phẩm
                                    </small>
                                </div>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Bulk Action Modal -->
    <div class="modal fade" id="bulkActionModal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <form method="POST" id="bulkActionForm">
                    <div class="modal-header">
                        <h5 class="modal-title">Hành động hàng loạt</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                    </div>
                    <div class="modal-body">
                        <div class="mb-3">
                            <label class="form-label">Chọn hành động:</label>
                            <select class="form-select" name="bulk_action" required>
                                <option value="">-- Chọn hành động --</option>
                                <option value="hoat_dong">Kích hoạt</option>
                                <option value="an">Ẩn sản phẩm</option>
                                <option value="het_hang">Đánh dấu hết hàng</option>
                                <option value="delete" class="text-danger">Xóa sản phẩm</option>
                            </select>
                        </div>
                        <div class="alert alert-warning">
                            <i class="fas fa-exclamation-triangle"></i>
                            Hành động này sẽ áp dụng cho tất cả sản phẩm đã chọn. Vui lòng kiểm tra kỹ trước khi thực hiện.
                        </div>
                        <div id="selectedProductsInfo"></div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Hủy</button>
                        <button type="submit" class="btn btn-primary">Thực hiện</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        // Select all functionality
        function toggleSelectAll() {
            const selectAllCheckbox = document.getElementById('selectAll');
            const productCheckboxes = document.querySelectorAll('.product-checkbox');
            
            selectAllCheckbox.checked = !selectAllCheckbox.checked;
            productCheckboxes.forEach(checkbox => {
                checkbox.checked = selectAllCheckbox.checked;
            });
            
            updateBulkActionButton();
        }

        // Update bulk action button state
        function updateBulkActionButton() {
            const selectedCount = document.querySelectorAll('.product-checkbox:checked').length;
            const bulkActionButton = document.querySelector('[data-bs-target="#bulkActionModal"]');
            
            if (selectedCount > 0) {
                bulkActionButton.textContent = `Hành động hàng loạt (${selectedCount})`;
                bulkActionButton.disabled = false;
            } else {
                bulkActionButton.textContent = 'Hành động hàng loạt';
                bulkActionButton.disabled = true;
            }
        }

        // Individual checkbox change handler
        document.addEventListener('change', function(e) {
            if (e.target.classList.contains('product-checkbox')) {
                updateBulkActionButton();
                
                // Update select all checkbox state
                const productCheckboxes = document.querySelectorAll('.product-checkbox');
                const checkedCount = document.querySelectorAll('.product-checkbox:checked').length;
                const selectAllCheckbox = document.getElementById('selectAll');
                
                selectAllCheckbox.checked = checkedCount === productCheckboxes.length;
                selectAllCheckbox.indeterminate = checkedCount > 0 && checkedCount < productCheckboxes.length;
            }
        });

        // Show bulk action modal
        document.getElementById('bulkActionModal').addEventListener('show.bs.modal', function() {
            const selectedIds = Array.from(document.querySelectorAll('.product-checkbox:checked')).map(cb => cb.value);
            const selectedCount = selectedIds.length;
            
            // Add hidden inputs for selected products
            const form = document.getElementById('bulkActionForm');
            const existingInputs = form.querySelectorAll('input[name="selected_products[]"]');
            existingInputs.forEach(input => input.remove());
            
            selectedIds.forEach(id => {
                const input = document.createElement('input');
                input.type = 'hidden';
                input.name = 'selected_products[]';
                input.value = id;
                form.appendChild(input);
            });
            
            // Update info text
            document.getElementById('selectedProductsInfo').innerHTML = 
                `<strong>Đã chọn ${selectedCount} sản phẩm</strong>`;
        });

        // Delete single product
        function deleteProduct(productId) {
            if (confirm('Bạn có chắc chắn muốn xóa sản phẩm này?\n\nLưu ý: Sản phẩm đã có đơn hàng sẽ không thể xóa.')) {
                window.location.href = `index.php?action=delete&id=${productId}`;
            }
        }

        // Initialize page
        document.addEventListener('DOMContentLoaded', function() {
            updateBulkActionButton();
            
            // Auto-dismiss alerts after 5 seconds
            setTimeout(() => {
                const alerts = document.querySelectorAll('.alert');
                alerts.forEach(alert => {
                    if (alert.querySelector('.btn-close')) {
                        alert.querySelector('.btn-close').click();
                    }
                });
            }, 5000);
        });

        // Quick search with Enter key
        document.querySelector('input[name="search"]').addEventListener('keypress', function(e) {
            if (e.key === 'Enter') {
                e.preventDefault();
                this.form.submit();
            }
        });

        // Prevent form submission on page reload
        if (window.history.replaceState) {
            const url = new URL(window.location);
            url.searchParams.delete('alert');
            url.searchParams.delete('type');
            window.history.replaceState({}, document.title, url);
        }
    </script>
</body>
</html>