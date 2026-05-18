<?php
// List columns of user_account table
chdir(__DIR__ . '/..');
require_once 'config/db.php';

$res = $mysqli->query("SHOW COLUMNS FROM user_account");
if (!$res) { echo "ERROR: " . $mysqli->error . PHP_EOL; exit(1); }
$cols = [];
while ($r = $res->fetch_assoc()) { $cols[] = $r; }
echo json_encode($cols, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) . PHP_EOL;
