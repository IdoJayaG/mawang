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
        return ['status' => 'Tidak Operasional', 'class' => 'bg-danger', 'can_request' => false];
    }
    if ($vehicle['status_peminjaman'] === 'Dipinjam') {
        return ['status' => 'Sedang Dipinjam', 'class' => 'bg-warning text-dark', 'can_request' => false];
    }
    if ($vehicle['status_peminjaman'] === 'Maintenance') {
        return ['status' => 'Maintenance', 'class' => 'bg-info', 'can_request' => false];
    }
    return ['status' => 'Tersedia', 'class' => 'bg-success', 'can_request' => true];
}
?>

<!-- ── Page Header ─────────────────────────────────────────────────────────── -->
<div class="page-header">
    <h1><i class="fas fa-car"></i> Daftar Kendaraan</h1>
</div>

<!-- ── Stat Cards ─────────────────────────────────────────────────────────── -->
<?php
$stats = $mysqli->query("
    SELECT
        COUNT(*) as total,
        SUM(CASE WHEN status_peminjaman = 'Tersedia'    THEN 1 ELSE 0 END) as tersedia,
        SUM(CASE WHEN status_peminjaman = 'Dipinjam'    THEN 1 ELSE 0 END) as dipinjam,
        SUM(CASE WHEN status_peminjaman = 'Maintenance' THEN 1 ELSE 0 END) as maintenance
    FROM kendaraan
    WHERE status_kendaraan = 'Operasional'
")->fetch_assoc();
?>
<div class="row mb-3">
    <div class="col-lg-3 col-md-6 mb-3">
        <div class="card bg-primary text-white shadow-sm card-hover h-100">
            <div class="card-body d-flex align-items-center">
                <div class="me-3">
                    <div class="bg-white bg-opacity-25 rounded-circle d-flex align-items-center justify-content-center stat-icon-circle">
                        <i class="fas fa-car fa-2x text-white"></i>
                    </div>
                </div>
                <div>
                    <h3 class="mb-0 fw-bold"><?= number_format($stats['total'] ?? 0) ?></h3>
                    <p class="mb-0 opacity-75">Total Kendaraan</p>
                </div>
            </div>
        </div>
    </div>
    <div class="col-lg-3 col-md-6 mb-3">
        <div class="card bg-success text-white shadow-sm card-hover h-100">
            <div class="card-body d-flex align-items-center">
                <div class="me-3">
                    <div class="bg-white bg-opacity-25 rounded-circle d-flex align-items-center justify-content-center stat-icon-circle">
                        <i class="fas fa-check-circle fa-2x text-white"></i>
                    </div>
                </div>
                <div>
                    <h3 class="mb-0 fw-bold"><?= number_format($stats['tersedia'] ?? 0) ?></h3>
                    <p class="mb-0 opacity-75">Tersedia</p>
                </div>
            </div>
        </div>
    </div>
    <div class="col-lg-3 col-md-6 mb-3">
        <div class="card bg-warning text-white shadow-sm card-hover h-100">
            <div class="card-body d-flex align-items-center">
                <div class="me-3">
                    <div class="bg-white bg-opacity-25 rounded-circle d-flex align-items-center justify-content-center stat-icon-circle">
                        <i class="fas fa-road fa-2x text-white"></i>
                    </div>
                </div>
                <div>
                    <h3 class="mb-0 fw-bold"><?= number_format($stats['dipinjam'] ?? 0) ?></h3>
                    <p class="mb-0 opacity-75">Sedang Dipinjam</p>
                </div>
            </div>
        </div>
    </div>
    <div class="col-lg-3 col-md-6 mb-3">
        <div class="card bg-danger text-white shadow-sm card-hover h-100">
            <div class="card-body d-flex align-items-center">
                <div class="me-3">
                    <div class="bg-white bg-opacity-25 rounded-circle d-flex align-items-center justify-content-center stat-icon-circle">
                        <i class="fas fa-wrench fa-2x text-white"></i>
                    </div>
                </div>
                <div>
                    <h3 class="mb-0 fw-bold"><?= number_format($stats['maintenance'] ?? 0) ?></h3>
                    <p class="mb-0 opacity-75">Maintenance</p>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- ── Search & Filter ────────────────────────────────────────────────────── -->
<div class="card shadow-sm mb-3">
    <div class="card-body py-2">
        <form method="get" class="row g-2 align-items-center">
            <input type="hidden" name="page" value="list_kendaraan">
            <div class="col-md-5">
                <div class="input-group">
                    <span class="input-group-text bg-white border-end-0">
                        <i class="fas fa-search text-muted"></i>
                    </span>
                    <input type="text" name="q" class="form-control border-start-0"
                           placeholder="Cari no. reg, merek, tipe..."
                           value="<?= htmlspecialchars($keyword) ?>">
                </div>
            </div>
            <div class="col-md-3">
                <select name="bahan_bakar" class="form-select">
                    <option value="">-- Semua Bahan Bakar --</option>
                    <?php foreach ($bahan_bakar_options as $opt): ?>
                        <?php if (empty($opt['bahan_bakar'])) continue; ?>
                        <option value="<?= htmlspecialchars($opt['bahan_bakar']) ?>"
                            <?= $bahan_bakar_filter === $opt['bahan_bakar'] ? 'selected' : '' ?>>
                            <?= htmlspecialchars($opt['bahan_bakar']) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-md-2">
                <button type="submit" class="btn btn-primary w-100">
                    <i class="fas fa-filter me-1"></i>Filter
                </button>
            </div>
            <div class="col-md-2">
                <a href="index.php?page=list_kendaraan" class="btn btn-outline-secondary w-100">
                    <i class="fas fa-times me-1"></i>Reset
                </a>
            </div>
        </form>
    </div>
</div>

<?php if ($keyword || $jenis_filter || $bahan_bakar_filter): ?>
<div class="d-flex align-items-center gap-2 mb-2 small text-muted">
    <i class="fas fa-filter"></i>
    Filter aktif:
    <?php if ($keyword): ?><span class="badge bg-secondary"><?= htmlspecialchars($keyword) ?></span><?php endif; ?>
    <?php if ($bahan_bakar_filter): ?><span class="badge bg-secondary"><?= htmlspecialchars($bahan_bakar_filter) ?></span><?php endif; ?>
    &bull; <?= $total_records ?> hasil ditemukan
</div>
<?php endif; ?>

<!-- ── Vehicle Cards ──────────────────────────────────────────────────────── -->
<?php if (count($vehicles) > 0): ?>
    <?php foreach ($vehicles as $vehicle): ?>
        <?php $availability = getAvailabilityStatus($vehicle); ?>
        <?php
            $display_reg = !empty($vehicle['no_reg']) ? $vehicle['no_reg'] : ($vehicle['no_polisi'] ?? '-');
            $detailParams = 'page=kendaraan_detail_publik&id=' . urlencode($vehicle['id']) . '&kendaraan_id=' . urlencode($vehicle['id']);
            if (!empty($vehicle['no_reg'])) $detailParams .= '&no_reg=' . urlencode($vehicle['no_reg']);
        ?>
        <div class="card shadow-sm mb-2 <?= !$availability['can_request'] ? 'border-start border-3 border-secondary' : '' ?>">
            <div class="card-body p-3">
                <div class="d-flex gap-3 align-items-start">

                    <!-- Foto kendaraan -->
                    <div class="flex-shrink-0" style="width:100px;">
                        <?php $photo = get_vehicle_photo_web_path((int)$vehicle['id']); ?>
                        <?php if ($photo): ?>
                            <img src="<?= htmlspecialchars($photo) ?>?v=<?= urlencode($vehicle['updated_at'] ?? '') ?>"
                                 alt="<?= htmlspecialchars($vehicle['merk'].' '.$vehicle['tipe']) ?>"
                                 class="rounded" style="width:100%;height:72px;object-fit:cover;">
                        <?php else: ?>
                            <div class="rounded d-flex align-items-center justify-content-center bg-light text-muted"
                                 style="width:100%;height:72px;font-size:1.5rem;">
                                <i class="fas fa-car"></i>
                            </div>
                        <?php endif; ?>
                    </div>

                    <!-- Info -->
                    <div class="flex-grow-1 min-w-0">
                        <div class="d-flex justify-content-between align-items-start flex-wrap gap-1">
                            <div>
                                <h6 class="mb-0 fw-bold">
                                    <?= htmlspecialchars($display_reg) ?>
                                    <span class="fw-normal text-muted">— <?= htmlspecialchars($vehicle['merk'].' '.$vehicle['tipe']) ?></span>
                                </h6>
                                <div class="text-muted small mt-1">
                                    <?= htmlspecialchars($vehicle['tahun_pembuatan'] ?? '') ?>
                                    <?php if (!empty($vehicle['jenis'])): ?> &bull; <?= htmlspecialchars($vehicle['jenis']) ?><?php endif; ?>
                                    <?php if (!empty($vehicle['bahan_bakar'])): ?> &bull; <?= htmlspecialchars($vehicle['bahan_bakar']) ?><?php endif; ?>
                                    <?php if (!empty($vehicle['satker'])): ?> &bull; <?= htmlspecialchars($vehicle['satker']) ?><?php endif; ?>
                                </div>
                            </div>
                            <span class="badge <?= $availability['class'] ?> align-self-start">
                                <?= $availability['status'] ?>
                            </span>
                        </div>

                        <div class="d-flex justify-content-between align-items-center mt-2 flex-wrap gap-2">
                            <small class="text-muted">
                                <?php if ($vehicle['total_peminjaman'] > 0): ?>
                                    <i class="fas fa-history me-1"></i><?= $vehicle['total_peminjaman'] ?> kali dipinjam
                                    <?php if ($vehicle['avg_km']): ?>
                                        &bull; rata-rata <?= number_format($vehicle['avg_km']) ?> km/perjalanan
                                    <?php endif; ?>
                                <?php else: ?>
                                    <i class="fas fa-history me-1 opacity-50"></i>Belum pernah dipinjam
                                <?php endif; ?>
                            </small>
                            <div class="d-flex gap-2">
                                <a href="index.php?<?= $detailParams ?>" class="btn btn-outline-primary btn-sm">
                                    <i class="fas fa-eye me-1"></i>Detail
                                </a>
                            </div>
                        </div>
                    </div>

                </div>
            </div>
        </div>
    <?php endforeach; ?>
<?php else: ?>
    <div class="card shadow-sm">
        <div class="card-body text-center py-5 text-muted">
            <i class="fas fa-car fa-3x mb-3 opacity-25 d-block"></i>
            <h5>Tidak Ada Kendaraan</h5>
            <p class="mb-0 small">
                <?= ($keyword || $jenis_filter || $bahan_bakar_filter) ? 'Tidak ada kendaraan yang cocok dengan filter yang dipilih.' : 'Tidak ada kendaraan yang tersedia saat ini.' ?>
            </p>
        </div>
    </div>
<?php endif; ?>

<!-- ── Pagination ──────────────────────────────────────────────────────────── -->
<?php if ($total_pages > 1): ?>
    <?php
        $qs = ($keyword ? '&q='.urlencode($keyword) : '') . ($jenis_filter ? '&jenis='.urlencode($jenis_filter) : '') . ($bahan_bakar_filter ? '&bahan_bakar='.urlencode($bahan_bakar_filter) : '');
        $base = 'index.php?page=list_kendaraan';
    ?>
    <div class="d-flex justify-content-between align-items-center mt-3 flex-wrap gap-2">
        <div class="small text-muted">
            Menampilkan <?= min($total_records, $offset + 1) ?>–<?= min($total_records, $offset + count($vehicles)) ?>
            dari <?= $total_records ?> kendaraan
        </div>
        <nav>
            <ul class="pagination pagination-sm mb-0">
                <li class="page-item <?= $page <= 1 ? 'disabled' : '' ?>">
                    <a class="page-link" href="<?= $base ?>&p=<?= $page - 1 ?><?= $qs ?>">
                        <i class="fas fa-chevron-left"></i>
                    </a>
                </li>
                <?php for ($i = max(1, $page - 2); $i <= min($total_pages, $page + 2); $i++): ?>
                    <li class="page-item <?= $i === $page ? 'active' : '' ?>">
                        <a class="page-link" href="<?= $base ?>&p=<?= $i ?><?= $qs ?>"><?= $i ?></a>
                    </li>
                <?php endfor; ?>
                <li class="page-item <?= $page >= $total_pages ? 'disabled' : '' ?>">
                    <a class="page-link" href="<?= $base ?>&p=<?= $page + 1 ?><?= $qs ?>">
                        <i class="fas fa-chevron-right"></i>
                    </a>
                </li>
            </ul>
        </nav>
    </div>
<?php endif; ?>

