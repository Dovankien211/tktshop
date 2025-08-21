<?php
// admin/sizes/index.php - Quản lý kích cỡ
require_once '../../config/database.php';
require_once '../../config/config.php';

requireLogin();

// Xử lý xóa kích cỡ
if (isset($_GET['delete'])) {
    $id = (int)$_GET['delete'];
    
    // Kiểm tra kích cỡ có được sử dụng không
    $check = $pdo->prepare("SELECT COUNT(*) FROM san_pham_bien_the WHERE kich_co_id = ?");
    $check->execute([$id]);
    
    if ($check->fetchColumn() > 0) {
        alert('Không thể xóa kích cỡ đang được sử dụng!', 'danger');
    } else {
        $stmt = $pdo->prepare("DELETE FROM kich_co WHERE id = ?");
        if ($stmt->execute([$id])) {
            alert('Xóa kích cỡ thành công!', 'success');
        } else {
            alert('Lỗi khi xóa kích cỡ!', 'danger');
        }
    }
    redirect('admin/sizes/');
}

// Lấy danh sách kích cỡ
try {
    $sizes = $pdo->query("
        SELECT kc.*, COUNT(spbt.id) as so_san_pham
        FROM kich_co kc
        LEFT JOIN san_pham_bien_the spbt ON kc.id = spbt.kich_co_id
        GROUP BY kc.id
        ORDER BY kc.thu_tu ASC, kc.id DESC
    ")->fetchAll();
} catch (Exception $e) {
    $sizes = [];
}
?>

<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Quản lý kích cỡ - <?= SITE_NAME ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
</head>
<body>
    <!-- ✅ Include Header -->
    <?php include '../layouts/header.php'; ?>
    
    <!-- ✅ Include Sidebar -->
    <?php include '../layouts/sidebar.php'; ?>
    
    <!-- ✅ Main Content -->
    <div class="main-content">
        <div class="content-wrapper">
            <div class="d-flex justify-content-between align-items-center mb-4">
                <h1><i class="fas fa-ruler me-2"></i>Quản lý kích cỡ</h1>
                <a href="add.php" class="btn btn-primary">
                    <i class="fas fa-plus"></i> Thêm kích cỡ
                </a>
            </div>

            <?php showAlert(); ?>

            <div class="card">
                <div class="card-header">
                    <h5><i class="fas fa-list me-2"></i>Danh sách kích cỡ</h5>
                </div>
                <div class="card-body">
                    <?php if (empty($sizes)): ?>
                        <div class="text-center py-5">
                            <i class="fas fa-ruler fa-3x text-muted mb-3"></i>
                            <h5>Chưa có kích cỡ nào</h5>
                            <p class="text-muted">Hãy thêm kích cỡ đầu tiên cho sản phẩm</p>
                            <a href="add.php" class="btn btn-primary">
                                <i class="fas fa-plus me-2"></i>Thêm kích cỡ đầu tiên
                            </a>
                        </div>
                    <?php else: ?>
                        <div class="table-responsive">
                            <table class="table table-hover">
                                <thead class="table-light">
                                    <tr>
                                        <th width="80">STT</th>
                                        <th>Tên kích cỡ</th>
                                        <th>Mã kích cỡ</th>
                                        <th>Mô tả</th>
                                        <th>Sản phẩm</th>
                                        <th>Thứ tự</th>
                                        <th>Trạng thái</th>
                                        <th width="120">Hành động</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($sizes as $index => $size): ?>
                                        <tr>
                                            <td><?= $index + 1 ?></td>
                                            <td>
                                                <strong><?= htmlspecialchars($size['ten_kich_co']) ?></strong>
                                            </td>
                                            <td>
                                                <code><?= htmlspecialchars($size['ma_kich_co']) ?></code>
                                            </td>
                                            <td>
                                                <?php if ($size['mo_ta']): ?>
                                                    <small class="text-muted"><?= htmlspecialchars(substr($size['mo_ta'], 0, 50)) ?><?= strlen($size['mo_ta']) > 50 ? '...' : '' ?></small>
                                                <?php else: ?>
                                                    <span class="text-muted">-</span>
                                                <?php endif; ?>
                                            </td>
                                            <td>
                                                <span class="badge bg-<?= $size['so_san_pham'] > 0 ? 'info' : 'secondary' ?>">
                                                    <?= $size['so_san_pham'] ?> sản phẩm
                                                </span>
                                            </td>
                                            <td><?= $size['thu_tu'] ?></td>
                                            <td>
                                                <?php if ($size['trang_thai'] == 'hoat_dong'): ?>
                                                    <span class="badge bg-success">Hoạt động</span>
                                                <?php else: ?>
                                                    <span class="badge bg-secondary">Ẩn</span>
                                                <?php endif; ?>
                                            </td>
                                            <td>
                                                <div class="btn-group btn-group-sm">
                                                    <a href="edit.php?id=<?= $size['id'] ?>" 
                                                       class="btn btn-outline-warning" title="Sửa">
                                                        <i class="fas fa-edit"></i>
                                                    </a>
                                                    <a href="?delete=<?= $size['id'] ?>" 
                                                       class="btn btn-outline-danger"
                                                       title="Xóa"
                                                       onclick="return confirm('Bạn có chắc muốn xóa kích cỡ này?')">
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

            <!-- Thống kê -->
            <div class="row mt-4">
                <div class="col-md-3">
                    <div class="card bg-primary text-white">
                        <div class="card-body text-center">
                            <h3><?= count($sizes) ?></h3>
                            <small>Tổng kích cỡ</small>
                        </div>
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="card bg-success text-white">
                        <div class="card-body text-center">
                            <h3><?= count(array_filter($sizes, fn($s) => $s['trang_thai'] == 'hoat_dong')) ?></h3>
                            <small>Đang hoạt động</small>
                        </div>
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="card bg-info text-white">
                        <div class="card-body text-center">
                            <h3><?= array_sum(array_column($sizes, 'so_san_pham')) ?></h3>
                            <small>Tổng sản phẩm</small>
                        </div>
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="card bg-warning text-white">
                        <div class="card-body text-center">
                            <h3><?= count(array_filter($sizes, fn($s) => $s['so_san_pham'] == 0)) ?></h3>
                            <small>Chưa sử dụng</small>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
