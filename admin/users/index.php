<?php
// admin/users/index.php - ĐÃ SỬA ĐƯỜNG DẪN
/**
 * Quản lý người dùng
 */

require_once '../../config/database.php';
require_once '../../config/config.php';

requireLogin();

// Xử lý xóa người dùng
if (isset($_GET['delete'])) {
    $id = (int)$_GET['delete'];
    
    // Không cho phép xóa chính mình
    if ($id == $_SESSION['admin_id']) {
        alert('Không thể xóa tài khoản của chính mình!', 'danger');
    } else {
        // Kiểm tra người dùng có đơn hàng không
        $check = $pdo->prepare("SELECT COUNT(*) FROM don_hang WHERE khach_hang_id = ?");
        $check->execute([$id]);
        
        if ($check->fetchColumn() > 0) {
            alert('Không thể xóa người dùng đã có đơn hàng! Hãy đặt trạng thái "Bị khóa" thay vì xóa.', 'danger');
        } else {
            $stmt = $pdo->prepare("DELETE FROM nguoi_dung WHERE id = ?");
            if ($stmt->execute([$id])) {
                alert('Xóa người dùng thành công!', 'success');
            } else {
                alert('Lỗi khi xóa người dùng!', 'danger');
            }
        }
    }
    redirect('admin/users/'); // ĐÃ SỬA
}

// Lấy danh sách người dùng với tìm kiếm và lọc
$search = $_GET['search'] ?? '';
$role_filter = $_GET['role'] ?? '';
$status_filter = $_GET['status'] ?? '';

$sql = "SELECT nd.*, 
               COUNT(DISTINCT dh.id) as so_don_hang,
               SUM(CASE WHEN dh.trang_thai_don_hang = 'da_giao' THEN dh.tong_thanh_toan ELSE 0 END) as tong_chi_tieu
        FROM nguoi_dung nd
        LEFT JOIN don_hang dh ON nd.id = dh.khach_hang_id
        WHERE 1=1";

$params = [];

if (!empty($search)) {
    $sql .= " AND (nd.ten_dang_nhap LIKE ? OR nd.ho_ten LIKE ? OR nd.email LIKE ?)";
    $params[] = "%$search%";
    $params[] = "%$search%";
    $params[] = "%$search%";
}

if (!empty($role_filter)) {
    $sql .= " AND nd.vai_tro = ?";
    $params[] = $role_filter;
}

if (!empty($status_filter)) {
    $sql .= " AND nd.trang_thai = ?";
    $params[] = $status_filter;
}

$sql .= " GROUP BY nd.id ORDER BY nd.ngay_tao DESC";

$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$users = $stmt->fetchAll();
?>

<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Quản lý người dùng - <?= SITE_NAME ?></title>
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
                    <h2>Quản lý người dùng</h2>
                    <a href="<?= adminUrl('users/create.php') ?>" class="btn btn-primary">
                        <i class="fas fa-plus"></i> Thêm người dùng
                    </a>
                </div>

                <?php showAlert(); ?>

                <!-- Bộ lọc -->
                <div class="card mb-3">
                    <div class="card-body">
                        <form method="GET" action="<?= adminUrl('users/') ?>" class="row g-3">
                            <div class="col-md-4">
                                <input type="text" 
                                       class="form-control" 
                                       name="search" 
                                       placeholder="Tìm theo tên, email, username..."
                                       value="<?= htmlspecialchars($search) ?>">
                            </div>
                            <div class="col-md-2">
                                <select name="role" class="form-select">
                                    <option value="">Tất cả vai trò</option>
                                    <option value="admin" <?= $role_filter == 'admin' ? 'selected' : '' ?>>Admin</option>
                                    <option value="nhan_vien" <?= $role_filter == 'nhan_vien' ? 'selected' : '' ?>>Nhân viên</option>
                                    <option value="khach_hang" <?= $role_filter == 'khach_hang' ? 'selected' : '' ?>>Khách hàng</option>
                                </select>
                            </div>
                            <div class="col-md-2">
                                <select name="status" class="form-select">
                                    <option value="">Tất cả trạng thái</option>
                                    <option value="hoat_dong" <?= $status_filter == 'hoat_dong' ? 'selected' : '' ?>>Hoạt động</option>
                                    <option value="chua_kich_hoat" <?= $status_filter == 'chua_kich_hoat' ? 'selected' : '' ?>>Chưa kích hoạt</option>
                                    <option value="bi_khoa" <?= $status_filter == 'bi_khoa' ? 'selected' : '' ?>>Bị khóa</option>
                                </select>
                            </div>
                            <div class="col-md-2">
                                <button type="submit" class="btn btn-outline-primary">
                                    <i class="fas fa-search"></i> Tìm kiếm
                                </button>
                            </div>
                            <div class="col-md-2">
                                <a href="<?= adminUrl('users/') ?>" class="btn btn-outline-secondary">
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
                                        <th>ID</th>
                                        <th>Thông tin</th>
                                        <th>Vai trò</th>
                                        <th>Trạng thái</th>
                                        <th>Đơn hàng</th>
                                        <th>Tổng chi tiêu</th>
                                        <th>Ngày tạo</th>
                                        <th>Hành động</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php if (empty($users)): ?>
                                        <tr>
                                            <td colspan="8" class="text-center">Không tìm thấy người dùng nào</td>
                                        </tr>
                                    <?php else: ?>
                                        <?php foreach ($users as $user): ?>
                                            <tr>
                                                <td><?= $user['id'] ?></td>
                                                <td>
                                                    <div>
                                                        <strong><?= htmlspecialchars($user['ho_ten']) ?></strong>
                                                        <?php if ($user['id'] == $_SESSION['admin_id']): ?>
                                                            <span class="badge bg-info">Bạn</span>
                                                        <?php endif; ?>
                                                    </div>
                                                    <div class="text-muted small">
                                                        <i class="fas fa-user"></i> <?= htmlspecialchars($user['ten_dang_nhap']) ?>
                                                    </div>
                                                    <div class="text-muted small">
                                                        <i class="fas fa-envelope"></i> <?= htmlspecialchars($user['email']) ?>
                                                    </div>
                                                    <?php if ($user['so_dien_thoai']): ?>
                                                        <div class="text-muted small">
                                                            <i class="fas fa-phone"></i> <?= htmlspecialchars($user['so_dien_thoai']) ?>
                                                        </div>
                                                    <?php endif; ?>
                                                </td>
                                                <td>
                                                    <?php 
                                                    $role_classes = [
                                                        'admin' => 'danger',
                                                        'nhan_vien' => 'warning',
                                                        'khach_hang' => 'primary'
                                                    ];
                                                    $role_text = [
                                                        'admin' => 'Admin',
                                                        'nhan_vien' => 'Nhân viên',
                                                        'khach_hang' => 'Khách hàng'
                                                    ];
                                                    ?>
                                                    <span class="badge bg-<?= $role_classes[$user['vai_tro']] ?>">
                                                        <?= $role_text[$user['vai_tro']] ?>
                                                    </span>
                                                </td>
                                                <td>
                                                    <?php 
                                                    $status_classes = [
                                                        'hoat_dong' => 'success',
                                                        'chua_kich_hoat' => 'warning',
                                                        'bi_khoa' => 'danger'
                                                    ];
                                                    $status_text = [
                                                        'hoat_dong' => 'Hoạt động',
                                                        'chua_kich_hoat' => 'Chưa kích hoạt',
                                                        'bi_khoa' => 'Bị khóa'
                                                    ];
                                                    ?>
                                                    <span class="badge bg-<?= $status_classes[$user['trang_thai']] ?>">
                                                        <?= $status_text[$user['trang_thai']] ?>
                                                    </span>
                                                </td>
                                                <td>
                                                    <?php if ($user['vai_tro'] == 'khach_hang'): ?>
                                                        <span class="badge bg-info"><?= $user['so_don_hang'] ?></span>
                                                    <?php else: ?>
                                                        <span class="text-muted">-</span>
                                                    <?php endif; ?>
                                                </td>
                                                <td>
                                                    <?php if ($user['vai_tro'] == 'khach_hang' && $user['tong_chi_tieu'] > 0): ?>
                                                        <span class="text-success fw-bold"><?= formatPrice($user['tong_chi_tieu']) ?></span>
                                                    <?php else: ?>
                                                        <span class="text-muted">-</span>
                                                    <?php endif; ?>
                                                </td>
                                                <td>
                                                    <small><?= date('d/m/Y', strtotime($user['ngay_tao'])) ?></small>
                                                </td>
                                                <td>
                                                    <div class="btn-group btn-group-sm">
                                                        <a href="<?= adminUrl('users/edit.php?id=' . $user['id']) ?>" 
                                                           class="btn btn-warning" title="Sửa">
                                                            <i class="fas fa-edit"></i>
                                                        </a>
                                                        <?php if ($user['id'] != $_SESSION['admin_id']): ?>
                                                            <a href="<?= adminUrl('users/?delete=' . $user['id']) ?>" 
                                                               class="btn btn-danger"
                                                               title="Xóa"
                                                               onclick="return confirm('Bạn có chắc muốn xóa người dùng này?\n\nLưu ý: Không thể xóa nếu đã có đơn hàng.')">
                                                                <i class="fas fa-trash"></i>
                                                            </a>
                                                        <?php endif; ?>
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