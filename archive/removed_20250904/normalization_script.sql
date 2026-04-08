-- ==========================================
-- RANDIS DATABASE NORMALIZATION SCRIPT
-- ==========================================
-- Tanggal: 27 Agustus 2025
-- Tujuan: Normalisasi struktur database dan perbaikan tipe data

-- Backup tables before normalization
CREATE TABLE IF NOT EXISTS jadwal_perawatan_backup AS SELECT * FROM jadwal_perawatan;

-- 1. FIX DATA TYPES
-- Fix biaya_aktual column type in jadwal_perawatan
ALTER TABLE jadwal_perawatan 
MODIFY COLUMN biaya_aktual DECIMAL(15,2) DEFAULT NULL 
COMMENT 'Biaya aktual yang dikeluarkan untuk perawatan';

-- Fix estimasi_biaya column type for consistency
ALTER TABLE jadwal_perawatan 
MODIFY COLUMN estimasi_biaya DECIMAL(15,2) DEFAULT NULL 
COMMENT 'Estimasi biaya perawatan';

-- 2. ADD MISSING INDEXES FOR PERFORMANCE
ALTER TABLE jadwal_perawatan 
ADD INDEX idx_jadwal_status_tanggal (status, tanggal_perawatan),
ADD INDEX idx_jadwal_kendaraan_status (kendaraan_id, status);

ALTER TABLE riwayat_perawatan 
ADD INDEX idx_riwayat_kendaraan_tanggal (kendaraan_id, tanggal_perawatan),
ADD INDEX idx_riwayat_status (status);

ALTER TABLE log_bahan_bakar 
ADD INDEX idx_bbm_kendaraan_tanggal (kendaraan_id, tanggal_isi),
ADD INDEX idx_bbm_tanggal (tanggal_isi);

-- 3. ADD MISSING FOREIGN KEY CONSTRAINTS
-- Add FK for jadwal_perawatan
ALTER TABLE jadwal_perawatan 
ADD CONSTRAINT fk_jadwal_kendaraan 
FOREIGN KEY (kendaraan_id) REFERENCES kendaraan(id) 
ON UPDATE CASCADE ON DELETE RESTRICT;

ALTER TABLE jadwal_perawatan 
ADD CONSTRAINT fk_jadwal_created_by 
FOREIGN KEY (created_by) REFERENCES pengguna(id) 
ON UPDATE CASCADE ON DELETE SET NULL;

ALTER TABLE jadwal_perawatan 
ADD CONSTRAINT fk_jadwal_updated_by 
FOREIGN KEY (updated_by) REFERENCES pengguna(id) 
ON UPDATE CASCADE ON DELETE SET NULL;

ALTER TABLE jadwal_perawatan 
ADD CONSTRAINT fk_jadwal_teknisi 
FOREIGN KEY (teknisi_id) REFERENCES pengguna(id) 
ON UPDATE CASCADE ON DELETE SET NULL;

-- Add FK for riwayat_perawatan
ALTER TABLE riwayat_perawatan 
ADD CONSTRAINT fk_riwayat_kendaraan 
FOREIGN KEY (kendaraan_id) REFERENCES kendaraan(id) 
ON UPDATE CASCADE ON DELETE RESTRICT;

ALTER TABLE riwayat_perawatan 
ADD CONSTRAINT fk_riwayat_created_by 
FOREIGN KEY (created_by) REFERENCES pengguna(id) 
ON UPDATE CASCADE ON DELETE SET NULL;

-- Add FK for log_bahan_bakar
ALTER TABLE log_bahan_bakar 
ADD CONSTRAINT fk_bbm_kendaraan 
FOREIGN KEY (kendaraan_id) REFERENCES kendaraan(id) 
ON UPDATE CASCADE ON DELETE RESTRICT;

ALTER TABLE log_bahan_bakar 
ADD CONSTRAINT fk_bbm_user 
FOREIGN KEY (user_id) REFERENCES pengguna(id) 
ON UPDATE CASCADE ON DELETE RESTRICT;

-- Add FK for dokumen_kendaraan
ALTER TABLE dokumen_kendaraan 
ADD CONSTRAINT fk_dokumen_kendaraan 
FOREIGN KEY (kendaraan_id) REFERENCES kendaraan(id) 
ON UPDATE CASCADE ON DELETE CASCADE;

ALTER TABLE dokumen_kendaraan 
ADD CONSTRAINT fk_dokumen_created_by 
FOREIGN KEY (created_by) REFERENCES pengguna(id) 
ON UPDATE CASCADE ON DELETE SET NULL;

ALTER TABLE dokumen_kendaraan 
ADD CONSTRAINT fk_dokumen_updated_by 
FOREIGN KEY (updated_by) REFERENCES pengguna(id) 
ON UPDATE CASCADE ON DELETE SET NULL;

-- Add FK for surat_tugas
ALTER TABLE surat_tugas 
ADD CONSTRAINT fk_surat_kendaraan 
FOREIGN KEY (kendaraan_id) REFERENCES kendaraan(id) 
ON UPDATE CASCADE ON DELETE RESTRICT;

ALTER TABLE surat_tugas 
ADD CONSTRAINT fk_surat_pengguna 
FOREIGN KEY (pengguna_id) REFERENCES pengguna(id) 
ON UPDATE CASCADE ON DELETE RESTRICT;

ALTER TABLE surat_tugas 
ADD CONSTRAINT fk_surat_created_by 
FOREIGN KEY (created_by) REFERENCES pengguna(id) 
ON UPDATE CASCADE ON DELETE SET NULL;

ALTER TABLE surat_tugas 
ADD CONSTRAINT fk_surat_updated_by 
FOREIGN KEY (updated_by) REFERENCES pengguna(id) 
ON UPDATE CASCADE ON DELETE SET NULL;

-- Add FK for pengguna
ALTER TABLE pengguna 
ADD CONSTRAINT fk_pengguna_kesatuan 
FOREIGN KEY (kesatuan_id) REFERENCES kesatuan(id) 
ON UPDATE CASCADE ON DELETE SET NULL;

-- Add FK for user_account
ALTER TABLE user_account 
ADD CONSTRAINT fk_account_pengguna 
FOREIGN KEY (pengguna_id) REFERENCES pengguna(id) 
ON UPDATE CASCADE ON DELETE CASCADE;

ALTER TABLE user_account 
ADD CONSTRAINT fk_account_role 
FOREIGN KEY (role_id) REFERENCES role(id) 
ON UPDATE CASCADE ON DELETE RESTRICT;

-- Add FK for log_aktivitas
ALTER TABLE log_aktivitas 
ADD CONSTRAINT fk_log_user 
FOREIGN KEY (user_id) REFERENCES pengguna(id) 
ON UPDATE CASCADE ON DELETE CASCADE;

-- Add FK for riwayat_pemakaian
ALTER TABLE riwayat_pemakaian 
ADD CONSTRAINT fk_pemakaian_kendaraan 
FOREIGN KEY (kendaraan_id) REFERENCES kendaraan(id) 
ON UPDATE CASCADE ON DELETE RESTRICT;

ALTER TABLE riwayat_pemakaian 
ADD CONSTRAINT fk_pemakaian_user 
FOREIGN KEY (user_id) REFERENCES pengguna(id) 
ON UPDATE CASCADE ON DELETE RESTRICT;

ALTER TABLE riwayat_pemakaian 
ADD CONSTRAINT fk_pemakaian_driver 
FOREIGN KEY (driver_id) REFERENCES pengguna(id) 
ON UPDATE CASCADE ON DELETE SET NULL;

-- Add FK for riwayat_perbaikan
ALTER TABLE riwayat_perbaikan 
ADD CONSTRAINT fk_perbaikan_kendaraan 
FOREIGN KEY (kendaraan_id) REFERENCES kendaraan(id) 
ON UPDATE CASCADE ON DELETE RESTRICT;

ALTER TABLE riwayat_perbaikan 
ADD CONSTRAINT fk_perbaikan_created_by 
FOREIGN KEY (created_by) REFERENCES pengguna(id) 
ON UPDATE CASCADE ON DELETE SET NULL;

ALTER TABLE riwayat_perbaikan 
ADD CONSTRAINT fk_perbaikan_updated_by 
FOREIGN KEY (updated_by) REFERENCES pengguna(id) 
ON UPDATE CASCADE ON DELETE SET NULL;

-- 4. OPTIMIZE TABLE STRUCTURE
-- Add missing columns to riwayat_perawatan for compatibility
ALTER TABLE riwayat_perawatan 
ADD COLUMN IF NOT EXISTS kategori ENUM('Ringan','Sedang','Berat') DEFAULT 'Ringan' 
COMMENT 'Kategori perawatan berdasarkan tingkat kesulitan',
ADD COLUMN IF NOT EXISTS bengkel VARCHAR(100) DEFAULT NULL 
COMMENT 'Nama bengkel tempat perawatan',
ADD COLUMN IF NOT EXISTS teknisi_id INT(11) DEFAULT NULL 
COMMENT 'ID teknisi yang menangani',
ADD CONSTRAINT fk_riwayat_teknisi 
FOREIGN KEY (teknisi_id) REFERENCES pengguna(id) 
ON UPDATE CASCADE ON DELETE SET NULL;

-- 5. NORMALIZE ENUM VALUES
-- Standardize status values across tables
UPDATE jadwal_perawatan SET status = 'Selesai' WHERE status IN ('Completed', 'Done', 'Finished');
UPDATE riwayat_perawatan SET status = 'Selesai' WHERE status IN ('Completed', 'Done', 'Finished');
UPDATE riwayat_perbaikan SET status = 'Selesai' WHERE status IN ('Completed', 'Done', 'Finished');

-- 6. DATA CONSISTENCY FIXES
-- Fix biaya_aktual values that are too small (convert tinyint values)
UPDATE jadwal_perawatan 
SET biaya_aktual = CASE 
    WHEN biaya_aktual IS NOT NULL AND biaya_aktual < 1000 AND estimasi_biaya > 1000 
    THEN estimasi_biaya 
    ELSE biaya_aktual 
END;

-- 7. CLEAN UP REDUNDANT DATA
-- Remove duplicate or invalid records
DELETE j1 FROM jadwal_perawatan j1
INNER JOIN jadwal_perawatan j2 
WHERE j1.id > j2.id 
    AND j1.kendaraan_id = j2.kendaraan_id 
    AND j1.jenis_perawatan = j2.jenis_perawatan 
    AND j1.tanggal_perawatan = j2.tanggal_perawatan;

-- 8. UPDATE COLUMN COMMENTS FOR DOCUMENTATION
ALTER TABLE jadwal_perawatan 
MODIFY COLUMN bengkel VARCHAR(50) DEFAULT NULL COMMENT 'Nama bengkel atau lokasi perawatan',
MODIFY COLUMN teknisi_id INT(11) DEFAULT NULL COMMENT 'ID pengguna yang bertindak sebagai teknisi',
MODIFY COLUMN prioritas VARCHAR(30) DEFAULT NULL COMMENT 'Tingkat prioritas: Normal, Tinggi, Urgent';

ALTER TABLE riwayat_perawatan 
MODIFY COLUMN mekanik VARCHAR(100) DEFAULT NULL COMMENT 'Nama atau ID teknisi yang menangani perawatan';

-- 9. CREATE VIEWS FOR REPORTING
CREATE OR REPLACE VIEW v_maintenance_schedule AS
SELECT 
    jp.id,
    jp.kendaraan_id,
    k.no_polisi,
    k.merk,
    k.tipe,
    jp.jenis_perawatan,
    jp.tanggal_perawatan,
    jp.status,
    jp.prioritas,
    jp.estimasi_biaya,
    jp.biaya_aktual,
    p.nama_lengkap as teknisi_nama,
    jp.bengkel,
    DATEDIFF(jp.tanggal_perawatan, CURDATE()) as days_until_maintenance
FROM jadwal_perawatan jp
LEFT JOIN kendaraan k ON jp.kendaraan_id = k.id
LEFT JOIN pengguna p ON jp.teknisi_id = p.id
WHERE jp.status IN ('Terjadwal', 'Dalam Proses')
ORDER BY jp.tanggal_perawatan ASC;

CREATE OR REPLACE VIEW v_maintenance_history AS
SELECT 
    rp.id,
    rp.kendaraan_id,
    k.no_polisi,
    k.merk,
    k.tipe,
    rp.jenis_perawatan,
    rp.tanggal_perawatan,
    rp.km_saat_perawatan,
    rp.biaya,
    rp.mekanik,
    rp.status,
    rp.keterangan,
    rp.created_at
FROM riwayat_perawatan rp
LEFT JOIN kendaraan k ON rp.kendaraan_id = k.id
ORDER BY rp.tanggal_perawatan DESC;

-- 10. ADD TRIGGERS FOR DATA INTEGRITY
DELIMITER $$

CREATE TRIGGER tr_update_vehicle_odometer 
AFTER INSERT ON riwayat_pemakaian
FOR EACH ROW
BEGIN
    UPDATE kendaraan 
    SET odometer = NEW.km_akhir,
        last_service_km = NEW.km_akhir,
        updated_at = CURRENT_TIMESTAMP
    WHERE id = NEW.kendaraan_id 
    AND (odometer IS NULL OR NEW.km_akhir > odometer);
END$$

CREATE TRIGGER tr_log_maintenance_completion
AFTER UPDATE ON jadwal_perawatan
FOR EACH ROW
BEGIN
    IF NEW.status = 'Selesai' AND OLD.status != 'Selesai' THEN
        INSERT INTO log_aktivitas (user_id, activity_type, description, created_at)
        VALUES (
            COALESCE(NEW.updated_by, NEW.created_by, 1),
            'COMPLETE_MAINTENANCE',
            CONCAT('Menyelesaikan jadwal perawatan ', NEW.jenis_perawatan, ' untuk kendaraan ', 
                   (SELECT no_polisi FROM kendaraan WHERE id = NEW.kendaraan_id)),
            CURRENT_TIMESTAMP
        );
    END IF;
END$$

DELIMITER ;

-- Final optimization
OPTIMIZE TABLE jadwal_perawatan, riwayat_perawatan, kendaraan, pengguna, log_aktivitas;

-- Create summary
SELECT 'NORMALIZATION COMPLETED' as Status,
       NOW() as Completed_At,
       'Database structure normalized and optimized' as Message;
