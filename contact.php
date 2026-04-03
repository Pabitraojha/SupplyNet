<?php
use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

// Require PHPMailer manually (We downloaded it locally)
require 'PHPMailer/src/Exception.php';
require 'PHPMailer/src/PHPMailer.php';
require 'PHPMailer/src/SMTP.php';

$message_status = '';

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $name = htmlspecialchars($_POST['name'] ?? '');
    $contact_info = htmlspecialchars($_POST['contact_info'] ?? '');
    $interest = htmlspecialchars($_POST['interest'] ?? '');
    $additional_info = htmlspecialchars($_POST['additional_info'] ?? '');

    $to = 'abhijitofficial977@gmail.com';
    $subject = 'New Contact Inquiry: ' . $interest;
    $body = "You have received a new inquiry from the SupplyNet Contact Form.\n\n";
    $body .= "Name: $name\n";
    $body .= "Contact Information: $contact_info\n";
    $body .= "Area of Interest: $interest\n\n";
    $body .= "Additional Information:\n$additional_info\n";
    
    $mail = new PHPMailer(true);
    
    try {
        // Server settings
        $mail->isSMTP();
        $mail->Host       = 'smtp.gmail.com'; 
        $mail->SMTPAuth   = true;
        
        // IMPORTANT: Replace 'YOUR_GMAIL_APP_PASSWORD' with an actual Gmail App Password generated from your Google Account settings
        $mail->Username   = 'abhihours24@gmail.com';
        $mail->Password   = 'ttgk odnj dppf orbm';
        $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
        $mail->Port       = 587;

        // Recipients
        $mail->setFrom('abhihours24@gmail.com', 'SupplyNet Web Form'); 
        $mail->addAddress($to); 
        $mail->addReplyTo($contact_info, $name); // so you can reply directly to the person who contacted you

        // Content
        $mail->isHTML(false);
        $mail->Subject = $subject;
        $mail->Body    = $body;

        $mail->send();
        $message_status = '<div class="alert alert-success d-flex align-items-center mb-4" role="alert"><i class="fas fa-check-circle fs-4 me-3"></i><div><strong>Success!</strong> Our team will contact you very soon.</div></div>';
    } catch (Exception $e) {
        $message_status = '<div class="alert alert-danger d-flex align-items-center mb-4" role="alert"><i class="fas fa-exclamation-circle fs-4 me-3"></i><div><strong>Error!</strong> Message could not be sent. Mailer Error: ' . $mail->ErrorInfo . '</div></div>';
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Contact Us - SupplyNet</title>
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
        
        body { font-family: 'Inter', sans-serif; background-color: var(--primary-bg); color: var(--secondary-color); overflow-x: hidden; }
        h1, h2, h3, h4, h5, h6 { color: var(--text-dark); font-weight: 700; }
        
        .navbar-custom { background: rgba(255, 255, 255, 0.9) !important; backdrop-filter: blur(10px); border-bottom: 1px solid rgba(0,0,0,0.05); transition: all 0.3s ease; }
        .navbar-custom .navbar-brand { font-weight: 800; color: var(--primary-color) !important; font-size: 1.5rem; }
        .navbar-custom .nav-link { color: var(--text-dark) !important; font-weight: 500; margin: 0 0.5rem; transition: color 0.3s ease; }
        .navbar-custom .nav-link:hover, .navbar-custom .nav-link.active { color: var(--primary-color) !important; }
        
        .hero-section { padding: 6rem 0; background: linear-gradient(135deg, rgba(78,115,223,0.05) 0%, rgba(255,255,255,0) 100%); border-bottom: 1px solid rgba(0,0,0,0.05); }
        
        .card-custom { border: none; border-radius: 1rem; box-shadow: var(--shadow); transition: transform 0.3s ease, box-shadow 0.3s ease; background: var(--card-bg); overflow: hidden; }
        .card-custom:hover { box-shadow: var(--shadow-hover); }
        

        
        .btn-custom { border-radius: 50rem; padding: 0.6rem 1.5rem; font-weight: 600; transition: all 0.3s; }
        .btn-custom-primary { background: var(--primary-color); color: white; border: none; box-shadow: 0 4px 10px rgba(78, 115, 223, 0.3); }
        .btn-custom-primary:hover { background: #2e59d9; color: white; transform: translateY(-2px); box-shadow: 0 6px 15px rgba(78, 115, 223, 0.4); }
        
        .form-control, .form-select { border-radius: 0.5rem; padding: 0.75rem 1rem; border: 1px solid #e3e6f0; }
        .form-control:focus, .form-select:focus { border-color: var(--primary-color); box-shadow: 0 0 0 0.25rem rgba(78, 115, 223, 0.25); }
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
                <li class="nav-item"><a class="nav-link" href="features.php">Features</a></li>
                <li class="nav-item"><a class="nav-link active" href="contact.php">Contact</a></li>
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
                <span class="badge bg-primary bg-opacity-10 text-primary px-3 py-2 rounded-pill mb-3 fw-semibold border border-primary-subtle">Get in Touch</span>
                <h1 class="display-4 fw-bold text-dark mb-4">Contact <span class="text-primary">Our Team</span></h1>
                <p class="lead text-muted fs-4">Have questions about SupplyNet or want to integrate our solution? Send us a message and we'll connect you with the right experts.</p>
            </div>
        </div>
    </div>
</div>

<div class="container pb-5">
    <div class="row g-5">
        <!-- Contact Information Side -->
        <div class="col-lg-5">
            <div class="pe-lg-5">
                <h3 class="fw-bold mb-4 border-bottom pb-3">Contact Information</h3>
                <p class="text-muted mb-5" style="line-height: 1.8;">
                    Our dedicated support team is available around the clock to assist you with onboarding, technical integrations, and pre-sales consulting. Let us help you transform your supply chain.
                </p>
                
                <div class="d-flex mb-4">
                    <div class="flex-shrink-0 text-primary fs-3 me-3">
                        <i class="fas fa-map-marker-alt"></i>
                    </div>
                    <div>
                        <h5 class="fw-bold text-dark mb-1">Headquarters</h5>
                        <p class="text-muted">IMIT Campus,<br>Cuttack, Odisha, India</p>
                    </div>
                </div>
                
                <div class="d-flex mb-4">
                    <div class="flex-shrink-0 text-success fs-3 me-3">
                        <i class="fas fa-envelope"></i>
                    </div>
                    <div>
                        <h5 class="fw-bold text-dark mb-1">Email Us</h5>
                        <p class="text-muted">abhihours24@gmail.com</p>
                    </div>
                </div>
                
                <div class="d-flex mb-4">
                    <div class="flex-shrink-0 text-info fs-3 me-3">
                        <i class="fas fa-phone-alt"></i>
                    </div>
                    <div>
                        <h5 class="fw-bold text-dark mb-1">Call Us</h5>
                        <p class="text-muted">+91 (123) 456-7890</p>
                    </div>
                </div>
            </div>
        </div>

        <!-- Contact Form Side -->
        <div class="col-lg-7">
            <div class="card card-custom p-4 p-md-5">
                <h4 class="fw-bold mb-4">Send Us a Message</h4>
                
                <?php echo $message_status; ?>
                
                <form action="contact.php" method="POST">
                    <div class="row g-3">
                        <div class="col-md-6">
                            <label for="name" class="form-label fw-semibold">Your Name *</label>
                            <input type="text" class="form-control" id="name" name="name" placeholder="Enter Full Name" required>
                        </div>
                        <div class="col-md-6">
                            <label for="contact_info" class="form-label fw-semibold">Contact Email / Phone *</label>
                            <input type="text" class="form-control" id="contact_info" name="contact_info" placeholder="example@email.com" required>
                        </div>
                        <div class="col-12">
                            <label for="interest" class="form-label fw-semibold">Area of Interest *</label>
                            <select class="form-select" id="interest" name="interest" required>
                                <option value="" selected disabled>Select your primary interest...</option>
                                <option value="Partnership / Reseller">Partnership / Reseller</option>
                                <option value="Product Demo Request">Product Demo Request</option>
                                <option value="Technical Integration Support">Technical Integration Support</option>
                                <option value="General Inquiry">General Inquiry</option>
                            </select>
                        </div>
                        <div class="col-12">
                            <label for="additional_info" class="form-label fw-semibold">Additional Information *</label>
                            <textarea class="form-control" id="additional_info" name="additional_info" rows="5" placeholder="Please describe how we can help you..." required></textarea>
                        </div>
                        <div class="col-12 mt-4">
                            <button type="submit" class="btn btn-custom btn-custom-primary btn-lg w-100"><i class="fas fa-paper-plane me-2"></i>Send Message</button>
                        </div>
                    </div>
                </form>
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
