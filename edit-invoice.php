<?php
// edit-invoice.php - Edit existing invoice
ini_set('session.save_path', '/tmp');
session_start();
require_once 'includes/config.php';
require_once 'includes/auth.php';

requireLogin();

$invoice_id = intval($_GET['id'] ?? 0);
$user_id = $_SESSION['user_id'];
$role = $_SESSION['role'];

if (!$invoice_id) {
    die("Invoice ID is required. <a href='invoices.php'>Go back</a>");
}

// Check permission
$stmt = $conn->prepare("SELECT i.*, c.name as client_name, c.email as client_email, 
                               c.phone as client_phone, c.address as client_address
                        FROM invoices i
                        JOIN clients c ON i.client_id = c.id
                        WHERE i.id = ? AND (i.user_id = ? OR ? = 'admin')");
$stmt->bind_param("iis", $invoice_id, $user_id, $role);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows === 0) {
    die("Invoice not found or you don't have permission to edit it. <a href='invoices.php'>Go back</a>");
}

$invoice = $result->fetch_assoc();

// Get invoice items
$items_stmt = $conn->prepare("SELECT * FROM invoice_items WHERE invoice_id = ? ORDER BY id");
$items_stmt->bind_param("i", $invoice_id);
$items_stmt->execute();
$items_result = $items_stmt->get_result();
$items = $items_result->fetch_all(MYSQLI_ASSOC);

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Get form data
    $client_name = trim($_POST['client_name'] ?? '');
    $client_address = trim($_POST['client_address'] ?? '');
    $client_email = trim($_POST['client_email'] ?? '');
    $client_phone = trim($_POST['client_phone'] ?? '');
    
    // Get invoice data
    $subtotal = floatval($_POST['subtotal'] ?? 0);
    $discount = floatval($_POST['discount'] ?? 0);
    $net = floatval($_POST['net'] ?? 0);
    $vat = floatval($_POST['vat'] ?? 0);
    $total = floatval($_POST['total'] ?? 0);
    $balance = floatval($_POST['balance'] ?? 0);
    $payment_amount = floatval($_POST['payment_amount'] ?? 0);
    
    // Get and CLEAN offer_type
    $offer_type_raw = $_POST['offer_type'] ?? 'none';
    
    // IMPORTANT: Clean the string to prevent ENUM issues
    $offer_type = cleanForEnum($offer_type_raw, ['none', 'percent', 'fixed']);
    $offer_value = floatval($_POST['offer_value'] ?? 0);
    
    // Update client info
    $update_client = $conn->prepare("UPDATE clients SET name = ?, email = ?, phone = ?, address = ? WHERE id = ?");
    $client_id = $invoice['client_id'];
    $update_client->bind_param("ssssi", $client_name, $client_email, $client_phone, $client_address, $client_id);
    $update_client->execute();
    
    // ============================================
    // FIXED: Use DIRECT SQL instead of prepared statement
    // ============================================
    $sql = sprintf("UPDATE invoices SET 
        subtotal = %.2f, 
        discount = %.2f, 
        net = %.2f, 
        vat = %.2f, 
        total = %.2f, 
        payment_amount = %.2f, 
        balance = %.2f, 
        offer_type = '%s', 
        offer_value = %.2f 
        WHERE id = %d",
        $subtotal,
        $discount,
        $net,
        $vat,
        $total,
        $payment_amount,
        $balance,
        $conn->real_escape_string($offer_type),
        $offer_value,
        $invoice_id
    );
    
    if ($conn->query($sql)) {
        $_SESSION['message'] = "✅ Invoice updated successfully!";
        header("Location: view-invoice.php?id=" . $invoice_id);
        exit();
    } else {
        $error = "Error updating invoice: " . $conn->error;
    }
}

/**
 * Clean string for ENUM column
 * Removes invisible characters, ensures ASCII, validates against allowed values
 */
function cleanForEnum($value, $allowed_values) {
    // Convert to UTF-8
    $value = mb_convert_encoding($value, 'UTF-8', 'UTF-8');
    
    // Remove all non-ASCII characters (ENUM only supports ASCII)
    $value = preg_replace('/[^\x20-\x7E]/', '', $value);
    
    // Trim and lowercase
    $value = strtolower(trim($value));
    
    // Validate against allowed values
    if (!in_array($value, $allowed_values)) {
        return $allowed_values[0]; // Return first allowed value (usually 'none')
    }
    
    return $value;
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Edit Invoice - CyberguardX</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        body { background: #f8f9fa; }
        .card { box-shadow: 0 5px 15px rgba(0,0,0,0.1); border: none; }
        .form-section { background: white; padding: 25px; border-radius: 10px; margin-bottom: 25px; }
        .header-gradient { background: linear-gradient(135deg, #4f46e5 0%, #7c3aed 100%); }
    </style>
</head>
<body>
    <nav class="navbar navbar-dark header-gradient mb-4">
        <div class="container">
            <a class="navbar-brand" href="dashboard.php">
                <i class="fas fa-arrow-left me-2"></i> Edit Invoice
            </a>
            <span class="navbar-text">
                Editing: <?php echo htmlspecialchars($invoice['invoice_number']); ?>
            </span>
        </div>
    </nav>

    <div class="container">
        <?php if (isset($error)): ?>
            <div class="alert alert-danger">
                <i class="fas fa-exclamation-triangle me-2"></i>
                <?php echo htmlspecialchars($error); ?>
            </div>
        <?php endif; ?>

        <div class="card mb-4">
            <div class="card-body">
                <form method="POST" action="">
                    <!-- Client Information -->
                    <div class="form-section">
                        <h4 class="mb-4"><i class="fas fa-user me-2"></i> Client Information</h4>
                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label class="form-label">Client Name *</label>
                                <input type="text" class="form-control" name="client_name" 
                                       value="<?php echo htmlspecialchars($invoice['client_name']); ?>" required>
                            </div>
                            <div class="col-md-6 mb-3">
                                <label class="form-label">Client Email *</label>
                                <input type="email" class="form-control" name="client_email" 
                                       value="<?php echo htmlspecialchars($invoice['client_email']); ?>" required>
                            </div>
                        </div>
                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label class="form-label">Client Phone</label>
                                <input type="text" class="form-control" name="client_phone" 
                                       value="<?php echo htmlspecialchars($invoice['client_phone']); ?>">
                            </div>
                            <div class="col-md-6 mb-3">
                                <label class="form-label">Address</label>
                                <textarea class="form-control" name="client_address" rows="2"><?php echo htmlspecialchars($invoice['client_address']); ?></textarea>
                            </div>
                        </div>
                    </div>

                    <!-- Invoice Summary -->
                    <div class="form-section">
                        <h4 class="mb-4"><i class="fas fa-file-invoice-dollar me-2"></i> Invoice Summary</h4>
                        <div class="row">
                            <div class="col-md-3 mb-3">
                                <label class="form-label">Subtotal (EGP)</label>
                                <input type="number" step="0.01" class="form-control" name="subtotal" 
                                       value="<?php echo $invoice['subtotal']; ?>">
                            </div>
                            <div class="col-md-3 mb-3">
                                <label class="form-label">Discount (EGP)</label>
                                <input type="number" step="0.01" class="form-control" name="discount" 
                                       value="<?php echo $invoice['discount']; ?>">
                            </div>
                            <div class="col-md-3 mb-3">
                                <label class="form-label">Net Amount (EGP)</label>
                                <input type="number" step="0.01" class="form-control" name="net" 
                                       value="<?php echo $invoice['net']; ?>">
                            </div>
                            <div class="col-md-3 mb-3">
                                <label class="form-label">VAT (EGP)</label>
                                <input type="number" step="0.01" class="form-control" name="vat" 
                                       value="<?php echo $invoice['vat']; ?>">
                            </div>
                        </div>
                        <div class="row">
                            <div class="col-md-4 mb-3">
                                <label class="form-label">Total (EGP)</label>
                                <input type="number" step="0.01" class="form-control" name="total" 
                                       value="<?php echo $invoice['total']; ?>">
                            </div>
                            <div class="col-md-4 mb-3">
                                <label class="form-label">Payment Made (EGP)</label>
                                <input type="number" step="0.01" class="form-control" name="payment_amount" 
                                       value="<?php echo $invoice['payment_amount']; ?>">
                            </div>
                            <div class="col-md-4 mb-3">
                                <label class="form-label">Balance Due (EGP)</label>
                                <input type="number" step="0.01" class="form-control" name="balance" 
                                       value="<?php echo $invoice['balance']; ?>">
                            </div>
                        </div>
                        
                        <!-- Offer/Discount Type -->
                        <div class="row">
                            <div class="col-md-4 mb-3">
                                <label class="form-label">Discount Type</label>
                                <select class="form-select" name="offer_type" id="offer_type_select">
                                    <option value="none" <?php echo ($invoice['offer_type'] === 'none') ? 'selected' : ''; ?>>No Discount</option>
                                    <option value="percent" <?php echo ($invoice['offer_type'] === 'percent') ? 'selected' : ''; ?>>Percentage %</option>
                                    <option value="fixed" <?php echo ($invoice['offer_type'] === 'fixed') ? 'selected' : ''; ?>>Fixed Amount</option>
                                </select>
                            </div>
                            <div class="col-md-4 mb-3">
                                <label class="form-label">Discount Value</label>
                                <input type="number" step="0.01" class="form-control" name="offer_value" id="offer_value_input"
                                       value="<?php echo $invoice['offer_value']; ?>">
                            </div>
                            <div class="col-md-4 mb-3">
                                <label class="form-label">Current Selection</label>
                                <div class="form-control" style="background: #f8f9fa;">
                                    <span id="current_selection">
                                        <?php echo htmlspecialchars($invoice['offer_type']); ?> = <?php echo $invoice['offer_value']; ?>
                                    </span>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Action Buttons -->
                    <div class="d-flex justify-content-between mt-4">
                        <a href="invoices.php" class="btn btn-secondary">
                            <i class="fas fa-times me-2"></i> Cancel
                        </a>
                        <div>
                            <a href="view-invoice.php?id=<?php echo $invoice_id; ?>" class="btn btn-info me-2">
                                <i class="fas fa-eye me-2"></i> Preview
                            </a>
                            <button type="submit" class="btn btn-success">
                                <i class="fas fa-save me-2"></i> Save Changes
                            </button>
                        </div>
                    </div>
                </form>
            </div>
        </div>

        <!-- Current Items (Read-only) -->
        <div class="card">
            <div class="card-body">
                <h5 class="card-title"><i class="fas fa-list me-2"></i> Current Invoice Items</h5>
                <p class="text-muted">To edit items, please delete and recreate the invoice with correct items.</p>
                
                <table class="table table-sm">
                    <thead>
                        <tr>
                            <th>Description</th>
                            <th>Qty</th>
                            <th>Unit Price</th>
                            <th>Total</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($items as $item): ?>
                        <tr>
                            <td><?php echo htmlspecialchars($item['description']); ?></td>
                            <td><?php echo number_format($item['quantity'], 2); ?></td>
                            <td><?php echo number_format($item['unit_price'], 2); ?> EGP</td>
                            <td><?php echo number_format($item['total'], 2); ?> EGP</td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
    <script>
    // Update current selection display
    document.getElementById('offer_type_select').addEventListener('change', updateSelection);
    document.getElementById('offer_value_input').addEventListener('input', updateSelection);
    
    function updateSelection() {
        var type = document.getElementById('offer_type_select').value;
        var value = document.getElementById('offer_value_input').value;
        document.getElementById('current_selection').textContent = type + ' = ' + value;
    }
    
    // Initialize
    updateSelection();
    </script>
</body>
</html>