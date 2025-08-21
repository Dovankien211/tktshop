<?php
require_once '../../config/database.php';
require_once '../../config/config.php';

requireLogin();

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['bulk_action'])) {
    $action = $_POST['bulk_action'];
    $variant_ids = $_POST['variant_ids'] ?? [];
    
    if (!empty($variant_ids)) {
        switch ($action) {
            case 'activate':
                $pdo->prepare("UPDATE product_variants SET status = 'active' WHERE id IN (" . implode(',', array_fill(0, count($variant_ids), '?')) . ")")
                    ->execute($variant_ids);
                alert('Kích hoạt biến thể thành công!', 'success');
                break;
                
            case 'deactivate':
                $pdo->prepare("UPDATE product_variants SET status = 'inactive' WHERE id IN (" . implode(',', array_fill(0, count($variant_ids), '?')) . ")")
                    ->execute($variant_ids);
                alert('Tạm ngưng biến thể thành công!', 'success');
                break;
                
            case 'delete':
                $pdo->prepare("DELETE FROM product_variants WHERE id IN (" . implode(',', array_fill(0, count($variant_ids), '?')) . ")")
                    ->execute($variant_ids);
                alert('Xóa biến thể thành công!', 'success');
                break;
                
            case 'update_stock':
                $new_stock = (int)$_POST['new_stock'];
                $pdo->prepare("UPDATE product_variants SET stock_quantity = ? WHERE id IN (" . implode(',', array_fill(0, count($variant_ids), '?')) . ")")
                    ->execute(array_merge([$new_stock], $variant_ids));
                alert('Cập nhật tồn kho thành công!', 'success');
                break;
        }
    }
}
?>

<!-- HTML form for bulk operations -->
<div class="card">
    <div class="card-header">
        <h5><i class="fas fa-tasks me-2"></i>Thao tác hàng loạt biến thể</h5>
    </div>
    <div class="card-body">
        <form method="POST" id="bulkForm">
            <div class="row">
                <div class="col-md-3">
                    <select name="bulk_action" class="form-select" required>
                        <option value="">Chọn thao tác</option>
                        <option value="activate">Kích hoạt</option>
                        <option value="deactivate">Tạm ngưng</option>
                        <option value="update_stock">Cập nhật tồn kho</option>
                        <option value="delete">Xóa</option>
                    </select>
                </div>
                <div class="col-md-3" id="stockInput" style="display:none;">
                    <input type="number" name="new_stock" class="form-control" placeholder="Số lượng tồn kho mới">
                </div>
                <div class="col-md-3">
                    <button type="submit" class="btn btn-primary" disabled id="bulkSubmit">
                        <i class="fas fa-check"></i> Thực hiện
                    </button>
                </div>
            </div>
        </form>
    </div>
</div>

<script>
// Show stock input when update_stock is selected
document.querySelector('select[name="bulk_action"]').addEventListener('change', function() {
    const stockInput = document.getElementById('stockInput');
    stockInput.style.display = this.value === 'update_stock' ? 'block' : 'none';
});

// Enable submit button when items selected
function updateBulkButton() {
    const checked = document.querySelectorAll('input[name="variant_ids[]"]:checked').length;
    document.getElementById('bulkSubmit').disabled = checked === 0;
}
</script>
