<?php
/**
 * TKT SHOP - IMAGE DEBUG SYSTEM
 * File: debug_images.php
 * Chức năng: Kiểm tra toàn bộ hệ thống ảnh, tạo thư mục, báo cáo thiếu ảnh
 */

error_reporting(E_ALL);
ini_set('display_errors', 1);

// Include database connection
try {
    require_once 'config/database.php';
} catch (Exception $e) {
    die("❌ Không thể kết nối database: " . $e->getMessage());
}

// Cấu hình đường dẫn
define('BASE_PATH', __DIR__);
define('UPLOAD_BASE', BASE_PATH . '/uploads');
define('WEB_BASE', '/tktshop');

$config = [
    'directories' => [
        'uploads' => UPLOAD_BASE,
        'products' => UPLOAD_BASE . '/products',
        'main' => UPLOAD_BASE . '/products/main',
        'gallery' => UPLOAD_BASE . '/products/gallery', 
        'thumbnails' => UPLOAD_BASE . '/products/thumbnails',
        'categories' => UPLOAD_BASE . '/categories',
        'users' => UPLOAD_BASE . '/users',
        'reviews' => UPLOAD_BASE . '/reviews',
        'delivery' => UPLOAD_BASE . '/delivery',
        'brands' => UPLOAD_BASE . '/brands',
        'temp' => UPLOAD_BASE . '/temp',
        'assets' => BASE_PATH . '/assets/images'
    ],
    'image_types' => ['jpg', 'jpeg', 'png', 'webp', 'gif'],
    'required_files' => [
        'assets/images/no-image.jpg',
        'assets/images/logo.png',
        'assets/images/placeholder.png'
    ]
];

// AJAX Handlers - Phải đặt trước HTML
if (isset($_GET['action']) || isset($_POST['action'])) {
    header('Content-Type: application/json');
    
    $action = $_GET['action'] ?? $_POST['action'] ?? '';
    
    switch ($action) {
        case 'check_system':
            echo json_encode(checkSystemStatus());
            break;
            
        case 'create_directories':
            echo json_encode(createDirectoriesStructure());
            break;
            
        case 'generate_demo_images':
            echo json_encode(generateDemoImages());
            break;
            
        case 'analyze_database':
            echo json_encode(analyzeDatabase());
            break;
            
        case 'get_products':
            echo json_encode(getProductsWithImageStatus());
            break;
            
        case 'upload_images':
            echo json_encode(handleImageUpload());
            break;
            
        case 'batch_action':
            echo json_encode(handleBatchAction());
            break;
            
        case 'optimize_images':
            echo json_encode(optimizeImages());
            break;
            
        case 'fix_missing_images':
            echo json_encode(fixMissingImages());
            break;
            
        case 'cleanup_orphaned':
            echo json_encode(cleanupOrphanedImages());
            break;
            
        default:
            echo json_encode(['error' => 'Invalid action: ' . $action]);
    }
    exit;
}

// Helper functions
function checkSystemStatus() {
    global $config, $pdo;
    
    try {
        // Check directories
        $directories = [];
        $total_directories = 0;
        $directories_exist = true;
        
        foreach ($config['directories'] as $name => $path) {
            $exists = is_dir($path);
            $file_count = 0;
            $writable = false;
            
            if ($exists) {
                $files = glob($path . '/*');
                $file_count = is_array($files) ? count($files) : 0;
                $writable = is_writable($path);
            } else {
                $directories_exist = false;
            }
            
            $directories[$name] = [
                'exists' => $exists,
                'path' => $path,
                'file_count' => $file_count,
                'writable' => $writable
            ];
            $total_directories++;
        }
        
        // Check products and images
        $stmt = $pdo->query("SELECT COUNT(*) FROM san_pham_chinh WHERE trang_thai = 'hoat_dong'");
        $total_products = $stmt->fetchColumn();
        
        $stmt = $pdo->query("SELECT COUNT(*) FROM san_pham_chinh WHERE hinh_anh_chinh IS NOT NULL AND hinh_anh_chinh != '' AND trang_thai = 'hoat_dong'");
        $products_with_images = $stmt->fetchColumn();
        
        $missing_images = $total_products - $products_with_images;
        
        // Calculate total images
        $total_images = 0;
        if (is_dir($config['directories']['main'])) {
            $images = glob($config['directories']['main'] . '/*.{jpg,jpeg,png,webp}', GLOB_BRACE);
            $total_images += count($images);
        }
        
        // Calculate total size
        $total_size = calculateDirectorySize($config['directories']['uploads']);
        
        return [
            'success' => true,
            'stats' => [
                'total_products' => $total_products,
                'complete_products' => $products_with_images,
                'missing_images' => $missing_images,
                'total_images' => $total_images,
                'total_directories' => $total_directories,
                'directories_exist' => $directories_exist,
                'total_size' => formatBytes($total_size)
            ],
            'directories' => $directories
        ];
    } catch (Exception $e) {
        return ['success' => false, 'error' => $e->getMessage()];
    }
}

function createDirectoriesStructure() {
    global $config;
    
    $created = [];
    $existing = [];
    $errors = [];
    
    foreach ($config['directories'] as $name => $path) {
        if (!is_dir($path)) {
            if (@mkdir($path, 0755, true)) {
                $created[] = $path;
                
                // Create .htaccess for security
                if (strpos($name, 'uploads') !== false) {
                    $htaccess = $path . '/.htaccess';
                    if (!file_exists($htaccess)) {
                        file_put_contents($htaccess, "Options -Indexes\nDeny from all\n<Files ~ \"\\.(jpg|jpeg|png|gif|webp)$\">\nOrder allow,deny\nAllow from all\n</Files>");
                    }
                }
            } else {
                $errors[] = "Không thể tạo: $path";
            }
        } else {
            $existing[] = $path;
        }
    }
    
    // Create required files
    createRequiredFiles();
    
    return [
        'success' => true,
        'created' => $created,
        'existing' => $existing,
        'errors' => $errors
    ];
}

function generateDemoImages() {
    global $config, $pdo;
    
    if (!extension_loaded('gd')) {
        return ['success' => false, 'message' => 'GD Extension không được cài đặt'];
    }
    
    $products = [
        ['name' => 'Nike Air Max 270', 'brand' => 'Nike', 'color' => '#000000'],
        ['name' => 'Adidas Ultraboost 22', 'brand' => 'Adidas', 'color' => '#FFFFFF'],
        ['name' => 'Converse Chuck Taylor', 'brand' => 'Converse', 'color' => '#FF0000'],
        ['name' => 'Vans Old Skool', 'brand' => 'Vans', 'color' => '#000000'],
        ['name' => 'Puma RS-X', 'brand' => 'Puma', 'color' => '#0000FF'],
        ['name' => 'New Balance 990', 'brand' => 'New Balance', 'color' => '#808080'],
        ['name' => 'Jordan Air 1', 'brand' => 'Jordan', 'color' => '#FF0000'],
        ['name' => 'Reebok Classic', 'brand' => 'Reebok', 'color' => '#FFFFFF']
    ];
    
    $created = [];
    $sql_updates = [];
    
    foreach ($products as $index => $product) {
        $productId = $index + 1;
        $mainFilename = "demo_product_{$productId}.jpg";
        
        try {
            // Create main image
            $mainImage = createDemoShoeImage(800, 800, $product['name'], $product['brand'], $product['color']);
            if (imagejpeg($mainImage, $config['directories']['main'] . '/' . $mainFilename, 90)) {
                $created[] = "main/$mainFilename";
            }
            imagedestroy($mainImage);
            
            // Create thumbnail
            $thumbImage = createDemoShoeImage(300, 300, $product['name'], $product['brand'], $product['color']);
            if (imagejpeg($thumbImage, $config['directories']['thumbnails'] . '/' . $mainFilename, 90)) {
                $created[] = "thumbnails/$mainFilename";
            }
            imagedestroy($thumbImage);
            
            // Create gallery images
            $galleryImages = [];
            for ($i = 0; $i < 3; $i++) {
                $galleryFilename = "demo_product_{$productId}_gallery_{$i}.jpg";
                $galleryImage = createDemoShoeImage(600, 600, $product['name'], $product['brand'], $product['color'], $i * 15);
                if (imagejpeg($galleryImage, $config['directories']['gallery'] . '/' . $galleryFilename, 90)) {
                    $created[] = "gallery/$galleryFilename";
                    $galleryImages[] = $galleryFilename;
                }
                imagedestroy($galleryImage);
            }
            
            // Update database if product exists
            $stmt = $pdo->prepare("SELECT id FROM san_pham_chinh WHERE id = ?");
            $stmt->execute([$productId]);
            if ($stmt->fetch()) {
                $galleryJson = json_encode($galleryImages);
                $updateStmt = $pdo->prepare("UPDATE san_pham_chinh SET hinh_anh_chinh = ?, album_hinh_anh = ? WHERE id = ?");
                $updateStmt->execute([$mainFilename, $galleryJson, $productId]);
                $sql_updates[] = "Updated product ID $productId";
            }
            
        } catch (Exception $e) {
            continue; // Skip on error
        }
    }
    
    return [
        'success' => true,
        'created' => $created,
        'sql_updates' => $sql_updates
    ];
}

function analyzeDatabase() {
    global $pdo;
    
    try {
        // Get products stats
        $stmt = $pdo->query("SELECT COUNT(*) FROM san_pham_chinh WHERE trang_thai = 'hoat_dong'");
        $total_products = $stmt->fetchColumn();
        
        $stmt = $pdo->query("SELECT COUNT(*) FROM san_pham_chinh WHERE hinh_anh_chinh IS NOT NULL AND hinh_anh_chinh != '' AND trang_thai = 'hoat_dong'");
        $products_with_main_image = $stmt->fetchColumn();
        
        $stmt = $pdo->query("SELECT COUNT(*) FROM san_pham_chinh WHERE album_hinh_anh IS NOT NULL AND album_hinh_anh != '' AND album_hinh_anh != '[]' AND trang_thai = 'hoat_dong'");
        $products_with_gallery = $stmt->fetchColumn();
        
        $missing_main_image = $total_products - $products_with_main_image;
        
        // Check for orphaned images
        $orphaned_images = [];
        $missing_files = [];
        
        // Get all images from database
        $stmt = $pdo->query("SELECT hinh_anh_chinh, album_hinh_anh FROM san_pham_chinh WHERE (hinh_anh_chinh IS NOT NULL OR album_hinh_anh IS NOT NULL) AND trang_thai = 'hoat_dong'");
        $db_images = [];
        
        while ($row = $stmt->fetch()) {
            if ($row['hinh_anh_chinh']) {
                $db_images[] = $row['hinh_anh_chinh'];
            }
            if ($row['album_hinh_anh']) {
                $gallery = json_decode($row['album_hinh_anh'], true);
                if (is_array($gallery)) {
                    $db_images = array_merge($db_images, $gallery);
                }
            }
        }
        
        // Get all physical images
        $physical_images = [];
        $image_dirs = ['main', 'gallery', 'thumbnails'];
        
        foreach ($image_dirs as $dir) {
            $path = __DIR__ . "/uploads/products/$dir";
            if (is_dir($path)) {
                $files = glob($path . '/*.{jpg,jpeg,png,webp}', GLOB_BRACE);
                foreach ($files as $file) {
                    $physical_images[] = basename($file);
                }
            }
        }
        
        // Find orphaned and missing
        $orphaned_images = array_diff($physical_images, $db_images);
        $missing_files = array_diff($db_images, $physical_images);
        
        return [
            'success' => true,
            'total_products' => $total_products,
            'products_with_main_image' => $products_with_main_image,
            'products_with_gallery' => $products_with_gallery,
            'missing_main_image' => $missing_main_image,
            'orphaned_images' => array_values($orphaned_images),
            'missing_files' => array_values($missing_files)
        ];
    } catch (Exception $e) {
        return ['success' => false, 'error' => $e->getMessage()];
    }
}

function getProductsWithImageStatus() {
    global $pdo;
    
    try {
        $stmt = $pdo->query("
            SELECT 
                id, 
                ten_san_pham, 
                thuong_hieu,
                hinh_anh_chinh,
                album_hinh_anh,
                trang_thai,
                CASE 
                    WHEN hinh_anh_chinh IS NOT NULL AND hinh_anh_chinh != '' AND 
                         album_hinh_anh IS NOT NULL AND album_hinh_anh != '' AND album_hinh_anh != '[]' 
                    THEN 'complete'
                    WHEN hinh_anh_chinh IS NOT NULL AND hinh_anh_chinh != '' 
                    THEN 'partial'
                    ELSE 'missing'
                END as image_status
            FROM san_pham_chinh 
            WHERE trang_thai = 'hoat_dong'
            ORDER BY 
                CASE 
                    WHEN hinh_anh_chinh IS NULL OR hinh_anh_chinh = '' THEN 0
                    WHEN album_hinh_anh IS NULL OR album_hinh_anh = '' OR album_hinh_anh = '[]' THEN 1
                    ELSE 2
                END,
                id
        ");
        
        $products = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        // Add additional image info
        foreach ($products as &$product) {
            $product['main_image'] = $product['hinh_anh_chinh'];
            $product['gallery_images'] = $product['album_hinh_anh'];
            
            // Check if main image file actually exists
            if ($product['main_image']) {
                $main_path = __DIR__ . "/uploads/products/main/" . $product['main_image'];
                if (!file_exists($main_path)) {
                    $product['image_status'] = 'missing';
                    $product['main_image_exists'] = false;
                } else {
                    $product['main_image_exists'] = true;
                }
            }
        }
        
        return ['success' => true, 'products' => $products];
    } catch (Exception $e) {
        return ['success' => false, 'error' => $e->getMessage()];
    }
}

function handleImageUpload() {
    return ['success' => false, 'message' => 'Upload feature coming soon'];
}

function handleBatchAction() {
    return ['success' => false, 'message' => 'Batch action feature coming soon'];
}

function optimizeImages() {
    global $config;
    
    $optimized = 0;
    $spaceSaved = 0;
    
    $dirs = ['main', 'gallery', 'thumbnails'];
    
    foreach ($dirs as $dir) {
        $path = $config['directories'][$dir];
        if (!is_dir($path)) continue;
        
        $images = glob($path . '/*.{jpg,jpeg}', GLOB_BRACE);
        
        foreach ($images as $imagePath) {
            $originalSize = filesize($imagePath);
            
            // Re-save with 85% quality
            $image = imagecreatefromjpeg($imagePath);
            if ($image) {
                if (imagejpeg($image, $imagePath, 85)) {
                    $newSize = filesize($imagePath);
                    $spaceSaved += ($originalSize - $newSize);
                    $optimized++;
                }
                imagedestroy($image);
            }
        }
    }
    
    return [
        'success' => true,
        'optimized_count' => $optimized,
        'space_saved' => formatBytes($spaceSaved)
    ];
}

function fixMissingImages() {
    global $pdo;
    
    try {
        $stmt = $pdo->query("SELECT id, ten_san_pham FROM san_pham_chinh WHERE (hinh_anh_chinh IS NULL OR hinh_anh_chinh = '') AND trang_thai = 'hoat_dong'");
        $missingProducts = $stmt->fetchAll();
        
        $fixed = 0;
        
        foreach ($missingProducts as $product) {
            $filename = "placeholder_product_{$product['id']}.jpg";
            if (createProductPlaceholder($product['id'], $product['ten_san_pham'], $filename)) {
                $updateStmt = $pdo->prepare("UPDATE san_pham_chinh SET hinh_anh_chinh = ? WHERE id = ?");
                $updateStmt->execute([$filename, $product['id']]);
                $fixed++;
            }
        }
        
        return [
            'success' => true,
            'fixed_count' => $fixed,
            'message' => "Fixed $fixed missing images"
        ];
        
    } catch (Exception $e) {
        return ['success' => false, 'error' => $e->getMessage()];
    }
}

function cleanupOrphanedImages() {
    global $pdo, $config;
    
    try {
        // Get all images referenced in database
        $stmt = $pdo->query("SELECT hinh_anh_chinh, album_hinh_anh FROM san_pham_chinh WHERE trang_thai = 'hoat_dong'");
        $dbImages = [];
        
        while ($row = $stmt->fetch()) {
            if ($row['hinh_anh_chinh']) {
                $dbImages[] = $row['hinh_anh_chinh'];
            }
            if ($row['album_hinh_anh']) {
                $gallery = json_decode($row['album_hinh_anh'], true);
                if (is_array($gallery)) {
                    $dbImages = array_merge($dbImages, $gallery);
                }
            }
        }
        
        $deleted = 0;
        $spaceSaved = 0;
        $dirs = ['main', 'gallery', 'thumbnails'];
        
        foreach ($dirs as $dir) {
            $path = $config['directories'][$dir];
            if (!is_dir($path)) continue;
            
            $files = glob($path . '/*');
            foreach ($files as $file) {
                $filename = basename($file);
                
                // Skip if referenced in database or is demo/placeholder
                if (in_array($filename, $dbImages) || 
                    strpos($filename, 'demo_') === 0 || 
                    strpos($filename, 'placeholder_') === 0) {
                    continue;
                }
                
                $fileSize = filesize($file);
                if (unlink($file)) {
                    $deleted++;
                    $spaceSaved += $fileSize;
                }
            }
        }
        
        return [
            'success' => true,
            'deleted_count' => $deleted,
            'space_saved' => formatBytes($spaceSaved)
        ];
        
    } catch (Exception $e) {
        return ['success' => false, 'error' => $e->getMessage()];
    }
}

function createRequiredFiles() {
    $files = [
        'assets/images/no-image.jpg' => [400, 400, 'No Image Available'],
        'assets/images/placeholder.png' => [300, 300, 'Placeholder'],
        'assets/images/logo.png' => [200, 80, 'TKT SHOP']
    ];
    
    foreach ($files as $path => [$width, $height, $text]) {
        $fullPath = __DIR__ . '/' . $path;
        $dir = dirname($fullPath);
        
        if (!is_dir($dir)) {
            mkdir($dir, 0755, true);
        }
        
        if (!file_exists($fullPath)) {
            createPlaceholderImage($fullPath, $width, $height, $text);
        }
    }
}

function createPlaceholderImage($path, $width, $height, $text) {
    $image = imagecreatetruecolor($width, $height);
    
    // Colors
    $bg = imagecolorallocate($image, 248, 249, 250);
    $border = imagecolorallocate($image, 222, 226, 230);
    $textColor = imagecolorallocate($image, 108, 117, 125);
    
    // Fill background
    imagefill($image, 0, 0, $bg);
    
    // Draw border
    imagerectangle($image, 0, 0, $width-1, $height-1, $border);
    
    // Add text
    $fontSize = max(3, $width / 20);
    $textX = ($width - strlen($text) * imagefontwidth($fontSize)) / 2;
    $textY = ($height - imagefontheight($fontSize)) / 2;
    
    imagestring($image, $fontSize, $textX, $textY, $text, $textColor);
    
    // Add icon
    $iconSize = min($width, $height) / 8;
    $iconX = ($width - $iconSize) / 2;
    $iconY = $textY - $iconSize - 10;
    
    // Simple camera icon
    imagerectangle($image, $iconX, $iconY, $iconX + $iconSize, $iconY + $iconSize * 0.7, $textColor);
    imagefilledellipse($image, $iconX + $iconSize/2, $iconY + $iconSize * 0.35, $iconSize * 0.4, $iconSize * 0.4, $textColor);
    
    // Save image
    $ext = strtolower(pathinfo($path, PATHINFO_EXTENSION));
    switch ($ext) {
        case 'png':
            imagepng($image, $path);
            break;
        case 'jpg':
        case 'jpeg':
        default:
            imagejpeg($image, $path, 90);
            break;
    }
    
    imagedestroy($image);
}

function createProductPlaceholder($productId, $productName, $filename) {
    global $config;
    
    try {
        $image = imagecreatetruecolor(800, 800);
        
        // Gradient background
        for ($i = 0; $i < 800; $i++) {
            $ratio = $i / 800;
            $r = 248 + ($ratio * (230 - 248));
            $g = 249 + ($ratio * (230 - 249));
            $b = 250 + ($ratio * (230 - 250));
            $color = imagecolorallocate($image, $r, $g, $b);
            imageline($image, 0, $i, 800, $i, $color);
        }
        
        // Product info
        $textColor = imagecolorallocate($image, 100, 100, 100);
        $accentColor = imagecolorallocate($image, 0, 123, 255);
        
        // Product ID
        imagestring($image, 3, 350, 300, "ID: $productId", $textColor);
        
        // Product name
        $displayName = strlen($productName) > 20 ? substr($productName, 0, 20) . '...' : $productName;
        imagestring($image, 4, 320, 350, $displayName, $accentColor);
        
        // Placeholder text
        imagestring($image, 3, 320, 400, "Image Coming Soon", $textColor);
        
        // TKT watermark
        imagestring($image, 2, 350, 450, "TKT SHOP", $accentColor);
        
        // Save all sizes
        $paths = [
            $config['directories']['main'] . '/' . $filename,
            $config['directories']['thumbnails'] . '/' . $filename,
            $config['directories']['gallery'] . '/' . $filename
        ];
        
        $success = true;
        foreach ($paths as $path) {
            if (!imagejpeg($image, $path, 90)) {
                $success = false;
            }
        }
        
        imagedestroy($image);
        return $success;
        
    } catch (Exception $e) {
        return false;
    }
}

function createDemoShoeImage($width, $height, $name, $brand, $colorHex, $angle = 0) {
    $image = imagecreatetruecolor($width, $height);
    
    // White background
    $white = imagecolorallocate($image, 255, 255, 255);
    imagefill($image, 0, 0, $white);
    
    // Convert hex to RGB
    $rgb = hexToRgb($colorHex);
    $shoeColor = imagecolorallocate($image, $rgb['r'], $rgb['g'], $rgb['b']);
    
    // Center coordinates
    $centerX = $width / 2;
    $centerY = $height / 2;
    
    // Scale based on image size
    $scale = min($width, $height) / 400;
    
    // Draw shadow
    $shadow = imagecolorallocatealpha($image, 0, 0, 0, 50);
    imagefilledellipse($image, $centerX + 5, $centerY + 50 * $scale, $width * 0.6, $height * 0.08, $shadow);
    
    // Draw shoe body
    $shoeWidth = $width * 0.6;
    $shoeHeight = $height * 0.3;
    
    // Rotate shoe slightly based on angle
    $offsetX = sin(deg2rad($angle)) * 20;
    $offsetY = cos(deg2rad($angle)) * 10;
    
    // Main shoe shape
    imagefilledellipse($image, $centerX + $offsetX, $centerY + $offsetY, $shoeWidth, $shoeHeight, $shoeColor);
    
    // Shoe sole
    $soleColor = imagecolorallocate($image, 50, 50, 50);
    imagefilledellipse($image, $centerX + $offsetX, $centerY + $shoeHeight * 0.35 + $offsetY, $shoeWidth * 0.9, $shoeHeight * 0.2, $soleColor);
    
    // Shoe details
    $detailColor = $colorHex === '#FFFFFF' ? imagecolorallocate($image, 200, 200, 200) : imagecolorallocate($image, 255, 255, 255);
    
    // Laces
    for ($i = 0; $i < 4; $i++) {
        $laceX = $centerX - 40 * $scale + ($i * 20 * $scale) + $offsetX;
        $laceY = $centerY - 30 * $scale + $offsetY;
        imagefilledellipse($image, $laceX, $laceY, 6 * $scale, 6 * $scale, $detailColor);
    }
    
    // Brand logo
    $logoX = $centerX + 30 * $scale + $offsetX;
    $logoY = $centerY - 10 * $scale + $offsetY;
    imagearc($image, $logoX, $logoY, 40 * $scale, 20 * $scale, 0, 180, $detailColor);
    
    // Add text
    $textColor = imagecolorallocate($image, 80, 80, 80);
    $brandColor = imagecolorallocate($image, 0, 123, 255);
    
    // Font sizes
    $brandFontSize = max(2, $width / 60);
    $nameFontSize = max(3, $width / 40);
    
    // Brand name
    $brandWidth = strlen($brand) * imagefontwidth($brandFontSize);
    $brandX = ($width - $brandWidth) / 2;
    imagestring($image, $brandFontSize, $brandX, $height - 60, $brand, $brandColor);
    
    // Product name
    $displayName = strlen($name) > 25 ? substr($name, 0, 25) . '...' : $name;
    $nameWidth = strlen($displayName) * imagefontwidth($nameFontSize);
    $nameX = ($width - $nameWidth) / 2;
    imagestring($image, $nameFontSize, $nameX, $height - 40, $displayName, $textColor);
    
    // TKT Shop watermark
    $watermarkColor = imagecolorallocatealpha($image, 0, 123, 255, 50);
    imagestring($image, 2, $width - 80, $height - 15, 'TKT SHOP', $watermarkColor);
    
    return $image;
}

function hexToRgb($hex) {
    $hex = ltrim($hex, '#');
    
    if (strlen($hex) == 3) {
        $hex = $hex[0] . $hex[0] . $hex[1] . $hex[1] . $hex[2] . $hex[2];
    }
    
    return [
        'r' => hexdec(substr($hex, 0, 2)),
        'g' => hexdec(substr($hex, 2, 2)),
        'b' => hexdec(substr($hex, 4, 2))
    ];
}

function calculateDirectorySize($dir) {
    $size = 0;
    if (is_dir($dir)) {
        try {
            $files = new RecursiveIteratorIterator(
                new RecursiveDirectoryIterator($dir, RecursiveDirectoryIterator::SKIP_DOTS)
            );
            
            foreach ($files as $file) {
                $size += $file->getSize();
            }
        } catch (Exception $e) {
            return 0;
        }
    }
    return $size;
}

function formatBytes($size, $precision = 2) {
    if ($size <= 0) return '0 B';
    
    $base = log($size, 1024);
    $suffixes = ['B', 'KB', 'MB', 'GB', 'TB'];
    
    return round(pow(1024, $base - floor($base)), $precision) . ' ' . $suffixes[floor($base)];
}

// Auto-create basic structure on first run
if (!is_dir(__DIR__ . '/uploads')) {
    createDirectoriesStructure();
}

// Check if GD extension is loaded
if (!extension_loaded('gd')) {
    $gd_error = "❌ GD Extension không được cài đặt. Một số tính năng sẽ không hoạt động.";
}

// Check write permissions
if (!is_writable(__DIR__)) {
    $permission_error = "⚠️ Thư mục không có quyền ghi. Vui lòng chmod 755 hoặc 777.";
}
?>

<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>🔍 TKT Shop - Image Debug System</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <style>
        .debug-header {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            padding: 30px 0;
            margin-bottom: 30px;
        }
        .stat-card {
            background: white;
            border-radius: 10px;
            padding: 20px;
            margin-bottom: 20px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
            border-left: 4px solid #007bff;
        }
        .stat-card.success { border-left-color: #28a745; }
        .stat-card.warning { border-left-color: #ffc107; }
        .stat-card.danger { border-left-color: #dc3545; }
        .product-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(300px, 1fr));
            gap: 20px;
            margin-top: 20px;
        }
        .product-card {
            border: 1px solid #ddd;
            border-radius: 8px;
            overflow: hidden;
            background: white;
        }
        .product-image {
            width: 100%;
            height: 200px;
            object-fit: cover;
            background: #f8f9fa;
        }
        .no-image {
            width: 100%;
            height: 200px;
            background: #f8f9fa;
            display: flex;
            align-items: center;
            justify-content: center;
            color: #6c757d;
            border: 2px dashed #ddd;
        }
        .action-buttons {
            position: sticky;
            top: 20px;
            background: white;
            padding: 15px;
            border-radius: 10px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
            margin-bottom: 20px;
        }
        .log-entry {
            padding: 10px;
            margin: 5px 0;
            border-radius: 5px;
            font-family: monospace;
        }
        .log-success { background: #d4edda; color: #155724; }
        .log-warning { background: #fff3cd; color: #856404; }
        .log-error { background: #f8d7da; color: #721c24; }
        .log-info { background: #d1ecf1; color: #0c5460; }
        
        .file-tree {
            font-family: monospace;
            background: #f8f9fa;
            padding: 15px;
            border-radius: 5px;
            white-space: pre-line;
        }
        
        .progress-container {
            margin: 20px 0;
        }
        
        .image-preview {
            max-width: 100px;
            max-height: 100px;
            border-radius: 5px;
            margin: 5px;
        }

        @media (max-width: 768px) {
            .action-buttons .btn-group {
                display: flex;
                flex-direction: column;
                width: 100%;
            }
            
            .action-buttons .btn {
                margin-bottom: 5px;
                width: 100%;
            }
            
            .product-grid {
                grid-template-columns: 1fr;
            }
            
            .debug-header h1 {
                font-size: 1.5rem;
            }
        }
    </style>
</head>
<body>
    <div class="debug-header text-center">
        <div class="container">
            <h1><i class="fas fa-search"></i> TKT Shop - Image Debug System</h1>
            <p class="mb-0">Kiểm tra và quản lý toàn bộ hệ thống ảnh</p>
        </div>
    </div>

    <div class="container-fluid">
        <!-- Error notifications -->
        <?php if (isset($gd_error)): ?>
            <div class="alert alert-danger"><?= $gd_error ?></div>
        <?php endif; ?>
        
        <?php if (isset($permission_error)): ?>
            <div class="alert alert-warning"><?= $permission_error ?></div>
        <?php endif; ?>

        <!-- Action Buttons -->
        <div class="action-buttons">
            <div class="row">
                <div class="col-md-8">
                    <div class="btn-group" role="group">
                        <button class="btn btn-primary" onclick="checkSystem()">
                            <i class="fas fa-search"></i> Kiểm tra hệ thống
                        </button>
                        <button class="btn btn-success" onclick="createDirectories()">
                            <i class="fas fa-folder-plus"></i> Tạo thư mục
                        </button>
                        <button class="btn btn-warning" onclick="generateDemoImages()">
                            <i class="fas fa-images"></i> Tạo ảnh demo
                        </button>
                        <button class="btn btn-info" onclick="analyzeDatabase()">
                            <i class="fas fa-database"></i> Phân tích DB
                        </button>
                        <button class="btn btn-secondary" onclick="clearLog()">
                            <i class="fas fa-trash"></i> Xóa log
                        </button>
                    </div>
                </div>
                <div class="col-md-4">
                    <div class="form-check form-switch">
                        <input class="form-check-input" type="checkbox" id="autoRefresh">
                        <label class="form-check-label" for="autoRefresh">Auto refresh (5s)</label>
                    </div>
                </div>
            </div>
            
            <!-- Additional buttons -->
            <div class="row mt-2">
                <div class="col-md-12">
                    <div class="btn-group" role="group">
                        <button class="btn btn-outline-primary" onclick="fixMissingImages()">
                            <i class="fas fa-wrench"></i> Fix thiếu ảnh
                        </button>
                        <button class="btn btn-outline-success" onclick="optimizeImages()">
                            <i class="fas fa-compress"></i> Tối ưu ảnh
                        </button>
                        <button class="btn btn-outline-danger" onclick="cleanupOrphaned()">
                            <i class="fas fa-broom"></i> Dọn ảnh thừa
                        </button>
                    </div>
                </div>
            </div>
        </div>

        <div class="row">
            <!-- Left Column: Statistics -->
            <div class="col-lg-4">
                <div id="systemStats">
                    <div class="stat-card">
                        <h5><i class="fas fa-info-circle"></i> Trạng thái</h5>
                        <p>Click "Kiểm tra hệ thống" để bắt đầu</p>
                    </div>
                </div>
                
                <div class="stat-card">
                    <h5><i class="fas fa-folder-tree"></i> Cấu trúc thư mục</h5>
                    <div id="directoryStructure" class="file-tree">
                        Đang tải...
                    </div>
                </div>
            </div>
            
            <!-- Right Column: Content -->
            <div class="col-lg-8">
                <!-- Log Section -->
                <div class="card mb-4">
                    <div class="card-header d-flex justify-content-between align-items-center">
                        <h5><i class="fas fa-terminal"></i> System Log</h5>
                        <small id="lastUpdate">Chưa kiểm tra</small>
                    </div>
                    <div class="card-body" style="max-height: 300px; overflow-y: auto;">
                        <div id="systemLog">
                            <div class="log-info">🚀 TKT Shop Image Debug System khởi động...</div>
                            <div class="log-info">📋 Click "Kiểm tra hệ thống" để bắt đầu phân tích</div>
                        </div>
                    </div>
                </div>

                <!-- Products Section -->
                <div class="card">
                    <div class="card-header d-flex justify-content-between align-items-center">
                        <h5><i class="fas fa-shopping-bag"></i> Sản phẩm & Ảnh</h5>
                        <div>
                            <select id="filterProducts" class="form-select form-select-sm" onchange="filterProducts()">
                                <option value="all">Tất cả sản phẩm</option>
                                <option value="missing">Thiếu ảnh</option>
                                <option value="complete">Đủ ảnh</option>
                                <option value="partial">Thiếu 1 số ảnh</option>
                            </select>
                        </div>
                    </div>
                    <div class="card-body">
                        <div id="productsGrid" class="product-grid">
                            <div class="text-center py-4">
                                <i class="fas fa-spinner fa-spin fa-2x text-muted"></i>
                                <p class="text-muted mt-2">Đang tải sản phẩm...</p>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        let autoRefreshInterval;
        
        // Auto refresh functionality
        document.getElementById('autoRefresh').addEventListener('change', function() {
            if (this.checked) {
                autoRefreshInterval = setInterval(checkSystem, 5000);
                addLog('🔄 Auto refresh enabled (5s)', 'info');
            } else {
                clearInterval(autoRefreshInterval);
                addLog('⏹️ Auto refresh disabled', 'info');
            }
        });

        // Initialize system check on load
        window.onload = function() {
            checkSystem();
        };

        function addLog(message, type = 'info') {
            const logContainer = document.getElementById('systemLog');
            const timestamp = new Date().toLocaleTimeString();
            const logEntry = document.createElement('div');
            logEntry.className = `log-${type}`;
            logEntry.innerHTML = `[${timestamp}] ${message}`;
            logContainer.appendChild(logEntry);
            logContainer.scrollTop = logContainer.scrollHeight;
        }

        function clearLog() {
            document.getElementById('systemLog').innerHTML = '';
            addLog('🧹 Log cleared', 'info');
        }

        function checkSystem() {
            addLog('🔍 Bắt đầu kiểm tra hệ thống...', 'info');
            
            fetch('debug_images.php?action=check_system')
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        updateSystemStats(data.stats);
                        updateDirectoryStructure(data.directories);
                        loadProducts();
                        
                        document.getElementById('lastUpdate').textContent = 
                            'Cập nhật lúc: ' + new Date().toLocaleTimeString();
                        
                        addLog(`✅ Kiểm tra hoàn tất: ${data.stats.total_products} sản phẩm, ${data.stats.missing_images} thiếu ảnh`, 'success');
                    } else {
                        addLog('❌ Lỗi: ' + data.error, 'error');
                    }
                })
                .catch(error => {
                    addLog('❌ Lỗi kiểm tra hệ thống: ' + error.message, 'error');
                });
        }

        function createDirectories() {
            addLog('📁 Tạo cấu trúc thư mục...', 'info');
            
            fetch('debug_images.php?action=create_directories')
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        data.created.forEach(dir => {
                            addLog(`✅ Tạo thành công: ${dir}`, 'success');
                        });
                        
                        data.existing.forEach(dir => {
                            addLog(`ℹ️ Đã tồn tại: ${dir}`, 'info');
                        });
                        
                        if (data.errors.length > 0) {
                            data.errors.forEach(error => {
                                addLog(`❌ ${error}`, 'error');
                            });
                        }
                        
                        checkSystem(); // Refresh after creation
                    } else {
                        addLog('❌ Lỗi tạo thư mục', 'error');
                    }
                })
                .catch(error => {
                    addLog('❌ Lỗi tạo thư mục: ' + error.message, 'error');
                });
        }

        function generateDemoImages() {
            addLog('🎨 Bắt đầu tạo ảnh demo...', 'info');
            
            const progressContainer = document.createElement('div');
            progressContainer.className = 'progress-container';
            progressContainer.innerHTML = `
                <div class="progress">
                    <div id="imageProgress" class="progress-bar progress-bar-striped progress-bar-animated" 
                         role="progressbar" style="width: 0%"></div>
                </div>
                <small id="progressText">Đang khởi tạo...</small>
            `;
            
            document.getElementById('systemLog').appendChild(progressContainer);
            
            fetch('debug_images.php?action=generate_demo_images')
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        data.created.forEach((file, index) => {
                            setTimeout(() => {
                                addLog(`🖼️ Tạo ảnh: ${file}`, 'success');
                                
                                const progress = ((index + 1) / data.created.length) * 100;
                                document.getElementById('imageProgress').style.width = progress + '%';
                                document.getElementById('progressText').textContent = 
                                    `Đã tạo ${index + 1}/${data.created.length} ảnh`;
                                
                                if (index === data.created.length - 1) {
                                    setTimeout(() => {
                                        progressContainer.remove();
                                        checkSystem();
                                        addLog(`🎉 Hoàn thành tạo ${data.created.length} ảnh demo!`, 'success');
                                        
                                        if (data.sql_updates.length > 0) {
                                            addLog(`📝 Cập nhật database: ${data.sql_updates.length} sản phẩm`, 'info');
                                        }
                                    }, 500);
                                }
                            }, index * 100);
                        });
                    } else {
                        addLog('❌ ' + data.message, 'error');
                        progressContainer.remove();
                    }
                })
                .catch(error => {
                    addLog('❌ Lỗi tạo ảnh demo: ' + error.message, 'error');
                    progressContainer.remove();
                });
        }

        function analyzeDatabase() {
            addLog('🔍 Phân tích database...', 'info');
            
            fetch('debug_images.php?action=analyze_database')
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        addLog(`📊 Tổng ${data.total_products} sản phẩm trong DB`, 'info');
                        addLog(`📸 ${data.products_with_main_image} có ảnh chính`, 'info');
                        addLog(`🖼️ ${data.products_with_gallery} có album ảnh`, 'info');
                        addLog(`❌ ${data.missing_main_image} thiếu ảnh chính`, 'warning');
                        
                        if (data.orphaned_images.length > 0) {
                            addLog(`🗑️ ${data.orphaned_images.length} ảnh không dùng đến`, 'warning');
                        }
                        
                        if (data.missing_files.length > 0) {
                            addLog(`📁 ${data.missing_files.length} file ảnh bị mất`, 'error');
                            data.missing_files.slice(0, 5).forEach(file => {
                                addLog(`   - ${file}`, 'error');
                            });
                            if (data.missing_files.length > 5) {
                                addLog(`   ... và ${data.missing_files.length - 5} file khác`, 'error');
                            }
                        }
                    } else {
                        addLog('❌ Lỗi: ' + data.error, 'error');
                    }
                })
                .catch(error => {
                    addLog('❌ Lỗi phân tích database: ' + error.message, 'error');
                });
        }

        function loadProducts() {
            fetch('debug_images.php?action=get_products')
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        displayProducts(data.products);
                    } else {
                        addLog('❌ Lỗi load sản phẩm: ' + data.error, 'error');
                    }
                })
                .catch(error => {
                    addLog('❌ Lỗi load sản phẩm: ' + error.message, 'error');
                });
        }

        function displayProducts(products) {
            const grid = document.getElementById('productsGrid');
            grid.innerHTML = '';
            
            if (products.length === 0) {
                grid.innerHTML = '<div class="text-center py-4"><p class="text-muted">Không có sản phẩm nào</p></div>';
                return;
            }
            
            products.forEach(product => {
                const card = createProductCard(product);
                grid.appendChild(card);
            });
        }

        function createProductCard(product) {
            const card = document.createElement('div');
            card.className = 'product-card';
            card.dataset.status = product.image_status;
            
            const imageSection = product.main_image ? 
                `<img src="/tktshop/uploads/products/main/${product.main_image}" class="product-image" 
                      onerror="this.parentElement.innerHTML='<div class=\\'no-image\\'>📷<br>Ảnh bị lỗi</div>'">` :
                `<div class="no-image"><i class="fas fa-image fa-2x"></i><br>Chưa có ảnh</div>`;
            
            const statusBadge = getStatusBadge(product.image_status);
            const galleryCount = product.gallery_images ? JSON.parse(product.gallery_images || '[]').length : 0;
            
            card.innerHTML = `
                ${imageSection}
                <div class="p-3">
                    <div class="d-flex justify-content-between align-items-start mb-2">
                        <h6 class="mb-1">${product.ten_san_pham}</h6>
                        ${statusBadge}
                    </div>
                    <small class="text-muted">ID: ${product.id} | ${product.thuong_hieu || 'Không có thương hiệu'}</small>
                    <div class="mt-2">
                        <small class="d-block">
                            📷 Ảnh chính: ${product.main_image ? '✅' : '❌'}
                        </small>
                        <small class="d-block">
                            🖼️ Album: ${galleryCount} ảnh
                        </small>
                    </div>
                    <div class="mt-2">
                        <button class="btn btn-sm btn-primary" onclick="uploadImage(${product.id})">
                            <i class="fas fa-upload"></i> Upload
                        </button>
                        <button class="btn btn-sm btn-outline-info" onclick="viewProduct('${product.id}')">
                            <i class="fas fa-eye"></i> Xem
                        </button>
                    </div>
                </div>
            `;
            
            return card;
        }

        function getStatusBadge(status) {
            const badges = {
                'complete': '<span class="badge bg-success">Đủ ảnh</span>',
                'partial': '<span class="badge bg-warning">Thiếu ảnh</span>',
                'missing': '<span class="badge bg-danger">Không có ảnh</span>'
            };
            return badges[status] || '<span class="badge bg-secondary">Không xác định</span>';
        }

        function filterProducts() {
            const filter = document.getElementById('filterProducts').value;
            const cards = document.querySelectorAll('.product-card');
            
            cards.forEach(card => {
                if (filter === 'all' || card.dataset.status === filter) {
                    card.style.display = 'block';
                } else {
                    card.style.display = 'none';
                }
            });
            
            addLog(`🔍 Lọc sản phẩm: ${filter}`, 'info');
        }

        function updateSystemStats(stats) {
            const statsContainer = document.getElementById('systemStats');
            statsContainer.innerHTML = `
                <div class="stat-card success">
                    <h5><i class="fas fa-check-circle"></i> Hệ thống ảnh</h5>
                    <div class="row">
                        <div class="col-6">
                            <div class="h4">${stats.total_products}</div>
                            <small>Tổng sản phẩm</small>
                        </div>
                        <div class="col-6">
                            <div class="h4">${stats.total_images}</div>
                            <small>Tổng ảnh</small>
                        </div>
                    </div>
                </div>
                
                <div class="stat-card ${stats.missing_images > 0 ? 'warning' : 'success'}">
                    <h5><i class="fas fa-exclamation-triangle"></i> Trạng thái</h5>
                    <div class="row">
                        <div class="col-6">
                            <div class="h4 ${stats.missing_images > 0 ? 'text-warning' : 'text-success'}">${stats.missing_images}</div>
                            <small>Thiếu ảnh</small>
                        </div>
                        <div class="col-6">
                            <div class="h4 text-success">${stats.complete_products}</div>
                            <small>Đủ ảnh</small>
                        </div>
                    </div>
                </div>
                
                <div class="stat-card">
                    <h5><i class="fas fa-folder"></i> Thư mục</h5>
                    <div class="row">
                        <div class="col-6">
                            <div class="h4 ${stats.directories_exist ? 'text-success' : 'text-danger'}">${stats.total_directories}</div>
                            <small>Thư mục</small>
                        </div>
                        <div class="col-6">
                            <div class="h4">${stats.total_size}</div>
                            <small>Dung lượng</small>
                        </div>
                    </div>
                </div>
            `;
        }

        function updateDirectoryStructure(directories) {
            const container = document.getElementById('directoryStructure');
            let structure = 'uploads/\n';
            
            Object.entries(directories).forEach(([name, info]) => {
                const icon = info.exists ? '📁' : '❌';
                const writable = info.exists ? (info.writable ? '✅' : '🔒') : '';
                const size = info.exists ? ` (${info.file_count} files)` : ' (missing)';
                structure += `├── ${icon} ${name}${size} ${writable}\n`;
            });
            
            container.textContent = structure;
        }

        function uploadImage(productId) {
            // Redirect to upload page or open modal
            window.open(`admin/products/edit.php?id=${productId}`, '_blank');
        }

        function viewProduct(productId) {
            // Open product detail page
            window.open(`customer/product_detail.php?id=${productId}`, '_blank');
        }

        function fixMissingImages() {
            addLog('🔧 Sửa ảnh bị thiếu...', 'info');
            
            fetch('debug_images.php?action=fix_missing_images')
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        addLog(`✅ ${data.message}`, 'success');
                        checkSystem();
                    } else {
                        addLog(`❌ Lỗi: ${data.error}`, 'error');
                    }
                })
                .catch(error => {
                    addLog('❌ Lỗi fix ảnh: ' + error.message, 'error');
                });
        }

        function optimizeImages() {
            addLog('⚡ Tối ưu hóa ảnh...', 'info');
            
            fetch('debug_images.php?action=optimize_images')
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        addLog(`✅ Tối ưu ${data.optimized_count} ảnh`, 'success');
                        addLog(`💾 Tiết kiệm ${data.space_saved} dung lượng`, 'info');
                    } else {
                        addLog(`❌ Lỗi tối ưu: ${data.message}`, 'error');
                    }
                })
                .catch(error => {
                    addLog('❌ Lỗi tối ưu: ' + error.message, 'error');
                });
        }

        function cleanupOrphaned() {
            addLog('🧹 Dọn dẹp ảnh thừa...', 'info');
            
            fetch('debug_images.php?action=cleanup_orphaned')
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        addLog(`✅ Xóa ${data.deleted_count} ảnh thừa`, 'success');
                        addLog(`💾 Giải phóng ${data.space_saved} dung lượng`, 'info');
                        checkSystem();
                    } else {
                        addLog(`❌ Lỗi: ${data.error}`, 'error');
                    }
                })
                .catch(error => {
                    addLog('❌ Lỗi dọn dẹp: ' + error.message, 'error');
                });
        }
    </script>
</body>
</html>