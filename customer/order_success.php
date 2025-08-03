<?php
// customer/order_success.php
/**
 * Trang thành công đặt hàng - COD
 */

require_once '../config/database.php';
require_once '../config/config.php';

session_start();

$order_code = $_GET['order'] ?? '';
$customer_id = $_SESSION['customer_id'] ?? null;

if (empty($order_code)) {
    redirect('/customer/index.php');
}

// Lấy thông tin đơn hàng
$stmt = $pdo->prepare("
    SELECT dh.*, nd.ho_ten, nd.email, nd.so_dien_thoai
    FROM don_hang dh
    LEFT JOIN nguoi_dung nd ON dh.khach_hang_id = nd.id
    WHERE dh.ma_don_hang = ?
");
$stmt->execute([$order_code]);
$order = $stmt->fetch();

if (!$order) {
    redirect('/customer/index.php');
}

// Kiểm tra quyền xem đơn hàng
if ($customer_id && $order['khach_hang_id'] != $customer_id) {
    redirect('/customer/index.php');
}

$page_title = 'Đặt hàng thành công - ' . SITE_NAME;
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
        .success-container {
            max-width: 800px;
            margin: 50px auto;
            padding: 20px;
        }
        
        .success-card {
            background: white;
            border-radius: 15px;
            box-shadow: 0 5px 15px rgba(0,0,0,0.1);
            padding: 40px;
            text-align: center;
        }
        
        .success-icon {
            width: 80px;
            height: 80px;
            background: #28a745;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            margin: 0 auto 30px;
            color: white;
            font-size: 2rem;
        }
        
        .order-details {
            background: #f8f9fa;
            border-radius: 10px;
            padding: 30px;
            margin: 30px 0;
        }
        
        .detail-row {
            display: flex;
            justify-content: space-between;
            margin-bottom: 15px;
            padding-bottom: 10px;
            border-bottom: 1px solid #dee2e6;
        }
        
        .detail-row:last-child {
            border-bottom: none;
            margin-bottom: 0;
        }
        
        .detail-label {
            font-weight: bold;
            color: #495057;
        }
        
        .detail-value {
            color: #6c757d;
        }
        
        .action-buttons {
            margin-top: 30px;
        }
        
        .btn-action {
            margin: 0 10px;
            padding: 12px 30px;
            border-radius: 25px;
            text-decoration: none;
            font-weight: bold;
            transition: all 0.3s ease;
        }
        
        .btn-primary {
            background: #007bff;
            color: white;
        }
        
        .btn-primary:hover {
            background: #0056b3;
            color: white;
        }
        
        .btn-outline-primary {
            border: 2px solid #007bff;
            color: #007bff;
        }
        
        .btn-outline-primary:hover {
            background: #007bff;
            color: white;
        }
    </style>
</head>
<body style="background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); min-height: 100vh;">
    
    <div class="success-container">
        <div class="success-card">
            <div class="success-icon">
                <i class="fas fa-check"></i>
            </div>
            
            <h1 class="text-success mb-3">Đặt hàng thành công!</h1>
            <p class="lead text-muted mb-4">
                Cảm ơn bạn đã đặt hàng. Chúng tôi sẽ xử lý đơn hàng của bạn trong thời gian sớm nhất.
            </p>
            
            <div class="order-details">
                <h4 class="mb-4">Thông tin đơn hàng</h4>
                
                <div class="detail-row">
                    <span class="detail-label">Mã đơn hàng:</span>
                    <span class="detail-value"><?= htmlspecialchars($order['ma_don_hang']) ?></span>
                </div>
                
                <div class="detail-row">
                    <span class="detail-label">Ngày đặt hàng:</span>
                    <span class="detail-value"><?= date('d/m/Y H:i', strtotime($order['ngay_dat_hang'])) ?></span>
                </div>
                
                <div class="detail-row">
                    <span class="detail-label">Tổng tiền:</span>
                    <span class="detail-value fw-bold text-primary"><?= formatPrice($order['tong_thanh_toan']) ?></span>
                </div>
                
                <div class="detail-row">
                    <span class="detail-label">Phương thức thanh toán:</span>
                    <span class="detail-value">
                        <?= $order['phuong_thuc_thanh_toan'] == 'cod' ? 'Thanh toán khi nhận hàng (COD)' : 'VNPay' ?>
                    </span>
                </div>
                
                <div class="detail-row">
                    <span class="detail-label">Trạng thái:</span>
                    <span class="detail-value">
                        <span class="badge bg-warning">Chờ xác nhận</span>
                    </span>
                </div>
                
                <div class="detail-row">
                    <span class="detail-label">Người nhận:</span>
                    <span class="detail-value"><?= htmlspecialchars($order['ho_ten_nhan']) ?></span>
                </div>
                
                <div class="detail-row">
                    <span class="detail-label">Số điện thoại:</span>
                    <span class="detail-value"><?= htmlspecialchars($order['so_dien_thoai_nhan']) ?></span>
                </div>
                
                <div class="detail-row">
                    <span class="detail-label">Địa chỉ giao hàng:</span>
                    <span class="detail-value"><?= htmlspecialchars($order['dia_chi_nhan']) ?></span>
                </div>
            </div>
            
            <div class="alert alert-info">
                <i class="fas fa-info-circle me-2"></i>
                <strong>Lưu ý:</strong> Chúng tôi sẽ liên hệ với bạn trong vòng 24 giờ để xác nhận đơn hàng. 
                Vui lòng giữ điện thoại luôn bật để nhận cuộc gọi từ chúng tôi.
            </div>
            
            <div class="action-buttons">
                <a href="/customer/orders.php" class="btn-action btn-primary">
                    <i class="fas fa-list me-2"></i>Xem đơn hàng của tôi
                </a>
                
                <a href="/customer/index.php" class="btn-action btn-outline-primary">
                    <i class="fas fa-home me-2"></i>Tiếp tục mua sắm
                </a>
            </div>
        </div>
    </div>
    
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html> 