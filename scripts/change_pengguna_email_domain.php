<?php
require_once __DIR__ . '/../config.php';
$mysqli = isset($conn) ? $conn : (isset($mysqli) ? $mysqli : null);
if (!($mysqli instanceof mysqli)) {
    fwrite(STDERR, "Database connection not available\n");
    exit(1);
}

$domain_from = '@setjen.kemhan.go.id';
$domain_to = '@gmail.com';
$log_file = __DIR__ . '/../logs/changed_pengguna_emails.log';

// Find matching rows
$sql = "SELECT id, nama_lengkap, email FROM pengguna WHERE email IS NOT NULL AND LOWER(TRIM(email)) LIKE '%" . strtolower(ltrim($domain_from, '@')) . "'";
// Note: build WHERE using domain with @, but we'll use LIKE '%@setjen.kemhan.go.id'
$sql = "SELECT id, nama_lengkap, email FROM pengguna WHERE email IS NOT NULL AND LOWER(TRIM(email)) LIKE '%@setjen.kemhan.go.id'";
$res = $mysqli->query($sql);
if (!$res) {
    fwrite(STDERR, "Query failed: " . $mysqli->error . "\n");
    exit(1);
}
$count = $res->num_rows;
printf("Found %d pengguna rows with domain %s\n", $count, $domain_from);
if ($count === 0) exit(0);

$updateStmt = $mysqli->prepare("UPDATE pengguna SET email = ? WHERE id = ?");
if (!$updateStmt) {
    fwrite(STDERR, "Prepare failed: " . $mysqli->error . "\n");
    exit(1);
}

$log_lines = [];
while ($row = $res->fetch_assoc()) {
    $id = (int)$row['id'];
    $old = trim((string)$row['email']);
    $local = preg_replace('/@.*$/', '', $old);
    $new = $local . $domain_to;
    $updateStmt->bind_param('si', $new, $id);
    if ($updateStmt->execute()) {
        $log_lines[] = date('Y-m-d H:i:s') . " | id={$id} | name={$row['nama_lengkap']} | old={$old} | new={$new}";
    } else {
        $log_lines[] = date('Y-m-d H:i:s') . " | id={$id} | name={$row['nama_lengkap']} | ERROR: " . $updateStmt->error;
    }
}
$updateStmt->close();

file_put_contents($log_file, implode("\n", $log_lines) . "\n", FILE_APPEND);

printf("Updated %d rows. Log written to %s\n", count($log_lines), $log_file);

// Show a sample of updated rows
$sample = $mysqli->query("SELECT id, nama_lengkap, email FROM pengguna WHERE LOWER(TRIM(email)) LIKE '%@gmail.com' ORDER BY id DESC LIMIT 20");
if ($sample) {
    printf("\nSample updated pengguna (last 20 with @gmail.com):\n");
    while ($r = $sample->fetch_assoc()) {
        printf("ID=%d | name=%s | email=%s\n", $r['id'], $r['nama_lengkap'], $r['email']);
    }
}

exit(0);
