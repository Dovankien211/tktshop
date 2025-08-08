<?php
// customer/product_detail.php
/**
 * Chi tiết sản phẩm - Ảnh slide + chọn biến thể + đánh giá + sản phẩm liên quan
 * Chức năng: Gallery ảnh, chọn size/màu, thêm giỏ hàng, đánh giá, sản phẩm tương tự
 */
session_start();

require_once '../config/database.php';
require_once '../config/config.php';

$slug = $_GET['slug'] ?? '';
if (empty($slug)) {
    redirect('/customer/products.php');
}

// Lấy thông tin sản phẩm
$stmt = $pdo->prepare("
    SELECT sp.*, dm.ten_danh_muc, dm.slug as danh_muc_slug,
           COALESCE(sp.gia_khuyen_mai, sp.gia_goc) as gia_hien_tai,
           CASE 
               WHEN sp.gia_khuyen_mai IS NOT NULL AND sp.gia_khuyen_mai < sp.gia_goc 
               THEN ROUND(((sp.gia_goc - sp.gia_khuyen_mai) / sp.gia_goc) * 100, 0)
               ELSE 0
           END as phan_tram_giam
    FROM san_pham_chinh sp
    LEFT JOIN danh_muc_giay dm ON sp.danh_muc_id = dm.id
    WHERE sp.slug = ? AND sp.trang_thai = 'hoat_dong'
");
$stmt->execute([$slug]);
$product = $stmt->fetch();

if (!$product) {
    alert('Sản phẩm không tồn tại hoặc đã bị xóa!', 'danger');
    redirect('/customer/products.php');
}

// Cập nhật lượt xem
$pdo->prepare("UPDATE san_pham_chinh SET luot_xem = luot_xem + 1 WHERE id = ?")->execute([$product['id']]);

// Lấy biến thể sản phẩm
$stmt = $pdo->prepare("
    SELECT bsp.*, kc.kich_co, ms.ten_mau, ms.ma_mau
    FROM bien_the_san_pham bsp
    JOIN kich_co kc ON bsp.kich_co_id = kc.id
    JOIN mau_sac ms ON bsp.mau_sac_id = ms.id
    WHERE bsp.san_pham_id = ? AND bsp.trang_thai = 'hoat_dong'
    ORDER BY kc.thu_tu_sap_xep, ms.thu_tu_hien_thi
");
$stmt->execute([$product['id']]);
$variants = $stmt->fetchAll();

// Nhóm biến thể theo size và màu
$sizes = [];
$colors = [];
$variant_matrix = [];

foreach ($variants as $variant) {
    if (!in_array($variant['kich_co'], $sizes)) {
        $sizes[] = $variant['kich_co'];
    }
    if (!isset($colors[$variant['mau_sac_id']])) {
        $colors[$variant['mau_sac_id']] = [
            'id' => $variant['mau_sac_id'],
            'ten_mau' => $variant['ten_mau'],
            'ma_mau' => $variant['ma_mau']
        ];
    }
    $variant_matrix[$variant['kich_co']][$variant['mau_sac_id']] = $variant;
}

// Xử lý AJAX thêm vào giỏ hàng
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['action']) && $_POST['action'] == 'add_to_cart') {
    header('Content-Type: application/json');
    
    $kich_co = $_POST['kich_co'] ?? '';
    $mau_sac_id = (int)($_POST['mau_sac_id'] ?? 0);
    $so_luong = max(1, (int)($_POST['so_luong'] ?? 1));
    
    if (empty($kich_co) || $mau_sac_id == 0) {
        echo json_encode(['success' => false, 'message' => 'Vui lòng chọn size và màu sắc!']);
        exit;
    }
    
    // Tìm biến thể tương ứng
    $selected_variant = null;
    foreach ($variants as $variant) {
        if ($variant['kich_co'] == $kich_co && $variant['mau_sac_id'] == $mau_sac_id) {
            $selected_variant = $variant;
            break;
        }
    }
    
    if (!$selected_variant) {
        echo json_encode(['success' => false, 'message' => 'Biến thể sản phẩm không tồn tại!']);
        exit;
    }
    
    if ($selected_variant['so_luong_ton_kho'] < $so_luong) {
        echo json_encode(['success' => false, 'message' => 'Không đủ hàng trong kho! Còn lại: ' . $selected_variant['so_luong_ton_kho']]);
        exit;
    }
    
    try {
        // Khởi tạo session
        $customer_id = $_SESSION['customer_id'] ?? null;
        $session_id = $customer_id ? null : ($_SESSION['session_id'] ?? session_id());
        
        if (!$session_id && !$customer_id) {
            $_SESSION['session_id'] = session_id();
            $session_id = $_SESSION['session_id'];
        }
        
        // Kiểm tra sản phẩm đã có trong giỏ hàng chưa
        $check_cart = $pdo->prepare("
            SELECT * FROM gio_hang 
            WHERE bien_the_id = ? 
            AND (khach_hang_id = ? OR session_id = ?)
        ");
        $check_cart->execute([$selected_variant['id'], $customer_id, $session_id]);
        $existing_item = $check_cart->fetch();
        
        if ($existing_item) {
            // Cập nhật số lượng
            $new_quantity = $existing_item['so_luong'] + $so_luong;
            if ($new_quantity > $selected_variant['so_luong_ton_kho']) {
                echo json_encode(['success' => false, 'message' => 'Không đủ hàng trong kho! Tối đa có thể mua: ' . $selected_variant['so_luong_ton_kho']]);
                exit;
            }
            
            $pdo->prepare("UPDATE gio_hang SET so_luong = ?, gia_tai_thoi_diem = ? WHERE id = ?")
                ->execute([$new_quantity, $selected_variant['gia_ban'], $existing_item['id']]);
        } else {
            // Thêm mới vào giỏ hàng
            $pdo->prepare("
                INSERT INTO gio_hang (khach_hang_id, session_id, bien_the_id, so_luong, gia_tai_thoi_diem, ngay_them)
                VALUES (?, ?, ?, ?, ?, NOW())
            ")->execute([$customer_id, $session_id, $selected_variant['id'], $so_luong, $selected_variant['gia_ban']]);
        }
        
        // Đếm tổng số sản phẩm trong giỏ hàng
        $count_stmt = $pdo->prepare("SELECT SUM(so_luong) FROM gio_hang WHERE khach_hang_id = ? OR session_id = ?");
        $count_stmt->execute([$customer_id, $session_id]);
        $cart_count = $count_stmt->fetchColumn() ?: 0;
        
        echo json_encode([
            'success' => true, 
            'message' => 'Thêm sản phẩm vào giỏ hàng thành công!',
            'cart_count' => $cart_count
        ]);
        
    } catch (Exception $e) {
        echo json_encode(['success' => false, 'message' => 'Có lỗi xảy ra: ' . $e->getMessage()]);
    }
    exit;
}

// Xử lý gửi đánh giá
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['action']) && $_POST['action'] == 'submit_review') {
    if (!isset($_SESSION['customer_id'])) {
        alert('Bạn cần đăng nhập để đánh giá sản phẩm!', 'warning');
        redirect('/customer/login.php');
    }
    
    $diem_danh_gia = (int)($_POST['rating'] ?? 0);
    $tieu_de = trim($_POST['review_title'] ?? '');
    $noi_dung = trim($_POST['review_content'] ?? '');
    $uu_diem = trim($_POST['pros'] ?? '');
    $nhuoc_diem = trim($_POST['cons'] ?? '');
    
    if ($diem_danh_gia < 1 || $diem_danh_gia > 5) {
        alert('Vui lòng chọn số sao đánh giá!', 'warning');
    } elseif (empty($noi_dung)) {
        alert('Vui lòng nhập nội dung đánh giá!', 'warning');
    } else {
        try {
            // Kiểm tra đã đánh giá chưa
            $check_review = $pdo->prepare("
                SELECT id FROM danh_gia_san_pham 
                WHERE san_pham_id = ? AND khach_hang_id = ?
            ");
            $check_review->execute([$product['id'], $_SESSION['customer_id']]);
            
            if ($check_review->fetch()) {
                alert('Bạn đã đánh giá sản phẩm này rồi!', 'warning');
            } else {
                // Thêm đánh giá mới
                $pdo->prepare("
                    INSERT INTO danh_gia_san_pham (san_pham_id, khach_hang_id, diem_danh_gia, tieu_de, noi_dung, uu_diem, nhuoc_diem, trang_thai, ngay_tao)
                    VALUES (?, ?, ?, ?, ?, ?, ?, 'cho_duyet', NOW())
                ")->execute([$product['id'], $_SESSION['customer_id'], $diem_danh_gia, $tieu_de, $noi_dung, $uu_diem, $nhuoc_diem]);
                
                // Cập nhật điểm trung bình cho sản phẩm
                $update_rating = $pdo->prepare("
                    UPDATE san_pham_chinh SET 
                        diem_danh_gia_tb = (
                            SELECT AVG(diem_danh_gia) 
                            FROM danh_gia_san_pham 
                            WHERE san_pham_id = ? AND trang_thai = 'da_duyet'
                        ),
                        so_luong_danh_gia = (
                            SELECT COUNT(*) 
                            FROM danh_gia_san_pham 
                            WHERE san_pham_id = ? AND trang_thai = 'da_duyet'
                        )
                    WHERE id = ?
                ");
                $update_rating->execute([$product['id'], $product['id'], $product['id']]);
                
                alert('Cảm ơn bạn đã đánh giá! Đánh giá sẽ được hiển thị sau khi được duyệt.', 'success');
            }
        } catch (Exception $e) {
            alert('Có lỗi xảy ra khi gửi đánh giá!', 'danger');
        }
    }
}

// Lấy đánh giá sản phẩm
$stmt = $pdo->prepare("
    SELECT dg.*, nd.ho_ten as ten_khach_hang
    FROM danh_gia_san_pham dg
    JOIN nguoi_dung nd ON dg.khach_hang_id = nd.id
    WHERE dg.san_pham_id = ? AND dg.trang_thai = 'da_duyet'
    ORDER BY dg.ngay_tao DESC
    LIMIT 10
");
$stmt->execute([$product['id']]);
$reviews = $stmt->fetchAll();

// Thống kê đánh giá
$stmt = $pdo->prepare("
    SELECT 
        diem_danh_gia,
        COUNT(*) as so_luong,
        ROUND((COUNT(*) * 100.0 / (SELECT COUNT(*) FROM danh_gia_san_pham WHERE san_pham_id = ? AND trang_thai = 'da_duyet')), 1) as phan_tram
    FROM danh_gia_san_pham 
    WHERE san_pham_id = ? AND trang_thai = 'da_duyet'
    GROUP BY diem_danh_gia
    ORDER BY diem_danh_gia DESC
");
$stmt->execute([$product['id'], $product['id']]);
$rating_stats = $stmt->fetchAll();

// Sản phẩm liên quan
$stmt = $pdo->prepare("
    SELECT sp.*, 
           COALESCE(sp.gia_khuyen_mai, sp.gia_goc) as gia_hien_tai,
           MIN(bsp.gia_ban) as gia_thap_nhat,
           SUM(bsp.so_luong_ton_kho) as tong_ton_kho
    FROM san_pham_chinh sp
    LEFT JOIN bien_the_san_pham bsp ON sp.id = bsp.san_pham_id AND bsp.trang_thai = 'hoat_dong'
    WHERE sp.danh_muc_id = ? AND sp.id != ? AND sp.trang_thai = 'hoat_dong'
    GROUP BY sp.id
    HAVING tong_ton_kho > 0
    ORDER BY sp.luot_xem DESC
    LIMIT 4
");
$stmt->execute([$product['danh_muc_id'], $product['id']]);
$related_products = $stmt->fetchAll();

// Album ảnh
$product_images = [];
if ($product['album_hinh_anh']) {
    $album = json_decode($product['album_hinh_anh'], true);
    if (is_array($album)) {
        foreach ($album as $img) {
            if (!empty($img) && $img !== 'default-product.jpg') {
                $product_images[] = $img;
            }
        }
    }
}

// Thêm ảnh chính vào đầu nếu có
if ($product['hinh_anh_chinh'] && $product['hinh_anh_chinh'] !== 'default-product.jpg') {
    array_unshift($product_images, $product['hinh_anh_chinh']);
}

// Loại bỏ ảnh trùng lặp
$product_images = array_unique($product_images);

$page_title = htmlspecialchars($product['ten_san_pham']) . ' - ' . SITE_NAME;

// Helper functions
function getContrastColor($hexColor) {
    $hexColor = ltrim($hexColor, '#');
    $r = hexdec(substr($hexColor, 0, 2));
    $g = hexdec(substr($hexColor, 2, 2));
    $b = hexdec(substr($hexColor, 4, 2));
    $luminance = (0.299 * $r + 0.587 * $g + 0.114 * $b) / 255;
    return $luminance > 0.5 ? '#000000' : '#ffffff';
}

function getImageUrl($imageName) {
    if (empty($imageName) || $imageName === 'default-product.jpg') {
        return null;
    }
    return "/tktshop/uploads/products/" . $imageName;
}
?>

<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= $page_title ?></title>
    <meta name="description" content="<?= htmlspecialchars($product['mo_ta_ngan'] ?? '') ?>">
    
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    
    <style>
        .product-gallery {
            position: sticky;
            top: 20px;
        }
        
        .main-image {
            width: 100%;
            height: 500px;
            object-fit: cover;
            border-radius: 10px;
            cursor: zoom-in;
        }
        
        .no-image-placeholder {
            width: 100%;
            height: 500px;
            border-radius: 10px;
            background: #f8f9fa;
            display: flex;
            align-items: center;
            justify-content: center;
            border: 2px dashed #dee2e6;
        }
        
        .thumbnail-images {
            display: flex;
            gap: 10px;
            margin-top: 15px;
            overflow-x: auto;
        }
        
        .thumbnail {
            width: 80px;
            height: 80px;
            object-fit: cover;
            border-radius: 8px;
            cursor: pointer;
            opacity: 0.7;
            transition: all 0.3s;
            flex-shrink: 0;
        }
        
        .thumbnail:hover,
        .thumbnail.active {
            opacity: 1;
            border: 2px solid #007bff;
        }
        
        .variant-option {
            border: 2px solid #dee2e6;
            border-radius: 8px;
            padding: 10px 15px;
            margin: 5px;
            cursor: pointer;
            transition: all 0.3s;
            display: inline-block;
        }
        
        .variant-option:hover,
        .variant-option.selected {
            border-color: #007bff;
            background-color: #f8f9ff;
        }
        
        .variant-option.disabled {
            opacity: 0.5;
            cursor: not-allowed;
            background-color: #f8f9fa;
        }
        
        .color-option {
            width: 40px;
            height: 40px;
            border-radius: 50%;
            border: 2px solid #ccc;
            cursor: pointer;
            margin: 5px;
            position: relative;
            transition: all 0.3s;
            display: inline-block;
        }
        
        .color-option:hover,
        .color-option.selected {
            border-color: #007bff;
            transform: scale(1.1);
        }
        
        .color-option.disabled {
            opacity: 0.5;
            cursor: not-allowed;
        }
        
        .price-section {
            background: #f8f9fa;
            border-radius: 10px;
            padding: 20px;
            margin: 20px 0;
        }
        
        .quantity-input {
            max-width: 120px;
        }
        
        .rating-bar {
            height: 8px;
            background-color: #e9ecef;
            border-radius: 4px;
            overflow: hidden;
        }
        
        .rating-fill {
            height: 100%;
            background-color: #ffc107;
            transition: width 0.3s ease;
        }
        
        .review-item {
            border-bottom: 1px solid #eee;
            padding: 20px 0;
        }
        
        .review-item:last-child {
            border-bottom: none;
        }
        
        .star-rating {
            display: flex;
            gap: 5px;
            margin-bottom: 15px;
        }
        
        .star-rating input {
            display: none;
        }
        
        .star-rating label {
            font-size: 24px;
            color: #ddd;
            cursor: pointer;
            transition: color 0.2s;
        }
        
        .star-rating label:hover,
        .star-rating label:hover ~ label,
        .star-rating input:checked ~ label {
            color: #ffc107;
        }
        
        .product-card {
            transition: transform 0.3s, box-shadow 0.3s;
        }
        
        .product-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 5px 15px rgba(0,0,0,0.1);
        }
        
        @media (max-width: 768px) {
            .main-image, .no-image-placeholder {
                height: 300px;
            }
            
            .thumbnail {
                width: 60px;
                height: 60px;
            }
            
            .product-gallery {
                position: static;
            }
        }
        
        .badge-sale {
            position: absolute;
            top: 15px;
            right: 15px;
            z-index: 1;
        }
    </style>
</head>
<body>
    <!-- Header -->
    <?php include 'includes/header.php'; ?>
    
    <div class="container py-4">
        <!-- Breadcrumb -->
        <nav aria-label="breadcrumb" class="mb-4">
            <ol class="breadcrumb">
                <li class="breadcrumb-item"><a href="/tktshop/">Trang chủ</a></li>
                <li class="breadcrumb-item"><a href="/tktshop/customer/products.php">Sản phẩm</a></li>
                <li class="breadcrumb-item"><a href="/tktshop/customer/products.php?category=<?= $product['danh_muc_id'] ?>"><?= htmlspecialchars($product['ten_danh_muc']) ?></a></li>
                <li class="breadcrumb-item active"><?= htmlspecialchars($product['ten_san_pham']) ?></li>
            </ol>
        </nav>
        
        <?php showAlert(); ?>
        
        <div class="row">
            <!-- Product Gallery -->
            <div class="col-lg-6">
                <div class="product-gallery">
                    <div class="position-relative">
                        <?php if (!empty($product_images)): ?>
                            <img id="mainImage" 
                                 src="<?= getImageUrl($product_images[0]) ?>" 
                                 alt="<?= htmlspecialchars($product['ten_san_pham']) ?>"
                                 class="main-image"
                                 onerror="this.src='tktshop/uploads/assets/Ultraboost.jpg'"
                        <?php else: ?>
                            <div class="no-image-placeholder">
                                <div class="text-center text-muted">
                                    <i class="fas fa-image fa-3x mb-3"></i>
                                    <p>Hình ảnh sản phẩm<br>đang được cập nhật</p>
                                </div>
                            </div>
                        <?php endif; ?>
                        
                        <?php if ($product['phan_tram_giam'] > 0): ?>
                            <span class="badge bg-danger badge-sale fs-6">-<?= $product['phan_tram_giam'] ?>%</span>
                        <?php endif; ?>
                        
                        <?php if ($product['san_pham_moi']): ?>
                            <span class="badge bg-success position-absolute" style="top: 15px; left: 15px;">Mới</span>
                        <?php endif; ?>
                        
                        <?php if ($product['san_pham_noi_bat']): ?>
                            <span class="badge bg-warning text-dark position-absolute" style="top: 60px; left: 15px;">Hot</span>
                        <?php endif; ?>
                    </div>
                    
                    <?php if (count($product_images) > 1): ?>
                        <div class="thumbnail-images">
                            <?php foreach ($product_images as $index => $image): ?>
                                <img src="<?= getImageUrl($image) ?>" 
                                     alt="<?= htmlspecialchars($product['ten_san_pham']) ?> - Ảnh <?= $index + 1 ?>"
                                     class="thumbnail <?= $index === 0 ? 'active' : '' ?>"
                                     onclick="changeMainImage('<?= $image ?>', this)"
                                     onerror="this.style.display='none'">
                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
            
            <!-- Product Information -->
            <div class="col-lg-6">
                <div class="product-info">
                    <!-- Product Title -->
                    <div class="mb-3">
                        <?php if ($product['thuong_hieu']): ?>
                            <div class="text-muted mb-2">
                                <i class="fas fa-tag me-1"></i>
                                <?= htmlspecialchars($product['thuong_hieu']) ?>
                            </div>
                        <?php endif; ?>
                        <h1 class="h3"><?= htmlspecialchars($product['ten_san_pham']) ?></h1>
                        <div class="text-muted small">
                            Mã sản phẩm: <strong><?= htmlspecialchars($product['ma_san_pham']) ?></strong>
                        </div>
                    </div>
                    
                    <!-- Rating -->
                    <div class="d-flex align-items-center mb-3">
                        <div class="text-warning me-2">
                            <?php for ($i = 1; $i <= 5; $i++): ?>
                                <i class="fas fa-star<?= $i <= floor($product['diem_danh_gia_tb']) ? '' : ' text-muted' ?>"></i>
                            <?php endfor; ?>
                        </div>
                        <span class="me-2"><?= number_format($product['diem_danh_gia_tb'], 1) ?></span>
                        <span class="text-muted">
                            (<?= $product['so_luong_danh_gia'] ?> đánh giá) •
                            <?= $product['luot_xem'] ?> lượt xem •
                            <?= $product['so_luong_ban'] ?> đã bán
                        </span>
                    </div>
                    
                    <!-- Price -->
                    <div class="price-section">
                        <div class="d-flex align-items-center gap-3 flex-wrap">
                            <?php if ($product['gia_khuyen_mai'] && $product['gia_khuyen_mai'] < $product['gia_goc']): ?>
                                <div class="h4 text-danger mb-0"><?= formatPrice($product['gia_khuyen_mai']) ?></div>
                                <div class="text-muted text-decoration-line-through"><?= formatPrice($product['gia_goc']) ?></div>
                                <div class="badge bg-danger">Tiết kiệm <?= formatPrice($product['gia_goc'] - $product['gia_khuyen_mai']) ?></div>
                            <?php else: ?>
                                <div class="h4 text-primary mb-0"><?= formatPrice($product['gia_goc']) ?></div>
                            <?php endif; ?>
                        </div>
                    </div>
                    
                    <!-- Short Description -->
                    <?php if ($product['mo_ta_ngan']): ?>
                        <div class="mb-4">
                            <p class="text-muted"><?= nl2br(htmlspecialchars($product['mo_ta_ngan'])) ?></p>
                        </div>
                    <?php endif; ?>
                    
                    <!-- Variant Selection Form -->
                    <form method="POST" id="addToCartForm">
                        <!-- Size Selection -->
                        <?php if (!empty($sizes)): ?>
                            <div class="mb-4">
                                <label class="form-label fw-bold">Kích cỡ:</label>
                                <div id="sizeOptions">
                                    <?php foreach ($sizes as $size): ?>
                                        <span class="variant-option size-option" data-size="<?= $size ?>">
                                            <?= htmlspecialchars($size) ?>
                                        </span>
                                    <?php endforeach; ?>
                                </div>
                                <input type="hidden" name="kich_co" id="selectedSize">
                            </div>
                        <?php endif; ?>
                        
                        <!-- Color Selection -->
                        <?php if (!empty($colors)): ?>
                            <div class="mb-4">
                                <label class="form-label fw-bold">Màu sắc:</label>
                                <div class="d-flex align-items-center gap-2 flex-wrap">
                                    <div id="colorOptions">
                                        <?php foreach ($colors as $color): ?>
                                            <div class="color-option" 
                                                 data-color-id="<?= $color['id'] ?>"
                                                 style="background-color: <?= $color['ma_mau'] ?>"
                                                 title="<?= htmlspecialchars($color['ten_mau']) ?>">
                                            </div>
                                        <?php endforeach; ?>
                                    </div>
                                    <span id="selectedColorName" class="ms-2 text-muted"></span>
                                </div>
                                <input type="hidden" name="mau_sac_id" id="selectedColorId">
                            </div>
                        <?php endif; ?>
                        
                        <!-- Stock Status -->
                        <div class="mb-4">
                            <div id="stockStatus" class="text-muted">
                                <i class="fas fa-info-circle me-1"></i>
                                Chọn size và màu để xem tình trạng kho
                            </div>
                        </div>
                        
                        <!-- Quantity -->
                        <div class="mb-4">
                            <label class="form-label fw-bold">Số lượng:</label>
                            <div class="d-flex align-items-center gap-3">
                                <div class="input-group quantity-input">
                                    <button class="btn btn-outline-secondary" type="button" onclick="changeQuantity(-1)">-</button>
                                    <input type="number" class="form-control text-center" name="so_luong" id="quantity" value="1" min="1" max="99">
                                    <button class="btn btn-outline-secondary" type="button" onclick="changeQuantity(1)">+</button>
                                </div>
                                <small class="text-muted" id="maxQuantityText"></small>
                            </div>
                        </div>
                        
                        <!-- Add to Cart Button -->
                        <div class="d-grid gap-2 d-md-flex">
                            <button type="button" name="add_to_cart" class="btn btn-primary btn-lg flex-grow-1" id="addToCartBtn" onclick="addToCartAjax()" disabled>
                                <i class="fas fa-shopping-cart me-2"></i>
                                Thêm vào giỏ hàng
                            </button>
                            <button type="button" class="btn btn-outline-danger btn-lg" onclick="addToWishlist(<?= $product['id'] ?>)">
                                <i class="fas fa-heart"></i>
                            </button>
                        </div>
                        
                        <!-- Hidden fields for AJAX -->
                        <input type="hidden" name="action" value="add_to_cart">
                    </form>
                    
                    <!-- Product Info -->
                    <div class="mt-4">
                        <div class="row g-3">
                            <div class="col-6">
                                <div class="d-flex align-items-center text-muted">
                                    <i class="fas fa-truck me-2"></i>
                                    <small>Miễn phí vận chuyển</small>
                                </div>
                            </div>
                            <div class="col-6">
                                <div class="d-flex align-items-center text-muted">
                                    <i class="fas fa-undo me-2"></i>
                                    <small>Đổi trả 7 ngày</small>
                                </div>
                            </div>
                            <div class="col-6">
                                <div class="d-flex align-items-center text-muted">
                                    <i class="fas fa-shield-alt me-2"></i>
                                    <small>Bảo hành chính hãng</small>
                                </div>
                            </div>
                            <div class="col-6">
                                <div class="d-flex align-items-center text-muted">
                                    <i class="fas fa-headset me-2"></i>
                                    <small>Hỗ trợ 24/7</small>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        
        <!-- Product Details Tabs -->
        <div class="row mt-5">
            <div class="col-12">
                <ul class="nav nav-tabs" id="productTabs" role="tablist">
                    <li class="nav-item" role="presentation">
                        <button class="nav-link active" id="description-tab" data-bs-toggle="tab" data-bs-target="#description" type="button" role="tab">
                            Mô tả sản phẩm
                        </button>
                    </li>
                    <li class="nav-item" role="presentation">
                        <button class="nav-link" id="specifications-tab" data-bs-toggle="tab" data-bs-target="#specifications" type="button" role="tab">
                            Thông số kỹ thuật
                        </button>
                    </li>
                    <li class="nav-item" role="presentation">
                        <button class="nav-link" id="reviews-tab" data-bs-toggle="tab" data-bs-target="#reviews" type="button" role="tab">
                            Đánh giá (<?= count($reviews) ?>)
                        </button>
                    </li>
                </ul>
                
                <div class="tab-content" id="productTabsContent">
                    <!-- Description Tab -->
                    <div class="tab-pane fade show active" id="description" role="tabpanel">
                        <div class="p-4">
                            <?php if ($product['mo_ta_chi_tiet']): ?>
                                <div class="product-description">
                                    <?= $product['mo_ta_chi_tiet'] ?>
                                </div>
                            <?php else: ?>
                                <p class="text-muted">Chưa có mô tả chi tiết cho sản phẩm này.</p>
                            <?php endif; ?>
                        </div>
                    </div>
                    
                    <!-- Specifications Tab -->
                    <div class="tab-pane fade" id="specifications" role="tabpanel">
                        <div class="p-4">
                            <div class="table-responsive">
                                <table class="table table-borderless">
                                    <?php if ($product['thuong_hieu']): ?>
                                        <tr>
                                            <td class="fw-bold" style="width: 200px;">Thương hiệu:</td>
                                            <td><?= htmlspecialchars($product['thuong_hieu']) ?></td>
                                        </tr>
                                    <?php endif; ?>
                                    <tr>
                                        <td class="fw-bold">Mã sản phẩm:</td>
                                        <td><?= htmlspecialchars($product['ma_san_pham']) ?></td>
                                    </tr>
                                    <tr>
                                        <td class="fw-bold">Danh mục:</td>
                                        <td><?= htmlspecialchars($product['ten_danh_muc']) ?></td>
                                    </tr>
                                    <?php if (!empty($sizes)): ?>
                                        <tr>
                                            <td class="fw-bold">Kích cỡ có sẵn:</td>
                                            <td><?= implode(', ', $sizes) ?></td>
                                        </tr>
                                    <?php endif; ?>
                                    <?php if (!empty($colors)): ?>
                                        <tr>
                                            <td class="fw-bold">Màu sắc có sẵn:</td>
                                            <td>
                                                <?php foreach ($colors as $color): ?>
                                                    <span class="badge me-1" style="background-color: <?= $color['ma_mau'] ?>; color: <?= getContrastColor($color['ma_mau']) ?>;">
                                                        <?= htmlspecialchars($color['ten_mau']) ?>
                                                    </span>
                                                <?php endforeach; ?>
                                            </td>
                                        </tr>
                                    <?php endif; ?>
                                    <tr>
                                        <td class="fw-bold">Trạng thái:</td>
                                        <td>
                                            <?php if (array_sum(array_column($variants, 'so_luong_ton_kho')) > 0): ?>
                                                <span class="badge bg-success">Còn hàng</span>
                                            <?php else: ?>
                                                <span class="badge bg-danger">Hết hàng</span>
                                            <?php endif; ?>
                                        </td>
                                    </tr>
                                </table>
                            </div>
                        </div>
                    </div>
                    
                    <!-- Reviews Tab -->
                    <div class="tab-pane fade" id="reviews" role="tabpanel">
                        <div class="p-4">
                            <!-- Review Form -->
                            <?php if (isset($_SESSION['customer_id'])): ?>
                                <div class="card mb-4">
                                    <div class="card-header">
                                        <h5 class="mb-0">Viết đánh giá của bạn</h5>
                                    </div>
                                    <div class="card-body">
                                        <form method="POST">
                                            <input type="hidden" name="action" value="submit_review">
                                            
                                            <!-- Rating Stars -->
                                            <div class="mb-3">
                                                <label class="form-label fw-bold">Đánh giá của bạn:</label>
                                                <div class="star-rating">
                                                    <input type="radio" id="star5" name="rating" value="5">
                                                    <label for="star5" title="5 sao"><i class="fas fa-star"></i></label>
                                                    <input type="radio" id="star4" name="rating" value="4">
                                                    <label for="star4" title="4 sao"><i class="fas fa-star"></i></label>
                                                    <input type="radio" id="star3" name="rating" value="3">
                                                    <label for="star3" title="3 sao"><i class="fas fa-star"></i></label>
                                                    <input type="radio" id="star2" name="rating" value="2">
                                                    <label for="star2" title="2 sao"><i class="fas fa-star"></i></label>
                                                    <input type="radio" id="star1" name="rating" value="1">
                                                    <label for="star1" title="1 sao"><i class="fas fa-star"></i></label>
                                                </div>
                                            </div>
                                            
                                            <!-- Review Title -->
                                            <div class="mb-3">
                                                <label for="review_title" class="form-label fw-bold">Tiêu đề (tùy chọn):</label>
                                                <input type="text" class="form-control" id="review_title" name="review_title" 
                                                       placeholder="Ví dụ: Sản phẩm rất tốt, đáng mua">
                                            </div>
                                            
                                            <!-- Review Content -->
                                            <div class="mb-3">
                                                <label for="review_content" class="form-label fw-bold">Nội dung đánh giá <span class="text-danger">*</span>:</label>
                                                <textarea class="form-control" id="review_content" name="review_content" rows="4" 
                                                          placeholder="Chia sẻ trải nghiệm của bạn về sản phẩm này..." required></textarea>
                                            </div>
                                            
                                            <!-- Pros -->
                                            <div class="mb-3">
                                                <label for="pros" class="form-label fw-bold">Ưu điểm (tùy chọn):</label>
                                                <textarea class="form-control" id="pros" name="pros" rows="2" 
                                                          placeholder="Những điểm tốt của sản phẩm..."></textarea>
                                            </div>
                                            
                                            <!-- Cons -->
                                            <div class="mb-3">
                                                <label for="cons" class="form-label fw-bold">Nhược điểm (tùy chọn):</label>
                                                <textarea class="form-control" id="cons" name="cons" rows="2" 
                                                          placeholder="Những điểm cần cải thiện..."></textarea>
                                            </div>
                                            
                                            <button type="submit" class="btn btn-primary">
                                                <i class="fas fa-paper-plane me-2"></i>
                                                Gửi đánh giá
                                            </button>
                                        </form>
                                    </div>
                                </div>
                            <?php else: ?>
                                <div class="alert alert-info">
                                    <i class="fas fa-info-circle me-2"></i>
                                    <a href="/tktshop/customer/login.php" class="alert-link">Đăng nhập</a> để viết đánh giá sản phẩm.
                                </div>
                            <?php endif; ?>
                            
                            <?php if (!empty($reviews)): ?>
                                <!-- Rating Summary -->
                                <div class="row mb-4">
                                    <div class="col-md-4">
                                        <div class="text-center">
                                            <div class="display-4 text-warning"><?= number_format($product['diem_danh_gia_tb'], 1) ?></div>
                                            <div class="text-warning mb-2">
                                                <?php for ($i = 1; $i <= 5; $i++): ?>
                                                    <i class="fas fa-star<?= $i <= floor($product['diem_danh_gia_tb']) ? '' : ' text-muted' ?>"></i>
                                                <?php endfor; ?>
                                            </div>
                                            <div class="text-muted"><?= $product['so_luong_danh_gia'] ?> đánh giá</div>
                                        </div>
                                    </div>
                                    <div class="col-md-8">
                                        <?php for ($rating = 5; $rating >= 1; $rating--): ?>
                                            <?php
                                            $count = 0;
                                            $percentage = 0;
                                            foreach ($rating_stats as $stat) {
                                                if ($stat['diem_danh_gia'] == $rating) {
                                                    $count = $stat['so_luong'];
                                                    $percentage = $stat['phan_tram'];
                                                    break;
                                                }
                                            }
                                            ?>
                                            <div class="d-flex align-items-center mb-2">
                                                <div class="me-2" style="width: 60px;">
                                                    <small><?= $rating ?> <i class="fas fa-star text-warning"></i></small>
                                                </div>
                                                <div class="flex-grow-1 me-2">
                                                    <div class="rating-bar">
                                                        <div class="rating-fill" style="width: <?= $percentage ?>%"></div>
                                                    </div>
                                                </div>
                                                <div style="width: 50px;">
                                                    <small class="text-muted"><?= $count ?></small>
                                                </div>
                                            </div>
                                        <?php endfor; ?>
                                    </div>
                                </div>
                                
                                <!-- Reviews List -->
                                <div class="reviews-list">
                                    <?php foreach ($reviews as $review): ?>
                                        <div class="review-item">
                                            <div class="d-flex justify-content-between align-items-start mb-2">
                                                <div>
                                                    <strong><?= htmlspecialchars($review['ten_khach_hang']) ?></strong>
                                                    <div class="text-warning">
                                                        <?php for ($i = 1; $i <= 5; $i++): ?>
                                                            <i class="fas fa-star<?= $i <= $review['diem_danh_gia'] ? '' : ' text-muted' ?> small"></i>
                                                        <?php endfor; ?>
                                                    </div>
                                                </div>
                                                <small class="text-muted"><?= date('d/m/Y', strtotime($review['ngay_tao'])) ?></small>
                                            </div>
                                            
                                            <?php if ($review['tieu_de']): ?>
                                                <h6 class="mb-2"><?= htmlspecialchars($review['tieu_de']) ?></h6>
                                            <?php endif; ?>
                                            
                                            <p class="mb-2"><?= nl2br(htmlspecialchars($review['noi_dung'])) ?></p>
                                            
                                            <?php if ($review['uu_diem']): ?>
                                                <div class="mb-2">
                                                    <strong class="text-success">Ưu điểm:</strong>
                                                    <p class="mb-0 text-success"><?= nl2br(htmlspecialchars($review['uu_diem'])) ?></p>
                                                </div>
                                            <?php endif; ?>
                                            
                                            <?php if ($review['nhuoc_diem']): ?>
                                                <div class="mb-2">
                                                    <strong class="text-warning">Nhược điểm:</strong>
                                                    <p class="mb-0 text-warning"><?= nl2br(htmlspecialchars($review['nhuoc_diem'])) ?></p>
                                                </div>
                                            <?php endif; ?>
                                            
                                            <?php if ($review['phan_hoi_admin']): ?>
                                                <div class="admin-reply mt-3 p-3 bg-light rounded">
                                                    <strong class="text-primary">Phản hồi từ Shop:</strong>
                                                    <p class="mb-0 mt-1"><?= nl2br(htmlspecialchars($review['phan_hoi_admin'])) ?></p>
                                                    <small class="text-muted">
                                                        <?= date('d/m/Y H:i', strtotime($review['ngay_phan_hoi'])) ?>
                                                    </small>
                                                </div>
                                            <?php endif; ?>
                                        </div>
                                    <?php endforeach; ?>
                                </div>
                            <?php else: ?>
                                <div class="text-center py-5">
                                    <i class="fas fa-star-o fa-3x text-muted mb-3"></i>
                                    <p class="text-muted">Chưa có đánh giá nào cho sản phẩm này.</p>
                                    <p class="text-muted">Hãy là người đầu tiên đánh giá sản phẩm!</p>
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        
        <!-- Related Products -->
        <?php if (!empty($related_products)): ?>
            <div class="row mt-5">
                <div class="col-12">
                    <h3 class="mb-4">Sản phẩm liên quan</h3>
                    <div class="row">
                        <?php foreach ($related_products as $related): ?>
                            <div class="col-lg-3 col-md-4 col-sm-6 mb-4">
                                <div class="card h-100 product-card">
                                    <div class="position-relative">
                                        <img src="/tktshop/uploads/products/Ultraboost.jpg"<?= $related['hinh_anh_chinh'] ?: 'default-product.jpg' ?>" 
                                             class="card-img-top" 
                                             alt="<?= htmlspecialchars($related['ten_san_pham']) ?>"
                                             style="height: 200px; object-fit: cover;"
                                             onerror="this.src='/tktshop/assets/images/no-image.jpg'">
                                        
                                        <?php if ($related['gia_khuyen_mai'] && $related['gia_khuyen_mai'] < $related['gia_goc']): ?>
                                            <?php $discount = round((($related['gia_goc'] - $related['gia_khuyen_mai']) / $related['gia_goc']) * 100); ?>
                                            <span class="badge bg-danger position-absolute" style="top: 10px; right: 10px;">
                                                -<?= $discount ?>%
                                            </span>
                                        <?php endif; ?>
                                    </div>
                                    
                                    <div class="card-body d-flex flex-column">
                                        <h6 class="card-title">
                                            <a href="/tktshop/customer/product_detail.php?slug=<?= $related['slug'] ?>" 
                                               class="text-decoration-none text-dark">
                                                <?= htmlspecialchars($related['ten_san_pham']) ?>
                                            </a>
                                        </h6>
                                        
                                        <div class="text-warning mb-2">
                                            <?php for ($i = 1; $i <= 5; $i++): ?>
                                                <i class="fas fa-star<?= $i <= floor($related['diem_danh_gia_tb']) ? '' : ' text-muted' ?> small"></i>
                                            <?php endfor; ?>
                                            <small class="text-muted ms-1">(<?= $related['so_luong_danh_gia'] ?>)</small>
                                        </div>
                                        
                                        <div class="mt-auto">
                                            <div class="d-flex align-items-center justify-content-between">
                                                <div>
                                                    <?php if ($related['gia_khuyen_mai'] && $related['gia_khuyen_mai'] < $related['gia_goc']): ?>
                                                        <div class="fw-bold text-danger"><?= formatPrice($related['gia_khuyen_mai']) ?></div>
                                                        <small class="text-muted text-decoration-line-through"><?= formatPrice($related['gia_goc']) ?></small>
                                                    <?php else: ?>
                                                        <div class="fw-bold text-primary"><?= formatPrice($related['gia_goc']) ?></div>
                                                    <?php endif; ?>
                                                </div>
                                                <div>
                                                    <?php if ($related['tong_ton_kho'] > 0): ?>
                                                        <small class="text-success">Còn hàng</small>
                                                    <?php else: ?>
                                                        <small class="text-danger">Hết hàng</small>
                                                    <?php endif; ?>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </div>
            </div>
        <?php endif; ?>
    </div>
    
    <!-- Footer -->
    <?php include 'includes/footer.php'; ?>
    
    <!-- Scripts -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
    
    <script>
        // Variant matrix for JavaScript
        const variantMatrix = <?= json_encode($variant_matrix) ?>;
        const colors = <?= json_encode(array_values($colors)) ?>;
        
        let selectedSize = null;
        let selectedColorId = null;
        let currentVariant = null;
        
        // Change main image
        function changeMainImage(imageSrc, thumbnail) {
            const mainImg = document.getElementById('mainImage');
            if (mainImg) {
                mainImg.src = '/tktshop/uploads/products/' + imageSrc;
                
                // Update thumbnail active state
                document.querySelectorAll('.thumbnail').forEach(t => t.classList.remove('active'));
                thumbnail.classList.add('active');
            }
        }
        
        // Size selection
        document.querySelectorAll('.size-option').forEach(option => {
            option.addEventListener('click', function() {
                const size = this.dataset.size;
                selectSize(size, this);
            });
        });
        
        function selectSize(size, element) {
            selectedSize = size;
            document.getElementById('selectedSize').value = size;
            
            // Update UI
            document.querySelectorAll('.size-option').forEach(opt => opt.classList.remove('selected'));
            element.classList.add('selected');
            
            updateAvailableColors();
            updateVariantInfo();
        }
        
        // Color selection
        document.querySelectorAll('.color-option').forEach(option => {
            option.addEventListener('click', function() {
                const colorId = parseInt(this.dataset.colorId);
                selectColor(colorId, this);
            });
        });
        
        function selectColor(colorId, element) {
            selectedColorId = colorId;
            document.getElementById('selectedColorId').value = colorId;
            
            // Update UI
            document.querySelectorAll('.color-option').forEach(opt => opt.classList.remove('selected'));
            element.classList.add('selected');
            
            // Show color name
            const color = colors.find(c => c.id == colorId);
            document.getElementById('selectedColorName').textContent = color ? color.ten_mau : '';
            
            updateAvailableSizes();
            updateVariantInfo();
        }
        
        function updateAvailableColors() {
            if (!selectedSize) return;
            
            document.querySelectorAll('.color-option').forEach(colorOption => {
                const colorId = parseInt(colorOption.dataset.colorId);
                const hasVariant = variantMatrix[selectedSize] && variantMatrix[selectedSize][colorId];
                
                if (hasVariant && variantMatrix[selectedSize][colorId].so_luong_ton_kho > 0) {
                    colorOption.classList.remove('disabled');
                } else {
                    colorOption.classList.add('disabled');
                }
            });
        }
        
        function updateAvailableSizes() {
            if (!selectedColorId) return;
            
            document.querySelectorAll('.size-option').forEach(sizeOption => {
                const size = sizeOption.dataset.size;
                const hasVariant = variantMatrix[size] && variantMatrix[size][selectedColorId];
                
                if (hasVariant && variantMatrix[size][selectedColorId].so_luong_ton_kho > 0) {
                    sizeOption.classList.remove('disabled');
                } else {
                    sizeOption.classList.add('disabled');
                }
            });
        }
        
        function updateVariantInfo() {
            const stockStatus = document.getElementById('stockStatus');
            const addToCartBtn = document.getElementById('addToCartBtn');
            const quantityInput = document.getElementById('quantity');
            const maxQuantityText = document.getElementById('maxQuantityText');
            
            if (selectedSize && selectedColorId) {
                currentVariant = variantMatrix[selectedSize] && variantMatrix[selectedSize][selectedColorId];
                
                if (currentVariant) {
                    const stock = currentVariant.so_luong_ton_kho;
                    
                    if (stock > 0) {
                        stockStatus.innerHTML = '<i class="fas fa-check-circle text-success me-1"></i>Còn ' + stock + ' sản phẩm';
                        addToCartBtn.disabled = false;
                        quantityInput.max = stock;
                        maxQuantityText.textContent = 'Tối đa: ' + stock;
                        
                        // Update quantity if it exceeds stock
                        if (parseInt(quantityInput.value) > stock) {
                            quantityInput.value = stock;
                        }
                    } else {
                        stockStatus.innerHTML = '<i class="fas fa-times-circle text-danger me-1"></i>Hết hàng';
                        addToCartBtn.disabled = true;
                        quantityInput.max = 0;
                        maxQuantityText.textContent = '';
                    }
                } else {
                    stockStatus.innerHTML = '<i class="fas fa-times-circle text-danger me-1"></i>Biến thể không tồn tại';
                    addToCartBtn.disabled = true;
                }
            } else {
                stockStatus.innerHTML = '<i class="fas fa-info-circle me-1"></i>Chọn size và màu để xem tình trạng kho';
                addToCartBtn.disabled = true;
                maxQuantityText.textContent = '';
            }
        }
        
        // Quantity controls
        function changeQuantity(delta) {
            const quantityInput = document.getElementById('quantity');
            const currentValue = parseInt(quantityInput.value);
            const newValue = Math.max(1, Math.min(parseInt(quantityInput.max) || 99, currentValue + delta));
            quantityInput.value = newValue;
        }
        
        // AJAX Add to Cart
        function addToCartAjax() {
            console.log('🛒 Debug: Bắt đầu thêm vào giỏ hàng...');
            console.log('Size:', selectedSize, 'Color:', selectedColorId);
            
            if (!selectedSize || !selectedColorId) {
                showToast('Vui lòng chọn size và màu sắc!', 'error');
                return;
            }
            
            if (!currentVariant || currentVariant.so_luong_ton_kho <= 0) {
                showToast('Sản phẩm đã hết hàng!', 'error');
                return;
            }
            
            const quantity = parseInt(document.getElementById('quantity').value);
            if (quantity > currentVariant.so_luong_ton_kho) {
                showToast('Số lượng vượt quá tồn kho!', 'error');
                return;
            }
            
            // Show loading
            const btn = document.getElementById('addToCartBtn');
            const originalText = btn.innerHTML;
            btn.innerHTML = '<i class="fas fa-spinner fa-spin me-2"></i>Đang thêm...';
            btn.disabled = true;
            
            // Prepare data
            const formData = new FormData();
            formData.append('action', 'add_to_cart');
            formData.append('kich_co', selectedSize);
            formData.append('mau_sac_id', selectedColorId);
            formData.append('so_luong', quantity);
            
            console.log('📤 Sending:', {
                action: 'add_to_cart',
                kich_co: selectedSize,
                mau_sac_id: selectedColorId,
                so_luong: quantity
            });
            
            // Send AJAX request
            fetch(window.location.href, {
                method: 'POST',
                body: formData
            })
            .then(response => {
                console.log('Response status:', response.status);
                return response.text();
            })
            .then(text => {
                console.log('Raw response:', text);
                
                try {
                    const data = JSON.parse(text);
                    console.log('Parsed data:', data);
                    
                    if (data.success) {
                        showToast(data.message, 'success');
                        if (data.cart_count) {
                            updateCartCount(data.cart_count);
                        }
                    } else {
                        showToast(data.message, 'error');
                    }
                } catch (e) {
                    console.error('JSON parse error:', e);
                    showToast('Lỗi server: ' + text.substring(0, 100), 'error');
                }
            })
            .catch(error => {
                console.error('Error:', error);
                showToast('Có lỗi xảy ra: ' + error.message, 'error');
            })
            .finally(() => {
                btn.innerHTML = originalText;
                btn.disabled = selectedSize && selectedColorId && currentVariant && currentVariant.so_luong_ton_kho > 0 ? false : true;
            });
        }
        
        // Show toast notification
        function showToast(message, type = 'info') {
            // Create toast container if it doesn't exist
            let toastContainer = document.getElementById('toastContainer');
            if (!toastContainer) {
                toastContainer = document.createElement('div');
                toastContainer.id = 'toastContainer';
                toastContainer.className = 'toast-container position-fixed top-0 end-0 p-3';
                toastContainer.style.zIndex = '9999';
                document.body.appendChild(toastContainer);
            }
            
            const toastId = 'toast-' + Date.now();
            const bgClass = type === 'success' ? 'bg-success' : type === 'error' ? 'bg-danger' : 'bg-info';
            const iconClass = type === 'success' ? 'fa-check-circle' : type === 'error' ? 'fa-exclamation-circle' : 'fa-info-circle';
            
            const toastHtml = `
                <div id="${toastId}" class="toast align-items-center text-white ${bgClass} border-0" role="alert">
                    <div class="d-flex">
                        <div class="toast-body">
                            <i class="fas ${iconClass} me-2"></i>
                            ${message}
                        </div>
                        <button type="button" class="btn-close btn-close-white me-2 m-auto" data-bs-dismiss="toast"></button>
                    </div>
                </div>
            `;
            
            toastContainer.insertAdjacentHTML('beforeend', toastHtml);
            const toast = new bootstrap.Toast(document.getElementById(toastId));
            toast.show();
            
            // Remove toast element after it hides
            setTimeout(() => {
                const toastElement = document.getElementById(toastId);
                if (toastElement) {
                    toastElement.remove();
                }
            }, 5000);
        }
        
        // Update cart count in header
        function updateCartCount(count) {
            const cartCountElements = document.querySelectorAll('#cart-count, [id$="cart-count"]');
            cartCountElements.forEach(element => {
                element.textContent = count;
                element.style.display = count > 0 ? 'inline' : 'none';
            });
        }
        
        // Wishlist function
        function addToWishlist(productId) {
            showToast('Tính năng yêu thích sẽ được cập nhật sớm!', 'info');
        }
        
        // Star rating functionality
        document.addEventListener('DOMContentLoaded', function() {
            // Auto-select first available variant if only one option
            if (document.querySelectorAll('.size-option').length === 1) {
                document.querySelector('.size-option').click();
            }
            if (document.querySelectorAll('.color-option').length === 1) {
                document.querySelector('.color-option').click();
            }
            
            // Star rating hover effect
            const starRating = document.querySelector('.star-rating');
            if (starRating) {
                const stars = starRating.querySelectorAll('label');
                const inputs = starRating.querySelectorAll('input');
                
                stars.forEach((star, index) => {
                    star.addEventListener('mouseenter', () => {
                        stars.forEach((s, i) => {
                            if (i >= index) {
                                s.style.color = '#ffc107';
                            } else {
                                s.style.color = '#ddd';
                            }
                        });
                    });
                    
                    star.addEventListener('mouseleave', () => {
                        // Reset to selected state
                        const checkedInput = starRating.querySelector('input:checked');
                        if (checkedInput) {
                            const checkedIndex = Array.from(inputs).indexOf(checkedInput);
                            stars.forEach((s, i) => {
                                if (i >= checkedIndex) {
                                    s.style.color = '#ffc107';
                                } else {
                                    s.style.color = '#ddd';
                                }
                            });
                        } else {
                            stars.forEach(s => s.style.color = '#ddd');
                        }
                    });
                    
                    star.addEventListener('click', () => {
                        const input = star.previousElementSibling;
                        input.checked = true;
                        
                        // Update visual state
                        const selectedIndex = Array.from(inputs).indexOf(input);
                        stars.forEach((s, i) => {
                            if (i >= selectedIndex) {
                                s.style.color = '#ffc107';
                            } else {
                                s.style.color = '#ddd';
                            }
                        });
                    });
                });
            }
        });
    </script>
</body>
</html>