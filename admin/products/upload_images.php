<?php
// admin/products/upload_images.php
/**
 * API endpoint để upload ảnh sản phẩm
 * Hỗ trợ upload multiple files, resize, optimize
 */

require_once '../../config/database.php';
require_once '../../config/config.php';

// Chỉ cho phép POST request
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Method not allowed']);
    exit;
}

// Kiểm tra đăng nhập admin
if (!isset($_SESSION['admin_id'])) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit;
}

// Kiểm tra CSRF token
if (!isset($_POST['csrf_token']) || $_POST['csrf_token'] !== ($_SESSION['csrf_token'] ?? '')) {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'Invalid CSRF token']);
    exit;
}

$response = [
    'success' => false,
    'message' => '',
    'data' => []
];

try {
    // Kiểm tra có file upload không
    if (empty($_FILES)) {
        throw new Exception('Không có file nào được upload');
    }

    $upload_type = $_POST['upload_type'] ?? 'product'; // product, category, user
    $product_id = isset($_POST['product_id']) ? (int)$_POST['product_id'] : 0;
    
    // Validate upload type
    $allowed_types = ['product', 'category', 'user'];
    if (!in_array($upload_type, $allowed_types)) {
        throw new Exception('Loại upload không hợp lệ');
    }

    $uploaded_files = [];
    
    // Xử lý upload single file
    if (isset($_FILES['image'])) {
        $file = $_FILES['image'];
        $result = processImageUpload($file, $upload_type, $product_id);
        if ($result['success']) {
            $uploaded_files[] = $result;
        } else {
            throw new Exception($result['message']);
        }
    }
    
    // Xử lý upload multiple files
    if (isset($_FILES['images'])) {
        $files = $_FILES['images'];
        $file_count = count($files['name']);
        
        for ($i = 0; $i < $file_count; $i++) {
            if ($files['error'][$i] === UPLOAD_ERR_OK) {
                $file = [
                    'name' => $files['name'][$i],
                    'type' => $files['type'][$i],
                    'tmp_name' => $files['tmp_name'][$i],
                    'error' => $files['error'][$i],
                    'size' => $files['size'][$i]
                ];
                
                $result = processImageUpload($file, $upload_type, $product_id);
                if ($result['success']) {
                    $uploaded_files[] = $result;
                }
            }
        }
    }

    if (empty($uploaded_files)) {
        throw new Exception('Không có file nào được upload thành công');
    }

    $response['success'] = true;
    $response['message'] = 'Upload thành công ' . count($uploaded_files) . ' file';
    $response['data'] = $uploaded_files;

} catch (Exception $e) {
    $response['message'] = $e->getMessage();
    logError('Image upload error: ' . $e->getMessage(), [
        'user_id' => $_SESSION['admin_id'],
        'files' => $_FILES,
        'post' => $_POST
    ]);
}

// Trả về JSON response
header('Content-Type: application/json');
echo json_encode($response);

/**
 * Xử lý upload và optimize ảnh
 */
function processImageUpload($file, $upload_type, $product_id = 0) {
    try {
        // Validate file
        $validation = validateImageFile($file);
        if (!$validation['valid']) {
            return ['success' => false, 'message' => $validation['message']];
        }

        // Tạo tên file unique
        $file_info = pathinfo($file['name']);
        $extension = strtolower($file_info['extension']);
        $filename = generateUniqueFilename($upload_type, $extension);
        
        // Xác định thư mục upload
        $upload_folder = getUploadFolder($upload_type);
        $upload_path = UPLOAD_PATH . '/' . $upload_folder;
        
        // Tạo thư mục nếu chưa tồn tại
        if (!is_dir($upload_path)) {
            if (!mkdir($upload_path, 0755, true)) {
                throw new Exception('Không thể tạo thư mục upload');
            }
        }

        $file_path = $upload_path . '/' . $filename;
        
        // Upload file
        if (!move_uploaded_file($file['tmp_name'], $file_path)) {
            throw new Exception('Không thể upload file');
        }

        // Optimize ảnh
        $optimized = optimizeUploadedImage($file_path, $upload_type);
        
        // Tạo thumbnail nếu cần
        $thumbnail_path = null;
        if ($upload_type === 'product') {
            $thumbnail_path = createProductThumbnail($file_path, $filename);
        }

        // Lưu thông tin vào database nếu có product_id
        if ($product_id > 0 && $upload_type === 'product') {
            saveImageToDatabase($product_id, $filename);
        }

        $result = [
            'success' => true,
            'filename' => $filename,
            'original_name' => $file['name'],
            'size' => $file['size'],
            'optimized_size' => filesize($file_path),
            'url' => BASE_URL . '/uploads/' . $upload_folder . '/' . $filename,
            'thumbnail_url' => $thumbnail_path ? BASE_URL . '/uploads/' . $upload_folder . '/thumbs/' . $filename : null,
            'upload_type' => $upload_type
        ];

        // Log successful upload
        logActivity('Image uploaded', [
            'filename' => $filename,
            'type' => $upload_type,
            'size' => $file['size'],
            'user_id' => $_SESSION['admin_id']
        ]);

        return $result;

    } catch (Exception $e) {
        // Cleanup nếu có lỗi
        if (isset($file_path) && file_exists($file_path)) {
            unlink($file_path);
        }
        
        return ['success' => false, 'message' => $e->getMessage()];
    }
}

/**
 * Validate file upload
 */
function validateImageFile($file) {
    // Kiểm tra lỗi upload
    if ($file['error'] !== UPLOAD_ERR_OK) {
        return ['valid' => false, 'message' => getUploadErrorMessage($file['error'])];
    }

    // Kiểm tra kích thước file
    if ($file['size'] > MAX_FILE_SIZE) {
        return ['valid' => false, 'message' => 'File quá lớn (tối đa ' . formatFileSize(MAX_FILE_SIZE) . ')'];
    }

    // Kiểm tra extension
    $file_info = pathinfo($file['name']);
    $extension = strtolower($file_info['extension']);
    
    if (!in_array($extension, ALLOWED_IMAGE_TYPES)) {
        return ['valid' => false, 'message' => 'Loại file không được phép. Chỉ chấp nhận: ' . implode(', ', ALLOWED_IMAGE_TYPES)];
    }

    // Kiểm tra MIME type
    $finfo = finfo_open(FILEINFO_MIME_TYPE);
    $mime_type = finfo_file($finfo, $file['tmp_name']);
    finfo_close($finfo);

    if (!in_array($mime_type, ALLOWED_IMAGE_MIMES)) {
        return ['valid' => false, 'message' => 'MIME type không hợp lệ'];
    }

    // Kiểm tra file có phải ảnh thật không
    $image_info = getimagesize($file['tmp_name']);
    if (!$image_info) {
        return ['valid' => false, 'message' => 'File không phải là ảnh hợp lệ'];
    }

    // Kiểm tra kích thước ảnh
    list($width, $height) = $image_info;
    if ($width < 100 || $height < 100) {
        return ['valid' => false, 'message' => 'Ảnh quá nhỏ (tối thiểu 100x100px)'];
    }

    if ($width > 5000 || $height > 5000) {
        return ['valid' => false, 'message' => 'Ảnh quá lớn (tối đa 5000x5000px)'];
    }

    return ['valid' => true];
}

/**
 * Generate unique filename
 */
function generateUniqueFilename($type, $extension) {
    $prefix = [
        'product' => 'prod',
        'category' => 'cat', 
        'user' => 'user'
    ];
    
    $type_prefix = $prefix[$type] ?? 'img';
    return $type_prefix . '_' . date('Y-m-d_H-i-s') . '_' . uniqid() . '.' . $extension;
}

/**
 * Get upload folder by type
 */
function getUploadFolder($type) {
    $folders = [
        'product' => 'products',
        'category' => 'categories',
        'user' => 'users'
    ];
    
    return $folders[$type] ?? 'others';
}

/**
 * Optimize uploaded image
 */
function optimizeUploadedImage($file_path, $type) {
    $image_info = getimagesize($file_path);
    if (!$image_info) {
        return false;
    }

    list($width, $height, $image_type) = $image_info;
    
    // Resize rules by type
    $resize_rules = [
        'product' => ['max_width' => 1200, 'max_height' => 1200, 'quality' => 85],
        'category' => ['max_width' => 800, 'max_height' => 600, 'quality' => 80],
        'user' => ['max_width' => 400, 'max_height' => 400, 'quality' => 80]
    ];
    
    $rules = $resize_rules[$type] ?? $resize_rules['product'];
    
    // Chỉ resize nếu ảnh lớn hơn quy định
    if ($width > $rules['max_width'] || $height > $rules['max_height']) {
        return resizeImage($file_path, $file_path, $rules['max_width'], $rules['max_height'], $rules['quality']);
    }
    
    // Optimize quality nếu file lớn
    if (filesize($file_path) > 500 * 1024) { // 500KB
        return optimizeImage($file_path, $rules['quality']);
    }
    
    return true;
}

/**
 * Create thumbnail for product images
 */
function createProductThumbnail($source_path, $filename) {
    $thumb_dir = dirname($source_path) . '/thumbs';
    if (!is_dir($thumb_dir)) {
        if (!mkdir($thumb_dir, 0755, true)) {
            return null;
        }
    }
    
    $thumb_path = $thumb_dir . '/' . $filename;
    
    if (createThumbnail($source_path, $thumb_path, 300)) {
        return $thumb_path;
    }
    
    return null;
}

/**
 * Save image info to database
 */
function saveImageToDatabase($product_id, $filename) {
    global $pdo;
    
    try {
        // Có thể lưu vào bảng product_images nếu có
        // Hoặc update album_hinh_anh của sản phẩm
        $stmt = $pdo->prepare("SELECT album_hinh_anh FROM san_pham_chinh WHERE id = ?");
        $stmt->execute([$product_id]);
        $product = $stmt->fetch();
        
        if ($product) {
            $album = $product['album_hinh_anh'] ? json_decode($product['album_hinh_anh'], true) : [];
            $album[] = $filename;
            
            $stmt = $pdo->prepare("UPDATE san_pham_chinh SET album_hinh_anh = ? WHERE id = ?");
            $stmt->execute([json_encode($album), $product_id]);
        }
        
        return true;
    } catch (Exception $e) {
        logError('Failed to save image to database: ' . $e->getMessage());
        return false;
    }
}

/**
 * Get upload error message
 */
function getUploadErrorMessage($error_code) {
    $errors = [
        UPLOAD_ERR_INI_SIZE => 'File quá lớn (vượt quá upload_max_filesize)',
        UPLOAD_ERR_FORM_SIZE => 'File quá lớn (vượt quá MAX_FILE_SIZE)',
        UPLOAD_ERR_PARTIAL => 'File chỉ được upload một phần',
        UPLOAD_ERR_NO_FILE => 'Không có file nào được upload',
        UPLOAD_ERR_NO_TMP_DIR => 'Thiếu thư mục tạm thời',
        UPLOAD_ERR_CANT_WRITE => 'Không thể ghi file vào disk',
        UPLOAD_ERR_EXTENSION => 'Upload dừng bởi extension'
    ];
    
    return $errors[$error_code] ?? 'Lỗi upload không xác định';
}
?>