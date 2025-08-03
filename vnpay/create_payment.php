<?php
// vnpay/create_payment.php
/**
 * Tạo thanh toán VNPay - Tích hợp vào checkout
 */

require_once '../config/database.php';
require_once '../config/config.php';
require_once 'config.php';

session_start();

// Kiểm tra session
if (!isset($_SESSION['vnpay_order'])) {
    header('Location: ../customer/cart.php');
    exit;
}

try {
    // Lấy thông tin từ session
    $vnpay_order = $_SESSION['vnpay_order'];
    $order_id = (int)($vnpay_order['order_id'] ?? 0);
    $amount = (float)($vnpay_order['amount'] ?? 0);
    $order_info = $vnpay_order['order_info'] ?? '';
    $customer_name = $vnpay_order['customer_name'] ?? '';
    $customer_email = $vnpay_order['customer_email'] ?? '';
    $customer_phone = $vnpay_order['customer_phone'] ?? '';
    $bank_code = $_GET['bank_code'] ?? '';
    $language = $_GET['language'] ?? 'vn';
    
    // Validation
    if ($order_id <= 0) {
        throw new Exception('Mã đơn hàng không hợp lệ');
    }
    
    if ($amount <= 0) {
        throw new Exception('Số tiền thanh toán không hợp lệ');
    }
    
    if (empty($customer_name) || empty($customer_email)) {
        throw new Exception('Thông tin khách hàng không đầy đủ');
    }
    
    // Kiểm tra đơn hàng tồn tại và chưa thanh toán
    $stmt = $pdo->prepare("
        SELECT * FROM don_hang 
        WHERE id = ? AND trang_thai_thanh_toan = 'chua_thanh_toan'
    ");
    $stmt->execute([$order_id]);
    $order = $stmt->fetch();
    
    if (!$order) {
        throw new Exception('Đơn hàng không tồn tại hoặc đã được thanh toán');
    }
    
    // Kiểm tra số tiền khớp với đơn hàng
    if (abs($order['tong_tien'] - $amount) > 1) {
        throw new Exception('Số tiền thanh toán không khớp với đơn hàng');
    }
    
    // Tạo mã giao dịch unique
    $vnp_TxnRef = generateVNPayOrderCode($order_id);
    
    // Lưu thông tin giao dịch vào database
    $stmt = $pdo->prepare("
        INSERT INTO thanh_toan_vnpay (
            don_hang_id, vnp_txn_ref, vnp_amount, vnp_order_info, 
            trang_thai, du_lieu_request, url_thanh_toan, ngay_tao
        ) VALUES (?, ?, ?, ?, 'khoi_tao', ?, ?, NOW())
    ");
    $stmt->execute([
        $order_id, $vnp_TxnRef, formatVNPayAmount($amount), $order_info ?: "Thanh toan don hang #" . $order_id,
        json_encode($inputData), $vnp_Url
    ]);
    
    $transaction_id = $pdo->lastInsertId();
    
    // Tạo dữ liệu cho VNPay
    $vnp_IpAddr = $_SERVER['REMOTE_ADDR'];
    
    $inputData = [
        "vnp_Version" => "2.1.0",
        "vnp_TmnCode" => $vnp_TmnCode,
        "vnp_Amount" => formatVNPayAmount($amount),
        "vnp_Command" => "pay",
        "vnp_CreateDate" => date('YmdHis'),
        "vnp_CurrCode" => "VND",
        "vnp_IpAddr" => $vnp_IpAddr,
        "vnp_Locale" => $language,
        "vnp_OrderInfo" => $order_info ?: "Thanh toan don hang #" . $order_id,
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
    
    $vnp_Url = $vnp_Url . "?" . $query . 'vnp_SecureHash=' . $vnpSecureHash;
    
    // Log giao dịch
    logVNPayTransaction('create_payment', [
        'transaction_id' => $transaction_id,
        'order_id' => $order_id,
        'vnp_txn_ref' => $vnp_TxnRef,
        'amount' => $amount,
        'customer_email' => $customer_email
    ]);
    
    // Cập nhật transaction với VNPay URL
    $pdo->prepare("UPDATE thanh_toan_vnpay SET url_thanh_toan = ? WHERE id = ?")
        ->execute([$vnp_Url, $transaction_id]);
    
    // Xóa session VNPay order
    unset($_SESSION['vnpay_order']);
    
    // Redirect đến VNPay
    header('Location: ' . $vnp_Url);
    exit;
    
} catch (Exception $e) {
    // Log lỗi
    error_log('VNPay Create Payment Error: ' . $e->getMessage());
    
    // Redirect về checkout với lỗi
    $_SESSION['payment_error'] = $e->getMessage();
    header('Location: ../customer/checkout.php?error=vnpay');
    exit;
}
?>