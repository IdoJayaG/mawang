<?php
require_once 'config/db.php';

echo "Menambahkan kolom-kolom TNI yang hilang ke tabel surat_tugas...\n\n";

// Array kolom yang perlu ditambahkan
$new_columns = [
    "klasifikasi VARCHAR(20) DEFAULT 'Biasa' AFTER tanggal_surat",
    "lampiran VARCHAR(100) DEFAULT '-' AFTER klasifikasi",
    "perihal VARCHAR(255) DEFAULT 'Permohonan peminjaman kendaraan dinas bus dan tenaga medis' AFTER lampiran",
    "kepada_jabatan VARCHAR(100) DEFAULT 'Dandenma Mabes TNI' AFTER perihal",
    "kepada_tempat VARCHAR(50) DEFAULT 'Jakarta' AFTER kepada_jabatan",
    "dasar_a TEXT DEFAULT 'Peraturan Panglima TNI Nomor 24 Tahun 2014 tentang Pengesahan Validasi Organisasi FKT dan;' AFTER kepada_tempat",
    "dasar_b TEXT DEFAULT 'Surat Perintah Kapusinfolahta TNI Nomor Sprin/97/XI/2023 tanggal 20 Oktober 2023 tentang Fungsi Pengadaan HUT ke-17 Pusinfolahta TNI dan;' AFTER dasar_a",
    "berangkat_dari VARCHAR(100) DEFAULT 'Pusinfolahta TNI' AFTER dasar_b",
    "waktu_berangkat VARCHAR(50) DEFAULT 'Pukul 05.00 WIB s.d selesai' AFTER berangkat_dari",
    "pejabat_ttd_jabatan VARCHAR(100) DEFAULT 'a.n Kepala Pusinfolahta TNI' AFTER waktu_berangkat",
    "pejabat_ttd_sebagai VARCHAR(50) DEFAULT 'Waka,' AFTER pejabat_ttd_jabatan",
    "tembusan_1 VARCHAR(100) DEFAULT 'Kapusinfolahta TNI' AFTER pejabat_ttd_sebagai",
    "tembusan_2 VARCHAR(100) DEFAULT 'Asops Denma Mabes TNI' AFTER tembusan_1",
    "tembusan_3 VARCHAR(100) DEFAULT 'Dansetang Denma Mabes TNI' AFTER tembusan_2",
    "tembusan_4 VARCHAR(100) DEFAULT 'Dansakdok Denma Mabes TNI' AFTER tembusan_3"
];

$success_count = 0;
$error_count = 0;

foreach ($new_columns as $column_def) {
    $sql = "ALTER TABLE surat_tugas ADD COLUMN $column_def";
    
    if ($conn->query($sql)) {
        echo "✓ Berhasil menambahkan kolom: " . explode(' ', $column_def)[0] . "\n";
        $success_count++;
    } else {
        echo "✗ Gagal menambahkan kolom: " . explode(' ', $column_def)[0] . " - " . $conn->error . "\n";
        $error_count++;
    }
}

echo "\n=== HASIL ===\n";
echo "Berhasil: $success_count kolom\n";
echo "Gagal: $error_count kolom\n";

if ($success_count > 0) {
    echo "\nTabel surat_tugas berhasil diperbarui dengan kolom TNI!\n";
    
    // Verifikasi struktur baru
    echo "\nStruktur tabel setelah update:\n";
    $result = $conn->query('DESCRIBE surat_tugas');
    if ($result) {
        while ($row = $result->fetch_assoc()) {
            echo "- " . $row['Field'] . " (" . $row['Type'] . ")\n";
        }
    }
}

$conn->close();
?>
