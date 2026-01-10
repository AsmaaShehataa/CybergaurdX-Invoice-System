<?php
// invoices.php - ENHANCED VERSION WITH FIXED DIRECT SQL
ini_set('session.save_path', '/tmp');
session_start();
require_once 'includes/config.php';
require_once 'includes/auth.php';

// Check if user is logged in
requireLogin();
$user_id = $_SESSION['user_id'];
$role = $_SESSION['role'];
$is_admin = ($role === 'admin');

// Get filter parameters
$filter_user_id = $_GET['user_id'] ?? '';
$filter_status = $_GET['status'] ?? '';
$filter_date_from = $_GET['date_from'] ?? '';
$filter_date_to = $_GET['date_to'] ?? '';

// Handle delete request (admin only)
if (isset($_GET['delete_id']) && $is_admin) {
    $delete_id = intval($_GET['delete_id']);
    
    // Start transaction
    $conn->begin_transaction();
    
    try {
        // First delete invoice items (due to foreign key)
        $stmt1 = $conn->prepare("DELETE FROM invoice_items WHERE invoice_id = ?");
        $stmt1->bind_param("i", $delete_id);
        $stmt1->execute();
        
        // Then delete the invoice
        $stmt2 = $conn->prepare("DELETE FROM invoices WHERE id = ?");
        $stmt2->bind_param("i", $delete_id);
        $stmt2->execute();
        
        $conn->commit();
        
        // Redirect with success message
        $_SESSION['message'] = "✅ Invoice deleted successfully!";
        header('Location: invoices.php?' . http_build_query($_GET));
        exit();
        
    } catch (Exception $e) {
        $conn->rollback();
        $error = "Error deleting invoice: " . $e->getMessage();
    }
}

// Handle status messages
$message = $_SESSION['message'] ?? '';
unset($_SESSION['message']);

// Get all sales reps for filter dropdown (admin only) - DIRECT SQL
$sales_reps = [];
if ($is_admin) {
    $sql = "SELECT id, full_name, username FROM users WHERE role = 'sales' ORDER BY full_name";
    $rep_result = $conn->query($sql);
    while ($rep = $rep_result->fetch_assoc()) {
        $sales_reps[] = $rep;
    }
}

// Build WHERE clause based on filters - USING DIRECT SQL
$where_conditions = [];
$query_params = [];

// Base permission filter
if ($is_admin) {
    // Admin can see all invoices, but can filter by sales rep
    if ($filter_user_id && $filter_user_id !== 'all') {
        $where_conditions[] = "user_id = " . intval($filter_user_id);
    }
} else {
    // Sales reps can only see their own invoices
    $where_conditions[] = "user_id = " . intval($user_id);
}

// Add status filter
if ($filter_status && $filter_status !== 'all') {
    $where_conditions[] = "status = '" . $conn->real_escape_string($filter_status) . "'";
}

// Add date filters
if ($filter_date_from) {
    $where_conditions[] = "issue_date >= '" . $conn->real_escape_string($filter_date_from) . "'";
}

if ($filter_date_to) {
    $where_conditions[] = "issue_date <= '" . $conn->real_escape_string($filter_date_to) . "'";
}

// Build WHERE clause string
$where_sql = '';
if (!empty($where_conditions)) {
    $where_sql = "WHERE " . implode(" AND ", $where_conditions);
}

// Build and execute main query - DIRECT SQL (CORRECT: using i.user_id)
$query = "SELECT i.*, c.name as client_name, u.full_name as sales_person, u.username as sales_username 
          FROM invoices i 
          JOIN clients c ON i.client_id = c.id 
          JOIN users u ON i.user_id = u.id 
          $where_sql
          ORDER BY i.created_at DESC";

// Execute query
$result = $conn->query($query);

// Calculate statistics for admin - DIRECT SQL
$stats = [];
if ($is_admin) {
    // Total invoices count
    $stats_sql = "SELECT 
        COUNT(*) as total_invoices,
        SUM(total) as total_revenue,
        COUNT(DISTINCT user_id) as active_sales_people
        FROM invoices $where_sql";
    
    $stats_result = $conn->query($stats_sql);
    $stats = $stats_result->fetch_assoc();
    
    // Get sales rep performance if filtered
    if ($filter_user_id && $filter_user_id !== 'all') {
        $perf_sql = sprintf("SELECT 
            u.full_name,
            COUNT(i.id) as invoice_count,
            SUM(i.total) as total_sales,
            AVG(i.total) as avg_invoice,
            MIN(i.created_at) as first_invoice,
            MAX(i.created_at) as last_invoice
            FROM invoices i
            JOIN users u ON i.user_id = u.id
            WHERE u.id = %d
            GROUP BY u.id", intval($filter_user_id));
        $perf_result = $conn->query($perf_sql);
        $performance = $perf_result->fetch_assoc();
    }
}
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
        .container { max-width: 1400px; margin: 0 auto; padding: 20px; }
        .card { background: white; padding: 30px; border-radius: 10px; margin-bottom: 20px; box-shadow: 0 5px 15px rgba(0,0,0,0.1); }
        table { width: 100%; border-collapse: collapse; margin: 20px 0; }
        th, td { padding: 12px; text-align: left; border-bottom: 1px solid #ddd; }
        th { background: #f8f9fa; font-weight: 600; }
        tr:hover { background: #f9fafb; }
        .btn { padding: 8px 15px; border: none; border-radius: 5px; cursor: pointer; text-decoration: none; display: inline-block; font-size: 14px; }
        .btn-primary { background: #4f46e5; color: white; }
        .btn-warning { background: #f59e0b; color: white; }
        .btn-danger { background: #ef4444; color: white; }
        .btn-success { background: #10b981; color: white; }
        .btn-secondary { background: #6b7280; color: white; }
        .btn-info { background: #3b82f6; color: white; }
        .invoice-status { padding: 4px 8px; border-radius: 4px; font-size: 12px; font-weight: 600; }
        .status-draft { background: #f3f4f6; color: #6b7280; }
        .status-sent { background: #dbeafe; color: #1e40af; }
        .status-paid { background: #d1fae5; color: #065f46; }
        .status-cancelled { background: #fee2e2; color: #991b1b; }
        .message { padding: 15px; margin: 20px 0; border-radius: 8px; text-align: center; }
        .message-success { background: #d1fae5; color: #065f46; border: 1px solid #a7f3d0; }
        .message-error { background: #fee2e2; color: #991b1b; border: 1px solid #fecaca; }
        .amount { font-family: 'Courier New', monospace; font-weight: 600; }
        .actions-cell { white-space: nowrap; }
        .filter-section { background: #f8f9fa; padding: 20px; border-radius: 10px; margin: 20px 0; }
        .filter-row { display: flex; flex-wrap: wrap; gap: 15px; margin-bottom: 15px; align-items: flex-end; }
        .filter-group { flex: 1; min-width: 200px; }
        .filter-group label { display: block; margin-bottom: 5px; color: #4a5568; font-weight: 500; }
        .filter-group select, .filter-group input { width: 100%; padding: 8px 12px; border: 1px solid #e2e8f0; border-radius: 6px; }
        .stats-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 15px; margin: 20px 0; }
        .stat-card { background: white; padding: 20px; border-radius: 8px; box-shadow: 0 2px 10px rgba(0,0,0,0.05); }
        .stat-card h4 { font-size: 12px; color: #6b7280; margin-bottom: 10px; }
        .stat-card .value { font-size: 24px; font-weight: bold; color: #4f46e5; }
        .performance-card { background: linear-gradient(135deg, #f0f9ff 0%, #e0f2fe 100%); border-left: 4px solid #0ea5e9; }
        .reset-filters { margin-top: 10px; }
        .sales-rep-badge { display: inline-block; padding: 2px 8px; background: #e0f2fe; color: #0369a1; border-radius: 4px; font-size: 12px; margin-left: 5px; }
    </style>
    <script>
    function confirmDelete(invoiceId, invoiceNumber) {
        if (confirm(`Are you sure you want to delete invoice ${invoiceNumber}?\n\nThis action cannot be undone!`)) {
            window.location.href = `invoices.php?delete_id=${invoiceId}&${window.location.search.substring(1)}`;
        }
    }
    
    function confirmSend(invoiceId) {
        if (confirm('Mark this invoice as sent to client?')) {
            window.location.href = `update-status.php?id=${invoiceId}&status=sent&${window.location.search.substring(1)}`;
        }
    }
    
    function confirmPaid(invoiceId) {
        if (confirm('Mark this invoice as paid?')) {
            window.location.href = `update-status.php?id=${invoiceId}&status=paid&${window.location.search.substring(1)}`;
        }
    }
    
    function applyFilters() {
        const form = document.getElementById('filterForm');
        const params = new URLSearchParams(new FormData(form));
        window.location.href = `invoices.php?${params.toString()}`;
    }
    
    function resetFilters() {
        window.location.href = 'invoices.php';
    }
    
    function exportCSV() {
        // Get current filter params
        const params = new URLSearchParams(window.location.search);
        window.location.href = `export-invoices.php?${params.toString()}`;
    }
    </script>
</head>
<body>
    <div class="header">
        <div class="container">
            <div style="display: flex; justify-content: space-between; align-items: center;">
                <h1>📄 Invoice Management</h1>
                <div>
                    <a href="dashboard.php" class="btn btn-secondary">← Dashboard</a>
                    <a href="create-invoice.php" class="btn btn-success">➕ New Invoice</a>
                    <?php if ($is_admin): ?>
                        <button onclick="exportCSV()" class="btn btn-info">📊 Export CSV</button>
                    <?php endif; ?>
                </div>
            </div>
            <p style="margin-top: 10px; opacity: 0.9;">Logged in as: <?php echo htmlspecialchars($_SESSION['full_name']); ?> (<?php echo htmlspecialchars($role); ?>)</p>
        </div>
    </div>
    
    <div class="container">
        <?php if ($message): ?>
            <div class="message message-success">
                <?php echo htmlspecialchars($message); ?>
            </div>
        <?php endif; ?>
        
        <?php if (isset($error)): ?>
            <div class="message message-error">
                ❌ <?php echo htmlspecialchars($error); ?>
            </div>
        <?php endif; ?>
        
        <!-- Filter Section -->
        <div class="filter-section card">
            <h3 style="margin-bottom: 20px;">🔍 Filter Invoices</h3>
            <form id="filterForm" method="GET" action="invoices.php">
                <div class="filter-row">
                    <?php if ($is_admin && !empty($sales_reps)): ?>
                    <div class="filter-group">
                        <label>Filter by Sales Representative:</label>
                        <select name="user_id" onchange="applyFilters()">
                            <option value="all" <?php echo ($filter_user_id === 'all' || !$filter_user_id) ? 'selected' : ''; ?>>All Sales Reps</option>
                            <?php foreach ($sales_reps as $rep): ?>
                            <option value="<?php echo $rep['id']; ?>" 
                                <?php echo ($filter_user_id == $rep['id']) ? 'selected' : ''; ?>>
                                <?php echo htmlspecialchars($rep['full_name']); ?> (<?php echo htmlspecialchars($rep['username']); ?>)
                            </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <?php endif; ?>
                    
                    <div class="filter-group">
                        <label>Filter by Status:</label>
                        <select name="status" onchange="applyFilters()">
                            <option value="all" <?php echo ($filter_status === 'all' || !$filter_status) ? 'selected' : ''; ?>>All Statuses</option>
                            <option value="draft" <?php echo ($filter_status === 'draft') ? 'selected' : ''; ?>>Draft</option>
                            <option value="sent" <?php echo ($filter_status === 'sent') ? 'selected' : ''; ?>>Sent</option>
                            <option value="paid" <?php echo ($filter_status === 'paid') ? 'selected' : ''; ?>>Paid</option>
                            <option value="cancelled" <?php echo ($filter_status === 'cancelled') ? 'selected' : ''; ?>>Cancelled</option>
                        </select>
                    </div>
                    
                    <div class="filter-group">
                        <label>From Date:</label>
                        <input type="date" name="date_from" value="<?php echo htmlspecialchars($filter_date_from); ?>" onchange="applyFilters()">
                    </div>
                    
                    <div class="filter-group">
                        <label>To Date:</label>
                        <input type="date" name="date_to" value="<?php echo htmlspecialchars($filter_date_to); ?>" onchange="applyFilters()">
                    </div>
                    
                    <div class="filter-group" style="flex: 0 0 auto;">
                        <button type="button" onclick="applyFilters()" class="btn btn-primary">Apply Filters</button>
                        <button type="button" onclick="resetFilters()" class="btn btn-secondary">Reset</button>
                    </div>
                </div>
            </form>
            
            <!-- Statistics -->
            <?php if ($is_admin && isset($stats)): ?>
            <div class="stats-grid">
                <div class="stat-card">
                    <h4>Total Invoices</h4>
                    <div class="value"><?php echo $stats['total_invoices'] ?? 0; ?></div>
                </div>
                <div class="stat-card">
                    <h4>Total Revenue</h4>
                    <div class="value"><?php echo number_format($stats['total_revenue'] ?? 0, 2); ?> EGP</div>
                </div>
                <div class="stat-card">
                    <h4>Active Sales People</h4>
                    <div class="value"><?php echo $stats['active_sales_people'] ?? 0; ?></div>
                </div>
                <div class="stat-card">
                    <h4>Avg. Revenue per Invoice</h4>
                    <div class="value">
                        <?php 
                        $avg = ($stats['total_invoices'] > 0 && $stats['total_revenue'] > 0) 
                            ? $stats['total_revenue'] / $stats['total_invoices'] 
                            : 0;
                        echo number_format($avg, 2); ?> EGP
                    </div>
                </div>
            </div>
            
            <!-- Sales Rep Performance (if filtered) -->
            <?php if (isset($performance) && $performance): ?>
            <div class="stat-card performance-card">
                <h4>📊 Performance for <?php echo htmlspecialchars($performance['full_name']); ?></h4>
                <div style="display: grid; grid-template-columns: repeat(2, 1fr); gap: 15px; margin-top: 10px;">
                    <div>
                        <small>Total Invoices:</small>
                        <div style="font-size: 18px; font-weight: bold;"><?php echo $performance['invoice_count']; ?></div>
                    </div>
                    <div>
                        <small>Total Sales:</small>
                        <div style="font-size: 18px; font-weight: bold; color: #10b981;"><?php echo number_format($performance['total_sales'], 2); ?> EGP</div>
                    </div>
                    <div>
                        <small>Avg. Invoice:</small>
                        <div style="font-size: 18px; font-weight: bold;"><?php echo number_format($performance['avg_invoice'], 2); ?> EGP</div>
                    </div>
                    <div>
                        <small>Activity Period:</small>
                        <div style="font-size: 14px;">
                            <?php 
                            echo date('M d, Y', strtotime($performance['first_invoice'])) . ' - ' . 
                                 date('M d, Y', strtotime($performance['last_invoice'])); 
                            ?>
                        </div>
                    </div>
                </div>
            </div>
            <?php endif; ?>
            <?php endif; ?>
        </div>
        
        <!-- Invoices Table -->
        <div class="card">
            <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px;">
                <h2>All Invoices</h2>
                <div style="color: #6b7280; font-size: 14px;">
                    Showing <?php echo $result ? $result->num_rows : 0; ?> invoice(s)
                    <?php if ($filter_user_id && $filter_user_id !== 'all' && $is_admin): 
                        $filtered_rep = array_filter($sales_reps, fn($rep) => $rep['id'] == $filter_user_id);
                        if (!empty($filtered_rep)):
                            $rep = reset($filtered_rep);
                    ?>
                        <span class="sales-rep-badge">Filtered by: <?php echo htmlspecialchars($rep['full_name']); ?></span>
                    <?php endif; endif; ?>
                </div>
            </div>
            
            <?php if ($result && $result->num_rows > 0): ?>
                <table>
                    <thead>
                        <tr>
                            <th>Invoice #</th>
                            <th>Client</th>
                            <th>Date</th>
                            <th>Amount</th>
                            <th>Status</th>
                            <th>Sales Rep</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php while ($invoice = $result->fetch_assoc()): 
                            $can_edit = $is_admin || $invoice['user_id'] == $user_id;
                            $can_delete = $is_admin;
                        ?>
                        <tr>
                            <td><strong><?php echo htmlspecialchars($invoice['invoice_number']); ?></strong></td>
                            <td><?php echo htmlspecialchars($invoice['client_name']); ?></td>
                            <td><?php echo date('M d, Y', strtotime($invoice['issue_date'])); ?></td>
                            <td class="amount"><?php echo number_format($invoice['total'], 2); ?> EGP</td>
                            <td>
                                <span class="invoice-status status-<?php echo $invoice['status'] ?? 'draft'; ?>">
                                    <?php echo ucfirst($invoice['status'] ?? 'draft'); ?>
                                </span>
                                <div style="margin-top: 5px;">
                                    <?php if ($invoice['status'] !== 'sent'): ?>
                                        <button onclick="confirmSend(<?php echo $invoice['id']; ?>)" class="btn" style="padding: 2px 6px; font-size: 10px; background: #3b82f6;">📤 Mark Sent</button>
                                    <?php endif; ?>
                                    <?php if ($invoice['status'] !== 'paid'): ?>
                                        <button onclick="confirmPaid(<?php echo $invoice['id']; ?>)" class="btn" style="padding: 2px 6px; font-size: 10px; background: #10b981;">💰 Mark Paid</button>
                                    <?php endif; ?>
                                </div>
                            </td>
                            <td>
                                <?php echo htmlspecialchars($invoice['sales_person']); ?>
                                <?php if ($is_admin): ?>
                                    <br><small style="color: #6b7280; font-size: 11px;">@<?php echo htmlspecialchars($invoice['sales_username']); ?></small>
                                <?php endif; ?>
                            </td>
                            <td class="actions-cell">
                                <a href="view-invoice.php?id=<?php echo $invoice['id']; ?>" class="btn btn-primary">👁️ View</a>
                                
                                <?php if ($can_edit): ?>
                                    <a href="edit-invoice.php?id=<?php echo $invoice['id']; ?>" class="btn btn-warning">✏️ Edit</a>
                                <?php endif; ?>
                                
                                <?php if ($can_delete): ?>
                                    <button onclick="confirmDelete(<?php echo $invoice['id']; ?>, '<?php echo $invoice['invoice_number']; ?>')" class="btn btn-danger">🗑️ Delete</button>
                                <?php endif; ?>
                            </td>
                        </tr>
                        <?php endwhile; ?>
                    </tbody>
                </table>
                
                <!-- Summary Info -->
                <div style="margin-top: 20px; padding: 15px; background: #f8fafc; border-radius: 8px;">
                    <?php if ($is_admin): ?>
                        <p><strong>Admin View:</strong> Showing all invoices<?php echo $filter_user_id && $filter_user_id !== 'all' ? ' for selected sales rep' : ' in the system'; ?></p>
                        <p><em>Use filters above to analyze individual sales rep performance</em></p>
                    <?php else: ?>
                        <p><strong>Your invoices only.</strong> Admins can see all invoices and filter by sales rep.</p>
                    <?php endif; ?>
                </div>
                
            <?php else: ?>
                <div style="text-align: center; padding: 40px;">
                    <h3 style="color: #6b7280; margin-bottom: 20px;">📭 No invoices found</h3>
                    <p style="margin-bottom: 20px;">
                        <?php 
                        if ($filter_user_id && $filter_user_id !== 'all') {
                            echo "No invoices found for the selected sales rep with current filters.";
                        } elseif ($filter_status || $filter_date_from || $filter_date_to) {
                            echo "No invoices match your filter criteria. Try changing your filters.";
                        } else {
                            echo "You haven't created any invoices yet.";
                        }
                        ?>
                    </p>
                    <?php if ($filter_user_id || $filter_status || $filter_date_from || $filter_date_to): ?>
                        <button onclick="resetFilters()" class="btn btn-secondary" style="margin-right: 10px;">Reset Filters</button>
                    <?php endif; ?>
                    <a href="create-invoice.php" class="btn btn-success">Create Your First Invoice</a>
                </div>
            <?php endif; ?>
        </div>
    </div>
</body>
</html>