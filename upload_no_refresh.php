<?php
/**
 * Upload ảnh không refresh - TKTShop
 * File: /tktshop/upload_no_refresh.php
 */

require_once 'config/database.php';

// Xử lý AJAX upload
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['ajax_upload'])) {
    header('Content-Type: application/json');
    
    try {
        $pdo = new PDO("mysql:host=localhost;dbname=tktshop;charset=utf8mb4", "root", "");
        $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        
        $uploadType = $_POST['upload_type'] ?? '';
        $itemId = $_POST['item_id'] ?? '';
        $imageType = $_POST['image_type'] ?? '';
        
        if (!isset($_FILES['image']) || $_FILES['image']['error'] != 0) {
            throw new Exception('Vui lòng chọn file ảnh!');
        }
        
        $file = $_FILES['image'];
        $allowedTypes = ['image/jpeg', 'image/jpg', 'image/png', 'image/gif'];
        $maxSize = 2 * 1024 * 1024; // 2MB
        
        if (!in_array($file['type'], $allowedTypes)) {
            throw new Exception('Chỉ chấp nhận file JPG, PNG, GIF!');
        }
        
        if ($file['size'] > $maxSize) {
            throw new Exception('File quá lớn! Tối đa 2MB.');
        }
        
        // Tạo tên file unique
        $extension = pathinfo($file['name'], PATHINFO_EXTENSION);
        $fileName = time() . '_' . uniqid() . '.' . $extension;
        
        // Xác định thư mục và table
        if ($uploadType == 'category') {
            $uploadDir = 'uploads/categories/';
            $table = 'danh_muc_giay';
            $dbField = 'hinh_anh';
        } elseif ($uploadType == 'product') {
            $uploadDir = 'uploads/products/';
            $table = 'san_pham_chinh';
            $dbField = ($imageType == 'main') ? 'hinh_anh_chinh' : 'album_hinh_anh';
        } else {
            throw new Exception('Loại upload không hợp lệ!');
        }
        
        $uploadPath = $uploadDir . $fileName;
        
        // Upload file
        if (!move_uploaded_file($file['tmp_name'], $uploadPath)) {
            throw new Exception('Lỗi upload file!');
        }
        
        // Cập nhật database
        if ($uploadType == 'category' || ($uploadType == 'product' && $imageType == 'main')) {
            $stmt = $pdo->prepare("UPDATE {$table} SET {$dbField} = ? WHERE id = ?");
            $stmt->execute([$fileName, $itemId]);
        } else {
            // Thêm vào album ảnh
            $stmt = $pdo->prepare("SELECT album_hinh_anh FROM {$table} WHERE id = ?");
            $stmt->execute([$itemId]);
            $currentAlbum = $stmt->fetchColumn();
            
            $album = $currentAlbum ? json_decode($currentAlbum, true) : [];
            $album[] = $fileName;
            
            $stmt = $pdo->prepare("UPDATE {$table} SET album_hinh_anh = ? WHERE id = ?");
            $stmt->execute([json_encode($album), $itemId]);
        }
        
        echo json_encode([
            'success' => true,
            'message' => 'Upload ảnh thành công!',
            'fileName' => $fileName,
            'filePath' => $uploadPath
        ]);
        
    } catch (Exception $e) {
        echo json_encode([
            'success' => false,
            'message' => $e->getMessage()
        ]);
    }
    exit;
}

// Lấy dữ liệu cho giao diện
try {
    $pdo = new PDO("mysql:host=localhost;dbname=tktshop;charset=utf8mb4", "root", "");
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    
    // Lấy danh mục thiếu ảnh
    $missingCategories = $pdo->query("
        SELECT id, ten_danh_muc, hinh_anh 
        FROM danh_muc_giay 
        WHERE hinh_anh IS NULL OR hinh_anh = ''
        ORDER BY id
    ")->fetchAll(PDO::FETCH_ASSOC);
    
    // Lấy sản phẩm
    $products = $pdo->query("
        SELECT id, ten_san_pham, thuong_hieu, hinh_anh_chinh, album_hinh_anh
        FROM san_pham_chinh 
        ORDER BY id
    ")->fetchAll(PDO::FETCH_ASSOC);
    
} catch(PDOException $e) {
    die("Lỗi database: " . $e->getMessage());
}
?>
<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Upload Ảnh Không Refresh - TKTShop</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <style>
        .upload-section { margin-bottom: 2rem; }
        .item-card { margin-bottom: 1rem; transition: all 0.3s ease; }
        .preview-img { max-width: 80px; max-height: 80px; object-fit: cover; border-radius: 4px; }
        .missing-img { 
            background: #f8f9fa; 
            border: 2px dashed #dee2e6; 
            padding: 30px; 
            text-align: center; 
            border-radius: 8px;
            transition: all 0.3s ease;
        }
        .missing-img:hover { border-color: #007bff; background: #f0f8ff; }
        .upload-progress { display: none; }
        .upload-success { background: #d4edda !important; border-color: #c3e6cb !important; }
        .upload-error { background: #f8d7da !important; border-color: #f1b0b7 !important; }
        .btn-upload { position: relative; overflow: hidden; }
        .btn-upload input[type=file] { 
            position: absolute; 
            left: -9999px; 
        }
    </style>
</head>
<body>
<div class="container-fluid mt-4">
    <div class="row">
        <div class="col-12">
            <h1 class="text-center mb-4">
                <i class="fas fa-upload"></i> Upload Ảnh Không Refresh
            </h1>
            
            <!-- Alert container -->
            <div id="alertContainer"></div>

            <!-- Progress bar -->
            <div class="progress upload-progress mb-3">
                <div class="progress-bar progress-bar-striped progress-bar-animated" 
                     role="progressbar" style="width: 0%"></div>
            </div>

            <!-- Tabs -->
            <ul class="nav nav-tabs mb-4" role="tablist">
                <li class="nav-item">
                    <a class="nav-link active" data-bs-toggle="tab" href="#categories">
                        <i class="fas fa-folder"></i> Danh mục thiếu ảnh (<?= count($missingCategories) ?>)
                    </a>
                </li>
                <li class="nav-item">
                    <a class="nav-link" data-bs-toggle="tab" href="#products">
                        <i class="fas fa-shoe-prints"></i> Sản phẩm (4 sản phẩm)
                    </a>
                </li>
                <li class="nav-item">
                    <a class="nav-link" data-bs-toggle="tab" href="#quick-stats">
                        <i class="fas fa-chart-bar"></i> Thống kê nhanh
                    </a>
                </li>
            </ul>

            <div class="tab-content">
                <!-- TAB DANH MỤC -->
                <div class="tab-pane fade show active" id="categories">
                    <div class="card">
                        <div class="card-header bg-primary text-white">
                            <h4><i class="fas fa-folder"></i> Danh mục thiếu ảnh</h4>
                        </div>
                        <div class="card-body">
                            <?php if (empty($missingCategories)): ?>
                            <div class="alert alert-success">
                                <i class="fas fa-check"></i> Tất cả danh mục đã có ảnh!
                            </div>
                            <?php else: ?>
                            <div class="row">
                                <?php foreach ($missingCategories as $category): ?>
                                <div class="col-md-4 col-lg-3 item-card" id="cat-<?= $category['id'] ?>">
                                    <div class="card h-100">
                                        <div class="card-header text-center">
                                            <strong><?= htmlspecialchars($category['ten_danh_muc']) ?></strong>
                                            <br><small class="text-muted">ID: <?= $category['id'] ?></small>
                                        </div>
                                        <div class="card-body text-center">
                                            <div class="missing-img mb-3" id="preview-cat-<?= $category['id'] ?>">
                                                <i class="fas fa-image fa-3x text-muted mb-2"></i>
                                                <p class="mb-0 text-muted">Chưa có ảnh</p>
                                            </div>
                                            
                                            <form class="upload-form" data-type="category" data-id="<?= $category['id'] ?>">
                                                <div class="btn-upload">
                                                    <button type="button" class="btn btn-primary w-100 upload-btn">
                                                        <i class="fas fa-upload"></i> Chọn ảnh
                                                    </button>
                                                    <input type="file" name="image" accept="image/*" class="file-input">
                                                </div>
                                            </form>
                                        </div>
                                    </div>
                                </div>
                                <?php endforeach; ?>
                            </div>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>

                <!-- TAB SẢN PHẨM -->
                <div class="tab-pane fade" id="products">
                    <div class="card">
                        <div class="card-header bg-success text-white">
                            <h4><i class="fas fa-shoe-prints"></i> Sản phẩm cần ảnh</h4>
                        </div>
                        <div class="card-body">
                            <?php foreach ($products as $product): 
                                $hasMainImage = !empty($product['hinh_anh_chinh']) && 
                                               file_exists('uploads/products/' . $product['hinh_anh_chinh']);
                                $albumImages = $product['album_hinh_anh'] ? json_decode($product['album_hinh_anh'], true) : [];
                                $validAlbumCount = 0;
                                if ($albumImages) {
                                    foreach ($albumImages as $img) {
                                        if (file_exists('uploads/products/' . $img)) $validAlbumCount++;
                                    }
                                }
                            ?>
                            <div class="card mb-3" id="prod-<?= $product['id'] ?>">
                                <div class="card-header">
                                    <div class="row align-items-center">
                                        <div class="col">
                                            <h5 class="mb-0"><?= htmlspecialchars($product['ten_san_pham']) ?></h5>
                                            <small class="text-muted">
                                                <?= htmlspecialchars($product['thuong_hieu']) ?> (ID: <?= $product['id'] ?>)
                                            </small>
                                        </div>
                                        <div class="col-auto">
                                            <span class="badge bg-<?= $hasMainImage ? 'success' : 'danger' ?>">
                                                Ảnh chính: <?= $hasMainImage ? 'OK' : 'Thiếu' ?>
                                            </span>
                                            <span class="badge bg-<?= $validAlbumCount > 0 ? 'success' : 'warning' ?>">
                                                Album: <?= $validAlbumCount ?>/3
                                            </span>
                                        </div>
                                    </div>
                                </div>
                                <div class="card-body">
                                    <div class="row">
                                        <!-- Ảnh chính -->
                                        <div class="col-md-6">
                                            <h6>Ảnh chính:</h6>
                                            <div class="text-center">
                                                <div id="preview-prod-main-<?= $product['id'] ?>">
                                                    <?php if ($hasMainImage): ?>
                                                    <img src="uploads/products/<?= $product['hinh_anh_chinh'] ?>" 
                                                         class="preview-img img-thumbnail mb-2">
                                                    <p class="text-success"><i class="fas fa-check"></i> Có ảnh chính</p>
                                                    <?php else: ?>
                                                    <div class="missing-img mb-2">
                                                        <i class="fas fa-image fa-2x text-muted"></i>
                                                        <p class="mt-2 mb-0 text-muted">Thiếu ảnh chính</p>
                                                    </div>
                                                    <?php endif; ?>
                                                </div>
                                                
                                                <form class="upload-form" data-type="product" data-id="<?= $product['id'] ?>" data-image-type="main">
                                                    <div class="btn-upload">
                                                        <button type="button" class="btn btn-primary btn-sm w-100 upload-btn">
                                                            <i class="fas fa-upload"></i> Upload ảnh chính
                                                        </button>
                                                        <input type="file" name="image" accept="image/*" class="file-input">
                                                    </div>
                                                </form>
                                            </div>
                                        </div>
                                        
                                        <!-- Album ảnh -->
                                        <div class="col-md-6">
                                            <h6>Album ảnh:</h6>
                                            <div class="text-center">
                                                <div id="preview-prod-album-<?= $product['id'] ?>">
                                                    <?php if ($validAlbumCount > 0): ?>
                                                    <div class="d-flex flex-wrap gap-2 justify-content-center mb-2">
                                                        <?php foreach ($albumImages as $img): 
                                                            if (file_exists('uploads/products/' . $img)): ?>
                                                        <img src="uploads/products/<?= $img ?>" 
                                                             class="preview-img img-thumbnail">
                                                        <?php endif; endforeach; ?>
                                                    </div>
                                                    <p class="text-success"><i class="fas fa-check"></i> Có <?= $validAlbumCount ?> ảnh</p>
                                                    <?php else: ?>
                                                    <div class="missing-img mb-2">
                                                        <i class="fas fa-images fa-2x text-muted"></i>
                                                        <p class="mt-2 mb-0 text-muted">Thiếu album ảnh</p>
                                                    </div>
                                                    <?php endif; ?>
                                                </div>
                                                
                                                <form class="upload-form" data-type="product" data-id="<?= $product['id'] ?>" data-image-type="album">
                                                    <div class="btn-upload">
                                                        <button type="button" class="btn btn-info btn-sm w-100 upload-btn">
                                                            <i class="fas fa-plus"></i> Thêm ảnh album
                                                        </button>
                                                        <input type="file" name="image" accept="image/*" class="file-input">
                                                    </div>
                                                </form>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                            <?php endforeach; ?>
                        </div>
                    </div>
                </div>

                <!-- TAB THỐNG KÊ -->
                <div class="tab-pane fade" id="quick-stats">
                    <div class="card">
                        <div class="card-header bg-info text-white">
                            <h4><i class="fas fa-chart-bar"></i> Thống kê nhanh</h4>
                        </div>
                        <div class="card-body">
                            <div class="row text-center">
                                <div class="col-md-3">
                                    <div class="card bg-danger text-white">
                                        <div class="card-body">
                                            <h3 id="missing-cat-count"><?= count($missingCategories) ?></h3>
                                            <p>Danh mục thiếu ảnh</p>
                                        </div>
                                    </div>
                                </div>
                                <div class="col-md-3">
                                    <div class="card bg-warning text-dark">
                                        <div class="card-body">
                                            <h3 id="missing-prod-count">16</h3>
                                            <p>File ảnh thiếu</p>
                                        </div>
                                    </div>
                                </div>
                                <div class="col-md-3">
                                    <div class="card bg-success text-white">
                                        <div class="card-body">
                                            <h3 id="uploaded-count">0</h3>
                                            <p>Đã upload</p>
                                        </div>
                                    </div>
                                </div>
                                <div class="col-md-3">
                                    <div class="card bg-primary text-white">
                                        <div class="card-body">
                                            <h3 id="progress-percent">0%</h3>
                                            <p>Hoàn thành</p>
                                        </div>
                                    </div>
                                </div>
                            </div>
                            
                            <div class="mt-4 text-center">
                                <a href="debug_simple.php" class="btn btn-outline-primary me-2">
                                    <i class="fas fa-search"></i> Debug lại
                                </a>
                                <a href="customer/" class="btn btn-outline-success">
                                    <i class="fas fa-eye"></i> Xem website
                                </a>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
<script>
document.addEventListener('DOMContentLoaded', function() {
    let uploadedCount = 0;
    const totalIssues = <?= count($missingCategories) ?> + 16; // Danh mục + files sản phẩm
    
    // Xử lý upload cho tất cả form
    document.querySelectorAll('.upload-form').forEach(form => {
        const fileInput = form.querySelector('.file-input');
        const uploadBtn = form.querySelector('.upload-btn');
        
        // Click button -> trigger file input
        uploadBtn.addEventListener('click', () => {
            fileInput.click();
        });
        
        // Khi chọn file -> upload ngay
        fileInput.addEventListener('change', function() {
            if (this.files.length > 0) {
                uploadFile(form, this.files[0]);
            }
        });
    });
    
    function uploadFile(form, file) {
        const formData = new FormData();
        formData.append('image', file);
        formData.append('ajax_upload', '1');
        formData.append('upload_type', form.dataset.type);
        formData.append('item_id', form.dataset.id);
        if (form.dataset.imageType) {
            formData.append('image_type', form.dataset.imageType);
        }
        
        // Hiện progress
        showProgress();
        
        // Disable button
        const uploadBtn = form.querySelector('.upload-btn');
        uploadBtn.disabled = true;
        uploadBtn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Đang upload...';
        
        fetch('upload_no_refresh.php', {
            method: 'POST',
            body: formData
        })
        .then(response => response.json())
        .then(data => {
            hideProgress();
            uploadBtn.disabled = false;
            
            if (data.success) {
                // Thành công
                showAlert('success', data.message);
                updatePreview(form, data.fileName);
                updateStats();
                uploadedCount++;
            } else {
                // Thất bại
                showAlert('danger', data.message);
                uploadBtn.innerHTML = '<i class="fas fa-upload"></i> Chọn lại ảnh';
            }
        })
        .catch(error => {
            hideProgress();
            uploadBtn.disabled = false;
            uploadBtn.innerHTML = '<i class="fas fa-upload"></i> Chọn lại ảnh';
            showAlert('danger', 'Lỗi upload: ' + error.message);
        });
    }
    
    function updatePreview(form, fileName) {
        const itemId = form.dataset.id;
        const type = form.dataset.type;
        const imageType = form.dataset.imageType;
        
        let previewId;
        let imgSrc;
        
        if (type === 'category') {
            previewId = `preview-cat-${itemId}`;
            imgSrc = `uploads/categories/${fileName}`;
        } else if (type === 'product') {
            if (imageType === 'main') {
                previewId = `preview-prod-main-${itemId}`;
            } else {
                previewId = `preview-prod-album-${itemId}`;
            }
            imgSrc = `uploads/products/${fileName}`;
        }
        
        const previewDiv = document.getElementById(previewId);
        if (previewDiv) {
            previewDiv.innerHTML = `
                <img src="${imgSrc}" class="preview-img img-thumbnail mb-2">
                <p class="text-success"><i class="fas fa-check"></i> Upload thành công!</p>
            `;
            
            // Thêm hiệu ứng
            previewDiv.parentElement.classList.add('upload-success');
            setTimeout(() => {
                previewDiv.parentElement.classList.remove('upload-success');
            }, 3000);
        }
        
        // Update button
        const uploadBtn = form.querySelector('.upload-btn');
        uploadBtn.innerHTML = '<i class="fas fa-check"></i> Đã upload';
        uploadBtn.classList.remove('btn-primary', 'btn-info');
        uploadBtn.classList.add('btn-success');
    }
    
    function updateStats() {
        const progressPercent = Math.round((uploadedCount / totalIssues) * 100);
        document.getElementById('uploaded-count').textContent = uploadedCount;
        document.getElementById('progress-percent').textContent = progressPercent + '%';
    }
    
    function showAlert(type, message) {
        const alertHtml = `
            <div class="alert alert-${type} alert-dismissible fade show">
                ${message}
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
        `;
        document.getElementById('alertContainer').innerHTML = alertHtml;
        
        // Auto hide sau 3s
        setTimeout(() => {
            const alert = document.querySelector('.alert');
            if (alert) {
                alert.remove();
            }
        }, 3000);
    }
    
    function showProgress() {
        const progressBar = document.querySelector('.upload-progress');
        progressBar.style.display = 'block';
        progressBar.querySelector('.progress-bar').style.width = '100%';
    }
    
    function hideProgress() {
        setTimeout(() => {
            const progressBar = document.querySelector('.upload-progress');
            progressBar.style.display = 'none';
            progressBar.querySelector('.progress-bar').style.width = '0%';
        }, 500);
    }
});
</script>
</body>
</html>