<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>About Us - SupplyNet</title>
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
        .about-hero {
            padding: 6rem 0;
            background: linear-gradient(135deg, rgba(78,115,223,0.05) 0%, rgba(255,255,255,0) 100%);
            border-bottom: 1px solid rgba(0,0,0,0.05);
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
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
        }
        
        .icon-primary { background: rgba(78, 115, 223, 0.1); color: var(--primary-color); }
        .icon-success { background: rgba(28, 200, 138, 0.1); color: var(--success-color); }
        .icon-info { background: rgba(54, 185, 204, 0.1); color: var(--info-color); }
        

        
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
        <a class="navbar-brand" href="index.php"><i class="fas fa-cubes me-2"></i>SupplyNet</a>
        <button class="navbar-toggler border-0" type="button" data-bs-toggle="collapse" data-bs-target="#navbarNav">
            <span class="navbar-toggler-icon"></span>
        </button>
        <div class="collapse navbar-collapse" id="navbarNav">
            <ul class="navbar-nav mx-auto mb-2 mb-lg-0">
                <li class="nav-item">
                    <a class="nav-link" href="index.php">Home</a>
                </li>
                <li class="nav-item">
                    <a class="nav-link" href="features.php">Features</a>
                </li>
                <li class="nav-item">
                    <a class="nav-link" href="contact.php">Contact</a>
                </li>
                <li class="nav-item">
                    <a class="nav-link active" href="about.php">About</a>
                </li>
            </ul>
            <div class="d-flex align-items-center gap-3">
                <a href="login.php" class="text-decoration-none text-dark fw-bold">Login</a>
                <a href="register.php" class="btn btn-custom btn-custom-primary">Register</a>
            </div>
        </div>
    </div>
</nav>

<!-- About Hero Section -->
<div class="about-hero text-center mb-5">
    <div class="container">
        <div class="row">
            <div class="col-lg-8 mx-auto">
                <span class="badge bg-primary bg-opacity-10 text-primary px-3 py-2 rounded-pill mb-3 fw-semibold border border-primary-subtle">Re-imagining Logistics</span>
                <h1 class="display-4 fw-bold text-dark mb-4">About <span class="text-primary">SupplyNet</span></h1>
                <p class="lead text-muted fs-4">A centralized web-based supply chain management system bridging the connectivity gap between employees, retailers, and logistical personnel.</p>
            </div>
        </div>
    </div>
</div>

<div class="container pb-5">
    <!-- Main Description -->
    <div class="row align-items-center mb-5">
        <div class="col-lg-6 mb-4 mb-lg-0">
            <!-- Professional imagery from Unsplash for supply chain / warehouse context -->
            <img src="https://images.unsplash.com/photo-1586528116311-ad8dd3c8310d?ixlib=rb-4.0.3&auto=format&fit=crop&w=800&q=80" alt="Supply Chain Operations" class="img-fluid rounded-4 shadow-lg" style="object-fit: cover; height: 100%; min-height: 400px; width: 100%;">
        </div>
        <div class="col-lg-6 px-lg-5">
            <h3 class="fw-bold text-dark mb-4 border-bottom pb-2">Digital Transformation in Logistics</h3>
            <p class="text-secondary mb-3" style="line-height: 1.8; font-size: 1.05rem;">
                The platform is purposefully designed to ensure smooth product flow, hyper-efficient order processing, and tracking precision across every stage of the supply chain. SupplyNet simplifies the process by allowing sales representatives to manage and fulfill orders directly from retailers. Our dispatch algorithms then intelligently assign these to delivery personnel.
            </p>
            <p class="text-secondary" style="line-height: 1.8; font-size: 1.05rem;">
                Overall, SupplyNet was built to provide a reliable, universally scalable, and efficient solution that diminishes manual dependency, actively minimizes errors, and drastically enhances inter-departmental communication.
            </p>
            <div class="mt-4 pt-3 border-top">
                <div class="d-flex align-items-center">
                    <div class="me-3">
                        <i class="fas fa-check-circle text-primary fs-4"></i>
                    </div>
                    <div><span class="fw-bold text-dark">Reducing Manual Workflows</span> by 60%</div>
                </div>
            </div>
        </div>
    </div>

    <!-- Core Pillars Grid -->
    <div class="row g-4 mb-3">
        <!-- Feature 1 -->
        <div class="col-md-4">
            <div class="card card-custom h-100 p-4">
                <div class="card-icon-box icon-primary mb-4" style="width: 4rem; height: 4rem; font-size: 1.75rem;">
                    <i class="fas fa-user-shield"></i>
                </div>
                <h5 class="fw-bold mb-3">Unified Role-Based Access</h5>
                <p class="text-muted small mb-0" style="line-height: 1.6;">
                    This system securely integrates multiple user roles including Admin, Salesman, Retailer, Delivery Person, and Pre-Sales Representatives into a single unified workspace. Each user is provided explicit, role-based access ensuring airtight security and organized workflow structuring.
                </p>
            </div>
        </div>
        <!-- Feature 2 -->
        <div class="col-md-4">
            <div class="card card-custom h-100 p-4">
                <div class="card-icon-box icon-success mb-4" style="width: 4rem; height: 4rem; font-size: 1.75rem;">
                    <i class="fas fa-shipping-fast"></i>
                </div>
                <h5 class="fw-bold mb-3">Real-time Order & Delivery</h5>
                <p class="text-muted small mb-0" style="line-height: 1.6;">
                    Continuous synchronization means that delivery statuses are logged and updated live. This persistent data flow cultivates total operational transparency and cultivates flawless coordination between stakeholders from warehouse extraction to door delivery.
                </p>
            </div>
        </div>
        <!-- Feature 3 -->
        <div class="col-md-4">
            <div class="card card-custom h-100 p-4">
                <div class="card-icon-box icon-info mb-4" style="width: 4rem; height: 4rem; font-size: 1.75rem;">
                    <i class="fas fa-chart-pie"></i>
                </div>
                <h5 class="fw-bold mb-3">Data-Driven Administration</h5>
                <p class="text-muted small mb-0" style="line-height: 1.6;">
                    Provides access to an all-out administrative dashboard that permits monitoring of active product inventory, individual employee KPIs, and sales analytics, thereby steering entire organizations toward informed, strategic decision-making.
                </p>
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
