<?php
require_once __DIR__ . '/config/db.php';

echo "=== CEK DEVICE YANG HILANG ===\n\n";

// STEP 1: List semua kendaraan dengan locator
echo "[STEP 1] Kendaraan Table - Locator Values:\n";
$query = "SELECT id, no_polisi, merk, tipe, locator FROM kendaraan WHERE locator IS NOT NULL AND locator != '' ORDER BY id";
$result = mysqli_query($conn, $query);
$kendaraan_list = [];

echo "Kendaraan dengan locator:\n";
while ($r = mysqli_fetch_assoc($result)) {
  echo "  ID:{$r['id']} | {$r['no_polisi']} | {$r['merk']} {$r['tipe']} | Locator: {$r['locator']}\n";
  $kendaraan_list[$r['locator']] = $r;
}
echo "\n";

// STEP 2: List data di traccar_positions_last
echo "[STEP 2] Data Dalam traccar_positions_last:\n";
$query = "SELECT device_id, device_uid, device_name FROM traccar_positions_last GROUP BY device_id ORDER BY device_id";
$result = mysqli_query($conn, $query);
$positions_devices = [];

echo "Device dengan data posisi:\n";
while ($r = mysqli_fetch_assoc($result)) {
  echo "  ID:{$r['device_id']} | Name:{$r['device_name']} | UID:{$r['device_uid']}\n";
  $positions_devices[] = [
    'device_id' => $r['device_id'],
    'device_uid' => $r['device_uid'],
    'device_name' => $r['device_name']
  ];
}
echo "\n";

// STEP 3: Compare - locator mana yang TIDAK ada di traccar_positions_last
echo "[STEP 3] Matching Analysis:\n";
foreach ($kendaraan_list as $locator => $kendaraan) {
  $found = false;
  foreach ($positions_devices as $dev) {
    if ($dev['device_uid'] == $locator || 
        $dev['device_id'] == $locator || 
        $dev['device_name'] == $locator) {
      echo "✓ Kendaraan {$kendaraan['no_polisi']} (Locator:{$locator}) → Matched dengan Device {$dev['device_name']} (UID:{$dev['device_uid']})\n";
      $found = true;
      break;
    }
  }
  if (!$found) {
    echo "✗ Kendaraan {$kendaraan['no_polisi']} (Locator:{$locator}) → NO MATCHING DEVICE IN traccar_positions_last!\n";
  }
}
echo "\n";

// STEP 4: Recommendations
echo "[STEP 4] KEMUNGKINAN PENYEBAB:\n\n";
echo "Untuk device yang TIDAK muncul:\n";
echo "1. Device belum mengirim posisi ke Traccar - cek di Traccar apakah device siap\n";
echo "2. Locator value SALAH - harus match: device_uid, device_id (angka), atau device_name\n";
echo "3. Device ada di Traccar tapi webhook/poller belum dijalankan\n\n";

echo "[STEP 5] SOLUSI:\n\n";
echo "Opsi A: Jalankan poller sekali untuk fetch semua device dari Traccar:\n";
echo "  php scripts/traccar_poller.php\n\n";

echo "Opsi B: Cek di Traccar UI - lihat apakah device dengan locator 888888 ada & punya posisi:\n";
echo "  - Buka: http://127.0.0.1:8082\n";
echo "  - Lihat device list dan catat: device_id, device_uid, device_name yang sesuai\n";
echo "  - Update kendaraan.locator dengan nilai yang EXACT MATCH\n";
?>
