<?php
/**
 * Upload ảnh cho sản phẩm đang lỗi - ĐƠN GIẢN NHẤT
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
        $newName = time() . '_' . $productId . '.' . $extension;
        $uploadPath = 'uploads/products/' . $newName;
        
        if (move_uploaded_file($file['tmp_name'], $uploadPath)) {
            // Cập nhật ảnh chính
            $sql = "UPDATE san_pham_chinh SET hinh_anh_chinh = ? WHERE id = ?";
            $stmt = $pdo->prepare($sql);
            $stmt->execute([$newName, $productId]);
            
            // Tạo album từ ảnh vừa upload
            $albumImages = [$newName];
            $sql = "UPDATE san_pham_chinh SET album_hinh_anh = ? WHERE id = ?";
            $stmt = $pdo->prepare($sql);
            $stmt->execute([json_encode($albumImages), $productId]);
            
            $message = "✅ Upload thành công cho sản phẩm ID $productId";
        } else {
            $message = "❌ Lỗi upload!";
        }
    } else {
        $message = "❌ Lỗi file!";
    }
}

// Lấy sản phẩm đang hiển thị ở các trang bạn note
$missingProducts = [];

// 1. Sản phẩm mới (products.php?new=1)
$sql = "SELECT * FROM san_pham_chinh WHERE san_pham_moi = 1";
$newProducts = $pdo->query($sql)->fetchAll(PDO::FETCH_ASSOC);

// 2. Sản phẩm nổi bật (products.php?featured=1) 
$sql = "SELECT * FROM san_pham_chinh WHERE san_pham_noi_bat = 1";
$featuredProducts = $pdo->query($sql)->fetchAll(PDO::FETCH_ASSOC);

// 3. Thương hiệu cụ thể
$brands = ['Adidas', 'Converse', 'Vans', 'Puma'];
$brandProducts = [];
foreach ($brands as $brand) {
    $sql = "SELECT * FROM san_pham_chinh WHERE thuong_hieu = ?";
    $stmt = $pdo->prepare($sql);
    $stmt->execute([$brand]);
    $brandProducts[$brand] = $stmt->fetchAll(PDO::FETCH_ASSOC);
}

// 4. Danh mục
$sql = "SELECT * FROM san_pham_chinh WHERE danh_muc_id IN (1, 5)";
$categoryProducts = $pdo->query($sql)->fetchAll(PDO::FETCH_ASSOC);

// Gộp tất cả sản phẩm và loại bỏ trùng lặp
$allRelevantProducts = [];
$allIds = [];

foreach ($newProducts as $p) {
    if (!in_array($p['id'], $allIds)) {
        $allRelevantProducts[] = $p;
        $allIds[] = $p['id'];
    }
}

foreach ($featuredProducts as $p) {
    if (!in_array($p['id'], $allIds)) {
        $allRelevantProducts[] = $p;
        $allIds[] = $p['id'];
    }
}

foreach ($brandProducts as $brand => $products) {
    foreach ($products as $p) {
        if (!in_array($p['id'], $allIds)) {
            $allRelevantProducts[] = $p;
            $allIds[] = $p['id'];
        }
    }
}

foreach ($categoryProducts as $p) {
    if (!in_array($p['id'], $allIds)) {
        $allRelevantProducts[] = $p;
        $allIds[] = $p['id'];
    }
}

// Kiểm tra sản phẩm nào thiếu ảnh
foreach ($allRelevantProducts as $product) {
    $needsImage = false;
    
    // Kiểm tra ảnh chính
    if (empty($product['hinh_anh_chinh']) || !file_exists('uploads/products/' . $product['hinh_anh_chinh'])) {
        $needsImage = true;
    }
    
    // Kiểm tra album
    if (!$needsImage) {
        $albumImages = $product['album_hinh_anh'] ? json_decode($product['album_hinh_anh'], true) : [];
        $validAlbum = false;
        if ($albumImages) {
            foreach ($albumImages as $img) {
                if (file_exists('uploads/products/' . $img)) {
                    $validAlbum = true;
                    break;
                }
            }
        }
        if (!$validAlbum) {
            $needsImage = true;
        }
    }
    
    if ($needsImage) {
        $missingProducts[] = $product;
    }
}

?>
<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <title>Upload Ảnh Cho Sản Phẩm Đang Lỗi</title>
    <style>
        body { font-family: Arial, sans-serif; margin: 20px; }
        .container { max-width: 800px; margin: 0 auto; }
        .product { border: 3px solid #dc3545; margin: 20px 0; padding: 20px; border-radius: 8px; background: #fff5f5; }
        .upload-form { background: #fff; padding: 15px; border-radius: 4px; margin: 10px 0; }
        .btn { background: #dc3545; color: white; padding: 15px 30px; border: none; border-radius: 4px; cursor: pointer; font-size: 16px; font-weight: bold; }
        .btn:hover { background: #c82333; }
        .message { padding: 15px; margin: 10px 0; border-radius: 4px; background: #d4edda; color: #155724; font-size: 18px; text-align: center; }
        input[type="file"] { width: 100%; padding: 10px; font-size: 16px; }
        .big-title { font-size: 2.5em; font-weight: bold; text-align: center; margin: 20px 0; color: #dc3545; }
        .count { background: #dc3545; color: white; padding: 5px 15px; border-radius: 20px; }
    </style>
</head>
<body>

<div class="container">
    <div class="big-title">🔥 UPLOAD ẢNH CHO SẢN PHẨM ĐANG LỖI</div>
    
    <?php if ($message): ?>
    <div class="message"><?= $message ?></div>
    <?php endif; ?>
    
    <div style="text-align: center; margin: 20px 0; font-size: 20px;">
        <span class="count"><?= count($missingProducts) ?> SẢN PHẨM ĐANG LỖI</span>
    </div>
    
    <?php if (empty($missingProducts)): ?>
    
    <div style="background: #d4edda; padding: 30px; border-radius: 8px; text-align: center; color: #155724; font-size: 20px;">
        <h2>🎉 KHÔNG CÓ SẢN PHẨM NÀO LỖI!</h2>
        <p>Tất cả sản phẩm trong các link bạn note đều đã có ảnh!</p>
        <a href="customer/" style="background: #28a745; color: white; padding: 15px 30px; text-decoration: none; border-radius: 4px; font-size: 18px;">👁️ XEM WEBSITE</a>
    </div>
    
    <?php else: ?>
    
    <div style="background: #fff3cd; padding: 20px; margin: 20px 0; border-radius: 8px; border: 2px solid #ffc107;">
        <h3 style="margin: 0; color: #856404;">💡 HƯỚNG DẪN:</h3>
        <p style="margin: 10px 0; font-size: 16px;">
            1. Chọn ảnh từ máy của bạn<br>
            2. Click "UPLOAD NGAY"<br>
            3. Làm lần lượt cho từng sản phẩm<br>
            4. Xong hết thì check website
        </p>
    </div>
    
    <?php foreach ($missingProducts as $i => $product): ?>
    <div class="product">
        <h2 style="margin: 0 0 10px 0; color: #dc3545;">
            ⚠️ SẢN PHẨM <?= $i + 1 ?>: <?= htmlspecialchars($product['ten_san_pham']) ?>
        </h2>
        
        <div style="margin: 10px 0; font-size: 16px;">
            <strong>ID:</strong> <?= $product['id'] ?> | 
            <strong>Thương hiệu:</strong> <?= $product['thuong_hieu'] ?> |
            <strong>Mới:</strong> <?= $product['san_pham_moi'] ? 'CÓ' : 'KHÔNG' ?> |
            <strong>Nổi bật:</strong> <?= $product['san_pham_noi_bat'] ? 'CÓ' : 'KHÔNG' ?>
        </div>
        
        <div class="upload-form">
            <form method="POST" enctype="multipart/form-data">
                <input type="hidden" name="product_id" value="<?= $product['id'] ?>">
                
                <div style="margin: 15px 0;">
                    <label style="font-size: 18px; font-weight: bold; display: block; margin-bottom: 10px;">
                        📷 Chọn ảnh từ máy của bạn:
                    </label>
                    <input type="file" name="image" accept="image/*" required>
                </div>
                
                <button type="submit" class="btn">
                    📤 UPLOAD NGAY CHO SẢN PHẨM NÀY
                </button>
            </form>
        </div>
    </div>
    <?php endforeach; ?>
    
    <div style="text-align: center; margin: 30px 0; padding: 20px; background: #e9ecef; border-radius: 8px;">
        <h3>🔗 SAU KHI UPLOAD XONG HẾT</h3>
        <a href="customer/products.php?new=1" target="_blank" style="background: #007bff; color: white; padding: 15px 30px; text-decoration: none; border-radius: 4px; margin: 10px; display: inline-block;">🆕 Sản phẩm mới</a>
        <a href="customer/products.php?featured=1" target="_blank" style="background: #ffc107; color: black; padding: 15px 30px; text-decoration: none; border-radius: 4px; margin: 10px; display: inline-block;">⭐ Sản phẩm nổi bật</a>
        <a href="customer/products.php?brand=Puma" target="_blank" style="background: #6f42c1; color: white; padding: 15px 30px; text-decoration: none; border-radius: 4px; margin: 10px; display: inline-block;">🐾 Puma</a>
        <a href="customer/products.php?brand=Adidas" target="_blank" style="background: #000; color: white; padding: 15px 30px; text-decoration: none; border-radius: 4px; margin: 10px; display: inline-block;">👟 Adidas</a>
    </div>
    
    <?php endif; ?>
</div>

</body>
</html>