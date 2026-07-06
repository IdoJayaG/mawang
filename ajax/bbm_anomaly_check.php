<?php
/**
 * ajax/bbm_anomaly_check.php
 * Hitung/recalculate anomali BBM untuk satu atau semua surat_tugas.
 *
 * POST ?action=check_trip   body: { surat_id: N }
 * POST ?action=recalc_all             (admin only)
 * GET  ?action=status&surat_id=N      → status anomali trip ini
 */

require_once dirname(__DIR__) . '/includes/auth.php';

header('Content-Type: application/json; charset=utf-8');

if (!is_logged_in()) {
    echo json_encode(['success' => false, 'message' => 'Tidak memiliki akses']);
    exit;
}

define('BBM_ANOMALY_THRESHOLD', 0.30);

// ── Helper ───────────────────────────────────────────────────────────────────

/**
 * Hitung anomali untuk satu surat_tugas.
 * Return: ['flag' => 0|1, 'pct' => float, 'reasons' => string[]]
 */
function compute_trip_anomaly(int $surat_id): array {
    global $mysqli;

    $stmt = $mysqli->prepare(
        "SELECT kendaraan_id, estimasi_bbm, bbm_terpakai, tanggal_berangkat, tanggal_kembali
         FROM surat_tugas WHERE id = ? LIMIT 1"
    );
    if (!$stmt) return ['flag' => 0, 'pct' => null, 'reasons' => []];
    $stmt->bind_param('i', $surat_id);
    $stmt->execute();
    $trip = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    if (!$trip || empty($trip['bbm_terpakai']) || empty($trip['estimasi_bbm'])) {
        return ['flag' => 0, 'pct' => null, 'reasons' => []];
    }

    $terpakai    = (float)$trip['bbm_terpakai'];
    $estimasi    = (float)$trip['estimasi_bbm'];
    $kend_id     = (int)$trip['kendaraan_id'];
    $tgl_ber     = $trip['tanggal_berangkat'];
    $tgl_kem     = $trip['tanggal_kembali'] ?: $trip['tanggal_berangkat'];
    $flag        = 0;
    $reasons     = [];
    $max_dev_pct = 0.0;

    // Rule 1: deviasi estimasi vs terpakai
    $dev1 = abs($terpakai - $estimasi) / $estimasi;
    if ($dev1 > BBM_ANOMALY_THRESHOLD) {
        $flag = 1;
        $reasons[] = sprintf(
            'Terpakai %.1fL vs estimasi %.1fL (deviasi %.0f%%)',
            $terpakai, $estimasi, $dev1 * 100
        );
    }
    $max_dev_pct = max($max_dev_pct, $dev1);

    // Rule 2: deviasi vs log_bahan_bakar fills selama periode trip
    if ($kend_id && $tgl_ber) {
        $stmt2 = $mysqli->prepare(
            "SELECT COALESCE(SUM(jumlah_liter),0) AS fill_sum, COUNT(*) AS fill_count
             FROM log_bahan_bakar
             WHERE kendaraan_id = ? AND DATE(tanggal_isi) BETWEEN ? AND ?"
        );
        if ($stmt2) {
            $stmt2->bind_param('iss', $kend_id, $tgl_ber, $tgl_kem);
            $stmt2->execute();
            $fill_row  = $stmt2->get_result()->fetch_assoc();
            $stmt2->close();
            $fill_sum  = (float)($fill_row['fill_sum']   ?? 0);
            $fill_cnt  = (int)  ($fill_row['fill_count'] ?? 0);

            if ($fill_sum > 0 && $fill_cnt > 0) {
                $dev2 = abs($terpakai - $fill_sum) / $fill_sum;
                if ($dev2 > BBM_ANOMALY_THRESHOLD) {
                    $flag = 1;
                    $reasons[] = sprintf(
                        'Terpakai %.1fL vs total isian BBM %.1fL (%d kali isi, deviasi %.0f%%)',
                        $terpakai, $fill_sum, $fill_cnt, $dev2 * 100
                    );
                }
                $max_dev_pct = max($max_dev_pct, $dev2);
            }
        }
    }

    return [
        'flag'    => $flag,
        'pct'     => round($max_dev_pct * 100, 2),
        'reasons' => $reasons,
    ];
}

/**
 * Tulis anomali ke DB (jika kolom sudah ada).
 */
function save_trip_anomaly(int $surat_id, int $flag, ?float $pct): bool {
    global $mysqli;

    $has_col = function_exists('db_table_columns')
        && in_array('bbm_anomali', (array)db_table_columns('surat_tugas'), true);
    if (!$has_col) return false;

    $stmt = $mysqli->prepare(
        "UPDATE surat_tugas SET bbm_anomali = ?, bbm_anomali_pct = ? WHERE id = ?"
    );
    if (!$stmt) return false;
    $stmt->bind_param('idi', $flag, $pct, $surat_id);
    $ok = $stmt->execute();
    $stmt->close();
    return $ok;
}

// ── Main ─────────────────────────────────────────────────────────────────────

$action = $_GET['action'] ?? ($_POST['action'] ?? 'check_trip');

try {
    // ── GET status ────────────────────────────────────────────────────────────
    if ($action === 'status') {
        $surat_id = (int)($_GET['surat_id'] ?? 0);
        if ($surat_id <= 0) {
            echo json_encode(['success' => false, 'message' => 'surat_id tidak valid']);
            exit;
        }
        $stmt = $mysqli->prepare(
            "SELECT bbm_anomali, bbm_anomali_pct, estimasi_bbm, bbm_terpakai FROM surat_tugas WHERE id = ? LIMIT 1"
        );
        $stmt->bind_param('i', $surat_id);
        $stmt->execute();
        $row = $stmt->get_result()->fetch_assoc();
        $stmt->close();

        echo json_encode([
            'success'       => true,
            'surat_id'      => $surat_id,
            'bbm_anomali'   => isset($row['bbm_anomali']) ? (int)$row['bbm_anomali'] : null,
            'bbm_anomali_pct' => isset($row['bbm_anomali_pct']) ? (float)$row['bbm_anomali_pct'] : null,
            'estimasi_bbm'  => (float)($row['estimasi_bbm'] ?? 0),
            'bbm_terpakai'  => (float)($row['bbm_terpakai'] ?? 0),
        ]);
        exit;
    }

    // ── Recalc semua (admin only) ─────────────────────────────────────────────
    if ($action === 'recalc_all') {
        if (!can_admin()) {
            echo json_encode(['success' => false, 'message' => 'Hanya admin yang dapat melakukan recalculate semua']);
            exit;
        }

        $res = $mysqli->query(
            "SELECT id FROM surat_tugas
             WHERE bbm_terpakai IS NOT NULL AND bbm_terpakai > 0
               AND estimasi_bbm IS NOT NULL AND estimasi_bbm > 0
             ORDER BY id"
        );
        $updated = $skipped = 0;
        while ($row = $res->fetch_assoc()) {
            $sid    = (int)$row['id'];
            $result = compute_trip_anomaly($sid);
            if (save_trip_anomaly($sid, $result['flag'], $result['pct'])) {
                $updated++;
            } else {
                $skipped++;
            }
        }
        echo json_encode([
            'success'  => true,
            'updated'  => $updated,
            'skipped'  => $skipped,
            'message'  => "Recalculate selesai: {$updated} diperbarui, {$skipped} dilewati",
        ]);
        exit;
    }

    // ── Check satu trip ───────────────────────────────────────────────────────
    $body     = json_decode(file_get_contents('php://input'), true) ?? [];
    $surat_id = (int)($_POST['surat_id'] ?? $body['surat_id'] ?? $_GET['surat_id'] ?? 0);

    if ($surat_id <= 0) {
        echo json_encode(['success' => false, 'message' => 'surat_id tidak valid']);
        exit;
    }

    // Cek akses
    if (!can_operate()) {
        $stmt_chk = $mysqli->prepare("SELECT pengguna_id FROM surat_tugas WHERE id = ? LIMIT 1");
        $stmt_chk->bind_param('i', $surat_id);
        $stmt_chk->execute();
        $row_chk = $stmt_chk->get_result()->fetch_assoc();
        $stmt_chk->close();
        $peng_id = (int)($row_chk['pengguna_id'] ?? 0);
        $uid     = (int)($_SESSION['user_id'] ?? 0);
        if ($peng_id !== $uid) {
            echo json_encode(['success' => false, 'message' => 'Akses ditolak']);
            exit;
        }
    }

    $result = compute_trip_anomaly($surat_id);
    save_trip_anomaly($surat_id, $result['flag'], $result['pct']);

    echo json_encode([
        'success'     => true,
        'surat_id'    => $surat_id,
        'flag'        => $result['flag'],
        'pct'         => $result['pct'],
        'reasons'     => $result['reasons'],
        'is_anomali'  => $result['flag'] === 1,
    ]);

} catch (Throwable $e) {
    error_log('[bbm_anomaly_check] ' . $e->getMessage());
    echo json_encode(['success' => false, 'message' => 'Server error', 'detail' => $e->getMessage()]);
}
