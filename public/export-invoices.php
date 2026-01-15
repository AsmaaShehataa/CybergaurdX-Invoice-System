<?php
// export-invoices.php - Simple CSV export
require_once dirname(__DIR__) . '/includes/init.php';
startAppSession();

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    die("Access denied");
}

// Simple CSV export
header('Content-Type: text/csv');
header('Content-Disposition: attachment; filename="invoices_export_' . date('Y-m-d') . '.csv"');

$output = fopen('php://output', 'w');
// Add the escape parameter (backslash is the standard CSV escape character)
fputcsv($output, ['Invoice #', 'Client', 'Date', 'Amount', 'Status', 'Sales Rep'], ',', '"', '\\');

$query = "SELECT i.invoice_number, c.name as client_name, i.issue_date, i.total, i.status, u.full_name as sales_person 
          FROM invoices i 
          JOIN clients c ON i.client_id = c.id 
          JOIN users u ON i.user_id = u.id 
          ORDER BY i.created_at DESC";

$result = $conn->query($query);
while ($invoice = $result->fetch_assoc()) {
    // Add the escape parameter here too
    fputcsv($output, [
        $invoice['invoice_number'],
        $invoice['client_name'],
        $invoice['issue_date'],
        $invoice['total'],
        $invoice['status'],
        $invoice['sales_person']
    ], ',', '"', '\\');
}

fclose($output);
exit();
?>