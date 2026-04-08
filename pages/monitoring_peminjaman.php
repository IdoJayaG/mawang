<?php
if (!is_logged_in() || get_current_role() !== 'admin') {
    header('Location: login.php');
    exit();
}

$current_user = get_logged_in_user();

// Pagination
$limit = 20;
$page_num = isset($_GET['p']) ? (int)$_GET['p'] : 1;
$offset = ($page_num - 1) * $limit;

// Filter parameters
$status_filter = isset($_GET['status']) ? $_GET['status'] : '';
$search = isset($_GET['search']) ? trim($_GET['search']) : '';
$tanggal_dari = isset($_GET['tanggal_dari']) ? $_GET['tanggal_dari'] : '';
$tanggal_sampai = isset($_GET['tanggal_sampai']) ? $_GET['tanggal_sampai'] : '';

// Auto-transition statuses based on current time
try {
    // 1) Any approved/ongoing past end time -> completed
    $mysqli->query("UPDATE peminjaman_kendaraan 
                    SET status = 'Completed', tanggal_selesai_aktual = COALESCE(tanggal_selesai_aktual, NOW()), updated_at = NOW()
                    WHERE status IN ('Approved','Ongoing') AND NOW() > tanggal_selesai");

    // 2) Any approved that should start now -> ongoing
    $mysqli->query("UPDATE peminjaman_kendaraan 
                    SET status = 'Ongoing', tanggal_mulai_aktual = COALESCE(tanggal_mulai_aktual, NOW()), updated_at = NOW()
                    WHERE status = 'Approved' AND NOW() >= tanggal_mulai AND NOW() <= tanggal_selesai");
} catch (Throwable $e) {
    // ignore automation errors
}

// Build query
$where_conditions = [];
$params = [];

// detect whether peminjaman_kendaraan uses pemohon_id or peminjam_id
$peminjam_col = 'pemohon_id';
$colRes = $mysqli->query("SHOW COLUMNS FROM peminjaman_kendaraan LIKE 'pemohon_id'");
if (!$colRes || $colRes->num_rows === 0) {
    $altRes = $mysqli->query("SHOW COLUMNS FROM peminjaman_kendaraan LIKE 'peminjam_id'");
    if ($altRes && $altRes->num_rows > 0) {
        $peminjam_col = 'peminjam_id';
    }
}

if (!empty($status_filter)) {
    $where_conditions[] = "LOWER(p.status) = ?";
    $params[] = strtolower($status_filter);
}

if (!empty($search)) {
    $where_conditions[] = "(u.nama_lengkap LIKE ? OR u.nrp_nip LIKE ? OR k.no_reg LIKE ? OR k.no_polisi LIKE ? OR k.merk LIKE ? OR p.keperluan LIKE ?)";
    $search_param = "%$search%";
    $params = array_merge($params, [$search_param, $search_param, $search_param, $search_param, $search_param, $search_param]);
}

if (!empty($tanggal_dari)) {
    $where_conditions[] = "DATE(p.tanggal_mulai) >= ?";
    $params[] = $tanggal_dari;
}

if (!empty($tanggal_sampai)) {
    $where_conditions[] = "DATE(p.tanggal_selesai) <= ?";
    $params[] = $tanggal_sampai;
}

$where_clause = !empty($where_conditions) ? "WHERE " . implode(" AND ", $where_conditions) : "";

// Get total count
$count_query = "SELECT COUNT(*) as total FROM peminjaman_kendaraan p 
                JOIN pengguna u ON p." . $peminjam_col . " = u.id 
                JOIN kendaraan k ON p.kendaraan_id = k.id 
                $where_clause";

$count_stmt = $mysqli->prepare($count_query);
if (!empty($params)) {
    $types = str_repeat('s', count($params));
    $count_stmt->bind_param($types, ...$params);
}
$count_stmt->execute();
$total_records = $count_stmt->get_result()->fetch_assoc()['total'];
$total_pages = ceil($total_records / $limit);

// Get records
$cols_info = $mysqli->query("SHOW COLUMNS FROM peminjaman_kendaraan")->fetch_all(MYSQLI_ASSOC);
$cols_names = array_column($cols_info, 'Field');
$approver_col = null;
foreach (['approved_by','approval_by','approver_id','approved_by_id','approver'] as $c) {
    if (in_array($c, $cols_names)) { $approver_col = $c; break; }
}
$select_extra = '';
$join_approver = '';
if ($approver_col) {
    $select_extra = ", admin.nama_lengkap as nama_admin_approval";
    $join_approver = " LEFT JOIN pengguna admin ON p.{$approver_col} = admin.id";
}

$query =
    "SELECT p.*, u.nama_lengkap as nama_peminjam, u.nrp_nip, u.pangkat, u.jabatan, "
    . "k.no_reg, k.no_polisi, k.merk, k.tipe, k.tahun_pembuatan, k.jenis, k.warna" . $select_extra .
    " FROM peminjaman_kendaraan p"
    . " JOIN pengguna u ON p." . $peminjam_col . " = u.id"
    . " JOIN kendaraan k ON p.kendaraan_id = k.id" . $join_approver .
    " " . $where_clause .
    " ORDER BY p.created_at DESC"
    . " LIMIT ? OFFSET ?";

$stmt = $mysqli->prepare($query);
$params[] = $limit;
$params[] = $offset;
$types = str_repeat('s', count($params) - 2) . 'ii';
$stmt->bind_param($types, ...$params);
$stmt->execute();
$peminjaman_list = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);

// Statistics
$stats_query = "SELECT 
                COUNT(*) as total,
                SUM(CASE WHEN LOWER(status) = 'pending' THEN 1 ELSE 0 END) as pending,
                SUM(CASE WHEN LOWER(status) = 'approved' THEN 1 ELSE 0 END) as approved,
                SUM(CASE WHEN LOWER(status) = 'rejected' THEN 1 ELSE 0 END) as rejected,
                SUM(CASE WHEN LOWER(status) = 'ongoing' THEN 1 ELSE 0 END) as ongoing,
                SUM(CASE WHEN LOWER(status) = 'completed' THEN 1 ELSE 0 END) as completed,
                SUM(CASE WHEN LOWER(status) = 'cancelled' THEN 1 ELSE 0 END) as cancelled
                FROM peminjaman_kendaraan";
$stats_result = $mysqli->query($stats_query);
$stats = $stats_result->fetch_assoc();

// --- Export handling (Excel/CSV and PDF) ---
if (!empty($_GET['export'])) {
    $export = strtolower($_GET['export']);

    // Rebuild export query same as $query but without LIMIT/OFFSET
    $export_sql = 
        "SELECT p.*, u.nama_lengkap as nama_peminjam, u.nrp_nip, u.pangkat, u.jabatan, "
        . "k.no_reg, k.no_polisi, k.merk, k.tipe, k.tahun_pembuatan, k.jenis, k.warna" . $select_extra .
        " FROM peminjaman_kendaraan p"
        . " JOIN pengguna u ON p." . $peminjam_col . " = u.id"
        . " JOIN kendaraan k ON p.kendaraan_id = k.id" . $join_approver .
        " " . $where_clause .
        " ORDER BY p.created_at DESC";

    $export_stmt = $mysqli->prepare($export_sql);
    // Prepare params without the LIMIT/OFFSET which were appended earlier
    $export_params = $params;
    if (count($export_params) >= 2 && is_numeric(end($export_params)) && is_numeric($export_params[count($export_params)-2])) {
        // remove last two entries (limit, offset)
        $export_params = array_slice($export_params, 0, -2);
    }
    if ($export_stmt) {
        if (!empty($export_params)) {
            $types = str_repeat('s', count($export_params));
            $export_stmt->bind_param($types, ...$export_params);
        }
        $export_stmt->execute();
        $export_rows = $export_stmt->get_result()->fetch_all(MYSQLI_ASSOC);
        $export_stmt->close();
    } else {
        $export_rows = [];
    }

    // XLSX via CSV export (Excel will open CSV)
    if ($export === 'excel' || $export === 'csv') {
        $filename = 'peminjaman_export_' . date('Ymd_His') . '.csv';
    // Clean any existing output buffers so headers can be sent cleanly
    while (ob_get_level() > 0) { ob_end_clean(); }
    header('Content-Type: text/csv; charset=UTF-8');
    header('Content-Disposition: attachment; filename="' . $filename . '"');
        // UTF-8 BOM for Excel compatibility
        echo "\xEF\xBB\xBF";
        $out = fopen('php://output', 'w');
        // Header row
    $headers = ['ID','Nama Peminjam','NRP/NIP','Pangkat','Jabatan','No. Reg','Merk','Tipe','Mulai','Selesai','Keperluan','Status','Approved By','Approved At'];
        fputcsv($out, $headers);
        foreach ($export_rows as $r) {
            $row = [
                $r['id'] ?? '',
                $r['nama_peminjam'] ?? '',
                $r['nrp_nip'] ?? '',
                $r['pangkat'] ?? '',
                $r['jabatan'] ?? '',
        $r['no_reg'] ?? '',
                $r['merk'] ?? '',
                $r['tipe'] ?? '',
                !empty($r['tanggal_mulai']) ? date('d/m/Y H:i', strtotime($r['tanggal_mulai'])) : '',
                !empty($r['tanggal_selesai']) ? date('d/m/Y H:i', strtotime($r['tanggal_selesai'])) : '',
                $r['keperluan'] ?? '',
                $r['status'] ?? '',
                $r['nama_admin_approval'] ?? '',
                !empty($r['approved_at']) ? date('d/m/Y H:i', strtotime($r['approved_at'])) : ''
            ];
            fputcsv($out, $row);
        }
        fclose($out);
        exit;
    }

    // PDF export using TCPDF if available
    if ($export === 'pdf') {
        // Try to include TCPDF from vendor
        $tcpdf_path = __DIR__ . '/../vendor/tecnickcom/tcpdf/tcpdf.php';
        if (file_exists($tcpdf_path)) {
            // Ensure no output has leaked before TCPDF sends headers
            while (ob_get_level() > 0) { ob_end_clean(); }
            require_once $tcpdf_path;
            $pdf = new TCPDF('P', 'mm', 'A4', true, 'UTF-8', false);
            $pdf->SetCreator(PDF_CREATOR);
            $pdf->SetAuthor('SI-KENDI');
            $pdf->SetTitle('Export Peminjaman Kendaraan');
            $pdf->setPrintHeader(false);
            $pdf->setPrintFooter(false);
            $pdf->SetAutoPageBreak(TRUE, 10);
            $pdf->AddPage();

            // Build simple HTML table
            $html = '<h2>Data Peminjaman Kendaraan</h2>';
            $html .= '<table border="1" cellpadding="4" cellspacing="0" width="100%">';
            $html .= '<thead><tr style="background:#f2f2f2; font-weight:bold;">';
            $cols = ['ID','Nama Peminjam','NRP/NIP','No. Reg','Mulai','Selesai','Keperluan','Status','Approved By'];
            foreach ($cols as $c) $html .= '<th>' . htmlspecialchars($c) . '</th>';
            $html .= '</tr></thead><tbody>';
            foreach ($export_rows as $r) {
                $html .= '<tr>';
                $html .= '<td>' . htmlspecialchars($r['id'] ?? '') . '</td>';
                $html .= '<td>' . htmlspecialchars($r['nama_peminjam'] ?? '') . '</td>';
                $html .= '<td>' . htmlspecialchars($r['nrp_nip'] ?? '') . '</td>';
                $html .= '<td>' . htmlspecialchars($r['no_reg'] ?? '') . '</td>';
                $html .= '<td>' . (!empty($r['tanggal_mulai']) ? htmlspecialchars(date('d/m/Y H:i', strtotime($r['tanggal_mulai']))) : '') . '</td>';
                $html .= '<td>' . (!empty($r['tanggal_selesai']) ? htmlspecialchars(date('d/m/Y H:i', strtotime($r['tanggal_selesai']))) : '') . '</td>';
                $html .= '<td>' . htmlspecialchars($r['keperluan'] ?? '') . '</td>';
                $html .= '<td>' . htmlspecialchars($r['status'] ?? '') . '</td>';
                $html .= '<td>' . htmlspecialchars($r['nama_admin_approval'] ?? '') . '</td>';
                $html .= '</tr>';
            }
            $html .= '</tbody></table>';

            $pdf->writeHTML($html, true, false, true, false, '');
            $filename = 'peminjaman_export_' . date('Ymd_His') . '.pdf';
            $pdf->Output($filename, 'D');
            exit;
        } else {
            // Fallback: generate simple HTML and let browser print to PDF
            // Clean any output buffers to avoid mixed content
            while (ob_get_level() > 0) { ob_end_clean(); }
            header('Content-Type: text/html; charset=UTF-8');
            echo '<html><head><meta charset="utf-8"><title>Export Peminjaman</title></head><body>';
            echo '<h2>Data Peminjaman Kendaraan</h2>';
            echo '<table border="1" cellpadding="4" cellspacing="0" width="100%">';
            echo '<thead><tr><th>ID</th><th>Nama</th><th>NRP/NIP</th><th>No. Reg</th><th>Mulai</th><th>Selesai</th><th>Keperluan</th><th>Status</th></tr></thead><tbody>';
            foreach ($export_rows as $r) {
                echo '<tr>';
                echo '<td>' . htmlspecialchars($r['id'] ?? '') . '</td>';
                echo '<td>' . htmlspecialchars($r['nama_peminjam'] ?? '') . '</td>';
                echo '<td>' . htmlspecialchars($r['nrp_nip'] ?? '') . '</td>';
                echo '<td>' . htmlspecialchars($r['no_reg'] ?? '') . '</td>';
                echo '<td>' . (!empty($r['tanggal_mulai']) ? htmlspecialchars(date('d/m/Y H:i', strtotime($r['tanggal_mulai']))) : '') . '</td>';
                echo '<td>' . (!empty($r['tanggal_selesai']) ? htmlspecialchars(date('d/m/Y H:i', strtotime($r['tanggal_selesai']))) : '') . '</td>';
                echo '<td>' . htmlspecialchars($r['keperluan'] ?? '') . '</td>';
                echo '<td>' . htmlspecialchars($r['status'] ?? '') . '</td>';
                echo '</tr>';
            }
            echo '</tbody></table></body></html>';
            exit;
        }
    }
}

function getStatusBadge($status) {
    // Use explicit colors to ensure badge backgrounds are visible
    $colors = [
        'pending' => ['bg' => '#ffc107', 'text' => '#212529'], // warning
        'approved' => ['bg' => '#17a2b8', 'text' => '#ffffff'], // info
        'rejected' => ['bg' => '#dc3545', 'text' => '#ffffff'], // danger
        'ongoing' => ['bg' => '#007bff', 'text' => '#ffffff'], // primary
        'completed' => ['bg' => '#28a745', 'text' => '#ffffff'], // success
        'cancelled' => ['bg' => '#6c757d', 'text' => '#ffffff'] // secondary
    ];
    $labels = [
        'pending' => 'Menunggu',
        'approved' => 'Disetujui',
        'rejected' => 'Ditolak',
        'ongoing' => 'Berlangsung',
        'completed' => 'Selesai',
        'cancelled' => 'Dibatalkan'
    ];

    $key = strtolower($status ?? '');
    $color = $colors[$key] ?? ['bg' => '#6c757d', 'text' => '#ffffff'];
    $label = $labels[$key] ?? ucfirst($status);

    return '<span class="badge" style="background-color: ' . $color['bg'] . '; color: ' . $color['text'] . ';">' . htmlspecialchars($label) . '</span>';
}

function formatDateTime($datetime) {
    if (empty($datetime) || $datetime === '0000-00-00' || $datetime === '0000-00-00 00:00:00') {
        return '-';
    }
    $ts = strtotime((string)$datetime);
    if ($ts === false) {
        return '-';
    }
    return date('d/m/Y H:i', $ts);
}
?>

<div class="container-fluid">
    <!-- Header -->
    <div class="row mb-4">
        <div class="col-12">
            <div class="d-flex justify-content-between align-items-center">
                <div>
                    <h2><i class="fas fa-monitor"></i> Monitoring Peminjaman Kendaraan</h2>
                    <p class="text-muted">Monitor dan kelola semua peminjaman kendaraan</p>
                </div>
                <div>
                    <button type="button" class="btn btn-success" onclick="exportData('excel')">
                        <i class="fas fa-file-excel"></i> Export Excel
                    </button>
                    <button type="button" class="btn btn-danger" onclick="exportData('pdf')">
                        <i class="fas fa-file-pdf"></i> Export PDF
                    </button>
                </div>
            </div>
        </div>
    </div>

    <!-- Statistics Cards -->
    <div class="row mb-4">
        <div class="col-lg-2 col-md-4 col-6">
            <div class="stat-card">
                <div class="stat-icon bg-primary">
                    <i class="fas fa-list"></i>
                </div>
                <div class="stat-content">
                    <h3><?= number_format($stats['total']) ?></h3>
                    <p>Total Peminjaman</p>
                </div>
            </div>
        </div>
        <div class="col-lg-2 col-md-4 col-6">
            <div class="stat-card">
                <div class="stat-icon bg-warning">
                    <i class="fas fa-clock"></i>
                </div>
                <div class="stat-content">
                    <h3><?= number_format($stats['pending']) ?></h3>
                    <p>Menunggu</p>
                </div>
            </div>
        </div>
        <div class="col-lg-2 col-md-4 col-6">
            <div class="stat-card">
                <div class="stat-icon bg-info">
                    <i class="fas fa-check"></i>
                </div>
                <div class="stat-content">
                    <h3><?= number_format($stats['approved']) ?></h3>
                    <p>Disetujui</p>
                </div>
            </div>
        </div>
        <div class="col-lg-2 col-md-4 col-6">
            <div class="stat-card">
                <div class="stat-icon bg-primary">
                    <i class="fas fa-play"></i>
                </div>
                <div class="stat-content">
                    <h3><?= number_format($stats['ongoing']) ?></h3>
                    <p>Berlangsung</p>
                </div>
            </div>
        </div>
        <div class="col-lg-2 col-md-4 col-6">
            <div class="stat-card">
                <div class="stat-icon bg-success">
                    <i class="fas fa-check-circle"></i>
                </div>
                <div class="stat-content">
                    <h3><?= number_format($stats['completed']) ?></h3>
                    <p>Selesai</p>
                </div>
            </div>
        </div>
        <div class="col-lg-2 col-md-4 col-6">
            <div class="stat-card">
                <div class="stat-icon bg-danger">
                    <i class="fas fa-times-circle"></i>
                </div>
                <div class="stat-content">
                    <h3><?= number_format($stats['rejected'] + $stats['cancelled']) ?></h3>
                    <p>Ditolak/Batal</p>
                </div>
            </div>
        </div>
    </div>

    <!-- Filters -->
    <div class="card mb-4">
        <div class="card-header">
            <h5><i class="fas fa-filter"></i> Filter & Pencarian</h5>
        </div>
        <div class="card-body">
            <form method="GET" action="">
                <input type="hidden" name="page" value="monitoring_peminjaman">
                <div class="row">
                    <div class="col-md-3">
                        <label>Status:</label>
                        <select name="status" class="form-control">
                            <option value="">Semua Status</option>
                            <option value="pending" <?= $status_filter === 'pending' ? 'selected' : '' ?>>Menunggu</option>
                            <option value="approved" <?= $status_filter === 'approved' ? 'selected' : '' ?>>Disetujui</option>
                            <option value="rejected" <?= $status_filter === 'rejected' ? 'selected' : '' ?>>Ditolak</option>
                            <option value="ongoing" <?= $status_filter === 'ongoing' ? 'selected' : '' ?>>Berlangsung</option>
                            <option value="completed" <?= $status_filter === 'completed' ? 'selected' : '' ?>>Selesai</option>
                            <option value="cancelled" <?= $status_filter === 'cancelled' ? 'selected' : '' ?>>Dibatalkan</option>
                        </select>
                    </div>
                    <div class="col-md-3">
                        <label>Pencarian:</label>
                        <input type="text" name="search" class="form-control" placeholder="Nama, NRP, No. Reg, Merk..." value="<?= htmlspecialchars($search) ?>">
                    </div>
                    <div class="col-md-2">
                        <label>Dari:</label>
                        <input type="date" name="tanggal_dari" class="form-control" value="<?= $tanggal_dari ?>">
                    </div>
                    <div class="col-md-2">
                        <label>Sampai:</label>
                        <input type="date" name="tanggal_sampai" class="form-control" value="<?= $tanggal_sampai ?>">
                    </div>
                    <div class="col-md-2">
                        <label>&nbsp;</label>
                        <div>
                            <button type="submit" class="btn btn-primary btn-block">
                                <i class="fas fa-search"></i> Filter
                            </button>
                        </div>
                    </div>
                </div>
            </form>
        </div>
    </div>

    <!-- Peminjaman Table -->
    <div class="card">
        <div class="card-header d-flex justify-content-between align-items-center">
            <h5><i class="fas fa-table"></i> Data Peminjaman (<?= number_format($total_records) ?> records)</h5>
            <div>
                Halaman <?= $page_num ?> dari <?= $total_pages ?>
            </div>
        </div>
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table table-striped table-hover mb-0">
                    <thead class="bg-light">
                        <tr>
                            <th width="5%">No</th>
                            <th width="12%">Peminjam</th>
                            <th width="15%">Kendaraan</th>
                            <th width="20%">Periode Peminjaman</th>
                            <th width="15%">Keperluan</th>
                            <th width="8%">Status</th>
                            <th width="12%">Approved By</th>
                            <th width="8%">Aksi</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($peminjaman_list)): ?>
                            <tr>
                                <td colspan="8" class="text-center py-4">
                                    <i class="fas fa-inbox fa-3x text-muted mb-3"></i>
                                    <p class="text-muted">Tidak ada data peminjaman ditemukan</p>
                                </td>
                            </tr>
                        <?php else: ?>
                            <?php foreach ($peminjaman_list as $index => $p): ?>
                                <tr>
                                    <td><?= $offset + $index + 1 ?></td>
                                    <td>
                                        <strong><?= htmlspecialchars($p['nama_peminjam']) ?></strong><br>
                                        <small class="text-muted">
                                            <?= htmlspecialchars($p['pangkat']) ?> | <?= htmlspecialchars($p['nrp_nip']) ?><br>
                                            <?= htmlspecialchars($p['jabatan']) ?>
                                        </small>
                                    </td>
                                    <td>
                                        <strong><?= htmlspecialchars($p['no_reg']) ?></strong><br>
                                        <small class="text-muted">
                                            <?= htmlspecialchars($p['merk'] . ' ' . $p['tipe']) ?> (<?= $p['tahun_pembuatan'] ?>)<br>
                                            <?= ucfirst($p['jenis']) ?> | <?= htmlspecialchars($p['warna']) ?>
                                        </small>
                                    </td>
                                    <td>
                                        <strong>Mulai:</strong> <?= formatDateTime($p['tanggal_mulai']) ?><br>
                                        <strong>Selesai:</strong> <?= formatDateTime($p['tanggal_selesai']) ?><br>
                                        <small class="text-muted">
                                            <?php
                                            $start = new DateTime($p['tanggal_mulai']);
                                            $end = new DateTime($p['tanggal_selesai']);
                                            $diff = $start->diff($end);
                                            echo $diff->days . ' hari ' . $diff->h . ' jam';
                                            ?>
                                        </small>
                                    </td>
                                    <td>
                                        <span class="d-inline-block text-truncate max-width-150" title="<?= htmlspecialchars($p['keperluan']) ?>">
                                            <?= htmlspecialchars($p['keperluan']) ?>
                                        </span>
                                    </td>
                                    <td><?= getStatusBadge($p['status']) ?></td>
                                    <td>
                                        <?php if (!empty($p['nama_admin_approval'])): ?>
                                            <small>
                                                <?= htmlspecialchars($p['nama_admin_approval']) ?><br>
                                                <span class="text-muted"><?= formatDateTime($p['approved_at'] ?? null) ?></span>
                                            </small>
                                        <?php else: ?>
                                            <span class="text-muted">-</span>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <div class="btn-group" role="group">
                                            <button type="button" class="btn btn-sm btn-info" onclick="viewDetail(<?= $p['id'] ?>)" title="Detail">
                                                <i class="fas fa-eye"></i>
                                            </button>
                                            <?php if (strtolower($p['status']) === 'ongoing'): ?>
                                                <button type="button" class="btn btn-sm btn-success" onclick="markCompleted(<?= $p['id'] ?>)" title="Tandai Selesai">
                                                    <i class="fas fa-check"></i>
                                                </button>
                                            <?php endif; ?>
                                        </div>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
        
        <!-- Pagination -->
        <?php if ($total_pages > 1): ?>
            <div class="card-footer">
                <nav aria-label="Pagination">
                    <ul class="pagination justify-content-center mb-0">
                        <?php if ($page_num > 1): ?>
                            <li class="page-item">
                                <a class="page-link" href="?page=monitoring_peminjaman&p=<?= $page_num - 1 ?><?= !empty($status_filter) ? '&status='.$status_filter : '' ?><?= !empty($search) ? '&search='.urlencode($search) : '' ?><?= !empty($tanggal_dari) ? '&tanggal_dari='.$tanggal_dari : '' ?><?= !empty($tanggal_sampai) ? '&tanggal_sampai='.$tanggal_sampai : '' ?>">Previous</a>
                            </li>
                        <?php endif; ?>
                        
                        <?php 
                        $start_page = max(1, $page_num - 2);
                        $end_page = min($total_pages, $page_num + 2);
                        
                        for ($i = $start_page; $i <= $end_page; $i++): 
                        ?>
                            <li class="page-item <?= $i === $page_num ? 'active' : '' ?>">
                                <a class="page-link" href="?page=monitoring_peminjaman&p=<?= $i ?><?= !empty($status_filter) ? '&status='.$status_filter : '' ?><?= !empty($search) ? '&search='.urlencode($search) : '' ?><?= !empty($tanggal_dari) ? '&tanggal_dari='.$tanggal_dari : '' ?><?= !empty($tanggal_sampai) ? '&tanggal_sampai='.$tanggal_sampai : '' ?>"><?= $i ?></a>
                            </li>
                        <?php endfor; ?>
                        
                        <?php if ($page_num < $total_pages): ?>
                            <li class="page-item">
                                <a class="page-link" href="?page=monitoring_peminjaman&p=<?= $page_num + 1 ?><?= !empty($status_filter) ? '&status='.$status_filter : '' ?><?= !empty($search) ? '&search='.urlencode($search) : '' ?><?= !empty($tanggal_dari) ? '&tanggal_dari='.$tanggal_dari : '' ?><?= !empty($tanggal_sampai) ? '&tanggal_sampai='.$tanggal_sampai : '' ?>">Next</a>
                            </li>
                        <?php endif; ?>
                    </ul>
                </nav>
            </div>
        <?php endif; ?>
    </div>
</div>

<!-- Detail Modal -->
<div class="modal fade" id="detailModal" tabindex="-1" role="dialog" aria-labelledby="detailModalTitle" aria-hidden="true">
    <div class="modal-dialog modal-xl" role="document">
        <div class="modal-content">
            <div class="modal-header custom-modal-header">
                <h5 class="modal-title" id="detailModalTitle"><i class="fas fa-info-circle mr-2"></i> Detail Peminjaman</h5>
            </div>
            <div class="modal-body" id="detailContent">
                <!-- Content will be loaded here -->
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-outline-secondary" onclick="closeDetailModal()" data-bs-dismiss="modal" data-dismiss="modal">Tutup</button>
            </div>
        </div>
    </div>
</div>

<style>
.stat-card {
    background: white;
    border-radius: 10px;
    padding: 20px;
    margin-bottom: 20px;
    box-shadow: 0 2px 10px rgba(0,0,0,0.1);
    border-left: 4px solid #007bff;
    display: flex;
    align-items: center;
    gap: 15px;
}

.stat-icon {
    width: 60px;
    height: 60px;
    border-radius: 50%;
    display: flex;
    align-items: center;
    justify-content: center;
    color: white;
    font-size: 24px;
}

.stat-content h3 {
    margin: 0;
    font-size: 28px;
    font-weight: bold;
    color: #333;
}

.stat-content p {
    margin: 0;
    color: #666;
    font-size: 14px;
}

.table th {
    border-top: none;
    border-bottom: 2px solid #dee2e6;
    font-weight: 600;
    color: #495057;
}

.badge {
    font-size: 12px;
    padding: 6px 12px;
}

.btn-group .btn {
    margin: 0 2px;
}

@media (max-width: 768px) {
    .stat-card {
        flex-direction: column;
        text-align: center;
    }
    
    .table-responsive {
        font-size: 12px;
    }
    
    .btn-group {
        flex-direction: column;
    }
    
    .btn-group .btn {
        margin: 2px 0;
    }
}

/* Ensure detail modal and its backdrop are above fixed header/sidebar */
.modal-backdrop,
.modal-backdrop.show {
    z-index: 2990 !important;
}

#detailModal.modal {
    z-index: 3000 !important;
}

/* Move the dialog down a bit so header doesn't cover modal header */
#detailModal .modal-dialog {
    margin-top: 220px !important;
}

@media (max-width: 576px) {
    #detailModal .modal-dialog {
        margin-top: 60px !important;
    }
}

/* Polished modal look */
#detailModal .modal-content {
    border: none;
    border-radius: 12px;
    overflow: hidden;
    box-shadow: 0 20px 60px rgba(0,0,0,0.25);
}

.custom-modal-header {
    background: linear-gradient(90deg, #5a67d8 0%, #9f7aea 100%);
    color: #fff;
}
.custom-modal-header .modal-title { font-weight: 600; }
</style>

<script>
function viewDetail(id) {
    // Ensure modal is in body to avoid stacking context issues
    $('#detailModal').appendTo('body');
    $('#detailContent').html('<div class="text-center"><i class="fas fa-spinner fa-spin"></i> Loading...</div>');
    $('#detailModal').modal('show');
    
    fetch(`ajax/get_peminjaman_detail.php?id=${id}`)
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                $('#detailContent').html(data.html);
            } else {
                $('#detailContent').html('<div class="alert alert-danger">Error: ' + data.message + '</div>');
            }
        })
        .catch(error => {
            $('#detailContent').html('<div class="alert alert-danger">Error loading data</div>');
        });
}

function closeDetailModal() {
    const $modal = $('#detailModal');
    // Try Bootstrap/jQuery API first (works for BS4)
    try {
        if ($modal.modal) {
            $modal.modal('hide');
            return;
        }
    } catch (e) { /* fall through */ }
    // Try Bootstrap 5 native API if available
    try {
        const el = document.getElementById('detailModal');
        if (window.bootstrap && el) {
            const instance = bootstrap.Modal.getInstance(el) || new bootstrap.Modal(el);
            instance.hide();
            return;
        }
    } catch (e) { /* fall through */ }
    // Hard fallback if no plugin present
    $modal.removeClass('show').attr('aria-hidden', 'true').hide();
    $('.modal-backdrop').remove();
    $('body').removeClass('modal-open').css('padding-right', '');
}

function markCompleted(id) {
    if (confirm('Tandai peminjaman ini sebagai selesai?')) {
        fetch('ajax/update_peminjaman_status.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
            },
            body: JSON.stringify({
                id: id,
                status: 'completed'
            })
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                location.reload();
            } else {
                alert('Error: ' + data.message);
            }
        })
        .catch(error => {
            alert('Error: ' + error.message);
        });
    }
}

function exportData(format) {
    const url = new URL(window.location);
    url.searchParams.set('export', format);
    window.open(url.toString(), '_blank');
}

// Safety: on DOM ready, keep modal attached to body
$(function(){
    $('#detailModal').appendTo('body');
    // Fallback: wire close buttons to hide modal if data attributes miss
    $(document).on('click', '[data-bs-dismiss="modal"], [data-dismiss="modal"]', function(e){
        e.preventDefault();
        closeDetailModal();
    });
});
</script>
