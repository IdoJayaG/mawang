<?php
if (!is_logged_in()) {
    header('Location: login.php');
    exit();
}
if (!is_admin_like()) {
    header('Location: index.php?page=403');
    exit();
}

$current_user    = get_logged_in_user();
$current_page    = 'monitoring_peminjaman';

// Pagination
$limit = 10;
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

$cols_info = $mysqli->query("SHOW COLUMNS FROM peminjaman_kendaraan")->fetch_all(MYSQLI_ASSOC);
$cols_names = array_column($cols_info, 'Field');
$has_pimpinan_approval = in_array('approval_pimpinan_status', $cols_names, true);

// detect whether peminjaman_kendaraan uses pemohon_id or peminjam_id
$peminjam_col = 'pemohon_id';
$colRes = $mysqli->query("SHOW COLUMNS FROM peminjaman_kendaraan LIKE 'pemohon_id'");
if (!$colRes || $colRes->num_rows === 0) {
    $altRes = $mysqli->query("SHOW COLUMNS FROM peminjaman_kendaraan LIKE 'peminjam_id'");
    if ($altRes && $altRes->num_rows > 0) {
        $peminjam_col = 'peminjam_id';
    }
}

// Surat tugas linkage for display fields (peminjam, pengemudi, status)
$has_surat_link = false;
$st_cols = [];
$join_surat = '';
$join_creator = '';
$join_driver = '';
$join_driver_pk = '';
$peminjam_name_expr = 'u.nama_lengkap';
$peminjam_nrp_expr = 'u.nrp_nip';
$peminjam_pangkat_expr = 'u.pangkat';
$peminjam_jabatan_expr = 'u.jabatan';
$status_expr = 'LOWER(CONVERT(p.status USING utf8mb4))';
$select_status_display = ', p.status AS status_display';
$select_driver = ", '' AS nama_pengemudi";

$tblRes = $mysqli->query("SHOW TABLES LIKE 'surat_tugas'");
$has_surat_tbl = ($tblRes && $tblRes->num_rows > 0);
if ($has_surat_tbl && in_array('surat_tugas_id', $cols_names, true)) {
    $has_surat_link = true;
    $st_cols_res = $mysqli->query("SHOW COLUMNS FROM surat_tugas");
    $st_cols = $st_cols_res ? array_column($st_cols_res->fetch_all(MYSQLI_ASSOC), 'Field') : [];

    $join_surat = ' LEFT JOIN surat_tugas s ON p.surat_tugas_id = s.id';

    $creator_join_col = 's.pengguna_id';
    if (in_array('created_by', $st_cols, true)) {
        $creator_join_col = 's.created_by';
    } elseif (in_array('pembuat_id', $st_cols, true)) {
        $creator_join_col = 's.pembuat_id';
    }
    $join_creator = " LEFT JOIN pengguna pembuat ON {$creator_join_col} = pembuat.id";
    $peminjam_name_expr = 'COALESCE(pembuat.nama_lengkap, u.nama_lengkap)';
    $peminjam_nrp_expr = 'COALESCE(pembuat.nrp_nip, u.nrp_nip)';
    $peminjam_pangkat_expr = 'COALESCE(pembuat.pangkat, u.pangkat)';
    $peminjam_jabatan_expr = 'COALESCE(pembuat.jabatan, u.jabatan)';

    $status_expr = 'LOWER(CONVERT(COALESCE(s.status, p.status) USING utf8mb4))';
    $select_status_display = ", CASE WHEN s.status IS NOT NULL AND s.status <> '' THEN s.status ELSE p.status END AS status_display";
}

// Driver display (from surat_tugas.driver_id or peminjaman_kendaraan fields when available)
$driver_expr_parts = [];
if ($has_surat_link && in_array('driver_id', $st_cols, true)) {
    $join_driver = ' LEFT JOIN pengguna drv ON s.driver_id = drv.id';
    $driver_expr_parts[] = 'drv.nama_lengkap';
}
if (in_array('driver_id', $cols_names, true)) {
    $join_driver_pk = ' LEFT JOIN pengguna drv_pk ON p.driver_id = drv_pk.id';
    $driver_expr_parts[] = 'drv_pk.nama_lengkap';
}
if (in_array('nama_sopir', $cols_names, true)) {
    $driver_expr_parts[] = 'p.nama_sopir';
}
if (in_array('sopir_sendiri', $cols_names, true)) {
    $driver_expr_parts[] = "CASE WHEN COALESCE(p.sopir_sendiri,0) = 1 THEN 'Sopir Sendiri' ELSE NULL END";
}
if (!empty($driver_expr_parts)) {
    $select_driver = ', COALESCE(' . implode(', ', $driver_expr_parts) . ') AS nama_pengemudi';
}

if (!empty($status_filter)) {
    $status_key = strtolower($status_filter);
    $status_map = [
        'pending' => ['pending', 'draft', 'menunggu'],
        'approved' => ['approved', 'disetujui'],
        'rejected' => ['rejected', 'ditolak'],
        'ongoing' => ['ongoing', 'dalam perjalanan'],
        'completed' => ['completed', 'selesai'],
        'cancelled' => ['cancelled', 'dibatalkan']
    ];
    $match_values = $status_map[$status_key] ?? [$status_key];
    $placeholders = implode(',', array_fill(0, count($match_values), '?'));
    $where_conditions[] = "{$status_expr} IN ({$placeholders})";
    $params = array_merge($params, $match_values);
}

if (!empty($search)) {
    $where_conditions[] = "({$peminjam_name_expr} LIKE ? OR {$peminjam_nrp_expr} LIKE ? OR k.no_reg LIKE ? OR k.no_polisi LIKE ? OR k.merk LIKE ? OR p.keperluan LIKE ?)";
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

// Approver column detection for admin display
$approver_col = null;
foreach (['approved_by','approval_by','approver_id','approved_by_id','approver'] as $c) {
    if (in_array($c, $cols_names, true)) { $approver_col = $c; break; }
}
$select_extra = '';
$join_approver = '';
if ($approver_col) {
    $select_extra = ", admin.nama_lengkap as nama_admin_approval";
    $join_approver = " LEFT JOIN pengguna admin ON p.{$approver_col} = admin.id";
}

// Base FROM with dynamic joins
$base_from = " FROM peminjaman_kendaraan p"
    . " JOIN pengguna u ON p." . $peminjam_col . " = u.id"
    . " JOIN kendaraan k ON p.kendaraan_id = k.id"
    . $join_surat
    . $join_creator
    . $join_driver
    . $join_driver_pk
    . $join_approver;

// Get total count
$count_query = "SELECT COUNT(*) as total" . $base_from . " $where_clause";

$count_stmt = $mysqli->prepare($count_query);
if (!empty($params)) {
    $types = str_repeat('s', count($params));
    $count_stmt->bind_param($types, ...$params);
}
$count_stmt->execute();
$total_records = $count_stmt->get_result()->fetch_assoc()['total'];
$total_pages = ceil($total_records / $limit);

// Get records

$query =
    "SELECT p.*, "
    . $peminjam_name_expr . " AS nama_peminjam, "
    . $peminjam_nrp_expr . " AS peminjam_nrp, "
    . $peminjam_pangkat_expr . " AS peminjam_pangkat, "
    . $peminjam_jabatan_expr . " AS peminjam_jabatan, "
    . "k.no_reg, k.no_polisi, k.merk, k.tipe, k.tahun_pembuatan, k.jenis, k.warna"
    . $select_status_display
    . $select_driver
    . $select_extra
    . $base_from
    . " " . $where_clause
    . " ORDER BY p.created_at DESC"
    . " LIMIT ? OFFSET ?";

$stmt = $mysqli->prepare($query);
$params[] = $limit;
$params[] = $offset;
$types = str_repeat('s', count($params) - 2) . 'ii';
$stmt->bind_param($types, ...$params);
$stmt->execute();
$peminjaman_list = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);

// Statistics
$stats_where = "";
$stats_from = "FROM peminjaman_kendaraan p" . $join_surat;
$stats_query = "SELECT 
                COUNT(*) as total,
                SUM(CASE WHEN {$status_expr} IN ('pending','draft','menunggu') THEN 1 ELSE 0 END) as pending,
                SUM(CASE WHEN {$status_expr} IN ('approved','disetujui') THEN 1 ELSE 0 END) as approved,
                SUM(CASE WHEN {$status_expr} IN ('rejected','ditolak') THEN 1 ELSE 0 END) as rejected,
                SUM(CASE WHEN {$status_expr} IN ('ongoing','dalam perjalanan') THEN 1 ELSE 0 END) as ongoing,
                SUM(CASE WHEN {$status_expr} IN ('completed','selesai') THEN 1 ELSE 0 END) as completed,
                SUM(CASE WHEN {$status_expr} IN ('cancelled','dibatalkan') THEN 1 ELSE 0 END) as cancelled
                {$stats_from} {$stats_where}";
$stats_result = $mysqli->query($stats_query);
// Normalize stats to avoid nulls (prevents passing null to number_format())
$stats = $stats_result->fetch_assoc();
if (!is_array($stats)) {
    $stats = [];
}
$defaults = [
    'total' => 0,
    'pending' => 0,
    'approved' => 0,
    'rejected' => 0,
    'ongoing' => 0,
    'completed' => 0,
    'cancelled' => 0,
];
foreach ($defaults as $k => $v) {
    if (!isset($stats[$k]) || $stats[$k] === null || $stats[$k] === '') {
        $stats[$k] = $v;
    } else {
        $stats[$k] = (int)$stats[$k];
    }
}

// --- Export handling (Excel/CSV and PDF) ---
if (!empty($_GET['export'])) {
    $export = strtolower($_GET['export']);

    // Rebuild export query same as $query but without LIMIT/OFFSET
    $export_sql =
        "SELECT p.*, "
        . $peminjam_name_expr . " AS nama_peminjam, "
        . $peminjam_nrp_expr . " AS peminjam_nrp, "
        . $peminjam_pangkat_expr . " AS peminjam_pangkat, "
        . $peminjam_jabatan_expr . " AS peminjam_jabatan, "
        . "k.no_reg, k.no_polisi, k.merk, k.tipe, k.tahun_pembuatan, k.jenis, k.warna"
        . $select_status_display
        . $select_driver
        . $select_extra
        . $base_from
        . " " . $where_clause
        . " ORDER BY p.created_at DESC";

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

    // Excel (XLSX) via PhpSpreadsheet when available, otherwise CSV fallback
    if ($export === 'excel' || $export === 'xlsx' || $export === 'csv') {
        // Prefer XLSX when PhpSpreadsheet is installed
        $composerAutoload = __DIR__ . '/../vendor/autoload.php';
        $canXlsx = false;
        if (file_exists($composerAutoload)) {
            require_once $composerAutoload;
            if (class_exists('\PhpOffice\PhpSpreadsheet\Spreadsheet')) {
                $canXlsx = true;
            }
        }

        if ($canXlsx && $export !== 'csv') {
            // Build XLSX
            $spreadsheet = new \PhpOffice\PhpSpreadsheet\Spreadsheet();
            $sheet = $spreadsheet->getActiveSheet();

            $headers = ['ID','Nama Peminjam','NRP/NIP','Pangkat','Jabatan','Pengemudi','No. Reg','Merk','Tipe','Mulai','Selesai','Keperluan','Status','Approved By','Approved At'];
            $col = 'A';
            foreach ($headers as $h) {
                $sheet->setCellValue($col . '1', $h);
                $sheet->getStyle($col . '1')->getFont()->setBold(true);
                $col++;
            }

            $rowNum = 2;
            foreach ($export_rows as $r) {
                $sheet->setCellValue('A' . $rowNum, $r['id'] ?? '');
                $sheet->setCellValue('B' . $rowNum, $r['nama_peminjam'] ?? '');
                $sheet->setCellValue('C' . $rowNum, $r['peminjam_nrp'] ?? '');
                $sheet->setCellValue('D' . $rowNum, $r['peminjam_pangkat'] ?? '');
                $sheet->setCellValue('E' . $rowNum, $r['peminjam_jabatan'] ?? '');
                $sheet->setCellValue('F' . $rowNum, $r['nama_pengemudi'] ?? '');
                $sheet->setCellValue('G' . $rowNum, $r['no_reg'] ?? '');
                $sheet->setCellValue('H' . $rowNum, $r['merk'] ?? '');
                $sheet->setCellValue('I' . $rowNum, $r['tipe'] ?? '');
                $sheet->setCellValue('J' . $rowNum, !empty($r['tanggal_mulai']) ? date('d/m/Y H:i', strtotime($r['tanggal_mulai'])) : '');
                $sheet->setCellValue('K' . $rowNum, !empty($r['tanggal_selesai']) ? date('d/m/Y H:i', strtotime($r['tanggal_selesai'])) : '');
                $sheet->setCellValue('L' . $rowNum, $r['keperluan'] ?? '');
                $sheet->setCellValue('M' . $rowNum, $r['status_display'] ?? ($r['status'] ?? '')); 
                $sheet->setCellValue('N' . $rowNum, $r['nama_admin_approval'] ?? '');
                $sheet->setCellValue('O' . $rowNum, !empty($r['approved_at']) ? date('d/m/Y H:i', strtotime($r['approved_at'])) : '');
                $rowNum++;
            }

            // Auto-size columns (best effort)
            foreach (range('A', 'O') as $columnID) {
                $sheet->getColumnDimension($columnID)->setAutoSize(true);
            }

            // Send XLSX
            $filename = 'peminjaman_export_' . date('Ymd_His') . '.xlsx';
            while (ob_get_level() > 0) { ob_end_clean(); }
            header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
            header('Content-Disposition: attachment; filename="' . $filename . '"');
            $writer = new \PhpOffice\PhpSpreadsheet\Writer\Xlsx($spreadsheet);
            $writer->save('php://output');
            exit;
        }

        // Fallback: CSV
        $filename = 'peminjaman_export_' . date('Ymd_His') . '.csv';
        while (ob_get_level() > 0) { ob_end_clean(); }
        header('Content-Type: text/csv; charset=UTF-8');
        header('Content-Disposition: attachment; filename="' . $filename . '"');
        // UTF-8 BOM for Excel compatibility
        echo "\xEF\xBB\xBF";
        $out = fopen('php://output', 'w');
        // Header row
        $headers = ['ID','Nama Peminjam','NRP/NIP','Pangkat','Jabatan','Pengemudi','No. Reg','Merk','Tipe','Mulai','Selesai','Keperluan','Status','Approved By','Approved At'];
        fputcsv($out, $headers);
        foreach ($export_rows as $r) {
            $row = [
                $r['id'] ?? '',
                $r['nama_peminjam'] ?? '',
                $r['peminjam_nrp'] ?? '',
                $r['peminjam_pangkat'] ?? '',
                $r['peminjam_jabatan'] ?? '',
                $r['nama_pengemudi'] ?? '',
                $r['no_reg'] ?? '',
                $r['merk'] ?? '',
                $r['tipe'] ?? '',
                !empty($r['tanggal_mulai']) ? date('d/m/Y H:i', strtotime($r['tanggal_mulai'])) : '',
                !empty($r['tanggal_selesai']) ? date('d/m/Y H:i', strtotime($r['tanggal_selesai'])) : '',
                $r['keperluan'] ?? '',
                $r['status_display'] ?? ($r['status'] ?? ''),
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
            $cols = ['ID','Nama Peminjam','NRP/NIP','Pengemudi','No. Reg','Mulai','Selesai','Keperluan','Status','Approved By'];
            foreach ($cols as $c) $html .= '<th>' . htmlspecialchars($c) . '</th>';
            $html .= '</tr></thead><tbody>';
            foreach ($export_rows as $r) {
                $html .= '<tr>';
                $html .= '<td>' . htmlspecialchars($r['id'] ?? '') . '</td>';
                $html .= '<td>' . htmlspecialchars($r['nama_peminjam'] ?? '') . '</td>';
                $html .= '<td>' . htmlspecialchars($r['peminjam_nrp'] ?? '') . '</td>';
                $html .= '<td>' . htmlspecialchars($r['nama_pengemudi'] ?? '') . '</td>';
                $html .= '<td>' . htmlspecialchars($r['no_reg'] ?? '') . '</td>';
                $html .= '<td>' . (!empty($r['tanggal_mulai']) ? htmlspecialchars(date('d/m/Y H:i', strtotime($r['tanggal_mulai']))) : '') . '</td>';
                $html .= '<td>' . (!empty($r['tanggal_selesai']) ? htmlspecialchars(date('d/m/Y H:i', strtotime($r['tanggal_selesai']))) : '') . '</td>';
                $html .= '<td>' . htmlspecialchars($r['keperluan'] ?? '') . '</td>';
                $html .= '<td>' . htmlspecialchars($r['status_display'] ?? ($r['status'] ?? '')) . '</td>';
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
            echo '<thead><tr><th>ID</th><th>Nama</th><th>NRP/NIP</th><th>Pengemudi</th><th>No. Reg</th><th>Mulai</th><th>Selesai</th><th>Keperluan</th><th>Status</th></tr></thead><tbody>';
            foreach ($export_rows as $r) {
                echo '<tr>';
                echo '<td>' . htmlspecialchars($r['id'] ?? '') . '</td>';
                echo '<td>' . htmlspecialchars($r['nama_peminjam'] ?? '') . '</td>';
                echo '<td>' . htmlspecialchars($r['peminjam_nrp'] ?? '') . '</td>';
                echo '<td>' . htmlspecialchars($r['nama_pengemudi'] ?? '') . '</td>';
                echo '<td>' . htmlspecialchars($r['no_reg'] ?? '') . '</td>';
                echo '<td>' . (!empty($r['tanggal_mulai']) ? htmlspecialchars(date('d/m/Y H:i', strtotime($r['tanggal_mulai']))) : '') . '</td>';
                echo '<td>' . (!empty($r['tanggal_selesai']) ? htmlspecialchars(date('d/m/Y H:i', strtotime($r['tanggal_selesai']))) : '') . '</td>';
                echo '<td>' . htmlspecialchars($r['keperluan'] ?? '') . '</td>';
                echo '<td>' . htmlspecialchars($r['status_display'] ?? ($r['status'] ?? '')) . '</td>';
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
        'draft' => ['bg' => '#ffc107', 'text' => '#212529'],
        'menunggu' => ['bg' => '#ffc107', 'text' => '#212529'],
        'approved' => ['bg' => '#17a2b8', 'text' => '#ffffff'], // info
        'disetujui' => ['bg' => '#17a2b8', 'text' => '#ffffff'],
        'rejected' => ['bg' => '#dc3545', 'text' => '#ffffff'], // danger
        'ditolak' => ['bg' => '#dc3545', 'text' => '#ffffff'],
        'ongoing' => ['bg' => '#007bff', 'text' => '#ffffff'], // primary
        'dalam perjalanan' => ['bg' => '#007bff', 'text' => '#ffffff'],
        'completed' => ['bg' => '#28a745', 'text' => '#ffffff'], // success
        'selesai' => ['bg' => '#28a745', 'text' => '#ffffff'],
        'cancelled' => ['bg' => '#6c757d', 'text' => '#ffffff'], // secondary
        'dibatalkan' => ['bg' => '#6c757d', 'text' => '#ffffff']
    ];
    $labels = [
        'pending' => 'Menunggu',
        'draft' => 'Draft',
        'menunggu' => 'Menunggu',
        'approved' => 'Disetujui',
        'disetujui' => 'Disetujui',
        'rejected' => 'Ditolak',
        'ditolak' => 'Ditolak',
        'ongoing' => 'Berlangsung',
        'dalam perjalanan' => 'Dalam Perjalanan',
        'completed' => 'Selesai',
        'selesai' => 'Selesai',
        'cancelled' => 'Dibatalkan',
        'dibatalkan' => 'Dibatalkan'
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

<!-- ── Welcome Header ─────────────────────────────────────────────────────── -->
<div class="gradient-header d-flex align-items-center justify-content-between">
    <div>
        <h5 class="mb-1 fw-bold">
            <i class="fas fa-chart-line me-2 opacity-75"></i>Monitoring Peminjaman Kendaraan
        </h5>
        <p class="mb-0 opacity-75 small">Monitor status dan riwayat seluruh peminjaman</p>
    </div>
    <div class="d-flex gap-2">
        <button type="button" class="btn btn-sm btn-light" onclick="exportData('excel')">
            <i class="fas fa-file-excel me-1"></i>Excel
        </button>
        <button type="button" class="btn btn-sm btn-light" onclick="exportData('pdf')">
            <i class="fas fa-file-pdf me-1"></i>PDF
        </button>
    </div>
</div>

<!-- ── Stat Cards ─────────────────────────────────────────────────────────── -->
<div class="row mb-3">
    <div class="col-lg-2 col-md-4 col-6 mb-3">
        <div class="card bg-primary text-white shadow-sm h-100">
            <div class="card-body d-flex align-items-center gap-3 py-3">
                <div class="bg-white bg-opacity-25 rounded-circle d-flex align-items-center justify-content-center flex-shrink-0" style="width:44px;height:44px;">
                    <i class="fas fa-list fa-lg text-white"></i>
                </div>
                <div>
                    <h4 class="mb-0 fw-bold"><?= number_format($stats['total']) ?></h4>
                    <p class="mb-0 opacity-75 small">Total</p>
                </div>
            </div>
        </div>
    </div>
    <div class="col-lg-2 col-md-4 col-6 mb-3">
        <div class="card bg-warning text-dark shadow-sm h-100">
            <div class="card-body d-flex align-items-center gap-3 py-3">
                <div class="bg-white bg-opacity-25 rounded-circle d-flex align-items-center justify-content-center flex-shrink-0" style="width:44px;height:44px;">
                    <i class="fas fa-clock fa-lg text-dark"></i>
                </div>
                <div>
                    <h4 class="mb-0 fw-bold"><?= number_format($stats['pending']) ?></h4>
                    <p class="mb-0 opacity-75 small">Menunggu</p>
                </div>
            </div>
        </div>
    </div>
    <div class="col-lg-2 col-md-4 col-6 mb-3">
        <div class="card bg-info text-dark shadow-sm h-100">
            <div class="card-body d-flex align-items-center gap-3 py-3">
                <div class="bg-white bg-opacity-25 rounded-circle d-flex align-items-center justify-content-center flex-shrink-0" style="width:44px;height:44px;">
                    <i class="fas fa-check fa-lg text-dark"></i>
                </div>
                <div>
                    <h4 class="mb-0 fw-bold"><?= number_format($stats['approved']) ?></h4>
                    <p class="mb-0 opacity-75 small">Disetujui</p>
                </div>
            </div>
        </div>
    </div>
    <div class="col-lg-2 col-md-4 col-6 mb-3">
        <div class="card bg-primary text-white shadow-sm h-100">
            <div class="card-body d-flex align-items-center gap-3 py-3">
                <div class="bg-white bg-opacity-25 rounded-circle d-flex align-items-center justify-content-center flex-shrink-0" style="width:44px;height:44px;">
                    <i class="fas fa-car fa-lg text-white"></i>
                </div>
                <div>
                    <h4 class="mb-0 fw-bold"><?= number_format($stats['ongoing']) ?></h4>
                    <p class="mb-0 opacity-75 small">Berlangsung</p>
                </div>
            </div>
        </div>
    </div>
    <div class="col-lg-2 col-md-4 col-6 mb-3">
        <div class="card bg-success text-white shadow-sm h-100">
            <div class="card-body d-flex align-items-center gap-3 py-3">
                <div class="bg-white bg-opacity-25 rounded-circle d-flex align-items-center justify-content-center flex-shrink-0" style="width:44px;height:44px;">
                    <i class="fas fa-check-circle fa-lg text-white"></i>
                </div>
                <div>
                    <h4 class="mb-0 fw-bold"><?= number_format($stats['completed']) ?></h4>
                    <p class="mb-0 opacity-75 small">Selesai</p>
                </div>
            </div>
        </div>
    </div>
    <div class="col-lg-2 col-md-4 col-6 mb-3">
        <div class="card bg-danger text-white shadow-sm h-100">
            <div class="card-body d-flex align-items-center gap-3 py-3">
                <div class="bg-white bg-opacity-25 rounded-circle d-flex align-items-center justify-content-center flex-shrink-0" style="width:44px;height:44px;">
                    <i class="fas fa-times-circle fa-lg text-white"></i>
                </div>
                <div>
                    <h4 class="mb-0 fw-bold"><?= number_format($stats['rejected'] + $stats['cancelled']) ?></h4>
                    <p class="mb-0 opacity-75 small">Ditolak/Batal</p>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- ── Pencarian ──────────────────────────────────────────────────────────── -->
<?php $qs = !empty($search) ? '&search=' . urlencode($search) : ''; ?>
<div class="mb-3">
    <form method="GET" action="index.php">
        <input type="hidden" name="page" value="monitoring_peminjaman">
        <div class="input-group shadow-sm">
            <span class="input-group-text bg-white border-end-0">
                <i class="fas fa-search text-muted"></i>
            </span>
            <input type="text" name="search" class="form-control border-start-0 border-end-0"
                   placeholder="Cari nama peminjam, NRP, no. registrasi, merk, atau keperluan..."
                   value="<?= htmlspecialchars($search) ?>">
            <button type="submit" class="btn btn-primary px-4">
                <i class="fas fa-search me-1"></i>
            </button>
            <?php if (!empty($search)): ?>
                <a href="index.php?page=monitoring_peminjaman" class="btn btn-outline-secondary" title="Hapus pencarian">
                    <i class="fas fa-times"></i>
                </a>
            <?php endif; ?>
        </div>
    </form>
    <?php if (!empty($search)): ?>
        <p class="text-muted small mt-1 mb-0">
            Menampilkan hasil untuk "<strong><?= htmlspecialchars($search) ?></strong>"
            — <?= number_format($total_records) ?> data ditemukan
        </p>
    <?php endif; ?>
</div>

<!-- ── Tabel Peminjaman ───────────────────────────────────────────────────── -->
<div class="card shadow-sm">
    <div class="card-header bg-primary text-white d-flex justify-content-between align-items-center">
        <h6 class="mb-0"><i class="fas fa-table me-2"></i>Data Peminjaman
            <span class="badge bg-light text-primary ms-2"><?= number_format($total_records) ?></span>
        </h6>
        <span class="small opacity-75">Hal. <?= $page_num ?> / <?= max(1,$total_pages) ?></span>
    </div>
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table table-striped table-hover mb-0">
                    <thead class="bg-light">
                        <tr>
                            <th width="5%">No</th>
                            <th width="12%">Peminjam</th>
                            <th width="12%">Pengemudi</th>
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
                                <td colspan="9" class="text-center py-4">
                                    <i class="fas fa-inbox fa-3x text-muted mb-3"></i>
                                    <p class="text-muted">Tidak ada data peminjaman ditemukan</p>
                                </td>
                            </tr>
                        <?php else: ?>
                            <?php foreach ($peminjaman_list as $index => $p): ?>
                                <tr>
                                    <td><?= $offset + $index + 1 ?></td>
                                    <td>
                                        <strong><?= htmlspecialchars($p['nama_peminjam'] ?? '') ?></strong><br>
                                        <small class="text-muted">
                                            <?= htmlspecialchars($p['peminjam_pangkat'] ?? '') ?> | <?= htmlspecialchars($p['peminjam_nrp'] ?? '') ?><br>
                                            <?= htmlspecialchars($p['peminjam_jabatan'] ?? '') ?>
                                        </small>
                                    </td>
                                    <td>
                                        <?php
                                        $pengemudi = trim((string)($p['nama_pengemudi'] ?? ''));
                                        ?>
                                        <strong><?= htmlspecialchars($pengemudi !== '' ? $pengemudi : '-') ?></strong>
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
                                    <td><?= getStatusBadge($p['status_display'] ?? $p['status']) ?></td>
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
        <?php
        $record_from = $total_records > 0 ? $offset + 1 : 0;
        $record_to   = min($offset + $limit, $total_records);
        $base_url    = 'index.php?page=monitoring_peminjaman' . $qs;
        ?>
        <div class="card-footer d-flex flex-column flex-sm-row justify-content-between align-items-center gap-2">
            <span class="text-muted small">
                Menampilkan <strong><?= $record_from ?>–<?= $record_to ?></strong>
                dari <strong><?= number_format($total_records) ?></strong> data
            </span>
            <?php if ($total_pages > 1): ?>
            <nav aria-label="Pagination">
                <ul class="pagination pagination-sm mb-0">
                    <!-- Halaman pertama -->
                    <li class="page-item <?= $page_num <= 1 ? 'disabled' : '' ?>">
                        <a class="page-link" href="<?= $base_url ?>&p=1">&laquo;</a>
                    </li>
                    <!-- Sebelumnya -->
                    <li class="page-item <?= $page_num <= 1 ? 'disabled' : '' ?>">
                        <a class="page-link" href="<?= $base_url ?>&p=<?= $page_num - 1 ?>">
                            <i class="fas fa-chevron-left"></i>
                        </a>
                    </li>
                    <!-- Nomor halaman (±2 dari current) -->
                    <?php
                    $pg_start = max(1, $page_num - 2);
                    $pg_end   = min($total_pages, $page_num + 2);
                    if ($pg_start > 1): ?>
                        <li class="page-item disabled"><span class="page-link">…</span></li>
                    <?php endif;
                    for ($i = $pg_start; $i <= $pg_end; $i++): ?>
                        <li class="page-item <?= $i === $page_num ? 'active' : '' ?>">
                            <a class="page-link" href="<?= $base_url ?>&p=<?= $i ?>"><?= $i ?></a>
                        </li>
                    <?php endfor;
                    if ($pg_end < $total_pages): ?>
                        <li class="page-item disabled"><span class="page-link">…</span></li>
                    <?php endif; ?>
                    <!-- Berikutnya -->
                    <li class="page-item <?= $page_num >= $total_pages ? 'disabled' : '' ?>">
                        <a class="page-link" href="<?= $base_url ?>&p=<?= $page_num + 1 ?>">
                            <i class="fas fa-chevron-right"></i>
                        </a>
                    </li>
                    <!-- Halaman terakhir -->
                    <li class="page-item <?= $page_num >= $total_pages ? 'disabled' : '' ?>">
                        <a class="page-link" href="<?= $base_url ?>&p=<?= $total_pages ?>">&raquo;</a>
                    </li>
                </ul>
            </nav>
            <?php endif; ?>
        </div>
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

<script>
let _detailModal = null;
function getDetailModal() {
    if (!_detailModal) {
        _detailModal = new bootstrap.Modal(document.getElementById('detailModal'));
    }
    return _detailModal;
}

function viewDetail(id) {
    document.getElementById('detailContent').innerHTML =
        '<div class="text-center py-4"><i class="fas fa-spinner fa-spin fa-2x text-primary"></i></div>';
    getDetailModal().show();

    fetch(`ajax/get_peminjaman_detail.php?id=${id}`)
        .then(r => r.json())
        .then(data => {
            document.getElementById('detailContent').innerHTML =
                data.success ? data.html : `<div class="alert alert-danger">Error: ${data.message}</div>`;
        })
        .catch(() => {
            document.getElementById('detailContent').innerHTML =
                '<div class="alert alert-danger">Gagal memuat data.</div>';
        });
}

function markCompleted(id) {
    if (!confirm('Tandai peminjaman ini sebagai selesai?')) return;
    fetch('ajax/update_peminjaman_status.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json', 'Accept': 'application/json' },
        body: JSON.stringify({ id, status: 'completed' })
    })
    .then(async r => {
        const text = await r.text();
        try { return JSON.parse(text); } catch (e) { throw new Error(text || 'Bukan JSON'); }
    })
    .then(data => {
        if (data && data.success) { location.reload(); }
        else { alert('Error: ' + (data?.message ?? 'Gagal memperbarui status.')); }
    })
    .catch(e => alert('Error: ' + (e?.message ?? 'Terjadi kesalahan.')));
}

function exportData(format) {
    const url = new URL(window.location);
    url.searchParams.set('export', format);
    window.open(url.toString(), '_blank');
}
</script>
