<?php
// admin/products/create.php - COMPLETE REWRITE
/**
 * Thêm sản phẩm mới - Fixed all issues
 */

session_start();
require_once '../../config/database.php';
require_once '../../config/config.php';

// 🔧 FIX: Kiểm tra đăng nhập admin (hỗ trợ cả user_id và admin_id)
$is_admin = false;

if (isset($_SESSION['admin_id'])) {
    // Nếu có admin_id
    $is_admin = true;
    $admin_id = $_SESSION['admin_id'];
} elseif (isset($_SESSION['user_id']) && isset($_SESSION['role']) && $_SESSION['role'] === 'admin') {
    // Nếu có user_id với role admin
    $is_admin = true;
    $admin_id = $_SESSION['user_id'];
}

if (!$is_admin) {
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

// Helper function để tạo slug
function createSlug($string) {
    $slug = trim($string);
    $slug = mb_strtolower($slug, 'UTF-8');
    
    // Chuyển đổi ký tự tiếng Việt
    $vietnamese = [
        'á'=>'a', 'à'=>'a', 'ả'=>'a', 'ã'=>'a', 'ạ'=>'a', 'ă'=>'a', 'ắ'=>'a', 'ằ'=>'a', 'ẳ'=>'a', 'ẵ'=>'a', 'ặ'=>'a',
        'â'=>'a', 'ấ'=>'a', 'ầ'=>'a', 'ẩ'=>'a', 'ẫ'=>'a', 'ậ'=>'a',
        'é'=>'e', 'è'=>'e', 'ẻ'=>'e', 'ẽ'=>'e', 'ẹ'=>'e', 'ê'=>'e', 'ế'=>'e', 'ề'=>'e', 'ể'=>'e', 'ễ'=>'e', 'ệ'=>'e',
        'í'=>'i', 'ì'=>'i', 'ỉ'=>'i', 'ĩ'=>'i', 'ị'=>'i',
        'ó'=>'o', 'ò'=>'o', 'ỏ'=>'o', 'õ'=>'o', 'ọ'=>'o', 'ô'=>'o', 'ố'=>'o', 'ồ'=>'o', 'ổ'=>'o', 'ỗ'=>'o', 'ộ'=>'o',
        'ơ'=>'o', 'ớ'=>'o', 'ờ'=>'o', 'ở'=>'o', 'ỡ'=>'o', 'ợ'=>'o',
        'ú'=>'u', 'ù'=>'u', 'ủ'=>'u', 'ũ'=>'u', 'ụ'=>'u', 'ư'=>'u', 'ứ'=>'u', 'ừ'=>'u', 'ử'=>'u', 'ữ'=>'u', 'ự'=>'u',
        'ý'=>'y', 'ỳ'=>'y', 'ỷ'=>'y', 'ỹ'=>'y', 'ỵ'=>'y',
        'đ'=>'d'
    ];
    
    $slug = strtr($slug, $vietnamese);
    $slug = preg_replace('/[^a-z0-9\s-]/', '', $slug);
    $slug = preg_replace('/[\s-]+/', '-', $slug);
    $slug = trim($slug, '-');
    
    return $slug;
}

// Helper function để upload ảnh
function uploadImage($file, $directory) {
    if (!isset($file) || $file['error'] !== UPLOAD_ERR_OK) {
        return false;
    }
    
    $allowed_types = ['image/jpeg', 'image/png', 'image/gif', 'image/webp'];
    if (!in_array($file['type'], $allowed_types)) {
        return false;
    }
    
    $max_size = 5 * 1024 * 1024; // 5MB
    if ($file['size'] > $max_size) {
        return false;
    }
    
    $upload_dir = "../../uploads/{$directory}/";
    if (!is_dir($upload_dir)) {
        mkdir($upload_dir, 0755, true);
    }
    
    $extension = pathinfo($file['name'], PATHINFO_EXTENSION);
    $filename = uniqid() . '_' . time() . '.' . $extension;
    $filepath = $upload_dir . $filename;
    
    if (move_uploaded_file($file['tmp_name'], $filepath)) {
        return $filename;
    }
    
    return false;
}

// Helper function để sanitize input
function sanitizeInput($input) {
    return htmlspecialchars(trim($input), ENT_QUOTES, 'UTF-8');
}

// Xử lý form submit
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        // Lấy và validate dữ liệu
        $ten_san_pham = sanitizeInput($_POST['ten_san_pham'] ?? '');
        $ma_san_pham = sanitizeInput($_POST['ma_san_pham'] ?? '');
        $mo_ta_ngan = sanitizeInput($_POST['mo_ta_ngan'] ?? '');
        $mo_ta_chi_tiet = $_POST['mo_ta_chi_tiet'] ?? ''; // Giữ nguyên HTML từ CKEditor
        $danh_muc_id = (int)($_POST['danh_muc_id'] ?? 0);
        $thuong_hieu = sanitizeInput($_POST['thuong_hieu'] ?? '');
        $gia_goc = (int)($_POST['gia_goc'] ?? 0);
        $gia_khuyen_mai = !empty($_POST['gia_khuyen_mai']) ? (int)$_POST['gia_khuyen_mai'] : null;
        $san_pham_noi_bat = isset($_POST['san_pham_noi_bat']) ? 1 : 0;
        $san_pham_moi = isset($_POST['san_pham_moi']) ? 1 : 0;

        // Validation
        if (empty($ten_san_pham)) {
            throw new Exception("Tên sản phẩm không được để trống");
        }
        
        if ($danh_muc_id <= 0) {
            throw new Exception("Vui lòng chọn danh mục");
        }
        
        if ($gia_goc <= 0) {
            throw new Exception("Giá gốc phải lớn hơn 0");
        }
        
        if ($gia_khuyen_mai && $gia_khuyen_mai >= $gia_goc) {
            throw new Exception("Giá khuyến mãi phải nhỏ hơn giá gốc");
        }

        // Tạo slug tự động
        $slug = createSlug($ten_san_pham);
        
        // Kiểm tra slug trùng lặp
        $stmt = $pdo->prepare("SELECT COUNT(*) FROM san_pham_chinh WHERE slug = ?");
        $stmt->execute([$slug]);
        if ($stmt->fetchColumn() > 0) {
            $slug .= '-' . time();
        }
        
        // Kiểm tra mã sản phẩm trùng lặp
        if (!empty($ma_san_pham)) {
            $stmt = $pdo->prepare("SELECT COUNT(*) FROM san_pham_chinh WHERE ma_san_pham = ?");
            $stmt->execute([$ma_san_pham]);
            if ($stmt->fetchColumn() > 0) {
                throw new Exception("Mã sản phẩm đã tồn tại");
            }
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
                    gia_goc, gia_khuyen_mai, san_pham_noi_bat, san_pham_moi, nguoi_tao,
                    ngay_tao
                ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())";

        $stmt = $pdo->prepare($sql);
        $stmt->execute([
            $ten_san_pham, $slug, $ma_san_pham, $mo_ta_ngan, $mo_ta_chi_tiet,
            $danh_muc_id, $thuong_hieu, $hinh_anh_chinh, 
            !empty($album_hinh_anh) ? json_encode($album_hinh_anh) : null,
            $gia_goc, $gia_khuyen_mai, $san_pham_noi_bat, $san_pham_moi, 
            $admin_id
        ]);

        $product_id = $pdo->lastInsertId();
        $success = "Thêm sản phẩm thành công! ID: #" . $product_id;
        
        // Tự động chuyển đến trang quản lý biến thể sau 2 giây
        echo "<script>
            setTimeout(function() {
                window.location.href = 'variants.php?product_id=" . $product_id . "';
            }, 2000);
        </script>";

    } catch (Exception $e) {
        $error = $e->getMessage();
    }
}
?>

<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Thêm sản phẩm mới - TKT Shop Admin</title>
    
    <!-- CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    
    <!-- CKEditor -->
    <script src="https://cdn.ckeditor.com/4.16.2/standard/ckeditor.js"></script>
    
    <style>
        .main-content {
            background: #f8f9fa;
            min-height: 100vh;
        }
        
        .page-header {
            background: white;
            padding: 20px 0;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
            margin-bottom: 30px;
        }
        
        .form-card {
            background: white;
            border-radius: 10px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
            margin-bottom: 20px;
        }
        
        .card-header-custom {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            padding: 15px 20px;
            border-radius: 10px 10px 0 0;
            border: none;
        }
        
        .form-section {
            padding: 25px;
        }
        
        .upload-area {
            border: 2px dashed #ddd;
            border-radius: 8px;
            padding: 30px;
            text-align: center;
            transition: all 0.3s ease;
            cursor: pointer;
        }
        
        .upload-area:hover {
            border-color: #667eea;
            background: #f8f9ff;
        }
        
        .upload-area.dragover {
            border-color: #667eea;
            background: #e3f2fd;
        }
        
        .preview-container {
            display: flex;
            flex-wrap: wrap;
            gap: 10px;
            margin-top: 15px;
        }
        
        .preview-item {
            position: relative;
            width: 100px;
            height: 100px;
            border-radius: 8px;
            overflow: hidden;
            border: 2px solid #ddd;
        }
        
        .preview-item img {
            width: 100%;
            height: 100%;
            object-fit: cover;
        }
        
        .preview-remove {
            position: absolute;
            top: 5px;
            right: 5px;
            background: #dc3545;
            color: white;
            border: none;
            border-radius: 50%;
            width: 25px;
            height: 25px;
            font-size: 12px;
            cursor: pointer;
        }
        
        .price-input {
            position: relative;
        }
        
        .price-input::before {
            content: 'VNĐ';
            position: absolute;
            right: 10px;
            top: 50%;
            transform: translateY(-50%);
            color: #6c757d;
            font-size: 0.9rem;
        }
        
        .btn-save {
            background: linear-gradient(135deg, #28a745, #20c997);
            border: none;
            padding: 12px 30px;
            border-radius: 8px;
            color: white;
            font-weight: 600;
            transition: all 0.3s ease;
        }
        
        .btn-save:hover {
            background: linear-gradient(135deg, #20c997, #28a745);
            transform: translateY(-2px);
            box-shadow: 0 4px 12px rgba(40, 167, 69, 0.3);
        }
        
        .feature-toggle {
            background: #f8f9fa;
            padding: 15px;
            border-radius: 8px;
            border: 1px solid #dee2e6;
            margin-bottom: 15px;
        }
        
        .toggle-switch {
            position: relative;
            display: inline-block;
            width: 50px;
            height: 24px;
        }
        
        .toggle-switch input {
            opacity: 0;
            width: 0;
            height: 0;
        }
        
        .toggle-slider {
            position: absolute;
            cursor: pointer;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background-color: #ccc;
            transition: .4s;
            border-radius: 24px;
        }
        
        .toggle-slider:before {
            position: absolute;
            content: "";
            height: 18px;
            width: 18px;
            left: 3px;
            bottom: 3px;
            background-color: white;
            transition: .4s;
            border-radius: 50%;
        }
        
        input:checked + .toggle-slider {
            background-color: #28a745;
        }
        
        input:checked + .toggle-slider:before {
            transform: translateX(26px);
        }
    </style>
</head>
<body>
    <div class="container-fluid">
        <div class="row">
            <!-- Sidebar -->
            <?php include '../layouts/sidebar.php'; ?>
            
            <!-- Main Content -->
            <div class="col-md-10 main-content">
                <!-- Page Header -->
                <div class="page-header">
                    <div class="container">
                        <div class="d-flex justify-content-between align-items-center">
                            <div>
                                <h2 class="mb-1">
                                    <i class="fas fa-plus-circle me-2 text-success"></i>
                                    Thêm sản phẩm mới
                                </h2>
                                <nav aria-label="breadcrumb">
                                    <ol class="breadcrumb mb-0">
                                        <li class="breadcrumb-item"><a href="../dashboard.php">Dashboard</a></li>
                                        <li class="breadcrumb-item"><a href="index.php">Sản phẩm</a></li>
                                        <li class="breadcrumb-item active">Thêm mới</li>
                                    </ol>
                                </nav>
                            </div>
                            <div>
                                <a href="index.php" class="btn btn-outline-secondary">
                                    <i class="fas fa-arrow-left me-1"></i>Quay lại
                                </a>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="container">
                    <!-- Alerts -->
                    <?php if ($error): ?>
                        <div class="alert alert-danger alert-dismissible fade show">
                            <i class="fas fa-exclamation-triangle me-2"></i>
                            <strong>Lỗi!</strong> <?= htmlspecialchars($error) ?>
                            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                        </div>
                    <?php endif; ?>

                    <?php if ($success): ?>
                        <div class="alert alert-success alert-dismissible fade show">
                            <i class="fas fa-check-circle me-2"></i>
                            <strong>Thành công!</strong> <?= htmlspecialchars($success) ?>
                            <div class="mt-2">
                                <small><i class="fas fa-info-circle me-1"></i>Đang chuyển đến trang quản lý biến thể...</small>
                            </div>
                            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                        </div>
                    <?php endif; ?>

                    <!-- Form -->
                    <form method="POST" enctype="multipart/form-data" id="productForm">
                        <div class="row">
                            <!-- Left Column - Main Info -->
                            <div class="col-lg-8">
                                <!-- Basic Information -->
                                <div class="form-card">
                                    <div class="card-header-custom">
                                        <h5 class="mb-0">
                                            <i class="fas fa-info-circle me-2"></i>
                                            Thông tin cơ bản
                                        </h5>
                                    </div>
                                    <div class="form-section">
                                        <div class="row">
                                            <div class="col-md-8">
                                                <div class="mb-3">
                                                    <label for="ten_san_pham" class="form-label">
                                                        Tên sản phẩm <span class="text-danger">*</span>
                                                    </label>
                                                    <input type="text" 
                                                           class="form-control" 
                                                           id="ten_san_pham" 
                                                           name="ten_san_pham" 
                                                           value="<?= htmlspecialchars($_POST['ten_san_pham'] ?? '') ?>" 
                                                           required
                                                           placeholder="Nhập tên sản phẩm...">
                                                </div>
                                            </div>
                                            <div class="col-md-4">
                                                <div class="mb-3">
                                                    <label for="ma_san_pham" class="form-label">Mã sản phẩm</label>
                                                    <input type="text" 
                                                           class="form-control" 
                                                           id="ma_san_pham" 
                                                           name="ma_san_pham" 
                                                           value="<?= htmlspecialchars($_POST['ma_san_pham'] ?? '') ?>"
                                                           placeholder="Tự động tạo nếu để trống">
                                                </div>
                                            </div>
                                        </div>

                                        <div class="mb-3">
                                            <label for="mo_ta_ngan" class="form-label">Mô tả ngắn</label>
                                            <textarea class="form-control" 
                                                      id="mo_ta_ngan" 
                                                      name="mo_ta_ngan" 
                                                      rows="3"
                                                      placeholder="Mô tả ngắn gọn về sản phẩm..."><?= htmlspecialchars($_POST['mo_ta_ngan'] ?? '') ?></textarea>
                                        </div>

                                        <div class="mb-3">
                                            <label for="mo_ta_chi_tiet" class="form-label">Mô tả chi tiết</label>
                                            <textarea class="form-control" 
                                                      id="mo_ta_chi_tiet" 
                                                      name="mo_ta_chi_tiet" 
                                                      rows="8"><?= htmlspecialchars($_POST['mo_ta_chi_tiet'] ?? '') ?></textarea>
                                        </div>
                                    </div>
                                </div>

                                <!-- Images -->
                                <div class="form-card">
                                    <div class="card-header-custom">
                                        <h5 class="mb-0">
                                            <i class="fas fa-images me-2"></i>
                                            Hình ảnh sản phẩm
                                        </h5>
                                    </div>
                                    <div class="form-section">
                                        <!-- Main Image -->
                                        <div class="mb-4">
                                            <label class="form-label">Ảnh chính sản phẩm</label>
                                            <div class="upload-area" onclick="document.getElementById('hinh_anh_chinh').click()">
                                                <i class="fas fa-cloud-upload-alt fa-3x text-muted mb-3"></i>
                                                <h6>Click để tải ảnh chính</h6>
                                                <p class="text-muted mb-0">Hỗ trợ: JPG, PNG, GIF (tối đa 5MB)</p>
                                            </div>
                                            <input type="file" 
                                                   id="hinh_anh_chinh" 
                                                   name="hinh_anh_chinh" 
                                                   accept="image/*" 
                                                   style="display: none;"
                                                   onchange="previewMainImage(this)">
                                            <div id="main-preview" class="preview-container"></div>
                                        </div>

                                        <!-- Album Images -->
                                        <div class="mb-3">
                                            <label class="form-label">Album ảnh</label>
                                            <div class="upload-area" onclick="document.getElementById('album_hinh_anh').click()">
                                                <i class="fas fa-images fa-3x text-muted mb-3"></i>
                                                <h6>Click để tải nhiều ảnh</h6>
                                                <p class="text-muted mb-0">Có thể chọn nhiều ảnh cùng lúc</p>
                                            </div>
                                            <input type="file" 
                                                   id="album_hinh_anh" 
                                                   name="album_hinh_anh[]" 
                                                   accept="image/*" 
                                                   multiple
                                                   style="display: none;"
                                                   onchange="previewAlbumImages(this)">
                                            <div id="album-preview" class="preview-container"></div>
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <!-- Right Column - Properties -->
                            <div class="col-lg-4">
                                <!-- Category & Brand -->
                                <div class="form-card">
                                    <div class="card-header-custom">
                                        <h5 class="mb-0">
                                            <i class="fas fa-tags me-2"></i>
                                            Phân loại
                                        </h5>
                                    </div>
                                    <div class="form-section">
                                        <div class="mb-3">
                                            <label for="danh_muc_id" class="form-label">
                                                Danh mục <span class="text-danger">*</span>
                                            </label>
                                            <select class="form-select" id="danh_muc_id" name="danh_muc_id" required>
                                                <option value="">Chọn danh mục</option>
                                                <?php foreach ($categories as $category): ?>
                                                    <option value="<?= $category['id'] ?>" 
                                                            <?= (isset($_POST['danh_muc_id']) && $_POST['danh_muc_id'] == $category['id']) ? 'selected' : '' ?>>
                                                        <?= htmlspecialchars($category['ten_danh_muc']) ?>
                                                    </option>
                                                <?php endforeach; ?>
                                            </select>
                                        </div>

                                        <div class="mb-3">
                                            <label for="thuong_hieu" class="form-label">Thương hiệu</label>
                                            <input type="text" 
                                                   class="form-control" 
                                                   id="thuong_hieu" 
                                                   name="thuong_hieu" 
                                                   value="<?= htmlspecialchars($_POST['thuong_hieu'] ?? '') ?>"
                                                   placeholder="Nike, Adidas, Converse...">
                                        </div>
                                    </div>
                                </div>

                                <!-- Pricing -->
                                <div class="form-card">
                                    <div class="card-header-custom">
                                        <h5 class="mb-0">
                                            <i class="fas fa-dollar-sign me-2"></i>
                                            Giá bán
                                        </h5>
                                    </div>
                                    <div class="form-section">
                                        <div class="mb-3">
                                            <label for="gia_goc" class="form-label">
                                                Giá gốc <span class="text-danger">*</span>
                                            </label>
                                            <div class="price-input">
                                                <input type="number" 
                                                       class="form-control" 
                                                       id="gia_goc" 
                                                       name="gia_goc" 
                                                       value="<?= $_POST['gia_goc'] ?? '' ?>" 
                                                       required
                                                       min="1000"
                                                       step="1000"
                                                       placeholder="0">
                                            </div>
                                        </div>

                                        <div class="mb-3">
                                            <label for="gia_khuyen_mai" class="form-label">Giá khuyến mãi</label>
                                            <div class="price-input">
                                                <input type="number" 
                                                       class="form-control" 
                                                       id="gia_khuyen_mai" 
                                                       name="gia_khuyen_mai" 
                                                       value="<?= $_POST['gia_khuyen_mai'] ?? '' ?>"
                                                       min="1000"
                                                       step="1000"
                                                       placeholder="Để trống nếu không khuyến mãi">
                                            </div>
                                            <div class="form-text">
                                                <small>Giá khuyến mãi phải nhỏ hơn giá gốc</small>
                                            </div>
                                        </div>
                                    </div>
                                </div>

                                <!-- Features -->
                                <div class="form-card">
                                    <div class="card-header-custom">
                                        <h5 class="mb-0">
                                            <i class="fas fa-star me-2"></i>
                                            Tính năng đặc biệt
                                        </h5>
                                    </div>
                                    <div class="form-section">
                                        <div class="feature-toggle">
                                            <div class="d-flex justify-content-between align-items-center">
                                                <div>
                                                    <strong>Sản phẩm nổi bật</strong>
                                                    <div class="text-muted small">Hiển thị ở trang chủ</div>
                                                </div>
                                                <label class="toggle-switch">
                                                    <input type="checkbox" 
                                                           name="san_pham_noi_bat" 
                                                           value="1" 
                                                           <?= isset($_POST['san_pham_noi_bat']) ? 'checked' : '' ?>>
                                                    <span class="toggle-slider"></span>
                                                </label>
                                            </div>
                                        </div>

                                        <div class="feature-toggle">
                                            <div class="d-flex justify-content-between align-items-center">
                                                <div>
                                                    <strong>Sản phẩm mới</strong>
                                                    <div class="text-muted small">Hiển thị nhãn "NEW"</div>
                                                </div>
                                                <label class="toggle-switch">
                                                    <input type="checkbox" 
                                                           name="san_pham_moi" 
                                                           value="1" 
                                                           <?= isset($_POST['san_pham_moi']) ? 'checked' : '' ?>>
                                                    <span class="toggle-slider"></span>
                                                </label>
                                            </div>
                                        </div>
                                    </div>
                                </div>

                                <!-- Submit Button -->
                                <div class="form-card">
                                    <div class="form-section">
                                        <button type="submit" class="btn btn-save w-100">
                                            <i class="fas fa-save me-2"></i>
                                            Tạo sản phẩm
                                        </button>
                                        
                                        <div class="mt-3 text-center">
                                            <small class="text-muted">
                                                <i class="fas fa-info-circle me-1"></i>
                                                Sau khi tạo sẽ chuyển đến trang thêm biến thể
                                            </small>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>

    <!-- Scripts -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
    
    <script>
        // Initialize CKEditor
        CKEDITOR.replace('mo_ta_chi_tiet', {
            height: 200,
            toolbar: [
                ['Bold', 'Italic', 'Underline'],
                ['NumberedList', 'BulletedList'],
                ['Link', 'Unlink'],
                ['Source']
            ]
        });

        // Auto generate product code
        document.getElementById('ten_san_pham').addEventListener('input', function() {
            const productName = this.value;
            const maField = document.getElementById('ma_san_pham');
            
            if (maField.value === '') {
                // Simple slug for product code
                let code = productName.toUpperCase()
                    .replace(/[^A-Z0-9\s]/g, '')
                    .replace(/\s+/g, '')
                    .substring(0, 10);
                
                if (code) {
                    maField.value = code + '-' + Date.now().toString().slice(-4);
                }
            }
        });

        // Preview main image
        function previewMainImage(input) {
            const preview = document.getElementById('main-preview');
            preview.innerHTML = '';
            
            if (input.files && input.files[0]) {
                const file = input.files[0];
                if (file.type.startsWith('image/')) {
                    const reader = new FileReader();
                    reader.onload = function(e) {
                        const div = document.createElement('div');
                        div.className = 'preview-item';
                        div.innerHTML = `
                            <img src="${e.target.result}" alt="Preview">
                            <button type="button" class="preview-remove" onclick="removeMainImage()">
                                <i class="fas fa-times"></i>
                            </button>
                        `;
                        preview.appendChild(div);
                    };
                    reader.readAsDataURL(file);
                }
            }
        }

        // Preview album images
        function previewAlbumImages(input) {
            const preview = document.getElementById('album-preview');
            preview.innerHTML = '';
            
            if (input.files) {
                Array.from(input.files).forEach((file, index) => {
                    if (file.type.startsWith('image/')) {
                        const reader = new FileReader();
                        reader.onload = function(e) {
                            const div = document.createElement('div');
                            div.className = 'preview-item';
                            div.innerHTML = `
                                <img src="${e.target.result}" alt="Preview ${index + 1}">
                                <button type="button" class="preview-remove" onclick="removeAlbumImage(${index})">
                                    <i class="fas fa-times"></i>
                                </button>
                            `;
                            preview.appendChild(div);
                        };
                        reader.readAsDataURL(file);
                    }
                });
            }
        }

        // Remove main image
        function removeMainImage() {
            document.getElementById('hinh_anh_chinh').value = '';
            document.getElementById('main-preview').innerHTML = '';
        }

        // Remove album image
        function removeAlbumImage(index) {
            const input = document.getElementById('album_hinh_anh');
            const dt = new DataTransfer();
            
            Array.from(input.files).forEach((file, i) => {
                if (i !== index) {
                    dt.items.add(file);
                }
            });
            
            input.files = dt.files;
            previewAlbumImages(input);
        }

        // Validate form before submit
        document.getElementById('productForm').addEventListener('submit', function(e) {
            const giaGoc = parseInt(document.getElementById('gia_goc').value);
            const giaKhuyenMai = parseInt(document.getElementById('gia_khuyen_mai').value);
            
            if (giaKhuyenMai && giaKhuyenMai >= giaGoc) {
                e.preventDefault();
                alert('Giá khuyến mãi phải nhỏ hơn giá gốc!');
                return false;
            }
            
            // Show loading
            const submitBtn = this.querySelector('button[type="submit"]');
            const originalText = submitBtn.innerHTML;
            submitBtn.innerHTML = '<i class="fas fa-spinner fa-spin me-2"></i>Đang tạo...';
            submitBtn.disabled = true;
            
            // Re-enable after 10 seconds (in case of errors)
            setTimeout(() => {
                submitBtn.innerHTML = originalText;
                submitBtn.disabled = false;
            }, 10000);
        });

        // Drag and drop for images
        ['hinh_anh_chinh', 'album_hinh_anh'].forEach(id => {
            const uploadArea = document.querySelector(`input[name="${id}"]`).closest('.form-section').querySelector('.upload-area');
            
            uploadArea.addEventListener('dragover', function(e) {
                e.preventDefault();
                this.classList.add('dragover');
            });
            
            uploadArea.addEventListener('dragleave', function(e) {
                e.preventDefault();
                this.classList.remove('dragover');
            });
            
            uploadArea.addEventListener('drop', function(e) {
                e.preventDefault();
                this.classList.remove('dragover');
                
                const input = document.getElementById(id);
                input.files = e.dataTransfer.files;
                
                if (id === 'hinh_anh_chinh') {
                    previewMainImage(input);
                } else {
                    previewAlbumImages(input);
                }
            });
        });
    </script>
</body>
</html>