<?php
// admin/sizes/create.php
/**
 * Thêm kích cỡ giày mới
 */

require_once '../../config/database.php';
require_once '../../config/config.php';

requireLogin();

$errors = [];

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $kich_co = trim($_POST['kich_co'] ?? '');
    $mo_ta = trim($_POST['mo_ta'] ?? '');
    $thu_tu_sap_xep = (int)($_POST['thu_tu_sap_xep'] ?? 0);
    $trang_thai = $_POST['trang_thai'] ?? 'hoat_dong';
    
    // Validate
    if (empty($kich_co)) {
        $errors[] = 'Kích cỡ không được để trống';
    }
    
    // Kiểm tra định dạng kích cỡ (chỉ cho phép số và dấu chấm)
    if (!empty($kich_co) && !preg_match('/^[\d\.]+$/', $kich_co)) {
        $errors[] = 'Kích cỡ chỉ được chứa số (VD: 40, 40.5)';
    }
    
    // Kiểm tra kích cỡ hợp lệ (từ 30 đến 50)
    if (!empty($kich_co)) {
        $size_number = floatval($kich_co);
        if ($size_number < 30 || $size_number > 50) {
            $errors[] = 'Kích cỡ phải từ 30 đến 50';
        }
    }
    
    // Kiểm tra trùng lặp
    if (!empty($kich_co)) {
        $check = $pdo->prepare("SELECT id FROM kich_co WHERE kich_co = ?");
        $check->execute([$kich_co]);
        if ($check->fetch()) {
            $errors[] = 'Kích cỡ này đã tồn tại';
        }
    }
    
    // Tự động tạo thứ tự sắp xếp nếu chưa có
    if ($thu_tu_sap_xep == 0 && !empty($kich_co)) {
        $thu_tu_sap_xep = floatval($kich_co) * 10;
    }
    
    // Tự động tạo mô tả nếu chưa có
    if (empty($mo_ta) && !empty($kich_co)) {
        $mo_ta = "Size " . $kich_co;
    }
    
    // Lưu vào database
    if (empty($errors)) {
        $stmt = $pdo->prepare("
            INSERT INTO kich_co (kich_co, mo_ta, thu_tu_sap_xep, trang_thai) 
            VALUES (?, ?, ?, ?)
        ");
        
        if ($stmt->execute([$kich_co, $mo_ta, $thu_tu_sap_xep, $trang_thai])) {
            alert('Thêm kích cỡ thành công!', 'success');
            redirect('/tktshop/admin/sizes/');
        } else {
            $errors[] = 'Lỗi khi lưu dữ liệu';
        }
    }
}

// Lấy kích cỡ phổ biến để gợi ý
$common_sizes = ['35', '36', '37', '38', '39', '40', '41', '42', '43', '44', '45'];
$existing_sizes = $pdo->query("SELECT kich_co FROM kich_co ORDER BY thu_tu_sap_xep")->fetchAll(PDO::FETCH_COLUMN);
?>

<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Thêm kích cỡ - <?= SITE_NAME ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
</head>
<body>
    <!-- ✅ Include Header -->
    <?php include '../layouts/header.php'; ?>
    
    <!-- ✅ Include Sidebar -->
    <?php include '../layouts/sidebar.php'; ?>
    
    <!-- ✅ Main Content -->
    <div class="main-content">
        <div class="content-wrapper">
            <div class="d-flex justify-content-between align-items-center py-3">
                <h2>Thêm kích cỡ mới</h2>
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
                <!-- Form thêm kích cỡ -->
                <div class="col-md-6">
                    <div class="card">
                        <div class="card-header">
                            <h5><i class="fas fa-ruler me-2"></i>Thông tin kích cỡ</h5>
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
                                           placeholder="VD: 40, 40.5"
                                           required>
                                    <div class="form-text">Chỉ được nhập số từ 30 đến 50 (hỗ trợ số thập phân)</div>
                                </div>
                                
                                <div class="mb-3">
                                    <label for="mo_ta" class="form-label">Mô tả</label>
                                    <input type="text" 
                                           class="form-control" 
                                           id="mo_ta" 
                                           name="mo_ta" 
                                           value="<?= htmlspecialchars($_POST['mo_ta'] ?? '') ?>"
                                           placeholder="Sẽ tự động tạo nếu để trống">
                                    <div class="form-text">Để trống sẽ tự động tạo "Size [số]"</div>
                                </div>
                                
                                <div class="mb-3">
                                    <label for="thu_tu_sap_xep" class="form-label">Thứ tự sắp xếp</label>
                                    <input type="number" 
                                           class="form-control" 
                                           id="thu_tu_sap_xep" 
                                           name="thu_tu_sap_xep" 
                                           value="<?= $_POST['thu_tu_sap_xep'] ?? 0 ?>"
                                           min="0">
                                    <div class="form-text">Để 0 sẽ tự động tính theo kích cỡ. Số nhỏ hiển thị trước.</div>
                                </div>
                                
                                <div class="mb-3">
                                    <label for="trang_thai" class="form-label">Trạng thái</label>
                                    <select class="form-select" id="trang_thai" name="trang_thai">
                                        <option value="hoat_dong" <?= ($_POST['trang_thai'] ?? 'hoat_dong') == 'hoat_dong' ? 'selected' : '' ?>>
                                            Hoạt động
                                        </option>
                                        <option value="an" <?= ($_POST['trang_thai'] ?? '') == 'an' ? 'selected' : '' ?>>
                                            Ẩn
                                        </option>
                                    </select>
                                </div>
                                
                                <div class="d-flex gap-2">
                                    <button type="submit" class="btn btn-primary">
                                        <i class="fas fa-save"></i> Lưu kích cỡ
                                    </button>
                                    <a href="/tktshop/admin/sizes/" class="btn btn-secondary">Hủy</a>
                                </div>
                            </form>
                        </div>
                    </div>
                </div>
                
                <!-- Gợi ý kích cỡ -->
                <div class="col-md-6">
                    <div class="card">
                        <div class="card-header">
                            <h5><i class="fas fa-lightbulb me-2"></i>Kích cỡ phổ biến</h5>
                        </div>
                        <div class="card-body">
                            <p class="text-muted">Click để chọn nhanh:</p>
                            <div class="d-flex flex-wrap gap-2 mb-3">
                                <?php foreach ($common_sizes as $size): ?>
                                    <?php $is_existing = in_array($size, $existing_sizes); ?>
                                    <button type="button" 
                                            class="btn btn-sm <?= $is_existing ? 'btn-secondary' : 'btn-outline-primary' ?> size-btn"
                                            data-size="<?= $size ?>"
                                            <?= $is_existing ? 'disabled title="Đã tồn tại"' : '' ?>>
                                        <?= $size ?>
                                        <?= $is_existing ? ' ✓' : '' ?>
                                    </button>
                                <?php endforeach; ?>
                            </div>
                            
                            <div class="alert alert-info">
                                <strong>Lưu ý:</strong>
                                <ul class="mb-0 mt-2">
                                    <li>Kích cỡ giày thường từ 35-45</li>
                                    <li>Có thể sử dụng số thập phân (VD: 40.5)</li>
                                    <li>Thứ tự sắp xếp sẽ tự động tính nếu để trống</li>
                                    <li>Các size đã có sẽ hiển thị màu xám</li>
                                </ul>
                            </div>
                        </div>
                    </div>
                    
                    <!-- Danh sách size hiện có -->
                    <?php if (!empty($existing_sizes)): ?>
                        <div class="card mt-3">
                            <div class="card-header">
                                <h5><i class="fas fa-list me-2"></i>Kích cỡ hiện có</h5>
                            </div>
                            <div class="card-body">
                                <div class="d-flex flex-wrap gap-1">
                                    <?php foreach ($existing_sizes as $size): ?>
                                        <span class="badge bg-secondary"><?= $size ?></span>
                                    <?php endforeach; ?>
                                </div>
                            </div>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        // Quick select size buttons
        document.querySelectorAll('.size-btn:not([disabled])').forEach(button => {
            button.addEventListener('click', function() {
                const size = this.dataset.size;
                document.getElementById('kich_co').value = size;
                
                // Auto-generate description if empty
                const descInput = document.getElementById('mo_ta');
                if (!descInput.value) {
                    descInput.value = 'Size ' + size;
                }
                
                // Auto-generate sort order if 0
                const sortInput = document.getElementById('thu_tu_sap_xep');
                if (sortInput.value == 0) {
                    sortInput.value = parseFloat(size) * 10;
                }
                
                // Highlight selected button
                document.querySelectorAll('.size-btn').forEach(btn => btn.classList.remove('btn-primary'));
                this.classList.add('btn-primary');
            });
        });
        
        // Validate size input (only numbers and decimal point)
        document.getElementById('kich_co').addEventListener('keypress', function(e) {
            const char = String.fromCharCode(e.which);
            if (!/[\d\.]/.test(char)) {
                e.preventDefault();
            }
        });
        
        // Auto-generate description and sort order when size changes
        document.getElementById('kich_co').addEventListener('input', function() {
            const sizeValue = this.value;
            const numericSize = parseFloat(sizeValue);
            
            // Auto-generate description if empty
            const descInput = document.getElementById('mo_ta');
            if (!descInput.value && sizeValue) {
                descInput.value = 'Size ' + sizeValue;
            }
            
            // Auto-generate sort order if 0
            const sortInput = document.getElementById('thu_tu_sap_xep');
            if (sortInput.value == 0 && !isNaN(numericSize)) {
                sortInput.value = numericSize * 10;
            }
        });
        
        // Clear description when clearing size
        document.getElementById('kich_co').addEventListener('blur', function() {
            const descInput = document.getElementById('mo_ta');
            if (!this.value && descInput.value.startsWith('Size ')) {
                descInput.value = '';
            }
        });
        
        // Form validation
        document.querySelector('form').addEventListener('submit', function(e) {
            const sizeValue = document.getElementById('kich_co').value;
            const numericSize = parseFloat(sizeValue);
            
            if (isNaN(numericSize) || numericSize < 30 || numericSize > 50) {
                e.preventDefault();
                alert('Kích cỡ phải là số từ 30 đến 50!');
                return false;
            }
        });
    </script>
</body>
</html>
