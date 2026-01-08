<?php
session_start();
echo "<h2>Session Debug</h2>";
echo "<pre>";
echo "Session ID: " . session_id() . "\n";
echo "Session Status: " . session_status() . "\n";
echo "Session Data:\n";
print_r($_SESSION);
echo "</pre>";

// Test login manually
$_SESSION['user_id'] = 1;
$_SESSION['username'] = 'CyberguardX';
echo "<p>✅ Session set manually</p>";
echo '<a href="index.php">Go to index.php</a>';
?>