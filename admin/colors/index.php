<?php
// admin/colors/index.php - Quản lý màu sắc
require_once '../../config/database.php';
require_once '../../config/config.php';

requireLogin();

// Xử lý xóa màu sắc
if (isset($_GET['delete'])) {
    $id = (int)$_GET['delete'];
    
    // Kiểm tra màu sắc có được sử dụng không
    $check = $pdo->prepare("SELECT COUNT(*) FROM san_pham_bien_the WHERE mau_sac_id = ?");
    $check->execute([$id]);
    
    if ($check->fetchColumn() > 0) {
        alert('Không thể xóa màu sắc đang được sử dụng!', 'danger');
    } else {
        $stmt = $pdo->prepare("DELETE FROM mau_sac WHERE id = ?");
        if ($stmt->execute([$id])) {
            alert('Xóa màu sắc thành công!', 'success');
        } else {
            alert('Lỗi khi xóa màu sắc!', 'danger');
        }
    }
    redirect('admin/colors/');
}

// Lấy danh sách màu sắc
try {
    $colors = $pdo->query("
        SELECT ms.*, COUNT(spbt.id) as so_san_pham
        FROM mau_sac ms
        LEFT JOIN san_pham_bien_the spbt ON ms.id = spbt.mau_sac_id
        GROUP BY ms.id
        ORDER BY ms.thu_tu ASC, ms.id DESC
    ")->fetchAll();
} catch (Exception $e) {
    $colors = [];
}
?>

<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Quản lý màu sắc - <?= SITE_NAME ?></title>
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
                <h1><i class="fas fa-palette me-2"></i>Quản lý màu sắc</h1>
                <a href="add.php" class="btn btn-primary">
                    <i class="fas fa-plus"></i> Thêm màu sắc
                </a>
            </div>

            <?php showAlert(); ?>

            <div class="card">
                <div class="card-header">
                    <h5><i class="fas fa-list me-2"></i>Danh sách màu sắc</h5>
                </div>
                <div class="card-body">
                    <?php if (empty($colors)): ?>
                        <div class="text-center py-5">
                            <i class="fas fa-palette fa-3x text-muted mb-3"></i>
                            <h5>Chưa có màu sắc nào</h5>
                            <p class="text-muted">Hãy thêm màu sắc đầu tiên cho sản phẩm</p>
                            <a href="add.php" class="btn btn-primary">
                                <i class="fas fa-plus me-2"></i>Thêm màu sắc đầu tiên
                            </a>
                        </div>
                    <?php else: ?>
                        <div class="table-responsive">
                            <table class="table table-hover">
                                <thead class="table-light">
                                    <tr>
                                        <th width="80">STT</th>
                                        <th>Màu sắc</th>
                                        <th>Tên màu</th>
                                        <th>Mã màu</th>
                                        <th>Mô tả</th>
                                        <th>Sản phẩm</th>
                                        <th>Thứ tự</th>
                                        <th>Trạng thái</th>
                                        <th width="120">Hành động</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($colors as $index => $color): ?>
                                        <tr>
                                            <td><?= $index + 1 ?></td>
                                            <td>
                                                <div class="d-flex align-items-center">
                                                    <div class="color-preview me-2" 
                                                         style="width: 30px; height: 30px; border-radius: 50%; border: 2px solid #ddd; background-color: <?= htmlspecialchars($color['ma_hex'] ?? '#ffffff') ?>"></div>
                                                    <div>
                                                        <?php if ($color['hinh_anh']): ?>
                                                            <img src="<?= uploadsUrl('colors/' . $color['hinh_anh']) ?>" 
                                                                 alt="<?= htmlspecialchars($color['ten_mau_sac']) ?>"
                                                                 style="width: 30px; height: 30px; object-fit: cover;"
                                                                 class="rounded">
                                                        <?php endif; ?>
                                                    </div>
                                                </div>
                                            </td>
                                            <td>
                                                <strong><?= htmlspecialchars($color['ten_mau_sac']) ?></strong>
                                            </td>
                                            <td>
                                                <code><?= htmlspecialchars($color['ma_mau_sac']) ?></code>
                                                <?php if ($color['ma_hex']): ?>
                                                    <br><small class="text-muted"><?= htmlspecialchars($color['ma_hex']) ?></small>
                                                <?php endif; ?>
                                            </td>
                                            <td>
                                                <?php if ($color['mo_ta']): ?>
                                                    <small class="text-muted"><?= htmlspecialchars(substr($color['mo_ta'], 0, 50)) ?><?= strlen($color['mo_ta']) > 50 ? '...' : '' ?></small>
                                                <?php else: ?>
                                                    <span class="text-muted">-</span>
                                                <?php endif; ?>
                                            </td>
                                            <td>
                                                <span class="badge bg-<?= $color['so_san_pham'] > 0 ? 'info' : 'secondary' ?>">
                                                    <?= $color['so_san_pham'] ?> sản phẩm
                                                </span>
                                            </td>
                                            <td><?= $color['thu_tu'] ?></td>
                                            <td>
                                                <?php if ($color['trang_thai'] == 'hoat_dong'): ?>
                                                    <span class="badge bg-success">Hoạt động</span>
                                                <?php else: ?>
                                                    <span class="badge bg-secondary">Ẩn</span>
                                                <?php endif; ?>
                                            </td>
                                            <td>
                                                <div class="btn-group btn-group-sm">
                                                    <a href="edit.php?id=<?= $color['id'] ?>" 
                                                       class="btn btn-outline-warning" title="Sửa">
                                                        <i class="fas fa-edit"></i>
                                                    </a>
                                                    <a href="?delete=<?= $color['id'] ?>" 
                                                       class="btn btn-outline-danger"
                                                       title="Xóa"
                                                       onclick="return confirm('Bạn có chắc muốn xóa màu sắc này?')">
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
                            <h3><?= count($colors) ?></h3>
                            <small>Tổng màu sắc</small>
                        </div>
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="card bg-success text-white">
                        <div class="card-body text-center">
                            <h3><?= count(array_filter($colors, fn($c) => $c['trang_thai'] == 'hoat_dong')) ?></h3>
                            <small>Đang hoạt động</small>
                        </div>
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="card bg-info text-white">
                        <div class="card-body text-center">
                            <h3><?= array_sum(array_column($colors, 'so_san_pham')) ?></h3>
                            <small>Tổng sản phẩm</small>
                        </div>
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="card bg-warning text-white">
                        <div class="card-body text-center">
                            <h3><?= count(array_filter($colors, fn($c) => $c['so_san_pham'] == 0)) ?></h3>
                            <small>Chưa sử dụng</small>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Color Grid Preview -->
            <?php if (!empty($colors)): ?>
            <div class="card mt-4">
                <div class="card-header">
                    <h5><i class="fas fa-th me-2"></i>Xem trước màu sắc</h5>
                </div>
                <div class="card-body">
                    <div class="row">
                        <?php foreach ($colors as $color): ?>
                            <?php if ($color['trang_thai'] == 'hoat_dong'): ?>
                                <div class="col-md-2 col-4 mb-3">
                                    <div class="text-center">
                                        <div class="color-preview mx-auto mb-2" 
                                             style="width: 60px; height: 60px; border-radius: 10px; border: 2px solid #ddd; background-color: <?= htmlspecialchars($color['ma_hex'] ?? '#ffffff') ?>"></div>
                                        <div class="fw-bold"><?= htmlspecialchars($color['ten_mau_sac']) ?></div>
                                        <small class="text-muted"><?= htmlspecialchars($color['ma_hex'] ?? '') ?></small>
                                    </div>
                                </div>
                            <?php endif; ?>
                        <?php endforeach; ?>
                    </div>
                </div>
            </div>
            <?php endif; ?>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
