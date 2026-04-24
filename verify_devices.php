<?php
require 'config/db.php';
echo "=== VERIFY DATA ===\n\n";
$result = mysqli_query($conn, "SELECT COUNT(*) as cnt FROM traccar_positions_last");
$r = mysqli_fetch_assoc($result);
echo "Total positions in traccar_positions_last: " . $r['cnt'] . "\n\n";

$result = mysqli_query($conn, "SELECT device_uid, device_name, latitude, longitude FROM traccar_positions_last ORDER BY device_uid");
while ($r = mysqli_fetch_assoc($result)) {
  echo "Device: " . $r['device_name'] . " (UID: " . $r['device_uid'] . ") -> Lat: " . $r['latitude'] . ", Lon: " . $r['longitude'] . "\n";
}

echo "\n=== CHECK MAPPING WITH KENDARAAN ===\n\n";
$query = "SELECT 
  p.device_uid,
  p.device_name,
  k.no_polisi,
  k.id as vehicle_id
FROM traccar_positions_last p
LEFT JOIN kendaraan k ON k.locator = p.device_uid
ORDER BY p.device_uid";

$result = mysqli_query($conn, $query);
if (!$result) {
  echo "Query error: " . mysqli_error($conn) . "\n";
} else {
  while ($r = mysqli_fetch_assoc($result)) {
    if ($r['vehicle_id']) {
      echo "✓ " . $r['device_name'] . " (UID: " . $r['device_uid'] . ") -> MAPPED to " . $r['no_polisi'] . " (ID: " . $r['vehicle_id'] . ")\n";
    } else {
      echo "✗ " . $r['device_name'] . " (UID: " . $r['device_uid'] . ") -> NOT MAPPED\n";
    }
  }
}
?>
