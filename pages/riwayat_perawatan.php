<?php
require_once 'includes/auth.php';
require_login();

$current_role = get_current_role();
$current_user_id = get_current_user_id();

// Check if user has access
if (!in_array($current_role, ['admin', 'operator', 'driver'], true)) {
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

// Excel export (list or filtered) - only for admin/operator
if ($action === 'export_excel') {
    if (!in_array($current_role, ['admin', 'operator'], true)) {
        $msg = '<div class="alert alert-danger">Anda tidak memiliki akses untuk export Excel.</div>';
    } else {
        // only admin/operator proceed
        $autoload = __DIR__ . '/../vendor/autoload.php';
        if (!file_exists($autoload)) {
            $msg = '<div class="alert alert-danger">Library PhpSpreadsheet tidak ditemukan. Tidak bisa export Excel.</div>';
        } else {
            require_once $autoload;

            // Collect filters from query
            $keyword = trim($_GET['q'] ?? ($_GET['search'] ?? ''));
            $filter_status = $_GET['status'] ?? '';
            $filter_kendaraan = isset($_GET['kendaraan']) ? (int)$_GET['kendaraan'] : 0;
            $filter_tanggal = $_GET['tanggal'] ?? '';
            // Multi-year support (tahun[] or CSV), controlled by 'all' flag
            $tahunParam = $_GET['tahun'] ?? '';
            $tahun_list = [];
            if (is_array($tahunParam)) {
                foreach ($tahunParam as $y) { $y = (int)preg_replace('/[^0-9]/','', (string)$y); if ($y > 0) { $tahun_list[$y] = true; } }
            } elseif (is_string($tahunParam) && trim($tahunParam) !== '') {
                $parts = preg_split('/[\s,;]+/', trim($tahunParam));
                foreach ($parts as $y) { $y = (int)preg_replace('/[^0-9]/','', (string)$y); if ($y > 0) { $tahun_list[$y] = true; } }
            }
            $tahun_multi = array_keys($tahun_list);
            $export_all = isset($_GET['all']) && $_GET['all'] == '1';

            // Build WHERE
            $where = [];$p=[];$t='';
            if ($filter_status) { $where[]='jp.status = ?'; $p[]=$filter_status; $t.='s'; }
            if (!empty($filter_tanggal)) { $where[]='DATE(jp.tanggal_perawatan)=?'; $p[]=$filter_tanggal; $t.='s'; }
            if ($keyword) {
                $where[]='(k.no_reg LIKE ? OR k.merk LIKE ? OR jp.jenis_perawatan LIKE ? OR jp.deskripsi LIKE ?)';
                $q="%$keyword%"; $p=array_merge($p,[$q,$q,$q,$q]); $t.='ssss';
            }
            if ($filter_kendaraan) { $where[]='jp.kendaraan_id = ?'; $p[]=$filter_kendaraan; $t.='i'; }
            if (!$export_all && !empty($tahun_multi)) {
                $ph = implode(',', array_fill(0, count($tahun_multi), '?'));
                $where[] = "YEAR(jp.tanggal_perawatan) IN ($ph)";
                foreach ($tahun_multi as $ty) { $p[] = (int)$ty; $t .= 'i'; }
            }
            $where_sql = $where ? 'WHERE '.implode(' AND ',$where) : '';

            // Query data
            $sql = "SELECT jp.*, k.no_reg, k.no_polisi, k.merk, k.tipe FROM jadwal_perawatan jp JOIN kendaraan k ON jp.kendaraan_id=k.id $where_sql ORDER BY jp.tanggal_perawatan DESC, jp.id DESC";
            $stmt = $mysqli->prepare($sql);
            if ($p) { $stmt->bind_param($t, ...$p); }
            $stmt->execute();
            $rows = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
            $stmt->close();

            // Build spreadsheet
            $spreadsheet = new \PhpOffice\PhpSpreadsheet\Spreadsheet();
            $sheet = $spreadsheet->getActiveSheet();
            $sheet->setTitle('Riwayat Perawatan');
            // Column widths (price columns removed)
            $sheet->getColumnDimension('A')->setWidth(6);
            $sheet->getColumnDimension('B')->setWidth(44);
            $sheet->getColumnDimension('C')->setWidth(16);
            $sheet->getColumnDimension('D')->setWidth(18);
            // Header row (removed Harga/Jumlah columns)
            $sheet->fromArray([[ 'NO', 'NAMA BARANG', 'BANYAKNYA', 'SATUAN' ]], null, 'A1');
            $sheet->getStyle('A1:D1')->getFont()->setBold(true);
            $sheet->getStyle('A1:D1')->getAlignment()->setHorizontal(\PhpOffice\PhpSpreadsheet\Style\Alignment::HORIZONTAL_CENTER);

            // Group by kendaraan label (no_reg > no_polisi > ID)
            $groups = [];
            $groupOrder = [];
        foreach ($rows as $rp) {
                $key = trim((string)($rp['no_reg'] ?? ''));
                if ($key === '') { $key = trim((string)($rp['no_polisi'] ?? '')); }
                if ($key === '') { $key = 'KendaraanID:'.(string)($rp['kendaraan_id'] ?? ''); }
                if (!isset($groups[$key])) {
                    $groups[$key] = [
                        'no_reg' => (string)($rp['no_reg'] ?? ''),
                        'no_polisi' => (string)($rp['no_polisi'] ?? ''),
                        'merk' => (string)($rp['merk'] ?? ''),
                        'tipe' => (string)($rp['tipe'] ?? ''),
                        'items' => []
                    ];
                    $groupOrder[] = $key;
                }
                // Each perawatan row becomes one item; name from jenis_perawatan/deskripsi; price from biaya_aktual/estimasi
                $nama = (string)($rp['jenis_perawatan'] ?? '');
                if ($nama === '') { $nama = (string)($rp['deskripsi'] ?? '-'); }
                // Price references removed for code-only change; zero out
                $harga = 0.0;
                // Preserve null for qty if DB value is NULL; otherwise use numeric value
                $qtyVal = null;
                if (array_key_exists('banyaknya', $rp) && $rp['banyaknya'] !== null) {
                    // DECIMAL comes as string from MySQLi; cast to float for numeric cell
                    $qtyVal = (float)$rp['banyaknya'];
                }

                $groups[$key]['items'][] = [
                    'tanggal' => (string)($rp['tanggal_perawatan'] ?? ''),
                    'nama' => $nama,
                    'qty' => $qtyVal, // keep null as null, don't coerce
                    'satuan' => (string)($rp['satuan'] ?? ''),
                    'harga' => $harga,
                ];
            }

            // When exporting Keseluruhan (all), merge in Riwayat Perbaikan items into the same sheet
            if ($export_all) {
                $where2 = [];$p2=[];$t2='';
                if (!empty($filter_status)) { $where2[]='rp.status = ?'; $p2[]=$filter_status; $t2.='s'; }
                if (!empty($filter_tanggal)) { $where2[]='DATE(rp.tanggal_perbaikan)=?'; $p2[]=$filter_tanggal; $t2.='s'; }
                if (!empty($keyword)) {
                    $where2[]='(k.no_reg LIKE ? OR k.no_polisi LIKE ? OR k.merk LIKE ? OR rp.jenis_perbaikan LIKE ? OR rp.bengkel LIKE ? OR rp.deskripsi LIKE ?)';
                    $q="%$keyword%"; $p2=array_merge($p2,[$q,$q,$q,$q,$q,$q]); $t2.='ssssss';
                }
                if (!empty($filter_kendaraan)) { $where2[]='rp.kendaraan_id = ?'; $p2[]=(int)$filter_kendaraan; $t2.='i'; }
                $where_sql2 = $where2 ? ('WHERE '.implode(' AND ', $where2)) : '';

                $sql2 = "SELECT rp.*, k.no_reg, k.no_polisi, k.merk, k.tipe FROM riwayat_perbaikan rp JOIN kendaraan k ON rp.kendaraan_id=k.id $where_sql2 ORDER BY rp.tanggal_perbaikan DESC, rp.id DESC";
                if ($st2 = $mysqli->prepare($sql2)) {
                    if (!empty($p2)) { $st2->bind_param($t2, ...$p2); }
                    $st2->execute();
                    $rowsRepair = $st2->get_result()->fetch_all(MYSQLI_ASSOC);
                    $st2->close();

                    foreach ($rowsRepair as $rr) {
                        $gkey = trim((string)($rr['no_reg'] ?? ''));
                        if ($gkey === '') { $gkey = trim((string)($rr['no_polisi'] ?? '')); }
                        if ($gkey === '') { $gkey = 'KendaraanID:'.(string)($rr['kendaraan_id'] ?? ''); }
                        if (!isset($groups[$gkey])) {
                            $groups[$gkey] = [
                                'no_reg' => (string)($rr['no_reg'] ?? ''),
                                'no_polisi' => (string)($rr['no_polisi'] ?? ''),
                                'merk' => (string)($rr['merk'] ?? ''),
                                'tipe' => (string)($rr['tipe'] ?? ''),
                                'items' => []
                            ];
                            $groupOrder[] = $gkey;
                        }
                        // Fetch perbaikan items; fallback to single line if none
                        $items = [];
                        if ($sti = $mysqli->prepare('SELECT nama_barang, qty, satuan, harga FROM riwayat_perbaikan_items WHERE perbaikan_id = ? ORDER BY urutan ASC, id ASC')) {
                            $pid = (int)$rr['id'];
                            $sti->bind_param('i', $pid);
                            $sti->execute();
                            $items = $sti->get_result()->fetch_all(MYSQLI_ASSOC);
                            $sti->close();
                        }
                        if (!empty($items)) {
                            foreach ($items as $it) {
                                $qv = null;
                                if (isset($it['qty']) && $it['qty'] !== null && $it['qty'] !== '') {
                                    $qv = (float)$it['qty'];
                                }
                                $groups[$gkey]['items'][] = [
                                    'tanggal' => (string)($rr['tanggal_perbaikan'] ?? ''),
                                    'nama' => (string)($it['nama_barang'] ?? ''),
                                    'qty' => $qv,
                                    'satuan' => (string)($it['satuan'] ?? ''),
                                    'harga' => (float)($it['harga'] ?? 0),
                                ];
                            }
                        } else {
                            $fallbackNama = (string)($rr['catatan'] ?? $rr['deskripsi'] ?? $rr['jenis_perbaikan'] ?? '-');
                            $fallbackHarga = (float)($rr['biaya'] ?? 0);
                            $groups[$gkey]['items'][] = [
                                'tanggal' => (string)($rr['tanggal_perbaikan'] ?? ''),
                                'nama' => $fallbackNama,
                                'qty' => null,
                                'satuan' => '',
                                'harga' => $fallbackHarga,
                            ];
                        }
                    }
                }
            }

            // Render groups
            $r = 2; $groupNo = 1;
            foreach ($groupOrder as $gk) {
                $g = $groups[$gk];
                $titleParts = ['Service'];
                if (!empty($g['merk'])) { $titleParts[] = trim($g['merk'].' '.($g['tipe'] ?? '')); }
                $noRegLabel = (string)($g['no_reg'] !== '' ? $g['no_reg'] : $g['no_polisi']);
                if ($noRegLabel !== '') { $titleParts[] = 'No.Reg ' . $noRegLabel; }
                $title = trim(implode(' ', $titleParts));
                $sheet->setCellValueExplicit('A'.$r, $groupNo++, \PhpOffice\PhpSpreadsheet\Cell\DataType::TYPE_NUMERIC);
                $sheet->mergeCells('B'.$r.':E'.$r);
                $sheet->setCellValue('B'.$r, $title);
                $sheet->getStyle('A'.$r.':E'.$r)->getFont()->setBold(true);
                $sheet->getStyle('A'.$r)->getAlignment()->setHorizontal(\PhpOffice\PhpSpreadsheet\Style\Alignment::HORIZONTAL_CENTER);
                $sheet->getStyle('B'.$r)->getAlignment()->setHorizontal(\PhpOffice\PhpSpreadsheet\Style\Alignment::HORIZONTAL_LEFT);
                $r++;

                foreach ($g['items'] as $it) {
                    // Date in column A (dd/mm/yyyy)
                    $tglStr = trim((string)$it['tanggal']);
                    if ($tglStr !== '') {
                        try {
                            $dt = new \DateTime($tglStr);
                            $dt->setTime(0, 0, 0);
                            $excelDate = \PhpOffice\PhpSpreadsheet\Shared\Date::PHPToExcel($dt);
                            $sheet->setCellValue('A'.$r, $excelDate);
                            $sheet->getStyle('A'.$r)->getNumberFormat()->setFormatCode('dd/mm/yyyy');
                            $sheet->getStyle('A'.$r)->getAlignment()->setHorizontal(\PhpOffice\PhpSpreadsheet\Style\Alignment::HORIZONTAL_CENTER);
                        } catch (\Exception $e) {
                            $sheet->setCellValue('A'.$r, '');
                        }
                    } else {
                        $sheet->setCellValue('A'.$r, '');
                    }
                    $sheet->setCellValue('B'.$r, (string)$it['nama']);
                    // BANYAKNYA: write blank if NULL, numeric if provided
                    if ($it['qty'] === null) {
                        $sheet->setCellValue('C'.$r, '');
                    } else {
                        $sheet->setCellValueExplicit('C'.$r, (float)$it['qty'], \PhpOffice\PhpSpreadsheet\Cell\DataType::TYPE_NUMERIC);
                    }
                    // Write satuan instead of price/amount
                    $sheet->setCellValue('D'.$r, (string)($it['satuan'] ?? ''));
                    $r++;
                }
                // Separator row
                $r++;
            }
            // Borders and number formats
            $lastDataRow = max(2, $r - 1);
            $sheet->getStyle('A1:D'.$lastDataRow)->getBorders()->getAllBorders()->setBorderStyle(\PhpOffice\PhpSpreadsheet\Style\Border::BORDER_THIN);
            // Qty supports decimals (no thousands grouping to avoid stray commas)
            $sheet->getStyle('C2:C'.$lastDataRow)->getNumberFormat()->setFormatCode('#,##0');
            $sheet->getStyle('A2:A'.$lastDataRow)->getAlignment()->setHorizontal(\PhpOffice\PhpSpreadsheet\Style\Alignment::HORIZONTAL_CENTER);

            // Activity log
            if (function_exists('log_activity')) {
                $rowsCount = is_array($rows) ? count($rows) : 0;
                $ctx = [];
                if (!$export_all && !empty($tahun_multi)) { $ctx[] = 'Tahun ' . implode(',', $tahun_multi); }
                if (!empty($filter_status)) { $ctx[] = 'Status ' . $filter_status; }
                if (!empty($filter_tanggal)) { $ctx[] = 'Tanggal ' . $filter_tanggal; }
                if (!empty($keyword)) { $ctx[] = 'Keyword "' . $keyword . '"'; }
                if (!empty($filter_kendaraan)) { $ctx[] = 'Kendaraan ID ' . (int)$filter_kendaraan; }
                $contextStr = !empty($ctx) ? (' [' . implode('; ', $ctx) . ']') : '';
                log_activity('EXPORT_RIWAYAT_PERAWATAN', 'Export Riwayat Perawatan ' . $rowsCount . ' baris' . $contextStr);
            }

            // Output
            if (function_exists('ini_get') && function_exists('ini_set') && ini_get('zlib.output_compression')) { @ini_set('zlib.output_compression', 'Off'); }
            if (function_exists('ob_get_level')) { while (ob_get_level() > 0) { @ob_end_clean(); } }
            $writer = \PhpOffice\PhpSpreadsheet\IOFactory::createWriter($spreadsheet, 'Xlsx');
            header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
            header('Content-Disposition: attachment; filename="riwayat_perawatan.xlsx"');
            header('Cache-Control: max-age=0');
            header('Pragma: public');
            header('Expires: 0');
            $writer->save('php://output');
            exit;
        }
    }
}

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
                // map form field `tanggal_perawatan` to DB column `tanggal_perawatan`
                $tanggal_perawatan = $_POST['tanggal_perawatan'] ?? ($_POST['jadwal_tanggal'] ?? null);
                $prioritas = $_POST['prioritas'];
                $teknisi_id = !empty($_POST['teknisi_id']) ? (int)$_POST['teknisi_id'] : null;
                
                // insert into actual column name `tanggal_perawatan` (estimasi_biaya removed)
                $stmt = $mysqli->prepare("INSERT INTO jadwal_perawatan (kendaraan_id, jenis_perawatan, deskripsi, tanggal_perawatan, prioritas, teknisi_id, status, created_by) VALUES (?, ?, ?, ?, ?, ?, 'Terjadwal', ?)");
                $stmt->bind_param('issssii', $kendaraan_id, $jenis_perawatan, $deskripsi, $tanggal_perawatan, $prioritas, $teknisi_id, $current_user_id);
                
                if ($stmt->execute()) {
                    // Log aktivitas penambahan jadwal (menggunakan log_activity)
                    $logMsg = "Menambah jadwal perawatan: $jenis_perawatan untuk kendaraan ID $kendaraan_id";
                    if (!empty($tanggal_perawatan)) { $logMsg .= " pada $tanggal_perawatan"; }
                    log_activity('ADD_JADWAL_PERAWATAN', $logMsg);
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
                    // Log aktivitas pembaruan status jadwal (menggunakan log_activity)
                    $logMsg = "Memperbarui status jadwal perawatan ID: $id menjadi $status";
                    if (!empty($tanggal_perawatan)) { $logMsg .= " pada $tanggal_perawatan"; }
                    // biaya_aktual intentionally not logged or persisted
                    log_activity('UPDATE_JADWAL_STATUS', $logMsg);
                    // If status set to Selesai, show SweetAlert and redirect to riwayat (history)
                    if ($status === 'Selesai') {
                        $_SESSION['swal'] = [
                            'icon' => 'success',
                            'title' => 'Perawatan Selesai',
                            'text' => 'Jadwal perawatan telah ditandai sebagai selesai dan dipindahkan ke Riwayat.'
                        ];
                        header('Location: index.php?page=riwayat_perawatan');
                        exit;
                    }

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
// Split filters into conditions that reference jadwal_perawatan (jp) and those that reference kendaraan (k)
$where_jp = [];
$params_jp = [];
$types_jp = '';

$where_k = [];
$params_k = [];
$types_k = '';

// Default status is now 'Semua Status' (no filter applied)

if ($filter_status) {
    $where_jp[] = "jp.status = ?";
    $params_jp[] = $filter_status;
    $types_jp .= 's';
}

if ($filter_kendaraan) {
    $where_jp[] = "jp.kendaraan_id = ?";
    $params_jp[] = (int)$filter_kendaraan;
    $types_jp .= 'i';
}

if ($current_role === 'driver') {
    if (!empty($accessible_vehicle_ids)) {
        $placeholders = implode(',', array_fill(0, count($accessible_vehicle_ids), '?'));
        $where_jp[] = "jp.kendaraan_id IN ($placeholders)";
        foreach ($accessible_vehicle_ids as $vid) {
            $params_jp[] = (int)$vid;
            $types_jp .= 'i';
        }
    } else {
        $where_jp[] = '1=0';
    }
}

if ($filter_tanggal) {
    // filter by tanggal_perawatan (column in DB)
    $where_jp[] = "DATE(jp.tanggal_perawatan) = ?";
    $params_jp[] = $filter_tanggal;
    $types_jp .= 's';
}

if ($search) {
    // search term: apply jenis_perawatan and deskripsi to jp, and no_polisi to kendaraan
    $search_term = "%$search%";
    $where_jp[] = "(jp.jenis_perawatan LIKE ? OR jp.deskripsi LIKE ?)";
    $params_jp[] = $search_term;
    $params_jp[] = $search_term;
    $types_jp .= 'ss';

    $where_k[] = "k.no_reg LIKE ?";
    $params_k[] = $search_term;
    $types_k .= 's';
}

$where_jp_sql = !empty($where_jp) ? ' AND ' . implode(' AND ', $where_jp) : '';
$where_k_sql = !empty($where_k) ? ' AND ' . implode(' AND ', $where_k) : '';

// Pagination
$page = max(1, (int)($_GET['page_num'] ?? 1));
$limit = 10;
$offset = ($page - 1) * $limit;

// Sorting
$sort = strtolower(trim($_GET['sort'] ?? ''));
$dir = strtolower(trim($_GET['dir'] ?? 'desc'));
$dir = in_array($dir, ['asc', 'desc'], true) ? $dir : 'desc';

// Get perawatan data: select the latest jadwal_perawatan id per kendaraan using jp-only filters,
// then fetch those records and apply any kendaraan-level filters (e.g., no_polisi search).

$inner_sql = "SELECT MAX(jp1.id) as id FROM jadwal_perawatan jp1 WHERE 1=1 " . $where_jp_sql . " GROUP BY jp1.kendaraan_id";

// ORDER BY whitelist mapping
$sort_map = [
    'kendaraan' => 'k.no_reg',
    'jenis' => 'jp.jenis_perawatan',
    'jadwal' => 'jp.tanggal_perawatan',
    'status' => 'status_display',
    'prioritas' => 'jp.prioritas',
    'teknisi' => 'teknisi_nama',
    'total' => 'agg.total_perawatan'
];
$order_by = 'jp.tanggal_perawatan DESC';
if (isset($sort_map[$sort])) {
    $order_by = $sort_map[$sort] . ' ' . strtoupper($dir);
}

$sql = "SELECT jp.*, k.no_reg, k.merk, k.tipe, t.nama_lengkap as teknisi_nama,
           CASE
               WHEN jp.status = 'Terjadwal' AND jp.tanggal_perawatan < CURDATE() THEN 'Terlambat'
               ELSE jp.status
           END as status_display,
           0 AS biaya_sort,
           COALESCE(agg.total_perawatan, 0) AS total_perawatan
    FROM jadwal_perawatan jp
    JOIN kendaraan k ON jp.kendaraan_id = k.id
    LEFT JOIN pengguna t ON jp.teknisi_id = t.id
    LEFT JOIN (
        SELECT kendaraan_id, COUNT(*) AS total_perawatan
        FROM jadwal_perawatan
        GROUP BY kendaraan_id
    ) agg ON agg.kendaraan_id = jp.kendaraan_id
    WHERE jp.id IN (" . $inner_sql . ")" . $where_k_sql . "
    ORDER BY $order_by
    LIMIT ? OFFSET ?";

$stmt = $mysqli->prepare($sql);

// Bind parameters: first jp-only params (for inner), then kendaraan params (outer), then limit/offset
$bind_params = array_merge($params_jp, $params_k, [$limit, $offset]);
$bind_types = $types_jp . $types_k . 'ii';

if (!empty($bind_params)) {
    $stmt->bind_param($bind_types, ...$bind_params);
}

$stmt->execute();
$perawatan_result = $stmt->get_result();
$stmt->close();

// If viewing details for a kendaraan, load full history for that kendaraan
$vehicle_history = [];
if ($action === 'view' && !empty($_GET['id'])) {
    $kendaraan_view_id = (int)$_GET['id'];
    $hist_stmt = $mysqli->prepare("SELECT jp.*, p.nama_lengkap as teknisi_nama, k.no_reg, k.merk, k.tipe FROM jadwal_perawatan jp LEFT JOIN pengguna p ON jp.teknisi_id = p.id LEFT JOIN kendaraan k ON jp.kendaraan_id = k.id WHERE jp.kendaraan_id = ? ORDER BY jp.tanggal_perawatan DESC");
    $hist_stmt->bind_param('i', $kendaraan_view_id);
    $hist_stmt->execute();
    $vehicle_history = $hist_stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    $hist_stmt->close();
}

// Get total count for pagination
$count_sql = "
    SELECT COUNT(DISTINCT jp.kendaraan_id) as total
    FROM jadwal_perawatan jp
    JOIN kendaraan k ON jp.kendaraan_id = k.id
    WHERE jp.id IN (SELECT MAX(jp1.id) FROM jadwal_perawatan jp1 WHERE 1=1 " . $where_jp_sql . " GROUP BY jp1.kendaraan_id) " . $where_k_sql . "
";

$count_stmt = $mysqli->prepare($count_sql);
$count_bind_params = array_merge($params_jp, $params_k);
$count_bind_types = $types_jp . $types_k;
if (!empty($count_bind_params)) {
    $count_stmt->bind_param($count_bind_types, ...$count_bind_params);
}
$count_stmt->execute();
$total_records = $count_stmt->get_result()->fetch_assoc()['total'];
$count_stmt->close();

$total_pages = ceil($total_records / $limit);

// Get statistics
$stats_sql = "
    SELECT 
        COUNT(*) as total,
        SUM(CASE WHEN status = 'Terjadwal' THEN 1 ELSE 0 END) as terjadwal,
        SUM(CASE WHEN status = 'Dalam Proses' THEN 1 ELSE 0 END) as proses,
        SUM(CASE WHEN status = 'Selesai' THEN 1 ELSE 0 END) as selesai,
        SUM(CASE WHEN status = 'Terjadwal' AND tanggal_perawatan < CURDATE() THEN 1 ELSE 0 END) as terlambat
    FROM jadwal_perawatan";
if ($current_role === 'driver') {
    if (!empty($accessible_vehicle_ids)) {
        $stats_sql .= " WHERE kendaraan_id IN (" . implode(',', array_map('intval', $accessible_vehicle_ids)) . ")";
    } else {
        $stats_sql .= " WHERE 1=0";
    }
}
$statsResult = $mysqli->query($stats_sql);
$stats = $statsResult ? $statsResult->fetch_assoc() : ['total'=>0,'terjadwal'=>0,'proses'=>0,'selesai'=>0,'terlambat'=>0];

// Get vehicle list for filters
$kendaraan_sql = "SELECT id, no_reg, merk, tipe FROM kendaraan";
if ($current_role === 'driver') {
    if (!empty($accessible_vehicle_ids)) {
        $kendaraan_sql .= " WHERE id IN (" . implode(',', array_map('intval', $accessible_vehicle_ids)) . ")";
    } else {
        $kendaraan_sql .= " WHERE 1=0";
    }
}
$kendaraan_sql .= " ORDER BY no_reg";
$kendaraan_list = $mysqli->query($kendaraan_sql)->fetch_all(MYSQLI_ASSOC);

// Get teknisi list for forms (pengguna stores nama_lengkap)
$teknisi_list = $mysqli->query("SELECT id, nama_lengkap FROM pengguna ORDER BY nama_lengkap")->fetch_all(MYSQLI_ASSOC);

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

// Build sortable header link
function sort_link($label, $key, $currentSort, $currentDir) {
    $params = $_GET;
    $isActive = ($currentSort === $key);
    $nextDir = ($isActive && strtolower($currentDir) === 'asc') ? 'desc' : 'asc';
    $params['sort'] = $key;
    $params['dir'] = $nextDir;
    // preserve page
    $params['page'] = 'riwayat_perawatan';
    $query = http_build_query($params);
    $icon = '';
    if ($isActive) {
        $icon = strtolower($currentDir) === 'asc' ? ' <i class="fas fa-sort-up"></i>' : ' <i class="fas fa-sort-down"></i>';
    } else {
        $icon = ' <i class="fas fa-sort text-muted"></i>';
    }
    return '<a href="?' . htmlspecialchars($query) . '" class="text-decoration-none">' . htmlspecialchars($label) . $icon . '</a>';
}
?>

<div class="page-header">
            <h1><i class="fas fa-tools me-2"></i>Riwayat Perawatan Kendaraan</h1>
            <?php if ($current_role !== 'driver'): ?>
            <div class="header-actions">
            <a class="btn btn-light btn-lg" href="index.php?page=jadwal_perawatan&action=add" >
                <i class="fas fa-plus me-1"></i> Tambah Jadwal
            </a>
            <button class="btn btn-success" data-bs-toggle="modal" data-bs-target="#exportPerawatanModal"><i class="fas fa-file-excel"></i> Export Excel</button>
            </div>
            <?php endif; ?>
</div>

<?= $msg ?>

<!-- Statistics Cards -->
<?php if ($current_role !== 'driver'): ?>
<div class="row row-cols-2 row-cols-md-3 row-cols-lg-5 g-3 mb-4">
    <div class="col">
        <div class="card text-center h-100">
            <div class="card-body">
                <i class="fas fa-list-alt text-primary fa-2x mb-2"></i>
                <h4 class="card-title"><?= $stats['total'] ?></h4>
                <p class="card-text text-muted">Total</p>
            </div>
        </div>
    </div>
    <div class="col">
        <div class="card text-center h-100">
            <div class="card-body">
                <i class="fas fa-clock text-warning fa-2x mb-2"></i>
                <h4 class="card-title"><?= $stats['terjadwal'] ?></h4>
                <p class="card-text text-muted">Terjadwal</p>
            </div>
        </div>
    </div>
    <div class="col">
        <div class="card text-center h-100">
            <div class="card-body">
                <i class="fas fa-cog text-info fa-2x mb-2"></i>
                <h4 class="card-title"><?= $stats['proses'] ?></h4>
                <p class="card-text text-muted">Dalam Proses</p>
            </div>
        </div>
    </div>
    <div class="col">
        <div class="card text-center h-100">
            <div class="card-body">
                <i class="fas fa-check-circle text-success fa-2x mb-2"></i>
                <h4 class="card-title"><?= $stats['selesai'] ?></h4>
                <p class="card-text text-muted">Selesai</p>
            </div>
        </div>
    </div>
    <div class="col">
        <div class="card text-center h-100">
            <div class="card-body">
                <i class="fas fa-exclamation-triangle text-danger fa-2x mb-2"></i>
                <h4 class="card-title"><?= $stats['terlambat'] ?></h4>
                <p class="card-text text-muted">Terlambat</p>
            </div>
        </div>
    </div>
</div>
<?php endif; ?>

<!-- Filters -->
<?php if ($current_role !== 'driver'): ?>
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
                            <?= htmlspecialchars($kendaraan['no_reg'] . ' - ' . $kendaraan['merk'] . ' ' . $kendaraan['tipe']) ?>
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
                <input type="text" id="filterSearch" name="search" class="form-control" placeholder="Cari jenis perawatan..." value="<?= htmlspecialchars($search) ?>">
            </div>
            
            <div class="col-md-1">
                <label class="form-label">&nbsp;</label>
                <button type="submit" class="btn btn-primary">
                    <i class="fas fa-search"></i>
                </button>
            </div>
        </form>
    </div>
</div>
<?php endif; ?>

<!-- Perawatan List -->
<div class="card">
    <div class="card-header">
        <h5 class="mb-0">Daftar Perawatan</h5>
    </div>
    <div class="card-body p-0">
        <?php if ($action === 'view' && !empty($vehicle_history)): ?>
            <?php
            // Build back URL with current filters (remove action & id)
            $backParams = $_GET;
            unset($backParams['action'], $backParams['id']);
            $backParams['page'] = 'riwayat_perawatan';
            $backUrl = '?' . http_build_query($backParams);
            ?>
            <div class="d-flex justify-content-between align-items-center px-3 py-2 border-bottom">
                <a href="<?= htmlspecialchars($backUrl) ?>" class="btn btn-outline-secondary">
                    <i class="fas fa-arrow-left me-1"></i> Kembali
                </a>
            </div>
            <div class="table-responsive">
                <table class="table table-hover mb-0" id="perawatanTable">
                    <thead class="table-light">
                        <tr>
                            <th>Tanggal</th>
                            <th>Jenis Perawatan</th>
                            <th>Deskripsi</th>
                            <th>Status</th>
                            <th>Teknisi</th>
                            <th>Catatan</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($vehicle_history as $hist): ?>
                            <tr>
                                <td><?= date('d/m/Y', strtotime($hist['tanggal_perawatan'])) ?></td>
                                <td><?= htmlspecialchars($hist['jenis_perawatan']) ?></td>
                                <td><?= nl2br(htmlspecialchars(substr($hist['deskripsi'], 0, 100))) ?></td>
                                <td><span class="badge bg-<?= getStatusBadge($hist['status']) ?>"><?= htmlspecialchars($hist['status']) ?></span></td>
                                <td><?= htmlspecialchars($hist['teknisi_nama'] ?? $hist['nama_lengkap'] ?? '-') ?></td>
                                <td>
                                    <?= nl2br(htmlspecialchars($hist['keterangan'] ?? '-')) ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php elseif ($perawatan_result->num_rows > 0): ?>
            <div class="table-responsive">
                <table class="table table-hover mb-0" id="perawatanTable">
                    <thead class="table-light">
                        <tr>
                            <th><?= sort_link('Kendaraan','kendaraan', $sort, $dir) ?></th>
                            <th><?= sort_link('Jenis Perawatan','jenis', $sort, $dir) ?></th>
                            <th><?= sort_link('Jadwal','jadwal', $sort, $dir) ?></th>
                            <th><?= sort_link('Status','status', $sort, $dir) ?></th>
                            <th><?= sort_link('Prioritas','prioritas', $sort, $dir) ?></th>
                            <th><?= sort_link('Teknisi','teknisi', $sort, $dir) ?></th>
                            <th>Info</th>
                            <th><?= sort_link('Total Perawatan','total', $sort, $dir) ?></th>
                            <th>Aksi</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php while ($row = $perawatan_result->fetch_assoc()): ?>
                            <tr>
                                <td>
                                    <strong><?= htmlspecialchars($row['no_reg']) ?></strong>
                                    <br><small class="text-muted"><?= htmlspecialchars($row['merk'] . ' ' . $row['tipe']) ?></small>
                                </td>
                                <td>
                                    <strong><?= htmlspecialchars($row['jenis_perawatan']) ?></strong>
                                    <?php if ($row['deskripsi']): ?>
                                        <br><small class="text-muted"><?= htmlspecialchars(substr($row['deskripsi'], 0, 50)) ?>...</small>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <?= date('d/m/Y', strtotime($row['tanggal_perawatan'])) ?>
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
                                <td>-</td>
                                <td>
                                    <?= (int)($row['total_perawatan'] ?? 0) ?>
                                </td>
                                <td>
                                    <div class="btn-group btn-group-sm">
                                        <button class="btn btn-outline-primary" onclick="viewDetail(<?= $row['kendaraan_id'] ?>)" title="Lihat Riwayat">
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
                        <a class="page-link" href="?page=riwayat_perawatan&page_num=<?= $i ?>&status=<?= urlencode($filter_status) ?>&kendaraan=<?= urlencode($filter_kendaraan) ?>&tanggal=<?= urlencode($filter_tanggal) ?>&search=<?= urlencode($search) ?>&sort=<?= urlencode($sort) ?>&dir=<?= urlencode($dir) ?>">
                            <?= $i ?>
                        </a>
                    </li>
                <?php endfor; ?>
            </ul>
        </nav>
    </div>
<?php endif; ?>

<!-- Export Perawatan Modal -->
<?php if ($action === 'list' || $action === '' || !isset($_GET['action'])): ?>
<div class="modal fade" id="exportPerawatanModal" tabindex="-1" aria-labelledby="exportPerawatanLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <form method="get" action="">
                <div class="modal-header">
                    <h5 class="modal-title" id="exportPerawatanLabel"><i class="fas fa-file-excel me-1"></i> Export Riwayat Perawatan</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <input type="hidden" name="page" value="riwayat_perawatan" />
                    <input type="hidden" name="action" value="export_excel" />
                    <?php if (!empty($search)): ?>
                        <input type="hidden" name="q" value="<?= htmlspecialchars($search) ?>" />
                    <?php endif; ?>
                    <?php if (!empty($filter_kendaraan)): ?>
                        <input type="hidden" name="kendaraan" value="<?= (int)$filter_kendaraan ?>" />
                    <?php endif; ?>
                    <?php if (!empty($filter_status)): ?>
                        <input type="hidden" name="status" value="<?= htmlspecialchars($filter_status) ?>" />
                    <?php endif; ?>
                    <?php if (!empty($filter_tanggal)): ?>
                        <input type="hidden" name="tanggal" value="<?= htmlspecialchars($filter_tanggal) ?>" />
                    <?php endif; ?>

                    <div class="mb-3">
                        <label class="form-label d-block">Rentang Data</label>
                        <div class="form-check form-check-inline">
                            <input class="form-check-input" type="radio" name="scope" id="scopeTahunPw" value="tahun" checked>
                            <label class="form-check-label" for="scopeTahunPw">Per Tahun</label>
                        </div>
                        <div class="form-check form-check-inline">
                            <input class="form-check-input" type="radio" name="scope" id="scopeAllPw" value="all">
                            <label class="form-check-label" for="scopeAllPw">Keseluruhan</label>
                        </div>
                    </div>

                    <?php
                        // Build available years from jadwal_perawatan
                        $available_years_pw = [];
                        if ($ys = $mysqli->prepare("SELECT DISTINCT YEAR(tanggal_perawatan) AS y FROM jadwal_perawatan WHERE tanggal_perawatan IS NOT NULL ORDER BY y DESC")) {
                            $ys->execute();
                            $res = $ys->get_result();
                            while ($row = $res->fetch_assoc()) { if (!empty($row['y'])) { $available_years_pw[] = (int)$row['y']; } }
                            $ys->close();
                        }
                        if (empty($available_years_pw)) { $available_years_pw = [ (int)date('Y') ]; }
                        // Selected years
                        $selected_years_pw = [];
                        $tahunParamPw = $_GET['tahun'] ?? null;
                        if (is_array($tahunParamPw)) {
                            foreach ($tahunParamPw as $y) { $y = (int)preg_replace('/[^0-9]/','', (string)$y); if ($y > 0) { $selected_years_pw[$y] = true; } }
                        } elseif (is_string($tahunParamPw) && trim($tahunParamPw) !== '') {
                            $parts = preg_split('/[\s,;]+/', trim($tahunParamPw));
                            foreach ($parts as $y) { $y = (int)preg_replace('/[^0-9]/','', (string)$y); if ($y > 0) { $selected_years_pw[$y] = true; } }
                        }
                        if (empty($selected_years_pw)) { $selected_years_pw[(int)date('Y')] = true; }
                    ?>
                    <div class="mb-2" id="tahunGroupPw">
                        <label for="tahunExportPw" class="form-label">Pilih Tahun</label>
                        <select multiple class="form-select" id="tahunExportPw" name="tahun[]">
                            <?php foreach ($available_years_pw as $y): ?>
                                <option value="<?= (int)$y ?>" <?= isset($selected_years_pw[$y]) ? 'selected' : '' ?>><?= (int)$y ?></option>
                            <?php endforeach; ?>
                        </select>
                        <div class="form-text">Pilih satu atau lebih tahun. Pilih "Keseluruhan" untuk semua tahun.</div>
                    </div>
                </div>
                <div class="modal-footer">
                    <input type="hidden" name="all" id="allFlagPw" value="0" />
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Batal</button>
                    <button type="submit" class="btn btn-primary"><i class="fas fa-download me-1"></i> Export</button>
                </div>
            </form>
        </div>
    </div>
</div>
<script>
(function(){
    // Ensure modal is appended to body to avoid z-index/stacking issues inside containers
    const modalEl = document.getElementById('exportPerawatanModal');
    if (modalEl) {
        modalEl.addEventListener('show.bs.modal', function(){
            if (modalEl.parentNode !== document.body) { document.body.appendChild(modalEl); }
        });
    }
    const scopeTahun = document.getElementById('scopeTahunPw');
    const scopeAll = document.getElementById('scopeAllPw');
    const tahunGroup = document.getElementById('tahunGroupPw');
    const allFlag = document.getElementById('allFlagPw');
    function sync(){ const all = scopeAll && scopeAll.checked; if(tahunGroup) tahunGroup.style.display = all ? 'none' : ''; if(allFlag) allFlag.value = all ? '1' : '0'; }
    scopeTahun && scopeTahun.addEventListener('change', sync);
    scopeAll && scopeAll.addEventListener('change', sync);
    sync();
})();
</script>
<style>
#exportPerawatanModal { z-index: 2100; }
#exportPerawatanModal .modal-dialog { z-index: 2110; }
.modal-backdrop.show { z-index: 2050; }
</style>
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
                                        <?= htmlspecialchars($kendaraan['no_reg'] . ' - ' . $kendaraan['merk'] . ' ' . $kendaraan['tipe']) ?>
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
                            <input type="date" name="tanggal_perawatan" class="form-control" required>
                        </div>
                        
                        <!-- Estimasi Biaya input removed -->
                        
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
                                        <?= htmlspecialchars($teknisi['nama_lengkap']) ?>
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
                    
                    <!-- Biaya Aktual input removed -->
                    
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

<style>
.gradient-header {
    background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
}

.page-header h1 {
    font-size: 2rem;
    font-weight: 600;
    margin-bottom: 0.5rem;
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
    .page-header h1 {
        font-size: 1.5rem;
    }
    
    .col-md-1, .col-md-2, .col-md-3, .col-md-4 {
        margin-bottom: 1rem;
    }
}
</style>

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
    
    if (status === 'Selesai') {
        tanggalGroup.style.display = 'block';
        tanggalGroup.querySelector('input').required = true;
    } else {
        tanggalGroup.style.display = 'none';
        tanggalGroup.querySelector('input').required = false;
    }
}

function viewDetail(id) {
    // Open the Riwayat Perawatan page showing full history for the selected kendaraan
    window.location.href = '?page=riwayat_perawatan&action=view&id=' + id;
}
</script>

<script>
// Live search for Daftar Perawatan (client-side)
(function(){
    function debounce(fn, wait){
        var t; return function(){
            clearTimeout(t); var args=arguments; t=setTimeout(function(){ fn.apply(null,args); }, wait);
        };
    }
    document.addEventListener('DOMContentLoaded', function(){
        var input = document.getElementById('liveSearchPerawatan');
        var input2 = document.getElementById('filterSearch');
        var table = document.getElementById('perawatanTable');
        if(!(input || input2) || !table) return;
        var tbody = table.tBodies[0]; if(!tbody) return;
        var rows = Array.prototype.slice.call(tbody.querySelectorAll('tr'));
        // Sync initial values between inputs
        if(input && input2){
            if(input2.value && !input.value){ input.value = input2.value; }
            if(input.value && !input2.value){ input2.value = input.value; }
        }
        // Add a no-results row if not present
        var nores = tbody.querySelector('tr.no-results');
        if(!nores){
            nores = document.createElement('tr');
            nores.className = 'no-results d-none';
            var td = document.createElement('td');
            // compute colspan based on table header/cells
            var headerRow = table.tHead && table.tHead.rows.length ? table.tHead.rows[0] : null;
            var colCount = headerRow ? headerRow.cells.length : (rows.length && rows[0].cells ? rows[0].cells.length : 1);
            td.colSpan = colCount;
            td.className = 'text-center text-muted py-3';
            td.textContent = 'Tidak ada hasil';
            nores.appendChild(td);
            tbody.appendChild(nores);
        }

        var filter = debounce(function(){
            var q1 = input ? String(input.value || '') : '';
            var q2 = input2 ? String(input2.value || '') : '';
            var q = String(q1 || q2).toLowerCase().trim();
            var visible = 0;
            rows.forEach(function(r){
                if(r.classList.contains('no-results')) return;
                var hay = (r.textContent || '').toLowerCase();
                var match = (q === '') || (hay.indexOf(q) !== -1);
                r.classList.toggle('d-none', !match);
                if(match) visible++;
            });
            nores.classList.toggle('d-none', visible !== 0);
        }, 120);

        // attach to available inputs and keep them in sync
        function bind(el, other){
            if(!el) return;
            el.addEventListener('input', function(){
                if(other && other.value !== el.value){ other.value = el.value; }
                filter();
            });
            el.addEventListener('keydown', function(e){
                if(e.key === 'Escape'){
                    el.value = '';
                    if(other){ other.value = ''; }
                    filter();
                }
            });
        }
        bind(input, input2);
        bind(input2, input);

        // run initial filter if any preset
        filter();
    });
})();
</script>
