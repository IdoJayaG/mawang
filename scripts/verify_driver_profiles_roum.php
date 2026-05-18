<?php
require_once __DIR__ . '/../config/db.php';

$sql = "SELECT ua.username, p.nama_lengkap, p.nrp_nip, p.pangkat, p.jabatan, p.jenis_personel, p.email, p.satuan_pns, p.kesatuan, p.korps, p.matra, p.alamat
        FROM pengguna p
        JOIN user_account ua ON ua.pengguna_id = p.id
        WHERE ua.username IN (
            'edy.s','ikhwan','marsudi','tarman','joko.s','mer.agus.tinus.calvin','purwanto','puput.y','sigit.permana','aminudin','rusdi','suhendra','turimin','tukiman','erik','sophan','arie.setiawan','isak','kodim','nemin','m.budi.s','suradi','jejen','nurfadli','lingga','mulyanto','asep.r','parman','didi.sariman','ujang.sugiana','budi.utomo','yudi.martono','mulyadi','totok'
        )
        ORDER BY ua.username";

$res = $conn->query($sql);

echo "=== VERIFIKASI PROFIL DRIVER ROUM ===\n\n";
echo "Jumlah data: " . $res->num_rows . "\n\n";

while ($r = $res->fetch_assoc()) {
    echo $r['username'] . " | " . $r['nama_lengkap'] . " | " . $r['nrp_nip'] . " | " . $r['pangkat'] . " | " . $r['jenis_personel'] . " | " . $r['jabatan'] . "\n";
}

echo "\n=== SAMPLE DETAIL ===\n";
$res2 = $conn->query("SELECT ua.username, p.email, p.alamat, p.satuan_pns, p.kesatuan FROM pengguna p JOIN user_account ua ON ua.pengguna_id = p.id WHERE ua.username IN ('edy.s','tarman','totok') ORDER BY ua.username");
while ($r = $res2->fetch_assoc()) {
    echo $r['username'] . "\n";
    echo "  email   : " . $r['email'] . "\n";
    echo "  alamat  : " . $r['alamat'] . "\n";
    echo "  satuan  : " . $r['satuan_pns'] . "\n";
    echo "  kesatuan: " . $r['kesatuan'] . "\n\n";
}

$res3 = $conn->query("SELECT pangkat, COUNT(*) c FROM pengguna p JOIN user_account ua ON ua.pengguna_id = p.id WHERE ua.username IN ('tarman','suhendra','turimin','arie.setiawan','nemin','jejen','nurfadli','parman','didi.sariman','totok') GROUP BY pangkat ORDER BY pangkat");
echo "=== DISTRIBUSI GOLONGAN PNS ===\n";
while ($r = $res3->fetch_assoc()) {
    echo $r['pangkat'] . ': ' . $r['c'] . "\n";
}
