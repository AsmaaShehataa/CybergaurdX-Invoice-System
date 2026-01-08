<?php
// ========== SESSION SETUP ==========
// Fix session path for macOS
ini_set('session.save_path', '/tmp');

// Start session
session_start();

// Debug info
$debug_info = [
    'session_id' => session_id(),
    'session_path' => session_save_path(),
    'can_write' => is_writable(session_save_path()) ? 'YES' : 'NO',
    'session_data' => $_SESSION
];

// ========== CHECK IF ALREADY LOGGED IN ==========
if (isset($_SESSION['user_id'])) {
    // Already logged in, go to dashboard
    header('Location: dashboard.php');
    exit();
}

// ========== HANDLE LOGIN FORM ==========
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    require_once 'includes/config.php';
    
    $username = trim($_POST['username'] ?? '');
    $password = $_POST['password'] ?? '';
    
    if (empty($username) || empty($password)) {
        $error = "Please enter username and password";
    } else {
        $stmt = $conn->prepare("SELECT id, username, password, full_name, role FROM users WHERE username = ?");
        $stmt->bind_param("s", $username);
        $stmt->execute();
        $result = $stmt->get_result();
        
        if ($result->num_rows === 1) {
            $user = $result->fetch_assoc();
            
            if (password_verify($password, $user['password'])) {
                // Login successful
                $_SESSION['user_id'] = $user['id'];
                $_SESSION['username'] = $user['username'];
                $_SESSION['full_name'] = $user['full_name'];
                $_SESSION['role'] = $user['role'];
                $_SESSION['login_time'] = time();
                
                // Force session save
                session_write_close();
                
                // Redirect to dashboard
                header('Location: dashboard.php');
                exit();
            } else {
                $error = "Invalid password";
            }
        } else {
            $error = "User not found";
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login - CyberguardX Invoice System</title>
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; font-family: -apple-system, sans-serif; }
        body { background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); min-height: 100vh; display: flex; justify-content: center; align-items: center; padding: 20px; }
        .login-container { background: white; border-radius: 20px; box-shadow: 0 20px 60px rgba(0,0,0,0.3); max-width: 400px; width: 100%; overflow: hidden; }
        .login-header { background: linear-gradient(135deg, #4f46e5 0%, #7c3aed 100%); color: white; padding: 40px 30px; text-align: center; }
        .login-header h1 { font-size: 28px; margin-bottom: 10px; }
        .login-form { padding: 30px; }
        .form-group { margin-bottom: 20px; }
        label { display: block; margin-bottom: 8px; color: #4a5568; font-weight: 500; }
        input { width: 100%; padding: 12px; border: 2px solid #e2e8f0; border-radius: 8px; font-size: 16px; }
        input:focus { outline: none; border-color: #4f46e5; box-shadow: 0 0 0 3px rgba(79,70,229,0.1); }
        .login-btn { width: 100%; padding: 14px; background: linear-gradient(135deg, #4f46e5 0%, #7c3aed 100%); color: white; border: none; border-radius: 8px; font-size: 16px; font-weight: 600; cursor: pointer; }
        .login-btn:hover { transform: translateY(-2px); box-shadow: 0 10px 20px rgba(79,70,229,0.3); }
        .error { background: #fed7d7; color: #9b2c2c; padding: 12px; border-radius: 8px; margin-bottom: 20px; }
        .test-creds { margin-top: 20px; padding: 15px; background: #f7fafc; border-radius: 8px; font-size: 14px; }
        .debug { margin-top: 15px; padding: 10px; background: #e2e8f0; border-radius: 5px; font-size: 11px; font-family: monospace; }
    </style>
</head>
<body>
    <div class="login-container">
        <div class="login-header">
            <h1>CyberGuardX</h1>
            <p>Invoice System Login</p>
        </div>
        
        <div class="login-form">
            <?php if ($error): ?>
                <div class="error">⚠️ <?php echo htmlspecialchars($error); ?></div>
            <?php endif; ?>
            
            <form method="POST" action="">
                <div class="form-group">
                    <label for="username">Username</label>
                    <input type="text" id="username" name="username" required value="CyberguardX">
                </div>
                
                <div class="form-group">
                    <label for="password">Password</label>
                    <input type="password" id="password" name="password" required value="admin2026">
                </div>
                
                <button type="submit" class="login-btn">Login</button>
            </form>
            
            <div class="test-creds">
                <strong>Test Accounts:</strong><br>
                • Admin: CyberguardX / admin2026<br>
                • Sales: Aya-SalesIndoor / (check DB)
            </div>
            
            <div class="debug">
                Session: <?php echo $debug_info['session_id']; ?><br>
                Can Write: <?php echo $debug_info['can_write']; ?>
            </div>
        </div>
    </div>
</body>
</html>