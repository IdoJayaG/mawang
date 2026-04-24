<?php
// Test Traccar connectivity dengan berbagai method

echo "=== TRACCAR API CONNECTIVITY TEST ===\n\n";

// Method 1: Direct curl dengan verifikasi SSL off
echo "[Method 1] Basic curl test to Traccar API endpoints:\n";
$traccarBases = [
  'http://127.0.0.1:8082',
  'http://localhost:8082',
  'http://192.168.1.109:8082',
  'http://10.239.171.72:8082'
];

foreach ($traccarBases as $base) {
  echo "\nTrying: $base/api/devices\n";
  
  $ch = curl_init($base . '/api/devices');
  curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
  curl_setopt($ch, CURLOPT_USERPWD, 'admin@example.com:admin');
  curl_setopt($ch, CURLOPT_TIMEOUT, 2);
  curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 2);
  curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
  curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false);
  curl_setopt($ch, CURLOPT_HTTPHEADER, ['Accept: application/json']);
  curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
  
  $response = curl_exec($ch);
  $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
  $error = curl_error($ch);
  $errno = curl_errno($ch);
  curl_close($ch);
  
  if ($errno !== 0) {
    echo "  [TIMEOUT/CONN ERROR #$errno] $error\n";
  } else if ($httpCode === 200) {
    $devices = json_decode($response, true);
    echo "  [SUCCESS HTTP 200] Found " . count($devices) . " devices\n";
    break;
  } else {
    echo "  [HTTP $httpCode] " . substr($response, 0, 100) . "\n";
  }
}

// Method 2: Check if we can access Traccar UI
echo "\n\n[Method 2] Check Traccar Web UI:\n";
$ch = curl_init('http://127.0.0.1:8082');
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_TIMEOUT, 2);
curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
$response = curl_exec($ch);
$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);

if ($httpCode === 200 && strpos($response, 'traccar') !== false) {
  echo "  [SUCCESS] Traccar UI accessible at http://127.0.0.1:8082\n";
} else {
  echo "  [FAILED] HTTP $httpCode - Traccar UI not responding properly\n";
}

// Method 3: Check /api/server endpoint (doesn't require auth by default)
echo "\n[Method 3] Check /api/server endpoint:\n";
$ch = curl_init('http://127.0.0.1:8082/api/server');
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_TIMEOUT, 2);
curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
$response = curl_exec($ch);
$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);

if ($httpCode === 200) {
  $server = json_decode($response, true);
  echo "  [SUCCESS] Server info: " . json_encode($server) . "\n";
} else {
  echo "  [HTTP $httpCode] Response: " . substr($response, 0, 150) . "\n";
}

echo "\n\n=== RECOMMENDATION ===\n";
echo "If Traccar API is not accessible:\n";
echo "1. Verify Traccar service is running: Get-Service traccar\n";
echo "2. Check Traccar port binding: netstat -an | findstr :8082\n";
echo "3. If not on port 8082, update poller script credential defaults\n";
echo "4. For historical timeline: ensure Traccar reports API accessible\n";
echo "5. Current data already in system - map display should work\n";
?>
