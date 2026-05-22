<?php
// Fix script: set given kendaraan ids to Tersedia/Operasional and print results
require_once __DIR__ . '/../config/db.php';
$mysqli = $mysqli ?? $conn;
$ids = [116, 131, 146];
foreach ($ids as $id) {
    $id = (int)$id;
    $q = "UPDATE kendaraan SET status_peminjaman = 'Tersedia', status_kendaraan = 'Operasional', updated_at = NOW() WHERE id = $id";
    if ($mysqli->query($q) === false) {
        echo "ERROR updating id=$id: " . $mysqli->error . PHP_EOL;
    } else {
        echo "Updated id=$id affected_rows=" . $mysqli->affected_rows . PHP_EOL;
    }
}

$res = $mysqli->query("SELECT id, COALESCE(NULLIF(TRIM(no_reg),''), NULLIF(TRIM(no_polisi),'')) AS label, status_peminjaman, status_kendaraan, updated_at FROM kendaraan WHERE id IN (116,131,146) ORDER BY id");
if ($res) {
    while ($r = $res->fetch_assoc()) {
        echo json_encode($r) . PHP_EOL;
    }
} else {
    echo "ERROR selecting updated rows: " . $mysqli->error . PHP_EOL;
}
