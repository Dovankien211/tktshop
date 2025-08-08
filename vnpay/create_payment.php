<?php
// vnpay/create_payment.php - FIXED VERSION
/**
 * Tạo thanh toán VNPay - Kết nối với checkout
 * 🔧 FIXED: Tương thích với hệ thống có sẵn
 */

require_once '../config/database.php';
require_once '../config/config.php';
require_once 'config.php';
require_once 'vnpay_helpers.php';

session_start();

// Kiểm tra session
if (!isset($_SESSION['vnpay_order'])) {
    alert('Phiên thanh toán đã hết hạn', 'warning');
    redirect('../customer/cart.php');
}

try {
    // Lấy thông tin từ session
    $vnpay_order = $_SESSION['vnpay_order'];
    $order_id = (int) ($vnpay_order['order_id'] ?? 0);
    $amount = (float) ($vnpay_order['amount'] ?? 0);
    $order_info = $vnpay_order['order_info'] ?? '';
    $customer_name = $vnpay_order['customer_name'] ?? '';
    $customer_email = $vnpay_order['customer_email'] ?? '';
    $customer_phone = $vnpay_order['customer_phone'] ?? '';
    $bank_code = $_GET['bank_code'] ?? '';
    $language = 'vn';
    
    // Validation session data
    if (!validateVNPaySession($vnpay_order)) {
        throw new Exception('Dữ liệu thanh toán không hợp lệ');
    }
    
    // Validate đơn hàng trong database
    $order = validateOrderForVNPay($pdo, $order_id, $amount);
    
    // Tạo mã tham chiếu giao dịch
    $vnp_TxnRef = generateVNPayOrderCode($order_id);
    
    // Tạo bản ghi VNPay transaction
    $transaction_id = createVNPayTransaction($pdo, $order_id, $amount, $vnp_TxnRef, $order_info, $customer_email);
    
    // Cập nhật trạng thái đơn hàng
    updateOrderForVNPay($pdo, $order_id);
    
    // Tạo dữ liệu cho VNPay
    $vnp_IpAddr = $_SERVER['REMOTE_ADDR'] ?? '127.0.0.1';
    
    $inputData = [
        "vnp_Version" => "2.1.0",
        "vnp_TmnCode" => $vnp_TmnCode,
        "vnp_Amount" => formatVNPayAmount($amount),
        "vnp_Command" => "pay",
        "vnp_CreateDate" => date('YmdHis'),
        "vnp_CurrCode" => "VND",
        "vnp_IpAddr" => $vnp_IpAddr,
        "vnp_Locale" => $language,
        "vnp_OrderInfo" => $order_info,
        "vnp_OrderType" => "other",
        "vnp_ReturnUrl" => $vnp_Returnurl,
        "vnp_TxnRef" => $vnp_TxnRef,
        "vnp_ExpireDate" => $expire
    ];
    
    // Thêm bank code nếu có
    if (!empty($bank_code)) {
        $inputData['vnp_BankCode'] = $bank_code;
    }
    
    // Tạo secure hash
    $vnpSecureHash = createVNPaySecureHash($inputData, $vnp_HashSecret);
    
    // Tạo URL thanh toán
    ksort($inputData);
    $query = "";
    foreach ($inputData as $key => $value) {
        $query .= urlencode($key) . "=" . urlencode($value) . '&';
    }
    
    $vnp_Url_final = $vnp_Url . "?" . $query . 'vnp_SecureHash=' . $vnpSecureHash;
    
    // Log giao dịch
    logVNPayTransaction('create_payment', [
        'transaction_id' => $transaction_id,
        'order_id' => $order_id,
        'vnp_txn_ref' => $vnp_TxnRef,
        'amount' => $amount,
        'customer_email' => $customer_email,
        'bank_code' => $bank_code
    ]);
    
    // Cập nhật transaction với URL và trạng thái
    $pdo->prepare("
        UPDATE thanh_toan_vnpay SET 
            url_thanh_toan = ?,
            trang_thai = 'cho_thanh_toan',
            du_lieu_request = ?
        WHERE id = ?
    ")->execute([$vnp_Url_final, json_encode($inputData), $transaction_id]);
    
    // Xóa session VNPay order
    unset($_SESSION['vnpay_order']);
    
    // Hiển thị trang xác nhận trước khi redirect
    $show_confirmation = $_GET['confirm'] ?? false;
    
    if (!$show_confirmation) {
        // Redirect trực tiếp đến VNPay
        header('Location: ' . $vnp_Url_final);
        exit;
    }
    
} catch (Exception $e) {
    // Log lỗi
    error_log('VNPay Create Payment Error: ' . $e->getMessage());
    
    // Redirect về checkout với lỗi
    $_SESSION['payment_error'] = $e->getMessage();
    redirect('../customer/checkout.php?error=vnpay');
}
?>

<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Chuyển hướng thanh toán VNPay</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <style>
        .payment-redirect {
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
        }
        
        .payment-card {
            background: white;
            border-radius: 15px;
            padding: 40px;
            text-align: center;
            box-shadow: 0 10px 30px rgba(0,0,0,0.3);
            max-width: 500px;
        }
        
        .vnpay-logo {
            width: 120px;
            margin-bottom: 20px;
        }
        
        .loading-spinner {
            margin: 20px 0;
        }
        
        .countdown {
            font-size: 1.2rem;
            font-weight: bold;
            color: #007bff;
        }
    </style>
</head>
<body>
    <div class="payment-redirect">
        <div class="payment-card">
            <div class="mb-4">
                <img src="/tktshop/assets/images/vnpay-logo.png" alt="VNPay" class="vnpay-logo" onerror="this.style.display='none'">
                <h3 class="text-primary">Chuyển hướng thanh toán</h3>
            </div>
            
            <div class="loading-spinner">
                <div class="spinner-border text-primary" role="status">
                    <span class="visually-hidden">Loading...</span>
                </div>
            </div>
            
            <p class="mb-3">Đang chuyển hướng đến cổng thanh toán VNPay...</p>
            <p class="text-muted">Vui lòng đợi <span class="countdown" id="countdown">5</span> giây</p>
            
            <div class="mt-4">
                <a href="<?= $vnp_Url_final ?>" class="btn btn-primary btn-lg">
                    <i class="fas fa-credit-card me-2"></i>
                    Thanh toán ngay
                </a>
            </div>
            
            <div class="mt-3">
                <a href="../customer/checkout.php" class="btn btn-outline-secondary">
                    <i class="fas fa-arrow-left me-1"></i>
                    Quay lại
                </a>
            </div>
        </div>
    </div>
    
    <script>
        // Auto redirect after 5 seconds
        let countdown = 5;
        const countdownElement = document.getElementById('countdown');
        
        const timer = setInterval(() => {
            countdown--;
            countdownElement.textContent = countdown;
            
            if (countdown <= 0) {
                clearInterval(timer);
                window.location.href = '<?= $vnp_Url_final ?>';
            }
        }, 1000);
        
        // Handle page visibility change (prevent redirect when user switches tabs)
        document.addEventListener('visibilitychange', function() {
            if (document.hidden) {
                clearInterval(timer);
            }
        });
    </script>
</body>
</html>