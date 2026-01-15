<?php
// create-user.php - Create New User (Admin Only)
// Use central session configuration
require_once dirname(__DIR__) . '/includes/session-config.php';
startAppSession();
require_once dirname(__DIR__) . '/includes/config.php';

// Check if logged in and is admin
if (!isset($_SESSION['user_id'])) {
    header('Location: index.php');
    exit();
}

if ($_SESSION['role'] !== 'admin') {
    die("Access denied. Admin only.");
}

$error = '';
$success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = trim($_POST['username'] ?? '');
    $password = $_POST['password'] ?? '';
    $confirm_password = $_POST['confirm_password'] ?? '';
    $full_name = trim($_POST['full_name'] ?? '');
    $role = $_POST['role'] ?? 'sales';
    
    // Validation
    if (empty($username) || empty($password) || empty($full_name)) {
        $error = "All fields are required";
    } elseif (strlen($password) < 6) {
        $error = "Password must be at least 6 characters";
    } elseif ($password !== $confirm_password) {
        $error = "Passwords do not match";
    } elseif (!preg_match('/^[a-zA-Z0-9_]+$/', $username)) {
        $error = "Username can only contain letters, numbers, and underscores";
    } else {
        // Check if username exists
        $check_stmt = $conn->prepare("SELECT id FROM users WHERE username = ?");
        $check_stmt->bind_param("s", $username);
        $check_stmt->execute();
        
        if ($check_stmt->get_result()->num_rows > 0) {
            $error = "Username already exists. Please choose another.";
        } else {
            // Hash the password
            $hashed_password = password_hash($password, PASSWORD_DEFAULT);
            
            // Insert user
            $stmt = $conn->prepare("INSERT INTO users (username, password, full_name, role) VALUES (?, ?, ?, ?)");
            $stmt->bind_param("ssss", $username, $hashed_password, $full_name, $role);
            
            if ($stmt->execute()) {
                $success = "✅ User created successfully!";
                // Clear form
                $_POST = [];
            } else {
                $error = "Error creating user: " . $conn->error;
            }
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Create User - CyberguardX</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        body { background: #f8f9fa; }
        .header-gradient { background: linear-gradient(135deg, #2c3e50 0%, #3498db 100%); }
        .card { box-shadow: 0 5px 15px rgba(0,0,0,0.1); max-width: 600px; margin: 0 auto; }
        .password-strength { height: 5px; margin-top: 5px; }
        .strength-weak { background: #dc3545; width: 25%; }
        .strength-medium { background: #ffc107; width: 50%; }
        .strength-strong { background: #28a745; width: 100%; }
    </style>
</head>
<body>
    <nav class="navbar navbar-dark header-gradient mb-4">
        <div class="container">
            <a class="navbar-brand" href="users.php">
                <i class="fas fa-arrow-left"></i> Create New User
            </a>
            <span class="navbar-text">
                Admin: <?php echo htmlspecialchars($_SESSION['full_name']); ?>
            </span>
        </div>
    </nav>

    <div class="container">
        <div class="card">
            <div class="card-header bg-primary text-white">
                <h5 class="mb-0"><i class="fas fa-user-plus"></i> Create New User Account</h5>
            </div>
            <div class="card-body">
                <?php if ($error): ?>
                    <div class="alert alert-danger">
                        <i class="fas fa-exclamation-triangle"></i> <?php echo htmlspecialchars($error); ?>
                    </div>
                <?php endif; ?>
                
                <?php if ($success): ?>
                    <div class="alert alert-success">
                        <i class="fas fa-check-circle"></i> <?php echo $success; ?>
                        <div class="mt-2">
                            <a href="users.php" class="btn btn-sm btn-success">View All Users</a>
                            <a href="create-user.php" class="btn btn-sm btn-primary">Create Another</a>
                        </div>
                    </div>
                <?php endif; ?>
                
                <form method="POST" action="" id="userForm">
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Username *</label>
                            <input type="text" class="form-control" name="username" 
                                   value="<?php echo htmlspecialchars($_POST['username'] ?? ''); ?>" 
                                   required pattern="[a-zA-Z0-9_]+"
                                   title="Only letters, numbers, and underscores allowed">
                            <small class="text-muted">Used for login. No spaces or special characters.</small>
                        </div>
                        
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Full Name *</label>
                            <input type="text" class="form-control" name="full_name" 
                                   value="<?php echo htmlspecialchars($_POST['full_name'] ?? ''); ?>" required>
                            <small class="text-muted">User's complete name</small>
                        </div>
                    </div>
                    
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Password *</label>
                            <input type="password" class="form-control" name="password" id="password" 
                                   required minlength="6" onkeyup="checkPasswordStrength()">
                            <div class="password-strength" id="passwordStrength"></div>
                            <small class="text-muted">Minimum 6 characters</small>
                        </div>
                        
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Confirm Password *</label>
                            <input type="password" class="form-control" name="confirm_password" 
                                   required minlength="6">
                        </div>
                    </div>
                    
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Role *</label>
                            <select class="form-select" name="role" required>
                                <option value="sales" <?php echo ($_POST['role'] ?? 'sales') === 'sales' ? 'selected' : ''; ?>>Sales Representative</option>
                                <option value="admin" <?php echo ($_POST['role'] ?? '') === 'admin' ? 'selected' : ''; ?>>Administrator</option>
                            </select>
                            <small class="text-muted">
                                Administrators have full system access. Sales can only manage their own invoices.
                            </small>
                        </div>
                    </div>
                    
                    <div class="mt-4">
                        <button type="submit" class="btn btn-success btn-lg">
                            <i class="fas fa-save"></i> Create User Account
                        </button>
                        <a href="users.php" class="btn btn-secondary">Cancel</a>
                    </div>
                </form>
            </div>
        </div>
        
        <!-- Information Card -->
        <div class="card mt-4">
            <div class="card-body">
                <h6><i class="fas fa-info-circle text-primary"></i> About User Roles:</h6>
                <div class="row">
                    <div class="col-md-6">
                        <div class="p-3 border rounded mb-3">
                            <h6 class="text-danger">Administrator</h6>
                            <ul class="mb-0">
                                <li>Full system access</li>
                                <li>Manage all invoices</li>
                                <li>Create/edit/delete users</li>
                                <li>View reports and analytics</li>
                                <li>Database administration</li>
                            </ul>
                        </div>
                    </div>
                    <div class="col-md-6">
                        <div class="p-3 border rounded">
                            <h6 class="text-success">Sales Representative</h6>
                            <ul class="mb-0">
                                <li>Create invoices</li>
                                <li>View only their own invoices</li>
                                <li>Cannot delete invoices</li>
                                <li>Cannot access user management</li>
                                <li>Cannot view reports</li>
                            </ul>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script>
    function checkPasswordStrength() {
        const password = document.getElementById('password').value;
        const strengthBar = document.getElementById('passwordStrength');
        
        // Reset
        strengthBar.className = 'password-strength';
        
        if (password.length === 0) return;
        
        let strength = 0;
        
        // Length check
        if (password.length >= 6) strength++;
        if (password.length >= 8) strength++;
        
        // Complexity checks
        if (/[A-Z]/.test(password)) strength++;
        if (/[0-9]/.test(password)) strength++;
        if (/[^A-Za-z0-9]/.test(password)) strength++;
        
        // Update visual
        if (strength <= 2) {
            strengthBar.className = 'password-strength strength-weak';
            strengthBar.title = 'Weak password';
        } else if (strength <= 4) {
            strengthBar.className = 'password-strength strength-medium';
            strengthBar.title = 'Medium strength';
        } else {
            strengthBar.className = 'password-strength strength-strong';
            strengthBar.title = 'Strong password';
        }
    }
    
    // Form validation
    document.getElementById('userForm').addEventListener('submit', function(e) {
        const password = document.querySelector('input[name="password"]').value;
        const confirm = document.querySelector('input[name="confirm_password"]').value;
        
        if (password !== confirm) {
            e.preventDefault();
            alert('Passwords do not match!');
            document.querySelector('input[name="confirm_password"]').focus();
        }
    });
    </script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>