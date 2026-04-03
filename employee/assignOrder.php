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

// Check and add requirement fields if missing
$check_col = $conn->query("SHOW COLUMNS FROM Deliveries LIKE 'AdditionalRequirements'");
if ($check_col && $check_col->num_rows == 0) {
    $conn->query("ALTER TABLE Deliveries ADD COLUMN AdditionalRequirements TEXT DEFAULT NULL");
}

// Handle Order Assignment & Status Update
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'assign_delivery') {
    $order_id = intval($_POST['order_id']);
    $delivery_person_id = intval($_POST['delivery_person_id']);
    $est_time = trim($_POST['estimated_time']);
    $additional_reqs = trim($_POST['additional_requirements']);
    
    if ($order_id > 0 && $delivery_person_id > 0 && !empty($est_time)) {
        // Format datetime correctly
        $delivery_date = date('Y-m-d H:i:s', strtotime($est_time));
        
        $conn->begin_transaction();
        try {
            // Check if already assigned
            $check = $conn->query("SELECT DeliveryID FROM Deliveries WHERE OrderID = $order_id AND markasdeleted=0");
            if ($check->num_rows > 0) {
                throw new Exception("Order #$order_id is already assigned.");
            }
            
            // Insert into Deliveries with requirements
            $stmt = $conn->prepare("INSERT INTO Deliveries (OrderID, DeliveryStatus, DeliveryPersonID, DeliveryDate, AdditionalRequirements) VALUES (?, 'pending', ?, ?, ?)");
            $stmt->bind_param("iiss", $order_id, $delivery_person_id, $delivery_date, $additional_reqs);
            $stmt->execute();
            
            // Auto Update OrderStatus to 'shipped' as per performance workflow
            $stmt_status = $conn->prepare("UPDATE Orders SET OrderStatus = 'shipped' WHERE OrderID = ? AND OrderStatus = 'processing'");
            $stmt_status->bind_param("i", $order_id);
            $stmt_status->execute();
            
            $conn->commit();
            $message = "Order #$order_id assigned to delivery. Status auto-updated to Shipped.";
            $message_type = "success";
        } catch (Exception $e) {
            $conn->rollback();
            $message = "Failed to assign delivery: " . $e->getMessage();
            $message_type = "danger";
        }
    } else {
        $message = "Invalid order, delivery person, or estimated time selected.";
        $message_type = "danger";
    }
}

// Fetch Orders that are Processing (Approved) and need assignment
$unassigned_orders = [];
$res = $conn->query("
    SELECT o.OrderID, o.OrderDate, o.ShippingAddress, u.UserName as CustomerName, o.RequiredDate
    FROM Orders o
    JOIN Users u ON o.UserID = u.UserID
    LEFT JOIN Deliveries d ON o.OrderID = d.OrderID AND d.markasdeleted = 0
    WHERE o.markasdeleted = 0 AND o.OrderStatus = 'processing' AND d.DeliveryID IS NULL
    ORDER BY o.RequiredDate ASC, o.OrderDate ASC
");
if ($res) {
    while($row = $res->fetch_assoc()) $unassigned_orders[] = $row;
}

// Fetch active delivery personnel
$delivery_personnel = [];
$dp_res = $conn->query("SELECT UserID, UserName FROM Users WHERE Role = 'delivery_person' AND markasdeleted = 0 AND IsActive = 1");
if ($dp_res) {
    while($row = $dp_res->fetch_assoc()) $delivery_personnel[] = $row;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Assign Deliveries - SupplyNet</title>
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
        .card-custom { border: none; border-radius: 0.75rem; box-shadow: 0 0.15rem 1.75rem 0 rgba(58, 59, 69, 0.1); background: #fff; overflow:hidden;}
        .table > :not(caption) > * > * { padding: 1rem; }
        .assign-cell { background-color: rgba(28,200,138,0.03); border-left: 1px dashed #e3e6f0; }

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
        <div class="nav-item"><a href="assignOrder.php" class="nav-link active"><i class="fas fa-clipboard-check"></i> Assign Orders</a></div>
        <div class="nav-item"><a href="routeAssign.php" class="nav-link"><i class="fas fa-map-marked-alt"></i> Route Assign</a></div>
    </div>
</div>

<div class="sidebar-overlay" id="sidebarOverlay"></div>
<div class="main-wrapper">
    <div class="topbar">
        <div class="d-flex align-items-center">
            <button class="btn btn-link d-md-none text-dark me-2 px-0" id="sidebarToggle"><i class="fas fa-bars fs-5"></i></button>
            <div class="ms-2 ms-md-0">
                <h4 class="m-0 fw-bold text-dark fs-5 fs-md-4">Assign Deliveries</h4>
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

        <div class="card card-custom border-0 shadow-sm bg-primary bg-opacity-10 border-start border-primary border-4 mb-4">
            <div class="card-body p-3 p-md-4">
                <div class="d-flex align-items-center">
                    <div class="bg-primary bg-opacity-25 p-3 rounded-circle me-3">
                        <i class="fas fa-info-circle text-primary fs-4"></i>
                    </div>
                    <div>
                        <h6 class="fw-bold text-dark mb-1">Operational Guidance</h6>
                        <p class="text-muted small mb-0">Displaying <strong>Processing</strong> orders. Assigning an agent will instantly transition this shipment to <strong>Shipped</strong> status and notify the client.</p>
                    </div>
                </div>
            </div>
        </div>

        <div class="card card-custom shadow-sm border-0">
            <div class="card-header bg-white border-bottom py-3 d-flex align-items-center justify-content-between">
                <div class="d-flex align-items-center">
                    <div class="bg-dark bg-opacity-10 p-2 rounded me-3 text-dark">
                        <i class="fas fa-clipboard-list"></i>
                    </div>
                    <h6 class="m-0 fw-bold text-dark">Assignment Control Panel</h6>
                </div>
                <span class="badge bg-success-subtle text-success border border-success-subtle px-3 py-2"><?php echo count($unassigned_orders); ?> Pending</span>
            </div>
            <div class="card-body p-0">
                <div class="table-responsive">
                    <table class="table align-middle mb-0 table-responsive-stack">
                        <thead class="bg-light">
                            <tr>
                                <th class="ps-4 py-3 text-uppercase small fw-bold border-0">Shipment Ref</th>
                                <th class="py-3 text-uppercase small fw-bold border-0">Client & Logistics</th>
                                <th class="pe-4 py-3 text-uppercase small fw-bold border-0 assign-cell" style="min-width: 320px;">Agent Allocation</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if(empty($unassigned_orders)): ?>
                                <tr>
                                    <td colspan="3" class="text-center py-5">
                                        <div class="mb-3">
                                            <i class="fas fa-box-open fs-1 text-success opacity-25"></i>
                                        </div>
                                        <h6 class="fw-bold text-dark">Cargo Manifest Clear</h6>
                                        <p class="text-muted small m-0 px-4">There are no approved orders awaiting dispatch at this time.</p>
                                    </td>
                                </tr>
                            <?php else: ?>
                                <?php foreach($unassigned_orders as $o): ?>
                                    <tr class="transition-all">
                                        <td class="ps-4 py-4" data-label="Shipment Ref">
                                            <div class="d-flex align-items-center mb-1">
                                                <span class="fw-bold text-dark fs-5">#ORD-<?php echo $o['OrderID']; ?></span>
                                            </div>
                                            <div class="text-muted small d-flex align-items-center">
                                                <i class="far fa-calendar-alt me-2 text-primary opacity-50"></i>
                                                Placed: <?php echo date('M d, Y', strtotime($o['OrderDate'])); ?>
                                            </div>
                                            <?php if(!empty($o['RequiredDate'])): ?>
                                                <div class="text-danger small mt-2 fw-bold d-flex align-items-center">
                                                    <i class="fas fa-bolt me-2 opacity-75"></i>
                                                    Deadline: <?php echo date('M d, Y', strtotime($o['RequiredDate'])); ?>
                                                </div>
                                            <?php endif; ?>
                                        </td>
                                        <td data-label="Client & Logistics" class="py-4">
                                            <div class="mb-3">
                                                <span class="badge bg-primary-subtle text-primary border border-primary-subtle px-2 fw-semibold">
                                                    <i class="fas fa-user-circle me-1"></i><?php echo htmlspecialchars($o['CustomerName']); ?>
                                                </span>
                                            </div>
                                            <div class="p-3 rounded-3 bg-light border-0 small" style="background: linear-gradient(to right, #f8f9fc, #ffffff);">
                                                <div class="fw-bold text-dark mb-1 text-uppercase " style="font-size: 0.65rem; letter-spacing: 0.5px;">
                                                    <i class="fas fa-map-pin text-danger me-1"></i> Shipping Destination
                                                </div>
                                                <span class="text-secondary d-block mt-1"><?php echo htmlspecialchars($o['ShippingAddress']); ?></span>
                                            </div>
                                        </td>
                                        <td class="pe-4 py-4 assign-cell" data-label="Agent Allocation">
                                            <form method="POST" class="d-flex flex-column gap-3">
                                                <input type="hidden" name="action" value="assign_delivery">
                                                <input type="hidden" name="order_id" value="<?php echo $o['OrderID']; ?>">
                                                
                                                <div class="input-group">
                                                    <span class="input-group-text bg-white border-end-0 text-muted"><i class="fas fa-id-card-alt"></i></span>
                                                    <select name="delivery_person_id" class="form-select border-start-0 ps-0 text-dark fw-medium" required style="font-size: 0.9rem;">
                                                        <option value="" disabled selected>Dispatch Personnel...</option>
                                                        <?php foreach($delivery_personnel as $dp): ?>
                                                            <option value="<?php echo $dp['UserID']; ?>"><?php echo htmlspecialchars($dp['UserName']); ?></option>
                                                        <?php endforeach; ?>
                                                    </select>
                                                </div>
                                                
                                                <div class="row g-2">
                                                    <div class="col-6">
                                                        <div class="input-group">
                                                            <span class="input-group-text bg-white border-end-0 text-muted" title="Departure Time"><i class="fas fa-clock"></i></span>
                                                            <input type="datetime-local" name="estimated_time" class="form-control border-start-0 ps-0" required style="font-size: 0.85rem;">
                                                        </div>
                                                    </div>
                                                    <div class="col-6">
                                                        <div class="input-group">
                                                            <span class="input-group-text bg-white border-end-0 text-muted"><i class="fas fa-sticky-note"></i></span>
                                                            <input type="text" name="additional_requirements" class="form-control border-start-0 ps-0" placeholder="Notes..." style="font-size: 0.85rem;">
                                                        </div>
                                                    </div>
                                                </div>
                                                
                                                <button type="submit" class="btn btn-primary shadow-sm w-100 fw-bold d-flex align-items-center justify-content-center py-2" style="background-color: var(--primary-color); border:none;">
                                                    <i class="fas fa-shipping-fast me-2"></i> Deploy Shipment
                                                </button>
                                            </form>
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
