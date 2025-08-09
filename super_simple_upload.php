<?php
/**
 * Upload ảnh cực đơn giản - Chắc chắn work
 * File: /tktshop/super_simple_upload.php
 */

// Kết nối database đơn giản
try {
    $pdo = new PDO("mysql:host=localhost;dbname=tktshop;charset=utf8mb4", "root", "");
} catch(PDOException $e) {
    die("Lỗi database: " . $e->getMessage());
}

$message = '';

// XỬ LÝ UPLOAD
if (isset($_POST['upload']) && isset($_FILES['image'])) {
    $productId = $_POST['product_id'];
    $uploadType = $_POST['upload_type']; // main hoặc album
    
    $file = $_FILES['image'];
    
    if ($file['error'] == 0) {
        // Tạo tên file đơn giản
        $extension = pathinfo($file['name'], PATHINFO_EXTENSION);
        $newName = time() . '_' . $productId . '_' . $uploadType . '.' . $extension;
        $uploadPath = 'uploads/products/' . $newName;
        
        // Upload file
        if (move_uploaded_file($file['tmp_name'], $uploadPath)) {
            // Cập nhật database
            if ($uploadType == 'main') {
                // Cập nhật ảnh chính
                $sql = "UPDATE san_pham_chinh SET hinh_anh_chinh = ? WHERE id = ?";
                $stmt = $pdo->prepare($sql);
                $stmt->execute([$newName, $productId]);
                $message = "✅ Upload ảnh chính thành công: $newName";
            } else {
                // Thêm vào album
                $sql = "SELECT album_hinh_anh FROM san_pham_chinh WHERE id = ?";
                $stmt = $pdo->prepare($sql);
                $stmt->execute([$productId]);
                $current = $stmt->fetchColumn();
                
                $album = $current ? json_decode($current, true) : [];
                $album[] = $newName;
                
                $sql = "UPDATE san_pham_chinh SET album_hinh_anh = ? WHERE id = ?";
                $stmt = $pdo->prepare($sql);
                $stmt->execute([json_encode($album), $productId]);
                $message = "✅ Thêm ảnh album thành công: $newName";
            }
        } else {
            $message = "❌ Lỗi upload file!";
        }
    } else {
        $message = "❌ Lỗi file: " . $file['error'];
    }
}

// Lấy danh sách sản phẩm
$sql = "SELECT id, ten_san_pham, thuong_hieu, hinh_anh_chinh, album_hinh_anh FROM san_pham_chinh ORDER BY id";
$products = $pdo->query($sql)->fetchAll(PDO::FETCH_ASSOC);
?>
<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <title>Upload Ảnh Cực Đơn Giản</title>
    <style>
        body { font-family: Arial, sans-serif; margin: 20px; }
        .product { border: 2px solid #007bff; margin: 20px 0; padding: 20px; border-radius: 8px; }
        .upload-form { background: #f0f8ff; padding: 15px; margin: 10px 0; border-radius: 4px; }
        .btn { background: #007bff; color: white; padding: 10px 20px; border: none; border-radius: 4px; cursor: pointer; margin: 5px; }
        .btn:hover { background: #0056b3; }
        .btn-success { background: #28a745; }
        .btn-danger { background: #dc3545; }
        .message { padding: 15px; margin: 10px 0; border-radius: 4px; background: #d4edda; color: #155724; }
        .current-images { margin: 10px 0; }
        .current-images img { width: 60px; height: 60px; object-fit: cover; margin: 5px; border: 1px solid #ddd; }
        .form-group { margin: 10px 0; }
        input[type="file"] { width: 100%; padding: 8px; }
        h1 { color: #007bff; text-align: center; }
        .big-title { font-size: 2em; font-weight: bold; text-align: center; margin: 20px 0; }
    </style>
</head>
<body>

<div class="big-title">📤 UPLOAD ẢNH CỰC ĐƠN GIẢN</div>

<?php if ($message): ?>
<div class="message"><?= $message ?></div>
<?php endif; ?>

<div style="text-align: center; margin: 20px 0;">
    <h2>🎯 CHỌN SẢN PHẨM VÀ UPLOAD ẢNH NGAY!</h2>
</div>

<?php foreach ($products as $product): ?>
<div class="product">
    <h2>🏷️ <?= htmlspecialchars($product['ten_san_pham']) ?></h2>
    <p><strong>Thương hiệu:</strong> <?= htmlspecialchars($product['thuong_hieu']) ?> | <strong>ID:</strong> <?= $product['id'] ?></p>
    
    <!-- Hiển thị ảnh hiện tại -->
    <div class="current-images">
        <strong>Ảnh hiện tại:</strong><br>
        
        <!-- Ảnh chính -->
        <div style="margin: 10px 0;">
            <strong>Ảnh chính:</strong>
            <?php if (!empty($product['hinh_anh_chinh']) && file_exists('uploads/products/' . $product['hinh_anh_chinh'])): ?>
            <br><img src="uploads/products/<?= $product['hinh_anh_chinh'] ?>" alt="Main">
            ✅ Có ảnh chính
            <?php else: ?>
            <br>❌ <strong style="color: red;">THIẾU ẢNH CHÍNH</strong>
            <?php endif; ?>
        </div>
        
        <!-- Album -->
        <div style="margin: 10px 0;">
            <strong>Album:</strong>
            <?php 
            $albumImages = $product['album_hinh_anh'] ? json_decode($product['album_hinh_anh'], true) : [];
            $validCount = 0;
            if ($albumImages) {
                foreach ($albumImages as $img) {
                    if (file_exists('uploads/products/' . $img)) {
                        echo "<img src='uploads/products/$img' alt='Album'>";
                        $validCount++;
                    }
                }
            }
            ?>
            <br><?= $validCount > 0 ? "✅ Có $validCount ảnh album" : "❌ <strong style='color: red;'>THIẾU ALBUM</strong>" ?>
        </div>
    </div>
    
    <!-- Form upload ảnh chính -->
    <div class="upload-form">
        <h3>📷 Upload Ảnh Chính</h3>
        <form method="POST" enctype="multipart/form-data">
            <input type="hidden" name="product_id" value="<?= $product['id'] ?>">
            <input type="hidden" name="upload_type" value="main">
            
            <div class="form-group">
                <input type="file" name="image" accept="image/*" required>
            </div>
            
            <button type="submit" name="upload" class="btn">
                📤 UPLOAD ẢNH CHÍNH
            </button>
        </form>
    </div>
    
    <!-- Form upload album -->
    <div class="upload-form">
        <h3>🖼️ Thêm Ảnh Album</h3>
        <form method="POST" enctype="multipart/form-data">
            <input type="hidden" name="product_id" value="<?= $product['id'] ?>">
            <input type="hidden" name="upload_type" value="album">
            
            <div class="form-group">
                <input type="file" name="image" accept="image/*" required>
            </div>
            
            <button type="submit" name="upload" class="btn btn-success">
                📤 THÊM VÀO ALBUM
            </button>
        </form>
    </div>
</div>
<?php endforeach; ?>

<div style="text-align: center; margin: 30px 0; padding: 20px; background: #e9ecef; border-radius: 8px;">
    <h2>🔗 KIỂM TRA KẾT QUẢ</h2>
    <a href="customer/" class="btn btn-success" style="font-size: 18px; padding: 15px 30px;">
        👁️ XEM WEBSITE NGAY
    </a>
    <br><br>
    <a href="customer/products.php?new=1" class="btn">🆕 Sản phẩm mới</a>
    <a href="customer/products.php?featured=1" class="btn">⭐ Sản phẩm nổi bật</a>
    <a href="debug_simple.php" class="btn">🔍 Debug lại</a>
</div>

<div style="background: #fff3cd; padding: 20px; margin: 20px 0; border-radius: 8px; border: 1px solid #ffeaa7;">
    <h3>💡 HƯỚNG DẪN:</h3>
    <ol style="font-size: 16px;">
        <li><strong>Chọn file ảnh</strong> (JPG, PNG, GIF)</li>
        <li><strong>Click "UPLOAD ẢNH CHÍNH"</strong> hoặc <strong>"THÊM VÀO ALBUM"</strong></li>
        <li><strong>Đợi trang reload</strong> → Thấy thông báo thành công</li>
        <li><strong>Click "XEM WEBSITE NGAY"</strong> để kiểm tra</li>
    </ol>
    <p style="color: #856404; font-weight: bold;">
        ⚠️ Nếu vẫn lỗi, kiểm tra quyền thư mục: chmod 777 uploads/products
    </p>
</div>

</body>
</html>