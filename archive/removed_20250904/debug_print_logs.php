<?php
require_once __DIR__ . '/config/db.php';

$user_id = $argv[1] ?? 3; // default to 3

$sql = "SELECT id, user_id, activity_type, description, ip_address, user_agent, created_at FROM log_aktivitas WHERE user_id = ? ORDER BY created_at DESC LIMIT 20";
$stmt = mysqli_prepare($conn, $sql);
mysqli_stmt_bind_param($stmt, 'i', $user_id);
mysqli_stmt_execute($stmt);
$res = mysqli_stmt_get_result($stmt);

if (!$res) {
    echo "Query failed: " . mysqli_error($conn) . "\n";
    exit(1);
}

while ($r = mysqli_fetch_assoc($res)) {
    echo "--- Log ID: " . $r['id'] . " ---\n";
    foreach ($r as $k => $v) {
        echo sprintf("%s: %s\n", $k, var_export($v, true));
    }
    echo "\n";
}

?>
