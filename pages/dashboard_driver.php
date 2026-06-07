<?php
require_once 'includes/auth.php';
require_login();
require_role('driver');

$pengguna_id = get_current_user_id();
$user = get_logged_in_user();

// Assigned vehicles
$assigned_vehicles = [];
if (db_table_exists('kendaraan')) {
    $cols = db_table_columns('kendaraan');
    if (in_array('pengguna_id', $cols, true)) {
        $st = $GLOBALS['mysqli']->prepare("SELECT id, no_reg, merk, tipe, status_kendaraan FROM kendaraan WHERE pengguna_id = ? ORDER BY id DESC LIMIT 50");
        if ($st) { $st->bind_param('i', $pengguna_id); $st->execute(); $assigned_vehicles = $st->get_result()->fetch_all(MYSQLI_ASSOC); $st->close(); }
    } else {
        if (db_table_exists('peminjaman_kendaraan')) {
            $cols2 = db_table_columns('peminjaman_kendaraan');
            $borrower_col = null; foreach (['peminjam_id','pemohon_id','user_id','pengguna_id'] as $c) { if (in_array($c, $cols2, true)) { $borrower_col = $c; break; } }
            if ($borrower_col) {
                $bindId = in_array($borrower_col, ['peminjam_id','pemohon_id','user_id'], true) ? (int)($_SESSION['user_id'] ?? 0) : $pengguna_id;
                $sql = "SELECT DISTINCT k.id, k.no_reg, k.merk, k.tipe, k.status_kendaraan FROM peminjaman_kendaraan pk LEFT JOIN kendaraan k ON pk.kendaraan_id = k.id WHERE pk." . $borrower_col . " = ? AND LOWER(pk.status) IN ('approved','ongoing') LIMIT 50";
                $st = $GLOBALS['mysqli']->prepare($sql);
                if ($st) { $st->bind_param('i', $bindId); $st->execute(); $assigned_vehicles = $st->get_result()->fetch_all(MYSQLI_ASSOC); $st->close(); }
            }
        }
    }
}

// Add vehicles assigned via surat_tugas driver_id when available
if (db_table_exists('surat_tugas')) {
    $st_cols = db_table_columns('surat_tugas');
    if (in_array('driver_id', $st_cols, true)) {
        $st = $GLOBALS['mysqli']->prepare("SELECT DISTINCT k.id, k.no_reg, k.merk, k.tipe, k.status_kendaraan FROM surat_tugas s JOIN kendaraan k ON s.kendaraan_id = k.id WHERE s.driver_id = ? AND s.status IN ('Disetujui','Dalam Perjalanan')");
        if ($st) {
            $st->bind_param('i', $pengguna_id);
            $st->execute();
            $extra = $st->get_result()->fetch_all(MYSQLI_ASSOC);
            $st->close();

            if (!empty($extra)) {
                $by_id = [];
                foreach ($assigned_vehicles as $veh) {
                    $by_id[(int)($veh['id'] ?? 0)] = $veh;
                }
                foreach ($extra as $veh) {
                    $by_id[(int)($veh['id'] ?? 0)] = $veh;
                }
                $assigned_vehicles = array_values($by_id);
            }
        }
    }
}

// Active loans count
$active_loans_count = 0;
if (db_table_exists('peminjaman_kendaraan')) {
    $cols = db_table_columns('peminjaman_kendaraan');
    $borrower_col = null; foreach (['peminjam_id','pemohon_id','user_id','pengguna_id'] as $c) { if (in_array($c, $cols, true)) { $borrower_col = $c; break; } }
    if ($borrower_col) {
        $bindId = in_array($borrower_col, ['peminjam_id','pemohon_id','user_id'], true) ? (int)($_SESSION['user_id'] ?? 0) : $pengguna_id;
        $st = $GLOBALS['mysqli']->prepare("SELECT COUNT(*) c FROM peminjaman_kendaraan WHERE " . $borrower_col . " = ? AND LOWER(status) IN ('approved','ongoing')");
        if ($st) { $st->bind_param('i', $bindId); $st->execute(); $active_loans_count = (int)($st->get_result()->fetch_assoc()['c'] ?? 0); $st->close(); }
    }
}

// BBM logs count
$bbm_count = 0;
if (db_table_exists('log_bahan_bakar')) {
    $st = $GLOBALS['mysqli']->prepare("SELECT COUNT(*) as c FROM log_bahan_bakar WHERE user_id = ?");
    if ($st) { $st->bind_param('i', $pengguna_id); $st->execute(); $bbm_count = (int)($st->get_result()->fetch_assoc()['c'] ?? 0); $st->close(); }
}

// Upcoming items for today and tomorrow
$today_date = date('Y-m-d');
$tomorrow_date = date('Y-m-d', strtotime('+1 day'));
$today_items = build_upcoming_items($pengguna_id, $today_date);
$tomorrow_items = build_upcoming_items($pengguna_id, $tomorrow_date);

?>

<div class="page-header mb-3">
    <h1><i class="fas fa-tachometer-alt"></i> Dashboard Driver</h1>
    <p class="mb-0">Selamat datang, <strong><?= htmlspecialchars($user['nama_lengkap'] ?? '') ?></strong></p>
</div>

<div class="row g-3 mb-4">
    <div class="col-md-4">
        <div class="card h-100">
            <div class="card-body d-flex align-items-center gap-3">
                <div class="icon bg-primary text-white rounded-circle d-flex align-items-center justify-content-center" style="width:56px;height:56px;font-size:20px;"><i class="fas fa-car"></i></div>
                <div>
                    <div class="h4 mb-0"><?= count($assigned_vehicles) ?></div>
                    <div class="text-muted">Kendaraan Ditugaskan</div>
                </div>
            </div>
        </div>
    </div>
    <div class="col-md-4">
        <div class="card h-100">
            <div class="card-body d-flex align-items-center gap-3">
                <div class="icon bg-success text-white rounded-circle d-flex align-items-center justify-content-center" style="width:56px;height:56px;font-size:20px;"><i class="fas fa-road"></i></div>
                <div>
                    <div class="h4 mb-0"><?= $active_loans_count ?></div>
                    <div class="text-muted">Peminjaman Aktif</div>
                </div>
            </div>
        </div>
    </div>
    <div class="col-md-4">
        <div class="card h-100">
            <div class="card-body d-flex align-items-center gap-3">
                <div class="icon bg-warning text-dark rounded-circle d-flex align-items-center justify-content-center" style="width:56px;height:56px;font-size:20px;"><i class="fas fa-gas-pump"></i></div>
                <div>
                    <div class="h4 mb-0"><?= $bbm_count ?></div>
                    <div class="text-muted">Log BBM</div>
                </div>
            </div>
        </div>
    </div>
</div>

<div class="card mb-3">
    <div class="card-header"><h6 class="mb-0">Kendaraan Saya</h6></div>
    <div class="card-body">
        <?php if (!empty($assigned_vehicles)): ?>
            <ul class="list-unstyled mb-0">
            <?php foreach ($assigned_vehicles as $veh): ?>
                <?php
                $veh_label = trim((string)($veh['no_reg'] ?? ''));
                if ($veh_label === '') { $veh_label = '-'; }
                $veh_model = trim((string)($veh['merk'] ?? '') . ' ' . (string)($veh['tipe'] ?? ''));
                $veh_status = trim((string)($veh['status_kendaraan'] ?? '-'));
                ?>
                <li class="mb-2">
                    <div class="fw-semibold"><?= htmlspecialchars($veh_label) ?></div>
                    <div class="small text-muted"><?= htmlspecialchars($veh_model !== '' ? $veh_model : '-') ?> • <?= htmlspecialchars($veh_status !== '' ? $veh_status : '-') ?></div>
                </li>
            <?php endforeach; ?>
            </ul>
        <?php else: ?>
            <div class="text-muted">Belum ada kendaraan ditugaskan.</div>
        <?php endif; ?>
    </div>
</div>

<div class="row g-3 mb-3">
    <div class="col-md-6">
        <div class="card h-100">
            <div class="card-header"><h6 class="mb-0">Pengingat Hari Ini</h6></div>
            <div class="card-body">
                <?php if (!empty($today_items)): ?>
                    <ul class="list-unstyled mb-0">
                    <?php foreach ($today_items as $it): ?>
                        <li class="mb-2">
                            <div class="fw-semibold"><?= htmlspecialchars($it['type']) ?> • <?= htmlspecialchars($it['label']) ?></div>
                            <div class="small text-muted"><?= date('d/m/Y', strtotime($it['date'])) ?> • <?= htmlspecialchars($it['note']) ?></div>
                        </li>
                    <?php endforeach; ?>
                    </ul>
                <?php else: ?>
                    <div class="text-muted">Tidak ada pengingat hari ini.</div>
                <?php endif; ?>
            </div>
        </div>
    </div>
    <div class="col-md-6">
        <div class="card h-100">
            <div class="card-header"><h6 class="mb-0">Pengingat Besok</h6></div>
            <div class="card-body">
                <?php if (!empty($tomorrow_items)): ?>
                    <ul class="list-unstyled mb-0">
                    <?php foreach ($tomorrow_items as $it): ?>
                        <li class="mb-2">
                            <div class="fw-semibold"><?= htmlspecialchars($it['type']) ?> • <?= htmlspecialchars($it['label']) ?></div>
                            <div class="small text-muted"><?= date('d/m/Y', strtotime($it['date'])) ?> • <?= htmlspecialchars($it['note']) ?></div>
                        </li>
                    <?php endforeach; ?>
                    </ul>
                <?php else: ?>
                    <div class="text-muted">Tidak ada pengingat untuk besok.</div>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<?php
$alert_items = [];
foreach ($today_items as $it) {
    $alert_items[] = [
        'when' => 'Hari ini',
        'type' => htmlspecialchars((string)($it['type'] ?? ''), ENT_QUOTES, 'UTF-8'),
        'label' => htmlspecialchars((string)($it['label'] ?? ''), ENT_QUOTES, 'UTF-8'),
        'date' => !empty($it['date']) ? date('d/m/Y', strtotime($it['date'])) : '-',
        'note' => htmlspecialchars((string)($it['note'] ?? ''), ENT_QUOTES, 'UTF-8'),
    ];
}
foreach ($tomorrow_items as $it) {
    $alert_items[] = [
        'when' => 'Besok',
        'type' => htmlspecialchars((string)($it['type'] ?? ''), ENT_QUOTES, 'UTF-8'),
        'label' => htmlspecialchars((string)($it['label'] ?? ''), ENT_QUOTES, 'UTF-8'),
        'date' => !empty($it['date']) ? date('d/m/Y', strtotime($it['date'])) : '-',
        'note' => htmlspecialchars((string)($it['note'] ?? ''), ENT_QUOTES, 'UTF-8'),
    ];
}

$alert_key = 'shown_driver_reminders';
$should_show_alert = !empty($alert_items) && (!isset($_SESSION[$alert_key]) || $_SESSION[$alert_key] !== $today_date);
?>

<?php if ($should_show_alert): ?>
<script>
document.addEventListener('DOMContentLoaded', function() {
    try {
        var items = <?php echo json_encode($alert_items, JSON_UNESCAPED_UNICODE); ?>;
        var grouped = { 'Hari ini': [], 'Besok': [] };
        items.forEach(function(it) {
            if (!grouped[it.when]) { grouped[it.when] = []; }
            grouped[it.when].push(it);
        });

        var html = '';
        ['Hari ini', 'Besok'].forEach(function(label) {
            if (!grouped[label] || grouped[label].length === 0) return;
            html += '<div style="margin-bottom:10px;"><strong>' + label + '</strong>';
            html += '<ul style="text-align:left; margin:6px 0 0 18px;">' +
                grouped[label].map(function(it) {
                    return '<li><strong>' + it.type + '</strong> &middot; ' + it.label + '<br><small>' + it.date + ' • ' + it.note + '</small></li>';
                }).join('') +
                '</ul></div>';
        });

        Swal.fire({
            icon: 'info',
            title: 'Pengingat Surat Tugas & Perawatan',
            html: html,
            confirmButtonText: 'OK'
        });
    } catch (e) { /* noop */ }
});
</script>
<?php $_SESSION[$alert_key] = $today_date; endif; ?>