<?php
require_once 'includes/auth.php';
require_login();
require_role('pimpinan');

$current_user    = get_logged_in_user();
$current_user_id = get_current_user_id();

$stats = [
    'pending_surat_tugas'  => 0,
    'pending_peminjaman'   => 0,
    'approved_surat_tugas' => 0,
    'driver_ready'         => 0,
];

$r = $mysqli->query("SELECT COUNT(*) AS cnt FROM surat_tugas WHERE approval_pimpinan_status = 'Pending'");
if ($r) { $stats['pending_surat_tugas'] = (int)($r->fetch_assoc()['cnt'] ?? 0); }

$r = $mysqli->query("SELECT COUNT(*) AS cnt FROM peminjaman_kendaraan WHERE LOWER(status) = 'pending'");
if ($r) { $stats['pending_peminjaman'] = (int)($r->fetch_assoc()['cnt'] ?? 0); }

$r = $mysqli->query("SELECT COUNT(*) AS cnt FROM surat_tugas WHERE approval_pimpinan_status = 'Approved'");
if ($r) { $stats['approved_surat_tugas'] = (int)($r->fetch_assoc()['cnt'] ?? 0); }

$r = $mysqli->query("SELECT COUNT(*) AS cnt FROM user_account ua JOIN role r ON ua.role_id = r.id WHERE ua.status = 'Aktif' AND UPPER(r.kode_role) = 'DRIVER'");
if ($r) { $stats['driver_ready'] = (int)($r->fetch_assoc()['cnt'] ?? 0); }

$pending_surat = [];
$r = $mysqli->query("
    SELECT s.id, s.nomor_surat, s.tanggal_surat, s.tujuan, s.keperluan,
           k.no_reg, k.no_polisi, p.nama_lengkap AS pemohon_nama
    FROM surat_tugas s
    LEFT JOIN kendaraan k ON s.kendaraan_id = k.id
    LEFT JOIN pengguna p ON s.pengguna_id = p.id
    WHERE s.approval_pimpinan_status = 'Pending'
    ORDER BY s.created_at DESC
    LIMIT 8");
if ($r) { $pending_surat = $r->fetch_all(MYSQLI_ASSOC); }

$pending_requests = [];
$r = $mysqli->query("
    SELECT pk.id, pk.tanggal_mulai, pk.tanggal_selesai, pk.keperluan,
           s.nomor_surat, k.no_reg, k.no_polisi, p.nama_lengkap AS pemohon_nama
    FROM peminjaman_kendaraan pk
    LEFT JOIN surat_tugas s ON pk.surat_tugas_id = s.id
    LEFT JOIN kendaraan k ON pk.kendaraan_id = k.id
    LEFT JOIN pengguna p ON pk.peminjam_id = p.id
    WHERE LOWER(pk.status) = 'pending'
    ORDER BY pk.created_at DESC
    LIMIT 8");
if ($r) { $pending_requests = $r->fetch_all(MYSQLI_ASSOC); }

$approved_recent = [];
$r = $mysqli->query("
    SELECT s.id, s.nomor_surat, s.tanggal_surat, s.approval_pimpinan_at,
           k.no_reg, k.no_polisi, p.nama_lengkap AS pemohon_nama
    FROM surat_tugas s
    LEFT JOIN kendaraan k ON s.kendaraan_id = k.id
    LEFT JOIN pengguna p ON s.pengguna_id = p.id
    WHERE s.approval_pimpinan_status = 'Approved'
    ORDER BY s.approval_pimpinan_at DESC, s.updated_at DESC
    LIMIT 8");
if ($r) { $approved_recent = $r->fetch_all(MYSQLI_ASSOC); }
?>

<div id="dashboard-pimpinan-page">

<!-- ── Welcome Header ─────────────────────────────────────────────────────── -->
<div class="gradient-header d-flex align-items-center justify-content-between">
    <div>
        <h5 class="mb-1 fw-bold">
            <i class="fas fa-user-tie me-2 opacity-75"></i>
            <?= htmlspecialchars($current_user['nama_lengkap'] ?? 'Pimpinan') ?>
        </h5>
        <p class="mb-0 opacity-75 small">Dashboard Pimpinan &mdash; Monitoring & Persetujuan</p>
    </div>
    <div class="text-end">
        <span class="badge bg-light text-dark p-2">
            <i class="fas fa-calendar me-1"></i><?= date('d F Y') ?>
        </span>
    </div>
</div>

<!-- ── Stat Cards ─────────────────────────────────────────────────────────── -->
<div class="row mb-3">
    <div class="col-lg-3 col-md-6 mb-3">
        <div class="card bg-warning text-dark shadow-sm card-hover h-100">
            <div class="card-body d-flex align-items-center">
                <div class="me-3">
                    <div class="bg-white bg-opacity-25 rounded-circle d-flex align-items-center justify-content-center stat-icon-circle">
                        <i class="fas fa-hourglass-half fa-2x text-dark"></i>
                    </div>
                </div>
                <div>
                    <h3 class="mb-0 fw-bold"><?= $stats['pending_surat_tugas'] ?></h3>
                    <p class="mb-0 fw-bold text-white opacity-75">Surat Tugas Menunggu</p>
                </div>
            </div>
        </div>
    </div>
    <div class="col-lg-3 col-md-6 mb-3">
        <div class="card bg-danger text-white shadow-sm card-hover h-100">
            <div class="card-body d-flex align-items-center">
                <div class="me-3">
                    <div class="bg-white bg-opacity-25 rounded-circle d-flex align-items-center justify-content-center stat-icon-circle">
                        <i class="fas fa-clipboard-list fa-2x text-white"></i>
                    </div>
                </div>
                <div>
                    <h3 class="mb-0 fw-bold"><?= $stats['pending_peminjaman'] ?></h3>
                    <p class="mb-0 fw-bold text-white opacity-75">Permohonan Peminjaman</p>
                </div>
            </div>
        </div>
    </div>
    <div class="col-lg-3 col-md-6 mb-3">
        <div class="card bg-success text-white shadow-sm card-hover h-100">
            <div class="card-body d-flex align-items-center">
                <div class="me-3">
                    <div class="bg-white bg-opacity-25 rounded-circle d-flex align-items-center justify-content-center stat-icon-circle">
                        <i class="fas fa-check-circle fa-2x text-white"></i>
                    </div>
                </div>
                <div>
                    <h3 class="mb-0 fw-bold"><?= $stats['approved_surat_tugas'] ?></h3>
                    <p class="mb-0 fw-bold text-white opacity-75">Surat Tugas Disetujui</p>
                </div>
            </div>
        </div>
    </div>
    <div class="col-lg-3 col-md-6 mb-3">
        <div class="card bg-primary text-white shadow-sm card-hover h-100">
            <div class="card-body d-flex align-items-center">
                <div class="me-3">
                    <div class="bg-white bg-opacity-25 rounded-circle d-flex align-items-center justify-content-center stat-icon-circle">
                        <i class="fas fa-id-badge fa-2x text-white"></i>
                    </div>
                </div>
                <div>
                    <h3 class="mb-0 fw-bold"><?= $stats['driver_ready'] ?></h3>
                    <p class="mb-0 fw-bold text-white opacity-75">Driver Aktif</p>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- ── Surat Tugas & Permohonan Peminjaman ────────────────────────────────── -->
<div class="row g-3 mb-4">
    <!-- Surat Tugas Menunggu -->
    <div class="col-lg-6">
        <div class="card shadow-sm h-100">
            <div class="card-header bg-warning text-white d-flex justify-content-between align-items-center">
                <h6 class="mb-0"><i class="fas fa-hourglass-half me-2"></i>Surat Tugas Menunggu Persetujuan</h6>
                <a href="index.php?page=persetujuan_peminjaman" class="btn btn-sm btn-dark">Semua</a>
            </div>
            <div class="card-body p-0">
                <?php if (!empty($pending_surat)): ?>
                    <ul class="list-unstyled mb-0">
                    <?php foreach ($pending_surat as $row): ?>
                        <li class="d-flex align-items-center gap-3 px-3 py-2 border-bottom">
                            <div class="rounded-circle bg-warning text-dark d-flex align-items-center justify-content-center flex-shrink-0" style="width:36px;height:36px;font-size:.8rem;">
                                <i class="fas fa-file-alt"></i>
                            </div>
                            <div class="flex-grow-1 min-w-0">
                                <div class="fw-semibold small"><?= htmlspecialchars($row['nomor_surat']) ?></div>
                                <div class="text-muted" style="font-size:.8rem;">
                                    <?= htmlspecialchars($row['pemohon_nama'] ?? '-') ?>
                                    <?php if (!empty($row['no_reg']) || !empty($row['no_polisi'])): ?>
                                        &middot; <?= htmlspecialchars($row['no_reg'] ?: $row['no_polisi']) ?>
                                    <?php endif; ?>
                                </div>
                            </div>
                            <a class="btn btn-sm btn-warning text-dark flex-shrink-0"
                               href="index.php?page=persetujuan_peminjaman&action=approve_surat&id=<?= (int)$row['id'] ?>">
                                Proses
                            </a>
                        </li>
                    <?php endforeach; ?>
                    </ul>
                <?php else: ?>
                    <div class="text-center py-4 text-muted">
                        <i class="fas fa-check-circle fa-2x mb-2 opacity-25 d-block"></i>
                        <div class="small">Tidak ada surat tugas yang menunggu persetujuan</div>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <!-- Permohonan Peminjaman -->
    <div class="col-lg-6">
        <div class="card shadow-sm h-100">
            <div class="card-header bg-danger text-white d-flex justify-content-between align-items-center">
                <h6 class="mb-0"><i class="fas fa-clipboard-list me-2"></i>Permohonan Peminjaman</h6>
                <a href="index.php?page=monitoring_peminjaman" class="btn btn-sm btn-light text-danger">Semua</a>
            </div>
            <div class="card-body p-0">
                <?php if (!empty($pending_requests)): ?>
                    <ul class="list-unstyled mb-0">
                    <?php foreach ($pending_requests as $row): ?>
                        <li class="d-flex align-items-center gap-3 px-3 py-2 border-bottom">
                            <div class="rounded-circle bg-danger text-white d-flex align-items-center justify-content-center flex-shrink-0" style="width:36px;height:36px;font-size:.8rem;">
                                <i class="fas fa-car"></i>
                            </div>
                            <div class="flex-grow-1 min-w-0">
                                <div class="fw-semibold small"><?= htmlspecialchars($row['nomor_surat'] ?? '-') ?></div>
                                <div class="text-muted" style="font-size:.8rem;">
                                    <?= htmlspecialchars($row['pemohon_nama'] ?? '-') ?>
                                    <?php if (!empty($row['tanggal_mulai'])): ?>
                                        &middot; <?= date('d/m/Y', strtotime($row['tanggal_mulai'])) ?>
                                    <?php endif; ?>
                                </div>
                            </div>
                            <a class="btn btn-sm btn-danger flex-shrink-0"
                               href="index.php?page=persetujuan_peminjaman&action=approve&id=<?= (int)$row['id'] ?>">
                                Proses
                            </a>
                        </li>
                    <?php endforeach; ?>
                    </ul>
                <?php else: ?>
                    <div class="text-center py-4 text-muted">
                        <i class="fas fa-clipboard-check fa-2x mb-2 opacity-25 d-block"></i>
                        <div class="small">Tidak ada permohonan peminjaman yang menunggu</div>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<!-- ── Riwayat Disetujui & Menu Cepat ────────────────────────────────────── -->
<div class="row g-3 mb-4">
    <!-- Surat Disetujui Terbaru -->
    <div class="col-lg-8">
        <div class="card shadow-sm h-100">
            <div class="card-header bg-success text-white">
                <h6 class="mb-0"><i class="fas fa-check-double me-2"></i>Surat Tugas Disetujui Terbaru</h6>
            </div>
            <div class="card-body p-0">
                <?php if (!empty($approved_recent)): ?>
                    <div class="table-responsive">
                        <table class="table table-hover mb-0 small">
                            <thead class="table-light">
                                <tr>
                                    <th>Nomor Surat</th>
                                    <th>Pemohon</th>
                                    <th>Kendaraan</th>
                                    <th>Tgl Persetujuan</th>
                                </tr>
                            </thead>
                            <tbody>
                            <?php foreach ($approved_recent as $row): ?>
                                <tr>
                                    <td class="fw-semibold"><?= htmlspecialchars($row['nomor_surat']) ?></td>
                                    <td><?= htmlspecialchars($row['pemohon_nama'] ?? '-') ?></td>
                                    <td><?= htmlspecialchars($row['no_reg'] ?: ($row['no_polisi'] ?? '-')) ?></td>
                                    <td class="text-muted">
                                        <?= !empty($row['approval_pimpinan_at']) ? date('d/m/Y H:i', strtotime($row['approval_pimpinan_at'])) : '-' ?>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php else: ?>
                    <div class="text-center py-4 text-muted">
                        <i class="fas fa-file-signature fa-2x mb-2 opacity-25 d-block"></i>
                        <div class="small">Belum ada surat tugas yang disetujui</div>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <!-- Menu Cepat -->
    <div class="col-lg-4">
        <div class="card shadow-sm h-100">
            <div class="card-header bg-primary text-white">
                <h6 class="mb-0"><i class="fas fa-th-large me-2"></i>Menu Cepat</h6>
            </div>
            <div class="card-body">
                <?php
                $menus = [
                    ['page' => 'persetujuan_peminjaman', 'icon' => 'fas fa-clipboard-check', 'color' => '#e74c3c', 'label' => 'Persetujuan',      'desc' => 'Proses permohonan masuk'],
                    ['page' => 'monitoring_peminjaman',  'icon' => 'fas fa-chart-line',      'color' => '#2980b9', 'label' => 'Monitoring',       'desc' => 'Status peminjaman'],
                    ['page' => 'surat_tugas',            'icon' => 'fas fa-file-signature',  'color' => '#27ae60', 'label' => 'Surat Tugas',      'desc' => 'Kelola surat tugas'],
                    ['page' => 'jadwal_perawatan',       'icon' => 'fas fa-tools',           'color' => '#8e44ad', 'label' => 'Perawatan',        'desc' => 'Jadwal servis kendaraan'],
                    ['page' => 'list_kendaraan',         'icon' => 'fas fa-car',             'color' => '#16a085', 'label' => 'Kendaraan',        'desc' => 'Daftar armada'],
                    ['page' => 'profil',                 'icon' => 'fas fa-user-cog',        'color' => '#7f8c8d', 'label' => 'Profil',           'desc' => 'Pengaturan akun'],
                ];
                ?>
                <div class="row g-2">
                <?php foreach ($menus as $m): ?>
                    <div class="col-6">
                        <a href="index.php?page=<?= $m['page'] ?>"
                           class="d-flex flex-column align-items-center text-center p-2 rounded-3 text-decoration-none border h-100"
                           style="transition: all .2s; color: inherit;"
                           onmouseover="this.style.boxShadow='0 4px 16px rgba(0,0,0,.12)';this.style.transform='translateY(-2px)'"
                           onmouseout="this.style.boxShadow='';this.style.transform=''">
                            <div class="rounded-circle d-flex align-items-center justify-content-center mb-1 text-white"
                                 style="width:40px;height:40px;background:<?= $m['color'] ?>;font-size:1rem;">
                                <i class="<?= $m['icon'] ?>"></i>
                            </div>
                            <div class="fw-semibold" style="font-size:.8rem;"><?= $m['label'] ?></div>
                            <div class="text-muted" style="font-size:.7rem;line-height:1.3"><?= $m['desc'] ?></div>
                        </a>
                    </div>
                <?php endforeach; ?>
                </div>
            </div>
        </div>
    </div>
</div>

</div><!-- #dashboard-pimpinan-page -->
