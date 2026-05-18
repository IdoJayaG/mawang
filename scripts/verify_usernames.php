<?php
require 'config/db.php';

echo "=== VERIFIKASI USERNAME SETELAH UPDATE ===\n\n";

// Check for double dots
$r = $conn->query("SELECT id, username FROM user_account WHERE username LIKE '%..%'");
if($r->num_rows > 0) {
    echo "Username dengan double dots (perlu dibersihkan):\n";
    while($row = $r->fetch_assoc()) {
        // Fix double dots
        $cleaned = str_replace('..', '.', $row['username']);
        $update = $conn->prepare("UPDATE user_account SET username = ? WHERE id = ?");
        $update->bind_param("si", $cleaned, $row['id']);
        $update->execute();
        $update->close();
        
        echo "✓ {$row['username']} → {$cleaned}\n";
    }
} else {
    echo "✓ Tidak ada double dots ditemukan\n";
}

// Show all current usernames
echo "\n=== DAFTAR USERNAME TERBARU ===\n\n";
$result = $conn->query(
    "SELECT ua.username, p.nama_lengkap 
     FROM user_account ua 
     JOIN pengguna p ON ua.pengguna_id = p.id
     WHERE ua.username NOT LIKE 'admin%'
     ORDER BY p.id"
);

$i = 1;
while($row = $result->fetch_assoc()) {
    printf("%2d. %-25s (%s)\n", $i, $row['username'], $row['nama_lengkap']);
    $i++;
}

echo "\nPassword default: password123\n";
?>
