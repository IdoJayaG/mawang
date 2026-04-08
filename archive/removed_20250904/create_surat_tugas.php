<?php
require_once 'config.php';

echo "Creating surat_tugas table...\n";

$sql = "CREATE TABLE IF NOT EXISTS `surat_tugas` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `nomor_surat` varchar(50) NOT NULL,
  `tanggal_surat` date NOT NULL,
  `kendaraan_id` int(11) NOT NULL,
  `pengguna_id` int(11) NOT NULL,
  `tujuan` varchar(255) NOT NULL,
  `keperluan` text NOT NULL,
  `tanggal_berangkat` date NOT NULL,
  `tanggal_kembali` date DEFAULT NULL,
  `estimasi_km` int(11) DEFAULT NULL,
  `estimasi_bbm` decimal(8,2) DEFAULT NULL,
  `status` enum('Draft','Disetujui','Dalam Perjalanan','Selesai','Dibatalkan') NOT NULL DEFAULT 'Draft',
  `km_berangkat` int(11) DEFAULT NULL,
  `km_kembali` int(11) DEFAULT NULL,
  `bbm_terpakai` decimal(8,2) DEFAULT NULL,
  `laporan_perjalanan` text DEFAULT NULL,
  `pejabat_ttd` varchar(100) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `created_by` int(11) DEFAULT NULL,
  `updated_by` int(11) DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `nomor_surat` (`nomor_surat`),
  KEY `kendaraan_id` (`kendaraan_id`),
  KEY `pengguna_id` (`pengguna_id`),
  KEY `created_by` (`created_by`),
  KEY `updated_by` (`updated_by`),
  KEY `idx_tanggal_surat` (`tanggal_surat`),
  KEY `idx_status` (`status`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci COMMENT='Tabel surat tugas perjalanan dinas'";

if ($mysqli->query($sql)) {
    echo "✓ Table surat_tugas created successfully!\n";
    
    // Insert sample data
    $sample_data = [
        [
            'nomor_surat' => 'ST/001/VIII/2025',
            'tanggal_surat' => '2025-08-18',
            'kendaraan_id' => 1,
            'pengguna_id' => 2,
            'tujuan' => 'Bandung - Jawa Barat',
            'keperluan' => 'Koordinasi dengan Kodam III/Siliwangi terkait sistem informasi manajemen kendaraan',
            'tanggal_berangkat' => '2025-08-20',
            'tanggal_kembali' => '2025-08-21',
            'estimasi_km' => 300,
            'estimasi_bbm' => 25.00,
            'status' => 'Disetujui',
            'km_berangkat' => 13500,
            'km_kembali' => 13800,
            'bbm_terpakai' => 24.50,
            'laporan_perjalanan' => 'Koordinasi berjalan lancar, sistem akan diimplementasikan tahap pertama',
            'pejabat_ttd' => 'LAKSDA TNI ARIANTYO CONDROWIBOWO',
            'created_by' => 1
        ],
        [
            'nomor_surat' => 'ST/002/VIII/2025',
            'tanggal_surat' => '2025-08-19',
            'kendaraan_id' => 3,
            'pengguna_id' => 3,
            'tujuan' => 'Surabaya - Jawa Timur',
            'keperluan' => 'Inspeksi dan audit sistem kendaraan dinas di Kodam V/Brawijaya',
            'tanggal_berangkat' => '2025-08-22',
            'tanggal_kembali' => '2025-08-24',
            'estimasi_km' => 800,
            'estimasi_bbm' => 65.00,
            'status' => 'Draft',
            'pejabat_ttd' => 'LAKSDA TNI ARIANTYO CONDROWIBOWO',
            'created_by' => 2
        ],
        [
            'nomor_surat' => 'ST/003/VIII/2025',
            'tanggal_surat' => '2025-08-20',
            'kendaraan_id' => 17,
            'pengguna_id' => 4,
            'tujuan' => 'Jakarta Selatan',
            'keperluan' => 'Pengambilan dokumen dan surat penting di Kemhan RI',
            'tanggal_berangkat' => '2025-08-20',
            'tanggal_kembali' => '2025-08-20',
            'estimasi_km' => 50,
            'estimasi_bbm' => 5.00,
            'status' => 'Dalam Perjalanan',
            'km_berangkat' => 14200,
            'pejabat_ttd' => 'KOLONEL LUT (T) WAHYU HIDAYAT',
            'created_by' => 3
        ]
    ];
    
    $insert_sql = "INSERT INTO surat_tugas (nomor_surat, tanggal_surat, kendaraan_id, pengguna_id, tujuan, keperluan, tanggal_berangkat, tanggal_kembali, estimasi_km, estimasi_bbm, status, km_berangkat, km_kembali, bbm_terpakai, laporan_perjalanan, pejabat_ttd, created_by) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";
    
    $stmt = $mysqli->prepare($insert_sql);
    
    foreach ($sample_data as $data) {
        $stmt->bind_param('ssiissssidsiidssi', 
            $data['nomor_surat'], 
            $data['tanggal_surat'], 
            $data['kendaraan_id'], 
            $data['pengguna_id'], 
            $data['tujuan'], 
            $data['keperluan'], 
            $data['tanggal_berangkat'], 
            $data['tanggal_kembali'], 
            $data['estimasi_km'], 
            $data['estimasi_bbm'], 
            $data['status'], 
            $data['km_berangkat'] ?? null, 
            $data['km_kembali'] ?? null, 
            $data['bbm_terpakai'] ?? null, 
            $data['laporan_perjalanan'] ?? null, 
            $data['pejabat_ttd'], 
            $data['created_by']
        );
        
        if ($stmt->execute()) {
            echo "✓ Sample data inserted: " . $data['nomor_surat'] . "\n";
        } else {
            echo "✗ Error inserting " . $data['nomor_surat'] . ": " . $stmt->error . "\n";
        }
    }
    $stmt->close();
    
} else {
    echo "✗ Error creating table: " . $mysqli->error . "\n";
}

$mysqli->close();
echo "\nDone!\n";
?>
