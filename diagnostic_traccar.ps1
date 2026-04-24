# Diagnostic script untuk Traccar integration
$TraccarBase = 'http://192.168.1.109:8082/api'
$TraccarUser = 'admin@example.com'
$TraccarPass = 'admin'
$AppBase = 'http://localhost/mawang'

# Build auth header
$pair = $TraccarUser + ':' + $TraccarPass
$basicAuth = [Convert]::ToBase64String([Text.Encoding]::ASCII.GetBytes($pair))
$headers = @{
  Authorization = 'Basic ' + $basicAuth
  Accept = 'application/json'
  'Content-Type' = 'application/json'
}

Write-Output "=== STEP 1: Test Traccar API Connectivity ==="
try {
  $devices = Invoke-RestMethod -Uri ($TraccarBase + '/devices') -Headers $headers -Method Get -TimeoutSec 15
  Write-Output "[OK] Traccar API accessible"
  Write-Output ("[OK] Found " + @($devices).Count + " devices in Traccar")
  Write-Output ""
  Write-Output "Device list:"
  @($devices) | ForEach-Object {
    Write-Output ("  - ID: $($_.id) | Name: $($_.name) | UniqueId: $($_.uniqueId)")
  }
  Write-Output ""
} catch {
  Write-Output "[ERROR] Cannot connect to Traccar: $($_.Exception.Message)"
  exit 1
}

Write-Output "=== STEP 2: Fetch Last Positions from Traccar ==="
try {
  $positions = Invoke-RestMethod -Uri ($TraccarBase + '/reports/lastPositions') -Headers $headers -Method Post -Body '{"deviceIds":[]}' -TimeoutSec 15
  Write-Output ("[OK] Found " + @($positions).Count + " positions in Traccar")
  if (@($positions).Count -gt 0) {
    @($positions) | ForEach-Object {
      Write-Output ("  - Device ID: $($_.deviceId) | Lat: $($_.latitude) | Lon: $($_.longitude)")
    }
  }
  Write-Output ""
} catch {
  Write-Output "[ERROR] Cannot fetch positions: $($_.Exception.Message)"
  Write-Output "Trying /positions endpoint instead..."
  try {
    $positions = Invoke-RestMethod -Uri ($TraccarBase + '/positions') -Headers $headers -Method Get -TimeoutSec 15
    Write-Output ("[OK] Found " + @($positions).Count + " positions from /positions")
    @($positions) | ForEach-Object {
      Write-Output ("  - Device ID: $($_.deviceId) | Lat: $($_.latitude) | Lon: $($_.longitude)")
    }
    Write-Output ""
  } catch {
    Write-Output "[ERROR] /positions also failed: $($_.Exception.Message)"
  }
}

Write-Output "=== STEP 3: Check Local Database - traccar_positions_last Table ===" 
# Run via PHP CLI to execute database query
$phpScript = @'
<?php
require_once __DIR__ . '/config/db.php';
$result = mysqli_query($conn, "SELECT COUNT(*) as cnt FROM traccar_positions_last");
$row = mysqli_fetch_assoc($result);
echo "Rows in traccar_positions_last: " . $row['cnt'] . "\n";

if ($row['cnt'] > 0) {
  $result2 = mysqli_query($conn, "SELECT device_id, device_uid, device_name, latitude, longitude FROM traccar_positions_last LIMIT 5");
  echo "\nSample data:\n";
  while ($r = mysqli_fetch_assoc($result2)) {
    echo "  - Device ID: " . $r['device_id'] . " | Name: " . $r['device_name'] . " | Lat: " . $r['latitude'] . " | Lon: " . $r['longitude'] . "\n";
  }
} else {
  echo "\n⚠ Table is empty! No data from Traccar has been ingested yet.\n";
}
?>
'@

$phpScript | Out-File -FilePath "$PSScriptRoot\temp_check.php" -Encoding UTF8
php temp_check.php
Remove-Item "$PSScriptRoot\temp_check.php" -ErrorAction SilentlyContinue
Write-Output ""

Write-Output "=== STEP 4: Check Kendaraan Table - Locator Values ==="
$phpScript2 = @'
<?php
require_once __DIR__ . '/config/db.php';
$result = mysqli_query($conn, "SELECT id, nama_kendaraan, locator FROM kendaraan WHERE locator IS NOT NULL AND locator != '' LIMIT 10");
echo "Vehicles with locator values:\n";
$count = 0;
while ($r = mysqli_fetch_assoc($result)) {
  echo "  - ID: " . $r['id'] . " | Name: " . $r['nama_kendaraan'] . " | Locator: " . $r['locator'] . "\n";
  $count++;
}
if ($count == 0) {
  echo "  ⚠ No locator values found in kendaraan table!\n";
}
?>
'@

$phpScript2 | Out-File -FilePath "$PSScriptRoot\temp_check2.php" -Encoding UTF8
php temp_check2.php
Remove-Item "$PSScriptRoot\temp_check2.php" -ErrorAction SilentlyContinue
Write-Output ""

Write-Output "=== STEP 5: Test Webhook Endpoint ==="
$samplePayload = @{
  id = 12345
  deviceId = 1
  uniqueId = "test_device_001"
  deviceName = "Test Vehicle"
  latitude = -7.8
  longitude = 110.4
  altitude = 50
  speed = 0
  course = 0
  accuracy = 5
  fixTime = Get-Date -Format "yyyy-MM-ddTHH:mm:ss.fffZ"
} | ConvertTo-Json

$webhookHeaders = @{
  'X-TRACCAR-SECRET' = 'mawang'
  'Content-Type' = 'application/json'
}

try {
  $webhookUri = $AppBase + '/ajax/traccar_webhook.php'
  $response = Invoke-RestMethod -Uri $webhookUri -Headers $webhookHeaders -Method Post -Body $samplePayload -TimeoutSec 15
  Write-Output ("[OK] Webhook endpoint returns: $($response | ConvertTo-Json)")
} catch {
  Write-Output "[ERROR] Webhook test failed: $($_.Exception.Message)"
}
Write-Output ""

Write-Output "=== STEP 6: Check ajax/traccar_positions.php Response ==="
try {
  $posResponse = Invoke-RestMethod -Uri ($AppBase + '/ajax/traccar_positions.php') -Method Get -UseBasicParsing -TimeoutSec 15
  $posData = $posResponse | ConvertFrom-Json -ErrorAction SilentlyContinue
  if ($posData) {
    Write-Output ("[OK] Positions endpoint returns " + @($posData).Count + " records")
    @($posData) | ForEach-Object {
      Write-Output ("  - Device: $($_.device_name) | Vehicle ID: $($_.vehicle_id) | Lat: $($_.latitude) | Lon: $($_.longitude)")
    }
  } else {
    Write-Output "[WARN] Empty response from positions endpoint"
  }
} catch {
  Write-Output "[ERROR] Cannot fetch positions: $($_.Exception.Message)"
}
Write-Output ""

Write-Output "=== SUMMARY ==="
Write-Output "If Vehicle ID is NULL in Step 6, it means locator does not match any device."
Write-Output "Check that kendaraan.locator matches one of: device_id, device_uid, or device_name from Traccar."
