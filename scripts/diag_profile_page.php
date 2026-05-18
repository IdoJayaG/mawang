<?php
require_once __DIR__ . '/../config.php';
if (session_status() === PHP_SESSION_NONE) session_start();

// Simulasi session — ubah sesuai akun yang ingin diuji
$_SESSION['user_id'] = 44; // contoh: user_account.id
$_SESSION['username'] = 'joko.s';

require_once __DIR__ . '/../includes/auth.php';

echo "SESSION username: " . ($_SESSION['username'] ?? 'NULL') . PHP_EOL;
echo "SESSION user_id: " . ($_SESSION['user_id'] ?? 'NULL') . PHP_EOL;
echo "get_current_account_id(): " . (get_current_account_id() ?? 'NULL') . PHP_EOL;
echo "get_current_user_id(): " . (get_current_user_id() ?? 'NULL') . PHP_EOL;

// Recreate the profil selection logic (same robust algorithm used by pages/profil.php)
$current_account_id = get_current_account_id();
$current_pengguna_id = get_current_user_id();

$profile_account_id = null;
$profile_pengguna_id = null;

// Default: show current session's profile — prefer account id when available
$profile_account_id = $current_account_id;
$profile_pengguna_id = $current_pengguna_id;
if (empty($profile_account_id) && !empty($profile_pengguna_id)) {
    $profile_account_id = get_user_account_id_for_pengguna($profile_pengguna_id);
}

$requested_id = $profile_account_id ?? $profile_pengguna_id ?? (int)$profile_user_id;

$selectBase = "SELECT ua.*, p.nama_lengkap AS nama, p.no_hp, p.email AS email, p.alamat, p.nrp_nip AS nrp, p.pangkat, p.jabatan, COALESCE(p.kesatuan, '') AS satuan, ua.created_at, ua.last_login, ua.status, ua.username, ua.role_id, ua.pengguna_id, COALESCE(r.nama_role, '') AS role FROM user_account ua LEFT JOIN pengguna p ON ua.pengguna_id = p.id LEFT JOIN role r ON ua.role_id = r.id";

if (!empty($profile_account_id)) {
    $stmt = $mysqli->prepare($selectBase . " WHERE ua.id = ? LIMIT 1");
    $stmt->bind_param('i', $profile_account_id);
} elseif (!empty($profile_pengguna_id)) {
    $stmt = $mysqli->prepare($selectBase . " WHERE ua.pengguna_id = ? LIMIT 1");
    $stmt->bind_param('i', $profile_pengguna_id);
} else {
    $current_account = get_current_account_id();
    if (!empty($current_account)) {
        $stmt = $mysqli->prepare($selectBase . " WHERE ua.id = ? LIMIT 1");
        $stmt->bind_param('i', $current_account);
    } else {
        $stmt = $mysqli->prepare($selectBase . " WHERE ua.id = ? OR ua.pengguna_id = ? LIMIT 1");
        $stmt->bind_param('ii', $requested_id, $requested_id);
    }
}

$stmt->execute();
$user = $stmt->get_result()->fetch_assoc();
$stmt->close();

echo "Profile query result (user_account + pengguna):\n";
print_r($user);

// Also show header username source and profile name fields
echo "HEADER username (session): " . ($_SESSION['username'] ?? 'NULL') . PHP_EOL;
echo "PROFILE username (from user_account): " . ($user['username'] ?? 'NULL') . PHP_EOL;
echo "PROFILE nama_lengkap (from pengguna): " . ($user['nama'] ?? 'NULL') . PHP_EOL;

?>