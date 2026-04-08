<?php
require_once 'config/db.php';

echo "Testing surat_tugas database...\n";

$result = $conn->query('SELECT COUNT(*) as count FROM surat_tugas');
if ($result) {
    $row = $result->fetch_assoc();
    echo "Tabel surat_tugas: " . $row['count'] . " records\n";
    echo "Database connection successful!\n";
} else {
    echo "Error: " . $conn->error . "\n";
}

// Test apakah kolom klasifikasi sudah ada
$result = $conn->query("SELECT klasifikasi FROM surat_tugas LIMIT 1");
if ($result !== FALSE) {
    echo "✓ Kolom klasifikasi berhasil ditemukan!\n";
} else {
    echo "✗ Error testing klasifikasi: " . $conn->error . "\n";
}

$conn->close();
?>
