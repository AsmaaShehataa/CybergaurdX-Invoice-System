<?php
require_once 'includes/config.php';

echo "<h3>Quick Database Test</h3>";

// Test 1: Connection
echo "Connection: " . ($conn->ping() ? "✅ OK" : "❌ Failed") . "<br>";

// Test 2: List users
$result = $conn->query("SELECT id, username, role FROM users");
echo "Users in database: " . $result->num_rows . "<br>";
while($row = $result->fetch_assoc()) {
    echo "- {$row['username']} ({$row['role']})<br>";
}

// Test 3: Insert test data
$test_sql = "INSERT INTO clients (name, email, created_by) VALUES ('PHP Test Client', 'php@test.com', 1)";
if ($conn->query($test_sql)) {
    echo "✅ Test client inserted<br>";
}

// Test 4: Count clients
$count = $conn->query("SELECT COUNT(*) as total FROM clients")->fetch_assoc()['total'];
echo "Total clients: $count<br>";

$conn->close();
?>