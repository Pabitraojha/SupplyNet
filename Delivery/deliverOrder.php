<?php
session_start();
require_once '../config/db.php';

// Auth Protection
if (!isset($_SESSION['UserID']) || strtolower($_SESSION['Role']) !== 'delivery_person') {
    header("Location: ../login.php");
    exit();
}

$user_id = $_SESSION['UserID'];
$display_name = $_SESSION['UserName'] ?? 'Agent';

// Fetch Delivery History
$history_orders = [];
$status_filter = isset($_GET['status']) ? strtolower(trim($_GET['status'])) : 'all';

$query = "
    SELECT 
        d.DeliveryID, 
        d.DeliveryStatus,
        o.OrderID, 
        u.UserName as CustomerName, 
        o.ShippingAddress as Address, 
        o.OrderDate, 
        d.DeliveryDate as TargetDate, 
        o.TotalAmount,
        (SELECT GROUP_CONCAT(p.ProductName SEPARATOR ', ') FROM OrderDetails od JOIN Products p ON od.ProductID = p.ProductID WHERE od.OrderID = o.OrderID) as Items
    FROM Deliveries d
    JOIN Orders o ON d.OrderID = o.OrderID
    JOIN Users u ON o.UserID = u.UserID
    WHERE d.DeliveryPersonID = ? 
      AND d.markasdeleted = 0 
      AND d.DeliveryStatus IN ('delivered', 'cancelled', 'failed')
";

if ($status_filter !== 'all') {
    $query .= " AND d.DeliveryStatus = ? ";
}

$query .= " ORDER BY d.DeliveryDate DESC";

$stmt = $conn->prepare($query);
if ($status_filter !== 'all') {
    $stmt->bind_param("is", $user_id, $status_filter);
} else {
    $stmt->bind_param("i", $user_id);
}

$stmt->execute();
$result = $stmt->get_result();

if ($result) {
    while($row = $result->fetch_assoc()) {
        $history_orders[] = $row;
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Delivery History - SupplyNet</title>
    <!-- Fonts -->
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    <!-- Bootstrap CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <!-- FontAwesome -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    
    <style>
        :root {
            --primary-color: #e74a3b;
            --primary-dark: #be2617;
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
        .sidebar { width: var(--sidebar-width); height: 100vh; position: fixed; background: linear-gradient(180deg, var(--primary-color) 10%, var(--primary-dark) 100%); color: #fff; z-index: 1000; box-shadow: 2px 0 10px rgba(0,0,0,0.1); }
        .sidebar .brand { padding: 1.5rem 1rem; font-size: 1.25rem; font-weight: 800; text-align: center; text-transform: uppercase; letter-spacing: 0.05rem; border-bottom: 1px solid rgba(255,255,255,0.1); display: block; color: #fff; text-decoration: none; }
        .nav-item { padding: 0 1rem; margin-bottom: 0.5rem; }
        .nav-link { color: rgba(255,255,255,0.8); padding: 1rem; border-radius: 0.5rem; font-weight: 600; transition: all 0.3s; text-decoration: none;}
        .nav-link:hover, .nav-link.active { color: #fff; background: rgba(255,255,255,0.15); transform: translateX(5px); }
        .nav-link i { margin-right: 0.75rem; width: 20px; text-align: center; }

        /* Main Content wrapper */
        .main-wrapper { margin-left: var(--sidebar-width); flex-grow: 1; min-height: 100vh; display: flex; flex-direction: column; }
        
        /* Top Navigation */
        .topbar { background: #fff; height: 70px; box-shadow: 0 0.15rem 1.75rem 0 rgba(58, 59, 69, 0.15); display: flex; align-items: center; justify-content: space-between; padding: 0 2rem; position: sticky; top: 0; z-index: 999; }
        .topbar-user { font-weight: 600; color: #5a5c69; display: flex; align-items: center; gap: 0.5rem; text-decoration: none; }
        .topbar-user img { width: 35px; height: 35px; border-radius: 50%; border: 2px solid var(--primary-color); }

        .dashboard-content { padding: 1.5rem 2rem; flex-grow: 1; }
        
        /* Card & Table Styling */
        .card-custom { border: none; border-radius: 0.75rem; box-shadow: 0 0.15rem 1.75rem 0 rgba(58, 59, 69, 0.1); background: #fff; overflow: hidden; }
        
        .table-custom { margin: 0; }
        .table-custom thead th { border-bottom: 2px solid #e3e6f0; font-size: 0.8rem; font-weight: 700; text-transform: uppercase; color: #858796; padding: 1.2rem 1rem; background-color: #f8f9fc; }
        .table-custom tbody td { padding: 1.2rem 1rem; vertical-align: middle; color: #5a5c69; border-bottom: 1px solid #e3e6f0; }
        .table-custom tbody tr:hover { background-color: rgba(231, 74, 59, 0.02); }
        .table-custom tbody tr:last-child td { border-bottom: none; }
        
        /* Status Badges */
        .badge-status { font-weight: 600; padding: 0.5em 0.8em; font-size: 0.75rem; border-radius: 0.35rem; text-transform: uppercase; letter-spacing: 0.05em; }

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
    <a href="index.php" class="brand"><i class="fas fa-cubes me-2"></i>SupplyNet<br><small class="text-white-50" style="font-size: 0.7rem;">Delivery Agent</small></a>
    <div class="mt-4">
        <div class="nav-item"><a href="index.php" class="nav-link"><i class="fas fa-tachometer-alt"></i> Dashboard</a></div>
        <div class="nav-item"><a href="viewAssignOrder.php" class="nav-link"><i class="fas fa-clipboard-list"></i> Assigned Orders</a></div>
        <div class="nav-item"><a href="deliverOrder.php" class="nav-link active"><i class="fas fa-truck"></i> Delivery History</a></div>
    </div>
</div>

<div class="sidebar-overlay" id="sidebarOverlay"></div>
<div class="main-wrapper">
    <div class="topbar">
        <div class="d-flex align-items-center">
            <button class="btn btn-link d-md-none text-dark me-2 px-0" id="sidebarToggle"><i class="fas fa-bars fs-5"></i></button>
            <div class="ms-2 ms-md-0">
                <h4 class="m-0 fw-bold text-dark fs-5 fs-md-4">Delivery History</h4>
                <p class="text-muted small mb-0 d-none d-md-block">Your past routes and completed assignments.</p>
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
                    <img src="https://ui-avatars.com/api/?name=<?php echo urlencode($display_name); ?>&background=e74a3b&color=fff" alt="Profile">
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

        <div class="row mb-4">
            <div class="col-12">
                <ul class="nav nav-pills flex-column flex-sm-row gap-2">
                    <li class="nav-item">
                        <a class="nav-link <?php echo $status_filter === 'all' ? 'active' : 'bg-white text-dark shadow-sm border border-light'; ?>" href="deliverOrder.php" <?php echo $status_filter === 'all' ? 'style="background-color: var(--primary-color);"' : ''; ?>><i class="fas fa-list me-2"></i>All History</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link <?php echo $status_filter === 'delivered' ? 'active' : 'bg-white text-dark shadow-sm border border-light'; ?>" href="deliverOrder.php?status=delivered" style="<?php echo $status_filter === 'delivered' ? 'background-color: var(--success-color);' : ''; ?>"><i class="fas fa-check-circle me-2"></i>Successful</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link <?php echo $status_filter === 'cancelled' ? 'active' : 'bg-white text-dark shadow-sm border border-light'; ?>" href="deliverOrder.php?status=cancelled" style="<?php echo $status_filter === 'cancelled' ? 'background-color: var(--dark-color);' : ''; ?>"><i class="fas fa-times-circle me-2"></i>Cancelled/Failed</a>
                    </li>
                </ul>
            </div>
        </div>

        <div class="card card-custom shadow-sm">
            <div class="card-header bg-white border-bottom p-4 d-flex justify-content-between align-items-center flex-wrap gap-3">
                <h6 class="m-0 font-weight-bold fs-5" style="color: var(--primary-color);">
                    <i class="fas fa-history me-2"></i>My Historical Records
                </h6>
                <div class="input-group input-group-sm rounded-pill shadow-sm" style="max-width: 280px; background: #f8f9fc; border: 1px solid #e3e6f0; overflow:hidden;">
                    <span class="input-group-text bg-transparent border-0 pe-2"><i class="fas fa-search text-muted"></i></span>
                    <input type="text" id="searchInput" class="form-control border-0 bg-transparent shadow-none px-1" placeholder="Search history...">
                </div>
            </div>
            
            <div class="card-body p-0">
                <div class="table-responsive">
                    <table class="table table-custom align-middle">
                        <thead>
                            <tr>
                                <th class="ps-4">Assignment & Ref</th>
                                <th>Client Name</th>
                                <th>Target Address</th>
                                <th>Items Delivered</th>
                                <th>Amount</th>
                                <th class="pe-4">Final Status</th>
                            </tr>
                        </thead>
                        <tbody id="dataTableBody">
                            <?php if(empty($history_orders)): ?>
                                <tr>
                                    <td colspan="6" class="text-center py-5">
                                        <div class="text-muted"><i class="fas fa-box-open fs-1 mb-3 text-light"></i></div>
                                        <h5 class="text-muted fw-bold">No History Found</h5>
                                        <p class="text-muted small">You don't have any past deliveries matching this view.</p>
                                    </td>
                                </tr>
                            <?php else: ?>
                                <?php foreach($history_orders as $o): ?>
                                    <tr>
                                        <td class="ps-4">
                                            <div class="fw-bold text-dark fs-5">#DEL-<?php echo str_pad($o['DeliveryID'], 4, '0', STR_PAD_LEFT); ?></div>
                                            <div class="text-muted small mt-1 fw-semibold"><i class="fas fa-shopping-cart text-primary me-1"></i>Ord #<?php echo $o['OrderID']; ?></div>
                                        </td>
                                        
                                        <td>
                                            <div class="fw-bold text-dark"><i class="fas fa-user text-secondary me-2"></i><?php echo htmlspecialchars($o['CustomerName']); ?></div>
                                        </td>
                                        
                                        <td>
                                            <div class="small fw-semibold text-secondary">
                                                <i class="fas fa-map-marker-alt text-danger me-1"></i>
                                                <?php echo htmlspecialchars($o['Address']); ?>
                                            </div>
                                            <div class="small text-muted mt-1 " style="font-size: 0.75rem;"><i class="fas fa-calendar-check me-1"></i>Target: <?php echo date('M d', strtotime($o['TargetDate'])); ?></div>
                                        </td>
                                        
                                        <td>
                                            <span class="d-inline-block text-truncate text-secondary fw-semibold" style="max-width: 200px;" title="<?php echo htmlspecialchars($o['Items']); ?>">
                                                <i class="fas fa-box-open me-1"></i><?php echo htmlspecialchars($o['Items'] ?? 'Unknown Items'); ?>
                                            </span>
                                        </td>
                                        
                                        <td>
                                            <div class="fw-bold fs-6 mt-1 text-dark">₹<?php echo number_format($o['TotalAmount'], 2); ?></div>
                                        </td>
                                        
                                        <td class="pe-4">
                                            <?php 
                                            $s = strtolower($o['DeliveryStatus']);
                                            if ($s === 'delivered') {
                                                echo '<span class="badge badge-status bg-success"><i class="fas fa-check-double me-1"></i>Delivered</span>';
                                            } else {
                                                echo '<span class="badge badge-status bg-dark"><i class="fas fa-times me-1"></i>'.ucfirst($s).'</span>';
                                            }
                                            ?>
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

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
<script>
    // Sidebar toggle
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
            
            for (let i = 0; i < rows.length; i++) {
                if (rows[i].cells.length === 1) continue; // Skip "No History Found" cell
                let text = rows[i].textContent.toLowerCase();
                rows[i].style.display = text.includes(filter) ? '' : 'none';
            }
        });
    }
</script>
</body>
</html>
