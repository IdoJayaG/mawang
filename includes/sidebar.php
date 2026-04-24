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
        // Normalize role string to lowercase to avoid capitalization mismatch (eg. 'Operator' vs 'operator')
        if (is_string($current_role)) {
            $current_role = strtolower($current_role);
        }
        $is_logged_in = is_logged_in();
        $current_page = $_GET['page'] ?? '';
        ?>
        
        <!-- Menu untuk semua pengunjung (termasuk guest) -->
        <?php if (in_array($current_role, ['user', 'driver'], true)): ?>
            <li><a href="index.php?page=home" class="<?= (!isset($_GET['page']) || $_GET['page'] == 'home') ? 'active' : '' ?>">
                <i class="fas fa-home"></i><span>Beranda</span>
            </a></li>
        <?php else: ?>
            <li><a href="index.php" class="<?= (!isset($_GET['page']) || $_GET['page'] == 'home') ? 'active' : '' ?>">
                <i class="fas fa-home"></i><span>Beranda</span>
            </a></li>
        <?php endif; ?>
        
        <?php if ($current_role === 'guest'): ?>
            <!-- Menu khusus guest -->
            <li><a href="index.php?page=kendaraan_publik" class="<?= ($current_page == 'kendaraan_publik') ? 'active' : '' ?>">
                <i class="fas fa-car"></i><span>Kendaraan Dinas</span>
            </a></li>
            <li><a class="btn-login" href="login.php">
                <i class="fas fa-sign-in-alt"></i><span>Login</span>
            </a></li>
            
        <?php elseif ($current_role === 'driver'): ?>
            <!-- Menu untuk Driver (akses setara operator, data tetap dibatasi kendaraan driver) -->
            <li><a href="index.php?page=dashboard_user" class="<?= ($current_page == 'dashboard_user') ? 'active' : '' ?>">
                <i class="fas fa-tachometer-alt"></i><span>Dashboard</span>
            </a></li>
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
            <li class="has-submenu <?= in_array($current_page, ['dokumen_kendaraan', 'surat_tugas']) ? 'active' : '' ?>">
                <a href="javascript:void(0)" class="submenu-toggle">
                    <i class="fas fa-file-alt"></i><span>Dokumen</span>
                    <i class="fas fa-chevron-down submenu-arrow"></i>
                </a>
                <ul class="submenu">
                    <li><a href="index.php?page=dokumen_kendaraan" class="<?= ($current_page == 'dokumen_kendaraan') ? 'active' : '' ?>">Dokumen Kendaraan</a></li>
                    <li><a href="index.php?page=surat_tugas" class="<?= ($current_page == 'surat_tugas') ? 'active' : '' ?>">Surat Tugas</a></li>
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
            <li><a href="index.php?page=dashboard_user" class="<?= ($current_page == 'dashboard_user') ? 'active' : '' ?>">
                <i class="fas fa-tachometer-alt"></i><span>Dashboard</span>
            </a></li>

            <li class="has-submenu <?= in_array($current_page, ['kendaraan_saya', 'list_kendaraan', 'log_bahan_bakar']) ? 'active' : '' ?>">
                <a href="javascript:void(0)" class="submenu-toggle">
                    <i class="fas fa-car"></i><span>Kendaraan</span>
                    <i class="fas fa-chevron-down submenu-arrow"></i>
                </a>
                <ul class="submenu">
                    <li><a href="index.php?page=list_kendaraan" class="<?= ($current_page == 'list_kendaraan') ? 'active' : '' ?>">List Kendaraan</a></li>
                    <li><a href="index.php?page=kendaraan_saya" class="<?= ($current_page == 'kendaraan_saya') ? 'active' : '' ?>">Kendaraan Saya</a></li>
                    <li><a href="index.php?page=log_bahan_bakar" class="<?= ($current_page == 'log_bahan_bakar') ? 'active' : '' ?>">Log BBM</a></li>
                </ul>
            </li>

            <li><a href="index.php?page=profil" class="<?= ($current_page == 'profil') ? 'active' : '' ?>">
                <i class="fas fa-user"></i><span>Profil Saya</span>
            </a></li>
            <li><a href="index.php?page=surat_tugas" class="<?= ($current_page == 'surat_tugas') ? 'active' : '' ?>">
                <i class="fas fa-file-signature"></i><span>Pengajuan Surat Tugas</span>
            </a></li>
            <li><a class="btn-logout" href="logout.php">
                <i class="fas fa-sign-out-alt"></i><span>Logout</span>
            </a></li>

        <?php elseif ($current_role === 'pimpinan'): ?>
            <!-- Menu untuk Pimpinan -->
            <li><a href="index.php?page=dashboard_pimpinan" class="<?= ($current_page == 'dashboard_pimpinan') ? 'active' : '' ?>">
                <i class="fas fa-tachometer-alt"></i><span>Dashboard</span>
            </a></li>
            <li><a href="index.php?page=persetujuan_peminjaman" class="<?= ($current_page == 'persetujuan_peminjaman') ? 'active' : '' ?>">
                <i class="fas fa-clipboard-check"></i><span>Persetujuan Peminjaman</span>
            </a></li>
            <li><a href="index.php?page=jadwal_perawatan" class="<?= ($current_page == 'jadwal_perawatan') ? 'active' : '' ?>">
                <i class="fas fa-calendar-alt"></i><span>Jadwal Perawatan</span>
            </a></li>
            <li><a href="index.php?page=surat_tugas" class="<?= ($current_page == 'surat_tugas') ? 'active' : '' ?>">
                <i class="fas fa-file-signature"></i><span>Surat Tugas</span>
            </a></li>
            <li><a href="index.php?page=profil" class="<?= ($current_page == 'profil') ? 'active' : '' ?>">
                <i class="fas fa-user"></i><span>Profil</span>
            </a></li>
            <li><a class="btn-logout" href="logout.php">
                <i class="fas fa-sign-out-alt"></i><span>Logout</span>
            </a></li>
            
        <?php elseif ($current_role === 'operator'): ?>
            <!-- Menu untuk Operator -->
            <!-- <li><a href="index.php?page=dashboard_operator" class="<?= ($current_page == 'dashboard_operator') ? 'active' : '' ?>">
                <i class="fas fa-tachometer-alt"></i><span>Dashboard</span>
            </a></li> -->
            
            <!-- Penugasan (hidden for operator) -->
            
            <li class="has-submenu <?= in_array($current_page, ['kendaraan', 'log_bahan_bakar']) ? 'active' : '' ?>">
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
            
            <li class="has-submenu <?= in_array($current_page, ['dokumen_kendaraan', 'surat_tugas']) ? 'active' : '' ?>">
                <a href="javascript:void(0)" class="submenu-toggle">
                    <i class="fas fa-file-alt"></i><span>Dokumen</span>
                    <i class="fas fa-chevron-down submenu-arrow"></i>
                </a>
                <ul class="submenu">
                    <li><a href="index.php?page=dokumen_kendaraan" class="<?= ($current_page == 'dokumen_kendaraan') ? 'active' : '' ?>">Dokumen Kendaraan</a></li>
                    <li><a href="index.php?page=surat_tugas" class="<?= ($current_page == 'surat_tugas') ? 'active' : '' ?>">Surat Tugas</a></li>
                </ul>
            </li>
            
            <li><a href="index.php?page=profil" class="<?= ($current_page == 'profil') ? 'active' : '' ?>">
                <i class="fas fa-user"></i><span>Profil</span>
            </a></li>
            <li><a class="btn-logout" href="logout.php">
                <i class="fas fa-sign-out-alt"></i><span>Logout</span>
            </a></li>
            
        <?php elseif ($current_role === 'admin'): ?>
            <li><a href="index.php?page=dashboard_admin" class="<?= ($current_page == 'dashboard_admin') ? 'active' : '' ?>">
                <i class="fas fa-tachometer-alt"></i><span>Dashboard</span>
            </a></li>

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
             
            <li class="has-submenu <?= in_array($current_page, ['persetujuan_peminjaman', 'monitoring_peminjaman']) ? 'active' : '' ?>">
                <a href="javascript:void(0)" class="submenu-toggle">
                    <i class="fas fa-clipboard-check"></i><span>Peminjaman</span>
                    <i class="fas fa-chevron-down submenu-arrow"></i>
                </a>
                <ul class="submenu">
                    <li><a href="index.php?page=persetujuan_peminjaman" class="<?= ($current_page == 'persetujuan_peminjaman') ? 'active' : '' ?>">Persetujuan Peminjaman</a></li>
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

            <li class="has-submenu <?= in_array($current_page, ['manajemen_user', 'manajemen_pengguna', 'log_aktivitas']) ? 'active' : '' ?>">
                <a href="javascript:void(0)" class="submenu-toggle">
                    <i class="fas fa-users-cog"></i><span>User</span>
                    <i class="fas fa-chevron-down submenu-arrow"></i>
                </a>
                <ul class="submenu">
                    <li><a href="index.php?page=manajemen_user" class="<?= ($current_page == 'manajemen_user') ? 'active' : '' ?>">Kelola User</a></li>
                    <li><a href="index.php?page=log_aktivitas" class="<?= ($current_page == 'log_aktivitas') ? 'active' : '' ?>">Log Aktivitas</a></li>
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
