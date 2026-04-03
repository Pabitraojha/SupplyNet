<?php
session_start();
require_once '../config/db.php';

if (!isset($_SESSION['UserID']) || strtolower($_SESSION['Role']) !== 'admin') {
    header("Location: ../login.php");
    exit();
}

$admin_name = $_SESSION['UserName'] ?? 'Administrator';

// Handling Actions
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $action = $_POST['action'] ?? '';
    $target_user_id = intval($_POST['user_id'] ?? 0);
    
    if ($target_user_id > 0) {
        if ($action === 'approve') {
            $stmt = $conn->prepare("UPDATE Users SET approval_status='approved' WHERE UserID=?");
            $stmt->bind_param("i", $target_user_id);
            $stmt->execute();
        } elseif ($action === 'reject') {
            $stmt = $conn->prepare("UPDATE Users SET approval_status='rejected' WHERE UserID=?");
            $stmt->bind_param("i", $target_user_id);
            $stmt->execute();
        } elseif ($action === 'block') {
            $stmt = $conn->prepare("UPDATE Users SET IsActive=0 WHERE UserID=?");
            $stmt->bind_param("i", $target_user_id);
            $stmt->execute();
        } elseif ($action === 'unblock') {
            $stmt = $conn->prepare("UPDATE Users SET IsActive=1 WHERE UserID=?");
            $stmt->bind_param("i", $target_user_id);
            $stmt->execute();
        } elseif ($action === 'delete') {
            $stmt = $conn->prepare("UPDATE Users SET markasdeleted=1 WHERE UserID=?");
            $stmt->bind_param("i", $target_user_id);
            $stmt->execute();
        }
        
        // Redirect to avoid form resubmission
        header("Location: user_management.php");
        exit();
    }
}

// Fetch metrics
function getMetric($conn, $condition) {
    // Only count users who are NOT marked as deleted and not admin
    $query = "SELECT COUNT(*) as cnt FROM Users WHERE Role != 'admin' AND markasdeleted=0 AND " . $condition;
    $res = $conn->query($query);
    return $res->fetch_assoc()['cnt'] ?? 0;
}

$total_approved = getMetric($conn, "approval_status='approved'");
$total_pending = getMetric($conn, "approval_status='pending'");
$total_rejected = getMetric($conn, "approval_status='rejected'");
$total_blocked = getMetric($conn, "IsActive=0 AND approval_status='approved'");
$total_active = getMetric($conn, "IsActive=1 AND approval_status='approved'"); 

// Check and Alter DB for new columns safely
$check_col = $conn->query("SHOW COLUMNS FROM Users LIKE 'created_by'");
if ($check_col && $check_col->num_rows == 0) {
    $conn->query("ALTER TABLE Users ADD COLUMN created_by INT DEFAULT NULL");
}

// Fetch all non-admin, non-deleted users
$users = [];
$search_query = "";
if (isset($_GET['search']) && !empty(trim($_GET['search']))) {
    $search_term = $conn->real_escape_string(trim($_GET['search']));
    $search_query = " AND (u.UserName LIKE '%$search_term%' OR u.Email LIKE '%$search_term%') ";
}

$res = $conn->query("
    SELECT u.*, creator.UserName as CreatorName, creator.Role as CreatorRole 
    FROM Users u
    LEFT JOIN Users creator ON u.created_by = creator.UserID
    WHERE u.Role != 'admin' AND u.markasdeleted=0 $search_query 
    ORDER BY u.CreatedAt DESC
");
if ($res) {
    while ($row = $res->fetch_assoc()) {
        $users[] = $row;
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>User Management - SupplyNet Admin</title>
    <!-- Fonts -->
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    <!-- Bootstrap CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">

    <style>
        :root {
            --primary-color: #4e73df;
            --info-color: #36b9cc;
            --success-color: #1cc88a;
            --warning-color: #f6c23e;
            --danger-color: #e74a3b;
            --dark-color: #1f2937;
            --sidebar-width: 250px;
        }

        body { font-family: 'Inter', sans-serif; background-color: #f8f9fc; overflow-x: hidden; }
        
        /* Sidebar Styling */
        .sidebar { width: var(--sidebar-width); height: 100vh; position: fixed; background: var(--primary-color); background: linear-gradient(180deg, #4e73df 10%, #224abe 100%); color: #fff; z-index: 1000; box-shadow: 2px 0 10px rgba(0,0,0,0.1); }
        .sidebar .brand { padding: 1.5rem 1rem; font-size: 1.25rem; font-weight: 800; text-align: center; text-transform: uppercase; letter-spacing: 0.05rem; border-bottom: 1px solid rgba(255,255,255,0.1); display: block; color: #fff; text-decoration: none; }
        .nav-item { padding: 0 1rem; margin-bottom: 0.5rem; }
        .nav-link { color: rgba(255,255,255,0.8); padding: 1rem; border-radius: 0.5rem; font-weight: 600; transition: all 0.3s; }
        .nav-link:hover, .nav-link.active { color: #fff; background: rgba(255,255,255,0.15); transform: translateX(5px); }
        .nav-link i { margin-right: 0.75rem; width: 20px; text-align: center; }

        /* Main Content */
        .main-wrapper { margin-left: var(--sidebar-width); flex-grow: 1; min-height: 100vh; display: flex; flex-direction: column; }
        
        /* Top Navigation */
        .topbar { background: #fff; height: 70px; box-shadow: 0 0.15rem 1.75rem 0 rgba(58, 59, 69, 0.15); display: flex; align-items: center; justify-content: space-between; padding: 0 2rem; }
        .topbar-user { font-weight: 600; color: #5a5c69; display: flex; align-items: center; gap: 0.5rem; }
        .topbar-user img { width: 35px; height: 35px; border-radius: 50%; border: 2px solid var(--primary-color); }

        /* Dashboard Content */
        .dashboard-content { padding: 1.5rem 2rem; }
        .card-custom { border: none; border-radius: 0.75rem; box-shadow: 0 0.15rem 1.75rem 0 rgba(58, 59, 69, 0.1); margin-bottom: 1.5rem; }
        
        /* Metric cards */
        .metric-card { border-left: 0.25rem solid; padding: 1rem; border-radius: 0.5rem; background: #fff; display: flex; align-items: center; justify-content: space-between; box-shadow: 0 0.15rem 1.75rem 0 rgba(58, 59, 69, 0.1); }
        .metric-title { font-size: 0.75rem; font-weight: 700; text-transform: uppercase; margin-bottom: 0.25rem; }
        .metric-value { font-size: 1.5rem; font-weight: 800; color: #5a5c69; margin: 0; }
        .metric-icon { font-size: 2rem; color: #dddfeb; }
        
        .metric-approved { border-left-color: var(--success-color); }
        .metric-approved .metric-title { color: var(--success-color); }
        
        .metric-pending { border-left-color: var(--warning-color); }
        .metric-pending .metric-title { color: var(--warning-color); }
        
        .metric-rejected { border-left-color: var(--danger-color); }
        .metric-rejected .metric-title { color: var(--danger-color); }
        
        .metric-blocked { border-left-color: var(--dark-color); }
        .metric-blocked .metric-title { color: var(--dark-color); }
        
        .metric-active { border-left-color: var(--info-color); }
        .metric-active .metric-title { color: var(--info-color); }

        .btn-action { margin-right: 0.25rem; padding: 0.25rem 0.6rem; font-size: 0.85rem; border-radius: 0.4rem; font-weight: 600; }
    
        @media (max-width: 768px) {
            .sidebar { transform: translateX(-100%); transition: transform 0.3s ease-in-out; }
            .sidebar.show-sidebar { transform: translateX(0); }
            .main-wrapper { margin-left: 0; width: 100%; }
            .sidebar-overlay { display: none; position: fixed; top: 0; left: 0; right: 0; bottom: 0; background: rgba(0,0,0,0.5); z-index: 999; cursor: pointer; }
            .sidebar-overlay.show { display: block; }
        }
</style>
</head>
<body>

<!-- Sidebar -->
<div class="sidebar">
    <a href="index.php" class="brand"><i class="fas fa-cubes me-2"></i>SupplyNet<br><small class="text-white-50" style="font-size: 0.7rem;">Admin Panel</small></a>
    <div class="mt-4">
        <div class="nav-item"><a href="index.php" class="nav-link"><i class="fas fa-tachometer-alt"></i> Dashboard</a></div>
        <div class="nav-item"><a href="user_management.php" class="nav-link active"><i class="fas fa-users"></i> User Management</a></div>
        <div class="nav-item"><a href="products_directory.php" class="nav-link"><i class="fas fa-box-open"></i> Products Directory</a></div>
        <div class="nav-item"><a href="orders_hub.php" class="nav-link"><i class="fas fa-truck-loading"></i> Orders Hub</a></div>
        <div class="nav-item"><a href="delivery_route.php" class="nav-link"><i class="fas fa-route"></i> Deliveries & Routes</a></div>
        <div class="nav-item"><a href="customer_feedback.php" class="nav-link"><i class="fas fa-comments"></i> Customer Feedback</a></div>
        <div class="nav-item"><a href="system_settings.php" class="nav-link"><i class="fas fa-cogs"></i> System Settings</a></div>
    </div>
</div>


<div class="sidebar-overlay" id="sidebarOverlay"></div>
<div class="main-wrapper">
    <!-- Topbar -->
    <div class="topbar">
        <div class="d-flex align-items-center">
            <button class="btn btn-link d-md-none text-dark me-3" id="sidebarToggle"><i class="fas fa-bars"></i></button>
            <h4 class="m-0 fw-bold text-dark">User Management</h4>
        </div>
        <div class="d-flex align-items-center gap-4">
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
                    <span class="d-none d-lg-inline"><?php echo htmlspecialchars($admin_name); ?></span>
                    <img src="https://ui-avatars.com/api/?name=<?php echo urlencode($admin_name); ?>&background=4e73df&color=fff" alt="Admin Profile">
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
        
        <!-- Metrics Row -->
        <div class="row g-4 mb-4">
            <div class="col-xl col-md-4 col-sm-6">
                <div class="metric-card metric-pending">
                    <div>
                        <div class="metric-title">Pending Users</div>
                        <div class="metric-value"><?php echo $total_pending; ?></div>
                    </div>
                    <i class="fas fa-user-clock metric-icon"></i>
                </div>
            </div>
            <div class="col-xl col-md-4 col-sm-6">
                <div class="metric-card metric-approved">
                    <div>
                        <div class="metric-title">Approved Users</div>
                        <div class="metric-value"><?php echo $total_approved; ?></div>
                    </div>
                    <i class="fas fa-user-check metric-icon"></i>
                </div>
            </div>
            <div class="col-xl col-md-4 col-sm-6">
                <div class="metric-card metric-rejected">
                    <div>
                        <div class="metric-title">Rejected Users</div>
                        <div class="metric-value"><?php echo $total_rejected; ?></div>
                    </div>
                    <i class="fas fa-user-times metric-icon"></i>
                </div>
            </div>
            <div class="col-xl col-md-6 col-sm-6">
                <div class="metric-card metric-active">
                    <div>
                        <div class="metric-title">Total Active (Unblocked)</div>
                        <div class="metric-value"><?php echo $total_active; ?></div>
                    </div>
                    <i class="fas fa-users metric-icon"></i>
                </div>
            </div>
            <div class="col-xl col-md-6 col-sm-6">
                <div class="metric-card metric-blocked">
                    <div>
                        <div class="metric-title">Total Blocked</div>
                        <div class="metric-value"><?php echo $total_blocked; ?></div>
                    </div>
                    <i class="fas fa-user-slash metric-icon"></i>
                </div>
            </div>
        </div>

        <!-- Users Table Card -->
        <div class="card card-custom">
            <div class="card-header bg-white border-bottom py-3 d-flex flex-wrap justify-content-between align-items-center gap-3">
                <h6 class="m-0 font-weight-bold text-primary" style="font-weight: 700;"><i class="fas fa-list me-2"></i>All Users List</h6>
                
                <form action="user_management.php" method="GET" class="d-flex m-0" style="flex: 1; min-width: 300px; max-width: 450px;" onsubmit="return false;">
                    <div class="input-group shadow-sm" style="border-radius: 50rem; overflow: hidden; background: #fcfcfc; border: 1px solid #e3e6f0;">
                        <span class="input-group-text bg-transparent border-0 text-primary ps-3 pe-2">
                            <i class="fas fa-search"></i>
                        </span>
                        <input type="text" id="searchInput" name="search" class="form-control bg-transparent border-0 shadow-none ps-1" placeholder="Search users by name or email..." value="<?php echo htmlspecialchars($_GET['search'] ?? ''); ?>" style="font-size: 0.9rem;">
                        <?php if(!empty($_GET['search'])): ?>
                            <a href="user_management.php" class="input-group-text bg-transparent border-0 text-secondary text-decoration-none pe-3" title="Clear Search">
                                <i class="fas fa-times-circle"></i>
                            </a>
                        <?php endif; ?>
                        <button class="btn btn-primary px-4" type="submit" style="font-weight: 600; letter-spacing: 0.03rem;">Search</button>
                    </div>
                </form>

                <a title="Deleted Archive" href="deleted_users.php" class="btn btn-sm btn-outline-danger"><i class="fas fa-trash-restore"></i></a>
            </div>
            <div class="card-body">
                <div class="table-responsive">
                    <table class="table table-hover align-middle border">
                        <thead class="table-light">
                            <tr>
                                <th>Name</th>
                                <th>Email</th>
                                <th>Role</th>
                                <th>Created By</th>
                                <th>Status</th>
                                <th class="text-end">Actions</th>
                            </tr>
                        </thead>
                        <tbody id="dataTableBody">
                            <?php if(empty($users)): ?>
                                <tr><td colspan="6" class="text-center py-4 text-muted">No users found.</td></tr>
                            <?php else: ?>
                                <?php foreach($users as $u): ?>
                                <tr>
                                    <td class="fw-semibold text-dark">
                                        <div class="d-flex align-items-center">
                                            <div class="rounded-circle bg-primary bg-opacity-10 text-primary d-flex align-items-center justify-content-center me-2" style="width: 35px; height: 35px; font-weight:bold;">
                                                <?php echo strtoupper(substr($u['UserName'], 0, 1)); ?>
                                            </div>
                                            <?php echo htmlspecialchars($u['UserName']); ?>
                                        </div>
                                    </td>
                                    <td><?php echo htmlspecialchars($u['Email']); ?></td>
                                    <td>
                                        <?php 
                                        $roleColors = [
                                            'employee' => 'primary',
                                            'customer' => 'success',
                                            'salesman' => 'info',
                                            'delivery_person' => 'warning'
                                        ];
                                        $color = $roleColors[strtolower($u['Role'])] ?? 'secondary';
                                        ?>
                                        <span class="badge bg-<?php echo $color; ?> bg-opacity-10 text-<?php echo $color; ?> border border-<?php echo $color; ?>-subtle px-2 py-1">
                                            <?php echo ucwords(str_replace('_', ' ', $u['Role'])); ?>
                                        </span>
                                    </td>
                                    <td>
                                        <?php if ($u['CreatorName']): ?>
                                            <span class="badge bg-light text-dark border" title="Created by <?php echo ucwords(str_replace('_', ' ', $u['CreatorRole'])); ?>">
                                                <i class="fas fa-user-edit me-1 text-muted"></i>
                                                <?php echo htmlspecialchars($u['CreatorName']); ?> 
                                                <small class="text-muted fw-normal ms-1">(<?php echo ucwords(str_replace('_', ' ', $u['CreatorRole'])); ?>)</small>
                                            </span>
                                        <?php else: ?>
                                            <span class="badge bg-light text-dark border"><i class="fas fa-globe me-1 text-muted"></i>Self-Registered</span>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <?php if($u['approval_status'] == 'pending'): ?>
                                            <span class="badge bg-warning text-dark"><i class="fas fa-clock me-1"></i>Pending</span>
                                        <?php elseif($u['approval_status'] == 'rejected'): ?>
                                            <span class="badge bg-danger"><i class="fas fa-times me-1"></i>Rejected</span>
                                        <?php else: // approved
                                            if($u['IsActive'] == 0): ?>
                                                <span class="badge bg-dark"><i class="fas fa-ban me-1"></i>Blocked</span>
                                            <?php else: ?>
                                                <span class="badge bg-success"><i class="fas fa-check me-1"></i>Active</span>
                                            <?php endif;
                                        endif; ?>
                                    </td>
                                    <td class="text-end">
                                        <form action="user_management.php" method="POST" class="d-inline mb-0">
                                            <input type="hidden" name="user_id" value="<?php echo $u['UserID']; ?>">
                                            
                                            <?php if($u['approval_status'] == 'pending'): ?>
                                                <button title="Approve" type="submit" name="action" value="approve" class="btn btn-action btn-success"><i class="fas fa-check"></i></button>
                                                <button title="Reject" type="submit" name="action" value="reject" class="btn btn-action btn-outline-danger"><i class="fas fa-times"></i></button>
                                            
                                            <?php elseif($u['approval_status'] == 'approved'): ?>
                                                
                                                <?php if($u['IsActive'] == 1): ?>
                                                    <button title="Block" type="submit" name="action" value="block" class="btn btn-action btn-warning text-dark"><i class="fas fa-ban"></i></button>
                                                <?php else: ?>
                                                    <button title="Unblock" type="submit" name="action" value="unblock" class="btn btn-action btn-success"><i class="fas fa-unlock"></i></button>
                                                <?php endif; ?>
                                                
                                                <a title="Edit User" href="edit_user.php?id=<?php echo $u['UserID']; ?>" class="btn btn-action btn-info text-white"><i class="fas fa-edit"></i></a>
                                                <button title="Delete" type="submit" name="action" value="delete" class="btn btn-action btn-danger" onclick="return confirm('Are you sure you want to delete this user?');"><i class="fas fa-trash-alt"></i></button>
                                            
                                            <?php elseif($u['approval_status'] == 'rejected'): ?>
                                                <button title="Approve Now" type="submit" name="action" value="approve" class="btn btn-action btn-success"><i class="fas fa-check"></i></button>
                                            <?php endif; ?>
                                            
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

<!-- Bootstrap JS -->
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>

<!-- Dynamic Search Script -->
<script>
document.addEventListener('DOMContentLoaded', function() {
    const searchInput = document.getElementById('searchInput');
    const tableBody = document.getElementById('dataTableBody');
    
    if (searchInput && tableBody) {
        searchInput.addEventListener('input', function() {
            const searchTerm = this.value.toLowerCase().trim();
            const rows = tableBody.getElementsByTagName('tr');
            
            for (let i = 0; i < rows.length; i++) {
                // Skip the "No users found" row if it exists
                if (rows[i].cells.length === 1) continue;
                
                // Get Name, Email, and Creator cells (index 0, 1, and 3)
                const nameText = rows[i].cells[0].textContent.toLowerCase();
                const emailText = rows[i].cells[1].textContent.toLowerCase();
                const creatorText = rows[i].cells[3].textContent.toLowerCase();
                
                if (nameText.includes(searchTerm) || emailText.includes(searchTerm) || creatorText.includes(searchTerm)) {
                    rows[i].style.display = '';
                } else {
                    rows[i].style.display = 'none';
                }
            }
        });
        
        // Trigger once on load in case there's an existing search value
        if (searchInput.value) {
            searchInput.dispatchEvent(new Event('input'));
        }
    }
});
</script>


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
