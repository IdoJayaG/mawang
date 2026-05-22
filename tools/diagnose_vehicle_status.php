<?php
// Diagnostic script: list kendaraan with status_peminjaman='Dipinjam' and show related records
require_once __DIR__ . '/../config/db.php';
$mysqli = $mysqli ?? $conn;

function table_exists($m, $t) {
    $t = $m->real_escape_string($t);
    $r = $m->query("SHOW TABLES LIKE '" . $t . "'");
    return $r && $r->num_rows > 0;
}

function fetch_rows_prepared($m, $sql, $param) {
    $stmt = $m->prepare($sql);
    if (!$stmt) return ['error' => $m->error];
    $stmt->bind_param('i', $param);
    if (!$stmt->execute()) { $err = $stmt->error; $stmt->close(); return ['error' => $err]; }
    $res = $stmt->get_result();
    $rows = $res ? $res->fetch_all(MYSQLI_ASSOC) : [];
    $stmt->close();
    return ['rows' => $rows];
}

echo "Diagnostic run: find kendaraan with status_peminjaman = 'Dipinjam'\n";

$res = $mysqli->query("SELECT id, COALESCE(NULLIF(TRIM(no_reg),''), NULLIF(TRIM(no_polisi),'')) AS label, status_peminjaman, status_kendaraan, updated_at FROM kendaraan WHERE TRIM(LOWER(COALESCE(status_peminjaman,''))) = 'dipinjam'");
if (!$res) {
    echo "ERROR querying kendaraan: " . $mysqli->error . "\n";
    exit(1);
}
$rows = $res->fetch_all(MYSQLI_ASSOC);
$cnt = count($rows);
echo "Found $cnt kendaraan with status 'Dipinjam'\n\n";
if ($cnt === 0) exit(0);

// Helper to print sample rows
function print_rows_sample($label, $arr) {
    if (isset($arr['error'])) {
        echo "  [$label] ERROR: " . $arr['error'] . "\n";
        return;
    }
    $rows = $arr['rows'];
    $c = count($rows);
    echo "  [$label] Count: $c\n";
    $sample = array_slice($rows, 0, 5);
    foreach ($sample as $r) {
        echo "    - ";
        $parts = [];
        foreach ($r as $k=>$v) { $parts[] = "$k=" . (is_null($v)?'NULL':$v); }
        echo implode(', ', $parts) . "\n";
    }
}

// Check for existence of related tables
$has_suratt = table_exists($mysqli, 'surat_tugas');
$has_pk = table_exists($mysqli, 'peminjaman_kendaraan');
$has_jk = table_exists($mysqli, 'jadwal_kendaraan');
$has_jp = table_exists($mysqli, 'jadwal_perawatan');
$has_rp = table_exists($mysqli, 'riwayat_perbaikan');
$has_pt = table_exists($mysqli, 'peminjaman_terjadwal');

echo "Tables present: surat_tugas=" . ($has_suratt? 'YES':'NO') . ", peminjaman_kendaraan=" . ($has_pk? 'YES':'NO') . ", jadwal_kendaraan=" . ($has_jk? 'YES':'NO') . ", jadwal_perawatan=" . ($has_jp? 'YES':'NO') . ", riwayat_perbaikan=" . ($has_rp? 'YES':'NO') . ", peminjaman_terjadwal=" . ($has_pt? 'YES':'NO') . "\n\n";

$vehicles = $rows;
foreach ($vehicles as $v) {
    $id = (int)$v['id'];
    echo "==== Vehicle ID: $id (" . ($v['label'] ?? 'n/a') . ") ====" . "\n";
    echo "  status_peminjaman: " . ($v['status_peminjaman'] ?? '') . ", status_kendaraan: " . ($v['status_kendaraan'] ?? '') . ", updated_at: " . ($v['updated_at'] ?? '') . "\n";

    // 1) surat_tugas active
    if ($has_suratt) {
        $sql = "SELECT * FROM surat_tugas WHERE kendaraan_id = ? AND TRIM(LOWER(COALESCE(status,''))) NOT IN ('dibatalkan','selesai') ORDER BY updated_at DESC LIMIT 50";
        $out = fetch_rows_prepared($mysqli, $sql, $id);
        print_rows_sample('surat_tugas_active', $out);
    } else {
        echo "  [surat_tugas_active] table missing\n";
    }

    // 2) peminjaman_kendaraan active
    if ($has_pk) {
        $sql2 = "SELECT * FROM peminjaman_kendaraan WHERE kendaraan_id = ? AND TRIM(LOWER(COALESCE(status,''))) NOT IN ('cancelled','selesai','dibatalkan','completed') ORDER BY updated_at DESC LIMIT 50";
        $out2 = fetch_rows_prepared($mysqli, $sql2, $id);
        print_rows_sample('peminjaman_kendaraan_active', $out2);
    } else {
        echo "  [peminjaman_kendaraan_active] table missing\n";
    }

    // 3) peminjaman_terjadwal
    if ($has_pt) {
        $sqlpt = "SELECT * FROM peminjaman_terjadwal WHERE kendaraan_id = ? ORDER BY created_at DESC LIMIT 50";
        $outpt = fetch_rows_prepared($mysqli, $sqlpt, $id);
        print_rows_sample('peminjaman_terjadwal', $outpt);
    }

    // 4) jadwal_kendaraan
    if ($has_jk) {
        $sqljk = "SELECT * FROM jadwal_kendaraan WHERE kendaraan_id = ? ORDER BY tanggal_mulai DESC LIMIT 50";
        $outjk = fetch_rows_prepared($mysqli, $sqljk, $id);
        print_rows_sample('jadwal_kendaraan', $outjk);
    }

    // 5) jadwal_perawatan
    if ($has_jp) {
        $sqljp = "SELECT * FROM jadwal_perawatan WHERE kendaraan_id = ? AND TRIM(LOWER(COALESCE(status,''))) NOT IN ('selesai','dibatalkan') ORDER BY updated_at DESC LIMIT 50";
        $outjp = fetch_rows_prepared($mysqli, $sqljp, $id);
        print_rows_sample('jadwal_perawatan_active', $outjp);
    }

    // 6) riwayat_perbaikan
    if ($has_rp) {
        $sqlrp = "SELECT * FROM riwayat_perbaikan WHERE kendaraan_id = ? AND TRIM(LOWER(COALESCE(status,''))) NOT IN ('selesai','dibatalkan') ORDER BY tanggal_perbaikan DESC LIMIT 50";
        $outrp = fetch_rows_prepared($mysqli, $sqlrp, $id);
        print_rows_sample('riwayat_perbaikan_active', $outrp);
    }

    // 7) last run_status_transitions log entries mentioning kendaraan.id
    $logpath = __DIR__ . '/../logs/run_status_transitions_debug.log';
    if (file_exists($logpath)) {
        $lines = file($logpath, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
        $found = [];
        foreach (array_reverse($lines) as $ln) {
            if (strpos($ln, "kendaraan.id $id") !== false || strpos($ln, "kendaraan.id '" . $id . "'") !== false || strpos($ln, "Set kendaraan.id $id") !== false || strpos($ln, "Set kendaraan.id '" . $id . "'") !== false) {
                $found[] = $ln;
                if (count($found) >= 20) break;
            }
        }
        echo "  [run_status_transitions_log] Recent matches: " . count($found) . "\n";
        foreach ($found as $f) echo "    $f\n";
    }

    echo "\n";
}

echo "Diagnostic complete.\n";
