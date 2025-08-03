<?php
session_start();
require_once '../../config/database.php';
require_once '../../config/config.php';

// Kiểm tra quyền admin
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    header('Location: ../../admin/login.php');
    exit();
}

$error = '';
$success = '';

// Lấy danh sách danh mục
$categories = [];
try {
    $stmt = $pdo->query("SELECT id, ten_danh_muc FROM danh_muc_giay WHERE trang_thai = 'hoat_dong' ORDER BY ten_danh_muc");
    $categories = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    $error = "Lỗi khi tải danh mục: " . $e->getMessage();
}

// Xử lý form submit
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        $ten_san_pham = sanitizeInput($_POST['ten_san_pham']);
        $ma_san_pham = sanitizeInput($_POST['ma_san_pham']);
        $mo_ta_ngan = sanitizeInput($_POST['mo_ta_ngan']);
        $mo_ta_chi_tiet = $_POST['mo_ta_chi_tiet']; // Giữ nguyên HTML
        $danh_muc_id = (int)$_POST['danh_muc_id'];
        $thuong_hieu = sanitizeInput($_POST['thuong_hieu']);
        $gia_goc = (int)$_POST['gia_goc'];
        $gia_khuyen_mai = !empty($_POST['gia_khuyen_mai']) ? (int)$_POST['gia_khuyen_mai'] : null;
        $san_pham_noi_bat = isset($_POST['san_pham_noi_bat']) ? 1 : 0;
        $san_pham_moi = isset($_POST['san_pham_moi']) ? 1 : 0;

        // Tạo slug tự động
        $slug = createSlug($ten_san_pham);
        
        // Kiểm tra slug trùng lặp
        $stmt = $pdo->prepare("SELECT COUNT(*) FROM san_pham_chinh WHERE slug = ?");
        $stmt->execute([$slug]);
        if ($stmt->fetchColumn() > 0) {
            $slug .= '-' . time();
        }

        // Xử lý upload ảnh chính
        $hinh_anh_chinh = null;
        if (isset($_FILES['hinh_anh_chinh']) && $_FILES['hinh_anh_chinh']['error'] === UPLOAD_ERR_OK) {
            $hinh_anh_chinh = uploadImage($_FILES['hinh_anh_chinh'], 'products');
            if (!$hinh_anh_chinh) {
                throw new Exception("Lỗi upload ảnh chính");
            }
        }

        // Xử lý upload album ảnh
        $album_hinh_anh = [];
        if (isset($_FILES['album_hinh_anh'])) {
            foreach ($_FILES['album_hinh_anh']['tmp_name'] as $key => $tmp_name) {
                if ($_FILES['album_hinh_anh']['error'][$key] === UPLOAD_ERR_OK) {
                    $file = [
                        'name' => $_FILES['album_hinh_anh']['name'][$key],
                        'type' => $_FILES['album_hinh_anh']['type'][$key],
                        'tmp_name' => $tmp_name,
                        'error' => $_FILES['album_hinh_anh']['error'][$key],
                        'size' => $_FILES['album_hinh_anh']['size'][$key]
                    ];
                    $uploaded = uploadImage($file, 'products');
                    if ($uploaded) {
                        $album_hinh_anh[] = $uploaded;
                    }
                }
            }
        }

        // Insert vào database
        $sql = "INSERT INTO san_pham_chinh (
                    ten_san_pham, slug, ma_san_pham, mo_ta_ngan, mo_ta_chi_tiet, 
                    danh_muc_id, thuong_hieu, hinh_anh_chinh, album_hinh_anh, 
                    gia_goc, gia_khuyen_mai, san_pham_noi_bat, san_pham_moi, nguoi_tao
                ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";

        $stmt = $pdo->prepare($sql);
        $stmt->execute([
            $ten_san_pham, $slug, $ma_san_pham, $mo_ta_ngan, $mo_ta_chi_tiet,
            $danh_muc_id, $thuong_hieu, $hinh_anh_chinh, 
            !empty($album_hinh_anh) ? json_encode($album_hinh_anh) : null,
            $gia_goc, $gia_khuyen_mai, $san_pham_noi_bat, $san_pham_moi, 
            $_SESSION['user_id']
        ]);

        $product_id = $pdo->lastInsertId();
        setFlashMessage('success', 'Thêm sản phẩm thành công!');
        
        // Chuyển hướng đến trang quản lý biến thể
        header("Location: " . ADMIN_URL . "/products/variants.php?product_id=" . $product_id);
        exit();

    } catch (Exception $e) {
        $error = "Lỗi: " . $e->getMessage();
    }
}
?>

<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Thêm sản phẩm mới - TKT Shop Admin</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <script src="https://cdn.ckeditor.com/4.16.2/standard/ckeditor.js"></script>
</head>
<body>
    <div class="container-fluid">
        <div class="row">
            <!-- Sidebar -->
            <?php include '../layouts/sidebar.php'; ?>
            
            <!-- Main content -->
            <div class="col-md-9 col-lg-10">
                <div class="d-flex justify-content-between align-items-center pt-3 pb-2 mb-3 border-bottom">
                    <h1 class="h2">Thêm sản phẩm mới</h1>
                    <a href="index.php" class="btn btn-outline-secondary">
                        <i class="fas fa-arrow-left"></i> Quay lại
                    </a>
                </div>

                <?php if ($error): ?>
                    <div class="alert alert-danger">
                        <i class="fas fa-exclamation-triangle"></i> <?php echo $error; ?>
                    </div>
                <?php endif; ?>

                <?php if ($success): ?>
                    <div class="alert alert-success">
                        <i class="fas fa-check-circle"></i> <?php echo $success; ?>
                    </div>
                <?php endif; ?>

                <form method="POST" enctype="multipart/form-data">
                    <div class="row">
                        <div class="col-lg-8">
                            <div class="card">
                                <div class="card-header">
                                    <h5 class="card-title mb-0">Thông tin cơ bản</h5>
                                </div>
                                <div class="card-body">
                                    <div class="mb-3">
                                        <label for="ten_san_pham" class="form-label">Tên sản phẩm *</label>
                                        <input type="text" class="form-control" id="ten_san_pham" name="ten_san_pham" 
                                               value="<?php echo isset($_POST['ten_san_pham']) ? htmlspecialchars($_POST['ten_san_pham']) : ''; ?>" required>
                                    </div>

                                    <div class="mb-3">
                                        <label for="ma_san_pham" class="form-label">Mã sản phẩm</label>
                                        <input type="text" class="form-control" id="ma_san_pham" name="ma_san_pham" 
                                               value="<?php echo isset($_POST['ma_san_pham']) ? htmlspecialchars($_POST['ma_san_pham']) : ''; ?>">
                                    </div>

                                    <div class="mb-3">
                                        <label for="mo_ta_ngan" class="form-label">Mô tả ngắn</label>
                                        <textarea class="form-control" id="mo_ta_ngan" name="mo_ta_ngan" rows="3"><?php echo isset($_POST['mo_ta_ngan']) ? htmlspecialchars($_POST['mo_ta_ngan']) : ''; ?></textarea>
                                    </div>

                                    <div class="mb-3">
                                        <label for="mo_ta_chi_tiet" class="form-label">Mô tả chi tiết</label>
                                        <textarea class="form-control" id="mo_ta_chi_tiet" name="mo_ta_chi_tiet" rows="8"><?php echo isset($_POST['mo_ta_chi_tiet']) ? htmlspecialchars($_POST['mo_ta_chi_tiet']) : ''; ?></textarea>
                                    </div>
                                </div>
                            </div>

                            <div class="card mt-3">
                                <div class="card-header">
                                    <h5 class="card-title mb-0">Hình ảnh</h5>
                                </div>
                                <div class="card-body">
                                    <div class="mb-3">
                                        <label for="hinh_anh_chinh" class="form-label">Ảnh chính</label>
                                        <input type="file" class="form-control" id="hinh_anh_chinh" name="hinh_anh_chinh" accept="image/*">
                                    </div>

                                    <div class="mb-3">
                                        <label for="album_hinh_anh" class="form-label">Album ảnh</label>
                                        <input type="file" class="form-control" id="album_hinh_anh" name="album_hinh_anh[]" accept="image/*" multiple>
                                        <div class="form-text">Có thể chọn nhiều ảnh cùng lúc</div>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <div class="col-lg-4">
                            <div class="card">
                                <div class="card-header">
                                    <h5 class="card-title mb-0">Thông tin khác</h5>
                                </div>
                                <div class="card-body">
                                    <div class="mb-3">
                                        <label for="danh_muc_id" class="form-label">Danh mục *</label>
                                        <select class="form-select" id="danh_muc_id" name="danh_muc_id" required>
                                            <option value="">Chọn danh mục</option>
                                            <?php foreach ($categories as $category): ?>
                                                <option value="<?php echo $category['id']; ?>" 
                                                        <?php echo (isset($_POST['danh_muc_id']) && $_POST['danh_muc_id'] == $category['id']) ? 'selected' : ''; ?>>
                                                    <?php echo htmlspecialchars($category['ten_danh_muc']); ?>
                                                </option>
                                            <?php endforeach; ?>
                                        </select>
                                    </div>

                                    <div class="mb-3">
                                        <label for="thuong_hieu" class="form-label">Thương hiệu</label>
                                        <input type="text" class="form-control" id="thuong_hieu" name="thuong_hieu" 
                                               value="<?php echo isset($_POST['thuong_hieu']) ? htmlspecialchars($_POST['thuong_hieu']) : ''; ?>">
                                    </div>

                                    <div class="mb-3">
                                        <label for="gia_goc" class="form-label">Giá gốc *</label>
                                        <input type="number" class="form-control" id="gia_goc" name="gia_goc" 
                                               value="<?php echo isset($_POST['gia_goc']) ? $_POST['gia_goc'] : ''; ?>" required>
                                    </div>

                                    <div class="mb-3">
                                        <label for="gia_khuyen_mai" class="form-label">Giá khuyến mãi</label>
                                        <input type="number" class="form-control" id="gia_khuyen_mai" name="gia_khuyen_mai" 
                                               value="<?php echo isset($_POST['gia_khuyen_mai']) ? $_POST['gia_khuyen_mai'] : ''; ?>">
                                    </div>

                                    <div class="mb-3">
                                        <div class="form-check">
                                            <input class="form-check-input" type="checkbox" id="san_pham_noi_bat" name="san_pham_noi_bat" value="1"
                                                   <?php echo (isset($_POST['san_pham_noi_bat'])) ? 'checked' : ''; ?>>
                                            <label class="form-check-label" for="san_pham_noi_bat">
                                                Sản phẩm nổi bật
                                            </label>
                                        </div>
                                    </div>

                                    <div class="mb-3">
                                        <div class="form-check">
                                            <input class="form-check-input" type="checkbox" id="san_pham_moi" name="san_pham_moi" value="1"
                                                   <?php echo (isset($_POST['san_pham_moi'])) ? 'checked' : ''; ?>>
                                            <label class="form-check-label" for="san_pham_moi">
                                                Sản phẩm mới
                                            </label>
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <div class="card mt-3">
                                <div class="card-body">
                                    <button type="submit" class="btn btn-primary w-100">
                                        <i class="fas fa-save"></i> Tạo sản phẩm
                                    </button>
                                </div>
                            </div>
                        </div>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        // Khởi tạo CKEditor
        CKEDITOR.replace('mo_ta_chi_tiet');
    </script>
</body>
</html>