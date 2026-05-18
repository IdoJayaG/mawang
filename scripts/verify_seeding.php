<?php
require 'config/db.php';

echo "=== VERIFIKASI DATA ===\n\n";

// Data kendaraan
$kendaraan = $conn->query(
    "SELECT k.id, k.no_reg, k.no_polisi, p.nama_lengkap, pa.username 
     FROM kendaraan k 
     JOIN pengguna p ON k.pengguna_id = p.id 
     LEFT JOIN user_account pa ON p.id = pa.pengguna_id
     WHERE k.no_reg IN ('7775-00', '7722-00', '7781-00', '7700-00', '7701-00', '7702-00', '7713-00', '7782-00', '7703-00', '7706-00', '7707-00', '7708-00', '7709-00', '7720-00', '7711-00', '7698-00', '7710-00', '7717-00', '7715-00', '7696-00', '7718-00', '7719-00', '7705-00', '7721-00', '7690-00', '7723-00', '7803-00', '7802-00', '7704-00', '7804-00', '7724-00', '7726-00', '7714-00', '8891-00')
     ORDER BY k.no_reg"
);

echo "NO | No. Registrasi | No. Polisi | Nama Driver | Username\n";
echo str_repeat("-", 100) . "\n";
$i = 1;
while ($row = $kendaraan->fetch_assoc()) {
    printf("%2d | %-14s | %-10s | %-30s | %s\n", 
        $i, 
        $row['no_reg'], 
        $row['no_polisi'], 
        $row['nama_lengkap'],
        $row['username']
    );
    $i++;
}

echo "\n=== RINGKASAN ===\n";
$k_count = $conn->query("SELECT COUNT(*) as total FROM kendaraan WHERE no_reg IN ('7775-00', '7722-00', '7781-00', '7700-00', '7701-00', '7702-00', '7713-00', '7782-00', '7703-00', '7706-00', '7707-00', '7708-00', '7709-00', '7720-00', '7711-00', '7698-00', '7710-00', '7717-00', '7715-00', '7696-00', '7718-00', '7719-00', '7705-00', '7721-00', '7690-00', '7723-00', '7803-00', '7802-00', '7704-00', '7804-00', '7724-00', '7726-00', '7714-00', '8891-00')")->fetch_assoc();
$p_count = $conn->query("SELECT COUNT(*) as total FROM pengguna WHERE nama_lengkap LIKE 'Ajp%' OR nama_lengkap LIKE 'Truk%'")->fetch_assoc();
$u_count = $conn->query("SELECT COUNT(*) as total FROM user_account WHERE username LIKE 'ajp.%' OR username LIKE 'truk.%'")->fetch_assoc();

echo "Total Kendaraan Baru: " . $k_count['total'] . "\n";
echo "Total Pengguna Driver: " . $p_count['total'] . "\n";
echo "Total User Account Driver: " . $u_count['total'] . "\n";
echo "\nPassword default untuk semua driver: password123\n";
?>
