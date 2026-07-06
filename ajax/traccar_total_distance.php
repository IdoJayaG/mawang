<?php
require_once dirname(__DIR__) . '/includes/auth.php';

header('Content-Type: application/json');

if (!is_logged_in()) {
    echo json_encode(['success' => false, 'message' => 'Tidak memiliki akses']);
    exit;
}

$vehicleId = (int)($_GET['kendaraan_id'] ?? 0);

if ($vehicleId <= 0) {
    echo json_encode(['success' => false, 'message' => 'kendaraan_id tidak valid']);
    exit;
}

if (!can_operate() && !can_access_vehicle($vehicleId)) {
    echo json_encode(['success' => false, 'message' => 'Tidak memiliki akses ke kendaraan ini']);
    exit;
}

function traccar_fetch_json_simple($url, $user, $pass) {
    $ch = curl_init($url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_USERPWD, "$user:$pass");
    curl_setopt($ch, CURLOPT_HTTPAUTH, CURLAUTH_BASIC);
    curl_setopt($ch, CURLOPT_TIMEOUT, 60);
    curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
    curl_setopt($ch, CURLOPT_HTTPHEADER, ['Accept: application/json']);
    $raw = curl_exec($ch);
    $code = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $err = curl_error($ch);
    curl_close($ch);

    if ($raw === false) {
        return ['ok' => false, 'code' => $code, 'error' => $err ?: 'curl_error'];
    }
    $json = json_decode($raw, true);
    if (!is_array($json)) {
        return ['ok' => false, 'code' => $code, 'error' => 'invalid_json'];
    }
    return ['ok' => true, 'code' => $code, 'data' => $json];
}

function haversine_km_local($lat1, $lon1, $lat2, $lon2) {
    $earth = 6371.0;
    $dLat = deg2rad($lat2 - $lat1);
    $dLon = deg2rad($lon2 - $lon1);
    $a = sin($dLat / 2) * sin($dLat / 2)
        + cos(deg2rad($lat1)) * cos(deg2rad($lat2))
        * sin($dLon / 2) * sin($dLon / 2);
    $c = 2 * atan2(sqrt($a), sqrt(1 - $a));
    return $earth * $c;
}

try {
    $stmt = $mysqli->prepare("SELECT id, no_polisi, no_reg, locator FROM kendaraan WHERE id = ? LIMIT 1");
    $stmt->bind_param('i', $vehicleId);
    $stmt->execute();
    $vehicle = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    if (!$vehicle) {
        echo json_encode(['success' => false, 'message' => 'Kendaraan tidak ditemukan']);
        exit;
    }

    $locator = trim((string)($vehicle['locator'] ?? ''));
    if ($locator === '') {
        echo json_encode(['success' => false, 'message' => 'Locator kendaraan belum diisi']);
        exit;
    }

    // Find device_id from traccar_positions_last
    $deviceId = null;
    $stmt = $mysqli->prepare("SELECT p.device_id, p.device_uid, p.device_name
        FROM traccar_positions_last p
        WHERE (
            CONVERT(TRIM(p.device_uid) USING utf8mb4) COLLATE utf8mb4_unicode_ci = CONVERT(TRIM(?) USING utf8mb4) COLLATE utf8mb4_unicode_ci
            OR TRIM(CAST(p.device_id AS CHAR)) = TRIM(?)
            OR CONVERT(TRIM(p.device_name) USING utf8mb4) COLLATE utf8mb4_unicode_ci = CONVERT(TRIM(?) USING utf8mb4) COLLATE utf8mb4_unicode_ci
        )
        ORDER BY p.updated_at DESC
        LIMIT 1");
    $stmt->bind_param('sss', $locator, $locator, $locator);
    $stmt->execute();
    $matched = $stmt->get_result()->fetch_assoc();
    $stmt->close();
    if ($matched) {
        $deviceId = isset($matched['device_id']) ? (int)$matched['device_id'] : null;
    }

    $traccarUser = getenv('TRACCAR_USER') ?: 'admin@gmail.com';
    $traccarPass = getenv('TRACCAR_PASS') ?: 'admin';
    $bases = [];
    $primary = getenv('TRACCAR_API_BASE') ?: 'http://localhost:8082/api';
    $alts = getenv('TRACCAR_API_BASE_ALTERNATES') ?: '';
    $bases[] = rtrim($primary, '/');
    foreach (explode(',', $alts) as $base) { $base = trim($base); if ($base !== '') $bases[] = rtrim($base, '/'); }

    if ($deviceId === null || $deviceId <= 0) {
        foreach ($bases as $base) {
            $res = traccar_fetch_json_simple($base . '/devices', $traccarUser, $traccarPass);
            if (!$res['ok']) continue;
            foreach ($res['data'] as $device) {
                $id = isset($device['id']) ? (int)$device['id'] : 0;
                $uid = isset($device['uniqueId']) ? trim((string)$device['uniqueId']) : '';
                $name = isset($device['name']) ? trim((string)$device['name']) : '';
                if ($id <= 0) continue;
                if (strcasecmp($locator, (string)$id) === 0 || ($uid !== '' && strcasecmp($locator, $uid) === 0) || ($name !== '' && strcasecmp($locator, $name) === 0)) {
                    $deviceId = $id; break 2;
                }
            }
        }
    }

    if ($deviceId === null || $deviceId <= 0) {
        echo json_encode(['success' => false, 'message' => 'Device Traccar tidak ditemukan untuk locator ini']);
        exit;
    }

    // ── Path 1 (since_log mode): Traccar distance since last BBM log ──────────
    if (!empty($_GET['since_log'])) {
        $since_dt_label = null;
        $fromIso = (new DateTime('2000-01-01 00:00:00'))->format(DateTime::ATOM);

        $stmt_last = $mysqli->prepare("SELECT MAX(created_at) AS last_at FROM log_bahan_bakar WHERE kendaraan_id = ?");
        if ($stmt_last) {
            $stmt_last->bind_param('i', $vehicleId);
            $stmt_last->execute();
            $row_last = $stmt_last->get_result()->fetch_assoc();
            $stmt_last->close();
            if (!empty($row_last['last_at'])) {
                $dt_from = new DateTime($row_last['last_at'], new DateTimeZone('Asia/Jakarta'));
                $dt_from->setTimezone(new DateTimeZone('UTC'));
                $fromIso = $dt_from->format(DateTime::ATOM);
                $since_dt_label = $row_last['last_at'];
            }
        }
        $toIso = (new DateTime('now'))->format(DateTime::ATOM);

        // Try /reports/summary first (fast aggregate)
        foreach ($bases as $base) {
            $url = $base . '/reports/summary?deviceId=' . rawurlencode((string)$deviceId)
                . '&from=' . rawurlencode($fromIso)
                . '&to=' . rawurlencode($toIso);
            $res = traccar_fetch_json_simple($url, $traccarUser, $traccarPass);
            if ($res['ok'] && !empty($res['data']) && isset($res['data'][0]['distance'])) {
                $dist_km = round((float)$res['data'][0]['distance'] / 1000, 2);
                echo json_encode(['success' => true, 'kendaraan_id' => $vehicleId, 'distance_km' => $dist_km, 'since_datetime' => $since_dt_label, 'source' => 'summary']);
                exit;
            }
        }

        // Fallback: compute from /reports/route points
        $route = null; $lastError = null;
        foreach ($bases as $base) {
            $url = $base . '/reports/route?deviceId=' . rawurlencode((string)$deviceId)
                . '&from=' . rawurlencode($fromIso)
                . '&to=' . rawurlencode($toIso);
            $res = traccar_fetch_json_simple($url, $traccarUser, $traccarPass);
            if ($res['ok']) { $route = $res['data']; break; }
            $lastError = $res['error'] ?? 'request_failed';
        }
        if (!is_array($route)) {
            echo json_encode(['success' => false, 'message' => 'Gagal mengambil route dari Traccar', 'error' => $lastError]);
            exit;
        }
        $distance = 0.0; $lastLat = null; $lastLon = null;
        foreach ($route as $item) {
            $lat = isset($item['latitude']) ? (float)$item['latitude'] : null;
            $lon = isset($item['longitude']) ? (float)$item['longitude'] : null;
            if (!is_numeric($lat) || !is_numeric($lon)) continue;
            if ($lastLat !== null) $distance += haversine_km_local($lastLat, $lastLon, $lat, $lon);
            $lastLat = $lat; $lastLon = $lon;
        }
        echo json_encode(['success' => true, 'kendaraan_id' => $vehicleId, 'distance_km' => round($distance, 2), 'since_datetime' => $since_dt_label, 'source' => 'route']);
        exit;
    }

    // ── Path 2 (list view): Total lifetime distance ────────────────────────────
    // Step 1: read totalDistance from local traccar_positions_last.extra JSON (instant)
    $stmt_td = $mysqli->prepare(
        "SELECT CAST(JSON_UNQUOTE(JSON_EXTRACT(extra, '$.attributes.totalDistance')) AS DECIMAL(20,2)) AS total_m
         FROM traccar_positions_last WHERE device_id = ? LIMIT 1"
    );
    if ($stmt_td) {
        $stmt_td->bind_param('i', $deviceId);
        $stmt_td->execute();
        $td = $stmt_td->get_result()->fetch_assoc();
        $stmt_td->close();
        $total_m = isset($td['total_m']) ? (float)$td['total_m'] : 0;
        if ($total_m > 0) {
            echo json_encode([
                'success'      => true,
                'kendaraan_id' => $vehicleId,
                'distance_km'  => round($total_m / 1000, 2),
                'source'       => 'odometer',
            ]);
            exit;
        }
    }

    // Step 2: try Traccar /reports/summary (fast server-side aggregate)
    $fromIso = (new DateTime('2000-01-01 00:00:00'))->format(DateTime::ATOM);
    $toIso   = (new DateTime('now'))->format(DateTime::ATOM);
    foreach ($bases as $base) {
        $url = $base . '/reports/summary?deviceId=' . rawurlencode((string)$deviceId)
            . '&from=' . rawurlencode($fromIso)
            . '&to=' . rawurlencode($toIso);
        $res = traccar_fetch_json_simple($url, $traccarUser, $traccarPass);
        if ($res['ok'] && !empty($res['data']) && isset($res['data'][0]['distance'])) {
            $dist_km = round((float)$res['data'][0]['distance'] / 1000, 2);
            echo json_encode(['success' => true, 'kendaraan_id' => $vehicleId, 'distance_km' => $dist_km, 'source' => 'summary']);
            exit;
        }
    }

    // Step 3: last resort — compute from /reports/route (slow, may time out for large ranges)
    $route = null; $lastError = null;
    foreach ($bases as $base) {
        $url = $base . '/reports/route?deviceId=' . rawurlencode((string)$deviceId)
            . '&from=' . rawurlencode($fromIso)
            . '&to=' . rawurlencode($toIso);
        $res = traccar_fetch_json_simple($url, $traccarUser, $traccarPass);
        if ($res['ok']) { $route = $res['data']; break; }
        $lastError = $res['error'] ?? 'request_failed';
    }
    if (!is_array($route)) {
        echo json_encode(['success' => false, 'message' => 'Gagal mengambil data jarak dari Traccar', 'error' => $lastError]);
        exit;
    }
    $distance = 0.0; $lastLat = null; $lastLon = null;
    foreach ($route as $item) {
        $lat = isset($item['latitude']) ? (float)$item['latitude'] : null;
        $lon = isset($item['longitude']) ? (float)$item['longitude'] : null;
        if (!is_numeric($lat) || !is_numeric($lon)) continue;
        if ($lastLat !== null) $distance += haversine_km_local($lastLat, $lastLon, $lat, $lon);
        $lastLat = $lat; $lastLon = $lon;
    }
    echo json_encode(['success' => true, 'kendaraan_id' => $vehicleId, 'distance_km' => round($distance, 2), 'source' => 'route']);

} catch (Throwable $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Terjadi kesalahan server', 'error' => $e->getMessage()]);
}
