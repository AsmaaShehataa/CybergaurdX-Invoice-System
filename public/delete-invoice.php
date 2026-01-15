<?php
// delete-invoice.php - DELETE INVOICE WITH CONFIRMATION
require_once dirname(__DIR__) . '/includes/init.php';
startAppSession();
//require_once dirname(__DIR__) . '/includes/config.php';

// Check login
if (!isset($_SESSION['user_id'])) {
    header('Location: index.php');
    exit();
}

$user_id = $_SESSION['user_id'];
$is_admin = $_SESSION['role'] === 'admin';

// Get invoice ID
$invoice_id = $_GET['id'] ?? 0;
if (!$invoice_id) {
    $_SESSION['error'] = "Invoice ID is required";
    header('Location: invoices.php');
    exit();
}

// Check if this is a confirmation request
$confirmed = isset($_GET['confirm']) && $_GET['confirm'] == 'yes';

if ($confirmed) {
    // Start transaction
    $conn->begin_transaction();
    try {
        // First, check permission
        if ($is_admin) {
            $check_stmt = $conn->prepare("SELECT invoice_number FROM invoices WHERE id = ?");
            $check_stmt->bind_param("i", $invoice_id);
        } else {
            $check_stmt = $conn->prepare("SELECT invoice_number FROM invoices WHERE id = ? AND user_id = ?");
            $check_stmt->bind_param("ii", $invoice_id, $user_id);
        }
        
        $check_stmt->execute();
        $check_result = $check_stmt->get_result();
        
        if ($check_result->num_rows === 0) {
            throw new Exception("Invoice not found or no permission to delete");
        }
        
        $invoice_data = $check_result->fetch_assoc();
        $invoice_number = $invoice_data['invoice_number'];
        
        // Delete invoice items first (foreign key constraint)
        $stmt1 = $conn->prepare("DELETE FROM invoice_items WHERE invoice_id = ?");
        $stmt1->bind_param("i", $invoice_id);
        $stmt1->execute();
        
        // Delete the invoice
        $stmt2 = $conn->prepare("DELETE FROM invoices WHERE id = ?");
        $stmt2->bind_param("i", $invoice_id);
        $stmt2->execute();
        
        $conn->commit();
        
        $_SESSION['message'] = "Invoice #$invoice_number deleted successfully!";
        header('Location: invoices.php');
        exit();
        
    } catch (Exception $e) {
        $conn->rollback();
        $_SESSION['error'] = "Error deleting invoice: " . $e->getMessage();
        header("Location: view-invoice.php?id=$invoice_id");
        exit();
    }
} else {
    // Show confirmation page
    // Get invoice details for confirmation
    if ($is_admin) {
        $stmt = $conn->prepare("SELECT invoice_number FROM invoices WHERE id = ?");
        $stmt->bind_param("i", $invoice_id);
    } else {
        $stmt = $conn->prepare("SELECT invoice_number FROM invoices WHERE id = ? AND user_id = ?");
        $stmt->bind_param("ii", $invoice_id, $user_id);
    }
    
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows === 0) {
        $_SESSION['error'] = "Invoice not found or no permission";
        header('Location: invoices.php');
        exit();
    }
    
    $invoice = $result->fetch_assoc();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Delete Invoice - CyberGuardX</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        body { background: #f8f9fa; }
        .confirmation-box {
            max-width: 600px;
            margin: 50px auto;
            border-radius: 10px;
            box-shadow: 0 5px 15px rgba(0,0,0,0.1);
        }
        .warning-icon {
            font-size: 48px;
            color: #dc3545;
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="card confirmation-box">
            <div class="card-header bg-danger text-white">
                <h4 class="mb-0"><i class="fas fa-exclamation-triangle me-2"></i>Delete Invoice Confirmation</h4>
            </div>
            <div class="card-body text-center">
                <div class="warning-icon mb-3">
                    <i class="fas fa-trash-alt"></i>
                </div>
                <h4>Are you sure you want to delete this invoice?</h4>
                <p class="text-muted mb-4">
                    Invoice Number: <strong><?php echo htmlspecialchars($invoice['invoice_number']); ?></strong><br>
                    This action cannot be undone. All invoice data will be permanently deleted.
                </p>
                
                <div class="d-flex justify-content-center gap-3">
                    <a href="delete-invoice.php?id=<?php echo $invoice_id; ?>&confirm=yes" class="btn btn-danger btn-lg">
                        <i class="fas fa-check me-1"></i> Yes, Delete Permanently
                    </a>
                    <a href="view-invoice.php?id=<?php echo $invoice_id; ?>" class="btn btn-secondary btn-lg">
                        <i class="fas fa-times me-1"></i> No, Go Back
                    </a>
                </div>
                
                <div class="mt-4 text-start">
                    <h6>What will be deleted:</h6>
                    <ul class="text-muted">
                        <li>Invoice record</li>
                        <li>All invoice items</li>
                        <li>Payment history for this invoice</li>
                    </ul>
                    <div class="alert alert-warning">
                        <strong>Note:</strong> Client information will NOT be deleted from the clients table.
                    </div>
                </div>
            </div>
            <div class="card-footer text-center">
                <a href="invoices.php" class="btn btn-outline-secondary">
                    <i class="fas fa-arrow-left me-1"></i> Back to Invoices
                </a>
            </div>
        </div>
    </div>
    
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
<?php } ?>