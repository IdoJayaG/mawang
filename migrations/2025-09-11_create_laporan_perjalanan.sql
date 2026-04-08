-- Migration: Create laporan_perjalanan table for trip reports
-- Columns capture trip basics; BBM is computed in app from jarak and kendaraan.bahan_bakar

CREATE TABLE IF NOT EXISTS `laporan_perjalanan` (
  `id` INT NOT NULL AUTO_INCREMENT,
  `tanggal` DATE NOT NULL,
  `kendaraan_id` INT NOT NULL,
  `pengguna_id` INT NULL,
  `uraian_kegiatan` VARCHAR(255) NOT NULL,
  `route` VARCHAR(255) NOT NULL,
  `jarak_km` DECIMAL(10,2) DEFAULT NULL,
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` DATETIME NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_laporan_kendaraan` (`kendaraan_id`),
  KEY `idx_laporan_pengguna` (`pengguna_id`),
  CONSTRAINT `fk_laporan_kendaraan` FOREIGN KEY (`kendaraan_id`) REFERENCES `kendaraan`(`id`) ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT `fk_laporan_pengguna` FOREIGN KEY (`pengguna_id`) REFERENCES `pengguna`(`id`) ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
