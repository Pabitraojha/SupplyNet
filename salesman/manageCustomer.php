<?php
session_start();
require_once '../config/db.php';

// Auth Protection: Check if user is logged in and is a salesman
if (!isset($_SESSION['UserID']) || strtolower($_SESSION['Role']) !== 'salesman') {
    header("Location: ../login.php");
    exit();
}

$salesman_name = $_SESSION['UserName'] ?? 'Sales Representative';
$salesman_id = $_SESSION['UserID'];

$success_msg = '';
$error_msg = '';

// Handle Actions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['action'])) {
        $action = $_POST['action'];
        
        if ($action === 'add_customer') {
            $name = mysqli_real_escape_string($conn, $_POST['name']);
            $email = mysqli_real_escape_string($conn, $_POST['email']);
            $mobile = mysqli_real_escape_string($conn, $_POST['mobile']);
            $address = mysqli_real_escape_string($conn, $_POST['address']);
            $password = password_hash('customer123', PASSWORD_DEFAULT); // Default password
            
            // Check if email exists
            $check = mysqli_query($conn, "SELECT UserID FROM Users WHERE Email='$email'");
            if (mysqli_num_rows($check) > 0) {
                $error_msg = "A user with this email already exists.";
            } else {
                // Ensure created_by column exists
                $check_col = mysqli_query($conn, "SHOW COLUMNS FROM Users LIKE 'created_by'");
                if (mysqli_num_rows($check_col) == 0) {
                    mysqli_query($conn, "ALTER TABLE Users ADD COLUMN created_by INT DEFAULT NULL");
                }
                
                $query = "INSERT INTO Users (UserName, Email, Password, Role, mobile_number, address, approval_status, IsActive, markasdeleted, created_by) 
                          VALUES ('$name', '$email', '$password', 'customer', '$mobile', '$address', 'approved', 1, 0, $salesman_id)";
                if (mysqli_query($conn, $query)) {
                    $success_msg = "Customer created successfully. Default password is 'customer123'.";
                } else {
                    $error_msg = "Failed to create customer: " . mysqli_error($conn);
                }
            }
        } elseif ($action === 'edit_customer') {
            $customer_id = (int)$_POST['customer_id'];
            $name = mysqli_real_escape_string($conn, $_POST['name']);
            $email = mysqli_real_escape_string($conn, $_POST['email']);
            $mobile = mysqli_real_escape_string($conn, $_POST['mobile']);
            $address = mysqli_real_escape_string($conn, $_POST['address']);
            
            // Check email uniquely
            $check = mysqli_query($conn, "SELECT UserID FROM Users WHERE Email='$email' AND UserID != $customer_id");
            if (mysqli_num_rows($check) > 0) {
                $error_msg = "Email is already used by another user.";
            } else {
                $query = "UPDATE Users SET UserName='$name', Email='$email', mobile_number='$mobile', address='$address' WHERE UserID=$customer_id AND Role='customer'";
                if (mysqli_query($conn, $query)) {
                    $success_msg = "Customer updated successfully.";
                } else {
                    $error_msg = "Failed to update customer.";
                }
            }
        } elseif ($action === 'toggle_block') {
            $customer_id = (int)$_POST['customer_id'];
            $current_status = (int)$_POST['current_status'];
            $new_status = $current_status == 1 ? 0 : 1;
            $query = "UPDATE Users SET IsActive=$new_status WHERE UserID=$customer_id AND Role='customer'";
            if (mysqli_query($conn, $query)) {
                $success_msg = "Customer block status updated.";
            } else {
                $error_msg = "Failed to update block status.";
            }
        } elseif ($action === 'delete_customer') {
            $customer_id = (int)$_POST['customer_id'];
            $query = "UPDATE Users SET markasdeleted=1 WHERE UserID=$customer_id AND Role='customer'";
            if (mysqli_query($conn, $query)) {
                $success_msg = "Customer moved to archives.";
            } else {
                $error_msg = "Failed to delete customer.";
            }
        } elseif ($action === 'restore_customer') {
            $customer_id = (int)$_POST['customer_id'];
            $query = "UPDATE Users SET markasdeleted=0 WHERE UserID=$customer_id AND Role='customer'";
            if (mysqli_query($conn, $query)) {
                $success_msg = "Customer restored successfully.";
            } else {
                $error_msg = "Failed to restore customer.";
            }
        }
    }
}

// Fetch Logic
$view_archive = isset($_GET['view']) && $_GET['view'] === 'archive';
$is_deleted = $view_archive ? 1 : 0;

$search = isset($_GET['search']) ? mysqli_real_escape_string($conn, trim($_GET['search'])) : '';
$search_condition = "";
if ($search !== '') {
    $search_condition = " AND (UserName LIKE '%$search%' OR Email LIKE '%$search%' OR mobile_number LIKE '%$search%')";
}

$customers = [];
$fetch_query = "SELECT * FROM Users WHERE Role='customer' AND markasdeleted=$is_deleted $search_condition ORDER BY UserID DESC";
$result = mysqli_query($conn, $fetch_query);
if ($result) {
    while($row = mysqli_fetch_assoc($result)) {
        $customers[] = $row;
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo $view_archive ? 'Deleted Customers' : 'Manage Customers'; ?> - SupplyNet</title>
    <!-- Fonts -->
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    <!-- Bootstrap CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <!-- FontAwesome -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    
    <style>
        :root {
            --primary-color: #36b9cc;
            --secondary-color: #f8f9fc;
            --info-color: #36b9cc;
            --success-color: #1cc88a;
            --warning-color: #f6c23e;
            --danger-color: #e74a3b;
            --dark-color: #1f2937;
            --sidebar-width: 250px;
        }

        body { font-family: 'Inter', sans-serif; background-color: #f8f9fc; overflow-x: hidden; }
        
        /* Sidebar Styling */
        .sidebar { width: var(--sidebar-width); height: 100vh; position: fixed; background: linear-gradient(180deg, #2c9faf 10%, #158596 100%); color: #fff; z-index: 1000; box-shadow: 2px 0 10px rgba(0,0,0,0.1); }
        .sidebar .brand { padding: 1.5rem 1rem; font-size: 1.25rem; font-weight: 800; text-align: center; text-transform: uppercase; letter-spacing: 0.05rem; border-bottom: 1px solid rgba(255,255,255,0.1); display: block; color: #fff; text-decoration: none; }
        .nav-item { padding: 0 1rem; margin-bottom: 0.5rem; }
        .nav-link { color: rgba(255,255,255,0.8); padding: 1rem; border-radius: 0.5rem; font-weight: 600; transition: all 0.3s; }
        .nav-link:hover, .nav-link.active { color: #fff; background: rgba(255,255,255,0.15); transform: translateX(5px); text-decoration: none; }
        .nav-link i { margin-right: 0.75rem; width: 20px; text-align: center; }

        /* Main Content wrapper */
        .main-wrapper { margin-left: var(--sidebar-width); flex-grow: 1; min-height: 100vh; display: flex; flex-direction: column; }
        
        /* Top Navigation */
        .topbar { background: #fff; height: 70px; box-shadow: 0 0.15rem 1.75rem 0 rgba(58, 59, 69, 0.15); display: flex; align-items: center; justify-content: space-between; padding: 0 2rem; }
        .topbar-user { font-weight: 600; color: #5a5c69; display: flex; align-items: center; gap: 0.5rem; text-decoration: none; }
        .topbar-user img { width: 35px; height: 35px; border-radius: 50%; border: 2px solid var(--primary-color); }

        .dashboard-content { padding: 1.5rem 2rem; }
        .card-custom { border: none; border-radius: 0.75rem; box-shadow: 0 0.15rem 1.75rem 0 rgba(58, 59, 69, 0.1); background: #fff; }
        
        .table-responsive { overflow-x: auto; }
        
        .badge-status { font-weight: 600; padding: 0.4em 0.8em; font-size: 0.75em; border-radius: 0.25rem; }

        /* Actions buttons */
        .btn-action { width: 32px; height: 32px; padding: 0; display: inline-flex; align-items: center; justify-content: center; border-radius: 0.25rem; transition: all 0.2s; margin-right: 0.25rem; background-color: #f8f9fc; }
        .btn-action:hover { transform: translateY(-2px); box-shadow: 0 4px 6px rgba(0,0,0,0.1); }
        .btn-outline-primary:hover { background-color: var(--primary-color); color: white; border-color: var(--primary-color); }
        .btn-outline-warning:hover { color: #fff; }
        
        .page-header { display: flex; justify-content: space-between; align-items: center; margin-bottom: 1.5rem; }
        .page-title { font-size: 1.5rem; font-weight: 700; color: var(--dark-color); margin: 0; }
        
        /* Table hover enhancements */
        .table-hover tbody tr { transition: background-color 0.2s; }
        .table-hover tbody tr:hover { background-color: #f1f5f9; }
        
        /* Search bar enhancements */
        .search-container { position: relative; max-width: 350px; width: 100%; }
        .search-container input { padding-left: 2.5rem; border-radius: 20px; transition: all 0.3s; background-color: #f8f9fc; border: 1px solid #e3e6f0; }
        .search-container input:focus { box-shadow: 0 0 0 0.15rem rgba(54, 185, 204, 0.25); border-color: var(--primary-color); background-color: #fff; }
        .search-container i.search-icon { position: absolute; left: 15px; top: 50%; transform: translateY(-50%); color: #a1a5b7; z-index: 10; pointer-events: none; }
        .search-container .clear-btn { position: absolute; right: 12px; top: 50%; transform: translateY(-50%); z-index: 10; color: #a1a5b7; cursor: pointer; text-decoration: none; padding: 2px; }
        .search-container .clear-btn:hover { color: var(--danger-color); }
    
        @media (max-width: 768px) {
            .sidebar { transform: translateX(-100%); transition: transform 0.3s ease-in-out; }
            .sidebar.show-sidebar { transform: translateX(0); }
            .main-wrapper { margin-left: 0; width: 100%; }
            .sidebar-overlay { display: none; position: fixed; top: 0; left: 0; right: 0; bottom: 0; background: rgba(0,0,0,0.5); z-index: 999; cursor: pointer; }
            .sidebar-overlay.show { display: block; }
            .topbar { padding: 0 1rem; }
            .dashboard-content { padding: 1rem; }
            
            /* Table Responsive Stack */
            .table-responsive-stack thead { display: none; }
            .table-responsive-stack tr { display: block; border: 1px solid #e3e6f0; margin-bottom: 1rem; border-radius: 0.75rem; background: #fff; box-shadow: 0 0.125rem 0.25rem rgba(0,0,0,0.075); overflow: hidden; }
            .table-responsive-stack td { display: flex; justify-content: space-between; align-items: center; text-align: right; padding: 0.75rem 1rem; border-bottom: 1px solid #f8f9fc; }
            .table-responsive-stack td:last-child { border-bottom: none; }
            .table-responsive-stack td::before { content: attr(data-label); font-weight: 700; color: #858796; text-transform: uppercase; font-size: 0.7rem; text-align: left; }
            .table-responsive-stack .btn-action { width: auto; height: auto; padding: 0.4rem 0.8rem; font-size: 0.8rem; }
            .table-responsive-stack td:last-child { justify-content: center; background: #f8f9fc; }
        }
</style>
</head>
<body>

<!-- Sidebar -->
<div class="sidebar">
    <a href="index.php" class="brand"><i class="fas fa-cubes me-2"></i>SupplyNet<br><small class="text-white-50" style="font-size: 0.7rem;">Sales Panel</small></a>
    <div class="mt-4">
        <div class="nav-item"><a href="index.php" class="nav-link"><i class="fas fa-home"></i> Sales Dashboard</a></div>
        <div class="nav-item"><a href="manageCustomer.php" class="nav-link active"><i class="fas fa-address-book"></i> Customers List</a></div>
        <div class="nav-item"><a href="placeOrder.php" class="nav-link"><i class="fas fa-cart-plus"></i> Place Order</a></div>
        <div class="nav-item"><a href="viewOrderHistory.php" class="nav-link"><i class="fas fa-history"></i> Order History</a></div>
    </div>
</div>

<div class="sidebar-overlay" id="sidebarOverlay"></div>

<div class="main-wrapper">
    <!-- Topbar -->
    <div class="topbar">
        <div class="d-flex align-items-center">
            <button class="btn btn-link d-md-none text-dark me-2 px-0" id="sidebarToggle"><i class="fas fa-bars fs-5"></i></button>
            <div class="ms-2 ms-md-0">
                <h4 class="m-0 fw-bold text-dark fs-5 fs-md-4">Customer Directory</h4>
                <p class="text-muted small mb-0 d-none d-md-block">Manage your client relationships and logistics.</p>
            </div>
        </div>
        <div class="d-flex align-items-center gap-3 gap-md-4">
            <a href="#" class="text-secondary position-relative d-none d-sm-block"><i class="fas fa-envelope fs-5"></i></a>
            
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
                    
                    <span class="d-none d-md-inline me-2"><?php echo htmlspecialchars($salesman_name); ?></span>
                    <img src="https://ui-avatars.com/api/?name=<?php echo urlencode($salesman_name); ?>&background=36b9cc&color=fff" alt="Profile">
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
        
        <?php if($success_msg): ?>
            <div class="alert alert-success alert-dismissible fade show" role="alert">
                <i class="fas fa-check-circle me-2"></i><?php echo $success_msg; ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
            </div>
        <?php endif; ?>
        <?php if($error_msg): ?>
            <div class="alert alert-danger alert-dismissible fade show" role="alert">
                <i class="fas fa-exclamation-triangle me-2"></i><?php echo $error_msg; ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
            </div>
        <?php endif; ?>

        <div class="card card-custom">
            <div class="card-header bg-white border-bottom py-3 d-flex flex-column flex-md-row align-items-center justify-content-between gap-3">
                <h6 class="m-0 font-weight-bold text-dark" style="color: var(--primary-color) !important; min-width: max-content;">
                    <i class="fas <?php echo $view_archive ? 'fa-trash-restore' : 'fa-users'; ?> me-2"></i>
                    <?php echo $view_archive ? 'Deleted Customers' : 'Active Customers'; ?>
                </h6>
                
                <!-- Search Form -->
                <form method="GET" class="search-container d-flex m-0">
                    <?php if($view_archive): ?>
                        <input type="hidden" name="view" value="archive">
                    <?php endif; ?>
                    <i class="fas fa-search search-icon"></i>
                    <input type="text" name="search" class="form-control form-control-sm" placeholder="Search name, email, or mobile..." value="<?php echo htmlspecialchars($search); ?>">
                    <?php if($search): ?>
                        <a href="manageCustomer.php<?php echo $view_archive ? '?view=archive' : ''; ?>" class="clear-btn" title="Clear Search"><i class="fas fa-times"></i></a>
                    <?php endif; ?>
                    <button type="submit" class="d-none">Search</button>
                </form>

                <div class="d-flex gap-2">
                    <?php if($view_archive): ?>
                        <a href="manageCustomer.php" class="btn btn-outline-primary shadow-sm px-3 py-2" title="Back to Active Customers"><i class="fas fa-arrow-left"></i></a>
                    <?php else: ?>
                        <button class="btn btn-primary shadow-sm px-3 py-2" data-bs-toggle="modal" data-bs-target="#addCustomerModal" title="Add New Customer"><i class="fas fa-plus"></i></button>
                        <a href="manageCustomer.php?view=archive" class="btn btn-outline-danger shadow-sm px-3 py-2" title="Deleted Archives"><i class="fas fa-trash-restore"></i></a>
                    <?php endif; ?>
                </div>
            </div>
            <div class="card-body p-0">
                <div class="table-responsive">
                    <table class="table table-hover align-middle mb-0 table-responsive-stack">
                        <thead class="table-light text-muted small">
                            <tr>
                                <th class="ps-4">ID</th>
                                <th>Name</th>
                                <th>Email</th>
                                <th>Mobile</th>
                                <th>Status</th>
                                <th class="text-end pe-4">Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if(empty($customers)): ?>
                                <tr>
                                    <td colspan="6" class="text-center py-5 text-muted">
                                        <i class="fas fa-folder-open fs-2 mb-3 text-light"></i><br>
                                        No customers found.
                                    </td>
                                </tr>
                            <?php else: ?>
                                <?php foreach($customers as $c): ?>
                                    <tr>
                                        <td class="ps-4 fw-bold text-dark" data-label="ID">#<?php echo str_pad($c['UserID'], 4, '0', STR_PAD_LEFT); ?></td>
                                        <td class="fw-semibold text-dark" data-label="Name">
                                            <div class="d-flex align-items-center">
                                                <div class="bg-light rounded-circle d-flex align-items-center justify-content-center me-2" style="width: 32px; height: 32px;">
                                                    <i class="fas fa-user text-secondary small"></i>
                                                </div>
                                                <?php echo htmlspecialchars($c['UserName']); ?>
                                            </div>
                                        </td>
                                        <td data-label="Email"><?php echo htmlspecialchars($c['Email']); ?></td>
                                        <td data-label="Mobile"><?php echo htmlspecialchars($c['mobile_number'] ?? 'N/A'); ?></td>
                                        <td data-label="Status">
                                            <?php if($view_archive): ?>
                                                <span class="badge badge-status bg-danger">Deleted</span>
                                            <?php else: ?>
                                                <?php if($c['IsActive'] == 1): ?>
                                                    <span class="badge badge-status bg-success">Active</span>
                                                <?php else: ?>
                                                    <span class="badge badge-status bg-warning text-dark">Blocked</span>
                                                <?php endif; ?>
                                            <?php endif; ?>
                                        </td>
                                        <td class="text-end pe-4" data-label="Actions">
                                            <?php if($view_archive): ?>
                                                <!-- Restore Button -->
                                                <form method="POST" class="d-inline">
                                                    <input type="hidden" name="action" value="restore_customer">
                                                    <input type="hidden" name="customer_id" value="<?php echo $c['UserID']; ?>">
                                                    <button type="submit" class="btn btn-action btn-outline-success" title="Restore Customer" onclick="return confirm('Are you sure you want to restore this customer?');">
                                                        <i class="fas fa-trash-restore"></i>
                                                    </button>
                                                </form>
                                            <?php else: ?>
                                                <div class="d-flex justify-content-end gap-1">
                                                    <!-- Edit Button -->
                                                    <button class="btn btn-action btn-outline-primary" title="Edit Customer" data-bs-toggle="modal" data-bs-target="#editCustomerModal<?php echo $c['UserID']; ?>">
                                                        <i class="fas fa-edit"></i>
                                                    </button>
                                                    
                                                    <!-- Block/Unblock Button -->
                                                    <form method="POST" class="m-0">
                                                        <input type="hidden" name="action" value="toggle_block">
                                                        <input type="hidden" name="customer_id" value="<?php echo $c['UserID']; ?>">
                                                        <input type="hidden" name="current_status" value="<?php echo $c['IsActive']; ?>">
                                                        <?php if($c['IsActive'] == 1): ?>
                                                            <button type="submit" class="btn btn-action btn-outline-warning" title="Block Customer" onclick="return confirm('Block this customer?');">
                                                                <i class="fas fa-ban"></i>
                                                            </button>
                                                        <?php else: ?>
                                                            <button type="submit" class="btn btn-action btn-outline-success" title="Unblock Customer">
                                                                <i class="fas fa-check-circle"></i>
                                                            </button>
                                                        <?php endif; ?>
                                                    </form>
                                                    
                                                    <!-- Delete Button -->
                                                    <form method="POST" class="m-0">
                                                        <input type="hidden" name="action" value="delete_customer">
                                                        <input type="hidden" name="customer_id" value="<?php echo $c['UserID']; ?>">
                                                        <button type="submit" class="btn btn-action btn-outline-danger" title="Delete Customer" onclick="return confirm('Move this customer to the archive?');">
                                                            <i class="fas fa-trash"></i>
                                                        </button>
                                                    </form>
                                                </div>
                                            <?php endif; ?>
                                        </td>
                                    </tr>

                                    <!-- Edit Modal for this customer -->
                                    <div class="modal fade" id="editCustomerModal<?php echo $c['UserID']; ?>" tabindex="-1" aria-labelledby="editLabel<?php echo $c['UserID']; ?>" aria-hidden="true">
                                      <div class="modal-dialog">
                                        <div class="modal-content">
                                            <form method="POST">
                                                <div class="modal-header">
                                                    <h5 class="modal-title" id="editLabel<?php echo $c['UserID']; ?>">Edit Customer Details</h5>
                                                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                                                </div>
                                                <div class="modal-body">
                                                    <input type="hidden" name="action" value="edit_customer">
                                                    <input type="hidden" name="customer_id" value="<?php echo $c['UserID']; ?>">
                                                    
                                                    <div class="mb-3">
                                                        <label class="form-label">Full Name</label>
                                                        <input type="text" name="name" class="form-control" value="<?php echo htmlspecialchars($c['UserName']); ?>" required>
                                                    </div>
                                                    <div class="mb-3">
                                                        <label class="form-label">Email Address</label>
                                                        <input type="email" name="email" class="form-control" value="<?php echo htmlspecialchars($c['Email']); ?>" required>
                                                    </div>
                                                    <div class="mb-3">
                                                        <label class="form-label">Mobile Number</label>
                                                        <input type="text" name="mobile" class="form-control" value="<?php echo htmlspecialchars($c['mobile_number']); ?>">
                                                    </div>
                                                    <div class="mb-3">
                                                        <label class="form-label">Address</label>
                                                        <textarea name="address" class="form-control" rows="3"><?php echo htmlspecialchars($c['address']); ?></textarea>
                                                    </div>
                                                </div>
                                                <div class="modal-footer">
                                                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                                                    <button type="submit" class="btn btn-primary">Save Changes</button>
                                                </div>
                                            </form>
                                        </div>
                                      </div>
                                    </div>

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

<!-- Add Customer Modal -->
<div class="modal fade" id="addCustomerModal" tabindex="-1" aria-labelledby="addCustomerLabel" aria-hidden="true">
  <div class="modal-dialog">
    <div class="modal-content">
        <form method="POST">
            <div class="modal-header">
                <h5 class="modal-title" id="addCustomerLabel">Register New Customer</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <input type="hidden" name="action" value="add_customer">
                
                <div class="mb-3">
                    <label class="form-label">Full Name <span class="text-danger">*</span></label>
                    <input type="text" name="name" class="form-control" required placeholder="John Doe">
                </div>
                <div class="mb-3">
                    <label class="form-label">Email Address <span class="text-danger">*</span></label>
                    <input type="email" name="email" class="form-control" required placeholder="john@example.com">
                </div>
                <div class="mb-3">
                    <label class="form-label">Mobile Number</label>
                    <input type="text" name="mobile" class="form-control" placeholder="+1234567890">
                </div>
                <div class="mb-3">
                    <label class="form-label">Full Address</label>
                    <textarea name="address" class="form-control" rows="3" placeholder="123 Main St, City, Country"></textarea>
                </div>
                <div class="alert alert-info small py-2 mb-0">
                    <i class="fas fa-info-circle me-1"></i> A default password <strong>customer123</strong> will be assigned. The customer can change it after logging in.
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                <button type="submit" class="btn btn-primary"><i class="fas fa-user-plus me-1"></i> Register Customer</button>
            </div>
        </form>
    </div>
  </div>
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
