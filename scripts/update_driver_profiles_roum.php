<?php
/**
 * Update profil driver sesuai permintaan:
 * - email
 * - nrp_nip
 * - jenis_personel
 * - pangkat/golongan
 * - jabatan (Pengemudi)
 * - satuan/kesatuan Setjen Kemhan
 * - alamat sekitar Cawang
 */

require_once __DIR__ . '/../config/db.php';
mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);

$drivers = [
    ['username' => 'edy.s', 'nama' => 'Edy S', 'label' => 'Serka'],
    ['username' => 'ikhwan', 'nama' => 'Ikhwan', 'label' => 'Honorer'],
    ['username' => 'marsudi', 'nama' => 'Marsudi', 'label' => 'Honorer'],
    ['username' => 'tarman', 'nama' => 'Tarman', 'label' => 'PNS'],
    ['username' => 'joko.s', 'nama' => 'Joko S', 'label' => 'Honorer'],
    ['username' => 'mer.agus.tinus.calvin', 'nama' => 'Mer Agus Tinus Calvin', 'label' => 'Kopka'],
    ['username' => 'purwanto', 'nama' => 'Purwanto', 'label' => 'Sertu'],
    ['username' => 'puput.y', 'nama' => 'Puput Y', 'label' => 'Sertu'],
    ['username' => 'sigit.permana', 'nama' => 'Sigit Permana', 'label' => 'Serma'],
    ['username' => 'aminudin', 'nama' => 'Aminudin', 'label' => 'Kopka'],
    ['username' => 'rusdi', 'nama' => 'Rusdi', 'label' => 'Peltu'],
    ['username' => 'suhendra', 'nama' => 'Suhendra', 'label' => 'PNS'],
    ['username' => 'turimin', 'nama' => 'Turimin', 'label' => 'PNS'],
    ['username' => 'tukiman', 'nama' => 'Tukiman', 'label' => 'Pelda'],
    ['username' => 'erik', 'nama' => 'Erik', 'label' => 'PPPK'],
    ['username' => 'sophan', 'nama' => 'Sophan', 'label' => 'Serka'],
    ['username' => 'arie.setiawan', 'nama' => 'Arie Setiawan', 'label' => 'PNS'],
    ['username' => 'isak', 'nama' => 'Isak', 'label' => 'Honorer'],
    ['username' => 'kodim', 'nama' => 'Kodim', 'label' => 'Honorer'],
    ['username' => 'nemin', 'nama' => 'Nemin', 'label' => 'PNS'],
    ['username' => 'm.budi.s', 'nama' => 'M. Budi S', 'label' => 'Sertu'],
    ['username' => 'suradi', 'nama' => 'Suradi', 'label' => 'Honorer'],
    ['username' => 'jejen', 'nama' => 'Jejen', 'label' => 'PNS'],
    ['username' => 'nurfadli', 'nama' => 'Nurfadli', 'label' => 'PNS'],
    ['username' => 'lingga', 'nama' => 'Lingga', 'label' => 'Kopka'],
    ['username' => 'mulyanto', 'nama' => 'Mulyanto', 'label' => 'Serma'],
    ['username' => 'asep.r', 'nama' => 'Asep R', 'label' => 'Honorer'],
    ['username' => 'parman', 'nama' => 'Parman', 'label' => 'PNS'],
    ['username' => 'didi.sariman', 'nama' => 'Didi Sariman', 'label' => 'PNS'],
    ['username' => 'ujang.sugiana', 'nama' => 'Ujang Sugiana', 'label' => 'Serka'],
    ['username' => 'budi.utomo', 'nama' => 'Budi Utomo', 'label' => 'Pelda'],
    ['username' => 'yudi.martono', 'nama' => 'Yudi Martono', 'label' => 'Sertu'],
    ['username' => 'mulyadi', 'nama' => 'Mulyadi', 'label' => 'Honorer'],
    ['username' => 'totok', 'nama' => 'Totok', 'label' => 'PNS'],
];

$alamatSekitarCawang = [
    'Jl. Mayjen Sutoyo No. 12, Cawang, Kramat Jati, Jakarta Timur',
    'Jl. Dewi Sartika No. 44, Cawang, Kramat Jati, Jakarta Timur',
    'Jl. Raya Kalibata No. 71, Rawajati, Pancoran, Jakarta Selatan',
    'Jl. MT Haryono No. 20, Cawang, Kramat Jati, Jakarta Timur',
    'Jl. UKI Cawang No. 8, Cawang, Kramat Jati, Jakarta Timur',
    'Jl. Cililitan Besar No. 15, Cililitan, Kramat Jati, Jakarta Timur',
    'Jl. Otista Raya No. 98, Bidara Cina, Jatinegara, Jakarta Timur',
    'Jl. DI Panjaitan No. 33, Cipinang Cempedak, Jatinegara, Jakarta Timur',
    'Jl. Tebet Barat Dalam No. 14, Tebet, Jakarta Selatan',
    'Jl. Kebon Nanas Selatan No. 9, Cipinang Cempedak, Jakarta Timur',
    'Jl. Kampung Melayu Besar No. 22, Kampung Melayu, Jakarta Timur',
    'Jl. PGC Cililitan No. 5, Cililitan, Kramat Jati, Jakarta Timur',
    'Jl. Raya Condet No. 40, Balekambang, Kramat Jati, Jakarta Timur',
    'Jl. Haji Darip No. 18, Cawang, Kramat Jati, Jakarta Timur',
    'Jl. Batu Ampar III No. 21, Batu Ampar, Kramat Jati, Jakarta Timur',
    'Jl. Pahlawan Revolusi No. 11, Pondok Bambu, Duren Sawit, Jakarta Timur',
    'Jl. RS Fatmawati Lama No. 3, Cawang Baru, Jakarta Timur',
    'Jl. SMPN 49 No. 26, Cililitan, Jakarta Timur',
    'Jl. Kramat Asem No. 34, Utan Kayu Selatan, Matraman, Jakarta Timur',
    'Jl. Pramuka Sari III No. 12, Rawasari, Cempaka Putih, Jakarta Pusat',
    'Jl. Gelong Baru Timur No. 27, Palmerah, Jakarta Barat',
    'Jl. Cipinang Muara Raya No. 46, Jatinegara, Jakarta Timur',
    'Jl. Penas Kalimalang No. 18, Cipinang Melayu, Makasar, Jakarta Timur',
    'Jl. Cawang Baru Tengah No. 6, Cawang, Kramat Jati, Jakarta Timur',
    'Jl. Raya Bogor KM 4 No. 10, Cawang, Kramat Jati, Jakarta Timur',
    'Jl. Halim Perdanakusuma No. 13, Makasar, Jakarta Timur',
    'Jl. Balai Rakyat No. 25, Utan Kayu Utara, Matraman, Jakarta Timur',
    'Jl. Jatinegara Barat No. 60, Bali Mester, Jatinegara, Jakarta Timur',
    'Jl. Ciliwung I No. 7, Cawang, Kramat Jati, Jakarta Timur',
    'Jl. Cililitan Kecil No. 19, Cililitan, Kramat Jati, Jakarta Timur',
    'Jl. Angkasa Dalam No. 4, Halim Perdanakusuma, Jakarta Timur',
    'Jl. Smesco Dalam No. 9, Pancoran, Jakarta Selatan',
    'Jl. Percetakan Negara II No. 21, Johar Baru, Jakarta Pusat',
    'Jl. Pintu Air Kalimalang No. 3, Cipinang Melayu, Jakarta Timur',
];

function tniPangkatToNrpPrefix($pangkat)
{
    $map = [
        'Serka' => '3196',
        'Sertu' => '3197',
        'Serma' => '3198',
        'Kopka' => '3199',
        'Peltu' => '3200',
        'Pelda' => '3201',
    ];
    return $map[$pangkat] ?? '3299';
}

function pnsGolonganByIndex($index)
{
    $gol = ['Golongan II/a', 'Golongan II/b', 'Golongan II/c', 'Golongan II/d'];
    return $gol[$index % count($gol)];
}

try {
    $conn->begin_transaction();

    // Pastikan enum jabatan mendukung nilai "Pengemudi"
    $colRes = $conn->query("SHOW COLUMNS FROM pengguna LIKE 'jabatan'");
    $col = $colRes->fetch_assoc();
    $columnType = $col ? $col['Type'] : '';

    if (stripos($columnType, "'Pengemudi'") === false) {
        $conn->query("ALTER TABLE pengguna MODIFY jabatan ENUM('Baur Spri Kapus','Baurku','Baurtarlat','Bidduk TI','Caraka','Kabidduk TI','Kabidinfomin','Kabidinfoops','Kabidpamsisfo','Kapusinfolahta TNI','Kasubbid Jarkomta & Duknis','Kasubbid Pam Aplikasi','Kasubbid Pam jarkomta','Kasubbid SDM TI','Kasubbid Sisfogarku','Kasubbid Sisfointel','Kasubbid Sisfoopslat','Kasubbid Sisfopers','Kasubbid Sisfoter','Kataud','Kaurdal','Kaurpers','Kaurtu','Tamudi Kapus','Wakapusinfolahta TNI','Pengemudi') DEFAULT NULL");
        echo "✓ Enum jabatan ditambah nilai 'Pengemudi'\n";
    }

    $stmtFind = $conn->prepare("SELECT p.id, ua.username FROM pengguna p JOIN user_account ua ON ua.pengguna_id = p.id WHERE ua.username = ? LIMIT 1");
    $stmtUpdate = $conn->prepare(
        "UPDATE pengguna
         SET nrp_nip = ?, nama_lengkap = ?, pangkat = ?, jabatan = 'Pengemudi', satuan_pns = ?, email = ?, alamat = ?,
             status_pegawai = 'Aktif', status_aktif = 'Aktif', jenis_personel = ?, matra = ?, korps = ?, kesatuan = ?
         WHERE id = ?"
    );

    $pnsCounter = 0;
    $updated = 0;
    $idxAlamat = 0;

    foreach ($drivers as $i => $d) {
        $username = $d['username'];
        $nama = $d['nama'];
        $label = $d['label'];

        $stmtFind->bind_param('s', $username);
        $stmtFind->execute();
        $found = $stmtFind->get_result()->fetch_assoc();

        if (!$found) {
            echo "⊘ Username tidak ditemukan: {$username}\n";
            continue;
        }

        $penggunaId = (int)$found['id'];
        $email = $username . '@setjen.kemhan.go.id';
        $alamat = $alamatSekitarCawang[$idxAlamat % count($alamatSekitarCawang)];
        $idxAlamat++;

        $satuan = 'Sekretariat Jenderal Kementerian Pertahanan';
        $kesatuan = 'Sekretariat Jenderal Kementerian Pertahanan';
        $korps = 'ROUM Kemhan';

        if (in_array($label, ['Serka', 'Sertu', 'Serma', 'Kopka', 'Peltu', 'Pelda'], true)) {
            $jenisPersonel = 'TNI';
            $pangkat = $label;
            $nrpNip = tniPangkatToNrpPrefix($label) . str_pad((string)($i + 1), 6, '0', STR_PAD_LEFT);
            $matra = 'TNI AD';
        } else {
            // PNS, Honorer, PPPK dipetakan ke personel sipil/PNS
            $jenisPersonel = 'PNS';
            $golongan = pnsGolonganByIndex($pnsCounter);
            $pnsCounter++;

            if ($label === 'PNS') {
                $pangkat = $golongan;
                $nrpNip = '1978' . str_pad((string)($i + 1), 2, '0', STR_PAD_LEFT) . '2005011' . str_pad((string)($pnsCounter), 3, '0', STR_PAD_LEFT);
            } elseif ($label === 'PPPK') {
                $pangkat = 'PPPK';
                $nrpNip = 'PPPK-' . str_pad((string)($i + 1), 5, '0', STR_PAD_LEFT);
            } else {
                $pangkat = 'Honorer';
                $nrpNip = 'HON-' . str_pad((string)($i + 1), 5, '0', STR_PAD_LEFT);
            }
            $matra = 'ASN Kemhan';
        }

        $stmtUpdate->bind_param(
            'ssssssssssi',
            $nrpNip,
            $nama,
            $pangkat,
            $satuan,
            $email,
            $alamat,
            $jenisPersonel,
            $matra,
            $korps,
            $kesatuan,
            $penggunaId
        );
        $stmtUpdate->execute();

        echo "✓ {$username} | {$nama} | {$label} | {$nrpNip} | {$pangkat}\n";
        $updated++;
    }

    $stmtFind->close();
    $stmtUpdate->close();

    $conn->commit();

    echo "\n=== HASIL UPDATE PROFIL DRIVER ===\n";
    echo "Total diperbarui: {$updated}\n";
    echo "Email domain: @setjen.kemhan.go.id\n";
    echo "Jabatan: Pengemudi\n";
    echo "Kesatuan/Satuan: Sekretariat Jenderal Kementerian Pertahanan\n";

} catch (Exception $e) {
    $conn->rollback();
    echo "ERROR: " . $e->getMessage() . "\n";
    exit(1);
} finally {
    $conn->close();
}
