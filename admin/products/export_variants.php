<?php
require_once '../../config/database.php';
require_once '../../config/config.php';

requireLogin();

if (isset($_GET['export'])) {
    $product_id = (int)$_GET['product_id'];
    
    $variants = $pdo->prepare("
        SELECT pv.*, p.name as product_name, p.sku as product_sku,
               s.name as size_name, c.name as color_name, c.color_code
        FROM product_variants pv
        JOIN products p ON pv.product_id = p.id
        LEFT JOIN sizes s ON pv.size_id = s.id
        LEFT JOIN colors c ON pv.color_id = c.id
        WHERE pv.product_id = ?
        ORDER BY pv.id ASC
    ");
    $variants->execute([$product_id]);
    $data = $variants->fetchAll();
    
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename=variants_product_'.$product_id.'_'.date('Y-m-d').'.csv');
    
    $output = fopen('php://output', 'w');
    
    // CSV Header
    fputcsv($output, [
        'ID', 'Product Name', 'Product SKU', 'Variant SKU', 
        'Size', 'Color', 'Color Code', 'Price Adjustment', 
        'Stock Quantity', 'Sold Quantity', 'Status', 'Variant Image'
    ]);
    
    // CSV Data
    foreach ($data as $row) {
        fputcsv($output, [
            $row['id'], $row['product_name'], $row['product_sku'], $row['sku'],
            $row['size_name'], $row['color_name'], $row['color_code'], $row['price_adjustment'],
            $row['stock_quantity'], $row['sold_quantity'], $row['status'], $row['variant_image']
        ]);
    }
    
    fclose($output);
    exit();
}
?>

<div class="card">
    <div class="card-header">
        <h5><i class="fas fa-download me-2"></i>Xuất dữ liệu biến thể</h5>
    </div>
    <div class="card-body">
        <p>Xuất tất cả biến thể của sản phẩm ra file CSV</p>
        <a href="?export=1&product_id=<?= $product_id ?>" class="btn btn-success">
            <i class="fas fa-file-csv"></i> Tải xuống CSV
        </a>
    </div>
</div>
