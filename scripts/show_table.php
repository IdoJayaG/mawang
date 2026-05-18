<?php
require 'config/db.php';
$r = $conn->query('SHOW CREATE TABLE kendaraan');
$row = $r->fetch_row();
echo $row[1];
?>
