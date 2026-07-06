<?php
require_once 'includes/auth.php';
require_login();
require_role('user');

$user_id = get_current_user_id();

// Helper: cek apakah kolom ada di tabel
function has_column($mysqli, $table, $column) {
    try {
        $res = $mysqli->query("SHOW COLUMNS FROM `" . $mysqli->real_escape_string($table) . "` LIKE '" . $mysqli->real_escape_string($column) . "'");
        if ($res && $res->num_rows > 0) { $res->free_result(); return true; }
        if ($res) $res->free_result();
    } catch (mysqli_sql_exception $e) { return false; }
    return false;
}

// Helper: cek apakah tabel ada
function table_exists_db($mysqli, $table) {
    try {
        $t = $mysqli->real_escape_string($table);
        $res = $mysqli->query("SHOW TABLES LIKE '" . $t . "'");
        $exists = $res && $res->num_rows > 0;
        if ($res) $res->free_result();
        return $exists;
    } catch (mysqli_sql_exception $e) { return false; }
}

// Helper: ambil seluruh kolom dari sebuah tabel
function get_table_columns($mysqli, $table) {
    $cols = [];
    try {
        $t = $mysqli->real_escape_string($table);
        $res = $mysqli->query("SHOW COLUMNS FROM `{$t}`");
        if ($res) { while ($r = $res->fetch_assoc()) { $cols[] = $r['Field']; } $res->free_result(); }
    } catch (mysqli_sql_exception $e) {}
    return $cols;
}

// Bangun SELECT dan JOIN secara dinamis berdasarkan kolom/tabel yang tersedia
$selects = "p.*, ua.id AS account_id, ua.username";
$joins = [];

if (has_column($mysqli, 'pengguna', 'kesatuan_id')) {
    $selects .= ", kes.nama_kesatuan, korps.nama_korps, m.nama_matra";
    $joins[] = "LEFT JOIN kesatuan kes ON p.kesatuan_id = kes.id";
    $joins[] = "LEFT JOIN korps korps ON kes.korps_id = korps.id";
    $joins[] = "LEFT JOIN matra m ON korps.matra_id = m.id";
} else {
    if (has_column($mysqli, 'pengguna', 'korps_id')) {
        $selects .= ", korps.nama_korps, m.nama_matra";
        $joins[] = "LEFT JOIN korps korps ON p.korps_id = korps.id";
        $joins[] = "LEFT JOIN matra m ON korps.matra_id = m.id";
    } elseif (has_column($mysqli, 'pengguna', 'matra_id')) {
        $selects .= ", m.nama_matra";
    } else {
        $selects .= ", '' as nama_kesatuan, '' as nama_korps, '' as nama_matra";
    }
}

$has_satuan_col = has_column($mysqli, 'pengguna', 'satuan_id');
$has_satuan_table = false;
try {
    $res = $mysqli->query("SHOW TABLES LIKE 'satuan'");
    if ($res && $res->num_rows > 0) { $has_satuan_table = true; }
    if ($res) $res->free_result();
} catch (mysqli_sql_exception $e) { $has_satuan_table = false; }
if ($has_satuan_col && $has_satuan_table) {
    $selects .= ", s.nama_satuan";
    $joins[] = "LEFT JOIN satuan s ON p.satuan_id = s.id";
} else {
    $selects .= ", '' as nama_satuan";
}

$sqlBase = "SELECT " . $selects . " FROM user_account ua JOIN pengguna p ON ua.pengguna_id = p.id ";
if (!empty($joins)) { $sqlBase .= " " . implode(' ', $joins); }

$account_id = get_current_account_id();
$pengguna_lookup = (int)get_current_user_id();

if (!empty($account_id)) {
    $sql = $sqlBase . " WHERE ua.id = ? LIMIT 1";
    $stmt = $mysqli->prepare($sql);
    $stmt->bind_param('i', $account_id);
} else {
    $sql = $sqlBase . " WHERE ua.pengguna_id = ? LIMIT 1";
    $stmt = $mysqli->prepare($sql);
    $stmt->bind_param('i', $pengguna_lookup);
}
$stmt->execute();
$user_info = $stmt->get_result()->fetch_assoc();
$stmt->close();

$user_info = $user_info ?? [];
$user_display_name  = isset($user_info['nama_lengkap']) ? (string)$user_info['nama_lengkap'] : '';
$user_username      = isset($user_info['username'])     ? (string)$user_info['username']      : '';
$user_nama_kesatuan = isset($user_info['nama_kesatuan'])? (string)$user_info['nama_kesatuan'] : '';
$user_pangkat       = isset($user_info['pangkat'])      ? (string)$user_info['pangkat']       : '';
$pengguna_id        = (int)($user_info['id'] ?? 0);

// Statistik peminjaman user
$peminjaman_stats = $mysqli->prepare("
    SELECT
        COUNT(*) as total_pengajuan,
        SUM(CASE WHEN status = 'pending'    THEN 1 ELSE 0 END) as pending,
        SUM(CASE WHEN status = 'approved'   THEN 1 ELSE 0 END) as approved,
        SUM(CASE WHEN status = 'ongoing'    THEN 1 ELSE 0 END) as ongoing,
        SUM(CASE WHEN status = 'completed'  THEN 1 ELSE 0 END) as completed
    FROM peminjaman_kendaraan
    WHERE peminjam_id = ?
");
$peminjaman_stats->bind_param('i', $user_id);
$peminjaman_stats->execute();
$stats = $peminjaman_stats->get_result()->fetch_assoc();
$peminjaman_stats->close();

// Peminjaman aktif (approved atau ongoing)
$active_peminjaman = $mysqli->prepare("
    SELECT p.*, k.no_reg, k.merk, k.tipe
    FROM peminjaman_kendaraan p
    LEFT JOIN kendaraan k ON p.kendaraan_id = k.id
    WHERE p.peminjam_id = ? AND p.status IN ('approved', 'ongoing')
    ORDER BY p.tanggal_mulai ASC
    LIMIT 5
");
$active_peminjaman->bind_param('i', $user_id);
$active_peminjaman->execute();
$active_loans = $active_peminjaman->get_result()->fetch_all(MYSQLI_ASSOC);
$active_peminjaman->close();

// Riwayat peminjaman terbaru
$recent_peminjaman = $mysqli->prepare("
    SELECT p.*, k.no_reg, k.merk, k.tipe
    FROM peminjaman_kendaraan p
    LEFT JOIN kendaraan k ON p.kendaraan_id = k.id
    WHERE p.peminjam_id = ?
    ORDER BY p.created_at DESC
    LIMIT 5
");
$recent_peminjaman->bind_param('i', $user_id);
$recent_peminjaman->execute();
$recent_loans = $recent_peminjaman->get_result()->fetch_all(MYSQLI_ASSOC);
$recent_peminjaman->close();

// Log BBM bulan ini
$bbm_stats = $mysqli->prepare("
    SELECT COUNT(*) as log_bbm_bulan_ini
    FROM log_bahan_bakar lb
    WHERE lb.user_id = ? AND MONTH(lb.tanggal_isi) = MONTH(CURRENT_DATE()) AND YEAR(lb.tanggal_isi) = YEAR(CURRENT_DATE())
");
$bbm_stats->bind_param('i', $user_id);
$bbm_stats->execute();
$bbm_stats->bind_result($log_bbm_bulan_ini);
$bbm_stats->fetch();
$bbm_stats->close();

// Total peminjaman selesai bulan ini
$selesai_bulan = 0;
$st_selesai = $mysqli->prepare("SELECT COUNT(*) FROM peminjaman_kendaraan WHERE peminjam_id = ? AND status = 'completed' AND MONTH(updated_at) = MONTH(CURDATE()) AND YEAR(updated_at) = YEAR(CURDATE())");
if ($st_selesai) {
    $st_selesai->bind_param('i', $user_id);
    $st_selesai->execute();
    $st_selesai->bind_result($selesai_bulan);
    $st_selesai->fetch();
    $st_selesai->close();
}

// Surat tugas aktif
$active_st = [];
if (table_exists_db($mysqli, 'surat_tugas')) {
    $today_str = date('Y-m-d');
    $st_q = $mysqli->prepare("
        SELECT st.*, k.no_reg, k.merk, k.tipe
        FROM surat_tugas st
        LEFT JOIN kendaraan k ON st.kendaraan_id = k.id
        WHERE st.pengguna_id = ?
          AND st.status IN ('Disetujui','Dalam Perjalanan')
          AND st.tanggal_berangkat <= ?
          AND (st.tanggal_kembali IS NULL OR st.tanggal_kembali >= ?)
        ORDER BY st.tanggal_berangkat ASC
        LIMIT 3
    ");
    if ($st_q) {
        $st_q->bind_param('iss', $pengguna_id, $today_str, $today_str);
        $st_q->execute();
        $active_st = $st_q->get_result()->fetch_all(MYSQLI_ASSOC);
        $st_q->close();
    }
}

// Notifikasi
$notif_query = $mysqli->prepare("
    SELECT n.*,
           CASE WHEN n.read_at IS NULL THEN 'info' ELSE 'success' END as type,
           COALESCE(n.title, 'Notifikasi') as title,
           COALESCE(n.message, '') as message
    FROM notifikasi n
    WHERE n.user_id = ?
    ORDER BY n.created_at DESC
    LIMIT 5
");
$notif_query->bind_param('i', $user_id);
$notif_query->execute();
$user_notifications = $notif_query->get_result()->fetch_all(MYSQLI_ASSOC);
$notif_query->close();

// Pengingat hari ini & besok
$today_items    = build_upcoming_items($pengguna_id, date('Y-m-d'));
$tomorrow_items = build_upcoming_items($pengguna_id, date('Y-m-d', strtotime('+1 day')));

// Status badge
function getStatusBadge($status) {
    $map = [
        'pending'   => ['bg-warning text-dark', 'Menunggu'],
        'approved'  => ['bg-success',           'Disetujui'],
        'rejected'  => ['bg-danger',            'Ditolak'],
        'ongoing'   => ['bg-info text-dark',    'Berlangsung'],
        'completed' => ['bg-primary',           'Selesai'],
        'cancelled' => ['bg-secondary',         'Dibatalkan'],
    ];
    [$cls, $label] = $map[$status] ?? ['bg-secondary', $status];
    return "<span class=\"badge $cls\">$label</span>";
}

function st_badge($status) {
    $map = [
        'Disetujui'       => 'bg-success',
        'Dalam Perjalanan'=> 'bg-info text-dark',
        'Menunggu'        => 'bg-warning text-dark',
        'Ditolak'         => 'bg-danger',
        'Selesai'         => 'bg-primary',
    ];
    $cls = $map[$status] ?? 'bg-secondary';
    return "<span class=\"badge $cls\">" . htmlspecialchars($status) . "</span>";
}
?>

<div id="dashboard-user-page">

<!-- ── Welcome Header ─────────────────────────────────────────────────────── -->
<div class="gradient-header d-flex align-items-center justify-content-between">
    <div>
        <h5 class="mb-1 fw-bold">
            <i class="fas fa-user-circle me-2 opacity-75"></i>
            <?= htmlspecialchars($user_display_name ?: $user_username) ?>
        </h5>
        <p class="mb-0 opacity-75 small">
            <?php if ($user_pangkat): ?>
                <span class="me-2"><?= htmlspecialchars($user_pangkat) ?></span>
            <?php endif; ?>
            <?php if ($user_nama_kesatuan): ?>
                <i class="fas fa-building me-1"></i><?= htmlspecialchars($user_nama_kesatuan) ?>
            <?php endif; ?>
        </p>
    </div>
    <div class="text-end">
        <span class="badge bg-light text-dark p-2">
            <i class="fas fa-calendar me-1"></i><?= date('d F Y') ?>
        </span>
 </div>
</div>

<!-- ── Stat Cards ─────────────────────────────────────────────────────────── -->
<div class="row mb-3">
    <div class="col-lg-4 col-md-6 mb-3">
        <div class="card bg-warning text-dark shadow-sm card-hover h-100">
            <div class="card-body d-flex align-items-center">
                <div class="me-3">
                    <div class="bg-white bg-opacity-25 rounded-circle d-flex align-items-center justify-content-center stat-icon-circle">
                        <i class="fas fa-clock fa-2x text-dark"></i>
                    </div>
                </div>
                <div>
                    <h3 class="mb-0 fw-bold"><?= (int)($stats['pending'] ?? 0) ?></h3>
                    <p class="mb-0 opacity-75">Menunggu Persetujuan</p>
                </div>
            </div>
        </div>
    </div>
    <div class="col-lg-4 col-md-6 mb-3">
        <div class="card bg-success text-white shadow-sm card-hover h-100">
            <div class="card-body d-flex align-items-center">
                <div class="me-3">
                    <div class="bg-white bg-opacity-25 rounded-circle d-flex align-items-center justify-content-center stat-icon-circle">
                        <i class="fas fa-car fa-2x text-white"></i>
                    </div>
                </div>
                <div>
                    <h3 class="mb-0 fw-bold"><?= (int)($stats['approved'] ?? 0) + (int)($stats['ongoing'] ?? 0) ?></h3>
                    <p class="mb-0 opacity-75">Peminjaman Aktif</p>
                </div>
            </div>
        </div>
    </div>
    <div class="col-lg-4 col-md-6 mb-3">
        <div class="card bg-primary text-white shadow-sm card-hover h-100">
            <div class="card-body d-flex align-items-center">
                <div class="me-3">
                    <div class="bg-white bg-opacity-25 rounded-circle d-flex align-items-center justify-content-center stat-icon-circle">
                        <i class="fas fa-check-circle fa-2x text-white"></i>
                    </div>
                </div>
                <div>
                    <h3 class="mb-0 fw-bold"><?= (int)($stats['completed'] ?? 0) ?></h3>
                    <p class="mb-0 opacity-75">Total Selesai</p>
             </div>
            </div>
        </div>
    </div>
</div>

<!-- ── Pengingat ──────────────────────────────────────────────────────────── -->
<div class="row g-3 mb-4">
    <div class="col-md-6">
           <div class="card shadow-sm h-100">
            <div class="card-header bg-warning text-white">
                <h6 class="mb-0"><i class="fas fa-bell me-2"></i>Pengingat Hari Ini</h6>
            </div>
            <div class="card-body">
             <?php if (!empty($today_items)): ?>
                    <ul class="list-unstyled mb-0">
                    <?php foreach ($today_items as $it): ?>
                        <li class="d-flex align-items-start gap-2 mb-2 pb-2 border-bottom">
                            <i class="fas fa-circle text-warning mt-1" style="font-size:.5rem;flex-shrink:0;"></i>
                            <div>
                                <div class="fw-semibold small"><?= htmlspecialchars($it['type']) ?> — <?= htmlspecialchars($it['label']) ?></div>
                                <div class="text-muted" style="font-size:.8rem;"><?= date('d/m/Y', strtotime($it['date'])) ?> · <?= htmlspecialchars($it['note']) ?></div>
                            </div>
                        </li>
                    <?php endforeach; ?>
                    </ul>
                <?php else: ?>
                    <div class="text-muted small py-2"><i class="fas fa-check-circle text-success me-1"></i>Tidak ada pengingat hari ini.</div>
                <?php endif; ?>
            </div>
        </div>
    </div>
    <div class="col-md-6">
           <div class="card shadow-sm h-100">
            <div class="card-header bg-info text-white">
                <h6 class="mb-0"><i class="fas fa-calendar-alt me-2"></i>Pengingat Besok</h6>
            </div>
            <div class="card-body">
             <?php if (!empty($tomorrow_items)): ?>
                    <ul class="list-unstyled mb-0">
                    <?php foreach ($tomorrow_items as $it): ?>
                        <li class="d-flex align-items-start gap-2 mb-2 pb-2 border-bottom">
                            <i class="fas fa-circle text-info mt-1" style="font-size:.5rem;flex-shrink:0;"></i>
                            <div>
                                <div class="fw-semibold small"><?= htmlspecialchars($it['type']) ?> — <?= htmlspecialchars($it['label']) ?></div>
                                <div class="text-muted" style="font-size:.8rem;"><?= date('d/m/Y', strtotime($it['date'])) ?> · <?= htmlspecialchars($it['note']) ?></div>
                            </div>
                        </li>
                    <?php endforeach; ?>
                    </ul>
                <?php else: ?>
                    <div class="text-muted small py-2"><i class="fas fa-check-circle text-info me-1"></i>Tidak ada pengingat untuk besok.</div>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<!-- ── Surat Tugas Aktif (if any) ────────────────────────────────────────── -->
<?php if (!empty($active_st)): ?>
<div class="card shadow-sm mb-4">
    <div class="card-header bg-success text-white">
        <h6 class="mb-0"><i class="fas fa-file-alt me-2"></i>Surat Tugas Aktif</h6>
 </div>
    <div class="card-body pt-0">
        <div class="row g-2">
        <?php foreach ($active_st as $st): ?>
            <div class="col-md-4">
                <div class="p-2 rounded border bg-light">
                    <div class="fw-semibold small"><?= htmlspecialchars($st['no_reg'] ?? '-') ?> — <?= htmlspecialchars(trim(($st['merk'] ?? '').' '.($st['tipe'] ?? ''))) ?></div>
                    <div class="text-muted" style="font-size:.8rem;">
                        <?= date('d/m/Y', strtotime($st['tanggal_berangkat'])) ?>
                        <?php if (!empty($st['tanggal_kembali'])): ?> s/d <?= date('d/m/Y', strtotime($st['tanggal_kembali'])) ?><?php endif; ?>
                    </div>
                    <div class="mt-1"><?= st_badge($st['status']) ?></div>
                </div>
            </div>
        <?php endforeach; ?>
        </div>
    </div>
</div>
<?php endif; ?>

<!-- ── Peminjaman Aktif + Riwayat ─────────────────────────────────────────── -->
<div class="row g-3 mb-4">
    <!-- Peminjaman Aktif -->
    <div class="col-md-6">
           <div class="card shadow-sm h-100">
            <div class="card-header bg-primary text-white d-flex justify-content-between align-items-center">
                <h6 class="mb-0"><i class="fas fa-car me-2"></i>Peminjaman Aktif</h6>
                <a href="index.php?page=riwayat_peminjaman" class="btn btn-sm btn-light py-0 text-primary">Semua</a>
            </div>
            <div class="card-body">
             <?php if (!empty($active_loans)): ?>
                    <ul class="list-unstyled mb-0">
                    <?php foreach ($active_loans as $loan): ?>
                        <li class="d-flex align-items-start gap-2 mb-3 pb-2 border-bottom">
                            <div class="rounded-circle bg-primary text-white d-flex align-items-center justify-content-center flex-shrink-0" style="width:36px;height:36px;font-size:.8rem;">
                                <i class="fas fa-car"></i>
                            </div>
                            <div class="flex-grow-1 min-w-0">
                                <div class="fw-semibold small"><?= htmlspecialchars($loan['no_reg'] ?? '-') ?> <span class="text-muted fw-normal"><?= htmlspecialchars($loan['merk'].' '.$loan['tipe']) ?></span></div>
                                <div class="text-muted" style="font-size:.8rem;"><?= htmlspecialchars($loan['keperluan']) ?></div>
                                <div class="text-muted" style="font-size:.75rem;"><?= date('d/m/Y', strtotime($loan['tanggal_mulai'])) ?> – <?= date('d/m/Y', strtotime($loan['tanggal_selesai'])) ?></div>
                            </div>
                            <div><?= getStatusBadge($loan['status']) ?></div>
                        </li>
                    <?php endforeach; ?>
                    </ul>
                <?php else: ?>
                    <div class="text-center py-4 text-muted">
                        <i class="fas fa-car fa-2x mb-2 opacity-25 d-block"></i>
                        <div class="small">Tidak ada peminjaman aktif</div>
                        <a href="index.php?page=form_peminjaman" class="btn btn-primary btn-sm mt-2">Ajukan Peminjaman</a>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <!-- Riwayat Terbaru -->
    <div class="col-md-6">
           <div class="card shadow-sm h-100">
            <div class="card-header bg-secondary text-white d-flex justify-content-between align-items-center">
                <h6 class="mb-0"><i class="fas fa-history me-2"></i>Riwayat Terbaru</h6>
                <a href="index.php?page=riwayat_peminjaman" class="btn btn-sm btn-light py-0 text-secondary">Semua</a>
            </div>
            <div class="card-body">
             <?php if (!empty($recent_loans)): ?>
                    <ul class="list-unstyled mb-0">
                    <?php foreach ($recent_loans as $loan): ?>
                        <li class="d-flex align-items-center gap-2 mb-2 pb-2 border-bottom">
                            <div class="flex-grow-1 min-w-0">
                                <div class="fw-semibold small"><?= htmlspecialchars($loan['no_reg'] ?? '-') ?> <span class="text-muted fw-normal"><?= htmlspecialchars($loan['merk'].' '.$loan['tipe']) ?></span></div>
                                <div class="text-muted" style="font-size:.75rem;"><?= htmlspecialchars($loan['keperluan']) ?> · <?= date('d/m/Y', strtotime($loan['created_at'])) ?></div>
                            </div>
                            <div class="flex-shrink-0"><?= getStatusBadge($loan['status']) ?></div>
                        </li>
                    <?php endforeach; ?>
                    </ul>
                <?php else: ?>
                    <div class="text-center py-4 text-muted">
                        <i class="fas fa-history fa-2x mb-2 opacity-25 d-block"></i>
                        <div class="small">Belum ada riwayat peminjaman</div>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<!-- ── Notifikasi Terbaru ─────────────────────────────────────────────────── -->
<?php if (!empty($user_notifications)): ?>
<div class="card shadow-sm mb-4">
    <div class="card-header bg-info text-white">
        <h6 class="mb-0"><i class="fas fa-bell me-2"></i>Notifikasi Terbaru</h6>
    </div>
    <div class="card-body">
        <?php foreach ($user_notifications as $notif): ?>
            <?php $is_read = !empty($notif['read_at']); ?>
            <div class="d-flex gap-3 mb-3 pb-2 border-bottom <?= $is_read ? 'opacity-50' : '' ?>">
                <?php
                $t = $notif['type'] ?? 'info';
                $icon_map   = ['success' => 'check', 'danger' => 'times', 'warning' => 'exclamation'];
                $color_map  = ['success' => '#27ae60', 'danger' => '#e74c3c', 'warning' => '#f39c12'];
                $icon  = $icon_map[$t]  ?? 'info-circle';
                $color = $color_map[$t] ?? '#2980b9';
                ?>
                <div class="rounded-circle d-flex align-items-center justify-content-center flex-shrink-0 text-white"
                     style="width:36px;height:36px;background:<?= $color ?>;font-size:.85rem;">
                    <i class="fas fa-<?= $icon ?>"></i>
                </div>
                <div>
                    <div class="fw-semibold small"><?= htmlspecialchars($notif['title'] ?? 'Notifikasi') ?></div>
                 <div class="text-muted small"><?= htmlspecialchars($notif['message'] ?? '') ?></div>
                    <div class="text-muted" style="font-size:.75rem;"><?= !empty($notif['created_at']) ? date('d/m/Y H:i', strtotime($notif['created_at'])) : '' ?></div>
                </div>
            </div>
        <?php endforeach; ?>
    </div>
</div>
<?php endif; ?>

<!-- ── Menu Cepat ─────────────────────────────────────────────────────────── -->
<div class="card shadow-sm mb-4">
    <div class="card-header bg-primary text-white">
        <h6 class="mb-0"><i class="fas fa-th-large me-2"></i>Menu Cepat</h6>
 </div>
    <div class="card-body">
        <div class="row g-3">
            <?php
            $menus = [
                ['page' => 'kendaraan_saya',    'icon' => 'fas fa-car',       'color' => '#2980b9', 'label' => 'Kendaraan Saya',     'desc' => 'Kendaraan yang ditugaskan'],
                ['page' => 'surat_tugas',        'icon' => 'fas fa-file-alt',  'color' => '#27ae60', 'label' => 'Surat Tugas',         'desc' => 'Tugas perjalanan dinas'],
                ['page' => 'log_bahan_bakar',    'icon' => 'fas fa-gas-pump',  'color' => '#8e44ad', 'label' => 'Log BBM',             'desc' => 'Catat penggunaan BBM'],
                ['page' => 'riwayat_peminjaman', 'icon' => 'fas fa-history',   'color' => '#16a085', 'label' => 'Riwayat Peminjaman',  'desc' => 'Riwayat pemakaian kendaraan'],
                ['page' => 'form_peminjaman',    'icon' => 'fas fa-plus-circle','color'=> '#e67e22', 'label' => 'Ajukan Peminjaman',   'desc' => 'Buat pengajuan baru'],
                ['page' => 'profil',             'icon' => 'fas fa-user-cog',  'color' => '#7f8c8d', 'label' => 'Profil',              'desc' => 'Lihat dan edit profil'],
            ];
            foreach ($menus as $m):
            ?>
            <div class="col-6 col-md-4 col-lg-2">
                <a href="index.php?page=<?= $m['page'] ?>"
                   class="d-flex flex-column align-items-center text-center p-3 rounded-3 text-decoration-none border h-100"
                   style="transition: all .2s; color: inherit;"
                   onmouseover="this.style.boxShadow='0 4px 16px rgba(0,0,0,.12)';this.style.transform='translateY(-3px)'"
                   onmouseout="this.style.boxShadow='';this.style.transform=''">
                    <div class="rounded-circle d-flex align-items-center justify-content-center mb-2 text-white"
                         style="width:48px;height:48px;background:<?= $m['color'] ?>;font-size:1.1rem;">
                        <i class="<?= $m['icon'] ?>"></i>
                    </div>
                    <div class="fw-semibold small"><?= $m['label'] ?></div>
                    <div class="text-muted mt-1" style="font-size:.75rem;line-height:1.3"><?= $m['desc'] ?></div>
                </a>
            </div>
            <?php endforeach; ?>
        </div>
    </div>
</div>

</div><!-- #dashboard-user-page -->

