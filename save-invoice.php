<?php
// save-invoice.php
ini_set('session.save_path', '/tmp');
session_start();
require_once 'includes/config.php';

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    header('Location: index.php');
    exit();
}

// Enable error reporting for debugging
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Get form data with validation
$client_name = trim($_POST['client_name'] ?? '');
$client_address = trim($_POST['client_address'] ?? '');
$client_email = trim($_POST['client_email'] ?? '');
$client_phone = trim($_POST['client_phone'] ?? '');
$bill_name = trim($_POST['bill_name'] ?? $client_name);
$bill_email = trim($_POST['bill_email'] ?? $client_email);
$bill_phone = trim($_POST['bill_phone'] ?? $client_phone);

$offer_type = $_POST['offer_type'] ?? 'none';
$offer_value = floatval($_POST['offer_value'] ?? 0);
$payment_amount = floatval($_POST['payment_amount'] ?? 0);

// Get calculated values - MATCHING YOUR FORM FIELDS
$subtotal = floatval($_POST['subtotal'] ?? 0);
$discount = floatval($_POST['discount'] ?? 0);
$net = floatval($_POST['net'] ?? 0);          // Form: "Net Amount"
$vat = floatval($_POST['vat'] ?? 0);          // Form: "VAT 14%"
$total = floatval($_POST['total'] ?? 0);      // Form: "Total"
$balance = floatval($_POST['balance'] ?? 0);  // Form: "Balance Amount"

// Debug: Show what values we're getting
error_log("Invoice Data: subtotal=$subtotal, discount=$discount, net=$net, vat=$vat, total=$total, balance=$balance");

// Get items data
$items_json = $_POST['items'] ?? '[]';
$items = json_decode($items_json, true);

// Validate required fields
if (empty($client_name)) {
    die("Error: Client name is required");
}

if (empty($client_email)) {
    die("Error: Client email is required");
}

if (empty($bill_name)) {
    die("Error: Bill to name is required");
}

if (empty($bill_email)) {
    die("Error: Bill to email is required");
}

if (!is_array($items) || count($items) === 0) {
    die("Error: At least one invoice item is required");
}

// Generate invoice number
function generateInvoiceNumber($conn) {
    $year = date('Y');
    $month = date('m');
    $query = "SELECT MAX(id) as last_id FROM invoices WHERE YEAR(created_at) = ? AND MONTH(created_at) = ?";
    $stmt = $conn->prepare($query);
    $stmt->bind_param("ss", $year, $month);
    $stmt->execute();
    $result = $stmt->get_result();
    $row = $result->fetch_assoc();
    
    $nextNumber = ($row['last_id'] ?? 0) + 1;
    return sprintf("INV-%s%s-%03d", $year, $month, $nextNumber);
}

$invoice_number = generateInvoiceNumber($conn);
$issue_date = date('Y-m-d');
$due_date = date('Y-m-d', strtotime('+30 days'));

// Start transaction
$conn->begin_transaction();

try {
    // 1. Save or get client
    $client_id = null;
    
    // Check if client exists by email
    $stmt = $conn->prepare("SELECT id FROM clients WHERE email = ?");
    $stmt->bind_param("s", $client_email);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows > 0) {
        $client = $result->fetch_assoc();
        $client_id = $client['id'];
        
        // Update client info
        $update_stmt = $conn->prepare("UPDATE clients SET name = ?, phone = ?, address = ? WHERE id = ?");
        $update_stmt->bind_param("sssi", $client_name, $client_phone, $client_address, $client_id);
        $update_stmt->execute();
    } else {
        // Create new client
        $stmt = $conn->prepare("INSERT INTO clients (name, email, phone, address, created_by) VALUES (?, ?, ?, ?, ?)");
        $stmt->bind_param("ssssi", $client_name, $client_email, $client_phone, $client_address, $_SESSION['user_id']);
        
        if (!$stmt->execute()) {
            throw new Exception("Failed to save client: " . $stmt->error);
        }
        $client_id = $conn->insert_id;
    }
    
    // 2. Save invoice - Using 'net' column (matches your form's "Net Amount")
    $stmt = $conn->prepare("INSERT INTO invoices (
        invoice_number, 
        user_id, 
        client_id, 
        issue_date, 
        due_date, 
        subtotal, 
        discount, 
        net,
        vat, 
        total, 
        payment_amount, 
        balance, 
        offer_type, 
        offer_value
    ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
    
    // Bind parameters - ORDER MATTERS!
    $stmt->bind_param("siissdddddddss", 
        $invoice_number,            // 1
        $_SESSION['user_id'],       // 2
        $client_id,                 // 3
        $issue_date,                // 4
        $due_date,                  // 5
        $subtotal,                  // 6
        $discount,                  // 7
        $net,                       // 8 - "Net Amount" from form
        $vat,                       // 9 - "VAT 14%" from form
        $total,                     // 10 - "Total" from form
        $payment_amount,            // 11
        $balance,                   // 12 - "Balance Amount" from form
        $offer_type,                // 13
        $offer_value                // 14
    );
    
    if (!$stmt->execute()) {
        throw new Exception("Failed to save invoice: " . $stmt->error);
    }
    
    $invoice_id = $conn->insert_id;
    
    // 3. Save invoice items
    $stmt = $conn->prepare("INSERT INTO invoice_items (invoice_id, item_type, description, quantity, unit_price, total) VALUES (?, ?, ?, ?, ?, ?)");
    
    foreach ($items as $item) {
        $item_type = $item['type'] ?? 'positive';
        $description = $item['description'] ?? '';
        $quantity = floatval($item['quantity'] ?? 0);
        $unit_price = floatval($item['unitPrice'] ?? 0);
        $item_total = floatval($item['total'] ?? 0);
        
        if (empty($description)) {
            continue; // Skip empty items
        }
        
        $stmt->bind_param("issddd", $invoice_id, $item_type, $description, $quantity, $unit_price, $item_total);
        if (!$stmt->execute()) {
            throw new Exception("Failed to save invoice item: " . $stmt->error);
        }
    }
    
    // Commit transaction
    $conn->commit();
    
    // Store bill info in session for view-invoice.php
    $_SESSION['last_bill_info'] = [
        'name' => $bill_name,
        'email' => $bill_email,
        'phone' => $bill_phone
    ];
    
    // Debug success
    error_log("Invoice saved successfully: ID=$invoice_id, Number=$invoice_number");
    
    // Redirect to view invoice
    header("Location: view-invoice.php?id=" . $invoice_id);
    exit();
    
} catch (Exception $e) {
    // Rollback on error
    $conn->rollback();
    
    // Log error
    error_log("Invoice save error: " . $e->getMessage());
    
    // Show detailed error
    die("Error saving invoice: " . $e->getMessage() . 
        "<br><br>Values being saved:<br>" .
        "Subtotal: $subtotal<br>" .
        "Discount: $discount<br>" .
        "Net: $net<br>" .
        "VAT: $vat<br>" .
        "Total: $total<br>" .
        "Balance: $balance");
}
?>