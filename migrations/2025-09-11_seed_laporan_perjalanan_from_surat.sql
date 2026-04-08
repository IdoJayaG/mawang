-- Optional seed: populate laporan_perjalanan from surat_tugas if available
-- Adjust column names as per your surat_tugas schema

INSERT INTO laporan_perjalanan (tanggal, kendaraan_id, pengguna_id, uraian_kegiatan, route, jarak_km)
SELECT 
  COALESCE(tanggal_berangkat, tanggal_surat) AS tanggal,
  kendaraan_id,
  pengguna_id,
  CONCAT('Surat: ', COALESCE(keperluan, tujuan)) AS uraian_kegiatan,
  COALESCE(rute, tujuan) AS route,
  NULL AS jarak_km
FROM surat_tugas
WHERE kendaraan_id IS NOT NULL;
