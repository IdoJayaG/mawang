<?php
require_once 'config/db.php';

echo "Struktur tabel user_account:\n";
$result = mysqli_query($conn, 'DESCRIBE user_account');
while($row = mysqli_fetch_assoc($result)) {
    echo $row['Field'] . " - " . $row['Type'] . "\n";
}
?>
