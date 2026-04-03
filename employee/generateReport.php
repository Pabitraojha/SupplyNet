<?php
session_start();
require_once '../config/db.php';

if (!isset($_SESSION['UserID']) || strtolower($_SESSION['Role']) !== 'employee') {
    die("Unauthorized access.");
}

$display_name = $_SESSION['UserName'] ?? 'Employee';

// Search Filter
$search_query = "";
$search_sql = "";
if (isset($_GET['search']) && !empty(trim($_GET['search']))) {
    $search_query = trim($_GET['search']);
    $escaped_search = "%" . $conn->real_escape_string($search_query) . "%";
    $search_sql = " AND (o.OrderID LIKE '$escaped_search' OR u.UserName LIKE '$escaped_search' OR o.OrderStatus LIKE '$escaped_search') ";
}

// Fetch all orders with Total Quantity
$orders = [];
$res = $conn->query("
    SELECT o.OrderID, o.OrderDate, o.TotalAmount, o.OrderStatus, o.RequiredDate, u.UserName as CustomerName, 
           IFNULL(SUM(od.Quantity), 0) as TotalQuantity
    FROM Orders o
    JOIN Users u ON o.UserID = u.UserID
    LEFT JOIN OrderDetails od ON o.OrderID = od.OrderID
    WHERE o.markasdeleted = 0 AND o.OrderStatus = 'pending' $search_sql
    GROUP BY o.OrderID
    ORDER BY o.OrderDate DESC
");
if ($res) {
    while($row = $res->fetch_assoc()) {
        $orders[] = $row;
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Pending Orders Report - SupplyNet</title>
    <!-- Fonts -->
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    <!-- Bootstrap CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <!-- FontAwesome -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    
    <style>
        :root {
            --primary-color: #1cc88a;
            --secondary-color: #f8f9fc;
            --dark-color: #1f2937;
        }

        body { font-family: 'Inter', sans-serif; background-color: #f8f9fc; padding: 2rem; color: #2d3748; }
        
        /* Report Print Layout */
        .report-card { background: #fff; max-width: 1000px; margin: 0 auto; box-shadow: 0 10px 30px rgba(0,0,0,0.1); border-radius: 8px; overflow: hidden; border: 1px solid #e2e8f0; }
        .report-header { background: var(--primary-color); color: #fff; padding: 2rem; border-bottom: 5px solid #13855c; }
        .report-body { padding: 3rem 2rem; }
        .report-footer { padding: 2rem; background: #f8f9fa; border-top: 1px solid #e2e8f0; text-align: center; color: #6c757d; font-size: 0.9rem;}
        
        .signature-box { border: 2px dashed #cbd5e0; height: 100px; border-radius: 8px; margin-top: 1rem; position: relative; background: #fff; width: 250px; float: right; }
        .signature-text { position: absolute; bottom: -25px; left: 0; right: 0; text-align: center; color: #718096; font-size: 0.85rem; font-weight: 600; text-transform: uppercase; }
        
        /* Interactive Print Button */
        .print-float-btn { position: fixed; bottom: 30px; right: 30px; border-radius: 50px; padding: 15px 25px; font-weight: bold; box-shadow: 0 4px 15px rgba(28, 200, 138, 0.4); z-index: 1000; transition: transform 0.2s; }
        .print-float-btn:hover { transform: translateY(-3px); }

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
            
            .badge-status { border: 1px solid #6c757d; color: #2d3748 !important; background: transparent !important; }
            .signature-box, tr { page-break-inside: avoid; }
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

<button class="btn btn-success print-float-btn" onclick="window.print()">
    <i class="fas fa-print me-2"></i> Print Log Book
</button>

<div class="report-card">
    <div class="report-header d-flex justify-content-between align-items-center flex-column flex-sm-row gap-3">
        <div>
            <h2 class="m-0 text-white fw-bold"><i class="fas fa-cubes me-2"></i>SupplyNet</h2>
            <p class="m-0 mt-1 opacity-75">Corporate Logistics & Order Fulfillment</p>
        </div>
        <div class="text-sm-end text-center">
            <h3 class="m-0 fw-bold">PENDING ORDERS LOG</h3>
            <p class="m-0 mt-1 pb-1">Date: <?php echo date('M d, Y'); ?></p>
            <span class="badge bg-white text-success px-3 py-1 fs-6 text-uppercase">INTERNAL AUDIT</span>
        </div>
    </div>
    
    <div class="report-body">
        
        <?php if(!empty($search_query)): ?>
        <div class="alert alert-warning py-2 mb-4">
            <i class="fas fa-filter me-2"></i><strong>Active Filter:</strong> Showing results matching "<?php echo htmlspecialchars($search_query); ?>"
        </div>
        <?php endif; ?>

        <div class="table-responsive mb-5">
            <table class="table table-bordered mb-0">
                <thead class="table-light">
                    <tr>
                        <th class="ps-3">Order ID</th>
                        <th>Date & Target</th>
                        <th>Customer Name</th>
                        <th class="text-center">Total Quantity</th>
                        <th class="text-end">Total Amount</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if(empty($orders)): ?>
                        <tr><td colspan="5" class="text-center py-4 text-muted">No pending orders found in the queue.</td></tr>
                    <?php else: ?>
                        <?php 
                        $grand_total_amount = 0;
                        $grand_total_quantity = 0;
                        foreach($orders as $o): 
                            $grand_total_amount += $o['TotalAmount'];
                            $grand_total_quantity += $o['TotalQuantity'];
                        ?>
                            <tr>
                                <td class="ps-3 fw-bold">#<?php echo $o['OrderID']; ?></td>
                                <td>
                                    <div><?php echo date('M d, Y', strtotime($o['OrderDate'])); ?></div>
                                    <?php if(!empty($o['RequiredDate'])): 
                                        $is_overdue = (strtotime($o['RequiredDate']) < strtotime(date('Y-m-d')));
                                    ?>
                                        <div class="small fw-bold <?php echo $is_overdue ? 'text-danger' : 'text-danger'; ?>">
                                            Req: <?php echo date('M d, Y', strtotime($o['RequiredDate'])); ?>
                                        </div>
                                    <?php endif; ?>
                                </td>
                                <td><?php echo htmlspecialchars($o['CustomerName']); ?></td>
                                <td class="text-center fw-bold text-dark"><?php echo $o['TotalQuantity']; ?> Items</td>
                                <td class="text-end fw-bold text-success">₹<?php echo number_format($o['TotalAmount'], 2); ?></td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
                <?php if(!empty($orders)): ?>
                <tfoot class="bg-light">
                    <tr>
                        <td colspan="3" class="text-end fw-bold pt-3 pb-3">Grand Totals Summary:</td>
                        <td class="text-center fw-bold fs-5 text-dark pt-3 pb-3"><?php echo $grand_total_quantity; ?> Items</td>
                        <td class="text-end fw-bold fs-5 text-success pt-3 pb-3">₹<?php echo number_format($grand_total_amount, 2); ?></td>
                    </tr>
                </tfoot>
                <?php endif; ?>
            </table>
        </div>

        <div class="row align-items-center mt-5 pt-3 border-top">
            <div class="col-md-7 mb-4 mb-md-0">
                <h6 class="fw-bold text-success mb-2"><i class="fas fa-info-circle me-1"></i>Terms of Protocol</h6>
                <p class="small text-muted mb-3" style="line-height: 1.6;">
                    This document serves as an authorized snapshot of pending fulfillment queues. All listed orders must be reviewed, authorized, and mapped to active delivery personnel prior to daily corporate dispatch deadlines. Any un-accounted inventory implies a breach of protocol.
                </p>
                <div class="mt-3 bg-light border p-2 rounded small d-inline-block">
                    <span class="text-muted fw-semibold">Internal Audit Trail:</span><br>
                    <strong><i class="fas fa-id-badge text-success me-1"></i> Generated By:</strong> <?php echo htmlspecialchars($display_name); ?>
                </div>
            </div>
            <div class="col-md-5">
                <div class="float-end w-100 ps-md-4">
                    <h6 class="fw-bold text-dark text-center mb-0">Approval Employee / Authority</h6>
                    <div class="signature-box" style="float: none; margin: 1rem auto;">
                        <div class="signature-text">Employee Sign / Stamp Here</div>
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <div class="report-footer text-center text-muted small">
        &copy; <?php echo date('Y'); ?> SupplyNet Supply Chain Solutions. All Rights Reserved. Generated securely by internal systems.
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
