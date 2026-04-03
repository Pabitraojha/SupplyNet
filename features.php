<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Features & Mission - SupplyNet</title>
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
            --shadow: 0 0.15rem 1.75rem 0 rgba(58, 59, 69, 0.15);
            --shadow-hover: 0 0.5rem 2rem 0 rgba(58, 59, 69, 0.2);
        }
        
        body {
            font-family: 'Inter', sans-serif;
            background-color: var(--primary-bg);
            color: var(--secondary-color);
            overflow-x: hidden;
        }

        h1, h2, h3, h4, h5, h6 { color: var(--text-dark); font-weight: 700; }

        .navbar-custom { background: rgba(255, 255, 255, 0.9) !important; backdrop-filter: blur(10px); border-bottom: 1px solid rgba(0,0,0,0.05); transition: all 0.3s ease; }
        .navbar-custom .navbar-brand { font-weight: 800; color: var(--primary-color) !important; font-size: 1.5rem; }
        .navbar-custom .nav-link { color: var(--text-dark) !important; font-weight: 500; margin: 0 0.5rem; transition: color 0.3s ease; }
        .navbar-custom .nav-link:hover, .navbar-custom .nav-link.active { color: var(--primary-color) !important; }

        .hero-section { padding: 6rem 0; background: linear-gradient(135deg, rgba(78,115,223,0.05) 0%, rgba(255,255,255,0) 100%); border-bottom: 1px solid rgba(0,0,0,0.05); }

        .card-custom { border: none; border-radius: 1rem; box-shadow: var(--shadow); transition: transform 0.3s ease, box-shadow 0.3s ease; background: var(--card-bg); overflow: hidden; }
        .card-custom:hover { transform: translateY(-5px); box-shadow: var(--shadow-hover); }

        .feature-icon { width: 3.5rem; height: 3.5rem; border-radius: 50%; display: flex; align-items: center; justify-content: center; font-size: 1.5rem; background: rgba(78, 115, 223, 0.1); color: var(--primary-color); margin-bottom: 1.2rem; }
        .icon-success { background: rgba(28, 200, 138, 0.1); color: var(--success-color); }
        .icon-info { background: rgba(54, 185, 204, 0.1); color: var(--info-color); }
        .icon-warning { background: rgba(246, 194, 62, 0.1); color: var(--warning-color); }


        
        .btn-custom { border-radius: 50rem; padding: 0.6rem 1.5rem; font-weight: 600; transition: all 0.3s; }
        .btn-custom-primary { background: var(--primary-color); color: white; border: none; box-shadow: 0 4px 10px rgba(78, 115, 223, 0.3); }
        .btn-custom-primary:hover { background: #2e59d9; color: white; transform: translateY(-2px); box-shadow: 0 6px 15px rgba(78, 115, 223, 0.4); }
        
        /* Mission/Vision Box Gradient */
        .mv-box {
            background: linear-gradient(135deg, #4e73df 0%, #224abe 100%);
            color: #fff;
        }
        .mv-box .feature-icon {
            background: rgba(255,255,255,0.2) !important;
            color: #fff !important;
        }
        .mv-box h4 { color: #fff; }
        .mv-box p { color: rgba(255,255,255,0.9) !important; }
    </style>
</head>
<body>

<!-- Navbar -->
<nav class="navbar navbar-expand-lg navbar-light navbar-custom sticky-top shadow-sm py-3">
    <div class="container">
        <a class="navbar-brand" href="index.php"><i class="fas fa-cubes me-2"></i>SupplyNet</a>
        <button class="navbar-toggler border-0" type="button" data-bs-toggle="collapse" data-bs-target="#navbarNav">
            <span class="navbar-toggler-icon"></span>
        </button>
        <div class="collapse navbar-collapse" id="navbarNav">
            <ul class="navbar-nav mx-auto mb-2 mb-lg-0">
                <li class="nav-item"><a class="nav-link" href="index.php">Home</a></li>
                <li class="nav-item"><a class="nav-link active" href="features.php">Features</a></li>
                <li class="nav-item"><a class="nav-link" href="contact.php">Contact</a></li>
                <li class="nav-item"><a class="nav-link" href="about.php">About</a></li>
            </ul>
            <div class="d-flex align-items-center gap-3">
                <a href="login.php" class="text-decoration-none text-dark fw-bold">Login</a>
                <a href="register.php" class="btn btn-custom btn-custom-primary">Register</a>
            </div>
        </div>
    </div>
</nav>

<!-- Hero Section -->
<div class="hero-section text-center mb-5">
    <div class="container">
        <div class="row">
            <div class="col-lg-8 mx-auto">
                <span class="badge bg-primary bg-opacity-10 text-primary px-3 py-2 rounded-pill mb-3 fw-semibold border border-primary-subtle">Core Values & Capabilities</span>
                <h1 class="display-4 fw-bold text-dark mb-4">Mission, Vision <span class="text-primary">& Features</span></h1>
                <p class="lead text-muted fs-4">Discover the driving force behind SupplyNet and the robust capabilities that make true digital transformation in corporate supply chains possible.</p>
            </div>
        </div>
    </div>
</div>

<div class="container pb-5">
    <!-- Mission and Vision -->
    <div class="row g-4 mb-5 pb-5 border-bottom">
        <div class="col-lg-6">
            <div class="card card-custom h-100 p-5 mv-box">
                <div class="feature-icon">
                    <i class="fas fa-bullseye"></i>
                </div>
                <h4 class="fw-bold mb-3">Our Mission</h4>
                <p class="mb-0 fs-5" style="line-height: 1.6;">
                    To architect a universally accessible, centralized web-based supply chain ecosystem that securely bridges the connectivity gap between employees, retailers, and logistical personnel, streamlining global operations.
                </p>
            </div>
        </div>
        <div class="col-lg-6">
            <div class="card card-custom h-100 p-5 border-0 bg-white">
                <div class="feature-icon icon-success">
                    <i class="fas fa-eye"></i>
                </div>
                <h4 class="fw-bold mb-3">Our Vision</h4>
                <p class="text-muted mb-0 fs-5" style="line-height: 1.6;">
                    To securely serve as the global standard for automated inventory management, ultimately eradicating operational inefficiencies through deeply integrated data analytics and 100% real-time logistics tracking.
                </p>
            </div>
        </div>
    </div>

    <!-- Features -->
    <div class="text-center mb-5">
        <h2 class="fw-bold mb-3">Features of SupplyNet</h2>
        <p class="text-muted">A comprehensive, modular toolkit designed to establish total supply chain perfection.</p>
    </div>

    <div class="row g-4 mb-5">
        <!-- 1 -->
        <div class="col-md-4">
            <div class="card card-custom h-100 p-4">
                <div class="feature-icon"><i class="fas fa-user-lock"></i></div>
                <h5 class="fw-bold mb-3">User Authentication & RBAC</h5>
                <p class="text-muted small mb-0">Secure login system with strict role-based access control for Admins, Salesmen, Retailers, Delivery Agents, and Pre-Sales Representatives.</p>
            </div>
        </div>
        <!-- 2 -->
        <div class="col-md-4">
            <div class="card card-custom h-100 p-4">
                <div class="feature-icon icon-info"><i class="fas fa-shopping-cart"></i></div>
                <h5 class="fw-bold mb-3">Order Management System</h5>
                <p class="text-muted small mb-0">Empowers your salesmen to accurately create, update, and manage vast magnitudes of product orders flawlessly for connected retailers.</p>
            </div>
        </div>
        <!-- 3 -->
        <div class="col-md-4">
            <div class="card card-custom h-100 p-4">
                <div class="feature-icon icon-success"><i class="fas fa-map-marked-alt"></i></div>
                <h5 class="fw-bold mb-3">Real-Time Order Tracking</h5>
                <p class="text-muted small mb-0">Allows users to consistently track the current condition and stage of distinct orders (pending, processed, shipped, delivered) completely in live time.</p>
            </div>
        </div>
        <!-- 4 -->
        <div class="col-md-4">
            <div class="card card-custom h-100 p-4">
                <div class="feature-icon icon-warning"><i class="fas fa-shipping-fast"></i></div>
                <h5 class="fw-bold mb-3">Delivery Management</h5>
                <p class="text-muted small mb-0">Intelligently assigns direct delivery tasks to delivery personnel, providing agents a portal to dynamically update the live delivery cycle statuses.</p>
            </div>
        </div>
        <!-- 5 -->
        <div class="col-md-4">
            <div class="card card-custom h-100 p-4">
                <div class="feature-icon"><i class="fas fa-boxes"></i></div>
                <h5 class="fw-bold mb-3">Inventory Management</h5>
                <p class="text-muted small mb-0">Actively maintains precise stock details and updates automated thresholds, severely diminishing instances of shortages or crippling overstock.</p>
            </div>
        </div>
        <!-- 6 -->
        <div class="col-md-4">
            <div class="card card-custom h-100 p-4">
                <div class="feature-icon icon-success"><i class="fas fa-chart-line"></i></div>
                <h5 class="fw-bold mb-3">Sales Analytics & Reporting</h5>
                <p class="text-muted small mb-0">Generates comprehensive insights evaluating complex sales performance metrics, product demand indices, and organizational staff efficiency over time.</p>
            </div>
        </div>
        <!-- 7 -->
        <div class="col-md-4">
            <div class="card card-custom h-100 p-4">
                <div class="feature-icon icon-info"><i class="fas fa-tachometer-alt"></i></div>
                <h5 class="fw-bold mb-3">Admin Dashboard</h5>
                <p class="text-muted small mb-0">Serves as a high-altitude control tower allowing operations leaders to micromanage accounts, monitor live activity, and foresee supply chain bottlenecks.</p>
            </div>
        </div>
        <!-- 8 -->
        <div class="col-md-4">
            <div class="card card-custom h-100 p-4">
                <div class="feature-icon"><i class="fas fa-store"></i></div>
                <h5 class="fw-bold mb-3">Customer (Retailer) CRM</h5>
                <p class="text-muted small mb-0">Securely stores extensive retailer credentials, historical order profiles, and long-term communication ledgers for future relationship management.</p>
            </div>
        </div>
        <!-- 9 -->
        <div class="col-md-4">
            <div class="card card-custom h-100 p-4">
                <div class="feature-icon icon-warning"><i class="fas fa-bell"></i></div>
                <h5 class="fw-bold mb-3">Notification System</h5>
                <p class="text-muted small mb-0">Automatically pushes live broadcast alerts and updates directly to specific users relating to stage transitions, incoming deliveries, or platform activities.</p>
            </div>
        </div>
        <!-- 10 -->
        <div class="col-md-4">
            <div class="card card-custom h-100 p-4">
                <div class="feature-icon icon-success"><i class="fas fa-headset"></i></div>
                <h5 class="fw-bold mb-3">Pre-Sales Support Tools</h5>
                <p class="text-muted small mb-0">Dedicated modules directly helping Pre-Sales analysts inspect live logistical data and effectively design accurate product promotions for future demand.</p>
            </div>
        </div>
        <!-- 11 -->
        <div class="col-md-4">
            <div class="card card-custom h-100 p-4">
                <div class="feature-icon"><i class="fas fa-database"></i></div>
                <h5 class="fw-bold mb-3">Total Data Centralization</h5>
                <p class="text-muted small mb-0">Guarantees all disparate tracking points are synchronized into a single unified cloud system, ensuring strict global consistency and immediate data accessibility.</p>
            </div>
        </div>
        <!-- 12 -->
        <div class="col-md-4">
            <div class="card card-custom h-100 p-4">
                <div class="feature-icon icon-info"><i class="fas fa-server"></i></div>
                <h5 class="fw-bold mb-3">Scalable Architecture</h5>
                <p class="text-muted small mb-0">Developed using state-of-the-art technological layers built strictly to infinitely handle surging simultaneous users, active queries, and payment transactions.</p>
            </div>
        </div>
        <!-- 13 -->
        <div class="col-md-4">
            <div class="card card-custom h-100 p-4">
                <div class="feature-icon icon-success"><i class="fas fa-laptop"></i></div>
                <h5 class="fw-bold mb-3">User-Friendly Interface</h5>
                <p class="text-muted small mb-0">A radically engineered, sleek aesthetic featuring inherently intuitive navigation flows optimized efficiently for high-volume daily input.</p>
            </div>
        </div>
        <!-- 14 -->
        <div class="col-md-4">
            <div class="card card-custom h-100 p-4">
                <div class="feature-icon icon-warning"><i class="fas fa-shield-alt"></i></div>
                <h5 class="fw-bold mb-3">Secure Data Handling</h5>
                <p class="text-muted small mb-0">Deploys rigorous enterprise security algorithms to deeply obfuscate sensitive business secrets and secure private user identities comprehensively.</p>
            </div>
        </div>
        <!-- 15 -->
        <div class="col-md-4">
            <div class="card card-custom h-100 p-4">
                <div class="feature-icon"><i class="fas fa-bolt"></i></div>
                <h5 class="fw-bold mb-3">Performance Optimization</h5>
                <p class="text-muted small mb-0">Architecturally minimizes sluggish database loads to severely reduce manual human delays and increase digital network efficacy.</p>
            </div>
        </div>
    </div>
</div>

<!-- Footer -->
<?php include 'config/footer.php'; ?>

<!-- Bootstrap JS -->
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>

</body>
</html>
