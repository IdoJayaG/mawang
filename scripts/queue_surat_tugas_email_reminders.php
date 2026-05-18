<?php
// Queue H-1 surat_tugas reminder emails for pengguna and driver.
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

if (!table_exists_local($mysqli, 'surat_tugas')) {
    echo "Table surat_tugas tidak ditemukan. Tidak ada reminder yang di-queue.\n";
    exit(0);
}

$sql = "SELECT s.id, s.nomor_surat, s.tanggal_berangkat, s.tanggal_kembali, s.kendaraan_id, s.pengguna_id, s.keperluan, s.tujuan, s.driver_id
        FROM surat_tugas s
        WHERE DATE(s.tanggal_berangkat) = DATE(DATE_ADD(CURDATE(), INTERVAL 1 DAY))
          AND (s.status IS NULL OR LOWER(s.status) NOT IN ('selesai','dibatalkan'))";

$res = $mysqli->query($sql);
if (!$res) {
    fwrite(STDERR, "Gagal mengambil data surat_tugas: {$mysqli->error}\n");
    exit(1);
}

$ins = $mysqli->prepare("INSERT IGNORE INTO email_reminder_jobs (recipient_email, recipient_name, subject, body_html, body_text, send_at, status, source_type, source_key, created_at, updated_at) VALUES (?, ?, ?, ?, ?, ?, 'pending', ?, ?, NOW(), NOW())");
if (!$ins) {
    fwrite(STDERR, "Prepare insert queue gagal: {$mysqli->error}\n");
    exit(1);
}

$stmt_get_user = $mysqli->prepare("SELECT id, nama_lengkap, email FROM pengguna WHERE id = ? AND email IS NOT NULL AND email <> '' LIMIT 1");
$stmt_get_kend_pengguna = $mysqli->prepare("SELECT pengguna_id FROM kendaraan WHERE id = ? LIMIT 1");

$inserted = 0;
$skipped = 0;

while ($row = $res->fetch_assoc()) {
    $sid = (int)$row['id'];
    $nomor = trim((string)($row['nomor_surat'] ?? '')) ?: ('#' . $sid);
    $tanggal = (string)($row['tanggal_berangkat'] ?? '');
    $tanggalView = $tanggal !== '' ? date('d/m/Y', strtotime($tanggal)) : '-';
    $kendId = (int)($row['kendaraan_id'] ?? 0);

    // Compose message
    $subject = "Pengingat Surat Tugas {$nomor} - {$tanggalView}";
    $htmlBase = '<p>Yth. Bapak/Ibu,</p>'
              . '<p>Ini adalah pengingat bahwa terdapat rencana penggunaan kendaraan berdasarkan <strong>Surat Tugas ' . htmlspecialchars($nomor, ENT_QUOTES, 'UTF-8') . '</strong> pada tanggal <strong>' . htmlspecialchars($tanggalView, ENT_QUOTES, 'UTF-8') . '</strong>.</p>'
              . '<ul>'
              . '<li>Tujuan: ' . htmlspecialchars((string)($row['tujuan'] ?? '-'), ENT_QUOTES, 'UTF-8') . '</li>'
              . '<li>Keperluan: ' . htmlspecialchars((string)($row['keperluan'] ?? '-'), ENT_QUOTES, 'UTF-8') . '</li>'
              . '</ul>'
              . '<p>Silakan cek detail di sistem untuk informasi lebih lanjut.</p>';

    $textBase = "Pengingat: Surat Tugas {$nomor} pada {$tanggalView}.\nTujuan: " . ($row['tujuan'] ?? '-') . "\nKeperluan: " . ($row['keperluan'] ?? '-') . "\n";

    // Determine send time (07:00 local H-1)
    $sendAt = date('Y-m-d 07:00:00', strtotime($tanggal . ' -1 day'));
    if (strtotime($sendAt) < time()) { $sendAt = date('Y-m-d H:i:s'); }

    $sourceType = 'surat_tugas_h1';
    $sourceKey = (string)$sid;

    // Recipients: pengguna_id (requester)
    $candidates = [];
    $pu = (int)($row['pengguna_id'] ?? 0);
    if ($pu > 0 && $stmt_get_user) {
        $stmt_get_user->bind_param('i', $pu);
        $stmt_get_user->execute();
        $urow = $stmt_get_user->get_result()->fetch_assoc();
        $stmt_get_user->close();
        if ($urow && filter_var($urow['email'] ?? '', FILTER_VALIDATE_EMAIL)) {
            $candidates[] = $urow;
        }
    }

    // driver_id on surat_tugas (if present)
    if (!empty($row['driver_id'])) {
        $drv = (int)$row['driver_id'];
        if ($drv > 0 && $stmt_get_user) {
            $stmt_get_user->bind_param('i', $drv);
            $stmt_get_user->execute();
            $drow = $stmt_get_user->get_result()->fetch_assoc();
            $stmt_get_user->close();
            if ($drow && filter_var($drow['email'] ?? '', FILTER_VALIDATE_EMAIL)) {
                $candidates[] = $drow;
            }
        }
    }

    // vehicle assigned pengguna (kendaraan.pengguna_id)
    if ($kendId > 0 && $stmt_get_kend_pengguna) {
        $stmt_get_kend_pengguna->bind_param('i', $kendId);
        $stmt_get_kend_pengguna->execute();
        $kp = $stmt_get_kend_pengguna->get_result()->fetch_assoc();
        $stmt_get_kend_pengguna->close();
        if (!empty($kp['pengguna_id'])) {
            $pg = (int)$kp['pengguna_id'];
            if ($pg > 0 && $stmt_get_user) {
                $stmt_get_user->bind_param('i', $pg);
                $stmt_get_user->execute();
                $pgrow = $stmt_get_user->get_result()->fetch_assoc();
                $stmt_get_user->close();
                if ($pgrow && filter_var($pgrow['email'] ?? '', FILTER_VALIDATE_EMAIL)) {
                    $candidates[] = $pgrow;
                }
            }
        }
    }

    // Deduplicate by email
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

$ins->close();

echo "Queue surat_tugas reminders selesai. Inserted={$inserted}, Skipped={$skipped}\n";
