<?php
/**
 * ajax/vehicle_efficiency.php
 * Mengembalikan efisiensi BBM (km/L) per kendaraan dari log_bahan_bakar.
 * Menggunakan cache (vehicle_efficiency_cache) dengan TTL 6 jam.
 *
 * GET ?kendaraan_id=N          → data satu kendaraan
 * GET ?mode=batch              → semua kendaraan (admin/operator only)
 * GET ?kendaraan_id=N&force=1  → paksa recompute, abaikan cache
 */

require_once dirname(__DIR__) . '/includes/auth.php';

header('Content-Type: application/json; charset=utf-8');

if (!is_logged_in()) {
    echo json_encode(['success' => false, 'message' => 'Tidak memiliki akses']);
    exit;
}

define('BBM_ANOMALY_THRESHOLD', 0.30);   // 30% deviasi
define('BBM_CACHE_TTL_HOURS',   6);      // jam sebelum cache dianggap stale
define('BBM_MIN_KM_PER_FILL',   1);      // filter: jarak minimal antar isian
define('BBM_MAX_KM_PER_FILL',   2000);   // filter: jarak maksimal (cegah error input)

// ── Helpers ─────────────────────────────────────────────────────────────────

/**
 * Ambil kendaraan_ids yang boleh diakses user ini.
 */
function get_allowed_vehicle_ids(): array {
    global $mysqli;
    if (can_operate()) {
        // admin/operator: semua kendaraan
        $res = $mysqli->query("SELECT id FROM kendaraan ORDER BY id");
        $ids = [];
        while ($r = $res->fetch_assoc()) $ids[] = (int)$r['id'];
        return $ids;
    }
    // user/driver: hanya kendaraan yang dapat diakses
    $uid = (int)($_SESSION['user_id'] ?? 0);
    $accessible = function_exists('get_accessible_vehicles') ? get_accessible_vehicles($uid) : [];
    return array_column($accessible, 'id');
}

/**
 * Hitung km/L dari log_bahan_bakar untuk satu kendaraan.
 * Mengambil semua baris dengan km_saat_isi & jumlah_liter, sort by tanggal_isi ASC,
 * lalu hitung selisih km antar isian berturut-turut.
 * Filter: km_diff antara BBM_MIN_KM_PER_FILL dan BBM_MAX_KM_PER_FILL.
 */
function compute_efficiency(int $kendaraan_id): array {
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
    if (!$stmt) {
        return ['avg_kml' => null, 'sample_count' => 0, 'total_km' => 0, 'total_liter' => 0,
                'computed_from' => null, 'computed_to' => null];
    }
    $stmt->bind_param('i', $kendaraan_id);
    $stmt->execute();
    $rows = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    $stmt->close();

    if (count($rows) < 2) {
        return ['avg_kml' => null, 'sample_count' => 0, 'total_km' => 0, 'total_liter' => 0,
                'computed_from' => $rows[0]['tanggal_isi'] ?? null,
                'computed_to'   => end($rows)['tanggal_isi'] ?? null];
    }

    $total_km    = 0.0;
    $total_liter = 0.0;
    $samples     = 0;

    for ($i = 1; $i < count($rows); $i++) {
        $km_curr  = (float)$rows[$i]['km_saat_isi'];
        $km_prev  = (float)$rows[$i - 1]['km_saat_isi'];
        $liter    = (float)$rows[$i]['jumlah_liter'];
        $km_diff  = $km_curr - $km_prev;

        if ($km_diff < BBM_MIN_KM_PER_FILL || $km_diff > BBM_MAX_KM_PER_FILL || $liter <= 0) {
            continue; // skip data yang tidak masuk akal
        }

        $total_km    += $km_diff;
        $total_liter += $liter;
        $samples++;
    }

    $avg_kml = ($total_liter > 0 && $samples > 0)
        ? round($total_km / $total_liter, 2)
        : null;

    return [
        'avg_kml'       => $avg_kml,
        'sample_count'  => $samples,
        'total_km'      => (int)round($total_km),
        'total_liter'   => round($total_liter, 2),
        'computed_from' => $rows[0]['tanggal_isi'],
        'computed_to'   => end($rows)['tanggal_isi'],
    ];
}

/**
 * Baca cache. Return null jika tidak ada atau sudah stale.
 */
function read_cache(int $kendaraan_id, bool $force): ?array {
    global $mysqli;
    if ($force) return null;

    // cek apakah tabel ada
    $tbl = $mysqli->query("SHOW TABLES LIKE 'vehicle_efficiency_cache'");
    $tbl_exists = $tbl && $tbl->num_rows > 0;
    if ($tbl) $tbl->free();
    if (!$tbl_exists) return null;

    $stmt = $mysqli->prepare(
        "SELECT * FROM vehicle_efficiency_cache WHERE kendaraan_id = ? LIMIT 1"
    );
    if (!$stmt) return null;
    $stmt->bind_param('i', $kendaraan_id);
    $stmt->execute();
    $row = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    if (!$row) return null;

    $age_hours = (time() - strtotime($row['last_computed'])) / 3600;
    if ($age_hours > BBM_CACHE_TTL_HOURS) return null;

    return $row;
}

/**
 * Tulis/update cache untuk satu kendaraan.
 */
function write_cache(int $kendaraan_id, array $data): void {
    global $mysqli;

    // pastikan tabel sudah ada
    $tbl = $mysqli->query("SHOW TABLES LIKE 'vehicle_efficiency_cache'");
    $tbl_exists = $tbl && $tbl->num_rows > 0;
    if ($tbl) $tbl->free();
    if (!$tbl_exists) return;

    $avg_kml      = $data['avg_kml'] ?? 0;   // NOT NULL column
    $total_km     = (int)($data['total_km'] ?? 0);
    $total_liter  = (float)($data['total_liter'] ?? 0);
    $sample_count = (int)($data['sample_count'] ?? 0);
    $from_date    = $data['computed_from'] ?? null;
    $to_date      = $data['computed_to'] ?? null;
    $now          = date('Y-m-d H:i:s');

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
    if (!$stmt) return;
    $stmt->bind_param('iddiisss',
        $kendaraan_id, $avg_kml, $total_km, $total_liter,
        $sample_count, $now, $from_date, $to_date
    );
    $stmt->execute();
    $stmt->close();
}

/**
 * Proses satu kendaraan: cache → compute → return payload.
 */
function get_efficiency_for_vehicle(int $kendaraan_id, bool $force): array {
    $cached = read_cache($kendaraan_id, $force);

    if ($cached) {
        return [
            'kendaraan_id' => $kendaraan_id,
            'avg_kml'      => $cached['avg_kml'] !== null ? (float)$cached['avg_kml'] : null,
            'sample_count' => (int)$cached['sample_count'],
            'total_km'     => (int)$cached['total_km'],
            'total_liter'  => (float)$cached['total_liter'],
            'source'       => 'cache',
            'last_computed' => $cached['last_computed'],
        ];
    }

    // compute segar
    $data = compute_efficiency($kendaraan_id);
    write_cache($kendaraan_id, $data);

    return [
        'kendaraan_id'  => $kendaraan_id,
        'avg_kml'       => $data['avg_kml'],
        'sample_count'  => $data['sample_count'],
        'total_km'      => $data['total_km'],
        'total_liter'   => $data['total_liter'],
        'source'        => 'computed',
        'last_computed' => date('Y-m-d H:i:s'),
    ];
}

// ── Main ─────────────────────────────────────────────────────────────────────

$force  = !empty($_GET['force']);
$mode   = $_GET['mode'] ?? 'single';

try {
    if ($mode === 'batch') {
        // Hanya admin/operator
        if (!can_operate()) {
            echo json_encode(['success' => false, 'message' => 'Akses ditolak']);
            exit;
        }

        $ids     = get_allowed_vehicle_ids();
        $results = [];
        foreach ($ids as $vid) {
            $results[] = get_efficiency_for_vehicle($vid, $force);
        }
        echo json_encode(['success' => true, 'data' => $results, 'count' => count($results)]);

    } else {
        // Single vehicle
        $kendaraan_id = (int)($_GET['kendaraan_id'] ?? 0);
        if ($kendaraan_id <= 0) {
            echo json_encode(['success' => false, 'message' => 'kendaraan_id tidak valid']);
            exit;
        }

        // Cek akses kendaraan
        if (!can_operate() && !can_access_vehicle($kendaraan_id)) {
            echo json_encode(['success' => false, 'message' => 'Tidak memiliki akses ke kendaraan ini']);
            exit;
        }

        $payload = get_efficiency_for_vehicle($kendaraan_id, $force);
        $payload['success'] = true;
        echo json_encode($payload);
    }

} catch (Throwable $e) {
    error_log('[vehicle_efficiency] ' . $e->getMessage());
    echo json_encode(['success' => false, 'message' => 'Server error', 'detail' => $e->getMessage()]);
}
