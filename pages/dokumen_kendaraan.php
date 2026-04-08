<?php
require_once 'includes/auth.php';
require_login();

$current_role = get_current_role();
$current_user_id = get_current_user_id();

// Check access
if (!in_array($current_role, ['admin', 'operator'])) {
    header('Location: pages/403.php');
    exit;
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
                $kendaraan_id = (int)$_POST['kendaraan_id'];
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
$stats = $mysqli->query("
    SELECT 
        COUNT(*) as total,
        SUM(CASE WHEN status = 'Aktif' THEN 1 ELSE 0 END) as aktif,
        SUM(CASE WHEN status = 'Kadaluarsa' THEN 1 ELSE 0 END) as kadaluarsa,
        SUM(CASE WHEN status = 'Dalam Proses' THEN 1 ELSE 0 END) as proses,
        SUM(CASE WHEN tanggal_berlaku < CURDATE() THEN 1 ELSE 0 END) as expired,
        SUM(CASE WHEN tanggal_berlaku BETWEEN CURDATE() AND DATE_ADD(CURDATE(), INTERVAL 30 DAY) THEN 1 ELSE 0 END) as expire_soon
    FROM dokumen_kendaraan
")->fetch_assoc();

// Get vehicle list for filters and forms
$kendaraan_list = $mysqli->query("SELECT id, no_polisi, no_reg, merk, tipe FROM kendaraan ORDER BY COALESCE(no_reg, no_polisi)")->fetch_all(MYSQLI_ASSOC);

// Get document types for tabs
$jenis_dokumen = ['STNK', 'BPKB', 'KIR', 'Pajak', 'Asuransi', 'SIM Driver', 'Lainnya'];

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

<div class="page-header gradient-header rounded">
            <h1><i class="fas fa-file-alt me-2"></i>Dokumen Kendaraan</h1>
            <p class="mb-0">Kelola dokumen dan surat-surat kendaraan</p>
        <div class="header-actions">
            <button class="btn btn-light btn-lg" data-bs-toggle="modal" data-bs-target="#addModal">
                <i class="fas fa-plus me-1"></i> Tambah Dokumen
            </button>
        </div>
</div>



<div class="card-body border-bottom">
        <form method="GET" class="row g-3">
            <input type="hidden" name="page" value="dokumen_kendaraan">
            <input type="hidden" name="tab" value="<?= $active_tab ?>">
            
            <div class="col-md-2">
                <label class="form-label">Jenis Dokumen</label>
                <select name="jenis" class="form-control">
                    <option value="">Semua Jenis</option>
                    <?php foreach ($jenis_dokumen as $jenis): ?>
                        <option value="<?= $jenis ?>" <?= $filter_jenis === $jenis ? 'selected' : '' ?>><?= $jenis ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            
            <div class="col-md-3">
                <label class="form-label">Kendaraan</label>
                <select name="kendaraan" class="form-control">
                    <option value="">Semua Kendaraan</option>
                        <?php foreach ($kendaraan_list as $kendaraan): ?>
                        <option value="<?= $kendaraan['id'] ?>" <?= $filter_kendaraan == $kendaraan['id'] ? 'selected' : '' ?>>
                            <?= htmlspecialchars((($kendaraan['no_reg'] ?? '') !== '' ? $kendaraan['no_reg'] : ($kendaraan['no_polisi'] ?? '-')) . ' - ' . $kendaraan['merk'] . ' ' . $kendaraan['tipe']) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            
            <div class="col-md-2">
                <label class="form-label">Status</label>
                <select name="status" class="form-control">
                    <option value="">Semua Status</option>
                    <option value="Aktif" <?= $filter_status === 'Aktif' ? 'selected' : '' ?>>Aktif</option>
                    <option value="Kadaluarsa" <?= $filter_status === 'Kadaluarsa' ? 'selected' : '' ?>>Kadaluarsa</option>
                    <option value="Dalam Proses" <?= $filter_status === 'Dalam Proses' ? 'selected' : '' ?>>Dalam Proses</option>
                </select>
            </div>
            
            <div class="col-md-4">
                <label class="form-label">Pencarian</label>
                <div class="input-group">
                    <input type="text" name="search" class="form-control" placeholder="Cari nomor dokumen, instansi..." value="<?= htmlspecialchars($search) ?>">
                    <button type="submit" class="btn btn-primary">
                        <i class="fas fa-search"></i>
                    </button>
                </div>
            </div>
            
            <div class="col-md-1">
                <label class="form-label">&nbsp;</label>
                <a href="?page=dokumen_kendaraan&tab=<?= $active_tab ?>" class="btn btn-secondary w-100">
                    <i class="fas fa-undo"></i>
                </a>
            </div>
        </form>
    </div>

<!-- Tabs -->
<div class="card mb-4">
    <div class="card-header">
        <ul class="nav nav-tabs card-header-tabs" role="tablist">
            <li class="nav-item">
                <a class="nav-link <?= $active_tab === 'all' ? 'active' : '' ?>" href="?page=dokumen_kendaraan&tab=all">
                    <i class="fas fa-list me-1"></i> Semua (<?= $stats['total'] ?>)
                </a>
            </li>
            <li class="nav-item">
                <a class="nav-link <?= $active_tab === 'active' ? 'active' : '' ?>" href="?page=dokumen_kendaraan&tab=active">
                    <i class="fas fa-check me-1"></i> Aktif (<?= $stats['aktif'] ?>)
                </a>
            </li>
            <li class="nav-item">
                <a class="nav-link <?= $active_tab === 'expire_soon' ? 'active' : '' ?>" href="?page=dokumen_kendaraan&tab=expire_soon">
                    <i class="fas fa-exclamation-triangle me-1"></i> Akan Habis (<?= $stats['expire_soon'] ?>)
                </a>
            </li>
            <li class="nav-item">
                <a class="nav-link <?= $active_tab === 'expired' ? 'active' : '' ?>" href="?page=dokumen_kendaraan&tab=expired">
                    <i class="fas fa-times me-1"></i> Kadaluarsa (<?= $stats['expired'] ?>)
                </a>
            </li>
        </ul>
    </div>
    
    <!-- Filters -->
    
</div>

<!-- Documents List -->
<div class="card">
    <div class="card-body p-0">
        <?php if ($documents_result->num_rows > 0): ?>
            <div class="table-responsive">
                <table class="table table-hover mb-0">
                    <thead class="table-light">
                        <tr>
                            <th>Kendaraan</th>
                            <th>Jenis Dokumen</th>
                            <th>Nomor Dokumen</th>
                            <th>Tanggal Terbit</th>
                            <th>Tanggal Berlaku</th>
                            <th>Status</th>
                            <th>File</th>
                            <th>Aksi</th>
                        </tr>
                    </thead>
                    <tbody>
                                <?php while ($row = $documents_result->fetch_assoc()): ?>
                            <tr>
                                <td>
                                    <strong><?= htmlspecialchars(($row['no_reg'] ?? '') !== '' ? $row['no_reg'] : ($row['no_polisi'] ?? '-')) ?></strong>
                                    <?php if (!empty($row['no_polisi'])): ?>
                                        <br><small class="text-muted">Nopol: <?= htmlspecialchars($row['no_polisi']) ?></small>
                                    <?php endif; ?>
                                    <br><small class="text-muted"><?= htmlspecialchars($row['merk'] . ' ' . $row['tipe']) ?></small>
                                </td>
                                <td>
                                    <span class="badge bg-info"><?= htmlspecialchars($row['jenis_dokumen']) ?></span>
                                </td>
                                <td>
                                    <strong><?= htmlspecialchars($row['nomor_dokumen']) ?></strong>
                                    <?php if ($row['instansi_penerbit']): ?>
                                        <br><small class="text-muted"><?= htmlspecialchars($row['instansi_penerbit']) ?></small>
                                    <?php endif; ?>
                                </td>
                                <td><?= date('d/m/Y', strtotime($row['tanggal_terbit'])) ?></td>
                                <td>
                                    <?= date('d/m/Y', strtotime($row['tanggal_berlaku'])) ?>
                                    <br>
                                    <span class="badge bg-<?= getValidityBadge($row['validity_status']) ?> small">
                                        <?php if ($row['validity_status'] === 'Expired'): ?>
                                            Kadaluarsa
                                        <?php elseif ($row['validity_status'] === 'Expire Soon'): ?>
                                            <?= $row['days_left'] ?> hari lagi
                                        <?php else: ?>
                                            Berlaku
                                        <?php endif; ?>
                                    </span>
                                </td>
                                <td>
                                    <span class="badge bg-<?= getStatusBadge($row['status']) ?>">
                                        <?= $row['status'] ?>
                                    </span>
                                </td>
                                <td>
                                    <?php if ($row['file_dokumen']): ?>
                                        <a href="uploads/dokumen/<?= htmlspecialchars($row['file_dokumen']) ?>" target="_blank" class="btn btn-sm btn-outline-primary">
                                            <i class="fas fa-file-download"></i> Lihat
                                        </a>
                                    <?php else: ?>
                                        <small class="text-muted">Tidak ada file</small>
                                    <?php endif; ?>
                                </td>
                                <td>
                                        <div class="btn-group btn-group-sm">
                                            <a href="?page=dokumen_kendaraan_edit&id=<?= $row['id'] ?>" class="btn btn-outline-primary">
                                                <i class="fas fa-edit"></i>
                                            </a>
                                            <button class="btn btn-outline-danger" onclick="deleteDocument(<?= $row['id'] ?>)">
                                                <i class="fas fa-trash"></i>
                                            </button>
                                        </div>
                                </td>
                            </tr>
                        <?php endwhile; ?>
                    </tbody>
                </table>
            </div>
    <?php else: ?>
            <div class="text-center p-5">
                <i class="fas fa-file-alt fa-3x text-muted mb-3"></i>
                <h5>Tidak ada dokumen</h5>
                <p class="text-muted">Belum ada dokumen yang terdaftar</p>
            </div>
        <?php endif; ?>
    </div>
</div>

<!-- Pagination -->
<?php if ($total_pages > 1): ?>
    <div class="mt-4">
        <nav aria-label="Page navigation">
            <ul class="pagination justify-content-center">
                <?php for ($i = 1; $i <= $total_pages; $i++): ?>
                    <li class="page-item <?= $i === $page ? 'active' : '' ?>">
                        <a class="page-link" href="?page=dokumen_kendaraan&tab=<?= $active_tab ?>&page_num=<?= $i ?>&jenis=<?= urlencode($filter_jenis) ?>&kendaraan=<?= urlencode($filter_kendaraan) ?>&status=<?= urlencode($filter_status) ?>&search=<?= urlencode($search) ?>">
                            <?= $i ?>
                        </a>
                    </li>
                <?php endfor; ?>
            </ul>
        </nav>
    </div>
<?php endif; ?>



<!-- Add Modal -->
<div class="modal fade" id="addModal" tabindex="-1" data-bs-backdrop="false">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <form method="POST" action="?page=dokumen_kendaraan&action=add" enctype="multipart/form-data">
                <input type="hidden" name="csrf_token" value="<?= generate_csrf_token() ?>">
                
                <div class="modal-header">
                    <h5 class="modal-title">Tambah Dokumen Kendaraan</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                
                <div class="modal-body">
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Kendaraan <span class="text-danger">*</span></label>
                            <select name="kendaraan_id" class="form-control" required>
                                <option value="">Pilih Kendaraan</option>
                                <?php foreach ($kendaraan_list as $kendaraan): ?>
                                    <option value="<?= $kendaraan['id'] ?>">
                                        <?= htmlspecialchars((($kendaraan['no_reg'] ?? '') !== '' ? $kendaraan['no_reg'] : ($kendaraan['no_polisi'] ?? '-')) . ' - ' . $kendaraan['merk'] . ' ' . $kendaraan['tipe']) ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Jenis Dokumen <span class="text-danger">*</span></label>
                            <select name="jenis_dokumen" class="form-control" required>
                                <option value="">Pilih Jenis</option>
                                <?php foreach ($jenis_dokumen as $jenis): ?>
                                    <option value="<?= $jenis ?>"><?= $jenis ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Nomor Dokumen <span class="text-danger">*</span></label>
                            <input type="text" name="nomor_dokumen" class="form-control" required>
                        </div>
                        
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Instansi Penerbit</label>
                            <input type="text" name="instansi_penerbit" class="form-control">
                        </div>
                        
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Tanggal Terbit <span class="text-danger">*</span></label>
                            <input type="date" name="tanggal_terbit" class="form-control" required>
                        </div>
                        
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Tanggal Berlaku <span class="text-danger">*</span></label>
                            <input type="date" name="tanggal_berlaku" class="form-control" required>
                        </div>
                        
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Status <span class="text-danger">*</span></label>
                            <select name="status" class="form-control" required>
                                <option value="">Pilih Status</option>
                                <option value="Aktif">Aktif</option>
                                <option value="Kadaluarsa">Kadaluarsa</option>
                                <option value="Dalam Proses">Dalam Proses</option>
                            </select>
                        </div>
                        
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Upload File</label>
                            <input type="file" name="file_dokumen" class="form-control" accept=".jpg,.jpeg,.png,.pdf">
                            <small class="text-muted">Format: JPG, PNG, PDF. Maksimal 10MB</small>
                        </div>
                        
                        <div class="col-12 mb-3">
                            <label class="form-label">Keterangan</label>
                            <textarea name="keterangan" class="form-control" rows="3"></textarea>
                        </div>
                    </div>
                </div>
                
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Batal</button>
                    <button type="submit" class="btn btn-primary">Simpan</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Edit Modal -->
<div class="modal fade" id="editModal" tabindex="-1" data-bs-backdrop="false">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <form method="POST" action="?page=dokumen_kendaraan&action=edit" enctype="multipart/form-data">
                <input type="hidden" name="csrf_token" value="<?= generate_csrf_token() ?>">
                <input type="hidden" name="id" id="edit_id">
                
                <div class="modal-header">
                    <h5 class="modal-title">Edit Dokumen Kendaraan</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                
                <div class="modal-body" id="edit_form">
                    <!-- Content will be loaded via AJAX -->
                </div>
                
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Batal</button>
                    <button type="submit" class="btn btn-primary">Update</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Delete Modal -->
<div class="modal fade" id="deleteModal" tabindex="-1" data-bs-backdrop="false">
    <div class="modal-dialog">
        <div class="modal-content">
            <form method="POST" action="?page=dokumen_kendaraan&action=delete">
                <input type="hidden" name="csrf_token" value="<?= generate_csrf_token() ?>">
                <input type="hidden" name="id" id="delete_id">
                
                <div class="modal-header">
                    <h5 class="modal-title">Hapus Dokumen</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                
                <div class="modal-body">
                    <p>Apakah Anda yakin ingin menghapus dokumen ini? Tindakan ini tidak dapat dibatalkan.</p>
                </div>
                
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Batal</button>
                    <button type="submit" class="btn btn-danger">Hapus</button>
                </div>
            </form>
        </div>
    </div>
</div>

<style>
.gradient-header {
    background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
}

.nav-tabs .nav-link {
    color: #495057;
    border: none;
    border-bottom: 3px solid transparent;
}

.nav-tabs .nav-link:hover {
    border-color: transparent;
    border-bottom-color: #dee2e6;
}

.nav-tabs .nav-link.active {
    color: #495057;
    background-color: transparent;
    border-color: transparent;
    border-bottom-color: #007bff;
    font-weight: 600;
}

.card {
    border: none;
    box-shadow: 0 0 20px rgba(0,0,0,0.1);
}

.table th {
    border-top: none;
    font-weight: 600;
    color: #495057;
}

.btn-group-sm .btn {
    padding: 0.25rem 0.5rem;
    font-size: 0.875rem;
}

.badge {
    font-size: 0.75rem;
    padding: 0.35em 0.65em;
}

@media (max-width: 768px) {
    .col-md-1, .col-md-2, .col-md-3, .col-md-4, .col-md-6 {
        margin-bottom: 1rem;
    }
}
</style>

<script>
// Ensure Add modal is above everything and not trapped in a stacking context
document.addEventListener('DOMContentLoaded', function() {
    var addModal = document.getElementById('addModal');
    if (addModal && addModal.parentElement !== document.body) {
        document.body.appendChild(addModal);
    }
});

function editDocument(id) {
    // Load document data via AJAX
    fetch(`ajax/get_dokumen_detail.php?id=${id}`)
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                document.getElementById('edit_id').value = id;
                document.getElementById('edit_form').innerHTML = data.html;
                
                var modalEl = document.getElementById('editModal');
                var modal = new bootstrap.Modal(modalEl, {backdrop: false});
                modal.show();
            } else {
                alert('Error loading document data');
            }
        })
        .catch(error => {
            console.error('Error:', error);
            alert('Error loading document data');
        });
}

function deleteDocument(id) {
    document.getElementById('delete_id').value = id;
    var modalEl = document.getElementById('deleteModal');
    var modal = new bootstrap.Modal(modalEl, {backdrop: false});
    modal.show();
}
</script>

<style>
/* Make Add Document modal the top-most layer */
.modal-backdrop,
.modal-backdrop.show {
    z-index: 2990 !important;
}

#addModal.modal {
    z-index: 3000 !important;
}

@media (max-width: 576px) {
    /* slight offset on small screens if header overlaps */
    #addModal .modal-dialog { margin-top: 40px !important; }
}
</style>


