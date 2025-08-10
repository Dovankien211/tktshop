<?php
/**
 * TKT Shop - Delete Review
 * Xóa đánh giá sản phẩm
 */

require_once '../../config/config.php';
require_once '../../config/database.php';

// Kiểm tra đăng nhập admin
if (!isset($_SESSION['admin_id'])) {
    header('Location: ../login.php');
    exit;
}

$review_id = $_GET['id'] ?? $_POST['review_id'] ?? 0;
$success = '';
$error = '';

if (!$review_id) {
    $_SESSION['error'] = 'Review ID is required';
    header('Location: index.php');
    exit;
}

// Lấy thông tin review trước khi xóa
$stmt = $pdo->prepare("
    SELECT pr.*, c.name as customer_name, p.name as product_name 
    FROM product_reviews pr
    LEFT JOIN customers c ON pr.customer_id = c.id
    LEFT JOIN products p ON pr.product_id = p.id
    WHERE pr.id = ?
");
$stmt->execute([$review_id]);
$review = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$review) {
    $_SESSION['error'] = 'Review not found';
    header('Location: index.php');
    exit;
}

// Xử lý xóa review
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    
    if ($action === 'delete') {
        try {
            $pdo->beginTransaction();
            
            // Xóa replies của review này trước (nếu có bảng review_replies)
            $deleteReplies = $pdo->prepare("DELETE FROM review_replies WHERE review_id = ?");
            $deleteReplies->execute([$review_id]);
            
            // Xóa review
            $deleteReview = $pdo->prepare("DELETE FROM product_reviews WHERE id = ?");
            $deleteReview->execute([$review_id]);
            
            if ($deleteReview->rowCount() > 0) {
                // Log activity
                logAdminActivity('DELETE_REVIEW', "Deleted review #$review_id for product: {$review['product_name']} by customer: {$review['customer_name']}");
                
                // Update product rating sau khi xóa review
                updateProductRating($review['product_id']);
                
                $pdo->commit();
                $_SESSION['success'] = 'Review deleted successfully';
            } else {
                $pdo->rollback();
                $_SESSION['error'] = 'Failed to delete review';
            }
            
        } catch (Exception $e) {
            $pdo->rollback();
            $_SESSION['error'] = 'Database error: ' . $e->getMessage();
        }
        
        header('Location: index.php');
        exit;
    }
}

// Xử lý AJAX delete
if (isset($_POST['ajax']) && $_POST['ajax'] === '1') {
    header('Content-Type: application/json');
    
    try {
        $pdo->beginTransaction();
        
        // Xóa replies
        $deleteReplies = $pdo->prepare("DELETE FROM review_replies WHERE review_id = ?");
        $deleteReplies->execute([$review_id]);
        
        // Xóa review
        $deleteReview = $pdo->prepare("DELETE FROM product_reviews WHERE id = ?");
        $deleteReview->execute([$review_id]);
        
        if ($deleteReview->rowCount() > 0) {
            // Log activity
            logAdminActivity('DELETE_REVIEW', "Deleted review #$review_id for product: {$review['product_name']}");
            
            // Update product rating
            updateProductRating($review['product_id']);
            
            $pdo->commit();
            echo json_encode(['success' => true, 'message' => 'Review deleted successfully']);
        } else {
            $pdo->rollback();
            echo json_encode(['success' => false, 'message' => 'Failed to delete review']);
        }
        
    } catch (Exception $e) {
        $pdo->rollback();
        echo json_encode(['success' => false, 'message' => 'Database error: ' . $e->getMessage()]);
    }
    
    exit;
}

$page_title = "Delete Review";
include '../layouts/header.php';
?>

<div class="content-area">
    <div class="page-header">
        <h1 class="page-title">Delete Review</h1>
        <div class="page-actions">
            <a href="index.php" class="btn btn-light">
                <i class="fas fa-arrow-left"></i> Back to Reviews
            </a>
        </div>
    </div>

    <div class="admin-card">
        <div class="admin-card-header">
            <h3 class="admin-card-title text-danger">
                <i class="fas fa-exclamation-triangle"></i> 
                Confirm Review Deletion
            </h3>
        </div>
        <div class="admin-card-body">
            <div class="alert alert-warning">
                <strong>Warning:</strong> This action cannot be undone. Deleting this review will permanently remove it from the system.
            </div>

            <!-- Review Details -->
            <div class="review-details mb-30">
                <div class="row">
                    <div class="col-md-8">
                        <h5 class="mb-15">Review Details</h5>
                        
                        <div class="info-group">
                            <label><strong>Customer:</strong></label>
                            <span><?php echo htmlspecialchars($review['customer_name']); ?></span>
                        </div>
                        
                        <div class="info-group">
                            <label><strong>Product:</strong></label>
                            <span><?php echo htmlspecialchars($review['product_name']); ?></span>
                        </div>
                        
                        <div class="info-group">
                            <label><strong>Rating:</strong></label>
                            <span>
                                <?php for ($i = 1; $i <= 5; $i++): ?>
                                    <i class="fas fa-star <?php echo $i <= $review['rating'] ? 'text-warning' : 'text-muted'; ?>"></i>
                                <?php endfor; ?>
                                (<?php echo $review['rating']; ?>/5)
                            </span>
                        </div>
                        
                        <?php if ($review['title']): ?>
                        <div class="info-group">
                            <label><strong>Title:</strong></label>
                            <span><?php echo htmlspecialchars($review['title']); ?></span>
                        </div>
                        <?php endif; ?>
                        
                        <div class="info-group">
                            <label><strong>Comment:</strong></label>
                            <div class="review-comment">
                                <?php echo nl2br(htmlspecialchars($review['comment'])); ?>
                            </div>
                        </div>
                        
                        <div class="info-group">
                            <label><strong>Status:</strong></label>
                            <span class="badge badge-<?php echo $review['status'] === 'approved' ? 'success' : ($review['status'] === 'rejected' ? 'danger' : 'warning'); ?>">
                                <?php echo ucfirst($review['status']); ?>
                            </span>
                        </div>
                        
                        <div class="info-group">
                            <label><strong>Created:</strong></label>
                            <span><?php echo date('M j, Y g:i A', strtotime($review['created_at'])); ?></span>
                        </div>
                    </div>
                    
                    <div class="col-md-4">
                        <h6>Impact of Deletion</h6>
                        <ul class="deletion-impact">
                            <li><i class="fas fa-times text-danger"></i> Review will be permanently removed</li>
                            <li><i class="fas fa-times text-danger"></i> Customer replies will be deleted</li>
                            <li><i class="fas fa-calculator text-warning"></i> Product rating will be recalculated</li>
                            <li><i class="fas fa-chart-line text-warning"></i> Review statistics will be updated</li>
                        </ul>
                    </div>
                </div>
            </div>

            <!-- Deletion Form -->
            <form method="POST" class="deletion-form">
                <input type="hidden" name="action" value="delete">
                <input type="hidden" name="review_id" value="<?php echo $review['id']; ?>">
                
                <div class="form-group">
                    <div class="form-check">
                        <input type="checkbox" class="form-check-input" id="confirmDelete" required>
                        <label class="form-check-label" for="confirmDelete">
                            I understand that this action cannot be undone and want to permanently delete this review.
                        </label>
                    </div>
                </div>
                
                <div class="form-actions">
                    <button type="submit" class="btn btn-danger" id="deleteBtn" disabled>
                        <i class="fas fa-trash"></i> Delete Review Permanently
                    </button>
                    <a href="index.php" class="btn btn-light">
                        <i class="fas fa-times"></i> Cancel
                    </a>
                </div>
            </form>
        </div>
    </div>
</div>

<style>
.info-group {
    margin-bottom: 15px;
    padding-bottom: 10px;
    border-bottom: 1px solid #f0f0f0;
}

.info-group:last-child {
    border-bottom: none;
    margin-bottom: 0;
}

.info-group label {
    display: inline-block;
    width: 120px;
    margin-bottom: 0;
    font-weight: 600;
    color: #333;
}

.review-comment {
    background: #f8f9fa;
    padding: 15px;
    border-radius: 6px;
    border-left: 4px solid #007bff;
    margin-top: 10px;
    max-height: 200px;
    overflow-y: auto;
}

.deletion-impact {
    list-style: none;
    padding: 0;
}

.deletion-impact li {
    padding: 8px 0;
    display: flex;
    align-items: center;
}

.deletion-impact li i {
    margin-right: 10px;
    width: 16px;
}

.form-actions {
    display: flex;
    gap: 15px;
    margin-top: 30px;
    padding-top: 20px;
    border-top: 2px solid #f0f0f0;
}

.badge {
    font-size: 0.8rem;
    padding: 4px 8px;
}

.badge-success {
    background-color: #28a745;
    color: white;
}

.badge-danger {
    background-color: #dc3545;
    color: white;
}

.badge-warning {
    background-color: #ffc107;
    color: #000;
}

.admin-card-title.text-danger {
    color: #dc3545 !important;
}

.deletion-form {
    background: #fff5f5;
    padding: 20px;
    border-radius: 8px;
    border: 1px solid #fed7d7;
}
</style>

<script>
$(document).ready(function() {
    // Enable delete button only when checkbox is checked
    $('#confirmDelete').on('change', function() {
        $('#deleteBtn').prop('disabled', !$(this).is(':checked'));
    });
    
    // Add confirmation on form submit
    $('.deletion-form').on('submit', function(e) {
        if (!confirm('Are you absolutely sure you want to delete this review? This action cannot be undone.')) {
            e.preventDefault();
            return false;
        }
        
        // Show loading state
        $('#deleteBtn').prop('disabled', true).html('<i class="fas fa-spinner fa-spin"></i> Deleting...');
    });
});

// Function for AJAX delete (can be called from other pages)
function deleteReviewAjax(reviewId) {
    if (!confirm('Are you sure you want to delete this review?')) {
        return;
    }
    
    $.ajax({
        url: 'delete.php',
        type: 'POST',
        data: {
            review_id: reviewId,
            ajax: 1
        },
        dataType: 'json',
        success: function(response) {
            if (response.success) {
                showAlert('success', response.message);
                // Remove the review row if on index page
                $(`tr[data-review-id="${reviewId}"]`).fadeOut(300, function() {
                    $(this).remove();
                });
            } else {
                showAlert('error', response.message);
            }
        },
        error: function() {
            showAlert('error', 'Error deleting review');
        }
    });
}

function showAlert(type, message) {
    const alertClass = type === 'success' ? 'alert-success' : 'alert-danger';
    const alertHtml = `
        <div class="alert ${alertClass} alert-dismissible fade show" role="alert">
            ${message}
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    `;
    
    $('.content-area').prepend(alertHtml);
    
    // Auto hide after 5 seconds
    setTimeout(() => {
        $('.alert').fadeOut();
    }, 5000);
}
</script>

<?php include '../layouts/footer.php'; ?>

<?php
/**
 * Helper Functions
 */

/**
 * Update product rating after review deletion
 */
function updateProductRating($productId) {
    global $pdo;
    
    try {
        // Calculate new average rating
        $stmt = $pdo->prepare("
            SELECT AVG(rating) as avg_rating, COUNT(*) as review_count 
            FROM product_reviews 
            WHERE product_id = ? AND status = 'approved'
        ");
        $stmt->execute([$productId]);
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        
        $avgRating = $result['avg_rating'] ? round($result['avg_rating'], 1) : 0;
        $reviewCount = $result['review_count'];
        
        // Update product table
        $updateStmt = $pdo->prepare("
            UPDATE products 
            SET average_rating = ?, review_count = ?, updated_at = NOW() 
            WHERE id = ?
        ");
        $updateStmt->execute([$avgRating, $reviewCount, $productId]);
        
    } catch (Exception $e) {
        error_log("Error updating product rating: " . $e->getMessage());
    }
}

/**
 * Log admin activity
 */
function logAdminActivity($action, $details = '') {
    global $pdo;
    
    try {
        $stmt = $pdo->prepare("
            INSERT INTO admin_activity_logs (admin_id, action, details, ip_address, user_agent, created_at) 
            VALUES (?, ?, ?, ?, ?, NOW())
        ");
        $stmt->execute([
            $_SESSION['admin_id'],
            $action,
            $details,
            $_SERVER['REMOTE_ADDR'] ?? '',
            $_SERVER['HTTP_USER_AGENT'] ?? ''
        ]);
    } catch (Exception $e) {
        error_log("Error logging admin activity: " . $e->getMessage());
    }
}
?>