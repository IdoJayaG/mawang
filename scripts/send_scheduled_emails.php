<?php
// Send pending scheduled reminder emails.
// Intended to run periodically via Task Scheduler/cron.

chdir(__DIR__ . '/..');
require_once 'config.php';
require_once 'lib/mailer.php';

function table_exists_local(mysqli $db, $table)
{
    $safe = $db->real_escape_string((string)$table);
    $res = $db->query("SHOW TABLES LIKE '{$safe}'");
    return $res && $res->num_rows > 0;
}

if (!table_exists_local($mysqli, 'email_reminder_jobs')) {
    fwrite(STDERR, "Table email_reminder_jobs belum ada. Jalankan migration terlebih dahulu.\n");
    exit(1);
}

$limit = 50;
if (PHP_SAPI === 'cli') {
    foreach (($argv ?? []) as $arg) {
        if (strpos($arg, '--limit=') === 0) {
            $limit = max(1, min(500, (int)substr($arg, 8)));
        }
    }
}

$sql = "SELECT id, recipient_email, recipient_name, subject, body_html, body_text, attempt_count, max_attempts FROM email_reminder_jobs WHERE status = 'pending' AND send_at <= NOW() AND attempt_count < max_attempts ORDER BY send_at ASC, id ASC LIMIT ?";
$stmt = $mysqli->prepare($sql);
$stmt->bind_param('i', $limit);
$stmt->execute();
$res = $stmt->get_result();

$jobs = [];
while ($row = $res->fetch_assoc()) {
    $jobs[] = $row;
}
$stmt->close();

if (empty($jobs)) {
    echo "Tidak ada email reminder yang jatuh tempo.\n";
    exit(0);
}

$sent = 0;
$failed = 0;

$updSent = $mysqli->prepare("UPDATE email_reminder_jobs SET status='sent', sent_at=NOW(), attempt_count=attempt_count+1, last_error=NULL, updated_at=NOW() WHERE id = ?");
$updFail = $mysqli->prepare("UPDATE email_reminder_jobs SET status=IF(attempt_count+1 >= max_attempts, 'failed', 'pending'), attempt_count=attempt_count+1, last_error=?, updated_at=NOW() WHERE id = ?");

foreach ($jobs as $job) {
    $id = (int)$job['id'];
    $err = null;

    $ok = app_send_email(
        (string)$job['recipient_email'],
        (string)($job['recipient_name'] ?? ''),
        (string)$job['subject'],
        (string)$job['body_html'],
        (string)($job['body_text'] ?? ''),
        $err
    );

    if ($ok) {
        $updSent->bind_param('i', $id);
        $updSent->execute();
        $sent++;
        continue;
    }

    $lastError = trim((string)$err);
    if ($lastError === '') {
        $lastError = 'Unknown send error';
    }

    $updFail->bind_param('si', $lastError, $id);
    $updFail->execute();
    $failed++;
}

$updSent->close();
$updFail->close();

echo "Pengiriman selesai. Sent={$sent}, Failed={$failed}, Total=" . count($jobs) . "\n";
