<?php
require_once 'includes/auth.php';
require_role('admin');

// Helpers
require_once __DIR__ . '/../lib/table_helpers.php';

$action = $_GET['action'] ?? '';
$id = isset($_GET['id']) ? intval($_GET['id']) : 0;

// Detail view for a single record
if ($action === 'view' && $id > 0) {
    $driver_join = table_has_columns($mysqli, 'riwayat_pemakaian', ['driver_id']);
    $select_driver = $driver_join ? ", d.nama_lengkap AS driver_name" : ", '' AS driver_name";
    $join_driver = $driver_join ? "LEFT JOIN pengguna d ON rp.driver_id = d.id" : "";

    $sql = "SELECT rp.*, k.no_reg, k.no_polisi, k.merk, k.tipe, ua.username AS username, p.nama_lengkap AS pemakai" . $select_driver . "\n        FROM riwayat_pemakaian rp\n        JOIN kendaraan k ON rp.kendaraan_id = k.id\n        LEFT JOIN user_account ua ON rp.user_id = ua.id\n        LEFT JOIN pengguna p ON ua.pengguna_id = p.id\n        " . $join_driver . "\n        WHERE rp.id = ? LIMIT 1";

    $stmt = $mysqli->prepare($sql);
    $stmt->bind_param('i', $id);
    $stmt->execute();
    $result = $stmt->get_result();
    $record = $result->fetch_assoc();
    $stmt->close();

    if (!$record) {
        header('Location: index.php?page=riwayat_pemakaian');
        exit;
    }
    ?>
    <div class="page-header">
        <h1><i class="fas fa-history me-2"></i> Detail Riwayat Pemakaian</h1>
    </div>

    <div class="content-container">
        <div class="mb-3">
            <a href="index.php?page=riwayat_pemakaian" class="btn btn-secondary btn-sm"><i class="fas fa-arrow-left me-1"></i> Kembali ke daftar</a>
        </div>

        <div class="card shadow-sm">
            <div class="card-header bg-light d-flex justify-content-between align-items-center">
                <div>
                    <strong><?= htmlspecialchars($record['merk'] . ' ' . $record['tipe']) ?></strong>
                    <div class="small text-muted">No. Reg: <?= htmlspecialchars($record['no_reg'] ?? '-') ?></div>
                </div>
                <div class="text-end small text-muted">
                    <?= htmlspecialchars($record['pemakai'] ?? $record['username'] ?? 'N/A') ?>
                    <div><?= date('d/m/Y', strtotime($record['tanggal'])) ?></div>
                </div>
            </div>
            <div class="card-body">
                <div class="row g-3">
                    <div class="col-md-6">
                        <label class="small text-muted">Tujuan</label>
                        <div><?= htmlspecialchars($record['tujuan'] ?? '-') ?></div>
                    </div>
                    <div class="col-md-6">
                        <label class="small text-muted">Keperluan</label>
                        <div><?= htmlspecialchars($record['keperluan'] ?? '-') ?></div>
                    </div>
                    <div class="col-md-4">
                        <label class="small text-muted">Jam Keluar / Kembali</label>
                        <div><?= htmlspecialchars($record['jam_keluar'] ?? '-') ?> - <?= htmlspecialchars($record['jam_kembali'] ?? '-') ?></div>
                    </div>
                    <div class="col-md-4">
                        <label class="small text-muted">KM Awal / Akhir</label>
                        <div><?= number_format($record['km_awal'] ?? 0) ?> / <?= number_format($record['km_akhir'] ?? 0) ?> km</div>
                    </div>
                    <div class="col-md-4">
                        <label class="small text-muted">Driver</label>
                        <div><?= htmlspecialchars($record['driver_name'] ?? 'Tidak tercatat') ?></div>
                    </div>
                    <div class="col-12">
                        <label class="small text-muted">Catatan</label>
                        <div><?= nl2br(htmlspecialchars($record['catatan'] ?? '-')) ?></div>
                    </div>
                </div>
            </div>
        </div>
    </div>
    <?php
    return;
}

// Listing view for admin
$page = isset($_GET['page_num']) ? max(1, intval($_GET['page_num'])) : 1;
$limit = 20;
$offset = ($page - 1) * $limit;

$filters = [];
$types = '';
$params = [];

// Optional filters
if (!empty($_GET['kendaraan_id'])) {
    $filters[] = 'rp.kendaraan_id = ?';
    $params[] = intval($_GET['kendaraan_id']);
    $types .= 'i';
}

if (!empty($_GET['q'])) {
    // search across vehicle and pemakai
    $q = '%' . $_GET['q'] . '%';
    $filters[] = '(k.no_reg LIKE ? OR k.no_polisi LIKE ? OR k.merk LIKE ? OR k.tipe LIKE ? OR p.nama_lengkap LIKE ? OR ua.username LIKE ?)';
    $params = array_merge($params, [$q, $q, $q, $q, $q, $q]);
    $types .= 'ssssss';
}

if (!empty($_GET['date_from'])) {
    $filters[] = 'rp.tanggal >= ?';
    $params[] = $_GET['date_from'];
    $types .= 's';
}
if (!empty($_GET['date_to'])) {
    $filters[] = 'rp.tanggal <= ?';
    $params[] = $_GET['date_to'];
    $types .= 's';
}

$filter_sql = '';
if (!empty($filters)) {
    $filter_sql = ' WHERE ' . implode(' AND ', $filters);
}

$driver_join = table_has_columns($mysqli, 'riwayat_pemakaian', ['driver_id']);
$select_driver = $driver_join ? ", d.nama_lengkap AS driver_name" : ", '' AS driver_name";
$join_driver = $driver_join ? "LEFT JOIN pengguna d ON rp.driver_id = d.id" : "";

$base_from = "riwayat_pemakaian rp\n        JOIN kendaraan k ON rp.kendaraan_id = k.id\n        LEFT JOIN user_account ua ON rp.user_id = ua.id\n        LEFT JOIN pengguna p ON ua.pengguna_id = p.id\n        " . $join_driver;

// Count total
$count_sql = "SELECT COUNT(*) as total FROM " . $base_from . $filter_sql;
$count_stmt = $mysqli->prepare($count_sql);
if ($types !== '') {
    $count_stmt->bind_param($types, ...$params);
}
$count_stmt->execute();
$total = (int)$count_stmt->get_result()->fetch_assoc()['total'];
$count_stmt->close();

// Fetch page
$select_sql = "SELECT rp.*, k.no_reg, k.no_polisi, k.merk, k.tipe, ua.username AS username, p.nama_lengkap AS pemakai" . $select_driver . "\n        FROM " . $base_from . $filter_sql . "\n        ORDER BY rp.tanggal DESC, rp.id DESC\n        LIMIT ? OFFSET ?";

$stmt = $mysqli->prepare($select_sql);
// bind params + limit + offset
$bind_params = $params;
$bind_types = $types;
$bind_params[] = $limit;
$bind_params[] = $offset;
$bind_types .= 'ii';
if ($bind_types !== '') {
    $stmt->bind_param($bind_types, ...$bind_params);
}
$stmt->execute();
$rows = $stmt->get_result();
$stmt->close();

$total_pages = max(1, ceil($total / $limit));
?>

<div class="page-header">
    <h1><i class="fas fa-history me-2"></i> Riwayat Pemakaian (Admin)</h1>
</div>

<div class="content-container">
    <div class="bg-white p-3 rounded-2 mb-4 d-flex justify-content-between align-items-center shadow-sm">
        <div class="text-muted small">
            <span class="fw-semibold"><i class="fas fa-list text-primary me-2"></i> Total: <?= $total ?> riwayat</span>
        </div>
        <form method="get" class="d-flex gap-2 align-items-center">
            <input type="hidden" name="page" value="riwayat_pemakaian">
            <input type="text" name="q" value="<?= htmlspecialchars($_GET['q'] ?? '') ?>" placeholder="Cari kendaraan / pengguna" class="form-control form-control-sm">
            <input type="date" name="date_from" value="<?= htmlspecialchars($_GET['date_from'] ?? '') ?>" class="form-control form-control-sm">
            <input type="date" name="date_to" value="<?= htmlspecialchars($_GET['date_to'] ?? '') ?>" class="form-control form-control-sm">
            <button class="btn btn-primary btn-sm" type="submit"><i class="fas fa-filter me-1"></i> Filter</button>
        </form>
    </div>

    <?php if ($rows && $rows->num_rows > 0): ?>
        <div class="table-responsive">
            <table class="table table-striped table-hover">
                <thead>
                    <tr>
                        <th>No</th>
                        <th>Tanggal</th>
                        <th>Kendaraan</th>
                        <th>No. Reg</th>
                        <th>Pemakai</th>
                        <th>Driver</th>
                        <th>KM</th>
                        <th>BBM (Keluar/Kembali)</th>
                        <th>Keperluan</th>
                        <th>Aksi</th>
                    </tr>
                </thead>
                <tbody>
                    <?php $i = $offset + 1; while ($r = $rows->fetch_assoc()): ?>
                        <tr>
                            <td><?= $i++ ?></td>
                            <td><?= date('d/m/Y', strtotime($r['tanggal'])) ?><br><small><?= htmlspecialchars($r['jam_keluar'] ?? '') ?> - <?= htmlspecialchars($r['jam_kembali'] ?? '') ?></small></td>
                            <td><?= htmlspecialchars($r['merk'] . ' ' . $r['tipe']) ?></td>
                            <td><?= htmlspecialchars($r['no_reg'] ?? '-') ?></td>
                            <td><?= htmlspecialchars($r['pemakai'] ?? $r['username'] ?? '-') ?></td>
                            <td><?= htmlspecialchars($r['driver_name'] ?? '-') ?></td>
                            <td><?= number_format($r['km_awal'] ?? 0) ?> / <?= number_format($r['km_akhir'] ?? 0) ?></td>
                            <td><?= !empty($r['bbm_keluar']) ? number_format($r['bbm_keluar'],1) . ' L' : '-' ?> / <?= !empty($r['bbm_kembali']) ? number_format($r['bbm_kembali'],1) . ' L' : '-' ?></td>
                            <td><?= htmlspecialchars(mb_strimwidth($r['keperluan'] ?? '-', 0, 60, '...')) ?></td>
                            <td>
                                <div class="btn-group" role="group">
                                    <a href="index.php?page=riwayat_pemakaian&action=view&id=<?= $r['id'] ?>" class="btn btn-sm btn-outline-info" title="Lihat Detail"><i class="fas fa-eye"></i></a>
                                </div>
                            </td>
                        </tr>
                    <?php endwhile; ?>
                </tbody>
            </table>
        </div>

        <?php if ($total_pages > 1): ?>
            <div class="pagination-container d-flex justify-content-between align-items-center mt-3">
                <div>
                    Halaman <?= $page ?> dari <?= $total_pages ?> (<?= $total ?>)
                </div>
                <div class="btn-group btn-group-sm">
                    <?php if ($page > 1): ?>
                        <a class="btn btn-outline-secondary" href="?page=riwayat_pemakaian&page_num=<?= $page - 1 ?><?= !empty($_GET['q']) ? '&q=' . urlencode($_GET['q']) : '' ?>">Prev</a>
                    <?php endif; ?>
                    <?php if ($page < $total_pages): ?>
                        <a class="btn btn-outline-secondary" href="?page=riwayat_pemakaian&page_num=<?= $page + 1 ?><?= !empty($_GET['q']) ? '&q=' . urlencode($_GET['q']) : '' ?>">Next</a>
                    <?php endif; ?>
                </div>
            </div>
        <?php endif; ?>

    <?php else: ?>
        <div class="empty-state text-center py-5">
            <i class="fas fa-history fa-2x mb-3 text-muted"></i>
            <h4>Tidak ada riwayat pemakaian</h4>
            <p class="text-muted">Coba ubah filter tanggal atau kata kunci.</p>
        </div>
    <?php endif; ?>

</div>

<?php
