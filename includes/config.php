<?php
// includes/config.php

// Check if composer autoload exists
$autoloadPath = dirname(__DIR__) . '/vendor/autoload.php';
if (!file_exists($autoloadPath)) {
    die("<div style='padding: 20px; background: #fff3cd; border: 2px solid #ffc107; border-radius: 10px; margin: 20px;'>
        <h3>⚠️ Composer Not Set Up</h3>
        <p>Please run these commands in your project directory:</p>
        <pre style='background: #f8f9fa; padding: 10px; border-radius: 5px;'>
cd " . htmlspecialchars(dirname(__DIR__)) . "
composer require vlucas/phpdotenv
        </pre>
    </div>");
}

require_once $autoloadPath;

// Load .env file
use Dotenv\Dotenv;

$dotenv = Dotenv::createImmutable(dirname(__DIR__));
try {
    $dotenv->load();
    
    // You can validate required variables if you want
    $dotenv->required(['DB_HOST', 'DB_USER', 'DB_PASS', 'DB_NAME']);
    
} catch (Exception $e) {
    die("<div style='padding: 20px; background: #fff3cd; border: 2px solid #ffc107; border-radius: 10px; margin: 20px;'>
        <h3>⚠️ .env File Error</h3>
        <p>" . htmlspecialchars($e->getMessage()) . "</p>
        <p>Make sure you have a .env file in: " . htmlspecialchars(dirname(__DIR__)) . "</p>
        <p>Example .env content:</p>
        <pre style='background: #f8f9fa; padding: 10px; border-radius: 5px;'>
DB_HOST=localhost
DB_USER=your_database_username
DB_PASS=your_secure_password
DB_NAME=your_database_name
        </pre>
    </div>");
}

// Application constants
define('APP_NAME', 'CyberGuardX Invoice System');
define('APP_VERSION', '1.0');
define('CURRENCY_SYMBOL', 'EGP ');

// Database configuration - Now loaded from .env
define('DB_HOST', $_ENV['DB_HOST']);
define('DB_USER', $_ENV['DB_USER']);
define('DB_PASS', $_ENV['DB_PASS']);
define('DB_NAME', $_ENV['DB_NAME']);

// Error reporting (disable in production)
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Create connection with error handling
try {
    $conn = new mysqli(DB_HOST, DB_USER, DB_PASS, DB_NAME);
    
    // Check connection
    if ($conn->connect_error) {
        throw new Exception("Database connection failed: " . $conn->connect_error);
    }
    
    // Set charset
    $conn->set_charset("utf8mb4");
    
} catch (Exception $e) {
    die("<div style='padding: 20px; background: #fee; border: 2px solid red; border-radius: 10px; margin: 20px;'>
        <h3>❌ Database Connection Error</h3>
        <p>" . htmlspecialchars($e->getMessage()) . "</p>
        <p>Please check your database credentials in the .env file</p>
        <p>Make sure MySQL is running and the database exists.</p>
    </div>");
}

// Set timezone
date_default_timezone_set('Africa/Cairo');

// Helper function for safe database queries
function dbQuery($sql, $params = []) {
    global $conn;
    
    if (empty($params)) {
        return $conn->query($sql);
    }
    
    $stmt = $conn->prepare($sql);
    if (!$stmt) {
        throw new Exception("Query preparation failed: " . $conn->error);
    }
    
    $types = '';
    foreach ($params as $param) {
        if (is_int($param)) $types .= 'i';
        elseif (is_float($param)) $types .= 'd';
        elseif (is_string($param)) $types .= 's';
        else $types .= 'b';
    }
    
    $stmt->bind_param($types, ...$params);
    $stmt->execute();
    return $stmt;
}
?>