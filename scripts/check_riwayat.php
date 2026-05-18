<?php
// Small CLI checker: list recent surat_tugas (Selesai), recent riwayat_pemakaian,
// and joined rows to help verify mapping after run_status_transitions.php
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../config/db.php';

$mysqliVar = null;
if (isset($mysqli) && $mysqli instanceof mysqli) {
    $mysqliVar = $mysqli;
} elseif (isset($conn) && $conn instanceof mysqli) {
    $mysqliVar = $conn;
} else {
    fwrite(STDERR, "No mysqli connection found in config/db.php\n");
    exit(2);
}

function fetch_all($db, $sql) {
    $rows = [];
    $res = $db->query($sql);
    if ($res) {
        while ($r = $res->fetch_assoc()) $rows[] = $r;
    }
    return $rows;
}

$recent_surat = fetch_all($mysqliVar, "SELECT id, nomor_surat, kendaraan_id, status, tanggal_berangkat, tanggal_kembali, updated_at FROM surat_tugas WHERE status = 'Selesai' OR tanggal_kembali <= NOW() ORDER BY updated_at DESC LIMIT 50");
$recent_riwayat = fetch_all($mysqliVar, "SELECT id, kendaraan_id, tanggal, jam_keluar, jam_kembali, user_id FROM riwayat_pemakaian WHERE tanggal >= DATE_SUB(CURDATE(), INTERVAL 14 DAY) ORDER BY id DESC LIMIT 200");
$joined = fetch_all($mysqliVar, "SELECT rp.id as rp_id, rp.kendaraan_id, rp.tanggal as rp_tanggal, st.id as surat_id, st.nomor_surat, st.tanggal_kembali, st.updated_at as surat_updated_at FROM riwayat_pemakaian rp LEFT JOIN surat_tugas st ON st.kendaraan_id = rp.kendaraan_id AND (st.tanggal_kembali = rp.tanggal OR (st.tanggal_kembali IS NULL AND st.tanggal_berangkat = rp.tanggal)) WHERE rp.tanggal >= DATE_SUB(CURDATE(), INTERVAL 14 DAY) ORDER BY rp.id DESC LIMIT 200");

echo json_encode(['recent_surat_tugas' => $recent_surat, 'recent_riwayat' => $recent_riwayat, 'joined' => $joined], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) . PHP_EOL;

exit(0);
