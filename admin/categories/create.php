<?php
// admin/categories/create.php - Fixed version
/**
 * Thêm danh mục giày mới
 */

require_once '../../config/database.php';
require_once '../../config/config.php';

requireLogin();

$errors = [];

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
    
    // Tạo slug từ tên danh mục (sử dụng hàm từ config.php)
    $slug = createSlug($ten_danh_muc);
    
    // Kiểm tra trùng lặp slug
    $check = $pdo->prepare("SELECT id FROM danh_muc_giay WHERE slug = ?");
    $check->execute([$slug]);
    if ($check->fetch()) {
        $slug .= '-' . time();
    }
    
    // Xử lý upload ảnh
    $hinh_anh = '';
    if (!empty($_FILES['hinh_anh']['name'])) {
        $upload_result = uploadFile($_FILES['hinh_anh'], 'categories');
        if ($upload_result['success']) {
            $hinh_anh = $upload_result['filename'];
        } else {
            $errors[] = $upload_result['message'];
        }
    }
    
    // Lưu vào database
    if (empty($errors)) {
        try {
            $stmt = $pdo->prepare("
                INSERT INTO danh_muc_giay 
                (ten_danh_muc, slug, mo_ta, hinh_anh, danh_muc_cha_id, thu_tu_hien_thi, trang_thai) 
                VALUES (?, ?, ?, ?, ?, ?, ?)
            ");
            
            if ($stmt->execute([
                $ten_danh_muc, $slug, $mo_ta, $hinh_anh, $danh_muc_cha_id, $thu_tu_hien_thi, $trang_thai
            ])) {
                alert('Thêm danh mục thành công!', 'success');
                redirect('/tktshop/admin/categories/');
            } else {
                $errors[] = 'Lỗi khi lưu dữ liệu';
            }
        } catch (PDOException $e) {
            $errors[] = 'Lỗi database: ' . $e->getMessage();
        }
    }
}

// Lấy danh mục cha
try {
    $parent_categories = $pdo->query("
        SELECT * FROM danh_muc_giay 
        WHERE danh_muc_cha_id IS NULL AND trang_thai = 'hoat_dong'
        ORDER BY ten_danh_muc
    ")->fetchAll();
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
    <title>Thêm danh mục - <?= SITE_NAME ?></title>
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
                    <h2>Thêm danh mục mới</h2>
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

                <form method="POST" action="<?= $_SERVER['PHP_SELF'] ?>" enctype="multipart/form-data">
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
                                    <div class="mb-3">
                                        <label for="hinh_anh" class="form-label">Ảnh đại diện</label>
                                        <input type="file" 
                                               class="form-control" 
                                               id="hinh_anh" 
                                               name="hinh_anh"
                                               accept="image/*">
                                        <div class="form-text">Chấp nhận: JPG, PNG, GIF. Tối đa 2MB</div>
                                    </div>
                                    
                                    <div id="image_preview" class="text-center" style="display: none;">
                                        <img id="preview_img" src="" alt="Preview" class="img-fluid rounded" style="max-height: 200px;">
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
                                            <option value="hoat_dong" <?= ($_POST['trang_thai'] ?? 'hoat_dong') == 'hoat_dong' ? 'selected' : '' ?>>
                                                Hoạt động
                                            </option>
                                            <option value="an" <?= ($_POST['trang_thai'] ?? '') == 'an' ? 'selected' : '' ?>>
                                                Ẩn
                                            </option>
                                        </select>
                                    </div>
                                </div>
                            </div>
                            
                            <!-- Nút lưu -->
                            <div class="d-grid gap-2">
                                <button type="submit" class="btn btn-primary btn-lg">
                                    <i class="fas fa-save"></i> Lưu danh mục
                                </button>
                                <a href="/tktshop/admin/categories/" class="btn btn-secondary">Hủy</a>
                            </div>
                        </div>
                    </div>
                </form>
            </div>
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
        
        // Auto-generate slug (preview)
        document.getElementById('ten_danh_muc').addEventListener('input', function() {
            const name = this.value;
            // Simple slug preview (the actual slug is generated server-side)
            const slug = name.toLowerCase()
                .replace(/[áàảãạâấầẩẫậăắằẳẵặ]/g, 'a')
                .replace(/[éèẻẽẹêếềểễệ]/g, 'e')
                .replace(/[íìỉĩị]/g, 'i')
                .replace(/[óòỏõọôốồổỗộơớờởỡợ]/g, 'o')
                .replace(/[úùủũụưứừửữự]/g, 'u')
                .replace(/[ýỳỷỹỵ]/g, 'y')
                .replace(/đ/g, 'd')
                .replace(/[^a-z0-9\s-]/g, '')
                .replace(/[\s-]+/g, '-')
                .replace(/^-+|-+$/g, '');
            
            // Show preview (optional)
            if (slug) {
                console.log('Slug preview:', slug);
            }
        });
    </script>
</body>
</html>