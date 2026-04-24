<?php
require_once __DIR__ . '/../config/db.php';

mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);

function q(mysqli $db, string $sql) {
    return $db->query($sql);
}

function tableExists(mysqli $db, string $table): bool {
    $safe = $db->real_escape_string($table);
    $res = $db->query("SHOW TABLES LIKE '{$safe}'");
    return $res && $res->num_rows > 0;
}

function countFromTemp(mysqli $db, string $tempTable): int {
    $res = $db->query("SELECT COUNT(*) AS c FROM {$tempTable}");
    $row = $res ? $res->fetch_assoc() : ['c' => 0];
    return (int)($row['c'] ?? 0);
}

try {
    $dbNameRes = q($conn, 'SELECT DATABASE() AS dbn');
    $dbName = $dbNameRes->fetch_assoc()['dbn'] ?? 'randis';

    $conn->begin_transaction();

    // Build keep/delete set for kendaraan
    q($conn, "DROP TEMPORARY TABLE IF EXISTS tmp_keep_kendaraan");
    q($conn, "CREATE TEMPORARY TABLE tmp_keep_kendaraan (id INT PRIMARY KEY)");
    q($conn, "INSERT INTO tmp_keep_kendaraan (id)
              SELECT id FROM kendaraan
              WHERE locator IS NOT NULL AND TRIM(locator) <> ''");

    q($conn, "DROP TEMPORARY TABLE IF EXISTS tmp_del_kendaraan");
    q($conn, "CREATE TEMPORARY TABLE tmp_del_kendaraan (id INT PRIMARY KEY)");
    q($conn, "INSERT INTO tmp_del_kendaraan (id)
              SELECT k.id FROM kendaraan k
              LEFT JOIN tmp_keep_kendaraan kk ON kk.id = k.id
              WHERE kk.id IS NULL");

    // Build keep/delete set for pengguna based on role ADMIN/PIMPINAN only
    q($conn, "DROP TEMPORARY TABLE IF EXISTS tmp_keep_pengguna");
    q($conn, "CREATE TEMPORARY TABLE tmp_keep_pengguna (id INT PRIMARY KEY)");
    q($conn, "INSERT INTO tmp_keep_pengguna (id)
              SELECT DISTINCT p.id
              FROM pengguna p
              JOIN user_account ua ON ua.pengguna_id = p.id
              JOIN role r ON r.id = ua.role_id
              WHERE UPPER(TRIM(r.kode_role)) IN ('ADMIN', 'PIMPINAN')");

    q($conn, "DROP TEMPORARY TABLE IF EXISTS tmp_del_pengguna");
    q($conn, "CREATE TEMPORARY TABLE tmp_del_pengguna (id INT PRIMARY KEY)");
    q($conn, "INSERT INTO tmp_del_pengguna (id)
              SELECT p.id
              FROM pengguna p
              LEFT JOIN tmp_keep_pengguna kp ON kp.id = p.id
              WHERE kp.id IS NULL");

    $keepK = countFromTemp($conn, 'tmp_keep_kendaraan');
    $delK = countFromTemp($conn, 'tmp_del_kendaraan');
    $keepP = countFromTemp($conn, 'tmp_keep_pengguna');
    $delP = countFromTemp($conn, 'tmp_del_pengguna');

    echo "=== CLEANUP PLAN ===\n";
    echo "Kendaraan keep(with uid): {$keepK}\n";
    echo "Kendaraan delete(without uid): {$delK}\n";
    echo "Pengguna keep(role admin/pimpinan): {$keepP}\n";
    echo "Pengguna delete(other roles): {$delP}\n\n";

    // Delete rows related to kendaraan to-be-deleted from all FK tables
    $stmtFkK = $conn->prepare("SELECT TABLE_NAME, COLUMN_NAME
        FROM information_schema.KEY_COLUMN_USAGE
        WHERE REFERENCED_TABLE_SCHEMA = ?
          AND REFERENCED_TABLE_NAME = 'kendaraan'
        ORDER BY TABLE_NAME, COLUMN_NAME");
    $stmtFkK->bind_param('s', $dbName);
    $stmtFkK->execute();
    $fkK = $stmtFkK->get_result();

    echo "=== DELETE RELASI KENDARAAN ===\n";
    while ($fkK && ($row = $fkK->fetch_assoc())) {
        $table = $row['TABLE_NAME'];
        $col = $row['COLUMN_NAME'];
        if (!tableExists($conn, $table)) {
            continue;
        }
        $sqlDel = "DELETE t FROM `{$table}` t JOIN tmp_del_kendaraan d ON t.`{$col}` = d.id";
        q($conn, $sqlDel);
        $aff = $conn->affected_rows;
        if ($aff > 0) {
            echo "- {$table}.{$col}: {$aff}\n";
        }
    }
    $stmtFkK->close();

    // Delete kendaraan itself
    q($conn, "DELETE k FROM kendaraan k JOIN tmp_del_kendaraan d ON k.id = d.id");
    echo "- kendaraan: " . $conn->affected_rows . "\n\n";

    // For kendaraan kept, nullify pengguna_id if pengguna will be deleted
    if (tableExists($conn, 'kendaraan')) {
        q($conn, "UPDATE kendaraan k
                  JOIN tmp_del_pengguna dp ON k.pengguna_id = dp.id
                  SET k.pengguna_id = NULL");
        if ($conn->affected_rows > 0) {
            echo "- kendaraan.pengguna_id di-null-kan: {$conn->affected_rows}\n";
        }
    }

    // Delete rows related to pengguna to-be-deleted from all FK tables
    $stmtFkP = $conn->prepare("SELECT TABLE_NAME, COLUMN_NAME
        FROM information_schema.KEY_COLUMN_USAGE
        WHERE REFERENCED_TABLE_SCHEMA = ?
          AND REFERENCED_TABLE_NAME = 'pengguna'
        ORDER BY TABLE_NAME, COLUMN_NAME");
    $stmtFkP->bind_param('s', $dbName);
    $stmtFkP->execute();
    $fkP = $stmtFkP->get_result();

    echo "=== DELETE RELASI PENGGUNA ===\n";
    while ($fkP && ($row = $fkP->fetch_assoc())) {
        $table = $row['TABLE_NAME'];
        $col = $row['COLUMN_NAME'];

        // kendaraan handled by null update above
        if ($table === 'kendaraan' && $col === 'pengguna_id') {
            continue;
        }
        if (!tableExists($conn, $table)) {
            continue;
        }

        $sqlDel = "DELETE t FROM `{$table}` t JOIN tmp_del_pengguna d ON t.`{$col}` = d.id";
        q($conn, $sqlDel);
        $aff = $conn->affected_rows;
        if ($aff > 0) {
            echo "- {$table}.{$col}: {$aff}\n";
        }
    }
    $stmtFkP->close();

    // Finally delete pengguna records
    q($conn, "DELETE p FROM pengguna p JOIN tmp_del_pengguna d ON p.id = d.id");
    echo "- pengguna: " . $conn->affected_rows . "\n\n";

    $conn->commit();

    echo "=== DONE ===\n";

    // Verification summary
    $r1 = q($conn, "SELECT COUNT(*) AS c FROM kendaraan")->fetch_assoc();
    $r2 = q($conn, "SELECT COUNT(*) AS c FROM kendaraan WHERE locator IS NOT NULL AND TRIM(locator) <> ''")->fetch_assoc();
    $r3 = q($conn, "SELECT COUNT(*) AS c FROM pengguna")->fetch_assoc();
    $r4 = q($conn, "SELECT COUNT(*) AS c
        FROM pengguna p
        JOIN user_account ua ON ua.pengguna_id = p.id
        JOIN role r ON r.id = ua.role_id
        WHERE UPPER(TRIM(r.kode_role)) IN ('ADMIN', 'PIMPINAN')")->fetch_assoc();
    $r5 = q($conn, "SELECT COUNT(*) AS c FROM traccar_positions_last")->fetch_assoc();

    echo "kendaraan_total=" . (int)$r1['c'] . "\n";
    echo "kendaraan_with_uid=" . (int)$r2['c'] . "\n";
    echo "pengguna_total=" . (int)$r3['c'] . "\n";
    echo "pengguna_admin_pimpinan=" . (int)$r4['c'] . "\n";
    echo "traccar_positions_last_total=" . (int)$r5['c'] . "\n";

    echo "\n=== traccar_positions_last ===\n";
    $res = q($conn, "SELECT id, device_id, device_uid, device_name, latitude, longitude, updated_at FROM traccar_positions_last ORDER BY updated_at DESC, id DESC");
    while ($res && ($row = $res->fetch_assoc())) {
        echo "- id={$row['id']}, did={$row['device_id']}, uid={$row['device_uid']}, name={$row['device_name']}, lat={$row['latitude']}, lon={$row['longitude']}, updated_at={$row['updated_at']}\n";
    }

} catch (Throwable $e) {
    if ($conn->errno === 0) {
        // no-op
    }
    $conn->rollback();
    echo "[ERROR] " . $e->getMessage() . "\n";
    exit(1);
}
