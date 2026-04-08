<?php
require_once __DIR__ . '/../includes/auth.php';
require_login();

if (!can_operate()) {
    header('Location: index.php?page=403');
    exit;
}

$user_id = (int)($_GET['user_id'] ?? 0);
if (!$user_id) {
    echo '<div class="alert alert-danger">ID user tidak valid</div>';
    return;
}

// Fetch user with role
$stmt = $mysqli->prepare("SELECT p.nama_lengkap as nama, COALESCE(r.nama_role, '') as role FROM pengguna p LEFT JOIN user_account ua ON p.id = ua.pengguna_id LEFT JOIN role r ON ua.role_id = r.id WHERE p.id = ?");
$stmt->bind_param('i', $user_id);
$stmt->execute();
$user = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$user) {
    echo '<div class="alert alert-warning">User tidak ditemukan</div>';
    return;
}

// fetch logs and materialize to array
$stmt = $mysqli->prepare("SELECT * FROM log_aktivitas WHERE user_id = ? ORDER BY created_at DESC LIMIT 100");
$stmt->bind_param('i', $user_id);
$stmt->execute();
$result_set = $stmt->get_result();
$stmt->close();
$logs = [];
while ($r = $result_set->fetch_assoc()) {
    $logs[] = $r;
}

function getActionBadgeColor($action) {
    $colors = [
        'LOGIN' => 'success',
        'LOGOUT' => 'secondary',
        'CREATE' => 'primary',
        'UPDATE' => 'warning',
        'DELETE' => 'danger',
        'VIEW' => 'info',
        'EXPORT' => 'info',
        'COMPLETE_MAINTENANCE' => 'success',
        'UPDATE_MAINTENANCE_STATUS' => 'warning',
        'ADD_BBM_LOG' => 'primary',
        'UPDATE_VEHICLE' => 'warning',
        'UPDATE_DOCUMENT' => 'info'
    ];
    foreach ($colors as $key => $color) {
        if (strpos($action, $key) !== false) return $color;
    }
    return 'secondary';
}

?>

<div class="content-wrapper">
    <div class="page-header">
        <h3 class="page-title"><i class="fas fa-user"></i> Detail Log Aktivitas - <?= htmlspecialchars($user['nama']) ?></h3>
        <p class="page-description">Menampilkan aktivitas lengkap untuk pengguna</p>
    </div>

    <div class="content">
        <div class="card">
            <div class="card-body">
                <div class="user-summary mb-4">
                    <div class="row">
                        <div class="col-md-4">
                            <div class="card bg-light">
                                <div class="card-body text-center">
                                    <div class="user-avatar-large mb-3">
                                        <div class="bg-<?= strtolower($user['role'] ?? '') == 'admin' ? 'danger' : (strtolower($user['role'] ?? '') == 'operator' ? 'warning' : 'info') ?> text-white d-flex align-items-center justify-content-center" 
                                             style="width:80px;height:80px;border-radius:50%;margin:0 auto;">
                                            <i class="fas fa-user fa-2x"></i>
                                        </div>
                                    </div>
                                    <h5 class="text-primary"><?= htmlspecialchars($user['nama']) ?></h5>
                                    <span class="badge bg-<?= strtolower($user['role'] ?? '') == 'admin' ? 'danger' : (strtolower($user['role'] ?? '') == 'operator' ? 'warning' : 'info') ?> text-white badge-lg">
                                        <?= strtoupper($user['role']) ?>
                                    </span>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-8">
                            <div class="row">
                                <div class="col-md-4">
                                    <div class="card border-primary mb-3">
                                        <div class="card-body text-center">
                                            <h4 class="text-primary"><?= $logs->num_rows ?></h4>
                                            <p class="mb-0">Total Aktivitas</p>
                                        </div>
                                    </div>
                                </div>
                                <div class="col-md-4">
                                    <div class="card border-success mb-3">
                                        <div class="card-body text-center">
                                            <?php 
                                            $unique_actions = [];
                                            foreach ($logs as $row) {
                                                $unique_actions[$row['activity_type']] = true;
                                            }
                                            ?>
                                            <h4 class="text-success"><?= count($unique_actions) ?></h4>
                                            <p class="mb-0">Jenis Aksi</p>
                                        </div>
                                    </div>
                                </div>
                                <div class="col-md-4">
                                    <div class="card border-info mb-3">
                                        <div class="card-body text-center">
                                            <?php 
                                            $today_count = 0;
                                            foreach ($logs as $row) {
                                                if (date('Y-m-d', strtotime($row['created_at'])) == date('Y-m-d')) $today_count++;
                                            }
                                            ?>
                                            <h4 class="text-info"><?= $today_count ?></h4>
                                            <p class="mb-0">Hari Ini</p>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="activity-logs">
                    <h5 class="mb-3"><i class="fas fa-clipboard-list text-primary"></i> Log Aktivitas Lengkap</h5>
                    <?php if (count($logs) > 0): ?>
                        <div class="table-responsive">
                            <table class="table table-bordered table-hover">
                                <thead class="bg-primary text-white">
                                    <tr>
                                        <th width="15%">Waktu</th>
                                        <th width="20%">Aksi</th>
                                        <th width="35%">Deskripsi</th>
                                        <th width="15%">IP Address</th>
                                        <th width="15%">User Agent</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php 
                                    $current_date = '';
                                    foreach ($logs as $log):
                                        $log_date = date('d F Y', strtotime($log['created_at']));
                                        if ($log_date !== $current_date):
                                            $current_date = $log_date;
                                    ?>
                                    <tr class="table-secondary">
                                        <td colspan="5"><strong><i class="fas fa-calendar"></i> <?= $log_date ?></strong></td>
                                    </tr>
                                    <?php endif; ?>
                                    <tr>
                                        <td><strong><?= date('H:i:s', strtotime($log['created_at'])) ?></strong></td>
                                        <td><span class="badge bg-<?= getActionBadgeColor($log['activity_type']) ?> text-white"><?= htmlspecialchars($log['activity_type']) ?></span></td>
                                        <td><?= htmlspecialchars($log['description']) ?></td>
                                        <td><small class="text-muted"><?= htmlspecialchars($log['ip_address']) ?></small></td>
                                        <td><small class="text-muted" title="<?= htmlspecialchars($log['user_agent']) ?>"><?= substr(htmlspecialchars($log['user_agent']), 0, 50) ?>...</small></td>
                                    </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    <?php else: ?>
                        <div class="alert alert-info">Belum ada log aktivitas untuk user ini.</div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
</div>

<?php
// end
