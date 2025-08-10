<?php
/**
 * config/database.php - Fixed Version with Database Class
 * Kết nối database MySQL với PDO + Database Singleton Class
 */

// Cấu hình database
$host = 'localhost';
$dbname = 'tktshop';
$username = 'root';
$password = '';

// Tạo kết nối PDO cơ bản (backward compatibility)
try {
    $pdo = new PDO(
        "mysql:host=$host;dbname=$dbname;charset=utf8mb4", 
        $username, 
        $password,
        [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES => false,
        ]
    );
} catch (PDOException $e) {
    die("Lỗi kết nối database: " . $e->getMessage());
}

/**
 * Database Singleton Class
 */
class Database {
    private static $instance = null;
    private $connection;
    
    private $host = 'localhost';
    private $dbname = 'tktshop';
    private $username = 'root';
    private $password = '';
    
    private function __construct() {
        try {
            $this->connection = new PDO(
                "mysql:host={$this->host};dbname={$this->dbname};charset=utf8mb4",
                $this->username,
                $this->password,
                [
                    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                    PDO::ATTR_EMULATE_PREPARES => false,
                ]
            );
        } catch (PDOException $e) {
            throw new Exception("Database connection failed: " . $e->getMessage());
        }
    }
    
    public static function getInstance() {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }
    
    public function getConnection() {
        return $this->connection;
    }
    
    // Prevent cloning
    private function __clone() {}
    
    // Prevent unserializing
    public function __wakeup() {
        throw new Exception("Cannot unserialize singleton");
    }
}

// Auto-create tables if not exist
try {
    // Tạo bảng categories nếu chưa có
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS categories (
            id INT AUTO_INCREMENT PRIMARY KEY,
            name VARCHAR(255) NOT NULL,
            slug VARCHAR(255),
            description TEXT,
            parent_id INT DEFAULT 0,
            sort_order INT DEFAULT 0,
            status VARCHAR(20) DEFAULT 'active',
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
    ");
    
    // Tạo bảng products nếu chưa có
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS products (
            id INT AUTO_INCREMENT PRIMARY KEY,
            name VARCHAR(255) NOT NULL,
            slug VARCHAR(255),
            description TEXT,
            short_description TEXT,
            price DECIMAL(10,2) NOT NULL,
            sale_price DECIMAL(10,2) DEFAULT NULL,
            sku VARCHAR(100) UNIQUE,
            category_id INT,
            brand VARCHAR(100),
            weight DECIMAL(8,2) DEFAULT 0,
            dimensions VARCHAR(100),
            stock_quantity INT DEFAULT 0,
            min_quantity INT DEFAULT 1,
            status VARCHAR(20) DEFAULT 'active',
            is_featured BOOLEAN DEFAULT FALSE,
            main_image VARCHAR(255),
            gallery_images TEXT,
            meta_title VARCHAR(255),
            meta_description TEXT,
            tags TEXT,
            view_count INT DEFAULT 0,
            sold_count INT DEFAULT 0,
            rating_average DECIMAL(3,2) DEFAULT 0,
            rating_count INT DEFAULT 0,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            INDEX idx_status (status),
            INDEX idx_category (category_id),
            INDEX idx_sku (sku),
            INDEX idx_featured (is_featured),
            INDEX idx_price (price)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
    ");
    
    // Kiểm tra và thêm dữ liệu mẫu cho categories
    $count = $pdo->query("SELECT COUNT(*) FROM categories")->fetchColumn();
    if ($count == 0) {
        $sample_categories = [
            ['Giày thể thao', 'giay-the-thao', 'Giày dành cho hoạt động thể thao', 1],
            ['Giày cao gót', 'giay-cao-got', 'Giày cao gót thời trang cho nữ', 2],
            ['Giày da', 'giay-da', 'Giày da cao cấp, sang trọng', 3],
            ['Giày lười', 'giay-luoi', 'Giày lười tiện lợi, thoải mái', 4],
            ['Dép & Sandal', 'dep-sandal', 'Dép và sandal các loại', 5],
            ['Nike', 'nike', 'Sản phẩm Nike chính hãng', 6],
            ['Adidas', 'adidas', 'Sản phẩm Adidas chính hãng', 7],
            ['Giày nam', 'giay-nam', 'Giày dành cho nam giới', 8],
            ['Giày nữ', 'giay-nu', 'Giày dành cho nữ giới', 9]
        ];
        
        $stmt = $pdo->prepare("INSERT INTO categories (name, slug, description, sort_order, status) VALUES (?, ?, ?, ?, 'active')");
        foreach ($sample_categories as $cat) {
            $stmt->execute($cat);
        }
    }
    
} catch (Exception $e) {
    error_log("Auto-create database tables error: " . $e->getMessage());
}
?>