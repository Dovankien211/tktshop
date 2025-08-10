<?php
/**
 * TKT Shop - Delete Size
 * Xóa kích cỡ sản phẩm
 */

require_once '../../config/config.php';
require_once '../../config/database.php';

// Kiểm tra đăng nhập admin
if (!isset($_SESSION['admin_id'])) {
    header('Location: ../login.php');
    exit;
}

$size_id = $_GET['id'] ?? $_POST['size_id'] ?? 0;

if (!$size_id) {
    $_SESSION['error'] = 'Size ID is required';
    header('Location: index.php');
    exit;
}

// Lấy thông tin size trước khi xóa
$stmt = $pdo->prepare("SELECT * FROM sizes WHERE id = ?");
$stmt->execute([$size_id]);
$size = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$size) {
    $_SESSION['error'] = 'Size not found';
    header('Location: index.php');
    exit;
}

// Kiểm tra xem size có đang được sử dụng không
$checkUsage = $pdo->prepare("
    SELECT COUNT(*) as total_usage,
           (SELECT COUNT(*) FROM product_variants WHERE size = ?) as variant_usage,
           (SELECT COUNT(*) FROM order_items oi 
            JOIN product_variants pv ON oi.variant_id = pv.id 
            WHERE pv.size = ?) as order_usage
");
$checkUsage->execute([$size['name'], $size['name']]);
$usage = $checkUsage->fetch(PDO::FETCH_ASSOC);

// Xử lý AJAX delete
if (isset($_POST['ajax']) && $_POST['ajax'] === '1') {
    header('Content-Type: application/json');
    
    if ($usage['variant_usage'] > 0) {
        echo json_encode([
            'success' => false, 
            'message' => "Cannot delete size '{$size['name']}'. It is being used by {$usage['variant_usage']} product variants."
        ]);
        exit;
    }
    
    try {
        $deleteStmt = $pdo->prepare("DELETE FROM sizes WHERE id = ?");
        $result = $deleteStmt->execute([$size_id]);
        
        if ($result && $deleteStmt->rowCount() > 0) {
            // Log activity
            logAdminActivity('DELETE_SIZE', "Deleted size: {$size['name']}");
            
            echo json_encode(['success' => true, 'message' => 'Size deleted successfully']);
        } else {
            echo json_encode(['success' => false, 'message' => 'Failed to delete size']);
        }
        
    } catch (Exception $e) {
        echo json_encode(['success' => false, 'message' => 'Database error: ' . $e->getMessage()]);
    }
    
    exit;
}

// Xử lý form delete
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    $force_delete = isset($_POST['force_delete']) ? true : false;
    
    if ($action === 'delete') {
        try {
            // Nếu size đang được sử dụng và không force delete
            if ($usage['variant_usage'] > 0 && !$force_delete) {
                $_SESSION['error'] = "Cannot delete size '{$size['name']}'. It is being used by {$usage['variant_usage']} product variants.";
                header('Location: index.php');
                exit;
            }
            
            $pdo->beginTransaction();
            
            if ($force_delete && $usage['variant_usage'] > 0) {
                // Nếu force delete, xóa hoặc update các references
                $action_type = $_POST['force_action'] ?? 'set_null';
                
                switch ($action_type) {
                    case 'set_null':
                        // Set size thành NULL trong product_variants
                        $updateStmt = $pdo->prepare("UPDATE product_variants SET size = NULL WHERE size = ?");
                        $updateStmt->execute([$size['name']]);
                        break;
                        
                    case 'delete_variants':
                        // Xóa tất cả product variants sử dụng size này
                        $deleteVariantsStmt = $pdo->prepare("DELETE FROM product_variants WHERE size = ?");
                        $deleteVariantsStmt->execute([$size['name']]);
                        break;
                        
                    case 'replace':
                        // Replace với size khác
                        $replacement_size = $_POST['replacement_size'] ?? '';
                        if ($replacement_size) {
                            $replaceStmt = $pdo->prepare("UPDATE product_variants SET size = ? WHERE size = ?");
                            $replaceStmt->execute([$replacement_size, $size['name']]);
                        }
                        break;
                }
            }
            
            // Xóa size
            $deleteStmt = $pdo->prepare("DELETE FROM sizes WHERE id = ?");
            $result = $deleteStmt->execute([$size_id]);
            
            if ($result && $deleteStmt->rowCount() > 0) {
                // Log activity
                $details = "Deleted size: {$size['name']}";
                if ($force_delete) {
                    $details .= " (Force delete with action: {$action_type})";
                }
                logAdminActivity('DELETE_SIZE', $details);
                
                $pdo->commit();
                $_SESSION['success'] = 'Size deleted successfully';
            } else {
                $pdo->rollback();
                $_SESSION['error'] = 'Failed to delete size';
            }
            
        } catch (Exception $e) {
            $pdo->rollback();
            $_SESSION['error'] = 'Database error: ' . $e->getMessage();
        }
        
        header('Location: index.php');
        exit;
    }
}

// Lấy danh sách sizes khác để replace (nếu cần)
$otherSizes = $pdo->prepare("SELECT * FROM sizes WHERE id != ? ORDER BY sort_order, name");
$otherSizes->execute([$size_id]);
$otherSizes = $otherSizes->fetchAll(PDO::FETCH_ASSOC);

$page_title = "Delete Size";
include '../layouts/header.php';
?>

<div class="content-area">
    <div class="page-header">
        <h1 class="page-title">Delete Size</h1>
        <div class="page-actions">
            <a href="index.php" class="btn btn-light">
                <i class="fas fa-arrow-left"></i> Back to Sizes
            </a>
        </div>
    </div>

    <div class="admin-card">
        <div class="admin-card-header">
            <h3 class="admin-card-title text-danger">
                <i class="fas fa-exclamation-triangle"></i> 
                Confirm Size Deletion
            </h3>
        </div>
        <div class="admin-card-body">
            <?php if ($usage['variant_usage'] > 0): ?>
                <div class="alert alert-warning">
                    <strong>Warning:</strong> This size is currently being used by <strong><?php echo $usage['variant_usage']; ?></strong> product variants.
                    <?php if ($usage['order_usage'] > 0): ?>
                        It also appears in <strong><?php echo $usage['order_usage']; ?></strong> order items.
                    <?php endif; ?>
                </div>
            <?php else: ?>
                <div class="alert alert-info">
                    <strong>Safe to delete:</strong> This size is not currently being used by any products.
                </div>
            <?php endif; ?>

            <!-- Size Details -->
            <div class="size-details mb-30">
                <h5 class="mb-15">Size Details</h5>
                
                <div class="row">
                    <div class="col-md-6">
                        <div class="info-group">
                            <label><strong>Size Name:</strong></label>
                            <span class="size-preview"><?php echo htmlspecialchars($size['name']); ?></span>
                        </div>
                        
                        <div class="info-group">
                            <label><strong>Display Name:</strong></label>
                            <span><?php echo htmlspecialchars($size['display_name'] ?: $size['name']); ?></span>
                        </div>
                        
                        <?php if ($size['description']): ?>
                        <div class="info-group">
                            <label><strong>Description:</strong></label>
                            <span><?php echo htmlspecialchars($size['description']); ?></span>
                        </div>
                        <?php endif; ?>
                        
                        <div class="info-group">
                            <label><strong>Sort Order:</strong></label>
                            <span><?php echo $size['sort_order']; ?></span>
                        </div>
                        
                        <div class="info-group">
                            <label><strong>Status:</strong></label>
                            <span class="badge badge-<?php echo $size['status'] === 'active' ? 'success' : 'secondary'; ?>">
                                <?php echo ucfirst($size['status']); ?>
                            </span>
                        </div>
                    </div>
                    
                    <div class="col-md-6">
                        <h6>Usage Statistics</h6>
                        <div class="usage-stats">
                            <div class="stat-item">
                                <span class="stat-number"><?php echo $usage['variant_usage']; ?></span>
                                <span class="stat-label">Product Variants</span>
                            </div>
                            <div class="stat-item">
                                <span class="stat-number"><?php echo $usage['order_usage']; ?></span>
                                <span class="stat-label">Order Items</span>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Deletion Form -->
            <form method="POST" class="deletion-form" id="deleteForm">
                <input type="hidden" name="action" value="delete">
                <input type="hidden" name="size_id" value="<?php echo $size['id']; ?>">
                
                <?php if ($usage['variant_usage'] > 0): ?>
                    <div class="force-delete-options">
                        <h6>Deletion Options</h6>
                        <p class="text-muted">Since this size is being used, please choose how to handle the existing references:</p>
                        
                        <div class="form-check">
                            <input type="radio" class="form-check-input" name="force_action" value="set_null" id="setNull" checked>
                            <label class="form-check-label" for="setNull">
                                <strong>Remove size from variants</strong>
                                <br><small class="text-muted">Set size to "No Size" for affected product variants</small>
                            </label>
                        </div>
                        
                        <?php if (!empty($otherSizes)): ?>
                        <div class="form-check">
                            <input type="radio" class="form-check-input" name="force_action" value="replace" id="replace">
                            <label class="form-check-label" for="replace">
                                <strong>Replace with another size</strong>
                                <br><small class="text-muted">Replace this size with an existing size</small>
                            </label>
                            <div class="replacement-options mt-2" style="display: none;">
                                <select name="replacement_size" class="form-control">
                                    <option value="">Select replacement size</option>
                                    <?php foreach ($otherSizes as $otherSize): ?>
                                        <option value="<?php echo htmlspecialchars($otherSize['name']); ?>">
                                            <?php echo htmlspecialchars($otherSize['display_name'] ?: $otherSize['name']); ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                        </div>
                        <?php endif; ?>
                        
                        <div class="form-check">
                            <input type="radio" class="form-check-input" name="force_action" value="delete_variants" id="deleteVariants">
                            <label class="form-check-label" for="deleteVariants">
                                <strong class="text-danger">Delete all affected variants</strong>
                                <br><small class="text-muted text-danger">⚠️ This will permanently delete <?php echo $usage['variant_usage']; ?> product variants</small>
                            </label>
                        </div>
                        
                        <div class="form-check mt-20">
                            <input type="checkbox" class="form-check-input" name="force_delete" value="1" id="forceDelete" required>
                            <label class="form-check-label" for="forceDelete">
                                I understand the consequences and want to proceed with the deletion.
                            </label>
                        </div>
                    </div>
                <?php else: ?>
                    <div class="form-group">
                        <div class="form-check">
                            <input type="checkbox" class="form-check-input" id="confirmDelete" required>
                            <label class="form-check-label" for="confirmDelete">
                                I confirm that I want to delete this size permanently.
                            </label>
                        </div>
                    </div>
                <?php endif; ?>
                
                <div class="form-actions">
                    <button type="submit" class="btn btn-danger" id="deleteBtn" disabled>
                        <i class="fas fa-trash"></i> Delete Size
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
}

.info-group label {
    display: inline-block;
    width: 120px;
    margin-bottom: 0;
    font-weight: 600;
    color: #333;
}

.size-preview {
    background: #f8f9fa;
    padding: 5px 10px;
    border-radius: 4px;
    border: 1px solid #ddd;
    font-weight: 600;
}

.usage-stats {
    display: flex;
    gap: 20px;
}

.stat-item {
    text-align: center;
    padding: 15px;
    background: #f8f9fa;
    border-radius: 8px;
    border: 1px solid #e9ecef;
}

.stat-number {
    display: block;
    font-size: 1.5rem;
    font-weight: bold;
    color: #007bff;
}

.stat-label {
    font-size: 0.8rem;
    color: #666;
}

.force-delete-options {
    background: #fff5f5;
    padding: 20px;
    border-radius: 8px;
    border: 1px solid #fed7d7;
    margin-bottom: 20px;
}

.form-check {
    margin-bottom: 15px;
    padding: 10px;
    border: 1px solid #e9ecef;
    border-radius: 6px;
    background: white;
}

.form-check:hover {
    background: #f8f9fa;
}

.form-check-input:checked + .form-check-label {
    color: #007bff;
}

.replacement-options {
    margin-left: 25px;
    padding: 10px;
    background: #f8f9fa;
    border-radius: 4px;
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

.badge-secondary {
    background-color: #6c757d;
    color: white;
}

.deletion-form {
    background: #fafafa;
    padding: 20px;
    border-radius: 8px;
    border: 1px solid #e9ecef;
}
</style>

<script>
$(document).ready(function() {
    // Enable delete button based on confirmation
    function updateDeleteButton() {
        const hasUsage = <?php echo $usage['variant_usage'] > 0 ? 'true' : 'false'; ?>;
        let canDelete = false;
        
        if (hasUsage) {
            canDelete = $('#forceDelete').is(':checked');
        } else {
            canDelete = $('#confirmDelete').is(':checked');
        }
        
        $('#deleteBtn').prop('disabled', !canDelete);
    }
    
    $('#confirmDelete, #forceDelete').on('change', updateDeleteButton);
    
    // Show/hide replacement options
    $('input[name="force_action"]').on('change', function() {
        if ($(this).val() === 'replace') {
            $('.replacement-options').show();
        } else {
            $('.replacement-options').hide();
        }
    });
    
    // Form validation
    $('#deleteForm').on('submit', function(e) {
        const hasUsage = <?php echo $usage['variant_usage'] > 0 ? 'true' : 'false'; ?>;
        
        if (hasUsage) {
            const action = $('input[name="force_action"]:checked').val();
            
            if (action === 'replace') {
                const replacement = $('select[name="replacement_size"]').val();
                if (!replacement) {
                    e.preventDefault();
                    alert('Please select a replacement size');
                    return false;
                }
            }
            
            if (action === 'delete_variants') {
                if (!confirm('This will permanently delete <?php echo $usage['variant_usage']; ?> product variants. Are you absolutely sure?')) {
                    e.preventDefault();
                    return false;
                }
            }
        }
        
        // Final confirmation
        if (!confirm('Are you sure you want to delete this size? This action cannot be undone.')) {
            e.preventDefault();
            return false;
        }
        
        // Show loading state
        $('#deleteBtn').prop('disabled', true).html('<i class="fas fa-spinner fa-spin"></i> Deleting...');
    });
});

// Function for AJAX delete (can be called from other pages)
function deleteSizeAjax(sizeId) {
    if (!confirm('Are you sure you want to delete this size?')) {
        return;
    }
    
    $.ajax({
        url: 'delete.php',
        type: 'POST',
        data: {
            size_id: sizeId,
            ajax: 1
        },
        dataType: 'json',
        success: function(response) {
            if (response.success) {
                showAlert('success', response.message);
                // Remove the size row if on index page
                $(`tr[data-size-id="${sizeId}"]`).fadeOut(300, function() {
                    $(this).remove();
                });
            } else {
                showAlert('error', response.message);
            }
        },
        error: function() {
            showAlert('error', 'Error deleting size');
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

/**
 * Get size usage statistics
 */
function getSizeUsageStats($sizeName) {
    global $pdo;
    
    try {
        $stmt = $pdo->prepare("
            SELECT 
                (SELECT COUNT(*) FROM product_variants WHERE size = ?) as variant_count,
                (SELECT COUNT(DISTINCT product_id) FROM product_variants WHERE size = ?) as product_count,
                (SELECT COUNT(*) FROM order_items oi 
                 JOIN product_variants pv ON oi.variant_id = pv.id 
                 WHERE pv.size = ?) as order_count
        ");
        $stmt->execute([$sizeName, $sizeName, $sizeName]);
        return $stmt->fetch(PDO::FETCH_ASSOC);
    } catch (Exception $e) {
        return [
            'variant_count' => 0,
            'product_count' => 0,
            'order_count' => 0
        ];
    }
}

/**
 * Check if size can be safely deleted
 */
function canDeleteSize($sizeId) {
    global $pdo;
    
    try {
        $stmt = $pdo->prepare("
            SELECT name FROM sizes WHERE id = ?
        ");
        $stmt->execute([$sizeId]);
        $sizeName = $stmt->fetchColumn();
        
        if (!$sizeName) {
            return ['can_delete' => false, 'reason' => 'Size not found'];
        }
        
        $usageStmt = $pdo->prepare("
            SELECT COUNT(*) FROM product_variants WHERE size = ?
        ");
        $usageStmt->execute([$sizeName]);
        $usageCount = $usageStmt->fetchColumn();
        
        if ($usageCount > 0) {
            return [
                'can_delete' => false, 
                'reason' => "Size is used by {$usageCount} product variants",
                'usage_count' => $usageCount
            ];
        }
        
        return ['can_delete' => true];
        
    } catch (Exception $e) {
        return ['can_delete' => false, 'reason' => 'Database error'];
    }
}

/**
 * Bulk delete sizes
 */
function bulkDeleteSizes($sizeIds, $options = []) {
    global $pdo;
    
    try {
        $pdo->beginTransaction();
        
        $deletedCount = 0;
        $errors = [];
        
        foreach ($sizeIds as $sizeId) {
            $canDelete = canDeleteSize($sizeId);
            
            if (!$canDelete['can_delete'] && !($options['force'] ?? false)) {
                $errors[] = "Size ID {$sizeId}: " . $canDelete['reason'];
                continue;
            }
            
            // Get size info
            $stmt = $pdo->prepare("SELECT name FROM sizes WHERE id = ?");
            $stmt->execute([$sizeId]);
            $sizeName = $stmt->fetchColumn();
            
            if (!$sizeName) {
                $errors[] = "Size ID {$sizeId}: Not found";
                continue;
            }
            
            // Handle force delete if needed
            if ($options['force'] ?? false) {
                $action = $options['force_action'] ?? 'set_null';
                
                switch ($action) {
                    case 'set_null':
                        $updateStmt = $pdo->prepare("UPDATE product_variants SET size = NULL WHERE size = ?");
                        $updateStmt->execute([$sizeName]);
                        break;
                        
                    case 'delete_variants':
                        $deleteVariantsStmt = $pdo->prepare("DELETE FROM product_variants WHERE size = ?");
                        $deleteVariantsStmt->execute([$sizeName]);
                        break;
                        
                    case 'replace':
                        if (!empty($options['replacement_size'])) {
                            $replaceStmt = $pdo->prepare("UPDATE product_variants SET size = ? WHERE size = ?");
                            $replaceStmt->execute([$options['replacement_size'], $sizeName]);
                        }
                        break;
                }
            }
            
            // Delete size
            $deleteStmt = $pdo->prepare("DELETE FROM sizes WHERE id = ?");
            if ($deleteStmt->execute([$sizeId]) && $deleteStmt->rowCount() > 0) {
                $deletedCount++;
                logAdminActivity('BULK_DELETE_SIZE', "Deleted size: {$sizeName}");
            } else {
                $errors[] = "Size ID {$sizeId}: Failed to delete";
            }
        }
        
        $pdo->commit();
        
        return [
            'success' => true,
            'deleted_count' => $deletedCount,
            'errors' => $errors
        ];
        
    } catch (Exception $e) {
        $pdo->rollback();
        return [
            'success' => false,
            'message' => 'Database error: ' . $e->getMessage()
        ];
    }
}
?>