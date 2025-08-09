<?php
/**
 * Các hàm hỗ trợ hoàn chỉnh cho hệ thống TKT Shop
 * File này chứa tất cả các hàm thiếu được tham chiếu trong code
 */

/**
 * Upload image với tối ưu hóa
 */
if (!function_exists('uploadImage')) {
    function uploadImage($file, $folder, $max_width = 800, $max_height = 800, $quality = 85) {
        if (!isset($file) || $file['error'] !== UPLOAD_ERR_OK) {
            return false;
        }
        
        // Kiểm tra file size (2MB)
        if ($file['size'] > 2 * 1024 * 1024) {
            throw new Exception('File quá lớn. Tối đa 2MB.');
        }
        
        // Kiểm tra file type
        $allowed_types = ['image/jpeg', 'image/jpg', 'image/png', 'image/gif'];
        $file_type = mime_content_type($file['tmp_name']);
        
        if (!in_array($file_type, $allowed_types)) {
            throw new Exception('Định dạng file không được hỗ trợ.');
        }
        
        // Tạo tên file unique
        $extension = pathinfo($file['name'], PATHINFO_EXTENSION);
        $filename = time() . '_' . uniqid() . '.' . strtolower($extension);
        
        // Tạo thư mục nếu chưa tồn tại
        $upload_dir = UPLOAD_PATH . '/' . $folder;
        if (!is_dir($upload_dir)) {
            mkdir($upload_dir, 0755, true);
        }
        
        $target_path = $upload_dir . '/' . $filename;
        
        // Resize và optimize image
        if (resizeImage($file['tmp_name'], $target_path, $max_width, $max_height, $quality)) {
            return $filename;
        }
        
        return false;
    }
}

/**
 * Resize image
 */
if (!function_exists('resizeImage')) {
    function resizeImage($source, $target, $max_width, $max_height, $quality = 85) {
        $image_info = getimagesize($source);
        if (!$image_info) return false;
        
        $original_width = $image_info[0];
        $original_height = $image_info[1];
        $mime_type = $image_info['mime'];
        
        // Tính toán kích thước mới
        $ratio = min($max_width / $original_width, $max_height / $original_height);
        if ($ratio >= 1) {
            // Không cần resize
            return move_uploaded_file($source, $target);
        }
        
        $new_width = (int)($original_width * $ratio);
        $new_height = (int)($original_height * $ratio);
        
        // Tạo image từ source
        switch ($mime_type) {
            case 'image/jpeg':
            case 'image/jpg':
                $source_image = imagecreatefromjpeg($source);
                break;
            case 'image/png':
                $source_image = imagecreatefrompng($source);
                break;
            case 'image/gif':
                $source_image = imagecreatefromgif($source);
                break;
            default:
                return false;
        }
        
        if (!$source_image) return false;
        
        // Tạo image mới với kích thước đã resize
        $new_image = imagecreatetruecolor($new_width, $new_height);
        
        // Preserve transparency cho PNG và GIF
        if ($mime_type == 'image/png' || $mime_type == 'image/gif') {
            imagealphablending($new_image, false);
            imagesavealpha($new_image, true);
            $transparent = imagecolorallocatealpha($new_image, 255, 255, 255, 127);
            imagefilledrectangle($new_image, 0, 0, $new_width, $new_height, $transparent);
        }
        
        // Resize image
        imagecopyresampled(
            $new_image, $source_image,
            0, 0, 0, 0,
            $new_width, $new_height,
            $original_width, $original_height
        );
        
        // Save resized image
        $result = false;
        switch ($mime_type) {
            case 'image/jpeg':
            case 'image/jpg':
                $result = imagejpeg($new_image, $target, $quality);
                break;
            case 'image/png':
                $result = imagepng($new_image, $target, (int)(9 * (100 - $quality) / 100));
                break;
            case 'image/gif':
                $result = imagegif($new_image, $target);
                break;
        }
        
        // Clean up memory
        imagedestroy($source_image);
        imagedestroy($new_image);
        
        return $result;
    }
}

/**
 * Flash messages - Cập nhật để tương thích
 */
if (!function_exists('setFlashMessage')) {
    function setFlashMessage($type, $message) {
        $_SESSION['flash_message'] = [
            'type' => $type,
            'message' => $message
        ];
    }
}

if (!function_exists('getFlashMessage')) {
    function getFlashMessage() {
        if (isset($_SESSION['flash_message'])) {
            $flash = $_SESSION['flash_message'];
            unset($_SESSION['flash_message']);
            return $flash;
        }
        return null;
    }
}

if (!function_exists('hasFlashMessage')) {
    function hasFlashMessage() {
        return isset($_SESSION['flash_message']);
    }
}

/**
 * URL helpers - Sửa để tương thích với config hiện tại
 */
if (!defined('UPLOAD_URL')) {
    define('UPLOAD_URL', BASE_URL . '/uploads');
}

/**
 * Tạo thumbnail
 */
if (!function_exists('createThumbnail')) {
    function createThumbnail($source_file, $folder, $width = 300, $height = 300) {
        if (!$source_file) return null;
        
        $source_path = UPLOAD_PATH . '/' . $folder . '/' . $source_file;
        if (!file_exists($source_path)) return null;
        
        $thumb_filename = 'thumb_' . $source_file;
        $thumb_path = UPLOAD_PATH . '/' . $folder . '/' . $thumb_filename;
        
        if (resizeImage($source_path, $thumb_path, $width, $height, 80)) {
            return $thumb_filename;
        }
        
        return null;
    }
}

/**
 * Validate product data
 */
if (!function_exists('validateProductData')) {
    function validateProductData($data) {
        $errors = [];
        
        // Required fields
        $required = ['ten_san_pham', 'thuong_hieu', 'danh_muc_id', 'gia_goc', 'mo_ta_ngan'];
        foreach ($required as $field) {
            if (empty($data[$field])) {
                $errors[$field] = ucfirst(str_replace('_', ' ', $field)) . ' là bắt buộc';
            }
        }
        
        // Validate price
        if (isset($data['gia_goc']) && $data['gia_goc'] <= 0) {
            $errors['gia_goc'] = 'Giá gốc phải lớn hơn 0';
        }
        
        if (isset($data['gia_khuyen_mai']) && $data['gia_khuyen_mai'] && 
            isset($data['gia_goc']) && $data['gia_khuyen_mai'] >= $data['gia_goc']) {
            $errors['gia_khuyen_mai'] = 'Giá khuyến mãi phải nhỏ hơn giá gốc';
        }
        
        // Validate text length
        if (isset($data['ten_san_pham']) && strlen($data['ten_san_pham']) > 255) {
            $errors['ten_san_pham'] = 'Tên sản phẩm quá dài (tối đa 255 ký tự)';
        }
        
        if (isset($data['mo_ta_ngan']) && strlen($data['mo_ta_ngan']) > 500) {
            $errors['mo_ta_ngan'] = 'Mô tả ngắn quá dài (tối đa 500 ký tự)';
        }
        
        return $errors;
    }
}

/**
 * Generate product SKU
 */
if (!function_exists('generateProductSKU')) {
    function generateProductSKU($brand, $category = '', $suffix = '') {
        $brand_code = strtoupper(substr(trim($brand), 0, 3));
        $category_code = $category ? strtoupper(substr(trim($category), 0, 2)) : '';
        $date_code = date('ymd');
        $random = rand(100, 999);
        
        return $brand_code . $category_code . $date_code . $random . $suffix;
    }
}

/**
 * Check if product exists
 */
if (!function_exists('productExists')) {
    function productExists($field, $value, $exclude_id = null) {
        global $pdo;
        
        $sql = "SELECT COUNT(*) FROM san_pham_chinh WHERE $field = ?";
        $params = [$value];
        
        if ($exclude_id) {
            $sql .= " AND id != ?";
            $params[] = $exclude_id;
        }
        
        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        
        return $stmt->fetchColumn() > 0;
    }
}

/**
 * Get product by ID
 */
if (!function_exists('getProductById')) {
    function getProductById($id) {
        global $pdo;
        
        $stmt = $pdo->prepare("
            SELECT sp.*, dm.ten_danh_muc 
            FROM san_pham_chinh sp
            LEFT JOIN danh_muc_giay dm ON sp.danh_muc_id = dm.id
            WHERE sp.id = ?
        ");
        $stmt->execute([$id]);
        
        return $stmt->fetch(PDO::FETCH_ASSOC);
    }
}

/**
 * Get product variants
 */
if (!function_exists('getProductVariants')) {
    function getProductVariants($product_id) {
        global $pdo;
        
        $stmt = $pdo->prepare("
            SELECT btp.*, kc.kich_co, ms.ten_mau, ms.ma_mau 
            FROM bien_the_san_pham btp
            JOIN kich_co kc ON btp.kich_co_id = kc.id
            JOIN mau_sac ms ON btp.mau_sac_id = ms.id
            WHERE btp.san_pham_id = ?
            ORDER BY kc.thu_tu_sap_xep ASC, ms.thu_tu_hien_thi ASC
        ");
        $stmt->execute([$product_id]);
        
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
}

/**
 * Update product stock
 */
if (!function_exists('updateProductStock')) {
    function updateProductStock($variant_id, $quantity_change) {
        global $pdo;
        
        $stmt = $pdo->prepare("
            UPDATE bien_the_san_pham 
            SET so_luong_ton_kho = so_luong_ton_kho + ?,
                so_luong_da_ban = so_luong_da_ban - ?
            WHERE id = ?
        ");
        
        return $stmt->execute([$quantity_change, $quantity_change, $variant_id]);
    }
}

/**
 * Get low stock products
 */
if (!function_exists('getLowStockProducts')) {
    function getLowStockProducts($limit = 10, $threshold = 5) {
        global $pdo;
        
        $stmt = $pdo->prepare("
            SELECT sp.*, SUM(btp.so_luong_ton_kho) as total_stock
            FROM san_pham_chinh sp
            JOIN bien_the_san_pham btp ON sp.id = btp.san_pham_id
            GROUP BY sp.id
            HAVING total_stock <= ?
            ORDER BY total_stock ASC
            LIMIT ?
        ");
        $stmt->execute([$threshold, $limit]);
        
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
}

/**
 * Get product statistics
 */
if (!function_exists('getProductStats')) {
    function getProductStats() {
        global $pdo;
        
        $stats = [];
        
        // Total products
        $stmt = $pdo->query("SELECT COUNT(*) FROM san_pham_chinh");
        $stats['total'] = $stmt->fetchColumn();
        
        // Active products
        $stmt = $pdo->query("SELECT COUNT(*) FROM san_pham_chinh WHERE trang_thai = 'hoat_dong'");
        $stats['active'] = $stmt->fetchColumn();
        
        // Out of stock
        $stmt = $pdo->query("SELECT COUNT(*) FROM san_pham_chinh WHERE trang_thai = 'het_hang'");
        $stats['out_of_stock'] = $stmt->fetchColumn();
        
        // Hidden products
        $stmt = $pdo->query("SELECT COUNT(*) FROM san_pham_chinh WHERE trang_thai = 'an'");
        $stats['hidden'] = $stmt->fetchColumn();
        
        return $stats;
    }
}

/**
 * Log product activity
 */
if (!function_exists('logProductActivity')) {
    function logProductActivity($product_id, $action, $details = '') {
        global $pdo;
        
        try {
            $stmt = $pdo->prepare("
                INSERT INTO product_activity_log (product_id, action, details, user_id, created_at)
                VALUES (?, ?, ?, ?, NOW())
            ");
            
            $user_id = $_SESSION['admin_id'] ?? $_SESSION['user_id'] ?? null;
            $stmt->execute([$product_id, $action, $details, $user_id]);
            
        } catch (Exception $e) {
            // Log error but don't stop execution
            error_log("Failed to log product activity: " . $e->getMessage());
        }
    }
}

/**
 * Search products
 */
if (!function_exists('searchProducts')) {
    function searchProducts($query, $filters = [], $limit = 20, $offset = 0) {
        global $pdo;
        
        $where_conditions = [];
        $params = [];
        
        // Search query
        if (!empty($query)) {
            $where_conditions[] = "(sp.ten_san_pham LIKE ? OR sp.ma_san_pham LIKE ? OR sp.thuong_hieu LIKE ? OR sp.tu_khoa_tim_kiem LIKE ?)";
            $search_param = "%$query%";
            $params[] = $search_param;
            $params[] = $search_param;
            $params[] = $search_param;
            $params[] = $search_param;
        }
        
        // Apply filters
        if (isset($filters['category']) && $filters['category']) {
            $where_conditions[] = "sp.danh_muc_id = ?";
            $params[] = $filters['category'];
        }
        
        if (isset($filters['status']) && $filters['status']) {
            $where_conditions[] = "sp.trang_thai = ?";
            $params[] = $filters['status'];
        }
        
        if (isset($filters['brand']) && $filters['brand']) {
            $where_conditions[] = "sp.thuong_hieu LIKE ?";
            $params[] = "%{$filters['brand']}%";
        }
        
        $where_clause = !empty($where_conditions) ? 'WHERE ' . implode(' AND ', $where_conditions) : '';
        
        $sql = "
            SELECT sp.*, dm.ten_danh_muc,
                   COUNT(btp.id) as so_bien_the,
                   SUM(btp.so_luong_ton_kho) as tong_ton_kho
            FROM san_pham_chinh sp
            LEFT JOIN danh_muc_giay dm ON sp.danh_muc_id = dm.id
            LEFT JOIN bien_the_san_pham btp ON sp.id = btp.san_pham_id
            $where_clause
            GROUP BY sp.id
            ORDER BY sp.ngay_tao DESC
            LIMIT $limit OFFSET $offset
        ";
        
        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
}

/**
 * Backup product data
 */
if (!function_exists('backupProductData')) {
    function backupProductData($product_id) {
        global $pdo;
        
        try {
            // Get product data
            $product = getProductById($product_id);
            if (!$product) return false;
            
            // Get variants
            $variants = getProductVariants($product_id);
            
            $backup_data = [
                'product' => $product,
                'variants' => $variants,
                'backup_date' => date('Y-m-d H:i:s')
            ];
            
            // Save to backup file
            $backup_dir = ROOT_PATH . '/backups/products';
            if (!is_dir($backup_dir)) {
                mkdir($backup_dir, 0755, true);
            }
            
            $backup_file = $backup_dir . '/product_' . $product_id . '_' . date('Ymd_His') . '.json';
            file_put_contents($backup_file, json_encode($backup_data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
            
            return $backup_file;
            
        } catch (Exception $e) {
            error_log("Backup failed: " . $e->getMessage());
            return false;
        }
    }
}

/**
 * Clean old images
 */
if (!function_exists('cleanUnusedImages')) {
    function cleanUnusedImages($folder = 'products') {
        global $pdo;
        
        $image_dir = UPLOAD_PATH . '/' . $folder;
        if (!is_dir($image_dir)) return 0;
        
        // Get all images from database
        $used_images = [];
        
        // Main images
        $stmt = $pdo->query("SELECT hinh_anh_chinh FROM san_pham_chinh WHERE hinh_anh_chinh IS NOT NULL");
        while ($row = $stmt->fetch()) {
            $used_images[] = $row['hinh_anh_chinh'];
        }
        
        // Secondary images
        $stmt = $pdo->query("SELECT hinh_anh_phu FROM san_pham_chinh WHERE hinh_anh_phu IS NOT NULL");
        while ($row = $stmt->fetch()) {
            $images = json_decode($row['hinh_anh_phu'], true);
            if ($images) {
                $used_images = array_merge($used_images, $images);
            }
        }
        
        // Variant images
        $stmt = $pdo->query("SELECT hinh_anh_bien_the FROM bien_the_san_pham WHERE hinh_anh_bien_the IS NOT NULL");
        while ($row = $stmt->fetch()) {
            $used_images[] = $row['hinh_anh_bien_the'];
        }
        
        // Get all files in directory
        $all_files = array_diff(scandir($image_dir), ['.', '..']);
        $unused_files = array_diff($all_files, $used_images);
        
        $deleted_count = 0;
        foreach ($unused_files as $file) {
            $file_path = $image_dir . '/' . $file;
            if (is_file($file_path) && unlink($file_path)) {
                $deleted_count++;
            }
        }
        
        return $deleted_count;
    }
}

/**
 * Export products to CSV
 */
if (!function_exists('exportProductsToCSV')) {
    function exportProductsToCSV($filters = []) {
        global $pdo;
        
        $where_conditions = [];
        $params = [];
        
        // Apply filters
        if (isset($filters['category']) && $filters['category']) {
            $where_conditions[] = "sp.danh_muc_id = ?";
            $params[] = $filters['category'];
        }
        
        if (isset($filters['status']) && $filters['status']) {
            $where_conditions[] = "sp.trang_thai = ?";
            $params[] = $filters['status'];
        }
        
        $where_clause = !empty($where_conditions) ? 'WHERE ' . implode(' AND ', $where_conditions) : '';
        
        $sql = "
            SELECT sp.*, dm.ten_danh_muc,
                   COUNT(btp.id) as so_bien_the,
                   SUM(btp.so_luong_ton_kho) as tong_ton_kho,
                   SUM(btp.so_luong_da_ban) as tong_da_ban
            FROM san_pham_chinh sp
            LEFT JOIN danh_muc_giay dm ON sp.danh_muc_id = dm.id
            LEFT JOIN bien_the_san_pham btp ON sp.id = btp.san_pham_id
            $where_clause
            GROUP BY sp.id
            ORDER BY sp.ten_san_pham ASC
        ";
        
        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        $products = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        // Create CSV content
        $csv_content = "\xEF\xBB\xBF"; // UTF-8 BOM
        $csv_content .= "ID,Mã sản phẩm,Tên sản phẩm,Thương hiệu,Danh mục,Giá gốc,Giá khuyến mãi,Số biến thể,Tồn kho,Đã bán,Trạng thái,Ngày tạo\n";
        
        foreach ($products as $product) {
            $csv_content .= sprintf(
                "%d,\"%s\",\"%s\",\"%s\",\"%s\",%d,%d,%d,%d,%d,\"%s\",\"%s\"\n",
                $product['id'],
                $product['ma_san_pham'],
                str_replace('"', '""', $product['ten_san_pham']),
                str_replace('"', '""', $product['thuong_hieu']),
                str_replace('"', '""', $product['ten_danh_muc'] ?? ''),
                $product['gia_goc'],
                $product['gia_khuyen_mai'] ?? 0,
                $product['so_bien_the'],
                $product['tong_ton_kho'] ?? 0,
                $product['tong_da_ban'] ?? 0,
                $product['trang_thai'],
                $product['ngay_tao']
            );
        }
        
        return $csv_content;
    }
}

/**
 * Import products from CSV
 */
if (!function_exists('importProductsFromCSV')) {
    function importProductsFromCSV($csv_file) {
        global $pdo;
        
        $imported = 0;
        $errors = [];
        
        if (($handle = fopen($csv_file, "r")) !== FALSE) {
            $header = fgetcsv($handle, 1000, ",");
            
            while (($data = fgetcsv($handle, 1000, ",")) !== FALSE) {
                try {
                    $product_data = array_combine($header, $data);
                    
                    // Validate required fields
                    $validation_errors = validateProductData($product_data);
                    if (!empty($validation_errors)) {
                        $errors[] = "Row " . ($imported + 1) . ": " . implode(', ', $validation_errors);
                        continue;
                    }
                    
                    // Insert product
                    $stmt = $pdo->prepare("
                        INSERT INTO san_pham_chinh (
                            ma_san_pham, ten_san_pham, thuong_hieu, danh_muc_id,
                            gia_goc, gia_khuyen_mai, mo_ta_ngan, trang_thai,
                            ngay_tao, ngay_cap_nhat
                        ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, NOW(), NOW())
                    ");
                    
                    $stmt->execute([
                        $product_data['ma_san_pham'],
                        $product_data['ten_san_pham'],
                        $product_data['thuong_hieu'],
                        $product_data['danh_muc_id'],
                        $product_data['gia_goc'],
                        $product_data['gia_khuyen_mai'] ?: null,
                        $product_data['mo_ta_ngan'],
                        $product_data['trang_thai'] ?: 'hoat_dong'
                    ]);
                    
                    $imported++;
                    
                } catch (Exception $e) {
                    $errors[] = "Row " . ($imported + 1) . ": " . $e->getMessage();
                }
            }
            fclose($handle);
        }
        
        return [
            'imported' => $imported,
            'errors' => $errors
        ];
    }
}

/**
 * Get popular products
 */
if (!function_exists('getPopularProducts')) {
    function getPopularProducts($limit = 10, $days = 30) {
        global $pdo;
        
        $stmt = $pdo->prepare("
            SELECT sp.*, SUM(ct.so_luong) as total_sold
            FROM san_pham_chinh sp
            JOIN chi_tiet_don_hang ct ON sp.id = ct.san_pham_id
            JOIN don_hang dh ON ct.don_hang_id = dh.id
            WHERE dh.ngay_dat_hang >= DATE_SUB(NOW(), INTERVAL ? DAY)
              AND dh.trang_thai_don_hang = 'da_giao'
            GROUP BY sp.id
            ORDER BY total_sold DESC
            LIMIT ?
        ");
        $stmt->execute([$days, $limit]);
        
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
}

/**
 * Get related products
 */
if (!function_exists('getRelatedProducts')) {
    function getRelatedProducts($product_id, $limit = 6) {
        global $pdo;
        
        // Get current product info
        $current_product = getProductById($product_id);
        if (!$current_product) return [];
        
        $stmt = $pdo->prepare("
            SELECT sp.*, dm.ten_danh_muc
            FROM san_pham_chinh sp
            LEFT JOIN danh_muc_giay dm ON sp.danh_muc_id = dm.id
            WHERE sp.id != ? 
              AND (sp.danh_muc_id = ? OR sp.thuong_hieu = ?)
              AND sp.trang_thai = 'hoat_dong'
            ORDER BY 
                CASE WHEN sp.danh_muc_id = ? THEN 1 ELSE 2 END,
                RAND()
            LIMIT ?
        ");
        
        $stmt->execute([
            $product_id,
            $current_product['danh_muc_id'],
            $current_product['thuong_hieu'],
            $current_product['danh_muc_id'],
            $limit
        ]);
        
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
}

/**
 * Generate product sitemap
 */
if (!function_exists('generateProductSitemap')) {
    function generateProductSitemap() {
        global $pdo;
        
        $stmt = $pdo->query("
            SELECT slug, ngay_cap_nhat
            FROM san_pham_chinh 
            WHERE trang_thai = 'hoat_dong'
            ORDER BY ngay_cap_nhat DESC
        ");
        $products = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        $sitemap = '<?xml version="1.0" encoding="UTF-8"?>' . "\n";
        $sitemap .= '<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">' . "\n";
        
        foreach ($products as $product) {
            $sitemap .= '  <url>' . "\n";
            $sitemap .= '    <loc>' . BASE_URL . '/san-pham/' . $product['slug'] . '</loc>' . "\n";
            $sitemap .= '    <lastmod>' . date('Y-m-d', strtotime($product['ngay_cap_nhat'])) . '</lastmod>' . "\n";
            $sitemap .= '    <changefreq>weekly</changefreq>' . "\n";
            $sitemap .= '    <priority>0.8</priority>' . "\n";
            $sitemap .= '  </url>' . "\n";
        }
        
        $sitemap .= '</urlset>';
        
        // Save sitemap
        $sitemap_file = ROOT_PATH . '/sitemap-products.xml';
        file_put_contents($sitemap_file, $sitemap);
        
        return $sitemap_file;
    }
}

/**
 * Check product availability
 */
if (!function_exists('checkProductAvailability')) {
    function checkProductAvailability($product_id, $size_id = null, $color_id = null) {
        global $pdo;
        
        $sql = "
            SELECT SUM(so_luong_ton_kho) as total_stock
            FROM bien_the_san_pham 
            WHERE san_pham_id = ?
        ";
        $params = [$product_id];
        
        if ($size_id) {
            $sql .= " AND kich_co_id = ?";
            $params[] = $size_id;
        }
        
        if ($color_id) {
            $sql .= " AND mau_sac_id = ?";
            $params[] = $color_id;
        }
        
        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        return $result['total_stock'] ?? 0;
    }
}

/**
 * Update product SEO
 */
if (!function_exists('updateProductSEO')) {
    function updateProductSEO($product_id, $seo_data) {
        global $pdo;
        
        $stmt = $pdo->prepare("
            UPDATE san_pham_chinh 
            SET meta_title = ?, meta_description = ?, meta_keywords = ?
            WHERE id = ?
        ");
        
        return $stmt->execute([
            $seo_data['meta_title'] ?? null,
            $seo_data['meta_description'] ?? null, 
            $seo_data['meta_keywords'] ?? null,
            $product_id
        ]);
    }
}

/**
 * Calculate product rating
 */
if (!function_exists('calculateProductRating')) {
    function calculateProductRating($product_id) {
        global $pdo;
        
        try {
            $stmt = $pdo->prepare("
                SELECT 
                    AVG(rating) as avg_rating,
                    COUNT(*) as total_reviews
                FROM product_reviews 
                WHERE product_id = ? AND status = 'approved'
            ");
            $stmt->execute([$product_id]);
            
            $result = $stmt->fetch(PDO::FETCH_ASSOC);
            
            return [
                'rating' => round($result['avg_rating'] ?? 0, 1),
                'total_reviews' => $result['total_reviews'] ?? 0
            ];
        } catch (Exception $e) {
            return ['rating' => 0, 'total_reviews' => 0];
        }
    }
}

/**
 * Get product price history
 */
if (!function_exists('getProductPriceHistory')) {
    function getProductPriceHistory($product_id, $days = 90) {
        global $pdo;
        
        try {
            $stmt = $pdo->prepare("
                SELECT price, sale_price, changed_at
                FROM product_price_history 
                WHERE product_id = ? 
                  AND changed_at >= DATE_SUB(NOW(), INTERVAL ? DAY)
                ORDER BY changed_at ASC
            ");
            $stmt->execute([$product_id, $days]);
            
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (Exception $e) {
            return [];
        }
    }
}

/**
 * Track product view
 */
if (!function_exists('trackProductView')) {
    function trackProductView($product_id) {
        global $pdo;
        
        $ip_address = $_SERVER['REMOTE_ADDR'] ?? '127.0.0.1';
        $user_agent = $_SERVER['HTTP_USER_AGENT'] ?? '';
        
        try {
            $stmt = $pdo->prepare("
                INSERT INTO product_views (product_id, ip_address, user_agent, viewed_at)
                VALUES (?, ?, ?, NOW())
            ");
            $stmt->execute([$product_id, $ip_address, $user_agent]);
            
            // Update product view count
            $stmt = $pdo->prepare("
                UPDATE san_pham_chinh 
                SET view_count = view_count + 1 
                WHERE id = ?
            ");
            $stmt->execute([$product_id]);
            
        } catch (Exception $e) {
            // Don't stop execution if tracking fails
            error_log("Failed to track product view: " . $e->getMessage());
        }
    }
}

/**
 * Get trending products
 */
if (!function_exists('getTrendingProducts')) {
    function getTrendingProducts($limit = 10, $days = 7) {
        global $pdo;
        
        try {
            $stmt = $pdo->prepare("
                SELECT sp.*, COUNT(pv.id) as recent_views
                FROM san_pham_chinh sp
                LEFT JOIN product_views pv ON sp.id = pv.product_id 
                    AND pv.viewed_at >= DATE_SUB(NOW(), INTERVAL ? DAY)
                WHERE sp.trang_thai = 'hoat_dong'
                GROUP BY sp.id
                ORDER BY recent_views DESC, sp.view_count DESC
                LIMIT ?
            ");
            $stmt->execute([$days, $limit]);
            
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (Exception $e) {
            return [];
        }
    }
}

/**
 * Generate product barcode
 */
if (!function_exists('generateProductBarcode')) {
    function generateProductBarcode($product_id) {
        // Simple barcode generation - in production, use a proper barcode library
        $timestamp = time();
        $random = rand(100, 999);
        return '8901234' . str_pad($product_id, 5, '0', STR_PAD_LEFT) . $random;
    }
}

/**
 * Check product dependencies
 */
if (!function_exists('checkProductDependencies')) {
    function checkProductDependencies($product_id) {
        global $pdo;
        
        $dependencies = [];
        
        try {
            // Check orders
            $stmt = $pdo->prepare("SELECT COUNT(*) FROM chi_tiet_don_hang WHERE san_pham_id = ?");
            $stmt->execute([$product_id]);
            $order_count = $stmt->fetchColumn();
            
            if ($order_count > 0) {
                $dependencies['orders'] = $order_count;
            }
            
            // Check reviews
            $stmt = $pdo->prepare("SELECT COUNT(*) FROM product_reviews WHERE product_id = ?");
            $stmt->execute([$product_id]);
            $review_count = $stmt->fetchColumn();
            
            if ($review_count > 0) {
                $dependencies['reviews'] = $review_count;
            }
            
            // Check wishlists
            $stmt = $pdo->prepare("SELECT COUNT(*) FROM wishlist WHERE product_id = ?");
            $stmt->execute([$product_id]);
            $wishlist_count = $stmt->fetchColumn();
            
            if ($wishlist_count > 0) {
                $dependencies['wishlists'] = $wishlist_count;
            }
        } catch (Exception $e) {
            error_log("Error checking dependencies: " . $e->getMessage());
        }
        
        return $dependencies;
    }
}

/**
 * Advanced search with filters
 */
if (!function_exists('advancedProductSearch')) {
    function advancedProductSearch($params = []) {
        global $pdo;
        
        $where_conditions = [];
        $sql_params = [];
        
        // Basic search
        if (!empty($params['search'])) {
            $where_conditions[] = "(sp.ten_san_pham LIKE ? OR sp.ma_san_pham LIKE ? OR sp.thuong_hieu LIKE ?)";
            $search_term = "%{$params['search']}%";
            $sql_params[] = $search_term;
            $sql_params[] = $search_term;
            $sql_params[] = $search_term;
        }
        
        // Category filter
        if (!empty($params['category_id'])) {
            $where_conditions[] = "sp.danh_muc_id = ?";
            $sql_params[] = $params['category_id'];
        }
        
        // Price range
        if (!empty($params['min_price'])) {
            $where_conditions[] = "sp.gia_goc >= ?";
            $sql_params[] = $params['min_price'];
        }
        
        if (!empty($params['max_price'])) {
            $where_conditions[] = "sp.gia_goc <= ?";
            $sql_params[] = $params['max_price'];
        }
        
        // Brand filter
        if (!empty($params['brand'])) {
            $where_conditions[] = "sp.thuong_hieu = ?";
            $sql_params[] = $params['brand'];
        }
        
        // Status filter
        if (!empty($params['status'])) {
            $where_conditions[] = "sp.trang_thai = ?";
            $sql_params[] = $params['status'];
        }
        
        // Date range
        if (!empty($params['date_from'])) {
            $where_conditions[] = "DATE(sp.ngay_tao) >= ?";
            $sql_params[] = $params['date_from'];
        }
        
        if (!empty($params['date_to'])) {
            $where_conditions[] = "DATE(sp.ngay_tao) <= ?";
            $sql_params[] = $params['date_to'];
        }
        
        $where_clause = !empty($where_conditions) ? 'WHERE ' . implode(' AND ', $where_conditions) : '';
        
        // Sorting
        $order_by = "ORDER BY sp.ngay_tao DESC";
        if (!empty($params['sort'])) {
            switch ($params['sort']) {
                case 'name_asc':
                    $order_by = "ORDER BY sp.ten_san_pham ASC";
                    break;
                case 'name_desc':
                    $order_by = "ORDER BY sp.ten_san_pham DESC";
                    break;
                case 'price_asc':
                    $order_by = "ORDER BY sp.gia_goc ASC";
                    break;
                case 'price_desc':
                    $order_by = "ORDER BY sp.gia_goc DESC";
                    break;
                case 'newest':
                    $order_by = "ORDER BY sp.ngay_tao DESC";
                    break;
                case 'oldest':
                    $order_by = "ORDER BY sp.ngay_tao ASC";
                    break;
            }
        }
        
        // Pagination
        $limit = isset($params['limit']) ? (int)$params['limit'] : 20;
        $offset = isset($params['offset']) ? (int)$params['offset'] : 0;
        
        $sql = "
            SELECT sp.*, dm.ten_danh_muc,
                   COUNT(btp.id) as so_bien_the,
                   SUM(btp.so_luong_ton_kho) as tong_ton_kho,
                   SUM(btp.so_luong_da_ban) as tong_da_ban
            FROM san_pham_chinh sp
            LEFT JOIN danh_muc_giay dm ON sp.danh_muc_id = dm.id
            LEFT JOIN bien_the_san_pham btp ON sp.id = btp.san_pham_id
            $where_clause
            GROUP BY sp.id
            $order_by
            LIMIT $limit OFFSET $offset
        ";
        
        $stmt = $pdo->prepare($sql);
        $stmt->execute($sql_params);
        
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
}

/**
 * Get product suggestions for autocomplete
 */
if (!function_exists('getProductSuggestions')) {
    function getProductSuggestions($query, $limit = 10) {
        global $pdo;
        
        $stmt = $pdo->prepare("
            SELECT id, ten_san_pham, ma_san_pham, thuong_hieu, hinh_anh_chinh
            FROM san_pham_chinh 
            WHERE (ten_san_pham LIKE ? OR ma_san_pham LIKE ? OR thuong_hieu LIKE ?)
              AND trang_thai = 'hoat_dong'
            ORDER BY ten_san_pham ASC
            LIMIT ?
        ");
        
        $search_term = "%$query%";
        $stmt->execute([$search_term, $search_term, $search_term, $limit]);
        
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
}

/**
 * Bulk update products
 */
if (!function_exists('bulkUpdateProducts')) {
    function bulkUpdateProducts($product_ids, $updates) {
        global $pdo;
        
        if (empty($product_ids) || empty($updates)) {
            return false;
        }
        
        try {
            $pdo->beginTransaction();
            
            $set_clauses = [];
            $params = [];
            
            // Build SET clause
            foreach ($updates as $field => $value) {
                $set_clauses[] = "$field = ?";
                $params[] = $value;
            }
            
            // Add product IDs for WHERE clause
            $placeholders = str_repeat('?,', count($product_ids) - 1) . '?';
            $params = array_merge($params, $product_ids);
            
            $sql = "UPDATE san_pham_chinh SET " . implode(', ', $set_clauses) . 
                   " WHERE id IN ($placeholders)";
            
            $stmt = $pdo->prepare($sql);
            $result = $stmt->execute($params);
            
            $pdo->commit();
            
            return $result;
            
        } catch (Exception $e) {
            $pdo->rollback();
            error_log("Bulk update failed: " . $e->getMessage());
            return false;
        }
    }
}

/**
 * Generate product report
 */
if (!function_exists('generateProductReport')) {
    function generateProductReport($type = 'summary', $filters = []) {
        global $pdo;
        
        $report = [];
        
        try {
            switch ($type) {
                case 'summary':
                    // Basic statistics
                    $stmt = $pdo->query("
                        SELECT 
                            COUNT(*) as total_products,
                            COUNT(CASE WHEN trang_thai = 'hoat_dong' THEN 1 END) as active_products,
                            COUNT(CASE WHEN trang_thai = 'het_hang' THEN 1 END) as out_of_stock,
                            COUNT(CASE WHEN trang_thai = 'an' THEN 1 END) as hidden_products,
                            AVG(gia_goc) as avg_price
                        FROM san_pham_chinh
                    ");
                    $report['summary'] = $stmt->fetch(PDO::FETCH_ASSOC);
                    break;
                    
                case 'by_category':
                    $stmt = $pdo->query("
                        SELECT dm.ten_danh_muc, COUNT(sp.id) as product_count
                        FROM danh_muc_giay dm
                        LEFT JOIN san_pham_chinh sp ON dm.id = sp.danh_muc_id
                        GROUP BY dm.id, dm.ten_danh_muc
                        ORDER BY product_count DESC
                    ");
                    $report['by_category'] = $stmt->fetchAll(PDO::FETCH_ASSOC);
                    break;
                    
                case 'by_brand':
                    $stmt = $pdo->query("
                        SELECT thuong_hieu, COUNT(*) as product_count
                        FROM san_pham_chinh
                        GROUP BY thuong_hieu
                        ORDER BY product_count DESC
                    ");
                    $report['by_brand'] = $stmt->fetchAll(PDO::FETCH_ASSOC);
                    break;
                    
                case 'low_stock':
                    $stmt = $pdo->query("
                        SELECT sp.*, SUM(btp.so_luong_ton_kho) as total_stock
                        FROM san_pham_chinh sp
                        LEFT JOIN bien_the_san_pham btp ON sp.id = btp.san_pham_id
                        GROUP BY sp.id
                        HAVING total_stock <= 5 OR total_stock IS NULL
                        ORDER BY total_stock ASC
                    ");
                    $report['low_stock'] = $stmt->fetchAll(PDO::FETCH_ASSOC);
                    break;
            }
            
        } catch (Exception $e) {
            error_log("Report generation failed: " . $e->getMessage());
        }
        
        return $report;
    }
}

/**
 * Validate image file
 */
if (!function_exists('validateImageFile')) {
    function validateImageFile($file) {
        $errors = [];
        
        // Check if file was uploaded
        if (!isset($file) || $file['error'] !== UPLOAD_ERR_OK) {
            $errors[] = 'Không có file được upload hoặc có lỗi xảy ra';
            return $errors;
        }
        
        // Check file size (2MB max)
        if ($file['size'] > 2 * 1024 * 1024) {
            $errors[] = 'File quá lớn. Kích thước tối đa là 2MB';
        }
        
        // Check file type
        $allowed_types = ['image/jpeg', 'image/jpg', 'image/png', 'image/gif'];
        $file_type = mime_content_type($file['tmp_name']);
        
        if (!in_array($file_type, $allowed_types)) {
            $errors[] = 'Định dạng file không được hỗ trợ. Chỉ chấp nhận: JPG, PNG, GIF';
        }
        
        // Check image dimensions
        $image_info = getimagesize($file['tmp_name']);
        if ($image_info === false) {
            $errors[] = 'File không phải là hình ảnh hợp lệ';
        } else {
            $width = $image_info[0];
            $height = $image_info[1];
            
            // Minimum dimensions
            if ($width < 100 || $height < 100) {
                $errors[] = 'Kích thước ảnh quá nhỏ. Tối thiểu 100x100 pixels';
            }
            
            // Maximum dimensions
            if ($width > 5000 || $height > 5000) {
                $errors[] = 'Kích thước ảnh quá lớn. Tối đa 5000x5000 pixels';
            }
        }
        
        return $errors;
    }
}

/**
 * Optimize database for products
 */
if (!function_exists('optimizeProductDatabase')) {
    function optimizeProductDatabase() {
        global $pdo;
        
        try {
            // Optimize tables
            $tables = ['san_pham_chinh', 'bien_the_san_pham', 'danh_muc_giay', 'kich_co', 'mau_sac'];
            
            foreach ($tables as $table) {
                $pdo->exec("OPTIMIZE TABLE $table");
            }
            
            // Update statistics
            $pdo->exec("ANALYZE TABLE " . implode(', ', $tables));
            
            return true;
            
        } catch (Exception $e) {
            error_log("Database optimization failed: " . $e->getMessage());
            return false;
        }
    }
}

/**
 * Get product metrics for dashboard
 */
if (!function_exists('getProductMetrics')) {
    function getProductMetrics($days = 30) {
        global $pdo;
        
        $metrics = [];
        
        try {
            // Products added in last X days
            $stmt = $pdo->prepare("
                SELECT COUNT(*) 
                FROM san_pham_chinh 
                WHERE ngay_tao >= DATE_SUB(NOW(), INTERVAL ? DAY)
            ");
            $stmt->execute([$days]);
            $metrics['new_products'] = $stmt->fetchColumn();
            
            // Most viewed products
            $stmt = $pdo->prepare("
                SELECT sp.ten_san_pham, sp.view_count
                FROM san_pham_chinh sp
                WHERE sp.view_count > 0
                ORDER BY sp.view_count DESC
                LIMIT 5
            ");
            $stmt->execute();
            $metrics['most_viewed'] = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            // Categories with most products
            $stmt = $pdo->query("
                SELECT dm.ten_danh_muc, COUNT(sp.id) as count
                FROM danh_muc_giay dm
                LEFT JOIN san_pham_chinh sp ON dm.id = sp.danh_muc_id
                GROUP BY dm.id
                ORDER BY count DESC
                LIMIT 5
            ");
            $metrics['top_categories'] = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
        } catch (Exception $e) {
            error_log("Metrics generation failed: " . $e->getMessage());
        }
        
        return $metrics;
    }
}

// Initialize some constants if not defined
if (!defined('UPLOAD_URL')) {
    define('UPLOAD_URL', BASE_URL . '/uploads');
}

if (!defined('PRODUCT_IMAGE_SIZES')) {
    define('PRODUCT_IMAGE_SIZES', [
        'thumbnail' => [150, 150],
        'medium' => [400, 400],
        'large' => [800, 800]
    ]);
}

if (!defined('DEFAULT_PRODUCT_IMAGE')) {
    define('DEFAULT_PRODUCT_IMAGE', 'no-image.jpg');
}

if (!defined('MAX_PRODUCT_IMAGES')) {
    define('MAX_PRODUCT_IMAGES', 10);
}

if (!defined('PRODUCT_SLUG_LENGTH')) {
    define('PRODUCT_SLUG_LENGTH', 100);
}

// Auto-load functions when file is included
if (function_exists('optimizeProductDatabase')) {
    // Run optimization weekly (can be moved to cron job)
    $last_optimization = get_option('last_db_optimization', 0);
    if (time() - $last_optimization > 7 * 24 * 60 * 60) { // 7 days
        optimizeProductDatabase();
        set_option('last_db_optimization', time());
    }
}

/**
 * Simple options system for storing settings
 */
if (!function_exists('get_option')) {
    function get_option($key, $default = null) {
        global $pdo;
        try {
            $stmt = $pdo->prepare("SELECT option_value FROM options WHERE option_key = ?");
            $stmt->execute([$key]);
            $result = $stmt->fetchColumn();
            return $result !== false ? $result : $default;
        } catch (Exception $e) {
            return $default;
        }
    }
}

if (!function_exists('set_option')) {
    function set_option($key, $value) {
        global $pdo;
        try {
            $stmt = $pdo->prepare("
                INSERT INTO options (option_key, option_value) 
                VALUES (?, ?) 
                ON DUPLICATE KEY UPDATE option_value = VALUES(option_value)
            ");
            return $stmt->execute([$key, $value]);
        } catch (Exception $e) {
            return false;
        }
    }
}
?>