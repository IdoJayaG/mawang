<?php
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../lib/table_helpers.php';
require_login();

$current_role = get_current_role();
$current_user_id = get_current_user_id();

// Role-based access control
if ($current_role === 'guest') {
    header('Location: index.php?page=kendaraan_publik');
    exit;
}

$can_crud = can_operate(); // operator dan admin
$can_view = is_logged_in();

$action = $_GET['action'] ?? 'list';
$perbaikan_id = $_GET['id'] ?? null;
$kendaraan_id = $_GET['kendaraan_id'] ?? null;
$msg = '';

// Excel export (list or filtered) - generates one sheet with header then rows per repair.
if ($action === 'export_excel') {
    if (!$can_crud) { header('Location: index.php?page=403'); exit; }
    $autoload = __DIR__ . '/../vendor/autoload.php';
    if (!file_exists($autoload)) {
        $msg = '<div class="alert alert-danger">Library PhpSpreadsheet tidak ditemukan. Tidak bisa export Excel.</div>';
    } else {
        require_once $autoload;
        $singleId = isset($_GET['id']) ? (int)$_GET['id'] : 0;
        if ($singleId > 0) {
            // Export single repair with itemized layout
            $stmt = $conn->prepare("SELECT rp.*, k.no_reg, k.no_polisi, k.merk, k.tipe FROM riwayat_perbaikan rp LEFT JOIN kendaraan k ON rp.kendaraan_id=k.id WHERE rp.id = ? LIMIT 1");
            $stmt->bind_param('i', $singleId);
            $stmt->execute();
            $rp = $stmt->get_result()->fetch_assoc();
            $stmt->close();
            if (!$rp) { header('Location: index.php?page=riwayat_perbaikan'); exit; }
            $its = $conn->prepare('SELECT nama_barang, qty, satuan, harga FROM riwayat_perbaikan_items WHERE perbaikan_id = ? ORDER BY urutan ASC, id ASC');
            $items = [];
            if ($its) { $its->bind_param('i', $singleId); $its->execute(); $items = $its->get_result()->fetch_all(MYSQLI_ASSOC); $its->close(); }

            $spreadsheet = new \PhpOffice\PhpSpreadsheet\Spreadsheet();
            $sheet = $spreadsheet->getActiveSheet();
            $sheet->setTitle('Perbaikan');
            // Service header like: SERVICE BUS AL MATSUS PUSINFOLAHTA TNI
            $sheet->mergeCells('A1:E1');
            $sheet->setCellValue('A1','SERVICE KENDARAAN - '.strtoupper((string)($rp['no_reg'] ?? $rp['no_polisi'] ?? '')));
            $sheet->getStyle('A1')->getFont()->setBold(true)->setSize(14);
            $sheet->getStyle('A1')->getAlignment()->setHorizontal(\PhpOffice\PhpSpreadsheet\Style\Alignment::HORIZONTAL_CENTER);
            // Info row
            $sheet->mergeCells('A2:E2');
            $info = 'Tanggal: '.(!empty($rp['tanggal_perbaikan'])?date('d/m/Y', strtotime($rp['tanggal_perbaikan'])):'-').' | Jenis: '.($rp['jenis_perbaikan']??'-').' | Bengkel: '.($rp['bengkel']??'-');
            $sheet->setCellValue('A2',$info);
            $sheet->getStyle('A2')->getAlignment()->setHorizontal(\PhpOffice\PhpSpreadsheet\Style\Alignment::HORIZONTAL_CENTER);

            // Widths
            $sheet->getColumnDimension('A')->setWidth(12);
            $sheet->getColumnDimension('B')->setWidth(40);
            $sheet->getColumnDimension('C')->setWidth(16);
            $sheet->getColumnDimension('D')->setWidth(18);
            $sheet->getColumnDimension('E')->setWidth(18);

            // Table header as requested
            $sheet->fromArray([['NO','NAMA BARANG','BANYAKNYA','HARGA','JUMLAH']], NULL, 'A4');
            // Subheader numeric row under the header to match the provided sample (1..5)
            $sheet->fromArray([[1,2,3,4,5]], NULL, 'A5');
            $sheet->getStyle('A4:E4')->getFont()->setBold(true);
            $sheet->getStyle('A4:E5')->getAlignment()->setHorizontal(\PhpOffice\PhpSpreadsheet\Style\Alignment::HORIZONTAL_CENTER);

            $r = 6; $no = 1; $grand = 0.0;
            if (!empty($items)) {
                foreach ($items as $it) {
                    $qtyLabel = (string)($it['qty'] ?? 0);
                    if (!empty($it['satuan'])) { $qtyLabel .= ' '. $it['satuan']; }
                    $jumlah = ((float)$it['qty']) * ((float)$it['harga']);
                    $grand += $jumlah;
                    $sheet->setCellValueExplicit('A'.$r, $no++, \PhpOffice\PhpSpreadsheet\Cell\DataType::TYPE_NUMERIC);
                    $sheet->setCellValue('B'.$r, (string)$it['nama_barang']);
                    $sheet->setCellValue('C'.$r, $qtyLabel);
                    $sheet->setCellValue('D'.$r, (float)$it['harga']);
                    $sheet->setCellValue('E'.$r, $jumlah);
                    $r++;
                }
            } else {
                // Fallback single row using rp.catatan/spare_parts with total biaya
                $sheet->setCellValueExplicit('A'.$r, 1, \PhpOffice\PhpSpreadsheet\Cell\DataType::TYPE_NUMERIC);
                $sheet->setCellValue('B'.$r, (string)($rp['catatan'] ?? $rp['deskripsi'] ?? ''));
                $sheet->setCellValue('C'.$r, '-');
                $sheet->setCellValue('D'.$r, (float)($rp['biaya'] ?? 0));
                $sheet->setCellValue('E'.$r, (float)($rp['biaya'] ?? 0));
                $grand = (float)($rp['biaya'] ?? 0);
                $r++;
            }

            // Borders and number formats
            $sheet->getStyle('A4:E'.($r-1))->getBorders()->getAllBorders()->setBorderStyle(\PhpOffice\PhpSpreadsheet\Style\Border::BORDER_THIN);
            $sheet->getStyle('D6:E'.($r-1))->getNumberFormat()->setFormatCode('#,##0');
            $sheet->getStyle('A6:A'.($r-1))->getAlignment()->setHorizontal(\PhpOffice\PhpSpreadsheet\Style\Alignment::HORIZONTAL_CENTER);

            // Grand total row
            $sheet->setCellValue('D'.$r, 'TOTAL');
            $sheet->setCellValue('E'.$r, $grand);
            $sheet->getStyle('A'.$r.':E'.$r)->getFont()->setBold(true);
            $sheet->getStyle('E'.$r)->getNumberFormat()->setFormatCode('#,##0');

            // Activity log: export single repair
            if (function_exists('log_activity')) {
                $vehLabel = (string)($rp['no_reg'] ?? ($rp['no_polisi'] ?? ''));
                $vehText = $vehLabel !== '' ? ('No.Reg ' . $vehLabel) : ('ID ' . (int)($rp['kendaraan_id'] ?? 0));
                $tgl = !empty($rp['tanggal_perbaikan']) ? (string)$rp['tanggal_perbaikan'] : '';
                $jenis = (string)($rp['jenis_perbaikan'] ?? '');
                $la = "Export Riwayat Perbaikan (single) ID $singleId untuk kendaraan $vehText" . ($jenis!==''?" ($jenis)":"") . ($tgl!==''?" pada $tgl":"");
                log_activity('EXPORT_RIWAYAT_PERBAIKAN', $la);
            }

            // Ensure clean binary output
            if (function_exists('ini_get') && function_exists('ini_set') && ini_get('zlib.output_compression')) {
                @ini_set('zlib.output_compression', 'Off');
            }
            if (function_exists('ob_get_level')) {
                while (ob_get_level() > 0) { @ob_end_clean(); }
            }
            $writer = \PhpOffice\PhpSpreadsheet\IOFactory::createWriter($spreadsheet, 'Xlsx');
            $fname = 'perbaikan_'.preg_replace('/[^a-zA-Z0-9_-]+/','', (string)($rp['no_reg'] ?? $rp['no_polisi'] ?? 'kendaraan')).'.xlsx';
            header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
            header('Content-Disposition: attachment; filename="'.$fname.'"');
            header('Cache-Control: max-age=0');
            header('Pragma: public');
            header('Expires: 0');
            $writer->save('php://output');
            exit;
        } else {
            // List export with grouped layout matching the provided image
            // Filters (year/all preserved)
            $keyword = trim($_GET['q'] ?? ($_GET['search'] ?? ''));
            $filter_status = $_GET['status'] ?? '';
            // Accept both `kendaraan` and `kendaraan_id` for vehicle-specific export/filter
            $kendaraan_id = isset($_GET['kendaraan']) ? (int)$_GET['kendaraan'] : (isset($_GET['kendaraan_id']) ? (int)$_GET['kendaraan_id'] : null);
            $filter_tanggal = $_GET['tanggal'] ?? '';
            // Support multiple years: tahun can be array or comma-separated string
            $tahunParam = $_GET['tahun'] ?? '';
            $tahun_list = [];
            if (is_array($tahunParam)) {
                foreach ($tahunParam as $y) {
                    $y = (int)preg_replace('/[^0-9]/','', (string)$y);
                    if ($y > 0) { $tahun_list[$y] = true; }
                }
            } else if (is_string($tahunParam) && trim($tahunParam) !== '') {
                $parts = preg_split('/[\s,;]+/', trim($tahunParam));
                foreach ($parts as $y) {
                    $y = (int)preg_replace('/[^0-9]/','', (string)$y);
                    if ($y > 0) { $tahun_list[$y] = true; }
                }
            }
            $tahun_multi = array_keys($tahun_list);
            $export_all = isset($_GET['all']) && $_GET['all'] == '1';

            // If explicitly exporting for a specific kendaraan_id, include all statuses and all years by default
            $isVehicleExport = isset($_GET['kendaraan_id']) && (int)$_GET['kendaraan_id'] > 0;
            if ($isVehicleExport) {
                $filter_status = ''; // no status restriction -> semua perbaikan
                $export_all = true;  // ignore year filter
                $filter_tanggal = ''; // ignore specific date filter
                $keyword = ''; // ignore keyword filter
            }

            $where = [];$p=[];$t='';
            if ($filter_status) { $where[]='rp.status = ?'; $p[]=$filter_status; $t.='s'; }
            if (!empty($filter_tanggal)) { $where[]='DATE(rp.tanggal_perbaikan)=?'; $p[]=$filter_tanggal; $t.='s'; }
            if ($keyword) {
                $where[]='(k.no_reg LIKE ? OR k.no_polisi LIKE ? OR k.merk LIKE ? OR rp.jenis_perbaikan LIKE ? OR rp.bengkel LIKE ?)';
                $q="%$keyword%"; $p=array_merge($p,[$q,$q,$q,$q,$q]); $t.='sssss';
            }
            if ($kendaraan_id) { $where[]='rp.kendaraan_id = ?'; $p[]=$kendaraan_id; $t.='i'; }
            if (!$export_all && !empty($tahun_multi)) {
                // YEAR IN (?, ?, ...)
                $ph = implode(',', array_fill(0, count($tahun_multi), '?'));
                $where[] = "YEAR(rp.tanggal_perbaikan) IN ($ph)";
                foreach ($tahun_multi as $ty) { $p[] = (int)$ty; $t .= 'i'; }
            }
            $where_sql = $where ? 'WHERE '.implode(' AND ',$where) : '';

            $sql = "SELECT rp.*, k.no_reg, k.no_polisi, k.merk, k.tipe FROM riwayat_perbaikan rp LEFT JOIN kendaraan k ON rp.kendaraan_id=k.id $where_sql ORDER BY rp.tanggal_perbaikan DESC, rp.id DESC";
            $stmt = $conn->prepare($sql);
            if ($p) { $stmt->bind_param($t, ...$p); }
            $stmt->execute();
            $rows = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
            $stmt->close();

            $spreadsheet = new \PhpOffice\PhpSpreadsheet\Spreadsheet();
            $sheet = $spreadsheet->getActiveSheet();
            $sheet->setTitle('Riwayat Perbaikan');

            // Column widths similar to sample
            $sheet->getColumnDimension('A')->setWidth(6);
            $sheet->getColumnDimension('B')->setWidth(44);
            $sheet->getColumnDimension('C')->setWidth(16);
            $sheet->getColumnDimension('D')->setWidth(18);
            $sheet->getColumnDimension('E')->setWidth(18);

            // Header row like in the image
            $sheet->fromArray([[ 'NO', 'NAMA BARANG', 'BANYAKNYA', 'HARGA', 'JUMLAH' ]], null, 'A1');
            $sheet->getStyle('A1:E1')->getFont()->setBold(true);
            $sheet->getStyle('A1:E1')->getAlignment()->setHorizontal(\PhpOffice\PhpSpreadsheet\Style\Alignment::HORIZONTAL_CENTER);

            // Group repairs by no_reg (fallback to no_polisi) so each kendaraan shows once
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

                // Fetch items for this repair
                $its = $conn->prepare('SELECT nama_barang, qty, satuan, harga FROM riwayat_perbaikan_items WHERE perbaikan_id = ? ORDER BY urutan ASC, id ASC');
                $items = [];
                if ($its) { $its->bind_param('i', $rp['id']); $its->execute(); $items = $its->get_result()->fetch_all(MYSQLI_ASSOC); $its->close(); }
                if (!empty($items)) {
                    foreach ($items as $it) {
                        $groups[$key]['items'][] = [
                            'tanggal' => (string)($rp['tanggal_perbaikan'] ?? ''),
                            'nama' => (string)($it['nama_barang'] ?? ''),
                            'qty' => (float)($it['qty'] ?? 0),
                            'satuan' => (string)($it['satuan'] ?? ''),
                            'harga' => (float)($it['harga'] ?? 0),
                        ];
                    }
                } else {
                    // No items: fallback to one line from description/catatan with biaya
                    $groups[$key]['items'][] = [
                        'tanggal' => (string)($rp['tanggal_perbaikan'] ?? ''),
                        'nama' => (string)($rp['catatan'] ?? $rp['deskripsi'] ?? $rp['jenis_perbaikan'] ?? '-'),
                        'qty' => 0,
                        'satuan' => '',
                        'harga' => (float)($rp['biaya'] ?? 0),
                    ];
                }
            }

            // Render groups with single header per kendaraan, then all items; put date in column A for items
            $r = 2; $groupNo = 1; $grand = 0.0;
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
                    // Column A: tanggal per item (dd/mm/yyyy) — force date-only (no time)
                    $tglStr = trim((string)$it['tanggal']);
                    if ($tglStr !== '') {
                        try {
                            $dt = new \DateTime($tglStr);
                            // Normalize to midnight to guarantee no time component
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

                    $qtyLabel = '';
                    if (!empty($it['qty'])) { $qtyLabel = (string)$it['qty']; }
                    if (!empty($it['satuan'])) { $qtyLabel = trim(($qtyLabel !== '' ? $qtyLabel.' ' : '').(string)$it['satuan']); }
                    $jumlah = ((float)$it['qty']) * ((float)$it['harga']);
                    $grand += (float)$jumlah;
                    $sheet->setCellValue('B'.$r, (string)$it['nama']);
                    $sheet->setCellValue('C'.$r, $qtyLabel !== '' ? $qtyLabel : '-');
                    $sheet->setCellValue('D'.$r, (float)$it['harga']);
                    $sheet->setCellValue('E'.$r, (float)$jumlah);
                    $r++;
                }

                // Separator row
                $r++;
            }

            // Apply borders for all used rows (skip trailing extra blank if no groups)
            $lastDataRow = max(2, $r - 1);
            $sheet->getStyle('A1:E'.$lastDataRow)->getBorders()->getAllBorders()->setBorderStyle(\PhpOffice\PhpSpreadsheet\Style\Border::BORDER_THIN);
            // Number formats
            $sheet->getStyle('D2:E'.$lastDataRow)->getNumberFormat()->setFormatCode('#,##0');
            $sheet->getStyle('A2:A'.$lastDataRow)->getAlignment()->setHorizontal(\PhpOffice\PhpSpreadsheet\Style\Alignment::HORIZONTAL_CENTER);

            // Grand total row
            $sheet->setCellValue('D'.$r, 'Total');
            $sheet->setCellValue('E'.$r, $grand);
            $sheet->getStyle('A'.$r.':E'.$r)->getFont()->setBold(true);
            $sheet->getStyle('E'.$r)->getNumberFormat()->setFormatCode('#,##0');
            $sheet->getStyle('A'.$r.':E'.$r)->getBorders()->getAllBorders()->setBorderStyle(\PhpOffice\PhpSpreadsheet\Style\Border::BORDER_THIN);

            // Activity log: export list
            if (function_exists('log_activity')) {
                $rowsCount = is_array($rows) ? count($rows) : 0;
                $ctx = [];
                if ($isVehicleExport) {
                    if (!empty($rows)) {
                        $vehLabel = (string)($rows[0]['no_reg'] ?? ($rows[0]['no_polisi'] ?? ''));
                        if ($vehLabel !== '') { $ctx[] = 'Kendaraan No.Reg ' . $vehLabel; }
                        else if (!empty($kendaraan_id)) { $ctx[] = 'Kendaraan ID ' . (int)$kendaraan_id; }
                    } else if (!empty($kendaraan_id)) {
                        $ctx[] = 'Kendaraan ID ' . (int)$kendaraan_id;
                    }
                } else {
                    if (!$export_all && !empty($tahun_multi)) { $ctx[] = 'Tahun ' . implode(',', $tahun_multi); }
                    if (!empty($filter_status)) { $ctx[] = 'Status ' . $filter_status; }
                    if (!empty($filter_tanggal)) { $ctx[] = 'Tanggal ' . $filter_tanggal; }
                    if (!empty($keyword)) { $ctx[] = 'Keyword "' . $keyword . '"'; }
                }
                $contextStr = !empty($ctx) ? (' [' . implode('; ', $ctx) . ']') : '';
                $la = 'Export Riwayat Perbaikan ' . $rowsCount . ' baris' . $contextStr;
                log_activity('EXPORT_RIWAYAT_PERBAIKAN', $la);
            }

            // Ensure clean binary output
            if (function_exists('ini_get') && function_exists('ini_set') && ini_get('zlib.output_compression')) {
                @ini_set('zlib.output_compression', 'Off');
            }
            if (function_exists('ob_get_level')) {
                while (ob_get_level() > 0) { @ob_end_clean(); }
            }
            $writer = \PhpOffice\PhpSpreadsheet\IOFactory::createWriter($spreadsheet, 'Xlsx');
            header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
            // If exporting a specific kendaraan_id, personalize the filename with no_reg/no_polisi
            if ($isVehicleExport && !empty($rows)) {
                $vehLabel = (string)($rows[0]['no_reg'] ?? ($rows[0]['no_polisi'] ?? 'kendaraan'));
                $vehLabel = $vehLabel !== '' ? $vehLabel : 'kendaraan_' . (int)$kendaraan_id;
                $fname = 'riwayat_perbaikan_' . preg_replace('/[^a-zA-Z0-9_-]+/','', $vehLabel) . '.xlsx';
            } else {
                $fname = 'riwayat_perbaikan.xlsx';
            }
            header('Content-Disposition: attachment; filename="' . $fname . '"');
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
    if ($action === 'add' && $can_crud) {
            $kendaraan_id = (int)$_POST['kendaraan_id'];
            // Optional pengguna/teknisi selection (maps to string column `teknisi`)
            $teknisi = null;
            $teknisi_user_id = isset($_POST['teknisi_user_id']) && $_POST['teknisi_user_id'] !== '' ? (int)$_POST['teknisi_user_id'] : null;
            if ($teknisi_user_id) {
                if ($stp = $conn->prepare('SELECT nama_lengkap FROM pengguna WHERE id = ?')) {
                    $stp->bind_param('i', $teknisi_user_id);
                    $stp->execute();
                    $teknisi_row = $stp->get_result()->fetch_assoc();
                    $stp->close();
                    if ($teknisi_row && isset($teknisi_row['nama_lengkap'])) {
                        $teknisi = (string)$teknisi_row['nama_lengkap'];
                    }
                }
            }
            
            // Validate FK constraints
            if (!validate_kendaraan_exists($conn, $kendaraan_id)) {
                $msg = '<div class="alert alert-danger">Kendaraan tidak ditemukan!</div>';
            } else {
                $tanggal_perbaikan = $_POST['tanggal_perbaikan'];
                $jenis_perbaikan = trim($_POST['jenis_perbaikan']);
                $nama_barang_utama = trim($_POST['nama_barang_utama'] ?? '');
                $deskripsi_kerusakan = trim($_POST['deskripsi_kerusakan']);
                // DB column is named `deskripsi` (see schema). Keep form field as deskripsi_kerusakan
                // and map it to $deskripsi when inserting/updating.
                $deskripsi = $deskripsi_kerusakan;
                $deskripsi_perbaikan = trim($_POST['deskripsi_perbaikan']);
                // Hilangkan input biaya/km/bengkel; biaya dihitung dari total item
                $km_perbaikan = null; $bengkel = null;
                // Normalize incoming status to match DB enum
                $status_input = trim($_POST['status'] ?? '');
                $status_lc = strtolower($status_input);
                if (in_array($status_lc, ['dalam proses','dalam progress'], true)) {
                    $status_db = 'Dalam Proses';
                } elseif ($status_lc === 'menunggu sparepart') {
                    $status_db = 'Menunggu Sparepart';
                } elseif ($status_lc === 'ditunda') {
                    $status_db = 'Ditunda';
                } else {
                    // default to Dalam Proses; only exact 'selesai' maps to Selesai
                    $status_db = ($status_lc === 'selesai') ? 'Selesai' : 'Dalam Proses';
                }
                // Map form fields ke DB columns
                // deskripsi -> deskripsi; deskripsi_perbaikan -> catatan
                $spare_parts = ''; // keterangan dihilangkan
                $catatan = $deskripsi_perbaikan; // map form field ke catatan

                // Kumpulkan item dan hitung total biaya
                $item_nama = $_POST['item_nama'] ?? [];
                $item_qty = $_POST['item_qty'] ?? [];
                $item_satuan = $_POST['item_satuan'] ?? [];
                $item_harga = $_POST['item_harga'] ?? [];
                $biaya = 0.0;
                if (is_array($item_nama)) {
                    foreach ($item_nama as $i => $nm) {
                        $qty = isset($item_qty[$i]) ? (float)$item_qty[$i] : 0;
                        $harga_item = isset($item_harga[$i]) ? (float)$item_harga[$i] : 0;
                        $biaya += $qty * $harga_item;
                    }
                }
                $stmt = $conn->prepare("INSERT INTO riwayat_perbaikan (kendaraan_id, tanggal_perbaikan, jenis_perbaikan, deskripsi, bengkel, biaya, teknisi, status, km_perbaikan, spare_parts, catatan, created_by) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
                // Types: i = kendaraan_id, s = tanggal_perbaikan, s = jenis_perbaikan, s = deskripsi, s = bengkel, d = biaya, s = teknisi, s = status, i = km_saat_perbaikan, s = spare_parts, s = catatan, i = created_by
                $stmt->bind_param('issssdssissi', $kendaraan_id, $tanggal_perbaikan, $jenis_perbaikan, $deskripsi, $bengkel, $biaya, $teknisi, $status_db, $km_perbaikan, $spare_parts, $catatan, $current_user_id);

                if ($stmt->execute()) {
                    $new_id = $stmt->insert_id;
                    // Insert only non-empty rows
                    if (!empty($item_nama) && is_array($item_nama)) {
                        $insItem = $conn->prepare("INSERT INTO riwayat_perbaikan_items (perbaikan_id, nama_barang, qty, satuan, harga, urutan) VALUES (?,?,?,?,?,?)");
                        if ($insItem) {
                            foreach ($item_nama as $i => $nm) {
                                $nm = trim((string)$nm);
                                if ($nm === '' && $i === 0 && $nama_barang_utama !== '') { $nm = $nama_barang_utama; }
                                if ($nm === '') continue;
                                $qty = isset($item_qty[$i]) ? (float)$item_qty[$i] : 0;
                                $sat = isset($item_satuan[$i]) ? trim((string)$item_satuan[$i]) : null;
                                $harga_item = isset($item_harga[$i]) ? (float)$item_harga[$i] : 0;
                                $urut = (int)$i + 1;
                                // types: i (perbaikan_id), s(nama), d(qty), s(satuan), d(harga), i(urutan)
                                $insItem->bind_param('isdsdi', $new_id, $nm, $qty, $sat, $harga_item, $urut);
                                $insItem->execute();
                            }
                            $insItem->close();
                        }
                    }
                    // Sync kendaraan status based on repair status
                    $normalized_status = strtolower($status_db);
                    $in_progress_statuses = ['dalam proses', 'dalam progress', 'menunggu sparepart', 'ditunda'];
                    $status_kendaraan = null;
                    $status_peminjaman = null;
                    if (in_array($normalized_status, $in_progress_statuses, true)) {
                        $status_kendaraan = 'Perbaikan';
                        $status_peminjaman = 'Maintenance';
                    } elseif ($normalized_status === 'selesai') {
                        $status_kendaraan = 'Operasional';
                        $status_peminjaman = 'Tersedia';
                    }
                    if ($status_kendaraan !== null) {
                        if ($upd = $conn->prepare("UPDATE kendaraan SET status_kendaraan = ?, status_peminjaman = ? WHERE id = ?")) {
                            $upd->bind_param('ssi', $status_kendaraan, $status_peminjaman, $kendaraan_id);
                            $upd->execute();
                            $upd->close();
                        }
                    }

                    // Activity log (prefer No.Reg label)
                    {
                        $vehLabel = '';
                        if ($stVeh = $conn->prepare("SELECT COALESCE(NULLIF(TRIM(no_reg),''), NULLIF(TRIM(no_polisi),'')) AS label FROM kendaraan WHERE id = ?")) {
                            $stVeh->bind_param('i', $kendaraan_id);
                            $stVeh->execute();
                            $vehLabel = (string)($stVeh->get_result()->fetch_assoc()['label'] ?? '');
                            $stVeh->close();
                        }
                        $vehText = $vehLabel !== '' ? ('No.Reg ' . $vehLabel) : ('ID ' . $kendaraan_id);
                        $msgLog = "Tambah riwayat perbaikan $jenis_perbaikan untuk kendaraan $vehText";
                        if (function_exists('log_activity')) {
                            log_activity('ADD_RIWAYAT_PERBAIKAN', $msgLog);
                        } else {
                            log_user_activity($msgLog);
                        }
                    }
                    header('Location: index.php?page=riwayat_perbaikan');
                    exit();
                } else {
                    if (strpos($stmt->error, 'foreign key constraint') !== false) {
                        $msg = '<div class="alert alert-danger">Error: Data kendaraan tidak valid atau telah dihapus!</div>';
                    } else {
                        $msg = '<div class="alert alert-danger">Error: ' . $stmt->error . '</div>';
                    }
                }
                $stmt->close();
            }
            
    } elseif ($action === 'edit' && $can_crud && $perbaikan_id) {
            $kendaraan_id = (int)$_POST['kendaraan_id'];
            // Optional pengguna/teknisi selection (maps to string column `teknisi`)
            $teknisi = null;
            $teknisi_user_id = isset($_POST['teknisi_user_id']) && $_POST['teknisi_user_id'] !== '' ? (int)$_POST['teknisi_user_id'] : null;
            if ($teknisi_user_id) {
                if ($stp = $conn->prepare('SELECT nama_lengkap FROM pengguna WHERE id = ?')) {
                    $stp->bind_param('i', $teknisi_user_id);
                    $stp->execute();
                    $teknisi_row = $stp->get_result()->fetch_assoc();
                    $stp->close();
                    if ($teknisi_row && isset($teknisi_row['nama_lengkap'])) {
                        $teknisi = (string)$teknisi_row['nama_lengkap'];
                    }
                }
            }
            
            // Validate FK constraints
            if (!validate_kendaraan_exists($conn, $kendaraan_id)) {
                $msg = '<div class="alert alert-danger">Kendaraan tidak ditemukan!</div>';
            } else {
                $tanggal_perbaikan = $_POST['tanggal_perbaikan'];
                $jenis_perbaikan = trim($_POST['jenis_perbaikan']);
                $nama_barang_utama = trim($_POST['nama_barang_utama'] ?? '');
                $deskripsi_kerusakan = trim($_POST['deskripsi_kerusakan']);
                // Map form field into DB column name
                $deskripsi = $deskripsi_kerusakan;
                $deskripsi_perbaikan = trim($_POST['deskripsi_perbaikan']);
                // Hilangkan input biaya/km/bengkel; biaya dihitung dari total item
                $km_perbaikan = null; $bengkel = null;
                // Normalize incoming status to match DB enum
                $status_input = trim($_POST['status'] ?? '');
                $status_lc = strtolower($status_input);
                if (in_array($status_lc, ['dalam proses','dalam progress'], true)) {
                    $status_db = 'Dalam Proses';
                } elseif ($status_lc === 'menunggu sparepart') {
                    $status_db = 'Menunggu Sparepart';
                } elseif ($status_lc === 'ditunda') {
                    $status_db = 'Ditunda';
                } else {
                    $status_db = ($status_lc === 'selesai') ? 'Selesai' : 'Dalam Proses';
                }
                $spare_parts = '';
                $catatan = $deskripsi_perbaikan;
                // Hitung total biaya dari items
                $item_nama = $_POST['item_nama'] ?? [];
                $item_qty = $_POST['item_qty'] ?? [];
                $item_satuan = $_POST['item_satuan'] ?? [];
                $item_harga = $_POST['item_harga'] ?? [];
                $biaya = 0.0;
                if (is_array($item_nama)) {
                    foreach ($item_nama as $i => $nm) {
                        $qty = isset($item_qty[$i]) ? (float)$item_qty[$i] : 0;
                        $harga_item = isset($item_harga[$i]) ? (float)$item_harga[$i] : 0;
                        $biaya += $qty * $harga_item;
                    }
                }
                // Map to DB columns: km_saat_perbaikan, spare_parts, catatan
                $stmt = $conn->prepare("UPDATE riwayat_perbaikan SET kendaraan_id=?, tanggal_perbaikan=?, jenis_perbaikan=?, deskripsi=?, bengkel=?, biaya=?, teknisi=?, status=?, km_perbaikan=?, spare_parts=?, catatan=?, updated_by=? WHERE id=?");
                // Types: i, s, s, s, s, d, s, s, i, s, s, i, i
                $stmt->bind_param('issssdssissii', $kendaraan_id, $tanggal_perbaikan, $jenis_perbaikan, $deskripsi, $bengkel, $biaya, $teknisi, $status_db, $km_perbaikan, $spare_parts, $catatan, $current_user_id, $perbaikan_id);

                if ($stmt->execute()) {
                    // Replace line items: delete then insert
                    $delItems = $conn->prepare("DELETE FROM riwayat_perbaikan_items WHERE perbaikan_id = ?");
                    if ($delItems) { $delItems->bind_param('i', $perbaikan_id); $delItems->execute(); $delItems->close(); }
                    if (!empty($item_nama) && is_array($item_nama)) {
                        $insItem = $conn->prepare("INSERT INTO riwayat_perbaikan_items (perbaikan_id, nama_barang, qty, satuan, harga, urutan) VALUES (?,?,?,?,?,?)");
                        if ($insItem) {
                            foreach ($item_nama as $i => $nm) {
                                $nm = trim((string)$nm);
                                if ($nm === '' && $i === 0 && $nama_barang_utama !== '') { $nm = $nama_barang_utama; }
                                if ($nm === '') continue;
                                $qty = isset($item_qty[$i]) ? (float)$item_qty[$i] : 0;
                                $sat = isset($item_satuan[$i]) ? trim((string)$item_satuan[$i]) : null;
                                $harga_item = isset($item_harga[$i]) ? (float)$item_harga[$i] : 0;
                                $urut = (int)$i + 1;
                                $insItem->bind_param('isdsdi', $perbaikan_id, $nm, $qty, $sat, $harga_item, $urut);
                                $insItem->execute();
                            }
                            $insItem->close();
                        }
                    }
                    // Sync kendaraan status based on repair status
                    $normalized_status = strtolower($status_db);
                    $in_progress_statuses = ['dalam proses', 'dalam progress', 'menunggu sparepart', 'ditunda'];
                    $status_kendaraan = null;
                    $status_peminjaman = null;
                    if (in_array($normalized_status, $in_progress_statuses, true)) {
                        $status_kendaraan = 'Perbaikan';
                        $status_peminjaman = 'Maintenance';
                    } elseif ($normalized_status === 'selesai') {
                        $status_kendaraan = 'Operasional';
                        $status_peminjaman = 'Tersedia';
                    }
                    if ($status_kendaraan !== null) {
                        if ($upd = $conn->prepare("UPDATE kendaraan SET status_kendaraan = ?, status_peminjaman = ? WHERE id = ?")) {
                            $upd->bind_param('ssi', $status_kendaraan, $status_peminjaman, $kendaraan_id);
                            $upd->execute();
                            $upd->close();
                        }
                    }

                    // Activity log (prefer No.Reg label)
                    {
                        $vehLabel = '';
                        if ($stVeh = $conn->prepare("SELECT COALESCE(NULLIF(TRIM(no_reg),''), NULLIF(TRIM(no_polisi),'')) AS label FROM kendaraan WHERE id = ?")) {
                            $stVeh->bind_param('i', $kendaraan_id);
                            $stVeh->execute();
                            $vehLabel = (string)($stVeh->get_result()->fetch_assoc()['label'] ?? '');
                            $stVeh->close();
                        }
                        $vehText = $vehLabel !== '' ? ('No.Reg ' . $vehLabel) : ('ID ' . $kendaraan_id);
                        $msgLog = "Edit riwayat perbaikan untuk kendaraan $vehText. Jenis: $jenis_perbaikan.";
                        if (function_exists('log_activity')) {
                            log_activity('EDIT_RIWAYAT_PERBAIKAN', $msgLog);
                        } else {
                            log_user_activity($msgLog);
                        }
                    }
                    header('Location: index.php?page=riwayat_perbaikan');
                    exit();
                } else {
                    if (strpos($stmt->error, 'foreign key constraint') !== false) {
                        $msg = '<div class="alert alert-danger">Error: Data kendaraan tidak valid atau telah dihapus!</div>';
                    } else {
                        $msg = '<div class="alert alert-danger">Error: ' . $stmt->error . '</div>';
                    }
                }
                $stmt->close();
            }
        }
    }
}

// Handle delete
if ($action === 'delete' && can_admin() && $perbaikan_id) {
    // Check if record exists before deletion and fetch context
    $check_stmt = $conn->prepare("SELECT rp.id, rp.kendaraan_id, rp.tanggal_perbaikan, rp.jenis_perbaikan FROM riwayat_perbaikan rp WHERE rp.id = ?");
    $check_stmt->bind_param('i', $perbaikan_id);
    $check_stmt->execute();
    $exists = $check_stmt->get_result()->fetch_assoc();
    $check_stmt->close();

    if (!$exists) {
        $msg = '<div class="alert alert-danger">Data tidak ditemukan!</div>';
    } else {
        $stmt = $conn->prepare("DELETE FROM riwayat_perbaikan WHERE id = ?");
        $stmt->bind_param('i', $perbaikan_id);
        if ($stmt->execute()) {
            $msg = '<div class="alert alert-success">Riwayat perbaikan berhasil dihapus!</div>';
            // Activity log (prefer No.Reg label)
            {
                $vehText = '';
                $kid = isset($exists['kendaraan_id']) ? (int)$exists['kendaraan_id'] : 0;
                if ($kid > 0) {
                    if ($stVeh = $conn->prepare("SELECT COALESCE(NULLIF(TRIM(no_reg),''), NULLIF(TRIM(no_polisi),'')) AS label FROM kendaraan WHERE id = ?")) {
                        $stVeh->bind_param('i', $kid);
                        $stVeh->execute();
                        $lab = (string)($stVeh->get_result()->fetch_assoc()['label'] ?? '');
                        $stVeh->close();
                        $vehText = $lab !== '' ? ('No.Reg ' . $lab) : ('ID ' . $kid);
                    }
                }
                $tgl = isset($exists['tanggal_perbaikan']) ? (string)$exists['tanggal_perbaikan'] : '';
                $jenis = isset($exists['jenis_perbaikan']) ? (string)$exists['jenis_perbaikan'] : '';
                $msgLog = "Hapus riwayat perbaikan" . ($jenis!==''?" ($jenis)":"") . ($vehText!==''?" untuk kendaraan $vehText":"");
                if (function_exists('log_activity')) {
                    log_activity('DELETE_RIWAYAT_PERBAIKAN', $msgLog);
                } else {
                    log_user_activity($msgLog);
                }
            }
        } else {
            $msg = '<div class="alert alert-danger">Error: ' . $stmt->error . '</div>';
        }
        $stmt->close();
    }
    $action = 'list';
}

// Get edit data
$edit_data = null;
if (($action === 'edit' || $action === 'view') && $perbaikan_id) {
    $stmt = $conn->prepare("SELECT * FROM riwayat_perbaikan WHERE id = ?");
    $stmt->bind_param('i', $perbaikan_id);
    $stmt->execute();
    $result = $stmt->get_result();
    $edit_data = $result->fetch_assoc();
    $stmt->close();

    // Fetch line items for editing
    if ($action === 'edit' && $edit_data) {
        $it = $conn->prepare("SELECT id, nama_barang, qty, satuan, harga FROM riwayat_perbaikan_items WHERE perbaikan_id = ? ORDER BY urutan ASC, id ASC");
        if ($it) {
            $it->bind_param('i', $perbaikan_id);
            $it->execute();
            $edit_items = $it->get_result()->fetch_all(MYSQLI_ASSOC);
            $it->close();
        } else {
            $edit_items = [];
        }
    }

    // Map DB column `deskripsi` to form-facing key `deskripsi_kerusakan`
    if (is_array($edit_data)) {
        $edit_data['deskripsi_kerusakan'] = $edit_data['deskripsi'] ?? '';
        // Ensure common keys exist to avoid undefined index warnings when rendering
        $_expected = [
            'kendaraan_id' => '',
            'tanggal_perbaikan' => '',
            'jenis_perbaikan' => '',
            'deskripsi_perbaikan' => '',
            'biaya' => 0,
            'km_perbaikan' => '',
            'bengkel' => '',
            'teknisi' => '',
            'status' => '',
            'keterangan' => '',
            'id' => null
        ];
        foreach ($_expected as $k => $v) {
            if (!array_key_exists($k, $edit_data)) {
                $edit_data[$k] = $v;
            }
        }
    }
}

// Ensure $edit_data is always an array with safe defaults to avoid undefined index / null warnings
if (!is_array($edit_data)) {
    $edit_data = [
        'kendaraan_id' => '',
        'tanggal_perbaikan' => '',
        'jenis_perbaikan' => '',
        'deskripsi_kerusakan' => '',
        'deskripsi_perbaikan' => '',
        'biaya' => 0,
        'km_perbaikan' => '',
        'bengkel' => '',
        'teknisi' => '',
        'status' => '',
        'keterangan' => ''
    ];
}

// Get vehicles for dropdown (for forms) and for filters
$vehicles = [];
if ($can_crud) {
    $vehicles_stmt = $conn->prepare("SELECT id, no_reg, no_polisi, merk, tipe FROM kendaraan ORDER BY no_reg");
    $vehicles_stmt->execute();
    $vehicles = $vehicles_stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    $vehicles_stmt->close();
}

    // Get teknisi/users for dropdown (searchable like jadwal_perawatan)
    $users = [];
    if ($can_crud) {
        if ($users_stmt = $conn->prepare("SELECT id, nama_lengkap FROM pengguna ORDER BY nama_lengkap")) {
            $users_stmt->execute();
            $users = $users_stmt->get_result()->fetch_all(MYSQLI_ASSOC);
            $users_stmt->close();
        }
    }

// Vehicle list for filters (available to all viewers)
$kendaraan_filter_list = [];
if ($stmt = $conn->prepare("SELECT id, no_reg, merk, tipe FROM kendaraan ORDER BY no_reg")) {
    $stmt->execute();
    $kendaraan_filter_list = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    $stmt->close();
}

// Get repair records - filters
// Accept both 'search' and 'q' for interoperability with other pages
$keyword = trim($_GET['q'] ?? ($_GET['search'] ?? ''));
// Status filter (default to Semua/empty to show all)
$filter_status = $_GET['status'] ?? '';
// If empty, no status filter will be applied
// Kendaraan filter: prefer 'kendaraan' param, fallback to legacy 'kendaraan_id'
$filter_kendaraan = isset($_GET['kendaraan']) ? (int)$_GET['kendaraan'] : null;
if ($filter_kendaraan) { $kendaraan_id = $filter_kendaraan; }
// Tanggal filter (YYYY-MM-DD)
$filter_tanggal = $_GET['tanggal'] ?? '';
$limit = 10;
$page = max(1, (int)($_GET['p'] ?? 1));
$offset = ($page - 1) * $limit;
// Sorting params (whitelisted per view)
$sort = strtolower(trim($_GET['sort'] ?? ''));
$dir = strtolower(trim($_GET['dir'] ?? 'desc'));
$dir = in_array($dir, ['asc','desc'], true) ? $dir : 'desc';

$where_conditions = [];
$params = [];
$param_types = '';

// Apply status filter (always present with default 'Selesai')
if ($filter_status) {
    $where_conditions[] = "rp.status = ?";
    $params[] = $filter_status;
    $param_types .= 's';
}

// Apply date filter
if (!empty($filter_tanggal)) {
    $where_conditions[] = "DATE(rp.tanggal_perbaikan) = ?";
    $params[] = $filter_tanggal;
    $param_types .= 's';
}

if ($keyword) {
    $where_conditions[] = "(k.no_reg LIKE ? OR k.no_polisi LIKE ? OR k.merk LIKE ? OR rp.jenis_perbaikan LIKE ? OR rp.bengkel LIKE ?)";
    $search_term = "%$keyword%";
    $params = array_merge($params, [$search_term, $search_term, $search_term, $search_term, $search_term]);
    $param_types .= 'sssss';
}

if ($kendaraan_id) {
    $where_conditions[] = "rp.kendaraan_id = ?";
    $params[] = (int)$kendaraan_id;
    $param_types .= 'i';
}

$where_sql = $where_conditions ? 'WHERE ' . implode(' AND ', $where_conditions) : '';

// Compute ORDER BY for repairs view
$repairs_sort_map = [
    'tanggal' => 'rp.tanggal_perbaikan',
    'no_reg' => 'k.no_reg',
    'jenis' => 'rp.jenis_perbaikan',
    'kerusakan' => 'rp.deskripsi',
    'bengkel' => 'rp.bengkel',
    'biaya' => 'rp.biaya',
    'status' => 'rp.status'
];
$order_by_repairs = 'rp.tanggal_perbaikan DESC';
if (isset($repairs_sort_map[$sort])) {
    $order_by_repairs = $repairs_sort_map[$sort] . ' ' . strtoupper($dir);
}

    $sql = "
    SELECT rp.*, k.no_reg, k.no_polisi, k.merk, k.tipe,
           p.nama_lengkap as created_by_name
    FROM riwayat_perbaikan rp
    LEFT JOIN kendaraan k ON rp.kendaraan_id = k.id
    LEFT JOIN pengguna p ON rp.created_by = p.id
    $where_sql
    ORDER BY $order_by_repairs
    LIMIT ? OFFSET ?
";

$stmt = $conn->prepare($sql);
if ($params) {
    $param_types .= 'ii';
    $params[] = $limit;
    $params[] = $offset;
    $stmt->bind_param($param_types, ...$params);
} else {
    $stmt->bind_param('ii', $limit, $offset);
}

$stmt->execute();
$repairs = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$stmt->close();

// Get total count for pagination
$count_sql = "
    SELECT COUNT(*) as total
    FROM riwayat_perbaikan rp
    LEFT JOIN kendaraan k ON rp.kendaraan_id = k.id
    $where_sql
";

$count_stmt = $conn->prepare($count_sql);
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
?>

<div class="page-header">
    <h1><i class="fas fa-wrench"></i> Riwayat Perbaikan Kendaraan</h1>
    <div class="header-actions">
        <?php if ($can_crud && $action === 'list'): ?>
            <a href="index.php?page=riwayat_perbaikan&action=add" class="btn btn-success">
                <i class="fas fa-plus"></i> Tambah Riwayat
            </a>
        <?php endif; ?>
                <?php if ($can_crud): ?>
                <button class="btn btn-success" data-bs-toggle="modal" data-bs-target="#exportPerbaikanModal"><i class="fas fa-file-excel"></i> Export Excel</button>
                <?php endif; ?>        
    </div>
</div>

<?= $msg ?>

<?php
// Helper: render status as Bootstrap badge
if (!function_exists('render_repair_status_badge')) {
    function render_repair_status_badge($status) {
        $label = is_string($status) ? trim($status) : '';
        $lc = strtolower($label);
        if ($lc === 'selesai') {
            $class = 'badge-success';
            $label = 'Selesai';
        } elseif ($lc === 'dalam proses') {
            $class = 'badge-warning';
            $label = 'Dalam Proses';
        } elseif ($lc === 'menunggu sparepart') {
            $class = 'badge-info';
            $label = 'Menunggu Sparepart';
        } elseif ($lc === 'ditunda') {
            $class = 'badge-secondary';
            $label = 'Ditunda';
        } else {
            $class = 'badge-light text-dark';
            $label = $label !== '' ? htmlspecialchars($label) : '-';
        }
        return '<span class="badge ' . $class . '">' . htmlspecialchars($label) . '</span>';
    }
}
// Helper: build sortable header links
if (!function_exists('sort_link')) {
    function sort_link($label, $key, $currentSort, $currentDir) {
        $curDir = strtolower((string)$currentDir);
        if ($curDir !== 'asc' && $curDir !== 'desc') { $curDir = 'desc'; }
        $nextDir = ($currentSort === $key && $curDir === 'asc') ? 'desc' : 'asc';
        $params = $_GET;
        // preserve existing parameters and toggle dir for this key
        $params['sort'] = $key;
        $params['dir'] = $nextDir;
        $qs = http_build_query($params);
        $icon = '';
        if ($currentSort === $key) {
            $icon = $curDir === 'asc' ? ' <i class="fas fa-sort-up"></i>' : ' <i class="fas fa-sort-down"></i>';
        } else {
            $icon = ' <i class="fas fa-sort text-muted"></i>';
        }
        return '<a href="?'.$qs.'" class="text-decoration-none">'.htmlspecialchars($label).$icon.'</a>';
    }
}
?>

<?php if ($can_crud && $action === 'add'): ?>
    <div class="card">
        <div class="card-header">
            <h3><i class="fas fa-plus"></i> Tambah Riwayat Perbaikan</h3>
        </div>
        <div class="card-body">
            <form method="post" class="repair-form">
                <input type="hidden" name="csrf_token" value="<?= generate_csrf_token() ?>">
                
                <div class="form-row">
                    <div class="form-group">
                        <label for="kendaraan_id">Kendaraan *</label>
                        <select id="kendaraan_id" name="kendaraan_id" class="form-control" required>
                            <option value="">Pilih Kendaraan</option>
                            <?php foreach ($vehicles as $vehicle): ?>
                                <?php $label = htmlspecialchars(($vehicle['no_polisi'] ?? '') . ' - ' . ($vehicle['merk'] ?? '') . ' ' . ($vehicle['tipe'] ?? '')); ?>
                                <option value="<?= $vehicle['id'] ?>" data-no_reg="<?= htmlspecialchars($vehicle['no_reg'] ?? '') ?>" <?= ($kendaraan_id == $vehicle['id']) ? 'selected' : '' ?>>
                                    <?= $label ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    
                    <div class="form-group">
                        <label for="tanggal_perbaikan">Tanggal Perbaikan *</label>
                        <input type="date" id="tanggal_perbaikan" name="tanggal_perbaikan" class="form-control" required value="<?= date('Y-m-d') ?>">
                    </div>
                    
                    <div class="form-group">
                        <label for="jenis_perbaikan">Jenis Perbaikan *</label>
                        <select id="jenis_perbaikan" name="jenis_perbaikan" class="form-control" required>
                            <option value="">Pilih Jenis</option>
                            <option value="Perbaikan Mesin">Perbaikan Mesin</option>
                            <option value="Perbaikan Body">Perbaikan Body</option>
                            <option value="Perbaikan Kelistrikan">Perbaikan Kelistrikan</option>
                            <option value="Perbaikan AC">Perbaikan AC</option>
                            <option value="Perbaikan Rem">Perbaikan Rem</option>
                            <option value="Perbaikan Transmisi">Perbaikan Transmisi</option>
                            <option value="Perbaikan Ban">Perbaikan Ban</option>
                            <option value="Lainnya">Lainnya</option>
                        </select>
                    </div>
                    
                    <div class="form-group">
                        <label for="nama_barang_utama">Nama Barang (opsional)</label>
                        <input type="text" id="nama_barang_utama" name="nama_barang_utama" class="form-control" placeholder="Nama barang utama">
                    </div>
                </div>
                <div class="form-row">
                    <div class="form-group">
                        <label for="teknisi_user_id">Teknisi</label>
                        <select id="teknisi_user_id" name="teknisi_user_id" class="form-control">
                            <option value="">-- Pilih Teknisi (opsional) --</option>
                            <?php foreach ($users as $u): ?>
                                <option value="<?= $u['id'] ?>"><?= htmlspecialchars((string)$u['nama_lengkap']) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                </div>
                
                <div class="form-row">
                    <div class="form-group">
                        <label for="deskripsi_kerusakan">Deskripsi Kerusakan *</label>
                        <textarea id="deskripsi_kerusakan" name="deskripsi_kerusakan" class="form-control" rows="3" required placeholder="Jelaskan kerusakan yang terjadi"></textarea>
                    </div>
                    
                    <div class="form-group">
                        <label for="deskripsi_perbaikan">Deskripsi Perbaikan *</label>
                        <textarea id="deskripsi_perbaikan" name="deskripsi_perbaikan" class="form-control" rows="3" required placeholder="Jelaskan perbaikan yang dilakukan"></textarea>
                    </div>
                </div>
                
                <!-- Hidden biaya to sync with items total -->
                <input type="hidden" id="biaya" name="biaya" value="">
                
                <div class="form-row">
                    <div class="form-group">
                        <label for="status">Status Perbaikan</label>
                        <select id="status" name="status" class="form-control">
                            <option value="Selesai" selected>Selesai</option>
                            <option value="Dalam Proses">Dalam Proses</option>
                            <option value="Menunggu Sparepart">Menunggu Sparepart</option>
                            <option value="Ditunda">Ditunda</option>
                        </select>
                    </div>
                </div>
                
                <hr>
                <h5>Rincian Barang/Jasa</h5>
                <div class="table-responsive">
                    <table class="table table-sm" id="items-table">
                        <thead>
                            <tr>
                                <th style="width:60px">No</th>
                                <th>Nama Barang/Jasa</th>
                                <th style="width:140px">Banyaknya</th>
                                <th style="width:160px">Harga Satuan (Rp)</th>
                                <th style="width:160px">Jumlah (Rp)</th>
                                <th style="width:80px">Aksi</th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr>
                                <td class="row-no">1</td>
                                <td><input type="text" name="item_nama[]" class="form-control" placeholder="Nama barang/jasa"></td>
                                <td>
                                    <div class="input-group">
                                        <input type="number" name="item_qty[]" class="form-control qty" min="0" placeholder="0">
                                        <input type="text" name="item_satuan[]" class="form-control" style="max-width:80px" placeholder="Unit">
                                    </div>
                                </td>
                                <td><input type="number" name="item_harga[]" class="form-control harga" min="0" placeholder="0"></td>
                                <td><input type="text" class="form-control jumlah" readonly></td>
                                <td><button type="button" class="btn btn-danger btn-sm remove-row">Hapus</button></td>
                            </tr>
                        </tbody>
                        <tfoot>
                            <tr>
                                <td colspan="4" class="text-end"><strong>Total</strong></td>
                                <td><input type="text" id="items-total" class="form-control" readonly></td>
                                <td></td>
                            </tr>
                        </tfoot>
                    </table>
                </div>
                <button type="button" id="add-item-row" class="btn btn-outline-primary btn-sm"><i class="fas fa-plus"></i> Tambah Baris</button>

                <div class="form-actions">
                    <button type="submit" class="btn btn-primary">
                        <i class="fas fa-save"></i> Simpan Riwayat
                    </button>
                    <a href="index.php?page=riwayat_perbaikan" class="btn btn-secondary">
                        <i class="fas fa-times"></i> Batal
                    </a>
                </div>
            </form>
        </div>
    </div>

<?php elseif ($can_crud && $action === 'edit' && $edit_data): ?>
    <div class="card">
        <div class="card-header">
            <h3><i class="fas fa-edit"></i> Edit Riwayat Perbaikan</h3>
        </div>
        <div class="card-body">
            <form method="post" class="repair-form">
                <input type="hidden" name="csrf_token" value="<?= generate_csrf_token() ?>">
                
                <div class="form-row">
                    <div class="form-group">
                        <label for="kendaraan_id">Kendaraan *</label>
                        <select id="kendaraan_id" name="kendaraan_id" class="form-control" required>
                            <option value="">Pilih Kendaraan</option>
                            <?php foreach ($vehicles as $vehicle): ?>
                                <?php $label = htmlspecialchars(($vehicle['no_polisi'] ?? '') . ' - ' . ($vehicle['merk'] ?? '') . ' ' . ($vehicle['tipe'] ?? '')); ?>
                                <option value="<?= $vehicle['id'] ?>" data-no_reg="<?= htmlspecialchars($vehicle['no_reg'] ?? '') ?>" <?= ($edit_data['kendaraan_id'] == $vehicle['id']) ? 'selected' : '' ?>>
                                    <?= $label ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    
                    <div class="form-group">
                        <label for="tanggal_perbaikan">Tanggal Perbaikan *</label>
                        <input type="date" id="tanggal_perbaikan" name="tanggal_perbaikan" class="form-control" required value="<?= $edit_data['tanggal_perbaikan'] ?>">
                    </div>
                    
                    <div class="form-group">
                        <label for="jenis_perbaikan">Jenis Perbaikan *</label>
                        <select id="jenis_perbaikan" name="jenis_perbaikan" class="form-control" required>
                            <option value="">Pilih Jenis</option>
                            <option value="Perbaikan Mesin" <?= $edit_data['jenis_perbaikan'] === 'Perbaikan Mesin' ? 'selected' : '' ?>>Perbaikan Mesin</option>
                            <option value="Perbaikan Body" <?= $edit_data['jenis_perbaikan'] === 'Perbaikan Body' ? 'selected' : '' ?>>Perbaikan Body</option>
                            <option value="Perbaikan Kelistrikan" <?= $edit_data['jenis_perbaikan'] === 'Perbaikan Kelistrikan' ? 'selected' : '' ?>>Perbaikan Kelistrikan</option>
                            <option value="Perbaikan AC" <?= $edit_data['jenis_perbaikan'] === 'Perbaikan AC' ? 'selected' : '' ?>>Perbaikan AC</option>
                            <option value="Perbaikan Rem" <?= $edit_data['jenis_perbaikan'] === 'Perbaikan Rem' ? 'selected' : '' ?>>Perbaikan Rem</option>
                            <option value="Perbaikan Transmisi" <?= $edit_data['jenis_perbaikan'] === 'Perbaikan Transmisi' ? 'selected' : '' ?>>Perbaikan Transmisi</option>
                            <option value="Perbaikan Ban" <?= $edit_data['jenis_perbaikan'] === 'Perbaikan Ban' ? 'selected' : '' ?>>Perbaikan Ban</option>
                            <option value="Lainnya" <?= $edit_data['jenis_perbaikan'] === 'Lainnya' ? 'selected' : '' ?>>Lainnya</option>
                        </select>
                    </div>
                    
                    <div class="form-group">
                        <label for="nama_barang_utama">Nama Barang (opsional)</label>
                        <input type="text" id="nama_barang_utama" name="nama_barang_utama" class="form-control" placeholder="Nama barang utama">
                    </div>
                </div>
                <?php
                    // Try to preselect teknisi by matching existing name to users list
                    $selected_teknisi_user_id = '';
                    if (!empty($edit_data['teknisi']) && is_array($users)) {
                        foreach ($users as $u) {
                            if (strcasecmp((string)$u['nama_lengkap'], (string)$edit_data['teknisi']) === 0) { $selected_teknisi_user_id = (string)$u['id']; break; }
                        }
                    }
                ?>
                <div class="form-row">
                    <div class="form-group">
                        <label for="teknisi_user_id">Teknisi</label>
                        <select id="teknisi_user_id" name="teknisi_user_id" class="form-control">
                            <option value="">-- Pilih Teknisi (opsional) --</option>
                            <?php foreach ($users as $u): ?>
                                <option value="<?= $u['id'] ?>" <?= ($selected_teknisi_user_id !== '' && $selected_teknisi_user_id == (string)$u['id']) ? 'selected' : '' ?>><?= htmlspecialchars((string)$u['nama_lengkap']) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                </div>
                
                <div class="form-row">
                    <div class="form-group">
                        <label for="deskripsi_kerusakan">Deskripsi Kerusakan *</label>
                        <textarea id="deskripsi_kerusakan" name="deskripsi_kerusakan" class="form-control" rows="3" required><?= htmlspecialchars($edit_data['deskripsi_kerusakan']) ?></textarea>
                    </div>
                    
                    <div class="form-group">
                        <label for="deskripsi_perbaikan">Deskripsi Perbaikan *</label>
                        <textarea id="deskripsi_perbaikan" name="deskripsi_perbaikan" class="form-control" rows="3" required><?= htmlspecialchars($edit_data['deskripsi_perbaikan']) ?></textarea>
                    </div>
                </div>
                
                <!-- Hidden biaya to sync with items total -->
                <input type="hidden" id="biaya" name="biaya" value="<?= htmlspecialchars((string)($edit_data['biaya'] ?? '')) ?>">
                
                <div class="form-row">
                    <div class="form-group">
                        <label for="status">Status Perbaikan</label>
                        <select id="status" name="status" class="form-control">
                            <option value="Dalam Proses" <?= $edit_data['status'] === 'Dalam Proses' ? 'selected' : '' ?>>Dalam Proses</option>
                            <option value="Menunggu Sparepart" <?= $edit_data['status'] === 'Menunggu Sparepart' ? 'selected' : '' ?>>Menunggu Sparepart</option>
                            <option value="Ditunda" <?= $edit_data['status'] === 'Ditunda' ? 'selected' : '' ?>>Ditunda</option>
                            <option value="Selesai" <?= $edit_data['status'] === 'Selesai' ? 'selected' : '' ?>>Selesai</option>
                        </select>
                    </div>
                </div>
                
                <hr>
                <h5>Rincian Barang/Jasa</h5>
                <div class="table-responsive">
                    <table class="table table-sm" id="items-table">
                        <thead>
                            <tr>
                                <th style="width:60px">No</th>
                                <th>Nama Barang/Jasa</th>
                                <th style="width:140px">Banyaknya</th>
                                <th style="width:160px">Harga Satuan (Rp)</th>
                                <th style="width:160px">Jumlah (Rp)</th>
                                <th style="width:80px">Aksi</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (!empty($edit_items)): foreach ($edit_items as $i => $it): ?>
                            <tr>
                                <td class="row-no"><?= $i+1 ?></td>
                                <td><input type="text" name="item_nama[]" class="form-control" value="<?= htmlspecialchars((string)$it['nama_barang']) ?>" placeholder="Nama barang/jasa"></td>
                                <td>
                                    <div class="input-group">
                                        <input type="number" name="item_qty[]" class="form-control qty" min="0" value="<?= (float)$it['qty'] ?>">
                                        <input type="text" name="item_satuan[]" class="form-control" style="max-width:80px" value="<?= htmlspecialchars((string)($it['satuan'] ?? '')) ?>">
                                    </div>
                                </td>
                                <td><input type="number" name="item_harga[]" class="form-control harga" min="0" value="<?= (float)$it['harga'] ?>"></td>
                                <td><input type="text" class="form-control jumlah" readonly></td>
                                <td><button type="button" class="btn btn-danger btn-sm remove-row">Hapus</button></td>
                            </tr>
                            <?php endforeach; else: ?>
                            <tr>
                                <td class="row-no">1</td>
                                <td><input type="text" name="item_nama[]" class="form-control" placeholder="Nama barang/jasa"></td>
                                <td>
                                    <div class="input-group">
                                        <input type="number" name="item_qty[]" class="form-control qty" min="0" placeholder="0">
                                        <input type="text" name="item_satuan[]" class="form-control" style="max-width:80px" placeholder="Unit">
                                    </div>
                                </td>
                                <td><input type="number" name="item_harga[]" class="form-control harga" min="0" placeholder="0"></td>
                                <td><input type="text" class="form-control jumlah" readonly></td>
                                <td><button type="button" class="btn btn-danger btn-sm remove-row">Hapus</button></td>
                            </tr>
                            <?php endif; ?>
                        </tbody>
                        <tfoot>
                            <tr>
                                <td colspan="4" class="text-end"><strong>Total</strong></td>
                                <td><input type="text" id="items-total" class="form-control" readonly></td>
                                <td></td>
                            </tr>
                        </tfoot>
                    </table>
                </div>
                <button type="button" id="add-item-row" class="btn btn-outline-primary btn-sm"><i class="fas fa-plus"></i> Tambah Baris</button>

                <div class="form-actions">
                    <button type="submit" class="btn btn-primary">
                        <i class="fas fa-save"></i> Update Riwayat
                    </button>
                    <a href="index.php?page=riwayat_perbaikan" class="btn btn-secondary">
                        <i class="fas fa-times"></i> Batal
                    </a>
                </div>
            </form>
        </div>
    </div>

<?php else: ?>
    <!-- Filters - match Riwayat Perawatan layout -->
<div class="card mb-9">
  <div class="card-body">
    <form method="get" class="row g-3 filters-row">
      <input type="hidden" name="page" value="riwayat_perbaikan">
      <input type="hidden" name="group" value="<?= htmlspecialchars((string)($_GET['group'] ?? 'repairs')) ?>">

            <div class="col-md-3">
                <label class="form-label">Status</label>
                <select name="status" class="form-select">
                    <option value="" <?= $filter_status === '' ? 'selected' : '' ?>>Semua Status</option>
          <option value="Dalam Proses" <?= $filter_status === 'Dalam Proses' ? 'selected' : '' ?>>Dalam Proses</option>
          <option value="Menunggu Sparepart" <?= $filter_status === 'Menunggu Sparepart' ? 'selected' : '' ?>>Menunggu Sparepart</option>
          <option value="Ditunda" <?= $filter_status === 'Ditunda' ? 'selected' : '' ?>>Ditunda</option>
          <option value="Selesai" <?= $filter_status === 'Selesai' ? 'selected' : '' ?>>Selesai</option>
        </select>
      </div>

      <div class="col-md-3">
        <label class="form-label">Kendaraan</label>
        <select name="kendaraan" class="form-select">
          <option value="">Semua Kendaraan</option>
          <?php foreach ($kendaraan_filter_list as $v): ?>
            <option value="<?= (int)$v['id'] ?>" <?= ((int)($kendaraan_id ?? 0) === (int)$v['id']) ? 'selected' : '' ?>>
              <?= htmlspecialchars((string)($v['no_reg'] ?? '')) ?> - <?= htmlspecialchars((string)($v['merk'] ?? '')) ?> <?= htmlspecialchars((string)($v['tipe'] ?? '')) ?>
            </option>
          <?php endforeach; ?>
        </select>
      </div>

      <div class="col-md-2">
        <label class="form-label">Tanggal</label>
        <input type="date" name="tanggal" class="form-control" value="<?= htmlspecialchars((string)$filter_tanggal) ?>">
      </div>

            <div class="col-md-2">
        <label class="form-label">Pencarian</label>
        <input type="text" name="search" class="form-control" placeholder="Cari jenis/bengkel/no reg..." value="<?= htmlspecialchars((string)$keyword) ?>">
      </div>

            <div class="col-md-2">
                <label class="form-label">&nbsp;</label>
                <div class="d-flex gap-2">
                    <button type="submit" class="btn btn-primary" aria-label="Cari">
                        <i class="fas fa-search"></i>
                    </button>
                    <?php 
                        $has_filters_inline = ($keyword !== '' || !empty($kendaraan_id) || !empty($filter_tanggal) || ($filter_status !== ''));
                        if ($has_filters_inline): ?>
                        <a href="index.php?page=riwayat_perbaikan&group=<?= htmlspecialchars((string)($_GET['group'] ?? 'repairs')) ?>" class="btn btn-outline-secondary" title="Reset" aria-label="Reset">
                            <i class="fas fa-times"></i>
                        </a>
                    <?php endif; ?>
                </div>
            </div>
    </form>
  </div>
</div>

    <?php
        // Default view: tampilkan semua perbaikan terlebih dahulu. Gunakan ?group=vehicles untuk ringkasan per-kendaraan.
        $group = $_GET['group'] ?? 'repairs';
    ?>

    <div class="actions-bar mb-3">
        <div class="float-right">
            <?php if ($group === 'repairs'): ?>
                <a href="index.php?page=riwayat_perbaikan&group=vehicles<?= $keyword ? '&q=' . urlencode($keyword) : '' ?><?= $kendaraan_id ? '&kendaraan=' . (int)$kendaraan_id : '' ?><?= $filter_status ? '&status=' . urlencode($filter_status) : '' ?><?= $filter_tanggal ? '&tanggal=' . urlencode($filter_tanggal) : '' ?>" class="btn btn-outline-secondary">Tampilkan Per-Kendaraan</a>
            <?php else: ?>
                <a href="index.php?page=riwayat_perbaikan&group=repairs<?= $keyword ? '&q=' . urlencode($keyword) : '' ?><?= $kendaraan_id ? '&kendaraan=' . (int)$kendaraan_id : '' ?><?= $filter_status ? '&status=' . urlencode($filter_status) : '' ?><?= $filter_tanggal ? '&tanggal=' . urlencode($filter_tanggal) : '' ?>" class="btn btn-outline-secondary">Tampilkan Semua Perbaikan</a>
            <?php endif; ?>
        </div>
        <div class="clearfix"></div>
    </div>

    <?php if ($group === 'repairs'): ?>
        <!-- Full per-repair list (kept from previous implementation) -->
        <div class="card">
            <div class="card-header">
                <h5 class="mb-0">Daftar Perbaikan Keseluruhan</h5>
            </div>
            <div class="card-body">
                <div class="table-responsive">
                    <table class="table">
                        <thead>
                            <tr>
                                <th>No</th>
                                    <th><?= sort_link('Tanggal','tanggal', $sort, $dir) ?></th>
                                    <th><?= sort_link('Kendaraan','no_reg', $sort, $dir) ?></th>
                                    <th><?= sort_link('Jenis Perbaikan','jenis', $sort, $dir) ?></th>
                                    <th><?= sort_link('Kerusakan','kerusakan', $sort, $dir) ?></th>
                                    <th>Nama Barang</th>
                                    <th>Banyaknya</th>
                                    <th>Harga</th>
                                    <th>Jumlah</th>
                                    <th><?= sort_link('Total (Rp)','biaya', $sort, $dir) ?></th>
                                    <th><?= sort_link('Status','status', $sort, $dir) ?></th>
                                <th>Aksi</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (count($repairs) > 0): ?>
                                <?php $no = ($page - 1) * $limit + 1; foreach ($repairs as $repair): ?>
                                    <?php
                                        $repair['jenis_perbaikan'] = (string)($repair['jenis_perbaikan'] ?? '');
                                        $repair['deskripsi_kerusakan'] = (string)($repair['deskripsi'] ?? '');
                                        $repair['keterangan'] = (string)($repair['keterangan'] ?? '');
                                        // Fetch items for this repair to display inline and compute total
                                        $sumItem = 0.0; $firstNama='-'; $firstQty='-'; $firstHarga='-'; $firstJumlah='-';
                                        if ($its = $conn->prepare('SELECT nama_barang, qty, satuan, harga FROM riwayat_perbaikan_items WHERE perbaikan_id = ? ORDER BY urutan ASC, id ASC')) {
                                            $its->bind_param('i', $repair['id']);
                                            $its->execute();
                                            $res = $its->get_result();
                                            $rows = $res ? $res->fetch_all(MYSQLI_ASSOC) : [];
                                            $its->close();
                                            if (!empty($rows)) {
                                                $it0 = $rows[0];
                                                $qtyLabel = (string)($it0['qty'] ?? 0);
                                                if (!empty($it0['satuan'])) { $qtyLabel .= ' ' . (string)$it0['satuan']; }
                                                $firstNama = (string)$it0['nama_barang'];
                                                $firstQty = $qtyLabel;
                                                $firstHarga = (float)$it0['harga'] > 0 ? 'Rp ' . number_format((float)$it0['harga']) : '-';
                                                $firstJumlah = 'Rp ' . number_format(((float)$it0['qty']) * ((float)$it0['harga']));
                                                foreach ($rows as $it) {
                                                    $sumItem += ((float)$it['qty']) * ((float)$it['harga']);
                                                }
                                            }
                                        }
                                        $total_dari_items = $sumItem;
                                        $total_tampil = $total_dari_items > 0 ? $total_dari_items : (float)($repair['biaya'] ?? 0);
                                    ?>
                                    <tr>
                                        <td><?= $no++ ?></td>
                                        <td><?= date('d/m/Y', strtotime($repair['tanggal_perbaikan'])) ?></td>
                                        <td>
                                            <div class="vehicle-info">
                                                <strong><?= htmlspecialchars((string)($repair['no_reg'] ?? '')) ?></strong>
                                                <br><small class="text-muted"><?= htmlspecialchars((string)($repair['merk'] ?? '')) ?> <?= htmlspecialchars((string)($repair['tipe'] ?? '')) ?></small>
                                            </div>
                                        </td>
                                        <td><span class="badge badge-light text-dark"><?= htmlspecialchars($repair['jenis_perbaikan'] ?: '-') ?></span></td>
                                        <td><div class="text-truncate text-truncate-custom" title="<?= htmlspecialchars($repair['deskripsi_kerusakan'] ?: '-') ?>"><?= htmlspecialchars($repair['deskripsi_kerusakan'] ?: '-') ?></div></td>
                                        <td><?= htmlspecialchars($firstNama) ?></td>
                                        <td><?= htmlspecialchars($firstQty) ?></td>
                                        <td><?= htmlspecialchars($firstHarga) ?></td>
                                        <td><?= htmlspecialchars($firstJumlah) ?></td>
                                        <td><?= ($total_tampil > 0) ? 'Rp ' . number_format($total_tampil) : '-' ?></td>
                                        <td><?= render_repair_status_badge($repair['status'] ?? '') ?></td>
                                        <td>
                                            <div class="btn-group" role="group">
                                                <a href="index.php?page=riwayat_perbaikan_detail&id=<?= $repair['id'] ?>" class="btn btn-sm btn-outline-info" title="Lihat Detail"><i class="fas fa-eye"></i></a>
                                                <?php if ($can_crud): ?><a href="index.php?page=riwayat_perbaikan&action=edit&id=<?= $repair['id'] ?>" class="btn btn-sm btn-outline-primary" title="Edit"><i class="fas fa-edit"></i></a><?php endif; ?>
                                                <?php if (can_admin()): ?><a href="index.php?page=riwayat_perbaikan&action=delete&id=<?= $repair['id'] ?>" class="btn btn-sm btn-outline-danger" title="Hapus" onclick="return confirm('Yakin ingin menghapus riwayat perbaikan ini?')"><i class="fas fa-trash"></i></a><?php endif; ?>
                                            </div>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php else: ?>
                                <tr><td colspan="12" class="text-center">Belum ada riwayat perbaikan.</td></tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>

                <!-- Pagination (kept) -->
                <?php if ($total_pages > 1): ?>
                    <div class="pagination-wrapper">
                        <nav aria-label="Pagination">
                            <ul class="pagination">
                                <?php if ($page > 1): ?><li class="page-item"><a class="page-link" href="index.php?page=riwayat_perbaikan&group=repairs&p=<?= $page - 1 ?><?= $keyword ? '&q=' . urlencode($keyword) : '' ?><?= $kendaraan_id ? '&kendaraan=' . (int)$kendaraan_id : '' ?><?= $filter_status ? '&status=' . urlencode($filter_status) : '' ?><?= $filter_tanggal ? '&tanggal=' . urlencode($filter_tanggal) : '' ?><?= $sort ? '&sort=' . urlencode($sort) : '' ?><?= $dir ? '&dir=' . urlencode($dir) : '' ?>"><i class="fas fa-chevron-left"></i></a></li><?php endif; ?>
                                <?php for ($i = max(1, $page - 2); $i <= min($total_pages, $page + 2); $i++): ?><li class="page-item <?= $i == $page ? 'active' : '' ?>"><a class="page-link" href="index.php?page=riwayat_perbaikan&group=repairs&p=<?= $i ?><?= $keyword ? '&q=' . urlencode($keyword) : '' ?><?= $kendaraan_id ? '&kendaraan=' . (int)$kendaraan_id : '' ?><?= $filter_status ? '&status=' . urlencode($filter_status) : '' ?><?= $filter_tanggal ? '&tanggal=' . urlencode($filter_tanggal) : '' ?><?= $sort ? '&sort=' . urlencode($sort) : '' ?><?= $dir ? '&dir=' . urlencode($dir) : '' ?>"><?= $i ?></a></li><?php endfor; ?>
                                <?php if ($page < $total_pages): ?><li class="page-item"><a class="page-link" href="index.php?page=riwayat_perbaikan&group=repairs&p=<?= $page + 1 ?><?= $keyword ? '&q=' . urlencode($keyword) : '' ?><?= $kendaraan_id ? '&kendaraan=' . (int)$kendaraan_id : '' ?><?= $filter_status ? '&status=' . urlencode($filter_status) : '' ?><?= $filter_tanggal ? '&tanggal=' . urlencode($filter_tanggal) : '' ?><?= $sort ? '&sort=' . urlencode($sort) : '' ?><?= $dir ? '&dir=' . urlencode($dir) : '' ?>"><i class="fas fa-chevron-right"></i></a></li><?php endif; ?>
                            </ul>
                        </nav>
                        <div class="pagination-info">Menampilkan <?= min($total_records, $offset + 1) ?> - <?= min($total_records, $offset + $limit) ?> dari <?= $total_records ?> data</div>
                    </div>
                <?php endif; ?>
            </div>
        </div>

    <?php else: ?>
        <!-- Aggregated per-vehicle summary -->
        <?php
            // Reuse existing where_sql and params but remove the LIMIT/OFFSET if present
            $agg_params = $params ? array_slice($params, 0, max(0, count($params) - 2)) : [];
            $agg_param_types = $param_types ? substr($param_types, 0, max(0, strlen($param_types) - 2)) : '';

            // Pagination for aggregated vehicles
            $agg_limit = $limit;
            $agg_page = max(1, (int)($_GET['p'] ?? 1));
            $agg_offset = ($agg_page - 1) * $agg_limit;

            // Count total vehicles matching filters (use DISTINCT to be safe when JOINing riwayat_perbaikan)
            $count_agg_sql = "SELECT COUNT(DISTINCT k.id) AS total FROM kendaraan k LEFT JOIN riwayat_perbaikan rp ON rp.kendaraan_id = k.id $where_sql";
            $count_agg_stmt = $conn->prepare($count_agg_sql);
            $vehicles_total_records = 0;
            if ($count_agg_stmt) {
                if ($agg_params) {
                    $count_agg_stmt->bind_param($agg_param_types, ...$agg_params);
                }
                $count_agg_stmt->execute();
                $vehicles_total_records = (int)$count_agg_stmt->get_result()->fetch_assoc()['total'];
                $count_agg_stmt->close();
            }
            $vehicles_total_pages = max(1, (int)ceil($vehicles_total_records / $agg_limit));

            // Sorting for vehicles aggregate
            $vehicles_sort_map = [
                'no_reg' => 'k.no_reg',
                'merk' => 'k.merk',
                'terakhir' => 'terakhir_tanggal',
                'total' => 'total_perbaikan',
                'biaya' => 'total_biaya'
            ];
            $order_by_vehicles = 'terakhir_tanggal DESC';
            if (isset($vehicles_sort_map[$sort])) { $order_by_vehicles = $vehicles_sort_map[$sort] . ' ' . strtoupper($dir); }

            $agg_sql = "SELECT k.id, k.no_polisi, k.no_reg, k.merk, k.tipe,
                               COUNT(rp.id) AS total_perbaikan,
                               COALESCE(SUM(rp.biaya),0) AS total_biaya,
                               MAX(rp.tanggal_perbaikan) AS terakhir_tanggal
                        FROM kendaraan k
                        LEFT JOIN riwayat_perbaikan rp ON rp.kendaraan_id = k.id
                        $where_sql
                        GROUP BY k.id
                        ORDER BY $order_by_vehicles
                        LIMIT ? OFFSET ?";

            $agg_stmt = $conn->prepare($agg_sql);
            $vehicles_aggregate = [];
            if ($agg_stmt) {
                if ($agg_params) {
                    // bind existing filter params + limit + offset
                    $bind_types = $agg_param_types . 'ii';
                    $bind_vals = array_merge($agg_params, [$agg_limit, $agg_offset]);
                    $agg_stmt->bind_param($bind_types, ...$bind_vals);
                } else {
                    // only limit/offset
                    $agg_stmt->bind_param('ii', $agg_limit, $agg_offset);
                }
                $agg_stmt->execute();
                $vehicles_aggregate = $agg_stmt->get_result()->fetch_all(MYSQLI_ASSOC);
                $agg_stmt->close();
            }
        ?>

        <div class="card">
            <div class="card-header">
                <h5 class="mb-0">Daftar Perbaikan Perkendaraan</h5>
            </div>
            <div class="card-body">
                <div class="table-responsive">
                    <table class="table table-striped table-hover">
                        <thead>
                            <tr>
                                <th>No</th>
                                <th><?= sort_link('Kendaraan','no_reg', $sort, $dir) ?></th>
                                <th><?= sort_link('Merk / Tipe','merk', $sort, $dir) ?></th>
                                <th><?= sort_link('Terakhir Perbaikan','terakhir', $sort, $dir) ?></th>
                                <th><?= sort_link('Total Perbaikan','total', $sort, $dir) ?></th>
                                <th><?= sort_link('Total Biaya','biaya', $sort, $dir) ?></th>
                                <th>Aksi</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (count($vehicles_aggregate) > 0): foreach ($vehicles_aggregate as $i => $v): ?>
                                <tr>
                                    <td><?= $i + 1 ?></td>
                                    <td>
                                        <strong><?= htmlspecialchars((string)$v['no_reg']) ?></strong>
                                    </td>
                                    <td><?= htmlspecialchars((string)($v['merk'] ?? '-')) ?> <?= htmlspecialchars((string)($v['tipe'] ?? '')) ?></td>
                                    <td><?= !empty($v['terakhir_tanggal']) ? date('d/m/Y', strtotime($v['terakhir_tanggal'])) : '-' ?></td>
                                    <td><?= (int)$v['total_perbaikan'] ?></td>
                                    <td><?= ($v['total_biaya'] > 0) ? 'Rp ' . number_format($v['total_biaya']) : '-' ?></td>
                                    <td>
                                        <a href="index.php?page=riwayat_perbaikan_detail&kendaraan_id=<?= $v['id'] ?>" class="btn btn-primary" title="Lihat Riwayat"><i class="fas fa-list"></i> Lihat</a>
                                        <?php if ($can_crud): ?><a href="index.php?page=riwayat_perbaikan&kendaraan_id=<?= $v['id'] ?>&action=add" class="btn btn-success" title="Tambah Perbaikan"><i class="fas fa-plus"></i></a><?php endif; ?>
                                    </td>
                                </tr>
                            <?php endforeach; else: ?>
                                <tr><td colspan="7" class="text-center">Belum ada data kendaraan atau riwayat perbaikan.</td></tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

                        <!-- Aggregated pagination -->
                        <?php if ($vehicles_total_pages > 1): ?>
                            <div class="pagination-wrapper">
                                <nav aria-label="Pagination">
                                    <ul class="pagination">
                                        <?php if ($agg_page > 1): ?>
                                            <li class="page-item"><a class="page-link" href="index.php?page=riwayat_perbaikan&group=vehicles&p=<?= $agg_page - 1 ?><?= $keyword ? '&q=' . urlencode($keyword) : '' ?><?= $kendaraan_id ? '&kendaraan=' . (int)$kendaraan_id : '' ?><?= $filter_status ? '&status=' . urlencode($filter_status) : '' ?><?= $filter_tanggal ? '&tanggal=' . urlencode($filter_tanggal) : '' ?><?= $sort ? '&sort=' . urlencode($sort) : '' ?><?= $dir ? '&dir=' . urlencode($dir) : '' ?>">&laquo;</a></li>
                                        <?php endif; ?>
                                        <?php for ($i = max(1, $agg_page - 2); $i <= min($vehicles_total_pages, $agg_page + 2); $i++): ?>
                                            <li class="page-item <?= $i == $agg_page ? 'active' : '' ?>"><a class="page-link" href="index.php?page=riwayat_perbaikan&group=vehicles&p=<?= $i ?><?= $keyword ? '&q=' . urlencode($keyword) : '' ?><?= $kendaraan_id ? '&kendaraan=' . (int)$kendaraan_id : '' ?><?= $filter_status ? '&status=' . urlencode($filter_status) : '' ?><?= $filter_tanggal ? '&tanggal=' . urlencode($filter_tanggal) : '' ?><?= $sort ? '&sort=' . urlencode($sort) : '' ?><?= $dir ? '&dir=' . urlencode($dir) : '' ?>"><?= $i ?></a></li>
                                        <?php endfor; ?>
                                        <?php if ($agg_page < $vehicles_total_pages): ?>
                                            <li class="page-item"><a class="page-link" href="index.php?page=riwayat_perbaikan&group=vehicles&p=<?= $agg_page + 1 ?><?= $keyword ? '&q=' . urlencode($keyword) : '' ?><?= $kendaraan_id ? '&kendaraan=' . (int)$kendaraan_id : '' ?><?= $filter_status ? '&status=' . urlencode($filter_status) : '' ?><?= $filter_tanggal ? '&tanggal=' . urlencode($filter_tanggal) : '' ?><?= $sort ? '&sort=' . urlencode($sort) : '' ?><?= $dir ? '&dir=' . urlencode($dir) : '' ?>">&raquo;</a></li>
                                        <?php endif; ?>
                                    </ul>
                                </nav>
                                <div class="pagination-info">Menampilkan <?= ($vehicles_total_records > 0) ? ($agg_offset + 1) : 0 ?> - <?= min($vehicles_total_records, $agg_offset + $agg_limit) ?> dari <?= $vehicles_total_records ?> kendaraan</div>
                            </div>
                        <?php endif; ?>

    <?php endif; ?>
<?php endif; ?>

<script>
// Live search for Riwayat Perbaikan (repairs and vehicles tables)
(function(){
    function debounce(fn, wait){ var t; return function(){ clearTimeout(t); var a=arguments; t=setTimeout(function(){ fn.apply(null,a); }, wait); }; }
    document.addEventListener('DOMContentLoaded', function(){
        var searchInput = document.querySelector('input[name="search"]');
        if(!searchInput) return;
        // Determine which table is visible based on group param
        var url = new URL(window.location.href);
        var group = url.searchParams.get('group') || 'repairs';
        var table;
        if(group === 'vehicles') {
            table = document.querySelector('.card-body table.table-striped.table-hover');
        } else {
            table = document.querySelector('.card-body table.table');
        }
        if(!table) return;
        var tbody = table.tBodies && table.tBodies[0];
        if(!tbody) return;
        var rows = Array.prototype.slice.call(tbody.querySelectorAll('tr'));

        // Add a no-results row if not exists
        var nores = tbody.querySelector('tr.no-results');
        if(!nores){
            nores = document.createElement('tr');
            nores.className = 'no-results d-none';
            var td = document.createElement('td');
            var headerRow = table.tHead && table.tHead.rows.length ? table.tHead.rows[0] : null;
            var colCount = headerRow ? headerRow.cells.length : (rows.length && rows[0].cells ? rows[0].cells.length : 1);
            td.colSpan = colCount;
            td.className = 'text-center text-muted py-3';
            td.textContent = 'Tidak ada hasil';
            nores.appendChild(td);
            tbody.appendChild(nores);
        }

        var doFilter = debounce(function(){
            var q = String(searchInput.value || '').toLowerCase().trim();
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

        searchInput.addEventListener('input', doFilter);
        searchInput.addEventListener('keydown', function(e){ if(e.key === 'Escape'){ searchInput.value=''; doFilter(); } });

        // Run once on load if field has preset value
        if((searchInput.value || '').trim() !== '') { doFilter(); }
    });
})();
</script>

<?php if ($can_crud && ($action === 'list' || $action === '' || !isset($_GET['action']))): ?>
<!-- Modal: Export Riwayat Perbaikan (pilih tahun atau semua) -->
<div class="modal fade" id="exportPerbaikanModal" tabindex="-1" aria-labelledby="exportPerbaikanLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <form method="get" action="">
                <div class="modal-header">
                    <h5 class="modal-title" id="exportPerbaikanLabel"><i class="fas fa-file-excel me-1"></i> Export Riwayat Perbaikan</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <input type="hidden" name="page" value="riwayat_perbaikan" />
                    <input type="hidden" name="action" value="export_excel" />
                    <?php if (!empty($keyword)): ?>
                        <input type="hidden" name="q" value="<?= htmlspecialchars($keyword) ?>" />
                    <?php endif; ?>
                    <?php if (!empty($kendaraan_id)): ?>
                        <input type="hidden" name="kendaraan" value="<?= (int)$kendaraan_id ?>" />
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
                            <input class="form-check-input" type="radio" name="scope" id="scopeTahun" value="tahun" checked>
                            <label class="form-check-label" for="scopeTahun">Per Tahun</label>
                        </div>
                        <div class="form-check form-check-inline">
                            <input class="form-check-input" type="radio" name="scope" id="scopeAll" value="all">
                            <label class="form-check-label" for="scopeAll">Keseluruhan</label>
                        </div>
                    </div>

                    <?php
                        // Available years for multi-select (distinct years from data)
                        $available_years = [];
                        if ($ys = $conn->prepare("SELECT DISTINCT YEAR(tanggal_perbaikan) AS y FROM riwayat_perbaikan WHERE tanggal_perbaikan IS NOT NULL ORDER BY y DESC")) {
                            $ys->execute();
                            $res = $ys->get_result();
                            while ($row = $res->fetch_assoc()) {
                                if (isset($row['y']) && (int)$row['y'] > 0) { $available_years[] = (int)$row['y']; }
                            }
                            $ys->close();
                        }
                        if (empty($available_years)) { $available_years = [ (int)date('Y') ]; }
                        // Selected years from GET or default current year
                        $selected_years = [];
                        $tahunParam = $_GET['tahun'] ?? null;
                        if (is_array($tahunParam)) {
                            foreach ($tahunParam as $y) { $y = (int)preg_replace('/[^0-9]/','', (string)$y); if ($y > 0) { $selected_years[$y] = true; } }
                        } elseif (is_string($tahunParam) && trim($tahunParam) !== '') {
                            $parts = preg_split('/[\s,;]+/', trim($tahunParam));
                            foreach ($parts as $y) { $y = (int)preg_replace('/[^0-9]/','', (string)$y); if ($y > 0) { $selected_years[$y] = true; } }
                        }
                        if (empty($selected_years)) { $selected_years[(int)date('Y')] = true; }
                    ?>
                    <div class="mb-2" id="tahunGroup">
                        <label for="tahunExport" class="form-label">Pilih Tahun</label>
                        <select multiple class="form-select" id="tahunExport" name="tahun[]">
                            <?php foreach ($available_years as $y): ?>
                                <option value="<?= (int)$y ?>" <?= isset($selected_years[$y]) ? 'selected' : '' ?>><?= (int)$y ?></option>
                            <?php endforeach; ?>
                        </select>
                        <div class="form-text">Pilih satu atau lebih tahun. Pilih "Keseluruhan" untuk semua tahun.</div>
                    </div>
                </div>
                <div class="modal-footer">
                    <input type="hidden" name="all" id="allFlag" value="0" />
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Batal</button>
                    <button type="submit" class="btn btn-primary"><i class="fas fa-download me-1"></i> Export</button>
                </div>
            </form>
        </div>
    </div>
    </div>
    <script>
        (function(){
            const modalEl = document.getElementById('exportPerbaikanModal');
            if (!modalEl) return;
            // Ensure modal appended to body for correct stacking
            modalEl.addEventListener('show.bs.modal', function(){
                if (modalEl.parentNode !== document.body) { document.body.appendChild(modalEl); }
            });
            const scopeTahun = document.getElementById('scopeTahun');
            const scopeAll = document.getElementById('scopeAll');
            const tahunGroup = document.getElementById('tahunGroup');
            const allFlag = document.getElementById('allFlag');
            function sync(){
                const all = scopeAll.checked;
                tahunGroup.style.display = all ? 'none' : '';
                allFlag.value = all ? '1' : '0';
            }
            scopeTahun?.addEventListener('change', sync);
            scopeAll?.addEventListener('change', sync);
            sync();
        })();
    </script>
    <style>
        #exportPerbaikanModal { z-index: 2100; }
        #exportPerbaikanModal .modal-dialog { z-index: 2110; }
        .modal-backdrop.show { z-index: 2050; }
    </style>
<?php endif; ?>

<?php if ($action === 'add' || ($action === 'edit' && $can_crud)): ?>
<script>
// Lightweight dynamic item rows and totals
(function(){
    const tbl = document.getElementById('items-table');
    if (!tbl) return;
    const tbody = tbl.querySelector('tbody');
    const totalEl = document.getElementById('items-total');
    const biayaEl = document.getElementById('biaya');

    function renumber(){
        tbody.querySelectorAll('tr').forEach((tr,idx)=>{
            const no = tr.querySelector('.row-no'); if(no) no.textContent = String(idx+1);
        });
    }
    function recalcRow(tr){
        const qty = parseFloat(tr.querySelector('.qty')?.value||'0')||0;
        const harga = parseFloat(tr.querySelector('.harga')?.value||'0')||0;
        const jumlah = qty*harga;
        const jEl = tr.querySelector('.jumlah'); if (jEl) jEl.value = isFinite(jumlah)? jumlah.toLocaleString('id-ID') : '';
    }
    function recalcTotal(){
        let total = 0;
        tbody.querySelectorAll('tr').forEach(tr=>{
            const qty = parseFloat(tr.querySelector('.qty')?.value||'0')||0;
            const harga = parseFloat(tr.querySelector('.harga')?.value||'0')||0;
            total += qty*harga;
        });
        if (totalEl) totalEl.value = total.toLocaleString('id-ID');
        if (biayaEl) biayaEl.value = total>0 ? total.toFixed(2) : biayaEl.value; // sync to main biaya when items present
    }
    function bind(tr){
        tr.querySelectorAll('.qty,.harga').forEach(inp=>{
            inp.addEventListener('input', ()=>{ recalcRow(tr); recalcTotal(); });
        });
        const btn = tr.querySelector('.remove-row');
        if (btn) btn.addEventListener('click', ()=>{ tr.remove(); renumber(); recalcTotal(); });
    }
    // bind existing
    tbody.querySelectorAll('tr').forEach(tr=>{ bind(tr); recalcRow(tr); });
    recalcTotal();

    document.getElementById('add-item-row')?.addEventListener('click', ()=>{
        const tr = document.createElement('tr');
        tr.innerHTML = `
            <td class="row-no"></td>
            <td><input type="text" name="item_nama[]" class="form-control" placeholder="Nama barang/jasa"></td>
            <td>
                <div class="input-group">
                    <input type="number" name="item_qty[]" class="form-control qty" min="0" placeholder="0">
                    <input type="text" name="item_satuan[]" class="form-control" style="max-width:80px" placeholder="Unit">
                </div>
            </td>
            <td><input type="number" name="item_harga[]" class="form-control harga" min="0" placeholder="0"></td>
            <td><input type="text" class="form-control jumlah" readonly></td>
            <td><button type="button" class="btn btn-danger btn-sm remove-row">Hapus</button></td>`;
        tbody.appendChild(tr);
        renumber(); bind(tr); recalcRow(tr); recalcTotal();
    });
})();
</script>
<!-- Select2 for searchable selects -->
<link href="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/css/select2.min.css" rel="stylesheet" />
<script src="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/js/select2.min.js"></script>
<script>
// Initialize Select2 for kendaraan and teknisi
$(function(){
    if ($.fn.select2) {
        function formatKendaraan(option) {
            if (!option.id) return option.text;
            var no_reg = $(option.element).data('no_reg') || '';
            var $r = $('<span></span>');
            $r.text(option.text + (no_reg ? ' — No.Reg: ' + no_reg : ''));
            return $r;
        }
        $('#kendaraan_id').select2({
            width: '100%',
            placeholder: 'Cari kendaraan (ketik no polisi, merk atau tipe)',
            templateResult: formatKendaraan,
            templateSelection: formatKendaraan,
            escapeMarkup: function(m) { return m; }
        });
        $('#teknisi_user_id').select2({
            width: '100%',
            placeholder: 'Cari teknisi/pengguna',
            allowClear: true
        });
    }
});
</script>
<?php endif; ?>

<?php if ($action === 'view' && !empty($kendaraan_id) && empty($edit_data)): ?>
    <?php
        $vid = (int)$kendaraan_id;
        $vehicle_stmt = $conn->prepare("SELECT id, no_reg, no_polisi, merk, tipe, foto FROM kendaraan WHERE id = ?");
        if ($vehicle_stmt) {
            $vehicle_stmt->bind_param('i', $vid);
            $vehicle_stmt->execute();
            $vehicle_info = $vehicle_stmt->get_result()->fetch_assoc();
            $vehicle_stmt->close();
        } else {
            $vehicle_info = null;
        }
    ?>
    <div class="page-header">
        <h1>
            <i class="fas fa-car"></i> Detail Kendaraan - 
            <?php
                $display_plate = htmlspecialchars((string)($vehicle_info['no_polisi'] ?? ''));
                $display_reg = htmlspecialchars((string)($vehicle_info['no_reg'] ?? ''));
            ?>
            <strong>
                <?= $display_reg ?: $display_plate ?>
                <?php if (!empty($display_reg) && !empty($display_plate)): ?>
                    <small class="text-muted">(Nopol: <?= $display_plate ?>)</small>
                <?php endif; ?>
            </strong>
        </h1>
        <div class="header-actions">
            <a href="index.php?page=riwayat_perbaikan" class="btn btn-light">&larr; Kembali</a>
            <?php if ($can_crud && !empty($vid)): ?>
                <a href="index.php?page=riwayat_perbaikan&action=export_excel&kendaraan_id=<?= (int)$vid ?>" class="btn btn-success">
                    <i class="fas fa-file-excel"></i> Export Excel Kendaraan Ini
                </a>
            <?php endif; ?>
            <?php if ($can_crud): ?><a href="index.php?page=riwayat_perbaikan&kendaraan_id=<?= $vid ?>&action=add" class="btn btn-success"><i class="fas fa-plus"></i> Tambah Perbaikan</a><?php endif; ?>
        </div>
    </div>
    <div class="card mb-4">
        <div class="card-body">
            <div class="vehicle-summary">
                <div><strong>Merk / Tipe:</strong> <?= htmlspecialchars((string)($vehicle_info['merk'] ?? '-')) ?> <?= htmlspecialchars((string)($vehicle_info['tipe'] ?? '')) ?></div>
                <div><strong>No. Registrasi:</strong> <?= htmlspecialchars((string)($vehicle_info['no_reg'] ?? '-')) ?></div>
                <div><strong>No. Polisi:</strong> <?= htmlspecialchars((string)($vehicle_info['no_polisi'] ?? '-')) ?></div>
            </div>
        </div>
    </div>
<?php endif; ?>
