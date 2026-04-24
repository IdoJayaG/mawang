<?php
// ajax/traccar_webhook.php
require_once __DIR__ . '/../config/db.php';

// Shared secret: change this to a strong secret and configure Traccar to send this header
$EXPECTED_SECRET = 'mawang';

// Read secret from header (X-TRACCAR-SECRET) or Authorization: Bearer <secret>
$hdr = $_SERVER['HTTP_X_TRACCAR_SECRET'] ?? null;
if (!$hdr && !empty($_SERVER['HTTP_AUTHORIZATION'])) {
    $auth = $_SERVER['HTTP_AUTHORIZATION'];
    if (stripos($auth, 'Bearer ') === 0) {
        $hdr = substr($auth, 7);
    }
}

if (!$hdr || $hdr !== $EXPECTED_SECRET) {
    http_response_code(401);
    header('Content-Type: application/json');
    echo json_encode(['error' => 'unauthorized']);
    exit;
}

$body = file_get_contents('php://input');
$data = json_decode($body, true);
if (!$data) {
    http_response_code(400);
    header('Content-Type: application/json');
    echo json_encode(['error' => 'invalid_json']);
    exit;
}

// Traccar notification payload may contain position fields directly
$deviceId = $data['deviceId'] ?? $data['id'] ?? null;
$deviceUid = $data['uniqueId'] ?? $data['deviceUid'] ?? null;
$deviceName = $data['deviceName'] ?? $data['name'] ?? null;
$lat = $data['latitude'] ?? $data['lat'] ?? null;
$lon = $data['longitude'] ?? $data['lon'] ?? null;
$speed = $data['speed'] ?? null;
$course = $data['course'] ?? null;
$accuracy = $data['accuracy'] ?? null;
$deviceTime = null;
if (!empty($data['deviceTime'])) {
    // deviceTime may be in ISO format
    $deviceTime = date('Y-m-d H:i:s', strtotime($data['deviceTime']));
}

// Fallback: if body contains nested 'position' or 'attributes', try to parse
if (($lat === null || $lon === null) && isset($data['position'])) {
    $pos = $data['position'];
    $lat = $lat ?? ($pos['latitude'] ?? null);
    $lon = $lon ?? ($pos['longitude'] ?? null);
    $speed = $speed ?? ($pos['speed'] ?? null);
    $course = $course ?? ($pos['course'] ?? null);
    if (empty($deviceTime) && !empty($pos['deviceTime'])) {
        $deviceTime = date('Y-m-d H:i:s', strtotime($pos['deviceTime']));
    }
}

// Save raw extra fields for debugging
$extra = $data;

// Prepare upsert
$stmt = $mysqli->prepare("INSERT INTO traccar_positions_last
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

// Bind params: device_id (i), device_uid (s), device_name (s), latitude (d), longitude (d), speed (d), course (d), accuracy (d), device_time (s), extra (s)
$device_id_param = $deviceId !== null ? (int)$deviceId : null;
$device_uid_param = $deviceUid !== null ? $deviceUid : null;
$device_name_param = $deviceName !== null ? $deviceName : null;
$lat_param = $lat !== null ? (float)$lat : null;
$lon_param = $lon !== null ? (float)$lon : null;
$speed_param = isset($speed) ? (float)$speed : null;
$course_param = isset($course) ? (float)$course : null;
$accuracy_param = isset($accuracy) ? (float)$accuracy : null;
$device_time_param = $deviceTime !== null ? $deviceTime : null;
$extra_json = json_encode($extra, JSON_UNESCAPED_UNICODE);

// Because mysqli_stmt::bind_param requires types and doesn't accept null for d types directly, we'll use NULL handling with strings and let MySQL convert.
$stmt->bind_param('issdddddss', $device_id_param, $device_uid_param, $device_name_param, $lat_param, $lon_param, $speed_param, $course_param, $accuracy_param, $device_time_param, $extra_json);
// Note: above type string may not perfectly match nulls; ensuring correct types further would require dynamic SQL. For simplicity, attempt execute.

$ok = $stmt->execute();
if (!$ok) {
    http_response_code(500);
    header('Content-Type: application/json');
    echo json_encode(['error' => 'db_error', 'msg' => $stmt->error]);
    $stmt->close();
    exit;
}
$stmt->close();

header('Content-Type: application/json');
echo json_encode(['ok' => true]);
