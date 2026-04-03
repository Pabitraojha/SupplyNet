<?php
session_start();
require_once '../config/db.php';

// Auth Protection
if (!isset($_SESSION['UserID']) || strtolower($_SESSION['Role']) !== 'customer') {
    header("Location: ../login.php");
    exit();
}

$user_id = $_SESSION['UserID'];
$display_name = $_SESSION['UserName'] ?? 'Customer';
$success_msg = '';
$error_msg = '';

// Handle Form Submission
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'submit_feedback') {
    $product_id = $_POST['product_id'];
    $rating = $_POST['rating'];
    $comment = trim($_POST['comment']);

    if (empty($product_id) || empty($rating)) {
        $error_msg = "Please select a product and provide a rating.";
    } else {
        $stmt = $conn->prepare("INSERT INTO CustomersFeedback (UserID, ProductID, Rating, Comment) VALUES (?, ?, ?, ?)");
        $stmt->bind_param("iiis", $user_id, $product_id, $rating, $comment);
        if ($stmt->execute()) {
            addNotification($conn, "New customer feedback received from $display_name for a product.", null, 'admin');
            $success_msg = "Thank you! Your feedback has been successfully submitted.";
        } else {
            $error_msg = "Failed to submit feedback. Please try again.";
        }
        $stmt->close();
    }
}

// Fetch user's ordered products for the dropdown (products they've explicitly ordered)
$stmt = $conn->prepare("
    SELECT DISTINCT p.ProductID, p.ProductName 
    FROM Orders o 
    JOIN Products p ON o.ProductID = p.ProductID 
    WHERE o.UserID = ? AND o.markasdeleted = 0
");
$stmt->bind_param("i", $user_id);
$stmt->execute();
$ordered_products = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);

// Fetch their past feedback
$stmt = $conn->prepare("
    SELECT f.Rating, f.Comment, f.FeedbackDate, p.ProductName, f.IsReplied, f.AdminReply
    FROM CustomersFeedback f
    JOIN Products p ON f.ProductID = p.ProductID
    WHERE f.UserID = ? AND f.markasdeleted = 0
    ORDER BY f.FeedbackDate DESC
");
$stmt->bind_param("i", $user_id);
$stmt->execute();
$past_feedbacks = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Submit Feedback - SupplyNet</title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    
    <style>
        :root {
            --primary-color: #fd7e14;
            --primary-hover: #e86000;
            --secondary-color: #f8f9fc;
            --dark-color: #1f2937;
            --sidebar-width: 250px;
        }

        body { font-family: 'Inter', sans-serif; background-color: var(--secondary-color); overflow-x: hidden; }
        
        .sidebar { width: var(--sidebar-width); height: 100vh; position: fixed; background: linear-gradient(180deg, var(--primary-color) 10%, var(--primary-hover) 100%); color: #fff; z-index: 1000; box-shadow: 2px 0 10px rgba(0,0,0,0.1); }
        .sidebar .brand { padding: 1.5rem 1rem; font-size: 1.25rem; font-weight: 800; text-align: center; text-transform: uppercase; letter-spacing: 0.05rem; border-bottom: 1px solid rgba(255,255,255,0.1); display: block; color: #fff; text-decoration: none; }
        .nav-item { padding: 0 1rem; margin-bottom: 0.5rem; }
        .nav-link { color: rgba(255,255,255,0.8); padding: 1rem; border-radius: 0.5rem; font-weight: 600; transition: all 0.3s; text-decoration: none;}
        .nav-link:hover, .nav-link.active { color: #fff; background: rgba(255,255,255,0.15); transform: translateX(5px); }
        .nav-link i { margin-right: 0.75rem; width: 20px; text-align: center; }

        .main-wrapper { margin-left: var(--sidebar-width); flex-grow: 1; min-height: 100vh; display: flex; flex-direction: column; }
        .topbar { background: #fff; height: 70px; box-shadow: 0 0.15rem 1.75rem 0 rgba(58, 59, 69, 0.15); display: flex; align-items: center; justify-content: space-between; padding: 0 2rem; }
        .topbar-user { font-weight: 600; color: #5a5c69; display: flex; align-items: center; gap: 0.5rem; text-decoration: none; }
        .topbar-user img { width: 35px; height: 35px; border-radius: 50%; border: 2px solid var(--primary-color); }

        .dashboard-content { padding: 1.5rem 2rem; flex-grow: 1; }
        
        .card-custom { border: none; border-radius: 1rem; box-shadow: 0 0.5rem 1.5rem rgba(0,0,0,0.05); background: #fff; overflow: hidden; margin-bottom: 1.5rem; }
        .card-header-custom { background: #fff; border-bottom: 1px solid #f8f9fc; padding: 1.25rem 1.5rem; font-weight: 700; color: var(--dark-color); }
        
        .form-control, .form-select { border-radius: 0.5rem; border: 1px solid #d1d3e2; padding: 0.75rem; transition: border-color 0.2s, box-shadow 0.2s; }
        .form-control:focus, .form-select:focus { border-color: var(--primary-color); box-shadow: 0 0 0 0.25rem rgba(253, 126, 20, 0.25); }
        .form-label { font-weight: 600; color: #4a5568; font-size: 0.85rem; text-transform: uppercase; letter-spacing: 0.5px; }
        
        .star-rating {
            display: flex;
            flex-direction: row-reverse;
            justify-content: flex-end;
        }
        .star-rating input { display: none; }
        .star-rating label { color: #ddd; font-size: 2rem; padding: 0 0.2rem; cursor: pointer; transition: color 0.2s; }
        .star-rating :checked ~ label,
        .star-rating label:hover,
        .star-rating label:hover ~ label { color: #f6c23e; }

        .feedback-item { padding: 1.5rem; border-bottom: 1px solid #f8f9fc; }
        .feedback-item:last-child { border-bottom: none; }
        .stars-display { color: #f6c23e; letter-spacing: 2px; }
        .admin-reply { background: rgba(54, 185, 204, 0.05); border-left: 4px solid #36b9cc; padding: 1rem; border-radius: 0.5rem; margin-top: 1rem; }

        @media (max-width: 768px) {
            .sidebar { transform: translateX(-100%); transition: transform 0.3s ease-in-out; }
            .sidebar.show-sidebar { transform: translateX(0); }
            .main-wrapper { margin-left: 0; width: 100%; }
            .sidebar-overlay { display: none; position: fixed; top: 0; left: 0; right: 0; bottom: 0; background: rgba(0,0,0,0.5); z-index: 999; cursor: pointer; }
            .sidebar-overlay.show { display: block; }
            .topbar { padding: 0 1rem; }
            .dashboard-content { padding: 1rem; }
        }
    </style>
</head>
<body>

<div class="sidebar">
    <a href="index.php" class="brand"><i class="fas fa-cubes me-2"></i>SupplyNet<br><small class="text-white-50" style="font-size: 0.7rem;">Customer Portal</small></a>
    <div class="mt-4">
        <div class="nav-item"><a href="index.php" class="nav-link"><i class="fas fa-home"></i> Dashboard</a></div>
        <div class="nav-item"><a href="trackOrder.php" class="nav-link"><i class="fas fa-satellite-dish"></i> Track Order</a></div>
        <div class="nav-item"><a href="submitFeedback.php" class="nav-link active"><i class="fas fa-comment-dots"></i> Feedback</a></div>
    </div>
</div>

<div class="sidebar-overlay" id="sidebarOverlay"></div>
<div class="main-wrapper">
    <div class="topbar">
        <div class="d-flex align-items-center">
            <button class="btn btn-link d-md-none text-dark me-2 px-0" id="sidebarToggle"><i class="fas fa-bars fs-5"></i></button>
            <div class="ms-2 ms-md-0">
                <h4 class="m-0 fw-bold text-dark fs-5 fs-md-4">Product Feedback</h4>
            </div>
        </div>
        <div class="d-flex align-items-center gap-3 gap-md-4">
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
                    <span class="d-none d-lg-inline"><?php echo htmlspecialchars($display_name); ?></span>
                    <img src="https://ui-avatars.com/api/?name=<?php echo urlencode($display_name); ?>&background=fd7e14&color=fff" alt="Profile">
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

        <div class="row g-4 d-flex align-items-stretch">
            <!-- Submit Form -->
            <div class="col-lg-5">
                <div class="card card-custom h-100">
                    <div class="card-header-custom border-bottom py-3">
                        <i class="fas fa-pen-square me-2 text-primary"></i> Write a Review
                    </div>
                    <div class="card-body p-4">
                        <?php if (empty($ordered_products)): ?>
                            <div class="text-center py-4">
                                <i class="fas fa-shopping-cart fs-1 text-muted mb-3 opacity-50"></i>
                                <p class="text-muted">You need to place an order before you can submit product feedback.</p>
                            </div>
                        <?php else: ?>
                            <form method="POST">
                                <input type="hidden" name="action" value="submit_feedback">
                                
                                <div class="mb-4">
                                    <label class="form-label" for="productSelect">Select Product <span class="text-danger">*</span></label>
                                    <select class="form-select" id="productSelect" name="product_id" required>
                                        <option value="" selected disabled>Choose what you're reviewing...</option>
                                        <?php foreach ($ordered_products as $p): ?>
                                            <option value="<?php echo $p['ProductID']; ?>"><?php echo htmlspecialchars($p['ProductName']); ?></option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                                
                                <div class="mb-4">
                                    <label class="form-label d-block">Rate Your Experience <span class="text-danger">*</span></label>
                                    <div class="star-rating">
                                        <input type="radio" id="star5" name="rating" value="5" required/><label for="star5" title="5 stars"><i class="fas fa-star"></i></label>
                                        <input type="radio" id="star4" name="rating" value="4" /><label for="star4" title="4 stars"><i class="fas fa-star"></i></label>
                                        <input type="radio" id="star3" name="rating" value="3" /><label for="star3" title="3 stars"><i class="fas fa-star"></i></label>
                                        <input type="radio" id="star2" name="rating" value="2" /><label for="star2" title="2 stars"><i class="fas fa-star"></i></label>
                                        <input type="radio" id="star1" name="rating" value="1" /><label for="star1" title="1 star"><i class="fas fa-star"></i></label>
                                    </div>
                                </div>
                                
                                <div class="mb-4">
                                    <label class="form-label" for="commentArea">Additional Comments</label>
                                    <textarea class="form-control" id="commentArea" name="comment" rows="4" placeholder="Tell us what you thought about the product quality, packaging, etc."></textarea>
                                </div>
                                
                                <button type="submit" class="btn btn-primary w-100 py-2" style="background-color: var(--primary-color); border:none;"><i class="fas fa-paper-plane me-2"></i>Submit Feedback</button>
                            </form>
                        <?php endif; ?>
                    </div>
                </div>
            </div>

            <!-- Past Feedback -->
            <div class="col-lg-7">
                <div class="card card-custom h-100">
                    <div class="card-header-custom border-bottom py-3">
                        <i class="fas fa-history me-2 text-primary"></i> Your Feedback History
                    </div>
                    <div class="card-body p-0" style="max-height: 600px; overflow-y: auto;">
                        <?php if (empty($past_feedbacks)): ?>
                            <div class="text-center p-5 text-muted">
                                <i class="fas fa-comment-slash fs-1 mb-3 opacity-50"></i>
                                <p>You haven't submitted any feedback yet.</p>
                            </div>
                        <?php else: ?>
                            <?php foreach ($past_feedbacks as $fb): 
                                $date = new DateTime($fb['FeedbackDate']);
                            ?>
                                <div class="feedback-item">
                                    <div class="d-flex justify-content-between align-items-start mb-2">
                                        <div>
                                            <h6 class="fw-bold mb-1 text-dark"><?php echo htmlspecialchars($fb['ProductName']); ?></h6>
                                            <div class="stars-display fs-6">
                                                <?php
                                                    for ($i=1; $i<=5; $i++) {
                                                        echo $i <= $fb['Rating'] ? '<i class="fas fa-star"></i>' : '<i class="far fa-star"></i>';
                                                    }
                                                ?>
                                            </div>
                                        </div>
                                        <span class="text-muted small"><i class="far fa-calendar-alt me-1"></i><?php echo $date->format('M d, Y'); ?></span>
                                    </div>
                                    <?php if (!empty($fb['Comment'])): ?>
                                        <p class="text-secondary small mb-0 mt-2">"<?php echo nl2br(htmlspecialchars($fb['Comment'])); ?>"</p>
                                    <?php endif; ?>
                                    
                                    <?php if ($fb['IsReplied'] && !empty($fb['AdminReply'])): ?>
                                        <div class="admin-reply">
                                            <div class="fw-bold text-dark small mb-1"><i class="fas fa-headset me-1 text-info"></i> Support Reply:</div>
                                            <p class="mb-0 small text-secondary"><?php echo nl2br(htmlspecialchars($fb['AdminReply'])); ?></p>
                                        </div>
                                    <?php endif; ?>
                                </div>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
    </div>
    <?php include '../config/footer.php'; ?>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
<script>
    document.getElementById('sidebarToggle').addEventListener('click', function() {
        document.querySelector('.sidebar').classList.add('show-sidebar');
        document.getElementById('sidebarOverlay').classList.add('show');
    });

    document.getElementById('sidebarOverlay').addEventListener('click', function() {
        document.querySelector('.sidebar').classList.remove('show-sidebar');
        this.classList.remove('show');
    });
</script>
</body>
</html>
