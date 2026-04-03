<?php
session_start();
require_once '../config/db.php';

// Auth Protection
if (!isset($_SESSION['UserID']) || strtolower($_SESSION['Role']) !== 'salesman') {
    header("Location: ../login.php");
    exit();
}

$salesman_name = $_SESSION['UserName'] ?? 'Sales Representative';
$salesman_id = $_SESSION['UserID'];

$success_msg = '';
$error_msg = '';
$invoice_data = null;

// Handle Order Placement
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'place_order') {
    $customer_id = (int)$_POST['customer_id'];
    $product_id = (int)$_POST['product_id'];
    $quantity = (int)$_POST['quantity'];
    $required_date = trim($_POST['required_date']);
    
    // Validate stock and get price
    $prod_res = $conn->query("SELECT ProductName, Price, StockQuantity FROM Products WHERE ProductID=$product_id AND markasdeleted=0");
    $cust_res = $conn->query("SELECT UserName, Email, address FROM Users WHERE UserID=$customer_id AND Role='customer'");
    
    if ($prod_res && $prod_res->num_rows > 0 && $cust_res && $cust_res->num_rows > 0) {
        $product = $prod_res->fetch_assoc();
        $customer = $cust_res->fetch_assoc();
        
        if ($quantity > 0 && !empty($required_date)) {
            $unit_price = (float)$product['Price'];
            $total_amount = $unit_price * $quantity;
            $customer_address = $customer['address'] ? $customer['address'] : 'Address not provided.';
            
            // Check for placed_by column in Orders table, add if missing for thorough tracking
            $check_col = $conn->query("SHOW COLUMNS FROM Orders LIKE 'placed_by'");
            if ($check_col && $check_col->num_rows == 0) {
                $conn->query("ALTER TABLE Orders ADD COLUMN placed_by INT DEFAULT NULL");
            }

            // Check for RequiredDate column
            $check_req_date = $conn->query("SHOW COLUMNS FROM Orders LIKE 'RequiredDate'");
            if ($check_req_date && $check_req_date->num_rows == 0) {
                $conn->query("ALTER TABLE Orders ADD COLUMN RequiredDate DATE DEFAULT NULL");
            }

            // Begin Transaction
            $conn->begin_transaction();
            try {
                // 1. Insert into Orders (using customer's address for shipping & billing)
                $stmt1 = $conn->prepare("INSERT INTO Orders (UserID, ProductID, TotalAmount, ShippingAddress, BillingAddress, OrderStatus, placed_by, RequiredDate) VALUES (?, ?, ?, ?, ?, 'pending', ?, ?)");
                $stmt1->bind_param("iidssis", $customer_id, $product_id, $total_amount, $customer_address, $customer_address, $salesman_id, $required_date);
                $stmt1->execute();
                $order_id = $conn->insert_id;
                
                // 2. Insert into OrderDetails
                $stmt2 = $conn->prepare("INSERT INTO OrderDetails (OrderID, ProductID, Quantity, UnitPrice) VALUES (?, ?, ?, ?)");
                $stmt2->bind_param("iiid", $order_id, $product_id, $quantity, $unit_price);
                $stmt2->execute();
                
                // 3. Update Stock
                $new_stock = $product['StockQuantity'] - $quantity;
                $stmt3 = $conn->prepare("UPDATE Products SET StockQuantity=? WHERE ProductID=?");
                $stmt3->bind_param("ii", $new_stock, $product_id);
                $stmt3->execute();
                
                $conn->commit();
                
                // Set success message with a formal link to the dedicated report generator
                $success_msg = "Order successfully placed! <a href='generateReport.php?id=$order_id' target='_blank' class='btn btn-sm btn-outline-success ms-3 fw-bold shadow-sm'><i class='fas fa-print me-1'></i> Print Official Sales Report</a>";
                
                // Clear the quantity input logic dynamically via generic reload if wanted, but session data is clean.
                
            } catch (Exception $e) {
                $conn->rollback();
                $error_msg = "Failed to process order: " . $e->getMessage();
            }
        } else {
            $error_msg = "Invalid quantity or required delivery date specified.";
        }
    } else {
        $error_msg = "Invalid product or customer selected.";
    }
}

// Fetch Active Customers
$customers = [];
$res_c = $conn->query("SELECT UserID, UserName, Email, mobile_number, address FROM Users WHERE Role='customer' AND IsActive=1 AND markasdeleted=0 ORDER BY UserName ASC");
if ($res_c) {
    while($row = $res_c->fetch_assoc()) $customers[] = $row;
}

// Fetch All Available Products
$products = [];
$res_p = $conn->query("SELECT ProductID, ProductName, Price, StockQuantity FROM Products WHERE markasdeleted=0 ORDER BY ProductName ASC");
if ($res_p) {
    while($row = $res_p->fetch_assoc()) $products[] = $row;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Place Order - SupplyNet</title>
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
        
        /* Interactive Elements */
        .form-control, .form-select { border-radius: 0.5rem; border: 1px solid #d1d3e2; padding: 0.75rem; transition: border-color 0.2s, box-shadow 0.2s; }
        .form-control:focus, .form-select:focus { border-color: var(--primary-color); box-shadow: 0 0 0 0.25rem rgba(54, 185, 204, 0.25); }

        /* Custom Search Dropdown */
        .searchable-dropdown { position: relative; }
        .searchable-list { position: absolute; top: 100%; left: 0; right: 0; background: #fff; box-shadow: 0 0.5rem 1rem rgba(0,0,0,0.15); border-radius: 0.5rem; z-index: 100; max-height: 250px; overflow-y: auto; display: none; border: 1px solid #e3e6f0; }
        .searchable-list.show { display: block; }
        .searchable-item { padding: 0.75rem 1rem; cursor: pointer; border-bottom: 1px solid #f8f9fc; }
        .searchable-item:hover { background-color: #f1f5f9; }
        
        /* Total Display Box */
        .total-box { background: linear-gradient(135deg, #1cc88a 0%, #13855c 100%); color: white; border-radius: 0.5rem; padding: 1.5rem; text-align: center; }
        .total-box h3 { margin: 0; font-weight: 800; font-size: 2rem; }
        
        .summary-grid { display: flex; flex-direction: column; gap: 15px; }
        .summary-item { background: #f8f9fc; padding: 15px; border-radius: 10px; border: 1px solid #e3e6f0; display: flex; flex-direction: column; align-items: center; justify-content: center; transition: all 0.2s; }
        .summary-item:hover { border-color: var(--primary-color); background: #fff; transform: translateY(-2px); box-shadow: 0 4px 6px rgba(0,0,0,0.05); }

        @media (max-width: 991px) {
            .sticky-top { position: static !important; }
            .order-form-container { display: flex; flex-direction: column-reverse; }
            .order-summary-card { margin-bottom: 2rem; }
            .summary-grid { display: grid; grid-template-columns: repeat(2, 1fr); gap: 10px; }
            .summary-item { padding: 10px; }
            .summary-total { grid-column: span 2; margin-top: 5px; }
        }

        @media (max-width: 768px) {
            .sidebar { transform: translateX(-100%); transition: transform 0.3s ease-in-out; }
            .sidebar.show-sidebar { transform: translateX(0); }
            .main-wrapper { margin-left: 0; width: 100%; }
            .sidebar-overlay { display: none; position: fixed; top: 0; left: 0; right: 0; bottom: 0; background: rgba(0,0,0,0.5); z-index: 999; cursor: pointer; }
            .sidebar-overlay.show { display: block; }
            .topbar { padding: 0 1rem; }
            .dashboard-content { padding: 1rem; }
            .searchable-list { position: fixed; top: auto; bottom: 0; left: 0; right: 0; max-height: 50vh; border-radius: 20px 20px 0 0; }
        }
    </style>
</head>
<body>

<!-- Sidebar -->
<div class="sidebar">
    <a href="index.php" class="brand"><i class="fas fa-cubes me-2"></i>SupplyNet<br><small class="text-white-50" style="font-size: 0.7rem;">Sales Panel</small></a>
    <div class="mt-4">
        <div class="nav-item"><a href="index.php" class="nav-link"><i class="fas fa-home"></i> Sales Dashboard</a></div>
        <div class="nav-item"><a href="manageCustomer.php" class="nav-link"><i class="fas fa-address-book"></i> Customers List</a></div>
        <div class="nav-item"><a href="placeOrder.php" class="nav-link active"><i class="fas fa-cart-plus"></i> Place Order</a></div>
        <div class="nav-item"><a href="viewOrderHistory.php" class="nav-link"><i class="fas fa-history"></i> Order History</a></div>
    </div>
</div>

<div class="sidebar-overlay" id="sidebarOverlay"></div>
<div class="main-wrapper">
    <!-- Topbar -->
    <div class="topbar">
        <div class="d-flex align-items-center">
            <button class="btn btn-link d-md-none text-dark me-3" id="sidebarToggle"><i class="fas fa-bars"></i></button>
            <h4 class="m-0 fw-bold text-dark">Place Bulk Order</h4>
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
                    <span class="d-none d-lg-inline"><?php echo htmlspecialchars($salesman_name); ?></span>
                    <img src="https://ui-avatars.com/api/?name=<?php echo urlencode($salesman_name); ?>&background=36b9cc&color=fff" alt="Salesman Profile">
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

        <div class="row order-form-container">
            <div class="col-lg-8">
                <div class="card card-custom mb-4">
                    <div class="card-header bg-white border-bottom py-3">
                        <h6 class="m-0 font-weight-bold" style="color: var(--primary-color) !important;">
                            <i class="fas fa-edit me-2"></i>Order Information
                        </h6>
                    </div>
                    <div class="card-body p-4">
                        <form method="POST" id="orderForm">
                            <input type="hidden" name="action" value="place_order">
                            <input type="hidden" name="customer_id" id="hiddenCustomerId" required>
                            <input type="hidden" name="product_id" id="hiddenProductId" required>
                            
                            <div class="row mb-4">
                                <div class="col-md-12 mb-3">
                                    <label class="form-label fw-semibold text-dark">Find Existing Customer <span class="text-danger">*</span></label>
                                    <div class="searchable-dropdown" id="custDropdownWrapper">
                                        <div class="input-group">
                                            <span class="input-group-text bg-white"><i class="fas fa-search text-muted"></i></span>
                                            <input type="text" class="form-control focus-trigger" id="customerSearch" placeholder="Type customer name or email to search..." autocomplete="off">
                                        </div>
                                        <div class="searchable-list" id="customerList">
                                            <?php foreach($customers as $c): ?>
                                                <div class="searchable-item cust-item" data-id="<?php echo $c['UserID']; ?>" data-name="<?php echo htmlspecialchars($c['UserName']); ?>" data-address="<?php echo htmlspecialchars($c['address']); ?>">
                                                    <div class="fw-bold text-dark"><?php echo htmlspecialchars($c['UserName']); ?></div>
                                                    <div class="small text-muted"><?php echo htmlspecialchars($c['Email']); ?> | <?php echo htmlspecialchars($c['mobile_number']); ?></div>
                                                    <div class="small text-muted fst-italic mt-1"><i class="fas fa-map-marker-alt me-1"></i><?php echo htmlspecialchars($c['address'] ? $c['address'] : 'No address provided'); ?></div>
                                                </div>
                                            <?php endforeach; ?>
                                            <?php if(empty($customers)): ?>
                                                <div class="p-3 text-muted text-center">No active customers found.</div>
                                            <?php endif; ?>
                                        </div>
                                    </div>
                                    <div id="selectedCustomerBadge" class="mt-2 d-none">
                                        <span class="badge bg-success bg-opacity-10 text-success border border-success-subtle px-3 py-2 fs-6">
                                            <i class="fas fa-user-check me-2"></i><span id="selectedCustName"></span>
                                            <i class="fas fa-times ms-2 cursor-pointer" onclick="clearCustomerSelection()" style="cursor:pointer;" title="Clear Selection"></i>
                                        </span>
                                        <div class="mt-2 p-2 bg-light border rounded small">
                                            <strong><i class="fas fa-truck me-1"></i> Delivery Address:</strong><br>
                                            <span id="selectedCustAddress"></span>
                                        </div>
                                    </div>
                                </div>
                                
                                <div class="col-md-12 mb-3 mt-2">
                                    <label class="form-label fw-semibold text-dark">Find Bulk Product <span class="text-danger">*</span></label>
                                    <div class="searchable-dropdown" id="prodDropdownWrapper">
                                        <div class="input-group">
                                            <span class="input-group-text bg-white"><i class="fas fa-box-open text-muted"></i></span>
                                            <input type="text" class="form-control focus-trigger" id="productSearch" placeholder="Type product name to search..." autocomplete="off">
                                        </div>
                                        <div class="searchable-list" id="productList">
                                            <?php foreach($products as $p): ?>
                                                <div class="searchable-item prod-item" data-id="<?php echo $p['ProductID']; ?>" data-name="<?php echo htmlspecialchars($p['ProductName']); ?>" data-price="<?php echo $p['Price']; ?>" data-stock="<?php echo $p['StockQuantity']; ?>">
                                                    <div class="d-flex justify-content-between align-items-center">
                                                        <div class="fw-bold text-dark"><?php echo htmlspecialchars($p['ProductName']); ?></div>
                                                        <div class="fw-bold text-primary">₹<?php echo number_format($p['Price'], 2); ?></div>
                                                    </div>
                                                    <div class="small text-success mt-1">
                                                        <i class="fas fa-hammer me-1"></i>
                                                        Make-to-Order Production
                                                    </div>
                                                </div>
                                            <?php endforeach; ?>
                                            <?php if(empty($products)): ?>
                                                <div class="p-3 text-muted text-center">No products found.</div>
                                            <?php endif; ?>
                                        </div>
                                    </div>
                                    <div id="selectedProductBadge" class="mt-2 d-none">
                                        <span class="badge bg-primary bg-opacity-10 text-primary border border-primary-subtle px-3 py-2 fs-6">
                                            <i class="fas fa-box-check me-2"></i><span id="selectedProdName"></span>
                                            <i class="fas fa-times ms-2 cursor-pointer" onclick="clearProductSelection()" style="cursor:pointer;" title="Clear Selection"></i>
                                        </span>
                                    </div>
                                </div>
                                
                                <div class="col-md-6 mb-3 mt-2">
                                    <label class="form-label fw-semibold text-dark">Bulk Quantity <span class="text-danger">*</span></label>
                                    <div class="input-group">
                                        <span class="input-group-text"><i class="fas fa-sort-numeric-up"></i></span>
                                        <input type="number" name="quantity" id="quantityInput" class="form-control" min="1" value="1" required oninput="calculateTotal()" disabled>
                                    </div>
                                </div>
                                <div class="col-md-6 mb-3 mt-2">
                                    <label class="form-label fw-semibold text-dark">Required Delivery Date <span class="text-danger">*</span></label>
                                    <div class="input-group">
                                        <span class="input-group-text"><i class="fas fa-calendar-alt"></i></span>
                                        <input type="date" name="required_date" class="form-control" required min="<?php echo date('Y-m-d'); ?>">
                                    </div>
                                </div>
                            </div>

                            <div class="d-flex justify-content-end mt-4">
                                <!-- Corporate standard icon button for placing order -->
                                <button type="submit" class="btn btn-primary px-4 py-2 shadow-sm rounded-3" title="Place Bulk Order Request">
                                    <i class="fas fa-paper-plane"></i>
                                </button>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
            
            <div class="col-lg-4">
                <div class="card card-custom sticky-top order-summary-card" style="top: 90px;">
                    <div class="card-header bg-white border-bottom py-3">
                        <h6 class="m-0 font-weight-bold" style="color: var(--primary-color) !important;">Order Summary</h6>
                    </div>
                    <div class="card-body p-4">
                        <div class="summary-grid">
                            <div class="summary-item mb-2 mb-md-0">
                                <span class="text-muted small">Unit Price</span>
                                <span class="fw-bold text-dark" id="displayUnitPrice">₹0.00</span>
                            </div>
                            <div class="summary-item mb-2 mb-md-0">
                                <span class="text-muted small">Quantity</span>
                                <span class="fw-bold text-dark" id="displayQuantity">0</span>
                            </div>
                            <div class="summary-total mt-1">
                                <div class="total-box shadow-sm">
                                    <div class="text-white-50 small text-uppercase fw-bold mb-1">Total Amount Due</div>
                                    <h3 id="displayTotal" class="m-0">₹0.00</h3>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
    <?php include '../config/footer.php'; ?>
</div>

<!-- Bootstrap Bundle with Popper -->
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
<script>
    // Sidebar Toggle Logic
    document.getElementById('sidebarToggle').addEventListener('click', function() {
        document.querySelector('.sidebar').classList.add('show-sidebar');
        document.getElementById('sidebarOverlay').classList.add('show');
    });

    document.getElementById('sidebarOverlay').addEventListener('click', function() {
        document.querySelector('.sidebar').classList.remove('show-sidebar');
        this.classList.remove('show');
    });

    // Customer Search Logic
    const custSearch = document.getElementById('customerSearch');
    const custList = document.getElementById('customerList');
    const custItems = document.querySelectorAll('.cust-item');
    const hiddenCustId = document.getElementById('hiddenCustomerId');
    const custBadge = document.getElementById('selectedCustomerBadge');
    const custBadgeName = document.getElementById('selectedCustName');
    const custAddressDisplay = document.getElementById('selectedCustAddress');

    custSearch.addEventListener('focus', () => custList.classList.add('show'));
    
    // Product Search Logic
    const prodSearch = document.getElementById('productSearch');
    const prodList = document.getElementById('productList');
    const prodItems = document.querySelectorAll('.prod-item');
    const hiddenProdId = document.getElementById('hiddenProductId');
    const prodBadge = document.getElementById('selectedProductBadge');
    const prodBadgeName = document.getElementById('selectedProdName');
    
    let currentProdPrice = 0;
    let currentProdStock = 0;

    prodSearch.addEventListener('focus', () => prodList.classList.add('show'));

    // Global click listener to close native dropdowns
    document.addEventListener('click', (e) => {
        if (!custSearch.contains(e.target) && !custList.contains(e.target)) {
            custList.classList.remove('show');
        }
        if (!prodSearch.contains(e.target) && !prodList.contains(e.target)) {
            prodList.classList.remove('show');
        }
    });

    // Filter Customer Array
    custSearch.addEventListener('input', function() {
        const val = this.value.toLowerCase().trim();
        custItems.forEach(item => {
            const text = item.innerText.toLowerCase();
            item.style.display = text.includes(val) ? '' : 'none';
        });
    });

    // Handle Customer Selection
    custItems.forEach(item => {
        item.addEventListener('click', function() {
            hiddenCustId.value = this.dataset.id;
            custBadgeName.textContent = this.dataset.name;
            custAddressDisplay.textContent = this.dataset.address ? this.dataset.address : 'No registered address available.';
            custSearch.value = '';
            
            document.getElementById('custDropdownWrapper').classList.add('d-none');
            custBadge.classList.remove('d-none');
            custList.classList.remove('show');
        });
    });

    function clearCustomerSelection() {
        hiddenCustId.value = '';
        document.getElementById('custDropdownWrapper').classList.remove('d-none');
        custBadge.classList.add('d-none');
        custSearch.focus();
    }

    // Filter Product Array
    prodSearch.addEventListener('input', function() {
        const val = this.value.toLowerCase().trim();
        prodItems.forEach(item => {
            const text = item.innerText.toLowerCase();
            item.style.display = text.includes(val) ? '' : 'none';
        });
    });

    // Handle Product Selection
    prodItems.forEach(item => {
        item.addEventListener('click', function() {
            hiddenProdId.value = this.dataset.id;
            prodBadgeName.textContent = this.dataset.name;
            currentProdPrice = parseFloat(this.dataset.price);
            currentProdStock = parseInt(this.dataset.stock);
            
            prodSearch.value = '';
            document.getElementById('prodDropdownWrapper').classList.add('d-none');
            prodBadge.classList.remove('d-none');
            prodList.classList.remove('show');
            
            document.getElementById('quantityInput').disabled = false;
            document.getElementById('quantityInput').value = 1;
            calculateTotal();
        });
    });

    function clearProductSelection() {
        hiddenProdId.value = '';
        currentProdPrice = 0;
        currentProdStock = 0;
        document.getElementById('prodDropdownWrapper').classList.remove('d-none');
        prodBadge.classList.add('d-none');
        
        document.getElementById('quantityInput').disabled = true;
        document.getElementById('quantityInput').value = 1;
        calculateTotal();
        
        prodSearch.focus();
    }

    // Dynamic Total Calculation
    function calculateTotal() {
        const qtyInput = document.getElementById('quantityInput');
        let qty = parseInt(qtyInput.value) || 0;
        
        if (qty < 1 && hiddenProdId.value) {
            qtyInput.value = 1;
            qty = 1;
        }
        
        const total = currentProdPrice * qty;
        
        document.getElementById('displayUnitPrice').textContent = '₹' + currentProdPrice.toFixed(2);
        document.getElementById('displayQuantity').textContent = qty;
        document.getElementById('displayTotal').textContent = '₹' + total.toFixed(2);
    }
    
    // Validate form before submit explicitly
    document.getElementById('orderForm')?.addEventListener('submit', function(e) {
        if (!document.getElementById('hiddenCustomerId').value) {
            e.preventDefault();
            alert('Please search and select a customer first before placing an order.');
            custSearch.focus();
            return;
        }
        if (!document.getElementById('hiddenProductId').value) {
            e.preventDefault();
            alert('Please search and select a bulk product first before placing an order.');
            prodSearch.focus();
            return;
        }
    });
</script>
</body>
</html>
