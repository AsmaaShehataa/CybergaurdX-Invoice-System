<?php
// update-status.php - Update invoice status
ini_set('session.save_path', '/tmp');
session_start();
require_once 'includes/config.php';
require_once 'includes/auth.php';

requireLogin();

$invoice_id = $_GET['id'] ?? 0;
$new_status = $_GET['status'] ?? '';

// Validate status
$allowed_statuses = ['draft', 'sent', 'paid', 'cancelled'];
if (!in_array($new_status, $allowed_statuses)) {
    die("Invalid status");
}

// Check if user has permission to update this invoice
$user_id = $_SESSION['user_id'];
$role = $_SESSION['role'];

// Get invoice to check ownership
$check_stmt = $conn->prepare("SELECT user_id FROM invoices WHERE id = ?");
$check_stmt->bind_param("i", $invoice_id);
$check_stmt->execute();
$check_result = $check_stmt->get_result();

if ($check_result->num_rows === 0) {
    die("Invoice not found");
}

$invoice = $check_result->fetch_assoc();

// Permission check: Admin or invoice owner
if ($role !== 'admin' && $invoice['user_id'] != $user_id) {
    die("You don't have permission to update this invoice");
}

// Update status
$update_stmt = $conn->prepare("UPDATE invoices SET status = ? WHERE id = ?");
$update_stmt->bind_param("si", $new_status, $invoice_id);

if ($update_stmt->execute()) {
    $_SESSION['message'] = "✅ Invoice status updated to '$new_status'";
} else {
    $_SESSION['message'] = "❌ Error updating invoice status: " . $conn->error;
}

// Redirect back to invoices page
header('Location: invoices.php');
exit();
?>