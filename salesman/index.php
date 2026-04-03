<?php
session_start();
require_once '../config/db.php';

// Auth Protection: Check if user is logged in and is a salesman
if (!isset($_SESSION['UserID']) || strtolower($_SESSION['Role']) !== 'salesman') {
    header("Location: ../login.php");
    exit();
}

$salesman_name = $_SESSION['UserName'] ?? 'Sales Representative';
$salesman_id = $_SESSION['UserID'];

// ---- CORE STATISTICS ----

// 1. Total Customers
$cust_res = mysqli_query($conn, "SELECT COUNT(*) as cnt FROM Users WHERE Role='customer' AND markasdeleted=0 AND IsActive=1 AND approval_status='approved'");
$total_customers = mysqli_fetch_assoc($cust_res)['cnt'] ?? 0;

// 2. Active Products
$prod_res = mysqli_query($conn, "SELECT COUNT(*) as cnt FROM Products WHERE markasdeleted=0 AND StockQuantity > 0");
$active_products = mysqli_fetch_assoc($prod_res)['cnt'] ?? 0;

// 3. Pending Orders (overall in system to be processed)
$order_res = mysqli_query($conn, "SELECT COUNT(*) as cnt FROM Orders WHERE OrderStatus IN ('pending', 'processing') AND markasdeleted=0");
$pending_orders = mysqli_fetch_assoc($order_res)['cnt'] ?? 0;

// 4. Low Stock Products
$low_stock_res = mysqli_query($conn, "SELECT COUNT(*) as cnt FROM Products WHERE markasdeleted=0 AND StockQuantity > 0 AND StockQuantity <= 10");
$low_stock_products = mysqli_fetch_assoc($low_stock_res)['cnt'] ?? 0;


// ---- RECENT ORDERS ----
// Fetch latest 5 orders with their products and user details
$recent_orders = [];
$recent_query = "
    SELECT o.OrderID, o.OrderDate, o.TotalAmount, o.OrderStatus, u.UserName as CustomerName, p.ProductName
    FROM Orders o
    JOIN Users u ON o.UserID = u.UserID
    JOIN Products p ON o.ProductID = p.ProductID
    WHERE o.markasdeleted=0
    ORDER BY o.OrderDate DESC LIMIT 5
";
$ro_res = mysqli_query($conn, $recent_query);
if ($ro_res) {
    while($row = mysqli_fetch_assoc($ro_res)) {
        $recent_orders[] = $row;
    }
}

// ---- LOW STOCK PRODUCTS ALERTS ----
// Fetch items running low to push for sales
$low_stock_items = [];
$ls_query = "SELECT ProductID, ProductName, StockQuantity, Price FROM Products WHERE markasdeleted=0 AND StockQuantity > 0 AND StockQuantity <= 15 ORDER BY StockQuantity ASC LIMIT 4";
$ls_res = mysqli_query($conn, $ls_query);
if ($ls_res) {
    while($row = mysqli_fetch_assoc($ls_res)) {
        $low_stock_items[] = $row;
    }
}

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Sales Dashboard - SupplyNet</title>
    <!-- Fonts -->
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    <!-- Bootstrap CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <!-- FontAwesome -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <!-- Chart.js -->
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>

    <style>
        :root {
            --primary-color: #36b9cc; /* Use teal for salesman panel to differentiate from Admin's blue */
            --secondary-color: #f8f9fc;
            --info-color: #36b9cc;
            --success-color: #1cc88a;
            --warning-color: #f6c23e;
            --danger-color: #e74a3b;
            --dark-color: #1f2937;
            --sidebar-width: 250px;
        }

        body { font-family: 'Inter', sans-serif; background-color: #f8f9fc; overflow-x: hidden; }
        
        /* Sidebar Styling */
        .sidebar { width: var(--sidebar-width); height: 100vh; position: fixed; background: linear-gradient(180deg, #2c9faf 10%, #158596 100%); color: #fff; z-index: 1000; box-shadow: 2px 0 10px rgba(0,0,0,0.1); }
        .sidebar .brand { padding: 1.5rem 1rem; font-size: 1.25rem; font-weight: 800; text-align: center; text-transform: uppercase; letter-spacing: 0.05rem; border-bottom: 1px solid rgba(255,255,255,0.1); display: block; color: #fff; text-decoration: none; }
        .nav-item { padding: 0 1rem; margin-bottom: 0.5rem; }
        .nav-link { color: rgba(255,255,255,0.8); padding: 1rem; border-radius: 0.5rem; font-weight: 600; transition: all 0.3s; }
        .nav-link:hover, .nav-link.active { color: #fff; background: rgba(255,255,255,0.15); transform: translateX(5px); }
        .nav-link i { margin-right: 0.75rem; width: 20px; text-align: center; }

        /* Main Content wrapper */
        .main-wrapper { margin-left: var(--sidebar-width); flex-grow: 1; min-height: 100vh; display: flex; flex-direction: column; }
        
        /* Top Navigation */
        .topbar { background: #fff; height: 70px; box-shadow: 0 0.15rem 1.75rem 0 rgba(58, 59, 69, 0.15); display: flex; align-items: center; justify-content: space-between; padding: 0 2rem; }
        .topbar-user { font-weight: 600; color: #5a5c69; display: flex; align-items: center; gap: 0.5rem; }
        .topbar-user img { width: 35px; height: 35px; border-radius: 50%; border: 2px solid var(--primary-color); }

        /* Dashboard Cards */
        .dashboard-content { padding: 1.5rem 2rem; }
        .card-custom { border: none; border-radius: 0.75rem; box-shadow: 0 0.15rem 1.75rem 0 rgba(58, 59, 69, 0.1); transition: transform 0.2s; background: #fff; }
        .card-custom:hover { transform: translateY(-3px); }
        .border-left-primary { border-left: 0.25rem solid var(--primary-color) !important; }
        .border-left-success { border-left: 0.25rem solid var(--success-color) !important; }
        .border-left-info { border-left: 0.25rem solid #4e73df !important; }
        .border-left-warning { border-left: 0.25rem solid var(--warning-color) !important; }
        
        .card-title { font-size: 0.75rem; font-weight: 700; text-transform: uppercase; letter-spacing: 0.05rem; margin-bottom: 0.25rem; }
        .card-metric { font-size: 1.5rem; font-weight: 800; color: #5a5c69; margin: 0; }
        .card-icon { font-size: 2rem; color: #dddfeb; }

        /* List styling */
        .list-group-item-custom { border-left: none; border-right: none; border-radius: 0; padding: 1rem 1.25rem; }
        .list-group-item-custom:first-child { border-top: none; }
        .list-group-item-custom:last-child { border-bottom: none; }
        
        .badge-status { font-weight: 600; padding: 0.35em 0.65em; font-size: 0.75em; border-radius: 0.25rem; }
    
        @media (max-width: 768px) {
            .sidebar { transform: translateX(-100%); transition: transform 0.3s ease-in-out; }
            .sidebar.show-sidebar { transform: translateX(0); }
            .main-wrapper { margin-left: 0; width: 100%; }
            .sidebar-overlay { display: none; position: fixed; top: 0; left: 0; right: 0; bottom: 0; background: rgba(0,0,0,0.5); z-index: 999; cursor: pointer; }
            .sidebar-overlay.show { display: block; }
            .topbar { padding: 0 1rem; }
            .dashboard-content { padding: 1rem; }
            .card-metric { font-size: 1.25rem; }
        }
</style>
</head>
<body>

<!-- Sidebar -->
<div class="sidebar">
    <a href="index.php" class="brand"><i class="fas fa-cubes me-2"></i>SupplyNet<br><small class="text-white-50" style="font-size: 0.7rem;">Sales Panel</small></a>
    <div class="mt-4">
        <div class="nav-item"><a href="index.php" class="nav-link active"><i class="fas fa-home"></i> Sales Dashboard</a></div>
        <div class="nav-item"><a href="manageCustomer.php" class="nav-link"><i class="fas fa-address-book"></i> Customers List</a></div>
        <div class="nav-item"><a href="placeOrder.php" class="nav-link"><i class="fas fa-cart-plus"></i> Place Order</a></div>
        <div class="nav-item"><a href="viewOrderHistory.php" class="nav-link"><i class="fas fa-history"></i> Order History</a></div>
    </div>
</div>


<div class="sidebar-overlay" id="sidebarOverlay"></div>
<div class="main-wrapper">
    <!-- Topbar -->
    <div class="topbar">
        <div class="d-flex align-items-center">
            <button class="btn btn-link d-md-none text-dark me-2 px-0" id="sidebarToggle"><i class="fas fa-bars fs-5"></i></button>
            <div class="ms-2 ms-md-0">
                <h4 class="m-0 fw-bold text-dark fs-5 fs-md-4">Welcome, <?php echo htmlspecialchars($salesman_name); ?>!</h4>
                <p class="text-muted small mb-0 d-none d-md-block">Here's what's happening in your sales territory today.</p>
            </div>
        </div>
        <div class="d-flex align-items-center gap-3 gap-md-4">
            <a href="#" class="text-secondary position-relative d-none d-sm-block"><i class="fas fa-envelope fs-5"></i></a>
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
                <a class="text-decoration-none topbar-user dropdown-toggle d-flex align-items-center" href="#" role="button" data-bs-toggle="dropdown">
                    <span class="d-none d-lg-inline me-2"><?php echo htmlspecialchars($salesman_name); ?></span>
                    <img src="https://ui-avatars.com/api/?name=<?php echo urlencode($salesman_name); ?>&background=36b9cc&color=fff" alt="Salesman Profile">
                </a>
                <ul class="dropdown-menu dropdown-menu-end shadow border-0 mt-2">
                    <li><a class="dropdown-item" href="profile.php"><i class="fas fa-user fa-sm fa-fw me-2 text-gray-400"></i> Profile</a></li>
                    <li><hr class="dropdown-divider"></li>
                    <li><a class="dropdown-item text-danger" href="../logout.php"><i class="fas fa-sign-out-alt fa-sm fa-fw me-2"></i> Logout</a></li>
                </ul>
            </div>
        </div>
    </div>

    <!-- Main Content -->
    <div class="dashboard-content">
        <!-- Quick Actions Row -->
        <div class="row mb-4">
            <div class="col-12">
                <div class="d-flex flex-wrap gap-2 gap-md-3">
                    <a href="placeOrder.php" class="btn btn-primary d-flex align-items-center flex-grow-1 flex-md-grow-0 justify-content-center" style="background-color: var(--primary-color); border:none;"><i class="fas fa-plus-circle me-2"></i> New Order</a>
                    <a href="manageCustomer.php" class="btn btn-outline-secondary bg-white d-flex align-items-center flex-grow-1 flex-md-grow-0 justify-content-center"><i class="fas fa-user-plus me-2"></i> New Client</a>
                </div>
            </div>
        </div>

        <!-- Metrics Row -->
        <div class="row g-4 mb-4">
            <!-- Total Customers -->
            <div class="col-xl-3 col-md-6">
                <div class="card card-custom border-left-info h-100 py-2">
                    <div class="card-body">
                        <div class="row align-items-center">
                            <div class="col">
                                <div class="card-title text-primary" style="color: #4e73df !important;">Active Clients</div>
                                <div class="card-metric"><?php echo $total_customers; ?></div>
                            </div>
                            <div class="col-auto"><i class="fas fa-users card-icon"></i></div>
                        </div>
                    </div>
                </div>
            </div>
            <!-- Active Products -->
            <div class="col-xl-3 col-md-6">
                <div class="card card-custom border-left-success h-100 py-2">
                    <div class="card-body">
                        <div class="row align-items-center">
                            <div class="col">
                                <div class="card-title text-success">Products Available</div>
                                <div class="card-metric"><?php echo $active_products; ?></div>
                            </div>
                            <div class="col-auto"><i class="fas fa-box-open card-icon"></i></div>
                        </div>
                    </div>
                </div>
            </div>
            <!-- Pending Orders -->
            <div class="col-xl-3 col-md-6">
                <div class="card card-custom border-left-warning h-100 py-2">
                    <div class="card-body">
                        <div class="row align-items-center">
                            <div class="col">
                                <div class="card-title text-warning">Pending Orders</div>
                                <div class="card-metric"><?php echo $pending_orders; ?></div>
                            </div>
                            <div class="col-auto"><i class="fas fa-clipboard-list card-icon"></i></div>
                        </div>
                    </div>
                </div>
            </div>
            <!-- Low Stock -->
            <div class="col-xl-3 col-md-6">
                <div class="card card-custom border-left-primary h-100 py-2" style="border-left-color: var(--danger-color) !important;">
                    <div class="card-body">
                        <div class="row align-items-center">
                            <div class="col">
                                <div class="card-title text-danger">Low Stock Items</div>
                                <div class="card-metric"><?php echo $low_stock_products; ?></div>
                            </div>
                            <div class="col-auto"><i class="fas fa-exclamation-triangle card-icon"></i></div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Main Content Row -->
        <div class="row g-4 mb-4">
            
            <!-- Recent Orders Data Table -->
            <div class="col-xl-8 col-lg-7">
                <div class="card card-custom h-100">
                    <div class="card-header bg-white border-bottom py-3 d-flex flex-row align-items-center justify-content-between">
                        <h6 class="m-0 font-weight-bold text-dark" style="color: var(--primary-color) !important;"><i class="fas fa-history me-2"></i>Recent System Orders</h6>
                        <a href="viewOrderHistory.php" class="btn btn-sm btn-outline-secondary">View All</a>
                    </div>
                    <div class="card-body p-0">
                        <div class="table-responsive">
                            <table class="table table-hover align-middle mb-0 table-responsive-stack">
                                <thead class="table-light text-muted small">
                                    <tr>
                                        <th class="ps-4">Order ID</th>
                                        <th>Date</th>
                                        <th>Client</th>
                                        <th>Product</th>
                                        <th>Amount</th>
                                        <th>Status</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php if(empty($recent_orders)): ?>
                                        <tr><td colspan="6" class="text-center py-4 text-muted">No recent orders found.</td></tr>
                                    <?php else: ?>
                                        <?php foreach($recent_orders as $o): ?>
                                            <tr>
                                                <td class="ps-4 fw-bold text-dark" data-label="Order ID">#<?php echo str_pad($o['OrderID'], 5, '0', STR_PAD_LEFT); ?></td>
                                                <td data-label="Date"><span class="text-muted small"><?php echo date('M d, Y', strtotime($o['OrderDate'])); ?></span></td>
                                                <td class="fw-semibold text-dark" data-label="Client"><?php echo htmlspecialchars($o['CustomerName']); ?></td>
                                                <td data-label="Product"><span class="text-truncate d-inline-block" style="max-width: 150px;"><?php echo htmlspecialchars($o['ProductName']); ?></span></td>
                                                <td class="fw-bold" data-label="Amount">₹<?php echo number_format($o['TotalAmount'], 2); ?></td>
                                                <td data-label="Status">
                                                    <?php 
                                                    $statusClasses = [
                                                        'pending' => 'bg-warning text-dark',
                                                        'processing' => 'bg-info text-white',
                                                        'shipped' => 'bg-primary text-white',
                                                        'delivered' => 'bg-success text-white',
                                                        'cancelled' => 'bg-danger text-white'
                                                    ];
                                                    $s_class = $statusClasses[strtolower($o['OrderStatus'])] ?? 'bg-secondary text-white';
                                                    ?>
                                                    <span class="badge badge-status <?php echo $s_class; ?>"><?php echo ucfirst($o['OrderStatus']); ?></span>
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
            
            <!-- Target / Recommended Products -->
            <div class="col-xl-4 col-lg-5">
                <div class="card card-custom h-100">
                    <div class="card-header bg-white border-bottom py-3">
                        <h6 class="m-0 font-weight-bold" style="color: var(--danger-color) !important;"><i class="fas fa-fire-alt me-2"></i>Push for Sale (Low Stock)</h6>
                    </div>
                    <div class="card-body p-0">
                        <ul class="list-group list-group-flush">
                            <?php if(empty($low_stock_items)): ?>
                                <li class="list-group-item list-group-item-custom text-center text-muted py-4">Inventory levels are healthy!</li>
                            <?php else: ?>
                                <?php foreach($low_stock_items as $item): ?>
                                    <li class="list-group-item list-group-item-custom d-flex justify-content-between align-items-center">
                                        <div>
                                            <h6 class="mb-1 fw-bold text-dark"><?php echo htmlspecialchars($item['ProductName']); ?></h6>
                                            <small class="text-muted">Unit Price: ₹<?php echo number_format($item['Price'], 2); ?></small>
                                        </div>
                                        <div class="text-end">
                                            <span class="badge bg-danger rounded-pill fs-6"><?php echo $item['StockQuantity']; ?> left</span>
                                        </div>
                                    </li>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </ul>
                    </div>
                    <div class="card-footer bg-white text-center border-top">
                        <a href="placeOrder.php" class="text-decoration-none text-info fw-bold small">Create Order for these Items <i class="fas fa-arrow-right ms-1"></i></a>
                    </div>
                </div>
            </div>

        </div>
    </div>
    <?php include '../config/footer.php'; ?>
</div>

<!-- Bootstrap JS -->
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>

<script>
    document.addEventListener('DOMContentLoaded', function() {
        var sidebarToggle = document.getElementById('sidebarToggle');
        var sidebar = document.querySelector('.sidebar');
        var sidebarOverlay = document.getElementById('sidebarOverlay');
        
        if(sidebarToggle && sidebar && sidebarOverlay) {
            sidebarToggle.addEventListener('click', function() {
                sidebar.classList.toggle('show-sidebar');
                sidebarOverlay.classList.toggle('show');
            });
            sidebarOverlay.addEventListener('click', function() {
                sidebar.classList.remove('show-sidebar');
                sidebarOverlay.classList.remove('show');
            });
        }
    });
</script>
</body>
</html>
