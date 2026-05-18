<?php
require_once __DIR__ . '/../config/db.php';
echo "=== user_account table ===\n";
$result = $conn->query('DESCRIBE user_account');
while($row = $result->fetch_assoc()) {
    echo $row['Field'] . ' (' . $row['Type'] . ')' . PHP_EOL;
}
echo "\n=== pengguna table ===\n";
$result = $conn->query('DESCRIBE pengguna');
while($row = $result->fetch_assoc()) {
    echo $row['Field'] . ' (' . $row['Type'] . ')' . PHP_EOL;
}
?>
