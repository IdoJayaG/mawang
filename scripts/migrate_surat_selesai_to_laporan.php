<?php
/**
 * Migrasi: pindahkan semua surat_tugas dengan status 'Selesai' ke tabel laporan_perjalanan
 * - Idempotent: tidak akan membuat duplikat bila laporan_perjalanan untuk kendaraan+tanggal sudah ada
 * - Menyalin: tanggal => tanggal_berangkat, kendaraan_id, pengguna_id (jika ada), uraian_kegiatan (laporan_perjalanan/keperluan/nomor_surat), route => tujuan
 * - jarak_km diisi dari estimasi_km jika tersedia
 * Usage (CLI):
 *   php scripts/migrate_surat_selesai_to_laporan.php
 */

require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../config/db.php';

if (php_sapi_name() !== 'cli') {
    echo "This script is intended to be run from CLI only.\n";
    exit(1);
}

echo "Starting migration: surat_tugas (status='Selesai') -> laporan_perjalanan\n";

$q = "SELECT id, kendaraan_id, pengguna_id, tanggal_berangkat, estimasi_km, nomor_surat, tujuan, keperluan, laporan_perjalanan FROM surat_tugas WHERE LOWER(TRIM(status)) = 'selesai'";
$res = $mysqli->query($q);
if (!$res) {
    echo "Query failed: " . $mysqli->error . "\n";
    exit(1);
}

$inserted = 0; $skipped = 0; $errors = 0;
while ($s = $res->fetch_assoc()) {
    $sid = (int)$s['id'];
    $kend = isset($s['kendaraan_id']) ? (int)$s['kendaraan_id'] : 0;
    $tanggal = trim((string)($s['tanggal_berangkat'] ?? ''));
    if (!$kend || $tanggal === '') {
        $skipped++;
        echo "Skip surat_id={$sid}: missing kendaraan or tanggal_berangkat\n";
        continue;
    }

    // Cek apakah laporan_perjalanan untuk kendaraan+tanggal sudah ada
    $chk = $mysqli->prepare("SELECT id FROM laporan_perjalanan WHERE kendaraan_id = ? AND DATE(tanggal) = DATE(?) LIMIT 1");
    if (!$chk) { echo "Prepare failed: " . $mysqli->error . "\n"; $errors++; continue; }
    $chk->bind_param('is', $kend, $tanggal);
    $chk->execute();
    $r = $chk->get_result()->fetch_assoc();
    $chk->close();
    if ($r && !empty($r['id'])) { $skipped++; echo "Exists laporan for kendaraan={$kend} date={$tanggal}, skipping\n"; continue; }

    $uraian = trim($s['laporan_perjalanan'] ?? $s['keperluan'] ?? $s['nomor_surat'] ?? ('Surat Tugas ' . $sid));
    $route = $s['tujuan'] ?? '';
    $peng = isset($s['pengguna_id']) && $s['pengguna_id'] !== null && $s['pengguna_id'] !== '' ? (int)$s['pengguna_id'] : null;
    $estimasi = null;
    if (isset($s['estimasi_km']) && $s['estimasi_km'] !== '' && is_numeric($s['estimasi_km'])) $estimasi = (float)$s['estimasi_km'];

    if ($peng === null) {
        if ($estimasi === null) {
            $ins = $mysqli->prepare("INSERT INTO laporan_perjalanan (tanggal, kendaraan_id, pengguna_id, uraian_kegiatan, route, jarak_km, created_at) VALUES (?, ?, NULL, ?, ?, NULL, NOW())");
            if ($ins) { $ins->bind_param('siss', $tanggal, $kend, $uraian, $route); }
        } else {
            $ins = $mysqli->prepare("INSERT INTO laporan_perjalanan (tanggal, kendaraan_id, pengguna_id, uraian_kegiatan, route, jarak_km, created_at) VALUES (?, ?, NULL, ?, ?, ?, NOW())");
            if ($ins) { $ins->bind_param('sissd', $tanggal, $kend, $uraian, $route, $estimasi); }
        }
    } else {
        if ($estimasi === null) {
            $ins = $mysqli->prepare("INSERT INTO laporan_perjalanan (tanggal, kendaraan_id, pengguna_id, uraian_kegiatan, route, jarak_km, created_at) VALUES (?, ?, ?, ?, ?, NULL, NOW())");
            if ($ins) { $ins->bind_param('siiss', $tanggal, $kend, $peng, $uraian, $route); }
        } else {
            $ins = $mysqli->prepare("INSERT INTO laporan_perjalanan (tanggal, kendaraan_id, pengguna_id, uraian_kegiatan, route, jarak_km, created_at) VALUES (?, ?, ?, ?, ?, ?, NOW())");
            if ($ins) { $ins->bind_param('siissd', $tanggal, $kend, $peng, $uraian, $route, $estimasi); }
        }
    }

    if (!$ins) { echo "Prepare insert failed: " . $mysqli->error . "\n"; $errors++; continue; }
    $ok = $ins->execute();
    if ($ok) {
        $inserted++;
        echo "Inserted laporan for surat_id={$sid} kendaraan={$kend} tanggal={$tanggal}\n";
    } else {
        echo "Failed insert for surat_id={$sid}: " . $ins->error . "\n";
        $errors++;
    }
    $ins->close();
}

echo "Done. Inserted={$inserted}, Skipped={$skipped}, Errors={$errors}\n";
exit(0);
