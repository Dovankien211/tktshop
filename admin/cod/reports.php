<?php
// admin/cod/reports.php
/**
 * Báo cáo COD - Analytics và thống kê chi tiết
 */

require_once '../../config/database.php';
require_once '../../config/config.php';

// Kiểm tra đăng nhập admin
if (!isset($_SESSION['admin_id'])) {
    redirect('/admin/login.php');
}

// Lấy filter parameters
$date_from = $_GET['date_from'] ?? date('Y-m-01'); // Đầu tháng
$date_to = $_GET['date_to'] ?? date('Y-m-d'); // Hôm nay
$shipper_id = $_GET['shipper_id'] ?? '';
$report_type = $_GET['report_type'] ?? 'overview';

// Thống kê tổng quan COD
$cod_overview = [];
try {
    $stmt = $pdo->prepare("
        SELECT 
            COUNT(*) as total_orders,
            COUNT(CASE WHEN trang_thai_don_hang = 'da_giao' THEN 1 END) as completed_orders,
            COUNT(CASE WHEN trang_thai_don_hang IN ('da_huy', 'hoan_tra') THEN 1 END) as failed_orders,
            SUM(tong_thanh_toan) as total_amount,
            SUM(CASE WHEN trang_thai_don_hang = 'da_giao' THEN tong_thanh_toan ELSE 0 END) as collected_amount,
            AVG(tong_thanh_toan) as avg_order_value,
            COUNT(DISTINCT shipper_id) as active_shippers
        FROM don_hang 
        WHERE phuong_thuc_thanh_toan = 'cod'
        AND DATE(ngay_dat_hang) BETWEEN ? AND ?
    ");
    $stmt->execute([$date_from, $date_to]);
    $cod_overview = $stmt->fetch();
    
    // Tính tỷ lệ thành công
    $cod_overview['success_rate'] = $cod_overview['total_orders'] > 0 
        ? ($cod_overview['completed_orders'] / $cod_overview['total_orders']) * 100 
        : 0;
        
} catch (Exception $e) {
    error_log('COD Overview Error: ' . $e->getMessage());
}

// Thống kê theo shipper
$shipper_stats = [];
try {
    $shipper_filter = $shipper_id ? "AND s.id = ?" : "";
    $params = [$date_from, $date_to];
    if ($shipper_id) $params[] = $shipper_id;
    
    $stmt = $pdo->prepare("
        SELECT 
            s.id, s.ten_shipper, s.so_dien_thoai, s.khu_vuc,
            COUNT(dh.id) as total_assigned,
            COUNT(CASE WHEN dh.trang_thai_don_hang = 'da_giao' THEN 1 END) as completed,
            COUNT(CASE WHEN dh.trang_thai_don_hang IN ('da_huy', 'hoan_tra') OR dh.ly_do_giao_that_bai IS NOT NULL THEN 1 END) as failed,
            SUM(CASE WHEN dh.trang_thai_don_hang = 'da_giao' THEN dh.tong_thanh_toan ELSE 0 END) as money_collected,
            AVG(CASE WHEN dh.trang_thai_don_hang = 'da_giao' THEN 
                TIMESTAMPDIFF(HOUR, dh.ngay_bat_dau_giao, dh.thoi_gian_giao_thuc_te) 
                END) as avg_delivery_hours,
            MAX(dh.ngay_cap_nhat) as last_activity
        FROM shippers s
        LEFT JOIN don_hang dh ON s.id = dh.shipper_id 
            AND dh.phuong_thuc_thanh_toan = 'cod'
            AND DATE(dh.ngay_dat_hang) BETWEEN ? AND ?
        {$shipper_filter}
        GROUP BY s.id
        ORDER BY money_collected DESC, completed DESC
    ");
    $stmt->execute($params);
    $shipper_stats = $stmt->fetchAll();
    
} catch (Exception $e) {
    error_log('Shipper Stats Error: ' . $e->getMessage());
}

// Thống kê theo ngày
$daily_stats = [];
try {
    $stmt = $pdo->prepare("
        SELECT 
            DATE(ngay_dat_hang) as order_date,
            COUNT(*) as total_orders,
            COUNT(CASE WHEN trang_thai_don_hang = 'da_giao' THEN 1 END) as completed,
            SUM(tong_thanh_toan) as total_amount,
            SUM(CASE WHEN trang_thai_don_hang = 'da_giao' THEN tong_thanh_toan ELSE 0 END) as collected_amount
        FROM don_hang 
        WHERE phuong_thuc_thanh_toan = 'cod'
        AND DATE(ngay_dat_hang) BETWEEN ? AND ?
        GROUP BY DATE(ngay_dat_hang)
        ORDER BY order_date DESC
        LIMIT 30
    ");
    $stmt->execute([$date_from, $date_to]);
    $daily_stats = $stmt->fetchAll();
    
} catch (Exception $e) {
    error_log('Daily Stats Error: ' . $e->getMessage());
}

// Lấy danh sách shipper cho filter
$shippers_list = [];
try {
    $stmt = $pdo->query("SELECT id, ten_shipper FROM shippers WHERE trang_thai = 'hoat_dong' ORDER BY ten_shipper");
    $shippers_list = $stmt->fetchAll();
} catch (Exception $e) {
    error_log('Shippers List Error: ' . $e->getMessage());
}

$page_title = 'Báo cáo COD';
?>

<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= $page_title ?> - Admin</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    
    <style>
        .stats-card {
            background: white;
            border-radius: 10px;
            padding: 25px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
            border-left: 4px solid;
            margin-bottom: 20px;
            transition: transform 0.3s ease;
        }
        
        .stats-card:hover {
            transform: translateY(-2px);
        }
        
        .stats-card.primary { border-left-color: #007bff; }
        .stats-card.success { border-left-color: #28a745; }
        .stats-card.warning { border-left-color: #ffc107; }
        .stats-card.danger { border-left-color: #dc3545; }
        .stats-card.info { border-left-color: #17a2b8; }
        
        .stat-number {
            font-size: 2rem;
            font-weight: bold;
            margin-bottom: 5px;
        }
        
        .stat-label {
            color: #6c757d;
            font-size: 0.9rem;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }
        
        .chart-container {
            background: white;
            border-radius: 10px;
            padding: 25px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
            margin-bottom: 20px;
        }
        
        .shipper-performance {
            background: white;
            border-radius: 10px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
            overflow: hidden;
        }
        
        .performance-header {
            background: #f8f9fa;
            padding: 20px;
            border-bottom: 1px solid #dee2e6;
        }
        
        .performance-item {
            padding: 15px 20px;
            border-bottom: 1px solid #f1f3f4;
            transition: background-color 0.3s ease;
        }
        
        .performance-item:hover {
            background: #f8f9fa;
        }
        
        .performance-item:last-child {
            border-bottom: none;
        }
        
        .filter-card {
            background: white;
            border-radius: 10px;
            padding: 20px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
            margin-bottom: 20px;
        }
        
        .progress-thin {
            height: 6px;
        }
        
        .metric-badge {
            padding: 4px 8px;
            border-radius: 12px;
            font-size: 0.75rem;
            font-weight: 600;
        }
        
        .trend-up {
            color: #28a745;
        }
        
        .trend-down {
            color: #dc3545;
        }
        
        .daily-stats-table {
            max-height: 400px;
            overflow-y: auto;
        }
    </style>
</head>
<body>
    <div class="container-fluid">
        <div class="row">
            <!-- Sidebar -->
            <div class="col-md-2 p-0">
                <?php include '../layouts/sidebar.php'; ?>
            </div>
            
            <!-- Main Content -->
            <div class="col-md-10">
                <div class="p-4">
                    <!-- Header -->
                    <div class="d-flex justify-content-between align-items-center mb-4">
                        <div>
                            <h2><i class="fas fa-chart-bar me-2"></i><?= $page_title ?></h2>
                            <p class="text-muted">Phân tích và thống kê hệ thống COD</p>
                        </div>
                        <div>
                            <a href="/admin/cod/index.php" class="btn btn-outline-primary">
                                <i class="fas fa-money-bill-wave me-1"></i>Quản lý COD
                            </a>
                            <button class="btn btn-success" onclick="exportReport()">
                                <i class="fas fa-file-excel me-1"></i>Xuất Excel
                            </button>
                        </div>
                    </div>
                    
                    <!-- Filter Card -->
                    <div class="filter-card">
                        <form method="GET" class="row g-3">
                            <div class="col-md-3">
                                <label class="form-label">Từ ngày:</label>
                                <input type="date" class="form-control" name="date_from" value="<?= $date_from ?>">
                            </div>
                            <div class="col-md-3">
                                <label class="form-label">Đến ngày:</label>
                                <input type="date" class="form-control" name="date_to" value="<?= $date_to ?>">
                            </div>
                            <div class="col-md-3">
                                <label class="form-label">Shipper:</label>
                                <select class="form-select" name="shipper_id">
                                    <option value="">Tất cả shipper</option>
                                    <?php foreach ($shippers_list as $shipper): ?>
                                        <option value="<?= $shipper['id'] ?>" <?= $shipper_id == $shipper['id'] ? 'selected' : '' ?>>
                                            <?= htmlspecialchars($shipper['ten_shipper']) ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div class="col-md-3">
                                <label class="form-label">&nbsp;</label>
                                <button type="submit" class="btn btn-primary d-block w-100">
                                    <i class="fas fa-filter me-1"></i>Lọc dữ liệu
                                </button>
                            </div>
                        </form>
                    </div>
                    
                    <!-- Overview Stats -->
                    <div class="row mb-4">
                        <div class="col-lg-2 col-md-4">
                            <div class="stats-card primary">
                                <div class="stat-number text-primary"><?= number_format($cod_overview['total_orders'] ?? 0) ?></div>
                                <div class="stat-label">Tổng đơn COD</div>
                            </div>
                        </div>
                        <div class="col-lg-2 col-md-4">
                            <div class="stats-card success">
                                <div class="stat-number text-success"><?= number_format($cod_overview['completed_orders'] ?? 0) ?></div>
                                <div class="stat-label">Giao thành công</div>
                                <div class="mt-2">
                                    <span class="metric-badge bg-success text-white">
                                        <?= number_format($cod_overview['success_rate'] ?? 0, 1) ?>%
                                    </span>
                                </div>
                            </div>
                        </div>
                        <div class="col-lg-2 col-md-4">
                            <div class="stats-card danger">
                                <div class="stat-number text-danger"><?= number_format($cod_overview['failed_orders'] ?? 0) ?></div>
                                <div class="stat-label">Giao thất bại</div>
                            </div>
                        </div>
                        <div class="col-lg-3 col-md-6">
                            <div class="stats-card info">
                                <div class="stat-number text-info"><?= formatPrice($cod_overview['collected_amount'] ?? 0) ?></div>
                                <div class="stat-label">Tiền đã thu</div>
                            </div>
                        </div>
                        <div class="col-lg-3 col-md-6">
                            <div class="stats-card warning">
                                <div class="stat-number text-warning"><?= formatPrice($cod_overview['avg_order_value'] ?? 0) ?></div>
                                <div class="stat-label">Giá trị TB/đơn</div>
                            </div>
                        </div>
                    </div>
                    
                    <div class="row">
                        <!-- Charts -->
                        <div class="col-lg-8">
                            <!-- Daily Revenue Chart -->
                            <div class="chart-container">
                                <h5 class="mb-3">
                                    <i class="fas fa-chart-line me-2"></i>
                                    Doanh thu COD theo ngày
                                </h5>
                                <canvas id="dailyRevenueChart" height="100"></canvas>
                            </div>
                            
                            <!-- Daily Orders Chart -->
                            <div class="chart-container">
                                <h5 class="mb-3">
                                    <i class="fas fa-chart-bar me-2"></i>
                                    Số đơn hàng theo ngày
                                </h5>
                                <canvas id="dailyOrdersChart" height="100"></canvas>
                            </div>
                        </div>
                        
                        <!-- Shipper Performance -->
                        <div class="col-lg-4">
                            <div class="shipper-performance">
                                <div class="performance-header">
                                    <h5 class="mb-0">
                                        <i class="fas fa-trophy me-2"></i>
                                        Hiệu suất Shipper
                                    </h5>
                                </div>
                                
                                <?php if (empty($shipper_stats)): ?>
                                    <div class="text-center py-4">
                                        <i class="fas fa-motorcycle fa-2x text-muted mb-2"></i>
                                        <p class="text-muted mb-0">Không có dữ liệu shipper</p>
                                    </div>
                                <?php else: ?>
                                    <?php foreach (array_slice($shipper_stats, 0, 10) as $index => $shipper): ?>
                                        <div class="performance-item">
                                            <div class="d-flex justify-content-between align-items-center mb-2">
                                                <div class="d-flex align-items-center">
                                                    <div class="me-3">
                                                        <span class="badge bg-<?= $index < 3 ? 'warning' : 'secondary' ?> rounded-pill">
                                                            #<?= $index + 1 ?>
                                                        </span>
                                                    </div>
                                                    <div>
                                                        <h6 class="mb-0"><?= htmlspecialchars($shipper['ten_shipper']) ?></h6>
                                                        <small class="text-muted"><?= htmlspecialchars($shipper['khu_vuc']) ?></small>
                                                    </div>
                                                </div>
                                                <div class="text-end">
                                                    <div class="fw-bold text-success">
                                                        <?= formatPrice($shipper['money_collected']) ?>
                                                    </div>
                                                    <small class="text-muted">
                                                        <?= $shipper['completed'] ?>/<?= $shipper['total_assigned'] ?> đơn
                                                    </small>
                                                </div>
                                            </div>
                                            
                                            <?php 
                                            $success_rate = $shipper['total_assigned'] > 0 
                                                ? ($shipper['completed'] / $shipper['total_assigned']) * 100 
                                                : 0;
                                            ?>
                                            <div class="progress progress-thin">
                                                <div class="progress-bar bg-<?= $success_rate >= 90 ? 'success' : ($success_rate >= 70 ? 'warning' : 'danger') ?>" 
                                                     style="width: <?= $success_rate ?>%"></div>
                                            </div>
                                            <div class="d-flex justify-content-between mt-1">
                                                <small class="text-muted">Tỷ lệ thành công</small>
                                                <small class="fw-bold"><?= number_format($success_rate, 1) ?>%</small>
                                            </div>
                                        </div>
                                    <?php endforeach; ?>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                    
                    <!-- Daily Stats Table -->
                    <div class="chart-container">
                        <h5 class="mb-3">
                            <i class="fas fa-calendar-alt me-2"></i>
                            Thống kê theo ngày
                        </h5>
                        
                        <div class="daily-stats-table">
                            <table class="table table-hover">
                                <thead class="table-light">
                                    <tr>
                                        <th>Ngày</th>
                                        <th>Tổng đơn</th>
                                        <th>Thành công</th>
                                        <th>Tỷ lệ</th>
                                        <th>Tổng tiền</th>
                                        <th>Đã thu</th>
                                        <th>Hiệu quả</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php if (empty($daily_stats)): ?>
                                        <tr>
                                            <td colspan="7" class="text-center py-4">
                                                <i class="fas fa-calendar-times fa-2x text-muted mb-2"></i>
                                                <div>Không có dữ liệu trong khoảng thời gian được chọn</div>
                                            </td>
                                        </tr>
                                    <?php else: ?>
                                        <?php foreach ($daily_stats as $stat): ?>
                                            <?php 
                                            $success_rate = $stat['total_orders'] > 0 
                                                ? ($stat['completed'] / $stat['total_orders']) * 100 
                                                : 0;
                                            $collection_rate = $stat['total_amount'] > 0 
                                                ? ($stat['collected_amount'] / $stat['total_amount']) * 100 
                                                : 0;
                                            ?>
                                            <tr>
                                                <td>
                                                    <strong><?= date('d/m/Y', strtotime($stat['order_date'])) ?></strong>
                                                    <div class="small text-muted"><?= date('l', strtotime($stat['order_date'])) ?></div>
                                                </td>
                                                <td><?= number_format($stat['total_orders']) ?></td>
                                                <td>
                                                    <span class="text-success fw-bold"><?= number_format($stat['completed']) ?></span>
                                                </td>
                                                <td>
                                                    <span class="metric-badge bg-<?= $success_rate >= 80 ? 'success' : ($success_rate >= 60 ? 'warning' : 'danger') ?> text-white">
                                                        <?= number_format($success_rate, 1) ?>%
                                                    </span>
                                                </td>
                                                <td><?= formatPrice($stat['total_amount']) ?></td>
                                                <td>
                                                    <span class="text-success fw-bold"><?= formatPrice($stat['collected_amount']) ?></span>
                                                </td>
                                                <td>
                                                    <div class="progress progress-thin">
                                                        <div class="progress-bar bg-info" style="width: <?= $collection_rate ?>%"></div>
                                                    </div>
                                                    <small class="text-muted"><?= number_format($collection_rate, 1) ?>%</small>
                                                </td>
                                            </tr>
                                        <?php endforeach; ?>
                                    <?php endif; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
    
    <script>
        // Prepare chart data
        const dailyData = <?= json_encode(array_reverse($daily_stats)) ?>;
        const labels = dailyData.map(item => {
            const date = new Date(item.order_date);
            return date.toLocaleDateString('vi-VN', { month: 'short', day: 'numeric' });
        });
        
        // Daily Revenue Chart
        const revenueCtx = document.getElementById('dailyRevenueChart').getContext('2d');
        new Chart(revenueCtx, {
            type: 'line',
            data: {
                labels: labels,
                datasets: [{
                    label: 'Tổng tiền',
                    data: dailyData.map(item => item.total_amount),
                    borderColor: '#007bff',
                    backgroundColor: 'rgba(0, 123, 255, 0.1)',
                    tension: 0.4,
                    fill: true
                }, {
                    label: 'Tiền đã thu',
                    data: dailyData.map(item => item.collected_amount),
                    borderColor: '#28a745',
                    backgroundColor: 'rgba(40, 167, 69, 0.1)',
                    tension: 0.4,
                    fill: true
                }]
            },
            options: {
                responsive: true,
                interaction: {
                    intersect: false,
                },
                scales: {
                    y: {
                        beginAtZero: true,
                        ticks: {
                            callback: function(value) {
                                return new Intl.NumberFormat('vi-VN', {
                                    style: 'currency',
                                    currency: 'VND',
                                    minimumFractionDigits: 0
                                }).format(value);
                            }
                        }
                    }
                },
                plugins: {
                    tooltip: {
                        callbacks: {
                            label: function(context) {
                                return context.dataset.label + ': ' + 
                                    new Intl.NumberFormat('vi-VN', {
                                        style: 'currency',
                                        currency: 'VND',
                                        minimumFractionDigits: 0
                                    }).format(context.parsed.y);
                            }
                        }
                    }
                }
            }
        });
        
        // Daily Orders Chart
        const ordersCtx = document.getElementById('dailyOrdersChart').getContext('2d');
        new Chart(ordersCtx, {
            type: 'bar',
            data: {
                labels: labels,
                datasets: [{
                    label: 'Tổng đơn',
                    data: dailyData.map(item => item.total_orders),
                    backgroundColor: 'rgba(0, 123, 255, 0.5)',
                    borderColor: '#007bff',
                    borderWidth: 1
                }, {
                    label: 'Thành công',
                    data: dailyData.map(item => item.completed),
                    backgroundColor: 'rgba(40, 167, 69, 0.5)',
                    borderColor: '#28a745',
                    borderWidth: 1
                }]
            },
            options: {
                responsive: true,
                scales: {
                    y: {
                        beginAtZero: true,
                        ticks: {
                            stepSize: 1
                        }
                    }
                }
            }
        });
        
        // Export report function
        function exportReport() {
            const params = new URLSearchParams(window.location.search);
            params.set('export', 'excel');
            
            // Create download link
            const link = document.createElement('a');
            link.href = window.location.pathname + '?' + params.toString();
            link.download = `cod_report_${new Date().toISOString().split('T')[0]}.xlsx`;
            link.click();
        }
        
        // Auto refresh every 5 minutes
        setTimeout(function() {
            location.reload();
        }, 300000);
        
        // Update clock
        function updateClock() {
            const now = new Date();
            const timeString = now.toLocaleTimeString('vi-VN');
            document.title = `${timeString} - <?= $page_title ?> - Admin`;
        }
        
        setInterval(updateClock, 1000);
    </script>
</body>
</html>