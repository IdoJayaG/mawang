<?php
// Queue H-1 maintenance reminder emails for admin, pimpinan, and driver.
// Intended to run periodically via Task Scheduler/cron.

chdir(__DIR__ . '/..');
require_once 'config.php';
require_once 'config/db.php';

function table_exists_local(mysqli $db, $table)
{
    $safe = $db->real_escape_string((string)$table);
    $res = $db->query("SHOW TABLES LIKE '{$safe}'");
    return $res && $res->num_rows > 0;
}

if (!table_exists_local($mysqli, 'email_reminder_jobs')) {
    fwrite(STDERR, "Table email_reminder_jobs belum ada. Jalankan migration terlebih dahulu.\n");
    exit(1);
}

if (!table_exists_local($mysqli, 'jadwal_perawatan')) {
    echo "Table jadwal_perawatan tidak ditemukan. Tidak ada reminder yang di-queue.\n";
    exit(0);
}

// Find schedule date column variant.
$dateCol = null;
foreach (['tanggal_perawatan', 'jadwal_tanggal'] as $c) {
    $q = $mysqli->query("SHOW COLUMNS FROM jadwal_perawatan LIKE '{$c}'");
    if ($q && $q->num_rows > 0) {
        $dateCol = $c;
        break;
    }
}

if ($dateCol === null) {
    fwrite(STDERR, "Kolom tanggal jadwal perawatan tidak ditemukan.\n");
    exit(1);
}

$sqlJadwal = "
    SELECT jp.id, jp.kendaraan_id, jp.jenis_perawatan, jp.deskripsi, jp.status, jp.{$dateCol} AS tanggal_jadwal,
        k.no_reg, k.no_polisi, k.merk, k.tipe
    FROM jadwal_perawatan jp
    LEFT JOIN kendaraan k ON k.id = jp.kendaraan_id
    WHERE DATE(jp.{$dateCol}) = DATE(DATE_ADD(CURDATE(), INTERVAL 1 DAY))
      AND (jp.status IS NULL OR LOWER(jp.status) NOT IN ('selesai', 'dibatalkan'))
";
$resJadwal = $mysqli->query($sqlJadwal);
if (!$resJadwal) {
        fwrite(STDERR, "Gagal mengambil data jadwal: {$mysqli->error}\n");
        exit(1);
}

$resJadwal = $mysqli->query($sqlJadwal);
if (!$resJadwal) {
    fwrite(STDERR, "Gagal mengambil data jadwal: {$mysqli->error}\n");
    exit(1);
}

// Recipients: active users with admin/pimpinan/driver role and valid email.
$sqlRecipients = "
    SELECT p.id AS pengguna_id, p.nama_lengkap, p.email, UPPER(r.kode_role) AS kode_role
    FROM pengguna p
    JOIN user_account ua ON ua.pengguna_id = p.id
    JOIN role r ON r.id = ua.role_id
    WHERE ua.status = 'Aktif'
      AND p.status_aktif = 'Aktif'
      AND p.email IS NOT NULL
      AND p.email <> ''
      AND UPPER(r.kode_role) IN ('ADMIN','PIMPINAN','DRIVER')
";

$resRecipients = $mysqli->query($sqlRecipients);
if (!$resRecipients) {
    fwrite(STDERR, "Gagal mengambil penerima email: {$mysqli->error}\n");
    exit(1);
}

$recipients = [];
while ($r = $resRecipients->fetch_assoc()) {
    if (!filter_var($r['email'], FILTER_VALIDATE_EMAIL)) {
        continue;
    }
    $recipients[] = $r;
}

if (empty($recipients)) {
    echo "Tidak ada penerima valid untuk reminder.\n";
    exit(0);
}

$inserted = 0;
$skipped = 0;

$ins = $mysqli->prepare("INSERT IGNORE INTO email_reminder_jobs (recipient_email, recipient_name, subject, body_html, body_text, send_at, status, source_type, source_key, created_at, updated_at) VALUES (?, ?, ?, ?, ?, ?, 'pending', ?, ?, NOW(), NOW())");
if (!$ins) {
    fwrite(STDERR, "Prepare insert queue gagal: {$mysqli->error}\n");
    exit(1);
}

while ($j = $resJadwal->fetch_assoc()) {
    $tanggal = (string)$j['tanggal_jadwal'];
    $tanggalView = date('d/m/Y', strtotime($tanggal));
    $namaKendaraan = trim((string)($j['no_reg'] ?: $j['no_polisi']));
    if ($namaKendaraan === '') {
        $namaKendaraan = trim((string)($j['merk'] . ' ' . $j['tipe']));
    }

    $jenis = trim((string)($j['jenis_perawatan'] ?? '-'));
    $desk = trim((string)($j['deskripsi'] ?? '-'));

    // Schedule send at 07:00 local one day before.
    $sendAt = date('Y-m-d 07:00:00', strtotime($tanggal . ' -1 day'));
    if (strtotime($sendAt) < time()) {
        $sendAt = date('Y-m-d H:i:s');
    }

    // Build per-schedule specific recipients (teknisi, assigned driver) and merge with role-based recipients
    // We'll iterate over a merged recipient list per schedule to ensure vehicle-specific recipients are included.
    // Prepare some helper statements for fetching teknisi and vehicle driver emails
    $stmt_get_kend_pengguna = $mysqli->prepare("SELECT pengguna_id FROM kendaraan WHERE id = ? LIMIT 1");
    $stmt_get_teknisi = $mysqli->prepare("SELECT p.id, p.nama_lengkap, p.email FROM jadwal_perawatan jp LEFT JOIN pengguna p ON jp.teknisi_id = p.id WHERE jp.id = ? AND p.email IS NOT NULL AND p.email <> '' LIMIT 1");
    $stmt_get_user = $mysqli->prepare("SELECT id, nama_lengkap, email FROM pengguna WHERE id = ? AND email IS NOT NULL AND email <> '' LIMIT 1");
    $stmt_get_drivers = $mysqli->prepare("SELECT DISTINCT p.id, p.nama_lengkap, p.email FROM surat_tugas s JOIN pengguna p ON s.pengguna_id = p.id WHERE s.kendaraan_id = ? AND s.status IN ('Disetujui','Dalam Perjalanan') AND p.email IS NOT NULL AND p.email <> ''");

        while ($j = $resJadwal->fetch_assoc()) {
            $tanggal = (string)$j['tanggal_jadwal'];
            $tanggalView = date('d/m/Y', strtotime($tanggal));
            $namaKendaraan = trim((string)($j['no_reg'] ?: $j['no_polisi']));
            if ($namaKendaraan === '') {
                $namaKendaraan = trim((string)($j['merk'] . ' ' . $j['tipe']));
            }

            $jenis = trim((string)($j['jenis_perawatan'] ?? '-'));
            $desk = trim((string)($j['deskripsi'] ?? '-'));

            // Schedule send at 07:00 local one day before.
            $sendAt = date('Y-m-d 07:00:00', strtotime($tanggal . ' -1 day'));
            if (strtotime($sendAt) < time()) {
                $sendAt = date('Y-m-d H:i:s');
            }

            // Build merged recipients: start with role-based ones
            $perRecipients = $recipients;

            // 1) kendaraan assigned pengguna (driver)
            $kendId = (int)($j['kendaraan_id'] ?? 0);
            if ($kendId > 0 && $stmt_get_kend_pengguna) {
                $stmt_get_kend_pengguna->bind_param('i', $kendId);
                $stmt_get_kend_pengguna->execute();
                $rkp = $stmt_get_kend_pengguna->get_result()->fetch_assoc();
                $stmt_get_kend_pengguna->close();
                if (!empty($rkp['pengguna_id'])) {
                    $pg = (int)$rkp['pengguna_id'];
                    if ($stmt_get_user) {
                        $stmt_get_user->bind_param('i', $pg);
                        $stmt_get_user->execute();
                        $pu = $stmt_get_user->get_result()->fetch_assoc();
                        $stmt_get_user->close();
                        if ($pu && filter_var($pu['email'] ?? '', FILTER_VALIDATE_EMAIL)) {
                            $perRecipients[] = ['pengguna_id' => $pu['id'], 'nama_lengkap' => $pu['nama_lengkap'], 'email' => $pu['email'], 'kode_role' => 'DRIVER'];
                        }
                    }
                }
            }

            // 2) teknisi assigned to this jadwal (if column exists and email present)
            try {
                $colChk = $mysqli->query("SHOW COLUMNS FROM jadwal_perawatan LIKE 'teknisi_id'");
                if ($colChk && $colChk->num_rows > 0 && $stmt_get_teknisi) {
                    $stmt_get_teknisi->bind_param('i', $j['id']);
                    $stmt_get_teknisi->execute();
                    $tk = $stmt_get_teknisi->get_result()->fetch_assoc();
                    $stmt_get_teknisi->close();
                    if ($tk && filter_var($tk['email'] ?? '', FILTER_VALIDATE_EMAIL)) {
                        $perRecipients[] = ['pengguna_id' => $tk['id'], 'nama_lengkap' => $tk['nama_lengkap'], 'email' => $tk['email'], 'kode_role' => 'TEKNISI'];
                    }
                }
            } catch (Throwable $e) { /* ignore */ }

            // 3) drivers found via surat_tugas for this vehicle
            if ($kendId > 0 && $stmt_get_drivers) {
                $stmt_get_drivers->bind_param('i', $kendId);
                $stmt_get_drivers->execute();
                $rdr = $stmt_get_drivers->get_result();
                while ($drow = $rdr->fetch_assoc()) {
                    if (!empty($drow['email']) && filter_var($drow['email'], FILTER_VALIDATE_EMAIL)) {
                        $perRecipients[] = ['pengguna_id' => $drow['id'], 'nama_lengkap' => $drow['nama_lengkap'], 'email' => $drow['email'], 'kode_role' => 'DRIVER'];
                    }
                }
                $stmt_get_drivers->close();
            }

            // Now insert queue items for the merged recipients for this specific jadwal
            foreach ($perRecipients as $rc) {
                $subject = "Pengingat Perawatan Kendaraan {$tanggalView}";
                $html = '<p>Yth. ' . htmlspecialchars((string)($rc['nama_lengkap'] ?? ''), ENT_QUOTES, 'UTF-8') . ',</p>'
                      . '<p>Ini adalah pengingat otomatis dari sistem bahwa terdapat jadwal perawatan kendaraan pada <strong>' . htmlspecialchars($tanggalView, ENT_QUOTES, 'UTF-8') . '</strong>.</p>'
                      . '<ul>'
                      . '<li>Kendaraan: ' . htmlspecialchars((string)$namaKendaraan, ENT_QUOTES, 'UTF-8') . '</li>'
                      . '<li>Jenis Perawatan: ' . htmlspecialchars($jenis, ENT_QUOTES, 'UTF-8') . '</li>'
                      . '<li>Deskripsi: ' . htmlspecialchars($desk, ENT_QUOTES, 'UTF-8') . '</li>'
                      . '</ul>'
                      . '<p>Pesan ini dikirim otomatis oleh sistem Randis.</p>';

                $text = "Yth. " . ($rc['nama_lengkap'] ?? '') . "\n\n"
                      . "Pengingat otomatis: terdapat jadwal perawatan kendaraan pada {$tanggalView}.\n"
                      . "Kendaraan: {$namaKendaraan}\n"
                      . "Jenis Perawatan: {$jenis}\n"
                      . "Deskripsi: {$desk}\n\n"
                      . "Pesan ini dikirim otomatis oleh sistem Randis.";

                $sourceType = 'jadwal_perawatan_h1';
                $sourceKey = (string)$j['id'];

                $email = (string)($rc['email'] ?? '');
                $name = (string)($rc['nama_lengkap'] ?? '');
                if (!filter_var($email, FILTER_VALIDATE_EMAIL)) continue;
                $ins->bind_param('ssssssss', $email, $name, $subject, $html, $text, $sendAt, $sourceType, $sourceKey);
                $ins->execute();

                if ($ins->affected_rows > 0) {
                    $inserted++;
                } else {
                    $skipped++;
                }
            }
        }

        // Close prepared helpers if still open
        if ($stmt_get_kend_pengguna) { @$stmt_get_kend_pengguna->close(); }
        if ($stmt_get_teknisi) { @$stmt_get_teknisi->close(); }
        if ($stmt_get_user) { @$stmt_get_user->close(); }
        if ($stmt_get_drivers) { @$stmt_get_drivers->close(); }

    while ($j = $resJadwal->fetch_assoc()) {
        $tanggal = (string)$j['tanggal_jadwal'];
        $tanggalView = date('d/m/Y', strtotime($tanggal));
        $namaKendaraan = trim((string)($j['no_reg'] ?: $j['no_polisi']));
        if ($namaKendaraan === '') {
            $namaKendaraan = trim((string)($j['merk'] . ' ' . $j['tipe']));
        }

        $jenis = trim((string)($j['jenis_perawatan'] ?? '-'));
        $desk = trim((string)($j['deskripsi'] ?? '-'));

        // Schedule send at 07:00 local one day before.
        $sendAt = date('Y-m-d 07:00:00', strtotime($tanggal . ' -1 day'));
        if (strtotime($sendAt) < time()) {
            $sendAt = date('Y-m-d H:i:s');
        }

        // Build merged recipients: start with role-based ones
        $perRecipients = $recipients;

        // 1) kendaraan assigned pengguna (driver)
        $kendId = (int)($j['kendaraan_id'] ?? 0);
        if ($kendId > 0 && $stmt_get_kend_pengguna) {
            $stmt_get_kend_pengguna->bind_param('i', $kendId);
            $stmt_get_kend_pengguna->execute();
            $rkp = $stmt_get_kend_pengguna->get_result()->fetch_assoc();
            $stmt_get_kend_pengguna->close();
            if (!empty($rkp['pengguna_id'])) {
                $pg = (int)$rkp['pengguna_id'];
                if ($stmt_get_user) {
                    $stmt_get_user->bind_param('i', $pg);
                    $stmt_get_user->execute();
                    $pu = $stmt_get_user->get_result()->fetch_assoc();
                    $stmt_get_user->close();
                    if ($pu && filter_var($pu['email'] ?? '', FILTER_VALIDATE_EMAIL)) {
                        $perRecipients[] = ['pengguna_id' => $pu['id'], 'nama_lengkap' => $pu['nama_lengkap'], 'email' => $pu['email'], 'kode_role' => 'DRIVER'];
                    }
                }
            }
        }

        // 2) teknisi assigned to this jadwal (if column exists and email present)
        try {
            $colChk = $mysqli->query("SHOW COLUMNS FROM jadwal_perawatan LIKE 'teknisi_id'");
            if ($colChk && $colChk->num_rows > 0 && $stmt_get_teknisi) {
                $stmt_get_teknisi->bind_param('i', $j['id']);
                $stmt_get_teknisi->execute();
                $tk = $stmt_get_teknisi->get_result()->fetch_assoc();
                $stmt_get_teknisi->close();
                if ($tk && filter_var($tk['email'] ?? '', FILTER_VALIDATE_EMAIL)) {
                    $perRecipients[] = ['pengguna_id' => $tk['id'], 'nama_lengkap' => $tk['nama_lengkap'], 'email' => $tk['email'], 'kode_role' => 'TEKNISI'];
                }
            }
        } catch (Throwable $e) { /* ignore */ }

        // 3) drivers found via surat_tugas for this vehicle
        if ($kendId > 0 && $stmt_get_drivers) {
            $stmt_get_drivers->bind_param('i', $kendId);
            $stmt_get_drivers->execute();
            $rdr = $stmt_get_drivers->get_result();
            while ($drow = $rdr->fetch_assoc()) {
                if (!empty($drow['email']) && filter_var($drow['email'], FILTER_VALIDATE_EMAIL)) {
                    $perRecipients[] = ['pengguna_id' => $drow['id'], 'nama_lengkap' => $drow['nama_lengkap'], 'email' => $drow['email'], 'kode_role' => 'DRIVER'];
                }
            }
            $stmt_get_drivers->close();
        }

        // Now insert queue items for the merged recipients for this specific jadwal
        foreach ($perRecipients as $rc) {
            $subject = "Pengingat Perawatan Kendaraan {$tanggalView}";
            $html = '<p>Yth. ' . htmlspecialchars((string)($rc['nama_lengkap'] ?? ''), ENT_QUOTES, 'UTF-8') . ',</p>'
                  . '<p>Ini adalah pengingat otomatis dari sistem bahwa terdapat jadwal perawatan kendaraan pada <strong>' . htmlspecialchars($tanggalView, ENT_QUOTES, 'UTF-8') . '</strong>.</p>'
                  . '<ul>'
                  . '<li>Kendaraan: ' . htmlspecialchars((string)$namaKendaraan, ENT_QUOTES, 'UTF-8') . '</li>'
                  . '<li>Jenis Perawatan: ' . htmlspecialchars($jenis, ENT_QUOTES, 'UTF-8') . '</li>'
                  . '<li>Deskripsi: ' . htmlspecialchars($desk, ENT_QUOTES, 'UTF-8') . '</li>'
                  . '</ul>'
                  . '<p>Pesan ini dikirim otomatis oleh sistem Randis.</p>';

            $text = "Yth. " . ($rc['nama_lengkap'] ?? '') . "\n\n"
                  . "Pengingat otomatis: terdapat jadwal perawatan kendaraan pada {$tanggalView}.\n"
                  . "Kendaraan: {$namaKendaraan}\n"
                  . "Jenis Perawatan: {$jenis}\n"
                  . "Deskripsi: {$desk}\n\n"
                  . "Pesan ini dikirim otomatis oleh sistem Randis.";

            $sourceType = 'jadwal_perawatan_h1';
            $sourceKey = (string)$j['id'];

            $email = (string)($rc['email'] ?? '');
            $name = (string)($rc['nama_lengkap'] ?? '');
            if (!filter_var($email, FILTER_VALIDATE_EMAIL)) continue;
            $ins->bind_param('ssssssss', $email, $name, $subject, $html, $text, $sendAt, $sourceType, $sourceKey);
            $ins->execute();

            if ($ins->affected_rows > 0) {
                $inserted++;
            } else {
                $skipped++;
            }
        }
    }

    // Close prepared helpers if still open
    if ($stmt_get_kend_pengguna) { @$stmt_get_kend_pengguna->close(); }
    if ($stmt_get_teknisi) { @$stmt_get_teknisi->close(); }
    if ($stmt_get_user) { @$stmt_get_user->close(); }
    if ($stmt_get_drivers) { @$stmt_get_drivers->close(); }
}

$ins->close();

echo "Queue reminder selesai. Inserted={$inserted}, Skipped={$skipped}\n";
