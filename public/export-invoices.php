<?php
// export-invoices.php - Simple CSV export
require_once dirname(__DIR__) . '/includes/session-config.php';
startAppSession();
require_once dirname(__DIR__) . '/includes/config.php';

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    die("Access denied");
}

// Simple CSV export
header('Content-Type: text/csv');
header('Content-Disposition: attachment; filename="invoices_export_' . date('Y-m-d') . '.csv"');

$output = fopen('php://output', 'w');
fputcsv($output, ['Invoice #', 'Client', 'Date', 'Amount', 'Status', 'Sales Rep']);

$query = "SELECT i.invoice_number, c.name as client_name, i.issue_date, i.total, i.status, u.full_name as sales_person 
          FROM invoices i 
          JOIN clients c ON i.client_id = c.id 
          JOIN users u ON i.user_id = u.id 
          ORDER BY i.created_at DESC";

$result = $conn->query($query);
while ($invoice = $result->fetch_assoc()) {
    fputcsv($output, [
        $invoice['invoice_number'],
        $invoice['client_name'],
        $invoice['issue_date'],
        $invoice['total'],
        $invoice['status'],
        $invoice['sales_person']
    ]);
}

fclose($output);
exit();
?>