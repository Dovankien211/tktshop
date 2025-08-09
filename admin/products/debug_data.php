<?php
require_once '../../config/database.php';

echo "<h2>🔍 DEBUG: Kiểm tra dữ liệu sản phẩm</h2>";

// 1. Kiểm tra cấu trúc bảng
echo "<h3>📋 Cấu trúc bảng san_pham_chinh:</h3>";
try {
    $stmt = $pdo->query("DESCRIBE san_pham_chinh");
    $columns = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    echo "<table border='1' style='border-collapse: collapse;'>";
    echo "<tr><th>Cột</th><th>Kiểu</th><th>Null</th><th>Key</th><th>Default</th></tr>";
    foreach($columns as $col) {
        echo "<tr>";
        echo "<td><strong>{$col['Field']}</strong></td>";
        echo "<td>{$col['Type']}</td>";
        echo "<td>{$col['Null']}</td>";
        echo "<td>{$col['Key']}</td>";
        echo "<td>{$col['Default']}</td>";
        echo "</tr>";
    }
    echo "</table>";
} catch(Exception $e) {
    echo "<p style='color: red;'>Lỗi: " . $e->getMessage() . "</p>";
}

// 2. Kiểm tra dữ liệu sản phẩm
echo "<h3>📦 Dữ liệu sản phẩm hiện có:</h3>";
try {
    $stmt = $pdo->query("SELECT * FROM san_pham_chinh ORDER BY ngay_tao DESC");
    $products = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    echo "<p><strong>Tổng sản phẩm:</strong> " . count($products) . "</p>";
    
    if (count($products) > 0) {
        echo "<table border='1' style='border-collapse: collapse; width: 100%;'>";
        echo "<tr>";
        echo "<th>ID</th><th>Tên</th><th>Mã</th><th>Thương hiệu</th><th>Danh mục ID</th>";
        echo "<th>Giá gốc</th><th>Trạng thái</th><th>Ảnh</th><th>Ngày tạo</th>";
        echo "</tr>";
        
        foreach($products as $product) {
            echo "<tr>";
            echo "<td>{$product['id']}</td>";
            echo "<td>" . htmlspecialchars($product['ten_san_pham']) . "</td>";
            echo "<td>{$product['ma_san_pham']}</td>";
            echo "<td>{$product['thuong_hieu']}</td>";
            echo "<td>{$product['danh_muc_id']}</td>";
            echo "<td>" . number_format($product['gia_goc']) . "₫</td>";
            echo "<td><span style='color: " . ($product['trang_thai'] == 'hoat_dong' ? 'green' : 'red') . ";'>{$product['trang_thai']}</span></td>";
            echo "<td>" . ($product['hinh_anh_chinh'] ? '✅ Có' : '❌ Không') . "</td>";
            echo "<td>{$product['ngay_tao']}</td>";
            echo "</tr>";
        }
        echo "</table>";
        
        // Kiểm tra sản phẩm hoạt động
        $active_products = array_filter($products, function($p) {
            return $p['trang_thai'] == 'hoat_dong';
        });
        
        echo "<h4>✅ Sản phẩm đang hoạt động: " . count($active_products) . "</h4>";
        
        // Kiểm tra sản phẩm có ảnh
        $products_with_image = array_filter($products, function($p) {
            return !empty($p['hinh_anh_chinh']);
        });
        
        echo "<h4>📷 Sản phẩm có ảnh: " . count($products_with_image) . "</h4>";
        
    } else {
        echo "<p style='color: red;'>❌ Không có sản phẩm nào trong database!</p>";
    }
    
} catch(Exception $e) {
    echo "<p style='color: red;'>Lỗi: " . $e->getMessage() . "</p>";
}

// 3. Kiểm tra danh mục
echo "<h3>📁 Danh mục:</h3>";
try {
    $stmt = $pdo->query("SELECT * FROM danh_muc_giay ORDER BY id");
    $categories = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    echo "<table border='1' style='border-collapse: collapse;'>";
    echo "<tr><th>ID</th><th>Tên danh mục</th><th>Trạng thái</th></tr>";
    foreach($categories as $cat) {
        echo "<tr>";
        echo "<td>{$cat['id']}</td>";
        echo "<td>" . htmlspecialchars($cat['ten_danh_muc']) . "</td>";
        echo "<td>{$cat['trang_thai']}</td>";
        echo "</tr>";
    }
    echo "</table>";
    
} catch(Exception $e) {
    echo "<p style='color: red;'>Lỗi: " . $e->getMessage() . "</p>";
}

// 4. Test query cho customer
echo "<h3>🛒 Test query cho trang khách hàng:</h3>";
try {
    $sql = "SELECT sp.*, dm.ten_danh_muc 
            FROM san_pham_chinh sp 
            LEFT JOIN danh_muc_giay dm ON sp.danh_muc_id = dm.id 
            WHERE sp.trang_thai = 'hoat_dong' 
            ORDER BY sp.ngay_tao DESC";
    
    $stmt = $pdo->query($sql);
    $customer_products = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    echo "<p><strong>Sản phẩm sẽ hiển thị cho khách:</strong> " . count($customer_products) . "</p>";
    
    if (count($customer_products) > 0) {
        echo "<ul>";
        foreach($customer_products as $p) {
            echo "<li>";
            echo "<strong>" . htmlspecialchars($p['ten_san_pham']) . "</strong> ";
            echo "({$p['thuong_hieu']}) - ";
            echo number_format($p['gia_goc']) . "₫ - ";
            echo "Danh mục: " . ($p['ten_danh_muc'] ?: 'Không có') . " - ";
            echo "Ảnh: " . ($p['hinh_anh_chinh'] ? '✅' : '❌');
            echo "</li>";
        }
        echo "</ul>";
    }
    
} catch(Exception $e) {
    echo "<p style='color: red;'>Lỗi: " . $e->getMessage() . "</p>";
}

// 5. Kiểm tra file customer
echo "<h3>📂 Kiểm tra file customer:</h3>";
$customer_files = [
    '../../customer/index.php' => 'Trang chủ customer',
    '../../customer/products.php' => 'Danh sách sản phẩm customer'
];

foreach($customer_files as $file => $desc) {
    if (file_exists($file)) {
        echo "<p>✅ $desc: <code>$file</code></p>";
    } else {
        echo "<p>❌ $desc: <code>$file</code> - <strong>Không tồn tại!</strong></p>";
    }
}

echo "<hr>";
echo "<h3>🔗 Test Links:</h3>";
echo "<p><a href='/tktshop/customer/' target='_blank'>🛒 Trang customer</a></p>";
echo "<p><a href='/tktshop/customer/products.php' target='_blank'>📦 Danh sách sản phẩm customer</a></p>";
echo "<p><a href='index.php'>🔙 Quay lại admin products</a></p>";
?>

<style>
body { font-family: Arial; padding: 20px; line-height: 1.6; }
table { margin: 10px 0; }
th, td { padding: 8px; text-align: left; }
th { background-color: #f2f2f2; }
h2, h3, h4 { color: #333; }
</style>