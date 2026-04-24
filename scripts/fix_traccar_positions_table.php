<?php
require_once __DIR__ . '/../config/db.php';

echo "=== FIX traccar_positions_last ===\n\n";

$mysqli->begin_transaction();

try {
    // 1) Clean test artifacts
    $deletedTest = 0;
    if ($res = $mysqli->query("DELETE FROM traccar_positions_last WHERE device_uid = '__test__' OR device_name = 'Test Device'")) {
        $deletedTest = $mysqli->affected_rows;
    }

    // 2) Normalize empty uid to NULL so unique index can be added safely
    $mysqli->query("UPDATE traccar_positions_last SET device_uid = NULL WHERE TRIM(COALESCE(device_uid, '')) = ''");

    // 3) Remove duplicate rows by device_uid, keep newest updated_at then highest id
    $sqlDedup = "DELETE p1
        FROM traccar_positions_last p1
        JOIN traccar_positions_last p2
          ON CONVERT(TRIM(p1.device_uid) USING utf8mb4) COLLATE utf8mb4_unicode_ci = CONVERT(TRIM(p2.device_uid) USING utf8mb4) COLLATE utf8mb4_unicode_ci
         AND p1.device_uid IS NOT NULL
         AND p2.device_uid IS NOT NULL
         AND (
            p1.updated_at < p2.updated_at
            OR (p1.updated_at = p2.updated_at AND p1.id < p2.id)
         )";
    $mysqli->query($sqlDedup);
    $deletedDup = $mysqli->affected_rows;

    // 4) Ensure unique index on device_uid exists
    $hasUidUnique = false;
    $idx = $mysqli->query("SHOW INDEX FROM traccar_positions_last WHERE Key_name = 'uniq_traccar_positions_last_device_uid'");
    if ($idx && $idx->num_rows > 0) {
        $hasUidUnique = true;
    }

    if (!$hasUidUnique) {
        $mysqli->query("ALTER TABLE traccar_positions_last ADD UNIQUE KEY uniq_traccar_positions_last_device_uid (device_uid)");
    }

    $mysqli->commit();

    echo "[OK] Deleted test rows: {$deletedTest}\n";
    echo "[OK] Deleted duplicate uid rows: {$deletedDup}\n";
    echo "[OK] Unique index on device_uid: " . ($hasUidUnique ? 'already_exists' : 'created') . "\n\n";

    // 5) Show current table content summary
    $total = 0;
    $rTotal = $mysqli->query("SELECT COUNT(*) AS cnt FROM traccar_positions_last")->fetch_assoc();
    if ($rTotal) {
        $total = (int)$rTotal['cnt'];
    }

    echo "Current rows in traccar_positions_last: {$total}\n";
    echo "\nLatest rows:\n";

    $q = "SELECT id, device_id, device_uid, device_name, latitude, longitude, device_time, updated_at
          FROM traccar_positions_last
          ORDER BY updated_at DESC, id DESC
          LIMIT 20";
    $res = $mysqli->query($q);
    while ($row = $res->fetch_assoc()) {
        echo "- id={$row['id']}, did={$row['device_id']}, uid={$row['device_uid']}, name={$row['device_name']}, lat={$row['latitude']}, lon={$row['longitude']}, device_time={$row['device_time']}, updated_at={$row['updated_at']}\n";
    }

} catch (Throwable $e) {
    $mysqli->rollback();
    echo "[ERROR] " . $e->getMessage() . "\n";
    exit(1);
}
