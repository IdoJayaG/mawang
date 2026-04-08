<?php
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../includes/auth.php';

// Start a session for CLI
if (session_status() == PHP_SESSION_NONE) session_start();

// Set session user id to a known user_account.id (adjust if your DB differs)
$_SESSION['user_id'] = 1; // change if necessary

$res = log_activity('TEST_CLI', 'Testing log_activity mapping');
if ($res) {
    echo "log_activity succeeded\n";
} else {
    echo "log_activity failed: " . ($mysqli->error ?? 'unknown') . "\n";
}
