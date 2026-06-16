<?php
require_once 'includes/auth.php';
require_login();

$current_role = get_current_role();
$current_user_id = get_current_user_id();

// Check access
if (!is_admin_like() && $current_role !== 'driver') {
    header('Location: pages/403.php');
    exit;
}

$accessible_vehicle_ids = [];
if ($current_role === 'driver') {
    $accessible = get_accessible_vehicles($current_role, $current_user_id);
    $accessible_vehicle_ids = array_values(array_filter(array_map(static function ($v) {
        return (int)($v['id'] ?? 0);
    }, $accessible)));
}

$action = $_GET['action'] ?? 'list';
$dokumen_id = $_GET['id'] ?? null;
$msg = '';

// Handle file upload
function uploadDocument($file, $kendaraan_id, $jenis_dokumen) {
    $upload_dir = 'uploads/dokumen/';
    if (!file_exists($upload_dir)) {
        mkdir($upload_dir, 0755, true);
    }
    
    $allowed_types = ['image/jpeg', 'image/jpg', 'image/png', 'application/pdf'];
    $max_size = 10 * 1024 * 1024; // 10MB
    
    if (!isset($file['tmp_name']) || empty($file['tmp_name'])) {
        return ['success' => false, 'message' => 'Tidak ada file yang diupload'];
    }
    
    if (!in_array($file['type'], $allowed_types)) {
        return ['success' => false, 'message' => 'Tipe file tidak diizinkan. Hanya JPG, PNG, dan PDF'];
    }
    
    if ($file['size'] > $max_size) {
        return ['success' => false, 'message' => 'Ukuran file terlalu besar. Maksimal 10MB'];
    }
    
    $extension = pathinfo($file['name'], PATHINFO_EXTENSION);
    $filename = $kendaraan_id . '_' . $jenis_dokumen . '_' . uniqid() . '_' . date('YmdHis') . '.' . $extension;
    $filepath = $upload_dir . $filename;
    
    if (!move_uploaded_file($file['tmp_name'], $filepath)) {
        return ['success' => false, 'message' => 'Gagal mengupload file'];
    }
    
    return ['success' => true, 'filename' => $filename];
}

// Handle form submissions
if ($_POST) {
    if (!validate_csrf_token($_POST['csrf_token'] ?? '')) {
        $msg = '<div class="alert alert-danger">Token keamanan tidak valid!</div>';
    } else {
        switch ($action) {
            case 'add':
                if (!can_admin()) {
                    $msg = '<div class="alert alert-danger">Hanya administrator yang dapat menambahkan dokumen.</div>';
                    break;
                }
                $kendaraan_id = (int)$_POST['kendaraan_id'];
                if ($current_role === 'driver' && !in_array($kendaraan_id, $accessible_vehicle_ids, true)) {
                    $msg = '<div class="alert alert-danger">Anda tidak memiliki akses ke kendaraan tersebut.</div>';
                    break;
                }
                $jenis_dokumen = trim($_POST['jenis_dokumen']);
                $nomor_dokumen = trim($_POST['nomor_dokumen']);
                $tanggal_terbit = $_POST['tanggal_terbit'];
                $tanggal_berlaku = $_POST['tanggal_berlaku'];
                $instansi_penerbit = trim($_POST['instansi_penerbit']);
                $status = $_POST['status'];
                $keterangan = trim($_POST['keterangan']);
                
                $file_dokumen = null;
                if (isset($_FILES['file_dokumen']) && $_FILES['file_dokumen']['error'] === 0) {
                    $upload_result = uploadDocument($_FILES['file_dokumen'], $kendaraan_id, $jenis_dokumen);
                    if (!$upload_result['success']) {
                        $msg = '<div class="alert alert-danger">Error upload: ' . $upload_result['message'] . '</div>';
                        break;
                    }
                    $file_dokumen = $upload_result['filename'];
                }
                
                $stmt = $mysqli->prepare("INSERT INTO dokumen_kendaraan (kendaraan_id, jenis_dokumen, nomor_dokumen, tanggal_terbit, tanggal_berlaku, instansi_penerbit, file_dokumen, status, keterangan, created_by) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
                $stmt->bind_param('issssssssi', $kendaraan_id, $jenis_dokumen, $nomor_dokumen, $tanggal_terbit, $tanggal_berlaku, $instansi_penerbit, $file_dokumen, $status, $keterangan, $current_user_id);
                
                if ($stmt->execute()) {
                    $msg = '<div class="alert alert-success">Dokumen berhasil ditambahkan!</div>';
                } else {
                    $msg = '<div class="alert alert-danger">Error: ' . $stmt->error . '</div>';
                }
                $stmt->close();
                break;
                
            case 'edit':
                $id = (int)$_POST['id'];
                if ($current_role === 'driver') {
                    $guard = $mysqli->prepare("SELECT kendaraan_id FROM dokumen_kendaraan WHERE id = ? LIMIT 1");
                    if ($guard) {
                        $guard->bind_param('i', $id);
                        $guard->execute();
                        $guardRow = $guard->get_result()->fetch_assoc();
                        $guard->close();
                        $guardKendaraanId = (int)($guardRow['kendaraan_id'] ?? 0);
                        if ($guardKendaraanId <= 0 || !in_array($guardKendaraanId, $accessible_vehicle_ids, true)) {
                            $msg = '<div class="alert alert-danger">Anda tidak memiliki akses ke dokumen tersebut.</div>';
                            break;
                        }
                    }
                }
                $jenis_dokumen = trim($_POST['jenis_dokumen']);
                $nomor_dokumen = trim($_POST['nomor_dokumen']);
                $tanggal_terbit = $_POST['tanggal_terbit'];
                $tanggal_berlaku = $_POST['tanggal_berlaku'];
                $instansi_penerbit = trim($_POST['instansi_penerbit']);
                $status = $_POST['status'];
                $keterangan = trim($_POST['keterangan']);
                
                // Check if new file uploaded
                $file_update = '';
                if (isset($_FILES['file_dokumen']) && $_FILES['file_dokumen']['error'] === 0) {
                    // Get current document info
                    $current_doc = $mysqli->query("SELECT kendaraan_id, file_dokumen FROM dokumen_kendaraan WHERE id = $id")->fetch_assoc();
                    
                    $upload_result = uploadDocument($_FILES['file_dokumen'], $current_doc['kendaraan_id'], $jenis_dokumen);
                    if (!$upload_result['success']) {
                        $msg = '<div class="alert alert-danger">Error upload: ' . $upload_result['message'] . '</div>';
                        break;
                    }
                    
                    // Delete old file if exists
                    if ($current_doc['file_dokumen'] && file_exists('uploads/dokumen/' . $current_doc['file_dokumen'])) {
                        unlink('uploads/dokumen/' . $current_doc['file_dokumen']);
                    }
                    
                    $file_update = ', file_dokumen = ?';
                }
                
                if ($file_update) {
                    $stmt = $mysqli->prepare("UPDATE dokumen_kendaraan SET jenis_dokumen = ?, nomor_dokumen = ?, tanggal_terbit = ?, tanggal_berlaku = ?, instansi_penerbit = ?, status = ?, keterangan = ?, updated_by = ?" . $file_update . " WHERE id = ?");
                    $stmt->bind_param('sssssssisi', $jenis_dokumen, $nomor_dokumen, $tanggal_terbit, $tanggal_berlaku, $instansi_penerbit, $status, $keterangan, $current_user_id, $upload_result['filename'], $id);
                } else {
                    $stmt = $mysqli->prepare("UPDATE dokumen_kendaraan SET jenis_dokumen = ?, nomor_dokumen = ?, tanggal_terbit = ?, tanggal_berlaku = ?, instansi_penerbit = ?, status = ?, keterangan = ?, updated_by = ? WHERE id = ?");
                    $stmt->bind_param('sssssssii', $jenis_dokumen, $nomor_dokumen, $tanggal_terbit, $tanggal_berlaku, $instansi_penerbit, $status, $keterangan, $current_user_id, $id);
                }
                
                if ($stmt->execute()) {
                    $msg = '<div class="alert alert-success">Dokumen berhasil diperbarui!</div>';
                } else {
                    $msg = '<div class="alert alert-danger">Error: ' . $stmt->error . '</div>';
                }
                $stmt->close();
                break;
                
            case 'delete':
                $id = (int)$_POST['id'];
                if ($current_role === 'driver') {
                    $guard = $mysqli->prepare("SELECT kendaraan_id FROM dokumen_kendaraan WHERE id = ? LIMIT 1");
                    if ($guard) {
                        $guard->bind_param('i', $id);
                        $guard->execute();
                        $guardRow = $guard->get_result()->fetch_assoc();
                        $guard->close();
                        $guardKendaraanId = (int)($guardRow['kendaraan_id'] ?? 0);
                        if ($guardKendaraanId <= 0 || !in_array($guardKendaraanId, $accessible_vehicle_ids, true)) {
                            $msg = '<div class="alert alert-danger">Anda tidak memiliki akses ke dokumen tersebut.</div>';
                            break;
                        }
                    }
                }
                
                // Get file info before deletion
                $doc = $mysqli->query("SELECT file_dokumen FROM dokumen_kendaraan WHERE id = $id")->fetch_assoc();
                
                $stmt = $mysqli->prepare("DELETE FROM dokumen_kendaraan WHERE id = ?");
                $stmt->bind_param('i', $id);
                
                if ($stmt->execute()) {
                    // Delete file if exists
                    if ($doc['file_dokumen'] && file_exists('uploads/dokumen/' . $doc['file_dokumen'])) {
                        unlink('uploads/dokumen/' . $doc['file_dokumen']);
                    }
                    $msg = '<div class="alert alert-success">Dokumen berhasil dihapus!</div>';
                } else {
                    $msg = '<div class="alert alert-danger">Error: ' . $stmt->error . '</div>';
                }
                $stmt->close();
                break;
        }
    }
}

// Get filter parameters
$filter_jenis = $_GET['jenis'] ?? '';
$filter_kendaraan = $_GET['kendaraan'] ?? '';
$filter_status = $_GET['status'] ?? '';
$search = trim($_GET['search'] ?? '');
$active_tab = $_GET['tab'] ?? 'all';

// Build query conditions
$where_conditions = [];
$params = [];
$param_types = '';

if ($filter_jenis && $filter_jenis !== 'all') {
    $where_conditions[] = "dk.jenis_dokumen = ?";
    $params[] = $filter_jenis;
    $param_types .= 's';
}

if ($filter_kendaraan) {
    $where_conditions[] = "dk.kendaraan_id = ?";
    $params[] = (int)$filter_kendaraan;
    $param_types .= 'i';
}

if ($filter_status && $filter_status !== 'all') {
    $where_conditions[] = "dk.status = ?";
    $params[] = $filter_status;
    $param_types .= 's';
}

if ($search) {
    $where_conditions[] = "(dk.nomor_dokumen LIKE ? OR dk.instansi_penerbit LIKE ? OR k.no_reg LIKE ? OR k.no_polisi LIKE ?)";
    $search_term = "%$search%";
    $params = array_merge($params, [$search_term, $search_term, $search_term, $search_term]);
    $param_types .= 'ssss';
}

if ($current_role === 'driver') {
    if (!empty($accessible_vehicle_ids)) {
        $placeholders = implode(',', array_fill(0, count($accessible_vehicle_ids), '?'));
        $where_conditions[] = "dk.kendaraan_id IN ($placeholders)";
        foreach ($accessible_vehicle_ids as $vid) {
            $params[] = (int)$vid;
            $param_types .= 'i';
        }
    } else {
        $where_conditions[] = '1=0';
    }
}

// Add tab-specific conditions
switch ($active_tab) {
    case 'expire_soon':
        $where_conditions[] = "dk.tanggal_berlaku BETWEEN CURDATE() AND DATE_ADD(CURDATE(), INTERVAL 30 DAY)";
        break;
    case 'expired':
        $where_conditions[] = "dk.tanggal_berlaku < CURDATE()";
        break;
    case 'active':
        $where_conditions[] = "dk.status = 'Aktif' AND dk.tanggal_berlaku > CURDATE()";
        break;
}

$where_sql = !empty($where_conditions) ? 'WHERE ' . implode(' AND ', $where_conditions) : '';

// Pagination
$page = max(1, (int)($_GET['page_num'] ?? 1));
$limit = 15;
$offset = ($page - 1) * $limit;

// Get documents
$sql = "
    SELECT dk.*, k.no_polisi, k.no_reg, k.merk, k.tipe,
           CASE 
               WHEN dk.tanggal_berlaku < CURDATE() THEN 'Expired'
               WHEN dk.tanggal_berlaku BETWEEN CURDATE() AND DATE_ADD(CURDATE(), INTERVAL 30 DAY) THEN 'Expire Soon'
               ELSE 'Valid'
           END as validity_status,
           DATEDIFF(dk.tanggal_berlaku, CURDATE()) as days_left
    FROM dokumen_kendaraan dk
    JOIN kendaraan k ON dk.kendaraan_id = k.id
    $where_sql
    ORDER BY 
        CASE 
            WHEN dk.tanggal_berlaku < CURDATE() THEN 1
            WHEN dk.tanggal_berlaku BETWEEN CURDATE() AND DATE_ADD(CURDATE(), INTERVAL 30 DAY) THEN 2
            ELSE 3
        END,
        dk.tanggal_berlaku ASC, COALESCE(k.no_reg, k.no_polisi)
    LIMIT ? OFFSET ?
";

$stmt = $mysqli->prepare($sql);
$params[] = $limit;
$params[] = $offset;
$param_types .= 'ii';

if (!empty($params)) {
    $stmt->bind_param($param_types, ...$params);
}
$stmt->execute();
$documents_result = $stmt->get_result();
$stmt->close();

// Get total count for pagination
$count_sql = "
    SELECT COUNT(*) as total
    FROM dokumen_kendaraan dk
    JOIN kendaraan k ON dk.kendaraan_id = k.id
    $where_sql
";

$count_stmt = $mysqli->prepare($count_sql);
if (!empty($where_conditions)) {
    $count_params = array_slice($params, 0, -2); // Remove limit and offset
    $count_param_types = substr($param_types, 0, -2);
    if (!empty($count_params)) {
        $count_stmt->bind_param($count_param_types, ...$count_params);
    }
}
$count_stmt->execute();
$total_records = $count_stmt->get_result()->fetch_assoc()['total'];
$count_stmt->close();

$total_pages = ceil($total_records / $limit);

// Get statistics
$stats_sql = "
    SELECT 
        COUNT(*) as total,
        SUM(CASE WHEN status = 'Aktif' THEN 1 ELSE 0 END) as aktif,
        SUM(CASE WHEN status = 'Kadaluarsa' THEN 1 ELSE 0 END) as kadaluarsa,
        SUM(CASE WHEN status = 'Dalam Proses' THEN 1 ELSE 0 END) as proses,
        SUM(CASE WHEN tanggal_berlaku < CURDATE() THEN 1 ELSE 0 END) as expired,
        SUM(CASE WHEN tanggal_berlaku BETWEEN CURDATE() AND DATE_ADD(CURDATE(), INTERVAL 30 DAY) THEN 1 ELSE 0 END) as expire_soon
    FROM dokumen_kendaraan";
if ($current_role === 'driver') {
    if (!empty($accessible_vehicle_ids)) {
        $stats_sql .= " WHERE kendaraan_id IN (" . implode(',', array_map('intval', $accessible_vehicle_ids)) . ")";
    } else {
        $stats_sql .= " WHERE 1=0";
    }
}
$stats = ($mysqli->query($stats_sql) ?: false);
$stats = $stats ? $stats->fetch_assoc() : ['total'=>0,'aktif'=>0,'kadaluarsa'=>0,'proses'=>0,'expired'=>0,'expire_soon'=>0];

// Get vehicle list for filters and forms
$kendaraan_sql = "SELECT id, no_polisi, no_reg, merk, tipe FROM kendaraan";
if ($current_role === 'driver') {
    if (!empty($accessible_vehicle_ids)) {
        $kendaraan_sql .= " WHERE id IN (" . implode(',', array_map('intval', $accessible_vehicle_ids)) . ")";
    } else {
        $kendaraan_sql .= " WHERE 1=0";
    }
}
$kendaraan_sql .= " ORDER BY COALESCE(no_reg, no_polisi)";
$kendaraan_list = $mysqli->query($kendaraan_sql)->fetch_all(MYSQLI_ASSOC);

// Get document types for tabs
$jenis_dokumen = ['STNK', 'KIR / Uji Berkala', 'Asuransi', 'BPKB', 'Bukti Nomor Kendaraan Bermotor', 'Surat Ijin Mengemudi', 'Lainnya'];

function getStatusBadge($status) {
    $badges = [
        'Aktif' => 'success',
        'Kadaluarsa' => 'danger',
        'Dalam Proses' => 'warning'
    ];
    return $badges[$status] ?? 'secondary';
}

function getValidityBadge($validity) {
    $badges = [
        'Valid' => 'success',
        'Expire Soon' => 'warning',
        'Expired' => 'danger'
    ];
    return $badges[$validity] ?? 'secondary';
}
?>

<!-- Page header -->
<div class="p-4 mb-3 rounded text-white" style="background:linear-gradient(135deg,#12354a 0%,#1f6f8b 100%)">
    <div class="d-flex justify-content-between align-items-center flex-wrap gap-2">
        <div>
            <h1 class="mb-1 fs-4"><i class="fas fa-file-alt me-2"></i>Dokumen Kendaraan</h1>
            <p class="mb-0 opacity-75 small">Kelola dokumen dan surat-surat kendaraan dinas</p>
        </div>
        <?php if (can_admin()): ?>
        <button class="btn btn-light" data-bs-toggle="modal" data-bs-target="#addModal">
            <i class="fas fa-plus me-1"></i>Tambah Dokumen
        </button>
        <?php endif; ?>
    </div>
</div>

<?= $msg ?>

<!-- Stat cards -->
<div class="row g-3 mb-3">
    <?php
    $stat_cards = [
        ['tab'=>'all',         'label'=>'Total Dokumen',  'value'=>$stats['total'],        'color'=>'6c757d', 'icon'=>'folder-open',      'text'=>'text-secondary'],
        ['tab'=>'active',      'label'=>'Aktif & Berlaku','value'=>$stats['aktif'],        'color'=>'28a745', 'icon'=>'check-circle',      'text'=>'text-success'],
        ['tab'=>'expire_soon', 'label'=>'Akan Habis',     'value'=>$stats['expire_soon'],  'color'=>'ffc107', 'icon'=>'exclamation-circle','text'=>'text-warning', 'sub'=>'dalam 30 hari'],
        ['tab'=>'expired',     'label'=>'Kadaluarsa',     'value'=>$stats['expired'],      'color'=>'dc3545', 'icon'=>'times-circle',      'text'=>'text-danger'],
    ];
    foreach ($stat_cards as $sc): ?>
    <div class="col-6 col-md-3">
        <a href="?page=dokumen_kendaraan&tab=<?= $sc['tab'] ?>" class="text-decoration-none">
            <div class="card shadow-sm border-0 h-100" style="border-left:4px solid #<?= $sc['color'] ?>!important">
                <div class="card-body py-2 px-3">
                    <div class="d-flex justify-content-between align-items-center">
                        <div>
                            <div class="text-muted small"><?= $sc['label'] ?></div>
                            <h3 class="mb-0 fw-bold <?= $sc['text'] ?>"><?= number_format((int)$sc['value']) ?></h3>
                            <?php if (!empty($sc['sub'])): ?><div class="text-muted" style="font-size:.7rem"><?= $sc['sub'] ?></div><?php endif; ?>
                        </div>
                        <i class="fas fa-<?= $sc['icon'] ?> fa-2x <?= $sc['text'] ?> opacity-50"></i>
                    </div>
                </div>
            </div>
        </a>
    </div>
    <?php endforeach; ?>
</div>

<!-- Filter -->
<div class="card shadow-sm mb-3">
    <div class="card-body py-2">
        <form method="GET" class="row g-2 align-items-end">
            <input type="hidden" name="page" value="dokumen_kendaraan">
            <input type="hidden" name="tab" value="<?= htmlspecialchars($active_tab) ?>">
            <div class="col-sm-6 col-md-2">
                <label class="form-label form-label-sm mb-1">Jenis</label>
                <select name="jenis" class="form-select form-select-sm">
                    <option value="">Semua Jenis</option>
                    <?php foreach ($jenis_dokumen as $jenis): ?>
                        <option value="<?= htmlspecialchars($jenis) ?>" <?= $filter_jenis === $jenis ? 'selected' : '' ?>><?= htmlspecialchars($jenis) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-sm-6 col-md-3">
                <label class="form-label form-label-sm mb-1">Kendaraan</label>
                <select name="kendaraan" class="form-select form-select-sm">
                    <option value="">Semua Kendaraan</option>
                    <?php foreach ($kendaraan_list as $kendaraan): ?>
                        <option value="<?= $kendaraan['id'] ?>" <?= $filter_kendaraan == $kendaraan['id'] ? 'selected' : '' ?>>
                            <?= htmlspecialchars((($kendaraan['no_reg'] ?? '') !== '' ? $kendaraan['no_reg'] : ($kendaraan['no_polisi'] ?? '-')) . ' — ' . $kendaraan['merk'] . ' ' . $kendaraan['tipe']) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-sm-6 col-md-2">
                <label class="form-label form-label-sm mb-1">Status</label>
                <select name="status" class="form-select form-select-sm">
                    <option value="">Semua Status</option>
                    <option value="Aktif" <?= $filter_status === 'Aktif' ? 'selected' : '' ?>>Aktif</option>
                    <option value="Kadaluarsa" <?= $filter_status === 'Kadaluarsa' ? 'selected' : '' ?>>Kadaluarsa</option>
                    <option value="Dalam Proses" <?= $filter_status === 'Dalam Proses' ? 'selected' : '' ?>>Dalam Proses</option>
                </select>
            </div>
            <div class="col-sm-6 col-md-4">
                <label class="form-label form-label-sm mb-1">Cari</label>
                <div class="input-group input-group-sm">
                    <input type="text" name="search" class="form-control" placeholder="Nomor, instansi, no.reg..." value="<?= htmlspecialchars($search) ?>">
                    <button type="submit" class="btn btn-primary"><i class="fas fa-search"></i></button>
                    <?php if ($filter_jenis || $filter_kendaraan || $filter_status || $search): ?>
                        <a href="?page=dokumen_kendaraan&tab=<?= htmlspecialchars($active_tab) ?>" class="btn btn-outline-secondary" title="Reset"><i class="fas fa-times"></i></a>
                    <?php endif; ?>
                </div>
            </div>
        </form>
    </div>
</div>

<!-- Tabs + table -->
<div class="card shadow-sm">
    <div class="card-header p-0 bg-white border-bottom">
        <ul class="nav nav-tabs border-0 px-2 pt-1" role="tablist">
            <?php
            $tabs = [
                ['key'=>'all',         'label'=>'Semua',       'count'=>$stats['total'],       'icon'=>'list',                 'badge'=>'secondary'],
                ['key'=>'active',      'label'=>'Aktif',        'count'=>$stats['aktif'],       'icon'=>'check-circle',         'badge'=>'success'],
                ['key'=>'expire_soon', 'label'=>'Akan Habis',   'count'=>$stats['expire_soon'], 'icon'=>'exclamation-triangle', 'badge'=>'warning text-dark'],
                ['key'=>'expired',     'label'=>'Kadaluarsa',   'count'=>$stats['expired'],     'icon'=>'times-circle',         'badge'=>'danger'],
            ];
            $tab_base = '?page=dokumen_kendaraan' . ($filter_kendaraan ? '&kendaraan='.urlencode($filter_kendaraan) : '');
            foreach ($tabs as $t):
            ?>
            <li class="nav-item">
                <a class="nav-link <?= $active_tab === $t['key'] ? 'active fw-semibold' : '' ?>"
                   href="<?= $tab_base ?>&tab=<?= $t['key'] ?>">
                    <i class="fas fa-<?= $t['icon'] ?> me-1 small"></i><?= $t['label'] ?>
                    <?php if ((int)$t['count'] > 0): ?>
                        <span class="badge bg-<?= $t['badge'] ?> ms-1" style="font-size:.7rem"><?= $t['count'] ?></span>
                    <?php endif; ?>
                </a>
            </li>
            <?php endforeach; ?>
        </ul>
    </div>

    <div class="card-body p-0">
        <?php if ($documents_result->num_rows > 0): ?>
        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0" style="font-size:.875rem">
                <thead class="table-dark">
                    <tr>
                        <th>Kendaraan</th>
                        <th>Jenis &amp; Nomor</th>
                        <th>Instansi</th>
                        <th>Terbit</th>
                        <th class="text-center">Berlaku S/D</th>
                        <th class="text-center">Status</th>
                        <th class="text-center">File</th>
                        <?php if (can_admin()): ?><th class="text-center" width="80">Aksi</th><?php endif; ?>
                    </tr>
                </thead>
                <tbody>
                    <?php while ($row = $documents_result->fetch_assoc()):
                        $row_bg = match($row['validity_status']) {
                            'Expired'     => 'table-danger',
                            'Expire Soon' => 'table-warning',
                            default       => '',
                        };
                        $jenis_badge_color = 'info';
                        foreach (['STNK'=>'primary','KIR'=>'info','Asuransi'=>'success','BPKB'=>'secondary','SIM'=>'dark'] as $kw => $clr) {
                            if (stripos($row['jenis_dokumen'], $kw) !== false) { $jenis_badge_color = $clr; break; }
                        }
                    ?>
                    <tr class="<?= $row_bg ?>">
                        <td>
                            <div class="fw-semibold"><?= htmlspecialchars(($row['no_reg'] ?? '') !== '' ? $row['no_reg'] : ($row['no_polisi'] ?? '-')) ?></div>
                            <div class="text-muted small"><?= htmlspecialchars($row['merk'] . ' ' . $row['tipe']) ?></div>
                        </td>
                        <td>
                            <span class="badge bg-<?= $jenis_badge_color ?> mb-1"><?= htmlspecialchars($row['jenis_dokumen']) ?></span>
                            <div class="fw-semibold"><?= htmlspecialchars($row['nomor_dokumen']) ?></div>
                        </td>
                        <td class="text-muted small"><?= htmlspecialchars($row['instansi_penerbit'] ?: '—') ?></td>
                        <td class="small"><?= $row['tanggal_terbit'] ? date('d/m/Y', strtotime($row['tanggal_terbit'])) : '—' ?></td>
                        <td class="text-center">
                            <div class="fw-semibold small"><?= $row['tanggal_berlaku'] ? date('d/m/Y', strtotime($row['tanggal_berlaku'])) : '—' ?></div>
                            <?php if ($row['validity_status'] === 'Expired'): ?>
                                <span class="badge bg-danger mt-1"><i class="fas fa-times me-1"></i>Kadaluarsa</span>
                            <?php elseif ($row['validity_status'] === 'Expire Soon'): ?>
                                <span class="badge bg-warning text-dark mt-1"><i class="fas fa-clock me-1"></i><?= $row['days_left'] ?> hari lagi</span>
                            <?php else: ?>
                                <span class="badge bg-success mt-1"><i class="fas fa-check me-1"></i>Berlaku</span>
                            <?php endif; ?>
                        </td>
                        <td class="text-center">
                            <span class="badge bg-<?= getStatusBadge($row['status']) ?>"><?= htmlspecialchars($row['status']) ?></span>
                        </td>
                        <td class="text-center">
                            <?php if ($row['file_dokumen']): ?>
                                <?php $ext = strtolower(pathinfo($row['file_dokumen'], PATHINFO_EXTENSION)); ?>
                                <a href="uploads/dokumen/<?= htmlspecialchars($row['file_dokumen']) ?>" target="_blank"
                                   class="btn btn-sm btn-outline-<?= $ext === 'pdf' ? 'danger' : 'primary' ?>" title="Lihat / Unduh">
                                    <i class="fas fa-<?= $ext === 'pdf' ? 'file-pdf' : 'image' ?>"></i>
                                </a>
                            <?php else: ?>
                                <span class="text-muted">—</span>
                            <?php endif; ?>
                        </td>
                        <?php if (can_admin()): ?>
                        <td class="text-center">
                            <div class="btn-group btn-group-sm">
                                <a href="?page=dokumen_kendaraan_edit&id=<?= $row['id'] ?>" class="btn btn-outline-primary" title="Edit"><i class="fas fa-edit"></i></a>
                                <button class="btn btn-outline-danger" onclick="deleteDocument(<?= $row['id'] ?>)" title="Hapus"><i class="fas fa-trash"></i></button>
                            </div>
                        </td>
                        <?php endif; ?>
                    </tr>
                    <?php endwhile; ?>
                </tbody>
            </table>
        </div>

        <?php if ($total_pages > 1): ?>
        <div class="d-flex justify-content-between align-items-center px-3 py-2 border-top flex-wrap gap-2">
            <div class="text-muted small">
                <?= min($total_records, $offset+1) ?>–<?= min($total_records, $offset+$limit) ?> dari <?= number_format($total_records) ?> dokumen
            </div>
            <nav>
                <ul class="pagination pagination-sm mb-0">
                    <?php if ($page > 1): ?>
                        <li class="page-item"><a class="page-link" href="?page=dokumen_kendaraan&tab=<?= urlencode($active_tab) ?>&page_num=<?= $page-1 ?>&jenis=<?= urlencode($filter_jenis) ?>&kendaraan=<?= urlencode($filter_kendaraan) ?>&status=<?= urlencode($filter_status) ?>&search=<?= urlencode($search) ?>"><i class="fas fa-angle-left"></i></a></li>
                    <?php endif; ?>
                    <?php for ($i = max(1,$page-2); $i <= min($total_pages,$page+2); $i++): ?>
                        <li class="page-item <?= $i===$page?'active':'' ?>"><a class="page-link" href="?page=dokumen_kendaraan&tab=<?= urlencode($active_tab) ?>&page_num=<?= $i ?>&jenis=<?= urlencode($filter_jenis) ?>&kendaraan=<?= urlencode($filter_kendaraan) ?>&status=<?= urlencode($filter_status) ?>&search=<?= urlencode($search) ?>"><?= $i ?></a></li>
                    <?php endfor; ?>
                    <?php if ($page < $total_pages): ?>
                        <li class="page-item"><a class="page-link" href="?page=dokumen_kendaraan&tab=<?= urlencode($active_tab) ?>&page_num=<?= $page+1 ?>&jenis=<?= urlencode($filter_jenis) ?>&kendaraan=<?= urlencode($filter_kendaraan) ?>&status=<?= urlencode($filter_status) ?>&search=<?= urlencode($search) ?>"><i class="fas fa-angle-right"></i></a></li>
                    <?php endif; ?>
                </ul>
            </nav>
        </div>
        <?php endif; ?>

        <?php else: ?>
        <div class="text-center py-5">
            <i class="fas fa-file-alt fa-3x text-muted mb-3 d-block"></i>
            <h5 class="text-muted">Tidak ada dokumen ditemukan</h5>
            <p class="text-muted small">
                <?php if ($filter_jenis || $filter_kendaraan || $filter_status || $search || $active_tab !== 'all'): ?>
                    Tidak ada dokumen yang cocok dengan filter yang dipilih. <a href="?page=dokumen_kendaraan">Reset</a>
                <?php else: ?>
                    Belum ada dokumen kendaraan yang terdaftar.
                <?php endif; ?>
            </p>
            <?php if (can_admin()): ?>
            <button class="btn btn-primary btn-sm mt-1" data-bs-toggle="modal" data-bs-target="#addModal">
                <i class="fas fa-plus me-1"></i>Tambah Dokumen Pertama
            </button>
            <?php endif; ?>
        </div>
        <?php endif; ?>
    </div>
</div>

<?php if (can_admin()): ?>
<!-- Add Modal -->
<div class="modal fade" id="addModal" tabindex="-1">
    <div class="modal-dialog modal-lg modal-dialog-scrollable">
        <div class="modal-content">
            <form method="POST" action="?page=dokumen_kendaraan&action=add" enctype="multipart/form-data">
                <input type="hidden" name="csrf_token" value="<?= generate_csrf_token() ?>">
                <div class="modal-header bg-dark text-white">
                    <h5 class="modal-title"><i class="fas fa-plus-circle me-2"></i>Tambah Dokumen Kendaraan</h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <div class="row g-3">
                        <div class="col-md-6">
                            <label class="form-label form-label-sm">Kendaraan <span class="text-danger">*</span></label>
                            <select name="kendaraan_id" class="form-select form-select-sm" required>
                                <option value="">— Pilih Kendaraan —</option>
                                <?php foreach ($kendaraan_list as $kendaraan): ?>
                                    <option value="<?= $kendaraan['id'] ?>">
                                        <?= htmlspecialchars((($kendaraan['no_reg'] ?? '') !== '' ? $kendaraan['no_reg'] : ($kendaraan['no_polisi'] ?? '-')) . ' — ' . $kendaraan['merk'] . ' ' . $kendaraan['tipe']) ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label form-label-sm">Jenis Dokumen <span class="text-danger">*</span></label>
                            <select name="jenis_dokumen" class="form-select form-select-sm" required>
                                <option value="">— Pilih Jenis —</option>
                                <?php foreach ($jenis_dokumen as $jenis): ?>
                                    <option value="<?= htmlspecialchars($jenis) ?>"><?= htmlspecialchars($jenis) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label form-label-sm">Nomor Dokumen <span class="text-danger">*</span></label>
                            <input type="text" name="nomor_dokumen" class="form-control form-control-sm" required placeholder="Nomor seri / referensi dokumen">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label form-label-sm">Instansi Penerbit</label>
                            <input type="text" name="instansi_penerbit" class="form-control form-control-sm" placeholder="Contoh: Samsat, Dishub...">
                        </div>
                        <div class="col-md-4">
                            <label class="form-label form-label-sm">Tanggal Terbit <span class="text-danger">*</span></label>
                            <input type="date" name="tanggal_terbit" class="form-control form-control-sm" required>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label form-label-sm">Tanggal Berlaku <span class="text-danger">*</span></label>
                            <input type="date" name="tanggal_berlaku" class="form-control form-control-sm" required>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label form-label-sm">Status <span class="text-danger">*</span></label>
                            <select name="status" class="form-select form-select-sm" required>
                                <option value="">— Pilih —</option>
                                <option value="Aktif">Aktif</option>
                                <option value="Kadaluarsa">Kadaluarsa</option>
                                <option value="Dalam Proses">Dalam Proses</option>
                            </select>
                        </div>
                        <div class="col-12">
                            <label class="form-label form-label-sm">Upload File <small class="text-muted">(JPG, PNG, PDF — maks 10 MB)</small></label>
                            <input type="file" name="file_dokumen" class="form-control form-control-sm" accept=".jpg,.jpeg,.png,.pdf">
                        </div>
                        <div class="col-12">
                            <label class="form-label form-label-sm">Keterangan</label>
                            <textarea name="keterangan" class="form-control form-control-sm" rows="2" placeholder="Catatan tambahan (opsional)"></textarea>
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary btn-sm" data-bs-dismiss="modal">Batal</button>
                    <button type="submit" class="btn btn-primary btn-sm"><i class="fas fa-save me-1"></i>Simpan</button>
                </div>
            </form>
        </div>
    </div>
</div>
<?php endif; ?>

<!-- Delete confirmation modal -->
<div class="modal fade" id="deleteModal" tabindex="-1">
    <div class="modal-dialog modal-sm">
        <div class="modal-content">
            <form method="POST" action="?page=dokumen_kendaraan&action=delete">
                <input type="hidden" name="csrf_token" value="<?= generate_csrf_token() ?>">
                <input type="hidden" name="id" id="delete_id">
                <div class="modal-header bg-danger text-white">
                    <h6 class="modal-title"><i class="fas fa-trash me-2"></i>Hapus Dokumen</h6>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <p class="mb-0">Yakin ingin menghapus dokumen ini? Tindakan ini tidak dapat dibatalkan.</p>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary btn-sm" data-bs-dismiss="modal">Batal</button>
                    <button type="submit" class="btn btn-danger btn-sm"><i class="fas fa-trash me-1"></i>Hapus</button>
                </div>
            </form>
        </div>
    </div>
</div>

<style>
.nav-tabs .nav-link { color: #495057; border: none; border-bottom: 3px solid transparent; padding: .5rem .75rem; }
.nav-tabs .nav-link:hover { border-bottom-color: #dee2e6; background: #f8f9fa; }
.nav-tabs .nav-link.active { color: #1f6f8b; background: transparent; border-bottom-color: #1f6f8b; }
.table-danger td, .table-warning td { opacity: .92; }
</style>

<script>
document.addEventListener('DOMContentLoaded', function() {
    var addModal = document.getElementById('addModal');
    if (addModal && addModal.parentElement !== document.body) document.body.appendChild(addModal);
    var delModal = document.getElementById('deleteModal');
    if (delModal && delModal.parentElement !== document.body) document.body.appendChild(delModal);
});

function deleteDocument(id) {
    document.getElementById('delete_id').value = id;
    new bootstrap.Modal(document.getElementById('deleteModal')).show();
}
</script>


