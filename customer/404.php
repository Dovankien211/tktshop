<?php
// customer/404.php - Enhanced 404 with product suggestions
/**
 * Trang 404 thông minh với gợi ý sản phẩm và tìm kiếm
 */
session_start();

require_once '../config/database.php';
require_once '../config/config.php';

// Set 404 status
http_response_code(404);

// Lấy URL được request
$requested_url = $_SERVER['REQUEST_URI'] ?? '';
$search_term = '';

// Extract potential search terms from URL
if (preg_match('/\/([a-zA-Z0-9\-_]+)/', $requested_url, $matches)) {
    $search_term = str_replace(['-', '_'], ' ', $matches[1]);
}

// Tìm sản phẩm gợi ý dựa trên search term
$suggested_products = [];
$popular_products = [];

try {
    // Nếu có search term, tìm sản phẩm liên quan
    if (!empty($search_term)) {
        $stmt = $pdo->prepare("
            SELECT sp.*, dm.ten_danh_muc,
                   COALESCE(sp.gia_khuyen_mai, sp.gia_goc)