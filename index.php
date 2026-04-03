<?php
include 'config/db.php';

// Queries for totals
$total_products = mysqli_fetch_assoc(mysqli_query($conn, "SELECT COUNT(*) as count FROM Products WHERE markasdeleted=0"))['count'];
$total_employees = mysqli_fetch_assoc(mysqli_query($conn, "SELECT COUNT(*) as count FROM Users WHERE Role='employee' AND markasdeleted=0"))['count'];
$total_customers = mysqli_fetch_assoc(mysqli_query($conn, "SELECT COUNT(*) as count FROM Users WHERE Role='customer' AND markasdeleted=0"))['count'];
$total_delivery = mysqli_fetch_assoc(mysqli_query($conn, "SELECT COUNT(*) as count FROM Users WHERE Role='delivery_person' AND markasdeleted=0"))['count'];

// Query for top 5 products by sales
$chart_query = mysqli_query($conn, "SELECT p.ProductName, SUM(o.TotalAmount) as sales FROM Orders o JOIN Products p ON o.ProductID = p.ProductID WHERE o.markasdeleted=0 AND p.markasdeleted=0 GROUP BY o.ProductID ORDER BY sales DESC LIMIT 5");
$labels = [];
$data = [];
while($row = mysqli_fetch_assoc($chart_query)) {
    $labels[] = $row['ProductName'];
    $data[] = $row['sales'];
}
$chart_data = ['labels' => $labels, 'data' => $data];

$analytics_data = [
    'employees' => $total_employees,
    'customers' => $total_customers,
    'delivery' => $total_delivery
];
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>SupplyNet - Dashboard</title>
    <!-- Fonts -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    <!-- Bootstrap CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    
    <style>
        :root {
            --primary-bg: #f8f9fc;
            --card-bg: #ffffff;
            --primary-color: #4e73df;
            --secondary-color: #858796;
            --success-color: #1cc88a;
            --info-color: #36b9cc;
            --warning-color: #f6c23e;
            --text-dark: #3a3b45;
            --shadow-sm: 0 0.125rem 0.25rem 0 rgba(58, 59, 69, 0.2);
            --shadow: 0 0.15rem 1.75rem 0 rgba(58, 59, 69, 0.15);
            --shadow-hover: 0 0.5rem 2rem 0 rgba(58, 59, 69, 0.2);
        }
        
        body {
            font-family: 'Inter', sans-serif;
            background-color: var(--primary-bg);
            color: var(--secondary-color);
            overflow-x: hidden;
        }

        h1, h2, h3, h4, h5, h6 {
            color: var(--text-dark);
            font-weight: 700;
        }

        /* Glassmorphism Navbar */
        .navbar-custom {
            background: rgba(255, 255, 255, 0.9) !important;
            backdrop-filter: blur(10px);
            border-bottom: 1px solid rgba(0,0,0,0.05);
            transition: all 0.3s ease;
        }
        .navbar-custom .navbar-brand {
            font-weight: 800;
            color: var(--primary-color) !important;
            font-size: 1.5rem;
        }
        .navbar-custom .nav-link {
            color: var(--text-dark) !important;
            font-weight: 500;
            margin: 0 0.5rem;
            transition: color 0.3s ease;
        }
        .navbar-custom .nav-link:hover, .navbar-custom .nav-link.active {
            color: var(--primary-color) !important;
        }

        /* Hero Section */
        .hero-section {
            padding: 5rem 0;
            background: linear-gradient(135deg, rgba(78,115,223,0.1) 0%, rgba(255,255,255,0) 100%);
            border-radius: 1rem;
            margin-bottom: 3rem;
        }
        .hero-title {
            font-size: 3.5rem;
            font-weight: 800;
            background: linear-gradient(to right, #4e73df, #36b9cc);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            margin-bottom: 1rem;
        }

        /* Cards */
        .card-custom {
            border: none;
            border-radius: 1rem;
            box-shadow: var(--shadow);
            transition: transform 0.3s ease, box-shadow 0.3s ease;
            background: var(--card-bg);
            overflow: hidden;
        }
        .card-custom:hover {
            transform: translateY(-5px);
            box-shadow: var(--shadow-hover);
        }
        .card-icon-box {
            width: 4rem;
            height: 4rem;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.75rem;
        }
        
        .icon-primary { background: rgba(78, 115, 223, 0.1); color: var(--primary-color); }
        .icon-success { background: rgba(28, 200, 138, 0.1); color: var(--success-color); }
        .icon-info { background: rgba(54, 185, 204, 0.1); color: var(--info-color); }
        .icon-warning { background: rgba(246, 194, 62, 0.1); color: var(--warning-color); }

        .stat-value {
            font-size: 1.8rem;
            font-weight: 700;
            color: var(--text-dark);
        }
        .stat-label {
            font-size: 0.85rem;
            text-transform: uppercase;
            letter-spacing: 1px;
            font-weight: 600;
        }

        /* Charts */
        .chart-container-custom {
            position: relative;
            height: 300px;
            width: 100%;
        }
        

        /* Buttons */
        .btn-custom {
            border-radius: 50rem;
            padding: 0.6rem 1.5rem;
            font-weight: 600;
            transition: all 0.3s;
        }
        .btn-custom-primary {
            background: var(--primary-color);
            color: white;
            border: none;
            box-shadow: 0 4px 10px rgba(78, 115, 223, 0.3);
        }
        .btn-custom-primary:hover {
            background: #2e59d9;
            color: white;
            transform: translateY(-2px);
            box-shadow: 0 6px 15px rgba(78, 115, 223, 0.4);
        }
    </style>
</head>
<body>

<!-- Navbar -->
<nav class="navbar navbar-expand-lg navbar-light navbar-custom sticky-top shadow-sm py-3">
    <div class="container">
        <a class="navbar-brand" href="#"><i class="fas fa-cubes me-2"></i>SupplyNet</a>
        <button class="navbar-toggler border-0" type="button" data-bs-toggle="collapse" data-bs-target="#navbarNav">
            <span class="navbar-toggler-icon"></span>
        </button>
        <div class="collapse navbar-collapse" id="navbarNav">
            <ul class="navbar-nav mx-auto mb-2 mb-lg-0">
                <li class="nav-item">
                    <a class="nav-link active" href="index.php">Home</a>
                </li>
                <li class="nav-item">
                    <a class="nav-link" href="features.php">Features</a>
                </li>
                <li class="nav-item">
                    <a class="nav-link" href="contact.php">Contact</a>
                </li>
                <li class="nav-item">
                    <a class="nav-link" href="about.php">About</a>
                </li>
            </ul>
            <div class="d-flex align-items-center gap-3">
                <a href="login.php" class="text-decoration-none text-dark fw-bold">Login</a>
                <a href="register.php" class="btn btn-custom btn-custom-primary">Register</a>
            </div>
        </div>
    </div>
</nav>

<div class="container py-4">
    <!-- Hero Section -->
    <div class="row align-items-center hero-section px-4">
        <div class="col-lg-6 mb-5 mb-lg-0 text-center text-lg-start">
            <span class="badge bg-primary bg-opacity-10 text-primary px-3 py-2 rounded-pill mb-3 fw-semibold border border-primary-subtle">Modern V2.0</span>
            <h1 class="hero-title">Intelligent Supply Chain Management.</h1>
            <p class="lead mb-4">SupplyNet is a comprehensive solution providing real-time visibility and control over inventory, orders, and deliveries. Unify your workflow today.</p>
            <div class="d-flex justify-content-center justify-content-lg-start gap-3">
                <a href="contact.php" class="btn btn-custom btn-custom-primary btn-lg"><i class="fas fa-rocket me-2"></i>Get Connect</a>
                <a href="about.php" class="btn btn-custom btn-outline-secondary btn-lg"><i class="fas fa-info-circle me-2"></i>About</a>
            </div>
        </div>
        <div class="col-lg-6 text-center">
            <img src="uploads/logo.png" alt="SupplyNet Dashboard" class="img-fluid drop-shadow" style="max-height: 280px; filter: drop-shadow(0 10px 15px rgba(0,0,0,0.1));" onerror="this.src='https://cdn-icons-png.flaticon.com/512/3050/3050431.png'">
        </div>
    </div>

    <!-- Statistics Cards -->
    <div class="row g-4 mb-5">
        <div class="col-xl-3 col-md-6">
            <div class="card card-custom h-100 p-3">
                <div class="card-body">
                    <div class="d-flex justify-content-between align-items-center mb-3">
                        <div>
                            <span class="stat-label text-primary">Products</span>
                            <div class="stat-value text-dark"><?php echo $total_products ?? 0; ?></div>
                        </div>
                        <div class="card-icon-box icon-primary">
                            <i class="fas fa-box-open"></i>
                        </div>
                    </div>
                    <p class="text-muted mb-0 small"><span class="text-success"><i class="fas fa-arrow-up me-1"></i>Active</span> in inventory</p>
                </div>
            </div>
        </div>
        
        <div class="col-xl-3 col-md-6">
            <div class="card card-custom h-100 p-3">
                <div class="card-body">
                    <div class="d-flex justify-content-between align-items-center mb-3">
                        <div>
                            <span class="stat-label text-success">Employees</span>
                            <div class="stat-value text-dark"><?php echo $total_employees ?? 0; ?></div>
                        </div>
                        <div class="card-icon-box icon-success">
                            <i class="fas fa-users-cog"></i>
                        </div>
                    </div>
                    <p class="text-muted mb-0 small"><span class="text-success"><i class="fas fa-check-circle me-1"></i>Registered</span> staff</p>
                </div>
            </div>
        </div>

        <div class="col-xl-3 col-md-6">
            <div class="card card-custom h-100 p-3">
                <div class="card-body">
                    <div class="d-flex justify-content-between align-items-center mb-3">
                        <div>
                            <span class="stat-label text-info">Customers</span>
                            <div class="stat-value text-dark"><?php echo $total_customers ?? 0; ?></div>
                        </div>
                        <div class="card-icon-box icon-info">
                            <i class="fas fa-user-friends"></i>
                        </div>
                    </div>
                    <p class="text-muted mb-0 small"><span class="text-info"><i class="fas fa-chart-line me-1"></i>Growing</span> base</p>
                </div>
            </div>
        </div>

        <div class="col-xl-3 col-md-6">
            <div class="card card-custom h-100 p-3">
                <div class="card-body">
                    <div class="d-flex justify-content-between align-items-center mb-3">
                        <div>
                            <span class="stat-label text-warning">Logistics</span>
                            <div class="stat-value text-dark"><?php echo $total_delivery ?? 0; ?></div>
                        </div>
                        <div class="card-icon-box icon-warning">
                            <i class="fas fa-truck-fast"></i>
                        </div>
                    </div>
                    <p class="text-muted mb-0 small"><span class="text-warning"><i class="fas fa-clock me-1"></i>On-call</span> agents</p>
                </div>
            </div>
        </div>
    </div>

    <!-- Charts Section -->
    <div class="row g-4 mb-5">
        <div class="col-lg-8">
            <div class="card card-custom h-100">
                <div class="card-header bg-white border-0 pt-4 pb-0 px-4">
                    <h5 class="mb-0 fw-bold"><i class="fas fa-chart-bar text-primary me-2"></i>Top 5 Products by Sales</h5>
                </div>
                <div class="card-body p-4">
                    <div class="chart-container-custom">
                        <canvas id="productionChart"></canvas>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-lg-4">
            <div class="card card-custom h-100">
                <div class="card-header bg-white border-0 pt-4 pb-0 px-4">
                    <h5 class="mb-0 fw-bold"><i class="fas fa-chart-pie text-info me-2"></i>User Distribution</h5>
                </div>
                <div class="card-body p-4 d-flex align-items-center justify-content-center">
                    <div class="chart-container-custom" style="height: 250px;">
                        <canvas id="analyticsChart"></canvas>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Footer -->
<?php include 'config/footer.php'; ?>

<!-- Chart.js and Bootstrap JS -->
<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>

<script>
    // Global Chart Settings for modern look
    Chart.defaults.font.family = "'Inter', sans-serif";
    Chart.defaults.color = "#858796";

    // Bar Chart
    const ctx = document.getElementById('productionChart').getContext('2d');
    new Chart(ctx, {
        type: 'bar',
        data: {
            labels: <?php echo json_encode($chart_data['labels']); ?>,
            datasets: [{
                label: 'Sales Output',
                data: <?php echo json_encode($chart_data['data']); ?>,
                backgroundColor: 'rgba(78, 115, 223, 0.8)',
                hoverBackgroundColor: 'rgba(78, 115, 223, 1)',
                borderColor: 'rgba(78, 115, 223, 1)',
                borderWidth: 1,
                borderRadius: 4,
                barPercentage: 0.5
            }]
        },
        options: {
            maintainAspectRatio: false,
            plugins: {
                legend: { display: false }
            },
            scales: {
                y: {
                    beginAtZero: true,
                    grid: { color: 'rgba(0, 0, 0, 0.05)', drawBorder: false },
                    border: { display: false }
                },
                x: {
                    grid: { display: false, drawBorder: false },
                    border: { display: false }
                }
            }
        }
    });

    // Doughnut Chart
    const analyticsCtx = document.getElementById('analyticsChart').getContext('2d');
    new Chart(analyticsCtx, {
        type: 'doughnut',
        data: {
            labels: ['Employees', 'Customers', 'Delivery Persons'],
            datasets: [{
                data: [<?php echo $analytics_data['employees']; ?>, <?php echo $analytics_data['customers']; ?>, <?php echo $analytics_data['delivery']; ?>],
                backgroundColor: [
                    '#1cc88a', // success
                    '#36b9cc', // info
                    '#f6c23e'  // warning
                ],
                hoverBackgroundColor: [
                    '#17a673',
                    '#2c9faf',
                    '#dda20a'
                ],
                borderWidth: 3,
                borderColor: '#ffffff'
            }]
        },
        options: {
            maintainAspectRatio: false,
            cutout: '70%',
            plugins: {
                legend: {
                    position: 'bottom',
                    labels: { padding: 20, usePointStyle: true }
                }
            }
        }
    });
</script>
</body>
</html>