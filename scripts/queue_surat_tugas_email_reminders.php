<?php
// Queue H-1 and H (today) surat_tugas reminder emails for pengguna and driver.
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

if (!table_exists_local($mysqli, 'surat_tugas')) {
    echo "Table surat_tugas tidak ditemukan. Tidak ada reminder yang di-queue.\n";
    exit(0);
}

$driverColExists = false;
try {
    if ($col = $mysqli->query("SHOW COLUMNS FROM surat_tugas LIKE 'driver_id'")) {
        if ($col->num_rows > 0) $driverColExists = true;
        $col->free();
    }
} catch (Throwable $e) {
    $driverColExists = false;
}

$driverSelect = $driverColExists ? "s.driver_id, drv.nama_lengkap AS driver_name" : "NULL AS driver_id, NULL AS driver_name";
$driverJoin = $driverColExists ? "LEFT JOIN pengguna drv ON s.driver_id = drv.id" : "";

$sql = "SELECT s.id, s.nomor_surat, s.tanggal_berangkat, s.tanggal_kembali, s.kendaraan_id, s.pengguna_id,
               s.keperluan, s.tujuan, s.perihal, s.berangkat_dari, s.waktu_berangkat, {$driverSelect},
               k.no_reg, k.no_polisi, k.merk, k.tipe
        FROM surat_tugas s
        LEFT JOIN kendaraan k ON k.id = s.kendaraan_id
        {$driverJoin}
        WHERE DATE(s.tanggal_berangkat) = ?
          AND (s.status IS NULL OR LOWER(s.status) NOT IN ('selesai','dibatalkan'))";

$stmt_st = $mysqli->prepare($sql);
if (!$stmt_st) {
    fwrite(STDERR, "Prepare surat_tugas gagal: {$mysqli->error}\n");
    exit(1);
}

$ins = $mysqli->prepare("INSERT IGNORE INTO email_reminder_jobs (recipient_email, recipient_name, subject, body_html, body_text, send_at, status, source_type, source_key, created_at, updated_at) VALUES (?, ?, ?, ?, ?, ?, 'pending', ?, ?, NOW(), NOW())");
if (!$ins) {
    fwrite(STDERR, "Prepare insert queue gagal: {$mysqli->error}\n");
    exit(1);
}

$stmt_get_user = $mysqli->prepare("SELECT id, nama_lengkap, email FROM pengguna WHERE id = ? AND email IS NOT NULL AND email <> '' LIMIT 1");
$stmt_get_kend_pengguna = $mysqli->prepare("SELECT pengguna_id FROM kendaraan WHERE id = ? LIMIT 1");

$scenarios = [
    [
        'label' => 'H-1',
        'sourceType' => 'surat_tugas_h1',
        'targetDate' => date('Y-m-d', strtotime('+1 day')),
        'dayLabel' => 'besok'
    ],
    [
        'label' => 'Hari H',
        'sourceType' => 'surat_tugas_h0',
        'targetDate' => date('Y-m-d'),
        'dayLabel' => 'hari ini'
    ],
];

$inserted = 0;
$skipped = 0;

foreach ($scenarios as $scenario) {
    $stmt_st->bind_param('s', $scenario['targetDate']);
    $stmt_st->execute();
    $res = $stmt_st->get_result();
    if (!$res) {
        fwrite(STDERR, "Gagal mengambil data surat_tugas: {$mysqli->error}\n");
        continue;
    }

    while ($row = $res->fetch_assoc()) {
        $sid = (int)$row['id'];
        $nomor = trim((string)($row['nomor_surat'] ?? '')) ?: ('#' . $sid);
        $tanggal = (string)($row['tanggal_berangkat'] ?? '');
        $tanggalView = $tanggal !== '' ? date('d/m/Y', strtotime($tanggal)) : '-';
        $tanggalKembali = (string)($row['tanggal_kembali'] ?? '');
        $tanggalKembaliView = $tanggalKembali !== '' ? date('d/m/Y', strtotime($tanggalKembali)) : '-';
        $kendId = (int)($row['kendaraan_id'] ?? 0);

        $kendaraanLabel = trim((string)($row['no_reg'] ?? ''));
        if ($kendaraanLabel === '') $kendaraanLabel = trim((string)($row['no_polisi'] ?? ''));
        if ($kendaraanLabel === '') $kendaraanLabel = trim((string)(($row['merk'] ?? '') . ' ' . ($row['tipe'] ?? '')));
        if ($kendaraanLabel === '') $kendaraanLabel = '-';

        $perihal = trim((string)($row['perihal'] ?? ''));
        if ($perihal === '') $perihal = trim((string)($row['keperluan'] ?? ''));
        if ($perihal === '') $perihal = '-';

        $tujuan = trim((string)($row['tujuan'] ?? '-'));
        $keperluan = trim((string)($row['keperluan'] ?? '-'));
        $berangkatDari = trim((string)($row['berangkat_dari'] ?? '-'));
        $waktuBerangkat = trim((string)($row['waktu_berangkat'] ?? '-'));
        $driverName = trim((string)($row['driver_name'] ?? ''));

        $subject = "Pengingat {$scenario['label']} Surat Tugas {$nomor} - {$tanggalView}";
        $htmlBase = '<p>Yth. Bapak/Ibu,</p>'
            . '<p>Ini adalah pengingat ' . safe_html($scenario['label']) . ' untuk Surat Tugas <strong>' . safe_html($nomor) . '</strong> yang dijadwalkan ' . safe_html($scenario['dayLabel']) . '.</p>'
            . '<ul>'
            . '<li>Nomor Surat: ' . safe_html($nomor) . '</li>'
            . '<li>Tanggal Berangkat: ' . safe_html($tanggalView) . '</li>'
            . '<li>Tanggal Kembali: ' . safe_html($tanggalKembaliView) . '</li>'
            . '<li>Kendaraan: ' . safe_html($kendaraanLabel) . '</li>'
            . '<li>Tujuan: ' . safe_html($tujuan) . '</li>'
            . '<li>Keperluan: ' . safe_html($keperluan) . '</li>'
            . '<li>Perihal: ' . safe_html($perihal) . '</li>'
            . '<li>Berangkat dari: ' . safe_html($berangkatDari) . '</li>'
            . '<li>Waktu berangkat: ' . safe_html($waktuBerangkat) . '</li>'
            . ($driverName !== '' ? ('<li>Driver: ' . safe_html($driverName) . '</li>') : '')
            . '</ul>'
            . '<p>Silakan cek detail di sistem untuk informasi lebih lanjut.</p>';

        $textBase = "Pengingat {$scenario['label']}: Surat Tugas {$nomor} dijadwalkan {$scenario['dayLabel']}.\n"
            . "Tanggal Berangkat: {$tanggalView}\n"
            . "Tanggal Kembali: {$tanggalKembaliView}\n"
            . "Kendaraan: {$kendaraanLabel}\n"
            . "Tujuan: {$tujuan}\n"
            . "Keperluan: {$keperluan}\n"
            . "Perihal: {$perihal}\n"
            . "Berangkat dari: {$berangkatDari}\n"
            . "Waktu berangkat: {$waktuBerangkat}\n"
            . ($driverName !== '' ? "Driver: {$driverName}\n" : '')
            . "\nSilakan cek detail di sistem.";

        $sendAt = $scenario['label'] === 'H-1'
            ? date('Y-m-d 07:00:00', strtotime($tanggal . ' -1 day'))
            : date('Y-m-d 07:00:00', strtotime($tanggal));
        if (strtotime($sendAt) < time()) { $sendAt = date('Y-m-d H:i:s'); }

        $sourceType = $scenario['sourceType'];
        $sourceKey = (string)$sid;

        $candidates = [];

        $pu = (int)($row['pengguna_id'] ?? 0);
        if ($pu > 0) {
            $urow = fetch_user_by_id($stmt_get_user, $pu);
            if ($urow && filter_var($urow['email'] ?? '', FILTER_VALIDATE_EMAIL)) {
                $candidates[] = $urow;
            }
        }

        $drv = $driverColExists ? (int)($row['driver_id'] ?? 0) : 0;
        if ($drv > 0) {
            $drow = fetch_user_by_id($stmt_get_user, $drv);
            if ($drow && filter_var($drow['email'] ?? '', FILTER_VALIDATE_EMAIL)) {
                $candidates[] = $drow;
            }
        }

        if ($kendId > 0 && $stmt_get_kend_pengguna) {
            $stmt_get_kend_pengguna->bind_param('i', $kendId);
            $stmt_get_kend_pengguna->execute();
            $kpRes = $stmt_get_kend_pengguna->get_result();
            $kp = $kpRes ? $kpRes->fetch_assoc() : null;
            if ($kpRes) $kpRes->free();
            if (!empty($kp['pengguna_id'])) {
                $pg = (int)$kp['pengguna_id'];
                $pgrow = fetch_user_by_id($stmt_get_user, $pg);
                if ($pgrow && filter_var($pgrow['email'] ?? '', FILTER_VALIDATE_EMAIL)) {
                    $candidates[] = $pgrow;
                }
            }
        }

        $seen = [];
        foreach ($candidates as $cand) {
            $email = strtolower(trim((string)($cand['email'] ?? '')));
            if ($email === '' || !filter_var($email, FILTER_VALIDATE_EMAIL)) continue;
            if (isset($seen[$email])) continue;
            $seen[$email] = true;

            $ins->bind_param('ssssssss', $email, $cand['nama_lengkap'], $subject, $htmlBase, $textBase, $sendAt, $sourceType, $sourceKey);
            $ins->execute();
            if ($ins->affected_rows > 0) {
                $inserted++;
            } else {
                $skipped++;
            }
        }
    }

    $res->free();
}

if ($stmt_get_user) $stmt_get_user->close();
if ($stmt_get_kend_pengguna) $stmt_get_kend_pengguna->close();
$stmt_st->close();
$ins->close();

echo "Queue surat_tugas reminders selesai. Inserted={$inserted}, Skipped={$skipped}\n";
