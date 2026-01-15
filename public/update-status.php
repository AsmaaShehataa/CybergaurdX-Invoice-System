<?php
// update-status.php - Update invoice status

// Use central session configuration
require_once dirname(__DIR__) . '/includes/init.php';
startAppSession();

// Check login
if (!isset($_SESSION['user_id'])) {
    header('Location: index.php');
    exit();
}

// Include config
require_once dirname(__DIR__) . '/includes/config.php';

// Get parameters
$invoice_id = $_GET['id'] ?? 0;
$new_status = $_GET['status'] ?? '';

// Validate
if (!$invoice_id || !in_array($new_status, ['draft', 'sent', 'paid', 'cancelled'])) {
    die("Invalid parameters. <a href='dashboard.php'>Go to Dashboard</a>");
}

// Check permission
$user_id = $_SESSION['user_id'];
$is_admin = $_SESSION['role'] === 'admin';

if ($is_admin) {
    $stmt = $conn->prepare("UPDATE invoices SET status = ? WHERE id = ?");
    $stmt->bind_param("si", $new_status, $invoice_id);
} else {
    $stmt = $conn->prepare("UPDATE invoices SET status = ? WHERE id = ? AND user_id = ?");
    $stmt->bind_param("sii", $new_status, $invoice_id, $user_id);
}

if ($stmt->execute()) {
    // Redirect back to view invoice
    header("Location: view-invoice.php?id=$invoice_id&status=updated");
} else {
    die("Failed to update status. <a href='view-invoice.php?id=$invoice_id'>Go back</a>");
}
