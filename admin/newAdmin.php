<?php
session_start();
// Use relative path to link mapping to the core db configuration mapped from localhost folder
require_once '../config/db.php';

// Prevent users from accessing if they are already authenticated successfully
if (isset($_SESSION['UserID'])) {
    $role = strtolower($_SESSION['Role']);
    header("Location: ../" . ($role === 'delivery_person' ? 'Delivery' : $role) . "/index.php");
    exit();
}

$error = '';
$success = '';

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $username = mysqli_real_escape_string($conn, trim($_POST['username'] ?? ''));
    $email = mysqli_real_escape_string($conn, trim($_POST['email'] ?? ''));
    $mobile = mysqli_real_escape_string($conn, trim($_POST['mobile'] ?? ''));
    $address = mysqli_real_escape_string($conn, trim($_POST['address'] ?? ''));
    
    // Explicitly enforce hardcoded top-level admin security layer context
    $role = 'admin'; 
    
    $password = $_POST['password'] ?? '';
    $confirm_password = $_POST['confirm_password'] ?? '';

    // Standard Form Layer Checks
    if (empty($username) || empty($email) || empty($password)) {
        $error = 'Please fill out all required fields marked with an asterisk (*).';
    } elseif ($password !== $confirm_password) {
        $error = 'Passwords do not match. Please ensure both fields are exactly the same.';
    } elseif (strlen($password) < 6) {
        $error = 'For security reasons, your password must be at least 6 characters long.';
    } else {
        // Prevent registering a duplicate email inside Users
        $chk_email = mysqli_query($conn, "SELECT UserID FROM Users WHERE Email = '$email'");
        if (mysqli_num_rows($chk_email) > 0) {
            $error = 'An account with that email already exists. Please <a href="../login.php" style="color:inherit; text-decoration:underline;">login</a> securely instead.';
        } else {
            // Provide exact same hashing mechanism for compatibility explicitly securely
            $hashed_password = password_hash($password, PASSWORD_DEFAULT);
            
            // Commit to Database
            $query = "INSERT INTO Users (UserName, Password, Email, Role, mobile_number, address) 
                      VALUES ('$username', '$hashed_password', '$email', '$role', '$mobile', '$address')";
            
            if (mysqli_query($conn, $query)) {
                $success = 'System Administrator account successfully created! You may now <a href="../login.php" class="alert-link">proceed to login</a>.';
            } else {
                $error = 'Database Configuration Error: ' . mysqli_error($conn);
            }
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Registration - SupplyNet</title>
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
            --primary-color: #36b9cc; /* distinct info color specific visually for admin */
            --secondary-color: #858796;
            --text-dark: #3a3b45;
            --shadow: 0 0.15rem 1.75rem 0 rgba(58, 59, 69, 0.15);
        }
        
        body {
            font-family: 'Inter', sans-serif;
            background-color: var(--primary-bg);
            color: var(--secondary-color);
            display: flex;
            align-items: center;
            justify-content: center;
            min-height: 100vh;
            padding: 2rem 0; 
        }

        .login-card {
            border: none;
            border-radius: 1rem;
            box-shadow: var(--shadow);
            overflow: hidden;
            width: 100%;
            max-width: 1000px;
            background: #fff;
            border-top: 5px solid var(--primary-color);
        }

        .bg-admin-image {
            background-image: url('https://images.unsplash.com/photo-1551288049-bebda4e38f71?ixlib=rb-4.0.3&auto=format&fit=crop&w=800&q=80');
            background-position: center;
            background-size: cover;
            position: relative;
        }

        .bg-admin-image::after {
            content: '';
            position: absolute;
            top: 0; left: 0; right: 0; bottom: 0;
            background: rgba(54, 185, 204, 0.2);
        }

        .login-form-container {
            padding: 3rem;
        }

        @media (min-width: 992px) {
            .login-form-container {
                padding: 4rem;
            }
        }

        .form-control {
            border-radius: 50rem;
            padding: 0.8rem 1.5rem;
            font-size: 0.9rem;
            border: 1px solid #d1d3e2;
        }
        
        textarea.form-control {
            border-radius: 1rem;
        }

        .form-control:focus {
            border-color: var(--primary-color);
            box-shadow: 0 0 0 0.25rem rgba(54, 185, 204, 0.25);
        }

        .btn-custom {
            border-radius: 50rem;
            padding: 0.8rem 1.5rem;
            font-size: 0.9rem;
            font-weight: 600;
            background: var(--primary-color);
            color: white;
            border: none;
            box-shadow: 0 4px 10px rgba(54, 185, 204, 0.3);
            transition: all 0.3s;
        }

        .btn-custom:hover {
            background: #2a96a5;
            color: white;
            transform: translateY(-2px);
            box-shadow: 0 6px 15px rgba(54, 185, 204, 0.4);
        }

        .text-primary-custom {
            color: var(--primary-color);
            text-decoration: none;
        }

        .text-primary-custom:hover {
            color: #2a96a5;
            text-decoration: underline;
        }
        
        .brand-logo {
            font-weight: 800;
            color: var(--primary-color);
            font-size: 1.5rem;
            text-decoration: none;
            display: inline-block;
            margin-bottom: 2rem;
        }
    </style>
</head>
<body>

<div class="container">
    <div class="row justify-content-center">
        <div class="col-xl-10 col-lg-12 col-md-9">
            <div class="card login-card">
                <div class="row g-0">
                    <!-- Image Half -->
                    <div class="col-lg-5 d-none d-lg-block bg-admin-image"></div>
                    
                    <!-- Form Half -->
                    <div class="col-lg-7">
                        <div class="login-form-container">
                            <div class="text-center">
                                <a href="../index.php" class="brand-logo"><i class="fas fa-cubes me-2"></i>SupplyNet <span class="badge bg-danger rounded-pill fs-6 ms-1 align-top">Admin Setup</span></a>
                                <h4 class="text-dark fw-bold mb-4">Master Configuration</h4>
                                <p class="small text-muted mb-4 d-none d-xl-block">Only authorized systems technicians should complete this step to provision a top-level network account.</p>
                            </div>

                            <?php if (!empty($error)): ?>
                                <div class="alert alert-danger alert-dismissible fade show" role="alert">
                                    <i class="fas fa-exclamation-circle me-2"></i><?php echo $error; ?>
                                    <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                                </div>
                            <?php endif; ?>
                            
                            <?php if (!empty($success)): ?>
                                <div class="alert alert-success alert-dismissible fade show" role="alert">
                                    <i class="fas fa-check-circle me-2"></i><?php echo $success; ?>
                                    <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                                </div>
                            <?php endif; ?>

                            <form class="user" action="register_admin.php" method="POST">
                                <div class="row g-3">
                                    <div class="col-12 mb-3">
                                        <!-- Hardcoded non-editable visual representation pointing to Admin mapping -->
                                        <div class="form-control bg-light text-muted d-flex align-items-center">
                                            <i class="fas fa-shield-alt text-danger me-2"></i> System Role: <span class="fw-bold text-dark ms-2">Administrator</span>
                                            <span class="badge bg-success ms-auto">Auto-Assigned</span>
                                        </div>
                                    </div>
                                    
                                    <div class="col-sm-6 mb-3">
                                        <input type="text" class="form-control" name="username" placeholder="Admin Full Name *" required value="<?php echo htmlspecialchars($_POST['username'] ?? ''); ?>">
                                    </div>
                                    <div class="col-sm-6 mb-3">
                                        <input type="text" class="form-control" name="mobile" placeholder="Mobile Number" value="<?php echo htmlspecialchars($_POST['mobile'] ?? ''); ?>">
                                    </div>
                                    <div class="col-12 mb-3">
                                        <input type="email" class="form-control" name="email" placeholder="Admin Email Address *" required value="<?php echo htmlspecialchars($_POST['email'] ?? ''); ?>">
                                    </div>
                                    <div class="col-12 mb-3">
                                        <textarea class="form-control" name="address" rows="2" placeholder="Full Address / Branch Location"><?php echo htmlspecialchars($_POST['address'] ?? ''); ?></textarea>
                                    </div>
                                    <div class="col-sm-6 mb-4">
                                        <input type="password" class="form-control" name="password" placeholder="Secure Password *" required minlength="6">
                                    </div>
                                    <div class="col-sm-6 mb-4">
                                        <input type="password" class="form-control" name="confirm_password" placeholder="Repeat Password *" required minlength="6">
                                    </div>
                                </div>
                                <button type="submit" class="btn btn-custom w-100">
                                    <i class="fas fa-user-shield me-2"></i> Initialize Administrator
                                </button>
                            </form>
                            
                            <hr class="my-4">
                            
                            <div class="text-center">
                                <a class="small text-primary-custom" href="../login.php">Finished? Return to Core Login Context.</a>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

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
