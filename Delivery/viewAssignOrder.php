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

// Handle delivery status update
$message = '';
$message_type = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    $action = $_POST['action'];
    $delivery_id = intval($_POST['delivery_id']);
    $order_id = intval($_POST['order_id']);
    
    if (in_array($action, ['approve', 'cancel'])) {
        $del_status = ($action === 'approve') ? 'delivered' : 'cancelled';
        $ord_status = ($action === 'approve') ? 'delivered' : 'cancelled';
        $msg_text = ($action === 'approve') ? 'successfully delivered!' : 'marked as cancelled.';
        $msg_type = ($action === 'approve') ? 'success' : 'warning';

        $conn->begin_transaction();
        try {
            $stmt1 = $conn->prepare("UPDATE Deliveries SET DeliveryStatus = ? WHERE DeliveryID = ? AND DeliveryPersonID = ?");
            $stmt1->bind_param("sii", $del_status, $delivery_id, $user_id);
            $stmt1->execute();
            
            $stmt2 = $conn->prepare("UPDATE Orders SET OrderStatus = ? WHERE OrderID = ?");
            $stmt2->bind_param("si", $ord_status, $order_id);
            $stmt2->execute();
            
            $conn->commit();
            $message = "Delivery #$delivery_id $msg_text";
            $message_type = $msg_type;
        } catch (Exception $e) {
            $conn->rollback();
            $message = "Failed to update delivery status: " . $e->getMessage();
            $message_type = "danger";
        }
    }
}

// Fetch Active Assigned Orders for this Delivery Agent
$raw_orders = [];
$grouped_orders = [];

$query = "
    SELECT 
        d.DeliveryID, 
        d.DeliveryStatus,
        o.OrderID, 
        u.UserID as CustomerID,
        u.UserName as CustomerName, 
        u.mobile_number as CustomerPhone,
        o.ShippingAddress as Address, 
        o.OrderDate, 
        d.DeliveryDate, 
        o.TotalAmount, 
        o.RequiredDate,
        d.AdditionalRequirements,
        r.EndLocation
    FROM Deliveries d
    JOIN Orders o ON d.OrderID = o.OrderID
    JOIN Users u ON o.UserID = u.UserID
    LEFT JOIN assignRoutes ar ON d.DeliveryID = ar.DeliveryID AND ar.markasdeleted = 0
    LEFT JOIN routes r ON ar.RouteID = r.RouteID AND r.markasdeleted = 0
    WHERE d.DeliveryPersonID = ? 
      AND d.markasdeleted = 0 
      AND d.DeliveryStatus IN ('pending', 'in_transit')
    ORDER BY d.DeliveryDate ASC
";

$stmt = $conn->prepare($query);
$stmt->bind_param("i", $user_id);
$stmt->execute();
$result = $stmt->get_result();

if ($result) {
    while($row = $result->fetch_assoc()) {
        $c_id = $row['CustomerID'];
        if (!isset($grouped_orders[$c_id])) {
            $grouped_orders[$c_id] = [
                'CustomerName' => $row['CustomerName'],
                'CustomerPhone' => $row['CustomerPhone'],
                'Address' => $row['Address'],
                'Deliveries' => []
            ];
        }
        $grouped_orders[$c_id]['Deliveries'][] = $row;
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Assigned Orders - SupplyNet</title>
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
        .search-box { max-width: 350px; position: relative; }
        .search-box input { padding-left: 2.5rem; border-radius: 50rem; border: 1px solid #d1d3e2; }
        .search-box input:focus { border-color: var(--primary-color); box-shadow: 0 0 0 0.25rem rgba(231, 74, 59, 0.25); }
        .search-box i { position: absolute; left: 1rem; top: 50%; transform: translateY(-50%); color: #b7b9cc; }
        
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
            .search-box { max-width: 100%; margin-bottom: 1rem; }
        }
    </style>
</head>
<body>

<!-- Sidebar -->
<div class="sidebar">
    <a href="index.php" class="brand"><i class="fas fa-cubes me-2"></i>SupplyNet<br><small class="text-white-50" style="font-size: 0.7rem;">Delivery Agent</small></a>
    <div class="mt-4">
        <div class="nav-item"><a href="index.php" class="nav-link"><i class="fas fa-tachometer-alt"></i> Dashboard</a></div>
        <div class="nav-item"><a href="viewAssignOrder.php" class="nav-link active"><i class="fas fa-clipboard-list"></i> Assigned Orders</a></div>
        <div class="nav-item"><a href="deliverOrder.php" class="nav-link"><i class="fas fa-truck"></i> Delivery History</a></div>
    </div>
</div>

<div class="sidebar-overlay" id="sidebarOverlay"></div>
<div class="main-wrapper">
    <div class="topbar">
        <div class="d-flex align-items-center">
            <button class="btn btn-link d-md-none text-dark me-2 px-0" id="sidebarToggle"><i class="fas fa-bars fs-5"></i></button>
            <div class="ms-2 ms-md-0">
                <h4 class="m-0 fw-bold text-dark fs-5 fs-md-4">Delivery Assignments</h4>
                <p class="text-muted small mb-0 d-none d-md-block">Active orders mapped to your route.</p>
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
        <?php if($message): ?>
            <div class="alert alert-<?php echo $message_type; ?> alert-dismissible fade show shadow-sm">
                <?php echo ($message_type === 'success') ? '<i class="fas fa-check-circle me-2"></i>' : '<i class="fas fa-exclamation-triangle me-2"></i>'; ?>
                <?php echo htmlspecialchars($message); ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
        <?php endif; ?>

        <!-- Corporate Info Banner -->
        <div class="alert alert-info border-info bg-info bg-opacity-10 d-flex flex-column flex-md-row align-items-md-center justify-content-between mb-4 shadow-sm p-4 rounded-3 border-0" style="border-left: 5px solid var(--info-color) !important;">
            <div class="d-flex align-items-center mb-3 mb-md-0">
                <div class="bg-white p-3 rounded-circle shadow-sm me-3 d-flex align-items-center justify-content-center" style="width: 60px; height: 60px;">
                    <i class="fas fa-shield-alt fs-2 text-info"></i>
                </div>
                <div>
                    <h5 class="fw-bold text-dark m-0">Corporate Logistics Protocol</h5>
                    <p class="text-secondary small m-0 mt-1">Please ensure all deliveries meet strict compliance protocols. Contact dispatch immediately for any routing discrepancies. Collect signatures where necessary.</p>
                </div>
            </div>
            <div class="text-md-end">
                <div class="fw-bold text-dark"><i class="fas fa-headset text-danger me-2"></i>Emergency Dispatch:</div>
                <div class="text-primary fw-semibold fs-5">1-800-SUPPLYNET</div>
            </div>
        </div>

        <div class="card card-custom shadow-sm">
            <div class="card-header bg-white border-bottom p-4 d-flex flex-column flex-md-row justify-content-between align-items-md-center gap-3">
                <h6 class="m-0 font-weight-bold fs-5" style="color: var(--primary-color);">
                    <i class="fas fa-map-marked-alt me-2"></i>My Active Assignments
                </h6>
                <div class="search-box">
                    <i class="fas fa-search"></i>
                    <input type="text" id="orderSearch" class="form-control" placeholder="Search orders, clients, addresses...">
                </div>
            </div>
            
            <div class="card-body p-0">
                <div class="table-responsive">
                    <table class="table table-custom align-middle" id="ordersTable">
                        <thead>
                            <tr>
                                <th class="ps-4">Assignment ID</th>
                                <th>Client Details & Address</th>
                                <th>Timelines</th>
                                <th>Amount to Collect</th>
                                <th>Routing Details</th>
                                <th class="pe-4 text-center">Action</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if(empty($grouped_orders)): ?>
                                <tr>
                                    <td colspan="6" class="text-center py-5">
                                        <div class="text-muted"><i class="fas fa-box fs-1 mb-3 text-light"></i></div>
                                        <h5 class="text-muted fw-bold">No Active Deliveries</h5>
                                        <p class="text-muted small">You currently have no pending orders assigned to your route.</p>
                                    </td>
                                </tr>
                            <?php else: ?>
                                <?php foreach($grouped_orders as $c_id => $group): ?>
                                    <tr class="bg-light border-top border-2 border-danger border-opacity-25 searchable-row">
                                        <td colspan="5">
                                            <div class="d-flex align-items-center gap-3">
                                                <div class="bg-white p-2 rounded-circle shadow-sm">
                                                    <i class="fas fa-user-circle fs-3 text-secondary"></i>
                                                </div>
                                                <div>
                                                    <h6 class="fw-bold text-dark m-0 fs-5"><?php echo htmlspecialchars($group['CustomerName']); ?></h6>
                                                    <div class="text-muted small fw-semibold mt-1">
                                                        <i class="fas fa-phone-alt text-primary me-1"></i> 
                                                        <a href="tel:<?php echo htmlspecialchars($group['CustomerPhone']); ?>" class="text-decoration-none text-primary fw-bold me-3">
                                                            <?php echo htmlspecialchars($group['CustomerPhone'] ?? 'No Phone'); ?>
                                                        </a>
                                                        <i class="fas fa-map-marker-alt text-danger me-1"></i> <?php echo htmlspecialchars($group['Address']); ?>
                                                        <span class="mx-2">|</span>
                                                        <i class="fas fa-box text-primary me-1"></i> <?php echo count($group['Deliveries']); ?> package(s)
                                                    </div>
                                                </div>
                                            </div>
                                        </td>
                                        <td class="text-center align-middle">
                                            <a href="generateReport.php?customer_id=<?php echo $c_id; ?>" target="_blank" class="btn btn-sm btn-outline-dark fw-bold rounded-pill shadow-sm hover-lift px-3">
                                                <i class="fas fa-file-signature me-1"></i> <?php echo (count($group['Deliveries']) > 1) ? 'Bulk Report' : 'Individual Report'; ?>
                                            </a>
                                        </td>
                                    </tr>
                                    <?php foreach($group['Deliveries'] as $o): ?>
                                        <tr class="searchable-row">
                                            <td class="ps-4">
                                                <div class="fw-bold text-dark fs-5">#DEL-<?php echo str_pad($o['DeliveryID'], 4, '0', STR_PAD_LEFT); ?></div>
                                                <div class="text-muted small fw-semibold"><i class="fas fa-shopping-cart text-primary me-1"></i>Ord #<?php echo $o['OrderID']; ?></div>
                                                
                                                <?php 
                                                $s_class = ($o['DeliveryStatus'] == 'in_transit') ? 'bg-primary' : 'bg-warning text-dark';
                                                $s_icon = ($o['DeliveryStatus'] == 'in_transit') ? 'fa-truck-fast' : 'fa-hourglass-half';
                                                $s_text = ($o['DeliveryStatus'] == 'in_transit') ? 'In Transit' : 'Pending Load';
                                                ?>
                                                <div class="badge badge-status <?php echo $s_class; ?> mt-2"><i class="fas <?php echo $s_icon; ?> me-1"></i><?php echo $s_text; ?></div>
                                            </td>
                                            
                                            <td>
                                                <div class="fw-bold text-dark mb-1"><i class="fas fa-user-circle text-secondary me-2"></i><?php echo htmlspecialchars($group['CustomerName']); ?></div>
                                                <div class="small mb-2 pt-1">
                                                    <a href="tel:<?php echo htmlspecialchars($o['CustomerPhone']); ?>" class="badge bg-primary text-white text-decoration-none p-2 shadow-sm hover-lift" style="font-size:0.75rem;">
                                                        <i class="fas fa-phone-alt me-1"></i> <?php echo htmlspecialchars($o['CustomerPhone'] ?? 'Call Customer'); ?>
                                                    </a>
                                                </div>
                                            </td>
                                            
                                            <td>
                                                <div class="mb-2">
                                                    <div class="small text-muted text-uppercase fw-bold" style="font-size: 0.7rem;">Order Placed</div>
                                                    <div class="fw-semibold text-dark"><i class="far fa-calendar-alt text-secondary me-1"></i><?php echo date('M d, Y', strtotime($o['OrderDate'])); ?></div>
                                                </div>
                                                <div>
                                                    <div class="small text-muted text-uppercase fw-bold" style="font-size: 0.7rem;">Est. Arrival Target</div>
                                                    <div class="fw-bold text-primary"><i class="far fa-clock text-primary me-1"></i><?php echo date('M d, Y - h:i A', strtotime($o['DeliveryDate'])); ?></div>
                                                </div>
                                            </td>
                                            
                                            <td>
                                                <div class="fw-bold text-success fs-5">₹<?php echo number_format($o['TotalAmount'], 2); ?></div>
                                                <div class="small text-muted fw-semibold mt-1">Pre-paid / Collect</div>
                                            </td>
                                            
                                            <td>
                                                <?php if(!empty($o['AdditionalRequirements'])): ?>
                                                    <div class="alert alert-warning py-1 px-2 border-warning text-dark small m-0 mb-2 rounded border-opacity-25 bg-warning bg-opacity-10 d-flex gap-2 align-items-start">
                                                        <i class="fas fa-exclamation-circle text-warning mt-1"></i>
                                                        <div><span class="fw-bold">Notes:</span> <?php echo htmlspecialchars($o['AdditionalRequirements']); ?></div>
                                                    </div>
                                                <?php else: ?>
                                                    <span class="text-muted small fst-italic">No special notes.</span>
                                                <?php endif; ?>
                                            </td>
                                            
                                            <td class="pe-4 text-center">
                                                <form method="POST" class="d-flex flex-column gap-2">
                                                    <input type="hidden" name="delivery_id" value="<?php echo $o['DeliveryID']; ?>">
                                                    <input type="hidden" name="order_id" value="<?php echo $o['OrderID']; ?>">
                                                    
                                                    <button type="submit" name="action" value="approve" class="btn btn-success btn-sm rounded-pill fw-bold shadow-sm w-100" title="Confirm Delivery Completion">
                                                        <i class="fas fa-check me-1"></i> Approve
                                                    </button>
                                                    <button type="submit" name="action" value="cancel" class="btn btn-danger btn-sm rounded-pill fw-bold shadow-sm w-100" title="Cancel Delivery">
                                                        <i class="fas fa-times me-1"></i> Cancel
                                                    </button>
                                                </form>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
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
    
    // Live Search Functionality
    document.getElementById('orderSearch').addEventListener('keyup', function() {
        let filter = this.value.toLowerCase();
        let rows = document.querySelectorAll('.searchable-row');
        
        let visibleCount = 0;
        rows.forEach(function(row) {
            let text = row.textContent.toLowerCase();
            if(text.includes(filter)) {
                row.style.display = '';
                visibleCount++;
            } else {
                row.style.display = 'none';
            }
        });
        
        // Handle empty search results feedback (optional enhancement)
        let tbody = document.querySelector('#ordersTable tbody');
        let emptyMsg = document.getElementById('emptySearchRow');
        
        if (visibleCount === 0 && rows.length > 0) {
            if (!emptyMsg) {
                tbody.insertAdjacentHTML('beforeend', '<tr id="emptySearchRow"><td colspan="6" class="text-center py-4 text-muted"><i class="fas fa-search-minus fs-2 mb-3"></i><br>No matching assignments found for "'+htmlspecialchars(this.value)+'".</td></tr>');
            } else {
                emptyMsg.style.display = '';
                emptyMsg.innerHTML = '<td colspan="6" class="text-center py-4 text-muted"><i class="fas fa-search-minus fs-2 mb-3"></i><br>No matching assignments found.</td>';
            }
        } else if (emptyMsg) {
            emptyMsg.style.display = 'none';
        }
    });

    // Helper for safely inserting user input back into HTML context (defense against very basic XSS in DOM flow)
    function htmlspecialchars(str) {
        if (typeof(str) == "string") {
            str = str.replace(/&/g, "&amp;").replace(/"/g, "&quot;").replace(/'/g, "&#039;").replace(/</g, "&lt;").replace(/>/g, "&gt;");
        }
        return str;
    }
</script>
</body>
</html>
