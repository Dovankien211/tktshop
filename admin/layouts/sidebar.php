<?php
// admin/layouts/sidebar.php - COMPLETE REWRITE
/**
 * Admin Sidebar Navigation - Hoàn toàn mới với dropdown menu
 */

// Lấy current page để highlight active menu
$current_page = $_SERVER['REQUEST_URI'];
$base_url = '/tktshop';

function isActiveMenu($path) {
    global $current_page;
    return strpos($current_page, $path) !== false ? 'active' : '';
}
?>

<style>
.admin-sidebar {
    background: linear-gradient(180deg, #2c3e50 0%, #34495e 100%);
    min-height: 100vh;
    box-shadow: 2px 0 10px rgba(0,0,0,0.1);
}

.sidebar-header {
    background: rgba(52, 73, 94, 0.8);
    padding: 20px 15px;
    border-bottom: 1px solid #465669;
    backdrop-filter: blur(10px);
}

.sidebar-brand {
    color: #ecf0f1;
    font-size: 1.3rem;
    font-weight: bold;
    text-decoration: none;
    display: flex;
    align-items: center;
    transition: color 0.3s ease;
}

.sidebar-brand:hover {
    color: #3498db;
}

.sidebar-menu {
    padding: 10px 0;
}

.menu-section {
    margin-bottom: 5px;
}

.menu-item {
    margin: 2px 8px;
    border-radius: 8px;
    overflow: hidden;
}

.menu-link {
    display: flex;
    align-items: center;
    justify-content: space-between;
    padding: 12px 16px;
    color: #bdc3c7;
    text-decoration: none;
    transition: all 0.3s ease;
    font-size: 0.95rem;
    border-radius: 8px;
}

.menu-link:hover {
    background: rgba(52, 73, 94, 0.7);
    color: #3498db;
    transform: translateX(5px);
}

.menu-link.active {
    background: linear-gradient(135deg, #3498db, #2980b9);
    color: white;
    box-shadow: 0 2px 8px rgba(52, 152, 219, 0.3);
}

.menu-icon {
    width: 20px;
    margin-right: 12px;
    text-align: center;
}

.menu-text {
    flex: 1;
}

.menu-arrow {
    font-size: 0.8rem;
    transition: transform 0.3s ease;
}

.menu-arrow.rotated {
    transform: rotate(180deg);
}

.submenu {
    max-height: 0;
    overflow: hidden;
    transition: max-height 0.3s ease;
    background: rgba(44, 62, 80, 0.5);
    margin: 5px 8px;
    border-radius: 8px;
}

.submenu.show {
    max-height: 300px;
    padding: 8px 0;
}

.submenu-link {
    display: flex;
    align-items: center;
    padding: 10px 20px 10px 45px;
    color: #95a5a6;
    text-decoration: none;
    transition: all 0.3s ease;
    font-size: 0.9rem;
    border-radius: 6px;
    margin: 2px 8px;
}

.submenu-link:hover {
    background: rgba(52, 152, 219, 0.2);
    color: #3498db;
    transform: translateX(3px);
}

.submenu-link.active {
    background: rgba(52, 152, 219, 0.3);
    color: #3498db;
    border-left: 3px solid #3498db;
}

.menu-divider {
    height: 1px;
    background: linear-gradient(90deg, transparent, #465669, transparent);
    margin: 15px 20px;
}

.user-section {
    background: rgba(44, 62, 80, 0.7);
    padding: 20px 15px;
    border-top: 1px solid #465669;
    margin-top: auto;
}

.user-info {
    display: flex;
    align-items: center;
    margin-bottom: 15px;
    padding: 10px;
    background: rgba(52, 73, 94, 0.5);
    border-radius: 8px;
}

.user-avatar {
    width: 40px;
    height: 40px;
    background: linear-gradient(135deg, #3498db, #2980b9);
    border-radius: 50%;
    display: flex;
    align-items: center;
    justify-content: center;
    color: white;
    font-weight: bold;
    margin-right: 12px;
    font-size: 1.1rem;
}

.user-details h6 {
    color: #ecf0f1;
    margin: 0;
    font-size: 0.95rem;
}

.user-details small {
    color: #95a5a6;
}

.logout-btn {
    background: linear-gradient(135deg, #e74c3c, #c0392b);
    color: white;
    border: none;
    padding: 12px 16px;
    border-radius: 8px;
    width: 100%;
    transition: all 0.3s ease;
    font-weight: 500;
}

.logout-btn:hover {
    background: linear-gradient(135deg, #c0392b, #a93226);
    transform: translateY(-2px);
    box-shadow: 0 4px 12px rgba(231, 76, 60, 0.3);
}

.badge-count {
    background: #e74c3c;
    color: white;
    font-size: 0.7rem;
    padding: 2px 6px;
    border-radius: 10px;
    margin-left: 8px;
}
</style>

<div class="admin-sidebar d-flex flex-column">
    <!-- Header -->
    <div class="sidebar-header">
        <a href="<?= $base_url ?>/admin/dashboard.php" class="sidebar-brand">
            <i class="fas fa-store me-2"></i>
            TKT Shop Admin
        </a>
    </div>
    
    <!-- Navigation Menu -->
    <div class="sidebar-menu flex-grow-1">
        <!-- Dashboard -->
        <div class="menu-section">
            <div class="menu-item">
                <a href="<?= $base_url ?>/admin/dashboard.php" 
                   class="menu-link <?= isActiveMenu('/admin/dashboard.php') ?>">
                    <div class="d-flex align-items-center">
                        <i class="fas fa-tachometer-alt menu-icon"></i>
                        <span class="menu-text">Dashboard</span>
                    </div>
                </a>
            </div>
        </div>
        
        <div class="menu-divider"></div>
        
        <!-- Quản lý sản phẩm -->
        <div class="menu-section">
            <div class="menu-item">
                <a href="#" class="menu-link" onclick="toggleSubmenu('products-menu', this)">
                    <div class="d-flex align-items-center">
                        <i class="fas fa-box menu-icon"></i>
                        <span class="menu-text">Quản lý sản phẩm</span>
                    </div>
                    <i class="fas fa-chevron-down menu-arrow"></i>
                </a>
            </div>
            <div id="products-menu" class="submenu <?= isActiveMenu('/admin/products/') ? 'show' : '' ?>">
                <a href="<?= $base_url ?>/admin/products/index.php" 
                   class="submenu-link <?= isActiveMenu('/admin/products/index.php') ?>">
                    <i class="fas fa-list me-2"></i>Danh sách sản phẩm
                </a>
                <a href="<?= $base_url ?>/admin/products/create.php" 
                   class="submenu-link <?= isActiveMenu('/admin/products/create.php') ?>">
                    <i class="fas fa-plus me-2"></i>Thêm sản phẩm
                </a>
                <a href="<?= $base_url ?>/admin/products/variants.php" 
                   class="submenu-link <?= isActiveMenu('/admin/products/variants.php') ?>">
                    <i class="fas fa-cubes me-2"></i>Biến thể sản phẩm
                </a>
            </div>
        </div>
        
        <!-- Danh mục & Thuộc tính -->
        <div class="menu-section">
            <div class="menu-item">
                <a href="#" class="menu-link" onclick="toggleSubmenu('categories-menu', this)">
                    <div class="d-flex align-items-center">
                        <i class="fas fa-tags menu-icon"></i>
                        <span class="menu-text">Danh mục & Thuộc tính</span>
                    </div>
                    <i class="fas fa-chevron-down menu-arrow"></i>
                </a>
            </div>
            <div id="categories-menu" class="submenu <?= isActiveMenu('/admin/categories/') || isActiveMenu('/admin/sizes/') || isActiveMenu('/admin/colors/') ? 'show' : '' ?>">
                <a href="<?= $base_url ?>/admin/categories/index.php" 
                   class="submenu-link <?= isActiveMenu('/admin/categories/') ?>">
                    <i class="fas fa-folder me-2"></i>Danh mục
                </a>
                <a href="<?= $base_url ?>/admin/sizes/index.php" 
                   class="submenu-link <?= isActiveMenu('/admin/sizes/') ?>">
                    <i class="fas fa-ruler me-2"></i>Kích cỡ
                </a>
                <a href="<?= $base_url ?>/admin/colors/index.php" 
                   class="submenu-link <?= isActiveMenu('/admin/colors/') ?>">
                    <i class="fas fa-palette me-2"></i>Màu sắc
                </a>
            </div>
        </div>
        
        <div class="menu-divider"></div>
        
        <!-- Quản lý đơn hàng -->
        <div class="menu-section">
            <div class="menu-item">
                <a href="#" class="menu-link" onclick="toggleSubmenu('orders-menu', this)">
                    <div class="d-flex align-items-center">
                        <i class="fas fa-shopping-cart menu-icon"></i>
                        <span class="menu-text">Quản lý đơn hàng</span>
                    </div>
                    <i class="fas fa-chevron-down menu-arrow"></i>
                </a>
            </div>
            <div id="orders-menu" class="submenu <?= isActiveMenu('/admin/orders/') || isActiveMenu('/admin/cod/') || isActiveMenu('/admin/shipping/') ? 'show' : '' ?>">
                <a href="<?= $base_url ?>/admin/orders/index.php" 
                   class="submenu-link <?= isActiveMenu('/admin/orders/index.php') ?>">
                    <i class="fas fa-list me-2"></i>Tất cả đơn hàng
                </a>
                <a href="<?= $base_url ?>/admin/cod/index.php" 
                   class="submenu-link <?= isActiveMenu('/admin/cod/') ?>">
                    <i class="fas fa-money-bill-wave me-2"></i>Quản lý COD
                </a>
                <a href="<?= $base_url ?>/admin/shipping/index.php" 
                   class="submenu-link <?= isActiveMenu('/admin/shipping/index.php') ?>">
                    <i class="fas fa-shipping-fast me-2"></i>Vận chuyển
                </a>
                <a href="<?= $base_url ?>/admin/shipping/shippers.php" 
                   class="submenu-link <?= isActiveMenu('/admin/shipping/shippers.php') ?>">
                    <i class="fas fa-motorcycle me-2"></i>Quản lý Shipper
                </a>
            </div>
        </div>
        
        <!-- Quản lý người dùng -->
        <div class="menu-section">
            <div class="menu-item">
                <a href="<?= $base_url ?>/admin/users/index.php" 
                   class="menu-link <?= isActiveMenu('/admin/users/') ?>">
                    <div class="d-flex align-items-center">
                        <i class="fas fa-users menu-icon"></i>
                        <span class="menu-text">Quản lý người dùng</span>
                    </div>
                </a>
            </div>
        </div>
        
        <!-- Quản lý đánh giá -->
        <div class="menu-section">
            <div class="menu-item">
                <a href="<?= $base_url ?>/admin/reviews/index.php" 
                   class="menu-link <?= isActiveMenu('/admin/reviews/') ?>">
                    <div class="d-flex align-items-center">
                        <i class="fas fa-star menu-icon"></i>
                        <span class="menu-text">Quản lý đánh giá</span>
                    </div>
                </a>
            </div>
        </div>
        
        <div class="menu-divider"></div>
        
        <!-- VNPay & Báo cáo -->
        <div class="menu-section">
            <div class="menu-item">
                <a href="#" class="menu-link" onclick="toggleSubmenu('reports-menu', this)">
                    <div class="d-flex align-items-center">
                        <i class="fas fa-chart-line menu-icon"></i>
                        <span class="menu-text">Báo cáo & VNPay</span>
                    </div>
                    <i class="fas fa-chevron-down menu-arrow"></i>
                </a>
            </div>
            <div id="reports-menu" class="submenu">
                <a href="<?= $base_url ?>/admin/cod/reports.php" class="submenu-link">
                    <i class="fas fa-chart-bar me-2"></i>Báo cáo COD
                </a>
                <a href="<?= $base_url ?>/vnpay/check_status.php" class="submenu-link">
                    <i class="fas fa-credit-card me-2"></i>Kiểm tra VNPay
                </a>
            </div>
        </div>
    </div>
    
    <!-- User Section -->
    <div class="user-section">
        <div class="user-info">
            <div class="user-avatar">
                <?= strtoupper(substr($_SESSION['admin_name'] ?? $_SESSION['user_name'] ?? 'A', 0, 1)) ?>
            </div>
            <div class="user-details">
                <h6><?= $_SESSION['admin_name'] ?? $_SESSION['user_name'] ?? 'Administrator' ?></h6>
                <small>Quản trị viên</small>
            </div>
        </div>
        
        <form method="POST" action="<?= $base_url ?>/admin/logout.php">
            <button type="submit" class="logout-btn">
                <i class="fas fa-sign-out-alt me-2"></i>
                Đăng xuất
            </button>
        </form>
    </div>
</div>

<script>
function toggleSubmenu(menuId, triggerElement) {
    event.preventDefault();
    
    const submenu = document.getElementById(menuId);
    const arrow = triggerElement.querySelector('.menu-arrow');
    
    // Close all other submenus
    document.querySelectorAll('.submenu.show').forEach(menu => {
        if (menu.id !== menuId) {
            menu.classList.remove('show');
        }
    });
    
    document.querySelectorAll('.menu-arrow.rotated').forEach(arr => {
        if (arr !== arrow) {
            arr.classList.remove('rotated');
        }
    });
    
    // Toggle current submenu
    submenu.classList.toggle('show');
    arrow.classList.toggle('rotated');
}

// Auto expand active menu on page load
document.addEventListener('DOMContentLoaded', function() {
    const activeSubmenuLink = document.querySelector('.submenu-link.active');
    if (activeSubmenuLink) {
        const parentSubmenu = activeSubmenuLink.closest('.submenu');
        if (parentSubmenu) {
            parentSubmenu.classList.add('show');
            const parentLink = parentSubmenu.previousElementSibling;
            const arrow = parentLink.querySelector('.menu-arrow');
            if (arrow) {
                arrow.classList.add('rotated');
            }
        }
    }
});
</script>