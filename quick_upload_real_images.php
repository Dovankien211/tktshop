<?php
/**
 * Upload ảnh thật - Thay placeholder
 * File: /tktshop/quick_upload_real_images.php
 */

require_once 'config/database.php';

try {
    $pdo = new PDO("mysql:host=localhost;dbname=tktshop;charset=utf8mb4", "root", "");
} catch(PDOException $e) {
    die("Lỗi database: " . $e->getMessage());
}

$message = '';

// Xử lý upload
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_FILES['image'])) {
    $productId = $_POST['product_id'];
    $file = $_FILES['image'];
    
    if ($file['error'] == 0) {
        $extension = pathinfo($file['name'], PATHINFO_EXTENSION);
        $newName = time() . '_real_' . $productId . '.' . $extension;
        $uploadPath = 'uploads/products/' . $newName;
        
        if (move_uploaded_file($file['tmp_name'], $uploadPath)) {
            // Cập nhật ảnh chính
            $sql = "UPDATE san_pham_chinh SET hinh_anh_chinh = ? WHERE id = ?";
            $stmt = $pdo->prepare($sql);
            $stmt->execute([$newName, $productId]);
            
            // Cập nhật album
            $sql = "UPDATE san_pham_chinh SET album_hinh_anh = ? WHERE id = ?";
            $stmt = $pdo->prepare($sql);
            $stmt->execute([json_encode([$newName]), $productId]);
            
            $message = "✅ Upload ảnh thật thành công cho sản phẩm ID $productId!";
        } else {
            $message = "❌ Lỗi upload!";
        }
    } else {
        $message = "❌ Lỗi file!";
    }
}

// Lấy tất cả sản phẩm
$sql = "SELECT id, ten_san_pham, thuong_hieu, hinh_anh_chinh, album_hinh_anh, san_pham_moi, san_pham_noi_bat FROM san_pham_chinh ORDER BY id";
$products = $pdo->query($sql)->fetchAll(PDO::FETCH_ASSOC);

?>
<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <title>Upload Ảnh Thật - Thay Placeholder</title>
    <style>
        body { font-family: Arial, sans-serif; margin: 20px; background: #f8f9fa; }
        .container { max-width: 1000px; margin: 0 auto; }
        .big-title { font-size: 2.5em; font-weight: bold; text-align: center; margin: 20px 0; color: #28a745; }
        .product { border: 2px solid #28a745; margin: 20px 0; padding: 20px; border-radius: 8px; background: white; }
        .upload-form { background: #e8f5e8; padding: 15px; border-radius: 4px; margin: 15px 0; }
        .btn { background: #28a745; color: white; padding: 15px 30px; border: none; border-radius: 4px; cursor: pointer; font-size: 16px; font-weight: bold; }
        .btn:hover { background: #218838; transform: scale(1.02); }
        .message { padding: 20px; margin: 20px 0; border-radius: 8px; background: #d4edda; color: #155724; font-size: 18px; text-align: center; }
        input[type="file"] { width: 100%; padding: 10px; font-size: 16px; border: 2px dashed #28a745; border-radius: 4px; }
        .current-img { width: 100px; height: 100px; object-fit: cover; border: 2px solid #ddd; border-radius: 4px; margin: 5px; }
        .placeholder-img { border: 3px dashed #ffc107 !important; }
        .links-section { background: #d1ecf1; padding: 20px; border-radius: 8px; margin: 20px 0; text-align: center; }
        .test-link { background: #007bff; color: white; padding: 10px 20px; text-decoration: none; border-radius: 4px; margin: 5px; display: inline-block; font-weight: bold; }
        .test-link:hover { background: #0056b3; text-decoration: none; color: white; }
        .product-tags { margin: 10px 0; }
        .tag { background: #007bff; color: white; padding: 2px 8px; border-radius: 12px; font-size: 12px; margin: 2px; }
        .tag.new { background: #28a745; }
        .tag.featured { background: #ffc107; color: black; }
    </style>
</head>
<body>

<div class="container">
    <div class="big-title">📤 UPLOAD ẢNH THẬT - THAY PLACEHOLDER</div>
    
    <?php if ($message): ?>
    <div class="message"><?= $message ?></div>
    <?php endif; ?>
    
    <div class="links-section">
        <h3 style="margin: 0 0 15px 0;">🔗 TEST CÁC LINK SAU KHI UPLOAD:</h3>
        <a href="customer/products.php?new=1" target="_blank" class="test-link">🆕 Sản phẩm mới</a>
        <a href="customer/products.php?featured=1" target="_blank" class="test-link">⭐ Sản phẩm nổi bật</a>
        <a href="customer/products.php?brand=Adidas" target="_blank" class="test-link">👟 Adidas</a>
        <a href="customer/products.php?brand=Vans" target="_blank" class="test-link">🛹 Vans</a>
        <a href="customer/products.php?brand=Puma" target="_blank" class="test-link">🐾 Puma</a>
        <br><br>
        <a href="customer/" target="_blank" class="test-link" style="background: #dc3545; font-size: 18px; padding: 15px 30px;">👁️ XEM WEBSITE TỔNG</a>
    </div>
    
    <div style="background: #fff3cd; padding: 20px; margin: 20px 0; border-radius: 8px; border: 2px solid #ffc107;">
        <h3 style="margin: 0; color: #856404;">💡 HƯỚNG DẪN UPLOAD:</h3>
        <ol style="margin: 10px 0; font-size: 16px;">
            <li><strong>Chọn ảnh từ máy</strong> của bạn (JPG, PNG, GIF)</li>
            <li><strong>Click "UPLOAD ẢNH THẬT"</strong></li>
            <li><strong>Ảnh sẽ thay thế</strong> ảnh placeholder hiện tại</li>
            <li><strong>Test link</strong> để xem kết quả</li>
        </ol>
    </div>
    
    <?php foreach ($products as $product): 
        // Kiểm tra có phải ảnh placeholder không
        $isPlaceholder = false;
        if (!empty($product['hinh_anh_chinh'])) {
            if (strpos($product['hinh_anh_chinh'], 'temp_product_') !== false || 
                strpos($product['hinh_anh_chinh'], 'placeholder_') !== false) {
                $isPlaceholder = true;
            }
        }
    ?>
    <div class="product">
        <h2 style="margin: 0 0 10px 0; color: #28a745;">
            🏷️ <?= htmlspecialchars($product['ten_san_pham']) ?> (ID: <?= $product['id'] ?>)
            <?= $isPlaceholder ? '⚠️ PLACEHOLDER' : '✅ ẢNH THẬT' ?>
        </h2>
        
        <div style="margin: 10px 0;">
            <strong>Thương hiệu:</strong> <?= $product['thuong_hieu'] ?>
        </div>
        
        <div class="product-tags">
            <?php if ($product['san_pham_moi']): ?>
            <span class="tag new">MỚI</span>
            <?php endif; ?>
            <?php if ($product['san_pham_noi_bat']): ?>
            <span class="tag featured">NỔI BẬT</span>
            <?php endif; ?>
        </div>
        
        <!-- Hiển thị ảnh hiện tại -->
        <div style="margin: 15px 0;">
            <strong>Ảnh hiện tại:</strong><br>
            <?php if (!empty($product['hinh_anh_chinh']) && file_exists('uploads/products/' . $product['hinh_anh_chinh'])): ?>
            <img src="uploads/products/<?= $product['hinh_anh_chinh'] ?>" 
                 class="current-img <?= $isPlaceholder ? 'placeholder-img' : '' ?>" 
                 alt="Current image">
            <br><small><?= $product['hinh_anh_chinh'] ?></small>
            <?php else: ?>
            <div style="background: #f8d7da; padding: 10px; border-radius: 4px; color: #721c24;">
                ❌ Không có ảnh
            </div>
            <?php endif; ?>
        </div>
        
        <!-- Form upload -->
        <div class="upload-form">
            <h4 style="margin: 0 0 15px 0; color: #155724;">📷 UPLOAD ẢNH THẬT CHO SẢN PHẨM NÀY:</h4>
            
            <form method="POST" enctype="multipart/form-data">
                <input type="hidden" name="product_id" value="<?= $product['id'] ?>">
                
                <div style="margin: 15px 0;">
                    <label style="font-weight: bold; display: block; margin-bottom: 10px;">
                        Chọn ảnh từ máy của bạn:
                    </label>
                    <input type="file" name="image" accept="image/*" required>
                </div>
                
                <button type="submit" class="btn">
                    📤 UPLOAD ẢNH THẬT CHO SẢN PHẨM NÀY
                </button>
            </form>
        </div>
    </div>
    <?php endforeach; ?>
    
    <div style="text-align: center; margin: 40px 0; padding: 30px; background: #e9ecef; border-radius: 8px;">
        <h3 style="color: #495057;">🎯 SAU KHI UPLOAD XONG</h3>
        <p style="font-size: 18px; margin: 15px 0;">
            Click các link test ở trên để xem kết quả!<br>
            Website sẽ hiển thị ảnh thật thay vì ảnh placeholder.
        </p>
        <a href="customer/" target="_blank" style="background: #28a745; color: white; padding: 20px 40px; text-decoration: none; border-radius: 8px; font-size: 20px; font-weight: bold;">
            👁️ XEM WEBSITE
        </a>
    </div>
</div>

</body>
</html>