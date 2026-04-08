<?php
require_once 'config.php';

// Log aktivitas logout jika user masih login
if (is_logged_in()) {
    log_activity("LOGOUT", "User keluar dari sistem");
}

// Hapus semua session
session_destroy();

// Redirect ke halaman home (guest)
header('Location: index.php');
exit;
?>