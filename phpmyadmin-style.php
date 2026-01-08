<?php
// simple-admin.php - Basic database admin panel
session_start();
require_once 'includes/config.php';

// Simple authentication
if (!isset($_SESSION['admin_logged_in'])) {
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        $username = $_POST['username'] ?? '';
        $password = $_POST['password'] ?? '';
        
        // Hardcoded admin credentials for this page only
        if ($username === 'CyberguardX' && $password === 'admin2026') {
            $_SESSION['admin_logged_in'] = true;
        } else {
            $error = "Invalid credentials";
        }
    }
    
    // Show login form
    ?>
    <!DOCTYPE html>
    <html>
    <head>
        <title>Database Admin Login</title>
        <style>
            body { font-family: Arial; background: #f5f5f5; padding: 50px; }
            .login-box { background: white; padding: 30px; border-radius: 10px; max-width: 400px; margin: 0 auto; box-shadow: 0 0 10px rgba(0,0,0,0.1); }
            input { width: 100%; padding: 10px; margin: 10px 0; border: 1px solid #ddd; border-radius: 5px; }
            button { background: #4CAF50; color: white; padding: 10px 20px; border: none; border-radius: 5px; cursor: pointer; width: 100%; }
            .error { color: red; margin: 10px 0; }
        </style>
    </head>
    <body>
        <div class="login-box">
            <h2>Database Admin Login</h2>
            <?php if (isset($error)) echo "<div class='error'>$error</div>"; ?>
            <form method="POST">
                <input type="text" name="username" placeholder="Username" required value="CyberguardX">
                <input type="password" name="password" placeholder="Password" required value="admin2026">
                <button type="submit">Login</button>
            </form>
        </div>
    </body>
    </html>
    <?php
    exit();
}
?>

<!DOCTYPE html>
<html>
<head>
    <title>Database Admin Panel</title>
    <style>
        body { font-family: Arial; background: #f5f5f5; padding: 20px; }
        .container { max-width: 1200px; margin: 0 auto; }
        .card { background: white; padding: 20px; margin: 20px 0; border-radius: 10px; box-shadow: 0 0 10px rgba(0,0,0,0.1); }
        table { width: 100%; border-collapse: collapse; margin: 20px 0; }
        th, td { padding: 12px; text-align: left; border-bottom: 1px solid #ddd; }
        th { background: #f8f9fa; }
        tr:hover { background: #f5f5f5; }
        .btn { padding: 8px 15px; border: none; border-radius: 5px; cursor: pointer; margin: 5px; }
        .btn-primary { background: #007bff; color: white; }
        .btn-danger { background: #dc3545; color: white; }
        .btn-success { background: #28a745; color: white; }
        .tabs { display: flex; border-bottom: 2px solid #dee2e6; }
        .tab { padding: 10px 20px; cursor: pointer; border: 1px solid transparent; }
        .tab.active { background: white; border: 1px solid #dee2e6; border-bottom: none; border-radius: 5px 5px 0 0; }
        .tab-content { display: none; }
        .tab-content.active { display: block; }
        .sql-box { width: 100%; height: 150px; font-family: monospace; padding: 10px; }
    </style>
</head>
<body>
    <div class="container">
        <h1>📊 Database Admin Panel</h1>
        <p>Connected to: <?php echo DB_NAME; ?> as <?php echo DB_USER; ?></p>
        
        <div class="tabs">
            <div class="tab active" onclick="showTab('overview')">📈 Overview</div>
            <div class="tab" onclick="showTab('tables')">🗂️ Tables</div>
            <div class="tab" onclick="showTab('query')">🔍 SQL Query</div>
            <div class="tab" onclick="showTab('users')">👥 Users</div>
            <div class="tab" style="margin-left: auto;">
                <a href="?logout=1" style="color: #dc3545; text-decoration: none;">🚪 Logout</a>
            </div>
        </div>
        
        <!-- Overview Tab -->
        <div id="overview" class="tab-content active card">
            <h2>Database Overview</h2>
            <?php
            // Get table counts
            $tables = ['users', 'clients', 'invoices', 'invoice_items'];
            echo "<table>";
            echo "<tr><th>Table</th><th>Records</th><th>Size</th><th>Actions</th></tr>";
            
            foreach ($tables as $table) {
                $count_result = $conn->query("SELECT COUNT(*) as count FROM $table");
                $count = $count_result->fetch_assoc()['count'];
                
                $size_result = $conn->query("SELECT ROUND((DATA_LENGTH + INDEX_LENGTH) / 1024, 2) as size_kb FROM information_schema.TABLES WHERE TABLE_SCHEMA = '" . DB_NAME . "' AND TABLE_NAME = '$table'");
                $size = $size_result->fetch_assoc();
                $size_display = isset($size['size_kb']) ? $size['size_kb'] . ' KB' : 'N/A';
                
                echo "<tr>";
                echo "<td><strong>$table</strong></td>";
                echo "<td>$count records</td>";
                echo "<td>$size_display</td>";
                echo "<td><button class='btn btn-primary' onclick=\"runQuery('SELECT * FROM $table LIMIT 10')\">View</button></td>";
                echo "</tr>";
            }
            echo "</table>";
            ?>
        </div>
        
        <!-- Tables Tab -->
        <div id="tables" class="tab-content card">
            <h2>Table Structures</h2>
            <?php
            foreach ($tables as $table) {
                echo "<h3>$table</h3>";
                $result = $conn->query("DESCRIBE $table");
                echo "<table>";
                echo "<tr><th>Field</th><th>Type</th><th>Null</th><th>Key</th><th>Default</th></tr>";
                while ($row = $result->fetch_assoc()) {
                    echo "<tr>";
                    echo "<td>{$row['Field']}</td>";
                    echo "<td><code>{$row['Type']}</code></td>";
                    echo "<td>{$row['Null']}</td>";
                    echo "<td>{$row['Key']}</td>";
                    echo "<td>{$row['Default']}</td>";
                    echo "</tr>";
                }
                echo "</table><hr>";
            }
            ?>
        </div>
        
        <!-- SQL Query Tab -->
        <div id="query" class="tab-content card">
            <h2>SQL Query Runner</h2>
            <form method="POST">
                <textarea name="sql_query" class="sql-box" placeholder="SELECT * FROM users LIMIT 10;"><?php echo $_POST['sql_query'] ?? ''; ?></textarea><br>
                <button type="submit" class="btn btn-primary">Execute Query</button>
                <button type="button" class="btn btn-success" onclick="document.querySelector('textarea').value='SELECT * FROM users LIMIT 10;'">Example</button>
            </form>
            
            <?php
            if ($_SERVER['REQUEST_METHOD'] === 'POST' && !empty($_POST['sql_query'])) {
                $sql = $_POST['sql_query'];
                echo "<h3>Results:</h3>";
                echo "<p><code>$sql</code></p>";
                
                $result = $conn->query($sql);
                
                if ($result) {
                    if ($result === TRUE) {
                        echo "<div class='alert alert-success'>✅ Query executed successfully. Affected rows: " . $conn->affected_rows . "</div>";
                    } else {
                        echo "<table border='1'>";
                        $fields = $result->fetch_fields();
                        echo "<tr>";
                        foreach ($fields as $field) {
                            echo "<th>" . htmlspecialchars($field->name) . "</th>";
                        }
                        echo "</tr>";
                        
                        while ($row = $result->fetch_assoc()) {
                            echo "<tr>";
                            foreach ($row as $cell) {
                                echo "<td>" . htmlspecialchars($cell) . "</td>";
                            }
                            echo "</tr>";
                        }
                        echo "</table>";
                        echo "<p>Total rows: " . $result->num_rows . "</p>";
                    }
                } else {
                    echo "<div class='alert alert-danger'>❌ Error: " . htmlspecialchars($conn->error) . "</div>";
                }
            }
            ?>
        </div>
        
        <!-- Users Tab -->
        <div id="users" class="tab-content card">
            <h2>User Management</h2>
            <button class="btn btn-success" onclick="showAddUserForm()">➕ Add New User</button>
            
            <?php
            $users_result = $conn->query("SELECT id, username, full_name, role, created_at FROM users ORDER BY id");
            if ($users_result->num_rows > 0) {
                echo "<table>";
                echo "<tr><th>ID</th><th>Username</th><th>Full Name</th><th>Role</th><th>Created</th><th>Actions</th></tr>";
                while ($user = $users_result->fetch_assoc()) {
                    echo "<tr>";
                    echo "<td>{$user['id']}</td>";
                    echo "<td>{$user['username']}</td>";
                    echo "<td>{$user['full_name']}</td>";
                    echo "<td>{$user['role']}</td>";
                    echo "<td>{$user['created_at']}</td>";
                    echo "<td>
                            <button class='btn btn-primary btn-sm' onclick=\"editUser({$user['id']})\">Edit</button>
                            <button class='btn btn-danger btn-sm' onclick=\"deleteUser({$user['id']})\">Delete</button>
                          </td>";
                    echo "</tr>";
                }
                echo "</table>";
            }
            ?>
        </div>
    </div>
    
    <script>
    function showTab(tabName) {
        // Hide all tabs
        document.querySelectorAll('.tab-content').forEach(tab => {
            tab.classList.remove('active');
        });
        document.querySelectorAll('.tab').forEach(tab => {
            tab.classList.remove('active');
        });
        
        // Show selected tab
        document.getElementById(tabName).classList.add('active');
        event.target.classList.add('active');
    }
    
    function runQuery(query) {
        document.querySelector('[name="sql_query"]').value = query;
        showTab('query');
        document.querySelector('form').submit();
    }
    
    function showAddUserForm() {
        const form = `
            <div id="addUserForm" style="margin: 20px 0; padding: 20px; background: #f8f9fa; border-radius: 5px;">
                <h3>Add New User</h3>
                <form method="POST" action="add-user.php">
                    <input type="text" name="username" placeholder="Username" required style="padding: 8px; margin: 5px; width: 200px;">
                    <input type="password" name="password" placeholder="Password" required style="padding: 8px; margin: 5px; width: 200px;">
                    <input type="text" name="full_name" placeholder="Full Name" required style="padding: 8px; margin: 5px; width: 200px;">
                    <select name="role" style="padding: 8px; margin: 5px;">
                        <option value="sales">Sales</option>
                        <option value="admin">Admin</option>
                    </select>
                    <button type="submit" class="btn btn-success">Add User</button>
                    <button type="button" class="btn btn-danger" onclick="document.getElementById('addUserForm').remove()">Cancel</button>
                </form>
            </div>
        `;
        document.querySelector('#users').innerHTML += form;
    }
    
    function editUser(id) {
        alert('Edit user ' + id + ' - This would open an edit form in a real implementation');
    }
    
    function deleteUser(id) {
        if (confirm('Are you sure you want to delete user ' + id + '?')) {
            window.location.href = 'delete-user.php?id=' + id;
        }
    }
    </script>
</body>
</html>
<?php
// Handle logout
if (isset($_GET['logout'])) {
    session_destroy();
    header('Location: simple-admin.php');
    exit();
}
?>