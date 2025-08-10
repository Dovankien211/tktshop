<?php
/**
 * TKT Shop - AJAX Products Handler
 * Xử lý các request AJAX cho quản lý sản phẩm
 */

require_once '../../config/config.php';
require_once '../../config/database.php';

// Kiểm tra đăng nhập admin
if (!isset($_SESSION['admin_id'])) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Unauthorized access']);
    exit;
}

// Lấy action từ request
$action = $_POST['action'] ?? $_GET['action'] ?? '';

// Xử lý theo action
switch ($action) {
    case 'delete':
        deleteProduct();
        break;
    
    case 'toggle_status':
        toggleProductStatus();
        break;
    
    case 'quick_edit':
        quickEditProduct();
        break;
    
    case 'bulk_delete':
        bulkDeleteProducts();
        break;
    
    case 'bulk_update_status':
        bulkUpdateStatus();
        break;
    
    case 'get_product_data':
        getProductData();
        break;
    
    case 'check_sku':
        checkSKU();
        break;
    
    case 'update_inventory':
        updateInventory();
        break;
    
    default:
        echo json_encode(['success' => false, 'message' => 'Invalid action']);
        break;
}

/**
 * Xóa sản phẩm
 */
function deleteProduct() {
    global $pdo;
    
    $product_id = $_POST['product_id'] ?? 0;
    
    if (!$product_id) {
        echo json_encode(['success' => false, 'message' => 'Product ID is required']);
        return;
    }
    
    try {
        $pdo->beginTransaction();
        
        // Kiểm tra xem sản phẩm có trong đơn hàng nào không
        $check_orders = $pdo->prepare("SELECT COUNT(*) FROM order_items WHERE product_id = ?");
        $check_orders->execute([$product_id]);
        
        if ($check_orders->fetchColumn() > 0) {
            echo json_encode(['success' => false, 'message' => 'Cannot delete product that has orders']);
            return;
        }
        
        // Lấy thông tin ảnh để xóa file
        $stmt = $pdo->prepare("SELECT image, gallery FROM products WHERE id = ?");
        $stmt->execute([$product_id]);
        $product = $stmt->fetch();
        
        if (!$product) {
            echo json_encode(['success' => false, 'message' => 'Product not found']);
            return;
        }
        
        // Xóa các bảng liên quan
        $tables = [
            'product_variants',
            'product_reviews',
            'cart_items',
            'wishlist_items'
        ];
        
        foreach ($tables as $table) {
            $delete_stmt = $pdo->prepare("DELETE FROM {$table} WHERE product_id = ?");
            $delete_stmt->execute([$product_id]);
        }
        
        // Xóa sản phẩm chính
        $delete_product = $pdo->prepare("DELETE FROM products WHERE id = ?");
        $delete_product->execute([$product_id]);
        
        if ($delete_product->rowCount() > 0) {
            // Xóa file ảnh
            deleteProductImages($product['image'], $product['gallery']);
            
            $pdo->commit();
            echo json_encode(['success' => true, 'message' => 'Product deleted successfully']);
        } else {
            $pdo->rollback();
            echo json_encode(['success' => false, 'message' => 'Failed to delete product']);
        }
        
    } catch (Exception $e) {
        $pdo->rollback();
        error_log("Delete product error: " . $e->getMessage());
        echo json_encode(['success' => false, 'message' => 'Database error occurred']);
    }
}

/**
 * Thay đổi trạng thái sản phẩm (active/inactive)
 */
function toggleProductStatus() {
    global $pdo;
    
    $product_id = $_POST['product_id'] ?? 0;
    
    if (!$product_id) {
        echo json_encode(['success' => false, 'message' => 'Product ID is required']);
        return;
    }
    
    try {
        // Lấy trạng thái hiện tại
        $stmt = $pdo->prepare("SELECT status FROM products WHERE id = ?");
        $stmt->execute([$product_id]);
        $current_status = $stmt->fetchColumn();
        
        if ($current_status === false) {
            echo json_encode(['success' => false, 'message' => 'Product not found']);
            return;
        }
        
        // Thay đổi trạng thái
        $new_status = ($current_status === 'active') ? 'inactive' : 'active';
        
        $update_stmt = $pdo->prepare("UPDATE products SET status = ?, updated_at = NOW() WHERE id = ?");
        $update_stmt->execute([$new_status, $product_id]);
        
        if ($update_stmt->rowCount() > 0) {
            echo json_encode([
                'success' => true, 
                'message' => 'Product status updated successfully',
                'status' => $new_status
            ]);
        } else {
            echo json_encode(['success' => false, 'message' => 'Failed to update status']);
        }
        
    } catch (Exception $e) {
        error_log("Toggle status error: " . $e->getMessage());
        echo json_encode(['success' => false, 'message' => 'Database error occurred']);
    }
}

/**
 * Chỉnh sửa nhanh sản phẩm (inline editing)
 */
function quickEditProduct() {
    global $pdo;
    
    $product_id = $_POST['product_id'] ?? 0;
    $field = $_POST['field'] ?? '';
    $value = $_POST['value'] ?? '';
    
    if (!$product_id || !$field) {
        echo json_encode(['success' => false, 'message' => 'Missing required parameters']);
        return;
    }
    
    // Whitelist các field được phép edit
    $allowed_fields = ['name', 'price', 'quantity', 'sku', 'weight'];
    
    if (!in_array($field, $allowed_fields)) {
        echo json_encode(['success' => false, 'message' => 'Field not allowed for editing']);
        return;
    }
    
    try {
        // Validate dữ liệu theo field
        if ($field === 'price' && (!is_numeric($value) || $value < 0)) {
            echo json_encode(['success' => false, 'message' => 'Invalid price value']);
            return;
        }
        
        if ($field === 'quantity' && (!is_numeric($value) || $value < 0)) {
            echo json_encode(['success' => false, 'message' => 'Invalid quantity value']);
            return;
        }
        
        if ($field === 'name' && strlen(trim($value)) < 2) {
            echo json_encode(['success' => false, 'message' => 'Product name too short']);
            return;
        }
        
        // Kiểm tra SKU trùng lặp
        if ($field === 'sku') {
            $check_sku = $pdo->prepare("SELECT id FROM products WHERE sku = ? AND id != ?");
            $check_sku->execute([$value, $product_id]);
            if ($check_sku->fetch()) {
                echo json_encode(['success' => false, 'message' => 'SKU already exists']);
                return;
            }
        }
        
        // Cập nhật field
        $sql = "UPDATE products SET {$field} = ?, updated_at = NOW() WHERE id = ?";
        $stmt = $pdo->prepare($sql);
        $stmt->execute([$value, $product_id]);
        
        if ($stmt->rowCount() > 0) {
            echo json_encode([
                'success' => true, 
                'message' => 'Product updated successfully',
                'field' => $field,
                'value' => $value
            ]);
        } else {
            echo json_encode(['success' => false, 'message' => 'No changes made']);
        }
        
    } catch (Exception $e) {
        error_log("Quick edit error: " . $e->getMessage());
        echo json_encode(['success' => false, 'message' => 'Database error occurred']);
    }
}

/**
 * Xóa nhiều sản phẩm cùng lúc
 */
function bulkDeleteProducts() {
    global $pdo;
    
    $product_ids = $_POST['product_ids'] ?? [];
    
    if (empty($product_ids) || !is_array($product_ids)) {
        echo json_encode(['success' => false, 'message' => 'No products selected']);
        return;
    }
    
    try {
        $pdo->beginTransaction();
        
        $deleted_count = 0;
        $failed_products = [];
        
        foreach ($product_ids as $product_id) {
            // Kiểm tra đơn hàng
            $check_orders = $pdo->prepare("SELECT COUNT(*) FROM order_items WHERE product_id = ?");
            $check_orders->execute([$product_id]);
            
            if ($check_orders->fetchColumn() > 0) {
                $failed_products[] = $product_id;
                continue;
            }
            
            // Lấy thông tin ảnh
            $stmt = $pdo->prepare("SELECT image, gallery FROM products WHERE id = ?");
            $stmt->execute([$product_id]);
            $product = $stmt->fetch();
            
            if (!$product) continue;
            
            // Xóa dữ liệu liên quan
            $tables = ['product_variants', 'product_reviews', 'cart_items', 'wishlist_items'];
            foreach ($tables as $table) {
                $delete_stmt = $pdo->prepare("DELETE FROM {$table} WHERE product_id = ?");
                $delete_stmt->execute([$product_id]);
            }
            
            // Xóa sản phẩm
            $delete_product = $pdo->prepare("DELETE FROM products WHERE id = ?");
            $delete_product->execute([$product_id]);
            
            if ($delete_product->rowCount() > 0) {
                deleteProductImages($product['image'], $product['gallery']);
                $deleted_count++;
            }
        }
        
        $pdo->commit();
        
        $message = "Deleted {$deleted_count} products successfully";
        if (!empty($failed_products)) {
            $message .= ". " . count($failed_products) . " products couldn't be deleted (have orders)";
        }
        
        echo json_encode([
            'success' => true, 
            'message' => $message,
            'deleted_count' => $deleted_count,
            'failed_count' => count($failed_products)
        ]);
        
    } catch (Exception $e) {
        $pdo->rollback();
        error_log("Bulk delete error: " . $e->getMessage());
        echo json_encode(['success' => false, 'message' => 'Database error occurred']);
    }
}

/**
 * Cập nhật trạng thái nhiều sản phẩm
 */
function bulkUpdateStatus() {
    global $pdo;
    
    $product_ids = $_POST['product_ids'] ?? [];
    $status = $_POST['status'] ?? '';
    
    if (empty($product_ids) || !in_array($status, ['active', 'inactive'])) {
        echo json_encode(['success' => false, 'message' => 'Invalid parameters']);
        return;
    }
    
    try {
        $placeholders = str_repeat('?,', count($product_ids) - 1) . '?';
        $sql = "UPDATE products SET status = ?, updated_at = NOW() WHERE id IN ($placeholders)";
        
        $params = array_merge([$status], $product_ids);
        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        
        $updated_count = $stmt->rowCount();
        
        echo json_encode([
            'success' => true, 
            'message' => "Updated {$updated_count} products to {$status}",
            'updated_count' => $updated_count
        ]);
        
    } catch (Exception $e) {
        error_log("Bulk update status error: " . $e->getMessage());
        echo json_encode(['success' => false, 'message' => 'Database error occurred']);
    }
}

/**
 * Lấy dữ liệu sản phẩm để edit
 */
function getProductData() {
    global $pdo;
    
    $product_id = $_GET['product_id'] ?? 0;
    
    if (!$product_id) {
        echo json_encode(['success' => false, 'message' => 'Product ID is required']);
        return;
    }
    
    try {
        $stmt = $pdo->prepare("
            SELECT p.*, c.name as category_name 
            FROM products p 
            LEFT JOIN categories c ON p.category_id = c.id 
            WHERE p.id = ?
        ");
        $stmt->execute([$product_id]);
        $product = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$product) {
            echo json_encode(['success' => false, 'message' => 'Product not found']);
            return;
        }
        
        // Lấy variants nếu có
        $variants_stmt = $pdo->prepare("
            SELECT * FROM product_variants WHERE product_id = ? ORDER BY id
        ");
        $variants_stmt->execute([$product_id]);
        $variants = $variants_stmt->fetchAll(PDO::FETCH_ASSOC);
        
        $product['variants'] = $variants;
        
        echo json_encode(['success' => true, 'product' => $product]);
        
    } catch (Exception $e) {
        error_log("Get product data error: " . $e->getMessage());
        echo json_encode(['success' => false, 'message' => 'Database error occurred']);
    }
}

/**
 * Kiểm tra SKU trùng lặp
 */
function checkSKU() {
    global $pdo;
    
    $sku = $_POST['sku'] ?? '';
    $product_id = $_POST['product_id'] ?? 0;
    
    if (!$sku) {
        echo json_encode(['success' => false, 'message' => 'SKU is required']);
        return;
    }
    
    try {
        $sql = "SELECT COUNT(*) FROM products WHERE sku = ?";
        $params = [$sku];
        
        if ($product_id) {
            $sql .= " AND id != ?";
            $params[] = $product_id;
        }
        
        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        $count = $stmt->fetchColumn();
        
        echo json_encode([
            'success' => true,
            'available' => $count == 0,
            'message' => $count > 0 ? 'SKU already exists' : 'SKU is available'
        ]);
        
    } catch (Exception $e) {
        error_log("Check SKU error: " . $e->getMessage());
        echo json_encode(['success' => false, 'message' => 'Database error occurred']);
    }
}

/**
 * Cập nhật tồn kho
 */
function updateInventory() {
    global $pdo;
    
    $product_id = $_POST['product_id'] ?? 0;
    $quantity = $_POST['quantity'] ?? 0;
    $operation = $_POST['operation'] ?? 'set'; // set, add, subtract
    
    if (!$product_id) {
        echo json_encode(['success' => false, 'message' => 'Product ID is required']);
        return;
    }
    
    try {
        // Lấy số lượng hiện tại
        $stmt = $pdo->prepare("SELECT quantity FROM products WHERE id = ?");
        $stmt->execute([$product_id]);
        $current_quantity = $stmt->fetchColumn();
        
        if ($current_quantity === false) {
            echo json_encode(['success' => false, 'message' => 'Product not found']);
            return;
        }
        
        // Tính toán số lượng mới
        switch ($operation) {
            case 'add':
                $new_quantity = $current_quantity + $quantity;
                break;
            case 'subtract':
                $new_quantity = max(0, $current_quantity - $quantity);
                break;
            case 'set':
            default:
                $new_quantity = max(0, $quantity);
                break;
        }
        
        // Cập nhật
        $update_stmt = $pdo->prepare("UPDATE products SET quantity = ?, updated_at = NOW() WHERE id = ?");
        $update_stmt->execute([$new_quantity, $product_id]);
        
        // Log inventory change
        $log_stmt = $pdo->prepare("
            INSERT INTO inventory_logs (product_id, old_quantity, new_quantity, operation, admin_id, created_at) 
            VALUES (?, ?, ?, ?, ?, NOW())
        ");
        $log_stmt->execute([$product_id, $current_quantity, $new_quantity, $operation, $_SESSION['admin_id']]);
        
        echo json_encode([
            'success' => true,
            'message' => 'Inventory updated successfully',
            'old_quantity' => $current_quantity,
            'new_quantity' => $new_quantity
        ]);
        
    } catch (Exception $e) {
        error_log("Update inventory error: " . $e->getMessage());
        echo json_encode(['success' => false, 'message' => 'Database error occurred']);
    }
}

/**
 * Xóa file ảnh sản phẩm
 */
function deleteProductImages($main_image, $gallery) {
    // Xóa ảnh chính
    if ($main_image && file_exists("../../uploads/products/" . $main_image)) {
        unlink("../../uploads/products/" . $main_image);
    }
    
    // Xóa ảnh gallery
    if ($gallery) {
        $gallery_images = json_decode($gallery, true);
        if (is_array($gallery_images)) {
            foreach ($gallery_images as $image) {
                if (file_exists("../../uploads/products/" . $image)) {
                    unlink("../../uploads/products/" . $image);
                }
            }
        }
    }
}

/**
 * Log hoạt động admin
 */
function logAdminActivity($action, $details = '') {
    global $pdo;
    
    try {
        $stmt = $pdo->prepare("
            INSERT INTO admin_activity_logs (admin_id, action, details, ip_address, user_agent, created_at) 
            VALUES (?, ?, ?, ?, ?, NOW())
        ");
        $stmt->execute([
            $_SESSION['admin_id'],
            $action,
            $details,
            $_SERVER['REMOTE_ADDR'] ?? '',
            $_SERVER['HTTP_USER_AGENT'] ?? ''
        ]);
    } catch (Exception $e) {
        error_log("Log activity error: " . $e->getMessage());
    }
}

/**
 * Validate admin permission
 */
function hasPermission($permission) {
    global $pdo;
    
    try {
        $stmt = $pdo->prepare("
            SELECT COUNT(*) FROM admin_permissions ap
            JOIN admins a ON a.role_id = ap.role_id
            WHERE a.id = ? AND ap.permission = ?
        ");
        $stmt->execute([$_SESSION['admin_id'], $permission]);
        return $stmt->fetchColumn() > 0;
    } catch (Exception $e) {
        return false;
    }
}

/**
 * Sanitize input data
 */
function sanitizeInput($data) {
    if (is_array($data)) {
        return array_map('sanitizeInput', $data);
    }
    return htmlspecialchars(trim($data), ENT_QUOTES, 'UTF-8');
}

/**
 * Validate image file
 */
function validateImage($file) {
    $allowed_types = ['image/jpeg', 'image/png', 'image/gif', 'image/webp'];
    $max_size = 5 * 1024 * 1024; // 5MB
    
    if (!in_array($file['type'], $allowed_types)) {
        return ['success' => false, 'message' => 'Invalid image format'];
    }
    
    if ($file['size'] > $max_size) {
        return ['success' => false, 'message' => 'Image size too large (max 5MB)'];
    }
    
    return ['success' => true];
}

/**
 * Upload image file
 */
function uploadImage($file, $prefix = '') {
    $upload_dir = "../../uploads/products/";
    
    // Tạo thư mục nếu chưa có
    if (!file_exists($upload_dir)) {
        mkdir($upload_dir, 0755, true);
    }
    
    // Validate file
    $validation = validateImage($file);
    if (!$validation['success']) {
        return $validation;
    }
    
    // Generate unique filename
    $extension = pathinfo($file['name'], PATHINFO_EXTENSION);
    $filename = $prefix . uniqid() . '_' . time() . '.' . $extension;
    $filepath = $upload_dir . $filename;
    
    if (move_uploaded_file($file['tmp_name'], $filepath)) {
        return ['success' => true, 'filename' => $filename];
    } else {
        return ['success' => false, 'message' => 'Failed to upload image'];
    }
}

// Đảm bảo response là JSON
header('Content-Type: application/json');
?>