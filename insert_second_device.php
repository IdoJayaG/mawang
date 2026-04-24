<?php
require_once __DIR__ . '/config/db.php';

echo "=== MANUAL INSERT DEVICE POSITION TEST ===\n\n";

// Check current state
$result = mysqli_query($conn, "SELECT id, device_uid, device_name FROM traccar_positions_last WHERE device_uid = '888888'");
if (mysqli_num_rows($result) > 0) {
  echo "[INFO] Device 888888 sudah ada di traccar_positions_last\n";
  $row = mysqli_fetch_assoc($result);
  echo "  Device Name: " . $row['device_name'] . "\n";
  echo "  Device ID: " . $row['id'] . "\n";
} else {
  echo "[INFO] Device 888888 BELUM ada - akan diinsert\n";
  
  // Insert test position for device 888888
  $stmt = $conn->prepare("INSERT INTO traccar_positions_last
    (device_id, device_uid, device_name, latitude, longitude, speed, course, accuracy, device_time)
    VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)
    ON DUPLICATE KEY UPDATE
      device_name = VALUES(device_name),
      latitude = VALUES(latitude),
      longitude = VALUES(longitude),
      device_time = CURRENT_TIMESTAMP");
  
  $device_id = 2;  // Ganti dengan device ID yang sebenarnya dari Traccar
  $device_uid = '888888';
  $device_name = 'Device Kedua';  // Ganti dengan nama device dari Traccar
  $latitude = -6.5304114;  // Contoh koordinat Jakarta
  $longitude = 106.8827232;
  $speed = 0;
  $course = 0;
  $accuracy = 0;
  $device_time = date('Y-m-d H:i:s');
  
  $stmt->bind_param('issddddds', $device_id, $device_uid, $device_name, $latitude, $longitude, $speed, $course, $accuracy, $device_time);
  
  if ($stmt->execute()) {
    echo "[SUCCESS] Inserted position untuk device 888888\n";
    echo "  Device Name: $device_name\n";
    echo "  Position: Lat=$latitude, Lon=$longitude\n";
  } else {
    echo "[ERROR] " . $stmt->error . "\n";
  }
}

// Show current mapping
echo "\n[MAPPING STATUS]\n";
$query = "SELECT 
  p.device_uid,
  p.device_name,
  k.no_polisi,
  k.id as vehicle_id
FROM traccar_positions_last p
LEFT JOIN kendaraan k ON (
  CONVERT(TRIM(k.locator) USING utf8mb4) COLLATE utf8mb4_unicode_ci = CONVERT(TRIM(p.device_uid) USING utf8mb4) COLLATE utf8mb4_unicode_ci
)
ORDER BY p.device_uid";

$result = mysqli_query($conn, $query);
while ($r = mysqli_fetch_assoc($result)) {
  $status = $r['vehicle_id'] ? "✓ MATCHED to {$r['no_polisi']}" : "✗ NOT MATCHED";
  echo "Device: {$r['device_uid']} ({$r['device_name']}) - $status\n";
}
?>
