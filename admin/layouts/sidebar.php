<?php
// admin/layouts/sidebar.php - FIXED LINKS
/**
 * Menu sidebar cho admin - Fixed để hoạt động với subdirectory
 */

// Lấy base URL từ config
$base_url = '/tktshop'; // Hoặc dùng BASE_URL nếu đã define
?>

<div class="col-md-2 bg-dark text-white p-0">
    <div class="d-flex flex-column min-vh-100">
        <!-- Logo -->
        <div class="p-3 border-bottom border-secondary">
            <h4 class="mb-0">
                <i class="fas fa-store"></i> TKT Shop Admin
            </h4>
        </div>
        
        <!-- Menu -->
        <nav class="flex-grow-1">
            <ul class="nav flex-column p-0">
                <!-- Dashboard -->
                <li class="nav-item">
                    <a href="<?= $base_url ?>/admin/dashboard.php" class="nav-link text-white">
                        <i class="fas fa-tachometer-alt me-2"></i> Dashboard
                    </a>
                </li>
                
                <li class="nav-item">
                    <hr class="dropdown-divider border-secondary">
                </li>
                
                <!-- Quản lý người dùng -->
                <li class="nav-item">
                    <a href="<?= $base_url ?>/admin/users/" class="nav-link text-white">
                        <i class="fas fa-users me-2"></i> Quản lý người dùng
                    </a>
                </li>
                
                <!-- Quản lý danh mục -->
                <li class="nav-item">
                    <a href="<?= $base_url ?>/admin/categories/" class="nav-link text-white">
                        <i class="fas fa-tags me-2"></i> Quản lý danh mục
                    </a>
                </li>
                
                <!-- Quản lý kích cỡ -->
                <li class="nav-item">
                    <a href="<?= $base_url ?>/admin/sizes/" class="nav-link text-white">
                        <i class="fas fa-ruler me-2"></i> Quản lý kích cỡ
                    </a>
                </li>
                
                <!-- Quản lý màu sắc -->
                <li class="nav-item">
                    <a href="<?= $base_url ?>/admin/colors/" class="nav-link text-white">
                        <i class="fas fa-palette me-2"></i> Quản lý màu sắc
                    </a>
                </li>
                
                <li class="nav-item">
                    <hr class="dropdown-divider border-secondary">
                </li>
                
                <!-- Quản lý sản phẩm -->
                <li class="nav-item">
                    <a href="<?= $base_url ?>/admin/products/" class="nav-link text-white">
                        <i class="fas fa-box me-2"></i> Quản lý sản phẩm
                    </a>
                </li>
                
                <!-- Quản lý biến thể -->
                <li class="nav-item">
                    <a href="<?= $base_url ?>/admin/products/variants.php" class="nav-link text-white">
                        <i class="fas fa-cubes me-2"></i> Biến thể sản phẩm
                    </a>
                </li>
                
                <li class="nav-item">
                    <hr class="dropdown-divider border-secondary">
                </li>
                
                <!-- Quản lý đơn hàng -->
                <li class="nav-item">
                    <a href="<?= $base_url ?>/admin/orders/" class="nav-link text-white">
                        <i class="fas fa-shopping-cart me-2"></i> Quản lý đơn hàng
                    </a>
                </li>
                
                <!-- Quản lý đánh giá -->
                <li class="nav-item">
                    <a href="<?= $base_url ?>/admin/reviews/" class="nav-link text-white">
                        <i class="fas fa-star me-2"></i> Quản lý đánh giá
                    </a>
                </li>
                
                <li class="nav-item">
                    <hr class="dropdown-divider border-secondary">
                </li>
                
                <!-- VNPay -->
                <li class="nav-item">
                    <a href="<?= $base_url ?>/vnpay/" class="nav-link text-white">
                        <i class="fas fa-credit-card me-2"></i> VNPay
                    </a>
                </li>
            </ul>
        </nav>
        
        <!-- User info & logout -->
        <div class="p-3 border-top border-secondary">
            <div class="d-flex align-items-center">
                <i class="fas fa-user-circle me-2"></i>
                <span><?= $_SESSION['admin_name'] ?? 'Admin' ?></span>
            </div>
            <a href="<?= $base_url ?>/admin/logout.php" class="btn btn-outline-light btn-sm mt-2 w-100">
                <i class="fas fa-sign-out-alt me-1"></i> Đăng xuất
            </a>
        </div>
    </div>
</div>