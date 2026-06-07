<?php
require_once 'includes/auth.php';
require_login();

$current_user_id = get_current_user_id();
$current_role = get_current_role();

// Determine which user's profile to display. Admin-like roles may pass ?id= to view another user.
$view_user_id = isset($_GET['id']) && is_numeric($_GET['id']) ? (int)$_GET['id'] : null;
$profile_user_id = ($current_role !== 'user' && $view_user_id) ? $view_user_id : $current_user_id;

$msg = '';

// Resolve profile identifiers robustly. Prefer `user_account.id` for the current session
// to avoid accidental numeric collisions between account.id and pengguna.id.
$current_account_id = get_current_account_id();
$current_pengguna_id = get_current_user_id();

$profile_account_id = null; // user_account.id
$profile_pengguna_id = null; // pengguna.id

// If admin-like passed ?id=, interpret that id as the requested profile (try account id first)
if ($view_user_id !== null && $current_role !== 'user') {
    $v = (int)$view_user_id;
    $st = $mysqli->prepare("SELECT id, pengguna_id FROM user_account WHERE id = ? LIMIT 1");
    if ($st) {
        $st->bind_param('i', $v);
        $st->execute();
        $r = $st->get_result()->fetch_assoc();
        $st->close();
        if ($r) {
            $profile_account_id = (int)$r['id'];
            $profile_pengguna_id = !empty($r['pengguna_id']) ? (int)$r['pengguna_id'] : null;
        }
    }
    if (is_null($profile_account_id)) {
        // maybe the id was a pengguna.id
        $st2 = $mysqli->prepare("SELECT id FROM pengguna WHERE id = ? LIMIT 1");
        if ($st2) {
            $st2->bind_param('i', $v);
            $st2->execute();
            $r2 = $st2->get_result()->fetch_assoc();
            $st2->close();
            if ($r2) {
                $profile_pengguna_id = $v;
                $profile_account_id = get_user_account_id_for_pengguna($v);
            }
        }
    }
} else {
    // Default: show current session's profile — prefer account id when available
    $profile_account_id = $current_account_id;
    $profile_pengguna_id = $current_pengguna_id;
    if (empty($profile_account_id) && !empty($profile_pengguna_id)) {
        $profile_account_id = get_user_account_id_for_pengguna($profile_pengguna_id);
    }
}

// For fallbacks below which use a single numeric id, keep a requested id (prefer account id)
$requested_id = $profile_account_id ?? $profile_pengguna_id ?? (int)$profile_user_id;

// Handle form submission for profile update
if ($_POST && isset($_POST['action']) && $_POST['action'] === 'update_profile') {
    // Disallow users with role 'user' from performing updates
    if ($current_role === 'user') {
        $msg = '<div class="alert alert-danger">Anda tidak memiliki izin untuk mengubah profil.</div>';
    } else {
        if (!validate_csrf_token($_POST['csrf_token'] ?? '')) {
            $msg = '<div class="alert alert-danger">Token keamanan tidak valid!</div>';
        } else {
            $nama = trim($_POST['nama']);
            $email = trim($_POST['email']);
            $no_hp = trim($_POST['no_hp']);
            $alamat = trim($_POST['alamat']);
            
            // Update user profile
            // Perbarui data: simpan di tabel pengguna bila tersedia (pengguna adalah sumber kebenaran untuk nama/no_hp/email/alamat)
            // Use the profile being viewed/edited (admins may edit other users via ?id=)
            $stmt2 = $mysqli->prepare("SELECT pengguna_id FROM user_account WHERE id = ? OR pengguna_id = ? LIMIT 1");
            $bind_account = $profile_account_id ?? 0;
            $bind_pengguna = $profile_pengguna_id ?? $requested_id;
            $stmt2->bind_param('ii', $bind_account, $bind_pengguna);
            $stmt2->execute();
            $res2 = $stmt2->get_result()->fetch_assoc();
            $stmt2->close();

            $ok = true;
            if (!empty($res2['pengguna_id'])) {
                $pid = (int)$res2['pengguna_id'];
                // Perbarui nama lengkap, email, no_hp, alamat pada tabel pengguna
                $stmt3 = $mysqli->prepare("UPDATE pengguna SET nama_lengkap = ?, email = ?, no_hp = ?, alamat = ? WHERE id = ?");
                $stmt3->bind_param('ssssi', $nama, $email, $no_hp, $alamat, $pid);
                $ok = $stmt3->execute();
                $stmt3->close();
            } else {
                // Jika tidak ada record pengguna, hanya tuliskan pesan sukses karena tidak ada data pengguna untuk diupdate
                $ok = true;
            }

            if ($ok) {
                $msg = '<div class="alert alert-success">Profil berhasil diperbarui!</div>';
            } else {
                $msg = '<div class="alert alert-danger">Error saat memperbarui profil</div>';
            }
        }
    }
}

// Get current user profile data (prefer matching user_account.id when available)
$selectBase = "SELECT ua.*, p.nama_lengkap AS nama, p.no_hp, p.email AS email, p.alamat, p.nrp_nip AS nrp, p.pangkat, p.jabatan, COALESCE(p.kesatuan, '') AS satuan, ua.created_at, ua.last_login, ua.status, ua.username, ua.role_id, ua.pengguna_id, COALESCE(r.nama_role, '') AS role
        FROM user_account ua
        LEFT JOIN pengguna p ON ua.pengguna_id = p.id
        LEFT JOIN role r ON ua.role_id = r.id";

if (!empty($profile_account_id)) {
    $stmt = $mysqli->prepare($selectBase . " WHERE ua.id = ? LIMIT 1");
    $stmt->bind_param('i', $profile_account_id);
} elseif (!empty($profile_pengguna_id)) {
    $stmt = $mysqli->prepare($selectBase . " WHERE ua.pengguna_id = ? LIMIT 1");
    $stmt->bind_param('i', $profile_pengguna_id);
} else {
    // Fallback: try to use current session account id, otherwise use requested id for both fields
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
// Grab last login event from log_aktivitas (if pengguna_id exists). We search activity_type for 'login' (case-insensitive).
$last_login_activity = null;
if (!empty($user['pengguna_id'])) {
    $lid = (int)$user['pengguna_id'];
    $like = '%login%';
    $stmtL = $mysqli->prepare("SELECT activity_type, description, created_at FROM log_aktivitas WHERE user_id = ? AND LOWER(activity_type) LIKE ? ORDER BY created_at DESC LIMIT 1");
    if ($stmtL) {
        $stmtL->bind_param('is', $lid, $like);
        $stmtL->execute();
        $last_login_activity = $stmtL->get_result()->fetch_assoc();
        $stmtL->close();
    }
}
// Get user statistics
$stats = [];

if ($current_role === 'user') {
    // Get peminjaman statistics for user
    $pengguna_id = $user['pengguna_id'] ?? 0;
    
        // Some databases use different status casing/labels. Use a tolerant query that checks multiple variants.
        $stats_query = $mysqli->prepare(
            "SELECT 
                COUNT(*) as total_peminjaman,
                SUM(CASE WHEN LOWER(status) IN ('pending','menunggu','pending_approval') THEN 1 ELSE 0 END) as pending,
                SUM(CASE WHEN LOWER(status) IN ('approved','disetujui') THEN 1 ELSE 0 END) as approved,
                SUM(CASE WHEN LOWER(status) IN ('ongoing','dipinjam','in_progress') THEN 1 ELSE 0 END) as ongoing,
                SUM(CASE WHEN LOWER(status) IN ('completed','selesai') THEN 1 ELSE 0 END) as completed
            FROM peminjaman_kendaraan 
            WHERE peminjam_id = ?"
        );
        $stats_query->bind_param('i', $pengguna_id);
    $stats_query->execute();
    $peminjaman_stats = $stats_query->get_result()->fetch_assoc();
    $stats_query->close();
    
    // Get riwayat pemakaian count (schema-aware: some installations use user_id, others pengguna_id)
    $riwayat_count = 0;
    if (function_exists('db_table_exists') && db_table_exists('riwayat_pemakaian')) {
        $rp_cols = db_table_columns('riwayat_pemakaian');
        if (in_array('user_id', $rp_cols, true)) {
            $bind_val = $profile_account_id ?: get_user_account_id_for_pengguna($profile_pengguna_id ?? $requested_id);
            if ($bind_val) {
                $st_rp = $mysqli->prepare("SELECT COUNT(*) as total FROM riwayat_pemakaian WHERE user_id = ?");
                if ($st_rp) {
                    $st_rp->bind_param('i', $bind_val);
                    $st_rp->execute();
                    $riwayat_count = (int)$st_rp->get_result()->fetch_assoc()['total'];
                    $st_rp->close();
                }
            }
        } elseif (in_array('pengguna_id', $rp_cols, true)) {
            $bind_val = $profile_pengguna_id ?? $requested_id;
            $st_rp = $mysqli->prepare("SELECT COUNT(*) as total FROM riwayat_pemakaian WHERE pengguna_id = ?");
            if ($st_rp) {
                $st_rp->bind_param('i', $bind_val);
                $st_rp->execute();
                $riwayat_count = (int)$st_rp->get_result()->fetch_assoc()['total'];
                $st_rp->close();
            }
        }
    }

    $stats['peminjaman'] = $peminjaman_stats;
    $stats['riwayat_count'] = $riwayat_count;
    
} else {
    // Get general statistics for admin-like roles
    $stats['kendaraan_total'] = $mysqli->query("SELECT COUNT(*) as total FROM kendaraan")->fetch_assoc()['total'];
    // Determine users_total safely: some installations use user_account.role, others use role_id with a role table
    $users_total = 0;
    try {
        $col = $mysqli->query("SHOW COLUMNS FROM user_account LIKE 'role'");
        if ($col && $col->num_rows > 0) {
            $users_total = (int)$mysqli->query("SELECT COUNT(*) as total FROM user_account WHERE role != 'admin'")->fetch_assoc()['total'];
            $col->free_result();
        } else {
            // Check for role_id foreign key
            $col2 = $mysqli->query("SHOW COLUMNS FROM user_account LIKE 'role_id'");
            if ($col2 && $col2->num_rows > 0) {
                // Try to resolve admin id from role table
                $adminRow = $mysqli->query("SELECT id FROM role WHERE UPPER(kode_role) = 'ADMIN' LIMIT 1");
                if ($adminRow && $adminRow->num_rows > 0) {
                    $admin_id = (int)$adminRow->fetch_assoc()['id'];
                    $users_total = (int)$mysqli->query("SELECT COUNT(*) as total FROM user_account WHERE role_id != " . $admin_id)->fetch_assoc()['total'];
                } else {
                    // Fallback: count all users if we can't detect admin id
                    $users_total = (int)$mysqli->query("SELECT COUNT(*) as total FROM user_account")->fetch_assoc()['total'];
                }
                if ($col2) $col2->free_result();
            } else {
                // No role columns — fall back to total user count
                $users_total = (int)$mysqli->query("SELECT COUNT(*) as total FROM user_account")->fetch_assoc()['total'];
            }
        }
    } catch (mysqli_sql_exception $e) {
        // On any error, fallback to total user count to avoid fatal
        $users_total = (int)$mysqli->query("SELECT COUNT(*) as total FROM user_account")->fetch_assoc()['total'];
    }
    $stats['users_total'] = $users_total;
    $stats['peminjaman_aktif'] = $mysqli->query("SELECT COUNT(*) as total FROM peminjaman_kendaraan WHERE status IN ('Approved', 'Ongoing')")->fetch_assoc()['total'];
}

// Get recent activities for user
$activities = [];
if ($current_role === 'user') {
    // Some installations do not have user_activity at all; keep the profile page usable.
    if (function_exists('db_table_exists') && !db_table_exists('user_activity')) {
        $activities = [];
    } else {
        // Ensure user_activity has the columns we expect to avoid unknown-column errors
        $columns = [];
        $cols_res = $mysqli->query("SHOW COLUMNS FROM user_activity");
        if ($cols_res) {
            while ($c = $cols_res->fetch_assoc()) {
                $columns[] = $c['Field'];
            }
        }

        // Build select parts with safe fallbacks and ensure alias 'activity_description' exists
        $select_parts = [];
        $select_parts[] = in_array('activity_type', $columns, true) ? 'activity_type' : "'' AS activity_type";

        if (in_array('activity_description', $columns, true)) {
            $select_parts[] = 'activity_description AS activity_description';
        } elseif (in_array('description', $columns, true)) {
            $select_parts[] = 'description AS activity_description';
        } else {
            $select_parts[] = "'' AS activity_description";
        }

        $select_parts[] = in_array('created_at', $columns, true) ? 'created_at' : 'NOW() AS created_at';

        if (in_array('user_id', $columns, true)) {
            $sql = "SELECT " . implode(', ', $select_parts) . " FROM user_activity WHERE user_id = ? ORDER BY created_at DESC LIMIT 10";
            $activity_query = $mysqli->prepare($sql);
            if ($activity_query) {
                $activity_bind = $profile_account_id ?: get_user_account_id_for_pengguna($profile_pengguna_id ?? $requested_id);
                $activity_query->bind_param('i', $activity_bind);
                $activity_query->execute();
                $activities = $activity_query->get_result()->fetch_all(MYSQLI_ASSOC);
                $activity_query->close();
            }
        }
    }
}
?>

<div class="profile-page">
    <div class="page-header gradient-header text-white p-4 mb-4 rounded">
        <div class="row align-items-center">
            <div class="col-md-8">
                <h1><i class="fas fa-user me-2"></i>Profil Saya</h1>
            </div>
            <div class="col-md-4 text-end">
                <span class="badge bg-light text-dark fs-6 px-3 py-2">
                    <i class="fas fa-shield-alt me-1"></i>
                    <?= ucfirst($current_role) ?>
                </span>
            </div>
        </div>
    </div>

    <?= $msg ?>

    <div class="row">
        <!-- Profile Information -->
        <div class="col-lg-8 mb-4">
            <div class="card">
                <div class="card-header">
                    <h5 class="mb-0"><i class="fas fa-user-edit me-2"></i>Informasi Pribadi</h5>
                </div>
                <div class="card-body">
                    <!-- Profile update form -->
                    <form method="post" action="?<?= isset($view_user_id) ? 'id=' . (int)$view_user_id : '' ?>">
                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label class="form-label">Nama Lengkap <span class="text-danger"></span></label>
                                <input type="text" name="nama" class="form-control" value="<?= htmlspecialchars($user['nama'] ?? '') ?>" required <?= $current_role !== 'admin' ? 'readonly' : '' ?>>
                            </div>
                            
                            <div class="col-md-6 mb-3">
                                <label class="form-label">Email <span class="text-danger"></span></label>
                                <input type="email" name="email" class="form-control" value="<?= htmlspecialchars($user['email'] ?? '') ?>" required <?= $current_role !== 'admin' ? 'readonly' : '' ?> >
                            </div>
                            
                <?php if (!empty($user['pengguna_id'])): ?>
                                <div class="col-md-6 mb-3">
                                    <label class="form-label">No. HP</label>
                    <input type="text" name="no_hp" class="form-control" value="<?= htmlspecialchars($user['no_hp'] ?? '') ?>" <?= $current_role !== 'admin' ? 'readonly' : '' ?>>
                                </div>
                                
                                <div class="col-md-6 mb-3">
                                    <label class="form-label">NRP</label>
                                    <input type="text" class="form-control" value="<?= htmlspecialchars($user['nrp'] ?? '') ?>" readonly>
                                    <small class="text-muted">NRP tidak dapat diubah</small>
                                </div>
                                
                                <div class="col-md-6 mb-3">
                                    <label class="form-label">Pangkat</label>
                                    <input type="text" class="form-control" value="<?= htmlspecialchars($user['pangkat'] ?? '') ?>" readonly>
                                </div>
                                
                                <div class="col-md-6 mb-3">
                                    <label class="form-label">Jabatan</label>
                                    <input type="text" class="form-control" value="<?= htmlspecialchars($user['jabatan'] ?? '') ?>" readonly>
                                </div>
                                
                                <div class="col-md-6 mb-3">
                                    <label class="form-label">Satuan</label>
                                    <input type="text" class="form-control" value="<?= htmlspecialchars($user['satuan'] ?? '') ?>" readonly>
                                </div>
                                
                                <div class="col-md-6 mb-3">
                                    <label class="form-label">Alamat</label>
                                    <textarea name="alamat" class="form-control" rows="3" <?= $current_role !== 'admin' ? 'readonly' : '' ?>><?= htmlspecialchars($user['alamat'] ?? '') ?></textarea>
                                </div>
                            <?php endif; ?>
                            
                            <div class="col-md-6 mb-3">
                                <label class="form-label">Username</label>
                                <input type="text" class="form-control" value="<?= htmlspecialchars($user['username'] ?? '') ?>" readonly>
                                <small class="text-muted">Username tidak dapat diubah</small>
                            </div>
                            
                            <div class="col-md-6 mb-3">
                                <label class="form-label">Role</label>
                                <input type="text" class="form-control" value="<?= ucfirst($user['role'] ?? '') ?>" readonly>
                            </div>
                        </div>
                        <?php if ($current_role === 'admin'): ?>
                            <div class="mt-3 text-end">
                                <input type="hidden" name="action" value="update_profile">
                                <input type="hidden" name="csrf_token" value="<?= htmlspecialchars(generate_csrf_token()) ?>">
                                <button type="submit" class="btn btn-primary">Simpan Perubahan</button>
                            </div>
                        <?php endif; ?>
                    </form>
                </div>
            </div>
        </div>

        <!-- Statistics & Activities -->
        <div class="col-lg-4 mb-4">
            <!-- Statistics -->
            <div class="card mb-4">
                <div class="card-header">
                    <h5 class="mb-0"><i class="fas fa-chart-bar me-2"></i>Statistik</h5>
                </div>
                <div class="card-body">
                    <?php if ($current_role === 'user'): ?>
                        <div class="stat-item mb-3">
                            <div class="d-flex justify-content-between align-items-center">
                                <span>Total Peminjaman</span>
                                <span class="badge bg-primary"><?= $stats['peminjaman']['total_peminjaman'] ?></span>
                            </div>
                        </div>
                        <div class="stat-item mb-3">
                            <div class="d-flex justify-content-between align-items-center">
                                <span>Pending</span>
                                <span class="badge bg-warning"><?= $stats['peminjaman']['pending'] ?></span>
                            </div>
                        </div>
                        <div class="stat-item mb-3">
                            <div class="d-flex justify-content-between align-items-center">
                                <span>Disetujui</span>
                                <span class="badge bg-info"><?= $stats['peminjaman']['approved'] ?></span>
                            </div>
                        </div>
                        <div class="stat-item mb-3">
                            <div class="d-flex justify-content-between align-items-center">
                                <span>Sedang Berlangsung</span>
                                <span class="badge bg-success"><?= $stats['peminjaman']['ongoing'] ?></span>
                            </div>
                        </div>
                        <div class="stat-item mb-3">
                            <div class="d-flex justify-content-between align-items-center">
                                <span>Selesai</span>
                                <span class="badge bg-secondary"><?= $stats['peminjaman']['completed'] ?></span>
                            </div>
                        </div>
                        <div class="stat-item">
                            <div class="d-flex justify-content-between align-items-center">
                                <span>Riwayat Pemakaian</span>
                                <span class="badge bg-dark"><?= $stats['riwayat_count'] ?></span>
                            </div>
                        </div>
                    <?php else: ?>
                        <div class="stat-item mb-3">
                            <div class="d-flex justify-content-between align-items-center">
                                <span>Total Kendaraan</span>
                                <span class="badge bg-primary"><?= $stats['kendaraan_total'] ?></span>
                            </div>
                        </div>
                        <div class="stat-item mb-3">
                            <div class="d-flex justify-content-between align-items-center">
                                <span>Total Users</span>
                                <span class="badge bg-info"><?= $stats['users_total'] ?></span>
                            </div>
                        </div>
                        <div class="stat-item">
                            <div class="d-flex justify-content-between align-items-center">
                                <span>Peminjaman Aktif</span>
                                <span class="badge bg-success"><?= $stats['peminjaman_aktif'] ?></span>
                            </div>
                        </div>
                    <?php endif; ?>
                </div>
            </div>

            <!-- Recent Activities (for users only) -->
            <!-- <?php if ($current_role === 'user' && !empty($activities)): ?>
                <div class="card">
                    <div class="card-header">
                        <h5 class="mb-0"><i class="fas fa-history me-2"></i>Aktivitas Terbaru</h5>
                    </div>
                    <div class="card-body">
                        <?php foreach ($activities as $activity): ?>
                            <div class="activity-item mb-3 pb-3 border-bottom">
                                <div class="d-flex align-items-start">
                                    <div class="activity-icon me-3">
                                        <i class="fas fa-circle text-primary" style="font-size: 0.5rem;"></i>
                                    </div>
                                    <div class="flex-grow-1">
                                        <div class="activity-type fw-bold"><?= htmlspecialchars($activity['activity_type']) ?></div>
                                        <div class="activity-desc text-muted small"><?= htmlspecialchars($activity['activity_description']) ?></div>
                                        <div class="activity-time text-muted small">
                                            <i class="fas fa-clock me-1"></i>
                                            <?= date('d/m/Y H:i', strtotime($activity['created_at'])) ?>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </div>
            <?php endif; ?> -->

            <!-- Account Info -->
            <div class="card">
                <div class="card-header">
                    <h5 class="mb-0"><i class="fas fa-info-circle me-2"></i>Informasi Akun</h5>
                </div>
                <div class="card-body">
                    <?php
                        // Safe date formatting helper
                        function safe_format_date($d) {
                            if (empty($d)) return 'Tidak tersedia';
                            $ts = strtotime($d);
                            return $ts ? date('d/m/Y H:i', $ts) : 'Tidak tersedia';
                        }

                        $status_raw = $user['status'] ?? '';
                        $status_label = $status_raw ?: 'Tidak Diketahui';
                        $status_norm = strtolower(trim($status_raw));
                        $badge_class = in_array($status_norm, ['aktif','active','1','on']) ? 'success' : (in_array($status_norm, ['nonaktif','inactive','0','off']) ? 'danger' : 'secondary');
                    ?>
                    <div class="info-item mb-2">
                        <strong>Terdaftar:</strong><br>
                        <small class="text-muted"><?= safe_format_date($user['created_at'] ?? '') ?></small>
                    </div>

                    <?php if (!empty($last_login_activity) && !empty($last_login_activity['created_at'])): ?>
                        <div class="info-item mb-2">
                            <strong>Terakhir Login:</strong><br>
                            <small class="text-muted"><?= safe_format_date($last_login_activity['created_at']) ?> &mdash; <?= htmlspecialchars($last_login_activity['activity_type']) ?></small>
                        </div>
                    <?php endif; ?>
                    <div class="info-item">
                        <strong>Status Akun:</strong><br>
                        <span class="badge bg-<?= $badge_class ?>">
                            <?= htmlspecialchars($status_label) ?>
                        </span>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<style>
.gradient-header {
    background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
}

.profile-page .card {
    border: none;
    box-shadow: 0 0 20px rgba(0,0,0,0.1);
    border-radius: 10px;
}

.profile-page .card-header {
    background: linear-gradient(135deg, #53adf6ff 0%, #5f5ff9ff 100%);
    color: white;
    border-radius: 10px 10px 0 0 !important;
}

.stat-item {
    padding: 0.5rem 0;
    border-bottom: 1px solid #eee;
}

.stat-item:last-child {
    border-bottom: none;
}

.activity-item:last-child {
    border-bottom: none !important;
    margin-bottom: 0 !important;
    padding-bottom: 0 !important;
}

.activity-icon {
    margin-top: 0.25rem;
}

.info-item {
    padding: 0.5rem 0;
}

.form-control:read-only {
    background-color: #f8f9fa;
}

@media (max-width: 768px) {
    .profile-page .row .col-lg-8,
    .profile-page .row .col-lg-4 {
        margin-bottom: 1rem;
    }
}
</style>


<style>
.profile-avatar {
    position: relative;
}

.stat-item {
    padding: 0.5rem;
}

.stat-value {
    font-size: 1.5rem;
    font-weight: bold;
    margin-bottom: 0.25rem;
}

.stat-label {
    font-size: 0.75rem;
    color: #6c757d;
    text-transform: uppercase;
    letter-spacing: 0.5px;
}

.info-value {
    background: #f8f9fa;
    border: 1px solid #e9ecef;
    border-radius: 0.375rem;
    padding: 0.75rem;
    min-height: 2.5rem;
    display: flex;
    align-items: center;
    color: #495057;
    font-weight: 500;
}

.info-value:empty::before {
    content: 'Belum diisi';
    color: #6c757d;
    font-style: italic;
}

.rounded-top-0 {
    border-top-left-radius: 0 !important;
    border-top-right-radius: 0 !important;
}

.nav-tabs .nav-link {
    border-bottom: 2px solid transparent;
    color: #6c757d;
}

.nav-tabs .nav-link.active {
    border-bottom-color: #0d6efd;
    color: #0d6efd;
    font-weight: 600;
}

.nav-tabs .nav-link:hover {
    border-bottom-color: #0d6efd;
    color: #0d6efd;
}
</style>


