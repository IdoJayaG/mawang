-- Migration: Align riwayat_perbaikan schema with new UI/logic
-- - Make descriptive fields nullable/wider
-- - Ensure status enum includes 'Menunggu Sparepart'
-- - Set biaya default to 0 (computed from items)
-- - Add helpful indexes

ALTER TABLE `riwayat_perbaikan`
  MODIFY `deskripsi` TEXT NULL,
  MODIFY `deskripsi_perbaikan` TEXT NULL,
  MODIFY `keterangan` TEXT NULL,
  MODIFY `bengkel` VARCHAR(100) NULL,
  MODIFY `biaya` DECIMAL(15,2) NOT NULL DEFAULT 0,
  MODIFY `teknisi` VARCHAR(100) NULL,
  MODIFY `status` ENUM('Dalam Proses','Menunggu Sparepart','Ditunda','Selesai') DEFAULT 'Dalam Proses',
  MODIFY `km_perbaikan` INT NULL,
  MODIFY `spare_parts` TEXT NULL,
  MODIFY `catatan` TEXT NULL;

-- Add indexes for common filters/sorts (may already exist on some DBs)
ALTER TABLE `riwayat_perbaikan` ADD INDEX `idx_rp_kendaraan_id` (`kendaraan_id`);
ALTER TABLE `riwayat_perbaikan` ADD INDEX `idx_rp_tanggal` (`tanggal_perbaikan`);
ALTER TABLE `riwayat_perbaikan` ADD INDEX `idx_rp_status` (`status`);
