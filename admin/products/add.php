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

// Lấy danh sách danh mục
$categories = [];
try {
    $stmt = $pdo->query("SELECT * FROM danh_muc_giay ORDER BY ten_danh_muc ASC");
    $categories = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    $error = "Lỗi khi tải danh mục: " . $e->getMessage();
}

// Xử lý form submit
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        echo "🔍 DEBUG: Form đã submit<br>";
        
        // Lấy dữ liệu từ form
        $ten_san_pham = trim($_POST['ten_san_pham'] ?? '');
        $thuong_hieu = trim($_POST['thuong_hieu'] ?? '');
        $danh_muc_id = (int)($_POST['danh_muc_id'] ?? 0);
        $gia_goc = (int)($_POST['gia_goc'] ?? 0);
        $gia_khuyen_mai = !empty($_POST['gia_khuyen_mai']) ? (int)$_POST['gia_khuyen_mai'] : null;
        $mo_ta_ngan = trim($_POST['mo_ta_ngan'] ?? '');
        $mo_ta_chi_tiet = trim($_POST['mo_ta_chi_tiet'] ?? '');
        $trang_thai = $_POST['trang_thai'] ?? 'hoat_dong';
        
        echo "🔍 DEBUG: Dữ liệu nhận được:<br>";
        echo "- Tên: $ten_san_pham<br>";
        echo "- Thương hiệu: $thuong_hieu<br>";
        echo "- Danh mục: $danh_muc_id<br>";
        echo "- Giá: $gia_goc<br>";
        
        // Validate cơ bản
        if (empty($ten_san_pham)) throw new Exception("Tên sản phẩm không được để trống!");
        if (empty($thuong_hieu)) throw new Exception("Thương hiệu không được để trống!");
        if ($danh_muc_id <= 0) throw new Exception("Vui lòng chọn danh mục!");
        if ($gia_goc <= 0) throw new Exception("Giá gốc phải lớn hơn 0!");
        if (empty($mo_ta_ngan)) throw new Exception("Mô tả ngắn không được để trống!");
        
        // Tạo mã sản phẩm tự động
        $ma_san_pham = strtoupper($thuong_hieu . '-' . date('YmdHis') . '-' . rand(100, 999));
        
        // Tạo slug đơn giản
        $slug = strtolower(str_replace(' ', '-', $ten_san_pham)) . '-' . time();
        
        echo "🔍 DEBUG: Validation OK, chuẩn bị insert<br>";
        
        // Insert sản phẩm
        $sql = "INSERT INTO san_pham_chinh (
                    ma_san_pham, ten_san_pham, slug, thuong_hieu, danh_muc_id,
                    gia_goc, gia_khuyen_mai, mo_ta_ngan, mo_ta_chi_tiet, trang_thai,
                    ngay_tao, ngay_cap_nhat
                ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW(), NOW())";
        
        $stmt = $pdo->prepare($sql);
        $result = $stmt->execute([
            $ma_san_pham, $ten_san_pham, $slug, $thuong_hieu, $danh_muc_id,
            $gia_goc, $gia_khuyen_mai, $mo_ta_ngan, $mo_ta_chi_tiet, $trang_thai
        ]);
        
        if ($result) {
            $product_id = $pdo->lastInsertId();
            echo "🔍 DEBUG: Insert thành công! Product ID: $product_id<br>";
            
            $success = "✅ Thêm sản phẩm thành công! ID: $product_id";
            
            // Reset form
            $_POST = [];
        } else {
            throw new Exception("Lỗi khi insert vào database");
        }
        
    } catch (Exception $e) {
        $error = "❌ Lỗi: " . $e->getMessage();
        echo "🔍 DEBUG Error: " . $e->getMessage() . "<br>";
    }
}
?>

<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Thêm sản phẩm - TKT Shop Admin</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
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
                        <h1 class="h2">➕ Thêm sản phẩm mới</h1>
                        <a href="index.php" class="btn btn-outline-secondary">
                            <i class="fas fa-arrow-left"></i> Quay lại
                        </a>
                    </div>

                    <!-- Alert Messages -->
                    <?php if ($error): ?>
                        <div class="alert alert-danger alert-dismissible fade show">
                            <i class="fas fa-exclamation-triangle"></i> <?= $error ?>
                            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                        </div>
                    <?php endif; ?>

                    <?php if ($success): ?>
                        <div class="alert alert-success alert-dismissible fade show">
                            <i class="fas fa-check-circle"></i> <?= $success ?>
                            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                            <div class="mt-2">
                                <a href="index.php" class="btn btn-sm btn-success">Xem danh sách</a>
                                <button type="button" class="btn btn-sm btn-primary" onclick="location.reload()">Thêm sản phẩm khác</button>
                            </div>
                        </div>
                    <?php endif; ?>

                    <form method="POST" id="productForm">
                        <div class="row">
                            <!-- Left Column -->
                            <div class="col-md-8">
                                <div class="card">
                                    <div class="card-header">
                                        <h5><i class="fas fa-info-circle me-2"></i>Thông tin sản phẩm</h5>
                                    </div>
                                    <div class="card-body">
                                        <div class="row">
                                            <div class="col-md-8">
                                                <div class="mb-3">
                                                    <label for="ten_san_pham" class="form-label">Tên sản phẩm <span class="text-danger">*</span></label>
                                                    <input type="text" class="form-control" id="ten_san_pham" name="ten_san_pham" 
                                                           value="<?= htmlspecialchars($_POST['ten_san_pham'] ?? '') ?>" required>
                                                </div>
                                            </div>
                                            <div class="col-md-4">
                                                <div class="mb-3">
                                                    <label for="thuong_hieu" class="form-label">Thương hiệu <span class="text-danger">*</span></label>
                                                    <input type="text" class="form-control" id="thuong_hieu" name="thuong_hieu" 
                                                           value="<?= htmlspecialchars($_POST['thuong_hieu'] ?? '') ?>" 
                                                           list="brandsList" required>
                                                    <datalist id="brandsList">
                                                        <option value="Nike">
                                                        <option value="Adidas">
                                                        <option value="Converse">
                                                        <option value="Vans">
                                                        <option value="Puma">
                                                    </datalist>
                                                </div>
                                            </div>
                                        </div>
                                        
                                        <div class="row">
                                            <div class="col-md-6">
                                                <div class="mb-3">
                                                    <label for="danh_muc_id" class="form-label">Danh mục <span class="text-danger">*</span></label>
                                                    <select class="form-select" id="danh_muc_id" name="danh_muc_id" required>
                                                        <option value="">Chọn danh mục</option>
                                                        <?php foreach ($categories as $category): ?>
                                                            <option value="<?= $category['id'] ?>" 
                                                                    <?= (($_POST['danh_muc_id'] ?? '') == $category['id']) ? 'selected' : '' ?>>
                                                                <?= htmlspecialchars($category['ten_danh_muc']) ?>
                                                            </option>
                                                        <?php endforeach; ?>
                                                    </select>
                                                </div>
                                            </div>
                                            <div class="col-md-6">
                                                <div class="mb-3">
                                                    <label for="trang_thai" class="form-label">Trạng thái</label>
                                                    <select class="form-select" id="trang_thai" name="trang_thai">
                                                        <option value="hoat_dong" <?= (($_POST['trang_thai'] ?? 'hoat_dong') === 'hoat_dong') ? 'selected' : '' ?>>Hoạt động</option>
                                                        <option value="an" <?= (($_POST['trang_thai'] ?? '') === 'an') ? 'selected' : '' ?>>Ẩn sản phẩm</option>
                                                    </select>
                                                </div>
                                            </div>
                                        </div>

                                        <div class="row">
                                            <div class="col-md-6">
                                                <div class="mb-3">
                                                    <label for="gia_goc" class="form-label">Giá gốc <span class="text-danger">*</span></label>
                                                    <div class="input-group">
                                                        <input type="number" class="form-control" id="gia_goc" name="gia_goc" 
                                                               value="<?= htmlspecialchars($_POST['gia_goc'] ?? '') ?>" 
                                                               min="1000" step="1000" required>
                                                        <span class="input-group-text">₫</span>
                                                    </div>
                                                </div>
                                            </div>
                                            <div class="col-md-6">
                                                <div class="mb-3">
                                                    <label for="gia_khuyen_mai" class="form-label">Giá khuyến mãi</label>
                                                    <div class="input-group">
                                                        <input type="number" class="form-control" id="gia_khuyen_mai" name="gia_khuyen_mai" 
                                                               value="<?= htmlspecialchars($_POST['gia_khuyen_mai'] ?? '') ?>" 
                                                               min="1000" step="1000">
                                                        <span class="input-group-text">₫</span>
                                                    </div>
                                                </div>
                                            </div>
                                        </div>

                                        <div class="mb-3">
                                            <label for="mo_ta_ngan" class="form-label">Mô tả ngắn <span class="text-danger">*</span></label>
                                            <textarea class="form-control" id="mo_ta_ngan" name="mo_ta_ngan" rows="3" 
                                                      required><?= htmlspecialchars($_POST['mo_ta_ngan'] ?? '') ?></textarea>
                                        </div>

                                        <div class="mb-3">
                                            <label for="mo_ta_chi_tiet" class="form-label">Mô tả chi tiết</label>
                                            <textarea class="form-control" id="mo_ta_chi_tiet" name="mo_ta_chi_tiet" rows="6"><?= htmlspecialchars($_POST['mo_ta_chi_tiet'] ?? '') ?></textarea>
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <!-- Right Column -->
                            <div class="col-md-4">
                                <!-- Test Data Card -->
                                <div class="card mb-3">
                                    <div class="card-header">
                                        <h6><i class="fas fa-magic me-2"></i>Dữ liệu test nhanh</h6>
                                    </div>
                                    <div class="card-body">
                                        <button type="button" class="btn btn-outline-primary btn-sm w-100 mb-2" onclick="fillTestData()">
                                            <i class="fas fa-fill-drip"></i> Điền dữ liệu test
                                        </button>
                                        <small class="text-muted">Click để tự động điền form với dữ liệu mẫu</small>
                                    </div>
                                </div>

                                <!-- Actions -->
                                <div class="card">
                                    <div class="card-header">
                                        <h6><i class="fas fa-cog me-2"></i>Hành động</h6>
                                    </div>
                                    <div class="card-body">
                                        <button type="submit" class="btn btn-success w-100 mb-2">
                                            <i class="fas fa-save"></i> Lưu sản phẩm
                                        </button>
                                        <a href="index.php" class="btn btn-outline-secondary w-100">
                                            <i class="fas fa-times"></i> Hủy bỏ
                                        </a>
                                    </div>
                                </div>

                                <!-- Debug Info -->
                                <div class="card bg-light mt-3">
                                    <div class="card-body">
                                        <h6>🔧 Debug Info</h6>
                                        <small>
                                            File: add.php<br>
                                            Method: <?= $_SERVER['REQUEST_METHOD'] ?><br>
                                            Categories: <?= count($categories) ?><br>
                                            Session: <?= isset($_SESSION['user_id']) ? 'OK' : 'None' ?>
                                        </small>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        function fillTestData() {
            document.getElementById('ten_san_pham').value = 'Giày Nike Air Max 270 Test';
            document.getElementById('thuong_hieu').value = 'Nike';
            document.getElementById('gia_goc').value = '2500000';
            document.getElementById('gia_khuyen_mai').value = '2200000';
            document.getElementById('mo_ta_ngan').value = 'Giày thể thao Nike Air Max 270 chính hãng, thiết kế hiện đại, thoải mái cho mọi hoạt động.';
            document.getElementById('mo_ta_chi_tiet').value = 'Giày Nike Air Max 270 là sự kết hợp hoàn hảo giữa phong cách và hiệu suất. Với công nghệ đệm Air Max tiên tiến, đôi giày mang lại cảm giác êm ái và thoải mái suốt cả ngày dài.';
            
            // Select first category if available
            const categorySelect = document.getElementById('danh_muc_id');
            if (categorySelect.options.length > 1) {
                categorySelect.selectedIndex = 1;
            }
            
            alert('✅ Đã điền dữ liệu test!');
        }

        // Form validation
        document.getElementById('productForm').addEventListener('submit', function(e) {
            const requiredFields = ['ten_san_pham', 'thuong_hieu', 'danh_muc_id', 'gia_goc', 'mo_ta_ngan'];
            let isValid = true;
            
            requiredFields.forEach(field => {
                const input = document.querySelector(`[name="${field}"]`);
                if (!input.value.trim()) {
                    input.classList.add('is-invalid');
                    isValid = false;
                } else {
                    input.classList.remove('is-invalid');
                }
            });
            
            if (!isValid) {
                e.preventDefault();
                alert('❌ Vui lòng điền đầy đủ thông tin bắt buộc!');
                return;
            }
            
            // Show loading
            const submitBtn = this.querySelector('button[type="submit"]');
            submitBtn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Đang lưu...';
            submitBtn.disabled = true;
        });

        // Price validation
        document.getElementById('gia_khuyen_mai').addEventListener('change', function() {
            const giaGoc = parseInt(document.getElementById('gia_goc').value);
            const giaKhuyenMai = parseInt(this.value);
            
            if (giaKhuyenMai && giaKhuyenMai >= giaGoc) {
                alert('⚠️ Giá khuyến mãi phải nhỏ hơn giá gốc!');
                this.value = '';
            }
        });
    </script>
</body>
</html>