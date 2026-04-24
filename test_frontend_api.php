<?php
// Simulate frontend API call
require_once __DIR__ . '/config/db.php';

echo "=== FRONTEND AJAX ENDPOINT TEST ===\n\n";

// This is the exact logic from ajax/traccar_positions.php
$query = "SELECT 
  p.id,
  p.device_id,
  p.device_uid,
  p.device_name,
  p.latitude,
  p.longitude,
  p.speed,
  p.course,
  p.accuracy,
  p.device_time,
  k.id as vehicle_id,
  k.no_polisi,
  k.penanggung_jawab
FROM traccar_positions_last p
LEFT JOIN kendaraan k ON (
  CONVERT(TRIM(k.locator) USING utf8mb4) COLLATE utf8mb4_unicode_ci = CONVERT(TRIM(p.device_uid) USING utf8mb4) COLLATE utf8mb4_unicode_ci
  OR TRIM(k.locator) = CAST(p.device_id AS CHAR)
  OR CONVERT(TRIM(k.locator) USING utf8mb4) COLLATE utf8mb4_unicode_ci = CONVERT(TRIM(p.device_name) USING utf8mb4) COLLATE utf8mb4_unicode_ci
)
ORDER BY p.id DESC";

$result = mysqli_query($conn, $query);
if (!$result) {
  echo "ERROR: " . mysqli_error($conn) . "\n";
  exit(1);
}

$positions = [];
while ($row = mysqli_fetch_assoc($result)) {
  // Filter out records tanpa vehicle_id (unmapped devices)
  if ($row['vehicle_id'] === null) {
    continue;  // Skip unmapped devices
  }
  
  $positions[] = [
    'id' => (int)$row['id'],
    'device_id' => (int)$row['device_id'],
    'device_uid' => $row['device_uid'],
    'device_name' => $row['device_name'],
    'latitude' => (float)$row['latitude'],
    'longitude' => (float)$row['longitude'],
    'speed' => (float)$row['speed'],
    'course' => (float)$row['course'],
    'accuracy' => (float)$row['accuracy'],
    'device_time' => $row['device_time'],
    'vehicle_id' => (int)$row['vehicle_id'],
    'display_label' => $row['no_polisi'],
    'user_label' => $row['penanggung_jawab']
  ];
}

echo "Positions untuk peta (dengan vehicle_id yang matched):\n";
echo json_encode($positions, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) . "\n\n";

echo "[SUMMARY]\n";
echo "Total mapped positions: " . count($positions) . "\n";
foreach ($positions as $pos) {
  echo "  ✓ " . $pos['device_name'] . " (" . $pos['vehicle_id'] . ") -> " . $pos['display_label'] . " [" . $pos['latitude'] . ", " . $pos['longitude'] . "]\n";
}
?>
