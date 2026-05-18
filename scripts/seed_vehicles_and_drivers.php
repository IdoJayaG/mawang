<?php
/**
 * Script untuk menambah data kendaraan dan pengguna driver
 * Berdasarkan nomor registrasi dari gambar yang disediakan
 */

// Koneksi database
require_once __DIR__ . '/../config/db.php';

// Set error reporting
mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);

try {
    // Data kendaraan dan driver berdasarkan urutan gambar
    $data = [
        ['no_reg' => '7775-00', 'nama_driver' => 'Serka Edy S', 'pengguna_nama' => 'Ajp 01 Serka Edy S'],
        ['no_reg' => '7722-00', 'nama_driver' => 'Honorer Ikhwan', 'pengguna_nama' => 'Ajp 02 Honorer Ikhwan'],
        ['no_reg' => '7781-00', 'nama_driver' => 'Honorer Marsudi', 'pengguna_nama' => 'Ajp 03 Honorer Marsudi'],
        ['no_reg' => '7700-00', 'nama_driver' => 'PNS Tarman', 'pengguna_nama' => 'Ajp 05 PNS Tarman'],
        ['no_reg' => '7701-00', 'nama_driver' => 'Honorer Joko S', 'pengguna_nama' => 'Ajp 06 Honorer Joko S'],
        ['no_reg' => '7702-00', 'nama_driver' => 'Kopka Mer Agus Tinus Calvin', 'pengguna_nama' => 'Ajp 07 Kopka Mer Agus Tinus Calvin'],
        ['no_reg' => '7713-00', 'nama_driver' => 'Sertu Purwanto', 'pengguna_nama' => 'Ajp 08 Sertu Purwanto'],
        ['no_reg' => '7782-00', 'nama_driver' => 'Sertu Puput Y', 'pengguna_nama' => 'Ajp 09 Sertu Puput Y'],
        ['no_reg' => '7703-00', 'nama_driver' => 'Serma Sigit Permana', 'pengguna_nama' => 'Ajp 10 Serma Sigit Permana'],
        ['no_reg' => '7706-00', 'nama_driver' => 'Kopka Aminudin', 'pengguna_nama' => 'Ajp 11 Kopka Aminudin'],
        ['no_reg' => '7707-00', 'nama_driver' => 'Peltu Rusdi', 'pengguna_nama' => 'Ajp 12 Peltu Rusdi'],
        ['no_reg' => '7708-00', 'nama_driver' => 'PNS Suhendra', 'pengguna_nama' => 'Ajp 13 PNS Suhendra'],
        ['no_reg' => '7709-00', 'nama_driver' => 'PNS Turimin', 'pengguna_nama' => 'Ajp 14 PNS Turimin'],
        ['no_reg' => '7720-00', 'nama_driver' => 'Pelda Tukiman', 'pengguna_nama' => 'Ajp 15 Pelda Tukiman'],
        ['no_reg' => '7711-00', 'nama_driver' => 'PPPK Erik', 'pengguna_nama' => 'Ajp 16 PPPK Erik'],
        ['no_reg' => '7698-00', 'nama_driver' => 'Serka Sophan', 'pengguna_nama' => 'Ajp 17 Serka Sophan'],
        ['no_reg' => '7710-00', 'nama_driver' => 'PNS Arie Setiawan', 'pengguna_nama' => 'Ajp 18 PNS Arie Setiawan'],
        ['no_reg' => '7717-00', 'nama_driver' => 'Honorer Isak', 'pengguna_nama' => 'Ajp 19 Honorer Isak'],
        ['no_reg' => '7715-00', 'nama_driver' => 'Honorer Kodim', 'pengguna_nama' => 'Ajp 20 Honorer Kodim'],
        ['no_reg' => '7696-00', 'nama_driver' => 'PNS Nemin', 'pengguna_nama' => 'Ajp 22 PNS Nemin'],
        ['no_reg' => '7718-00', 'nama_driver' => 'Sertu M. Budi S', 'pengguna_nama' => 'Ajp 23 Sertu M. Budi S'],
        ['no_reg' => '7719-00', 'nama_driver' => 'Honorer Suradi', 'pengguna_nama' => 'Ajp 24 Honorer Suradi'],
        ['no_reg' => '7705-00', 'nama_driver' => 'PNS Jejen', 'pengguna_nama' => 'Ajp 25 PNS Jejen'],
        ['no_reg' => '7721-00', 'nama_driver' => 'PNS Nurfadli', 'pengguna_nama' => 'Ajp 26 PNS Nurfadli'],
        ['no_reg' => '7690-00', 'nama_driver' => 'Kopka Lingga', 'pengguna_nama' => 'Ajp 27 Kopka Lingga'],
        ['no_reg' => '7723-00', 'nama_driver' => 'Serma Mulyanto', 'pengguna_nama' => 'Ajp 28 Serma Mulyanto'],
        ['no_reg' => '7803-00', 'nama_driver' => 'Honorer Asep R', 'pengguna_nama' => 'Ajp 29 Honorer Asep R'],
        ['no_reg' => '7802-00', 'nama_driver' => 'PNS Parman', 'pengguna_nama' => 'Ajp 30 PNS Parman'],
        ['no_reg' => '7704-00', 'nama_driver' => 'PNS Didi Sariman', 'pengguna_nama' => 'Ajp 32 PNS Didi Sariman'],
        ['no_reg' => '7804-00', 'nama_driver' => 'Serka Ujang Sugiana', 'pengguna_nama' => 'Ajp 33 Serka Ujang Sugiana'],
        ['no_reg' => '7724-00', 'nama_driver' => 'Pelda Budi Utomo', 'pengguna_nama' => 'Ajp 34 Pelda Budi Utomo'],
        ['no_reg' => '7726-00', 'nama_driver' => 'Sertu Yudi Martono', 'pengguna_nama' => 'Ajp 35 Sertu Yudi Martono'],
        ['no_reg' => '7714-00', 'nama_driver' => 'Honorer Mulyadi', 'pengguna_nama' => 'Ajp 36 (Denwalsus) Honorer Mulyadi'],
        ['no_reg' => '8891-00', 'nama_driver' => 'PNS Totok', 'pengguna_nama' => 'Truk Denwalsus PNS Totok'],
    ];

    // Mulai transaksi
    $conn->begin_transaction();

    // 1. Pastikan role 'driver' ada
    $resultRole = $conn->query("SELECT id FROM role WHERE nama_role = 'driver'");
    if ($resultRole->num_rows == 0) {
        $conn->query("INSERT INTO role (nama_role, deskripsi, created_at) VALUES ('driver', 'Pengemudi Kendaraan', NOW())");
        echo "✓ Role 'driver' dibuat\n";
    } else {
        echo "✓ Role 'driver' sudah ada\n";
    }

    // Ambil role_id untuk driver
    $resultRole = $conn->query("SELECT id FROM role WHERE nama_role = 'driver'");
    $roleDriver = $resultRole->fetch_assoc();
    $driverRoleId = $roleDriver['id'];

    // Counter untuk insert
    $insertCount = 0;
    $skipCount = 0;

    foreach ($data as $index => $item) {
        $no_reg = $item['no_reg'];
        $nama_driver = $item['nama_driver'];
        $pengguna_nama = $item['pengguna_nama'];

        // Cek apakah kendaraan sudah ada
        $checkVehicle = $conn->prepare("SELECT id FROM kendaraan WHERE no_reg = ?");
        $checkVehicle->bind_param("s", $no_reg);
        $checkVehicle->execute();
        $existingVehicle = $checkVehicle->get_result()->fetch_assoc();
        $checkVehicle->close();

        if ($existingVehicle) {
            echo "⊘ Kendaraan {$no_reg} sudah ada, skip\n";
            $skipCount++;
            continue;
        }

        // 1. Buat pengguna entry terlebih dahulu
        $username = strtolower(str_replace([' ', '(', ')'], ['.', '', ''], $pengguna_nama));
        $email = $username . '@driver.local';
        
        $insertPengguna = $conn->prepare(
            "INSERT INTO pengguna (nama_lengkap, email, status_pegawai, status_aktif, jenis_personel, created_at) 
             VALUES (?, ?, 'Aktif', 'Aktif', 'TNI', NOW())"
        );
        $insertPengguna->bind_param("ss", $pengguna_nama, $email);
        $insertPengguna->execute();
        $penggunaId = $insertPengguna->insert_id;
        $insertPengguna->close();

        // 2. Buat user_account entry dengan pengguna_id
        $passwordHash = password_hash('password123', PASSWORD_DEFAULT);
        
        $insertUser = $conn->prepare(
            "INSERT INTO user_account (pengguna_id, username, password, role_id, status, created_at) 
             VALUES (?, ?, ?, ?, 'Aktif', NOW())"
        );
        $insertUser->bind_param("issi", $penggunaId, $username, $passwordHash, $driverRoleId);
        $insertUser->execute();
        $insertUser->close();

        // 3. Buat kendaraan dengan pengguna_id = driver
        // Generate unique values for required fields
        $no_polisi = strtoupper(substr($no_reg, 0, 7) . str_pad((int)substr($no_reg, -2), 2, '0', STR_PAD_LEFT));
        $no_rangka = 'VIN' . substr(md5($no_reg . microtime()), 0, 20) . time();
        $no_mesin = 'ENGINE' . substr(md5($no_reg . microtime() . 'mesin'), 0, 20);
        
        $insertVehicle = $conn->prepare(
            "INSERT INTO kendaraan (no_polisi, no_rangka, no_mesin, no_reg, pengguna_id, merk, satker, status_kendaraan, kondisi, status_kepemilikan, jenis, bahan_bakar, penanggung_jawab, created_at) 
             VALUES (?, ?, ?, ?, ?, 'Isuzu', 'Kodim', 'Operasional', 'Baik', 'Satker', 'Roda 4', 'Solar', 'Baurku', NOW())"
        );
        $insertVehicle->bind_param("ssssi", $no_polisi, $no_rangka, $no_mesin, $no_reg, $penggunaId);
        $insertVehicle->execute();
        $insertVehicle->close();

        echo "✓ [{$index}] {$no_reg} → {$pengguna_nama} (user: {$username})\n";
        $insertCount++;
    }

    // Commit transaksi
    $conn->commit();

    echo "\n=== HASIL SEEDING ===\n";
    echo "Total diinput: {$insertCount}\n";
    echo "Total skip: {$skipCount}\n";
    echo "Total data: " . count($data) . "\n";
    echo "\nData kendaraan dan driver berhasil ditambahkan!\n";
    echo "Password default untuk semua driver: password123\n";

} catch (Exception $e) {
    // Rollback jika ada error
    $conn->rollback();
    echo "ERROR: " . $e->getMessage() . "\n";
    exit(1);
} finally {
    $conn->close();
}
?>
