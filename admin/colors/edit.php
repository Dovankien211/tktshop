<?php
// admin/colors/edit.php
/**
 * Sửa màu sắc
 */

require_once '../../config/database.php';
require_once '../../config/config.php';

requireLogin();

$id = (int)($_GET['id'] ?? 0);
$errors = [];

// Lấy thông tin màu sắc hiện tại
$stmt = $pdo->prepare("SELECT * FROM mau_sac WHERE id = ?");
$stmt->execute([$id]);
$color = $stmt->fetch();

if (!$color) {
    alert('Màu sắc không tồn tại!', 'danger');
    redirect('admin/colors/');
}

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $ten_mau = trim($_POST['ten_mau'] ?? '');
    $ma_mau = trim($_POST['ma_mau'] ?? '');
    $thu_tu_hien_thi = (int)($_POST['thu_tu_hien_thi'] ?? 0);
    $trang_thai = $_POST['trang_thai'] ?? 'hoat_dong';
    
    // Validate
    if (empty($ten_mau)) {
        $errors[] = 'Tên màu không được để trống';
    }
    
    if (empty($ma_mau)) {
        $errors[] = 'Mã màu không được để trống';
    } elseif (!preg_match('/^#[0-9A-Fa-f]{6}$/', $ma_mau)) {
        $errors[] = 'Mã màu phải có định dạng #RRGGBB (ví dụ: #FF0000)';
    }
    
    // Kiểm tra trùng lặp (loại trừ bản ghi hiện tại)
    if (!empty($ten_mau)) {
        $check = $pdo->prepare("SELECT id FROM mau_sac WHERE ten_mau = ? AND id != ?");
        $check->execute([$ten_mau, $id]);
        if ($check->fetch()) {
            $errors[] = 'Tên màu đã tồn tại';
        }
    }
    
    if (!empty($ma_mau)) {
        $check = $pdo->prepare("SELECT id FROM mau_sac WHERE ma_mau = ? AND id != ?");
        $check->execute([$ma_mau, $id]);
        if ($check->fetch()) {
            $errors[] = 'Mã màu đã tồn tại';
        }
    }
    
    // Cập nhật database
    if (empty($errors)) {
        $stmt = $pdo->prepare("UPDATE mau_sac SET ten_mau = ?, ma_mau = ?, thu_tu_hien_thi = ?, trang_thai = ? WHERE id = ?");
        
        if ($stmt->execute([$ten_mau, $ma_mau, $thu_tu_hien_thi, $trang_thai, $id])) {
            alert('Cập nhật màu sắc thành công!', 'success');
            redirect('index.php');
        } else {
            $errors[] = 'Lỗi khi cập nhật dữ liệu';
        }
    }
} else {
    // Hiển thị dữ liệu hiện tại
    $_POST = $color;
}
?>

<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Sửa màu sắc - <?= SITE_NAME ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
</head>
<body>
    <div class="container-fluid">
        <div class="row">
            <!-- Sidebar -->
            <?php include '../layouts/sidebar.php'; ?>
            
            <!-- Main content -->
            <div class="col-md-10">
                <div class="d-flex justify-content-between align-items-center py-3">
                    <h2>Sửa màu sắc: <?= htmlspecialchars($color['ten_mau']) ?></h2>
                    <a href="<?= adminUrl('colors/') ?>" class="btn btn-secondary">
                        <i class="fas fa-arrow-left"></i> Quay lại
                    </a>
                </div>

                <?php if (!empty($errors)): ?>
                    <div class="alert alert-danger">
                        <ul class="mb-0">
                            <?php foreach ($errors as $error): ?>
                                <li><?= $error ?></li>
                            <?php endforeach; ?>
                        </ul>
                    </div>
                <?php endif; ?>

                <div class="card">
                    <div class="card-body">
                        <form method="POST">
                            <div class="row">
                                <div class="col-md-6">
                                    <div class="mb-3">
                                        <label for="ten_mau" class="form-label">Tên màu <span class="text-danger">*</span></label>
                                        <input type="text" 
                                               class="form-control" 
                                               id="ten_mau" 
                                               name="ten_mau" 
                                               value="<?= htmlspecialchars($_POST['ten_mau'] ?? '') ?>"
                                               placeholder="Ví dụ: Đen, Trắng, Đỏ..."
                                               required>
                                    </div>
                                </div>
                                
                                <div class="col-md-6">
                                    <div class="mb-3">
                                        <label for="ma_mau" class="form-label">Mã màu <span class="text-danger">*</span></label>
                                        <div class="input-group">
                                            <input type="color" 
                                                   class="form-control form-control-color" 
                                                   id="color_picker" 
                                                   value="<?= $_POST['ma_mau'] ?? '#000000' ?>"
                                                   style="width: 60px;">
                                            <input type="text" 
                                                   class="form-control" 
                                                   id="ma_mau" 
                                                   name="ma_mau" 
                                                   value="<?= htmlspecialchars($_POST['ma_mau'] ?? '') ?>"
                                                   placeholder="#FF0000"
                                                   pattern="^#[0-9A-Fa-f]{6}$"
                                                   required>
                                        </div>
                                    </div>
                                </div>
                            </div>
                            
                            <div class="row">
                                <div class="col-md-6">
                                    <div class="mb-3">
                                        <label for="thu_tu_hien_thi" class="form-label">Thứ tự hiển thị</label>
                                        <input type="number" 
                                               class="form-control" 
                                               id="thu_tu_hien_thi" 
                                               name="thu_tu_hien_thi" 
                                               value="<?= $_POST['thu_tu_hien_thi'] ?? 0 ?>"
                                               min="0">
                                    </div>
                                </div>
                                
                                <div class="col-md-6">
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
                            
                            <div class="d-flex gap-2">
                                <button type="submit" class="btn btn-primary">
                                    <i class="fas fa-save"></i> Cập nhật màu sắc
                                </button>
                                <a href="<?= adminUrl('colors/') ?>" class="btn btn-secondary">Hủy</a>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        // Đồng bộ color picker với input text
        document.getElementById('color_picker').addEventListener('change', function() {
            document.getElementById('ma_mau').value = this.value.toUpperCase();
        });
        
        document.getElementById('ma_mau').addEventListener('input', function() {
            const value = this.value;
            if (/^#[0-9A-Fa-f]{6}$/.test(value)) {
                document.getElementById('color_picker').value = value;
            }
        });
    </script>
</body>
</html>
