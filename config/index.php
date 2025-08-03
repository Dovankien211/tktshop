<?php
/**
 * FILE: config/index.php
 * Mục đích: Ngăn chặn directory listing
 */
<?php
// Redirect về trang chủ nếu truy cập trực tiếp
header('Location: ../customer/index.php');
exit();
?>

<!-- ============================= -->

<?php
/**
 * FILE: uploads/index.php  
 * Mục đích: Ngăn chặn directory listing
 */
<?php
// Chặn truy cập trực tiếp vào thư mục uploads
http_response_code(403);
die('Access Denied');
?>

<!-- ============================= -->

<?php
/**
 * FILE: admin/index.php
 * Mục đích: Redirect về login admin
 */
<?php
// Redirect về trang login admin
header('Location: colors/index.php'); // Hoặc trang admin chính
exit();
?>

<!-- ============================= -->

<?php
/**
 * FILE: .htaccess (Tạo trong thư mục gốc)
 * Mục đích: Bảo mật và URL rewriting
 */
# Ngăn chặn truy cập file config
<FilesMatch "\.(sql|log|txt|md)$">
    Order allow,deny
    Deny from all
</FilesMatch>

# Ngăn chặn truy cập thư mục config
<Directory "config">
    Order allow,deny
    Deny from all
</Directory>

# URL Rewrite
RewriteEngine On

# Redirect trang chủ
RewriteRule ^$ customer/index.php [L]

# Trang sản phẩm thân thiện
RewriteRule ^product/([0-9]+)/?$ customer/product_detail.php?id=$1 [L,QSA]
RewriteRule ^products/?$ customer/products.php [L,QSA]
RewriteRule ^category/([0-9]+)/?$ customer/products.php?category_id=$1 [L,QSA]

# Admin routes
RewriteRule ^admin/?$ admin/colors/index.php [L]

# Bảo mật headers
<IfModule mod_headers.c>
    Header always set X-Content-Type-Options nosniff
    Header always set X-Frame-Options DENY
    Header always set X-XSS-Protection "1; mode=block"
    Header always set Referrer-Policy "strict-origin-when-cross-origin"
</IfModule>

# Gzip compression
<IfModule mod_deflate.c>
    AddOutputFilterByType DEFLATE text/html text/css text/javascript application/javascript application/json
</IfModule>
?>