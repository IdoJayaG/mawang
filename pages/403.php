<?php
// Include global template and helpers (anchored)
require_once __DIR__ . '/../templates/page_template.php';
require_once __DIR__ . '/../includes/functions.php';

// Page configuration
$page_title = "Akses Ditolak - RANDIS";
$additional_css = [];

// Render page head
render_page_head($page_title, $additional_css);
?>

<div class="error-container">
    <div class="error-content">
        <h1>403</h1>
        <h2>Akses Ditolak</h2>
        <p>Maaf, Anda tidak memiliki izin untuk mengakses halaman ini.</p>
        <p>Silakan hubungi administrator jika Anda merasa ini adalah kesalahan.</p>
        <div class="error-actions">
            <a href="index.php" class="btn btn-primary">Kembali ke Beranda</a>
            <?php if (!is_logged_in()): ?>
                <a href="login.php" class="btn btn-secondary">Login</a>
            <?php endif; ?>
        </div>
    </div>
</div>

<?php render_page_footer(); ?>

