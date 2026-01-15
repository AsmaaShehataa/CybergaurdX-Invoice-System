<?php
// includes/session-config.php
function startAppSession() {
    // Use our own session directory
    $sessionDir = dirname(__DIR__) . '/storage/sessions';
    
    // Create directory if it doesn't exist
    if (!is_dir($sessionDir)) {
        mkdir($sessionDir, 0755, true);
    }
    
    // Set session parameters
    ini_set('session.save_path', $sessionDir);
    ini_set('session.name', 'CYBERGUARDX_SESS');
    ini_set('session.use_cookies', 1);
    ini_set('session.use_only_cookies', 1);
    ini_set('session.cookie_httponly', 1);
    ini_set('session.cookie_secure', 0); // Set to 1 if using HTTPS
    ini_set('session.cookie_samesite', 'Lax');
    ini_set('session.gc_maxlifetime', 86400); // 24 hours
    
    // Start session if not already started
    if (session_status() === PHP_SESSION_NONE) {
        session_start();
    }
    
    // Regenerate session ID periodically for security
    if (!isset($_SESSION['last_regeneration'])) {
        $_SESSION['last_regeneration'] = time();
    } elseif (time() - $_SESSION['last_regeneration'] > 1800) { // 30 minutes
        session_regenerate_id(true);
        $_SESSION['last_regeneration'] = time();
    }
}
?>