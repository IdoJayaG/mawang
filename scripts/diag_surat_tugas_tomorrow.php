<?php
// Diagnostic helper: list surat_tugas scheduled for tomorrow
// Run: php scripts/diag_surat_tugas_tomorrow.php

chdir(__DIR__ . '/..');
require_once 'config/db.php';

header('Content-Type: application/json; charset=utf-8');
$mysqli->set_charset('utf8mb4');

$hasDriver = false;
try {
    $colRes = $mysqli->query("SHOW COLUMNS FROM surat_tugas LIKE 'driver_id'");
    if ($colRes && $colRes->num_rows > 0) $hasDriver = true;
    if ($colRes) $colRes->free_result();
} catch (Throwable $e) { /* ignore */ }

if ($hasDriver) {
    $selectDriver = "IFNULL(s.driver_id,'') AS driver_id, ";
} else {
    $selectDriver = "";
}

$sql = "SELECT s.id, s.nomor_surat, s.pengguna_id, " . $selectDriver . " s.kendaraan_id, DATE_FORMAT(s.tanggal_berangkat, '%Y-%m-%d %H:%i:%s') AS tanggal_berangkat, s.status, k.pengguna_id AS kendaraan_pengguna_id
        FROM surat_tugas s
        LEFT JOIN kendaraan k ON s.kendaraan_id = k.id
        WHERE DATE(s.tanggal_berangkat) = DATE(DATE_ADD(CURDATE(), INTERVAL 1 DAY))
          AND (s.status IS NULL OR LOWER(s.status) NOT IN ('selesai','dibatalkan'))
        ORDER BY s.tanggal_berangkat ASC, s.id DESC
        LIMIT 200";

$res = $mysqli->query($sql);
if (!$res) {
    echo json_encode(['error' => true, 'message' => $mysqli->error], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
    exit(1);
}

$rows = [];
while ($r = $res->fetch_assoc()) {
    $rows[] = $r;
}

echo json_encode(['error' => false, 'count' => count($rows), 'rows' => $rows], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) . PHP_EOL;
