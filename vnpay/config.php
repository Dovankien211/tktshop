<?php
// vnpay/config.php
/**
 * Cấu hình VNPay - Tích hợp vào hệ thống
 */

date_default_timezone_set('Asia/Ho_Chi_Minh');

// VNPay Configuration
$vnp_TmnCode = "RYNQXLGK"; // Terminal ID từ VNPay cung cấp
$vnp_HashSecret = "YCYJDMIDW0V2NA5OCER3OIHD36VS67NU"; // Secret Key từ VNPay
$vnp_Url = "https://sandbox.vnpayment.vn/paymentv2/vpcpay.html"; // URL thanh toán sandbox
$vnp_Returnurl = "http://localhost/tktshop/vnpay/return.php"; // URL return sau thanh toán
$vnp_apiUrl = "https://sandbox.vnpayment.vn/merchant_webapi/api/transaction"; // API URL

// Config cho production (comment lại khi test)
// $vnp_Url = "https://vnpayment.vn/paymentv2/vpcpay.html";
// $vnp_apiUrl = "https://vnpayment.vn/merchant_webapi/api/transaction";

// Thời gian hết hạn thanh toán (15 phút)
$startTime = date("YmdHis");
$expire = date('YmdHis', strtotime('+15 minutes', strtotime($startTime)));

// Response codes VNPay
$vnpay_response_codes = [
    '00' => 'Giao dịch thành công',
    '07' => 'Trừ tiền thành công. Giao dịch bị nghi ngờ (liên quan tới lừa đảo, giao dịch bất thường).',
    '09' => 'Giao dịch không thành công do: Thẻ/Tài khoản của khách hàng chưa đăng ký dịch vụ InternetBanking tại ngân hàng.',
    '10' => 'Giao dịch không thành công do: Khách hàng xác thực thông tin thẻ/tài khoản không đúng quá 3 lần',
    '11' => 'Giao dịch không thành công do: Đã hết hạn chờ thanh toán. Xin quý khách vui lòng thực hiện lại giao dịch.',
    '12' => 'Giao dịch không thành công do: Thẻ/Tài khoản của khách hàng bị khóa.',
    '13' => 'Giao dịch không thành công do Quý khách nhập sai mật khẩu xác thực giao dịch (OTP). Xin quý khách vui lòng thực hiện lại giao dịch.',
    '24' => 'Giao dịch không thành công do: Khách hàng hủy giao dịch',
    '51' => 'Giao dịch không thành công do: Tài khoản của quý khách không đủ số dư để thực hiện giao dịch.',
    '65' => 'Giao dịch không thành công do: Tài khoản của Quý khách đã vượt quá hạn mức giao dịch trong ngày.',
    '75' => 'Ngân hàng thanh toán đang bảo trì.',
    '79' => 'Giao dịch không thành công do: KH nhập sai mật khẩu thanh toán quá số lần quy định. Xin quý khách vui lòng thực hiện lại giao dịch',
    '99' => 'Các lỗi khác (lỗi còn lại, không có trong danh sách mã lỗi đã liệt kê)'
];

// Bank codes VNPay hỗ trợ
$vnpay_bank_codes = [
    '' => 'Cổng thanh toán VNPAYQR',
    'VNPAYQR' => 'Thanh toán bằng ứng dụng hỗ trợ VNPAYQR',
    'VNBANK' => 'Thanh toán qua thẻ ATM/Tài khoản nội địa',
    'INTCARD' => 'Thanh toán qua thẻ quốc tế',
    'VIETCOMBANK' => 'Ngân hàng TMCP Ngoại Thương Việt Nam',
    'VIETINBANK' => 'Ngân hàng TMCP Công Thương Việt Nam',
    'BIDV' => 'Ngân hàng TMCP Đầu tư và Phát triển Việt Nam',
    'AGRIBANK' => 'Ngân hàng Nông nghiệp và Phát triển Nông thôn Việt Nam',
    'TECHCOMBANK' => 'Ngân hàng TMCP Kỹ thương Việt Nam',
    'ACB' => 'Ngân hàng TMCP Á Châu',
    'MBBANK' => 'Ngân hàng TMCP Quân đội',
    'TPBANK' => 'Ngân hàng TMCP Tiên Phong',
    'SACOMBANK' => 'Ngân hàng TMCP Sài Gòn Thương Tín',
    'SHB' => 'Ngân hàng TMCP Sài Gòn - Hà Nội'
];

/**
 * Hàm tạo secure hash cho VNPay
 */
function createVNPaySecureHash($inputData, $hashSecret) {
    ksort($inputData);
    $hashData = "";
    $i = 0;
    
    foreach ($inputData as $key => $value) {
        if ($i == 1) {
            $hashData .= '&' . urlencode($key) . "=" . urlencode($value);
        } else {
            $hashData .= urlencode($key) . "=" . urlencode($value);
            $i = 1;
        }
    }
    
    return hash_hmac('sha512', $hashData, $hashSecret);
}

/**
 * Hàm verify secure hash từ VNPay
 */
function verifyVNPaySecureHash($inputData, $hashSecret, $vnpSecureHash) {
    unset($inputData['vnp_SecureHash']);
    ksort($inputData);
    
    $hashData = "";
    $i = 0;
    
    foreach ($inputData as $key => $value) {
        if ($i == 1) {
            $hashData .= '&' . urlencode($key) . "=" . urlencode($value);
        } else {
            $hashData .= urlencode($key) . "=" . urlencode($value);
            $i = 1;
        }
    }
    
    $secureHash = hash_hmac('sha512', $hashData, $hashSecret);
    return $secureHash === $vnpSecureHash;
}

/**
 * Hàm gọi API VNPay
 */
function callVNPayAPI($method, $url, $data) {
    $curl = curl_init();
    
    switch ($method) {
        case "POST":
            curl_setopt($curl, CURLOPT_POST, 1);
            if ($data) {
                curl_setopt($curl, CURLOPT_POSTFIELDS, $data);
            }
            break;
        case "PUT":
            curl_setopt($curl, CURLOPT_CUSTOMREQUEST, "PUT");
            if ($data) {
                curl_setopt($curl, CURLOPT_POSTFIELDS, $data);
            }
            break;
        default:
            if ($data) {
                $url = sprintf("%s?%s", $url, http_build_query($data));
            }
    }
    
    curl_setopt($curl, CURLOPT_URL, $url);
    curl_setopt($curl, CURLOPT_HTTPHEADER, [
        'Content-Type: application/json'
    ]);
    curl_setopt($curl, CURLOPT_RETURNTRANSFER, 1);
    curl_setopt($curl, CURLOPT_TIMEOUT, 30);
    curl_setopt($curl, CURLOPT_SSL_VERIFYPEER, false);
    
    $result = curl_exec($curl);
    $httpCode = curl_getinfo($curl, CURLINFO_HTTP_CODE);
    
    if (curl_error($curl)) {
        curl_close($curl);
        throw new Exception('VNPay API Error: ' . curl_error($curl));
    }
    
    curl_close($curl);
    
    if ($httpCode !== 200) {
        throw new Exception('VNPay API HTTP Error: ' . $httpCode);
    }
    
    return $result;
}

/**
 * Hàm format số tiền cho VNPay (nhân 100)
 */
function formatVNPayAmount($amount) {
    return (int)($amount * 100);
}

/**
 * Hàm parse số tiền từ VNPay (chia 100)
 */
function parseVNPayAmount($amount) {
    return (int)($amount / 100);
}

/**
 * Hàm log giao dịch VNPay
 */
function logVNPayTransaction($type, $data, $response = null) {
    $logFile = __DIR__ . '/logs/vnpay_' . date('Y-m-d') . '.log';
    $logDir = dirname($logFile);
    
    if (!is_dir($logDir)) {
        mkdir($logDir, 0755, true);
    }
    
    $logEntry = [
        'timestamp' => date('Y-m-d H:i:s'),
        'type' => $type,
        'ip' => $_SERVER['REMOTE_ADDR'] ?? 'CLI',
        'data' => $data
    ];
    
    if ($response) {
        $logEntry['response'] = $response;
    }
    
    file_put_contents($logFile, json_encode($logEntry, JSON_UNESCAPED_UNICODE) . "\n", FILE_APPEND | LOCK_EX);
}

/**
 * Hàm tạo order code unique
 */
function generateVNPayOrderCode($orderId = null) {
    if ($orderId) {
        return 'ORD' . str_pad($orderId, 6, '0', STR_PAD_LEFT);
    }
    return 'TXN' . date('YmdHis') . rand(100, 999);
}

/**
 * Hàm kiểm tra IP VNPay (bảo mật)
 */
function isVNPayIP($ip) {
    $vnpay_ips = [
        '103.220.87.4',
        '103.220.87.5', 
        '203.171.19.146',
        '203.171.19.147'
    ];
    
    return in_array($ip, $vnpay_ips);
}
?>