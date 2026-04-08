<?php
require_once 'config/db.php';
require_once 'config.php';

// Initialize database connection
$mysqli = new mysqli(DB_HOST, DB_USER, DB_PASS, DB_NAME);
if ($mysqli->connect_error) {
    die("Connection failed: " . $mysqli->connect_error);
}

echo "Memeriksa dan memperbaiki tabel dokumen_kendaraan...\n";

// Check if file_dokumen column exists
$result = $mysqli->query("SHOW COLUMNS FROM dokumen_kendaraan LIKE 'file_dokumen'");
if ($result->num_rows == 0) {
    echo "Menambahkan kolom file_dokumen...\n";
    $mysqli->query("ALTER TABLE dokumen_kendaraan ADD COLUMN file_dokumen VARCHAR(255) NULL AFTER instansi_penerbit");
    if ($mysqli->error) {
        echo "Error: " . $mysqli->error . "\n";
    } else {
        echo "Kolom file_dokumen berhasil ditambahkan!\n";
    }
} else {
    echo "Kolom file_dokumen sudah ada.\n";
}

// Create uploads directory if not exists
$upload_dir = 'uploads/dokumen/';
if (!file_exists($upload_dir)) {
    if (mkdir($upload_dir, 0755, true)) {
        echo "Direktori uploads/dokumen/ berhasil dibuat.\n";
    } else {
        echo "Gagal membuat direktori uploads/dokumen/.\n";
    }
} else {
    echo "Direktori uploads/dokumen/ sudah ada.\n";
}

echo "Selesai!\n";
?>
