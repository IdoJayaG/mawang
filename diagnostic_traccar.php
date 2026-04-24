<?php
// diagnostic_traccar.php
require_once __DIR__ . '/config/db.php';

echo "=== TRACCAR INTEGRATION DIAGNOSTIC ===\n\n";

// STEP 1: Check database connection
echo "[STEP 1] Database Connection\n";
if ($conn && mysqli_ping($conn)) {
  echo "  [OK] Connected to database\n\n";
} else {
  echo "  [ERROR] Cannot connect to database: " . mysqli_connect_error() . "\n";
  exit(1);
}

// STEP 2: Check traccar_positions_last table
echo "[STEP 2] traccar_positions_last Table Status\n";
$result = mysqli_query($conn, "SELECT COUNT(*) as cnt FROM traccar_positions_last");
$row = mysqli_fetch_assoc($result);
$count = (int)$row['cnt'];
echo "  Total rows: $count\n";
if ($count > 0) {
  echo "  [OK] Table has data\n";
  $result2 = mysqli_query($conn, "SELECT device_id, device_uid, device_name, latitude, longitude, device_time FROM traccar_positions_last LIMIT 5");
  echo "  Last 5 entries:\n";
  while ($r = mysqli_fetch_assoc($result2)) {
    echo "    - ID:{$r['device_id']} | Name:{$r['device_name']} | UID:{$r['device_uid']} | Lat:{$r['latitude']} | Lon:{$r['longitude']} | Time:{$r['device_time']}\n";
  }
} else {
  echo "  [WARN] Table is EMPTY - no data from Traccar ingested yet\n";
}
echo "\n";

// STEP 3: Check kendaraan vs locator mapping
echo "[STEP 3] Kendaraan Table - Locator Values\n";
$query = "SELECT id, no_polisi, merk, tipe, locator FROM kendaraan WHERE locator IS NOT NULL AND locator != '' ORDER BY id LIMIT 10";
$result = mysqli_query($conn, $query);
if (!$result) {
  echo "  [ERROR] Query failed: " . mysqli_error($conn) . "\n";
} else {
  $count_with_locator = mysqli_num_rows($result);
  if ($count_with_locator === 0) {
    echo "  [WARN] No locator values found in kendaraan table\n";
    echo "  First, you need to add device identifiers to the 'locator' column\n";
  } else {
    echo "  Found $count_with_locator vehicle(s) with locator values:\n";
    while ($r = mysqli_fetch_assoc($result)) {
      echo "    - ID:{$r['id']} | Polisi:{$r['no_polisi']} | {$r['merk']} {$r['tipe']} | Locator:{$r['locator']}\n";
    }
  }
}
echo "\n";

// STEP 4: Check mapping between traccar_positions_last and kendaraan
echo "[STEP 4] Mapping Test: traccar_positions_last -> kendaraan\n";
$query = "SELECT 
  p.device_id, 
  p.device_uid, 
  p.device_name,
  p.latitude,
  p.longitude,
  k.id as vehicle_id,
  k.no_polisi
FROM traccar_positions_last p
LEFT JOIN kendaraan k ON (
  CONVERT(TRIM(k.locator) USING utf8mb4) COLLATE utf8mb4_unicode_ci = CONVERT(TRIM(p.device_uid) USING utf8mb4) COLLATE utf8mb4_unicode_ci
  OR TRIM(k.locator) = CAST(p.device_id AS CHAR)
  OR CONVERT(TRIM(k.locator) USING utf8mb4) COLLATE utf8mb4_unicode_ci = CONVERT(TRIM(p.device_name) USING utf8mb4) COLLATE utf8mb4_unicode_ci
)
LIMIT 10";

$result = mysqli_query($conn, $query);
if (!$result) {
  echo "  [ERROR] Query failed: " . mysqli_error($conn) . "\n";
} else {
  $rows = mysqli_num_rows($result);
  if ($rows === 0) {
    echo "  [INFO] No positions in traccar_positions_last yet - waiting for data ingestion\n";
  } else {
    echo "  Found $rows position(s):\n";
    while ($r = mysqli_fetch_assoc($result)) {
      $vehicleId = $r['vehicle_id'] === null ? "NULL (NO MATCH)" : $r['vehicle_id'];
      $vehicleName = $r['no_polisi'] === null ? "---" : $r['no_polisi'];
      echo "    - TraccarDevice: {$r['device_id']} ({$r['device_name']} / {$r['device_uid']}) -> Vehicle: $vehicleId ($vehicleName)\n";
    }
  }
}
echo "\n";

// STEP 5: Check webhook endpoint accessibility
echo "[STEP 5] Webhook Endpoint Status\n";
if (file_exists(__DIR__ . '/ajax/traccar_webhook.php')) {
  echo "  [OK] Webhook file exists: ajax/traccar_webhook.php\n";
  // Try to make a test call
  $payload = json_encode([
    'id' => 9999,
    'deviceId' => 9999,
    'uniqueId' => '__test__',
    'deviceName' => 'Test Device',
    'latitude' => -7.8,
    'longitude' => 110.4,
    'altitude' => 0,
    'speed' => 0,
    'course' => 0,
    'accuracy' => 0,
    'deviceTime' => date('c')
  ]);
  
  $ch = curl_init('http://localhost/mawang/ajax/traccar_webhook.php');
  curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
  curl_setopt($ch, CURLOPT_POST, true);
  curl_setopt($ch, CURLOPT_POSTFIELDS, $payload);
  curl_setopt($ch, CURLOPT_HTTPHEADER, [
    'Content-Type: application/json',
    'X-TRACCAR-SECRET: mawang'
  ]);
  curl_setopt($ch, CURLOPT_TIMEOUT, 10);
  
  $response = curl_exec($ch);
  $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
  $error = curl_error($ch);
  curl_close($ch);
  
  if ($error) {
    echo "  [ERROR] Webhook test failed: $error\n";
  } else {
    echo "  Webhook response: HTTP $httpCode\n";
    $decoded = json_decode($response, true);
    if ($decoded) {
      echo "  Response: " . json_encode($decoded, JSON_PRETTY_PRINT) . "\n";
    } else {
      echo "  Response body: $response\n";
    }
  }
} else {
  echo "  [ERROR] Webhook file not found\n";
}
echo "\n";

// STEP 6: Check Traccar API connectivity
echo "[STEP 6] Traccar API Connectivity\n";
$traccarBases = [
  'http://localhost:8082/api',
  'http://127.0.0.1:8082/api',
  'http://192.168.1.109:8082/api',
  'http://10.239.171.72:8082/api'
];

$connected = false;
foreach ($traccarBases as $base) {
  $ch = curl_init($base . '/devices');
  curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
  curl_setopt($ch, CURLOPT_USERPWD, 'admin@example.com:admin');
  curl_setopt($ch, CURLOPT_TIMEOUT, 3);
  curl_setopt($ch, CURLOPT_HTTPHEADER, ['Accept: application/json']);
  
  $response = curl_exec($ch);
  $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
  curl_close($ch);
  
  if ($httpCode === 200) {
    $devices = json_decode($response, true);
    echo "  [OK] Connected to: $base\n";
    echo "  Found " . count($devices) . " device(s) in Traccar:\n";
    foreach ($devices as $dev) {
      echo "    - ID: {$dev['id']} | Name: {$dev['name']} | UniqueId: {$dev['uniqueId']}\n";
    }
    $connected = true;
    break;
  }
}

if (!$connected) {
  echo "  [ERROR] Cannot reach Traccar API on any known address\n";
  echo "  Tried:\n";
  foreach ($traccarBases as $base) {
    echo "    - $base\n";
  }
}
echo "\n";

// STEP 7: Recommendations
echo "[RECOMMENDATIONS]\n";
if ($count === 0) {
  echo "1. traccar_positions_last is EMPTY - you need to start data ingestion:\n";
  echo "   Option A: Run poller: php scripts/traccar_poller.php --daemon --interval=10\n";
  echo "   Option B: Configure webhook in Traccar UI:\n";
  echo "      - Go to Traccar admin panel\n";
  echo "      - Create Notification event for Position updated\n";
  echo "      - Set type: HTTP\n";
  echo "      - URL: http://YOUR_APP_HOST/mawang/ajax/traccar_webhook.php\n";
  echo "      - Headers: X-TRACCAR-SECRET=mawang\n";
  echo "\n";
}

echo "2. Verify locator values match your Traccar devices:\n";
echo "   Locator can be: device_id (numeric), device_uid, or device_name\n";
echo "   Current Traccar devices shown in STEP 6 above\n";
echo "   Update kendaraan.locator to match one of these values\n";
echo "\n";

echo "Done!\n";
?>
