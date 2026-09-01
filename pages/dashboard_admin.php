<?php
// Check authentication - functions already loaded by index.php
if (!is_logged_in()) {
    header('Location: login.php');
    exit();
}

// Check admin-like access
if (!is_admin_like()) {
    header('Location: index.php?page=403');
    exit();
}

// Get current user info
$current_user = get_logged_in_user();

// Initial page head rendering removed (avoid duplicate); we'll render once later with JS includes

// Get statistics (mysqli-based, similar to legacy dashboard)
$stats = [];
$master_counts = [];

// Users by role
$stats['users'] = [];
$rres = $mysqli->query("SELECT r.kode_role as role, COUNT(*) as count FROM user_account ua JOIN role r ON ua.role_id = r.id WHERE ua.status = 'Aktif' AND UPPER(COALESCE(r.kode_role, r.nama_role, '')) IN ('ADMIN','PIMPINAN','DRIVER','USER') GROUP BY r.kode_role");
if ($rres) {
    while ($r = $rres->fetch_assoc()) {
        $stats['users'][$r['role']] = intval($r['count']);
    }
}

// Vehicle status (jenis removed - deprecated field)
$stats['kendaraan_status'] = [];
$res = $mysqli->query("SELECT status_kendaraan, COUNT(*) as jumlah FROM kendaraan GROUP BY status_kendaraan");
if ($res) {
    while ($row = $res->fetch_assoc()) {
        $stats['kendaraan_status'][$row['status_kendaraan']] = intval($row['jumlah']);
    }
}

// Master counts
$tables = ['kendaraan', 'user_account', 'jadwal_perawatan'];
$table_aliases = [
    'user_account' => 'users',
    'jadwal_perawatan' => 'jadwal_perawatan'
];
foreach ($tables as $table) {
    $q = "SELECT COUNT(*) as count FROM $table";
    if ($table === 'user_account') $q .= " WHERE status = 'Aktif'";
    $r = $mysqli->query($q);
    $cnt = 0;
    if ($r) {
        $row = $r->fetch_assoc();
        $cnt = intval($row['count'] ?? 0);
    }
    $display = $table_aliases[$table] ?? $table;
    $master_counts[$display] = $cnt;
}

// Maintenance statistics (top 5)
$maintenance_stats = [];
$r = $mysqli->query("SELECT k.no_reg, k.merk, k.tipe, COUNT(jp.id) as total_maintenance, MAX(jp.tanggal_perawatan) as last_maintenance FROM kendaraan k LEFT JOIN jadwal_perawatan jp ON k.id = jp.kendaraan_id GROUP BY k.id, k.no_reg, k.merk, k.tipe ORDER BY total_maintenance DESC LIMIT 5");
if ($r) $maintenance_stats = $r->fetch_all(MYSQLI_ASSOC);

// Scheduled maintenance (upcoming and overdue)
$scheduled_maintenance = [];
$r = $mysqli->query("
    SELECT jp.*, k.no_reg, k.merk, k.tipe, p.nama_lengkap as teknisi_nama 
    FROM jadwal_perawatan jp 
    LEFT JOIN kendaraan k ON jp.kendaraan_id = k.id 
    LEFT JOIN pengguna p ON jp.teknisi_id = p.id 
    WHERE jp.status = 'Terjadwal' 
    ORDER BY jp.tanggal_perawatan ASC 
    LIMIT 10
");
if ($r) $scheduled_maintenance = $r->fetch_all(MYSQLI_ASSOC);

// Recent activity logs
$log_aktivitas = [];
$r = $mysqli->query("SELECT la.*, la.activity_type AS aktivitas, la.description AS deskripsi, p.nama_lengkap FROM log_aktivitas la LEFT JOIN pengguna p ON la.user_id = p.id ORDER BY la.created_at DESC LIMIT 10");
if ($r) $log_aktivitas = $r->fetch_all(MYSQLI_ASSOC);

// Documents expiring and jadwal_perawatan will be fetched later in rendering where needed

// Final page configuration (single head render)
$page_title = "Dashboard Admin - RANDIS";
$current_page = "dashboard_admin";
$additional_css = [];
$additional_js = ['sidebar.js'];

// Create user_info array from current_user data
$user_info = [
    'nama_lengkap' => $current_user['nama_lengkap'] ?? 'Admin',
    'pangkat' => $current_user['pangkat'] ?? '',
    'nrp_nip' => $current_user['nrp_nip'] ?? ''
];

// Render page head
render_page_head($page_title, $additional_css, $additional_js);

// Render sidebar
render_sidebar($current_page, 'admin');
?>

<div class="gradient-header">
    <div class="container-fluid">
        <div class="row align-items-center">
            <div class="col-md-8">
                <h1 class="mb-1"><i class="fas fa-tachometer-alt me-2"></i>Dashboard Admin</h1>
                <p class="mb-0 opacity-75">Selamat datang, <?= htmlspecialchars($current_user['nama_lengkap']) ?></p>
            </div>
            <div class="col-md-4 text-md-end">
                <span class="badge bg-light text-dark p-2 fs-6">
                    <i class="fas fa-calendar me-1"></i><?= date('d F Y') ?>
                </span>
            </div>
        </div>
    </div>
</div>

<div class="container-fluid">
    <!-- Quick Stats -->
    <div class="row mb-3">
        <div class="col-lg-4 col-md-6 mb-3">
            <div class="card bg-primary text-white shadow-sm card-hover h-100">
                <div class="card-body d-flex align-items-center">
                    <div class="me-3">
                        <div class="bg-white bg-opacity-25 rounded-circle d-flex align-items-center justify-content-center stat-icon-circle">
                            <i class="fas fa-users fa-2x text-white"></i>
                        </div>
                    </div>
                    <div>
                        <h3 class="mb-0 fw-bold text-white"><?= number_format(array_sum($stats['users'] ?? [])) ?></h3>
                        <p class="mb-0 fw-bold text-white">Total Users</p>
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
                        <h3 class="mb-0 fw-bold text-white"><?= number_format($master_counts['kendaraan'] ?? 0) ?></h3>
                        <p class="mb-0 fw-bold text-white">Total Kendaraan</p>
                    </div>
                </div>
            </div>
        </div>

        <div class="col-lg-4 col-md-6 mb-3">
            <div class="card bg-warning text-dark shadow-sm card-hover h-100">
                <div class="card-body d-flex align-items-center">
                    <div class="me-3">
                        <div class="bg-white bg-opacity-25 rounded-circle d-flex align-items-center justify-content-center stat-icon-circle">
                            <i class="fas fa-wrench fa-2x text-dark"></i>
                        </div>
                    </div>
                    <div>
                        <h3 class="mb-0 fw-bold text-white"><?= number_format($master_counts['jadwal_perawatan'] ?? 0) ?></h3>
                        <p class="mb-0 fw-bold text-white">Total Perawatan</p>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Charts & Analytics Row -->
    <div class="row mb-4">
        <!-- User Role Distribution -->
        <div class="col-lg-6 mb-3">
            <div class="card shadow-sm h-100">
                <div class="card-header bg-primary text-white">
                    <h5 class="mb-0"><i class="fas fa-users-cog me-2"></i>Distribusi Role User</h5>
                </div>
                <div class="card-body">
                    <?php if (!empty($stats['users'])): ?>
                        <div class="mb-3">
                            <?php foreach ($stats['users'] as $role => $count): ?>
                                <div class="d-flex justify-content-between align-items-center mb-3">
                                    <div class="d-flex align-items-center">
                                        <div class="role-icon me-3">
                                            <i class="fas fa-<?= $role === 'admin' ? 'crown' : 'user' ?> text-primary"></i>
                                        </div>
                                        <span class="text-capitalize fw-semibold"><?= ucfirst($role) ?></span>
                                    </div>
                                    <span class="badge bg-primary fs-6"><?= $count ?></span>
                                </div>
                                <div class="progress progress-sm mb-2">
                                    <div class="progress-bar bg-primary" style="width: <?= (array_sum($stats['users']) > 0) ? ($count / array_sum($stats['users']) * 100) : 0 ?>%"></div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    <?php else: ?>
                        <div class="text-center py-4">
                            <i class="fas fa-users fa-3x text-muted mb-3"></i>
                            <p class="text-muted">Tidak ada data user</p>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>

        <!-- Vehicle Status -->
        <div class="col-lg-6 mb-3">
            <div class="card shadow-sm h-100">
                <div class="card-header bg-success text-white">
                    <h5 class="mb-0"><i class="fas fa-car me-2"></i>Status Kendaraan</h5>
                </div>
                <div class="card-body">
                    <?php if (!empty($stats['kendaraan_status'])): ?>
                        <div class="mb-3">
                            <?php foreach ($stats['kendaraan_status'] as $status => $count): ?>
                                <?php $total_kendaraan = array_sum($stats['kendaraan_status']); ?>
                                <div class="d-flex justify-content-between align-items-center mb-3">
                                    <div class="d-flex align-items-center">
                                        <div class="status-icon me-3">
                                            <i class="fas fa-circle text-<?= getStatusBadgeColor($status) ?>"></i>
                                        </div>
                                        <span class="text-capitalize fw-semibold"><?= $status ?></span>
                                    </div>
                                    <span class="badge bg-<?= getStatusBadgeColor($status) ?> fs-6"><?= $count ?></span>
                                </div>
                                <div class="progress progress-sm mb-2">
                                    <div class="progress-bar bg-<?= getStatusBadgeColor($status) ?>" style="width: <?= ($total_kendaraan > 0) ? ($count / $total_kendaraan * 100) : 0 ?>%"></div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    <?php else: ?>
                        <div class="text-center py-4">
                            <i class="fas fa-car fa-3x text-muted mb-3"></i>
                            <p class="text-muted">Tidak ada data kendaraan</p>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>

    <!-- Recent Activity & System Management -->
    <div class="row mb-4">
        <!-- Recent Activity Log -->
        <div class="col-lg-8">
            <div class="card shadow-sm">
                <div class="card-header bg-info text-white d-flex justify-content-between align-items-center">
                    <h5 class="mb-0"><i class="fas fa-history me-2"></i>Log Aktivitas Terbaru</h5>
                    <a href="index.php?page=log_aktivitas" class="btn btn-sm btn-light">
                        <i class="fas fa-eye"></i> Lihat Semua
                    </a>
                </div>
                <div class="card-body p-0">
                    <?php if (!empty($log_aktivitas ?? [])): ?>
                        <div class="table-responsive table-scroll-400">
                            <table class="table table-sm mb-0">
                                <thead class="bg-light sticky-top">
                                    <tr>
                                        <th>Waktu</th>
                                        <th>User</th>
                                        <th>Aktivitas</th>
                                        <th>Detail</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($log_aktivitas as $log): ?>
                                    <tr>
                                        <td>
                                            <small class="text-muted">
                                                <?= date('d/m/Y H:i', strtotime($log['created_at'] ?? '')) ?>
                                            </small>
                                        </td>
                                        <td>
                                            <div class="d-flex align-items-center">
                                                <div class="user-avatar me-2">
                                                    <i class="fas fa-user-circle text-primary"></i>
                                                </div>
                                                <small class="fw-semibold">
                                                    <?= htmlspecialchars($log['nama_lengkap'] ?? 'Unknown') ?>
                                                </small>
                                            </div>
                                        </td>
                                        <td>
                                            <?php $activity = $log['activity_type'] ?? ($log['aktivitas'] ?? ''); $meta = getActivityMeta($activity); ?>
                                            <span class="badge <?= $meta['class'] ?>" title="<?= htmlspecialchars($activity) ?>">
                                                <?php if (!empty($meta['icon'])): ?><i class="<?= $meta['icon'] ?> me-1"></i><?php endif; ?>
                                                <?= htmlspecialchars($activity) ?>
                                            </span>
                                        </td>
                                        <td>
                                            <?= renderLogDetail($log['description'] ?? ($log['deskripsi'] ?? '')) ?>
                                        </td>
                                    </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    <?php else: ?>
                        <div class="text-center py-5">
                            <i class="fas fa-clipboard-list fa-3x text-muted mb-3"></i>
                            <p class="text-muted">Belum ada log aktivitas</p>
                        </div>
                    <?php endif; ?>
                </div>
            </div>

        </div>

        <!-- System Management Panel -->
        <div class="col-lg-4">
            <div class="card shadow-sm">
                <div class="card-header bg-dark text-white">
                    <h5 class="mb-0"><i class="fas fa-cogs me-2"></i>Manajemen Sistem</h5>
                </div>
                <div class="card-body">
                    <div class="d-grid gap-2">
                        <a href="index.php?page=manajemen_user" class="btn btn-primary btn-sm">
                            <i class="fas fa-users-cog me-2"></i>Kelola User
                        </a>
                        <a href="index.php?page=kendaraan" class="btn btn-success btn-sm">
                            <i class="fas fa-car me-2"></i>Kelola Kendaraan
                        </a>
                        <a href="index.php?page=riwayat" class="btn btn-info btn-sm">
                            <i class="fas fa-chart-line me-2"></i>Laporan Lengkap
                        </a>
                    </div>
                </div>
            </div>

        </div>
    </div>

    <!-- Scheduled Maintenance Section -->
    <div class="row mb-4">
        <div class="col-12">
            <div class="card shadow-sm">
                <div class="card-header bg-warning text-dark d-flex justify-content-between align-items-center">
                    <h5 class="mb-0"><i class="fas fa-calendar-alt me-2"></i>Perawatan Terjadwal</h5>
                    <a href="index.php?page=jadwal_perawatan" class="btn btn-sm btn-dark">
                        <i class="fas fa-eye"></i> Lihat Semua
                    </a>
                </div>
                <div class="card-body p-0">
                    <?php if (!empty($scheduled_maintenance)): ?>
                        <div class="table-responsive">
                            <table class="table table-sm mb-0">
                                <thead class="bg-light">
                                    <tr>
                                        <th>Kendaraan</th>
                                        <th>Jenis Perawatan</th>
                                        <th>Tanggal</th>
                                        <th>Bengkel</th>
                                        <th>Prioritas</th>
                                        <!-- Estimasi Biaya removed from admin scheduled maintenance -->
                                        <th>Status</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($scheduled_maintenance as $maintenance): ?>
                                    <?php 
                                        $tanggal_perawatan = strtotime($maintenance['tanggal_perawatan']);
                                        $today = strtotime(date('Y-m-d'));
                                        $is_overdue = $tanggal_perawatan < $today;
                                        $is_soon = ($tanggal_perawatan - $today) <= (3 * 24 * 3600); // 3 days
                                    ?>
                                    <tr class="<?= $is_overdue ? 'table-danger' : ($is_soon ? 'table-warning' : '') ?>">
                                        <td>
                                            <div>
                                                <strong><?= htmlspecialchars($maintenance['no_reg'] ?? 'N/A') ?></strong><br>
                                                <small class="text-muted">
                                                    <?= htmlspecialchars($maintenance['merk'] ?? '') ?> 
                                                    <?= htmlspecialchars($maintenance['tipe'] ?? '') ?>
                                                </small>
                                            </div>
                                        </td>
                                        <td>
                                            <strong><?= htmlspecialchars($maintenance['jenis_perawatan'] ?? '') ?></strong>
                                            <?php if (!empty($maintenance['deskripsi'])): ?>
                                                <br><small class="text-muted"><?= htmlspecialchars($maintenance['deskripsi']) ?></small>
                                            <?php endif; ?>
                                        </td>
                                        <td>
                                            <span class="fw-semibold">
                                                <?= date('d/m/Y', strtotime($maintenance['tanggal_perawatan'])) ?>
                                            </span>
                                            <?php if ($is_overdue): ?>
                                                <br><small class="text-danger"><i class="fas fa-exclamation-triangle"></i> Terlewat</small>
                                            <?php elseif ($is_soon): ?>
                                                <br><small class="text-warning"><i class="fas fa-clock"></i> Segera</small>
                                            <?php endif; ?>
                                        </td>
                                        <td>
                                            <?= htmlspecialchars($maintenance['bengkel'] ?? 'N/A') ?>
                                            <?php if (!empty($maintenance['teknisi_nama'])): ?>
                                                <br><small class="text-muted">
                                                    <i class="fas fa-user"></i> <?= htmlspecialchars($maintenance['teknisi_nama']) ?>
                                                </small>
                                            <?php endif; ?>
                                        </td>
                                        <td>
                                            <span class="badge bg-<?= 
                                                $maintenance['prioritas'] === 'Tinggi' ? 'danger' : 
                                                ($maintenance['prioritas'] === 'Sedang' ? 'warning' : 'info') 
                                            ?>">
                                                <?= htmlspecialchars($maintenance['prioritas'] ?? 'Normal') ?>
                                            </span>
                                        </td>
                                        <td>
                                            <span class="badge bg-warning text-dark">
                                                <?= htmlspecialchars($maintenance['status']) ?>
                                            </span>
                                        </td>
                                    </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    <?php else: ?>
                        <div class="text-center py-5">
                            <i class="fas fa-calendar-check fa-3x text-muted mb-3"></i>
                            <p class="text-muted">Tidak ada perawatan yang terjadwal</p>
                            <a href="index.php?page=jadwal_perawatan" class="btn btn-warning">
                                <i class="fas fa-plus"></i> Tambah Jadwal Perawatan
                            </a>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
</div>

<?php
// Helper function
function getStatusBadgeColor($status) {
    switch (strtolower($status)) {
        case 'operasional': return 'success';
        case 'perbaikan': return 'warning';
        case 'rusak': return 'danger';
        case 'aktif': return 'success';
        case 'maintenance': return 'warning';
        default: return 'secondary';
    }
}

// Map activity text to a Bootstrap badge class and icon
function getActivityMeta($aktivitas) {
    $a = strtolower(trim((string)$aktivitas));
    $meta = ['class' => 'badge bg-light text-dark', 'icon' => ''];

    $map = [
        'login' => ['bg-success text-white', 'fas fa-sign-in-alt'],
        'logout' => ['bg-secondary text-white', 'fas fa-sign-out-alt'],
    'add_bbm_log' => ['bg-info text-white', 'fas fa-gas-pump'],
    'bbm' => ['bg-info text-white', 'fas fa-gas-pump'],
        'create' => ['bg-primary text-white', 'fas fa-plus'],
        'tambah' => ['bg-primary text-white', 'fas fa-plus'],
        'insert' => ['bg-primary text-white', 'fas fa-plus'],
        'update' => ['bg-warning text-dark', 'fas fa-edit'],
        'edit' => ['bg-warning text-dark', 'fas fa-edit'],
        'delete' => ['bg-danger text-white', 'fas fa-trash'],
        'hapus' => ['bg-danger text-white', 'fas fa-trash'],
        'remove' => ['bg-danger text-white', 'fas fa-trash'],
        'approve' => ['bg-success text-white', 'fas fa-check'],
        'setujui' => ['bg-success text-white', 'fas fa-check'],
        'reject' => ['bg-danger text-white', 'fas fa-times'],
        'tolak' => ['bg-danger text-white', 'fas fa-times'],
        'view' => ['bg-info text-white', 'fas fa-eye'],
        'lihat' => ['bg-info text-white', 'fas fa-eye'],
        'download' => ['bg-info text-white', 'fas fa-download'],
        'export' => ['bg-info text-white', 'fas fa-file-export'],
    ];

    foreach ($map as $key => [$cls, $icon]) {
        if (strpos($a, $key) !== false) {
            $meta['class'] = 'badge ' . $cls;
            $meta['icon'] = $icon;
            return $meta;
        }
    }

    return $meta;
}

// Render log description smartly (JSON-aware, truncated, safe)
function renderLogDetail($deskripsi) {
    $desc = trim((string)$deskripsi);
    if ($desc === '') {
        return '<small class="text-muted">-</small>';
    }

    // Try to parse JSON
    $decoded = json_decode($desc, true);
    if (json_last_error() === JSON_ERROR_NONE && is_array($decoded)) {
        $pretty = json_encode($decoded, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        $safe = htmlspecialchars($pretty, ENT_QUOTES, 'UTF-8');
        return '<details><summary class="small text-muted">Detail JSON</summary><pre class="small mb-0">' . $safe . '</pre></details>';
    }

    // Plain text with truncation
    $max = 120;
    $safeFull = htmlspecialchars($desc, ENT_QUOTES, 'UTF-8');
    if (mb_strlen($desc) > $max) {
        $short = htmlspecialchars(mb_substr($desc, 0, $max) . '…', ENT_QUOTES, 'UTF-8');
        return '<details><summary class="small text-muted">' . $short . '</summary><small class="text-muted">' . $safeFull . '</small></details>';
    }

    return '<small class="text-muted">' . $safeFull . '</small>';
}

// Render page footer
render_page_footer($additional_js);
?>
