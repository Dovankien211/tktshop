<?php
// admin/colors/create.php
/**
 * Thêm màu sắc mới
 */

require_once '../../config/database.php';
require_once '../../config/config.php';

requireLogin();

$errors = [];

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $ten_mau = trim($_POST['ten_mau'] ?? '');
    $ma_mau = trim($_POST['ma_mau'] ?? '');
    $thu_tu_hien_thi = (int)($_POST['thu_tu_hien_thi'] ?? 0);
    $trang_thai = $_POST['trang_thai'] ?? 'hoat_dong';
    
    // Validate
    if (empty($ten_mau)) {
        $errors[] = 'Tên màu không được để trống';
    }
    
    if (empty($ma_mau)) {
        $errors[] = 'Mã màu không được để trống';
    } elseif (!preg_match('/^#[0-9A-Fa-f]{6}$/', $ma_mau)) {
        $errors[] = 'Mã màu phải có định dạng #RRGGBB (ví dụ: #FF0000)';
    }
    
    // Kiểm tra trùng lặp
    if (!empty($ten_mau)) {
        $check = $pdo->prepare("SELECT id FROM mau_sac WHERE ten_mau = ?");
        $check->execute([$ten_mau]);
        if ($check->fetch()) {
            $errors[] = 'Tên màu đã tồn tại';
        }
    }
    
    if (!empty($ma_mau)) {
        $check = $pdo->prepare("SELECT id FROM mau_sac WHERE ma_mau = ?");
        $check->execute([$ma_mau]);
        if ($check->fetch()) {
            $errors[] = 'Mã màu đã tồn tại';
        }
    }
    
    // Lưu vào database
    if (empty($errors)) {
        $stmt = $pdo->prepare("INSERT INTO mau_sac (ten_mau, ma_mau, thu_tu_hien_thi, trang_thai) VALUES (?, ?, ?, ?)");
        
        if ($stmt->execute([$ten_mau, $ma_mau, $thu_tu_hien_thi, $trang_thai])) {
            alert('Thêm màu sắc thành công!', 'success');
            header('Location: /tktshop/admin/colors/index.php');
            exit;
        } else {
            $errors[] = 'Lỗi khi lưu dữ liệu';
        }
    }
}

// Lấy màu sắc hiện có để gợi ý thứ tự
$existing_colors = $pdo->query("
    SELECT ten_mau, ma_mau, thu_tu_hien_thi 
    FROM mau_sac 
    ORDER BY thu_tu_hien_thi ASC
")->fetchAll();
?>

<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Thêm màu sắc - <?= SITE_NAME ?></title>
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
                    <h2>Thêm màu sắc mới</h2>
                    <a href="/tktshop/admin/colors/" class="btn btn-secondary">
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
                    <!-- Form thêm màu sắc -->
                    <div class="col-md-6">
                        <div class="card">
                            <div class="card-header">
                                <h5>Thông tin màu sắc</h5>
                            </div>
                            <div class="card-body">
                                <form method="POST">
                                    <div class="mb-3">
                                        <label for="ten_mau" class="form-label">Tên màu <span class="text-danger">*</span></label>
                                        <input type="text" 
                                               class="form-control" 
                                               id="ten_mau" 
                                               name="ten_mau" 
                                               value="<?= htmlspecialchars($_POST['ten_mau'] ?? '') ?>"
                                               placeholder="Ví dụ: Đen, Trắng, Đỏ..."
                                               required>
                                    </div>
                                    
                                    <div class="mb-3">
                                        <label for="ma_mau" class="form-label">Mã màu <span class="text-danger">*</span></label>
                                        <div class="input-group">
                                            <input type="color" 
                                                   class="form-control form-control-color" 
                                                   id="color_picker" 
                                                   value="<?= $_POST['ma_mau'] ?? '#000000' ?>"
                                                   style="width: 60px;">
                                            <input type="text" 
                                                   class="form-control" 
                                                   id="ma_mau" 
                                                   name="ma_mau" 
                                                   value="<?= htmlspecialchars($_POST['ma_mau'] ?? '') ?>"
                                                   placeholder="#FF0000"
                                                   pattern="^#[0-9A-Fa-f]{6}$"
                                                   required>
                                        </div>
                                        <div class="form-text">Sử dụng định dạng HEX: #RRGGBB</div>
                                    </div>
                                    
                                    <div class="mb-3">
                                        <label for="thu_tu_hien_thi" class="form-label">Thứ tự hiển thị</label>
                                        <input type="number" 
                                               class="form-control" 
                                               id="thu_tu_hien_thi" 
                                               name="thu_tu_hien_thi" 
                                               value="<?= $_POST['thu_tu_hien_thi'] ?? '' ?>"
                                               min="0"
                                               placeholder="Để trống để tự động tạo">
                                        <div class="form-text">Số nhỏ sẽ hiển thị trước</div>
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
                                            <i class="fas fa-save"></i> Lưu màu sắc
                                        </button>
                                        <a href="/tktshop/admin/colors/" class="btn btn-secondary">Hủy</a>
                                    </div>
                                </form>
                            </div>
                        </div>
                    </div>
                    
                    <!-- Màu sắc hiện có và gợi ý -->
                    <div class="col-md-6">
                        <div class="card">
                            <div class="card-header">
                                <h5>Màu sắc hiện có</h5>
                            </div>
                            <div class="card-body">
                                <?php if (empty($existing_colors)): ?>
                                    <p class="text-muted">Chưa có màu sắc nào</p>
                                <?php else: ?>
                                    <div class="row">
                                        <?php foreach ($existing_colors as $color): ?>
                                            <div class="col-4 mb-3">
                                                <div class="d-flex align-items-center p-2 border rounded">
                                                    <div class="rounded-circle me-2" 
                                                         style="width: 30px; height: 30px; background-color: <?= htmlspecialchars($color['ma_mau']) ?>; border: 1px solid #ccc;">
                                                    </div>
                                                    <div>
                                                        <small class="fw-bold"><?= htmlspecialchars($color['ten_mau']) ?></small>
                                                        <br><small class="text-muted"><?= $color['thu_tu_hien_thi'] ?></small>
                                                    </div>
                                                </div>
                                            </div>
                                        <?php endforeach; ?>
                                    </div>
                                <?php endif; ?>
                            </div>
                        </div>
                        
                        <!-- Gợi ý màu phổ biến -->
                        <div class="card mt-3">
                            <div class="card-header">
                                <h5>Màu sắc phổ biến</h5>
                            </div>
                            <div class="card-body">
                                <p class="mb-2"><strong>Màu cơ bản:</strong></p>
                                <div class="mb-3">
                                    <?php 
                                    $basic_colors = [
                                        ['name' => 'Đen', 'code' => '#000000'],
                                        ['name' => 'Trắng', 'code' => '#FFFFFF'],
                                        ['name' => 'Xám', 'code' => '#808080'],
                                        ['name' => 'Nâu', 'code' => '#8B4513']
                                    ];
                                    foreach ($basic_colors as $color): 
                                    ?>
                                        <button type="button" 
                                                class="btn btn-outline-secondary btn-sm me-2 mb-2"
                                                onclick="fillColor('<?= $color['name'] ?>', '<?= $color['code'] ?>')">
                                            <span class="rounded-circle d-inline-block me-1" 
                                                  style="width: 12px; height: 12px; background-color: <?= $color['code'] ?>; border: 1px solid #ccc; vertical-align: middle;"></span>
                                            <?= $color['name'] ?>
                                        </button>
                                    <?php endforeach; ?>
                                </div>
                                
                                <p class="mb-2"><strong>Màu sáng:</strong></p>
                                <div class="mb-3">
                                    <?php 
                                    $bright_colors = [
                                        ['name' => 'Đỏ', 'code' => '#FF0000'],
                                        ['name' => 'Xanh dương', 'code' => '#0000FF'],
                                        ['name' => 'Xanh lá', 'code' => '#00FF00'],
                                        ['name' => 'Vàng', 'code' => '#FFFF00'],
                                        ['name' => 'Cam', 'code' => '#FFA500'],
                                        ['name' => 'Tím', 'code' => '#800080']
                                    ];
                                    foreach ($bright_colors as $color): 
                                    ?>
                                        <button type="button" 
                                                class="btn btn-outline-primary btn-sm me-2 mb-2"
                                                onclick="fillColor('<?= $color['name'] ?>', '<?= $color['code'] ?>')">
                                            <span class="rounded-circle d-inline-block me-1" 
                                                  style="width: 12px; height: 12px; background-color: <?= $color['code'] ?>; border: 1px solid #ccc; vertical-align: middle;"></span>
                                            <?= $color['name'] ?>
                                        </button>
                                    <?php endforeach; ?>
                                </div>
                                
                                <p class="mb-2"><strong>Màu pastel:</strong></p>
                                <div>
                                    <?php 
                                    $pastel_colors = [
                                        ['name' => 'Hồng nhạt', 'code' => '#FFB6C1'],
                                        ['name' => 'Xanh nhạt', 'code' => '#ADD8E6'],
                                        ['name' => 'Vàng nhạt', 'code' => '#FFFFE0'],
                                        ['name' => 'Tím nhạt', 'code' => '#DDA0DD']
                                    ];
                                    foreach ($pastel_colors as $color): 
                                    ?>
                                        <button type="button" 
                                                class="btn btn-outline-info btn-sm me-2 mb-2"
                                                onclick="fillColor('<?= $color['name'] ?>', '<?= $color['code'] ?>')">
                                            <span class="rounded-circle d-inline-block me-1" 
                                                  style="width: 12px; height: 12px; background-color: <?= $color['code'] ?>; border: 1px solid #ccc; vertical-align: middle;"></span>
                                            <?= $color['name'] ?>
                                        </button>
                                    <?php endforeach; ?>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
    
    <script>
        // Fill color when clicking on suggested colors
        function fillColor(name, code) {
            document.getElementById('ten_mau').value = name;
            document.getElementById('ma_mau').value = code.toUpperCase();
            document.getElementById('color_picker').value = code;
            
            // Auto-set display order if empty
            const orderInput = document.getElementById('thu_tu_hien_thi');
            if (!orderInput.value) {
                // Set a reasonable default order
                const existingCount = <?= count($existing_colors) ?>;
                orderInput.value = (existingCount + 1) * 10;
            }
            
            // Focus on name field for editing
            document.getElementById('ten_mau').focus();
            document.getElementById('ten_mau').select();
        }
        
        // Sync color picker with text input
        document.getElementById('color_picker').addEventListener('change', function() {
            document.getElementById('ma_mau').value = this.value.toUpperCase();
        });
        
        document.getElementById('ma_mau').addEventListener('input', function() {
            const value = this.value;
            if (/^#[0-9A-Fa-f]{6}$/.test(value)) {
                document.getElementById('color_picker').value = value;
            }
        });
        
        // Auto-generate display order if empty
        document.getElementById('ten_mau').addEventListener('input', function() {
            const orderInput = document.getElementById('thu_tu_hien_thi');
            if (!orderInput.value && this.value) {
                const existingCount = <?= count($existing_colors) ?>;
                orderInput.value = (existingCount + 1) * 10;
            }
        });
        
        // Auto-focus on name input
        document.addEventListener('DOMContentLoaded', function() {
            document.getElementById('ten_mau').focus();
        });
        
        // Validate hex color format
        document.getElementById('ma_mau').addEventListener('keypress', function(e) {
            const char = String.fromCharCode(e.which);
            const currentValue = this.value;
            
            // Allow backspace, delete, etc.
            if (e.which < 32) return;
            
            // Ensure starts with #
            if (currentValue.length === 0 && char !== '#') {
                this.value = '#' + char;
                e.preventDefault();
                return;
            }
            
            // Only allow hex characters after #
            if (currentValue.length >= 1 && !/[0-9A-Fa-f]/.test(char)) {
                e.preventDefault();
            }
            
            // Limit to 7 characters (#RRGGBB)
            if (currentValue.length >= 7) {
                e.preventDefault();
            }
        });
        
        // Convert to uppercase on blur
        document.getElementById('ma_mau').addEventListener('blur', function() {
            this.value = this.value.toUpperCase();
        });
    </script>
</body>
</html>