<?php
/**
 * cron/refresh_efficiency_cache.php
 * CLI script — isi/refresh vehicle_efficiency_cache untuk semua kendaraan.
 *
 * Jalankan sekali setelah migration, atau via Windows Task Scheduler tiap malam:
 *   php C:\xampp\htdocs\mawang\cron\refresh_efficiency_cache.php
 *
 * Opsional argumen:
 *   --force          : paksa recompute meski cache masih fresh
 *   --kendaraan=N    : hanya proses kendaraan dengan id=N
 *   --dry-run        : hitung tapi tidak simpan ke DB
 */

if (PHP_SAPI !== 'cli') {
    http_response_code(403);
    exit('Hanya bisa dijalankan dari command line.');
}

// Parse argumen
$args     = getopt('', ['force', 'dry-run', 'kendaraan:']);
$force    = isset($args['force']);
$dry_run  = isset($args['dry-run']);
$only_id  = isset($args['kendaraan']) ? (int)$args['kendaraan'] : null;

// Bootstrap
define('BBM_MIN_KM_PER_FILL', 1);
define('BBM_MAX_KM_PER_FILL', 2000);

require_once dirname(__DIR__) . '/config.php';

// ── Helpers ──────────────────────────────────────────────────────────────────

function cli_log(string $msg): void {
    echo '[' . date('Y-m-d H:i:s') . '] ' . $msg . PHP_EOL;
}

/**
 * Cek apakah tabel vehicle_efficiency_cache sudah ada.
 */
function cache_table_exists(): bool {
    global $mysqli;
    $res = $mysqli->query("SHOW TABLES LIKE 'vehicle_efficiency_cache'");
    return $res && $res->num_rows > 0;
}

/**
 * Hitung efisiensi dari log_bahan_bakar (logika sama dengan vehicle_efficiency.php).
 */
function compute_vehicle_efficiency(int $kendaraan_id): array {
    global $mysqli;

    $stmt = $mysqli->prepare(
        "SELECT km_saat_isi, jumlah_liter, tanggal_isi
         FROM log_bahan_bakar
         WHERE kendaraan_id = ?
           AND km_saat_isi IS NOT NULL
           AND km_saat_isi > 0
           AND jumlah_liter > 0
         ORDER BY tanggal_isi ASC, id ASC"
    );
    if (!$stmt) return ['avg_kml' => null, 'sample_count' => 0, 'total_km' => 0,
                        'total_liter' => 0, 'from' => null, 'to' => null];

    $stmt->bind_param('i', $kendaraan_id);
    $stmt->execute();
    $rows = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    $stmt->close();

    if (count($rows) < 2) {
        return ['avg_kml' => null, 'sample_count' => 0, 'total_km' => 0, 'total_liter' => 0,
                'from' => $rows[0]['tanggal_isi'] ?? null, 'to' => end($rows)['tanggal_isi'] ?? null];
    }

    $total_km = $total_liter = 0.0;
    $samples  = 0;

    for ($i = 1; $i < count($rows); $i++) {
        $km_diff = (float)$rows[$i]['km_saat_isi'] - (float)$rows[$i - 1]['km_saat_isi'];
        $liter   = (float)$rows[$i]['jumlah_liter'];
        if ($km_diff < BBM_MIN_KM_PER_FILL || $km_diff > BBM_MAX_KM_PER_FILL || $liter <= 0) continue;
        $total_km    += $km_diff;
        $total_liter += $liter;
        $samples++;
    }

    return [
        'avg_kml'      => ($total_liter > 0 && $samples > 0) ? round($total_km / $total_liter, 2) : null,
        'sample_count' => $samples,
        'total_km'     => (int)round($total_km),
        'total_liter'  => round($total_liter, 2),
        'from'         => $rows[0]['tanggal_isi'],
        'to'           => end($rows)['tanggal_isi'],
    ];
}

/**
 * Upsert satu baris ke vehicle_efficiency_cache.
 */
function upsert_cache(int $kendaraan_id, array $data): bool {
    global $mysqli;
    $now = date('Y-m-d H:i:s');
    $stmt = $mysqli->prepare(
        "INSERT INTO vehicle_efficiency_cache
             (kendaraan_id, avg_kml, total_km, total_liter, sample_count,
              last_computed, computed_from, computed_to)
         VALUES (?, ?, ?, ?, ?, ?, ?, ?)
         ON DUPLICATE KEY UPDATE
             avg_kml       = VALUES(avg_kml),
             total_km      = VALUES(total_km),
             total_liter   = VALUES(total_liter),
             sample_count  = VALUES(sample_count),
             last_computed = VALUES(last_computed),
             computed_from = VALUES(computed_from),
             computed_to   = VALUES(computed_to)"
    );
    if (!$stmt) return false;
    $avg_kml      = $data['avg_kml'] ?? 0;   // NOT NULL column — pakai 0 jika belum cukup data
    $total_km     = $data['total_km'];
    $total_liter  = $data['total_liter'];
    $sample_count = $data['sample_count'];
    $from_date    = $data['from'];
    $to_date      = $data['to'];
    $stmt->bind_param('iddiisss',
        $kendaraan_id, $avg_kml, $total_km, $total_liter,
        $sample_count, $now, $from_date, $to_date
    );
    $ok = $stmt->execute();
    $stmt->close();
    return $ok;
}

// ── Main ─────────────────────────────────────────────────────────────────────

cli_log('=== refresh_efficiency_cache.php START ===');
cli_log('force=' . ($force ? 'yes' : 'no') . ', dry-run=' . ($dry_run ? 'yes' : 'no')
    . ', kendaraan=' . ($only_id ?? 'all'));

if (!cache_table_exists()) {
    cli_log('ERROR: Tabel vehicle_efficiency_cache belum ada. Jalankan migration terlebih dahulu.');
    exit(1);
}

// Ambil daftar kendaraan
if ($only_id) {
    $res = $mysqli->query("SELECT id, no_polisi, no_reg FROM kendaraan WHERE id = " . $only_id . " LIMIT 1");
} else {
    $res = $mysqli->query("SELECT id, no_polisi, no_reg FROM kendaraan ORDER BY id");
}

if (!$res || $res->num_rows === 0) {
    cli_log('Tidak ada kendaraan ditemukan.');
    exit(0);
}

$processed = $skipped = $errors = 0;

while ($kend = $res->fetch_assoc()) {
    $kid   = (int)$kend['id'];
    $label = trim(($kend['no_reg'] ?? $kend['no_polisi']) ?: 'id=' . $kid);

    // Cek apakah cache masih fresh (skip jika tidak force)
    if (!$force) {
        $chk = $mysqli->query(
            "SELECT last_computed FROM vehicle_efficiency_cache WHERE kendaraan_id = {$kid} LIMIT 1"
        );
        if ($chk && $row = $chk->fetch_assoc()) {
            $age = (time() - strtotime($row['last_computed'])) / 3600;
            if ($age < 6) {
                cli_log("  SKIP [{$label}] cache masih fresh (" . number_format($age, 1) . " jam)");
                $skipped++;
                continue;
            }
        }
    }

    $data = compute_vehicle_efficiency($kid);

    $kml_str = $data['avg_kml'] !== null
        ? number_format($data['avg_kml'], 2) . ' km/L (' . $data['sample_count'] . ' sampel)'
        : 'tidak cukup data (' . $data['sample_count'] . ' sampel)';

    if ($dry_run) {
        cli_log("  DRY [{$label}] {$kml_str}");
        $processed++;
        continue;
    }

    if (upsert_cache($kid, $data)) {
        cli_log("  OK  [{$label}] {$kml_str}");
        $processed++;
    } else {
        cli_log("  ERR [{$label}] gagal upsert: " . $mysqli->error);
        $errors++;
    }
}

cli_log('=== SELESAI: processed=' . $processed . ', skipped=' . $skipped . ', errors=' . $errors . ' ===');
exit($errors > 0 ? 1 : 0);
