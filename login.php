<?php
session_start();
require_once 'config/db.php';

// Redirect if already logged in
if (isset($_SESSION['UserID'])) {
    $role = strtolower($_SESSION['Role']);
    if ($role === 'delivery_person') {
        header("Location: Delivery/index.php");
    } else {
        header("Location: {$role}/index.php");
    }
    exit();
}

$error = '';

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $email = mysqli_real_escape_string($conn, trim($_POST['email'] ?? ''));
    $password = trim($_POST['password'] ?? '');

    if (empty($email) || empty($password)) {
        $error = 'Please enter both email and password.';
    } else {
        // Query user matching the email, ensuring they are active, approved, and not deleted
        $query = "SELECT UserID, UserName, Password, Role FROM Users WHERE Email = '$email' AND IsActive = 1 AND markasdeleted = 0 AND approval_status = 'approved'";
        $result = mysqli_query($conn, $query);

        if ($result && mysqli_num_rows($result) > 0) {
            $user = mysqli_fetch_assoc($result);
            
            // Check password (supports both plain text for legacy sample data and password_hash for secure deployments)
            if ($password === $user['Password'] || password_verify($password, $user['Password'])) {
                
                // Initialize Session Variables
                $_SESSION['UserID'] = $user['UserID'];
                $_SESSION['UserName'] = $user['UserName'];
                $_SESSION['Role'] = $user['Role'];
                
                // Route to appropriate dashboard based on Role
                switch (strtolower($user['Role'])) {
                    case 'admin':
                        header("Location: admin/index.php");
                        break;
                    case 'employee':
                        header("Location: employee/index.php");
                        break;
                    case 'customer':
                        header("Location: customer/index.php");
                        break;
                    case 'salesman':
                        header("Location: salesman/index.php");
                        break;
                    case 'delivery_person':
                        header("Location: Delivery/index.php");
                        break;
                    default:
                        header("Location: index.php");
                }
                exit();
            } else {
                $error = 'Invalid email or password.';
            }
        } else {
            $error = 'Invalid email or password or account is deactivated.';
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login - SupplyNet</title>
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
            --primary-color: #4e73df;
            --secondary-color: #858796;
            --text-dark: #3a3b45;
            --shadow: 0 0.15rem 1.75rem 0 rgba(58, 59, 69, 0.15);
        }
        
        body {
            font-family: 'Inter', sans-serif;
            background-color: var(--primary-bg);
            color: var(--secondary-color);
            height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
        }

        .login-card {
            border: none;
            border-radius: 1rem;
            box-shadow: var(--shadow);
            overflow: hidden;
            width: 100%;
            max-width: 900px;
            background: #fff;
        }

        .login-form-container {
            padding: 4rem 3rem;
        }

        .form-control {
            border-radius: 50rem;
            padding: 0.8rem 1.5rem;
            font-size: 0.9rem;
            border: 1px solid #d1d3e2;
        }

        .form-control:focus {
            border-color: var(--primary-color);
            box-shadow: 0 0 0 0.25rem rgba(78, 115, 223, 0.25);
        }

        .btn-custom {
            border-radius: 50rem;
            padding: 0.8rem 1.5rem;
            font-size: 0.9rem;
            font-weight: 600;
            background: var(--primary-color);
            color: white;
            border: none;
            box-shadow: 0 4px 10px rgba(78, 115, 223, 0.3);
            transition: all 0.3s;
        }

        .btn-custom:hover {
            background: #2e59d9;
            color: white;
            transform: translateY(-2px);
            box-shadow: 0 6px 15px rgba(78, 115, 223, 0.4);
        }

        .text-primary-custom {
            color: var(--primary-color);
            text-decoration: none;
        }

        .text-primary-custom:hover {
            color: #2e59d9;
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
                    <div class="col-lg-6 d-none d-lg-flex align-items-center justify-content-center p-5" style="background-color: var(--primary-bg);">
                        <img src="uploads/logo.png" alt="SupplyNet Logo" class="img-fluid drop-shadow" style="max-height: 250px; filter: drop-shadow(0 10px 15px rgba(0,0,0,0.1));" onerror="this.src='https://cdn-icons-png.flaticon.com/512/3050/3050431.png'">
                    </div>
                    
                    <!-- Form Half -->
                    <div class="col-lg-6">
                        <div class="login-form-container">
                            <div class="text-center">
                                <a href="index.php" class="brand-logo"><i class="fas fa-cubes me-2"></i>SupplyNet</a>
                                <h4 class="text-dark fw-bold mb-4">Welcome Back!</h4>
                            </div>

                            <?php if (!empty($error)): ?>
                                <div class="alert alert-danger alert-dismissible fade show" role="alert">
                                    <i class="fas fa-exclamation-circle me-2"></i><?php echo $error; ?>
                                    <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                                </div>
                            <?php endif; ?>

                            <form class="user" action="login.php" method="POST">
                                <div class="mb-3">
                                    <input type="email" class="form-control" name="email" id="email" placeholder="Enter Email Address..." required autofocus>
                                </div>
                                <div class="mb-4">
                                    <input type="password" class="form-control" name="password" id="password" placeholder="Password" required>
                                </div>
                                <div class="mb-4">
                                    <div class="form-check">
                                        <input class="form-check-input" type="checkbox" id="customCheck">
                                        <label class="form-check-label text-muted small" for="customCheck">
                                            Remember Me
                                        </label>
                                    </div>
                                </div>
                                <button type="submit" class="btn btn-custom w-100">
                                    Login
                                </button>
                            </form>
                            <hr class="my-4">
                            <div class="text-center mb-2">
                                <a class="small text-primary-custom" href="forgot_password.php">Forgot Password?</a>
                            </div>
                            <div class="text-center">
                                <a class="small text-primary-custom" href="register.php">Create an Account!</a>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Bootstrap JS -->
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
