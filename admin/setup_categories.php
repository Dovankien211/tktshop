<?php
/**
 * Setup Categories Data - Tạo bảng và dữ liệu danh mục mẫu
 * Chạy file này 1 lần để tạo dữ liệu danh mục
 */

session_start();
require_once '../config/database.php';
require_once '../config/config.php';

// Kiểm tra đăng nhập admin
if (!isset($_SESSION['admin_id'])) {
    header('Location: login.php');
    exit;
}

$success_messages = [];
$error_messages = [];

try {
    // 1. Tạo bảng categories nếu chưa có
    $create_categories_sql = "
        CREATE TABLE IF NOT EXISTS categories (
            id INT AUTO_INCREMENT PRIMARY KEY,
            name VARCHAR(255) NOT NULL,
            slug VARCHAR(255) UNIQUE,
            description TEXT,
            image VARCHAR(255),
            parent_id INT DEFAULT 0,
            sort_order INT DEFAULT 0,
            status VARCHAR(20) DEFAULT 'active',
            meta_title VARCHAR(255),
            meta_description TEXT,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            INDEX idx_status (status),
            INDEX idx_parent (parent_id),
            INDEX idx_sort (sort_order)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
    ";
    
    $pdo->exec($create_categories_sql);
    $success_messages[] = "✅ Tạo bảng categories thành công!";
    
    // 2. Kiểm tra xem đã có dữ liệu chưa
    $count_stmt = $pdo->query("SELECT COUNT(*) FROM categories");
    $existing_count = $count_stmt->fetchColumn();
    
    if ($existing_count == 0) {
        // 3. Thêm dữ liệu mẫu
        $categories_data = [
            // Danh mục chính
            ['name' => 'Giày thể thao', 'slug' => 'giay-the-thao', 'description' => 'Giày dành cho hoạt động thể thao', 'parent_id' => 0, 'sort_order' => 1],
            ['name' => 'Giày cao gót', 'slug' => 'giay-cao-got', 'description' => 'Giày cao gót cho nữ', 'parent_id' => 0, 'sort_order' => 2],
            ['name' => 'Giày da', 'slug' => 'giay-da', 'description' => 'Giày da cao cấp', 'parent_id' => 0, 'sort_order' => 3],
            ['name' => 'Giày lười', 'slug' => 'giay-luoi', 'description' => 'Giày lười tiện lợi', 'parent_id' => 0, 'sort_order' => 4],
            ['name' => 'Dép & Sandal', 'slug' => 'dep-sandal', 'description' => 'Dép và sandal các loại', 'parent_id' => 0, 'sort_order' => 5],
            ['name' => 'Giày boots', 'slug' => 'giay-boots', 'description' => 'Giày boots phong cách', 'parent_id' => 0, 'sort_order' => 6],
            
            // Danh mục con cho Giày thể thao
            ['name' => 'Running', 'slug' => 'running', 'description' => 'Giày chạy bộ', 'parent_id' => 1, 'sort_order' => 1],
            ['name' => 'Basketball', 'slug' => 'basketball', 'description' => 'Giày bóng rổ', 'parent_id' => 1, 'sort_order' => 2],
            ['name' => 'Football', 'slug' => 'football', 'description' => 'Giày bóng đá', 'parent_id' => 1, 'sort_order' => 3],
            ['name' => 'Tennis', 'slug' => 'tennis', 'description' => 'Giày tennis', 'parent_id' => 1, 'sort_order' => 4],
            ['name' => 'Sneakers', 'slug' => 'sneakers', 'description' => 'Giày sneaker thời trang', 'parent_id' => 1, 'sort_order' => 5],
            
            // Danh mục theo thương hiệu
            ['name' => 'Nike', 'slug' => 'nike', 'description' => 'Sản phẩm Nike chính hãng', 'parent_id' => 0, 'sort_order' => 7],
            ['name' => 'Adidas', 'slug' => 'adidas', 'description' => 'Sản phẩm Adidas chính hãng', 'parent_id' => 0, 'sort_order' => 8],
            ['name' => 'Converse', 'slug' => 'converse', 'description' => 'Sản phẩm Converse chính hãng', 'parent_id' => 0, 'sort_order' => 9],
            ['name' => 'Vans', 'slug' => 'vans', 'description' => 'Sản phẩm Vans chính hãng', 'parent_id' => 0, 'sort_order' => 10],
            
            // Danh mục theo giới tính
            ['name' => 'Giày nam', 'slug' => 'giay-nam', 'description' => 'Giày dành cho nam giới', 'parent_id' => 0, 'sort_order' => 11],
            ['name' => 'Giày nữ', 'slug' => 'giay-nu', 'description' => 'Giày dành cho nữ giới', 'parent_id' => 0, 'sort_order' => 12],
            ['name' => 'Giày trẻ em', 'slug' => 'giay-tre-em', 'description' => 'Giày dành cho trẻ em', 'parent_id' => 0, 'sort_order' => 13],
        ];
        
        $insert_sql = "
            INSERT INTO categories (name, slug, description, parent_id, sort_order, status, meta_title, meta_description) 
            VALUES (?, ?, ?, ?, ?, 'active', ?, ?)
        ";
        
        $stmt = $pdo->prepare($insert_sql);
        
        foreach ($categories_data as $category) {
            $meta_title = $category['name'] . ' - TKT Shop';
            $meta_description = $category['description'] . ' chất lượng cao tại TKT Shop';
            
            $stmt->execute([
                $category['name'],
                $category['slug'],
                $category['description'],
                $category['parent_id'],
                $category['sort_order'],
                $meta_title,
                $meta_description
            ]);
        }
        
        $success_messages[] = "✅ Thêm " . count($categories_data) . " danh mục mẫu thành công!";
    } else {
        $success_messages[] = "ℹ️ Đã có {$existing_count} danh mục trong hệ thống.";
    }
    
    // 4. Kiểm tra và tạo bảng sizes nếu cần
    $create_sizes_sql = "
        CREATE TABLE IF NOT EXISTS sizes (
            id INT AUTO_INCREMENT PRIMARY KEY,
            name VARCHAR(50) NOT NULL,
            sort_order INT DEFAULT 0,
            status VARCHAR(20) DEFAULT 'active',
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
    ";
    
    $pdo->exec($create_sizes_sql);
    
    // Thêm sizes mẫu nếu chưa có
    $size_count = $pdo->query("SELECT COUNT(*) FROM sizes")->fetchColumn();
    if ($size_count == 0) {
        $sizes_data = [
            ['name' => 'XS', 'sort_order' => 1],
            ['name' => 'S', 'sort_order' => 2],
            ['name' => 'M', 'sort_order' => 3],
            ['name' => 'L', 'sort_order' => 4],
            ['name' => 'XL', 'sort_order' => 5],
            ['name' => 'XXL', 'sort_order' => 6],
            ['name' => '35', 'sort_order' => 7],
            ['name' => '36', 'sort_order' => 8],
            ['name' => '37', 'sort_order' => 9],
            ['name' => '38', 'sort_order' => 10],
            ['name' => '39', 'sort_order' => 11],
            ['name' => '40', 'sort_order' => 12],
            ['name' => '41', 'sort_order' => 13],
            ['name' => '42', 'sort_order' => 14],
            ['name' => '43', 'sort_order' => 15],
            ['name' => '44', 'sort_order' => 16],
            ['name' => '45', 'sort_order' => 17],
        ];
        
        $size_stmt = $pdo->prepare("INSERT INTO sizes (name, sort_order) VALUES (?, ?)");
        foreach ($sizes_data as $size) {
            $size_stmt->execute([$size['name'], $size['sort_order']]);
        }
        
        $success_messages[] = "✅ Thêm " . count($sizes_data) . " kích cỡ mẫu thành công!";
    }
    
    // 5. Kiểm tra và tạo bảng colors nếu cần
    $create_colors_sql = "
        CREATE TABLE IF NOT EXISTS colors (
            id INT AUTO_INCREMENT PRIMARY KEY,
            name VARCHAR(50) NOT NULL,
            color_code VARCHAR(7) DEFAULT '#000000',
            status VARCHAR(20) DEFAULT 'active',
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
    ";
    
    $pdo->exec($create_colors_sql);
    
    // Thêm colors mẫu nếu chưa có
    $color_count = $pdo->query("SELECT COUNT(*) FROM colors")->fetchColumn();
    if ($color_count == 0) {
        $colors_data = [
            ['name' => 'Đỏ', 'color_code' => '#FF0000'],
            ['name' => 'Xanh dương', 'color_code' => '#0066FF'],
            ['name' => 'Xanh lá', 'color_code' => '#00CC00'],
            ['name' => 'Vàng', 'color_code' => '#FFFF00'],
            ['name' => 'Đen', 'color_code' => '#000000'],
            ['name' => 'Trắng', 'color_code' => '#FFFFFF'],
            ['name' => 'Xám', 'color_code' => '#808080'],
            ['name' => 'Nâu', 'color_code' => '#8B4513'],
            ['name' => 'Hồng', 'color_code' => '#FF69B4'],
            ['name' => 'Tím', 'color_code' => '#800080'],
            ['name' => 'Cam', 'color_code' => '#FF8C00'],
            ['name' => 'Xanh navy', 'color_code' => '#000080'],
            ['name' => 'Be', 'color_code' => '#F5F5DC'],
            ['name' => 'Bạc', 'color_code' => '#C0C0C0'],
            ['name' => 'Vàng gold', 'color_code' => '#FFD700'],
        ];
        
        $color_stmt = $pdo->prepare("INSERT INTO colors (name, color_code) VALUES (?, ?)");
        foreach ($colors_data as $color) {
            $color_stmt->execute([$color['name'], $color['color_code']]);
        }
        
        $success_messages[] = "✅ Thêm " . count($colors_data) . " màu sắc mẫu thành công!";
    }
    
} catch (Exception $e) {
    $error_messages[] = "❌ Lỗi: " . $e->getMessage();
}

?>

<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Setup Categories - TKT Admin</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
    <style>
        body {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            min-height: 100vh;
            font-family: 'Inter', sans-serif;
        }
        
        .setup-container {
            max-width: 800px;
            margin: 50px auto;
            padding: 20px;
        }
        
        .setup-card {
            background: white;
            border-radius: 20px;
            box-shadow: 0 20px 40px rgba(0,0,0,0.1);
            padding: 40px;
            text-align: center;
        }
        
        .setup-icon {
            width: 80px;
            height: 80px;
            background: linear-gradient(135deg, #667eea, #764ba2);
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            margin: 0 auto 30px;
            color: white;
            font-size: 2rem;
        }
        
        .message-list {
            text-align: left;
            max-height: 400px;
            overflow-y: auto;
            background: #f8f9fa;
            border-radius: 10px;
            padding: 20px;
            margin: 20px 0;
        }
        
        .message-item {
            padding: 10px 0;
            border-bottom: 1px solid #e9ecef;
            font-size: 0.95rem;
        }
        
        .message-item:last-child {
            border-bottom: none;
        }
        
        .btn-custom {
            background: linear-gradient(135deg, #667eea, #764ba2);
            border: none;
            padding: 12px 30px;
            border-radius: 25px;
            color: white;
            font-weight: 600;
            transition: all 0.3s ease;
        }
        
        .btn-custom:hover {
            transform: translateY(-2px);
            box-shadow: 0 8px 25px rgba(102, 126, 234, 0.4);
            color: white;
        }
        
        .category-preview {
            text-align: left;
            background: white;
            border: 1px solid #e9ecef;
            border-radius: 10px;
            padding: 15px;
            margin-top: 20px;
        }
        
        .category-item {
            padding: 8px 12px;
            margin: 5px 0;
            background: #f8f9fa;
            border-radius: 6px;
            font-size: 0.9rem;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        
        .category-parent {
            font-weight: 600;
            background: #e3f2fd;
        }
        
        .category-child {
            margin-left: 20px;
            background: #f3e5f5;
        }
        
        .badge-status {
            font-size: 0.7rem;
            padding: 4px 8px;
        }
    </style>
</head>
<body>
    <div class="setup-container">
        <div class="setup-card">
            <div class="setup-icon">
                <i class="fas fa-database"></i>
            </div>
            
            <h2 class="mb-4">Setup Dữ liệu Danh mục</h2>
            <p class="text-muted mb-4">Tạo bảng và dữ liệu mẫu cho hệ thống quản lý sản phẩm</p>
            
            <?php if (!empty($success_messages) || !empty($error_messages)): ?>
                <div class="message-list">
                    <?php foreach ($success_messages as $message): ?>
                        <div class="message-item text-success">
                            <?= $message ?>
                        </div>
                    <?php endforeach; ?>
                    
                    <?php foreach ($error_messages as $message): ?>
                        <div class="message-item text-danger">
                            <?= $message ?>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
            
            <?php if (empty($error_messages)): ?>
                <!-- Hiển thị danh sách danh mục đã tạo -->
                <?php
                try {
                    $categories = $pdo->query("
                        SELECT c1.*, c2.name as parent_name 
                        FROM categories c1 
                        LEFT JOIN categories c2 ON c1.parent_id = c2.id 
                        ORDER BY c1.parent_id, c1.sort_order
                    ")->fetchAll();
                    
                    if (!empty($categories)):
                ?>
                    <div class="category-preview">
                        <h5 class="mb-3">
                            <i class="fas fa-list me-2"></i>
                            Danh sách danh mục (<?= count($categories) ?>)
                        </h5>
                        
                        <?php foreach ($categories as $category): ?>
                            <div class="category-item <?= $category['parent_id'] == 0 ? 'category-parent' : 'category-child' ?>">
                                <div>
                                    <?php if ($category['parent_id'] > 0): ?>
                                        <i class="fas fa-arrow-right me-2 text-muted"></i>
                                    <?php endif; ?>
                                    <strong><?= htmlspecialchars($category['name']) ?></strong>
                                    <?php if ($category['parent_name']): ?>
                                        <small class="text-muted">(<?= htmlspecialchars($category['parent_name']) ?>)</small>
                                    <?php endif; ?>
                                </div>
                                <span class="badge bg-success badge-status"><?= $category['status'] ?></span>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php 
                    endif;
                } catch (Exception $e) {
                    echo '<div class="alert alert-warning">Không thể hiển thị danh sách danh mục: ' . $e->getMessage() . '</div>';
                }
                ?>
                
                <div class="mt-4">
                    <a href="products/add.php" class="btn btn-custom me-3">
                        <i class="fas fa-plus me-2"></i>
                        Thêm sản phẩm ngay
                    </a>
                    <a href="categories/index.php" class="btn btn-outline-primary">
                        <i class="fas fa-cog me-2"></i>
                        Quản lý danh mục
                    </a>
                </div>
            <?php else: ?>
                <div class="mt-4">
                    <button onclick="window.location.reload()" class="btn btn-custom">
                        <i class="fas fa-redo me-2"></i>
                        Thử lại
                    </button>
                </div>
            <?php endif; ?>
            
            <div class="mt-4 pt-4 border-top">
                <small class="text-muted">
                    <i class="fas fa-info-circle me-1"></i>
                    Chạy file này để khởi tạo dữ liệu mẫu cho hệ thống
                </small>
            </div>
        </div>
    </div>
    
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>