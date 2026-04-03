<?php
session_start();
require_once '../config/db.php';

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

if (!isset($_SESSION['UserID']) || strtolower($_SESSION['Role']) !== 'admin') {
    header("Location: ../login.php");
    exit();
}

$admin_name = $_SESSION['UserName'] ?? 'Administrator';
$message_status = '';

// Handle Reply Action
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['action']) && $_POST['action'] == 'reply') {
    $feedback_id = intval($_POST['feedback_id'] ?? 0);
    $reply_message = htmlspecialchars($_POST['reply_message'] ?? '');
    
    // Get user details
    $stmt = $conn->prepare("
        SELECT u.Email, u.UserName, p.ProductName, cf.Comment
        FROM CustomersFeedback cf
        JOIN Users u ON cf.UserID = u.UserID
        JOIN Products p ON cf.ProductID = p.ProductID
        WHERE cf.FeedbackID = ?
    ");
    $stmt->bind_param("i", $feedback_id);
    $stmt->execute();
    $res = $stmt->get_result();
    $fb_data = $res->fetch_assoc();
    
    if ($fb_data && !empty($reply_message)) {
        require '../PHPMailer/src/Exception.php';
        require '../PHPMailer/src/PHPMailer.php';
        require '../PHPMailer/src/SMTP.php';
        
        $to = $fb_data['Email'];
        $subject = 'Reply to your SupplyNet Feedback (' . $fb_data['ProductName'] . ')';
        $body = "Hello " . $fb_data['UserName'] . ",\n\n";
        $body .= "Thank you for the feedback on " . $fb_data['ProductName'] . ".\n\n";
        $body .= "Your Feedback: \"" . $fb_data['Comment'] . "\"\n\n";
        $body .= "---\n";
        $body .= "Response from Admin:\n" . $reply_message . "\n\n";
        $body .= "Best regards,\nThe SupplyNet Team";
        
        $mail = new PHPMailer(true);
        try {
            $mail->isSMTP();
            $mail->Host       = 'smtp.gmail.com'; 
            $mail->SMTPAuth   = true;
            $mail->Username   = 'abhihours24@gmail.com';
            $mail->Password   = 'ttgk odnj dppf orbm';
            $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
            $mail->Port       = 587;
            
            $mail->setFrom('abhihours24@gmail.com', 'SupplyNet Support'); 
            $mail->addAddress($to); 

            $mail->isHTML(false);
            $mail->Subject = $subject;
            $mail->Body    = $body;
            
            $mail->send();
            
            // Mark as replied in DB
            $update = $conn->prepare("UPDATE CustomersFeedback SET IsReplied=1, AdminReply=? WHERE FeedbackID=?");
            $update->bind_param("si", $reply_message, $feedback_id);
            $update->execute();
            
            $message_status = '<div class="alert alert-success"><i class="fas fa-check-circle me-2"></i>Reply sent successfully to ' . htmlspecialchars($to) . '!</div>';
        } catch (Exception $e) {
            $message_status = '<div class="alert alert-danger"><i class="fas fa-exclamation-triangle me-2"></i>Error sending reply. Mailer Error: ' . $mail->ErrorInfo . '</div>';
        }
    } else {
        $message_status = '<div class="alert alert-warning">Invalid reply data or empty message.</div>';
    }
}

// Fetch all feedbacks
$feedbacks = [];
$query = "
    SELECT cf.*, u.UserName, u.Email, p.ProductName 
    FROM CustomersFeedback cf
    JOIN Users u ON cf.UserID = u.UserID
    JOIN Products p ON cf.ProductID = p.ProductID
    WHERE cf.markasdeleted = 0
    ORDER BY cf.FeedbackDate DESC
";
$res_fb = $conn->query($query);
if ($res_fb) {
    while ($row = $res_fb->fetch_assoc()) {
        $feedbacks[] = $row;
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Customer Feedback - SupplyNet Admin</title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <style>
        :root { --primary-color: #4e73df; --sidebar-width: 250px; }
        body { font-family: 'Inter', sans-serif; background-color: #f8f9fc; overflow-x: hidden; }
        .sidebar { width: var(--sidebar-width); height: 100vh; position: fixed; background: linear-gradient(180deg, #4e73df 10%, #224abe 100%); color: #fff; z-index: 1000; box-shadow: 2px 0 10px rgba(0,0,0,0.1); }
        .sidebar .brand { padding: 1.5rem 1rem; font-size: 1.25rem; font-weight: 800; text-align: center; text-transform: uppercase; letter-spacing: 0.05rem; border-bottom: 1px solid rgba(255,255,255,0.1); display: block; color: #fff; text-decoration: none; }
        .nav-item { padding: 0 1rem; margin-bottom: 0.5rem; }
        .nav-link { color: rgba(255,255,255,0.8); padding: 1rem; border-radius: 0.5rem; font-weight: 600; transition: all 0.3s; }
        .nav-link:hover, .nav-link.active { color: #fff; background: rgba(255,255,255,0.15); transform: translateX(5px); }
        .nav-link i { margin-right: 0.75rem; width: 20px; text-align: center; }
        .main-wrapper { margin-left: var(--sidebar-width); flex-grow: 1; min-height: 100vh; display: flex; flex-direction: column; }
        .topbar { background: #fff; height: 70px; box-shadow: 0 0.15rem 1.75rem 0 rgba(58, 59, 69, 0.15); display: flex; align-items: center; justify-content: space-between; padding: 0 2rem; }
        .topbar-user { font-weight: 600; color: #5a5c69; display: flex; align-items: center; gap: 0.5rem; }
        .topbar-user img { width: 35px; height: 35px; border-radius: 50%; border: 2px solid var(--primary-color); }
        .dashboard-content { padding: 1.5rem 2rem; }
        .card-custom { border: none; border-radius: 0.75rem; box-shadow: 0 0.15rem 1.75rem 0 rgba(58, 59, 69, 0.1); }
        .rating-stars { color: #f6c23e; }
    
        @media (max-width: 768px) {
            .sidebar { transform: translateX(-100%); transition: transform 0.3s ease-in-out; }
            .sidebar.show-sidebar { transform: translateX(0); }
            .main-wrapper { margin-left: 0; width: 100%; }
            .sidebar-overlay { display: none; position: fixed; top: 0; left: 0; right: 0; bottom: 0; background: rgba(0,0,0,0.5); z-index: 999; cursor: pointer; }
            .sidebar-overlay.show { display: block; }
        }
</style>
</head>
<body>

<div class="sidebar">
    <a href="index.php" class="brand"><i class="fas fa-cubes me-2"></i>SupplyNet<br><small class="text-white-50" style="font-size: 0.7rem;">Admin Panel</small></a>
    <div class="mt-4">
        <div class="nav-item"><a href="index.php" class="nav-link"><i class="fas fa-tachometer-alt"></i> Dashboard</a></div>
        <div class="nav-item"><a href="user_management.php" class="nav-link"><i class="fas fa-users"></i> User Management</a></div>
        <div class="nav-item"><a href="products_directory.php" class="nav-link"><i class="fas fa-box-open"></i> Products Directory</a></div>
        <div class="nav-item"><a href="orders_hub.php" class="nav-link"><i class="fas fa-truck-loading"></i> Orders Hub</a></div>
        <div class="nav-item"><a href="delivery_route.php" class="nav-link"><i class="fas fa-route"></i> Deliveries & Routes</a></div>
        <div class="nav-item"><a href="customer_feedback.php" class="nav-link active"><i class="fas fa-comments"></i> Customer Feedback</a></div>
        <div class="nav-item"><a href="system_settings.php" class="nav-link"><i class="fas fa-cogs"></i> System Settings</a></div>
    </div>
</div>


<div class="sidebar-overlay" id="sidebarOverlay"></div>
<div class="main-wrapper">
    <div class="topbar">
        <div class="d-flex align-items-center">
            <button class="btn btn-link d-md-none text-dark me-3" id="sidebarToggle"><i class="fas fa-bars"></i></button>
            <h4 class="m-0 fw-bold text-dark">Customer Feedback</h4></div>
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
                    <span class="d-none d-lg-inline"><?php echo htmlspecialchars($admin_name); ?></span>
                    <img src="https://ui-avatars.com/api/?name=<?php echo urlencode($admin_name); ?>&background=4e73df&color=fff" alt="Admin Profile">
                </a>
                <ul class="dropdown-menu dropdown-menu-end shadow border-0 mt-2">
                    <li><a class="dropdown-item" href="profile.php"><i class="fas fa-user fa-sm fa-fw me-2 text-gray-400"></i> Profile</a></li>
                    <li><hr class="dropdown-divider"></li>
                    <li><a class="dropdown-item text-danger" href="../logout.php"><i class="fas fa-sign-out-alt fa-sm fa-fw me-2"></i> Logout</a></li>
                </ul>
            </div>
        </div>
    </div>

    <div class="dashboard-content">
        <?php echo $message_status; ?>
        
        <div class="card card-custom">
            <div class="card-header bg-white border-bottom py-3">
                <h6 class="m-0 font-weight-bold text-primary"><i class="fas fa-inbox me-2"></i>Feedback Inbox</h6>
            </div>
            <div class="card-body">
                <div class="table-responsive">
                    <table class="table table-hover align-middle border">
                        <thead class="table-light">
                            <tr>
                                <th>Date</th>
                                <th>Customer</th>
                                <th>Product</th>
                                <th>Rating</th>
                                <th style="max-width:300px;">Comment</th>
                                <th>Status</th>
                                <th class="text-end">Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if(empty($feedbacks)): ?>
                                <tr><td colspan="7" class="text-center py-4 text-muted">No feedback received yet.</td></tr>
                            <?php else: ?>
                                <?php foreach($feedbacks as $fb): ?>
                                <tr>
                                    <td class="text-muted small"><?php echo date('M d, Y', strtotime($fb['FeedbackDate'])); ?></td>
                                    <td>
                                        <div class="fw-bold"><?php echo htmlspecialchars($fb['UserName']); ?></div>
                                        <div class="small text-muted"><?php echo htmlspecialchars($fb['Email']); ?></div>
                                    </td>
                                    <td><span class="badge bg-info text-dark"><?php echo htmlspecialchars($fb['ProductName']); ?></span></td>
                                    <td class="rating-stars mb-0">
                                        <?php for($i=1; $i<=5; $i++): ?>
                                            <i class="fas fa-star<?php echo ($i <= $fb['Rating']) ? '' : ' text-light text-opacity-50'; ?>"></i>
                                        <?php endfor; ?>
                                    </td>
                                    <td style="max-width:300px;" class="text-truncate">
                                        <?php echo htmlspecialchars($fb['Comment']); ?>
                                        <?php if($fb['IsReplied'] == 1 && !empty($fb['AdminReply'])): ?>
                                            <br><small class="text-success"><i class="fas fa-reply me-1"></i>Replied: <i><?php echo htmlspecialchars(substr($fb['AdminReply'], 0, 50)) . '...'; ?></i></small>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <?php if(isset($fb['IsReplied']) && $fb['IsReplied'] == 1): ?>
                                            <span class="badge bg-success bg-opacity-10 text-success border border-success border-opacity-25 pb-1"><i class="fas fa-check-double me-1"></i>Replied</span>
                                        <?php else: ?>
                                            <span class="badge bg-warning bg-opacity-10 text-warning border border-warning border-opacity-50 pb-1 text-dark"><i class="fas fa-clock me-1"></i>Pending</span>
                                        <?php endif; ?>
                                    </td>
                                    <td class="text-end">
                                        <button class="btn btn-sm btn-primary" data-bs-toggle="modal" data-bs-target="#replyModal<?php echo $fb['FeedbackID']; ?>">
                                            <i class="fas fa-reply me-1"></i> Reply
                                        </button>
                                    </td>
                                </tr>

                                <!-- Reply Modal -->
                                <div class="modal fade" id="replyModal<?php echo $fb['FeedbackID']; ?>" tabindex="-1" aria-hidden="true">
                                    <div class="modal-dialog modal-dialog-centered modal-lg">
                                        <div class="modal-content border-0 shadow">
                                            <div class="modal-header bg-primary text-white border-0">
                                                <h5 class="modal-title"><i class="fas fa-reply me-2"></i>Reply to <?php echo htmlspecialchars($fb['UserName']); ?></h5>
                                                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
                                            </div>
                                            <form action="customer_feedback.php" method="POST">
                                                <div class="modal-body p-4">
                                                    <input type="hidden" name="action" value="reply">
                                                    <input type="hidden" name="feedback_id" value="<?php echo $fb['FeedbackID']; ?>">
                                                    
                                                    <div class="mb-4 bg-light p-3 rounded border">
                                                        <h6 class="fw-bold mb-2 text-primary">Customer's Feedback on <?php echo htmlspecialchars($fb['ProductName']); ?>:</h6>
                                                        <div class="rating-stars mb-2 small">
                                                            <?php for($i=1; $i<=5; $i++): ?>
                                                                <i class="fas fa-star<?php echo ($i <= $fb['Rating']) ? '' : ' text-secondary opacity-25'; ?>"></i>
                                                            <?php endfor; ?>
                                                        </div>
                                                        <p class="mb-0 text-dark">"<?php echo nl2br(htmlspecialchars($fb['Comment'])); ?>"</p>
                                                    </div>
                                                    
                                                    <div class="mb-3">
                                                        <label class="form-label fw-bold">Your Reply Template (will be sent via email)</label>
                                                        <textarea class="form-control" name="reply_message" rows="5" placeholder="Dear <?php echo htmlspecialchars($fb['UserName']); ?>, thank you for your feedback..." required></textarea>
                                                        <div class="form-text mt-2"><i class="fas fa-info-circle me-1"></i> This response will be emailed directly to <strong><?php echo htmlspecialchars($fb['Email']); ?></strong> using SupplyNet support email.</div>
                                                    </div>
                                                </div>
                                                <div class="modal-footer bg-light border-top-0">
                                                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                                                    <button title="Send Reply" type="submit" class="btn btn-primary px-4"><i class="fas fa-paper-plane"></i></button>
                                                </div>
                                            </form>
                                        </div>
                                    </div>
                                </div>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
    <?php include '../config/footer.php'; ?>
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
