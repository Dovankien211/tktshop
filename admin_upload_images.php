<?php
/**
 * Giao diện upload ảnh cho TKTShop
 * Đặt file này tại: /tktshop/admin_upload_images.php
 */

require_once 'config/database.php';

// Khởi tạo kết nối database
try {
    $pdo = new PDO("mysql:host=localhost;dbname=tktshop;charset=utf8mb4", "root", "");
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
} catch(PDOException $e) {
    die("Lỗi kết nối database: " . $e->getMessage());
}

$message = '';
$messageType = '';

// Xử lý upload ảnh
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $uploadType = $_POST['upload_type'] ?? '';
    $itemId = $_POST['item_id'] ?? '';
    $imageType = $_POST['image_type'] ?? '';
    
    if (isset($_FILES['image']) && $_FILES['image']['error'] == 0) {
        $file = $_FILES['image'];
        $allowedTypes = ['image/jpeg', 'image/jpg', 'image/png', 'image/gif'];
        $maxSize = 2 * 1024 * 1024; // 2MB
        
        // Kiểm tra file
        if (!in_array($file['type'], $allowedTypes)) {
            $message = "Chỉ chấp nhận file JPG, PNG, GIF!";
            $messageType = 'danger';
        } elseif ($file['size'] > $maxSize) {
            $message = "File quá lớn! Tối đa 2MB.";
            $messageType = 'danger';
        } else {
            // Tạo tên file unique
            $extension = pathinfo($file['name'], PATHINFO_EXTENSION);
            $fileName = time() . '_' . uniqid() . '.' . $extension;
            
            // Xác định thư mục upload
            if ($uploadType == 'category') {
                $uploadDir = 'uploads/categories/';
                $dbField = 'hinh_anh';
                $table = 'danh_muc_giay';
            } elseif ($uploadType == 'product') {
                $uploadDir = 'uploads/products/';
                if ($imageType == 'main') {
                    $dbField = 'hinh_anh_chinh';
                } else {
                    $dbField = 'album_hinh_anh';
                }
                $table = 'san_pham_chinh';
            }
            
            $uploadPath = $uploadDir . $fileName;
            
            // Upload file
            if (move_uploaded_file($file['tmp_name'], $uploadPath)) {
                try {
                    if ($uploadType == 'category') {
                        // Cập nhật ảnh danh mục
                        $stmt = $pdo->prepare("UPDATE {$table} SET {$dbField} = ? WHERE id = ?");
                        $stmt->execute([$fileName, $itemId]);
                    } elseif ($uploadType == 'product') {
                        if ($imageType == 'main') {
                            // Cập nhật ảnh chính sản phẩm
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
                    }
                    
                    $message = "Upload ảnh thành công!";
                    $messageType = 'success';
                } catch (Exception $e) {
                    $message = "Lỗi cập nhật database: " . $e->getMessage();
                    $messageType = 'danger';
                    unlink($uploadPath); // Xóa file đã upload
                }
            } else {
                $message = "Lỗi upload file!";
                $messageType = 'danger';
            }
        }
    } else {
        $message = "Vui lòng chọn file ảnh!";
        $messageType = 'danger';
    }
}

// Lấy danh sách danh mục thiếu ảnh
$missingCategories = $pdo->query("
    SELECT id, ten_danh_muc, hinh_anh 
    FROM danh_muc_giay 
    WHERE hinh_anh IS NULL OR hinh_anh = ''
    ORDER BY id
")->fetchAll(PDO::FETCH_ASSOC);

// Lấy danh sách sản phẩm thiếu ảnh
$missingProducts = $pdo->query("
    SELECT id, ten_san_pham, thuong_hieu, hinh_anh_chinh, album_hinh_anh
    FROM san_pham_chinh 
    ORDER BY id
")->fetchAll(PDO::FETCH_ASSOC);

?>
<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Upload Ảnh - TKTShop Admin</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <style>
        .upload-section { margin-bottom: 2rem; }
        .item-card { margin-bottom: 1rem; }
        .preview-img { max-width: 100px; max-height: 100px; object-fit: cover; }
        .missing-img { background: #f8f9fa; border: 2px dashed #dee2e6; padding: 2rem; text-align: center; }
        .upload-form { display: none; }
        .upload-form.show { display: block; }
    </style>
</head>
<body>
<div class="container-fluid mt-4">
    <div class="row">
        <div class="col-12">
            <h1 class="text-center mb-4">
                <i class="fas fa-upload"></i> Upload Ảnh - TKTShop Admin
            </h1>
            
            <?php if ($message): ?>
            <div class="alert alert-<?= $messageType ?> alert-dismissible fade show">
                <?= $message ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
            <?php endif; ?>

            <!-- Navigation Tabs -->
            <ul class="nav nav-tabs mb-4" role="tablist">
                <li class="nav-item">
                    <a class="nav-link active" data-bs-toggle="tab" href="#categories">
                        <i class="fas fa-folder"></i> Danh mục (<?= count($missingCategories) ?> thiếu ảnh)
                    </a>
                </li>
                <li class="nav-item">
                    <a class="nav-link" data-bs-toggle="tab" href="#products">
                        <i class="fas fa-shoe-prints"></i> Sản phẩm (4 sản phẩm)
                    </a>
                </li>
                <li class="nav-item">
                    <a class="nav-link" data-bs-toggle="tab" href="#bulk-upload">
                        <i class="fas fa-upload"></i> Upload hàng loạt
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
                                <div class="col-md-6 col-lg-4 item-card">
                                    <div class="card">
                                        <div class="card-header">
                                            <strong><?= htmlspecialchars($category['ten_danh_muc']) ?></strong>
                                            <small class="text-muted">(ID: <?= $category['id'] ?>)</small>
                                        </div>
                                        <div class="card-body">
                                            <div class="missing-img mb-3">
                                                <i class="fas fa-image fa-3x text-muted"></i>
                                                <p class="mt-2 text-muted">Chưa có ảnh</p>
                                            </div>
                                            
                                            <button class="btn btn-primary btn-sm w-100" 
                                                    onclick="showUploadForm('cat_<?= $category['id'] ?>')">
                                                <i class="fas fa-upload"></i> Upload ảnh
                                            </button>
                                            
                                            <div id="cat_<?= $category['id'] ?>" class="upload-form mt-3">
                                                <form method="POST" enctype="multipart/form-data">
                                                    <input type="hidden" name="upload_type" value="category">
                                                    <input type="hidden" name="item_id" value="<?= $category['id'] ?>">
                                                    
                                                    <div class="mb-3">
                                                        <input type="file" class="form-control" name="image" 
                                                               accept="image/jpeg,image/jpg,image/png,image/gif" required>
                                                    </div>
                                                    
                                                    <div class="d-grid gap-2">
                                                        <button type="submit" class="btn btn-success btn-sm">
                                                            <i class="fas fa-check"></i> Upload
                                                        </button>
                                                        <button type="button" class="btn btn-secondary btn-sm" 
                                                                onclick="hideUploadForm('cat_<?= $category['id'] ?>')">
                                                            <i class="fas fa-times"></i> Hủy
                                                        </button>
                                                    </div>
                                                </form>
                                            </div>
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
                            <?php foreach ($missingProducts as $product): 
                                $hasMainImage = !empty($product['hinh_anh_chinh']) && 
                                               file_exists('uploads/products/' . $product['hinh_anh_chinh']);
                                $albumImages = $product['album_hinh_anh'] ? json_decode($product['album_hinh_anh'], true) : [];
                                $validAlbumImages = 0;
                                if ($albumImages) {
                                    foreach ($albumImages as $img) {
                                        if (file_exists('uploads/products/' . $img)) $validAlbumImages++;
                                    }
                                }
                            ?>
                            <div class="card mb-3">
                                <div class="card-header">
                                    <div class="row align-items-center">
                                        <div class="col">
                                            <h5 class="mb-0"><?= htmlspecialchars($product['ten_san_pham']) ?></h5>
                                            <small class="text-muted">
                                                <?= htmlspecialchars($product['thuong_hieu']) ?> (ID: <?= $product['id'] ?>)
                                            </small>
                                        </div>
                                        <div class="col-auto">
                                            <?php if (!$hasMainImage): ?>
                                            <span class="badge bg-danger">Thiếu ảnh chính</span>
                                            <?php endif; ?>
                                            
                                            <?php if ($validAlbumImages == 0): ?>
                                            <span class="badge bg-warning">Thiếu album</span>
                                            <?php endif; ?>
                                        </div>
                                    </div>
                                </div>
                                <div class="card-body">
                                    <div class="row">
                                        <!-- Ảnh chính -->
                                        <div class="col-md-6">
                                            <h6>Ảnh chính:</h6>
                                            <?php if ($hasMainImage): ?>
                                            <img src="uploads/products/<?= $product['hinh_anh_chinh'] ?>" 
                                                 class="preview-img img-thumbnail">
                                            <p class="text-success mt-2">
                                                <i class="fas fa-check"></i> Có ảnh chính
                                            </p>
                                            <?php else: ?>
                                            <div class="missing-img">
                                                <i class="fas fa-image fa-2x text-muted"></i>
                                                <p class="mt-2 text-muted">Thiếu ảnh chính</p>
                                            </div>
                                            
                                            <button class="btn btn-primary btn-sm" 
                                                    onclick="showUploadForm('prod_main_<?= $product['id'] ?>')">
                                                <i class="fas fa-upload"></i> Upload ảnh chính
                                            </button>
                                            
                                            <div id="prod_main_<?= $product['id'] ?>" class="upload-form mt-3">
                                                <form method="POST" enctype="multipart/form-data">
                                                    <input type="hidden" name="upload_type" value="product">
                                                    <input type="hidden" name="image_type" value="main">
                                                    <input type="hidden" name="item_id" value="<?= $product['id'] ?>">
                                                    
                                                    <div class="mb-3">
                                                        <input type="file" class="form-control" name="image" 
                                                               accept="image/jpeg,image/jpg,image/png,image/gif" required>
                                                    </div>
                                                    
                                                    <div class="d-grid gap-2">
                                                        <button type="submit" class="btn btn-success btn-sm">
                                                            <i class="fas fa-check"></i> Upload
                                                        </button>
                                                        <button type="button" class="btn btn-secondary btn-sm" 
                                                                onclick="hideUploadForm('prod_main_<?= $product['id'] ?>')">
                                                            <i class="fas fa-times"></i> Hủy
                                                        </button>
                                                    </div>
                                                </form>
                                            </div>
                                            <?php endif; ?>
                                        </div>
                                        
                                        <!-- Album ảnh -->
                                        <div class="col-md-6">
                                            <h6>Album ảnh:</h6>
                                            <?php if ($validAlbumImages > 0): ?>
                                            <div class="d-flex flex-wrap gap-2">
                                                <?php foreach ($albumImages as $img): 
                                                    if (file_exists('uploads/products/' . $img)): ?>
                                                <img src="uploads/products/<?= $img ?>" 
                                                     class="preview-img img-thumbnail">
                                                <?php endif; endforeach; ?>
                                            </div>
                                            <p class="text-success mt-2">
                                                <i class="fas fa-check"></i> Có <?= $validAlbumImages ?> ảnh
                                            </p>
                                            <?php else: ?>
                                            <div class="missing-img">
                                                <i class="fas fa-images fa-2x text-muted"></i>
                                                <p class="mt-2 text-muted">Thiếu album ảnh</p>
                                            </div>
                                            <?php endif; ?>
                                            
                                            <button class="btn btn-info btn-sm" 
                                                    onclick="showUploadForm('prod_album_<?= $product['id'] ?>')">
                                                <i class="fas fa-plus"></i> Thêm ảnh vào album
                                            </button>
                                            
                                            <div id="prod_album_<?= $product['id'] ?>" class="upload-form mt-3">
                                                <form method="POST" enctype="multipart/form-data">
                                                    <input type="hidden" name="upload_type" value="product">
                                                    <input type="hidden" name="image_type" value="album">
                                                    <input type="hidden" name="item_id" value="<?= $product['id'] ?>">
                                                    
                                                    <div class="mb-3">
                                                        <input type="file" class="form-control" name="image" 
                                                               accept="image/jpeg,image/jpg,image/png,image/gif" required>
                                                    </div>
                                                    
                                                    <div class="d-grid gap-2">
                                                        <button type="submit" class="btn btn-success btn-sm">
                                                            <i class="fas fa-check"></i> Thêm vào album
                                                        </button>
                                                        <button type="button" class="btn btn-secondary btn-sm" 
                                                                onclick="hideUploadForm('prod_album_<?= $product['id'] ?>')">
                                                            <i class="fas fa-times"></i> Hủy
                                                        </button>
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

                <!-- TAB BULK UPLOAD -->
                <div class="tab-pane fade" id="bulk-upload">
                    <div class="card">
                        <div class="card-header bg-warning text-dark">
                            <h4><i class="fas fa-upload"></i> Upload hàng loạt</h4>
                        </div>
                        <div class="card-body">
                            <div class="alert alert-info">
                                <h5><i class="fas fa-info-circle"></i> Hướng dẫn:</h5>
                                <ul>
                                    <li>Đặt tên file theo format: <code>category_[ID].jpg</code> cho danh mục</li>
                                    <li>Đặt tên file theo format: <code>product_[ID]_main.jpg</code> cho ảnh chính sản phẩm</li>
                                    <li>Đặt tên file theo format: <code>product_[ID]_gallery_[NUMBER].jpg</code> cho album</li>
                                    <li>Ví dụ: <code>category_3.jpg</code>, <code>product_2_main.jpg</code>, <code>product_2_gallery_1.jpg</code></li>
                                </ul>
                            </div>
                            
                            <form id="bulkUploadForm" method="POST" enctype="multipart/form-data">
                                <input type="hidden" name="upload_type" value="bulk">
                                
                                <div class="mb-3">
                                    <label class="form-label">Chọn nhiều file:</label>
                                    <input type="file" class="form-control" name="images[]" 
                                           multiple accept="image/jpeg,image/jpg,image/png,image/gif">
                                </div>
                                
                                <button type="submit" class="btn btn-warning btn-lg">
                                    <i class="fas fa-upload"></i> Upload tất cả
                                </button>
                            </form>
                        </div>
                    </div>
                </div>
            </div>
            
            <!-- Actions -->
            <div class="row mt-4">
                <div class="col-12 text-center">
                    <a href="debug_missing_images.php" class="btn btn-outline-primary">
                        <i class="fas fa-search"></i> Kiểm tra lại
                    </a>
                    <a href="customer/" class="btn btn-outline-success">
                        <i class="fas fa-eye"></i> Xem website
                    </a>
                </div>
            </div>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
<script>
function showUploadForm(id) {
    document.getElementById(id).classList.add('show');
}

function hideUploadForm(id) {
    document.getElementById(id).classList.remove('show');
}

// Auto refresh page after successful upload
<?php if ($messageType == 'success'): ?>
setTimeout(function() {
    location.reload();
}, 2000);
<?php endif; ?>
</script>
</body>
</html>