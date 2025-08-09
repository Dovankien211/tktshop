<?php
require_once '../../config/database.php';

echo "<h3>🔍 Cấu trúc bảng san_pham_chinh:</h3>";
try {
    $stmt = $pdo->query("DESCRIBE san_pham_chinh");
    $columns = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    echo "<table border='1'>";
    echo "<tr><th>Cột</th><th>Kiểu</th><th>Null</th><th>Key</th><th>Default</th></tr>";
    foreach($columns as $col) {
        echo "<tr>";
        echo "<td>{$col['Field']}</td>";
        echo "<td>{$col['Type']}</td>";
        echo "<td>{$col['Null']}</td>";
        echo "<td>{$col['Key']}</td>";
        echo "<td>{$col['Default']}</td>";
        echo "</tr>";
    }
    echo "</table>";
} catch(Exception $e) {
    echo "Lỗi: " . $e->getMessage();
}
?>