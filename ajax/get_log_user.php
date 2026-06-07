<?php
require_once dirname(__DIR__) . '/includes/auth.php';

// Buffer output to prevent accidental HTML/whitespace from breaking JSON responses
ob_start();
header('Content-Type: application/json; charset=utf-8');

if (!can_operate()) {
    echo json_encode(['success' => false, 'message' => 'Tidak memiliki akses']);
    exit;
}

$user_id = (int)($_GET['user_id'] ?? 0);

if (!$user_id) {
    echo json_encode(['success' => false, 'message' => 'ID user tidak valid']);
    exit;
}

try {
    // Get user info (join role via user_account)
    $stmt = $mysqli->prepare("SELECT p.nama_lengkap as nama, COALESCE(r.kode_role, r.nama_role, '') as role_code, COALESCE(r.nama_role, '') as role_name FROM pengguna p LEFT JOIN user_account ua ON p.id = ua.pengguna_id LEFT JOIN role r ON ua.role_id = r.id WHERE p.id = ?");
    $stmt->bind_param('i', $user_id);
    $stmt->execute();
    $user = $stmt->get_result()->fetch_assoc();
    $stmt->close();
    
    if (!$user) {
        echo json_encode(['success' => false, 'message' => 'User tidak ditemukan']);
        exit;
    }
    
    // Get activity logs
    $stmt = $mysqli->prepare("
        SELECT * FROM log_aktivitas 
        WHERE user_id = ?
        ORDER BY created_at DESC
        LIMIT 100
    ");
    $stmt->bind_param('i', $user_id);
    $stmt->execute();
    $logs = $stmt->get_result();
    $stmt->close();
    
    $role_code = strtoupper(trim((string)($user['role_code'] ?? '')));
    $role_label = trim((string)($user['role_name'] ?? ''));
    if ($role_label === '') { $role_label = $role_code; }
    $badge_class = 'info';
    if ($role_code !== '') {
        if (function_exists('is_role_admin_like') && is_role_admin_like($role_code)) {
            $badge_class = 'danger';
        } elseif ($role_code === 'DRIVER') {
            $badge_class = 'primary';
        } elseif ($role_code === 'USER') {
            $badge_class = 'info';
        }
    }

    ob_start();
?>
<div class="user-summary mb-4">
    <div class="row">
        <div class="col-md-4">
            <div class="card bg-light">
                <div class="card-body text-center">
                    <div class="user-avatar-large mb-3">
                        <div class="bg-<?= $badge_class ?> text-white d-flex align-items-center justify-content-center" 
                             style="width: 80px; height: 80px; border-radius: 50%; margin: 0 auto;">
                            <i class="fas fa-user fa-2x"></i>
                        </div>
                    </div>
                    <h5 class="text-primary"><?= htmlspecialchars($user['nama']) ?></h5>
                    <span class="badge badge-<?= $badge_class ?> badge-lg">
                        <?= htmlspecialchars(strtoupper($role_label)) ?>
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
                            $logs->data_seek(0);
                            $unique_actions = [];
                            while ($row = $logs->fetch_assoc()) {
                                $unique_actions[$row['activity_type']] = true;
                            }
                            $logs->data_seek(0);
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
                            $logs->data_seek(0);
                            $today_count = 0;
                            while ($row = $logs->fetch_assoc()) {
                                if (date('Y-m-d', strtotime($row['created_at'])) == date('Y-m-d')) {
                                    $today_count++;
                                }
                            }
                            $logs->data_seek(0);
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
    
    <?php if ($logs->num_rows > 0): ?>
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
                    while ($log = $logs->fetch_assoc()): 
                        $log_date = date('d F Y', strtotime($log['created_at']));
                        if ($log_date !== $current_date):
                            $current_date = $log_date;
                    ?>
                    <tr class="table-secondary">
                        <td colspan="5">
                            <strong><i class="fas fa-calendar"></i> <?= $log_date ?></strong>
                        </td>
                    </tr>
                    <?php endif; ?>
                    <tr>
                        <td>
                            <strong><?= date('H:i:s', strtotime($log['created_at'])) ?></strong>
                        </td>
                        <td>
                            <span class="badge badge-<?= getActionBadgeColor($log['activity_type']) ?> badge-sm">
                                <?= htmlspecialchars($log['activity_type']) ?>
                            </span>
                        </td>
                        <td>
                            <?= htmlspecialchars($log['description']) ?>
                        </td>
                        <td>
                            <small class="text-muted"><?= htmlspecialchars($log['ip_address']) ?></small>
                        </td>
                        <td>
                            <small class="text-muted" title="<?= htmlspecialchars($log['user_agent']) ?>">
                                <?= substr(htmlspecialchars($log['user_agent']), 0, 50) ?>...
                            </small>
                        </td>
                    </tr>
                    <?php endwhile; ?>
                </tbody>
            </table>
        </div>
        
        <div class="alert alert-info mt-3">
            <i class="fas fa-info-circle"></i> 
            Menampilkan 100 aktivitas terakhir. Untuk melihat semua log, gunakan fitur export.
        </div>
    <?php else: ?>
        <div class="alert alert-info">
            <i class="fas fa-info-circle"></i> Belum ada log aktivitas untuk user ini.
        </div>
    <?php endif; ?>
</div>

<?php
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
            if (strpos($action, $key) !== false) {
                return $color;
            }
        }
        
        return 'secondary';
    }
    
    $html = ob_get_clean();
    // If any unexpected output was emitted before JSON (e.g. PHP warning), log it and discard
    if (trim($html) !== '') {
        error_log('[ajax/get_log_user] unexpected output before JSON: ' . substr(trim($html), 0, 1000));
    }
    echo json_encode(['success' => true, 'html' => $html]);
    
} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => 'Error: ' . $e->getMessage()]);
}
?>
