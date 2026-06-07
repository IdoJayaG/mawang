<?php
// ====================
// AUTH.PHP - Role Based Access Control
// ====================

// Prevent multiple inclusions
if (defined('AUTH_PHP_INCLUDED')) {
    return;
}
define('AUTH_PHP_INCLUDED', true);

// Ensure session is started and config is loaded
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Include config if not already included
if (!defined('DB_HOST')) {
    require_once __DIR__ . '/../config.php';
}

// Authentication and authorization functions

// Lightweight helpers for flexible schemas
if (!function_exists('db_table_exists')) {
    function db_table_exists($name) {
        global $mysqli;
        $name = $mysqli->real_escape_string($name);
        $res = $mysqli->query("SHOW TABLES LIKE '{$name}'");
        return $res && $res->num_rows > 0;
    }
}

if (!function_exists('db_table_columns')) {
    function db_table_columns($name) {
        global $mysqli;
        $cols = [];
        $name = $mysqli->real_escape_string($name);
        $res = $mysqli->query("SHOW COLUMNS FROM `{$name}`");
        if ($res) while ($r = $res->fetch_assoc()) $cols[] = $r['Field'];
        return $cols;
    }
}

if (!function_exists('pk_applicant_column')) {
    // Detect the applicant column for peminjaman_kendaraan
    function pk_applicant_column() {
        if (!db_table_exists('peminjaman_kendaraan')) return null;
        $cols = db_table_columns('peminjaman_kendaraan');
        foreach (['peminjam_id','pemohon_id','pengguna_id','user_id'] as $c) {
            if (in_array($c, $cols, true)) return $c;
        }
        return null;
    }
}

// Map pengguna.id to user_account.id when needed
if (!function_exists('get_user_account_id_for_pengguna')) {
    function get_user_account_id_for_pengguna($penggunaId) {
        global $mysqli;
        $penggunaId = (int)$penggunaId;
        $stmt = $mysqli->prepare("SELECT id FROM user_account WHERE pengguna_id = ? ORDER BY id DESC LIMIT 1");
        if (!$stmt) return null;
        $stmt->bind_param('i', $penggunaId);
        $stmt->execute();
        $res = $stmt->get_result();
        $row = $res ? $res->fetch_assoc() : null;
        $stmt->close();
        return $row && isset($row['id']) ? (int)$row['id'] : null;
    }
}

// Get current user_account.id for the logged-in session (if available)
if (!function_exists('get_current_account_id')) {
    function get_current_account_id() {
        global $mysqli;
        $session_id = isset($_SESSION['user_id']) ? (int)$_SESSION['user_id'] : null;
        if (!$session_id) return null;

        // If session holds account id, return it
        $st = $mysqli->prepare("SELECT id FROM user_account WHERE id = ? LIMIT 1");
        if ($st) {
            $st->bind_param('i', $session_id);
            $st->execute();
            $r = $st->get_result()->fetch_assoc();
            $st->close();
            if ($r && isset($r['id'])) return (int)$r['id'];
        }

        // Otherwise, try mapping session (pengguna.id) -> account id
        $mapped = get_user_account_id_for_pengguna($session_id);
        return $mapped ? (int)$mapped : null;
    }
}

// Given applicant column and pengguna.id, get the value to bind
if (!function_exists('pk_applicant_bind_value')) {
    function pk_applicant_bind_value($appCol, $penggunaId) {
        $penggunaId = (int)$penggunaId;
        if (!$appCol) return null;
        // Columns that reference pengguna.id directly
        if (in_array($appCol, ['peminjam_id','pemohon_id','pengguna_id'], true)) {
            return $penggunaId;
        }
        // Columns that reference user_account.id
        if ($appCol === 'user_id') {
            return get_user_account_id_for_pengguna($penggunaId);
        }
        return null;
    }
}

// Check if user is logged in
function is_logged_in() {
    // consider a user logged in when user_id exists in session
    return isset($_SESSION['user_id']);
}

// Get current user role (canonical slug)
function get_current_role() {
    // Prefer canonical role slug stored in session (kode_role). Fall back to role_name if needed.
    if (!empty($_SESSION['role'])) {
        $role = strtolower((string)$_SESSION['role']);
        return $role;
    }

    // Backward compatibility: if role_name exists, derive a slug-like value
    if (!empty($_SESSION['role_name'])) {
        $slug = strtolower(trim($_SESSION['role_name']));
        $slug = preg_replace('/[^a-z0-9_\-]+/', '_', $slug);
        $slug = trim($slug, '_');
        return $slug;
    }

    return 'guest';
}

// Role helpers (dynamic RBAC based on role.level_akses)
if (!function_exists('get_role_cache')) {
    function get_role_cache() {
        static $cache = null;
        if ($cache !== null) return $cache;

        $cache = [
            'by_id' => [],
            'by_code' => [],
            'min_level' => null,
        ];

        if (!function_exists('db_table_exists') || !db_table_exists('role')) {
            return $cache;
        }

        global $mysqli;
        $res = $mysqli->query("SELECT id, kode_role, nama_role, level_akses FROM role");
        if ($res) {
            while ($row = $res->fetch_assoc()) {
                $id = (int)($row['id'] ?? 0);
                $code = strtoupper(trim((string)($row['kode_role'] ?? $row['nama_role'] ?? '')));
                $level = isset($row['level_akses']) ? (int)$row['level_akses'] : null;
                if ($id <= 0) continue;
                $cache['by_id'][$id] = [
                    'id' => $id,
                    'kode_role' => $code,
                    'nama_role' => $row['nama_role'] ?? $code,
                    'level_akses' => $level,
                ];
                if ($code !== '') {
                    $cache['by_code'][$code] = $cache['by_id'][$id];
                }
                if ($level !== null) {
                    $cache['min_level'] = ($cache['min_level'] === null) ? $level : min($cache['min_level'], $level);
                }
            }
        }

        return $cache;
    }
}

if (!function_exists('get_role_by_code')) {
    function get_role_by_code($code) {
        $code = strtoupper(trim((string)$code));
        if ($code === '') return null;
        $cache = get_role_cache();
        return $cache['by_code'][$code] ?? null;
    }
}

if (!function_exists('get_role_level_by_code')) {
    function get_role_level_by_code($code) {
        $role = get_role_by_code($code);
        return $role && isset($role['level_akses']) ? (int)$role['level_akses'] : null;
    }
}

if (!function_exists('get_role_id_by_code')) {
    function get_role_id_by_code($code) {
        $role = get_role_by_code($code);
        return $role && isset($role['id']) ? (int)$role['id'] : null;
    }
}

if (!function_exists('get_min_role_level')) {
    function get_min_role_level() {
        $cache = get_role_cache();
        return $cache['min_level'];
    }
}

if (!function_exists('get_current_role_level')) {
    function get_current_role_level() {
        $role = get_current_role();
        $level = get_role_level_by_code($role);
        if ($level !== null) return $level;

        // Fallback: derive from user_account if session role slug is missing
        if (function_exists('get_current_account_id')) {
            $account_id = get_current_account_id();
            if ($account_id) {
                global $mysqli;
                $stmt = $mysqli->prepare("SELECT r.level_akses FROM user_account ua JOIN role r ON ua.role_id = r.id WHERE ua.id = ? LIMIT 1");
                if ($stmt) {
                    $stmt->bind_param('i', $account_id);
                    $stmt->execute();
                    $row = $stmt->get_result()->fetch_assoc();
                    $stmt->close();
                    if ($row && isset($row['level_akses'])) return (int)$row['level_akses'];
                }
            }
        }

        return null;
    }
}

if (!function_exists('is_admin_like')) {
    function is_admin_like() {
        $current_role = strtolower(trim((string)get_current_role()));
        // Policy: pimpinan is admin-like even if level_akses has not been normalized yet.
        if ($current_role === 'pimpinan') return true;

        $current_level = get_current_role_level();
        $min_level = get_min_role_level();
        if ($current_level === null || $min_level === null) {
            return in_array($current_role, ['admin', 'pimpinan'], true);
        }
        return $current_level <= $min_level;
    }
}

if (!function_exists('is_role_admin_like')) {
    function is_role_admin_like($role_code) {
        $role_code_norm = strtolower(trim((string)$role_code));
        if ($role_code_norm === 'pimpinan') return true;

        $level = get_role_level_by_code($role_code);
        $min_level = get_min_role_level();
        if ($level === null || $min_level === null) {
            return in_array($role_code_norm, ['admin', 'pimpinan'], true);
        }
        return $level <= $min_level;
    }
}

if (!function_exists('get_admin_like_role_ids')) {
    function get_admin_like_role_ids() {
        $cache = get_role_cache();
        if (empty($cache['by_id'])) return [];

        $ids = [];
        foreach ($cache['by_id'] as $id => $role) {
            $code = $role['kode_role'] ?? $role['nama_role'] ?? '';
            if (is_role_admin_like($code)) {
                $ids[] = (int)$id;
            }
        }
        return $ids;
    }
}

// Get current user ID
function get_current_user_id() {
    // Return the canonical pengguna.id for the currently logged-in user.
    // Some codepaths store user_account.id in the session (login.php). Map it to pengguna.id when possible.
    global $mysqli;
    $session_id = isset($_SESSION['user_id']) ? $_SESSION['user_id'] : null;
    if (!$session_id) return null;

    // Try mapping assuming session contains user_account.id
    $stmt = $mysqli->prepare("SELECT pengguna_id FROM user_account WHERE id = ? LIMIT 1");
    if ($stmt) {
        $stmt->bind_param('i', $session_id);
        $stmt->execute();
        $res = $stmt->get_result();
        if ($res && ($row = $res->fetch_assoc())) {
            $stmt->close();
            if (!empty($row['pengguna_id'])) return (int)$row['pengguna_id'];
        }
        $stmt->close();
    }

    // Fallback: assume session holds pengguna.id already
    return $session_id;
}

// Get current user data
function get_logged_in_user() {
    global $mysqli;
    
    if (!is_logged_in()) {
        return null;
    }
    
    $user_id = get_current_user_id();
    if (!$user_id) {
        return null;
    }
    
    $stmt = $mysqli->prepare("SELECT * FROM pengguna WHERE id = ?");
    $stmt->bind_param('i', $user_id);
    $stmt->execute();
    $result = $stmt->get_result();
    $user = $result->fetch_assoc();
    $stmt->close();
    
    return $user;
}

// Get current username (for backward compatibility)
function get_current_username() {
    $user = get_logged_in_user();
    return $user ? $user['nama_lengkap'] : null;
}

// Require login - redirect if not logged in
function require_login() {
    if (!is_logged_in()) {
        header('Location: login.php');
        exit;
    }
}

// Check if user can perform admin-like actions
function can_admin() {
    return is_admin_like();
}

// Check if user can perform admin-like actions
function can_operate() {
    return is_admin_like();
}

// Check if user can access specific vehicle
function can_access_vehicle($vehicle_id) {
    global $mysqli;
    $role = get_current_role();
    $user_id = get_current_user_id();
    
    // Admin and pimpinan can access all vehicles
    if (can_operate()) {
        return true;
    }
    
    // User can only access vehicles they are using/approved to use
    if (in_array($role, ['user', 'driver'], true) && $user_id) {
        // Check active peminjaman_kendaraan
        if (db_table_exists('peminjaman_kendaraan')) {
            $appCol = pk_applicant_column();
            $bindVal = pk_applicant_bind_value($appCol, (int)$user_id);
            if ($appCol && $bindVal) {
                $sql = "SELECT COUNT(*) c FROM peminjaman_kendaraan WHERE kendaraan_id = ? AND `{$appCol}` = ? AND status IN ('Approved','approved','Ongoing','ongoing')";
                $stmt = $mysqli->prepare($sql);
                $stmt->bind_param('ii', $vehicle_id, $bindVal);
                $stmt->execute();
                $res = $stmt->get_result()->fetch_assoc();
                $stmt->close();
                if ((int)(isset($res['c']) ? $res['c'] : 0) > 0) return true;
            }
        }
        // Check surat_tugas
        if (db_table_exists('surat_tugas')) {
            $stmt = $mysqli->prepare("SELECT COUNT(*) c FROM surat_tugas WHERE kendaraan_id = ? AND pengguna_id = ? AND status IN ('Disetujui','Dalam Perjalanan')");
            $stmt->bind_param('ii', $vehicle_id, $user_id);
            $stmt->execute();
            $res = $stmt->get_result()->fetch_assoc();
            $stmt->close();
            if ((int)(isset($res['c']) ? $res['c'] : 0) > 0) return true;
        }
        // Also allow access when the vehicle has an explicit `pengguna_id` assigned
        if (db_table_exists('kendaraan') && function_exists('db_table_columns')) {
            $cols = db_table_columns('kendaraan');
            if (in_array('pengguna_id', $cols, true)) {
                $st = $mysqli->prepare("SELECT COUNT(*) c FROM kendaraan WHERE id = ? AND COALESCE(pengguna_id,0) = ?");
                if ($st) {
                    $st->bind_param('ii', $vehicle_id, $user_id);
                    $st->execute();
                    $r = $st->get_result()->fetch_assoc();
                    $st->close();
                    if ((int)(isset($r['c']) ? $r['c'] : 0) > 0) return true;
                }
            }
        }
        return false;
    }
    
    // Guest can view public vehicles only
    if ($role === 'guest') {
        $stmt = $mysqli->prepare("SELECT COUNT(*) FROM kendaraan WHERE id = ? AND status_kendaraan = 'Operasional' AND kondisi = 'Baik'");
        $stmt->bind_param('i', $vehicle_id);
        $stmt->execute();
        $stmt->bind_result($count);
        $stmt->fetch();
        $stmt->close();
        return $count > 0;
    }
    
    return false;
}

// Get vehicles accessible by current user with search capability
function get_accessible_vehicles($role, $user_id = null, $search = '', $limit = null) {
    global $mysqli;
    // Base select with optional join to pengguna for searching current user
    $hasPenggunaId = function_exists('db_table_columns') && in_array('pengguna_id', (array)db_table_columns('kendaraan'), true);
    $sql = $hasPenggunaId
        ? "SELECT k.*, COALESCE(p.nama_lengkap,'') AS current_user_name FROM kendaraan k LEFT JOIN pengguna p ON k.pengguna_id = p.id"
        : "SELECT k.*, NULL AS current_user_name FROM kendaraan k";
    
    $where_conditions = [];
    $params = [];
    $types = "";
    
    // Role-based filtering
    if ($role === 'guest') {
        // Guest: Only operational vehicles in good condition with public visibility
        $where_conditions[] = "k.status_kendaraan = 'Operasional'";
        $where_conditions[] = "k.kondisi IN ('Baik', 'Rusak Ringan')";
    } elseif (in_array($role, ['user', 'driver'], true) && $user_id) {
        // User: vehicles they are approved/ongoing to use (pk or surat_tugas)
        $existsClauses = [];
        if (db_table_exists('peminjaman_kendaraan')) {
            $appCol = pk_applicant_column();
            $bindVal = pk_applicant_bind_value($appCol, (int)$user_id);
            if ($appCol && $bindVal) {
                $existsClauses[] = "EXISTS (SELECT 1 FROM peminjaman_kendaraan pk2 WHERE pk2.kendaraan_id = k.id AND pk2.`{$appCol}` = ? AND pk2.status IN ('Approved','approved','Ongoing','ongoing'))";
                $params[] = $bindVal;
                $types .= 'i';
            }
        }
        if (db_table_exists('surat_tugas')) {
            $existsClauses[] = "EXISTS (SELECT 1 FROM surat_tugas s2 WHERE s2.kendaraan_id = k.id AND s2.pengguna_id = ? AND s2.status IN ('Disetujui','Dalam Perjalanan'))";
            $params[] = $user_id;
            $types .= 'i';
        }
        if (!empty($existsClauses)) {
            $where_conditions[] = '(' . implode(' OR ', $existsClauses) . ')';
        } else {
            $where_conditions[] = '1=0';
        }
    }
    // Admin-like roles: see all vehicles (no additional filtering)
    
    // Search filtering
    if (!empty($search)) {
        $search_term = "%$search%";
        if ($hasPenggunaId) {
            $where_conditions[] = "(k.no_polisi LIKE ? OR k.merk LIKE ? OR k.tipe LIKE ? OR p.nama_lengkap LIKE ?)";
            $params = array_merge($params, [$search_term, $search_term, $search_term, $search_term]);
            $types .= "ssss";
        } else {
            $where_conditions[] = "(k.no_polisi LIKE ? OR k.merk LIKE ? OR k.tipe LIKE ?)";
            $params = array_merge($params, [$search_term, $search_term, $search_term]);
            $types .= "sss";
        }
    }
    
    // Add WHERE clause if there are conditions
    if (!empty($where_conditions)) {
        $sql .= " WHERE " . implode(" AND ", $where_conditions);
    }
    
    $sql .= " ORDER BY k.updated_at DESC, k.id DESC";
    
    if ($limit) {
        $sql .= " LIMIT ?";
        $params[] = $limit;
        $types .= "i";
    }
    
    $stmt = $mysqli->prepare($sql);
    if (!empty($params)) {
        $stmt->bind_param($types, ...$params);
    }
    $stmt->execute();
    $result = $stmt->get_result();
    $vehicles = $result->fetch_all(MYSQLI_ASSOC);
    $stmt->close();
    
    return $vehicles;
}

// Paginated variant returning total via reference (non-breaking new helper)
function get_accessible_vehicles_paginated($role, $user_id = null, $search = '', $limit = 20, $offset = 0, &$total_records = 0, $sort_col = 'updated_at', $sort_dir = 'desc') {
    global $mysqli;

    // Detect optional pengguna_id column to enable join & sorting by current user
    $hasPenggunaId = function_exists('db_table_columns') && in_array('pengguna_id', (array)db_table_columns('kendaraan'), true);

    $baseSql = $hasPenggunaId ? "FROM kendaraan k LEFT JOIN pengguna p ON k.pengguna_id = p.id" : "FROM kendaraan k";
    $where_conditions = [];
    $params = [];
    $types = '';

    // Role-based filtering (mirrors get_accessible_vehicles)
    if ($role === 'guest') {
        $where_conditions[] = "k.status_kendaraan = 'Operasional'";
        $where_conditions[] = "k.kondisi IN ('Baik', 'Rusak Ringan')";
    } elseif ($role === 'user' && $user_id) {
        $existsClauses = [];
        if (db_table_exists('peminjaman_kendaraan')) {
            $appCol = pk_applicant_column();
            $bindVal = pk_applicant_bind_value($appCol, (int)$user_id);
            if ($appCol && $bindVal) {
                $existsClauses[] = "EXISTS (SELECT 1 FROM peminjaman_kendaraan pk2 WHERE pk2.kendaraan_id = k.id AND pk2.`{$appCol}` = ? AND pk2.status IN ('Approved','approved','Ongoing','ongoing'))";
                $params[] = $bindVal; $types .= 'i';
            }
        }
        if (db_table_exists('surat_tugas')) {
            $existsClauses[] = "EXISTS (SELECT 1 FROM surat_tugas s2 WHERE s2.kendaraan_id = k.id AND s2.pengguna_id = ? AND s2.status IN ('Disetujui','Dalam Perjalanan'))";
            $params[] = $user_id; $types .= 'i';
        }
        if (!empty($existsClauses)) {
            $where_conditions[] = '(' . implode(' OR ', $existsClauses) . ')';
        } else {
            $where_conditions[] = '1=0';
        }
    }

    if (!empty($search)) {
        $search_term = "%$search%";
        if ($hasPenggunaId) {
            $where_conditions[] = "(k.no_polisi LIKE ? OR k.merk LIKE ? OR k.tipe LIKE ? OR p.nama_lengkap LIKE ?)";
            $params = array_merge($params, [$search_term, $search_term, $search_term, $search_term]);
            $types .= 'ssss';
        } else {
            $where_conditions[] = "(k.no_polisi LIKE ? OR k.merk LIKE ? OR k.tipe LIKE ?)";
            $params = array_merge($params, [$search_term, $search_term, $search_term]);
            $types .= 'sss';
        }
    }

    $whereSql = '';
    if (!empty($where_conditions)) {
        $whereSql = ' WHERE ' . implode(' AND ', $where_conditions);
    }

    // Count total
    $countSql = 'SELECT COUNT(*) cnt ' . $baseSql . $whereSql;
    $stmtCount = $mysqli->prepare($countSql);
    if ($stmtCount) {
        if (!empty($params)) { $stmtCount->bind_param($types, ...$params); }
        $stmtCount->execute();
    $resC = $stmtCount->get_result()->fetch_assoc();
    $total_records = (int)(isset($resC['cnt']) ? $resC['cnt'] : 0);
        $stmtCount->close();
    } else {
        $total_records = 0; // fallback
    }

    // Sanitize sorting
    $allowedCols = [
        'updated_at' => 'k.updated_at',
        'merk' => 'k.merk',
        'no_reg' => 'k.no_reg',
        'tahun_pembuatan' => 'k.tahun_pembuatan',
        'status_kendaraan' => 'k.status_kendaraan'
    ];
    if ($hasPenggunaId) {
        // Allow sorting by current user (nama_lengkap)
        $allowedCols['current_user'] = 'p.nama_lengkap';
    }
    if (!isset($allowedCols[$sort_col])) { $sort_col = 'updated_at'; }
    $sort_dir = strtolower($sort_dir) === 'asc' ? 'ASC' : 'DESC';
    $orderSql = $allowedCols[$sort_col] . ' ' . $sort_dir . ', k.id DESC';

    // Fetch page data
    $selectUser = $hasPenggunaId ? "COALESCE(p.nama_lengkap,'') AS current_user_name" : "NULL AS current_user_name";
    $dataSql = 'SELECT k.*, ' . $selectUser . ' ' . $baseSql . $whereSql . ' ORDER BY ' . $orderSql . ' LIMIT ? OFFSET ?';
    $stmt = $mysqli->prepare($dataSql);
    if ($stmt) {
        // bind params + limit + offset
        $bindParams = $params; $bindTypes = $types . 'ii';
        $bindParams[] = (int)$limit; $bindParams[] = (int)$offset;
        if (!empty($bindParams)) { $stmt->bind_param($bindTypes, ...$bindParams); }
        $stmt->execute();
        $result = $stmt->get_result();
        $vehicles = $result->fetch_all(MYSQLI_ASSOC);
        $stmt->close();
    } else {
        $vehicles = [];
    }

    return $vehicles;
}

// Check if user can download documents based on role and vehicle access
function can_download_documents($vehicle_id) {
    $role = get_current_role();
    $user_id = get_current_user_id();
    
    // Admin and pimpinan can download all documents
    if (can_operate()) {
        return true;
    }
    
    // User can download documents for their assigned vehicles
    if (in_array($role, ['user', 'driver'], true) && $user_id) {
        global $mysqli;
        // peminjaman_kendaraan check
        if (db_table_exists('peminjaman_kendaraan')) {
            $appCol = pk_applicant_column();
            $bindVal = pk_applicant_bind_value($appCol, (int)$user_id);
            if ($appCol && $bindVal) {
                $sql = "SELECT COUNT(*) c FROM peminjaman_kendaraan WHERE kendaraan_id = ? AND `{$appCol}` = ? AND status IN ('Approved','approved','Ongoing','ongoing')";
                $stmt = $mysqli->prepare($sql);
                $stmt->bind_param('ii', $vehicle_id, $bindVal);
                $stmt->execute();
                $res = $stmt->get_result()->fetch_assoc();
                $stmt->close();
                if ((int)(isset($res['c']) ? $res['c'] : 0) > 0) return true;
            }
        }
        // surat_tugas check
        if (db_table_exists('surat_tugas')) {
            $stmt = $mysqli->prepare("SELECT COUNT(*) c FROM surat_tugas WHERE kendaraan_id = ? AND pengguna_id = ? AND status IN ('Disetujui','Dalam Perjalanan')");
            $stmt->bind_param('ii', $vehicle_id, $user_id);
            $stmt->execute();
            $res = $stmt->get_result()->fetch_assoc();
            $stmt->close();
            if ((int)(isset($res['c']) ? $res['c'] : 0) > 0) return true;
        }
        return false;
    }
    
    return false;
}

// Get role-specific dashboard stats
function get_dashboard_stats($role, $user_id = null) {
    global $mysqli;
    $stats = [];
    
    if ($role === 'guest') {
        // Public statistics
        $result = $mysqli->query("SELECT 
            COUNT(*) as total_kendaraan, 
            SUM(CASE WHEN status_kendaraan = 'Operasional' THEN 1 ELSE 0 END) as operasional, 
            SUM(CASE WHEN kondisi = 'Baik' THEN 1 ELSE 0 END) as kondisi_baik 
            FROM kendaraan");
        $stats = $result->fetch_assoc();
        
    } elseif (in_array($role, ['user', 'driver'], true) && $user_id) {
        // User personal statistics using peminjaman_kendaraan and surat_tugas
        $kendaraanSaya = 0; $totalPemakaian = 0; $totalPerawatan = 0;
        // Build EXISTS clauses
        $existsPk = '';
        $existsSt = '';
        $binds = [];
        $types = '';
        if (db_table_exists('peminjaman_kendaraan')) {
            $appCol = pk_applicant_column();
            $bindVal = pk_applicant_bind_value($appCol, (int)$user_id);
            if ($appCol && $bindVal) {
                $existsPk = "EXISTS (SELECT 1 FROM peminjaman_kendaraan pk2 WHERE pk2.kendaraan_id = k.id AND pk2.`{$appCol}` = ? AND pk2.status IN ('Approved','approved','Ongoing','ongoing'))";
                $binds[] = $bindVal; $types .= 'i';
            }
        }
        if (db_table_exists('surat_tugas')) {
            $existsSt = "EXISTS (SELECT 1 FROM surat_tugas s2 WHERE s2.kendaraan_id = k.id AND s2.pengguna_id = ? AND s2.status IN ('Disetujui','Dalam Perjalanan'))";
            $binds[] = $user_id; $types .= 'i';
        }
        $exists = [];
        if ($existsPk) $exists[] = $existsPk;
        if ($existsSt) $exists[] = $existsSt;
        $where = !empty($exists) ? '(' . implode(' OR ', $exists) . ')' : '1=0';

        // 1) Count kendaraan saya
        $sql1 = "SELECT COUNT(DISTINCT k.id) c FROM kendaraan k WHERE {$where}";
        $stmt1 = $mysqli->prepare($sql1);
        if (!empty($binds)) { $stmt1->bind_param($types, ...$binds); }
    $stmt1->execute(); $res1 = $stmt1->get_result()->fetch_assoc(); $stmt1->close();
    $kendaraanSaya = (int)(isset($res1['c']) ? $res1['c'] : 0);

        // 2) Total pemakaian
        if (db_table_exists('riwayat_pemakaian')) {
            $sql2 = "SELECT COUNT(*) c FROM riwayat_pemakaian rp WHERE EXISTS (SELECT 1 FROM kendaraan k WHERE k.id = rp.kendaraan_id AND {$where})";
            $stmt2 = $mysqli->prepare($sql2);
            if (!empty($binds)) { $stmt2->bind_param($types, ...$binds); }
            $stmt2->execute(); $res2 = $stmt2->get_result()->fetch_assoc(); $stmt2->close();
            $totalPemakaian = (int)(isset($res2['c']) ? $res2['c'] : 0);
        }

        // 3) Total perawatan
        if (db_table_exists('riwayat_perawatan')) {
            $sql3 = "SELECT COUNT(*) c FROM riwayat_perawatan rt WHERE EXISTS (SELECT 1 FROM kendaraan k WHERE k.id = rt.kendaraan_id AND {$where})";
            $stmt3 = $mysqli->prepare($sql3);
            if (!empty($binds)) { $stmt3->bind_param($types, ...$binds); }
            $stmt3->execute(); $res3 = $stmt3->get_result()->fetch_assoc(); $stmt3->close();
            $totalPerawatan = (int)(isset($res3['c']) ? $res3['c'] : 0);
        }

        $stats = [
            'kendaraan_saya' => $kendaraanSaya,
            'total_pemakaian' => $totalPemakaian,
            'total_perawatan' => $totalPerawatan,
        ];
        
    } elseif (can_operate()) {
        // Admin-like system statistics without pengguna_kendaraan
        $totalPenggunaExpr = '0';
        $parts = [];
        if (db_table_exists('peminjaman_kendaraan')) {
            $appCol = pk_applicant_column();
            if ($appCol) {
                $parts[] = "SELECT DISTINCT pk2.`{$appCol}` AS uid FROM peminjaman_kendaraan pk2 WHERE pk2.status IN ('Approved','approved','Ongoing','ongoing')";
            }
        }
        if (db_table_exists('surat_tugas')) {
            $parts[] = "SELECT DISTINCT s2.pengguna_id AS uid FROM surat_tugas s2 WHERE s2.status IN ('Disetujui','Dalam Perjalanan')";
        }
        if (!empty($parts)) {
            $totalPenggunaExpr = "(SELECT COUNT(*) FROM (" . implode(" UNION ", $parts) . ") u)";
        }
        $sql = "SELECT 
                COUNT(*) as total_kendaraan, 
                SUM(CASE WHEN status_kendaraan = 'Operasional' THEN 1 ELSE 0 END) as operasional, 
                SUM(CASE WHEN status_kendaraan = 'Perbaikan' THEN 1 ELSE 0 END) as perbaikan, 
                SUM(CASE WHEN status_kendaraan = 'Rusak' THEN 1 ELSE 0 END) as rusak, 
                SUM(CASE WHEN kondisi = 'Baik' THEN 1 ELSE 0 END) as kondisi_baik, 
                {$totalPenggunaExpr} as total_pengguna 
                FROM kendaraan k";
        $result = $mysqli->query($sql);
        $stats = $result ? $result->fetch_assoc() : [
            'total_kendaraan' => 0,
            'operasional' => 0,
            'perbaikan' => 0,
            'rusak' => 0,
            'kondisi_baik' => 0,
            'total_pengguna' => 0,
        ];
    }
    
    return $stats;
}

// Redirect to login if not authenticated
function check_login() {
    if (!is_logged_in()) {
        header('Location: login.php');
        exit;
    }
}

// Redirect to appropriate dashboard based on role
function redirect_to_dashboard() {
    $role = get_current_role();
    $role_upper = strtoupper((string)$role);
    $page = 'home';

    if (is_admin_like()) {
        $page = ($role_upper === 'PIMPINAN') ? 'dashboard_pimpinan' : 'dashboard_admin';
    } elseif (in_array($role_upper, ['DRIVER', 'SOPIR'], true)) {
        $page = 'dashboard_driver';
    } elseif (in_array($role_upper, ['USER', 'PEGAWAI', 'PNS', 'ANGGOTA'], true)) {
        $page = 'dashboard_user';
    }

    header("Location: index.php?page={$page}");
    exit;
}

// Log user activity
function log_activity($action, $description = '') {
    global $mysqli;
    
    if (!is_logged_in()) {
        return false;
    }
    
    // Session may store either user_account.id (current code) or pengguna.id (legacy).
    // log_aktivitas.user_id references pengguna.id, so try to map session user to pengguna.id.
    $session_user_id = $_SESSION['user_id'];
    $user_id = null;

    // First try: assume session holds user_account.id -> map to pengguna_id
    $stmt_map = $mysqli->prepare("SELECT pengguna_id FROM user_account WHERE id = ? LIMIT 1");
    if ($stmt_map) {
        $stmt_map->bind_param('i', $session_user_id);
        $stmt_map->execute();
        $res_map = $stmt_map->get_result();
        if ($res_map && $row_map = $res_map->fetch_assoc()) {
            $mapped = isset($row_map['pengguna_id']) ? $row_map['pengguna_id'] : null;
            if ($mapped) {
                $user_id = (int)$mapped;
            }
        }
        $stmt_map->close();
    }

    // Fallback: if mapping not found, assume session stores pengguna.id directly
    if (!$user_id) {
        $user_id = $session_user_id;
    }
    $ip_address = isset($_SERVER['REMOTE_ADDR']) ? $_SERVER['REMOTE_ADDR'] : 'UNKNOWN';
    $user_agent = isset($_SERVER['HTTP_USER_AGENT']) ? $_SERVER['HTTP_USER_AGENT'] : 'UNKNOWN';
    
    // Ensure the log_aktivitas table exists
    if (!function_exists('db_table_exists') || !db_table_exists('log_aktivitas')) {
        return false;
    }

    // Ensure user_id is a valid pengguna.id; try a few fallbacks to resolve it
    $valid_user_id = null;

    // 1) If mapping from user_account produced a pengguna id, use it
    if (!empty($user_id) && is_numeric($user_id)) {
        // user_id here may already be a pengguna.id from mapping above
        $chk = $mysqli->prepare("SELECT id FROM pengguna WHERE id = ? LIMIT 1");
        if ($chk) {
            $uid_check = (int)$user_id;
            $chk->bind_param('i', $uid_check);
            $chk->execute();
            $res_chk = $chk->get_result();
            if ($res_chk && $res_chk->fetch_assoc()) {
                $valid_user_id = $uid_check;
            }
            $chk->close();
        }
    }

    // 2) If not valid yet, maybe session stores pengguna.id directly
    if (!$valid_user_id && !empty($session_user_id) && is_numeric($session_user_id)) {
        $chk2 = $mysqli->prepare("SELECT id FROM pengguna WHERE id = ? LIMIT 1");
        if ($chk2) {
            $suid = (int)$session_user_id;
            $chk2->bind_param('i', $suid);
            $chk2->execute();
            $res2 = $chk2->get_result();
            if ($res2 && $res2->fetch_assoc()) {
                $valid_user_id = $suid;
            }
            $chk2->close();
        }
    }

    // 3) If still not found, try resolving by username -> user_account -> pengguna
    if (!$valid_user_id && !empty($_SESSION['username'])) {
        $uname = $_SESSION['username'];
        $stmt_un = $mysqli->prepare("SELECT p.id FROM pengguna p JOIN user_account ua ON ua.pengguna_id = p.id WHERE ua.username = ? LIMIT 1");
        if ($stmt_un) {
            $stmt_un->bind_param('s', $uname);
            $stmt_un->execute();
            $res_un = $stmt_un->get_result();
            if ($res_un && ($r = $res_un->fetch_assoc())) {
                $valid_user_id = (int)$r['id'];
            }
            $stmt_un->close();
        }
    }

    // If still no valid pengguna id, skip logging to avoid FK errors
    if (!$valid_user_id) {
        return false;
    }

    // Insert log with error handling to avoid uncaught exceptions
    try {
        $stmt = $mysqli->prepare("INSERT INTO log_aktivitas (user_id, activity_type, description, ip_address, user_agent, created_at) VALUES (?, ?, ?, ?, ?, NOW())");
        if (!$stmt) {
            return false;
        }
        $stmt->bind_param('issss', $valid_user_id, $action, $description, $ip_address, $user_agent);
        $result = $stmt->execute();
        $stmt->close();
        return $result;
    } catch (Throwable $e) {
        error_log('log_activity failed: ' . $e->getMessage());
        return false;
    }
}

// Get user info
function get_user_info() {
    global $mysqli;
    
    if (!is_logged_in()) {
        return null;
    }
    
    $stmt = $mysqli->prepare("
        SELECT p.*, ua.username, r.nama_role, r.kode_role
        FROM pengguna p 
        JOIN user_account ua ON p.id = ua.pengguna_id 
        JOIN role r ON ua.role_id = r.id 
        WHERE ua.id = ?
    ");
    $stmt->bind_param('i', $_SESSION['user_id']);
    $stmt->execute();
    $result = $stmt->get_result();
    $user = $result->fetch_assoc();
    $stmt->close();
    
    return $user;
}

// Get user's assigned vehicles
function get_user_vehicles($user_id = null) {
    global $mysqli;
    
    if (!$user_id) {
        $user_id = isset($_SESSION['user_id']) ? $_SESSION['user_id'] : 0;
    }
    $vehicles = [];
    $queries = [];
    $binds = [];
    $types = '';
    // From peminjaman_kendaraan
    if (db_table_exists('peminjaman_kendaraan')) {
        $appCol = pk_applicant_column();
        $bindVal = pk_applicant_bind_value($appCol, (int)$user_id);
        if ($appCol && $bindVal) {
            $queries[] = "SELECT k.*, pk.tanggal_mulai, pk.tanggal_selesai, pk.status as assignment_status FROM kendaraan k JOIN peminjaman_kendaraan pk ON k.id = pk.kendaraan_id WHERE pk.`{$appCol}` = ? AND pk.status IN ('Approved','approved','Ongoing','ongoing')";
            $binds[] = $bindVal; $types .= 'i';
        }
    }
    // From surat_tugas
    if (db_table_exists('surat_tugas')) {
        $queries[] = "SELECT k.*, s.tanggal_berangkat AS tanggal_mulai, s.tanggal_kembali AS tanggal_selesai, s.status as assignment_status FROM kendaraan k JOIN surat_tugas s ON k.id = s.kendaraan_id WHERE s.pengguna_id = ? AND s.status IN ('Disetujui','Dalam Perjalanan')";
        $binds[] = get_current_user_id(); $types .= 'i';
    }
    if (!empty($queries)) {
        $sql = implode(' UNION ', $queries) . ' ORDER BY no_polisi';
        $stmt = $mysqli->prepare($sql);
        if (!empty($binds)) { $stmt->bind_param($types, ...$binds); }
        $stmt->execute();
        $res = $stmt->get_result();
        while ($row = $res->fetch_assoc()) { $vehicles[] = $row; }
        $stmt->close();
    }
    return $vehicles;
}

// Role-specific access control functions
function require_admin() {
    require_login();
    if (!can_admin()) {
        header('Location: index.php?page=403');
        exit;
    }
}

function require_operator() {
    // Legacy alias for admin-like access
    require_admin();
}

function require_user() {
    require_login();
    $role = get_current_role();
    if (!is_admin_like() && !in_array($role, ['user', 'driver'], true)) {
        header('Location: index.php?page=403');
        exit;
    }
}

// Generic role requirement function
function require_role($required_role) {
    require_login();
    $current_role = get_current_role();

    // Admin-like roles can access everything
    if (is_admin_like()) {
        return;
    }

    $current_level = get_current_role_level();

    // Handle array of roles (allow higher/equal level if possible)
    if (is_array($required_role)) {
        if (in_array($current_role, $required_role, true)) {
            return;
        }
        $min_required_level = null;
        foreach ($required_role as $rr) {
            $lvl = get_role_level_by_code($rr);
            if ($lvl !== null) {
                $min_required_level = ($min_required_level === null) ? $lvl : min($min_required_level, $lvl);
            }
        }
        if ($min_required_level !== null && $current_level !== null && $current_level <= $min_required_level) {
            return;
        }
        header('Location: index.php?page=403');
        exit;
    }

    // Single role string: compare by level when possible
    $required_level = get_role_level_by_code($required_role);
    if ($required_level !== null && $current_level !== null) {
        if ($current_level <= $required_level) {
            return;
        }
        header('Location: index.php?page=403');
        exit;
    }

    // Fallback: require exact role match
    $role = strtolower((string)$required_role);
    if ($role !== $current_role) {
        header('Location: index.php?page=403');
        exit;
    }
}

// Build upcoming reminders for a given pengguna (driver/user) and date.
function build_upcoming_items($pengguna_id, $targetDate = null) {
    global $mysqli;
    $items = [];
    $targetDate = $targetDate ?: date('Y-m-d', strtotime('+1 day'));

    // jadwal_perawatan
    if (db_table_exists('jadwal_perawatan')) {
        $cols = db_table_columns('jadwal_perawatan');
        $dateCol = in_array('tanggal_perawatan', $cols, true) ? 'tanggal_perawatan' : (in_array('jadwal_tanggal', $cols, true) ? 'jadwal_tanggal' : null);
        if ($dateCol) {
            $driverStmt = null;
            $hasDriverCol = false;
            if (db_table_exists('surat_tugas')) {
                $stCols = db_table_columns('surat_tugas');
                if (in_array('driver_id', $stCols, true)) {
                    $hasDriverCol = true;
                    $driverStmt = $mysqli->prepare("SELECT COUNT(*) c FROM surat_tugas WHERE kendaraan_id = ? AND driver_id = ? AND status IN ('Disetujui','Dalam Perjalanan')");
                }
            }

            $sqlp = "SELECT jp.id, jp.{$dateCol} AS tanggal, jp.jenis_perawatan, jp.deskripsi, k.no_reg, k.no_polisi, k.merk, k.tipe, jp.kendaraan_id, jp.teknisi_id
                     FROM jadwal_perawatan jp LEFT JOIN kendaraan k ON k.id = jp.kendaraan_id
                     WHERE DATE(jp.{$dateCol}) = ? AND (jp.status IS NULL OR LOWER(jp.status) NOT IN ('selesai','dibatalkan'))";
            $st = $mysqli->prepare($sqlp);
            if ($st) {
                 $st->bind_param('s', $targetDate);
                $st->execute();
                $res = $st->get_result();
                while ($r = $res->fetch_assoc()) {
                    $relevant = false;
                    if (!empty($r['teknisi_id']) && (int)$r['teknisi_id'] === (int)$pengguna_id) $relevant = true;
                    if (!$relevant && !empty($r['kendaraan_id'])) {
                        if (db_table_exists('kendaraan')) {
                            $st2 = $mysqli->prepare("SELECT pengguna_id FROM kendaraan WHERE id = ? LIMIT 1");
                            if ($st2) { $st2->bind_param('i', $r['kendaraan_id']); $st2->execute(); $kp = $st2->get_result()->fetch_assoc(); $st2->close(); if (!empty($kp['pengguna_id']) && (int)$kp['pengguna_id'] === (int)$pengguna_id) $relevant = true; }
                        }
                    }
                    if (!$relevant && $hasDriverCol && $driverStmt && !empty($r['kendaraan_id'])) {
                        $kid = (int)$r['kendaraan_id'];
                        $driverStmt->bind_param('ii', $kid, $pengguna_id);
                        $driverStmt->execute();
                        $dr = $driverStmt->get_result()->fetch_assoc();
                        if ((int)($dr['c'] ?? 0) > 0) $relevant = true;
                    }
                    if ($relevant) {
                        $label = trim((string)($r['no_reg'] ?: $r['no_polisi']));
                        if ($label === '') $label = trim((string)(($r['merk'] ?? '') . ' ' . ($r['tipe'] ?? '')));
                        $items[] = ['type' => 'Perawatan', 'label' => $label, 'date' => $r['tanggal'], 'note' => ($r['jenis_perawatan'] ?? '-')];
                    }
                }
                $st->close();
                if ($driverStmt) $driverStmt->close();
            }
        }
    }

    // surat_tugas
    if (db_table_exists('surat_tugas')) {
        $cols = db_table_columns('surat_tugas');
        $hasDriver = in_array('driver_id', $cols, true);
        $selectCols = "s.id, s.nomor_surat, s.tanggal_berangkat, s.tujuan, s.keperluan, s.kendaraan_id, s.pengguna_id";
        if ($hasDriver) $selectCols .= ", s.driver_id";
        $sql = "SELECT " . $selectCols . ", k.no_reg, k.no_polisi FROM surat_tugas s LEFT JOIN kendaraan k ON s.kendaraan_id = k.id WHERE DATE(s.tanggal_berangkat) = ? AND (s.status IS NULL OR LOWER(s.status) NOT IN ('selesai','dibatalkan')) AND (s.pengguna_id = ?";
        if ($hasDriver) $sql .= " OR s.driver_id = ?";
        $sql .= " OR k.pengguna_id = ?)";
        $st = $mysqli->prepare($sql);
        if ($st) {
            if ($hasDriver) { $st->bind_param('siii', $targetDate, $pengguna_id, $pengguna_id, $pengguna_id); }
            else { $st->bind_param('sii', $targetDate, $pengguna_id, $pengguna_id); }
            $st->execute();
            $res = $st->get_result();
            while ($r = $res->fetch_assoc()) {
                $label = trim((string)($r['no_reg'] ?: $r['no_polisi']));
                if ($label === '') $label = 'Surat Tugas ' . ($r['nomor_surat'] ?? $r['id']);
                $items[] = ['type' => 'Surat Tugas', 'label' => $label, 'date' => $r['tanggal_berangkat'], 'note' => ($r['tujuan'] ?? $r['keperluan'] ?? '-')];
            }
            $st->close();
        }
    }

    return $items;
}

if (!function_exists('insert_notification')) {
    function insert_notification($mysqli, $user_id, $message, $title = null, $type = 'info', $category = 'system') {
        $uid = (int)$user_id;
        if ($uid <= 0) {
            return false;
        }

        if (db_table_exists('notifikasi_advanced')) {
            $table = 'notifikasi_advanced';
        } elseif (db_table_exists('notifikasi')) {
            $table = 'notifikasi';
        } else {
            return false;
        }

        $cols = db_table_columns($table);
        $msg = $mysqli->real_escape_string((string)$message);
        $ttl = $title !== null ? $mysqli->real_escape_string((string)$title) : '';
        $typ = $mysqli->real_escape_string((string)$type);
        $cat = $mysqli->real_escape_string((string)$category);

        if ($table === 'notifikasi_advanced') {
            if (in_array('judul', $cols, true) && in_array('pesan', $cols, true)) {
                $sql = "INSERT INTO notifikasi_advanced (user_id, judul, pesan, jenis, created_at) VALUES ({$uid}, '{$ttl}', '{$msg}', '{$cat}', NOW())";
                return (bool)$mysqli->query($sql);
            }
        }

        if (in_array('message', $cols, true)) {
            $fields = ['user_id', 'message', 'created_at'];
            $values = ["{$uid}", "'{$msg}'", 'NOW()'];
            if (in_array('title', $cols, true)) {
                $fields[] = 'title';
                $values[] = "'{$ttl}'";
            }
            if (in_array('type', $cols, true)) {
                $fields[] = 'type';
                $values[] = "'{$typ}'";
            }
            if (in_array('category', $cols, true)) {
                $fields[] = 'category';
                $values[] = "'{$cat}'";
            }
            $sql = 'INSERT INTO notifikasi (' . implode(', ', $fields) . ') VALUES (' . implode(', ', $values) . ')';
            return (bool)$mysqli->query($sql);
        }

        if (in_array('pesan', $cols, true)) {
            $sql = "INSERT INTO notifikasi (user_id, pesan, created_at) VALUES ({$uid}, '{$msg}', NOW())";
            return (bool)$mysqli->query($sql);
        }

        return false;
    }
}

// Legacy function for backward compatibility (CSRF functions already in config.php)
function verify_csrf_token($token) {
    return validate_csrf_token($token);
}

// Vehicle photo helpers (shared)
if (!function_exists('get_vehicle_photo_web_path')) {
    function get_vehicle_photo_web_path($vehicleId) {
        $vehicleId = (int)$vehicleId;
        // Resolve uploads directory relative to pages root
        $baseDir = __DIR__ . '/../uploads/vehicles';
        $candidates = [
            $baseDir . '/' . $vehicleId . '.jpg',
            $baseDir . '/' . $vehicleId . '.jpeg',
            $baseDir . '/' . $vehicleId . '.png',
            $baseDir . '/' . $vehicleId . '.webp',
        ];
        foreach ($candidates as $fsPath) {
            if (file_exists($fsPath)) {
                return 'uploads/vehicles/' . basename($fsPath);
            }
        }
        return null;
    }
}

if (!function_exists('save_vehicle_photo')) {
    function save_vehicle_photo($vehicleId, $file, &$error = null) {
        $vehicleId = (int)$vehicleId;
        if (!$file || (isset($file['error']) ? $file['error'] : UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_NO_FILE) {
            return true;
        }
        if ((isset($file['error']) ? $file['error'] : UPLOAD_ERR_OK) !== UPLOAD_ERR_OK) {
            $error = 'Gagal mengunggah file (kode: ' . (int)$file['error'] . ').';
            return false;
        }
        if (!empty($file['size']) && $file['size'] > 4 * 1024 * 1024) {
            $error = 'Ukuran gambar melebihi 4MB.';
            return false;
        }
        $finfo = @finfo_open(FILEINFO_MIME_TYPE);
        $mime = $finfo ? @finfo_file($finfo, $file['tmp_name']) : null;
        if ($finfo) { @finfo_close($finfo); }
        $allowed = [ 'image/jpeg' => '.jpg', 'image/png' => '.png', 'image/webp' => '.webp' ];
        if (!$mime || !isset($allowed[$mime])) {
            $error = 'Format gambar tidak didukung. Gunakan JPG, PNG, atau WEBP.';
            return false;
        }
        $ext = $allowed[$mime];
        $baseDir = __DIR__ . '/../uploads/vehicles';
        if (!is_dir($baseDir)) { @mkdir($baseDir, 0777, true); }
        foreach (['.jpg','.jpeg','.png','.webp'] as $oldExt) {
            $old = $baseDir . '/' . $vehicleId . $oldExt;
            if (file_exists($old)) { @unlink($old); }
        }
        $dest = $baseDir . '/' . $vehicleId . $ext;
        if (!@move_uploaded_file($file['tmp_name'], $dest)) {
            if (!@copy($file['tmp_name'], $dest)) {
                $error = 'Gagal menyimpan gambar kendaraan.';
                return false;
            }
        }
        return true;
    }
}
?>
