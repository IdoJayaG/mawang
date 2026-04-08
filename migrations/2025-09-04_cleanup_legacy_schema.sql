-- randis cleanup: remove unused legacy objects and fix approval_by FK
-- Safe to run multiple times (IF EXISTS guards)

-- 0) Temporarily disable FK checks for the cleanup window
SET @OLD_FOREIGN_KEY_CHECKS = @@FOREIGN_KEY_CHECKS;
SET FOREIGN_KEY_CHECKS = 0;

-- 1) Drop views not used by runtime PHP
DROP VIEW IF EXISTS `v_active_loans`;
DROP VIEW IF EXISTS `v_document_expiry`;
DROP VIEW IF EXISTS `v_maintenance_history`;
DROP VIEW IF EXISTS `v_maintenance_schedule`;

-- 2) Drop routines not used by runtime PHP
DROP FUNCTION IF EXISTS `CalculateFuelConsumption`;
DROP PROCEDURE IF EXISTS `GetVehicleAvailability`;

-- 3) If old FK exists from peminjaman_kendaraan.approval_by -> user_account_old, drop it
SET @have_fk := (SELECT COUNT(*) FROM information_schema.KEY_COLUMN_USAGE 
                 WHERE TABLE_SCHEMA = DATABASE()
                   AND TABLE_NAME = 'peminjaman_kendaraan'
                   AND COLUMN_NAME = 'approval_by'
                   AND REFERENCED_TABLE_NAME = 'user_account_old');
SET @sql := IF(@have_fk > 0,
  'ALTER TABLE `peminjaman_kendaraan` DROP FOREIGN KEY `fk_peminjaman_approval`',
  'SELECT 1');
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- 4) Re-point approval_by to pengguna.id when column exists
SET @have_col := (SELECT COUNT(*) FROM information_schema.COLUMNS 
                  WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'peminjaman_kendaraan' AND COLUMN_NAME = 'approval_by');
SET @have_pengguna := (SELECT COUNT(*) FROM information_schema.TABLES WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'pengguna');
SET @need_fk := (SELECT COUNT(*) FROM information_schema.KEY_COLUMN_USAGE 
                 WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'peminjaman_kendaraan' AND COLUMN_NAME = 'approval_by' AND REFERENCED_TABLE_NAME = 'pengguna');
-- Sanitize existing values that don't map to pengguna to avoid FK add failures
SET @sql := IF(@have_col > 0 AND @have_pengguna > 0,
  'UPDATE `peminjaman_kendaraan` pk LEFT JOIN `pengguna` p ON pk.approval_by = p.id SET pk.approval_by = NULL WHERE pk.approval_by IS NOT NULL AND p.id IS NULL',
  'SELECT 1');
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;
SET @sql := IF(@have_col > 0 AND @have_pengguna > 0 AND @need_fk = 0,
  'ALTER TABLE `peminjaman_kendaraan` MODIFY `approval_by` INT NULL, ADD CONSTRAINT `fk_peminjaman_approval_pengguna` FOREIGN KEY (`approval_by`) REFERENCES `pengguna`(`id`) ON DELETE SET NULL ON UPDATE CASCADE',
  'SELECT 1');
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- 5) In older dumps, notifikasi.user_id FK pointed to user_account_old; re-point to pengguna when needed
SET @have_notif := (SELECT COUNT(*) FROM information_schema.TABLES WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'notifikasi');
SET @notif_fk_old := (SELECT COUNT(*) FROM information_schema.KEY_COLUMN_USAGE 
                      WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'notifikasi' AND COLUMN_NAME = 'user_id' AND REFERENCED_TABLE_NAME = 'user_account_old');
SET @sql := IF(@have_notif > 0 AND @notif_fk_old > 0,
  'ALTER TABLE `notifikasi` DROP FOREIGN KEY `fk_notifikasi_user`', 'SELECT 1');
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

SET @notif_fk_new := (SELECT COUNT(*) FROM information_schema.KEY_COLUMN_USAGE 
                      WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'notifikasi' AND COLUMN_NAME = 'user_id' AND REFERENCED_TABLE_NAME = 'pengguna');
SET @sql := IF(@have_notif > 0 AND @notif_fk_new = 0,
  'ALTER TABLE `notifikasi` ADD CONSTRAINT `fk_notifikasi_pengguna` FOREIGN KEY (`user_id`) REFERENCES `pengguna`(`id`) ON DELETE SET NULL ON UPDATE CASCADE', 'SELECT 1');
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- 6) Optional: legacy user_activity table was only used for experiments; drop if present
DROP TABLE IF EXISTS `user_activity`;

-- 7) Drop legacy/backup tables not referenced by runtime code
DROP TABLE IF EXISTS `user_account_old`;
DROP TABLE IF EXISTS `pengguna_old`;
DROP TABLE IF EXISTS `jadwal_perawatan_backup`;
DROP TABLE IF EXISTS `kendaraan_old`;
DROP TABLE IF EXISTS `system_settings`;

-- 8) Re-enable FK checks
SET FOREIGN_KEY_CHECKS = @OLD_FOREIGN_KEY_CHECKS;

-- Done
