<?php
session_start();
require_once '../config/db.php';

if (!isset($_SESSION['UserID']) || strtolower($_SESSION['Role']) !== 'admin') {
    header("Location: ../login.php");
    exit();
}

$admin_name = $_SESSION['UserName'] ?? 'Administrator';

// Handle Delete Action
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['action']) && $_POST['action'] == 'delete') {
    $target_product_id = intval($_POST['product_id'] ?? 0);
    if ($target_product_id > 0) {
        $stmt = $conn->prepare("UPDATE Products SET markasdeleted=1 WHERE ProductID=?");
        $stmt->bind_param("i", $target_product_id);
        $stmt->execute();
        header("Location: products_directory.php");
        exit();
    }
}

// ---- PRODUCTION METRICS CALCULATION ----
// Since production matches orders, we sum the Quantity from OrderDetails for non-cancelled orders

function getProductionMetric($conn, $timeCondition) {
    $query = "
        SELECT IFNULL(SUM(od.Quantity), 0) as total 
        FROM OrderDetails od 
        JOIN Orders o ON od.OrderID = o.OrderID 
        WHERE o.OrderStatus != 'cancelled' 
        AND o.markasdeleted=0 
        AND " . $timeCondition;
    $res = $conn->query($query);
    return $res->fetch_assoc()['total'] ?? 0;
}

// 1. Total Active Products
$prod_res = $conn->query("SELECT COUNT(*) as cnt FROM Products WHERE markasdeleted=0");
$total_products = $prod_res->fetch_assoc()['cnt'] ?? 0;

// 2. Production Yesterday
$prod_yesterday = getProductionMetric($conn, "DATE(o.OrderDate) = CURDATE() - INTERVAL 1 DAY");

// 3. Production This Week
$prod_week = getProductionMetric($conn, "YEARWEEK(o.OrderDate, 1) = YEARWEEK(CURDATE(), 1)");

// 4. Production Current Month
$prod_cur_month = getProductionMetric($conn, "MONTH(o.OrderDate) = MONTH(CURDATE()) AND YEAR(o.OrderDate) = YEAR(CURDATE())");

// 5. Production Last Month
$prod_last_month = getProductionMetric($conn, "MONTH(o.OrderDate) = MONTH(CURDATE() - INTERVAL 1 MONTH) AND YEAR(o.OrderDate) = YEAR(CURDATE() - INTERVAL 1 MONTH)");


// ---- FETCH PRODUCTS LIST ----
$search_query = "";
if (isset($_GET['search']) && !empty(trim($_GET['search']))) {
    $search_term = $conn->real_escape_string(trim($_GET['search']));
    $search_query = " AND (p.ProductName LIKE '%$search_term%' OR p.Description LIKE '%$search_term%') ";
}

$products = [];
$p_query = "
    SELECT p.*, 
           IFNULL((
               SELECT SUM(od.Quantity) 
               FROM OrderDetails od 
               JOIN Orders o ON od.OrderID = o.OrderID 
               WHERE od.ProductID = p.ProductID 
               AND o.OrderStatus != 'cancelled' 
               AND o.markasdeleted=0
           ), 0) as TotalProduced
    FROM Products p 
    WHERE p.markasdeleted=0 $search_query 
    ORDER BY p.ProductID DESC
";
$res_p = $conn->query($p_query);
if ($res_p) {
    while ($row = $res_p->fetch_assoc()) {
        $products[] = $row;
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Products Directory - SupplyNet Admin</title>
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
        .metric-card { border-left: 0.25rem solid; padding: 1rem; border-radius: 0.5rem; background: #fff; display: flex; align-items: center; justify-content: space-between; box-shadow: 0 0.15rem 1.75rem 0 rgba(58, 59, 69, 0.1); height: 100%; }
        .metric-title { font-size: 0.75rem; font-weight: 700; text-transform: uppercase; margin-bottom: 0.25rem; }
        .metric-value { font-size: 1.5rem; font-weight: 800; color: #5a5c69; margin: 0; }
        .metric-icon { font-size: 2rem; color: #dddfeb; }
        
        .metric-primary { border-left-color: var(--primary-color); }
        .metric-primary .metric-title { color: var(--primary-color); }
        
        .metric-success { border-left-color: var(--success-color); }
        .metric-success .metric-title { color: var(--success-color); }
        
        .metric-info { border-left-color: var(--info-color); }
        .metric-info .metric-title { color: var(--info-color); }
        
        .metric-warning { border-left-color: var(--warning-color); }
        .metric-warning .metric-title { color: var(--warning-color); }

        .metric-dark { border-left-color: #6c757d; }
        .metric-dark .metric-title { color: #6c757d; }
    
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
            <h4 class="m-0 fw-bold text-dark">Products Directory</h4>
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
                <div class="metric-card metric-primary">
                    <div>
                        <div class="metric-title">Total Active Products</div>
                        <div class="metric-value"><?php echo $total_products; ?> <span style="font-size:0.8rem; font-weight: 500; color:#858796;">items</span></div>
                    </div>
                    <i class="fas fa-box metric-icon"></i>
                </div>
            </div>
            
            <div class="col-xl col-md-4 col-sm-6">
                <div class="metric-card metric-info">
                    <div>
                        <div class="metric-title">Units Produced (Current Month)</div>
                        <div class="metric-value"><?php echo number_format($prod_cur_month); ?></div>
                    </div>
                    <i class="fas fa-calendar-alt metric-icon"></i>
                </div>
            </div>

            <div class="col-xl col-md-4 col-sm-6">
                <div class="metric-card metric-warning">
                    <div>
                        <div class="metric-title">Production (This Week)</div>
                        <div class="metric-value"><?php echo number_format($prod_week); ?></div>
                    </div>
                    <i class="fas fa-calendar-week metric-icon"></i>
                </div>
            </div>
            
            <div class="col-xl col-md-6 col-sm-6">
                <div class="metric-card metric-success">
                    <div>
                        <div class="metric-title">Production (Yesterday)</div>
                        <div class="metric-value"><?php echo number_format($prod_yesterday); ?></div>
                    </div>
                    <i class="fas fa-calendar-day metric-icon"></i>
                </div>
            </div>

            <div class="col-xl col-md-6 col-sm-6">
                <div class="metric-card metric-dark">
                    <div>
                        <div class="metric-title">Production (Last Month)</div>
                        <div class="metric-value"><?php echo number_format($prod_last_month); ?></div>
                    </div>
                    <i class="fas fa-history metric-icon"></i>
                </div>
            </div>
        </div>

        <div class="d-flex flex-wrap justify-content-between align-items-center gap-3 mb-3">
            <h5 class="m-0 fw-bold text-dark border-start border-4 border-primary ps-2">Product Catalog</h5>
            <div class="d-flex gap-2">
                <a href="deleted_products.php" class="btn btn-outline-danger shadow-sm px-3 py-2" title="Deleted Products Archive"><i class="fas fa-trash-restore"></i></a>
                <a href="add_product.php" class="btn btn-primary shadow-sm px-3 py-2" title="Add New Product"><i class="fas fa-plus"></i></a>
            </div>
        </div>

        <!-- Products Table Card -->
        <div class="card card-custom">
            <div class="card-header bg-white border-bottom py-3 d-flex flex-wrap justify-content-between align-items-center gap-3">
                
                <form action="products_directory.php" method="GET" class="d-flex m-0" style="flex: 1; min-width: 300px; max-width: 450px;" onsubmit="return false;">
                    <div class="input-group shadow-sm" style="border-radius: 50rem; overflow: hidden; background: #fcfcfc; border: 1px solid #e3e6f0;">
                        <span class="input-group-text bg-transparent border-0 text-primary ps-3 pe-2">
                            <i class="fas fa-search"></i>
                        </span>
                        <input type="text" id="searchInput" name="search" class="form-control bg-transparent border-0 shadow-none ps-1" placeholder="Search products..." value="<?php echo htmlspecialchars($_GET['search'] ?? ''); ?>" style="font-size: 0.9rem;">
                        <?php if(!empty($_GET['search'])): ?>
                            <a href="products_directory.php" class="input-group-text bg-transparent border-0 text-secondary text-decoration-none pe-3" title="Clear Search">
                                <i class="fas fa-times-circle"></i>
                            </a>
                        <?php endif; ?>
                        <button class="btn btn-primary px-4" type="submit" style="font-weight: 600; letter-spacing: 0.03rem;">Search</button>
                    </div>
                </form>
            </div>
            <div class="card-body p-0">
                <div class="table-responsive">
                    <table class="table table-hover align-middle mb-0">
                        <thead class="table-light">
                            <tr>
                                <th class="ps-4">Product Name</th>
                                <th>Description</th>
                                <th>Unit Price</th>
                                <th>Total Lifetime Produced</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody id="dataTableBody">
                            <?php if(empty($products)): ?>
                                <tr><td colspan="5" class="text-center py-5 text-muted"><i class="fas fa-box-open d-block fs-2 mb-3 text-black-50 opacity-25"></i>No products found. Add a new product to get started.</td></tr>
                            <?php else: ?>
                                <?php foreach($products as $p): ?>
                                <tr>
                                    <td class="ps-4 fw-bold text-dark"><?php echo htmlspecialchars($p['ProductName']); ?></td>
                                    <td>
                                        <div class="text-truncate text-muted" style="max-width: 300px; font-size: 0.9rem;">
                                            <?php echo htmlspecialchars($p['Description']); ?>
                                        </div>
                                    </td>
                                    <td class="fw-bold text-success">₹<?php echo number_format($p['Price'], 2); ?></td>
                                    <td>
                                        <span class="badge bg-secondary bg-opacity-10 text-secondary border border-secondary-subtle px-3 py-2 fs-6">
                                            <i class="fas fa-industry me-2"></i><?php echo number_format($p['TotalProduced']); ?> Units
                                        </span>
                                    </td>
                                    <td>
                                        <div class="d-flex gap-2">
                                            <a title="Edit Product" href="edit_product.php?id=<?php echo $p['ProductID']; ?>" class="btn btn-sm btn-info text-white"><i class="fas fa-edit"></i></a>
                                            <form action="products_directory.php" method="POST" class="m-0 p-0" onsubmit="return confirm('Are you sure you want to delete this product?');">
                                                <input type="hidden" name="action" value="delete">
                                                <input type="hidden" name="product_id" value="<?php echo $p['ProductID']; ?>">
                                                <button title="Delete" type="submit" class="btn btn-sm btn-danger"><i class="fas fa-trash-alt"></i></button>
                                            </form>
                                        </div>
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
                // Skip the "No products found" row if it exists
                if (rows[i].cells.length === 1) continue;
                
                // Get Name and Description cells (index 0 and 1)
                const nameText = rows[i].cells[0].textContent.toLowerCase();
                const descText = rows[i].cells[1].textContent.toLowerCase();
                
                if (nameText.includes(searchTerm) || descText.includes(searchTerm)) {
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
