<?php
session_start();
require_once '../config/db.php';

// Auth Protection
if (!isset($_SESSION['UserID']) || strtolower($_SESSION['Role']) !== 'customer') {
    header("Location: ../login.php");
    exit();
}

$user_id = $_SESSION['UserID'];
$success_msg = '';
$error_msg = '';

// Handle Profile Update
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'update_profile') {
    $name = trim($_POST['UserName']);
    $email = trim($_POST['Email']);
    $mobile = trim($_POST['mobile_number']);
    $address = trim($_POST['address']);
    
    if (empty($name) || empty($email)) {
        $error_msg = "Name and Email are required fields.";
    } else {
        $stmt_check = $conn->prepare("SELECT UserID FROM Users WHERE Email = ? AND UserID != ? AND markasdeleted=0");
        $stmt_check->bind_param("si", $email, $user_id);
        $stmt_check->execute();
        $res_check = $stmt_check->get_result();
        
        if ($res_check->num_rows > 0) {
            $error_msg = "This email is already registered with another account.";
        } else {
            $stmt = $conn->prepare("UPDATE Users SET UserName=?, Email=?, mobile_number=?, address=? WHERE UserID=?");
            $stmt->bind_param("ssssi", $name, $email, $mobile, $address, $user_id);
            if ($stmt->execute()) {
                $success_msg = "Profile successfully updated.";
                $_SESSION['UserName'] = $name;
                $_SESSION['Email'] = $email;
            } else {
                $error_msg = "Failed to update profile. Please try again.";
            }
        }
    }
}

// Handle Password Update
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'update_password') {
    $current_password = $_POST['current_password'];
    $new_password = $_POST['new_password'];
    $confirm_password = $_POST['confirm_password'];
    
    if (empty($current_password) || empty($new_password) || empty($confirm_password)) {
        $error_msg = "All password fields are required.";
    } elseif ($new_password !== $confirm_password) {
        $error_msg = "New password and confirmation do not match.";
    } elseif (strlen($new_password) < 6) {
        $error_msg = "New password must be at least 6 characters long.";
    } else {
        $stmt = $conn->prepare("SELECT Password FROM Users WHERE UserID = ?");
        $stmt->bind_param("i", $user_id);
        $stmt->execute();
        $user = $stmt->get_result()->fetch_assoc();
        
        if (password_verify($current_password, $user['Password'])) {
            $hashed_password = password_hash($new_password, PASSWORD_DEFAULT);
            $update_stmt = $conn->prepare("UPDATE Users SET Password=? WHERE UserID=?");
            $update_stmt->bind_param("si", $hashed_password, $user_id);
            if ($update_stmt->execute()) {
                $success_msg = "Password successfully updated. Please use your new password next time.";
            } else {
                $error_msg = "Failed to update password.";
            }
        } else {
            $error_msg = "Incorrect current password.";
        }
    }
}

// Fetch Current User Data
$stmt = $conn->prepare("SELECT UserName, Email, mobile_number, address, Role, CreatedAt FROM Users WHERE UserID = ?");
$stmt->bind_param("i", $user_id);
$stmt->execute();
$current_user = $stmt->get_result()->fetch_assoc();
$display_name = $current_user['UserName'];
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Customer Profile - SupplyNet</title>
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
        .card-custom { border: none; border-radius: 0.75rem; box-shadow: 0 0.15rem 1.75rem 0 rgba(58, 59, 69, 0.1); background: #fff; overflow: hidden; }
        
        /* Interactive Elements */
        .form-control { border-radius: 0.5rem; border: 1px solid #d1d3e2; padding: 0.75rem; transition: border-color 0.2s, box-shadow 0.2s; }
        .form-control:focus { border-color: var(--primary-color); box-shadow: 0 0 0 0.25rem rgba(253, 126, 20, 0.25); }
        .form-label { font-weight: 600; color: #fd7e14; font-size: 0.85rem; text-transform: uppercase; letter-spacing: 0.05rem; }
        
        /* Profile Image Badge */
        .profile-img-lg { width: 120px; height: 120px; border-radius: 50%; border: 4px solid #fff; box-shadow: 0 0.25rem 1rem rgba(0,0,0,0.15); }
        .profile-header-bg { background: linear-gradient(135deg, var(--primary-color) 0%, #e86000 100%); height: 120px; border-radius: 0.75rem 0.75rem 0 0; }
        .profile-details-wrapper { margin-top: -60px; text-align: center; padding-bottom: 1.5rem; border-bottom: 2px solid #f8f9fc; }
        
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
    <a href="index.php" class="brand"><i class="fas fa-cubes me-2"></i>SupplyNet<br><small class="text-white-50" style="font-size: 0.7rem;">Customer Portal</small></a>
    <div class="mt-4">
        <div class="nav-item"><a href="index.php" class="nav-link"><i class="fas fa-home"></i> Dashboard</a></div>
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
                <h4 class="m-0 fw-bold text-dark fs-5 fs-md-4">Customer Profile</h4>
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
                    <li><a class="dropdown-item active" href="profile.php"><i class="fas fa-user fa-sm fa-fw me-2 text-gray-400"></i> Profile</a></li>
                    <li><hr class="dropdown-divider"></li>
                    <li><a class="dropdown-item text-danger" href="../logout.php"><i class="fas fa-sign-out-alt fa-sm fa-fw me-2"></i> Logout</a></li>
                </ul>
            </div>
        </div>
    </div>

    <div class="dashboard-content">
        <?php if($success_msg): ?>
            <div class="alert alert-success alert-dismissible fade show shadow-sm" role="alert">
                <i class="fas fa-check-circle me-2"></i><?php echo $success_msg; ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
            </div>
        <?php endif; ?>
        <?php if($error_msg): ?>
            <div class="alert alert-danger alert-dismissible fade show shadow-sm" role="alert">
                <i class="fas fa-exclamation-triangle me-2"></i><?php echo $error_msg; ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
            </div>
        <?php endif; ?>

        <div class="row g-4 d-flex align-items-stretch">
            <div class="col-xl-4 col-lg-5">
                <div class="card card-custom h-100">
                    <div class="profile-header-bg"></div>
                    <div class="profile-details-wrapper position-relative px-4">
                        <img src="https://ui-avatars.com/api/?name=<?php echo urlencode($current_user['UserName']); ?>&background=fd7e14&color=fff&size=200" alt="Profile Image" class="profile-img-lg mb-3">
                        <h4 class="fw-bold text-dark m-0"><?php echo htmlspecialchars($current_user['UserName']); ?></h4>
                        <div class="text-muted small mt-1">
                            <i class="fas fa-envelope me-1"></i><?php echo htmlspecialchars($current_user['Email']); ?>
                        </div>
                        <div class="mt-3">
                            <span class="badge bg-primary bg-opacity-10 text-primary border border-primary-subtle px-3 py-2 fs-6 rounded-pill text-uppercase">
                                <i class="fas fa-star me-2"></i><?php echo htmlspecialchars($current_user['Role']); ?>
                            </span>
                        </div>
                        <div class="text-muted small mt-4 pt-3 border-top">
                            <i class="fas fa-calendar-alt me-1"></i> Account Created: <?php echo date('M d, Y', strtotime($current_user['CreatedAt'] ?? 'now')); ?>
                        </div>
                    </div>
                </div>
            </div>

            <div class="col-xl-8 col-lg-7">
                <div class="card card-custom h-100">
                    <div class="card-header bg-white border-bottom py-3">
                        <ul class="nav nav-pills card-header-pills" id="profileTabs" role="tablist">
                            <li class="nav-item" role="presentation">
                                <button class="nav-link active fw-bold text-dark" id="edit-profile-tab" data-bs-toggle="tab" data-bs-target="#edit-profile" type="button" role="tab" style="color: var(--primary-color) !important; background: transparent;"><i class="fas fa-user-edit me-2"></i>Edit Information</button>
                            </li>
                            <li class="nav-item" role="presentation">
                                <button class="nav-link fw-bold text-muted" id="change-security-tab" data-bs-toggle="tab" data-bs-target="#change-security" type="button" role="tab"><i class="fas fa-lock me-2"></i>Security Settings</button>
                            </li>
                        </ul>
                    </div>
                    <div class="card-body p-4">
                        <div class="tab-content" id="profileTabsContent">
                            <div class="tab-pane fade show active" id="edit-profile" role="tabpanel">
                                <form method="POST">
                                    <input type="hidden" name="action" value="update_profile">
                                    <div class="row">
                                        <div class="col-md-6 mb-4">
                                            <label class="form-label">Full Name <span class="text-danger">*</span></label>
                                            <div class="input-group">
                                                <span class="input-group-text bg-light"><i class="fas fa-user text-muted"></i></span>
                                                <input type="text" class="form-control" name="UserName" value="<?php echo htmlspecialchars($current_user['UserName']); ?>" required>
                                            </div>
                                        </div>
                                        <div class="col-md-6 mb-4">
                                            <label class="form-label">Email Address <span class="text-danger">*</span></label>
                                            <div class="input-group">
                                                <span class="input-group-text bg-light"><i class="fas fa-envelope text-muted"></i></span>
                                                <input type="email" class="form-control" name="Email" value="<?php echo htmlspecialchars($current_user['Email']); ?>" required>
                                            </div>
                                        </div>
                                        <div class="col-md-12 mb-4">
                                            <label class="form-label">Mobile Number</label>
                                            <div class="input-group">
                                                <span class="input-group-text bg-light"><i class="fas fa-phone text-muted"></i></span>
                                                <input type="text" class="form-control" name="mobile_number" value="<?php echo htmlspecialchars($current_user['mobile_number'] ?? ''); ?>">
                                            </div>
                                        </div>
                                        <div class="col-md-12 mb-4">
                                            <label class="form-label">Shipping Address</label>
                                            <div class="input-group">
                                                <span class="input-group-text bg-light align-items-start pt-3"><i class="fas fa-map-marker-alt text-muted"></i></span>
                                                <textarea class="form-control" name="address" rows="3"><?php echo htmlspecialchars($current_user['address'] ?? ''); ?></textarea>
                                            </div>
                                        </div>
                                    </div>
                                    <div class="text-end mt-2">
                                        <button type="submit" class="btn btn-primary px-4 shadow-sm" style="background-color: var(--primary-color); border:none;"><i class="fas fa-save me-2"></i> Save Changes</button>
                                    </div>
                                </form>
                            </div>
                            
                            <div class="tab-pane fade" id="change-security" role="tabpanel">
                                <div class="alert alert-info border-info border-opacity-25 bg-info bg-opacity-10 d-flex align-items-center mb-4">
                                    <i class="fas fa-info-circle fs-4 text-info me-3"></i>
                                    <div class="small text-dark">If you enter a new password, your account will instantly update.</div>
                                </div>
                                <form method="POST">
                                    <input type="hidden" name="action" value="update_password">
                                    <div class="mb-4">
                                        <label class="form-label text-dark" style="color:#1f2937 !important;">Current Password <span class="text-danger">*</span></label>
                                        <div class="input-group">
                                            <span class="input-group-text bg-light"><i class="fas fa-key text-muted"></i></span>
                                            <input type="password" class="form-control" name="current_password" required>
                                        </div>
                                    </div>
                                    <hr class="text-muted my-4">
                                    <div class="row">
                                        <div class="col-md-6 mb-4">
                                            <label class="form-label text-dark" style="color:#1f2937 !important;">New Password <span class="text-danger">*</span></label>
                                            <div class="input-group">
                                                <span class="input-group-text bg-light"><i class="fas fa-lock text-muted"></i></span>
                                                <input type="password" class="form-control" name="new_password" required minlength="6">
                                            </div>
                                        </div>
                                        <div class="col-md-6 mb-4">
                                            <label class="form-label text-dark" style="color:#1f2937 !important;">Confirm New Password <span class="text-danger">*</span></label>
                                            <div class="input-group">
                                                <span class="input-group-text bg-light"><i class="fas fa-lock text-muted"></i></span>
                                                <input type="password" class="form-control" name="confirm_password" required minlength="6">
                                            </div>
                                        </div>
                                    </div>
                                    <div class="text-end mt-2">
                                        <button type="submit" class="btn btn-danger px-4 shadow-sm"><i class="fas fa-shield-alt me-2"></i> Update Security</button>
                                    </div>
                                </form>
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

    const tabs = document.querySelectorAll('button[data-bs-toggle="tab"]');
    tabs.forEach(tab => {
        tab.addEventListener('shown.bs.tab', event => {
            tabs.forEach(t => { t.style.color = ''; t.classList.add('text-muted'); t.classList.remove('text-dark'); });
            event.target.style.color = 'var(--primary-color)';
            event.target.classList.remove('text-muted');
            event.target.classList.add('text-dark');
        });
    });
</script>
</body>
</html>
