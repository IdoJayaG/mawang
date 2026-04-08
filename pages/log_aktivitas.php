<?php
require_once __DIR__ . '/../includes/auth.php';
require_login();

// Helper: badge color for action types (define only if missing)
if (!function_exists('getActionBadgeColor')) {
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
}

$current_role = get_current_role();
$current_user_id = get_current_user_id();

// Backward-compatibility: some pages call is_admin(); provide a thin wrapper if missing
if (!function_exists('is_admin')) {
    function is_admin() {
        // prefer can_admin() from includes/auth.php if available
        if (function_exists('can_admin')) {
            return can_admin();
        }
        // fallback to role check
        return get_current_role() === 'admin';
    }
}

// Role-based access control - hanya admin dan operator yang bisa melihat log aktivitas
if (!can_operate()) {
    header('Location: index.php?page=403');
    exit;
}

// Pagination
$action = $_GET['action'] ?? '';
$id = isset($_GET['id']) ? intval($_GET['id']) : 0;

$current_page = isset($_GET['p']) ? max(1, intval($_GET['p'])) : 1;
$limit = 15;
$offset = ($current_page - 1) * $limit;

// Get log aktivitas grouped by user
$query = "SELECT u.id as user_id, u.nama_lengkap as nama, COALESCE(r.nama_role, '') as role,
                COUNT(la.id) as total_aktivitas,
                MAX(la.created_at) as aktivitas_terakhir,
                SUBSTRING_INDEX(GROUP_CONCAT(CONCAT(IFNULL(la.activity_type, ''), ': ', IFNULL(la.description, '')) ORDER BY la.created_at DESC SEPARATOR '||'), '||', 5) as aksi_terakhir
    FROM pengguna u
    LEFT JOIN user_account ua ON u.id = ua.pengguna_id
    LEFT JOIN role r ON ua.role_id = r.id
    LEFT JOIN log_aktivitas la ON u.id = la.user_id
    GROUP BY u.id, u.nama_lengkap, r.nama_role
    HAVING total_aktivitas > 0
    ORDER BY aktivitas_terakhir DESC
    LIMIT ? OFFSET ?";

$stmt = $mysqli->prepare($query);
$stmt->bind_param('ii', $limit, $offset);
$stmt->execute();
$result = $stmt->get_result();

// Get total count of users with activity logs
$count_query = "SELECT COUNT(DISTINCT u.id) as total 
                FROM pengguna u 
                INNER JOIN log_aktivitas la ON u.id = la.user_id";
$count_result = $mysqli->query($count_query);
$total_records = $count_result->fetch_assoc()['total'];
$total_pages = ceil($total_records / $limit);

// If detail view requested, render full page detail similar to riwayat_pemakaian
if ($action === 'view' && $id > 0) {
    // fetch user and role
    $s = $mysqli->prepare("SELECT p.nama_lengkap as nama, COALESCE(r.nama_role, '') as role FROM pengguna p LEFT JOIN user_account ua ON p.id = ua.pengguna_id LEFT JOIN role r ON ua.role_id = r.id WHERE p.id = ?");
    $s->bind_param('i', $id);
    $s->execute();
    $user = $s->get_result()->fetch_assoc();
    $s->close();

    if (!$user) {
        header('Location: index.php?page=log_aktivitas');
        exit;
    }

    // Filters: tanggal (YYYY-MM-DD), aksi (activity_type), and sort direction for created_at
    $tanggal = trim($_GET['tanggal'] ?? '');
    $aksi = trim($_GET['aksi'] ?? '');
    $dir = strtolower($_GET['dir'] ?? 'desc');
    $order_dir = ($dir === 'asc') ? 'ASC' : 'DESC';

    // Fetch distinct actions for dropdown
    $action_opts = [];
    if ($st = $mysqli->prepare("SELECT DISTINCT activity_type FROM log_aktivitas WHERE user_id = ? ORDER BY activity_type")) {
        $st->bind_param('i', $id);
        $st->execute();
        $rs = $st->get_result();
        while ($row = $rs->fetch_assoc()) { $action_opts[] = $row['activity_type']; }
        $st->close();
    }

    // ---------------- Pagination & Filter Handling (Detail View) ----------------
    // Page parameters for detail pagination (lp = log page, lpp = log per page)
    $detail_page = isset($_GET['lp']) ? max(1, intval($_GET['lp'])) : 1;
    $detail_limit = isset($_GET['lpp']) ? intval($_GET['lpp']) : 25; // default 25
    if ($detail_limit < 5) $detail_limit = 5; if ($detail_limit > 200) $detail_limit = 200; // sane bounds
    $detail_offset = ($detail_page - 1) * $detail_limit;

    // Build WHERE clauses dynamically (reuse for count & data queries)
    $where = ["user_id = ?"]; // mandatory
    $params = [$id];
    $types = 'i';

    if ($tanggal !== '' && preg_match('/^\d{4}-\d{2}-\d{2}$/', $tanggal)) {
        $where[] = "DATE(created_at) = ?";
        $types .= 's';
        $params[] = $tanggal;
    }
    if ($aksi !== '') {
        $where[] = "activity_type = ?";
        $types .= 's';
        $params[] = $aksi;
    }

    $where_sql = implode(' AND ', $where);

    // Total filtered count
    $countSql = "SELECT COUNT(*) as cnt FROM log_aktivitas WHERE $where_sql";
    $stCount = $mysqli->prepare($countSql);
    $bind = [$types]; foreach ($params as $i => $v) { $bind[] = &$params[$i]; }
    call_user_func_array([$stCount, 'bind_param'], $bind);
    $stCount->execute();
    $resCnt = $stCount->get_result()->fetch_assoc();
    $stCount->close();
    $total_filtered = intval($resCnt['cnt'] ?? 0);

    // Adjust page if overflow
    $total_pages_detail = max(1, ceil($total_filtered / $detail_limit));
    if ($detail_page > $total_pages_detail) { $detail_page = $total_pages_detail; $detail_offset = ($detail_page - 1) * $detail_limit; }

    // Count distinct actions (filtered)
    $distinctSql = "SELECT COUNT(DISTINCT activity_type) as cnt FROM log_aktivitas WHERE $where_sql";
    $stDistinct = $mysqli->prepare($distinctSql);
    $bind = [$types]; foreach ($params as $i => $v) { $bind[] = &$params[$i]; }
    call_user_func_array([$stDistinct, 'bind_param'], $bind);
    $stDistinct->execute();
    $distinct_actions_count = intval(($stDistinct->get_result()->fetch_assoc()['cnt'] ?? 0));
    $stDistinct->close();

    // Count today's logs (ignoring explicit tanggal filter to show real today total for this user) if no specific date filter supplied
    $today_count_total = 0;
    if ($tanggal === '') {
        $todaySql = "SELECT COUNT(*) as cnt FROM log_aktivitas WHERE $where_sql AND DATE(created_at) = CURDATE()";
        $stToday = $mysqli->prepare($todaySql);
        $bind = [$types]; foreach ($params as $i => $v) { $bind[] = &$params[$i]; }
        call_user_func_array([$stToday, 'bind_param'], $bind);
        $stToday->execute();
        $today_count_total = intval(($stToday->get_result()->fetch_assoc()['cnt'] ?? 0));
        $stToday->close();
    } else {
        // If user filtered by date (including today) reuse logic: if same as today count total filtered (already limited by date)
        if ($tanggal === date('Y-m-d')) { $today_count_total = $total_filtered; }
    }

    // Data query with LIMIT/OFFSET
    $sqlLogs = "SELECT * FROM log_aktivitas WHERE $where_sql ORDER BY created_at $order_dir LIMIT ? OFFSET ?";
    $s = $mysqli->prepare($sqlLogs);
    $typesWithLimit = $types . 'ii';
    $paramsWithLimit = $params; $paramsWithLimit[] = $detail_limit; $paramsWithLimit[] = $detail_offset;
    $bind = [$typesWithLimit]; foreach ($paramsWithLimit as $i => $v) { $bind[] = &$paramsWithLimit[$i]; }
    call_user_func_array([$s, 'bind_param'], $bind);
    $s->execute();
    $result_set = $s->get_result();
    $s->close();
    $log_rows = [];
    while ($r = $result_set->fetch_assoc()) { $log_rows[] = $r; }

    // Render detail (simple, similar layout to riwayat_pemakaian)
    ?>
    <div class="page-header">
        <h1><i class="fas fa-clipboard-list me-2"></i> Detail Log Aktivitas - <?= htmlspecialchars($user['nama']) ?></h1>
    </div>

    <div class="content-container">
        <div class="mb-3">
            <a href="index.php?page=log_aktivitas" class="btn btn-secondary btn-sm"><i class="fas fa-arrow-left me-1"></i> Kembali ke daftar</a>
        </div>

        <div class="card shadow-sm">
            <div class="card-body">
                <div class="user-summary mb-4">
                    <div class="row">
                        <div class="col-md-4">
                            <div class="card bg-light">
                                <div class="card-body text-center">
                                        <div class="bg-<?= strtolower($user['role'] ?? '') == 'admin' ? 'danger' : (strtolower($user['role'] ?? '') == 'operator' ? 'warning' : 'info') ?> text-white d-flex align-items-center justify-content-center">
                                        </div>
                                    <h5 class="text-primary"><?= htmlspecialchars($user['nama']) ?></h5>
                                        <?php 
                                            $uRoleNorm = strtolower(trim($user['role'] ?? ''));
                                            if ($uRoleNorm === 'admin' || strpos($uRoleNorm, 'administr') === 0) {
                                                $uRoleColor = 'danger';
                                            } elseif ($uRoleNorm === 'operator') {
                                                $uRoleColor = 'warning';
                                            } elseif ($uRoleNorm === '') {
                                                $uRoleColor = 'secondary';
                                            } else {
                                                $uRoleColor = 'info';
                                            }
                                        ?>
                                    <span class="badge bg-<?= $uRoleColor ?> text-white badge-lg">
                                        <?= strtoupper($user['role'] ?: '-') ?>
                                    </span>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-8">
                            <div class="row">
                                <div class="col-md-4">
                                    <div class="card border-primary mb-3">
                                        <div class="card-body text-center">
                                            <h4 class="text-primary"><?= $total_filtered ?></h4>
                                            <p class="mb-0">Total Aktivitas</p>
                                        </div>
                                    </div>
                                </div>
                                <div class="col-md-4">
                                    <div class="card border-success mb-3">
                                        <div class="card-body text-center">
                                            <h4 class="text-success"><?= $distinct_actions_count ?></h4>
                                            <p class="mb-0">Jenis Aksi</p>
                                        </div>
                                    </div>
                                </div>
                                <div class="col-md-4">
                                    <div class="card border-info mb-3">
                                        <div class="card-body text-center">
                                            <h4 class="text-info"><?= $today_count_total ?></h4>
                                            <p class="mb-0">Hari Ini</p>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="activity-logs">
                    <h5 class="mb-3 d-flex justify-content-between align-items-center">
                        <!-- <span><i class="fas fa-clipboard-list text-primary"></i> Log Aktivitas Lengkap</span> -->
                        <form method="GET" class="d-flex gap-2 align-items-end">
                            <input type="hidden" name="page" value="log_aktivitas" />
                            <input type="hidden" name="action" value="view" />
                            <input type="hidden" name="id" value="<?= (int)$id ?>" />
                            <div class="me-2">
                                <label class="form-label mb-1">Tanggal</label>
                                <input type="date" name="tanggal" class="form-control form-control-sm" value="<?= htmlspecialchars($tanggal) ?>" />
                            </div>
                            <div class="me-2">
                                <label class="form-label mb-1">Aksi</label>
                                <select name="aksi" class="form-select form-select-sm">
                                    <option value="">Semua</option>
                                    <?php foreach ($action_opts as $opt): ?>
                                        <option value="<?= htmlspecialchars($opt) ?>" <?= ($aksi === $opt ? 'selected' : '') ?>><?= htmlspecialchars($opt) ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div class="me-2">
                                <label class="form-label mb-1">Urutkan</label>
                                <select name="dir" class="form-select form-select-sm">
                                    <option value="desc" <?= ($dir==='desc'?'selected':'') ?>>Terbaru dulu (DESC)</option>
                                    <option value="asc" <?= ($dir==='asc'?'selected':'') ?>>Terlama dulu (ASC)</option>
                                </select>
                            </div>
                            <div class="me-2">
                                <button type="submit" class="btn btn-sm btn-primary"><i class="fas fa-filter"></i></button>
                                <a href="?page=log_aktivitas&action=view&id=<?= (int)$id ?>" class="btn btn-sm btn-outline-secondary"><i class="fas fa-undo"></i></a>
                            </div>
                        </form>
                    </h5>
                    <?php if (count($log_rows) > 0): ?>
                        <div class="table-responsive">
                            <table class="table table-bordered table-hover">
                                <thead class="bg-primary text-white">
                                    <tr>
                                        <th width="20%">Waktu</th>
                                        <th width="20%">Aksi</th>
                                        <th width="60%">Deskripsi</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php 
                                    // render from materialized array
                                    $current_date = '';
                                    foreach ($log_rows as $log):
                                        $log_date = date('d F Y', strtotime($log['created_at']));
                                        if ($log_date !== $current_date):
                                            $current_date = $log_date;
                                    ?>
                                    <tr class="table-secondary">
                                        <td colspan="3"><strong><i class="fas fa-calendar"></i> <?= $log_date ?></strong></td>
                                    </tr>
                                    <?php endif; ?>
                                    <tr>
                                        <td><strong><?= date('H:i:s', strtotime($log['created_at'])) ?></strong></td>
                                        <td><span class="badge bg-<?= getActionBadgeColor($log['activity_type']) ?> text-white"><?= htmlspecialchars($log['activity_type']) ?></span></td>
                                        <td><?= htmlspecialchars($log['description']) ?></td>
                                    </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                        <div class="d-flex flex-column flex-md-row justify-content-between align-items-md-center mt-3 gap-2">
                            <div>
                                <?php
                                    $from = $total_filtered ? ($detail_offset + 1) : 0;
                                    $to = min($detail_offset + $detail_limit, $total_filtered);
                                ?>
                                <small class="text-muted">Menampilkan <?= $from ?> - <?= $to ?> dari <?= $total_filtered ?> aktivitas</small>
                            </div>
                            <div class="d-flex align-items-center gap-2">
                                <form method="GET" class="d-flex align-items-center gap-2">
                                    <input type="hidden" name="page" value="log_aktivitas" />
                                    <input type="hidden" name="action" value="view" />
                                    <input type="hidden" name="id" value="<?= (int)$id ?>" />
                                    <?php if ($tanggal !== ''): ?><input type="hidden" name="tanggal" value="<?= htmlspecialchars($tanggal) ?>" /><?php endif; ?>
                                    <?php if ($aksi !== ''): ?><input type="hidden" name="aksi" value="<?= htmlspecialchars($aksi) ?>" /><?php endif; ?>
                                    <input type="hidden" name="dir" value="<?= htmlspecialchars($dir) ?>" />
                                    <select name="lpp" class="form-select form-select-sm" onchange="this.form.submit()">
                                        <?php foreach ([10,25,50,100] as $optL): ?>
                                            <option value="<?= $optL ?>" <?= $detail_limit==$optL?'selected':'' ?>><?= $optL ?></option>
                                        <?php endforeach; ?>
                                    </select>
                                    <noscript><button class="btn btn-sm btn-primary">Terapkan</button></noscript>
                                </form>
                                <?php if ($total_pages_detail > 1): ?>
                                <nav aria-label="Pagination Detail Log">
                                    <ul class="pagination pagination-sm mb-0">
                                        <?php
                                            // helper to build URL while preserving filters
                                            function buildDetailUrl($p) {
                                                $params = $_GET;
                                                $params['lp'] = $p;
                                                return '?' . http_build_query($params);
                                            }
                                        ?>
                                        <?php if ($detail_page > 1): ?>
                                            <li class="page-item"><a class="page-link" href="<?= buildDetailUrl($detail_page-1) ?>">&laquo;</a></li>
                                        <?php endif; ?>
                                        <?php
                                            $window = 2; // pages before/after current
                                            $start = max(1, $detail_page - $window);
                                            $end = min($total_pages_detail, $detail_page + $window);
                                            if ($start > 1) {
                                                echo '<li class="page-item"><a class="page-link" href="'.buildDetailUrl(1).'">1</a></li>';
                                                if ($start > 2) echo '<li class="page-item disabled"><span class="page-link">...</span></li>';
                                            }
                                            for ($p=$start; $p<=$end; $p++) {
                                                $active = $p == $detail_page ? ' active' : '';
                                                echo '<li class="page-item'.$active.'"><a class="page-link" href="'.buildDetailUrl($p).'">'.$p.'</a></li>';
                                            }
                                            if ($end < $total_pages_detail) {
                                                if ($end < $total_pages_detail - 1) echo '<li class="page-item disabled"><span class="page-link">...</span></li>';
                                                echo '<li class="page-item"><a class="page-link" href="'.buildDetailUrl($total_pages_detail).'">'.$total_pages_detail.'</a></li>';
                                            }
                                        ?>
                                        <?php if ($detail_page < $total_pages_detail): ?>
                                            <li class="page-item"><a class="page-link" href="<?= buildDetailUrl($detail_page+1) ?>">&raquo;</a></li>
                                        <?php endif; ?>
                                    </ul>
                                </nav>
                                <?php endif; ?>
                            </div>
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
    return;
}
?>

<div id="log-aktivitas-page" class="content-wrapper">
    <div class="page-header">
            <div class="col">
                <h3 class="page-title"><i class="fas fa-clipboard-list"></i>Log Aktivitas</h3>
                <p class="page-description">Pantau aktivitas pengguna dalam sistem</p>
            </div>
            <div class="header-actions">
                <div class="btn-group">
                    <button class="btn btn-outline-primary btn-export-log" title="Export CSV">
                        <i class="fas fa-file-csv"></i> CSV
                    </button>
                    <button class="btn btn-outline-success btn-export-log-xlsx" title="Export Excel (.xlsx)">
                        <i class="fas fa-file-excel"></i> Excel
                    </button>
                </div>
            </div>
    </div>

    <div class="content">
        <div class="card">
            <div class="card-header">
                <h4 class="card-title">Log Aktivitas Per Pengguna</h4>
                <div class="card-actions">
                    <span class="badge bg-primary"><?= $total_records ?> Pengguna Aktif</span>
                </div>
            </div>
            <div class="card-body">
                <div id="log-container">
                    <div class="table-responsive">
                        <table class="table table-hover">
                            <thead class="bg-light">
                                <tr>
                                    <th width="5%">No</th>
                                    <th width="30%">Pengguna</th>
                                    <th width="15%">Role</th>
                                    <th width="15%">Total Aktivitas</th>
                                    <th width="20%">Aktivitas Terakhir</th>
                                    <th width="15%">Aksi</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php 
                                $no = $offset + 1;
                                if ($result->num_rows > 0):
                                    while ($row = $result->fetch_assoc()):
                                ?>
                                <tr>
                                    <td><?= $no++ ?></td>
                                    <td>
                                        <div class="d-flex align-items-center">
                                            <div class="flex-shrink-0 me-3">
                                                <?php $roleClass = strtolower($row['role'] ?? ''); ?>
                                                <div class="bg-<?= $roleClass == 'admin' ? 'danger' : ($roleClass == 'operator' ? 'warning' : 'info') ?> text-white d-flex align-items-center justify-content-center" 
                                                     class="rounded-circle img-small">
                                                </div>
                                            </div>
                                            <div>
                                                <strong class="text-primary"><?= htmlspecialchars($row['nama']) ?></strong>
                                            </div>
                                        </div>
                                    </td>
                                    <td>
                                        <?php 
                                            $roleUpper = strtoupper($row['role'] ?? '');
                                            $roleNorm = strtolower(trim($row['role'] ?? ''));
                                            // accept variations like 'administrator'
                                            if ($roleNorm === 'admin' || strpos($roleNorm, 'administr') === 0) {
                                                $roleColor = 'danger';
                                            } elseif ($roleNorm === 'operator') {
                                                $roleColor = 'warning';
                                            } elseif ($roleNorm === '') {
                                                $roleColor = 'secondary';
                                            } else {
                                                $roleColor = 'info';
                                            }
                                        ?>
                                        <span class="badge bg-<?= $roleColor ?> text-white">
                                            <?= $roleUpper ?: '-' ?>
                                        </span>
                                    </td>
                                    <td>
                                        <span class="badge bg-primary fs-6 px-3 py-2"><?= $row['total_aktivitas'] ?> Aktivitas</span>
                                    </td>
                                    <td>
                                        <?php if ($row['aktivitas_terakhir']): ?>
                                            <strong><?= date('d/m/Y H:i', strtotime($row['aktivitas_terakhir'])) ?></strong><br>
                                            <small class="text-muted"><?= floor((time() - strtotime($row['aktivitas_terakhir'])) / (60*60*24)) ?> hari lalu</small>
                                        <?php else: ?>
                                            <span class="text-muted">-</span>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <a class="btn btn-sm btn-primary" href="index.php?page=log_aktivitas&action=view&id=<?= $row['user_id'] ?>" title="Lihat Detail Log">
                                            <i class="fas fa-list"></i> Detail Log
                                        </a>
                                    </td>
                                </tr>
                                <?php 
                                    endwhile;
                                else: 
                                ?>
                                <tr>
                                    <td colspan="6" class="text-center text-muted">
                                        <div class="py-4">
                                            <i class="fas fa-clipboard-list fa-3x mb-3 text-muted"></i>
                                            <h5>Belum ada log aktivitas</h5>
                                            <p>Log akan muncul setelah pengguna melakukan aktivitas dalam sistem</p>
                                        </div>
                                    </td>
                                </tr>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
                
                <?php if ($total_pages > 1): ?>
                <div class="card-footer">
                    <nav aria-label="Page navigation">
                        <ul class="pagination justify-content-center mb-0">
                            <?php if ($current_page > 1): ?>
                                <li class="page-item">
                                    <a class="page-link" href="?page=log_aktivitas&p=<?= $current_page - 1 ?>">
                                        <i class="fas fa-chevron-left"></i> Sebelumnya
                                    </a>
                                </li>
                            <?php endif; ?>
                            
                            <?php for ($i = max(1, $current_page - 2); $i <= min($total_pages, $current_page + 2); $i++): ?>
                                <li class="page-item <?= $i == $current_page ? 'active' : '' ?>">
                                    <a class="page-link" href="?page=log_aktivitas&p=<?= $i ?>"><?= $i ?></a>
                                </li>
                            <?php endfor; ?>
                            
                            <?php if ($current_page < $total_pages): ?>
                                <li class="page-item">
                                    <a class="page-link" href="?page=log_aktivitas&p=<?= $current_page + 1 ?>">
                                        Selanjutnya <i class="fas fa-chevron-right"></i>
                                    </a>
                                </li>
                            <?php endif; ?>
                        </ul>
                    </nav>
                </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<!-- Detail Modal untuk Log Per User -->
<div class="modal fade" id="userLogModal" tabindex="-1" role="dialog">
    <div class="modal-dialog modal-xl" role="document">
        <div class="modal-content">
            <div class="modal-header bg-primary text-white">
                <h5 class="modal-title">
                    <i class="fas fa-clipboard-list"></i> 
                    Detail Log Aktivitas User
                </h5>
                <button type="button" class="close text-white" data-dismiss="modal">
                    <span>&times;</span>
                </button>
            </div>
            <div class="modal-body">
                <!-- Content will be loaded via AJAX -->
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-dismiss="modal">Tutup</button>
            </div>
        </div>
    </div>
</div>

<!-- Timeline Modal untuk Log Per User -->
<div class="modal fade" id="timelineModal" tabindex="-1" role="dialog">
    <div class="modal-dialog modal-lg" role="document">
        <div class="modal-content">
            <div class="modal-header bg-info text-white">
                <h5 class="modal-title">
                    <i class="fas fa-clock"></i> 
                    Timeline Aktivitas User
                </h5>
                <button type="button" class="close text-white" data-dismiss="modal">
                    <span>&times;</span>
                </button>
            </div>
            <div class="modal-body">
                <!-- Content will be loaded via AJAX -->
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-dismiss="modal">Tutup</button>
            </div>
        </div>
    </div>
</div>

<script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
<script src="assets/js/main.js"></script>

<div id="log-aktivitas-page" class="d-none"></div>


