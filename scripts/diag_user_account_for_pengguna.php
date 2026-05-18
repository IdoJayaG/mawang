<?php
// Usage: php scripts/diag_user_account_for_pengguna.php <pengguna_id>
chdir(__DIR__ . '/..');
require_once 'config/db.php';

$pid = isset($argv[1]) ? (int)$argv[1] : 0;
if ($pid <= 0) {
    fwrite(STDERR, "Usage: php scripts/diag_user_account_for_pengguna.php <pengguna_id>\n");
    exit(1);
}

$sql = "SELECT id, username, role_id, pengguna_id, created_at FROM user_account WHERE pengguna_id = ? LIMIT 50";
$stmt = $mysqli->prepare($sql);
if (!$stmt) { echo json_encode(['error' => true, 'message' => $mysqli->error], JSON_PRETTY_PRINT|JSON_UNESCAPED_UNICODE) . PHP_EOL; exit(1); }
$stmt->bind_param('i', $pid);
$stmt->execute();
$res = $stmt->get_result();
$rows = [];
while ($r = $res->fetch_assoc()) { $rows[] = $r; }
echo json_encode(['error' => false, 'pengguna_id' => $pid, 'count' => count($rows), 'rows' => $rows], JSON_PRETTY_PRINT|JSON_UNESCAPED_UNICODE) . PHP_EOL;
