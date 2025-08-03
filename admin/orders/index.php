<?php
// admin/orders/index.php
/**
 * Quản lý đơn hàng
 */

require_once '../../config/database.php';
require_once '../../config/config.php';

requireLogin();

// Lấy danh sách đơn hàng
$status_filter = $_GET['status'] ?? '';
$search = $_GET['search'] ?? '';

$sql = "SELECT dh.*, nd.ho_ten as ten_khach_hang, nd.email as email_khach_hang,
               COUNT(ct.id) as so_san_pham,
               SUM(ct.so_luong) as tong_so_luong
        FROM don_hang dh 
        LEFT JOIN nguoi_dung nd ON dh.khach_hang_id = nd.id
        LEFT JOIN chi_tiet_don_hang ct ON dh.id = ct.don_hang_id
        WHERE 1=1";

$params = [];

if (!empty($status_filter)) {
    $sql .= " AND dh.trang_thai_don_hang = ?";
    $params[] = $status_filter;
}

if (!empty($search)) {
    $sql .= " AND (dh.ma_don_hang LIKE ? OR dh.ho_ten_nhan LIKE ? OR dh.so_dien_thoai_nhan LIKE ?)";
    $params[] = "%$search%";
    $params[] = "%$search%"; 
    $params[] = "%$search%";
}

$sql .= " GROUP BY dh.id ORDER BY dh.ngay_dat_hang DESC";

$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$orders = $stmt->fetchAll();

// Thống kê đơn hàng
$stats = [
    'cho_xac_nhan' => 0,
    'da_xac_nhan' => 0,
    'dang_giao' => 0,
    'da_giao' => 0,
    'da_huy' => 0
];

$stat_query = $pdo->query("
    SELECT trang_thai_don_hang, COUNT(*) as count 
    FROM don_hang 
    GROUP BY trang_thai_don_hang
");

while ($row = $stat_query->fetch()) {
    if (isset($stats[$row['trang_thai_don_hang']])) {
        $stats[$row['trang_thai_don_hang']] = $row['count'];
    }
}
?>

<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Quản lý đơn hàng - <?= SITE_NAME ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
</head>
<body>
    <div class="container-fluid">
        <div class="row">
            <!-- Sidebar -->
            <?php include '../layouts/sidebar.php'; ?>
            
            <!-- Main content -->
            <div class="col-md-10">
                <div class="d-flex justify-content-between align-items-center py-3">
                    <h2>Quản lý đơn hàng</h2>
                </div>

                <?php showAlert(); ?>

                <!-- Thống kê nhanh -->
                <div class="row mb-4">
                    <div class="col-md-2">
                        <div class="card text-center border-warning">
                            <div class="card-body">
                                <h3 class="text-warning"><?= $stats['cho_xac_nhan'] ?></h3>
                                <small>Chờ xác nhận</small>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-2">
                        <div class="card text-center border-info">
                            <div class="card-body">
                                <h3 class="text-info"><?= $stats['da_xac_nhan'] ?></h3>
                                <small>Đã xác nhận</small>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-2">
                        <div class="card text-center border-primary">
                            <div class="card-body">
                                <h3 class="text-primary"><?= $stats['dang_giao'] ?></h3>
                                <small>Đang giao</small>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-2">
                        <div class="card text-center border-success">
                            <div class="card-body">
                                <h3 class="text-success"><?= $stats['da_giao'] ?></h3>
                                <small>Đã giao</small>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-2">
                        <div class="card text-center border-danger">
                            <div class="card-body">
                                <h3 class="text-danger"><?= $stats['da_huy'] ?></h3>
                                <small>Đã hủy</small>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Bộ lọc -->
                <div class="card mb-3">
                    <div class="card-body">
                        <form method="GET" class="row g-3">
                            <div class="col-md-4">
                                <input type="text" 
                                       class="form-control" 
                                       name="search" 
                                       placeholder="Tìm theo mã đơn hàng, tên, SĐT..."
                                       value="<?= htmlspecialchars($search) ?>">
                            </div>
                            <div class="col-md-3">
                                <select name="status" class="form-select">
                                    <option value="">Tất cả trạng thái</option>
                                    <option value="cho_xac_nhan" <?= $status_filter == 'cho_xac_nhan' ? 'selected' : '' ?>>
                                        Chờ xác nhận
                                    </option>
                                    <option value="da_xac_nhan" <?= $status_filter == 'da_xac_nhan' ? 'selected' : '' ?>>
                                        Đã xác nhận
                                    </option>
                                    <option value="dang_chuan_bi" <?= $status_filter == 'dang_chuan_bi' ? 'selected' : '' ?>>
                                        Đang chuẩn bị
                                    </option>
                                    <option value="dang_giao" <?= $status_filter == 'dang_giao' ? 'selected' : '' ?>>
                                        Đang giao
                                    </option>
                                    <option value="da_giao" <?= $status_filter == 'da_giao' ? 'selected' : '' ?>>
                                        Đã giao
                                    </option>
                                    <option value="da_huy" <?= $status_filter == 'da_huy' ? 'selected' : '' ?>>
                                        Đã hủy
                                    </option>
                                </select>
                            </div>
                            <div class="col-md-2">
                                <button type="submit" class="btn btn-outline-primary">
                                    <i class="fas fa-search"></i> Tìm kiếm
                                </button>
                            </div>
                            <div class="col-md-2">
                                <a href="/tktshop/admin/orders/" class="btn btn-outline-secondary">
                                    <i class="fas fa-times"></i> Xóa bộ lọc
                                </a>
                            </div>
                        </form>
                    </div>
                </div>

                <div class="card">
                    <div class="card-body">
                        <div class="table-responsive">
                            <table class="table table-striped">
                                <thead>
                                    <tr>
                                        <th>Mã đơn hàng</th>
                                        <th>Khách hàng</th>
                                        <th>Sản phẩm</th>
                                        <th>Tổng tiền</th>
                                        <th>Thanh toán</th>
                                        <th>Trạng thái</th>
                                        <th>Ngày đặt</th>
                                        <th>Hành động</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php if (empty($orders)): ?>
                                        <tr>
                                            <td colspan="8" class="text-center">Chưa có đơn hàng nào</td>
                                        </tr>
                                    <?php else: ?>
                                        <?php foreach ($orders as $order): ?>
                                            <tr>
                                                <td>
                                                    <strong><?= $order['ma_don_hang'] ?></strong>
                                                </td>
                                                <td>
                                                    <div>
                                                        <strong><?= htmlspecialchars($order['ho_ten_nhan']) ?></strong>
                                                    </div>
                                                    <small class="text-muted">
                                                        <?= htmlspecialchars($order['so_dien_thoai_nhan']) ?>
                                                    </small>
                                                    <?php if ($order['email_khach_hang']): ?>
                                                        <br><small class="text-muted">
                                                            <?= htmlspecialchars($order['email_khach_hang']) ?>
                                                        </small>
                                                    <?php endif; ?>
                                                </td>
                                                <td>
                                                    <span class="badge bg-primary"><?= $order['so_san_pham'] ?> sản phẩm</span>
                                                    <br><small class="text-muted"><?= $order['tong_so_luong'] ?> món</small>
                                                </td>
                                                <td>
                                                    <strong class="text-success">
                                                        <?= formatPrice($order['tong_thanh_toan']) ?>
                                                    </strong>
                                                </td>
                                                <td>
                                                    <?php 
                                                    $payment_status_class = [
                                                        'chua_thanh_toan' => 'warning',
                                                        'da_thanh_toan' => 'success',
                                                        'cho_thanh_toan' => 'info',
                                                        'that_bai' => 'danger'
                                                    ];
                                                    $payment_status_text = [
                                                        'chua_thanh_toan' => 'Chưa thanh toán',
                                                        'da_thanh_toan' => 'Đã thanh toán',
                                                        'cho_thanh_toan' => 'Chờ thanh toán',
                                                        'that_bai' => 'Thất bại'
                                                    ];
                                                    ?>
                                                    <span class="badge bg-<?= $payment_status_class[$order['trang_thai_thanh_toan']] ?>">
                                                        <?= $payment_status_text[$order['trang_thai_thanh_toan']] ?>
                                                    </span>
                                                    <br>
                                                    <small class="text-muted">
                                                        <?= $order['phuong_thuc_thanh_toan'] == 'vnpay' ? 'VNPay' : 'COD' ?>
                                                    </small>
                                                </td>
                                                <td>
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
                                                </td>
                                                <td>
                                                    <?= date('d/m/Y H:i', strtotime($order['ngay_dat_hang'])) ?>
                                                </td>
                                                <td>
                                                    <div class="btn-group btn-group-sm">
                                                        <a href="detail.php?id=<?= $order['id'] ?>" 
                                                           class="btn btn-info" title="Chi tiết">
                                                            <i class="fas fa-eye"></i>
                                                        </a>
                                                        <a href="update_status.php?id=<?= $order['id'] ?>" 
                                                           class="btn btn-warning" title="Cập nhật">
                                                            <i class="fas fa-edit"></i>
                                                        </a>
                                                    </div>
                                                </td>
                                            </tr>
                                        <?php endforeach; ?>
                                    <?php endif; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>