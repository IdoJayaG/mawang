<?php
require_once 'config.php';
include 'config/db.php';

echo "Checking kendaraan table structure:\n";
$result = $mysqli->query('SHOW COLUMNS FROM kendaraan');
if ($result) {
    while ($row = $result->fetch_assoc()) {
        echo $row['Field'] . ' - ' . $row['Type'] . "\n";
    }
} else {
    echo 'Error: ' . $mysqli->error . "\n";
}
?>
