<?php
// includes/session.php - Centralized session and authentication management
// This is the ONLY file for session/authentication functions

/**
 * Start application session with secure configuration
 */
function startAppSession() {
    // Prevent multiple session starts
    if (session_status() === PHP_SESSION_ACTIVE) {
        return;
    }
    
    // Use our own session directory
    $sessionDir = dirname(__DIR__) . '/storage/sessions';
    
    // Create directory if it doesn't exist
    if (!is_dir($sessionDir)) {
        mkdir($sessionDir, 0755, true);
    }
    
    // Set session parameters BEFORE starting
    ini_set('session.save_path', $sessionDir);
    ini_set('session.name', 'CYBERGUARDX_SESS');
    ini_set('session.use_cookies', 1);
    ini_set('session.use_only_cookies', 1);
    ini_set('session.cookie_httponly', 1);
    ini_set('session.cookie_secure', 0); // Set to 1 if using HTTPS
    ini_set('session.cookie_samesite', 'Lax');
    ini_set('session.gc_maxlifetime', 86400); // 24 hours
    
    // Prevent session fixation
    ini_set('session.use_trans_sid', 0);
    ini_set('session.cache_limiter', 'nocache');
    
    // Start session
    session_start();
    
    // Security: Regenerate session ID periodically
    if (!isset($_SESSION['last_regeneration'])) {
        $_SESSION['last_regeneration'] = time();
    } elseif (time() - $_SESSION['last_regeneration'] > 1800) { // 30 minutes
        session_regenerate_id(true);
        $_SESSION['last_regeneration'] = time();
    }
}

/**
 * Check if user is logged in
 */
function isLoggedIn() {
    return isset($_SESSION['user_id']);
}

/**
 * Redirect to login if not authenticated
 */
function requireLogin() {
    if (!isLoggedIn()) {
        header('Location: index.php');
        exit();
    }
}

/**
 * Check if user is admin
 */
function isAdmin() {
    return isset($_SESSION['role']) && $_SESSION['role'] === 'admin';
}

/**
 * Redirect if not admin
 */
function requireAdmin() {
    requireLogin();
    if (!isAdmin()) {
        header('Location: dashboard.php');
        exit();
    }
}

/**
 * Clean input data to prevent XSS
 */
function cleanInput($data) {
    return htmlspecialchars(trim($data), ENT_QUOTES, 'UTF-8');
}

/**
 * Generate CSRF token
 */
function generateCsrfToken() {
    if (!isset($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

/**
 * Validate CSRF token
 */
function validateCsrfToken($token) {
    return isset($_SESSION['csrf_token']) && hash_equals($_SESSION['csrf_token'], $token);
}

/**
 * Destroy session completely (for logout)
 */
function destroySession() {
    // Unset all session variables
    $_SESSION = [];
    
    // Destroy session cookie
    if (ini_get("session.use_cookies")) {
        $params = session_get_cookie_params();
        setcookie(
            session_name(),
            '',
            time() - 42000,
            $params["path"],
            $params["domain"],
            $params["secure"],
            $params["httponly"]
        );
    }
    
    // Destroy session
    session_destroy();
}

/**
 * Set a flash message (temporary message for one request)
 */
function setFlashMessage($type, $message) {
    $_SESSION['flash_message'] = [
        'type' => $type,
        'message' => $message
    ];
}

/**
 * Get and clear flash message
 */
function getFlashMessage() {
    if (isset($_SESSION['flash_message'])) {
        $message = $_SESSION['flash_message'];
        unset($_SESSION['flash_message']);
        return $message;
    }
    return null;
}

/**
 * Get current user's information safely
 */
function getCurrentUserInfo() {
    return [
        'id' => $_SESSION['user_id'] ?? null,
        'username' => $_SESSION['username'] ?? null,
        'full_name' => $_SESSION['full_name'] ?? null,
        'role' => $_SESSION['role'] ?? null
    ];
}

/**
 * Check if current user can access invoice
 * Admin can access all, sales can only access their own
 */
function canAccessInvoice($invoiceUserId) {
    if (!isLoggedIn()) return false;
    if (isAdmin()) return true;
    return (getCurrentUserInfo()['id'] == $invoiceUserId);
}
?>