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

// Upcoming H-1 items
$upcoming_items = build_upcoming_items($pengguna_id);

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
    <div class="card-header"><h6 class="mb-0">Pengingat Besok</h6></div>
    <div class="card-body">
        <?php if (!empty($upcoming_items)): ?>
            <ul class="list-unstyled mb-0">
            <?php foreach ($upcoming_items as $it): ?>
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

<?php