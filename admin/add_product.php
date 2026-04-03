<?php
session_start();
require_once '../config/db.php';

// Auth Protection
if (!isset($_SESSION['UserID']) || strtolower($_SESSION['Role']) !== 'admin') {
    header("Location: ../login.php");
    exit();
}

$admin_name = $_SESSION['UserName'] ?? 'Administrator';
$message = '';

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $product_name = trim($_POST['product_name'] ?? '');
    $description = trim($_POST['description'] ?? '');
    $price = floatval($_POST['price'] ?? 0);
    $stock_quantity = 0; // Production happens when orders come, so initial stock is 0

    if (empty($product_name) || $price <= 0) {
        $message = '<div class="alert alert-danger"><i class="fas fa-exclamation-circle me-2"></i>Please provide a valid product name and price.</div>';
    } else {
        $stmt = $conn->prepare("INSERT INTO Products (ProductName, Description, Price, StockQuantity) VALUES (?, ?, ?, ?)");
        $stmt->bind_param("ssdi", $product_name, $description, $price, $stock_quantity);
        
        if ($stmt->execute()) {
            $message = '<div class="alert alert-success"><i class="fas fa-check-circle me-2"></i>Product was successfully added to the catalog!</div>';
            // Optional: redirect to directory after a short delay or just show the message with a button Back
        } else {
            $message = '<div class="alert alert-danger"><i class="fas fa-times-circle me-2"></i>Failed to add product. Please try again.</div>';
        }
        $stmt->close();
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Add Product - SupplyNet Admin</title>
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
        
        .form-control { border-radius: 0.5rem; padding: 0.75rem 1rem; border: 1px solid #d1d3e2; }
        .form-control:focus { border-color: var(--primary-color); box-shadow: 0 0 0 0.25rem rgba(78, 115, 223, 0.25); }
        .form-label { font-weight: 600; color: var(--dark-color); }
    
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
        <div class="nav-item"><a href="user_management.php" class="nav-link"><i class="fas fa-users"></i> User Management</a></div>
        <div class="nav-item"><a href="products_directory.php" class="nav-link active"><i class="fas fa-box-open"></i> Products Directory</a></div>
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
            <h4 class="m-0 fw-bold text-dark">Add New Product</h4>
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
        
        <div class="mb-4">
            <a href="products_directory.php" class="btn btn-secondary shadow-sm"><i class="fas fa-arrow-left me-2"></i>Back to Directory</a>
        </div>

        <div class="row">
            <div class="col-lg-8">
                <div class="card card-custom">
                    <div class="card-header bg-white border-bottom py-3">
                        <h6 class="m-0 font-weight-bold text-primary" style="font-weight: 700;"><i class="fas fa-box me-2"></i>Product Details</h6>
                    </div>
                    <div class="card-body p-4">
                        <?php echo $message; ?>
                        
                        <form action="add_product.php" method="POST">
                            <div class="mb-4">
                                <label for="product_name" class="form-label">Product Name <span class="text-danger">*</span></label>
                                <input type="text" class="form-control" id="product_name" name="product_name" placeholder="Enter the product's official name" required>
                            </div>
                            
                            <div class="mb-4">
                                <label for="price" class="form-label">Unit Price (₹) <span class="text-danger">*</span></label>
                                <div class="input-group">
                                    <span class="input-group-text bg-light border-end-0 fw-bold text-secondary px-3" style="border-radius: 0.5rem 0 0 0.5rem;">₹</span>
                                    <input type="number" step="0.01" min="0.01" class="form-control border-start-0 ps-0" id="price" name="price" placeholder="0.00" required>
                                </div>
                            </div>

                            <div class="mb-4">
                                <label for="description" class="form-label">Detailed Description</label>
                                <textarea class="form-control" id="description" name="description" rows="5" placeholder="Provide full features and product specifications here..."></textarea>
                            </div>

                            <div class="alert alert-info border-info border-opacity-25 bg-info bg-opacity-10 d-flex align-items-center mb-4">
                                <i class="fas fa-info-circle fs-4 text-info me-3"></i>
                                <div>
                                    <strong>Inventory Note:</strong> Since production is purely order-driven, this product will initially be added to the catalog with <strong>0 units</strong> in stock. Stock tracking is handled via production orders later.
                                </div>
                            </div>
                            
                            <hr class="my-4 text-muted opacity-25">
                            
                            <div class="d-flex justify-content-end gap-2">
                                <a href="products_directory.php" class="btn btn-light border fw-bold text-secondary px-4">Cancel</a>
                                <button type="submit" class="btn btn-primary fw-bold px-4 shadow-sm"><i class="fas fa-save me-2"></i>Save Product Catalog</button>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
            
            <div class="col-lg-4 d-none d-lg-block">
                <div class="card card-custom bg-primary text-white text-center p-5 h-100 d-flex flex-column justify-content-center align-items-center" style="background: linear-gradient(135deg, #4e73df 0%, #224abe 100%);">
                    <i class="fas fa-cube text-white-50 mb-3" style="font-size: 5rem;"></i>
                    <h5 class="fw-bold mb-3">Expand Your Inventory</h5>
                    <p class="text-white-50 small mb-0">Adding products to your supply chain registry allows salesmen and customers to immediately start generating production orders for them.</p>
                </div>
            </div>
        </div>

    </div>
    <?php include '../config/footer.php'; ?>
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
