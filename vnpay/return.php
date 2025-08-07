<?php
// vnpay/return.php
/**
 * Xử lý kết quả thanh toán từ VNPay
 */

require_once '../config/database.php';
require_once '../config/config.php';
require_once 'config.php';

session_start();

$success = false;
$message = '';
$order_info = [];
$vnpay_info = [];

try {
    // Lấy dữ liệu từ VNPay
    if (empty($_GET)) {
        throw new Exception('Không nhận được dữ liệu từ VNPay');
    }
    
    $inputData = [];
    foreach ($_GET as $key => $value) {
        if (substr($key, 0, 4) == "vnp_") {
            $inputData[$key] = $value;
        }
    }
    
    $vnp_SecureHash = $inputData['vnp_SecureHash'] ?? '';
    
    // Verify secure hash
    if (!verifyVNPaySecureHash($inputData, $vnp_HashSecret, $vnp_SecureHash)) {
        throw new Exception('Chữ ký không hợp lệ');
    }
    
    // Lấy thông tin giao dịch
    $vnp_TxnRef = $inputData['vnp_TxnRef'] ?? '';
    $vnp_Amount = parseVNPayAmount($inputData['vnp_Amount'] ?? 0);
    $vnp_OrderInfo = $inputData['vnp_OrderInfo'] ?? '';
    $vnp_ResponseCode = $inputData['vnp_ResponseCode'] ?? '';
    $vnp_TransactionNo = $inputData['vnp_TransactionNo'] ?? '';
    $vnp_BankCode = $inputData['vnp_BankCode'] ?? '';
    $vnp_PayDate = $inputData['vnp_PayDate'] ?? '';
    $vnp_TransactionStatus = $inputData['vnp_TransactionStatus'] ?? '';
    
    // Tìm giao dịch trong database
    $stmt = $pdo->prepare("
        SELECT vt.*, dh.id as order_id, dh.tong_thanh_toan, dh.trang_thai_thanh_toan, dh.khach_hang_id
        FROM thanh_toan_vnpay vt
        JOIN don_hang dh ON vt.don_hang_id = dh.id
        WHERE vt.vnp_txn_ref = ?
    ");
    $stmt->execute([$vnp_TxnRef]);
    $transaction = $stmt->fetch();
    
    if (!$transaction) {
        throw new Exception('Không tìm thấy giao dịch');
    }
    
    // Kiểm tra số tiền
    if (abs($transaction['tong_thanh_toan'] - $vnp_Amount) > 1) {
        throw new Exception('Số tiền không khớp');
    }
    
    // Kiểm tra trạng thái giao dịch (tránh xử lý trùng lặp)
    if ($transaction['trang_thai'] !== 'khoi_tao' && $transaction['trang_thai'] !== 'cho_thanh_toan') {
        // Đã xử lý rồi, chỉ hiển thị kết quả
        $success = ($transaction['trang_thai'] === 'thanh_cong');
        $message = $success ? 'Giao dịch đã được xử lý thành công trước đó' : 'Giao dịch đã thất bại';
    } else {
        // Xử lý kết quả thanh toán
        if ($vnp_ResponseCode === '00' && $vnp_TransactionStatus === '00') {
            // Thanh toán thành công
            $pdo->beginTransaction();
            
            try {
                // Cập nhật trạng thái giao dịch VNPay
                $stmt = $pdo->prepare("
                    UPDATE thanh_toan_vnpay SET 
                        trang_thai = 'thanh_cong',
                        vnp_response_code = ?,
                        vnp_transaction_no = ?,
                        vnp_bank_code = ?,
                        vnp_pay_date = ?,
                        vnp_transaction_status = ?,
                        du_lieu_response = ?,
                        ngay_thanh_toan = NOW(),
                        ngay_cap_nhat = NOW()
                    WHERE vnp_txn_ref = ?
                ");
                $stmt->execute([
                    $vnp_ResponseCode, $vnp_TransactionNo, $vnp_BankCode,
                    $vnp_PayDate, $vnp_TransactionStatus, 
                    json_encode($inputData), $vnp_TxnRef
                ]);
                
                // Cập nhật trạng thái đơn hàng
                $stmt = $pdo->prepare("
                    UPDATE don_hang SET
                        trang_thai_thanh_toan = 'da_thanh_toan',
                        trang_thai_don_hang = 'cho_xac_nhan',
                        ngay_thanh_toan = NOW(),
                        ghi_chu_admin = CONCAT(COALESCE(ghi_chu_admin, ''), ?)
                    WHERE id = ?
                ");
                $stmt->execute([
                    "\nVNPay - Mã GD: {$vnp_TransactionNo} - Ngân hàng: {$vnp_BankCode}",
                    $transaction['order_id']
                ]);
                
                // Cập nhật tồn kho sản phẩm
                $stmt = $pdo->prepare("
                    UPDATE bien_the_san_pham bsp
                    JOIN chi_tiet_don_hang ctdh ON bsp.id = ctdh.bien_the_id
                    SET bsp.so_luong_ton_kho = bsp.so_luong_ton_kho - ctdh.so_luong
                    WHERE ctdh.don_hang_id = ?
                ");
                $stmt->execute([$transaction['order_id']]);
                
                // Xóa giỏ hàng của khách hàng
                $customer_id = $transaction['khach_hang_id'];
                $session_id = $customer_id ? null : ($_SESSION['session_id'] ?? null);
                if ($customer_id || $session_id) {
                    $pdo->prepare("DELETE FROM gio_hang WHERE khach_hang_id = ? OR session_id = ?")
                        ->execute([$customer_id, $session_id]);
                }
                
                $pdo->commit();
                
                $success = true;
                $message = 'Thanh toán thành công';
                
                // Gửi email xác nhận (optional)
                // sendOrderConfirmationEmail($transaction['order_id']);
                
            } catch (Exception $e) {
                $pdo->rollBack();
                throw $e;
            }
            
        } else {
            // Thanh toán thất bại
            $error_message = $vnpay_response_codes[$vnp_ResponseCode] ?? 'Lỗi không xác định';
            
            $stmt = $pdo->prepare("
                UPDATE thanh_toan_vnpay SET 
                    trang_thai = 'that_bai',
                    vnp_response_code = ?,
                    vnp_transaction_status = ?,
                    du_lieu_response = ?,
                    ngay_cap_nhat = NOW()
                WHERE vnp_txn_ref = ?
            ");
            $stmt->execute([
                $vnp_ResponseCode, $vnp_TransactionStatus,
                json_encode($inputData), $vnp_TxnRef
            ]);
            
            // Cập nhật trạng thái đơn hàng là thanh toán thất bại
            $pdo->prepare("
                UPDATE don_hang SET
                    trang_thai_thanh_toan = 'that_bai'
                WHERE id = ?
            ")->execute([$transaction['order_id']]);
            
            $success = false;
            $message = 'Thanh toán thất bại: ' . $error_message;
        }
    }
    
    // Lấy thông tin đơn hàng để hiển thị
    $stmt = $pdo->prepare("
        SELECT dh.*, nd.ho_ten, nd.email, nd.so_dien_thoai,
               vt.vnp_transaction_no, vt.vnp_bank_code, vt.vnp_pay_date, vt.trang_thai as vnpay_status
        FROM don_hang dh
        LEFT JOIN nguoi_dung nd ON dh.khach_hang_id = nd.id
        LEFT JOIN thanh_toan_vnpay vt ON dh.id = vt.don_hang_id
        WHERE dh.id = ?
    ");
    $stmt->execute([$transaction['order_id']]);
    $order_info = $stmt->fetch();
    
    $vnpay_info = [
        'txn_ref' => $vnp_TxnRef,
        'amount' => $vnp_Amount,
        'order_info' => $vnp_OrderInfo,
        'response_code' => $vnp_ResponseCode,
        'transaction_no' => $vnp_TransactionNo,
        'bank_code' => $vnp_BankCode,
        'pay_date' => $vnp_PayDate,
        'response_message' => $vnpay_response_codes[$vnp_ResponseCode] ?? 'Không xác định'
    ];
    
    // Log kết quả
    logVNPayTransaction('return', [
        'vnp_txn_ref' => $vnp_TxnRef,
        'response_code' => $vnp_ResponseCode,
        'success' => $success
    ], $inputData);
    
} catch (Exception $e) {
    $success = false;
    $message = $e->getMessage();
    
    // Log lỗi
    error_log('VNPay Return Error: ' . $e->getMessage());
    logVNPayTransaction('return_error', [
        'error' => $e->getMessage(),
        'get_data' => $_GET
    ]);
}

$page_title = $success ? 'Thanh toán thành công' : 'Thanh toán thất bại';
?>

<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= $page_title ?> - <?= SITE_NAME ?></title>
    
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    
    <style>
        .result-container {
            max-width: 800px;
            margin: 0 auto;
            padding: 40px 20px;
        }
        
        .result-card {
            background: white;
            border-radius: 15px;
            box-shadow: 0 10px 30px rgba(0,0,0,0.1);
            padding: 40px;
            text-align: center;
        }
        
        .result-icon {
            font-size: 4rem;
            margin-bottom: 20px;
        }
        
        .result-icon.success {
            color: #28a745;
        }
        
        .result-icon.failed {
            color: #dc3545;
        }
        
        .order-details {
            background: #f8f9fa;
            border-radius: 10px;
            padding: 20px;
            margin: 30px 0;
            text-align: left;
        }
        
        .detail-row {
            display: flex;
            justify-content: space-between;
            padding: 8px 0;
            border-bottom: 1px solid #dee2e6;
        }
        
        .detail-row:last-child {
            border-bottom: none;
        }
        
        .detail-label {
            font-weight: 600;
            color: #495057;
        }
        
        .detail-value {
            color: #212529;
        }
        
        .btn-group-custom {
            gap: 15px;
            margin-top: 30px;
        }
        
        @media (max-width: 768px) {
            .result-card {
                padding: 20px;
            }
            
            .result-icon {
                font-size: 3rem;
            }
            
            .detail-row {
                flex-direction: column;
                gap: 5px;
            }
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="result-container">
            <div class="result-card">
                <!-- Result Icon & Title -->
                <div class="result-icon <?= $success ? 'success' : 'failed' ?>">
                    <i class="fas fa-<?= $success ? 'check-circle' : 'times-circle' ?>"></i>
                </div>
                
                <h2 class="<?= $success ? 'text-success' : 'text-danger' ?> mb-3">
                    <?= $page_title ?>
                </h2>
                
                <p class="lead mb-4"><?= htmlspecialchars($message) ?></p>
                
                <?php if (!empty($order_info)): ?>
                    <!-- Order Details -->
                    <div class="order-details">
                        <h5 class="mb-3">
                            <i class="fas fa-receipt me-2"></i>
                            Thông tin đơn hàng
                        </h5>
                        
                        <div class="detail-row">
                            <span class="detail-label">Mã đơn hàng:</span>
                            <span class="detail-value">#<?= $order_info['id'] ?></span>
                        </div>
                        
                        <div class="detail-row">
                            <span class="detail-label">Khách hàng:</span>
                            <span class="detail-value"><?= htmlspecialchars($order_info['ho_ten'] ?: $order_info['ten_nguoi_nhan']) ?></span>
                        </div>
                        
                        <div class="detail-row">
                            <span class="detail-label">Email:</span>
                            <span class="detail-value"><?= htmlspecialchars($order_info['email'] ?: $order_info['email_nguoi_nhan']) ?></span>
                        </div>
                        
                        <div class="detail-row">
                            <span class="detail-label">Số điện thoại:</span>
                            <span class="detail-value"><?= htmlspecialchars($order_info['so_dien_thoai'] ?: $order_info['sdt_nguoi_nhan']) ?></span>
                        </div>
                        
                        <div class="detail-row">
                            <span class="detail-label">Tổng tiền:</span>
                            <span class="detail-value fw-bold text-primary"><?= formatPrice($order_info['tong_tien']) ?></span>
                        </div>
                        
                        <div class="detail-row">
                            <span class="detail-label">Trạng thái thanh toán:</span>
                            <span class="detail-value">
                                <span class="badge bg-<?= $success ? 'success' : 'danger' ?>">
                                    <?= $success ? 'Đã thanh toán' : 'Chưa thanh toán' ?>
                                </span>
                            </span>
                        </div>
                        
                        <div class="detail-row">
                            <span class="detail-label">Ngày đặt hàng:</span>
                            <span class="detail-value"><?= date('d/m/Y H:i', strtotime($order_info['ngay_dat_hang'])) ?></span>
                        </div>
                    </div>
                <?php endif; ?>
                
                <?php if (!empty($vnpay_info)): ?>
                    <!-- VNPay Transaction Details -->
                    <div class="order-details">
                        <h5 class="mb-3">
                            <i class="fas fa-credit-card me-2"></i>
                            Thông tin giao dịch VNPay
                        </h5>
                        
                        <div class="detail-row">
                            <span class="detail-label">Mã giao dịch:</span>
                            <span class="detail-value"><?= htmlspecialchars($vnpay_info['txn_ref']) ?></span>
                        </div>
                        
                        <?php if (!empty($vnpay_info['transaction_no'])): ?>
                            <div class="detail-row">
                                <span class="detail-label">Mã GD VNPay:</span>
                                <span class="detail-value"><?= htmlspecialchars($vnpay_info['transaction_no']) ?></span>
                            </div>
                        <?php endif; ?>
                        
                        <div class="detail-row">
                            <span class="detail-label">Số tiền:</span>
                            <span class="detail-value"><?= formatPrice($vnpay_info['amount']) ?></span>
                        </div>
                        
                        <?php if (!empty($vnpay_info['bank_code'])): ?>
                            <div class="detail-row">
                                <span class="detail-label">Ngân hàng:</span>
                                <span class="detail-value">
                                    <?= $vnpay_bank_codes[$vnpay_info['bank_code']] ?? $vnpay_info['bank_code'] ?>
                                </span>
                            </div>
                        <?php endif; ?>
                        
                        <?php if (!empty($vnpay_info['pay_date'])): ?>
                            <div class="detail-row">
                                <span class="detail-label">Thời gian thanh toán:</span>
                                <span class="detail-value">
                                    <?= date('d/m/Y H:i:s', strtotime($vnpay_info['pay_date'])) ?>
                                </span>
                            </div>
                        <?php endif; ?>
                        
                        <div class="detail-row">
                            <span class="detail-label">Mã phản hồi:</span>
                            <span class="detail-value">
                                <span class="badge bg-<?= $success ? 'success' : 'warning' ?>">
                                    <?= $vnpay_info['response_code'] ?>
                                </span>
                            </span>
                        </div>
                        
                        <div class="detail-row">
                            <span class="detail-label">Kết quả:</span>
                            <span class="detail-value"><?= htmlspecialchars($vnpay_info['response_message']) ?></span>
                        </div>
                    </div>
                <?php endif; ?>
                
                <!-- Action Buttons -->
                <div class="d-flex justify-content-center btn-group-custom">
                    <?php if ($success): ?>
                        <a href="../customer/order_tracking.php?order_id=<?= $order_info['id'] ?? '' ?>" 
                           class="btn btn-primary btn-lg">
                            <i class="fas fa-eye me-2"></i>
                            Xem đơn hàng
                        </a>
                        
                        <a href="../customer/products.php" class="btn btn-outline-primary btn-lg">
                            <i class="fas fa-shopping-bag me-2"></i>
                            Tiếp tục mua sắm
                        </a>
                    <?php else: ?>
                        <?php if (!empty($order_info)): ?>
                            <a href="../customer/checkout.php?order_id=<?= $order_info['id'] ?>" 
                               class="btn btn-primary btn-lg">
                                <i class="fas fa-redo me-2"></i>
                                Thử lại thanh toán
                            </a>
                        <?php endif; ?>
                        
                        <a href="../customer/cart.php" class="btn btn-outline-secondary btn-lg">
                            <i class="fas fa-shopping-cart me-2"></i>
                            Về giỏ hàng
                        </a>
                    <?php endif; ?>
                </div>
                
                <!-- Support Info -->
                <div class="mt-4 pt-4 border-top">
                    <p class="text-muted mb-2">
                        <small>
                            <i class="fas fa-info-circle me-1"></i>
                            Nếu bạn có bất kỳ thắc mắc nào, vui lòng liên hệ với chúng tôi
                        </small>
                    </p>
                    <div class="d-flex justify-content-center gap-3">
                        <a href="tel:1900123456" class="text-decoration-none">
                            <i class="fas fa-phone me-1"></i>
                            1900.123.456
                        </a>
                        <a href="mailto:support@example.com" class="text-decoration-none">
                            <i class="fas fa-envelope me-1"></i>
                            support@example.com
                        </a>
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
    
    <?php if ($success && !empty($order_info)): ?>
        <!-- Success tracking -->
        <script>
            // Google Analytics / Facebook Pixel tracking
            if (typeof gtag !== 'undefined') {
                gtag('event', 'purchase', {
                    transaction_id: '<?= $order_info['id'] ?>',
                    value: <?= $order_info['tong_tien'] ?>,
                    currency: 'VND',
                    items: [{
                        item_id: 'order_<?= $order_info['id'] ?>',
                        item_name: 'Order #<?= $order_info['id'] ?>',
                        quantity: 1,
                        price: <?= $order_info['tong_tien'] ?>
                    }]
                });
            }
            
            // Auto redirect after 30 seconds for successful payments
            setTimeout(function() {
                if (confirm('Bạn có muốn chuyển đến trang theo dõi đơn hàng?')) {
                    window.location.href = '../customer/order_tracking.php?order_id=<?= $order_info['id'] ?>';
                }
            }, 30000);
        </script>
    <?php endif; ?>
</body>
</html>