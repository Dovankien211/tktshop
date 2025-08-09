<?php
session_start();
require_once '../../config/database.php';

$product_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;

if ($product_id <= 0) {
    echo "<script>alert('ID không hợp lệ!'); window.location.href='index.php';</script>";
    exit();
}

// Lấy thông tin sản phẩm
try {
    $stmt = $pdo->prepare("SELECT * FROM san_pham_chinh WHERE id = ?");
    $stmt->execute([$product_id]);
    $product = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$product) {
        echo "<script>alert('Không tìm thấy sản phẩm!'); window.location.href='index.php';</script>";
        exit();
    }
} catch (Exception $e) {
    echo "<script>alert('Lỗi: {$e->getMessage()}'); window.location.href='index.php';</script>";
    exit();
}

// Xóa luôn nếu có tham số confirm
if (isset($_GET['confirm']) && $_GET['confirm'] == 'yes') {
    try {
        echo "🔍 DEBUG: Bắt đầu xóa sản phẩm ID: $product_id<br>";
        
        // Bắt đầu transaction
        $pdo->beginTransaction();
        
        // Xóa biến thể trước
        $stmt = $pdo->prepare("DELETE FROM bien_the_san_pham WHERE san_pham_id = ?");
        $stmt->execute([$product_id]);
        $variant_count = $stmt->rowCount();
        echo "🔍 DEBUG: Đã xóa $variant_count biến thể<br>";
        
        // Xóa ảnh nếu có
        if (!empty($product['hinh_anh_chinh'])) {
            $image_path = '../../uploads/products/' . $product['hinh_anh_chinh'];
            if (file_exists($image_path)) {
                unlink($image_path);
                echo "🔍 DEBUG: Đã xóa ảnh: {$product['hinh_anh_chinh']}<br>";
            }
        }
        
        // Xóa sản phẩm chính
        $stmt = $pdo->prepare("DELETE FROM san_pham_chinh WHERE id = ?");
        $result = $stmt->execute([$product_id]);
        
        if ($result) {
            $pdo->commit();
            echo "🔍 DEBUG: Xóa thành công!<br>";
            echo "<script>
                alert('✅ Đã xóa sản phẩm \"{$product['ten_san_pham']}\" thành công!');
                window.location.href = 'index.php';
            </script>";
        } else {
            throw new Exception("Lỗi khi xóa sản phẩm từ database");
        }
        
    } catch (Exception $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollback();
        }
        echo "<script>alert('❌ Lỗi: {$e->getMessage()}'); window.location.href='index.php';</script>";
    }
    exit();
}
?>

<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Xóa sản phẩm - TKT Shop</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
</head>
<body>
    <div class="container mt-5">
        <div class="row justify-content-center">
            <div class="col-md-6">
                <div class="card">
                    <div class="card-header bg-danger text-white">
                        <h5>🗑️ Xác nhận xóa sản phẩm</h5>
                    </div>
                    <div class="card-body">
                        <div class="text-center mb-3">
                            <?php if ($product['hinh_anh_chinh']): ?>
                                <img src="/tktshop/uploads/products/<?= $product['hinh_anh_chinh'] ?>" 
                                     alt="" class="img-thumbnail" style="max-width: 150px;">
                            <?php endif; ?>
                        </div>
                        
                        <h6 class="text-center"><?= htmlspecialchars($product['ten_san_pham']) ?></h6>
                        <p class="text-center text-muted">
                            <?= htmlspecialchars($product['thuong_hieu']) ?> - 
                            <?= number_format($product['gia_goc']) ?>₫
                        </p>
                        
                        <div class="alert alert-warning">
                            <strong>⚠️ Cảnh báo:</strong> Bạn có chắc chắn muốn xóa sản phẩm này không? 
                            Hành động này không thể hoàn tác!
                        </div>
                        
                        <div class="d-grid gap-2">
                            <a href="quick_delete.php?id=<?= $product_id ?>&confirm=yes" 
                               class="btn btn-danger"
                               onclick="return confirm('🚨 CHẮC CHẮN XÓA KHÔNG?')">
                                <i class="fas fa-trash"></i> XÓA VĨNH VIỄN
                            </a>
                            <a href="index.php" class="btn btn-secondary">
                                <i class="fas fa-times"></i> Hủy bỏ
                            </a>
                        </div>
                    </div>
                </div>
                
                <div class="mt-3">
                    <div class="card bg-light">
                        <div class="card-body">
                            <small>
                                <strong>Debug:</strong><br>
                                ID: <?= $product_id ?><br>
                                File: <?= __FILE__ ?><br>
                                Product: <?= $product ? 'Found' : 'Not found' ?>
                            </small>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</body>
</html>