<?php
// Simulate the dashboard's surat_tugas reminder query for a given pengguna_id
// Usage: php scripts/simulate_dashboard_reminder.php <pengguna_id>

chdir(__DIR__ . '/..');
require_once 'config/db.php';

$pid = isset($argv[1]) ? (int)$argv[1] : 0;
if ($pid <= 0) {
    fwrite(STDERR, "Usage: php scripts/simulate_dashboard_reminder.php <pengguna_id>\n");
    exit(1);
}

$tomorrow = date('Y-m-d', strtotime('+1 day'));

$hasDriver = false;
try {
    $colRes = $mysqli->query("SHOW COLUMNS FROM surat_tugas LIKE 'driver_id'");
    if ($colRes && $colRes->num_rows > 0) $hasDriver = true;
    if ($colRes) $colRes->free_result();
} catch (Throwable $e) { }

$selectCols = "s.id, s.nomor_surat, s.tanggal_berangkat, s.tujuan, s.keperluan, s.kendaraan_id, s.pengguna_id";
if ($hasDriver) $selectCols .= ", s.driver_id";

$sql = "SELECT " . $selectCols . ", k.no_reg, k.no_polisi FROM surat_tugas s LEFT JOIN kendaraan k ON s.kendaraan_id = k.id WHERE DATE(s.tanggal_berangkat) = ? AND (s.status IS NULL OR LOWER(s.status) NOT IN ('selesai','dibatalkan')) AND (s.pengguna_id = ?";
if ($hasDriver) $sql .= " OR s.driver_id = ?";
$sql .= " OR k.pengguna_id = ?)";

$stmt = $mysqli->prepare($sql);
if (!$stmt) { echo "Prepare failed: " . $mysqli->error . PHP_EOL; exit(1); }

if ($hasDriver) {
    $stmt->bind_param('siii', $tomorrow, $pid, $pid, $pid);
} else {
    $stmt->bind_param('sii', $tomorrow, $pid, $pid);
}

$stmt->execute();
$res = $stmt->get_result();
$rows = [];
while ($r = $res->fetch_assoc()) { $rows[] = $r; }

echo json_encode(['pengguna_id' => $pid, 'tomorrow' => $tomorrow, 'hasDriverCol' => $hasDriver, 'count' => count($rows), 'rows' => $rows], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) . PHP_EOL;
