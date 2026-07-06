<?php
// ====================
// CONFIG.PHP
// ====================

// Start output buffering to prevent header issues
ob_start();

// Start session if not already started
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Error reporting (development mode)
// Avoid HTML error output for AJAX/JSON responses to keep JSON parseable.
$__is_ajax = false;
if (isset($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest') {
    $__is_ajax = true;
}
if (!$__is_ajax && isset($_SERVER['REQUEST_URI']) && strpos($_SERVER['REQUEST_URI'], '/ajax/') !== false) {
    $__is_ajax = true;
}
if (!$__is_ajax && isset($_SERVER['HTTP_ACCEPT']) && strpos($_SERVER['HTTP_ACCEPT'], 'application/json') !== false) {
    $__is_ajax = true;
}
if (!$__is_ajax && isset($_SERVER['CONTENT_TYPE']) && strpos($_SERVER['CONTENT_TYPE'], 'application/json') !== false) {
    $__is_ajax = true;
}

$__display_errors = !$__is_ajax;
ini_set('display_errors', $__display_errors ? '1' : '0');
ini_set('display_startup_errors', $__display_errors ? '1' : '0');
error_reporting($__display_errors ? E_ALL : 0);

// Timezone & Date format
date_default_timezone_set('Asia/Jakarta');
define('DATE_FORMAT', 'd/m/y');

// Database credentials
define('DB_HOST', 'localhost');
define('DB_USER', 'root');
define('DB_PASS', '');
define('DB_NAME', 'randis');

// Base URL (ubah sesuai kebutuhan)
define('BASE_URL', 'http://localhost/randis/');

// Mail configuration — edit config/mail.php to set SMTP credentials.
// Environment variables override config/mail.php values if set.
$_mail_cfg = file_exists(__DIR__ . '/config/mail.php') ? (include __DIR__ . '/config/mail.php') : [];
if (!is_array($_mail_cfg)) { $_mail_cfg = []; }
define('MAIL_TRANSPORT',    getenv('MAIL_TRANSPORT')    ?: ($_mail_cfg['transport']    ?? 'smtp'));
define('MAIL_FROM_ADDRESS', getenv('MAIL_FROM_ADDRESS') ?: ($_mail_cfg['from_address'] ?? ''));
define('MAIL_FROM_NAME',    getenv('MAIL_FROM_NAME')    ?: ($_mail_cfg['from_name']    ?? 'Sistem Randis'));
define('MAIL_HOST',         getenv('MAIL_HOST')         ?: ($_mail_cfg['host']         ?? 'smtp.gmail.com'));
define('MAIL_PORT',         (int)(getenv('MAIL_PORT')   ?: ($_mail_cfg['port']         ?? 587)));
define('MAIL_USERNAME',     getenv('MAIL_USERNAME')     ?: ($_mail_cfg['username']     ?? ''));
define('MAIL_PASSWORD',     getenv('MAIL_PASSWORD')     ?: ($_mail_cfg['password']     ?? ''));
define('MAIL_ENCRYPTION',   getenv('MAIL_ENCRYPTION')   ?: ($_mail_cfg['encryption']   ?? 'tls'));
unset($_mail_cfg);

// ====================
// DB CONNECTION (MySQLi Persistent)
// ====================
$mysqli = @new mysqli('p:' . DB_HOST, DB_USER, DB_PASS, DB_NAME);

// Check connection
if ($mysqli->connect_error) {
    die("Koneksi database gagal: " . $mysqli->connect_error);
}

// Set charset to UTF-8
$mysqli->set_charset('utf8mb4');

// Create $conn alias for backward compatibility
$conn = $mysqli;

// ====================
// SECURITY FUNCTIONS
// ====================

// Password hashing (bcrypt)
function hash_password($password) {
    return password_hash($password, PASSWORD_BCRYPT);
}

// Password verify
function verify_password($password, $hash) {
    return password_verify($password, $hash);
}

// CSRF Token Generator
function generate_csrf_token() {
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

// CSRF Token Validator
function validate_csrf_token($token) {
    if (!isset($_SESSION['csrf_token'])) {
        error_log("CSRF: No session token exists");
        return false;
    }
    
    if (empty($token)) {
        error_log("CSRF: Empty token provided");
        return false;
    }
    
    $result = hash_equals($_SESSION['csrf_token'], $token);
    if (!$result) {
        error_log("CSRF: Token mismatch. Session: " . substr($_SESSION['csrf_token'], 0, 8) . "... Posted: " . substr($token, 0, 8) . "...");
    }
    
    return $result;
}

// ====================
// LOGGING FUNCTIONS
// ====================
function log_user_activity($activity) {
    $ip = $_SERVER['REMOTE_ADDR'] ?? 'UNKNOWN';
    $agent = $_SERVER['HTTP_USER_AGENT'] ?? 'UNKNOWN';
    $time = date(DATE_FORMAT . ' H:i:s');
    $log = "[{$time}] {$ip} | {$agent} | {$activity}\n";

    file_put_contents(__DIR__ . '/logs/user_activity.log', $log, FILE_APPEND);
}

// ====================
// HELPER FUNCTIONS
// ====================

// Redirect helper
function redirect($url) {
    header("Location: " . BASE_URL . $url);
    exit;
}

// Base URL helper
function base_url($path = '') {
    return BASE_URL . ltrim($path, '/');
}

// ====================
// AUTH INCLUDE
// ====================
// Include auth functions after database connection is established
require_once __DIR__ . '/includes/auth.php';

// ====================
// EMAIL AUTOMATION (pseudo-cron)
// ====================
// Menjalankan antrean + pengiriman reminder email secara otomatis setiap ~10 menit,
// dipicu oleh request halaman biasa (bukan AJAX) sehingga tidak perlu Windows Task Scheduler.
// Dibungkus try/catch supaya kegagalan email tidak pernah mematahkan halaman.
if (!$__is_ajax) {
    try {
        require_once __DIR__ . '/lib/email_triggers.php';
        run_email_automation_if_due($mysqli, 600);
    } catch (Throwable $e) {
        error_log('[email_automation] ' . $e->getMessage());
    }
}
?>
