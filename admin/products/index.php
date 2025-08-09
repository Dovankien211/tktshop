<?php
session_start();
require_once '../../config/database.php';
require_once '../../config/config.php';

// Tạm thời bypass login check để test
if (!isset($_SESSION['user_id'])) {
    $_SESSION['user_id'] = 1;
    $_SESSION['role'] = 'admin';
    $_SESSION['admin_name'] = 'Test Admin';
}

// Lấy danh sách sản phẩm đơn giản
$products = [];
try {
    $stmt = $pdo->query("SELECT * FROM san_pham_chinh ORDER BY ngay_tao DESC LIMIT 20");
    $products = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) {
    $error = "Lỗi: " . $e->getMessage();
}
?>

<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Danh sách sản phẩm - TKT Shop Admin</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
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
                <div class="p-4">
                    <div class="d-flex justify-content-between align-items-center mb-4">
                        <h1 class="h2">📦 Danh sách sản phẩm</h1>
                        <a href="add.php" class="btn btn-primary">
                            <i class="fas fa-plus"></i> Thêm sản phẩm
                        </a>
                    </div>

                    <?php if (isset($error)): ?>
                        <div class="alert alert-danger">
                            <i class="fas fa-exclamation-triangle"></i> <?= $error ?>
                        </div>
                    <?php endif; ?>

                    <!-- Stats Cards -->
                    <div class="row mb-4">
                        <div class="col-md-3">
                            <div class="card bg-primary text-white">
                                <div class="card-body text-center">
                                    <i class="fas fa-box fa-2x mb-2"></i>
                                    <h4><?= count($products) ?></h4>
                                    <small>Tổng sản phẩm</small>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-3">
                            <div class="card bg-success text-white">
                                <div class="card-body text-center">
                                    <i class="fas fa-eye fa-2x mb-2"></i>
                                    <h4><?php 
                                        $active = 0;
                                        foreach($products as $p) if($p['trang_thai'] == 'hoat_dong') $active++;
                                        echo $active;
                                    ?></h4>
                                    <small>Đang hiển thị</small>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-3">
                            <div class="card bg-warning text-white">
                                <div class="card-body text-center">
                                    <i class="fas fa-exclamation-triangle fa-2x mb-2"></i>
                                    <h4>0</h4>
                                    <small>Sắp hết hàng</small>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-3">
                            <div class="card bg-info text-white">
                                <div class="card-body text-center">
                                    <i class="fas fa-plus fa-2x mb-2"></i>
                                    <h4>0</h4>
                                    <small>Mới hôm nay</small>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Search & Filter -->
                    <div class="card mb-4">
                        <div class="card-body">
                            <form method="GET" class="row g-3">
                                <div class="col-md-4">
                                    <input type="text" class="form-control" name="search" placeholder="Tìm kiếm sản phẩm..." value="<?= $_GET['search'] ?? '' ?>">
                                </div>
                                <div class="col-md-3">
                                    <select class="form-select" name="status">
                                        <option value="">Tất cả trạng thái</option>
                                        <option value="hoat_dong" <?= ($_GET['status'] ?? '') == 'hoat_dong' ? 'selected' : '' ?>>Hoạt động</option>
                                        <option value="an" <?= ($_GET['status'] ?? '') == 'an' ? 'selected' : '' ?>>Ẩn</option>
                                        <option value="het_hang" <?= ($_GET['status'] ?? '') == 'het_hang' ? 'selected' : '' ?>>Hết hàng</option>
                                    </select>
                                </div>
                                <div class="col-md-3">
                                    <button type="submit" class="btn btn-primary me-2">
                                        <i class="fas fa-search"></i> Tìm kiếm
                                    </button>
                                    <a href="index.php" class="btn btn-outline-secondary">
                                        <i class="fas fa-refresh"></i> Reset
                                    </a>
                                </div>
                                <div class="col-md-2">
                                    <a href="add.php" class="btn btn-success w-100">
                                        <i class="fas fa-plus"></i> Thêm mới
                                    </a>
                                </div>
                            </form>
                        </div>
                    </div>

                    <!-- Products Table -->
                    <div class="card">
                        <div class="card-header">
                            <h5 class="mb-0">
                                <i class="fas fa-list me-2"></i>Danh sách sản phẩm (<?= count($products) ?>)
                            </h5>
                        </div>
                        <div class="card-body">
                            <?php if (empty($products)): ?>
                                <div class="text-center py-5">
                                    <i class="fas fa-box-open fa-3x text-muted mb-3"></i>
                                    <h5 class="text-muted">Chưa có sản phẩm nào</h5>
                                    <p class="text-muted">Hãy thêm sản phẩm đầu tiên cho cửa hàng của bạn</p>
                                    <a href="add.php" class="btn btn-primary">
                                        <i class="fas fa-plus"></i> Thêm sản phẩm đầu tiên
                                    </a>
                                </div>
                            <?php else: ?>
                                <div class="table-responsive">
                                    <table class="table table-hover align-middle">
                                        <thead class="table-dark">
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
                                                            <img src="/tktshop/uploads/products/<?= $product['hinh_anh_chinh'] ?>" 
                                                                 alt="<?= htmlspecialchars($product['ten_san_pham']) ?>" 
                                                                 class="img-thumbnail" style="width: 60px; height: 60px; object-fit: cover;">
                                                        <?php else: ?>
                                                            <div class="bg-light d-flex align-items-center justify-content-center" 
                                                                 style="width: 60px; height: 60px;">
                                                                <i class="fas fa-image text-muted"></i>
                                                            </div>
                                                        <?php endif; ?>
                                                    </td>
                                                    <td>
                                                        <div>
                                                            <strong><?= htmlspecialchars($product['ten_san_pham']) ?></strong><br>
                                                            <small class="text-muted">Mã: <?= htmlspecialchars($product['ma_san_pham']) ?></small>
                                                        </div>
                                                    </td>
                                                    <td>
                                                        <span class="badge bg-secondary">
                                                            <?= htmlspecialchars($product['thuong_hieu']) ?>
                                                        </span>
                                                    </td>
                                                    <td>
                                                        <strong class="text-primary"><?= number_format($product['gia_goc']) ?>₫</strong>
                                                        <?php if ($product['gia_khuyen_mai'] && $product['gia_khuyen_mai'] < $product['gia_goc']): ?>
                                                            <br><small class="text-success"><?= number_format($product['gia_khuyen_mai']) ?>₫</small>
                                                        <?php endif; ?>
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
                                                        <span class="badge bg-<?= $status['class'] ?>">
                                                            <?= $status['text'] ?>
                                                        </span>
                                                    </td>
                                                    <td>
                                                        <small><?= date('d/m/Y H:i', strtotime($product['ngay_tao'])) ?></small>
                                                    </td>
                                                    <td>
                                                        <div class="btn-group btn-group-sm">
                                                            <a href="variants.php?product_id=<?= $product['id'] ?>" 
                                                               class="btn btn-outline-primary" title="Quản lý biến thể">
                                                                <i class="fas fa-cubes"></i>
                                                            </a>
                                                            <a href="edit.php?id=<?= $product['id'] ?>" 
                                                               class="btn btn-outline-warning" title="Sửa">
                                                                <i class="fas fa-edit"></i>
                                                            </a>
                                                            <a href="delete.php?id=<?= $product['id'] ?>" 
                                                               class="btn btn-outline-danger" title="Xóa">
                                                                <i class="fas fa-trash"></i>
                                                            </a>
                                                        </div>
                                                    </td>
                                                </tr>
                                            <?php endforeach; ?>
                                        </tbody>
                                    </table>
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>

                    <!-- Debug Info -->
                    <div class="mt-4">
                        <div class="card bg-light">
                            <div class="card-body">
                                <h6>🔧 Debug Info:</h6>
                                <small>
                                    File: <?= __FILE__ ?><br>
                                    URL: <?= $_SERVER['REQUEST_URI'] ?><br>
                                    Session: <?= isset($_SESSION['user_id']) ? 'OK' : 'None' ?><br>
                                    Products found: <?= count($products) ?><br>
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
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        // Remove the old deleteProduct function and use direct links
        console.log('Products index loaded successfully');
        
        // Auto refresh every 30 seconds for development
        setTimeout(() => {
            console.log('Auto refresh for development');
        }, 30000);
        
        // Show success/error messages from URL params
        const urlParams = new URLSearchParams(window.location.search);
        const success = urlParams.get('success');
        const error = urlParams.get('error');
        
        if (success) {
            const alertDiv = document.createElement('div');
            alertDiv.className = 'alert alert-success alert-dismissible fade show';
            alertDiv.innerHTML = `
                <i class="fas fa-check-circle"></i> ${success}
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            `;
            document.querySelector('.p-4').insertBefore(alertDiv, document.querySelector('.p-4').firstChild);
        }
        
        if (error) {
            const alertDiv = document.createElement('div');
            alertDiv.className = 'alert alert-danger alert-dismissible fade show';
            alertDiv.innerHTML = `
                <i class="fas fa-exclamation-triangle"></i> ${error}
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            `;
            document.querySelector('.p-4').insertBefore(alertDiv, document.querySelector('.p-4').firstChild);
        }
    </script>
</body>
</html>