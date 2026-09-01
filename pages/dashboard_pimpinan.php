<?php
require_once 'includes/auth.php';
require_login();
require_role('pimpinan');

$current_user    = get_logged_in_user();
$current_user_id = get_current_user_id();

$stats = [
    'driver_ready' => 0,
];

$r = $mysqli->query("SELECT COUNT(*) AS cnt FROM user_account ua JOIN role r ON ua.role_id = r.id WHERE ua.status = 'Aktif' AND UPPER(r.kode_role) = 'DRIVER'");
if ($r) { $stats['driver_ready'] = (int)($r->fetch_assoc()['cnt'] ?? 0); }
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
    <div class="col-lg-4 col-md-6 mb-3">
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

<!-- ── Menu Cepat ─────────────────────────────────────────────────────────── -->
<div class="row g-3 mb-4">
    <div class="col-lg-12">
        <div class="card shadow-sm h-100">
            <div class="card-header bg-primary text-white">
                <h6 class="mb-0"><i class="fas fa-th-large me-2"></i>Menu Cepat</h6>
            </div>
            <div class="card-body">
                <?php
                $menus = [
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
