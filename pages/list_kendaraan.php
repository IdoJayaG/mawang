<?php
// Include global template (use __DIR__ to resolve path reliably)
require_once __DIR__ . '/../templates/page_template.php';

// Include auth helpers (anchored to pages directory)
require_once __DIR__ . '/../includes/auth.php';
require_user(); // Only users can access this page

// Get available vehicles for peminjaman
$keyword = trim($_GET['q'] ?? '');
$jenis_filter = $_GET['jenis'] ?? '';
$bahan_bakar_filter = $_GET['bahan_bakar'] ?? '';
$limit = 20;
$page = max(1, (int)($_GET['p'] ?? 1));
$offset = ($page - 1) * $limit;

// Show all vehicles by default for logged-in users (no default restrictions)
// Prepare where conditions
$where_conditions = [];
$params = [];
$param_types = '';

// Detect which columns exist in kendaraan early (we'll use no_reg and pengguna_id if available)
$cols_info = $mysqli->query("SHOW COLUMNS FROM kendaraan")->fetch_all(MYSQLI_ASSOC);
$cols_names = array_column($cols_info, 'Field');

// Determine availability column
$availability_col = null;
if (in_array('status', $cols_names)) {
    $availability_col = 'status';
} elseif (in_array('status_peminjaman', $cols_names)) {
    $availability_col = 'status_peminjaman';
} elseif (in_array('status_kendaraan', $cols_names)) {
    $availability_col = 'status_kendaraan';
}

// Prepare optional join to pengguna if kendaraan.pengguna_id exists
$join_pengguna = '';
if (in_array('pengguna_id', $cols_names) && function_exists('db_table_exists') && db_table_exists('pengguna')) {
    $join_pengguna = " LEFT JOIN pengguna pg ON k.pengguna_id = pg.id";
}

// Build search clause: single input searches nama pengguna (if available), no_reg, merk, tipe
if ($keyword) {
    $search_term = "%$keyword%";
    if ($join_pengguna) {
        $where_conditions[] = "(k.no_reg LIKE ? OR k.merk LIKE ? OR k.tipe LIKE ? OR pg.nama_lengkap LIKE ?)";
        $params = array_merge($params, [$search_term, $search_term, $search_term, $search_term]);
        $param_types .= 'ssss';
    } else {
        $where_conditions[] = "(k.no_reg LIKE ? OR k.merk LIKE ? OR k.tipe LIKE ?)";
        $params = array_merge($params, [$search_term, $search_term, $search_term]);
        $param_types .= 'sss';
    }
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

$where_sql = !empty($where_conditions) ? ('WHERE ' . implode(' AND ', $where_conditions)) : '';

// Determine order column (prefer no_reg)
$order_col = in_array('no_reg', $cols_names) ? 'k.no_reg' : 'k.no_polisi';

if ($availability_col) {
    $sql = "
    SELECT k.*, 
           CASE WHEN k.$availability_col = 'Tersedia' THEN 1 ELSE 0 END as available,
           COUNT(p.id) as total_peminjaman,
           AVG(CASE WHEN (LOWER(p.status) IN ('completed','selesai')) AND p.km_akhir IS NOT NULL AND p.km_awal IS NOT NULL THEN (p.km_akhir - p.km_awal) ELSE NULL END) as avg_km
    FROM kendaraan k
    {$join_pengguna}
    LEFT JOIN peminjaman_kendaraan p ON k.id = p.kendaraan_id
    $where_sql
    GROUP BY k.id
    ORDER BY available DESC, {$order_col}
    LIMIT ? OFFSET ?
    ";
} else {
    // Fallback: don't compute 'available' using a missing column
    $sql = "
    SELECT k.*, 
           1 as available,
           COUNT(p.id) as total_peminjaman,
           AVG(CASE WHEN (LOWER(p.status) IN ('completed','selesai')) AND p.km_akhir IS NOT NULL AND p.km_awal IS NOT NULL THEN (p.km_akhir - p.km_awal) ELSE NULL END) as avg_km
    FROM kendaraan k
    {$join_pengguna}
    LEFT JOIN peminjaman_kendaraan p ON k.id = p.kendaraan_id
    $where_sql
    GROUP BY k.id
    ORDER BY {$order_col}
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
    {$join_pengguna}
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
// Filter opsi agar konsisten dengan daftar Bus/Truk
$jenis_options = $mysqli->query("SELECT DISTINCT jenis FROM kendaraan ORDER BY jenis")->fetch_all(MYSQLI_ASSOC);
$bahan_bakar_options = $mysqli->query("SELECT DISTINCT bahan_bakar FROM kendaraan ORDER BY bahan_bakar")->fetch_all(MYSQLI_ASSOC);

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
    <div class="action-buttons">
        <a href="index.php?page=form_peminjaman" class="btn btn-primary btn-md"><i class="fas fa-plus icon-input"></i> Ajukan Peminjaman
        </a>
    </div>
</div>


<!-- Statistics -->
<?php
$stats = $mysqli->query("
SELECT 
COUNT(*) as total,
SUM(CASE WHEN status_peminjaman = 'Tersedia' THEN 1 ELSE 0 END) as tersedia,
SUM(CASE WHEN status_peminjaman = 'Dipinjam' THEN 1 ELSE 0 END) as dipinjam,
SUM(CASE WHEN status_peminjaman = 'Maintenance' THEN 1 ELSE 0 END) as maintenance,
SUM(CASE WHEN status_peminjaman = 'Rusak' THEN 1 ELSE 0 END) as rusak
FROM kendaraan 
WHERE status_kendaraan = 'Operasional' AND jenis IN ('Bus','Truk')
")->fetch_assoc();
?>

<div class="row g-3 mb-4 align-items-stretch">
    <div class="col-6 col-md-3">
        <div class="card h-100 shadow-sm text-center">
            <div class="card-body">
                <div class="mb-2 text-muted"><i class="fas fa-car fa-2x"></i></div>
                <h3 class="mb-0"><?= number_format($stats['total'] ?? 0) ?></h3>
                <small class="text-muted">Total Kendaraan</small>
            </div>
        </div>
    </div>

    <div class="col-6 col-md-3">
        <div class="card h-100 shadow-sm text-center">
            <div class="card-body">
                <div class="mb-2 text-success"><i class="fas fa-check-circle fa-2x"></i></div>
                <h3 class="mb-0"><?= number_format($stats['tersedia'] ?? 0) ?></h3>
                <small class="text-muted">Tersedia</small>
            </div>
        </div>
    </div>

    <div class="col-6 col-md-3">
        <div class="card h-100 shadow-sm text-center">
            <div class="card-body">
                <div class="mb-2 text-warning"><i class="fas fa-user fa-2x"></i></div>
                <h3 class="mb-0"><?= number_format($stats['dipinjam'] ?? 0) ?></h3>
                <small class="text-muted">Sedang Dipinjam</small>
            </div>
        </div>
    </div>

    <div class="col-6 col-md-3">
        <div class="card h-100 shadow-sm text-center">
            <div class="card-body">
                <div class="mb-2 text-danger"><i class="fas fa-wrench fa-2x"></i></div>
                <h3 class="mb-0"><?= number_format($stats['maintenance'] ?? 0) ?></h3>
                <small class="text-muted">Maintenance</small>
            </div>
        </div>
    </div>
</div>

<!-- Search and Filter -->

<!-- Small inline styles to make the search/filter appear as a single rounded row on wider screens (matches screenshot) -->
<style>
    /* container for search + reset */
    .actions-bar { display:flex; gap: .75rem; align-items: center; justify-content: space-between; }
    .actions-bar .search-box { flex:1; display:flex; align-items:center; gap:.5rem; }

    /* rounded white filter bar that holds inputs */
    .actions-bar .search-box .filter-group {
        display: flex;
        gap: .5rem;
        align-items: center;
        flex-wrap: nowrap;
        background: #ffffffff;
        padding: .4rem .5rem;
        border-radius: .6rem;
        box-shadow: 0 6px 14px rgba(31,41,55,0.06);
        border: 1px solid rgba(0,0,0,0.04);
    }

    /* inputs inside the rounded bar: borderless and transparent background to blend in */
    .actions-bar .search-box .filter-group .form-control {
        border: none !important;
        background: #f4f4f4ff;
        box-shadow: 0 6px 14px rgba(31,41,55,0.06);
        padding: .45rem .6rem;
        border-radius: .4rem;
        flex: 1 1 180px;
        min-width: 140px;
    }
    .actions-bar .search-box .filter-group .form-control:focus {
        outline: none;
        box-shadow: none;
    }

    /* compact, purple search button matching screenshot */
    .actions-bar .search-box .filter-group button[type="submit"] {
        flex: 0 0 auto;
        background: #6f42c1; /* purple */
        color: #fff;
        border: none;
        padding: .45rem .6rem;
        display: inline-flex;
        align-items: center;
        justify-content: center;
        border-radius: .45rem;
        height: calc(2.25rem + 2px);
        min-width: 44px;
    }
    .actions-bar .search-box .filter-group button[type="submit"] .icon-input { color: #fff; }

    /* Reset / clear link styling */
    .actions-bar .search-box > a {
        margin-left: .5rem;
        align-self: center;
        border-radius: .45rem;
        padding: .4rem .6rem;
        background: transparent;
        border: 1px solid rgba(0,0,0,0.06);
        color: inherit;
    }

    /* ensure action buttons sit to the right on wide screens */
    .actions-bar .action-buttons { margin-left: .75rem; }

    /* Mobile: allow wrapping and stacked inputs */
    @media (max-width: 576px) {
        .actions-bar { flex-direction: column; align-items: stretch; gap: .5rem; }
        .actions-bar .search-box { width:100%; }
        .actions-bar .search-box .filter-group { flex-wrap: wrap; padding: .35rem; }
        .actions-bar .search-box .filter-group .form-control { flex-basis: 100%; min-width: 0; }
        .actions-bar .search-box .filter-group button[type="submit"] { width: 100%; }
        .actions-bar .action-buttons { margin-left: 0; }
    }
</style>

<div class="actions-bar">
    <div class="search-box">
        <form method="get" class="search-form">
            <input type="hidden" name="page" value="list_kendaraan">
            <div class="filter-group">
                <input type="text" name="q" placeholder="Cari nama pengguna, no reg, merek, tipe..." 
                       value="<?= htmlspecialchars($keyword) ?>" class="form-control">
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
</div>
<!-- Vehicle List (detailed vertical list) -->
<div class="vehicle-list">
    <?php if (count($vehicles) > 0): ?>
        <ul class="list-group">
                                <?php foreach ($vehicles as $vehicle): ?>
                <?php $availability = getAvailabilityStatus($vehicle); ?>
                <li class="list-group-item d-flex align-items-start gap-3 <?= !$availability['can_request'] ? 'unavailable' : '' ?>">
                    <div style="width:120px; flex:0 0 120px;">
                        <?php $photo = get_vehicle_photo_web_path((int)$vehicle['id']); ?>
                        <?php if ($photo): ?>
                            <img src="<?= htmlspecialchars($photo) ?>?v=<?= urlencode($vehicle['updated_at'] ?? $vehicle['created_at'] ?? time()) ?>" alt="<?= htmlspecialchars($vehicle['merk']) ?> <?= htmlspecialchars($vehicle['tipe']) ?>" style="width:100%; height:80px; object-fit:cover; border-radius:6px;">
                        <?php else: ?>
                            <div style="width:100%; height:80px; display:flex; align-items:center; justify-content:center; background:#f3f4f6; color:#6b7280; border-radius:6px; border:1px solid #e0e0e0;">
                                <i class="fas fa-car"></i>
                            </div>
                        <?php endif; ?>
                    </div>
                    <div style="flex:1;">
                        <div class="d-flex justify-content-between align-items-start">
                            <div>
                                <?php $display_reg = !empty($vehicle['no_reg']) ? htmlspecialchars($vehicle['no_reg']) : htmlspecialchars($vehicle['no_polisi'] ?? ''); ?>
                                <h5 class="mb-1"><?= $display_reg ?> <small class="text-muted">— <?= htmlspecialchars($vehicle['merk']) ?> <?= htmlspecialchars($vehicle['tipe']) ?></small></h5>
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

                            <div class="d-flex align-items-center" style="gap:8px;">
                                <?php
                                    // Prefer passing kendaraan id and provide multiple param names for compatibility
                                    $detailParams = 'page=kendaraan_detail_publik';
                                    if (!empty($vehicle['id'])) {
                                        // include both common param names some pages expect
                                        $detailParams .= '&id=' . urlencode($vehicle['id']) . '&kendaraan_id=' . urlencode($vehicle['id']);
                                    }
                                    if (!empty($vehicle['no_reg'])) {
                                        $detailParams .= '&no_reg=' . urlencode($vehicle['no_reg']);
                                    }
                                ?>
                                <a href="index.php?<?= $detailParams ?>" class="btn btn-primary btn-sm">
                                    <i class="fas fa-eye me-1"></i> Detail
                                </a>
                                <?php if ($availability['can_request']): ?>
                                    <?php
                                        // Prefer passing kendaraan id to the peminjaman form; include alternate names and no_reg for compatibility
                                        $requestParams = 'page=form_peminjaman';
                                        if (!empty($vehicle['id'])) {
                                            $requestParams .= '&kendaraan_id=' . urlencode($vehicle['id']) . '&id=' . urlencode($vehicle['id']);
                                        }
                                        if (!empty($vehicle['no_reg'])) {
                                            $requestParams .= '&no_reg=' . urlencode($vehicle['no_reg']);
                                        }
                                    ?>
                                    <a href="index.php?<?= $requestParams ?>" class="btn btn-success btn-sm">
                                        <i class="fas fa-plus me-1"></i> Ajukan Peminjaman
                                    </a>
                                <?php else: ?>
                                    <button class="btn btn-secondary btn-sm" disabled><i class="fas fa-ban me-1"></i> Tidak Tersedia</button>
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

