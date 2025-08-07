<?php
// customer/includes/footer.php
/**
 * Footer chung cho website khách hàng
 */
?>

<!-- Footer -->
<footer class="bg-dark text-white py-5 mt-5">
    <div class="container">
        <div class="row">
            <!-- Thông tin công ty -->
            <div class="col-lg-4 col-md-6 mb-4">
                <h5 class="fw-bold mb-3">
                    <i class="fas fa-store me-2"></i>
                    <?= SITE_NAME ?>
                </h5>
                <p class="text-light">
                    Chuyên cung cấp giày thể thao chính hãng từ các thương hiệu nổi tiếng thế giới. 
                    Chất lượng đảm bảo, giá cả hợp lý, dịch vụ tận tâm.
                </p>
                <div class="d-flex gap-3">
                    <a href="#" class="text-white fs-4" title="Facebook">
                        <i class="fab fa-facebook"></i>
                    </a>
                    <a href="#" class="text-white fs-4" title="Instagram">
                        <i class="fab fa-instagram"></i>
                    </a>
                    <a href="#" class="text-white fs-4" title="YouTube">
                        <i class="fab fa-youtube"></i>
                    </a>
                    <a href="#" class="text-white fs-4" title="TikTok">
                        <i class="fab fa-tiktok"></i>
                    </a>
                </div>
            </div>
            
            <!-- Danh mục sản phẩm -->
            <div class="col-lg-2 col-md-3 col-6 mb-4">
                <h6 class="fw-bold mb-3">Sản phẩm</h6>
                <ul class="list-unstyled">
                    <?php 
                    $footer_categories = $pdo->query("
                        SELECT id, ten_danh_muc 
                        FROM danh_muc_giay 
                        WHERE trang_thai = 'hoat_dong' AND danh_muc_cha_id IS NULL
                        ORDER BY thu_tu_hien_thi ASC 
                        LIMIT 6
                    ")->fetchAll();
                    
                    foreach ($footer_categories as $cat): 
                    ?>
                        <li class="mb-2">
                            <a href="/customer/products.php?category=<?= $cat['id'] ?>" 
                               class="text-light text-decoration-none hover-primary">
                                <?= htmlspecialchars($cat['ten_danh_muc']) ?>
                            </a>
                        </li>
                    <?php endforeach; ?>
                </ul>
            </div>
            
            <!-- Thương hiệu -->
            <div class="col-lg-2 col-md-3 col-6 mb-4">
                <h6 class="fw-bold mb-3">Thương hiệu</h6>
                <ul class="list-unstyled">
                    <li class="mb-2">
                        <a href="/customer/products.php?brand=Nike" class="text-light text-decoration-none hover-primary">Nike</a>
                    </li>
                    <li class="mb-2">
                        <a href="/customer/products.php?brand=Adidas" class="text-light text-decoration-none hover-primary">Adidas</a>
                    </li>
                    <li class="mb-2">
                        <a href="/customer/products.php?brand=Converse" class="text-light text-decoration-none hover-primary">Converse</a>
                    </li>
                    <li class="mb-2">
                        <a href="/customer/products.php?brand=Vans" class="text-light text-decoration-none hover-primary">Vans</a>
                    </li>
                    <li class="mb-2">
                        <a href="/customer/products.php?brand=Puma" class="text-light text-decoration-none hover-primary">Puma</a>
                    </li>
                </ul>
            </div>
            
            <!-- Hỗ trợ khách hàng -->
            <div class="col-lg-2 col-md-6 mb-4">
                <h6 class="fw-bold mb-3">Hỗ trợ</h6>
                <ul class="list-unstyled">
                    <li class="mb-2">
                        <a href="/customer/guide.php" class="text-light text-decoration-none hover-primary">Hướng dẫn mua hàng</a>
                    </li>
                    <li class="mb-2">
                        <a href="/customer/size-guide.php" class="text-light text-decoration-none hover-primary">Hướng dẫn chọn size</a>
                    </li>
                    <li class="mb-2">
                        <a href="/customer/policy.php" class="text-light text-decoration-none hover-primary">Chính sách đổi trả</a>
                    </li>
                    <li class="mb-2">
                        <a href="/customer/warranty.php" class="text-light text-decoration-none hover-primary">Chính sách bảo hành</a>
                    </li>
                    <li class="mb-2">
                        <a href="/customer/contact.php" class="text-light text-decoration-none hover-primary">Liên hệ hỗ trợ</a>
                    </li>
                </ul>
            </div>
            
            <!-- Thông tin liên hệ -->
            <div class="col-lg-2 col-md-6 mb-4">
                <h6 class="fw-bold mb-3">Liên hệ</h6>
                <div class="text-light">
                    <p class="mb-2">
                        <i class="fas fa-map-marker-alt me-2"></i>
                        số nhà 17 ngõ 89 , xã hoài đức, hà nội<br>
                        <span class="ms-4">TP. Hà Nội</span>
                    </p>
                    <p class="mb-2">
                        <i class="fas fa-phone me-2"></i>
                        <a href="tel:0866792996" class="text-light text-decoration-none">
                            (0866) 792996
                        </a>
                    </p>
                    <p class="mb-2">
                        <i class="fas fa-envelope me-2"></i>
                        <a href="mailto:Dovankien072211@gmail.com" class="text-light text-decoration-none">
                            Dovankien072211@gmail.com
                        </a>
                    </p>
                    <p class="mb-0">
                        <i class="fas fa-clock me-2"></i>
                        8:00 - 22:00 (Hàng ngày)
                    </p>
                </div>
                
                <!-- Phương thức thanh toán -->
                <div class="mt-3">
                    <h6 class="fw-bold mb-2">Thanh toán</h6>
                    <div class="d-flex gap-2 flex-wrap">
                        <div class="bg-white p-2 rounded d-flex align-items-center" style="min-width: 60px; height: 35px;">
                            <img src="/assets/images/vnpay-logo.png" alt="VNPay" style="max-height: 20px; width: auto;">
                        </div>
                        <div class="bg-white p-2 rounded d-flex align-items-center justify-content-center" style="min-width: 60px; height: 35px;">
                            <span class="fw-bold text-dark" style="font-size: 0.8rem;">COD</span>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        
        <!-- Đường phân cách -->
        <hr class="my-4 border-light">
        
        <!-- Footer bottom -->
        <div class="row align-items-center">
            <div class="col-md-6">
                <p class="text-light mb-0">
                    &copy; <?= date('Y') ?> <?= SITE_NAME ?>. Tất cả quyền được bảo lưu.
                </p>
            </div>
            <div class="col-md-6 text-md-end">
                <div class="d-flex justify-content-md-end gap-3 mt-2 mt-md-0">
                    <a href="/customer/terms.php" class="text-light text-decoration-none hover-primary">
                        Điều khoản sử dụng
                    </a>
                    <a href="/customer/privacy.php" class="text-light text-decoration-none hover-primary">
                        Chính sách bảo mật
                    </a>
                    <a href="/customer/sitemap.php" class="text-light text-decoration-none hover-primary">
                        Sơ đồ trang web
                    </a>
                </div>
            </div>
        </div>
    </div>
</footer>

<!-- Newsletter Modal -->
<div class="modal fade" id="newsletterModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header border-0">
                <h5 class="modal-title">
                    <i class="fas fa-envelope-open-text text-primary me-2"></i>
                    Đăng ký nhận tin
                </h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <p class="text-muted">Đăng ký để nhận thông tin về sản phẩm mới và ưu đãi đặc biệt!</p>
                <form id="newsletterForm">
                    <div class="input-group">
                        <input type="email" class="form-control" placeholder="Nhập email của bạn..." required>
                        <button type="submit" class="btn btn-primary">
                            <i class="fas fa-paper-plane"></i> Đăng ký
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>

<!-- Back to top button -->
<button id="backToTop" class="btn btn-primary position-fixed" 
        style="bottom: 30px; right: 30px; z-index: 1000; display: none; width: 50px; height: 50px; border-radius: 50%;">
    <i class="fas fa-chevron-up"></i>
</button>

<style>
.hover-primary:hover {
    color: #0d6efd !important;
    transition: color 0.3s ease;
}

.footer-social a:hover {
    transform: translateY(-2px);
    transition: transform 0.3s ease;
}

#backToTop {
    opacity: 0.8;
    transition: all 0.3s ease;
}

#backToTop:hover {
    opacity: 1;
    transform: translateY(-2px);
}
</style>

<script>
// Back to top functionality
document.addEventListener('DOMContentLoaded', function() {
    const backToTopBtn = document.getElementById('backToTop');
    
    // Show/hide back to top button
    window.addEventListener('scroll', function() {
        if (window.pageYOffset > 300) {
            backToTopBtn.style.display = 'block';
        } else {
            backToTopBtn.style.display = 'none';
        }
    });
    
    // Smooth scroll to top
    backToTopBtn.addEventListener('click', function(e) {
        e.preventDefault();
        window.scrollTo({
            top: 0,
            behavior: 'smooth'
        });
    });
    
    // Newsletter form
    document.getElementById('newsletterForm').addEventListener('submit', function(e) {
        e.preventDefault();
        const email = this.querySelector('input[type="email"]').value;
        
        // TODO: Implement newsletter subscription
        alert('Cảm ơn bạn đã đăng ký! Chúng tôi sẽ gửi thông tin mới nhất đến email của bạn.');
        
        // Close modal
        const modal = bootstrap.Modal.getInstance(document.getElementById('newsletterModal'));
        modal.hide();
        
        // Clear form
        this.reset();
    });
    
    // Show newsletter modal after 30 seconds (only once per session)
    if (!sessionStorage.getItem('newsletterShown')) {
        setTimeout(function() {
            const modal = new bootstrap.Modal(document.getElementById('newsletterModal'));
            modal.show();
            sessionStorage.setItem('newsletterShown', 'true');
        }, 30000);
    }
});

// External links tracking
document.querySelectorAll('a[href^="http"]').forEach(link => {
    link.addEventListener('click', function() {
        console.log('External link clicked:', this.href);
        // TODO: Add analytics tracking
    });
});
</script>