<?php
session_start();
require_once '../config/db.php';

// Auth Protection
if (!isset($_SESSION['UserID']) || strtolower($_SESSION['Role']) !== 'salesman') {
    die("Unauthorized access.");
}

$salesman_name = $_SESSION['UserName'] ?? 'Sales Representative';
$salesman_id = $_SESSION['UserID'];

if (!isset($_GET['id']) || empty($_GET['id'])) {
    die("Invalid Order Report ID.");
}

$order_id = intval($_GET['id']);

// Fetch Order Details explicitly ensuring it was placed by this salesman
$query = "
    SELECT 
        o.OrderID, 
        o.OrderDate, 
        o.RequiredDate, 
        o.TotalAmount, 
        o.ShippingAddress, 
        u.UserName as CustomerName, 
        u.Email as CustomerEmail,
        p.ProductName, 
        od.Quantity, 
        od.UnitPrice
    FROM Orders o
    JOIN Users u ON o.UserID = u.UserID
    JOIN OrderDetails od ON o.OrderID = od.OrderID
    JOIN Products p ON od.ProductID = p.ProductID
    WHERE o.OrderID = ? AND o.placed_by = ?
";
$stmt = $conn->prepare($query);
$stmt->bind_param("ii", $order_id, $salesman_id);
if (!$stmt->execute()) {
    die("Database communication error.");
}
$result = $stmt->get_result();

if ($result->num_rows === 0) {
    die("Report data not found or unauthorized access.");
}

$invoice_data = $result->fetch_assoc();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Sales Order Report #<?php echo str_pad($invoice_data['OrderID'], 6, '0', STR_PAD_LEFT); ?> - SupplyNet</title>
    <!-- Fonts -->
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    <!-- Bootstrap CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <!-- FontAwesome -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    
    <style>
        :root {
            --primary-color: #36b9cc;
        }

        body { font-family: 'Inter', sans-serif; background-color: #f8f9fc; padding: 2rem; color: #2d3748; }

        /* Report Invoice Styling - Corporate Standard */
        .report-card { background: #fff; max-width: 900px; margin: 0 auto; box-shadow: 0 10px 30px rgba(0,0,0,0.1); border-radius: 8px; overflow: hidden; border: 1px solid #e2e8f0; }
        .report-header { background: var(--primary-color); color: #fff; padding: 2rem; border-bottom: 5px solid #2c9faf; }
        .report-body { padding: 3rem 2rem; }
        .report-footer { padding: 2rem; background: #f8f9fa; border-top: 1px solid #e2e8f0; font-size: 0.85rem;}
        
        .info-box { border: 1px solid #e2e8f0; border-radius: 8px; padding: 1.5rem; background: #fafafa; }
        .signature-box { border: 2px dashed #cbd5e0; height: 120px; border-radius: 8px; margin-top: 1rem; position: relative; background: #fff; }
        .signature-text { position: absolute; bottom: -25px; left: 0; right: 0; text-align: center; color: #718096; font-size: 0.85rem; font-weight: 600; text-transform: uppercase; }
        
        /* Interactive Print Button */
        .print-float-btn { position: fixed; bottom: 30px; right: 30px; border-radius: 50px; padding: 15px 25px; font-weight: bold; background-color: var(--primary-color); color: white; border: none; box-shadow: 0 4px 15px rgba(54, 185, 204, 0.4); z-index: 1000; transition: transform 0.2s; }
        .print-float-btn:hover { transform: translateY(-3px); color: white; }

        @page {
            size: A4;
            margin: 10mm; /* Slashed margins to maximize paper real-estate */
        }

        @media print {
            body { background: #fff !important; padding: 0 !important; font-size: 10pt !important; margin: 0 !important; max-width: 210mm !important; }
            .print-float-btn { display: none !important; }
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

<button class="print-float-btn" onclick="window.print()">
    <i class="fas fa-print me-2"></i> Print Order Report
</button>

<div class="report-card">
    <div class="report-header d-flex justify-content-between align-items-center flex-column flex-sm-row gap-3">
        <div>
            <h2 class="m-0 text-white"><i class="fas fa-cubes me-2"></i>SupplyNet</h2>
            <p class="m-0 mt-1 opacity-75">Corporate Sales & Manufacturing</p>
        </div>
        <div class="text-sm-end text-center">
            <h3 class="m-0 fw-bold">SALES ORDER REPORT</h3>
            <p class="m-0 mt-1 pb-1">Ref No: #<?php echo str_pad($invoice_data['OrderID'], 6, '0', STR_PAD_LEFT); ?></p>
            <span class="badge bg-white text-primary px-3 py-1 fs-6 text-uppercase">Direct Order Allocation</span>
        </div>
    </div>
    
    <div class="report-body">
        <div class="row mb-5">
            <div class="col-sm-6 mb-4 mb-sm-0">
                <h6 class="text-muted text-uppercase mb-3 fw-bold tracking-wide">Billed & Shipped To</h6>
                <div class="info-box h-100">
                    <h4 class="text-dark mb-2"><?php echo htmlspecialchars($invoice_data['CustomerName']); ?></h4>
                    <p class="mb-1 text-secondary"><i class="fas fa-envelope me-2" style="color:var(--primary-color);"></i> <?php echo htmlspecialchars($invoice_data['CustomerEmail']); ?></p>
                    <p class="mb-0 text-secondary"><i class="fas fa-map-marker-alt me-2" style="color:var(--primary-color);"></i> <?php echo htmlspecialchars($invoice_data['ShippingAddress']); ?></p>
                </div>
            </div>
            <div class="col-sm-6">
                <h6 class="text-muted text-uppercase mb-3 fw-bold tracking-wide">Authorized & Prepared By</h6>
                <div class="info-box h-100">
                    <h5 class="text-dark mb-2"><?php echo htmlspecialchars($salesman_name); ?></h5>
                    <p class="mb-1 text-secondary"><i class="fas fa-id-badge me-2" style="color:var(--primary-color);"></i> Sales Representative</p>
                    <p class="mb-0 text-secondary"><i class="fas fa-calendar-alt me-2" style="color:var(--primary-color);"></i> Target Delivery: <?php echo !empty($invoice_data['RequiredDate']) ? date('M d, Y', strtotime($invoice_data['RequiredDate'])) : 'N/A'; ?></p>
                    <p class="mb-0 mt-1 text-secondary"><i class="fas fa-clock me-2" style="color:var(--primary-color);"></i> Placed: <?php echo date('M d, Y', strtotime($invoice_data['OrderDate'])); ?></p>
                </div>
            </div>
        </div>
        
        <h6 class="text-uppercase mb-3 fw-bold" style="color:var(--primary-color);"><i class="fas fa-clipboard-list me-2"></i>Order Specification</h6>
        <div class="table-responsive mb-5">
            <table class="table table-bordered mb-0">
                <thead class="table-light">
                    <tr>
                        <th style="min-width: 150px;">Item Description</th>
                        <th class="text-center">Rate</th>
                        <th class="text-center">Quantity</th>
                        <th class="text-end">Amount</th>
                    </tr>
                </thead>
                <tbody>
                    <tr>
                        <td class="fw-semibold text-dark"><?php echo htmlspecialchars($invoice_data['ProductName']); ?></td>
                        <td class="text-center">₹<?php echo number_format($invoice_data['UnitPrice'], 2); ?></td>
                        <td class="text-center fw-bold"><?php echo $invoice_data['Quantity']; ?></td>
                        <td class="text-end fw-bold">₹<?php echo number_format($invoice_data['UnitPrice'] * $invoice_data['Quantity'], 2); ?></td>
                    </tr>
                </tbody>
                <tfoot>
                    <tr>
                        <td colspan="3" class="text-end fw-bold pt-3 pb-3">Total Payable Balance:</td>
                        <td class="text-end h4 fw-bold text-success pt-3 pb-3 m-0">₹<?php echo number_format($invoice_data['TotalAmount'], 2); ?></td>
                    </tr>
                </tfoot>
            </table>
        </div>
        
        <div class="row mt-5 pt-4 border-top">
            <div class="col-12 mb-4">
                <h6 class="fw-bold text-dark mb-2"><i class="fas fa-info-circle me-2" style="color:var(--primary-color);"></i>Terms of Invoice Billing & Processing</h6>
                <p class="small text-muted mb-0" style="line-height: 1.6;">
                    This document serves as a verified order invoice bill. The Salesman's signature validates the requested cataloging and agreed billing rates, while the client's signature acts as formal acknowledgment of the order obligations and total payable amount. The corporate warehousing teams will process this order subject to stock availability and logistics timelines.
                </p>
            </div>
        </div>
        
        <div class="row mt-3">
            <div class="col-6">
                <div class="mx-auto ps-md-3" style="max-width: 300px;">
                    <h6 class="fw-bold text-dark text-center mb-0">Authorized Salesman</h6>
                    <div class="signature-box" style="float: none; margin: 1rem auto; width: 100%;">
                        <div class="signature-text">Sales Sign / Stamp</div>
                    </div>
                </div>
            </div>
            <div class="col-6">
                <div class="float-end w-100 pe-md-3" style="max-width: 300px;">
                    <h6 class="fw-bold text-dark text-center mb-0">Client Acknowledgment</h6>
                    <div class="signature-box" style="float: none; margin: 1rem auto; width: 100%;">
                        <div class="signature-text">Customer Sign / Stamp</div>
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <div class="report-footer text-center text-muted">
        &copy; <?php echo date('Y'); ?> SupplyNet Supply Chain Solutions. All Rights Reserved. Generated securely by internal systems.
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
