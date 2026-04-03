<?php
session_start();
require_once 'config/db.php';

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

// Require PHPMailer manually
require 'PHPMailer/src/Exception.php';
require 'PHPMailer/src/PHPMailer.php';
require 'PHPMailer/src/SMTP.php';

// Ensure the mapping table exists without destroying any current schema
mysqli_query($conn, "CREATE TABLE IF NOT EXISTS PasswordResets (
    Email VARCHAR(255) PRIMARY KEY,
    Token VARCHAR(255) NOT NULL,
    Expiry DATETIME NOT NULL
)");

$message = '';
$message_type = ''; // 'success' or 'danger'
$step = $_GET['step'] ?? '1';

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    if ($step == '1') {
        $email = mysqli_real_escape_string($conn, trim($_POST['email']));
        
        // Ensure user actually exists in the core system
        $chk = mysqli_query($conn, "SELECT UserID FROM Users WHERE Email='$email' AND IsActive=1 AND markasdeleted=0");
        if (mysqli_num_rows($chk) > 0) {
            $code = sprintf("%06d", mt_rand(1, 999999));
            $expiry = date('Y-m-d H:i:s', strtotime('+15 minutes'));
            
            // Store the token mapped to the email
            mysqli_query($conn, "REPLACE INTO PasswordResets (Email, Token, Expiry) VALUES ('$email', '$code', '$expiry')");
            
            // Dispatch Mail
            $mail = new PHPMailer(true);
            try {
                $mail->isSMTP();
                $mail->Host = 'smtp.gmail.com';
                $mail->SMTPAuth = true;
                $mail->Username = 'abhihours24@gmail.com'; 
                $mail->Password = 'ttgk odnj dppf orbm'; // Reused active App Password provided in contact.php
                $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
                $mail->Port = 587;

                $mail->setFrom('abhihours24@gmail.com', 'SupplyNet Security');
                $mail->addAddress($email);

                $mail->isHTML(true);
                $mail->Subject = 'Your SupplyNet Password Reset Code';
                $mail->Body = "<h2>Password Reset Verification</h2>
                               <p>Hello,</p>
                               <p>You requested to reset the password for your SupplyNet account.</p>
                               <p>Your highly secure 6-digit reset code is: <h3 style='color: #4e73df; font-size: 24px;'>$code</h3></p>
                               <p>This code will explicitly expire in 15 minutes. If you did not request a password reset, please ignore this email.</p>
                               <hr>
                               <p><small>SupplyNet automated security module.</small></p>";

                $mail->send();
                
                $_SESSION['reset_email'] = $email;
                header("Location: forgot_password.php?step=2");
                exit();
            } catch (Exception $e) {
                $message = "Mail Error: " . $mail->ErrorInfo;
                $message_type = "danger";
            }
        } else {
            $message = "We could not find an active account associated with that email.";
            $message_type = "danger";
        }
    } elseif ($step == '2') {
        $email = $_SESSION['reset_email'] ?? '';
        $code = mysqli_real_escape_string($conn, trim($_POST['code']));
        $new_password = $_POST['new_password'];
        $confirm_password = $_POST['confirm_password'];

        if ($email === '') {
            $message = "Session expired. Please try again.";
            $message_type = "danger";
            $step = '1';
        } elseif ($new_password !== $confirm_password) {
            $message = "Passwords do not align. Please make sure they match.";
            $message_type = "danger";
        } elseif (strlen($new_password) < 6) {
            $message = "Password must be precisely at least 6 characters long.";
            $message_type = "danger";
        } else {
            $now = date('Y-m-d H:i:s');
            // Check code validity
            $chk = mysqli_query($conn, "SELECT * FROM PasswordResets WHERE Email='$email' AND Token='$code' AND Expiry > '$now'");
            
            if (mysqli_num_rows($chk) > 0) {
                // Apply hashed password algorithm for highly secure database persisting
                $hashed = password_hash($new_password, PASSWORD_DEFAULT);
                mysqli_query($conn, "UPDATE Users SET Password='$hashed' WHERE Email='$email'");
                
                // Erase token for security
                mysqli_query($conn, "DELETE FROM PasswordResets WHERE Email='$email'");
                unset($_SESSION['reset_email']);
                
                $message = "Password reconfigured successfully! <br><br> <a href='login.php' class='btn btn-outline-success mt-3'>Return to Login</a>";
                $message_type = "success";
                $step = '3';
            } else {
                $message = "Invalid or expired authorization code. Please try again or request a new code.";
                $message_type = "danger";
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
    <title>Forgot Password - SupplyNet</title>
    <!-- Fonts -->
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
        
        body { font-family: 'Inter', sans-serif; background-color: var(--primary-bg); height: 100vh; display: flex; align-items: center; justify-content: center; }
        .login-card { border: none; border-radius: 1rem; box-shadow: var(--shadow); overflow: hidden; width: 100%; max-width: 500px; background: #fff; }
        .login-form-container { padding: 4rem 3rem; }
        .form-control { border-radius: 50rem; padding: 0.8rem 1.5rem; font-size: 0.9rem; border: 1px solid #d1d3e2; }
        .form-control:focus { border-color: var(--primary-color); box-shadow: 0 0 0 0.25rem rgba(78, 115, 223, 0.25); }
        .btn-custom { border-radius: 50rem; padding: 0.8rem 1.5rem; font-size: 0.9rem; font-weight: 600; background: var(--primary-color); color: white; border: none; transition: all 0.3s; }
        .btn-custom:hover { background: #2e59d9; transform: translateY(-2px); box-shadow: 0 6px 15px rgba(78, 115, 223, 0.4); }
        .text-primary-custom { color: var(--primary-color); text-decoration: none; }
        .text-primary-custom:hover { color: #2e59d9; text-decoration: underline; }
    </style>
</head>
<body>

<div class="container">
    <div class="row justify-content-center">
        <div class="col-12">
            <div class="card login-card mx-auto">
                <div class="login-form-container text-center">
                    
                    <a href="index.php" class="text-decoration-none d-inline-block mb-3">
                        <h4 class="fw-bold mb-0" style="color: var(--primary-color);"><i class="fas fa-cubes me-2"></i>SupplyNet</h4>
                    </a>
                    
                    <?php if ($message): ?>
                        <div class="alert alert-<?php echo $message_type; ?> text-start mb-4">
                            <?php echo $message; ?>
                        </div>
                    <?php endif; ?>

                    <?php if ($step == '1'): ?>
                        <h4 class="text-dark fw-bold mb-2">Forgot Your Password?</h4>
                        <p class="text-muted small mb-4">We get it, stuff happens. Just enter your email address below and we'll send you a 6-digit code to reset your password!</p>
                        
                        <form action="forgot_password.php?step=1" method="POST">
                            <div class="mb-4 text-start">
                                <input type="email" class="form-control" name="email" placeholder="Enter Email Address..." required autofocus>
                            </div>
                            <button type="submit" class="btn btn-custom w-100 mb-3"><i class="fas fa-paper-plane me-2"></i>Send Reset Code</button>
                        </form>
                        
                    <?php elseif ($step == '2'): ?>
                        
                        <h4 class="text-dark fw-bold mb-2">Verify Reset Code</h4>
                        <p class="text-muted small mb-4">An email with a 6-digit secure code has been sent to <strong><?php echo htmlspecialchars($_SESSION['reset_email'] ?? ''); ?></strong>. Please enter the code along with your new password.</p>
                        
                        <form action="forgot_password.php?step=2" method="POST">
                            <div class="mb-3 text-start">
                                <input type="text" class="form-control text-center text-primary fw-bold" name="code" placeholder="- - - - - -" maxlength="6" required autofocus style="letter-spacing: 0.5rem; font-size: 1.2rem;">
                            </div>
                            <div class="mb-3 text-start">
                                <input type="password" class="form-control" name="new_password" placeholder="New Password" required minlength="6">
                            </div>
                            <div class="mb-4 text-start">
                                <input type="password" class="form-control" name="confirm_password" placeholder="Confirm New Password" required minlength="6">
                            </div>
                            <button type="submit" class="btn btn-custom w-100 mb-3"><i class="fas fa-lock me-2"></i>Update Password</button>
                        </form>
                        
                    <?php elseif ($step == '3'): ?>
                        <!-- Completed state, nothing to show except the message generated above -->
                    <?php endif; ?>

                    <?php if ($step != '3'): ?>
                        <hr class="my-4">
                        <div class="text-center">
                            <a class="small text-primary-custom" href="login.php">Already have an account? Login!</a>
                        </div>
                    <?php endif; ?>
                    
                </div>
            </div>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
