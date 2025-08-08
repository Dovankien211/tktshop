<?php
// vnpay/vnpay_helpers.php
/**
 * Helper functions cho VNPay - File này bị thiếu trong create_payment.php
 */

/**
 * Tạo bản ghi VNPay transaction trong database
 */
function createVNPayTransaction($pdo, $order_id, $amount, $vnp_txn_ref, $order_info, $customer_email = '') {
    try {
        $stmt = $pdo->prepare("
            INSERT INTO thanh_toan_vnpay 
            (don_hang_id, vnp_txn_ref, vnp_amount, vnp_order_info, trang_thai, 
             ip_address, ngay_tao, ngay_het_han)
            VALUES (?, ?, ?, ?, 'khoi_tao', ?, NOW(), DATE_ADD(NOW(), INTERVAL 15 MINUTE))
        ");
        
        $vnp_amount_formatted = formatVNPayAmount($amount);
        $ip_address = $_SERVER['REMOTE_ADDR'] ?? '127.0.0.1';
        
        $stmt->execute([
            $order_id, 
            $vnp_txn_ref, 
            $vnp_amount_formatted, 
            $order_info, 
            $ip_address
        ]);
        
        return $pdo->lastInsertId();
        
    } catch (Exception $e) {
        error_log('Create VNPay transaction error: ' . $e->getMessage());
        throw new Exception('Không thể tạo giao dịch VNPay');
    }
}

/**
 * Cập nhật trạng thái đơn hàng thành cho_thanh_toan
 */
function updateOrderForVNPay($pdo, $order_id) {
    try {
        $stmt = $pdo->prepare("
            UPDATE don_hang SET 
                trang_thai_thanh_toan = 'cho_thanh_toan',
                ngay_cap_nhat = NOW()
            WHERE id = ? AND trang_thai_thanh_toan = 'chua_thanh_toan'
        ");
        
        return $stmt->execute([$order_id]);
        
    } catch (Exception $e) {
        error_log('Update order for VNPay error: ' . $e->getMessage());
        return false;
    }
}

/**
 * Kiểm tra đơn hàng có thể thanh toán VNPay không
 */
function validateOrderForVNPay($pdo, $order_id, $expected_amount) {
    try {
        $stmt = $pdo->prepare("
            SELECT id, tong_thanh_toan, trang_thai_thanh_toan, khach_hang_id, ma_don_hang
            FROM don_hang 
            WHERE id = ?
        ");
        $stmt->execute([$order_id]);
        $order = $stmt->fetch();
        
        if (!$order) {
            throw new Exception('Đơn hàng không tồn tại');
        }
        
        if ($order['trang_thai_thanh_toan'] === 'da_thanh_toan') {
            throw new Exception('Đơn hàng đã được thanh toán');
        }
        
        if (abs($order['tong_thanh_toan'] - $expected_amount) > 1) {
            throw new Exception('Số tiền thanh toán không khớp với đơn hàng');
        }
        
        return $order;
        
    } catch (Exception $e) {
        throw $e;
    }
}

/**
 * Gửi email xác nhận đơn hàng (optional)
 */
function sendOrderConfirmationEmail($order_id) {
    // TODO: Implement email sending
    // Có thể dùng PHPMailer hoặc service email khác
    return true;
}

/**
 * Tạo QR code cho thanh toán (optional)
 */
function generateQRCode($vnp_url) {
    // TODO: Implement QR code generation
    return null;
}

/**
 * Validate VNPay session data
 */
function validateVNPaySession($session_data) {
    $required_fields = ['order_id', 'amount', 'order_info', 'customer_name'];
    
    foreach ($required_fields as $field) {
        if (empty($session_data[$field])) {
            return false;
        }
    }
    
    if ($session_data['amount'] <= 0) {
        return false;
    }
    
    if ($session_data['order_id'] <= 0) {
        return false;
    }
    
    return true;
}

/**
 * Clean expired VNPay transactions
 */
function cleanExpiredVNPayTransactions($pdo) {
    try {
        $stmt = $pdo->prepare("
            UPDATE thanh_toan_vnpay SET 
                trang_thai = 'het_han'
            WHERE trang_thai IN ('khoi_tao', 'cho_thanh_toan') 
            AND ngay_het_han < NOW()
        ");
        
        return $stmt->execute();
        
    } catch (Exception $e) {
        error_log('Clean expired VNPay transactions error: ' . $e->getMessage());
        return false;
    }
}
?>