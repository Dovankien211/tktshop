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
        
        // Validate cơ bản
        if (empty($ten_san_pham)) throw new Exception("Tên sản phẩm không được để trống!");
        if (empty($thuong_hieu)) throw new Exception("Thương hiệu không được để trống!");
        if ($danh_muc_id <= 0) throw new Exception("Vui lòng chọn danh mục!");
        if ($gia_goc <= 0) throw new Exception("Giá gốc phải lớn hơn 0!");
        if (empty($mo_ta_ngan)) throw new Exception("Mô tả ngắn không được để trống!");
        
        // Tạo mã sản phẩm tự động
        $ma_san_pham = strtoupper($thuong_hieu . '-' . date('YmdHis') . '-' . rand(100, 999));
        
        // Tạo slug đơn giản
        $slug = strtolower(str_replace([' ', 'đ', 'ă', 'â', 'ê', 'ô', 'ơ', 'ư'], ['-', 'd', 'a', 'a', 'e', 'o', 'o', 'u'], $ten_san_pham)) . '-' . time();
        
        // Xử lý upload ảnh chính
        $hinh_anh_chinh = null;
        if (isset($_FILES['hinh_anh_chinh']) && $_FILES['hinh_anh_chinh']['error'] === UPLOAD_ERR_OK) {
            echo "🔍 DEBUG: Đang upload ảnh chính<br>";
            
            $file = $_FILES['hinh_anh_chinh'];
            $allowed_types = ['image/jpeg', 'image/jpg', 'image/png', 'image/gif'];
            $file_type = mime_content_type($file['tmp_name']);
            
            if (!in_array($file_type, $allowed_types)) {
                throw new Exception("Định dạng ảnh không hợp lệ! Chỉ chấp nhận: JPG, PNG, GIF");
            }
            
            if ($file['size'] > 2 * 1024 * 1024) {
                throw new Exception("Ảnh quá lớn! Tối đa 2MB");
            }
            
            // Tạo thư mục nếu chưa có
            $upload_dir = '../../uploads/products';
            if (!is_dir($upload_dir)) {
                mkdir($upload_dir, 0755, true);
            }
            
            // Tạo tên file unique
            $extension = pathinfo($file['name'], PATHINFO_EXTENSION);
            $hinh_anh_chinh = time() . '_' . uniqid() . '.' . strtolower($extension);
            $target_path = $upload_dir . '/' . $hinh_anh_chinh;
            
            if (!move_uploaded_file($file['tmp_name'], $target_path)) {
                throw new Exception("Lỗi khi upload ảnh!");
            }
            
            echo "🔍 DEBUG: Upload ảnh thành công: $hinh_anh_chinh<br>";
        }
        
        // Xử lý upload ảnh phụ (nếu có)
        $hinh_anh_phu = [];
        if (isset($_FILES['hinh_anh_phu']) && is_array($_FILES['hinh_anh_phu']['name'])) {
            echo "🔍 DEBUG: Đang upload ảnh phụ<br>";
            
            for ($i = 0; $i < count($_FILES['hinh_anh_phu']['name']); $i++) {
                if ($_FILES['hinh_anh_phu']['error'][$i] === UPLOAD_ERR_OK) {
                    $file = [
                        'name' => $_FILES['hinh_anh_phu']['name'][$i],
                        'type' => $_FILES['hinh_anh_phu']['type'][$i],
                        'tmp_name' => $_FILES['hinh_anh_phu']['tmp_name'][$i],
                        'error' => $_FILES['hinh_anh_phu']['error'][$i],
                        'size' => $_FILES['hinh_anh_phu']['size'][$i]
                    ];
                    
                    if ($file['size'] <= 2 * 1024 * 1024) {
                        $extension = pathinfo($file['name'], PATHINFO_EXTENSION);
                        $filename = time() . '_' . uniqid() . '_' . $i . '.' . strtolower($extension);
                        $target_path = $upload_dir . '/' . $filename;
                        
                        if (move_uploaded_file($file['tmp_name'], $target_path)) {
                            $hinh_anh_phu[] = $filename;
                        }
                    }
                }
            }
        }
        
        $hinh_anh_phu_json = !empty($hinh_anh_phu) ? json_encode($hinh_anh_phu) : null;
        
        // Insert sản phẩm (chỉ dùng cột có sẵn)
        $sql = "INSERT INTO san_pham_chinh (
                    ma_san_pham, ten_san_pham, slug, thuong_hieu, danh_muc_id,
                    gia_goc, gia_khuyen_mai, mo_ta_ngan, mo_ta_chi_tiet, 
                    hinh_anh_chinh, trang_thai, ngay_tao, ngay_cap_nhat
                ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW(), NOW())";
        
        $stmt = $pdo->prepare($sql);
        $result = $stmt->execute([
            $ma_san_pham, $ten_san_pham, $slug, $thuong_hieu, $danh_muc_id,
            $gia_goc, $gia_khuyen_mai, $mo_ta_ngan, $mo_ta_chi_tiet,
            $hinh_anh_chinh, $trang_thai
        ]);
        
        if ($result) {
            $product_id = $pdo->lastInsertId();
            echo "🔍 DEBUG: Insert thành công! Product ID: $product_id<br>";
            
            // Nếu có ảnh phụ, có thể lưu riêng vào bảng khác (tùy chọn)
            if (!empty($hinh_anh_phu)) {
                echo "🔍 DEBUG: Có " . count($hinh_anh_phu) . " ảnh phụ (sẽ cần bảng riêng để lưu)<br>";
            }
            
            $success = "✅ Thêm sản phẩm thành công! ID: $product_id. Ảnh chính: " . ($hinh_anh_chinh ? "Có" : "Không có");
            
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
    <style>
        .upload-area {
            border: 2px dashed #ddd;
            border-radius: 8px;
            padding: 2rem;
            text-align: center;
            transition: border-color 0.3s ease;
            cursor: pointer;
        }
        .upload-area:hover {
            border-color: #007bff;
        }
        .upload-area.dragover {
            border-color: #007bff;
            background-color: #f8f9fa;
        }
        .image-preview {
            max-width: 150px;
            max-height: 150px;
            object-fit: cover;
            border-radius: 8px;
            margin: 5px;
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
                        <h1 class="h2">📷 Thêm sản phẩm mới (có ảnh)</h1>
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
                                <a href="index.php" class="btn btn-sm btn-success">📋 Xem danh sách</a>
                                <a href="/tktshop/customer/" class="btn btn-sm btn-info" target="_blank">🛒 Xem trang khách</a>
                                <button type="button" class="btn btn-sm btn-primary" onclick="location.reload()">➕ Thêm sản phẩm khác</button>
                            </div>
                        </div>
                    <?php endif; ?>

                    <form method="POST" enctype="multipart/form-data" id="productForm">
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
                                                        <option value="hoat_dong" <?= (($_POST['trang_thai'] ?? 'hoat_dong') === 'hoat_dong') ? 'selected' : '' ?>>✅ Hoạt động (hiển thị cho khách)</option>
                                                        <option value="an" <?= (($_POST['trang_thai'] ?? '') === 'an') ? 'selected' : '' ?>>❌ Ẩn sản phẩm</option>
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
                                            <div class="form-text">Mô tả này sẽ hiển thị trong danh sách sản phẩm</div>
                                        </div>

                                        <div class="mb-3">
                                            <label for="mo_ta_chi_tiet" class="form-label">Mô tả chi tiết</label>
                                            <textarea class="form-control" id="mo_ta_chi_tiet" name="mo_ta_chi_tiet" rows="6"><?= htmlspecialchars($_POST['mo_ta_chi_tiet'] ?? '') ?></textarea>
                                            <div class="form-text">Mô tả chi tiết sẽ hiển thị trong trang chi tiết sản phẩm</div>
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <!-- Right Column - Images -->
                            <div class="col-md-4">
                                <!-- Main Image Upload -->
                                <div class="card mb-3">
                                    <div class="card-header">
                                        <h6><i class="fas fa-camera me-2"></i>Ảnh chính sản phẩm</h6>
                                    </div>
                                    <div class="card-body">
                                        <div class="upload-area" onclick="document.getElementById('hinh_anh_chinh').click()">
                                            <i class="fas fa-cloud-upload-alt fa-2x text-muted mb-2"></i>
                                            <p class="mb-2">Click để chọn ảnh chính</p>
                                            <small class="text-muted">JPG, PNG, GIF (max 2MB)</small>
                                        </div>
                                        <input type="file" class="form-control" id="hinh_anh_chinh" name="hinh_anh_chinh" 
                                               accept="image/*" style="display: none;" onchange="previewMainImage(this)">
                                        <div id="mainImagePreview"></div>
                                    </div>
                                </div>

                                <!-- Sub Images Upload -->
                                <div class="card mb-3">
                                    <div class="card-header">
                                        <h6><i class="fas fa-images me-2"></i>Ảnh phụ (tùy chọn)</h6>
                                    </div>
                                    <div class="card-body">
                                        <div class="upload-area" onclick="document.getElementById('hinh_anh_phu').click()">
                                            <i class="fas fa-images fa-2x text-muted mb-2"></i>
                                            <p class="mb-2">Click để chọn nhiều ảnh</p>
                                            <small class="text-muted">Có thể chọn nhiều ảnh</small>
                                        </div>
                                        <input type="file" class="form-control" id="hinh_anh_phu" name="hinh_anh_phu[]" 
                                               accept="image/*" multiple style="display: none;" onchange="previewSubImages(this)">
                                        <div id="subImagesPreview"></div>
                                    </div>
                                </div>

                                <!-- Test Data -->
                                <div class="card mb-3">
                                    <div class="card-header">
                                        <h6><i class="fas fa-magic me-2"></i>Dữ liệu test</h6>
                                    </div>
                                    <div class="card-body">
                                        <button type="button" class="btn btn-outline-primary btn-sm w-100 mb-2" onclick="fillTestData()">
                                            <i class="fas fa-fill-drip"></i> Điền dữ liệu test
                                        </button>
                                    </div>
                                </div>

                                <!-- Submit -->
                                <div class="card">
                                    <div class="card-body">
                                        <button type="submit" class="btn btn-success w-100 mb-2">
                                            <i class="fas fa-save"></i> Lưu sản phẩm
                                        </button>
                                        <a href="index.php" class="btn btn-outline-secondary w-100">
                                            <i class="fas fa-times"></i> Hủy bỏ
                                        </a>
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
            document.getElementById('ten_san_pham').value = 'Giày Nike Air Force 1 Low Triple White';
            document.getElementById('thuong_hieu').value = 'Nike';
            document.getElementById('gia_goc').value = '2890000';
            document.getElementById('gia_khuyen_mai').value = '2590000';
            document.getElementById('mo_ta_ngan').value = 'Giày Nike Air Force 1 Low màu trắng toàn phần, thiết kế cổ điển, phù hợp mọi phong cách.';
            document.getElementById('mo_ta_chi_tiet').value = 'Nike Air Force 1 Low Triple White là một trong những mẫu giày thể thao kinh điển nhất mọi thời đại. Với thiết kế toàn màu trắng tinh khôi, đôi giày này dễ dàng phối hợp với mọi trang phục và phù hợp cho nhiều dịp khác nhau.';
            
            // Select first category
            const categorySelect = document.getElementById('danh_muc_id');
            if (categorySelect.options.length > 1) {
                categorySelect.selectedIndex = 1;
            }
            
            alert('✅ Đã điền dữ liệu test! Hãy chọn ảnh để hoàn thiện.');
        }

        function previewMainImage(input) {
            const preview = document.getElementById('mainImagePreview');
            preview.innerHTML = '';
            
            if (input.files && input.files[0]) {
                const file = input.files[0];
                
                if (file.size > 2 * 1024 * 1024) {
                    alert('❌ Ảnh quá lớn! Tối đa 2MB');
                    input.value = '';
                    return;
                }
                
                const reader = new FileReader();
                reader.onload = function(e) {
                    preview.innerHTML = `
                        <div class="mt-3 text-center">
                            <img src="${e.target.result}" class="image-preview" alt="Preview">
                            <div class="mt-2">
                                <small class="text-muted d-block">${file.name}</small>
                                <button type="button" class="btn btn-sm btn-outline-danger" onclick="removeMainImage()">
                                    <i class="fas fa-times"></i> Xóa
                                </button>
                            </div>
                        </div>
                    `;
                };
                reader.readAsDataURL(file);
            }
        }

        function previewSubImages(input) {
            const preview = document.getElementById('subImagesPreview');
            preview.innerHTML = '';
            
            if (input.files) {
                if (input.files.length > 5) {
                    alert('❌ Tối đa 5 ảnh phụ!');
                    return;
                }
                
                Array.from(input.files).forEach((file, index) => {
                    if (file.size > 2 * 1024 * 1024) {
                        alert(`❌ Ảnh ${file.name} quá lớn!`);
                        return;
                    }
                    
                    const reader = new FileReader();
                    reader.onload = function(e) {
                        const imageDiv = document.createElement('div');
                        imageDiv.className = 'mt-2 text-center';
                        imageDiv.innerHTML = `
                            <img src="${e.target.result}" class="image-preview" alt="Preview">
                            <div class="mt-1">
                                <small class="text-muted d-block">${file.name}</small>
                            </div>
                        `;
                        preview.appendChild(imageDiv);
                    };
                    reader.readAsDataURL(file);
                });
            }
        }

        function removeMainImage() {
            document.getElementById('hinh_anh_chinh').value = '';
            document.getElementById('mainImagePreview').innerHTML = '';
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
    </script>
</body>
</html>