<?php
// Queue H-1 and H (today) maintenance reminder emails for user and driver.
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

function safe_html($value)
{
    return htmlspecialchars((string)$value, ENT_QUOTES, 'UTF-8');
}

function fetch_user_by_id($stmt, $id)
{
    if (!$stmt || $id <= 0) return null;
    $stmt->bind_param('i', $id);
    if (!$stmt->execute()) return null;
    $res = $stmt->get_result();
    if (!$res) return null;
    $row = $res->fetch_assoc();
    $res->free();
    return $row ?: null;
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
        $q->free();
        break;
    }
    if ($q) $q->free();
}

if ($dateCol === null) {
    fwrite(STDERR, "Kolom tanggal jadwal perawatan tidak ditemukan.\n");
    exit(1);
}

$hasSuratTugas = table_exists_local($mysqli, 'surat_tugas');
$driverColExists = false;
if ($hasSuratTugas) {
    try {
        if ($col = $mysqli->query("SHOW COLUMNS FROM surat_tugas LIKE 'driver_id'")) {
            if ($col->num_rows > 0) $driverColExists = true;
            $col->free();
        }
    } catch (Throwable $e) {
        $driverColExists = false;
    }
}

$inserted = 0;
$skipped = 0;

$ins = $mysqli->prepare("INSERT IGNORE INTO email_reminder_jobs (recipient_email, recipient_name, subject, body_html, body_text, send_at, status, source_type, source_key, created_at, updated_at) VALUES (?, ?, ?, ?, ?, ?, 'pending', ?, ?, NOW(), NOW())");
if (!$ins) {
    fwrite(STDERR, "Prepare insert queue gagal: {$mysqli->error}\n");
    exit(1);
}

$sqlJadwal = "
    SELECT jp.id, jp.kendaraan_id, jp.jenis_perawatan, jp.deskripsi, jp.status, jp.{$dateCol} AS tanggal_jadwal,
           k.no_reg, k.no_polisi, k.merk, k.tipe
    FROM jadwal_perawatan jp
    LEFT JOIN kendaraan k ON k.id = jp.kendaraan_id
    WHERE DATE(jp.{$dateCol}) = ?
      AND (jp.status IS NULL OR LOWER(jp.status) NOT IN ('selesai', 'dibatalkan'))
";

$stmt_jadwal = $mysqli->prepare($sqlJadwal);
if (!$stmt_jadwal) {
    fwrite(STDERR, "Prepare jadwal_perawatan gagal: {$mysqli->error}\n");
    exit(1);
}

$stmt_get_kend_pengguna = $mysqli->prepare("SELECT pengguna_id FROM kendaraan WHERE id = ? LIMIT 1");
$stmt_get_user = $mysqli->prepare("SELECT id, nama_lengkap, email FROM pengguna WHERE id = ? AND email IS NOT NULL AND email <> '' LIMIT 1");
$stmt_get_drivers = null;
if ($hasSuratTugas) {
    $driverSql = $driverColExists
        ? "SELECT DISTINCT p.id, p.nama_lengkap, p.email FROM surat_tugas s JOIN pengguna p ON s.driver_id = p.id WHERE s.kendaraan_id = ? AND s.status IN ('Disetujui','Dalam Perjalanan') AND p.email IS NOT NULL AND p.email <> ''"
        : "SELECT DISTINCT p.id, p.nama_lengkap, p.email FROM surat_tugas s JOIN pengguna p ON s.pengguna_id = p.id WHERE s.kendaraan_id = ? AND s.status IN ('Disetujui','Dalam Perjalanan') AND p.email IS NOT NULL AND p.email <> ''";
    $stmt_get_drivers = $mysqli->prepare($driverSql);
}

$scenarios = [
    [
        'label' => 'H-1',
        'sourceType' => 'jadwal_perawatan_h1',
        'targetDate' => date('Y-m-d', strtotime('+1 day')),
        'dayLabel' => 'besok'
    ],
    [
        'label' => 'Hari H',
        'sourceType' => 'jadwal_perawatan_h0',
        'targetDate' => date('Y-m-d'),
        'dayLabel' => 'hari ini'
    ],
];

foreach ($scenarios as $scenario) {
    $stmt_jadwal->bind_param('s', $scenario['targetDate']);
    $stmt_jadwal->execute();
    $resJadwal = $stmt_jadwal->get_result();
    if (!$resJadwal) {
        fwrite(STDERR, "Gagal mengambil data jadwal: {$mysqli->error}\n");
        continue;
    }

    while ($j = $resJadwal->fetch_assoc()) {
        $tanggal = (string)$j['tanggal_jadwal'];
        $tanggalView = $tanggal !== '' ? date('d/m/Y', strtotime($tanggal)) : '-';
        $namaKendaraan = trim((string)($j['no_reg'] ?? ''));
        if ($namaKendaraan === '') $namaKendaraan = trim((string)($j['no_polisi'] ?? ''));
        if ($namaKendaraan === '') $namaKendaraan = trim((string)(($j['merk'] ?? '') . ' ' . ($j['tipe'] ?? '')));
        if ($namaKendaraan === '') $namaKendaraan = '-';

        $jenis = trim((string)($j['jenis_perawatan'] ?? '-'));
        $desk = trim((string)($j['deskripsi'] ?? '-'));
        $status = trim((string)($j['status'] ?? '-'));

        $sendAt = $scenario['label'] === 'H-1'
            ? date('Y-m-d 07:00:00', strtotime($tanggal . ' -1 day'))
            : date('Y-m-d 07:00:00', strtotime($tanggal));
        if (strtotime($sendAt) < time()) {
            $sendAt = date('Y-m-d H:i:s');
        }

        $perRecipients = [];
        $kendId = (int)($j['kendaraan_id'] ?? 0);

        // kendaraan assigned pengguna
        if ($kendId > 0 && $stmt_get_kend_pengguna) {
            $stmt_get_kend_pengguna->bind_param('i', $kendId);
            $stmt_get_kend_pengguna->execute();
            $rkpRes = $stmt_get_kend_pengguna->get_result();
            $rkp = $rkpRes ? $rkpRes->fetch_assoc() : null;
            if ($rkpRes) $rkpRes->free();
            if (!empty($rkp['pengguna_id'])) {
                $pg = (int)$rkp['pengguna_id'];
                $pu = fetch_user_by_id($stmt_get_user, $pg);
                if ($pu && filter_var($pu['email'] ?? '', FILTER_VALIDATE_EMAIL)) {
                    $perRecipients[] = ['pengguna_id' => $pu['id'], 'nama_lengkap' => $pu['nama_lengkap'], 'email' => $pu['email']];
                }
            }
        }

        // drivers found via surat_tugas for this vehicle
        if ($kendId > 0 && $stmt_get_drivers) {
            $stmt_get_drivers->bind_param('i', $kendId);
            $stmt_get_drivers->execute();
            $rdr = $stmt_get_drivers->get_result();
            if ($rdr) {
                while ($drow = $rdr->fetch_assoc()) {
                    if (!empty($drow['email']) && filter_var($drow['email'], FILTER_VALIDATE_EMAIL)) {
                        $perRecipients[] = ['pengguna_id' => $drow['id'], 'nama_lengkap' => $drow['nama_lengkap'], 'email' => $drow['email']];
                    }
                }
                $rdr->free();
            }
        }

        $subject = "Pengingat {$scenario['label']} Jadwal Perawatan/Perbaikan {$tanggalView}";
        $html = '<p>Yth. Bapak/Ibu,</p>'
            . '<p>Ini adalah pengingat otomatis untuk jadwal perawatan/perbaikan kendaraan yang dijadwalkan ' . safe_html($scenario['dayLabel']) . '.</p>'
            . '<ul>'
            . '<li>Tanggal: ' . safe_html($tanggalView) . '</li>'
            . '<li>Kendaraan: ' . safe_html($namaKendaraan) . '</li>'
            . '<li>Jenis Perawatan: ' . safe_html($jenis) . '</li>'
            . '<li>Deskripsi: ' . safe_html($desk) . '</li>'
            . '<li>Status: ' . safe_html($status) . '</li>'
            . '</ul>'
            . '<p>Pesan ini dikirim otomatis oleh sistem Randis.</p>';

        $text = "Pengingat {$scenario['label']}: jadwal perawatan/perbaikan dijadwalkan {$scenario['dayLabel']}.\n"
            . "Tanggal: {$tanggalView}\n"
            . "Kendaraan: {$namaKendaraan}\n"
            . "Jenis Perawatan: {$jenis}\n"
            . "Deskripsi: {$desk}\n"
            . "Status: {$status}\n\n"
            . "Pesan ini dikirim otomatis oleh sistem Randis.";

        $sourceType = $scenario['sourceType'];
        $sourceKey = (string)$j['id'];

        $seen = [];
        foreach ($perRecipients as $rc) {
            $email = strtolower(trim((string)($rc['email'] ?? '')));
            if ($email === '' || !filter_var($email, FILTER_VALIDATE_EMAIL)) continue;
            if (isset($seen[$email])) continue;
            $seen[$email] = true;

            $name = (string)($rc['nama_lengkap'] ?? '');
            $ins->bind_param('ssssssss', $email, $name, $subject, $html, $text, $sendAt, $sourceType, $sourceKey);
            $ins->execute();
            if ($ins->affected_rows > 0) {
                $inserted++;
            } else {
                $skipped++;
            }
        }
    }

    $resJadwal->free();
}

if ($stmt_get_kend_pengguna) $stmt_get_kend_pengguna->close();
if ($stmt_get_user) $stmt_get_user->close();
if ($stmt_get_drivers) $stmt_get_drivers->close();
$stmt_jadwal->close();
$ins->close();

echo "Queue reminder selesai. Inserted={$inserted}, Skipped={$skipped}\n";
