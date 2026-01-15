<?php
// users.php - User Management (Admin Only)
// Use central session configuration
require_once dirname(__DIR__) . '/includes/init.php';
require_once dirname(__DIR__) . '/includes/config.php';

// Check if logged in and is admin
if (!isset($_SESSION['user_id'])) {
    header('Location: index.php');
    exit();
}

if ($_SESSION['role'] !== 'admin') {
    die("Access denied. Admin only.");
}

// Handle user deletion
if (isset($_GET['delete_id'])) {
    $delete_id = intval($_GET['delete_id']);
    
    // Prevent self-deletion
    if ($delete_id != $_SESSION['user_id']) {
        $stmt = $conn->prepare("DELETE FROM users WHERE id = ?");
        $stmt->bind_param("i", $delete_id);
        if ($stmt->execute()) {
            $_SESSION['message'] = "✅ User deleted successfully";
        } else {
            $_SESSION['error'] = "❌ Error deleting user";
        }
    } else {
        $_SESSION['error'] = "❌ You cannot delete yourself";
    }
    header('Location: users.php');
    exit();
}

// Handle toggle active status
if (isset($_GET['toggle_active'])) {
    $user_id = intval($_GET['toggle_active']);
    
    // Prevent deactivating self
    if ($user_id != $_SESSION['user_id']) {
        $stmt = $conn->prepare("UPDATE users SET is_active = NOT is_active WHERE id = ?");
        $stmt->bind_param("i", $user_id);
        if ($stmt->execute()) {
            $_SESSION['message'] = "✅ User status updated";
        }
    } else {
        $_SESSION['error'] = "❌ You cannot deactivate yourself";
    }
    header('Location: users.php');
    exit();
}

// Get all users
$users_result = $conn->query("
    SELECT id, username, full_name, role, created_at, is_active 
    FROM users 
    ORDER BY role, full_name
");
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>User Management - CyberguardX</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        body { background: #f8f9fa; }
        .header-gradient { background: linear-gradient(135deg, #2c3e50 0%, #3498db 100%); }
        .card { box-shadow: 0 5px 15px rgba(0,0,0,0.1); }
        .badge-admin { background: #dc3545; }
        .badge-sales { background: #28a745; }
        .user-row:hover { background-color: #f8f9fa; }
        .inactive-user { opacity: 0.6; background-color: #f8f9fa; }
        .inactive-user td { color: #6c757d; }
    </style>
</head>
<body>
    <!-- Navigation -->
    <nav class="navbar navbar-dark header-gradient mb-4">
        <div class="container">
            <a class="navbar-brand" href="dashboard.php">
                <i class="fas fa-arrow-left"></i> User Management
            </a>
            <span class="navbar-text">
                Admin: <?php echo htmlspecialchars($_SESSION['full_name']); ?>
            </span>
        </div>
    </nav>

    <div class="container">
        <!-- Messages -->
        <?php if (isset($_SESSION['message'])): ?>
            <div class="alert alert-success alert-dismissible fade show">
                <?php echo $_SESSION['message']; unset($_SESSION['message']); ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
        <?php endif; ?>
        
        <?php if (isset($_SESSION['error'])): ?>
            <div class="alert alert-danger alert-dismissible fade show">
                <?php echo $_SESSION['error']; unset($_SESSION['error']); ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
        <?php endif; ?>

        <!-- Header with Create Button -->
        <div class="d-flex justify-content-between align-items-center mb-4">
            <h2>👥 User Management</h2>
            <a href="create-user.php" class="btn btn-success">
                <i class="fas fa-user-plus"></i> Create New User
            </a>
        </div>

        <!-- Users Table -->
        <div class="card">
            <div class="card-body">
                <div class="table-responsive">
                    <table class="table table-hover">
                        <thead>
                            <tr>
                                <th>ID</th>
                                <th>Username</th>
                                <th>Full Name</th>
                                <th>Role</th>
                                <th>Status</th>
                                <th>Created</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php while ($user = $users_result->fetch_assoc()): ?>
                            <tr class="user-row <?php echo $user['is_active'] == 0 ? 'inactive-user' : ''; ?>">
                                <td><?php echo $user['id']; ?></td>
                                <td>
                                    <strong><?php echo htmlspecialchars($user['username']); ?></strong>
                                    <?php if ($user['id'] == $_SESSION['user_id']): ?>
                                        <span class="badge bg-info">You</span>
                                    <?php endif; ?>
                                </td>
                                <td><?php echo htmlspecialchars($user['full_name']); ?></td>
                                <td>
                                    <span class="badge badge-<?php echo $user['role']; ?>">
                                        <?php echo ucfirst($user['role']); ?>
                                    </span>
                                </td>
                                <td>
                                    <?php if ($user['is_active']): ?>
                                        <span class="badge bg-success">Active</span>
                                    <?php else: ?>
                                        <span class="badge bg-secondary">Inactive</span>
                                    <?php endif; ?>
                                </td>
                                <td><?php echo date('M d, Y', strtotime($user['created_at'])); ?></td>
                                <td>
                                    <div class="btn-group btn-group-sm">
                                        <a href="edit-user.php?id=<?php echo $user['id']; ?>" 
                                           class="btn btn-outline-primary">
                                            <i class="fas fa-edit"></i> Edit
                                        </a>
                                        
                                        <?php if ($user['id'] != $_SESSION['user_id']): ?>
                                            <a href="users.php?toggle_active=<?php echo $user['id']; ?>" 
                                               class="btn btn-outline-warning"
                                               onclick="return confirm('Toggle user active status?')">
                                                <i class="fas fa-power-off"></i>
                                            </a>
                                            
                                            <a href="users.php?delete_id=<?php echo $user['id']; ?>" 
                                               class="btn btn-outline-danger"
                                               onclick="return confirm('Are you sure you want to delete this user?\n\nThis action cannot be undone!')">
                                                <i class="fas fa-trash"></i>
                                            </a>
                                        <?php else: ?>
                                            <button class="btn btn-outline-secondary" disabled>
                                                <i class="fas fa-lock"></i> Protected
                                            </button>
                                        <?php endif; ?>
                                    </div>
                                </td>
                            </tr>
                            <?php endwhile; ?>
                        </tbody>
                    </table>
                </div>
                
                <!-- Summary -->
                <div class="mt-3 p-3 bg-light rounded">
                    <small>
                        <i class="fas fa-info-circle text-primary"></i>
                        Total Users: <?php echo $users_result->num_rows; ?> | 
                        You cannot delete or deactivate yourself for security reasons.
                    </small>
                </div>
            </div>
        </div>
        
        <!-- Quick Stats -->
        <div class="row mt-4">
            <div class="col-md-3">
                <div class="card text-center">
                    <div class="card-body">
                        <h3 class="text-primary"><?php 
                            $count = $conn->query("SELECT COUNT(*) as c FROM users WHERE role = 'admin'")->fetch_assoc();
                            echo $count['c'];
                        ?></h3>
                        <p class="text-muted mb-0">Administrators</p>
                    </div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="card text-center">
                    <div class="card-body">
                        <h3 class="text-success"><?php 
                            $count = $conn->query("SELECT COUNT(*) as c FROM users WHERE role = 'sales'")->fetch_assoc();
                            echo $count['c'];
                        ?></h3>
                        <p class="text-muted mb-0">Sales Representatives</p>
                    </div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="card text-center">
                    <div class="card-body">
                        <h3 class="text-success"><?php 
                            $count = $conn->query("SELECT COUNT(*) as c FROM users WHERE is_active = 1")->fetch_assoc();
                            echo $count['c'];
                        ?></h3>
                        <p class="text-muted mb-0">Active Users</p>
                    </div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="card text-center">
                    <div class="card-body">
                        <h3 class="text-secondary"><?php 
                            $count = $conn->query("SELECT COUNT(*) as c FROM users WHERE is_active = 0")->fetch_assoc();
                            echo $count['c'];
                        ?></h3>
                        <p class="text-muted mb-0">Inactive Users</p>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>