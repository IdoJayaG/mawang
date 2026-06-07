<?php
chdir(__DIR__ . '/..');
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../includes/auth.php';

header('Content-Type: application/json; charset=utf-8');

if (!is_logged_in()) {
    http_response_code(401);
    echo json_encode(['error' => 'Not authenticated']);
    exit;
}

$user_id = get_current_user_id();
if (!$user_id) {
    echo json_encode(['items' => [], 'last_check' => date('c')]);
    exit;
}

$last_check = isset($_GET['last_check']) ? trim($_GET['last_check']) : null;
// If not provided, return empty and set last_check to now
if (!$last_check) {
    echo json_encode(['items' => [], 'last_check' => date('c')]);
    exit;
}

// Normalize columns availability
$cols = db_table_columns('surat_tugas');
$timeCol = in_array('updated_at', $cols, true) ? 'updated_at' : (in_array('created_at', $cols, true) ? 'created_at' : null);
if (!$timeCol) {
    echo json_encode(['items' => [], 'last_check' => date('c')]);
    exit;
}

// Find surat_tugas updated since last_check where current user is requester, driver, or vehicle assignee
$sql = "SELECT s.id, s.nomor_surat, s.tanggal_berangkat, s.tujuan, s.keperluan, s.kendaraan_id, s.pengguna_id, s.driver_id, s." . $timeCol . " AS changed_at, k.pengguna_id AS kendaraan_pengguna_id
        FROM surat_tugas s
        LEFT JOIN kendaraan k ON s.kendaraan_id = k.id
        WHERE LOWER(COALESCE(s.status,'')) = 'disetujui'
          AND s.{$timeCol} > ?
          AND (
              s.pengguna_id = ?
              OR COALESCE(s.driver_id,0) = ?
              OR COALESCE(k.pengguna_id,0) = ?
          )
        ORDER BY s.{$timeCol} ASC
        LIMIT 50";

$stmt = $mysqli->prepare($sql);
if (!$stmt) {
    echo json_encode(['items' => [], 'last_check' => date('c'), 'error' => $mysqli->error]);
    exit;
}

$stmt->bind_param('siii', $last_check, $user_id, $user_id, $user_id);
$stmt->execute();
$res = $stmt->get_result();
$items = [];
while ($r = $res->fetch_assoc()) {
    $items[] = [
        'id' => (int)$r['id'],
        'nomor_surat' => $r['nomor_surat'],
        'tanggal_berangkat' => $r['tanggal_berangkat'],
        'tujuan' => $r['tujuan'],
        'keperluan' => $r['keperluan'],
        'kendaraan_id' => isset($r['kendaraan_id']) ? (int)$r['kendaraan_id'] : null,
        'changed_at' => $r['changed_at']
    ];
}

$stmt->close();

echo json_encode(['items' => $items, 'last_check' => date('c')]);
