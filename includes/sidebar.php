<aside class="sidebar" id="sidebar" role="navigation" aria-label="Sidebar Navigation">
    <div class="sidebar-header">
        <div class="sidebar-logo">
            <img src="assets/images/logo.png" alt="Logo TNI" class="logo-img" 
                 onerror="this.src='assets/images/logo.svg'; this.onerror=null;">
            <div class="logo-fallback" style="display: none;">
                <i class="fas fa-shield-alt"></i>
            </div>
        </div>
        <div class="sidebar-title">
            <h3>SI-KENDI</h3>
            <span class="subtitle">TNI/PNS</span>
        </div>
        <button class="sidebar-toggle" id="sidebarToggle" aria-label="Toggle Sidebar" aria-expanded="false" aria-controls="sidebar">
            <i class="fas fa-bars" aria-hidden="true"></i>
        </button>
    </div>
    
    <ul class="sidebar-menu">
        <?php 
        $current_role = get_current_role();
        // Normalize role string to lowercase to avoid capitalization mismatch (eg. 'Pimpinan' vs 'pimpinan')
        if (is_string($current_role)) {
            $current_role = strtolower($current_role);
        }
        $is_logged_in = is_logged_in();
        $current_page = $_GET['page'] ?? '';
        $is_admin_like = function_exists('is_admin_like') && is_admin_like();
        if (!$is_admin_like && $current_role === 'pimpinan') {
            $is_admin_like = true;
        }
        ?>
        
        <?php
        // Unified Dashboard link per role — replace legacy 'Beranda' link so sidebar shows role-specific dashboard
        $dashboard_map = [
            'admin' => 'dashboard_admin',
            'pimpinan' => 'dashboard_pimpinan',
            'user' => 'dashboard_user',
            'driver' => 'dashboard_driver',
            'guest' => 'home'
        ];
        $dash_page = $dashboard_map[$current_role] ?? ($is_admin_like ? 'dashboard_admin' : 'dashboard_user');
        ?>
        <li><a href="index.php?page=<?= $dash_page ?>" class="<?= ($current_page == $dash_page) ? 'active' : '' ?>">
            <i class="fas fa-tachometer-alt"></i><span>Dashboard</span>
        </a></li>
        
        <?php if ($current_role === 'guest'): ?>
            <!-- Menu khusus guest -->
            <li><a href="index.php?page=kendaraan_publik" class="<?= ($current_page == 'kendaraan_publik') ? 'active' : '' ?>">
                <i class="fas fa-car"></i><span>Kendaraan Dinas</span>
            </a></li>
            <li><a class="btn-login" href="login.php">
                <i class="fas fa-sign-in-alt"></i><span>Login</span>
            </a></li>
            
        <?php elseif ($current_role === 'driver'): ?>
            <!-- Menu untuk Driver (akses terbatas pada kendaraan penugasan) -->
            <li class="has-submenu <?= in_array($current_page, ['kendaraan', 'log_bahan_bakar', 'map_kendaraan']) ? 'active' : '' ?>">
                <a href="javascript:void(0)" class="submenu-toggle">
                    <i class="fas fa-car"></i><span>Kendaraan</span>
                    <i class="fas fa-chevron-down submenu-arrow"></i>
                </a>
                <ul class="submenu">
                    <li><a href="index.php?page=kendaraan" class="<?= ($current_page == 'kendaraan') ? 'active' : '' ?>">Data Kendaraan</a></li>
                    <li><a href="index.php?page=log_bahan_bakar" class="<?= ($current_page == 'log_bahan_bakar') ? 'active' : '' ?>">Log BBM</a></li>
                    <li><a href="index.php?page=map_kendaraan" class="<?= ($current_page == 'map_kendaraan') ? 'active' : '' ?>">Peta Kendaraan</a></li>
                </ul>
            </li>
            <li class="has-submenu <?= in_array($current_page, ['jadwal_perawatan', 'riwayat_perawatan', 'riwayat_perbaikan']) ? 'active' : '' ?>">
                <a href="javascript:void(0)" class="submenu-toggle">
                    <i class="fas fa-tools"></i><span>Perawatan</span>
                    <i class="fas fa-chevron-down submenu-arrow"></i>
                </a>
                <ul class="submenu">
                    <li><a href="index.php?page=jadwal_perawatan" class="<?= ($current_page == 'jadwal_perawatan') ? 'active' : '' ?>">Jadwal Perawatan</a></li>
                    <li><a href="index.php?page=riwayat_perawatan" class="<?= ($current_page == 'riwayat_perawatan') ? 'active' : '' ?>">Riwayat Perawatan</a></li>
                    <li><a href="index.php?page=riwayat_perbaikan" class="<?= ($current_page == 'riwayat_perbaikan') ? 'active' : '' ?>">Riwayat Perbaikan</a></li>
                </ul>
            </li>
            <li class="has-submenu <?= in_array($current_page, ['dokumen_kendaraan', 'surat_tugas','laporan_perjalanan']) ? 'active' : '' ?>">
                <a href="javascript:void(0)" class="submenu-toggle">
                    <i class="fas fa-file-alt"></i><span>Dokumen</span>
                    <i class="fas fa-chevron-down submenu-arrow"></i>
                </a>
                <ul class="submenu">
                    <li><a href="index.php?page=dokumen_kendaraan" class="<?= ($current_page == 'dokumen_kendaraan') ? 'active' : '' ?>">Dokumen Kendaraan</a></li>
                    <li><a href="index.php?page=surat_tugas" class="<?= ($current_page == 'surat_tugas') ? 'active' : '' ?>">Surat Tugas</a></li>
                    <li><a href="index.php?page=laporan_perjalanan&action=assigned" class="<?= ($current_page == 'laporan_perjalanan' && ($_GET['action'] ?? '') === 'assigned') ? 'active' : '' ?>">Laporan Perjalanan</a></li>
                </ul>
            </li>
            <li><a href="index.php?page=profil" class="<?= ($current_page == 'profil') ? 'active' : '' ?>">
                <i class="fas fa-user"></i><span>Profil</span>
            </a></li>
            <li><a class="btn-logout" href="logout.php">
                <i class="fas fa-sign-out-alt"></i><span>Logout</span>
            </a></li>

        <?php elseif ($current_role === 'user'): ?>
            <!-- Menu untuk User -->

            <li class="has-submenu <?= in_array($current_page, ['list_kendaraan', 'map_kendaraan']) ? 'active' : '' ?>">
                <a href="javascript:void(0)" class="submenu-toggle">
                    <i class="fas fa-car"></i><span>Kendaraan</span>
                    <i class="fas fa-chevron-down submenu-arrow"></i>
                </a>
                <ul class="submenu">
                    <li><a href="index.php?page=list_kendaraan" class="<?= ($current_page == 'list_kendaraan') ? 'active' : '' ?>">List Kendaraan</a></li>
                    <li><a href="index.php?page=form_peminjaman" class="<?= ($current_page == 'form_peminjaman') ? 'active' : '' ?>">Pengajuan Peminjaman</a></li>
                    <li><a href="index.php?page=map_kendaraan" class="<?= ($current_page == 'map_kendaraan') ? 'active' : '' ?>">Peta Kendaraan</a></li>
                </ul>
            </li>

            <li><a href="index.php?page=profil" class="<?= ($current_page == 'profil') ? 'active' : '' ?>">
                <i class="fas fa-user"></i><span>Profil Saya</span>
            </a></li>
            <li><a href="index.php?page=surat_tugas" class="<?= ($current_page == 'surat_tugas') ? 'active' : '' ?>">
                <i class="fas fa-file-signature"></i><span>Pengajuan Surat Tugas</span>
            </a></li>
            <li><a href="index.php?page=riwayat_peminjaman" class="<?= ($current_page == 'riwayat_peminjaman') ? 'active' : '' ?>">
                <i class="fas fa-history"></i><span>Riwayat Peminjaman</span>
            </a></li>
            <li><a class="btn-logout" href="logout.php">
                <i class="fas fa-sign-out-alt"></i><span>Logout</span>
            </a></li>

        <?php elseif ($is_admin_like): ?>

    <li class="has-submenu <?= in_array($current_page, ['kendaraan', 'pengguna_kendaraan']) ? 'active' : (($current_page=='dashboard_admin'||$current_page=='') ? '' : '') ?>">
                <a href="javascript:void(0)" class="submenu-toggle">
                    <i class="fas fa-car"></i><span>Kendaraan</span>
                    <i class="fas fa-chevron-down submenu-arrow"></i>
                </a>
                <ul class="submenu">
            <li><a href="index.php?page=kendaraan" class="<?= ($current_page == 'kendaraan') ? 'active' : '' ?>">Data Kendaraan</a></li>
            <li><a href="index.php?page=pengguna_kendaraan" class="<?= ($current_page == 'pengguna_kendaraan') ? 'active' : '' ?>">Pengguna Kendaraan</a></li>
            <li><a href="index.php?page=map_kendaraan" class="<?= ($current_page == 'map_kendaraan') ? 'active' : '' ?>">Peta Kendaraan</a></li>
                </ul>
            </li>
            

            <!-- peminjaman -->
            <?php
            $peminjaman_pages = $current_role === 'pimpinan'
                ? ['persetujuan_peminjaman', 'monitoring_peminjaman']
                : ['monitoring_peminjaman'];
            ?>
            <li class="has-submenu <?= in_array($current_page, $peminjaman_pages) ? 'active' : '' ?>">
                <a href="javascript:void(0)" class="submenu-toggle">
                    <i class="fas fa-clipboard-check"></i><span>Peminjaman</span>
                    <i class="fas fa-chevron-down submenu-arrow"></i>
                </a>
                <ul class="submenu">
                    <?php if ($current_role === 'pimpinan'): ?>
                    <li><a href="index.php?page=persetujuan_peminjaman" class="<?= ($current_page == 'persetujuan_peminjaman') ? 'active' : '' ?>">Persetujuan Peminjaman</a></li>
                    <?php endif; ?>
                    <li><a href="index.php?page=monitoring_peminjaman" class="<?= ($current_page == 'monitoring_peminjaman') ? 'active' : '' ?>">Monitoring Peminjaman</a></li>
                </ul>
            </li>
            
            <li class="has-submenu <?= in_array($current_page, ['riwayat_pemakaian', 'riwayat_perawatan', 'riwayat_perbaikan']) ? 'active' : '' ?>">
                <a href="javascript:void(0)" class="submenu-toggle">
                    <i class="fas fa-history"></i><span>Riwayat</span>
                    <i class="fas fa-chevron-down submenu-arrow"></i>
                </a>
                <ul class="submenu">
                    <li><a href="index.php?page=riwayat_pemakaian" class="<?= ($current_page == 'riwayat_pemakaian') ? 'active' : '' ?>">Riwayat Pemakaian</a></li>
                    <li><a href="index.php?page=riwayat_perawatan" class="<?= ($current_page == 'riwayat_perawatan') ? 'active' : '' ?>">Riwayat Perawatan</a></li>
                    <li><a href="index.php?page=riwayat_perbaikan" class="<?= ($current_page == 'riwayat_perbaikan') ? 'active' : '' ?>">Riwayat Perbaikan</a></li>
                    <li><a href="index.php?page=log_bahan_bakar" class="<?= ($current_page == 'log_bahan_bakar') ? 'active' : '' ?>">Log BBM</a></li>
                </ul>
            </li>
            
            <li><a href="index.php?page=jadwal_perawatan" class="<?= ($current_page == 'jadwal_perawatan') ? 'active' : '' ?>">
                <i class="fas fa-calendar-alt"></i><span>Jadwal Perawatan</span></a></li>




             <li class="has-submenu <?= in_array($current_page, ['dokumen_kendaraan','laporan_perjalanan','surat_tugas']) ? 'active' : '' ?>">
                <a href="javascript:void(0)" class="submenu-toggle">
                    <i class="fas fa-folder"></i><span>Dokumen</span>
                    <i class="fas fa-chevron-down submenu-arrow"></i>
                </a>
                <ul class="submenu">
                    <li><a href="index.php?page=dokumen_kendaraan" class="<?= ($current_page == 'dokumen_kendaraan') ? 'active' : '' ?>">Dokumen Kendaraan</a></li>
                    <li><a href="index.php?page=laporan_perjalanan" class="<?= ($current_page == 'laporan_perjalanan') ? 'active' : '' ?>">Laporan Perjalanan</a></li>
                    <li><a href="index.php?page=surat_tugas" class="<?= ($current_page == 'surat_tugas') ? 'active' : '' ?>">Surat Tugas</a></li>
                </ul>
            </li>

            <?php $user_pages = $current_role === 'admin'
                ? ['manajemen_user', 'manajemen_pengguna', 'log_aktivitas', 'email_queue']
                : ['manajemen_user', 'manajemen_pengguna', 'log_aktivitas']; ?>
            <li class="has-submenu <?= in_array($current_page, $user_pages) ? 'active' : '' ?>">
                <a href="javascript:void(0)" class="submenu-toggle">
                    <i class="fas fa-users-cog"></i><span>User</span>
                    <i class="fas fa-chevron-down submenu-arrow"></i>
                </a>
                <ul class="submenu">
                    <li><a href="index.php?page=manajemen_user" class="<?= ($current_page == 'manajemen_user') ? 'active' : '' ?>">Kelola User</a></li>
                    <li><a href="index.php?page=log_aktivitas" class="<?= ($current_page == 'log_aktivitas') ? 'active' : '' ?>">Log Aktivitas</a></li>
                    <?php if ($current_role === 'admin'): ?>
                    <li><a href="index.php?page=email_queue" class="<?= ($current_page == 'email_queue') ? 'active' : '' ?>">Email Queue</a></li>
                    <?php endif; ?>
                </ul>
            </li>
            
            <li><a href="index.php?page=profil" class="<?= ($current_page == 'profil') ? 'active' : '' ?>">
                <i class="fas fa-user"></i><span>Profil</span>
            </a></li>
            <li><a class="btn-logout" href="logout.php">
                <i class="fas fa-sign-out-alt"></i><span>Logout</span>
            </a></li>
        <?php endif; ?>
    </ul>
</aside>

<!-- Sidebar script is now loaded from assets/js/sidebar.js -->
<!-- Overlay for mobile -->
<div class="sidebar-overlay" id="sidebarOverlay" aria-hidden="true"></div>
