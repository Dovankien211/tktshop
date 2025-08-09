<?php
/**
 * Upload album đơn giản - Fix ngay
 * File: /tktshop/simple_album_upload.php
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

// Xử lý upload thông thường (không AJAX)
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['upload'])) {
    try {
        $productId = $_POST['product_id'] ?? '';
        
        if (empty($productId)) {
            throw new Exception('Vui lòng chọn sản phẩm!');
        }
        
        if (!isset($_FILES['images']) || empty($_FILES['images']['name'][0])) {
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
                $maxSize = 5 * 1024 * 1024; // 5MB
                
                if (!in_array($file['type'], $allowedTypes)) {
                    $errors[] = "File {$file['name']}: Chỉ chấp nhận JPG, PNG, GIF";
                    continue;
                }
                
                if ($file['size'] > $maxSize) {
                    $errors[] = "File {$file['name']}: Quá lớn (max 5MB)";
                    continue;
                }
                
                // Tạo tên file unique
                $extension = pathinfo($file['name'], PATHINFO_EXTENSION);
                $fileName = time() . '_' . uniqid() . '_album.' . $extension;
                
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
            
            $message = "✅ Upload thành công " . count($uploadedFiles) . " ảnh!";
            $messageType = 'success';
            
            if (!empty($errors)) {
                $message .= " (Có " . count($errors) . " file lỗi)";
            }
        } else {
            $message = "❌ Không có file nào được upload thành công!";
            $messageType = 'danger';
        }
        
        if (!empty($errors)) {
            $message .= "<br><small>" . implode('<br>', $errors) . "</small>";
        }
        
    } catch (Exception $e) {
        $message = "❌ Lỗi: " . $e->getMessage();
        $messageType = 'danger';
    }
}

// Lấy danh sách sản phẩm
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
?>
<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Upload Album Đơn Giản - TKTShop</title>
    <style>
        body { font-family: Arial, sans-serif; margin: 20px; line-height: 1.6; }
        .container { max-width: 1200px; margin: 0 auto; }
        .form-group { margin: 15px 0; }
        .form-control { width: 100%; padding: 10px; border: 1px solid #ddd; border-radius: 4px; }
        .btn { padding: 12px 24px; border: none; border-radius: 4px; cursor: pointer; margin: 5px; }
        .btn-primary { background: #007bff; color: white; }
        .btn-success { background: #28a745; color: white; }
        .btn:hover { opacity: 0.9; }
        .alert { padding: 15px; margin: 20px 0; border-radius: 4px; }
        .alert-success { background: #d4edda; color: #155724; border: 1px solid #c3e6cb; }
        .alert-danger { background: #f8d7da; color: #721c24; border: 1px solid #f1b0b7; }
        .product-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(300px, 1fr)); gap: 20px; margin: 20px 0; }
        .product-card { border: 1px solid #ddd; border-radius: 8px; padding: 20px; }
        .product-header { margin-bottom: 15px; }
        .product-title { font-size: 18px; font-weight: bold; margin: 0; }
        .product-brand { color: #666; font-size: 14px; }
        .product-tags { margin: 10px 0; }
        .tag { background: #007bff; color: white; padding: 2px 8px; border-radius: 12px; font-size: 12px; margin: 2px; }
        .tag.new { background: #28a745; }
        .tag.featured { background: #ffc107; color: #000; }
        .album-preview { display: flex; gap: 5px; flex-wrap: wrap; margin: 10px 0; }
        .album-img { width: 50px; height: 50px; object-fit: cover; border-radius: 4px; border: 1px solid #ddd; }
        .upload-section { background: #f8f9fa; padding: 15px; border-radius: 4px; margin: 15px 0; }
        .file-info { margin: 10px 0; font-size: 14px; color: #666; }
        .stats { text-align: center; margin: 20px 0; }
    </style>
</head>
<body>
<div class="container">
    <h1>📤 Upload Album Đơn Giản</h1>
    
    <?php if ($message): ?>
    <div class="alert alert-<?= $messageType ?>">
        <?= $message ?>
    </div>
    <?php endif; ?>
    
    <div class="stats">
        <p><strong>Tổng sản phẩm:</strong> <?= count($products) ?> | 
           <strong>Thời gian:</strong> <?= date('H:i:s d/m/Y') ?></p>
    </div>
    
    <div class="product-grid">
        <?php foreach ($products as $product): 
            $albumImages = $product['album_hinh_anh'] ? json_decode($product['album_hinh_anh'], true) : [];
            $validAlbumCount = 0;
            
            if ($albumImages) {
                foreach ($albumImages as $img) {
                    if (file_exists('uploads/products/' . $img)) $validAlbumCount++;
                }
            }
        ?>
        <div class="product-card">
            <div class="product-header">
                <div class="product-title"><?= htmlspecialchars($product['ten_san_pham']) ?></div>
                <div class="product-brand"><?= htmlspecialchars($product['thuong_hieu']) ?> (ID: <?= $product['id'] ?>)</div>
                
                <div class="product-tags">
                    <?php if ($product['san_pham_moi']): ?>
                    <span class="tag new">MỚI</span>
                    <?php endif; ?>
                    <?php if ($product['san_pham_noi_bat']): ?>
                    <span class="tag featured">NỔI BẬT</span>
                    <?php endif; ?>
                    <span class="tag">Album: <?= $validAlbumCount ?></span>
                </div>
            </div>
            
            <!-- Ảnh chính -->
            <div>
                <strong>Ảnh chính:</strong><br>
                <?php if (!empty($product['hinh_anh_chinh']) && file_exists('uploads/products/' . $product['hinh_anh_chinh'])): ?>
                <img src="uploads/products/<?= $product['hinh_anh_chinh'] ?>" class="album-img" alt="Main">
                ✅ Có ảnh chính
                <?php else: ?>
                ❌ Thiếu ảnh chính
                <?php endif; ?>
            </div>
            
            <!-- Album hiện tại -->
            <div style="margin: 15px 0;">
                <strong>Album hiện tại (<?= $validAlbumCount ?> ảnh):</strong>
                <div class="album-preview">
                    <?php if (!empty($albumImages)): ?>
                        <?php foreach ($albumImages as $img): ?>
                            <?php if (file_exists('uploads/products/' . $img)): ?>
                            <img src="uploads/products/<?= $img ?>" class="album-img" alt="Album" title="<?= $img ?>">
                            <?php endif; ?>
                        <?php endforeach; ?>
                    <?php else: ?>
                    <em>Chưa có ảnh album</em>
                    <?php endif; ?>
                </div>
            </div>
            
            <!-- Form upload -->
            <div class="upload-section">
                <form method="POST" enctype="multipart/form-data">
                    <input type="hidden" name="product_id" value="<?= $product['id'] ?>">
                    
                    <div class="form-group">
                        <label><strong>Chọn ảnh album:</strong></label>
                        <input type="file" 
                               name="images[]" 
                               multiple 
                               accept="image/*" 
                               class="form-control"
                               onchange="showFileInfo(this, <?= $product['id'] ?>)">
                    </div>
                    
                    <div class="file-info" id="file-info-<?= $product['id'] ?>">
                        Chưa chọn file nào
                    </div>
                    
                    <button type="submit" name="upload" class="btn btn-primary">
                        📤 Upload Ảnh Album
                    </button>
                </form>
            </div>
        </div>
        <?php endforeach; ?>
    </div>
    
    <div style="text-align: center; margin: 30px 0;">
        <a href="debug_simple.php" class="btn btn-success">🔍 Debug Lại</a>
        <a href="customer/" class="btn btn-success">👁️ Xem Website</a>
    </div>
</div>

<script>
function showFileInfo(input, productId) {
    const fileInfoDiv = document.getElementById('file-info-' + productId);
    const files = input.files;
    
    if (files.length === 0) {
        fileInfoDiv.innerHTML = 'Chưa chọn file nào';
        return;
    }
    
    let html = `<strong>Đã chọn ${files.length} file:</strong><br>`;
    for (let i = 0; i < files.length; i++) {
        const file = files[i];
        const sizeKB = Math.round(file.size / 1024);
        html += `• ${file.name} (${sizeKB} KB)<br>`;
    }
    
    fileInfoDiv.innerHTML = html;
}

// Auto scroll to message if exists
<?php if ($message): ?>
window.scrollTo(0, 0);
<?php endif; ?>
</script>
</body>
</html>