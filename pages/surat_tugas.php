<?php
require_once 'includes/auth.php';

$current_role = get_current_role();
$current_user_id = get_current_user_id();

// Role-based access control
if ($current_role === 'guest') {
    header('Location: index.php?page=kendaraan_publik');
    exit;
}

$can_crud = can_operate(); // operator dan admin
$can_view = is_logged_in();

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
        `kepada_jabatan` varchar(100) DEFAULT 'Dandenma Mabes TNI',
        `kepada_tempat` varchar(50) DEFAULT 'Jakarta',
        `dasar_a` text DEFAULT 'Peraturan Panglima TNI Nomor 24 Tahun 2014 tentang Pengesahan Validasi Organisasi FKT dan;',
        `dasar_b` text DEFAULT 'Surat Perintah Kapusinfolahta TNI Nomor Sprin/97/XI/2023 tanggal 20 Oktober 2023 tentang Fungsi Pengadaan HUT ke-17 Pusinfolahta TNI dan;',
        `berangkat_dari` varchar(100) DEFAULT 'Pusinfolahta TNI',
        `waktu_berangkat` varchar(50) DEFAULT 'Pukul 05.00 WIB s.d selesai',
        `pejabat_ttd_jabatan` varchar(100) DEFAULT 'a.n Kepala Pusinfolahta TNI',
        `pejabat_ttd_sebagai` varchar(50) DEFAULT 'Waka,',
        `tembusan_1` varchar(100) DEFAULT 'Kapusinfolahta TNI',
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

$action = $_GET['action'] ?? 'list';
$surat_id = $_GET['id'] ?? null;
$msg = '';

// Handle URL parameters for messages
if (isset($_GET['msg']) && $_GET['msg'] === 'success' && isset($_GET['text'])) {
    $msg = '<div class="alert alert-success">' . htmlspecialchars($_GET['text']) . '</div>';
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
        if ($action === 'add' && $can_crud) {
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
                $tanggal_kembali = $_POST['tanggal_kembali'] ?? '';
                $estimasi_km = $_POST['estimasi_km'] ? (int)$_POST['estimasi_km'] : null;
                $estimasi_bbm = $_POST['estimasi_bbm'] ? (float)$_POST['estimasi_bbm'] : null;
                $pejabat_ttd = trim($_POST['pejabat_ttd']);
                
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
                    $stmt = $conn->prepare("INSERT INTO surat_tugas (nomor_surat, tanggal_surat, klasifikasi, lampiran, perihal, kepada_jabatan, kepada_tempat, dasar_a, dasar_b, berangkat_dari, waktu_berangkat, pejabat_ttd_jabatan, pejabat_ttd_sebagai, tembusan_1, tembusan_2, tembusan_3, tembusan_4, kendaraan_id, pengguna_id, tujuan, keperluan, tanggal_berangkat, tanggal_kembali, estimasi_km, estimasi_bbm, pejabat_ttd, created_by) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
                    // types: 1-17 strings, 18 kendaraan_id int, 19 pengguna_id int, 20-23 strings (tujuan, keperluan, tanggal_berangkat, tanggal_kembali),
                    // 24 estimasi_km int, 25 estimasi_bbm double, 26 pejabat_ttd string, 27 created_by int
                    $types = str_repeat('s', 17) . 'ii' . str_repeat('s', 4) . 'idsi';
                    $params = array(
                        $nomor_surat, $tanggal_surat, $klasifikasi, $lampiran, $perihal, $kepada_jabatan, $kepada_tempat, $dasar_a, $dasar_b, $berangkat_dari, $waktu_berangkat, $pejabat_ttd_jabatan, $pejabat_ttd_sebagai, $tembusan_1, $tembusan_2, $tembusan_3, $tembusan_4, $kendaraan_id, $pengguna_id, $tujuan, $keperluan, $tanggal_berangkat, $tanggal_kembali, $estimasi_km, $estimasi_bbm, $pejabat_ttd, $current_user_id
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

                        // Note: Legacy pengguna_kendaraan upsert removed; views now derive from surat_tugas/peminjaman tables.

                        $msg = '<div class="alert alert-success">Surat tugas berhasil ditambahkan!</div>';
                        log_user_activity("Menambah surat tugas: $nomor_surat");
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
                $stmt = $conn->prepare("UPDATE surat_tugas SET nomor_surat=?, tanggal_surat=?, klasifikasi=?, lampiran=?, perihal=?, kepada_jabatan=?, kepada_tempat=?, dasar_a=?, dasar_b=?, berangkat_dari=?, waktu_berangkat=?, pejabat_ttd_jabatan=?, pejabat_ttd_sebagai=?, tembusan_1=?, tembusan_2=?, tembusan_3=?, tembusan_4=?, kendaraan_id=?, pengguna_id=?, tujuan=?, keperluan=?, tanggal_berangkat=?, tanggal_kembali=?, estimasi_km=?, estimasi_bbm=?, status=?, km_berangkat=?, km_kembali=?, bbm_terpakai=?, laporan_perjalanan=?, pejabat_ttd=?, updated_by=? WHERE id=?");
                    // Build dynamic types and params for UPDATE to avoid manual mismatch errors
                    $types_upd = str_repeat('s', 17) . 'ii' . str_repeat('s', 4) . 'ids' . 'ii' . 'd' . 'ss' . 'ii';
                    // Above constructed expecting: 17s, kendaraan_id i, pengguna_id i, 4s (tujuan-ke...kembali), estimasi_km i, estimasi_bbm d, status s,
                    // km_berangkat i, km_kembali i, bbm_terpakai d, laporan_perjalanan s, pejabat_ttd s, updated_by i, id i
                    $params_upd = array(
                        $nomor_surat, $tanggal_surat, $klasifikasi, $lampiran, $perihal, $kepada_jabatan, $kepada_tempat, $dasar_a, $dasar_b, $berangkat_dari, $waktu_berangkat, $pejabat_ttd_jabatan, $pejabat_ttd_sebagai, $tembusan_1, $tembusan_2, $tembusan_3, $tembusan_4, $kendaraan_id, $pengguna_id, $tujuan, $keperluan, $tanggal_berangkat, $tanggal_kembali, $estimasi_km, $estimasi_bbm, $status, $km_berangkat, $km_kembali, $bbm_terpakai, $laporan_perjalanan, $pejabat_ttd, $current_user_id, $surat_id
                    );
                    $bind_names_upd = array();
                    $bind_names_upd[] = & $types_upd;
                    foreach ($params_upd as $k => $v) {
                        $bind_names_upd[] = & $params_upd[$k];
                    }
                    call_user_func_array(array($stmt, 'bind_param'), $bind_names_upd);
                
                if ($stmt->execute()) {
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
                    if (in_array($status_l, ['disetujui', 'approved', 'approve', 'dalam perjalanan'])) {
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

                    $msg = '<div class="alert alert-success">Surat tugas berhasil diperbarui!</div>';
                    log_user_activity("Memperbarui surat tugas ID: $surat_id");
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
    } else {
        $msg = '<div class="alert alert-danger">Error: ' . $stmt->error . '</div>';
    }
    $stmt->close();
    $action = 'list';
}

// Get surat data for edit
$surat_data = null;
if ($action === 'edit' && $surat_id) {
    $stmt = $conn->prepare("SELECT * FROM surat_tugas WHERE id = ?");
    $stmt->bind_param('i', $surat_id);
    $stmt->execute();
    $result = $stmt->get_result();
    $surat_data = $result->fetch_assoc();
    $stmt->close();
}

// Get vehicles and users for dropdown
$vehicles = [];
$vehicles_query = "SELECT id, no_polisi, no_reg, merk, tipe FROM kendaraan ORDER BY COALESCE(no_reg, no_polisi)";
$vehicles_result = $conn->query($vehicles_query);
if ($vehicles_result) {
    $vehicles = $vehicles_result->fetch_all(MYSQLI_ASSOC);
}

$users = [];
$users_query = "SELECT p.id, p.nama_lengkap, p.pangkat, p.nrp_nip FROM pengguna p WHERE p.status_aktif = 'Aktif' ORDER BY p.nama_lengkap";
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
        <?php if ($can_crud): ?>
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
                    if ($status_filter !== 'all') {
                        $sf = $conn->real_escape_string($status_filter);
                        $where_clauses[] = "s.status = '" . $sf . "'";
                    }
                    $where_sql = $where_clauses ? 'WHERE ' . implode(' AND ', $where_clauses) : '';

                    // total count (respecting filters)
                    $countRes = $conn->query("SELECT COUNT(*) as total FROM surat_tugas s $where_sql");
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
                                    <th class="sortable" data-type="text">Pengguna <span class="sort-indicator"></span></th>
                                    <th class="sortable" data-type="text">Tujuan <span class="sort-indicator"></span></th>
                                    <th class="sortable" data-type="date">Tanggal Berangkat <span class="sort-indicator"></span></th>
                                    <th class="sortable" data-type="text">Status <span class="sort-indicator"></span></th>
                                    <?php if ($can_crud): ?>
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
                                        <input type="checkbox" class="select_row" data-id="<?= (int)$row['id'] ?>" aria-label="Pilih baris"> <?= $no++ ?>
                                    </td>
                                    <td>
                                        <strong><?= htmlspecialchars($row['nomor_surat']) ?></strong><br>
                                        <small class="text-muted"><?= date('d/m/Y', strtotime($row['tanggal_surat'])) ?></small>
                                    </td>
                                        <td>
                                        <strong><?= vehicle_label($row) ?></strong>
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
                                    <?php if ($can_crud): ?>
                                    <td class="text-center">
                                        <div class="btn-group" role="group">
                                            <a href="?page=surat_tugas&action=view&id=<?= $row['id'] ?>" 
                                               class="btn btn-sm btn-outline-info" title="Lihat Detail">
                                                <i class="fas fa-eye"></i>
                                            </a>
                                            <a href="?page=surat_tugas&action=edit&id=<?= $row['id'] ?>" 
                                               class="btn btn-sm btn-outline-primary" title="Edit">
                                                <i class="fas fa-edit"></i>
                                            </a>
                                            <a href="?page=surat_tugas&action=delete&id=<?= $row['id'] ?>" 
                                               class="btn btn-sm btn-outline-danger" title="Hapus"
                                               onclick="return confirm('Yakin ingin menghapus surat tugas ini?')">
                                                <i class="fas fa-trash"></i>
                                            </a>
                                        </div>
                                    </td>
                                    <?php endif; ?>
                                </tr>
                                <?php 
                                    endwhile;
                                else: 
                                ?>
                                <tr>
                                    <td colspan="<?= $can_crud ? '8' : '7' ?>" class="text-center text-muted py-4">
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
                                <h3 class="mb-1">TENTARA NASIONAL INDONESIA</h3>
                                <h4 class="mb-1">PUSAT INFORMASI PENGOLAH DATA</h4>
                                <p class="mb-0">Jl. Medan Merdeka Barat No. 13-14, Jakarta Pusat 10110</p>
                                <hr class="border-dark" style="height: 2px;">
                            </div>

                            <!-- Judul Surat -->
                            <div class="text-center mb-4">
                                <h4 class="text-decoration-underline">SURAT TUGAS</h4>
                                <p class="mb-0">Nomor: <?= htmlspecialchars($surat['nomor_surat']) ?></p>
                            </div>

                            <!-- Isi Surat -->
                            <div class="mb-4">
                                <p>Yang bertanda tangan di bawah ini:</p>
                                <table class="mb-3" style="width: 100%;">
                                    <tr>
                                        <td style="width: 150px;">Nama</td>
                                        <td style="width: 20px;">:</td>
                                        <td><?= htmlspecialchars($surat['pejabat_ttd'] ?: 'LAKSDA TNI ARIANTYO CONDROWIBOWO') ?></td>
                                    </tr>
                                    <tr>
                                        <td>Jabatan</td>
                                        <td>:</td>
                                        <td>Kepala Pusat Informasi Pengolah Data TNI</td>
                                    </tr>
                                </table>

                                <p>Memberikan tugas kepada:</p>
                                <table class="mb-3" style="width: 100%;">
                                    <tr>
                                        <td style="width: 150px;">Nama</td>
                                        <td style="width: 20px;">:</td>
                                        <td><?= htmlspecialchars(($surat['pangkat'] ? $surat['pangkat'] . ' ' : '') . $surat['nama_lengkap']) ?></td>
                                    </tr>
                                    <tr>
                                        <td>NRP/NIP</td>
                                        <td>:</td>
                                        <td><?= htmlspecialchars($surat['nrp_nip'] ?: '-') ?></td>
                                    </tr>
                                    <tr>
                                        <td>Kesatuan</td>
                                        <td>:</td>
                                        <td><?= htmlspecialchars($surat['nama_kesatuan'] ?: 'Pusinfolahta TNI') ?></td>
                                    </tr>
                                </table>

                                <p><strong>Untuk melaksanakan perjalanan dinas dengan ketentuan sebagai berikut:</strong></p>
                                <table class="mb-3" style="width: 100%;">
                                    <tr>
                                        <td style="width: 150px;">Tujuan</td>
                                        <td style="width: 20px;">:</td>
                                        <td><?= htmlspecialchars($surat['tujuan']) ?></td>
                                    </tr>
                                    <tr>
                                        <td>Keperluan</td>
                                        <td>:</td>
                                        <td><?= htmlspecialchars($surat['keperluan']) ?></td>
                                    </tr>
                                    <tr>
                                        <td>Tanggal Berangkat</td>
                                        <td>:</td>
                                        <td><?= date('d F Y', strtotime($surat['tanggal_berangkat'])) ?></td>
                                    </tr>
                                    <?php if ($surat['tanggal_kembali']): ?>
                                    <tr>
                                        <td>Tanggal Kembali</td>
                                        <td>:</td>
                                        <td><?= date('d F Y', strtotime($surat['tanggal_kembali'])) ?></td>
                                    </tr>
                                    <?php endif; ?>
                                    <tr>
                                        <td>Kendaraan</td>
                                        <td>:</td>
                                        <td>
                                            <?php 
                                                $primary = ($surat['no_reg'] ?? '') !== '' ? $surat['no_reg'] : ($surat['no_polisi'] ?? '-');
                                                $nopol = !empty($surat['no_polisi']) ? ' (Nopol: ' . $surat['no_polisi'] . ')' : '';
                                                echo htmlspecialchars($primary . $nopol . ' (' . $surat['merk'] . ' ' . $surat['tipe'] . ')');
                                            ?>
                                        </td>
                                    </tr>
                                    <?php if ($surat['estimasi_km']): ?>
                                    <tr>
                                        <td>Estimasi KM</td>
                                        <td>:</td>
                                        <td><?= number_format($surat['estimasi_km']) ?> KM</td>
                                    </tr>
                                    <?php endif; ?>
                                    <?php if ($surat['estimasi_bbm']): ?>
                                    <tr>
                                        <td>Estimasi BBM</td>
                                        <td>:</td>
                                        <td><?= number_format($surat['estimasi_bbm'], 2) ?> Liter</td>
                                    </tr>
                                    <?php endif; ?>
                                </table>

                                <p>Demikian surat tugas ini dibuat untuk dilaksanakan dengan penuh tanggung jawab.</p>
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
                                    <p class="mb-1">Kepala Pusat Informasi Pengolah Data TNI</p>
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

        <?php elseif ($action === 'add' || $action === 'edit'): ?>
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
                                                   class="form-control" required
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
                                            <input type="text" class="form-control me-2 dasar-item" value="Peraturan Panglima TNI Nomor 24 Tahun 2014 tentang Pengesahan Validasi Organisasi FKT dan;">
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
                                                    <option value="<?= $vehicle['id'] ?>" 
                                                            <?= (isset($surat_data) && $surat_data['kendaraan_id'] == $vehicle['id']) ? 'selected' : '' ?>>
                                                        <?= htmlspecialchars(($vehicle['no_reg'] ?? '') !== '' ? $vehicle['no_reg'] : ($vehicle['no_polisi'] ?? '-')) ?><?= !empty($vehicle['no_polisi']) ? ' (Nopol: ' . htmlspecialchars($vehicle['no_polisi']) . ')' : '' ?> - <?= htmlspecialchars($vehicle['merk'] . ' ' . $vehicle['tipe']) ?>
                                                    </option>
                                                <?php endforeach; ?>
                                            </select>
                                        </div>
                                    </div>
                                    <div class="col-md-6">
                                        <div class="form-group">
                                            <label for="pengguna_id">Pengguna *</label>
                                            <select name="pengguna_id" id="pengguna_id" class="form-control" required>
                                                <option value="">Pilih Pengguna</option>
                                                <?php foreach ($users as $user): ?>
                                                    <option value="<?= $user['id'] ?>" 
                                                            <?= (isset($surat_data) && $surat_data['pengguna_id'] == $user['id']) ? 'selected' : '' ?>>
                                                        <?= htmlspecialchars(($user['pangkat'] ? $user['pangkat'] . ' ' : '') . $user['nama_lengkap'] . ' (' . $user['nrp_nip'] . ')') ?>
                                                    </option>
                                                <?php endforeach; ?>
                                            </select>
                                        </div>
                                    </div>
                                </div>

                                <div class="row">
                                    <div class="col-md-6">
                                        <div class="form-group">
                                            <label for="tujuan">Tujuan *</label>
                                            <input type="text" name="tujuan" id="tujuan" 
                                                           class="form-control" required
                                                           value="<?= htmlspecialchars($surat_data['tujuan'] ?? '') ?>"
                                                           placeholder="Contoh: Wisma Majestic Cisarua Bogor">
                                        </div>
                                    </div>
                                    <div class="col-md-6">
                                        <div class="form-group">
                                            <label for="berangkat_dari">Berangkat Dari *</label>
                                            <input type="text" name="berangkat_dari" id="berangkat_dari" 
                                                   class="form-control" required
                                                   value="<?= htmlspecialchars($surat_data['berangkat_dari'] ?? 'Pusinfolahta TNI') ?>"
                                                   placeholder="Contoh: Pusinfolahta TNI">
                                        </div>
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
                                                   value="<?= htmlspecialchars($surat_data['pejabat_ttd_jabatan'] ?? 'a.n Kepala Pusinfolahta TNI') ?>"
                                                   placeholder="Contoh: a.n Kepala Pusinfolahta TNI">
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
                                    $tembusan_items = ['Kapusinfolahta TNI', 'Asops Denma Mabes TNI', 'Dansetang Denma Mabes TNI', 'Dansakdok Denma Mabes TNI'];
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
