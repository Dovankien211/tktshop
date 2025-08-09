<?php
/**
 * Dọn dẹp album - Fix ngay lập tức
 * File: /tktshop/clean_album_fix.php
 */

require_once 'config/database.php';

try {
    $pdo = new PDO("mysql:host=localhost;dbname=tktshop;charset=utf8mb4", "root", "");
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
} catch(PDOException $e) {
    die("Lỗi database: " . $e->getMessage());
}

$results = [];
$totalCleaned = 0;
$totalKept = 0;

// Xử lý fix
if (isset($_GET['action']) && $_GET['action'] == 'fix') {
    $products = $pdo->query("SELECT id, ten_san_pham, album_hinh_anh FROM san_pham_chinh")->fetchAll(PDO::FETCH_ASSOC);
    
    foreach ($products as $product) {
        $results[$product['id']] = [
            'name' => $product['ten_san_pham'],
            'old_album' => $product['album_hinh_anh'],
            'old_count' => 0,
            'new_count' => 0,
            'cleaned_files' => [],
            'kept_files' => []
        ];
        
        if (!empty($product['album_hinh_anh'])) {
            $albumImages = json_decode($product['album_hinh_anh'], true);
            if ($albumImages) {
                $results[$product['id']]['old_count'] = count($albumImages);
                
                $validImages = [];
                foreach ($albumImages as $img) {
                    if (file_exists('uploads/products/' . $img)) {
                        $validImages[] = $img;
                        $results[$product['id']]['kept_files'][] = $img;
                        $totalKept++;
                    } else {
                        $results[$product['id']]['cleaned_files'][] = $img;
                        $totalCleaned++;
                    }
                }
                
                // Cập nhật database
                $newAlbum = empty($validImages) ? null : json_encode($validImages);
                $updateStmt = $pdo->prepare("UPDATE san_pham_chinh SET album_hinh_anh = ? WHERE id = ?");
                $updateStmt->execute([$newAlbum, $product['id']]);
                
                $results[$product['id']]['new_count'] = count($validImages);
            }
        }
    }
}

?>
<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dọn Dẹp Album - TKTShop</title>
    <style>
        body { font-family: Arial, sans-serif; margin: 20px; line-height: 1.6; }
        .container { max-width: 1200px; margin: 0 auto; }
        .btn { padding: 12px 24px; margin: 10px 5px; border: none; border-radius: 6px; cursor: pointer; text-decoration: none; display: inline-block; font-weight: bold; }
        .btn-danger { background: #dc3545; color: white; }
        .btn-success { background: #28a745; color: white; }
        .btn-primary { background: #007bff; color: white; }
        .btn:hover { opacity: 0.9; transform: translateY(-1px); }
        .alert { padding: 15px; margin: 20px 0; border-radius: 6px; }
        .alert-success { background: #d4edda; color: #155724; border: 1px solid #c3e6cb; }
        .alert-warning { background: #fff3cd; color: #856404; border: 1px solid #ffeaa7; }
        .alert-info { background: #d1ecf1; color: #0c5460; border: 1px solid #bee5eb; }
        .card { border: 1px solid #ddd; border-radius: 8px; margin: 20px 0; }
        .card-header { background: #f8f9fa; padding: 15px; border-bottom: 1px solid #ddd; font-weight: bold; }
        .card-body { padding: 20px; }
        .stats { display: flex; gap: 20px; margin: 20px 0; }
        .stat-box { background: #f8f9fa; padding: 20px; border-radius: 8px; text-align: center; flex: 1; }
        .file-list { margin: 10px 0; }
        .file-item { display: inline-block; background: #e9ecef; padding: 5px 10px; margin: 2px; border-radius: 4px; font-size: 12px; }
        .file-kept { background: #d4edda; color: #155724; }
        .file-removed { background: #f8d7da; color: #721c24; }
        .summary { background: #e3f2fd; padding: 20px; border-radius: 8px; margin: 20px 0; }
    </style>
</head>
<body>
<div class="container">
    <h1>🧹 Dọn Dẹp Album Ảnh</h1>
    
    <?php if (empty($results)): ?>
    <!-- Trước khi fix -->
    <div class="alert alert-warning">
        <h3>⚠️ Phát hiện vấn đề album ảnh</h3>
        <p>Trong database có <strong>12 file demo cũ</strong> không tồn tại:</p>
        <ul>
            <li><code>demo_product_2_gallery_0.jpg</code></li>
            <li><code>demo_product_2_gallery_1.jpg</code></li>
            <li><code>demo_product_2_gallery_2.jpg</code></li>
            <li><code>demo_product_3_gallery_0.jpg</code></li>
            <li><code>demo_product_3_gallery_1.jpg</code></li>
            <li><code>demo_product_3_gallery_2.jpg</code></li>
            <li><code>demo_product_4_gallery_0.jpg</code></li>
            <li><code>demo_product_4_gallery_1.jpg</code></li>
            <li><code>demo_product_4_gallery_2.jpg</code></li>
            <li><code>demo_product_5_gallery_0.jpg</code></li>
            <li><code>demo_product_5_gallery_1.jpg</code></li>
            <li><code>demo_product_5_gallery_2.jpg</code></li>
        </ul>
    </div>
    
    <div class="alert alert-info">
        <h3>💡 Giải pháp</h3>
        <p>Script sẽ:</p>
        <ol>
            <li><strong>Quét tất cả album</strong> trong database</li>
            <li><strong>Xóa tham chiếu</strong> những file không tồn tại</li>
            <li><strong>Giữ lại</strong> những file thật có trong thư mục</li>
            <li><strong>Cập nhật database</strong> với album sạch</li>
        </ol>
        <p><strong>Kết quả:</strong> Từ 32 file → 20 file (xóa 12 file demo cũ)</p>
    </div>
    
    <div style="text-align: center; margin: 30px 0;">
        <a href="?action=fix" class="btn btn-danger" 
           onclick="return confirm('Bạn có chắc muốn dọn dẹp album? Hành động này không thể hoàn tác!')">
            🧹 Dọn Dẹp Ngay Lập Tức
        </a>
    </div>
    
    <?php else: ?>
    <!-- Sau khi fix -->
    <div class="alert alert-success">
        <h3>✅ Dọn dẹp thành công!</h3>
        <p>Đã xóa <strong><?= $totalCleaned ?></strong> tham chiếu file không tồn tại và giữ lại <strong><?= $totalKept ?></strong> file thật.</p>
    </div>
    
    <div class="stats">
        <div class="stat-box">
            <h3><?= $totalCleaned ?></h3>
            <p>File đã xóa</p>
        </div>
        <div class="stat-box">
            <h3><?= $totalKept ?></h3>
            <p>File giữ lại</p>
        </div>
        <div class="stat-box">
            <h3><?= count($results) ?></h3>
            <p>Sản phẩm xử lý</p>
        </div>
    </div>
    
    <div class="card">
        <div class="card-header">📋 Chi tiết từng sản phẩm</div>
        <div class="card-body">
            <?php foreach ($results as $productId => $result): ?>
            <div style="border-bottom: 1px solid #eee; padding: 15px 0;">
                <h4>🏷️ <?= htmlspecialchars($result['name']) ?> (ID: <?= $productId ?>)</h4>
                <p><strong>Album cũ:</strong> <?= $result['old_count'] ?> file → <strong>Album mới:</strong> <?= $result['new_count'] ?> file</p>
                
                <?php if (!empty($result['kept_files'])): ?>
                <div class="file-list">
                    <strong>✅ File giữ lại (<?= count($result['kept_files']) ?>):</strong><br>
                    <?php foreach ($result['kept_files'] as $file): ?>
                    <span class="file-item file-kept"><?= htmlspecialchars($file) ?></span>
                    <?php endforeach; ?>
                </div>
                <?php endif; ?>
                
                <?php if (!empty($result['cleaned_files'])): ?>
                <div class="file-list">
                    <strong>🗑️ File đã xóa (<?= count($result['cleaned_files']) ?>):</strong><br>
                    <?php foreach ($result['cleaned_files'] as $file): ?>
                    <span class="file-item file-removed"><?= htmlspecialchars($file) ?></span>
                    <?php endforeach; ?>
                </div>
                <?php endif; ?>
            </div>
            <?php endforeach; ?>
        </div>
    </div>
    
    <div class="summary">
        <h3>🎉 Hoàn thành!</h3>
        <p>Database đã được dọn dẹp. Bây giờ bạn có thể:</p>
        <ul>
            <li>Chạy lại debug để kiểm tra kết quả</li>
            <li>Xem website để đảm bảo ảnh hiển thị đúng</li>
            <li>Upload thêm ảnh mới nếu cần</li>
        </ul>
    </div>
    
    <?php endif; ?>
    
    <div style="text-align: center; margin: 30px 0;">
        <a href="debug_simple.php" class="btn btn-primary">🔍 Debug Lại</a>
        <a href="debug_album_detail.php" class="btn btn-primary">📋 Chi Tiết Album</a>
        <a href="upload_no_refresh.php" class="btn btn-success">📤 Upload Ảnh</a>
        <a href="customer/" class="btn btn-success">👁️ Xem Website</a>
    </div>
</div>
</body>
</html>