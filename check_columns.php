<?php
require 'config/db.php';
$result = mysqli_query($conn, 'DESCRIBE kendaraan');
echo "Kendaraan table columns:\n";
while ($r = mysqli_fetch_assoc($result)) {
  echo '  - ' . $r['Field'] . ' (' . $r['Type'] . ')' . ($r['Key'] === 'PRI' ? ' [PRIMARY]' : '') . "\n";
}
?>
