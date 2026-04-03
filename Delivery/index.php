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

// Fetch Metrics for Delivery Agent
$metrics = [
    'pending' => 0,
    'in_transit' => 0,
    'delivered_today' => 0,
    'total_delivered' => 0
];

$query = "SELECT DeliveryStatus, DATE(DeliveryDate) as dDate FROM Deliveries WHERE DeliveryPersonID = ? AND markasdeleted = 0";
$stmt = $conn->prepare($query);
$stmt->bind_param("i", $user_id);
$stmt->execute();
$res = $stmt->get_result();
$today = date('Y-m-d');

if ($res) {
    while($row = $res->fetch_assoc()) {
        $status = strtolower($row['DeliveryStatus']);
        if ($status === 'pending') {
            $metrics['pending']++;
        } elseif ($status === 'in_transit') {
            $metrics['in_transit']++;
        } elseif ($status === 'delivered') {
            $metrics['total_delivered']++;
            if ($row['dDate'] === $today) {
                $metrics['delivered_today']++;
            }
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Delivery Dashboard - SupplyNet</title>
    <!-- Fonts -->
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    <!-- Bootstrap CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <!-- FontAwesome -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    
    <style>
        :root {
            --primary-color: #e74a3b;
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
        .sidebar { width: var(--sidebar-width); height: 100vh; position: fixed; background: linear-gradient(180deg, #e74a3b 10%, #be2617 100%); color: #fff; z-index: 1000; box-shadow: 2px 0 10px rgba(0,0,0,0.1); }
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

        .dashboard-content { padding: 1.5rem 2rem; flex-grow: 1; display: flex; align-items: center; justify-content: center; }
        
        /* Construct Container */
        .construct-container { text-align: center; background: #fff; padding: 4rem 2rem; border-radius: 1rem; box-shadow: 0 0.5rem 2rem rgba(0,0,0,0.05); max-width: 600px; width: 100%; position: relative; overflow: hidden; }
        .construct-container::before { content: ''; position: absolute; top: 0; left: 0; right: 0; height: 6px; background: linear-gradient(90deg, var(--primary-color), var(--dark-color)); }
        
        .construct-icon { font-size: 5rem; color: var(--primary-color); margin-bottom: 1.5rem; animation: float 3s ease-in-out infinite; }
        .construct-title { font-weight: 800; color: var(--dark-color); font-size: 2rem; margin-bottom: 1rem; letter-spacing: -0.5px; }
        .construct-text { font-size: 1.1rem; color: #6c757d; margin-bottom: 2rem; line-height: 1.6; }
        
        @keyframes float {
            0% { transform: translateY(0px); }
            50% { transform: translateY(-15px); }
            100% { transform: translateY(0px); }
        }

        .pulse-badge { display: inline-flex; align-items: center; background: rgba(231, 74, 59, 0.1); color: var(--primary-color); padding: 0.5rem 1.25rem; border-radius: 50rem; font-weight: 600; font-size: 0.9rem; margin-bottom: 2rem; border: 1px solid rgba(231, 74, 59, 0.2); }
        .pulse-dot { width: 8px; height: 8px; background-color: var(--primary-color); border-radius: 50%; margin-right: 0.5rem; box-shadow: 0 0 0 rgba(231, 74, 59, 0.4); animation: pulse 2s infinite; }
        
        @keyframes pulse {
            0% { box-shadow: 0 0 0 0 rgba(231, 74, 59, 0.4); }
            70% { box-shadow: 0 0 0 10px rgba(231, 74, 59, 0); }
            100% { box-shadow: 0 0 0 0 rgba(231, 74, 59, 0); }
        }

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
        <div class="nav-item"><a href="index.php" class="nav-link active"><i class="fas fa-tachometer-alt"></i> Dashboard</a></div>
        <div class="nav-item"><a href="viewAssignOrder.php" class="nav-link"><i class="fas fa-clipboard-list"></i> Assigned Orders</a></div>
        <div class="nav-item"><a href="deliverOrder.php" class="nav-link"><i class="fas fa-truck"></i> Delivery History</a></div>
    </div>
</div>

<div class="sidebar-overlay" id="sidebarOverlay"></div>
<div class="main-wrapper">
    <div class="topbar">
        <div class="d-flex align-items-center">
            <button class="btn btn-link d-md-none text-dark me-2 px-0" id="sidebarToggle"><i class="fas fa-bars fs-5"></i></button>
            <div class="ms-2 ms-md-0">
                <h4 class="m-0 fw-bold text-dark fs-5 fs-md-4">Dashboard</h4>
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

    <!-- Main Content -->
    <div class="dashboard-content d-block w-100 px-4 py-4">
        <!-- Welcome Banner -->
        <div class="card card-custom bg-white border-0 shadow-sm mb-4">
            <div class="card-body p-4 d-flex flex-column flex-md-row align-items-md-center justify-content-between gap-3">
                <div>
                    <h4 class="fw-bold mb-1 text-dark">Welcome back, <?php echo htmlspecialchars($display_name); ?>!</h4>
                    <p class="text-secondary mb-0">Here is your routing overview for today, <?php echo date('l, F jS'); ?>.</p>
                </div>
                <div class="text-md-end">
                    <a href="viewAssignOrder.php" class="btn fw-bold text-white px-4 py-2 shadow-sm hover-lift" style="background-color: var(--primary-color); border:none;">
                        <i class="fas fa-truck-fast me-2"></i> Start Daily Route
                    </a>
                </div>
            </div>
        </div>

        <!-- Metric Cards -->
        <h6 class="fw-bold text-dark mb-3"><i class="fas fa-chart-pie me-2" style="color:var(--primary-color);"></i> Route Analytics</h6>
        <div class="row g-4 mb-4">
            <div class="col-xl-3 col-md-6">
                <div class="card card-custom h-100 py-3 border-0 bg-white">
                    <div class="card-body">
                        <div class="metric-title text-secondary fw-bold text-uppercase mb-2" style="font-size:0.75rem; letter-spacing:0.05em;">Waiting for Load</div>
                        <div class="d-flex align-items-center justify-content-between">
                            <h2 class="metric-value text-warning fw-bold m-0"><?php echo $metrics['pending']; ?></h2>
                            <i class="fas fa-boxes fs-2 text-warning opacity-25"></i>
                        </div>
                    </div>
                </div>
            </div>
            <div class="col-xl-3 col-md-6">
                <div class="card card-custom h-100 py-3 border-0 bg-white">
                    <div class="card-body">
                        <div class="metric-title text-secondary fw-bold text-uppercase mb-2" style="font-size:0.75rem; letter-spacing:0.05em;">Active Transit Routes</div>
                        <div class="d-flex align-items-center justify-content-between">
                            <h2 class="metric-value fw-bold m-0" style="color:var(--primary-color);"><?php echo $metrics['in_transit']; ?></h2>
                            <i class="fas fa-truck fs-2 opacity-25" style="color:var(--primary-color);"></i>
                        </div>
                    </div>
                </div>
            </div>
            <div class="col-xl-3 col-md-6">
                <div class="card card-custom h-100 py-3 border-0 bg-white">
                    <div class="card-body">
                        <div class="metric-title text-secondary fw-bold text-uppercase mb-2" style="font-size:0.75rem; letter-spacing:0.05em;">Packages Dropped Today</div>
                        <div class="d-flex align-items-center justify-content-between">
                            <h2 class="metric-value text-success fw-bold m-0"><?php echo $metrics['delivered_today']; ?></h2>
                            <i class="fas fa-box-open fs-2 text-success opacity-25"></i>
                        </div>
                    </div>
                </div>
            </div>
            <div class="col-xl-3 col-md-6">
                <div class="card card-custom h-100 py-3 border-0 bg-white">
                    <div class="card-body">
                        <div class="metric-title text-secondary fw-bold text-uppercase mb-2" style="font-size:0.75rem; letter-spacing:0.05em;">All-Time Drop Records</div>
                        <div class="d-flex align-items-center justify-content-between">
                            <h2 class="metric-value text-dark fw-bold m-0"><?php echo $metrics['total_delivered']; ?></h2>
                            <i class="fas fa-award fs-2 text-secondary opacity-25"></i>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <div class="row g-4 d-flex align-items-stretch">
            <div class="col-lg-6 mb-4">
                <div class="card card-custom shadow-sm h-100 border-0">
                    <div class="card-header bg-white border-bottom p-4">
                        <h6 class="m-0 fw-bold fs-5" style="color:var(--primary-color);"><i class="fas fa-bolt me-2"></i>Urgent Logistical Actions</h6>
                    </div>
                    <div class="card-body p-4 d-flex flex-column gap-3 justify-content-center">
                        <a href="viewAssignOrder.php" class="btn btn-outline-danger p-3 text-start fw-bold shadow-sm d-block hover-lift text-decoration-none" style="border: 1px solid rgba(231,74,59,0.2); border-left: 5px solid var(--primary-color); border-radius: 8px; background-color: rgba(231,74,59,0.02);">
                            <div class="d-flex align-items-center">
                                <div class="bg-danger bg-opacity-10 p-3 rounded-circle me-3 ms-1"><i class="fas fa-map-marked-alt text-danger fs-4"></i></div>
                                <div><div class="fs-5 text-dark">Access Live Nav & Routing</div><div class="small text-secondary fw-normal">Scan assigned deliveries, initiate travel sequence.</div></div>
                            </div>
                        </a>
                        <a href="deliverOrder.php" class="btn btn-outline-secondary p-3 text-start fw-bold shadow-sm d-block hover-lift text-decoration-none" style="border: 1px solid rgba(108,117,125,0.2); border-left: 5px solid #6c757d; border-radius: 8px; background-color: rgba(108,117,125,0.02);">
                            <div class="d-flex align-items-center">
                                <div class="bg-secondary bg-opacity-10 p-3 rounded-circle me-3 ms-1"><i class="fas fa-history text-secondary fs-4"></i></div>
                                <div><div class="fs-5 text-dark">Review Dispatch History</div><div class="small text-secondary fw-normal">Audit daily drops, cancellations, or signature receipts.</div></div>
                            </div>
                        </a>
                    </div>
                </div>
            </div>
            
            <div class="col-lg-6 mb-4">
                <div class="card card-custom shadow-sm h-100 border-0">
                    <div class="card-header bg-white border-bottom p-4">
                        <h6 class="m-0 fw-bold fs-5" style="color:var(--primary-color);"><i class="fas fa-clipboard-check me-2"></i>Compliance & Verification</h6>
                    </div>
                    <div class="card-body bg-light p-4">
                        <ul class="list-unstyled m-0 text-dark">
                            <li class="mb-4 d-flex">
                                <i class="fas fa-id-card text-success fs-5 me-3 mt-1"></i> 
                                <div><strong class="text-dark d-block mb-1">Identity Check Protocols</strong> Verify customer credentials against invoice targets before surrendering corporate property.</div>
                            </li>
                            <li class="mb-4 d-flex">
                                <i class="fas fa-signature text-success fs-5 me-3 mt-1"></i> 
                                <div><strong class="text-dark d-block mb-1">Mandatory Authorization</strong> Generate official reporting mechanisms and collect validated recipient signatures.</div>
                            </li>
                            <li class="mb-4 d-flex">
                                <i class="fas fa-phone-volume text-warning fs-5 me-3 mt-1"></i> 
                                <div><strong class="text-dark d-block mb-1">Proactive Communication</strong> Leverage built-in digital phone links to secure locations ahead of schedule to minimize failed deposits.</div>
                            </li>
                            <li class="d-flex">
                                <i class="fas fa-shield-virus text-danger fs-5 me-3 mt-1"></i> 
                                <div><strong class="text-dark d-block mb-1">Critical Fault Procedures</strong> Escalate unresolvable logistical blockades immediately to internal dispatch (800-SUPPLYNET).</div>
                            </li>
                        </ul>
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
