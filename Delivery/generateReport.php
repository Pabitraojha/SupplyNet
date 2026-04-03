<?php
session_start();
require_once '../config/db.php';

// Auth Protection
if (!isset($_SESSION['UserID']) || strtolower($_SESSION['Role']) !== 'delivery_person') {
    header("Location: ../login.php");
    exit();
}

if (!isset($_GET['customer_id'])) {
    die("Invalid Request. Customer ID missing.");
}

$user_id = $_SESSION['UserID'];
$display_name = $_SESSION['UserName'] ?? 'Agent';
$customer_id = intval($_GET['customer_id']);

// Fetch active driver details
$driver_phone = 'N/A';
$drv_stmt = $conn->prepare("SELECT mobile_number FROM Users WHERE UserID = ?");
$drv_stmt->bind_param("i", $user_id);
$drv_stmt->execute();
$drv_res = $drv_stmt->get_result();
if ($drv_res && $drv_row = $drv_res->fetch_assoc()) {
    $driver_phone = $drv_row['mobile_number'] ?? 'N/A';
}

// Fetch active assignments for this specific customer
$orders = [];
$customer_info = null;

$query = "
    SELECT 
        d.DeliveryID, 
        d.DeliveryStatus,
        o.OrderID, 
        u.UserName as CustomerName, 
        u.mobile_number as CustomerPhone,
        o.ShippingAddress as Address, 
        o.OrderDate, 
        d.DeliveryDate, 
        o.TotalAmount,
        d.AdditionalRequirements,
        u_emp.UserName as ApprovedEmployeeName,
        (SELECT GROUP_CONCAT(p.ProductName SEPARATOR ', ') FROM OrderDetails od JOIN Products p ON od.ProductID = p.ProductID WHERE od.OrderID = o.OrderID) as Items
    FROM Deliveries d
    JOIN Orders o ON d.OrderID = o.OrderID
    JOIN Users u ON o.UserID = u.UserID
    LEFT JOIN Users u_emp ON o.ApprovedBy = u_emp.UserID
    WHERE d.DeliveryPersonID = ? 
      AND u.UserID = ?
      AND d.markasdeleted = 0 
      AND d.DeliveryStatus IN ('pending', 'in_transit')
    ORDER BY d.DeliveryDate ASC
";

$stmt = $conn->prepare($query);
$stmt->bind_param("ii", $user_id, $customer_id);
$stmt->execute();
$result = $stmt->get_result();

$grand_total = 0;
$approving_employee = 'System Setup / Administrator';

if ($result && $result->num_rows > 0) {
    while($row = $result->fetch_assoc()) {
        if (!$customer_info) {
            $customer_info = [
                'Name' => $row['CustomerName'],
                'Phone' => $row['CustomerPhone'],
                'Address' => $row['Address']
            ];
        }
        if (!empty($row['ApprovedEmployeeName'])) {
            $approving_employee = $row['ApprovedEmployeeName'];
        }
        $grand_total += $row['TotalAmount'];
        $orders[] = $row;
    }
} else {
    die("<div style='font-family:sans-serif; text-align:center; margin-top:50px;'>No active assignments found to generate a report for this customer.</div>");
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Delivery Challan - <?php echo htmlspecialchars($customer_info['Name']); ?></title>
    <!-- Fonts -->
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;600;700;800&display=swap" rel="stylesheet">
    <!-- Bootstrap CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    
    <style>
        body { font-family: 'Inter', sans-serif; background: #f0f2f5; color: #2d3748; padding: 2rem; }
        .report-card { background: #fff; max-width: 900px; margin: 0 auto; box-shadow: 0 10px 30px rgba(0,0,0,0.1); border-radius: 8px; overflow: hidden; }
        .report-header { background: #e74a3b; color: #fff; padding: 2rem; border-bottom: 5px solid #be2617; }
        .report-body { padding: 3rem 2rem; }
        .report-footer { padding: 2rem; background: #f8f9fa; border-top: 1px solid #e2e8f0; }
        
        h1, h2, h3, h4, h5, h6 { font-weight: 700; }
        .text-primary-corp { color: #e74a3b !important; }
        
        .info-box { border: 1px solid #e2e8f0; border-radius: 8px; padding: 1.5rem; background: #fafafa; }
        
        .table-custom th { background: #edf2f7; color: #4a5568; font-weight: 700; text-transform: uppercase; font-size: 0.8rem; letter-spacing: 0.05em; border-bottom: 2px solid #cbd5e0; }
        .table-custom td { vertical-align: middle; border-bottom: 1px solid #e2e8f0; }
        .table-custom tbody tr:nth-last-child(2) td { border-bottom: 2px solid #cbd5e0; }
        
        .signature-box { border: 2px dashed #cbd5e0; height: 120px; border-radius: 8px; margin-top: 1rem; position: relative; background: #fff; }
        .signature-text { position: absolute; bottom: -25px; left: 0; right: 0; text-align: center; color: #718096; font-size: 0.85rem; font-weight: 600; text-transform: uppercase; }
        
        @page {
            size: A4;
            margin: 10mm; /* Slashed margins to maximize paper real-estate */
        }

        @media print {
            body { background: #fff !important; padding: 0 !important; font-size: 10pt !important; margin: 0 !important; max-width: 210mm !important; }
            .btn-print { display: none !important; }
            .report-card { box-shadow: none !important; max-width: 100% !important; border: none !important; margin: 0 !important; border-radius: 0 !important; }
            
            /* Aggressive Bootstrap Margin Compression */
            .mb-5 { margin-bottom: 1.5rem !important; }
            .mt-5 { margin-top: 1.5rem !important; }
            .pt-3 { padding-top: 0.5rem !important; }
            .pb-1, .pb-3 { padding-bottom: 0.5rem !important; }
            
            /* Container Compressions */
            .report-header { padding: 1rem 1.5rem !important; -webkit-print-color-adjust: exact; print-color-adjust: exact; }
            .report-body { padding: 1.5rem !important; }
            .report-footer { padding: 0.5rem !important; margin-top: 1rem !important; border-top: 1px solid #dee2e6 !important; -webkit-print-color-adjust: exact; print-color-adjust: exact; }
            
            .info-box { padding: 1rem !important; }
            .signature-box, .info-box, tr { page-break-inside: avoid; }
            .signature-box { border-color: #000 !important; height: 80px !important; margin-top: 0.5rem !important; }
            
            /* Typeface Compressions */
            h2 { font-size: 1.4rem !important; }
            h3, h4 { font-size: 1.1rem !important; }
            h5, h6 { font-size: 0.95rem !important; margin-bottom: 0.25rem !important; }
            table td, table th { padding: 0.4rem !important; font-size: 9.5pt !important; }
            p.small { font-size: 8.5pt !important; margin-bottom: 0 !important; }
        }
    </style>
</head>
<body>

<div class="report-card">
    <div class="report-header d-flex justify-content-between align-items-center">
        <div>
            <h2 class="m-0 text-white"><i class="fas fa-cubes me-2"></i>SupplyNet</h2>
            <p class="m-0 mt-1 opacity-75">Corporate Logistics & Distribution</p>
        </div>
        <div class="text-end">
            <h3 class="m-0 fw-bold">DELIVERY CHALLAN</h3>
            <p class="m-0 mt-1 pb-1">Date: <?php echo date('M d, Y'); ?></p>
            <span class="badge bg-white text-danger px-3 py-1 fs-6"><?php echo (count($orders) > 1) ? 'BULK DELIVERY' : 'STANDARD DELIVERY'; ?></span>
        </div>
    </div>
    
    <div class="report-body">
        <div class="row mb-5">
            <div class="col-sm-6 mb-4 mb-sm-0">
                <h6 class="text-muted text-uppercase mb-3 fw-bold tracking-wide">Delivery To</h6>
                <div class="info-box h-100">
                    <h4 class="text-dark mb-2"><?php echo htmlspecialchars($customer_info['Name']); ?></h4>
                    <p class="mb-1 text-secondary"><i class="fas fa-map-marker-alt text-primary-corp me-2"></i> <?php echo htmlspecialchars($customer_info['Address']); ?></p>
                    <p class="mb-0 text-secondary"><i class="fas fa-phone-alt text-primary-corp me-2"></i> <?php echo htmlspecialchars($customer_info['Phone'] ?? 'N/A'); ?></p>
                </div>
            </div>
            <div class="col-sm-6">
                <h6 class="text-muted text-uppercase mb-3 fw-bold tracking-wide">Dispatch By</h6>
                <div class="info-box h-100">
                    <h5 class="text-dark mb-2"><?php echo htmlspecialchars($display_name); ?></h5>
                    <p class="mb-1 text-secondary"><i class="fas fa-id-badge text-primary-corp me-2"></i> Authorized Agent ID: <?php echo $user_id; ?></p>
                    <p class="mb-0 text-secondary"><i class="fas fa-phone-alt text-primary-corp me-2"></i> <?php echo htmlspecialchars($driver_phone); ?></p>
                </div>
            </div>
        </div>

        <h6 class="text-primary-corp text-uppercase mb-3 fw-bold"><i class="fas fa-clipboard-list me-2"></i>Assignment Details</h6>
        <div class="table-responsive mb-5">
            <table class="table table-custom">
                <thead>
                    <tr>
                        <th>Del. ID</th>
                        <th>Order Ref</th>
                        <th>Items in Package</th>
                        <th>Placed On</th>
                        <th class="text-end">Value (₹)</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($orders as $o): ?>
                        <tr>
                            <td class="fw-bold">DEL-<?php echo str_pad($o['DeliveryID'], 4, '0', STR_PAD_LEFT); ?></td>
                            <td>#<?php echo $o['OrderID']; ?></td>
                            <td style="max-width: 250px;" class="text-truncate" title="<?php echo htmlspecialchars($o['Items']); ?>">
                                <?php echo htmlspecialchars($o['Items'] ?? 'Multiple Corporate Parcels'); ?>
                                <?php if(!empty($o['AdditionalRequirements'])): ?>
                                    <br><small class="text-danger fst-italic"><i class="fas fa-exclamation-circle"></i> <?php echo htmlspecialchars($o['AdditionalRequirements']); ?></small>
                                <?php endif; ?>
                            </td>
                            <td><?php echo date('M d, Y', strtotime($o['OrderDate'])); ?></td>
                            <td class="text-end fw-semibold"><?php echo number_format($o['TotalAmount'], 2); ?></td>
                        </tr>
                    <?php endforeach; ?>
                    <tr class="bg-light">
                        <td colspan="4" class="text-end fw-bold text-uppercase p-3">Total Payable / Value :</td>
                        <td class="text-end fw-bold fs-5 text-success p-3">₹<?php echo number_format($grand_total, 2); ?></td>
                    </tr>
                </tbody>
            </table>
        </div>

        <div class="row align-items-center mt-5 pt-3 border-top">
            <div class="col-md-6 mb-4 mb-md-0">
                <h6 class="fw-bold text-dark mb-2"><i class="fas fa-info-circle text-primary-corp me-2"></i>Terms of Protocol</h6>
                <p class="small text-muted mb-2">
                    By signing this report, the customer acknowledges receipt of all mentioned packages in good, undamaged condition. If discrepancies are found, they must be registered immediately with the dispatcher prior to signing.
                </p>
                <div class="border rounded bg-light p-2 small mt-3 d-inline-block">
                    <span class="text-muted fw-semibold">Internal Audit Trail:</span><br>
                    <span class="fw-bold text-dark"><i class="fas fa-user-check text-success me-1"></i> Authorized By:</span> <?php echo htmlspecialchars($approving_employee); ?>
                </div>
            </div>
            <div class="col-md-6">
                <div class="ps-md-4">
                    <h6 class="fw-bold text-dark text-center mb-0">Customer / Receiver Signature</h6>
                    <div class="signature-box">
                        <div class="signature-text">Sign Here</div>
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <div class="report-footer text-center text-muted small">
        &copy; <?php echo date('Y'); ?> SupplyNet Supply Chain Solutions. All Rights Reserved. Generated securely by internal system.
    </div>
</div>

<button onclick="window.print()" class="btn btn-danger btn-print">
    <i class="fas fa-print me-2"></i> Print Challan
</button>

<script>
    // Optionally trigger print popup automatically on load
    // window.onload = function() { window.print(); }
</script>

</body>
</html>
