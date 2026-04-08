<?php
require_once 'config/db.php';

$conn = new mysqli('localhost', 'root', '', 'randis');
$result = $conn->query('SELECT id, nomor_surat FROM surat_tugas LIMIT 3');

if($result && $result->num_rows > 0) {
    echo "Found surat tugas records:\n";
    while($row = $result->fetch_assoc()) {
        echo "ID: " . $row['id'] . " - Nomor: " . $row['nomor_surat'] . "\n";
    }
} else {
    echo "No surat tugas found\n";
}
?>
