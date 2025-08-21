<?php
// admin/categories/index.php - ĐÃ SỬA LAYOUT
/**
 * Quản lý danh mục giày
 */

require_once '../../config/database.php';
require_once '../../config/config.php';

requireLogin();

// Xử lý xóa danh mục
if (isset($_GET['delete'])) {
    $id = (int)$_GET['delete'];
    
    // Kiểm tra danh mục có sản phẩm không
    $check = $pdo->prepare("SELECT COUNT(*) FROM san_pham_chinh WHERE danh_muc_id = ?");
    $check->execute([$id]);
    
    if ($check->fetchColumn() > 0) {
        alert('Không thể xóa danh mục đang có sản phẩm!', 'danger');
    } else {
        // Kiểm tra có danh mục con không
        $check_children = $pdo->prepare("SELECT COUNT(*) FROM danh_muc_giay WHERE danh_muc_cha_id = ?");
        $check_children->execute([$id]);
        
        if ($check_children->fetchColumn() > 0) {
            alert('Không thể xóa danh mục đang có danh mục con!', 'danger');
        } else {
            $stmt = $pdo->prepare("DELETE FROM danh_muc_giay WHERE id = ?");
            if ($stmt->execute([$id])) {
                alert('Xóa danh mục thành công!', 'success');
            } else {
                alert('Lỗi khi xóa danh mục!', 'danger');
            }
        }
    }
    redirect('admin/categories/');
}

// Lấy danh sách danh mục với thông tin cha và số sản phẩm
$categories = $pdo->query("
    SELECT dm.*, 
           dm_cha.ten_danh_muc as ten_danh_muc_cha,
           COUNT(sp.id) as so_san_pham
    FROM danh_muc_giay dm
    LEFT JOIN danh_muc_giay dm_cha ON dm.danh_muc_cha_id = dm_cha.id
    LEFT JOIN san_pham_chinh sp ON dm.id = sp.danh_muc_id AND sp.trang_thai = 'hoat_dong'
    GROUP BY dm.id
    ORDER BY dm.danh_muc_cha_id IS NULL DESC, dm.thu_tu_hien_thi ASC, dm.id ASC
")->fetchAll();
?>

<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Quản lý danh mục - <?= SITE_NAME ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <style>
        .category-tree {
            padding-left: 0;
        }
        .category-child {
            padding-left: 30px;
            border-left: 2px solid #dee2e6;
            margin-left: 10px;
        }
        .category-parent {
            background-color: #f8f9fa;
        }
    </style>
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
                <h1><i class="fas fa-tags me-2"></i>Quản lý danh mục giày</h1>
                <a href="<?= adminUrl('categories/create.php') ?>" class="btn btn-primary">
                    <i class="fas fa-plus"></i> Thêm danh mục
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
                                    <th>Tên danh mục</th>
                                    <th>Slug</th>
                                    <th>Danh mục cha</th>
                                    <th>Sản phẩm</th>
                                    <th>Thứ tự</th>
                                    <th>Trạng thái</th>
                                    <th>Hành động</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if (empty($categories)): ?>
                                    <tr>
                                        <td colspan="8" class="text-center">Chưa có danh mục nào</td>
                                    </tr>
                                <?php else: ?>
                                    <?php foreach ($categories as $category): ?>
                                        <tr class="<?= !$category['danh_muc_cha_id'] ? 'category-parent' : '' ?>">
                                            <td><?= $category['id'] ?></td>
                                            <td>
                                                <?php if ($category['danh_muc_cha_id']): ?>
                                                    <i class="fas fa-level-up-alt fa-rotate-90 text-muted me-2"></i>
                                                <?php endif; ?>
                                                
                                                <?php if ($category['hinh_anh']): ?>
                                                    <img src="<?= uploadsUrl('categories/' . $category['hinh_anh']) ?>" 
                                                         alt="<?= htmlspecialchars($category['ten_danh_muc']) ?>"
                                                         style="width: 30px; height: 30px; object-fit: cover;"
                                                         class="rounded me-2">
                                                <?php endif; ?>
                                                
                                                <strong><?= htmlspecialchars($category['ten_danh_muc']) ?></strong>
                                                
                                                <?php if ($category['mo_ta']): ?>
                                                    <br><small class="text-muted"><?= htmlspecialchars(substr($category['mo_ta'], 0, 50)) ?>...</small>
                                                <?php endif; ?>
                                            </td>
                                            <td>
                                                <code><?= htmlspecialchars($category['slug']) ?></code>
                                            </td>
                                            <td>
                                                <?php if ($category['ten_danh_muc_cha']): ?>
                                                    <span class="badge bg-secondary">
                                                        <?= htmlspecialchars($category['ten_danh_muc_cha']) ?>
                                                    </span>
                                                <?php else: ?>
                                                    <span class="text-muted">Danh mục gốc</span>
                                                <?php endif; ?>
                                            </td>
                                            <td>
                                                <span class="badge bg-<?= $category['so_san_pham'] > 0 ? 'success' : 'secondary' ?>">
                                                    <?= $category['so_san_pham'] ?> sản phẩm
                                                </span>
                                            </td>
                                            <td><?= $category['thu_tu_hien_thi'] ?></td>
                                            <td>
                                                <?php if ($category['trang_thai'] == 'hoat_dong'): ?>
                                                    <span class="badge bg-success">Hoạt động</span>
                                                <?php else: ?>
                                                    <span class="badge bg-secondary">Ẩn</span>
                                                <?php endif; ?>
                                            </td>
                                            <td>
                                                <div class="btn-group btn-group-sm">
                                                    <a href="<?= adminUrl('categories/edit.php?id=' . $category['id']) ?>" 
                                                       class="btn btn-warning" title="Sửa">
                                                        <i class="fas fa-edit"></i>
                                                    </a>
                                                    <a href="<?= adminUrl('categories/?delete=' . $category['id']) ?>" 
                                                       class="btn btn-danger"
                                                       title="Xóa"
                                                       onclick="return confirm('Bạn có chắc muốn xóa danh mục này?')">
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
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
