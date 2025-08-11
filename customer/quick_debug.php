<?php
// Tạo file: customer/quick_debug.php
require_once '../config/database.php';

$slug = 'converse-chuck-taylor';

echo "<h3>🔍 DEBUG SLUG: $slug</h3>";

// Test query trực tiếp
try {
    $stmt = $pdo->prepare("
        SELECT sp.*, dm.ten_danh_muc, dm.slug as danh_muc_slug,
               COALESCE(sp.gia_khuyen_mai, sp.gia_goc) as gia_hien_tai,
               CASE 
                   WHEN sp.gia_khuyen_mai IS NOT NULL AND sp.gia_khuyen_mai < sp.gia_goc 
                   THEN ROUND(((sp.gia_goc - sp.gia_khuyen_mai) / sp.gia_goc) * 100, 0)
                   ELSE 0
               END as phan_tram_giam
        FROM san_pham_chinh sp
        LEFT JOIN danh_muc_giay dm ON sp.danh_muc_id = dm.id
        WHERE sp.slug = ? AND sp.trang_thai = 'hoat_dong'
    ");
    $stmt->execute([$slug]);
    $product = $stmt->fetch();
    
    if ($product) {
        echo "✅ FOUND PRODUCT:<br>";
        echo "ID: " . $product['id'] . "<br>";
        echo "Tên: " . htmlspecialchars($product['ten_san_pham']) . "<br>";
        echo "Slug: " . htmlspecialchars($product['slug']) . "<br>";
        echo "Trạng thái: " . $product['trang_thai'] . "<br>";
        echo "Giá: " . number_format($product['gia_goc']) . "<br>";
    } else {
        echo "❌ PRODUCT NOT FOUND<br>";
        
        // Kiểm tra slug có tồn tại không
        $check = $pdo->prepare("SELECT id, ten_san_pham, slug, trang_thai FROM san_pham_chinh WHERE slug = ?");
        $check->execute([$slug]);
        $check_result = $check->fetch();
        
        if ($check_result) {
            echo "⚠️ Slug tồn tại nhưng có thể bị lỗi điều kiện:<br>";
            echo "Trạng thái: " . $check_result['trang_thai'] . "<br>";
        } else {
            echo "❌ Slug hoàn toàn không tồn tại<br>";
            
            // Hiển thị 5 slug có sẵn
            $all_slugs = $pdo->query("SELECT slug, ten_san_pham FROM san_pham_chinh LIMIT 5")->fetchAll();
            echo "<br>📋 Slug có sẵn:<br>";
            foreach ($all_slugs as $item) {
                echo "- " . $item['slug'] . " (" . htmlspecialchars($item['ten_san_pham']) . ")<br>";
            }
        }
    }
    
} catch (Exception $e) {
    echo "❌ Lỗi SQL: " . $e->getMessage();
}

// Test với các slug khác
$test_slugs = ['converse-chuck-taylor', 'adidas-ultraboost-22', 'vans-old-skool'];
foreach ($test_slugs as $test_slug) {
    $stmt = $pdo->prepare("SELECT id, ten_san_pham, slug FROM san_pham_chinh WHERE slug = ?");
    $stmt->execute([$test_slug]);
    $result = $stmt->fetch();
    
    if ($result) {
        echo "<br>✅ <a href='product_detail.php?slug=$test_slug' target='_blank'>$test_slug</a> - " . htmlspecialchars($result['ten_san_pham']);
    } else {
        echo "<br>❌ $test_slug - Không tồn tại";
    }
}
?>