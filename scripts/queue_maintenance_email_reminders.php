<?php
// Queue H-1 and H (today) maintenance reminder emails for user and driver.
// Intended to run periodically via Task Scheduler/cron. Logic lives in lib/email_triggers.php
// so the same code path is shared with the automatic in-app runner (config.php).

chdir(__DIR__ . '/..');
require_once 'config.php';
require_once 'lib/email_triggers.php';

$result = queue_maintenance_reminders($mysqli);
if (!$result['ok']) {
    fwrite(STDERR, $result['message'] . "\n");
    exit(1);
}
echo "Queue reminder selesai. " . $result['message'] . "\n";
