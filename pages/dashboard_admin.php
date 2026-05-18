<?php
// Check authentication - functions already loaded by index.php
if (!is_logged_in()) {
    header('Location: login.php');
    exit();
}

// Check admin access
if (get_current_role() !== 'admin') {
    header('Location: index.php?page=403');
    exit();
}

// Get current user info
$current_user = get_logged_in_user();

// Initial page head rendering removed (avoid duplicate); we'll render once later with JS includes

// Get statistics (mysqli-based, similar to operator dashboard)
$stats = [];
$master_counts = [];

// Users by role
$stats['users'] = [];
$rres = $mysqli->query("SELECT r.kode_role as role, COUNT(*) as count FROM user_account ua JOIN role r ON ua.role_id = r.id WHERE ua.status = 'Aktif' AND LOWER(COALESCE(r.kode_role, r.nama_role, '')) <> 'operator' GROUP BY r.kode_role");
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
$tables = ['kendaraan', 'user_account', 'peminjaman_kendaraan', 'jadwal_perawatan'];
$table_aliases = [
    'user_account' => 'users',
    'peminjaman_kendaraan' => 'peminjaman',
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

// Monthly peminjaman stats
$stats_bulanan = [];
$r = $mysqli->query("SELECT MONTH(tanggal_mulai) as bulan, COUNT(*) as jumlah FROM peminjaman_kendaraan WHERE YEAR(tanggal_mulai) = YEAR(CURDATE()) GROUP BY MONTH(tanggal_mulai) ORDER BY bulan");
if ($r) $stats_bulanan = $r->fetch_all(MYSQLI_ASSOC);

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

// Current loans ordered by ascending start date (for main table under activity log)
$current_loans_asc = [];
try {
    $r = $mysqli->query("SELECT pk.id, pk.nomor_surat, pk.tanggal_mulai, pk.tanggal_selesai, pk.status,
                                k.no_reg, k.merk, k.tipe,
                                p.nama_lengkap AS peminjam_nama
                         FROM peminjaman_kendaraan pk
                         LEFT JOIN kendaraan k ON pk.kendaraan_id = k.id
                         LEFT JOIN pengguna p ON pk.peminjam_id = p.id
                         WHERE LOWER(pk.status) = 'ongoing'
                         ORDER BY pk.tanggal_mulai ASC
                         LIMIT 10");
    if ($r) $current_loans_asc = $r->fetch_all(MYSQLI_ASSOC);
} catch (Throwable $e) {
    // ignore
}

// Pending approvals (peminjaman) notifications
$pending_approvals_total = 0;
$pending_approvals_items = [];

// Count and fetch from peminjaman_kendaraan
try {
    $cq = $mysqli->query("SELECT COUNT(*) AS cnt FROM peminjaman_kendaraan WHERE LOWER(status) = 'pending'");
    if ($cq) {
        $pending_approvals_total += intval(($cq->fetch_assoc()['cnt'] ?? 0));
    }

    $q = $mysqli->query("SELECT pk.id, pk.nomor_surat, pk.tanggal_mulai, pk.tanggal_selesai, pk.status, pk.created_at,
                                 k.no_reg, k.merk, k.tipe,
                                 p.nama_lengkap AS peminjam_nama
                          FROM peminjaman_kendaraan pk
                          LEFT JOIN kendaraan k ON pk.kendaraan_id = k.id
                          LEFT JOIN pengguna p ON pk.peminjam_id = p.id
                          WHERE LOWER(pk.status) = 'pending'
                          ORDER BY pk.created_at DESC
                          LIMIT 5");
    if ($q) {
        while ($row = $q->fetch_assoc()) {
            $row['source'] = 'pk';
            $pending_approvals_items[] = $row;
        }
    }
} catch (Throwable $e) {
    // ignore
}

// Active loans (Approved/Ongoing) for table card
$active_loans = [];
try {
    $r = $mysqli->query("SELECT pk.id, pk.nomor_surat, pk.tanggal_mulai, pk.tanggal_selesai, pk.status,
                                k.no_reg, k.merk, k.tipe,
                                p.nama_lengkap AS peminjam_nama
                         FROM peminjaman_kendaraan pk
                         LEFT JOIN kendaraan k ON pk.kendaraan_id = k.id
                         LEFT JOIN pengguna p ON pk.peminjam_id = p.id
                         WHERE LOWER(pk.status) IN ('approved','ongoing','disetujui','berjalan','aktif','dipinjam','sedang_dipinjam')
                         ORDER BY pk.tanggal_mulai DESC
                         LIMIT 10");
    if ($r) $active_loans = $r->fetch_all(MYSQLI_ASSOC);
} catch (Throwable $e) {
    // ignore
}

// Optionally include peminjaman_terjadwal if table exists
try {
    $pt_exists = $mysqli->query("SHOW TABLES LIKE 'peminjaman_terjadwal'");
    if ($pt_exists && $pt_exists->num_rows > 0) {
        $cq2 = $mysqli->query("SELECT COUNT(*) AS cnt FROM peminjaman_terjadwal WHERE LOWER(status) = 'pending'");
        if ($cq2) {
            $pending_approvals_total += intval(($cq2->fetch_assoc()['cnt'] ?? 0));
        }

        $q2 = $mysqli->query("SELECT pt.id, pt.tanggal_mulai, pt.tanggal_selesai, pt.status,
                                     k.no_reg, k.merk, k.tipe,
                                     p.nama_lengkap AS peminjam_nama
                              FROM peminjaman_terjadwal pt
                              LEFT JOIN kendaraan k ON pt.kendaraan_id = k.id
                              LEFT JOIN pengguna p ON pt.pemohon_id = p.id
                              WHERE LOWER(pt.status) = 'pending'
                              ORDER BY pt.tanggal_mulai ASC
                              LIMIT 5");
        if ($q2) {
            while ($row = $q2->fetch_assoc()) {
                $row['source'] = 'pt';
                $pending_approvals_items[] = $row;
            }
        }
    }
} catch (Throwable $e) {
    // ignore
}

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

<div class="gradient-header text-white p-4 mb-4 rounded">
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
                        <div class="bg-white bg-opacity-25 rounded-circle d-flex align-items-center justify-content-center" style="width: 60px; height: 60px;">
                            <i class="fas fa-users fa-2x text-white"></i>
                        </div>
                    </div>
                    <div>
                        <h3 class="mb-0 fw-bold"><?= number_format(array_sum($stats['users'] ?? [])) ?></h3>
                        <p class="mb-0 opacity-75">Total Users</p>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-lg-4 col-md-6 mb-3">
            <div class="card bg-success text-white shadow-sm card-hover h-100">
                <div class="card-body d-flex align-items-center">
                    <div class="me-3">
                        <div class="bg-white bg-opacity-25 rounded-circle d-flex align-items-center justify-content-center" style="width: 60px; height: 60px;">
                            <i class="fas fa-car fa-2x text-white"></i>
                        </div>
                    </div>
                    <div>
                        <h3 class="mb-0 fw-bold"><?= number_format($master_counts['kendaraan'] ?? 0) ?></h3>
                        <p class="mb-0 opacity-75">Total Kendaraan</p>
                    </div>
                </div>
            </div>
        </div>

        <!-- <div class="col-lg-4 col-md-6 mb-3">
            <div class="card bg-info text-white shadow-sm card-hover h-100">
                <div class="card-body d-flex align-items-center">
                    <div class="me-3">
                        <div class="bg-white bg-opacity-25 rounded-circle d-flex align-items-center justify-content-center" style="width: 60px; height: 60px;">
                            <i class="fas fa-clipboard-list fa-2x text-white"></i>
                        </div>
                    </div>
                    <div>
                        <h3 class="mb-0 fw-bold"><?= number_format($master_counts['peminjaman'] ?? 0) ?></h3>
                        <p class="mb-0 opacity-75">Total Peminjaman</p>
                    </div>
                </div>
            </div>
        </div> -->
        
        <div class="col-lg-4 col-md-6 mb-3">
            <div class="card bg-warning text-white shadow-sm card-hover h-100">
                <div class="card-body d-flex align-items-center">
                    <div class="me-3">
                        <div class="bg-white bg-opacity-25 rounded-circle d-flex align-items-center justify-content-center" style="width: 60px; height: 60px;">
                            <i class="fas fa-wrench fa-2x text-white"></i>
                        </div>
                    </div>
                    <div>
                        <h3 class="mb-0 fw-bold"><?= number_format($master_counts['jadwal_perawatan'] ?? 0) ?></h3>
                        <p class="mb-0 opacity-75">Total Perawatan</p>
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
                                <div class="progress mb-2" style="height: 8px;">
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
                                <div class="progress mb-2" style="height: 8px;">
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
                        <div class="table-responsive" style="max-height: 400px;">
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

            <!-- Peminjam Kendaraan (Ascending Start Date) -->
            <!-- <div class="card shadow-sm mt-3">
                <div class="card-header bg-primary text-white d-flex justify-content-between align-items-center">
                    <h5 class="mb-0"><i class="fas fa-id-card-alt me-2"></i>Peminjam Kendaraan</h5>
                    <a href="index.php?page=monitoring_peminjaman" class="btn btn-sm btn-light">
                        <i class="fas fa-eye"></i> Lihat Semua
                    </a>
                </div>
                <div class="card-body p-0">
                    <?php if (!empty($current_loans_asc)): ?>
                        <div class="table-responsive" style="max-height: 400px;">
                            <table class="table table-sm mb-0">
                                <thead class="bg-light sticky-top">
                                    <tr>
                                        <th>Waktu Mulai</th>
                                        <th>Peminjam</th>
                                        <th>Kendaraan</th>
                                        <th>Periode</th>
                                        <th>Status</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($current_loans_asc as $row): ?>
                                        <tr>
                                            <td>
                                                <small class="text-muted">
                                                    <?= !empty($row['tanggal_mulai']) ? date('d/m/Y', strtotime($row['tanggal_mulai'])) : '-' ?>
                                                </small>
                                            </td>
                                            <td>
                                                <div class="fw-semibold"><?= htmlspecialchars($row['peminjam_nama'] ?? 'N/A') ?></div>
                                                <?php if (!empty($row['nomor_surat'])): ?>
                                                    <small class="text-muted">No: <?= htmlspecialchars($row['nomor_surat']) ?></small>
                                                <?php endif; ?>
                                            </td>
                                            <td>
                                                <div class="fw-semibold"><?= htmlspecialchars($row['no_polisi'] ?? '') ?></div>
                                                <small class="text-muted"><?= htmlspecialchars($row['merk'] ?? '') ?> <?= htmlspecialchars($row['tipe'] ?? '') ?></small>
                                            </td>
                                            <td>
                                                <small class="text-muted">
                                                    <?= !empty($row['tanggal_mulai']) ? date('d/m/Y', strtotime($row['tanggal_mulai'])) : '-' ?>
                                                    <?= !empty($row['tanggal_selesai']) ? ' - ' . date('d/m/Y', strtotime($row['tanggal_selesai'])) : '' ?>
                                                </small>
                                            </td>
                                            <td>
                                                <?php 
                                                    $sraw = strtolower($row['status'] ?? '');
                                                    $active_aliases = ['approved','ongoing','disetujui','berjalan','aktif','dipinjam','sedang_dipinjam'];
                                                    $cls = in_array($sraw, $active_aliases, true) ? 'success' : 'secondary';
                                                    $label_map = [
                                                        'approved' => 'Disetujui',
                                                        'disetujui' => 'Disetujui',
                                                        'ongoing' => 'Berjalan',
                                                        'berjalan' => 'Berjalan',
                                                        'aktif' => 'Aktif',
                                                        'dipinjam' => 'Dipinjam',
                                                        'sedang_dipinjam' => 'Dipinjam',
                                                    ];
                                                    $label = $label_map[$sraw] ?? ucfirst($row['status'] ?? '');
                                                ?>
                                                <span class="badge bg-<?= $cls ?>"><?= htmlspecialchars($label) ?></span>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    <?php else: ?>
                        <div class="text-center py-5">
                            <i class="fas fa-clipboard-list fa-3x text-muted mb-3"></i>
                            <p class="text-muted">Tidak ada peminjaman aktif</p>
                        </div>
                    <?php endif; ?>
                </div>
            </div> -->
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
                        <a href="index.php?page=surat_tugas" class="btn btn-warning btn-sm">
                            <i class="fas fa-file-alt me-2"></i>Surat Tugas
                        </a>
                        <a href="index.php?page=riwayat" class="btn btn-info btn-sm">
                            <i class="fas fa-chart-line me-2"></i>Laporan Lengkap
                        </a>
                    </div>
                </div>
            </div>

            <!-- Quick Stats Panel -->
            <!-- <div class="card shadow-sm mt-3">
                <div class="card-header bg-secondary text-white">
                    <h6 class="mb-0"><i class="fas fa-tachometer-alt me-2"></i>Statistik Cepat</h6>
                </div>
                <div class="card-body">
                    <div class="row text-center">
                        <div class="col-6 mb-3">
                            <div class="border-end">
                                <h4 class="text-primary mb-1"><?= $master_counts['users'] ?? 0 ?></h4>
                                <small class="text-muted">Aktif Users</small>
                            </div>
                        </div>
                        <div class="col-6 mb-3">
                            <h4 class="text-success mb-1"><?= $stats['kendaraan_status']['Operasional'] ?? 0 ?></h4>
                            <small class="text-muted">Operasional</small>
                        </div>
                        <div class="col-6">
                            <div class="border-end">
                                <h4 class="text-warning mb-1"><?= $stats['kendaraan_status']['Perbaikan'] ?? 0 ?></h4>
                                <small class="text-muted">Perbaikan</small>
                            </div>
                        </div>
                        <div class="col-6">
                            <h4 class="text-info mb-1"><?= $master_counts['peminjaman'] ?? 0 ?></h4>
                            <small class="text-muted">Peminjaman</small>
                        </div>
                    </div>
                </div>
            </div> -->

            <!-- Pending Approval Notifications -->
            <!-- <div class="card shadow-sm mt-3">
                <div class="card-header bg-warning text-dark d-flex justify-content-between align-items-center">
                    <h6 class="mb-0"><i class="fas fa-clipboard-check me-2"></i>Persetujuan Peminjaman</h6>
                    <span class="badge bg-dark text-white"><?= intval($pending_approvals_total) ?></span>
                </div>
                <div class="card-body">
                    <?php if ($pending_approvals_total > 0): ?>
                        <div class="list-group list-group-flush">
                            <?php foreach (array_slice($pending_approvals_items, 0, 5) as $item): ?>
                                <div class="list-group-item px-0 d-flex justify-content-between">
                                    <div>
                                        <div class="fw-semibold">
                                            <?php if (!empty($item['no_polisi'])): ?>
                                                <?= htmlspecialchars($item['no_polisi']) ?>
                                            <?php else: ?>
                                                <?= htmlspecialchars($item['merk'] ?? '') ?> <?= htmlspecialchars($item['tipe'] ?? '') ?>
                                            <?php endif; ?>
                                        </div>
                                        <small class="text-muted">
                                            <?= htmlspecialchars($item['peminjam_nama'] ?? 'Pemohon') ?>
                                            • <?= !empty($item['tanggal_mulai']) ? date('d/m/Y', strtotime($item['tanggal_mulai'])) : '-' ?>
                                            <?= !empty($item['tanggal_selesai']) ? ' - ' . date('d/m/Y', strtotime($item['tanggal_selesai'])) : '' ?>
                                        </small>
                                    </div>
                                    <div class="text-nowrap">
                                        <a href="index.php?page=persetujuan_peminjaman" class="btn btn-sm btn-outline-dark">
                                            <i class="fas fa-check"></i>
                                        </a>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                        <div class="text-end mt-2">
                            <a href="index.php?page=persetujuan_peminjaman" class="btn btn-sm btn-dark">
                                Lihat Semua
                            </a>
                        </div>
                    <?php else: ?>
                        <div class="text-center py-3">
                            <i class="fas fa-inbox fa-2x text-muted mb-2"></i>
                            <p class="text-muted mb-0">Tidak ada pengajuan menunggu</p>
                        </div>
                    <?php endif; ?>
                </div>
            </div> -->

            <!-- Active Borrowers Table -->
            <!-- <div class="card shadow-sm mt-3">
                <div class="card-header bg-info text-white d-flex justify-content-between align-items-center">
                    <h6 class="mb-0"><i class="fas fa-id-card-alt me-2"></i>Peminjam Kendaraan Aktif</h6>
                    <a href="index.php?page=monitoring_peminjaman" class="btn btn-sm btn-light">
                        <i class="fas fa-eye"></i> Lihat Semua
                    </a>
                </div>
                <div class="card-body p-0">
                    <?php if (!empty($active_loans)): ?>
                        <div class="table-responsive" style="max-height: 300px;">
                            <table class="table table-sm mb-0">
                                <thead class="bg-light sticky-top">
                                    <tr>
                                        <th>Peminjam</th>
                                        <th>Kendaraan</th>
                                        <th>Periode</th>
                                        <th>Status</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($active_loans as $al): ?>
                                        <tr>
                                            <td>
                                                <div class="fw-semibold"><?= htmlspecialchars($al['peminjam_nama'] ?? 'N/A') ?></div>
                                                <?php if (!empty($al['nomor_surat'])): ?>
                                                    <small class="text-muted">No: <?= htmlspecialchars($al['nomor_surat']) ?></small>
                                                <?php endif; ?>
                                            </td>
                                            <td>
                                                <div class="fw-semibold">
                                                    <?= htmlspecialchars($al['no_polisi'] ?? '') ?>
                                                </div>
                                                <small class="text-muted">
                                                    <?= htmlspecialchars($al['merk'] ?? '') ?> <?= htmlspecialchars($al['tipe'] ?? '') ?>
                                                </small>
                                            </td>
                                            <td>
                                                <small class="text-muted">
                                                    <?= !empty($al['tanggal_mulai']) ? date('d/m/Y', strtotime($al['tanggal_mulai'])) : '-' ?>
                                                    <?= !empty($al['tanggal_selesai']) ? ' - ' . date('d/m/Y', strtotime($al['tanggal_selesai'])) : '' ?>
                                                </small>
                                            </td>
                                            <td>
                                                <?php 
                                                    $sraw = strtolower($al['status'] ?? '');
                                                    $active_aliases = ['approved','ongoing','disetujui','berjalan','aktif','dipinjam','sedang_dipinjam'];
                                                    $cls = in_array($sraw, $active_aliases, true) ? 'success' : 'secondary';
                                                    $label_map = [
                                                        'approved' => 'Disetujui',
                                                        'disetujui' => 'Disetujui',
                                                        'ongoing' => 'Berjalan',
                                                        'berjalan' => 'Berjalan',
                                                        'aktif' => 'Aktif',
                                                        'dipinjam' => 'Dipinjam',
                                                        'sedang_dipinjam' => 'Dipinjam',
                                                    ];
                                                    $label = $label_map[$sraw] ?? ucfirst($al['status'] ?? '');
                                                ?>
                                                <span class="badge bg-<?= $cls ?>">
                                                    <?= htmlspecialchars($label) ?>
                                                </span>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    <?php else: ?>
                        <div class="text-center py-3">
                            <i class="fas fa-clipboard-list fa-3x text-muted mb-2"></i>
                            <p class="text-muted mb-0">Tidak ada peminjaman aktif</p>
                        </div>
                    <?php endif; ?>
                </div>
            </div> -->
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
                                        <!-- Estimasi Biaya removed from admin scheduled maintenance -->
                                        <?php if (!empty($maintenance['km_saat_perawatan'])): ?>
                                                <br><small class="text-muted"><?= number_format($maintenance['km_saat_perawatan']) ?> km</small>
                                            <?php endif; ?>
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
