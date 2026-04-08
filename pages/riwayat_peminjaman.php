<?php
require_once 'includes/auth.php';
require_user(); // Only users can access this page

$current_user_id = get_current_user_id();
$action = $_GET['action'] ?? 'list';
$peminjaman_id = $_GET['id'] ?? null;

// Determine which column is present for the request owner (pemohon_id or peminjam_id)
$peminjam_col = 'pemohon_id';
$colRes = $mysqli->query("SHOW COLUMNS FROM peminjaman_kendaraan LIKE 'pemohon_id'");
if (!$colRes || $colRes->num_rows === 0) {
    $altRes = $mysqli->query("SHOW COLUMNS FROM peminjaman_kendaraan LIKE 'peminjam_id'");
    if ($altRes && $altRes->num_rows > 0) {
        $peminjam_col = 'peminjam_id';
    }
}

// Handle cancel request
if ($action === 'cancel' && $peminjaman_id) {
    // Use case-insensitive status check to allow 'Pending' or 'pending'
    $stmt = $mysqli->prepare("UPDATE peminjaman_kendaraan SET status = 'cancelled', updated_by = ?, updated_at = NOW() WHERE id = ? AND " . $peminjam_col . " = ? AND LOWER(status) = 'pending'");
    $stmt->bind_param('iii', $current_user_id, $peminjaman_id, $current_user_id);
    
    if ($stmt->execute() && $stmt->affected_rows > 0) {
        $msg = '<div class="alert alert-success">Pengajuan peminjaman berhasil dibatalkan!</div>';
        log_user_activity("Membatalkan pengajuan peminjaman ID: $peminjaman_id");
    } else {
        $msg = '<div class="alert alert-danger">Gagal membatalkan pengajuan. Mungkin status sudah berubah.</div>';
    }
    $stmt->close();
    $action = 'list';
}

// Get user's peminjaman history
$keyword = trim($_GET['q'] ?? '');
$status_filter = $_GET['status'] ?? '';
$limit = 20;
$page = max(1, (int)($_GET['p'] ?? 1));
$offset = ($page - 1) * $limit;

$where_conditions = ["p." . $peminjam_col . " = ?"];
$params = [$current_user_id];
$param_types = 'i';

if ($keyword) {
    $where_conditions[] = "(k.no_reg LIKE ? OR k.no_polisi LIKE ? OR p.keperluan LIKE ? OR p.tujuan LIKE ?)";
    $search_term = "%$keyword%";
    $params = array_merge($params, [$search_term, $search_term, $search_term, $search_term]);
    $param_types .= 'ssss';
}

if ($status_filter) {
    // perform case-insensitive filter by normalizing status in SQL and PHP
    $where_conditions[] = "LOWER(p.status) = ?";
    $params[] = strtolower($status_filter);
    $param_types .= 's';
}

$where_sql = 'WHERE ' . implode(' AND ', $where_conditions);

// detect approver column variants and build optional select/join
$cols_info = $mysqli->query("SHOW COLUMNS FROM peminjaman_kendaraan")->fetch_all(MYSQLI_ASSOC);
$cols_names = array_column($cols_info, 'Field');
$approver_col = null;
foreach (['approved_by','approval_by','approver_id','approved_by_id','approver'] as $c) {
    if (in_array($c, $cols_names)) { $approver_col = $c; break; }
}
$select_extra = '';
$join_approver = '';
if ($approver_col) {
    $select_extra = ", approver.nama_lengkap as approved_by_name";
    $join_approver = " LEFT JOIN pengguna approver ON p.{$approver_col} = approver.id";
}

$sql = "
    SELECT p.*, k.no_polisi, k.no_reg, k.merk, k.tipe, k.jenis" . $select_extra . "
    FROM peminjaman_kendaraan p
    LEFT JOIN kendaraan k ON p.kendaraan_id = k.id" . $join_approver . "
    $where_sql
    ORDER BY p.created_at DESC
    LIMIT ? OFFSET ?
";

$stmt = $mysqli->prepare($sql);
$param_types .= 'ii';
$params[] = $limit;
$params[] = $offset;
$stmt->bind_param($param_types, ...$params);
$stmt->execute();
$peminjaman_list = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$stmt->close();

// Get total count for pagination
$count_sql = "
    SELECT COUNT(*) as total
    FROM peminjaman_kendaraan p
    LEFT JOIN kendaraan k ON p.kendaraan_id = k.id
    $where_sql
";

$count_stmt = $mysqli->prepare($count_sql);
// Remove limit and offset from params for count
$count_params = array_slice($params, 0, -2);
$count_param_types = substr($param_types, 0, -2);
if ($count_params) {
    $count_stmt->bind_param($count_param_types, ...$count_params);
}
$count_stmt->execute();
$total_records = $count_stmt->get_result()->fetch_assoc()['total'];
$count_stmt->close();

$total_pages = ceil($total_records / $limit);

// Get detail data if viewing detail
$detail_data = null;
if ($action === 'detail' && $peminjaman_id) {
    $sql = "SELECT p.*, k.no_polisi, k.no_reg, k.merk, k.tipe, k.jenis, k.warna" . $select_extra
         . " FROM peminjaman_kendaraan p"
         . " LEFT JOIN kendaraan k ON p.kendaraan_id = k.id" . $join_approver
         . " WHERE p.id = ? AND p." . $peminjam_col . " = ?";

    $stmt = $mysqli->prepare($sql);
    $stmt->bind_param('ii', $peminjaman_id, $current_user_id);
    $stmt->execute();
    $detail_data = $stmt->get_result()->fetch_assoc();
    $stmt->close();
}

// Status badge mapping
function getStatusBadge($status) {
    $s = strtolower((string)$status);
    $badges = [
        'pending' => 'badge-warning',
        'approved' => 'badge-success', 
        'rejected' => 'badge-danger',
        'ongoing' => 'badge-info',
        'completed' => 'badge-primary',
        'cancelled' => 'badge-secondary'
    ];
    
    $labels = [
        'pending' => 'Menunggu',
        'approved' => 'Disetujui',
        'rejected' => 'Ditolak', 
        'ongoing' => 'Berlangsung',
        'completed' => 'Selesai',
        'cancelled' => 'Dibatalkan'
    ];
    
    $badge_class = $badges[$s] ?? 'badge-secondary';
    $label = $labels[$s] ?? $status;
    
    return "<span class=\"badge $badge_class\">$label</span>";
}
?>

<div class="container-fluid">
    <div class="page-header">
        <h1><i class="fas fa-history"></i> Riwayat Peminjaman Kendaraan</h1>
            <div class="proposal-actions">
                <a href="index.php?page=form_peminjaman" class="btn btn-primary btn-md"><i class="fas fa-plus"></i> Buka Form</a>
            </div>
    </div>

    <?= $msg ?? '' ?>

<?php if ($action === 'detail' && $detail_data): ?>
    <div class="card">
        <div class="card-header">
            <h3><i class="fas fa-eye"></i> Detail Peminjaman</h3>
        </div>
        <div class="card-body">
            <div class="row g-4 mb-4">
                <div class="col-md-6">
                    <label class="fw-semibold text-dark mb-2 d-block">Kendaraan</label>
                    <div class="bg-light p-3 rounded border">
                        <strong class="text-primary"><?= htmlspecialchars(($detail_data['no_reg'] ?? '') !== '' ? $detail_data['no_reg'] : ($detail_data['no_polisi'] ?? '-')) ?></strong>
                        <?php if (!empty($detail_data['no_polisi'])): ?>
                            <br><small class="text-muted">Nopol: <?= htmlspecialchars($detail_data['no_polisi']) ?></small>
                        <?php endif; ?>
                        <br><?= htmlspecialchars($detail_data['merk']) ?> <?= htmlspecialchars($detail_data['tipe']) ?><br>
                        <small class="text-muted"><?= htmlspecialchars($detail_data['jenis']) ?> - <?= htmlspecialchars($detail_data['warna']) ?></small>
                    </div>
                </div>
                
                <div class="col-md-6">
                    <label class="fw-semibold text-dark mb-2 d-block">Status</label>
                    <div class="bg-light p-3 rounded border"><?= getStatusBadge($detail_data['status']) ?></div>
                </div>
                
                <div class="col-md-6">
                    <label class="fw-semibold text-dark mb-2 d-block">Keperluan</label>
                    <div class="bg-light p-3 rounded border"><?= htmlspecialchars($detail_data['keperluan']) ?></div>
                </div>
                
                <div class="col-md-6">
                    <label class="fw-semibold text-dark mb-2 d-block">Tujuan</label>
                    <div class="bg-light p-3 rounded border"><?= htmlspecialchars($detail_data['tujuan']) ?></div>
                </div>
                
                <div class="col-md-6">
                    <label class="fw-semibold text-dark mb-2 d-block">Periode Peminjaman</label>
                    <div class="bg-light p-3 rounded border">
                        <strong>Mulai:</strong> <?= date('d/m/Y H:i', strtotime($detail_data['tanggal_mulai'])) ?><br>
                        <strong>Selesai:</strong> <?= date('d/m/Y H:i', strtotime($detail_data['tanggal_selesai'])) ?>
                    </div>
                </div>
                
                <div class="col-md-6">
                    <label class="fw-semibold text-dark mb-2 d-block">Sopir</label>
                    <div class="bg-light p-3 rounded border">
                        <?php
                            $sopir_sendiri = !empty($detail_data['sopir_sendiri'] ?? null);
                            $nama_sopir = $detail_data['nama_sopir'] ?? '';
                            echo $sopir_sendiri ? 'Sopir Sendiri' : ($nama_sopir !== '' ? htmlspecialchars($nama_sopir) : '-');
                        ?>
                    </div>
                </div>
                
                <div class="detail-group">
                    <label>Kontak Darurat</label>
                    <div class="detail-value">
                        <?php $kontak_darurat = $detail_data['kontak_darurat'] ?? ''; ?>
                        <?= $kontak_darurat !== '' ? htmlspecialchars($kontak_darurat) : '-' ?>
                    </div>
                </div>
                
                <div class="detail-group">
                    <label>Estimasi</label>
                    <div class="detail-value">
                        <?php $estimasi_km = $detail_data['estimasi_km'] ?? null; $estimasi_bbm = $detail_data['estimasi_bbm'] ?? null; ?>
                        <strong>KM:</strong> <?= is_numeric($estimasi_km) ? number_format($estimasi_km) . ' km' : '-' ?><br>
                        <strong>BBM:</strong> <?= is_numeric($estimasi_bbm) ? 'Rp ' . number_format($estimasi_bbm) : '-' ?>
                    </div>
                </div>
                
                <?php if ((($detail_data['status'] ?? '') === 'Approved') && !empty($detail_data['approved_by_name'] ?? null)): ?>
                <div class="detail-group">
                    <label>Disetujui Oleh</label>
                    <div class="detail-value">
                        <?= htmlspecialchars($detail_data['approved_by_name']) ?><br>
                        <small class="text-muted"><?= !empty($detail_data['approved_at'] ?? null) ? date('d/m/Y H:i', strtotime($detail_data['approved_at'])) : '' ?></small>
                    </div>
                </div>
                <?php endif; ?>
                
                <?php if (($detail_data['status'] === 'Rejected') && !empty($detail_data['rejected_reason'] ?? null)): ?>
                <div class="detail-group">
                    <label>Alasan Ditolak</label>
                    <div class="detail-value">
                        <div class="alert alert-danger">
                            <?= nl2br(htmlspecialchars($detail_data['rejected_reason'])) ?>
                        </div>
                    </div>
                </div>
                <?php endif; ?>
                
                <?php if (!empty($detail_data['notes'] ?? null)): ?>
                <div class="detail-group">
                    <label>Catatan</label>
                    <div class="detail-value"><?= nl2br(htmlspecialchars($detail_data['notes'])) ?></div>
                </div>
                <?php endif; ?>
            </div>
            
            <div class="form-actions">
                <a href="index.php?page=riwayat_peminjaman" class="btn btn-secondary">
                    <i class="fas fa-arrow-left"></i> Kembali
                </a>
            </div>
        </div>
    </div>

<?php else: ?>
    <!-- Search and Filter -->
    <div class="actions-bar">
        <div class="search-box">
            <form method="get" class="search-form">
                <input type="hidden" name="page" value="riwayat_peminjaman">
                <div class="input-group">
                    <input type="text" name="q" placeholder="Cari no. reg, nopol, keperluan, tujuan..." 
                           value="<?= htmlspecialchars($keyword) ?>" class="form-control">
                    <select name="status" class="form-control">
                        <option value="">Semua Status</option>
                        <option value="pending" <?= $status_filter === 'pending' ? 'selected' : '' ?>>Menunggu</option>
                        <option value="approved" <?= $status_filter === 'approved' ? 'selected' : '' ?>>Disetujui</option>
                        <option value="rejected" <?= $status_filter === 'rejected' ? 'selected' : '' ?>>Ditolak</option>
                        <option value="ongoing" <?= $status_filter === 'ongoing' ? 'selected' : '' ?>>Berlangsung</option>
                        <option value="completed" <?= $status_filter === 'completed' ? 'selected' : '' ?>>Selesai</option>
                        <option value="cancelled" <?= $status_filter === 'cancelled' ? 'selected' : '' ?>>Dibatalkan</option>
                    </select>
                    <button type="submit" class="btn btn-primary">
                        <i class="fas fa-search"></i>
                    </button>
                </div>
            </form>
            <?php if ($keyword !== '' || $status_filter): ?>
                <a href="index.php?page=riwayat_peminjaman" class="btn btn-outline">
                    <i class="fas fa-times"></i> Reset
                </a>
            <?php endif; ?>
        </div>
    </div>

    <!-- Data Table -->
    <div class="card">
        <div class="card-header">
            <h3><i class="fas fa-list"></i> Riwayat Peminjaman</h3>
        </div>
        <div class="card-body">
            <div class="table-responsive">
                <table class="table table-striped">
                    <thead>
                        <tr>
                            <th>No</th>
                            <th>Tanggal Ajuan</th>
                            <th>Kendaraan</th>
                            <th>Keperluan</th>
                            <th>Periode</th>
                            <th>Status</th>
                            <th>Aksi</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (count($peminjaman_list) > 0): ?>
                            <?php $no = ($page - 1) * $limit + 1; foreach ($peminjaman_list as $peminjaman): ?>
                                <tr>
                                    <td><?= $no++ ?></td>
                                    <td><?= date('d/m/Y', strtotime($peminjaman['created_at'])) ?></td>
                                    <td>
                                        <div class="vehicle-info">
                                            <strong><?= htmlspecialchars(($peminjaman['no_reg'] ?? '') !== '' ? $peminjaman['no_reg'] : ($peminjaman['no_polisi'] ?? '-')) ?></strong>
                                            <?php if (!empty($peminjaman['no_polisi'])): ?>
                                                <br><small class="text-muted">Nopol: <?= htmlspecialchars($peminjaman['no_polisi']) ?></small>
                                            <?php endif; ?>
                                            <br><small class="text-muted"><?= htmlspecialchars($peminjaman['merk']) ?> <?= htmlspecialchars($peminjaman['tipe']) ?></small>
                                        </div>
                                    </td>
                                    <td>
                                        <div class="text-truncate text-truncate-custom" title="<?= htmlspecialchars($peminjaman['keperluan']) ?>">
                                            <?= htmlspecialchars($peminjaman['keperluan']) ?>
                                        </div>
                                        <small class="text-muted"><?= htmlspecialchars($peminjaman['tujuan']) ?></small>
                                    </td>
                                    <td>
                                        <small>
                                            <?= date('d/m/y H:i', strtotime($peminjaman['tanggal_mulai'])) ?><br>
                                            s/d<br>
                                            <?= date('d/m/y H:i', strtotime($peminjaman['tanggal_selesai'])) ?>
                                        </small>
                                    </td>
                                    <td><?= getStatusBadge($peminjaman['status']) ?></td>
                                    <td>
                                        <div class="action-buttons">
                                            <div class="btn-group" role="group" aria-label="Aksi Peminjaman">
                                                <a href="index.php?page=riwayat_peminjaman&action=detail&id=<?= $peminjaman['id'] ?>"
                                                   class="btn btn-outline-primary btn-sm d-inline-flex align-items-center"
                                                   title="Lihat Detail" aria-label="Lihat Detail">
                                                    <i class="fas fa-eye"></i>
                                                    <span class="d-none d-md-inline ms-2">Detail</span>
                                                </a>
                                                <?php if ($peminjaman['status'] === 'Pending'): ?>
                                                    <a href="index.php?page=riwayat_peminjaman&action=cancel&id=<?= $peminjaman['id'] ?>"
                                                       class="btn btn-outline-danger btn-sm d-inline-flex align-items-center ms-1"
                                                       title="Batalkan" aria-label="Batalkan pengajuan"
                                                       onclick="return confirm('Yakin ingin membatalkan pengajuan ini?')">
                                                        <i class="fas fa-times"></i>
                                                        <span class="d-none d-md-inline ms-2">Batalkan</span>
                                                    </a>
                                                <?php endif; ?>
                                            </div>
                                        </div>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <tr>
                                    <td colspan="7" class="text-center">
                                        <div class="text-center py-5">
                                            <i class="fas fa-history fa-3x text-muted mb-3"></i>
                                            <h5>Tidak Ada Riwayat Peminjaman</h5>
                                            <p class="text-muted">
                                                <?php if ($keyword || $status_filter): ?>
                                                    Tidak ada riwayat yang cocok dengan pencarian
                                                <?php else: ?>
                                                    Anda belum pernah mengajukan peminjaman kendaraan
                                                <?php endif; ?>
                                            </p>
                                            <?php if (!$keyword && !$status_filter): ?>
                                                <a href="index.php?page=form_peminjaman" class="btn btn-primary">
                                                    <i class="fas fa-plus"></i> Ajukan Peminjaman Pertama
                                                </a>
                                            <?php endif; ?>
                                        </div>
                                    </td>
                            </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
            
            <!-- Pagination -->
            <?php if ($total_pages > 1): ?>
                <div class="pagination-wrapper">
                    <nav aria-label="Pagination">
                        <ul class="pagination">
                            <?php if ($page > 1): ?>
                                <li class="page-item">
                                    <a class="page-link" href="index.php?page=riwayat_peminjaman&p=<?= $page - 1 ?><?= $keyword ? '&q=' . urlencode($keyword) : '' ?><?= $status_filter ? '&status=' . urlencode($status_filter) : '' ?>">
                                        <i class="fas fa-chevron-left"></i>
                                    </a>
                                </li>
                            <?php endif; ?>
                            
                            <?php for ($i = max(1, $page - 2); $i <= min($total_pages, $page + 2); $i++): ?>
                                <li class="page-item <?= $i == $page ? 'active' : '' ?>">
                                    <a class="page-link" href="index.php?page=riwayat_peminjaman&p=<?= $i ?><?= $keyword ? '&q=' . urlencode($keyword) : '' ?><?= $status_filter ? '&status=' . urlencode($status_filter) : '' ?>">
                                        <?= $i ?>
                                    </a>
                                </li>
                            <?php endfor; ?>
                            
                            <?php if ($page < $total_pages): ?>
                                <li class="page-item">
                                    <a class="page-link" href="index.php?page=riwayat_peminjaman&p=<?= $page + 1 ?><?= $keyword ? '&q=' . urlencode($keyword) : '' ?><?= $status_filter ? '&status=' . urlencode($status_filter) : '' ?>">
                                        <i class="fas fa-chevron-right"></i>
                                    </a>
                                </li>
                            <?php endif; ?>
                        </ul>
                    </nav>
                    
                    <div class="pagination-info">
                        Menampilkan <?= min($total_records, $offset + 1) ?> - <?= min($total_records, $offset + count($peminjaman_list)) ?> dari <?= $total_records ?> data
                    </div>
                </div>
            <?php endif; ?>
        </div>
    </div>
<?php endif; ?>


