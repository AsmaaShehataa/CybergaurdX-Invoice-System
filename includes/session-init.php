<?php
// includes/session-init.php - Initialize session properly

function initSession() {
    // Check if session is already started
    if (session_status() == PHP_SESSION_NONE) {
        // Set session path
        $session_path = dirname(__DIR__) . '/storage/sessions';
        
        // Ensure session directory exists
        if (!is_dir($session_path)) {
            mkdir($session_path, 0777, true);
        }
        
        // Configure session settings BEFORE starting
        ini_set('session.save_path', $session_path);
        ini_set('session.cookie_httponly', 1);
        ini_set('session.use_only_cookies', 1);
        ini_set('session.cookie_secure', 0); // Set to 1 if using HTTPS
        ini_set('session.cookie_samesite', 'Strict');
        
        // Start session
        session_start();
    }
    return true;
}
