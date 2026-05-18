<?php
require_once __DIR__ . '/../config.php';
$mysqli = isset($conn) ? $conn : (isset($mysqli) ? $mysqli : null);
if (!($mysqli instanceof mysqli)) {
    fwrite(STDERR, "Database connection not available\n");
    exit(1);
}

$res = $mysqli->query("SHOW COLUMNS FROM kendaraan");
if (!$res) {
    fwrite(STDERR, "Failed to read kendaraan columns: " . $mysqli->error . "\n");
    exit(1);
}
$cols = [];
while ($r = $res->fetch_assoc()) $cols[] = $r['Field'];
printf("Found columns: %s\n", implode(',', $cols));

function run_update($mysqli, $sql, $label) {
    $mysqli->query($sql);
    if ($mysqli->errno) {
        printf("%s: ERROR (%d): %s\n", $label, $mysqli->errno, $mysqli->error);
        return -1;
    }
    $affected = $mysqli->affected_rows;
    printf("%s: %d rows affected\n", $label, $affected);
    return $affected;
}

$tot = 0;

if (in_array('warna', $cols, true)) {
    $sql = "UPDATE kendaraan SET warna = 'Hitam' WHERE COALESCE(TRIM(warna),'') = '' OR warna IS NULL";
    $tot += max(0, run_update($mysqli, $sql, 'warna'));
} else {
    echo "kolom 'warna' tidak ada\n";
}

if (in_array('merk', $cols, true)) {
    $sql = "UPDATE kendaraan SET merk = 'Hino' WHERE COALESCE(TRIM(merk),'') = '' OR merk IS NULL";
    $tot += max(0, run_update($mysqli, $sql, 'merk'));
} else {
    echo "kolom 'merk' tidak ada\n";
}

if (in_array('tipe', $cols, true)) {
    $sql = "UPDATE kendaraan SET tipe = 'Bus' WHERE COALESCE(TRIM(tipe),'') = '' OR tipe IS NULL";
    $tot += max(0, run_update($mysqli, $sql, 'tipe'));
} else {
    echo "kolom 'tipe' tidak ada\n";
}

if (in_array('tahun_pembuatan', $cols, true)) {
    // Use RAND(id) to vary the year per row deterministically by id
    $sql = "UPDATE kendaraan SET tahun_pembuatan = FLOOR(2000 + RAND(id) * 17) WHERE tahun_pembuatan IS NULL OR tahun_pembuatan = 0 OR COALESCE(TRIM(CAST(tahun_pembuatan AS CHAR)),'') = ''";
    $tot += max(0, run_update($mysqli, $sql, 'tahun_pembuatan'));
} else {
    echo "kolom 'tahun_pembuatan' tidak ada\n";
}

if (in_array('satker', $cols, true)) {
    $sql = "UPDATE kendaraan SET satker = 'Setjen Kemhan'";
    $tot += max(0, run_update($mysqli, $sql, 'satker'));
} else {
    echo "kolom 'satker' tidak ada\n";
}

printf("All updates complete. Sum of affected rows reported: %d\n", $tot);

// Show a few sample rows after update
$sample = $mysqli->query("SELECT id, no_reg, merk, tipe, warna, tahun_pembuatan, satker FROM kendaraan ORDER BY id DESC LIMIT 10");
if ($sample) {
    printf("\nLast 10 kendaraan (post-update):\n");
    while ($r = $sample->fetch_assoc()) {
        printf("ID=%d | no_reg=%s | merk=%s | tipe=%s | warna=%s | tahun=%s | satker=%s\n",
            $r['id'], $r['no_reg'] ?? '-', $r['merk'] ?? '-', $r['tipe'] ?? '-', $r['warna'] ?? '-', $r['tahun_pembuatan'] ?? '-', $r['satker'] ?? '-');
    }
}

exit(0);
