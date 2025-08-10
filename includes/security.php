<?php
/**
 * TKT Shop - Security Functions
 * File: includes/security.php
 */

if (!defined('ABSPATH')) {
    define('ABSPATH', dirname(__FILE__) . '/');
}

/**
 * XSS Protection - Clean input data
 */
function cleanXSS($data) {
    // Fix nested attack
    $data = preg_replace('/((\%3C)|<)((\%2F)|\/)*[a-z0-9\%]+((\%3E)|>)/ix', '', $data);
    $data = preg_replace('/((\%3C)|<)((\%69)|i|(\%49))((\%6D)|m|(\%4D))((\%67)|g|(\%47))[^\n]+((\%3E)|>)/I', '', $data);
    
    // Remove any attribute starting with "on" or xmlns
    $data = preg_replace('#(<[^>]+?[\x00-\x20"\'])(?:on|xmlns)[^>]*+>#iu', '$1>', $data);
    
    // Remove javascript: and vbscript: protocols
    $data = preg_replace('#([a-z]*)[\x00-\x20]*=[\x00-\x20]*([`\'"]*)[\x00-\x20]*j[\x00-\x20]*a[\x00-\x20]*v[\x00-\x20]*a[\x00-\x20]*s[\x00-\x20]*c[\x00-\x20]*r[\x00-\x20]*i[\x00-\x20]*p[\x00-\x20]*t[\x00-\x20]*:#iu', '$1=$2nojavascript...', $data);
    $data = preg_replace('#([a-z]*)[\x00-\x20]*=([\'"]*)[\x00-\x20]*v[\x00-\x20]*b[\x00-\x20]*s[\x00-\x20]*c[\x00-\x20]*r[\x00-\x20]*i[\x00-\x20]*p[\x00-\x20]*t[\x00-\x20]*:#iu', '$1=$2novbscript...', $data);
    $data = preg_replace('#([a-z]*)[\x00-\x20]*=([\'"]*)[\x00-\x20]*-moz-binding[\x00-\x20]*:#u', '$1=$2nomozbinding...', $data);
    
    // Only works in IE: <span style="width: expression(alert('Ping!'));"></span>
    $data = preg_replace('#(<[^>]+?)style[\x00-\x20]*=[\x00-\x20]*[`\'"]*.*?expression[\x00-\x20]*\([^>]*+>#i', '$1>', $data);
    $data = preg_replace('#(<[^>]+?)style[\x00-\x20]*=[\x00-\x20]*[`\'"]*.*?behaviour[\x00-\x20]*\([^>]*+>#i', '$1>', $data);
    $data = preg_replace('#(<[^>]+?)style[\x00-\x20]*=[\x00-\x20]*[`\'"]*.*?s[\x00-\x20]*c[\x00-\x20]*r[\x00-\x20]*i[\x00-\x20]*p[\x00-\x20]*t[\x00-\x20]*:*[^>]*+>#iu', '$1>', $data);
    
    // Remove namespaced elements (we do not need them)
    $data = preg_replace('#</*\w+:\w[^>]*+>#i', '', $data);
    
    do {
        // Remove really unwanted tags
        $old_data = $data;
        $data = preg_replace('#</*(?:applet|b(?:ase|gsound|link)|embed|frame(?:set)?|i(?:frame|layer)|l(?:ayer|ink)|meta|object|s(?:cript|tyle)|title|xml)[^>]*+>#i', '', $data);
    } while ($old_data !== $data);
    
    return $data;
}

/**
 * SQL Injection Protection
 */
function prepareSQLValue($value, $type = 'string') {
    global $conn;
    
    switch ($type) {
        case 'int':
            return (int) $value;
        case 'float':
            return (float) $value;
        case 'bool':
            return (bool) $value;
        default:
            return $conn->real_escape_string($value);
    }
}

/**
 * CSRF Token Protection
 */
class CSRFProtection {
    private static $token_name = 'csrf_token';
    
    public static function generateToken() {
        if (session_status() == PHP_SESSION_NONE) {
            session_start();
        }
        
        $token = bin2hex(random_bytes(32));
        $_SESSION[self::$token_name] = $token;
        return $token;
    }
    
    public static function validateToken($token) {
        if (session_status() == PHP_SESSION_NONE) {
            session_start();
        }
        
        if (!isset($_SESSION[self::$token_name])) {
            return false;
        }
        
        return hash_equals($_SESSION[self::$token_name], $token);
    }
    
    public static function getTokenInput() {
        $token = self::generateToken();
        return '<input type="hidden" name="' . self::$token_name . '" value="' . $token . '">';
    }
    
    public static function getTokenMeta() {
        $token = self::generateToken();
        return '<meta name="' . self::$token_name . '" content="' . $token . '">';
    }
}

/**
 * Rate Limiting Class
 */
class RateLimiter {
    private $redis;
    private $prefix = 'rate_limit:';
    
    public function __construct() {
        // If Redis is available, use it. Otherwise, use session
        if (class_exists('Redis')) {
            try {
                $this->redis = new Redis();
                $this->redis->connect('127.0.0.1', 6379);
            } catch (Exception $e) {
                $this->redis = null;
            }
        }
    }
    
    public function isAllowed($identifier, $max_attempts = 10, $time_window = 3600) {
        if ($this->redis) {
            return $this->redisRateLimit($identifier, $max_attempts, $time_window);
        } else {
            return $this->sessionRateLimit($identifier, $max_attempts, $time_window);
        }
    }
    
    private function redisRateLimit($identifier, $max_attempts, $time_window) {
        $key = $this->prefix . $identifier;
        $current = $this->redis->incr($key);
        
        if ($current === 1) {
            $this->redis->expire($key, $time_window);
        }
        
        return $current <= $max_attempts;
    }
    
    private function sessionRateLimit($identifier, $max_attempts, $time_window) {
        if (session_status() == PHP_SESSION_NONE) {
            session_start();
        }
        
        $key = $this->prefix . $identifier;
        $now = time();
        
        if (!isset($_SESSION[$key])) {
            $_SESSION[$key] = [];
        }
        
        // Clean old attempts
        $_SESSION[$key] = array_filter($_SESSION[$key], function($timestamp) use ($now, $time_window) {
            return ($now - $timestamp) <= $time_window;
        });
        
        // Add current attempt
        $_SESSION[$key][] = $now;
        
        return count($_SESSION[$key]) <= $max_attempts;
    }
}

/**
 * Input Validation Class
 */
class InputValidator {
    
    public static function validateEmail($email) {
        return filter_var($email, FILTER_VALIDATE_EMAIL) !== false;
    }
    
    public static function validatePhone($phone) {
        // Vietnamese phone number validation
        $phone = preg_replace('/[^0-9+]/', '', $phone);
        $patterns = [
            '/^(\+84|84|0)(3[2-9]|5[689]|7[06-9]|8[1-689]|9[0-46-9])[0-9]{7}$/',
            '/^(\+84|84|0)(1[2689])[0-9]{8}$/' // Old format
        ];
        
        foreach ($patterns as $pattern) {
            if (preg_match($pattern, $phone)) {
                return true;
            }
        }
        
        return false;
    }
    
    public static function validatePassword($password) {
        // At least 8 characters, 1 uppercase, 1 lowercase, 1 number
        return preg_match('/^(?=.*[a-z])(?=.*[A-Z])(?=.*\d)[a-zA-Z\d@$!%*?&]{8,}$/', $password);
    }
    
    public static function validateURL($url) {
        return filter_var($url, FILTER_VALIDATE_URL) !== false;
    }
    
    public static function validateIP($ip) {
        return filter_var($ip, FILTER_VALIDATE_IP) !== false;
    }
    
    public static function validateDate($date, $format = 'Y-m-d') {
        $d = DateTime::createFromFormat($format, $date);
        return $d && $d->format($format) === $date;
    }
    
    public static function validateCreditCard($number) {
        // Luhn algorithm
        $number = preg_replace('/[^0-9]/', '', $number);
        $sum = 0;
        $alternate = false;
        
        for ($i = strlen($number) - 1; $i >= 0; $i--) {
            $digit = (int) $number[$i];
            
            if ($alternate) {
                $digit *= 2;
                if ($digit > 9) {
                    $digit = ($digit % 10) + 1;
                }
            }
            
            $sum += $digit;
            $alternate = !$alternate;
        }
        
        return ($sum % 10) === 0;
    }
    
    public static function sanitizeFileName($filename) {
        // Remove path info
        $filename = basename($filename);
        
        // Replace dangerous characters
        $filename = preg_replace('/[^a-zA-Z0-9._-]/', '_', $filename);
        
        // Remove multiple underscores
        $filename = preg_replace('/_+/', '_', $filename);
        
        // Trim dots and underscores
        $filename = trim($filename, '._');
        
        // Ensure not empty
        if (empty($filename)) {
            $filename = 'file_' . time();
        }
        
        return $filename;
    }
}

/**
 * Password Security Class
 */
class PasswordSecurity {
    
    public static function hash($password) {
        return password_hash($password, PASSWORD_ARGON2ID, [
            'memory_cost' => 65536, // 64 MB
            'time_cost' => 4,      // 4 iterations
            'threads' => 3,        // 3 threads
        ]);
    }
    
    public static function verify($password, $hash) {
        return password_verify($password, $hash);
    }
    
    public static function needsRehash($hash) {
        return password_needs_rehash($hash, PASSWORD_ARGON2ID, [
            'memory_cost' => 65536,
            'time_cost' => 4,
            'threads' => 3,
        ]);
    }
    
    public static function generateRandomPassword($length = 12) {
        $characters = 'abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789!@#$%^&*';
        $password = '';
        
        for ($i = 0; $i < $length; $i++) {
            $password .= $characters[random_int(0, strlen($characters) - 1)];
        }
        
        return $password;
    }
    
    public static function getPasswordStrength($password) {
        $score = 0;
        $feedback = [];
        
        // Length check
        if (strlen($password) >= 8) {
            $score += 1;
        } else {
            $feedback[] = 'Password should be at least 8 characters long';
        }
        
        if (strlen($password) >= 12) {
            $score += 1;
        }
        
        // Character variety checks
        if (preg_match('/[a-z]/', $password)) {
            $score += 1;
        } else {
            $feedback[] = 'Add lowercase letters';
        }
        
        if (preg_match('/[A-Z]/', $password)) {
            $score += 1;
        } else {
            $feedback[] = 'Add uppercase letters';
        }
        
        if (preg_match('/[0-9]/', $password)) {
            $score += 1;
        } else {
            $feedback[] = 'Add numbers';
        }
        
        if (preg_match('/[^a-zA-Z0-9]/', $password)) {
            $score += 1;
        } else {
            $feedback[] = 'Add special characters';
        }
        
        // Common patterns check
        if (!preg_match('/(.)\1{2,}/', $password)) {
            $score += 1;
        } else {
            $feedback[] = 'Avoid repeated characters';
        }
        
        $strength_levels = [
            0 => 'Very Weak',
            1 => 'Weak', 
            2 => 'Weak',
            3 => 'Fair',
            4 => 'Good',
            5 => 'Strong',
            6 => 'Very Strong',
            7 => 'Excellent'
        ];
        
        return [
            'score' => $score,
            'strength' => $strength_levels[min($score, 7)],
            'feedback' => $feedback
        ];
    }
}

/**
 * Session Security Class
 */
class SessionSecurity {
    
    public static function start() {
        if (session_status() == PHP_SESSION_NONE) {
            // Configure session security
            ini_set('session.cookie_httponly', 1);
            ini_set('session.cookie_secure', isset($_SERVER['HTTPS']));
            ini_set('session.use_only_cookies', 1);
            ini_set('session.cookie_samesite', 'Strict');
            
            session_start();
            
            // Regenerate session ID periodically
            if (!isset($_SESSION['last_regeneration'])) {
                self::regenerateId();
            } elseif (time() - $_SESSION['last_regeneration'] > 300) { // 5 minutes
                self::regenerateId();
            }
        }
    }
    
    public static function regenerateId() {
        session_regenerate_id(true);
        $_SESSION['last_regeneration'] = time();
    }
    
    public static function destroy() {
        if (session_status() == PHP_SESSION_ACTIVE) {
            session_unset();
            session_destroy();
            
            // Delete session cookie
            if (ini_get("session.use_cookies")) {
                $params = session_get_cookie_params();
                setcookie(session_name(), '', time() - 42000,
                    $params["path"], $params["domain"],
                    $params["secure"], $params["httponly"]
                );
            }
        }
    }
    
    public static function validateFingerprint() {
        $fingerprint = self::generateFingerprint();
        
        if (!isset($_SESSION['fingerprint'])) {
            $_SESSION['fingerprint'] = $fingerprint;
            return true;
        }
        
        return $_SESSION['fingerprint'] === $fingerprint;
    }
    
    private static function generateFingerprint() {
        $user_agent = $_SERVER['HTTP_USER_AGENT'] ?? '';
        $accept_language = $_SERVER['HTTP_ACCEPT_LANGUAGE'] ?? '';
        $accept_encoding = $_SERVER['HTTP_ACCEPT_ENCODING'] ?? '';
        
        return hash('sha256', $user_agent . $accept_language . $accept_encoding);
    }
}

/**
 * SQL Injection Prevention Class
 */
class SQLSecurity {
    
    public static function escapeString($string, $connection) {
        return mysqli_real_escape_string($connection, $string);
    }
    
    public static function escapeIdentifier($identifier) {
        // Only allow alphanumeric characters and underscores
        return preg_replace('/[^a-zA-Z0-9_]/', '', $identifier);
    }
    
    public static function preparePlaceholders($array) {
        return str_repeat('?,', count($array) - 1) . '?';
    }
    
    public static function validateTableName($table) {
        $allowed_tables = [
            'users', 'products', 'categories', 'orders', 'order_items',
            'product_images', 'product_variants', 'reviews', 'wishlists',
            'cart_items', 'payment_logs', 'activity_logs', 'settings'
        ];
        
        return in_array($table, $allowed_tables);
    }
}

/**
 * File Upload Security Class
 */
class FileUploadSecurity {
    
    private static $allowed_image_types = [
        'image/jpeg', 'image/png', 'image/gif', 'image/webp'
    ];
    
    private static $allowed_document_types = [
        'application/pdf', 'text/plain', 'application/msword',
        'application/vnd.openxmlformats-officedocument.wordprocessingml.document'
    ];
    
    private static $dangerous_extensions = [
        'php', 'php3', 'php4', 'php5', 'phtml', 'phps',
        'asp', 'aspx', 'jsp', 'js', 'exe', 'bat', 'com',
        'scr', 'vbs', 'jar', 'sh', 'py', 'pl', 'cgi'
    ];
    
    public static function validateImage($file) {
        return self::validateFile($file, self::$allowed_image_types, 5242880); // 5MB
    }
    
    public static function validateDocument($file) {
        return self::validateFile($file, self::$allowed_document_types, 10485760); // 10MB
    }
    
    private static function validateFile($file, $allowed_types, $max_size) {
        $errors = [];
        
        // Check for upload errors
        if ($file['error'] !== UPLOAD_ERR_OK) {
            $errors[] = 'File upload error: ' . $file['error'];
            return ['valid' => false, 'errors' => $errors];
        }
        
        // Check file size
        if ($file['size'] > $max_size) {
            $errors[] = 'File too large. Maximum size: ' . formatFileSize($max_size);
        }
        
        // Check MIME type
        $finfo = finfo_open(FILEINFO_MIME_TYPE);
        $actual_type = finfo_file($finfo, $file['tmp_name']);
        finfo_close($finfo);
        
        if (!in_array($actual_type, $allowed_types)) {
            $errors[] = 'Invalid file type: ' . $actual_type;
        }
        
        // Check file extension
        $extension = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
        if (in_array($extension, self::$dangerous_extensions)) {
            $errors[] = 'Dangerous file extension: ' . $extension;
        }
        
        // Check for embedded PHP code
        $content = file_get_contents($file['tmp_name']);
        if (strpos($content, '<?php') !== false || strpos($content, '<?=') !== false) {
            $errors[] = 'File contains executable code';
        }
        
        // For images, additional validation
        if (strpos($actual_type, 'image/') === 0) {
            $image_info = getimagesize($file['tmp_name']);
            if (!$image_info) {
                $errors[] = 'Invalid image file';
            }
        }
        
        return [
            'valid' => empty($errors),
            'errors' => $errors,
            'type' => $actual_type,
            'size' => $file['size']
        ];
    }
    
    public static function sanitizeFileName($filename) {
        // Remove path info
        $filename = basename($filename);
        
        // Convert to ASCII
        $filename = iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', $filename);
        
        // Replace unsafe characters
        $filename = preg_replace('/[^a-zA-Z0-9._-]/', '_', $filename);
        
        // Remove multiple dots and underscores
        $filename = preg_replace('/[._]{2,}/', '_', $filename);
        
        // Trim dots and underscores
        $filename = trim($filename, '._');
        
        // Ensure not empty
        if (empty($filename)) {
            $filename = 'file_' . time();
        }
        
        // Limit length
        if (strlen($filename) > 100) {
            $extension = pathinfo($filename, PATHINFO_EXTENSION);
            $name = pathinfo($filename, PATHINFO_FILENAME);
            $filename = substr($name, 0, 100 - strlen($extension) - 1) . '.' . $extension;
        }
        
        return $filename;
    }
}

/**
 * Content Security Policy Helper
 */
class CSPHelper {
    
    public static function getCSPHeader() {
        $directives = [
            "default-src 'self'",
            "script-src 'self' 'unsafe-inline' 'unsafe-eval' https://cdn.jsdelivr.net https://cdnjs.cloudflare.com",
            "style-src 'self' 'unsafe-inline' https://cdn.jsdelivr.net https://cdnjs.cloudflare.com https://fonts.googleapis.com",
            "img-src 'self' data: https:",
            "font-src 'self' https://fonts.gstatic.com https://cdnjs.cloudflare.com",
            "connect-src 'self'",
            "frame-src 'none'",
            "object-src 'none'",
            "base-uri 'self'",
            "form-action 'self'"
        ];
        
        return implode('; ', $directives);
    }
    
    public static function setSecurityHeaders() {
        // Content Security Policy
        header('Content-Security-Policy: ' . self::getCSPHeader());
        
        // X-Frame-Options
        header('X-Frame-Options: DENY');
        
        // X-Content-Type-Options
        header('X-Content-Type-Options: nosniff');
        
        // X-XSS-Protection
        header('X-XSS-Protection: 1; mode=block');
        
        // Referrer Policy
        header('Referrer-Policy: strict-origin-when-cross-origin');
        
        // Strict Transport Security (if HTTPS)
        if (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on') {
            header('Strict-Transport-Security: max-age=31536000; includeSubDomains; preload');
        }
        
        // Feature Policy / Permissions Policy
        header('Permissions-Policy: geolocation=(), microphone=(), camera=()');
    }
}

/**
 * Utility function to format file size
 */
function formatFileSize($bytes) {
    $units = ['B', 'KB', 'MB', 'GB'];
    $bytes = max($bytes, 0);
    $pow = floor(($bytes ? log($bytes) : 0) / log(1024));
    $pow = min($pow, count($units) - 1);
    
    $bytes /= pow(1024, $pow);
    
    return round($bytes, 2) . ' ' . $units[$pow];
}

// Initialize security measures
if (!headers_sent()) {
    CSPHelper::setSecurityHeaders();
}

SessionSecurity::start();

?>