<?php
// scripts/traccar_poller.php
// Usage: php traccar_poller.php [--daemon] [--interval=10]
// Replace $TRACCAR_API_BASE, $TRACCAR_USER, $TRACCAR_PASS with your Traccar settings.

require_once __DIR__ . '/../config/db.php';

// Read credentials from environment if set, otherwise use defaults.
// Set these in your environment or replace defaults below.
$TRACCAR_API_BASE = getenv('TRACCAR_API_BASE') ?: 'http://localhost:8082/api'; // root API path
$TRACCAR_USER = getenv('TRACCAR_USER') ?: 'admin@gmail.com';
$TRACCAR_PASS = getenv('TRACCAR_PASS') ?: 'admin';
$TRACCAR_API_BASE_ALTERNATES = getenv('TRACCAR_API_BASE_ALTERNATES') ?: 'http://10.239.171.72:8082/api,http://192.168.1.109:8082/api';

fwrite(STDOUT, "Traccar poller config: API_BASE={$TRACCAR_API_BASE}, USER={$TRACCAR_USER}\n");

$opts = getopt('', ['daemon', 'interval::']);
$daemon = isset($opts['daemon']);
$interval = isset($opts['interval']) ? (int)$opts['interval'] : 10; // seconds
if ($interval < 5) $interval = 5;

function fetchFromTraccar($url, $user, $pass) {
    $ch = curl_init($url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_USERPWD, "$user:$pass");
    curl_setopt($ch, CURLOPT_HTTPAUTH, CURLAUTH_BASIC);
    curl_setopt($ch, CURLOPT_TIMEOUT, 20);
    // follow redirects and request JSON explicitly
    curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
    curl_setopt($ch, CURLOPT_HTTPHEADER, ['Accept: application/json']);
    $res = curl_exec($ch);
    $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $err = curl_error($ch);
    curl_close($ch);

    if ($res === false) return ['ok' => false, 'error' => $err, 'code' => $code];
    $json = json_decode($res, true);
    if ($json === null) return ['ok' => false, 'error' => 'invalid_json', 'raw' => $res, 'code' => $code];
    return ['ok' => true, 'data' => $json, 'code' => $code];
}

function fetchDevicesMap($apiBase, $user, $pass) {
    $base = rtrim($apiBase, '/');
    $tryUrls = [];
    if (preg_match('#/api$#', $base)) {
        $tryUrls[] = $base . '/devices';
    } else {
        $tryUrls[] = $base . '/devices';
        $tryUrls[] = $base . '/api/devices';
    }

    foreach ($tryUrls as $url) {
        $res = fetchFromTraccar($url, $user, $pass);
        if (!$res['ok'] || !is_array($res['data'])) {
            continue;
        }
        $map = [];
        foreach ($res['data'] as $d) {
            $id = isset($d['id']) ? (int)$d['id'] : 0;
            if ($id <= 0) continue;
            $map[$id] = [
                'uniqueId' => $d['uniqueId'] ?? null,
                'name' => $d['name'] ?? null,
            ];
        }
        return $map;
    }

    return [];
}

function upsertPosition($mysqli, $item) {
    $deviceId = $item['deviceId'] ?? $item['id'] ?? null;
    $deviceUid = $item['uniqueId'] ?? $item['deviceUid'] ?? ($item['device'] ?? null);
    $deviceName = $item['deviceName'] ?? $item['name'] ?? null;
    $lat = $item['latitude'] ?? $item['lat'] ?? null;
    $lon = $item['longitude'] ?? $item['lon'] ?? null;
    $speed = $item['speed'] ?? null;
    $course = $item['course'] ?? null;
    $accuracy = $item['accuracy'] ?? null;
    $deviceTime = null;
    if (!empty($item['deviceTime'])) $deviceTime = date('Y-m-d H:i:s', strtotime($item['deviceTime']));
    if (!empty($item['positionTime'])) $deviceTime = date('Y-m-d H:i:s', strtotime($item['positionTime']));

    $extra_json = json_encode($item, JSON_UNESCAPED_UNICODE);

    $stmt = $mysqli->prepare("INSERT INTO traccar_positions_last
      (device_id, device_uid, device_name, latitude, longitude, speed, course, accuracy, device_time, extra)
      VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
      ON DUPLICATE KEY UPDATE
        device_uid = VALUES(device_uid),
        device_name = VALUES(device_name),
        latitude = VALUES(latitude),
        longitude = VALUES(longitude),
        speed = VALUES(speed),
        course = VALUES(course),
        accuracy = VALUES(accuracy),
        device_time = VALUES(device_time),
        extra = VALUES(extra),
        updated_at = CURRENT_TIMESTAMP
    ");
    if (!$stmt) {
        fwrite(STDERR, "DB prepare failed: " . $mysqli->error . "\n");
        return false;
    }
    $device_id_param = $deviceId !== null ? (int)$deviceId : null;
    $device_uid_param = $deviceUid !== null ? $deviceUid : null;
    $device_name_param = $deviceName !== null ? $deviceName : null;
    $lat_param = $lat !== null ? (float)$lat : null;
    $lon_param = $lon !== null ? (float)$lon : null;
    $speed_param = isset($speed) ? (float)$speed : null;
    $course_param = isset($course) ? (float)$course : null;
    $accuracy_param = isset($accuracy) ? (float)$accuracy : null;
    $device_time_param = $deviceTime !== null ? $deviceTime : null;

    $stmt->bind_param('issdddddss', $device_id_param, $device_uid_param, $device_name_param, $lat_param, $lon_param, $speed_param, $course_param, $accuracy_param, $device_time_param, $extra_json);
    $ok = $stmt->execute();
    if (!$ok) {
        fwrite(STDERR, "DB execute failed: " . $stmt->error . "\n");
    }
    $stmt->close();
    return $ok;
}

function tryFetchAndSave($mysqli, $apiBase, $user, $pass) {
    // Normalize base and build candidate endpoints.
    $base = rtrim($apiBase, '/');
    $tryUrls = [];
    // If user provided a full endpoint already, try to use it and variants
    if (preg_match('#/reports/lastPositions$#', $base)) {
        $tryUrls[] = $base;
        $tryUrls[] = preg_replace('#/reports/lastPositions$#', '/positions', $base);
    } elseif (preg_match('#/positions$#', $base)) {
        $tryUrls[] = $base;
        $tryUrls[] = preg_replace('#/positions$#', '/reports/lastPositions', $base);
    } else {
        // Common case: base ends with /api
        $tryUrls[] = $base . '/reports/lastPositions';
        $tryUrls[] = $base . '/positions';
        // Also try with /api/positions path if base already points to /api
        $tryUrls[] = $base . '/api/reports/lastPositions';
        $tryUrls[] = $base . '/api/positions';
    }

    $devicesMap = fetchDevicesMap($apiBase, $user, $pass);

    foreach ($tryUrls as $url) {
        $res = fetchFromTraccar($url, $user, $pass);
        if (!$res['ok']) {
            fwrite(STDOUT, "Fetch failed for $url: " . ($res['error'] ?? 'unknown') . " (HTTP " . ($res['code'] ?? '??') . ")\n");
            if (isset($res['raw'])) {
                $raw = $res['raw'];
                $excerpt = strlen($raw) > 800 ? substr($raw, 0, 800) . "...(truncated)" : $raw;
                fwrite(STDOUT, "Raw response snippet:\n" . $excerpt . "\n---\n");
            }
            continue;
        }
        $data = $res['data'];
        if (!is_array($data)) {
            fwrite(STDOUT, "Unexpected data from $url\n");
            continue;
        }
        // If /positions returns many historical positions, attempt to extract only last positions per device
        if (preg_match('#/positions$#', $url)) {
            // Build last by deviceId
            $byDevice = [];
            foreach ($data as $d) {
                $did = $d['deviceId'] ?? $d['id'] ?? null;
                $time = strtotime($d['deviceTime'] ?? ($d['serverTime'] ?? null) ?: '0');
                if (!isset($byDevice[$did]) || $time > $byDevice[$did]['time']) {
                    $byDevice[$did] = ['time' => $time, 'data' => $d];
                }
            }
            $items = array_map(function($v){return $v['data'];}, $byDevice);
        } else {
            $items = $data;
        }

        foreach ($items as $item) {
            $did = isset($item['deviceId']) ? (int)$item['deviceId'] : (isset($item['id']) ? (int)$item['id'] : 0);
            if ($did > 0 && isset($devicesMap[$did])) {
                if (empty($item['uniqueId']) && !empty($devicesMap[$did]['uniqueId'])) {
                    $item['uniqueId'] = $devicesMap[$did]['uniqueId'];
                }
                if (empty($item['deviceName']) && !empty($devicesMap[$did]['name'])) {
                    $item['deviceName'] = $devicesMap[$did]['name'];
                }
            }
            upsertPosition($mysqli, $item);
        }
        return true;
    }
    return false;
}

function tryFetchAndSaveFromBases($mysqli, $primaryBase, $altCsv, $user, $pass) {
    $bases = [];
    $bases[] = rtrim((string)$primaryBase, '/');
    foreach (explode(',', (string)$altCsv) as $b) {
        $b = trim($b);
        if ($b !== '') {
            $bases[] = rtrim($b, '/');
        }
    }
    $bases = array_values(array_unique($bases));

    foreach ($bases as $base) {
        fwrite(STDOUT, "Trying API base: {$base}\n");
        $ok = tryFetchAndSave($mysqli, $base, $user, $pass);
        if ($ok) {
            return true;
        }
    }
    return false;
}

// run once or as daemon
if ($daemon) {
    fwrite(STDOUT, "Starting traccar poller in daemon mode (interval={$interval}s)\n");
    while (true) {
        tryFetchAndSaveFromBases($mysqli, $TRACCAR_API_BASE, $TRACCAR_API_BASE_ALTERNATES, $TRACCAR_USER, $TRACCAR_PASS);
        sleep($interval);
    }
} else {
    $ok = tryFetchAndSaveFromBases($mysqli, $TRACCAR_API_BASE, $TRACCAR_API_BASE_ALTERNATES, $TRACCAR_USER, $TRACCAR_PASS);
    if ($ok) fwrite(STDOUT, "Fetch and save completed\n");
    else fwrite(STDERR, "Fetch failed\n");
}
