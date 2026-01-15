<?php
// edit-user.php - Edit User (Admin Only)
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

$user_id = intval($_GET['id'] ?? 0);

if (!$user_id) {
    header('Location: users.php');
    exit();
}

// Get user data
$stmt = $conn->prepare("SELECT id, username, full_name, role, is_active FROM users WHERE id = ?");
$stmt->bind_param("i", $user_id);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows === 0) {
    $_SESSION['error'] = "User not found";
    header('Location: users.php');
    exit();
}

$user = $result->fetch_assoc();

// Prevent editing self's role/status
$is_self = ($user_id == $_SESSION['user_id']);

$error = '';
$success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $full_name = trim($_POST['full_name'] ?? '');
    $role = $_POST['role'] ?? $user['role'];
    $is_active = isset($_POST['is_active']) ? 1 : 0;
    $change_password = isset($_POST['change_password']);
    
    // If editing self, protect role and status
    if ($is_self) {
        $role = 'admin'; // Force admin
        $is_active = 1; // Force active
    }
    
    // Validation
    if (empty($full_name)) {
        $error = "Full name is required";
    } else {
        // Update user
        if ($change_password) {
            $new_password = $_POST['new_password'] ?? '';
            $confirm_password = $_POST['confirm_password'] ?? '';
            
            if (strlen($new_password) < 6) {
                $error = "New password must be at least 6 characters";
            } elseif ($new_password !== $confirm_password) {
                $error = "Passwords do not match";
            } else {
                $hashed_password = password_hash($new_password, PASSWORD_DEFAULT);
                $stmt = $conn->prepare("UPDATE users SET full_name = ?, role = ?, is_active = ?, password = ? WHERE id = ?");
                $stmt->bind_param("ssisi", $full_name, $role, $is_active, $hashed_password, $user_id);
            }
        } else {
            $stmt = $conn->prepare("UPDATE users SET full_name = ?, role = ?, is_active = ? WHERE id = ?");
            $stmt->bind_param("ssii", $full_name, $role, $is_active, $user_id);
        }
        
        if (empty($error)) {
            if ($stmt->execute()) {
                $success = "✅ User updated successfully!";
                // Refresh user data
                $user['full_name'] = $full_name;
                $user['role'] = $role;
                $user['is_active'] = $is_active;
            } else {
                $error = "Error updating user: " . $conn->error;
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
    <title>Edit User - CyberguardX</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        body { background: #f8f9fa; }
        .header-gradient { background: linear-gradient(135deg, #2c3e50 0%, #3498db 100%); }
        .card { box-shadow: 0 5px 15px rgba(0,0,0,0.1); max-width: 600px; margin: 0 auto; }
        .self-warning { border-left: 4px solid #ffc107; }
    </style>
</head>
<body>
    <nav class="navbar navbar-dark header-gradient mb-4">
        <div class="container">
            <a class="navbar-brand" href="users.php">
                <i class="fas fa-arrow-left"></i> Edit User
            </a>
            <span class="navbar-text">
                Editing: <?php echo htmlspecialchars($user['username']); ?>
            </span>
        </div>
    </nav>

    <div class="container">
        <div class="card">
            <div class="card-header bg-primary text-white">
                <h5 class="mb-0"><i class="fas fa-user-edit"></i> Edit User Account</h5>
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
                    </div>
                <?php endif; ?>
                
                <?php if ($is_self): ?>
                    <div class="alert alert-warning self-warning">
                        <i class="fas fa-exclamation-triangle"></i>
                        <strong>You are editing your own account.</strong> You cannot change your role or deactivate yourself.
                    </div>
                <?php endif; ?>
                
                <form method="POST" action="">
                    <div class="row mb-3">
                        <div class="col-md-6">
                            <label class="form-label">Username</label>
                            <input type="text" class="form-control" value="<?php echo htmlspecialchars($user['username']); ?>" disabled>
                            <small class="text-muted">Username cannot be changed</small>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">User ID</label>
                            <input type="text" class="form-control" value="<?php echo $user['id']; ?>" disabled>
                        </div>
                    </div>
                    
                    <div class="mb-3">
                        <label class="form-label">Full Name *</label>
                        <input type="text" class="form-control" name="full_name" 
                               value="<?php echo htmlspecialchars($user['full_name']); ?>" required>
                    </div>
                    
                    <div class="row mb-3">
                        <div class="col-md-6">
                            <label class="form-label">Role *</label>
                            <select class="form-select" name="role" <?php echo $is_self ? 'disabled' : ''; ?> required>
                                <option value="sales" <?php echo $user['role'] === 'sales' ? 'selected' : ''; ?>>Sales Representative</option>
                                <option value="admin" <?php echo $user['role'] === 'admin' ? 'selected' : ''; ?>>Administrator</option>
                            </select>
                            <?php if ($is_self): ?>
                                <input type="hidden" name="role" value="admin">
                            <?php endif; ?>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Status</label>
                            <div class="form-check form-switch">
                                <input class="form-check-input" type="checkbox" role="switch" 
                                       name="is_active" value="1" 
                                       <?php echo $user['is_active'] ? 'checked' : ''; ?>
                                       <?php echo $is_self ? 'disabled' : ''; ?>>
                                <label class="form-check-label">
                                    <?php echo $user['is_active'] ? 'Active' : 'Inactive'; ?>
                                </label>
                            </div>
                            <?php if ($is_self): ?>
                                <input type="hidden" name="is_active" value="1">
                            <?php endif; ?>
                        </div>
                    </div>
                    
                    <!-- Password Change Section -->
                    <div class="card mb-3">
                        <div class="card-header">
                            <div class="form-check">
                                <input class="form-check-input" type="checkbox" name="change_password" 
                                       id="changePasswordCheck" onchange="togglePasswordFields()">
                                <label class="form-check-label" for="changePasswordCheck">
                                    Change Password
                                </label>
                            </div>
                        </div>
                        <div class="card-body" id="passwordFields" style="display: none;">
                            <div class="row">
                                <div class="col-md-6 mb-3">
                                    <label class="form-label">New Password</label>
                                    <input type="password" class="form-control" name="new_password" minlength="6">
                                </div>
                                <div class="col-md-6 mb-3">
                                    <label class="form-label">Confirm Password</label>
                                    <input type="password" class="form-control" name="confirm_password" minlength="6">
                                </div>
                            </div>
                            <small class="text-muted">Leave blank if you don't want to change the password</small>
                        </div>
                    </div>
                    
                    <div class="mt-4">
                        <button type="submit" class="btn btn-primary btn-lg">
                            <i class="fas fa-save"></i> Save Changes
                        </button>
                        <a href="users.php" class="btn btn-secondary">Cancel</a>
                        
                        <?php if (!$is_self): ?>
                            <a href="users.php?delete_id=<?php echo $user_id; ?>" 
                               class="btn btn-danger float-end"
                               onclick="return confirm('Are you sure you want to delete this user?\\n\\nThis action cannot be undone!')">
                                <i class="fas fa-trash"></i> Delete User
                            </a>
                        <?php endif; ?>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <script>
    function togglePasswordFields() {
        const checkbox = document.getElementById('changePasswordCheck');
        const fields = document.getElementById('passwordFields');
        fields.style.display = checkbox.checked ? 'block' : 'none';
        
        // Clear password fields when hiding
        if (!checkbox.checked) {
            document.querySelectorAll('#passwordFields input').forEach(input => {
                input.value = '';
            });
        }
    }
    </script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>