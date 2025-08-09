<?php
require_once '../../config/config.php';
require_once '../../config/database.php';

// Kiểm tra đăng nhập admin
if (!isset($_SESSION['admin_id'])) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit;
}

$action = $_POST['action'] ?? $_GET['action'] ?? '';

switch ($action) {
    case 'delete':
        $product_id = $_POST['product_id'] ?? 0;
        if ($product_id) {
            $sql = "DELETE FROM products WHERE id = ?";
            $stmt = $pdo->prepare($sql);
            if ($stmt->execute([$product_id])) {
                echo json_encode(['success' => true, 'message' => 'Xóa sản phẩm thành công']);
            } else {
                echo json_encode(['success' => false, 'message' => 'Không thể xóa sản phẩm']);
            }
        }
        break;
    
    case 'quick_edit':
        // Xử lý chỉnh sửa nhanh
        break;
    
    default:
        echo json_encode(['success' => false, 'message' => 'Action không hợp lệ']);
}
?>