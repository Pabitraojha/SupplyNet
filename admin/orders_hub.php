<?php
session_start();
require_once '../config/db.php';

// Auth Protection
if (!isset($_SESSION['UserID']) || strtolower($_SESSION['Role']) !== 'admin') {
    header("Location: ../login.php");
    exit();
}

$admin_name = $_SESSION['UserName'] ?? 'Administrator';
$admin_id = $_SESSION['UserID'];
$message = '';

// Check and Alter DB for new columns safely
$check_col = $conn->query("SHOW COLUMNS FROM Orders LIKE 'PlacedBy'");
if ($check_col && $check_col->num_rows == 0) {
    $conn->query("ALTER TABLE Orders ADD COLUMN PlacedBy INT DEFAULT NULL");
    $conn->query("ALTER TABLE Orders ADD COLUMN ApprovedBy INT DEFAULT NULL");
}

// Handle Order Update Actions (e.g. Approve/Process)
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['action'])) {
    $order_id = intval($_POST['order_id'] ?? 0);
    $new_status = $_POST['new_status'] ?? '';
    
    if ($order_id > 0 && in_array($new_status, ['pending', 'processing', 'shipped', 'delivered', 'cancelled'])) {
        
        $update_query = "UPDATE Orders SET OrderStatus=? ";
        $params = [$new_status];
        $types = "s";
        
        // If moving out of pending for the first time, record WHO approved it
        if ($new_status != 'pending' && $new_status != 'cancelled') {
            $update_query .= ", ApprovedBy=? ";
            $params[] = $admin_id;
            $types .= "i";
        }
        
        $update_query .= " WHERE OrderID=? AND markasdeleted=0";
        $params[] = $order_id;
        $types .= "i";
        
        $stmt = $conn->prepare($update_query);
        $stmt->bind_param($types, ...$params);
        if($stmt->execute()) {
            $message = '<div class="alert alert-success"><i class="fas fa-check-circle me-2"></i>Order #'.str_pad($order_id, 4, '0', STR_PAD_LEFT).' status updated to '.ucfirst($new_status).'.</div>';
        } else {
             $message = '<div class="alert alert-danger"><i class="fas fa-times-circle me-2"></i>Failed to update order status.</div>';
        }
    } elseif ($_POST['action'] === 'send_reminder') {
        // Handle sending reminder
        $notif_msg = "URGENT: Priority reminder for overdue Order #" . str_pad($order_id, 4, '0', STR_PAD_LEFT);
        addNotification($conn, $notif_msg, null, 'employee');
        
        $message = '<div class="alert alert-success shadow-sm"><i class="fas fa-bell me-2"></i>Automated priority reminder successfully broadcasted to all active Employee channels for Order #'.str_pad($order_id, 4, '0', STR_PAD_LEFT).' because it is marked strictly Overdue.</div>';
    }
}

// Fetch Orders By Date Range
$start_date = $_GET['start_date'] ?? date('Y-m-d');
$end_date = $_GET['end_date'] ?? date('Y-m-d');
$search_term = trim($_GET['search'] ?? '');

$query = "
    SELECT o.OrderID, o.OrderDate, o.TotalAmount, o.OrderStatus, o.RequiredDate,
           u_cust.UserName as CustomerName,
           p_prod.ProductName,
           u_placer.UserName as PlacedByName,
           u_approver.UserName as ApprovedByName
    FROM Orders o
    JOIN Users u_cust ON o.UserID = u_cust.UserID
    JOIN Products p_prod ON o.ProductID = p_prod.ProductID
    LEFT JOIN Users u_placer ON o.PlacedBy = u_placer.UserID
    LEFT JOIN Users u_approver ON o.ApprovedBy = u_approver.UserID
    WHERE o.markasdeleted=0
";

if (!empty($start_date)) {
    $query .= " AND DATE(o.OrderDate) >= '" . $conn->real_escape_string($start_date) . "' ";
}
if (!empty($end_date)) {
    $query .= " AND DATE(o.OrderDate) <= '" . $conn->real_escape_string($end_date) . "' ";
}

if (!empty($search_term)) {
    $query .= " AND (u_cust.UserName LIKE '%".$conn->real_escape_string($search_term)."%' OR p_prod.ProductName LIKE '%".$conn->real_escape_string($search_term)."%') ";
}

$query .= " ORDER BY o.OrderDate DESC";

$stmt = $conn->prepare($query);
$stmt->execute();
$res = $stmt->get_result();

$orders = [];
$total_daily_revenue = 0;
while($row = $res->fetch_assoc()) {
    $orders[] = $row;
    if ($row['OrderStatus'] != 'cancelled') {
        $total_daily_revenue += $row['TotalAmount'];
    }
}
$stmt->close();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Orders Hub - SupplyNet Admin</title>
    <!-- Fonts -->
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    <!-- Bootstrap CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">

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

        body { font-family: 'Inter', sans-serif; background-color: #f8f9fc; overflow-x: hidden; }
        
        /* Sidebar Styling */
        .sidebar { width: var(--sidebar-width); height: 100vh; position: fixed; background: var(--primary-color); background: linear-gradient(180deg, #4e73df 10%, #224abe 100%); color: #fff; z-index: 1000; box-shadow: 2px 0 10px rgba(0,0,0,0.1); }
        .sidebar .brand { padding: 1.5rem 1rem; font-size: 1.25rem; font-weight: 800; text-align: center; text-transform: uppercase; letter-spacing: 0.05rem; border-bottom: 1px solid rgba(255,255,255,0.1); display: block; color: #fff; text-decoration: none; }
        .nav-item { padding: 0 1rem; margin-bottom: 0.5rem; }
        .nav-link { color: rgba(255,255,255,0.8); padding: 1rem; border-radius: 0.5rem; font-weight: 600; transition: all 0.3s; }
        .nav-link:hover, .nav-link.active { color: #fff; background: rgba(255,255,255,0.15); transform: translateX(5px); }
        .nav-link i { margin-right: 0.75rem; width: 20px; text-align: center; }

        /* Main Content */
        .main-wrapper { margin-left: var(--sidebar-width); flex-grow: 1; min-height: 100vh; display: flex; flex-direction: column; }
        
        /* Top Navigation */
        .topbar { background: #fff; height: 70px; box-shadow: 0 0.15rem 1.75rem 0 rgba(58, 59, 69, 0.15); display: flex; align-items: center; justify-content: space-between; padding: 0 2rem; }
        .topbar-user { font-weight: 600; color: #5a5c69; display: flex; align-items: center; gap: 0.5rem; }
        .topbar-user img { width: 35px; height: 35px; border-radius: 50%; border: 2px solid var(--primary-color); }

        /* Dashboard Content */
        .dashboard-content { padding: 1.5rem 2rem; }
        .card-custom { border: none; border-radius: 0.75rem; box-shadow: 0 0.15rem 1.75rem 0 rgba(58, 59, 69, 0.1); margin-bottom: 1.5rem; }
        
        /* Date Picker */
        .date-picker-form { background: white; padding: 1.5rem; border-radius: 0.75rem; box-shadow: 0 0.15rem 1.75rem 0 rgba(58, 59, 69, 0.1); margin-bottom: 1.5rem; }

        .order-meta-badge { font-size: 0.75rem; font-weight: 600; padding: 0.35em 0.65em; border-radius: 0.25rem; }
        .status-select { font-size: 0.85rem; padding: 0.3rem 2rem 0.3rem 0.75rem; border-radius: 0.4rem; font-weight: 600;}
    
        @media (max-width: 768px) {
            .sidebar { transform: translateX(-100%); transition: transform 0.3s ease-in-out; }
            .sidebar.show-sidebar { transform: translateX(0); }
            .main-wrapper { margin-left: 0; width: 100%; }
            .sidebar-overlay { display: none; position: fixed; top: 0; left: 0; right: 0; bottom: 0; background: rgba(0,0,0,0.5); z-index: 999; cursor: pointer; }
            .sidebar-overlay.show { display: block; }
            
            .dashboard-content { padding: 1rem; }
            .topbar { padding: 0 1rem; }
            
            .date-picker-form { flex-direction: column; align-items: stretch !important; text-align: center; }
            .date-picker-form form { flex-direction: column; width: 100%; }
            .date-picker-form .text-end { text-align: center !important; margin-top: 1rem; border-top: 1px solid #eee; padding-top: 1rem; }
            
            /* Table Stacking on Mobile */
            .table-responsive-stack thead { display: none; }
            .table-responsive-stack tr { display: block; margin-bottom: 1.5rem; border: 1px solid #e3e6f0; border-radius: 0.75rem; background: #fff; box-shadow: 0 0.125rem 0.25rem rgba(0,0,0,0.075); overflow: hidden; }
            .table-responsive-stack td { display: block; text-align: left; border: none; padding: 1rem 1.25rem !important; position: relative; }
            .table-responsive-stack td::before { content: attr(data-label); display: block; font-weight: 800; text-transform: uppercase; font-size: 0.65rem; color: var(--primary-color); margin-bottom: 0.25rem; opacity: 0.8; }
            .table-responsive-stack td:not(:last-child) { border-bottom: 1px solid #f8f9fc; }
            .table-responsive-stack .ps-4 { padding-left: 1.25rem !important; }
        }
</style>
</head>
<body>

<!-- Sidebar -->
<div class="sidebar">
    <a href="index.php" class="brand"><i class="fas fa-cubes me-2"></i>SupplyNet<br><small class="text-white-50" style="font-size: 0.7rem;">Admin Panel</small></a>
    <div class="mt-4">
        <div class="nav-item"><a href="index.php" class="nav-link"><i class="fas fa-tachometer-alt"></i> Dashboard</a></div>
        <div class="nav-item"><a href="user_management.php" class="nav-link"><i class="fas fa-users"></i> User Management</a></div>
        <div class="nav-item"><a href="products_directory.php" class="nav-link"><i class="fas fa-box-open"></i> Products Directory</a></div>
        <div class="nav-item"><a href="orders_hub.php" class="nav-link active"><i class="fas fa-truck-loading"></i> Orders Hub</a></div>
        <div class="nav-item"><a href="delivery_route.php" class="nav-link"><i class="fas fa-route"></i> Deliveries & Routes</a></div>
        <div class="nav-item"><a href="customer_feedback.php" class="nav-link"><i class="fas fa-comments"></i> Customer Feedback</a></div>
        <div class="nav-item"><a href="system_settings.php" class="nav-link"><i class="fas fa-cogs"></i> System Settings</a></div>
    </div>
</div>


<div class="sidebar-overlay" id="sidebarOverlay"></div>
<div class="main-wrapper">
    <!-- Topbar -->
    <div class="topbar">
        <div class="d-flex align-items-center">
            <button class="btn btn-link d-md-none text-dark me-3" id="sidebarToggle"><i class="fas fa-bars"></i></button>
            <h4 class="m-0 fw-bold text-dark">Production Orders Hub</h4>
        </div>
        <div class="d-flex align-items-center gap-4">
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
                    <span class="d-none d-lg-inline"><?php echo htmlspecialchars($admin_name); ?></span>
                    <img src="https://ui-avatars.com/api/?name=<?php echo urlencode($admin_name); ?>&background=4e73df&color=fff" alt="Admin Profile">
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
        <?php echo $message; ?>

        <!-- Date & Metrics Bar -->
        <div class="date-picker-form d-flex flex-wrap align-items-center justify-content-between gap-3">
            <form action="orders_hub.php" method="GET" class="d-flex align-items-center gap-3 w-100 w-lg-auto">
                <div class="flex-grow-1 flex-md-grow-0">
                    <label class="form-label mb-0 fw-bold text-primary small text-uppercase">From</label>
                    <input type="date" name="start_date" class="form-control" value="<?php echo htmlspecialchars($start_date); ?>" onchange="this.form.submit()">
                </div>
                <div class="flex-grow-1 flex-md-grow-0">
                    <label class="form-label mb-0 fw-bold text-primary small text-uppercase">To</label>
                    <input type="date" name="end_date" class="form-control" value="<?php echo htmlspecialchars($end_date); ?>" onchange="this.form.submit()">
                </div>
                <div class="flex-grow-1 flex-md-grow-0" style="min-width: 250px;">
                    <label class="form-label mb-0 fw-bold text-primary small text-uppercase">Search Logs</label>
                    <div class="input-group">
                        <input type="text" name="search" class="form-control" placeholder="Client or Product..." value="<?php echo htmlspecialchars($search_term); ?>">
                        <button class="btn btn-outline-primary" type="submit"><i class="fas fa-search"></i></button>
                    </div>
                </div>
            </form>
            <div class="flex-grow-1 text-end mobile-metrics-summary">
                <div class="text-muted small text-uppercase fw-bold mb-1">Total Period Revenue</div>
                <h3 class="m-0 fw-bold text-success">₹<?php echo number_format($total_daily_revenue, 2); ?></h3>
                <div class="text-muted small"><?php echo count($orders); ?> active orders in period</div>
            </div>
        </div>

        <!-- Orders Table Card -->
        <div class="card card-custom">
            <div class="card-header bg-white border-bottom py-3 d-flex justify-content-between align-items-center">
                <h6 class="m-0 font-weight-bold text-primary" style="font-weight: 700;"><i class="fas fa-clipboard-list me-2"></i>Orders Operating Log</h6>
            </div>
            <div class="card-body p-0">
                <div class="table-responsive">
                    <table class="table table-hover align-middle mb-0 table-responsive-stack">
                        <thead class="table-light">
                            <tr>
                                <th class="ps-4">Order Record</th>
                                <th>Personnel Log</th>
                                <th>Amount</th>
                                <th>Status Action</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if(empty($orders)): ?>
                                <tr><td colspan="4" class="text-center py-5 text-muted"><i class="fas fa-calendar-times d-block fs-1 mb-3 text-black-50 opacity-25"></i>No orders logged on this date.</td></tr>
                            <?php else: ?>
                                <?php foreach($orders as $o): ?>
                                <tr>
                                    <td class="ps-4" data-label="Order Record">
                                        <div class="fw-bold text-dark mb-1">#<?php echo str_pad($o['OrderID'], 5, '0', STR_PAD_LEFT); ?></div>
                                        <div class="text-primary fw-semibold"><i class="fas fa-box-open opacity-50 me-1"></i><?php echo htmlspecialchars($o['ProductName']); ?></div>
                                        <div class="text-muted small"><i class="fas fa-clock opacity-50 me-1"></i><?php echo date('h:i A', strtotime($o['OrderDate'])); ?></div>
                                        <?php if(!empty($o['RequiredDate'])): 
                                            $is_overdue = (strtotime($o['RequiredDate']) < strtotime(date('Y-m-d'))) && !in_array(strtolower($o['OrderStatus']), ['delivered', 'cancelled']);
                                        ?>
                                            <div class="small mt-1 fw-bold <?php echo $is_overdue ? 'text-danger' : 'text-danger'; ?>">
                                                <i class="fas fa-calendar-check me-1"></i>Req: <?php echo date('M d, Y', strtotime($o['RequiredDate'])); ?>
                                                <?php if($is_overdue): ?>
                                                    <i class="fas fa-exclamation-triangle ms-1 fs-5 align-middle" title="CRITICAL: Target Date Exceeded!"></i>
                                                <?php endif; ?>
                                            </div>
                                        <?php endif; ?>
                                    </td>
                                    <td data-label="Personnel Log">
                                        <div class="mb-1">
                                            <span class="text-muted small" style="width: 70px; display:inline-block;">For Client:</span>
                                            <span class="fw-bold text-primary"><?php echo htmlspecialchars($o['CustomerName']); ?></span>
                                        </div>
                                        <div class="mb-1">
                                            <span class="text-muted small" style="width: 70px; display:inline-block;">Placed By:</span>
                                            <span class="badge bg-secondary bg-opacity-10 text-secondary border">
                                                <i class="fas fa-user-tag me-1"></i><?php echo $o['PlacedByName'] ? htmlspecialchars($o['PlacedByName']) : 'Self-Serve (Customer)'; ?>
                                            </span>
                                        </div>
                                        <div>
                                            <span class="text-muted small" style="width: 70px; display:inline-block;">Approver:</span>
                                            <?php if ($o['ApprovedByName']): ?>
                                                <span class="badge bg-success bg-opacity-10 text-success border border-success border-opacity-25">
                                                    <i class="fas fa-check-circle me-1"></i><?php echo htmlspecialchars($o['ApprovedByName']); ?>
                                                </span>
                                            <?php else: ?>
                                                <span class="badge bg-warning bg-opacity-10 text-warning border border-warning border-opacity-25">
                                                    <i class="fas fa-clock me-1"></i>Awaiting Approval
                                                </span>
                                            <?php endif; ?>
                                        </div>
                                    </td>
                                    <td class="fw-bold text-dark" data-label="Amount">₹<?php echo number_format($o['TotalAmount'], 2); ?></td>
                                    <td data-label="Status Action">
                                        <?php 
                                        $curr = strtolower($o['OrderStatus']);
                                        $s_color = 'primary';
                                        if($curr == 'pending') $s_color = 'warning text-dark';
                                        if($curr == 'processing') $s_color = 'info text-dark';
                                        if($curr == 'shipped') $s_color = 'primary text-white';
                                        if($curr == 'delivered') $s_color = 'success text-white';
                                        if($curr == 'cancelled') $s_color = 'danger text-white';
                                        ?>
                                        <div class="d-flex flex-column gap-2">
                                            <span class="badge bg-<?php echo str_replace(' ', ' ', $s_color); ?> fs-6 py-2"><?php echo ucfirst($curr); ?></span>
                                            
                                            <?php 
                                            if(!empty($o['RequiredDate'])) {
                                                $is_overdue = (strtotime($o['RequiredDate']) < strtotime(date('Y-m-d'))) && !in_array($curr, ['delivered', 'cancelled']);
                                                if($is_overdue):
                                            ?>
                                                <form action="orders_hub.php" method="POST" class="m-0 p-0">
                                                    <input type="hidden" name="action" value="send_reminder">
                                                    <input type="hidden" name="order_id" value="<?php echo $o['OrderID']; ?>">
                                                    <button type="submit" class="btn btn-sm btn-danger shadow-sm fw-bold w-100"><i class="fas fa-bell me-1"></i> Send Remind</button>
                                                </form>
                                            <?php 
                                                endif; 
                                            } 
                                            ?>
                                        </div>
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
