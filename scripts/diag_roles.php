<?php
// List roles table
chdir(__DIR__ . '/..');
require_once 'config/db.php';

$res = $mysqli->query("SELECT id, nama_role, kode_role FROM roles");
if (!$res) { echo "ERROR: " . $mysqli->error . PHP_EOL; exit(1); }
$rows = [];
while ($r = $res->fetch_assoc()) { $rows[] = $r; }
echo json_encode($rows, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) . PHP_EOL;
