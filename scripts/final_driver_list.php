<?php
require 'config/db.php';

echo "=== DAFTAR DRIVER SETELAH UPDATE LENGKAP ===\n\n";

$result = $conn->query(
    "SELECT p.id, p.nama_lengkap, ua.username
     FROM pengguna p
     LEFT JOIN user_account ua ON p.id = ua.pengguna_id
     WHERE p.nama_lengkap NOT IN ('AHMAD FAUZI', 'pimpinan')
     ORDER BY p.id"
);

printf("%-3s | %-25s | %-25s\n", "NO", "Username", "Nama Driver");
echo str_repeat("-", 56) . "\n";

$i = 1;
while($row = $result->fetch_assoc()) {
    printf("%-3d | %-25s | %-25s\n", $i, $row['username'] ?? '-', $row['nama_lengkap']);
    $i++;
}

echo "\nPassword default: password123\n";
?>
