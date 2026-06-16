<?php
if (!is_logged_in()) {
    header('Location: login.php');
    exit();
}

$role = get_current_role();
if ($role !== 'pimpinan') {
    header('Location: index.php?page=403');
    exit();
}

$current_user = get_logged_in_user();
$current_user_id = get_current_user_id();

$stats = [
    'pending_peminjaman' => 0,
    'pending_surat_tugas' => 0,
    'approved_surat_tugas' => 0,
    'driver_ready' => 0,
];

// Pending peminjaman that need pimpinan attention
$r = $mysqli->query("SELECT COUNT(*) AS cnt FROM peminjaman_kendaraan WHERE LOWER(status) = 'pending' AND surat_tugas_id IS NOT NULL");
if ($r) {
    $row = $r->fetch_assoc();
    $stats['pending_peminjaman'] = (int)($row['cnt'] ?? 0);
}

// Surat tugas approval pipeline
$r = $mysqli->query("SELECT COUNT(*) AS cnt FROM surat_tugas WHERE approval_pimpinan_status = 'Pending'");
if ($r) {
    $row = $r->fetch_assoc();
    $stats['pending_surat_tugas'] = (int)($row['cnt'] ?? 0);
}

$r = $mysqli->query("SELECT COUNT(*) AS cnt FROM surat_tugas WHERE approval_pimpinan_status = 'Approved'");
if ($r) {
    $row = $r->fetch_assoc();
    $stats['approved_surat_tugas'] = (int)($row['cnt'] ?? 0);
}

$r = $mysqli->query("SELECT COUNT(*) AS cnt FROM user_account ua JOIN role r ON ua.role_id = r.id WHERE ua.status = 'Aktif' AND UPPER(r.kode_role) = 'DRIVER'");
if ($r) {
    $row = $r->fetch_assoc();
    $stats['driver_ready'] = (int)($row['cnt'] ?? 0);
}

$pending_requests = [];
$r = $mysqli->query("SELECT pk.id, pk.surat_tugas_id, pk.tanggal_mulai, pk.tanggal_selesai, pk.tujuan, pk.keperluan, s.nomor_surat, s.status AS surat_status, k.no_reg, k.no_polisi, p.nama_lengkap AS pemohon_nama
                    FROM peminjaman_kendaraan pk
                    LEFT JOIN surat_tugas s ON pk.surat_tugas_id = s.id
                    LEFT JOIN kendaraan k ON pk.kendaraan_id = k.id
                    LEFT JOIN pengguna p ON pk.peminjam_id = p.id
                    WHERE LOWER(pk.status) = 'pending' AND pk.surat_tugas_id IS NOT NULL
                    ORDER BY pk.created_at DESC
                    LIMIT 8");
if ($r) {
    $pending_requests = $r->fetch_all(MYSQLI_ASSOC);
}

$pending_surat = [];
$r = $mysqli->query("SELECT s.id, s.nomor_surat, s.tanggal_surat, s.tujuan, s.keperluan, s.approval_pimpinan_status, k.no_reg, k.no_polisi, p.nama_lengkap AS pemohon_nama
                    FROM surat_tugas s
                    LEFT JOIN kendaraan k ON s.kendaraan_id = k.id
                    LEFT JOIN pengguna p ON s.pengguna_id = p.id
                    WHERE s.approval_pimpinan_status = 'Pending'
                    ORDER BY s.created_at DESC
                    LIMIT 8");
if ($r) {
    $pending_surat = $r->fetch_all(MYSQLI_ASSOC);
}

$approved_recent = [];
$r = $mysqli->query("SELECT s.id, s.nomor_surat, s.tanggal_surat, s.approval_pimpinan_at, k.no_reg, k.no_polisi, p.nama_lengkap AS pemohon_nama
                    FROM surat_tugas s
                    LEFT JOIN kendaraan k ON s.kendaraan_id = k.id
                    LEFT JOIN pengguna p ON s.pengguna_id = p.id
                    WHERE s.approval_pimpinan_status = 'Approved'
                    ORDER BY s.approval_pimpinan_at DESC, s.updated_at DESC
                    LIMIT 8");
if ($r) {
    $approved_recent = $r->fetch_all(MYSQLI_ASSOC);
}

$current_page = 'dashboard_pimpinan';
?>
<div class="p-4 mb-4 rounded" style="background: linear-gradient(135deg, #12354a 0%, #1f6f8b 100%); color: #fff;">
    <div class="row align-items-center">
        <div class="col-md-8">
            <h1 class="mb-1"><i class="fas fa-user-tie me-2"></i>Dashboard Pimpinan</h1>
            <p class="mb-0 opacity-75">Selamat datang, <?= htmlspecialchars($current_user['nama_lengkap'] ?? 'Pimpinan') ?></p>
        </div>
        <div class="col-md-4 text-md-end">
            <span class="badge bg-light text-dark p-2 fs-6">
                <i class="fas fa-calendar me-1"></i><?= date('d F Y') ?>
            </span>
        </div>
    </div>
</div>

<div class="container-fluid">
    <div class="row mb-4">
        <div class="col-md-3 mb-3">
            <div class="card shadow-sm h-100 border-0" style="border-left: 4px solid #ffc107;">
                <div class="card-body">
                    <div class="d-flex justify-content-between align-items-center">
                        <div>
                            <div class="text-muted small">Menunggu Persetujuan</div>
                            <h2 class="mb-0"><?= number_format($stats['pending_surat_tugas']) ?></h2>
                        </div>
                        <i class="fas fa-hourglass-half fa-2x text-warning"></i>
                    </div>
                    <small class="text-muted">Surat tugas yang menunggu validasi pimpinan</small>
                </div>
            </div>
        </div>
        <div class="col-md-3 mb-3">
            <div class="card shadow-sm h-100 border-0" style="border-left: 4px solid #0d6efd;">
                <div class="card-body">
                    <div class="d-flex justify-content-between align-items-center">
                        <div>
                            <div class="text-muted small">Permohonan Peminjaman</div>
                            <h2 class="mb-0"><?= number_format($stats['pending_peminjaman']) ?></h2>
                        </div>
                        <i class="fas fa-clipboard-check fa-2x text-primary"></i>
                    </div>
                    <small class="text-muted">Antrian yang diturunkan dari surat tugas</small>
                </div>
            </div>
        </div>
        <div class="col-md-3 mb-3">
            <div class="card shadow-sm h-100 border-0" style="border-left: 4px solid #198754;">
                <div class="card-body">
                    <div class="d-flex justify-content-between align-items-center">
                        <div>
                            <div class="text-muted small">Sudah Disetujui</div>
                            <h2 class="mb-0"><?= number_format($stats['approved_surat_tugas']) ?></h2>
                        </div>
                        <i class="fas fa-check-circle fa-2x text-success"></i>
                    </div>
                    <small class="text-muted">Surat tugas yang siap dipakai driver</small>
                </div>
            </div>
        </div>
        <div class="col-md-3 mb-3">
            <div class="card shadow-sm h-100 border-0" style="border-left: 4px solid #6f42c1;">
                <div class="card-body">
                    <div class="d-flex justify-content-between align-items-center">
                        <div>
                            <div class="text-muted small">Driver Aktif</div>
                            <h2 class="mb-0"><?= number_format($stats['driver_ready']) ?></h2>
                        </div>
                        <i class="fas fa-id-badge fa-2x text-purple"></i>
                    </div>
                    <small class="text-muted">Driver yang bisa menerima penugasan</small>
                </div>
            </div>
        </div>
    </div>

    <div class="row">
        <div class="col-lg-6 mb-4">
            <div class="card shadow-sm h-100">
                <div class="card-header bg-dark text-white">
                    <strong>Surat Tugas Menunggu Pimpinan</strong>
                </div>
                <div class="card-body p-0">
                    <div class="table-responsive">
                        <table class="table table-hover mb-0">
                            <thead class="table-light">
                                <tr>
                                    <th>Nomor</th>
                                    <th>Driver/Pemohon</th>
                                    <th>Kendaraan</th>
                                    <th>Aksi</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if (!empty($pending_surat)): ?>
                                    <?php foreach ($pending_surat as $row): ?>
                                        <tr>
                                            <td><?= htmlspecialchars($row['nomor_surat']) ?></td>
                                            <td><?= htmlspecialchars($row['pemohon_nama'] ?? '-') ?></td>
                                            <td><?= htmlspecialchars(($row['no_reg'] ?: ($row['no_polisi'] ?? '-'))) ?></td>
                                            <td><a class="btn btn-sm btn-warning" href="index.php?page=persetujuan_peminjaman&action=approve_surat&id=<?= (int)$row['id'] ?>">Proses</a></td>
                                        </tr>
                                    <?php endforeach; ?>
                                <?php else: ?>
                                    <tr><td colspan="4" class="text-center text-muted py-4">Tidak ada surat tugas yang menunggu pimpinan</td></tr>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>

        <div class="col-lg-6 mb-4">
            <div class="card shadow-sm h-100">
                <div class="card-header bg-dark text-white">
                    <strong>Permohonan Peminjaman dari Surat Tugas</strong>
                </div>
                <div class="card-body p-0">
                    <div class="table-responsive">
                        <table class="table table-hover mb-0">
                            <thead class="table-light">
                                <tr>
                                    <th>Surat</th>
                                    <th>Periode</th>
                                    <th>Aksi</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if (!empty($pending_requests)): ?>
                                    <?php foreach ($pending_requests as $row): ?>
                                        <tr>
                                            <td><?= htmlspecialchars($row['nomor_surat'] ?? '-') ?></td>
                                            <td><?= !empty($row['tanggal_mulai']) ? date('d/m/Y', strtotime($row['tanggal_mulai'])) : '-' ?></td>
                                            <td><a class="btn btn-sm btn-outline-success" href="index.php?page=persetujuan_peminjaman&action=approve&id=<?= (int)$row['id'] ?>">Proses</a></td>
                                        </tr>
                                    <?php endforeach; ?>
                                <?php else: ?>
                                    <tr><td colspan="3" class="text-center text-muted py-4">Belum ada permohonan peminjaman</td></tr>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div class="row">
        <div class="col-lg-6 mb-4">
            <div class="card shadow-sm h-100">
                <div class="card-header bg-dark text-white">
                    <strong>Surat Disetujui Terbaru</strong>
                </div>
                <div class="card-body p-0">
                    <div class="table-responsive">
                        <table class="table table-hover mb-0">
                            <thead class="table-light">
                                <tr>
                                    <th>Nomor</th>
                                    <th>Driver/Pemohon</th>
                                    <th>Tgl Persetujuan</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if (!empty($approved_recent)): ?>
                                    <?php foreach ($approved_recent as $row): ?>
                                        <tr>
                                            <td><?= htmlspecialchars($row['nomor_surat']) ?></td>
                                            <td><?= htmlspecialchars($row['pemohon_nama'] ?? '-') ?></td>
                                            <td><?= !empty($row['approval_pimpinan_at']) ? date('d/m/Y H:i', strtotime($row['approval_pimpinan_at'])) : '-' ?></td>
                                        </tr>
                                    <?php endforeach; ?>
                                <?php else: ?>
                                    <tr><td colspan="3" class="text-center text-muted py-4">Belum ada surat tugas yang disetujui pimpinan</td></tr>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>

        <div class="col-lg-6 mb-4">
            <div class="card shadow-sm h-100">
                <div class="card-header bg-dark text-white">
                    <strong>Akses Cepat</strong>
                </div>
                <div class="card-body d-grid gap-2">
                    <a class="btn btn-primary" href="index.php?page=persetujuan_peminjaman"><i class="fas fa-clipboard-check me-2"></i>Persetujuan Peminjaman</a>
                    <a class="btn btn-outline-primary" href="index.php?page=surat_tugas"><i class="fas fa-file-signature me-2"></i>Surat Tugas</a>
                    <a class="btn btn-outline-secondary" href="index.php?page=jadwal_perawatan"><i class="fas fa-calendar-alt me-2"></i>Jadwal Perawatan</a>
                </div>
            </div>
        </div>
    </div>
</div>
