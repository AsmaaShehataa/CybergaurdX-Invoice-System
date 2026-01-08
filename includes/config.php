<?php
// Database configuration
define('DB_HOST', 'localhost');
define('DB_USER', 'CyberguardX');
define('DB_PASS', 'admin2026');
define('DB_NAME', 'CyberguardX_invoice_system');

// Enable error reporting for development
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Create connection
$conn = new mysqli(DB_HOST, DB_USER, DB_PASS, DB_NAME);

// Check connection
if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

$conn->set_charset("utf8mb4");

// Helper function
function redirect($url) {
    header("Location: $url");
    exit();
}

// DO NOT start session here - let individual pages handle it
?>