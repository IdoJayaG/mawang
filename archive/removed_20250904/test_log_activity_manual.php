<?php
require_once __DIR__ . '/config/db.php';
require_once __DIR__ . '/includes/auth.php';

// Start session for log_activity check
if (session_status() === PHP_SESSION_NONE) session_start();

// Try to find a user_account id
$res = mysqli_query($conn, "SELECT id FROM user_account LIMIT 1");
$row = $res ? mysqli_fetch_assoc($res) : null;
if (!$row) {
    echo "No user_account found in DB; cannot run test.\n";
    exit(1);
}

$_SESSION['user_id'] = (int)$row['id'];

echo "Using session user_id = " . $_SESSION['user_id'] . "\n";

$ok = log_activity('MANUAL_TEST', 'Manual test of log_activity');
echo $ok ? "log_activity returned true\n" : "log_activity returned false\n";

// Verify
$check = mysqli_query($conn, "SELECT * FROM log_aktivitas WHERE activity_type = 'MANUAL_TEST' ORDER BY created_at DESC LIMIT 1");
if ($check && mysqli_num_rows($check) > 0) {
    $l = mysqli_fetch_assoc($check);
    echo "Inserted log id=" . $l['id'] . " user_id=" . $l['user_id'] . " at " . $l['created_at'] . "\n";
    // cleanup
    mysqli_query($conn, "DELETE FROM log_aktivitas WHERE id = " . intval($l['id']));
    echo "Cleanup done.\n";
} else {
    echo "No log row found after calling log_activity. Check error logs for messages.\n";
}

?>
