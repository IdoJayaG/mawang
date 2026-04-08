<?php
// CLI script to run status transitions. Intended for scheduled task / cron.
chdir(__DIR__ . '/..');
require_once 'config.php';
require_once 'config/db.php';
require_once 'lib/run_status_transitions.php';

$current_user = null;
// when run from CLI, $argv may include --user=ID
foreach ($argv ?? [] as $arg) {
    if (strpos($arg, '--user=') === 0) {
        $current_user = (int)substr($arg, 7);
    }
}

try {
    run_status_transitions($mysqli, $current_user);
    echo "OK\n";
} catch (Throwable $e) {
    file_put_contents('logs/run_status_transitions_error.log', date('c') . ' ' . $e->getMessage() . "\n", FILE_APPEND);
    echo "ERROR: " . $e->getMessage() . "\n";
}
