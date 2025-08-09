// Xóa sản phẩm với AJAX
function deleteProduct(productId) {
    if (confirm('Bạn có chắc chắn muốn xóa sản phẩm này?')) {
        $.ajax({
            url: 'ajax_products.php',
            type: 'POST',
            data: {
                action: 'delete',
                product_id: productId
            },
            dataType: 'json',
            success: function(response) {
                if (response.success) {
                    // Xóa dòng khỏi table
                    $('#product-' + productId).remove();
                    showAlert('success', response.message);
                } else {
                    showAlert('error', response.message);
                }
            },
            error: function() {
                showAlert('error', 'Có lỗi xảy ra khi xóa sản phẩm');
            }
        });
    }
}

// Hiển thị thông báo
function showAlert(type, message) {
    // Có thể dùng Bootstrap alert hoặc SweetAlert
    alert(message);
}