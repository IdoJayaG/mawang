<?php
// Run the cleanup migration to remove unused legacy DB objects safely
require_once __DIR__ . '/../config.php';

header('Content-Type: text/plain; charset=utf-8');
echo "Starting legacy schema cleanup...\n\n";

$file = __DIR__ . '/../migrations/2025-09-04_cleanup_legacy_schema.sql';
if (!file_exists($file)) {
    echo "Migration file not found: {$file}\n";
    exit(1);
}

$sql = file_get_contents($file);
if ($sql === false) {
    echo "Failed to read migration file.\n";
    exit(1);
}

// Split on delimiters cautiously; here we only use standard statements without custom DELIMITER
$statements = array_filter(array_map('trim', preg_split('/;\s*\n/', $sql)));

$mysqli->begin_transaction();
try {
    foreach ($statements as $stmt) {
        if ($stmt === '' || strpos($stmt, '--') === 0) {
            continue; // skip comments/empties
        }
        // Avoid echoing sensitive info; print short preview
        $preview = substr(preg_replace('/\s+/', ' ', $stmt), 0, 120);
        echo "> Executing: {$preview}...\n";
        if (!$mysqli->query($stmt)) {
            throw new Exception('Error executing statement: ' . $mysqli->error);
        }
    }
    $mysqli->commit();
    echo "\nCleanup completed successfully.\n";
} catch (Throwable $e) {
    $mysqli->rollback();
    echo "\nCleanup failed and was rolled back.\nReason: " . $e->getMessage() . "\n";
    exit(1);
}

// Quick post-checks
function table_exists($mysqli, $table) {
    $table = $mysqli->real_escape_string($table);
    $res = $mysqli->query("SHOW TABLES LIKE '{$table}'");
    return $res && $res->num_rows > 0;
}

echo "\nPost-checks:\n";
foreach (['user_account_old','pengguna_old','jadwal_perawatan_backup','kendaraan_old','system_settings'] as $t) {
    echo sprintf(" - %-28s %s\n", $t, table_exists($mysqli, $t) ? 'STILL PRESENT' : 'OK (dropped)');
}

// Check approval_by FK destination (should point to pengguna if column exists)
$check = $mysqli->query("SELECT REFERENCED_TABLE_NAME AS ref_table FROM information_schema.KEY_COLUMN_USAGE WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'peminjaman_kendaraan' AND COLUMN_NAME = 'approval_by' AND REFERENCED_TABLE_NAME IS NOT NULL LIMIT 1");
$row = $check ? $check->fetch_assoc() : null;
$ref = $row['ref_table'] ?? null;
echo " - peminjaman_kendaraan.approval_by FK target: " . ($ref ?: 'NONE') . "\n";

// If user_account_old still present, attempt explicit drop now
if (table_exists($mysqli, 'user_account_old')) {
    echo "\nAttempting explicit drop of user_account_old...\n";
    $mysqli->query('SET FOREIGN_KEY_CHECKS = 0');
    if ($mysqli->query('DROP TABLE IF EXISTS `user_account_old`')) {
        echo " - user_account_old dropped.\n";
    } else {
        echo " - Failed to drop user_account_old: " . $mysqli->error . "\n";
    }
    $mysqli->query('SET FOREIGN_KEY_CHECKS = 1');
}

echo "\nDone.\n";
$fixRes = $mysqli->query("SELECT CONSTRAINT_NAME, REFERENCED_TABLE_NAME FROM information_schema.KEY_COLUMN_USAGE WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'peminjaman_kendaraan' AND COLUMN_NAME = 'approval_by' AND (REFERENCED_TABLE_NAME = 'user_account_old' OR REFERENCED_TABLE_NAME IS NULL)");
if ($fixRes && $fixRes->num_rows > 0) {
    echo "\nFixing approval_by FK on peminjaman_kendaraan...\n";
    while ($fk = $fixRes->fetch_assoc()) {
        $name = $fk['CONSTRAINT_NAME'];
        if ($name) {
            $sqlDrop = "ALTER TABLE `peminjaman_kendaraan` DROP FOREIGN KEY `{$name}`";
            if ($mysqli->query($sqlDrop)) {
                echo " - Dropped FK {$name}.\n";
            } else {
                echo " - Failed to drop FK {$name}: " . $mysqli->error . "\n";
            }
        }
    }
    // Re-add sane FK to pengguna if column exists
    $colExists = $mysqli->query("SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'peminjaman_kendaraan' AND COLUMN_NAME = 'approval_by'");
    $penggunaExists = table_exists($mysqli, 'pengguna');
    if ($colExists && $colExists->num_rows > 0 && $penggunaExists) {
        // sanitize orphaned values
        $mysqli->query("UPDATE peminjaman_kendaraan pk LEFT JOIN pengguna p ON pk.approval_by = p.id SET pk.approval_by = NULL WHERE pk.approval_by IS NOT NULL AND p.id IS NULL");
        $sqlAdd = "ALTER TABLE `peminjaman_kendaraan` ADD CONSTRAINT `fk_peminjaman_approval_pengguna` FOREIGN KEY (`approval_by`) REFERENCES `pengguna`(`id`) ON DELETE SET NULL ON UPDATE CASCADE";
        if ($mysqli->query($sqlAdd)) {
            echo " - Added FK fk_peminjaman_approval_pengguna -> pengguna(id).\n";
        } else {
            echo " - Failed to add new FK: " . $mysqli->error . "\n";
        }
    }
}

?>
