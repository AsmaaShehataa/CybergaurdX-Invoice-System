<?php
// edit-invoice.php - CLEAN DESIGN WITH DISCOUNT OPTIONS
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

// Initialize variables
$invoice = null;
$items = [];
$clients = [];
$error = null;
$message = null;

// Get all clients for dropdown
$clients_stmt = $conn->prepare("SELECT id, name, email, phone FROM clients ORDER BY name");
$clients_stmt->execute();
$clients_result = $clients_stmt->get_result();
while ($client = $clients_result->fetch_assoc()) {
    $clients[] = $client;
}

// Get invoice data with permission check
if ($is_admin) {
    $stmt = $conn->prepare("
        SELECT i.*, c.name as client_name, c.email as client_email,
        c.phone as client_phone, c.address as client_address, c.tax_number as client_tax
        FROM invoices i
        LEFT JOIN clients c ON i.client_id = c.id
        WHERE i.id = ?
    ");
    $stmt->bind_param("i", $invoice_id);
} else {
    $stmt = $conn->prepare("
        SELECT i.*, c.name as client_name, c.email as client_email,
        c.phone as client_phone, c.address as client_address, c.tax_number as client_tax
        FROM invoices i
        LEFT JOIN clients c ON i.client_id = c.id
        WHERE i.id = ? AND i.user_id = ?
    ");
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

// Get invoice items
$items_stmt = $conn->prepare("SELECT * FROM invoice_items WHERE invoice_id = ? ORDER BY id");
$items_stmt->bind_param("i", $invoice_id);
$items_stmt->execute();
$items_result = $items_stmt->get_result();
$items = $items_result->fetch_all(MYSQLI_ASSOC);

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Get form data
    $client_id = $_POST['client_id'] ?? $invoice['client_id'];
    $status = $_POST['status'] ?? $invoice['status'];
    $issue_date = $_POST['issue_date'] ?? $invoice['issue_date'];
    $due_date = $_POST['due_date'] ?? $invoice['due_date'];
    $notes = $_POST['notes'] ?? '';
    $terms_conditions = $_POST['terms_conditions'] ?? '';
    
    // Get discount type and value
    $discount_type = $_POST['discount_type'] ?? 'fixed';
    $discount_value = floatval($_POST['discount_value'] ?? $invoice['discount']);
    $payment_amount = floatval($_POST['payment_amount'] ?? $invoice['payment_amount']);
    $vat_rate = 14; // 14% VAT
    
    // Get items data
    $item_descriptions = $_POST['item_description'] ?? [];
    $item_quantities = $_POST['item_quantity'] ?? [];
    $item_prices = $_POST['item_price'] ?? [];
    $item_types = $_POST['item_type'] ?? [];
    
    // Start transaction
    $conn->begin_transaction();
    
    try {
        // Delete old items
        $delete_stmt = $conn->prepare("DELETE FROM invoice_items WHERE invoice_id = ?");
        $delete_stmt->bind_param("i", $invoice_id);
        $delete_stmt->execute();
        
        // Calculate new totals
        $subtotal = 0;
        $new_items = [];
        
        // Process items
        if (!empty($item_descriptions)) {
            for ($i = 0; $i < count($item_descriptions); $i++) {
                if (!empty($item_descriptions[$i])) {
                    $description = $conn->real_escape_string($item_descriptions[$i]);
                    $quantity = floatval($item_quantities[$i] ?? 0);
                    $price = floatval($item_prices[$i] ?? 0);
                    $type = $item_types[$i] ?? 'positive';
                    $total = $quantity * $price;
                    
                    // Adjust total based on type
                    if ($type === 'negative') {
                        $total = -$total;
                    }
                    
                    $subtotal += $total;
                    
                    // Insert new item
                    $item_stmt = $conn->prepare("
                        INSERT INTO invoice_items (invoice_id, description, quantity, unit_price, total, item_type)
                        VALUES (?, ?, ?, ?, ?, ?)
                    ");
                    $item_stmt->bind_param("issdds", $invoice_id, $description, $quantity, $price, $total, $type);
                    $item_stmt->execute();
                    
                    $new_items[] = [
                        'description' => $description,
                        'quantity' => $quantity,
                        'unit_price' => $price,
                        'total' => $total,
                        'type' => $type
                    ];
                }
            }
        }
        
        // Calculate discount based on type
        $discount = 0;
        if ($discount_type === 'percentage' && $discount_value > 0) {
            $discount = ($subtotal * $discount_value) / 100;
        } else {
            $discount = $discount_value;
        }
        
        // Calculate financials
        $net = $subtotal - $discount;
        $vat = $net * ($vat_rate / 100);
        $total = $net + $vat;
        $balance = $total - $payment_amount;
        
        // Update invoice
        $update_stmt = $conn->prepare("
            UPDATE invoices SET 
            client_id = ?, 
            status = ?, 
            issue_date = ?, 
            due_date = ?, 
            subtotal = ?, 
            discount = ?, 
            net = ?, 
            vat = ?, 
            total = ?, 
            payment_amount = ?, 
            balance = ?, 
            notes = ?, 
            terms_conditions = ?,
            updated_at = NOW()
            WHERE id = ?
        ");
        
        $update_stmt->bind_param(
            "issddddddddssi", 
            $client_id, 
            $status, 
            $issue_date, 
            $due_date, 
            $subtotal, 
            $discount, 
            $net, 
            $vat, 
            $total, 
            $payment_amount, 
            $balance, 
            $notes, 
            $terms_conditions, 
            $invoice_id
        );
        
        if ($update_stmt->execute()) {
            $conn->commit();
            $_SESSION['message'] = "Invoice updated successfully!";
            header("Location: view-invoice.php?id=$invoice_id");
            exit();
        } else {
            throw new Exception("Error updating invoice: " . $conn->error);
        }
        
    } catch (Exception $e) {
        $conn->rollback();
        $error = $e->getMessage();
    }
}

// Calculate discount type and value from existing discount
$discount_type = 'fixed';
$discount_value = $invoice['discount'];
if ($invoice['subtotal'] > 0) {
    $percentage = ($invoice['discount'] / $invoice['subtotal']) * 100;
    if (abs($percentage - round($percentage)) < 0.01) { // Check if it's a round percentage
        $discount_type = 'percentage';
        $discount_value = round($percentage, 2);
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Edit Invoice - CyberGuardX</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        body { 
            background: linear-gradient(135deg, #f8f9fa 0%, #e9ecef 100%);
            font-family: 'Segoe UI', 'Roboto', 'Helvetica Neue', Arial, sans-serif;
            color: #2d3748;
            min-height: 100vh;
            padding: 20px;
        }
        
        .invoice-container {
            max-width: 1200px;
            margin: 0 auto;
            background: white;
            padding: 30px;
            box-shadow: 0 10px 30px rgba(0, 0, 0, 0.08);
            border-radius: 12px;
            border: 1px solid rgba(0, 0, 0, 0.06);
        }
        
        .header-section {
            background: #2c3e50;
            color: white;
            padding: 20px;
            border-radius: 8px;
            margin-bottom: 30px;
        }
        
        .section-card {
            background: white;
            border-radius: 8px;
            border: 1px solid #e2e8f0;
            padding: 25px;
            margin-bottom: 25px;
        }
        
        .section-title {
            color: #2c3e50;
            font-weight: 700;
            margin-bottom: 20px;
            padding-bottom: 10px;
            border-bottom: 2px solid #e9ecef;
            font-size: 18px;
        }
        
        .form-control, .form-select {
            border-radius: 6px;
            border: 1px solid #e2e8f0;
            padding: 10px 15px;
        }
        
        .form-control:focus, .form-select:focus {
            border-color: #3498db;
            box-shadow: 0 0 0 0.2rem rgba(52, 152, 219, 0.25);
        }
        
        .btn {
            border-radius: 6px;
            padding: 10px 20px;
            font-weight: 600;
        }
        
        .btn-primary {
            background: #2c3e50;
            border: none;
        }
        
        .btn-primary:hover {
            background: #1a252f;
        }
        
        .btn-secondary {
            background: #718096;
            border: none;
            color: white;
        }
        
        .btn-success {
            background: #10b981;
            border: none;
        }
        
        .btn-outline-danger {
            border: 1px solid #ef4444;
            color: #ef4444;
        }
        
        .btn-outline-danger:hover {
            background: #ef4444;
            color: white;
        }
        
                /* UPDATED INVOICE ITEMS CSS - PUT THIS SECTION */
        .item-row {
            background: #f8fafc;
            border-radius: 6px;
            padding: 15px;
            margin-bottom: 15px;
            border: 1px solid #e2e8f0;
            transition: all 0.3s ease;
        }
        
        .item-row:hover {
            background: #f1f5f9;
            border-color: #cbd5e0;
        }
        
        .item-header {
            border-bottom: 1px solid #e2e8f0;
            padding-bottom: 10px;
        }
        
        .item-header input[type="text"] {
            border: none;
            background: transparent;
            font-weight: 600;
            color: #2c3e50;
            padding: 0;
            font-size: 14px;
        }
        
        .item-header input[type="text"]:focus {
            background: white;
            border: 1px solid #e2e8f0;
            padding: 4px 8px;
            border-radius: 4px;
        }
        
        .item-row .row {
            margin-top: 10px;
        }
        
        .item-row .form-control-sm,
        .item-row .form-select-sm {
            padding: 4px 8px;
            font-size: 13px;
        }
        
        .item-row .text-muted {
            font-size: 11px;
            margin-bottom: 2px;
        }
        
        .item-total {
            font-family: 'Courier New', monospace;
            font-size: 13px;
            color: #2c3e50;
            padding-top: 4px;
        }
        
        .badge {
            font-size: 11px;
            padding: 3px 8px;
            font-weight: 600;
        }
        
        .bg-success {
            background-color: #10b981 !important;
        }
        
        .bg-danger {
            background-color: #ef4444 !important;
        }
        
        .delete-item-btn {
            padding: 2px 6px;
            font-size: 12px;
        }
        
        /* For empty state - make description input visible */
        .item-row[data-index="0"] .item-header input[type="text"] {
            background: white;
            border: 1px solid #e2e8f0;
            padding: 4px 8px;
            border-radius: 4px;
            width: 100%;
        }
        /* END OF UPDATED INVOICE ITEMS CSS */
        
        .financial-summary {
            background: #f8fafc;
            border-radius: 8px;
            padding: 20px;
            border: 1px solid #e2e8f0;
        }
        
        .financial-item {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 10px 0;
            border-bottom: 1px solid #e2e8f0;
        }
        
        .financial-item:last-child {
            border-bottom: none;
        }
        
        .financial-item.total {
            font-weight: 700;
            font-size: 16px;
            color: #2c3e50;
            padding-top: 15px;
            margin-top: 10px;
            border-top: 2px solid #2c3e50;
        }
        
        .amount {
            font-family: 'Courier New', monospace;
            font-weight: 600;
        }
        
        .info-box {
            background: #f8fafc;
            border-radius: 8px;
            padding: 20px;
            border: 1px solid #e2e8f0;
            margin-bottom: 20px;
        }
        
        .status-badge {
            padding: 6px 12px;
            border-radius: 20px;
            font-size: 12px;
            font-weight: 600;
            display: inline-block;
        }
        
        .status-draft { background: #f3f4f6; color: #6b7280; }
        .status-sent { background: #dbeafe; color: #1e40af; }
        .status-paid { background: #d1fae5; color: #065f46; }
        .status-cancelled { background: #fee2e2; color: #991b1b; }
        
        label {
            font-weight: 600;
            color: #4a5568;
            margin-bottom: 8px;
            font-size: 14px;
        }
        
        .discount-options {
            display: flex;
            gap: 10px;
            margin-bottom: 15px;
        }
        
        .discount-option {
            flex: 1;
        }
        
        .discount-option .form-check-input:checked + .form-check-label {
            font-weight: 600;
            color: #2c3e50;
        }
        
        @media (max-width: 768px) {
            .invoice-container {
                padding: 15px;
            }
            
            .section-card {
                padding: 15px;
            }
            
            .item-row .row > div {
                margin-bottom: 10px;
            }
            
            .discount-options {
                flex-direction: column;
            }
        }
    </style>
</head>
<body>
    <div class="invoice-container">
        <!-- Header -->
        <div class="header-section">
            <div class="d-flex justify-content-between align-items-center">
                <div>
                    <h4 class="mb-1"><i class="fas fa-edit me-2"></i>Edit Invoice</h4>
                    <p class="mb-0">
                        Invoice: <strong><?php echo htmlspecialchars($invoice['invoice_number']); ?></strong> | 
                        Created by: <?php echo htmlspecialchars($_SESSION['full_name']); ?>
                    </p>
                </div>
                <div>
                    <a href="dashboard.php" class="text-white me-3">← Dashboard</a>
                    <a href="invoices.php" class="text-white">📋 All Invoices</a>
                </div>
            </div>
        </div>

        <?php if ($error): ?>
            <div class="alert alert-danger alert-dismissible fade show mb-4" role="alert">
                <i class="fas fa-exclamation-circle me-2"></i>
                <?php echo htmlspecialchars($error); ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
            </div>
        <?php endif; ?>

        <form method="POST" action="" id="invoiceForm">
            <div class="row">
                <!-- Left Column: Invoice Details -->
                <div class="col-lg-8">
                    <!-- Basic Information -->
                    <div class="section-card">
                        <h5 class="section-title">Basic Information</h5>
                        <div class="row mb-3">
                            <div class="col-md-6">
                                <label class="form-label">Invoice Number</label>
                                <input type="text" class="form-control" value="<?php echo htmlspecialchars($invoice['invoice_number']); ?>" readonly>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label">Status</label>
                                <select name="status" class="form-select">
                                    <option value="draft" <?php echo $invoice['status'] == 'draft' ? 'selected' : ''; ?>>Draft</option>
                                    <option value="sent" <?php echo $invoice['status'] == 'sent' ? 'selected' : ''; ?>>Sent</option>
                                    <option value="paid" <?php echo $invoice['status'] == 'paid' ? 'selected' : ''; ?>>Paid</option>
                                    <option value="cancelled" <?php echo $invoice['status'] == 'cancelled' ? 'selected' : ''; ?>>Cancelled</option>
                                </select>
                            </div>
                        </div>
                        
                        <div class="row mb-3">
                            <div class="col-md-6">
                                <label class="form-label">Issue Date</label>
                                <input type="date" name="issue_date" class="form-control" value="<?php echo $invoice['issue_date']; ?>" required>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label">Due Date</label>
                                <input type="date" name="due_date" class="form-control" value="<?php echo $invoice['due_date']; ?>" required>
                            </div>
                        </div>
                        
                        <div class="mb-3">
                            <label class="form-label">Client</label>
                            <select name="client_id" class="form-select" required>
                                <option value="">Select Client</option>
                                <?php foreach ($clients as $client): ?>
                                    <option value="<?php echo $client['id']; ?>" <?php echo $client['id'] == $invoice['client_id'] ? 'selected' : ''; ?>>
                                        <?php echo htmlspecialchars($client['name']); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    </div>
                    
                    <!-- Invoice Items Section - Updated -->
<div class="section-card">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h5 class="section-title mb-0">Invoice Items</h5>
        <button type="button" class="btn btn-success btn-sm" id="addItem">
            <i class="fas fa-plus me-1"></i>Add Item
        </button>
    </div>
    
    <div id="itemsContainer">
        <?php if (!empty($items)): ?>
            <?php foreach ($items as $index => $item): ?>
                <div class="item-row" data-index="<?php echo $index; ?>">
                    <!-- Item Header with compact info -->
                    <div class="item-header d-flex justify-content-between align-items-center mb-2">
                        <div>
                            <strong><?php echo htmlspecialchars($item['description']); ?></strong>
                        </div>
                        <div class="d-flex align-items-center gap-2">
                            <span class="badge <?php echo $item['item_type'] == 'positive' ? 'bg-success' : 'bg-danger'; ?>">
                                <?php echo $item['item_type'] == 'positive' ? 'Charge' : 'Deduction'; ?>
                            </span>
                            <button type="button" class="btn btn-outline-danger btn-sm delete-item-btn">
                                <i class="fas fa-trash"></i>
                            </button>
                        </div>
                    </div>
                    
                    <!-- Item Details in a compact grid -->
                    <div class="row g-2">
                        <div class="col-md-3">
                            <small class="text-muted d-block">Quantity</small>
                            <input type="number" name="item_quantity[]" class="form-control form-control-sm quantity" 
                                   step="0.01" value="<?php echo $item['quantity']; ?>" required>
                        </div>
                        <div class="col-md-4">
                            <small class="text-muted d-block">Unit Price (EGP)</small>
                            <input type="number" name="item_price[]" class="form-control form-control-sm price" 
                                   step="0.01" value="<?php echo $item['unit_price']; ?>" required>
                        </div>
                        <div class="col-md-3">
                            <small class="text-muted d-block">Type</small>
                            <select name="item_type[]" class="form-select form-select-sm item-type">
                                <option value="positive" <?php echo $item['item_type'] == 'positive' ? 'selected' : ''; ?>>Charge (+)</option>
                                <option value="negative" <?php echo $item['item_type'] == 'negative' ? 'selected' : ''; ?>>Deduction (-)</option>
                            </select>
                        </div>
                        <div class="col-md-2">
                            <small class="text-muted d-block">Total</small>
                            <div class="item-total fw-bold"><?php echo number_format($item['total'], 2); ?> EGP</div>
                        </div>
                    </div>
                    
                    <!-- Hidden description input -->
                    <input type="hidden" name="item_description[]" value="<?php echo htmlspecialchars($item['description']); ?>">
                </div>
            <?php endforeach; ?>
        <?php else: ?>
            <div class="item-row" data-index="0">
                <!-- Item Header with compact info -->
                <div class="item-header d-flex justify-content-between align-items-center mb-2">
                    <div>
                        <input type="text" name="item_description[]" class="form-control form-control-sm" 
                               placeholder="Item description" required>
                    </div>
                    <div class="d-flex align-items-center gap-2">
                        <span class="badge bg-success item-type-badge">Charge</span>
                        <button type="button" class="btn btn-outline-danger btn-sm delete-item-btn">
                            <i class="fas fa-trash"></i>
                        </button>
                    </div>
                </div>
                
                <!-- Item Details in a compact grid -->
                <div class="row g-2">
                    <div class="col-md-3">
                        <small class="text-muted d-block">Quantity</small>
                        <input type="number" name="item_quantity[]" class="form-control form-control-sm quantity" 
                               step="0.01" value="1" required>
                    </div>
                    <div class="col-md-4">
                        <small class="text-muted d-block">Unit Price (EGP)</small>
                        <input type="number" name="item_price[]" class="form-control form-control-sm price" 
                               step="0.01" value="0" required>
                    </div>
                    <div class="col-md-3">
                        <small class="text-muted d-block">Type</small>
                        <select name="item_type[]" class="form-select form-select-sm item-type">
                            <option value="positive">Charge (+)</option>
                            <option value="negative">Deduction (-)</option>
                        </select>
                    </div>
                    <div class="col-md-2">
                        <small class="text-muted d-block">Total</small>
                        <div class="item-total fw-bold">0.00 EGP</div>
                    </div>
                </div>
            </div>
        <?php endif; ?>
    </div>
</div>
                    
                    <!-- Financial Adjustments -->
                    <div class="section-card">
                        <h5 class="section-title">Financial Adjustments</h5>
                        
                        <!-- Discount Options -->
                        <div class="mb-4">
                            <label class="form-label mb-2">Discount Type</label>
                            <div class="discount-options">
                                <div class="form-check discount-option">
                                    <input class="form-check-input" type="radio" name="discount_type" id="discountFixed" value="fixed" <?php echo $discount_type == 'fixed' ? 'checked' : ''; ?>>
                                    <label class="form-check-label" for="discountFixed">
                                        Fixed Amount
                                    </label>
                                </div>
                                <div class="form-check discount-option">
                                    <input class="form-check-input" type="radio" name="discount_type" id="discountPercentage" value="percentage" <?php echo $discount_type == 'percentage' ? 'checked' : ''; ?>>
                                    <label class="form-check-label" for="discountPercentage">
                                        Percentage (%)
                                    </label>
                                </div>
                            </div>
                            
                            <div class="row">
                                <div class="col-md-6">
                                    <label class="form-label">Discount Value</label>
                                    <div class="input-group">
                                        <input type="number" name="discount_value" id="discount_value" class="form-control" 
                                               step="0.01" value="<?php echo $discount_value; ?>" placeholder="0.00">
                                        <span class="input-group-text" id="discountSuffix">EGP</span>
                                    </div>
                                </div>
                                <div class="col-md-6">
                                    <label class="form-label">Payment Received (EGP)</label>
                                    <input type="number" name="payment_amount" class="form-control" step="0.01" 
                                           value="<?php echo $invoice['payment_amount']; ?>" placeholder="0.00">
                                    <small class="text-muted">Amount already paid by client</small>
                                </div>
                            </div>
                        </div>
                        
                        <!-- Notes & Terms -->
                        <div class="mb-3">
                            <label class="form-label">Notes</label>
                            <textarea name="notes" class="form-control" rows="2" placeholder="Add any notes for this invoice..."><?php echo htmlspecialchars($invoice['notes'] ?? ''); ?></textarea>
                        </div>
                        
                    </div>
                </div>
                
                <!-- Right Column: Summary & Actions -->
                <div class="col-lg-4">
                    <!-- Financial Summary -->
                    <div class="section-card">
                        <h5 class="section-title">Financial Summary</h5>
                        <div class="financial-summary">
                            <div class="financial-item">
                                <span>Subtotal:</span>
                                <span class="amount" id="subtotalDisplay"><?php echo number_format($invoice['subtotal'], 2); ?> EGP</span>
                            </div>
                            <div class="financial-item">
                                <span>Discount:</span>
                                <span class="amount text-danger" id="discountDisplay">- <?php echo number_format($invoice['discount'], 2); ?> EGP</span>
                            </div>
                            <div class="financial-item">
                                <span>Net Amount:</span>
                                <span class="amount" id="netDisplay"><?php echo number_format($invoice['net'], 2); ?> EGP</span>
                            </div>
                            <div class="financial-item">
                                <span>VAT (14%):</span>
                                <span class="amount" id="vatDisplay"><?php echo number_format($invoice['vat'], 2); ?> EGP</span>
                            </div>
                            <div class="financial-item total">
                                <span>TOTAL:</span>
                                <span class="amount" id="totalDisplay"><?php echo number_format($invoice['total'], 2); ?> EGP</span>
                            </div>
                            <div class="financial-item">
                                <span>Payment Made:</span>
                                <span class="amount text-success" id="paymentDisplay">- <?php echo number_format($invoice['payment_amount'], 2); ?> EGP</span>
                            </div>
                            <div class="financial-item total">
                                <span>BALANCE DUE:</span>
                                <span class="amount" id="balanceDisplay"><?php echo number_format($invoice['balance'], 2); ?> EGP</span>
                            </div>
                        </div>
                    </div>
                    
                    <!-- Action Buttons -->
                    <div class="section-card">
                        <h5 class="section-title">Actions</h5>
                        <div class="d-grid gap-2 mb-3">
                            <button type="submit" class="btn btn-primary">
                                <i class="fas fa-save me-1"></i>Save Changes
                            </button>
                            <a href="view-invoice.php?id=<?php echo $invoice_id; ?>" class="btn btn-secondary">
                                <i class="fas fa-times me-1"></i>Cancel
                            </a>
                        </div>
                        <div class="d-grid gap-2">
                            <a href="invoices.php" class="btn btn-outline-secondary">
                                <i class="fas fa-arrow-left me-1"></i>Back to List
                            </a>
                            <?php if ($is_admin): ?>
                            <a href="delete-invoice.php?id=<?php echo $invoice_id; ?>" class="btn btn-outline-danger" 
                            onclick="return confirm('Are you sure you want to delete this invoice?')">
                                <i class="fas fa-trash me-1"></i>Delete Invoice
                            </a>
                            <?php endif; ?>
                        </div>
                    </div>
                        </div>
                    </div>
                </div>
            </div>
        </form>
    </div>
    
    <!-- JavaScript for dynamic calculations -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            let itemIndex = <?php echo count($items); ?>;
            
            // Handle discount type change
            function updateDiscountSuffix() {
                const discountFixed = document.getElementById('discountFixed');
                const discountSuffix = document.getElementById('discountSuffix');
                
                if (discountFixed.checked) {
                    discountSuffix.textContent = 'EGP';
                } else {
                    discountSuffix.textContent = '%';
                }
            }
            
            document.querySelectorAll('input[name="discount_type"]').forEach(radio => {
                radio.addEventListener('change', function() {
                    updateDiscountSuffix();
                    calculateTotals();
                });
            });
            

            // Add item button
document.getElementById('addItem').addEventListener('click', function() {
    const itemsContainer = document.getElementById('itemsContainer');
    const newItem = document.createElement('div');
    newItem.className = 'item-row';
    newItem.setAttribute('data-index', itemIndex);
    
    newItem.innerHTML = `
        <!-- Item Header with compact info -->
        <div class="item-header d-flex justify-content-between align-items-center mb-2">
            <div class="flex-grow-1 me-3">
                <input type="text" name="item_description[]" class="form-control form-control-sm" 
                       placeholder="Item description" required>
            </div>
            <div class="d-flex align-items-center gap-2">
                <span class="badge bg-success item-type-badge">Charge</span>
                <button type="button" class="btn btn-outline-danger btn-sm delete-item-btn">
                    <i class="fas fa-trash"></i>
                </button>
            </div>
        </div>
        
        <!-- Item Details in a compact grid -->
        <div class="row g-2">
            <div class="col-md-3">
                <small class="text-muted d-block">Quantity</small>
                <input type="number" name="item_quantity[]" class="form-control form-control-sm quantity" 
                       step="0.01" value="1" required>
            </div>
            <div class="col-md-4">
                <small class="text-muted d-block">Unit Price (EGP)</small>
                <input type="number" name="item_price[]" class="form-control form-control-sm price" 
                       step="0.01" value="0" required>
            </div>
            <div class="col-md-3">
                <small class="text-muted d-block">Type</small>
                <select name="item_type[]" class="form-select form-select-sm item-type">
                    <option value="positive">Charge (+)</option>
                    <option value="negative">Deduction (-)</option>
                </select>
            </div>
            <div class="col-md-2">
                <small class="text-muted d-block">Total</small>
                <div class="item-total fw-bold">0.00 EGP</div>
            </div>
        </div>
    `;
    
    itemsContainer.appendChild(newItem);
    itemIndex++;
    
    // Add event listeners to new item
    addItemEventListeners(newItem);
    calculateTotals();
});

// Remove item button
function addItemEventListeners(itemElement) {
    const deleteBtn = itemElement.querySelector('.delete-item-btn');
    const quantityInput = itemElement.querySelector('.quantity');
    const priceInput = itemElement.querySelector('.price');
    const typeSelect = itemElement.querySelector('.item-type');
    const descriptionInput = itemElement.querySelector('input[name="item_description[]"]');
    const badge = itemElement.querySelector('.item-type-badge');
    
    deleteBtn.addEventListener('click', function() {
        itemElement.remove();
        calculateTotals();
    });
    
    quantityInput.addEventListener('input', calculateTotals);
    priceInput.addEventListener('input', calculateTotals);
    
    typeSelect.addEventListener('change', function() {
        badge.className = 'badge ' + (this.value === 'positive' ? 'bg-success' : 'bg-danger') + ' item-type-badge';
        badge.textContent = this.value === 'positive' ? 'Charge' : 'Deduction';
        calculateTotals();
    });
    
    // Special handling for description input
    if (descriptionInput.value === '') {
        descriptionInput.classList.add('border', 'p-1');
    }
    
    descriptionInput.addEventListener('focus', function() {
        this.classList.add('border', 'p-1', 'bg-white');
        this.classList.remove('border-0', 'bg-transparent', 'p-0');
    });
    
    descriptionInput.addEventListener('blur', function() {
        if (this.value.trim()) {
            this.classList.remove('border', 'p-1', 'bg-white');
            this.classList.add('border-0', 'bg-transparent', 'p-0');
        }
    });
}
            
            // Remove item button
            function addItemEventListeners(itemElement) {
                const deleteBtn = itemElement.querySelector('.delete-item-btn');
                const quantityInput = itemElement.querySelector('.quantity');
                const priceInput = itemElement.querySelector('.price');
                const typeSelect = itemElement.querySelector('.item-type');
                
                deleteBtn.addEventListener('click', function() {
                    itemElement.remove();
                    calculateTotals();
                });
                
                quantityInput.addEventListener('input', calculateTotals);
                priceInput.addEventListener('input', calculateTotals);
                typeSelect.addEventListener('change', function() {
                    const badge = itemElement.querySelector('.item-type-badge');
                    badge.className = 'item-type-badge item-type-' + this.value;
                    badge.textContent = this.value === 'positive' ? 'Charge' : 'Deduction';
                    calculateTotals();
                });
            }
            
            // Calculate totals function
            function calculateTotals() {
                let subtotal = 0;
                const itemRows = document.querySelectorAll('.item-row');
                
                itemRows.forEach(row => {
                    const quantity = parseFloat(row.querySelector('.quantity').value) || 0;
                    const price = parseFloat(row.querySelector('.price').value) || 0;
                    const type = row.querySelector('.item-type').value;
                    let total = quantity * price;
                    
                    if (type === 'negative') {
                        total = -total;
                    }
                    
                    // Update item total display
                    const itemTotalElement = row.querySelector('.item-total');
                    itemTotalElement.textContent = total.toFixed(2) + ' EGP';
                    
                    subtotal += total;
                });
                
                const discountType = document.querySelector('input[name="discount_type"]:checked').value;
                const discountInput = parseFloat(document.getElementById('discount_value').value) || 0;
                const payment = parseFloat(document.querySelector('input[name="payment_amount"]').value) || 0;
                const vatRate = 14; // 14%
                
                // Calculate discount based on type
                let discount = 0;
                if (discountType === 'percentage' && discountInput > 0) {
                    discount = (subtotal * discountInput) / 100;
                } else {
                    discount = discountInput;
                }
                
                const net = subtotal - discount;
                const vat = net * (vatRate / 100);
                const total = net + vat;
                const balance = total - payment;
                
                // Update display elements
                document.getElementById('subtotalDisplay').textContent = subtotal.toFixed(2) + ' EGP';
                document.getElementById('discountDisplay').textContent = '- ' + discount.toFixed(2) + ' EGP';
                document.getElementById('netDisplay').textContent = net.toFixed(2) + ' EGP';
                document.getElementById('vatDisplay').textContent = vat.toFixed(2) + ' EGP';
                document.getElementById('totalDisplay').textContent = total.toFixed(2) + ' EGP';
                document.getElementById('paymentDisplay').textContent = '- ' + payment.toFixed(2) + ' EGP';
                document.getElementById('balanceDisplay').textContent = balance.toFixed(2) + ' EGP';
            }
            
            // Initialize event listeners for existing items
            document.querySelectorAll('.item-row').forEach(item => {
                addItemEventListeners(item);
            });
            
            // Add event listeners to discount and payment inputs
            document.getElementById('discount_value').addEventListener('input', calculateTotals);
            document.querySelector('input[name="payment_amount"]').addEventListener('input', calculateTotals);
            
            // Initial setup
            updateDiscountSuffix();
            calculateTotals();
        });
    </script>
</body>
</html>