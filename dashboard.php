<?php
// dashboard.php
// Fix session path for macOS
ini_set('session.save_path', '/tmp');

// Start session
session_start();

// Check if logged in
if (!isset($_SESSION['user_id'])) {
    header('Location: index.php');
    exit();
}

// Include config for database if needed
require_once 'includes/config.php';
?>
<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dashboard - CyberguardX</title>
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; font-family: -apple-system, sans-serif; }
        body { background: #f5f5f5; }
        .header { background: linear-gradient(135deg, #4f46e5 0%, #7c3aed 100%); color: white; padding: 20px; }
        .container { max-width: 1200px; margin: 0 auto; padding: 20px; }
        .welcome-box { background: white; padding: 30px; border-radius: 10px; margin-bottom: 20px; box-shadow: 0 5px 15px rgba(0,0,0,0.1); }
        .quick-actions { display: grid; grid-template-columns: repeat(auto-fit, minmax(250px, 1fr)); gap: 20px; margin: 30px 0; }
        .action-card { background: white; padding: 25px; border-radius: 10px; text-align: center; box-shadow: 0 5px 15px rgba(0,0,0,0.1); transition: transform 0.3s; }
        .action-card:hover { transform: translateY(-5px); }
        .action-card i { font-size: 40px; margin-bottom: 15px; display: block; }
        .action-card h3 { margin-bottom: 10px; color: #333; }
        .action-card p { color: #666; margin-bottom: 20px; }
        .btn { display: inline-block; padding: 12px 25px; background: #4f46e5; color: white; text-decoration: none; border-radius: 8px; font-weight: 600; }
        .btn:hover { background: #4338ca; }
        .logout-btn { background: #dc2626; }
        .logout-btn:hover { background: #b91c1c; }
        .user-info { display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px; }
        .stats { display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 20px; margin: 30px 0; }
        .stat-card { background: white; padding: 20px; border-radius: 10px; text-align: center; }
        .stat-card .number { font-size: 32px; font-weight: bold; color: #4f46e5; }
        .stat-card .label { color: #666; margin-top: 10px; }
    </style>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
</head>
<body>
    <div class="header">
        <div class="container">
            <div class="user-info">
                <div>
                    <h1>Welcome, <?php echo htmlspecialchars($_SESSION['full_name']); ?>!</h1>
                    <p>Role: <?php echo htmlspecialchars($_SESSION['role']); ?></p>
                </div>
                <a href="logout.php" class="btn logout-btn"><i class="fas fa-sign-out-alt"></i> Logout</a>
            </div>
        </div>
    </div>
    
    <div class="container">
        <div class="welcome-box">
            <h2>Invoice System Dashboard</h2>
            <p>Manage your invoices, clients, and sales reports from one place.</p>
        </div>
        
        <div class="quick-actions">
            <div class="action-card">
                <i class="fas fa-file-invoice" style="color: #4f46e5;"></i>
                <h3>Create Invoice</h3>
                <p>Create a new invoice for your client</p>
                <a href="create-invoice.php" class="btn">Create New</a>
            </div>
            
            <div class="action-card">
                <i class="fas fa-list" style="color: #10b981;"></i>
                <h3>View Invoices</h3>
                <p>View and manage all your invoices</p>
                <a href="invoices.php" class="btn">View All</a>
            </div>
            
            <div class="action-card">
                <i class="fas fa-users" style="color: #f59e0b;"></i>
                <h3>Manage Clients</h3>
                <p>Add or edit client information</p>
                <a href="clients.php" class="btn">Manage</a>
            </div>
            
            <?php if ($_SESSION['role'] === 'admin'): ?>
            <div class="action-card">
                <i class="fas fa-chart-bar" style="color: #8b5cf6;"></i>
                <h3>Reports</h3>
                <p>View sales reports and analytics</p>
                <a href="reports.php" class="btn">View Reports</a>
            </div>
            
            <div class="action-card">
                <i class="fas fa-database" style="color: #ef4444;"></i>
                <h3>Database Admin</h3>
                <p>Manage database and users</p>
                <a href="phpmyadmin-style.php" class="btn">Admin Panel</a>
            </div>
            <?php endif; ?>
        </div>
        
        <div class="stats">
            <div class="stat-card">
                <div class="number">0</div>
                <div class="label">Invoices Today</div>
            </div>
            <div class="stat-card">
                <div class="number">0</div>
                <div class="label">This Month</div>
            </div>
            <div class="stat-card">
                <div class="number">$0.00</div>
                <div class="label">Total Revenue</div>
            </div>
            <div class="stat-card">
                <div class="number">1</div>
                <div class="label">Active Clients</div>
            </div>
        </div>
    </div>
</body>
</html>