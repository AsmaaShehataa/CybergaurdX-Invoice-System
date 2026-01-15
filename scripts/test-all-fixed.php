<?php
// scripts/test-all-fixed.php - USING YOUR ACTUAL CREDENTIALS
echo "=== Testing CyberGuardX Invoice System ===\n\n";

// 1. Test includes/config.php
echo "1. Testing includes/config.php... ";
if (file_exists(dirname(__DIR__) . '/includes/config.php')) {
    // Read the config to see what credentials you're using
    $config_content = file_get_contents(dirname(__DIR__) . '/includes/config.php');
    
    // Extract DB credentials
    preg_match("/define\('DB_USER',\s*'([^']+)'/", $config_content, $user_match);
    preg_match("/define\('DB_PASS',\s*'([^']*)'/", $config_content, $pass_match);
    preg_match("/define\('DB_NAME',\s*'([^']+)'/", $config_content, $name_match);
    
    $db_user = $user_match[1] ?? 'root';
    $db_pass = $pass_match[1] ?? '';
    $db_name = $name_match[1] ?? 'CyberguardX_invoice_system';
    
    echo "✓ File exists (Using: $db_user / " . (strlen($db_pass) > 0 ? "***" : "empty") . " / $db_name)\n";
} else {
    echo "✗ Missing\n";
    exit();
}

// 2. Test session config
echo "2. Testing includes/session-config.php... ";
if (file_exists(dirname(__DIR__) . '/includes/session-config.php')) {
    echo "✓ File exists\n";
} else {
    echo "✗ Missing\n";
}

// 3. Test includes/auth.php
echo "3. Testing includes/auth.php... ";
if (file_exists(dirname(__DIR__) . '/includes/auth.php')) {
    echo "✓ File exists\n";
} else {
    echo "✗ Missing\n";
}

// 4. Test database connection WITH YOUR ACTUAL CREDENTIALS
echo "4. Testing database connection with YOUR credentials...\n";
echo "   Trying: Username='CyberguardX', Password='admin2026', Database='CyberguardX_invoice_system'\n";

try {
    // Try with CyberguardX credentials
    $test_conn = @new mysqli('localhost', 'CyberguardX', 'admin2026', 'CyberguardX_invoice_system');
    
    if ($test_conn->connect_error) {
        echo "   ✗ Failed: " . $test_conn->connect_error . "\n";
        
        // Try common alternatives
        echo "   Trying common alternatives:\n";
        $attempts = [
            ['root', ''],
            ['root', 'root'],
            ['root', 'password'],
            ['CyberguardX', ''],
        ];
        
        foreach ($attempts as $attempt) {
            list($user, $pass) = $attempt;
            $conn = @new mysqli('localhost', $user, $pass, 'CyberguardX_invoice_system');
            if (!$conn->connect_error) {
                echo "   ✓ Connected with: $user / " . (strlen($pass) > 0 ? "***" : "empty") . "\n";
                $conn->close();
                break;
            }
        }
    } else {
        echo "   ✓ Connected successfully with CyberguardX/admin2026\n";
        $test_conn->close();
    }
} catch (Exception $e) {
    echo "   ✗ Error: " . $e->getMessage() . "\n";
}

// 5. Check if MySQL service is running
echo "5. Checking if MySQL service is running... ";
exec("mysqladmin ping 2>/dev/null", $output, $return_code);
if ($return_code === 0) {
    echo "✓ MySQL is running\n";
} else {
    echo "✗ MySQL may not be running. Start it with: mysql.server start\n";
}

// 6. Check database and tables
echo "6. Checking database and tables...\n";
try {
    // Try to connect with the most likely working credentials
    $conn = @new mysqli('localhost', 'CyberguardX', 'admin2026', 'CyberguardX_invoice_system');
    
    if (!$conn->connect_error) {
        // Check if database exists
        $result = $conn->query("SELECT DATABASE() as db");
        $db = $result->fetch_assoc()['db'];
        echo "   - Connected to database: " . ($db ? "✓ '$db'" : "✗ None") . "\n";
        
        // Check tables
        $tables = ['users', 'clients', 'invoices', 'invoice_items'];
        foreach ($tables as $table) {
            $result = $conn->query("SHOW TABLES LIKE '$table'");
            echo "   - Table '$table': " . ($result->num_rows > 0 ? "✓ Exists" : "✗ Missing") . "\n";
        }
        $conn->close();
    } else {
        echo "   ⚠️ Cannot connect to check tables\n";
    }
} catch (Exception $e) {
    echo "   ⚠️ Error: " . $e->getMessage() . "\n";
}

// 7. Test main files
echo "\n7. Testing main files in public/:\n";
$files = [
    'index.php',
    'dashboard.php',
    'create-invoice.php',
    'view-invoice.php',
    'save-invoice.php',
    'invoices.php',
    'users.php',
    'logout.php',
    'update-status.php',
    'edit-invoice.php',
    'edit-user.php',
    'export-invoices.php'
];

$missing = [];
foreach ($files as $file) {
    $path = dirname(__DIR__) . '/public/' . $file;
    if (file_exists($path)) {
        echo "   - $file: ✓ Exists\n";
    } else {
        echo "   - $file: ✗ Missing\n";
        $missing[] = $file;
    }
}

echo "\n=== Test Complete ===\n";
echo "Summary:\n";

if (count($missing) > 0) {
    echo "⚠️  Missing files: " . implode(', ', $missing) . "\n";
}

echo "\nTo fix database issues:\n";
echo "1. First, check if MySQL user 'CyberguardX' exists:\n";
echo "   mysql -u root -p -e \"SELECT User, Host FROM mysql.user WHERE User='CyberguardX';\"\n\n";
echo "2. If user doesn't exist, create it:\n";
echo "   mysql -u root -p -e \"CREATE USER 'CyberguardX'@'localhost' IDENTIFIED BY 'admin2026';\"\n";
echo "   mysql -u root -p -e \"GRANT ALL PRIVILEGES ON CyberguardX_invoice_system.* TO 'CyberguardX'@'localhost';\"\n";
echo "   mysql -u root -p -e \"FLUSH PRIVILEGES;\"\n\n";
echo "3. Import database if missing:\n";
echo "   mysql -u CyberguardX -padmin2026 CyberguardX_invoice_system < SQL-dump-backup.sql\n\n";
echo "4. Test login:\n";
echo "   http://localhost/Invoice/public/\n";
?>
