<?php
require_once __DIR__ . '/../config.php';

$mysqli = isset($conn) ? $conn : (isset($mysqli) ? $mysqli : null);
if (!($mysqli instanceof mysqli)) {
    fwrite(STDERR, "Database connection not available\n");
    exit(1);
}

$sql = "UPDATE kendaraan SET merk = 'Hino' WHERE TRIM(LOWER(merk)) = 'isuzu'";
if ($mysqli->query($sql)) {
    printf("Affected rows: %d\n", $mysqli->affected_rows);
} else {
    printf("Error: (%d) %s\n", $mysqli->errno, $mysqli->error);
}

exit(0);
