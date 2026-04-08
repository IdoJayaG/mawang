-- Adds optional assignment of a pengguna to a kendaraan.
-- Safe to run once; subsequent runs will no-op due to IF NOT EXISTS checks.

ALTER TABLE `kendaraan`
ADD COLUMN IF NOT EXISTS `pengguna_id` INT NULL AFTER `penanggung_jawab`;

ALTER TABLE `kendaraan`
ADD INDEX IF NOT EXISTS `idx_kendaraan_pengguna_id` (`pengguna_id`);

ALTER TABLE `kendaraan`
ADD CONSTRAINT `fk_kendaraan_pengguna`
FOREIGN KEY (`pengguna_id`) REFERENCES `pengguna`(`id`)
ON DELETE SET NULL ON UPDATE CASCADE;
