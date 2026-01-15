<?php
require_once dirname(__DIR__) . '/includes/config.php';

echo "<h2>Database Connection Test</h2>";

// Check connection
if ($conn->connect_error) {
    die("❌ Connection failed: " . $conn->connect_error);
}

echo "✅ Database connection successful!<br>";

// Test query
$sql = "SELECT DATABASE() as db_name";
$result = $conn->query($sql);

if ($result) {
    $row = $result->fetch_assoc();
    echo "✅ Connected to database: " . $row['db_name'] . "<br>";
} else {
    echo "❌ Query failed: " . $conn->error . "<br>";
}

// Test if tables exist
$sql = "SHOW TABLES";
$result = $conn->query($sql);

if ($result->num_rows > 0) {
    echo "✅ Tables in database:<br>";
    echo "<ul>";
    while($row = $result->fetch_array()) {
        echo "<li>" . $row[0] . "</li>";
    }
    echo "</ul>";
} else {
    echo "⚠️ No tables found. Did you run the SQL dump?<br>";
}

$conn->close();
?>