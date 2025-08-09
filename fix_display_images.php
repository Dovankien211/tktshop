<?php
/**
 * Fix hiển thị ảnh website - TKTShop
 * File: /tktshop/fix_display_images.php
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

// Xử lý các hành động
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $action = $_POST['action'] ?? '';
    
    if ($action == 'create_albums') {
        // Tạo album từ ảnh chính cho tất cả sản phẩm
        try {
            $products = $pdo->query("SELECT id, ten_san_pham, hinh_anh_chinh FROM san_pham_chinh WHERE hinh_anh_chinh IS NOT NULL")->fetchAll(PDO::FETCH_ASSOC);
            $totalCreated = 0;
            $errors = [];
            
            foreach ($products as $product) {
                $mainImagePath = 'uploads/products/' . $product['hinh_anh_chinh'];
                
                if (file_exists($mainImagePath)) {
                    // Tạo 3 bản copy cho album
                    $albumImages = [];
                    $pathInfo = pathinfo($product['hinh_anh_chinh']);
                    
                    for ($i = 1; $i <= 3; $i++) {
                        $albumName = $pathInfo['filename'] . "_album_{$i}." . $pathInfo['extension'];
                        $albumPath = 'uploads/products/' . $albumName;
                        
                        // Kiểm tra file đã tồn tại chưa
                        if (!file_exists($albumPath)) {
                            if (copy($mainImagePath, $albumPath)) {
                                $albumImages[] = $albumName;
                            } else {
                                $errors[] = "Không thể copy {$albumName}";
                            }
                        } else {
                            $albumImages[] = $albumName; // File đã tồn tại
                        }
                    }
                    
                    if (!empty($albumImages)) {
                        // Lấy album hiện tại
                        $stmt = $pdo->prepare("SELECT album_hinh_anh FROM san_pham_chinh WHERE id = ?");
                        $stmt->execute([$product['id']]);
                        $currentAlbum = $stmt->fetchColumn();
                        
                        $existingAlbum = $currentAlbum ? json_decode($currentAlbum, true) : [];
                        $finalAlbum = array_merge($existingAlbum, $albumImages);
                        $finalAlbum = array_unique($finalAlbum); // Loại bỏ trùng lặp
                        
                        // Cập nhật database
                        $updateStmt = $pdo->prepare("UPDATE san_pham_chinh SET album_hinh_anh = ? WHERE id = ?");
                        $updateStmt->execute([json_encode($finalAlbum), $product['id']]);
                        $totalCreated++;
                    }
                } else {
                    $errors[] = "File ảnh chính không tồn tại: {$product['hinh_anh_chinh']}";
                }
            }
            
            $message = "✅ Đã tạo album cho {$totalCreated} sản phẩm!";
            if (!empty($errors)) {
                $message .= "<br><small>Lỗi: " . implode('<br>', array_slice($errors, 0, 5)) . "</small>";
            }
            $messageType = 'success';
            
        } catch (Exception $e) {
            $message = "❌ Lỗi: " . $e->getMessage();
            $messageType = 'danger';
        }
    }
    
    elseif ($action == 'set_featured_new') {
        // Cập nhật sản phẩm nổi bật và mới
        try {
            // Reset tất cả
            $pdo->exec("UPDATE san_pham_chinh SET san_pham_noi_bat = 0, san_pham_moi = 0");
            
            // Set sản phẩm mới (ID 2, 5)
            $pdo->exec("UPDATE san_pham_chinh SET san_pham_moi = 1 WHERE id IN (2, 5)");
            
            // Set sản phẩm nổi bật (ID 2, 3, 4)
            $pdo->exec("UPDATE san_pham_chinh SET san_pham_noi_bat = 1 WHERE id IN (2, 3, 4)");
            
            $message = "✅ Đã cập nhật sản phẩm nổi bật và mới!";
            $messageType = 'success';
            
        } catch (Exception $e) {
            $message = "❌ Lỗi: " . $e->getMessage();
            $messageType = 'danger';
        }
    }
    
    elseif ($action == 'fix_image_paths') {
        // Kiểm tra và sửa đường dẫn ảnh
        try {
            $products = $pdo->query("SELECT id, ten_san_pham, hinh_anh_chinh, album_hinh_anh FROM san_pham_chinh")->fetchAll(PDO::FETCH_ASSOC);
            $fixedCount = 0;
            
            foreach ($products as $product) {
                $needUpdate = false;
                $newMainImage = $product['hinh_anh_chinh'];
                $newAlbum = $product['album_hinh_anh'];
                
                // Kiểm tra ảnh chính
                if (!empty($product['hinh_anh_chinh'])) {
                    $mainPath = 'uploads/products/' . $product['hinh_anh_chinh'];
                    if (!file_exists($mainPath)) {
                        // Tìm file tương tự
                        $files = glob('uploads/products/*');
                        foreach ($files as $file) {
                            $fileName = basename($file);
                            if (strpos($fileName, 'product_' . $product['id']) !== false || 
                                strpos($fileName, $product['id'] . '_') !== false) {
                                $newMainImage = $fileName;
                                $needUpdate = true;
                                break;
                            }
                        }
                    }
                }
                
                // Cập nhật nếu cần
                if ($needUpdate) {
                    $stmt = $pdo->prepare("UPDATE san_pham_chinh SET hinh_anh_chinh = ? WHERE id = ?");
                    $stmt->execute([$newMainImage, $product['id']]);
                    $fixedCount++;
                }
            }
            
            $message = "✅ Đã sửa đường dẫn cho {$fixedCount} sản phẩm!";
            $messageType = 'success';
            
        } catch (Exception $e) {
            $message = "❌ Lỗi: " . $e->getMessage();
            $messageType = 'danger';
        }
    }
}

// Lấy thông tin chi tiết
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

$stats = [
    'total_products' => count($products),
    'has_main_image' => 0,
    'has_album' => 0,
    'missing_main' => 0,
    'missing_album' => 0,
    'featured_count' => 0,
    'new_count' => 0
];

$productDetails = [];

foreach ($products as $product) {
    $detail = [
        'product' => $product,
        'main_exists' => false,
        'album_count' => 0,
        'album_files' => []
    ];
    
    // Kiểm tra ảnh chính
    if (!empty($product['hinh_anh_chinh'])) {
        $mainPath = 'uploads/products/' . $product['hinh_anh_chinh'];
        if (file_exists($mainPath)) {
            $detail['main_exists'] = true;
            $stats['has_main_image']++;
        } else {
            $stats['missing_main']++;
        }
    } else {
        $stats['missing_main']++;
    }
    
    // Kiểm tra album
    if (!empty($product['album_hinh_anh'])) {
        $albumImages = json_decode($product['album_hinh_anh'], true);
        if ($albumImages) {
            foreach ($albumImages as $img) {
                if (file_exists('uploads/products/' . $img)) {
                    $detail['album_count']++;
                    $detail['album_files'][] = $img;
                }
            }
        }
    }
    
    if ($detail['album_count'] > 0) {
        $stats['has_album']++;
    } else {
        $stats['missing_album']++;
    }
    
    if ($product['san_pham_noi_bat']) $stats['featured_count']++;
    if ($product['san_pham_moi']) $stats['new_count']++;
    
    $productDetails[] = $detail;
}
?>
<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Fix Hiển Thị Ảnh - TKTShop</title>
    <style>
        body { font-family: Arial, sans-serif; margin: 20px; line-height: 1.6; }
        .container { max-width: 1200px; margin: 0 auto; }
        .alert { padding: 15px; margin: 15px 0; border-radius: 4px; }
        .alert-success { background: #d4edda; color: #155724; }
        .alert-danger { background: #f8d7da; color: #721c24; }
        .alert-warning { background: #fff3cd; color: #856404; }
        .btn { padding: 12px 20px; border: none; border-radius: 4px; cursor: pointer; margin: 5px; }
        .btn-primary { background: #007bff; color: white; }
        .btn-success { background: #28a745; color: white; }
        .btn-warning { background: #ffc107; color: black; }
        .btn-danger { background: #dc3545; color: white; }
        .btn:hover { opacity: 0.9; }
        .stats-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 15px; margin: 20px 0; }
        .stat-card { background: #f8f9fa; padding: 20px; border-radius: 8px; text-align: center; }
        .stat-number { font-size: 2em; font-weight: bold; color: #007bff; }
        .actions-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(300px, 1fr)); gap: 20px; margin: 20px 0; }
        .action-card { border: 1px solid #ddd; border-radius: 8px; padding: 20px; }
        .action-card h3 { margin-top: 0; }
        .product-list { margin: 20px 0; }
        .product-item { border: 1px solid #ddd; border-radius: 4px; padding: 15px; margin: 10px 0; }
        .product-item.missing { border-left: 4px solid #dc3545; }
        .product-item.complete { border-left: 4px solid #28a745; }
        .image-preview { display: flex; gap: 10px; margin: 10px 0; }
        .image-preview img { width: 50px; height: 50px; object-fit: cover; border: 1px solid #ddd; border-radius: 4px; }
        .tag { background: #007bff; color: white; padding: 2px 8px; border-radius: 12px; font-size: 12px; margin: 2px; }
        .tag.new { background: #28a745; }
        .tag.featured { background: #ffc107; color: black; }
    </style>
</head>
<body>
<div class="container">
    <h1>🔧 Fix Hiển Thị Ảnh Website</h1>
    
    <?php if ($message): ?>
    <div class="alert alert-<?= $messageType ?>">
        <?= $message ?>
    </div>
    <?php endif; ?>
    
    <!-- Thống kê -->
    <div class="stats-grid">
        <div class="stat-card">
            <div class="stat-number"><?= $stats['total_products'] ?></div>
            <div>Tổng sản phẩm</div>
        </div>
        <div class="stat-card">
            <div class="stat-number"><?= $stats['has_main_image'] ?></div>
            <div>Có ảnh chính</div>
        </div>
        <div class="stat-card">
            <div class="stat-number"><?= $stats['has_album'] ?></div>
            <div>Có album</div>
        </div>
        <div class="stat-card">
            <div class="stat-number"><?= $stats['featured_count'] ?></div>
            <div>Nổi bật</div>
        </div>
        <div class="stat-card">
            <div class="stat-number"><?= $stats['new_count'] ?></div>
            <div>Sản phẩm mới</div>
        </div>
    </div>
    
    <!-- Hành động khắc phục -->
    <div class="actions-grid">
        <div class="action-card">
            <h3>🖼️ Tạo Album Từ Ảnh Chính</h3>
            <p>Copy ảnh chính thành 3 ảnh album cho tất cả sản phẩm</p>
            <form method="POST" style="display: inline;">
                <input type="hidden" name="action" value="create_albums">
                <button type="submit" class="btn btn-primary" 
                        onclick="return confirm('Tạo album từ ảnh chính?')">
                    🔧 Tạo Album Ngay
                </button>
            </form>
        </div>
        
        <div class="action-card">
            <h3>⭐ Cập Nhật Sản Phẩm Nổi Bật/Mới</h3>
            <p>Đặt sản phẩm nổi bật và mới theo chuẩn</p>
            <form method="POST" style="display: inline;">
                <input type="hidden" name="action" value="set_featured_new">
                <button type="submit" class="btn btn-warning" 
                        onclick="return confirm('Cập nhật sản phẩm nổi bật/mới?')">
                    ⭐ Cập Nhật Tags
                </button>
            </form>
        </div>
        
        <div class="action-card">
            <h3>🔗 Sửa Đường Dẫn Ảnh</h3>
            <p>Tự động tìm và sửa đường dẫn ảnh bị lỗi</p>
            <form method="POST" style="display: inline;">
                <input type="hidden" name="action" value="fix_image_paths">
                <button type="submit" class="btn btn-danger" 
                        onclick="return confirm('Sửa đường dẫn ảnh?')">
                    🔗 Sửa Đường Dẫn
                </button>
            </form>
        </div>
    </div>
    
    <!-- Chi tiết sản phẩm -->
    <h2>📋 Chi Tiết Từng Sản Phẩm</h2>
    <div class="product-list">
        <?php foreach ($productDetails as $detail): 
            $product = $detail['product'];
            $isComplete = $detail['main_exists'] && $detail['album_count'] > 0;
        ?>
        <div class="product-item <?= $isComplete ? 'complete' : 'missing' ?>">
            <h4>
                <?= htmlspecialchars($product['ten_san_pham']) ?>
                <small>(ID: <?= $product['id'] ?>) - <?= htmlspecialchars($product['thuong_hieu']) ?></small>
            </h4>
            
            <div style="margin: 10px 0;">
                <?php if ($product['san_pham_moi']): ?>
                <span class="tag new">MỚI</span>
                <?php endif; ?>
                <?php if ($product['san_pham_noi_bat']): ?>
                <span class="tag featured">NỔI BẬT</span>
                <?php endif; ?>
            </div>
            
            <div>
                <strong>Ảnh chính:</strong> 
                <?= $detail['main_exists'] ? '✅' : '❌' ?>
                <?= $product['hinh_anh_chinh'] ?: 'Chưa có' ?>
            </div>
            
            <div>
                <strong>Album:</strong> <?= $detail['album_count'] ?> ảnh
            </div>
            
            <?php if ($detail['main_exists'] || !empty($detail['album_files'])): ?>
            <div class="image-preview">
                <?php if ($detail['main_exists']): ?>
                <img src="uploads/products/<?= $product['hinh_anh_chinh'] ?>" alt="Main" title="Ảnh chính">
                <?php endif; ?>
                
                <?php foreach (array_slice($detail['album_files'], 0, 5) as $img): ?>
                <img src="uploads/products/<?= $img ?>" alt="Album" title="<?= $img ?>">
                <?php endforeach; ?>
                
                <?php if (count($detail['album_files']) > 5): ?>
                <div style="align-self: center;">+<?= count($detail['album_files']) - 5 ?> ảnh</div>
                <?php endif; ?>
            </div>
            <?php endif; ?>
        </div>
        <?php endforeach; ?>
    </div>
    
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