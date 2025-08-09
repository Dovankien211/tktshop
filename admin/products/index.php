<?php
// Test file để kiểm tra đường dẫn
session_start();

echo "<h1>ĐƯỜNG DẪN HOẠT ĐỘNG!</h1>";
echo "<p>Bạn đang ở: <strong>/admin/products/index.php</strong></p>";
echo "<p>Current URL: " . $_SERVER['REQUEST_URI'] . "</p>";

// Kiểm tra session
if (isset($_SESSION['user_id'])) {
    echo "<p>User ID: " . $_SESSION['user_id'] . "</p>";
    echo "<p>Role: " . ($_SESSION['role'] ?? 'Không có') . "</p>";
} else {
    echo "<p style='color: red;'>Chưa đăng nhập!</p>";
}

// Link test
echo "<hr>";
echo "<h3>Test Links:</h3>";
echo "<ul>";
echo "<li><a href='create.php'>Thêm sản phẩm</a></li>";
echo "<li><a href='../dashboard.php'>Dashboard</a></li>";
echo "<li><a href='variants.php?product_id=1'>Biến thể (test)</a></li>";
echo "</ul>";

// Kiểm tra file tồn tại
echo "<hr>";
echo "<h3>Kiểm tra file:</h3>";
echo "<ul>";
echo "<li>create.php: " . (file_exists('create.php') ? '✅ Có' : '❌ Không có') . "</li>";
echo "<li>variants.php: " . (file_exists('variants.php') ? '✅ Có' : '❌ Không có') . "</li>";
echo "<li>../dashboard.php: " . (file_exists('../dashboard.php') ? '✅ Có' : '❌ Không có') . "</li>";
echo "</ul>";
?>

<style>
body { font-family: Arial; padding: 20px; }
h1 { color: green; }
ul { margin: 10px 0; }
li { margin: 5px 0; }
</style>