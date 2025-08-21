<?php
// admin/orders/detail.php
/**
 * Chi tiết đơn hàng - Hiển thị thông tin chi tiết đơn hàng, sản phẩm, khách hàng, địa chỉ
 * Chức năng: Xem thông tin đầy đủ, cập nhật trạng thái, in hóa đơn
 */

require_once '../../config/database.php';
require_once '../../config/config.php';

requireLogin();

$order_id = (int)($_GET['id'] ?? 0);

// Lấy thông tin đơn hàng
$stmt = $pdo->prepare("
    SELECT dh.*, nd.ho_ten as ten_khach_hang, nd.email as email_khach_hang, nd.so_dien_thoai,
           vnp.vnp_transaction_no, vnp.trang_thai as trang_thai_vnpay, vnp.vnp_pay_date
    FROM don_hang dh 
    LEFT JOIN nguoi_dung nd ON dh.khach_hang_id = nd.id
    LEFT JOIN thanh_toan_vnpay vnp ON dh.id = vnp.don_hang_id
    WHERE dh.id = ?
");
$stmt->execute([$order_id]);
$order = $stmt->fetch();

if (!$order) {
    alert('Đơn hàng không tồn tại!', 'danger');
    redirect('/tktshop/admin/orders/');
}

// Lấy chi tiết sản phẩm trong đơn hàng
$stmt = $pdo->prepare("
    SELECT ct.*, sp.hinh_anh_chinh, sp.slug as san_pham_slug
    FROM chi_tiet_don_hang ct
    JOIN san_pham_chinh sp ON ct.san_pham_id = sp.id
    WHERE ct.don_hang_id = ?
    ORDER BY ct.id
");
$stmt->execute([$order_id]);
$order_items = $stmt->fetchAll();

// Lấy lịch sử thay đổi trạng thái (nếu có bảng log)
// Tạm thời dùng thông tin từ đơn hàng hiện tại
$status_history = [];
if ($order['ngay_dat_hang']) {
    $status_history[] = [
        'trang_thai' => 'cho_xac_nhan',
        'ngay_thay_doi' => $order['ngay_dat_hang'],
        'ghi_chu' => 'Đơn hàng được tạo'
    ];
}
if ($order['ngay_xac_nhan']) {
    $status_history[] = [
        'trang_thai' => 'da_xac_nhan',
        'ngay_thay_doi' => $order['ngay_xac_nhan'],
        'ghi_chu' => 'Đơn hàng đã được xác nhận'
    ];
}
if ($order['ngay_giao_hang']) {
    $status_history[] = [
        'trang_thai' => 'dang_giao',
        'ngay_thay_doi' => $order['ngay_giao_hang'],
        'ghi_chu' => 'Đơn hàng đang được giao'
    ];
}
if ($order['ngay_hoan_thanh']) {
    $status_history[] = [
        'trang_thai' => 'da_giao',
        'ngay_thay_doi' => $order['ngay_hoan_thanh'],
        'ghi_chu' => 'Đơn hàng đã giao thành công'
    ];
}
if ($order['ngay_huy']) {
    $status_history[] = [
        'trang_thai' => 'da_huy',
        'ngay_thay_doi' => $order['ngay_huy'],
        'ghi_chu' => 'Đơn hàng đã bị hủy: ' . $order['ly_do_huy']
    ];
}
?>

<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Chi tiết đơn hàng #<?= $order['ma_don_hang'] ?> - <?= SITE_NAME ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <style>
        .order-status-timeline {
            position: relative;
            padding-left: 30px;
        }
        .order-status-timeline::before {
            content: '';
            position: absolute;
            left: 10px;
            top: 0;
            bottom: 0;
            width: 2px;
            background: #dee2e6;
        }
        .status-item {
            position: relative;
            margin-bottom: 20px;
        }
        .status-item::before {
            content: '';
            position: absolute;
            left: -35px;
            top: 5px;
            width: 12px;
            height: 12px;
            border-radius: 50%;
            background: #6c757d;
        }
        .status-item.active::before {
            background: #198754;
        }
        .status-item.cancelled::before {
            background: #dc3545;
        }
        
        @media print {
            .no-print { display: none !important; }
            .container-fluid { padding: 0; }
            .card { border: none; box-shadow: none; }
        }
    </style>
</head>
<body>
    <!-- ✅ Include Header -->
    <div class="no-print">
        <?php include '../layouts/header.php'; ?>
    </div>
    
    <!-- ✅ Include Sidebar -->
    <div class="no-print">
        <?php include '../layouts/sidebar.php'; ?>
    </div>
    
    <!-- ✅ Main Content -->
    <div class="main-content">
        <div class="content-wrapper">
            <div class="d-flex justify-content-between align-items-center py-3 no-print">
                <div>
                    <h2>Chi tiết đơn hàng #<?= $order['ma_don_hang'] ?></h2>
                    <p class="text-muted mb-0">
                        Ngày đặt: <?= date('d/m/Y H:i:s', strtotime($order['ngay_dat_hang'])) ?>
                    </p>
                </div>
                <div>
                    <button onclick="window.print()" class="btn btn-outline-primary me-2">
                        <i class="fas fa-print"></i> In hóa đơn
                    </button>
                    <a href="update_status.php?id=<?= $order['id'] ?>" class="btn btn-warning me-2">
                        <i class="fas fa-edit"></i> Cập nhật trạng thái
                    </a>
                    <a href="/tktshop/admin/orders/" class="btn btn-secondary">
                        <i class="fas fa-arrow-left"></i> Quay lại
                    </a>
                </div>
            </div>

            <?php showAlert(); ?>

            <div class="row">
                <!-- Thông tin đơn hàng -->
                <div class="col-md-8">
                    <div class="card mb-3">
                        <div class="card-header">
                            <h5>Thông tin đơn hàng</h5>
                        </div>
                        <div class="card-body">
                            <div class="row">
                                <div class="col-md-6">
                                    <p><strong>Mã đơn hàng:</strong> <?= $order['ma_don_hang'] ?></p>
                                    <p><strong>Trạng thái đơn hàng:</strong> 
                                        <?php 
                                        $order_status_class = [
                                            'cho_xac_nhan' => 'warning',
                                            'da_xac_nhan' => 'info',
                                            'dang_chuan_bi' => 'primary',
                                            'dang_giao' => 'primary',
                                            'da_giao' => 'success',
                                            'da_huy' => 'danger'
                                        ];
                                        $order_status_text = [
                                            'cho_xac_nhan' => 'Chờ xác nhận',
                                            'da_xac_nhan' => 'Đã xác nhận',
                                            'dang_chuan_bi' => 'Đang chuẩn bị',
                                            'dang_giao' => 'Đang giao',
                                            'da_giao' => 'Đã giao',
                                            'da_huy' => 'Đã hủy'
                                        ];
                                        ?>
                                        <span class="badge bg-<?= $order_status_class[$order['trang_thai_don_hang']] ?>">
                                            <?= $order_status_text[$order['trang_thai_don_hang']] ?>
                                        </span>
                                    </p>
                                    <p><strong>Phương thức thanh toán:</strong> 
                                        <?= $order['phuong_thuc_thanh_toan'] == 'vnpay' ? 'VNPay' : 'COD' ?>
                                    </p>
                                    <p><strong>Trạng thái thanh toán:</strong>
                                        <?php 
                                        $payment_status_class = [
                                            'chua_thanh_toan' => 'warning',
                                            'da_thanh_toan' => 'success',
                                            'cho_thanh_toan' => 'info',
                                            'that_bai' => 'danger',
                                            'het_han' => 'secondary'
                                        ];
                                        $payment_status_text = [
                                            'chua_thanh_toan' => 'Chưa thanh toán',
                                            'da_thanh_toan' => 'Đã thanh toán',
                                            'cho_thanh_toan' => 'Chờ thanh toán',
                                            'that_bai' => 'Thất bại',
                                            'het_han' => 'Hết hạn'
                                        ];
                                        ?>
                                        <span class="badge bg-<?= $payment_status_class[$order['trang_thai_thanh_toan']] ?>">
                                            <?= $payment_status_text[$order['trang_thai_thanh_toan']] ?>
                                        </span>
                                    </p>
                                </div>
                                <div class="col-md-6">
                                    <?php if ($order['vnp_transaction_no']): ?>
                                        <p><strong>Mã giao dịch VNPay:</strong> <?= $order['vnp_transaction_no'] ?></p>
                                    <?php endif; ?>
                                    <?php if ($order['vnp_pay_date']): ?>
                                        <p><strong>Thời gian thanh toán:</strong> <?= date('d/m/Y H:i:s', strtotime($order['vnp_pay_date'])) ?></p>
                                    <?php endif; ?>
                                    <?php if ($order['ma_van_don']): ?>
                                        <p><strong>Mã vận đơn:</strong> <?= $order['ma_van_don'] ?></p>
                                    <?php endif; ?>
                                    <?php if ($order['han_thanh_toan'] && $order['phuong_thuc_thanh_toan'] == 'vnpay'): ?>
                                        <p><strong>Hạn thanh toán:</strong> <?= date('d/m/Y H:i:s', strtotime($order['han_thanh_toan'])) ?></p>
                                    <?php endif; ?>
                                </div>
                            </div>
                            
                            <?php if ($order['ghi_chu_khach_hang']): ?>
                                <div class="mt-3">
                                    <strong>Ghi chú của khách hàng:</strong>
                                    <p class="text-muted"><?= htmlspecialchars($order['ghi_chu_khach_hang']) ?></p>
                                </div>
                            <?php endif; ?>
                            
                            <?php if ($order['ghi_chu_admin']): ?>
                                <div class="mt-3">
                                    <strong>Ghi chú nội bộ:</strong>
                                    <p class="text-info"><?= htmlspecialchars($order['ghi_chu_admin']) ?></p>
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>

                    <!-- Thông tin khách hàng và giao hàng -->
                    <div class="card mb-3">
                        <div class="card-header">
                            <h5>Thông tin khách hàng & Giao hàng</h5>
                        </div>
                        <div class="card-body">
                            <div class="row">
                                <div class="col-md-6">
                                    <h6>Thông tin khách hàng</h6>
                                    <?php if ($order['ten_khach_hang']): ?>
                                        <p><strong>Tên khách hàng:</strong> <?= htmlspecialchars($order['ten_khach_hang']) ?></p>
                                        <p><strong>Email:</strong> <?= htmlspecialchars($order['email_khach_hang']) ?></p>
                                        <p><strong>SĐT:</strong> <?= htmlspecialchars($order['so_dien_thoai']) ?></p>
                                    <?php else: ?>
                                        <p class="text-muted">Khách hàng vãng lai</p>
                                    <?php endif; ?>
                                </div>
                                <div class="col-md-6">
                                    <h6>Địa chỉ giao hàng</h6>
                                    <p><strong>Người nhận:</strong> <?= htmlspecialchars($order['ho_ten_nhan']) ?></p>
                                    <p><strong>SĐT:</strong> <?= htmlspecialchars($order['so_dien_thoai_nhan']) ?></p>
                                    <?php if ($order['email_nhan']): ?>
                                        <p><strong>Email:</strong> <?= htmlspecialchars($order['email_nhan']) ?></p>
                                    <?php endif; ?>
                                    <p><strong>Địa chỉ:</strong> <?= htmlspecialchars($order['dia_chi_nhan']) ?></p>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Danh sách sản phẩm -->
                    <div class="card mb-3">
                        <div class="card-header">
                            <h5>Danh sách sản phẩm (<?= count($order_items) ?> sản phẩm)</h5>
                        </div>
                        <div class="card-body">
                            <div class="table-responsive">
                                <table class="table">
                                    <thead>
                                        <tr>
                                            <th>Sản phẩm</th>
                                            <th>SKU</th>
                                            <th>Đơn giá</th>
                                            <th>Số lượng</th>
                                            <th>Thành tiền</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($order_items as $item): ?>
                                            <tr>
                                                <td>
                                                    <div class="d-flex align-items-center">
                                                        <?php if ($item['hinh_anh']): ?>
                                                            <img src="/tktshop/uploads/products/<?= $item['hinh_anh'] ?>" 
                                                                 alt="<?= htmlspecialchars($item['ten_san_pham']) ?>"
                                                                 style="width: 50px; height: 50px; object-fit: cover;"
                                                                 class="rounded me-3">
                                                        <?php endif; ?>
                                                        <div>
                                                            <strong><?= htmlspecialchars($item['ten_san_pham']) ?></strong>
                                                            <?php if ($item['thuong_hieu']): ?>
                                                                <br><small class="text-muted"><?= htmlspecialchars($item['thuong_hieu']) ?></small>
                                                            <?php endif; ?>
                                                            <br><small class="text-muted">Size: <?= $item['kich_co'] ?> | Màu: <?= $item['mau_sac'] ?></small>
                                                        </div>
                                                    </div>
                                                </td>
                                                <td><code><?= $item['ma_sku'] ?></code></td>
                                                <td><?= formatPrice($item['gia_ban']) ?></td>
                                                <td><?= $item['so_luong'] ?></td>
                                                <td><strong><?= formatPrice($item['thanh_tien']) ?></strong></td>
                                            </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                    <tfoot>
                                        <tr>
                                            <th colspan="4">Tổng tiền hàng:</th>
                                            <th><?= formatPrice($order['tong_tien_hang']) ?></th>
                                        </tr>
                                        <?php if ($order['tien_giam_gia'] > 0): ?>
                                            <tr>
                                                <th colspan="4">Giảm giá:</th>
                                                <th class="text-danger">-<?= formatPrice($order['tien_giam_gia']) ?></th>
                                            </tr>
                                        <?php endif; ?>
                                        <tr>
                                            <th colspan="4">Phí vận chuyển:</th>
                                            <th><?= formatPrice($order['phi_van_chuyen']) ?></th>
                                        </tr>
                                        <tr class="table-success">
                                            <th colspan="4">Tổng thanh toán:</th>
                                            <th><?= formatPrice($order['tong_thanh_toan']) ?></th>
                                        </tr>
                                    </tfoot>
                                </table>
                            </div>
                        </div>
                    </div>
                </div>
                
                <!-- Sidebar phải -->
                <div class="col-md-4">
                    <!-- Lịch sử trạng thái -->
                    <div class="card mb-3">
                        <div class="card-header">
                            <h5>Lịch sử trạng thái</h5>
                        </div>
                        <div class="card-body">
                            <div class="order-status-timeline">
                                <?php foreach ($status_history as $history): ?>
                                    <div class="status-item <?= $history['trang_thai'] == 'da_huy' ? 'cancelled' : 'active' ?>">
                                        <div>
                                            <strong>
                                                <?php 
                                                echo $order_status_text[$history['trang_thai']] ?? $history['trang_thai'];
                                                ?>
                                            </strong>
                                        </div>
                                        <small class="text-muted">
                                            <?= date('d/m/Y H:i:s', strtotime($history['ngay_thay_doi'])) ?>
                                        </small>
                                        <?php if ($history['ghi_chu']): ?>
                                            <div><small class="text-muted"><?= htmlspecialchars($history['ghi_chu']) ?></small></div>
                                        <?php endif; ?>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        </div>
                    </div>

                    <!-- Hành động nhanh -->
                    <div class="card no-print">
                        <div class="card-header">
                            <h5>Hành động nhanh</h5>
                        </div>
                        <div class="card-body">
                            <?php if ($order['trang_thai_don_hang'] == 'cho_xac_nhan'): ?>
                                <a href="update_status.php?id=<?= $order['id'] ?>&action=confirm" 
                                   class="btn btn-success btn-sm w-100 mb-2"
                                   onclick="return confirm('Xác nhận đơn hàng này?')">
                                    <i class="fas fa-check"></i> Xác nhận đơn hàng
                                </a>
                                <a href="update_status.php?id=<?= $order['id'] ?>&action=cancel" 
                                   class="btn btn-danger btn-sm w-100 mb-2"
                                   onclick="return confirm('Hủy đơn hàng này?')">
                                    <i class="fas fa-times"></i> Hủy đơn hàng
                                </a>
                            <?php endif; ?>
                            
                            <?php if ($order['trang_thai_don_hang'] == 'da_xac_nhan'): ?>
                                <a href="update_status.php?id=<?= $order['id'] ?>&action=prepare" 
                                   class="btn btn-primary btn-sm w-100 mb-2">
                                    <i class="fas fa-box"></i> Chuẩn bị đơn hàng
                                </a>
                            <?php endif; ?>
                            
                            <?php if ($order['trang_thai_don_hang'] == 'dang_chuan_bi'): ?>
                                <a href="update_status.php?id=<?= $order['id'] ?>&action=ship" 
                                   class="btn btn-info btn-sm w-100 mb-2">
                                    <i class="fas fa-shipping-fast"></i> Bắt đầu giao hàng
                                </a>
                            <?php endif; ?>
                            
                            <?php if ($order['trang_thai_don_hang'] == 'dang_giao'): ?>
                                <a href="update_status.php?id=<?= $order['id'] ?>&action=complete" 
                                   class="btn btn-success btn-sm w-100 mb-2"
                                   onclick="return confirm('Xác nhận đã giao hàng thành công?')">
                                    <i class="fas fa-check-circle"></i> Hoàn thành giao hàng
                                </a>
                            <?php endif; ?>
                            
                            <hr>
                            
                            <button onclick="window.print()" class="btn btn-outline-primary btn-sm w-100 mb-2">
                                <i class="fas fa-print"></i> In hóa đơn
                            </button>
                            
                            <?php if ($order['phuong_thuc_thanh_toan'] == 'vnpay' && $order['trang_thai_thanh_toan'] == 'cho_thanh_toan'): ?>
                                <a href="/tktshop/vnpay/check_status.php?order_id=<?= $order['id'] ?>" 
                                   class="btn btn-outline-info btn-sm w-100 mb-2">
                                    <i class="fas fa-credit-card"></i> Kiểm tra thanh toán VNPay
                                </a>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
