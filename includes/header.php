<?php
// check_login(); // Fungsi ini tidak ada, jadi di-nonaktifkan agar tidak error

// Helper: render vehicle label with No. Reg when present
function vehicle_label($v) {
    $no_pol = isset($v['no_polisi']) ? htmlspecialchars($v['no_polisi'], ENT_QUOTES) : '';
    $no_reg = isset($v['no_reg']) ? htmlspecialchars($v['no_reg'], ENT_QUOTES) : '';
    $merk = isset($v['merk']) ? htmlspecialchars(trim($v['merk']), ENT_QUOTES) : '';
    $tipe = isset($v['tipe']) ? htmlspecialchars(trim($v['tipe']), ENT_QUOTES) : '';
    $label = $no_pol;
    if ($no_reg !== '') {
        $label .= ' &middot; <small class="text-muted">No.Reg: ' . $no_reg . '</small>';
    }
    $model = trim(($merk . ' ' . $tipe));
    if ($model) {
        $label .= ' - ' . $model;
    }
    return $label;
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>SI-KENDI - Sistem Manajemen Kendaraan Dinas</title>
    
    <!-- CSS Files -->
    <link rel="stylesheet" href="assets/css/style.css">
    <link rel="stylesheet" href="assets/css/sidebar.css">
    <link rel="stylesheet" href="assets/css/bootstrap-custom.css">
    
    <!-- Bootstrap CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    
    <!-- Font Awesome -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    
    <!-- jQuery -->
    <script src="https://code.jquery.com/jquery-3.7.1.min.js"></script>
    
    <!-- Bootstrap JS -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    
    <!-- SweetAlert2 -->
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    
    <!-- Sidebar JavaScript -->
    <script src="assets/js/sidebar.js"></script>
    
    <!-- Leaflet (map) -->
    <link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css" crossorigin="" />
    <script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js" crossorigin=""></script>
    
    <!-- CSRF Token -->
    <meta name="csrf-token" content="<?= generate_csrf_token() ?>">
</head>
<body>
    <!-- Mobile Menu Toggle (restored for explicit mobile access) -->
    <button class="mobile-menu-toggle" id="mobileMenuToggle" aria-label="Buka menu" aria-controls="sidebar" aria-expanded="false">
        <i class="fas fa-bars"></i>
    </button>

    <!-- Sidebar overlay -->
    <div class="sidebar-overlay" id="sidebarOverlay"></div>

    <!-- Modern Header -->
    <header class="modern-header">
        <div class="header-content">
            <a href="index.php" class="header-brand" aria-label="Beranda SI-KENDI">
                <div class="brand-logo">
                    <img src="assets/images/logo.png" alt="Logo TNI" 
                         onerror="this.src='assets/images/logo.svg'; this.onerror=null;">
                    <i class="fas fa-shield-alt" style="display: none;"></i>
                </div>
                <div class="brand-text">
                    <h1>SI-KENDI</h1>
                    <span>Sistem Informasi Kendaraan Dinas</span>
                </div>
            </a>
            
            <?php if (is_logged_in()): ?>
            <div class="header-user">
                <div class="user-info">
                    <span class="user-name"><?= htmlspecialchars(isset($_SESSION['username']) ? $_SESSION['username'] : '') ?></span>
                    <span class="user-role"><?= htmlspecialchars(isset($_SESSION['role_name']) ? $_SESSION['role_name'] : (isset($_SESSION['role']) ? $_SESSION['role'] : '')) ?></span>
                </div>
                <div class="user-avatar">
                    <i class="fas fa-user-circle"></i>
                </div>
                <a href="logout.php" class="logout-btn" title="Logout">
                    <i class="fas fa-sign-out-alt"></i>
                </a>
            </div>
            <?php else: ?>
            <div class="header-actions">
                <a href="login.php" class="login-btn" title="Login">
                    <i class="fas fa-sign-in-alt"></i>
                    <span class="d-none d-md-inline ms-1">Login</span>
                </a>
            </div>
            <?php endif; ?>
        </div>
    </header>

    <style>
    :root { --sidebar-width: 260px; }
    .mobile-menu-toggle {
        position: fixed;
        top: 0.75rem;
        left: 0.75rem;
        width: 40px;
        height: 40px;
        display: none;
        align-items: center;
        justify-content: center;
        border-radius: 8px;
        background: rgba(0, 0, 0, 0.15);
        color: #ffffff;
        border: 1px solid rgba(255, 255, 255, 0.3);
        z-index: 1101;
        backdrop-filter: blur(4px);
    }

    .modern-header {
        background: linear-gradient(135deg, #ff0000a7 0%, #ce1313cb 100%);
        color: white;
        padding: 1rem 2rem;
        box-shadow: 0 2px 20px rgba(220, 20, 60, 0.3);
        position: sticky;
        top: 0;
        z-index: 1000;
        margin: 0;
        border-bottom: 7px solid #FFD700;
    }

    .header-content {
        display: flex;
        justify-content: space-between;
        align-items: center;
        max-width: 1150px;
        margin: 0 auto;
        position: relative;
    }

    .header-brand {
        display: flex;
        align-items: center;
        gap: 1rem;
    color: #fff;
    text-decoration: none;
    }

    .brand-logo {
            width: 70px;
            height: 70px;
            margin: 0 auto;
            background: linear-gradient(135deg, #faf3f4ff, #f1e7beff);
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            box-shadow: 
                0 10px 30px rgba(220, 20, 60, 0.3),
                inset 0 0 0 3px rgba(255, 215, 0, 0.3);
            position: relative;
    }

    .brand-logo img {
        width: 60px;
        height: 60px;
        border-radius: 50%;
        object-fit: cover;
    }

    .brand-logo i {
        font-size: 1.5rem;
        color: #DC143C;
    }

    .brand-text h1 {
        margin: 0;
        font-size: 1.5rem;
        font-weight: 700;
        letter-spacing: 1px;
        text-shadow: 0 2px 4px rgba(0, 0, 0, 0.3);
    }

    .brand-text span {
        font-size: 0.8rem;
        opacity: 0.9;
        display: block;
        margin-top: -2px;
        color: #FFD700;
        font-weight: 500;
    }

    .header-brand:hover { opacity: 0.95; }

    .header-user {
        display: flex;
        align-items: center;
        gap: 1rem;
    }

    .user-info {
        text-align: right;
        display: flex;
        flex-direction: column;
    }

    .user-name {
        font-weight: 600;
        font-size: 0.9rem;
        color: #FFD700;
    }

    .user-role {
        font-size: 0.7rem;
        opacity: 0.8;
        text-transform: uppercase;
        letter-spacing: 0.5px;
        color: white;
    }

    .user-avatar {
        font-size: 2rem;
        opacity: 0.9;
        color: #FFD700;
    }

    .logout-btn {
        color: white;
        text-decoration: none;
        padding: 0.5rem;
        border-radius: 8px;
        transition: all 0.3s ease;
        background: rgba(255, 215, 0, 0.2);
        border: 1px solid rgba(255, 215, 0, 0.3);
    }

    .logout-btn:hover {
        background: rgba(255, 215, 0, 0.3);
        color: white;
        transform: translateY(-1px);
        box-shadow: 0 4px 12px rgba(255, 215, 0, 0.3);
    }

    .header-actions .login-btn {
        color: white;
        text-decoration: none;
        padding: 0.5rem;
        border-radius: 8px;
        transition: all 0.3s ease;
        background: rgba(255, 255, 255, 0.12);
        border: 1px solid rgba(255, 255, 255, 0.25);
    }

    .header-actions .login-btn:hover {
        background: rgba(255, 255, 255, 0.2);
        color: white;
        transform: translateY(-1px);
        box-shadow: 0 4px 12px rgba(255, 255, 255, 0.2);
    }

    @media (max-width: 768px) {
        .modern-header {
            /* leave extra left space for fixed mobile toggle */
            padding: 0.75rem 0.75rem 0.75rem 3.25rem;
        }
        
        .header-brand {
            gap: 0.5rem;
        }
        
        .brand-text h1 {
            font-size: 1.2rem;
        }
        
        .brand-text span {
            font-size: 0.7rem;
        }
        
        .user-info {
            display: none;
        }
        
        .header-user {
            gap: 0.4rem;
        }
        .user-avatar { font-size: 1.6rem; }
        .logout-btn, .header-actions .login-btn { padding: 0.4rem; }
    }

    @media (max-width: 576px) {
        .brand-logo { width: 40px; height: 40px; }
        .brand-logo img { width: 32px; height: 32px; }
        .brand-text h1 { font-size: 1rem; }
        .brand-text span { display: none; }
    }

    @media (max-width: 768px) {
        .mobile-menu-toggle { display: inline-flex; }
    }

    /* Override default header styles */
    header.modern-header {
        background: linear-gradient(135deg, #154a6b 0%, #5b8aa8 100%) !important;
        text-align: left !important;
        letter-spacing: normal !important;
        font-size: inherit !important;
        box-shadow: 0 2px 20px rgba(220, 20, 60, 0.3) !important;
        border-bottom: 5px solid #FFD700 !important;
    }

    /* Ensure header doesn't sit underneath the fixed sidebar on desktop */
    @media (min-width: 992px) {
        .modern-header {
            margin-left: var(--sidebar-width);
            width: calc(100% - var(--sidebar-width));
        }
        body.sidebar-collapsed .modern-header {
            margin-left: 70px;
            width: calc(100% - 70px);
        }
    }
    /* Tablet width: header should also follow collapse when applied */
    @media (max-width: 901px) and (min-width: 900px) {
        body.sidebar-collapsed .modern-header {
            margin-left: 70px;
            width: calc(100% - 70px);
        }
    }
    </style>

    <style>
    /* Improved card styling applied site-wide for consistent per-page cards */
    .content .card {
        border: none;
        border-radius: 0.75rem;
        box-shadow: 0 8px 24px rgba(16, 24, 40, 0.06);
        transition: transform 0.14s ease, box-shadow 0.14s ease;
        overflow: hidden;
        margin-bottom: 1rem;
        background-color: #ffffff;
    }

    .content .card:hover {
        transform: translateY(-6px);
        box-shadow: 0 18px 48px rgba(16, 24, 40, 0.10);
    }

    .content .card .card-header {
        background: linear-gradient(90deg, rgba(32, 96, 115, 0.65), rgba(20, 32, 82, 0.71));
        border-bottom: none;
        padding: 0.75rem 1rem;
        font-weight: 600;
        color: #111827;
    }

    .content .card .card-body {
        padding: 1rem;
    }

    /* Small visual tweak for empty-state cards */
    .content .card .table td.text-center i {
        opacity: 0.45;
    }

    /* Per-page header/banner spacing consistency */
    .gradient-header, .content {
        max-width: 1150px;
        margin-left: auto;
        margin-right: auto;
        padding-left: 1rem;
        padding-right: 1rem;
    }

    @media (max-width: 768px) {
        .content .card { margin-bottom: 0.9rem; }
        .content .card .card-header { padding: 0.6rem 0.8rem; }
        .content { padding-left: 0.5rem; padding-right: 0.5rem; }
    }
    </style>
