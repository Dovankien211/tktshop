<?php
// admin/logout.php - ĐÃ SỬA ĐƯỜNG DẪN
/**
 * Đăng xuất admin
 */
session_start();

// Xóa tất cả session
session_unset();
session_destroy();

// Xóa cookie session nếu có
if (ini_get("session.use_cookies")) {
    $params = session_get_cookie_params();
    setcookie(session_name(), '', time() - 42000,
        $params["path"], $params["domain"],
        $params["secure"], $params["httponly"]
    );
}

// Include config để dùng redirect function
require_once '../config/config.php';

// Chuyển hướng về trang đăng nhập - ĐÃ SỬA
redirect('admin/login.php');
?>