<?php
require_once 'includes/auth.php';

$current_role = get_current_role();
$current_user_id = get_current_user_id();

// Role-based access control
if ($current_role === 'guest') {
    header('Location: index.php?page=kendaraan_publik');
    exit;
} else {
    require_login();
}

$can_crud = can_operate();
$action = $_GET['action'] ?? '';
$id = (int)($_GET['id'] ?? 0);
$keyword = trim($_GET['q'] ?? '');

$msg = '';

// Note: `penanggung_jawab` column/enum removed — handled via migration and code cleanup.

// Check if DB has optional pengguna assignment column
$HAS_PENGGUNA_ID = function_exists('db_table_columns') && in_array('pengguna_id', db_table_columns('kendaraan') ?: [], true);
$HAS_LOCATOR = function_exists('db_table_columns') && in_array('locator', db_table_columns('kendaraan') ?: [], true);

if (!function_exists('traccar_fetch_json_kendaraan')) {
    function traccar_fetch_json_kendaraan($url, $user, $pass) {
        $ch = curl_init($url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_USERPWD, $user . ':' . $pass);
        curl_setopt($ch, CURLOPT_HTTPAUTH, CURLAUTH_BASIC);
        curl_setopt($ch, CURLOPT_TIMEOUT, 20);
        curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
        curl_setopt($ch, CURLOPT_HTTPHEADER, ['Accept: application/json']);

        $raw = curl_exec($ch);
        $code = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $err = curl_error($ch);
        curl_close($ch);

        if ($raw === false) {
            return ['ok' => false, 'code' => $code, 'error' => $err ?: 'curl_error'];
        }

        $json = json_decode($raw, true);
        if (!is_array($json)) {
            return ['ok' => false, 'code' => $code, 'error' => 'invalid_json'];
        }

        return ['ok' => true, 'code' => $code, 'data' => $json];
    }
}

if (!function_exists('traccar_sync_last_position_by_locator')) {
    function traccar_sync_last_position_by_locator(mysqli $mysqli, $locator) {
        $locator = trim((string)$locator);
        if ($locator === '') {
            return ['ok' => false, 'message' => 'locator kosong'];
        }

        $primaryBase = rtrim((string)(getenv('TRACCAR_API_BASE') ?: 'http://localhost:8082/api'), '/');
        $altBasesRaw = (string)(getenv('TRACCAR_API_BASE_ALTERNATES') ?: 'http://127.0.0.1:8082/api,http://192.168.1.109:8082/api');
        $bases = [$primaryBase];
        foreach (explode(',', $altBasesRaw) as $b) {
            $b = trim($b);
            if ($b !== '') {
                $bases[] = rtrim($b, '/');
            }
        }
        $bases = array_values(array_unique($bases));

        $envUser = trim((string)(getenv('TRACCAR_USER') ?: ''));
        $envPass = trim((string)(getenv('TRACCAR_PASS') ?: ''));
        $credentials = [];
        if ($envUser !== '' && $envPass !== '') {
            $credentials[] = [$envUser, $envPass];
        }
        $credentials[] = ['admin@example.com', 'admin'];
        $credentials[] = ['admin@gmail.com', 'admin'];

        $deviceId = null;
        $deviceUid = null;
        $deviceName = null;
        $lastError = 'device_not_found';

        foreach ($bases as $base) {
            foreach ($credentials as $cred) {
                $user = $cred[0];
                $pass = $cred[1];

                $devicesRes = traccar_fetch_json_kendaraan($base . '/devices', $user, $pass);
                if (!$devicesRes['ok']) {
                    $lastError = $devicesRes['error'] ?? 'fetch_devices_failed';
                    continue;
                }

                foreach ($devicesRes['data'] as $device) {
                    $id = isset($device['id']) ? (int)$device['id'] : 0;
                    $uid = trim((string)($device['uniqueId'] ?? ''));
                    $name = trim((string)($device['name'] ?? ''));
                    if ($id <= 0) {
                        continue;
                    }

                    if (
                        strcasecmp($locator, (string)$id) === 0
                        || ($uid !== '' && strcasecmp($locator, $uid) === 0)
                        || ($name !== '' && strcasecmp($locator, $name) === 0)
                    ) {
                        $deviceId = $id;
                        $deviceUid = $uid !== '' ? $uid : null;
                        $deviceName = $name !== '' ? $name : null;
                        break;
                    }
                }

                if ($deviceId === null || $deviceId <= 0) {
                    $lastError = 'locator_not_found_in_devices';
                    continue;
                }

                $posCandidates = [];
                $posRes = traccar_fetch_json_kendaraan($base . '/positions?deviceId=' . rawurlencode((string)$deviceId), $user, $pass);
                if ($posRes['ok']) {
                    $posCandidates = $posRes['data'];
                } else {
                    $fallbackRes = traccar_fetch_json_kendaraan($base . '/positions', $user, $pass);
                    if ($fallbackRes['ok']) {
                        foreach ($fallbackRes['data'] as $item) {
                            $did = isset($item['deviceId']) ? (int)$item['deviceId'] : (isset($item['id']) ? (int)$item['id'] : 0);
                            if ($did === $deviceId) {
                                $posCandidates[] = $item;
                            }
                        }
                    } else {
                        $lastError = $fallbackRes['error'] ?? 'fetch_positions_failed';
                    }
                }

                if (!is_array($posCandidates) || empty($posCandidates)) {
                    $lastError = 'no_position_for_device';
                    continue;
                }

                usort($posCandidates, function ($a, $b) {
                    $ta = strtotime($a['deviceTime'] ?? ($a['serverTime'] ?? '1970-01-01 00:00:00'));
                    $tb = strtotime($b['deviceTime'] ?? ($b['serverTime'] ?? '1970-01-01 00:00:00'));
                    return $tb <=> $ta;
                });
                $latest = $posCandidates[0];

                $lat = isset($latest['latitude']) ? (float)$latest['latitude'] : null;
                $lon = isset($latest['longitude']) ? (float)$latest['longitude'] : null;
                if (!is_numeric($lat) || !is_numeric($lon)) {
                    $lastError = 'invalid_position_payload';
                    continue;
                }

                $speed = isset($latest['speed']) ? (float)$latest['speed'] : null;
                $course = isset($latest['course']) ? (float)$latest['course'] : null;
                $accuracy = isset($latest['accuracy']) ? (float)$latest['accuracy'] : null;
                $deviceTimeRaw = $latest['deviceTime'] ?? ($latest['positionTime'] ?? null);
                $deviceTime = $deviceTimeRaw ? date('Y-m-d H:i:s', strtotime((string)$deviceTimeRaw)) : null;
                $extraJson = json_encode($latest, JSON_UNESCAPED_UNICODE);

                $upsert = $mysqli->prepare("INSERT INTO traccar_positions_last
                    (device_id, device_uid, device_name, latitude, longitude, speed, course, accuracy, device_time, extra)
                    VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
                    ON DUPLICATE KEY UPDATE
                        device_id = VALUES(device_id),
                        device_uid = VALUES(device_uid),
                        device_name = VALUES(device_name),
                        latitude = VALUES(latitude),
                        longitude = VALUES(longitude),
                        speed = VALUES(speed),
                        course = VALUES(course),
                        accuracy = VALUES(accuracy),
                        device_time = VALUES(device_time),
                        extra = VALUES(extra),
                        updated_at = CURRENT_TIMESTAMP");

                if (!$upsert) {
                    return ['ok' => false, 'message' => 'prepare_upsert_failed: ' . $mysqli->error];
                }

                $deviceUidParam = $deviceUid !== null ? $deviceUid : null;
                $deviceNameParam = $deviceName !== null ? $deviceName : null;
                $deviceTimeParam = $deviceTime !== null ? $deviceTime : null;
                $speedParam = $speed !== null ? $speed : null;
                $courseParam = $course !== null ? $course : null;
                $accuracyParam = $accuracy !== null ? $accuracy : null;

                $upsert->bind_param(
                    'issdddddss',
                    $deviceId,
                    $deviceUidParam,
                    $deviceNameParam,
                    $lat,
                    $lon,
                    $speedParam,
                    $courseParam,
                    $accuracyParam,
                    $deviceTimeParam,
                    $extraJson
                );

                $ok = $upsert->execute();
                $upsert->close();

                if ($ok) {
                    return [
                        'ok' => true,
                        'message' => 'synced',
                        'device_id' => $deviceId,
                        'device_uid' => $deviceUid,
                        'device_name' => $deviceName,
                    ];
                }

                return ['ok' => false, 'message' => 'execute_upsert_failed: ' . $mysqli->error];
            }
        }

        return ['ok' => false, 'message' => $lastError];
    }
}

// Handle Excel export early to avoid any prior output
if ($action === 'export_excel') {
    if (!$can_crud) {
        header('Location: index.php?page=403');
        exit;
    }
    // Try to load PhpSpreadsheet
    $autoload = __DIR__ . '/../vendor/autoload.php';
    if (!file_exists($autoload)) {
        $msg = '<div class="alert alert-danger">Library PhpSpreadsheet tidak ditemukan. Tidak bisa export Excel.</div>';
    } else {
        require_once $autoload;
        // Branch by export mode
        $mode = trim($_GET['mode'] ?? 'daftar');

        if ($mode === 'rekap_bbm') {
            // =====================
            // REKAP PENERIMAAN BBM (Tahunan)
            // =====================
            $tahun = (int)($_GET['tahun'] ?? date('Y'));
            if ($tahun < 2000 || $tahun > 2100) { $tahun = (int)date('Y'); }

            // Restrict to accessible vehicles when available
            $vehicles_for_export = get_accessible_vehicles($current_role, $current_user_id, $keyword);
            $kendaraanIds = array_map(function($v){ return (int)($v['id'] ?? 0); }, $vehicles_for_export);
            $kendaraanIds = array_values(array_filter($kendaraanIds));
            $filterIn = '';
            if (!empty($kendaraanIds)) {
                $filterIn = ' AND lb.kendaraan_id IN ('.implode(',', array_map('intval', $kendaraanIds)).') ';
            } else {
                // If user has no accessible vehicles, produce an empty/zero rekap
                $filterIn = ' AND 1=0 ';
            }

        // Aggregasi berdasarkan jenis_bahan_bakar asli; mapping ke 3 baris (Pertamax, Premium, Solar) dilakukan di PHP
        $sql = "SELECT UPPER(lb.jenis_bahan_bakar) AS jenis_src, MONTH(lb.tanggal_isi) AS bulan, SUM(lb.jumlah_liter) AS total_liter
            FROM log_bahan_bakar lb
            WHERE YEAR(lb.tanggal_isi)=? $filterIn
            GROUP BY UPPER(lb.jenis_bahan_bakar), MONTH(lb.tanggal_isi)";
            $stmt = $mysqli->prepare($sql);
            if ($stmt) {
                $stmt->bind_param('i', $tahun);
                $stmt->execute();
                $res = $stmt->get_result();
                $agg = [];
                while ($r = $res->fetch_assoc()) {
                    $jenisSrc = $r['jenis_src'] ?: '';
                    $bulan = (int)$r['bulan'];
                    $lit = (float)$r['total_liter'];
                    // Normalisasi ke 3 grup: PERTAMAX, PREMIUM (termasuk PERTALITE), SOLAR (termasuk BIO SOLAR)
                    $u = strtoupper(trim($jenisSrc));
                    if (strpos($u, 'PERTAMAX') === 0) {
                        $key = 'PERTAMAX';
                    } elseif (strpos($u, 'SOLAR') !== false) {
                        $key = 'SOLAR';
                    } else {
                        // PREMIUM dan turunan/ersatz (Pertalite) dipetakan ke PREMIUM untuk format rekap TNI lama
                        $key = 'PREMIUM';
                    }
                    if (!isset($agg[$key])) { $agg[$key] = array_fill(1,12,0); }
                    $agg[$key][$bulan] += $lit;
                }
                $stmt->close();
            } else {
                $agg = [];
            }

            $spreadsheet = new \PhpOffice\PhpSpreadsheet\Spreadsheet();
            $sheet = $spreadsheet->getActiveSheet();
            $sheet->setTitle('Rekap BBM '.$tahun);

            // Column widths A..P
            $widths = [5,14,10,10,10,10,10,10,10,10,10,10,10,10,10,18];
            $col = 'A'; foreach ($widths as $w) { $sheet->getColumnDimension($col)->setWidth($w); $col++; }

            // Header Institution
            $sheet->mergeCells('A1:E1');
            $sheet->mergeCells('A2:E2');
            $sheet->setCellValue('A1','KEMENTERIAN PERTAHANAN REPUBLIK INDONESIA');
            $sheet->setCellValue('A2','SPBT KEMHAN CAWANG');
            $sheet->getStyle('A1:A2')->getFont()->setBold(true);
            $sheet->getStyle('A1:A2')->getAlignment()->setHorizontal(\PhpOffice\PhpSpreadsheet\Style\Alignment::HORIZONTAL_LEFT);
            $sheet->getStyle('A2')->getBorders()->getBottom()->setBorderStyle(\PhpOffice\PhpSpreadsheet\Style\Border::BORDER_THIN);

            // Title
            $sheet->mergeCells('A4:P4');
            $sheet->setCellValue('A4','REKAP PENERIMAAN BBM TAHUN '. $tahun);
            $sheet->getStyle('A4')->getFont()->setBold(true)->setSize(14);
            $sheet->getStyle('A4')->getAlignment()->setHorizontal(\PhpOffice\PhpSpreadsheet\Style\Alignment::HORIZONTAL_CENTER);

            // Table headers (row 6 and 7)
            $headers = ['NO','MT-88','JANUARI','FEBR','MARET','APRIL','MEI','JUNI','JULI','AGUST','SEPT','OKT','NOV','DES','JML/TH','KET'];
            $rowHdr1 = 6; $rowHdr2 = 7;
            $col = 'A'; foreach ($headers as $h) { $sheet->setCellValue($col.$rowHdr1, $h); $col++; }
            // numeric index row per TNI format
            for ($i=1; $i<=count($headers); $i++) { $sheet->setCellValue(chr(64+$i).$rowHdr2, (string)$i); }
            $sheet->getStyle('A'.$rowHdr1.':P'.$rowHdr2)->getFont()->setBold(true);
            $sheet->getStyle('A'.$rowHdr1.':P'.$rowHdr2)->getAlignment()->setHorizontal(\PhpOffice\PhpSpreadsheet\Style\Alignment::HORIZONTAL_CENTER);
            $sheet->getRowDimension($rowHdr1)->setRowHeight(20);
            $sheet->getRowDimension($rowHdr2)->setRowHeight(18);

            $startDataRow = $rowHdr2 + 1; $r = $startDataRow; $no=1;
            $fuelOrders = [
                'PERTAMAX' => 'Pertamax',
                'PREMIUM'  => 'Premium',
                'SOLAR'    => 'Solar',
            ];
            foreach ($fuelOrders as $key => $label) {
                // Title row for fuel
                $sheet->setCellValue('A'.$r, $no++);
                $sheet->setCellValue('B'.$r, $label);
                $r++;
                // Kupon row (kept for format; empty numbers)
                $sheet->setCellValue('B'.$r, 'Kupon');
                $r++;
                // Jumlah row with values
                $sheet->setCellValue('B'.$r, 'Jumlah');
                $bulanTotals = isset($agg[$key]) ? $agg[$key] : array_fill(1,12,0);
                $sum = 0;
                for ($m=1; $m<=12; $m++) {
                    $val = (float)($bulanTotals[$m] ?? 0);
                    $sum += $val;
                    // columns C..N map to months 1..12
                    $colIndex = 2 + $m; // A=1,B=2 => C starts at 3
                    $colLetter = \PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex($colIndex);
                    $sheet->setCellValue($colLetter.$r, $val > 0 ? round($val) : 0);
                }
                $sheet->setCellValue('O'.$r, round($sum)); // JML/TH
                $r++;
            }

            // Borders and number formats
            $lastRow = $r-1;
            $sheet->getStyle('A'.$rowHdr1.':P'.$lastRow)->getBorders()->getAllBorders()->setBorderStyle(\PhpOffice\PhpSpreadsheet\Style\Border::BORDER_THIN);
            $sheet->getStyle('C'.($startDataRow+2).':N'.$lastRow)->getNumberFormat()->setFormatCode('#,##0'); // jumlah rows are every 3rd; simplify by applying to block
            $sheet->getStyle('O'.($startDataRow+2).':O'.$lastRow)->getNumberFormat()->setFormatCode('#,##0');
            $sheet->getStyle('B'.($startDataRow).':B'.$lastRow)->getAlignment()->setHorizontal(\PhpOffice\PhpSpreadsheet\Style\Alignment::HORIZONTAL_LEFT);
            $sheet->getStyle('A'.($startDataRow).':A'.$lastRow)->getAlignment()->setHorizontal(\PhpOffice\PhpSpreadsheet\Style\Alignment::HORIZONTAL_CENTER);

            // Output file
            $filename = 'rekap_bbm_'.$tahun.'_'.date('Ymd_His').'.xlsx';
            if (function_exists('ini_get') && function_exists('ini_set') && ini_get('zlib.output_compression')) {
                @ini_set('zlib.output_compression', 'Off');
            }
            if (function_exists('ob_get_level')) {
                while (ob_get_level() > 0) { @ob_end_clean(); }
            }
            header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
            header('Content-Disposition: attachment; filename=' . $filename);
            header('Cache-Control: max-age=0');
            header('Pragma: public');
            header('Expires: 0');
            $writer = \PhpOffice\PhpSpreadsheet\IOFactory::createWriter($spreadsheet, 'Xlsx');
            $writer->save('php://output');
            exit;

        } else {
            // =====================
            // DAFTAR KENDARAAN (existing)
            // =====================
            // Resolve selected period (YYYY-MM) for header label and recap calculation
            $periode = trim($_GET['periode'] ?? ($_GET['bulan'] ?? ($_GET['month'] ?? '')));
            if (preg_match('/^\d{4}-\d{2}$/', $periode)) {
                list($tahunPilihan, $bulanPilihan) = explode('-', $periode);
            } else {
                $tahunPilihan = date('Y');
                $bulanPilihan = date('m');
                $periode = $tahunPilihan . '-' . $bulanPilihan;
            }

            // Use same accessible vehicles and respect search keyword
            $vehicles_for_export = get_accessible_vehicles($current_role, $current_user_id, $keyword);

            $spreadsheet = new \PhpOffice\PhpSpreadsheet\Spreadsheet();
            $sheet = $spreadsheet->getActiveSheet();
            $sheet->setTitle('Kendaraan');

            // =====================
            // CUSTOM HEADER (sesuai contoh gambar)
            // =====================
            $monthMap = ['01'=>'JANUARI','02'=>'FEBRUARI','03'=>'MARET','04'=>'APRIL','05'=>'MEI','06'=>'JUNI','07'=>'JULI','08'=>'AGUSTUS','09'=>'SEPTEMBER','10'=>'OKTOBER','11'=>'NOVEMBER','12'=>'DESEMBER'];
            $bulanLabel = ($monthMap[$bulanPilihan] ?? strtoupper(date('F'))) . ' ' . $tahunPilihan;
            $distinctFuel = array_unique(array_filter(array_map(function($v){ return $v['bahan_bakar'] ?? ''; }, $vehicles_for_export)));
            $fuelLabel = !empty($distinctFuel) ? implode(', ', $distinctFuel) : '-';

            // Lebar kolom dasar
            $widths = [5,12,18,18,30,8,5,6,5,28]; // A-J
            $col = 'A'; foreach ($widths as $w) { $sheet->getColumnDimension($col)->setWidth($w); $col++; }

            // Baris institusi
            $sheet->mergeCells('A1:F1');
            $sheet->mergeCells('A2:F2');
            $sheet->setCellValue('A1','KEMENTERIAN PERTAHANAN REPUBLIK INDONESIA');
            $sheet->setCellValue('A2','SPBT KEMHAN CAWANG');
            $sheet->getStyle('A1:A2')->getFont()->setBold(true);
            $sheet->getStyle('A1:A2')->getAlignment()->setHorizontal(\PhpOffice\PhpSpreadsheet\Style\Alignment::HORIZONTAL_LEFT);
            $sheet->getStyle('A1:A2')->getAlignment()->setVertical(\PhpOffice\PhpSpreadsheet\Style\Alignment::VERTICAL_CENTER);
            $sheet->getStyle('A2')->getBorders()->getBottom()->setBorderStyle(\PhpOffice\PhpSpreadsheet\Style\Border::BORDER_THIN);

            // Informasi kanan (kolom G-J)
            $sheet->setCellValue('G1','DAFTAR');   $sheet->setCellValue('H1',':'); $sheet->mergeCells('I1:J1'); $sheet->setCellValue('I1','KEKUATAN AL MAT');
            $sheet->setCellValue('G2','SATKER');   $sheet->setCellValue('H2',':'); $sheet->mergeCells('I2:J2'); $sheet->setCellValue('I2','SPBT KEMHAN CAWANG');
            $sheet->setCellValue('G3','BAHAN BAKAR'); $sheet->setCellValue('H3',':'); $sheet->mergeCells('I3:J3'); $sheet->setCellValue('I3', strtoupper($fuelLabel));
            $sheet->setCellValue('G4','BULAN');    $sheet->setCellValue('H4',':'); $sheet->mergeCells('I4:J4'); $sheet->setCellValue('I4',$bulanLabel);
            $sheet->getStyle('G1:G4')->getFont()->setBold(true);

            // Judul tengah
            $sheet->mergeCells('A6:J6');
            $sheet->mergeCells('A7:J7');
            $sheet->setCellValue('A6','DAFTAR KEKUATAN AL MAT');
            $sheet->setCellValue('A7','SATKER : SPBT KEMHAN CAWANG');
            $sheet->getStyle('A6:A7')->getFont()->setBold(true);
            $sheet->getStyle('A6:A7')->getAlignment()->setHorizontal(\PhpOffice\PhpSpreadsheet\Style\Alignment::HORIZONTAL_CENTER);
            $sheet->getStyle('A6:A7')->getAlignment()->setVertical(\PhpOffice\PhpSpreadsheet\Style\Alignment::VERTICAL_CENTER);

            // Header tabel (mulai baris 8 & 9)
            $sheet->mergeCells('A8:A9');
            $sheet->mergeCells('B8:B9');
            $sheet->mergeCells('C8:C9');
            $sheet->mergeCells('D8:D9');
            $sheet->mergeCells('E8:E9');
            $sheet->mergeCells('F8:F9');
            $sheet->mergeCells('G8:I8'); // KONDISI group
            $sheet->mergeCells('J8:J9');

            $sheet->setCellValue('A8','NO');
            $sheet->setCellValue('B8','No REG');
            $sheet->setCellValue('C8','CHASIS');
            $sheet->setCellValue('D8','MESIN');
            $sheet->setCellValue('E8','JENIS/TYPE/MERK');
            $sheet->setCellValue('F8','TAHUN');
            $sheet->setCellValue('G8','KONDISI');
            $sheet->setCellValue('J8','KETERANGAN');
            $sheet->setCellValue('G9','B');
            $sheet->setCellValue('H9','RR');
            $sheet->setCellValue('I9','RB');

            $sheet->getStyle('A8:J9')->getFont()->setBold(true);
            $sheet->getStyle('A8:J9')->getAlignment()->setHorizontal(\PhpOffice\PhpSpreadsheet\Style\Alignment::HORIZONTAL_CENTER);
            $sheet->getStyle('A8:J9')->getAlignment()->setVertical(\PhpOffice\PhpSpreadsheet\Style\Alignment::VERTICAL_CENTER);
            $sheet->getRowDimension(8)->setRowHeight(24);
            $sheet->getRowDimension(9)->setRowHeight(20);

            // Data rows mulai baris 10
            $row = 10; $no=1;
            if (!empty($vehicles_for_export)) {
                foreach ($vehicles_for_export as $v) {
                    $sheet->setCellValue('A'.$row, $no++);
                    $sheet->setCellValueExplicit('B'.$row, (string)($v['no_reg'] ?? ''), \PhpOffice\PhpSpreadsheet\Cell\DataType::TYPE_STRING);
                    $sheet->setCellValueExplicit('C'.$row, (string)($v['no_rangka'] ?? ''), \PhpOffice\PhpSpreadsheet\Cell\DataType::TYPE_STRING);
                    $sheet->setCellValueExplicit('D'.$row, (string)($v['no_mesin'] ?? ''), \PhpOffice\PhpSpreadsheet\Cell\DataType::TYPE_STRING);
                    $jenis = trim((string)($v['jenis'] ?? ''));
                    $tipe  = trim((string)($v['tipe'] ?? ''));
                    $merk  = trim((string)($v['merk'] ?? ''));
                    $jtDisplay = trim(implode(' / ', array_filter([$jenis,$tipe,$merk])));
                    $sheet->setCellValue('E'.$row, $jtDisplay);
                    $sheet->setCellValue('F'.$row, (int)($v['tahun_pembuatan'] ?? 0));
                    $kondisi = strtolower($v['kondisi'] ?? '');
                    $sheet->setCellValue('G'.$row, ($kondisi === 'baik') ? '1' : '');
                    $sheet->setCellValue('H'.$row, (strpos($kondisi,'ringan') !== false) ? '1' : '');
                    $sheet->setCellValue('I'.$row, (strpos($kondisi,'berat') !== false) ? '1' : '');
                    $row++;
                }
            }

            // Border tabel
            if ($row > 10) {
                $tableRange = 'A8:I'.($row-1);
                $sheet->getStyle($tableRange)->getBorders()->getAllBorders()->setBorderStyle(\PhpOffice\PhpSpreadsheet\Style\Border::BORDER_THIN);
            } else {
                // tetap kasih border header
                $sheet->getStyle('A8:J9')->getBorders()->getAllBorders()->setBorderStyle(\PhpOffice\PhpSpreadsheet\Style\Border::BORDER_THIN);
            }

            // Alignment khusus kolom angka / kondisi
            $sheet->getStyle('A10:A'.($row-1))->getAlignment()->setHorizontal(\PhpOffice\PhpSpreadsheet\Style\Alignment::HORIZONTAL_CENTER);
            $sheet->getStyle('F10:F'.($row-1))->getAlignment()->setHorizontal(\PhpOffice\PhpSpreadsheet\Style\Alignment::HORIZONTAL_CENTER);
            $sheet->getStyle('G10:I'.($row-1))->getAlignment()->setHorizontal(\PhpOffice\PhpSpreadsheet\Style\Alignment::HORIZONTAL_CENTER);
            $sheet->getStyle('B10:D'.($row-1))->getAlignment()->setHorizontal(\PhpOffice\PhpSpreadsheet\Style\Alignment::HORIZONTAL_LEFT);
            $sheet->getStyle('E10:E'.($row-1))->getAlignment()->setWrapText(true);

            // =====================
            // REKAPITULASI & FOOTER TANDA TANGAN
            // =====================
            $rekapStart = $row + 2; // dua baris kosong setelah data
            $sheet->setCellValue('A'.($rekapStart-1), '');
            $sheet->setCellValue('A'.$rekapStart, 'Rekapitulasi :');
            $sheet->getStyle('A'.$rekapStart)->getFont()->setBold(true);

            // Hitung jumlah kategori (heuristik sederhana)
            $countSedan = 0; $countMinibus = 0; $countMotor = 0;
            foreach ($vehicles_for_export as $v) {
                $jenis = strtolower((string)($v['jenis'] ?? ''));
                $tipe  = strtolower((string)($v['tipe'] ?? ''));
                $merk  = strtolower((string)($v['merk'] ?? ''));
                if (strpos($jenis,'roda 2') !== false || strpos($tipe,'motor')!==false || strpos($merk,'motor')!==false) {
                    $countMotor++; continue;
                }
                if (strpos($tipe,'bus')!==false || strpos($tipe,'hiace')!==false || strpos($tipe,'elf')!==false || strpos($tipe,'mini')!==false || strpos($merk,'bus')!==false) {
                    $countMinibus++; continue;
                }
                // asumsi sisanya sedan / mobil penumpang
                $countSedan++;
            }
            // Hitung hari kerja (Senin-Jumat) pada bulan yang dipilih
            $daysInMonth = (int)date('t', strtotime($periode . '-01'));
            $HARI_EFEKTIF = 0;
            for ($d = 1; $d <= $daysInMonth; $d++) {
                $dow = (int)date('N', strtotime($periode . '-' . str_pad((string)$d, 2, '0', STR_PAD_LEFT))); // 1..7 (Mon..Sun)
                if ($dow >= 1 && $dow <= 5) { $HARI_EFEKTIF++; }
            }
            if ($HARI_EFEKTIF <= 0) { $HARI_EFEKTIF = 22; } // fallback
            // Konstanta liter/hari per kategori
            $LITER_SEDAN_PER_HARI = 7; $LITER_MINIBUS_PER_HARI = 7; $LITER_MOTOR_PER_HARI = 1;
            $totalSedan = $countSedan * $HARI_EFEKTIF * $LITER_SEDAN_PER_HARI;
            $totalMinibus = $countMinibus * $HARI_EFEKTIF * $LITER_MINIBUS_PER_HARI;
            $totalMotor = $countMotor * $HARI_EFEKTIF * $LITER_MOTOR_PER_HARI;
            $grandTotal = $totalSedan + $totalMinibus + $totalMotor;

            $rLine = $rekapStart + 1; $i=1;
            $sheet->setCellValue('A'.$rLine, $i++.'. Sedan');
            \PhpOffice\PhpSpreadsheet\Cell\Cell::setValueBinder(new \PhpOffice\PhpSpreadsheet\Cell\StringValueBinder());
            $sheet->setCellValueExplicit('B'.$rLine, $countSedan.' x '.$HARI_EFEKTIF.' x '.$LITER_SEDAN_PER_HARI.' LITER', \PhpOffice\PhpSpreadsheet\Cell\DataType::TYPE_STRING);
            $sheet->setCellValueExplicit('D'.$rLine, $totalSedan.' LITER', \PhpOffice\PhpSpreadsheet\Cell\DataType::TYPE_STRING); $rLine++;
            $sheet->setCellValue('A'.$rLine, $i++.'. Minibus');
            $sheet->setCellValueExplicit('B'.$rLine, $countMinibus.' x '.$HARI_EFEKTIF.' x '.$LITER_MINIBUS_PER_HARI.' LITER', \PhpOffice\PhpSpreadsheet\Cell\DataType::TYPE_STRING);
            $sheet->setCellValueExplicit('D'.$rLine, $totalMinibus.' LITER', \PhpOffice\PhpSpreadsheet\Cell\DataType::TYPE_STRING); $rLine++;
            $sheet->setCellValue('A'.$rLine, $i++.'. Sepeda Motor');
            $sheet->setCellValueExplicit('B'.$rLine, $countMotor.' x '.$HARI_EFEKTIF.' x '.$LITER_MOTOR_PER_HARI.' LITER', \PhpOffice\PhpSpreadsheet\Cell\DataType::TYPE_STRING);
            $sheet->setCellValueExplicit('D'.$rLine, $totalMotor.' LITER', \PhpOffice\PhpSpreadsheet\Cell\DataType::TYPE_STRING); $rLine++;
            $sheet->setCellValue('A'.$rLine, 'Jumlah');
            $sheet->setCellValueExplicit('D'.$rLine, (string)$grandTotal.' LITER', \PhpOffice\PhpSpreadsheet\Cell\DataType::TYPE_STRING);
            $sheet->getStyle('A'.$rLine.':D'.$rLine)->getFont()->setBold(true);

            // Signature block
            $sigStart = $rLine + 3;
            // Tanggal tanda tangan mengikuti periode pilihan
            $dtSig = DateTime::createFromFormat('Y-m', $periode) ?: new DateTime();
            $bulanHuman = strtolower($dtSig->format('F Y'));
            $bulanHuman = ucwords($bulanHuman); // e.g., September 2025 (locale English capitalized)
            // Kolom penempatan (gunakan merge agar rapi)
            // Left (A-C), Middle (D-F), Right (G-J)
            $sheet->mergeCells('A'.$sigStart.':C'.$sigStart);      $sheet->setCellValue('A'.$sigStart, 'Menyetujui');
            $sheet->mergeCells('D'.$sigStart.':F'.$sigStart);      $sheet->setCellValue('D'.$sigStart, 'Mengetahui');
            $sheet->mergeCells('G'.$sigStart.':J'.$sigStart);      $sheet->setCellValue('G'.$sigStart, 'Jakarta, '.$bulanHuman);
            $sheet->mergeCells('A'.($sigStart+1).':C'.($sigStart+1)); $sheet->setCellValue('A'.($sigStart+1), 'Komandan Sathanpal Denma Mabes TNI,');
            $sheet->mergeCells('D'.($sigStart+1).':F'.($sigStart+1)); $sheet->setCellValue('D'.($sigStart+1), 'a.n. Komandan Denma Mabes TNI');
            $sheet->mergeCells('G'.($sigStart+1).':J'.($sigStart+1)); $sheet->setCellValue('G'.($sigStart+1), 'a.n. Kepala SPBT Kemhan Cawang');
            $sheet->mergeCells('D'.($sigStart+2).':F'.($sigStart+2)); $sheet->setCellValue('D'.($sigStart+2), 'Asmin,');
            $sheet->mergeCells('G'.($sigStart+2).':J'.($sigStart+2)); $sheet->setCellValue('G'.($sigStart+2), 'Kataud,');
            // Space for signatures (approx 5 rows)
            $nameRow = $sigStart + 7;
            $sheet->mergeCells('A'.$nameRow.':C'.$nameRow); $sheet->setCellValue('A'.$nameRow, 'Teguh Sulistyono');
            $sheet->mergeCells('D'.$nameRow.':F'.$nameRow); $sheet->setCellValue('D'.$nameRow, 'Yudo Pramono, S.E., S.H.');
            $sheet->mergeCells('G'.$nameRow.':J'.$nameRow); $sheet->setCellValue('G'.$nameRow, 'Subeno');
            $sheet->mergeCells('A'.($nameRow+1).':C'.($nameRow+1)); $sheet->setCellValue('A'.($nameRow+1), 'Letkol Cpl NRP 11000056341078');
            $sheet->mergeCells('D'.($nameRow+1).':F'.($nameRow+1)); $sheet->setCellValue('D'.($nameRow+1), 'Kolonel Cpm  NRP 119900880777');
            $sheet->mergeCells('G'.($nameRow+1).':J'.($nameRow+1)); $sheet->setCellValue('G'.($nameRow+1), 'Letkol Arh NRP 607954');

            // Styling signatures
            $sheet->getStyle('A'.$sigStart.':J'.($nameRow+1))->getAlignment()->setHorizontal(\PhpOffice\PhpSpreadsheet\Style\Alignment::HORIZONTAL_CENTER);
            $sheet->getStyle('A'.$sigStart.':J'.($sigStart+2))->getFont()->setBold(true);

            // Output (ensure clean buffer to avoid corrupt file / invalid extension warning)
            $filename = 'kendaraan_' . date('Ymd_His') . '.xlsx';
            // Turn off output compression (can corrupt binary stream)
            if (function_exists('ini_get') && function_exists('ini_set') && ini_get('zlib.output_compression')) {
                @ini_set('zlib.output_compression', 'Off');
            }
            // Clean all output buffers (remove any BOM/whitespace already captured)
            if (function_exists('ob_get_level')) {
                while (ob_get_level() > 0) { @ob_end_clean(); }
            }
            header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
            header('Content-Disposition: attachment; filename=' . $filename);
            header('Cache-Control: max-age=0');
            header('Pragma: public');
            header('Expires: 0');
            $writer = \PhpOffice\PhpSpreadsheet\IOFactory::createWriter($spreadsheet, 'Xlsx');
            $writer->save('php://output');
            exit;
        }
    }
}

// Handle POST actions (add, edit, import_excel)
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!validate_csrf_token($_POST['csrf_token'] ?? '')) {
        $msg = '<div class="alert alert-danger">Token keamanan tidak valid!</div>';
    } else {
        if ($action === 'add' && $can_crud) {
            $no_reg = trim($_POST['no_reg'] ?? '');
            $no_polisi = trim($_POST['no_polisi'] ?? '');
            if ($no_polisi === '') { $no_polisi = $no_reg; }
            $no_rangka = trim($_POST['no_rangka'] ?? '');
            $no_mesin = trim($_POST['no_mesin'] ?? '');
            $merk = trim($_POST['merk'] ?? '');
            $tipe = trim($_POST['tipe'] ?? '');
            $tahun_pembuatan = ($_POST['tahun_pembuatan'] !== '' ? (int)$_POST['tahun_pembuatan'] : 0);
            $warna = trim($_POST['warna'] ?? '');
            $bahan_bakar = trim($_POST['bahan_bakar'] ?? '');
            $kondisi = trim($_POST['kondisi'] ?? 'Baik');
            $status_kendaraan = trim($_POST['status_kendaraan'] ?? 'Operasional');
            $satker = trim($_POST['satker'] ?? '');
            $locator = trim($_POST['locator'] ?? '');
            $pengguna_id = null;
            if (isset($_POST['pengguna_id']) && $_POST['pengguna_id'] !== '' && ctype_digit((string)$_POST['pengguna_id'])) {
                $pengguna_id = (int)$_POST['pengguna_id'];
            }

            $locator = trim($_POST['locator'] ?? '');

            // If pengguna dipilih, validate user exists and is active (no longer tied to penanggung_jawab)
            if (empty($msg) && $pengguna_id !== null) {
                $stmtC = $mysqli->prepare("SELECT jabatan, status_aktif FROM pengguna WHERE id = ? LIMIT 1");
                if ($stmtC) {
                    $stmtC->bind_param('i', $pengguna_id);
                    $stmtC->execute();
                    $resC = $stmtC->get_result();
                    $rowC = $resC ? $resC->fetch_assoc() : null;
                    $stmtC->close();
                    if (!$rowC) {
                        $msg = '<div class="alert alert-danger">Pengguna yang dipilih tidak ditemukan.</div>';
                    } elseif (($rowC['status_aktif'] ?? '') !== 'Aktif') {
                        $msg = '<div class="alert alert-danger">Pengguna yang dipilih tidak aktif.</div>';
                    }
                }
            }

            // Uniqueness: a driver (pengguna_id) may only be assigned to one vehicle.
            if (empty($msg) && $HAS_PENGGUNA_ID && $pengguna_id !== null) {
                $checkSql = "SELECT id, no_reg FROM kendaraan WHERE pengguna_id = ? LIMIT 1";
                $checkStmt = $mysqli->prepare($checkSql);
                if ($checkStmt) {
                    $checkStmt->bind_param('i', $pengguna_id);
                    $checkStmt->execute();
                    $ex = $checkStmt->get_result()->fetch_assoc();
                    $checkStmt->close();
                    if (!empty($ex)) {
                        $msg = '<div class="alert alert-danger">Pengguna yang dipilih sudah ditugaskan ke kendaraan lain (No.Reg: ' . htmlspecialchars($ex['no_reg'] ?? $ex['id']) . '). Pilih pengguna lain.</div>';
                    }
                }
            }

        if ($no_reg !== '' && $merk !== '' && $satker !== '' && (!$HAS_LOCATOR || $locator !== '') && empty($msg)) {
                if ($HAS_PENGGUNA_ID) {
                    if ($pengguna_id === null) {
                        $sql = "INSERT INTO kendaraan (no_polisi, no_reg, no_rangka, no_mesin, merk, tipe, tahun_pembuatan, warna, bahan_bakar, kondisi, status_kendaraan, satker, locator, pengguna_id, created_at) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NULL, NOW())";
                        $stmt = $mysqli->prepare($sql);
                        if ($stmt) {
                            // 13 params: 6s + i (tahun) + 6s (removed jenis)
                            $stmt->bind_param('ssssssisssssss', $no_polisi, $no_reg, $no_rangka, $no_mesin, $merk, $tipe, $tahun_pembuatan, $warna, $bahan_bakar, $kondisi, $status_kendaraan, $satker, $locator);
                        }
                    } else {
                        $sql = "INSERT INTO kendaraan (no_polisi, no_reg, no_rangka, no_mesin, merk, tipe, tahun_pembuatan, warna, bahan_bakar, kondisi, status_kendaraan, satker, locator, pengguna_id, created_at) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())";
                        $stmt = $mysqli->prepare($sql);
                        if ($stmt) {
                            // 14 params: 6s + i (tahun) + 6s + i (pengguna_id, removed jenis)
                            $stmt->bind_param('ssssssisssssssi', $no_polisi, $no_reg, $no_rangka, $no_mesin, $merk, $tipe, $tahun_pembuatan, $warna, $bahan_bakar, $kondisi, $status_kendaraan, $satker, $locator, $pengguna_id);
                        }
                    }
                } else {
                    $stmt = $mysqli->prepare("INSERT INTO kendaraan (no_polisi, no_reg, no_rangka, no_mesin, merk, tipe, tahun_pembuatan, warna, bahan_bakar, kondisi, status_kendaraan, satker, locator, created_at) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())");
                    if ($stmt) {
                        // 13 params: 6s + i (tahun) + 6s (removed jenis)
                        $stmt->bind_param('ssssssisssssss', $no_polisi, $no_reg, $no_rangka, $no_mesin, $merk, $tipe, $tahun_pembuatan, $warna, $bahan_bakar, $kondisi, $status_kendaraan, $satker, $locator);
                    }
                }
                if ($stmt) {
                    try {
                        if ($stmt->execute()) {
                            $newId = $mysqli->insert_id;
                            $uploadErr = null;
                            if (!save_vehicle_photo((int)$newId, $_FILES['foto'] ?? null, $uploadErr) && $uploadErr) {
                                $msg .= '<div class="alert alert-warning">Foto tidak tersimpan: ' . htmlspecialchars($uploadErr) . '</div>';
                            }

                            if ($HAS_LOCATOR && $locator !== '') {
                                $syncResult = traccar_sync_last_position_by_locator($mysqli, $locator);
                                if (!$syncResult['ok']) {
                                    $msg .= '<div class="alert alert-warning">Kendaraan tersimpan, tetapi sinkronisasi posisi Traccar belum berhasil: ' . htmlspecialchars((string)($syncResult['message'] ?? 'unknown_error')) . '</div>';
                                }
                            }

                            log_activity('CREATE_VEHICLE', "Menambah kendaraan: $no_reg - $merk");
                            $msg = '<div class="alert alert-success">Kendaraan berhasil ditambahkan!</div>' . $msg;
                            $action = '';
                        } else {
                            // Non-exception error path
                            if (isset($stmt->errno) && (int)$stmt->errno === 1062) {
                                $dupField = 'data unik';
                                $err = strtolower((string)$stmt->error);
                                if (strpos($err, 'no_reg') !== false) { $dupField = 'No. Reg'; }
                                elseif (strpos($err, 'no_polisi') !== false) { $dupField = 'No. Polisi'; }
                                elseif (strpos($err, 'locator') !== false) { $dupField = 'Locator'; }
                                $dupVal = htmlspecialchars($dupField === 'Locator' ? $locator : ($no_reg ?: $no_polisi));
                                $msg = '<script>Swal.fire({icon:"error", title:"Duplikat Data", text:"' . $dupField . ' ' . $dupVal . ' sudah terdaftar. Gunakan nomor lain.", confirmButtonText:"OK"});</script>';
                                $action = 'add';
                            } else {
                                $msg = '<div class="alert alert-danger">Gagal menambah kendaraan! ' . htmlspecialchars($stmt->error ?? '') . '</div>';
                            }
                        }
                    } catch (\mysqli_sql_exception $e) {
                        if ((int)$e->getCode() === 1062 || strpos($e->getMessage(), 'Duplicate entry') !== false) {
                            // Try to extract duplicated value from message: Duplicate entry 'VALUE' for key '...'
                            $m = $e->getMessage();
                            $val = '';
                            if (preg_match("/Duplicate entry '([^']+)'/i", $m, $mm)) { $val = $mm[1]; }
                            $dupField = (stripos($m, 'no_reg') !== false) ? 'No. Reg' : ((stripos($m, 'no_polisi') !== false) ? 'No. Polisi' : 'Data');
                            $text = $dupField . ' ' . ($val !== '' ? $val : ($no_reg ?: $no_polisi)) . ' sudah terdaftar. Gunakan nomor lain.';
                            $msg = '<script>Swal.fire({icon:"error", title:"Duplikat Data", text:' . json_encode($text) . ', confirmButtonText:"OK"});</script>';
                            $action = 'add';
                        } else {
                            $msg = '<div class="alert alert-danger">Gagal menambah kendaraan: ' . htmlspecialchars($e->getMessage()) . '</div>';
                        }
                    }
                    $stmt->close();
                } else {
                    $msg = '<div class="alert alert-danger">Gagal menyiapkan query.</div>';
                }
            } else {
        $msg = $msg ?: '<div class="alert alert-danger">No. Reg, merk, satker, dan locator harus diisi!</div>';
            }
        } elseif ($action === 'edit' && $can_crud && $id) {
            $old_locator = '';
            if ($HAS_LOCATOR) {
                $stmtOld = $mysqli->prepare("SELECT locator FROM kendaraan WHERE id = ? LIMIT 1");
                if ($stmtOld) {
                    $stmtOld->bind_param('i', $id);
                    $stmtOld->execute();
                    $oldRow = $stmtOld->get_result()->fetch_assoc();
                    $stmtOld->close();
                    $old_locator = trim((string)($oldRow['locator'] ?? ''));
                }
            }

            $no_reg = trim($_POST['no_reg'] ?? '');
            $no_polisi = trim($_POST['no_polisi'] ?? '');
            if ($no_polisi === '') { $no_polisi = $no_reg; }
            $no_rangka = trim($_POST['no_rangka'] ?? '');
            $no_mesin = trim($_POST['no_mesin'] ?? '');
            $merk = trim($_POST['merk'] ?? '');
            $tipe = trim($_POST['tipe'] ?? '');
            $tahun_pembuatan = ($_POST['tahun_pembuatan'] !== '' ? (int)$_POST['tahun_pembuatan'] : 0);
            $warna = trim($_POST['warna'] ?? '');
            $bahan_bakar = trim($_POST['bahan_bakar'] ?? '');
            $kondisi = trim($_POST['kondisi'] ?? 'Baik');
            $status_kendaraan = trim($_POST['status_kendaraan'] ?? 'Operasional');
            $satker = trim($_POST['satker'] ?? '');
            $locator = trim($_POST['locator'] ?? '');
            $pengguna_id = null;
            if (isset($_POST['pengguna_id']) && $_POST['pengguna_id'] !== '' && ctype_digit((string)$_POST['pengguna_id'])) {
                $pengguna_id = (int)$_POST['pengguna_id'];
            }

            // Validasi pengguna yang dipilih (tetap wajib aktif jika dipilih)
            if (empty($msg) && $pengguna_id !== null) {
                $stmtC = $mysqli->prepare("SELECT jabatan, status_aktif FROM pengguna WHERE id = ? LIMIT 1");
                if ($stmtC) {
                    $stmtC->bind_param('i', $pengguna_id);
                    $stmtC->execute();
                    $resC = $stmtC->get_result();
                    $rowC = $resC ? $resC->fetch_assoc() : null;
                    $stmtC->close();
                    if (!$rowC) {
                        $msg = '<div class="alert alert-danger">Pengguna yang dipilih tidak ditemukan.</div>';
                    } elseif (($rowC['status_aktif'] ?? '') !== 'Aktif') {
                        $msg = '<div class="alert alert-danger">Pengguna yang dipilih tidak aktif.</div>';
                    }
                }
            }

            // Uniqueness check for edit: ensure pengguna_id not assigned to other kendaraan
            if (empty($msg) && $HAS_PENGGUNA_ID && $pengguna_id !== null) {
                $chk = $mysqli->prepare("SELECT id, no_reg FROM kendaraan WHERE pengguna_id = ? AND id <> ? LIMIT 1");
                if ($chk) {
                    $chk->bind_param('ii', $pengguna_id, $id);
                    $chk->execute();
                    $ex = $chk->get_result()->fetch_assoc();
                    $chk->close();
                    if (!empty($ex)) {
                        $msg = '<div class="alert alert-danger">Pengguna yang dipilih sudah ditugaskan ke kendaraan lain (No.Reg: ' . htmlspecialchars($ex['no_reg'] ?? $ex['id']) . '). Pilih pengguna lain.</div>';
                    }
                }
            }

        if ($no_reg !== '' && $merk !== '' && $satker !== '' && (!$HAS_LOCATOR || $locator !== '') && empty($msg)) {
                if ($HAS_PENGGUNA_ID) {
                    if ($pengguna_id === null) {
                        $sql = "UPDATE kendaraan SET no_polisi=?, no_reg=?, no_rangka=?, no_mesin=?, merk=?, tipe=?, tahun_pembuatan=?, warna=?, bahan_bakar=?, kondisi=?, status_kendaraan=?, satker=?, locator=?, pengguna_id=NULL, updated_at=NOW() WHERE id=?";
                        $stmt = $mysqli->prepare($sql);
                        if ($stmt) {
                            $stmt->bind_param('ssssssissssssi', $no_polisi, $no_reg, $no_rangka, $no_mesin, $merk, $tipe, $tahun_pembuatan, $warna, $bahan_bakar, $kondisi, $status_kendaraan, $satker, $locator, $id);
                        }
                    } else {
                        $sql = "UPDATE kendaraan SET no_polisi=?, no_reg=?, no_rangka=?, no_mesin=?, merk=?, tipe=?, tahun_pembuatan=?, warna=?, bahan_bakar=?, kondisi=?, status_kendaraan=?, satker=?, locator=?, pengguna_id=?, updated_at=NOW() WHERE id=?";
                        $stmt = $mysqli->prepare($sql);
                        if ($stmt) {
                            // 15 params: 6s + i (tahun) + 6s + i (pengguna_id) + i (id, removed jenis)
                            $stmt->bind_param('ssssssissssssii', $no_polisi, $no_reg, $no_rangka, $no_mesin, $merk, $tipe, $tahun_pembuatan, $warna, $bahan_bakar, $kondisi, $status_kendaraan, $satker, $locator, $pengguna_id, $id);
                        }
                    }
                } else {
                    $stmt = $mysqli->prepare("UPDATE kendaraan SET no_polisi=?, no_reg=?, no_rangka=?, no_mesin=?, merk=?, tipe=?, tahun_pembuatan=?, warna=?, bahan_bakar=?, kondisi=?, status_kendaraan=?, satker=?, locator=?, updated_at=NOW() WHERE id=?");
                    if ($stmt) {
                        $stmt->bind_param('ssssssissssssi', $no_polisi, $no_reg, $no_rangka, $no_mesin, $merk, $tipe, $tahun_pembuatan, $warna, $bahan_bakar, $kondisi, $status_kendaraan, $satker, $locator, $id);
                    }
                }
                    if ($stmt) {
                        try {
                            if ($stmt->execute()) {
                                $uploadErr = null;
                                if (!save_vehicle_photo($id, $_FILES['foto'] ?? null, $uploadErr) && $uploadErr) {
                                    $msg .= '<div class="alert alert-warning">Foto tidak tersimpan: ' . htmlspecialchars($uploadErr) . '</div>';
                                }

                                if ($HAS_LOCATOR && $locator !== '' && $locator !== $old_locator) {
                                    $syncResult = traccar_sync_last_position_by_locator($mysqli, $locator);
                                    if (!$syncResult['ok']) {
                                        $msg .= '<div class="alert alert-warning">Locator tersimpan, tetapi sinkronisasi posisi Traccar belum berhasil: ' . htmlspecialchars((string)($syncResult['message'] ?? 'unknown_error')) . '</div>';
                                    }
                                }

                                log_activity('UPDATE_VEHICLE', "Mengupdate kendaraan: $no_reg - $merk");
                                $msg = '<div class="alert alert-success">Kendaraan berhasil diupdate!</div>' . $msg;
                                $action = '';
                            } else {
                                if (isset($stmt->errno) && (int)$stmt->errno === 1062) {
                                    $dupField = 'data unik';
                                    $err = strtolower((string)$stmt->error);
                                    if (strpos($err, 'no_reg') !== false) { $dupField = 'No. Reg'; }
                                    elseif (strpos($err, 'no_polisi') !== false) { $dupField = 'No. Polisi'; }
                                    $dupVal = htmlspecialchars($dupField === 'Locator' ? $locator : ($no_reg ?: $no_polisi));
                                    $msg = '<script>Swal.fire({icon:"error", title:"Duplikat Data", text:"' . $dupField . ' ' . $dupVal . ' sudah terdaftar. Gunakan nomor lain.", confirmButtonText:"OK"});</script>';
                                    $action = 'edit';
                                } else {
                                    $msg = '<div class="alert alert-danger">Gagal mengupdate kendaraan! ' . htmlspecialchars($stmt->error ?? '') . '</div>';
                                }
                            }
                        } catch (\mysqli_sql_exception $e) {
                            if ((int)$e->getCode() === 1062 || strpos($e->getMessage(), 'Duplicate entry') !== false) {
                                $m = $e->getMessage();
                                $val = '';
                                if (preg_match("/Duplicate entry '([^']+)'/i", $m, $mm)) { $val = $mm[1]; }
                                $dupField = (stripos($m, 'no_reg') !== false) ? 'No. Reg' : ((stripos($m, 'no_polisi') !== false) ? 'No. Polisi' : 'Data');
                                $text = $dupField . ' ' . ($val !== '' ? $val : ($no_reg ?: $no_polisi)) . ' sudah terdaftar. Gunakan nomor lain.';
                                $msg = '<script>Swal.fire({icon:"error", title:"Duplikat Data", text:' . json_encode($text) . ', confirmButtonText:"OK"});</script>';
                                $action = 'edit';
                            } else {
                                $msg = '<div class="alert alert-danger">Gagal mengupdate kendaraan: ' . htmlspecialchars($e->getMessage()) . '</div>';
                            }
                        }
                        $stmt->close();
                    } else {
                        $msg = '<div class="alert alert-danger">Gagal menyiapkan query.</div>';
                    }
            } else {
            $msg = $msg ?: '<div class="alert alert-danger">No. Reg, merk, satker, dan locator harus diisi!</div>';
            }
        } elseif ($action === 'import_excel' && $can_crud) {
            if (isset($_FILES['excel_file']) && ($_FILES['excel_file']['error'] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_OK) {
                $excel_file = $_FILES['excel_file']['tmp_name'];
                $ext = strtolower(pathinfo($_FILES['excel_file']['name'], PATHINFO_EXTENSION));
                if ($ext !== 'xlsx') {
                    $msg = '<div class="alert alert-danger">Hanya file .xlsx yang didukung.</div>';
                } else {
                    $autoload = __DIR__ . '/../vendor/autoload.php';
                    if (!file_exists($autoload)) {
                        $msg = '<div class="alert alert-danger">Library PhpSpreadsheet tidak ditemukan.</div>';
                    } else {
                        require_once $autoload;
                        $imported = 0; $errors = [];
                        try {
                            $spreadsheet = \PhpOffice\PhpSpreadsheet\IOFactory::load($excel_file);
                            $sheet = $spreadsheet->getActiveSheet();
                            $rows = $sheet->toArray(null, true, true, true);
                            if (!$rows || count($rows) < 2) { throw new \Exception('File kosong atau tidak memiliki data.'); }
                            $headerRow = array_shift($rows);
                            $map = [];
                            foreach ($headerRow as $col => $val) {
                                $key = strtolower(trim((string)$val));
                                $key = str_replace([' ', '-'], '_', $key);
                                if ($key !== '') $map[$key] = $col;
                            }
                            // Note: penanggung_jawab removed from required headers (column dropped from DB)
                            // Note: jenis removed from required headers (replaced by pengguna_id driver selection)
                            $required = ['no_rangka','no_mesin','no_reg','merk','tipe','tahun_pembuatan','warna','bahan_bakar','satker','kondisi','status_kendaraan'];
                            foreach ($required as $req) {
                                if (!isset($map[$req])) {
                                    throw new \Exception('Header tidak lengkap. Wajib: ' . implode(',', $required));
                                }
                            }

                            foreach ($rows as $i => $dataRow) {
                                $no_rangka = trim((string)($dataRow[$map['no_rangka']] ?? ''));
                                $no_mesin = trim((string)($dataRow[$map['no_mesin']] ?? ''));
                                $no_reg = trim((string)($dataRow[$map['no_reg']] ?? ''));
                                $merk = trim((string)($dataRow[$map['merk']] ?? ''));
                                $tipe = trim((string)($dataRow[$map['tipe']] ?? ''));
                                $tahun_pembuatan_raw = trim((string)($dataRow[$map['tahun_pembuatan']] ?? ''));
                                $tahun_pembuatan = ($tahun_pembuatan_raw === '' ? 0 : (int)$tahun_pembuatan_raw);
                                $warna = trim((string)($dataRow[$map['warna']] ?? ''));
                                $bahan_bakar = trim((string)($dataRow[$map['bahan_bakar']] ?? ''));
                                // Normalisasi nilai bahan bakar dari file import
                                if ($bahan_bakar !== '') {
                                    $bb_lc = strtolower($bahan_bakar);
                                    if ($bb_lc === 'bensin' || $bb_lc === 'premium') {
                                        $bahan_bakar = 'Pertalite';
                                    } elseif ($bb_lc === 'pertamax turbo') {
                                        // Enum kendaraan tidak memiliki Pertamax Turbo; map ke Pertamax
                                        $bahan_bakar = 'Pertamax';
                                    } elseif ($bb_lc === 'bio solar') {
                                        // Kendaraan disederhanakan ke Solar
                                        $bahan_bakar = 'Solar';
                                    } else {
                                        // Kapitalisasi konsisten untuk nilai dikenal
                                        if ($bb_lc === 'pertalite') $bahan_bakar = 'Pertalite';
                                        if ($bb_lc === 'pertamax') $bahan_bakar = 'Pertamax';
                                        if ($bb_lc === 'solar') $bahan_bakar = 'Solar';
                                        if ($bb_lc === 'listrik') $bahan_bakar = 'Listrik';
                                        if ($bb_lc === 'hybrid') $bahan_bakar = 'Hybrid';
                                    }
                                }
                                $satker = trim((string)($dataRow[$map['satker']] ?? ''));
                                $kondisi = trim((string)($dataRow[$map['kondisi']] ?? ''));
                                $status_kendaraan = trim((string)($dataRow[$map['status_kendaraan']] ?? ''));
                                $no_polisi = isset($map['no_polisi']) ? trim((string)($dataRow[$map['no_polisi']] ?? '')) : '';
                                if ($no_polisi === '') { $no_polisi = $no_reg; }

                                if ($merk === '' || $satker === '' || $no_reg === '') {
                                    $errors[] = 'Baris ' . (intval($i) + 2) . ': merk/satker/no_reg wajib diisi.';
                                    continue;
                                }

                                $check_stmt = $mysqli->prepare("SELECT id FROM kendaraan WHERE no_polisi = ? OR no_reg = ? LIMIT 1");
                                if ($check_stmt) {
                                    $check_stmt->bind_param('ss', $no_polisi, $no_reg);
                                    $check_stmt->execute();
                                    $existing = $check_stmt->get_result()->fetch_assoc();
                                    $check_stmt->close();
                                    if ($existing) {
                                        $errors[] = 'Baris ' . (intval($i) + 2) . ': Kendaraan dengan no_reg tersebut sudah ada (atau duplikat no_polisi lama).';
                                        continue;
                                    }
                                }

                                $stmt = $mysqli->prepare("INSERT INTO kendaraan (no_polisi, no_reg, no_rangka, no_mesin, merk, tipe, tahun_pembuatan, warna, bahan_bakar, kondisi, status_kendaraan, satker, created_at) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())");
                                if ($stmt === false) { $errors[] = 'Baris ' . (intval($i) + 2) . ': gagal menyiapkan statement: ' . $mysqli->error; continue; }
                                // 12 params: 6s + i (tahun) + 5s (removed jenis)
                                $stmt->bind_param('ssssssississss', $no_polisi, $no_reg, $no_rangka, $no_mesin, $merk, $tipe, $tahun_pembuatan, $warna, $bahan_bakar, $kondisi, $status_kendaraan, $satker);
                                if ($stmt->execute()) {
                                    $imported++;
                                    log_activity('IMPORT_VEHICLE', 'Import kendaraan: ' . $no_reg . ' - ' . $merk);
                                } else {
                                    $errors[] = 'Baris ' . (intval($i) + 2) . ': Gagal import ' . htmlspecialchars($no_reg) . ': ' . $mysqli->error;
                                }
                                $stmt->close();
                            }

                            $msg = '<div class="alert alert-success">Berhasil import ' . $imported . ' kendaraan!</div>';
                            if (!empty($errors)) {
                                $msg .= '<div class="alert alert-warning">Beberapa data gagal diimport:<ul>';
                                foreach ($errors as $error) { $msg .= '<li>' . htmlspecialchars($error) . '</li>'; }
                                $msg .= '</ul></div>';
                            }
                            $action = '';
                        } catch (\Throwable $e) {
                            $msg = '<div class="alert alert-danger">Gagal memproses file: ' . htmlspecialchars($e->getMessage()) . '</div>';
                        }
                    }
                }
            } else {
                $msg = '<div class="alert alert-danger">File Excel (.xlsx) harus dipilih!</div>';
            }
        }
    }
}

// Handle delete action (GET)
if ($action === 'delete' && can_admin() && $id) {
    if (isset($_GET['confirm']) && $_GET['confirm'] === 'yes') {
        $stmt = $mysqli->prepare('SELECT no_polisi, merk FROM kendaraan WHERE id = ?');
        if ($stmt) {
            $stmt->bind_param('i', $id);
            $stmt->execute();
            $result = $stmt->get_result();
            $vehicle_info = $result->fetch_assoc();
            $stmt->close();
        }
        if (!empty($vehicle_info)) {
            $stmt = $mysqli->prepare('DELETE FROM kendaraan WHERE id = ?');
            if ($stmt) {
                $stmt->bind_param('i', $id);
                if ($stmt->execute()) {
                    log_activity('DELETE_VEHICLE', 'Menghapus kendaraan: ' . $vehicle_info['no_polisi'] . ' - ' . $vehicle_info['merk']);
                    $msg = '<div class="alert alert-success">Kendaraan berhasil dihapus!</div>';
                } else {
                    $msg = '<div class="alert alert-danger">Gagal menghapus kendaraan!</div>';
                }
                $stmt->close();
            }
        }
        $action = '';
    } else {
        $msg = '<div class="alert alert-warning">'
            . '<strong>Konfirmasi Hapus:</strong> Yakin ingin menghapus kendaraan ini? '
            . '<a href="index.php?page=kendaraan&action=delete&id=' . $id . '&confirm=yes" class="btn btn-danger btn-sm">Ya, Hapus</a> '
            . '<a href="index.php?page=kendaraan" class="btn btn-secondary btn-sm">Batal</a>'
            . '</div>';
    }
}

// Prepare data for views
$edit_data = null;
if ($action === 'edit' && $id) {
    $stmt = $mysqli->prepare('SELECT * FROM kendaraan WHERE id = ? LIMIT 1');
    if ($stmt) {
        $stmt->bind_param('i', $id);
        $stmt->execute();
        $res = $stmt->get_result();
        $edit_data = $res ? $res->fetch_assoc() : null;
        $stmt->close();
    }
}

// Vehicles list for table (list view pagination like log_bahan_bakar)
$page_num = (isset($_GET['page_num']) && ctype_digit($_GET['page_num'])) ? max(1, (int)$_GET['page_num'])
    : ((isset($_GET['pg']) && ctype_digit($_GET['pg'])) ? max(1, (int)$_GET['pg']) : 1); // backward compat 'pg'
$limit = 10; // default page size
$sort_col = $_GET['sort'] ?? 'updated_at';
$sort_dir = strtolower($_GET['dir'] ?? 'desc');
$sort_dir = in_array($sort_dir, ['asc','desc'], true) ? $sort_dir : 'desc';
$offset = ($page_num - 1) * $limit;
$total_records = 0;
$vehicles = get_accessible_vehicles_paginated($current_role, $current_user_id, $keyword, $limit, $offset, $total_records, $sort_col, $sort_dir);
$total_pages = ($total_records > 0) ? (int)ceil($total_records / $limit) : 1;
$currentUsers = [];
// Optional: Show assigned pengguna when column exists
if ($HAS_PENGGUNA_ID && !empty($vehicles)) {
    $ids = array_column($vehicles, 'id');
    $ids_in = implode(',', array_map('intval', $ids));
    if ($ids_in) {
    $sqlCU = "SELECT k.id as kendaraan_id, p.nama_lengkap, p.pangkat FROM kendaraan k LEFT JOIN pengguna p ON k.pengguna_id = p.id WHERE k.id IN ($ids_in) AND k.pengguna_id IS NOT NULL";
        $resCU = $mysqli->query($sqlCU);
        if ($resCU) {
            while ($r = $resCU->fetch_assoc()) {
                $label = $r['nama_lengkap'];
                if (!empty($r['pangkat'])) $label = $r['pangkat'] . ' - ' . $label;
                $currentUsers[(int)$r['kendaraan_id']] = $label;
            }
        }
    }
}
?>

<?php
// Load drivers (pengguna with role='driver') for vehicle forms if column exists
$drivers = [];
if ($HAS_PENGGUNA_ID) {
    // Some installations store role in user_account/role table; prefer joining there to find drivers
    // Exclude users who are already assigned to a vehicle (one driver per vehicle constraint).
    // If editing, allow the current vehicle's assigned pengguna to remain selectable.
    $currentAssigned = 0;
    if (!empty($edit_data) && !empty($edit_data['pengguna_id'])) { $currentAssigned = (int)$edit_data['pengguna_id']; }
    $excludeSub = "SELECT COALESCE(pengguna_id,0) FROM kendaraan WHERE pengguna_id IS NOT NULL";
    if ($currentAssigned > 0) { $excludeSub .= " AND pengguna_id <> " . $currentAssigned; }

    $sqlDrivers = "SELECT p.id, p.nama_lengkap, p.pangkat, p.nrp_nip
        FROM pengguna p
        JOIN user_account ua ON p.id = ua.pengguna_id
        JOIN role r ON ua.role_id = r.id
        WHERE (ua.status = 'Aktif' OR ua.status = 'aktif') AND UPPER(COALESCE(r.kode_role, '')) = 'DRIVER'
          AND p.id NOT IN ($excludeSub)
        ORDER BY p.nama_lengkap ASC";
    $drivers_res = $mysqli->query($sqlDrivers);
    if ($drivers_res) { $drivers = $drivers_res->fetch_all(MYSQLI_ASSOC); }
}
?>

<?= $msg ?>

<?php if ($can_crud && $action === 'add'): ?>
    <div class="card">
        <div class="card-header">
            <h3><i class="fas fa-plus"></i> Tambah Kendaraan</h3>
        </div>
        <div class="card-body">
            <form method="post" class="vehicle-form" enctype="multipart/form-data">
                <input type="hidden" name="csrf_token" value="<?= generate_csrf_token() ?>">
                <div class="form-row">
                    <div class="form-group">
                        <label for="no_reg">No. Reg</label>
                        <input type="text" id="no_reg" name="no_reg" class="form-control" placeholder="0000-00">
                    </div>
                    <div class="form-group">
                        <label for="satker">Satker *</label>
                        <input type="text" id="satker" name="satker" class="form-control" required>
                    </div>
                    <div class="form-group">
                        <label for="merk">Merk Kendaraan *</label>
                        <input type="text" id="merk" name="merk" class="form-control" required>
                    </div>
                    <div class="form-group">
                        <label for="tipe">Tipe/Model</label>
                        <input type="text" id="tipe" name="tipe" class="form-control">
                    </div>
                </div>
                <div class="form-row">
                    <div class="form-group">
                        <label for="tahun_pembuatan">Tahun Pembuatan</label>
                        <input type="number" id="tahun_pembuatan" name="tahun_pembuatan" class="form-control" min="1900" max="<?= date('Y') ?>">
                    </div>
                    <div class="form-group">
                        <label for="warna">Warna</label>
                        <input type="text" id="warna" name="warna" class="form-control" placeholder="Hijau, Hitam, Biru">
                    </div>
                    
                </div>
                <div class="form-row">
                    <div class="form-group">
                        <label for="bahan_bakar">Bahan Bakar</label>
                        <select id="bahan_bakar" name="bahan_bakar" class="form-control">
                            <option value="Pertalite" selected>Pertalite</option>
                            <option value="Pertamax">Pertamax</option>
                            <option value="Solar">Solar</option>
                            <option value="Listrik">Listrik</option>
                            <option value="Hybrid">Hybrid</option>
                        </select>
                    </div>
                    <div class="form-group">
                        <label for="kondisi">Kondisi</label>
                        <select id="kondisi" name="kondisi" class="form-control">
                            <option value="Baik">Baik</option>
                            <option value="Rusak Ringan">Rusak Ringan</option>
                            <option value="Rusak Berat">Rusak Berat</option>
                        </select>
                    </div>
                    <div class="form-group">
                        <label for="status_kendaraan">Status Kendaraan</label>
                        <select id="status_kendaraan" name="status_kendaraan" class="form-control">
                            <option value="Operasional">Operasional</option>
                            <option value="Perbaikan">Perbaikan</option>
                            <option value="Rusak">Rusak</option>
                            <option value="Tidak Aktif">Tidak Aktif</option>
                        </select>
                    </div>
                </div>
                <div class="form-row">
                    <div class="form-group">
                        <label for="no_rangka">Nomor Rangka</label>
                        <input type="text" id="no_rangka" name="no_rangka" class="form-control">
                    </div>
                    <div class="form-group">
                        <label for="no_mesin">Nomor Mesin</label>
                        <input type="text" id="no_mesin" name="no_mesin" class="form-control">
                    </div>
                    <!-- penanggung_jawab field removed (column dropped). -->
                </div>
                <div class="form-row">
                    <div class="form-group">
                        <label for="locator">Locator Traccar *</label>
                        <input type="text" id="locator" name="locator" class="form-control" placeholder="UID perangkat Traccar" required>
                        <small class="form-text text-muted">Isi dengan locator atau uniqueId dari perangkat Traccar.</small>
                    </div>
                </div>
                <?php if ($HAS_PENGGUNA_ID): ?>
                <div class="form-row">
                    <div class="form-group">
                        <label for="pengguna_id">Driver</label>
                        <select id="pengguna_id" name="pengguna_id" class="form-control">
                            <option value="">-- Pilih Driver (opsional) --</option>
                            <?php foreach ($drivers as $d): ?>
                                <option value="<?= (int)$d['id'] ?>"><?= htmlspecialchars((!empty($d['pangkat']) ? $d['pangkat'] . ' ' : '') . $d['nama_lengkap'] . (!empty($d['nrp_nip']) ? ' (' . $d['nrp_nip'] . ')' : '')) ?></option>
                            <?php endforeach; ?>
                        </select>
                        <small class="form-text text-muted">Pilih pengguna dengan role 'driver' (jika ada).</small>
                    </div>
                </div>
                <?php endif; ?>
                <div class="form-row">
                    <div class="form-group">
                        <label for="foto">Foto Kendaraan</label>
                        <input type="file" id="foto" name="foto" class="form-control" accept="image/*">
                        <small class="form-text text-muted">Unggah foto (opsional). Maks 4MB (JPG, PNG, WEBP).</small>
                    </div>
                    <div class="form-group" style="display:flex; align-items:flex-end; gap:12px;">
                        <img id="foto-preview" src="" alt="Preview Foto" style="display:none; width:96px; height:96px; object-fit:cover; border-radius:8px; border:1px solid #ddd;" class="img-thumbnail" />
                    </div>
                </div>
                <div class="form-actions">
                    <button type="submit" class="btn btn-primary"><i class="fas fa-save"></i> Simpan</button>
                    <a href="index.php?page=kendaraan" class="btn btn-secondary"><i class="fas fa-times"></i> Batal</a>
                </div>
            </form>
        </div>
    </div>
    
<?php elseif ($can_crud && $action === 'edit' && $edit_data): ?>
    <div class="card">
        <div class="card-header">
            <h3><i class="fas fa-edit"></i> Edit Kendaraan: <?= htmlspecialchars($edit_data['no_reg'] ?: ($edit_data['merk'] ?? '')) ?></h3>
        </div>
        <div class="card-body">
            <form method="post" class="vehicle-form" enctype="multipart/form-data">
                <input type="hidden" name="csrf_token" value="<?= generate_csrf_token() ?>">
                <div class="form-row">
                    <div class="form-group">
                        <label for="no_reg">No. Reg</label>
                        <input type="text" id="no_reg" name="no_reg" class="form-control" value="<?= htmlspecialchars($edit_data['no_reg'] ?? '') ?>" placeholder="0000-00">
                    </div>
                    <div class="form-group">
                        <label for="satker">Satker *</label>
                        <input type="text" id="satker" name="satker" class="form-control" value="<?= htmlspecialchars($edit_data['satker'] ?? '') ?>" required>
                    </div>
                    <div class="form-group">
                        <label for="merk">Merk Kendaraan *</label>
                        <input type="text" id="merk" name="merk" class="form-control" value="<?= htmlspecialchars($edit_data['merk']) ?>" required>
                    </div>
                    <div class="form-group">
                        <label for="tipe">Tipe/Model</label>
                        <input type="text" id="tipe" name="tipe" class="form-control" value="<?= htmlspecialchars($edit_data['tipe'] ?: '') ?>">
                    </div>
                </div>
                <div class="form-row">
                    <div class="form-group">
                        <label for="tahun_pembuatan">Tahun Pembuatan</label>
                        <input type="number" id="tahun_pembuatan" name="tahun_pembuatan" class="form-control" value="<?= htmlspecialchars($edit_data['tahun_pembuatan'] ?: '') ?>" min="1900" max="<?= date('Y') ?>">
                    </div>
                    <div class="form-group">
                        <label for="warna">Warna</label>
                        <input type="text" id="warna" name="warna" class="form-control" value="<?= htmlspecialchars($edit_data['warna'] ?? '') ?>" placeholder="Putih, Hitam">
                    </div>
                    
                </div>
                <div class="form-row">
                    <div class="form-group">
                        <label for="bahan_bakar">Bahan Bakar</label>
                        <select id="bahan_bakar" name="bahan_bakar" class="form-control">
                            <option value="Pertalite" <?= isset($edit_data['bahan_bakar']) && $edit_data['bahan_bakar'] === 'Pertalite' ? 'selected' : '' ?>>Pertalite</option>
                            <option value="Pertamax" <?= isset($edit_data['bahan_bakar']) && $edit_data['bahan_bakar'] === 'Pertamax' ? 'selected' : '' ?>>Pertamax</option>
                            <option value="Solar" <?= isset($edit_data['bahan_bakar']) && $edit_data['bahan_bakar'] === 'Solar' ? 'selected' : '' ?>>Solar</option>
                            <option value="Listrik" <?= isset($edit_data['bahan_bakar']) && $edit_data['bahan_bakar'] === 'Listrik' ? 'selected' : '' ?>>Listrik</option>
                            <option value="Hybrid" <?= isset($edit_data['bahan_bakar']) && $edit_data['bahan_bakar'] === 'Hybrid' ? 'selected' : '' ?>>Hybrid</option>
                        </select>
                    </div>
                    <div class="form-group">
                        <label for="kondisi">Kondisi</label>
                        <select id="kondisi" name="kondisi" class="form-control">
                            <option value="Baik" <?= $edit_data['kondisi'] === 'Baik' ? 'selected' : '' ?>>Baik</option>
                            <option value="Rusak Ringan" <?= $edit_data['kondisi'] === 'Rusak Ringan' ? 'selected' : '' ?>>Rusak Ringan</option>
                            <option value="Rusak Berat" <?= $edit_data['kondisi'] === 'Rusak Berat' ? 'selected' : '' ?>>Rusak Berat</option>
                        </select>
                    </div>
                    <div class="form-group">
                        <label for="status_kendaraan">Status Kendaraan</label>
                        <select id="status_kendaraan" name="status_kendaraan" class="form-control">
                            <option value="Operasional" <?= $edit_data['status_kendaraan'] === 'Operasional' ? 'selected' : '' ?>>Operasional</option>
                            <option value="Perbaikan" <?= $edit_data['status_kendaraan'] === 'Perbaikan' ? 'selected' : '' ?>>Perbaikan</option>
                            <option value="Rusak" <?= $edit_data['status_kendaraan'] === 'Rusak' ? 'selected' : '' ?>>Rusak</option>
                            <option value="Tidak Aktif" <?= $edit_data['status_kendaraan'] === 'Tidak Aktif' ? 'selected' : '' ?>>Tidak Aktif</option>
                        </select>
                    </div>
                </div>
                <div class="form-row">
                    <div class="form-group">
                        <label for="no_rangka">Nomor Rangka</label>
                        <input type="text" id="no_rangka" name="no_rangka" class="form-control" value="<?= htmlspecialchars($edit_data['no_rangka'] ?: '') ?>">
                    </div>
                    <div class="form-group">
                        <label for="no_mesin">Nomor Mesin</label>
                        <input type="text" id="no_mesin" name="no_mesin" class="form-control" value="<?= htmlspecialchars($edit_data['no_mesin'] ?: '') ?>">
                    </div>
                    <!-- penanggung_jawab field removed (column dropped). -->
                </div>
                <div class="form-row">
                    <div class="form-group">
                        <label for="locator">Locator Traccar *</label>
                        <input type="text" id="locator" name="locator" class="form-control" value="<?= htmlspecialchars($edit_data['locator'] ?? '') ?>" placeholder="UID perangkat Traccar" required>
                        <small class="form-text text-muted">Isi dengan locator atau uniqueId dari perangkat Traccar.</small>
                    </div>
                </div>
                
                <?php if ($HAS_PENGGUNA_ID): ?>
                <div class="form-row">
                    <div class="form-group">
                        <label for="pengguna_id">Driver</label>
                        <select id="pengguna_id" name="pengguna_id" class="form-control">
                            <option value="">-- Pilih Driver (opsional) --</option>
                            <?php foreach ($drivers as $d): ?>
                                <option value="<?= (int)$d['id'] ?>" <?= ((int)($edit_data['pengguna_id'] ?? 0) === (int)$d['id']) ? 'selected' : '' ?>>
                                    <?= htmlspecialchars((!empty($d['pangkat']) ? $d['pangkat'] . ' ' : '') . $d['nama_lengkap'] . (!empty($d['nrp_nip']) ? ' (' . $d['nrp_nip'] . ')' : '')) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                        <small class="form-text text-muted">Pilih pengguna dengan role 'driver' (jika ada).</small>
                    </div>
                </div>
                <?php endif; ?>

                <div class="form-row">
                    <div class="form-group">
                        <label for="foto">Foto Kendaraan</label>
                        <input type="file" id="foto" name="foto" class="form-control" accept="image/*">
                        <small class="form-text text-muted">Unggah untuk mengganti foto. Maks 4MB (JPG, PNG, WEBP).</small>
                    </div>
                    <div class="form-group" style="display:flex; align-items:flex-end; gap:12px;">
                        <?php $currPhoto = get_vehicle_photo_web_path((int)$edit_data['id']); ?>
                        <?php if ($currPhoto): ?>
                            <img id="foto-preview" src="<?= htmlspecialchars($currPhoto) ?>?v=<?= urlencode($edit_data['updated_at'] ?? $edit_data['created_at'] ?? time()) ?>" alt="Foto Kendaraan" style="width:96px; height:96px; object-fit:cover; border-radius:8px; border:1px solid #ddd;" class="img-thumbnail" />
                        <?php else: ?>
                            <img id="foto-preview" src="" alt="Preview Foto" style="display:none; width:96px; height:96px; object-fit:cover; border-radius:8px; border:1px solid #ddd;" class="img-thumbnail" />
                        <?php endif; ?>
                    </div>
                </div>
                <div class="form-actions">
                    <button type="submit" class="btn btn-primary"><i class="fas fa-save"></i> Update Data</button>
                    <a href="index.php?page=kendaraan" class="btn btn-secondary"><i class="fas fa-times"></i> Batal</a>
                </div>
            </form>
        </div>
    </div>
    
<?php else: ?>
    <div class="page-header">
                <h1><i class="fas fa-car me-2"></i>Daftar Kendaraan</h1>
                <div class="header-actions">
                    <?php if ($can_crud): ?>
                    <div class="btn-group" role="group">
                        <a href="index.php?page=kendaraan&action=add" class="btn btn-secondary"><i class="fas fa-plus"></i> Tambah</a>
                        <a class="btn btn-success" data-bs-toggle="modal" data-bs-target="#importModal"><i class="fas fa-file-excel"></i> Import Data</a>
                        <button class="btn btn-danger" data-bs-toggle="modal" data-bs-target="#exportKendaraanModal"><i class="fas fa-file-excel"></i> Export Rekapitulasi</button>
                    </div>
                    <?php endif; ?>
                </div>
    </div>
    <div class="actions-bar" style="display:flex; justify-content:space-between; align-items:center; margin-bottom:12px; gap:12px;">
        <div class="search-box">
            <form method="get" class="search-form">
                <input type="hidden" name="page" value="kendaraan">
                <input type="text" name="q" placeholder="Cari no polisi, merk, tipe, penanggung jawab, atau pengguna..." value="<?= htmlspecialchars($keyword) ?>">
                <button type="submit" class="btn btn-primary"><i class="fas fa-search"></i></button>
                <?php if ($keyword !== ''): ?>
                    <a href="index.php?page=kendaraan" class="btn btn-outline"><i class="fas fa-times"></i></a>
                <?php endif; ?>
            </form>
        </div>
    </div>

    <div class="card">
        <div class="card-body">
            <div class="table-responsive">
                <table class="table table-hover align-middle">
                    <thead>
                        <?php
                        // helper to build sortable header link
                        $buildSort = function($col, $label) use($sort_col, $sort_dir, $keyword, $page_num) {
                            $nextDir = ($sort_col === $col && $sort_dir === 'asc') ? 'desc' : 'asc';
                            $icon = '';
                            if ($sort_col === $col) {
                                $icon = $sort_dir === 'asc' ? '▲' : '▼';
                            }
                            $params = [
                                'page' => 'kendaraan',
                                'page_num' => $page_num,
                                'sort' => $col,
                                'dir' => $nextDir,
                            ];
                            if ($keyword !== '') { $params['q'] = $keyword; }
                            $url = 'index.php?' . http_build_query($params);
                            return '<a href="'.htmlspecialchars($url).'" class="text-decoration-none">'.htmlspecialchars($label).' <span style="font-size:10px;">'.$icon.'</span></a>';
                        };
                        ?>
                        <tr>
                            <th>Foto</th>
                            <th><?= $buildSort('no_reg','No') ?></th>
                            <th><?= $buildSort('no_reg','No. Reg') ?></th>
                            <th><?= $buildSort('merk','Merk & Tipe') ?></th>
                            <th><?= $buildSort('tahun_pembuatan','Tahun') ?></th>
                            <th><?= $buildSort('status_kendaraan','Status') ?></th>
                            <?php if ($current_role !== 'user'): ?>
                                <th><?= $buildSort('current_user','Driver') ?></th>
                            <?php endif; ?>
                            <th>Aksi</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (!empty($vehicles)): ?>
                            <?php $no = $offset + 1; foreach ($vehicles as $vehicle): ?>
                            <tr>
                                <td>
                                    <?php $thumb = get_vehicle_photo_web_path((int)$vehicle['id']); ?>
                                    <?php if ($thumb): ?>
                                        <img src="<?= htmlspecialchars($thumb) ?>" alt="Foto" style="width:42px; height:42px; object-fit:cover; border-radius:6px; border:1px solid #e0e0e0;" />
                                    <?php else: ?>
                                        <div style="width:42px; height:42px; display:flex; align-items:center; justify-content:center; background:#f3f4f6; color:#6b7280; border-radius:6px; border:1px solid #e0e0e0;">
                                            <i class="fas fa-car"></i>
                                        </div>
                                    <?php endif; ?>
                                </td>
                                <td><?= $no++ ?></td>
                                <td><strong class="text-primary"><?= htmlspecialchars($vehicle['no_reg'] ?: '-') ?></strong></td>
                                <td>
                                    <div class="vehicle-info">
                                        <strong><?= htmlspecialchars($vehicle['merk']) ?></strong>
                                        <?php if (!empty($vehicle['tipe'])): ?><br><small class="text-muted"><?= htmlspecialchars($vehicle['tipe']) ?></small><?php endif; ?>
                                    </div>
                                </td>
                                <td><?= htmlspecialchars($vehicle['tahun_pembuatan'] ?: '-') ?></td>
                                <td><span class="badge bg-secondary"><?= htmlspecialchars($vehicle['status_kendaraan'] ?: '-') ?></span></td>
                                <?php if ($current_role !== 'user'): ?>
                                <td>
                                    <?php $cu = $currentUsers[$vehicle['id']] ?? ($vehicle['current_user_name'] ?? null); ?>
                                    <?php if ($cu): ?><small class="text-muted"><?= htmlspecialchars($cu) ?></small><?php else: ?><small class="text-muted">-</small><?php endif; ?>
                                </td>
                                <?php endif; ?>
                                <td>
                                    <div class="btn-group" role="group">
                                        <a href="index.php?page=kendaraan_detail&id=<?= $vehicle['id'] ?>" class="btn btn-sm btn-outline-info" title="Lihat Detail"><i class="fas fa-eye"></i></a>
                                        <?php if ($can_crud): ?>
                                        <a href="index.php?page=kendaraan&action=edit&id=<?= $vehicle['id'] ?>" class="btn btn-sm btn-outline-primary" title="Edit"><i class="fas fa-edit"></i></a>
                                        <?php endif; ?>
                                        <?php if (can_admin()): ?>
                                        <a href="index.php?page=kendaraan&action=delete&id=<?= $vehicle['id'] ?>" class="btn btn-sm btn-outline-danger" title="Hapus"><i class="fas fa-trash"></i></a>
                                        <?php endif; ?>
                                    </div>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <tr>
                                <td colspan="<?= $current_role !== 'user' ? '10' : '9' ?>" class="text-center">
                                    <div class="empty-state">
                                        <i class="fas fa-car"></i>
                                        <h4>Tidak Ada Data Kendaraan</h4>
                                        <p>
                                            <?php if ($keyword): ?>Tidak ada kendaraan yang cocok dengan pencarian "<?= htmlspecialchars($keyword) ?>"<?php elseif ($current_role === 'user'): ?>Anda belum memiliki kendaraan yang ditugaskan<?php else: ?>Belum ada data kendaraan yang tersedia<?php endif; ?>
                                        </p>
                                        <?php if ($can_crud && !$keyword): ?>
                                        <a href="index.php?page=kendaraan&action=add" class="btn btn-primary"><i class="fas fa-plus"></i> Tambah Kendaraan Pertama</a>
                                        <?php endif; ?>
                                    </div>
                                </td>
                            </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
                <?php if ($total_pages > 1): ?>
                <div class="pagination-wrapper mt-3 d-flex justify-content-between align-items-center">
                    <div>
                        Menampilkan <?= min($total_records, $offset + 1) ?> - <?= min($total_records, $offset + $limit) ?> dari <?= $total_records ?> kendaraan
                    </div>
                    <nav aria-label="Pagination">
                        <ul class="pagination mb-0">
                            <?php if ($page_num > 1): ?>
                                <li class="page-item">
                                    <a class="page-link" href="?page=kendaraan&page_num=<?= $page_num - 1 ?>&q=<?= urlencode($keyword) ?>&sort=<?= urlencode($sort_col) ?>&dir=<?= urlencode($sort_dir) ?>" aria-label="Previous">&laquo;</a>
                                </li>
                            <?php endif; ?>
                            <?php for ($i = max(1, $page_num - 2); $i <= min($total_pages, $page_num + 2); $i++): ?>
                                <li class="page-item <?= $i == $page_num ? 'active' : '' ?>">
                                    <a class="page-link" href="?page=kendaraan&page_num=<?= $i ?>&q=<?= urlencode($keyword) ?>&sort=<?= urlencode($sort_col) ?>&dir=<?= urlencode($sort_dir) ?>"><?= $i ?></a>
                                </li>
                            <?php endfor; ?>
                            <?php if ($page_num < $total_pages): ?>
                                <li class="page-item">
                                    <a class="page-link" href="?page=kendaraan&page_num=<?= $page_num + 1 ?>&q=<?= urlencode($keyword) ?>&sort=<?= urlencode($sort_col) ?>&dir=<?= urlencode($sort_dir) ?>" aria-label="Next">&raquo;</a>
                                </li>
                            <?php endif; ?>
                        </ul>
                    </nav>
                </div>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <script>
    (function(){
        document.addEventListener('DOMContentLoaded', function(){
            var input = document.getElementById('foto');
            var preview = document.getElementById('foto-preview');
            if (!input || !preview) return;
            input.addEventListener('change', function(){
                if (!this.files || !this.files[0]) return;
                var file = this.files[0];
                if (!file.type || file.type.indexOf('image/') !== 0) return;
                var reader = new FileReader();
                reader.onload = function(e){
                    preview.src = e.target.result;
                    preview.style.display = 'inline-block';
                };
                reader.readAsDataURL(file);
            });
        });
    })();

    $(document).ready(function() {
        // `penanggung_jawab` removed: pengguna select now editable when available

        function cleanupModalState() {
            try {
                if ($('.modal.show').length === 0 && $('.modal-backdrop').length > 0) {
                    $('.modal-backdrop').remove();
                    $('body').removeClass('modal-open');
                    $('.modal').each(function() { $(this).removeClass('show').attr('aria-hidden', 'true').css('display', 'none'); });
                }
            } catch (e) { }
        }
        setInterval(cleanupModalState, 3000);
        $(document).on('click', '.btn, button, a', function() { setTimeout(cleanupModalState, 10); });
    });
    </script>
<?php endif; ?>

<!-- Import Excel Modal -->
<div class="modal fade" id="importModal" tabindex="-1" aria-labelledby="importModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
    <div class="modal-content">
      <form method="POST" enctype="multipart/form-data" action="?page=kendaraan&action=import_excel">
        <div class="modal-header">
          <h5 class="modal-title" id="importModalLabel"><i class="fas fa-file-excel"></i> Import Kendaraan dari Excel (.xlsx)</h5>
          <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
        </div>
        <div class="modal-body">
                    <div class="mb-3">
                        <label for="excel_file" class="form-label">Pilih File Excel (.xlsx) *</label>
            <input type="file" class="form-control" id="excel_file" name="excel_file" accept=".xlsx" required>
            <div class="form-text">
              Header wajib: <code>no_rangka,no_mesin,no_reg,merk,tipe,tahun_pembuatan,warna,jenis,bahan_bakar,satker,kondisi,status_kendaraan</code>.
              <a href="templates/template_kendaraan.php">Download template</a>.
            </div>
          </div>
        </div>
        <div class="modal-footer">
          <input type="hidden" name="csrf_token" value="<?= generate_csrf_token() ?>">
          <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Batal</button>
          <button type="submit" class="btn btn-primary"><i class="fas fa-upload"></i> Import Data</button>
        </div>
      </form>
    </div>  
  </div>
</div>

<!-- Export Excel Modal (pilih bulan/periode) -->
<div class="modal fade" id="exportKendaraanModal" tabindex="-1" aria-labelledby="exportKendaraanLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <form method="GET" action="">
                <div class="modal-header">
                    <h5 class="modal-title" id="exportKendaraanLabel"><i class="fas fa-file-excel me-1"></i> Export Kendaraan</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <input type="hidden" name="page" value="kendaraan" />
                    <input type="hidden" name="action" value="export_excel" />
                    <?php if ($keyword !== ''): ?>
                        <input type="hidden" name="q" value="<?= htmlspecialchars($keyword) ?>" />
                    <?php endif; ?>

                    <div class="mb-3">
                        <label for="exportMode" class="form-label">Jenis Export</label>
                        <select id="exportMode" name="mode" class="form-select">
                            <option value="daftar" selected>Daftar Kendaraan (bulanan)</option>
                            <option value="rekap_bbm">Rekap Penerimaan BBM (tahunan)</option>
                        </select>
                    </div>

                    <div class="mb-2" id="field-bulan">
                        <label for="periodeExport" class="form-label">Pilih Bulan</label>
                        <input type="month" class="form-control" id="periodeExport" name="periode" value="<?= htmlspecialchars(date('Y-m')) ?>" />
                        <div class="form-text">Dipakai untuk label dan perhitungan hari kerja pada Daftar Kendaraan.</div>
                    </div>

                    <div class="mb-2 d-none" id="field-tahun">
                        <label for="tahunExport" class="form-label">Pilih Tahun</label>
                        <input type="number" min="2000" max="2100" step="1" class="form-control" id="tahunExport" name="tahun" value="<?= (int)date('Y') ?>" />
                        <div class="form-text">Digunakan untuk rekap penerimaan BBM per bulan selama 1 tahun.</div>
                    </div>
                    <script>
                        (function(){
                            const modeSel = document.getElementById('exportMode');
                            const bulan = document.getElementById('field-bulan');
                            const tahun = document.getElementById('field-tahun');
                            const periodeInput = document.getElementById('periodeExport');
                            const tahunInput = document.getElementById('tahunExport');
                            function sync(){
                                if (!modeSel) return;
                                const isRekap = modeSel.value === 'rekap_bbm';
                                if (isRekap) {
                                    bulan.classList.add('d-none');
                                    tahun.classList.remove('d-none');
                                    if (periodeInput) periodeInput.removeAttribute('required');
                                    if (tahunInput) tahunInput.setAttribute('required','required');
                                } else {
                                    tahun.classList.add('d-none');
                                    bulan.classList.remove('d-none');
                                    if (tahunInput) tahunInput.removeAttribute('required');
                                    if (periodeInput) periodeInput.setAttribute('required','required');
                                }
                            }
                            if (modeSel) { modeSel.addEventListener('change', sync); setTimeout(sync, 0); }
                        })();
                    </script>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Batal</button>
                    <button type="submit" class="btn btn-primary"><i class="fas fa-download me-1"></i> Export</button>
                </div>
            </form>
        </div>
    </div>
</div>
<style>
    /* Ensure modals appear above sidebar/other elements */
    #importModal { z-index: 2100; }
    #importModal .modal-dialog { z-index: 2110; }
    #exportKendaraanModal { z-index: 2100; }
    #exportKendaraanModal .modal-dialog { z-index: 2110; }
    .modal-backdrop.show { z-index: 2050; }
</style>
