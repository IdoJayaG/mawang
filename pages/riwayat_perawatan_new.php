<?php
require_once 'includes/auth.php';
require_login();

$current_role = get_current_role();
$current_user_id = get_current_user_id();

// Check if user has access
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
$msg = '';

// Handle form submissions
if ($_POST) {
    if (!validate_csrf_token($_POST['csrf_token'] ?? '')) {
        $msg = '<div class="alert alert-danger">Token keamanan tidak valid!</div>';
    } else {
        switch ($action) {
            case 'add':
                $kendaraan_id = (int)$_POST['kendaraan_id'];
                if ($current_role === 'driver' && !in_array($kendaraan_id, $accessible_vehicle_ids, true)) {
                    $msg = '<div class="alert alert-danger">Anda tidak memiliki akses ke kendaraan tersebut.</div>';
                    break;
                }
                $jenis_perawatan = trim($_POST['jenis_perawatan']);
                $deskripsi = trim($_POST['deskripsi']);
                $jadwal_tanggal = $_POST['jadwal_tanggal'];
                // estimasi_biaya removed
                $prioritas = $_POST['prioritas'];
                $teknisi_id = !empty($_POST['teknisi_id']) ? (int)$_POST['teknisi_id'] : null;
                
                $stmt = $mysqli->prepare("INSERT INTO jadwal_perawatan (kendaraan_id, jenis_perawatan, deskripsi, jadwal_tanggal, prioritas, teknisi_id, status, created_by) VALUES (?, ?, ?, ?, ?, ?, 'Terjadwal', ?)");
                $stmt->bind_param('issssii', $kendaraan_id, $jenis_perawatan, $deskripsi, $jadwal_tanggal, $prioritas, $teknisi_id, $current_user_id);
                
                if ($stmt->execute()) {
                    $msg = '<div class="alert alert-success">Jadwal perawatan berhasil ditambahkan!</div>';
                } else {
                    $msg = '<div class="alert alert-danger">Error: ' . $stmt->error . '</div>';
                }
                $stmt->close();
                break;
                
            case 'update_status':
                $id = (int)$_POST['id'];
                if ($current_role === 'driver') {
                    $guard = $mysqli->prepare("SELECT kendaraan_id FROM jadwal_perawatan WHERE id = ? LIMIT 1");
                    if ($guard) {
                        $guard->bind_param('i', $id);
                        $guard->execute();
                        $guardRow = $guard->get_result()->fetch_assoc();
                        $guard->close();
                        $guardKendaraanId = (int)($guardRow['kendaraan_id'] ?? 0);
                        if ($guardKendaraanId <= 0 || !in_array($guardKendaraanId, $accessible_vehicle_ids, true)) {
                            $msg = '<div class="alert alert-danger">Anda tidak memiliki akses ke jadwal perawatan tersebut.</div>';
                            break;
                        }
                    }
                }
                $status = $_POST['status'];
                $tanggal_perawatan = $_POST['tanggal_perawatan'] ?? null;
                // biaya_aktual removed from input handling
                $keterangan = trim($_POST['keterangan'] ?? '');
                
                if ($status === 'Selesai') {
                    // Do not persist biaya_aktual; remove from UPDATE
                    $stmt = $mysqli->prepare("UPDATE jadwal_perawatan SET status = ?, tanggal_perawatan = ?, keterangan = ?, tanggal_selesai = NOW(), updated_by = ? WHERE id = ?");
                    $stmt->bind_param('sssii', $status, $tanggal_perawatan, $keterangan, $current_user_id, $id);
                } else {
                    $stmt = $mysqli->prepare("UPDATE jadwal_perawatan SET status = ?, keterangan = ?, updated_by = ? WHERE id = ?");
                    $stmt->bind_param('ssii', $status, $keterangan, $current_user_id, $id);
                }
                
                if ($stmt->execute()) {
                    $msg = '<div class="alert alert-success">Status perawatan berhasil diupdate!</div>';
                } else {
                    $msg = '<div class="alert alert-danger">Error: ' . $stmt->error . '</div>';
                }
                $stmt->close();
                break;
        }
    }
}

// Get filter parameters
$filter_status = $_GET['status'] ?? '';
$filter_kendaraan = $_GET['kendaraan'] ?? '';
$filter_tanggal = $_GET['tanggal'] ?? '';
$search = trim($_GET['search'] ?? '');

// Build query
$where_conditions = [];
$params = [];
$param_types = '';

if ($filter_status) {
    $where_conditions[] = "jp.status = ?";
    $params[] = $filter_status;
    $param_types .= 's';
}

if ($filter_kendaraan) {
    $where_conditions[] = "jp.kendaraan_id = ?";
    $params[] = (int)$filter_kendaraan;
    $param_types .= 'i';
}

if ($current_role === 'driver') {
    if (!empty($accessible_vehicle_ids)) {
        $placeholders = implode(',', array_fill(0, count($accessible_vehicle_ids), '?'));
        $where_conditions[] = "jp.kendaraan_id IN ($placeholders)";
        foreach ($accessible_vehicle_ids as $vid) {
            $params[] = (int)$vid;
            $param_types .= 'i';
        }
    } else {
        $where_conditions[] = '1=0';
    }
}

if ($filter_tanggal) {
    $where_conditions[] = "DATE(jp.jadwal_tanggal) = ?";
    $params[] = $filter_tanggal;
    $param_types .= 's';
}

if ($search) {
    $where_conditions[] = "(jp.jenis_perawatan LIKE ? OR jp.deskripsi LIKE ? OR k.no_polisi LIKE ?)";
    $search_term = "%$search%";
    $params = array_merge($params, [$search_term, $search_term, $search_term]);
    $param_types .= 'sss';
}

$where_sql = !empty($where_conditions) ? 'WHERE ' . implode(' AND ', $where_conditions) : '';

// Pagination
$page = max(1, (int)($_GET['page_num'] ?? 1));
$limit = 15;
$offset = ($page - 1) * $limit;

// Get perawatan data
$sql = "
    SELECT jp.*, k.no_polisi, k.merk, k.tipe, 
           t.nama as teknisi_nama,
           CASE 
               WHEN jp.status = 'Terjadwal' AND jp.jadwal_tanggal < CURDATE() THEN 'Terlambat'
               ELSE jp.status
           END as status_display
    FROM jadwal_perawatan jp
    JOIN kendaraan k ON jp.kendaraan_id = k.id
    LEFT JOIN pengguna t ON jp.teknisi_id = t.id
    $where_sql
    ORDER BY 
        CASE jp.status 
            WHEN 'Terjadwal' THEN 1
            WHEN 'Dalam Proses' THEN 2
            WHEN 'Selesai' THEN 3
            WHEN 'Ditunda' THEN 4
        END,
        jp.jadwal_tanggal ASC
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
$perawatan_result = $stmt->get_result();
$stmt->close();

// Get total count for pagination
$count_sql = "
    SELECT COUNT(*) as total
    FROM jadwal_perawatan jp
    JOIN kendaraan k ON jp.kendaraan_id = k.id
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
        SUM(CASE WHEN status = 'Terjadwal' THEN 1 ELSE 0 END) as terjadwal,
        SUM(CASE WHEN status = 'Dalam Proses' THEN 1 ELSE 0 END) as proses,
        SUM(CASE WHEN status = 'Selesai' THEN 1 ELSE 0 END) as selesai,
        SUM(CASE WHEN status = 'Terjadwal' AND jadwal_tanggal < CURDATE() THEN 1 ELSE 0 END) as terlambat
    FROM jadwal_perawatan
")->fetch_assoc();

// Get vehicle list for filters
$kendaraan_sql = "SELECT id, no_polisi, merk, tipe FROM kendaraan";
if ($current_role === 'driver') {
    if (!empty($accessible_vehicle_ids)) {
        $kendaraan_sql .= " WHERE id IN (" . implode(',', array_map('intval', $accessible_vehicle_ids)) . ")";
    } else {
        $kendaraan_sql .= " WHERE 1=0";
    }
}
$kendaraan_sql .= " ORDER BY no_polisi";
$kendaraan_list = $mysqli->query($kendaraan_sql)->fetch_all(MYSQLI_ASSOC);

// Get teknisi list for forms
$teknisi_list = $mysqli->query("SELECT id, nama FROM pengguna WHERE role = 'teknisi' ORDER BY nama")->fetch_all(MYSQLI_ASSOC);

function getStatusBadge($status) {
    $badges = [
        'Terjadwal' => 'warning',
        'Dalam Proses' => 'info', 
        'Selesai' => 'success',
        'Ditunda' => 'secondary',
        'Terlambat' => 'danger'
    ];
    return $badges[$status] ?? 'secondary';
}

function getPriorityBadge($priority) {
    $badges = [
        'Tinggi' => 'danger',
        'Sedang' => 'warning',
        'Rendah' => 'info'
    ];
    return $badges[$priority] ?? 'secondary';
}
?>

<div class="page-header">
    <div class="row align-items-center">
        <div class="col-md-8">
            <h1><i class="fas fa-tools me-2"></i>Riwayat Perawatan Kendaraan</h1>
            <p class="mb-0">Kelola jadwal dan riwayat perawatan kendaraan</p>
        </div>
        <div class="col-md-4 text-end">
            <button class="btn btn-light btn-lg" data-bs-toggle="modal" data-bs-target="#addModal">
                <i class="fas fa-plus me-1"></i> Tambah Jadwal
            </button>
        </div>
    </div>
</div>

<?= $msg ?>

<!-- Statistics Cards -->
<div class="row mb-4">
    <div class="col-lg-2 col-md-4 col-6 mb-3">
        <div class="card text-center h-100">
            <div class="card-body">
                <i class="fas fa-list-alt text-primary fa-2x mb-2"></i>
                <h4 class="card-title"><?= $stats['total'] ?></h4>
                <p class="card-text text-muted">Total</p>
            </div>
        </div>
    </div>
    <div class="col-lg-2 col-md-4 col-6 mb-3">
        <div class="card text-center h-100">
            <div class="card-body">
                <i class="fas fa-clock text-warning fa-2x mb-2"></i>
                <h4 class="card-title"><?= $stats['terjadwal'] ?></h4>
                <p class="card-text text-muted">Terjadwal</p>
            </div>
        </div>
    </div>
    <div class="col-lg-2 col-md-4 col-6 mb-3">
        <div class="card text-center h-100">
            <div class="card-body">
                <i class="fas fa-cog text-info fa-2x mb-2"></i>
                <h4 class="card-title"><?= $stats['proses'] ?></h4>
                <p class="card-text text-muted">Dalam Proses</p>
            </div>
        </div>
    </div>
    <div class="col-lg-2 col-md-4 col-6 mb-3">
        <div class="card text-center h-100">
            <div class="card-body">
                <i class="fas fa-check-circle text-success fa-2x mb-2"></i>
                <h4 class="card-title"><?= $stats['selesai'] ?></h4>
                <p class="card-text text-muted">Selesai</p>
            </div>
        </div>
    </div>
    <div class="col-lg-2 col-md-4 col-6 mb-3">
        <div class="card text-center h-100">
            <div class="card-body">
                <i class="fas fa-exclamation-triangle text-danger fa-2x mb-2"></i>
                <h4 class="card-title"><?= $stats['terlambat'] ?></h4>
                <p class="card-text text-muted">Terlambat</p>
            </div>
        </div>
    </div>
</div>

<!-- Filters -->
<div class="card mb-4">
    <div class="card-body">
        <form method="GET" class="row g-3">
            <input type="hidden" name="page" value="riwayat_perawatan">
            
            <div class="col-md-3">
                <label class="form-label">Status</label>
                <select name="status" class="form-control">
                    <option value="">Semua Status</option>
                    <option value="Terjadwal" <?= $filter_status === 'Terjadwal' ? 'selected' : '' ?>>Terjadwal</option>
                    <option value="Dalam Proses" <?= $filter_status === 'Dalam Proses' ? 'selected' : '' ?>>Dalam Proses</option>
                    <option value="Selesai" <?= $filter_status === 'Selesai' ? 'selected' : '' ?>>Selesai</option>
                    <option value="Ditunda" <?= $filter_status === 'Ditunda' ? 'selected' : '' ?>>Ditunda</option>
                </select>
            </div>
            
            <div class="col-md-3">
                <label class="form-label">Kendaraan</label>
                <select name="kendaraan" class="form-control">
                    <option value="">Semua Kendaraan</option>
                    <?php foreach ($kendaraan_list as $kendaraan): ?>
                        <option value="<?= $kendaraan['id'] ?>" <?= $filter_kendaraan == $kendaraan['id'] ? 'selected' : '' ?>>
                            <?= htmlspecialchars($kendaraan['no_polisi'] . ' - ' . $kendaraan['merk'] . ' ' . $kendaraan['tipe']) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            
            <div class="col-md-2">
                <label class="form-label">Tanggal</label>
                <input type="date" name="tanggal" class="form-control" value="<?= htmlspecialchars($filter_tanggal) ?>">
            </div>
            
            <div class="col-md-3">
                <label class="form-label">Pencarian</label>
                <input type="text" name="search" class="form-control" placeholder="Cari jenis perawatan..." value="<?= htmlspecialchars($search) ?>">
            </div>
            
            <div class="col-md-1">
                <label class="form-label">&nbsp;</label>
                <button type="submit" class="btn btn-primary w-100">
                    <i class="fas fa-search"></i>
                </button>
            </div>
        </form>
    </div>
</div>

<!-- Perawatan List -->
<div class="card">
    <div class="card-header">
        <h5 class="mb-0">Daftar Perawatan</h5>
    </div>
    <div class="card-body p-0">
        <?php if ($perawatan_result->num_rows > 0): ?>
            <div class="table-responsive">
                <table class="table table-hover mb-0">
                    <thead class="table-light">
                        <tr>
                            <th>Kendaraan</th>
                            <th>Jenis Perawatan</th>
                            <th>Jadwal</th>
                            <th>Status</th>
                            <th>Prioritas</th>
                            <th>Teknisi</th>
                            <th>Biaya</th>
                            <th>Aksi</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php while ($row = $perawatan_result->fetch_assoc()): ?>
                            <tr>
                                <td>
                                    <strong><?= htmlspecialchars($row['no_polisi']) ?></strong>
                                    <br><small class="text-muted"><?= htmlspecialchars($row['merk'] . ' ' . $row['tipe']) ?></small>
                                </td>
                                <td>
                                    <strong><?= htmlspecialchars($row['jenis_perawatan']) ?></strong>
                                    <?php if ($row['deskripsi']): ?>
                                        <br><small class="text-muted"><?= htmlspecialchars(substr($row['deskripsi'], 0, 50)) ?>...</small>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <?= date('d/m/Y', strtotime($row['jadwal_tanggal'])) ?>
                                    <?php if ($row['tanggal_perawatan']): ?>
                                        <br><small class="text-success">Dikerjakan: <?= date('d/m/Y', strtotime($row['tanggal_perawatan'])) ?></small>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <span class="badge bg-<?= getStatusBadge($row['status_display']) ?>">
                                        <?= $row['status_display'] ?>
                                    </span>
                                </td>
                                <td>
                                    <?php if ($row['prioritas']): ?>
                                        <span class="badge bg-<?= getPriorityBadge($row['prioritas']) ?>">
                                            <?= $row['prioritas'] ?>
                                        </span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <?= $row['teknisi_nama'] ? htmlspecialchars($row['teknisi_nama']) : '<small class="text-muted">Belum ditentukan</small>' ?>
                                </td>
                                <td>
                                    <small class="text-muted">Est:</small> Rp <?= number_format($row['estimasi_biaya'] ?: 0) ?>
                                    <?php if ($row['biaya_aktual']): ?>
                                        <br><strong class="text-success">Rp <?= number_format($row['biaya_aktual']) ?></strong>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <div class="btn-group btn-group-sm">
                                        <button class="btn btn-outline-primary" onclick="viewDetail(<?= $row['id'] ?>)">
                                            <i class="fas fa-eye"></i>
                                        </button>
                                        <?php if ($row['status'] !== 'Selesai'): ?>
                                            <button class="btn btn-outline-success" onclick="updateStatus(<?= $row['id'] ?>, '<?= $row['status'] ?>')">
                                                <i class="fas fa-edit"></i>
                                            </button>
                                        <?php endif; ?>
                                    </div>
                                </td>
                            </tr>
                        <?php endwhile; ?>
                    </tbody>
                </table>
            </div>
        <?php else: ?>
            <div class="text-center p-5">
                <i class="fas fa-tools fa-3x text-muted mb-3"></i>
                <h5>Tidak ada data perawatan</h5>
                <p class="text-muted">Belum ada jadwal perawatan yang terdaftar</p>
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
                        <a class="page-link" href="?page=riwayat_perawatan&page_num=<?= $i ?>&status=<?= urlencode($filter_status) ?>&kendaraan=<?= urlencode($filter_kendaraan) ?>&tanggal=<?= urlencode($filter_tanggal) ?>&search=<?= urlencode($search) ?>">
                            <?= $i ?>
                        </a>
                    </li>
                <?php endfor; ?>
            </ul>
        </nav>
    </div>
<?php endif; ?>

<!-- Add Modal -->
<div class="modal fade" id="addModal" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <form method="POST" action="?page=riwayat_perawatan&action=add">
                <input type="hidden" name="csrf_token" value="<?= generate_csrf_token() ?>">
                
                <div class="modal-header">
                    <h5 class="modal-title">Tambah Jadwal Perawatan</h5>
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
                                        <?= htmlspecialchars($kendaraan['no_polisi'] . ' - ' . $kendaraan['merk'] . ' ' . $kendaraan['tipe']) ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Jenis Perawatan <span class="text-danger">*</span></label>
                            <input type="text" name="jenis_perawatan" class="form-control" required placeholder="Ganti oli, servis rutin, dll">
                        </div>
                        
                        <div class="col-12 mb-3">
                            <label class="form-label">Deskripsi</label>
                            <textarea name="deskripsi" class="form-control" rows="3" placeholder="Deskripsi detail perawatan..."></textarea>
                        </div>
                        
                        <div class="col-md-4 mb-3">
                            <label class="form-label">Jadwal Tanggal <span class="text-danger">*</span></label>
                            <input type="date" name="jadwal_tanggal" class="form-control" required>
                        </div>
                        
                        <div class="col-md-4 mb-3">
                            <label class="form-label">Estimasi Biaya</label>
                            <input type="number" name="estimasi_biaya" class="form-control" step="0.01" placeholder="0">
                        </div>
                        
                        <div class="col-md-4 mb-3">
                            <label class="form-label">Prioritas</label>
                            <select name="prioritas" class="form-control">
                                <option value="">Pilih Prioritas</option>
                                <option value="Tinggi">Tinggi</option>
                                <option value="Sedang">Sedang</option>
                                <option value="Rendah">Rendah</option>
                            </select>
                        </div>
                        
                        <div class="col-12 mb-3">
                            <label class="form-label">Teknisi</label>
                            <select name="teknisi_id" class="form-control">
                                <option value="">Belum ditentukan</option>
                                <?php foreach ($teknisi_list as $teknisi): ?>
                                    <option value="<?= $teknisi['id'] ?>">
                                        <?= htmlspecialchars($teknisi['nama']) ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
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

<!-- Update Status Modal -->
<div class="modal fade" id="statusModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <form method="POST" action="?page=riwayat_perawatan&action=update_status">
                <input type="hidden" name="csrf_token" value="<?= generate_csrf_token() ?>">
                <input type="hidden" name="id" id="status_id">
                
                <div class="modal-header">
                    <h5 class="modal-title">Update Status Perawatan</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                
                <div class="modal-body">
                    <div class="mb-3">
                        <label class="form-label">Status <span class="text-danger">*</span></label>
                        <select name="status" class="form-control" id="status_select" required>
                            <option value="Terjadwal">Terjadwal</option>
                            <option value="Dalam Proses">Dalam Proses</option>
                            <option value="Selesai">Selesai</option>
                            <option value="Ditunda">Ditunda</option>
                        </select>
                    </div>
                    
                    <div class="mb-3" id="tanggal_group" style="display:none;">
                        <label class="form-label">Tanggal Perawatan</label>
                        <input type="date" name="tanggal_perawatan" class="form-control">
                    </div>
                    
                    <div class="mb-3" id="biaya_group" style="display:none;">
                        <label class="form-label">Biaya Aktual</label>
                        <input type="number" name="biaya_aktual" class="form-control" step="0.01" placeholder="0">
                    </div>
                    
                    <div class="mb-3">
                        <label class="form-label">Keterangan</label>
                        <textarea name="keterangan" class="form-control" rows="3"></textarea>
                    </div>
                </div>
                
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Batal</button>
                    <button type="submit" class="btn btn-primary">Update</button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
function updateStatus(id, currentStatus) {
    document.getElementById('status_id').value = id;
    document.getElementById('status_select').value = currentStatus;
    
    // Show/hide fields based on status
    toggleStatusFields();
    
    var modal = new bootstrap.Modal(document.getElementById('statusModal'));
    modal.show();
}

document.getElementById('status_select').addEventListener('change', toggleStatusFields);

function toggleStatusFields() {
    const status = document.getElementById('status_select').value;
    const tanggalGroup = document.getElementById('tanggal_group');
    const biayaGroup = document.getElementById('biaya_group');
    
    if (status === 'Selesai') {
        tanggalGroup.style.display = 'block';
        biayaGroup.style.display = 'block';
        tanggalGroup.querySelector('input').required = true;
    } else {
        tanggalGroup.style.display = 'none';
        biayaGroup.style.display = 'none';
        tanggalGroup.querySelector('input').required = false;
    }
}

function viewDetail(id) {
    // Implement detail view if needed
    window.location.href = '?page=jadwal_perawatan&action=view&id=' + id;
}
</script>
