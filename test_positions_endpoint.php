<?php
// Test frontend endpoint
require_once __DIR__ . '/config/db.php';

// Simulate what traccar_positions.php does
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
  p.server_time,
  k.id as vehicle_id,
  k.no_polisi,
  k.pengguna_id,
  k.penanggung_jawab
FROM traccar_positions_last p
LEFT JOIN kendaraan k ON (
  CONVERT(TRIM(k.locator) USING utf8mb4) COLLATE utf8mb4_unicode_ci = CONVERT(TRIM(p.device_uid) USING utf8mb4) COLLATE utf8mb4_unicode_ci
  OR TRIM(k.locator) = CAST(p.device_id AS CHAR)
  OR CONVERT(TRIM(k.locator) USING utf8mb4) COLLATE utf8mb4_unicode_ci = CONVERT(TRIM(p.device_name) USING utf8mb4) COLLATE utf8mb4_unicode_ci
)
ORDER BY p.id DESC";

$result = mysqli_query($conn, $query);
$positions = [];

while ($row = mysqli_fetch_assoc($result)) {
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
    'server_time' => $row['server_time'],
    'vehicle_id' => $row['vehicle_id'] ? (int)$row['vehicle_id'] : null,
    'display_label' => $row['no_polisi'] ? $row['no_polisi'] : 'Unknown',
    'user_label' => $row['penanggung_jawab'] ? $row['penanggung_jawab'] : '---'
  ];
}

echo "=== Frontend Endpoint Test (traccar_positions.php simulation) ===\n\n";
echo json_encode($positions, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . "\n\n";

echo "Results:\n";
echo "Total records: " . count($positions) . "\n";
echo "Records with vehicle_id: " . count(array_filter($positions, fn($p) => $p['vehicle_id'] !== null)) . "\n";

if (count($positions) > 0 && $positions[0]['vehicle_id'] !== null) {
  echo "\n✓ Data should appear on map!\n";
  echo "  Vehicle: " . $positions[0]['display_label'] . "\n";
  echo "  Position: Lat " . $positions[0]['latitude'] . ", Lon " . $positions[0]['longitude'] . "\n";
} else if (count($positions) > 0 && $positions[0]['vehicle_id'] === null) {
  echo "\n✗ Vehicle ID is NULL - locator mismatch!\n";
  echo "  Device: " . $positions[0]['device_name'] . " / " . $positions[0]['device_uid'] . "\n";
} else {
  echo "\n✗ No positions found\n";
}
?>
