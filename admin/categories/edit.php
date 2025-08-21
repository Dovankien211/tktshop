<?php
// admin/categories/edit.php - Fixed version
/**
 * Chỉnh sửa danh mục giày
 */

require_once '../../config/database.php';
require_once '../../config/config.php';

requireLogin();

$id = (int)($_GET['id'] ?? 0);
$errors = [];

// Lấy thông tin danh mục hiện tại
try {
    $stmt = $pdo->prepare("SELECT * FROM danh_muc_giay WHERE id = ?");
    $stmt->execute([$id]);
    $category = $stmt->fetch();

    if (!$category) {
        alert('Danh mục không tồn tại!', 'danger');
        redirect('/tktshop/admin/categories/');
    }
} catch (PDOException $e) {
    alert('Lỗi khi lấy thông tin danh mục: ' . $e->getMessage(), 'danger');
    redirect('/tktshop/admin/categories/');
}

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $ten_danh_muc = trim($_POST['ten_danh_muc'] ?? '');
    $mo_ta = trim($_POST['mo_ta'] ?? '');
    $danh_muc_cha_id = !empty($_POST['danh_muc_cha_id']) ? (int)$_POST['danh_muc_cha_id'] : null;
    $thu_tu_hien_thi = (int)($_POST['thu_tu_hien_thi'] ?? 0);
    $trang_thai = $_POST['trang_thai'] ?? 'hoat_dong';
    
    // Validate
    if (empty($ten_danh_muc)) {
        $errors[] = 'Tên danh mục không được để trống';
    }
    
    // Kiểm tra không thể đặt chính nó làm danh mục cha
    if ($danh_muc_cha_id == $id) {
        $errors[] = 'Không thể đặt chính danh mục này làm danh mục cha';
    }
    
    // Kiểm tra không tạo vòng lặp danh mục
    if ($danh_muc_cha_id) {
        try {
            $check_loop = $pdo->prepare("
                WITH RECURSIVE category_path AS (
                    SELECT id, danh_muc_cha_id, 0 as level
                    FROM danh_muc_giay 
                    WHERE id = ?
                    
                    UNION ALL
                    
                    SELECT dm.id, dm.danh_muc_cha_id, cp.level + 1
                    FROM danh_muc_giay dm
                    INNER JOIN category_path cp ON dm.id = cp.danh_muc_cha_id
                    WHERE cp.level < 10
                )
                SELECT COUNT(*) FROM category_path WHERE id = ?
            ");
            $check_loop->execute([$danh_muc_cha_id, $id]);
            if ($check_loop->fetchColumn() > 0) {
                $errors[] = 'Không thể tạo vòng lặp danh mục';
            }
        } catch (PDOException $e) {
            // Nếu database không hỗ trợ CTE, bỏ qua kiểm tra này
        }
    }
    
    // Tạo slug mới nếu tên thay đổi
    $slug = $category['slug'];
    if ($ten_danh_muc !== $category['ten_danh_muc']) {
        $slug = createSlug($ten_danh_muc);
        
        // Kiểm tra trùng lặp slug (loại trừ bản ghi hiện tại)
        try {
            $check = $pdo->prepare("SELECT id FROM danh_muc_giay WHERE slug = ? AND id != ?");
            $check->execute([$slug, $id]);
            if ($check->fetch()) {
                $slug .= '-' . time();
            }
        } catch (PDOException $e) {
            $errors[] = 'Lỗi khi kiểm tra slug: ' . $e->getMessage();
        }
    }
    
    // Xử lý upload ảnh mới
    $hinh_anh = $category['hinh_anh'];
    if (!empty($_FILES['hinh_anh']['name'])) {
        $upload_result = uploadFile($_FILES['hinh_anh'], 'categories');
        if ($upload_result['success']) {
            // Xóa ảnh cũ
            if ($category['hinh_anh'] && file_exists(UPLOAD_PATH . '/categories/' . $category['hinh_anh'])) {
                unlink(UPLOAD_PATH . '/categories/' . $category['hinh_anh']);
            }
            $hinh_anh = $upload_result['filename'];
        } else {
            $errors[] = $upload_result['message'];
        }
    }
    
    // Cập nhật database
    if (empty($errors)) {
        try {
            $stmt = $pdo->prepare("
                UPDATE danh_muc_giay 
                SET ten_danh_muc = ?, slug = ?, mo_ta = ?, hinh_anh = ?, 
                    danh_muc_cha_id = ?, thu_tu_hien_thi = ?, trang_thai = ?
                WHERE id = ?
            ");
            
            if ($stmt->execute([
                $ten_danh_muc, $slug, $mo_ta, $hinh_anh, $danh_muc_cha_id, $thu_tu_hien_thi, $trang_thai, $id
            ])) {
                alert('Cập nhật danh mục thành công!', 'success');
                redirect('/tktshop/admin/categories/');
            } else {
                $errors[] = 'Lỗi khi cập nhật dữ liệu';
            }
        } catch (PDOException $e) {
            $errors[] = 'Lỗi database: ' . $e->getMessage();
        }
    }
} else {
    // Hiển thị dữ liệu hiện tại
    $_POST = $category;
}

// Lấy danh mục cha (loại trừ chính nó và các danh mục con của nó)
try {
    $stmt = $pdo->prepare("
        SELECT * FROM danh_muc_giay 
        WHERE danh_muc_cha_id IS NULL AND id != ? AND trang_thai = 'hoat_dong'
        ORDER BY ten_danh_muc
    ");
    $stmt->execute([$id]);
    $parent_categories = $stmt->fetchAll();
} catch (PDOException $e) {
    $parent_categories = [];
    $errors[] = 'Lỗi khi lấy danh mục cha: ' . $e->getMessage();
}
?>

<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Chỉnh sửa danh mục - <?= SITE_NAME ?></title>
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
            <div class="d-flex justify-content-between align-items-center py-3">
                <div>
                    <h2>Chỉnh sửa danh mục</h2>
                    <p class="text-muted mb-0"><?= htmlspecialchars($category['ten_danh_muc']) ?></p>
                </div>
                <a href="/tktshop/admin/categories/" class="btn btn-secondary">
                    <i class="fas fa-arrow-left"></i> Quay lại
                </a>
            </div>

            <?php if (!empty($errors)): ?>
                <div class="alert alert-danger">
                    <ul class="mb-0">
                        <?php foreach ($errors as $error): ?>
                            <li><?= htmlspecialchars($error) ?></li>
                        <?php endforeach; ?>
                    </ul>
                </div>
            <?php endif; ?>

            <form method="POST" action="<?= $_SERVER['PHP_SELF'] ?>?id=<?= $id ?>" enctype="multipart/form-data">
                <div class="row">
                    <!-- Thông tin cơ bản -->
                    <div class="col-md-8">
                        <div class="card mb-3">
                            <div class="card-header">
                                <h5>Thông tin danh mục</h5>
                            </div>
                            <div class="card-body">
                                <div class="mb-3">
                                    <label for="ten_danh_muc" class="form-label">Tên danh mục <span class="text-danger">*</span></label>
                                    <input type="text" 
                                           class="form-control" 
                                           id="ten_danh_muc" 
                                           name="ten_danh_muc" 
                                           value="<?= htmlspecialchars($_POST['ten_danh_muc'] ?? '') ?>"
                                           required>
                                </div>
                                
                                <div class="mb-3">
                                    <label class="form-label">Slug hiện tại</label>
                                    <div class="form-control-plaintext">
                                        <code><?= htmlspecialchars($category['slug']) ?></code>
                                    </div>
                                    <div class="form-text">Slug sẽ được tự động cập nhật nếu thay đổi tên danh mục</div>
                                </div>
                                
                                <div class="mb-3">
                                    <label for="danh_muc_cha_id" class="form-label">Danh mục cha</label>
                                    <select class="form-select" id="danh_muc_cha_id" name="danh_muc_cha_id">
                                        <option value="">Không có (Danh mục gốc)</option>
                                        <?php foreach ($parent_categories as $parent): ?>
                                            <option value="<?= $parent['id'] ?>" <?= ($_POST['danh_muc_cha_id'] ?? '') == $parent['id'] ? 'selected' : '' ?>>
                                                <?= htmlspecialchars($parent['ten_danh_muc']) ?>
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                    <div class="form-text">Chọn danh mục cha nếu đây là danh mục con</div>
                                </div>
                                
                                <div class="mb-3">
                                    <label for="mo_ta" class="form-label">Mô tả</label>
                                    <textarea class="form-control" 
                                              id="mo_ta" 
                                              name="mo_ta" 
                                              rows="4"
                                              placeholder="Mô tả về danh mục..."><?= htmlspecialchars($_POST['mo_ta'] ?? '') ?></textarea>
                                </div>
                            </div>
                        </div>
                    </div>
                    
                    <!-- Sidebar -->
                    <div class="col-md-4">
                        <!-- Ảnh danh mục -->
                        <div class="card mb-3">
                            <div class="card-header">
                                <h5>Ảnh danh mục</h5>
                            </div>
                            <div class="card-body">
                                <?php if ($category['hinh_anh']): ?>
                                    <div class="mb-3 text-center">
                                        <img src="/tktshop/uploads/categories/<?= $category['hinh_anh'] ?>" 
                                             alt="<?= htmlspecialchars($category['ten_danh_muc']) ?>"
                                             class="img-fluid rounded"
                                             style="max-height: 200px;">
                                        <div class="mt-2">
                                            <small class="text-muted">Ảnh hiện tại</small>
                                        </div>
                                    </div>
                                <?php endif; ?>
                                
                                <div class="mb-3">
                                    <label for="hinh_anh" class="form-label">Ảnh đại diện mới</label>
                                    <input type="file" 
                                           class="form-control" 
                                           id="hinh_anh" 
                                           name="hinh_anh"
                                           accept="image/*">
                                    <div class="form-text">Chấp nhận: JPG, PNG, GIF. Tối đa 2MB</div>
                                </div>
                                
                                <div id="image_preview" class="text-center" style="display: none;">
                                    <img id="preview_img" src="" alt="Preview" class="img-fluid rounded" style="max-height: 200px;">
                                    <div class="mt-2">
                                        <small class="text-muted">Ảnh mới</small>
                                    </div>
                                </div>
                            </div>
                        </div>
                        
                        <!-- Cài đặt -->
                        <div class="card mb-3">
                            <div class="card-header">
                                <h5>Cài đặt</h5>
                            </div>
                            <div class="card-body">
                                <div class="mb-3">
                                    <label for="thu_tu_hien_thi" class="form-label">Thứ tự hiển thị</label>
                                    <input type="number" 
                                           class="form-control" 
                                           id="thu_tu_hien_thi" 
                                           name="thu_tu_hien_thi" 
                                           value="<?= $_POST['thu_tu_hien_thi'] ?? 0 ?>"
                                           min="0">
                                    <div class="form-text">Số nhỏ sẽ hiển thị trước</div>
                                </div>
                                
                                <div class="mb-3">
                                    <label for="trang_thai" class="form-label">Trạng thái</label>
                                    <select class="form-select" id="trang_thai" name="trang_thai">
                                        <option value="hoat_dong" <?= ($_POST['trang_thai'] ?? '') == 'hoat_dong' ? 'selected' : '' ?>>
                                            Hoạt động
                                        </option>
                                        <option value="an" <?= ($_POST['trang_thai'] ?? '') == 'an' ? 'selected' : '' ?>>
                                            Ẩn
                                        </option>
                                    </select>
                                </div>
                            </div>
                        </div>
                        
                        <!-- Thống kê -->
                        <?php 
                        try {
                            $stats = $pdo->prepare("
                                SELECT COUNT(*) as so_san_pham 
                                FROM san_pham_chinh 
                                WHERE danh_muc_id = ? AND trang_thai = 'hoat_dong'
                            ");
                            $stats->execute([$id]);
                            $stats = $stats->fetch();
                        } catch (PDOException $e) {
                            $stats = ['so_san_pham' => 0];
                        }
                        ?>
                        <div class="card mb-3">
                            <div class="card-header">
                                <h5>Thống kê</h5>
                            </div>
                            <div class="card-body text-center">
                                <div class="h4 text-primary"><?= $stats['so_san_pham'] ?></div>
                                <small class="text-muted">Sản phẩm trong danh mục</small>
                            </div>
                        </div>
                        
                        <!-- Nút lưu -->
                        <div class="d-grid gap-2">
                            <button type="submit" class="btn btn-primary btn-lg">
                                <i class="fas fa-save"></i> Cập nhật danh mục
                            </button>
                            <a href="/tktshop/admin/categories/" class="btn btn-secondary">Hủy</a>
                        </div>
                    </div>
                </div>
            </form>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
    
    <script>
        // Preview ảnh khi chọn file
        document.getElementById('hinh_anh').addEventListener('change', function(e) {
            const file = e.target.files[0];
            if (file) {
                const reader = new FileReader();
                reader.onload = function(e) {
                    document.getElementById('preview_img').src = e.target.result;
                    document.getElementById('image_preview').style.display = 'block';
                };
                reader.readAsDataURL(file);
            } else {
                document.getElementById('image_preview').style.display = 'none';
            }
        });
    </script>
</body>
</html>
