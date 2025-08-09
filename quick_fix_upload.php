<?php
/**
 * Quick Fix Upload - Sửa ngay lập tức
 * File: /tktshop/quick_fix_upload.php
 */

require_once 'config/database.php';

try {
    $pdo = new PDO("mysql:host=localhost;dbname=tktshop;charset=utf8mb4", "root", "");
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
} catch(PDOException $e) {
    die("Lỗi database: " . $e->getMessage());
}

$message = '';
$messageType = '';

// Xử lý upload
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $action = $_POST['action'] ?? '';
    
    if ($action == 'upload_single') {
        // Upload cho 1 sản phẩm cụ thể
        $productId = $_POST['product_id'] ?? '';
        $imageType = $_POST['image_type'] ?? 'album'; // main hoặc album
        
        if (!empty($_FILES['image']['name'])) {
            $file = $_FILES['image'];
            
            if ($file['error'] == 0) {
                $allowedTypes = ['image/jpeg', 'image/jpg', 'image/png', 'image/gif'];
                
                if (in_array($file['type'], $allowedTypes) && $file['size'] <= 5*1024*1024) {
                    $extension = pathinfo($file['name'], PATHINFO_EXTENSION);
                    $fileName = time() . '_' . uniqid() . '.' . $extension;
                    $uploadPath = 'uploads/products/' . $fileName;
                    
                    if (move_uploaded_file($file['tmp_name'], $uploadPath)) {
                        try {
                            if ($imageType == 'main') {
                                // Cập nhật ảnh chính
                                $stmt = $pdo->prepare("UPDATE san_pham_chinh SET hinh_anh_chinh = ? WHERE id = ?");
                                $stmt->execute([$fileName, $productId]);
                                $message = "✅ Upload ảnh chính thành công!";
                            } else {
                                // Thêm vào album
                                $stmt = $pdo->prepare("SELECT album_hinh_anh FROM san_pham_chinh WHERE id = ?");
                                $stmt->execute([$productId]);
                                $currentAlbum = $stmt->fetchColumn();
                                
                                $album = $currentAlbum ? json_decode($currentAlbum, true) : [];
                                $album[] = $fileName;
                                
                                $updateStmt = $pdo->prepare("UPDATE san_pham_chinh SET album_hinh_anh = ? WHERE id = ?");
                                $updateStmt->execute([json_encode($album), $productId]);
                                $message = "✅ Thêm ảnh album thành công!";
                            }
                            $messageType = 'success';
                        } catch (Exception $e) {
                            $message = "❌ Lỗi database: " . $e->getMessage();
                            $messageType = 'danger';
                            unlink($uploadPath);
                        }
                    } else {
                        $message = "❌ Lỗi upload file!";
                        $messageType = 'danger';
                    }
                } else {
                    $message = "❌ File không hợp lệ! (Chỉ chấp nhận JPG, PNG, GIF dưới 5MB)";
                    $messageType = 'danger';
                }
            } else {
                $message = "❌ Lỗi upload: " . $file['error'];
                $messageType = 'danger';
            }
        } else {
            $message = "❌ Vui lòng chọn file!";
            $messageType = 'danger';
        }
    }
    
    elseif ($action == 'quick_fix_all') {
        // Fix nhanh: copy ảnh chính thành album cho tất cả
        try {
            $products = $pdo->query("SELECT id, ten_san_pham, hinh_anh_chinh FROM san_pham_chinh WHERE hinh_anh_chinh IS NOT NULL")->fetchAll(PDO::FETCH_ASSOC);
            $totalFixed = 0;
            
            foreach ($products as $product) {
                $mainImagePath = 'uploads/products/' . $product['hinh_anh_chinh'];
                
                if (file_exists($mainImagePath)) {
                    // Tạo 3 bản copy cho album
                    $albumImages = [];
                    $pathInfo = pathinfo($product['hinh_anh_chinh']);
                    
                    for ($i = 1; $i <= 3; $i++) {
                        $albumName = $pathInfo['filename'] . "_album_{$i}." . $pathInfo['extension'];
                        $albumPath = 'uploads/products/' . $albumName;
                        
                        if (copy($mainImagePath, $albumPath)) {
                            $albumImages[] = $albumName;
                        }
                    }
                    
                    if (!empty($albumImages)) {
                        // Cập nhật album
                        $updateStmt = $pdo->prepare("UPDATE san_pham_chinh SET album_hinh_anh = ? WHERE id = ?");
                        $updateStmt->execute([json_encode($albumImages), $product['id']]);
                        $totalFixed++;
                    }
                }
            }
            
            $message = "✅ Đã fix {$totalFixed} sản phẩm! Tạo album từ ảnh chính.";
            $messageType = 'success';
            
        } catch (Exception $e) {
            $message = "❌ Lỗi: " . $e->getMessage();
            $messageType = 'danger';
        }
    }
}

// Lấy thông tin sản phẩm
$products = $pdo->query("
    SELECT 
        sp.id,
        sp.ten_san_pham,
        sp.thuong_hieu,
        sp.hinh_anh_chinh,
        sp.album_hinh_anh,
        sp.san_pham_noi_bat,
        sp.san_pham_moi
    FROM san_pham_chinh sp
    ORDER BY sp.id
")->fetchAll(PDO::FETCH_ASSOC);

$issues = [];
foreach ($products as $product) {
    $productIssues = [];
    
    // Kiểm tra ảnh chính
    if (empty($product['hinh_anh_chinh']) || !file_exists('uploads/products/' . $product['hinh_anh_chinh'])) {
        $productIssues[] = 'Thiếu ảnh chính';
    }
    
    // Kiểm tra album
    $albumImages = $product['album_hinh_anh'] ? json_decode($product['album_hinh_anh'], true) : [];
    $validAlbumCount = 0;
    if ($albumImages) {
        foreach ($albumImages as $img) {
            if (file_exists('uploads/products/' . $img)) $validAlbumCount++;
        }
    }
    
    if ($validAlbumCount < 1) {
        $productIssues[] = 'Thiếu album ảnh';
    }
    
    if (!empty($productIssues)) {
        $issues[] = [
            'product' => $product,
            'issues' => $productIssues,
            'album_count' => $validAlbumCount
        ];
    }
}
?>
<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Quick Fix Upload - TKTShop</title>
    <style>
        body { font-family: Arial, sans-serif; margin: 20px; line-height: 1.6; }
        .container { max-width: 1000px; margin: 0 auto; }
        .alert { padding: 15px; margin: 15px 0; border-radius: 4px; }
        .alert-success { background: #d4edda; color: #155724; }
        .alert-danger { background: #f8d7da; color: #721c24; }
        .alert-warning { background: #fff3cd; color: #856404; }
        .btn { padding: 10px 20px; border: none; border-radius: 4px; cursor: pointer; margin: 5px; text-decoration: none; display: inline-block; }
        .btn-primary { background: #007bff; color: white; }
        .btn-success { background: #28a745; color: white; }
        .btn-danger { background: #dc3545; color: white; }
        .btn-warning { background: #ffc107; color: black; }
        .btn:hover { opacity: 0.9; }
        .card { border: 1px solid #ddd; border-radius: 8px; margin: 15px 0; padding: 20px; }
        .card-danger { border-left: 4px solid #dc3545; }
        .upload-form { background: #f8f9fa; padding: 15px; border-radius: 4px; margin: 10px 0; }
        .form-group { margin: 10px 0; }
        .form-control { width: 100%; padding: 8px; border: 1px solid #ddd; border-radius: 4px; }
        .product-info { margin: 10px 0; }
        .tags { margin: 5px 0; }
        .tag { background: #007bff; color: white; padding: 2px 8px; border-radius: 12px; font-size: 12px; margin: 2px; }
        .tag.new { background: #28a745; }
        .tag.featured { background: #ffc107; color: black; }
        .stats { text-align: center; background: #e9ecef; padding: 20px; border-radius: 8px; margin: 20px 0; }
        .quick-actions { background: #fff3cd; padding: 20px; border-radius: 8px; margin: 20px 0; }
    </style>
</head>
<body>
<div class="container">
    <h1>🚀 Quick Fix Upload - Sửa Ngay Lập Tức</h1>
    
    <?php if ($message): ?>
    <div class="alert alert-<?= $messageType ?>">
        <?= $message ?>
    </div>
    <?php endif; ?>
    
    <div class="stats">
        <h3>📊 Thống Kê</h3>
        <p><strong>Sản phẩm có vấn đề:</strong> <?= count($issues) ?>/<?= count($products) ?></p>
        <p><strong>Thời gian:</strong> <?= date('H:i:s d/m/Y') ?></p>
    </div>
    
    <?php if (!empty($issues)): ?>
    
    <!-- Quick Actions -->
    <div class="quick-actions">
        <h3>⚡ Hành Động Nhanh</h3>
        <p>Tạo album ảnh từ ảnh chính có sẵn cho tất cả sản phẩm:</p>
        <form method="POST" style="display: inline;">
            <input type="hidden" name="action" value="quick_fix_all">
            <button type="submit" class="btn btn-warning" 
                    onclick="return confirm('Tạo album từ ảnh chính cho tất cả sản phẩm?')">
                🔧 Fix Tất Cả Ngay Lập Tức
            </button>
        </form>
        <small style="display: block; margin-top: 10px;">
            * Sẽ copy ảnh chính thành 3 ảnh album cho mỗi sản phẩm
        </small>
    </div>
    
    <!-- Danh sách sản phẩm có vấn đề -->
    <h2>🔧 Sản Phẩm Cần Sửa</h2>
    
    <?php foreach ($issues as $issue): 
        $product = $issue['product'];
    ?>
    <div class="card card-danger">
        <div class="product-info">
            <h3><?= htmlspecialchars($product['ten_san_pham']) ?></h3>
            <p><strong>Thương hiệu:</strong> <?= htmlspecialchars($product['thuong_hieu']) ?> | <strong>ID:</strong> <?= $product['id'] ?></p>
            
            <div class="tags">
                <?php if ($product['san_pham_moi']): ?>
                <span class="tag new">MỚI</span>
                <?php endif; ?>
                <?php if ($product['san_pham_noi_bat']): ?>
                <span class="tag featured">NỔI BẬT</span>
                <?php endif; ?>
                <span class="tag">Album: <?= $issue['album_count'] ?></span>
            </div>
            
            <p><strong>Vấn đề:</strong> 
                <span style="color: #dc3545;"><?= implode(', ', $issue['issues']) ?></span>
            </p>
        </div>
        
        <!-- Ảnh hiện tại -->
        <div style="margin: 15px 0;">
            <strong>Ảnh hiện tại:</strong><br>
            <?php if (!empty($product['hinh_anh_chinh']) && file_exists('uploads/products/' . $product['hinh_anh_chinh'])): ?>
            <img src="uploads/products/<?= $product['hinh_anh_chinh'] ?>" 
                 style="width: 100px; height: 100px; object-fit: cover; border: 1px solid #ddd; border-radius: 4px;">
            ✅ Có ảnh chính
            <?php else: ?>
            ❌ Thiếu ảnh chính
            <?php endif; ?>
        </div>
        
        <!-- Upload ảnh chính -->
        <?php if (in_array('Thiếu ảnh chính', $issue['issues'])): ?>
        <div class="upload-form">
            <h4>📷 Upload Ảnh Chính</h4>
            <form method="POST" enctype="multipart/form-data">
                <input type="hidden" name="action" value="upload_single">
                <input type="hidden" name="product_id" value="<?= $product['id'] ?>">
                <input type="hidden" name="image_type" value="main">
                
                <div class="form-group">
                    <input type="file" name="image" accept="image/*" class="form-control" required>
                </div>
                
                <button type="submit" class="btn btn-primary">
                    📤 Upload Ảnh Chính
                </button>
            </form>
        </div>
        <?php endif; ?>
        
        <!-- Upload album -->
        <div class="upload-form">
            <h4>🖼️ Thêm Ảnh Album</h4>
            <form method="POST" enctype="multipart/form-data">
                <input type="hidden" name="action" value="upload_single">
                <input type="hidden" name="product_id" value="<?= $product['id'] ?>">
                <input type="hidden" name="image_type" value="album">
                
                <div class="form-group">
                    <input type="file" name="image" accept="image/*" class="form-control" required>
                </div>
                
                <button type="submit" class="btn btn-success">
                    📤 Thêm Vào Album
                </button>
            </form>
        </div>
    </div>
    <?php endforeach; ?>
    
    <?php else: ?>
    
    <div class="alert alert-success">
        <h3>🎉 Hoàn Hảo!</h3>
        <p>Tất cả sản phẩm đã có ảnh đầy đủ!</p>
    </div>
    
    <?php endif; ?>
    
    <!-- Navigation -->
    <div style="text-align: center; margin: 30px 0;">
        <a href="debug_simple.php" class="btn btn-primary">🔍 Debug Lại</a>
        <a href="customer/" class="btn btn-success">👁️ Xem Website</a>
        <a href="customer/products.php?new=1" class="btn btn-success">🆕 Sản Phẩm Mới</a>
        <a href="customer/products.php?featured=1" class="btn btn-warning">⭐ Sản Phẩm Nổi Bật</a>
    </div>
</div>
</body>
</html>