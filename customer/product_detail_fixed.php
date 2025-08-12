// Product configuration
const productTable = '<?= $product_table ?>';
const productId = <?= $product['id'] ?>;

<?php if ($product_table == 'san_pham_chinh' && !empty($variant_matrix)): ?>
// Variant matrix for san_pham_chinh
const variantMatrix = <?= json_encode($variant_matrix) ?>;
const colors = <?= json_encode(array_values($colors)) ?>;

let selectedSize = null;
let selectedColorId = null;
let currentVariant = null;

// Size selection
document.querySelectorAll('.size-option').forEach(option => {
    option.addEventListener('click', function() {
        if (this.classList.contains('disabled')) return;
        
        const size = this.dataset.size;
        selectSize(size, this);
    });
});

function selectSize(size, element) {
    selectedSize = size;
    document.getElementById('selectedSize').value = size;
    
    // Update UI
    document.querySelectorAll('.size-option').forEach(opt => opt.classList.remove('selected'));
    element.classList.add('selected');
    
    updateAvailableColors();
    updateVariantInfo();
}

// Color selection
document.querySelectorAll('.color-option').forEach(option => {
    option.addEventListener('click', function() {
        if (this.classList.contains('disabled')) return;
        
        const colorId = parseInt(this.dataset.colorId);
        selectColor(colorId, this);
    });
});

function selectColor(colorId, element) {
    selectedColorId = colorId;
    document.getElementById('selectedColorId').value = colorId;
    
    // Update UI
    document.querySelectorAll('.color-option').forEach(opt => opt.classList.remove('selected'));
    element.classList.add('selected');
    
    // Show color name
    const color = colors.find(c => c.id == colorId);
    document.getElementById('selectedColorName').textContent = color ? color.ten_mau : '';
    
    updateVariantInfo();
}

function updateAvailableColors() {
    if (!selectedSize) {
        // Reset all colors to enabled
        document.querySelectorAll('.color-option').forEach(opt => {
            opt.classList.remove('disabled');
        });
        return;
    }
    
    const availableColors = variantMatrix[selectedSize] || {};
    
    document.querySelectorAll('.color-option').forEach(option => {
        const colorId = parseInt(option.dataset.colorId);
        
        if (availableColors[colorId]) {
            option.classList.remove('disabled');
        } else {
            option.classList.add('disabled');
            if (option.classList.contains('selected')) {
                option.classList.remove('selected');
                selectedColorId = null;
                document.getElementById('selectedColorId').value = '';
                document.getElementById('selectedColorName').textContent = '';
            }
        }
    });
}

function updateVariantInfo() {
    const stockInfo = document.getElementById('stockInfo');
    const addToCartBtn = document.getElementById('addToCartBtn');
    const quantityInput = document.getElementById('quantity');
    
    if (!selectedSize || !selectedColorId) {
        stockInfo.textContent = '';
        addToCartBtn.disabled = true;
        addToCartBtn.innerHTML = '<i class="fas fa-shopping-cart me-2"></i>Vui lòng chọn size và màu';
        currentVariant = null;
        return;
    }
    
    const variant = variantMatrix[selectedSize] && variantMatrix[selectedSize][selectedColorId];
    currentVariant = variant;
    
    if (variant) {
        const stock = variant.so_luong_ton_kho;
        
        if (stock > 0) {
            stockInfo.innerHTML = `<span class="text-success">Còn ${stock} sản phẩm</span>`;
            addToCartBtn.disabled = false;
            addToCartBtn.innerHTML = '<i class="fas fa-shopping-cart me-2"></i>Thêm vào giỏ hàng';
            quantityInput.max = stock;
            
            if (stock <= 5) {
                stockInfo.innerHTML = `<span class="text-warning stock-warning">Chỉ còn ${stock} sản phẩm!</span>`;
            }
        } else {
            stockInfo.innerHTML = '<span class="text-danger">Hết hàng</span>';
            addToCartBtn.disabled = true;
            addToCartBtn.innerHTML = '<i class="fas fa-times me-2"></i>Hết hàng';
        }
    } else {
        stockInfo.innerHTML = '<span class="text-danger">Không có sẵn</span>';
        addToCartBtn.disabled = true;
        addToCartBtn.innerHTML = '<i class="fas fa-times me-2"></i>Không có sẵn';
    }
}
<?php endif; ?>

// Image gallery functions
function changeMainImage(imageSrc, thumbnailElement) {
    const mainImage = document.getElementById('mainImage');
    if (mainImage) {
        mainImage.src = '<?= "/tktshop/uploads/products/" ?>' + imageSrc;
        
        // Update thumbnail active state
        document.querySelectorAll('.thumbnail').forEach(thumb => thumb.classList.remove('active'));
        thumbnailElement.classList.add('active');
    }
}

// Quantity functions
function changeQuantity(delta) {
    const quantityInput = document.getElementById('quantity');
    const currentValue = parseInt(quantityInput.value) || 1;
    const newValue = Math.max(1, Math.min(parseInt(quantityInput.max) || 99, currentValue + delta));
    quantityInput.value = newValue;
}

// Add to cart function
async function addToCartAjax() {
    const form = document.getElementById('addToCartForm');
    const addToCartBtn = document.getElementById('addToCartBtn');
    const loadingOverlay = document.getElementById('loadingOverlay');
    
    // Validation for san_pham_chinh
    if (productTable === 'san_pham_chinh') {
        if (!selectedSize || !selectedColorId) {
            showToast('Vui lòng chọn size và màu sắc!', 'warning');
            return;
        }
        
        if (!currentVariant || currentVariant.so_luong_ton_kho <= 0) {
            showToast('Sản phẩm hiện tại không có sẵn!', 'error');
            return;
        }
    }
    
    // Show loading
    addToCartBtn.disabled = true;
    addToCartBtn.innerHTML = '<i class="fas fa-spinner fa-spin me-2"></i>Đang thêm...';
    loadingOverlay.style.display = 'flex';
    
    try {
        const formData = new FormData(form);
        
        const response = await fetch(window.location.href, {
            method: 'POST',
            body: formData,
            headers: {
                'X-Requested-With': 'XMLHttpRequest'
            }
        });
        
        const result = await response.json();
        
        if (result.success) {
            showToast(result.message, 'success');
            
            // Update cart count in header if exists
            updateCartCount(result.cart_count);
            
            // Reset form state
            if (productTable === 'san_pham_chinh') {
                updateVariantInfo();
            }
        } else {
            showToast(result.message, 'error');
        }
        
    } catch (error) {
        console.error('Add to cart error:', error);
        showToast('Có lỗi xảy ra khi thêm sản phẩm vào giỏ hàng!', 'error');
    } finally {
        // Hide loading
        loadingOverlay.style.display = 'none';
        
        // Reset button
        if (productTable === 'san_pham_chinh') {
            if (!selectedSize || !selectedColorId) {
                addToCartBtn.disabled = true;
                addToCartBtn.innerHTML = '<i class="fas fa-shopping-cart me-2"></i>Vui lòng chọn size và màu';
            } else if (currentVariant && currentVariant.so_luong_ton_kho > 0) {
                addToCartBtn.disabled = false;
                addToCartBtn.innerHTML = '<i class="fas fa-shopping-cart me-2"></i>Thêm vào giỏ hàng';
            }
        } else {
            addToCartBtn.disabled = false;
            addToCartBtn.innerHTML = '<i class="fas fa-shopping-cart me-2"></i>Thêm vào giỏ hàng';
        }
    }
}

// Toast notification function
function showToast(message, type = 'info') {
    const toastContainer = document.getElementById('toastContainer');
    
    const toastId = 'toast_' + Date.now();
    const iconClass = {
        'success': 'fas fa-check-circle text-success',
        'error': 'fas fa-times-circle text-danger',
        'warning': 'fas fa-exclamation-triangle text-warning',
        'info': 'fas fa-info-circle text-info'
    }[type] || 'fas fa-info-circle text-info';
    
    const bgClass = {
        'success': 'bg-success',
        'error': 'bg-danger',
        'warning': 'bg-warning',
        'info': 'bg-info'
    }[type] || 'bg-info';
    
    const toastHTML = `
        <div class="toast" id="${toastId}" role="alert" data-bs-delay="4000">
            <div class="toast-header ${bgClass} text-white">
                <i class="${iconClass} me-2"></i>
                <strong class="me-auto">Thông báo</strong>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="toast"></button>
            </div>
            <div class="toast-body">
                ${message}
            </div>
        </div>
    `;
    
    toastContainer.insertAdjacentHTML('beforeend', toastHTML);
    
    const toastElement = document.getElementById(toastId);
    const toast = new bootstrap.Toast(toastElement);
    toast.show();
    
    // Remove toast element after it's hidden
    toastElement.addEventListener('hidden.bs.toast', function() {
        this.remove();
    });
}

// Update cart count in header
function updateCartCount(count) {
    const cartCountElements = document.querySelectorAll('.cart-count, #cartCount, .badge-cart');
    cartCountElements.forEach(element => {
        element.textContent = count;
        if (count > 0) {
            element.style.display = 'inline';
        } else {
            element.style.display = 'none';
        }
    });
}

// Add to wishlist function
async function addToWishlist(productId) {
    try {
        const response = await fetch('/tktshop/customer/ajax/wishlist.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-Requested-With': 'XMLHttpRequest'
            },
            body: JSON.stringify({
                action: 'add',
                product_id: productId
            })
        });
        
        const result = await response.json();
        
        if (result.success) {
            showToast(result.message, 'success');
        } else {
            showToast(result.message, 'error');
        }
    } catch (error) {
        console.error('Wishlist error:', error);
        showToast('Có lỗi xảy ra khi thêm vào danh sách yêu thích!', 'error');
    }
}

// Image zoom functionality
function initImageZoom() {
    const mainImage = document.getElementById('mainImage');
    if (!mainImage) return;
    
    mainImage.addEventListener('click', function() {
        const modal = document.createElement('div');
        modal.className = 'modal fade';
        modal.innerHTML = `
            <div class="modal-dialog modal-lg modal-dialog-centered">
                <div class="modal-content">
                    <div class="modal-header">
                        <h5 class="modal-title">Xem ảnh chi tiết</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                    </div>
                    <div class="modal-body text-center">
                        <img src="${this.src}" class="img-fluid" alt="Product Image">
                    </div>
                </div>
            </div>
        `;
        
        document.body.appendChild(modal);
        const modalInstance = new bootstrap.Modal(modal);
        modalInstance.show();
        
        modal.addEventListener('hidden.bs.modal', function() {
            modal.remove();
        });
    });
}

// Lazy loading for related products
function initLazyLoading() {
    const images = document.querySelectorAll('img[data-src]');
    
    if ('IntersectionObserver' in window) {
        const imageObserver = new IntersectionObserver((entries, observer) => {
            entries.forEach(entry => {
                if (entry.isIntersecting) {
                    const img = entry.target;
                    img.src = img.dataset.src;
                    img.classList.remove('lazy');
                    imageObserver.unobserve(img);
                }
            });
        });
        
        images.forEach(img => imageObserver.observe(img));
    } else {
        // Fallback for older browsers
        images.forEach(img => {
            img.src = img.dataset.src;
        });
    }
}

// Price formatting function
function formatPrice(price) {
    return new Intl.NumberFormat('vi-VN', {
        style: 'currency',
        currency: 'VND'
    }).format(price);
}

// Initialize when DOM is loaded
document.addEventListener('DOMContentLoaded', function() {
    // Initialize image zoom
    initImageZoom();
    
    // Initialize lazy loading
    initLazyLoading();
    
    // Auto-select first available variant for san_pham_chinh
    <?php if ($product_table == 'san_pham_chinh' && !empty($variants)): ?>
    if (productTable === 'san_pham_chinh') {
        // Auto-select first size if only one available
        const sizeOptions = document.querySelectorAll('.size-option');
        if (sizeOptions.length === 1) {
            selectSize(sizeOptions[0].dataset.size, sizeOptions[0]);
        }
        
        // Update initial state
        updateAvailableColors();
        updateVariantInfo();
    }
    <?php endif; ?>
    
    // Handle quantity input changes
    const quantityInput = document.getElementById('quantity');
    if (quantityInput) {
        quantityInput.addEventListener('change', function() {
            const value = parseInt(this.value);
            const min = parseInt(this.min) || 1;
            const max = parseInt(this.max) || 99;
            
            if (value < min) this.value = min;
            if (value > max) this.value = max;
        });
    }
    
    // Handle keyboard shortcuts
    document.addEventListener('keydown', function(e) {
        // Escape key to close modals
        if (e.key === 'Escape') {
            const openModals = document.querySelectorAll('.modal.show');
            openModals.forEach(modal => {
                const modalInstance = bootstrap.Modal.getInstance(modal);
                if (modalInstance) modalInstance.hide();
            });
        }
        
        // Ctrl/Cmd + Enter to add to cart
        if ((e.ctrlKey || e.metaKey) && e.key === 'Enter') {
            const addToCartBtn = document.getElementById('addToCartBtn');
            if (addToCartBtn && !addToCartBtn.disabled) {
                addToCartAjax();
            }
        }
    });
    
    // Handle scroll effects
    let lastScrollTop = 0;
    window.addEventListener('scroll', function() {
        const scrollTop = window.pageYOffset || document.documentElement.scrollTop;
        
        // Add/remove shadow to header based on scroll
        const header = document.querySelector('header, .navbar');
        if (header) {
            if (scrollTop > 50) {
                header.classList.add('shadow-sm');
            } else {
                header.classList.remove('shadow-sm');
            }
        }
        
        lastScrollTop = scrollTop;
    });
    
    // Analytics tracking
    trackProductView();
});

// Analytics functions
function trackProductView() {
    // Track product view for analytics
    if (typeof gtag !== 'undefined') {
        gtag('event', 'view_item', {
            'currency': 'VND',
            'value': <?= $product['gia_hien_tai'] ?>,
            'items': [{
                'item_id': '<?= $product['id'] ?>',
                'item_name': '<?= addslashes($product['ten_san_pham']) ?>',
                'item_category': '<?= addslashes($product['ten_danh_muc'] ?? '') ?>',
                'item_brand': '<?= addslashes($product['thuong_hieu'] ?? '') ?>',
                'price': <?= $product['gia_hien_tai'] ?>,
                'quantity': 1
            }]
        });
    }
}

function trackAddToCart() {
    // Track add to cart for analytics
    if (typeof gtag !== 'undefined') {
        const quantity = parseInt(document.getElementById('quantity').value) || 1;
        gtag('event', 'add_to_cart', {
            'currency': 'VND',
            'value': <?= $product['gia_hien_tai'] ?> * quantity,
            'items': [{
                'item_id': '<?= $product['id'] ?>',
                'item_name': '<?= addslashes($product['ten_san_pham']) ?>',
                'item_category': '<?= addslashes($product['ten_danh_muc'] ?? '') ?>',
                'item_brand': '<?= addslashes($product['thuong_hieu'] ?? '') ?>',
                'price': <?= $product['gia_hien_tai'] ?>,
                'quantity': quantity
            }]
        });
    }
}

// Error handling
window.addEventListener('error', function(e) {
    console.error('JavaScript error:', e.error);
    // You can send this to your logging service
});

// Service Worker registration for PWA (optional)
if ('serviceWorker' in navigator) {
    window.addEventListener('load', function() {
        navigator.serviceWorker.register('/tktshop/sw.js')
            .then(function(registration) {
                console.log('SW registered: ', registration);
            })
            .catch(function(registrationError) {
                console.log('SW registration failed: ', registrationError);
            });
    });
}