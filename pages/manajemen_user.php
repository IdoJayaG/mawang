<?php
require_once 'includes/auth.php';
require_admin(); // Hanya admin yang bisa akses

$action = $_POST['action'] ?? ($_GET['action'] ?? 'list');
$user_id = isset($_POST['id']) ? intval($_POST['id']) : ($_GET['id'] ?? null);
$msg = '';
$users = [];
$total_pages = 1;
$q = '';
$sort = 'username';
$dir = 'asc';
$pg = 1;

// Global helper for sortable header links (guarded to avoid redeclare)
if (!function_exists('_sort_link')) {
    function _sort_link($label, $key, $currentSort, $currentDir, $q, $pg) {
        $nextDir = ($currentSort === $key && strtolower((string)$currentDir) === 'asc') ? 'desc' : 'asc';
        $icon = '';
        if ($currentSort === $key) {
            $icon = strtolower((string)$currentDir) === 'asc' ? ' <i class="fas fa-sort-up"></i>' : ' <i class="fas fa-sort-down"></i>';
        } else {
            $icon = ' <i class="fas fa-sort text-muted"></i>';
        }
        $qs = http_build_query([
            'page' => 'manajemen_user',
            'action' => 'list',
            'q' => $q,
            'sort' => $key,
            'dir' => $nextDir,
            'pg' => 1
        ]);
        return '<a href="?' . $qs . '" class="text-decoration-none">' . htmlspecialchars($label) . $icon . '</a>';
    }
}

// Export Excel: list of users (filtered by q)
if ($action === 'export_excel') {
    // Admin already enforced by require_admin()
    $autoload = dirname(__DIR__) . '/vendor/autoload.php';
    if (!file_exists($autoload)) {
        $msg = '<div class="alert alert-danger">Library PhpSpreadsheet tidak ditemukan. Tidak bisa export Excel.</div>';
    } else {
        require_once $autoload;

        // Reuse list filters (search by username/nama/role)
        $q = trim($_GET['q'] ?? '');

        $from = " FROM user_account ua LEFT JOIN pengguna p ON ua.pengguna_id = p.id LEFT JOIN role r ON ua.role_id = r.id ";
        $from_with_login = $from . " LEFT JOIN (\n        SELECT user_id, MAX(created_at) AS last_login\n        FROM log_aktivitas\n        WHERE LOWER(activity_type) = 'login'\n        GROUP BY user_id\n    ) ll ON ll.user_id = p.id ";

        $where = '';
        $params = [];
        $types = '';
        if ($q !== '') {
            $where = ' WHERE (ua.username LIKE ? OR p.nama_lengkap LIKE ? OR r.nama_role LIKE ?)';
            $like = "%{$q}%";
            $params = [$like, $like, $like];
            $types = 'sss';
        }
        // Exclude Operator role from admin listing/export
        $excludeOp = "LOWER(COALESCE(r.kode_role, r.nama_role, '')) <> 'operator'";
        if ($where === '') {
            $where = ' WHERE ' . $excludeOp;
        } else {
            $where .= ' AND ' . $excludeOp;
        }

        $sql = 'SELECT ua.username, ua.status, COALESCE(ll.last_login, ua.last_login) AS last_login, ua.created_at, '
             . 'p.nama_lengkap, p.pangkat, p.nrp_nip, p.email, p.no_hp, p.jabatan, r.nama_role '
             . $from_with_login . $where . ' ORDER BY ua.username ASC';

        $stmt = $mysqli->prepare($sql);
        if ($stmt) {
            if (!empty($params)) {
                $bindArgs = [$types];
                foreach ($params as $k => $v) { $bindArgs[] = &$params[$k]; }
                call_user_func_array([$stmt, 'bind_param'], $bindArgs);
            }
            $stmt->execute();
            $rows = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
            $stmt->close();
        } else {
            $rows = [];
        }

        // Build spreadsheet
        $spreadsheet = new \PhpOffice\PhpSpreadsheet\Spreadsheet();
        $sheet = $spreadsheet->getActiveSheet();
        $sheet->setTitle('Users');

    // Column widths (trimmed columns: removed Role, Status, Last Login, Created At)
    $sheet->getColumnDimension('A')->setWidth(6);   // No
    $sheet->getColumnDimension('B')->setWidth(20);  // Username
    $sheet->getColumnDimension('C')->setWidth(30);  // Nama
    $sheet->getColumnDimension('D')->setWidth(18);  // Pangkat
    $sheet->getColumnDimension('E')->setWidth(22);  // NRP/NIP
    $sheet->getColumnDimension('F')->setWidth(22);  // Jabatan
    $sheet->getColumnDimension('G')->setWidth(28);  // Email
    $sheet->getColumnDimension('H')->setWidth(18);  // No HP

    // Header (removed ROLE, STATUS, LAST LOGIN, CREATED AT)
    $sheet->fromArray([[ 'NO','USERNAME','NAMA','PANGKAT','NRP/NIP','JABATAN','EMAIL','NO HP' ]], null, 'A1');
    $sheet->getStyle('A1:H1')->getFont()->setBold(true);
    $sheet->getStyle('A1:H1')->getAlignment()->setHorizontal(\PhpOffice\PhpSpreadsheet\Style\Alignment::HORIZONTAL_CENTER);

        $r = 2; $no = 1;
        foreach ($rows as $row) {
            $sheet->setCellValueExplicit('A'.$r, $no++, \PhpOffice\PhpSpreadsheet\Cell\DataType::TYPE_NUMERIC);
            $sheet->setCellValue('B'.$r, (string)($row['username'] ?? ''));
            $sheet->setCellValue('C'.$r, (string)($row['nama_lengkap'] ?? ''));
            $sheet->setCellValue('F'.$r, (string)($row['jabatan'] ?? ''));
            // Shift Email and No HP to columns G and H
            $sheet->setCellValue('G'.$r, (string)($row['email'] ?? ''));
            $sheet->setCellValue('H'.$r, (string)($row['no_hp'] ?? ''));
            if ($ca !== '') {
                try {
                    $dt2 = new DateTime($ca);
                    $excelDate2 = \PhpOffice\PhpSpreadsheet\Shared\Date::PHPToExcel($dt2);
                    $sheet->setCellValue('J'.$r, $excelDate2);
                    $sheet->getStyle('J'.$r)->getNumberFormat()->setFormatCode('dd/mm/yyyy hh:mm');
                } catch (\Exception $e) { $sheet->setCellValue('J'.$r, ''); }
            } else { $sheet->setCellValue('J'.$r, ''); }
            $sheet->setCellValue('K'.$r, (string)($row['email'] ?? ''));
            $sheet->setCellValue('L'.$r, (string)($row['no_hp'] ?? ''));
            $r++;
        }

        // Borders and alignment
        $last = max(1, $r-1);
    $sheet->getStyle('A1:H'.$last)->getBorders()->getAllBorders()->setBorderStyle(\PhpOffice\PhpSpreadsheet\Style\Border::BORDER_THIN);
    $sheet->getStyle('A2:A'.$last)->getAlignment()->setHorizontal(\PhpOffice\PhpSpreadsheet\Style\Alignment::HORIZONTAL_CENTER);

        // Activity log
        if (function_exists('log_activity')) {
            $ctx = [];
            if ($q !== '') { $ctx[] = 'q="'. $q .'"'; }
            log_activity('EXPORT_USERS', 'Export Manajemen User ' . (is_array($rows)?count($rows):0) . ' baris' . (!empty($ctx)?(' ['.implode('; ', $ctx).']') : ''));
        }

        // Output headers and file
        if (function_exists('ini_get') && function_exists('ini_set') && ini_get('zlib.output_compression')) { @ini_set('zlib.output_compression', 'Off'); }
        if (function_exists('ob_get_level')) { while (ob_get_level() > 0) { @ob_end_clean(); } }
        $writer = \PhpOffice\PhpSpreadsheet\IOFactory::createWriter($spreadsheet, 'Xlsx');
        header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
        header('Content-Disposition: attachment; filename="manajemen_user.xlsx"');
        header('Cache-Control: max-age=0');
        header('Pragma: public');
        header('Expires: 0');
        $writer->save('php://output');
        exit;
    }
}

// Prepare list data (search/sort/pagination) for list view
if ($action === 'list') {
    // Inputs
    $q = trim($_GET['q'] ?? '');
    $sort = $_GET['sort'] ?? 'username';
    $dir = strtolower($_GET['dir'] ?? 'asc');
    $pg = max(1, intval($_GET['pg'] ?? 1));
    $limit = 10;
    $offset = ($pg - 1) * $limit;

    // Whitelist sort keys
    $sort_map = [
        'username' => 'ua.username',
        'nama' => 'p.nama_lengkap',
        'role' => 'r.nama_role',
        'status' => 'ua.status',
        // Use alias from joined log_aktivitas subquery
        'last_login' => 'last_login',
        'created_at' => 'ua.created_at',
    ];
    $order_col = $sort_map[$sort] ?? $sort_map['username'];
    $order_dir = ($dir === 'desc') ? 'DESC' : 'ASC';

    // Base FROM/JOIN
    $from = " FROM user_account ua LEFT JOIN pengguna p ON ua.pengguna_id = p.id LEFT JOIN role r ON ua.role_id = r.id ";
    // Extend FROM with last login from log_aktivitas (activity_type = LOGIN) for data query
    $from_with_login = $from . " LEFT JOIN (\n        SELECT user_id, MAX(created_at) AS last_login\n        FROM log_aktivitas\n        WHERE LOWER(activity_type) = 'login'\n        GROUP BY user_id\n    ) ll ON ll.user_id = p.id ";

    // Filtering
    $where = '';
    $params = [];
    $types = '';
    if ($q !== '') {
        $where = ' WHERE (ua.username LIKE ? OR p.nama_lengkap LIKE ? OR r.nama_role LIKE ?)';
        $like = "%{$q}%";
        $params = [$like, $like, $like];
        $types = 'sss';
    }

    // Exclude Operator role from admin listing
    $excludeOp = "LOWER(COALESCE(r.kode_role, r.nama_role, '')) <> 'operator'";
    if ($where === '') {
        $where = ' WHERE ' . $excludeOp;
    } else {
        $where .= ' AND ' . $excludeOp;
    }

    // Count total
    $sql_count = 'SELECT COUNT(*) AS cnt' . $from . $where;
    if ($stmt = $mysqli->prepare($sql_count)) {
        if (!empty($params)) {
            // build refs for bind_param
            $bindArgs = [$types];
            foreach ($params as $k => $v) { $bindArgs[] = &$params[$k]; }
            call_user_func_array([$stmt, 'bind_param'], $bindArgs);
        }
        $stmt->execute();
        $res = $stmt->get_result();
        $row = $res ? $res->fetch_assoc() : null;
        $total = $row ? intval($row['cnt']) : 0;
        $stmt->close();
    } else {
        $total = 0;
    }
    $total_pages = max(1, (int)ceil($total / $limit));
    if ($pg > $total_pages) { $pg = $total_pages; $offset = ($pg - 1) * $limit; }

    // Fetch page data
    $sql_data = 'SELECT ua.id, ua.username, ua.status, ' .
                // Prefer last_login from logs; fallback to ua.last_login for legacy/null cases
                'COALESCE(ll.last_login, ua.last_login) AS last_login, ' .
                'ua.created_at, p.nama_lengkap, p.pangkat, p.nrp_nip, p.jabatan, r.nama_role' .
                $from_with_login . $where . " ORDER BY {$order_col} {$order_dir} LIMIT ? OFFSET ?";
    // Append limit/offset to params
    $params2 = $params;
    $types2 = $types . 'ii';
    $params2[] = $limit;
    $params2[] = $offset;
    if ($stmt = $mysqli->prepare($sql_data)) {
        if (!empty($params2)) {
            $bindArgs2 = [$types2];
            foreach ($params2 as $k => $v) { $bindArgs2[] = &$params2[$k]; }
            call_user_func_array([$stmt, 'bind_param'], $bindArgs2);
        }
        $stmt->execute();
        $res = $stmt->get_result();
        $users = [];
        if ($res) {
            while ($r = $res->fetch_assoc()) { $users[] = $r; }
        }
        $stmt->close();
    } else {
        $users = [];
    }
}

// Handle form submissions (fixed structure)
if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    // Fast CSRF check for AJAX toggle
    if ($action === 'toggle_status' && !validate_csrf_token($_POST['csrf_token'] ?? '')) {
        if (function_exists('ob_get_level')) { while (ob_get_level() > 0) { ob_end_clean(); } }
        header('Content-Type: application/json');
        echo json_encode(['success' => false, 'error' => 'Token keamanan tidak valid.']);
        exit;
    }

    if (!validate_csrf_token($_POST['csrf_token'] ?? '')) {
        $msg = '<div class="alert alert-danger">Token keamanan tidak valid!</div>';
    } else {

        // 1. RESET PASSWORD
        if ($action === 'reset_password' && $user_id) {
            $new_password     = trim($_POST['new_password'] ?? '');
            $confirm_password = trim($_POST['confirm_password'] ?? '');
            if ($new_password === '' || $confirm_password === '') {
                $msg = '<div class="alert alert-danger">Password baru dan konfirmasi wajib diisi!</div>';
            } elseif ($new_password !== $confirm_password) {
                $msg = '<div class="alert alert-danger">Konfirmasi password tidak cocok!</div>';
            } elseif (strlen($new_password) < 6) {
                $msg = '<div class="alert alert-danger">Password minimal 6 karakter!</div>';
            } else {
                $new_hash = hash_password($new_password);
                if ($stmt = $mysqli->prepare("UPDATE user_account SET password = ? WHERE id = ?")) {
                    $stmt->bind_param('si', $new_hash, $user_id);
                    if ($stmt->execute()) {
                        $stmt2 = $mysqli->prepare("SELECT username FROM user_account WHERE id = ?");
                        $stmt2->bind_param('i', $user_id);
                        $stmt2->execute();
                        $stmt2->bind_result($username);
                        $stmt2->fetch();
                        $stmt2->close();
                        log_activity("RESET_PASSWORD", "Admin mereset password user: $username");
                        header('Location: ?page=manajemen_user&pwdreset=1');
                        exit;
                    } else {
                        $msg = '<div class="alert alert-danger">Gagal mereset password!</div>';
                    }
                    $stmt->close();
                } else {
                    $msg = '<div class="alert alert-danger">Gagal menyiapkan query!</div>';
                }
            }
        }

        // 2. TOGGLE STATUS (AJAX)
        elseif ($action === 'toggle_status' && $user_id) {
            $incoming = trim($_POST['status'] ?? '');
            $new_status_ua = ($incoming === 'Aktif') ? 'Aktif' : 'Tidak Aktif';
            $new_status_pengguna = ($new_status_ua === 'Aktif') ? 'Aktif' : 'Tidak Aktif';
            $mysqli->begin_transaction();
            try {
                $stmt1 = $mysqli->prepare("UPDATE user_account SET status = ?, updated_at = NOW() WHERE id = ?");
                if (!$stmt1) throw new Exception;
                $stmt1->bind_param('si', $new_status_ua, $user_id);
                if (!$stmt1->execute()) throw new Exception;
                $stmt1->close();

                $stmt2 = $mysqli->prepare("UPDATE pengguna p JOIN user_account ua ON ua.pengguna_id = p.id SET p.status_aktif = ? WHERE ua.id = ?");
                if (!$stmt2) throw new Exception;
                $stmt2->bind_param('si', $new_status_pengguna, $user_id);
                if (!$stmt2->execute()) throw new Exception;
                $stmt2->close();

                $mysqli->commit();
                if (function_exists('ob_get_level')) { while (ob_get_level() > 0) { ob_end_clean(); } }
                header('Content-Type: application/json');
                echo json_encode([
                    'success' => true,
                    'new_status'    => $new_status_ua,
                    'badge_class'   => $new_status_ua === 'Aktif' ? 'bg-success' : 'bg-secondary',
                    'button_class'  => $new_status_ua === 'Aktif' ? 'btn-outline-secondary' : 'btn-outline-success',
                    'button_title'  => $new_status_ua === 'Aktif' ? 'Nonaktifkan User' : 'Aktifkan User',
                    'button_icon'   => $new_status_ua === 'Aktif' ? 'times' : 'check',
                    'next_status'   => $new_status_ua === 'Aktif' ? 'Tidak Aktif' : 'Aktif'
                ]);
                exit;
            } catch (Exception $e) {
                $mysqli->rollback();
                if (function_exists('ob_get_level')) { while (ob_get_level() > 0) { ob_end_clean(); } }
                header('Content-Type: application/json');
                echo json_encode(['success' => false, 'error' => 'Gagal menyimpan perubahan.']);
                exit;
            }
        }

        // 3. CREATE USER
        elseif ($action === 'create') {
            $use_existing = isset($_POST['use_existing']) && $_POST['use_existing'] === '1';
            $username = trim($_POST['username'] ?? '');
            $password = trim($_POST['password'] ?? '');
            $role_id  = intval($_POST['role_id'] ?? 0);

            if (empty($username) || empty($password) || $role_id <= 0) {
                $msg = '<div class="alert alert-danger">Username, password, dan role wajib diisi!</div>';
            } elseif (strlen($password) < 6) {
                $msg = '<div class="alert alert-danger">Password minimal 6 karakter!</div>';
            } else {
                // Cek username unik
                $chk = $mysqli->prepare("SELECT id FROM user_account WHERE username = ? LIMIT 1");
                $chk->bind_param('s', $username);
                $chk->execute();
                $exists = $chk->get_result()->fetch_assoc();
                $chk->close();
                if ($exists) {
                    $msg = '<div class="alert alert-danger">Username sudah digunakan!</div>';
                } else {
                    $mysqli->begin_transaction();
                    try {
                        if ($use_existing) {
                            $pengguna_id = intval($_POST['pengguna_id'] ?? 0);
                            if ($pengguna_id <= 0) throw new Exception('Pilih data pengguna yang valid.');
                        } else {
                            // Data pengguna baru
                            $nama_lengkap = trim($_POST['nama_lengkap'] ?? '');
                            $email = trim($_POST['email'] ?? '');
                            $pangkat = trim($_POST['pangkat'] ?? '');
                            $nrp_nip = trim($_POST['nrp_nip'] ?? '');
                            $no_hp = trim($_POST['no_hp'] ?? '');
                            $alamat = trim($_POST['alamat'] ?? '');
                            $jabatan = trim($_POST['jabatan'] ?? '');
                            $kesatuan = trim($_POST['kesatuan'] ?? '') ?: null;
                            $status_pegawai = trim($_POST['status_pegawai'] ?? 'Aktif');
                            $status_aktif_pengguna = trim($_POST['status_aktif'] ?? 'Aktif');
                            $jenis_personel = trim($_POST['jenis_personel'] ?? '');
                            $matra = trim($_POST['matra'] ?? '') ?: null;
                            $korps = trim($_POST['korps'] ?? '') ?: null;
                            $satuan_pns = trim($_POST['satuan_pns'] ?? '') ?: null;

                            if ($jenis_personel === 'TNI') {
                                $satuan_pns = null;
                            } elseif ($jenis_personel === 'PNS') {
                                $matra = null;
                                $korps = null;
                                $kesatuan = trim($_POST['kesatuan'] ?? '') ?: null;
                            }

                            if (empty($nama_lengkap) || empty($email)) throw new Exception('Nama lengkap dan email wajib diisi.');
                            if (!filter_var($email, FILTER_VALIDATE_EMAIL)) throw new Exception('Format email tidak valid.');
                            if ($jenis_personel === 'TNI' && trim($nrp_nip) === '') {
                                throw new Exception('NRP/NIP wajib diisi untuk personel TNI.');
                            }
                            // Cek email unik
                            $cekEmail = $mysqli->prepare("SELECT 1 FROM pengguna WHERE email = ? LIMIT 1");
                            $cekEmail->bind_param('s', $email);
                            $cekEmail->execute();
                            if ($cekEmail->get_result()->fetch_assoc()) {
                                $cekEmail->close();
                                throw new Exception('Email sudah digunakan.');
                            }
                            $cekEmail->close();

                            $stmtp = $mysqli->prepare("INSERT INTO pengguna (nama_lengkap, email, pangkat, nrp_nip, no_hp, alamat, jabatan, kesatuan, status_pegawai, status_aktif, jenis_personel, matra, korps, satuan_pns, created_at) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())");
                            if (!$stmtp) throw new Exception('Prepare pengguna gagal: ' . $mysqli->error);
                            $stmtp->bind_param('ssssssssssssss', $nama_lengkap, $email, $pangkat, $nrp_nip, $no_hp, $alamat, $jabatan, $kesatuan, $status_pegawai, $status_aktif_pengguna, $jenis_personel, $matra, $korps, $satuan_pns);
                            if (!$stmtp->execute()) throw new Exception('Gagal menyimpan pengguna.');
                            $pengguna_id = $mysqli->insert_id;
                            $stmtp->close();
                        }

                        $password_hash = hash_password($password);
                        $stmtu = $mysqli->prepare("INSERT INTO user_account (username, password, pengguna_id, role_id, status, created_at) VALUES (?, ?, ?, ?, 'Aktif', NOW())");
                        if (!$stmtu) throw new Exception('Prepare user_account gagal: ' . $mysqli->error);
                        $stmtu->bind_param('ssii', $username, $password_hash, $pengguna_id, $role_id);
                        if (!$stmtu->execute()) throw new Exception('Gagal membuat akun user.');
                        $stmtu->close();

                        $mysqli->commit();
                        log_activity('CREATE_USER', "Admin membuat user: $username");
                        header('Location: ?page=manajemen_user&created=1');
                        exit;
                    } catch (Exception $e) {
                        $mysqli->rollback();
                        $msg = '<div class="alert alert-danger">Error: ' . htmlspecialchars($e->getMessage()) . '</div>';
                    }
                }
            }
        }

        // 4. IMPORT CSV (XLSX)
        elseif ($action === 'import_csv') {
            // Import Excel (.xlsx) with header: nrp,nama_lengkap,jabatan,pangkat,no_hp,email,jenis_personel,matra,korps,kesatuan,[username,password,alamat,role]
            if (isset($_FILES['excel_file']) && $_FILES['excel_file']['error'] === UPLOAD_ERR_OK) {
                $excel_file = $_FILES['excel_file']['tmp_name'];
                $file_extension = strtolower(pathinfo($_FILES['excel_file']['name'], PATHINFO_EXTENSION));

                if ($file_extension !== 'xlsx') {
                    $msg = '<div class="alert alert-danger">File harus berformat .xlsx!</div>';
                } else {
                    if (!class_exists('PhpOffice\\PhpSpreadsheet\\IOFactory')) {
                        // Try composer's autoload at project root
                        $autoload = dirname(__DIR__) . '/vendor/autoload.php';
                        if (file_exists($autoload)) { require_once $autoload; }
                    }
                    if (!class_exists('PhpOffice\\PhpSpreadsheet\\IOFactory')) {
                        $msg = '<div class="alert alert-danger">Library PhpSpreadsheet tidak ditemukan.</div>';
                    } else {
                        $imported = 0;
                        $errors = [];
                        try {
                            $spreadsheet = \PhpOffice\PhpSpreadsheet\IOFactory::load($excel_file);
                            $sheet = $spreadsheet->getActiveSheet();
                            $rows = $sheet->toArray(null, true, true, true);
                            if (!$rows || count($rows) < 2) { throw new Exception('File kosong atau tidak memiliki data.'); }
                            $headerRow = array_shift($rows);
                            $map = [];
                            foreach ($headerRow as $col => $val) {
                                $key = strtolower(trim((string)$val));
                                $key = str_replace([' ', '-'], '_', $key);
                                $map[$key] = $col;
                            }
                            $required = ['nrp','nama_lengkap','jabatan','pangkat','no_hp','email','jenis_personel','matra','korps','kesatuan'];
                            foreach ($required as $req) { if (!isset($map[$req])) { throw new Exception('Header tidak lengkap. Wajib: ' . implode(',', $required)); } }
                            // Prefetch roles and default role (user)
                            $rolesMap = [];
                            $defaultRoleId = null;
                            $rs = $mysqli->query("SELECT id, nama_role FROM role");
                            if ($rs) {
                                while ($r = $rs->fetch_assoc()) {
                                    $rolesMap[strtolower($r['nama_role'])] = (int)$r['id'];
                                }
                            }
                            $defaultRoleId = $rolesMap['user'] ?? null;
                            if (!$defaultRoleId) { throw new Exception('Role "user" tidak ditemukan.'); }
                            foreach ($rows as $i => $dataRow) {
                                $nrp = trim((string)($dataRow[$map['nrp']] ?? ''));
                                $nama_lengkap = trim((string)($dataRow[$map['nama_lengkap']] ?? ''));
                                $jabatan = trim((string)($dataRow[$map['jabatan']] ?? ''));
                                $pangkat = trim((string)($dataRow[$map['pangkat']] ?? ''));
                                $no_hp = trim((string)($dataRow[$map['no_hp']] ?? ''));
                                $email = trim((string)($dataRow[$map['email']] ?? ''));
                                $jenis_personel = strtoupper(trim((string)($dataRow[$map['jenis_personel']] ?? '')));
                                $matra = trim((string)($dataRow[$map['matra']] ?? ''));
                                $korps = trim((string)($dataRow[$map['korps']] ?? ''));
                                $kesatuan = trim((string)($dataRow[$map['kesatuan']] ?? ''));
                                // Optional fields
                                $username_input = isset($map['username']) ? trim((string)($dataRow[$map['username']] ?? '')) : '';
                                $password_input = isset($map['password']) ? (string)($dataRow[$map['password']] ?? '') : '';
                                $alamat_input = isset($map['alamat']) ? trim((string)($dataRow[$map['alamat']] ?? '')) : '';
                                $role_input = isset($map['role']) ? strtolower(trim((string)($dataRow[$map['role']] ?? ''))) : '';
                                if (!$nama_lengkap || (!$email && !$nrp)) { $errors[] = 'Baris ' . (intval($i)+2) . ': nama_lengkap dan (email atau nrp) wajib diisi.'; continue; }
                                // Determine username
                                $username = '';
                                if ($username_input !== '') {
                                    $username = strtolower(preg_replace('/[^a-z0-9_\.]+/', '', $username_input));
                                    if ($username === '') {
                                        $errors[] = 'Baris ' . (intval($i)+2) . ': Username tidak valid.'; 
                                        continue;
                                    }
                                    $chk = $mysqli->prepare("SELECT 1 FROM user_account WHERE username = ? LIMIT 1");
                                    $chk->bind_param('s', $username);
                                    $chk->execute();
                                    $exists = $chk->get_result()->fetch_assoc();
                                    $chk->close();
                                    if ($exists) { $errors[] = 'Baris ' . (intval($i)+2) . ': Username sudah digunakan.'; continue; }
                                } else {
                                    $base_username = '';
                                    if ($email && strpos($email, '@') !== false) { $base_username = strtolower(preg_replace('/[^a-z0-9_\.]+/','', strtok($email, '@'))); }
                                    if (empty($base_username) && $nrp) { $base_username = strtolower(preg_replace('/[^a-z0-9]+/','', $nrp)); }
                                    if (empty($base_username)) { $base_username = strtolower(preg_replace('/[^a-z0-9]+/','', substr($nama_lengkap,0,12))); }
                                    if (empty($base_username)) { $base_username = 'user'; }
                                    $username = $base_username; $suffix = 1;
                                    $chk = $mysqli->prepare("SELECT 1 FROM user_account WHERE username = ? LIMIT 1");
                                    while (true) {
                                        $chk->bind_param('s', $username);
                                        $chk->execute();
                                        $exists = $chk->get_result()->fetch_assoc();
                                        if (!$exists) break;
                                        $username = $base_username . $suffix; $suffix++;
                                    }
                                    $chk->close();
                                }
                                if ($email) {
                                    $chk2 = $mysqli->prepare("SELECT 1 FROM pengguna WHERE email = ? LIMIT 1");
                                    $chk2->bind_param('s', $email);
                                    $chk2->execute();
                                    $existsEmail = $chk2->get_result()->fetch_assoc();
                                    $chk2->close();
                                    if ($existsEmail) { $errors[] = 'Baris ' . (intval($i)+2) . ': Email sudah digunakan.'; continue; }
                                }
                                $mysqli->begin_transaction();
                                try {
                                    $status_pegawai = 'Aktif'; $status_aktif_pengguna = 'Aktif';
                                    $alamat = $alamat_input !== '' ? $alamat_input : null;
                                    if ($jenis_personel !== 'TNI' && $jenis_personel !== 'PNS') { $jenis_personel = 'TNI'; }
                                    $satuan_pns = ($jenis_personel === 'PNS') ? ($kesatuan ?: null) : null;
                                    $stmtp = $mysqli->prepare("INSERT INTO pengguna (nama_lengkap, email, pangkat, nrp_nip, no_hp, alamat, jabatan, kesatuan, status_pegawai, status_aktif, jenis_personel, matra, korps, satuan_pns, created_at) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())");
                                    if (!$stmtp) { throw new Exception('Prepare pengguna gagal: ' . $mysqli->error); }
                                    $stmtp->bind_param('ssssssssssssss', $nama_lengkap, $email, $pangkat, $nrp, $no_hp, $alamat, $jabatan, $kesatuan, $status_pegawai, $status_aktif_pengguna, $jenis_personel, $matra, $korps, $satuan_pns);
                                    if (!$stmtp->execute()) { throw new Exception('Insert pengguna gagal: ' . $mysqli->error); }
                                    $pengguna_id = $mysqli->insert_id; $stmtp->close();
                                    // Determine password
                                    $raw_password = null;
                                    if ($password_input !== '') {
                                        if (strlen($password_input) < 6) {
                                            $errors[] = 'Baris ' . (intval($i)+2) . ': Password kurang dari 6 karakter; diisi acak.';
                                            $raw_password = bin2hex(random_bytes(4));
                                        } else {
                                            $raw_password = $password_input;
                                        }
                                    } else {
                                        $raw_password = bin2hex(random_bytes(4));
                                    }
                                    $password_hash = hash_password($raw_password);
                                    // Determine role
                                    $role_id = $defaultRoleId;
                                    if ($role_input !== '') {
                                        $role_key = $role_input;
                                        if ($role_key === 'admin') { $role_key = 'administrator'; }
                                        if ($role_key === 'sopir') { $role_key = 'driver'; }
                                        if ($role_key === 'leader') { $role_key = 'pimpinan'; }
                                        if (isset($rolesMap[$role_key])) { $role_id = $rolesMap[$role_key]; }
                                        else { $errors[] = 'Baris ' . (intval($i)+2) . ': Role tidak dikenali; menggunakan User.'; }
                                    }
                                    $stmtu = $mysqli->prepare("INSERT INTO user_account (username, password, pengguna_id, role_id, status, created_at) VALUES (?, ?, ?, ?, 'Aktif', NOW())");
                                    if (!$stmtu) { throw new Exception('Prepare user_account gagal: ' . $mysqli->error); }
                                    $stmtu->bind_param('ssii', $username, $password_hash, $pengguna_id, $role_id);
                                    if (!$stmtu->execute()) { throw new Exception('Insert user_account gagal: ' . $mysqli->error); }
                                    $stmtu->close();
                                    $mysqli->commit();
                                    $imported++;
                                    if (function_exists('log_activity')) { log_activity('IMPORT_USER', 'Import user: ' . $username); }
                                } catch (Exception $eRow) {
                                    $mysqli->rollback();
                                    $errors[] = 'Baris ' . (intval($i)+2) . ': ' . $eRow->getMessage();
                                }
                            }
                            $msg = '<div class="alert alert-success">Berhasil import ' . $imported . ' user!</div>';
                            if (!empty($errors)) {
                                $msg .= '<div class="alert alert-warning">Beberapa data gagal diimport:<ul>';
                                foreach ($errors as $error) { $msg .= '<li>' . htmlspecialchars($error) . '</li>'; }
                                $msg .= '</ul></div>';
                            }
                            $action = 'list';
                        } catch (Exception $e) {
                            $msg = '<div class="alert alert-danger">Gagal memproses file: ' . htmlspecialchars($e->getMessage()) . '</div>';
                        }
                    }
                }
            } else {
                // Jika tidak ada file diupload atau terjadi error upload
                $msg = '<div class="alert alert-danger">File Excel (.xlsx) harus dipilih!</div>';
            }
        } elseif ($action === 'edit' && $user_id) { // end: elseif ($action === 'import_csv')
            // Update existing user and pengguna info
            $nama_lengkap = trim($_POST['nama_lengkap'] ?? '');
            $email = trim($_POST['email'] ?? '');
            $pangkat = trim($_POST['pangkat'] ?? '');
            $nrp_nip = trim($_POST['nrp_nip'] ?? '');
            $no_hp = trim($_POST['no_hp'] ?? '');
            $alamat = trim($_POST['alamat'] ?? '');
            $jabatan = trim($_POST['jabatan'] ?? '');
            $status_pegawai = trim($_POST['status_pegawai'] ?? 'Aktif');
            $status_aktif_pengguna = trim($_POST['status_aktif'] ?? 'Aktif');
            $role_id = intval($_POST['role_id'] ?? 0);
            
            // Handle TNI/PNS fields
            $jenis_personel = trim($_POST['jenis_personel'] ?? '');
            if ($jenis_personel === 'TNI') {
                $matra = trim($_POST['matra'] ?? '') ?: null;
                $korps = trim($_POST['korps'] ?? '') ?: null;
                $kesatuan = trim($_POST['kesatuan'] ?? '') ?: null;
                $satuan_pns = null;
            } elseif ($jenis_personel === 'PNS') {
                $satuan_pns = trim($_POST['satuan_pns'] ?? '') ?: null;
                $matra = null; $korps = null; $kesatuan = null;
            } else {
                $matra = $korps = $kesatuan = $satuan_pns = null;
            }

            if (empty($nama_lengkap) || empty($email) || $role_id <= 0) {
                $msg = '<div class="alert alert-danger">Nama lengkap, email, dan role wajib diisi!</div>';
            } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
                $msg = '<div class="alert alert-danger">Format email tidak valid!</div>';
            } elseif ($jenis_personel === 'TNI' && trim($nrp_nip) === '') {
                $msg = '<div class="alert alert-danger">NRP/NIP wajib diisi untuk personel TNI!</div>';
            } else {
                // Cek email digunakan user lain
                $stmtEmail = $mysqli->prepare("
                    SELECT 1 
                    FROM pengguna p 
                    JOIN user_account ua2 ON ua2.pengguna_id = p.id 
                    WHERE p.email = ? AND ua2.id <> ? LIMIT 1
                ");
                if ($stmtEmail) {
                    $stmtEmail->bind_param('si', $email, $user_id);
                    $stmtEmail->execute();
                    $dup = $stmtEmail->get_result()->fetch_assoc();
                    $stmtEmail->close();
                    if ($dup) {
                        $msg = '<div class="alert alert-danger">Email sudah digunakan oleh user lain.</div>';
                        goto end_post_handlers;
                    }
                }

                $mysqli->begin_transaction();
                try {
                    $stmtp = $mysqli->prepare("
                        UPDATE pengguna p 
                        JOIN user_account ua ON ua.pengguna_id = p.id 
                        SET p.nama_lengkap = ?, p.email = ?, p.pangkat = ?, p.nrp_nip = ?, p.no_hp = ?, 
                            p.alamat = ?, p.jabatan = ?, p.kesatuan = ?, p.status_pegawai = ?, p.status_aktif = ?, 
                            p.jenis_personel = ?, p.matra = ?, p.korps = ?, p.satuan_pns = ?
                        WHERE ua.id = ?
                    ");
                    $stmtp->bind_param(
                        'ssssssssssssssi',
                        $nama_lengkap, $email, $pangkat, $nrp_nip, $no_hp,
                        $alamat, $jabatan, $kesatuan, $status_pegawai, $status_aktif_pengguna,
                        $jenis_personel, $matra, $korps, $satuan_pns, $user_id
                    );
                    if (!$stmtp->execute()) throw new Exception('Gagal update data pengguna: ' . $mysqli->error);
                    $stmtp->close();

                    $statu = $mysqli->prepare("UPDATE user_account SET role_id = ? WHERE id = ?");
                    // Fix binding: second parameter must be user_id, not nama_lengkap
                    $statu->bind_param('ii', $role_id, $user_id);
                    if (!$statu->execute()) throw new Exception('Gagal update akun user: ' . $mysqli->error);
                    $statu->close();

                    $mysqli->commit();
                    log_activity('EDIT_USER', "Admin mengubah user: $nama_lengkap");
                    header('Location: ?page=manajemen_user&updated=1');
                    exit;
                } catch (Exception $e) {
                    $mysqli->rollback();
                    $msg = '<div class="alert alert-danger">Error: ' . htmlspecialchars($e->getMessage()) . '</div>';
                }
            }
        }
    }
}

// Label keluar lebih awal (legacy)
end_post_handlers:

// PRG success flag handler
if ($msg === '') {
    if (isset($_GET['updated']) && $_GET['updated'] === '1') {
        $msg = '<div class="alert alert-success">User berhasil diperbarui.</div>';
    } elseif (isset($_GET['created']) && $_GET['created'] === '1') {
        $msg = '<div class="alert alert-success">User baru berhasil dibuat.</div>';
    } elseif (isset($_GET['pwdreset']) && $_GET['pwdreset'] === '1') {
        $msg = '<div class="alert alert-success">Password user berhasil direset.</div>';
    }
}

// Get user data for reset password form
$user_data = null;
if ($action === 'reset_password' && $user_id) {
    $stmt = $mysqli->prepare("
        SELECT ua.id, ua.username, p.nama_lengkap, r.nama_role 
        FROM user_account ua 
        JOIN pengguna p ON ua.pengguna_id = p.id 
        JOIN role r ON ua.role_id = r.id 
        WHERE ua.id = ?
    ");
    $stmt->bind_param('i', $user_id);
    $stmt->execute();
    $user_data = $stmt->get_result()->fetch_assoc();
    $stmt->close();
}

// Get user data for edit form
if ($action === 'edit' && $user_id) {
    $stmt = $mysqli->prepare("SELECT ua.id as ua_id, ua.username, ua.role_id, p.* FROM user_account ua JOIN pengguna p ON ua.pengguna_id = p.id WHERE ua.id = ?");
    $stmt->bind_param('i', $user_id);
    $stmt->execute();
    $edit_data = $stmt->get_result()->fetch_assoc();
    $stmt->close();
} else {
    $edit_data = null;
}

?>

<div class="page-header">
    <h1><i class="fas fa-users-cog"></i> Manajemen User</h1>
    <div class="header-actions">
        <?php if ($action === 'list'): ?>
            <div class="btn-group" role="group">
                <a href="?page=manajemen_user&action=create" class="btn btn-success btn-lg">
                    <i class="fas fa-user-plus me-1"></i> Tambah User
                </a>
                <a href="?page=manajemen_user&action=import_csv" class="btn btn-secondary btn-lg">
                    <i class="fas fa-file-import me-1"></i> Import Excel
                </a>
                <a href="?page=manajemen_user&action=export_excel<?= !empty($q) ? '&q=' . urlencode($q) : '' ?>" class="btn btn-outline-secondary btn-lg">
                    <i class="fas fa-file-excel me-1"></i> Export Excel
                </a>
            </div>
        <?php endif; ?>
    </div>
</div>

<form method="get" class="d-flex mb-7" role="search">
                <input type="hidden" name="page" value="manajemen_user">
                <input type="hidden" name="action" value="list">
                <input type="text" class="form-control" name="q" value="<?= htmlspecialchars($q ?? '') ?>" placeholder="Cari username/nama/role...">
                <button class="btn btn-success" type="submit"><i class="fas fa-search"></i></button>
                <?php if (!empty($q)): ?>
                    <a href="?page=manajemen_user&action=list" class="btn btn-outline-secondary"><i class="fas fa-times"></i></a>
                <?php endif; ?>
            </form>

<p class="text-muted">Kelola akun pengguna dan reset password</p>

<?= $msg ?>

<?php if ($action === 'list'): ?>
    <div class="card">
        <div class="card-header d-flex justify-content-between align-items-center flex-wrap gap-2">
            <h3 class="m-0"><i class="fas fa-list"></i> Daftar Pengguna</h3>
        </div>
        <div class="card-body">
            <div class="table-responsive">
                <table class="table table-striped align-middle">
                    <thead>
                        <tr>
                            <th style="width:55px;">No</th>
                            <th>Nama</th>
                            <th>Pangkat / NRP/NIP</th>
                            <th>Jabatan</th>
                            <th>Status</th>
                            <th style="width:160px;">Aksi</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (!empty($users)): ?>
                            <?php $no = ($pg - 1) * 10 + 1; foreach ($users as $u): ?>
                                <tr>
                                    <td><?= $no++ ?></td>
                                    <td><?= htmlspecialchars($u['nama_lengkap'] ?: $u['username']) ?></td>
                                    <td><?= htmlspecialchars(trim(($u['pangkat'] ?? '') . ' / ' . ($u['nrp_nip'] ?? ''), ' /')) ?></td>
                                    <td><?= htmlspecialchars($u['jabatan'] ?? '-') ?></td>
                                    <td>
                                        <?php $isAktif = ($u['status'] === 'Aktif'); ?>
                                        <span class="badge status-badge <?= $isAktif ? 'bg-success' : 'bg-secondary' ?> text-white" data-id="<?= (int)$u['id'] ?>">
                                            <?= $isAktif ? 'Aktif' : 'Tidak Aktif' ?>
                                        </span>
                                    </td>
                                    <td class="text-nowrap">
                                        <a href="?page=manajemen_user&action=reset_password&id=<?= (int)$u['id'] ?>" class="btn btn-sm btn-outline-info" title="Reset Password"><i class="fas fa-key"></i></a>
                                        <a href="?page=manajemen_user&action=edit&id=<?= (int)$u['id'] ?>" class="btn btn-sm btn-outline-primary" title="Edit User"><i class="fas fa-edit"></i></a>
                                        <button type="button" class="btn btn-sm <?= $isAktif ? 'btn-outline-secondary' : 'btn-outline-success' ?> action-toggle"
                                                title="<?= $isAktif ? 'Nonaktifkan User' : 'Aktifkan User' ?>"
                                                data-id="<?= (int)$u['id'] ?>"
                                                data-status="<?= $isAktif ? 'Aktif' : 'Tidak Aktif' ?>"
                                                data-url="?page=manajemen_user&action=toggle_status&id=<?= (int)$u['id'] ?>"
                                                data-csrf="<?= generate_csrf_token() ?>">
                                            <i class="fas fa-<?= $isAktif ? 'times' : 'check' ?>"></i>
                                        </button>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <tr><td colspan="6" class="text-center text-muted">Belum ada data pengguna.</td></tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
            <?php if (($total_pages ?? 1) > 1): ?>
            <nav aria-label="Paging">
                <ul class="pagination justify-content-end">
                    <?php 
                        $cur = $pg ?? 1; 
                        $srt = $sort ?? 'username'; 
                        $d = $dir ?? 'asc';
                        $build = function($p) use($q,$srt,$d){
                            return '?'.http_build_query(['page'=>'manajemen_user','action'=>'list','q'=>$q,'sort'=>$srt,'dir'=>$d,'pg'=>$p]);
                        };
                    ?>
                    <li class="page-item <?= $cur<=1?'disabled':'' ?>"><a class="page-link" href="<?= $build(max(1,$cur-1)) ?>">&laquo;</a></li>
                    <?php for ($i=1; $i<=($total_pages ?? 1); $i++): ?>
                        <li class="page-item <?= $i===$cur?'active':'' ?>"><a class="page-link" href="<?= $build($i) ?>"><?= $i ?></a></li>
                    <?php endfor; ?>
                    <li class="page-item <?= $cur>=($total_pages ?? 1)?'disabled':'' ?>"><a class="page-link" href="<?= $build(min(($total_pages ?? 1),$cur+1)) ?>">&raquo;</a></li>
                </ul>
            </nav>
            <?php endif; ?>
        </div>
    </div>

<?php elseif ($action === 'reset_password' && $user_data): ?>
    <!-- Reset Password Form -->
    <div class="card">
        <div class="card-header">
            <h3><i class="fas fa-key"></i> Reset Password User</h3>
        </div>
        <div class="card-body">
            <div class="bg-light border rounded-2 p-4 mb-4">
                <h4>Informasi User</h4>
                <div class="row g-3">
                    <div class="col-md-4 d-flex flex-column">
                        <label class="fw-semibold text-muted small mb-1">Username</label>
                        <span class="text-dark"><?= htmlspecialchars($user_data['username']) ?></span>
                    </div>
                    <div class="col-md-4 d-flex flex-column">
                        <label class="fw-semibold text-muted small mb-1">Nama Lengkap</label>
                        <span class="text-dark"><?= htmlspecialchars($user_data['nama_lengkap']) ?></span>
                    </div>
                    <div class="col-md-4 d-flex flex-column">
                        <label class="fw-semibold text-muted small mb-1">Role</label>
                        <span class="text-dark"><?= htmlspecialchars(ucfirst($user_data['nama_role'])) ?></span>
                    </div>
                </div>
            </div>
            
            <form method="POST" class="reset-password-form">
                <input type="hidden" name="csrf_token" value="<?= generate_csrf_token() ?>">
                
                <div class="form-group">
                    <label for="new_password">Password Baru *</label>
                    <input type="password" id="new_password" name="new_password" required 
                           class="form-control" minlength="6">
                    <small class="form-text">Minimal 6 karakter</small>
                </div>
                
                <div class="form-group">
                    <label for="confirm_password">Konfirmasi Password Baru *</label>
                    <input type="password" id="confirm_password" name="confirm_password" required 
                           class="form-control" minlength="6">
                </div>
                
                <div class="form-actions">
                    <a href="?page=manajemen_user" class="btn btn-secondary">
                        <i class="fas fa-arrow-left"></i> Kembali
                    </a>
                    <button type="submit" class="btn btn-warning">
                        <i class="fas fa-key"></i> Reset Password
                    </button>
                </div>
            </form>
        </div>
    </div>

    <?php endif; ?>


<?php if ($action === 'create'): ?>
    <?php
    // fetch roles
    $roles = [];
    $rres = $mysqli->query("SELECT id, nama_role FROM role WHERE LOWER(nama_role) IN ('administrator','pimpinan','driver','user') ORDER BY id");
    if ($rres) {
        while ($rr = $rres->fetch_assoc()) $roles[] = $rr;
    }

    // fetch pengguna list for selection
    $penggunas = [];
    $pres = $mysqli->query("SELECT id, nama_lengkap, nrp_nip FROM pengguna ORDER BY nama_lengkap");
    if ($pres) {
        while ($pp = $pres->fetch_assoc()) $penggunas[] = $pp;
    }
    // fetch kesatuan list for select
    $kesatuans = [];
    $kres = $mysqli->query("SELECT id, nama_kesatuan FROM kesatuan ORDER BY nama_kesatuan");
    if ($kres) { while ($kr = $kres->fetch_assoc()) $kesatuans[] = $kr; }
    ?>

    <div class="card">
        <div class="card-header">
            <h3><i class="fas fa-user-plus"></i> Tambah User</h3>
        </div>
        <div class="card-body">
            <form method="POST" action="?page=manajemen_user&action=create">
                <input type="hidden" name="csrf_token" value="<?= generate_csrf_token() ?>">

                <div id="newPengguna">
                    <h5>Data Pengguna Baru</h5>
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label>Nama Lengkap *</label>
                            <input type="text" name="nama_lengkap" class="form-control">
                        </div>
                        <div class="col-md-6 mb-3">
                            <label>Email *</label>
                            <input type="email" name="email" class="form-control" placeholder="nama@gmail.com">
                        </div>
                        <div class="col-md-6 mb-3">
                            <label>Jenis Personel *</label>
                            <select name="jenis_personel" id="jenis_personel" class="form-select" onchange="toggleMatraSatuan()" required>
                                <option value="">-- Pilih Jenis --</option>
                                <option value="TNI">TNI</option>
                                <option value="PNS">PNS</option>
                            </select>
                        </div>
                        <div class="col-md-6 mb-3">
                            <label>NRP/NIP</label>
                            <input type="text" name="nrp_nip" id="nrp_nip_input" class="form-control" placeholder="Wajib untuk TNI, opsional untuk PNS/PPPK/Honorer">
                        </div>
                        <div class="col-md-6 mb-3">
                            <label>No. Telepon</label>
                            <input type="tel" name="no_hp" class="form-control" placeholder="08xxxxxxxxxx">
                        </div>
                        <div class="col-md-6 mb-3">
                            <label>Alamat</label>
                            <input type="text" name="alamat" class="form-control">
                        </div>
                    </div>

                    <!-- TNI Hierarchy Section -->
                    <div id="tni_hierarchy" style="display: none;">
                        <h6 class="text-primary mt-3 mb-3"><i class="fas fa-military-tech"></i> Struktur TNI</h6>
                        <div class="row">
                            <div class="col-md-4 mb-3">
                                <label>Matra *</label>
                                <select name="matra" id="matra_select" class="form-select">
                                    <option value="">-- Pilih Matra --</option>
                                    <option value="AD">AD</option>
                                    <option value="AL">AL</option>
                                    <option value="AU">AU</option>
                                </select>
                            </div>
                            <div class="col-md-4 mb-3">
                                <label>Korps *</label>
                                <input type="text" name="korps" id="korps_input" class="form-control" style="text-transform: uppercase;" placeholder="Contoh: Infanteri, Kavaleri, Artileri">
                            </div>
                            <div class="col-md-4 mb-3">
                                <label>Kesatuan *</label>
                                <input type="text" name="kesatuan" id="kesatuan_input" class="form-control" style="text-transform: uppercase;" placeholder="Contoh: Kodam, Korem, Kodim">
                            </div>
                        </div>
                    </div>

                    <!-- PNS Section -->
                    <div id="pns_section" style="display: none;">
                        <h6 class="text-success mt-3 mb-3"></h6>
                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label>Satuan/Unit Kerja *</label>
                                <input type="text" name="satuan_pns" class="form-control" placeholder="Contoh: Pusinfo, Satsiber, Puspen">
                            </div>
                        </div>
                    </div>

                    <!-- Dipindah ke bawah setelah seleksi personel & hierarki -->
                    <div class="row mt-2">
                        <div class="col-md-6 mb-3">
                            <label>Pangkat/Golongan</label>
                            <input type="text" name="pangkat" class="form-control" id="pangkat_input" placeholder="Contoh: Golongan II/a atau kosong untuk honorer">
                            <select name="pangkat" class="form-select" id="pangkat_select" style="display:none;"></select>
                        </div>
                        <div class="col-md-6 mb-3">
                            <label>Jabatan</label>
                            <input type="text" name="jabatan" class="form-control" placeholder="Isi jabatan">
                        </div>
                    </div>

                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label>Status Pegawai</label>
                            <select name="status_pegawai" class="form-select">
                                <option>Aktif</option>
                                <option>Pensiun</option>
                                <option>Mutasi</option>
                                <option>Non-Aktif</option>
                            </select>
                        </div>
                        <div class="col-md-6 mb-3">
                            <label>Status Aktif</label>
                            <select name="status_aktif" class="form-select">
                                <option>Aktif</option>
                                <option>Tidak Aktif</option>
                            </select>
                        </div>
                    </div>
                </div>

                <hr>
                <h5>Akun Login</h5>
                <div class="row">
                    <div class="col-md-4 mb-3">
                        <label>Username *</label>
                        <input type="text" name="username" class="form-control" required>
                    </div>
                    <div class="col-md-4 mb-3">
                        <label>Password *</label>
                        <input type="password" name="password" class="form-control" required>
                    </div>
                    <div class="col-md-4 mb-3">
                        <label>Role *</label>
                        <select name="role_id" class="form-select" required>
                            <option value="">-- Pilih Role --</option>
                            <?php foreach ($roles as $role): ?>
                                <option value="<?= $role['id'] ?>"><?= htmlspecialchars($role['nama_role']) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                </div>

                <div class="form-actions">
                    <a href="?page=manajemen_user" class="btn btn-secondary">Kembali</a>
                    <button type="submit" class="btn btn-primary">Buat User</button>
                </div>
            </form>
        </div>
    </div>

    <script>
    function togglePenggunaForm(cb) {
        var existing = document.getElementById('existingPengguna');
        var neu = document.getElementById('newPengguna');
        if (cb.checked) {
            existing.style.display = 'block';
            neu.style.display = 'none';
        } else {
            existing.style.display = 'none';
            neu.style.display = 'block';
        }
    }

    function toggleMatraSatuan() {
        var jenisPersonel = document.getElementById('jenis_personel').value;
        var tniHierarchy = document.getElementById('tni_hierarchy');
        var pnsSection = document.getElementById('pns_section');
        var pangkatInput = document.getElementById('pangkat_input');
        var pangkatSelect = document.getElementById('pangkat_select');
        var nrpInput = document.getElementById('nrp_nip_input');
        
        if (jenisPersonel === 'TNI') {
            tniHierarchy.style.display = 'block';
            pnsSection.style.display = 'none';
            pangkatInput.style.display='none'; pangkatInput.disabled=true;
            pangkatSelect.style.display='block'; pangkatSelect.disabled=false;
            if (nrpInput) {
                nrpInput.required = true;
            }
            updatePangkatOptions('create');
        } else if (jenisPersonel === 'PNS') {
            tniHierarchy.style.display = 'none';
            pnsSection.style.display = 'block';
            pangkatSelect.style.display='none'; pangkatSelect.disabled=true;
            pangkatInput.style.display='block'; pangkatInput.disabled=false;
            if (nrpInput) {
                nrpInput.required = false;
            }
        } else {
            tniHierarchy.style.display = 'none';
            pnsSection.style.display = 'none';
            pangkatSelect.style.display='none'; pangkatSelect.disabled=true;
            pangkatInput.style.display='block'; pangkatInput.disabled=false;
            if (nrpInput) {
                nrpInput.required = false;
            }
        }
    }
    </script>
<?php endif; ?>

<?php if ($action === 'import_csv'): ?>
    <div class="card">
        <div class="card-header">
            <h3><i class="fas fa-file-excel"></i> Import User dari Excel (.xlsx)</h3>
        </div>
        <div class="card-body">
            <form method="POST" enctype="multipart/form-data" action="?page=manajemen_user&action=import_csv">
                <input type="hidden" name="csrf_token" value="<?= generate_csrf_token() ?>">
                
                <div class="mb-3">
                    <label for="excel_file" class="form-label">Pilih File Excel (.xlsx) *</label>
                    <input type="file" class="form-control" id="excel_file" name="excel_file" accept=".xlsx" required>
                    <div class="form-text">
                        File harus berformat Excel .xlsx dengan header: 
                        <code>nrp,nama_lengkap,jabatan,pangkat,no_hp,email,jenis_personel,matra,korps,kesatuan</code> dan opsional <code>username,password,alamat,role</code>.
                        <a href="templates/template_user.php">Download template</a>.
                    </div>
                </div>
                
                    <div class="alert alert-info">
                    <strong>Catatan:</strong><br>
                    - Username bisa diisi; jika kosong akan dibuat otomatis dari email/NRP dan dipastikan unik.<br>
                    - Password bisa diisi (min. 6); jika kosong akan diisi acak (8 karakter heksadesimal).<br>
                    - Role bisa diisi sesuai nama role (mis. Administrator, Pimpinan, Driver, User). Jika kosong akan default User.<br>
                    - Jenis personel: TNI atau PNS. Jika PNS, kolom kesatuan akan disimpan ke satuan_pns.
                </div>

                <div class="form-actions">
                    <a href="?page=manajemen_user" class="btn btn-secondary">
                        <i class="fas fa-arrow-left"></i> Kembali
                    </a>
                    <button type="submit" class="btn btn-primary">
                        <i class="fas fa-upload"></i> Import Data
                    </button>
                </div>
            </form>
        </div>
    </div>
<?php endif; ?>

<?php if ($action === 'edit' && $edit_data): ?>
    <?php
    // roles for select
    $roles = [];
    $rres = $mysqli->query("SELECT id, nama_role FROM role WHERE LOWER(nama_role) IN ('administrator','pimpinan','driver','user') ORDER BY id");
    if ($rres) { while ($rr = $rres->fetch_assoc()) $roles[] = $rr; }
    // kesatuan list
    $kesatuans = [];
    $kres = $mysqli->query("SELECT id, nama_kesatuan FROM kesatuan ORDER BY nama_kesatuan");
    if ($kres) { while ($kr = $kres->fetch_assoc()) $kesatuans[] = $kr; }
    ?>

    <div class="card">
        <div class="card-header">
            <h3><i class="fas fa-edit"></i> Edit User</h3>
        </div>
        <div class="card-body">
            <form method="POST" action="?page=manajemen_user&action=edit&id=<?= $edit_data['ua_id'] ?>">
                <input type="hidden" name="csrf_token" value="<?= generate_csrf_token() ?>">

                <div class="row">
                    <div class="col-md-6 mb-3">
                        <label>Nama Lengkap *</label>
                        <input type="text" name="nama_lengkap" class="form-control" required value="<?= htmlspecialchars($edit_data['nama_lengkap'] ?? '') ?>">
                    </div>
                    <div class="col-md-6 mb-3">
                        <label>Email *</label>
                        <input type="email" name="email" class="form-control" required value="<?= htmlspecialchars($edit_data['email'] ?? '') ?>" placeholder="nama@gmail.com">
                    </div>
                    <div class="col-md-6 mb-3">
                        <label>NRP/NIP</label>
                        <input type="text" name="nrp_nip" id="nrp_nip_input_edit" class="form-control" value="<?= htmlspecialchars($edit_data['nrp_nip'] ?? '') ?>" placeholder="Wajib untuk TNI, opsional untuk PNS/PPPK/Honorer">
                    </div>
                    <div class="col-md-6 mb-3">
                        <label>No. Telepon</label>
                        <input type="tel" name="no_hp" class="form-control" value="<?= htmlspecialchars($edit_data['no_hp'] ?? '') ?>" placeholder="08xxxxxxxxxx">
                    </div>
                    <div class="col-md-6 mb-3">
                        <label>Alamat</label>
                        <input type="text" name="alamat" class="form-control" value="<?= htmlspecialchars($edit_data['alamat'] ?? '') ?>">
                    </div>
                    <!-- Jenis Personel -->
                    <div class="col-md-6 mb-3">
                        <label>Jenis Personel *</label>
                        <select name="jenis_personel" id="jenis_personel_edit" class="form-select" onchange="toggleMatraSatuanEdit()" required>
                            <option value="">-- Pilih Jenis Personel --</option>
                            <option value="TNI" <?= (($edit_data['jenis_personel'] ?? '') == 'TNI') ? 'selected' : '' ?>>TNI</option>
                            <option value="PNS" <?= (($edit_data['jenis_personel'] ?? '') == 'PNS') ? 'selected' : '' ?>>PNS</option>
                        </select>
                    </div>
                </div>

                <!-- TNI Hierarchy Section for Edit -->
                <div id="tni_hierarchy_edit" style="display: <?= (($edit_data['jenis_personel'] ?? '') == 'TNI') ? 'block' : 'none' ?>;">
                    <h6 class="text-primary mt-3 mb-3"><i class="fas fa-military-tech"></i> Struktur TNI</h6>
                    <div class="row">
                        <div class="col-md-4 mb-3">
                            <label>Matra</label>
                            <select name="matra" id="matra_select_edit" class="form-select">
                                <option value="">-- Pilih Matra --</option>
                                <option value="AD" <?= (($edit_data['matra'] ?? '')=='AD')?'selected':''; ?>>AD</option>
                                <option value="AL" <?= (($edit_data['matra'] ?? '')=='AL')?'selected':''; ?>>AL</option>
                                <option value="AU" <?= (($edit_data['matra'] ?? '')=='AU')?'selected':''; ?>>AU</option>
                            </select>
                        </div>
                        <div class="col-md-4 mb-3">
                            <label>Korps</label>
                            <input type="text" name="korps" class="form-control" value="<?= htmlspecialchars($edit_data['korps'] ?? '') ?>" placeholder="Contoh: Infanteri, Kavaleri">
                        </div>
                        <div class="col-md-4 mb-3">
                            <label>Kesatuan</label>
                            <input type="text" name="kesatuan" class="form-control" value="<?= htmlspecialchars($edit_data['kesatuan'] ?? '') ?>" placeholder="Contoh: Kodam, Korem">
                        </div>
                    </div>
                </div>

                <!-- PNS Section for Edit -->
                <div id="pns_section_edit" style="display: <?= (($edit_data['jenis_personel'] ?? '') == 'PNS') ? 'block' : 'none' ?>;">
                    <h6 class="text-success mt-3 mb-3"><i class="fas fa-building"></i> Struktur PNS</h6>
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label>Satuan/Unit Kerja</label>
                            <input type="text" name="satuan_pns" class="form-control" value="<?= htmlspecialchars($edit_data['satuan_pns'] ?? '') ?>" placeholder="Contoh: Bagian Keuangan, Sekretariat">
                        </div>
                    </div>
                </div>

                <div class="row mt-2">
                    <!-- Pangkat & Jabatan dipindah ke bawah -->
                    <div class="col-md-6 mb-3">
                        <label>Pangkat/Golongan</label>
                        <input type="text" name="pangkat" class="form-control" id="pangkat_input_edit" value="<?= htmlspecialchars($edit_data['pangkat'] ?? '') ?>" placeholder="Contoh: Golongan II/a atau kosong untuk honorer">
                        <select name="pangkat" class="form-select" id="pangkat_select_edit" style="display:none;"></select>
                    </div>
                    <div class="col-md-6 mb-3">
                        <label>Jabatan</label>
                        <input type="text" name="jabatan" class="form-control" value="<?= htmlspecialchars($edit_data['jabatan'] ?? '') ?>" placeholder="Isi jabatan">
                    </div>
                </div>
                <div class="row">
                    <div class="col-md-6 mb-3">
                        <label>Status Pegawai</label>
                        <select name="status_pegawai" class="form-select">
                            <option <?= (($edit_data['status_pegawai'] ?? '') == 'Aktif') ? 'selected' : '' ?>>Aktif</option>
                            <option <?= (($edit_data['status_pegawai'] ?? '') == 'Pensiun') ? 'selected' : '' ?>>Pensiun</option>
                            <option <?= (($edit_data['status_pegawai'] ?? '') == 'Mutasi') ? 'selected' : '' ?>>Mutasi</option>
                            <option <?= (($edit_data['status_pegawai'] ?? '') == 'Non-Aktif') ? 'selected' : '' ?>>Non-Aktif</option>
                        </select>
                    </div>
                    <div class="col-md-6 mb-3">
                        <label>Status Aktif</label>
                        <select name="status_aktif" class="form-select">
                            <option <?= (($edit_data['status_aktif'] ?? '') == 'Aktif') ? 'selected' : '' ?>>Aktif</option>
                            <option <?= (($edit_data['status_aktif'] ?? '') == 'Tidak Aktif') ? 'selected' : '' ?>>Tidak Aktif</option>
                        </select>
                    </div>
                </div>

                <hr>
                <h5>Akun Login</h5>
                <div class="row">
                    <div class="col-md-4 mb-3">
                        <label>Username</label>
                        <input type="text" class="form-control" value="<?= htmlspecialchars($edit_data['username']) ?>" disabled>
                    </div>
                    <div class="col-md-4 mb-3">
                        <label>Role *</label>
                        <select name="role_id" class="form-select" required>
                            <option value="">-- Pilih Role --</option>
                            <?php foreach ($roles as $role): ?>
                                <option value="<?= $role['id'] ?>" <?= $role['id'] == $edit_data['role_id'] ? 'selected' : '' ?>><?= htmlspecialchars($role['nama_role']) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                </div>

                <div class="form-actions mt-3">
                    <a href="?page=manajemen_user" class="btn btn-secondary">Batal</a>
                    <button type="submit" class="btn btn-primary">Simpan Perubahan</button>
                </div>
            </form>
        </div>
    </div>
<?php endif; ?>

<script>
    function toggleMatraSatuanEdit() {
        var jenisPersonel = document.getElementById('jenis_personel_edit').value;
        var tniHierarchy = document.getElementById('tni_hierarchy_edit');
        var pnsSection = document.getElementById('pns_section_edit');
        var pangkatInput = document.getElementById('pangkat_input_edit');
        var pangkatSelect = document.getElementById('pangkat_select_edit');
        var nrpInput = document.getElementById('nrp_nip_input_edit');
        
        if (jenisPersonel === 'TNI') {
            tniHierarchy.style.display = 'block';
            pnsSection.style.display = 'none';
            pangkatInput.style.display='none'; pangkatInput.disabled=true;
            pangkatSelect.style.display='block'; pangkatSelect.disabled=false;
            if (nrpInput) {
                nrpInput.required = true;
            }
            updatePangkatOptions('edit');
        } else if (jenisPersonel === 'PNS') {
            tniHierarchy.style.display = 'none';
            pnsSection.style.display = 'block';
            pangkatSelect.style.display='none'; pangkatSelect.disabled=true;
            pangkatInput.style.display='block'; pangkatInput.disabled=false;
            if (nrpInput) {
                nrpInput.required = false;
            }
        } else {
            tniHierarchy.style.display = 'none';
            pnsSection.style.display = 'none';
            pangkatSelect.style.display='none'; pangkatSelect.disabled=true;
            pangkatInput.style.display='block'; pangkatInput.disabled=false;
            if (nrpInput) {
                nrpInput.required = false;
            }
        }
    }

    const PANGKAT_TNI = {
        'AD':["Jenderal","Letnan Jenderal","Mayor Jenderal","Brigadir Jenderal","Kolonel","Letnan Kolonel","Mayor","Kapten","Letnan Satu","Letnan Dua","Pembantu Letnan Satu","Pembantu Letnan Dua","Sersan Mayor","Sersan Kepala","Sersan Satu","Sersan Dua","Kopral Kepala","Kopral Satu","Kopral Dua","Prajurit Kepala","Prajurit Satu","Prajurit Dua"],
        'AL':["Laksamana","Laksamana Madya","Laksamana Muda","Laksamana Pertama","Kolonel","Letnan Kolonel","Mayor","Kapten","Letnan Satu","Letnan Dua","Pembantu Letnan Satu","Pembantu Letnan Dua","Sersan Mayor","Sersan Kepala","Sersan Satu","Sersan Dua","Kopral Kepala","Kopral Satu","Kopral Dua","Kelasi Kepala","Kelasi Satu","Kelasi Dua"],
        'AU':["Marsekal","Marsekal Madya","Marsekal Muda","Marsekal Pertama","Kolonel","Letnan Kolonel","Mayor","Kapten","Letnan Satu","Letnan Dua","Pembantu Letnan Satu","Pembantu Letnan Dua","Sersan Mayor","Sersan Kepala","Sersan Satu","Sersan Dua","Kopral Kepala","Kopral Satu","Kopral Dua","Prajurit Kepala","Prajurit Satu","Prajurit Dua"]
    };

    function updatePangkatOptions(mode){
        var matraEl = (mode==='edit'? document.getElementById('matra_select_edit'):document.getElementById('matra_select'));
        var selectEl = (mode==='edit'? document.getElementById('pangkat_select_edit'):document.getElementById('pangkat_select'));
        if(!matraEl || !selectEl) return;
        var currentMatra = matraEl.value;
        var existingValue = (mode==='edit'? document.getElementById('pangkat_input_edit').value : document.getElementById('pangkat_input').value);
        selectEl.innerHTML='<option value="">-- Pilih Pangkat --</option>';
        if(PANGKAT_TNI[currentMatra]){
            PANGKAT_TNI[currentMatra].forEach(function(p){
                var opt=document.createElement('option'); opt.value=p; opt.textContent=p; if(existingValue===p) opt.selected=true; selectEl.appendChild(opt);
            });
            if(existingValue && !PANGKAT_TNI[currentMatra].includes(existingValue)){
                var opt=document.createElement('option'); opt.value=existingValue; opt.textContent=existingValue+' (lama)'; opt.selected=true; selectEl.appendChild(opt);
            }
        }
    }

    document.addEventListener('DOMContentLoaded', function(){
        var matraCreate=document.getElementById('matra_select'); if(matraCreate){matraCreate.addEventListener('change', function(){updatePangkatOptions('create');});}
        var matraEdit=document.getElementById('matra_select_edit'); if(matraEdit){matraEdit.addEventListener('change', function(){updatePangkatOptions('edit');});}
        if(document.getElementById('jenis_personel') && document.getElementById('jenis_personel').value==='TNI'){ toggleMatraSatuan(); }
        if(document.getElementById('jenis_personel_edit') && document.getElementById('jenis_personel_edit').value==='TNI'){ toggleMatraSatuanEdit(); }
    });

document.addEventListener('click', function(e){
    var target = e.target.closest && e.target.closest('.action-toggle');
    if (!target) return;
    e.preventDefault();
    var id = target.getAttribute('data-id');
    var status = target.getAttribute('data-status');
    var csrf = target.getAttribute('data-csrf');
    var url = target.getAttribute('data-url');
    if (!confirm('Yakin ingin mengubah status user ini?')) return;

    fetch(url, {
        method: 'POST',
        headers: {
            'Content-Type': 'application/x-www-form-urlencoded',
            'Accept': 'application/json',
            'X-Requested-With': 'XMLHttpRequest'
        },
        // Include id so server can read it from POST if not present in URL
        body: 'csrf_token=' + encodeURIComponent(csrf)
            + '&status=' + encodeURIComponent(status)
            + '&action=toggle_status'
            + '&id=' + encodeURIComponent(id)
    }).then(function(res){
        var ct = res.headers.get('content-type') || '';
        if (ct.indexOf('application/json') !== -1) return res.json();
        return res.text().then(function(t){ return { success: false, error: t }; });
    }).then(function(data){
        if (data && data.success) {
            // Update badge text and class
            var badge = document.querySelector('.status-badge[data-id="' + id + '"]');
            if (badge) {
                badge.textContent = data.new_status || (status === 'Aktif' ? 'Aktif' : 'Tidak Aktif');
                badge.className = 'badge status-badge ' + (data.badge_class || (status === 'Aktif' ? 'bg-success' : 'bg-secondary')) + ' text-white';
            }
            // Update button next state
            target.setAttribute('data-status', data.next_status || (status === 'Aktif' ? 'Tidak Aktif' : 'Aktif'));
            target.title = data.button_title || target.title;
            target.className = 'btn btn-sm ' + (data.button_class || (status === 'Aktif' ? 'btn-outline-secondary' : 'btn-outline-success')) + ' action-toggle';
            var icon = target.querySelector('i');
           
           
           
            if (icon) { icon.className = 'fas fa-' + (data.button_icon || (status === 'Aktif' ? 'times' : 'check')); }
        } else {
            alert('Gagal mengubah status.');
        }
      
       }).catch(function(err){
        alert('Gagal mengirim request: ' + err);
    });
});
</script>



<!-- Add User Modal -->
<div class="modal fade" id="addUserModal" tabindex="-1" aria-labelledby="addUserModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <form method="POST" action="?page=manajemen_user&action=create">
                <input type="hidden" name="csrf_token" value="<?= generate_csrf_token() ?>">
                <div class="modal-header">
                    <h5 class="modal-title" id="addUserModalLabel"><i class="fas fa-user-plus me-1"></i> Tambah User</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <?php
                    // fetch roles and pengguna lists for modal (lightweight)
                    $roles_modal = [];
                    $rres_modal = $mysqli->query("SELECT id, nama_role FROM role WHERE LOWER(nama_role) IN ('administrator','pimpinan','driver','user') ORDER BY id");
                    if ($rres_modal) { while ($rrm = $rres_modal->fetch_assoc()) $roles_modal[] = $rrm; }

                    $penggunas_modal = [];
                    $pres_modal = $mysqli->query("SELECT id, nama_lengkap, nrp_nip FROM pengguna ORDER BY nama_lengkap LIMIT 200");
                    if ($pres_modal) { while ($ppm = $pres_modal->fetch_assoc()) $penggunas_modal[] = $ppm; }

                    // kesatuan list (optional)
                    $kesatuans_modal = [];
                    $kres = $mysqli->query("SELECT id, nama_kesatuan FROM kesatuan ORDER BY nama_kesatuan");
                    if ($kres) { while ($kr = $kres->fetch_assoc()) $kesatuans_modal[] = $kr; }
                    ?>

                                    <div class="mb-3 form-check">
                                        <input type="checkbox" class="form-check-input" id="use_existing_modal" name="use_existing" value="1" onchange="togglePenggunaFormModal(this)">
                                        <label class="form-check-label" for="use_existing_modal">Gunakan data pengguna yang sudah ada</label>
                                    </div>

                                    <div id="existingPenggunaModal" style="display:none;" class="mb-3">
                                        <label>Pilih Pengguna</label>

                                        <select name="pengguna_id" class="form-select">
                                            <option value="">-- Pilih Pengguna --</option>
                                            <?php foreach ($penggunas_modal as $p): ?>
                                                <option value="<?= $p['id'] ?>"><?= htmlspecialchars($p['nama_lengkap'] . ' (' . $p['nrp_nip'] . ')') ?></option>
                                            <?php endforeach; ?>
                                        </select>
                                    </div>

                                    <div id="newPenggunaModal">
                                        <h6>Data Pengguna Baru</h6>
                                        <div class="row">
                                            <div class="col-md-6 mb-3">
                                                <label>Nama Lengkap *</label>
                                                <input type="text" name="nama_lengkap" class="form-control">
                                            </div>
                                            <div class="col-md-6 mb-3">
                                                <label>Email *</label>
                                                <input type="email" name="email" class="form-control" placeholder="nama@gmail.com">
                                            </div>
                                            <div class="col-md-6 mb-3">
                                                <label>Pangkat/Golongan</label>
                                                <input type="text" name="pangkat" class="form-control" placeholder="Contoh: Golongan II/a atau kosong untuk honorer">
                                            </div>
                                            <div class="col-md-6 mb-3">
                                                <label>Jabatan</label>
                                                <input type="text" name="jabatan" class="form-control" placeholder="Isi jabatan">
                                            </div>
                                            <div class="col-md-6 mb-3">
                                                <label>NRP/NIP</label>
                                                <input type="text" name="nrp_nip" class="form-control">
                                            </div>
                                            <div class="col-md-6 mb-3">
                                                <label>No. Telepon</label>
                                                <input type="tel" name="no_hp" class="form-control" placeholder="08xxxxxxxxxx">
                                            </div>
                                            <div class="col-md-6 mb-3">
                                                <label>Alamat</label>
                                                <input type="text" name="alamat" class="form-control">
                                            </div>
                                            <div class="col-md-6 mb-3">
                                                <label>Kesatuan</label>
                                                <?php if (!empty($kesatuans_modal)): ?>
                                                    <select name="kesatuan_id" class="form-select">
                                                        <option value="">-- Pilih Kesatuan --</option>
                                                        <?php foreach ($kesatuans_modal as $k): ?>
                                                            <option value="<?= $k['id'] ?>"><?= htmlspecialchars($k['nama_kesatuan']) ?></option>
                                                        <?php endforeach; ?>
                                                    </select>
                                                <?php else: ?>
                                                    <input type="number" name="kesatuan_id" class="form-control" placeholder="ID Kesatuan (opsional)">
                                                <?php endif; ?>
                                            </div>
                                            <div class="col-md-6 mb-3">
                                                <label>Status Pegawai</label>
                                                <select name="status_pegawai" class="form-select">
                                                    <option>Aktif</option>
                                                    <option>Pensiun</option>
                                                    <option>Mutasi</option>
                                                    <option>Non-Aktif</option>
                                                </select>
                                            </div>
                                            <div class="col-md-6 mb-3">
                                                <label>Status Aktif</label>
                                                <select name="status_aktif" class="form-select">
                                                    <option>Aktif</option>
                                                    <option>Tidak Aktif</option>
                                                </select>
                                            </div>
                                        </div>
                                    </div>

                                    <hr>
                                    <h6>Akun Login</h6>
                                    <div class="row">
                                        <div class="col-md-4 mb-3">
                                            <label>Username *</label>
                                            <input type="text" name="username" class="form-control" required>
                                        </div>
                                        <div class="col-md-4 mb-3">
                                            <label>Password *</label>
                                            <input type="password" name="password" class="form-control" required>
                                        </div>
                                        <div class="col-md-4 mb-3">
                                            <label>Role *</label>
                                            <select name="role_id" class="form-select" required>
                                                <option value="">-- Pilih Role --</option>
                                                <?php foreach ($roles_modal as $role): ?>
                                                    <option value="<?= $role['id'] ?>"><?= htmlspecialchars($role['nama_role']) ?></option>
                                                <?php endforeach; ?>
                                            </select>
                                        </div>
                                    </div>

                                </div>
                                <div class="modal-footer">
                                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Batal</button>
                                    <button type="submit" class="btn btn-primary">Buat User</button>
                                </div>
                            </form>
                        </div>
                    </div>
                </div>

                <style>
                .gradient-header { background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); }
                .page-header h1 { font-size: 1.75rem; font-weight:600; }
                .text-white-50 { color: rgba(255,255,255,0.85); }
                </style>

                <!-- Modals for Adding New Entries -->
                <!-- Modals removed - using simple dropdowns instead -->

                <script>
                function togglePenggunaFormModal(cb) {
                    var existing = document.getElementById('existingPenggunaModal');
                    var neu = document.getElementById('newPenggunaModal');
                    if (cb.checked) {
                        existing.style.display = 'block';
                        neu.style.display = 'none';
                    } else {
                        existing.style.display = 'none';
                        neu.style.display = 'block';
                    }
                }
                </script>