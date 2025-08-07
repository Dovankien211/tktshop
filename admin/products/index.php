<?php
session_start();
require_once '../../config/database.php';
require_once '../../config/config.php';

// Kiểm tra quyền admin
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    header('Location: ../../admin/login.php');
    exit();
}

$product_id = isset($_GET['product_id']) ? (int)$_GET['product_id'] : 0;
if (!$product_id) {
    setFlashMessage('error', 'Không tìm thấy sản phẩm!');
    header('Location: index.php');
    exit();
}

// Lấy thông tin sản phẩm
$product = null;
try {
    $stmt = $pdo->prepare("SELECT * FROM san_pham_chinh WHERE id = ?");
    $stmt->execute([$product_id]);
    $product = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$product) {
        setFlashMessage('error', 'Không tìm thấy sản phẩm!');
        header('Location: index.php');
        exit();
    }
} catch (PDOException $e) {
    setFlashMessage('error', 'Lỗi database: ' . $e->getMessage());
    header('Location: index.php');
    exit();
}

// Lấy danh sách kích cỡ
$sizes = [];
try {
    $stmt = $pdo->query("SELECT * FROM kich_co WHERE trang_thai = 'hoat_dong' ORDER BY thu_tu_sap_xep ASC");
    $sizes = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    $error = "Lỗi khi tải kích cỡ: " . $e->getMessage();
}

// Lấy danh sách màu sắc
$colors = [];
try {
    $stmt = $pdo->query("SELECT * FROM mau_sac WHERE trang_thai = 'hoat_dong' ORDER BY thu_tu_hien_thi ASC");
    $colors = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    $error = "Lỗi khi tải màu sắc: " . $e->getMessage();
}

// Lấy danh sách biến thể hiện có
$variants = [];
try {
    $stmt = $pdo->prepare("
        SELECT btp.*, kc.kich_co, ms.ten_mau, ms.ma_mau 
        FROM bien_the_san_pham btp
        JOIN kich_co kc ON btp.kich_co_id = kc.id
        JOIN mau_sac ms ON btp.mau_sac_id = ms.id
        WHERE btp.san_pham_id = ?
        ORDER BY kc.thu_tu_sap_xep ASC, ms.thu_tu_hien_thi ASC
    ");
    $stmt->execute([$product_id]);
    $variants = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    $error = "Lỗi khi tải biến thể: " . $e->getMessage();
}

$error = '';
$success = '';

// Xử lý thêm biến thể
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'add_variant') {
    try {
        $kich_co_id = (int)$_POST['kich_co_id'];
        $mau_sac_id = (int)$_POST['mau_sac_id'];
        $gia_ban = (int)$_POST['gia_ban'];
        $gia_so_sanh = !empty($_POST['gia_so_sanh']) ? (int)$_POST['gia_so_sanh'] : null;
        $so_luong_ton_kho = (int)$_POST['so_luong_ton_kho'];
        
        // Kiểm tra trùng lặp biến thể
        $stmt = $pdo->prepare("SELECT COUNT(*) FROM bien_the_san_pham WHERE san_pham_id = ? AND kich_co_id = ? AND mau_sac_id = ?");
        $stmt->execute([$product_id, $kich_co_id, $mau_sac_id]);
        if ($stmt->fetchColumn() > 0) {
            throw new Exception("Biến thể này đã tồn tại!");
        }
        
        // Tạo SKU
        $stmt = $pdo->prepare("SELECT kich_co FROM kich_co WHERE id = ?");
        $stmt->execute([$kich_co_id]);
        $size = $stmt->fetchColumn();
        
        $stmt = $pdo->prepare("SELECT ten_mau FROM mau_sac WHERE id = ?");
        $stmt->execute([$mau_sac_id]);
        $color = $stmt->fetchColumn();
        
        $ma_sku = strtoupper($product['ma_san_pham'] . '-' . $size . '-' . str_replace(' ', '', $color));
        
        // Xử lý upload ảnh biến thể
        $hinh_anh_bien_the = null;
        if (isset($_FILES['hinh_anh_bien_the']) && $_FILES['hinh_anh_bien_the']['error'] === UPLOAD_ERR_OK) {
            $hinh_anh_bien_the = uploadImage($_FILES['hinh_anh_bien_the'], 'products');
        }
        
        // Insert biến thể
        $sql = "INSERT INTO bien_the_san_pham (
                    san_pham_id, kich_co_id, mau_sac_id, ma_sku, gia_ban, gia_so_sanh, 
                    so_luong_ton_kho, hinh_anh_bien_the
                ) VALUES (?, ?, ?, ?, ?, ?, ?, ?)";
        
        $stmt = $pdo->prepare($sql);
        $stmt->execute([
            $product_id, $kich_co_id, $mau_sac_id, $ma_sku, $gia_ban, 
            $gia_so_sanh, $so_luong_ton_kho, $hinh_anh_bien_the
        ]);
        
        $success = "Thêm biến thể thành công!";
        
        // Reload trang để cập nhật danh sách
        header("Location: " . ADMIN_URL . "/products/variants.php?product_id=" . $product_id);
        exit();
        
    } catch (Exception $e) {
        $error = "Lỗi: " . $e->getMessage();
    }
}

// Xử lý xóa biến thể
if (isset($_GET['delete_variant'])) {
    try {
        $variant_id = (int)$_GET['delete_variant'];
        
        $stmt = $pdo->prepare("DELETE FROM bien_the_san_pham WHERE id = ? AND san_pham_id = ?");
        $stmt->execute([$variant_id, $product_id]);
        
        setFlashMessage('success', 'Xóa biến thể thành công!');
        header("Location: " . ADMIN_URL . "/products/variants.php?product_id=" . $product_id);
        exit();
        
    } catch (Exception $e) {
        setFlashMessage('error', 'Lỗi khi xóa biến thể: ' . $e->getMessage());
    }
}
?>

<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Quản lý biến thể - <?php echo htmlspecialchars($product['ten_san_pham']); ?> - TKT Shop Admin</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <style>
        .color-preview {
            width: 20px;
            height: 20px;
            border-radius: 50%;
            display: inline-block;
            border: 1px solid #ddd;
            margin-right: 5px;
        }
        .variant-card {
            transition: all 0.3s ease;
        }
        .variant-card:hover {
            transform: translateY(-2px);
            box-shadow: 0 4px 8px rgba(0,0,0,0.1);
        }
    </style>
</head>
<body>
    <div class="container-fluid">
        <div class="row">
            <!-- Sidebar -->
            <?php include '../layouts/sidebar.php'; ?>
            
            <!-- Main content -->
            <div class="col-md-9 col-lg-10">
                <div class="d-flex justify-content-between align-items-center pt-3 pb-2 mb-3 border-bottom">
                    <div>
                        <h1 class="h2">Quản lý biến thể</h1>
                        <nav aria-label="breadcrumb">
                            <ol class="breadcrumb">
                                <li class="breadcrumb-item"><a href="index.php">Sản phẩm</a></li>
                                <li class="breadcrumb-item active"><?php echo htmlspecialchars($product['ten_san_pham']); ?></li>
                            </ol>
                        </nav>
                    </div>
                    <div>
                        <a href="index.php" class="btn btn-outline-secondary me-2">
                            <i class="fas fa-arrow-left"></i> Quay lại
                        </a>
                        <button type="button" class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#addVariantModal">
                            <i class="fas fa-plus"></i> Thêm biến thể
                        </button>
                    </div>
                </div>

                <!-- Flash Messages -->
                <?php if ($error): ?>
                    <div class="alert alert-danger alert-dismissible fade show">
                        <i class="fas fa-exclamation-triangle"></i> <?php echo $error; ?>
                        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                    </div>
                <?php endif; ?>

                <?php if ($success): ?>
                    <div class="alert alert-success alert-dismissible fade show">
                        <i class="fas fa-check-circle"></i> <?php echo $success; ?>
                        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                    </div>
                <?php endif; ?>

                <?php
                $flash = getFlashMessage();
                if ($flash):
                ?>
                    <div class="alert alert-<?php echo $flash['type'] === 'error' ? 'danger' : $flash['type']; ?> alert-dismissible fade show">
                        <i class="fas fa-<?php echo $flash['type'] === 'error' ? 'exclamation-triangle' : 'check-circle'; ?>"></i> 
                        <?php echo $flash['message']; ?>
                        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                    </div>
                <?php endif; ?>

                <!-- Thông tin sản phẩm -->
                <div class="card mb-4">
                    <div class="card-header">
                        <h5 class="card-title mb-0">
                            <i class="fas fa-info-circle"></i> Thông tin sản phẩm
                        </h5>
                    </div>
                    <div class="card-body">
                        <div class="row">
                            <div class="col-md-2">
                                <?php if ($product['hinh_anh_chinh']): ?>
                                    <img src="<?php echo UPLOAD_URL . $product['hinh_anh_chinh']; ?>" 
                                         alt="<?php echo htmlspecialchars($product['ten_san_pham']); ?>" 
                                         class="img-fluid rounded">
                                <?php else: ?>
                                    <div class="bg-light p-3 text-center rounded">
                                        <i class="fas fa-image fa-3x text-muted"></i>
                                    </div>
                                <?php endif; ?>
                            </div>
                            <div class="col-md-10">
                                <h4><?php echo htmlspecialchars($product['ten_san_pham']); ?></h4>
                                <p class="text-muted mb-2">
                                    <strong>Mã sản phẩm:</strong> <?php echo htmlspecialchars($product['ma_san_pham']); ?> |
                                    <strong>Thương hiệu:</strong> <?php echo htmlspecialchars($product['thuong_hieu']); ?> |
                                    <strong>Giá gốc:</strong> <?php echo formatPrice($product['gia_goc']); ?>
                                </p>
                                <p><?php echo htmlspecialchars($product['mo_ta_ngan']); ?></p>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Danh sách biến thể -->
                <div class="card">
                    <div class="card-header d-flex justify-content-between align-items-center">
                        <h5 class="card-title mb-0">
                            <i class="fas fa-list"></i> Danh sách biến thể (<?php echo count($variants); ?>)
                        </h5>
                    </div>
                    <div class="card-body">
                        <?php if (empty($variants)): ?>
                            <div class="text-center py-5">
                                <i class="fas fa-box-open fa-3x text-muted mb-3"></i>
                                <h5 class="text-muted">Chưa có biến thể nào</h5>
                                <p class="text-muted">Hãy thêm biến thể đầu tiên cho sản phẩm này</p>
                                <button type="button" class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#addVariantModal">
                                    <i class="fas fa-plus"></i> Thêm biến thể
                                </button>
                            </div>
                        <?php else: ?>
                            <div class="row">
                                <?php foreach ($variants as $variant): ?>
                                    <div class="col-lg-4 col-md-6 mb-3">
                                        <div class="card variant-card h-100">
                                            <div class="card-body">
                                                <div class="d-flex justify-content-between align-items-start mb-2">
                                                    <div>
                                                        <h6 class="card-title mb-1">
                                                            Size <?php echo htmlspecialchars($variant['kich_co']); ?>
                                                        </h6>
                                                        <div class="d-flex align-items-center mb-2">
                                                            <span class="color-preview" style="background-color: <?php echo $variant['ma_mau']; ?>"></span>
                                                            <span><?php echo htmlspecialchars($variant['ten_mau']); ?></span>
                                                        </div>
                                                    </div>
                                                    <div class="dropdown">
                                                        <button class="btn btn-sm btn-outline-secondary" type="button" data-bs-toggle="dropdown">
                                                            <i class="fas fa-ellipsis-v"></i>
                                                        </button>
                                                        <ul class="dropdown-menu">
                                                            <li>
                                                                <a class="dropdown-item" href="#" onclick="editVariant(<?php echo $variant['id']; ?>)">
                                                                    <i class="fas fa-edit"></i> Sửa
                                                                </a>
                                                            </li>
                                                            <li>
                                                                <a class="dropdown-item text-danger" href="#" onclick="deleteVariant(<?php echo $variant['id']; ?>)">
                                                                    <i class="fas fa-trash"></i> Xóa
                                                                </a>
                                                            </li>
                                                        </ul>
                                                    </div>
                                                </div>
                                                
                                                <div class="mb-2">
                                                    <small class="text-muted">SKU:</small><br>
                                                    <code><?php echo htmlspecialchars($variant['ma_sku']); ?></code>
                                                </div>
                                                
                                                <div class="mb-2">
                                                    <small class="text-muted">Giá bán:</small><br>
                                                    <strong class="text-primary"><?php echo formatPrice($variant['gia_ban']); ?></strong>
                                                    <?php if ($variant['gia_so_sanh']): ?>
                                                        <small class="text-muted text-decoration-line-through ms-2">
                                                            <?php echo formatPrice($variant['gia_so_sanh']); ?>
                                                        </small>
                                                    <?php endif; ?>
                                                </div>
                                                
                                                <div class="mb-2">
                                                    <small class="text-muted">Tồn kho:</small><br>
                                                    <span class="badge bg-<?php echo $variant['so_luong_ton_kho'] > 0 ? 'success' : 'danger'; ?>">
                                                        <?php echo $variant['so_luong_ton_kho']; ?> sản phẩm
                                                    </span>
                                                </div>
                                                
                                                <div class="mb-2">
                                                    <small class="text-muted">Đã bán:</small> 
                                                    <span class="fw-bold"><?php echo $variant['so_luong_da_ban']; ?></span>
                                                </div>
                                                
                                                <div class="mt-2">
                                                    <span class="badge bg-<?php echo $variant['trang_thai'] === 'hoat_dong' ? 'success' : 'secondary'; ?>">
                                                        <?php 
                                                        switch($variant['trang_thai']) {
                                                            case 'hoat_dong': echo 'Hoạt động'; break;
                                                            case 'het_hang': echo 'Hết hàng'; break;
                                                            case 'an': echo 'Ẩn'; break;
                                                        }
                                                        ?>
                                                    </span>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Modal thêm biến thể -->
    <div class="modal fade" id="addVariantModal" tabindex="-1">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <form method="POST" enctype="multipart/form-data">
                    <input type="hidden" name="action" value="add_variant">
                    <div class="modal-header">
                        <h5 class="modal-title">
                            <i class="fas fa-plus"></i> Thêm biến thể mới
                        </h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                    </div>
                    <div class="modal-body">
                        <div class="row">
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label for="kich_co_id" class="form-label">Kích cỡ *</label>
                                    <select class="form-select" id="kich_co_id" name="kich_co_id" required>
                                        <option value="">Chọn kích cỡ</option>
                                        <?php foreach ($sizes as $size): ?>
                                            <option value="<?php echo $size['id']; ?>">
                                                Size <?php echo htmlspecialchars($size['kich_co']); ?>
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label for="mau_sac_id" class="form-label">Màu sắc *</label>
                                    <select class="form-select" id="mau_sac_id" name="mau_sac_id" required>
                                        <option value="">Chọn màu sắc</option>
                                        <?php foreach ($colors as $color): ?>
                                            <option value="<?php echo $color['id']; ?>" data-color="<?php echo $color['ma_mau']; ?>">
                                                <?php echo htmlspecialchars($color['ten_mau']); ?>
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                            </div>
                        </div>
                        
                        <div class="row">
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label for="gia_ban" class="form-label">Giá bán *</label>
                                    <input type="number" class="form-control" id="gia_ban" name="gia_ban" required>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label for="gia_so_sanh" class="form-label">Giá so sánh</label>
                                    <input type="number" class="form-control" id="gia_so_sanh" name="gia_so_sanh">
                                    <div class="form-text">Giá gốc để hiển thị khuyến mãi</div>
                                </div>
                            </div>
                        </div>
                        
                        <div class="mb-3">
                            <label for="so_luong_ton_kho" class="form-label">Số lượng tồn kho *</label>
                            <input type="number" class="form-control" id="so_luong_ton_kho" name="so_luong_ton_kho" required>
                        </div>
                        
                        <div class="mb-3">
                            <label for="hinh_anh_bien_the" class="form-label">Ảnh biến thể</label>
                            <input type="file" class="form-control" id="hinh_anh_bien_the" name="hinh_anh_bien_the" accept="image/*">
                            <div class="form-text">Ảnh riêng cho biến thể này (tùy chọn)</div>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Hủy</button>
                        <button type="submit" class="btn btn-primary">
                            <i class="fas fa-save"></i> Thêm biến thể
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        function deleteVariant(variantId) {
            if (confirm('Bạn có chắc chắn muốn xóa biến thể này?')) {
                window.location.href = '<?php echo ADMIN_URL; ?>/products/variants.php?product_id=<?php echo $product_id; ?>&delete_variant=' + variantId;
            }
        }
        
        function editVariant(variantId) {
            // TODO: Implement edit functionality
            alert('Chức năng sửa biến thể sẽ được cập nhật trong phiên bản tiếp theo');
        }
        
        // Hiển thị màu sắc trong select option
        document.getElementById('mau_sac_id').addEventListener('change', function() {
            const selectedOption = this.options[this.selectedIndex];
            const color = selectedOption.getAttribute('data-color');
            if (color) {
                this.style.borderLeft = '5px solid ' + color;
            } else {
                this.style.borderLeft = '';
            }
        });
    </script>
</body>
</html>