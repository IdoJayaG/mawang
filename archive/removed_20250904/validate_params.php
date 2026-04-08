<?php
// Test parameter count validation

echo "=== VALIDASI PARAMETER BIND_PARAM ===\n\n";

// INSERT Statement validation
$insert_columns = [
    'nomor_surat', 'tanggal_surat', 'klasifikasi', 'lampiran', 'perihal', 
    'kepada_jabatan', 'kepada_tempat', 'dasar_a', 'dasar_b', 'berangkat_dari', 
    'waktu_berangkat', 'pejabat_ttd_jabatan', 'pejabat_ttd_sebagai', 
    'tembusan_1', 'tembusan_2', 'tembusan_3', 'tembusan_4', 
    'kendaraan_id', 'pengguna_id', 'tujuan', 'keperluan', 
    'tanggal_berangkat', 'tanggal_kembali', 'estimasi_km', 'estimasi_bbm', 
    'pejabat_ttd', 'created_by'
];

$insert_types = 'sssssssssssssssssiisssiidsi';

echo "INSERT Statement:\n";
echo "Kolom count: " . count($insert_columns) . "\n";
echo "Types count: " . strlen($insert_types) . "\n";
echo "Match: " . (count($insert_columns) == strlen($insert_types) ? "✓ YES" : "✗ NO") . "\n\n";

// Breakdown types
echo "Type breakdown:\n";
for ($i = 0; $i < count($insert_columns); $i++) {
    $type = substr($insert_types, $i, 1);
    echo ($i + 1) . ". {$insert_columns[$i]} -> {$type}\n";
}

echo "\n" . str_repeat("=", 50) . "\n\n";

// UPDATE Statement validation
$update_columns = [
    'nomor_surat', 'tanggal_surat', 'klasifikasi', 'lampiran', 'perihal',
    'kepada_jabatan', 'kepada_tempat', 'dasar_a', 'dasar_b', 'berangkat_dari',
    'waktu_berangkat', 'pejabat_ttd_jabatan', 'pejabat_ttd_sebagai',
    'tembusan_1', 'tembusan_2', 'tembusan_3', 'tembusan_4',
    'kendaraan_id', 'pengguna_id', 'tujuan', 'keperluan',
    'tanggal_berangkat', 'tanggal_kembali', 'estimasi_km', 'estimasi_bbm',
    'status', 'km_berangkat', 'km_kembali', 'bbm_terpakai',
    'laporan_perjalanan', 'pejabat_ttd', 'updated_by', 'id'
];

$update_types = 'ssssssssssssssssiisssiidsidssii';

echo "UPDATE Statement:\n";
echo "Kolom count: " . count($update_columns) . "\n";
echo "Types count: " . strlen($update_types) . "\n";
echo "Match: " . (count($update_columns) == strlen($update_types) ? "✓ YES" : "✗ NO") . "\n\n";

// Breakdown types
echo "Type breakdown:\n";
for ($i = 0; $i < count($update_columns); $i++) {
    $type = substr($update_types, $i, 1);
    echo ($i + 1) . ". {$update_columns[$i]} -> {$type}\n";
}

echo "\n=== HASIL VALIDASI ===\n";
echo "INSERT: " . (count($insert_columns) == strlen($insert_types) ? "✓ FIXED" : "✗ MASIH ERROR") . "\n";
echo "UPDATE: " . (count($update_columns) == strlen($update_types) ? "✓ FIXED" : "✗ MASIH ERROR") . "\n";
?>
