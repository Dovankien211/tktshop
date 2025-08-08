<?php
// admin/products/edit.php - Complete version
/**
 * Chỉnh sửa sản phẩm
 */

require_once '../../config/database.php';
require_once '../../config/config.php';

requireLogin();

$id = (int)($_GET['id'] ?? 0);
$errors = [];

// Lấy thông tin sản phẩm hiện tại
try {
    $stmt = $pdo->prepare("
        SELECT sp.*, dm.ten_danh_muc
        FROM san_pham_chinh sp
        LEFT JOIN danh_muc_giay dm ON sp.danh_muc_id = dm.id
        WHERE sp.id = ?
    ");
    $stmt->execute([$id]);
    $product = $stmt->fetch();

    if (!$product) {
        alert('Sản phẩm không tồn tại!', 'danger');
        redirect('/tktshop/admin/products/');
    }
} catch (PDOException $e) {
    alert('Lỗi khi lấy thông tin sản phẩm: ' . $e->getMessage(), 'danger');
    redirect('/tktshop/admin/products/');
}

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $ten_san_pham = trim($_POST['ten_san_pham'] ?? '');
    $ma_san_pham = trim($_POST['ma_san_pham'] ?? '');
    $mo_ta_ngan = trim($_POST['mo_ta_ngan'] ?? '');
    $mo_ta_chi_tiet = trim($_POST['mo_ta_chi_tiet'] ?? '');
    $danh_muc_id = (int)($_POST['danh_muc_id'] ?? 0);
    $thuong_hieu = trim($_POST['thuong_hieu'] ?? '');
    $gia_goc = (float)($_POST['gia_goc'] ?? 0);
    $gia_khuyen_mai = !empty($_POST['gia_khuyen_mai']) ? (float)$_POST['gia_khuyen_mai'] : null;
    $ngay_bat_dau_km = !empty($_POST['ngay_bat_dau_km']) ? $_POST['ngay_bat_dau_km'] : null;
    $ngay_ket_thuc_km = !empty($_POST['ngay_ket_thuc_km']) ? $_POST['ngay_ket_thuc_km'] : null;
    $san_pham_noi_bat = isset($_POST['san_pham_noi_bat']) ? 1 : 0;
    $san_pham_moi = isset($_POST['san_pham_moi']) ? 1 : 0;
    $san_pham_ban_chay = isset($_POST['san_pham_ban_chay']) ? 1 : 0;
    $meta_title = trim($_POST['meta_title'] ?? '');
    $meta_description = trim($_POST['meta_description'] ?? '');
    $tags = trim($_POST['tags'] ?? '');
    $trang_thai = $_POST['trang_thai'] ?? 'hoat_dong';
    
    // Validate
    if (empty($ten_san_pham)) {
        $errors[] = 'Tên sản phẩm không được để trống';
    }
    
    if (empty($danh_muc_id)) {
        $errors[] = 'Vui lòng chọn danh mục';
    }
    
    if ($gia_goc <= 0) {
        $errors[] = 'Giá gốc phải lớn hơn 0';
    }
    
    if ($gia_khuyen_mai && $gia_khuyen_mai >= $gia_goc) {
        $errors[] = 'Giá khuyến mãi phải nhỏ hơn giá gốc';
    }
    
    // Kiểm tra mã sản phẩm trùng lặp (loại trừ bản ghi hiện tại)
    if (!empty($ma_san_pham)) {
        try {
            $check = $pdo->prepare("SELECT id FROM san_pham_chinh WHERE ma_san_pham = ? AND id != ?");
            $check->execute([$ma_san_pham, $id]);
            if ($check->fetch()) {
                $errors[] = 'Mã sản phẩm đã tồn tại';
            }
        } catch (PDOException $e) {
            $errors[] = 'Lỗi khi kiểm tra mã sản phẩm: ' . $e->getMessage();
        }
    }
    
    // Tạo slug mới nếu tên thay đổi
    $slug = $product['slug'];
    if ($ten_san_pham !== $product['ten_san_pham']) {
        $slug = createSlug($ten_san_pham);
        
        // Kiểm tra trùng lặp slug (loại trừ bản ghi hiện tại)
        try {
            $check = $pdo->prepare("SELECT id FROM san_pham_chinh WHERE slug = ? AND id != ?");
            $check->execute([$slug, $id]);
            if ($check->fetch()) {
                $slug .= '-' . time();
            }
        } catch (PDOException $e) {
            $errors[] = 'Lỗi khi kiểm tra slug: ' . $e->getMessage();
        }
    }
    
    // Xử lý upload ảnh chính mới
    $hinh_anh_chinh = $product['hinh_anh_chinh'];
    if (!empty($_FILES['hinh_anh_chinh']['name'])) {
        $upload_result = uploadFile($_FILES['hinh_anh_chinh'], 'products');
        if ($upload_result['success']) {
            // Xóa ảnh cũ
            if ($product['hinh_anh_chinh'] && file_exists(UPLOAD_PATH . '/products/' . $product['hinh_anh_chinh'])) {
                unlink(UPLOAD_PATH . '/products/' . $product['hinh_anh_chinh']);
            }
            $hinh_anh_chinh = $upload_result['filename'];
        } else {
            $errors[] = $upload_result['message'];
        }
    }
    
    // Xử lý tags
    $tags_json = null;
    if (!empty($tags)) {
        $tags_array = array_map('trim', explode(',', $tags));
        $tags_array = array_filter($tags_array);
        if (!empty($tags_array)) {
            $tags_json = json_encode($tags_array, JSON_UNESCAPED_UNICODE);
        }
    }
    
    // Cập nhật database
    if (empty($errors)) {
        try {
            $stmt = $pdo->prepare("
                UPDATE san_pham_chinh 
                SET ten_san_pham = ?, slug = ?, ma_san_pham = ?, mo_ta_ngan = ?, mo_ta_chi_tiet = ?,
                    danh_muc_id = ?, thuong_hieu = ?, hinh_anh_chinh = ?, gia_goc = ?, gia_khuyen_mai = ?,
                    ngay_bat_dau_km = ?, ngay_ket_thuc_km = ?, san_pham_noi_bat = ?, san_pham_moi = ?,
                    san_pham_ban_chay = ?, meta_title = ?, meta_description = ?, tags = ?, trang_thai = ?
                WHERE id = ?
            ");
            
            if ($stmt->execute([
                $ten_san_pham, $slug, $ma_san_pham, $mo_ta_ngan, $mo_ta_chi_tiet,
                $danh_muc_id, $thuong_hieu, $hinh_anh_chinh, $gia_goc, $gia_khuyen_mai,
                $ngay_bat_dau_km, $ngay_ket_thuc_km, $san_pham_noi_bat, $san_pham_moi,
                $san_pham_ban_chay, $meta_title, $meta_description, $tags_json, $trang_thai, $id
            ])) {
                alert('Cập nhật sản phẩm thành công!', 'success');
                redirect('/tktshop/admin/products/');
            } else {
                $errors[] = 'Lỗi khi cập nhật dữ liệu';
            }
        } catch (PDOException $e) {
            $errors[] = 'Lỗi database: ' . $e->getMessage();
        }
    }
} else {
    // Hiển thị dữ liệu hiện tại
    $_POST = $product;
    $_POST['ngay_bat_dau_km'] = $product['ngay_bat_dau_km'] ? date('Y-m-d\TH:i', strtotime($product['ngay_bat_dau_km'])) : '';
    $_POST['ngay_ket_thuc_km'] = $product['ngay_ket_thuc_km'] ? date('Y-m-d\TH:i', strtotime($product['ngay_ket_thuc_km'])) : '';
    $_POST['tags'] = '';
    if ($product['tags']) {
        $tags_array = json_decode($product['tags'], true);
        if (is_array($tags_array)) {
            $_POST['tags'] = implode(', ', $tags_array);
        }
    }
}

// Lấy danh mục
try {
    $categories = $pdo->query("
        SELECT * FROM danh_muc_giay 
        WHERE trang_thai = 'hoat_dong' 
        ORDER BY ten_danh_muc
    ")->fetchAll();
} catch (PDOException $e) {
    $categories = [];
    $errors[] = 'Lỗi khi lấy danh mục: ' . $e->getMessage();
}

// Lấy biến thể sản phẩm
try {
    $variants = $pdo->prepare("
        SELECT bsp.*, kc.kich_co, ms.ten_mau, ms.ma_mau
        FROM bien_the_san_pham bsp
        JOIN kich_co kc ON bsp.kich_co_id = kc.id
        JOIN mau_sac ms ON bsp.mau_sac_id = ms.id
        WHERE bsp.san_pham_id = ?
        ORDER BY kc.thu_tu_sap_xep, ms.thu_tu_hien_thi
    ");
    $variants->execute([$id]);
    $variants = $variants->fetchAll();
} catch (PDOException $e) {
    $variants = [];
}
?>

<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Chỉnh sửa sản phẩm - <?= SITE_NAME ?></title>
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
                        <h2>Chỉnh sửa sản phẩm</h2>
                        <p class="text-muted mb-0"><?= htmlspecialchars($product['ten_san_pham']) ?></p>
                    </div>
                    <div>
                        <a href="/tktshop/admin/products/variants.php?id=<?= $id ?>" class="btn btn-info me-2">
                            <i class="fas fa-cogs"></i> Quản lý biến thể
                        </a>
                        <a href="/tktshop/admin/products/" class="btn btn-secondary">
                            <i class="fas fa-arrow-left"></i> Quay lại
                        </a>
                    </div>
                </div>

                <?php if (!empty($errors)): ?>
                    <div class="alert alert-danger">
                        <ul class="mb-0">
                            <?php foreach ($errors as $error): ?>
                                <li><?= htmlspecialchars($error) ?></li>
                            <?php endforeach; ?>
                        </ul>
                    </div>
                <?php endif; ?>

                <form method="POST" action="<?= $_SERVER['PHP_SELF'] ?>?id=<?= $id ?>" enctype="multipart/form-data">
                    <div class="row">
                        <!-- Thông tin cơ bản -->
                        <div class="col-md-8">
                            <div class="card mb-3">
                                <div class="card-header">
                                    <h5>Thông tin cơ bản</h5>
                                </div>
                                <div class="card-body">
                                    <div class="row">
                                        <div class="col-md-8">
                                            <div class="mb-3">
                                                <label for="ten_san_pham" class="form-label">Tên sản phẩm <span class="text-danger">*</span></label>
                                                <input type="text" 
                                                       class="form-control" 
                                                       id="ten_san_pham" 
                                                       name="ten_san_pham" 
                                                       value="<?= htmlspecialchars($_POST['ten_san_pham'] ?? '') ?>"
                                                       required>
                                            </div>
                                        </div>
                                        <div class="col-md-4">
                                            <div class="mb-3">
                                                <label for="ma_san_pham" class="form-label">Mã sản phẩm</label>
                                                <input type="text" 
                                                       class="form-control" 
                                                       id="ma_san_pham" 
                                                       name="ma_san_pham" 
                                                       value="<?= htmlspecialchars($_POST['ma_san_pham'] ?? '') ?>"
                                                       placeholder="AUTO">
                                            </div>
                                        </div>
                                    </div>
                                    
                                    <div class="mb-3">
                                        <label class="form-label">Slug hiện tại</label>
                                        <div class="form-control-plaintext">
                                            <code><?= htmlspecialchars($product['slug']) ?></code>
                                        </div>
                                    </div>
                                    
                                    <div class="row">
                                        <div class="col-md-6">
                                            <div class="mb-3">
                                                <label for="danh_muc_id" class="form-label">Danh mục <span class="text-danger">*</span></label>
                                                <select class="form-select" id="danh_muc_id" name="danh_muc_id" required>
                                                    <option value="">Chọn danh mục</option>
                                                    <?php foreach ($categories as $category): ?>
                                                        <option value="<?= $category['id'] ?>" <?= ($_POST['danh_muc_id'] ?? '') == $category['id'] ? 'selected' : '' ?>>
                                                            <?= htmlspecialchars($category['ten_danh_muc']) ?>
                                                        </option>
                                                    <?php endforeach; ?>
                                                </select>
                                            </div>
                                        </div>
                                        <div class="col-md-6">
                                            <div class="mb-3">
                                                <label for="thuong_hieu" class="form-label">Thương hiệu</label>
                                                <input type="text" 
                                                       class="form-control" 
                                                       id="thuong_hieu" 
                                                       name="thuong_hieu" 
                                                       value="<?= htmlspecialchars($_POST['thuong_hieu'] ?? '') ?>"
                                                       placeholder="Nike, Adidas, ...">
                                            </div>
                                        </div>
                                    </div>
                                    
                                    <div class="mb-3">
                                        <label for="mo_ta_ngan" class="form-label">Mô tả ngắn</label>
                                        <textarea class="form-control" 
                                                  id="mo_ta_ngan" 
                                                  name="mo_ta_ngan" 
                                                  rows="3"
                                                  placeholder="Mô tả ngắn gọn về sản phẩm..."><?= htmlspecialchars($_POST['mo_ta_ngan'] ?? '') ?></textarea>
                                    </div>
                                    
                                    <div class="mb-3">
                                        <label for="mo_ta_chi_tiet" class="form-label">Mô tả chi tiết</label>
                                        <textarea class="form-control" 
                                                  id="mo_ta_chi_tiet" 
                                                  name="mo_ta_chi_tiet" 
                                                  rows="8"
                                                  placeholder="Mô tả chi tiết về sản phẩm..."><?= htmlspecialchars($_POST['mo_ta_chi_tiet'] ?? '') ?></textarea>
                                    </div>
                                </div>
                            </div>

                            <!-- Giá và khuyến mãi -->
                            <div class="card mb-3">
                                <div class="card-header">
                                    <h5>Giá và khuyến mãi</h5>
                                </div>
                                <div class="card-body">
                                    <div class="row">
                                        <div class="col-md-6">
                                            <div class="mb-3">
                                                <label for="gia_goc" class="form-label">Giá gốc <span class="text-danger">*</span></label>
                                                <div class="input-group">
                                                    <input type="number" 
                                                           class="form-control" 
                                                           id="gia_goc" 
                                                           name="gia_goc" 
                                                           value="<?= $_POST['gia_goc'] ?? '' ?>"
                                                           min="0"
                                                           step="1000"
                                                           required>
                                                    <span class="input-group-text">₫</span>
                                                </div>
                                            </div>
                                        </div>
                                        <div class="col-md-6">
                                            <div class="mb-3">
                                                <label for="gia_khuyen_mai" class="form-label">Giá khuyến mãi</label>
                                                <div class="input-group">
                                                    <input type="number" 
                                                           class="form-control" 
                                                           id="gia_khuyen_mai" 
                                                           name="gia_khuyen_mai" 
                                                           value="<?= $_POST['gia_khuyen_mai'] ?? '' ?>"
                                                           min="0"
                                                           step="1000">
                                                    <span class="input-group-text">₫</span>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                    
                                    <div class="row">
                                        <div class="col-md-6">
                                            <div class="mb-3">
                                                <label for="ngay_bat_dau_km" class="form-label">Ngày bắt đầu KM</label>
                                                <input type="datetime-local" 
                                                       class="form-control" 
                                                       id="ngay_bat_dau_km" 
                                                       name="ngay_bat_dau_km" 
                                                       value="<?= $_POST['ngay_bat_dau_km'] ?? '' ?>">
                                            </div>
                                        </div>
                                        <div class="col-md-6">
                                            <div class="mb-3">
                                                <label for="ngay_ket_thuc_km" class="form-label">Ngày kết thúc KM</label>
                                                <input type="datetime-local" 
                                                       class="form-control" 
                                                       id="ngay_ket_thuc_km" 
                                                       name="ngay_ket_thuc_km" 
                                                       value="<?= $_POST['ngay_ket_thuc_km'] ?? '' ?>">
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <!-- SEO -->
                            <div class="card mb-3">
                                <div class="card-header">
                                    <h5>SEO & Tags</h5>
                                </div>
                                <div class="card-body">
                                    <div class="mb-3">
                                        <label for="meta_title" class="form-label">Meta Title</label>
                                        <input type="text" 
                                               class="form-control" 
                                               id="meta_title" 
                                               name="meta_title" 
                                               value="<?= htmlspecialchars($_POST['meta_title'] ?? '') ?>"
                                               maxlength="60">
                                        <div class="form-text">Tối đa 60 ký tự</div>
                                    </div>
                                    
                                    <div class="mb-3">
                                        <label for="meta_description" class="form-label">Meta Description</label>
                                        <textarea class="form-control" 
                                                  id="meta_description" 
                                                  name="meta_description" 
                                                  rows="3"
                                                  maxlength="160"><?= htmlspecialchars($_POST['meta_description'] ?? '') ?></textarea>
                                        <div class="form-text">Tối đa 160 ký tự</div>
                                    </div>
                                    
                                    <div class="mb-3">
                                        <label for="tags" class="form-label">Tags</label>
                                        <input type="text" 
                                               class="form-control" 
                                               id="tags" 
                                               name="tags" 
                                               value="<?= htmlspecialchars($_POST['tags'] ?? '') ?>"
                                               placeholder="giày thể thao, nike, nam">
                                        <div class="form-text">Cách nhau bằng dấu phẩy</div>
                                    </div>
                                </div>
                            </div>
                        </div>
                        
                        <!-- Sidebar -->
                        <div class="col-md-4">
                            <!-- Ảnh sản phẩm -->
                            <div class="card mb-3">
                                <div class="card-header">
                                    <h5>Ảnh sản phẩm</h5>
                                </div>
                                <div class="card-body">
                                    <?php if ($product['hinh_anh_chinh']): ?>
                                        <div class="mb-3 text-center">
                                            <img src="/tktshop/uploads/products/<?= $product['hinh_anh_chinh'] ?>" 
                                                 alt="<?= htmlspecialchars($product['ten_san_pham']) ?>"
                                                 class="img-fluid rounded"
                                                 style="max-height: 200px;">
                                            <div class="mt-2">
                                                <small class="text-muted">Ảnh hiện tại</small>
                                            </div>
                                        </div>
                                    <?php endif; ?>
                                    
                                    <div class="mb-3">
                                        <label for="hinh_anh_chinh" class="form-label">Ảnh chính mới</label>
                                        <input type="file" 
                                               class="form-control" 
                                               id="hinh_anh_chinh" 
                                               name="hinh_anh_chinh"
                                               accept="image/*">
                                        <div class="form-text">JPG, PNG, GIF. Tối đa 2MB</div>
                                    </div>
                                    
                                    <div id="image_preview" class="text-center" style="display: none;">
                                        <img id="preview_img" src="" alt="Preview" class="img-fluid rounded" style="max-height: 200px;">
                                    </div>
                                </div>
                            </div>
                            
                            <!-- Cài đặt -->
                            <div class="card mb-3">
                                <div class="card-header">
                                    <h5>Cài đặt</h5>
                                </div>
                                <div class="card-body">
                                    <div class="mb-3">
                                        <div class="form-check">
                                            <input class="form-check-input" type="checkbox" 
                                                   id="san_pham_noi_bat" name="san_pham_noi_bat" 
                                                   <?= ($_POST['san_pham_noi_bat'] ?? false) ? 'checked' : '' ?>>
                                            <label class="form-check-label" for="san_pham_noi_bat">
                                                Sản phẩm nổi bật
                                            </label>
                                        </div>
                                    </div>
                                    
                                    <div class="mb-3">
                                        <div class="form-check">
                                            <input class="form-check-input" type="checkbox" 
                                                   id="san_pham_moi" name="san_pham_moi" 
                                                   <?= ($_POST['san_pham_moi'] ?? false) ? 'checked' : '' ?>>
                                            <label class="form-check-label" for="san_pham_moi">
                                                Sản phẩm mới
                                            </label>
                                        </div>
                                    </div>
                                    
                                    <div class="mb-3">
                                        <div class="form-check">
                                            <input class="form-check-input" type="checkbox" 
                                                   id="san_pham_ban_chay" name="san_pham_ban_chay" 
                                                   <?= ($_POST['san_pham_ban_chay'] ?? false) ? 'checked' : '' ?>>
                                            <label class="form-check-label" for="san_pham_ban_chay">
                                                Sản phẩm bán chạy
                                            </label>
                                        </div>
                                    </div>
                                    
                                    <div class="mb-3">
                                        <label for="trang_thai" class="form-label">Trạng thái</label>
                                        <select class="form-select" id="trang_thai" name="trang_thai">
                                            <option value="hoat_dong" <?= ($_POST['trang_thai'] ?? '') == 'hoat_dong' ? 'selected' : '' ?>>
                                                Hoạt động
                                            </option>
                                            <option value="het_hang" <?= ($_POST['trang_thai'] ?? '') == 'het_hang' ? 'selected' : '' ?>>
                                                Hết hàng
                                            </option>
                                            <option value="an" <?= ($_POST['trang_thai'] ?? '') == 'an' ? 'selected' : '' ?>>
                                                Ẩn
                                            </option>
                                        </select>
                                    </div>
                                </div>
                            </div>
                            
                            <!-- Thống kê -->
                            <div class="card mb-3">
                                <div class="card-header">
                                    <h5>Thống kê</h5>
                                </div>
                                <div class="card-body text-center">
                                    <div class="row">
                                        <div class="col-6">
                                            <div class="h4 text-primary"><?= count($variants) ?></div>
                                            <small class="text-muted">Biến thể</small>
                                        </div>
                                        <div class="col-6">
                                            <div class="h4 text-info"><?= $product['luot_xem'] ?></div>
                                            <small class="text-muted">Lượt xem</small>
                                        </div>
                                    </div>
                                    <hr>
                                    <div class="row">
                                        <div class="col-6">
                                            <div class="h4 text-success"><?= $product['so_luong_ban'] ?></div>
                                            <small class="text-muted">Đã bán</small>
                                        </div>
                                        <div class="col-6">
                                            <div class="h4 text-warning"><?= number_format($product['diem_danh_gia_tb'], 1) ?>⭐</div>
                                            <small class="text-muted"><?= $product['so_luong_danh_gia'] ?> đánh giá</small>
                                        </div>
                                    </div>
                                </div>
                            </div>
                            
                            <!-- Nút lưu -->
                            <div class="d-grid gap-2">
                                <button type="submit" class="btn btn-primary btn-lg">
                                    <i class="fas fa-save"></i> Cập nhật sản phẩm
                                </button>
                                <a href="/tktshop/admin/products/" class="btn btn-secondary">Hủy</a>
                            </div>
                        </div>
                    </div>
                </form>

                <!-- Biến thể sản phẩm -->
                <?php if (!empty($variants)): ?>
                    <div class="card mt-4">
                        <div class="card-header d-flex justify-content-between align-items-center">
                            <h5><i class="fas fa-cogs me-2"></i>Biến thể sản phẩm</h5>
                            <a href="/tktshop/admin/products/variants.php?id=<?= $id ?>" class="btn btn-info btn-sm">
                                <i class="fas fa-edit"></i> Quản lý biến thể
                            </a>
                        </div>
                        <div class="card-body">
                            <div class="table-responsive">
                                <table class="table table-sm">
                                    <thead>
                                        <tr>
                                            <th>SKU</th>
                                            <th>Size</th>
                                            <th>Màu</th>
                                            <th>Giá bán</th>
                                            <th>Tồn kho</th>
                                            <th>Đã bán</th>
                                            <th>Trạng thái</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($variants as $variant): ?>
                                            <tr>
                                                <td><code><?= $variant['ma_sku'] ?></code></td>
                                                <td><span class="badge bg-secondary"><?= $variant['kich_co'] ?></span></td>
                                                <td>
                                                    <span class="badge" style="background-color: <?= $variant['ma_mau'] ?>; color: <?= $variant['ma_mau'] == '#FFFFFF' ? '#000' : '#fff' ?>;">
                                                        <?= htmlspecialchars($variant['ten_mau']) ?>
                                                    </span>
                                                </td>
                                                <td><?= formatPrice($variant['gia_ban']) ?></td>
                                                <td>
                                                    <span class="badge bg-<?= $variant['so_luong_ton_kho'] > 5 ? 'success' : ($variant['so_luong_ton_kho'] > 0 ? 'warning' : 'danger') ?>">
                                                        <?= $variant['so_luong_ton_kho'] ?>
                                                    </span>
                                                </td>
                                                <td><?= $variant['so_luong_da_ban'] ?></td>
                                                <td>
                                                    <?php if ($variant['trang_thai'] == 'hoat_dong'): ?>
                                                        <span class="badge bg-success">Hoạt động</span>
                                                    <?php elseif ($variant['trang_thai'] == 'het_hang'): ?>
                                                        <span class="badge bg-danger">Hết hàng</span>
                                                    <?php else: ?>
                                                        <span class="badge bg-secondary">Ẩn</span>
                                                    <?php endif; ?>
                                                </td>
                                            </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
    
    <script>
        // Preview ảnh khi chọn file
        document.getElementById('hinh_anh_chinh').addEventListener('change', function(e) {
            const file = e.target.files[0];
            if (file) {
                const reader = new FileReader();
                reader.onload = function(e) {
                    document.getElementById('preview_img').src = e.target.result;
                    document.getElementById('image_preview').style.display = 'block';
                };
                reader.readAsDataURL(file);
            } else {
                document.getElementById('image_preview').style.display = 'none';
            }
        });
        
        // Auto-generate product code
        document.getElementById('ten_san_pham').addEventListener('input', function() {
            const maField = document.getElementById('ma_san_pham');
            if (!maField.value) {
                const name = this.value.toUpperCase();
                const brand = document.getElementById('thuong_hieu').value.toUpperCase();
                
                if (brand && name) {
                    const code = brand.substring(0, 3) + '_' + name.substring(0, 3).replace(/\s/g, '');
                    maField.value = code;
                }
            }
        });
        
        // Validate promotion dates
        document.getElementById('ngay_ket_thuc_km').addEventListener('change', function() {
            const startDate = document.getElementById('ngay_bat_dau_km').value;
            const endDate = this.value;
            
            if (startDate && endDate && new Date(endDate) <= new Date(startDate)) {
                alert('Ngày kết thúc phải sau ngày bắt đầu!');
                this.value = '';
            }
        });
        
        // Price validation
        document.getElementById('gia_khuyen_mai').addEventListener('input', function() {
            const originalPrice = parseFloat(document.getElementById('gia_goc').value) || 0;
            const salePrice = parseFloat(this.value) || 0;
            
            if (salePrice > 0 && salePrice >= originalPrice) {
                alert('Giá khuyến mãi phải nhỏ hơn giá gốc!');
                this.value = '';
            }
        });
    </script>
</body>
</html>