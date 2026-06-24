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
    
    <!-- Bootstrap CSS (must load first so our files can override it) -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">

    <!-- Custom CSS (load after Bootstrap so overrides take effect) -->
    <link rel="stylesheet" href="assets/css/style.css">
    
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

