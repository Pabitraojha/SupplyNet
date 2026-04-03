<?php
session_start();
require_once 'config/db.php';

// Redirect if already logged in
if (isset($_SESSION['UserID'])) {
    $role = strtolower($_SESSION['Role']);
    header("Location: " . ($role === 'delivery_person' ? 'Delivery' : $role) . "/index.php");
    exit();
}

$error = '';
$success = '';

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $username = mysqli_real_escape_string($conn, trim($_POST['username'] ?? ''));
    $email = mysqli_real_escape_string($conn, trim($_POST['email'] ?? ''));
    $mobile = mysqli_real_escape_string($conn, trim($_POST['mobile'] ?? ''));
    $address = mysqli_real_escape_string($conn, trim($_POST['address'] ?? ''));
    $role = mysqli_real_escape_string($conn, trim($_POST['role'] ?? 'customer'));
    $password = $_POST['password'] ?? '';
    $confirm_password = $_POST['confirm_password'] ?? '';

    // Basic Input Validation
    if (empty($username) || empty($email) || empty($password) || empty($role)) {
        $error = 'Please fill out all required fields marked with an asterisk (*).';
    } elseif ($password !== $confirm_password) {
        $error = 'Passwords do not match. Please ensure both fields are exactly the same.';
    } elseif (strlen($password) < 6) {
        $error = 'For security reasons, your password must be at least 6 characters long.';
    } else {
        // Validation for existing email
        $chk_email = mysqli_query($conn, "SELECT UserID FROM Users WHERE Email = '$email'");
        if (mysqli_num_rows($chk_email) > 0) {
            $error = 'That email is already securely registered to another account. Please <a href="login.php" style="color:inherit; text-decoration:underline;">login</a> instead.';
        } else {
            // Apply secure hashing explicitly matching login.php capabilities
            $hashed_password = password_hash($password, PASSWORD_DEFAULT);
            
            // Registration SQL Query
            $query = "INSERT INTO Users (UserName, Password, Email, Role, mobile_number, address) 
                      VALUES ('$username', '$hashed_password', '$email', '$role', '$mobile', '$address')";
            
            if (mysqli_query($conn, $query)) {
                $success = 'Account generated successfully! You can now <a href="login.php" class="alert-link">proceed to login</a>.';
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
    <title>Register - SupplyNet</title>
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
            display: flex;
            align-items: center;
            justify-content: center;
            min-height: 100vh;
            padding: 2rem 0; /* Ensures scroll padding on smaller screens */
        }

        .login-card {
            border: none;
            border-radius: 1rem;
            box-shadow: var(--shadow);
            overflow: hidden;
            width: 100%;
            max-width: 1000px;
            background: #fff;
        }

        .bg-register-image {
            background-image: url('https://images.unsplash.com/photo-1586528116311-ad8dd3c8310d?ixlib=rb-4.0.3&auto=format&fit=crop&w=800&q=80');
            background-position: center;
            background-size: cover;
        }

        .login-form-container {
            padding: 3rem;
        }

        @media (min-width: 992px) {
            .login-form-container {
                padding: 4rem;
            }
        }

        .form-control, .form-select {
            border-radius: 50rem;
            padding: 0.8rem 1.5rem;
            font-size: 0.9rem;
            border: 1px solid #d1d3e2;
        }
        
        /* Adjust textarea explicitly since border-radius 50rem is too large for multiline */
        textarea.form-control {
            border-radius: 1rem;
        }

        .form-control:focus, .form-select:focus {
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
                    <div class="col-lg-5 d-none d-lg-block bg-register-image"></div>
                    
                    <!-- Form Half -->
                    <div class="col-lg-7">
                        <div class="login-form-container">
                            <div class="text-center">
                                <a href="index.php" class="brand-logo"><i class="fas fa-cubes me-2"></i>SupplyNet</a>
                                <h4 class="text-dark fw-bold mb-4">Create an Account!</h4>
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

                            <form class="user" action="register.php" method="POST">
                                <div class="row g-3">
                                    <div class="col-sm-6 mb-3">
                                        <input type="text" class="form-control" name="username" placeholder="Full Name *" required value="<?php echo htmlspecialchars($_POST['username'] ?? ''); ?>">
                                    </div>
                                    <div class="col-sm-6 mb-3">
                                        <input type="text" class="form-control" name="mobile" placeholder="Mobile Number" value="<?php echo htmlspecialchars($_POST['mobile'] ?? ''); ?>">
                                    </div>
                                    <div class="col-12 mb-3">
                                        <input type="email" class="form-control" name="email" placeholder="Email Address *" required value="<?php echo htmlspecialchars($_POST['email'] ?? ''); ?>">
                                    </div>
                                    <div class="col-12 mb-3">
                                        <select class="form-select" name="role" required>
                                            <option value="" disabled <?php echo empty($_POST['role']) ? 'selected' : ''; ?>>Select System Role *</option>
                                            <option value="customer" <?php echo (($_POST['role'] ?? '') == 'customer') ? 'selected' : ''; ?>>Customer (Retailer)</option>
                                            <option value="salesman" <?php echo (($_POST['role'] ?? '') == 'salesman') ? 'selected' : ''; ?>>Salesman</option>
                                            <option value="delivery_person" <?php echo (($_POST['role'] ?? '') == 'delivery_person') ? 'selected' : ''; ?>>Delivery Agent</option>
                                            <option value="employee" <?php echo (($_POST['role'] ?? '') == 'employee') ? 'selected' : ''; ?>>Internal Employee</option>
                                        </select>
                                    </div>
                                    <div class="col-12 mb-3">
                                        <textarea class="form-control" name="address" rows="2" placeholder="Full Address"><?php echo htmlspecialchars($_POST['address'] ?? ''); ?></textarea>
                                    </div>
                                    <div class="col-sm-6 mb-4">
                                        <input type="password" class="form-control" name="password" placeholder="Password *" required minlength="6">
                                    </div>
                                    <div class="col-sm-6 mb-4">
                                        <input type="password" class="form-control" name="confirm_password" placeholder="Repeat Password *" required minlength="6">
                                    </div>
                                </div>
                                <button type="submit" class="btn btn-custom w-100">
                                    Register Account
                                </button>
                            </form>
                            
                            <hr class="my-4">
                            
                            <div class="text-center mb-2">
                                <a class="small text-primary-custom" href="forgot_password.php">Forgot Password?</a>
                            </div>
                            <div class="text-center">
                                <span class="small text-muted">Already have an account? </span><a class="small text-primary-custom fw-bold" href="login.php">Login!</a>
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
