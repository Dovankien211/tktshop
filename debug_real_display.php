<?php
/**
 * Debug hiển thị ảnh thực tế - Kiểm tra chính xác vấn đề
 * File: /tktshop/debug_real_display.php
 */

require_once 'config/database.php';

try {
    $pdo = new PDO("mysql:host=localhost;dbname=tktshop;charset=utf8mb4", "root", "");
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
} catch(PDOException $e) {
    die("Lỗi database: " . $e->getMessage());
}

$message = '';

// Xử lý upload
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_FILES['image'])) {
    $productId = $_POST['product_id'];
    $imageType = $_POST['image_type'];
    
    $file = $_FILES['image'];
    
    if ($file['error'] == 0) {
        $extension = pathinfo($file['name'], PATHINFO_EXTENSION);
        $newName = time() . '_' . uniqid() . '.' . $extension;
        $uploadPath = 'uploads/products/' . $newName;
        
        if (move_uploaded_file($file['tmp_name'], $uploadPath)) {
            if ($imageType == 'main') {
                $sql = "UPDATE san_pham_chinh SET hinh_anh_chinh = ? WHERE id = ?";
                $stmt = $pdo->prepare($sql);
                $stmt->execute([$newName, $productId]);
                $message = "✅ Upload ảnh chính thành công: $newName";
            } else {
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
    }
}

// Lấy sản phẩm Puma cụ thể
$sql = "SELECT sp.*, dm.ten_danh_muc 
        FROM san_pham_chinh sp 
        LEFT JOIN danh_muc_giay dm ON sp.danh_muc_id = dm.id 
        WHERE sp.thuong_hieu = 'Puma'
        ORDER BY sp.id";
$pumaProducts = $pdo->query($sql)->fetchAll(PDO::FETCH_ASSOC);

// Kiểm tra tất cả sản phẩm có tags
$sql = "SELECT sp.*, dm.ten_danh_muc 
        FROM san_pham_chinh sp 
        LEFT JOIN danh_muc_giay dm ON sp.danh_muc_id = dm.id 
        ORDER BY sp.id";
$allProducts = $pdo->query($sql)->fetchAll(PDO::FETCH_ASSOC);

// Function mô phỏng logic hiển thị ảnh của website
function getDisplayImage($product) {
    // Ưu tiên: album_hinh_anh trước, rồi mới đến hinh_anh_chinh
    if (!empty($product['album_hinh_anh'])) {
        $albumImages = json_decode($product['album_hinh_anh'], true);
        if ($albumImages && is_array($albumImages)) {
            foreach ($albumImages as $img) {
                if (file_exists('uploads/products/' . $img)) {
                    return 'uploads/products/' . $img;
                }
            }
        }
    }
    
    // Nếu không có album, dùng ảnh chính
    if (!empty($product['hinh_anh_chinh']) && file_exists('uploads/products/' . $product['hinh_anh_chinh'])) {
        return 'uploads/products/' . $product['hinh_anh_chinh'];
    }
    
    return null; // No image available
}

?>
<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Debug Hiển Thị Ảnh Thực Tế</title>
    <style>
        body { font-family: Arial, sans-serif; margin: 20px; line-height: 1.6; }
        .container { max-width: 1200px; margin: 0 auto; }
        .product-card { border: 2px solid #ddd; margin: 20px 0; padding: 20px; border-radius: 8px; }
        .product-card.no-image { border-color: #dc3545; background: #fff5f5; }
        .product-card.has-image { border-color: #28a745; background: #f8fff8; }
        .message { padding: 15px; margin: 10px 0; border-radius: 4px; background: #d4edda; color: #155724; }
        .btn { background: #007bff; color: white; padding: 10px 20px; border: none; border-radius: 4px; cursor: pointer; margin: 5px; }
        .btn-danger { background: #dc3545; }
        .btn-success { background: #28a745; }
        .btn:hover { opacity: 0.9; }
        .upload-form { background: #ffe6e6; padding: 15px; margin: 10px 0; border-radius: 4px; border: 2px dashed #dc3545; }
        .form-group { margin: 10px 0; }
        input[type="file"] { width: 100%; padding: 8px; }
        .data-table { background: #f8f9fa; padding: 15px; margin: 10px 0; border-radius: 4px; font-family: monospace; }
        .preview-img { width: 100px; height: 100px; object-fit: cover; margin: 5px; border: 2px solid #ddd; }
        .no-image-placeholder { width: 100px; height: 100px; background: #eee; border: 2px dashed #ccc; display: flex; align-items: center; justify-content: center; color: #999; font-size: 12px; text-align: center; }
        .section { margin: 30px 0; padding: 20px; border: 1px solid #ddd; border-radius: 8px; }
        .stats { background: #e9ecef; padding: 15px; border-radius: 8px; text-align: center; }
    </style>
</head>
<body>
<div class="container">
    <h1>🔍 DEBUG HIỂN THỊ ẢNH THỰC TẾ</h1>
    
    <?php if ($message): ?>
    <div class="message"><?= $message ?></div>
    <?php endif; ?>
    
    <div class="stats">
        <h3>📊 THỐNG KÊ NHANH</h3>
        <p><strong>Tổng sản phẩm:</strong> <?= count($allProducts) ?> | 
           <strong>Sản phẩm Puma:</strong> <?= count($pumaProducts) ?></p>
    </div>
    
    <!-- SECTION 1: SẢN PHẨM PUMA -->
    <div class="section">
        <h2>🔥 SẢN PHẨM PUMA (Đang "No Image Available")</h2>
        
        <?php foreach ($pumaProducts as $product): 
            $displayImage = getDisplayImage($product);
            $hasImage = $displayImage !== null;
        ?>
        <div class="product-card <?= $hasImage ? 'has-image' : 'no-image' ?>">
            <h3>
                🏷️ <?= htmlspecialchars($product['ten_san_pham']) ?> (ID: <?= $product['id'] ?>)
                <?= $hasImage ? '✅ CÓ ẢNH' : '❌ NO IMAGE' ?>
            </h3>
            
            <div style="display: flex; gap: 20px; margin: 15px 0;">
                <!-- Preview ảnh hiển thị -->
                <div>
                    <strong>Ảnh website sẽ hiển thị:</strong><br>
                    <?php if ($hasImage): ?>
                    <img src="<?= $displayImage ?>" class="preview-img" alt="Display image">
                    <br><small><?= basename($displayImage) ?></small>
                    <?php else: ?>
                    <div class="no-image-placeholder">No Image Available</div>
                    <?php endif; ?>
                </div>
                
                <!-- Thông tin chi tiết -->
                <div class="data-table">
                    <strong>Chi tiết database:</strong><br>
                    <strong>Thương hiệu:</strong> <?= $product['thuong_hieu'] ?><br>
                    <strong>Danh mục:</strong> <?= $product['ten_danh_muc'] ?><br>
                    <strong>Sản phẩm mới:</strong> <?= $product['san_pham_moi'] ? 'CÓ' : 'KHÔNG' ?><br>
                    <strong>Nổi bật:</strong> <?= $product['san_pham_noi_bat'] ? 'CÓ' : 'KHÔNG' ?><br>
                    <strong>Ảnh chính:</strong> <?= $product['hinh_anh_chinh'] ?: 'RỖNG' ?><br>
                    <strong>File tồn tại:</strong> 
                    <?php if (!empty($product['hinh_anh_chinh'])): ?>
                        <?= file_exists('uploads/products/' . $product['hinh_anh_chinh']) ? 'CÓ' : 'KHÔNG' ?>
                    <?php else: ?>
                        N/A
                    <?php endif; ?><br>
                    
                    <strong>Album:</strong><br>
                    <?php 
                    if (!empty($product['album_hinh_anh'])) {
                        $albumImages = json_decode($product['album_hinh_anh'], true);
                        if ($albumImages) {
                            foreach ($albumImages as $i => $img) {
                                $exists = file_exists('uploads/products/' . $img);
                                echo "- $img: " . ($exists ? 'CÓ' : 'KHÔNG') . "<br>";
                            }
                        } else {
                            echo "JSON lỗi<br>";
                        }
                    } else {
                        echo "RỖNG<br>";
                    }
                    ?>
                </div>
            </div>
            
            <!-- Upload forms nếu thiếu ảnh -->
            <?php if (!$hasImage): ?>
            <div class="upload-form">
                <h4>⚠️ UPLOAD NGAY ĐỂ FIX "NO IMAGE"</h4>
                
                <!-- Upload ảnh chính -->
                <div style="margin: 15px 0;">
                    <strong>📷 Upload ảnh chính:</strong>
                    <form method="POST" enctype="multipart/form-data" style="display: inline-block; margin-left: 10px;">
                        <input type="hidden" name="product_id" value="<?= $product['id'] ?>">
                        <input type="hidden" name="image_type" value="main">
                        <input type="file" name="image" accept="image/*" required style="width: 300px; display: inline;">
                        <button type="submit" class="btn btn-danger">📤 UPLOAD ẢNH CHÍNH</button>
                    </form>
                </div>
                
                <!-- Upload album -->
                <div style="margin: 15px 0;">
                    <strong>🖼️ Thêm vào album:</strong>
                    <form method="POST" enctype="multipart/form-data" style="display: inline-block; margin-left: 10px;">
                        <input type="hidden" name="product_id" value="<?= $product['id'] ?>">
                        <input type="hidden" name="image_type" value="album">
                        <input type="file" name="image" accept="image/*" required style="width: 300px; display: inline;">
                        <button type="submit" class="btn btn-success">📤 THÊM VÀO ALBUM</button>
                    </form>
                </div>
            </div>
            <?php endif; ?>
        </div>
        <?php endforeach; ?>
    </div>
    
    <!-- SECTION 2: TẤT CẢ SẢN PHẨM -->
    <div class="section">
        <h2>📋 TẤT CẢ SẢN PHẨM - KIỂM TRA HIỂN THỊ</h2>
        
        <?php 
        $noImageProducts = [];
        foreach ($allProducts as $product) {
            $displayImage = getDisplayImage($product);
            if ($displayImage === null) {
                $noImageProducts[] = $product;
            }
        }
        ?>
        
        <div class="stats">
            <h4>📊 TỔNG KẾT</h4>
            <p><strong>Tổng sản phẩm:</strong> <?= count($allProducts) ?></p>
            <p><strong>Sản phẩm "No Image":</strong> <?= count($noImageProducts) ?></p>
            <p><strong>Sản phẩm có ảnh:</strong> <?= count($allProducts) - count($noImageProducts) ?></p>
        </div>
        
        <?php if (!empty($noImageProducts)): ?>
        <h3>🚨 DANH SÁCH TẤT CẢ SẢN PHẨM "NO IMAGE":</h3>
        
        <?php foreach ($noImageProducts as $product): ?>
        <div style="background: #fff5f5; border: 1px solid #dc3545; padding: 10px; margin: 5px 0; border-radius: 4px;">
            <strong>ID <?= $product['id'] ?>:</strong> <?= htmlspecialchars($product['ten_san_pham']) ?> 
            (<?= $product['thuong_hieu'] ?>)
            
            <!-- Quick upload -->
            <div style="margin-top: 10px;">
                <form method="POST" enctype="multipart/form-data" style="display: inline;">
                    <input type="hidden" name="product_id" value="<?= $product['id'] ?>">
                    <input type="hidden" name="image_type" value="main">
                    <input type="file" name="image" accept="image/*" required style="width: 200px; display: inline; margin-right: 10px;">
                    <button type="submit" class="btn btn-danger" style="padding: 5px 10px;">📤 UPLOAD</button>
                </form>
            </div>
        </div>
        <?php endforeach; ?>
        
        <?php else: ?>
        <div style="background: #d4edda; padding: 15px; border-radius: 4px; color: #155724;">
            <h3>🎉 HOÀN HẢO!</h3>
            <p>Tất cả sản phẩm đều có ảnh hiển thị!</p>
        </div>
        <?php endif; ?>
    </div>
    
    <!-- Links -->
    <div style="text-align: center; margin: 30px 0; padding: 20px; background: #e9ecef; border-radius: 8px;">
        <h3>🔗 KIỂM TRA KẾT QUẢ</h3>
        <a href="customer/products.php?brand=Puma" target="_blank" class="btn" style="font-size: 16px; padding: 12px 24px;">
            👁️ XEM TRANG PUMA
        </a>
        <br><br>
        <button onclick="location.reload()" class="btn btn-success">🔄 REFRESH DEBUG</button>
    </div>
</div>
</body>
</html>