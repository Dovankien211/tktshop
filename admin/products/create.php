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
    $stmt = $pdo->query("SELECT * FROM danh_muc_giay WHERE trang_thai = 'hoat_dong' ORDER BY ten_danh_muc ASC");
    $categories = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    $error = "Lỗi khi tải danh mục: " . $e->getMessage();
}

// Xử lý form submit
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        // Validate dữ liệu
        $required_fields = [
            'ten_san_pham' => 'Tên sản phẩm',
            'thuong_hieu' => 'Thương hiệu',
            'danh_muc_id' => 'Danh mục',
            'gia_goc' => 'Giá gốc',
            'mo_ta_ngan' => 'Mô tả ngắn'
        ];
        
        foreach ($required_fields as $field => $label) {
            if (empty($_POST[$field])) {
                throw new Exception("$label không được để trống!");
            }
        }
        
        // Lấy dữ liệu từ form
        $ten_san_pham = trim($_POST['ten_san_pham']);
        $thuong_hieu = trim($_POST['thuong_hieu']);
        $danh_muc_id = (int)$_POST['danh_muc_id'];
        $gia_goc = (int)$_POST['gia_goc'];
        $gia_khuyen_mai = !empty($_POST['gia_khuyen_mai']) ? (int)$_POST['gia_khuyen_mai'] : null;
        $mo_ta_ngan = trim($_POST['mo_ta_ngan']);
        $mo_ta_chi_tiet = trim($_POST['mo_ta_chi_tiet'] ?? '');
        $tu_khoa_tim_kiem = trim($_POST['tu_khoa_tim_kiem'] ?? '');
        $trang_thai = $_POST['trang_thai'] ?? 'hoat_dong';
        
        // Validate giá
        if ($gia_goc <= 0) {
            throw new Exception("Giá gốc phải lớn hơn 0!");
        }
        
        if ($gia_khuyen_mai && $gia_khuyen_mai >= $gia_goc) {
            throw new Exception("Giá khuyến mãi phải nhỏ hơn giá gốc!");
        }
        
        // Tạo mã sản phẩm tự động
        $ma_san_pham = strtoupper($thuong_hieu . '-' . date('YmdHis') . '-' . rand(100, 999));
        
        // Tạo slug
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
            $upload_result = uploadFile($_FILES['hinh_anh_chinh'], 'products');
            if ($upload_result['success']) {
                $hinh_anh_chinh = $upload_result['filename'];
            } else {
                throw new Exception("Lỗi upload ảnh chính: " . $upload_result['message']);
            }
        }
        
        // Xử lý upload ảnh phụ
        $hinh_anh_phu = [];
        if (isset($_FILES['hinh_anh_phu']) && is_array($_FILES['hinh_anh_phu']['name'])) {
            for ($i = 0; $i < count($_FILES['hinh_anh_phu']['name']); $i++) {
                if ($_FILES['hinh_anh_phu']['error'][$i] === UPLOAD_ERR_OK) {
                    $file = [
                        'name' => $_FILES['hinh_anh_phu']['name'][$i],
                        'type' => $_FILES['hinh_anh_phu']['type'][$i],
                        'tmp_name' => $_FILES['hinh_anh_phu']['tmp_name'][$i],
                        'error' => $_FILES['hinh_anh_phu']['error'][$i],
                        'size' => $_FILES['hinh_anh_phu']['size'][$i]
                    ];
                    
                    $upload_result = uploadFile($file, 'products');
                    if ($upload_result['success']) {
                        $hinh_anh_phu[] = $upload_result['filename'];
                    }
                }
            }
        }
        
        $hinh_anh_phu_json = !empty($hinh_anh_phu) ? json_encode($hinh_anh_phu) : null;
        
        // Bắt đầu transaction
        $pdo->beginTransaction();
        
        // Insert sản phẩm chính
        $sql = "INSERT INTO san_pham_chinh (
                    ma_san_pham, ten_san_pham, slug, thuong_hieu, danh_muc_id,
                    gia_goc, gia_khuyen_mai, mo_ta_ngan, mo_ta_chi_tiet,
                    hinh_anh_chinh, hinh_anh_phu, tu_khoa_tim_kiem, trang_thai,
                    ngay_tao, ngay_cap_nhat
                ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW(), NOW())";
        
        $stmt = $pdo->prepare($sql);
        $stmt->execute([
            $ma_san_pham, $ten_san_pham, $slug, $thuong_hieu, $danh_muc_id,
            $gia_goc, $gia_khuyen_mai, $mo_ta_ngan, $mo_ta_chi_tiet,
            $hinh_anh_chinh, $hinh_anh_phu_json, $tu_khoa_tim_kiem, $trang_thai
        ]);
        
        $product_id = $pdo->lastInsertId();
        
        // Commit transaction
        $pdo->commit();
        
        $success = "Thêm sản phẩm thành công! Bây giờ bạn có thể thêm biến thể cho sản phẩm.";
        
        // Redirect đến trang quản lý biến thể
        header("Location: variants.php?product_id=" . $product_id . "&success=" . urlencode($success));
        exit();
        
    } catch (Exception $e) {
        // Rollback transaction nếu có lỗi
        if ($pdo->inTransaction()) {
            $pdo->rollback();
        }
        
        // Xóa file đã upload nếu có lỗi
        if (isset($hinh_anh_chinh)) {
            deleteUploadedFile($hinh_anh_chinh, 'products');
        }
        if (isset($hinh_anh_phu)) {
            foreach ($hinh_anh_phu as $filename) {
                deleteUploadedFile($filename, 'products');
            }
        }
        
        $error = $e->getMessage();
    }
}

$page_title = "Thêm sản phẩm mới";
$breadcrumbs = [
    ['title' => 'Dashboard', 'url' => '../dashboard.php'],
    ['title' => 'Sản phẩm', 'url' => 'index.php'],
    ['title' => 'Thêm mới', 'url' => 'create.php']
];
?>

<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= $page_title ?> - TKT Shop Admin</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <style>
        .image-preview {
            max-width: 200px;
            max-height: 200px;
            object-fit: cover;
            border-radius: 8px;
        }
        .upload-area {
            border: 2px dashed #ddd;
            border-radius: 8px;
            padding: 2rem;
            text-align: center;
            transition: border-color 0.3s ease;
        }
        .upload-area:hover {
            border-color: #007bff;
        }
        .upload-area.dragover {
            border-color: #007bff;
            background-color: #f8f9fa;
        }
        .form-section {
            background: #f8f9fa;
            border-radius: 8px;
            padding: 1.5rem;
            margin-bottom: 1.5rem;
        }
        .required {
            color: #dc3545;
        }
    </style>
</head>
<body>
    <div class="container-fluid">
        <div class="row">
            <!-- Sidebar -->
            <div class="col-md-2 p-0">
                <?php include '../layouts/sidebar.php'; ?>
            </div>
            
            <!-- Main content -->
            <div class="col-md-10">
                <!-- Header -->
                <?php include '../layouts/header.php'; ?>
                
                <div class="p-4">
                    <!-- Alert Messages -->
                    <?php if ($error): ?>
                        <div class="alert alert-danger alert-dismissible fade show">
                            <i class="fas fa-exclamation-triangle"></i> <?= htmlspecialchars($error) ?>
                            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                        </div>
                    <?php endif; ?>

                    <?php if ($success): ?>
                        <div class="alert alert-success alert-dismissible fade show">
                            <i class="fas fa-check-circle"></i> <?= htmlspecialchars($success) ?>
                            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                        </div>
                    <?php endif; ?>

                    <form method="POST" enctype="multipart/form-data" id="productForm">
                        <div class="row">
                            <!-- Left Column - Main Info -->
                            <div class="col-md-8">
                                <!-- Thông tin cơ bản -->
                                <div class="form-section">
                                    <h5 class="mb-3">
                                        <i class="fas fa-info-circle me-2"></i>Thông tin cơ bản
                                    </h5>
                                    
                                    <div class="row">
                                        <div class="col-md-8">
                                            <div class="mb-3">
                                                <label for="ten_san_pham" class="form-label">
                                                    Tên sản phẩm <span class="required">*</span>
                                                </label>
                                                <input type="text" class="form-control" id="ten_san_pham" name="ten_san_pham" 
                                                       value="<?= htmlspecialchars($_POST['ten_san_pham'] ?? '') ?>" required>
                                                <div class="form-text">Tên sản phẩm sẽ hiển thị cho khách hàng</div>
                                            </div>
                                        </div>
                                        <div class="col-md-4">
                                            <div class="mb-3">
                                                <label for="thuong_hieu" class="form-label">
                                                    Thương hiệu <span class="required">*</span>
                                                </label>
                                                <input type="text" class="form-control" id="thuong_hieu" name="thuong_hieu" 
                                                       value="<?= htmlspecialchars($_POST['thuong_hieu'] ?? '') ?>" 
                                                       list="brandsList" required>
                                                <datalist id="brandsList">
                                                    <option value="Nike">
                                                    <option value="Adidas">
                                                    <option value="Converse">
                                                    <option value="Vans">
                                                    <option value="Puma">
                                                    <option value="New Balance">
                                                    <option value="Fila">
                                                </datalist>
                                            </div>
                                        </div>
                                    </div>
                                    
                                    <div class="row">
                                        <div class="col-md-6">
                                            <div class="mb-3">
                                                <label for="danh_muc_id" class="form-label">
                                                    Danh mục <span class="required">*</span>
                                                </label>
                                                <select class="form-select" id="danh_muc_id" name="danh_muc_id" required>
                                                    <option value="">Chọn danh mục</option>
                                                    <?php foreach ($categories as $category): ?>
                                                        <option value="<?= $category['id'] ?>" 
                                                                <?= (($_POST['danh_muc_id'] ?? '') == $category['id']) ? 'selected' : '' ?>>
                                                            <?= htmlspecialchars($category['ten_danh_muc']) ?>
                                                        </option>
                                                    <?php endforeach; ?>
                                                </select>
                                            </div>
                                        </div>
                                        <div class="col-md-6">
                                            <div class="mb-3">
                                                <label for="trang_thai" class="form-label">Trạng thái</label>
                                                <select class="form-select" id="trang_thai" name="trang_thai">
                                                    <option value="hoat_dong" <?= (($_POST['trang_thai'] ?? 'hoat_dong') === 'hoat_dong') ? 'selected' : '' ?>>
                                                        Hoạt động
                                                    </option>
                                                    <option value="an" <?= (($_POST['trang_thai'] ?? '') === 'an') ? 'selected' : '' ?>>
                                                        Ẩn sản phẩm
                                                    </option>
                                                </select>
                                            </div>
                                        </div>
                                    </div>
                                </div>

                                <!-- Giá bán -->
                                <div class="form-section">
                                    <h5 class="mb-3">
                                        <i class="fas fa-money-bill-wave me-2"></i>Giá bán
                                    </h5>
                                    
                                    <div class="row">
                                        <div class="col-md-6">
                                            <div class="mb-3">
                                                <label for="gia_goc" class="form-label">
                                                    Giá gốc <span class="required">*</span>
                                                </label>
                                                <div class="input-group">
                                                    <input type="number" class="form-control" id="gia_khuyen_mai" name="gia_khuyen_mai" 
                                                           value="<?= htmlspecialchars($_POST['gia_khuyen_mai'] ?? '') ?>" 
                                                           min="1000" step="1000">
                                                    <span class="input-group-text">₫</span>
                                                </div>
                                                <div class="form-text">Giá bán có khuyến mãi (để trống nếu không có)</div>
                                            </div>
                                        </div>
                                    </div>
                                    
                                    <div class="row">
                                        <div class="col-12">
                                            <div class="alert alert-info">
                                                <i class="fas fa-info-circle"></i>
                                                <strong>Lưu ý:</strong> Giá của từng biến thể (size, màu) có thể khác nhau và sẽ được thiết lập sau khi tạo sản phẩm.
                                            </div>
                                        </div>
                                    </div>
                                </div>

                                <!-- Mô tả sản phẩm -->
                                <div class="form-section">
                                    <h5 class="mb-3">
                                        <i class="fas fa-file-alt me-2"></i>Mô tả sản phẩm
                                    </h5>
                                    
                                    <div class="mb-3">
                                        <label for="mo_ta_ngan" class="form-label">
                                            Mô tả ngắn <span class="required">*</span>
                                        </label>
                                        <textarea class="form-control" id="mo_ta_ngan" name="mo_ta_ngan" rows="3" 
                                                  maxlength="200" required><?= htmlspecialchars($_POST['mo_ta_ngan'] ?? '') ?></textarea>
                                        <div class="form-text">
                                            Mô tả ngắn gọn về sản phẩm (tối đa 200 ký tự)
                                            <span id="shortDescCount">0/200</span>
                                        </div>
                                    </div>
                                    
                                    <div class="mb-3">
                                        <label for="mo_ta_chi_tiet" class="form-label">Mô tả chi tiết</label>
                                        <textarea class="form-control" id="mo_ta_chi_tiet" name="mo_ta_chi_tiet" rows="8"><?= htmlspecialchars($_POST['mo_ta_chi_tiet'] ?? '') ?></textarea>
                                        <div class="form-text">Mô tả chi tiết về sản phẩm, chất liệu, tính năng...</div>
                                    </div>
                                    
                                    <div class="mb-3">
                                        <label for="tu_khoa_tim_kiem" class="form-label">Từ khóa tìm kiếm</label>
                                        <input type="text" class="form-control" id="tu_khoa_tim_kiem" name="tu_khoa_tim_kiem" 
                                               value="<?= htmlspecialchars($_POST['tu_khoa_tim_kiem'] ?? '') ?>">
                                        <div class="form-text">Các từ khóa để tìm kiếm sản phẩm, cách nhau bằng dấu phẩy</div>
                                    </div>
                                </div>
                            </div>

                            <!-- Right Column - Images -->
                            <div class="col-md-4">
                                <!-- Ảnh sản phẩm -->
                                <div class="form-section">
                                    <h5 class="mb-3">
                                        <i class="fas fa-images me-2"></i>Hình ảnh sản phẩm
                                    </h5>
                                    
                                    <!-- Ảnh chính -->
                                    <div class="mb-4">
                                        <label for="hinh_anh_chinh" class="form-label">
                                            Ảnh chính <span class="required">*</span>
                                        </label>
                                        <div class="upload-area" id="mainImageUpload">
                                            <i class="fas fa-cloud-upload-alt fa-2x text-muted mb-2"></i>
                                            <p class="mb-2">Kéo thả ảnh vào đây hoặc click để chọn</p>
                                            <input type="file" class="form-control" id="hinh_anh_chinh" name="hinh_anh_chinh" 
                                                   accept="image/*" style="display: none;">
                                            <button type="button" class="btn btn-outline-primary btn-sm" 
                                                    onclick="document.getElementById('hinh_anh_chinh').click()">
                                                Chọn ảnh chính
                                            </button>
                                        </div>
                                        <div id="mainImagePreview"></div>
                                        <div class="form-text">Ảnh chính hiển thị trên danh sách sản phẩm (tối đa 2MB)</div>
                                    </div>
                                    
                                    <!-- Ảnh phụ -->
                                    <div class="mb-4">
                                        <label for="hinh_anh_phu" class="form-label">Ảnh phụ</label>
                                        <div class="upload-area" id="subImagesUpload">
                                            <i class="fas fa-images fa-2x text-muted mb-2"></i>
                                            <p class="mb-2">Thêm nhiều ảnh cho sản phẩm</p>
                                            <input type="file" class="form-control" id="hinh_anh_phu" name="hinh_anh_phu[]" 
                                                   accept="image/*" multiple style="display: none;">
                                            <button type="button" class="btn btn-outline-secondary btn-sm" 
                                                    onclick="document.getElementById('hinh_anh_phu').click()">
                                                Chọn ảnh phụ
                                            </button>
                                        </div>
                                        <div id="subImagesPreview"></div>
                                        <div class="form-text">Có thể chọn nhiều ảnh (tối đa 5 ảnh, mỗi ảnh 2MB)</div>
                                    </div>
                                </div>

                                <!-- Hướng dẫn -->
                                <div class="card bg-light">
                                    <div class="card-header">
                                        <h6 class="mb-0">
                                            <i class="fas fa-lightbulb me-2"></i>Hướng dẫn
                                        </h6>
                                    </div>
                                    <div class="card-body">
                                        <ul class="list-unstyled mb-0">
                                            <li class="mb-2">
                                                <i class="fas fa-check text-success me-2"></i>
                                                Điền đầy đủ thông tin cơ bản
                                            </li>
                                            <li class="mb-2">
                                                <i class="fas fa-check text-success me-2"></i>
                                                Upload ảnh chất lượng cao
                                            </li>
                                            <li class="mb-2">
                                                <i class="fas fa-check text-success me-2"></i>
                                                Sau khi tạo, thêm biến thể
                                            </li>
                                            <li class="mb-0">
                                                <i class="fas fa-check text-success me-2"></i>
                                                Kiểm tra trước khi lưu
                                            </li>
                                        </ul>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- Action Buttons -->
                        <div class="row mt-4">
                            <div class="col-12">
                                <div class="d-flex justify-content-between">
                                    <div>
                                        <a href="index.php" class="btn btn-outline-secondary">
                                            <i class="fas fa-arrow-left me-2"></i>Quay lại danh sách
                                        </a>
                                    </div>
                                    <div>
                                        <button type="button" class="btn btn-outline-primary me-2" onclick="previewProduct()">
                                            <i class="fas fa-eye me-2"></i>Xem trước
                                        </button>
                                        <button type="submit" class="btn btn-success">
                                            <i class="fas fa-save me-2"></i>Lưu sản phẩm
                                        </button>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>

    <!-- Preview Modal -->
    <div class="modal fade" id="previewModal" tabindex="-1">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">
                        <i class="fas fa-eye me-2"></i>Xem trước sản phẩm
                    </h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body" id="previewContent">
                    <!-- Preview content will be generated here -->
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Đóng</button>
                    <button type="button" class="btn btn-success" onclick="submitForm()">
                        <i class="fas fa-save me-2"></i>Lưu sản phẩm
                    </button>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        // Character count for short description
        document.getElementById('mo_ta_ngan').addEventListener('input', function() {
            const count = this.value.length;
            document.getElementById('shortDescCount').textContent = count + '/200';
            
            if (count > 200) {
                this.value = this.value.substring(0, 200);
                document.getElementById('shortDescCount').textContent = '200/200';
            }
        });

        // Image upload handlers
        document.getElementById('hinh_anh_chinh').addEventListener('change', function() {
            previewMainImage(this);
        });

        document.getElementById('hinh_anh_phu').addEventListener('change', function() {
            previewSubImages(this);
        });

        // Drag and drop for main image
        const mainUpload = document.getElementById('mainImageUpload');
        mainUpload.addEventListener('dragover', function(e) {
            e.preventDefault();
            this.classList.add('dragover');
        });

        mainUpload.addEventListener('dragleave', function(e) {
            e.preventDefault();
            this.classList.remove('dragover');
        });

        mainUpload.addEventListener('drop', function(e) {
            e.preventDefault();
            this.classList.remove('dragover');
            
            const files = e.dataTransfer.files;
            if (files.length > 0) {
                document.getElementById('hinh_anh_chinh').files = files;
                previewMainImage(document.getElementById('hinh_anh_chinh'));
            }
        });

        // Preview main image
        function previewMainImage(input) {
            const preview = document.getElementById('mainImagePreview');
            preview.innerHTML = '';
            
            if (input.files && input.files[0]) {
                const file = input.files[0];
                
                // Validate file size
                if (file.size > 2 * 1024 * 1024) {
                    alert('Ảnh quá lớn! Vui lòng chọn ảnh nhỏ hơn 2MB.');
                    input.value = '';
                    return;
                }
                
                const reader = new FileReader();
                reader.onload = function(e) {
                    preview.innerHTML = `
                        <div class="mt-3">
                            <img src="${e.target.result}" class="image-preview" alt="Preview">
                            <div class="mt-2">
                                <small class="text-muted">${file.name} (${formatFileSize(file.size)})</small>
                                <button type="button" class="btn btn-sm btn-outline-danger ms-2" onclick="removeMainImage()">
                                    <i class="fas fa-times"></i> Xóa
                                </button>
                            </div>
                        </div>
                    `;
                };
                reader.readAsDataURL(file);
            }
        }

        // Preview sub images
        function previewSubImages(input) {
            const preview = document.getElementById('subImagesPreview');
            preview.innerHTML = '';
            
            if (input.files) {
                if (input.files.length > 5) {
                    alert('Tối đa 5 ảnh phụ!');
                    return;
                }
                
                Array.from(input.files).forEach((file, index) => {
                    if (file.size > 2 * 1024 * 1024) {
                        alert(`Ảnh ${file.name} quá lớn! Vui lòng chọn ảnh nhỏ hơn 2MB.`);
                        return;
                    }
                    
                    const reader = new FileReader();
                    reader.onload = function(e) {
                        const imageDiv = document.createElement('div');
                        imageDiv.className = 'mt-2 d-flex align-items-center';
                        imageDiv.innerHTML = `
                            <img src="${e.target.result}" class="image-preview me-2" style="width: 60px; height: 60px;" alt="Preview">
                            <div class="flex-grow-1">
                                <small class="text-muted">${file.name}</small><br>
                                <small class="text-muted">${formatFileSize(file.size)}</small>
                            </div>
                            <button type="button" class="btn btn-sm btn-outline-danger" onclick="removeSubImage(${index})">
                                <i class="fas fa-times"></i>
                            </button>
                        `;
                        preview.appendChild(imageDiv);
                    };
                    reader.readAsDataURL(file);
                });
            }
        }

        // Remove main image
        function removeMainImage() {
            document.getElementById('hinh_anh_chinh').value = '';
            document.getElementById('mainImagePreview').innerHTML = '';
        }

        // Remove sub image
        function removeSubImage(index) {
            const input = document.getElementById('hinh_anh_phu');
            const dt = new DataTransfer();
            
            Array.from(input.files).forEach((file, i) => {
                if (i !== index) {
                    dt.items.add(file);
                }
            });
            
            input.files = dt.files;
            previewSubImages(input);
        }

        // Format file size
        function formatFileSize(bytes) {
            if (bytes === 0) return '0 Bytes';
            const k = 1024;
            const sizes = ['Bytes', 'KB', 'MB', 'GB'];
            const i = Math.floor(Math.log(bytes) / Math.log(k));
            return parseFloat((bytes / Math.pow(k, i)).toFixed(2)) + ' ' + sizes[i];
        }

        // Preview product
        function previewProduct() {
            const formData = new FormData(document.getElementById('productForm'));
            
            // Validate required fields
            const requiredFields = ['ten_san_pham', 'thuong_hieu', 'danh_muc_id', 'gia_goc', 'mo_ta_ngan'];
            let isValid = true;
            
            requiredFields.forEach(field => {
                const input = document.querySelector(`[name="${field}"]`);
                if (!input.value.trim()) {
                    input.classList.add('is-invalid');
                    isValid = false;
                } else {
                    input.classList.remove('is-invalid');
                }
            });
            
            if (!isValid) {
                alert('Vui lòng điền đầy đủ thông tin bắt buộc!');
                return;
            }
            
            // Generate preview
            const preview = generatePreview(formData);
            document.getElementById('previewContent').innerHTML = preview;
            
            // Show modal
            new bootstrap.Modal(document.getElementById('previewModal')).show();
        }

        // Generate preview HTML
        function generatePreview(formData) {
            const ten_san_pham = formData.get('ten_san_pham');
            const thuong_hieu = formData.get('thuong_hieu');
            const gia_goc = parseInt(formData.get('gia_goc'));
            const gia_khuyen_mai = formData.get('gia_khuyen_mai') ? parseInt(formData.get('gia_khuyen_mai')) : null;
            const mo_ta_ngan = formData.get('mo_ta_ngan');
            
            const mainImage = document.getElementById('hinh_anh_chinh').files[0];
            const mainImageSrc = mainImage ? URL.createObjectURL(mainImage) : '/tktshop/uploads/products/no-image.jpg';
            
            return `
                <div class="row">
                    <div class="col-md-5">
                        <img src="${mainImageSrc}" class="img-fluid rounded" alt="Product Preview">
                    </div>
                    <div class="col-md-7">
                        <h4>${ten_san_pham}</h4>
                        <p class="text-muted">Thương hiệu: <strong>${thuong_hieu}</strong></p>
                        <div class="mb-3">
                            ${gia_khuyen_mai ? 
                                `<h5 class="text-danger">${formatPrice(gia_khuyen_mai)} <small class="text-muted text-decoration-line-through">${formatPrice(gia_goc)}</small></h5>` :
                                `<h5 class="text-primary">${formatPrice(gia_goc)}</h5>`
                            }
                        </div>
                        <p>${mo_ta_ngan}</p>
                        <div class="alert alert-info">
                            <i class="fas fa-info-circle"></i>
                            Đây là bản xem trước. Biến thể (size, màu) sẽ được thêm sau khi lưu sản phẩm.
                        </div>
                    </div>
                </div>
            `;
        }

        // Format price for preview
        function formatPrice(price) {
            return new Intl.NumberFormat('vi-VN', {
                style: 'currency',
                currency: 'VND'
            }).format(price);
        }

        // Submit form
        function submitForm() {
            document.getElementById('productForm').submit();
        }

        // Price validation
        document.getElementById('gia_khuyen_mai').addEventListener('change', function() {
            const giaGoc = parseInt(document.getElementById('gia_goc').value);
            const giaKhuyenMai = parseInt(this.value);
            
            if (giaKhuyenMai && giaKhuyenMai >= giaGoc) {
                alert('Giá khuyến mãi phải nhỏ hơn giá gốc!');
                this.value = '';
            }
        });

        // Form validation on submit
        document.getElementById('productForm').addEventListener('submit', function(e) {
            const requiredFields = ['ten_san_pham', 'thuong_hieu', 'danh_muc_id', 'gia_goc', 'mo_ta_ngan'];
            let isValid = true;
            
            requiredFields.forEach(field => {
                const input = document.querySelector(`[name="${field}"]`);
                if (!input.value.trim()) {
                    input.classList.add('is-invalid');
                    isValid = false;
                } else {
                    input.classList.remove('is-invalid');
                }
            });
            
            if (!isValid) {
                e.preventDefault();
                alert('Vui lòng điền đầy đủ thông tin bắt buộc!');
                return;
            }
            
            // Show loading
            const submitBtn = this.querySelector('button[type="submit"]');
            submitBtn.innerHTML = '<i class="fas fa-spinner fa-spin me-2"></i>Đang lưu...';
            submitBtn.disabled = true;
        });

        // Initialize page
        document.addEventListener('DOMContentLoaded', function() {
            // Auto-dismiss alerts
            setTimeout(() => {
                const alerts = document.querySelectorAll('.alert');
                alerts.forEach(alert => {
                    if (alert.querySelector('.btn-close')) {
                        alert.querySelector('.btn-close').click();
                    }
                });
            }, 5000);
            
            // Update character count on load
            const descInput = document.getElementById('mo_ta_ngan');
            if (descInput.value) {
                document.getElementById('shortDescCount').textContent = descInput.value.length + '/200';
            }
        });
    </script>
</body>
</html>_goc" name="gia_goc" 
                                                           value="<?= htmlspecialchars($_POST['gia_goc'] ?? '') ?>" 
                                                           min="1000" step="1000" required>
                                                    <span class="input-group-text">₫</span>
                                                </div>
                                                <div class="form-text">Giá bán chính thức của sản phẩm</div>
                                            </div>
                                        </div>
                                        <div class="col-md-6">
                                            <div class="mb-3">
                                                <label for="gia_khuyen_mai" class="form-label">Giá khuyến mãi</label>
                                                <div class="input-group">
                                                    <input type="number" class="form-control" id="gia