<?php
/**
 * TKT Shop - System Constants
 * File: includes/constants.php
 */

if (!defined('ABSPATH')) {
    define('ABSPATH', dirname(__FILE__) . '/');
}

// ==============================================
// SITE CONFIGURATION
// ==============================================
define('SITE_NAME', 'TKT Shop');
define('SITE_DESCRIPTION', 'Your trusted online fashion store');
define('SITE_KEYWORDS', 'fashion, clothing, shoes, accessories, online shopping');
define('SITE_AUTHOR', 'TKT Development Team');
define('SITE_VERSION', '2.0.0');

// ==============================================
// URL CONFIGURATION  
// ==============================================
define('BASE_URL', 'http://localhost/tktshop');
define('ADMIN_URL', BASE_URL . '/admin');
define('CUSTOMER_URL', BASE_URL . '/customer');
define('ASSETS_URL', BASE_URL . '/assets');
define('UPLOADS_URL', BASE_URL . '/uploads');

// ==============================================
// PATH CONFIGURATION
// ==============================================
define('ROOT_PATH', dirname(__DIR__));
define('ADMIN_PATH', ROOT_PATH . '/admin');
define('CUSTOMER_PATH', ROOT_PATH . '/customer');
define('INCLUDES_PATH', ROOT_PATH . '/includes');
define('UPLOADS_PATH', ROOT_PATH . '/uploads');
define('LOGS_PATH', ROOT_PATH . '/logs');
define('BACKUPS_PATH', ROOT_PATH . '/backups');

// ==============================================
// FILE UPLOAD CONFIGURATION
// ==============================================
define('MAX_FILE_SIZE', 5242880); // 5MB
define('ALLOWED_IMAGE_TYPES', ['jpg', 'jpeg', 'png', 'gif', 'webp']);
define('ALLOWED_DOCUMENT_TYPES', ['pdf', 'doc', 'docx', 'txt']);
define('IMAGE_QUALITY', 85);
define('THUMBNAIL_WIDTH', 300);
define('THUMBNAIL_HEIGHT', 300);

// ==============================================
// PAGINATION CONFIGURATION
// ==============================================
define('PRODUCTS_PER_PAGE', 12);
define('ORDERS_PER_PAGE', 20);
define('REVIEWS_PER_PAGE', 10);
define('USERS_PER_PAGE', 50);
define('ADMIN_ITEMS_PER_PAGE', 25);

// ==============================================
// ORDER STATUS CONSTANTS
// ==============================================
define('ORDER_STATUS_PENDING', 'pending');
define('ORDER_STATUS_CONFIRMED', 'confirmed');
define('ORDER_STATUS_PROCESSING', 'processing');
define('ORDER_STATUS_SHIPPED', 'shipped');
define('ORDER_STATUS_DELIVERED', 'delivered');
define('ORDER_STATUS_CANCELLED', 'cancelled');
define('ORDER_STATUS_RETURNED', 'returned');

// ==============================================
// PAYMENT STATUS CONSTANTS
// ==============================================
define('PAYMENT_STATUS_PENDING', 'pending');
define('PAYMENT_STATUS_PROCESSING', 'processing');
define('PAYMENT_STATUS_COMPLETED', 'completed');
define('PAYMENT_STATUS_FAILED', 'failed');
define('PAYMENT_STATUS_CANCELLED', 'cancelled');
define('PAYMENT_STATUS_REFUNDED', 'refunded');

// ==============================================
// PAYMENT METHOD CONSTANTS
// ==============================================
define('PAYMENT_METHOD_COD', 'cod');
define('PAYMENT_METHOD_VNPAY', 'vnpay');
define('PAYMENT_METHOD_MOMO', 'momo');
define('PAYMENT_METHOD_BANK_TRANSFER', 'bank_transfer');
define('PAYMENT_METHOD_CREDIT_CARD', 'credit_card');

// ==============================================
// USER ROLE CONSTANTS
// ==============================================
define('USER_ROLE_ADMIN', 'admin');
define('USER_ROLE_MANAGER', 'manager');
define('USER_ROLE_CUSTOMER', 'customer');
define('USER_ROLE_GUEST', 'guest');

// ==============================================
// USER STATUS CONSTANTS
// ==============================================
define('USER_STATUS_ACTIVE', 'active');
define('USER_STATUS_INACTIVE', 'inactive');
define('USER_STATUS_SUSPENDED', 'suspended');
define('USER_STATUS_PENDING', 'pending');

// ==============================================
// PRODUCT STATUS CONSTANTS
// ==============================================
define('PRODUCT_STATUS_ACTIVE', 'active');
define('PRODUCT_STATUS_INACTIVE', 'inactive');
define('PRODUCT_STATUS_DRAFT', 'draft');
define('PRODUCT_STATUS_OUT_OF_STOCK', 'out_of_stock');

// ==============================================
// SHIPPING CONFIGURATION
// ==============================================
define('FREE_SHIPPING_THRESHOLD', 500000); // 500k VNĐ
define('STANDARD_SHIPPING_FEE', 25000);     // 25k VNĐ
define('EXPRESS_SHIPPING_FEE', 50000);      // 50k VNĐ
define('SAME_DAY_SHIPPING_FEE', 80000);     // 80k VNĐ

// ==============================================
// TAX CONFIGURATION
// ==============================================
define('DEFAULT_TAX_RATE', 0.10); // 10% VAT
define('TAX_INCLUSIVE', true);

// ==============================================
// DISCOUNT TYPES
// ==============================================
define('DISCOUNT_TYPE_PERCENTAGE', 'percentage');
define('DISCOUNT_TYPE_FIXED', 'fixed');
define('DISCOUNT_TYPE_FREE_SHIPPING', 'free_shipping');

// ==============================================
// COUPON STATUS
// ==============================================
define('COUPON_STATUS_ACTIVE', 'active');
define('COUPON_STATUS_INACTIVE', 'inactive');
define('COUPON_STATUS_EXPIRED', 'expired');
define('COUPON_STATUS_USED_UP', 'used_up');

// ==============================================
// REVIEW STATUS
// ==============================================
define('REVIEW_STATUS_PENDING', 'pending');
define('REVIEW_STATUS_APPROVED', 'approved');
define('REVIEW_STATUS_REJECTED', 'rejected');

// ==============================================
// EMAIL CONFIGURATION
// ==============================================
define('DEFAULT_FROM_EMAIL', 'noreply@tktshop.com');
define('DEFAULT_FROM_NAME', 'TKT Shop');
define('SUPPORT_EMAIL', 'support@tktshop.com');
define('ADMIN_EMAIL', 'admin@tktshop.com');

// ==============================================
// SESSION CONFIGURATION
// ==============================================
define('SESSION_TIMEOUT', 3600); // 1 hour
define('REMEMBER_ME_DURATION', 2592000); // 30 days
define('CSRF_TOKEN_LIFETIME', 1800); // 30 minutes

// ==============================================
// SECURITY CONFIGURATION
// ==============================================
define('MAX_LOGIN_ATTEMPTS', 5);
define('LOGIN_LOCKOUT_TIME', 900); // 15 minutes
define('PASSWORD_MIN_LENGTH', 8);
define('PASSWORD_REQUIRE_UPPERCASE', true);
define('PASSWORD_REQUIRE_LOWERCASE', true);
define('PASSWORD_REQUIRE_NUMBERS', true);
define('PASSWORD_REQUIRE_SYMBOLS', true);

// ==============================================
// CACHE CONFIGURATION
// ==============================================
define('CACHE_ENABLED', true);
define('CACHE_LIFETIME', 3600); // 1 hour
define('PAGE_CACHE_LIFETIME', 1800); // 30 minutes
define('PRODUCT_CACHE_LIFETIME', 7200); // 2 hours

// ==============================================
// LOG LEVELS
// ==============================================
define('LOG_LEVEL_ERROR', 'error');
define('LOG_LEVEL_WARNING', 'warning');
define('LOG_LEVEL_INFO', 'info');
define('LOG_LEVEL_DEBUG', 'debug');

// ==============================================
// CURRENCY CONFIGURATION
// ==============================================
define('DEFAULT_CURRENCY', 'VNĐ');
define('CURRENCY_SYMBOL', '₫');
define('CURRENCY_POSITION', 'after'); // before or after
define('DECIMAL_PLACES', 0);
define('THOUSANDS_SEPARATOR', '.');
define('DECIMAL_SEPARATOR', ',');

// ==============================================
// DATE/TIME CONFIGURATION
// ==============================================
define('DEFAULT_TIMEZONE', 'Asia/Ho_Chi_Minh');
define('DATE_FORMAT', 'd/m/Y');
define('TIME_FORMAT', 'H:i');
define('DATETIME_FORMAT', 'd/m/Y H:i');

// ==============================================
// INVENTORY CONFIGURATION
// ==============================================
define('LOW_STOCK_THRESHOLD', 10);
define('ENABLE_STOCK_MANAGEMENT', true);
define('ALLOW_BACKORDERS', false);
define('STOCK_STATUS_IN_STOCK', 'in_stock');
define('STOCK_STATUS_LOW_STOCK', 'low_stock');
define('STOCK_STATUS_OUT_OF_STOCK', 'out_of_stock');

// ==============================================
// SEARCH CONFIGURATION
// ==============================================
define('MIN_SEARCH_LENGTH', 2);
define('MAX_SEARCH_RESULTS', 100);
define('SEARCH_HIGHLIGHT_ENABLED', true);
define('ENABLE_SEARCH_SUGGESTIONS', true);

// ==============================================
// IMAGE SIZE CONFIGURATION
// ==============================================
define('PRODUCT_IMAGE_LARGE_WIDTH', 800);
define('PRODUCT_IMAGE_LARGE_HEIGHT', 800);
define('PRODUCT_IMAGE_MEDIUM_WIDTH', 400);
define('PRODUCT_IMAGE_MEDIUM_HEIGHT', 400);
define('PRODUCT_IMAGE_SMALL_WIDTH', 150);
define('PRODUCT_IMAGE_SMALL_HEIGHT', 150);
define('CATEGORY_IMAGE_WIDTH', 300);
define('CATEGORY_IMAGE_HEIGHT', 200);
define('USER_AVATAR_SIZE', 150);

// ==============================================
// SOCIAL MEDIA LINKS
// ==============================================
define('FACEBOOK_URL', 'https://facebook.com/tktshop');
define('INSTAGRAM_URL', 'https://instagram.com/tktshop');
define('YOUTUBE_URL', 'https://youtube.com/tktshop');
define('TIKTOK_URL', 'https://tiktok.com/@tktshop');

// ==============================================
// CONTACT INFORMATION
// ==============================================
define('COMPANY_NAME', 'TKT Fashion Company Ltd.');
define('COMPANY_ADDRESS', '123 Fashion Street, District 1, Ho Chi Minh City');
define('COMPANY_PHONE', '+84 28 1234 5678');
define('COMPANY_EMAIL', 'info@tktshop.com');
define('BUSINESS_LICENSE', '0123456789');
define('TAX_CODE', '0123456789-001');

// ==============================================
// API CONFIGURATION
// ==============================================
define('API_VERSION', 'v1');
define('API_RATE_LIMIT', 100); // requests per hour
define('API_KEY_LENGTH', 32);
define('ENABLE_API_LOGGING', true);

// ==============================================
// VNPAY CONFIGURATION
// ==============================================
define('VNPAY_TMN_CODE', 'TKTSHOP01');
define('VNPAY_HASH_SECRET', 'your_vnpay_hash_secret');
define('VNPAY_URL', 'https://sandbox.vnpayment.vn/paymentv2/vpcpay.html');
define('VNPAY_RETURN_URL', BASE_URL . '/vnpay/return.php');
define('VNPAY_API_URL', 'https://sandbox.vnpayment.vn/merchant_webapi/api/transaction');

// ==============================================
// MOMO CONFIGURATION
// ==============================================
define('MOMO_PARTNER_CODE', 'MOMOIQA420180417');
define('MOMO_ACCESS_KEY', 'SvDmj2cOTYZmQQ3H');
define('MOMO_SECRET_KEY', 'PPuDXq1KowPT1ftR8DvlQTHhC03aul17');
define('MOMO_ENDPOINT', 'https://test-payment.momo.vn/gw_payment/transactionProcessor');
define('MOMO_RETURN_URL', BASE_URL . '/momo/return.php');
define('MOMO_NOTIFY_URL', BASE_URL . '/momo/notify.php');

// ==============================================
// BACKUP CONFIGURATION
// ==============================================
define('AUTO_BACKUP_ENABLED', true);
define('BACKUP_FREQUENCY', 'daily'); // daily, weekly, monthly
define('BACKUP_RETENTION_DAYS', 30);
define('BACKUP_COMPRESSION', true);

// ==============================================
// MAINTENANCE MODE
// ==============================================
define('MAINTENANCE_MODE', false);
define('MAINTENANCE_MESSAGE', 'We are currently performing scheduled maintenance. Please check back soon.');
define('MAINTENANCE_ALLOWED_IPS', ['127.0.0.1', '::1']); // Local IPs

// ==============================================
// FEATURE FLAGS
// ==============================================
define('ENABLE_WISHLIST', true);
define('ENABLE_COMPARE', true);
define('ENABLE_REVIEWS', true);
define('ENABLE_RATINGS', true);
define('ENABLE_COUPONS', true);
define('ENABLE_LOYALTY_POINTS', true);
define('ENABLE_GIFT_CARDS', true);
define('ENABLE_NEWSLETTER', true);
define('ENABLE_LIVE_CHAT', true);
define('ENABLE_PUSH_NOTIFICATIONS', true);

// ==============================================
// RATING CONFIGURATION
// ==============================================
define('MAX_RATING', 5);
define('MIN_RATING', 1);
define('ALLOW_GUEST_REVIEWS', false);
define('REQUIRE_PURCHASE_FOR_REVIEW', true);
define('AUTO_APPROVE_REVIEWS', false);

// ==============================================
// LOYALTY POINTS CONFIGURATION
// ==============================================
define('POINTS_PER_VND', 0.01); // 1 point per 100 VNĐ
define('POINTS_REDEMPTION_RATE', 1000); // 1000 points = 10,000 VNĐ
define('MIN_POINTS_REDEMPTION', 500);
define('POINTS_EXPIRY_MONTHS', 12);

// ==============================================
// NEWSLETTER CONFIGURATION
// ==============================================
define('NEWSLETTER_SIGNUP_DISCOUNT', 50000); // 50k VNĐ discount
define('NEWSLETTER_FROM_EMAIL', 'newsletter@tktshop.com');
define('UNSUBSCRIBE_TOKEN_LENGTH', 32);

// ==============================================
// NOTIFICATION TYPES
// ==============================================
define('NOTIFICATION_ORDER_PLACED', 'order_placed');
define('NOTIFICATION_ORDER_CONFIRMED', 'order_confirmed');
define('NOTIFICATION_ORDER_SHIPPED', 'order_shipped');
define('NOTIFICATION_ORDER_DELIVERED', 'order_delivered');
define('NOTIFICATION_ORDER_CANCELLED', 'order_cancelled');
define('NOTIFICATION_PAYMENT_SUCCESS', 'payment_success');
define('NOTIFICATION_PAYMENT_FAILED', 'payment_failed');
define('NOTIFICATION_NEW_PRODUCT', 'new_product');
define('NOTIFICATION_SALE_STARTED', 'sale_started');
define('NOTIFICATION_STOCK_ALERT', 'stock_alert');

// ==============================================
// ERROR CODES
// ==============================================
define('ERROR_INVALID_INPUT', 1001);
define('ERROR_UNAUTHORIZED', 1002);
define('ERROR_FORBIDDEN', 1003);
define('ERROR_NOT_FOUND', 1004);
define('ERROR_CONFLICT', 1005);
define('ERROR_VALIDATION_FAILED', 1006);
define('ERROR_PAYMENT_FAILED', 1007);
define('ERROR_INSUFFICIENT_STOCK', 1008);
define('ERROR_EXPIRED_TOKEN', 1009);
define('ERROR_RATE_LIMIT_EXCEEDED', 1010);

// ==============================================
// SUCCESS CODES
// ==============================================
define('SUCCESS_CREATED', 2001);
define('SUCCESS_UPDATED', 2002);
define('SUCCESS_DELETED', 2003);
define('SUCCESS_PROCESSED', 2004);
define('SUCCESS_SENT', 2005);

// ==============================================
// CATEGORY TYPES
// ==============================================
define('CATEGORY_TYPE_PRODUCT', 'product');
define('CATEGORY_TYPE_BLOG', 'blog');
define('CATEGORY_TYPE_PAGE', 'page');

// ==============================================
// PRODUCT TYPES
// ==============================================
define('PRODUCT_TYPE_SIMPLE', 'simple');
define('PRODUCT_TYPE_VARIABLE', 'variable');
define('PRODUCT_TYPE_GROUPED', 'grouped');
define('PRODUCT_TYPE_DIGITAL', 'digital');

// ==============================================
// ATTRIBUTE TYPES
// ==============================================
define('ATTRIBUTE_TYPE_TEXT', 'text');
define('ATTRIBUTE_TYPE_NUMBER', 'number');
define('ATTRIBUTE_TYPE_SELECT', 'select');
define('ATTRIBUTE_TYPE_MULTISELECT', 'multiselect');
define('ATTRIBUTE_TYPE_COLOR', 'color');
define('ATTRIBUTE_TYPE_IMAGE', 'image');

// ==============================================
// SHIPPING METHODS
// ==============================================
define('SHIPPING_METHOD_STANDARD', 'standard');
define('SHIPPING_METHOD_EXPRESS', 'express');
define('SHIPPING_METHOD_SAME_DAY', 'same_day');
define('SHIPPING_METHOD_PICKUP', 'pickup');

// ==============================================
// SHIPPING STATUS
// ==============================================
define('SHIPPING_STATUS_PENDING', 'pending');
define('SHIPPING_STATUS_PICKED_UP', 'picked_up');
define('SHIPPING_STATUS_IN_TRANSIT', 'in_transit');
define('SHIPPING_STATUS_OUT_FOR_DELIVERY', 'out_for_delivery');
define('SHIPPING_STATUS_DELIVERED', 'delivered');
define('SHIPPING_STATUS_FAILED', 'failed');
define('SHIPPING_STATUS_RETURNED', 'returned');

// ==============================================
// RETURN/REFUND STATUS
// ==============================================
define('RETURN_STATUS_REQUESTED', 'requested');
define('RETURN_STATUS_APPROVED', 'approved');
define('RETURN_STATUS_REJECTED', 'rejected');
define('RETURN_STATUS_IN_PROGRESS', 'in_progress');
define('RETURN_STATUS_COMPLETED', 'completed');

define('REFUND_STATUS_PENDING', 'pending');
define('REFUND_STATUS_PROCESSING', 'processing');
define('REFUND_STATUS_COMPLETED', 'completed');
define('REFUND_STATUS_FAILED', 'failed');

// ==============================================
// REPORT TYPES
// ==============================================
define('REPORT_TYPE_SALES', 'sales');
define('REPORT_TYPE_PRODUCTS', 'products');
define('REPORT_TYPE_CUSTOMERS', 'customers');
define('REPORT_TYPE_INVENTORY', 'inventory');
define('REPORT_TYPE_TRAFFIC', 'traffic');
define('REPORT_TYPE_FINANCIAL', 'financial');

// ==============================================
// ACTIVITY LOG ACTIONS
// ==============================================
define('ACTION_LOGIN', 'login');
define('ACTION_LOGOUT', 'logout');
define('ACTION_REGISTER', 'register');
define('ACTION_PASSWORD_CHANGE', 'password_change');
define('ACTION_PROFILE_UPDATE', 'profile_update');
define('ACTION_ORDER_CREATE', 'order_create');
define('ACTION_ORDER_UPDATE', 'order_update');
define('ACTION_PAYMENT_CREATE', 'payment_create');
define('ACTION_PRODUCT_VIEW', 'product_view');
define('ACTION_CART_ADD', 'cart_add');
define('ACTION_CART_REMOVE', 'cart_remove');
define('ACTION_WISHLIST_ADD', 'wishlist_add');
define('ACTION_WISHLIST_REMOVE', 'wishlist_remove');
define('ACTION_REVIEW_CREATE', 'review_create');

// ==============================================
// FILE TYPES
// ==============================================
define('FILE_TYPE_IMAGE', 'image');
define('FILE_TYPE_DOCUMENT', 'document');
define('FILE_TYPE_VIDEO', 'video');
define('FILE_TYPE_AUDIO', 'audio');
define('FILE_TYPE_ARCHIVE', 'archive');

// ==============================================
// IMAGE PROCESSING
// ==============================================
define('IMAGE_RESIZE_CROP', 'crop');
define('IMAGE_RESIZE_FIT', 'fit');
define('IMAGE_RESIZE_STRETCH', 'stretch');
define('WATERMARK_ENABLED', false);
define('WATERMARK_POSITION', 'bottom-right');
define('WATERMARK_OPACITY', 50);

// ==============================================
// SEO CONFIGURATION
// ==============================================
define('SEO_TITLE_SEPARATOR', ' | ');
define('SEO_META_DESCRIPTION_LENGTH', 160);
define('SEO_META_KEYWORDS_LENGTH', 255);
define('ENABLE_SCHEMA_MARKUP', true);
define('ENABLE_OPEN_GRAPH', true);
define('ENABLE_TWITTER_CARDS', true);

// ==============================================
// ANALYTICS CONFIGURATION
// ==============================================
define('GOOGLE_ANALYTICS_ID', 'GA_MEASUREMENT_ID');
define('FACEBOOK_PIXEL_ID', 'FACEBOOK_PIXEL_ID');
define('ENABLE_INTERNAL_ANALYTICS', true);
define('TRACK_USER_BEHAVIOR', true);

// ==============================================
// SPAM PROTECTION
// ==============================================
define('ENABLE_RECAPTCHA', false);
define('RECAPTCHA_SITE_KEY', 'your_recaptcha_site_key');
define('RECAPTCHA_SECRET_KEY', 'your_recaptcha_secret_key');
define('HONEYPOT_ENABLED', true);
define('COMMENT_MODERATION', true);

// ==============================================
// MEDIA CONFIGURATION
// ==============================================
define('MEDIA_LIBRARY_ENABLED', true);
define('MAX_MEDIA_FILE_SIZE', 10485760); // 10MB
define('ALLOWED_MEDIA_TYPES', [
    'image/jpeg', 'image/png', 'image/gif', 'image/webp',
    'video/mp4', 'video/webm',
    'audio/mp3', 'audio/wav',
    'application/pdf'
]);

// ==============================================
// MULTI-LANGUAGE SUPPORT
// ==============================================
define('DEFAULT_LANGUAGE', 'vi');
define('AVAILABLE_LANGUAGES', ['vi', 'en']);
define('ENABLE_RTL', false);
define('LANGUAGE_DETECTION_METHOD', 'browser'); // browser, url, session

// ==============================================
// PERFORMANCE CONFIGURATION
// ==============================================
define('ENABLE_GZIP_COMPRESSION', true);
define('ENABLE_BROWSER_CACHING', true);
define('MINIFY_HTML', true);
define('MINIFY_CSS', true);
define('MINIFY_JS', true);
define('LAZY_LOAD_IMAGES', true);

// ==============================================
// THIRD-PARTY INTEGRATIONS
// ==============================================
define('ENABLE_GOOGLE_MAPS', true);
define('GOOGLE_MAPS_API_KEY', 'your_google_maps_api_key');
define('ENABLE_SOCIAL_LOGIN', true);
define('FACEBOOK_APP_ID', 'your_facebook_app_id');
define('GOOGLE_CLIENT_ID', 'your_google_client_id');

// ==============================================
// MOBILE APP CONFIGURATION
// ==============================================
define('MOBILE_APP_ENABLED', false);
define('MOBILE_API_VERSION', 'v1');
define('PUSH_NOTIFICATION_KEY', 'your_fcm_server_key');
define('APP_STORE_URL', 'https://apps.apple.com/app/tktshop');
define('PLAY_STORE_URL', 'https://play.google.com/store/apps/details?id=com.tktshop');

// ==============================================
// DEVELOPMENT CONFIGURATION
// ==============================================
define('DEBUG_MODE', false);
define('SHOW_ERRORS', false);
define('LOG_QUERIES', false);
define('PROFILING_ENABLED', false);
define('DEVELOPMENT_MODE', false);

// ==============================================
// REGEX PATTERNS
// ==============================================
define('REGEX_EMAIL', '/^[^\s@]+@[^\s@]+\.[^\s@]+$/');
define('REGEX_PHONE_VN', '/^(\+84|84|0)(3[2-9]|5[689]|7[06-9]|8[1-689]|9[0-46-9])[0-9]{7}$/');
define('REGEX_USERNAME', '/^[a-zA-Z0-9_]{3,20}$/');
define('REGEX_SLUG', '/^[a-z0-9-]+$/');
define('REGEX_COLOR_HEX', '/^#([A-Fa-f0-9]{6}|[A-Fa-f0-9]{3})$/');

// ==============================================
// HTTP STATUS CODES
// ==============================================
define('HTTP_OK', 200);
define('HTTP_CREATED', 201);
define('HTTP_ACCEPTED', 202);
define('HTTP_NO_CONTENT', 204);
define('HTTP_BAD_REQUEST', 400);
define('HTTP_UNAUTHORIZED', 401);
define('HTTP_FORBIDDEN', 403);
define('HTTP_NOT_FOUND', 404);
define('HTTP_METHOD_NOT_ALLOWED', 405);
define('HTTP_CONFLICT', 409);
define('HTTP_UNPROCESSABLE_ENTITY', 422);
define('HTTP_TOO_MANY_REQUESTS', 429);
define('HTTP_INTERNAL_SERVER_ERROR', 500);
define('HTTP_SERVICE_UNAVAILABLE', 503);

// ==============================================
// SYSTEM REQUIREMENTS
// ==============================================
define('MIN_PHP_VERSION', '7.4.0');
define('MIN_MYSQL_VERSION', '5.7.0');
define('REQUIRED_PHP_EXTENSIONS', [
    'mysqli', 'gd', 'curl', 'json', 'mbstring', 'openssl', 'zip'
]);

// ==============================================
// DEFAULT VALUES
// ==============================================
define('DEFAULT_PRODUCT_STATUS', PRODUCT_STATUS_DRAFT);
define('DEFAULT_USER_ROLE', USER_ROLE_CUSTOMER);
define('DEFAULT_ORDER_STATUS', ORDER_STATUS_PENDING);
define('DEFAULT_PAYMENT_STATUS', PAYMENT_STATUS_PENDING);
define('DEFAULT_SHIPPING_METHOD', SHIPPING_METHOD_STANDARD);

// ==============================================
// VALIDATION RULES
// ==============================================
define('USERNAME_MIN_LENGTH', 3);
define('USERNAME_MAX_LENGTH', 20);
define('PRODUCT_NAME_MAX_LENGTH', 255);
define('CATEGORY_NAME_MAX_LENGTH', 100);
define('REVIEW_MAX_LENGTH', 1000);
define('ADDRESS_MAX_LENGTH', 255);
define('PHONE_MAX_LENGTH', 20);

// ==============================================
// TIME CONSTANTS (in seconds)
// ==============================================
define('ONE_MINUTE', 60);
define('ONE_HOUR', 3600);
define('ONE_DAY', 86400);
define('ONE_WEEK', 604800);
define('ONE_MONTH', 2592000);
define('ONE_YEAR', 31536000);

// ==============================================
// WIDGET CONFIGURATION
// ==============================================
define('ENABLE_RECENT_PRODUCTS', true);
define('ENABLE_FEATURED_PRODUCTS', true);
define('ENABLE_BESTSELLERS', true);
define('ENABLE_TESTIMONIALS', true);
define('ENABLE_INSTAGRAM_FEED', true);
define('RECENT_PRODUCTS_LIMIT', 8);
define('FEATURED_PRODUCTS_LIMIT', 8);
define('BESTSELLERS_LIMIT', 8);

// ==============================================
// CHRISTMAS/HOLIDAY FEATURES
// ==============================================
define('ENABLE_SEASONAL_THEMES', true);
define('ENABLE_GIFT_WRAPPING', true);
define('GIFT_WRAPPING_FEE', 15000);
define('ENABLE_GIFT_MESSAGES', true);
define('GIFT_MESSAGE_MAX_LENGTH', 500);

// ==============================================
// ADVANCED FEATURES
// ==============================================
define('ENABLE_ADVANCED_SEARCH', true);
define('ENABLE_PRODUCT_BUNDLES', true);
define('ENABLE_CROSS_SELLING', true);
define('ENABLE_UP_SELLING', true);
define('ENABLE_RECENTLY_VIEWED', true);
define('RECENTLY_VIEWED_LIMIT', 10);

// ==============================================
// ADMIN DASHBOARD WIDGETS
// ==============================================
define('DASHBOARD_SALES_PERIOD', 30); // days
define('DASHBOARD_TOP_PRODUCTS_LIMIT', 10);
define('DASHBOARD_RECENT_ORDERS_LIMIT', 10);
define('DASHBOARD_LOW_STOCK_LIMIT', 20);

// ==============================================
// SYSTEM STATUS
// ==============================================
define('SYSTEM_STATUS_ONLINE', 'online');
define('SYSTEM_STATUS_MAINTENANCE', 'maintenance');
define('SYSTEM_STATUS_OFFLINE', 'offline');

// Set timezone
date_default_timezone_set(DEFAULT_TIMEZONE);

// Define current timestamp for consistency
define('CURRENT_TIMESTAMP', time());
define('CURRENT_DATE', date('Y-m-d'));
define('CURRENT_DATETIME', date('Y-m-d H:i:s'));

?>