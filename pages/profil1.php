<?php
require_once 'includes/auth.php';
require_login();

$current_user_id = get_current_user_id();
$current_role = get_current_role();

$msg = '';

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
            $stmt2 = $mysqli->prepare("SELECT pengguna_id FROM user_account WHERE id = ? LIMIT 1");
            $stmt2->bind_param('i', $current_user_id);
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

// Get current user profile data
if ($current_role === 'user') {
    // Ambil data user + pengguna. Perhatikan: kolom nama pada pengguna adalah nama_lengkap, nrp disimpan di nrp_nip
    $stmt = $mysqli->prepare("SELECT ua.*, p.nama_lengkap AS nama, p.no_hp, p.alamat, p.nrp_nip AS nrp, p.pangkat, p.jabatan, COALESCE(kes.nama_kesatuan, '') AS satuan, ua.created_at, ua.last_login, ua.status, ua.username, ua.role_id, ua.pengguna_id
        FROM user_account ua
        LEFT JOIN pengguna p ON ua.pengguna_id = p.id
        LEFT JOIN kesatuan kes ON p.kesatuan_id = kes.id
        WHERE ua.id = ?");
} else {
    // Provide safe defaults when pengguna data is not applicable
    $stmt = $mysqli->prepare("SELECT ua.*, '' as nama, '' as no_hp, '' as alamat, '' as nrp, '' as pangkat, '' as jabatan, '' as satuan, ua.created_at, ua.last_login, ua.status, ua.username, ua.role_id, ua.pengguna_id
        FROM user_account ua
        WHERE ua.id = ?");
}

$stmt->bind_param('i', $current_user_id);
$stmt->execute();
$user = $stmt->get_result()->fetch_assoc();
$stmt->close();
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
    
    // Get riwayat pemakaian count
    $riwayat_query = $mysqli->prepare("SELECT COUNT(*) as total FROM riwayat_pemakaian WHERE user_id = ?");
    $riwayat_query->bind_param('i', $current_user_id);
    $riwayat_query->execute();
    $riwayat_count = $riwayat_query->get_result()->fetch_assoc()['total'];
    $riwayat_query->close();
    
    $stats['peminjaman'] = $peminjaman_stats;
    $stats['riwayat_count'] = $riwayat_count;
    
} else {
    // Get general statistics for operator/admin
    $stats['kendaraan_total'] = $mysqli->query("SELECT COUNT(*) as total FROM kendaraan")->fetch_assoc()['total'];
    $stats['users_total'] = $mysqli->query("SELECT COUNT(*) as total FROM user_account WHERE role != 'admin'")->fetch_assoc()['total'];
    $stats['peminjaman_aktif'] = $mysqli->query("SELECT COUNT(*) as total FROM peminjaman_kendaraan WHERE status IN ('Approved', 'Ongoing')")->fetch_assoc()['total'];
}

// Get recent activities for user
$activities = [];
// Check if the current role is user
// Guard recent-activities query by checking user_activity columns first
if ($current_role === 'user') {
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
    $select_parts[] = in_array('activity_type', $columns) ? 'activity_type' : "'' AS activity_type";

    if (in_array('activity_description', $columns)) {
        $select_parts[] = 'activity_description AS activity_description';
    } elseif (in_array('description', $columns)) {
        $select_parts[] = 'description AS activity_description';
    } else {
        $select_parts[] = "'' AS activity_description";
    }

    $select_parts[] = in_array('created_at', $columns) ? 'created_at' : 'NOW() AS created_at';

    if (in_array('user_id', $columns)) {
        $sql = "SELECT " . implode(', ', $select_parts) . " FROM user_activity WHERE user_id = ? ORDER BY created_at DESC LIMIT 10";
        $activity_query = $mysqli->prepare($sql);
        if ($activity_query) {
            $activity_query->bind_param('i', $current_user_id);
            $activity_query->execute();
            $activities = $activity_query->get_result()->fetch_all(MYSQLI_ASSOC);
            $activity_query->close();
        }
    } else {
        // No user_id column — can't fetch per-user activities
        $activities = [];
    }
}
?>

<div class="profile-page">
    <div class="page-header gradient-header text-white p-4 mb-4 rounded">
        <div class="row align-items-center">
            <div class="col-md-8">
                <h1><i class="fas fa-user me-2"></i>Profil Saya</h1>
                <p class="mb-0">Informasi pribadi dan aktivitas Anda</p>
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
                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label class="form-label">Nama Lengkap <span class="text-danger"></span></label>
                                <input type="text" name="nama" class="form-control" value="<?= htmlspecialchars($user['nama'] ?? '') ?>" required <?= $current_role === 'user' ? 'readonly' : '' ?>>
                            </div>
                            
                            <div class="col-md-6 mb-3">
                                <label class="form-label">Email <span class="text-danger"></span></label>
                                <input type="email" name="email" class="form-control" value="<?= htmlspecialchars($user['email'] ?? '') ?>" required <?= $current_role === 'user' ? 'readonly' : '' ?> >
                            </div>
                            
                            <?php if ($current_role === 'user' && $user['pengguna_id']): ?>
                                <div class="col-md-6 mb-3">
                                    <label class="form-label">No. HP</label>
                                    <input type="text" name="no_hp" class="form-control" value="<?= htmlspecialchars($user['no_hp'] ?? '') ?>" <?= $current_role === 'user' ? 'readonly' : '' ?>>
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
                                    <textarea name="alamat" class="form-control" rows="3" <?= $current_role === 'user' ? 'readonly' : '' ?>><?= htmlspecialchars($user['alamat'] ?? '') ?></textarea>
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
                        <?php if ($current_role !== 'user'): ?>
                            <div class="mt-3 text-end">
                                <input type="hidden" name="action" value="update_profile">
                                <input type="hidden" name="csrf_token" value="<?= htmlspecialchars(generate_csrf_token()) ?>">
                                <button type="submit" class="btn btn-primary">Simpan Perubahan</button>
                            </div>
                        <?php endif; ?>
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
            <?php if ($current_role === 'user' && !empty($activities)): ?>
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
            <?php endif; ?>

            <!-- Account Info -->
            <div class="card">
                <div class="card-header">
                    <h5 class="mb-0"><i class="fas fa-info-circle me-2"></i>Informasi Akun</h5>
                </div>
                <div class="card-body">
                    <div class="info-item mb-2">
                        <strong>Terdaftar:</strong><br>
                        <small class="text-muted"><?= date('d/m/Y H:i', strtotime($user['created_at'] ?? '')) ?></small>
                    </div>
                    <div class="info-item mb-2">
                        <strong>Terakhir Login:</strong><br>
                        <small class="text-muted"><?= $user['last_login'] ? date('d/m/Y H:i', strtotime($user['last_login'])) : 'Belum pernah login' ?></small>
                    </div>
                    <div class="info-item">
                        <strong>Status Akun:</strong><br>
                        <span class="badge bg-<?= $user['status'] === 'Aktif' ? 'success' : 'danger' ?>">
                            <?= $user['status'] ?? 'Tidak Diketahui' ?>
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
    background: linear-gradient(135deg, #FF6B6B 0%, #FF8E53 100%);
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


