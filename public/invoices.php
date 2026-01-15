<?php
// invoices.php - ENHANCED VERSION WITH FIXED DIRECT SQL (FROM OLD PROJECT)
require_once dirname(__DIR__) . '/includes/session-config.php';
startAppSession();
require_once dirname(__DIR__) . '/includes/config.php';
require_once dirname(__DIR__) . '/includes/auth.php';

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

// Initialize message variables
$message = isset($_SESSION['message']) ? $_SESSION['message'] : '';
$error = isset($_SESSION['error']) ? $_SESSION['error'] : '';

// Clear session messages
unset($_SESSION['message']);
unset($_SESSION['error']);

// Handle delete request (admin only) - REMOVED THIS SECTION since we're using delete-invoice.php
// This should not be here anymore

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
        $where_conditions[] = "i.user_id = " . intval($filter_user_id);
    }
} else {
    // Sales reps can only see their own invoices
    $where_conditions[] = "i.user_id = " . intval($user_id);
}

// Add status filter
if ($filter_status && $filter_status !== 'all') {
    $where_conditions[] = "i.status = '" . $conn->real_escape_string($filter_status) . "'";
}

// Add date filters
if ($filter_date_from) {
    $where_conditions[] = "i.issue_date >= '" . $conn->real_escape_string($filter_date_from) . "'";
}

if ($filter_date_to) {
    $where_conditions[] = "i.issue_date <= '" . $conn->real_escape_string($filter_date_to) . "'";
}

// Build WHERE clause string
$where_sql = '';
if (!empty($where_conditions)) {
    $where_sql = "WHERE " . implode(" AND ", $where_conditions);
}

// Build and execute main query
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
        FROM invoices i $where_sql";

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
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        :root {
            --primary-color: #4f46e5;
            --secondary-color: #7c3aed;
            --success-color: #10b981;
            --danger-color: #ef4444;
            --warning-color: #f59e0b;
            --info-color: #3b82f6;
        }
        
        body {
            background: linear-gradient(135deg, #f8f9fa 0%, #e9ecef 100%);
            min-height: 100vh;
            font-family: 'Segoe UI', system-ui, -apple-system, sans-serif;
        }
        
        .header {
            background: linear-gradient(135deg, var(--primary-color) 0%, var(--secondary-color) 100%);
            color: white;
            padding: 20px 0;
            box-shadow: 0 4px 12px rgba(0,0,0,0.1);
        }
        
        .container-main {
            max-width: 1400px;
            margin: 0 auto;
            padding: 20px;
        }
        
        .card {
            background: white;
            border-radius: 12px;
            border: none;
            box-shadow: 0 4px 15px rgba(0,0,0,0.08);
            margin-bottom: 20px;
            overflow: hidden;
        }
        
        .card-header {
            background: linear-gradient(135deg, #f8f9fa 0%, #e9ecef 100%);
            border-bottom: 2px solid #e9ecef;
            padding: 20px;
            font-weight: 600;
        }
        
        .table-custom {
            width: 100%;
            border-collapse: separate;
            border-spacing: 0;
        }
        
        .table-custom thead {
            background: linear-gradient(135deg, #2c3e50 0%, #3498db 100%);
        }
        
        .table-custom th {
            color: white;
            padding: 16px;
            font-weight: 600;
            border: none;
            text-align: left;
        }
        
        .table-custom td {
            padding: 14px 16px;
            border-bottom: 1px solid #edf2f7;
            vertical-align: middle;
        }
        
        .table-custom tbody tr:hover {
            background-color: #f8fafc;
            transform: translateY(-1px);
            transition: all 0.2s ease;
            box-shadow: 0 2px 8px rgba(0,0,0,0.05);
        }
        
        .btn {
            padding: 8px 16px;
            border-radius: 8px;
            font-weight: 500;
            transition: all 0.2s ease;
            border: none;
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            gap: 6px;
        }
        
        .btn:hover {
            transform: translateY(-2px);
            box-shadow: 0 4px 12px rgba(0,0,0,0.15);
        }
        
        .btn-primary {
            background: linear-gradient(135deg, var(--primary-color) 0%, var(--secondary-color) 100%);
            color: white;
        }
        
        .btn-success {
            background: linear-gradient(135deg, var(--success-color) 0%, #34d399 100%);
            color: white;
        }
        
        .btn-danger {
            background: linear-gradient(135deg, var(--danger-color) 0%, #f87171 100%);
            color: white;
        }
        
        .btn-warning {
            background: linear-gradient(135deg, var(--warning-color) 0%, #fbbf24 100%);
            color: white;
        }
        
        .btn-info {
            background: linear-gradient(135deg, var(--info-color) 0%, #60a5fa 100%);
            color: white;
        }
        
        .btn-secondary {
            background: linear-gradient(135deg, #6b7280 0%, #9ca3af 100%);
            color: white;
        }
        
        .invoice-status {
            padding: 6px 12px;
            border-radius: 20px;
            font-size: 12px;
            font-weight: 600;
            display: inline-block;
        }
        
        .status-draft {
            background: linear-gradient(135deg, #f3f4f6 0%, #e5e7eb 100%);
            color: #6b7280;
        }
        
        .status-sent {
            background: linear-gradient(135deg, #dbeafe 0%, #93c5fd 100%);
            color: #1e40af;
        }
        
        .status-paid {
            background: linear-gradient(135deg, #d1fae5 0%, #a7f3d0 100%);
            color: #065f46;
        }
        
        .status-cancelled {
            background: linear-gradient(135deg, #fee2e2 0%, #fecaca 100%);
            color: #991b1b;
        }
        
        .filter-section {
            background: linear-gradient(135deg, #f8fafc 0%, #f1f5f9 100%);
            border-radius: 12px;
            padding: 25px;
            margin-bottom: 25px;
        }
        
        .filter-group {
            margin-bottom: 15px;
        }
        
        .filter-group label {
            color: #4a5568;
            font-weight: 500;
            margin-bottom: 8px;
            display: block;
        }
        
        .filter-group select,
        .filter-group input {
            width: 100%;
            padding: 10px 14px;
            border: 2px solid #e2e8f0;
            border-radius: 8px;
            transition: all 0.2s ease;
        }
        
        .filter-group select:focus,
        .filter-group input:focus {
            outline: none;
            border-color: var(--primary-color);
            box-shadow: 0 0 0 3px rgba(79, 70, 229, 0.1);
        }
        
        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 20px;
            margin: 25px 0;
        }
        
        .stat-card {
            background: white;
            padding: 20px;
            border-radius: 10px;
            box-shadow: 0 3px 10px rgba(0,0,0,0.05);
            border-left: 4px solid var(--primary-color);
        }
        
        .stat-card h4 {
            font-size: 13px;
            color: #6b7280;
            margin-bottom: 10px;
            font-weight: 600;
        }
        
        .stat-card .value {
            font-size: 28px;
            font-weight: 700;
            color: var(--primary-color);
            line-height: 1.2;
        }
        
        .actions-cell {
            display: flex;
            gap: 8px;
            flex-wrap: wrap;
        }
        
        .actions-cell .btn {
            padding: 6px 12px;
            font-size: 13px;
            min-width: 80px;
            justify-content: center;
        }
        
        .amount-cell {
            font-family: 'Courier New', monospace;
            font-weight: 600;
            color: #2d3748;
        }
        
        .empty-state {
            text-align: center;
            padding: 60px 20px;
            color: #6b7280;
        }
        
        .empty-state i {
            font-size: 48px;
            margin-bottom: 20px;
            color: #cbd5e1;
        }
        
        .sales-rep-badge {
            background: linear-gradient(135deg, #e0f2fe 0%, #bae6fd 100%);
            color: #0369a1;
            padding: 4px 10px;
            border-radius: 20px;
            font-size: 12px;
            font-weight: 500;
            margin-left: 10px;
        }
        
        /* Responsive adjustments */
        @media (max-width: 768px) {
            .actions-cell {
                flex-direction: column;
            }
            
            .actions-cell .btn {
                width: 100%;
            }
            
            .table-custom {
                display: block;
                overflow-x: auto;
            }
        }
    </style>
</head>
<body>
    <!-- Header -->
    <div class="header">
        <div class="container-main">
            <div class="d-flex flex-column flex-md-row justify-content-between align-items-start align-items-md-center">
                <div class="mb-3 mb-md-0">
                    <h1 class="h3 mb-2"><i class="fas fa-file-invoice me-2"></i>Invoice Management</h1>
                    <p class="mb-0 opacity-90">Logged in as: <strong><?php echo htmlspecialchars($_SESSION['full_name']); ?></strong> (<?php echo htmlspecialchars($role); ?>)</p>
                </div>
                <div class="d-flex flex-wrap gap-2">
                    <a href="dashboard.php" class="btn btn-secondary">
                        <i class="fas fa-arrow-left me-1"></i> Dashboard
                    </a>
                    <a href="create-invoice.php" class="btn btn-success">
                        <i class="fas fa-plus me-1"></i> New Invoice
                    </a>
                    <?php if ($is_admin): ?>
                        <button onclick="exportCSV()" class="btn btn-info">
                            <i class="fas fa-file-export me-1"></i> Export CSV
                        </button>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>

    <!-- Main Content -->
    <div class="container-main">
        <!-- Display Messages -->
        <?php if ($message): ?>
            <div class="alert alert-success alert-dismissible fade show d-flex align-items-center" role="alert">
                <i class="fas fa-check-circle me-2"></i>
                <div class="flex-grow-1"><?php echo htmlspecialchars($message); ?></div>
                <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
            </div>
        <?php endif; ?>

        <?php if ($error): ?>
            <div class="alert alert-danger alert-dismissible fade show d-flex align-items-center" role="alert">
                <i class="fas fa-exclamation-circle me-2"></i>
                <div class="flex-grow-1"><?php echo htmlspecialchars($error); ?></div>
                <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
            </div>
        <?php endif; ?>

        <!-- Filter Section -->
        <div class="filter-section">
            <h3 class="h4 mb-4"><i class="fas fa-filter me-2"></i>Filter Invoices</h3>
            <form id="filterForm" method="GET" action="invoices.php">
                <div class="row g-3">
                    <?php if ($is_admin && !empty($sales_reps)): ?>
                        <div class="col-md-3">
                            <label class="form-label">Sales Representative</label>
                            <select name="user_id" class="form-select" onchange="applyFilters()">
                                <option value="all" <?php echo ($filter_user_id === 'all' || !$filter_user_id) ? 'selected' : ''; ?>>All Sales Reps</option>
                                <?php foreach ($sales_reps as $rep): ?>
                                    <option value="<?php echo $rep['id']; ?>" <?php echo ($filter_user_id == $rep['id']) ? 'selected' : ''; ?>>
                                        <?php echo htmlspecialchars($rep['full_name']); ?> (<?php echo htmlspecialchars($rep['username']); ?>)
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    <?php endif; ?>

                    <div class="col-md-3">
                        <label class="form-label">Status</label>
                        <select name="status" class="form-select" onchange="applyFilters()">
                            <option value="all" <?php echo ($filter_status === 'all' || !$filter_status) ? 'selected' : ''; ?>>All Statuses</option>
                            <option value="draft" <?php echo ($filter_status === 'draft') ? 'selected' : ''; ?>>Draft</option>
                            <option value="sent" <?php echo ($filter_status === 'sent') ? 'selected' : ''; ?>>Sent</option>
                            <option value="paid" <?php echo ($filter_status === 'paid') ? 'selected' : ''; ?>>Paid</option>
                            <option value="cancelled" <?php echo ($filter_status === 'cancelled') ? 'selected' : ''; ?>>Cancelled</option>
                        </select>
                    </div>

                    <div class="col-md-3">
                        <label class="form-label">From Date</label>
                        <input type="date" name="date_from" class="form-control" value="<?php echo htmlspecialchars($filter_date_from); ?>" onchange="applyFilters()">
                    </div>

                    <div class="col-md-3">
                        <label class="form-label">To Date</label>
                        <input type="date" name="date_to" class="form-control" value="<?php echo htmlspecialchars($filter_date_to); ?>" onchange="applyFilters()">
                    </div>

                    <div class="col-12">
                        <div class="d-flex gap-2">
                            <button type="button" onclick="applyFilters()" class="btn btn-primary">
                                <i class="fas fa-search me-1"></i> Apply Filters
                            </button>
                            <button type="button" onclick="resetFilters()" class="btn btn-secondary">
                                <i class="fas fa-redo me-1"></i> Reset
                            </button>
                        </div>
                    </div>
                </div>
            </form>

            <!-- Statistics -->
            <?php if ($is_admin && isset($stats)): ?>
                <div class="stats-grid mt-4">
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
                    <div class="stat-card mt-3" style="border-left-color: var(--info-color);">
                        <h4><i class="fas fa-chart-line me-2"></i>Performance for <?php echo htmlspecialchars($performance['full_name']); ?></h4>
                        <div class="row mt-3">
                            <div class="col-6 col-md-3 mb-3">
                                <small class="text-muted d-block">Total Invoices</small>
                                <div class="h4 fw-bold"><?php echo $performance['invoice_count']; ?></div>
                            </div>
                            <div class="col-6 col-md-3 mb-3">
                                <small class="text-muted d-block">Total Sales</small>
                                <div class="h4 fw-bold text-success"><?php echo number_format($performance['total_sales'], 2); ?> EGP</div>
                            </div>
                            <div class="col-6 col-md-3 mb-3">
                                <small class="text-muted d-block">Avg. Invoice</small>
                                <div class="h4 fw-bold"><?php echo number_format($performance['avg_invoice'], 2); ?> EGP</div>
                            </div>
                            <div class="col-6 col-md-3 mb-3">
                                <small class="text-muted d-block">Activity Period</small>
                                <div class="h6">
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
            <div class="card-header d-flex justify-content-between align-items-center">
                <h5 class="mb-0"><i class="fas fa-list me-2"></i>All Invoices</h5>
                <div class="text-muted">
                    Showing <?php echo $result ? $result->num_rows : 0; ?> invoice(s)
                    <?php if ($filter_user_id && $filter_user_id !== 'all' && $is_admin):
                        $filtered_rep = array_filter($sales_reps, fn($rep) => $rep['id'] == $filter_user_id);
                        if (!empty($filtered_rep)):
                            $rep = reset($filtered_rep);
                    ?>
                            <span class="sales-rep-badge">Filtered by: <?php echo htmlspecialchars($rep['full_name']); ?></span>
                    <?php endif;
                    endif; ?>
                </div>
            </div>
            
            <div class="card-body p-0">
                <?php if ($result && $result->num_rows > 0): ?>
                    <div class="table-responsive">
                        <table class="table-custom">
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
                                        <td>
                                            <div class="fw-bold"><?php echo htmlspecialchars($invoice['invoice_number']); ?></div>
                                            <small class="text-muted">#<?php echo $invoice['id']; ?></small>
                                        </td>
                                        <td>
                                            <div><?php echo htmlspecialchars($invoice['client_name']); ?></div>
                                            <small class="text-muted">ID: <?php echo $invoice['client_id']; ?></small>
                                        </td>
                                        <td>
                                            <div><?php echo date('M d, Y', strtotime($invoice['issue_date'])); ?></div>
                                            <small class="text-muted">Due: <?php echo date('M d', strtotime($invoice['due_date'])); ?></small>
                                        </td>
                                        <td class="amount-cell">
                                            <?php echo number_format($invoice['total'], 2); ?> EGP
                                            <?php if ($invoice['payment_amount'] > 0): ?>
                                                <br><small class="text-success">Paid: <?php echo number_format($invoice['payment_amount'], 2); ?></small>
                                            <?php endif; ?>
                                        </td>
                                        <td>
                                            <span class="invoice-status status-<?php echo $invoice['status'] ?? 'draft'; ?>">
                                                <?php echo ucfirst($invoice['status'] ?? 'draft'); ?>
                                            </span>
                                            <div class="mt-1">
                                                <?php if ($invoice['status'] !== 'sent'): ?>
                                                    <button onclick="confirmSend(<?php echo $invoice['id']; ?>)" class="btn btn-sm btn-outline-primary p-1" style="font-size: 11px;">
                                                        <i class="fas fa-paper-plane me-1"></i> Mark Sent
                                                    </button>
                                                <?php endif; ?>
                                                <?php if ($invoice['status'] !== 'paid'): ?>
                                                    <button onclick="confirmPaid(<?php echo $invoice['id']; ?>)" class="btn btn-sm btn-outline-success p-1" style="font-size: 11px;">
                                                        <i class="fas fa-check me-1"></i> Mark Paid
                                                    </button>
                                                <?php endif; ?>
                                            </div>
                                        </td>
                                        <td>
                                            <div><?php echo htmlspecialchars($invoice['sales_person']); ?></div>
                                            <?php if ($is_admin): ?>
                                                <small class="text-muted">@<?php echo htmlspecialchars($invoice['sales_username']); ?></small>
                                            <?php endif; ?>
                                        </td>
                                        <td class="actions-cell">
                                            <a href="view-invoice.php?id=<?php echo $invoice['id']; ?>" class="btn btn-primary btn-sm">
                                                <i class="fas fa-eye"></i> View
                                            </a>

                                            <?php if ($can_edit): ?>
                                                <a href="edit-invoice.php?id=<?php echo $invoice['id']; ?>" class="btn btn-warning btn-sm">
                                                    <i class="fas fa-edit"></i> Edit
                                                </a>
                                            <?php endif; ?>

                                            <?php if ($can_delete): ?>
                                                <button onclick="confirmDelete(<?php echo $invoice['id']; ?>, '<?php echo $invoice['invoice_number']; ?>')" class="btn btn-danger btn-sm">
                                                    <i class="fas fa-trash"></i> Delete
                                                </button>
                                            <?php endif; ?>
                                        </td>
                                    </tr>
                                <?php endwhile; ?>
                            </tbody>
                        </table>
                    </div>
                    
                    <!-- Summary Info -->
                    <div class="p-4 border-top">
                        <?php if ($is_admin): ?>
                            <p class="mb-2"><strong>Admin View:</strong> Showing all invoices<?php echo $filter_user_id && $filter_user_id !== 'all' ? ' for selected sales rep' : ' in the system'; ?></p>
                            <p class="text-muted mb-0"><i class="fas fa-info-circle me-1"></i> Use filters above to analyze individual sales rep performance</p>
                        <?php else: ?>
                            <p class="mb-0"><strong>Your invoices only.</strong> Admins can see all invoices and filter by sales rep.</p>
                        <?php endif; ?>
                    </div>
                <?php else: ?>
                    <div class="empty-state">
                        <i class="fas fa-file-invoice"></i>
                        <h4 class="h5 mb-3">No invoices found</h4>
                        <p class="text-muted mb-4">
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
                        <div class="d-flex justify-content-center gap-2">
                            <?php if ($filter_user_id || $filter_status || $filter_date_from || $filter_date_to): ?>
                                <button onclick="resetFilters()" class="btn btn-secondary">
                                    <i class="fas fa-redo me-1"></i> Reset Filters
                                </button>
                            <?php endif; ?>
                            <a href="create-invoice.php" class="btn btn-success">
                                <i class="fas fa-plus me-1"></i> Create Your First Invoice
                            </a>
                        </div>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        function confirmDelete(invoiceId, invoiceNumber) {
            if (confirm(`Are you sure you want to delete invoice ${invoiceNumber}?\n\nYou'll be taken to a confirmation page.`)) {
                window.location.href = `delete-invoice.php?id=${invoiceId}`;
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

        // Auto-apply filters after 1.5 seconds of inactivity
        let filterTimeout;
        const filterInputs = document.querySelectorAll('#filterForm select, #filterForm input');
        
        filterInputs.forEach(input => {
            input.addEventListener('input', () => {
                clearTimeout(filterTimeout);
                filterTimeout = setTimeout(applyFilters, 1500);
            });
            
            input.addEventListener('change', applyFilters);
        });
    </script>
</body>
</html>