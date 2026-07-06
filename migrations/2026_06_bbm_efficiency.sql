-- =============================================================
-- BBM Efficiency Tracking — Migration
-- Aman dijalankan berkali-kali (IF NOT EXISTS / ADD COLUMN IF NOT EXISTS)
-- =============================================================

-- 1. Cache efisiensi per kendaraan (dihitung dari log_bahan_bakar)
CREATE TABLE IF NOT EXISTS `vehicle_efficiency_cache` (
    `kendaraan_id`  INT           NOT NULL,
    `avg_kml`       DECIMAL(6,2)  NOT NULL DEFAULT 0.00   COMMENT 'Rata-rata km/liter historis',
    `total_km`      INT           NOT NULL DEFAULT 0       COMMENT 'Total km terhitung dari odometer',
    `total_liter`   DECIMAL(10,2) NOT NULL DEFAULT 0.00   COMMENT 'Total liter dari semua fill yang valid',
    `sample_count`  INT           NOT NULL DEFAULT 0       COMMENT 'Jumlah fill yang dipakai dalam perhitungan',
    `last_computed` DATETIME      NOT NULL                 COMMENT 'Terakhir dicompute',
    `computed_from` DATE          NULL                     COMMENT 'Tanggal fill paling awal yang dipakai',
    `computed_to`   DATE          NULL                     COMMENT 'Tanggal fill paling akhir yang dipakai',
    PRIMARY KEY (`kendaraan_id`),
    CONSTRAINT `fk_vec_kendaraan`
        FOREIGN KEY (`kendaraan_id`) REFERENCES `kendaraan`(`id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- 2. Kolom anomali di surat_tugas (level trip: estimasi vs aktual)
ALTER TABLE `surat_tugas`
    ADD COLUMN IF NOT EXISTS `bbm_anomali`
        TINYINT(1) NOT NULL DEFAULT 0
        COMMENT '1 = deviasi >30% antara estimasi_bbm dan bbm_terpakai',
    ADD COLUMN IF NOT EXISTS `bbm_anomali_pct`
        DECIMAL(6,2) NULL
        COMMENT 'Persentase deviasi aktual (%) disimpan saat trip selesai';

-- 3. Kolom anomali & efisiensi di log_bahan_bakar (level isian)
ALTER TABLE `log_bahan_bakar`
    ADD COLUMN IF NOT EXISTS `kml_this_fill`
        DECIMAL(6,2) NULL
        COMMENT 'km/liter dihitung dari fill ini vs fill sebelumnya',
    ADD COLUMN IF NOT EXISTS `anomali_flag`
        TINYINT(1) NOT NULL DEFAULT 0
        COMMENT '1 = kml_this_fill deviasi >30% dari rata-rata historis kendaraan';

-- Index untuk query anomali yang cepat
ALTER TABLE `log_bahan_bakar`
    ADD INDEX IF NOT EXISTS `idx_lbb_anomali` (`anomali_flag`),
    ADD INDEX IF NOT EXISTS `idx_lbb_kendaraan_tanggal` (`kendaraan_id`, `tanggal_isi`);

ALTER TABLE `surat_tugas`
    ADD INDEX IF NOT EXISTS `idx_st_bbm_anomali` (`bbm_anomali`);
