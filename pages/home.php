<?php
// Landing page untuk guest dan beranda untuk user
$current_role = get_current_role();
$is_logged_in = is_logged_in();
// Lightweight marker to prevent showing the same reminder repeatedly on reload
if (isset($_GET['mark_upcoming_shown']) && $_GET['mark_upcoming_shown'] == '1' && $is_logged_in) {
    $_SESSION['shown_upcoming_perawatan'] = date('Y-m-d');
    // For fetch(), avoid full HTML output
    if (!empty($_SERVER['HTTP_X_REQUESTED_WITH']) || (isset($_SERVER['HTTP_ACCEPT']) && strpos($_SERVER['HTTP_ACCEPT'], 'application/json') !== false)) {
        header('Content-Type: application/json');
        echo json_encode(['ok' => true]);
        exit;
    }
}
?>


<div class="modern-landing">
    <!-- Hero Section -->
    <div class="hero-section">
        <div class="hero-content">
            <div class="hero-logo">
                <img src="assets/images/logo.png" alt="Logo TNI" 
                     onerror="this.src='assets/images/logo.svg'; this.onerror=null;">
                <i class="fas fa-shield-alt" style="display: none;"></i>
            </div>
            <h1 class="hero-title">SI-KENDI</h1>
            <p class="hero-subtitle">Sistem Informasi Kendaraan Dinas TNI/PNS</p>
            <p class="hero-description">
                Platform digital terintegrasi untuk mengelola kendaraan dinas, perawatan, 
                dokumentasi, dan operasional kendaraan TNI/PNS secara efisien dan transparan.
            </p>
            
            <div class="hero-actions">
                <?php if (!$is_logged_in): ?>
                    <a href="login.php" class="btn-hero btn-hero-primary">
                        <i class="fas fa-sign-in-alt"></i>
                        Login Sistem
                    </a>
                    <a href="index.php?page=kendaraan_publik" class="btn-hero btn-hero-outline">
                        <i class="fas fa-eye"></i>
                        Lihat Kendaraan
                    </a>
                <?php else: ?>
                    <a href="index.php?page=dashboard_<?= $current_role ?>" class="btn-hero btn-hero-primary">
                        <i class="fas fa-tachometer-alt"></i>
                        Dashboard
                    </a>
                    <a href="index.php?page=profil" class="btn-hero btn-hero-outline">
                        <i class="fas fa-user"></i>
                        Profil Saya
                    </a>
                <?php endif; ?>
            </div>

            <div class="stats-preview">
                <?php
                // Get basic statistics for preview
                $total_kendaraan = 0;
                $kendaraan_operasional = 0;
                
                // Use schema-aware counts
                $cols = $mysqli->query("SHOW COLUMNS FROM kendaraan")->fetch_all(MYSQLI_ASSOC);
                $col_names = array_column($cols, 'Field');

                if (in_array('id', $col_names)) {
                    $res = $mysqli->query("SELECT COUNT(*) as cnt FROM kendaraan");
                    $total_kendaraan = (int)$res->fetch_assoc()['cnt'];
                } else {
                    $total_kendaraan = 0;
                }

                if (in_array('status_kendaraan', $col_names)) {
                    $res = $mysqli->query("SELECT COUNT(*) as cnt FROM kendaraan WHERE status_kendaraan = 'Operasional'");
                    $kendaraan_operasional = (int)$res->fetch_assoc()['cnt'];
                } elseif (in_array('status_peminjaman', $col_names)) {
                    $res = $mysqli->query("SELECT COUNT(*) as cnt FROM kendaraan WHERE status_peminjaman = 'Tersedia'");
                    $kendaraan_operasional = (int)$res->fetch_assoc()['cnt'];
                } else {
                    $kendaraan_operasional = 0;
                }
                ?>
                
                <div class="stat-preview">
                    <span class="number"><?= $total_kendaraan ?></span>
                    <span class="label">Kendaraan</span>
                </div>
                <div class="stat-preview">
                    <span class="number"><?= $kendaraan_operasional ?></span>
                    <span class="label">Operasional</span>
                </div>
                <div class="stat-preview">
                    <span class="number">24/7</span>
                    <span class="label">Monitoring</span>
                </div>
            </div>
        </div>
    </div>

    <!-- Features Section -->
    <div class="features-section">
        <div class="container">
            <div class="section-header">
                <h2 class="section-title">Fitur Unggulan</h2>
                <p class="section-subtitle">
                    Kelola kendaraan dinas dengan mudah dan efisien menggunakan teknologi terdepan
                </p>
            </div>
            
            <div class="features-grid">
                <div class="feature-card">
                    <div class="feature-icon">
                        <i class="fas fa-car"></i>
                    </div>
                    <h3>Manajemen Kendaraan</h3>
                    <p>Kelola data kendaraan dinas, status operasional, dan assignment pengguna secara real-time dengan antarmuka yang intuitif.</p>
                </div>
                
                <div class="feature-card">
                    <div class="feature-icon">
                        <i class="fas fa-tools"></i>
                    </div>
                    <h3>Jadwal Perawatan</h3>
                    <p>Sistem penjadwalan perawatan otomatis berdasarkan kilometer dan waktu untuk menjaga kondisi kendaraan tetap optimal.</p>
                </div>
                
                <div class="feature-card">
                    <div class="feature-icon">
                        <i class="fas fa-gas-pump"></i>
                    </div>
                    <h3>Log Bahan Bakar</h3>
                    <p>Pencatatan konsumsi BBM lengkap dengan dokumentasi foto dan analisis efisiensi kendaraan untuk transparansi.</p>
                </div>
                
                <div class="feature-card">
                    <div class="feature-icon">
                        <i class="fas fa-file-alt"></i>
                    </div>
                    <h3>Dokumentasi Digital</h3>
                    <p>Penyimpanan dokumen kendaraan dalam format digital yang aman dan mudah diakses.</p>
                </div>
            </div>
        </div>
    </div>

    <!-- Statistics Section -->
    <div class="stats-section">
        <div class="container">
            <div class="section-header">
                <h2 class="section-title">Statistik Sistem</h2>
                <p class="section-subtitle">Data real-time kendaraan dinas yang dikelola dalam sistem</p>
            </div>
            
            <div class="stats-grid">
                <?php
                // Get detailed statistics
                $kondisi_baik = 0;
                if (in_array('kondisi', $col_names)) {
                    $res = $mysqli->query("SELECT COUNT(*) as cnt FROM kendaraan WHERE kondisi = 'Baik'");
                    $kondisi_baik = (int)$res->fetch_assoc()['cnt'];
                }
                ?>
                
                <div class="stat-card">
                    <span class="stat-number"><?= $total_kendaraan ?></span>
                    <span class="stat-label">Total Kendaraan</span>
                </div>
                
                <div class="stat-card">
                    <span class="stat-number"><?= $kendaraan_operasional ?></span>
                    <span class="stat-label">Kendaraan Operasional</span>
                </div>
                
                <div class="stat-card">
                    <span class="stat-number"><?= $kondisi_baik ?></span>
                    <span class="stat-label">Kondisi Baik</span>
                </div>
            </div>
        </div>
    </div>

    <!-- Call to Action Section -->
    <?php if (!$is_logged_in): ?>
    <div class="cta-section">
        <div class="cta-content">
            <h2 class="cta-title">Siap Memulai?</h2>
            <p class="cta-description">
                Bergabung dengan sistem manajemen kendaraan dinas yang modern dan efisien. 
                Login sekarang untuk mengakses semua fitur.
            </p>
            <a href="login.php" class="btn-hero btn-hero-primary">
                <i class="fas fa-rocket"></i>
                Mulai Sekarang
            </a>
        </div>
    </div>
    <?php endif; ?>
</div>

<?php
// SweetAlert: Upcoming maintenance reminder on home/beranda
// Show to logged-in users when there are schedules within next N days.
if ($is_logged_in) {
    $thresholdDays = 3; // consider "sudah dekat" as within 3 days
    $showKey = 'shown_upcoming_perawatan';
    $todayKey = date('Y-m-d');

    // Only show once per day
    $alreadyShownToday = isset($_SESSION[$showKey]) && $_SESSION[$showKey] === $todayKey;

    // Check table existence defensively
    $hasJadwal = $mysqli && $mysqli->query("SHOW TABLES LIKE 'jadwal_perawatan'");
    $hasJadwal = $hasJadwal && $hasJadwal->num_rows > 0;

    $upcoming = [];
    $totalUpcoming = 0;
    if (!$alreadyShownToday && $hasJadwal) {
        // Count upcoming 'Terjadwal' between today and +threshold days
        $countSql = "SELECT COUNT(*) AS cnt FROM jadwal_perawatan j
                     WHERE DATE(j.tanggal_perawatan) BETWEEN CURDATE() AND DATE_ADD(CURDATE(), INTERVAL ? DAY)
                     AND LOWER(j.status) IN ('terjadwal','dalam proses')";
        if ($stmtC = $mysqli->prepare($countSql)) {
            $stmtC->bind_param('i', $thresholdDays);
            $stmtC->execute();
            $resC = $stmtC->get_result();
            $rowC = $resC ? $resC->fetch_assoc() : null;
            $totalUpcoming = (int)($rowC['cnt'] ?? 0);
            $stmtC->close();
        }

        if ($totalUpcoming > 0) {
            $listSql = "SELECT j.id, j.tanggal_perawatan, j.jenis_perawatan, k.no_polisi, k.no_reg, k.merk, k.tipe
                        FROM jadwal_perawatan j
                        LEFT JOIN kendaraan k ON j.kendaraan_id = k.id
                        WHERE DATE(j.tanggal_perawatan) BETWEEN CURDATE() AND DATE_ADD(CURDATE(), INTERVAL ? DAY)
                        AND LOWER(j.status) IN ('terjadwal','dalam proses')
                        ORDER BY j.tanggal_perawatan ASC
                        LIMIT 5";
            if ($stmtL = $mysqli->prepare($listSql)) {
                $stmtL->bind_param('i', $thresholdDays);
                $stmtL->execute();
                $resL = $stmtL->get_result();
                while ($r = $resL->fetch_assoc()) { $upcoming[] = $r; }
                $stmtL->close();
            }
        }
    }
}
?>

<?php if (!empty($upcoming) && $totalUpcoming > 0): ?>
<script>
document.addEventListener('DOMContentLoaded', function() {
    try {
        var items = <?php echo json_encode(array_map(function($r){
            return [
                'tgl' => !empty($r['tanggal_perawatan']) ? date('d/m/Y', strtotime($r['tanggal_perawatan'])) : '-',
                'kend' => trim(($r['no_polisi'] ?? '')),
                'no_reg' => trim(($r['no_reg'] ?? '')),
                'merk' => trim(($r['merk'] ?? '')),
                'tipe' => trim(($r['tipe'] ?? '')),
                'jenis' => trim(($r['jenis_perawatan'] ?? '')),
            ];
        }, $upcoming), JSON_UNESCAPED_UNICODE); ?>;

        var listHtml = '<ul style="text-align:left; margin:0; padding-left:18px;">' +
            items.map(function(it){
                var label = (it.kend || it.no_reg) ? (it.kend ? it.kend : ('No.Reg ' + it.no_reg)) : (it.merk || '') ;
                var model = (it.merk && it.tipe) ? (' - ' + it.merk + ' ' + it.tipe) : '';
                return '<li><strong>' + it.tgl + '</strong> &middot; ' + (label || '-') + model + '<br><small>' + (it.jenis || '-') + '</small></li>';
            }).join('') +
            '</ul>';

        Swal.fire({
            icon: 'warning',
            title: 'Jadwal Perawatan Sudah Dekat',
            html: listHtml + (<?php echo (int)$totalUpcoming; ?> > items.length ? '<div style="margin-top:8px;">Dan ' + (<?php echo (int)$totalUpcoming; ?> - items.length) + ' lainnya…</div>' : ''),
            confirmButtonText: 'Lihat Jadwal',
            cancelButtonText: 'Nanti',
            showCancelButton: true,
            confirmButtonColor: '#3085d6',
            cancelButtonColor: '#6c757d'
        }).then(function(result){
            // Mark as shown for today regardless of choice
            try { fetch('index.php?page=home&mark_upcoming_shown=1', {credentials:'same-origin'}); } catch(e) {}
            if (result.isConfirmed) {
                window.location.href = 'index.php?page=jadwal_perawatan';
            }
        });
    } catch (e) { /* noop */ }
});
</script>
<?php $_SESSION['shown_upcoming_perawatan'] = date('Y-m-d'); endif; ?>

<script>
// Ensure sidebar functionality works on homepage
document.addEventListener('DOMContentLoaded', function() {
    // Reinitialized sidebar submenu functionality in case it's overridden
    const submenuToggles = document.querySelectorAll('.submenu-toggle');
    
    submenuToggles.forEach(toggle => {
        // Remove any existing listeners
        toggle.replaceWith(toggle.cloneNode(true));
    });
    
    // Re-add listeners
    document.querySelectorAll('.submenu-toggle').forEach(toggle => {
        toggle.addEventListener('click', function(e) {
            e.preventDefault();
            e.stopPropagation();
            
            const parentLi = this.closest('li.has-submenu');
            const submenu = parentLi.querySelector('.submenu');
            const arrow = this.querySelector('.submenu-arrow');
            
            if (!parentLi || !submenu) return;
            
            // Close all other submenus first
            document.querySelectorAll('.submenu-toggle').forEach(otherToggle => {
                if (otherToggle !== this) {
                    const otherParent = otherToggle.closest('li.has-submenu');
                    const otherSubmenu = otherParent?.querySelector('.submenu');
                    const otherArrow = otherToggle.querySelector('.submenu-arrow');
                    
                    if (otherParent && otherSubmenu) {
                        otherParent.classList.remove('open');
                        otherSubmenu.style.maxHeight = '0';
                    }
                    if (otherArrow) {
                        otherArrow.style.transform = 'rotate(0deg)';
                    }
                }
            });
            
            // Toggle current submenu
            if (parentLi.classList.contains('open')) {
                parentLi.classList.remove('open');
                submenu.style.maxHeight = '0';
                if (arrow) arrow.style.transform = 'rotate(0deg)';
            } else {
                parentLi.classList.add('open');
                submenu.style.maxHeight = submenu.scrollHeight + 'px';
                if (arrow) arrow.style.transform = 'rotate(180deg)';
            }
        });
    });
    
    // Auto-open active submenus
    const activeSubmenus = document.querySelectorAll('.has-submenu.active');
    activeSubmenus.forEach(parentLi => {
        const submenu = parentLi.querySelector('.submenu');
        const arrow = parentLi.querySelector('.submenu-arrow');
        
        parentLi.classList.add('open');
        if (submenu) {
            submenu.style.maxHeight = submenu.scrollHeight + 'px';
        }
        if (arrow) {
            arrow.style.transform = 'rotate(180deg)';
        }
    });
    
    // Also check for active submenu items
    const activeSubmenuItems = document.querySelectorAll('.submenu a.active');
    activeSubmenuItems.forEach(activeItem => {
        const parentSubmenu = activeItem.closest('.has-submenu');
        if (parentSubmenu && !parentSubmenu.classList.contains('open')) {
            const submenu = parentSubmenu.querySelector('.submenu');
            const arrow = parentSubmenu.querySelector('.submenu-arrow');
            
            parentSubmenu.classList.add('open');
            if (submenu) {
                submenu.style.maxHeight = submenu.scrollHeight + 'px';
            }
            if (arrow) {
                arrow.style.transform = 'rotate(180deg)';
            }
        }
    });
});
</script>


