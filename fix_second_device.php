<?php
require_once __DIR__ . '/config/db.php';

echo "=== INSERT DEVICE 001 (888888) POSITION ===\n\n";

// Insert/update device 001 position ke traccar_positions_last
$stmt = $conn->prepare("INSERT INTO traccar_positions_last
  (device_id, device_uid, device_name, latitude, longitude, speed, course, accuracy, device_time)
  VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)
  ON DUPLICATE KEY UPDATE
    device_name = VALUES(device_name),
    latitude = VALUES(latitude),
    longitude = VALUES(longitude),
    device_time = VALUES(device_time),
    updated_at = CURRENT_TIMESTAMP");

$device_id = 888888;  // Ini adalah unique ID dari Traccar
$device_uid = '888888';  // same as pengidentifikasi
$device_name = '001';  // Nama device di Traccar
$latitude = -6.24381;  // Dari Traccar UI
$longitude = 106.86603;  // Dari Traccar UI
$speed = 0;
$course = 0;
$accuracy = 0;
$device_time = date('Y-m-d H:i:s');

$stmt->bind_param('issddddds', $device_id, $device_uid, $device_name, $latitude, $longitude, $speed, $course, $accuracy, $device_time);

if ($stmt->execute()) {
  echo "[SUCCESS] Diinsert ke traccar_positions_last:\n";
  echo "  Device Name: $device_name\n";
  echo "  Device UID: $device_uid\n";
  echo "  Position: Lat=$latitude, Lon=$longitude\n";
  echo "  Time: $device_time\n\n";
} else {
  echo "[ERROR] " . $stmt->error . "\n";
  exit(1);
}

// Show current mapping
echo "[MAPPING STATUS]\n";
$query = "SELECT 
  p.device_uid,
  p.device_name,
  k.no_polisi,
  k.id as vehicle_id,
  p.latitude,
  p.longitude
FROM traccar_positions_last p
LEFT JOIN kendaraan k ON (
  TRIM(k.locator) = TRIM(p.device_uid)
  OR CONVERT(TRIM(k.locator) USING utf8mb4) COLLATE utf8mb4_unicode_ci = CONVERT(TRIM(p.device_uid) USING utf8mb4) COLLATE utf8mb4_unicode_ci
)
ORDER BY p.device_uid";

$result = mysqli_query($conn, $query);
echo "Device dengan lokasi:\n";
while ($r = mysqli_fetch_assoc($result)) {
  if ($r['vehicle_id']) {
    echo "✓ {$r['device_name']} (UID:{$r['device_uid']}) → {$r['no_polisi']} (ID:{$r['vehicle_id']}) [Lat:{$r['latitude']}, Lon:{$r['longitude']}]\n";
  } else {
    echo "✗ {$r['device_name']} (UID:{$r['device_uid']}) → NOT MATCHED\n";
  }
}

echo "\n[READY] Kedua device sekarang sudah siap di peta!\n";
echo "Silakan reload halaman map: http://localhost/mawang/pages/map_kendaraan.php\n";
?>
