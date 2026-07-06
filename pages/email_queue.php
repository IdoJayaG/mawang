<?php
if (!is_logged_in() || get_current_role() !== 'admin') {
    header('Location: index.php?page=403');
    exit;
}

require_once dirname(__DIR__) . '/lib/mailer.php';
require_once dirname(__DIR__) . '/lib/email_triggers.php';

$msg = '';
$action = $_POST['action'] ?? '';

if ($_POST) {
    if (!validate_csrf_token($_POST['csrf_token'] ?? '')) {
        $msg = '<div class="alert alert-danger">Token keamanan tidak valid!</div>';
        $action = '';
    }
}

// ── Send pending emails now ────────────────────────────────────
if ($action === 'send_pending') {
    $r = send_pending_reminder_emails($mysqli, 100);
    if (!$r['ok']) {
        $msg = '<div class="alert alert-warning">' . htmlspecialchars($r['message']) . '</div>';
    } else {
        $cls = $r['failed'] > 0 ? 'warning' : 'success';
        $msg = '<div class="alert alert-' . $cls . '"><i class="fas fa-envelope me-2"></i>Selesai: <strong>' . $r['sent'] . '</strong> terkirim, <strong>' . $r['failed'] . '</strong> gagal.</div>';
    }
}

// ── Queue reminders (H-1/H0 surat tugas & jadwal perawatan) ─────
if ($action === 'queue_reminders') {
    $r1 = queue_surat_tugas_reminders($mysqli);
    $r2 = queue_maintenance_reminders($mysqli);
    $out = "Surat Tugas: {$r1['message']}\nJadwal Perawatan: {$r2['message']}";
    $cls = ($r1['ok'] && $r2['ok']) ? 'success' : 'warning';
    $msg = '<div class="alert alert-' . $cls . '"><i class="fas fa-check-circle me-2"></i>Queue selesai.<br><pre class="mt-2 mb-0 small">' . htmlspecialchars($out) . '</pre></div>';
}

// ── Info automasi otomatis (pseudo-cron di config.php) ───────────
$auto_marker_file = dirname(__DIR__) . '/logs/email_automation_last_run.txt';
$auto_last_run = is_file($auto_marker_file) ? (int)@file_get_contents($auto_marker_file) : 0;

// ── Stats ──────────────────────────────────────────────────────
$stats = ['pending' => 0, 'sent' => 0, 'failed' => 0];
$has_table = false;
$tbl_q = $mysqli->query("SHOW TABLES LIKE 'email_reminder_jobs'");
if ($tbl_q && $tbl_q->num_rows > 0) {
    $has_table = true;
    $res_s = $mysqli->query("SELECT status, COUNT(*) c FROM email_reminder_jobs GROUP BY status");
    if ($res_s) {
        while ($r = $res_s->fetch_assoc()) {
            $k = strtolower((string)$r['status']);
            if (array_key_exists($k, $stats)) $stats[$k] = (int)$r['c'];
        }
    }
}

// ── Recent jobs ────────────────────────────────────────────────
$recent_jobs = [];
if ($has_table) {
    $res_r = $mysqli->query("SELECT id, recipient_email, recipient_name, subject, status, source_type, send_at, sent_at, attempt_count, last_error FROM email_reminder_jobs ORDER BY created_at DESC, id DESC LIMIT 25");
    if ($res_r) $recent_jobs = $res_r->fetch_all(MYSQLI_ASSOC);
}

?>

<!-- Gradient Header -->
<div class="gradient-header mb-4">
    <div class="d-flex align-items-center justify-content-between flex-wrap gap-2">
        <div class="d-flex align-items-center gap-3">
            <div style="width:56px;height:56px;background:rgba(255,255,255,0.2);border-radius:50%;display:flex;align-items:center;justify-content:center;">
                <i class="fas fa-envelope-open-text fa-2x text-white"></i>
            </div>
            <div>
                <h4 class="mb-0 fw-bold text-white">Email Queue</h4>
                <p class="mb-0 text-white opacity-75 small">Kelola antrean pengiriman reminder otomatis</p>
            </div>
        </div>
        <form method="post" action="index.php?page=email_queue" class="d-flex flex-wrap gap-2">
            <input type="hidden" name="csrf_token" value="<?= generate_csrf_token() ?>">
            <button type="submit" name="action" value="queue_reminders"
                    class="btn btn-light btn-sm">
                <i class="fas fa-calendar-plus me-1"></i>Queue Reminder Sekarang
            </button>
            <?php if ($has_table && $stats['pending'] > 0): ?>
                <button type="submit" name="action" value="send_pending"
                        class="btn btn-warning btn-sm fw-bold">
                    <i class="fas fa-paper-plane me-1"></i>Kirim <?= $stats['pending'] ?> Email Pending
                </button>
            <?php else: ?>
                <button type="submit" name="action" value="send_pending"
                        class="btn btn-outline-light btn-sm" <?= !$has_table ? 'disabled' : '' ?>>
                    <i class="fas fa-paper-plane me-1"></i>Kirim Email Pending
                </button>
            <?php endif; ?>
        </form>
    </div>
</div>

<?= $msg ?>

<!-- Status Automasi -->
<div class="alert <?= $auto_last_run > 0 ? 'alert-success' : 'alert-secondary' ?> shadow-sm mb-4 d-flex align-items-center gap-2">
    <i class="fas fa-robot"></i>
    <div>
        <?php if ($auto_last_run > 0): ?>
            <strong>Automasi aktif.</strong> Terakhir dijalankan otomatis:
            <strong><?= date('d/m/Y H:i:s', $auto_last_run) ?></strong> WIB
            (setiap kunjungan halaman, maks. tiap 10 menit — antre H-1/H0 lalu kirim email pending).
        <?php else: ?>
            <strong>Automasi belum pernah berjalan.</strong> Akan otomatis aktif saat ada aktivitas di sistem
            (dipicu dari <code>config.php</code>, tiap ~10 menit).
        <?php endif; ?>
        Notifikasi persetujuan surat tugas dikirim <strong>instan</strong> saat pimpinan approve — tidak menunggu siklus ini.
    </div>
</div>

<!-- Stat Cards -->
<?php if (!$has_table): ?>
    <div class="alert alert-warning shadow-sm mb-4">
        <i class="fas fa-exclamation-triangle me-2"></i>
        Tabel <code>email_reminder_jobs</code> belum ada. Jalankan migration
        <code>migrations/2026-04-23_create_email_reminder_jobs.sql</code> terlebih dahulu.
    </div>
<?php else: ?>
<div class="row g-3 mb-4">
    <div class="col-md-4">
        <div class="card bg-warning text-dark shadow-sm h-100">
            <div class="card-body d-flex align-items-center gap-3">
                <div style="width:60px;height:60px;background:rgba(0,0,0,0.1);border-radius:50%;display:flex;align-items:center;justify-content:center;flex-shrink:0;">
                    <i class="fas fa-clock fa-2x text-dark"></i>
                </div>
                <div>
                    <h3 class="mb-0 fw-bold"><?= number_format($stats['pending']) ?></h3>
                    <p class="mb-0 fw-semibold opacity-75">Email Pending</p>
                </div>
            </div>
        </div>
    </div>
    <div class="col-md-4">
        <div class="card bg-success text-white shadow-sm h-100">
            <div class="card-body d-flex align-items-center gap-3">
                <div style="width:60px;height:60px;background:rgba(255,255,255,0.2);border-radius:50%;display:flex;align-items:center;justify-content:center;flex-shrink:0;">
                    <i class="fas fa-check-circle fa-2x text-white"></i>
                </div>
                <div>
                    <h3 class="mb-0 fw-bold"><?= number_format($stats['sent']) ?></h3>
                    <p class="mb-0 fw-semibold opacity-75">Terkirim</p>
                </div>
            </div>
        </div>
    </div>
    <div class="col-md-4">
        <div class="card bg-danger text-white shadow-sm h-100">
            <div class="card-body d-flex align-items-center gap-3">
                <div style="width:60px;height:60px;background:rgba(255,255,255,0.2);border-radius:50%;display:flex;align-items:center;justify-content:center;flex-shrink:0;">
                    <i class="fas fa-times-circle fa-2x text-white"></i>
                </div>
                <div>
                    <h3 class="mb-0 fw-bold"><?= number_format($stats['failed']) ?></h3>
                    <p class="mb-0 fw-semibold opacity-75">Gagal</p>
                </div>
            </div>
        </div>
    </div>
</div>
<?php endif; ?>

<!-- Recent Jobs Table -->
<?php if ($has_table && !empty($recent_jobs)): ?>
<div class="card shadow-sm">
    <div class="card-header bg-primary text-white d-flex align-items-center justify-content-between">
        <h6 class="mb-0"><i class="fas fa-history me-2"></i>25 Email Terbaru</h6>
        <span class="badge bg-white text-primary"><?= count($recent_jobs) ?> data</span>
    </div>
    <div class="card-body p-0">
        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0">
                <thead class="table-light">
                    <tr>
                        <th class="ps-3">Penerima</th>
                        <th>Subjek</th>
                        <th>Tipe</th>
                        <th>Status</th>
                        <th>Waktu</th>
                        <th>Keterangan</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($recent_jobs as $j): ?>
                    <?php
                        $badge = match(strtolower($j['status'] ?? '')) {
                            'sent'    => 'bg-success text-white',
                            'failed'  => 'bg-danger text-white',
                            'pending' => 'bg-warning text-dark',
                            default   => 'bg-secondary text-white',
                        };
                        $icon = match(strtolower($j['status'] ?? '')) {
                            'sent'    => 'text-success',
                            'failed'  => 'fa-times-circle text-danger',
                            'pending' => 'fa-clock text-warning',
                            default   => 'fa-circle text-secondary',
                        };
                    ?>
                    <tr>
                        <td class="ps-3">
                            <div class="fw-semibold"><?= htmlspecialchars($j['recipient_name'] ?? '-') ?></div>
                            <small class="text-muted"><?= htmlspecialchars($j['recipient_email']) ?></small>
                        </td>
                        <td style="max-width:220px;">
                            <div class="text-truncate" title="<?= htmlspecialchars($j['subject']) ?>">
                                <?= htmlspecialchars($j['subject']) ?>
                            </div>
                        </td>
                        <td>
                            <span class="badge bg-light text-dark border">
                                <?= htmlspecialchars(ucwords(str_replace('_', ' ', $j['source_type'] ?? '-'))) ?>
                            </span>
                        </td>
                        <td>
                            <span class="badge <?= $badge ?>">
                                <i class="fas <?= $icon ?> me-1"></i>
                                <?= htmlspecialchars(ucfirst($j['status'] ?? '-')) ?>
                            </span>
                        </td>
                        <td>
                            <small class="fw-semibold">
                                <?= !empty($j['sent_at'])
                                    ? date('d/m/Y H:i', strtotime($j['sent_at']))
                                    : date('d/m/Y H:i', strtotime($j['send_at'] ?? 'now')) ?>
                            </small>
                            <?php if ((int)$j['attempt_count'] > 0): ?>
                                <br><small class="text-muted"><?= $j['attempt_count'] ?>x percobaan</small>
                            <?php endif; ?>
                        </td>
                        <td>
                            <?php if (!empty($j['last_error'])): ?>
                                <small class="text-danger" title="<?= htmlspecialchars($j['last_error']) ?>">
                                    <i class="fas fa-exclamation-circle me-1"></i>
                                    <?= htmlspecialchars(mb_substr($j['last_error'], 0, 55)) ?>…
                                </small>
                            <?php else: ?>
                                <span class="text-muted">—</span>
                            <?php endif; ?>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>
<?php elseif ($has_table): ?>
    <div class="alert alert-info shadow-sm">
        <i class="fas fa-info-circle me-2"></i>
        Antrean email masih kosong. Klik <strong>Queue Reminder Sekarang</strong> untuk mengisi antrean.
    </div>
<?php endif; ?>
