<?php
require_once 'includes/config.php';

// Check if admin already exists
$check_sql = "SELECT id FROM users WHERE username = 'CyberguardX'";
$result = $conn->query($check_sql);

if ($result->num_rows > 0) {
    echo "⚠️ Admin user already exists! Skipping creation.<br>";
    echo "To reset, delete the user from database first.<br>";
    exit();
}

// Create admin user
$username = 'CyberguardX';
$password = 'admin2026';
$full_name = 'System Administrator';

// Hash password
$hashed_password = password_hash($password, PASSWORD_DEFAULT);

// Use 'admin' as role, not 'CyberguardX'
$sql = "INSERT INTO users (username, password, full_name, role) VALUES (?, ?, ?, 'admin')";
$stmt = $conn->prepare($sql);
$stmt->bind_param("sss", $username, $hashed_password, $full_name);

if ($stmt->execute()) {
    echo "✅ Admin user created successfully!<br>";
    echo "Username: CyberguardX<br>";
    echo "Password: admin2026<br>";
    echo "Role: admin<br><br>";
    
    // Verify the user was created
    $verify_sql = "SELECT id, username, role FROM users WHERE username = 'CyberguardX'";
    $verify_result = $conn->query($verify_sql);
    
    if ($verify_row = $verify_result->fetch_assoc()) {
        echo "✅ Verification:<br>";
        echo "User ID: " . $verify_row['id'] . "<br>";
        echo "Username: " . $verify_row['username'] . "<br>";
        echo "Role: " . $verify_row['role'] . "<br>";
    }
    
} else {
    echo "❌ Error creating admin user: " . $conn->error . "<br>";
    
    // Show the exact error
    if ($conn->errno == 1062) {
        echo "Duplicate entry - user already exists.<br>";
    }
}

$conn->close();
?>