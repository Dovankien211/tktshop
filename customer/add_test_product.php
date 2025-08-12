<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Thêm Sản Phẩm Test - Miễn Ship</title>
    <style>
        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            margin: 0;
            padding: 20px;
            min-height: 100vh;
        }
        
        .container {
            max-width: 900px;
            margin: 0 auto;
            background: white;
            border-radius: 15px;
            box-shadow: 0 10px 30px rgba(0,0,0,0.2);
            overflow: hidden;
        }
        
        .header {
            background: linear-gradient(45deg, #28a745, #20c997);
            color: white;
            padding: 30px;
            text-align: center;
        }
        
        .header h1 {
            margin: 0;
            font-size: 2.5rem;
        }
        
        .tabs {
            display: flex;
            background: #f8f9fa;
            border-bottom: 1px solid #dee2e6;
        }
        
        .tab {
            flex: 1;
            padding: 15px;
            text-align: center;
            background: none;
            border: none;
            cursor: pointer;
            font-weight: 600;
            transition: all 0.3s;
        }
        
        .tab.active {
            background: white;
            border-bottom: 3px solid #28a745;
            color: #28a745;
        }
        
        .tab-content {
            display: none;
            padding: 30px;
        }
        
        .tab-content.active {
            display: block;
        }
        
        .method {
            background: #f8f9fa;
            border-left: 4px solid #28a745;
            padding: 20px;
            margin: 20px 0;
            border-radius: 8px;
        }
        
        .code-block {
            background: #1a202c;
            color: #e2e8f0;
            padding: 15px;
            border-radius: 8px;
            margin: 15px 0;
            font-family: 'Courier New', monospace;
            overflow-x: auto;
            position: relative;
        }
        
        .copy-btn {
            position: absolute;
            top: 10px;
            right: 10px;
            background: #28a745;
            color: white;
            border: none;
            padding: 5px 10px;
            border-radius: 4px;
            cursor: pointer;
            font-size: 12px;
        }
        
        .copy-btn:hover {
            background: #218838;
        }
        
        .btn {
            background: linear-gradient(45deg, #28a745, #20c997);
            color: white;
            border: none;
            padding: 12px 25px;
            border-radius: 25px;
            cursor: pointer;
            font-weight: 600;
            margin: 10px 5px;
            text-decoration: none;
            display: inline-block;
            transition: all 0.3s;
        }
        
        .btn:hover {
            transform: translateY(-2px);
            box-shadow: 0 5px 15px rgba(0,0,0,0.2);
            color: white;
        }
        
        .success-box {
            background: #d4edda;
            border: 1px solid #c3e6cb;
            color: #155724;
            padding: 15px;
            border-radius: 8px;
            margin: 15px 0;
        }
        
        .info-box {
            background: #cce7ff;
            border: 1px solid #99d6ff;
            color: #004085;
            padding: 15px;
            border-radius: 8px;
            margin: 15px 0;
        }
        
        .step {
            background: white;
            border: 2px solid #e9ecef;
            padding: 20px;
            margin: 15px 0;
            border-radius: 10px;
            position: relative;
        }
        
        .step-number {
            position: absolute;
            top: -15px;
            left: 20px;
            background: #28a745;
            color: white;
            width: 30px;
            height: 30px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-weight: bold;
        }
        
        .price-highlight {
            background: #fff3cd;
            border: 1px solid #ffeaa7;
            padding: 10px;
            border-radius: 5px;
            margin: 10px 0;
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <h1>🚚 Thêm Sản Phẩm Miễn Ship</h1>
            <p>Test thanh toán với sản phẩm miễn phí vận chuyển</p>
        </div>
        
        <div class="tabs">
            <button class="tab active" onclick="showTab('quick')">Cách Nhanh</button>
            <button class="tab" onclick="showTab('admin')">Qua Admin</button>
            <button class="tab" onclick="showTab('database')">Database Direct</button>
            <button class="tab" onclick="showTab('config')">Cấu Hình Ship</button>
        </div>
        
        <div id="quick" class="tab-content active">
            <h2>🚀 Cách Nhanh Nhất - Script Tự Động</h2>
            
            <div class="success-box">
                <h3>✨ Tạo sản phẩm test với 1 click!</h3>
                <p>Script này sẽ tự động thêm sản phẩm test với giá 496.000đ (đủ miễn ship)</p>
            </div>
            
            <div class="step">
                <div class="step-number">1</div>
                <h3>Tạo file add_test_product.php</h3>
                <p>Tạo file này trong thư mục <strong>customer/</strong></p>
                
                <div class="code-block">
                    <button class="copy-btn" onclick="copyCode('quick-script')">Copy</button>
<?php
// File: customer/add_test_product.php
require_once '../config/config.php';
require_once '../config/database.php';

echo "<h2>🛍️ Thêm Sản Phẩm Test - Miễn Ship</h2>";

try {
    // Kiểm tra sản phẩm test đã tồn tại chưa
    $check = $pdo->prepare("SELECT id FROM san_pham_chinh WHERE ten_san_pham LIKE '%TEST%' LIMIT 1");
    $check->execute();
    
    if ($check->fetch()) {
        echo "<div style='color: orange;'>⚠️ Sản phẩm test đã tồn tại!</div>";
        echo "<p><a href='products_fixed.php'>Xem sản phẩm</a></p>";
        exit;
    }
    
    // Lấy danh mục đầu tiên
    $category = $pdo->query("SELECT id FROM danh_muc_giay WHERE trang_thai = 'hoat_dong' LIMIT 1")->fetch();
    $category_id = $category ? $category['id'] : 1;
    
    // Tạo sản phẩm test
    $stmt = $pdo->prepare("
        INSERT INTO san_pham_chinh (
            ten_san_pham, slug, mo_ta_ngan, mo_ta_chi_tiet, 
            gia_goc, gia_khuyen_mai, danh_muc_id, thuong_hieu,
            trang_thai, so_luong_ton_kho, anh_chinh, created_at
        ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())
    ");
    
    $product_data = [
        'TEST - Giày Thể Thao Miễn Ship',
        'test-giay-the-thao-mien-ship',
        'Sản phẩm test để kiểm tra thanh toán - Miễn phí vận chuyển',
        'Đây là sản phẩm test dành cho việc kiểm tra chức năng thanh toán. Sản phẩm có giá trị đủ để được miễn phí vận chuyển.',
        500000, // Giá gốc: 500k
        496000, // Giá khuyến mãi: 496k (đủ miễn ship)
        $category_id,
        'TEST',
        'hoat_dong',
        100, // Số lượng
        'test-product.jpg'
    ];
    
    $stmt->execute($product_data);
    $product_id = $pdo->lastInsertId();
    
    // Thêm một số size cơ bản
    $sizes = [38, 39, 40, 41, 42];
    foreach ($sizes as $size) {
        $size_stmt = $pdo->prepare("
            INSERT INTO san_pham_bien_the (
                san_pham_id, kich_co, mau_sac, so_luong_ton, 
                gia_ban, trang_thai, created_at
            ) VALUES (?, ?, ?, ?, ?, ?, NOW())
        ");
        
        $size_stmt->execute([
            $product_id, $size, 'Trắng', 20, 496000, 'hoat_dong'
        ]);
    }
    
    echo "<div style='color: green; padding: 20px; background: #d4edda; border-radius: 8px;'>";
    echo "✅ <strong>Đã tạo sản phẩm test thành công!</strong><br>";
    echo "📦 <strong>Tên:</strong> TEST - Giày Thể Thao Miễn Ship<br>";
    echo "💰 <strong>Giá:</strong> 496.000đ (đủ miễn ship)<br>";
    echo "📏 <strong>Size:</strong> 38, 39, 40, 41, 42<br>";
    echo "🎨 <strong>Màu:</strong> Trắng<br>";
    echo "</div>";
    
    echo "<h3>🎯 Test ngay:</h3>";
    echo "<a href='products_fixed.php' style='background: #007bff; color: white; padding: 10px 20px; text-decoration: none; border-radius: 5px; margin: 5px;'>Xem sản phẩm</a>";
    echo "<a href='cart_fixed.php' style='background: #28a745; color: white; padding: 10px 20px; text-decoration: none; border-radius: 5px; margin: 5px;'>Xem giỏ hàng</a>";
    
} catch (Exception $e) {
    echo "<div style='color: red;'>❌ Lỗi: " . $e->getMessage() . "</div>";
}
?>