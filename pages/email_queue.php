<?php
if (!is_logged_in() || get_current_role() !== 'admin') {
    header('Location: index.php?page=403');
    exit;
}

require_once dirname(__DIR__) . '/lib/mailer.php';

$msg = '';
$action = $_POST['action'] ?? '';

if ($_POST) {
    if (!validate_csrf_token($_POST['csrf_token'] ?? '')) {
        $msg = '<div class="alert alert-danger">Token keamanan tidak valid!</div>';
        $action = '';
    }
}

// ── Test SMTP ──────────────────────────────────────────────────
if ($action === 'test_smtp') {
    $test_to = trim($_POST['test_email'] ?? '');
    if (!filter_var($test_to, FILTER_VALIDATE_EMAIL)) {
        $msg = '<div class="alert alert-warning">Masukkan alamat email tujuan yang valid untuk test.</div>';
    } else {
        $err = null;
        $ok = app_send_email(
            $test_to,
            $test_to,
            'Test SMTP — Sistem Randis ' . date('d/m/Y H:i'),
            '<div style="font-family:sans-serif;max-width:500px;margin:auto;padding:24px;border:1px solid #ddd;border-radius:8px">'
            . '<h2 style="color:#1f6f8b">Test Email — SI-KENDI RANDIS</h2>'
            . '<p>Email uji coba berhasil dikirim dari Sistem Randis.</p>'
            . '<p>Jika Anda menerima email ini, konfigurasi SMTP sudah benar.</p>'
            . '<hr><small>Dikirim: ' . date('d/m/Y H:i:s') . '</small></div>',
            'Test email dari Sistem Randis. Konfigurasi SMTP berhasil.',
            $err
        );
        if ($ok) {
            $msg = '<div class="alert alert-success"><i class="fas fa-check-circle me-2"></i>Test email berhasil dikirim ke <strong>' . htmlspecialchars($test_to) . '</strong>.</div>';
        } else {
            $msg = '<div class="alert alert-danger"><i class="fas fa-times-circle me-2"></i>Gagal: ' . htmlspecialchars($err ?? 'Unknown error') . '</div>';
        }
    }
}

// ── Send pending emails now ────────────────────────────────────
if ($action === 'send_pending') {
    $tbl_check = $mysqli->query("SHOW TABLES LIKE 'email_reminder_jobs'");
    if (!$tbl_check || $tbl_check->num_rows === 0) {
        $msg = '<div class="alert alert-warning">Tabel email_reminder_jobs belum ada. Jalankan migration terlebih dahulu.</div>';
    } else {
        $lim = 100;
        $stmt_jobs = $mysqli->prepare("SELECT id, recipient_email, recipient_name, subject, body_html, body_text FROM email_reminder_jobs WHERE status = 'pending' AND send_at <= NOW() AND attempt_count < max_attempts ORDER BY send_at ASC LIMIT ?");
        $stmt_jobs->bind_param('i', $lim);
        $stmt_jobs->execute();
        $jobs = $stmt_jobs->get_result()->fetch_all(MYSQLI_ASSOC);
        $stmt_jobs->close();

        if (empty($jobs)) {
            $msg = '<div class="alert alert-info">Tidak ada email pending yang jatuh tempo saat ini.</div>';
        } else {
            $sent = $failed = 0;
            $upd_sent = $mysqli->prepare("UPDATE email_reminder_jobs SET status='sent', sent_at=NOW(), attempt_count=attempt_count+1, last_error=NULL, updated_at=NOW() WHERE id=?");
            $upd_fail = $mysqli->prepare("UPDATE email_reminder_jobs SET status=IF(attempt_count+1>=max_attempts,'failed','pending'), attempt_count=attempt_count+1, last_error=?, updated_at=NOW() WHERE id=?");
            foreach ($jobs as $job) {
                $id = (int)$job['id'];
                $err = null;
                $ok = app_send_email((string)$job['recipient_email'], (string)($job['recipient_name'] ?? ''), (string)$job['subject'], (string)$job['body_html'], (string)($job['body_text'] ?? ''), $err);
                if ($ok) {
                    $upd_sent->bind_param('i', $id); $upd_sent->execute(); $sent++;
                } else {
                    $e = trim((string)$err) ?: 'Unknown error';
                    $upd_fail->bind_param('si', $e, $id); $upd_fail->execute(); $failed++;
                }
            }
            $upd_sent->close(); $upd_fail->close();
            $cls = $failed > 0 ? 'warning' : 'success';
            $msg = '<div class="alert alert-' . $cls . '"><i class="fas fa-envelope me-2"></i>Selesai: <strong>' . $sent . '</strong> terkirim, <strong>' . $failed . '</strong> gagal dari ' . count($jobs) . ' email.</div>';
        }
    }
}

// ── Queue reminders via CLI ─────────────────────────────────────
if ($action === 'queue_reminders') {
    $php = PHP_BINARY ?: 'php';
    $base = dirname(__DIR__);
    $out1 = @shell_exec(escapeshellarg($php) . ' ' . escapeshellarg($base . DIRECTORY_SEPARATOR . 'scripts' . DIRECTORY_SEPARATOR . 'queue_surat_tugas_email_reminders.php') . ' 2>&1');
    $out2 = @shell_exec(escapeshellarg($php) . ' ' . escapeshellarg($base . DIRECTORY_SEPARATOR . 'scripts' . DIRECTORY_SEPARATOR . 'queue_maintenance_email_reminders.php') . ' 2>&1');
    if ($out1 === null && $out2 === null) {
        $msg = '<div class="alert alert-warning"><i class="fas fa-exclamation-triangle me-2"></i><strong>shell_exec</strong> tidak tersedia. Jalankan secara manual:<br>'
             . '<code>php scripts/queue_surat_tugas_email_reminders.php</code><br>'
             . '<code>php scripts/queue_maintenance_email_reminders.php</code></div>';
    } else {
        $out = trim(($out1 ?? '') . "\n" . ($out2 ?? ''));
        $msg = '<div class="alert alert-success"><i class="fas fa-check-circle me-2"></i>Queue selesai.<br><pre class="mt-2 mb-0 small">' . htmlspecialchars($out) . '</pre></div>';
    }
}

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

$smtp_masked = MAIL_USERNAME !== '' ? (substr(MAIL_USERNAME, 0, 3) . '***@' . (explode('@', MAIL_USERNAME)[1] ?? '?')) : '(kosong)';
$is_smtp_ready = (MAIL_TRANSPORT === 'smtp' && MAIL_HOST !== '' && MAIL_USERNAME !== '' && MAIL_PASSWORD !== '');
?>

<div class="p-4 mb-4 rounded" style="background: linear-gradient(135deg, #12354a 0%, #1f6f8b 100%); color:#fff;">
    <h1 class="mb-1"><i class="fas fa-envelope-open-text me-2"></i>Email Queue & SMTP</h1>
    <p class="mb-0 opacity-75">Konfigurasi SMTP dan antrean reminder otomatis</p>
</div>

<?= $msg ?>

<div class="row mb-4">
    <!-- SMTP Config Card -->
    <div class="col-lg-5 mb-4">
        <div class="card shadow-sm h-100">
            <div class="card-header bg-dark text-white">
                <strong><i class="fas fa-cog me-2"></i>Konfigurasi SMTP</strong>
            </div>
            <div class="card-body">
                <table class="table table-sm table-borderless mb-3">
                    <tr><td class="text-muted" width="130">Transport</td><td><code><?= htmlspecialchars(MAIL_TRANSPORT) ?></code></td></tr>
                    <tr><td class="text-muted">Host</td><td><code><?= htmlspecialchars(MAIL_HOST) ?></code></td></tr>
                    <tr><td class="text-muted">Port</td><td><code><?= MAIL_PORT ?></code></td></tr>
                    <tr><td class="text-muted">Encryption</td><td><code><?= htmlspecialchars(MAIL_ENCRYPTION) ?></code></td></tr>
                    <tr><td class="text-muted">Username</td><td><code><?= htmlspecialchars($smtp_masked) ?></code></td></tr>
                    <tr><td class="text-muted">From</td><td><code><?= htmlspecialchars(MAIL_FROM_ADDRESS) ?></code></td></tr>
                    <tr><td class="text-muted">Status</td>
                        <td>
                            <?php if ($is_smtp_ready): ?>
                                <span class="badge bg-success"><i class="fas fa-check me-1"></i>Terkonfigurasi</span>
                            <?php else: ?>
                                <span class="badge bg-danger"><i class="fas fa-times me-1"></i>Belum dikonfigurasi</span>
                            <?php endif; ?>
                        </td>
                    </tr>
                </table>
                <p class="text-muted small mb-3">Edit <code>config/mail.php</code> untuk mengubah kredensial SMTP.</p>

                <form method="post" class="border-top pt-3">
                    <input type="hidden" name="csrf_token" value="<?= generate_csrf_token() ?>">
                    <input type="hidden" name="action" value="test_smtp">
                    <label class="form-label small fw-bold">Test Kirim Email</label>
                    <div class="input-group input-group-sm">
                        <input type="email" name="test_email" class="form-control" placeholder="email@tujuan.com" required>
                        <button type="submit" class="btn btn-outline-primary">
                            <i class="fas fa-paper-plane me-1"></i>Kirim Test
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Queue Stats + Actions -->
    <div class="col-lg-7 mb-4">
        <div class="card shadow-sm h-100">
            <div class="card-header bg-dark text-white">
                <strong><i class="fas fa-list me-2"></i>Status Antrean Email</strong>
            </div>
            <div class="card-body">
                <?php if (!$has_table): ?>
                    <div class="alert alert-warning mb-3">
                        Tabel <code>email_reminder_jobs</code> belum ada. Jalankan migration <code>migrations/2026-04-23_create_email_reminder_jobs.sql</code> terlebih dahulu.
                    </div>
                <?php else: ?>
                    <div class="row text-center mb-4">
                        <div class="col-4">
                            <div class="p-3 rounded" style="background:#fff3cd;">
                                <div class="fs-3 fw-bold text-warning"><?= number_format($stats['pending']) ?></div>
                                <div class="small text-muted">Pending</div>
                            </div>
                        </div>
                        <div class="col-4">
                            <div class="p-3 rounded" style="background:#d1e7dd;">
                                <div class="fs-3 fw-bold text-success"><?= number_format($stats['sent']) ?></div>
                                <div class="small text-muted">Terkirim</div>
                            </div>
                        </div>
                        <div class="col-4">
                            <div class="p-3 rounded" style="background:#f8d7da;">
                                <div class="fs-3 fw-bold text-danger"><?= number_format($stats['failed']) ?></div>
                                <div class="small text-muted">Gagal</div>
                            </div>
                        </div>
                    </div>
                <?php endif; ?>

                <form method="post" class="d-flex flex-wrap gap-2">
                    <input type="hidden" name="csrf_token" value="<?= generate_csrf_token() ?>">
                    <button type="submit" name="action" value="queue_reminders" class="btn btn-outline-secondary btn-sm">
                        <i class="fas fa-calendar-plus me-1"></i>Queue Reminder Sekarang
                    </button>
                    <?php if ($has_table && $stats['pending'] > 0): ?>
                        <button type="submit" name="action" value="send_pending" class="btn btn-primary btn-sm">
                            <i class="fas fa-paper-plane me-1"></i>Kirim <?= $stats['pending'] ?> Email Pending
                        </button>
                    <?php else: ?>
                        <button type="submit" name="action" value="send_pending" class="btn btn-outline-primary btn-sm" <?= !$has_table ? 'disabled' : '' ?>>
                            <i class="fas fa-paper-plane me-1"></i>Kirim Email Pending
                        </button>
                    <?php endif; ?>
                </form>

                <hr>
                <p class="small text-muted mb-1"><strong>Jadwal otomatis (Windows Task Scheduler):</strong></p>
                <p class="small text-muted mb-0">Setiap 1 jam: <code>php scripts/queue_surat_tugas_email_reminders.php</code></p>
                <p class="small text-muted mb-0">Setiap 1 jam: <code>php scripts/queue_maintenance_email_reminders.php</code></p>
                <p class="small text-muted mb-0">Setiap 5 menit: <code>php scripts/send_scheduled_emails.php</code></p>
            </div>
        </div>
    </div>
</div>

<?php if ($has_table && !empty($recent_jobs)): ?>
<div class="card shadow-sm">
    <div class="card-header bg-secondary text-white">
        <strong><i class="fas fa-history me-2"></i>25 Email Terbaru</strong>
    </div>
    <div class="card-body p-0">
        <div class="table-responsive">
            <table class="table table-sm table-hover mb-0">
                <thead class="table-light">
                    <tr>
                        <th>Penerima</th>
                        <th>Subjek</th>
                        <th>Tipe</th>
                        <th>Status</th>
                        <th>Kirim/Dikirim</th>
                        <th>Error</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($recent_jobs as $j): ?>
                    <?php
                        $badge = match(strtolower($j['status'] ?? '')) {
                            'sent'    => 'bg-success',
                            'failed'  => 'bg-danger',
                            'pending' => 'bg-warning text-dark',
                            default   => 'bg-secondary',
                        };
                    ?>
                    <tr>
                        <td>
                            <div><?= htmlspecialchars($j['recipient_name'] ?? '-') ?></div>
                            <small class="text-muted"><?= htmlspecialchars($j['recipient_email']) ?></small>
                        </td>
                        <td class="text-truncate" style="max-width:200px;" title="<?= htmlspecialchars($j['subject']) ?>">
                            <?= htmlspecialchars($j['subject']) ?>
                        </td>
                        <td><small><?= htmlspecialchars(str_replace('_', ' ', $j['source_type'] ?? '-')) ?></small></td>
                        <td><span class="badge <?= $badge ?>"><?= htmlspecialchars(ucfirst($j['status'] ?? '-')) ?></span></td>
                        <td>
                            <small><?= !empty($j['sent_at']) ? date('d/m/y H:i', strtotime($j['sent_at'])) : date('d/m/y H:i', strtotime($j['send_at'] ?? 'now')) ?></small>
                            <?php if ((int)$j['attempt_count'] > 0): ?>
                                <br><small class="text-muted"><?= $j['attempt_count'] ?> percobaan</small>
                            <?php endif; ?>
                        </td>
                        <td>
                            <?php if (!empty($j['last_error'])): ?>
                                <small class="text-danger" title="<?= htmlspecialchars($j['last_error']) ?>">
                                    <?= htmlspecialchars(mb_substr($j['last_error'], 0, 60)) ?>...
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
    <div class="alert alert-info"><i class="fas fa-info-circle me-2"></i>Antrean email masih kosong. Klik <strong>Queue Reminder Sekarang</strong> untuk mengisi antrean.</div>
<?php endif; ?>
