<?php
require_once __DIR__ . '/../config/db.php';

$dbNameRes = $conn->query('SELECT DATABASE() AS dbn');
$dbName = $dbNameRes ? $dbNameRes->fetch_assoc()['dbn'] : 'randis';

echo "DB: {$dbName}\n\n";

echo "=== Kendaraan candidates ===\n";
$q = $conn->query("SELECT COUNT(*) AS total, SUM(CASE WHEN locator IS NOT NULL AND TRIM(locator) <> '' THEN 1 ELSE 0 END) AS with_uid FROM kendaraan");
if ($q) {
    $r = $q->fetch_assoc();
    echo "total={$r['total']}, with_uid={$r['with_uid']}, to_delete=" . ((int)$r['total'] - (int)$r['with_uid']) . "\n\n";
}

echo "=== Role schema check ===\n";
$roleCols = $conn->query("SHOW COLUMNS FROM pengguna");
while ($roleCols && ($c = $roleCols->fetch_assoc())) {
    if (stripos($c['Field'], 'role') !== false || stripos($c['Field'], 'jabatan') !== false) {
        echo "pengguna col: {$c['Field']} ({$c['Type']})\n";
    }
}
$uaCols = $conn->query("SHOW COLUMNS FROM user_account");
while ($uaCols && ($c = $uaCols->fetch_assoc())) {
    if (stripos($c['Field'], 'role') !== false || stripos($c['Field'], 'pengguna') !== false) {
        echo "user_account col: {$c['Field']} ({$c['Type']})\n";
    }
}
$rCols = $conn->query("SHOW COLUMNS FROM role");
while ($rCols && ($c = $rCols->fetch_assoc())) {
    echo "role col: {$c['Field']} ({$c['Type']})\n";
}

echo "\n=== Role distribution via user_account-role ===\n";
$sql = "SELECT r.kode_role, COUNT(*) AS cnt
        FROM user_account ua
        LEFT JOIN role r ON r.id = ua.role_id
        GROUP BY r.kode_role
        ORDER BY cnt DESC";
$res = $conn->query($sql);
while ($res && ($row = $res->fetch_assoc())) {
    echo ($row['kode_role'] ?? 'NULL') . ": {$row['cnt']}\n";
}

echo "\n=== FK refs to kendaraan ===\n";
$stmt = $conn->prepare("SELECT TABLE_NAME, COLUMN_NAME
    FROM information_schema.KEY_COLUMN_USAGE
    WHERE REFERENCED_TABLE_SCHEMA = ?
      AND REFERENCED_TABLE_NAME = 'kendaraan'
    ORDER BY TABLE_NAME, COLUMN_NAME");
$stmt->bind_param('s', $dbName);
$stmt->execute();
$fkK = $stmt->get_result();
while ($fkK && ($row = $fkK->fetch_assoc())) {
    echo "{$row['TABLE_NAME']}.{$row['COLUMN_NAME']}\n";
}
$stmt->close();

echo "\n=== FK refs to pengguna ===\n";
$stmt = $conn->prepare("SELECT TABLE_NAME, COLUMN_NAME
    FROM information_schema.KEY_COLUMN_USAGE
    WHERE REFERENCED_TABLE_SCHEMA = ?
      AND REFERENCED_TABLE_NAME = 'pengguna'
    ORDER BY TABLE_NAME, COLUMN_NAME");
$stmt->bind_param('s', $dbName);
$stmt->execute();
$fkP = $stmt->get_result();
while ($fkP && ($row = $fkP->fetch_assoc())) {
    echo "{$row['TABLE_NAME']}.{$row['COLUMN_NAME']}\n";
}
$stmt->close();
