<?php
// scripts/run_migrations.php
// Simple runner to execute a single SQL migration file.
// Usage: php run_migrations.php migrations/2026_add_traccar_positions.sql

if ($argc < 2) {
    echo "Usage: php run_migrations.php <path-to-sql-file>\n";
    exit(1);
}

$sqlFile = $argv[1];
if (!file_exists($sqlFile)) {
    echo "SQL file not found: $sqlFile\n";
    exit(1);
}

require_once __DIR__ . '/../config/db.php'; // expects $mysqli

$sql = file_get_contents($sqlFile);
if ($sql === false) {
    echo "Failed to read file: $sqlFile\n";
    exit(1);
}

// Execute as multi_query to allow multiple statements
if ($mysqli->multi_query($sql)) {
    do {
        if ($result = $mysqli->store_result()) {
            $result->free();
        }
    } while ($mysqli->more_results() && $mysqli->next_result());

    if ($mysqli->errno) {
        echo "Migration had warnings/errors: (" . $mysqli->errno . ") " . $mysqli->error . "\n";
        exit(1);
    }

    echo "Migration executed: $sqlFile\n";
    exit(0);
} else {
    echo "Migration failed: (" . $mysqli->errno . ") " . $mysqli->error . "\n";
    exit(1);
}
