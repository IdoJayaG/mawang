<?php
require_once 'config/db.php';

echo "Memeriksa struktur tabel surat_tugas...\n\n";

// Cek apakah tabel ada
$result = $conn->query("SHOW TABLES LIKE 'surat_tugas'");
if ($result->num_rows == 0) {
    echo "Tabel surat_tugas tidak ditemukan!\n";
} else {
    echo "Tabel surat_tugas ditemukan.\n\n";
    
    // Tampilkan struktur tabel
    $result = $conn->query('DESCRIBE surat_tugas');
    if ($result) {
        echo "Struktur tabel surat_tugas:\n";
        echo "Field\t\t\tType\t\t\tNull\tKey\tDefault\n";
        echo "---------------------------------------------------------------\n";
        while ($row = $result->fetch_assoc()) {
            echo sprintf("%-20s\t%-20s\t%s\t%s\t%s\n", 
                $row['Field'], 
                $row['Type'], 
                $row['Null'], 
                $row['Key'], 
                $row['Default'] ?? 'NULL'
            );
        }
    }
}

$conn->close();
?>
