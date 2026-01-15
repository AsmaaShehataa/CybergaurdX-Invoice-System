<?php
ini_set('session.save_path', '/tmp');
session_start();
require_once dirname(__DIR__) . '/includes/config.php';

// Quick check - don't require login for this
echo "<pre>";
echo "=== Database Users Table Check ===\n\n";

// Check if users table exists and its structure
$result = $conn->query("DESCRIBE users");
if ($result) {
    echo "📋 Users Table Structure:\n";
    while ($row = $result->fetch_assoc()) {
        echo "- {$row['Field']} : {$row['Type']}\n";
    }
    echo "\n";
    
    // Show existing users
    $users = $conn->query("SELECT id, username, full_name, role FROM users");
    echo "👥 Existing Users:\n";
    while ($user = $users->fetch_assoc()) {
        echo "- ID: {$user['id']} | Username: {$user['username']} | ";
        echo "Name: {$user['full_name']} | Role: {$user['role']}\n";
    }
} else {
    echo "❌ Users table doesn't exist or has issues.\n";
    echo "Error: " . $conn->error . "\n";
    
    // Try to create it
    echo "\nAttempting to create users table...\n";
    $sql = "CREATE TABLE IF NOT EXISTS users (
        id INT AUTO_INCREMENT PRIMARY KEY,
        username VARCHAR(50) UNIQUE NOT NULL,
        password VARCHAR(255) NOT NULL,
        full_name VARCHAR(100) NOT NULL,
        role ENUM('admin', 'sales') DEFAULT 'sales',
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        is_active BOOLEAN DEFAULT TRUE
    )";
    
    if ($conn->query($sql)) {
        echo "✅ Users table created successfully!\n";
        
        // Insert default admin if none exists
        $hashed_pass = password_hash('admin2026', PASSWORD_DEFAULT);
        $conn->query("INSERT INTO users (username, password, full_name, role) 
                     VALUES ('CyberguardX', '$hashed_pass', 'System Administrator', 'admin')
                     ON DUPLICATE KEY UPDATE password = '$hashed_pass'");
        echo "✅ Default admin user created/updated.\n";
    } else {
        echo "❌ Failed to create table: " . $conn->error . "\n";
    }
}

echo "</pre>";
?>