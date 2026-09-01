<?php
if (!is_logged_in()) {
    header('Location: login.php');
    exit();
}

$role = get_current_role();
if ($role === 'driver') {
    header('Location: index.php?page=dashboard_driver');
    exit();
}
if (!is_admin_like()) {
    header('Location: index.php?page=403');
    exit();
}

$current_user = get_logged_in_user();

// Statistik Kendaraan
// initialize defaults to avoid undefined index notices when queries fail
$stats_kendaraan = [
    'total' => 0,
    'by_status' => []
];

// Total kendaraan
$result = $mysqli->query("SELECT COUNT(*) as total FROM kendaraan");
if ($result) {
    $row = $result->fetch_assoc();
    $stats_kendaraan['total'] = isset($row['total']) ? intval($row['total']) : 0;
}

// Kendaraan berdasarkan status
$result = $mysqli->query("SELECT status_kendaraan, COUNT(*) as jumlah FROM kendaraan GROUP BY status_kendaraan");
while ($row = $result->fetch_assoc()) {
    $stats_kendaraan['by_status'][$row['status_kendaraan']] = $row['jumlah'];
}

// Statistik Peminjaman
$stats_peminjaman = [];

// Set default values
$stats_peminjaman = [
    'total' => 0,
    'pending' => 0,
    'approved' => 0,
    'ongoing' => 0,
    'completed' => 0
];

// Dokumen yang akan expire
$dokumen_expire = [];
$result = $mysqli->query("
    SELECT k.no_reg, dk.jenis_dokumen, dk.tanggal_berlaku,
           DATEDIFF(dk.tanggal_berlaku, CURRENT_DATE()) as hari_tersisa
    FROM dokumen_kendaraan dk
    JOIN kendaraan k ON dk.kendaraan_id = k.id
    WHERE dk.tanggal_berlaku BETWEEN CURRENT_DATE() AND DATE_ADD(CURRENT_DATE(), INTERVAL 30 DAY)
    ORDER BY dk.tanggal_berlaku ASC
    LIMIT 10
");
$dokumen_expire = $result ? $result->fetch_all(MYSQLI_ASSOC) : [];

// Jadwal Perawatan Mendatang
$jadwal_perawatan = [];
$result = $mysqli->query("
    SELECT jp.*, k.no_reg, k.merk, k.tipe,
           DATEDIFF(jp.tanggal_perawatan, CURRENT_DATE()) as hari_tersisa
    FROM jadwal_perawatan jp
    JOIN kendaraan k ON jp.kendaraan_id = k.id
    WHERE jp.status = 'Terjadwal' 
    AND jp.tanggal_perawatan BETWEEN CURRENT_DATE() AND DATE_ADD(CURRENT_DATE(), INTERVAL 14 DAY)
    ORDER BY jp.tanggal_perawatan ASC
    LIMIT 10
");
$jadwal_perawatan = $result ? $result->fetch_all(MYSQLI_ASSOC) : [];

// Activity Log terbaru
$recent_activities = [];

function getStatusBadge($status) {
    $badges = [
        'aktif' => 'success',
        'maintenance' => 'warning',
        'tidak_aktif' => 'secondary',
        'pending' => 'warning',
        'approved' => 'info',
        'ongoing' => 'primary',
        'completed' => 'success',
        'rejected' => 'danger',
        'cancelled' => 'secondary'
    ];
    return $badges[$status] ?? 'secondary';
}
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
    <div class="row mb-4">
        <div class="col-lg-3 col-md-6 mb-3">
            <div class="card bg-primary text-white shadow-sm card-hover h-100">
            <div class="card-body d-flex align-items-center">
                <div class="me-3">
                <div class="bg-white bg-opacity-25 rounded-circle d-flex align-items-center justify-content-center" style="width: 60px; height: 60px;">
                    <i class="fas fa-car fa-2x text-white"></i>
                </div>
                </div>
                <div>
                <h3 class="mb-0 fw-bold"><?= number_format($stats_kendaraan['total']) ?></h3>
                <p class="mb-0 opacity-75">Total Kendaraan</p>
                </div>
            </div>
            </div>
        </div>
        <div class="col-lg-3 col-md-6 mb-3">
            <div class="card bg-success text-white shadow-sm card-hover h-100">
                <div class="card-body d-flex align-items-center">
                    <div class="me-3">
                        <div class="bg-white bg-opacity-25 rounded-circle d-flex align-items-center justify-content-center" style="width: 60px; height: 60px;">
                            <i class="fas fa-check-circle fa-2x text-white"></i>
                        </div>
                    </div>
                    <div>
                        <h3 class="mb-0 fw-bold"><?= number_format($stats_kendaraan['by_status']['Operasional'] ?? 0) ?></h3>
                        <p class="mb-0 opacity-75">Operasional</p>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-lg-3 col-md-6 mb-3">
            <div class="card bg-warning text-white shadow-sm card-hover h-100">
                <div class="card-body d-flex align-items-center">
                    <div class="me-3">
                        <div class="bg-white bg-opacity-25 rounded-circle d-flex align-items-center justify-content-center" style="width: 60px; height: 60px;">
                            <i class="fas fa-tools fa-2x text-white"></i>
                        </div>
                    </div>
                    <div>
                        <h3 class="mb-0 fw-bold"><?= number_format($stats_kendaraan['by_status']['Perbaikan'] ?? 0) ?></h3>
                        <p class="mb-0 opacity-75">Perbaikan</p>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-lg-3 col-md-6 mb-3">
            <div class="card bg-info text-white shadow-sm card-hover h-100">
                <div class="card-body d-flex align-items-center">
                    <div class="me-3">
                        <div class="bg-white bg-opacity-25 rounded-circle d-flex align-items-center justify-content-center" style="width: 60px; height: 60px;">
                            <i class="fas fa-calendar-alt fa-2x text-white"></i>
                        </div>
                    </div>
                    <div>
                        <h3 class="mb-0 fw-bold"><?= number_format($stats_peminjaman['ongoing'] ?? 0) ?></h3>
                        <p class="mb-0 opacity-75">Peminjaman Aktif</p>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Charts Row -->
    </div>
    <!-- Jenis distribution chart removed (jenis field deprecated) -->
    </div>

    <!-- Alerts & Quick Access -->
    <div class="row mb-4">
        <!-- Dokumen Expire -->
        <div class="col-lg-6">
            <div class="card">
                <div class="card-header bg-warning text-dark">
                    <h5><i class="fas fa-exclamation-triangle"></i> Dokumen Akan Expire</h5>
                </div>
                <div class="card-body p-0">
                    <div class="table-responsive" style="max-height: 300px;">
                        <table class="table table-sm mb-0">
                            <thead class="bg-light">
                                <tr>
                                    <th>No. Reg</th>
                                    <th>Dokumen</th>
                                    <th>Expire</th>
                                    <th>Hari</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if (empty($dokumen_expire)): ?>
                                    <tr>
                                        <td colspan="4" class="text-center text-muted py-3">
                                            <i class="fas fa-check-circle"></i> Tidak ada dokumen yang akan expire
                                        </td>
                                    </tr>
                                <?php else: ?>
                                    <?php foreach ($dokumen_expire as $dok): ?>
                                        <tr class="<?= $dok['hari_tersisa'] <= 7 ? 'table-danger' : 'table-warning' ?>">
                                            <td><strong><?= htmlspecialchars($dok['no_reg'] ?? $dok['nopol'] ?? '') ?></strong></td>
                                            <td><?= htmlspecialchars($dok['jenis_dokumen']) ?></td>
                                            <td><?= date('d/m/Y', strtotime($dok['tanggal_berlaku'] ?? '')) ?></td>
                                            <td>
                                                <span class="badge badge-<?= ($dok['hari_tersisa'] ?? 999) <= 7 ? 'danger' : 'warning' ?>">
                                                    <?= $dok['hari_tersisa'] ?? '-' ?> hari
                                                </span>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                    <div class="card-footer">
                        <a href="index.php?page=dokumen_kendaraan" class="btn btn-sm btn-warning">
                            <i class="fas fa-eye"></i> Lihat Semua Dokumen
                        </a>
                    </div>
                </div>
            </div>
        </div>

        <!-- Jadwal Perawatan -->
        <div class="col-lg-6">
            <div class="card">
                <div class="card-header bg-info text-white">
                    <h5><i class="fas fa-tools"></i> Jadwal Perawatan Mendatang</h5>
                </div>
                <div class="card-body p-0">
                    <div class="table-responsive" style="max-height: 300px;">
                        <table class="table table-sm mb-0">
                            <thead class="bg-light">
                                <tr>
                                    <th>Kendaraan</th>
                                    <th>Jenis</th>
                                    <th>Tanggal</th>
                                    <th>Status</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if (empty($jadwal_perawatan)): ?>
                                    <tr>
                                        <td colspan="4" class="text-center text-muted py-3">
                                            <i class="fas fa-calendar-check"></i> Tidak ada jadwal mendatang
                                        </td>
                                    </tr>
                                <?php else: ?>
                                    <?php foreach ($jadwal_perawatan as $jadwal): ?>
                                        <tr>
                                            <td>
                                                <strong><?= htmlspecialchars($jadwal['no_reg']) ?></strong><br>
                                                <small class="text-muted"><?= htmlspecialchars($jadwal['merk'] . ' ' . $jadwal['tipe']) ?></small>
                                            </td>
                                            <td><?= htmlspecialchars($jadwal['jenis_perawatan']) ?></td>
                                            <td>
                                                    <?= date('d/m/Y', strtotime($jadwal['tanggal_perawatan'] ?? $jadwal['jadwal_tanggal'] ?? '')) ?><br>
                                                    <small class="text-muted"><?= $jadwal['hari_tersisa'] ?? '-' ?> hari lagi</small>
                                                </td>
                                            <td>
                                                <span class="badge badge-<?= getStatusBadge($jadwal['status']) ?>">
                                                    <?= ucfirst($jadwal['status']) ?>
                                                </span>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                    <div class="card-footer">
                        <a href="index.php?page=jadwal_perawatan" class="btn btn-sm btn-info">
                            <i class="fas fa-calendar"></i> Kelola Jadwal
                        </a>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Riwayat Perawatan Terbaru -->
    <div class="row mb-4">
        <div class="col-12">
            <div class="card">
                <div class="card-header d-flex justify-content-between align-items-center">
                    <h4 class="card-title mb-0">
                        <i class="fas fa-history text-success"></i>
                        Riwayat Perawatan Terbaru
                    </h4>
                    <a href="index.php?page=riwayat_perawatan" class="btn btn-sm btn-success">
                        <i class="fas fa-list"></i> Lihat Semua
                    </a>
                </div>
                <div class="card-body p-0">
                    <div class="table-responsive">
                        <table class="table table-sm mb-0">
                            <thead class="bg-light">
                                <tr>
                                    <th>Tanggal</th>
                                    <th>Kendaraan</th>
                                    <th>Jenis Perawatan</th>
                                    <th>Bengkel/Mekanik</th>
                                    <!-- Biaya removed from recent maintenance -->
                                    <th>Status</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php 
                                // Ambil riwayat perawatan terbaru
                                $recent_maintenance = $mysqli->query("
                                    SELECT rp.*, k.no_reg, k.merk, k.tipe
                                    FROM riwayat_perawatan rp 
                                    LEFT JOIN kendaraan k ON rp.kendaraan_id = k.id 
                                    ORDER BY rp.tanggal_perawatan DESC 
                                    LIMIT 5
                                ");
                                
                                if ($recent_maintenance && $recent_maintenance->num_rows > 0): 
                                    while ($maintenance = $recent_maintenance->fetch_assoc()): 
                                ?>
                                    <tr>
                                        <td>
                                            <strong><?= date('d/m/Y', strtotime($maintenance['tanggal_perawatan'])) ?></strong><br>
                                            <small class="text-muted"><?= date('H:i', strtotime($maintenance['created_at'])) ?></small>
                                        </td>
                                        <td>
                                            <strong><?= htmlspecialchars($maintenance['no_reg']) ?></strong><br>
                                            <small class="text-muted"><?= htmlspecialchars($maintenance['merk'] . ' ' . $maintenance['tipe']) ?></small>
                                        </td>
                                        <td>
                                            <strong><?= htmlspecialchars($maintenance['jenis_perawatan']) ?></strong>
                                            <?php if (!empty($maintenance['deskripsi'])): ?>
                                                <br><small class="text-muted"><?= htmlspecialchars(substr($maintenance['deskripsi'], 0, 50)) ?><?= strlen($maintenance['deskripsi']) > 50 ? '...' : '' ?></small>
                                            <?php endif; ?>
                                        </td>
                                        <td>
                                            <?php if (!empty($maintenance['mekanik'])): ?>
                                                <strong><?= htmlspecialchars($maintenance['mekanik']) ?></strong>
                                            <?php else: ?>
                                                <span class="text-muted">-</span>
                                            <?php endif; ?>
                                            <?php if (!empty($maintenance['status'])): ?>
                                                <br><span class="badge badge-sm <?= $maintenance['status'] === 'Selesai' ? 'badge-success' : ($maintenance['status'] === 'Ongoing' ? 'badge-warning' : 'badge-secondary') ?>"><?= htmlspecialchars($maintenance['status']) ?></span>
                                            <?php endif; ?>
                                        </td>
                                        <!-- Biaya removed from recent maintenance -->
                                        <td>
                                            <span class="badge badge-success">
                                                <i class="fas fa-check-circle"></i> <?= htmlspecialchars($maintenance['status']) ?>
                                            </span>
                                        </td>
                                    </tr>
                                <?php 
                                    endwhile; 
                                else: 
                                ?>
                                    <tr>
                                        <td colspan="5" class="text-center text-muted py-3">
                                            <i class="fas fa-tools"></i> Belum ada riwayat perawatan
                                        </td>
                                    </tr>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
                <div class="card-footer">
                    <div class="row">
                        <div class="col-md-6">
                            <small class="text-muted">
                                <i class="fas fa-info-circle"></i> 
                                Menampilkan 5 perawatan terbaru
                            </small>
                        </div>
                        <div class="col-md-6 text-right">
                            <a href="index.php?page=riwayat_perawatan" class="btn btn-sm btn-outline-success">
                                <i class="fas fa-eye"></i> Lihat Detail
                            </a>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>


    <!-- Quick Actions -->
    <div class="row">
        <div class="col-12">
            <div class="card shadow-sm">
                <div class="card-header bg-dark text-white">
                    <h5 class="mb-0"><i class="fas fa-bolt me-2"></i>Aksi Cepat</h5>
                </div>
                <div class="card-body">
                    <div class="row g-3">
                        <div class="col-md-3">
                            <a href="index.php?page=kendaraan" class="btn btn-primary w-100">
                                <i class="fas fa-car me-2"></i>Kelola Kendaraan
                            </a>
                        </div>
                        <div class="col-md-3">
                            <a href="index.php?page=dokumen_kendaraan" class="btn btn-warning w-100">
                                <i class="fas fa-file-alt me-2"></i>Kelola Dokumen
                            </a>
                        </div>
                        <div class="col-md-3">
                            <a href="index.php?page=jadwal_perawatan" class="btn btn-info w-100">
                                <i class="fas fa-tools me-2"></i>Jadwal Perawatan
                            </a>
                        </div>
                        <div class="col-md-3">
                            <a href="index.php?page=riwayat_pemakaian" class="btn btn-success w-100">
                                <i class="fas fa-chart-line me-2"></i>Laporan
                            </a>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Include Chart.js -->
<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>

<script>
// Chart untuk peminjaman bulan ini
</script>
