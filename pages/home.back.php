<?php
// Landing page untuk guest dan beranda untuk user
$current_role = get_current_role();
$is_logged_in = is_logged_in();
?>

<style>
/* Modern landing page styles */
.modern-landing {
    margin: -2rem;
    padding: 0;
    background: linear-gradient(135deg, #DC143C 0%, #B22222 50%, #8B0000 100%);
    min-height: 100vh;
    color: white;
    position: relative;
}

.modern-landing::before {
    content: '';
    position: absolute;
    top: 0;
    left: 0;
    right: 0;
    bottom: 0;
    background-image: 
        radial-gradient(circle at 20% 80%, rgba(255, 215, 0, 0.1) 0%, transparent 50%),
        radial-gradient(circle at 80% 20%, rgba(255, 215, 0, 0.1) 0%, transparent 50%);
    pointer-events: none;
}

.hero-section {
    padding: 4rem 2rem;
    text-align: center;
    min-height: 70vh;
    display: flex;
    align-items: center;
    justify-content: center;
}

.hero-content {
    max-width: 800px;
    margin: 0 auto;
}

.hero-logo {
    width: 120px;
    height: 120px;
    background: linear-gradient(135deg, #FFD700, #FFA500);
    border-radius: 50%;
    display: flex;
    align-items: center;
    justify-content: center;
    margin: 0 auto 2rem;
    backdrop-filter: blur(10px);
    border: 3px solid rgba(255, 255, 255, 0.2);
    position: relative;
}

.hero-logo img {
    width: 90px;
    height: 90px;
    border-radius: 50%;
    object-fit: cover;
    border: 2px solid rgba(255, 255, 255, 0.3);
}

.hero-logo i {
    font-size: 4rem;
    color: #DC143C;
    text-shadow: 0 2px 4px rgba(0, 0, 0, 0.3);
}

.hero-title {
    font-size: 4rem;
    font-weight: 800;
    margin-bottom: 1rem;
    letter-spacing: -2px;
    text-shadow: 0 2px 4px rgba(0, 0, 0, 0.1);
}

.hero-subtitle {
    font-size: 1.5rem;
    margin-bottom: 1.5rem;
    opacity: 0.9;
    font-weight: 500;
}

.hero-description {
    font-size: 1.1rem;
    line-height: 1.6;
    margin-bottom: 3rem;
    opacity: 0.8;
    max-width: 600px;
    margin-left: auto;
    margin-right: auto;
}

.hero-actions {
    display: flex;
    justify-content: center;
    gap: 1.5rem;
    flex-wrap: wrap;
    margin-bottom: 3rem;
}

.btn-hero {
    padding: 1.2rem 2.5rem;
    font-size: 1.1rem;
    font-weight: 600;
    border-radius: 50px;
    text-decoration: none;
    transition: all 0.3s ease;
    display: inline-flex;
    align-items: center;
    gap: 0.5rem;
    min-width: 200px;
    justify-content: center;
    box-shadow: 0 4px 15px rgba(0, 0, 0, 0.1);
}

.btn-hero-primary {
    background: linear-gradient(135deg, #FFD700, #FFA500);
    color: #DC143C;
    font-weight: 700;
}

.btn-hero-primary:hover {
    transform: translateY(-3px);
    box-shadow: 0 8px 25px rgba(255, 215, 0, 0.4);
    color: #8B0000;
}

.btn-hero-outline {
    background: transparent;
    border: 2px solid #FFD700;
    color: #FFD700;
}

.btn-hero-outline:hover {
    background: #FFD700;
    color: #DC143C;
    transform: translateY(-3px);
}

.stats-preview {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(120px, 1fr));
    gap: 2rem;
    max-width: 600px;
    margin: 0 auto;
}

.stat-preview {
    text-align: center;
}

.stat-preview .number {
    font-size: 2.5rem;
    font-weight: 700;
    display: block;
    margin-bottom: 0.5rem;
}

.stat-preview .label {
    font-size: 0.9rem;
    opacity: 0.8;
    text-transform: uppercase;
    letter-spacing: 1px;
}

.features-section {
    background: white;
    color: #495057;
    padding: 5rem 2rem;
}

.container {
    max-width: 1200px;
    margin: 0 auto;
}

.section-header {
    text-align: center;
    margin-bottom: 4rem;
}

.section-title {
    font-size: 2.5rem;
    font-weight: 700;
    margin-bottom: 1rem;
    color: #2c3e50;
}

.section-subtitle {
    font-size: 1.2rem;
    color: #6c757d;
    max-width: 600px;
    margin: 0 auto;
}

.features-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(350px, 1fr));
    gap: 2.5rem;
}

.feature-card {
    background: white;
    padding: 2.5rem;
    border-radius: 20px;
    box-shadow: 0 10px 30px rgba(0, 0, 0, 0.1);
    text-align: center;
    transition: all 0.3s ease;
    border: 1px solid #f1f3f4;
}

.feature-card:hover {
    transform: translateY(-10px);
    box-shadow: 0 20px 40px rgba(0, 0, 0, 0.15);
}

.feature-icon {
    width: 80px;
    height: 80px;
    background: linear-gradient(135deg, #DC143C, #B22222);
    border-radius: 50%;
    display: flex;
    align-items: center;
    justify-content: center;
    margin: 0 auto 1.5rem;
    box-shadow: 0 8px 20px rgba(220, 20, 60, 0.3);
}

.feature-icon i {
    font-size: 2rem;
    color: #FFD700;
}

.feature-card h3 {
    font-size: 1.5rem;
    font-weight: 700;
    margin-bottom: 1rem;
    color: #2c3e50;
}

.feature-card p {
    line-height: 1.6;
    color: #6c757d;
    font-size: 1rem;
}

.stats-section {
    background: #f8f9fa;
    padding: 4rem 2rem;
}

.stats-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
    gap: 2rem;
    max-width: 1000px;
    margin: 0 auto;
}

.stat-card {
    background: white;
    padding: 2.5rem;
    border-radius: 20px;
    text-align: center;
    box-shadow: 0 10px 30px rgba(0, 0, 0, 0.1);
    transition: transform 0.3s ease;
}

.stat-card:hover {
    transform: translateY(-5px);
}

.stat-number {
    font-size: 3rem;
    font-weight: 800;
    color: #DC143C;
    margin-bottom: 0.5rem;
    display: block;
}

.stat-label {
    font-size: 1.1rem;
    color: #6c757d;
    font-weight: 500;
}

.cta-section {
    background: linear-gradient(135deg, #2c3e50, #34495e);
    color: white;
    padding: 4rem 2rem;
    text-align: center;
}

.cta-content {
    max-width: 600px;
    margin: 0 auto;
}

.cta-title {
    font-size: 2.5rem;
    font-weight: 700;
    margin-bottom: 1rem;
}

.cta-description {
    font-size: 1.2rem;
    margin-bottom: 2.5rem;
    opacity: 0.9;
}

@media (max-width: 768px) {
    .modern-landing {
        margin: -1rem;
    }
    
    .hero-section {
        padding: 3rem 1rem;
        min-height: 60vh;
    }
    
    .hero-title {
        font-size: 2.5rem;
        letter-spacing: -1px;
    }
    
    .hero-subtitle {
        font-size: 1.2rem;
    }
    
    .hero-actions {
        flex-direction: column;
        align-items: center;
    }
    
    .btn-hero {
        width: 100%;
        max-width: 300px;
    }
    
    .features-grid {
        grid-template-columns: 1fr;
        gap: 2rem;
    }
    
    .feature-card {
        padding: 2rem;
    }
    
    .stats-preview {
        grid-template-columns: repeat(2, 1fr);
        gap: 1.5rem;
    }
    
    .stats-grid {
        grid-template-columns: repeat(2, 1fr);
        gap: 1.5rem;
    }
    
    .section-title {
        font-size: 2rem;
    }
    
    .cta-title {
        font-size: 2rem;
    }
}

@media (max-width: 480px) {
    .hero-title {
        font-size: 2rem;
    }
    
    .stats-preview {
        grid-template-columns: 1fr;
    }
    
    .stats-grid {
        grid-template-columns: 1fr;
    }
}
</style>

<div class="modern-landing">
    <!-- Hero Section -->
    <div class="hero-section">
        <div class="hero-content">
            <div class="hero-logo">
                <img src="assets/images/logo.png" alt="Logo TNI" 
                     onerror="this.src='assets/images/logo.svg'; this.onerror=null;">
                <i class="fas fa-shield-alt" style="display: none;"></i>
            </div>
            <h1 class="hero-title">RANDIS</h1>
            <p class="hero-subtitle">Sistem Manajemen Kendaraan Dinas TNI/PNS</p>
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
                    <p>Penyimpanan dokumen kendaraan (STNK, BPKB, Asuransi) dalam format digital yang aman dan mudah diakses.</p>
                </div>
                
                <div class="feature-card">
                    <div class="feature-icon">
                        <i class="fas fa-chart-line"></i>
                    </div>
                    <h3>Reporting & Analytics</h3>
                    <p>Dashboard analitik dan laporan komprehensif untuk monitoring kinerja dan biaya operasional kendaraan.</p>
                </div>
                
                <div class="feature-card">
                    <div class="feature-icon">
                        <i class="fas fa-shield-alt"></i>
                    </div>
                    <h3>Keamanan Berlapis</h3>
                    <p>Sistem keamanan multi-level dengan role-based access control sesuai hierarki TNI/PNS yang berlaku.</p>
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


