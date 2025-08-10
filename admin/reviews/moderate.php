<?php
/**
 * TKT Shop - Review Moderation Page
 * Trang kiểm duyệt đánh giá sản phẩm
 */

require_once '../../config/config.php';
require_once '../../config/database.php';

// Kiểm tra đăng nhập admin
if (!isset($_SESSION['admin_id'])) {
    header('Location: ../login.php');
    exit;
}

$page_title = "Review Moderation";
$success = '';
$error = '';

// Xử lý actions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    $review_id = $_POST['review_id'] ?? 0;
    
    switch ($action) {
        case 'approve':
            $stmt = $pdo->prepare("UPDATE product_reviews SET status = 'approved', moderated_by = ?, moderated_at = NOW() WHERE id = ?");
            if ($stmt->execute([$_SESSION['admin_id'], $review_id])) {
                $success = "Review approved successfully";
            } else {
                $error = "Failed to approve review";
            }
            break;
            
        case 'reject':
            $reason = $_POST['reason'] ?? '';
            $stmt = $pdo->prepare("UPDATE product_reviews SET status = 'rejected', moderation_reason = ?, moderated_by = ?, moderated_at = NOW() WHERE id = ?");
            if ($stmt->execute([$reason, $_SESSION['admin_id'], $review_id])) {
                $success = "Review rejected successfully";
            } else {
                $error = "Failed to reject review";
            }
            break;
            
        case 'bulk_approve':
            $review_ids = $_POST['review_ids'] ?? [];
            if (!empty($review_ids)) {
                $placeholders = str_repeat('?,', count($review_ids) - 1) . '?';
                $stmt = $pdo->prepare("UPDATE product_reviews SET status = 'approved', moderated_by = ?, moderated_at = NOW() WHERE id IN ($placeholders)");
                $params = array_merge([$_SESSION['admin_id']], $review_ids);
                if ($stmt->execute($params)) {
                    $success = count($review_ids) . " reviews approved successfully";
                } else {
                    $error = "Failed to approve reviews";
                }
            }
            break;
            
        case 'bulk_reject':
            $review_ids = $_POST['review_ids'] ?? [];
            $reason = $_POST['bulk_reason'] ?? '';
            if (!empty($review_ids)) {
                $placeholders = str_repeat('?,', count($review_ids) - 1) . '?';
                $stmt = $pdo->prepare("UPDATE product_reviews SET status = 'rejected', moderation_reason = ?, moderated_by = ?, moderated_at = NOW() WHERE id IN ($placeholders)");
                $params = array_merge([$reason, $_SESSION['admin_id']], $review_ids);
                if ($stmt->execute($params)) {
                    $success = count($review_ids) . " reviews rejected successfully";
                } else {
                    $error = "Failed to reject reviews";
                }
            }
            break;
    }
}

// Pagination
$page = $_GET['page'] ?? 1;
$limit = 20;
$offset = ($page - 1) * $limit;

// Filters
$status_filter = $_GET['status'] ?? 'pending';
$rating_filter = $_GET['rating'] ?? '';
$product_filter = $_GET['product'] ?? '';
$search = $_GET['search'] ?? '';

// Build query
$where_conditions = [];
$params = [];

if ($status_filter) {
    $where_conditions[] = "pr.status = ?";
    $params[] = $status_filter;
}

if ($rating_filter) {
    $where_conditions[] = "pr.rating = ?";
    $params[] = $rating_filter;
}

if ($product_filter) {
    $where_conditions[] = "pr.product_id = ?";
    $params[] = $product_filter;
}

if ($search) {
    $where_conditions[] = "(pr.title LIKE ? OR pr.comment LIKE ? OR c.name LIKE ? OR p.name LIKE ?)";
    $search_term = "%$search%";
    $params = array_merge($params, [$search_term, $search_term, $search_term, $search_term]);
}

$where_clause = empty($where_conditions) ? "" : "WHERE " . implode(" AND ", $where_conditions);

// Get reviews
$sql = "
    SELECT pr.*, 
           c.name as customer_name, c.email as customer_email,
           p.name as product_name, p.image as product_image,
           a.name as moderator_name
    FROM product_reviews pr
    LEFT JOIN customers c ON pr.customer_id = c.id
    LEFT JOIN products p ON pr.product_id = p.id
    LEFT JOIN admins a ON pr.moderated_by = a.id
    $where_clause
    ORDER BY pr.created_at DESC
    LIMIT $limit OFFSET $offset
";

$reviews = $pdo->prepare($sql);
$reviews->execute($params);
$reviews = $reviews->fetchAll(PDO::FETCH_ASSOC);

// Get total count for pagination
$count_sql = "
    SELECT COUNT(*) 
    FROM product_reviews pr
    LEFT JOIN customers c ON pr.customer_id = c.id
    LEFT JOIN products p ON pr.product_id = p.id
    $where_clause
";
$count_stmt = $pdo->prepare($count_sql);
$count_stmt->execute($params);
$total_reviews = $count_stmt->fetchColumn();
$total_pages = ceil($total_reviews / $limit);

// Get statistics
$stats = $pdo->query("
    SELECT 
        COUNT(CASE WHEN status = 'pending' THEN 1 END) as pending,
        COUNT(CASE WHEN status = 'approved' THEN 1 END) as approved,
        COUNT(CASE WHEN status = 'rejected' THEN 1 END) as rejected,
        COUNT(*) as total
    FROM product_reviews
")->fetch(PDO::FETCH_ASSOC);

// Get products for filter
$products = $pdo->query("SELECT id, name FROM products ORDER BY name")->fetchAll(PDO::FETCH_ASSOC);

include '../layouts/header.php';
?>

<div class="content-area">
    <div class="page-header">
        <h1 class="page-title">Review Moderation</h1>
        <div class="page-actions">
            <a href="index.php" class="btn btn-light">
                <i class="fas fa-list"></i> All Reviews
            </a>
        </div>
    </div>

    <!-- Statistics -->
    <div class="stats-grid mb-30">
        <div class="stat-card">
            <div class="stat-content">
                <h3><?php echo $stats['pending']; ?></h3>
                <p>Pending Reviews</p>
            </div>
            <div class="stat-icon">
                <i class="fas fa-clock text-warning"></i>
            </div>
        </div>
        <div class="stat-card">
            <div class="stat-content">
                <h3><?php echo $stats['approved']; ?></h3>
                <p>Approved Reviews</p>
            </div>
            <div class="stat-icon">
                <i class="fas fa-check-circle text-success"></i>
            </div>
        </div>
        <div class="stat-card">
            <div class="stat-content">
                <h3><?php echo $stats['rejected']; ?></h3>
                <p>Rejected Reviews</p>
            </div>
            <div class="stat-icon">
                <i class="fas fa-times-circle text-danger"></i>
            </div>
        </div>
        <div class="stat-card">
            <div class="stat-content">
                <h3><?php echo $stats['total']; ?></h3>
                <p>Total Reviews</p>
            </div>
            <div class="stat-icon">
                <i class="fas fa-star text-info"></i>
            </div>
        </div>
    </div>

    <?php if ($success): ?>
        <div class="alert alert-success"><?php echo $success; ?></div>
    <?php endif; ?>

    <?php if ($error): ?>
        <div class="alert alert-danger"><?php echo $error; ?></div>
    <?php endif; ?>

    <!-- Filters -->
    <div class="admin-card mb-30">
        <div class="admin-card-header">
            <h3 class="admin-card-title">Filters</h3>
        </div>
        <div class="admin-card-body">
            <form method="GET" class="filter-form">
                <div class="row">
                    <div class="col-3">
                        <label class="form-label">Status</label>
                        <select name="status" class="form-control">
                            <option value="">All Status</option>
                            <option value="pending" <?php echo $status_filter === 'pending' ? 'selected' : ''; ?>>Pending</option>
                            <option value="approved" <?php echo $status_filter === 'approved' ? 'selected' : ''; ?>>Approved</option>
                            <option value="rejected" <?php echo $status_filter === 'rejected' ? 'selected' : ''; ?>>Rejected</option>
                        </select>
                    </div>
                    <div class="col-3">
                        <label class="form-label">Rating</label>
                        <select name="rating" class="form-control">
                            <option value="">All Ratings</option>
                            <option value="5" <?php echo $rating_filter == '5' ? 'selected' : ''; ?>>5 Stars</option>
                            <option value="4" <?php echo $rating_filter == '4' ? 'selected' : ''; ?>>4 Stars</option>
                            <option value="3" <?php echo $rating_filter == '3' ? 'selected' : ''; ?>>3 Stars</option>
                            <option value="2" <?php echo $rating_filter == '2' ? 'selected' : ''; ?>>2 Stars</option>
                            <option value="1" <?php echo $rating_filter == '1' ? 'selected' : ''; ?>>1 Star</option>
                        </select>
                    </div>
                    <div class="col-3">
                        <label class="form-label">Product</label>
                        <select name="product" class="form-control">
                            <option value="">All Products</option>
                            <?php foreach ($products as $product): ?>
                                <option value="<?php echo $product['id']; ?>" <?php echo $product_filter == $product['id'] ? 'selected' : ''; ?>>
                                    <?php echo htmlspecialchars($product['name']); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="col-3">
                        <label class="form-label">Search</label>
                        <input type="text" name="search" class="form-control" 
                               value="<?php echo htmlspecialchars($search); ?>" 
                               placeholder="Search reviews...">
                    </div>
                </div>
                <div class="row mt-20">
                    <div class="col-12">
                        <button type="submit" class="btn btn-primary">Apply Filters</button>
                        <a href="moderate.php" class="btn btn-light">Reset</a>
                    </div>
                </div>
            </form>
        </div>
    </div>

    <!-- Reviews List -->
    <div class="admin-card">
        <div class="admin-card-header">
            <h3 class="admin-card-title">Reviews (<?php echo $total_reviews; ?>)</h3>
            <div class="bulk-actions" style="display: none;">
                <span class="selected-count">0</span> selected
                <button type="button" class="btn btn-sm btn-success bulk-approve">
                    <i class="fas fa-check"></i> Approve
                </button>
                <button type="button" class="btn btn-sm btn-danger bulk-reject">
                    <i class="fas fa-times"></i> Reject
                </button>
            </div>
        </div>
        <div class="admin-card-body">
            <?php if (empty($reviews)): ?>
                <div class="text-center py-4">
                    <i class="fas fa-star fa-3x text-muted mb-3"></i>
                    <h5>No reviews found</h5>
                    <p class="text-muted">Try adjusting your filters</p>
                </div>
            <?php else: ?>
                <form id="reviewsForm">
                    <div class="table-responsive">
                        <table class="table">
                            <thead>
                                <tr>
                                    <th>
                                        <input type="checkbox" id="selectAll">
                                    </th>
                                    <th>Review</th>
                                    <th>Customer</th>
                                    <th>Product</th>
                                    <th>Rating</th>
                                    <th>Status</th>
                                    <th>Date</th>
                                    <th>Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($reviews as $review): ?>
                                    <tr data-review-id="<?php echo $review['id']; ?>">
                                        <td>
                                            <input type="checkbox" class="review-checkbox" value="<?php echo $review['id']; ?>">
                                        </td>
                                        <td>
                                            <div class="review-content">
                                                <?php if ($review['title']): ?>
                                                    <h6 class="mb-1"><?php echo htmlspecialchars($review['title']); ?></h6>
                                                <?php endif; ?>
                                                <p class="mb-0 text-muted"><?php echo nl2br(htmlspecialchars(substr($review['comment'], 0, 150))); ?><?php echo strlen($review['comment']) > 150 ? '...' : ''; ?></p>
                                                <?php if ($review['status'] === 'rejected' && $review['moderation_reason']): ?>
                                                    <small class="text-danger">
                                                        <i class="fas fa-exclamation-triangle"></i>
                                                        Reason: <?php echo htmlspecialchars($review['moderation_reason']); ?>
                                                    </small>
                                                <?php endif; ?>
                                            </div>
                                        </td>
                                        <td>
                                            <div class="customer-info">
                                                <strong><?php echo htmlspecialchars($review['customer_name']); ?></strong><br>
                                                <small class="text-muted"><?php echo htmlspecialchars($review['customer_email']); ?></small>
                                            </div>
                                        </td>
                                        <td>
                                            <div class="product-info d-flex align-items-center">
                                                <?php if ($review['product_image']): ?>
                                                    <img src="../../uploads/products/<?php echo $review['product_image']; ?>" 
                                                         alt="Product" class="product-thumb me-2" style="width: 40px; height: 40px; object-fit: cover;">
                                                <?php endif; ?>
                                                <div>
                                                    <strong><?php echo htmlspecialchars($review['product_name']); ?></strong>
                                                </div>
                                            </div>
                                        </td>
                                        <td>
                                            <div class="rating">
                                                <?php for ($i = 1; $i <= 5; $i++): ?>
                                                    <i class="fas fa-star <?php echo $i <= $review['rating'] ? 'text-warning' : 'text-muted'; ?>"></i>
                                                <?php endfor; ?>
                                                <br>
                                                <small class="text-muted"><?php echo $review['rating']; ?>/5</small>
                                            </div>
                                        </td>
                                        <td>
                                            <?php
                                            $statusClass = '';
                                            $statusText = '';
                                            switch ($review['status']) {
                                                case 'pending':
                                                    $statusClass = 'badge-warning';
                                                    $statusText = 'Pending';
                                                    break;
                                                case 'approved':
                                                    $statusClass = 'badge-success';
                                                    $statusText = 'Approved';
                                                    break;
                                                case 'rejected':
                                                    $statusClass = 'badge-danger';
                                                    $statusText = 'Rejected';
                                                    break;
                                            }
                                            ?>
                                            <span class="badge <?php echo $statusClass; ?>"><?php echo $statusText; ?></span>
                                            <?php if ($review['moderator_name']): ?>
                                                <br><small class="text-muted">by <?php echo htmlspecialchars($review['moderator_name']); ?></small>
                                            <?php endif; ?>
                                        </td>
                                        <td>
                                            <span class="text-muted"><?php echo date('M j, Y', strtotime($review['created_at'])); ?></span><br>
                                            <small class="text-muted"><?php echo date('g:i A', strtotime($review['created_at'])); ?></small>
                                        </td>
                                        <td>
                                            <div class="btn-group btn-group-sm">
                                                <button type="button" class="btn btn-info view-review" data-review-id="<?php echo $review['id']; ?>">
                                                    <i class="fas fa-eye"></i>
                                                </button>
                                                <?php if ($review['status'] === 'pending'): ?>
                                                    <button type="button" class="btn btn-success approve-review" data-review-id="<?php echo $review['id']; ?>">
                                                        <i class="fas fa-check"></i>
                                                    </button>
                                                    <button type="button" class="btn btn-danger reject-review" data-review-id="<?php echo $review['id']; ?>">
                                                        <i class="fas fa-times"></i>
                                                    </button>
                                                <?php endif; ?>
                                                <button type="button" class="btn btn-secondary reply-review" data-review-id="<?php echo $review['id']; ?>">
                                                    <i class="fas fa-reply"></i>
                                                </button>
                                            </div>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </form>

                <!-- Pagination -->
                <?php if ($total_pages > 1): ?>
                    <div class="pagination-wrapper mt-30">
                        <nav aria-label="Reviews pagination">
                            <ul class="pagination justify-content-center">
                                <?php if ($page > 1): ?>
                                    <li class="page-item">
                                        <a class="page-link" href="?page=<?php echo $page - 1; ?>&<?php echo http_build_query(array_filter($_GET, function($key) { return $key !== 'page'; }, ARRAY_FILTER_USE_KEY)); ?>">
                                            Previous
                                        </a>
                                    </li>
                                <?php endif; ?>

                                <?php for ($i = max(1, $page - 2); $i <= min($total_pages, $page + 2); $i++): ?>
                                    <li class="page-item <?php echo $i == $page ? 'active' : ''; ?>">
                                        <a class="page-link" href="?page=<?php echo $i; ?>&<?php echo http_build_query(array_filter($_GET, function($key) { return $key !== 'page'; }, ARRAY_FILTER_USE_KEY)); ?>">
                                            <?php echo $i; ?>
                                        </a>
                                    </li>
                                <?php endfor; ?>

                                <?php if ($page < $total_pages): ?>
                                    <li class="page-item">
                                        <a class="page-link" href="?page=<?php echo $page + 1; ?>&<?php echo http_build_query(array_filter($_GET, function($key) { return $key !== 'page'; }, ARRAY_FILTER_USE_KEY)); ?>">
                                            Next
                                        </a>
                                    </li>
                                <?php endif; ?>
                            </ul>
                        </nav>
                    </div>
                <?php endif; ?>
            <?php endif; ?>
        </div>
    </div>
</div>

<!-- Review Detail Modal -->
<div class="modal-overlay" id="reviewModal">
    <div class="modal">
        <div class="modal-header">
            <h3 class="modal-title">Review Details</h3>
            <button type="button" class="modal-close">&times;</button>
        </div>
        <div class="modal-body" id="reviewModalContent">
            <!-- Content loaded via AJAX -->
        </div>
    </div>
</div>

<!-- Reject Reason Modal -->
<div class="modal-overlay" id="rejectModal">
    <div class="modal">
        <div class="modal-header">
            <h3 class="modal-title">Reject Review</h3>
            <button type="button" class="modal-close">&times;</button>
        </div>
        <div class="modal-body">
            <form id="rejectForm" method="POST">
                <input type="hidden" name="action" value="reject">
                <input type="hidden" name="review_id" id="rejectReviewId">
                
                <div class="form-group">
                    <label class="form-label">Reason for rejection:</label>
                    <select name="reason" class="form-control" required>
                        <option value="">Select a reason</option>
                        <option value="Inappropriate content">Inappropriate content</option>
                        <option value="Spam or fake review">Spam or fake review</option>
                        <option value="Off-topic content">Off-topic content</option>
                        <option value="Promotional content">Promotional content</option>
                        <option value="Personal information disclosed">Personal information disclosed</option>
                        <option value="Violates community guidelines">Violates community guidelines</option>
                        <option value="Other">Other</option>
                    </select>
                </div>
                
                <div class="form-group">
                    <label class="form-label">Additional notes (optional):</label>
                    <textarea name="additional_notes" class="form-control" rows="3" 
                              placeholder="Add any additional notes about the rejection..."></textarea>
                </div>
        </div>
        <div class="modal-footer">
            <button type="submit" class="btn btn-danger">Reject Review</button>
            <button type="button" class="btn btn-light modal-close">Cancel</button>
        </div>
            </form>
    </div>
</div>

<!-- Bulk Reject Modal -->
<div class="modal-overlay" id="bulkRejectModal">
    <div class="modal">
        <div class="modal-header">
            <h3 class="modal-title">Bulk Reject Reviews</h3>
            <button type="button" class="modal-close">&times;</button>
        </div>
        <div class="modal-body">
            <form id="bulkRejectForm" method="POST">
                <input type="hidden" name="action" value="bulk_reject">
                
                <p>You are about to reject <span id="bulkRejectCount">0</span> reviews.</p>
                
                <div class="form-group">
                    <label class="form-label">Reason for rejection:</label>
                    <select name="bulk_reason" class="form-control" required>
                        <option value="">Select a reason</option>
                        <option value="Inappropriate content">Inappropriate content</option>
                        <option value="Spam or fake review">Spam or fake review</option>
                        <option value="Off-topic content">Off-topic content</option>
                        <option value="Promotional content">Promotional content</option>
                        <option value="Personal information disclosed">Personal information disclosed</option>
                        <option value="Violates community guidelines">Violates community guidelines</option>
                        <option value="Other">Other</option>
                    </select>
                </div>
        </div>
        <div class="modal-footer">
            <button type="submit" class="btn btn-danger">Reject Selected Reviews</button>
            <button type="button" class="btn btn-light modal-close">Cancel</button>
        </div>
            </form>
    </div>
</div>

<style>
.review-content {
    max-width: 300px;
}

.product-thumb {
    border-radius: 4px;
    border: 1px solid #ddd;
}

.rating .fa-star {
    font-size: 0.9rem;
}

.bulk-actions {
    display: flex;
    align-items: center;
    gap: 10px;
}

.pagination {
    margin-bottom: 0;
}

.modal {
    max-width: 600px;
}

.customer-info strong {
    color: #333;
}

.badge {
    font-size: 0.75rem;
    padding: 4px 8px;
}

.badge-warning {
    background-color: #ffc107;
    color: #000;
}

.badge-success {
    background-color: #28a745;
    color: #fff;
}

.badge-danger {
    background-color: #dc3545;
    color: #fff;
}

.btn-group-sm .btn {
    padding: 4px 8px;
    font-size: 0.8rem;
}

.stats-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
    gap: 20px;
}

.stat-card {
    background: white;
    padding: 20px;
    border-radius: 8px;
    box-shadow: 0 2px 4px rgba(0,0,0,0.1);
    display: flex;
    justify-content: space-between;
    align-items: center;
}

.stat-content h3 {
    font-size: 2rem;
    font-weight: bold;
    margin: 0 0 5px 0;
}

.stat-content p {
    margin: 0;
    color: #666;
}

.stat-icon {
    font-size: 2.5rem;
    opacity: 0.3;
}
</style>

<script>
$(document).ready(function() {
    // Select all functionality
    $('#selectAll').on('change', function() {
        $('.review-checkbox').prop('checked', $(this).is(':checked'));
        updateBulkActions();
    });
    
    $('.review-checkbox').on('change', function() {
        updateBulkActions();
        updateSelectAllState();
    });
    
    function updateBulkActions() {
        const selectedCount = $('.review-checkbox:checked').length;
        if (selectedCount > 0) {
            $('.bulk-actions').show();
            $('.selected-count').text(selectedCount);
        } else {
            $('.bulk-actions').hide();
        }
    }
    
    function updateSelectAllState() {
        const totalCheckboxes = $('.review-checkbox').length;
        const checkedCheckboxes = $('.review-checkbox:checked').length;
        
        $('#selectAll').prop('indeterminate', checkedCheckboxes > 0 && checkedCheckboxes < totalCheckboxes);
        $('#selectAll').prop('checked', checkedCheckboxes === totalCheckboxes);
    }
    
    // View review details
    $('.view-review').on('click', function() {
        const reviewId = $(this).data('review-id');
        loadReviewDetails(reviewId);
    });
    
    // Approve review
    $('.approve-review').on('click', function() {
        const reviewId = $(this).data('review-id');
        if (confirm('Are you sure you want to approve this review?')) {
            submitAction('approve', reviewId);
        }
    });
    
    // Reject review
    $('.reject-review').on('click', function() {
        const reviewId = $(this).data('review-id');
        $('#rejectReviewId').val(reviewId);
        openModal('rejectModal');
    });
    
    // Bulk approve
    $('.bulk-approve').on('click', function() {
        const selectedIds = getSelectedReviewIds();
        if (selectedIds.length === 0) {
            alert('Please select reviews to approve');
            return;
        }
        
        if (confirm(`Are you sure you want to approve ${selectedIds.length} reviews?`)) {
            submitBulkAction('bulk_approve', selectedIds);
        }
    });
    
    // Bulk reject
    $('.bulk-reject').on('click', function() {
        const selectedIds = getSelectedReviewIds();
        if (selectedIds.length === 0) {
            alert('Please select reviews to reject');
            return;
        }
        
        $('#bulkRejectCount').text(selectedIds.length);
        openModal('bulkRejectModal');
    });
    
    // Submit reject form
    $('#rejectForm').on('submit', function(e) {
        e.preventDefault();
        
        const formData = new FormData(this);
        
        $.ajax({
            url: 'moderate.php',
            type: 'POST',
            data: formData,
            processData: false,
            contentType: false,
            success: function(response) {
                closeModal();
                location.reload();
            },
            error: function() {
                alert('Error rejecting review');
            }
        });
    });
    
    // Submit bulk reject form
    $('#bulkRejectForm').on('submit', function(e) {
        e.preventDefault();
        
        const selectedIds = getSelectedReviewIds();
        const reason = $(this).find('[name="bulk_reason"]').val();
        
        if (!reason) {
            alert('Please select a reason for rejection');
            return;
        }
        
        submitBulkAction('bulk_reject', selectedIds, { bulk_reason: reason });
    });
    
    function getSelectedReviewIds() {
        return $('.review-checkbox:checked').map(function() {
            return $(this).val();
        }).get();
    }
    
    function submitAction(action, reviewId) {
        $.post('moderate.php', {
            action: action,
            review_id: reviewId
        }, function(response) {
            location.reload();
        }).fail(function() {
            alert('Error performing action');
        });
    }
    
    function submitBulkAction(action, reviewIds, extraData = {}) {
        const data = {
            action: action,
            review_ids: reviewIds,
            ...extraData
        };
        
        $.post('moderate.php', data, function(response) {
            closeModal();
            location.reload();
        }).fail(function() {
            alert('Error performing bulk action');
        });
    }
    
    function loadReviewDetails(reviewId) {
        $.get('../api/review_details.php', { id: reviewId }, function(response) {
            if (response.success) {
                displayReviewDetails(response.review);
                openModal('reviewModal');
            } else {
                alert('Error loading review details');
            }
        }, 'json').fail(function() {
            alert('Error loading review details');
        });
    }
    
    function displayReviewDetails(review) {
        const html = `
            <div class="review-details">
                <div class="row">
                    <div class="col-md-8">
                        <h5>${review.title || 'No Title'}</h5>
                        <div class="rating mb-2">
                            ${generateStars(review.rating)} (${review.rating}/5)
                        </div>
                        <p>${review.comment.replace(/\n/g, '<br>')}</p>
                        
                        <div class="review-meta">
                            <strong>Customer:</strong> ${review.customer_name} (${review.customer_email})<br>
                            <strong>Date:</strong> ${new Date(review.created_at).toLocaleDateString()}<br>
                            <strong>Verified Purchase:</strong> ${review.verified_purchase ? 'Yes' : 'No'}
                        </div>
                    </div>
                    <div class="col-md-4">
                        <div class="product-info text-center">
                            ${review.product_image ? `<img src="../../uploads/products/${review.product_image}" alt="Product" style="max-width: 100%; height: auto; border-radius: 8px;">` : ''}
                            <h6 class="mt-2">${review.product_name}</h6>
                        </div>
                    </div>
                </div>
                
                ${review.status === 'rejected' && review.moderation_reason ? `
                    <div class="alert alert-danger mt-3">
                        <strong>Rejection Reason:</strong> ${review.moderation_reason}
                    </div>
                ` : ''}
                
                <div class="review-actions mt-3">
                    ${review.status === 'pending' ? `
                        <button class="btn btn-success" onclick="submitAction('approve', ${review.id})">
                            <i class="fas fa-check"></i> Approve
                        </button>
                        <button class="btn btn-danger" onclick="showRejectModal(${review.id})">
                            <i class="fas fa-times"></i> Reject
                        </button>
                    ` : ''}
                    <button class="btn btn-info" onclick="showReplyModal(${review.id})">
                        <i class="fas fa-reply"></i> Reply
                    </button>
                </div>
            </div>
        `;
        
        $('#reviewModalContent').html(html);
    }
    
    function generateStars(rating) {
        let stars = '';
        for (let i = 1; i <= 5; i++) {
            stars += `<i class="fas fa-star ${i <= rating ? 'text-warning' : 'text-muted'}"></i>`;
        }
        return stars;
    }
    
    // Modal functions
    function openModal(modalId) {
        $('#' + modalId).addClass('show');
        $('body').addClass('modal-open');
    }
    
    function closeModal() {
        $('.modal-overlay').removeClass('show');
        $('body').removeClass('modal-open');
    }
    
    $('.modal-close, .modal-overlay').on('click', function(e) {
        if (e.target === this) {
            closeModal();
        }
    });
    
    // Prevent modal close when clicking inside modal content
    $('.modal').on('click', function(e) {
        e.stopPropagation();
    });
});

// Global functions for modal actions
function showRejectModal(reviewId) {
    $('#rejectReviewId').val(reviewId);
    openModal('rejectModal');
}

function showReplyModal(reviewId) {
    // Implementation for reply modal
    alert('Reply functionality to be implemented');
}
</script>

<?php include '../layouts/footer.php'; ?>