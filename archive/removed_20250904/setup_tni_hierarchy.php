<?php
require_once 'config.php';

echo "Setting up TNI Hierarchy Tables...\n";

// Read the SQL file
$sql_file = __DIR__ . '/create_tni_hierarchy_tables.sql';
if (!file_exists($sql_file)) {
    die("SQL file not found: $sql_file\n");
}

$sql_content = file_get_contents($sql_file);
if ($sql_content === false) {
    die("Failed to read SQL file\n");
}

// Split the SQL content into individual statements
$statements = array_filter(array_map('trim', explode(';', $sql_content)));

$success_count = 0;
$error_count = 0;

foreach ($statements as $statement) {
    if (empty($statement) || strpos(trim($statement), '--') === 0) {
        continue; // Skip empty statements and comments
    }
    
    try {
        if ($mysqli->query($statement)) {
            $success_count++;
            echo "✓ Executed successfully\n";
        } else {
            $error_count++;
            echo "✗ Error: " . $mysqli->error . "\n";
            echo "Statement: " . substr($statement, 0, 100) . "...\n";
        }
    } catch (Exception $e) {
        $error_count++;
        echo "✗ Exception: " . $e->getMessage() . "\n";
        echo "Statement: " . substr($statement, 0, 100) . "...\n";
    }
}

echo "\n=== Setup Complete ===\n";
echo "Successful statements: $success_count\n";
echo "Failed statements: $error_count\n";

// Test the tables
echo "\n=== Testing Tables ===\n";

$test_queries = [
    "SELECT COUNT(*) as count FROM matra" => "Matra records",
    "SELECT COUNT(*) as count FROM korps" => "Korps records",
    "SELECT COUNT(*) as count FROM kesatuan" => "Kesatuan records"
];

foreach ($test_queries as $query => $description) {
    try {
        $result = $mysqli->query($query);
        if ($result) {
            $row = $result->fetch_assoc();
            echo "$description: " . $row['count'] . "\n";
        } else {
            echo "$description: Error - " . $mysqli->error . "\n";
        }
    } catch (Exception $e) {
        echo "$description: Exception - " . $e->getMessage() . "\n";
    }
}

echo "\nSetup completed. You can now test the form functionality.\n";
?>
