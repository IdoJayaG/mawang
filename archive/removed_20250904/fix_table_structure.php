<?php
require_once 'config.php';

// Initialize database connection
$mysqli = new mysqli(DB_HOST, DB_USER, DB_PASS, DB_NAME);
if ($mysqli->connect_error) {
    die("Connection failed: " . $mysqli->connect_error);
}

echo "Memperbaiki struktur tabel log_bahan_bakar...\n";

// Fix column types that are incorrect
$fixes = [
    "ALTER TABLE log_bahan_bakar MODIFY COLUMN foto_sebelum_isi VARCHAR(255) NULL",
    "ALTER TABLE log_bahan_bakar MODIFY COLUMN foto_sesudah_isi VARCHAR(255) NULL", 
    "ALTER TABLE log_bahan_bakar MODIFY COLUMN foto_odometer VARCHAR(255) NULL",
    "ALTER TABLE log_bahan_bakar MODIFY COLUMN keterangan TEXT NULL"
];

foreach ($fixes as $fix) {
    echo "Menjalankan: $fix\n";
    if ($mysqli->query($fix)) {
        echo "✓ Berhasil\n";
    } else {
        echo "✗ Error: " . $mysqli->error . "\n";
    }
}

// Fix ID column if needed
echo "Memperbaiki kolom id...\n";
$result = $mysqli->query("SHOW COLUMNS FROM log_bahan_bakar WHERE Field = 'id'");
if ($result && $result->num_rows > 0) {
    $column = $result->fetch_assoc();
    if (strpos($column['Extra'], 'auto_increment') === false) {
        echo "Menambahkan AUTO_INCREMENT ke kolom id...\n";
        $mysqli->query("ALTER TABLE log_bahan_bakar MODIFY COLUMN id INT(11) NOT NULL AUTO_INCREMENT");
        if ($mysqli->error) {
            echo "Error: " . $mysqli->error . "\n";
        } else {
            echo "✓ Berhasil\n";
        }
    } else {
        echo "✓ Kolom id sudah benar\n";
    }
}

// Fix other tables' id columns if needed
$tables_to_fix = ['riwayat_pemakaian', 'riwayat_perawatan'];

foreach ($tables_to_fix as $table) {
    echo "Memeriksa tabel $table...\n";
    $result = $mysqli->query("SHOW COLUMNS FROM $table WHERE Field = 'id'");
    if ($result && $result->num_rows > 0) {
        $column = $result->fetch_assoc();
        if (strpos($column['Extra'], 'auto_increment') === false) {
            echo "Menambahkan AUTO_INCREMENT ke $table.id...\n";
            $mysqli->query("ALTER TABLE $table MODIFY COLUMN id INT(11) NOT NULL AUTO_INCREMENT");
            if ($mysqli->error) {
                echo "Error: " . $mysqli->error . "\n";
            } else {
                echo "✓ Berhasil\n";
            }
        } else {
            echo "✓ Kolom id sudah benar\n";
        }
    }
}

// Fix user tables id columns
$user_tables = ['pengguna', 'user_account'];
foreach ($user_tables as $table) {
    echo "Memeriksa tabel $table...\n";
    $result = $mysqli->query("SHOW COLUMNS FROM $table WHERE Field = 'id'");
    if ($result && $result->num_rows > 0) {
        $column = $result->fetch_assoc();
        if (strpos($column['Extra'], 'auto_increment') === false) {
            echo "Menambahkan AUTO_INCREMENT ke $table.id...\n";
            $mysqli->query("ALTER TABLE $table MODIFY COLUMN id INT(11) NOT NULL AUTO_INCREMENT");
            if ($mysqli->error) {
                echo "Error: " . $mysqli->error . "\n";
            } else {
                echo "✓ Berhasil\n";
            }
        } else {
            echo "✓ Kolom id sudah benar\n";
        }
    }
}

echo "Selesai memperbaiki struktur database!\n";
$mysqli->close();
?>
