-- Migration: create peminjaman_terjadwal table
-- Generated to satisfy application expectations in pages/peminjaman_terjadwal.php

CREATE TABLE IF NOT EXISTS `peminjaman_terjadwal` (
  `id` INT(11) NOT NULL AUTO_INCREMENT,
  `pemohon_id` INT(11) DEFAULT NULL,
  `kendaraan_id` INT(11) NOT NULL,
  `keperluan` TEXT,
  `tujuan` VARCHAR(255) DEFAULT NULL,
  `tanggal_mulai` DATETIME DEFAULT NULL,
  `tanggal_selesai` DATETIME DEFAULT NULL,
  `jam_mulai` TIME DEFAULT NULL,
  `jam_selesai` TIME DEFAULT NULL,
  `status` VARCHAR(50) DEFAULT 'pending',
  `approved_by` INT(11) DEFAULT NULL,
  `approved_at` DATETIME DEFAULT NULL,
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_pemohon` (`pemohon_id`),
  KEY `idx_kendaraan` (`kendaraan_id`),
  KEY `idx_status` (`status`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- Optional: grant privileges or further indices can be added if needed.
