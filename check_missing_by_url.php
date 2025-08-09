<?php
/**
 * Check và upload theo URL cụ thể
 * File: /tktshop/check_missing_by_url.php
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
    $imageType = $_POST['image_type']; // main hoặc album
    
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
                $message = "✅ Upload ảnh chính thành công cho sản phẩm ID $productId";
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
                $message = "✅ Thêm ảnh album thành công cho sản phẩm ID $productId";
            }
        } else {
            $message = "❌ Lỗi upload file!";
        }
    } else {
        $message = "❌ Lỗi file!";
    }
}

// Danh sách URL cần check
$checkUrls = [
    'products.php?new=1' => 'Sản phẩm mới',
    'products.php?category=1' => 'Danh mục: Giày thể thao', 
    'products.php?category=5' => 'Danh mục: Giày thể thao nam',
    'products.php?featured=1' => 'Sản phẩm nổi bật',
    'products.php?brand=Adidas' => 'Thương hiệu: Adidas',
    'products.php?brand=Converse' => 'Thương hiệu: Converse',
    'products.php?brand=Vans' => 'Thương hiệu: Vans',
    'products.php?brand=Puma' => 'Thương hiệu: Puma'
];

// Function để lấy sản phẩm theo điều kiện
function getProductsByCondition($pdo, $url) {
    $sql = "SELECT sp.id, sp.ten_san_pham, sp.thuong_hieu, sp.hinh_anh_chinh, sp.album_hinh_anh, 
                   sp.san_pham_noi_bat, sp.san_pham_moi, sp.danh_muc_id
            FROM san_pham_chinh sp";
    
    $where = [];
    $params = [];
    
    if (strpos($url, 'new=1') !== false) {
        $where[] = "sp.san_pham_moi = 1";
    }
    
    if (strpos($url, 'featured=1') !== false) {
        $where[] = "sp.san_pham_noi_bat = 1";
    }
    
    if (strpos($url, 'category=1') !== false) {
        $where[] = "sp.danh_muc_id = 1";
    }
    
    if (strpos($url, 'category=5') !== false) {
        $where[] = "sp.danh_muc_id = 5";
    }
    
    if (strpos($url, 'brand=Adidas') !== false) {
        $where[] = "sp.thuong_hieu = 'Adidas'";
    }
    
    if (strpos($url, 'brand=Converse') !== false) {
        $where[] = "sp.thuong_hieu = 'Converse'";
    }
    
    if (strpos($url, 'brand=Vans') !== false) {
        $where[] = "sp.thuong_hieu = 'Vans'";
    }
    
    if (strpos($url, 'brand=Puma') !== false) {
        $where[] = "sp.thuong_hieu = 'Puma'";
    }
    
    if (!empty($where)) {
        $sql .= " WHERE " . implode(' AND ', $where);
    }
    
    $sql .= " ORDER BY sp.id";
    
    return $pdo->query($sql)->fetchAll(PDO::FETCH_ASSOC);
}

// Kiểm tra từng URL
$urlResults = [];
foreach ($checkUrls as $url => $title) {
    $products = getProductsByCondition($pdo, $url);
    $missingImages = [];
    
    foreach ($products as $product) {
        $issues = [];
        
        // Check ảnh chính
        if (empty($product['hinh_anh_chinh']) || !file_exists('uploads/products/' . $product['hinh_anh_chinh'])) {
            $issues[] = 'Thiếu ảnh chính';
        }
        
        // Check album
        $albumImages = $product['album_hinh_anh'] ? json_decode($product['album_hinh_anh'], true) : [];
        $validAlbumCount = 0;
        if ($albumImages) {
            foreach ($albumImages as $img) {
                if (file_exists('uploads/products/' . $img)) $validAlbumCount++;
            }
        }
        
        if ($validAlbumCount == 0) {
            $issues[] = 'Thiếu album';
        }
        
        if (!empty($issues)) {
            $missingImages[] = [
                'product' => $product,
                'issues' => $issues,
                'album_count' => $validAlbumCount
            ];
        }
    }
    
    $urlResults[$url] = [
        'title' => $title,
        'total_products' => count($products),
        'missing_images' => $missingImages,
        'missing_count' => count($missingImages)
    ];
}
?>
<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Check Missing Images By URL</title>
    <style>
        body { font-family: Arial, sans-serif; margin: 20px; line-height: 1.6; }
        .container { max-width: 1200px; margin: 0 auto; }
        .url-section { border: 2px solid #007bff; margin: 20px 0; padding: 20px; border-radius: 8px; }
        .url-section.has-issues { border-color: #dc3545; background: #fff5f5; }
        .url-section.no-issues { border-color: #28a745; background: #f8fff8; }
        .url-header { margin: 0 0 15px 0; color: #007bff; }
        .url-section.has-issues .url-header { color: #dc3545; }
        .url-section.no-issues .url-header { color: #28a745; }
        .product-missing { border: 1px solid #ffc107; background: #fffbf0; padding: 15px; margin: 10px 0; border-radius: 4px; }
        .upload-form { background: #e3f2fd; padding: 15px; margin: 10px 0; border-radius: 4px; }
        .btn { background: #007bff; color: white; padding: 10px 20px; border: none; border-radius: 4px; cursor: pointer; margin: 5px; }
        .btn-success { background: #28a745; }
        .btn-danger { background: #dc3545; }
        .btn:hover { opacity: 0.9; }
        .message { padding: 15px; margin: 10px 0; border-radius: 4px; background: #d4edda; color: #155724; }
        .stats { text-align: center; background: #f8f9fa; padding: 15px; border-radius: 8px; margin: 20px 0; }
        .preview-img { width: 50px; height: 50px; object-fit: cover; margin: 2px; border: 1px solid #ddd; }
        .form-group { margin: 10px 0; }
        input[type="file"] { width: 100%; padding: 8px; }
        .missing-badge { background: #dc3545; color: white; padding: 2px 8px; border-radius: 12px; font-size: 12px; }
        .ok-badge { background: #28a745; color: white; padding: 2px 8px; border-radius: 12px; font-size: 12px; }
    </style>
</head>
<body>
<div class="container">
    <h1>🔍 CHECK ẢNH THIẾU THEO URL CỤ THỂ</h1>
    
    <?php if ($message): ?>
    <div class="message"><?= $message ?></div>
    <?php endif; ?>
    
    <div class="stats">
        <h3>📊 TỔNG QUAN</h3>
        <?php 
        $totalMissing = array_sum(array_column($urlResults, 'missing_count'));
        $totalUrls = count($urlResults);
        $urlsWithIssues = count(array_filter($urlResults, fn($r) => $r['missing_count'] > 0));
        ?>
        <p><strong>Tổng URL kiểm tra:</strong> <?= $totalUrls ?> | 
           <strong>URL có vấn đề:</strong> <?= $urlsWithIssues ?> | 
           <strong>Sản phẩm thiếu ảnh:</strong> <?= $totalMissing ?></p>
    </div>
    
    <?php foreach ($urlResults as $url => $result): ?>
    <div class="url-section <?= $result['missing_count'] > 0 ? 'has-issues' : 'no-issues' ?>">
        <h2 class="url-header">
            📄 <?= $result['title'] ?>
            <?php if ($result['missing_count'] > 0): ?>
            <span class="missing-badge"><?= $result['missing_count'] ?> thiếu ảnh</span>
            <?php else: ?>
            <span class="ok-badge">✅ OK</span>
            <?php endif; ?>
        </h2>
        
        <p><strong>URL:</strong> <code>customer/<?= $url ?></code></p>
        <p><strong>Tổng sản phẩm:</strong> <?= $result['total_products'] ?> | 
           <strong>Thiếu ảnh:</strong> <?= $result['missing_count'] ?></p>
        
        <p><a href="customer/<?= $url ?>" target="_blank" class="btn">👁️ Xem trang này</a></p>
        
        <?php if ($result['missing_count'] > 0): ?>
        <h3>🚨 SẢN PHẨM THIẾU ẢNH:</h3>
        
        <?php foreach ($result['missing_images'] as $missing): 
            $product = $missing['product'];
        ?>
        <div class="product-missing">
            <h4>🏷️ <?= htmlspecialchars($product['ten_san_pham']) ?> (ID: <?= $product['id'] ?>)</h4>
            <p><strong>Thương hiệu:</strong> <?= htmlspecialchars($product['thuong_hieu']) ?></p>
            <p><strong>Vấn đề:</strong> <span style="color: #dc3545;"><?= implode(', ', $missing['issues']) ?></span></p>
            
            <!-- Hiển thị ảnh hiện tại -->
            <div style="margin: 10px 0;">
                <strong>Ảnh hiện tại:</strong><br>
                <?php if (!empty($product['hinh_anh_chinh']) && file_exists('uploads/products/' . $product['hinh_anh_chinh'])): ?>
                <img src="uploads/products/<?= $product['hinh_anh_chinh'] ?>" class="preview-img" alt="Main">
                ✅ Có ảnh chính
                <?php else: ?>
                ❌ Thiếu ảnh chính
                <?php endif; ?>
                
                <?php 
                $albumImages = $product['album_hinh_anh'] ? json_decode($product['album_hinh_anh'], true) : [];
                if ($albumImages) {
                    foreach ($albumImages as $img) {
                        if (file_exists('uploads/products/' . $img)) {
                            echo "<img src='uploads/products/$img' class='preview-img' alt='Album'>";
                        }
                    }
                }
                ?>
                <br>Album: <?= $missing['album_count'] ?> ảnh
            </div>
            
            <!-- Upload forms -->
            <?php if (in_array('Thiếu ảnh chính', $missing['issues'])): ?>
            <div class="upload-form">
                <h4>📷 UPLOAD ẢNH CHÍNH</h4>
                <form method="POST" enctype="multipart/form-data">
                    <input type="hidden" name="product_id" value="<?= $product['id'] ?>">
                    <input type="hidden" name="image_type" value="main">
                    
                    <div class="form-group">
                        <input type="file" name="image" accept="image/*" required>
                    </div>
                    
                    <button type="submit" class="btn btn-danger">
                        📤 UPLOAD ẢNH CHÍNH NGAY
                    </button>
                </form>
            </div>
            <?php endif; ?>
            
            <?php if (in_array('Thiếu album', $missing['issues'])): ?>
            <div class="upload-form">
                <h4>🖼️ THÊM ẢNH ALBUM</h4>
                <form method="POST" enctype="multipart/form-data">
                    <input type="hidden" name="product_id" value="<?= $product['id'] ?>">
                    <input type="hidden" name="image_type" value="album">
                    
                    <div class="form-group">
                        <input type="file" name="image" accept="image/*" required>
                    </div>
                    
                    <button type="submit" class="btn btn-success">
                        📤 THÊM VÀO ALBUM NGAY
                    </button>
                </form>
            </div>
            <?php endif; ?>
        </div>
        <?php endforeach; ?>
        
        <?php else: ?>
        <div style="background: #d4edda; padding: 15px; border-radius: 4px; color: #155724;">
            <h3>🎉 HOÀN HẢO!</h3>
            <p>Tất cả sản phẩm trong URL này đã có ảnh đầy đủ!</p>
        </div>
        <?php endif; ?>
    </div>
    <?php endforeach; ?>
    
    <div style="text-align: center; margin: 30px 0; padding: 20px; background: #e9ecef; border-radius: 8px;">
        <h2>🔄 SAU KHI UPLOAD XONG</h2>
        <p>Refresh lại trang này để check kết quả:</p>
        <button onclick="location.reload()" class="btn" style="font-size: 18px; padding: 15px 30px;">
            🔄 REFRESH CHECK LẠI
        </button>
    </div>
</div>
</body>
</html>