<?php
session_start();
require_once '../config/db.php';

// Auth Protection: Check if user is logged in and truly an admin
if (!isset($_SESSION['UserID']) || strtolower($_SESSION['Role']) !== 'admin') {
    header("Location: ../login.php");
    exit();
}

$admin_name = $_SESSION['UserName'] ?? 'Administrator';

// ---- 1. CORE STATISTICS ----
$cust_res = mysqli_query($conn, "SELECT COUNT(*) as cnt FROM Users WHERE Role='customer' AND markasdeleted=0");
$customers_count = mysqli_fetch_assoc($cust_res)['cnt'] ?? 0;

$emp_res = mysqli_query($conn, "SELECT COUNT(*) as cnt FROM Users WHERE Role IN ('employee', 'salesman', 'delivery_person') AND markasdeleted=0");
$employees_count = mysqli_fetch_assoc($emp_res)['cnt'] ?? 0;

$prod_res = mysqli_query($conn, "SELECT COUNT(*) as cnt FROM Products WHERE markasdeleted=0");
$products_count = mysqli_fetch_assoc($prod_res)['cnt'] ?? 0;

$order_res = mysqli_query($conn, "SELECT COUNT(*) as cnt FROM Orders WHERE OrderStatus IN ('pending', 'processing') AND markasdeleted=0");
$current_orders = mysqli_fetch_assoc($order_res)['cnt'] ?? 0;


// ---- 2. TIME-BASED ORDER METRICS ----
$wk_res = mysqli_query($conn, "SELECT COUNT(*) as cnt FROM Orders WHERE YEARWEEK(OrderDate, 1) = YEARWEEK(CURDATE(), 1) AND markasdeleted=0");
$orders_week = mysqli_fetch_assoc($wk_res)['cnt'] ?? 0;

$mo_res = mysqli_query($conn, "SELECT COUNT(*) as cnt FROM Orders WHERE MONTH(OrderDate) = MONTH(CURDATE()) AND YEAR(OrderDate) = YEAR(CURDATE()) AND markasdeleted=0");
$orders_month = mysqli_fetch_assoc($mo_res)['cnt'] ?? 0;

$yr_res = mysqli_query($conn, "SELECT COUNT(*) as cnt FROM Orders WHERE YEAR(OrderDate) = YEAR(CURDATE()) AND markasdeleted=0");
$orders_year = mysqli_fetch_assoc($yr_res)['cnt'] ?? 0;


// ---- 3. MONTHLY ANALYSIS GRAPH (Orders/Products Timeline) ----
$months_data = array_fill(1, 12, 0);
$yr_orders_query = mysqli_query($conn, "SELECT MONTH(OrderDate) as mth, COUNT(*) as cnt FROM Orders WHERE YEAR(OrderDate) = YEAR(CURDATE()) AND markasdeleted=0 GROUP BY MONTH(OrderDate)");
if ($yr_orders_query) {
    while ($row = mysqli_fetch_assoc($yr_orders_query)) {
        $months_data[$row['mth']] = $row['cnt'];
    }
}
$monthly_orders_json = json_encode(array_values($months_data));


// ---- 4. ORDER STATUS ANALYSIS (Doughnut Chart) ----
$status_data = ['pending' => 0, 'processing' => 0, 'shipped' => 0, 'delivered' => 0, 'cancelled' => 0];
$status_res = mysqli_query($conn, "SELECT OrderStatus, COUNT(*) as cnt FROM Orders WHERE markasdeleted=0 GROUP BY OrderStatus");
if ($status_res) {
    while ($row = mysqli_fetch_assoc($status_res)) {
        $status_data[$row['OrderStatus']] = $row['cnt'];
    }
}
$status_json = json_encode(array_values($status_data));


// ---- 5. WORKFORCE & CUSTOMER ANALYSIS (Doughnut Chart) ----
$role_data = ['customer' => 0, 'employee' => 0, 'salesman' => 0, 'delivery_person' => 0];
$role_res = mysqli_query($conn, "SELECT Role, COUNT(*) as cnt FROM Users WHERE Role != 'admin' AND markasdeleted=0 GROUP BY Role");
if ($role_res) {
    while ($row = mysqli_fetch_assoc($role_res)) {
        $role_data[$row['Role']] = $row['cnt'];
    }
}
$role_json = json_encode(array_values($role_data));

?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Dashboard - SupplyNet</title>
    <!-- Fonts -->
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&display=swap"
        rel="stylesheet">
    <!-- Bootstrap CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <!-- Chart.js -->
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>

    <style>
        :root {
            --primary-color: #4e73df;
            --info-color: #36b9cc;
            --success-color: #1cc88a;
            --warning-color: #f6c23e;
            --danger-color: #e74a3b;
            --dark-color: #1f2937;
            --sidebar-width: 250px;
        }

        body {
            font-family: 'Inter', sans-serif;
            background-color: #f8f9fc;
            overflow-x: hidden;
        }

        /* Sidebar Styling */
        .sidebar {
            width: var(--sidebar-width);
            height: 100vh;
            position: fixed;
            background: var(--primary-color);
            background: linear-gradient(180deg, #4e73df 10%, #224abe 100%);
            color: #fff;
            z-index: 1000;
            box-shadow: 2px 0 10px rgba(0, 0, 0, 0.1);
        }

        .sidebar .brand {
            padding: 1.5rem 1rem;
            font-size: 1.25rem;
            font-weight: 800;
            text-align: center;
            text-transform: uppercase;
            letter-spacing: 0.05rem;
            border-bottom: 1px solid rgba(255, 255, 255, 0.1);
            display: block;
            color: #fff;
            text-decoration: none;
        }

        .nav-item {
            padding: 0 1rem;
            margin-bottom: 0.5rem;
        }

        .nav-link {
            color: rgba(255, 255, 255, 0.8);
            padding: 1rem;
            border-radius: 0.5rem;
            font-weight: 600;
            transition: all 0.3s;
        }

        .nav-link:hover,
        .nav-link.active {
            color: #fff;
            background: rgba(255, 255, 255, 0.15);
            transform: translateX(5px);
        }

        .nav-link i {
            margin-right: 0.75rem;
            width: 20px;
            text-align: center;
        }

        /* Main Content wrapper */
        .main-wrapper {
            margin-left: var(--sidebar-width);
            flex-grow: 1;
            min-height: 100vh;
            display: flex;
            flex-direction: column;
        }

        /* Top Navigation */
        .topbar {
            background: #fff;
            height: 70px;
            box-shadow: 0 0.15rem 1.75rem 0 rgba(58, 59, 69, 0.15);
            display: flex;
            align-items: center;
            justify-content: space-between;
            padding: 0 2rem;
        }

        .topbar-user {
            font-weight: 600;
            color: #5a5c69;
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }

        .topbar-user img {
            width: 35px;
            height: 35px;
            border-radius: 50%;
            border: 2px solid var(--primary-color);
        }

        /* Dashboard Cards */
        .dashboard-content {
            padding: 1.5rem 2rem;
        }

        .card-custom {
            border: none;
            border-radius: 0.75rem;
            box-shadow: 0 0.15rem 1.75rem 0 rgba(58, 59, 69, 0.1);
            transition: transform 0.2s;
        }

        .card-custom:hover {
            transform: translateY(-3px);
        }

        .border-left-primary {
            border-left: 0.25rem solid var(--primary-color) !important;
        }

        .border-left-success {
            border-left: 0.25rem solid var(--success-color) !important;
        }

        .border-left-info {
            border-left: 0.25rem solid var(--info-color) !important;
        }

        .border-left-warning {
            border-left: 0.25rem solid var(--warning-color) !important;
        }

        .card-title {
            font-size: 0.75rem;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: 0.05rem;
            margin-bottom: 0.25rem;
        }

        .card-metric {
            font-size: 1.5rem;
            font-weight: 800;
            color: #5a5c69;
            margin: 0;
        }

        .card-icon {
            font-size: 2rem;
            color: #dddfeb;
        }

        /* Graph / Chart Cards */
        .chart-header {
            background-color: #f8f9fc;
            border-bottom: 1px solid #e3e6f0;
        }

        .chart-title {
            font-weight: 700;
            color: var(--primary-color);
            margin: 0;
            font-size: 1rem;
        }

        /* Time metrics mini cards */
        .time-metric-box {
            background: rgba(54, 185, 204, 0.1);
            border-radius: 0.5rem;
            padding: 1rem;
            text-align: center;
            border: 1px solid rgba(54, 185, 204, 0.2);
        }

        .time-metric-title {
            font-size: 0.8rem;
            font-weight: 700;
            color: var(--info-color);
            text-transform: uppercase;
            margin-bottom: 0.5rem;
        }

        .time-metric-val {
            font-size: 1.5rem;
            font-weight: 800;
            color: var(--dark-color);
        }

        @media (max-width: 768px) {
            .sidebar {
                transform: translateX(-100%);
                transition: transform 0.3s ease-in-out;
            }

            .sidebar.show-sidebar {
                transform: translateX(0);
            }

            .main-wrapper {
                margin-left: 0;
                width: 100%;
            }

            .sidebar-overlay {
                display: none;
                position: fixed;
                top: 0;
                left: 0;
                right: 0;
                bottom: 0;
                background: rgba(0, 0, 0, 0.5);
                z-index: 999;
                cursor: pointer;
            }

            .sidebar-overlay.show {
                display: block;
            }
        }
    </style>
</head>

<body>

    <!-- Sidebar -->
    <div class="sidebar">
        <a href="#" class="brand"><i class="fas fa-cubes me-2"></i>SupplyNet<br><small class="text-white-50"
                style="font-size: 0.7rem;">Admin Panel</small></a>
        <div class="mt-4">
            <div class="nav-item"><a href="index.php" class="nav-link active"><i class="fas fa-tachometer-alt"></i>
                    Dashboard</a></div>
            <div class="nav-item"><a href="user_management.php" class="nav-link"><i class="fas fa-users"></i> User
                    Management</a></div>
            <div class="nav-item"><a href="products_directory.php" class="nav-link"><i class="fas fa-box-open"></i>
                    Products Directory</a></div>
            <div class="nav-item"><a href="orders_hub.php" class="nav-link"><i class="fas fa-truck-loading"></i> Orders
                    Hub</a></div>
            <div class="nav-item"><a href="delivery_route.php" class="nav-link"><i class="fas fa-route"></i> Deliveries
                    & Routes</a></div>
            <div class="nav-item"><a href="customer_feedback.php" class="nav-link"><i class="fas fa-comments"></i>
                    Customer Feedback</a></div>
            <div class="nav-item"><a href="system_settings.php" class="nav-link"><i class="fas fa-cogs"></i> System
                    Settings</a></div>
        </div>
    </div>


    <div class="sidebar-overlay" id="sidebarOverlay"></div>
    <div class="main-wrapper">
        <!-- Topbar -->
        <div class="topbar">
            <div class="d-flex align-items-center">
                <button class="btn btn-link d-md-none text-dark me-3" id="sidebarToggle"><i
                        class="fas fa-bars"></i></button>
                <h4 class="m-0 fw-bold text-dark">Dashboard Overview</h4>
            </div>
            <div class="d-flex align-items-center gap-4">
                <div class="dropdown d-inline-block">
                    <a href="#" class="text-secondary position-relative dropdown-toggle" data-bs-toggle="dropdown"
                        aria-expanded="false" style="text-decoration: none;">
                        <i class="fas fa-bell fs-5"></i>
                        <?php if (!empty($notif_data) && $notif_data['count'] > 0): ?>
                            <span class="position-absolute top-0 start-100 translate-middle badge rounded-pill bg-danger"
                                style="font-size: 0.6rem;">
                                <?php echo $notif_data['count']; ?>
                            </span>
                        <?php endif; ?>
                    </a>
                    <ul class="dropdown-menu dropdown-menu-end shadow border-0 mt-3 py-0 dropdown-menu-notif"
                        style="min-width: 320px; max-height: 400px; overflow-y: auto;">
                        <li
                            class="dropdown-header bg-primary text-white fw-bold py-2 rounded-top d-flex justify-content-between align-items-center">
                            <span>Notifications</span>
                            <?php if (!empty($notif_data) && $notif_data['count'] > 0): ?>
                                <span class="badge bg-light text-primary rounded-pill"><?php echo $notif_data['count']; ?>
                                    New</span>
                            <?php endif; ?>
                        </li>
                        <?php if (empty($notif_data) || empty($notif_data['list'])): ?>
                            <li><a class="dropdown-item text-muted py-4 text-center" href="#"><i
                                        class="fas fa-bell-slash fs-4 d-block mb-2 opacity-50"></i>No new notifications</a>
                            </li>
                        <?php else: ?>
                            <?php foreach ($notif_data['list'] as $n): ?>
                                <li class="border-bottom">
                                    <a class="dropdown-item py-3 text-wrap <?php echo !$n['IsRead'] ? 'fw-bold bg-light' : ''; ?>"
                                        href="#" style="font-size: 0.85rem; line-height: 1.4; white-space: normal;">
                                        <div class="small text-muted mb-1 d-flex justify-content-between align-items-center">
                                            <span><i
                                                    class="fas fa-clock me-1"></i><?php echo date('M d, h:i A', strtotime($n['CreatedAt'])); ?></span>
                                            <?php if (!$n['IsRead']): ?><span
                                                    class="badge bg-danger p-1 border border-light rounded-circle"
                                                    style="width: 8px; height: 8px;"></span><?php endif; ?>
                                        </div>
                                        <div class="text-dark"><?php echo htmlspecialchars($n['Message']); ?></div>
                                    </a>
                                </li>
                            <?php endforeach; ?>
                        <?php endif; ?>
                        <li><a class="dropdown-item text-center small text-primary fw-bold py-2 bg-light rounded-bottom text-decoration-none"
                                href="?mark_read=true"><i class="fas fa-check-double me-1"></i>Mark all as read</a></li>
                    </ul>
                </div>
                <div class="dropdown">
                    <a class="text-decoration-none topbar-user dropdown-toggle" href="#" role="button"
                        data-bs-toggle="dropdown">
                        <span class="d-none d-lg-inline"><?php echo htmlspecialchars($admin_name); ?></span>
                        <img src="https://ui-avatars.com/api/?name=<?php echo urlencode($admin_name); ?>&background=4e73df&color=fff"
                            alt="Admin Profile">
                    </a>
                    <ul class="dropdown-menu dropdown-menu-end shadow border-0 mt-2">
                        <li><a class="dropdown-item" href="profile.php"><i
                                    class="fas fa-user fa-sm fa-fw me-2 text-gray-400"></i> Profile</a></li>
                        <li>
                            <hr class="dropdown-divider">
                        </li>
                        <li><a class="dropdown-item text-danger" href="../logout.php"><i
                                    class="fas fa-sign-out-alt fa-sm fa-fw me-2"></i> Logout</a></li>
                    </ul>
                </div>
            </div>
        </div>



        <!-- Main Content -->
        <div class="dashboard-content">
            <!-- Core Stats Row -->
            <div class="row g-4 mb-4">
                <!-- Customers Card -->
                <div class="col-xl-3 col-md-6">
                    <div class="card card-custom border-left-primary h-100 py-2">
                        <div class="card-body">
                            <div class="row align-items-center">
                                <div class="col">
                                    <div class="card-title text-primary">Registered Customers</div>
                                    <div class="card-metric"><?php echo $customers_count; ?></div>
                                </div>
                                <div class="col-auto"><i class="fas fa-users card-icon"></i></div>
                            </div>
                        </div>
                    </div>
                </div>
                <!-- Employees Card -->
                <div class="col-xl-3 col-md-6">
                    <div class="card card-custom border-left-success h-100 py-2">
                        <div class="card-body">
                            <div class="row align-items-center">
                                <div class="col">
                                    <div class="card-title text-success">Total Employees / Staff</div>
                                    <div class="card-metric"><?php echo $employees_count; ?></div>
                                </div>
                                <div class="col-auto"><i class="fas fa-id-badge card-icon"></i></div>
                            </div>
                        </div>
                    </div>
                </div>
                <!-- Products Card -->
                <div class="col-xl-3 col-md-6">
                    <div class="card card-custom border-left-info h-100 py-2">
                        <div class="card-body">
                            <div class="row align-items-center">
                                <div class="col">
                                    <div class="card-title text-info">Total Products Active</div>
                                    <div class="card-metric"><?php echo $products_count; ?></div>
                                </div>
                                <div class="col-auto"><i class="fas fa-box card-icon"></i></div>
                            </div>
                        </div>
                    </div>
                </div>
                <!-- Current Orders Card -->
                <div class="col-xl-3 col-md-6">
                    <div class="card card-custom border-left-warning h-100 py-2">
                        <div class="card-body">
                            <div class="row align-items-center">
                                <div class="col">
                                    <div class="card-title text-warning">Current Pending Orders</div>
                                    <div class="card-metric"><?php echo $current_orders; ?></div>
                                </div>
                                <div class="col-auto"><i class="fas fa-clipboard-list card-icon"></i></div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Orders Deep Dive & Row 2 -->
            <div class="row g-4 mb-4">
                <!-- Order Trajectory (Line Chart) -->
                <div class="col-xl-8 col-lg-7">
                    <div class="card card-custom h-100">
                        <div
                            class="card-header chart-header d-flex flex-row align-items-center justify-content-between py-3">
                            <h6 class="chart-title"><i class="fas fa-chart-line me-2"></i>Orders Trajectory (Current
                                Year)</h6>
                        </div>
                        <div class="card-body">
                            <canvas id="ordersLineChart" style="display: block; width: 100%; height: 320px;"></canvas>
                        </div>
                    </div>
                </div>

                <!-- Order Timing Snapshot -->
                <div class="col-xl-4 col-lg-5">
                    <div class="card card-custom h-100">
                        <div class="card-header chart-header py-3">
                            <h6 class="chart-title"><i class="fas fa-stopwatch me-2"></i>Total Ordered Products Timeline
                            </h6>
                        </div>
                        <div class="card-body d-flex flex-column justify-content-center gap-3">
                            <div class="time-metric-box">
                                <div class="time-metric-title">This Week</div>
                                <div class="time-metric-val"><?php echo $orders_week; ?></div>
                            </div>
                            <div class="time-metric-box"
                                style="background: rgba(28, 200, 138, 0.1); border-color: rgba(28, 200, 138, 0.2);">
                                <div class="time-metric-title" style="color: var(--success-color);">This Month</div>
                                <div class="time-metric-val"><?php echo $orders_month; ?></div>
                            </div>
                            <div class="time-metric-box"
                                style="background: rgba(78, 115, 223, 0.1); border-color: rgba(78, 115, 223, 0.2);">
                                <div class="time-metric-title" style="color: var(--primary-color);">This Year</div>
                                <div class="time-metric-val"><?php echo $orders_year; ?></div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

        <!-- Analytical Distribution Row (Row 3) -->
        <div class="row g-4">
            <!-- Order Status Analysis (Doughnut) -->
            <div class="col-lg-6">
                <div class="card card-custom h-100">
                    <div class="card-header chart-header py-3">
                        <h6 class="chart-title"><i class="fas fa-chart-pie me-2"></i>Order Status Analysis</h6>
                    </div>
                    <div class="card-body">
                        <div class="chart-pie pt-4 pb-2" style="height: 300px; position:relative;">
                            <canvas id="statusPieChart"></canvas>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Workforce & Customer Distribution (Doughnut) -->
            <div class="col-lg-6">
                <div class="card card-custom h-100">
                    <div class="card-header chart-header py-3">
                        <h6 class="chart-title"><i class="fas fa-users-cog me-2"></i>Workforce & Users Distribution
                        </h6>
                    </div>
                    <div class="card-body">
                        <div class="chart-pie pt-4 pb-2" style="height: 300px; position:relative;">
                            <canvas id="rolesPieChart"></canvas>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        <?php include '../config/footer.php'; ?>
    </div>
</div>

<!-- Bootstrap Bundle JS -->
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>

    <!-- Chart.js Setup -->
    <script>
        Chart.defaults.font.family = "'Inter', sans-serif";
        Chart.defaults.color = '#858796';

        // 1. Orders Line Chart
        var ctxLine = document.getElementById("ordersLineChart");
        var ordersLineChart = new Chart(ctxLine, {
            type: 'line',
            data: {
                labels: ["Jan", "Feb", "Mar", "Apr", "May", "Jun", "Jul", "Aug", "Sep", "Oct", "Nov", "Dec"],
                datasets: [{
                    label: "Orders Handled",
                    lineTension: 0.3,
                    backgroundColor: "rgba(78, 115, 223, 0.05)",
                    borderColor: "rgba(78, 115, 223, 1)",
                    pointRadius: 4,
                    pointBackgroundColor: "rgba(78, 115, 223, 1)",
                    pointBorderColor: "rgba(255, 255, 255, 1)",
                    pointHoverRadius: 6,
                    pointHoverBackgroundColor: "rgba(78, 115, 223, 1)",
                    pointHoverBorderColor: "rgba(255, 255, 255, 1)",
                    pointHitRadius: 10,
                    pointBorderWidth: 2,
                    data: <?php echo $monthly_orders_json; ?>,
                    fill: true
                }],
            },
            options: {
                maintainAspectRatio: false,
                plugins: { legend: { display: false } },
                scales: {
                    y: { beginAtZero: true, ticks: { precision: 0 } },
                    x: { grid: { display: false } }
                }
            }
        });

        // 2. Order Status Doughnut Chart
        var ctxStatus = document.getElementById("statusPieChart");
        var orderStatusData = <?php echo $status_json; ?>;
        var statusPieChart = new Chart(ctxStatus, {
            type: 'doughnut',
            data: {
                labels: ["Pending", "Processing", "Shipped", "Delivered", "Cancelled"],
                datasets: [{
                    data: orderStatusData,
                    backgroundColor: ['#f6c23e', '#36b9cc', '#4e73df', '#1cc88a', '#e74a3b'],
                    hoverBackgroundColor: ['#dda20a', '#2c9faf', '#2e59d9', '#17a673', '#be2617'],
                    hoverBorderColor: "rgba(234, 236, 244, 1)",
                }],
            },
            options: {
                maintainAspectRatio: false,
                cutout: '75%',
                plugins: {
                    legend: { position: 'bottom', labels: { padding: 20, boxWidth: 12 } }
                }
            }
        });

        // 3. User Roles Doughnut Chart
        var ctxRoles = document.getElementById("rolesPieChart");
        var userRolesData = <?php echo $role_json; ?>;
        var rolesPieChart = new Chart(ctxRoles, {
            type: 'doughnut',
            data: {
                labels: ["Customers", "Employees", "Salesmen", "Delivery Agents"],
                datasets: [{
                    data: userRolesData,
                    backgroundColor: ['#4e73df', '#36b9cc', '#1cc88a', '#f6c23e'],
                    hoverBackgroundColor: ['#2e59d9', '#2c9faf', '#17a673', '#dda20a'],
                    hoverBorderColor: "rgba(234, 236, 244, 1)",
                }],
            },
            options: {
                maintainAspectRatio: false,
                cutout: '75%',
                plugins: {
                    legend: { position: 'bottom', labels: { padding: 20, boxWidth: 12 } }
                }
            }
        });
    </script>


    <script>
        document.addEventListener('DOMContentLoaded', function () {
            var sidebarToggle = document.getElementById('sidebarToggle');
            var sidebar = document.querySelector('.sidebar');
            var sidebarOverlay = document.getElementById('sidebarOverlay');

            if (sidebarToggle && sidebar && sidebarOverlay) {
                sidebarToggle.addEventListener('click', function () {
                    sidebar.classList.toggle('show-sidebar');
                    sidebarOverlay.classList.toggle('show');
                });
                sidebarOverlay.addEventListener('click', function () {
                    sidebar.classList.remove('show-sidebar');
                    sidebarOverlay.classList.remove('show');
                });
            }
        });
    </script>
</body>

</html>