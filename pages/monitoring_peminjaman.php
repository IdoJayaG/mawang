<?php
if (!is_logged_in()) {
    header('Location: login.php');
    exit();
}
if (!is_admin_like()) {
    header('Location: index.php?page=403');
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

if ($has_pimpinan_approval) {
    $where_conditions[] = "LOWER(CONVERT(p.approval_pimpinan_status USING utf8mb4)) = 'approved'";
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
$stats_where = $has_pimpinan_approval ? "WHERE LOWER(CONVERT(p.approval_pimpinan_status USING utf8mb4)) = 'approved'" : "";
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
                'Accept': 'application/json'
            },
            body: JSON.stringify({
                id: id,
                status: 'completed'
            })
        })
        .then(async response => {
            const text = await response.text();
            try {
                return JSON.parse(text);
            } catch (e) {
                throw new Error(text || 'Response bukan JSON');
            }
        })
        .then(data => {
            if (data && data.success) {
                location.reload();
            } else {
                const msg = data && data.message ? data.message : 'Gagal memperbarui status.';
                alert('Error: ' + msg);
            }
        })
        .catch(error => {
            const raw = (error && error.message) ? String(error.message) : 'Terjadi kesalahan.';
            alert('Error: ' + raw);
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
