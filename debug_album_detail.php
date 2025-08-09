<?php
/**
 * Debug chi tiết album ảnh - TKTShop
 * File: /tktshop/debug_album_detail.php
 */

require_once 'config/database.php';

try {
    $pdo = new PDO("mysql:host=localhost;dbname=tktshop;charset=utf8mb4", "root", "");
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
} catch(PDOException $e) {
    die("Lỗi database: " . $e->getMessage());
}

?>
<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Debug Chi Tiết Album - TKTShop</title>
    <style>
        body { font-family: Arial, sans-serif; margin: 20px; }
        .product { border: 1px solid #ddd; margin: 10px 0; padding: 15px; }
        .exists { background: #e8f5e8; }
        .missing { background: #ffebee; }
        .db-data { background: #f5f5f5; padding: 10px; margin: 5px 0; font-family: monospace; }
        .file-list { margin: 10px 0; }
        .file-item { display: inline-block; margin: 5px; padding: 5px; border: 1px solid #ccc; }
        .file-exists { background: #c8e6c9; }
        .file-missing { background: #ffcdd2; }
        img { max-width: 100px; max-height: 100px; margin: 2px; }
        .actions { margin: 20px 0; padding: 15px; background: #e3f2fd; }
    </style>
</head>
<body>
    <h1>🔍 Debug Chi Tiết Album Ảnh</h1>
    
    <?php
    // Lấy tất cả sản phẩm
    $products = $pdo->query("
        SELECT id, ten_san_pham, thuong_hieu, hinh_anh_chinh, album_hinh_anh
        FROM san_pham_chinh 
        ORDER BY id
    ")->fetchAll(PDO::FETCH_ASSOC);
    
    echo "<h2>📊 Tổng quan:</h2>";
    echo "<ul>";
    echo "<li>Tổng sản phẩm: " . count($products) . "</li>";
    echo "<li>Thời gian check: " . date('H:i:s d/m/Y') . "</li>";
    echo "<li>Thư mục uploads/products: " . (is_dir('uploads/products') ? '✅ Tồn tại' : '❌ Không tồn tại') . "</li>";
    echo "</ul>";
    
    $totalMissing = 0;
    $totalFiles = 0;
    
    foreach ($products as $product) {
        echo "<div class='product'>";
        echo "<h3>🏷️ {$product['ten_san_pham']} (ID: {$product['id']})</h3>";
        echo "<p><strong>Thương hiệu:</strong> {$product['thuong_hieu']}</p>";
        
        // Kiểm tra ảnh chính
        echo "<h4>Ảnh chính:</h4>";
        if (!empty($product['hinh_anh_chinh'])) {
            $mainPath = 'uploads/products/' . $product['hinh_anh_chinh'];
            $mainExists = file_exists($mainPath);
            echo "<div class='file-item " . ($mainExists ? 'file-exists' : 'file-missing') . "'>";
            echo "📄 {$product['hinh_anh_chinh']} ";
            echo $mainExists ? '✅' : '❌';
            if ($mainExists) {
                echo "<br><img src='{$mainPath}' alt='Main image'>";
                echo "<br>Kích thước: " . filesize($mainPath) . " bytes";
            }
            echo "</div>";
        } else {
            echo "<div class='file-missing'>❌ Không có ảnh chính</div>";
        }
        
        // Kiểm tra album chi tiết
        echo "<h4>Album ảnh:</h4>";
        echo "<div class='db-data'>";
        echo "<strong>Dữ liệu database:</strong><br>";
        echo htmlspecialchars($product['album_hinh_anh'] ?: 'NULL');
        echo "</div>";
        
        if (!empty($product['album_hinh_anh'])) {
            $albumData = json_decode($product['album_hinh_anh'], true);
            
            if (json_last_error() !== JSON_ERROR_NONE) {
                echo "<div class='file-missing'>❌ Lỗi JSON: " . json_last_error_msg() . "</div>";
            } else {
                echo "<div class='file-list'>";
                echo "<p><strong>Số ảnh trong album:</strong> " . count($albumData) . "</p>";
                
                foreach ($albumData as $index => $imageName) {
                    $totalFiles++;
                    $imagePath = 'uploads/products/' . $imageName;
                    $imageExists = file_exists($imagePath);
                    
                    if (!$imageExists) $totalMissing++;
                    
                    echo "<div class='file-item " . ($imageExists ? 'file-exists' : 'file-missing') . "'>";
                    echo "<strong>#{$index}:</strong> {$imageName}<br>";
                    echo "Đường dẫn: {$imagePath}<br>";
                    echo "Trạng thái: " . ($imageExists ? '✅ Tồn tại' : '❌ Không tồn tại');
                    
                    if ($imageExists) {
                        echo "<br><img src='{$imagePath}' alt='Album image'>";
                        echo "<br>Kích thước: " . filesize($imagePath) . " bytes";
                        echo "<br>Cập nhật: " . date('H:i:s d/m/Y', filemtime($imagePath));
                    } else {
                        // Kiểm tra file có tên tương tự
                        $similarFiles = glob('uploads/products/' . pathinfo($imageName, PATHINFO_FILENAME) . '*');
                        if (!empty($similarFiles)) {
                            echo "<br>🔍 File tương tự: " . implode(', ', array_map('basename', $similarFiles));
                        }
                    }
                    echo "</div>";
                }
                echo "</div>";
            }
        } else {
            echo "<div class='file-missing'>❌ Album trống hoặc NULL</div>";
        }
        
        echo "</div>";
    }
    
    echo "<div class='actions'>";
    echo "<h2>📋 Kết quả:</h2>";
    echo "<ul>";
    echo "<li><strong>Tổng file album trong DB:</strong> {$totalFiles}</li>";
    echo "<li><strong>File không tồn tại:</strong> {$totalMissing}</li>";
    echo "<li><strong>File tồn tại:</strong> " . ($totalFiles - $totalMissing) . "</li>";
    echo "</ul>";
    
    if ($totalMissing > 0) {
        echo "<h3>🛠️ Hành động khuyến nghị:</h3>";
        echo "<ol>";
        echo "<li><strong>Kiểm tra thư mục uploads/products</strong> - Có thể file upload nhưng tên khác</li>";
        echo "<li><strong>Kiểm tra quyền thư mục</strong> - chmod 755 hoặc 777</li>";
        echo "<li><strong>Upload lại ảnh album</strong> qua giao diện upload</li>";
        echo "<li><strong>Hoặc dọn dẹp database</strong> để xóa tham chiếu file không tồn tại</li>";
        echo "</ol>";
    } else {
        echo "<h3>🎉 Tất cả file album đều tồn tại!</h3>";
    }
    echo "</div>";
    
    // Liệt kê tất cả file trong thư mục uploads/products
    echo "<div class='actions'>";
    echo "<h2>📁 Tất cả file trong uploads/products:</h2>";
    $files = glob('uploads/products/*');
    if ($files) {
        echo "<div style='max-height: 300px; overflow-y: auto; border: 1px solid #ccc; padding: 10px;'>";
        sort($files);
        foreach ($files as $file) {
            $fileName = basename($file);
            $fileTime = date('H:i:s d/m/Y', filemtime($file));
            $fileSize = filesize($file);
            echo "<div style='margin: 2px 0; padding: 2px; background: #f9f9f9;'>";
            echo "📄 <strong>{$fileName}</strong> - {$fileSize} bytes - {$fileTime}";
            echo "</div>";
        }
        echo "</div>";
    } else {
        echo "<p>❌ Không có file nào trong thư mục uploads/products</p>";
    }
    echo "</div>";
    ?>
    
    <div class="actions">
        <h2>🔗 Liên kết hữu ích:</h2>
        <a href="upload_no_refresh.php" style="background: #007bff; color: white; padding: 10px; text-decoration: none; margin: 5px;">📤 Upload Ảnh</a>
        <a href="fix_album_images.php" style="background: #28a745; color: white; padding: 10px; text-decoration: none; margin: 5px;">🛠️ Sửa Album</a>
        <a href="debug_simple.php" style="background: #6c757d; color: white; padding: 10px; text-decoration: none; margin: 5px;">🔍 Debug Đơn Giản</a>
    </div>

</body>
</html>