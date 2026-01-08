<?php
// invoices.php
ini_set('session.save_path', '/tmp');
session_start();
require_once 'includes/config.php';

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    header('Location: index.php');
    exit();
}

// Determine which invoices to show (all for admin, only user's for sales)
$user_id = $_SESSION['user_id'];
$role = $_SESSION['role'];

if ($role === 'admin') {
    $query = "SELECT i.*, c.name as client_name, u.full_name as sales_person 
              FROM invoices i 
              JOIN clients c ON i.client_id = c.id 
              JOIN users u ON i.user_id = u.id 
              ORDER BY i.created_at DESC";
    $stmt = $conn->prepare($query);
} else {
    $query = "SELECT i.*, c.name as client_name, u.full_name as sales_person 
              FROM invoices i 
              JOIN clients c ON i.client_id = c.id 
              JOIN users u ON i.user_id = u.id 
              WHERE i.user_id = ? 
              ORDER BY i.created_at DESC";
    $stmt = $conn->prepare($query);
    $stmt->bind_param("i", $user_id);
}

$stmt->execute();
$result = $stmt->get_result();
?>

<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>View Invoices - CyberguardX</title>
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; font-family: -apple-system, sans-serif; }
        body { background: #f5f5f5; }
        .header { background: linear-gradient(135deg, #4f46e5 0%, #7c3aed 100%); color: white; padding: 20px; }
        .container { max-width: 1200px; margin: 0 auto; padding: 20px; }
        .card { background: white; padding: 30px; border-radius: 10px; margin-bottom: 20px; box-shadow: 0 5px 15px rgba(0,0,0,0.1); }
        table { width: 100%; border-collapse: collapse; margin: 20px 0; }
        th, td { padding: 12px; text-align: left; border-bottom: 1px solid #ddd; }
        th { background: #f8f9fa; font-weight: 600; }
        tr:hover { background: #f9fafb; }
        .btn { padding: 8px 15px; border: none; border-radius: 5px; cursor: pointer; text-decoration: none; display: inline-block; }
        .btn-primary { background: #4f46e5; color: white; }
        .btn-success { background: #10b981; color: white; }
        .btn-danger { background: #ef4444; color: white; }
        .invoice-status { padding: 4px 8px; border-radius: 4px; font-size: 12px; font-weight: 600; }
        .status-draft { background: #f3f4f6; color: #6b7280; }
        .status-sent { background: #dbeafe; color: #1e40af; }
        .status-paid { background: #d1fae5; color: #065f46; }
        .status-cancelled { background: #fee2e2; color: #991b1b; }
    </style>
</head>
<body>
    <div class="header">
        <div class="container">
            <div style="display: flex; justify-content: space-between; align-items: center;">
                <h1>📄 Invoices</h1>
                <div>
                    <a href="dashboard.php" class="btn" style="background: #6b7280; color: white; margin-right: 10px;">← Dashboard</a>
                    <a href="create-invoice.php" class="btn btn-success">➕ New Invoice</a>
                </div>
            </div>
        </div>
    </div>
    
    <div class="container">
        <div class="card">
            <h2>All Invoices</h2>
            <?php if ($result->num_rows > 0): ?>
                <table>
                    <thead>
                        <tr>
                            <th>Invoice #</th>
                            <th>Client</th>
                            <th>Date</th>
                            <th>Amount</th>
                            <th>Status</th>
                            <th>Created By</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php while ($invoice = $result->fetch_assoc()): ?>
                        <tr>
                            <td><strong><?php echo htmlspecialchars($invoice['invoice_number']); ?></strong></td>
                            <td><?php echo htmlspecialchars($invoice['client_name']); ?></td>
                            <td><?php echo date('M d, Y', strtotime($invoice['issue_date'])); ?></td>
                            <td><?php echo number_format($invoice['total'], 2); ?> EGP</td>
                            <td>
                                <span class="invoice-status status-<?php echo $invoice['status'] ?? 'draft'; ?>">
                                    <?php echo ucfirst($invoice['status'] ?? 'draft'); ?>
                                </span>
                            </td>
                            <td><?php echo htmlspecialchars($invoice['sales_person']); ?></td>
                            <td>
                                <a href="view-invoice.php?id=<?php echo $invoice['id']; ?>" class="btn btn-primary">View</a>
                                <a href="javascript:void(0)" class="btn" style="background: #6b7280; color: white;">Edit</a>
                                <?php if ($role === 'admin'): ?>
                                <a href="javascript:void(0)" class="btn btn-danger">Delete</a>
                                <?php endif; ?>
                            </td>
                        </tr>
                        <?php endwhile; ?>
                    </tbody>
                </table>
                
                <p style="margin-top: 20px; color: #6b7280;">
                    Total invoices: <?php echo $result->num_rows; ?>
                </p>
            <?php else: ?>
                <div style="text-align: center; padding: 40px;">
                    <h3 style="color: #6b7280; margin-bottom: 20px;">No invoices found</h3>
                    <p style="margin-bottom: 20px;">You haven't created any invoices yet.</p>
                    <a href="create-invoice.php" class="btn btn-success">Create Your First Invoice</a>
                </div>
            <?php endif; ?>
        </div>
    </div>
</body>
</html>