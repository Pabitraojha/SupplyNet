<?php
session_start();
require_once '../config/db.php';

// Auth Protection
if (!isset($_SESSION['UserID']) || strtolower($_SESSION['Role']) !== 'employee') {
    header("Location: ../login.php");
    exit();
}

$display_name = $_SESSION['UserName'] ?? 'Employee';

// Check & Fix Schema for Deliveries if needed
$check_col = $conn->query("SHOW COLUMNS FROM Deliveries LIKE 'DeliveryPersonID'");
if ($check_col && $check_col->num_rows == 0) {
    $conn->query("ALTER TABLE Deliveries ADD COLUMN DeliveryPersonID INT DEFAULT NULL");
    $conn->query("ALTER TABLE Deliveries ADD CONSTRAINT fk_del_person FOREIGN KEY (DeliveryPersonID) REFERENCES Users(UserID)");
}

// Fetch Workflow Metrics
$metrics = [
    'pending_orders' => 0, 'processing_orders' => 0,
    'active_deliveries' => 0, 'total_routes' => 0,
    'today_orders' => 0, 'yesterday_orders' => 0,
    'week_orders' => 0, 'month_orders' => 0
];

$res = $conn->query("SELECT OrderStatus, COUNT(*) as cnt FROM Orders WHERE markasdeleted=0 GROUP BY OrderStatus");
if($res) {
    while($row = $res->fetch_assoc()) {
        if($row['OrderStatus'] == 'pending') $metrics['pending_orders'] = $row['cnt'];
        if($row['OrderStatus'] == 'processing') $metrics['processing_orders'] = $row['cnt'];
    }
}
$res3 = $conn->query("SELECT COUNT(*) as cnt FROM Deliveries WHERE DeliveryStatus IN ('pending', 'in_transit') AND markasdeleted=0");
if($res3) $metrics['active_deliveries'] = $res3->fetch_assoc()['cnt'];
$res4 = $conn->query("SELECT COUNT(*) as cnt FROM routes WHERE markasdeleted=0");
if($res4) $metrics['total_routes'] = $res4->fetch_assoc()['cnt'];

// Time-based Order Performance Metrics
$t_res = $conn->query("SELECT COUNT(*) as cnt FROM Orders WHERE DATE(OrderDate) = CURDATE() AND markasdeleted=0");
if($t_res) $metrics['today_orders'] = $t_res->fetch_assoc()['cnt'];

$y_res = $conn->query("SELECT COUNT(*) as cnt FROM Orders WHERE DATE(OrderDate) = DATE_SUB(CURDATE(), INTERVAL 1 DAY) AND markasdeleted=0");
if($y_res) $metrics['yesterday_orders'] = $y_res->fetch_assoc()['cnt'];

$w_res = $conn->query("SELECT COUNT(*) as cnt FROM Orders WHERE YEARWEEK(OrderDate, 1) = YEARWEEK(CURDATE(), 1) AND markasdeleted=0");
if($w_res) $metrics['week_orders'] = $w_res->fetch_assoc()['cnt'];

$m_res = $conn->query("SELECT COUNT(*) as cnt FROM Orders WHERE MONTH(OrderDate) = MONTH(CURDATE()) AND YEAR(OrderDate) = YEAR(CURDATE()) AND markasdeleted=0");
if($m_res) $metrics['month_orders'] = $m_res->fetch_assoc()['cnt'];

// Chart Data Calculation (Last 7 Days)
$chart_labels = [];
$chart_data = [];
for ($i = 6; $i >= 0; $i--) {
    $date_val = date('Y-m-d', strtotime("-$i days"));
    $chart_labels[] = date('M d', strtotime("-$i days"));
    $c_res = $conn->query("SELECT COUNT(*) as cnt FROM Orders WHERE DATE(OrderDate) = '$date_val' AND markasdeleted=0");
    $chart_data[] = $c_res ? $c_res->fetch_assoc()['cnt'] : 0;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Employee Dashboard - SupplyNet</title>
    <!-- Fonts -->
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    <!-- Bootstrap CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <!-- FontAwesome -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    
    <style>
        :root {
            --primary-color: #1cc88a;
            --secondary-color: #f8f9fc;
            --info-color: #36b9cc;
            --warning-color: #f6c23e;
            --danger-color: #e74a3b;
            --dark-color: #1f2937;
            --sidebar-width: 250px;
        }
        body { font-family: 'Inter', sans-serif; background-color: var(--secondary-color); overflow-x: hidden; }
        
        /* Sidebar Styling */
        .sidebar { width: var(--sidebar-width); height: 100vh; position: fixed; background: linear-gradient(180deg, #1cc88a 10%, #13855c 100%); color: #fff; z-index: 1000; box-shadow: 2px 0 10px rgba(0,0,0,0.1); }
        .sidebar .brand { padding: 1.5rem 1rem; font-size: 1.25rem; font-weight: 800; text-align: center; text-transform: uppercase; letter-spacing: 0.05rem; border-bottom: 1px solid rgba(255,255,255,0.1); display: block; color: #fff; text-decoration: none; }
        .nav-item { padding: 0 1rem; margin-bottom: 0.5rem; }
        .nav-link { color: rgba(255,255,255,0.8); padding: 1rem; border-radius: 0.5rem; font-weight: 600; transition: all 0.3s; text-decoration: none;}
        .nav-link:hover, .nav-link.active { color: #fff; background: rgba(255,255,255,0.15); transform: translateX(5px); }
        .nav-link i { margin-right: 0.75rem; width: 20px; text-align: center; }

        /* Main Content wrapper */
        .main-wrapper { margin-left: var(--sidebar-width); flex-grow: 1; min-height: 100vh; display: flex; flex-direction: column; }
        
        /* Top Navigation */
        .topbar { background: #fff; height: 70px; box-shadow: 0 0.15rem 1.75rem 0 rgba(58, 59, 69, 0.15); display: flex; align-items: center; justify-content: space-between; padding: 0 2rem; }
        .topbar-user { font-weight: 600; color: #5a5c69; display: flex; align-items: center; gap: 0.5rem; text-decoration: none; }
        .topbar-user img { width: 35px; height: 35px; border-radius: 50%; border: 2px solid var(--primary-color); }

        .dashboard-content { padding: 1.5rem 2rem; }
        
        .card-custom { border: none; border-radius: 0.75rem; box-shadow: 0 0.15rem 1.75rem 0 rgba(58, 59, 69, 0.1); background: #fff; transition: transform 0.2s; }
        .card-custom:hover { transform: translateY(-3px); }
        
        .metric-title { font-size: 0.75rem; font-weight: 700; text-transform: uppercase; margin-bottom: 0.25rem; letter-spacing: 0.05rem; }
        .metric-value { font-size: 1.5rem; font-weight: 800; color: #5a5c69; margin: 0; }
        .metric-icon { font-size: 2rem; color: #dddfeb; }

        @media (max-width: 768px) {
            .sidebar { transform: translateX(-100%); transition: transform 0.3s ease-in-out; }
            .sidebar.show-sidebar { transform: translateX(0); }
            .main-wrapper { margin-left: 0; width: 100%; }
            .sidebar-overlay { display: none; position: fixed; top: 0; left: 0; right: 0; bottom: 0; background: rgba(0,0,0,0.5); z-index: 999; cursor: pointer; }
            .sidebar-overlay.show { display: block; }
            .topbar { padding: 0 1rem; }
            .dashboard-content { padding: 1rem; }
            
            .card-custom .metric-value { font-size: 1.25rem; }
            .card-custom .metric-icon { font-size: 1.5rem; }
        }
    </style>
</head>
<body>

<!-- Sidebar -->
<div class="sidebar">
    <a href="index.php" class="brand"><i class="fas fa-cubes me-2"></i>SupplyNet<br><small class="text-white-50" style="font-size: 0.7rem;">Employee Panel</small></a>
    <div class="mt-4">
        <div class="nav-item"><a href="index.php" class="nav-link active"><i class="fas fa-tachometer-alt"></i> Dashboard</a></div>
        <div class="nav-item"><a href="manageOrder.php" class="nav-link"><i class="fas fa-tasks"></i> Manage Orders</a></div>
        <div class="nav-item"><a href="viewOrderHistory.php" class="nav-link"><i class="fas fa-history"></i> Order History</a></div>
        <div class="nav-item"><a href="assignOrder.php" class="nav-link"><i class="fas fa-clipboard-check"></i> Assign Orders</a></div>
        <div class="nav-item"><a href="routeAssign.php" class="nav-link"><i class="fas fa-map-marked-alt"></i> Route Assign</a></div>
    </div>
</div>

<div class="sidebar-overlay" id="sidebarOverlay"></div>
<div class="main-wrapper">
    <div class="topbar">
        <div class="d-flex align-items-center">
            <button class="btn btn-link d-md-none text-dark me-2 px-0" id="sidebarToggle"><i class="fas fa-bars fs-5"></i></button>
            <div class="ms-2 ms-md-0">
                <h4 class="m-0 fw-bold text-dark fs-5 fs-md-4">Dashboard Overview</h4>
            </div>
        </div>
        <div class="d-flex align-items-center gap-3 gap-md-4">
            <div class="dropdown d-inline-block">
    <a href="#" class="text-secondary position-relative dropdown-toggle" data-bs-toggle="dropdown" aria-expanded="false" style="text-decoration: none;">
        <i class="fas fa-bell fs-5"></i>
        <?php if(!empty($notif_data) && $notif_data['count'] > 0): ?>
            <span class="position-absolute top-0 start-100 translate-middle badge rounded-pill bg-danger" style="font-size: 0.6rem;">
                <?php echo $notif_data['count']; ?>
            </span>
        <?php endif; ?>
    </a>
    <ul class="dropdown-menu dropdown-menu-end shadow border-0 mt-3 py-0 dropdown-menu-notif" style="min-width: 320px; max-height: 400px; overflow-y: auto;">
        <li class="dropdown-header bg-primary text-white fw-bold py-2 rounded-top d-flex justify-content-between align-items-center">
            <span>Notifications</span>
            <?php if(!empty($notif_data) && $notif_data['count'] > 0): ?>
                <span class="badge bg-light text-primary rounded-pill"><?php echo $notif_data['count']; ?> New</span>
            <?php endif; ?>
        </li>
        <?php if(empty($notif_data) || empty($notif_data['list'])): ?>
            <li><a class="dropdown-item text-muted py-4 text-center" href="#"><i class="fas fa-bell-slash fs-4 d-block mb-2 opacity-50"></i>No new notifications</a></li>
        <?php else: ?>
            <?php foreach($notif_data['list'] as $n): ?>
                <li class="border-bottom">
                    <a class="dropdown-item py-3 text-wrap <?php echo !$n['IsRead'] ? 'fw-bold bg-light' : ''; ?>" href="#" style="font-size: 0.85rem; line-height: 1.4; white-space: normal;">
                        <div class="small text-muted mb-1 d-flex justify-content-between align-items-center">
                            <span><i class="fas fa-clock me-1"></i><?php echo date('M d, h:i A', strtotime($n['CreatedAt'])); ?></span>
                            <?php if(!$n['IsRead']): ?><span class="badge bg-danger p-1 border border-light rounded-circle" style="width: 8px; height: 8px;"></span><?php endif; ?>
                        </div>
                        <div class="text-dark"><?php echo htmlspecialchars($n['Message']); ?></div>
                    </a>
                </li>
            <?php endforeach; ?>
        <?php endif; ?>
        <li><a class="dropdown-item text-center small text-primary fw-bold py-2 bg-light rounded-bottom text-decoration-none" href="?mark_read=true"><i class="fas fa-check-double me-1"></i>Mark all as read</a></li>
    </ul>
</div>
            <div class="dropdown">
                <a class="text-decoration-none topbar-user dropdown-toggle" href="#" role="button" data-bs-toggle="dropdown">
                    <span class="d-none d-lg-inline"><?php echo htmlspecialchars($display_name); ?></span>
                    <img src="https://ui-avatars.com/api/?name=<?php echo urlencode($display_name); ?>&background=1cc88a&color=fff" alt="Profile">
                </a>
                <ul class="dropdown-menu dropdown-menu-end shadow border-0 mt-2">
                    <li><a class="dropdown-item" href="profile.php"><i class="fas fa-user fa-sm fa-fw me-2 text-gray-400"></i> Profile</a></li>
                    <li><hr class="dropdown-divider"></li>
                    <li><a class="dropdown-item text-danger" href="../logout.php"><i class="fas fa-sign-out-alt fa-sm fa-fw me-2"></i> Logout</a></li>
                </ul>
            </div>
        </div>
    </div>

    <div class="dashboard-content">
        <div class="row g-4">
            <!-- Pending Orders -->
            <div class="col-xl-3 col-md-6">
                <div class="card card-custom h-100 py-2" style="border-left: 0.25rem solid var(--warning-color);">
                    <div class="card-body">
                        <div class="row align-items-center">
                            <div class="col">
                                <div class="metric-title text-warning">Pending Orders</div>
                                <div class="metric-value"><?php echo $metrics['pending_orders']; ?></div>
                            </div>
                            <div class="col-auto"><i class="fas fa-clock metric-icon"></i></div>
                        </div>
                    </div>
                </div>
            </div>
            <!-- Processing Orders -->
            <div class="col-xl-3 col-md-6">
                <div class="card card-custom h-100 py-2" style="border-left: 0.25rem solid var(--info-color);">
                    <div class="card-body">
                        <div class="row align-items-center">
                            <div class="col">
                                <div class="metric-title text-info">Processing Orders</div>
                                <div class="metric-value"><?php echo $metrics['processing_orders']; ?></div>
                            </div>
                            <div class="col-auto"><i class="fas fa-cog metric-icon"></i></div>
                        </div>
                    </div>
                </div>
            </div>
            <!-- Active Deliveries -->
            <div class="col-xl-3 col-md-6">
                <div class="card card-custom h-100 py-2" style="border-left: 0.25rem solid var(--primary-color);">
                    <div class="card-body">
                        <div class="row align-items-center">
                            <div class="col">
                                <div class="metric-title" style="color: var(--primary-color);">Active Deliveries</div>
                                <div class="metric-value"><?php echo $metrics['active_deliveries']; ?></div>
                            </div>
                            <div class="col-auto"><i class="fas fa-truck-loading metric-icon"></i></div>
                        </div>
                    </div>
                </div>
            </div>
            <!-- Total Routes -->
            <div class="col-xl-3 col-md-6">
                <div class="card card-custom h-100 py-2" style="border-left: 0.25rem solid var(--dark-color);">
                    <div class="card-body">
                        <div class="row align-items-center">
                            <div class="col">
                                <div class="metric-title text-dark">Active Routes</div>
                                <div class="metric-value"><?php echo $metrics['total_routes']; ?></div>
                            </div>
                            <div class="col-auto"><i class="fas fa-route metric-icon"></i></div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        
        <!-- Time-Based Order Reporting -->
        <h6 class="mt-4 mb-3 fw-bold text-dark"><i class="fas fa-chart-line me-2 text-primary" style="color:var(--primary-color) !important;"></i>Order Volume Reports</h6>
        <div class="row g-4 mb-4">
            <div class="col-xl-3 col-md-6">
                <div class="card card-custom h-100 py-2 border-0 bg-white">
                    <div class="card-body">
                        <div class="metric-title text-secondary">Today's Orders</div>
                        <div class="d-flex align-items-center justify-content-between">
                            <h3 class="metric-value text-primary m-0" style="color:var(--primary-color) !important;"><?php echo $metrics['today_orders']; ?></h3>
                            <i class="fas fa-calendar-day fs-3 text-light opacity-50 text-secondary"></i>
                        </div>
                    </div>
                </div>
            </div>
            <div class="col-xl-3 col-md-6">
                <div class="card card-custom h-100 py-2 border-0 bg-white">
                    <div class="card-body">
                        <div class="metric-title text-secondary">Yesterday</div>
                        <div class="d-flex align-items-center justify-content-between">
                            <h3 class="metric-value text-muted m-0"><?php echo $metrics['yesterday_orders']; ?></h3>
                            <i class="fas fa-calendar-minus fs-3 text-light opacity-50 text-secondary"></i>
                        </div>
                    </div>
                </div>
            </div>
            <div class="col-xl-3 col-md-6">
                <div class="card card-custom h-100 py-2 border-0 bg-white">
                    <div class="card-body">
                        <div class="metric-title text-secondary">This Week</div>
                        <div class="d-flex align-items-center justify-content-between">
                            <h3 class="metric-value text-info m-0"><?php echo $metrics['week_orders']; ?></h3>
                            <i class="fas fa-calendar-week fs-3 text-light opacity-50 text-secondary"></i>
                        </div>
                    </div>
                </div>
            </div>
            <div class="col-xl-3 col-md-6">
                <div class="card card-custom h-100 py-2 border-0 bg-white">
                    <div class="card-body">
                        <div class="metric-title text-secondary">This Month</div>
                        <div class="d-flex align-items-center justify-content-between">
                            <h3 class="metric-value text-dark m-0"><?php echo $metrics['month_orders']; ?></h3>
                            <i class="fas fa-calendar-alt fs-3 text-light opacity-50 text-secondary"></i>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        
        <div class="row mt-4">
            <!-- Graph Section -->
            <div class="col-lg-7 mb-4">
                <div class="card card-custom shadow-sm h-100">
                    <div class="card-header bg-white border-bottom py-3 d-flex flex-row align-items-center justify-content-between">
                        <h6 class="m-0 font-weight-bold" style="color: var(--primary-color);">7-Day Order Trajectory</h6>
                    </div>
                    <div class="card-body">
                        <div class="chart-area" style="position: relative; height: 100%; min-height: 250px;">
                            <canvas id="ordersChart"></canvas>
                        </div>
                    </div>
                </div>
            </div>
            
            <!-- Quick Actions Section -->
            <div class="col-lg-5 mb-4">
                <div class="card card-custom shadow-sm h-100">
                    <div class="card-header bg-white border-bottom py-3">
                        <h6 class="m-0 font-weight-bold" style="color: var(--primary-color);">Quick Actions & Links</h6>
                    </div>
                    <div class="card-body d-flex flex-column justify-content-center gap-3">
                        <a href="manageOrder.php" class="btn btn-outline-secondary text-start p-3 fw-semibold hover-lift"><i class="fas fa-tasks text-info me-3 fs-5"></i> Process pending orders</a>
                        <a href="assignOrder.php" class="btn btn-outline-secondary text-start p-3 fw-semibold hover-lift"><i class="fas fa-clipboard-check text-success me-3 fs-5"></i> Map assignments to Drivers</a>
                        <a href="viewOrderHistory.php" class="btn btn-outline-secondary text-start p-3 fw-semibold hover-lift"><i class="fas fa-history text-primary me-3 fs-5"></i> Browse processing history</a>
                    </div>
                </div>
            </div>
        </div>
    </div>
    <?php include '../config/footer.php'; ?>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
<script>
    // Chart Initialization
    document.addEventListener("DOMContentLoaded", function() {
        var ctx = document.getElementById("ordersChart").getContext('2d');
        var ordersChart = new Chart(ctx, {
            type: 'line',
            data: {
                labels: <?php echo json_encode($chart_labels); ?>,
                datasets: [{
                    label: "Orders Placed",
                    lineTension: 0.3,
                    backgroundColor: "rgba(28, 200, 138, 0.05)",
                    borderColor: "rgba(28, 200, 138, 1)",
                    pointRadius: 4,
                    pointBackgroundColor: "rgba(28, 200, 138, 1)",
                    pointBorderColor: "rgba(255, 255, 255, 1)",
                    pointHoverRadius: 6,
                    pointHoverBackgroundColor: "rgba(28, 200, 138, 1)",
                    pointHoverBorderColor: "rgba(255, 255, 255, 1)",
                    pointHitRadius: 10,
                    pointBorderWidth: 2,
                    data: <?php echo json_encode($chart_data); ?>,
                    fill: true,
                }],
            },
            options: {
                maintainAspectRatio: false,
                layout: { padding: { left: 10, right: 25, top: 10, bottom: 0 } },
                scales: {
                    x: {
                        grid: { display: false, drawBorder: false },
                        ticks: { maxTicksLimit: 7 }
                    },
                    y: {
                        ticks: {
                            maxTicksLimit: 5,
                            padding: 10,
                            stepSize: 1
                        },
                        grid: {
                            color: "rgb(234, 236, 244)",
                            zeroLineColor: "rgb(234, 236, 244)",
                            drawBorder: false,
                            borderDash: [2],
                            zeroLineBorderDash: [2]
                        }
                    }
                },
                plugins: {
                    legend: { display: false }
                }
            }
        });
    });


    document.getElementById('sidebarToggle').addEventListener('click', function() {
        document.querySelector('.sidebar').classList.add('show-sidebar');
        document.getElementById('sidebarOverlay').classList.add('show');
    });

    document.getElementById('sidebarOverlay').addEventListener('click', function() {
        document.querySelector('.sidebar').classList.remove('show-sidebar');
        this.classList.remove('show');
    });
</script>
</body>
</html>
