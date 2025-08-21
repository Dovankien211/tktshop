<?php
// admin/colors/index.php
/**
 * Quản lý màu sắc giày - Fixed redirect loop issue
 */

require_once '../../config/database.php';
require_once '../../config/config.php';

requireLogin();

// Xử lý xóa màu sắc
if (isset($_GET['delete'])) {
    $id = (int)$_GET['delete'];
    
    // Kiểm tra màu sắc có đang được sử dụng không
    $check = $pdo->prepare("SELECT COUNT(*) FROM bien_the_san_pham WHERE mau_sac_id = ?");
    $check->execute([$id]);
    
    if ($check->fetchColumn() > 0) {
        alert('Không thể xóa màu sắc đang được sử dụng trong sản phẩm!', 'danger');
    } else {
        $stmt = $pdo->prepare("DELETE FROM mau_sac WHERE id = ?");
        if ($stmt->execute([$id])) {
            alert('Xóa màu sắc thành công!', 'success');
        } else {
            alert('Lỗi khi xóa màu sắc!', 'danger');
        }
    }
    
    // Redirect về chính trang này để tránh loop
    header('Location: /tktshop/admin/colors/index.php');
    exit;
}

// Lấy danh sách màu sắc với thống kê
$colors = $pdo->query("
    SELECT ms.*, 
           COUNT(DISTINCT bsp.id) as so_bien_the,
           COUNT(DISTINCT sp.id) as so_san_pham
    FROM mau_sac ms
    LEFT JOIN bien_the_san_pham bsp ON ms.id = bsp.mau_sac_id AND bsp.trang_thai = 'hoat_dong'
    LEFT JOIN san_pham_chinh sp ON bsp.san_pham_id = sp.id AND sp.trang_thai = 'hoat_dong'
    GROUP BY ms.id
    ORDER BY ms.thu_tu_hien_thi ASC, ms.id ASC
")->fetchAll();
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
    <div class="container-fluid">
        <div class="row">
            <!-- Sidebar -->
            <?php include '../layouts/sidebar.php'; ?>
            
            <!-- Main content -->
            <div class="col-md-10">
                <div class="d-flex justify-content-between align-items-center py-3">
                    <h2>Quản lý màu sắc giày</h2>
                    <a href="/tktshop/admin/colors/create.php" class="btn btn-primary">
                        <i class="fas fa-plus"></i> Thêm màu sắc
                    </a>
                </div>

                <?php showAlert(); ?>

                <div class="row mb-4">
                    <div class="col-md-12">
                        <div class="card">
                            <div class="card-body">
                                <h5 class="card-title">Hướng dẫn quản lý màu sắc</h5>
                                <div class="row">
                                    <div class="col-md-6">
                                        <ul class="list-unstyled">
                                            <li><i class="fas fa-palette text-primary me-2"></i>Màu sắc được sắp xếp theo thứ tự hiển thị</li>
                                            <li><i class="fas fa-warning text-warning me-2"></i>Không thể xóa màu đã có sản phẩm sử dụng</li>
                                            <li><i class="fas fa-eye text-success me-2"></i>Màu ẩn sẽ không hiển thị cho khách hàng</li>
                                        </ul>
                                    </div>
                                    <div class="col-md-6">
                                        <ul class="list-unstyled">
                                            <li><i class="fas fa-code text-info me-2"></i>Mã màu sử dụng định dạng HEX (#RRGGBB)</li>
                                            <li><i class="fas fa-sort text-secondary me-2"></i>Thay đổi thứ tự để sắp xếp hiển thị</li>
                                            <li><i class="fas fa-cubes text-purple me-2"></i>Xem biến thể sản phẩm sử dụng màu</li>
                                        </ul>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="card">
                    <div class="card-body">
                        <div class="table-responsive">
                            <table class="table table-striped">
                                <thead>
                                    <tr>
                                        <th>ID</th>
                                        <th>Màu sắc</th>
                                        <th>Tên màu</th>
                                        <th>Mã màu</th>
                                        <th>Thứ tự</th>
                                        <th>Biến thể</th>
                                        <th>Sản phẩm</th>
                                        <th>Trạng thái</th>
                                        <th>Hành động</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php if (empty($colors)): ?>
                                        <tr>
                                            <td colspan="9" class="text-center">
                                                <div class="py-4">
                                                    <i class="fas fa-palette fa-3x text-muted mb-3"></i>
                                                    <h5>Chưa có màu sắc nào</h5>
                                                    <p class="text-muted">Hãy thêm màu sắc đầu tiên cho hệ thống</p>
                                                    <a href="/tktshop/admin/colors/create.php" class="btn btn-primary">
                                                        <i class="fas fa-plus"></i> Thêm màu sắc
                                                    </a>
                                                </div>
                                            </td>
                                        </tr>
                                    <?php else: ?>
                                        <?php foreach ($colors as $color): ?>
                                            <tr>
                                                <td><?= $color['id'] ?></td>
                                                <td>
                                                    <div class="d-flex align-items-center">
                                                        <div class="rounded-circle me-3" 
                                                             style="width: 40px; height: 40px; background-color: <?= htmlspecialchars($color['ma_mau']) ?>; border: 2px solid #dee2e6;">
                                                        </div>
                                                        <div class="rounded-circle" 
                                                             style="width: 20px; height: 20px; background-color: <?= htmlspecialchars($color['ma_mau']) ?>; border: 1px solid #ccc;">
                                                        </div>
                                                    </div>
                                                </td>
                                                <td>
                                                    <strong><?= htmlspecialchars($color['ten_mau']) ?></strong>
                                                </td>
                                                <td>
                                                    <code><?= strtoupper($color['ma_mau']) ?></code>
                                                </td>
                                                <td>
                                                    <span class="badge bg-secondary"><?= $color['thu_tu_hien_thi'] ?></span>
                                                </td>
                                                <td>
                                                    <span class="badge bg-<?= $color['so_bien_the'] > 0 ? 'success' : 'secondary' ?>">
                                                        <?= $color['so_bien_the'] ?> biến thể
                                                    </span>
                                                </td>
                                                <td>
                                                    <span class="badge bg-<?= $color['so_san_pham'] > 0 ? 'info' : 'secondary' ?>">
                                                        <?= $color['so_san_pham'] ?> sản phẩm
                                                    </span>
                                                </td>
                                                <td>
                                                    <?php if ($color['trang_thai'] == 'hoat_dong'): ?>
                                                        <span class="badge bg-success">
                                                            <i class="fas fa-eye me-1"></i>Hiển thị
                                                        </span>
                                                    <?php else: ?>
                                                        <span class="badge bg-secondary">
                                                            <i class="fas fa-eye-slash me-1"></i>Ẩn
                                                        </span>
                                                    <?php endif; ?>
                                                </td>
                                                <td>
                                                    <div class="btn-group btn-group-sm">
                                                        <a href="/tktshop/admin/colors/edit.php?id=<?= $color['id'] ?>" 
                                                           class="btn btn-warning" title="Sửa">
                                                            <i class="fas fa-edit"></i>
                                                        </a>
                                                        <a href="/tktshop/admin/colors/index.php?delete=<?= $color['id'] ?>" 
                                                           class="btn btn-danger"
                                                           title="Xóa"
                                                           onclick="return confirm('Bạn có chắc muốn xóa màu sắc này?\n\nLưu ý: Không thể xóa nếu đã có sản phẩm sử dụng.')">
                                                            <i class="fas fa-trash"></i>
                                                        </a>
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

                <!-- Quick Add Form -->
                <?php if (!empty($colors)): ?>
                    <div class="card mt-4">
                        <div class="card-header">
                            <h5>Thêm nhanh màu sắc</h5>
                        </div>
                        <div class="card-body">
                            <form method="POST" action="/tktshop/admin/colors/create.php" class="row g-3">
                                <div class="col-md-3">
                                    <input type="text" 
                                           class="form-control" 
                                           name="ten_mau" 
                                           placeholder="Tên màu (VD: Đỏ đậm)"
                                           required>
                                </div>
                                <div class="col-md-3">
                                    <div class="input-group">
                                        <input type="color" 
                                               class="form-control form-control-color" 
                                               name="ma_mau_picker" 
                                               style="width: 60px;">
                                        <input type="text" 
                                               class="form-control" 
                                               name="ma_mau" 
                                               placeholder="#FF0000"
                                               pattern="^#[0-9A-Fa-f]{6}$">
                                    </div>
                                </div>
                                <div class="col-md-2">
                                    <input type="number" 
                                           class="form-control" 
                                           name="thu_tu_hien_thi" 
                                           placeholder="Thứ tự"
                                           value="<?= max(array_column($colors, 'thu_tu_hien_thi')) + 1 ?>">
                                </div>
                                <div class="col-md-2">
                                    <button type="submit" class="btn btn-primary">
                                        <i class="fas fa-plus"></i> Thêm nhanh
                                    </button>
                                </div>
                            </form>
                        </div>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
    
    <script>
        // Sync color picker with text input in quick add form
        document.addEventListener('DOMContentLoaded', function() {
            const colorPicker = document.querySelector('input[name="ma_mau_picker"]');
            const colorText = document.querySelector('input[name="ma_mau"]');
            
            if (colorPicker && colorText) {
                colorPicker.addEventListener('change', function() {
                    colorText.value = this.value.toUpperCase();
                });
                
                colorText.addEventListener('input', function() {
                    if (/^#[0-9A-Fa-f]{6}$/.test(this.value)) {
                        colorPicker.value = this.value;
                    }
                });
            }
        });
    </script>
</body>
</html>