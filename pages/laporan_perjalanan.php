<?php
require_once __DIR__ . '/../includes/auth.php';
// Allow drivers to access their own laporan; require at least 'user' role
require_user();
// Current session role/id for later logic
$current_role = get_current_role();
$current_user_id = get_current_user_id();

// Routing
$action = $_GET['action'] ?? 'list';
$msg = '';

// Inputs
$q = trim($_GET['q'] ?? '');
$pg = max(1, (int)($_GET['pg'] ?? 1));
$limit = 10; $offset = ($pg - 1) * $limit;

// Compute BBM liters from jarak and fuel type
function hitung_bbm_liter($jarak_km, $bahan_bakar, $round_trip = false) {
  // Aturan:
  // - Non-solar (Pertalite/Pertamax/Hybrid/Listrik-none) diasumsikan 12 km/l
  // - Solar/Bio Solar 6 km/l
  // - Pembulatan: ke atas per 1 liter, minimum 1 bila jarak > 0
  if ($jarak_km === null) return null;
  $jarak = (float)$jarak_km;
  if ($round_trip) { $jarak *= 2.0; }
  $jenis = strtolower((string)$bahan_bakar);
  $is_solar = ($jenis === 'solar') || (strpos($jenis, 'bio') !== false);
  $km_per_l = $is_solar ? 6.0 : 12.0;
  if ($km_per_l <= 0) return null;
  $raw = $jarak / $km_per_l; // liters
  $rounded = (float)ceil($raw);
  if ($rounded == 0 && $raw > 0) $rounded = 1.0;
  return $rounded;
}

// Early: handle Excel export with support for multi-month or yearly selection
if ($action === 'export_excel') {
  // Parse mode and inputs
  $mode = $_GET['mode'] ?? 'bulan';
  $bulan_inputs = $_GET['bulan'] ?? '';
  $tahun_input = trim($_GET['tahun'] ?? '');

  $bulan_list = [];
  $tahun = null;

  if ($mode === 'tahun' && $tahun_input !== '' && preg_match('/^\d{4}$/', $tahun_input)) {
    $tahun = $tahun_input;
  } else {
    if (is_array($bulan_inputs)) {
      foreach ($bulan_inputs as $b) {
        $b = trim((string)$b);
        if ($b !== '' && preg_match('/^\d{4}-\d{2}$/', $b)) {
          $bulan_list[] = $b;
        }
      }
    } else if (is_string($bulan_inputs) && $bulan_inputs !== '' && preg_match('/^\d{4}-\d{2}$/', $bulan_inputs)) {
      $bulan_list[] = $bulan_inputs;
    }
    if (empty($bulan_list)) {
      // default current month if not supplied/invalid
      $bulan_list[] = date('Y-m');
    }
    // normalize and unique
    $bulan_list = array_values(array_unique($bulan_list));
    sort($bulan_list);
  }

  // Load library
  $autoload = __DIR__ . '/../vendor/autoload.php';
  if (!file_exists($autoload)) {
    $msg = '<div class="alert alert-danger">Library PhpSpreadsheet tidak ditemukan.</div>';
  } else {
    require_once $autoload;

    // Build query and params
    $paramsE = [];
    $typesE = '';
    if ($tahun !== null) {
      $start = $tahun . '-01-01';
      $end = ((int)$tahun + 1) . '-01-01';
      $sql = "SELECT lp.id, lp.tanggal, lp.uraian_kegiatan, lp.route, lp.jarak_km, k.no_reg, k.no_polisi, k.merk, k.tipe, k.bahan_bakar, p.nama_lengkap, p.pangkat, p.nrp_nip FROM laporan_perjalanan lp JOIN kendaraan k ON lp.kendaraan_id = k.id LEFT JOIN pengguna p ON lp.pengguna_id = p.id WHERE lp.tanggal >= ? AND lp.tanggal < ? ORDER BY lp.tanggal ASC, lp.id ASC";
      $paramsE = [$start, $end];
      $typesE = 'ss';
    } else if (count($bulan_list) === 1) {
      $bulan = $bulan_list[0];
      $bulan_start = $bulan . '-01';
      $dt = DateTime::createFromFormat('Y-m-d', $bulan_start);
      $dt->modify('first day of next month');
      $bulan_end = $dt->format('Y-m-d');
      $sql = "SELECT lp.id, lp.tanggal, lp.uraian_kegiatan, lp.route, lp.jarak_km, k.no_reg, k.no_polisi, k.merk, k.tipe, k.bahan_bakar, p.nama_lengkap, p.pangkat, p.nrp_nip FROM laporan_perjalanan lp JOIN kendaraan k ON lp.kendaraan_id = k.id LEFT JOIN pengguna p ON lp.pengguna_id = p.id WHERE lp.tanggal >= ? AND lp.tanggal < ? ORDER BY lp.tanggal ASC, lp.id ASC";
      $paramsE = [$bulan_start, $bulan_end];
      $typesE = 'ss';
    } else {
      // Multiple non-contiguous months: filter by YYYY-MM using DATE_FORMAT
      $placeholders = implode(',', array_fill(0, count($bulan_list), '?'));
      $sql = "SELECT lp.id, lp.tanggal, lp.uraian_kegiatan, lp.route, lp.jarak_km, k.no_reg, k.no_polisi, k.merk, k.tipe, k.bahan_bakar, p.nama_lengkap, p.pangkat, p.nrp_nip FROM laporan_perjalanan lp JOIN kendaraan k ON lp.kendaraan_id = k.id LEFT JOIN pengguna p ON lp.pengguna_id = p.id WHERE DATE_FORMAT(lp.tanggal, '%Y-%m') IN ($placeholders) ORDER BY lp.tanggal ASC, lp.id ASC";
      $paramsE = $bulan_list;
      $typesE = str_repeat('s', count($bulan_list));
    }

    $stmtE = $mysqli->prepare($sql);
    $bind = [$typesE]; foreach ($paramsE as $i=>$_) { $bind[] = &$paramsE[$i]; } call_user_func_array([$stmtE,'bind_param'],$bind);
    $stmtE->execute();
    $resE = $stmtE->get_result();
    $rows_export = $resE ? $resE->fetch_all(MYSQLI_ASSOC) : [];
    $stmtE->close();

    $spreadsheet = new \PhpOffice\PhpSpreadsheet\Spreadsheet();
    // Preload today's surat_tugas for drivers for quick action checks
    $surat_map = [];
    if ($current_role === 'driver' && $current_user_id && db_table_exists('surat_tugas')) {
      $today = date('Y-m-d');
      $stQ = $mysqli->prepare("SELECT id, kendaraan_id, status, tanggal_berangkat FROM surat_tugas WHERE pengguna_id = ? AND DATE(tanggal_berangkat) = ? AND status IN ('Disetujui','Dalam Perjalanan')");
      $stQ->bind_param('is', $current_user_id, $today);
      $stQ->execute();
      $stRes = $stQ->get_result();
      while ($s = $stRes->fetch_assoc()) { $surat_map[(int)$s['kendaraan_id']] = $s; }
      $stQ->close();
    }
    $sheet = $spreadsheet->getActiveSheet();
    $sheet->setTitle('Laporan');

    // Month name in Indonesian
    $nama_bulan_map = ['01'=>'JANUARI','02'=>'FEBRUARI','03'=>'MARET','04'=>'APRIL','05'=>'MEI','06'=>'JUNI','07'=>'JULI','08'=>'AGUSTUS','09'=>'SEPTEMBER','10'=>'OKTOBER','11'=>'NOVEMBER','12'=>'DESEMBER'];

    // Build periode label and filename
    $periode_label = '';
    $filename = 'laporan_perjalanan_';
    if ($tahun !== null) {
      $periode_label = 'TAHUN ' . $tahun;
      $filename .= $tahun . '.xlsx';
    } else if (count($bulan_list) === 1) {
      $bulan = $bulan_list[0];
      $bulan_num = substr($bulan,5,2); $tahun_num = substr($bulan,0,4);
      $nama_bulan = $nama_bulan_map[$bulan_num] ?? strtoupper($bulan_num);
      $periode_label = 'BULAN ' . $nama_bulan . ' ' . $tahun_num;
      $filename .= $bulan . '.xlsx';
    } else {
      // multiple months
      $years = array_values(array_unique(array_map(fn($b)=>substr($b,0,4), $bulan_list)));
      if (count($years) === 1) {
        $y = $years[0];
        $bulan_names = array_map(function($b) use ($nama_bulan_map) { $m = substr($b,5,2); return $nama_bulan_map[$m] ?? strtoupper($m); }, $bulan_list);
        $periode_label = 'BULAN ' . implode(', ', $bulan_names) . ' ' . $y;
      } else {
        $min = $bulan_list[0]; $max = $bulan_list[count($bulan_list)-1];
        $min_label = ($nama_bulan_map[substr($min,5,2)] ?? substr($min,5,2)) . ' ' . substr($min,0,4);
        $max_label = ($nama_bulan_map[substr($max,5,2)] ?? substr($max,5,2)) . ' ' . substr($max,0,4);
        $periode_label = 'PERIODE ' . $min_label . ' s.d. ' . $max_label;
      }
      $min = $bulan_list[0]; $max = $bulan_list[count($bulan_list)-1];
      $filename .= $min . '_' . $max . '.xlsx';
    }

    // Header layout
    $sheet->mergeCells('A1:D1');
    $sheet->mergeCells('A2:D2');
    $sheet->mergeCells('A4:K4');
    $sheet->mergeCells('A5:K5');
    $sheet->mergeCells('A6:K6');
    $sheet->setCellValue('A1','KEMENTERIAN PERTAHANAN REPUBLIK INDONESIA');
    $sheet->setCellValue('A2','SPBT KEMHAN CAWANG');
    $sheet->setCellValue('A5','LAPORAN PENGGUNAAN BBM INTENSITAS PERTALITE SPBT KEMHAN CAWANG');
    $sheet->setCellValue('A6', $periode_label);

    // Draw a bottom border under row2 (institution line)
    $sheet->getStyle('A2')->getFont()->setBold(true);
    $sheet->getStyle('A1')->getFont()->setBold(true);
    $sheet->getStyle('A5:A6')->getFont()->setBold(true);
    $sheet->getStyle('A1:A6')->getAlignment()->setHorizontal(\PhpOffice\PhpSpreadsheet\Style\Alignment::HORIZONTAL_CENTER);
    $sheet->getStyle('A1:A6')->getAlignment()->setVertical(\PhpOffice\PhpSpreadsheet\Style\Alignment::VERTICAL_CENTER);
    $sheet->getRowDimension(1)->setRowHeight(18);
    $sheet->getRowDimension(2)->setRowHeight(18);
    $sheet->getRowDimension(5)->setRowHeight(20);
    $sheet->getRowDimension(6)->setRowHeight(18);
    $sheet->getStyle('A2:D2')->getBorders()->getBottom()->setBorderStyle(\PhpOffice\PhpSpreadsheet\Style\Border::BORDER_THIN);

    // Table header (sesuai template):
    // - Baris 8: judul kolom, dengan E8:F8 merged (DUKUNGAN JENIS BBM)
    // - Baris 9: sub-judul untuk E9 (PERTALITE) dan F9 (BIO SOLAR)
    // - Baris 10: nomor kolom 1..9
    $headerRowTop = 8;
    $headerRowSub = 9;
    $numberRow = 10;

    // Merge kolom tanpa subjudul
    foreach (['A','B','C','D','G','H','I'] as $c) {
      $sheet->mergeCells($c.$headerRowTop.':'.$c.$headerRowSub);
    }
    // Merge judul E8:F8
    $sheet->mergeCells('E'.$headerRowTop.':F'.$headerRowTop);

    // Set header atas
    $sheet->setCellValue('A'.$headerRowTop,'NO.');
    $sheet->setCellValue('B'.$headerRowTop,'TANGGAL');
    $sheet->setCellValue('C'.$headerRowTop,"URAIAN GIAT/PERHITUNGAN\n( RAN x KEBUTUHAN SESUAI JARAK x HB )\nKONSUMSI BBM RAN SESUAI INDEKS");
    $sheet->setCellValue('D'.$headerRowTop,'JENIS/\nNO. RAN');
    $sheet->setCellValue('E'.$headerRowTop,'DUKUNGAN JENIS BBM');
    $sheet->setCellValue('G'.$headerRowTop,'NAMA/PANGKAT PENERIMA');
    $sheet->setCellValue('H'.$headerRowTop,'TANDA\nTANGAN');
    $sheet->setCellValue('I'.$headerRowTop,'KET');

    // Sub-header
    $sheet->setCellValue('E'.$headerRowSub,'PERTALITE (liter)');
    $sheet->setCellValue('F'.$headerRowSub,'BIO SOLAR (liter)');

    // Baris nomor 1..9
    $ncols = ['A','B','C','D','E','F','G','H','I'];
    foreach ($ncols as $i => $c) { $sheet->setCellValue($c.$numberRow, (string)($i+1)); }

    // Styling header
    $sheet->getStyle('A'.$headerRowTop.':I'.$headerRowSub)->getFont()->setBold(true);
    $sheet->getStyle('A'.$headerRowTop.':I'.$numberRow)->getAlignment()->setHorizontal(\PhpOffice\PhpSpreadsheet\Style\Alignment::HORIZONTAL_CENTER);
    $sheet->getStyle('A'.$headerRowTop.':I'.$numberRow)->getAlignment()->setVertical(\PhpOffice\PhpSpreadsheet\Style\Alignment::VERTICAL_CENTER);
    $sheet->getStyle('A'.$headerRowTop.':I'.$headerRowSub)->getAlignment()->setWrapText(true);

    // Data rows
    $hari_map = [ 'Sun'=>'Minggu','Mon'=>'Senin','Tue'=>'Selasa','Wed'=>'Rabu','Thu'=>'Kamis','Fri'=>'Jumat','Sat'=>'Sabtu' ];
    $rnum = $numberRow + 1; $no=1;
    foreach ($rows_export as $r) {
      $bbm = hitung_bbm_liter($r['jarak_km'], $r['bahan_bakar'], true);
      $isSolar = false; $bb = strtolower((string)$r['bahan_bakar']);
      if ($bb === 'solar' || strpos($bb,'bio') !== false) { $isSolar = true; }

      // A: No
      $sheet->setCellValueExplicit('A'.$rnum, $no, \PhpOffice\PhpSpreadsheet\Cell\DataType::TYPE_NUMERIC);
      // B: Tanggal + hari
      $tgl = $r['tanggal']; $bval = $tgl;
      if ($tgl) { $dt = \DateTime::createFromFormat('Y-m-d', $tgl); if ($dt) { $bval = ($hari_map[$dt->format('D')] ?? $dt->format('D')) . "\n" . $dt->format('d/m/Y'); }}
      $sheet->setCellValue('B'.$rnum, $bval);
      // C: Uraian kegiatan + formula otomatis
  $kmIndex = $isSolar ? 6.0 : 12.0;
      $fmt = function($n){ return number_format((float)$n, 0, ',', '.'); };
      $uraianText = (string)($r['uraian_kegiatan'] ?? '');
      $jarakNum = isset($r['jarak_km']) ? (float)$r['jarak_km'] : null;
      $line2 = $jarakNum !== null
        ? ('1 ran (' . $fmt($jarakNum) . ' km x 2) x 1 HB = ' . ($bbm!==null ? $fmt($bbm) : '-') . ' liter')
        : '1 ran x 1 HB = -';
      // Baris ketiga hanya angka indeks sesuai contoh (tanpa deskripsi)
      $line3 = $fmt($kmIndex);
      $uraianBlock = trim($uraianText . "\n" . $line2 . "\n" . $line3);
      $sheet->setCellValue('C'.$rnum, $uraianBlock);
      // D: Jenis/No.Ran (Merk Tipe + baris No.Reg/No.Polisi)
      $jenisRan = trim($r['merk'].' '.($r['tipe'] ?: ''));
      $noRan = ($r['no_reg'] ?: $r['no_polisi']);
      $sheet->setCellValue('D'.$rnum, trim($jenisRan . (strlen($noRan)?"\n$noRan":'')));
      // E/F: BBM split
      $sheet->setCellValue('E'.$rnum, (!$isSolar && $bbm!==null) ? $bbm : '');
      $sheet->setCellValue('F'.$rnum, ($isSolar && $bbm!==null) ? $bbm : '');
      // G: Nama/Pangkat Penerima
      $np = trim(($r['pangkat'] ?: '')); $nrp = trim(($r['nrp_nip'] ?: ''));
      $line2 = trim($np . (strlen($np)&&strlen($nrp)?' ':'') . $nrp);
      $sheet->setCellValue('G'.$rnum, trim(($r['nama_lengkap'] ?: '') . (strlen($line2)?"\n$line2":'')));
      // H: Tanda tangan (kosong), I: Ket (kosong)
      $sheet->setCellValue('H'.$rnum, '');
      $sheet->setCellValue('I'.$rnum, '');
      $rnum++; $no++;
    }

    // Bungkus teks pada kolom multi-baris dan rapikan alignment untuk area data
    if ($rnum > $numberRow + 1) {
      $dataStart = $numberRow + 1; $dataEnd = $rnum - 1;
      $sheet->getStyle('C'.$dataStart.':C'.$dataEnd)->getAlignment()->setWrapText(true);
      $sheet->getStyle('D'.$dataStart.':D'.$dataEnd)->getAlignment()->setWrapText(true);
      $sheet->getStyle('G'.$dataStart.':G'.$dataEnd)->getAlignment()->setWrapText(true);
      $sheet->getStyle('A'.$dataStart.':I'.$dataEnd)->getAlignment()->setVertical(\PhpOffice\PhpSpreadsheet\Style\Alignment::VERTICAL_TOP);
    }

    // Borders for table
    if ($rnum > $numberRow + 1) {
      $tableRange = 'A'.$headerRowTop.':I'.($rnum-1);
      $sheet->getStyle($tableRange)->getBorders()->getAllBorders()->setBorderStyle(\PhpOffice\PhpSpreadsheet\Style\Border::BORDER_THIN);
      // Align numeric columns center
      $sheet->getStyle('A'.($headerRowTop).':A'.($rnum-1))->getAlignment()->setHorizontal(\PhpOffice\PhpSpreadsheet\Style\Alignment::HORIZONTAL_CENTER);
      $sheet->getStyle('B'.($headerRowSub).':B'.($rnum-1))->getAlignment()->setHorizontal(\PhpOffice\PhpSpreadsheet\Style\Alignment::HORIZONTAL_CENTER);
      $sheet->getStyle('E'.($headerRowSub+1).':F'.($rnum-1))->getAlignment()->setHorizontal(\PhpOffice\PhpSpreadsheet\Style\Alignment::HORIZONTAL_CENTER);
    }
    // Column widths
    $widths = [5,16,40,24,12,12,26,14,10];
    $c='A'; foreach ($widths as $w){ $sheet->getColumnDimension($c)->setWidth($w); $c++; }

    // Do not freeze header (no sticky header in Excel)
    // $sheet->freezePane('A'.($headerRow+1));

    // Output
    // Activity log for export
    if (function_exists('log_activity')) {
      $rowsCount = is_array($rows_export) ? count($rows_export) : 0;
      $la = 'Export Laporan Perjalanan ' . $periode_label . ' (' . $rowsCount . ' baris)';
      log_activity('EXPORT_LAPORAN_PERJALANAN', $la);
    }
    if (function_exists('ob_get_level')) { while (ob_get_level()>0) { @ob_end_clean(); } }
    if (function_exists('ini_get') && ini_get('zlib.output_compression')) { @ini_set('zlib.output_compression','Off'); }
    header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
    header('Content-Disposition: attachment; filename="'.$filename.'"');
    header('Cache-Control: max-age=0');
    $writer = \PhpOffice\PhpSpreadsheet\IOFactory::createWriter($spreadsheet,'Xlsx');
    $writer->save('php://output');
    exit;
  }
}

// Lightweight AJAX: lookup pengguna default by kendaraan
if ($action === 'lookup_pengguna') {
  header('Content-Type: application/json');
  $kendaraan_id = (int)($_GET['kendaraan_id'] ?? 0);
  if ($kendaraan_id <= 0) {
    echo json_encode(['success'=>false, 'error'=>'kendaraan_id invalid']);
    exit;
  }
  $stmtL = $mysqli->prepare("SELECT k.pengguna_id, p.nama_lengkap, p.jabatan, p.status_aktif FROM kendaraan k LEFT JOIN pengguna p ON p.id = k.pengguna_id WHERE k.id = ? LIMIT 1");
  $stmtL->bind_param('i', $kendaraan_id);
  $stmtL->execute();
  $resL = $stmtL->get_result();
  $rowL = $resL ? $resL->fetch_assoc() : null;
  $stmtL->close();
  if (!$rowL) {
    echo json_encode(['success'=>false, 'error'=>'kendaraan not found']);
    exit;
  }
  echo json_encode([
    'success' => true,
    'pengguna_id' => isset($rowL['pengguna_id']) ? (int)$rowL['pengguna_id'] : null,
    'nama' => $rowL['nama_lengkap'] ?? null,
    'jabatan' => $rowL['jabatan'] ?? null,
    'status_aktif' => $rowL['status_aktif'] ?? null,
  ]);
  exit;
}

// Handle POST
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
  if (!validate_csrf_token($_POST['csrf_token'] ?? '')) {
    http_response_code(400);
    echo 'Invalid CSRF token';
    exit;
  }
  // Driver actions: start/finish surat_tugas, or save edit to laporan
  if (!empty($_POST['start_surat_id'])) {
    $sid = (int)$_POST['start_surat_id'];
    if ($sid > 0 && db_table_exists('surat_tugas')) {
      $st = $mysqli->prepare("SELECT id, kendaraan_id, pengguna_id, tanggal_berangkat, nomor_surat, tujuan, keperluan, laporan_perjalanan, status FROM surat_tugas WHERE id = ? LIMIT 1");
      $st->bind_param('i', $sid);
      $st->execute();
      $srow = $st->get_result()->fetch_assoc();
      $st->close();
      if ($srow && ((int)$srow['pengguna_id'] === (int)$current_user_id || can_admin())) {
        $newStatus = 'Dalam Perjalanan';
        $up = $mysqli->prepare("UPDATE surat_tugas SET status = ?, updated_at = NOW() WHERE id = ?");
        $up->bind_param('si', $newStatus, $sid);
        $up->execute();
        $up->close();
        if (function_exists('log_activity')) log_activity('SURAT_START', "Surat tugas id={$sid} set to Dalam Perjalanan oleh pengguna {$current_user_id}");

        // Ensure a laporan_perjalanan exists for this surat (use tanggal_berangkat); create if missing.
        $lp_id = null;
        $tanggal = $srow['tanggal_berangkat'] ?? null;
        if ($tanggal) {
          $chk = $mysqli->prepare("SELECT id FROM laporan_perjalanan WHERE kendaraan_id = ? AND tanggal = ? LIMIT 1");
          $chk->bind_param('is', $srow['kendaraan_id'], $tanggal);
          $chk->execute();
          $rowc = $chk->get_result()->fetch_assoc();
          $chk->close();
          if ($rowc && !empty($rowc['id'])) {
            $lp_id = (int)$rowc['id'];
          } else {
            $uraian = trim($srow['laporan_perjalanan'] ?? $srow['keperluan'] ?? $srow['nomor_surat'] ?? ('Surat Tugas ' . ($srow['id'] ?? '')));
            $route = $srow['tujuan'] ?? '';
            if ($ins = $mysqli->prepare("INSERT INTO laporan_perjalanan (tanggal, kendaraan_id, pengguna_id, uraian_kegiatan, route, jarak_km, created_at) VALUES (?, ?, ?, ?, ?, NULL, NOW())")) {
              $ins->bind_param('siiss', $tanggal, $srow['kendaraan_id'], $srow['pengguna_id'], $uraian, $route);
              if ($ins->execute()) {
                $lp_id = (int)$mysqli->insert_id;
              }
              $ins->close();
            }
          }
        }
        if ($lp_id) {
          header('Location: index.php?page=laporan_perjalanan&action=edit&id=' . $lp_id);
          exit;
        }
      }
    }
    header('Location: index.php?page=laporan_perjalanan');
    exit;
  }
  if (!empty($_POST['finish_surat_id'])) {
    $sid = (int)$_POST['finish_surat_id'];
    if ($sid > 0 && db_table_exists('surat_tugas')) {
      $st = $mysqli->prepare("SELECT id, kendaraan_id, pengguna_id, tanggal_berangkat, tanggal_kembali, nomor_surat, tujuan, keperluan, laporan_perjalanan, status FROM surat_tugas WHERE id = ? LIMIT 1");
      $st->bind_param('i', $sid);
      $st->execute();
      $srow = $st->get_result()->fetch_assoc();
      $st->close();
      if ($srow && ((int)$srow['pengguna_id'] === (int)$current_user_id || can_admin())) {
        $newStatus = 'Selesai';
        $up = $mysqli->prepare("UPDATE surat_tugas SET status = ?, updated_at = NOW() WHERE id = ?");
        $up->bind_param('si', $newStatus, $sid);
        $up->execute();
        $up->close();
        if (function_exists('log_activity')) log_activity('SURAT_FINISH', "Surat tugas id={$sid} set to Selesai oleh pengguna {$current_user_id}");
        // Ensure a laporan_perjalanan exists for this surat (tanggal_berangkat)
        $lp_id = null;
        $tanggal = $srow['tanggal_berangkat'] ?? null;
        if ($tanggal) {
          $chk = $mysqli->prepare("SELECT id FROM laporan_perjalanan WHERE kendaraan_id = ? AND tanggal = ? LIMIT 1");
          $chk->bind_param('is', $srow['kendaraan_id'], $tanggal);
          $chk->execute();
          $rowc = $chk->get_result()->fetch_assoc();
          $chk->close();
          if ($rowc && !empty($rowc['id'])) {
            $lp_id = (int)$rowc['id'];
          } else {
            // create a minimal laporan_perjalanan row
            $uraian = trim($srow['laporan_perjalanan'] ?? $srow['keperluan'] ?? $srow['nomor_surat'] ?? ('Surat Tugas ' . ($srow['id'] ?? '')));
            $route = $srow['tujuan'] ?? '';
            if ($ins = $mysqli->prepare("INSERT INTO laporan_perjalanan (tanggal, kendaraan_id, pengguna_id, uraian_kegiatan, route, jarak_km, created_at) VALUES (?, ?, ?, ?, ?, NULL, NOW())")) {
              $ins->bind_param('siiss', $tanggal, $srow['kendaraan_id'], $srow['pengguna_id'], $uraian, $route);
              if ($ins->execute()) {
                $lp_id = (int)$mysqli->insert_id;
              }
              $ins->close();
            }
          }
        }
        if ($lp_id) {
          header('Location: index.php?page=laporan_perjalanan&action=edit&id=' . $lp_id);
          exit;
        }
      }
    }
    header('Location: index.php?page=laporan_perjalanan');
    exit;
  }
  if (!empty($_POST['edit_id'])) {
    $edit_id = (int)$_POST['edit_id'];
    $tanggal = trim($_POST['tanggal'] ?? '');
    $kendaraan_id = (int)($_POST['kendaraan_id'] ?? 0);
    $pengguna_id = (int)($_POST['pengguna_id'] ?? 0);
    $uraian = trim($_POST['uraian_kegiatan'] ?? '');
    $route = trim($_POST['route'] ?? '');
    $jarak_in = trim($_POST['jarak_km'] ?? '');
    $jarak_val = ($jarak_in === '' ? null : (float)$jarak_in);
    // Permission: drivers may only edit reports for vehicles they are responsible for
    $allowed = false;
    if (can_admin()) $allowed = true;
    else if ($current_role === 'driver' && $current_user_id) {
      // ensure kendaraan.pengguna_id or laporan.pengguna_id equals current user
      $stchk = $mysqli->prepare("SELECT lp.pengguna_id, k.pengguna_id AS kend_pengguna FROM laporan_perjalanan lp JOIN kendaraan k ON lp.kendaraan_id = k.id WHERE lp.id = ? LIMIT 1");
      $stchk->bind_param('i', $edit_id);
      $stchk->execute();
      $stchk_row = $stchk->get_result()->fetch_assoc();
      $stchk->close();
      if ($stchk_row && ((int)$stchk_row['pengguna_id'] === (int)$current_user_id || (int)$stchk_row['kend_pengguna'] === (int)$current_user_id)) $allowed = true;
    }
    if ($allowed) {
      if ($jarak_val === null) {
        $upq = $mysqli->prepare("UPDATE laporan_perjalanan SET tanggal = ?, kendaraan_id = ?, pengguna_id = ?, uraian_kegiatan = ?, route = ?, jarak_km = NULL, updated_at = NOW() WHERE id = ?");
        $upq->bind_param('siissi', $tanggal, $kendaraan_id, $pengguna_id, $uraian, $route, $edit_id);
      } else {
        $upq = $mysqli->prepare("UPDATE laporan_perjalanan SET tanggal = ?, kendaraan_id = ?, pengguna_id = ?, uraian_kegiatan = ?, route = ?, jarak_km = ?, updated_at = NOW() WHERE id = ?");
        $upq->bind_param('siissdi', $tanggal, $kendaraan_id, $pengguna_id, $uraian, $route, $jarak_val, $edit_id);
      }
      if ($upq) { $ok = $upq->execute(); $upq->close(); if ($ok && function_exists('log_activity')) log_activity('UPDATE_LAPORAN', "Update laporan id={$edit_id} oleh pengguna {$current_user_id}"); }
    }
    header('Location: index.php?page=laporan_perjalanan&updated=1');
    exit;
  }
  if ($action === 'create') {
    // Create new laporan perjalanan
    $tanggal = trim($_POST['tanggal'] ?? '');
    $kendaraan_id = (int)($_POST['kendaraan_id'] ?? 0);
    $pengguna_id = (int)($_POST['pengguna_id'] ?? 0);
    $uraian = trim($_POST['uraian_kegiatan'] ?? '');
    $route = trim($_POST['route'] ?? '');
    $jarak_in = trim($_POST['jarak_km'] ?? '');
    $jarak_val = ($jarak_in === '' ? null : (float)$jarak_in);

    // Basic validation
    $errors = [];
    if ($tanggal === '') $errors[] = 'Tanggal wajib diisi';
    if ($kendaraan_id <= 0) $errors[] = 'Kendaraan wajib dipilih';
    if ($pengguna_id <= 0) $errors[] = 'Pengemudi (pengguna) wajib dipilih';
    if ($uraian === '') $errors[] = 'Uraian kegiatan wajib diisi';
    if ($route === '') $errors[] = 'Route wajib diisi';

    if (!empty($errors)) {
      $msg = '<div class="alert alert-danger"><ul class="m-0">' . implode('', array_map(fn($e)=>'<li>'.htmlspecialchars($e).'</li>', $errors)) . '</ul></div>';
    } else {
      if ($jarak_val === null) {
        $stmtC = $mysqli->prepare("INSERT INTO laporan_perjalanan (tanggal, kendaraan_id, pengguna_id, uraian_kegiatan, route, jarak_km, created_at) VALUES (?, ?, ?, ?, ?, NULL, NOW())");
        $stmtC->bind_param('siiss', $tanggal, $kendaraan_id, $pengguna_id, $uraian, $route);
      } else {
        $stmtC = $mysqli->prepare("INSERT INTO laporan_perjalanan (tanggal, kendaraan_id, pengguna_id, uraian_kegiatan, route, jarak_km, created_at) VALUES (?, ?, ?, ?, ?, ?, NOW())");
        $stmtC->bind_param('siissd', $tanggal, $kendaraan_id, $pengguna_id, $uraian, $route, $jarak_val);
      }
      if ($stmtC->execute()) {
        $stmtC->close();
        if (function_exists('log_activity')) {
          // Build kendaraan label (prefer No.Reg then No.Polisi)
          $vehLabel = '';
          if ($stVK = $mysqli->prepare("SELECT COALESCE(NULLIF(TRIM(no_reg),''), NULLIF(TRIM(no_polisi),'')) AS label FROM kendaraan WHERE id = ?")) {
        $stVK->bind_param('i', $kendaraan_id);
        $stVK->execute();
        $vehLabel = (string)(($stVK->get_result()->fetch_assoc()['label'] ?? ''));
        $stVK->close();
          }
          $vehText = $vehLabel !== '' ? ('No.Reg ' . $vehLabel) : ('ID ' . $kendaraan_id);

          // Ambil nama_lengkap pengguna
          $namaPengguna = '';
          if ($stPU = $mysqli->prepare("SELECT nama_lengkap FROM pengguna WHERE id = ?")) {
        $stPU->bind_param('i', $pengguna_id);
        $stPU->execute();
        $namaPengguna = (string)(($stPU->get_result()->fetch_assoc()['nama_lengkap'] ?? ''));
        $stPU->close();
          }
          $penggunaText = $namaPengguna !== '' ? $namaPengguna : ('ID ' . $pengguna_id);

          $desc = "Tambah laporan perjalanan untuk kendaraan $vehText (pengguna $penggunaText)";
          log_activity('CREATE_LAPORAN_PERJALANAN', $desc);
        }
        header('Location: index.php?page=laporan_perjalanan&created=1');
        exit;
      } else {
        $msg = '<div class="alert alert-danger">Gagal menyimpan laporan: ' . htmlspecialchars($mysqli->error) . '</div>';
      }
      $stmtC->close();
    }
  } else {
    // Update jarak inline
    $id = (int)($_POST['id'] ?? 0);
    $jarak = trim($_POST['jarak_km'] ?? '');
    if ($jarak === '') {
      $stmtU = $mysqli->prepare("UPDATE laporan_perjalanan SET jarak_km = NULL, updated_at = NOW() WHERE id = ?");
      $stmtU->bind_param('i', $id);
      $ok = $stmtU->execute();
      $stmtU->close();
      if ($ok && function_exists('log_activity')) {
        // Fetch kendaraan label for this laporan
        $vehText = '';
        if ($stL = $mysqli->prepare("SELECT COALESCE(NULLIF(TRIM(k.no_reg),''), NULLIF(TRIM(k.no_polisi),'')) AS label FROM laporan_perjalanan lp JOIN kendaraan k ON lp.kendaraan_id = k.id WHERE lp.id = ?")) {
          $stL->bind_param('i', $id);
          $stL->execute();
          $lab = (string)(($stL->get_result()->fetch_assoc()['label'] ?? ''));
          $stL->close();
          $vehText = $lab !== '' ? ('No.Reg ' . $lab) : '';
        }
        $msgLog = "Set jarak_km NULL laporan ID $id" . ($vehText!=='' ? " ($vehText)" : '');
        log_activity('UPDATE_JARAK_LAPORAN', $msgLog);
      }
    } else {
      $jarak_val = (float)$jarak;
      $stmtU = $mysqli->prepare("UPDATE laporan_perjalanan SET jarak_km = ?, updated_at = NOW() WHERE id = ?");
      $stmtU->bind_param('di', $jarak_val, $id);
      $ok = $stmtU->execute();
      $stmtU->close();
      if ($ok && function_exists('log_activity')) {
        // Fetch kendaraan label for this laporan
        $vehText = '';
        if ($stL = $mysqli->prepare("SELECT COALESCE(NULLIF(TRIM(k.no_reg),''), NULLIF(TRIM(k.no_polisi),'')) AS label FROM laporan_perjalanan lp JOIN kendaraan k ON lp.kendaraan_id = k.id WHERE lp.id = ?")) {
          $stL->bind_param('i', $id);
          $stL->execute();
          $lab = (string)(($stL->get_result()->fetch_assoc()['label'] ?? ''));
          $stL->close();
          $vehText = $lab !== '' ? ('No.Reg ' . $lab) : '';
        }
        $msgLog = "Ubah jarak_km laporan ID $id menjadi $jarak_val" . ($vehText!=='' ? " ($vehText)" : '');
        log_activity('UPDATE_JARAK_LAPORAN', $msgLog);
      }
    }
    header('Location: index.php?page=laporan_perjalanan');
    exit;
  }
}

// List view data only when listing
// Driver-assigned surat list (sidebar target)
if ($action === 'assigned') {
  if ($current_role !== 'driver' || !$current_user_id) {
    header('Location: index.php'); exit;
  }
  // Fetch surat_tugas assigned to this driver
  $stmt = $mysqli->prepare("SELECT st.id, st.nomor_surat, st.tanggal_berangkat, st.tanggal_kembali, st.kendaraan_id, st.pengguna_id, st.tujuan, st.status, k.no_reg, k.no_polisi, k.merk, k.tipe FROM surat_tugas st JOIN kendaraan k ON st.kendaraan_id = k.id WHERE st.pengguna_id = ? ORDER BY st.tanggal_berangkat ASC LIMIT 100");
  $stmt->bind_param('i', $current_user_id);
  $stmt->execute();
  $assigned = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
  $stmt->close();

  ?>
  <div class="page-header">
    <h1><i class="fas fa-route"></i> Surat Tugas - Tugas Saya</h1>
  </div>
  <div class="card">
    <div class="card-body">
      <div class="table-responsive">
        <table class="table align-middle">
          <thead>
            <tr>
              <th>Tanggal Berangkat</th>
              <th>Nomor Surat</th>
              <th>Kendaraan</th>
              <th>Tujuan</th>
              <th>Status</th>
              <th>Aksi</th>
            </tr>
          </thead>
          <tbody>
          <?php if (empty($assigned)): ?>
            <tr><td colspan="6" class="text-center text-muted">Belum ada surat tugas</td></tr>
          <?php else: foreach ($assigned as $s):
            $today = date('Y-m-d');
            $editable = ($s['tanggal_berangkat'] !== null && $today >= $s['tanggal_berangkat']);
            $vehLabel = htmlspecialchars(($s['no_reg'] ?: $s['no_polisi']) . ' - ' . $s['merk'] . ($s['tipe'] ? ' '.$s['tipe'] : ''));
            ?>
            <tr>
              <td><?= htmlspecialchars($s['tanggal_berangkat']) ?></td>
              <td><?= htmlspecialchars($s['nomor_surat']) ?></td>
              <td><?= $vehLabel ?></td>
              <td><?= htmlspecialchars($s['tujuan']) ?></td>
              <td><?= htmlspecialchars($s['status']) ?></td>
              <td>
                <?php if ($editable): ?>
                  <form method="post" style="display:inline" class="d-inline">
                    <input type="hidden" name="csrf_token" value="<?= generate_csrf_token() ?>">
                    <input type="hidden" name="start_surat_id" value="<?= (int)$s['id'] ?>">
                    <button class="btn btn-sm btn-primary" type="submit"><i class="fas fa-edit"></i> Edit</button>
                  </form>
                <?php else: ?>
                  <button class="btn btn-sm btn-secondary" disabled><i class="fas fa-edit"></i> Edit</button>
                <?php endif; ?>
                <a href="?page=laporan_perjalanan&action=detail_surat&surat_id=<?= (int)$s['id'] ?>" class="btn btn-sm btn-outline-info ms-1"><i class="fas fa-map-marker-alt"></i> Detail</a>
              </td>
            </tr>
          <?php endforeach; endif; ?>
          </tbody>
        </table>
      </div>
    </div>
  </div>
  <?php
  exit;
}

// Detail view for a surat (show map with tracking since LP creation)
if ($action === 'detail_surat') {
  $surat_id = (int)($_GET['surat_id'] ?? 0);
  if ($surat_id <= 0) { echo '<div class="alert alert-danger">Surat tidak ditemukan</div>'; exit; }
  $stmt = $mysqli->prepare("SELECT st.*, k.no_reg, k.no_polisi, k.merk, k.tipe, k.locator FROM surat_tugas st JOIN kendaraan k ON st.kendaraan_id = k.id WHERE st.id = ? LIMIT 1");
  $stmt->bind_param('i', $surat_id);
  $stmt->execute();
  $s = $stmt->get_result()->fetch_assoc();
  $stmt->close();
  if (!$s) { echo '<div class="alert alert-danger">Surat tidak ditemukan</div>'; exit; }

  // find related laporan_perjalanan (use created_at as start time)
  $lp_start = null; $lp_id = null;
  $st2 = $mysqli->prepare("SELECT id, created_at FROM laporan_perjalanan WHERE kendaraan_id = ? AND tanggal = ? ORDER BY created_at ASC LIMIT 1");
  $st2->bind_param('is', $s['kendaraan_id'], $s['tanggal_berangkat']);
  $st2->execute();
  $r2 = $st2->get_result()->fetch_assoc();
  $st2->close();
  if ($r2) { $lp_id = (int)$r2['id']; $lp_start = $r2['created_at']; }

  ?>
  <div class="page-header">
    <h1><i class="fas fa-map-marked-alt"></i> Detail Surat - <?= htmlspecialchars($s['nomor_surat']) ?></h1>
    <div class="header-actions">
      <a class="btn btn-secondary" href="?page=laporan_perjalanan&action=assigned"><i class="fas fa-arrow-left me-1"></i> Kembali</a>
    </div>
  </div>

  <div class="card">
    <div class="card-body">
      <div class="row mb-3">
        <div class="col-md-4"><strong>Tanggal Berangkat:</strong> <?= htmlspecialchars($s['tanggal_berangkat']) ?></div>
        <div class="col-md-4"><strong>Kendaraan:</strong> <?= htmlspecialchars(($s['no_reg'] ?: $s['no_polisi']) . ' - ' . $s['merk']) ?></div>
        <div class="col-md-4"><strong>Status:</strong> <?= htmlspecialchars($s['status']) ?></div>
      </div>
      <div id="suratTrackMap" style="height:420px; width:100%;"></div>
      <div class="mt-2" id="trackSummary"></div>
    </div>
  </div>

  <script>
  document.addEventListener('DOMContentLoaded', function(){
    const mapEl = document.getElementById('suratTrackMap');
    if (!mapEl) return;
    const map = L.map('suratTrackMap').setView([-6.200, 106.816], 11);
    L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', { maxZoom: 19, attribution: '&copy; OpenStreetMap contributors' }).addTo(map);

    const vehicleId = <?= (int)$s['kendaraan_id'] ?>;
    const dateStr = '<?= htmlspecialchars($s['tanggal_berangkat']) ?>';
    const lpStart = <?= $lp_start ? json_encode($lp_start) : 'null' ?>;

    fetch(`ajax/traccar_daily_timeline.php?vehicle_id=${vehicleId}&date=${encodeURIComponent(dateStr)}`, { credentials: 'same-origin' })
      .then(r=>r.json())
      .then(data => {
        if (!data.success) {
          document.getElementById('trackSummary').innerText = data.message || 'Gagal mengambil tracking data dari Traccar.';
          return;
        }
        let points = data.points || [];
        if (lpStart) {
          const startTs = Date.parse(lpStart.replace(' ', 'T'));
          points = points.filter(p => {
            const t = Date.parse((p.server_time||p.device_time||'').replace(' ', 'T'));
            return !isNaN(t) && t >= startTs;
          });
        }
        if (!points.length) {
          document.getElementById('trackSummary').innerText = 'Belum ada data pelacakan untuk periode ini.';
          return;
        }
        const latlngs = points.map(p => [p.lat, p.lon]);
        const poly = L.polyline(latlngs, { color: 'blue', weight: 4 }).addTo(map);
        map.fitBounds(poly.getBounds(), { padding: [20,20] });
        const first = points[0]; const last = points[points.length-1];
        L.marker([first.lat, first.lon], { icon: L.divIcon({ className: 'start-marker', html: '<i class="fas fa-flag-checkered"></i>' }) }).addTo(map).bindPopup('Start');
        L.marker([last.lat, last.lon], { icon: L.divIcon({ className: 'current-marker', html: '<i class="fas fa-car"></i>' }) }).addTo(map).bindPopup('Terakhir: ' + (last.server_time || last.device_time || ''));
        document.getElementById('trackSummary').innerHTML = `<div class="small text-muted">Menampilkan ${points.length} titik dari ${lpStart ? 'mulai '+lpStart : 'awal hari'}</div>`;
      }).catch(err=>{ document.getElementById('trackSummary').innerText = 'Terjadi kesalahan saat memuat data pelacakan.'; });
  });
  </script>

  <?php
  exit;
}

// List view data only when listing
if ($action === 'list') {
  if (isset($_GET['created']) && $_GET['created'] == '1') {
    $msg = '<div class="alert alert-success">Laporan perjalanan berhasil ditambahkan.</div>';
  }
  // Base query
  $where = '';
  $params = [];
  $types = '';
  $bulan = trim($_GET['bulan'] ?? '');
  $bulan_start = null; $bulan_end = null; $bulan_valid = false;
  if ($bulan !== '' && preg_match('/^\d{4}-\d{2}$/', $bulan)) {
    $bulan_valid = true;
    $bulan_start = $bulan . '-01';
    // compute end as first day of next month using PHP
    $dt = DateTime::createFromFormat('Y-m-d', $bulan_start);
    if ($dt) {
      $dt->modify('first day of next month');
      $bulan_end = $dt->format('Y-m-d');
    } else {
      $bulan_valid = false;
    }
  }
  if ($q !== '') {
    $where = " WHERE (lp.uraian_kegiatan LIKE ? OR lp.route LIKE ? OR k.no_reg LIKE ? OR k.no_polisi LIKE ? OR p.nama_lengkap LIKE ? OR p.nrp_nip LIKE ? OR p.jabatan LIKE ? OR k.merk LIKE ? OR k.tipe LIKE ? OR lp.jarak_km LIKE ?)";
    $like = "%$q%";
    // 10 placeholders above => bind 10 params (all strings for LIKE)
    $params = [$like,$like,$like,$like,$like,$like,$like,$like,$like,$like];
    $types = 'ssssssssss';
  }
  if ($bulan_valid && $bulan_start && $bulan_end) {
    if ($where === '') $where = ' WHERE 1=1';
    $where .= ' AND lp.tanggal >= ? AND lp.tanggal < ?';
    $params[] = $bulan_start; $params[] = $bulan_end; $types .= 'ss';
  }

  // Role filter: drivers see only kendaraan they are responsible for
  if ($current_role === 'driver' && $current_user_id) {
    $hasPenggunaCol = function_exists('db_table_columns') && in_array('pengguna_id', (array)db_table_columns('kendaraan'), true);
    if ($where === '') $where = ' WHERE ';
    else $where .= ' AND ';
    if ($hasPenggunaCol) {
      $where .= 'k.pengguna_id = ?';
    } else {
      $where .= 'lp.pengguna_id = ?';
    }
    $params[] = $current_user_id; $types .= 'i';
  }

  // Count
  $sql_count = "SELECT COUNT(*) AS cnt FROM laporan_perjalanan lp JOIN kendaraan k ON lp.kendaraan_id = k.id LEFT JOIN pengguna p ON lp.pengguna_id = p.id $where";
  $stmt = $mysqli->prepare($sql_count);
  if ($where) { $bind = [$types]; foreach ($params as $i=>$_) { $bind[] = &$params[$i]; } call_user_func_array([$stmt,'bind_param'],$bind); }
  $stmt->execute(); $rc = $stmt->get_result(); $total = ($rc && ($r=$rc->fetch_assoc())) ? (int)$r['cnt'] : 0; $stmt->close();
  $total_pages = max(1, (int)ceil($total/$limit)); if ($pg>$total_pages) { $pg=$total_pages; $offset=($pg-1)*$limit; }

  // Data
  $sql = "SELECT lp.id, lp.tanggal, lp.uraian_kegiatan, lp.route, lp.jarak_km,
           k.id AS kendaraan_id, k.no_reg, k.no_polisi, k.merk, k.tipe, k.bahan_bakar,
           p.id AS pengguna_id, p.nama_lengkap, p.pangkat, p.nrp_nip
      FROM laporan_perjalanan lp
      JOIN kendaraan k ON lp.kendaraan_id = k.id
      LEFT JOIN pengguna p ON lp.pengguna_id = p.id
      $where
      ORDER BY lp.tanggal DESC, lp.id DESC
      LIMIT ? OFFSET ?";
  $params2 = $params; $types2 = $types . 'ii'; $params2[] = $limit; $params2[] = $offset;
  $stmt = $mysqli->prepare($sql);
  if ($where || true) { $bind2 = [$types2]; foreach ($params2 as $i=>$_) { $bind2[] = &$params2[$i]; } call_user_func_array([$stmt,'bind_param'],$bind2); }
  $stmt->execute(); $res = $stmt->get_result();
  $rows = $res ? $res->fetch_all(MYSQLI_ASSOC) : [];
  $stmt->close();
}

?>

<div class="page-header">
  <h1><i class="fas fa-clipboard-list"></i> Laporan Perjalanan</h1>
  <div class="header-actions">
    <?php if ($action === 'list'): ?>
  <a class="btn btn-success" href="?page=laporan_perjalanan&action=create"><i class="fas fa-plus me-1"></i> Tambah Laporan</a>
  <button class="btn btn-success" data-bs-toggle="modal" data-bs-target="#exportLaporanModal"><i class="fas fa-file-excel me-1"></i> Export Excel</button>
    <?php endif; ?>
  </div>
</div>
<?= $msg ?>

<?php if ($action === 'create'): ?>
<div class="card">
  <div class="card-header"><h3 class="m-0"><i class="fas fa-file-circle-plus me-1"></i> Buat Laporan Perjalanan</h3></div>
  <div class="card-body">
    <?php
      // Fetch dropdown data
      $kendaraan_opts = [];
      $resK = $mysqli->query("SELECT id, no_reg, no_polisi, merk, tipe, bahan_bakar FROM kendaraan ORDER BY COALESCE(no_reg, no_polisi), merk");
      if ($resK) { while ($k = $resK->fetch_assoc()) $kendaraan_opts[] = $k; }
      $pengguna_opts = [];
      $resP = $mysqli->query("SELECT id, nama_lengkap, pangkat, nrp_nip, jabatan FROM pengguna WHERE status_aktif = 'Aktif' ORDER BY nama_lengkap");
      if ($resP) { while ($p = $resP->fetch_assoc()) $pengguna_opts[] = $p; }
      $pengguna_tamudi_opts = [];
      $resT = $mysqli->query("SELECT id, nama_lengkap, pangkat, nrp_nip, jabatan FROM pengguna WHERE status_aktif = 'Aktif' AND LOWER(COALESCE(jabatan,'')) LIKE '%tamudi%' ORDER BY nama_lengkap");
      if ($resT) { while ($t = $resT->fetch_assoc()) $pengguna_tamudi_opts[] = $t; }
      $tanggal_val = htmlspecialchars($_POST['tanggal'] ?? date('Y-m-d'));
      $uraian_val = htmlspecialchars($_POST['uraian_kegiatan'] ?? '');
      $route_val = htmlspecialchars($_POST['route'] ?? '');
      $jarak_val_in = htmlspecialchars($_POST['jarak_km'] ?? '');
    ?>
    <form method="post" action="?page=laporan_perjalanan&action=create" class="row g-3">
      <input type="hidden" name="csrf_token" value="<?= generate_csrf_token() ?>" />
      <div class="col-md-3">
        <label class="form-label">Tanggal *</label>
        <input type="date" name="tanggal" class="form-control" required value="<?= $tanggal_val ?>" />
      </div>
      <div class="col-md-4">
        <label class="form-label">Kendaraan (No.Reg) *</label>
        <select name="kendaraan_id" id="kendaraan_id" class="form-select" required>
          <option value="">-- Pilih Kendaraan --</option>
          <?php foreach ($kendaraan_opts as $k): $id=(int)$k['id']; $label = trim(($k['no_reg'] ?: $k['no_polisi']) . ' - ' . $k['merk'] . ($k['tipe']?' '.$k['tipe']:'')); $fuel=strtolower(trim((string)($k['bahan_bakar'] ?? ''))); ?>
            <option value="<?= $id ?>" data-fuel="<?= htmlspecialchars($fuel) ?>" <?= (isset($_POST['kendaraan_id']) && (int)$_POST['kendaraan_id']===$id)?'selected':'' ?>><?= htmlspecialchars($label) ?></option>
          <?php endforeach; ?>
        </select>
      </div>
      <div class="col-md-5">
        <label class="form-label">Pengemudi (Pengguna) *</label>
        <select name="pengguna_id" id="pengguna_id" class="form-select" required>
          <option value="">-- Pilih Pengguna --</option>
          <?php if (!empty($pengguna_tamudi_opts)): ?>
            <optgroup label="Pengguna - Tamudi">
              <?php foreach ($pengguna_tamudi_opts as $p): $id=(int)$p['id']; $label = $p['nama_lengkap'] . ' - ' . trim(($p['pangkat']?:'') . ' ' . ($p['nrp_nip']?:'')); ?>
                <option value="<?= $id ?>" <?= (isset($_POST['pengguna_id']) && (int)$_POST['pengguna_id']===$id)?'selected':'' ?>><?= htmlspecialchars($label) ?></option>
              <?php endforeach; ?>
            </optgroup>
          <?php endif; ?>
          <optgroup label="Semua Pengguna">
            <?php foreach ($pengguna_opts as $p): $id=(int)$p['id']; $label = $p['nama_lengkap'] . ' - ' . trim(($p['pangkat']?:'') . ' ' . ($p['nrp_nip']?:'')); ?>
              <option value="<?= $id ?>" <?= (isset($_POST['pengguna_id']) && (int)$_POST['pengguna_id']===$id)?'selected':'' ?>><?= htmlspecialchars($label) ?></option>
            <?php endforeach; ?>
          </optgroup>
        </select>
      </div>
      <div class="col-md-6">
        <label class="form-label">Uraian Kegiatan *</label>
        <input type="text" name="uraian_kegiatan" class="form-control" required value="<?= $uraian_val ?>" />
      </div>
      <div class="col-md-6">
        <label class="form-label">Route *</label>
        <input type="text" name="route" class="form-control" required value="<?= $route_val ?>" />
      </div>
      <div class="col-md-3">
        <label class="form-label">Jarak (km)</label>
        <input type="number" step="1" min="0" name="jarak_km" class="form-control" value="<?= $jarak_val_in ?>" />
        <div class="mt-2" id="bbm_calc_wrap" aria-live="polite">
          <span id="bbm_liter_badge" class="badge bg-info text-dark" style="display:none;"></span>
          <div id="bbm_formula_text" class="form-text"></div>
        </div>
      </div>
      <div class="col-12 d-flex justify-content-between mt-2">
        <a href="?page=laporan_perjalanan" class="btn btn-secondary"><i class="fas fa-arrow-left me-1"></i> Kembali</a>
        <button type="submit" class="btn btn-primary"><i class="fas fa-save me-1"></i> Simpan</button>
      </div>
    </form>
  </div>
</div>

<script>
  (function(){
    const selK = document.getElementById('kendaraan_id');
    const selP = document.getElementById('pengguna_id');
    const jarakInput = document.querySelector('input[name="jarak_km"]');
    const bbmBadge = document.getElementById('bbm_liter_badge');
    const bbmText = document.getElementById('bbm_formula_text');
    if (!selK || !selP) return;
    async function applyDefaultPengguna() {
      const kid = selK.value;
      if (!kid) return;
      try {
        const url = `?page=laporan_perjalanan&action=lookup_pengguna&kendaraan_id=${encodeURIComponent(kid)}`;
        const res = await fetch(url, { headers: { 'Accept': 'application/json' } });
        const data = await res.json();
        if (data && data.success && data.pengguna_id) {
          const pid = String(data.pengguna_id);
          let opt = selP.querySelector(`option[value="${pid}"]`);
          if (!opt && data.nama) {
            opt = new Option(`${data.nama} ${data.jabatan?'- '+data.jabatan:''} (terdaftar)`, pid, true, true);
            // insert at top
            selP.insertBefore(opt, selP.firstChild);
          }
          if (opt) {
            selP.value = pid;
          }
        }
      } catch (e) {
        console.warn('Gagal lookup pengguna kendaraan', e);
      }
    }
    function getSelectedFuel(){
      const opt = selK && selK.selectedIndex >= 0 ? selK.options[selK.selectedIndex] : null;
      return (opt ? (opt.getAttribute('data-fuel') || '') : '').toLowerCase();
    }
    function computeBBM(){
      if (!jarakInput || !bbmBadge || !bbmText) return;
      const fuel = getSelectedFuel();
      const isSolar = (fuel === 'solar') || (fuel.includes('bio'));
      const kmPerL = isSolar ? 6 : 12; // default non-solar 12 km/L
      const v = parseFloat(jarakInput.value);
      if (!v || isNaN(v) || v <= 0) {
        bbmBadge.style.display = 'none';
        bbmText.textContent = 'Pilih kendaraan & isi jarak untuk melihat perhitungan (PP x2). Index '+kmPerL+' km/L.';
        return;
      }
      const raw = (v * 2) / kmPerL;
      let liters = Math.ceil(raw);
      if (liters === 0 && raw > 0) liters = 1;
      bbmBadge.textContent = `Perkiraan BBM: ${liters} L`;
      bbmBadge.style.display = '';
      bbmText.textContent = `1 ran (${v} km x 2) x 1 HB = ${liters} liter • ${kmPerL}`;
    }
    selK.addEventListener('change', applyDefaultPengguna);
    selK.addEventListener('change', computeBBM);
    if (jarakInput) {
      jarakInput.addEventListener('input', computeBBM);
    }
    // Prefill on load if kendaraan already selected
    if (selK.value) { applyDefaultPengguna(); }
    computeBBM();
  })();
</script>

<?php elseif ($action === 'edit'): ?>
<?php
  $edit_id = (int)($_GET['id'] ?? 0);
  if ($edit_id <= 0) {
    echo '<div class="alert alert-danger">Laporan tidak ditemukan.</div>';
  } else {
    $stmtE = $mysqli->prepare("SELECT lp.*, k.no_reg, k.no_polisi FROM laporan_perjalanan lp JOIN kendaraan k ON lp.kendaraan_id = k.id WHERE lp.id = ? LIMIT 1");
    $stmtE->bind_param('i', $edit_id);
    $stmtE->execute();
    $lp = $stmtE->get_result()->fetch_assoc();
    $stmtE->close();
    if (!$lp) {
      echo '<div class="alert alert-danger">Laporan tidak ditemukan.</div>';
    } else {
      // If driver, attempt to mark associated surat_tugas as 'Dalam Perjalanan' for today
      if ($current_role === 'driver' && db_table_exists('surat_tugas')) {
        $today = date('Y-m-d');
        $stq = $mysqli->prepare("SELECT id, status FROM surat_tugas WHERE kendaraan_id = ? AND pengguna_id = ? AND DATE(tanggal_berangkat) = ? LIMIT 1");
        $stq->bind_param('iis', $lp['kendaraan_id'], $current_user_id, $today);
        $stq->execute();
        $stres = $stq->get_result()->fetch_assoc();
        $stq->close();
        if ($stres && strtolower(trim($stres['status'] ?? '')) !== 'dalam perjalanan') {
          $up = $mysqli->prepare("UPDATE surat_tugas SET status = 'Dalam Perjalanan', updated_at = NOW() WHERE id = ?");
          $up->bind_param('i', $stres['id']);
          $up->execute();
          $up->close();
          if (function_exists('log_activity')) log_activity('SURAT_START', "Surat tugas id={$stres['id']} set to Dalam Perjalanan oleh pengguna {$current_user_id}");
        }
      }
      // Fetch dropdowns for kendaraan and pengguna (reuse create logic)
      $kendaraan_opts = [];
      $resK = $mysqli->query("SELECT id, no_reg, no_polisi, merk, tipe, bahan_bakar FROM kendaraan ORDER BY COALESCE(no_reg, no_polisi), merk");
      if ($resK) { while ($k = $resK->fetch_assoc()) $kendaraan_opts[] = $k; }
      $pengguna_opts = [];
      $resP = $mysqli->query("SELECT id, nama_lengkap, pangkat, nrp_nip, jabatan FROM pengguna WHERE status_aktif = 'Aktif' ORDER BY nama_lengkap");
      if ($resP) { while ($p = $resP->fetch_assoc()) $pengguna_opts[] = $p; }
      $pengguna_tamudi_opts = [];
      $resT = $mysqli->query("SELECT id, nama_lengkap, pangkat, nrp_nip, jabatan FROM pengguna WHERE status_aktif = 'Aktif' AND LOWER(COALESCE(jabatan,'')) LIKE '%tamudi%' ORDER BY nama_lengkap");
      if ($resT) { while ($t = $resT->fetch_assoc()) $pengguna_tamudi_opts[] = $t; }
      $tanggal_val = htmlspecialchars($lp['tanggal'] ?? date('Y-m-d'));
      $uraian_val = htmlspecialchars($lp['uraian_kegiatan'] ?? '');
      $route_val = htmlspecialchars($lp['route'] ?? '');
      $jarak_val_in = htmlspecialchars($lp['jarak_km'] ?? '');
    }
  }
?>
<?php if (!empty($lp)): ?>
<div class="card">
  <div class="card-header"><h3 class="m-0"><i class="fas fa-edit me-1"></i> Edit Laporan Perjalanan</h3></div>
  <div class="card-body">
    <form method="post" action="?page=laporan_perjalanan&action=edit&id=<?= (int)$edit_id ?>" class="row g-3">
      <input type="hidden" name="csrf_token" value="<?= generate_csrf_token() ?>" />
      <input type="hidden" name="edit_id" value="<?= (int)$edit_id ?>" />
      <div class="col-md-3">
        <label class="form-label">Tanggal *</label>
        <input type="date" name="tanggal" class="form-control" required value="<?= $tanggal_val ?>" />
      </div>
      <div class="col-md-4">
        <label class="form-label">Kendaraan (No.Reg) *</label>
        <select name="kendaraan_id" id="kendaraan_id" class="form-select" required>
          <option value="">-- Pilih Kendaraan --</option>
          <?php foreach ($kendaraan_opts as $k): $id=(int)$k['id']; $label = trim(($k['no_reg'] ?: $k['no_polisi']) . ' - ' . $k['merk'] . ($k['tipe']?' '.$k['tipe']:'')); $fuel=strtolower(trim((string)($k['bahan_bakar'] ?? ''))); ?>
            <option value="<?= $id ?>" data-fuel="<?= htmlspecialchars($fuel) ?>" <?= ((int)$lp['kendaraan_id'] === $id)?'selected':'' ?>><?= htmlspecialchars($label) ?></option>
          <?php endforeach; ?>
        </select>
      </div>
      <div class="col-md-5">
        <label class="form-label">Pengemudi (Pengguna) *</label>
        <select name="pengguna_id" id="pengguna_id" class="form-select" required>
          <option value="">-- Pilih Pengguna --</option>
          <?php if (!empty($pengguna_tamudi_opts)): ?>
            <optgroup label="Pengguna - Tamudi">
              <?php foreach ($pengguna_tamudi_opts as $p): $id=(int)$p['id']; $label = $p['nama_lengkap'] . ' - ' . trim(($p['pangkat']?:'') . ' ' . ($p['nrp_nip']?:'')); ?>
                <option value="<?= $id ?>" <?= ((int)$lp['pengguna_id'] === $id)?'selected':'' ?>><?= htmlspecialchars($label) ?></option>
              <?php endforeach; ?>
            </optgroup>
          <?php endif; ?>
          <optgroup label="Semua Pengguna">
            <?php foreach ($pengguna_opts as $p): $id=(int)$p['id']; $label = $p['nama_lengkap'] . ' - ' . trim(($p['pangkat']?:'') . ' ' . ($p['nrp_nip']?:'')); ?>
              <option value="<?= $id ?>" <?= ((int)$lp['pengguna_id'] === $id)?'selected':'' ?>><?= htmlspecialchars($label) ?></option>
            <?php endforeach; ?>
          </optgroup>
        </select>
      </div>
      <div class="col-md-6">
        <label class="form-label">Uraian Kegiatan *</label>
        <input type="text" name="uraian_kegiatan" class="form-control" required value="<?= $uraian_val ?>" />
      </div>
      <div class="col-md-6">
        <label class="form-label">Route *</label>
        <input type="text" name="route" class="form-control" required value="<?= $route_val ?>" />
      </div>
      <div class="col-md-3">
        <label class="form-label">Jarak (km)</label>
        <input type="number" step="1" min="0" name="jarak_km" class="form-control" value="<?= $jarak_val_in ?>" />
      </div>
      <div class="col-12 d-flex justify-content-between mt-2">
        <a href="?page=laporan_perjalanan" class="btn btn-secondary"><i class="fas fa-arrow-left me-1"></i> Kembali</a>
        <button type="submit" class="btn btn-primary"><i class="fas fa-save me-1"></i> Simpan Perubahan</button>
      </div>
    </form>
  </div>
</div>
<?php endif; ?>

<?php else: ?>
<div class="content">
  <!-- Filters (match placement/style from Riwayat Perawatan) -->
  <div class="card mb-4">
    <div class="card-body">
      <form method="GET" class="row g-3 filters-row">
        <input type="hidden" name="page" value="laporan_perjalanan">
        <div class="col-md-3">
          <label class="form-label">Bulan</label>
          <input type="month" name="bulan" class="form-control" value="<?= htmlspecialchars($bulan ?? '') ?>">
        </div>
        <div class="col-md-7">
          <label class="form-label">Pencarian</label>
          <input type="text" name="q" class="form-control" placeholder="Cari no reg / pengguna / kegiatan / rute" value="<?= htmlspecialchars($q ?? '') ?>">
        </div>
        <div class="col-md-1">
          <label class="form-label">&nbsp;</label>
          <button type="submit" class="btn btn-outline-primary" aria-label="Cari">
            <i class="fas fa-search"></i>
          </button>
        </div>
        <?php if (!empty($q) || !empty($bulan)): ?>
        <div class="col-md-1">
          <label class="form-label">&nbsp;</label>
          <a href="?page=laporan_perjalanan" class="btn btn-outline-danger" title="Reset" aria-label="Reset">
            <i class="fas fa-times"></i>
          </a>
        </div>
        <?php endif; ?>
      </form>
    </div>
  </div>
    <div class="card">
    <div class="card-header">
        <h4 class="card-title">Daftar Laporan Perjalanan</h4>
    </div>
    <div class="card-body">
        <div class="table-responsive">
        <table class="table align-middle">
            <thead>
            <tr>
                <th>Tanggal</th>
                <th>No Reg</th>
                <th>Pengemudi (Pengguna)</th>
                <th>Pangkat/NRP</th>
                <th>Merek & Tipe</th>
                <th>Uraian Kegiatan</th>
                <th>Route</th>
                <th>Jarak (km)</th>
                <th>Aksi</th>
                <th>BBM (L)</th>
            </tr>
            </thead>
            <tbody>
            <?php if (empty($rows)): ?>
                <tr><td colspan="10" class="text-center text-muted">Belum ada data</td></tr>
            <?php else: foreach ($rows as $r): $bbm = hitung_bbm_liter($r['jarak_km'], $r['bahan_bakar'], true); ?>
                <tr>
                <td><?= htmlspecialchars($r['tanggal']) ?></td>
                <td><?= htmlspecialchars($r['no_reg'] ?: $r['no_polisi']) ?></td>
                <td><?= htmlspecialchars($r['nama_lengkap'] ?: '-') ?></td>
                <td><?= htmlspecialchars(trim(($r['pangkat'] ?: '') . ' / ' . ($r['nrp_nip'] ?: ''), ' /')) ?></td>
                <td><?= htmlspecialchars($r['merk'] . ($r['tipe']? ' ' . $r['tipe'] : '')) ?></td>
                <td><?= htmlspecialchars($r['uraian_kegiatan']) ?></td>
                <td><?= htmlspecialchars($r['route']) ?></td>
                <td><?= htmlspecialchars($r['jarak_km'] ?? '') ?> Km</td>
                <td>
                  <?php if ($current_role === 'driver' && isset($surat_map[(int)$r['kendaraan_id']])): $st = $surat_map[(int)$r['kendaraan_id']]; ?>
                    <a href="?page=laporan_perjalanan&action=edit&id=<?= (int)$r['id'] ?>" class="btn btn-sm btn-outline-primary me-1"><i class="fas fa-edit"></i> Edit</a>
                    <form method="post" style="display:inline" class="d-inline" onsubmit="return confirm('Selesaikan perjalanan?');">
                      <input type="hidden" name="csrf_token" value="<?= generate_csrf_token() ?>">
                      <input type="hidden" name="finish_surat_id" value="<?= (int)$st['id'] ?>">
                      <button class="btn btn-sm btn-success" type="submit" title="Selesaikan Perjalanan"><i class="fas fa-check"></i></button>
                    </form>
                  <?php elseif (can_admin()): ?>
                    <a href="?page=laporan_perjalanan&action=edit&id=<?= (int)$r['id'] ?>" class="btn btn-sm btn-outline-primary"><i class="fas fa-edit"></i> Edit</a>
                  <?php else: ?>
                    -
                  <?php endif; ?>
                </td>
                <td><span class="badge bg-info text-dark"><?= $bbm !== null ? number_format($bbm, 0) : '-' ?> L</span></td>
                </tr>
            <?php endforeach; endif; ?>
            </tbody>
        </table>
        </div>

        <?php if ($total_pages > 1): $cur=$pg; ?>
        <nav aria-label="Paging">
        <ul class="pagination justify-content-end">
    <?php $build=function($p) use($q,$bulan){ $qs=http_build_query(['page'=>'laporan_perjalanan','q'=>$q,'bulan'=>$bulan,'pg'=>$p]); return '?' . $qs; }; ?>
            <li class="page-item <?= $cur<=1?'disabled':'' ?>"><a class="page-link" href="<?= $build(max(1,$cur-1)) ?>">&laquo;</a></li>
            <?php for($i=1;$i<=$total_pages;$i++): ?>
            <li class="page-item <?= $i===$cur?'active':'' ?>"><a class="page-link" href="<?= $build($i) ?>"><?= $i ?></a></li>
            <?php endfor; ?>
            <li class="page-item <?= $cur>=$total_pages?'disabled':'' ?>"><a class="page-link" href="<?= $build(min($total_pages,$cur+1)) ?>">&raquo;</a></li>
        </ul>
        </nav>
        <?php endif; ?>
    </div>
    </div>
</div>
<?php endif; ?>

<?php // Optionally: helper to seed from surat_tugas when that module is in use ?>

<?php if ($action === 'list'): ?>
<!-- Modal Export: Pilih Bulan (bisa multi) atau Tahun -->
<div class="modal fade" id="exportLaporanModal" tabindex="-1" aria-labelledby="exportLaporanLabel" aria-hidden="true">
  <div class="modal-dialog modal-dialog-centered">
    <div class="modal-content">
      <form method="get" action="" class="needs-validation" novalidate>
        <div class="modal-header">
          <h5 class="modal-title" id="exportLaporanLabel"><i class="fas fa-file-excel me-1"></i> Export Laporan</h5>
          <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
        </div>
        <div class="modal-body">
          <input type="hidden" name="page" value="laporan_perjalanan" />
          <input type="hidden" name="action" value="export_excel" />
          <?php if (!empty($q)): ?>
            <input type="hidden" name="q" value="<?= htmlspecialchars($q) ?>" />
          <?php endif; ?>
          <div class="mb-3">
            <label class="form-label d-block">Mode Export</label>
            <div class="btn-group" role="group" aria-label="Mode Export">
              <input type="radio" class="btn-check" name="mode" id="mode_bulan" value="bulan" autocomplete="off" checked>
              <label class="btn btn-outline-primary" for="mode_bulan">Per Bulan</label>
              <input type="radio" class="btn-check" name="mode" id="mode_tahun" value="tahun" autocomplete="off">
              <label class="btn btn-outline-primary" for="mode_tahun">Per Tahun</label>
            </div>
          </div>

          <div id="field_bulan_wrap" class="mb-3">
            <label class="form-label">Pilih Bulan (bisa lebih dari 1)</label>
            <div id="bulan_list">
              <div class="input-group mb-2 bulan-row">
                <input type="month" class="form-control" name="bulan[]" value="<?= htmlspecialchars($bulan ?? date('Y-m')) ?>" required />
                <button class="btn btn-outline-danger remove-bulan" type="button" title="Hapus">&times;</button>
              </div>
            </div>
            <button class="btn btn-sm btn-outline-secondary" type="button" id="add_bulan_btn"><i class="fas fa-plus me-1"></i>Tambah Bulan</button>
            <div class="form-text">Anda dapat menambahkan beberapa bulan sekaligus, misal JAN & FEB 2025.</div>
          </div>

          <div id="field_tahun_wrap" class="mb-3" style="display:none;">
            <label for="tahunExport" class="form-label">Pilih Tahun</label>
            <input type="number" min="2000" max="2100" step="1" class="form-control" id="tahunExport" name="tahun" placeholder="<?= date('Y') ?>" />
            <div class="form-text">Semua data pada tahun yang dipilih akan diexport.</div>
          </div>
          <div class="mb-2">
            <label class="form-label">Catatan</label>
            <ul class="small ps-3 mb-0">
              <li>Pilih satu atau beberapa bulan, atau pilih satu tahun penuh.</li>
              <li>Perhitungan kolom BBM (L) otomatis dari Jarak Km & Jenis BBM: non-solar 12 km/L, Bio Solar 6 km/L. Jarak dihitung PP (x2).</li>
              <li>Gunakan filter di halaman utama bila ingin memastikan data sudah lengkap sebelum export.</li>
            </ul>
          </div>
        </div>
        <div class="modal-footer">
          <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Batal</button>
          <button type="submit" class="btn btn-primary"><i class="fas fa-download me-1"></i> Export</button>
        </div>
      </form>
    </div>
  </div>
</div>
<script>
  // Sinkronkan nilai bulan di filter utama ke modal saat dibuka
  (function(){
    const modalEl = document.getElementById('exportLaporanModal');
    if(!modalEl) return;
    modalEl.addEventListener('show.bs.modal', function(){
      // Pastikan modal dipindah ke body untuk menghindari stacking context
      if (modalEl.parentNode !== document.body) {
        document.body.appendChild(modalEl);
      }
      const mainMonth = document.querySelector('form.filters-row input[name="bulan"]');
      const firstMonth = document.querySelector('#bulan_list .bulan-row input[type="month"]');
      if(mainMonth && firstMonth && mainMonth.value) {
        firstMonth.value = mainMonth.value;
      }
    });

     // Toggle mode fields
     const modeBulan = document.getElementById('mode_bulan');
     const modeTahun = document.getElementById('mode_tahun');
     const wrapBulan = document.getElementById('field_bulan_wrap');
     const wrapTahun = document.getElementById('field_tahun_wrap');
     function syncMode(){
       if (modeTahun.checked) {
         wrapBulan.style.display = 'none';
         wrapTahun.style.display = '';
         // Remove required from bulan[] and add required to tahun
         document.querySelectorAll('#bulan_list input[type="month"]').forEach(i=>i.required=false);
         const tahunInput = document.getElementById('tahunExport');
         tahunInput.required = true;
         if (!tahunInput.value) {
           tahunInput.value = new Date().getFullYear();
         }
       } else {
         wrapBulan.style.display = '';
         wrapTahun.style.display = 'none';
         document.querySelectorAll('#bulan_list input[type="month"]').forEach(i=>i.required=true);
         document.getElementById('tahunExport').required = false;
       }
     }
     modeBulan.addEventListener('change', syncMode);
     modeTahun.addEventListener('change', syncMode);
     syncMode();

     // Add/remove month rows
     const addBtn = document.getElementById('add_bulan_btn');
     const list = document.getElementById('bulan_list');
     addBtn.addEventListener('click', function(){
       const row = document.createElement('div');
       row.className = 'input-group mb-2 bulan-row';
       const input = document.createElement('input');
       input.type = 'month';
       input.name = 'bulan[]';
       input.className = 'form-control';
       input.required = true;
       input.value = (new Date()).toISOString().slice(0,7);
       const btn = document.createElement('button');
       btn.type = 'button';
       btn.className = 'btn btn-outline-danger remove-bulan';
       btn.textContent = '\u00D7';
       row.appendChild(input);
       row.appendChild(btn);
       list.appendChild(row);
     });
     list.addEventListener('click', function(e){
       if (e.target && e.target.classList.contains('remove-bulan')) {
         const rows = list.querySelectorAll('.bulan-row');
         if (rows.length > 1) {
           e.target.closest('.bulan-row').remove();
         }
       }
     });
  })();
</script>
<style>
  /* Z-index elevated to ensure modal & backdrop appear above custom sidebar/header */
  #exportLaporanModal { z-index: 2100; }
  #exportLaporanModal .modal-dialog { z-index: 2110; }
  .modal-backdrop.show { z-index: 2050; }
  #exportLaporanModal .modal-header { position: relative; z-index: 1; }
</style>
<?php endif; ?>
