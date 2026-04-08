<?php
require_once 'config.php';

echo "Database connection test\n";
echo "Connected to database: " . DB_NAME . "\n";

// Test basic connection
$result = $mysqli->query("SELECT 1 as test");
if ($result) {
    echo "✓ Database connection working\n";
} else {
    echo "✗ Database connection failed: " . $mysqli->error . "\n";
    exit(1);
}

// Test tables exist
$tables = ['user_account', 'riwayat_pemakaian', 'peminjaman_kendaraan', 'user_activity'];
foreach ($tables as $table) {
    $result = $mysqli->query("SHOW TABLES LIKE '$table'");
    if ($result && $result->num_rows > 0) {
        echo "✓ Table '$table' exists\n";
    } else {
        echo "✗ Table '$table' missing\n";
    }
}

// Test the specific query that's failing
echo "\nTesting the failing query...\n";
$stmt = $mysqli->prepare("SELECT COUNT(*) as total_usage FROM riwayat_pemakaian WHERE user_id = ?");
if (!$stmt) {
    echo "✗ Prepare failed: " . $mysqli->error . "\n";
} else {
    $test_id = 2;
    $stmt->bind_param('i', $test_id);
    if ($stmt->execute()) {
        $result = $stmt->get_result();
        $data = $result->fetch_assoc();
        echo "✓ Query successful. Count: " . $data['total_usage'] . "\n";
    } else {
        echo "✗ Execute failed: " . $stmt->error . "\n";
    }
    $stmt->close();
}

$mysqli->close();
?>
