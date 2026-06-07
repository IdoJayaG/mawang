<?php
require_once dirname(__DIR__) . '/includes/auth.php';

header('Content-Type: application/json');

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
    // Get user info
    $stmt = $mysqli->prepare("SELECT p.nama_lengkap as nama, COALESCE(r.kode_role, r.nama_role, '') as role_code, COALESCE(r.nama_role, '') as role_name FROM pengguna p LEFT JOIN user_account ua ON p.id = ua.pengguna_id LEFT JOIN role r ON ua.role_id = r.id WHERE p.id = ?");
    $stmt->bind_param('i', $user_id);
    $stmt->execute();
    $user = $stmt->get_result()->fetch_assoc();
    $stmt->close();
    
    if (!$user) {
        echo json_encode(['success' => false, 'message' => 'User tidak ditemukan']);
        exit;
    }
    
    // Get activity timeline grouped by day
    $stmt = $mysqli->prepare("
        SELECT DATE(created_at) as activity_date,
               COUNT(*) as activity_count,
               GROUP_CONCAT(DISTINCT aksi ORDER BY created_at DESC) as actions,
               MIN(created_at) as first_activity,
               MAX(created_at) as last_activity
        FROM log_aktivitas 
        WHERE user_id = ?
        GROUP BY DATE(created_at)
        ORDER BY activity_date DESC
        LIMIT 30
    ");
    $stmt->bind_param('i', $user_id);
    $stmt->execute();
    $timeline_items = $stmt->get_result();
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
<div class="timeline-container">
    <div class="user-header mb-4">
        <h5 class="text-center">
            <i class="fas fa-user text-primary"></i>
            <?= htmlspecialchars($user['nama']) ?> - Timeline Aktivitas
        </h5>
        <p class="text-center text-muted">
            <span class="badge badge-<?= $badge_class ?>">
                <?= htmlspecialchars(strtoupper($role_label)) ?>
            </span>
        </p>
    </div>
    
    <?php if ($timeline_items->num_rows > 0): ?>
        <div class="timeline">
            <?php while ($item = $timeline_items->fetch_assoc()): ?>
                <div class="timeline-item <?= getTimelineStatus($item['activity_count']) ?>">
                    <div class="timeline-content">
                        <div class="timeline-header d-flex justify-content-between align-items-start">
                            <div>
                                <h6 class="mb-1">
                                    <i class="fas fa-calendar-day text-primary"></i>
                                    <?= date('d F Y', strtotime($item['activity_date'])) ?>
                                </h6>
                                <small class="text-muted">
                                    <i class="fas fa-clock"></i> 
                                    <?= date('H:i', strtotime($item['first_activity'])) ?> - <?= date('H:i', strtotime($item['last_activity'])) ?>
                                </small>
                            </div>
                            <div class="text-right">
                                <span class="badge badge-<?= getTimelineStatus($item['activity_count']) ?>">
                                    <?= $item['activity_count'] ?> Aktivitas
                                </span>
                            </div>
                        </div>
                        <div class="activity-summary mt-2">
                            <?php 
                            $actions = explode(',', $item['actions']);
                            $action_groups = array_count_values($actions);
                            arsort($action_groups);
                            $top_actions = array_slice($action_groups, 0, 3, true);
                            ?>
                            <div class="action-tags">
                                <?php foreach ($top_actions as $action => $count): ?>
                                    <span class="badge badge-outline-<?= getActionColor($action) ?> badge-sm me-1">
                                        <?= htmlspecialchars($action) ?> (<?= $count ?>x)
                                    </span>
                                <?php endforeach; ?>
                                <?php if (count($action_groups) > 3): ?>
                                    <span class="badge badge-outline-secondary badge-sm">
                                        +<?= count($action_groups) - 3 ?> lainnya
                                    </span>
                                <?php endif; ?>
                            </div>
                        </div>
                        <?php 
                        // Determine activity intensity
                        if ($item['activity_count'] >= 50) {
                            $intensity = 'Sangat Aktif';
                            $intensity_class = 'text-danger';
                        } elseif ($item['activity_count'] >= 20) {
                            $intensity = 'Aktif';
                            $intensity_class = 'text-warning';
                        } elseif ($item['activity_count'] >= 5) {
                            $intensity = 'Normal';
                            $intensity_class = 'text-success';
                        } else {
                            $intensity = 'Sedikit';
                            $intensity_class = 'text-muted';
                        }
                        ?>
                        <p class="mb-0 mt-2 small <?= $intensity_class ?>">
                            <i class="fas fa-chart-line"></i> Intensitas: <?= $intensity ?>
                        </p>
                    </div>
                </div>
            <?php endwhile; ?>
        </div>
        
        <div class="text-center mt-4">
            <p class="text-muted">
                <i class="fas fa-info-circle"></i> 
                Menampilkan 30 hari terakhir dengan aktivitas
            </p>
        </div>
    <?php else: ?>
        <div class="text-center py-5">
            <i class="fas fa-clock fa-3x text-muted mb-3"></i>
            <h5 class="text-muted">Belum ada aktivitas</h5>
            <p class="text-muted">Timeline akan muncul setelah user melakukan aktivitas dalam sistem</p>
        </div>
    <?php endif; ?>
</div>

<style>
.timeline {
    position: relative;
    padding: 0;
    list-style: none;
}

.timeline-item {
    border-left: 3px solid #007bff;
    padding-left: 20px;
    margin-bottom: 25px;
    position: relative;
    background: #f8f9fa;
    border-radius: 8px;
    padding: 15px 15px 15px 25px;
}

.timeline-item:before {
    content: '';
    position: absolute;
    left: -8px;
    top: 15px;
    width: 12px;
    height: 12px;
    border-radius: 50%;
    background-color: #007bff;
    border: 2px solid #fff;
    box-shadow: 0 0 0 2px #007bff;
}

.timeline-item.success {
    border-left-color: #28a745;
    background: #f8fff9;
}

.timeline-item.success:before {
    background-color: #28a745;
    box-shadow: 0 0 0 2px #28a745;
}

.timeline-item.warning {
    border-left-color: #ffc107;
    background: #fffdf5;
}

.timeline-item.warning:before {
    background-color: #ffc107;
    box-shadow: 0 0 0 2px #ffc107;
}

.timeline-item.danger {
    border-left-color: #dc3545;
    background: #fff5f5;
}

.timeline-item.danger:before {
    background-color: #dc3545;
    box-shadow: 0 0 0 2px #dc3545;
}

.timeline-content {
    padding: 0;
}

.timeline-header h6 {
    color: #495057;
    font-weight: 600;
}

.user-header {
    background: linear-gradient(135deg, #007bff, #6610f2);
    color: white;
    padding: 15px;
    border-radius: 8px;
    margin-bottom: 20px;
}

.action-tags .badge {
    margin-right: 0.25rem;
    margin-bottom: 0.25rem;
}

.badge-outline-primary {
    color: #007bff;
    border: 1px solid #007bff;
    background: transparent;
}

.badge-outline-success {
    color: #28a745;
    border: 1px solid #28a745;
    background: transparent;
}

.badge-outline-warning {
    color: #ffc107;
    border: 1px solid #ffc107;
    background: transparent;
}

.badge-outline-danger {
    color: #dc3545;
    border: 1px solid #dc3545;
    background: transparent;
}

.badge-outline-secondary {
    color: #6c757d;
    border: 1px solid #6c757d;
    background: transparent;
}

.badge-sm {
    font-size: 0.75rem;
    padding: 0.25rem 0.5rem;
}

.me-1 {
    margin-right: 0.25rem;
}
</style>

<?php
    function getTimelineStatus($count) {
        if ($count >= 50) return 'danger';
        if ($count >= 20) return 'warning';
        if ($count >= 5) return 'success';
        return 'primary';
    }
    
    function getActionColor($action) {
        $colors = [
            'LOGIN' => 'success',
            'LOGOUT' => 'secondary',
            'CREATE' => 'primary',
            'UPDATE' => 'warning',
            'DELETE' => 'danger',
            'VIEW' => 'info',
            'EXPORT' => 'info',
            'COMPLETE' => 'success',
            'ADD' => 'primary'
        ];
        
        foreach ($colors as $key => $color) {
            if (strpos($action, $key) !== false) {
                return $color;
            }
        }
        
        return 'secondary';
    }
    
    $html = ob_get_clean();
    echo json_encode(['success' => true, 'html' => $html]);
    
} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => 'Error: ' . $e->getMessage()]);
}
?>
