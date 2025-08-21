<?php
// admin/layouts/header.php
/**
 * Header layout cho admin panel
 */

// Kiểm tra đăng nhập
if (!isset($_SESSION['admin_id'])) {
    header('Location: /tktshop/admin/login.php');
    exit;
}

$admin_name = $_SESSION['admin_name'] ?? 'Administrator';
$admin_role = $_SESSION['admin_role'] ?? 'admin';
$admin_avatar = $_SESSION['admin_avatar'] ?? '';

// Đếm thông báo
$notification_count = 0;
$pending_orders = 0;

try {
    // Đếm đơn hàng chờ xác nhận
    $stmt = $pdo->query("SELECT COUNT(*) FROM don_hang WHERE trang_thai_don_hang = 'cho_xac_nhan'");
    $pending_orders = $stmt->fetchColumn();
    
    // Tổng thông báo
    $notification_count = $pending_orders;
} catch (Exception $e) {
    // Bỏ qua lỗi nếu bảng chưa tồn tại
}
?>

<style>
.admin-header {
    background: linear-gradient(90deg, #fff 0%, #f8f9fa 100%);
    border-bottom: 1px solid #dee2e6;
    box-shadow: 0 2px 4px rgba(0,0,0,0.05);
    position: sticky;
    top: 0;
    z-index: 1020;
}

.header-brand {
    font-weight: 700;
    color: #2c3e50;
    text-decoration: none;
    font-size: 1.1rem;
}

.header-brand:hover {
    color: #3498db;
}

.notification-badge {
    position: absolute;
    top: -8px;
    right: -8px;
    background: #e74c3c;
    color: white;
    border-radius: 50%;
    padding: 2px 6px;
    font-size: 0.7rem;
    font-weight: bold;
    min-width: 18px;
    text-align: center;
}

.admin-dropdown .dropdown-toggle::after {
    margin-left: 8px;
}

.admin-avatar {
    width: 32px;
    height: 32px;
    border-radius: 50%;
    background: linear-gradient(135deg, #3498db, #2980b9);
    display: flex;
    align-items: center;
    justify-content: center;
    color: white;
    font-weight: bold;
    font-size: 0.9rem;
    margin-right: 8px;
}

.breadcrumb-nav {
    background: transparent;
    padding: 0;
    margin: 0;
}

.breadcrumb-nav .breadcrumb-item + .breadcrumb-item::before {
    content: "›";
    color: #6c757d;
}

.header-actions .btn {
    margin-left: 8px;
}

.quick-stats {
    font-size: 0.85rem;
    color: #6c757d;
}

.search-form {
    max-width: 300px;
}

.search-form .form-control {
    border-radius: 20px;
    border: 1px solid #dee2e6;
    background: #f8f9fa;
}

.search-form .form-control:focus {
    box-shadow: 0 0 0 0.2rem rgba(52, 144, 220, 0.25);
    border-color: #3490dc;
    background: white;
    .custom-sidebar-toggle {
    position: fixed;
    top: 20px;
    left: 290px; /* Sidebar width + 10px */
    width: 30px;
    height: 30px;
    background: linear-gradient(45deg, #f39c12, #e67e22);
    border: none;
    border-radius: 50%;
    color: white;
    font-size: 16px;
    font-weight: bold;
    cursor: pointer;
    z-index: 2000;
    box-shadow: 0 3px 8px rgba(243, 156, 18, 0.4);
    transition: all 0.3s ease;
    display: flex;
    align-items: center;
    justify-content: center;
    user-select: none;
}

.custom-sidebar-toggle:hover {
    background: linear-gradient(45deg, #e67e22, #d35400);
    transform: scale(1.1);
    box-shadow: 0 4px 12px rgba(243, 156, 18, 0.6);
}

.custom-sidebar-toggle:active {
    transform: scale(0.95);
}

/* Khi sidebar ẩn */
.sidebar-hidden .custom-sidebar-toggle {
    left: 20px; /* Move button to left edge when sidebar hidden */
}

.sidebar-hidden .admin-sidebar {
    transform: translateX(-100%);
    transition: transform 0.3s ease;
}

.sidebar-hidden .main-content {
    margin-left: 0;
    transition: margin-left 0.3s ease;
}

/* Animation cho main content */
.main-content {
    transition: margin-left 0.3s ease;
}
}
</style>

<header class="admin-header">
    <div class="container-fluid px-3">
        <div class="row align-items-center py-2">
            <!-- Left side - Brand & Navigation -->
            <div class="col-md-6">
                <div class="d-flex align-items-center">
                    <!-- Mobile toggle (if needed) -->
                    <button class="btn btn-link d-md-none me-2" type="button" data-bs-toggle="offcanvas" data-bs-target="#sidebar">
                        <i class="fas fa-bars"></i>
                    </button>
                    
                    <!-- Brand -->
                    <a href="/tktshop/admin/dashboard.php" class="header-brand me-4">
                        <i class="fas fa-store me-2"></i>TKT Admin
                    </a>
                    
                    <!-- Breadcrumb (sẽ được cập nhật bởi JavaScript hoặc PHP) -->
                    <nav aria-label="breadcrumb" class="breadcrumb-nav d-none d-lg-block">
                        <ol class="breadcrumb mb-0">
                            <li class="breadcrumb-item">
                                <a href="/tktshop/admin/dashboard.php" class="text-decoration-none">
                                    <i class="fas fa-home"></i>
                                </a>
                            </li>
                            <li class="breadcrumb-item active" id="current-page">Dashboard</li>
                        </ol>
                    </nav>
                </div>
            </div>
            
            <!-- Right side - Search, Notifications, User -->
            <div class="col-md-6">
                <div class="d-flex align-items-center justify-content-end">
                    <!-- Search Form -->
                    <form class="search-form me-3 d-none d-sm-block" action="/tktshop/admin/products/index.php" method="GET">
                        <div class="input-group input-group-sm">
                            <input type="text" 
                                   class="form-control" 
                                   placeholder="Tìm sản phẩm..." 
                                   name="search"
                                   value="<?= htmlspecialchars($_GET['search'] ?? '') ?>">
                            <button class="btn btn-outline-secondary" type="submit">
                                <i class="fas fa-search"></i>
                            </button>
                        </div>
                    </form>
                    
                    <!-- Quick Stats -->
                    <div class="quick-stats me-3 d-none d-lg-block">
                        <small>
                            <i class="fas fa-clock text-warning"></i>
                            <span class="text-warning"><?= $pending_orders ?></span> chờ xử lý
                        </small>
                    </div>
                    
                    <!-- Notifications -->
                    <div class="dropdown me-2">
                        <button class="btn btn-link position-relative" 
                                type="button" 
                                data-bs-toggle="dropdown" 
                                aria-expanded="false">
                            <i class="fas fa-bell text-muted"></i>
                            <?php if ($notification_count > 0): ?>
                                <span class="notification-badge"><?= $notification_count ?></span>
                            <?php endif; ?>
                        </button>
                        <ul class="dropdown-menu dropdown-menu-end">
                            <li><h6 class="dropdown-header">Thông báo mới</h6></li>
                            
                            <?php if ($pending_orders > 0): ?>
                                <li>
                                    <a class="dropdown-item" href="/tktshop/admin/orders/index.php?status=cho_xac_nhan">
                                        <div class="d-flex">
                                            <div class="flex-shrink-0">
                                                <i class="fas fa-shopping-cart text-warning"></i>
                                            </div>
                                            <div class="flex-grow-1 ms-2">
                                                <div class="fw-bold">Đơn hàng mới</div>
                                                <small class="text-muted"><?= $pending_orders ?> đơn chờ xác nhận</small>
                                            </div>
                                        </div>
                                    </a>
                                </li>
                                <li><hr class="dropdown-divider"></li>
                            <?php endif; ?>
                            
                            <?php if ($notification_count == 0): ?>
                                <li><span class="dropdown-item-text text-muted">Không có thông báo mới</span></li>
                            <?php else: ?>
                                <li><a class="dropdown-item text-center" href="/tktshop/admin/orders/index.php">Xem tất cả</a></li>
                            <?php endif; ?>
                        </ul>
                    </div>
                    
                    <!-- User Dropdown -->
                    <div class="dropdown admin-dropdown">
                        <button class="btn btn-link d-flex align-items-center text-decoration-none" 
                                type="button" 
                                data-bs-toggle="dropdown" 
                                aria-expanded="false">
                            <div class="admin-avatar">
                                <?= strtoupper(substr($admin_name, 0, 1)) ?>
                            </div>
                            <div class="d-none d-md-block">
                                <div class="fw-bold text-dark"><?= htmlspecialchars($admin_name) ?></div>
                                <small class="text-muted"><?= $admin_role == 'admin' ? 'Quản trị viên' : 'Nhân viên' ?></small>
                            </div>
                            <i class="fas fa-chevron-down ms-2 text-muted"></i>
                        </button>
                        
                        <ul class="dropdown-menu dropdown-menu-end">
                            <li>
                                <div class="dropdown-item-text">
                                    <div class="d-flex align-items-center">
                                        <div class="admin-avatar me-2">
                                            <?= strtoupper(substr($admin_name, 0, 1)) ?>
                                        </div>
                                        <div>
                                            <div class="fw-bold"><?= htmlspecialchars($admin_name) ?></div>
                                            <small class="text-muted"><?= htmlspecialchars($_SESSION['admin_email'] ?? '') ?></small>
                                        </div>
                                    </div>
                                </div>
                            </li>
                            <li><hr class="dropdown-divider"></li>
                            
                            <li>
                                <a class="dropdown-item" href="/tktshop/admin/profile.php">
                                    <i class="fas fa-user me-2"></i>Thông tin cá nhân
                                </a>
                            </li>
                            <li>
                                <a class="dropdown-item" href="/tktshop/admin/settings.php">
                                    <i class="fas fa-cog me-2"></i>Cài đặt
                                </a>
                            </li>
                            <li>
                                <a class="dropdown-item" href="/tktshop/" target="_blank">
                                    <i class="fas fa-external-link-alt me-2"></i>Xem website
                                </a>
                            </li>
                            <li><hr class="dropdown-divider"></li>
                            
                            <li>
                                <form method="POST" action="/tktshop/admin/logout.php" class="d-inline">
                                    <button type="submit" class="dropdown-item text-danger" onclick="return confirm('Bạn có chắc muốn đăng xuất?')">
                                        <i class="fas fa-sign-out-alt me-2"></i>Đăng xuất
                                    </button>
                                </form>
                            </li>
                        </ul>
                    </div>
                </div>
            </div>
        </div>
    </div>
</header>

<script>
document.addEventListener('DOMContentLoaded', function() {
    // Update breadcrumb based on current page
    updateBreadcrumb();
    
    // Auto-refresh notifications every 30 seconds
    setInterval(function() {
        // You can implement AJAX refresh here
    }, 30000);
});

function updateBreadcrumb() {
    const path = window.location.pathname;
    const currentPageElement = document.getElementById('current-page');
    
    if (!currentPageElement) return;
    
    let pageName = 'Dashboard';
    
    if (path.includes('/products/')) {
        if (path.includes('create.php') || path.includes('add.php')) {
            pageName = 'Thêm sản phẩm';
        } else if (path.includes('variants.php')) {
            pageName = 'Biến thể sản phẩm';
        } else if (path.includes('edit.php')) {
            pageName = 'Sửa sản phẩm';
        } else {
            pageName = 'Quản lý sản phẩm';
        }
    } else if (path.includes('/orders/')) {
        pageName = 'Quản lý đơn hàng';
    } else if (path.includes('/users/')) {
        pageName = 'Quản lý người dùng';
    } else if (path.includes('/categories/')) {
        pageName = 'Quản lý danh mục';
    } else if (path.includes('/reviews/')) {
        pageName = 'Quản lý đánh giá';
    }
    
    currentPageElement.textContent = pageName;
}

// Real-time search
const searchInput = document.querySelector('.search-form input');
if (searchInput) {
    let searchTimeout;
    searchInput.addEventListener('input', function() {
        clearTimeout(searchTimeout);
        searchTimeout = setTimeout(() => {
            // Implement live search here
            console.log('Searching for:', this.value);
        }, 500);
    });
}
</script>