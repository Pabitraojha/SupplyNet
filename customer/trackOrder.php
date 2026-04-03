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

$start_date = isset($_GET['start_date']) ? trim($_GET['start_date']) : '';
$end_date = isset($_GET['end_date']) ? trim($_GET['end_date']) : '';

// Fetch Customer Orders
$orders_query = "
    SELECT 
        o.OrderID, 
        o.OrderDate, 
        o.TotalAmount, 
        o.OrderStatus, 
        o.ShippingAddress, 
        p.ProductName,
        d.DeliveryStatus,
        d.DeliveryDate as ActualDeliveryDate,
        a.AssignedDate,
        r.Distance,
        r.EstimatedTime
    FROM Orders o
    JOIN Products p ON o.ProductID = p.ProductID
    LEFT JOIN Deliveries d ON o.OrderID = d.OrderID
    LEFT JOIN assignRoutes a ON d.DeliveryID = a.DeliveryID
    LEFT JOIN routes r ON a.RouteID = r.RouteID
    WHERE o.UserID = ? AND o.markasdeleted = FALSE
";

if (!empty($start_date)) {
    $orders_query .= " AND DATE(o.OrderDate) >= '" . $conn->real_escape_string($start_date) . "' ";
}
if (!empty($end_date)) {
    $orders_query .= " AND DATE(o.OrderDate) <= '" . $conn->real_escape_string($end_date) . "' ";
}

$orders_query .= " ORDER BY o.OrderDate DESC";

$stmt = $conn->prepare($orders_query);
$stmt->bind_param("i", $user_id);
$stmt->execute();
$orders_result = $stmt->get_result();
$orders = $orders_result->fetch_all(MYSQLI_ASSOC);
$stmt->close();

$total_pending = 0;
$total_delivered = 0;

foreach ($orders as $o_item) {
    $stat = strtolower($o_item['OrderStatus']);
    if (in_array($stat, ['pending', 'processing', 'shipped'])) {
        $total_pending++;
    } elseif ($stat === 'delivered') {
        $total_delivered++;
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Track Order - SupplyNet</title>
    <!-- Fonts -->
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    <!-- Bootstrap CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <!-- FontAwesome -->
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
        
        /* Sidebar Styling */
        .sidebar { width: var(--sidebar-width); height: 100vh; position: fixed; background: linear-gradient(180deg, var(--primary-color) 10%, var(--primary-hover) 100%); color: #fff; z-index: 1000; box-shadow: 2px 0 10px rgba(0,0,0,0.1); }
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

        .dashboard-content { padding: 1.5rem 2rem; flex-grow: 1; }
        
        /* Order Cards Styling */
        .order-card {
            background: #fff;
            border-radius: 1rem;
            box-shadow: 0 0.5rem 1.5rem rgba(0,0,0,0.05);
            border: none;
            margin-bottom: 1.5rem;
            overflow: hidden;
            transition: transform 0.3s ease, box-shadow 0.3s ease;
        }
        
        .order-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 1rem 3rem rgba(0,0,0,0.1);
        }

        .order-header {
            background: rgba(253, 126, 20, 0.05);
            padding: 1.25rem 1.5rem;
            border-bottom: 1px solid rgba(0,0,0,0.05);
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        .order-id {
            font-weight: 700;
            color: var(--dark-color);
            font-size: 1.1rem;
        }

        .order-date {
            color: #6c757d;
            font-size: 0.9rem;
            font-weight: 500;
        }

        .order-body {
            padding: 1.5rem;
        }

        .product-name {
            font-size: 1.25rem;
            font-weight: 700;
            color: var(--dark-color);
            margin-bottom: 0.5rem;
        }

        .order-amount {
            font-size: 1.5rem;
            font-weight: 800;
            color: var(--primary-color);
        }

        /* Tracking Timeline */
        .tracking-timeline {
            display: flex;
            justify-content: space-between;
            align-items: center;
            position: relative;
            margin: 2rem 0;
            padding: 0 1rem;
        }

        .tracking-timeline::before {
            content: '';
            position: absolute;
            top: 50%;
            left: 0;
            right: 0;
            height: 4px;
            background: #e9ecef;
            transform: translateY(-50%);
            z-index: 1;
        }

        .timeline-progress {
            position: absolute;
            top: 50%;
            left: 0;
            height: 4px;
            background: var(--primary-color);
            transform: translateY(-50%);
            z-index: 2;
            transition: width 0.5s ease;
        }

        .timeline-step {
            position: relative;
            z-index: 3;
            text-align: center;
            flex: 1;
        }

        .timeline-icon {
            width: 40px;
            height: 40px;
            border-radius: 50%;
            background: #fff;
            border: 4px solid #e9ecef;
            display: flex;
            align-items: center;
            justify-content: center;
            margin: 0 auto 0.5rem;
            color: #adb5bd;
            font-size: 1rem;
            transition: all 0.3s ease;
            box-shadow: 0 0 0 4px #fff;
        }

        .timeline-step.active .timeline-icon {
            border-color: var(--primary-color);
            color: var(--primary-color);
        }

        .timeline-step.completed .timeline-icon {
            background: var(--primary-color);
            border-color: var(--primary-color);
            color: #fff;
        }

        .timeline-label {
            font-size: 0.85rem;
            font-weight: 600;
            color: #6c757d;
        }

        .timeline-step.active .timeline-label,
        .timeline-step.completed .timeline-label {
            color: var(--dark-color);
        }

        .status-badge {
            padding: 0.35rem 1rem;
            border-radius: 50rem;
            font-weight: 600;
            font-size: 0.85rem;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }

        .status-pending { background: rgba(246, 194, 62, 0.1); color: var(--warning-color); }
        .status-processing { background: rgba(54, 185, 204, 0.1); color: var(--info-color); }
        .status-shipped { background: rgba(253, 126, 20, 0.1); color: var(--primary-color); }
        .status-delivered { background: rgba(28, 200, 138, 0.1); color: var(--success-color); }
        .status-cancelled { background: rgba(231, 74, 59, 0.1); color: var(--danger-color); }

        .empty-state {
            text-align: center;
            padding: 4rem 2rem;
            background: #fff;
            border-radius: 1rem;
            box-shadow: 0 0.5rem 1.5rem rgba(0,0,0,0.05);
        }
        
        .empty-icon {
            font-size: 4rem;
            color: #cbd5e1;
            margin-bottom: 1.5rem;
        }

        @media (max-width: 768px) {
            .sidebar { transform: translateX(-100%); transition: transform 0.3s ease-in-out; }
            .sidebar.show-sidebar { transform: translateX(0); }
            .main-wrapper { margin-left: 0; width: 100%; }
            .sidebar-overlay { display: none; position: fixed; top: 0; left: 0; right: 0; bottom: 0; background: rgba(0,0,0,0.5); z-index: 999; cursor: pointer; }
            .sidebar-overlay.show { display: block; }
            .topbar { padding: 0 1rem; }
            .dashboard-content { padding: 1rem; }
            
            .tracking-timeline { flex-direction: column; align-items: flex-start; padding-left: 1.5rem; margin: 1.5rem 0; }
            .tracking-timeline::before { left: 1.5rem; top: 0; bottom: 0; width: 4px; height: auto; transform: none; }
            .timeline-progress { left: 1.5rem; top: 0; width: 4px; height: 0; transform: none; transition: height 0.5s ease; }
            .timeline-step { display: flex; align-items: center; text-align: left; width: 100%; margin-bottom: 1.5rem; }
            .timeline-step:last-child { margin-bottom: 0; }
            .timeline-icon { margin: 0 1rem 0 -1.15rem; }
            .timeline-label { margin-top: 0; }
        }
    </style>
</head>
<body>

<!-- Sidebar -->
<div class="sidebar">
    <a href="index.php" class="brand"><i class="fas fa-cubes me-2"></i>SupplyNet<br><small class="text-white-50" style="font-size: 0.7rem;">Customer Portal</small></a>
    <div class="mt-4">
        <div class="nav-item"><a href="index.php" class="nav-link"><i class="fas fa-home"></i> Dashboard</a></div>
        <div class="nav-item"><a href="trackOrder.php" class="nav-link active"><i class="fas fa-satellite-dish"></i> Track Order</a></div>
        <div class="nav-item"><a href="submitFeedback.php" class="nav-link"><i class="fas fa-comment-dots"></i> Feedback</a></div>
    </div>
</div>

<div class="sidebar-overlay" id="sidebarOverlay"></div>
<div class="main-wrapper">
    <div class="topbar">
        <div class="d-flex align-items-center">
            <button class="btn btn-link d-md-none text-dark me-2 px-0" id="sidebarToggle"><i class="fas fa-bars fs-5"></i></button>
            <div class="ms-2 ms-md-0">
                <h4 class="m-0 fw-bold text-dark fs-5 fs-md-4">Track Orders</h4>
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
        <!-- Date Filter -->
        <div class="card card-custom mb-4 p-3 shadow-sm" style="border-radius: 1rem; border: none;">
            <form method="GET" class="row gx-2 gy-2 align-items-center">
                <div class="col-auto"><label class="col-form-label fw-bold" style="color: var(--dark-color);"><i class="fas fa-filter me-2 text-primary"></i>Filter by Date:</label></div>
                <div class="col-auto">
                    <input type="date" name="start_date" class="form-control form-control-sm" value="<?php echo htmlspecialchars($start_date); ?>" max="<?php echo date('Y-m-d'); ?>">
                </div>
                <div class="col-auto text-muted">to</div>
                <div class="col-auto">
                    <input type="date" name="end_date" class="form-control form-control-sm" value="<?php echo htmlspecialchars($end_date); ?>" max="<?php echo date('Y-m-d'); ?>">
                </div>
                <div class="col-auto">
                    <button type="submit" class="btn btn-sm btn-primary shadow-sm" style="background-color: var(--primary-color); border:none;"><i class="fas fa-search"></i> Apply</button>
                    <?php if(!empty($start_date) || !empty($end_date)): ?>
                        <a href="trackOrder.php" class="btn btn-sm btn-outline-secondary shadow-sm"><i class="fas fa-times"></i> Clear</a>
                    <?php endif; ?>
                </div>
            </form>
        </div>

        <!-- Metrics Boxes -->
        <div class="row g-3 mb-4">
            <div class="col-md-6">
                <div class="card card-custom p-3 shadow-sm border-start border-4 border-info h-100" style="border-radius: 1rem;">
                    <div class="d-flex align-items-center">
                        <div class="flex-shrink-0 bg-info bg-opacity-10 text-info rounded p-3 me-3">
                            <i class="fas fa-truck-loading fa-2x"></i>
                        </div>
                        <div>
                            <div class="text-uppercase text-muted fw-bold small">Total Pending / In-Transit Products</div>
                            <div class="fs-4 fw-bold text-dark"><?php echo $total_pending; ?></div>
                        </div>
                    </div>
                </div>
            </div>
            <div class="col-md-6">
                <div class="card card-custom p-3 shadow-sm border-start border-4 border-success h-100" style="border-radius: 1rem;">
                    <div class="d-flex align-items-center">
                        <div class="flex-shrink-0 bg-success bg-opacity-10 text-success rounded p-3 me-3">
                            <i class="fas fa-check-circle fa-2x"></i>
                        </div>
                        <div>
                            <div class="text-uppercase text-muted fw-bold small">Total Delivered Products</div>
                            <div class="fs-4 fw-bold text-dark"><?php echo $total_delivered; ?></div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <?php if (empty($orders)): ?>
            <div class="empty-state">
                <div class="empty-icon"><i class="fas fa-box-open"></i></div>
                <h3 class="fw-bold text-dark mb-3">No Orders Found</h3>
                <p class="text-muted mb-4">You haven't placed any orders yet. Once you do, you can track their status here.</p>
                <a href="index.php" class="btn btn-primary px-4 py-2"><i class="fas fa-shopping-cart me-2"></i>Start Shopping</a>
            </div>
        <?php else: ?>
            <div class="row">
                <?php foreach ($orders as $order): 
                    $status = strtolower($order['OrderStatus']);
                    
                    // Determine timeline progress
                    $progress = 0;
                    $steps = ['pending' => 0, 'processing' => 1, 'shipped' => 2, 'delivered' => 3];
                    $current_step = isset($steps[$status]) ? $steps[$status] : 0;
                    
                    if ($status === 'cancelled') {
                        $progress = 100; // Fully filled but red
                    } else {
                        $progress = ($current_step / 3) * 100;
                    }

                    // Format Dates
                    $orderDate = new DateTime($order['OrderDate']);
                    $formattedDate = $orderDate->format('M d, Y, h:i A');
                ?>
                <div class="col-12">
                    <div class="order-card">
                        <div class="order-header">
                            <div>
                                <div class="order-id">Order #<?php echo str_pad($order['OrderID'], 5, '0', STR_PAD_LEFT); ?></div>
                                <div class="order-date"><i class="far fa-calendar-alt me-1"></i><?php echo $formattedDate; ?></div>
                            </div>
                            <div>
                                <span class="status-badge status-<?php echo $status; ?>">
                                    <?php echo ucfirst($status); ?>
                                </span>
                            </div>
                        </div>
                        <div class="order-body">
                            <div class="row align-items-center">
                                <div class="col-md-6 mb-4 mb-md-0">
                                    <div class="text-muted text-uppercase fw-bold" style="font-size: 0.75rem; letter-spacing: 1px;">Product Details</div>
                                    <div class="product-name"><?php echo htmlspecialchars($order['ProductName']); ?></div>
                                    <div class="order-amount">₹<?php echo number_format($order['TotalAmount'], 2); ?></div>
                                    <div class="mt-3">
                                        <div class="text-muted text-uppercase fw-bold mb-1" style="font-size: 0.75rem; letter-spacing: 1px;">Shipping Address</div>
                                        <p class="mb-0 text-dark"><?php echo htmlspecialchars($order['ShippingAddress']); ?></p>
                                    </div>
                                </div>
                                <div class="col-md-6">
                                    <?php if ($status === 'cancelled'): ?>
                                        <div class="alert alert-danger mb-0 text-center">
                                            <i class="fas fa-times-circle fs-3 mb-2 d-block"></i>
                                            <strong>Order Cancelled</strong>
                                            <p class="mb-0 small mt-1">This order has been cancelled and will not be delivered.</p>
                                        </div>
                                    <?php else: ?>
                                        <div class="tracking-timeline">
                                            <div class="timeline-progress d-none d-md-block" style="width: <?php echo $progress; ?>%;"></div>
                                            <div class="timeline-progress d-md-none" style="height: <?php echo $progress; ?>%;"></div>
                                            
                                            <div class="timeline-step <?php echo $current_step >= 0 ? 'completed' : ''; ?>">
                                                <div class="timeline-icon"><i class="fas fa-clipboard-list"></i></div>
                                                <div class="timeline-label">Placed</div>
                                            </div>
                                            
                                            <div class="timeline-step <?php echo $current_step >= 1 ? 'completed' : ($current_step == 0 ? 'active' : ''); ?>">
                                                <div class="timeline-icon"><i class="fas fa-cog"></i></div>
                                                <div class="timeline-label">Processing</div>
                                            </div>
                                            
                                            <div class="timeline-step <?php echo $current_step >= 2 ? 'completed' : ($current_step == 1 ? 'active' : ''); ?>">
                                                <div class="timeline-icon"><i class="fas fa-truck"></i></div>
                                                <div class="timeline-label">Shipped</div>
                                            </div>
                                            
                                            <div class="timeline-step <?php echo $current_step >= 3 ? 'completed' : ($current_step == 2 ? 'active' : ''); ?>">
                                                <div class="timeline-icon"><i class="fas fa-home"></i></div>
                                                <div class="timeline-label">Delivered</div>
                                            </div>
                                        </div>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
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
