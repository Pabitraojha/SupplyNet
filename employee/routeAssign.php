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

// Handle Route Creation and Assignment
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'assign_route') {
    $delivery_id = intval($_POST['delivery_id']);
    $start_location = trim($_POST['start_location']);
    $end_location = trim($_POST['end_location']);
    $distance = floatval($_POST['distance']);
    $estimated_time = trim($_POST['estimated_time']); // Format: YYYY-MM-DDTHH:MM
    
    if ($delivery_id > 0 && !empty($start_location) && !empty($end_location) && $distance > 0 && !empty($estimated_time)) {
        // Format datetime correctly for MySQL (from datetime-local)
        $est_time = date('Y-m-d H:i:s', strtotime($estimated_time));
        
        $conn->begin_transaction();
        try {
            // Check if Route already exists or create new one
            // We'll just create a new one to be safe and specific to this delivery
            $stmt1 = $conn->prepare("INSERT INTO routes (StartLocation, EndLocation, Distance, EstimatedTime) VALUES (?, ?, ?, ?)");
            $stmt1->bind_param("ssds", $start_location, $end_location, $distance, $est_time);
            $stmt1->execute();
            $route_id = $conn->insert_id;
            
            // Assign this route to the delivery
            $stmt2 = $conn->prepare("INSERT INTO assignRoutes (DeliveryID, RouteID) VALUES (?, ?)");
            $stmt2->bind_param("ii", $delivery_id, $route_id);
            $stmt2->execute();
            
            // Update Delivery Status to in_transit
            $conn->query("UPDATE Deliveries SET DeliveryStatus = 'in_transit' WHERE DeliveryID = $delivery_id");
            
            $conn->commit();
            $message = "Route successfully generated and assigned to the delivery. Status updated to in transit.";
            $message_type = "success";
        } catch (Exception $e) {
            $conn->rollback();
            $message = "Error assigning route: " . $e->getMessage();
            $message_type = "danger";
        }
    } else {
        $message = "All fields are required and must be valid.";
        $message_type = "warning";
    }
}

// Fetch Active Deliveries that need routing (not yet completely assigned/in-transit)
$deliveries = [];
$res = $conn->query("
    SELECT d.DeliveryID, d.OrderID, o.ShippingAddress, u.UserName as DeliveryPerson
    FROM Deliveries d
    JOIN Orders o ON d.OrderID = o.OrderID
    LEFT JOIN Users u ON d.DeliveryPersonID = u.UserID
    LEFT JOIN assignRoutes ar ON d.DeliveryID = ar.DeliveryID AND ar.markasdeleted = 0
    WHERE d.markasdeleted = 0 AND d.DeliveryStatus = 'pending' AND ar.AssignID IS NULL
    ORDER BY d.DeliveryID ASC
");
if ($res) {
    while($row = $res->fetch_assoc()) $deliveries[] = $row;
}

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Route Assignment - SupplyNet</title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
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

        @media (max-width: 991px) {
            .route-planning-row { display: flex; flex-direction: column-reverse; }
            .sticky-top { position: static !important; }
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

<div class="sidebar">
    <a href="index.php" class="brand"><i class="fas fa-cubes me-2"></i>SupplyNet<br><small class="text-white-50" style="font-size: 0.7rem;">Employee Panel</small></a>
    <div class="mt-4">
        <div class="nav-item"><a href="index.php" class="nav-link"><i class="fas fa-tachometer-alt"></i> Dashboard</a></div>
        <div class="nav-item"><a href="manageOrder.php" class="nav-link"><i class="fas fa-tasks"></i> Manage Orders</a></div>
        <div class="nav-item"><a href="viewOrderHistory.php" class="nav-link"><i class="fas fa-history"></i> Order History</a></div>
        <div class="nav-item"><a href="assignOrder.php" class="nav-link"><i class="fas fa-clipboard-check"></i> Assign Orders</a></div>
        <div class="nav-item"><a href="routeAssign.php" class="nav-link active"><i class="fas fa-map-marked-alt"></i> Route Assign</a></div>
    </div>
</div>

<div class="sidebar-overlay" id="sidebarOverlay"></div>
<div class="main-wrapper">
    <div class="topbar">
        <div class="d-flex align-items-center">
            <button class="btn btn-link d-md-none text-dark me-2 px-0" id="sidebarToggle"><i class="fas fa-bars fs-5"></i></button>
            <div class="ms-2 ms-md-0">
                <h4 class="m-0 fw-bold text-dark fs-5 fs-md-4">Delivery Route Planning</h4>
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
            <div class="alert alert-<?php echo $message_type; ?> alert-dismissible fade show shadow-sm border-0 mb-4 py-3">
                <i class="fas <?php echo $message_type === 'success' ? 'fa-check-circle' : 'fa-exclamation-triangle'; ?> me-2"></i>
                <?php echo $message; ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
        <?php endif; ?>

        <div class="row g-4 route-planning-row d-flex align-items-start">
            <!-- Planning Form -->
            <div class="col-lg-5">
                <div class="card card-custom shadow-sm border-0 sticky-top" style="top: 90px; z-index: 10;">
                    <div class="card-header bg-white border-bottom py-3 d-flex align-items-center">
                        <div class="bg-primary bg-opacity-10 p-2 rounded me-3">
                            <i class="fas fa-route text-primary"></i>
                        </div>
                        <h6 class="m-0 fw-bold text-dark">Route Generator</h6>
                    </div>
                    <div class="card-body p-4">
                        <form method="POST" class="needs-validation" novalidate>
                            <input type="hidden" name="action" value="assign_route">
                            
                            <div class="mb-4">
                                <label class="form-label fw-bold small text-muted text-uppercase mb-2">Target Delivery <span class="text-danger">*</span></label>
                                <div class="input-group">
                                    <span class="input-group-text bg-light border-end-0 text-muted"><i class="fas fa-truck-loading"></i></span>
                                    <select name="delivery_id" class="form-select border-start-0 ps-0" required onchange="updateEndLocation(this)">
                                        <option value="" disabled selected>Select shipment...</option>
                                        <?php foreach($deliveries as $d): ?>
                                            <option value="<?php echo $d['DeliveryID']; ?>" data-address="<?php echo htmlspecialchars($d['ShippingAddress']); ?>">
                                                #<?php echo $d['DeliveryID']; ?> - Ord #<?php echo $d['OrderID']; ?> (<?php echo htmlspecialchars($d['DeliveryPerson']); ?>)
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                            </div>

                            <div class="mb-4">
                                <label class="form-label fw-bold small text-muted text-uppercase mb-2">Dispatch Point <span class="text-danger">*</span></label>
                                <div class="input-group">
                                    <span class="input-group-text bg-light border-end-0 text-muted"><i class="fas fa-warehouse"></i></span>
                                    <input type="text" class="form-control border-start-0 ps-0" name="start_location" value="Main Supply Warehouse" required>
                                </div>
                            </div>

                            <div class="mb-4">
                                <label class="form-label fw-bold small text-muted text-uppercase mb-2">Final Destination <span class="text-danger">*</span></label>
                                <div class="input-group text-nowrap">
                                    <span class="input-group-text bg-light border-end-0 text-muted "><i class="fas fa-map-marker-alt"></i></span>
                                    <input type="text" class="form-control border-start-0 ps-0" id="end_location" name="end_location" placeholder="Awaiting shipment selection..." required>
                                </div>
                            </div>
                            
                            <div class="row">
                                <div class="col-md-6 mb-4">
                                    <label class="form-label fw-bold small text-muted text-uppercase mb-2">Distance (Km)</label>
                                    <div class="input-group">
                                        <span class="input-group-text bg-light border-end-0 text-muted"><i class="fas fa-road"></i></span>
                                        <input type="number" step="0.1" class="form-control border-start-0 ps-0" name="distance" placeholder="0.0" required>
                                    </div>
                                </div>
                                <div class="col-md-6 mb-4">
                                    <label class="form-label fw-bold small text-muted text-uppercase mb-2">Est. Arrival</label>
                                    <div class="input-group">
                                        <span class="input-group-text bg-light border-end-0 text-muted"><i class="fas fa-clock"></i></span>
                                        <input type="datetime-local" class="form-control border-start-0 ps-0" name="estimated_time" required>
                                    </div>
                                </div>
                            </div>

                            <button type="submit" class="btn btn-success w-100 py-3 fw-bold shadow-sm d-flex align-items-center justify-content-center">
                                <i class="fas fa-magic me-2"></i> Deploy Route Logistics
                            </button>
                        </form>
                    </div>
                </div>
            </div>

            <!-- Queue List -->
            <div class="col-lg-7">
                <div class="card card-custom shadow-sm border-0">
                    <div class="card-header bg-white border-bottom py-3 d-flex align-items-center justify-content-between">
                        <div class="d-flex align-items-center">
                            <div class="bg-warning bg-opacity-10 p-2 rounded me-3">
                                <i class="fas fa-list-ul text-warning"></i>
                            </div>
                            <h6 class="m-0 fw-bold text-dark">Routing Queue</h6>
                        </div>
                        <span class="badge bg-secondary-subtle text-secondary border border-secondary-subtle px-3 fw-medium"><?php echo count($deliveries); ?> Pending</span>
                    </div>
                    <div class="card-body p-0">
                        <div class="table-responsive">
                            <table class="table table-hover align-middle mb-0 table-responsive-stack">
                                <thead class="bg-light">
                                    <tr>
                                        <th class="ps-4 py-3 border-0">Shipment</th>
                                        <th class="py-3 border-0">Order Ref</th>
                                        <th class="py-3 border-0">Assigned Agent</th>
                                        <th class="pe-4 py-3 border-0">Destination</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php if(empty($deliveries)): ?>
                                        <tr>
                                            <td colspan="4" class="text-center py-5">
                                                <div class="mb-3">
                                                    <i class="fas fa-check-double fs-1 text-success opacity-25"></i>
                                                </div>
                                                <h6 class="fw-bold text-dark">All Clear!</h6>
                                                <p class="text-muted small m-0 px-4">There are no pending shipments awaiting routing at this time.</p>
                                            </td>
                                        </tr>
                                    <?php else: ?>
                                        <?php foreach($deliveries as $d): ?>
                                            <tr class="transition-all">
                                                <td class="ps-4 py-3 fw-bold" data-label="Shipment">
                                                    <span class="text-dark">#DEL-<?php echo $d['DeliveryID']; ?></span>
                                                </td>
                                                <td data-label="Order Ref" class="py-3">
                                                    <span class="badge bg-light text-dark fw-medium border">ORD-<?php echo $d['OrderID']; ?></span>
                                                </td>
                                                <td data-label="Assigned Agent" class="py-3">
                                                    <div class="d-flex align-items-center">
                                                        <div class="avatar-xs bg-success-subtle text-success rounded-circle me-2 d-flex align-items-center justify-content-center" style="width: 24px; height: 24px; font-size: 0.7rem;">
                                                            <i class="fas fa-user-ninja"></i>
                                                        </div>
                                                        <span class="small fw-medium"><?php echo htmlspecialchars($d['DeliveryPerson']); ?></span>
                                                    </div>
                                                </td>
                                                <td class="pe-4 py-3" data-label="Destination">
                                                    <p class="text-muted small mb-0 text-truncate" style="max-width: 180px;" title="<?php echo htmlspecialchars($d['ShippingAddress']); ?>">
                                                        <i class="fas fa-map-pin me-1 text-danger opacity-50"></i>
                                                        <?php echo htmlspecialchars($d['ShippingAddress']); ?>
                                                    </p>
                                                </td>
                                            </tr>
                                        <?php endforeach; ?>
                                    <?php endif; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>

                <!-- Guidance Info (Responsive Card) -->
                <div class="card card-custom mt-4 border-0 shadow-sm bg-info bg-opacity-10 border-start border-info border-3">
                    <div class="card-body p-4">
                        <div class="d-flex flex-column flex-md-row align-items-center align-items-md-start">
                            <i class="fas fa-lightbulb text-info fs-4 mb-3 mb-md-0 me-md-4"></i>
                            <div>
                                <h6 class="fw-bold text-dark mb-1">Logistics Tip</h6>
                                <p class="text-muted small mb-0">For optimized routing, always verify the <strong>Distance (Km)</strong> using the integrated map satellite service. Accurate estimations reduce delivery delays and fuel consumption.</p>
                            </div>
                        </div>
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
    
    function updateEndLocation(selectObj) {
        var el = selectObj.options[selectObj.selectedIndex];
        var addr = el.getAttribute('data-address');
        if(addr) {
            document.getElementById('end_location').value = addr;
            document.getElementById('end_location').classList.add('bg-success-subtle');
            setTimeout(() => {
                document.getElementById('end_location').classList.remove('bg-success-subtle');
            }, 1000);
        }
    }
</script>
</body>
</html>
