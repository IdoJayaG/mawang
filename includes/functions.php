<?php
/**
 * Common functions for SI-KENDI application (formerly RANDIS)
 */

// Start session if not already started
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

/**
 * Check if user is logged in
 * @return bool
 */
if (!function_exists('is_logged_in')) {
    function is_logged_in() {
        return isset($_SESSION['user_id']) && !empty($_SESSION['user_id']);
    }
}

/**
 * Get current user role
 * @return string
 */
if (!function_exists('get_current_role')) {
    function get_current_role() {
        // Normalize role to lowercase for consistent comparisons across the app
        $role = $_SESSION['role'] ?? 'user';
        return is_string($role) ? strtolower($role) : 'user';
    }
}

/**
 * Get logged in user data
 * @return array|null
 */
if (!function_exists('get_logged_in_user')) {
    function get_logged_in_user() {
        if (!is_logged_in()) {
            return null;
        }

        return [
            'id' => $_SESSION['user_id'] ?? null,
            'nama_lengkap' => $_SESSION['nama_lengkap'] ?? '',
            'nrp_nip' => $_SESSION['nrp_nip'] ?? '',
            'pangkat' => $_SESSION['pangkat'] ?? '',
            'role' => $_SESSION['role'] ?? 'USER',
            'email' => $_SESSION['email'] ?? ''
        ];
    }
}

/**
 * Log user activity
 * @param int $user_id
 * @param string $action
 * @param string $description
 * @return bool
 */
function logActivity($user_id, $action, $description = '') {
    global $pdo;

    if (!$pdo) {
        return false;
    }

    try {
        $stmt = $pdo->prepare("
            INSERT INTO log_aktivitas (user_id, action, description, ip_address, user_agent, created_at)
            VALUES (?, ?, ?, ?, ?, NOW())
        ");

        $ip = $_SERVER['REMOTE_ADDR'] ?? '';
        $user_agent = $_SERVER['HTTP_USER_AGENT'] ?? '';

        return $stmt->execute([$user_id, $action, $description, $ip, $user_agent]);
    } catch (Exception $e) {
        error_log("Failed to log activity: " . $e->getMessage());
        return false;
    }
}

/**
 * Check if user has specific role
 * @param string $role
 * @return bool
 */
if (!function_exists('has_role')) {
    function has_role($role) {
        return get_current_role() === strtolower($role);
    }
}

/**
 * Check if user has admin privileges
 * @return bool
 */
if (!function_exists('is_admin')) {
    function is_admin() {
        // Treat 'admin' and 'operator' as elevated roles
        return in_array(get_current_role(), ['admin', 'operator']);
    }
}

/**
 * Redirect if not logged in
 */
if (!function_exists('require_login')) {
    function require_login() {
        if (!is_logged_in()) {
            header('Location: login.php');
            exit();
        }
    }
}

/**
 * Sanitize output for HTML
 * @param string $string
 * @return string
 */
function h($string) {
    return htmlspecialchars($string, ENT_QUOTES, 'UTF-8');
}

/**
 * Format currency
 * @param float $amount
 * @return string
 */
if (!function_exists('format_currency')) {
    function format_currency($amount) {
        return 'Rp ' . number_format($amount, 0, ',', '.');
    }
}

/**
 * Format date
 * @param string $date
 * @param string $format
 * @return string
 */
if (!function_exists('format_date')) {
    function format_date($date, $format = 'd/m/Y') {
        if (empty($date)) return '-';
        return date($format, strtotime($date));
    }
}

/**
 * Get status badge class
 * @param string $status
 * @return string
 */
if (!function_exists('get_status_badge')) {
    function get_status_badge($status) {
        $badges = [
            'aktif' => 'success',
            'tidak_aktif' => 'secondary',
            'maintenance' => 'warning',
            'perbaikan' => 'danger',
            'pending' => 'warning',
            'approved' => 'info',
            'ongoing' => 'primary',
            'completed' => 'success',
            'rejected' => 'danger',
            'cancelled' => 'secondary',
            'terjadwal' => 'info',
            'selesai' => 'success'
        ];

        return $badges[strtolower($status)] ?? 'secondary';
    }
}

/**
 * Generate random string
 * @param int $length
 * @return string
 */
function generate_random_string($length = 10) {
    return bin2hex(random_bytes($length / 2));
}

/**
 * Validate email format
 * @param string $email
 * @return bool
 */
function is_valid_email($email) {
    return filter_var($email, FILTER_VALIDATE_EMAIL) !== false;
}

/**
 * Get user by ID
 * @param int $user_id
 * @return array|null
 */
function get_user_by_id($user_id) {
    global $pdo;

    if (!$pdo) {
        return null;
    }

    try {
        $stmt = $pdo->prepare("
            SELECT p.*, ua.username, r.kode_role, r.nama_role
            FROM pengguna p
            LEFT JOIN user_account ua ON p.id = ua.pengguna_id
            LEFT JOIN role r ON ua.role_id = r.id
            WHERE p.id = ?
        ");
        $stmt->execute([$user_id]);
        return $stmt->fetch(PDO::FETCH_ASSOC);
    } catch (Exception $e) {
        error_log("Failed to get user: " . $e->getMessage());
        return null;
    }
}

/**
 * Get vehicle by ID
 * @param int $vehicle_id
 * @return array|null
 */
function get_vehicle_by_id($vehicle_id) {
    global $pdo;

    if (!$pdo) {
        return null;
    }

    try {
        $stmt = $pdo->prepare("SELECT * FROM kendaraan WHERE id = ?");
        $stmt->execute([$vehicle_id]);
        return $stmt->fetch(PDO::FETCH_ASSOC);
    } catch (Exception $e) {
        error_log("Failed to get vehicle: " . $e->getMessage());
        return null;
    }
}

/**
 * Check if user can access page
 * @param string $required_role
 * @return bool
 */
if (!function_exists('can_access')) {
    function can_access($required_role) {
        $user_role = get_current_role();

        $role_hierarchy = [
            'user' => 1,
            'driver' => 1,
            'operator' => 2,
            'admin' => 3
        ];

        $required = strtolower($required_role);
        return ($role_hierarchy[$user_role] ?? 0) >= ($role_hierarchy[$required] ?? 999);
    }
}

/**
 * Get page title
 * @param string $page
 * @return string
 */
function get_page_title($page) {
    $titles = [
        'dashboard_user' => 'Dashboard User',
        'dashboard_admin' => 'Dashboard Admin',
        'dashboard_operator' => 'Dashboard Driver',
        'profil' => 'Profil Pengguna',
        'surat_tugas' => 'Surat Tugas',
        'peminjaman' => 'Peminjaman Kendaraan',
        'kendaraan' => 'Kelola Kendaraan',
        'perawatan' => 'Perawatan Kendaraan',
        'bbm' => 'Log Bahan Bakar',
        'laporan' => 'Laporan',
        'pengguna' => 'Manajemen Pengguna',
        'settings' => 'Pengaturan Sistem',
        'log_aktivitas' => 'Log Aktivitas'
    ];

    return $titles[$page] ?? ucfirst(str_replace('_', ' ', $page));
}

/**
 * Get menu items based on role
 * @return array
 */
function get_menu_items() {
    $role = get_current_role();
    $base_items = [];

    // Common items for all users
    $base_items[] = ['url' => 'dashboard_user', 'icon' => 'fas fa-home', 'text' => 'Dashboard'];
    $base_items[] = ['url' => 'profil', 'icon' => 'fas fa-user', 'text' => 'Profil'];

    // Driver and Admin items
    if (is_admin()) {
        $base_items[] = ['url' => 'kendaraan', 'icon' => 'fas fa-car', 'text' => 'Kendaraan'];
        $base_items[] = ['url' => 'peminjaman', 'icon' => 'fas fa-calendar-check', 'text' => 'Peminjaman'];
        $base_items[] = ['url' => 'surat_tugas', 'icon' => 'fas fa-file-signature', 'text' => 'Surat Tugas'];
        $base_items[] = ['url' => 'perawatan', 'icon' => 'fas fa-tools', 'text' => 'Perawatan'];
        $base_items[] = ['url' => 'bbm', 'icon' => 'fas fa-gas-pump', 'text' => 'BBM'];
        $base_items[] = ['url' => 'laporan', 'icon' => 'fas fa-chart-bar', 'text' => 'Laporan'];
        $base_items[] = ['url' => 'log_aktivitas', 'icon' => 'fas fa-history', 'text' => 'Log Aktivitas'];

        // Admin only items
        if ($role === 'admin') {
            $base_items[] = ['url' => 'pengguna', 'icon' => 'fas fa-users', 'text' => 'Pengguna'];
            $base_items[] = ['url' => 'settings', 'icon' => 'fas fa-cog', 'text' => 'Pengaturan'];
        }
    }

    return $base_items;
}
?>
