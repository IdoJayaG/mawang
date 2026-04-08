<?php
require_once 'config.php';

echo "Creating missing tables...\n";

// Create riwayat_perbaikan table
$sql = "CREATE TABLE IF NOT EXISTS riwayat_perbaikan (
    id int(11) NOT NULL AUTO_INCREMENT,
    kendaraan_id int(11) NOT NULL,
    tanggal_perbaikan date NOT NULL,
    jenis_perbaikan varchar(100) NOT NULL,
    deskripsi text DEFAULT NULL,
    bengkel varchar(100) DEFAULT NULL,
    biaya decimal(15,2) DEFAULT NULL,
    teknisi varchar(100) DEFAULT NULL,
    status enum('Dalam Proses','Selesai','Ditunda') DEFAULT 'Dalam Proses',
    km_saat_perbaikan int(11) DEFAULT NULL,
    spare_parts text DEFAULT NULL,
    catatan text DEFAULT NULL,
    bukti_nota varchar(255) DEFAULT NULL,
    created_at timestamp NOT NULL DEFAULT current_timestamp(),
    updated_at timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
    created_by int(11) DEFAULT NULL,
    updated_by int(11) DEFAULT NULL,
    PRIMARY KEY (id),
    KEY kendaraan_id (kendaraan_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci";

if ($mysqli->query($sql)) {
    echo "Table riwayat_perbaikan created successfully\n";
} else {
    echo "Error creating riwayat_perbaikan: " . $mysqli->error . "\n";
}

// Create pengguna_kendaraan table
$sql = "CREATE TABLE IF NOT EXISTS pengguna_kendaraan (
    id int(11) NOT NULL AUTO_INCREMENT,
    pengguna_id int(11) NOT NULL,
    kendaraan_id int(11) NOT NULL,
    tanggal_mulai date NOT NULL,
    tanggal_selesai date DEFAULT NULL,
    status enum('Aktif','Selesai','Ditangguhkan') DEFAULT 'Aktif',
    keterangan text DEFAULT NULL,
    created_at timestamp NOT NULL DEFAULT current_timestamp(),
    updated_at timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
    PRIMARY KEY (id),
    KEY pengguna_id (pengguna_id),
    KEY kendaraan_id (kendaraan_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci";

if ($mysqli->query($sql)) {
    echo "Table pengguna_kendaraan created successfully\n";
} else {
    echo "Error creating pengguna_kendaraan: " . $mysqli->error . "\n";
}

// Insert some dummy data for riwayat_perbaikan
$dummy_data = [
    [1, '2025-08-15', 'Ganti Oli', 'Ganti oli mesin dan filter oli', 'Bengkel TNI', 350000, 'Bambang', 'Selesai', 15000],
    [3, '2025-08-10', 'Servis AC', 'Perbaikan sistem AC kendaraan', 'Bengkel Resmi', 750000, 'Andi', 'Selesai', 24000],
    [17, '2025-08-05', 'Ganti Ban', 'Ganti 4 ban kendaraan', 'Bengkel TNI', 2000000, 'Slamet', 'Selesai', 14500]
];

foreach ($dummy_data as $data) {
    $stmt = $mysqli->prepare("INSERT IGNORE INTO riwayat_perbaikan (kendaraan_id, tanggal_perbaikan, jenis_perbaikan, deskripsi, bengkel, biaya, teknisi, status, km_saat_perbaikan) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)");
    $stmt->bind_param('issssdssi', $data[0], $data[1], $data[2], $data[3], $data[4], $data[5], $data[6], $data[7], $data[8]);
    $stmt->execute();
    $stmt->close();
}

echo "Database fixes completed!\n";
?>
?>
