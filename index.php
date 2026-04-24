<?php
require_once 'config.php';
// make sure global template helpers are available to pages
require_once __DIR__ . '/templates/page_template.php';

// Detect export/download actions early to avoid emitting any HTML before pages stream files
$action_get = isset($_GET['action']) ? strtolower((string)$_GET['action']) : '';
$action_post = ($_SERVER['REQUEST_METHOD'] === 'POST') ? strtolower((string)(isset($_POST['action']) ? $_POST['action'] : '')) : '';
$exporting = false;
if (!empty($_GET['export'])) {
    $exporting = true;
} elseif ($action_get !== '' && preg_match('/^(export|download)/', $action_get)) {
    $exporting = true;
} elseif ($action_post !== '' && preg_match('/^(export|download)/', $action_post)) {
    $exporting = true;
}

// Only include chrome when not exporting
if (!$exporting) {
    include 'includes/header.php';
    include 'includes/sidebar.php';
}

// Open main wrapper only for non-export flows
if (!$exporting) {
    echo '<main>';
}

include 'config/db.php';

$page = isset($_GET['page']) ? $_GET['page'] : '';

// Default page routing based on role
if (empty($page)) {
    $role = get_current_role();
    switch ($role) {
        case 'admin':
            $page = 'dashboard_admin';
            break;
        case 'operator':
            $page = 'dashboard_operator';
            break;
        case 'pimpinan':
            $page = 'dashboard_pimpinan';
            break;
        case 'user':
        case 'driver':
            $page = 'dashboard_user';
            break;
        default:
            $page = 'home';
            break;
    }
}

$file = "pages/$page.php";
if (file_exists($file)) {
    include $file;
} else {
    if (!$exporting) {
        echo '<div class="alert alert-danger">';
        echo '<h2>Halaman tidak ditemukan</h2>';
        echo '<p>Halaman yang Anda cari tidak dapat ditemukan.</p>';
        echo '<a href="index.php" class="btn btn-primary">Kembali ke Beranda</a>';
        echo '</div>';
    }
}

// Close main wrapper only for non-export flows
if (!$exporting) {
    echo '</main>';
    include 'includes/footer.php';
    // Flush output buffer (avoid during exports to keep binary stream clean)
    if (ob_get_level()) {
        ob_end_flush();
    }
}
?>
