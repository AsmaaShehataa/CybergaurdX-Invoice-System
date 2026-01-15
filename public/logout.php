<?php
// logout.php - Simple logout
require_once dirname(__DIR__) . '/includes/init.php';
destroySession();
header('Location: index.php');
exit();
?>