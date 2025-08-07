<?php
// customer/order_success.php
/**
 * Trang thành công sau khi đặt hàng (COD) hoặc thanh toán VNPay thành công
 */

require_once '../config/database.php';
require_once '../config/config.php';

$order_code = $_GET['order'] ?? '';
$order_id = $_GET['order_id'] ?? '';

if (empty($order_code) && empty($order_id)) {
    redirect('/customer/cart.php');
}

try {
    // Tìm đơn hàng theo mã đơn hàng hoặc ID
    if ($order_code) {
        $stmt = $pdo->prepare("
            SELECT dh.*, nd.ho_ten, nd.email, nd.so_dien_thoai,
                   vt.vnp_transaction_no, vt.vnp_bank_code, vt.trang_thai as vnpay_status
            FROM don_hang dh
            LEFT JOIN nguoi_dung nd ON dh.khach_hang_id = nd.id
            LEFT JOIN thanh_toan_vnpay vt ON dh.id = vt.don_hang_id
            WHERE dh.ma_don_hang = ?
        ");
        $stmt->execute([$order_code]);
    } else {
        $stmt = $pdo->prepare("
            SELECT dh.*, nd.ho_ten, nd.email, nd.so_dien_thoai,
                   vt.vnp_transaction_no, vt.vnp_bank_code, vt.trang_thai as vnpay_status
            FROM don_hang dh
            LEFT JOIN nguoi_dung nd ON dh.khach_hang_id = nd.id
            LEFT JOIN thanh_toan_vnpay vt ON dh.id = vt.don_hang_id
            WHERE dh.id = ?
        ");
        $stmt->execute([$order_id]);
    }
    
    $order = $stmt->fetch();
    
    if (!$order) {
        throw new Exception('Không tìm thấy đơn hàng');
    }
    
    // Lấy chi tiết sản phẩm trong đơn hàng
    $stmt = $pdo->prepare("
        SELECT ctdh.*, sp.hinh_anh_chinh, sp.slug
        FROM chi_tiet_don_hang ctdh
        LEFT JOIN san_pham_chinh sp ON ctdh.san_pham_id = sp.id
        WHERE ctdh.don_hang_id = ?
        ORDER BY ctdh.id
    ");
    $stmt->execute([$order['id']]);
    $order_items = $stmt->fetchAll();
    
} catch (Exception $e) {
    alert('Có lỗi xảy ra: ' . $e->getMessage(), 'error');
    redirect('/customer/cart.php');
}

$page_title = 'Đặt hàng thành công - ' . SITE_NAME;
$is_vnpay = $order['phuong_thuc_thanh_toan'] === 'vnpay';
$is_paid = $order['trang_thai_thanh_toan'] === 'da_thanh_toan';
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
        .success-header {
            background: linear-gradient(135deg, #28a745 0%, #20c997 100%);
            color: white;
            padding: 60px 0;
            text-align: center;
        }
        
        .success-icon {
            font-size: 4rem;
            margin-bottom: 20px;
            animation: bounce 2s infinite;
        }
        
        @keyframes bounce {
            0%, 20%, 50%, 80%, 100% { transform: translateY(0); }
            40% { transform: translateY(-10px); }
            60% { transform: translateY(-5px); }
        }
        
        .order-summary {
            background: white;
            border-radius: 15px;
            box-shadow: 0 5px 20px rgba(0,0,0,0.1);
            padding: 30px;
            margin-top: -40px;
            position: relative;
            z-index: 10;
        }
        
        .order-item {
            border-bottom: 1px solid #eee;
            padding: 20px 0;
        }
        
        .order-item:last-child {
            border-bottom: none;
        }
        
        .product-image {
            width: 80px;
            height: 80px;
            object-fit: cover;
            border-radius: 8px;
        }
        
        .status-badge {
            padding: 8px 16px;
            border-radius: 20px;
            font-size: 0.9rem;
            font-weight: 600;
        }
        
        .timeline {
            position: relative;
            padding-left: 30px;
        }
        
        .timeline::before {
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
            margin-bottom: 20px;
        }
        
        .timeline-item::before {
            content: '';
            position: absolute;
            left: -23px;
            top: 5px;
            width: 12px;
            height: 12px;
            border-radius: 50%;
            background: #28a745;
            border: 2px solid white;
            box-shadow: 0 0 0 2px #28a745;
        }
        
        .timeline-item.pending::before {
            background: #6c757d;
            box-shadow: 0 0 0 2px #6c757d;
        }
        
        .next-steps {
            background: #f8f9fa;
            border-radius: 10px;
            padding: 25px;
            margin-top: 30px;
        }
        
        .contact-info {
            background: #e7f3ff;
            border: 1px solid #b8daff;
            border-radius: 10px;
            padding: 20px;
            margin-top: 20px;
        }
    </style>
</head>
<body>
    <!-- Success Header -->
    <div class="success-header">
        <div class="container">
            <div class="success-icon">
                <i class="fas fa-check-circle"></i>
            </div>
            <h1 class="mb-3">Đặt hàng thành công!</h1>
            <p class="lead mb-4">Cảm ơn bạn đã mua hàng tại <?= SITE_NAME ?></p>
            <div class="h4">
                Mã đơn hàng: <strong><?= $order['ma_don_hang'] ?></strong>
            </div>
        </div>
    </div>
    
    <div class="container">
        <!-- Order Summary -->
        <div class="order-summary">
            <div class="row">
                <div class="col-lg-8">
                    <!-- Order Information -->
                    <div class="mb-4">
                        <h4 class="mb-3">
                            <i class="fas fa-receipt me-2"></i>
                            Thông tin đơn hàng
                        </h4>
                        
                        <div class="row">
                            <div class="col-md-6">
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
                                        <td class="fw-bold">Địa chỉ:</td>
                                        <td><?= htmlspecialchars($order['dia_chi_nhan']) ?></td>
                                    </tr>
                                </table>
                            </div>
                            <div class="col-md-6">
                                <table class="table table-borderless">
                                    <tr>
                                        <td class="fw-bold">Ngày đặt:</td>
                                        <td><?= date('d/m/Y H:i', strtotime($order['ngay_dat_hang'])) ?></td>
                                    </tr>
                                    <tr>
                                        <td class="fw-bold">Thanh toán:</td>
                                        <td>
                                            <span class="badge bg-<?= $is_vnpay ? 'primary' : 'warning' ?>">
                                                <?= $is_vnpay ? 'VNPay' : 'COD' ?>
                                            </span>
                                        </td>
                                    </tr>
                                    <tr>
                                        <td class="fw-bold">Trạng thái:</td>
                                        <td>
                                            <span class="status-badge bg-<?= $is_paid ? 'success' : 'warning' ?> text-white">
                                                <?= $is_paid ? 'Đã thanh toán' : 'Chưa thanh toán' ?>
                                            </span>
                                        </td>
                                    </tr>
                                    <tr>
                                        <td class="fw-bold">Vận chuyển:</td>
                                        <td><?= ucfirst(str_replace('_', ' ', $order['phuong_thuc_van_chuyen'])) ?></td>
                                    </tr>
                                </table>
                            </div>
                        </div>
                        
                        <?php if ($order['ghi_chu_khach_hang']): ?>
                            <div class="mt-3">
                                <strong>Ghi chú:</strong> 
                                <em><?= htmlspecialchars($order['ghi_chu_khach_hang']) ?></em>
                            </div>
                        <?php endif; ?>
                    </div>
                    
                    <!-- Order Items -->
                    <div class="mb-4">
                        <h4 class="mb-3">
                            <i class="fas fa-box me-2"></i>
                            Sản phẩm đã đặt
                        </h4>
                        
                        <?php foreach ($order_items as $item): ?>
                            <div class="order-item">
                                <div class="row align-items-center">
                                    <div class="col-md-2 col-3">
                                        <img src="/tktshop/uploads/products/<?= $item['hinh_anh'] ?: 'default-product.jpg' ?>" 
                                             alt="<?= htmlspecialchars($item['ten_san_pham']) ?>"
                                             class="product-image"
                                             onerror="this.src='/tktshop/assets/images/no-image.jpg'">
                                    </div>
                                    <div class="col-md-6 col-9">
                                        <h6 class="mb-1"><?= htmlspecialchars($item['ten_san_pham']) ?></h6>
                                        <div class="text-muted small">
                                            <?php if ($item['thuong_hieu']): ?>
                                                Thương hiệu: <?= htmlspecialchars($item['thuong_hieu']) ?><br>
                                            <?php endif; ?>
                                            Size: <?= htmlspecialchars($item['kich_co']) ?> | 
                                            Màu: <?= htmlspecialchars($item['mau_sac']) ?>
                                        </div>
                                        <small class="text-muted">SKU: <?= htmlspecialchars($item['ma_sku']) ?></small>
                                    </div>
                                    <div class="col-md-2 col-6 text-center">
                                        <div class="fw-bold"><?= formatPrice($item['gia_ban']) ?></div>
                                        <small class="text-muted">x<?= $item['so_luong'] ?></small>
                                    </div>
                                    <div class="col-md-2 col-6 text-end">
                                        <div class="fw-bold text-primary"><?= formatPrice($item['thanh_tien']) ?></div>
                                    </div>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                    
                    <!-- Payment Summary -->
                    <div class="row">
                        <div class="col-md-6 offset-md-6">
                            <table class="table">
                                <tr>
                                    <td>Tạm tính:</td>
                                    <td class="text-end"><?= formatPrice($order['tong_tien_hang']) ?></td>
                                </tr>
                                <tr>
                                    <td>Phí vận chuyển:</td>
                                    <td class="text-end">
                                        <?= $order['phi_van_chuyen'] > 0 ? formatPrice($order['phi_van_chuyen']) : 'Miễn phí' ?>
                                    </td>
                                </tr>
                                <tr class="table-active">
                                    <td><strong>Tổng cộng:</strong></td>
                                    <td class="text-end"><strong class="text-primary"><?= formatPrice($order['tong_thanh_toan']) ?></strong></td>
                                </tr>
                            </table>
                        </div>
                    </div>
                </div>
                
                <!-- Right Column - Order Status & Next Steps -->
                <div class="col-lg-4">
                    <!-- Order Status Timeline -->
                    <div class="mb-4">
                        <h5 class="mb-3">
                            <i class="fas fa-truck me-2"></i>
                            Trạng thái đơn hàng
                        </h5>
                        
                        <div class="timeline">
                            <div class="timeline-item">
                                <div class="fw-bold">Đã đặt hàng</div>
                                <small class="text-muted"><?= date('d/m/Y H:i', strtotime($order['ngay_dat_hang'])) ?></small>
                            </div>
                            
                            <?php if ($is_paid): ?>
                                <div class="timeline-item">
                                    <div class="fw-bold">Đã thanh toán</div>
                                    <small class="text-success">
                                        <?php if ($is_vnpay && $order['vnp_transaction_no']): ?>
                                            VNPay - GD: <?= $order['vnp_transaction_no'] ?>
                                        <?php else: ?>
                                            <?= date('d/m/Y H:i') ?>
                                        <?php endif; ?>
                                    </small>
                                </div>
                            <?php endif; ?>
                            
                            <div class="timeline-item pending">
                                <div class="fw-bold text-muted">Xác nhận đơn hàng</div>
                                <small class="text-muted">Đang chờ xử lý</small>
                            </div>
                            
                            <div class="timeline-item pending">
                                <div class="fw-bold text-muted">Đang chuẩn bị</div>
                                <small class="text-muted">Chưa bắt đầu</small>
                            </div>
                            
                            <div class="timeline-item pending">
                                <div class="fw-bold text-muted">Đang giao hàng</div>
                                <small class="text-muted">Chưa bắt đầu</small>
                            </div>
                            
                            <div class="timeline-item pending">
                                <div class="fw-bold text-muted">Hoàn thành</div>
                                <small class="text-muted">Chưa hoàn thành</small>
                            </div>
                        </div>
                    </div>
                    
                    <!-- VNPay Info (if applicable) -->
                    <?php if ($is_vnpay && $order['vnpay_status']): ?>
                        <div class="mb-4">
                            <h5 class="mb-3">
                                <i class="fas fa-credit-card me-2"></i>
                                Thông tin VNPay
                            </h5>
                            
                            <div class="card">
                                <div class="card-body">
                                    <?php if ($order['vnp_transaction_no']): ?>
                                        <div class="mb-2">
                                            <strong>Mã GD VNPay:</strong><br>
                                            <code><?= $order['vnp_transaction_no'] ?></code>
                                        </div>
                                    <?php endif; ?>
                                    
                                    <?php if ($order['vnp_bank_code']): ?>
                                        <div class="mb-2">
                                            <strong>Ngân hàng:</strong><br>
                                            <?= $order['vnp_bank_code'] ?>
                                        </div>
                                    <?php endif; ?>
                                    
                                    <div class="mb-0">
                                        <strong>Trạng thái:</strong><br>
                                        <span class="badge bg-<?= $order['vnpay_status'] === 'thanh_cong' ? 'success' : 'warning' ?>">
                                            <?= $order['vnpay_status'] === 'thanh_cong' ? 'Thành công' : 'Đang xử lý' ?>
                                        </span>
                                    </div>
                                </div>
                            </div>
                        </div>
                    <?php endif; ?>
                    
                    <!-- Action Buttons -->
                    <div class="d-grid gap-2 mb-4">
                        <a href="orders.php" class="btn btn-primary">
                            <i class="fas fa-list me-2"></i>
                            Xem tất cả đơn hàng
                        </a>
                        
                        <a href="products.php" class="btn btn-outline-primary">
                            <i class="fas fa-shopping-bag me-2"></i>
                            Tiếp tục mua sắm
                        </a>
                        
                        <?php if (!$is_paid && !$is_vnpay): ?>
                            <a href="checkout.php?order_id=<?= $order['id'] ?>" class="btn btn-warning">
                                <i class="fas fa-credit-card me-2"></i>
                                Thanh toán ngay
                            </a>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
        
        <!-- Next Steps -->
        <div class="next-steps">
            <h4 class="mb-3">
                <i class="fas fa-info-circle me-2"></i>
                Những việc cần làm tiếp theo
            </h4>
            
            <div class="row">
                <div class="col-md-6">
                    <h6><i class="fas fa-envelope me-2 text-primary"></i>Kiểm tra email</h6>
                    <p class="text-muted">
                        Chúng tôi đã gửi email xác nhận đơn hàng đến địa chỉ của bạn. 
                        Vui lòng kiểm tra hộp thư đến và thư spam.
                    </p>
                </div>
                
                <div class="col-md-6">
                    <h6><i class="fas fa-phone me-2 text-success"></i>Chuẩn bị nhận hàng</h6>
                    <p class="text-muted">
                        <?php if ($is_vnpay && $is_paid): ?>
                            Đơn hàng sẽ được xử lý trong 1-2 giờ làm việc và giao trong 1-3 ngày.
                        <?php else: ?>
                            Nhân viên sẽ liên hệ xác nhận đơn hàng trong 30 phút. Vui lòng để máy.
                        <?php endif; ?>
                    </p>
                </div>
            </div>
            
            <?php if (!$is_paid): ?>
                <div class="alert alert-warning mt-3">
                    <i class="fas fa-exclamation-triangle me-2"></i>
                    <strong>Lưu ý:</strong> 
                    <?php if ($is_vnpay): ?>
                        Đơn hàng chưa được thanh toán. Vui lòng hoàn tất thanh toán để được xử lý nhanh nhất.
                    <?php else: ?>
                        Đây là đơn hàng thanh toán khi nhận hàng (COD). Vui lòng chuẩn bị đủ tiền mặt khi nhận hàng.
                    <?php endif; ?>
                </div>
            <?php endif; ?>
        </div>
        
        <!-- Contact Info -->
        <div class="contact-info">
            <h5 class="mb-3">
                <i class="fas fa-headset me-2"></i>
                Cần hỗ trợ?
            </h5>
            
            <div class="row">
                <div class="col-md-4">
                    <div class="text-center">
                        <i class="fas fa-phone fa-2x text-primary mb-2"></i>
                        <div class="fw-bold">Hotline</div>
                        <a href="tel:1900123456" class="text-decoration-none">1900.123.456</a>
                        <div class="small text-muted">8:00 - 22:00 hàng ngày</div>
                    </div>
                </div>
                
                <div class="col-md-4">
                    <div class="text-center">
                        <i class="fas fa-envelope fa-2x text-success mb-2"></i>
                        <div class="fw-bold">Email</div>
                        <a href="mailto:support@tktshop.com" class="text-decoration-none">support@tktshop.com</a>
                        <div class="small text-muted">Phản hồi trong 24h</div>
                    </div>
                </div>
                
                <div class="col-md-4">
                    <div class="text-center">
                        <i class="fas fa-comments fa-2x text-info mb-2"></i>
                        <div class="fw-bold">Live Chat</div>
                        <a href="#" class="text-decoration-none" onclick="openLiveChat()">Nhắn tin ngay</a>
                        <div class="small text-muted">Hỗ trợ trực tuyến</div>
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <!-- Footer -->
    <?php include 'includes/footer.php'; ?>
    
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
    
    <script>
        // Print order function
        function printOrder() {
            window.print();
        }
        
        // Open live chat (placeholder)
        function openLiveChat() {
            alert('Tính năng chat sẽ được cập nhật sớm!');
        }
        
        // Auto refresh order status every 30 seconds (for real-time updates)
        <?php if (!$is_paid && $is_vnpay): ?>
        setInterval(function() {
            // Check payment status via AJAX
            fetch('/vnpay/check_status.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded',
                },
                body: 'action=get_order_status&order_id=<?= $order['id'] ?>'
            })
            .then(response => response.json())
            .then(data => {
                if (data.success && data.data.payment_status === 'da_thanh_toan') {
                    // Reload page if payment completed
                    location.reload();
                }
            })
            .catch(error => console.log('Status check error:', error));
        }, 30000);
        <?php endif; ?>
        
        // Tracking pixel for successful orders
        <?php if ($is_paid): ?>
        // Google Analytics conversion tracking
        if (typeof gtag !== 'undefined') {
            gtag('event', 'purchase', {
                transaction_id: '<?= $order['ma_don_hang'] ?>',
                value: <?= $order['tong_thanh_toan'] ?>,
                currency: 'VND',
                items: [
                    <?php foreach ($order_items as $index => $item): ?>
                    {
                        item_id: '<?= $item['ma_sku'] ?>',
                        item_name: '<?= addslashes($item['ten_san_pham']) ?>',
                        category: '<?= addslashes($item['thuong_hieu'] ?? '') ?>',
                        quantity: <?= $item['so_luong'] ?>,
                        price: <?= $item['gia_ban'] ?>
                    }<?= $index < count($order_items) - 1 ? ',' : '' ?>
                    <?php endforeach; ?>
                ]
            });
        }
        
        // Facebook Pixel conversion tracking
        if (typeof fbq !== 'undefined') {
            fbq('track', 'Purchase', {
                value: <?= $order['tong_thanh_toan'] ?>,
                currency: 'VND',
                content_ids: [<?php echo '"' . implode('","', array_column($order_items, 'ma_sku')) . '"'; ?>],
                content_type: 'product'
            });
        }
        <?php endif; ?>
        
        // Show welcome message for first-time buyers
        <?php if (!isset($_SESSION['customer_id'])): ?>
        setTimeout(function() {
            if (confirm('Bạn có muốn tạo tài khoản để theo dõi đơn hàng dễ dàng hơn?')) {
                window.location.href = '/customer/register.php?order=<?= $order['ma_don_hang'] ?>';
            }
        }, 5000);
        <?php endif; ?>
    </script>
</body>
</html>