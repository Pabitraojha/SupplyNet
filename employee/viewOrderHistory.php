<?php
session_start();
require_once '../config/db.php';

// Auth Protection
if (!isset($_SESSION['UserID']) || strtolower($_SESSION['Role']) !== 'employee') {
    header("Location: ../login.php");
    exit();
}

$display_name = $_SESSION['UserName'] ?? 'Employee';

// Fetch Order History for actioned orders
$orders = [];
$status_filter = isset($_GET['status']) ? strtolower(trim($_GET['status'])) : 'all';

$start_date = isset($_GET['start_date']) ? trim($_GET['start_date']) : '';
$end_date = isset($_GET['end_date']) ? trim($_GET['end_date']) : '';

$query = "
    SELECT o.OrderID, o.OrderDate, o.TotalAmount, o.OrderStatus, o.RequiredDate, u.UserName as CustomerName,
           (SELECT GROUP_CONCAT(p.ProductName SEPARATOR ', ') FROM OrderDetails od JOIN Products p ON od.ProductID = p.ProductID WHERE od.OrderID = o.OrderID) as ProductName,
           (SELECT SUM(Quantity) FROM OrderDetails WHERE OrderID = o.OrderID) as Quantity
    FROM Orders o
    JOIN Users u ON o.UserID = u.UserID
    WHERE o.markasdeleted=0 AND o.OrderStatus != 'pending'
";

if ($status_filter !== 'all') {
    $query .= " AND LOWER(o.OrderStatus) = '" . $conn->real_escape_string($status_filter) . "' ";
}
if (!empty($start_date)) {
    $query .= " AND DATE(o.OrderDate) >= '" . $conn->real_escape_string($start_date) . "' ";
}
if (!empty($end_date)) {
    $query .= " AND DATE(o.OrderDate) <= '" . $conn->real_escape_string($end_date) . "' ";
}
$query .= " ORDER BY o.OrderDate DESC";

$result = $conn->query($query);
if ($result) {
    while($row = $result->fetch_assoc()) {
        $orders[] = $row;
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Order History - SupplyNet Employee</title>
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
            --success-color: #1cc88a;
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
        .card-custom { border: none; border-radius: 0.75rem; box-shadow: 0 0.15rem 1.75rem 0 rgba(58, 59, 69, 0.1); background: #fff; overflow: hidden; }
        
        /* Table Styling */
        .table-custom { margin-bottom: 0; }
        .table-custom thead th { border-bottom: 2px solid #e3e6f0; font-size: 0.85rem; font-weight: 700; text-transform: uppercase; color: #858796; padding: 1rem; background-color: #f8f9fc; }
        .table-custom tbody td { padding: 1rem; vertical-align: middle; color: #5a5c69; font-size: 0.95rem; border-bottom: 1px solid #e3e6f0; }
        .table-custom tbody tr:hover { background-color: #f8f9fc; }
        .table-custom tbody tr:last-child td { border-bottom: none; }
        
        .badge-status { font-weight: 600; padding: 0.4em 0.75em; font-size: 0.8em; border-radius: 0.35rem; }
        
        /* Date & Time Styling */
        .date-display { font-weight: 600; color: #2e384d; margin-bottom: 0.1rem; }
        .time-display { font-size: 0.8rem; color: #8798ad; display: flex; align-items: center; }

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

<!-- Sidebar -->
<div class="sidebar">
    <a href="index.php" class="brand"><i class="fas fa-cubes me-2"></i>SupplyNet<br><small class="text-white-50" style="font-size: 0.7rem;">Employee Panel</small></a>
    <div class="mt-4">
        <div class="nav-item"><a href="index.php" class="nav-link"><i class="fas fa-tachometer-alt"></i> Dashboard</a></div>
        <div class="nav-item"><a href="manageOrder.php" class="nav-link"><i class="fas fa-tasks"></i> Manage Orders</a></div>
        <div class="nav-item"><a href="viewOrderHistory.php" class="nav-link active"><i class="fas fa-history"></i> Order History</a></div>
        <div class="nav-item"><a href="assignOrder.php" class="nav-link"><i class="fas fa-clipboard-check"></i> Assign Orders</a></div>
        <div class="nav-item"><a href="routeAssign.php" class="nav-link"><i class="fas fa-map-marked-alt"></i> Route Assign</a></div>
    </div>
</div>

<div class="sidebar-overlay" id="sidebarOverlay"></div>
<div class="main-wrapper">
    <!-- Topbar -->
    <div class="topbar">
        <div class="d-flex align-items-center">
            <button class="btn btn-link d-md-none text-dark me-2 px-0" id="sidebarToggle"><i class="fas fa-bars fs-5"></i></button>
            <div class="ms-2 ms-md-0">
                <h4 class="m-0 fw-bold text-dark fs-5 fs-md-4">Processed Order History</h4>
                <p class="text-muted small mb-0 d-none d-md-block">View and track all actioned orders.</p>
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
                    <img src="https://ui-avatars.com/api/?name=<?php echo urlencode($display_name); ?>&background=1cc88a&color=fff" alt="Employee Profile">
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
        <div class="row align-items-center mb-3">
            <div class="col-md-7 mb-3 mb-md-0">
                <ul class="nav nav-pills flex-column flex-sm-row gap-2">
                    <?php
                        $qs = '';
                        if(!empty($start_date)) $qs .= '&start_date='.urlencode($start_date);
                        if(!empty($end_date)) $qs .= '&end_date='.urlencode($end_date);
                    ?>
                    <li class="nav-item">
                        <a class="nav-link <?php echo $status_filter === 'all' ? 'active' : 'bg-white text-dark shadow-sm border border-light'; ?>" href="viewOrderHistory.php?status=all<?php echo $qs; ?>" <?php echo $status_filter === 'all' ? 'style="background-color: var(--primary-color);"' : ''; ?>><i class="fas fa-list me-2"></i>All Actioned Orders</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link <?php echo $status_filter === 'processing' ? 'active' : 'bg-white text-dark shadow-sm border border-light'; ?>" href="viewOrderHistory.php?status=processing<?php echo $qs; ?>" style="<?php echo $status_filter === 'processing' ? 'background-color: #36b9cc;' : ''; ?>"><i class="fas fa-cog me-2"></i>Processing</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link <?php echo $status_filter === 'shipped' ? 'active' : 'bg-white text-dark shadow-sm border border-light'; ?>" href="viewOrderHistory.php?status=shipped<?php echo $qs; ?>" style="<?php echo $status_filter === 'shipped' ? 'background-color: #4e73df;' : ''; ?>"><i class="fas fa-truck me-2"></i>Shipped</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link <?php echo $status_filter === 'delivered' ? 'active' : 'bg-white text-dark shadow-sm border border-light'; ?>" href="viewOrderHistory.php?status=delivered<?php echo $qs; ?>" style="<?php echo $status_filter === 'delivered' ? 'background-color: #1cc88a;' : ''; ?>"><i class="fas fa-check-circle me-2"></i>Delivered</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link <?php echo $status_filter === 'cancelled' ? 'active' : 'bg-white text-dark shadow-sm border border-light'; ?>" href="viewOrderHistory.php?status=cancelled<?php echo $qs; ?>" style="<?php echo $status_filter === 'cancelled' ? 'background-color: #e74a3b;' : ''; ?>"><i class="fas fa-times-circle me-2"></i>Cancelled</a>
                    </li>
                </ul>
            </div>
            <div class="col-md-5">
                <form method="GET" class="d-flex align-items-center gap-2 justify-content-md-end bg-white p-2 rounded shadow-sm">
                    <?php if($status_filter !== 'all'): ?>
                        <input type="hidden" name="status" value="<?php echo htmlspecialchars($status_filter); ?>">
                    <?php endif; ?>
                    <input type="date" name="start_date" class="form-control form-control-sm" value="<?php echo htmlspecialchars($start_date); ?>" max="<?php echo date('Y-m-d'); ?>">
                    <span class="text-muted small">to</span>
                    <input type="date" name="end_date" class="form-control form-control-sm" value="<?php echo htmlspecialchars($end_date); ?>" max="<?php echo date('Y-m-d'); ?>">
                    <button type="submit" class="btn btn-sm btn-primary shadow-sm" style="background-color: var(--primary-color); border:none;"><i class="fas fa-filter"></i></button>
                    <?php if(!empty($start_date) || !empty($end_date)): ?>
                        <a href="viewOrderHistory.php<?php echo $status_filter !== 'all' ? '?status='.$status_filter : ''; ?>" class="btn btn-sm btn-outline-secondary shadow-sm"><i class="fas fa-times"></i></a>
                    <?php endif; ?>
                </form>
            </div>
        </div>
        
        <div class="card card-custom shadow-sm">
            <div class="card-header bg-white border-bottom py-3 d-flex flex-wrap justify-content-between align-items-center gap-3">
                <h6 class="m-0 font-weight-bold" style="color: var(--primary-color) !important;">
                    <i class="fas fa-list me-2"></i><?php echo $status_filter === 'all' ? 'Processed Orders Archive' : ucfirst($status_filter) . ' Orders'; ?>
                </h6>
                <div class="input-group input-group-sm rounded-pill" style="max-width: 250px; background: #f8f9fc; border: 1px solid #e3e6f0; overflow:hidden;">
                    <span class="input-group-text bg-transparent border-0 pe-2"><i class="fas fa-search text-muted"></i></span>
                    <input type="text" id="searchInput" class="form-control border-0 bg-transparent shadow-none px-1" placeholder="Search archive...">
                </div>
            </div>
            <div class="card-body p-0">
                <div class="table-responsive">
                    <table class="table table-custom table-hover align-middle table-responsive-stack">
                        <thead>
                            <tr>
                                <th class="ps-4">Order ID</th>
                                <th>Order Log</th>
                                <th>Client Name</th>
                                <th>Item Ordered</th>
                                <th>Amount</th>
                                <th class="pe-4">Status</th>
                            </tr>
                        </thead>
                        <tbody id="dataTableBody">
                            <?php if (empty($orders)): ?>
                                <tr>
                                    <td colspan="6" class="text-center py-5">
                                        <div class="text-muted mb-2"><i class="fas fa-box-open fs-1 text-light"></i></div>
                                        <h6 class="text-muted mt-3">No orders found matching this filter.</h6>
                                    </td>
                                </tr>
                            <?php else: ?>
                                <?php foreach ($orders as $o): ?>
                                    <tr>
                                        <td class="ps-4" data-label="Order ID">
                                            <div class="fw-bold text-dark">#<?php echo str_pad($o['OrderID'], 5, '0', STR_PAD_LEFT); ?></div>
                                            <?php if ($o['Quantity']): ?>
                                                <small class="text-muted"><i class="fas fa-cubes me-1" style="font-size:0.75rem;"></i><?php echo $o['Quantity']; ?> units</small>
                                            <?php endif; ?>
                                        </td>
                                        <td data-label="Order Log">
                                            <div class="date-display d-flex align-items-center gap-2"><?php echo date('M d, Y', strtotime($o['OrderDate'])); ?></div>
                                            <div class="time-display mb-1"><i class="far fa-clock me-1 d-none d-sm-inline"></i><?php echo date('h:i A', strtotime($o['OrderDate'])); ?></div>
                                            <?php if(!empty($o['RequiredDate'])): 
                                                $is_overdue = (strtotime($o['RequiredDate']) < strtotime(date('Y-m-d'))) && !in_array(strtolower($o['OrderStatus']), ['delivered', 'cancelled']);
                                            ?>
                                                <div class="small mt-1 fw-bold <?php echo $is_overdue ? 'text-danger' : 'text-danger opacity-75'; ?>">
                                                    <i class="fas fa-calendar-check me-1"></i>Target: <?php echo date('M d, Y', strtotime($o['RequiredDate'])); ?>
                                                </div>
                                            <?php endif; ?>
                                        </td>
                                        <td data-label="Client Name">
                                            <div class="fw-semibold text-dark mt-1"><?php echo htmlspecialchars($o['CustomerName']); ?></div>
                                        </td>
                                        <td data-label="Item Ordered">
                                            <span class="text-truncate d-inline-block" style="max-width: 220px; font-weight: 500;">
                                                <?php echo htmlspecialchars($o['ProductName'] ?? 'Multiple Items'); ?>
                                            </span>
                                        </td>
                                        <td data-label="Amount">
                                            <div class="fw-bold text-success fs-6">₹<?php echo number_format($o['TotalAmount'], 2); ?></div>
                                        </td>
                                        <td class="pe-4" data-label="Status">
                                            <?php 
                                            $statusClasses = [
                                                'processing' => 'bg-info text-white',
                                                'shipped' => 'bg-primary text-white',
                                                'delivered' => 'bg-success text-white',
                                                'cancelled' => 'bg-danger text-white'
                                            ];
                                            $status = strtolower($o['OrderStatus']);
                                            $s_class = $statusClasses[$status] ?? 'bg-secondary text-white';
                                            $icons = [
                                                'processing' => 'fa-cog fa-spin',
                                                'shipped' => 'fa-truck',
                                                'delivered' => 'fa-check-circle',
                                                'cancelled' => 'fa-times-circle'
                                            ];
                                            $icon = $icons[$status] ?? 'fa-circle';
                                            ?>
                                            <span class="badge badge-status <?php echo $s_class; ?>">
                                                <i class="fas <?php echo $icon; ?> me-1"></i><?php echo ucfirst($status); ?>
                                            </span>
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

<!-- Bootstrap Bundle with Popper -->
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
<script>
    // Sidebar Toggle Logic
    document.getElementById('sidebarToggle').addEventListener('click', function() {
        document.querySelector('.sidebar').classList.add('show-sidebar');
        document.getElementById('sidebarOverlay').classList.add('show');
    });

    document.getElementById('sidebarOverlay').addEventListener('click', function() {
        document.querySelector('.sidebar').classList.remove('show-sidebar');
        this.classList.remove('show');
    });

    // Dynamic Search Script
    const searchInput = document.getElementById('searchInput');
    if (searchInput) {
        searchInput.addEventListener('keyup', function() {
            let filter = this.value.toLowerCase().trim();
            let rows = document.getElementById('dataTableBody').getElementsByTagName('tr');
            
            for(let i=0; i<rows.length; i++) {
                if(rows[i].cells.length === 1) continue; // Skip empty message
                let text = rows[i].textContent.toLowerCase();
                rows[i].style.display = text.includes(filter) ? '' : 'none';
            }
        });
    }
</script>
</body>
</html>
