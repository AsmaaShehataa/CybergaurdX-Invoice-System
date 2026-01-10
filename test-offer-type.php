<?php
// test-offer-type.php
// Run from terminal: php test-offer-type.php

require_once 'includes/config.php';

echo "=== TEST: offer_type ENUM Issue ===\n\n";

// 1. Check database ENUM definition
echo "1. Checking database ENUM definition...\n";
$result = $conn->query("SHOW COLUMNS FROM invoices LIKE 'offer_type'");
if ($row = $result->fetch_assoc()) {
    echo "   Column Type: " . $row['Type'] . "\n";
    
    if (preg_match("/enum\((.*)\)/i", $row['Type'], $matches)) {
        // Fix the deprecated warning
        $enum_str = trim($matches[1], "'");
        $enum_values = explode("','", $enum_str);
        echo "   ENUM values: " . implode(', ', $enum_values) . "\n";
    }
}

// 2. Test different strings
echo "\n2. Testing different string representations:\n";

$test_strings = [
    'percent',
    'percent ', // with space
    'percent' . chr(0), // with null byte
    'percent' . "\r\n", // with newline
    'Percent', // capital
    'PERCENT', // uppercase
    'percent' . chr(194) . chr(160), // with non-breaking space
];

foreach ($test_strings as $i => $str) {
    echo "   Test " . ($i+1) . ": '" . addcslashes($str, "\0..\37") . "'";
    echo " (len: " . strlen($str) . ", hex: " . bin2hex($str) . ")\n";
    
    // Test direct SQL
    $test_id = 10; // Use your test invoice ID
    $sql = "UPDATE invoices SET offer_type = '" . $conn->real_escape_string($str) . "' WHERE id = $test_id";
    
    if ($conn->query($sql)) {
        echo "   ✅ Direct SQL: Works\n";
        // Reset back to original
        $conn->query("UPDATE invoices SET offer_type = 'percent' WHERE id = $test_id");
    } else {
        echo "   ❌ Direct SQL: " . $conn->error . "\n";
    }
    
    // Test prepared statement - EXTRACT VARIABLE FIRST
    $value_for_bind = $str; // Extract to variable
    $stmt = $conn->prepare("UPDATE invoices SET offer_type = ? WHERE id = ?");
    $stmt->bind_param("si", $value_for_bind, $test_id);
    
    if ($stmt->execute()) {
        echo "   ✅ Prepared: Works\n";
        $conn->query("UPDATE invoices SET offer_type = 'percent' WHERE id = $test_id");
    } else {
        echo "   ❌ Prepared: " . $stmt->error . "\n";
    }
    
    echo "\n";
}

// 3. Test with different variable types - FIXED
echo "\n3. Testing with different PHP variable types:\n";

$tests = [
    ['type' => 'string', 'value' => 'percent'],
    ['type' => 'integer cast', 'value' => (string)'percent'],
    ['type' => 'from array', 'value' => trim('percent')],
    ['type' => 'mb encoded', 'value' => mb_convert_encoding('percent', 'UTF-8', 'UTF-8')],
    ['type' => 'filtered', 'value' => preg_replace('/[^\x20-\x7E]/', '', 'percent')],
];

foreach ($tests as $test) {
    echo "   Type: " . $test['type'] . ", Value: '" . $test['value'] . "'\n";
    echo "   Hex: " . bin2hex($test['value']) . ", Length: " . strlen($test['value']) . "\n";
    
    // FIX: Extract to variable before bind_param
    $test_value = $test['value'];
    $stmt = $conn->prepare("UPDATE invoices SET offer_type = ? WHERE id = ?");
    $stmt->bind_param("si", $test_value, 10);
    
    if ($stmt->execute()) {
        echo "   ✅ Works\n";
        $conn->query("UPDATE invoices SET offer_type = 'percent' WHERE id = 10");
    } else {
        echo "   ❌ Failed: " . $stmt->error . "\n";
    }
    echo "\n";
}

// 4. Check actual data in invoice 10
echo "\n4. Current data in invoice 10:\n";
$result = $conn->query("SELECT offer_type, HEX(offer_type) as hex_value, LENGTH(offer_type) as length FROM invoices WHERE id = 10");
if ($row = $result->fetch_assoc()) {
    echo "   offer_type: '" . $row['offer_type'] . "'\n";
    echo "   Hex value: " . $row['hex_value'] . "\n";
    echo "   Length: " . $row['length'] . " bytes\n";
}

// 5. CRITICAL TEST: Test with YOUR edit-invoice.php logic
echo "\n5. Testing with edit-invoice.php logic:\n";

// Simulate what happens in your form
$offer_type_raw = 'percent'; // From $_POST['offer_type']
$offer_type = trim($offer_type_raw);
$offer_value = 40.00;

echo "   Simulating form submission:\n";
echo "   Raw: '$offer_type_raw'\n";
echo "   Trimmed: '$offer_type'\n";

// Create typed variables like in edit-invoice.php
$subtotal_float = 18000.00;
$discount_float = 7200.00;
$net_float = 10800.00;
$vat_float = 1512.00;
$total_float = 12312.00;
$payment_amount_float = 1000.00;
$balance_float = 12312.00;
$offer_type_string = (string)$offer_type; // This is what you use
$offer_value_float = (float)$offer_value;
$invoice_id_int = 10;

echo "   offer_type_string: '$offer_type_string'\n";
echo "   Hex: " . bin2hex($offer_type_string) . "\n";

// Test the EXACT bind_param from edit-invoice.php
$update_invoice = $conn->prepare("UPDATE invoices SET 
    subtotal = ?, 
    discount = ?, 
    net = ?, 
    vat = ?, 
    total = ?, 
    payment_amount = ?, 
    balance = ?, 
    offer_type = ?, 
    offer_value = ? 
    WHERE id = ?");

if ($update_invoice->bind_param("ddddddsdis", 
    $subtotal_float,
    $discount_float,
    $net_float,
    $vat_float,
    $total_float,
    $payment_amount_float,
    $balance_float,
    $offer_type_string,  // This is the variable!
    $offer_value_float,
    $invoice_id_int
)) {
    echo "   ✅ bind_param succeeded\n";
    
    if ($update_invoice->execute()) {
        echo "   ✅ execute succeeded - Invoice updated!\n";
        $conn->query("UPDATE invoices SET offer_type = 'percent' WHERE id = 10"); // Reset
    } else {
        echo "   ❌ execute failed: " . $update_invoice->error . "\n";
    }
} else {
    echo "   ❌ bind_param failed\n";
}

$conn->close();
echo "\n=== Test Complete ===\n";
?>