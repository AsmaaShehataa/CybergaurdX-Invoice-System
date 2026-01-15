<?php
// save-invoice.php - FIXED VERSION (from old working project)
require_once dirname(__DIR__) . '/includes/init.php';
startAppSession();
// require_once dirname(__DIR__) . '/includes/config.php';

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    header('Location: index.php');
    exit();
}

// Enable error reporting
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Get form data
$client_name = trim($_POST['client_name'] ?? '');
$client_address = trim($_POST['client_address'] ?? '');
$client_email = trim($_POST['client_email'] ?? '');
$client_phone = trim($_POST['client_phone'] ?? '');
$bill_name = trim($_POST['bill_name'] ?? $client_name);
$bill_email = trim($_POST['bill_email'] ?? $client_email);
$bill_phone = trim($_POST['bill_phone'] ?? $client_phone);

// Get and CLEAN offer_type
$offer_type_raw = $_POST['offer_type'] ?? 'none';
$offer_type = cleanForEnum($offer_type_raw, ['none', 'percent', 'fixed']);
$offer_value = floatval($_POST['offer_value'] ?? 0);
$payment_amount = floatval($_POST['payment_amount'] ?? 0);

// Get calculated values
$subtotal = floatval($_POST['subtotal'] ?? 0);
$discount = floatval($_POST['discount'] ?? 0);
$net = floatval($_POST['net'] ?? 0);
$vat = floatval($_POST['vat'] ?? 0);
$total = floatval($_POST['total'] ?? 0);
$balance = floatval($_POST['balance'] ?? 0);

// Get items data
$items_json = $_POST['items'] ?? '[]';
$items = json_decode($items_json, true);

// Validate
if (empty($client_name) || empty($client_email) || empty($bill_name) || empty($bill_email)) {
    die("Error: Required fields missing");
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
$status = 'draft';

// Clean string for ENUM column
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

// Start transaction
$conn->begin_transaction();

try {
    // 1. Save or get client (THIS WAS MISSING!)
    $client_id = null;
    
    $stmt = $conn->prepare("SELECT id FROM clients WHERE email = ?");
    $stmt->bind_param("s", $client_email);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows > 0) {
        $client = $result->fetch_assoc();
        $client_id = $client['id'];
        
        $update_stmt = $conn->prepare("UPDATE clients SET name = ?, phone = ?, address = ? WHERE id = ?");
        $update_stmt->bind_param("sssi", $client_name, $client_phone, $client_address, $client_id);
        $update_stmt->execute();
    } else {
        $stmt = $conn->prepare("INSERT INTO clients (name, email, phone, address, created_by) VALUES (?, ?, ?, ?, ?)");
        $stmt->bind_param("ssssi", $client_name, $client_email, $client_phone, $client_address, $_SESSION['user_id']);
        
        if (!$stmt->execute()) {
            throw new Exception("Failed to save client: " . $stmt->error);
        }
        $client_id = $conn->insert_id;
    }
    
    // 2. Save invoice - USE DIRECT SQL (from old working version)
    $notes = '';
    $terms_conditions = '';
    
    // Build direct SQL query (this avoids the bind_param issue)
    $sql = sprintf("INSERT INTO invoices (
        invoice_number, user_id, client_id, issue_date, due_date, 
        subtotal, discount, net, vat, total, status,
        payment_amount, balance, offer_type, offer_value,
        notes, terms_conditions
    ) VALUES (
        '%s', %d, %d, '%s', '%s', 
        %.2f, %.2f, %.2f, %.2f, %.2f, '%s',
        %.2f, %.2f, '%s', %.2f,
        '%s', '%s'
    )",
        $conn->real_escape_string($invoice_number),
        $_SESSION['user_id'],
        $client_id,
        $conn->real_escape_string($issue_date),
        $conn->real_escape_string($due_date),
        $subtotal,
        $discount,
        $net,
        $vat,
        $total,
        $conn->real_escape_string($status),
        $payment_amount,
        $balance,
        $conn->real_escape_string($offer_type),
        $offer_value,
        $conn->real_escape_string($notes),
        $conn->real_escape_string($terms_conditions)
    );
    
    if (!$conn->query($sql)) {
        throw new Exception("Failed to save invoice: " . $conn->error);
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
        
        if (empty($description)) continue;
        
        $stmt->bind_param("issddd", $invoice_id, $item_type, $description, $quantity, $unit_price, $item_total);
        if (!$stmt->execute()) {
            throw new Exception("Failed to save invoice item: " . $stmt->error);
        }
    }
    
    // Commit transaction
    $conn->commit();
    
    // Store bill info
    $_SESSION['last_bill_info'] = [
        'name' => $bill_name,
        'email' => $bill_email,
        'phone' => $bill_phone
    ];
    
    // Success! Redirect to view invoice
    header("Location: view-invoice.php?id=" . $invoice_id);
    exit();
    
} catch (Exception $e) {
    $conn->rollback();
    
    // Show friendly error
    echo "<div style='padding: 20px; background: #fee; border: 2px solid red; border-radius: 10px; margin: 20px;'>";
    echo "<h3>❌ Error Saving Invoice</h3>";
    echo "<p><strong>Message:</strong> " . htmlspecialchars($e->getMessage()) . "</p>";
    
    // Debug info
    echo "<div style='background: #fff; padding: 15px; margin: 15px 0; border-radius: 5px;'>";
    echo "<h4>Debug Information:</h4>";
    echo "Invoice Number: " . htmlspecialchars($invoice_number ?? 'N/A') . "<br>";
    echo "Client ID: " . ($client_id ?? 'N/A') . "<br>";
    echo "Client Name: " . htmlspecialchars($client_name) . "<br>";
    echo "Client Email: " . htmlspecialchars($client_email) . "<br>";
    echo "User ID: " . $_SESSION['user_id'] . "<br>";
    echo "Subtotal: $subtotal<br>";
    echo "Total: $total<br>";
    echo "Status: $status<br>";
    echo "Offer Type: '" . htmlspecialchars($offer_type) . "'<br>";
    echo "Offer Value: $offer_value<br>";
    echo "</div>";
    
    echo "<p><a href='create-invoice.php' style='color: blue;'>← Go back and try again</a></p>";
    echo "</div>";
    exit();
}
?>