<?php
// admin/sizes/edit.php
/**
 * Chỉnh sửa kích cỡ giày
 */

require_once '../../config/database.php';
require_once '../../config/config.php';

requireLogin();

$id = (int)($_GET['id'] ?? 0);
$errors = [];

// Lấy thông tin kích cỡ hiện tại
$stmt = $pdo->prepare("SELECT * FROM kich_co WHERE id = ?");
$stmt->execute([$id]);
$size = $stmt->fetch();

if (!$size) {
    alert('Kích cỡ không tồn tại!', 'danger');
    redirect('/tktshop/admin/sizes/');
}

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $kich_co = trim($_POST['kich_co'] ?? '');
    $mo_ta = trim($_POST['mo_ta'] ?? '');
    $thu_tu_sap_xep = (int)($_POST['thu_tu_sap_xep'] ?? 0);
    $trang_thai = $_POST['trang_thai'] ?? 'hoat_dong';
    
    // Validate
    if (empty($kich_co)) {
        $errors[] = 'Kích cỡ không được để trống';
    }
    
    // Kiểm tra định dạng kích cỡ
    if (!empty($kich_co) && !preg_match('/^[\d\.]+$/', $kich_co)) {
        $errors[] = 'Kích cỡ chỉ được chứa số (VD: 40, 40.5)';
    }
    
    // Kiểm tra trùng lặp (loại trừ bản ghi hiện tại)
    if (!empty($kich_co)) {
        $check = $pdo->prepare("SELECT id FROM kich_co WHERE kich_co = ? AND id != ?");
        $check->execute([$kich_co, $id]);
        if ($check->fetch()) {
            $errors[] = 'Kích cỡ đã tồn tại';
        }
    }
    
    // Cập nhật database
    if (empty($errors)) {
        $stmt = $pdo->prepare("
            UPDATE kich_co 
            SET kich_co = ?, mo_ta = ?, thu_tu_sap_xep = ?, trang_thai = ?
            WHERE id = ?
        ");
        
        if ($stmt->execute([$kich_co, $mo_ta, $thu_tu_sap_xep, $trang_thai, $id])) {
            alert('Cập nhật kích cỡ thành công!', 'success');
            redirect('/tktshop/admin/sizes/');
        } else {
            $errors[] = 'Lỗi khi cập nhật dữ liệu';
        }
    }
} else {
    // Hiển thị dữ liệu hiện tại
    $_POST = $size;
}

// Lấy thống kê sử dụng
$stmt = $pdo->prepare("
    SELECT 
        COUNT(DISTINCT bsp.id) as so_bien_the,
        COUNT(DISTINCT sp.id) as so_san_pham,
        SUM(bsp.so_luong_ton_kho) as tong_ton_kho,
        SUM(bsp.so_luong_da_ban) as tong_da_ban
    FROM bien_the_san_pham bsp
    JOIN san_pham_chinh sp ON bsp.san_pham_id = sp.id
    WHERE bsp.kich_co_id = ? AND bsp.trang_thai = 'hoat_dong' AND sp.trang_thai = 'hoat_dong'
");
$stmt->execute([$id]);
$stats = $stmt->fetch();
?>

<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Chỉnh sửa kích cỡ - <?= SITE_NAME ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
</head>
<body>
    <div class="container-fluid">
        <div class="row">
            <!-- Sidebar -->
            <?php include '../layouts/sidebar.php'; ?>
            
            <!-- Main content -->
            <div class="col-md-10">
                <div class="d-flex justify-content-between align-items-center py-3">
                    <div>
                        <h2>Chỉnh sửa kích cỡ</h2>
                        <p class="text-muted mb-0">Size <?= htmlspecialchars($size['kich_co']) ?></p>
                    </div>
                    <a href="/tktshop/admin/sizes/" class="btn btn-secondary">
                        <i class="fas fa-arrow-left"></i> Quay lại
                    </a>
                </div>

                <?php if (!empty($errors)): ?>
                    <div class="alert alert-danger">
                        <ul class="mb-0">
                            <?php foreach ($errors as $error): ?>
                                <li><?= $error ?></li>
                            <?php endforeach; ?>
                        </ul>
                    </div>
                <?php endif; ?>

                <div class="row">
                    <!-- Form chỉnh sửa -->
                    <div class="col-md-6">
                        <div class="card">
                            <div class="card-header">
                                <h5>Thông tin kích cỡ</h5>
                            </div>
                            <div class="card-body">
                                <form method="POST">
                                    <div class="mb-3">
                                        <label for="kich_co" class="form-label">Kích cỡ <span class="text-danger">*</span></label>
                                        <input type="text" 
                                               class="form-control" 
                                               id="kich_co" 
                                               name="kich_co" 
                                               value="<?= htmlspecialchars($_POST['kich_co'] ?? '') ?>"
                                               required>
                                        <div class="form-text">Chỉ được nhập số (hỗ trợ số thập phân)</div>
                                    </div>
                                    
                                    <div class="mb-3">
                                        <label for="mo_ta" class="form-label">Mô tả</label>
                                        <input type="text" 
                                               class="form-control" 
                                               id="mo_ta" 
                                               name="mo_ta" 
                                               value="<?= htmlspecialchars($_POST['mo_ta'] ?? '') ?>">
                                    </div>
                                    
                                    <div class="mb-3">
                                        <label for="thu_tu_sap_xep" class="form-label">Thứ tự sắp xếp</label>
                                        <input type="number" 
                                               class="form-control" 
                                               id="thu_tu_sap_xep" 
                                               name="thu_tu_sap_xep" 
                                               value="<?= $_POST['thu_tu_sap_xep'] ?? 0 ?>"
                                               min="0">
                                        <div class="form-text">Số nhỏ sẽ hiển thị trước</div>
                                    </div>
                                    
                                    <div class="mb-3">
                                        <label for="trang_thai" class="form-label">Trạng thái</label>
                                        <select class="form-select" id="trang_thai" name="trang_thai">
                                            <option value="hoat_dong" <?= ($_POST['trang_thai'] ?? '') == 'hoat_dong' ? 'selected' : '' ?>>
                                                Hoạt động
                                            </option>
                                            <option value="an" <?= ($_POST['trang_thai'] ?? '') == 'an' ? 'selected' : '' ?>>
                                                Ẩn
                                            </option>
                                        </select>
                                        <?php if ($stats['so_bien_the'] > 0): ?>
                                            <div class="form-text text-warning">
                                                <i class="fas fa-warning"></i> Ẩn kích cỡ sẽ ảnh hưởng đến <?= $stats['so_bien_the'] ?> biến thể sản phẩm
                                            </div>
                                        <?php endif; ?>
                                    </div>
                                    
                                    <div class="d-flex gap-2">
                                        <button type="submit" class="btn btn-primary">
                                            <i class="fas fa-save"></i> Cập nhật kích cỡ
                                        </button>
                                        <a href="/tktshop/admin/sizes/" class="btn btn-secondary">Hủy</a>
                                    </div>
                                </form>
                            </div>
                        </div>
                    </div>
                    
                    <!-- Thống kê sử dụng -->
                    <div class="col-md-6">
                        <div class="card">
                            <div class="card-header">
                                <h5>Thống kê sử dụng</h5>
                            </div>
                            <div class="card-body">
                                <div class="row text-center">
                                    <div class="col-6 mb-3">
                                        <div class="h4 text-primary"><?= $stats['so_bien_the'] ?></div>
                                        <small class="text-muted">Biến thể</small>
                                    </div>
                                    <div class="col-6 mb-3">
                                        <div class="h4 text-info"><?= $stats['so_san_pham'] ?></div>
                                        <small class="text-muted">Sản phẩm</small>
                                    </div>
                                    <div class="col-6">
                                        <div class="h4 text-success"><?= $stats['tong_ton_kho'] ?: 0 ?></div>
                                        <small class="text-muted">Tồn kho</small>
                                    </div>
                                    <div class="col-6">
                                        <div class="h4 text-warning"><?= $stats['tong_da_ban'] ?: 0 ?></div>
                                        <small class="text-muted">Đã bán</small>
                                    </div>
                                </div>
                                
                                <?php if ($stats['so_bien_the'] == 0): ?>
                                    <div class="alert alert-info mt-3">
                                        <i class="fas fa-info-circle"></i>
                                        Kích cỡ này chưa được sử dụng trong sản phẩm nào
                                    </div>
                                <?php else: ?>
                                    <div class="alert alert-warning mt-3">
                                        <i class="fas fa-exclamation-triangle"></i>
                                        Kích cỡ này đang được sử dụng. Hãy cẩn thận khi thay đổi hoặc xóa.
                                    </div>
                                <?php endif; ?>
                            </div>
                        </div>
                        
                        <!-- Sản phẩm sử dụng kích cỡ này -->
                        <?php if ($stats['so_san_pham'] > 0): ?>
                            <?php
                            $stmt = $pdo->prepare("
                                SELECT DISTINCT sp.id, sp.ten_san_pham, sp.hinh_anh_chinh, sp.slug
                                FROM san_pham_chinh sp
                                JOIN bien_the_san_pham bsp ON sp.id = bsp.san_pham_id
                                WHERE bsp.kich_co_id = ? AND sp.trang_thai = 'hoat_dong' AND bsp.trang_thai = 'hoat_dong'
                                ORDER BY sp.ten_san_pham
                                LIMIT 5
                            ");
                            $stmt->execute([$id]);
                            $products = $stmt->fetchAll();
                            ?>
                            <div class="card mt-3">
                                <div class="card-header">
                                    <h5>Sản phẩm sử dụng</h5>
                                </div>
                                <div class="card-body">
                                    <?php foreach ($products as $product): ?>
                                        <div class="d-flex align-items-center mb-2">
                                            <?php if ($product['hinh_anh_chinh']): ?>
                                                <img src="/tktshop/uploads/products/<?= $product['hinh_anh_chinh'] ?>" 
                                                     alt="<?= htmlspecialchars($product['ten_san_pham']) ?>"
                                                     style="width: 30px; height: 30px; object-fit: cover;"
                                                     class="rounded me-2">
                                            <?php endif; ?>
                                            <div class="flex-grow-1">
                                                <a href="/tktshop/admin/products/edit.php?id=<?= $product['id'] ?>" 
                                                   class="text-decoration-none small">
                                                    <?= htmlspecialchars($product['ten_san_pham']) ?>
                                                </a>
                                            </div>
                                        </div>
                                    <?php endforeach; ?>
                                    
                                    <?php if ($stats['so_san_pham'] > 5): ?>
                                        <div class="text-muted small">
                                            và <?= $stats['so_san_pham'] - 5 ?> sản phẩm khác...
                                        </div>
                                    <?php endif; ?>
                                </div>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
    
    <script>
        // Validate size input (only numbers and decimal point)
        document.getElementById('kich_co').addEventListener('keypress', function(e) {
            const char = String.fromCharCode(e.which);
            if (!/[\d\.]/.test(char)) {
                e.preventDefault();
            }
        });
        
        // Auto-calculate sort order when size changes
        document.getElementById('kich_co').addEventListener('input', function() {
            const sizeValue = parseFloat(this.value);
            const sortOrderInput = document.getElementById('thu_tu_sap_xep');
            
            if (!isNaN(sizeValue)) {
                // Only update if current sort order seems to be auto-generated
                const currentOrder = parseInt(sortOrderInput.value);
                const expectedOrder = sizeValue * 10;
                
                if (currentOrder === 0 || Math.abs(currentOrder - expectedOrder) < 50) {
                    sortOrderInput.value = expectedOrder;
                }
            }
        });
    </script>
</body>
</html>