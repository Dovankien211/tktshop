<?php
/**
 * File: customer/product_detail_helper.php
 * Mục đích: Bổ sung chức năng xử lý product detail để khắc phục lỗi product_not_found
 * Giữ nguyên toàn bộ code hiện tại, chỉ thêm helper functions
 */

// Include file này vào đầu product_detail.php hiện tại
// require_once 'product_detail_helper.php';

/**
 * Tìm sản phẩm theo slug hoặc ID với fallback logic
 */
function findProductWithFallback($pdo, $slug = '', $id = 0) {
    $product = null;
    $product_table = null;
    
    // Priority 1: Tìm theo slug trong san_pham_chinh
    if (!empty($slug)) {
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
                LIMIT 1
            ");
            $stmt->execute([$slug]);
            $product = $stmt->fetch();
            
            if ($product) {
                $product_table = 'san_pham_chinh';
                return ['product' => $product, 'table' => $product_table];
            }
        } catch (Exception $e) {
            error_log("Error finding product by slug in san_pham_chinh: " . $e->getMessage());
        }
    }
    
    // Priority 2: Tìm theo ID trong san_pham_chinh
    if (!$product && $id > 0) {
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
                WHERE sp.id = ? AND sp.trang_thai = 'hoat_dong'
                LIMIT 1
            ");
            $stmt->execute([$id]);
            $product = $stmt->fetch();
            
            if ($product) {
                $product_table = 'san_pham_chinh';
                return ['product' => $product, 'table' => $product_table];
            }
        } catch (Exception $e) {
            error_log("Error finding product by ID in san_pham_chinh: " . $e->getMessage());
        }
    }
    
    // Priority 3: Tìm theo slug trong products (nếu có slug column)
    if (!$product && !empty($slug)) {
        try {
            // Kiểm tra xem bảng products có cột slug không
            $columns = $pdo->query("SHOW COLUMNS FROM products LIKE 'slug'")->fetch();
            
            if ($columns) {
                $stmt = $pdo->prepare("
                    SELECT p.*, c.name as category_name, c.slug as danh_muc_slug,
                           COALESCE(p.sale_price, p.price) as gia_hien_tai,
                           CASE 
                               WHEN p.sale_price IS NOT NULL AND p.sale_price < p.price 
                               THEN ROUND(((p.price - p.sale_price) / p.price) * 100, 0)
                               ELSE 0
                           END as phan_tram_giam,
                           p.name as ten_san_pham,
                           p.description as mo_ta_ngan,
                           p.description as mo_ta_chi_tiet,
                           p.price as gia_goc,
                           p.sale_price as gia_khuyen_mai,
                           p.main_image as hinh_anh_chinh,
                           p.gallery_images as album_hinh_anh,
                           p.brand as thuong_hieu,
                           p.category_id as danh_muc_id,
                           p.is_featured as san_pham_noi_bat,
                           0 as san_pham_moi,
                           0 as san_pham_ban_chay,
                           p.view_count as luot_xem,
                           p.sold_count as so_luong_ban,
                           p.rating_average as diem_danh_gia_tb,
                           p.rating_count as so_luong_danh_gia,
                           c.name as ten_danh_muc
                    FROM products p
                    LEFT JOIN categories c ON p.category_id = c.id
                    WHERE p.slug = ? AND p.status = 'active'
                    LIMIT 1
                ");
                $stmt->execute([$slug]);
                $product = $stmt->fetch();
                
                if ($product) {
                    $product_table = 'products';
                    return ['product' => $product, 'table' => $product_table];
                }
            }
        } catch (Exception $e) {
            error_log("Error finding product by slug in products: " . $e->getMessage());
        }
    }
    
    // Priority 4: Tìm theo ID trong products
    if (!$product && $id > 0) {
        try {
            $stmt = $pdo->prepare("
                SELECT p.*, c.name as category_name, c.slug as danh_muc_slug,
                       COALESCE(p.sale_price, p.price) as gia_hien_tai,
                       CASE 
                           WHEN p.sale_price IS NOT NULL AND p.sale_price < p.price 
                           THEN ROUND(((p.price - p.sale_price) / p.price) * 100, 0)
                           ELSE 0
                       END as phan_tram_giam,
                       p.name as ten_san_pham,
                       p.description as mo_ta_ngan,
                       p.description as mo_ta_chi_tiet,
                       p.price as gia_goc,
                       p.sale_price as gia_khuyen_mai,
                       p.main_image as hinh_anh_chinh,
                       p.gallery_images as album_hinh_anh,
                       p.brand as thuong_hieu,
                       p.category_id as danh_muc_id,
                       p.is_featured as san_pham_noi_bat,
                       0 as san_pham_moi,
                       0 as san_pham_ban_chay,
                       p.view_count as luot_xem,
                       p.sold_count as so_luong_ban,
                       p.rating_average as diem_danh_gia_tb,
                       p.rating_count as so_luong_danh_gia,
                       c.name as ten_danh_muc,
                       p.stock_quantity
                FROM products p
                LEFT JOIN categories c ON p.category_id = c.id
                WHERE p.id = ? AND p.status = 'active'
                LIMIT 1
            ");
            $stmt->execute([$id]);
            $product = $stmt->fetch();
            
            if ($product) {
                $product_table = 'products';
                return ['product' => $product, 'table' => $product_table];
            }
        } catch (Exception $e) {
            error_log("Error finding product by ID in products: " . $e->getMessage());
        }
    }
    
    // Priority 5: Tìm kiếm fuzzy matching cho slug
    if (!$product && !empty($slug)) {
        try {
            // Tìm sản phẩm có slug tương tự trong san_pham_chinh
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
                WHERE sp.slug LIKE ? AND sp.trang_thai = 'hoat_dong'
                ORDER BY CASE WHEN sp.slug = ? THEN 1 ELSE 2 END
                LIMIT 1
            ");
            $like_slug = '%' . $slug . '%';
            $stmt->execute([$like_slug, $slug]);
            $product = $stmt->fetch();
            
            if ($product) {
                $product_table = 'san_pham_chinh';
                return ['product' => $product, 'table' => $product_table];
            }
        } catch (Exception $e) {
            error_log("Error fuzzy finding product: " . $e->getMessage());
        }
    }
    
    return null;
}

/**
 * Lấy variants cho sản phẩm
 */
function getProductVariants($pdo, $product_id, $product_table) {
    if ($product_table !== 'san_pham_chinh') {
        return [
            'variants' => [],
            'sizes' => [],
            'colors' => [],
            'variant_matrix' => []
        ];
    }
    
    try {
        $stmt = $pdo->prepare("
            SELECT bsp.*, kc.kich_co, ms.ten_mau, ms.ma_mau
            FROM bien_the_san_pham bsp
            JOIN kich_co kc ON bsp.kich_co_id = kc.id
            JOIN mau_sac ms ON bsp.mau_sac_id = ms.id
            WHERE bsp.san_pham_id = ? AND bsp.trang_thai = 'hoat_dong'
            ORDER BY kc.thu_tu_sap_xep, ms.thu_tu_hien_thi
        ");
        $stmt->execute([$product_id]);
        $variants = $stmt->fetchAll();
        
        // Nhóm biến thể theo size và màu
        $sizes = [];
        $colors = [];
        $variant_matrix = [];
        
        foreach ($variants as $variant) {
            if (!in_array($variant['kich_co'], $sizes)) {
                $sizes[] = $variant['kich_co'];
            }
            if (!isset($colors[$variant['mau_sac_id']])) {
                $colors[$variant['mau_sac_id']] = [
                    'id' => $variant['mau_sac_id'],
                    'ten_mau' => $variant['ten_mau'],
                    'ma_mau' => $variant['ma_mau']
                ];
            }
            $variant_matrix[$variant['kich_co']][$variant['mau_sac_id']] = $variant;
        }
        
        return [
            'variants' => $variants,
            'sizes' => $sizes,
            'colors' => $colors,
            'variant_matrix' => $variant_matrix
        ];
    } catch (Exception $e) {
        error_log("Error getting product variants: " . $e->getMessage());
        return [
            'variants' => [],
            'sizes' => [],
            'colors' => [],
            'variant_matrix' => []
        ];
    }
}

/**
 * Lấy sản phẩm liên quan
 */
function getRelatedProducts($pdo, $product, $product_table, $limit = 4) {
    $related_products = [];
    
    try {
        if ($product_table === 'san_pham_chinh') {
            $stmt = $pdo->prepare("
                SELECT sp.*, MIN(bsp.gia_ban) as gia_thap_nhat,
                       SUM(bsp.so_luong_ton_kho) as tong_ton_kho
                FROM san_pham_chinh sp
                LEFT JOIN bien_the_san_pham bsp ON sp.id = bsp.san_pham_id AND bsp.trang_thai = 'hoat_dong'
                WHERE sp.danh_muc_id = ? AND sp.id != ? AND sp.trang_thai = 'hoat_dong'
                GROUP BY sp.id
                HAVING tong_ton_kho > 0
                ORDER BY sp.luot_xem DESC, sp.so_luong_ban DESC
                LIMIT ?
            ");
            $stmt->execute([$product['danh_muc_id'], $product['id'], $limit]);
        } else {
            $stmt = $pdo->prepare("
                SELECT p.*, c.name as category_name,
                       p.name as ten_san_pham,
                       p.main_image as hinh_anh_chinh,
                       p.price as gia_goc,
                       p.sale_price as gia_khuyen_mai,
                       p.rating_average as diem_danh_gia_tb,
                       p.rating_count as so_luong_danh_gia,
                       p.stock_quantity as tong_ton_kho
                FROM products p
                LEFT JOIN categories c ON p.category_id = c.id
                WHERE p.category_id = ? AND p.id != ? AND p.status = 'active' AND p.stock_quantity > 0
                ORDER BY p.view_count DESC, p.sold_count DESC
                LIMIT ?
            ");
            $stmt->execute([$product['danh_muc_id'], $product['id'], $limit]);
        }
        
        $related_products = $stmt->fetchAll();
    } catch (Exception $e) {
        error_log("Error getting related products: " . $e->getMessage());
    }
    
    return $related_products;
}

/**
 * Cập nhật lượt xem sản phẩm
 */
function updateProductViews($pdo, $product_id, $product_table) {
    try {
        if ($product_table === 'san_pham_chinh') {
            $pdo->prepare("UPDATE san_pham_chinh SET luot_xem = luot_xem + 1 WHERE id = ?")
                ->execute([$product_id]);
        } else {
            $pdo->prepare("UPDATE products SET view_count = view_count + 1 WHERE id = ?")
                ->execute([$product_id]);
        }
    } catch (Exception $e) {
        error_log("Error updating product views: " . $e->getMessage());
    }
}

/**
 * Xử lý album ảnh sản phẩm
 */
function processProductImages($product, $product_table) {
    $product_images = [];
    
    if ($product_table === 'products') {
        // Xử lý gallery_images cho bảng products
        if (!empty($product['gallery_images'])) {
            $gallery = json_decode($product['gallery_images'], true);
            if (is_array($gallery)) {
                $product_images = array_filter($gallery);
            }
        }
        if (!empty($product['hinh_anh_chinh'])) {
            array_unshift($product_images, $product['hinh_anh_chinh']);
        }
    } else {
        // Xử lý album_hinh_anh cho bảng san_pham_chinh
        if (!empty($product['album_hinh_anh'])) {
            $album = json_decode($product['album_hinh_anh'], true);
            if (is_array($album)) {
                foreach ($album as $img) {
                    if (!empty($img) && $img !== 'default-product.jpg') {
                        $product_images[] = $img;
                    }
                }
            }
        }
        if (!empty($product['hinh_anh_chinh']) && $product['hinh_anh_chinh'] !== 'default-product.jpg') {
            array_unshift($product_images, $product['hinh_anh_chinh']);
        }
    }
    
    return array_unique(array_filter($product_images));
}

/**
 * Tạo URL sản phẩm đúng định dạng
 */
function generateProductUrl($product, $product_table) {
    if (!empty($product['slug'])) {
        return "product_detail.php?slug=" . urlencode($product['slug']);
    } else {
        return "product_detail.php?id=" . $product['id'];
    }
}

/**
 * Lấy URL hình ảnh sản phẩm
 */
function getProductImageUrl($imageName, $default = 'no-image.jpg') {
    if (empty($imageName) || $imageName === 'default-product.jpg') {
        return "/tktshop/uploads/products/$default";
    }
    
    // Kiểm tra file có tồn tại không
    $full_path = $_SERVER['DOCUMENT_ROOT'] . "/tktshop/uploads/products/" . $imageName;
    if (file_exists($full_path)) {
        return "/tktshop/uploads/products/" . htmlspecialchars($imageName);
    }
    
    return "/tktshop/uploads/products/$default";
}

/**
 * Debug function - log thông tin sản phẩm
 */
function debugProductSearch($slug, $id, $result) {
    $log_message = "Product Search Debug:\n";
    $log_message .= "- Slug: " . ($slug ?: 'empty') . "\n";
    $log_message .= "- ID: " . ($id ?: 'empty') . "\n";
    $log_message .= "- Result: " . ($result ? 'Found in ' . $result['table'] : 'Not found') . "\n";
    $log_message .= "- Time: " . date('Y-m-d H:i:s') . "\n";
    $log_message .= "----------------------------------------\n";
    
    error_log($log_message);
}

/**
 * Tạo breadcrumb cho sản phẩm
 */
function generateProductBreadcrumb($product, $product_table) {
    $breadcrumb = [
        ['name' => 'Trang chủ', 'url' => '/tktshop/customer/'],
        ['name' => 'Sản phẩm', 'url' => '/tktshop/customer/products.php'],
    ];
    
    // Thêm danh mục nếu có
    if (!empty($product['ten_danh_muc'])) {
        $category_url = '/tktshop/customer/products.php?category=' . $product['danh_muc_id'];
        $breadcrumb[] = ['name' => $product['ten_danh_muc'], 'url' => $category_url];
    }
    
    // Thêm sản phẩm hiện tại
    $breadcrumb[] = ['name' => $product['ten_san_pham'], 'url' => null];
    
    return $breadcrumb;
}

/**
 * Kiểm tra và tạo slug nếu thiếu
 */
function ensureProductSlug($pdo, $product_id, $product_name, $product_table) {
    try {
        if ($product_table === 'san_pham_chinh') {
            // Kiểm tra xem sản phẩm có slug chưa
            $stmt = $pdo->prepare("SELECT slug FROM san_pham_chinh WHERE id = ?");
            $stmt->execute([$product_id]);
            $current_slug = $stmt->fetchColumn();
            
            if (empty($current_slug)) {
                // Tạo slug mới
                $new_slug = createSlug($product_name);
                
                // Kiểm tra slug có trùng không
                $counter = 1;
                $original_slug = $new_slug;
                while (true) {
                    $check = $pdo->prepare("SELECT id FROM san_pham_chinh WHERE slug = ? AND id != ?");
                    $check->execute([$new_slug, $product_id]);
                    if (!$check->fetch()) break;
                    
                    $new_slug = $original_slug . '-' . $counter;
                    $counter++;
                }
                
                // Cập nhật slug
                $pdo->prepare("UPDATE san_pham_chinh SET slug = ? WHERE id = ?")
                    ->execute([$new_slug, $product_id]);
                
                return $new_slug;
            }
            return $current_slug;
        }
    } catch (Exception $e) {
        error_log("Error ensuring product slug: " . $e->getMessage());
    }
    
    return null;
}

/**
 * Thêm các automatic redirects cho các URL pattern phổ biến
 */
function handleProductUrlRedirects() {
    $request_uri = $_SERVER['REQUEST_URI'];
    $query_string = $_SERVER['QUERY_STRING'];
    
    // Parse các pattern URL có thể có
    if (preg_match('/product[_-](\d+)/', $request_uri, $matches)) {
        $product_id = $matches[1];
        header("Location: /tktshop/customer/product_detail.php?id=$product_id", true, 301);
        exit;
    }
    
    if (preg_match('/product[\/]([a-z0-9\-]+)/', $request_uri, $matches)) {
        $slug = $matches[1];
        header("Location: /tktshop/customer/product_detail.php?slug=$slug", true, 301);
        exit;
    }
}

// Gọi function này ở đầu product_detail.php
// handleProductUrlRedirects();
?>