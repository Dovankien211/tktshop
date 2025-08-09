<?php
/**
 * Sửa chữa album ảnh thiếu - TKTShop
 * File: /tktshop/fix_album_images.php
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
$actions = [];

// Xử lý các hành động
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $action = $_POST['action'] ?? '';
    
    try {
        if ($action == 'clean_albums') {
            // Dọn dẹp album - xóa những file không tồn tại
            $stmt = $pdo->query("SELECT id, ten_san_pham, album_hinh_anh FROM san_pham_chinh WHERE album_hinh_anh IS NOT NULL");
            $products = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            $totalCleaned = 0;
            
            foreach ($products as $product) {
                $albumImages = json_decode($product['album_hinh_anh'], true);
                if (!$albumImages) continue;
                
                $validImages = [];
                foreach ($albumImages as $img) {
                    if (file_exists('uploads/products/' . $img)) {
                        $validImages[] = $img;
                    } else {
                        $totalCleaned++;
                    }
                }
                
                // Cập nhật lại album chỉ với những ảnh tồn tại
                $newAlbum = empty($validImages) ? null : json_encode($validImages);
                $updateStmt = $pdo->prepare("UPDATE san_pham_chinh SET album_hinh_anh = ? WHERE id = ?");
                $updateStmt->execute([$newAlbum, $product['id']]);
            }
            
            $message = "Đã dọn dẹp thành công! Xóa {$totalCleaned} tham chiếu file không tồn tại.";
            $messageType = 'success';
            
        } elseif ($action == 'duplicate_main') {
            // Tạo album từ ảnh chính (duplicate 3 lần)
            $stmt = $pdo->query("
                SELECT id, ten_san_pham, hinh_anh_chinh, album_hinh_anh 
                FROM san_pham_chinh 
                WHERE hinh_anh_chinh IS NOT NULL
            ");
            $products = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            $totalCreated = 0;
            
            foreach ($products as $product) {
                $mainImage = $product['hinh_anh_chinh'];
                $mainPath = 'uploads/products/' . $mainImage;
                
                if (!file_exists($mainPath)) continue;
                
                // Tạo 3 bản copy cho album
                $albumImages = [];
                $pathInfo = pathinfo($mainImage);
                
                for ($i = 1; $i <= 3; $i++) {
                    $albumName = $pathInfo['filename'] . "_gallery_{$i}." . $pathInfo['extension'];
                    $albumPath = 'uploads/products/' . $albumName;
                    
                    // Copy file
                    if (copy($mainPath, $albumPath)) {
                        $albumImages[] = $albumName;
                        $totalCreated++;
                    }
                }
                
                // Cập nhật album
                if (!empty($albumImages)) {
                    $currentAlbum = $product['album_hinh_anh'] ? json_decode($product['album_hinh_anh'], true) : [];
                    $newAlbum = array_merge($currentAlbum, $albumImages);
                    
                    $updateStmt = $pdo->prepare("UPDATE san_pham_chinh SET album_hinh_anh = ? WHERE id = ?");
                    $updateStmt->execute([json_encode($newAlbum), $product['id']]);
                }
            }
            
            $message = "Đã tạo thành công {$totalCreated} ảnh album từ ảnh chính!";
            $messageType = 'success';
            
        } elseif ($action == 'create_placeholder') {
            // Tạo ảnh placeholder cho album
            $products = $pdo->query("SELECT id, ten_san_pham FROM san_pham_chinh")->fetchAll(PDO::FETCH_ASSOC);
            $totalCreated = 0;
            
            foreach ($products as $product) {
                // Tạo ảnh placeholder đơn giản
                $placeholderImages = [];
                
                for ($i = 1; $i <= 3; $i++) {
                    $fileName = "placeholder_product_{$product['id']}_gallery_{$i}.png";
                    $filePath = 'uploads/products/' . $fileName;
                    
                    // Tạo ảnh placeholder 400x400px
                    if (createPlaceholderImage($filePath, $product['ten_san_pham'], $i)) {
                        $placeholderImages[] = $fileName;
                        $totalCreated++;
                    }
                }
                
                // Cập nhật album
                if (!empty($placeholderImages)) {
                    $updateStmt = $pdo->prepare("UPDATE san_pham_chinh SET album_hinh_anh = ? WHERE id = ?");
                    $updateStmt->execute([json_encode($placeholderImages), $product['id']]);
                }
            }
            
            $message = "Đã tạo thành công {$totalCreated} ảnh placeholder!";
            $messageType = 'success';
        }
        
    } catch (Exception $e) {
        $message = "Lỗi: " . $e->getMessage();
        $messageType = 'danger';
    }
}

// Hàm tạo ảnh placeholder
function createPlaceholderImage($filePath, $productName, $index) {
    if (!extension_loaded('gd')) {
        return false;
    }
    
    $width = 400;
    $height = 400;
    
    // Tạo canvas
    $image = imagecreate($width, $height);
    
    // Màu nền gradient
    $colors = [
        ['r' => 240, 'g' => 240, 'b' => 240], // Light gray
        ['r' => 220, 'g' => 220, 'b' => 250], // Light blue
        ['r' => 250, 'g' => 240, 'b' => 220], // Light orange
    ];
    
    $color = $colors[($index - 1) % 3];
    $bgColor = imagecolorallocate($image, $color['r'], $color['g'], $color['b']);
    $textColor = imagecolorallocate($image, 100, 100, 100);
    $borderColor = imagecolorallocate($image, 200, 200, 200);
    
    // Vẽ border
    imagerectangle($image, 0, 0, $width-1, $height-1, $borderColor);
    imagerectangle($image, 10, 10, $width-11, $height-11, $borderColor);
    
    // Vẽ text
    $text1 = strtoupper(substr($productName, 0, 20));
    $text2 = "Gallery Image #{$index}";
    $text3 = date('Y-m-d');
    
    // Centered text
    imagestring($image, 4, $width/2 - strlen($text1)*5, $height/2 - 40, $text1, $textColor);
    imagestring($image, 3, $width/2 - strlen($text2)*3.5, $height/2 - 10, $text2, $textColor);
    imagestring($image, 2, $width/2 - strlen($text3)*3, $height/2 + 20, $text3, $textColor);
    
    // Vẽ icon camera đơn giản
    $iconColor = imagecolorallocate($image, 150, 150, 150);
    imagefilledrectangle($image, $width/2-15, $height/2-60, $width/2+15, $height/2-45, $iconColor);
    imagefilledrectangle($image, $width/2-5, $height/2-70, $width/2+5, $height/2-60, $iconColor);
    
    // Save ảnh
    $result = imagepng($image, $filePath);
    imagedestroy($image);
    
    return $result;
}

// Lấy thông tin hiện tại
$stmt = $pdo->query("
    SELECT id, ten_san_pham, hinh_anh_chinh, album_hinh_anh 
    FROM san_pham_chinh 
    ORDER BY id
");
$products = $stmt->fetchAll(PDO::FETCH_ASSOC);

$totalMissingFiles = 0;
$productIssues = [];

foreach ($products as $product) {
    $issues = [];
    $albumImages = $product['album_hinh_anh'] ? json_decode($product['album_hinh_anh'], true) : [];
    $missingAlbumFiles = 0;
    
    if (!empty($albumImages)) {
        foreach ($albumImages as $img) {
            if (!file_exists('uploads/products/' . $img)) {
                $missingAlbumFiles++;
                $totalMissingFiles++;
            }
        }
        
        if ($missingAlbumFiles > 0) {
            $issues[] = "{$missingAlbumFiles} file album không tồn tại";
        }
    } else {
        $issues[] = "Chưa có album ảnh";
    }
    
    if (!empty($issues)) {
        $productIssues[] = [
            'product' => $product,
            'issues' => $issues,
            'album_count' => count($albumImages),
            'missing_count' => $missingAlbumFiles
        ];
    }
}
?>
<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Sửa Chữa Album Ảnh - TKTShop</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <style>
        .action-card { margin-bottom: 1.5rem; transition: all 0.3s ease; }
        .action-card:hover { transform: translateY(-2px); box-shadow: 0 4px 8px rgba(0,0,0,0.1); }
        .preview-img { max-width: 60px; max-height: 60px; object-fit: cover; margin: 2px; }
        .missing { background-color: #fff3cd; }
        .issue-badge { margin: 2px; }
    </style>
</head>
<body>
<div class="container-fluid mt-4">
    <div class="row">
        <div class="col-12">
            <h1 class="text-center mb-4">
                <i class="fas fa-tools"></i> Sửa Chữa Album Ảnh
            </h1>
            
            <?php if ($message): ?>
            <div class="alert alert-<?= $messageType ?> alert-dismissible fade show">
                <?= $message ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
            <?php endif; ?>
            
            <!-- Thống kê nhanh -->
            <div class="row mb-4">
                <div class="col-md-3">
                    <div class="card bg-danger text-white">
                        <div class="card-body text-center">
                            <h3><?= $totalMissingFiles ?></h3>
                            <p>File ảnh thiếu</p>
                        </div>
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="card bg-warning text-dark">
                        <div class="card-body text-center">
                            <h3><?= count($productIssues) ?></h3>
                            <p>Sản phẩm có vấn đề</p>
                        </div>
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="card bg-info text-white">
                        <div class="card-body text-center">
                            <h3><?= count($products) ?></h3>
                            <p>Tổng sản phẩm</p>
                        </div>
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="card bg-success text-white">
                        <div class="card-body text-center">
                            <h3><?= extension_loaded('gd') ? 'ON' : 'OFF' ?></h3>
                            <p>GD Extension</p>
                        </div>
                    </div>
                </div>
            </div>
            
            <!-- Các hành động khắc phục -->
            <div class="row">
                <div class="col-md-4">
                    <div class="card action-card">
                        <div class="card-header bg-warning text-dark">
                            <h5><i class="fas fa-broom"></i> Dọn Dẹp Album</h5>
                        </div>
                        <div class="card-body">
                            <p>Xóa các tham chiếu đến file ảnh không tồn tại trong database.</p>
                            <ul>
                                <li>Quét tất cả album ảnh</li>
                                <li>Loại bỏ file không tồn tại</li>
                                <li>Cập nhật database</li>
                            </ul>
                            <form method="POST">
                                <input type="hidden" name="action" value="clean_albums">
                                <button type="submit" class="btn btn-warning w-100" 
                                        onclick="return confirm('Bạn có chắc muốn dọn dẹp album?')">
                                    <i class="fas fa-broom"></i> Dọn Dẹp Ngay
                                </button>
                            </form>
                        </div>
                    </div>
                </div>
                
                <div class="col-md-4">
                    <div class="card action-card">
                        <div class="card-header bg-info text-white">
                            <h5><i class="fas fa-copy"></i> Tạo Album Từ Ảnh Chính</h5>
                        </div>
                        <div class="card-body">
                            <p>Sao chép ảnh chính thành 3 ảnh album cho mỗi sản phẩm.</p>
                            <ul>
                                <li>Copy ảnh chính → gallery_1,2,3</li>
                                <li>Tự động đặt tên file</li>
                                <li>Cập nhật album database</li>
                            </ul>
                            <form method="POST">
                                <input type="hidden" name="action" value="duplicate_main">
                                <button type="submit" class="btn btn-info w-100"
                                        onclick="return confirm('Tạo album từ ảnh chính?')">
                                    <i class="fas fa-copy"></i> Tạo Album
                                </button>
                            </form>
                        </div>
                    </div>
                </div>
                
                <div class="col-md-4">
                    <div class="card action-card">
                        <div class="card-header bg-secondary text-white">
                            <h5><i class="fas fa-image"></i> Tạo Ảnh Placeholder</h5>
                        </div>
                        <div class="card-body">
                            <p>Tạo ảnh placeholder tạm thời cho album (cần GD extension).</p>
                            <ul>
                                <li>Tạo ảnh 400x400px</li>
                                <li>Hiển thị tên sản phẩm</li>
                                <li>3 ảnh/sản phẩm</li>
                            </ul>
                            <form method="POST">
                                <input type="hidden" name="action" value="create_placeholder">
                                <button type="submit" class="btn btn-secondary w-100"
                                        onclick="return confirm('Tạo ảnh placeholder?')"
                                        <?= !extension_loaded('gd') ? 'disabled title="Cần GD extension"' : '' ?>>
                                    <i class="fas fa-image"></i> Tạo Placeholder
                                </button>
                            </form>
                        </div>
                    </div>
                </div>
            </div>
            
            <!-- Chi tiết sản phẩm có vấn đề -->
            <?php if (!empty($productIssues)): ?>
            <div class="card mt-4">
                <div class="card-header bg-danger text-white">
                    <h4><i class="fas fa-exclamation-triangle"></i> Chi Tiết Sản Phẩm Có Vấn đề</h4>
                </div>
                <div class="card-body">
                    <div class="table-responsive">
                        <table class="table table-bordered">
                            <thead>
                                <tr>
                                    <th>ID</th>
                                    <th>Sản phẩm</th>
                                    <th>Ảnh chính</th>
                                    <th>Album hiện tại</th>
                                    <th>Vấn đề</th>
                                    <th>Hành động</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($productIssues as $issue): 
                                    $product = $issue['product'];
                                    $albumImages = $product['album_hinh_anh'] ? json_decode($product['album_hinh_anh'], true) : [];
                                ?>
                                <tr class="<?= $issue['missing_count'] > 0 ? 'missing' : '' ?>">
                                    <td><?= $product['id'] ?></td>
                                    <td>
                                        <strong><?= htmlspecialchars($product['ten_san_pham']) ?></strong>
                                    </td>
                                    <td>
                                        <?php if (!empty($product['hinh_anh_chinh']) && file_exists('uploads/products/' . $product['hinh_anh_chinh'])): ?>
                                        <img src="uploads/products/<?= $product['hinh_anh_chinh'] ?>" class="preview-img img-thumbnail">
                                        <?php else: ?>
                                        <span class="text-muted">Không có</span>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <?php if (!empty($albumImages)): ?>
                                        <div class="d-flex flex-wrap">
                                            <?php foreach ($albumImages as $img): ?>
                                                <?php if (file_exists('uploads/products/' . $img)): ?>
                                                <img src="uploads/products/<?= $img ?>" class="preview-img img-thumbnail">
                                                <?php else: ?>
                                                <div class="preview-img bg-danger text-white d-flex align-items-center justify-content-center">
                                                    <i class="fas fa-times"></i>
                                                </div>
                                                <?php endif; ?>
                                            <?php endforeach; ?>
                                        </div>
                                        <small class="text-muted"><?= count($albumImages) ?> ảnh trong album</small>
                                        <?php else: ?>
                                        <span class="text-muted">Album trống</span>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <?php foreach ($issue['issues'] as $problemText): ?>
                                        <span class="badge bg-danger issue-badge"><?= $problemText ?></span>
                                        <?php endforeach; ?>
                                    </td>
                                    <td>
                                        <a href="upload_no_refresh.php" class="btn btn-primary btn-sm">
                                            <i class="fas fa-upload"></i> Upload
                                        </a>
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
            <?php endif; ?>
            
            <!-- Navigation -->
            <div class="text-center mt-4">
                <a href="debug_simple.php" class="btn btn-outline-primary me-2">
                    <i class="fas fa-search"></i> Debug lại
                </a>
                <a href="upload_no_refresh.php" class="btn btn-outline-success me-2">
                    <i class="fas fa-upload"></i> Upload ảnh
                </a>
                <a href="customer/" class="btn btn-outline-info">
                    <i class="fas fa-eye"></i> Xem website
                </a>
            </div>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>