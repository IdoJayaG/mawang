<?php
// ajax/traccar_positions.php
require_once __DIR__ . '/../config/db.php';
header('Content-Type: application/json');

function table_exists_mysqli(mysqli $db, $name) {
    $safe = $db->real_escape_string($name);
    $res = $db->query("SHOW TABLES LIKE '{$safe}'");
    return $res && $res->num_rows > 0;
}

function table_columns_mysqli(mysqli $db, $name) {
    $cols = [];
    $safe = $db->real_escape_string($name);
    $res = $db->query("SHOW COLUMNS FROM `{$safe}`");
    if ($res) {
        while ($r = $res->fetch_assoc()) {
            $cols[] = $r['Field'];
        }
    }
    return $cols;
}

function traccar_positions_fetch_json($url, $user, $pass) {
    $ch = curl_init($url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_USERPWD, "$user:$pass");
    curl_setopt($ch, CURLOPT_HTTPAUTH, CURLAUTH_BASIC);
    curl_setopt($ch, CURLOPT_TIMEOUT, 12);
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

function traccar_positions_fetch_devices_map($base, $user, $pass) {
    $base = rtrim((string)$base, '/');
    $tryUrls = [];
    if (preg_match('#/api$#', $base)) {
        $tryUrls[] = $base . '/devices';
    } else {
        $tryUrls[] = $base . '/devices';
        $tryUrls[] = $base . '/api/devices';
    }

    foreach ($tryUrls as $url) {
        $res = traccar_positions_fetch_json($url, $user, $pass);
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

function traccar_positions_upsert(mysqli $db, array $item, array $devicesMap) {
    $deviceId = $item['deviceId'] ?? $item['id'] ?? null;
    if ($deviceId !== null && isset($devicesMap[(int)$deviceId])) {
        if (empty($item['uniqueId']) && !empty($devicesMap[(int)$deviceId]['uniqueId'])) {
            $item['uniqueId'] = $devicesMap[(int)$deviceId]['uniqueId'];
        }
        if (empty($item['deviceName']) && !empty($devicesMap[(int)$deviceId]['name'])) {
            $item['deviceName'] = $devicesMap[(int)$deviceId]['name'];
        }
    }

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

    if (!is_numeric($lat) || !is_numeric($lon)) {
        return false;
    }

    $extra_json = json_encode($item, JSON_UNESCAPED_UNICODE);
    $stmt = $db->prepare("INSERT INTO traccar_positions_last
      (device_id, device_uid, device_name, latitude, longitude, speed, course, accuracy, device_time, extra)
      VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
      ON DUPLICATE KEY UPDATE
        device_id = VALUES(device_id),
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
        return false;
    }

    $device_id_param = $deviceId !== null ? (int)$deviceId : null;
    $device_uid_param = $deviceUid !== null ? $deviceUid : null;
    $device_name_param = $deviceName !== null ? $deviceName : null;
    $lat_param = (float)$lat;
    $lon_param = (float)$lon;
    $speed_param = isset($speed) ? (float)$speed : null;
    $course_param = isset($course) ? (float)$course : null;
    $accuracy_param = isset($accuracy) ? (float)$accuracy : null;
    $device_time_param = $deviceTime !== null ? $deviceTime : null;

    $stmt->bind_param(
        'issdddddss',
        $device_id_param,
        $device_uid_param,
        $device_name_param,
        $lat_param,
        $lon_param,
        $speed_param,
        $course_param,
        $accuracy_param,
        $device_time_param,
        $extra_json
    );
    $ok = $stmt->execute();
    $stmt->close();
    return $ok;
}

function traccar_positions_try_fetch_and_save(mysqli $db, $base, $user, $pass) {
    $base = rtrim((string)$base, '/');
    $tryUrls = [];
    if (preg_match('#/reports/lastPositions$#', $base)) {
        $tryUrls[] = $base;
        $tryUrls[] = preg_replace('#/reports/lastPositions$#', '/positions', $base);
    } elseif (preg_match('#/positions$#', $base)) {
        $tryUrls[] = $base;
        $tryUrls[] = preg_replace('#/positions$#', '/reports/lastPositions', $base);
    } else {
        $tryUrls[] = $base . '/reports/lastPositions';
        $tryUrls[] = $base . '/positions';
        $tryUrls[] = $base . '/api/reports/lastPositions';
        $tryUrls[] = $base . '/api/positions';
    }

    $devicesMap = traccar_positions_fetch_devices_map($base, $user, $pass);

    foreach ($tryUrls as $url) {
        $res = traccar_positions_fetch_json($url, $user, $pass);
        if (!$res['ok']) {
            continue;
        }
        $data = $res['data'];
        if (!is_array($data)) {
            continue;
        }

        $items = $data;
        if (preg_match('#/positions$#', $url)) {
            $byDevice = [];
            foreach ($data as $d) {
                $did = $d['deviceId'] ?? $d['id'] ?? null;
                $time = strtotime($d['deviceTime'] ?? ($d['serverTime'] ?? null) ?: '0');
                if (!isset($byDevice[$did]) || $time > $byDevice[$did]['time']) {
                    $byDevice[$did] = ['time' => $time, 'data' => $d];
                }
            }
            $items = array_map(function ($v) { return $v['data']; }, $byDevice);
        }

        $saved = false;
        foreach ($items as $item) {
            if (traccar_positions_upsert($db, $item, $devicesMap)) {
                $saved = true;
            }
        }
        if ($saved) {
            return true;
        }
    }

    return false;
}

function traccar_positions_try_fetch_and_save_from_bases(mysqli $db, $primaryBase, $altCsv, $user, $pass) {
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
        if (traccar_positions_try_fetch_and_save($db, $base, $user, $pass)) {
            return true;
        }
    }

    return false;
}

function traccar_positions_is_stale(mysqli $db, $maxAge) {
    $res = $db->query("SELECT MAX(updated_at) AS last_updated FROM traccar_positions_last");
    if (!$res) {
        return true;
    }
    $row = $res->fetch_assoc();
    $last = $row['last_updated'] ?? null;
    if (!$last) {
        return true;
    }
    $age = time() - strtotime((string)$last);
    return $age >= (int)$maxAge;
}

$hasKendaraan = table_exists_mysqli($mysqli, 'kendaraan');
$kendaraanCols = $hasKendaraan ? table_columns_mysqli($mysqli, 'kendaraan') : [];
$hasLocator = in_array('locator', $kendaraanCols, true);
$hasPenggunaId = in_array('pengguna_id', $kendaraanCols, true);
$hasPengguna = table_exists_mysqli($mysqli, 'pengguna');

$hasTraccar = table_exists_mysqli($mysqli, 'traccar_positions_last');
$live = isset($_GET['live']) && $_GET['live'] !== '0';
$force = isset($_GET['force']) && $_GET['force'] !== '0';
$maxAge = isset($_GET['max_age']) ? (int)$_GET['max_age'] : null;
if ($maxAge === null) {
    $maxAge = $live ? 5 : 30;
}
if ($maxAge < 3) $maxAge = 3;
if ($maxAge > 300) $maxAge = 300;

if ($hasTraccar && ($force || traccar_positions_is_stale($mysqli, $maxAge))) {
    $traccarBase = getenv('TRACCAR_API_BASE') ?: 'http://localhost:8082/api';
    $traccarAlt = getenv('TRACCAR_API_BASE_ALTERNATES') ?: '';
    $traccarUser = getenv('TRACCAR_USER') ?: 'admin@gmail.com';
    $traccarPass = getenv('TRACCAR_PASS') ?: 'admin';
    traccar_positions_try_fetch_and_save_from_bases($mysqli, $traccarBase, $traccarAlt, $traccarUser, $traccarPass);
}

$select = "p.*, NULL AS vehicle_id, NULL AS no_reg, NULL AS no_polisi, NULL AS merk, NULL AS tipe, NULL AS locator, NULL AS pengguna_id, NULL AS user_name, NULL AS user_pangkat";
$from = " FROM traccar_positions_last p";

if ($hasKendaraan && $hasLocator) {
        // Locator matching supports uniqueId, numeric deviceId, and deviceName from Traccar.
        $select = "p.*, k.id AS vehicle_id, k.no_reg, k.no_polisi, k.merk, k.tipe, k.locator, " .
                            ($hasPenggunaId ? "k.pengguna_id" : "NULL") . " AS pengguna_id, " .
                            (($hasPengguna && $hasPenggunaId) ? "u.nama_lengkap" : "NULL") . " AS user_name, " .
                            (($hasPengguna && $hasPenggunaId) ? "u.pangkat" : "NULL") . " AS user_pangkat";

    $from .= " LEFT JOIN kendaraan k
                             ON (
                                        CONVERT(TRIM(k.locator) USING utf8mb4) COLLATE utf8mb4_unicode_ci = CONVERT(TRIM(p.device_uid) USING utf8mb4) COLLATE utf8mb4_unicode_ci
                                        OR TRIM(k.locator) = CAST(p.device_id AS CHAR)
                                        OR CONVERT(TRIM(k.locator) USING utf8mb4) COLLATE utf8mb4_unicode_ci = CONVERT(TRIM(p.device_name) USING utf8mb4) COLLATE utf8mb4_unicode_ci
                                    )";

    if ($hasPengguna && $hasPenggunaId) {
        $from .= " LEFT JOIN pengguna u ON u.id = k.pengguna_id";
    }
}

$sql = "SELECT {$select}{$from} ORDER BY p.updated_at DESC";

$res = $mysqli->query($sql);
if (!$res) {
    http_response_code(500);
    echo json_encode(['error' => 'db_error', 'msg' => $mysqli->error]);
    exit;
}

$rows = [];
while ($r = $res->fetch_assoc()) {
    // penanggung_jawab column removed; user_name now always from pengguna relationship or stays empty
    $labelParts = [];
    if (!empty($r['no_reg'])) {
        $labelParts[] = $r['no_reg'];
    } elseif (!empty($r['no_polisi'])) {
        $labelParts[] = $r['no_polisi'];
    } elseif (!empty($r['merk'])) {
        $labelParts[] = $r['merk'];
    } elseif (!empty($r['device_name'])) {
        $labelParts[] = $r['device_name'];
    }
    if (!empty($r['user_name'])) {
        $labelParts[] = $r['user_name'];
    }
    $r['display_label'] = !empty($labelParts) ? implode(' - ', $labelParts) : ($r['device_name'] ?? '');
    $r['user_label'] = trim(($r['user_pangkat'] ?? '') . (($r['user_pangkat'] ?? '') !== '' && !empty($r['user_name']) ? ' - ' : '') . ($r['user_name'] ?? ''));
    $rows[] = $r;
}

echo json_encode($rows);
