<?php
// Send pending scheduled reminder emails.
// Intended to run periodically via Task Scheduler/cron. Logic lives in lib/email_triggers.php
// so the same code path is shared with the automatic in-app runner (config.php).

chdir(__DIR__ . '/..');
require_once 'config.php';
require_once 'lib/email_triggers.php';

$limit = 50;
if (PHP_SAPI === 'cli') {
    foreach (($argv ?? []) as $arg) {
        if (strpos($arg, '--limit=') === 0) {
            $limit = max(1, min(500, (int)substr($arg, 8)));
        }
    }
}

$result = send_pending_reminder_emails($mysqli, $limit);
if (!$result['ok']) {
    fwrite(STDERR, $result['message'] . "\n");
    exit(1);
}
echo "Pengiriman selesai. " . $result['message'] . "\n";
