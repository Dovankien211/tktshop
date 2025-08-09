<?php
/**
 * Upload album cho sản phẩm thiếu ảnh
 * File: /tktshop/upload_album_missing.php
 */

require_once 'config/database.php';

try {
    $pdo = new PDO("mysql:host=localhost;dbname=tktshop;charset=utf8mb4", "root", "");
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
} catch(PDOException $e) {
    die("Lỗi database: " . $e->getMessage());
}

// Xử lý upload AJAX
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['ajax_upload'])) {
    header('Content-Type: application/json');
    
    try {
        $productId = $_POST['product_id'] ?? '';
        $uploadCount = intval($_POST['upload_count'] ?? 1);
        
        if (!isset($_FILES['images'])) {
            throw new Exception('Vui lòng chọn ít nhất 1 ảnh!');
        }
        
        $files = $_FILES['images'];
        $uploadedFiles = [];
        $errors = [];
        
        // Xử lý từng file
        for ($i = 0; $i < count($files['name']); $i++) {
            if ($files['error'][$i] == 0) {
                $file = [
                    'name' => $files['name'][$i],
                    'type' => $files['type'][$i],
                    'tmp_name' => $files['tmp_name'][$i],
                    'size' => $files['size'][$i]
                ];
                
                $allowedTypes = ['image/jpeg', 'image/jpg', 'image/png', 'image/gif'];
                $maxSize = 2 * 1024 * 1024; // 2MB
                
                if (!in_array($file['type'], $allowedTypes)) {
                    $errors[] = "File {$file['name']}: Chỉ chấp nhận JPG, PNG, GIF";
                    continue;
                }
                
                if ($file['size'] > $maxSize) {
                    $errors[] = "File {$file['name']}: Quá lớn (max 2MB)";
                    continue;
                }
                
                // Tạo tên file unique
                $extension = pathinfo($file['name'], PATHINFO_EXTENSION);
                $fileName = time() . '_' . uniqid() . '_gallery_' . (count($uploadedFiles) + 1) . '.' . $extension;
                
                $uploadPath = 'uploads/products/' . $fileName;
                
                if (move_uploaded_file($file['tmp_name'], $uploadPath)) {
                    $uploadedFiles[] = $fileName;
                } else {
                    $errors[] = "File {$file['name']}: Lỗi upload";
                }
            }
        }
        
        // Cập nhật database nếu có file upload thành công
        if (!empty($uploadedFiles)) {
            // Lấy album hiện tại
            $stmt = $pdo->prepare("SELECT album_hinh_anh FROM san_pham_chinh WHERE id = ?");
            $stmt->execute([$productId]);
            $currentAlbum = $stmt->fetchColumn();
            
            $album = $currentAlbum ? json_decode($currentAlbum, true) : [];
            $album = array_merge($album, $uploadedFiles);
            
            // Cập nhật database
            $updateStmt = $pdo->prepare("UPDATE san_pham_chinh SET album_hinh_anh = ? WHERE id = ?");
            $updateStmt->execute([json_encode($album), $productId]);
        }
        
        echo json_encode([
            'success' => true,
            'message' => 'Upload thành công ' . count($uploadedFiles) . ' ảnh!',
            'uploaded_files' => $uploadedFiles,
            'errors' => $errors,
            'total_uploaded' => count($uploadedFiles)
        ]);
        
    } catch (Exception $e) {
        echo json_encode([
            'success' => false,
            'message' => $e->getMessage()
        ]);
    }
    exit;
}

// Lấy thông tin sản phẩm và album hiện tại
$products = $pdo->query("
    SELECT 
        sp.id,
        sp.ten_san_pham,
        sp.thuong_hieu,
        sp.hinh_anh_chinh,
        sp.album_hinh_anh,
        sp.san_pham_noi_bat,
        sp.san_pham_moi,
        dm.ten_danh_muc
    FROM san_pham_chinh sp
    LEFT JOIN danh_muc_giay dm ON sp.danh_muc_id = dm.id
    ORDER BY sp.id
")->fetchAll(PDO::FETCH_ASSOC);

$productStats = [];
foreach ($products as $product) {
    $albumImages = $product['album_hinh_anh'] ? json_decode($product['album_hinh_anh'], true) : [];
    $validAlbumCount = 0;
    
    if ($albumImages) {
        foreach ($albumImages as $img) {
            if (file_exists('uploads/products/' . $img)) $validAlbumCount++;
        }
    }
    
    $productStats[] = [
        'product' => $product,
        'album_count' => $validAlbumCount,
        'needs_more' => $validAlbumCount < 3, // Khuyến nghị ít nhất 3 ảnh
        'tags' => []
    ];
    
    // Thêm tags
    if ($product['san_pham_noi_bat']) $productStats[count($productStats)-1]['tags'][] = 'featured';
    if ($product['san_pham_moi']) $productStats[count($productStats)-1]['tags'][] = 'new';
}
?>
<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Upload Album - Sản Phẩm Thiếu Ảnh</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <style>
        .product-card { margin-bottom: 1.5rem; transition: all 0.3s ease; }
        .product-card:hover { transform: translateY(-2px); box-shadow: 0 4px 12px rgba(0,0,0,0.1); }
        .album-preview { display: flex; gap: 10px; flex-wrap: wrap; margin: 10px 0; }
        .album-img { width: 60px; height: 60px; object-fit: cover; border-radius: 4px; border: 2px solid #dee2e6; }
        .upload-area { 
            border: 2px dashed #dee2e6; 
            border-radius: 8px; 
            padding: 20px; 
            text-align: center; 
            transition: all 0.3s ease;
            cursor: pointer;
        }
        .upload-area:hover { border-color: #007bff; background: #f8f9fa; }
        .upload-area.dragover { border-color: #28a745; background: #e8f5e8; }
        .upload-progress { display: none; margin: 10px 0; }
        .tag-badge { margin: 2px; }
        .needs-more { border-left: 4px solid #ffc107; }
        .complete { border-left: 4px solid #28a745; }
        .stats-bar { background: #f8f9fa; padding: 15px; border-radius: 8px; margin: 20px 0; }
    </style>
</head>
<body>
<div class="container-fluid mt-4">
    <div class="row">
        <div class="col-12">
            <h1 class="text-center mb-4">
                <i class="fas fa-images"></i> Upload Album - Sản Phẩm Thiếu Ảnh
            </h1>
            
            <!-- Thống kê tổng quan -->
            <div class="stats-bar">
                <div class="row text-center">
                    <div class="col-md-3">
                        <h4><?= count(array_filter($productStats, fn($p) => $p['needs_more'])) ?></h4>
                        <small>Sản phẩm cần thêm ảnh</small>
                    </div>
                    <div class="col-md-3">
                        <h4><?= array_sum(array_column($productStats, 'album_count')) ?></h4>
                        <small>Tổng ảnh album hiện có</small>
                    </div>
                    <div class="col-md-3">
                        <h4><?= count(array_filter($productStats, fn($p) => in_array('new', $p['tags']))) ?></h4>
                        <small>Sản phẩm mới</small>
                    </div>
                    <div class="col-md-3">
                        <h4><?= count(array_filter($productStats, fn($p) => in_array('featured', $p['tags']))) ?></h4>
                        <small>Sản phẩm nổi bật</small>
                    </div>
                </div>
            </div>
            
            <!-- Alert container -->
            <div id="alertContainer"></div>
            
            <!-- Danh sách sản phẩm -->
            <div class="row">
                <?php foreach ($productStats as $stat): 
                    $product = $stat['product'];
                    $albumImages = $product['album_hinh_anh'] ? json_decode($product['album_hinh_anh'], true) : [];
                ?>
                <div class="col-md-6 col-lg-4">
                    <div class="card product-card <?= $stat['needs_more'] ? 'needs-more' : 'complete' ?>">
                        <div class="card-header">
                            <div class="d-flex justify-content-between align-items-start">
                                <div>
                                    <h6 class="mb-1"><?= htmlspecialchars($product['ten_san_pham']) ?></h6>
                                    <small class="text-muted"><?= htmlspecialchars($product['thuong_hieu']) ?></small>
                                </div>
                                <div>
                                    <?php foreach ($stat['tags'] as $tag): ?>
                                    <span class="badge bg-<?= $tag == 'new' ? 'primary' : 'warning' ?> tag-badge">
                                        <?= $tag == 'new' ? 'MỚI' : 'NỔI BẬT' ?>
                                    </span>
                                    <?php endforeach; ?>
                                </div>
                            </div>
                        </div>
                        <div class="card-body">
                            <!-- Ảnh chính -->
                            <div class="mb-3">
                                <strong>Ảnh chính:</strong>
                                <?php if (!empty($product['hinh_anh_chinh']) && file_exists('uploads/products/' . $product['hinh_anh_chinh'])): ?>
                                <br><img src="uploads/products/<?= $product['hinh_anh_chinh'] ?>" 
                                         class="album-img" alt="Main image">
                                <?php else: ?>
                                <span class="text-danger">❌ Thiếu</span>
                                <?php endif; ?>
                            </div>
                            
                            <!-- Album hiện tại -->
                            <div class="mb-3">
                                <strong>Album hiện tại (<?= $stat['album_count'] ?> ảnh):</strong>
                                <div class="album-preview" id="album-preview-<?= $product['id'] ?>">
                                    <?php if (!empty($albumImages)): ?>
                                        <?php foreach ($albumImages as $img): ?>
                                            <?php if (file_exists('uploads/products/' . $img)): ?>
                                            <img src="uploads/products/<?= $img ?>" class="album-img" alt="Album image">
                                            <?php endif; ?>
                                        <?php endforeach; ?>
                                    <?php else: ?>
                                    <small class="text-muted">Chưa có ảnh album</small>
                                    <?php endif; ?>
                                </div>
                            </div>
                            
                            <!-- Trạng thái -->
                            <div class="mb-3">
                                <?php if ($stat['album_count'] >= 3): ?>
                                <span class="badge bg-success">✅ Đủ ảnh (<?= $stat['album_count'] ?>/3+)</span>
                                <?php else: ?>
                                <span class="badge bg-warning text-dark">⚠️ Cần thêm (<?= $stat['album_count'] ?>/3)</span>
                                <?php endif; ?>
                            </div>
                            
                            <!-- Upload area -->
                            <div class="upload-area" onclick="document.getElementById('file-<?= $product['id'] ?>').click()">
                                <i class="fas fa-cloud-upload-alt fa-2x text-muted mb-2"></i>
                                <p class="mb-0">Click để chọn ảnh</p>
                                <small class="text-muted">Hoặc kéo thả file vào đây</small>
                                
                                <input type="file" 
                                       id="file-<?= $product['id'] ?>" 
                                       multiple 
                                       accept="image/*" 
                                       style="display: none;"
                                       data-product-id="<?= $product['id'] ?>"
                                       class="file-input">
                            </div>
                            
                            <!-- Progress bar -->
                            <div class="upload-progress" id="progress-<?= $product['id'] ?>">
                                <div class="progress">
                                    <div class="progress-bar progress-bar-striped progress-bar-animated" 
                                         role="progressbar" style="width: 0%"></div>
                                </div>
                            </div>
                            
                            <!-- Upload button -->
                            <button class="btn btn-primary w-100 mt-2 upload-btn" 
                                    data-product-id="<?= $product['id'] ?>" 
                                    style="display: none;">
                                <i class="fas fa-upload"></i> Upload <span class="file-count">0</span> ảnh
                            </button>
                        </div>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>
            
            <!-- Links -->
            <div class="text-center mt-4">
                <a href="debug_simple.php" class="btn btn-outline-primary me-2">
                    <i class="fas fa-search"></i> Debug lại
                </a>
                <a href="customer/" class="btn btn-outline-success me-2">
                    <i class="fas fa-eye"></i> Xem website
                </a>
                <a href="customer/products.php?new=1" class="btn btn-outline-info me-2">
                    <i class="fas fa-star"></i> Sản phẩm mới
                </a>
                <a href="customer/products.php?featured=1" class="btn btn-outline-warning">
                    <i class="fas fa-crown"></i> Sản phẩm nổi bật
                </a>
            </div>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
<script>
document.addEventListener('DOMContentLoaded', function() {
    
    // Xử lý chọn file
    document.querySelectorAll('.file-input').forEach(input => {
        input.addEventListener('change', function() {
            const productId = this.dataset.productId;
            const fileCount = this.files.length;
            const uploadBtn = document.querySelector(`.upload-btn[data-product-id="${productId}"]`);
            
            if (fileCount > 0) {
                uploadBtn.style.display = 'block';
                uploadBtn.querySelector('.file-count').textContent = fileCount;
            } else {
                uploadBtn.style.display = 'none';
            }
        });
    });
    
    // Xử lý upload
    document.querySelectorAll('.upload-btn').forEach(btn => {
        btn.addEventListener('click', function() {
            const productId = this.dataset.productId;
            const fileInput = document.getElementById(`file-${productId}`);
            
            if (fileInput.files.length === 0) {
                showAlert('warning', 'Vui lòng chọn ít nhất 1 ảnh!');
                return;
            }
            
            uploadFiles(productId, fileInput.files);
        });
    });
    
    // Drag & drop
    document.querySelectorAll('.upload-area').forEach(area => {
        area.addEventListener('dragover', function(e) {
            e.preventDefault();
            this.classList.add('dragover');
        });
        
        area.addEventListener('dragleave', function(e) {
            e.preventDefault();
            this.classList.remove('dragover');
        });
        
        area.addEventListener('drop', function(e) {
            e.preventDefault();
            this.classList.remove('dragover');
            
            const productId = this.querySelector('.file-input').dataset.productId;
            const files = e.dataTransfer.files;
            
            if (files.length > 0) {
                uploadFiles(productId, files);
            }
        });
    });
    
    function uploadFiles(productId, files) {
        const formData = new FormData();
        formData.append('ajax_upload', '1');
        formData.append('product_id', productId);
        
        for (let i = 0; i < files.length; i++) {
            formData.append('images[]', files[i]);
        }
        
        // Show progress
        const progressDiv = document.getElementById(`progress-${productId}`);
        const progressBar = progressDiv.querySelector('.progress-bar');
        const uploadBtn = document.querySelector(`.upload-btn[data-product-id="${productId}"]`);
        
        progressDiv.style.display = 'block';
        progressBar.style.width = '100%';
        uploadBtn.disabled = true;
        uploadBtn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Đang upload...';
        
        fetch('upload_album_missing.php', {
            method: 'POST',
            body: formData
        })
        .then(response => response.json())
        .then(data => {
            progressDiv.style.display = 'none';
            uploadBtn.disabled = false;
            uploadBtn.style.display = 'none';
            
            if (data.success) {
                showAlert('success', data.message);
                updateAlbumPreview(productId, data.uploaded_files);
                
                // Reset file input
                document.getElementById(`file-${productId}`).value = '';
                
                if (data.errors && data.errors.length > 0) {
                    showAlert('warning', 'Một số file có lỗi: ' + data.errors.join(', '));
                }
            } else {
                showAlert('danger', data.message);
                uploadBtn.innerHTML = '<i class="fas fa-upload"></i> Thử lại';
                uploadBtn.style.display = 'block';
            }
        })
        .catch(error => {
            progressDiv.style.display = 'none';
            uploadBtn.disabled = false;
            uploadBtn.innerHTML = '<i class="fas fa-upload"></i> Thử lại';
            showAlert('danger', 'Lỗi upload: ' + error.message);
        });
    }
    
    function updateAlbumPreview(productId, newFiles) {
        const previewDiv = document.getElementById(`album-preview-${productId}`);
        
        newFiles.forEach(fileName => {
            const img = document.createElement('img');
            img.src = `uploads/products/${fileName}`;
            img.className = 'album-img';
            img.alt = 'New album image';
            previewDiv.appendChild(img);
        });
        
        // Update badge
        const currentCount = previewDiv.querySelectorAll('.album-img').length;
        const card = document.querySelector(`.product-card .upload-btn[data-product-id="${productId}"]`).closest('.product-card');
        const badge = card.querySelector('.badge');
        
        if (currentCount >= 3) {
            badge.className = 'badge bg-success';
            badge.innerHTML = `✅ Đủ ảnh (${currentCount}/3+)`;
            card.classList.remove('needs-more');
            card.classList.add('complete');
        } else {
            badge.innerHTML = `⚠️ Cần thêm (${currentCount}/3)`;
        }
    }
    
    function showAlert(type, message) {
        const alertHtml = `
            <div class="alert alert-${type} alert-dismissible fade show">
                ${message}
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
        `;
        document.getElementById('alertContainer').innerHTML = alertHtml;
        
        setTimeout(() => {
            const alert = document.querySelector('.alert');
            if (alert) alert.remove();
        }, 5000);
    }
});
</script>
</body>
</html>