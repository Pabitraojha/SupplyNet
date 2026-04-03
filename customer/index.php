<?php
session_start();
require_once '../config/db.php';

// Auth Protection
if (!isset($_SESSION['UserID']) || strtolower($_SESSION['Role']) !== 'customer') {
    header("Location: ../login.php");
    exit();
}

$user_id = $_SESSION['UserID'];
$display_name = $_SESSION['UserName'] ?? 'Customer';

// Fetch summary metrics
$stmt = $conn->prepare("SELECT COUNT(*) as TotalOrders, SUM(TotalAmount) as TotalSpent FROM Orders WHERE UserID = ? AND markasdeleted = 0 AND OrderStatus != 'cancelled'");
$stmt->bind_param("i", $user_id);
$stmt->execute();
$metrics = $stmt->get_result()->fetch_assoc();
$total_orders = $metrics['TotalOrders'] ?? 0;
$total_spent = $metrics['TotalSpent'] ?? 0;

$stmt = $conn->prepare("SELECT COUNT(*) as PendingOrders FROM Orders WHERE UserID = ? AND markasdeleted = 0 AND OrderStatus IN ('pending', 'processing', 'shipped')");
$stmt->bind_param("i", $user_id);
$stmt->execute();
$pending = $stmt->get_result()->fetch_assoc();
$pending_orders = $pending['PendingOrders'] ?? 0;

// Fetch Recent Orders (Limit 5)
$stmt = $conn->prepare("
    SELECT o.OrderID, o.OrderDate, o.TotalAmount, o.OrderStatus, p.ProductName 
    FROM Orders o 
    JOIN Products p ON o.ProductID = p.ProductID 
    WHERE o.UserID = ? AND o.markasdeleted = 0 
    ORDER BY o.OrderDate DESC LIMIT 5
");
$stmt->bind_param("i", $user_id);
$stmt->execute();
$recent_orders = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Customer Dashboard - SupplyNet</title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    
    <style>
        :root {
            --primary-color: #fd7e14;
            --primary-hover: #e86000;
            --secondary-color: #f8f9fc;
            --info-color: #36b9cc;
            --success-color: #1cc88a;
            --warning-color: #f6c23e;
            --danger-color: #e74a3b;
            --dark-color: #1f2937;
            --sidebar-width: 250px;
        }

        body { font-family: 'Inter', sans-serif; background-color: var(--secondary-color); overflow-x: hidden; }
        
        .sidebar { width: var(--sidebar-width); height: 100vh; position: fixed; background: linear-gradient(180deg, var(--primary-color) 10%, var(--primary-hover) 100%); color: #fff; z-index: 1000; box-shadow: 2px 0 10px rgba(0,0,0,0.1); }
        .sidebar .brand { padding: 1.5rem 1rem; font-size: 1.25rem; font-weight: 800; text-align: center; text-transform: uppercase; letter-spacing: 0.05rem; border-bottom: 1px solid rgba(255,255,255,0.1); display: block; color: #fff; text-decoration: none; }
        .nav-item { padding: 0 1rem; margin-bottom: 0.5rem; border-radius: 0.5rem; }
        .nav-link { color: rgba(255,255,255,0.8); padding: 1rem; border-radius: 0.5rem; font-weight: 600; transition: all 0.3s; text-decoration: none;}
        .nav-link:hover, .nav-link.active { color: #fff; background: rgba(255,255,255,0.15); transform: translateX(5px); }
        .nav-link i { margin-right: 0.75rem; width: 20px; text-align: center; }

        .main-wrapper { margin-left: var(--sidebar-width); flex-grow: 1; min-height: 100vh; display: flex; flex-direction: column; }
        
        .topbar { background: #fff; height: 70px; box-shadow: 0 0.15rem 1.75rem 0 rgba(58, 59, 69, 0.15); display: flex; align-items: center; justify-content: space-between; padding: 0 2rem; }
        .topbar-user { font-weight: 600; color: #5a5c69; display: flex; align-items: center; gap: 0.5rem; text-decoration: none; }
        .topbar-user img { width: 35px; height: 35px; border-radius: 50%; border: 2px solid var(--primary-color); }

        .dashboard-content { padding: 1.5rem 2rem; flex-grow: 1; }
        
        .stat-card {
            background: #fff;
            border-radius: 1rem;
            padding: 1.5rem;
            box-shadow: 0 0.5rem 1.5rem rgba(0,0,0,0.05);
            display: flex;
            align-items: center;
            transition: transform 0.3s ease;
            height: 100%;
        }
        
        .stat-card:hover { transform: translateY(-5px); box-shadow: 0 1rem 2rem rgba(0,0,0,0.1); }
        .stat-icon { width: 60px; height: 60px; border-radius: 1rem; display: flex; align-items: center; justify-content: center; font-size: 1.5rem; margin-right: 1.5rem; }
        
        .stat-content .title { color: #858796; font-size: 0.8rem; font-weight: 700; text-transform: uppercase; margin-bottom: 0.25rem; }
        .stat-content .value { color: var(--dark-color); font-size: 1.8rem; font-weight: 800; line-height: 1; }
        
        .card-custom { border: none; border-radius: 1rem; box-shadow: 0 0.5rem 1.5rem rgba(0,0,0,0.05); }
        .card-header-custom { background: #fff; border-bottom: 1px solid #f8f9fc; padding: 1.25rem 1.5rem; font-weight: 700; color: var(--dark-color); border-radius: 1rem 1rem 0 0 !important; }
        
        .table-responsive { border-radius: 0 0 1rem 1rem; }
        .table th { background-color: #f8f9fc; color: #858796; font-size: 0.8rem; font-weight: 700; text-transform: uppercase; border-bottom: none; }
        .table td { vertical-align: middle; color: #5a5c69; border-bottom-color: #f8f9fc; }
        
        .status-badge { padding: 0.35rem 0.75rem; border-radius: 50rem; font-weight: 600; font-size: 0.75rem; text-transform: uppercase; }
        .status-pending { background: rgba(246, 194, 62, 0.1); color: var(--warning-color); }
        .status-processing { background: rgba(54, 185, 204, 0.1); color: var(--info-color); }
        .status-shipped { background: rgba(253, 126, 20, 0.1); color: var(--primary-color); }
        .status-delivered { background: rgba(28, 200, 138, 0.1); color: var(--success-color); }
        .status-cancelled { background: rgba(231, 74, 59, 0.1); color: var(--danger-color); }
        
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
    <a href="index.php" class="brand"><i class="fas fa-cubes me-2"></i>SupplyNet<br><small class="text-white-50" style="font-size: 0.7rem;">Customer Portal</small></a>
    <div class="mt-4">
        <div class="nav-item"><a href="index.php" class="nav-link active"><i class="fas fa-home"></i> Dashboard</a></div>
        <div class="nav-item"><a href="trackOrder.php" class="nav-link"><i class="fas fa-satellite-dish"></i> Track Order</a></div>
        <div class="nav-item"><a href="submitFeedback.php" class="nav-link"><i class="fas fa-comment-dots"></i> Feedback</a></div>
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
                    <img src="https://ui-avatars.com/api/?name=<?php echo urlencode($display_name); ?>&background=fd7e14&color=fff" alt="Profile">
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
        <div class="row g-4 mb-4">
            <div class="col-xl-4 col-md-6">
                <div class="stat-card border-start border-4" style="border-left-color: var(--primary-color) !important;">
                    <div class="stat-icon bg-primary bg-opacity-10 text-primary">
                        <i class="fas fa-shopping-bag"></i>
                    </div>
                    <div class="stat-content">
                        <div class="title">Total Orders</div>
                        <div class="value"><?php echo number_format($total_orders); ?></div>
                    </div>
                </div>
            </div>
            
            <div class="col-xl-4 col-md-6">
                <div class="stat-card border-start border-4 border-info">
                    <div class="stat-icon bg-info bg-opacity-10 text-info">
                        <i class="fas fa-truck-loading"></i>
                    </div>
                    <div class="stat-content">
                        <div class="title">In Progress / Shipping</div>
                        <div class="value"><?php echo number_format($pending_orders); ?></div>
                    </div>
                </div>
            </div>
            
            <div class="col-xl-4 col-md-12">
                <div class="stat-card border-start border-4 border-success">
                    <div class="stat-icon bg-success bg-opacity-10 text-success">
                        <i class="fas fa-rupee-sign"></i>
                    </div>
                    <div class="stat-content">
                        <div class="title">Total Spent</div>
                        <div class="value">₹<?php echo number_format($total_spent, 2); ?></div>
                    </div>
                </div>
            </div>
        </div>
        
        <div class="row">
            <div class="col-12">
                <div class="card card-custom">
                    <div class="card-header-custom d-flex justify-content-between align-items-center">
                        <h5 class="m-0 fw-bold text-dark"><i class="fas fa-clipboard-list me-2 text-primary"></i>Recent Orders</h5>
                        <a href="trackOrder.php" class="btn btn-sm btn-outline-primary border-primary text-primary" style="--bs-btn-hover-bg: var(--primary-color); --bs-btn-hover-border-color: var(--primary-color);">View All</a>
                    </div>
                    <div class="card-body p-0">
                        <?php if(empty($recent_orders)): ?>
                            <div class="text-center p-5 text-muted">
                                <i class="fas fa-box-open fs-1 mb-3 opacity-50"></i>
                                <p>You haven't placed any orders yet.</p>
                            </div>
                        <?php else: ?>
                            <div class="table-responsive">
                                <table class="table table-hover mb-0">
                                    <thead class="bg-light">
                                        <tr>
                                            <th class="px-4 py-3">Order ID</th>
                                            <th class="py-3">Date</th>
                                            <th class="py-3">Product</th>
                                            <th class="py-3">Amount</th>
                                            <th class="px-4 py-3 text-end">Status</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($recent_orders as $o): 
                                            $oStatus = strtolower($o['OrderStatus']);
                                            $date = new DateTime($o['OrderDate']);
                                        ?>
                                        <tr>
                                            <td class="px-4 py-3 fw-bold text-dark">#<?php echo str_pad($o['OrderID'], 5, '0', STR_PAD_LEFT); ?></td>
                                            <td class="py-3"><?php echo $date->format('M d, Y'); ?></td>
                                            <td class="py-3 text-wrap" style="max-width: 250px;"><?php echo htmlspecialchars($o['ProductName']); ?></td>
                                            <td class="py-3 fw-bold">₹<?php echo number_format($o['TotalAmount'], 2); ?></td>
                                            <td class="px-4 py-3 text-end">
                                                <span class="status-badge status-<?php echo $oStatus; ?>"><?php echo ucfirst($oStatus); ?></span>
                                            </td>
                                        </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
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
</script>
</body>
</html>
