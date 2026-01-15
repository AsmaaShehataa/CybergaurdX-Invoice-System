<?php
// logout.php
// Fix session path for macOS

// Start session
// Use central session configuration
require_once dirname(__DIR__) . '/includes/session-config.php';
startAppSession();

// Destroy all session data
$_SESSION = [];

// Destroy the session
session_destroy();

// Clear session cookie
if (isset($_COOKIE[session_name()])) {
    setcookie(session_name(), '', time() - 3600, '/');
}

// Redirect to login page
header('Location: index.php');
exit();
?>