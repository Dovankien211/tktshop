<?php
/**
 * Force Fix tất cả links lỗi - Không cần nghĩ gì
 * File: /tktshop/force_fix_all.php
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
    }
}

// Xử lý force fix
if (isset($_POST['force_fix'])) {
    try {
        // FORCE FIX 1: Đảm bảo tất cả sản phẩm có tags đúng
        
        // Reset tất cả trước
        $pdo->exec("UPDATE san_pham_chinh SET san_pham_moi = 0, san_pham_noi_bat = 0");
        
        // Set sản phẩm MỚI: Adidas (ID 2), Puma (ID 5) 
        $pdo->exec("UPDATE san_pham_chinh SET san_pham_moi = 1 WHERE id IN (2, 5)");
        
        // Set sản phẩm NỔI BẬT: Adidas (ID 2), Converse (ID 3), Vans (ID 4)
        $pdo->exec("UPDATE san_pham_chinh SET san_pham_noi_bat = 1 WHERE id IN (2, 3, 4)");
        
        // FORCE FIX 2: Đảm bảo danh mục đúng
        $pdo->exec("UPDATE san_pham_chinh SET danh_muc_id = 5 WHERE thuong_hieu = 'Adidas'"); // Giày thể thao nam
        $pdo->exec("UPDATE san_pham_chinh SET danh_muc_id = 1 WHERE thuong_hieu IN ('Converse', 'Vans', 'Puma')"); // Giày thể thao
        
        // FORCE FIX 3: Tạo ảnh cho những sản phẩm thiếu (nếu có)
        $products = $pdo->query("SELECT id, ten_san_pham, hinh_anh_chinh, album_hinh_anh FROM san_pham_chinh")->fetchAll(PDO::FETCH_ASSOC);
        $fixedCount = 0;
        
        foreach ($products as $product) {
            $needsFix = false;
            
            // Kiểm tra ảnh chính
            if (empty($product['hinh_anh_chinh']) || !file_exists('uploads/products/' . $product['hinh_anh_chinh'])) {
                $needsFix = true;
            }
            
            // Kiểm tra album
            $hasValidAlbum = false;
            if (!empty($product['album_hinh_anh'])) {
                $albumImages = json_decode($product['album_hinh_anh'], true);
                if ($albumImages) {
                    foreach ($albumImages as $img) {
                        if (file_exists('uploads/products/' . $img)) {
                            $hasValidAlbum = true;
                            break;
                        }
                    }
                }
            }
            
            if (!$hasValidAlbum) {
                $needsFix = true;
            }
            
            // Tạo ảnh placeholder nếu cần
            if ($needsFix) {
                $placeholderName = "temp_product_{$product['id']}.png";
                $placeholderPath = "uploads/products/$placeholderName";
                
                if (createPlaceholder($placeholderPath, $product['ten_san_pham'])) {
                    // Cập nhật ảnh chính
                    $sql = "UPDATE san_pham_chinh SET hinh_anh_chinh = ? WHERE id = ?";
                    $stmt = $pdo->prepare($sql);
                    $stmt->execute([$placeholderName, $product['id']]);
                    
                    // Cập nhật album
                    $sql = "UPDATE san_pham_chinh SET album_hinh_anh = ? WHERE id = ?";
                    $stmt = $pdo->prepare($sql);
                    $stmt->execute([json_encode([$placeholderName]), $product['id']]);
                    
                    $fixedCount++;
                }
            }
        }
        
        $message = "✅ FORCE FIX thành công! Đã sửa tags, danh mục và tạo $fixedCount ảnh placeholder!";
        
    } catch (Exception $e) {
        $message = "❌ Lỗi: " . $e->getMessage();
    }
}

// Function tạo ảnh placeholder đơn giản
function createPlaceholder($filePath, $productName) {
    if (!extension_loaded('gd')) {
        return false;
    }
    
    $width = 400;
    $height = 400;
    
    $image = imagecreate($width, $height);
    $bgColor = imagecolorallocate($image, 240, 240, 240);
    $textColor = imagecolorallocate($image, 100, 100, 100);
    
    // Vẽ text
    $text = strtoupper(substr($productName, 0, 15));
    imagestring($image, 5, 50, $height/2 - 20, $text, $textColor);
    imagestring($image, 3, 50, $height/2 + 20, date('Y-m-d'), $textColor);
    
    $result = imagepng($image, $filePath);
    imagedestroy($image);
    
    return $result;
}

// Lấy tất cả sản phẩm
$allProducts = $pdo->query("SELECT * FROM san_pham_chinh ORDER BY id")->fetchAll(PDO::FETCH_ASSOC);

// Phân tích vấn đề
$issues = [];
foreach ($allProducts as $product) {
    $productIssues = [];
    
    // Kiểm tra ảnh
    if (empty($product['hinh_anh_chinh']) || !file_exists('uploads/products/' . $product['hinh_anh_chinh'])) {
        $productIssues[] = 'Thiếu ảnh chính';
    }
    
    $hasValidAlbum = false;
    if (!empty($product['album_hinh_anh'])) {
        $albumImages = json_decode($product['album_hinh_anh'], true);
        if ($albumImages) {
            foreach ($albumImages as $img) {
                if (file_exists('uploads/products/' . $img)) {
                    $hasValidAlbum = true;
                    break;
                }
            }
        }
    }
    if (!$hasValidAlbum) {
        $productIssues[] = 'Thiếu album';
    }
    
    if (!empty($productIssues)) {
        $issues[] = [
            'product' => $product,
            'issues' => $productIssues
        ];
    }
}

?>
<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <title>Force Fix Tất Cả Links Lỗi</title>
    <style>
        body { font-family: Arial, sans-serif; margin: 20px; background: #f8f9fa; }
        .container { max-width: 1000px; margin: 0 auto; }
        .big-title { font-size: 2.5em; font-weight: bold; text-align: center; margin: 20px 0; color: #dc3545; }
        .force-fix-btn { background: #dc3545; color: white; padding: 20px 40px; border: none; border-radius: 8px; cursor: pointer; font-size: 20px; font-weight: bold; margin: 20px; }
        .force-fix-btn:hover { background: #c82333; transform: scale(1.05); }
        .message { padding: 20px; margin: 20px 0; border-radius: 8px; background: #d4edda; color: #155724; font-size: 18px; text-align: center; }
        .links-section { background: #fff3cd; padding: 20px; border-radius: 8px; margin: 20px 0; border: 2px solid #ffc107; }
        .test-link { background: #007bff; color: white; padding: 10px 20px; text-decoration: none; border-radius: 4px; margin: 5px; display: inline-block; }
        .test-link:hover { background: #0056b3; text-decoration: none; color: white; }
        .product-item { border: 2px solid #dc3545; margin: 15px 0; padding: 15px; border-radius: 8px; background: #fff5f5; }
        .upload-form { background: #e3f2fd; padding: 15px; margin: 10px 0; border-radius: 4px; }
        .btn { background: #28a745; color: white; padding: 10px 20px; border: none; border-radius: 4px; cursor: pointer; }
        .btn:hover { background: #218838; }
        input[type="file"] { width: 100%; padding: 8px; margin: 10px 0; }
        .stats { background: white; padding: 20px; border-radius: 8px; text-align: center; margin: 20px 0; border: 1px solid #dee2e6; }
    </style>
</head>
<body>

<div class="container">
    <div class="big-title">🔥 FORCE FIX TẤT CẢ LINKS LỖI</div>
    
    <?php if ($message): ?>
    <div class="message"><?= $message ?></div>
    <?php endif; ?>
    
    <div class="stats">
        <h3>📊 TÌNH HÌNH HIỆN TẠI</h3>
        <p><strong>Tổng sản phẩm:</strong> <?= count($allProducts) ?></p>
        <p><strong>Sản phẩm có vấn đề:</strong> <?= count($issues) ?></p>
        <p><strong>Sản phẩm OK:</strong> <?= count($allProducts) - count($issues) ?></p>
    </div>
    
    <!-- FORCE FIX BUTTON -->
    <div style="text-align: center; margin: 30px 0; padding: 30px; background: #fff; border-radius: 8px; border: 3px solid #dc3545;">
        <h2 style="color: #dc3545; margin: 0 0 20px 0;">⚡ FORCE FIX TẤT CẢ</h2>
        <p style="font-size: 16px; margin: 10px 0;">
            Sẽ tự động fix:<br>
            ✅ Set sản phẩm mới/nổi bật<br>
            ✅ Sửa danh mục<br>
            ✅ Tạo ảnh placeholder cho sản phẩm thiếu<br>
        </p>
        
        <form method="POST" style="display: inline;">
            <button type="submit" name="force_fix" class="force-fix-btn" 
                    onclick="return confirm('FORCE FIX tất cả? Hành động này sẽ thay đổi database!')">
                🚀 FORCE FIX NGAY LẬP TỨC
            </button>
        </form>
    </div>
    
    <!-- TEST LINKS -->
    <div class="links-section">
        <h3 style="margin: 0 0 15px 0; color: #856404;">🔗 TEST CÁC LINK SAU KHI FIX:</h3>
        <a href="customer/products.php?new=1" target="_blank" class="test-link">🆕 Sản phẩm mới</a>
        <a href="customer/products.php?new=1&category=1" target="_blank" class="test-link">🆕 Mới + Danh mục 1</a>
        <a href="customer/products.php?new=1&category=5" target="_blank" class="test-link">🆕 Mới + Danh mục 5</a>
        <a href="customer/products.php?featured=1" target="_blank" class="test-link">⭐ Sản phẩm nổi bật</a>
        <a href="customer/products.php?brand=Adidas" target="_blank" class="test-link">👟 Adidas</a>
        <a href="customer/products.php?brand=Vans" target="_blank" class="test-link">🛹 Vans</a>
        <a href="customer/products.php?brand=Puma" target="_blank" class="test-link">🐾 Puma</a>
    </div>
    
    <?php if (!empty($issues)): ?>
    <!-- SẢN PHẨM CẦN UPLOAD ẢNH -->
    <h2 style="color: #dc3545;">📤 HOẶC UPLOAD ẢNH THẬT TỪ MÁY CỦA BẠN:</h2>
    
    <?php foreach ($issues as $issue): 
        $product = $issue['product'];
    ?>
    <div class="product-item">
        <h3 style="margin: 0 0 10px 0;">
            🏷️ <?= htmlspecialchars($product['ten_san_pham']) ?> (ID: <?= $product['id'] ?>)
        </h3>
        <p><strong>Thương hiệu:</strong> <?= $product['thuong_hieu'] ?> | 
           <strong>Vấn đề:</strong> <span style="color: #dc3545;"><?= implode(', ', $issue['issues']) ?></span></p>
        
        <div class="upload-form">
            <form method="POST" enctype="multipart/form-data">
                <input type="hidden" name="product_id" value="<?= $product['id'] ?>">
                
                <label style="font-weight: bold; display: block;">📷 Chọn ảnh từ máy:</label>
                <input type="file" name="image" accept="image/*" required>
                
                <button type="submit" class="btn">📤 UPLOAD ẢNH CHO SẢN PHẨM NÀY</button>
            </form>
        </div>
    </div>
    <?php endforeach; ?>
    
    <?php endif; ?>
    
    <div style="text-align: center; margin: 40px 0; padding: 20px; background: #e9ecef; border-radius: 8px;">
        <h3>🎯 HƯỚNG DẪN:</h3>
        <ol style="text-align: left; display: inline-block;">
            <li><strong>Click "FORCE FIX NGAY LẬP TỨC"</strong> để tự động fix tất cả</li>
            <li><strong>Hoặc upload ảnh thật</strong> từ máy của bạn cho từng sản phẩm</li>
            <li><strong>Test các link</strong> ở phần "TEST CÁC LINK" để kiểm tra</li>
        </ol>
    </div>
</div>

</body>
</html>