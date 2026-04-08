<?php
require_once 'includes/auth.php';
require_user(); // Only users can access this page

// Get available vehicles for peminjaman
$keyword = trim($_GET['q'] ?? '');
$jenis_filter = $_GET['jenis'] ?? '';
$bahan_bakar_filter = $_GET['bahan_bakar'] ?? '';
$limit = 20;
$page = max(1, (int)($_GET['p'] ?? 1));
$offset = ($page - 1) * $limit;

$where_conditions = ["k.status_kendaraan = 'Operasional'"];
$params = [];
$param_types = '';

if ($keyword) {
    $where_conditions[] = "(k.no_polisi LIKE ? OR k.merk LIKE ? OR k.tipe LIKE ?)";
    $search_term = "%$keyword%";
    $params = array_merge($params, [$search_term, $search_term, $search_term]);
    $param_types .= 'sss';
}

if ($jenis_filter) {
    $where_conditions[] = "k.jenis = ?";
    $params[] = $jenis_filter;
    $param_types .= 's';
}

if ($bahan_bakar_filter) {
    $where_conditions[] = "k.bahan_bakar = ?";
    $params[] = $bahan_bakar_filter;
    $param_types .= 's';
}

$where_sql = 'WHERE ' . implode(' AND ', $where_conditions);

// Detect which availability column exists in kendaraan
$cols_info = $mysqli->query("SHOW COLUMNS FROM kendaraan")->fetch_all(MYSQLI_ASSOC);
$cols_names = array_column($cols_info, 'Field');
$availability_col = null;
if (in_array('status', $cols_names)) {
    $availability_col = 'status';
} elseif (in_array('status_peminjaman', $cols_names)) {
    $availability_col = 'status_peminjaman';
} elseif (in_array('status_kendaraan', $cols_names)) {
    $availability_col = 'status_kendaraan';
}

if ($availability_col) {
    $sql = "
    SELECT k.*, 
           CASE WHEN k.$availability_col = 'Tersedia' THEN 1 ELSE 0 END as available,
           COUNT(p.id) as total_peminjaman,
           AVG(CASE WHEN p.status = 'Completed' AND p.km_akhir IS NOT NULL AND p.km_awal IS NOT NULL THEN (p.km_akhir - p.km_awal) ELSE NULL END) as avg_km
    FROM kendaraan k
    LEFT JOIN peminjaman_kendaraan p ON k.id = p.kendaraan_id
    $where_sql
    GROUP BY k.id
    ORDER BY available DESC, k.no_polisi
    LIMIT ? OFFSET ?
    ";
} else {
    // Fallback: don't compute 'available' using a missing column
    $sql = "
    SELECT k.*, 
           1 as available,
           COUNT(p.id) as total_peminjaman,
           AVG(CASE WHEN p.status = 'Completed' AND p.km_akhir IS NOT NULL AND p.km_awal IS NOT NULL THEN (p.km_akhir - p.km_awal) ELSE NULL END) as avg_km
    FROM kendaraan k
    LEFT JOIN peminjaman_kendaraan p ON k.id = p.kendaraan_id
    $where_sql
    GROUP BY k.id
    ORDER BY k.no_polisi
    LIMIT ? OFFSET ?
    ";
}

$stmt = $mysqli->prepare($sql);
if ($params) {
    $param_types .= 'ii';
    $params[] = $limit;
    $params[] = $offset;
    $stmt->bind_param($param_types, ...$params);
} else {
    $stmt->bind_param('ii', $limit, $offset);
}

$stmt->execute();
$vehicles = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$stmt->close();

// Get total count for pagination
$count_sql = "
    SELECT COUNT(DISTINCT k.id) as total
    FROM kendaraan k
    $where_sql
";

$count_stmt = $mysqli->prepare($count_sql);
if ($params) {
    // Remove limit and offset from params for count
    $count_params = array_slice($params, 0, -2);
    $count_param_types = substr($param_types, 0, -2);
    if ($count_params) {
        $count_stmt->bind_param($count_param_types, ...$count_params);
    }
}
$count_stmt->execute();
$total_records = $count_stmt->get_result()->fetch_assoc()['total'];
$count_stmt->close();

$total_pages = ceil($total_records / $limit);

// Get filter options
$jenis_options = $mysqli->query("SELECT DISTINCT jenis FROM kendaraan WHERE status_kendaraan = 'Operasional' ORDER BY jenis")->fetch_all(MYSQLI_ASSOC);
$bahan_bakar_options = $mysqli->query("SELECT DISTINCT bahan_bakar FROM kendaraan WHERE status_kendaraan = 'Operasional' ORDER BY bahan_bakar")->fetch_all(MYSQLI_ASSOC);

// Function to get vehicle image
function getVehicleImage($jenis, $foto = null) {
    if ($foto && file_exists("assets/images/$foto")) {
        return "assets/images/$foto";
    }
    
    // Default images based on vehicle type
    $default_images = [
        'Roda 2' => 'roda 2.png',
        'Roda 4' => 'sedan.png',
        'Truk' => 'truck.png',
        'Bus' => 'suv.png',
        'Lainnya' => 'default.jpg'
    ];
    
    $default_image = $default_images[$jenis] ?? 'default.jpg';
    return "assets/images/$default_image";
}

// Function to get availability status
function getAvailabilityStatus($vehicle) {
    if ($vehicle['status_kendaraan'] !== 'Operasional') {
        return ['status' => 'Tidak Operasional', 'class' => 'status-danger', 'can_request' => false];
    }
    
    if ($vehicle['status_peminjaman'] === 'Dipinjam') {
        return ['status' => 'Sedang Dipinjam', 'class' => 'status-warning', 'can_request' => false];
    }
    
    if ($vehicle['status_peminjaman'] === 'Maintenance') {
        return ['status' => 'Maintenance', 'class' => 'status-info', 'can_request' => false];
    }
    
    return ['status' => 'Tersedia', 'class' => 'status-success', 'can_request' => true];
}
?>

<div class="page-header">
    <h1><i class="fas fa-car"></i> Daftar Kendaraan</h1>
</div>


<!-- Statistics -->
<?php
$stats = $mysqli->query("
SELECT 
COUNT(*) as total,
SUM(CASE WHEN status_peminjaman = 'Tersedia' THEN 1 ELSE 0 END) as tersedia,
SUM(CASE WHEN status_peminjaman = 'Dipinjam' THEN 1 ELSE 0 END) as dipinjam,
SUM(CASE WHEN status_peminjaman = 'Maintenance' THEN 1 ELSE 0 END) as maintenance
FROM kendaraan 
WHERE status_kendaraan = 'Operasional'
")->fetch_assoc();
?>

<div class="stats-cards">
    <div class="stat-card total">
        <div class="stat-icon"><i class="fas fa-car"></i></div>
        <div class="stat-info">
            <h3><?= $stats['total'] ?></h3>
            <p>Total Kendaraan</p>
        </div>
    </div>
    <div class="stat-card available">
        <div class="stat-icon"><i class="fas fa-check-circle"></i></div>
        <div class="stat-info">
            <h3><?= $stats['tersedia'] ?></h3>
            <p>Tersedia</p>
        </div>
    </div>
    <div class="stat-card borrowed">
        <div class="stat-icon"><i class="fas fa-user"></i></div>
        <div class="stat-info">
            <h3><?= $stats['dipinjam'] ?></h3>
            <p>Sedang Dipinjam</p>
        </div>
    </div>
    <div class="stat-card maintenance">
        <div class="stat-icon"><i class="fas fa-wrench"></i></div>
        <div class="stat-info">
            <h3><?= $stats['maintenance'] ?></h3>
            <p>Maintenance</p>
        </div>
    </div>
</div>

<!-- Search and Filter -->
<style>
/* Make FontAwesome icons visually match the form-control height */
.icon-input { font-size: 1rem; line-height: 1; display: inline-block; vertical-align: middle; }
/* Ensure buttons align their icon and label centered like the inputs */
.filter-group .btn, .action-buttons .btn { display: inline-flex; align-items: center; gap: 0.5rem; }
</style>

<div class="actions-bar">
    <div class="search-box">
        <form method="get" class="search-form">
            <input type="hidden" name="page" value="list_kendaraan">
            <div class="filter-group">
                <input type="text" name="q" placeholder="Cari nomor polisi, merk, tipe..." 
                       value="<?= htmlspecialchars($keyword) ?>" class="form-control">
                <select name="jenis" class="form-control">
                    <option value="">Semua Jenis</option>
                    <?php foreach ($jenis_options as $option): ?>
                        <option value="<?= $option['jenis'] ?>" <?= $jenis_filter === $option['jenis'] ? 'selected' : '' ?>>
                            <?= htmlspecialchars($option['jenis']) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
                <select name="bahan_bakar" class="form-control">
                    <option value="">Semua Bahan Bakar</option>
                    <?php foreach ($bahan_bakar_options as $option): ?>
                        <option value="<?= $option['bahan_bakar'] ?>" <?= $bahan_bakar_filter === $option['bahan_bakar'] ? 'selected' : '' ?>>
                            <?= htmlspecialchars($option['bahan_bakar']) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
                <button type="submit" class="btn btn-primary">
                    <i class="fas fa-search icon-input"></i>
                </button>
            </div>
        </form>
        <?php if ($keyword !== '' || $jenis_filter || $bahan_bakar_filter): ?>
            <a href="index.php?page=list_kendaraan" class="btn btn-outline">
                <i class="fas fa-times icon-input"></i> Reset
            </a>
        <?php endif; ?>
    </div>
    
    <div class="action-buttons">
        <a href="index.php?page=form_peminjaman" class="btn btn-primary btn-md"><i class="fas fa-plus icon-input"></i> Ajukan Peminjaman
        </a>
    </div>
</div>
<!-- Vehicle List (detailed vertical list) -->
<div class="vehicle-list">
    <?php if (count($vehicles) > 0): ?>
        <ul class="list-group">
                                <?php foreach ($vehicles as $vehicle): ?>
                <?php $availability = getAvailabilityStatus($vehicle); ?>
                <li class="list-group-item d-flex align-items-start gap-3 <?= !$availability['can_request'] ? 'unavailable' : '' ?>">
                    <div style="width:120px; flex:0 0 120px;">
                        <img src="<?= getVehicleImage($vehicle['jenis'], $vehicle['foto']) ?>" 
                             alt="<?= htmlspecialchars($vehicle['merk']) ?> <?= htmlspecialchars($vehicle['tipe']) ?>" 
                             style="width:100%; height:80px; object-fit:cover; border-radius:6px;">
                    </div>
                    <div style="flex:1;">
                        <div class="d-flex justify-content-between align-items-start">
                            <div>
                                <h5 class="mb-1"><?= htmlspecialchars($vehicle['no_polisi']) ?> <small class="text-muted">— <?= htmlspecialchars($vehicle['merk']) ?> <?= htmlspecialchars($vehicle['tipe']) ?></small></h5>
                                <div class="text-muted small">
                                    <?= htmlspecialchars($vehicle['tahun_pembuatan']) ?> • <?= htmlspecialchars($vehicle['jenis']) ?> • <?= htmlspecialchars($vehicle['bahan_bakar']) ?>
                                    <?php if (!empty($vehicle['satker'])): ?>
                                        • Satker: <?= htmlspecialchars($vehicle['satker']) ?>
                                    <?php endif; ?>
                                </div>
                            </div>
                            <div class="text-end">
                                <div class="vehicle-status <?= $availability['class'] ?> p-1 rounded-2"><?= $availability['status'] ?></div>
                            </div>
                        </div>

                        <div class="mt-2 d-flex justify-content-between align-items-center">
                            <div>
                                <?php if ($vehicle['total_peminjaman'] > 0): ?>
                                    <small class="text-muted"><i class="fas fa-history"></i> <?= $vehicle['total_peminjaman'] ?> kali dipinjam
                                        <?php if ($vehicle['avg_km']): ?> • Rata-rata: <?= number_format($vehicle['avg_km']) ?> km/perjalanan <?php endif; ?>
                                    </small>
                                <?php else: ?>
                                    <small class="text-muted">Belum pernah dipinjam</small>
                                <?php endif; ?>
                            </div>

                            <div class="btn-group">
                                    <a href="index.php?page=kendaraan_detail_publik&id=<?= $vehicle['id'] ?>" class="btn btn-primary btn-sm">
                                        <i class="fas fa-eye"></i> Detail
                                    </a>
                                    <?php if ($availability['can_request']): ?>
                                        <a href="index.php?page=form_peminjaman&kendaraan_id=<?= $vehicle['id'] ?>" class="btn btn-success btn-sm">
                                            <i class="fas fa-plus"></i> Ajukan Peminjaman
                                        </a>
                                    <?php else: ?>
                                        <button class="btn btn-secondary btn-sm" disabled><i class="fas fa-ban"></i> Tidak Tersedia</button>
                                    <?php endif; ?>
                            </div>
                        </div>
                    </div>
                </li>
            <?php endforeach; ?>
        </ul>
    <?php else: ?>
        <div class="empty-state">
            <i class="fas fa-car"></i>
            <h4>Tidak Ada Kendaraan</h4>
            <p>
                <?php if ($keyword || $jenis_filter || $bahan_bakar_filter): ?>
                    Tidak ada kendaraan yang cocok dengan filter yang dipilih
                <?php else: ?>
                    Tidak ada kendaraan yang tersedia saat ini
                <?php endif; ?>
            </p>
        </div>
    <?php endif; ?>
</div>

<!-- Pagination -->
<?php if ($total_pages > 1): ?>
    <div class="pagination-wrapper">
        <nav aria-label="Pagination">
            <ul class="pagination">
                <?php if ($page > 1): ?>
                    <li class="page-item">
                        <a class="page-link" href="index.php?page=list_kendaraan&p=<?= $page - 1 ?><?= $keyword ? '&q=' . urlencode($keyword) : '' ?><?= $jenis_filter ? '&jenis=' . urlencode($jenis_filter) : '' ?><?= $bahan_bakar_filter ? '&bahan_bakar=' . urlencode($bahan_bakar_filter) : '' ?>">
                            <i class="fas fa-chevron-left"></i>
                        </a>
                    </li>
                <?php endif; ?>
                
                <?php for ($i = max(1, $page - 2); $i <= min($total_pages, $page + 2); $i++): ?>
                    <li class="page-item <?= $i == $page ? 'active' : '' ?>">
                        <a class="page-link" href="index.php?page=list_kendaraan&p=<?= $i ?><?= $keyword ? '&q=' . urlencode($keyword) : '' ?><?= $jenis_filter ? '&jenis=' . urlencode($jenis_filter) : '' ?><?= $bahan_bakar_filter ? '&bahan_bakar=' . urlencode($bahan_bakar_filter) : '' ?>">
                            <?= $i ?>
                        </a>
                    </li>
                <?php endfor; ?>
                
                <?php if ($page < $total_pages): ?>
                    <li class="page-item">
                        <a class="page-link" href="index.php?page=list_kendaraan&p=<?= $page + 1 ?><?= $keyword ? '&q=' . urlencode($keyword) : '' ?><?= $jenis_filter ? '&jenis=' . urlencode($jenis_filter) : '' ?><?= $bahan_bakar_filter ? '&bahan_bakar=' . urlencode($bahan_bakar_filter) : '' ?>">
                            <i class="fas fa-chevron-right"></i>
                        </a>
                    </li>
                <?php endif; ?>
            </ul>
        </nav>
        
        <div class="pagination-info">
            Menampilkan <?= min($total_records, $offset + 1) ?> - <?= min($total_records, $offset + count($vehicles)) ?> dari <?= $total_records ?> kendaraan
        </div>
    </div>
<?php endif; ?>
<style>
.page-header {
    margin-bottom: 2rem;
    padding-bottom: 1rem;
    border-bottom: 1px solid #e9ecef;
}
.page-header h1 {
    color: #333;
    margin-bottom: 0.5rem;
}

.page-description {
    color: #666;
    margin: 0.5rem 0 0 0;
}

.actions-bar {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 2rem;
    gap: 1rem;
}

.search-box {
    flex: 1;
}

.filter-group {
    display: flex;
    gap: 0.5rem;
    align-items: center;
}

.filter-group .form-control {
    min-width: 150px;
}

.stats-cards {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
    gap: 1rem;
    margin-bottom: 2rem;
}

.stat-card {
    background: white;
    border-radius: 8px;
    padding: 1.5rem;
    box-shadow: 0 2px 4px rgba(0,0,0,0.1);
    display: flex;
    align-items: center;
    gap: 1rem;
}

.stat-card.total { border-left: 4px solid #007bff; }
.stat-card.available { border-left: 4px solid #28a745; }
.stat-card.borrowed { border-left: 4px solid #ffc107; }
.stat-card.maintenance { border-left: 4px solid #dc3545; }

.stat-icon {
    font-size: 2rem;
    opacity: 0.7;
}

.stat-card.total .stat-icon { color: #007bff; }
.stat-card.available .stat-icon { color: #28a745; }
.stat-card.borrowed .stat-icon { color: #ffc107; }
.stat-card.maintenance .stat-icon { color: #dc3545; }

.stat-info h3 {
    margin: 0;
    font-size: 2rem;
    font-weight: bold;
}

.stat-info p {
    margin: 0;
    color: #666;
    font-size: 0.9rem;
}

.vehicles-grid {
    display: grid;
    grid-template-columns: repeat(auto-fill, minmax(350px, 1fr));
    gap: 1.5rem;
    margin-bottom: 2rem;
}

.vehicle-card {
    background: white;
    border-radius: 12px;
    box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1);
    overflow: hidden;
    transition: transform 0.2s ease, box-shadow 0.2s ease;
}

.vehicle-card:hover {
    transform: translateY(-2px);
    box-shadow: 0 8px 15px rgba(0, 0, 0, 0.15);
}

.vehicle-card.unavailable {
    opacity: 0.7;
}

.vehicle-image {
    position: relative;
    height: 200px;
    overflow: hidden;
}

.vehicle-image img {
    width: 100%;
    height: 100%;
    object-fit: cover;
}

.vehicle-status {
    position: absolute;
    top: 1rem;
    right: 1rem;
    padding: 0.5rem 1rem;
    border-radius: 20px;
    font-size: 0.8rem;
    font-weight: 600;
}

.status-success {
    background-color: #d4edda;
    color: #155724;
}

.status-warning {
    background-color: #fff3cd;
    color: #856404;
}

.status-danger {
    background-color: #f8d7da;
    color: #721c24;
}

.status-info {
    background-color: #d1ecf1;
    color: #0c5460;
}

.vehicle-info {
    padding: 1.5rem;
}

.vehicle-title {
    margin: 0 0 0.5rem 0;
    font-size: 1.25rem;
    font-weight: bold;
    color: #333;
}

.vehicle-details {
    margin: 0 0 1rem 0;
    color: #666;
}

.vehicle-specs {
    display: flex;
    flex-wrap: wrap;
    gap: 1rem;
    margin-bottom: 1rem;
}

.vehicle-stats {
    margin-bottom: 1rem;
}

.vehicle-actions {
    display: flex;
    gap: 0.5rem;
    padding: 0 1.5rem 1.5rem 1.5rem;
}

.vehicle-actions .btn {
    flex: 1;
    text-align: center;
}

.empty-state {
    grid-column: 1 / -1;
    text-align: center;
    padding: 3rem;
    color: #666;
}

.empty-state i {
    font-size: 4rem;
    margin-bottom: 1rem;
    opacity: 0.5;
}

.empty-state h4 {
    margin-bottom: 0.5rem;
}

.card {
    box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1);
    border: none;
    border-radius: 12px;
}

.card-header {
    background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
    color: white;
    border-radius: 12px 12px 0 0;
}

.badge {
    padding: 0.4rem 0.8rem;
    border-radius: 12px;
    font-size: 0.85rem;
}

.badge-warning { background-color: #ffc107; color: #212529; }
.badge-success { background-color: #28a745; color: white; }
.badge-danger { background-color: #dc3545; color: white; }
.badge-info { background-color: #17a2b8; color: white; }
.badge-primary { background-color: #007bff; color: white; }
.badge-secondary { background-color: #6c757d; color: white; }

.table th {
    border-top: none;
    background-color: #f8f9fa;
    font-weight: 600;
}

.btn {
    border-radius: 8px;
    font-weight: 500;
    padding: 0.375rem 1rem;
}

.form-control {
    border-radius: 8px;
    border: 2px solid #e9ecef;
    transition: all 0.2s ease;
}

.form-control:focus {
    border-color: #667eea;
    box-shadow: 0 0 0 0.2rem rgba(102, 126, 234, 0.25);
}

.text-danger {
    color: #dc3545 !important;
}

/* Custom modal header styling */
.custom-header { background: linear-gradient(90deg,#5a67d8,#9f7aea); color: #fff; align-items:center; }
.custom-header .modal-title { margin:0; font-weight:600; }
.modal-close { border: none; background: rgba(255,255,255,0.12); color: #fff; }
.modal-close:hover { background: rgba(255,255,255,0.18); }

@media (max-width: 768px) {
    .table-responsive {
        font-size: 0.9rem;
    }
    
    .btn-sm {
        padding: 0.25rem 0.5rem;
        font-size: 0.8rem;
    }

    .actions-bar {
        flex-direction: column;
        align-items: stretch;
    }
    
    .filter-group {
        flex-direction: column;
    }
    
    .vehicles-grid {
        grid-template-columns: 1fr;
    }
    
    .vehicle-specs {
        flex-direction: column;
        gap: 0.5rem;
    }
    
    .stats-cards {
        grid-template-columns: repeat(2, 1fr);
    }
}
</style>
