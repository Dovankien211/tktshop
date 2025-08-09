<?php
session_start();
require_once '../../config/database.php';
require_once '../../config/config.php';

// Tạm thời bypass login check để test
if (!isset($_SESSION['user_id'])) {
    $_SESSION['user_id'] = 1;
    $_SESSION['role'] = 'admin';
    $_SESSION['admin_name'] = 'Test Admin';
}

$error = '';
$success = '';

// Lấy ID sản phẩm từ URL
$product_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;

if ($product_id <= 0) {
    header('Location: index.php?error=' . urlencode('ID sản phẩm không hợp lệ!'));
    exit();
}

// Lấy thông tin sản phẩm
$product = null;
try {
    $stmt = $pdo->prepare("SELECT * FROM san_pham_chinh WHERE id = ?");
    $stmt->execute([$product_id]);
    $product = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$product) {
        header('Location: index.php?error=' . urlencode('Không tìm thấy sản phẩm!'));
        exit();
    }
} catch (Exception $e) {
    header('Location: index.php?error=' . urlencode('Lỗi database: ' . $e->getMessage()));
    exit();
}

// Xử lý xóa sản phẩm
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['confirm_delete'])) {
    try {
        echo "🔍 DEBUG: Bắt đầu xóa sản phẩm ID: $product_id<br>";
        
        // Kiểm tra xem sản phẩm có trong đơn hàng nào không
        $stmt = $pdo->prepare("SELECT COUNT(*) FROM chi_tiet_don_hang WHERE san_pham_id = ?");
        $stmt->execute([$product_id]);
        $order_count = $stmt->fetchColumn();
        
        if ($order_count > 0) {
            throw new Exception("Không thể xóa sản phẩm này vì đã có $order_count đơn hàng liên quan!");
        }
        
        echo "🔍 DEBUG: Kiểm tra đơn hàng OK, không có ràng buộc<br>";
        
        // Bắt đầu transaction
        $pdo->beginTransaction();
        
        // Xóa biến thể sản phẩm trước (nếu có)
        $stmt = $pdo->prepare("DELETE FROM bien_the_san_pham WHERE san_pham_id = ?");
        $deleted_variants = $stmt->execute([$product_id]);
        $variant_count = $stmt->rowCount();
        
        echo "🔍 DEBUG: Đã xóa $variant_count biến thể<br>";
        
        // Xóa ảnh sản phẩm từ server
        if (!empty($product['hinh_anh_chinh'])) {
            $image_path = '../../uploads/products/' . $product['hinh_anh_chinh'];
            if (file_exists($image_path)) {
                if (unlink($image_path)) {
                    echo "🔍 DEBUG: Đã xóa ảnh chính: {$product['hinh_anh_chinh']}<br>";
                } else {
                    echo "⚠️ DEBUG: Không thể xóa ảnh: {$product['hinh_anh_chinh']}<br>";
                }
            }
        }
        
        // Xóa sản phẩm chính
        $stmt = $pdo->prepare("DELETE FROM san_pham_chinh WHERE id = ?");
        $result = $stmt->execute([$product_id]);
        
        if ($result) {
            echo "🔍 DEBUG: Đã xóa sản phẩm khỏi database<br>";
            
            // Commit transaction
            $pdo->commit();
            
            // Redirect về trang danh sách với thông báo thành công
            header('Location: http://localhost/tktshop/admin/products/index.php?success=' . urlencode("Đã xóa sản phẩm '{$product['ten_san_pham']}' thành công!"));
            exit();
        } else {
            throw new Exception("Lỗi khi xóa sản phẩm từ database");
        }
        
    } catch (Exception $e) {
        // Rollback transaction nếu có lỗi
        if ($pdo->inTransaction()) {
            $pdo->rollback();
        }
        
        $error = $e->getMessage();
        echo "🔍 DEBUG Error: " . $e->getMessage() . "<br>";
    }
}

// Đếm số biến thể
$variant_count = 0;
try {
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM bien_the_san_pham WHERE san_pham_id = ?");
    $stmt->execute([$product_id]);
    $variant_count = $stmt->fetchColumn();
} catch (Exception $e) {
    // Ignore error
}

// Đếm số đơn hàng liên quan
$order_count = 0;
try {
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM chi_tiet_don_hang WHERE san_pham_id = ?");
    $stmt->execute([$product_id]);
    $order_count = $stmt->fetchColumn();
} catch (Exception $e) {
    // Ignore error
}
?>

<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Xóa sản phẩm - TKT Shop Admin</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <style>
        .product-image {
            max-width: 200px;
            max-height: 200px;
            object-fit: cover;
            border-radius: 8px;
        }
        .danger-zone {
            border: 2px solid #dc3545;
            border-radius: 8px;
            background-color: #f8d7da;
        }
        .warning-box {
            background-color: #fff3cd;
            border: 1px solid #ffeaa7;
            border-radius: 8px;
            padding: 1rem;
        }
    </style>
</head>
<body>
    <div class="container-fluid">
        <div class="row">
            <!-- Sidebar -->
            <div class="col-md-2 p-0">
                <?php include '../layouts/sidebar.php'; ?>
            </div>
            
            <!-- Main content -->
            <div class="col-md-10">
                <div class="p-4">
                    <div class="d-flex justify-content-between align-items-center mb-4">
                        <h1 class="h2">🗑️ Xóa sản phẩm</h1>
                        <a href="index.php" class="btn btn-outline-secondary">
                            <i class="fas fa-arrow-left"></i> Quay lại danh sách
                        </a>
                    </div>

                    <!-- Alert Messages -->
                    <?php if ($error): ?>
                        <div class="alert alert-danger alert-dismissible fade show">
                            <i class="fas fa-exclamation-triangle"></i> <?= htmlspecialchars($error) ?>
                            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                        </div>
                    <?php endif; ?>

                    <!-- Product Info -->
                    <div class="card mb-4">
                        <div class="card-header bg-light">
                            <h5 class="mb-0">
                                <i class="fas fa-info-circle me-2"></i>Thông tin sản phẩm sẽ xóa
                            </h5>
                        </div>
                        <div class="card-body">
                            <div class="row">
                                <div class="col-md-3">
                                    <?php if ($product['hinh_anh_chinh']): ?>
                                        <img src="/tktshop/uploads/products/<?= $product['hinh_anh_chinh'] ?>" 
                                             alt="<?= htmlspecialchars($product['ten_san_pham']) ?>" 
                                             class="product-image img-fluid">
                                    <?php else: ?>
                                        <div class="bg-light d-flex align-items-center justify-content-center product-image">
                                            <i class="fas fa-image fa-3x text-muted"></i>
                                        </div>
                                    <?php endif; ?>
                                </div>
                                <div class="col-md-9">
                                    <h4><?= htmlspecialchars($product['ten_san_pham']) ?></h4>
                                    <p class="mb-2">
                                        <strong>Mã sản phẩm:</strong> <?= htmlspecialchars($product['ma_san_pham']) ?><br>
                                        <strong>Thương hiệu:</strong> <?= htmlspecialchars($product['thuong_hieu']) ?><br>
                                        <strong>Giá gốc:</strong> <?= number_format($product['gia_goc']) ?>₫
                                        <?php if ($product['gia_khuyen_mai']): ?>
                                            | <strong>Giá KM:</strong> <?= number_format($product['gia_khuyen_mai']) ?>₫
                                        <?php endif; ?>
                                    </p>
                                    <p class="text-muted"><?= htmlspecialchars($product['mo_ta_ngan']) ?></p>
                                    
                                    <div class="mt-3">
                                        <span class="badge bg-secondary me-2">
                                            <i class="fas fa-calendar"></i> Tạo: <?= date('d/m/Y H:i', strtotime($product['ngay_tao'])) ?>
                                        </span>
                                        <?php
                                        $status_config = [
                                            'hoat_dong' => ['class' => 'success', 'text' => 'Hoạt động'],
                                            'an' => ['class' => 'secondary', 'text' => 'Ẩn'],
                                            'het_hang' => ['class' => 'warning', 'text' => 'Hết hàng']
                                        ];
                                        $status = $status_config[$product['trang_thai']] ?? ['class' => 'dark', 'text' => $product['trang_thai']];
                                        ?>
                                        <span class="badge bg-<?= $status['class'] ?>">
                                            <?= $status['text'] ?>
                                        </span>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Warning Box -->
                    <?php if ($order_count > 0): ?>
                        <div class="alert alert-warning">
                            <i class="fas fa-exclamation-triangle me-2"></i>
                            <strong>⚠️ Không thể xóa!</strong><br>
                            Sản phẩm này đã có <strong><?= $order_count ?> đơn hàng</strong> liên quan. 
                            Bạn không thể xóa sản phẩm này để đảm bảo tính toàn vẹn dữ liệu.
                            <div class="mt-2">
                                <a href="index.php" class="btn btn-sm btn-outline-primary">
                                    <i class="fas fa-arrow-left"></i> Quay lại danh sách
                                </a>
                            </div>
                        </div>
                    <?php else: ?>
                        <!-- Impact Analysis -->
                        <div class="warning-box mb-4">
                            <h6><i class="fas fa-exclamation-triangle me-2"></i>Phân tích tác động khi xóa:</h6>
                            <ul class="mb-0">
                                <li><strong>Biến thể:</strong> <?= $variant_count ?> biến thể sẽ bị xóa</li>
                                <li><strong>Ảnh:</strong> <?= $product['hinh_anh_chinh'] ? 'Ảnh chính sẽ bị xóa khỏi server' : 'Không có ảnh' ?></li>
                                <li><strong>Đơn hàng:</strong> <?= $order_count ?> đơn hàng liên quan</li>
                                <li><strong>Dữ liệu:</strong> Tất cả dữ liệu sẽ bị xóa vĩnh viễn và không thể khôi phục</li>
            </ul>
        </div>

        <!-- Danger Zone -->
        <div class="danger-zone p-4">
            <h5 class="text-danger mb-3">
                <i class="fas fa-skull-crossbones me-2"></i>Vùng nguy hiểm
            </h5>
            <p class="mb-3">
                <strong>Cảnh báo:</strong> Hành động này không thể hoàn tác! 
                Sản phẩm và tất cả dữ liệu liên quan sẽ bị xóa vĩnh viễn.
            </p>
            
            <form method="POST" id="deleteForm">
                <div class="mb-3">
                    <div class="form-check">
                        <input class="form-check-input" type="checkbox" id="confirmCheck" required>
                        <label class="form-check-label" for="confirmCheck">
                            <strong>Tôi hiểu rủi ro và muốn xóa sản phẩm này vĩnh viễn</strong>
                        </label>
                    </div>
                </div>
                
                <div class="mb-3">
                    <label for="confirmText" class="form-label">
                        Nhập tên sản phẩm để xác nhận: <code><?= htmlspecialchars($product['ten_san_pham']) ?></code>
                    </label>
                    <input type="text" class="form-control" id="confirmText" 
                           placeholder="Nhập chính xác tên sản phẩm" required>
                    <div class="form-text">Phải nhập chính xác tên sản phẩm để xác nhận xóa</div>
                </div>
                
                <div class="d-flex justify-content-between">
                    <a href="index.php" class="btn btn-outline-secondary">
                        <i class="fas fa-times"></i> Hủy bỏ
                    </a>
                    <button type="submit" name="confirm_delete" class="btn btn-danger" id="deleteBtn" disabled>
                        <i class="fas fa-trash"></i> Xóa vĩnh viễn
                    </button>
                </div>
            </form>
        </div>
    <?php endif; ?>

    <!-- Debug Info -->
    <div class="mt-4">
        <div class="card bg-light">
            <div class="card-body">
                <h6>🔧 Debug Info:</h6>
                <small>
                    Product ID: <?= $product_id ?><br>
                    Variants: <?= $variant_count ?><br>
                    Orders: <?= $order_count ?><br>
                    Can delete: <?= $order_count == 0 ? 'Yes' : 'No' ?><br>
                    Image: <?= $product['hinh_anh_chinh'] ?: 'None' ?>
                </small>
            </div>
        </div>
    </div>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        const productName = <?= json_encode($product['ten_san_pham']) ?>;
        const confirmCheck = document.getElementById('confirmCheck');
        const confirmText = document.getElementById('confirmText');
        const deleteBtn = document.getElementById('deleteBtn');
        
        function checkCanDelete() {
            const isChecked = confirmCheck.checked;
            const isTextMatch = confirmText.value.trim() === productName;
            
            deleteBtn.disabled = !(isChecked && isTextMatch);
            
            if (isTextMatch) {
                confirmText.classList.remove('is-invalid');
                confirmText.classList.add('is-valid');
            } else if (confirmText.value.trim() !== '') {
                confirmText.classList.remove('is-valid');
                confirmText.classList.add('is-invalid');
            } else {
                confirmText.classList.remove('is-valid', 'is-invalid');
            }
        }
        
        confirmCheck.addEventListener('change', checkCanDelete);
        confirmText.addEventListener('input', checkCanDelete);
        
        document.getElementById('deleteForm').addEventListener('submit', function(e) {
            if (!confirm('🚨 BẠN CÓ CHẮC CHẮN MUỐN XÓA SẢN PHẨM NÀY?\n\nHành động này không thể hoàn tác!')) {
                e.preventDefault();
                return;
            }
            
            // Show loading
            deleteBtn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Đang xóa...';
            deleteBtn.disabled = true;
        });
    </script>
</body>
</html>