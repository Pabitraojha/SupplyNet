<?php
session_start();
require_once '../config/db.php';

if (!isset($_SESSION['UserID']) || strtolower($_SESSION['Role']) !== 'employee') {
    header("Location: ../login.php");
    exit();
}

$display_name = $_SESSION['UserName'] ?? 'Employee';
$message = '';
$message_type = '';

// Handle Order Approval/Rejection (Single)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_GET['action']) && isset($_GET['id'])) {
    $order_id = intval($_GET['id']);
    
    if ($_GET['action'] === 'single_approve') {
        $stmt = $conn->prepare("UPDATE Orders SET OrderStatus = 'processing' WHERE OrderID = ? AND OrderStatus = 'pending'");
        $stmt->bind_param("i", $order_id);
        if ($stmt->execute() && $stmt->affected_rows > 0) {
            $message = "Order #$order_id has been approved and is now Processing.";
            $message_type = "success";
        } else {
            $message = "Error approving order or order is no longer pending.";
            $message_type = "danger";
        }
    } elseif ($_GET['action'] === 'single_reject') {
        $stmt = $conn->prepare("UPDATE Orders SET OrderStatus = 'cancelled' WHERE OrderID = ? AND OrderStatus = 'pending'");
        $stmt->bind_param("i", $order_id);
        if ($stmt->execute() && $stmt->affected_rows > 0) {
            $message = "Order #$order_id has been rejected and Cancelled.";
            $message_type = "warning";
        } else {
            $message = "Error rejecting order or order is no longer pending.";
            $message_type = "danger";
        }
    }
} elseif ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['bulk_action'])) {
    if (!empty($_POST['selected_orders'])) {
        $ids = array_map('intval', $_POST['selected_orders']);
        $id_str = implode(',', $ids);
        if ($_POST['bulk_action'] === 'bulk_approve') {
            $conn->query("UPDATE Orders SET OrderStatus = 'processing' WHERE OrderID IN ($id_str) AND OrderStatus = 'pending'");
            $message = "Selected orders have been bulk approved.";
            $message_type = "success";
        } elseif ($_POST['bulk_action'] === 'bulk_reject') {
            $conn->query("UPDATE Orders SET OrderStatus = 'cancelled' WHERE OrderID IN ($id_str) AND OrderStatus = 'pending'");
            $message = "Selected orders have been bulk rejected.";
            $message_type = "warning";
        }
    } else {
        $message = "No orders were selected for bulk action.";
        $message_type = "warning";
    }
}

// Search Filter
$search_query = "";
$search_sql = "";
if (isset($_GET['search']) && !empty(trim($_GET['search']))) {
    $search_query = trim($_GET['search']);
    $escaped_search = "%" . $conn->real_escape_string($search_query) . "%";
    $search_sql = " AND (o.OrderID LIKE '$escaped_search' OR u.UserName LIKE '$escaped_search' OR o.OrderStatus LIKE '$escaped_search') ";
}

$start_date = isset($_GET['start_date']) ? trim($_GET['start_date']) : '';
$end_date = isset($_GET['end_date']) ? trim($_GET['end_date']) : '';

if (!empty($start_date)) {
    $search_sql .= " AND DATE(o.OrderDate) >= '" . $conn->real_escape_string($start_date) . "' ";
}
if (!empty($end_date)) {
    $search_sql .= " AND DATE(o.OrderDate) <= '" . $conn->real_escape_string($end_date) . "' ";
}

// Fetch all orders with Total Quantity
$orders = [];
$res = $conn->query("
    SELECT o.OrderID, o.OrderDate, o.TotalAmount, o.OrderStatus, o.RequiredDate, u.UserName as CustomerName, 
           IFNULL(SUM(od.Quantity), 0) as TotalQuantity
    FROM Orders o
    JOIN Users u ON o.UserID = u.UserID
    LEFT JOIN OrderDetails od ON o.OrderID = od.OrderID
    WHERE o.markasdeleted = 0 AND o.OrderStatus = 'pending' $search_sql
    GROUP BY o.OrderID
    ORDER BY o.OrderDate DESC
");
if ($res) {
    while($row = $res->fetch_assoc()) {
        $orders[] = $row;
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Manage Orders - SupplyNet</title>
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
            --dark-color: #1f2937;
            --sidebar-width: 250px;
        }
        body { font-family: 'Inter', sans-serif; background-color: var(--secondary-color); overflow-x: hidden; }
        
        .sidebar { width: var(--sidebar-width); height: 100vh; position: fixed; background: linear-gradient(180deg, #1cc88a 10%, #13855c 100%); color: #fff; z-index: 1000; box-shadow: 2px 0 10px rgba(0,0,0,0.1); }
        .sidebar .brand { padding: 1.5rem 1rem; font-size: 1.25rem; font-weight: 800; text-align: center; text-transform: uppercase; letter-spacing: 0.05rem; border-bottom: 1px solid rgba(255,255,255,0.1); display: block; color: #fff; text-decoration: none; }
        .nav-item { padding: 0 1rem; margin-bottom: 0.5rem; }
        .nav-link { color: rgba(255,255,255,0.8); padding: 1rem; border-radius: 0.5rem; font-weight: 600; transition: all 0.3s; text-decoration: none;}
        .nav-link:hover, .nav-link.active { color: #fff; background: rgba(255,255,255,0.15); transform: translateX(5px); }
        .nav-link i { margin-right: 0.75rem; width: 20px; text-align: center; }

        .main-wrapper { margin-left: var(--sidebar-width); flex-grow: 1; min-height: 100vh; display: flex; flex-direction: column; }
        .topbar { background: #fff; height: 70px; box-shadow: 0 0.15rem 1.75rem 0 rgba(58, 59, 69, 0.15); display: flex; align-items: center; justify-content: space-between; padding: 0 2rem; }
        .topbar-user { font-weight: 600; color: #5a5c69; display: flex; align-items: center; gap: 0.5rem; text-decoration: none; }
        .topbar-user img { width: 35px; height: 35px; border-radius: 50%; border: 2px solid var(--primary-color); }

        .dashboard-content { padding: 1.5rem 2rem; }
        .card-custom { border: none; border-radius: 0.75rem; box-shadow: 0 0.15rem 1.75rem 0 rgba(58, 59, 69, 0.1); background: #fff; }
        
        .badge-status { font-weight: 600; padding: 0.4em 0.75em; border-radius: 0.35rem; font-size: 0.8em; }

        .badge-status { font-weight: 600; padding: 0.4em 0.75em; border-radius: 0.35rem; font-size: 0.8em; }

        @media (max-width: 768px) {
            .sidebar { transform: translateX(-100%); transition: transform 0.3s ease-in-out; }
            .sidebar.show-sidebar { transform: translateX(0); }
            .main-wrapper { margin-left: 0; width: 100%; }
            .sidebar-overlay { display: none; position: fixed; top: 0; left: 0; right: 0; bottom: 0; background: rgba(0,0,0,0.5); z-index: 999; cursor: pointer; }
            .sidebar-overlay.show { display: block; }
            .topbar { padding: 0 1rem; }
            .dashboard-content { padding: 1rem; }
        }
    </style>
</head>
<body>

<div class="sidebar">
    <a href="index.php" class="brand"><i class="fas fa-cubes me-2"></i>SupplyNet<br><small class="text-white-50" style="font-size: 0.7rem;">Employee Panel</small></a>
    <div class="mt-4">
        <div class="nav-item"><a href="index.php" class="nav-link"><i class="fas fa-tachometer-alt"></i> Dashboard</a></div>
        <div class="nav-item"><a href="manageOrder.php" class="nav-link active"><i class="fas fa-tasks"></i> Manage Orders</a></div>
        <div class="nav-item"><a href="viewOrderHistory.php" class="nav-link"><i class="fas fa-history"></i> Order History</a></div>
        <div class="nav-item"><a href="assignOrder.php" class="nav-link"><i class="fas fa-clipboard-check"></i> Assign Orders</a></div>
        <!-- Dropped Route Assign as we merge to Assign Order, but keep link to not break template if user clicks it -->
        <div class="nav-item"><a href="routeAssign.php" class="nav-link"><i class="fas fa-map-marked-alt"></i> Route Assign</a></div>
    </div>
</div>

<div class="sidebar-overlay" id="sidebarOverlay"></div>
<div class="main-wrapper">
    <div class="topbar">
        <div class="d-flex align-items-center">
            <button class="btn btn-link d-md-none text-dark me-2 px-0" id="sidebarToggle"><i class="fas fa-bars fs-5"></i></button>
            <div class="ms-2 ms-md-0">
                <h4 class="m-0 fw-bold text-dark fs-5 fs-md-4">Manage Orders</h4>
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
        <?php if($message): ?>
            <div class="alert alert-<?php echo $message_type; ?> alert-dismissible fade show shadow-sm">
                <?php echo $message; ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
        <?php endif; ?>

        <div class="card card-custom shadow-sm mb-4">
            <div class="card-header bg-white border-bottom py-3 d-flex justify-content-between align-items-center flex-wrap gap-2">
                <h6 class="m-0 font-weight-bold" style="color: var(--primary-color);">Pending Action Required</h6>
                
                <div class="d-flex align-items-center gap-2 search-container flex-wrap mt-2 mt-md-0">
                    <form method="GET" class="d-flex align-items-center gap-2 mb-0">
                        <input type="date" name="start_date" class="form-control form-control-sm" style="max-width: 130px;" value="<?php echo htmlspecialchars($start_date); ?>" max="<?php echo date('Y-m-d'); ?>" title="Start Date">
                        <span class="text-muted small">to</span>
                        <input type="date" name="end_date" class="form-control form-control-sm" style="max-width: 130px;" value="<?php echo htmlspecialchars($end_date); ?>" max="<?php echo date('Y-m-d'); ?>" title="End Date">
                        <div class="input-group input-group-sm">
                            <input type="text" id="searchInput" class="form-control" name="search" placeholder="Search orders, customers..." value="<?php echo htmlspecialchars($search_query); ?>">
                            <button class="btn btn-primary" type="submit" style="background-color: var(--primary-color); border:none;"><i class="fas fa-search"></i></button>
                            <?php if(!empty($search_query) || !empty($start_date) || !empty($end_date)): ?>
                                <a href="manageOrder.php" class="btn btn-outline-secondary"><i class="fas fa-times"></i></a>
                            <?php endif; ?>
                        </div>
                    </form>
                    <a href="generateReport.php?search=<?php echo urlencode($search_query); ?>" target="_blank" class="btn btn-sm btn-outline-secondary print-btn"><i class="fas fa-print me-1"></i>Report</a>
                </div>
            </div>
            <div class="card-body p-0">
                <form id="bulkForm" method="POST">
                <input type="hidden" name="bulk_action" id="bulk_action_input" value="">
                <div class="table-responsive">
                    <table class="table table-hover align-middle mb-0 table-responsive-stack" id="ordersTable">
                        <thead class="bg-light">
                            <tr>
                                <th class="ps-4" style="width: 40px;"><input type="checkbox" class="form-check-input mt-0" id="selectAll"></th>
                                <th>Order ID</th>
                                <th>Order Log</th>
                                <th>Client Info</th>
                                <th>Quantity</th>
                                <th>Amount</th>
                                <th>Status</th>
                                <th class="pe-4 action-btns text-center">Actions</th>
                            </tr>
                        </thead>
                        <tbody id="dataTableBody">
                            <?php if(empty($orders)): ?>
                                <tr><td colspan="8" class="text-center py-4">No orders found matching the criteria.</td></tr>
                            <?php else: ?>
                                <?php 
                                $grand_total_amount = 0;
                                $grand_total_quantity = 0;
                                foreach($orders as $o): 
                                    $s_classes = [
                                        'pending' => 'bg-warning text-dark',
                                        'processing' => 'bg-info text-dark',
                                        'shipped' => 'bg-primary text-white',
                                        'delivered' => 'bg-success text-white',
                                        'cancelled' => 'bg-danger text-white'
                                    ];
                                    $s_class = $s_classes[strtolower($o['OrderStatus'])] ?? 'bg-secondary text-white';
                                    $is_pending = (strtolower($o['OrderStatus']) === 'pending');
                                    
                                    $grand_total_amount += $o['TotalAmount'];
                                    $grand_total_quantity += $o['TotalQuantity'];
                                ?>
                                    <tr>
                                        <td class="ps-3" data-label="Selection">
                                            <?php if($is_pending): ?>
                                                <input type="checkbox" name="selected_orders[]" class="form-check-input order-chk mt-0" value="<?php echo $o['OrderID']; ?>">
                                            <?php else: ?>
                                                <input type="checkbox" class="form-check-input mt-0" disabled>
                                            <?php endif; ?>
                                        </td>
                                        <td data-label="Order ID" class="fw-bold">#<?php echo $o['OrderID']; ?></td>
                                        <td data-label="Order Log">
                                            <div class="text-muted"><i class="fas fa-clock me-1 opacity-50"></i><?php echo date('M d, Y', strtotime($o['OrderDate'])); ?></div>
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
                                        <td data-label="Client Info"><i class="fas fa-user-circle text-muted me-1"></i><?php echo htmlspecialchars($o['CustomerName']); ?></td>
                                        <td data-label="Quantity"><span class="badge bg-secondary"><?php echo $o['TotalQuantity']; ?> Items</span></td>
                                        <td data-label="Amount" class="fw-semibold text-success">₹<?php echo number_format($o['TotalAmount'], 2); ?></td>
                                        <td data-label="Status"><span class="badge badge-status <?php echo $s_class; ?>"><?php echo ucfirst($o['OrderStatus']); ?></span></td>
                                        <td data-label="Actions" class="pe-4 action-btns text-center">
                                            <?php if($is_pending): ?>
                                                <div class="d-flex justify-content-center gap-1">
                                                    <button type="submit" formmethod="POST" formaction="manageOrder.php?action=single_approve&id=<?php echo $o['OrderID']; ?>" class="btn btn-sm btn-success shadow-sm" title="Approve"><i class="fas fa-check"></i></button>
                                                    <button type="submit" formmethod="POST" formaction="manageOrder.php?action=single_reject&id=<?php echo $o['OrderID']; ?>" class="btn btn-sm btn-danger shadow-sm" title="Reject"><i class="fas fa-times"></i></button>
                                                </div>
                                            <?php else: ?>
                                                <span class="text-muted small fst-italic">Actioned</span>
                                            <?php endif; ?>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </tbody>
                        <?php if(!empty($orders)): ?>
                        <tfoot class="bg-light fw-bold">
                            <tr>
                                <td colspan="4" class="text-end text-dark pe-3 pt-3">Grand Totals Summary:</td>
                                <td class="text-dark pt-3"><span class="badge bg-dark"><?php echo $grand_total_quantity; ?> Items</span></td>
                                <td class="text-success fs-6 pt-3">₹<?php echo number_format($grand_total_amount, 2); ?></td>
                                <td colspan="2" class="text-center">
                                    <button type="button" class="btn btn-sm btn-success shadow-sm" onclick="submitBulk('bulk_approve')" title="Bulk Approve Selected"><i class="fas fa-check-double"></i></button>
                                    <button type="button" class="btn btn-sm btn-danger shadow-sm ms-1" onclick="submitBulk('bulk_reject')" title="Bulk Reject Selected"><i class="fas fa-ban"></i></button>
                                </td>
                            </tr>
                        </tfoot>
                        <?php endif; ?>
                    </table>
                </div>
                </form>
            </div>
        </div>
    </div>
    <?php include '../config/footer.php'; ?>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
<script>
    document.getElementById('sidebarToggle').addEventListener('click', function() {
        document.querySelector('.sidebar').classList.add('show-sidebar');
        document.getElementById('sidebarOverlay').classList.add('show');
    });
    document.getElementById('sidebarOverlay').addEventListener('click', function() {
        document.querySelector('.sidebar').classList.remove('show-sidebar');
        this.classList.remove('show');
    });

    // Checkbox toggle logic
    const selectAll = document.getElementById('selectAll');
    if (selectAll) {
        selectAll.addEventListener('change', function() {
            const checkboxes = document.querySelectorAll('.order-chk');
            checkboxes.forEach(chk => chk.checked = this.checked);
        });
    }

    function submitBulk(actionName) {
        const checked = document.querySelectorAll('.order-chk:checked');
        if (checked.length === 0) {
            alert('Please select at least one pending order.');
            return;
        }
        if (confirm(`Are you sure you want to ${actionName.replace('_', ' ')} ${checked.length} selected orders?`)) {
            document.getElementById('bulk_action_input').value = actionName;
            document.getElementById('bulkForm').submit();
        }
    }

    // Dynamic Search Script
    document.getElementById('searchInput').addEventListener('keyup', function() {
        let filter = this.value.toLowerCase().trim();
        let rows = document.getElementById('dataTableBody').getElementsByTagName('tr');
        
        for(let i=0; i<rows.length; i++) {
            if(rows[i].cells.length === 1) continue; // Skip 'no data' row
            let text = rows[i].textContent.toLowerCase();
            rows[i].style.display = text.includes(filter) ? '' : 'none';
        }
    });
</script>
</body>
</html>
