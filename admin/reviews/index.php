<?php
// admin/reviews/index.php
/**
 * Quản lý đánh giá sản phẩm - Xem danh sách tất cả đánh giá của khách hàng
 * Chức năng: Hiển thị, lọc, duyệt/từ chối đánh giá
 */

require_once '../../config/database.php';
require_once '../../config/config.php';

requireLogin();

// Xử lý duyệt đánh giá
if (isset($_GET['approve'])) {
    $review_id = (int)$_GET['approve'];
    
    $stmt = $pdo->prepare("UPDATE danh_gia_san_pham SET trang_thai = 'da_duyet', nguoi_duyet = ?, ngay_duyet = NOW() WHERE id = ?");
    if ($stmt->execute([$_SESSION['admin_id'], $review_id])) {
        alert('Duyệt đánh giá thành công!', 'success');
    } else {
        alert('Lỗi khi duyệt đánh giá!', 'danger');
    }
    redirect('/tktshop/admin/reviews/');
}

// Xử lý từ chối đánh giá
if (isset($_GET['reject'])) {
    $review_id = (int)$_GET['reject'];
    $ly_do = $_GET['reason'] ?? 'Vi phạm quy định';
    
    $stmt = $pdo->prepare("UPDATE danh_gia_san_pham SET trang_thai = 'tu_choi', nguoi_duyet = ?, ngay_duyet = NOW(), ly_do_tu_choi = ? WHERE id = ?");
    if ($stmt->execute([$_SESSION['admin_id'], $ly_do, $review_id])) {
        alert('Từ chối đánh giá thành công!', 'success');
    } else {
        alert('Lỗi khi từ chối đánh giá!', 'danger');
    }
    redirect('/tktshop/admin/reviews/');
}

// Xử lý xóa đánh giá spam
if (isset($_GET['delete'])) {
    $review_id = (int)$_GET['delete'];
    
    $stmt = $pdo->prepare("DELETE FROM danh_gia_san_pham WHERE id = ?");
    if ($stmt->execute([$review_id])) {
        alert('Xóa đánh giá spam thành công!', 'success');
    } else {
        alert('Lỗi khi xóa đánh giá!', 'danger');
    }
    redirect('/tktshop/admin/reviews/');
}

// Lấy danh sách đánh giá với bộ lọc
$status_filter = $_GET['status'] ?? '';
$rating_filter = $_GET['rating'] ?? '';
$search = $_GET['search'] ?? '';

$sql = "SELECT d.*, sp.ten_san_pham, sp.hinh_anh_chinh, nd.ho_ten as ten_khach_hang, nd.email as email_khach_hang
        FROM danh_gia_san_pham d
        JOIN san_pham_chinh sp ON d.san_pham_id = sp.id
        JOIN nguoi_dung nd ON d.khach_hang_id = nd.id
        WHERE 1=1";

$params = [];

if (!empty($status_filter)) {
    $sql .= " AND d.trang_thai = ?";
    $params[] = $status_filter;
}

if (!empty($rating_filter)) {
    $sql .= " AND d.diem_danh_gia = ?";
    $params[] = $rating_filter;
}

if (!empty($search)) {
    $sql .= " AND (sp.ten_san_pham LIKE ? OR nd.ho_ten LIKE ? OR d.noi_dung LIKE ?)";
    $params[] = "%$search%";
    $params[] = "%$search%";
    $params[] = "%$search%";
}

$sql .= " ORDER BY d.ngay_tao DESC";

$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$reviews = $stmt->fetchAll();

// Thống kê đánh giá
$stats = $pdo->query("
    SELECT 
        COUNT(*) as total,
        SUM(CASE WHEN trang_thai = 'cho_duyet' THEN 1 ELSE 0 END) as pending,
        SUM(CASE WHEN trang_thai = 'da_duyet' THEN 1 ELSE 0 END) as approved,
        SUM(CASE WHEN trang_thai = 'tu_choi' THEN 1 ELSE 0 END) as rejected,
        AVG(diem_danh_gia) as avg_rating
    FROM danh_gia_san_pham
")->fetch();
?>

<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Quản lý đánh giá - <?= SITE_NAME ?></title>
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
                    <h2>Quản lý đánh giá sản phẩm</h2>
                </div>

                <?php showAlert(); ?>

                <!-- Thống kê nhanh -->
                <div class="row mb-4">
                    <div class="col-md-3">
                        <div class="card text-center border-info">
                            <div class="card-body">
                                <h3 class="text-info"><?= $stats['total'] ?></h3>
                                <small>Tổng đánh giá</small>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="card text-center border-warning">
                            <div class="card-body">
                                <h3 class="text-warning"><?= $stats['pending'] ?></h3>
                                <small>Chờ duyệt</small>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="card text-center border-success">
                            <div class="card-body">
                                <h3 class="text-success"><?= $stats['approved'] ?></h3>
                                <small>Đã duyệt</small>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="card text-center border-primary">
                            <div class="card-body">
                                <h3 class="text-primary"><?= number_format($stats['avg_rating'], 1) ?></h3>
                                <small>Điểm TB</small>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Bộ lọc -->
                <div class="card mb-3">
                    <div class="card-body">
                        <form method="GET" class="row g-3">
                            <div class="col-md-3">
                                <input type="text" 
                                       class="form-control" 
                                       name="search" 
                                       placeholder="Tìm theo sản phẩm, khách hàng..."
                                       value="<?= htmlspecialchars($search) ?>">
                            </div>
                            <div class="col-md-2">
                                <select name="status" class="form-select">
                                    <option value="">Tất cả trạng thái</option>
                                    <option value="cho_duyet" <?= $status_filter == 'cho_duyet' ? 'selected' : '' ?>>
                                        Chờ duyệt
                                    </option>
                                    <option value="da_duyet" <?= $status_filter == 'da_duyet' ? 'selected' : '' ?>>
                                        Đã duyệt
                                    </option>
                                    <option value="tu_choi" <?= $status_filter == 'tu_choi' ? 'selected' : '' ?>>
                                        Từ chối
                                    </option>
                                </select>
                            </div>
                            <div class="col-md-2">
                                <select name="rating" class="form-select">
                                    <option value="">Tất cả điểm</option>
                                    <option value="5" <?= $rating_filter == '5' ? 'selected' : '' ?>>5 sao</option>
                                    <option value="4" <?= $rating_filter == '4' ? 'selected' : '' ?>>4 sao</option>
                                    <option value="3" <?= $rating_filter == '3' ? 'selected' : '' ?>>3 sao</option>
                                    <option value="2" <?= $rating_filter == '2' ? 'selected' : '' ?>>2 sao</option>
                                    <option value="1" <?= $rating_filter == '1' ? 'selected' : '' ?>>1 sao</option>
                                </select>
                            </div>
                            <div class="col-md-2">
                                <button type="submit" class="btn btn-outline-primary">
                                    <i class="fas fa-search"></i> Lọc
                                </button>
                            </div>
                            <div class="col-md-2">
                                <a href="/tktshop/admin/reviews/" class="btn btn-outline-secondary">
                                    <i class="fas fa-times"></i> Xóa lọc
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
                                        <th>Sản phẩm</th>
                                        <th>Khách hàng</th>
                                        <th>Đánh giá</th>
                                        <th>Nội dung</th>
                                        <th>Trạng thái</th>
                                        <th>Ngày tạo</th>
                                        <th>Hành động</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php if (empty($reviews)): ?>
                                        <tr>
                                            <td colspan="8" class="text-center">Chưa có đánh giá nào</td>
                                        </tr>
                                    <?php else: ?>
                                        <?php foreach ($reviews as $review): ?>
                                            <tr>
                                                <td><?= $review['id'] ?></td>
                                                <td>
                                                    <div class="d-flex align-items-center">
                                                        <?php if ($review['hinh_anh_chinh']): ?>
                                                            <img src="/tktshop/uploads/products/<?= $review['hinh_anh_chinh'] ?>" 
                                                                 alt="<?= htmlspecialchars($review['ten_san_pham']) ?>"
                                                                 style="width: 40px; height: 40px; object-fit: cover;"
                                                                 class="rounded me-2">
                                                        <?php endif; ?>
                                                        <div>
                                                            <small><strong><?= htmlspecialchars($review['ten_san_pham']) ?></strong></small>
                                                        </div>
                                                    </div>
                                                </td>
                                                <td>
                                                    <div>
                                                        <strong><?= htmlspecialchars($review['ten_khach_hang']) ?></strong>
                                                    </div>
                                                    <small class="text-muted"><?= htmlspecialchars($review['email_khach_hang']) ?></small>
                                                    <?php if ($review['la_mua_hang_xac_thuc']): ?>
                                                        <br><span class="badge bg-success">Đã mua</span>
                                                    <?php endif; ?>
                                                </td>
                                                <td>
                                                    <div class="mb-1">
                                                        <?php for ($i = 1; $i <= 5; $i++): ?>
                                                            <i class="fas fa-star <?= $i <= $review['diem_danh_gia'] ? 'text-warning' : 'text-muted' ?>"></i>
                                                        <?php endfor; ?>
                                                        <span class="ms-1"><?= $review['diem_danh_gia'] ?>/5</span>
                                                    </div>
                                                    <?php if ($review['tieu_de']): ?>
                                                        <small class="text-muted"><?= htmlspecialchars($review['tieu_de']) ?></small>
                                                    <?php endif; ?>
                                                </td>
                                                <td>
                                                    <div class="review-content" style="max-width: 200px;">
                                                        <?php if (strlen($review['noi_dung']) > 100): ?>
                                                            <?= htmlspecialchars(substr($review['noi_dung'], 0, 100)) ?>...
                                                            <br><button class="btn btn-link btn-sm p-0" onclick="showFullReview(<?= $review['id'] ?>)">Xem thêm</button>
                                                        <?php else: ?>
                                                            <?= htmlspecialchars($review['noi_dung']) ?>
                                                        <?php endif; ?>
                                                    </div>
                                                    
                                                    <!-- Hiển thị ưu/nhược điểm nếu có -->
                                                    <?php if ($review['uu_diem']): ?>
                                                        <div class="mt-1">
                                                            <small class="text-success"><i class="fas fa-plus"></i> <?= htmlspecialchars($review['uu_diem']) ?></small>
                                                        </div>
                                                    <?php endif; ?>
                                                    <?php if ($review['nhuoc_diem']): ?>
                                                        <div class="mt-1">
                                                            <small class="text-danger"><i class="fas fa-minus"></i> <?= htmlspecialchars($review['nhuoc_diem']) ?></small>
                                                        </div>
                                                    <?php endif; ?>
                                                </td>
                                                <td>
                                                    <?php 
                                                    $status_classes = [
                                                        'cho_duyet' => 'warning',
                                                        'da_duyet' => 'success', 
                                                        'tu_choi' => 'danger',
                                                        'an' => 'secondary'
                                                    ];
                                                    $status_text = [
                                                        'cho_duyet' => 'Chờ duyệt',
                                                        'da_duyet' => 'Đã duyệt',
                                                        'tu_choi' => 'Từ chối',
                                                        'an' => 'Ẩn'
                                                    ];
                                                    ?>
                                                    <span class="badge bg-<?= $status_classes[$review['trang_thai']] ?>">
                                                        <?= $status_text[$review['trang_thai']] ?>
                                                    </span>
                                                    
                                                    <?php if ($review['trang_thai'] == 'tu_choi' && $review['ly_do_tu_choi']): ?>
                                                        <br><small class="text-muted">Lý do: <?= htmlspecialchars($review['ly_do_tu_choi']) ?></small>
                                                    <?php endif; ?>
                                                </td>
                                                <td>
                                                    <small><?= date('d/m/Y H:i', strtotime($review['ngay_tao'])) ?></small>
                                                </td>
                                                <td>
                                                    <div class="btn-group btn-group-sm">
                                                        <?php if ($review['trang_thai'] == 'cho_duyet'): ?>
                                                            <a href="?approve=<?= $review['id'] ?>" 
                                                               class="btn btn-success" 
                                                               title="Duyệt"
                                                               onclick="return confirm('Bạn có chắc muốn duyệt đánh giá này?')">
                                                                <i class="fas fa-check"></i>
                                                            </a>
                                                            <button type="button" 
                                                                    class="btn btn-danger" 
                                                                    title="Từ chối"
                                                                    onclick="rejectReview(<?= $review['id'] ?>)">
                                                                <i class="fas fa-times"></i>
                                                            </button>
                                                        <?php endif; ?>
                                                        
                                                        <button type="button" 
                                                                class="btn btn-info" 
                                                                title="Xem chi tiết"
                                                                onclick="viewDetail(<?= $review['id'] ?>)">
                                                            <i class="fas fa-eye"></i>
                                                        </button>
                                                        
                                                        <a href="?delete=<?= $review['id'] ?>" 
                                                           class="btn btn-outline-danger" 
                                                           title="Xóa spam"
                                                           onclick="return confirm('Bạn có chắc muốn xóa đánh giá spam này?')">
                                                            <i class="fas fa-trash"></i>
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

    <!-- Modal từ chối đánh giá -->
    <div class="modal fade" id="rejectModal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Từ chối đánh giá</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <form id="rejectForm">
                        <div class="mb-3">
                            <label for="reject_reason" class="form-label">Lý do từ chối</label>
                            <select class="form-select" id="reject_reason">
                                <option value="Nội dung không phù hợp">Nội dung không phù hợp</option>
                                <option value="Ngôn từ thô tục">Ngôn từ thô tục</option>
                                <option value="Spam">Spam</option>
                                <option value="Đánh giá sai sản phẩm">Đánh giá sai sản phẩm</option>
                                <option value="Khác">Khác</option>
                            </select>
                        </div>
                        <div class="mb-3">
                            <label for="custom_reason" class="form-label">Lý do cụ thể (nếu chọn "Khác")</label>
                            <textarea class="form-control" id="custom_reason" rows="3"></textarea>
                        </div>
                    </form>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Hủy</button>
                    <button type="button" class="btn btn-danger" onclick="confirmReject()">Từ chối</button>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
    
    <script>
        let currentReviewId = null;
        
        function rejectReview(reviewId) {
            currentReviewId = reviewId;
            new bootstrap.Modal(document.getElementById('rejectModal')).show();
        }
        
        function confirmReject() {
            const reason = document.getElementById('reject_reason').value;
            const customReason = document.getElementById('custom_reason').value;
            const finalReason = reason === 'Khác' ? customReason : reason;
            
            if (finalReason.trim() === '') {
                alert('Vui lòng nhập lý do từ chối');
                return;
            }
            
            window.location.href = `?reject=${currentReviewId}&reason=${encodeURIComponent(finalReason)}`;
        }
        
        function viewDetail(reviewId) {
            // TODO: Implement view detail functionality
            alert('Chức năng xem chi tiết sẽ được phát triển trong phiên bản tiếp theo');
        }
        
        function showFullReview(reviewId) {
            // TODO: Implement show full review content
            alert('Chức năng xem nội dung đầy đủ sẽ được phát triển trong phiên bản tiếp theo');
        }
    </script>
</body>
</html>