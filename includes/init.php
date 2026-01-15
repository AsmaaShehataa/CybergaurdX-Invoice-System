<?php
// includes/init.php - Standard initialization for all pages
// Include this ONE file at the start of every page

// Set error reporting
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Include session/authentication functions
require_once 'session.php';

// Start session automatically
startAppSession();

// Include database configuration
require_once 'config.php';

// Set default timezone
date_default_timezone_set('Africa/Cairo');

// Optional: Check for maintenance mode
// if (file_exists(dirname(__DIR__) . '/maintenance.flag')) {
//     die('System is under maintenance. Please check back later.');
// }
?>