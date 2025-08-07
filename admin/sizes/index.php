<?php
// admin/sizes/index.php
/**
 * Quản lý kích cỡ giày
 */

require_once '../../config/database.php';
require_once '../../config/config.php';

requireLogin();

// Xử lý xóa kích cỡ
if (isset($_GET['delete'])) {
    $id = (int)$_GET['delete'];
    
    // Kiểm tra kích cỡ có đang được sử dụng không
    $check = $pdo->prepare("SELECT COUNT(*) FROM bien_the_san_pham WHERE kich_co_id = ?");
    $check->execute([$id]);
    
    if ($check->fetchColumn() > 0) {
        alert('Không thể xóa kích cỡ đang được sử dụng trong sản phẩm!', 'danger');
    } else {
        $stmt = $pdo->prepare("DELETE FROM kich_co WHERE id = ?");
        if ($stmt->execute([$id])) {
            alert('Xóa kích cỡ thành công!', 'success');
        } else {
            alert('Lỗi khi xóa kích cỡ!', 'danger');
        }
    }
    redirect('/tktshop/admin/sizes/');
}

// Lấy danh sách kích cỡ với thống kê sử dụng
$sizes = $pdo->query("
    SELECT kc.*, 
           COUNT(DISTINCT bsp.id) as so_bien_the,
           COUNT(DISTINCT bsp.san_pham_id) as so_san_pham,
           SUM(bsp.so_luong_ton_kho) as tong_ton_kho,
           SUM(bsp.so_luong_da_ban) as tong_da_ban
    FROM kich_co kc
    LEFT JOIN bien_the_san_pham bsp ON kc.id = bsp.kich_co_id AND bsp.trang_thai = 'hoat_dong'
    GROUP BY kc.id
    ORDER BY kc.thu_tu_sap_xep ASC, kc.kich_co ASC
")->fetchAll();
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
    <div class="container-fluid">
        <div class="row">
            <!-- Sidebar -->
            <?php include '../layouts/sidebar.php'; ?>
            
            <!-- Main content -->
            <div class="col-md-10">
                <div class="d-flex justify-content-between align-items-center py-3">
                    <h2>Quản lý kích cỡ giày</h2>
                    <a href="/tktshop/admin/sizes/create.php" class="btn btn-primary">
                        <i class="fas fa-plus"></i> Thêm kích cỡ
                    </a>
                </div>

                <?php showAlert(); ?>

                <div class="card">
                    <div class="card-body">
                        <div class="table-responsive">
                            <table class="table table-striped">
                                <thead>
                                    <tr>
                                        <th>ID</th>
                                        <th>Kích cỡ</th>
                                        <th>Mô tả</th>
                                        <th>Biến thể</th>
                                        <th>Sản phẩm</th>
                                        <th>Tồn kho</th>
                                        <th>Đã bán</th>
                                        <th>Thứ tự</th>
                                        <th>Trạng thái</th>
                                        <th>Hành động</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php if (empty($sizes)): ?>
                                        <tr>
                                            <td colspan="10" class="text-center">Chưa có kích cỡ nào</td>
                                        </tr>
                                    <?php else: ?>
                                        <?php foreach ($sizes as $size): ?>
                                            <tr>
                                                <td><?= $size['id'] ?></td>
                                                <td>
                                                    <span class="badge bg-dark fs-6">Size <?= htmlspecialchars($size['kich_co']) ?></span>
                                                </td>
                                                <td><?= htmlspecialchars($size['mo_ta']) ?></td>
                                                <td>
                                                    <span class="badge bg-<?= $size['so_bien_the'] > 0 ? 'info' : 'secondary' ?>">
                                                        <?= $size['so_bien_the'] ?> biến thể
                                                    </span>
                                                </td>
                                                <td>
                                                    <span class="badge bg-<?= $size['so_san_pham'] > 0 ? 'primary' : 'secondary' ?>">
                                                        <?= $size['so_san_pham'] ?> sản phẩm
                                                    </span>
                                                </td>
                                                <td>
                                                    <?php if ($size['tong_ton_kho'] > 0): ?>
                                                        <span class="text-success fw-bold"><?= $size['tong_ton_kho'] ?></span>
                                                    <?php else: ?>
                                                        <span class="text-muted">0</span>
                                                    <?php endif; ?>
                                                </td>
                                                <td>
                                                    <?php if ($size['tong_da_ban'] > 0): ?>
                                                        <span class="text-primary fw-bold"><?= $size['tong_da_ban'] ?></span>
                                                    <?php else: ?>
                                                        <span class="text-muted">0</span>
                                                    <?php endif; ?>
                                                </td>
                                                <td><?= $size['thu_tu_sap_xep'] ?></td>
                                                <td>
                                                    <?php if ($size['trang_thai'] == 'hoat_dong'): ?>
                                                        <span class="badge bg-success">Hoạt động</span>
                                                    <?php else: ?>
                                                        <span class="badge bg-secondary">Ẩn</span>
                                                    <?php endif; ?>
                                                </td>
                                                <td>
                                                    <div class="btn-group btn-group-sm">
                                                        <a href="/tktshop/admin/sizes/edit.php?id=<?= $size['id'] ?>" 
                                                           class="btn btn-warning" title="Sửa">
                                                            <i class="fas fa-edit"></i>
                                                        </a>
                                                        <?php if ($size['so_bien_the'] == 0): ?>
                                                            <a href="/tktshop/admin/sizes/?delete=<?= $size['id'] ?>" 
                                                               class="btn btn-danger"
                                                               title="Xóa"
                                                               onclick="return confirm('Bạn có chắc muốn xóa kích cỡ này?')">
                                                                <i class="fas fa-trash"></i>
                                                            </a>
                                                        <?php else: ?>
                                                            <button class="btn btn-danger" disabled title="Không thể xóa - đang được sử dụng">
                                                                <i class="fas fa-trash"></i>
                                                            </button>
                                                        <?php endif; ?>
                                                    </div>
                                                </td>
                                            </tr>
                                        <?php endforeach; ?>
                                    <?php endif; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>

                <!-- Thống kê tổng quan -->
                <?php
                $total_stats = $pdo->query("
                    SELECT 
                        COUNT(*) as tong_kich_co,
                        COUNT(CASE WHEN trang_thai = 'hoat_dong' THEN 1 END) as kich_co_hoat_dong,
                        COUNT(CASE WHEN trang_thai = 'an' THEN 1 END) as kich_co_an
                    FROM kich_co
                ")->fetch();
                ?>
                
                <div class="row mt-4">
                    <div class="col-md-3">
                        <div class="card bg-primary text-white">
                            <div class="card-body text-center">
                                <div class="h3"><?= $total_stats['tong_kich_co'] ?></div>
                                <div>Tổng kích cỡ</div>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="card bg-success text-white">
                            <div class="card-body text-center">
                                <div class="h3"><?= $total_stats['kich_co_hoat_dong'] ?></div>
                                <div>Đang hoạt động</div>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="card bg-secondary text-white">
                            <div class="card-body text-center">
                                <div class="h3"><?= $total_stats['kich_co_an'] ?></div>
                                <div>Đã ẩn</div>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="card bg-info text-white">
                            <div class="card-body text-center">
                                <?php
                                $used_sizes = $pdo->query("
                                    SELECT COUNT(DISTINCT kich_co_id) 
                                    FROM bien_the_san_pham 
                                    WHERE trang_thai = 'hoat_dong'
                                ")->fetchColumn();
                                ?>
                                <div class="h3"><?= $used_sizes ?></div>
                                <div>Đang sử dụng</div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>