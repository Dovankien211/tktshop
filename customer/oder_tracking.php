<?php
// customer/order_tracking.php
/**
 * Theo dõi đơn hàng cho khách hàng
 */

require_once '../config/database.php';
require_once '../config/config.php';

$order_code = $_GET['order'] ?? '';
$order_id = $_GET['order_id'] ?? '';
$phone = $_GET['phone'] ?? '';

$order = null;
$order_items = [];
$error_message = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $search_order = trim($_POST['order_code'] ?? '');
    $search_phone = trim($_POST['phone'] ?? '');
    
    if (empty($search_order) || empty($search_phone)) {
        $error_message = 'Vui lòng nhập đầy đủ mã đơn hàng và số điện thoại';
    } else {
        redirect("order_tracking.php?order={$search_order}&phone={$search_phone}");
    }
}

// Tìm kiếm đơn hàng
if ($order_code && $phone) {
    try {
        $stmt = $pdo->prepare("
            SELECT dh.*, nd.ho_ten, nd.email, nd.so_dien_thoai,
                   vt.vnp_transaction_no, vt.vnp_bank_code, vt.trang_thai as vnpay_status
            FROM don_hang dh
            LEFT JOIN nguoi_dung nd ON dh.khach_hang_id = nd.id
            LEFT JOIN thanh_toan_vnpay vt ON dh.id = vt.don_hang_id
            WHERE (dh.ma_don_hang = ? OR dh.id = ?) 
            AND dh.so_dien_thoai_nhan = ?
        ");
        $stmt->execute([$order_code, $order_id, $phone]);
        $order = $stmt->fetch();
        
        if ($order) {
            // Lấy chi tiết sản phẩm
            $stmt = $pdo->prepare("
                SELECT ctdh.*, sp.hinh_anh_chinh, sp.slug
                FROM chi_tiet_don_hang ctdh
                LEFT JOIN san_pham_chinh sp ON ctdh.san_pham_id = sp.id
                WHERE ctdh.don_hang_id = ?
                ORDER BY ctdh.id
            ");
            $stmt->execute([$order['id']]);
            $order_items = $stmt->fetchAll();
        } else {
            $error_message = 'Không tìm thấy đơn hàng với thông tin đã nhập';
        }
        
    } catch (Exception $e) {
        $error_message = 'Có lỗi xảy ra khi tìm kiếm đơn hàng';
        error_log('Order tracking error: ' . $e->getMessage());
    }
} elseif ($order_id && $phone) {
    // Direct link from email/SMS
    redirect("order_tracking.php?order={$order_id}&phone={$phone}");
}

$page_title = 'Theo dõi đơn hàng - ' . SITE_NAME;
?>

<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= $page_title ?></title>
    
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    
    <style>
        .tracking-header {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            padding: 40px 0;
        }
        
        .search-card {
            background: white;
            border-radius: 15px;
            box-shadow: 0 10px 30px rgba(0,0,0,0.1);
            padding: 30px;
            margin-top: -40px;
            position: relative;
            z-index: 10;
        }
        
        .order-timeline {
            position: relative;
            padding-left: 30px;
            margin: 30px 0;
        }
        
        .order-timeline::before {
            content: '';
            position: absolute;
            left: 15px;
            top: 0;
            bottom: 0;
            width: 2px;
            background: #dee2e6;
        }
        
        .timeline-item {
            position: relative;
            margin-bottom: 30px;
            background: white;
            padding: 20px;
            border-radius: 10px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
        }
        
        .timeline-item::before {
            content: '';
            position: absolute;
            left: -23px;
            top: 25px;
            width: 16px;
            height: 16px;
            border-radius: 50%;
            background: #6c757d;
            border: 3px solid white;
            box-shadow: 0 0 0 3px #6c757d;
        }
        
        .timeline-item.completed::before {
            background: #28a745;
            box-shadow: 0 0 0 3px #28a745;
        }
        
        .timeline-item.active::before {
            background: #007bff;
            box-shadow: 0 0 0 3px #007bff;
            animation: pulse 2s infinite;
        }
        
        @keyframes pulse {
            0% { transform: scale(1); }
            50% { transform: scale(1.2); }
            100% { transform: scale(1); }
        }
        
        .timeline-date {
            font-size: 0.875rem;
            color: #6c757d;
        }
        
        .order-info {
            background: #f8f9fa;
            border-radius: 10px;
            padding: 25px;
            margin-bottom: 30px;
        }
        
        .product-item {
            border-bottom: 1px solid #eee;
            padding: 15px 0;
        }
        
        .product-item:last-child {
            border-bottom: none;
        }
        
        .product-image {
            width: 60px;
            height: 60px;
            object-fit: cover;
            border-radius: 8px;
        }
        
        .status-badge {
            padding: 8px 16px;
            border-radius: 20px;
            font-size: 0.875rem;
            font-weight: 600;
        }
        
        .delivery-info {
            background: linear-gradient(135deg, #e3f2fd 0%, #f3e5f5 100%);
            border-radius: 10px;
            padding: 20px;
            margin: 20px 0;
        }
        
        .contact-support {
            background: #fff3cd;
            border: 1px solid #ffeaa7;
            border-radius: 10px;
            padding: 20px;
            margin-top: 30px;
        }
    </style>
</head>
<body>
    <!-- Header -->
    <?php include 'includes/header.php'; ?>
    
    <!-- Tracking Header -->
    <div class="tracking-header">
        <div class="container">
            <div class="text-center">
                <h2><i class="fas fa-search me-2"></i>Theo dõi đơn hàng</h2>
                <p class="mb-0">Kiểm tra trạng thái và tiến độ giao hàng</p>
            </div>
        </div>
    </div>
    
    <div class="container">
        <!-- Search Card -->
        <div class="search-card">
            <?php if (!$order): ?>
                <div class="text-center mb-4">
                    <i class="fas fa-shipping-fast fa-3x text-primary mb-3"></i>
                    <h4>Tìm kiếm đơn hàng</h4>
                    <p class="text-muted">Nhập thông tin để theo dõi đơn hàng của bạn</p>
                </div>
                
                <?php if ($error_message): ?>
                    <div class="alert alert-danger">
                        <i class="fas fa-exclamation-triangle me-2"></i>
                        <?= htmlspecialchars($error_message) ?>
                    </div>
                <?php endif; ?>
                
                <form method="POST">
                    <div class="row">
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label for="order_code" class="form-label">Mã đơn hàng:</label>
                                <input type="text" class="form-control" id="order_code" name="order_code" 
                                       placeholder="Ví dụ: DH20240815123456" required
                                       value="<?= htmlspecialchars($_POST['order_code'] ?? $order_code) ?>">
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label for="phone" class="form-label">Số điện thoại:</label>
                                <input type="tel" class="form-control" id="phone" name="phone" 
                                       placeholder="Số điện thoại nhận hàng" required
                                       value="<?= htmlspecialchars($_POST['phone'] ?? $phone) ?>">
                            </div>
                        </div>
                    </div>
                    
                    <div class="d-grid">
                        <button type="submit" class="btn btn-primary btn-lg">
                            <i class="fas fa-search me-2"></i>Tìm kiếm đơn hàng
                        </button>
                    </div>
                </form>
                
                <div class="text-center mt-4">
                    <p class="text-muted mb-2">
                        <i class="fas fa-info-circle me-1"></i>
                        Mã đơn hàng được gửi qua email hoặc SMS sau khi đặt hàng thành công
                    </p>
                    <div class="d-flex justify-content-center gap-3">
                        <a href="tel:1900123456" class="text-decoration-none">
                            <i class="fas fa-phone me-1"></i>1900.123.456
                        </a>
                        <a href="mailto:support@tktshop.com" class="text-decoration-none">
                            <i class="fas fa-envelope me-1"></i>support@tktshop.com
                        </a>
                    </div>
                </div>
            <?php else: ?>
                <!-- Order Found -->
                <div class="text-center mb-4">
                    <i class="fas fa-check-circle fa-3x text-success mb-3"></i>
                    <h4>Tìm thấy đơn hàng</h4>
                    <p class="text-muted">Thông tin chi tiết và trạng thái đơn hàng của bạn</p>
                </div>
                
                <!-- Order Info -->
                <div class="order-info">
                    <div class="row">
                        <div class="col-md-6">
                            <h5 class="mb-3">
                                <i class="fas fa-receipt me-2"></i>
                                Đơn hàng #<?= $order['ma_don_hang'] ?>
                            </h5>
                            
                            <table class="table table-borderless">
                                <tr>
                                    <td class="fw-bold">Khách hàng:</td>
                                    <td><?= htmlspecialchars($order['ho_ten_nhan']) ?></td>
                                </tr>
                                <tr>
                                    <td class="fw-bold">Điện thoại:</td>
                                    <td><?= htmlspecialchars($order['so_dien_thoai_nhan']) ?></td>
                                </tr>
                                <tr>
                                    <td class="fw-bold">Email:</td>
                                    <td><?= htmlspecialchars($order['email_nhan'] ?: 'Không có') ?></td>
                                </tr>
                                <tr>
                                    <td class="fw-bold">Ngày đặt:</td>
                                    <td><?= date('d/m/Y H:i', strtotime($order['ngay_dat_hang'])) ?></td>
                                </tr>
                            </table>
                        </div>
                        
                        <div class="col-md-6">
                            <table class="table table-borderless">
                                <tr>
                                    <td class="fw-bold">Trạng thái:</td>
                                    <td>
                                        <?php
                                        $status_info = [
                                            'cho_xac_nhan' => ['warning', 'Chờ xác nhận'],
                                            'da_xac_nhan' => ['info', 'Đã xác nhận'],
                                            'dang_chuan_bi' => ['primary', 'Đang chuẩn bị'],
                                            'dang_giao' => ['warning', 'Đang giao hàng'],
                                            'da_giao' => ['success', 'Đã giao hàng'],
                                            'da_huy' => ['danger', 'Đã hủy'],
                                            'hoan_tra' => ['secondary', 'Hoàn trả']
                                        ];
                                        
                                        $current_status = $status_info[$order['trang_thai_don_hang']] ?? ['secondary', 'Không xác định'];
                                        ?>
                                        <span class="status-badge bg-<?= $current_status[0] ?> text-white">
                                            <?= $current_status[1] ?>
                                        </span>
                                    </td>
                                </tr>
                                <tr>
                                    <td class="fw-bold">Thanh toán:</td>
                                    <td>
                                        <span class="badge bg-<?= $order['phuong_thuc_thanh_toan'] === 'vnpay' ? 'primary' : 'warning' ?>">
                                            <?= $order['phuong_thuc_thanh_toan'] === 'vnpay' ? 'VNPay' : 'COD' ?>
                                        </span>
                                        <span class="badge bg-<?= $order['trang_thai_thanh_toan'] === 'da_thanh_toan' ? 'success' : 'warning' ?> ms-1">
                                            <?= $order['trang_thai_thanh_toan'] === 'da_thanh_toan' ? 'Đã thanh toán' : 'Chưa thanh toán' ?>
                                        </span>
                                    </td>
                                </tr>
                                <tr>
                                    <td class="fw-bold">Tổng tiền:</td>
                                    <td class="fw-bold text-primary"><?= formatPrice($order['tong_thanh_toan']) ?></td>
                                </tr>
                                <tr>
                                    <td class="fw-bold">Địa chỉ:</td>
                                    <td><?= htmlspecialchars($order['dia_chi_nhan']) ?></td>
                                </tr>
                            </table>
                        </div>
                    </div>
                    
                    <?php if ($order['ghi_chu_khach_hang']): ?>
                        <div class="mt-3">
                            <strong>Ghi chú khách hàng:</strong>
                            <em><?= htmlspecialchars($order['ghi_chu_khach_hang']) ?></em>
                        </div>
                    <?php endif; ?>
                </div>
                
                <!-- Order Timeline -->
                <div class="row">
                    <div class="col-lg-8">
                        <h5 class="mb-4">
                            <i class="fas fa-route me-2"></i>
                            Lịch trình đơn hàng
                        </h5>
                        
                        <div class="order-timeline">
                            <!-- Đặt hàng -->
                            <div class="timeline-item completed">
                                <div class="d-flex justify-content-between align-items-start">
                                    <div>
                                        <h6 class="mb-1">
                                            <i class="fas fa-shopping-cart me-2 text-success"></i>
                                            Đặt hàng thành công
                                        </h6>
                                        <p class="mb-1">Đơn hàng đã được tạo và đang chờ xử lý</p>
                                    </div>
                                    <small class="timeline-date">
                                        <?= date('d/m/Y H:i', strtotime($order['ngay_dat_hang'])) ?>
                                    </small>
                                </div>
                            </div>
                            
                            <!-- Xác nhận -->
                            <div class="timeline-item <?= in_array($order['trang_thai_don_hang'], ['da_xac_nhan', 'dang_chuan_bi', 'dang_giao', 'da_giao']) ? 'completed' : ($order['trang_thai_don_hang'] === 'cho_xac_nhan' ? 'active' : '') ?>">
                                <div class="d-flex justify-content-between align-items-start">
                                    <div>
                                        <h6 class="mb-1">
                                            <i class="fas fa-check-circle me-2 text-<?= $order['ngay_xac_nhan'] ? 'success' : 'muted' ?>"></i>
                                            Xác nhận đơn hàng
                                        </h6>
                                        <p class="mb-1">
                                            <?php if ($order['ngay_xac_nhan']): ?>
                                                Shop đã xác nhận đơn hàng và sẽ chuẩn bị hàng
                                            <?php else: ?>
                                                Shop đang xác nhận thông tin đơn hàng
                                            <?php endif; ?>
                                        </p>
                                    </div>
                                    <?php if ($order['ngay_xac_nhan']): ?>
                                        <small class="timeline-date">
                                            <?= date('d/m/Y H:i', strtotime($order['ngay_xac_nhan'])) ?>
                                        </small>
                                    <?php endif; ?>
                                </div>
                            </div>
                            
                            <!-- Chuẩn bị hàng -->
                            <div class="timeline-item <?= in_array($order['trang_thai_don_hang'], ['dang_chuan_bi', 'dang_giao', 'da_giao']) ? 'completed' : ($order['trang_thai_don_hang'] === 'da_xac_nhan' ? 'active' : '') ?>">
                                <div class="d-flex justify-content-between align-items-start">
                                    <div>
                                        <h6 class="mb-1">
                                            <i class="fas fa-box me-2 text-<?= in_array($order['trang_thai_don_hang'], ['dang_chuan_bi', 'dang_giao', 'da_giao']) ? 'success' : 'muted' ?>"></i>
                                            Chuẩn bị hàng hóa
                                        </h6>
                                        <p class="mb-1">
                                            <?= in_array($order['trang_thai_don_hang'], ['dang_chuan_bi', 'dang_giao', 'da_giao']) ? 'Đã chuẩn bị xong và sẵn sàng giao hàng' : 'Đang đóng gói và chuẩn bị hàng hóa' ?>
                                        </p>
                                    </div>
                                </div>
                            </div>
                            
                            <!-- Giao hàng -->
                            <div class="timeline-item <?= in_array($order['trang_thai_don_hang'], ['dang_giao', 'da_giao']) ? 'completed' : ($order['trang_thai_don_hang'] === 'dang_chuan_bi' ? 'active' : '') ?>">
                                <div class="d-flex justify-content-between align-items-start">
                                    <div>
                                        <h6 class="mb-1">
                                            <i class="fas fa-truck me-2 text-<?= in_array($order['trang_thai_don_hang'], ['dang_giao', 'da_giao']) ? 'success' : 'muted' ?>"></i>
                                            Đang giao hàng
                                        </h6>
                                        <p class="mb-1">
                                            <?php if ($order['trang_thai_don_hang'] === 'dang_giao'): ?>
                                                Shipper đang trên đường giao hàng đến bạn
                                            <?php elseif ($order['trang_thai_don_hang'] === 'da_giao'): ?>
                                                Đã giao hàng thành công
                                            <?php else: ?>
                                                Chờ shipper nhận hàng và giao đến bạn
                                            <?php endif; ?>
                                        </p>
                                        
                                        <?php if ($order['trang_thai_don_hang'] === 'dang_giao' && $order['phuong_thuc_thanh_toan'] === 'cod'): ?>
                                            <div class="delivery-info">
                                                <i class="fas fa-info-circle me-2"></i>
                                                <strong>Lưu ý thanh toán COD:</strong>
                                                <br>Vui lòng chuẩn bị đủ tiền mặt <strong><?= formatPrice($order['tong_thanh_toan']) ?></strong> để thanh toán khi nhận hàng.
                                            </div>
                                        <?php endif; ?>
                                    </div>
                                    <?php if ($order['ngay_giao_hang']): ?>
                                        <small class="timeline-date">
                                            <?= date('d/m/Y H:i', strtotime($order['ngay_giao_hang'])) ?>
                                        </small>
                                    <?php endif; ?>
                                </div>
                            </div>
                            
                            <!-- Hoàn thành -->
                            <div class="timeline-item <?= $order['trang_thai_don_hang'] === 'da_giao' ? 'completed' : ($order['trang_thai_don_hang'] === 'dang_giao' ? 'active' : '') ?>">
                                <div class="d-flex justify-content-between align-items-start">
                                    <div>
                                        <h6 class="mb-1">
                                            <i class="fas fa-home me-2 text-<?= $order['trang_thai_don_hang'] === 'da_giao' ? 'success' : 'muted' ?>"></i>
                                            Giao hàng thành công
                                        </h6>
                                        <p class="mb-1">
                                            <?= $order['trang_thai_don_hang'] === 'da_giao' ? 'Đơn hàng đã được giao thành công. Cảm ơn bạn đã mua hàng!' : 'Chờ xác nhận giao hàng thành công' ?>
                                        </p>
                                    </div>
                                    <?php if ($order['ngay_hoan_thanh']): ?>
                                        <small class="timeline-date">
                                            <?= date('d/m/Y H:i', strtotime($order['ngay_hoan_thanh'])) ?>
                                        </small>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </div>
                    </div>
                    
                    <!-- Order Items -->
                    <div class="col-lg-4">
                        <h5 class="mb-4">
                            <i class="fas fa-box-open me-2"></i>
                            Sản phẩm đã đặt
                        </h5>
                        
                        <div class="card">
                            <div class="card-body">
                                <?php foreach ($order_items as $item): ?>
                                    <div class="product-item">
                                        <div class="d-flex">
                                            <div class="me-3">
                                                <img src="/tktshop/uploads/products/<?= $item['hinh_anh'] ?: 'default-product.jpg' ?>" 
                                                     alt="<?= htmlspecialchars($item['ten_san_pham']) ?>"
                                                     class="product-image"
                                                     onerror="this.src='/tktshop/assets/images/no-image.jpg'">
                                            </div>
                                            <div class="flex-grow-1">
                                                <h6 class="mb-1"><?= htmlspecialchars($item['ten_san_pham']) ?></h6>
                                                <div class="text-muted small mb-1">
                                                    Size: <?= htmlspecialchars($item['kich_co']) ?> | 
                                                    Màu: <?= htmlspecialchars($item['mau_sac']) ?>
                                                </div>
                                                <div class="d-flex justify-content-between">
                                                    <span class="text-muted">x<?= $item['so_luong'] ?></span>
                                                    <span class="fw-bold"><?= formatPrice($item['thanh_tien']) ?></span>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                <?php endforeach; ?>
                                
                                <div class="border-top pt-3 mt-3">
                                    <div class="d-flex justify-content-between mb-2">
                                        <span>Tạm tính:</span>
                                        <span><?= formatPrice($order['tong_tien_hang']) ?></span>
                                    </div>
                                    <div class="d-flex justify-content-between mb-2">
                                        <span>Phí vận chuyển:</span>
                                        <span><?= $order['phi_van_chuyen'] > 0 ? formatPrice($order['phi_van_chuyen']) : 'Miễn phí' ?></span>
                                    </div>
                                    <div class="d-flex justify-content-between fw-bold text-primary">
                                        <span>Tổng cộng:</span>
                                        <span><?= formatPrice($order['tong_thanh_toan']) ?></span>
                                    </div>
                                </div>
                            </div>
                        </div>
                        
                        <!-- Action Buttons -->
                        <div class="d-grid gap-2 mt-3">
                            <?php if ($order['trang_thai_don_hang'] === 'cho_xac_nhan'): ?>
                                <a href="checkout.php?order_id=<?= $order['id'] ?>" class="btn btn-warning">
                                    <i class="fas fa-edit me-1"></i>Chỉnh sửa đơn hàng
                                </a>
                            <?php endif; ?>
                            
                            <?php if ($order['trang_thai_don_hang'] === 'da_giao'): ?>
                                <a href="orders.php" class="btn btn-primary">
                                    <i class="fas fa-star me-1"></i>Đánh giá sản phẩm
                                </a>
                            <?php endif; ?>
                            
                            <a href="products.php" class="btn btn-outline-primary">
                                <i class="fas fa-shopping-bag me-1"></i>Tiếp tục mua sắm
                            </a>
                        </div>
                    </div>
                </div>
                
                <!-- Contact Support -->
                <div class="contact-support">
                    <h6><i class="fas fa-headset me-2"></i>Cần hỗ trợ?</h6>
                    <p class="mb-2">Liên hệ với chúng tôi nếu bạn có bất kỳ thắc mắc nào về đơn hàng</p>
                    <div class="d-flex flex-wrap gap-3">
                        <a href="tel:1900123456" class="text-decoration-none">
                            <i class="fas fa-phone me-1"></i>1900.123.456
                        </a>
                        <a href="mailto:support@tktshop.com" class="text-decoration-none">
                            <i class="fas fa-envelope me-1"></i>support@tktshop.com
                        </a>
                        <a href="#" class="text-decoration-none" onclick="openLiveChat()">
                            <i class="fas fa-comments me-1"></i>Live Chat
                        </a>
                    </div>
                </div>
                
                <!-- Search Again -->
                <div class="text-center mt-4">
                    <a href="order_tracking.php" class="btn btn-outline-secondary">
                        <i class="fas fa-search me-1"></i>Tìm đơn hàng khác
                    </a>
                </div>
            <?php endif; ?>
        </div>
    </div>
    
    <!-- Footer -->
    <?php include 'includes/footer.php'; ?>
    
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
    
    <script>
        // Phone number formatting
        document.getElementById('phone')?.addEventListener('input', function() {
            this.value = this.value.replace(/[^0-9]/g, '');
        });
        
        // Order code formatting
        document.getElementById('order_code')?.addEventListener('input', function() {
            this.value = this.value.toUpperCase();
        });
        
        // Auto-refresh if order is being processed
        <?php if ($order && in_array($order['trang_thai_don_hang'], ['cho_xac_nhan', 'da_xac_nhan', 'dang_chuan_bi', 'dang_giao'])): ?>
        setTimeout(function() {
            location.reload();
        }, 60000); // Refresh every minute
        <?php endif; ?>
        
        function openLiveChat() {
            alert('Tính năng chat sẽ được cập nhật sớm!');
        }
        
        // Copy order code to clipboard
        function copyOrderCode(code) {
            navigator.clipboard.writeText(code).then(function() {
                alert('Đã copy mã đơn hàng: ' + code);
            });
        }
    </script>
</body>
</html>