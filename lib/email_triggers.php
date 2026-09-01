<?php
/**
 * lib/email_triggers.php
 *
 * Logika inti untuk antrean & pengiriman email notifikasi otomatis:
 *  - Reminder H-1 & Hari-H untuk surat tugas (queue_surat_tugas_reminders)
 *  - Reminder H-1 & Hari-H untuk jadwal perawatan (queue_maintenance_reminders)
 *  - Pengiriman email yang sudah jatuh tempo dari antrean (send_pending_reminder_emails)
 *  - Notifikasi instan saat surat tugas disetujui pimpinan (queue_and_send_surat_tugas_approved_email)
 *  - Runner otomatis berbasis throttle, dipanggil dari config.php tiap request (run_email_automation_if_due)
 *
 * File ini dipakai bersama oleh:
 *  - scripts/queue_surat_tugas_email_reminders.php (CLI, opsional bila memakai Windows Task Scheduler)
 *  - scripts/queue_maintenance_email_reminders.php (CLI)
 *  - scripts/send_scheduled_emails.php (CLI)
 *  - pages/email_queue.php (tombol manual admin)
 *  - config.php (auto-run berkala tanpa perlu cron OS)
 *
 * Catatan: queue_and_send_surat_tugas_approved_email() sebelumnya dipanggil dari
 * pages/persetujuan_peminjaman.php (aksi approve_surat). Halaman itu sudah dihapus
 * bersama seluruh fitur peminjaman, sehingga fungsi ini saat ini tidak lagi dipanggil
 * di manapun — perlu dikaitkan ke UI approval Surat Tugas yang baru bila dibutuhkan lagi.
 */

if (!function_exists('_eqt_table_exists')) {
    function _eqt_table_exists(mysqli $db, string $table): bool {
        $safe = $db->real_escape_string($table);
        $res = $db->query("SHOW TABLES LIKE '{$safe}'");
        $ok = $res && $res->num_rows > 0;
        if ($res) $res->free();
        return $ok;
    }
}

if (!function_exists('_eqt_column_exists')) {
    function _eqt_column_exists(mysqli $db, string $table, string $column): bool {
        $safeTable = $db->real_escape_string($table);
        $safeCol = $db->real_escape_string($column);
        $res = $db->query("SHOW COLUMNS FROM `{$safeTable}` LIKE '{$safeCol}'");
        $ok = $res && $res->num_rows > 0;
        if ($res) $res->free();
        return $ok;
    }
}

if (!function_exists('_eqt_safe_html')) {
    function _eqt_safe_html($value): string {
        return htmlspecialchars((string)$value, ENT_QUOTES, 'UTF-8');
    }
}

if (!function_exists('_eqt_fetch_user_by_id')) {
    function _eqt_fetch_user_by_id($stmt, int $id): ?array {
        if (!$stmt || $id <= 0) return null;
        $stmt->bind_param('i', $id);
        if (!$stmt->execute()) return null;
        $res = $stmt->get_result();
        if (!$res) return null;
        $row = $res->fetch_assoc();
        $res->free();
        return $row ?: null;
    }
}

/**
 * Queue reminder H-1 & Hari-H untuk surat_tugas yang belum Selesai/Dibatalkan.
 * Return: ['inserted' => int, 'skipped' => int, 'ok' => bool, 'message' => string]
 */
function queue_surat_tugas_reminders(mysqli $mysqli): array {
    if (!_eqt_table_exists($mysqli, 'email_reminder_jobs')) {
        return ['ok' => false, 'inserted' => 0, 'skipped' => 0, 'message' => 'Tabel email_reminder_jobs belum ada.'];
    }
    if (!_eqt_table_exists($mysqli, 'surat_tugas')) {
        return ['ok' => true, 'inserted' => 0, 'skipped' => 0, 'message' => 'Tabel surat_tugas tidak ditemukan.'];
    }

    $driverColExists = _eqt_column_exists($mysqli, 'surat_tugas', 'driver_id');
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
        return ['ok' => false, 'inserted' => 0, 'skipped' => 0, 'message' => 'Prepare surat_tugas gagal: ' . $mysqli->error];
    }

    $ins = $mysqli->prepare("INSERT IGNORE INTO email_reminder_jobs (recipient_email, recipient_name, subject, body_html, body_text, send_at, status, source_type, source_key, created_at, updated_at) VALUES (?, ?, ?, ?, ?, ?, 'pending', ?, ?, NOW(), NOW())");
    if (!$ins) {
        $stmt_st->close();
        return ['ok' => false, 'inserted' => 0, 'skipped' => 0, 'message' => 'Prepare insert queue gagal: ' . $mysqli->error];
    }

    $stmt_get_user = $mysqli->prepare("SELECT id, nama_lengkap, email FROM pengguna WHERE id = ? AND email IS NOT NULL AND email <> '' LIMIT 1");
    $stmt_get_kend_pengguna = $mysqli->prepare("SELECT pengguna_id FROM kendaraan WHERE id = ? LIMIT 1");

    $scenarios = [
        ['label' => 'H-1', 'sourceType' => 'surat_tugas_h1', 'targetDate' => date('Y-m-d', strtotime('+1 day')), 'dayLabel' => 'besok'],
        ['label' => 'Hari H', 'sourceType' => 'surat_tugas_h0', 'targetDate' => date('Y-m-d'), 'dayLabel' => 'hari ini'],
    ];

    $inserted = 0;
    $skipped = 0;

    foreach ($scenarios as $scenario) {
        $stmt_st->bind_param('s', $scenario['targetDate']);
        $stmt_st->execute();
        $res = $stmt_st->get_result();
        if (!$res) continue;

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
                . '<p>Ini adalah pengingat ' . _eqt_safe_html($scenario['label']) . ' untuk Surat Tugas <strong>' . _eqt_safe_html($nomor) . '</strong> yang dijadwalkan ' . _eqt_safe_html($scenario['dayLabel']) . '.</p>'
                . '<ul>'
                . '<li>Nomor Surat: ' . _eqt_safe_html($nomor) . '</li>'
                . '<li>Tanggal Berangkat: ' . _eqt_safe_html($tanggalView) . '</li>'
                . '<li>Tanggal Kembali: ' . _eqt_safe_html($tanggalKembaliView) . '</li>'
                . '<li>Kendaraan: ' . _eqt_safe_html($kendaraanLabel) . '</li>'
                . '<li>Tujuan: ' . _eqt_safe_html($tujuan) . '</li>'
                . '<li>Keperluan: ' . _eqt_safe_html($keperluan) . '</li>'
                . '<li>Perihal: ' . _eqt_safe_html($perihal) . '</li>'
                . '<li>Berangkat dari: ' . _eqt_safe_html($berangkatDari) . '</li>'
                . '<li>Waktu berangkat: ' . _eqt_safe_html($waktuBerangkat) . '</li>'
                . ($driverName !== '' ? ('<li>Driver: ' . _eqt_safe_html($driverName) . '</li>') : '')
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
                $urow = _eqt_fetch_user_by_id($stmt_get_user, $pu);
                if ($urow && filter_var($urow['email'] ?? '', FILTER_VALIDATE_EMAIL)) $candidates[] = $urow;
            }

            $drv = $driverColExists ? (int)($row['driver_id'] ?? 0) : 0;
            if ($drv > 0) {
                $drow = _eqt_fetch_user_by_id($stmt_get_user, $drv);
                if ($drow && filter_var($drow['email'] ?? '', FILTER_VALIDATE_EMAIL)) $candidates[] = $drow;
            }

            if ($kendId > 0 && $stmt_get_kend_pengguna) {
                $stmt_get_kend_pengguna->bind_param('i', $kendId);
                $stmt_get_kend_pengguna->execute();
                $kpRes = $stmt_get_kend_pengguna->get_result();
                $kp = $kpRes ? $kpRes->fetch_assoc() : null;
                if ($kpRes) $kpRes->free();
                if (!empty($kp['pengguna_id'])) {
                    $pgrow = _eqt_fetch_user_by_id($stmt_get_user, (int)$kp['pengguna_id']);
                    if ($pgrow && filter_var($pgrow['email'] ?? '', FILTER_VALIDATE_EMAIL)) $candidates[] = $pgrow;
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
                if ($ins->affected_rows > 0) { $inserted++; } else { $skipped++; }
            }
        }
        $res->free();
    }

    if ($stmt_get_user) $stmt_get_user->close();
    if ($stmt_get_kend_pengguna) $stmt_get_kend_pengguna->close();
    $stmt_st->close();
    $ins->close();

    return ['ok' => true, 'inserted' => $inserted, 'skipped' => $skipped, 'message' => "Inserted={$inserted}, Skipped={$skipped}"];
}

/**
 * Queue reminder H-1 & Hari-H untuk jadwal_perawatan yang belum Selesai/Dibatalkan.
 * Return: ['inserted' => int, 'skipped' => int, 'ok' => bool, 'message' => string]
 */
function queue_maintenance_reminders(mysqli $mysqli): array {
    if (!_eqt_table_exists($mysqli, 'email_reminder_jobs')) {
        return ['ok' => false, 'inserted' => 0, 'skipped' => 0, 'message' => 'Tabel email_reminder_jobs belum ada.'];
    }
    if (!_eqt_table_exists($mysqli, 'jadwal_perawatan')) {
        return ['ok' => true, 'inserted' => 0, 'skipped' => 0, 'message' => 'Tabel jadwal_perawatan tidak ditemukan.'];
    }

    $dateCol = null;
    foreach (['tanggal_perawatan', 'jadwal_tanggal'] as $c) {
        if (_eqt_column_exists($mysqli, 'jadwal_perawatan', $c)) { $dateCol = $c; break; }
    }
    if ($dateCol === null) {
        return ['ok' => false, 'inserted' => 0, 'skipped' => 0, 'message' => 'Kolom tanggal jadwal perawatan tidak ditemukan.'];
    }

    $hasSuratTugas = _eqt_table_exists($mysqli, 'surat_tugas');
    $driverColExists = $hasSuratTugas && _eqt_column_exists($mysqli, 'surat_tugas', 'driver_id');

    $ins = $mysqli->prepare("INSERT IGNORE INTO email_reminder_jobs (recipient_email, recipient_name, subject, body_html, body_text, send_at, status, source_type, source_key, created_at, updated_at) VALUES (?, ?, ?, ?, ?, ?, 'pending', ?, ?, NOW(), NOW())");
    if (!$ins) {
        return ['ok' => false, 'inserted' => 0, 'skipped' => 0, 'message' => 'Prepare insert queue gagal: ' . $mysqli->error];
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
        $ins->close();
        return ['ok' => false, 'inserted' => 0, 'skipped' => 0, 'message' => 'Prepare jadwal_perawatan gagal: ' . $mysqli->error];
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
        ['label' => 'H-1', 'sourceType' => 'jadwal_perawatan_h1', 'targetDate' => date('Y-m-d', strtotime('+1 day')), 'dayLabel' => 'besok'],
        ['label' => 'Hari H', 'sourceType' => 'jadwal_perawatan_h0', 'targetDate' => date('Y-m-d'), 'dayLabel' => 'hari ini'],
    ];

    $inserted = 0;
    $skipped = 0;

    foreach ($scenarios as $scenario) {
        $stmt_jadwal->bind_param('s', $scenario['targetDate']);
        $stmt_jadwal->execute();
        $resJadwal = $stmt_jadwal->get_result();
        if (!$resJadwal) continue;

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
            if (strtotime($sendAt) < time()) { $sendAt = date('Y-m-d H:i:s'); }

            $perRecipients = [];
            $kendId = (int)($j['kendaraan_id'] ?? 0);

            if ($kendId > 0 && $stmt_get_kend_pengguna) {
                $stmt_get_kend_pengguna->bind_param('i', $kendId);
                $stmt_get_kend_pengguna->execute();
                $rkpRes = $stmt_get_kend_pengguna->get_result();
                $rkp = $rkpRes ? $rkpRes->fetch_assoc() : null;
                if ($rkpRes) $rkpRes->free();
                if (!empty($rkp['pengguna_id'])) {
                    $pu = _eqt_fetch_user_by_id($stmt_get_user, (int)$rkp['pengguna_id']);
                    if ($pu && filter_var($pu['email'] ?? '', FILTER_VALIDATE_EMAIL)) {
                        $perRecipients[] = ['pengguna_id' => $pu['id'], 'nama_lengkap' => $pu['nama_lengkap'], 'email' => $pu['email']];
                    }
                }
            }

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
                . '<p>Ini adalah pengingat otomatis untuk jadwal perawatan/perbaikan kendaraan yang dijadwalkan ' . _eqt_safe_html($scenario['dayLabel']) . '.</p>'
                . '<ul>'
                . '<li>Tanggal: ' . _eqt_safe_html($tanggalView) . '</li>'
                . '<li>Kendaraan: ' . _eqt_safe_html($namaKendaraan) . '</li>'
                . '<li>Jenis Perawatan: ' . _eqt_safe_html($jenis) . '</li>'
                . '<li>Deskripsi: ' . _eqt_safe_html($desk) . '</li>'
                . '<li>Status: ' . _eqt_safe_html($status) . '</li>'
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
                if ($ins->affected_rows > 0) { $inserted++; } else { $skipped++; }
            }
        }
        $resJadwal->free();
    }

    if ($stmt_get_kend_pengguna) $stmt_get_kend_pengguna->close();
    if ($stmt_get_user) $stmt_get_user->close();
    if ($stmt_get_drivers) $stmt_get_drivers->close();
    $stmt_jadwal->close();
    $ins->close();

    return ['ok' => true, 'inserted' => $inserted, 'skipped' => $skipped, 'message' => "Inserted={$inserted}, Skipped={$skipped}"];
}

/**
 * Kirim email yang berstatus pending dan sudah jatuh tempo (send_at <= NOW()).
 * Return: ['sent' => int, 'failed' => int, 'ok' => bool, 'message' => string]
 */
function send_pending_reminder_emails(mysqli $mysqli, int $limit = 50): array {
    if (!_eqt_table_exists($mysqli, 'email_reminder_jobs')) {
        return ['ok' => false, 'sent' => 0, 'failed' => 0, 'message' => 'Tabel email_reminder_jobs belum ada.'];
    }
    require_once __DIR__ . '/mailer.php';

    $limit = max(1, min(500, $limit));
    $stmt = $mysqli->prepare("SELECT id, recipient_email, recipient_name, subject, body_html, body_text FROM email_reminder_jobs WHERE status = 'pending' AND send_at <= NOW() AND attempt_count < max_attempts ORDER BY send_at ASC, id ASC LIMIT ?");
    $stmt->bind_param('i', $limit);
    $stmt->execute();
    $jobs = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    $stmt->close();

    if (empty($jobs)) {
        return ['ok' => true, 'sent' => 0, 'failed' => 0, 'message' => 'Tidak ada email pending yang jatuh tempo.'];
    }

    $sent = $failed = 0;
    $updSent = $mysqli->prepare("UPDATE email_reminder_jobs SET status='sent', sent_at=NOW(), attempt_count=attempt_count+1, last_error=NULL, updated_at=NOW() WHERE id = ?");
    $updFail = $mysqli->prepare("UPDATE email_reminder_jobs SET status=IF(attempt_count+1 >= max_attempts, 'failed', 'pending'), attempt_count=attempt_count+1, last_error=?, updated_at=NOW() WHERE id = ?");

    foreach ($jobs as $job) {
        $id = (int)$job['id'];
        $err = null;
        $ok = app_send_email((string)$job['recipient_email'], (string)($job['recipient_name'] ?? ''), (string)$job['subject'], (string)$job['body_html'], (string)($job['body_text'] ?? ''), $err);
        if ($ok) {
            $updSent->bind_param('i', $id); $updSent->execute(); $sent++;
        } else {
            $e = trim((string)$err) ?: 'Unknown send error';
            $updFail->bind_param('si', $e, $id); $updFail->execute(); $failed++;
        }
    }
    $updSent->close();
    $updFail->close();

    return ['ok' => true, 'sent' => $sent, 'failed' => $failed, 'message' => "Sent={$sent}, Failed={$failed}, Total=" . count($jobs)];
}

/**
 * Dipanggil segera setelah surat_tugas mendapat persetujuan FINAL (approval_pimpinan_status -> Approved).
 * Antre + langsung kirim email ke pengguna (pemohon) dan driver (jika kolom driver_id ada),
 * supaya driver/user tahu seketika tanpa menunggu siklus reminder H-1/H0.
 *
 * Return: true jika minimal satu email berhasil terkirim atau ter-antre, false jika surat tidak ditemukan.
 */
function queue_and_send_surat_tugas_approved_email(mysqli $mysqli, int $surat_id): bool {
    if ($surat_id <= 0) return false;
    if (!_eqt_table_exists($mysqli, 'email_reminder_jobs') || !_eqt_table_exists($mysqli, 'surat_tugas')) return false;

    $driverColExists = _eqt_column_exists($mysqli, 'surat_tugas', 'driver_id');
    $driverSelect = $driverColExists ? "s.driver_id, drv.nama_lengkap AS driver_name" : "NULL AS driver_id, NULL AS driver_name";
    $driverJoin = $driverColExists ? "LEFT JOIN pengguna drv ON s.driver_id = drv.id" : "";

    $stmt = $mysqli->prepare(
        "SELECT s.id, s.nomor_surat, s.tanggal_berangkat, s.tanggal_kembali, s.kendaraan_id, s.pengguna_id,
                s.keperluan, s.tujuan, s.perihal, s.berangkat_dari, s.waktu_berangkat, {$driverSelect},
                k.no_reg, k.no_polisi, k.merk, k.tipe
         FROM surat_tugas s
         LEFT JOIN kendaraan k ON k.id = s.kendaraan_id
         {$driverJoin}
         WHERE s.id = ? LIMIT 1"
    );
    if (!$stmt) return false;
    $stmt->bind_param('i', $surat_id);
    $stmt->execute();
    $row = $stmt->get_result()->fetch_assoc();
    $stmt->close();
    if (!$row) return false;

    $nomor = trim((string)($row['nomor_surat'] ?? '')) ?: ('#' . $surat_id);
    $tanggal = (string)($row['tanggal_berangkat'] ?? '');
    $tanggalView = $tanggal !== '' ? date('d/m/Y', strtotime($tanggal)) : '-';
    $tanggalKembaliView = !empty($row['tanggal_kembali']) ? date('d/m/Y', strtotime((string)$row['tanggal_kembali'])) : '-';
    $kendId = (int)($row['kendaraan_id'] ?? 0);

    $kendaraanLabel = trim((string)($row['no_reg'] ?? ''));
    if ($kendaraanLabel === '') $kendaraanLabel = trim((string)($row['no_polisi'] ?? ''));
    if ($kendaraanLabel === '') $kendaraanLabel = trim((string)(($row['merk'] ?? '') . ' ' . ($row['tipe'] ?? '')));
    if ($kendaraanLabel === '') $kendaraanLabel = '-';

    $tujuan = trim((string)($row['tujuan'] ?? '-'));
    $keperluan = trim((string)($row['keperluan'] ?? '-'));
    $berangkatDari = trim((string)($row['berangkat_dari'] ?? '-'));
    $waktuBerangkat = trim((string)($row['waktu_berangkat'] ?? '-'));
    $driverName = trim((string)($row['driver_name'] ?? ''));

    $subject = "Surat Tugas {$nomor} Disetujui — Siap Berangkat {$tanggalView}";
    $html = '<p>Yth. Bapak/Ibu,</p>'
        . '<p>Surat Tugas <strong>' . _eqt_safe_html($nomor) . '</strong> Anda telah <strong>disetujui pimpinan</strong> dan siap dilaksanakan.</p>'
        . '<ul>'
        . '<li>Nomor Surat: ' . _eqt_safe_html($nomor) . '</li>'
        . '<li>Tanggal Berangkat: ' . _eqt_safe_html($tanggalView) . '</li>'
        . '<li>Tanggal Kembali: ' . _eqt_safe_html($tanggalKembaliView) . '</li>'
        . '<li>Kendaraan: ' . _eqt_safe_html($kendaraanLabel) . '</li>'
        . '<li>Tujuan: ' . _eqt_safe_html($tujuan) . '</li>'
        . '<li>Keperluan: ' . _eqt_safe_html($keperluan) . '</li>'
        . '<li>Berangkat dari: ' . _eqt_safe_html($berangkatDari) . '</li>'
        . '<li>Waktu berangkat: ' . _eqt_safe_html($waktuBerangkat) . '</li>'
        . ($driverName !== '' ? ('<li>Driver: ' . _eqt_safe_html($driverName) . '</li>') : '')
        . '</ul>'
        . '<p>Silakan cek detail lengkap di sistem Randis.</p>';

    $text = "Surat Tugas {$nomor} telah disetujui pimpinan dan siap dilaksanakan.\n"
        . "Tanggal Berangkat: {$tanggalView}\n"
        . "Tanggal Kembali: {$tanggalKembaliView}\n"
        . "Kendaraan: {$kendaraanLabel}\n"
        . "Tujuan: {$tujuan}\n"
        . "Keperluan: {$keperluan}\n"
        . "Berangkat dari: {$berangkatDari}\n"
        . "Waktu berangkat: {$waktuBerangkat}\n"
        . ($driverName !== '' ? "Driver: {$driverName}\n" : '')
        . "\nSilakan cek detail di sistem.";

    $stmt_get_user = $mysqli->prepare("SELECT id, nama_lengkap, email FROM pengguna WHERE id = ? AND email IS NOT NULL AND email <> '' LIMIT 1");
    $stmt_get_kend_pengguna = $mysqli->prepare("SELECT pengguna_id FROM kendaraan WHERE id = ? LIMIT 1");

    $candidates = [];
    $pu = (int)($row['pengguna_id'] ?? 0);
    if ($pu > 0) {
        $urow = _eqt_fetch_user_by_id($stmt_get_user, $pu);
        if ($urow && filter_var($urow['email'] ?? '', FILTER_VALIDATE_EMAIL)) $candidates[] = $urow;
    }
    $drv = $driverColExists ? (int)($row['driver_id'] ?? 0) : 0;
    if ($drv > 0) {
        $drow = _eqt_fetch_user_by_id($stmt_get_user, $drv);
        if ($drow && filter_var($drow['email'] ?? '', FILTER_VALIDATE_EMAIL)) $candidates[] = $drow;
    }
    if ($kendId > 0 && $stmt_get_kend_pengguna) {
        $stmt_get_kend_pengguna->bind_param('i', $kendId);
        $stmt_get_kend_pengguna->execute();
        $kpRes = $stmt_get_kend_pengguna->get_result();
        $kp = $kpRes ? $kpRes->fetch_assoc() : null;
        if ($kpRes) $kpRes->free();
        if (!empty($kp['pengguna_id'])) {
            $pgrow = _eqt_fetch_user_by_id($stmt_get_user, (int)$kp['pengguna_id']);
            if ($pgrow && filter_var($pgrow['email'] ?? '', FILTER_VALIDATE_EMAIL)) $candidates[] = $pgrow;
        }
    }
    if ($stmt_get_user) $stmt_get_user->close();
    if ($stmt_get_kend_pengguna) $stmt_get_kend_pengguna->close();

    require_once __DIR__ . '/mailer.php';
    $ins = $mysqli->prepare("INSERT IGNORE INTO email_reminder_jobs (recipient_email, recipient_name, subject, body_html, body_text, send_at, status, source_type, source_key, created_at, updated_at) VALUES (?, ?, ?, ?, ?, NOW(), 'pending', 'surat_tugas_approved', ?, NOW(), NOW())");
    $updSent = $mysqli->prepare("UPDATE email_reminder_jobs SET status='sent', sent_at=NOW(), attempt_count=attempt_count+1, updated_at=NOW() WHERE id=?");
    $updFail = $mysqli->prepare("UPDATE email_reminder_jobs SET status=IF(attempt_count+1>=max_attempts,'failed','pending'), attempt_count=attempt_count+1, last_error=?, updated_at=NOW() WHERE id=?");

    $sourceKey = (string)$surat_id;
    $seen = [];
    $anySuccess = false;

    foreach ($candidates as $cand) {
        $email = strtolower(trim((string)($cand['email'] ?? '')));
        if ($email === '' || !filter_var($email, FILTER_VALIDATE_EMAIL)) continue;
        if (isset($seen[$email])) continue;
        $seen[$email] = true;

        $name = (string)($cand['nama_lengkap'] ?? '');

        // Insert queue row first (dedup guard so re-approval / re-trigger never double-sends this event)
        $ins->bind_param('ssssss', $email, $name, $subject, $html, $text, $sourceKey);
        $ins->execute();
        $newId = $mysqli->insert_id;
        $wasNew = $ins->affected_rows > 0;

        if (!$wasNew) continue; // sudah pernah diantre/dikirim untuk surat ini + email ini

        $err = null;
        $ok = app_send_email($email, $name, $subject, $html, $text, $err);
        if ($ok) {
            if ($updSent) { $updSent->bind_param('i', $newId); $updSent->execute(); }
            $anySuccess = true;
        } else {
            $e = trim((string)$err) ?: 'Unknown send error';
            if ($updFail) { $updFail->bind_param('si', $e, $newId); $updFail->execute(); }
        }
    }

    if ($ins) $ins->close();
    if ($updSent) $updSent->close();
    if ($updFail) $updFail->close();

    return $anySuccess || !empty($seen);
}

/**
 * Pseudo-cron: dipanggil di config.php pada tiap request halaman (bukan AJAX) supaya
 * antrean & pengiriman reminder berjalan otomatis tanpa perlu Windows Task Scheduler.
 * Dibatasi (throttle) via file marker supaya tidak query DB berulang di setiap request.
 */
function run_email_automation_if_due(mysqli $mysqli, int $throttleSeconds = 600): ?array {
    $markerFile = __DIR__ . '/../logs/email_automation_last_run.txt';
    $now = time();

    $last = 0;
    if (is_file($markerFile)) {
        $last = (int)@file_get_contents($markerFile);
    }
    if (($now - $last) < $throttleSeconds) {
        return null; // belum waktunya, skip diam-diam
    }

    // Tulis marker duluan supaya request paralel tidak sama-sama menjalankan automation.
    @file_put_contents($markerFile, (string)$now);

    if (!_eqt_table_exists($mysqli, 'email_reminder_jobs')) {
        return null;
    }

    $r1 = queue_surat_tugas_reminders($mysqli);
    $r2 = queue_maintenance_reminders($mysqli);
    $r3 = send_pending_reminder_emails($mysqli, 50);

    return ['queue_surat_tugas' => $r1, 'queue_maintenance' => $r2, 'send' => $r3, 'ran_at' => date('Y-m-d H:i:s', $now)];
}
