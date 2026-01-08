<?php
// Test if sessions are working now
ini_set('session.save_path', '/tmp');
session_start();

echo "<h1>Session Test</h1>";
echo "Session ID: " . session_id() . "<br>";
echo "Session path: " . session_save_path() . "<br>";
echo "Can write? " . (is_writable(session_save_path()) ? 'YES ✅' : 'NO ❌') . "<br>";

// Test storing data
if (!isset($_SESSION['test_counter'])) {
    $_SESSION['test_counter'] = 1;
} else {
    $_SESSION['test_counter']++;
}

echo "Counter: " . $_SESSION['test_counter'] . "<br>";
echo '<a href="test-session-fix.php">Refresh - counter should increase</a>';
?>