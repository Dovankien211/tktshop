<?php
/**
 * Script Debug - Kiểm tra ảnh thiếu trong hệ thống TKTShop
 * Đặt file này trong thư mục gốc của dự án: /tktshop/debug_missing_images.php
 */

require_once 'config/database.php';

// Khởi tạo kết nối database
try {
    $pdo = new PDO("mysql:host=localhost;dbname=tktshop;charset=utf8mb4", "root", "");
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
} catch(PDOException $e) {
    die("Lỗi kết nối database: " . $e->getMessage());
}

// Định nghĩa đường dẫn thư mục ảnh
$uploadDir = __DIR__ . '/uploads/products/';
$uploadDirCategory = __DIR__ . '/uploads/categories/';

echo "<!DOCTYPE html>
<html lang='vi'>
<head>
    <meta charset='UTF-8'>
    <meta name='viewport' content='width=device-width, initial-scale=1.0'>
    <title>Debug - Kiểm tra ảnh thiếu</title>
    <link href='https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css' rel='stylesheet'>
    <style>
        .missing { background-color: #ffebee; }
        .exists { background-color: #e8f5e8; }
        .warning { background-color: #fff3e0; }
        .img-preview { max-width: 80px; max-height: 80px; object-fit: cover; }
    </style>
</head>
<body>
<div class='container-fluid mt-4'>
    <h1 class='text-center mb-4'>🔍 Debug - Kiểm tra ảnh thiếu trong TKTShop</h1>";

// 1. KIỂM TRA DANH MỤC THIẾU ẢNH
echo "<div class='card mb-4'>
        <div class='card-header bg-primary text-white'>
            <h3>📁 1. DANH MỤC THIẾU ẢNH</h3>
        </div>
        <div class='card-body'>";

$stmt = $pdo->query("SELECT id, ten_danh_muc, hinh_anh, trang_thai FROM danh_muc_giay ORDER BY id");
$categories = $stmt->fetchAll(PDO::FETCH_ASSOC);

echo "<div class='table-responsive'>
        <table class='table table-bordered'>
            <thead>
                <tr>
                    <th>ID</th>
                    <th>Tên danh mục</th>
                    <th>Tên file ảnh</th>
                    <th>Trạng thái file</th>
                    <th>Preview</th>
                    <th>Ghi chú</th>
                </tr>
            </thead>
            <tbody>";

$missingCategoryImages = 0;
foreach ($categories as $cat) {
    $filePath = $uploadDirCategory . $cat['hinh_anh'];
    $fileExists = !empty($cat['hinh_anh']) && file_exists($filePath);
    $rowClass = '';
    $status = '';
    $note = '';
    
    if (empty($cat['hinh_anh'])) {
        $rowClass = 'missing';
        $status = '❌ KHÔNG CÓ ẢNH';
        $note = 'Cần thêm ảnh cho danh mục';
        $missingCategoryImages++;
    } elseif (!$fileExists) {
        $rowClass = 'warning';
        $status = '⚠️ FILE KHÔNG TỒN TẠI';
        $note = 'File database có nhưng không tìm thấy trong thư mục';
        $missingCategoryImages++;
    } else {
        $rowClass = 'exists';
        $status = '✅ CÓ ẢNH';
        $note = 'OK';
    }
    
    echo "<tr class='{$rowClass}'>
            <td>{$cat['id']}</td>
            <td>{$cat['ten_danh_muc']}</td>
            <td>" . ($cat['hinh_anh'] ?: '<em>Chưa có</em>') . "</td>
            <td>{$status}</td>
            <td>";
    
    if ($fileExists) {
        echo "<img src='uploads/categories/{$cat['hinh_anh']}' class='img-preview' alt='Category image'>";
    } else {
        echo "N/A";
    }
    
    echo "</td>
            <td>{$note}</td>
          </tr>";
}

echo "</tbody></table></div>";
echo "<div class='alert alert-info'>
        <strong>Tổng kết danh mục:</strong> 
        <span class='badge bg-danger'>{$missingCategoryImages} danh mục thiếu ảnh</span> / 
        <span class='badge bg-success'>" . (count($categories) - $missingCategoryImages) . " danh mục có ảnh</span>
      </div>";
echo "</div></div>";

// 2. KIỂM TRA SẢN PHẨM THIẾU ẢNH
echo "<div class='card mb-4'>
        <div class='card-header bg-success text-white'>
            <h3>👟 2. SẢN PHẨM THIẾU ẢNH</h3>
        </div>
        <div class='card-body'>";

$stmt = $pdo->query("
    SELECT 
        sp.id,
        sp.ten_san_pham,
        sp.thuong_hieu,
        sp.hinh_anh_chinh,
        sp.album_hinh_anh,
        sp.trang_thai,
        dm.ten_danh_muc
    FROM san_pham_chinh sp
    LEFT JOIN danh_muc_giay dm ON sp.danh_muc_id = dm.id
    ORDER BY sp.id
");
$products = $stmt->fetchAll(PDO::FETCH_ASSOC);

echo "<div class='table-responsive'>
        <table class='table table-bordered'>
            <thead>
                <tr>
                    <th>ID</th>
                    <th>Tên sản phẩm</th>
                    <th>Thương hiệu</th>
                    <th>Danh mục</th>
                    <th>Ảnh chính</th>
                    <th>Album ảnh</th>
                    <th>Preview</th>
                    <th>Vấn đề</th>
                </tr>
            </thead>
            <tbody>";

$missingProductImages = 0;
$totalMissingFiles = 0;

foreach ($products as $product) {
    $issues = [];
    $rowClass = 'exists';
    
    // Kiểm tra ảnh chính
    if (empty($product['hinh_anh_chinh'])) {
        $issues[] = 'Thiếu ảnh chính';
        $rowClass = 'missing';
        $missingProductImages++;
    } else {
        $mainImagePath = $uploadDir . $product['hinh_anh_chinh'];
        if (!file_exists($mainImagePath)) {
            $issues[] = 'File ảnh chính không tồn tại';
            $rowClass = 'warning';
            $totalMissingFiles++;
        }
    }
    
    // Kiểm tra album ảnh
    $albumImages = [];
    $missingAlbumFiles = 0;
    if (!empty($product['album_hinh_anh'])) {
        $album = json_decode($product['album_hinh_anh'], true);
        if (is_array($album)) {
            foreach ($album as $img) {
                $albumImagePath = $uploadDir . $img;
                if (!file_exists($albumImagePath)) {
                    $missingAlbumFiles++;
                    $totalMissingFiles++;
                }
                $albumImages[] = $img;
            }
        }
    }
    
    if (empty($albumImages)) {
        $issues[] = 'Thiếu album ảnh';
        if ($rowClass !== 'missing') $rowClass = 'warning';
    } elseif ($missingAlbumFiles > 0) {
        $issues[] = "{$missingAlbumFiles} file album không tồn tại";
        if ($rowClass !== 'missing') $rowClass = 'warning';
    }
    
    echo "<tr class='{$rowClass}'>
            <td>{$product['id']}</td>
            <td>{$product['ten_san_pham']}</td>
            <td>{$product['thuong_hieu']}</td>
            <td>{$product['ten_danh_muc']}</td>
            <td>" . ($product['hinh_anh_chinh'] ?: '<em>Chưa có</em>') . "</td>
            <td>";
    
    if (!empty($albumImages)) {
        echo "<small>" . count($albumImages) . " ảnh: " . implode(', ', array_slice($albumImages, 0, 2));
        if (count($albumImages) > 2) echo "...";
        echo "</small>";
    } else {
        echo "<em>Chưa có</em>";
    }
    
    echo "</td>
            <td>";
    
    // Preview ảnh chính
    if (!empty($product['hinh_anh_chinh']) && file_exists($uploadDir . $product['hinh_anh_chinh'])) {
        echo "<img src='uploads/products/{$product['hinh_anh_chinh']}' class='img-preview' alt='Product image'>";
    } else {
        echo "❌";
    }
    
    echo "</td>
            <td>";
    
    if (empty($issues)) {
        echo "<span class='text-success'>✅ OK</span>";
    } else {
        echo "<span class='text-danger'>" . implode('<br>', $issues) . "</span>";
    }
    
    echo "</td></tr>";
}

echo "</tbody></table></div>";
echo "<div class='alert alert-info'>
        <strong>Tổng kết sản phẩm:</strong> 
        <span class='badge bg-danger'>{$missingProductImages} sản phẩm thiếu ảnh</span> / 
        <span class='badge bg-warning'>{$totalMissingFiles} file ảnh không tồn tại</span> / 
        <span class='badge bg-success'>" . count($products) . " tổng sản phẩm</span>
      </div>";
echo "</div></div>";

// 3. KIỂM TRA BIẾN THỂ SẢN PHẨM
echo "<div class='card mb-4'>
        <div class='card-header bg-warning text-dark'>
            <h3>🎨 3. BIẾN THỂ SẢN PHẨM (Size + Màu)</h3>
        </div>
        <div class='card-body'>";

$stmt = $pdo->query("
    SELECT 
        bt.id,
        bt.ma_sku,
        bt.hinh_anh_bien_the,
        sp.ten_san_pham,
        kc.kich_co,
        ms.ten_mau,
        bt.trang_thai
    FROM bien_the_san_pham bt
    LEFT JOIN san_pham_chinh sp ON bt.san_pham_id = sp.id
    LEFT JOIN kich_co kc ON bt.kich_co_id = kc.id
    LEFT JOIN mau_sac ms ON bt.mau_sac_id = ms.id
    ORDER BY sp.ten_san_pham, kc.kich_co, ms.ten_mau
");
$variants = $stmt->fetchAll(PDO::FETCH_ASSOC);

echo "<div class='table-responsive'>
        <table class='table table-bordered table-sm'>
            <thead>
                <tr>
                    <th>ID</th>
                    <th>Sản phẩm</th>
                    <th>Size</th>
                    <th>Màu</th>
                    <th>SKU</th>
                    <th>Ảnh biến thể</th>
                    <th>Trạng thái</th>
                </tr>
            </thead>
            <tbody>";

$missingVariantImages = 0;
foreach ($variants as $variant) {
    $rowClass = '';
    $imageStatus = '';
    
    if (empty($variant['hinh_anh_bien_the'])) {
        $rowClass = 'warning';
        $imageStatus = '⚠️ Chưa có ảnh riêng';
        $missingVariantImages++;
    } else {
        $variantImagePath = $uploadDir . $variant['hinh_anh_bien_the'];
        if (!file_exists($variantImagePath)) {
            $rowClass = 'missing';
            $imageStatus = '❌ File không tồn tại';
        } else {
            $rowClass = 'exists';
            $imageStatus = '✅ Có ảnh';
        }
    }
    
    echo "<tr class='{$rowClass}'>
            <td>{$variant['id']}</td>
            <td>{$variant['ten_san_pham']}</td>
            <td>{$variant['kich_co']}</td>
            <td>{$variant['ten_mau']}</td>
            <td>{$variant['ma_sku']}</td>
            <td>" . ($variant['hinh_anh_bien_the'] ?: '<em>Chưa có</em>') . "</td>
            <td>{$imageStatus}</td>
          </tr>";
}

echo "</tbody></table></div>";
echo "<div class='alert alert-info'>
        <strong>Tổng kết biến thể:</strong> 
        <span class='badge bg-warning'>{$missingVariantImages} biến thể chưa có ảnh riêng</span> / 
        <span class='badge bg-success'>" . count($variants) . " tổng biến thể</span>
        <br><small class='text-muted'>Lưu ý: Biến thể có thể dùng ảnh chung của sản phẩm chính</small>
      </div>";
echo "</div></div>";

// 4. TÓM TẮT TỔNG QUAN
echo "<div class='card mb-4'>
        <div class='card-header bg-dark text-white'>
            <h3>📊 4. TÓM TẮT TỔNG QUAN</h3>
        </div>
        <div class='card-body'>";

$totalIssues = $missingCategoryImages + $missingProductImages + $totalMissingFiles;

echo "<div class='row'>
        <div class='col-md-3'>
            <div class='card bg-danger text-white'>
                <div class='card-body text-center'>
                    <h4>{$missingCategoryImages}</h4>
                    <p>Danh mục thiếu ảnh</p>
                </div>
            </div>
        </div>
        <div class='col-md-3'>
            <div class='card bg-warning text-dark'>
                <div class='card-body text-center'>
                    <h4>{$missingProductImages}</h4>
                    <p>Sản phẩm thiếu ảnh</p>
                </div>
            </div>
        </div>
        <div class='col-md-3'>
            <div class='card bg-secondary text-white'>
                <div class='card-body text-center'>
                    <h4>{$totalMissingFiles}</h4>
                    <p>File ảnh không tồn tại</p>
                </div>
            </div>
        </div>
        <div class='col-md-3'>
            <div class='card " . ($totalIssues > 0 ? 'bg-danger' : 'bg-success') . " text-white'>
                <div class='card-body text-center'>
                    <h4>{$totalIssues}</h4>
                    <p>Tổng vấn đề</p>
                </div>
            </div>
        </div>
      </div>";

// Kiểm tra thư mục uploads
echo "<div class='mt-4'>
        <h5>📁 Trạng thái thư mục uploads:</h5>
        <ul class='list-group'>";

$dirs = [
    'uploads/products/' => $uploadDir,
    'uploads/categories/' => $uploadDirCategory,
    'uploads/users/' => __DIR__ . '/uploads/users/',
    'uploads/delivery/' => __DIR__ . '/uploads/delivery/'
];

foreach ($dirs as $displayPath => $fullPath) {
    $exists = is_dir($fullPath);
    $writable = $exists && is_writable($fullPath);
    
    echo "<li class='list-group-item d-flex justify-content-between align-items-center'>
            <span>{$displayPath}</span>";
    
    if ($exists && $writable) {
        echo "<span class='badge bg-success'>✅ OK (có thể ghi)</span>";
    } elseif ($exists) {
        echo "<span class='badge bg-warning'>⚠️ Tồn tại nhưng không ghi được</span>";
    } else {
        echo "<span class='badge bg-danger'>❌ Không tồn tại</span>";
    }
    
    echo "</li>";
}

echo "</ul></div>";

echo "</div></div>";

// 5. HƯỚNG DẪN XỬ LÝ
echo "<div class='card'>
        <div class='card-header bg-info text-white'>
            <h3>💡 5. HƯỚNG DẪN XỬ LÝ</h3>
        </div>
        <div class='card-body'>
            <div class='row'>
                <div class='col-md-6'>
                    <h5>🔧 Các bước khắc phục:</h5>
                    <ol>
                        <li>Tạo thư mục nếu chưa có:</li>
                        <pre class='bg-light p-2'>mkdir -p uploads/products uploads/categories uploads/users uploads/delivery
chmod 777 uploads/products uploads/categories uploads/users uploads/delivery</pre>
                        
                        <li>Upload ảnh cho sản phẩm thiếu ảnh</li>
                        <li>Cập nhật database với tên file ảnh mới</li>
                        <li>Chạy lại script này để kiểm tra</li>
                    </ol>
                </div>
                <div class='col-md-6'>
                    <h5>📄 Files cần tạo tiếp theo:</h5>
                    <ul>
                        <li><code>admin_upload_images.php</code> - Giao diện upload ảnh</li>
                        <li><code>fix_missing_images.php</code> - Script tự động sửa</li>
                        <li>Bulk upload cho nhiều ảnh cùng lúc</li>
                    </ul>
                    
                    <div class='alert alert-warning mt-3'>
                        <strong>Lưu ý:</strong> Đảm bảo ảnh có định dạng JPG, PNG, GIF và kích thước phù hợp (≤ 2MB)
                    </div>
                </div>
            </div>
        </div>
      </div>";

echo "</div>

<script>
// Disable auto-refresh và cache busting
document.addEventListener('DOMContentLoaded', function() {
    // Prevent form resubmission on page refresh
    if (window.history.replaceState) {
        window.history.replaceState(null, null, window.location.href);
    }
});
</script>

</body>
</html>";

// Đóng kết nối
$pdo = null;
?>