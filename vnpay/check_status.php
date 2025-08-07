<?php
// vnpay/check_status.php
/**
 * Kiểm tra trạng thái giao dịch VNPay
 */

require_once '../config/database.php';
require_once '../config/config.php';
require_once 'config.php';

// API hoặc AJAX request
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    header('Content-Type: application/json');
    
    $action = $_POST['action'];
    
    switch ($action) {
        case 'check_status':
            $txn_ref = $_POST['txn_ref'] ?? '';
            $transaction_date = $_POST['transaction_date'] ?? '';
            
            if (empty($txn_ref) || empty($transaction_date)) {
                echo json_encode([
                    'success' => false,
                    'message' => 'Thiếu thông tin giao dịch'
                ]);
                exit;
            }
            
            try {
                $result = queryVNPayTransaction($txn_ref, $transaction_date);
                echo json_encode([
                    'success' => true,
                    'data' => $result
                ]);
            } catch (Exception $e) {
                echo json_encode([
                    'success' => false,
                    'message' => $e->getMessage()
                ]);
            }
            break;
            
        case 'get_order_status':
            $order_id = (int)($_POST['order_id'] ?? 0);
            
            if ($order_id <= 0) {
                echo json_encode([
                    'success' => false,
                    'message' => 'Mã đơn hàng không hợp lệ'
                ]);
                exit;
            }
            
            try {
                $stmt = $pdo->prepare("
                    SELECT dh.*, vt.trang_thai as vnpay_status, vt.vnp_transaction_no,
                           vt.vnp_response_code
                    FROM don_hang dh
                    LEFT JOIN thanh_toan_vnpay vt ON dh.id = vt.don_hang_id
                    WHERE dh.id = ?
                ");
                $stmt->execute([$order_id]);
                $order = $stmt->fetch();
                
                if (!$order) {
                    throw new Exception('Không tìm thấy đơn hàng');
                }
                
                echo json_encode([
                    'success' => true,
                    'data' => [
                        'order_id' => $order['id'],
                        'payment_status' => $order['trang_thai_thanh_toan'],
                        'vnpay_status' => $order['vnpay_status'],
                        'vnpay_transaction_no' => $order['vnp_transaction_no'],
                        'response_code' => $order['vnp_response_code']
                    ]
                ]);
                
            } catch (Exception $e) {
                echo json_encode([
                    'success' => false,
                    'message' => $e->getMessage()
                ]);
            }
            break;
    }
    exit;
}

/**
 * Hàm query giao dịch từ VNPay API
 */
function queryVNPayTransaction($txnRef, $transactionDate) {
    global $vnp_TmnCode, $vnp_HashSecret, $vnp_apiUrl, $pdo;
    
    $vnp_RequestId = rand(1, 10000);
    $vnp_Command = "querydr";
    $vnp_OrderInfo = "Query transaction";
    $vnp_CreateDate = date('YmdHis');
    $vnp_IpAddr = $_SERVER['REMOTE_ADDR'];
    
    $dataRq = [
        "vnp_RequestId" => $vnp_RequestId,
        "vnp_Version" => "2.1.0",
        "vnp_Command" => $vnp_Command,
        "vnp_TmnCode" => $vnp_TmnCode,
        "vnp_TxnRef" => $txnRef,
        "vnp_OrderInfo" => $vnp_OrderInfo,
        "vnp_TransactionDate" => $transactionDate,
        "vnp_CreateDate" => $vnp_CreateDate,
        "vnp_IpAddr" => $vnp_IpAddr
    ];
    
    // Tạo secure hash
    $format = '%s|%s|%s|%s|%s|%s|%s|%s|%s';
    $dataHash = sprintf(
        $format,
        $dataRq['vnp_RequestId'],
        $dataRq['vnp_Version'],
        $dataRq['vnp_Command'],
        $dataRq['vnp_TmnCode'],
        $dataRq['vnp_TxnRef'],
        $dataRq['vnp_TransactionDate'],
        $dataRq['vnp_CreateDate'],
        $dataRq['vnp_IpAddr'],
        $dataRq['vnp_OrderInfo']
    );
    
    $checksum = hash_hmac('SHA512', $dataHash, $vnp_HashSecret);
    $dataRq["vnp_SecureHash"] = $checksum;
    
    // Gọi API
    $response = callVNPayAPI("POST", $vnp_apiUrl, json_encode($dataRq));
    $result = json_decode($response, true);
    
    // Log query
    logVNPayTransaction('query', [
        'txn_ref' => $txnRef,
        'transaction_date' => $transactionDate,
        'request_id' => $vnp_RequestId
    ], $result);
    
    // Cập nhật database nếu có thông tin mới
    if (isset($result['vnp_ResponseCode']) && $result['vnp_ResponseCode'] === '00') {
        updateTransactionFromQuery($result);
    }
    
    return $result;
}

/**
 * Cập nhật giao dịch từ kết quả query
 */
function updateTransactionFromQuery($queryResult) {
    global $pdo;
    
    try {
        $vnp_TxnRef = $queryResult['vnp_TxnRef'] ?? '';
        
        if (empty($vnp_TxnRef)) {
            return;
        }
        
        $stmt = $pdo->prepare("
            UPDATE thanh_toan_vnpay SET
                vnp_response_code = ?,
                vnp_transaction_no = ?,
                vnp_transaction_status = ?,
                vnp_amount = ?,
                du_lieu_response = ?,
                ngay_cap_nhat = NOW()
            WHERE vnp_txn_ref = ?
        ");
        
        $stmt->execute([
            $queryResult['vnp_ResponseCode'] ?? '',
            $queryResult['vnp_TransactionNo'] ?? '',
            $queryResult['vnp_TransactionStatus'] ?? '',
            parseVNPayAmount($queryResult['vnp_Amount'] ?? 0),
            json_encode($queryResult),
            $vnp_TxnRef
        ]);
        
        // Nếu giao dịch thành công nhưng chưa cập nhật đơn hàng
        if (($queryResult['vnp_ResponseCode'] ?? '') === '00' && 
            ($queryResult['vnp_TransactionStatus'] ?? '') === '00') {
            
            $stmt = $pdo->prepare("
                SELECT vt.don_hang_id, dh.trang_thai_thanh_toan
                FROM thanh_toan_vnpay vt
                JOIN don_hang dh ON vt.don_hang_id = dh.id
                WHERE vt.vnp_txn_ref = ? AND dh.trang_thai_thanh_toan != 'da_thanh_toan'
            ");
            $stmt->execute([$vnp_TxnRef]);
            $order = $stmt->fetch();
            
            if ($order) {
                // Cập nhật trạng thái đơn hàng
                $pdo->prepare("
                    UPDATE don_hang SET 
                        trang_thai_thanh_toan = 'da_thanh_toan',
                        phuong_thuc_thanh_toan = 'vnpay',
                        ngay_thanh_toan = NOW()
                    WHERE id = ?
                ")->execute([$order['don_hang_id']]);
                
                // Cập nhật trạng thái transaction
                $pdo->prepare("UPDATE thanh_toan_vnpay SET trang_thai = 'thanh_cong' WHERE vnp_txn_ref = ?")
                    ->execute([$vnp_TxnRef]);
            }
        }
        
    } catch (Exception $e) {
        error_log('Update transaction from query error: ' . $e->getMessage());
    }
}

$page_title = 'Kiểm tra trạng thái giao dịch - VNPay';
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
        .check-container {
            max-width: 600px;
            margin: 50px auto;
            padding: 20px;
        }
        
        .check-card {
            background: white;
            border-radius: 15px;
            box-shadow: 0 5px 15px rgba(0,0,0,0.1);
            padding: 30px;
        }
        
        .result-area {
            margin-top: 30px;
            padding: 20px;
            border-radius: 10px;
            display: none;
        }
        
        .result-success {
            background-color: #d4edda;
            border: 1px solid #c3e6cb;
            color: #155724;
        }
        
        .result-error {
            background-color: #f8d7da;
            border: 1px solid #f5c6cb;
            color: #721c24;
        }
        
        .loading {
            display: none;
            text-align: center;
            margin: 20px 0;
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="check-container">
            <div class="check-card">
                <div class="text-center mb-4">
                    <i class="fas fa-search fa-3x text-primary mb-3"></i>
                    <h2>Kiểm tra trạng thái giao dịch</h2>
                    <p class="text-muted">Nhập thông tin giao dịch để kiểm tra trạng thái</p>
                </div>
                
                <form id="checkForm">
                    <div class="mb-3">
                        <label for="txnRef" class="form-label">Mã giao dịch (vnp_TxnRef):</label>
                        <input type="text" class="form-control" id="txnRef" name="txn_ref" 
                               placeholder="Ví dụ: ORD000123" required>
                        <div class="form-text">Mã giao dịch bạn nhận được khi tạo thanh toán</div>
                    </div>
                    
                    <div class="mb-3">
                        <label for="transactionDate" class="form-label">Thời gian giao dịch:</label>
                        <input type="text" class="form-control" id="transactionDate" name="transaction_date" 
                               placeholder="yyyyMMddHHmmss (Ví dụ: 20240315143000)" required>
                        <div class="form-text">Định dạng: yyyyMMddHHmmss</div>
                    </div>
                    
                    <div class="d-grid gap-2">
                        <button type="submit" class="btn btn-primary btn-lg">
                            <i class="fas fa-search me-2"></i>
                            Kiểm tra trạng thái
                        </button>
                        
                        <button type="button" class="btn btn-outline-secondary" onclick="fillSampleData()">
                            <i class="fas fa-edit me-2"></i>
                            Điền dữ liệu mẫu
                        </button>
                    </div>
                </form>
                
                <div class="loading">
                    <i class="fas fa-spinner fa-spin fa-2x text-primary"></i>
                    <p class="mt-2">Đang kiểm tra...</p>
                </div>
                
                <div id="resultArea" class="result-area">
                    <div id="resultContent"></div>
                </div>
            </div>
            
            <!-- Quick Order Status Check -->
            <div class="check-card mt-4">
                <h5 class="mb-3">
                    <i class="fas fa-receipt me-2"></i>
                    Kiểm tra nhanh theo mã đơn hàng
                </h5>
                
                <form id="orderCheckForm">
                    <div class="input-group">
                        <input type="number" class="form-control" id="orderId" name="order_id" 
                               placeholder="Mã đơn hàng (Ví dụ: 123)" min="1">
                        <button type="submit" class="btn btn-outline-primary">
                            <i class="fas fa-search"></i>
                        </button>
                    </div>
                </form>
                
                <div id="orderResultArea" class="result-area">
                    <div id="orderResultContent"></div>
                </div>
            </div>
        </div>
    </div>
    
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
    
    <script>
        // Check transaction status
        document.getElementById('checkForm').addEventListener('submit', function(e) {
            e.preventDefault();
            
            const formData = new FormData(this);
            formData.append('action', 'check_status');
            
            showLoading(true);
            hideResult();
            
            fetch('check_status.php', {
                method: 'POST',
                body: formData
            })
            .then(response => response.json())
            .then(data => {
                showLoading(false);
                
                if (data.success) {
                    showResult(formatVNPayResult(data.data), 'success');
                } else {
                    showResult('<i class="fas fa-exclamation-triangle me-2"></i>' + data.message, 'error');
                }
            })
            .catch(error => {
                showLoading(false);
                showResult('<i class="fas fa-times-circle me-2"></i>Có lỗi xảy ra: ' + error.message, 'error');
            });
        });
        
        // Check order status
        document.getElementById('orderCheckForm').addEventListener('submit', function(e) {
            e.preventDefault();
            
            const formData = new FormData(this);
            formData.append('action', 'get_order_status');
            
            const orderId = document.getElementById('orderId').value;
            if (!orderId) {
                showOrderResult('<i class="fas fa-exclamation-triangle me-2"></i>Vui lòng nhập mã đơn hàng', 'error');
                return;
            }
            
            fetch('check_status.php', {
                method: 'POST',
                body: formData
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    showOrderResult(formatOrderResult(data.data), 'success');
                } else {
                    showOrderResult('<i class="fas fa-exclamation-triangle me-2"></i>' + data.message, 'error');
                }
            })
            .catch(error => {
                showOrderResult('<i class="fas fa-times-circle me-2"></i>Có lỗi xảy ra: ' + error.message, 'error');
            });
        });
        
        function showLoading(show) {
            document.querySelector('.loading').style.display = show ? 'block' : 'none';
        }
        
        function hideResult() {
            document.getElementById('resultArea').style.display = 'none';
        }
        
        function showResult(content, type) {
            const resultArea = document.getElementById('resultArea');
            const resultContent = document.getElementById('resultContent');
            
            resultArea.className = 'result-area result-' + type;
            resultContent.innerHTML = content;
            resultArea.style.display = 'block';
        }
        
        function showOrderResult(content, type) {
            const resultArea = document.getElementById('orderResultArea');
            const resultContent = document.getElementById('orderResultContent');
            
            resultArea.className = 'result-area result-' + type;
            resultContent.innerHTML = content;
            resultArea.style.display = 'block';
        }
        
        function formatVNPayResult(data) {
            let html = '<h5><i class="fas fa-info-circle me-2"></i>Kết quả truy vấn VNPay</h5>';
            
            html += '<table class="table table-borderless">';
            html += '<tr><td><strong>Mã giao dịch:</strong></td><td>' + (data.vnp_TxnRef || 'N/A') + '</td></tr>';
            html += '<tr><td><strong>Mã phản hồi:</strong></td><td>' + (data.vnp_ResponseCode || 'N/A') + '</td></tr>';
            html += '<tr><td><strong>Mã GD VNPay:</strong></td><td>' + (data.vnp_TransactionNo || 'N/A') + '</td></tr>';
            html += '<tr><td><strong>Số tiền:</strong></td><td>' + (data.vnp_Amount ? formatMoney(data.vnp_Amount / 100) : 'N/A') + '</td></tr>';
            html += '<tr><td><strong>Trạng thái:</strong></td><td>';
            
            if (data.vnp_ResponseCode === '00') {
                html += '<span class="badge bg-success">Thành công</span>';
            } else {
                html += '<span class="badge bg-danger">Thất bại</span>';
            }
            
            html += '</td></tr>';
            html += '<tr><td><strong>Thông báo:</strong></td><td>' + (data.vnp_Message || 'N/A') + '</td></tr>';
            html += '</table>';
            
            return html;
        }
        
        function formatOrderResult(data) {
            let html = '<h5><i class="fas fa-receipt me-2"></i>Trạng thái đơn hàng #' + data.order_id + '</h5>';
            
            html += '<table class="table table-borderless">';
            html += '<tr><td><strong>Trạng thái thanh toán:</strong></td><td>';
            
            if (data.payment_status === 'da_thanh_toan') {
                html += '<span class="badge bg-success">Đã thanh toán</span>';
            } else {
                html += '<span class="badge bg-warning">Chưa thanh toán</span>';
            }
            
            html += '</td></tr>';
            
            if (data.vnpay_status) {
                html += '<tr><td><strong>Trạng thái VNPay:</strong></td><td>';
                
                if (data.vnpay_status === 'success') {
                    html += '<span class="badge bg-success">Thành công</span>';
                } else if (data.vnpay_status === 'failed') {
                    html += '<span class="badge bg-danger">Thất bại</span>';
                } else {
                    html += '<span class="badge bg-warning">Đang xử lý</span>';
                }
                
                html += '</td></tr>';
                
                if (data.vnpay_transaction_no) {
                    html += '<tr><td><strong>Mã GD VNPay:</strong></td><td>' + data.vnpay_transaction_no + '</td></tr>';
                }
                
                if (data.response_code) {
                    html += '<tr><td><strong>Mã phản hồi:</strong></td><td>' + data.response_code + '</td></tr>';
                }
                
                if (data.error_message) {
                    html += '<tr><td><strong>Lỗi:</strong></td><td class="text-danger">' + data.error_message + '</td></tr>';
                }
            }
            
            html += '</table>';
            
            return html;
        }
        
        function formatMoney(amount) {
            return new Intl.NumberFormat('vi-VN', {
                style: 'currency',
                currency: 'VND'
            }).format(amount);
        }
        
        function fillSampleData() {
            const now = new Date();
            const sampleDate = now.getFullYear() + 
                              String(now.getMonth() + 1).padStart(2, '0') + 
                              String(now.getDate()).padStart(2, '0') + 
                              String(now.getHours()).padStart(2, '0') + 
                              String(now.getMinutes()).padStart(2, '0') + 
                              String(now.getSeconds()).padStart(2, '0');
            
            document.getElementById('txnRef').value = 'ORD000123';
            document.getElementById('transactionDate').value = sampleDate;
        }
    </script>
</body>
</html>