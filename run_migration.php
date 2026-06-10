<?php
// Database connection
$host = "localhost";
$user = "root";
$pass = "";
$db   = "randis";
$mysqli = mysqli_connect($host, $user, $pass, $db);

if (!$mysqli) {
    die("Connection failed: " . mysqli_connect_error() . "\n");
}

// Read and execute migration
$sqlFile = __DIR__ . '/migrations/003_add_approval_columns.sql';
if (!file_exists($sqlFile)) {
    die("Migration file not found: " . $sqlFile . "\n");
}

$sql = file_get_contents($sqlFile);
$statements = array_filter(array_map('trim', preg_split('/;[\s\n]+/', $sql)));

$successCount = 0;
$errorCount = 0;
$errors = [];

foreach ($statements as $statement) {
    if (empty($statement) || strpos(trim($statement), '--') === 0) {
        continue;
    }
    
    if (!$mysqli->query($statement)) {
        $errorCount++;
        $errors[] = "Error: " . $mysqli->error . "\nStatement: " . substr($statement, 0, 100) . "...";
        echo "FAILED: " . substr($statement, 0, 80) . "\n";
    } else {
        $successCount++;
        echo "OK: " . substr($statement, 0, 80) . "\n";
    }
}

echo "\n=== MIGRATION RESULT ===\n";
echo "Successful queries: $successCount\n";
echo "Failed queries: $errorCount\n";

if (!empty($errors)) {
    echo "\nErrors:\n";
    foreach ($errors as $err) {
        echo "- " . $err . "\n";
    }
}

$mysqli->close();
?>
