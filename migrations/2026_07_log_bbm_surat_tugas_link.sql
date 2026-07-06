-- =============================================================
-- Link log_bahan_bakar ke surat_tugas — Migration
-- Tujuan: BBM aktual (surat_tugas.bbm_terpakai) diisi otomatis dari total
-- log_bahan_bakar yang dikaitkan ke trip tersebut, bukan input manual terpisah.
-- Aman dijalankan berkali-kali (IF NOT EXISTS / ADD COLUMN IF NOT EXISTS)
-- =============================================================

ALTER TABLE `log_bahan_bakar`
    ADD COLUMN IF NOT EXISTS `surat_tugas_id`
        INT NULL
        COMMENT 'Trip surat tugas yang sedang berjalan saat isi BBM ini dicatat (NULL = isi BBM rutin, tidak terkait trip tertentu)';

ALTER TABLE `log_bahan_bakar`
    ADD INDEX IF NOT EXISTS `idx_lbb_surat_tugas` (`surat_tugas_id`);

-- Catatan: tidak pakai FK constraint (mengikuti pola soft-reference yang sudah dipakai
-- di peminjaman_kendaraan.surat_tugas_id pada codebase ini) — integritas dijaga di kode aplikasi.
