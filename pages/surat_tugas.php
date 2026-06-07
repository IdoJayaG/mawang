<?php
require_once 'includes/auth.php';

$current_role = get_current_role();
$current_user_id = get_current_user_id();

// Role-based access control
if ($current_role === 'guest') {
    header('Location: index.php?page=kendaraan_publik');
    exit;
}

$can_crud = can_operate(); // admin-like
$can_view = is_logged_in();
$is_user = in_array($current_role, ['user', 'driver'], true);
$can_submit = is_logged_in();

function can_access_surat_tugas($conn, $role, $userId, $suratId) {
    if (function_exists('is_admin_like') && is_admin_like()) {
        return true;
    }
    if (!in_array($role, ['user', 'driver'], true) || empty($userId) || empty($suratId)) {
        return false;
    }
    // Determine accessible when:
    // - pengguna_id matches OR created_by matches (if column exists)
    // - OR the kendaraan assigned to the surat has pengguna_id = current user
    // - OR there's an approved/ongoing peminjaman_kendaraan for this kendaraan for the user
    $uid = (int)$userId;
    $sid = (int)$suratId;

    // Build base checks
    $checks = [];
    // pengguna_id
    $checks[] = "s.pengguna_id = {$uid}";
    // created_by when present
    $col_check = $conn->query("SHOW COLUMNS FROM surat_tugas LIKE 'created_by'");
    if ($col_check && $col_check->num_rows > 0) {
        $checks[] = "s.created_by = {$uid}";
    }
    // kendaraan assigned pengguna
    $checks[] = "COALESCE(k.pengguna_id,0) = {$uid}";

    // peminjaman_kendaraan approved/ongoing check (detect applicant column)
    $res_pk = $conn->query("SHOW TABLES LIKE 'peminjaman_kendaraan'");
    if ($res_pk && $res_pk->num_rows > 0) {
        $cols_q = $conn->query("SHOW COLUMNS FROM peminjaman_kendaraan");
        $cols_now = $cols_q ? array_column($cols_q->fetch_all(MYSQLI_ASSOC), 'Field') : [];
        $appCol = null;
        foreach (['peminjam_id','pemohon_id','pengguna_id','user_id','created_by'] as $c) { if (in_array($c, $cols_now, true)) { $appCol = $c; break; } }
        if ($appCol) {
            if ($appCol === 'user_id' && function_exists('get_current_account_id')) {
                $acct = (int)get_current_account_id();
                $checks[] = "EXISTS (SELECT 1 FROM peminjaman_kendaraan pk WHERE pk.kendaraan_id = s.kendaraan_id AND pk.user_id = {$acct} AND pk.status IN ('Approved','approved','Ongoing','ongoing'))";
            } else {
                $checks[] = "EXISTS (SELECT 1 FROM peminjaman_kendaraan pk WHERE pk.kendaraan_id = s.kendaraan_id AND pk.{$appCol} = {$uid} AND pk.status IN ('Approved','approved','Ongoing','ongoing'))";
            }
        }
    }

    $sql = "SELECT COUNT(*) as total FROM surat_tugas s LEFT JOIN kendaraan k ON s.kendaraan_id = k.id WHERE s.id = {$sid} AND (" . implode(' OR ', $checks) . ")";
    $res = $conn->query($sql);
    if (!$res) return false;
    $row = $res->fetch_assoc();
    return ((int)($row['total'] ?? 0)) > 0;
}

function log_surat_tugas_role_activity($role, $message) {
    if (in_array($role, ['pimpinan', 'driver', 'user'], true) && function_exists('log_user_activity')) {
        log_user_activity('[' . strtoupper($role) . '] ' . $message);
    }
}

// Check if surat_tugas table exists, create if not
$table_check = $conn->query("SHOW TABLES LIKE 'surat_tugas'");
if ($table_check->num_rows == 0) {
    $create_table = "CREATE TABLE `surat_tugas` (
        `id` int(11) NOT NULL AUTO_INCREMENT,
        `nomor_surat` varchar(50) NOT NULL,
        `tanggal_surat` date NOT NULL,
        `klasifikasi` varchar(20) DEFAULT 'Biasa',
        `lampiran` varchar(100) DEFAULT '-',
        `perihal` varchar(255) DEFAULT 'Permohonan peminjaman kendaraan dinas bus dan tenaga medis',
        `nama_unit` varchar(150) DEFAULT 'BIRO UMUM SETJEN KEMHAN',
        `nama_bagian` varchar(150) DEFAULT 'BAGIAN PENGAMANAN',
        `jenis_naskah` varchar(100) DEFAULT 'NOTA DINAS',
        `surat_dari` varchar(150) DEFAULT 'Kabag Pam Roum Setjen Kemhan',
        `kepada_jabatan` varchar(100) DEFAULT 'Dandenma Mabes TNI',
        `kepada_tempat` varchar(50) DEFAULT 'Jakarta',
        `dasar_a` text DEFAULT 'Peraturan Panglima TNI Nomor 24 Tahun 2014 tentang Pengesahan Validasi Organisasi FKT dan;',
        `dasar_b` text DEFAULT 'Surat Perintah Kepala SPBT Kemhan Cawang Nomor Sprin/97/XI/2023 tanggal 20 Oktober 2023 tentang fungsi dukungan SPBT Kemhan Cawang dan;',
        `berangkat_dari` varchar(100) DEFAULT 'SPBT Kemhan Cawang',
        `waktu_berangkat` varchar(50) DEFAULT 'Pukul 05.00 WIB s.d selesai',
        `pejabat_ttd_jabatan` varchar(100) DEFAULT 'a.n Kepala SPBT Kemhan Cawang',
        `pejabat_ttd_sebagai` varchar(50) DEFAULT 'Waka,',
        `tembusan_1` varchar(100) DEFAULT 'Kepala SPBT Kemhan Cawang',
        `tembusan_2` varchar(100) DEFAULT 'Asops Denma Mabes TNI',
        `tembusan_3` varchar(100) DEFAULT 'Dansetang Denma Mabes TNI',
        `tembusan_4` varchar(100) DEFAULT 'Dansakdok Denma Mabes TNI',
        `kendaraan_id` int(11) NOT NULL,
        `pengguna_id` int(11) NOT NULL,
        `tujuan` varchar(255) NOT NULL,
        `keperluan` text NOT NULL,
        `tanggal_berangkat` date NOT NULL,
        `tanggal_kembali` date DEFAULT NULL,
        `estimasi_km` int(11) DEFAULT NULL,
        `estimasi_bbm` decimal(8,2) DEFAULT NULL,
        `status` enum('Draft','Disetujui','Dalam Perjalanan','Selesai','Dibatalkan') NOT NULL DEFAULT 'Draft',
        `approval_pimpinan_status` enum('Pending','Approved','Rejected') NOT NULL DEFAULT 'Pending',
        `approval_pimpinan_by` int(11) DEFAULT NULL,
        `approval_pimpinan_at` datetime DEFAULT NULL,
        `km_berangkat` int(11) DEFAULT NULL,
        `km_kembali` int(11) DEFAULT NULL,
        `bbm_terpakai` decimal(8,2) DEFAULT NULL,
        `laporan_perjalanan` text DEFAULT NULL,
        `pejabat_ttd` varchar(100) DEFAULT NULL,
        `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
        `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
        `created_by` int(11) DEFAULT NULL,
        `updated_by` int(11) DEFAULT NULL,
        PRIMARY KEY (`id`),
        UNIQUE KEY `nomor_surat` (`nomor_surat`),
        KEY `kendaraan_id` (`kendaraan_id`),
        KEY `pengguna_id` (`pengguna_id`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci";
    
    if ($conn->query($create_table)) {
        $msg = '<div class="alert alert-info">Tabel surat_tugas berhasil dibuat!</div>';
    } else {
        $msg = '<div class="alert alert-danger">Error membuat tabel: ' . $conn->error . '</div>';
    }
}

// Ensure new nota-dinas related columns exist for old installations
function ensure_surat_tugas_column($conn, $columnName, $definitionSql) {
    $safe = $conn->real_escape_string($columnName);
    $check = $conn->query("SHOW COLUMNS FROM surat_tugas LIKE '{$safe}'");
    if ($check && $check->num_rows > 0) {
        return;
    }
    $conn->query("ALTER TABLE surat_tugas ADD COLUMN {$definitionSql}");
}

if ($conn->query("SHOW TABLES LIKE 'surat_tugas'") && $conn->query("SHOW TABLES LIKE 'surat_tugas'")->num_rows > 0) {
    ensure_surat_tugas_column($conn, 'nama_unit', "nama_unit VARCHAR(150) DEFAULT 'BIRO UMUM SETJEN KEMHAN' AFTER perihal");
    ensure_surat_tugas_column($conn, 'nama_bagian', "nama_bagian VARCHAR(150) DEFAULT 'BAGIAN PENGAMANAN' AFTER nama_unit");
    ensure_surat_tugas_column($conn, 'jenis_naskah', "jenis_naskah VARCHAR(100) DEFAULT 'NOTA DINAS' AFTER nama_bagian");
    ensure_surat_tugas_column($conn, 'surat_dari', "surat_dari VARCHAR(150) DEFAULT 'Kabag Pam Roum Setjen Kemhan' AFTER jenis_naskah");
    ensure_surat_tugas_column($conn, 'approval_pimpinan_status', "approval_pimpinan_status ENUM('Pending','Approved','Rejected') NOT NULL DEFAULT 'Pending' AFTER status");
    ensure_surat_tugas_column($conn, 'approval_pimpinan_by', "approval_pimpinan_by INT NULL AFTER approval_pimpinan_status");
    ensure_surat_tugas_column($conn, 'approval_pimpinan_at', "approval_pimpinan_at DATETIME NULL AFTER approval_pimpinan_by");
}

$has_created_by_col = false;
$created_by_col_check = $conn->query("SHOW COLUMNS FROM surat_tugas LIKE 'created_by'");
if ($created_by_col_check && $created_by_col_check->num_rows > 0) {
    $has_created_by_col = true;
}

$has_approval_pimpinan_col = false;
$approval_pimpinan_col_check = $conn->query("SHOW COLUMNS FROM surat_tugas LIKE 'approval_pimpinan_status'");
if ($approval_pimpinan_col_check && $approval_pimpinan_col_check->num_rows > 0) {
    $has_approval_pimpinan_col = true;
}

$action = $_GET['action'] ?? 'list';
$surat_id = $_GET['id'] ?? null;
$msg = '';

// Handle URL parameters for messages
if (isset($_GET['msg']) && $_GET['msg'] === 'success' && isset($_GET['text'])) {
    $msg = '<div class="alert alert-success">' . htmlspecialchars($_GET['text']) . '</div>';
}

if ($action === 'add' && !$can_submit) {
    $msg = '<div class="alert alert-danger">Anda tidak memiliki akses untuk mengajukan surat tugas.</div>';
    $action = 'list';
    $surat_id = null;
}

if ($action === 'edit' && !$can_crud) {
    $msg = '<div class="alert alert-danger">Anda tidak memiliki akses untuk mengubah surat tugas.</div>';
    $action = 'list';
    $surat_id = null;
}

if ($action === 'delete' && !$can_crud) {
    $msg = '<div class="alert alert-danger">Anda tidak memiliki akses untuk menghapus surat tugas.</div>';
    $action = 'list';
    $surat_id = null;
}

if (in_array($action, ['view', 'download_pdf'], true) && $surat_id && !can_access_surat_tugas($conn, $current_role, (int)$current_user_id, (int)$surat_id)) {
    $msg = '<div class="alert alert-danger">Anda tidak memiliki akses ke surat tugas ini.</div>';
    $action = 'list';
    $surat_id = null;
}

// Function to generate unique nomor surat
function generate_nomor_surat($conn) {
    $today = new DateTime();
    $month_roman = ['I', 'II', 'III', 'IV', 'V', 'VI', 'VII', 'VIII', 'IX', 'X', 'XI', 'XII'][$today->format('n') - 1];
    $year = $today->format('Y');
    
    // Get the last number for this month/year
    $stmt = $conn->prepare("SELECT nomor_surat FROM surat_tugas WHERE nomor_surat LIKE ? ORDER BY id DESC LIMIT 1");
    $pattern = "ST/%/{$month_roman}/{$year}";
    $stmt->bind_param('s', $pattern);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows > 0) {
        $last_surat = $result->fetch_assoc();
        // Extract number from ST/XXX/VIII/2025 format
        preg_match('/ST\/(\d+)\//', $last_surat['nomor_surat'], $matches);
        $next_number = isset($matches[1]) ? intval($matches[1]) + 1 : 1;
    } else {
        $next_number = 1;
    }
    
    return sprintf("ST/%03d/%s/%s", $next_number, $month_roman, $year);
}

// Function to check vehicle availability
function check_vehicle_availability($conn, $kendaraan_id, $tanggal_berangkat, $tanggal_kembali, $exclude_id = null) {
    $end_date = $tanggal_kembali ?: $tanggal_berangkat;
    
    // Check in surat_tugas (block against Disetujui & Dalam Perjalanan)
    $sql = "SELECT COUNT(*) as count FROM surat_tugas 
            WHERE kendaraan_id = ? 
            AND status NOT IN ('Dibatalkan', 'Selesai')
            AND ((tanggal_berangkat <= ? AND (tanggal_kembali >= ? OR tanggal_kembali IS NULL))
                 OR (tanggal_berangkat <= ? AND (tanggal_kembali >= ? OR tanggal_kembali IS NULL)))";
    
    if ($exclude_id) {
        $sql .= " AND id != ?";
    }
    
    $stmt = $conn->prepare($sql);
    if ($exclude_id) {
        $stmt->bind_param('issssi', $kendaraan_id, $tanggal_berangkat, $tanggal_berangkat, $end_date, $end_date, $exclude_id);
    } else {
        $stmt->bind_param('issss', $kendaraan_id, $tanggal_berangkat, $tanggal_berangkat, $end_date, $end_date);
    }
    $stmt->execute();
    $result = $stmt->get_result()->fetch_assoc();
    
    if ($result['count'] > 0) {
        return false;
    }
    
    // Check in jadwal_perawatan (case-insensitive Terjadwal/Dalam Proses)
    $stmt = $conn->prepare("SELECT COUNT(*) as count FROM jadwal_perawatan 
                           WHERE kendaraan_id = ? 
                           AND LOWER(status) IN ('terjadwal', 'dalam proses')
                           AND tanggal_perawatan BETWEEN ? AND ?");
    $stmt->bind_param('iss', $kendaraan_id, $tanggal_berangkat, $end_date);
    $stmt->execute();
    $result = $stmt->get_result()->fetch_assoc();
    
    if ($result['count'] > 0) return false;

    // Also block against peminjaman_kendaraan Approved/Ongoing overlaps
    $stmt = $conn->prepare("SELECT COUNT(*) as count FROM peminjaman_kendaraan 
                           WHERE kendaraan_id = ? 
                           AND LOWER(status) IN ('approved','ongoing')
                           AND ((tanggal_mulai <= ? AND tanggal_selesai >= ?) 
                                OR (tanggal_mulai <= ? AND tanggal_selesai >= ?) 
                                OR (tanggal_mulai >= ? AND tanggal_selesai <= ?))");
    $stmt->bind_param('issssss', $kendaraan_id, $tanggal_berangkat, $tanggal_berangkat, $end_date, $end_date, $tanggal_berangkat, $end_date);
    $stmt->execute();
    $result = $stmt->get_result()->fetch_assoc();
    if ($result['count'] > 0) return false;

    // Also block against riwayat_perbaikan that are not finished (status <> 'Selesai') overlapping dates
    try {
        $rpTbl = $conn->query("SHOW TABLES LIKE 'riwayat_perbaikan'");
        $has_rp = $rpTbl && $rpTbl->num_rows > 0; if ($rpTbl) $rpTbl->free_result();
    } catch (mysqli_sql_exception $e) { $has_rp = false; }
    if (!empty($has_rp)) {
        $stmt = $conn->prepare("SELECT COUNT(*) as count FROM riwayat_perbaikan WHERE kendaraan_id = ? AND LOWER(status) <> 'selesai' AND tanggal_perbaikan BETWEEN ? AND ?");
        if ($stmt) {
            $stmt->bind_param('iss', $kendaraan_id, $tanggal_berangkat, $end_date);
            $stmt->execute();
            $result = $stmt->get_result()->fetch_assoc();
            if ($result['count'] > 0) return false;
        }
    }

    return true;
}

// Function to check user availability  
function check_user_availability($conn, $pengguna_id, $tanggal_berangkat, $tanggal_kembali, $exclude_id = null) {
    $end_date = $tanggal_kembali ?: $tanggal_berangkat;
    
    $sql = "SELECT COUNT(*) as count FROM surat_tugas 
            WHERE pengguna_id = ? 
            AND status NOT IN ('Dibatalkan', 'Selesai')
            AND ((tanggal_berangkat <= ? AND (tanggal_kembali >= ? OR tanggal_kembali IS NULL))
                 OR (tanggal_berangkat <= ? AND (tanggal_kembali >= ? OR tanggal_kembali IS NULL)))";
    
    if ($exclude_id) {
        $sql .= " AND id != ?";
    }
    
    $stmt = $conn->prepare($sql);
    if ($exclude_id) {
        $stmt->bind_param('issssi', $pengguna_id, $tanggal_berangkat, $tanggal_berangkat, $end_date, $end_date, $exclude_id);
    } else {
        $stmt->bind_param('issss', $pengguna_id, $tanggal_berangkat, $tanggal_berangkat, $end_date, $end_date);
    }
    $stmt->execute();
    $result = $stmt->get_result()->fetch_assoc();
    
    return $result['count'] == 0;
}

// Handle form submissions
if ($_POST) {
    if (!validate_csrf_token($_POST['csrf_token'] ?? '')) {
        $msg = '<div class="alert alert-danger">Token keamanan tidak valid!</div>';
    } else {
        // Handle bulk delete
        if (isset($_POST['bulk_delete_ids']) && $can_crud) {
            $ids = array_map('intval', (array)$_POST['bulk_delete_ids']);
            if (!empty($ids)) {
                // Fetch lampiran filenames to unlink
                $in = implode(',', array_fill(0, count($ids), '?'));
                $types = str_repeat('i', count($ids));
                $stmt_files = $conn->prepare("SELECT id, lampiran FROM surat_tugas WHERE id IN ($in)");
                // bind params dynamically
                $refs = array();
                $refs[] = & $types;
                foreach ($ids as $k => $v) {
                    $refs[] = & $ids[$k];
                }
                call_user_func_array(array($stmt_files, 'bind_param'), $refs);
                $stmt_files->execute();
                $res = $stmt_files->get_result();
                $upload_dir = __DIR__ . '/../uploads/surat_lampiran/';
                while ($r = $res->fetch_assoc()) {
                    if (!empty($r['lampiran']) && $r['lampiran'] !== '-' && file_exists($upload_dir . $r['lampiran'])) {
                        @unlink($upload_dir . $r['lampiran']);
                    }
                }
                $stmt_files->close();

                // Delete rows
                $stmt_del = $conn->prepare("DELETE FROM surat_tugas WHERE id IN ($in)");
                $refs2 = array();
                $refs2[] = & $types;
                foreach ($ids as $k => $v) {
                    $refs2[] = & $ids[$k];
                }
                call_user_func_array(array($stmt_del, 'bind_param'), $refs2);
                if ($stmt_del->execute()) {
                    $msg = '<div class="alert alert-success">Surat tugas terpilih berhasil dihapus.</div>';
                    log_user_activity('Menghapus beberapa surat tugas: ' . implode(',', $ids));
                } else {
                    $msg = '<div class="alert alert-danger">Error saat menghapus: ' . $stmt_del->error . '</div>';
                }
                $stmt_del->close();
            }
        }
        if ($action === 'add' && $can_submit) {
            // Auto-generate nomor surat if empty
            $nomor_surat = trim($_POST['nomor_surat']);
            if (empty($nomor_surat)) {
                $nomor_surat = generate_nomor_surat($conn);
            }
            
            // Check if nomor_surat already exists
            $stmt = $conn->prepare("SELECT COUNT(*) as count FROM surat_tugas WHERE nomor_surat = ?");
            $stmt->bind_param('s', $nomor_surat);
            $stmt->execute();
            $result = $stmt->get_result()->fetch_assoc();
            
            if ($result['count'] > 0) {
                $msg = '<div class="alert alert-danger">Nomor surat sudah digunakan! Nomor surat otomatis: ' . generate_nomor_surat($conn) . '</div>';
            } else {
                // tanggal_surat is auto-filled to today and not editable
                $tanggal_surat = date('Y-m-d');
                $klasifikasi = trim($_POST['klasifikasi']);
                // Handle lampiran upload (add)
                $upload_dir = __DIR__ . '/../uploads/surat_lampiran/';
                if (!is_dir($upload_dir)) mkdir($upload_dir, 0755, true);
                $lampiran = '-';
                if (!empty($_FILES['lampiran']) && $_FILES['lampiran']['error'] === UPLOAD_ERR_OK) {
                    $tmp = $_FILES['lampiran']['tmp_name'];
                    $name = basename($_FILES['lampiran']['name']);
                    $ext = pathinfo($name, PATHINFO_EXTENSION);
                    $safe = preg_replace('/[^a-zA-Z0-9-_\.]/', '_', pathinfo($name, PATHINFO_FILENAME));
                    $newname = $safe . '_' . time() . '.' . $ext;
                    if (move_uploaded_file($tmp, $upload_dir . $newname)) {
                        $lampiran = $newname;
                    }
                }
                $perihal = trim($_POST['perihal']);
                $nama_unit = trim($_POST['nama_unit'] ?? 'BIRO UMUM SETJEN KEMHAN');
                $nama_bagian = trim($_POST['nama_bagian'] ?? 'BAGIAN PENGAMANAN');
                $jenis_naskah = trim($_POST['jenis_naskah'] ?? 'NOTA DINAS');
                $surat_dari = trim($_POST['surat_dari'] ?? 'Kabag Pam Roum Setjen Kemhan');
                $kepada_jabatan = trim($_POST['kepada_jabatan']);
                $kepada_tempat = trim($_POST['kepada_tempat']);
                $dasar_a = trim($_POST['dasar_a']);
                $dasar_b = trim($_POST['dasar_b']);
                $berangkat_dari = trim($_POST['berangkat_dari']);
                $waktu_berangkat = trim($_POST['waktu_berangkat']);
                $pejabat_ttd_jabatan = trim($_POST['pejabat_ttd_jabatan']);
                $pejabat_ttd_sebagai = trim($_POST['pejabat_ttd_sebagai']);
                $tembusan_1 = trim($_POST['tembusan_1']);
                $tembusan_2 = trim($_POST['tembusan_2']);
                $tembusan_3 = trim($_POST['tembusan_3']);
                $tembusan_4 = trim($_POST['tembusan_4']);
                $kendaraan_id = (int)$_POST['kendaraan_id'];
                $pengguna_id = $is_user ? (int)$current_user_id : (int)$_POST['pengguna_id'];
                $tujuan = trim($_POST['tujuan']);
                $keperluan = trim($_POST['keperluan']);
                $tanggal_berangkat = $_POST['tanggal_berangkat'];
                $tanggal_kembali = $_POST['tanggal_kembali'] ?? '';
                $estimasi_km = $_POST['estimasi_km'] ? (int)$_POST['estimasi_km'] : null;
                $estimasi_bbm = $_POST['estimasi_bbm'] ? (float)$_POST['estimasi_bbm'] : null;
                $pejabat_ttd = trim($_POST['pejabat_ttd']);

                // Note: Removed previous restriction that limited surat tugas to Bus only.
                
                // Validate tanggal_berangkat not less than today
                $today = date('Y-m-d');
                if ($tanggal_berangkat < $today) {
                    $msg = '<div class="alert alert-danger">Tanggal berangkat tidak boleh kurang dari hari ini.</div>';
                }

                // tanggal_kembali wajib diisi
                if (empty($tanggal_kembali)) {
                    $msg = '<div class="alert alert-danger">Tanggal kembali wajib diisi.</div>';
                } elseif ($tanggal_kembali < $tanggal_berangkat) {
                    $msg = '<div class="alert alert-danger">Tanggal kembali tidak boleh sebelum tanggal berangkat.</div>';
                }

                // Validate vehicle availability
                if (!check_vehicle_availability($conn, $kendaraan_id, $tanggal_berangkat, $tanggal_kembali)) {
                    $msg = '<div class="alert alert-danger">Kendaraan tidak tersedia pada tanggal tersebut! Sudah ada penugasan atau sedang dalam perawatan.</div>';
                } elseif (!check_user_availability($conn, $pengguna_id, $tanggal_berangkat, $tanggal_kembali)) {
                    $msg = '<div class="alert alert-danger">Pengguna tidak tersedia pada tanggal tersebut! Sudah ada penugasan lain.</div>';
                } else {
                    $stmt = $conn->prepare("INSERT INTO surat_tugas (nomor_surat, tanggal_surat, klasifikasi, lampiran, perihal, nama_unit, nama_bagian, jenis_naskah, surat_dari, kepada_jabatan, kepada_tempat, dasar_a, dasar_b, berangkat_dari, waktu_berangkat, pejabat_ttd_jabatan, pejabat_ttd_sebagai, tembusan_1, tembusan_2, tembusan_3, tembusan_4, kendaraan_id, pengguna_id, tujuan, keperluan, tanggal_berangkat, tanggal_kembali, estimasi_km, estimasi_bbm, pejabat_ttd, created_by) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
                    $types = str_repeat('s', 21) . 'ii' . str_repeat('s', 4) . 'idsi';
                    $params = array(
                        $nomor_surat, $tanggal_surat, $klasifikasi, $lampiran, $perihal, $nama_unit, $nama_bagian, $jenis_naskah, $surat_dari, $kepada_jabatan, $kepada_tempat, $dasar_a, $dasar_b, $berangkat_dari, $waktu_berangkat, $pejabat_ttd_jabatan, $pejabat_ttd_sebagai, $tembusan_1, $tembusan_2, $tembusan_3, $tembusan_4, $kendaraan_id, $pengguna_id, $tujuan, $keperluan, $tanggal_berangkat, $tanggal_kembali, $estimasi_km, $estimasi_bbm, $pejabat_ttd, $current_user_id
                    );
                    // Bind parameters by reference using call_user_func_array
                    $bind_names = array();
                    $bind_names[] = & $types;
                    foreach ($params as $key => $value) {
                        $bind_names[] = & $params[$key];
                    }
                    call_user_func_array(array($stmt, 'bind_param'), $bind_names);
                    
                    if ($stmt->execute()) {
                        // Get new surat_tugas ID and prepare schedule/assignment so user sees vehicle on scheduled dates
                        $new_surat_id = $conn->insert_id;

                        // Normalize to datetime windows for schedule tables
                        $mulai_dt = $tanggal_berangkat ? ($tanggal_berangkat . ' 00:00:00') : null;
                        $selesai_dt = $tanggal_kembali ? ($tanggal_kembali . ' 23:59:59') : null;

                        // If the creator is admin-like, create scheduled peminjaman and jadwal immediately.
                        // If creator is a regular user/driver, create a peminjaman_kendaraan request (status=Pending) linked to this surat_tugas for admin approval.
                        $role_creator = strtolower((string)$current_role);
                        $is_admin_like = function_exists('is_admin_like') && is_admin_like();

                        if ($is_admin_like) {
                            // 1) Create peminjaman_terjadwal (approved) if table exists, using dynamic columns
                            try {
                                $tbl = $conn->query("SHOW TABLES LIKE 'peminjaman_terjadwal'");
                                $has_pt = $tbl && $tbl->num_rows > 0; if ($tbl) $tbl->free_result();
                            } catch (mysqli_sql_exception $e) { $has_pt = false; }
                            if (!empty($has_pt)) {
                                $cols_info = $conn->query("SHOW COLUMNS FROM peminjaman_terjadwal");
                                if ($cols_info) {
                                    $cols = array_column($cols_info->fetch_all(MYSQLI_ASSOC), 'Field');
                                    $cols_info->free_result();

                                    // Determine applicant column name
                                    $applicant_col = null;
                                    foreach (["pemohon_id","peminjam_id","pengguna_id","user_id"] as $c) { if (in_array($c, $cols)) { $applicant_col = $c; break; } }

                                    $desired_map = [];
                                    if ($applicant_col) $desired_map[$applicant_col] = ['type'=>'i','value'=>$pengguna_id];
                                    if (in_array('kendaraan_id', $cols)) $desired_map['kendaraan_id'] = ['type'=>'i','value'=>$kendaraan_id];
                                    if (in_array('tujuan', $cols)) $desired_map['tujuan'] = ['type'=>'s','value'=>$tujuan];
                                    if (in_array('keperluan', $cols)) $desired_map['keperluan'] = ['type'=>'s','value'=>$keperluan];
                                    if (in_array('tanggal_mulai', $cols)) $desired_map['tanggal_mulai'] = ['type'=>'s','value'=>$mulai_dt];
                                    if (in_array('tanggal_selesai', $cols)) $desired_map['tanggal_selesai'] = ['type'=>'s','value'=>$selesai_dt];
                                    if (in_array('status', $cols)) $desired_map['status'] = ['type'=>'s','value'=>'approved'];
                                    if (in_array('created_by', $cols)) $desired_map['created_by'] = ['type'=>'i','value'=>$current_user_id];

                                    if (!empty($desired_map)) {
                                        $insert_cols = array_keys($desired_map);
                                        $types_pt = '';
                                        $values_pt = [];
                                        foreach ($desired_map as $meta) { $types_pt .= $meta['type']; $values_pt[] = $meta['value']; }
                                        $placeholders = implode(', ', array_fill(0, count($insert_cols), '?'));
                                        $sql_pt = "INSERT INTO peminjaman_terjadwal (" . implode(', ', $insert_cols) . ") VALUES (" . $placeholders . ")";
                                        $ins_pt = $conn->prepare($sql_pt);
                                        if ($ins_pt) {
                                            $bind_params = [];
                                            $bind_params[] = & $types_pt;
                                            foreach ($values_pt as $i => $v) { $bind_params[] = & $values_pt[$i]; }
                                            call_user_func_array([$ins_pt, 'bind_param'], $bind_params);
                                            if ($ins_pt->execute()) {
                                                $pt_id = $conn->insert_id;
                                                // Also create jadwal_kendaraan referencing peminjaman_terjadwal if table exists
                                                try {
                                                    $jkTbl = $conn->query("SHOW TABLES LIKE 'jadwal_kendaraan'");
                                                    $has_jk = $jkTbl && $jkTbl->num_rows > 0; if ($jkTbl) $jkTbl->free_result();
                                                } catch (mysqli_sql_exception $e) { $has_jk = false; }
                                                if (!empty($has_jk)) {
                                                    $ket = "Peminjaman terjadwal (dari surat tugas): " . ($nomor_surat ?? '');
                                                    $ins_jk = $conn->prepare("INSERT INTO jadwal_kendaraan (kendaraan_id, tipe_penggunaan, referensi_id, pengguna_id, tanggal_mulai, tanggal_selesai, status, keterangan) VALUES (?, 'peminjaman_terjadwal', ?, ?, ?, ?, 'aktif', ?)");
                                                    if ($ins_jk) {
                                                        $ins_jk->bind_param('iiisss', $kendaraan_id, $pt_id, $pengguna_id, $mulai_dt, $selesai_dt, $ket);
                                                        $ins_jk->execute();
                                                        $ins_jk->close();
                                                    }
                                                }
                                            }
                                            $ins_pt->close();
                                        }
                                    }
                                }
                            }

                            // 2) Ensure jadwal_kendaraan for the surat itself (tipe_penggunaan: surat_tugas)
                            try {
                                $jkTbl2 = $conn->query("SHOW TABLES LIKE 'jadwal_kendaraan'");
                                $has_jk2 = $jkTbl2 && $jkTbl2->num_rows > 0; if ($jkTbl2) $jkTbl2->free_result();
                            } catch (mysqli_sql_exception $e) { $has_jk2 = false; }
                            if (!empty($has_jk2)) {
                                $ket2 = "Surat Tugas: " . ($nomor_surat ?? '');
                                $ins_jk2 = $conn->prepare("INSERT INTO jadwal_kendaraan (kendaraan_id, tipe_penggunaan, referensi_id, pengguna_id, tanggal_mulai, tanggal_selesai, status, keterangan) VALUES (?, 'surat_tugas', ?, ?, ?, ?, 'aktif', ?)");
                                if ($ins_jk2) {
                                    $ins_jk2->bind_param('iiisss', $kendaraan_id, $new_surat_id, $pengguna_id, $mulai_dt, $selesai_dt, $ket2);
                                    $ins_jk2->execute();
                                    $ins_jk2->close();
                                }
                            }
                        } else {
                            // Creator is a regular user/driver: create peminjaman_kendaraan request with status Pending and link to surat_tugas
                            try {
                                $tblpk = $conn->query("SHOW TABLES LIKE 'peminjaman_kendaraan'");
                                $has_pk = $tblpk && $tblpk->num_rows > 0; if ($tblpk) $tblpk->free_result();
                            } catch (mysqli_sql_exception $e) { $has_pk = false; }
                            if (!empty($has_pk)) {
                                $cols_info = $conn->query("SHOW COLUMNS FROM peminjaman_kendaraan");
                                if ($cols_info) {
                                    $cols = array_column($cols_info->fetch_all(MYSQLI_ASSOC), 'Field');
                                    $cols_info->free_result();

                                    $desired_map = [];
                                    foreach (["pemohon_id","peminjam_id","pengguna_id","user_id"] as $c) { if (in_array($c,$cols)) { $desired_map[$c] = ['type'=>'i','value'=>$pengguna_id]; break; } }
                                    if (in_array('kendaraan_id',$cols)) $desired_map['kendaraan_id'] = ['type'=>'i','value'=>$kendaraan_id];
                                    if (in_array('nomor_surat',$cols)) $desired_map['nomor_surat'] = ['type'=>'s','value'=>$nomor_surat];
                                    if (in_array('tanggal_mulai',$cols)) $desired_map['tanggal_mulai'] = ['type'=>'s','value'=>$mulai_dt];
                                    if (in_array('tanggal_selesai',$cols)) $desired_map['tanggal_selesai'] = ['type'=>'s','value'=>$selesai_dt];
                                    if (in_array('tujuan',$cols)) $desired_map['tujuan'] = ['type'=>'s','value'=>$tujuan];
                                    if (in_array('keperluan',$cols)) $desired_map['keperluan'] = ['type'=>'s','value'=>$keperluan];
                                    if (in_array('status',$cols)) $desired_map['status'] = ['type'=>'s','value'=>'Pending'];
                                    if (in_array('surat_tugas_id',$cols)) $desired_map['surat_tugas_id'] = ['type'=>'i','value'=>$new_surat_id];
                                    if (in_array('created_by',$cols)) $desired_map['created_by'] = ['type'=>'i','value'=>$current_user_id];

                                    if (!empty($desired_map)) {
                                        $insert_cols = array_keys($desired_map);
                                        $types_pk = '';
                                        $values_pk = [];
                                        foreach ($desired_map as $meta) { $types_pk .= $meta['type']; $values_pk[] = $meta['value']; }
                                        $placeholders = implode(', ', array_fill(0, count($insert_cols), '?'));
                                        $sql_pk = "INSERT INTO peminjaman_kendaraan (" . implode(', ', $insert_cols) . ") VALUES (" . $placeholders . ")";
                                        $ins_pk = $conn->prepare($sql_pk);
                                        if ($ins_pk) {
                                            $bind_params = [];
                                            $bind_params[] = & $types_pk;
                                            foreach ($values_pk as $i => $v) { $bind_params[] = & $values_pk[$i]; }
                                            call_user_func_array([$ins_pk, 'bind_param'], $bind_params);
                                            if ($ins_pk->execute()) {
                                                $pk_id = $conn->insert_id;
                                                // Notify admin-like roles about new peminjaman request
                                                $admin_role_ids = function_exists('get_admin_like_role_ids') ? get_admin_like_role_ids() : [];
                                                $admin_like_users = null;
                                                if (!empty($admin_role_ids)) {
                                                    $in_ids = implode(',', array_map('intval', $admin_role_ids));
                                                    $admin_like_users = $conn->query("SELECT p.id FROM pengguna p JOIN user_account ua ON p.id = ua.pengguna_id WHERE ua.role_id IN ({$in_ids})");
                                                }
                                                $noteMsg = 'Pengajuan peminjaman dari user telah dibuat untuk Surat Tugas: ' . ($nomor_surat ?? '') . '. Mohon persetujuan.';
                                                if ($admin_like_users) {
                                                    while ($adm = $admin_like_users->fetch_assoc()) {
                                                        insert_notification($conn, (int)$adm['id'], $noteMsg, 'Permohonan Peminjaman');
                                                    }
                                                }
                                            }
                                            $ins_pk->close();
                                        }
                                    }
                                }
                            }
                            // Do not create jadwal_kendaraan yet; wait for admin approval
                        }

                        // Note: Legacy pengguna_kendaraan upsert removed; views now derive from surat_tugas/peminjaman tables.

                        $msg = '<div class="alert alert-success">Surat tugas berhasil ditambahkan!</div>';
                        log_user_activity("Menambah surat tugas: $nomor_surat");
                        log_surat_tugas_role_activity($current_role, "Menambah surat tugas: $nomor_surat");
                        header('Location: ?page=surat_tugas&msg=success&text=' . urlencode('Surat tugas berhasil ditambahkan!'));
                        exit;
                    } else {
                        $msg = '<div class="alert alert-danger">Error: ' . $stmt->error . '</div>';
                    }
                    $stmt->close();
                }
            }
            
        } elseif ($action === 'edit' && $can_crud && $surat_id) {
            $nomor_surat = trim($_POST['nomor_surat']);
            $tanggal_surat = $_POST['tanggal_surat'];
            $klasifikasi = trim($_POST['klasifikasi']);
            // Handle lampiran upload (edit)
            $upload_dir = __DIR__ . '/../uploads/surat_lampiran/';
            if (!is_dir($upload_dir)) mkdir($upload_dir, 0755, true);
            $lampiran = $_POST['existing_lampiran'] ?? '-';
            if (!empty($_FILES['lampiran']) && $_FILES['lampiran']['error'] === UPLOAD_ERR_OK) {
                $tmp = $_FILES['lampiran']['tmp_name'];
                $name = basename($_FILES['lampiran']['name']);
                $ext = pathinfo($name, PATHINFO_EXTENSION);
                $safe = preg_replace('/[^a-zA-Z0-9-_\.]/', '_', pathinfo($name, PATHINFO_FILENAME));
                $newname = $safe . '_' . time() . '.' . $ext;
                if (move_uploaded_file($tmp, $upload_dir . $newname)) {
                    // Optionally delete old file
                    if (!empty($lampiran) && $lampiran !== '-' && file_exists($upload_dir . $lampiran)) {
                        @unlink($upload_dir . $lampiran);
                    }
                    $lampiran = $newname;
                }
            }
            $perihal = trim($_POST['perihal']);
            $nama_unit = trim($_POST['nama_unit'] ?? 'BIRO UMUM SETJEN KEMHAN');
            $nama_bagian = trim($_POST['nama_bagian'] ?? 'BAGIAN PENGAMANAN');
            $jenis_naskah = trim($_POST['jenis_naskah'] ?? 'NOTA DINAS');
            $surat_dari = trim($_POST['surat_dari'] ?? 'Kabag Pam Roum Setjen Kemhan');
            $kepada_jabatan = trim($_POST['kepada_jabatan']);
            $kepada_tempat = trim($_POST['kepada_tempat']);
            $dasar_a = trim($_POST['dasar_a']);
            $dasar_b = trim($_POST['dasar_b']);
            $berangkat_dari = trim($_POST['berangkat_dari']);
            $waktu_berangkat = trim($_POST['waktu_berangkat']);
            $pejabat_ttd_jabatan = trim($_POST['pejabat_ttd_jabatan']);
            $pejabat_ttd_sebagai = trim($_POST['pejabat_ttd_sebagai']);
            $tembusan_1 = trim($_POST['tembusan_1']);
            $tembusan_2 = trim($_POST['tembusan_2']);
            $tembusan_3 = trim($_POST['tembusan_3']);
            $tembusan_4 = trim($_POST['tembusan_4']);
            $kendaraan_id = (int)$_POST['kendaraan_id'];
            $pengguna_id = (int)$_POST['pengguna_id'];
            $tujuan = trim($_POST['tujuan']);
            $keperluan = trim($_POST['keperluan']);
            $tanggal_berangkat = $_POST['tanggal_berangkat'];
            $tanggal_kembali = $_POST['tanggal_kembali'] ?: null;
            $estimasi_km = $_POST['estimasi_km'] ? (int)$_POST['estimasi_km'] : null;
            $estimasi_bbm = $_POST['estimasi_bbm'] ? (float)$_POST['estimasi_bbm'] : null;
            $status = $_POST['status'];
            $km_berangkat = $_POST['km_berangkat'] ? (int)$_POST['km_berangkat'] : null;
            $km_kembali = $_POST['km_kembali'] ? (int)$_POST['km_kembali'] : null;
            $bbm_terpakai = $_POST['bbm_terpakai'] ? (float)$_POST['bbm_terpakai'] : null;
            $laporan_perjalanan = trim($_POST['laporan_perjalanan']);
            $pejabat_ttd = trim($_POST['pejabat_ttd']);

            // Kebijakan: peminjaman via surat tugas hanya untuk kendaraan jenis Bus.
                // Note: Removed previous restriction that limited surat tugas to Bus only.
            // Fetch previous surat fields so we only validate availability when relevant fields change
            $prev_kendaraan_id = null;
            $prev_pengguna_id = null;
            $prev_tanggal_berangkat = null;
            $prev_tanggal_kembali = null;
            $prev_status = null;
            $stmt_prev = $conn->prepare("SELECT kendaraan_id, pengguna_id, tanggal_berangkat, tanggal_kembali, status FROM surat_tugas WHERE id = ?");
            if ($stmt_prev) {
                $stmt_prev->bind_param('i', $surat_id);
                $stmt_prev->execute();
                $res_prev = $stmt_prev->get_result();
                if ($res_prev && $row_prev = $res_prev->fetch_assoc()) {
                    $prev_kendaraan_id = isset($row_prev['kendaraan_id']) ? (int)$row_prev['kendaraan_id'] : null;
                    $prev_pengguna_id = isset($row_prev['pengguna_id']) ? (int)$row_prev['pengguna_id'] : null;
                    $prev_tanggal_berangkat = $row_prev['tanggal_berangkat'] ?? null;
                    $prev_tanggal_kembali = $row_prev['tanggal_kembali'] ?? null;
                    $prev_status = $row_prev['status'] ?? null;
                }
                $stmt_prev->close();
            }
            
            // Check for duplicate nomor_surat (excluding current record)
            $stmt_check = $conn->prepare("SELECT COUNT(*) as count FROM surat_tugas WHERE nomor_surat = ? AND id != ?");
            $stmt_check->bind_param('si', $nomor_surat, $surat_id);
            $stmt_check->execute();
            $duplicate_check = $stmt_check->get_result()->fetch_assoc();
            $stmt_check->close();
            
            if ($duplicate_check['count'] > 0) {
                $msg = '<div class="alert alert-danger">Nomor surat sudah digunakan oleh surat tugas lain!</div>';
            } else {
                // Persetujuan surat tugas hanya dapat dilakukan admin.
                if (empty($msg)) {
                    $status_changed = ($status !== $prev_status);
                    $status_target = strtolower(trim((string)$status));
                    if ($status_changed && $status_target === 'disetujui' && !is_admin_like()) {
                        $msg = '<div class="alert alert-danger">Status Disetujui hanya dapat ditetapkan oleh admin atau pimpinan.</div>';
                    }
                }

                // Enforce tanggal_berangkat not earlier than today, but only if tanggal_berangkat was changed
                $today = date('Y-m-d');
                if ($tanggal_berangkat < $today && $tanggal_berangkat !== $prev_tanggal_berangkat) {
                    $msg = '<div class="alert alert-danger">Tanggal berangkat tidak boleh kurang dari hari ini.</div>';
                }

                // Only perform availability checks if kendaraan/pengguna/tanggal range changed
                $fields_changed = (
                    $kendaraan_id !== $prev_kendaraan_id ||
                    $pengguna_id !== $prev_pengguna_id ||
                    $tanggal_berangkat !== $prev_tanggal_berangkat ||
                    ($tanggal_kembali ?: '') !== ($prev_tanggal_kembali ?: '')
                );

                if (empty($msg) && $fields_changed) {
                    if ($status !== 'Dibatalkan' && !check_vehicle_availability($conn, $kendaraan_id, $tanggal_berangkat, $tanggal_kembali, $surat_id)) {
                        $msg = '<div class="alert alert-danger">Kendaraan tidak tersedia pada tanggal tersebut! Sudah ada penugasan atau sedang dalam perawatan.</div>';
                    } elseif ($status !== 'Dibatalkan' && !check_user_availability($conn, $pengguna_id, $tanggal_berangkat, $tanggal_kembali, $surat_id)) {
                        $msg = '<div class="alert alert-danger">Pengguna tidak tersedia pada tanggal tersebut! Sudah ada penugasan lain.</div>';
                    }
                }
                $stmt = $conn->prepare("UPDATE surat_tugas SET nomor_surat=?, tanggal_surat=?, klasifikasi=?, lampiran=?, perihal=?, nama_unit=?, nama_bagian=?, jenis_naskah=?, surat_dari=?, kepada_jabatan=?, kepada_tempat=?, dasar_a=?, dasar_b=?, berangkat_dari=?, waktu_berangkat=?, pejabat_ttd_jabatan=?, pejabat_ttd_sebagai=?, tembusan_1=?, tembusan_2=?, tembusan_3=?, tembusan_4=?, kendaraan_id=?, pengguna_id=?, tujuan=?, keperluan=?, tanggal_berangkat=?, tanggal_kembali=?, estimasi_km=?, estimasi_bbm=?, status=?, km_berangkat=?, km_kembali=?, bbm_terpakai=?, laporan_perjalanan=?, pejabat_ttd=?, updated_by=? WHERE id=?");
                    $types_upd = str_repeat('s', 21) . 'ii' . str_repeat('s', 4) . 'ids' . 'ii' . 'd' . 'ss' . 'ii';
                    // Above constructed expecting: 17s, kendaraan_id i, pengguna_id i, 4s (tujuan-ke...kembali), estimasi_km i, estimasi_bbm d, status s,
                    // km_berangkat i, km_kembali i, bbm_terpakai d, laporan_perjalanan s, pejabat_ttd s, updated_by i, id i
                    $params_upd = array(
                        $nomor_surat, $tanggal_surat, $klasifikasi, $lampiran, $perihal, $nama_unit, $nama_bagian, $jenis_naskah, $surat_dari, $kepada_jabatan, $kepada_tempat, $dasar_a, $dasar_b, $berangkat_dari, $waktu_berangkat, $pejabat_ttd_jabatan, $pejabat_ttd_sebagai, $tembusan_1, $tembusan_2, $tembusan_3, $tembusan_4, $kendaraan_id, $pengguna_id, $tujuan, $keperluan, $tanggal_berangkat, $tanggal_kembali, $estimasi_km, $estimasi_bbm, $status, $km_berangkat, $km_kembali, $bbm_terpakai, $laporan_perjalanan, $pejabat_ttd, $current_user_id, $surat_id
                    );
                    $bind_names_upd = array();
                    $bind_names_upd[] = & $types_upd;
                    foreach ($params_upd as $k => $v) {
                        $bind_names_upd[] = & $params_upd[$k];
                    }
                    call_user_func_array(array($stmt, 'bind_param'), $bind_names_upd);
                
                if ($stmt->execute()) {
                    $prev_status_l = strtolower(trim((string)$prev_status));
                    $new_status_l = strtolower(trim((string)$status));
                    if ($new_status_l === 'disetujui' && $prev_status_l !== 'disetujui') {
                        // Admin approval creates handoff request to pimpinan.
                        if ($has_approval_pimpinan_col) {
                            $stp = $conn->prepare("UPDATE surat_tugas SET approval_pimpinan_status = 'Pending', approval_pimpinan_by = NULL, approval_pimpinan_at = NULL, updated_at = NOW() WHERE id = ?");
                            if ($stp) {
                                $stp->bind_param('i', $surat_id);
                                $stp->execute();
                                $stp->close();
                            }
                        }

                        // Ensure peminjaman request exists for pimpinan approval.
                        $pkColsRes = $conn->query("SHOW COLUMNS FROM peminjaman_kendaraan");
                        $pkCols = $pkColsRes ? array_column($pkColsRes->fetch_all(MYSQLI_ASSOC), 'Field') : [];
                        $pkHasSuratId = in_array('surat_tugas_id', $pkCols, true);
                        if (!$pkHasSuratId) {
                            @$conn->query("ALTER TABLE peminjaman_kendaraan ADD COLUMN surat_tugas_id INT NULL AFTER kendaraan_id");
                            @$conn->query("ALTER TABLE peminjaman_kendaraan ADD INDEX idx_pk_surat_tugas_id (surat_tugas_id)");
                            $pkColsRes2 = $conn->query("SHOW COLUMNS FROM peminjaman_kendaraan");
                            $pkCols = $pkColsRes2 ? array_column($pkColsRes2->fetch_all(MYSQLI_ASSOC), 'Field') : $pkCols;
                            $pkHasSuratId = in_array('surat_tugas_id', $pkCols, true);
                        }

                        $hasPending = false;
                        if ($pkHasSuratId) {
                            $chkPk = $conn->prepare("SELECT id FROM peminjaman_kendaraan WHERE surat_tugas_id = ? AND status = 'Pending' LIMIT 1");
                            if ($chkPk) {
                                $chkPk->bind_param('i', $surat_id);
                                $chkPk->execute();
                                $hasPending = (bool)$chkPk->get_result()->fetch_assoc();
                                $chkPk->close();
                            }
                        }

                        if (!$hasPending) {
                            $applicant_col = null;
                            foreach (['peminjam_id','pemohon_id','pengguna_id','user_id'] as $c) {
                                if (in_array($c, $pkCols, true)) { $applicant_col = $c; break; }
                            }
                            $approver_col = null;
                            foreach (['approved_by','approval_by','approver_id','approved_by_id','approver'] as $c) {
                                if (in_array($c, $pkCols, true)) { $approver_col = $c; break; }
                            }

                            $desired = [];
                            if (in_array('kendaraan_id', $pkCols, true)) $desired['kendaraan_id'] = ['t' => 'i', 'v' => $kendaraan_id];
                            if ($applicant_col) $desired[$applicant_col] = ['t' => 'i', 'v' => $pengguna_id];
                            if ($pkHasSuratId) $desired['surat_tugas_id'] = ['t' => 'i', 'v' => $surat_id];
                            if (in_array('nomor_surat', $pkCols, true)) $desired['nomor_surat'] = ['t' => 's', 'v' => $nomor_surat];
                            if (in_array('tanggal_mulai', $pkCols, true)) $desired['tanggal_mulai'] = ['t' => 's', 'v' => $tanggal_berangkat];
                            if (in_array('tanggal_selesai', $pkCols, true)) $desired['tanggal_selesai'] = ['t' => 's', 'v' => ($tanggal_kembali ?: $tanggal_berangkat)];
                            if (in_array('tujuan', $pkCols, true)) $desired['tujuan'] = ['t' => 's', 'v' => $tujuan];
                            if (in_array('keperluan', $pkCols, true)) $desired['keperluan'] = ['t' => 's', 'v' => $keperluan];
                            if (in_array('status', $pkCols, true)) $desired['status'] = ['t' => 's', 'v' => 'Pending'];
                            if (in_array('catatan_approval', $pkCols, true)) $desired['catatan_approval'] = ['t' => 's', 'v' => 'Permohonan dari Surat Tugas #' . $surat_id . ' menunggu persetujuan pimpinan'];
                            if ($approver_col) $desired[$approver_col] = ['t' => 'i', 'v' => null];

                            $insCols = [];
                            $insTypes = '';
                            $insVals = [];
                            foreach ($desired as $col => $meta) {
                                if (in_array($col, $pkCols, true)) {
                                    $insCols[] = $col;
                                    $insTypes .= $meta['t'];
                                    $insVals[] = $meta['v'];
                                }
                            }

                            if (!empty($insCols)) {
                                $ph = array_fill(0, count($insCols), '?');
                                $sqlPk = "INSERT INTO peminjaman_kendaraan (" . implode(', ', $insCols) . ") VALUES (" . implode(', ', $ph) . ")";
                                $insPk = $conn->prepare($sqlPk);
                                if ($insPk) {
                                    $bindArgs = [];
                                    $bindArgs[] = & $insTypes;
                                    for ($i = 0; $i < count($insVals); $i++) { $bindArgs[] = & $insVals[$i]; }
                                    call_user_func_array([$insPk, 'bind_param'], $bindArgs);
                                    $insPk->execute();
                                    $insPk->close();
                                }
                            }
                        }

                        $notifMsg = 'Surat tugas ' . ($nomor_surat ?: '-') . ' sudah diverifikasi admin dan diteruskan sebagai permohonan peminjaman ke pimpinan.';
                        insert_notification($conn, (int)$pengguna_id, $notifMsg, 'Surat Tugas Disetujui', 'success', 'document');
                    }

                    // If the surat was cancelled, mark the associated vehicle as available/operational.
                    if ($status === 'Dibatalkan') {
                        // Prefer freeing the previous kendaraan attached to this surat. If not available, fall back to posted kendaraan_id.
                        $to_free = $prev_kendaraan_id ?: $kendaraan_id;
                        if ($to_free) {
                            $stmt_free = $conn->prepare("UPDATE kendaraan SET status_peminjaman='Tersedia', status_kendaraan='Operasional' WHERE id = ?");
                            if ($stmt_free) {
                                $stmt_free->bind_param('i', $to_free);
                                $stmt_free->execute();
                                $stmt_free->close();
                            }
                            log_user_activity("Membatalkan surat tugas ID: $surat_id; kendaraan ID $to_free diset Tersedia/Operasional");
                        }
                    }

                    // Handle approvals / active assignments: create scheduled loan entries and assignments
                    $status_l = strtolower(trim((string)$status));
                    $approval_pimpinan_final = true;
                    if ($has_approval_pimpinan_col && $status_l === 'disetujui') {
                        $ap = $conn->prepare("SELECT approval_pimpinan_status FROM surat_tugas WHERE id = ? LIMIT 1");
                        if ($ap) {
                            $ap->bind_param('i', $surat_id);
                            $ap->execute();
                            $aprow = $ap->get_result()->fetch_assoc();
                            $ap->close();
                            $approval_pimpinan_final = (strtolower((string)($aprow['approval_pimpinan_status'] ?? '')) === 'approved');
                        }
                    }

                    if (in_array($status_l, ['disetujui', 'approved', 'approve', 'dalam perjalanan']) && $approval_pimpinan_final) {
                        // Immediately mark kendaraan as Dipinjam when approved
                        if (!empty($kendaraan_id)) {
                            $upd_k = $conn->prepare("UPDATE kendaraan SET status_peminjaman='Dipinjam' WHERE id = ?");
                            if ($upd_k) { $upd_k->bind_param('i', $kendaraan_id); $upd_k->execute(); $upd_k->close(); }
                        }
                        // Use posted values if present, otherwise fall back to previous values fetched earlier
                        $penerima = $pengguna_id ? (int)$pengguna_id : ($prev_pengguna_id ? (int)$prev_pengguna_id : null);
                        $kend = $kendaraan_id ? (int)$kendaraan_id : ($prev_kendaraan_id ? (int)$prev_kendaraan_id : null);
                        $tgl_mulai = !empty($tanggal_berangkat) ? $tanggal_berangkat : $prev_tanggal_berangkat;
                        $tgl_selesai = !empty($tanggal_kembali) ? $tanggal_kembali : $prev_tanggal_kembali;

                        // 1) Ensure jadwal_kendaraan entry exists and mark active
                        $has_jadwal = false;
                        $res_jk = $conn->query("SHOW TABLES LIKE 'jadwal_kendaraan'");
                        if ($res_jk && $res_jk->num_rows > 0) $has_jadwal = true;

                        // If jadwal_kendaraan exists, check if an entry for this surat already exists; if not, insert one
                        if ($has_jadwal) {
                            $chk_jk = $conn->prepare("SELECT COUNT(*) as cnt FROM jadwal_kendaraan WHERE ref_id = ? AND ref_type = 'surat_tugas'");
                            if ($chk_jk) {
                                $chk_jk->bind_param('i', $surat_id);
                                $chk_jk->execute();
                                $cnt = $chk_jk->get_result()->fetch_assoc()['cnt'] ?? 0;
                                $chk_jk->close();
                            } else {
                                $cnt = 0;
                            }

                            if (empty($cnt)) {
                                $keterangan = "Surat Tugas: " . ($nomor_surat ?? '');
                                $ins_jk = $conn->prepare("INSERT INTO jadwal_kendaraan (kendaraan_id, tipe_penggunaan, referensi_id, pengguna_id, tanggal_mulai, tanggal_selesai, status, keterangan) VALUES (?, 'surat_tugas', ?, ?, ?, ?, 'aktif', ?)");
                                if ($ins_jk) {
                                    $ins_jk->bind_param('iiisss', $kend, $surat_id, $penerima, $tgl_mulai, $tgl_selesai, $keterangan);
                                    $ins_jk->execute();
                                    $ins_jk->close();
                                }
                            } else {
                                // If already exists, try to mark it active
                                $upd_jk = $conn->prepare("UPDATE jadwal_kendaraan SET status = 'aktif', tanggal_mulai = ?, tanggal_selesai = ? WHERE ref_id = ? AND ref_type = 'surat_tugas'");
                                if ($upd_jk) {
                                    $upd_jk->bind_param('ssi', $tgl_mulai, $tgl_selesai, $surat_id);
                                    $upd_jk->execute();
                                    $upd_jk->close();
                                }
                            }
                        }

                        // 2) Create peminjaman_terjadwal record (so it appears in user's scheduled loans) if table exists
                        $res_pt = $conn->query("SHOW TABLES LIKE 'peminjaman_terjadwal'");
                        if ($res_pt && $res_pt->num_rows > 0) {
                            // Avoid duplicates by checking if there is already a jadwal_kendaraan ref for this surat (we already handled insertion above)
                            $should_insert_pt = true;
                            if ($has_jadwal) {
                                $chk = $conn->prepare("SELECT COUNT(*) as cnt FROM jadwal_kendaraan WHERE ref_id = ? AND ref_type = 'surat_tugas'");
                                if ($chk) {
                                    $chk->bind_param('i', $surat_id);
                                    $chk->execute();
                                    $cnt2 = $chk->get_result()->fetch_assoc()['cnt'] ?? 0;
                                    $chk->close();
                                    if ($cnt2 > 0) $should_insert_pt = false; // already represented via jadwal_kendaraan -> assume peminjaman_terjadwal exists/was created
                                }
                            }

                            if ($should_insert_pt) {
                                $cols_info = $conn->query("SHOW COLUMNS FROM peminjaman_terjadwal")->fetch_all(MYSQLI_ASSOC);
                                $cols = array_column($cols_info, 'Field');

                                // determine applicant column if any
                                $applicant_col = null;
                                foreach (['pemohon_id','peminjam_id','pengguna_id','user_id'] as $c) {
                                    if (in_array($c, $cols)) { $applicant_col = $c; break; }
                                }

                                $desired_map = [];
                                if ($applicant_col) { $desired_map[$applicant_col] = ['type'=>'i','value'=>$penerima]; }
                                if (in_array('kendaraan_id', $cols)) $desired_map['kendaraan_id'] = ['type'=>'i','value'=>$kend];
                                if (in_array('keperluan', $cols)) $desired_map['keperluan'] = ['type'=>'s','value'=>$keperluan];
                                if (in_array('tujuan', $cols)) $desired_map['tujuan'] = ['type'=>'s','value'=>$tujuan];
                                if (in_array('tanggal_mulai', $cols)) $desired_map['tanggal_mulai'] = ['type'=>'s','value'=>$tgl_mulai];
                                if (in_array('tanggal_selesai', $cols)) $desired_map['tanggal_selesai'] = ['type'=>'s','value'=>$tgl_selesai];
                                if (in_array('status', $cols)) $desired_map['status'] = ['type'=>'s','value'=>'approved'];
                                if (in_array('created_by', $cols)) $desired_map['created_by'] = ['type'=>'i','value'=>$current_user_id];

                                $insert_cols = [];
                                $types_pt = '';
                                $values_pt = [];
                                foreach ($desired_map as $col => $meta) {
                                    if (in_array($col, $cols)) {
                                        $insert_cols[] = $col;
                                        $types_pt .= $meta['type'];
                                        $values_pt[] = $meta['value'];
                                    }
                                }

                                if (!empty($insert_cols)) {
                                    $placeholders = array_fill(0, count($insert_cols), '?');
                                    $sql_pt = "INSERT INTO peminjaman_terjadwal (" . implode(', ', $insert_cols) . ") VALUES (" . implode(', ', $placeholders) . ")";
                                    $ins_pt = $conn->prepare($sql_pt);
                                    if ($ins_pt) {
                                        $bind_params = [];
                                        $bind_params[] = & $types_pt;
                                        for ($i = 0; $i < count($values_pt); $i++) { $bind_params[] = & $values_pt[$i]; }
                                        call_user_func_array(array($ins_pt, 'bind_param'), $bind_params);
                                        if ($ins_pt->execute()) {
                                            $pt_id = $conn->insert_id;
                                            // Also create jadwal_kendaraan pointing to this peminjaman_terjadwal if jadwal table exists
                                            if ($has_jadwal) {
                                                $keterangan = "Peminjaman terjadwal (dari surat tugas): " . ($nomor_surat ?? '');
                                                $schedule_stmt = $conn->prepare("INSERT INTO jadwal_kendaraan (kendaraan_id, tipe_penggunaan, referensi_id, pengguna_id, tanggal_mulai, tanggal_selesai, keterangan) VALUES (?, 'peminjaman_terjadwal', ?, ?, ?, ?, ?)");
                                                if ($schedule_stmt) {
                                                    $schedule_stmt->bind_param('iiisss', $kend, $pt_id, $penerima, $tgl_mulai, $tgl_selesai, $keterangan);
                                                    $schedule_stmt->execute();
                                                    $schedule_stmt->close();
                                                }
                                            }
                                        }
                                        $ins_pt->close();
                                    }
                                }
                            }
                        }

                        // Note: Legacy pengguna_kendaraan upsert removed; access/UI use jadwal_kendaraan and status sync.

                        log_user_activity("Menyetujui/menetapkan surat tugas ID: $surat_id; menetapkan kendaraan ID: " . ($kend ?? 'null') . " ke pengguna ID: " . ($penerima ?? 'null'));
                    }

                    // If status moved to Selesai, create a laporan_perjalanan entry if not already present
                    $prev_status_l = strtolower(trim((string)$prev_status));
                    $new_status_l = strtolower(trim((string)$status));
                    if ($new_status_l === 'selesai') {
                        try {
                            $tblLp = $conn->query("SHOW TABLES LIKE 'laporan_perjalanan'");
                            if ($tblLp && $tblLp->num_rows > 0) {
                                $tanggal_lp = $tanggal_kembali ?: $tanggal_berangkat ?: null;
                                $kend_id = $kendaraan_id ?: $prev_kendaraan_id ?: 0;
                                if ($tanggal_lp && $kend_id > 0) {
                                    // determine pengguna for laporan: prefer posted pengguna_id, then previous, then kendaraan.pengguna_id
                                    $peng_id = !empty($pengguna_id) ? (int)$pengguna_id : (!empty($prev_pengguna_id) ? (int)$prev_pengguna_id : 0);
                                    if (empty($peng_id)) {
                                        $stp = $conn->prepare("SELECT pengguna_id FROM kendaraan WHERE id = ? LIMIT 1");
                                        if ($stp) {
                                            $stp->bind_param('i', $kend_id);
                                            $stp->execute();
                                            $pv = $stp->get_result()->fetch_assoc();
                                            $stp->close();
                                            $peng_id = !empty($pv['pengguna_id']) ? (int)$pv['pengguna_id'] : 0;
                                        }
                                    }

                                    $uraian = trim($laporan_perjalanan ?: $keperluan ?: $perihal ?: ('Surat Tugas ' . ($nomor_surat ?? $surat_id)));
                                    $route = trim($tujuan ?? '');
                                    $jarak_km_val = null;
                                    if (isset($estimasi_km) && $estimasi_km !== '' && is_numeric($estimasi_km)) {
                                        $jarak_km_val = (int)$estimasi_km;
                                    }

                                    // avoid duplicate laporan for same kendaraan+tanggal (berangkat/kembali)
                                    $tgl_ber = $tanggal_berangkat ?: null;
                                    $tgl_kem = $tanggal_kembali ?: null;
                                    $chkLp = $conn->prepare("SELECT COUNT(*) c FROM laporan_perjalanan WHERE kendaraan_id = ? AND tanggal IN (?, ?) LIMIT 1");
                                    $dupLp = false;
                                    if ($chkLp) {
                                        $chkLp->bind_param('iss', $kend_id, $tgl_ber, $tgl_kem);
                                        $chkLp->execute();
                                        $cres = $chkLp->get_result()->fetch_assoc();
                                        $dupLp = ((int)($cres['c'] ?? 0)) > 0;
                                        $chkLp->close();
                                    }

                                    if (!$dupLp) {
                                        if ($jarak_km_val === null) {
                                            if ($peng_id > 0) {
                                                $ins = $conn->prepare("INSERT INTO laporan_perjalanan (tanggal, kendaraan_id, pengguna_id, uraian_kegiatan, route, jarak_km, created_at) VALUES (?, ?, ?, ?, ?, NULL, NOW())");
                                                if ($ins) { $ins->bind_param('siiss', $tanggal_lp, $kend_id, $peng_id, $uraian, $route); $ins->execute(); $ins->close(); }
                                            } else {
                                                $ins = $conn->prepare("INSERT INTO laporan_perjalanan (tanggal, kendaraan_id, pengguna_id, uraian_kegiatan, route, jarak_km, created_at) VALUES (?, ?, NULL, ?, ?, NULL, NOW())");
                                                if ($ins) { $ins->bind_param('siss', $tanggal_lp, $kend_id, $uraian, $route); $ins->execute(); $ins->close(); }
                                            }
                                        } else {
                                            if ($peng_id > 0) {
                                                $ins = $conn->prepare("INSERT INTO laporan_perjalanan (tanggal, kendaraan_id, pengguna_id, uraian_kegiatan, route, jarak_km, created_at) VALUES (?, ?, ?, ?, ?, ?, NOW())");
                                                if ($ins) { $kms = (double)$jarak_km_val; $ins->bind_param('siissd', $tanggal_lp, $kend_id, $peng_id, $uraian, $route, $kms); $ins->execute(); $ins->close(); }
                                            } else {
                                                $ins = $conn->prepare("INSERT INTO laporan_perjalanan (tanggal, kendaraan_id, pengguna_id, uraian_kegiatan, route, jarak_km, created_at) VALUES (?, ?, NULL, ?, ?, ?, NOW())");
                                                if ($ins) { $kms = (double)$jarak_km_val; $ins->bind_param('sissd', $tanggal_lp, $kend_id, $uraian, $route, $kms); $ins->execute(); $ins->close(); }
                                            }
                                        }
                                    }
                                }
                            }
                        } catch (Throwable $e) {
                            @file_put_contents(__DIR__ . '/../logs/run_status_transitions_history_error.log', date('c') . ' ' . $e->getMessage() . "\n", FILE_APPEND);
                        }
                    }

                    // Ensure kendaraan marked available when surat tugas set to Selesai
                    if ($new_status_l === 'selesai') {
                        $to_free = $kendaraan_id ?: $prev_kendaraan_id ?: 0;
                        if ($to_free > 0) {
                            $stmt_free2 = $conn->prepare("UPDATE kendaraan SET status_peminjaman='Tersedia', status_kendaraan='Operasional', updated_at = NOW() WHERE id = ?");
                            if ($stmt_free2) {
                                $stmt_free2->bind_param('i', $to_free);
                                $stmt_free2->execute();
                                $stmt_free2->close();
                            }
                            log_user_activity("Surat tugas ID: $surat_id selesai; kendaraan ID $to_free set to Tersedia");
                        }
                    }

                    $msg = '<div class="alert alert-success">Surat tugas berhasil diperbarui!</div>';
                    log_user_activity("Memperbarui surat tugas ID: $surat_id");
                    log_surat_tugas_role_activity($current_role, "Memperbarui surat tugas ID: $surat_id");
                    header('Location: ?page=surat_tugas&msg=success&text=' . urlencode('Surat tugas berhasil diperbarui!'));
                    exit;
                } else {
                    $msg = '<div class="alert alert-danger">Error: ' . $stmt->error . '</div>';
                }
                $stmt->close();
            }
        }
    }
}

// Handle delete
if ($action === 'delete' && $can_crud && $surat_id) {
    $stmt = $conn->prepare("DELETE FROM surat_tugas WHERE id = ?");
    $stmt->bind_param('i', $surat_id);
    if ($stmt->execute()) {
        $msg = '<div class="alert alert-success">Surat tugas berhasil dihapus!</div>';
        log_user_activity("Menghapus surat tugas ID: $surat_id");
        log_surat_tugas_role_activity($current_role, "Menghapus surat tugas ID: $surat_id");
    } else {
        $msg = '<div class="alert alert-danger">Error: ' . $stmt->error . '</div>';
    }
    $stmt->close();
    $action = 'list';
}

// Get surat data for edit
$surat_data = null;
if ($action === 'edit' && $surat_id && $can_crud) {
    $stmt = $conn->prepare("SELECT * FROM surat_tugas WHERE id = ?");
    $stmt->bind_param('i', $surat_id);
    $stmt->execute();
    $result = $stmt->get_result();
    $surat_data = $result->fetch_assoc();
    $stmt->close();
}

// Get vehicles and users for dropdown
$vehicles = [];
// Detect optional `pengguna_id` column on `kendaraan` so we can join driver data when present
$col_check = $conn->query("SHOW COLUMNS FROM kendaraan LIKE 'pengguna_id'");
$has_pengguna_id = $col_check && $col_check->num_rows > 0;
if ($has_pengguna_id) {
    $vehicles_query = "SELECT k.id, k.no_polisi, k.no_reg, k.merk, k.tipe, k.pengguna_id, COALESCE(p.nama_lengkap, '') AS driver_name, COALESCE(p.pangkat, '') AS driver_pangkat FROM kendaraan k LEFT JOIN pengguna p ON k.pengguna_id = p.id ORDER BY COALESCE(k.no_reg, k.no_polisi)";
} else {
    $vehicles_query = "SELECT id, no_polisi, no_reg, merk, tipe FROM kendaraan ORDER BY COALESCE(no_reg, no_polisi)";
}
$vehicles_result = $conn->query($vehicles_query);
if ($vehicles_result) {
    $vehicles = $vehicles_result->fetch_all(MYSQLI_ASSOC);
}

$users = [];
// Fetch drivers by joining user_account and role (some schemas store role outside pengguna)
$users_query = "SELECT p.id, p.nama_lengkap, p.pangkat, p.nrp_nip
    FROM pengguna p
    JOIN user_account ua ON p.id = ua.pengguna_id
    JOIN role r ON ua.role_id = r.id
    WHERE (ua.status = 'Aktif' OR ua.status = 'aktif') AND UPPER(COALESCE(r.kode_role, '')) = 'DRIVER'";
if ($is_user && $current_user_id) {
    $users_query .= " AND p.id = " . (int)$current_user_id;
}
$users_query .= " ORDER BY p.nama_lengkap";
$users_result = $conn->query($users_query);
if ($users_result) {
    $users = $users_result->fetch_all(MYSQLI_ASSOC);
}
?>

<div class="page-header gradient-header rounded">
            <h1><i class="fas fa-file-signature me-2"></i>Surat Tugas</h1>
            <div class="col">
            <p class="mb-0">Kelola surat tugas kendaraan dinas</p>
            </div>
            <div class="header-actions">
        <?php if ($can_submit): ?>
            <a href="?page=surat_tugas&action=add" class="btn btn-primary">
                <i class="fas fa-plus me-2"></i>Buat Surat Tugas
            </a>
        <?php endif; ?>
        </div>
</div>

<div class="content">
    <?= $msg ?>

    <?php if ($action === 'list'): ?>
        <!-- Status Filter -->
        <div class="card shadow-sm card-hover mb-3">
            <div class="card-header bg-dark text-white">
                <h5 class="mb-0"><i class="fas fa-filter me-2"></i>Filter Status</h5>
            </div>
            <div class="card-body">
                <div class="btn-group" role="group">
                    <button type="button" class="btn btn-outline-primary active" onclick="filterStatus('all')">Semua</button>
                    <button type="button" class="btn btn-outline-secondary" onclick="filterStatus('Draft')">Draft</button>
                    <button type="button" class="btn btn-outline-success" onclick="filterStatus('Disetujui')">Disetujui</button>
                    <button type="button" class="btn btn-outline-warning" onclick="filterStatus('Dalam Perjalanan')">Dalam Perjalanan</button>
                    <button type="button" class="btn btn-outline-info" onclick="filterStatus('Selesai')">Selesai</button>
                    <button type="button" class="btn btn-outline-danger" onclick="filterStatus('Dibatalkan')">Dibatalkan</button>
                </div>
            </div>
        </div>

        <!-- List View -->
        <div class="card shadow-sm card-hover">
            <div class="card-header bg-dark text-white">
                <h5 class="mb-0"><i class="fas fa-list me-2"></i>Daftar Surat Tugas</h5>
            </div>
            <div class="card-body p-0">
                <div class="table-responsive">
                    <?php
                    // Per-page selection (10/20/30)
                    $per_page = (int)($_GET['per_page'] ?? 10);
                    if (!in_array($per_page, [10,20,30])) $per_page = 10;
                    $p = max(1, (int)($_GET['p'] ?? 1));

                    // Status filter (server-side) - default to 'all'
                    $status_filter = $_GET['status'] ?? 'all';
                    $status_filter = $status_filter === '' ? 'all' : $status_filter;

                    // Build WHERE clause for filtering
                    $where_clauses = [];
                    if ($is_user && $current_user_id) {
                        $uid = (int)$current_user_id;
                        // Build access checks: creator, assigned pengguna, vehicle assigned pengguna, or approved peminjaman for this kendaraan
                        $access_parts = [];
                        if ($has_created_by_col) $access_parts[] = "s.created_by = {$uid}";
                        $access_parts[] = "s.pengguna_id = {$uid}";
                        // allow when kendaraan.pengguna_id equals current user (vehicle assigned to user)
                        $access_parts[] = "COALESCE(k.pengguna_id, 0) = {$uid}";

                        // If peminjaman_kendaraan table exists, detect applicant column and allow when user has an approved/ongoing peminjaman for this kendaraan
                        $res_pk = $conn->query("SHOW TABLES LIKE 'peminjaman_kendaraan'");
                        if ($res_pk && $res_pk->num_rows > 0) {
                            $cols_now = [];
                            $cols_q = $conn->query("SHOW COLUMNS FROM peminjaman_kendaraan");
                            if ($cols_q) $cols_now = array_column($cols_q->fetch_all(MYSQLI_ASSOC), 'Field');
                            $appCol = null;
                            foreach (['peminjam_id','pemohon_id','pengguna_id','user_id','created_by'] as $c) { if (in_array($c, $cols_now, true)) { $appCol = $c; break; } }
                            if ($appCol) {
                                if ($appCol === 'user_id') {
                                    $acct = (int)(function_exists('get_current_account_id') ? get_current_account_id() : 0);
                                    $access_parts[] = "EXISTS (SELECT 1 FROM peminjaman_kendaraan pk WHERE pk.kendaraan_id = s.kendaraan_id AND pk.user_id = {$acct} AND pk.status IN ('Approved','approved','Ongoing','ongoing'))";
                                } else {
                                    $access_parts[] = "EXISTS (SELECT 1 FROM peminjaman_kendaraan pk WHERE pk.kendaraan_id = s.kendaraan_id AND pk.{$appCol} = {$uid} AND pk.status IN ('Approved','approved','Ongoing','ongoing'))";
                                }
                            }
                        }

                        $where_clauses[] = '(' . implode(' OR ', $access_parts) . ')';

                        // Require pimpinan approval when column exists
                        if ($has_approval_pimpinan_col) {
                            $where_clauses[] = "s.approval_pimpinan_status = 'Approved'";
                        }
                    }
                    if ($status_filter !== 'all') {
                        $sf = $conn->real_escape_string($status_filter);
                        $where_clauses[] = "s.status = '" . $sf . "'";
                    }
                    $where_sql = $where_clauses ? 'WHERE ' . implode(' AND ', $where_clauses) : '';

                    // total count (respecting filters)
                    // include kendaraan join when k.* is referenced in where clauses
                    $count_sql_from = "FROM surat_tugas s";
                    if (stripos($where_sql, 'k.pengguna_id') !== false || stripos($where_sql, 's.kendaraan_id') !== false) {
                        $count_sql_from = "FROM surat_tugas s LEFT JOIN kendaraan k ON s.kendaraan_id = k.id LEFT JOIN pengguna p ON s.pengguna_id = p.id";
                    }
                    $countRes = $conn->query("SELECT COUNT(*) as total " . $count_sql_from . " " . $where_sql);
                    $total = 0;
                    if ($countRes) {
                        $rtmp = $countRes->fetch_assoc();
                        $total = (int)$rtmp['total'];
                    }
                    $total_pages = max(1, (int)ceil($total / $per_page));
                    if ($p > $total_pages) $p = $total_pages;
                    $offset = ($p - 1) * $per_page;
                    ?>

                    <div class="d-flex justify-content-between align-items-center p-2">
                        <div class="d-flex align-items-center">
                                <?php if ($can_crud): ?>
                                <div class="me-2">
                                    <input type="checkbox" id="select_all" title="Pilih semua di halaman ini"> <label for="select_all" class="small mb-0 ms-1">Pilih semua</label>
                                </div>
                                <div>
                                    <button type="button" id="deleteSelectedBtn" class="btn btn-danger btn-sm" disabled>
                                        <i class="fas fa-trash"></i> Hapus Terpilih
                                    </button>
                                </div>
                            <?php endif; ?>
                        </div>
                        <div>
                            <small class="text-muted">Menampilkan <?= $offset + 1 ?> - <?= min($offset + $per_page, $total) ?> dari <?= $total ?> entri</small>
                        </div>
                    </div>

                    <table class="table table-hover mb-0" id="suratTable">
                            <thead class="table-light">
                                <tr>
                                    <th class="sortable" data-type="num">No <span class="sort-indicator"></span></th>
                                    <th class="sortable" data-type="text">Nomor Surat <span class="sort-indicator"></span></th>
                                    <th class="sortable" data-type="text">Kendaraan <span class="sort-indicator"></span></th>
                                    <th class="sortable" data-type="text">Penanggung Jawab <span class="sort-indicator"></span></th>
                                    <th class="sortable" data-type="text">Tujuan <span class="sort-indicator"></span></th>
                                    <th class="sortable" data-type="date">Tanggal Berangkat <span class="sort-indicator"></span></th>
                                    <th class="sortable" data-type="text">Status <span class="sort-indicator"></span></th>
                                    <th class="sortable" data-type="text">Proses Persetujuan <span class="sort-indicator"></span></th>
                                    <?php if ($can_view): ?>
                                        <th class="text-center">Aksi</th>
                                    <?php endif; ?>
                                </tr>
                            </thead>
                            <tbody>
                                <?php
                                $query = "SELECT s.*, k.no_polisi, k.no_reg, k.merk, k.tipe, p.nama_lengkap, p.pangkat
                                         FROM surat_tugas s 
                                         LEFT JOIN kendaraan k ON s.kendaraan_id = k.id 
                                         LEFT JOIN pengguna p ON s.pengguna_id = p.id
                                         $where_sql
                                         ORDER BY s.tanggal_surat DESC
                                         LIMIT " . $per_page . " OFFSET " . $offset;
                                $result = $conn->query($query);
                                $no = $offset + 1;

                                if ($result && $result->num_rows > 0):
                                    while ($row = $result->fetch_assoc()):
                                        $status_class = [
                                            'Draft' => 'secondary',
                                            'Disetujui' => 'success',
                                            'Dalam Perjalanan' => 'warning',
                                            'Selesai' => 'info',
                                            'Dibatalkan' => 'danger'
                                        ][$row['status']] ?? 'secondary';
                                ?>
                                <tr data-status="<?= htmlspecialchars($row['status']) ?>">
                                    <td>
                                        <?php if ($can_crud): ?>
                                            <input type="checkbox" class="select_row" data-id="<?= (int)$row['id'] ?>" aria-label="Pilih baris">
                                        <?php endif; ?>
                                        <?= $no++ ?>
                                    </td>
                                    <td>
                                        <strong><?= htmlspecialchars($row['nomor_surat']) ?></strong><br>
                                        <small class="text-muted"><?= date('d/m/Y', strtotime($row['tanggal_surat'])) ?></small>
                                    </td>
                                        <td>
                                        <?php
                                            $no_reg = isset($row['no_reg']) ? trim($row['no_reg']) : '';
                                            $merk = isset($row['merk']) ? trim($row['merk']) : '';
                                            $kend_label = $no_reg;
                                            if ($merk !== '') {
                                                $kend_label .= ($kend_label ? ' - ' : '') . $merk;
                                            }
                                        ?>
                                        <strong><?= htmlspecialchars($kend_label) ?></strong>
                                    </td>
                                    <td>
                                        <strong><?= htmlspecialchars(($row['pangkat'] ? $row['pangkat'] . ' ' : '') . $row['nama_lengkap']) ?></strong>
                                    </td>
                                    <td>
                                        <strong><?= htmlspecialchars($row['tujuan']) ?></strong><br>
                                        <small class="text-muted"><?= htmlspecialchars(substr($row['keperluan'], 0, 50)) ?><?= strlen($row['keperluan']) > 50 ? '...' : '' ?></small>
                                    </td>
                                    <td>
                                        <?= date('d/m/Y', strtotime($row['tanggal_berangkat'])) ?>
                                        <?php if ($row['tanggal_kembali']): ?>
                                            <br><small class="text-muted">s/d <?= date('d/m/Y', strtotime($row['tanggal_kembali'])) ?></small>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <span class="badge bg-<?= $status_class ?> text-white"><?= htmlspecialchars($row['status']) ?></span>
                                    </td>
                                    <td>
                                        <?php
                                            $approval_raw = strtolower(trim((string)($row['approval_pimpinan_status'] ?? 'pending')));
                                            $approval_badge = 'secondary';
                                            $approval_label = 'Menunggu Persetujuan';
                                            if ($approval_raw === 'approved') {
                                                $approval_badge = 'success';
                                                $approval_label = 'Disetujui Pimpinan';
                                            } elseif ($approval_raw === 'rejected') {
                                                $approval_badge = 'danger';
                                                $approval_label = 'Ditolak Pimpinan';
                                            }
                                        ?>
                                        <span class="badge bg-<?= $approval_badge ?> text-white"><?= htmlspecialchars($approval_label) ?></span>
                                    </td>
                                    <?php if ($can_view): ?>
                                    <td class="text-center">
                                        <div class="btn-group" role="group">
                                            <a href="?page=surat_tugas&action=view&id=<?= $row['id'] ?>" 
                                               class="btn btn-sm btn-outline-info" title="Lihat Detail">
                                                <i class="fas fa-eye"></i>
                                            </a>
                                            <?php if ($can_crud): ?>
                                            <a href="?page=surat_tugas&action=edit&id=<?= $row['id'] ?>" 
                                               class="btn btn-sm btn-outline-primary" title="Edit">
                                                <i class="fas fa-edit"></i>
                                            </a>
                                            <a href="?page=surat_tugas&action=delete&id=<?= $row['id'] ?>" 
                                               class="btn btn-sm btn-outline-danger" title="Hapus"
                                               onclick="return confirm('Yakin ingin menghapus surat tugas ini?')">
                                                <i class="fas fa-trash"></i>
                                            </a>
                                            <?php endif; ?>
                                        </div>
                                    </td>
                                    <?php endif; ?>
                                </tr>
                                <?php 
                                    endwhile;
                                else: 
                                ?>
                                <tr>
                                    <td colspan="<?= $can_view ? '9' : '8' ?>" class="text-center text-muted py-4">
                                        <i class="fas fa-file-signature fa-3x mb-3 text-muted"></i><br>
                                        Belum ada data surat tugas
                                    </td>
                                </tr>
                                <?php endif; ?>
                            </tbody>
                        </table>

                        <?php // hidden bulk delete POST form ?>
                        <form method="POST" id="bulkDeleteForm" style="display:none;">
                            <input type="hidden" name="csrf_token" value="<?= generate_csrf_token() ?>">
                        </form>

                        <?php // pagination controls ?>
                        <?php if ($total_pages > 1): ?>
                        <div class="d-flex justify-content-between align-items-center p-2">
                            <div>
                                <!-- Per-page selector (moved below table) -->
                                <form method="get" id="perPageForm" class="per-page-form">
                                    <input type="hidden" name="page" value="surat_tugas">
                                    <input type="hidden" name="p" value="1">
                                    <?php
                                    // Preserve existing GET parameters except per_page and p
                                    foreach ($_GET as $gk => $gv) {
                                        if (in_array($gk, ['per_page','p'])) continue;
                                        // sanitize name and value
                                        $gk_esc = htmlspecialchars($gk, ENT_QUOTES);
                                        $gv_esc = htmlspecialchars($gv, ENT_QUOTES);
                                        echo "<input type=\"hidden\" name=\"{$gk_esc}\" value=\"{$gv_esc}\">\n";
                                    }
                                    ?>
                                    <label for="per_page" class="visually-hidden">Jumlah per halaman</label>
                                    <select name="per_page" id="per_page" class="form-select form-select-sm d-inline-block" style="width:auto; display:inline-block;" onchange="this.form.submit()">
                                        <?php foreach ([10,20,30] as $pp): ?>
                                            <option value="<?= $pp ?>" <?= $per_page == $pp ? 'selected' : '' ?>><?= $pp ?></option>
                                        <?php endforeach; ?>
                                    </select>
                                </form>
                            </div>

                            <div>
                                <!-- Simple numeric page links -->
                                <nav>
                                    <ul class="pagination pagination-sm mb-0">
                                        <?php
                                        $base_params = $_GET;
                                        $base_params['page'] = 'surat_tugas';
                                        $base_params['per_page'] = $per_page;
                                        for ($i = 1; $i <= $total_pages; $i++):
                                            $base_params['p'] = $i;
                                            $url = '?' . http_build_query($base_params);
                                        ?>
                                        <li class="page-item <?= $p == $i ? 'active' : '' ?>"><a class="page-link" href="<?= $url ?>"><?= $i ?></a></li>
                                        <?php endfor; ?>
                                    </ul>
                                </nav>
                            </div>

                            <div>
                                <?php
                                $prev_p = max(1, $p - 1);
                                $next_p = min($total_pages, $p + 1);
                                $prev_params = $_GET; $prev_params['page'] = 'surat_tugas'; $prev_params['per_page'] = $per_page; $prev_params['p'] = $prev_p;
                                $next_params = $_GET; $next_params['page'] = 'surat_tugas'; $next_params['per_page'] = $per_page; $next_params['p'] = $next_p;
                                $prev_url = '?' . http_build_query($prev_params);
                                $next_url = '?' . http_build_query($next_params);
                                ?>
                                <div class="btn-group ms-2" role="group">
                                    <a href="<?= $prev_url ?>" class="btn btn-sm btn-outline-secondary <?= $p == 1 ? 'disabled' : '' ?>">Prev</a>
                                    <a href="<?= $next_url ?>" class="btn btn-sm btn-outline-primary <?= $p == $total_pages ? 'disabled' : '' ?>">Next</a>
                                </div>
                            </div>
                        </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>

        <?php elseif ($action === 'view' && $surat_id): ?>
            <!-- View/Preview Surat Tugas -->
            <?php 
            // Build SELECT with optional kesatuan join only if pengguna.kesatuan_id exists
            $select = "s.*, k.no_polisi, k.no_reg, k.merk, k.tipe, p.nama_lengkap, p.pangkat, p.nrp_nip";
            $joins = " LEFT JOIN kendaraan k ON s.kendaraan_id = k.id LEFT JOIN pengguna p ON s.pengguna_id = p.id";
            // Check if pengguna.kesatuan_id column exists to avoid SQL errors
            $has_kesatuan = false;
            try {
                $colCheck = $conn->query("SHOW COLUMNS FROM `pengguna` LIKE 'kesatuan_id'");
                if ($colCheck && $colCheck->num_rows > 0) {
                    $has_kesatuan = true;
                }
                if ($colCheck) $colCheck->free_result();
            } catch (mysqli_sql_exception $e) {
                $has_kesatuan = false;
            }
            if ($has_kesatuan) {
                $select .= ", ks.nama_kesatuan";
                $joins .= " LEFT JOIN kesatuan ks ON p.kesatuan_id = ks.id";
            } else {
                // Provide empty alias so templates can reference nama_kesatuan safely
                $select .= ", '' as nama_kesatuan";
            }

            $sql = "SELECT " . $select . " FROM surat_tugas s " . $joins . " WHERE s.id = ?";
            $stmt = $conn->prepare($sql);
            $stmt->bind_param('i', $surat_id);
            $stmt->execute();
            $result = $stmt->get_result();
            $surat = $result->fetch_assoc();
            $stmt->close();
            
            if (!$surat) {
                echo '<div class="alert alert-danger">Surat tugas tidak ditemukan!</div>';
            } else {
                log_surat_tugas_role_activity($current_role, 'Melihat detail surat tugas: ' . ($surat['nomor_surat'] ?? ('ID ' . (int)$surat_id)));
            ?>
            <div class="row">
                <div class="col-md-8 offset-md-2">
                    <div class="card">
                        <div class="card-header d-flex justify-content-between align-items-center">
                            <h4 class="mb-0">Preview Surat Tugas</h4>
                            <div>
                                <button type="button" class="btn btn-danger btn-sm" onclick="downloadPDF(<?= $surat_id ?>)">
                                    <i class="fas fa-file-pdf"></i> Download PDF
                                </button>
                                <a href="?page=surat_tugas" class="btn btn-secondary btn-sm">
                                    <i class="fas fa-arrow-left"></i> Kembali
                                </a>
                            </div>
                        </div>
                        <div class="card-body" id="printArea">
                            <!-- Header Surat -->
                            <div class="text-center mb-4">
                                <h3 class="mb-1"><?= htmlspecialchars(strtoupper($surat['nama_unit'] ?? 'BIRO UMUM SETJEN KEMHAN')) ?></h3>
                                <h4 class="mb-1"><?= htmlspecialchars(strtoupper($surat['nama_bagian'] ?? 'BAGIAN PENGAMANAN')) ?></h4>
                                <h4 class="mt-3 mb-1"><?= htmlspecialchars(strtoupper($surat['jenis_naskah'] ?? 'NOTA DINAS')) ?></h4>
                            </div>

                            <!-- Judul Surat -->
                            <div class="mb-4">
                                <table style="width:100%;">
                                    <tr>
                                        <td style="width:90px;">Nomor</td>
                                        <td style="width:20px;">:</td>
                                        <td><?= htmlspecialchars($surat['nomor_surat']) ?></td>
                                    </tr>
                                    <tr>
                                        <td>Kepada</td>
                                        <td>:</td>
                                        <td>Yth. <?= htmlspecialchars($surat['kepada_jabatan']) ?></td>
                                    </tr>
                                    <tr>
                                        <td>Dari</td>
                                        <td>:</td>
                                        <td><?= htmlspecialchars($surat['surat_dari'] ?? '-') ?></td>
                                    </tr>
                                    <tr>
                                        <td>Hal</td>
                                        <td>:</td>
                                        <td><?= htmlspecialchars($surat['perihal']) ?></td>
                                    </tr>
                                </table>
                            </div>

                            <!-- Isi Surat -->
                            <div class="mb-4">
                                <p><strong>1. Dasar:</strong></p>
                                <ol type="a" class="mb-3">
                                    <?php
                                    $dasar_items_preview = [];
                                    foreach ([$surat['dasar_a'] ?? '', $surat['dasar_b'] ?? ''] as $dsrc) {
                                        foreach (preg_split('/\r?\n|;/', (string)$dsrc) as $part) {
                                            $part = trim($part);
                                            if ($part !== '') {
                                                $dasar_items_preview[] = $part;
                                            }
                                        }
                                    }
                                    if (empty($dasar_items_preview)) {
                                        $dasar_items_preview[] = '-';
                                    }
                                    foreach ($dasar_items_preview as $di):
                                    ?>
                                        <li><?= htmlspecialchars($di) ?></li>
                                    <?php endforeach; ?>
                                </ol>

                                <p><strong>2.</strong> Sehubungan dasar di atas, dengan hormat diajukan permohonan dukungan sebagai berikut:</p>
                                <div style="padding-left:20px;"><?= nl2br(htmlspecialchars($surat['keperluan'])) ?></div>

                                <div class="mt-3">
                                    <table style="width:100%;">
                                        <tr>
                                            <td style="width:180px;">Tujuan</td>
                                            <td style="width:20px;">:</td>
                                            <td><?= htmlspecialchars($surat['tujuan']) ?></td>
                                        </tr>
                                        <tr>
                                            <td>Tanggal Pelaksanaan</td>
                                            <td>:</td>
                                            <td>
                                                <?= date('d F Y', strtotime($surat['tanggal_berangkat'])) ?>
                                                <?php if (!empty($surat['tanggal_kembali'])): ?>
                                                    s.d. <?= date('d F Y', strtotime($surat['tanggal_kembali'])) ?>
                                                <?php endif; ?>
                                            </td>
                                        </tr>
                                        <tr>
                                            <td>Waktu</td>
                                            <td>:</td>
                                            <td><?= htmlspecialchars($surat['waktu_berangkat']) ?></td>
                                        </tr>
                                        <tr>
                                            <td>Kendaraan</td>
                                            <td>:</td>
                                            <td><?= htmlspecialchars((($surat['no_reg'] ?? '') !== '' ? $surat['no_reg'] : ($surat['no_polisi'] ?? '-')) . ' - ' . ($surat['merk'] ?? '') . ' ' . ($surat['tipe'] ?? '')) ?></td>
                                        </tr>
                                    </table>
                                </div>

                                <p class="mt-3"><strong>3.</strong> Demikian mohon menjadikan periksa.</p>
                            </div>

                            <!-- Status dan Laporan -->
                            <?php if ($surat['status'] !== 'Draft'): ?>
                            <div class="mb-4 p-3 bg-light rounded">
                                <h6>Status Perjalanan</h6>
                                <p><strong>Status:</strong> 
                                    <span class="badge badge-<?= ['Draft' => 'secondary', 'Disetujui' => 'success', 'Dalam Perjalanan' => 'warning', 'Selesai' => 'info', 'Dibatalkan' => 'danger'][$surat['status']] ?>">
                                        <?= htmlspecialchars($surat['status']) ?>
                                    </span>
                                </p>
                                
                                <?php if ($surat['km_berangkat'] || $surat['km_kembali'] || $surat['bbm_terpakai']): ?>
                                <table style="width: 100%;">
                                    <?php if ($surat['km_berangkat']): ?>
                                    <tr>
                                        <td style="width: 150px;">KM Berangkat</td>
                                        <td style="width: 20px;">:</td>
                                        <td><?= number_format($surat['km_berangkat']) ?> KM</td>
                                    </tr>
                                    <?php endif; ?>
                                    <?php if ($surat['km_kembali']): ?>
                                    <tr>
                                        <td>KM Kembali</td>
                                        <td>:</td>
                                        <td><?= number_format($surat['km_kembali']) ?> KM</td>
                                    </tr>
                                    <tr>
                                        <td>Total KM</td>
                                        <td>:</td>
                                        <td><strong><?= number_format($surat['km_kembali'] - $surat['km_berangkat']) ?> KM</strong></td>
                                    </tr>
                                    <?php endif; ?>
                                    <?php if ($surat['bbm_terpakai']): ?>
                                    <tr>
                                        <td>BBM Terpakai</td>
                                        <td>:</td>
                                        <td><?= number_format($surat['bbm_terpakai'], 2) ?> Liter</td>
                                    </tr>
                                    <?php endif; ?>
                                </table>
                                <?php endif; ?>

                                <?php if ($surat['laporan_perjalanan']): ?>
                                <div class="mt-3">
                                    <h6>Laporan Perjalanan</h6>
                                    <p><?= nl2br(htmlspecialchars($surat['laporan_perjalanan'])) ?></p>
                                </div>
                                <?php endif; ?>
                            </div>
                            <?php endif; ?>

                            <!-- Tanda Tangan -->
                            <div class="row mt-5">
                                <div class="col-md-6"></div>
                                <div class="col-md-6 text-center">
                                    <p class="mb-1">Jakarta, <?= date('d F Y', strtotime($surat['tanggal_surat'])) ?></p>
                                    <p class="mb-1"><?= htmlspecialchars($surat['pejabat_ttd_jabatan'] ?? ('Plh. Kepala ' . ($surat['nama_bagian'] ?? 'Bagian Pengamanan'))) ?></p>
                                    <p class="mb-1"><?= htmlspecialchars($surat['pejabat_ttd_sebagai'] ?? '') ?></p>
                                    <div style="height: 80px;"></div>
                                    <p class="mb-0"><strong><?= htmlspecialchars($surat['pejabat_ttd'] ?: 'LAKSDA TNI ARIANTYO CONDROWIBOWO') ?></strong></p>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            <?php } ?>

        <?php elseif ($action === 'download_pdf' && $surat_id): ?>
            <?php 
            // Generate PDF menggunakan TCPDF
            require_once 'lib/SuratTugasPDF.php';
            
            // Build SELECT with optional kesatuan join only if pengguna.kesatuan_id exists
            $select = "s.*, k.no_polisi, k.merk, k.tipe, p.nama_lengkap, p.pangkat, p.nrp_nip";
            $joins = " LEFT JOIN kendaraan k ON s.kendaraan_id = k.id LEFT JOIN pengguna p ON s.pengguna_id = p.id";
            $has_kesatuan = false;
            try {
                $colCheck = $conn->query("SHOW COLUMNS FROM `pengguna` LIKE 'kesatuan_id'");
                if ($colCheck && $colCheck->num_rows > 0) {
                    $has_kesatuan = true;
                }
                if ($colCheck) $colCheck->free_result();
            } catch (mysqli_sql_exception $e) {
                $has_kesatuan = false;
            }
            if ($has_kesatuan) {
                $select .= ", ks.nama_kesatuan";
                $joins .= " LEFT JOIN kesatuan ks ON p.kesatuan_id = ks.id";
            } else {
                $select .= ", '' as nama_kesatuan";
            }

            $sql = "SELECT " . $select . " FROM surat_tugas s " . $joins . " WHERE s.id = ?";
            $stmt = $conn->prepare($sql);
            $stmt->bind_param('i', $surat_id);
            $stmt->execute();
            $result = $stmt->get_result();
            $surat = $result->fetch_assoc();
            $stmt->close();
            
            if (!$surat) {
                echo '<div class="alert alert-danger">Surat tugas tidak ditemukan!</div>';
            } else {
                log_surat_tugas_role_activity($current_role, 'Mengunduh PDF surat tugas: ' . ($surat['nomor_surat'] ?? ('ID ' . (int)$surat_id)));
                // Create new PDF document
                $pdf = new SuratTugasPDF(PDF_PAGE_ORIENTATION, PDF_UNIT, PDF_PAGE_FORMAT, true, 'UTF-8', false);
                
                // Set document information
                $pdf->SetCreator(PDF_CREATOR);
                $pdf->SetAuthor('SI-KENDI - Sistem Manajemen Kendaraan Dinas');
                $pdf->SetTitle('Surat Tugas - ' . $surat['nomor_surat']);
                $pdf->SetSubject('Surat Tugas Kendaraan Dinas');
                
                // Set default header data
                $pdf->SetHeaderData('', 0, '', '');
                
                // Set header and footer fonts
                $pdf->setHeaderFont(Array(PDF_FONT_NAME_MAIN, '', PDF_FONT_SIZE_MAIN));
                $pdf->setFooterFont(Array(PDF_FONT_NAME_DATA, '', PDF_FONT_SIZE_DATA));
                
                // Set default monospaced font
                $pdf->SetDefaultMonospacedFont(PDF_FONT_MONOSPACED);
                
                // Set margins
                $pdf->SetMargins(PDF_MARGIN_LEFT, PDF_MARGIN_TOP, PDF_MARGIN_RIGHT);
                $pdf->SetHeaderMargin(PDF_MARGIN_HEADER);
                $pdf->SetFooterMargin(PDF_MARGIN_FOOTER);
                
                // Set auto page breaks
                $pdf->SetAutoPageBreak(TRUE, PDF_MARGIN_BOTTOM);
                
                // Generate the PDF content
                $pdf->generateSuratTugas($surat);
                
                // Close and output PDF document
                $filename = 'Surat_Tugas_' . str_replace('/', '_', $surat['nomor_surat']) . '.pdf';
                
                // Clean output buffer
                if (ob_get_level()) {
                    ob_end_clean();
                }
                
                $pdf->Output($filename, 'D'); // 'D' for download
                exit;
            }
            ?>

        <?php elseif (($action === 'add' && $can_submit) || ($action === 'edit' && $can_crud)): ?>
            <!-- Add/Edit Form -->
            <div class="card">
                <div class="card-header">
                    <h4 class="card-title">
                        <?= $action === 'add' ? 'Buat' : 'Edit' ?> Surat Tugas
                    </h4>
                </div>
                <div class="card-body">
                    <form method="POST" enctype="multipart/form-data">
                        <!-- Informasi Kop Surat -->
                        <div class="card mb-4">
                            <div class="card-header bg-primary text-white">
                                <h5 class="mb-0"><i class="fas fa-file-alt"></i> Informasi Kop Surat</h5>
                            </div>
                            <div class="card-body">
                                <div class="row">
                                    <div class="col-md-6">
                                        <div class="form-group">
                                            <label for="nomor_surat">Nomor Surat *</label>
                                              <input type="text" name="nomor_surat" id="nomor_surat" 
                                                  class="form-control"
                                                  value="<?= isset($surat_data) ? htmlspecialchars($surat_data['nomor_surat']) : '' ?>"
                                                  placeholder="Contoh: ST/001/VIII/2025">
                                        </div>
                                    </div>
                                    <div class="col-md-6">
                                        <div class="form-group">
                                            <label for="tanggal_surat">Tanggal Surat *</label>
                          <input type="date" name="tanggal_surat" id="tanggal_surat" 
                              class="form-control" required readonly
                              value="<?= isset($surat_data) ? $surat_data['tanggal_surat'] : date('Y-m-d') ?>">
                                        </div>
                                    </div>
                                </div>

                                <div class="row">
                                    <div class="col-md-6">
                                        <div class="form-group">
                                            <label for="nama_unit">Unit/Kop Atas *</label>
                                            <input type="text" name="nama_unit" id="nama_unit"
                                                   class="form-control" required
                                                   value="<?= htmlspecialchars($surat_data['nama_unit'] ?? 'BIRO UMUM SETJEN KEMHAN') ?>"
                                                   placeholder="Contoh: BIRO UMUM SETJEN KEMHAN">
                                        </div>
                                    </div>
                                    <div class="col-md-6">
                                        <div class="form-group">
                                            <label for="nama_bagian">Bagian *</label>
                                            <input type="text" name="nama_bagian" id="nama_bagian"
                                                   class="form-control" required
                                                   value="<?= htmlspecialchars($surat_data['nama_bagian'] ?? 'BAGIAN PENGAMANAN') ?>"
                                                   placeholder="Contoh: BAGIAN PENGAMANAN">
                                        </div>
                                    </div>
                                </div>

                                <div class="row">
                                    <div class="col-md-4">
                                        <div class="form-group">
                                            <label for="klasifikasi">Klasifikasi</label>
                                            <select name="klasifikasi" id="klasifikasi" class="form-control">
                                                <option value="Biasa" <?= (isset($surat_data) && $surat_data['klasifikasi'] == 'Biasa') ? 'selected' : '' ?>>Biasa</option>
                                                <option value="Rahasia" <?= (isset($surat_data) && $surat_data['klasifikasi'] == 'Rahasia') ? 'selected' : '' ?>>Rahasia</option>
                                                <option value="Sangat Rahasia" <?= (isset($surat_data) && $surat_data['klasifikasi'] == 'Sangat Rahasia') ? 'selected' : '' ?>>Sangat Rahasia</option>
                                            </select>
                                        </div>
                                    </div>
                                    <div class="col-md-4">
                                        <div class="form-group">
                                            <label for="lampiran">Lampiran (file)</label>
                                            <?php if (isset($surat_data) && !empty($surat_data['lampiran']) && $surat_data['lampiran'] !== '-'): ?>
                                                <div class="mb-2">
                                                    <a href="uploads/surat_lampiran/<?= htmlspecialchars($surat_data['lampiran']) ?>" target="_blank">Lihat lampiran saat ini</a>
                                                </div>
                                            <?php endif; ?>
                                            <input type="file" name="lampiran" id="lampiran" class="form-control">
                                            <input type="hidden" name="existing_lampiran" value="<?= isset($surat_data) ? htmlspecialchars($surat_data['lampiran']) : '-' ?>">
                                        </div>
                                    </div>
                                    <div class="col-md-4">
                                        <div class="form-group">
                                            <label for="jenis_naskah">Jenis Naskah *</label>
                                            <input type="text" name="jenis_naskah" id="jenis_naskah"
                                                   class="form-control" required
                                                   value="<?= htmlspecialchars($surat_data['jenis_naskah'] ?? 'NOTA DINAS') ?>"
                                                   placeholder="Contoh: NOTA DINAS">
                                        </div>
                                    </div>
                                    <div class="col-md-4">
                                        <div class="form-group">
                                            <label for="perihal">Perihal *</label>
                          <input type="text" name="perihal" id="perihal" 
                              class="form-control" required
                              value="<?= htmlspecialchars($surat_data['perihal'] ?? 'Permohonan peminjaman kendaraan dinas bus dan tenaga medis') ?>"
                              placeholder="Perihal surat">
                                        </div>
                                    </div>
                                </div>

                                <div class="row">
                                    <div class="col-md-6">
                                        <div class="form-group">
                                            <label for="kepada_jabatan">Kepada (Jabatan) *</label>
                                            <input type="text" name="kepada_jabatan" id="kepada_jabatan" 
                                                   class="form-control" required
                                                   value="<?= htmlspecialchars($surat_data['kepada_jabatan'] ?? 'Dandenma Mabes TNI') ?>"
                                                   placeholder="Contoh: Dandenma Mabes TNI">
                                        </div>
                                    </div>
                                    <div class="col-md-6">
                                        <div class="form-group">
                                            <label for="surat_dari">Dari *</label>
                                            <input type="text" name="surat_dari" id="surat_dari"
                                                   class="form-control" required
                                                   value="<?= htmlspecialchars($surat_data['surat_dari'] ?? 'Kabag Pam Roum Setjen Kemhan') ?>"
                                                   placeholder="Contoh: Kabag Pam Roum Setjen Kemhan">
                                        </div>
                                    </div>
                                    <div class="col-md-6">
                                        <div class="form-group">
                                            <label for="kepada_tempat">Kepada (Tempat) *</label>
                                            <input type="text" name="kepada_tempat" id="kepada_tempat" 
                                                   class="form-control" required
                                                   value="<?= htmlspecialchars($surat_data['kepada_tempat'] ?? 'Jakarta') ?>"
                                                   placeholder="Contoh: Jakarta">
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- Dasar Surat (dynamic list) -->
                        <div class="card mb-4">
                            <div class="card-header bg-info text-white">
                                <h5 class="mb-0"><i class="fas fa-list"></i> Dasar Surat</h5>
                            </div>
                            <div class="card-body">
                                <?php
                                // Prepare dasar items from existing data
                                $dasar_items = [];
                                if (isset($surat_data)) {
                                    if (!empty($surat_data['dasar_a'])) {
                                        $parts = preg_split('/\r?\n|;/', $surat_data['dasar_a']);
                                        $dasar_items = array_merge($dasar_items, $parts);
                                    }
                                    if (!empty($surat_data['dasar_b'])) {
                                        $parts = preg_split('/\r?\n|;/', $surat_data['dasar_b']);
                                        $dasar_items = array_merge($dasar_items, $parts);
                                    }
                                }
                                $dasar_items = array_map('trim', array_filter($dasar_items));
                                ?>

                                <label>Daftar Dasar</label>
                                <ul id="dasarList" class="list-group mb-2">
                                    <?php if (!empty($dasar_items)): ?>
                                        <?php foreach ($dasar_items as $di): ?>
                                            <li class="list-group-item d-flex align-items-center">
                                                <input type="text" class="form-control me-2 dasar-item" value="<?= htmlspecialchars($di) ?>">
                                                <button type="button" class="btn btn-sm btn-danger remove-dasar">&times;</button>
                                            </li>
                                        <?php endforeach; ?>
                                    <?php else: ?>
                                        <li class="list-group-item d-flex align-items-center">
                                            <input type="text" class="form-control me-2 dasar-item" value="Peraturan Sekretaris Jenderal Kemhan Nomor 4 Tahun 2026 tentang Pengamanan di Lingkungan Kemhan.">
                                            <button type="button" class="btn btn-sm btn-danger remove-dasar">&times;</button>
                                        </li>
                                    <?php endif; ?>
                                </ul>
                                <div class="mb-3">
                                    <button type="button" id="addDasarBtn" class="btn btn-sm btn-outline-primary">Tambah Dasar</button>
                                </div>
                                <!-- Hidden fields expected by server -->
                                <input type="hidden" name="dasar_a" id="dasar_a_hidden" value="<?= isset($surat_data) ? htmlspecialchars($surat_data['dasar_a']) : '' ?>">
                                <input type="hidden" name="dasar_b" id="dasar_b_hidden" value="<?= isset($surat_data) ? htmlspecialchars($surat_data['dasar_b']) : '' ?>">
                            </div>
                        </div>

                        <!-- Detail Perjalanan -->
                        <div class="card mb-4">
                            <div class="card-header bg-warning text-dark">
                                <h5 class="mb-0"><i class="fas fa-route"></i> Detail Perjalanan</h5>
                            </div>
                            <div class="card-body">
                                <div class="row">
                                    <div class="col-md-6">
                                        <div class="form-group">
                                            <label for="kendaraan_id">Kendaraan *</label>
                                            <select name="kendaraan_id" id="kendaraan_id" class="form-control" required>
                                                <option value="">Pilih Kendaraan</option>
                                                <?php foreach ($vehicles as $vehicle): ?>
                                                    <?php
                                                        $reg = '';
                                                        if (!empty($vehicle['no_reg'])) $reg = $vehicle['no_reg'];
                                                        elseif (!empty($vehicle['no_polisi'])) $reg = $vehicle['no_polisi'];
                                                        else $reg = '-';
                                                        $merk = trim(($vehicle['merk'] ?? '') . ' ' . ($vehicle['tipe'] ?? ''));
                                                        $driver_display = '';
                                                        if (!empty($vehicle['driver_name'])) {
                                                            $driver_display = trim(($vehicle['driver_pangkat'] ?? '') . ' ' . $vehicle['driver_name']);
                                                        }
                                                    ?>
                                                    <option value="<?= $vehicle['id'] ?>" data-merk="<?= htmlspecialchars($vehicle['merk'] ?? '') ?>" <?= (isset($surat_data) && $surat_data['kendaraan_id'] == $vehicle['id']) ? 'selected' : '' ?>>
                                                        <?= htmlspecialchars($reg) ?> - <?= htmlspecialchars($merk) ?><?= $driver_display ? ' - ' . htmlspecialchars($driver_display) : '' ?>
                                                    </option>
                                                <?php endforeach; ?>
                                            </select>
                                        </div>
                                    </div>
                                    <div class="col-md-6">
                                        <div class="form-group">
                                            <label for="pengguna_id">Penanggung Jawab *</label>
                                            <?php if ($action === 'edit'): ?>
                                                <!-- Edit mode: Display only, read-only -->
                                                <input type="text" class="form-control" readonly 
                                                       value="<?= htmlspecialchars((isset($surat_data) && !empty($surat_data['pengguna_nama_lengkap'])) 
                                                                   ? (($surat_data['pengguna_pangkat'] ?? '') !== '' ? $surat_data['pengguna_pangkat'] . ' ' : '') . $surat_data['pengguna_nama_lengkap']
                                                                   : '(Tidak tersedia)') ?>">
                                                <input type="hidden" name="pengguna_id" value="<?= isset($surat_data) ? (int)$surat_data['pengguna_id'] : '' ?>">
                                            <?php else: ?>
                                                <!-- Create mode: Editable select (for admin-like) or hidden for users -->
                                                <select name="pengguna_id" id="pengguna_id" class="form-control" <?= $is_user ? 'style="display:none"' : 'required' ?>>
                                                    <option value="">Pilih Pengguna (atau pilih kendaraan terlebih dahulu)</option>
                                                    <?php foreach ($users as $user): ?>
                                                        <option value="<?= $user['id'] ?>" 
                                                                <?= ((isset($surat_data) && $surat_data['pengguna_id'] == $user['id']) || (!isset($surat_data) && $is_user && (int)$current_user_id === (int)$user['id'])) ? 'selected' : '' ?>>
                                                            <?= htmlspecialchars(($user['pangkat'] ? $user['pangkat'] . ' ' : '') . $user['nama_lengkap'] . ' (' . $user['nrp_nip'] . ')') ?>
                                                        </option>
                                                    <?php endforeach; ?>
                                                </select>
                                                <!-- Display selected driver for users -->
                                                <?php if ($is_user): ?>
                                                    <div id="pengguna_display" class="alert alert-info mt-2" style="display:none;">
                                                        Penanggung Jawab: <strong id="pengguna_display_text"></strong>
                                                    </div>
                                                    <input type="hidden" name="pengguna_id" id="pengguna_id_hidden" value="<?= (int)$current_user_id ?>">
                                                <?php endif; ?>
                                            <?php endif; ?>
                                        </div>
                                    </div>
                                </div>

                                <div class="row">
                                    <div class="col-md-6">
                                        <div class="form-group">
                                            <label>Tujuan (bisa lebih dari satu) *</label>
                                            <ul id="destinationList" class="list-group mb-2">
                                                <?php
                                                $dest_items = [];
                                                if (!empty($surat_data['tujuan'])) {
                                                    // If multiple stored separated by '||', split; else single
                                                    if (strpos($surat_data['tujuan'], '||') !== false) {
                                                        $dest_items = array_map('trim', explode('||', $surat_data['tujuan']));
                                                    } else {
                                                        $dest_items = [trim($surat_data['tujuan'])];
                                                    }
                                                }
                                                if (empty($dest_items)) $dest_items = [''];
                                                foreach ($dest_items as $di): ?>
                                                    <li class="list-group-item d-flex align-items-center">
                                                        <input type="text" class="form-control me-2 destination-item" value="<?= htmlspecialchars($di) ?>" placeholder="Alamat tujuan">
                                                        <button type="button" class="btn btn-sm btn-danger remove-destination">&times;</button>
                                                    </li>
                                                <?php endforeach; ?>
                                            </ul>
                                            <div class="mb-2">
                                                <button type="button" id="addDestinationBtn" class="btn btn-sm btn-outline-primary">Tambah Tujuan</button>
                                            </div>
                                            <input type="hidden" name="tujuan" id="tujuan_hidden" value="<?= isset($surat_data['tujuan']) ? htmlspecialchars($surat_data['tujuan']) : '' ?>">
                                            <small class="form-text text-muted">Anda bisa menambah beberapa tujuan; peta akan menghitung rute otomatis.</small>
                                        </div>
                                    </div>
                                    <div class="col-md-6">
                                        <div class="form-group">
                                            <label>Berangkat Dari</label>
                                            <div class="form-control-plaintext">SPBT Kemhan Cawang</div>
                                            <input type="hidden" name="berangkat_dari" id="berangkat_dari" value="<?= htmlspecialchars($surat_data['berangkat_dari'] ?? 'SPBT Kemhan Cawang') ?>">
                                            <small class="form-text text-muted">Asal rute dipaksa ke SPBT Kemhan Cawang; pilih tujuan pada peta atau gunakan saran lokasi.</small>
                                        </div>
                                    </div>
                                    <div class="col-12">
                                        <div id="routeMap" style="height:360px; border:1px solid #ddd; border-radius:6px;"></div>
                                        <div id="routeSummary" class="mt-2 small text-muted">Jarak: <span id="routeDistance">-</span> km — Estimasi BBM: <span id="routeFuel">-</span> L</div>
                                    </div>
                                </div>

                                <div class="form-group">
                                    <label for="keperluan">Isi *</label>
                                    <textarea name="keperluan" id="keperluan" class="form-control" rows="3" required
                                              placeholder="Isi surat / uraian kegiatan"><?= htmlspecialchars($surat_data['keperluan'] ?? '') ?></textarea>
                                </div>

                                <div class="row">
                                    <div class="col-md-4">
                                        <div class="form-group">
                                            <label for="tanggal_berangkat">Tanggal Berangkat *</label>
                                            <input type="date" name="tanggal_berangkat" id="tanggal_berangkat" 
                                                   class="form-control" required
                                                   value="<?= isset($surat_data) ? $surat_data['tanggal_berangkat'] : '' ?>">
                                        </div>
                                    </div>
                                    <div class="col-md-4">
                                        <div class="form-group">
                                            <label for="tanggal_kembali">Tanggal Kembali</label>
                          <input type="date" name="tanggal_kembali" id="tanggal_kembali" 
                              class="form-control" required
                              value="<?= isset($surat_data) ? $surat_data['tanggal_kembali'] : '' ?>">
                                        </div>
                                    </div>
                                    <div class="col-md-4">
                                        <div class="form-group">
                                            <label for="waktu_berangkat">Waktu Berangkat</label>
                                            <input type="text" name="waktu_berangkat" id="waktu_berangkat" 
                                                   class="form-control"
                                                   value="<?= htmlspecialchars($surat_data['waktu_berangkat'] ?? 'Pukul 05.00 WIB s.d selesai') ?>"
                                                   placeholder="Contoh: Pukul 05.00 WIB s.d selesai">
                                        </div>
                                    </div>
                                </div>

                                <div class="row">
                                    <div class="col-md-6">
                                        <div class="form-group">
                                            <label for="estimasi_km">Estimasi KM</label>
                                            <input type="number" name="estimasi_km" id="estimasi_km" 
                                                   class="form-control" 
                                                   value="<?= isset($surat_data) ? $surat_data['estimasi_km'] : '' ?>"
                                                   placeholder="Contoh: 300">
                                        </div>
                                    </div>
                                    <div class="col-md-6">
                                        <div class="form-group">
                                            <label for="estimasi_bbm">Estimasi BBM (Liter)</label>
                                            <input type="number" name="estimasi_bbm" id="estimasi_bbm" 
                                                   class="form-control" step="0.01"
                                                   value="<?= isset($surat_data) ? $surat_data['estimasi_bbm'] : '' ?>"
                                                   placeholder="Contoh: 30.5">
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- Pejabat Penandatangan -->
                        <div class="card mb-4">
                            <div class="card-header bg-info text-white">
                                <h5 class="mb-0"><i class="fas fa-signature"></i> Pejabat Penandatangan</h5>
                            </div>
                            <div class="card-body">
                                <div class="row">
                                    <div class="col-md-4">
                                        <div class="form-group">
                                            <label for="pejabat_ttd_jabatan">Jabatan (a.n) *</label>
                                            <input type="text" name="pejabat_ttd_jabatan" id="pejabat_ttd_jabatan" 
                                                   class="form-control" required
                                                   value="<?= htmlspecialchars($surat_data['pejabat_ttd_jabatan'] ?? 'a.n Kepala SPBT Kemhan Cawang') ?>"
                                                   placeholder="Contoh: a.n Kepala SPBT Kemhan Cawang">
                                        </div>
                                    </div>
                                    <div class="col-md-4">
                                        <div class="form-group">
                                            <label for="pejabat_ttd_sebagai">Sebagai *</label>
                                            <input type="text" name="pejabat_ttd_sebagai" id="pejabat_ttd_sebagai" 
                                                   class="form-control" required
                                                   value="<?= htmlspecialchars($surat_data['pejabat_ttd_sebagai'] ?? 'Waka,') ?>"
                                                   placeholder="Contoh: Waka, atau Kabag,">
                                        </div>
                                    </div>
                                    <div class="col-md-4">
                                        <div class="form-group">
                                            <label for="pejabat_ttd">Nama & Pangkat Penandatangan *</label>
                                            <input type="text" name="pejabat_ttd" id="pejabat_ttd" 
                                                   class="form-control" required
                                                   value="<?= htmlspecialchars($surat_data['pejabat_ttd'] ?? 'S. Ginting, S.Kom., MMSI., M.Tr.Hankam') ?>"
                                                   placeholder="Contoh: KOLONEL TNI BAMBANG SUSILO">
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- Tembusan (dynamic list) -->
                        <div class="card mb-4">
                            <div class="card-header bg-dark text-white">
                                <h5 class="mb-0"><i class="fas fa-copy"></i> Tembusan</h5>
                            </div>
                            <div class="card-body">
                                <?php
                                $tembusan_items = [];
                                if (isset($surat_data)) {
                                    for ($i = 1; $i <= 4; $i++) {
                                        $key = 'tembusan_' . $i;
                                        if (!empty($surat_data[$key])) $tembusan_items[] = $surat_data[$key];
                                    }
                                }
                                if (empty($tembusan_items)) {
                                    $tembusan_items = ['Kepala SPBT Kemhan Cawang', 'Asops Denma Mabes TNI', 'Dansetang Denma Mabes TNI', 'Dansakdok Denma Mabes TNI'];
                                }
                                ?>

                                <label>Daftar Tembusan</label>
                                <ul id="tembusanList" class="list-group mb-2">
                                    <?php foreach ($tembusan_items as $ti): ?>
                                        <li class="list-group-item d-flex align-items-center">
                                            <input type="text" class="form-control me-2 tembusan-item" value="<?= htmlspecialchars($ti) ?>">
                                            <button type="button" class="btn btn-sm btn-danger remove-tembusan">&times;</button>
                                        </li>
                                    <?php endforeach; ?>
                                </ul>
                                <div class="mb-3">
                                    <button type="button" id="addTembusanBtn" class="btn btn-sm btn-outline-primary">Tambah Tembusan</button>
                                </div>
                                <!-- Hidden fields expected by server -->
                                <input type="hidden" name="tembusan_1" id="tembusan_1_hidden" value="<?= isset($surat_data) ? htmlspecialchars($surat_data['tembusan_1']) : '' ?>">
                                <input type="hidden" name="tembusan_2" id="tembusan_2_hidden" value="<?= isset($surat_data) ? htmlspecialchars($surat_data['tembusan_2']) : '' ?>">
                                <input type="hidden" name="tembusan_3" id="tembusan_3_hidden" value="<?= isset($surat_data) ? htmlspecialchars($surat_data['tembusan_3']) : '' ?>">
                                <input type="hidden" name="tembusan_4" id="tembusan_4_hidden" value="<?= isset($surat_data) ? htmlspecialchars($surat_data['tembusan_4']) : '' ?>">
                            </div>
                        </div>

                        <?php if ($action === 'edit'): ?>
                        <hr>
                        <h5>Status & Laporan Perjalanan</h5>
                        
                        <div class="row">
                            <div class="col-md-4">
                                <div class="form-group">
                                    <label for="status">Status</label>
                                    <select name="status" id="status" class="form-control">
                                        <?php 
                                        $status_options = ['Draft', 'Disetujui', 'Dalam Perjalanan', 'Selesai', 'Dibatalkan'];
                                        foreach ($status_options as $status_option): 
                                        ?>
                                            <option value="<?= $status_option ?>" 
                                                    <?= (isset($surat_data) && $surat_data['status'] == $status_option) ? 'selected' : '' ?>>
                                                <?= $status_option ?>
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                            </div>
                            <div class="col-md-4">
                                <div class="form-group">
                                    <label for="km_berangkat">KM Berangkat</label>
                                    <input type="number" name="km_berangkat" id="km_berangkat" 
                                           class="form-control" 
                                           value="<?= isset($surat_data) ? $surat_data['km_berangkat'] : '' ?>">
                                </div>
                            </div>
                            <div class="col-md-4">
                                <div class="form-group">
                                    <label for="km_kembali">KM Kembali</label>
                                    <input type="number" name="km_kembali" id="km_kembali" 
                                           class="form-control" 
                                           value="<?= isset($surat_data) ? $surat_data['km_kembali'] : '' ?>">
                                </div>
                            </div>
                        </div>

                        <div class="row">
                            <div class="col-md-6">
                                <div class="form-group">
                                    <label for="bbm_terpakai">BBM Terpakai (Liter)</label>
                                    <input type="number" name="bbm_terpakai" id="bbm_terpakai" 
                                           class="form-control" step="0.01"
                                           value="<?= isset($surat_data) ? $surat_data['bbm_terpakai'] : '' ?>">
                                </div>
                            </div>
                        </div>

                        <div class="form-group">
                            <label for="laporan_perjalanan">Laporan Perjalanan</label>
                            <textarea name="laporan_perjalanan" id="laporan_perjalanan" class="form-control" rows="4"
                                      placeholder="Laporan hasil perjalanan dinas"><?= isset($surat_data) ? htmlspecialchars($surat_data['laporan_perjalanan'] ?? '') : '' ?></textarea>
                        </div>
                        <?php endif; ?>

                        <div class="form-group">
                            <input type="hidden" name="csrf_token" value="<?= generate_csrf_token() ?>">
                            <button type="submit" class="btn btn-primary">
                                <i class="fas fa-save"></i> Simpan
                            </button>
                            <a href="?page=surat_tugas" class="btn btn-secondary">
                                <i class="fas fa-times"></i> Batal
                            </a>
                        </div>
                    </form>
                </div>
            </div>
        <?php endif; ?>
    </div>
</div>

<script>
// Auto-populate driver/pengguna based on selected vehicle
$(document).ready(function() {
    const kendaraanSelect = document.getElementById('kendaraan_id');
    const penggunaSelect = document.getElementById('pengguna_id');
    const penggunaDisplay = document.getElementById('pengguna_display');
    const penggunaDisplayText = document.getElementById('pengguna_display_text');
    const penggunaHidden = document.getElementById('pengguna_id_hidden');
    
    // Handle vehicle selection change
    if (kendaraanSelect) {
        kendaraanSelect.addEventListener('change', function() {
            const kendaraanId = parseInt(this.value);
            
            if (kendaraanId <= 0) {
                // Clear when no vehicle selected
                if (penggunaSelect) penggunaSelect.value = '';
                if (penggunaDisplay) penggunaDisplay.style.display = 'none';
                if (penggunaHidden) penggunaHidden.value = '';
                return;
            }
            
            // Fetch driver info
            fetch('ajax/get_vehicle_driver.php?kendaraan_id=' + kendaraanId)
                .then(response => response.json())
                .then(data => {
                    if (data.success && data.data) {
                        const driverInfo = data.data;
                        
                        if (driverInfo.has_driver && driverInfo.pengguna_id) {
                            // Set pengguna_id value
                            if (penggunaSelect) {
                                penggunaSelect.value = driverInfo.pengguna_id;
                            }
                            if (penggunaHidden) {
                                penggunaHidden.value = driverInfo.pengguna_id;
                            }
                            
                            // Show driver info for users
                            if (penggunaDisplay && penggunaDisplayText) {
                                penggunaDisplayText.textContent = driverInfo.display_text;
                                penggunaDisplay.style.display = 'block';
                            }
                        } else {
                            // No penanggung jawab assigned
                            if (penggunaSelect) penggunaSelect.value = '';
                            if (penggunaHidden) penggunaHidden.value = '';
                            if (penggunaDisplay) penggunaDisplay.style.display = 'none';
                            alert('Kendaraan yang dipilih belum memiliki penanggung jawab yang ditugaskan.');
                        }
                    }
                    try { if (typeof recalcRouteAndEstimates === 'function') recalcRouteAndEstimates(); } catch (e) { }
                })
                .catch(error => {
                    console.error('Error fetching penanggung jawab info:', error);
                    alert('Gagal mengambil informasi penanggung jawab kendaraan.');
                });
        });
        
        // Trigger change if vehicle is already selected (edit mode)
        if (kendaraanSelect.value) {
            kendaraanSelect.dispatchEvent(new Event('change'));
        }
    }
});

function filterStatus(status) {
    const table = document.getElementById('suratTable');
    const rows = table.getElementsByTagName('tbody')[0].getElementsByTagName('tr');
    
    // Update button states
    document.querySelectorAll('.btn-group .btn').forEach(btn => {
        btn.classList.remove('active');
    });
    event.target.classList.add('active');
    
    // Filter rows
    for (let i = 0; i < rows.length; i++) {
        const row = rows[i];
        const rowStatus = row.getAttribute('data-status');
        
        if (status === 'all' || rowStatus === status) {
            row.style.display = '';
        } else {
            row.style.display = 'none';
        }
    }
}

// Auto-generate nomor surat
function generateNomorSurat() {
    const today = new Date();
    const month = ['I', 'II', 'III', 'IV', 'V', 'VI', 'VII', 'VIII', 'IX', 'X', 'XI', 'XII'][today.getMonth()];
    const year = today.getFullYear();
    
    // Get existing numbers for this month/year via AJAX
    fetch('ajax/get_next_nomor_surat.php?month=' + month + '&year=' + year)
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                document.getElementById('nomor_surat').value = data.nomor_surat;
            }
        })
        .catch(error => {
            console.error('Error:', error);
            // Fallback to simple increment
            const nomorSurat = document.getElementById('nomor_surat');
            nomorSurat.value = `ST/001/${month}/${year}`;
        });
}

// Check availability function
function checkAvailability() {
    const kendaraanId = document.getElementById('kendaraan_id').value;
    const penggunaId = document.getElementById('pengguna_id').value;
    const tanggalBerangkat = document.getElementById('tanggal_berangkat').value;
    const tanggalKembali = document.getElementById('tanggal_kembali').value;
    const suratId = <?= $surat_id ? $surat_id : 'null' ?>;
    
    if (!kendaraanId || !penggunaId || !tanggalBerangkat) {
        return;
    }
    
    const params = new URLSearchParams({
        kendaraan_id: kendaraanId,
        pengguna_id: penggunaId,
        tanggal_berangkat: tanggalBerangkat,
        tanggal_kembali: tanggalKembali || '',
        surat_id: suratId || ''
    });
    
    fetch('ajax/check_availability.php?' + params)
        .then(response => response.json())
        .then(data => {
            const alertDiv = document.getElementById('availability-alert');
            if (alertDiv) {
                alertDiv.remove();
            }
            
            if (!data.vehicle_available || !data.user_available) {
                let message = '<div id="availability-alert" class="alert alert-warning mt-2">';
                message += '<strong>Peringatan:</strong><ul>';
                
                if (!data.vehicle_available) {
                    message += '<li>Kendaraan tidak tersedia pada tanggal tersebut</li>';
                }
                if (!data.user_available) {
                    message += '<li>Pengguna tidak tersedia pada tanggal tersebut</li>';
                }
                
                message += '</ul></div>';
                
                document.querySelector('.card-body form').insertAdjacentHTML('afterbegin', message);
            }
        })
        .catch(error => {
            console.error('Error checking availability:', error);
        });
}

document.addEventListener('DOMContentLoaded', function() {
    const nomorSurat = document.getElementById('nomor_surat');
    
    // Auto-generate nomor surat if empty and this is add mode
    if (nomorSurat && !nomorSurat.value && window.location.href.includes('action=add')) {
        generateNomorSurat();
    }
    
    // Add event listeners for availability checking
    const kendaraanSelect = document.getElementById('kendaraan_id');
    const penggunaSelect = document.getElementById('pengguna_id');
    const tanggalBerangkat = document.getElementById('tanggal_berangkat');
    const tanggalKembali = document.getElementById('tanggal_kembali');
    
    if (kendaraanSelect) kendaraanSelect.addEventListener('change', checkAvailability);
    if (penggunaSelect) penggunaSelect.addEventListener('change', checkAvailability);
    if (tanggalBerangkat) tanggalBerangkat.addEventListener('change', checkAvailability);
    if (tanggalKembali) tanggalKembali.addEventListener('change', checkAvailability);
    
    // Auto-generate button
    if (nomorSurat) {
        const generateBtn = document.createElement('button');
        generateBtn.type = 'button';
        generateBtn.className = 'btn btn-sm btn-outline-primary ml-2';
        generateBtn.innerHTML = '<i class="fas fa-sync"></i> Auto';
        generateBtn.onclick = generateNomorSurat;
        
        nomorSurat.parentElement.appendChild(generateBtn);
    }
});

// Function to download PDF
function downloadPDF(suratId) {
    // Open PDF in new window for download
    const pdfWindow = window.open('?page=surat_tugas&action=download_pdf&id=' + suratId, '_blank', 'width=800,height=600');
    
    // Focus back to main window after opening
    if (pdfWindow) {
        setTimeout(() => {
            window.focus();
        }, 500);
    }
}
</script>

<!-- Leaflet + Routing -->
<link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.3/dist/leaflet.css" integrity="" crossorigin="" />
<link rel="stylesheet" href="https://unpkg.com/leaflet-routing-machine@3.2.12/dist/leaflet-routing-machine.css" />
<script src="https://unpkg.com/leaflet@1.9.3/dist/leaflet.js"></script>
<script src="https://unpkg.com/leaflet-routing-machine@3.2.12/dist/leaflet-routing-machine.min.js"></script>

<script>
// Map, routing and geocoding for multi-destination route + BBM estimate
document.addEventListener('DOMContentLoaded', function(){
    if (!document.getElementById('routeMap')) return;

    const map = L.map('routeMap').setView([-6.200, 106.816], 11);
    L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
        maxZoom: 19,
        attribution: '&copy; OpenStreetMap contributors'
    }).addTo(map);

    let routingControl = null;
    let originMarker = null;
    const destMarkers = new Map(); // inputElem -> marker

    const kendaraanSelect = document.getElementById('kendaraan_id');
    const originInput = document.getElementById('berangkat_dari');
    const destinationList = document.getElementById('destinationList');
    const addDestinationBtn = document.getElementById('addDestinationBtn');
    const routeDistanceEl = document.getElementById('routeDistance');
    const routeFuelEl = document.getElementById('routeFuel');

    // Simple nominatim geocode
    function geocodeAddress(q){
        return fetch('https://nominatim.openstreetmap.org/search?format=json&limit=1&q=' + encodeURIComponent(q), { headers: { 'Accept': 'application/json' } })
            .then(r => r.json())
            .then(j => j && j.length ? j[0] : null)
            .catch(()=>null);
    }
    function reverseGeocode(lat, lon){
        return fetch('https://nominatim.openstreetmap.org/reverse?format=json&lat=' + encodeURIComponent(lat) + '&lon=' + encodeURIComponent(lon), { headers: { 'Accept': 'application/json' } })
            .then(r => r.json())
            .then(j => j && j.display_name ? j.display_name : null)
            .catch(()=>null);
    }

    function addDestinationInput(value){
        const li = document.createElement('li');
        li.className = 'list-group-item d-flex align-items-center';
        const input = document.createElement('input');
        input.type = 'text';
        input.className = 'form-control me-2 destination-item';
        input.value = value || '';
        input.placeholder = 'Alamat tujuan';
        // container for suggestions
        li.style.position = 'relative';
        const sugg = document.createElement('div');
        sugg.className = 'destination-suggestions list-group';
        sugg.style.position = 'absolute';
        sugg.style.left = '0';
        sugg.style.right = '0';
        sugg.style.top = '100%';
        sugg.style.zIndex = '1200';
        const btn = document.createElement('button');
        btn.type = 'button'; btn.className = 'btn btn-sm btn-danger remove-destination'; btn.innerHTML = '&times;';
        btn.addEventListener('click', function(){
            // remove marker if exists
            const m = destMarkers.get(input);
            if (m) { map.removeLayer(m); destMarkers.delete(input); }
            li.remove(); recalcRouteAndEstimates();
        });
        input.addEventListener('keydown', function(e){ if (e.key === 'Enter') { e.preventDefault(); geocodeInputAndSetMarker(input).then(recalcRouteAndEstimates); } });
        input.addEventListener('blur', function(){ setTimeout(()=>{ if (input.value.trim()) geocodeInputAndSetMarker(input).then(recalcRouteAndEstimates); sugg.innerHTML=''; }, 200); });
        // autocomplete (Nominatim) with debounce
        let acTimeout = null;
        input.addEventListener('input', function(){
            const q = input.value.trim();
            if (acTimeout) clearTimeout(acTimeout);
            if (q.length < 2) { sugg.innerHTML = ''; return; }
            acTimeout = setTimeout(function(){
                fetch('https://nominatim.openstreetmap.org/search?format=json&limit=5&q=' + encodeURIComponent(q))
                    .then(r => r.json())
                    .then(list => {
                        sugg.innerHTML = '';
                        if (!list || !list.length) return;
                        list.forEach(item => {
                            const a = document.createElement('a');
                            a.href = '#';
                            a.className = 'list-group-item list-group-item-action';
                            a.textContent = item.display_name;
                            a.addEventListener('click', function(ev){ ev.preventDefault(); input.value = item.display_name; input.dataset.lat = item.lat; input.dataset.lng = item.lon; input.dataset.display = item.display_name; // place marker immediately
                                const old = destMarkers.get(input); if (old) map.removeLayer(old);
                                const m = L.marker([parseFloat(item.lat), parseFloat(item.lon)]).addTo(map).bindPopup(item.display_name);
                                destMarkers.set(input, m);
                                sugg.innerHTML = '';
                                recalcRouteAndEstimates();
                            });
                            sugg.appendChild(a);
                        });
                    }).catch(()=>{ sugg.innerHTML=''; });
            }, 300);
        });
        li.appendChild(input); li.appendChild(btn); li.appendChild(sugg); destinationList.appendChild(li);
        return input;
    }

    function geocodeInputAndSetMarker(input){
        const q = input.value.trim();
        if (!q) return Promise.resolve(null);
        return geocodeAddress(q).then(res => {
            if (!res) return null;
            input.dataset.lat = res.lat; input.dataset.lng = res.lon; input.dataset.display = res.display_name || q;
            // place marker
            const latlng = L.latLng(parseFloat(res.lat), parseFloat(res.lon));
            const old = destMarkers.get(input);
            if (old) map.removeLayer(old);
            const m = L.marker(latlng).addTo(map).bindPopup(input.dataset.display || q);
            destMarkers.set(input, m);
            return {lat: latlng.lat, lng: latlng.lng};
        });
    }

    function setOriginMarker(latlng, display){
        if (originMarker) map.removeLayer(originMarker);
        originMarker = L.marker(latlng, {icon: L.icon({iconUrl: 'https://unpkg.com/leaflet@1.9.3/dist/images/marker-icon.png'})}).addTo(map).bindPopup(display || 'Asal');
    }

    function getConsumptionRateForSelectedVehicle(){
        if (!kendaraanSelect) return 4;
        const opt = kendaraanSelect.selectedOptions && kendaraanSelect.selectedOptions[0];
        const merk = (opt && (opt.dataset && opt.dataset.merk)) ? opt.dataset.merk.toLowerCase() : '';
        if (merk.includes('mercedes')) return 3;
        if (merk.includes('mitsubishi')) return 4;
        if (merk.includes('hino')) return 5;
        return 4; // default
    }

    function recalcRouteAndEstimates(){
        // Build promises to ensure lat/lng available for origin and all destinations
        // If an origin input exists but is empty, fall back to the fixed origin
        const originVal = (originInput && originInput.value && originInput.value.trim()) ? originInput.value.trim() : 'SPBT Kemhan Cawang';
        const destInputs = Array.from(document.querySelectorAll('.destination-item'));
        const originPromise = (originInput && originInput.dataset && originInput.dataset.lat && originInput.dataset.lng)
            ? Promise.resolve({lat: parseFloat(originInput.dataset.lat), lng: parseFloat(originInput.dataset.lng)})
            : (originVal ? geocodeAddress(originVal).then(r => {
                if (r) {
                    if (originInput) { originInput.dataset.lat = r.lat; originInput.dataset.lng = r.lon; originInput.dataset.display = r.display_name; }
                    setOriginMarker([parseFloat(r.lat), parseFloat(r.lon)], r.display_name);
                    return {lat: parseFloat(r.lat), lng: parseFloat(r.lon)};
                }
                // geocode failed -> fallback to fixed SPBT Kemhan Cawang coords so routing can proceed
                console.warn('geocodeAddress failed for origin, using fixed fallback coords');
                const fallback = { lat: -6.200, lng: 106.816 };
                if (originInput) { originInput.dataset.lat = fallback.lat; originInput.dataset.lng = fallback.lng; originInput.dataset.display = 'SPBT Kemhan Cawang'; }
                setOriginMarker([fallback.lat, fallback.lng], 'SPBT Kemhan Cawang');
                return fallback;
            }) : Promise.resolve(null));

        const destPromises = destInputs.map(inp => {
            const v = inp.value.trim();
            if (!v) return Promise.resolve(null);
            if (inp.dataset.lat && inp.dataset.lng) return Promise.resolve({lat: parseFloat(inp.dataset.lat), lng: parseFloat(inp.dataset.lng)});
            return geocodeAddress(v).then(r=>{ if (r){ inp.dataset.lat=r.lat; inp.dataset.lng=r.lon; inp.dataset.display=r.display_name; const old = destMarkers.get(inp); if (old) map.removeLayer(old); const m = L.marker([parseFloat(r.lat), parseFloat(r.lon)]).addTo(map).bindPopup(r.display_name); destMarkers.set(inp,m); return {lat: parseFloat(r.lat), lng: parseFloat(r.lon)} } return null; });
        });

        return Promise.all([originPromise].concat(destPromises)).then(results => {
            const originLatLng = results[0];
            const destLatLngs = results.slice(1).filter(Boolean);
            console.log('recalcRouteAndEstimates (surat_tugas): origin=', originLatLng, 'dests=', destLatLngs);
            if (!originLatLng || destLatLngs.length === 0) {
                // Not enough points to route
                console.log('recalcRouteAndEstimates: not enough points to route');
                routeDistanceEl.textContent = '-'; routeFuelEl.textContent = '-';
                if (routingControl) { try{ map.removeControl(routingControl); } catch(e){} routingControl = null; }
                return;
            }

            // Build waypoints: origin -> dest1 -> dest2 ... -> origin (return)
            const waypoints = [L.latLng(originLatLng.lat, originLatLng.lng)].concat(destLatLngs.map(d=>L.latLng(d.lat, d.lng))).concat([L.latLng(originLatLng.lat, originLatLng.lng)]);

            if (routingControl) { try{ map.removeControl(routingControl); } catch(e){} routingControl = null; }
            routingControl = L.Routing.control({
                waypoints: waypoints,
                lineOptions: { styles: [{color: 'blue', opacity: 0.6, weight: 5}] },
                createMarker: function(i, wp) { return L.marker(wp.latLng); },
                addWaypoints: false,
                routeWhileDragging: false,
                fitSelectedRoutes: true,
                router: L.Routing.osrmv1({ serviceUrl: 'https://router.project-osrm.org/route/v1' })
            }).addTo(map);

            // routesfound handler with fallback
            let routesFoundHandled = false;
            routingControl.on('routesfound', function(e){
                routesFoundHandled = true;
                const summary = e.routes && e.routes[0] && e.routes[0].summary;
                if (!summary) return;
                console.log('OSRM routesfound summary=', summary);
                const distKmRaw = (summary.totalDistance/1000);
                const distKmCeil = Math.ceil(distKmRaw);
                routeDistanceEl.textContent = distKmCeil;
                // Estimasi BBM: ceil(liters) + 2
                const rate = getConsumptionRateForSelectedVehicle();
                const litersRaw = distKmRaw / rate;
                const litersCeilPlus = Math.ceil(litersRaw) + 2;
                routeFuelEl.textContent = litersCeilPlus;
                // Update form fields (prefer numeric values and trigger input)
                const kmEl = document.getElementById('estimasi_km');
                const bbmEl = document.getElementById('estimasi_bbm');
                if (kmEl) { kmEl.value = distKmCeil; kmEl.dispatchEvent(new Event('input')); }
                if (bbmEl) { bbmEl.value = litersCeilPlus; bbmEl.dispatchEvent(new Event('input')); }
            });

            // Immediate approximate (straight-line) distance so UI shows values quickly
            try {
                function haversine(a, b) {
                    const R = 6371; // km
                    const dLat = (b.lat - a.lat) * Math.PI / 180;
                    const dLon = (b.lng - a.lng) * Math.PI / 180;
                    const lat1 = a.lat * Math.PI / 180;
                    const lat2 = b.lat * Math.PI / 180;
                    const sinHalf = Math.sin(dLat/2) * Math.sin(dLat/2) + Math.cos(lat1) * Math.cos(lat2) * Math.sin(dLon/2) * Math.sin(dLon/2);
                    const c = 2 * Math.atan2(Math.sqrt(sinHalf), Math.sqrt(1 - sinHalf));
                    return R * c; // km
                }
                let totalKm = 0;
                for (let i = 0; i < waypoints.length - 1; i++) {
                    const a = waypoints[i];
                    const b = waypoints[i+1];
                    if (!a || !b) continue;
                    totalKm += haversine({lat: a.lat, lng: a.lng}, {lat: b.lat, lng: b.lng});
                }
                const distKmCeil = Math.ceil(totalKm);
                console.log('Immediate straight-line distance (km)=', distKmCeil);
                routeDistanceEl.textContent = distKmCeil;
                const rate = getConsumptionRateForSelectedVehicle();
                const litersCeilPlus = Math.ceil(totalKm / rate) + 2;
                console.log('Immediate liters (ceil +2)=', litersCeilPlus);
                routeFuelEl.textContent = litersCeilPlus;
                const kmEl = document.getElementById('estimasi_km');
                const bbmEl = document.getElementById('estimasi_bbm');
                if (kmEl) { kmEl.value = distKmCeil; kmEl.dispatchEvent(new Event('input')); }
                if (bbmEl) { bbmEl.value = litersCeilPlus; bbmEl.dispatchEvent(new Event('input')); }
            } catch(e) { console.warn('Immediate routing calculation failed', e); }

            // Fit map to route after short delay
            setTimeout(()=>{ try{ if (routingControl && routingControl.getPlan) routingControl.getPlan().setWaypoints(waypoints); } catch(e){} }, 300);
        });
    }

    // Initialize existing destination inputs (add autocomplete + remove handlers)
    Array.from(document.querySelectorAll('.destination-item')).forEach(inp => {
        const li = inp.closest('li') || inp.parentElement;
        // ensure suggestion container exists
        let sugg = li.querySelector('.destination-suggestions');
        if (!sugg) {
            sugg = document.createElement('div');
            sugg.className = 'destination-suggestions list-group';
            sugg.style.position = 'absolute';
            sugg.style.left = '0';
            sugg.style.right = '0';
            sugg.style.top = '100%';
            sugg.style.zIndex = '1200';
            li.style.position = 'relative';
            li.appendChild(sugg);
        }

        // wire remove button
        const removeBtn = li.querySelector('.remove-destination');
        if (removeBtn) {
            removeBtn.addEventListener('click', function(){ const m = destMarkers.get(inp); if (m) { try{ map.removeLayer(m); } catch(e){} destMarkers.delete(inp); } li.remove(); recalcRouteAndEstimates(); });
        } else {
            const btn = document.createElement('button'); btn.type='button'; btn.className='btn btn-sm btn-danger remove-destination'; btn.innerHTML='&times;'; btn.addEventListener('click', function(){ const m = destMarkers.get(inp); if (m) { try{ map.removeLayer(m); } catch(e){} destMarkers.delete(inp); } li.remove(); recalcRouteAndEstimates(); }); li.appendChild(btn);
        }

        // input handlers (enter, blur)
        inp.addEventListener('keydown', function(e){ if (e.key === 'Enter') { e.preventDefault(); geocodeInputAndSetMarker(inp).then(recalcRouteAndEstimates); } });
        inp.addEventListener('blur', function(){ setTimeout(()=>{ if (inp.value.trim()) geocodeInputAndSetMarker(inp).then(recalcRouteAndEstimates); sugg.innerHTML=''; }, 200); });

        // autocomplete for existing input (debounced)
        let acTimeout = null;
        inp.addEventListener('input', function(){ const q = inp.value.trim(); sugg.innerHTML = ''; if (acTimeout) clearTimeout(acTimeout); if (q.length < 2) return; acTimeout = setTimeout(function(){ fetch('https://nominatim.openstreetmap.org/search?format=json&limit=5&q=' + encodeURIComponent(q)).then(r=>r.json()).then(list=>{ sugg.innerHTML = ''; if (!list || !list.length) return; list.forEach(item=>{ const a = document.createElement('a'); a.href='#'; a.className='list-group-item list-group-item-action'; a.textContent = item.display_name; a.addEventListener('click', function(ev){ ev.preventDefault(); inp.value = item.display_name; inp.dataset.lat = item.lat; inp.dataset.lng = item.lon; inp.dataset.display = item.display_name; const old = destMarkers.get(inp); if (old) try{ map.removeLayer(old); }catch(e){} const m = L.marker([parseFloat(item.lat), parseFloat(item.lon)]).addTo(map).bindPopup(item.display_name); destMarkers.set(inp, m); sugg.innerHTML=''; recalcRouteAndEstimates(); }); sugg.appendChild(a); }); }).catch(()=>{ sugg.innerHTML=''; }); }, 250); });

        // if there's an initial value, geocode it to create a marker
        if (inp.value.trim()) {
            if (inp.dataset.lat && inp.dataset.lng) {
                try{
                    const m = L.marker([parseFloat(inp.dataset.lat), parseFloat(inp.dataset.lng)]).addTo(map).bindPopup(inp.dataset.display || inp.value);
                    destMarkers.set(inp, m);
                } catch(e) {}
            } else {
                geocodeInputAndSetMarker(inp).then(()=>{ try{ recalcRouteAndEstimates(); } catch(e){} });
            }
        }
    });

    // Add destination button
    addDestinationBtn?.addEventListener('click', function(){ const newInp = addDestinationInput(''); newInp.focus(); });

    // Map click: add destination at clicked point (origin is fixed to SPBT Kemhan Cawang)
    map.on('click', function(e){
        reverseGeocode(e.latlng.lat, e.latlng.lng).then(addr=>{
            const inp = addDestinationInput(addr || (e.latlng.lat + ',' + e.latlng.lng));
            inp.dataset.lat = e.latlng.lat; inp.dataset.lng = e.latlng.lng; const m = L.marker(e.latlng).addTo(map).bindPopup(inp.value); destMarkers.set(inp, m);
            recalcRouteAndEstimates();
        });
    });

    // Pack destinations on form submit (join with '||')
    const form = document.querySelector('form');
    if (form) {
        form.addEventListener('submit', function(){
            const dests = Array.from(document.querySelectorAll('.destination-item')).map(i=>i.value.trim()).filter(Boolean);
            document.getElementById('tujuan_hidden').value = dests.join('||');
            // ensure estimasi fields are set (prefer numeric values already computed)
            const kmEl = document.getElementById('estimasi_km');
            const bbmEl = document.getElementById('estimasi_bbm');
            if (kmEl) {
                const v = parseFloat(kmEl.value);
                if (isFinite(v)) kmEl.value = Math.round(v * 100) / 100;
                else if (routeDistanceEl && !isNaN(parseFloat(routeDistanceEl.textContent))) kmEl.value = Math.round(parseFloat(routeDistanceEl.textContent) * 100) / 100;
            }
            if (bbmEl) {
                const v2 = parseFloat(bbmEl.value);
                if (isFinite(v2)) bbmEl.value = Math.round(v2 * 100) / 100;
                else if (routeFuelEl && !isNaN(parseFloat(routeFuelEl.textContent))) bbmEl.value = Math.round(parseFloat(routeFuelEl.textContent) * 100) / 100;
            }
        });
    }

    // initial calc if possible
    setTimeout(()=>{ recalcRouteAndEstimates(); }, 800);
});
</script>
<script>
document.addEventListener('DOMContentLoaded', function(){
    const selectAll = document.getElementById('select_all');
    const deleteBtn = document.getElementById('deleteSelectedBtn');
    const bulkDeleteForm = document.getElementById('bulkDeleteForm');

    function updateDeleteButton() {
        const any = document.querySelectorAll('.select_row:checked').length > 0;
        if (deleteBtn) deleteBtn.disabled = !any;
    }

    if (selectAll) {
        selectAll.addEventListener('change', function(){
            const rows = document.querySelectorAll('.select_row');
            rows.forEach(r => r.checked = selectAll.checked);
            updateDeleteButton();
        });
    }

    document.querySelectorAll('.select_row').forEach(chk => chk.addEventListener('change', function(){
        const total = document.querySelectorAll('.select_row').length;
        const checked = document.querySelectorAll('.select_row:checked').length;
        if (selectAll) selectAll.checked = (total === checked);
        updateDeleteButton();
    }));

    if (deleteBtn && bulkDeleteForm) {
        deleteBtn.addEventListener('click', function(){
            if (!confirm('Yakin ingin menghapus surat tugas yang dipilih? Tindakan ini tidak dapat dibatalkan.')) return;
            // Collect selected IDs
            const ids = Array.from(document.querySelectorAll('.select_row:checked')).map(c => c.getAttribute('data-id'));
            if (ids.length === 0) return;
            // Clear any previous inputs
            Array.from(bulkDeleteForm.querySelectorAll('input[name="bulk_delete_ids[]"]')).forEach(n => n.remove());
            ids.forEach(id => {
                const inp = document.createElement('input');
                inp.type = 'hidden';
                inp.name = 'bulk_delete_ids[]';
                inp.value = id;
                bulkDeleteForm.appendChild(inp);
            });
            // Submit hidden POST form
            bulkDeleteForm.submit();
        });
    }
});
</script>
<script>
// Table sorting for surat list
function compareValues(a, b, type) {
    if (type === 'num') {
        return (parseInt(a) || 0) - (parseInt(b) || 0);
    }
    if (type === 'date') {
        // Robust date parsing: accept ISO (YYYY-MM-DD) and common local formats (DD-MM-YYYY or DD/MM/YYYY)
        function toTime(v) {
            if (!v) return 0;
            v = v.toString().trim();
            // If the cell contains a range like "21/08/2025 s/d 21/08/2025", extract the first date-like substring
            const dateRegex = /(\d{4}-\d{2}-\d{2})|(\d{1,2}[\/\-.]\d{1,2}[\/\-.]\d{4})/g;
            const matches = v.match(dateRegex);
            let candidate = matches && matches.length ? matches[0] : v;

            // Normalize separators to '-' for easier parsing
            candidate = candidate.replace(/\./g, '-').replace(/\//g, '-');

            // If ISO format YYYY-MM-DD, let Date.parse handle it
            if (/^\d{4}-\d{2}-\d{2}$/.test(candidate)) {
                const t = Date.parse(candidate);
                return isNaN(t) ? 0 : t;
            }

            // If dd-mm-yyyy, construct Date(year, month-1, day)
            const dmy = candidate.match(/^(\d{1,2})-(\d{1,2})-(\d{4})$/);
            if (dmy) {
                const day = parseInt(dmy[1], 10);
                const month = parseInt(dmy[2], 10) - 1;
                const year = parseInt(dmy[3], 10);
                return new Date(year, month, day).getTime();
            }

            // Try Date.parse as fallback
            const direct = Date.parse(candidate);
            if (!isNaN(direct)) return direct;

            // Unknown format => treat as very early
            return 0;
        }

        const ta = toTime(a);
        const tb = toTime(b);
        return ta - tb;
    }
    // text
    return a.toString().localeCompare(b.toString(), undefined, {sensitivity: 'base'});
}

function sortTable(tableId, colIndex, type, asc) {
    const table = document.getElementById(tableId);
    const tbody = table.tBodies[0];
    const rows = Array.from(tbody.querySelectorAll('tr'));

    rows.sort((r1, r2) => {
        const c1 = r1.children[colIndex]?.textContent?.trim() || '';
        const c2 = r2.children[colIndex]?.textContent?.trim() || '';
        const cmp = compareValues(c1, c2, type);
        return asc ? cmp : -cmp;
    });

    // Append in new order
    rows.forEach(r => tbody.appendChild(r));
}

document.addEventListener('DOMContentLoaded', function() {
    const table = document.getElementById('suratTable');
    if (!table) return;

    // Initialize sort state
    table._sortState = {index: null, asc: true};

    const headers = table.querySelectorAll('thead th.sortable');
    headers.forEach((th, idx) => {
        // Determine real column index among all ths
        const allTh = Array.from(th.parentElement.children);
        const realIndex = allTh.indexOf(th);
        th.style.cursor = 'pointer';
        th.addEventListener('click', function() {
            const type = th.getAttribute('data-type') || 'text';
            let asc = true;
            if (table._sortState.index === realIndex) {
                asc = !table._sortState.asc;
            }
            table._sortState = {index: realIndex, asc: asc};

            // Clear indicators
            headers.forEach(h => h.querySelector('.sort-indicator').textContent = '');
            th.querySelector('.sort-indicator').textContent = asc ? '▲' : '▼';

            sortTable('suratTable', realIndex, type, asc);
        });
    });
});
</script>
<script>
// Manage dynamic dasar and tembusan lists
function makeRemoveHandler(containerId, itemClass) {
    return function(e) {
        const li = e.target.closest('li');
        if (li) li.remove();
    };
}

function addListItem(listId, itemClass, value) {
    const ul = document.getElementById(listId);
    const li = document.createElement('li');
    li.className = 'list-group-item d-flex align-items-center';
    const input = document.createElement('input');
    input.type = 'text';
    input.className = 'form-control me-2 ' + itemClass;
    input.value = value || '';
    const btn = document.createElement('button');
    btn.type = 'button';
    btn.className = 'btn btn-sm btn-danger remove-' + itemClass.replace('-item','');
    btn.innerHTML = '&times;';
    btn.addEventListener('click', function(){ li.remove(); });
    li.appendChild(input);
    li.appendChild(btn);
    ul.appendChild(li);
}

document.addEventListener('DOMContentLoaded', function() {
    // Dasar
    document.getElementById('addDasarBtn')?.addEventListener('click', function(){ addListItem('dasarList','dasar-item',''); });
    document.querySelectorAll('.remove-dasar').forEach(btn => btn.addEventListener('click', makeRemoveHandler('dasarList','dasar-item')));

    // Tembusan
    document.getElementById('addTembusanBtn')?.addEventListener('click', function(){ addListItem('tembusanList','tembusan-item',''); });
    document.querySelectorAll('.remove-tembusan').forEach(btn => btn.addEventListener('click', makeRemoveHandler('tembusanList','tembusan-item')));

    // Before submit, pack list values into hidden inputs expected by server
    const form = document.querySelector('form');
    form.addEventListener('submit', function(e){
        // Pack dasar into dasar_a_hidden and dasar_b_hidden (split half/half)
        const dasarInputs = Array.from(document.querySelectorAll('.dasar-item')).map(i => i.value.trim()).filter(Boolean);
        const mid = Math.ceil(dasarInputs.length/2);
        const a = dasarInputs.slice(0, mid).join('; ');
        const b = dasarInputs.slice(mid).join('; ');
        document.getElementById('dasar_a_hidden').value = a;
        document.getElementById('dasar_b_hidden').value = b;

        // Pack tembusan into up to 4 hidden fields
        const tembusanInputs = Array.from(document.querySelectorAll('.tembusan-item')).map(i => i.value.trim()).filter(Boolean);
        for (let i = 0; i < 4; i++) {
            const el = document.getElementById('tembusan_' + (i+1) + '_hidden');
            el.value = tembusanInputs[i] || '';
        }
    });
});
</script>
