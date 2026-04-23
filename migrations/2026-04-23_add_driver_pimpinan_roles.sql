-- Add new roles for operational workflow changes.
-- Safe to run multiple times.

INSERT INTO role (nama_role, kode_role, deskripsi, level_akses)
SELECT 'Driver', 'DRIVER', 'Akses pengelolaan BBM kendaraan yang menjadi tanggung jawab', 3
WHERE NOT EXISTS (
    SELECT 1 FROM role WHERE UPPER(kode_role) = 'DRIVER'
);

INSERT INTO role (nama_role, kode_role, deskripsi, level_akses)
SELECT 'Pimpinan', 'PIMPINAN', 'Monitoring dan notifikasi operasional/perawatan', 2
WHERE NOT EXISTS (
    SELECT 1 FROM role WHERE UPPER(kode_role) = 'PIMPINAN'
);
