<?php
// customer/checkout.php - FINAL FIXED VERSION
/**
 * Thanh toán - Tương thích hoàn toàn với VNPay có sẵn và COD
 * 🔧 FIXED: Kết nối với VNPay config có sẵn + COD hoàn chỉnh
 */

require_once '../config/database.php';
require_once '../config/config.php';

// Kiểm tra đăng nhập (có thể checkout với guest)
$customer_id = $_SESSION['customer_id'] ?? null;
$session_id = $customer_id ? null : ($_SESSION['session_id'] ?? session_id());

// Lấy sản phẩm trong giỏ hàng
$stmt = $pdo->prepare("
    SELECT gh.*, sp.id as san_pham_id, sp.ten_san_pham, sp.hinh_anh_chinh, sp.slug, sp.thuong_hieu,
           bsp.ma_sku, bsp.gia_ban as gia_hien_tai, bsp.so_luong_ton_kho,
           kc.kich_co, ms.ten_mau, ms.ma_mau,
           (gh.so_luong * gh.gia_tai_thoi_diem) as thanh_tien
    FROM gio_hang gh
    JOIN bien_the_san_pham bsp ON gh.bien_the_id = bsp.id
    JOIN san_pham_chinh sp ON bsp.san_pham_id = sp.id
    JOIN kich_co kc ON bsp.kich_co_id = kc.id
    JOIN mau_sac ms ON bsp.mau_sac_id = ms.id
    WHERE (gh.khach_hang_id = ? OR gh.session_id = ?) 
    AND bsp.trang_thai = 'hoat_dong' 
    AND sp.trang_thai = 'hoat_dong'
    AND bsp.so_luong_ton_kho >= gh.so_luong
    ORDER BY gh.ngay_them DESC
");
$stmt->execute([$customer_id, $session_id]);
$cart_items = $stmt->fetchAll();

// Redirect nếu giỏ hàng trống
if (empty($cart_items)) {
    alert('Giỏ hàng của bạn đang trống!', 'warning');
    redirect('/customer/cart.php');
}

// Tính tổng tiền
$subtotal = array_sum(array_column($cart_items, 'thanh_tien'));
$total_quantity = array_sum(array_column($cart_items, 'so_luong'));

// Lấy thông tin khách hàng nếu đã đăng nhập
$customer_info = [];
if ($customer_id) {
    $stmt = $pdo->prepare("SELECT * FROM nguoi_dung WHERE id = ?");
    $stmt->execute([$customer_id]);
    $customer_info = $stmt->fetch() ?: [];
}

$errors = [];
$form_data = [];

// Xử lý đặt hàng
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    // Lấy dữ liệu form
    $form_data = [
        'ho_ten_nhan' => trim($_POST['ho_ten_nhan'] ?? ''),
        'so_dien_thoai_nhan' => trim($_POST['so_dien_thoai_nhan'] ?? ''),
        'email_nhan' => trim($_POST['email_nhan'] ?? ''),
        'dia_chi_nhan' => trim($_POST['dia_chi_nhan'] ?? ''),
        'ghi_chu_khach_hang' => trim($_POST['ghi_chu_khach_hang'] ?? ''),
        'phuong_thuc_thanh_toan' => $_POST['phuong_thuc_thanh_toan'] ?? 'cod',
        'phuong_thuc_van_chuyen' => $_POST['phuong_thuc_van_chuyen'] ?? 'giao_hang_nhanh'
    ];
    
    // Validate
    if (empty($form_data['ho_ten_nhan'])) {
        $errors[] = 'Họ tên người nhận không được để trống';
    }
    
    if (empty($form_data['so_dien_thoai_nhan'])) {
        $errors[] = 'Số điện thoại không được để trống';
    } elseif (!preg_match('/^[0-9]{10,11}$/', $form_data['so_dien_thoai_nhan'])) {
        $errors[] = 'Số điện thoại không hợp lệ';
    }
    
    if (!empty($form_data['email_nhan']) && !filter_var($form_data['email_nhan'], FILTER_VALIDATE_EMAIL)) {
        $errors[] = 'Email không hợp lệ';
    }
    
    if (empty($form_data['dia_chi_nhan'])) {
        $errors[] = 'Địa chỉ giao hàng không được để trống';
    }
    
    // Tính phí vận chuyển
    $phi_van_chuyen = 0;
    if ($subtotal < 500000) { // Miễn phí vận chuyển cho đơn từ 500k
        switch ($form_data['phuong_thuc_van_chuyen']) {
            case 'giao_hang_nhanh':
                $phi_van_chuyen = 30000;
                break;
            case 'giao_hang_tieu_chuan':
                $phi_van_chuyen = 20000;
                break;
            case 'giao_hang_hoa_toc':
                $phi_van_chuyen = 50000;
                break;
        }
    }
    
    $total_amount = $subtotal + $phi_van_chuyen;
    
    // Tạo đơn hàng nếu không có lỗi
    if (empty($errors)) {
        try {
            $pdo->beginTransaction();
            
            // Tạo mã đơn hàng
            $ma_don_hang = 'DH' . date('YmdHis') . rand(100, 999);
            
            // 🔧 FIX: INSERT đúng số cột và giá trị (BỎ tien_giam_gia)
            $stmt = $pdo->prepare("
                INSERT INTO don_hang 
                (ma_don_hang, khach_hang_id, ho_ten_nhan, so_dien_thoai_nhan, email_nhan, 
                 dia_chi_nhan, ghi_chu_khach_hang, tong_tien_hang, phi_van_chuyen,
                 tong_thanh_toan, phuong_thuc_thanh_toan, phuong_thuc_van_chuyen, trang_thai_thanh_toan) 
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
            ");
            
            $stmt->execute([
                $ma_don_hang, 
                $customer_id, 
                $form_data['ho_ten_nhan'], 
                $form_data['so_dien_thoai_nhan'], 
                $form_data['email_nhan'],
                $form_data['dia_chi_nhan'], 
                $form_data['ghi_chu_khach_hang'],
                $subtotal, 
                $phi_van_chuyen,
                $total_amount,
                $form_data['phuong_thuc_thanh_toan'], 
                $form_data['phuong_thuc_van_chuyen'],
                ($form_data['phuong_thuc_thanh_toan'] == 'vnpay' ? 'cho_thanh_toan' : 'chua_thanh_toan')
            ]);
            
            $don_hang_id = $pdo->lastInsertId();
            
            // Insert chi tiết đơn hàng
            $stmt_ctdh = $pdo->prepare("
                INSERT INTO chi_tiet_don_hang 
                (don_hang_id, san_pham_id, bien_the_id, ten_san_pham, thuong_hieu, 
                 kich_co, mau_sac, ma_sku, hinh_anh, so_luong, gia_ban, thanh_tien)
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
            ");
            
            foreach ($cart_items as $item) {
                $stmt_ctdh->execute([
                    $don_hang_id, 
                    $item['san_pham_id'], 
                    $item['bien_the_id'],
                    $item['ten_san_pham'], 
                    $item['thuong_hieu'],
                    $item['kich_co'], 
                    $item['ten_mau'], 
                    $item['ma_sku'],
                    $item['hinh_anh_chinh'], 
                    $item['so_luong'], 
                    $item['gia_tai_thoi_diem'], 
                    $item['thanh_tien']
                ]);
            }
            
            $pdo->commit();
            
            // Xử lý theo phương thức thanh toán
            if ($form_data['phuong_thuc_thanh_toan'] == 'vnpay') {
                // 🔧 VNPAY: Lưu thông tin vào session để chuyển đến VNPay
                $_SESSION['vnpay_order'] = [
                    'order_id' => $don_hang_id,
                    'amount' => $total_amount,
                    'order_info' => "Thanh toan don hang #" . $ma_don_hang,
                    'customer_name' => $form_data['ho_ten_nhan'],
                    'customer_email' => $form_data['email_nhan'],
                    'customer_phone' => $form_data['so_dien_thoai_nhan']
                ];
                
                // Redirect đến VNPay create_payment.php
                redirect('/vnpay/create_payment.php');
                
            } else {
                // 🔧 COD: Xử lý đơn hàng COD
                $pdo->beginTransaction();
                
                // Trừ tồn kho
                foreach ($cart_items as $item) {
                    $pdo->prepare("
                        UPDATE bien_the_san_pham 
                        SET so_luong_ton_kho = so_luong_ton_kho - ?,
                            so_luong_da_ban = so_luong_da_ban + ?
                        WHERE id = ?
                    ")->execute([$item['so_luong'], $item['so_luong'], $item['bien_the_id']]);
                }
                
                // Xóa giỏ hàng
                $pdo->prepare("
                    DELETE FROM gio_hang 
                    WHERE khach_hang_id = ? OR session_id = ?
                ")->execute([$customer_id, $session_id]);
                
                // Cập nhật trạng thái đơn hàng COD
                $pdo->prepare("
                    UPDATE don_hang SET 
                        trang_thai_don_hang = 'cho_xac_nhan',
                        ngay_cap_nhat = NOW()
                    WHERE id = ?
                ")->execute([$don_hang_id]);
                
                $pdo->commit();
                
                // Thông báo thành công và chuyển đến trang success
                alert('Đặt hàng thành công! Mã đơn hàng: ' . $ma_don_hang, 'success');
                redirect('/customer/order_success.php?order=' . $ma_don_hang);
            }
            
        } catch (Exception $e) {
            $pdo->rollback();
            $errors[] = 'Có lỗi xảy ra khi tạo đơn hàng: ' . $e->getMessage();
            error_log('Checkout Error: ' . $e->getMessage());
        }
    }
}

// Hiển thị lỗi payment nếu có
if (isset($_GET['error']) && $_GET['error'] === 'vnpay') {
    if (isset($_SESSION['payment_error'])) {
        $errors[] = $_SESSION['payment_error'];
        unset($_SESSION['payment_error']);
    }
}

// Tính phí vận chuyển mặc định
$phi_van_chuyen = $subtotal >= 500000 ? 0 : 30000;
$total_amount = $subtotal + $phi_van_chuyen;
?>

<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Thanh toán - <?= SITE_NAME ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <style>
        .checkout-header {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            padding: 40px 0;
        }
        
        .checkout-section {
            background: white;
            border-radius: 10px;
            padding: 30px;
            margin-bottom: 20px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
        }
        
        .section-title {
            font-size: 1.25rem;
            font-weight: bold;
            margin-bottom: 20px;
            color: #495057;
            border-bottom: 2px solid #007bff;
            padding-bottom: 10px;
        }
        
        .shipping-option,
        .payment-option {
            border: 2px solid #dee2e6;
            border-radius: 8px;
            padding: 15px;
            margin-bottom: 10px;
            cursor: pointer;
            transition: all 0.3s;
        }
        
        .shipping-option:hover,
        .shipping-option.selected,
        .payment-option:hover,
        .payment-option.selected {
            border-color: #007bff;
            background-color: #f8f9ff;
        }
        
        .order-summary {
            background: #f8f9fa;
            border-radius: 10px;
            padding: 25px;
            position: sticky;
            top: 20px;
        }
        
        .product-image {
            width: 60px;
            height: 60px;
            object-fit: cover;
            border-radius: 8px;
        }
        
        .product-item {
            border-bottom: 1px solid #eee;
            padding: 15px 0;
        }
        
        .product-item:last-child {
            border-bottom: none;
        }
        
        .summary-row {
            display: flex;
            justify-content: space-between;
            margin-bottom: 10px;
        }
        
        .summary-row.total {
            border-top: 2px solid #dee2e6;
            padding-top: 15px;
            margin-top: 15px;
            font-weight: bold;
            font-size: 1.1rem;
        }
        
        .payment-methods {
            display: grid;
            gap: 15px;
        }
        
        .payment-method {
            border: 2px solid #e9ecef;
            border-radius: 10px;
            padding: 20px;
            cursor: pointer;
            transition: all 0.3s ease;
            background: white;
        }
        
        .payment-method:hover,
        .payment-method.selected {
            border-color: #007bff;
            background: #f8f9ff;
            transform: translateY(-2px);
            box-shadow: 0 4px 12px rgba(0,123,255,0.15);
        }
        
        .payment-method input[type="radio"] {
            margin-right: 15px;
            transform: scale(1.2);
        }
        
        .payment-icon {
            font-size: 2rem;
            margin-right: 15px;
            vertical-align: middle;
        }
        
        .vnpay-icon {
            background: linear-gradient(45deg, #1e3c72, #2a5298);
            color: white;
            padding: 8px 12px;
            border-radius: 6px;
            font-weight: bold;
            font-size: 1.2rem;
        }
        
        .cod-icon {
            color: #28a745;
        }
    </style>
</head>
<body>
    <!-- Header -->
    <div class="checkout-header">
        <div class="container">
            <div class="text-center">
                <h2><i class="fas fa-credit-card me-2"></i>Thanh toán</h2>
                <p class="mb-0">Hoàn tất đơn hàng của bạn</p>
            </div>
        </div>
    </div>
    
    <div class="container py-4">
        <?php if (!empty($errors)): ?>
            <div class="alert alert-danger">
                <strong><i class="fas fa-exclamation-triangle me-2"></i>Có lỗi xảy ra:</strong>
                <ul class="mb-0 mt-2">
                    <?php foreach ($errors as $error): ?>
                        <li><?= htmlspecialchars($error) ?></li>
                    <?php endforeach; ?>
                </ul>
            </div>
        <?php endif; ?>
        
        <form method="POST" id="checkoutForm">
            <div class="row">
                <!-- Left Column -->
                <div class="col-lg-8">
                    <!-- Customer Information -->
                    <div class="checkout-section">
                        <div class="section-title">
                            <i class="fas fa-user me-2"></i>Thông tin người nhận
                        </div>
                        
                        <div class="row">
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label class="form-label">Họ và tên <span class="text-danger">*</span></label>
                                    <input type="text" 
                                           class="form-control" 
                                           name="ho_ten_nhan" 
                                           value="<?= htmlspecialchars($form_data['ho_ten_nhan'] ?? $customer_info['ho_ten'] ?? '') ?>"
                                           required>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label class="form-label">Số điện thoại <span class="text-danger">*</span></label>
                                    <input type="tel" 
                                           class="form-control" 
                                           name="so_dien_thoai_nhan" 
                                           value="<?= htmlspecialchars($form_data['so_dien_thoai_nhan'] ?? $customer_info['so_dien_thoai'] ?? '') ?>"
                                           required>
                                </div>
                            </div>
                        </div>
                        
                        <div class="mb-3">
                            <label class="form-label">Email</label>
                            <input type="email" 
                                   class="form-control" 
                                   name="email_nhan" 
                                   value="<?= htmlspecialchars($form_data['email_nhan'] ?? $customer_info['email'] ?? '') ?>">
                            <div class="form-text">Email để nhận thông báo đơn hàng</div>
                        </div>
                        
                        <div class="mb-3">
                            <label class="form-label">Địa chỉ giao hàng <span class="text-danger">*</span></label>
                            <textarea class="form-control" 
                                      name="dia_chi_nhan" 
                                      rows="3" 
                                      placeholder="Số nhà, tên đường, phường/xã, quận/huyện, tỉnh/thành phố"
                                      required><?= htmlspecialchars($form_data['dia_chi_nhan'] ?? $customer_info['dia_chi'] ?? '') ?></textarea>
                        </div>
                        
                        <div class="mb-3">
                            <label class="form-label">Ghi chú đơn hàng</label>
                            <textarea class="form-control" 
                                      name="ghi_chu_khach_hang" 
                                      rows="2" 
                                      placeholder="Ghi chú thêm cho đơn hàng (tuỳ chọn)"><?= htmlspecialchars($form_data['ghi_chu_khach_hang'] ?? '') ?></textarea>
                        </div>
                    </div>
                    
                    <!-- Shipping Method -->
                    <div class="checkout-section">
                        <div class="section-title">
                            <i class="fas fa-shipping-fast me-2"></i>Phương thức vận chuyển
                        </div>
                        
                        <div class="shipping-option selected" onclick="selectShipping('giao_hang_nhanh', 30000)">
                            <div class="d-flex justify-content-between align-items-center">
                                <div>
                                    <input type="radio" name="phuong_thuc_van_chuyen" value="giao_hang_nhanh" checked>
                                    <strong class="ms-2">Giao hàng nhanh</strong>
                                    <div class="text-muted small mt-1">Giao trong 1-2 ngày</div>
                                </div>
                                <div class="text-end">
                                    <div class="fw-bold" id="shipping-cost-nhanh">30.000đ</div>
                                    <div class="text-success small">Miễn phí từ 500k</div>
                                </div>
                            </div>
                        </div>
                        
                        <div class="shipping-option" onclick="selectShipping('giao_hang_tieu_chuan', 20000)">
                            <div class="d-flex justify-content-between align-items-center">
                                <div>
                                    <input type="radio" name="phuong_thuc_van_chuyen" value="giao_hang_tieu_chuan">
                                    <strong class="ms-2">Giao hàng tiêu chuẩn</strong>
                                    <div class="text-muted small mt-1">Giao trong 3-5 ngày</div>
                                </div>
                                <div class="text-end">
                                    <div class="fw-bold">20.000đ</div>
                                    <div class="text-success small">Miễn phí từ 500k</div>
                                </div>
                            </div>
                        </div>
                        
                        <div class="shipping-option" onclick="selectShipping('giao_hang_hoa_toc', 50000)">
                            <div class="d-flex justify-content-between align-items-center">
                                <div>
                                    <input type="radio" name="phuong_thuc_van_chuyen" value="giao_hang_hoa_toc">
                                    <strong class="ms-2">Giao hàng hoả tốc</strong>
                                    <div class="text-muted small mt-1">Giao trong ngày (nội thành)</div>
                                </div>
                                <div class="text-end">
                                    <div class="fw-bold">50.000đ</div>
                                </div>
                            </div>
                        </div>
                    </div>
                    
                    <!-- Payment Method -->
                    <div class="checkout-section">
                        <div class="section-title">
                            <i class="fas fa-credit-card me-2"></i>Phương thức thanh toán
                        </div>
                        
                        <div class="payment-methods">
                            <!-- VNPay Payment -->
                            <div class="payment-method selected" onclick="selectPayment('vnpay')">
                                <div class="d-flex align-items-center">
                                    <input type="radio" name="phuong_thuc_thanh_toan" value="vnpay" checked>
                                    <div class="payment-icon">
                                        <span class="vnpay-icon">VNPay</span>
                                    </div>
                                    <div class="flex-grow-1">
                                        <div class="fw-bold text-primary">Thanh toán VNPay</div>
                                        <div class="text-muted small">
                                            Thanh toán qua ví điện tử, ngân hàng, QR Code
                                        </div>
                                        <div class="mt-2">
                                            <span class="badge bg-success me-1">An toàn</span>
                                            <span class="badge bg-info me-1">Nhanh chóng</span>
                                            <span class="badge bg-warning">Ưu đãi</span>
                                        </div>
                                    </div>
                                </div>
                            </div>
                            
                            <!-- COD Payment -->
                            <div class="payment-method" onclick="selectPayment('cod')">
                                <div class="d-flex align-items-center">
                                    <input type="radio" name="phuong_thuc_thanh_toan" value="cod">
                                    <div class="payment-icon">
                                        <i class="fas fa-money-bill-wave cod-icon"></i>
                                    </div>
                                    <div class="flex-grow-1">
                                        <div class="fw-bold text-success">Thanh toán khi nhận hàng (COD)</div>
                                        <div class="text-muted small">
                                            Thanh toán tiền mặt khi shipper giao hàng
                                        </div>
                                        <div class="mt-2">
                                            <span class="badge bg-secondary me-1">Tiền mặt</span>
                                            <span class="badge bg-primary">Kiểm tra hàng trước</span>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                        
                        <!-- Payment Security Info -->
                        <div class="mt-3 p-3 bg-light rounded">
                            <div class="d-flex align-items-center">
                                <i class="fas fa-shield-alt text-success me-2"></i>
                                <div>
                                    <strong class="text-success">Giao dịch được bảo mật</strong>
                                    <div class="small text-muted">
                                        Thông tin thanh toán của bạn được mã hóa SSL 256-bit
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                
                <!-- Right Column - Order Summary -->
                <div class="col-lg-4">
                    <div class="order-summary">
                        <h5 class="mb-4">
                            <i class="fas fa-receipt me-2"></i>Tóm tắt đơn hàng
                        </h5>
                        
                        <!-- Products -->
                        <div class="mb-4">
                            <?php foreach ($cart_items as $item): ?>
                                <div class="product-item">
                                    <div class="d-flex">
                                        <div class="me-3">
                                            <img src="/tktshop/uploads/products/<?= $item['hinh_anh_chinh'] ?: 'default-product.jpg' ?>" 
                                                 class="product-image" 
                                                 alt="<?= htmlspecialchars($item['ten_san_pham']) ?>"
                                                 onerror="this.src='/tktshop/assets/images/no-image.jpg'">
                                        </div>
                                        <div class="flex-grow-1">
                                            <h6 class="mb-1"><?= htmlspecialchars($item['ten_san_pham']) ?></h6>
                                            <div class="text-muted small mb-1">
                                                Size <?= $item['kich_co'] ?> - <?= htmlspecialchars($item['ten_mau']) ?>
                                            </div>
                                            <div class="d-flex justify-content-between">
                                                <span class="text-muted">x<?= $item['so_luong'] ?></span>
                                                <span class="fw-bold"><?= formatPrice($item['thanh_tien']) ?></span>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                        
                        <!-- Summary -->
                        <div class="summary-row">
                            <span>Tạm tính (<?= $total_quantity ?> sản phẩm):</span>
                            <span id="subtotalAmount"><?= formatPrice($subtotal) ?></span>
                        </div>
                        
                        <div class="summary-row">
                            <span>Phí vận chuyển:</span>
                            <span id="shippingAmount"><?= $phi_van_chuyen == 0 ? 'Miễn phí' : formatPrice($phi_van_chuyen) ?></span>
                        </div>
                        
                        <?php if ($subtotal >= 500000): ?>
                            <div class="summary-row text-success">
                                <small><i class="fas fa-check me-1"></i>Miễn phí vận chuyển</small>
                            </div>
                        <?php endif; ?>
                        
                        <div class="summary-row total">
                            <span>Tổng cộng:</span>
                            <span class="text-primary" id="totalAmount"><?= formatPrice($total_amount) ?></span>
                        </div>
                        
                        <!-- Checkout Button -->
                        <div class="d-grid mt-4">
                            <button type="submit" class="btn btn-primary btn-lg" id="submitBtn">
                                <i class="fas fa-credit-card me-2"></i>
                                <span id="submitText">Thanh toán VNPay</span>
                            </button>
                        </div>
                        
                        <div class="text-center mt-3">
                            <a href="/customer/cart.php" class="text-muted text-decoration-none">
                                <i class="fas fa-arrow-left me-1"></i>Quay lại giỏ hàng
                            </a>
                        </div>
                        
                        <!-- Trust Badges -->
                        <div class="mt-4 pt-3 border-top">
                            <div class="row text-center">
                                <div class="col-4">
                                    <i class="fas fa-truck text-primary"></i>
                                    <div class="small text-muted mt-1">Giao hàng nhanh</div>
                                </div>
                                <div class="col-4">
                                    <i class="fas fa-shield-alt text-success"></i>
                                    <div class="small text-muted mt-1">Bảo mật</div>
                                </div>
                                <div class="col-4">
                                    <i class="fas fa-headset text-info"></i>
                                    <div class="small text-muted mt-1">Hỗ trợ 24/7</div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </form>
    </div>
    
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
    
    <script>
        const subtotal = <?= $subtotal ?>;
        let currentShippingFee = <?= $phi_van_chuyen ?>;
        
        // Select shipping method
        function selectShipping(method, fee) {
            // Update UI
            document.querySelectorAll('.shipping-option').forEach(el => el.classList.remove('selected'));
            event.currentTarget.classList.add('selected');
            
            // Update radio
            document.querySelector(`input[value="${method}"]`).checked = true;
            
            // Calculate shipping fee (free if order >= 500k)
            const finalFee = subtotal >= 500000 ? 0 : fee;
            currentShippingFee = finalFee;
            
            // Update display
            document.getElementById('shippingAmount').textContent = 
                finalFee === 0 ? 'Miễn phí' : formatPrice(finalFee);
            document.getElementById('totalAmount').textContent = formatPrice(subtotal + finalFee);
        }
        
        // Select payment method
        function selectPayment(method) {
            // Update UI
            document.querySelectorAll('.payment-method').forEach(el => el.classList.remove('selected'));
            event.currentTarget.classList.add('selected');
            
            // Update radio
            document.querySelector(`input[name="phuong_thuc_thanh_toan"][value="${method}"]`).checked = true;
            
            // Update submit button text
            const submitBtn = document.getElementById('submitText');
            const submitIcon = document.querySelector('#submitBtn i');
            
            if (method === 'vnpay') {
                submitBtn.textContent = 'Thanh toán VNPay';
                submitIcon.className = 'fas fa-credit-card me-2';
            } else {
                submitBtn.textContent = 'Đặt hàng COD';
                submitIcon.className = 'fas fa-shopping-bag me-2';
            }
        }
        
        // Format price
        function formatPrice(amount) {
            return new Intl.NumberFormat('vi-VN', {
                style: 'currency',
                currency: 'VND',
                minimumFractionDigits: 0
            }).format(amount);
        }
        
        // Form validation and submission
        document.getElementById('checkoutForm').addEventListener('submit', function(e) {
            const requiredFields = ['ho_ten_nhan', 'so_dien_thoai_nhan', 'dia_chi_nhan'];
            let isValid = true;
            
            // Validate required fields
            requiredFields.forEach(fieldName => {
                const field = document.querySelector(`[name="${fieldName}"]`);
                if (!field.value.trim()) {
                    field.classList.add('is-invalid');
                    isValid = false;
                } else {
                    field.classList.remove('is-invalid');
                }
            });
            
            // Validate phone number
            const phone = document.querySelector('[name="so_dien_thoai_nhan"]').value;
            if (phone && !/^[0-9]{10,11}$/.test(phone)) {
                document.querySelector('[name="so_dien_thoai_nhan"]').classList.add('is-invalid');
                isValid = false;
            }
            
            // Validate email if provided
            const email = document.querySelector('[name="email_nhan"]').value;
            if (email && !/^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(email)) {
                document.querySelector('[name="email_nhan"]').classList.add('is-invalid');
                isValid = false;
            }
            
            if (!isValid) {
                e.preventDefault();
                showToast('Vui lòng điền đầy đủ thông tin bắt buộc', 'error');
                return false;
            }
            
            // Show loading state
            const submitBtn = document.getElementById('submitBtn');
            const originalText = submitBtn.innerHTML;
            submitBtn.innerHTML = '<i class="fas fa-spinner fa-spin me-2"></i>Đang xử lý...';
            submitBtn.disabled = true;
            
            // Re-enable button after 15 seconds (in case of errors)
            setTimeout(() => {
                submitBtn.innerHTML = originalText;
                submitBtn.disabled = false;
            }, 15000);
        });
        
        // Initialize
        document.addEventListener('DOMContentLoaded', function() {
            // Initialize shipping fee display for free shipping
            if (subtotal >= 500000) {
                document.getElementById('shipping-cost-nhanh').innerHTML = 
                    '<span class="text-decoration-line-through">30.000đ</span> <span class="text-success">Miễn phí</span>';
            }
            
            // Phone number formatting
            const phoneInput = document.querySelector('[name="so_dien_thoai_nhan"]');
            if (phoneInput) {
                phoneInput.addEventListener('input', function() {
                    this.value = this.value.replace(/[^0-9]/g, '');
                });
            }
            
            // Real-time validation
            const inputs = document.querySelectorAll('input[required], textarea[required]');
            inputs.forEach(input => {
                input.addEventListener('blur', function() {
                    if (!this.value.trim()) {
                        this.classList.add('is-invalid');
                    } else {
                        this.classList.remove('is-invalid');
                    }
                });
                
                input.addEventListener('input', function() {
                    if (this.classList.contains('is-invalid') && this.value.trim()) {
                        this.classList.remove('is-invalid');
                    }
                });
            });
        });
        
        // Toast notification
        function showToast(message, type = 'info') {
            const toast = document.createElement('div');
            toast.className = `alert alert-${type === 'error' ? 'danger' : type} alert-dismissible fade show position-fixed`;
            toast.style.cssText = 'top: 20px; right: 20px; z-index: 9999; min-width: 300px;';
            toast.innerHTML = `
                ${message}
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            `;
            
            document.body.appendChild(toast);
            
            setTimeout(() => {
                if (toast.parentNode) {
                    toast.parentNode.removeChild(toast);
                }
            }, 5000);
        }
        
        // Prevent double submission
        let isSubmitting = false;
        document.getElementById('checkoutForm').addEventListener('submit', function(e) {
            if (isSubmitting) {
                e.preventDefault();
                return false;
            }
            isSubmitting = true;
        });
    </script>
</body>
</html>