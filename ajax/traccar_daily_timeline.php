<?php
require_once dirname(__DIR__) . '/includes/auth.php';

header('Content-Type: application/json');

if (!is_logged_in()) {
    echo json_encode(['success' => false, 'message' => 'Tidak memiliki akses']);
    exit;
}

$vehicleId = (int)($_GET['vehicle_id'] ?? 0);
$date = trim((string)($_GET['date'] ?? ''));

if ($vehicleId <= 0) {
    echo json_encode(['success' => false, 'message' => 'vehicle_id tidak valid']);
    exit;
}

if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $date)) {
    echo json_encode(['success' => false, 'message' => 'Format tanggal harus YYYY-MM-DD']);
    exit;
}

if (!can_operate() && !can_access_vehicle($vehicleId)) {
    echo json_encode(['success' => false, 'message' => 'Tidak memiliki akses ke kendaraan ini']);
    exit;
}

function traccar_fetch_json($url, $user, $pass) {
    $ch = curl_init($url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_USERPWD, "$user:$pass");
    curl_setopt($ch, CURLOPT_HTTPAUTH, CURLAUTH_BASIC);
    curl_setopt($ch, CURLOPT_TIMEOUT, 25);
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

function traccar_bases() {
    $primary = getenv('TRACCAR_API_BASE') ?: 'http://localhost:8082/api';
    $alts = getenv('TRACCAR_API_BASE_ALTERNATES') ?: 'http://10.239.171.72:8082/api,http://192.168.1.109:8082/api';

    $bases = [rtrim($primary, '/')];
    foreach (explode(',', $alts) as $base) {
        $base = trim($base);
        if ($base !== '') {
            $bases[] = rtrim($base, '/');
        }
    }
    return array_values(array_unique($bases));
}

function haversine_km($lat1, $lon1, $lat2, $lon2) {
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
    $stmt = $mysqli->prepare("SELECT id, no_polisi, no_reg, merk, tipe, locator FROM kendaraan WHERE id = ? LIMIT 1");
    if (!$stmt) {
        throw new Exception('Gagal menyiapkan query kendaraan');
    }
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

    $deviceId = null;
    $deviceUid = null;
    $deviceName = null;

    $stmt = $mysqli->prepare("SELECT p.device_id, p.device_uid, p.device_name
        FROM traccar_positions_last p
        WHERE (
            CONVERT(TRIM(p.device_uid) USING utf8mb4) COLLATE utf8mb4_unicode_ci = CONVERT(TRIM(?) USING utf8mb4) COLLATE utf8mb4_unicode_ci
            OR TRIM(CAST(p.device_id AS CHAR)) = TRIM(?)
            OR CONVERT(TRIM(p.device_name) USING utf8mb4) COLLATE utf8mb4_unicode_ci = CONVERT(TRIM(?) USING utf8mb4) COLLATE utf8mb4_unicode_ci
        )
        ORDER BY p.updated_at DESC
        LIMIT 1");
    if ($stmt) {
        $stmt->bind_param('sss', $locator, $locator, $locator);
        $stmt->execute();
        $matched = $stmt->get_result()->fetch_assoc();
        $stmt->close();

        if ($matched) {
            $deviceId = isset($matched['device_id']) ? (int)$matched['device_id'] : null;
            $deviceUid = $matched['device_uid'] ?? null;
            $deviceName = $matched['device_name'] ?? null;
        }
    }

    $traccarUser = getenv('TRACCAR_USER') ?: 'admin@gmail.com';
    $traccarPass = getenv('TRACCAR_PASS') ?: 'admin';
    $bases = traccar_bases();

    if ($deviceId === null || $deviceId <= 0) {
        foreach ($bases as $base) {
            $res = traccar_fetch_json($base . '/devices', $traccarUser, $traccarPass);
            if (!$res['ok']) {
                continue;
            }
            foreach ($res['data'] as $device) {
                $id = isset($device['id']) ? (int)$device['id'] : 0;
                $uid = isset($device['uniqueId']) ? trim((string)$device['uniqueId']) : '';
                $name = isset($device['name']) ? trim((string)$device['name']) : '';
                if ($id <= 0) {
                    continue;
                }
                if (
                    strcasecmp($locator, (string)$id) === 0
                    || ($uid !== '' && strcasecmp($locator, $uid) === 0)
                    || ($name !== '' && strcasecmp($locator, $name) === 0)
                ) {
                    $deviceId = $id;
                    $deviceUid = $uid !== '' ? $uid : $deviceUid;
                    $deviceName = $name !== '' ? $name : $deviceName;
                    break 2;
                }
            }
        }
    }

    if ($deviceId === null || $deviceId <= 0) {
        echo json_encode(['success' => false, 'message' => 'Device Traccar tidak ditemukan untuk locator ini']);
        exit;
    }

    $fromIso = (new DateTime($date . ' 00:00:00'))->format(DateTime::ATOM);
    $toIso = (new DateTime($date . ' 23:59:59'))->format(DateTime::ATOM);

    $route = null;
    $lastError = null;
    foreach ($bases as $base) {
        $url = $base . '/reports/route?deviceId=' . rawurlencode((string)$deviceId)
            . '&from=' . rawurlencode($fromIso)
            . '&to=' . rawurlencode($toIso);
        $res = traccar_fetch_json($url, $traccarUser, $traccarPass);
        if ($res['ok']) {
            $route = $res['data'];
            break;
        }
        $lastError = $res['error'] ?? 'request_failed';
    }

    if (!is_array($route)) {
        echo json_encode(['success' => false, 'message' => 'Gagal mengambil route harian dari Traccar', 'error' => $lastError]);
        exit;
    }

    $points = [];
    $distance = 0.0;
    $lastLat = null;
    $lastLon = null;

    foreach ($route as $item) {
        $lat = isset($item['latitude']) ? (float)$item['latitude'] : null;
        $lon = isset($item['longitude']) ? (float)$item['longitude'] : null;
        if (!is_numeric($lat) || !is_numeric($lon)) {
            continue;
        }

        if ($lastLat !== null && $lastLon !== null) {
            $distance += haversine_km($lastLat, $lastLon, $lat, $lon);
        }
        $lastLat = $lat;
        $lastLon = $lon;

        $points[] = [
            'lat' => $lat,
            'lon' => $lon,
            'speed' => isset($item['speed']) ? (float)$item['speed'] : 0.0,
            'course' => isset($item['course']) ? (float)$item['course'] : 0.0,
            'accuracy' => isset($item['accuracy']) ? (float)$item['accuracy'] : null,
            'device_time' => $item['deviceTime'] ?? null,
            'server_time' => $item['serverTime'] ?? null,
            'address' => $item['address'] ?? null,
        ];
    }

    $label = $vehicle['no_polisi'] ?: ($vehicle['no_reg'] ?: trim(($vehicle['merk'] ?? '') . ' ' . ($vehicle['tipe'] ?? '')));

    echo json_encode([
        'success' => true,
        'vehicle' => [
            'id' => (int)$vehicle['id'],
            'label' => trim((string)$label),
            'no_polisi' => $vehicle['no_polisi'] ?? null,
            'no_reg' => $vehicle['no_reg'] ?? null,
            'locator' => $locator,
        ],
        'device' => [
            'id' => (int)$deviceId,
            'uid' => $deviceUid,
            'name' => $deviceName,
        ],
        'date' => $date,
        'summary' => [
            'point_count' => count($points),
            'distance_km' => round($distance, 2),
        ],
        'points' => $points,
    ]);
} catch (Throwable $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Terjadi kesalahan server', 'error' => $e->getMessage()]);
}
