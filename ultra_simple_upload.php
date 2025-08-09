<?php
/**
 * Upload cực đơn giản - 1 form 1 sản phẩm
 * File: /tktshop/ultra_simple_upload.php
 */

try {
    $pdo = new PDO("mysql:host=localhost;dbname=tktshop;charset=utf8mb4", "root", "");
} catch(PDOException $e) {
    die("Lỗi database: " . $e->getMessage());
}

$message = '';
$uploaded = false;

// Xử lý upload
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_FILES['image'])) {
    $productId = $_POST['product_id'];
    $file = $_FILES['image'];
    
    if ($file['error'] == 0 && $file['size'] > 0) {
        $extension = pathinfo($file['name'], PATHINFO_EXTENSION);
        $newName = 'real_' . time() . '_' . $productId . '.' . $extension;
        $uploadPath = 'uploads/products/' . $newName;
        
        if (move_uploaded_file($file['tmp_name'], $uploadPath)) {
            // Cập nhật database
            $sql = "UPDATE san_pham_chinh SET hinh_anh_chinh = ?, album_hinh_anh = ? WHERE id = ?";
            $stmt = $pdo->prepare($sql);
            $stmt->execute([$newName, json_encode([$newName]), $productId]);
            
            $message = "✅ UPLOAD THÀNH CÔNG! File: $newName";
            $uploaded = true;
        } else {
            $message = "❌ Lỗi move file!";
        }
    } else {
        $message = "❌ Lỗi file: " . $file['error'];
    }
}

// Lấy 1 sản phẩm để upload
$productId = $_GET['id'] ?? 2; // Default Adidas
$sql = "SELECT * FROM san_pham_chinh WHERE id = ?";
$stmt = $pdo->prepare($sql);
$stmt->execute([$productId]);
$product = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$product) {
    die("Không tìm thấy sản phẩm ID: $productId");
}

?>
<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <title>Upload Cực Đơn Giản</title>
    <style>
        body { 
            font-family: Arial, sans-serif; 
            margin: 0; 
            padding: 40px; 
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            min-height: 100vh;
        }
        .container { 
            max-width: 600px; 
            margin: 0 auto; 
            background: white; 
            padding: 40px; 
            border-radius: 20px; 
            box-shadow: 0 20px 40px rgba(0,0,0,0.1);
        }
        .title { 
            font-size: 2.5em; 
            font-weight: bold; 
            text-align: center; 
            margin: 0 0 30px 0; 
            color: #333;
            text-shadow: 2px 2px 4px rgba(0,0,0,0.1);
        }
        .product-info { 
            background: #f8f9fa; 
            padding: 20px; 
            border-radius: 10px; 
            margin: 20px 0;
            border-left: 5px solid #007bff;
        }
        .current-img { 
            width: 150px; 
            height: 150px; 
            object-fit: cover; 
            border-radius: 10px; 
            box-shadow: 0 5px 15px rgba(0,0,0,0.2);
            margin: 10px auto;
            display: block;
        }
        .upload-area { 
            border: 3px dashed #007bff; 
            border-radius: 15px; 
            padding: 40px; 
            text-align: center; 
            margin: 30px 0;
            background: #f0f8ff;
            transition: all 0.3s ease;
        }
        .upload-area:hover { 
            border-color: #0056b3; 
            background: #e6f3ff;
            transform: scale(1.02);
        }
        .file-input { 
            width: 100%; 
            padding: 15px; 
            font-size: 18px; 
            border: 2px solid #007bff; 
            border-radius: 10px;
            margin: 20px 0;
        }
        .upload-btn { 
            background: linear-gradient(45deg, #28a745, #20c997);
            color: white; 
            padding: 20px 40px; 
            border: none; 
            border-radius: 50px; 
            cursor: pointer; 
            font-size: 20px; 
            font-weight: bold;
            width: 100%;
            transition: all 0.3s ease;
            text-transform: uppercase;
            letter-spacing: 1px;
        }
        .upload-btn:hover { 
            transform: translateY(-2px);
            box-shadow: 0 10px 20px rgba(40,167,69,0.3);
        }
        .message { 
            padding: 20px; 
            margin: 20px 0; 
            border-radius: 10px; 
            font-size: 18px; 
            text-align: center;
            font-weight: bold;
        }
        .message.success { 
            background: #d4edda; 
            color: #155724; 
            border: 2px solid #28a745;
        }
        .message.error { 
            background: #f8d7da; 
            color: #721c24; 
            border: 2px solid #dc3545;
        }
        .nav-links { 
            text-align: center; 
            margin: 30px 0;
        }
        .nav-link { 
            background: #007bff; 
            color: white; 
            padding: 10px 20px; 
            text-decoration: none; 
            border-radius: 25px; 
            margin: 5px;
            display: inline-block;
            transition: all 0.3s ease;
        }
        .nav-link:hover { 
            background: #0056b3; 
            text-decoration: none; 
            color: white;
            transform: translateY(-2px);
        }
        .product-selector {
            background: #fff3cd;
            padding: 20px;
            border-radius: 10px;
            margin: 20px 0;
            text-align: center;
        }
    </style>
</head>
<body>

<div class="container">
    <div class="title">📤 UPLOAD ẢNH CỰC ĐƠN GIẢN</div>
    
    <?php if ($message): ?>
    <div class="message <?= $uploaded ? 'success' : 'error' ?>"><?= $message ?></div>
    <?php endif; ?>
    
    <!-- Chọn sản phẩm khác -->
    <div class="product-selector">
        <h3 style="margin: 0 0 15px 0;">🎯 Chọn sản phẩm để upload:</h3>
        <a href="?id=2" class="nav-link <?= $productId == 2 ? 'active' : '' ?>">👟 Adidas (ID: 2)</a>
        <a href="?id=3" class="nav-link <?= $productId == 3 ? 'active' : '' ?>">⚪ Converse (ID: 3)</a>
        <a href="?id=4" class="nav-link <?= $productId == 4 ? 'active' : '' ?>">🛹 Vans (ID: 4)</a>
        <a href="?id=5" class="nav-link <?= $productId == 5 ? 'active' : '' ?>">🐾 Puma (ID: 5)</a>
    </div>
    
    <!-- Thông tin sản phẩm hiện tại -->
    <div class="product-info">
        <h2 style="margin: 0 0 15px 0; color: #007bff;">
            🏷️ <?= htmlspecialchars($product['ten_san_pham']) ?>
        </h2>
        <p><strong>Thương hiệu:</strong> <?= $product['thuong_hieu'] ?></p>
        <p><strong>ID:</strong> <?= $product['id'] ?></p>
        
        <div style="text-align: center; margin: 20px 0;">
            <strong>Ảnh hiện tại:</strong><br>
            <?php if (!empty($product['hinh_anh_chinh']) && file_exists('uploads/products/' . $product['hinh_anh_chinh'])): ?>
            <img src="uploads/products/<?= $product['hinh_anh_chinh'] ?>" class="current-img" alt="Current">
            <br><small><?= $product['hinh_anh_chinh'] ?></small>
            <?php else: ?>
            <div style="background: #f8d7da; padding: 20px; border-radius: 10px; color: #721c24;">
                ❌ KHÔNG CÓ ẢNH
            </div>
            <?php endif; ?>
        </div>
    </div>
    
    <!-- Form upload -->
    <div class="upload-area">
        <h3 style="margin: 0 0 20px 0; color: #007bff;">📷 UPLOAD ẢNH MỚI</h3>
        
        <form method="POST" enctype="multipart/form-data">
            <input type="hidden" name="product_id" value="<?= $product['id'] ?>">
            
            <input type="file" 
                   name="image" 
                   accept="image/*" 
                   required 
                   class="file-input"
                   onchange="showFileName(this)">
            
            <div id="file-name" style="margin: 10px 0; font-weight: bold; color: #666;"></div>
            
            <button type="submit" class="upload-btn">
                🚀 UPLOAD NGAY LẬP TỨC
            </button>
        </form>
    </div>
    
    <!-- Links test -->
    <div class="nav-links">
        <h3>🔗 Test kết quả sau khi upload:</h3>
        <a href="customer/products.php?featured=1" target="_blank" class="nav-link">⭐ Sản phẩm nổi bật</a>
        <a href="customer/products.php?new=1" target="_blank" class="nav-link">🆕 Sản phẩm mới</a>
        <a href="customer/products.php?brand=<?= $product['thuong_hieu'] ?>" target="_blank" class="nav-link">
            🏷️ <?= $product['thuong_hieu'] ?>
        </a>
        <br><br>
        <a href="customer/" target="_blank" class="nav-link" style="background: #dc3545; font-size: 18px; padding: 15px 30px;">
            👁️ XEM WEBSITE TỔNG
        </a>
    </div>
    
    <div style="background: #e9ecef; padding: 20px; border-radius: 10px; text-align: center; margin: 30px 0;">
        <h4 style="margin: 0; color: #495057;">💡 HƯỚNG DẪN:</h4>
        <p style="margin: 10px 0; font-size: 16px;">
            1. Chọn sản phẩm ở trên<br>
            2. Chọn file ảnh từ máy<br>
            3. Click "UPLOAD NGAY LẬP TỨC"<br>
            4. Test link để xem kết quả
        </p>
    </div>
</div>

<script>
function showFileName(input) {
    const fileName = input.files[0] ? input.files[0].name : '';
    document.getElementById('file-name').innerHTML = fileName ? '📁 ' + fileName : '';
}

// Auto scroll to message
<?php if ($message): ?>
window.scrollTo(0, 0);
<?php endif; ?>
</script>

</body>
</html>