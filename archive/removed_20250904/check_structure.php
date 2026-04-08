<?php
require_once 'config.php';

echo "Current pengguna table structure:\n\n";

$result = $mysqli->query('DESCRIBE pengguna');
if ($result) {
    while($row = $result->fetch_assoc()) {
        echo $row['Field'] . ' - ' . $row['Type'] . "\n";
    }
} else {
    echo "Error: " . $mysqli->error . "\n";
}
?>
