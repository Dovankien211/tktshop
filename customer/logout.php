<?php
// customer/logout.php
/**
 * Đăng xuất khách hàng - Xóa session, cookies, chuyển hướng
 * Chức năng: Xử lý logout an toàn, xóa remember token, redirect
 */

require_once '../config/database.php';
require_once '../config/config.php';

// Kiểm tra đã đăng nhập chưa
if (!isset($_SESSION['customer_id'])) {
    redirect('/customer/login.php');
}

try {
    // Xóa remember token nếu có
    if (isset($_COOKIE['remember_token'])) {
        // TODO: Xóa token khỏi database khi có bảng remember_tokens
        // $pdo->prepare("DELETE FROM remember_tokens WHERE token = ?")->execute([$_COOKIE['remember_token']]);
        
        // Xóa cookie
        setcookie('remember_token', '', time() - 3600, '/', '', false, true);
    }
    
    // Lưu thông tin để hiển thị thông báo
    $customer_name = $_SESSION['customer_name'] ?? 'bạn';
    
    // Xóa tất cả session data liên quan đến customer
    unset($_SESSION['customer_id']);
    unset($_SESSION['customer_name']);
    unset($_SESSION['customer_email']);
    
    // Không xóa session_id để giữ giỏ hàng cho guest
    // unset($_SESSION['session_id']);
    
    // Regenerate session ID để bảo mật
    session_regenerate_id(true);
    
    // Set thông báo đăng xuất thành công
    alert("Đăng xuất thành công! Hẹn gặp lại $customer_name.", 'success');
    
} catch (Exception $e) {
    alert('Có lỗi xảy ra khi đăng xuất. Vui lòng thử lại.', 'danger');
}

// Chuyển hướng về trang chủ
redirect('/customer/');
?>