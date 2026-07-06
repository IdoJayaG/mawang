<?php
require_once 'includes/auth.php';

$current_role = get_current_role();
$current_user_id = get_current_user_id();

// Role-based access control
if ($current_role === 'guest') {
    header('Location: index.php?page=kendaraan_publik');
    exit;
}

$can_crud = can_operate() || in_array($current_role, ['user', 'driver'], true);
$can_view = is_logged_in();

$action = $_GET['action'] ?? 'list';
$log_id = $_GET['id'] ?? null;
$kendaraan_id = $_GET['kendaraan_id'] ?? null;
$msg = '';
    

// AJAX: return last km + first km for a vehicle (for client-side hint + total distance)
if ($action === 'get_prev_km') {
    header('Content-Type: application/json');
    $vid = (int)($_GET['kendaraan_id'] ?? 0);
    $ex  = (int)($_GET['exclude_id']   ?? 0);
    if ($vid > 0) {
        if ($ex > 0) {
            $stmt = $mysqli->prepare("SELECT MAX(km_saat_isi) AS prev_km, MIN(km_saat_isi) AS first_km, MAX(tanggal_isi) AS last_date FROM log_bahan_bakar WHERE kendaraan_id = ? AND id != ? AND km_saat_isi IS NOT NULL");
            $stmt->bind_param('ii', $vid, $ex);
        } else {
            $stmt = $mysqli->prepare("SELECT MAX(km_saat_isi) AS prev_km, MIN(km_saat_isi) AS first_km, MAX(tanggal_isi) AS last_date FROM log_bahan_bakar WHERE kendaraan_id = ? AND km_saat_isi IS NOT NULL");
            $stmt->bind_param('i', $vid);
        }
        $stmt->execute();
        $row = $stmt->get_result()->fetch_assoc();
        $stmt->close();
        $prev  = $row['prev_km']  !== null ? (int)$row['prev_km']  : null;
        $first = $row['first_km'] !== null ? (int)$row['first_km'] : null;
        $total_km = ($prev !== null && $first !== null && $prev > $first) ? ($prev - $first) : null;
        echo json_encode(['prev_km' => $prev, 'first_km' => $first, 'total_km' => $total_km, 'last_date' => $row['last_date'] ?? null]);
    } else {
        echo json_encode(['prev_km' => null, 'first_km' => null, 'total_km' => null, 'last_date' => null]);
    }
    exit;
}

// AJAX: cari surat tugas yang sedang berjalan (Dalam Perjalanan) untuk kendaraan ini,
// supaya log BBM yang ditambahkan bisa otomatis dikaitkan ke trip tsb.
if ($action === 'get_active_surat') {
    header('Content-Type: application/json');
    $vid = (int)($_GET['kendaraan_id'] ?? 0);
    if ($vid <= 0 || !db_table_exists('surat_tugas')) {
        echo json_encode(['surat_tugas_id' => null, 'nomor_surat' => null]);
        exit;
    }
    // Prioritaskan trip milik driver/user yang sedang login; kalau admin/operator, ambil trip aktif manapun untuk kendaraan ini.
    if (in_array($current_role, ['user', 'driver'], true)) {
        $stmt = $mysqli->prepare("SELECT id, nomor_surat FROM surat_tugas WHERE kendaraan_id = ? AND pengguna_id = ? AND status = 'Dalam Perjalanan' ORDER BY tanggal_berangkat DESC LIMIT 1");
        $stmt->bind_param('ii', $vid, $current_user_id);
    } else {
        $stmt = $mysqli->prepare("SELECT id, nomor_surat FROM surat_tugas WHERE kendaraan_id = ? AND status = 'Dalam Perjalanan' ORDER BY tanggal_berangkat DESC LIMIT 1");
        $stmt->bind_param('i', $vid);
    }
    $stmt->execute();
    $row = $stmt->get_result()->fetch_assoc();
    $stmt->close();
    echo json_encode([
        'surat_tugas_id' => $row['id'] ?? null,
        'nomor_surat'    => $row['nomor_surat'] ?? null,
    ]);
    exit;
}

$edit_data = null;
if ($action === 'edit' && $can_crud && $log_id) {
    $stmt_edit = $mysqli->prepare("SELECT * FROM log_bahan_bakar WHERE id = ? LIMIT 1");
    if ($stmt_edit) {
        $stmt_edit->bind_param('i', $log_id);
        $stmt_edit->execute();
        $edit_data = $stmt_edit->get_result()->fetch_assoc();
        $stmt_edit->close();
    }
    if (!$edit_data) {
        $msg = '<div class="alert alert-danger">Data log BBM tidak ditemukan.</div>';
        $action = 'list';
    } elseif (!can_operate() && !can_access_vehicle((int)$edit_data['kendaraan_id'])) {
        $msg = '<div class="alert alert-danger">Anda tidak memiliki akses untuk mengedit log BBM ini.</div>';
        $action = 'list';
        $edit_data = null;
    }
}

// Time helpers for window filtering
$now = date('Y-m-d H:i:s');
$today = date('Y-m-d');

// Detect optional columns to keep queries safe if migration removed them
$has_harga = function_exists('db_table_columns') && in_array('harga_per_liter', (array)db_table_columns('log_bahan_bakar'), true);
$has_metode = function_exists('db_table_columns') && in_array('metode_bayar', (array)db_table_columns('log_bahan_bakar'), true);
$has_surat_link = function_exists('db_table_columns') && in_array('surat_tugas_id', (array)db_table_columns('log_bahan_bakar'), true);

// Recompute surat_tugas.bbm_terpakai dari total log_bahan_bakar yang dikaitkan ke trip tsb.
function recompute_surat_bbm_terpakai(mysqli $mysqli, int $surat_tugas_id): void {
    if ($surat_tugas_id <= 0) return;
    $stmt = $mysqli->prepare("UPDATE surat_tugas SET bbm_terpakai = (SELECT COALESCE(SUM(jumlah_liter),0) FROM log_bahan_bakar WHERE surat_tugas_id = ?) WHERE id = ?");
    if ($stmt) {
        $stmt->bind_param('ii', $surat_tugas_id, $surat_tugas_id);
        $stmt->execute();
        $stmt->close();
    }
}

// Precompute AVG selector to avoid referencing removed column
$avg_col = $has_harga ? 'AVG(lb.harga_per_liter) as rata_harga,' : 'NULL as rata_harga,';

// Handle file upload
function uploadPhoto($file, $prefix = '') {
    $upload_dir = 'uploads/bbm/';
    $allowed_types = ['image/jpeg', 'image/jpg', 'image/png'];
    $max_size = 5 * 1024 * 1024; // 5MB
    
    if (!isset($file['tmp_name']) || empty($file['tmp_name'])) {
        return null;
    }
    
    if (!in_array($file['type'], $allowed_types)) {
        throw new Exception('Format file tidak didukung. Gunakan JPG, JPEG, atau PNG.');
    }
    
    if ($file['size'] > $max_size) {
        throw new Exception('Ukuran file terlalu besar. Maksimal 5MB.');
    }
    
    $extension = pathinfo($file['name'], PATHINFO_EXTENSION);
    $filename = $prefix . '_' . uniqid() . '_' . date('YmdHis') . '.' . $extension;
    $filepath = $upload_dir . $filename;
    
    if (!move_uploaded_file($file['tmp_name'], $filepath)) {
        throw new Exception('Gagal mengupload file.');
    }
    
    return $filename;
}

// Handle form submissions
if ($_POST) {
    if (!validate_csrf_token($_POST['csrf_token'] ?? '')) {
        $msg = '<div class="alert alert-danger">Token keamanan tidak valid!</div>';
    } else {
        if ($action === 'add' && $can_crud) {
            try {
                $kendaraan_id = (int)$_POST['kendaraan_id'];
                if (!can_operate() && !can_access_vehicle($kendaraan_id)) {
                    throw new Exception('Anda hanya dapat mengelola BBM untuk kendaraan yang menjadi tanggung jawab Anda.');
                }
                $tanggal_isi = $_POST['tanggal_isi'];
                $jumlah_liter = (float)$_POST['jumlah_liter'];
                $km_saat_isi = $_POST['km_saat_isi'] ? (int)$_POST['km_saat_isi'] : null;
                $spbu = trim($_POST['spbu']);
                $jenis_bbm = $_POST['jenis_bbm'];
                $keterangan = trim($_POST['keterangan']);
                $jarak_traccar_km = isset($_POST['jarak_traccar_km']) && $_POST['jarak_traccar_km'] !== '' ? (float)$_POST['jarak_traccar_km'] : null;

                // Trip aktif yang dipilih otomatis di form (jika ada) — dipakai agar BBM aktual
                // surat tugas terisi dari input driver di sini, bukan input manual terpisah.
                $surat_tugas_id = null;
                if ($has_surat_link && !empty($_POST['surat_tugas_id'])) {
                    $st_candidate = (int)$_POST['surat_tugas_id'];
                    $chk_st = $mysqli->prepare("SELECT id FROM surat_tugas WHERE id = ? AND kendaraan_id = ? AND status = 'Dalam Perjalanan' LIMIT 1");
                    if ($chk_st) {
                        $chk_st->bind_param('ii', $st_candidate, $kendaraan_id);
                        $chk_st->execute();
                        if ($chk_st->get_result()->fetch_assoc()) { $surat_tugas_id = $st_candidate; }
                        $chk_st->close();
                    }
                }

                // Validate km is not smaller than previous entry for this vehicle
                if ($km_saat_isi !== null) {
                    $chk = $mysqli->prepare("SELECT MAX(km_saat_isi) AS max_km FROM log_bahan_bakar WHERE kendaraan_id = ? AND km_saat_isi IS NOT NULL");
                    $chk->bind_param('i', $kendaraan_id);
                    $chk->execute();
                    $prev_km_val = $chk->get_result()->fetch_assoc()['max_km'];
                    $chk->close();
                    if ($prev_km_val !== null && $km_saat_isi < (int)$prev_km_val) {
                        throw new Exception("KM tidak boleh lebih kecil dari KM sebelumnya (" . number_format((int)$prev_km_val) . " km).");
                    }
                }

                // biaya/harga dihapus dari input; nilai disimpan terpisah jika perlu melalui migrasi
                
                // Upload photos
                $foto_sebelum = null;
                $foto_sesudah = null;
                $foto_odometer = null;
                
                if (!empty($_FILES['foto_sebelum_isi']['tmp_name'])) {
                    $foto_sebelum = uploadPhoto($_FILES['foto_sebelum_isi'], 'sebelum');
                }
                
                if (!empty($_FILES['foto_sesudah_isi']['tmp_name'])) {
                    $foto_sesudah = uploadPhoto($_FILES['foto_sesudah_isi'], 'sesudah');
                }
                
                if (!empty($_FILES['foto_odometer']['tmp_name'])) {
                    $foto_odometer = uploadPhoto($_FILES['foto_odometer'], 'odometer');
                }
                
                if ($has_surat_link) {
                    $stmt = $mysqli->prepare("INSERT INTO log_bahan_bakar (kendaraan_id, tanggal_isi, jumlah_liter, km_saat_isi, jarak_traccar_km, spbu, jenis_bahan_bakar, user_id, foto_sebelum_isi, foto_sesudah_isi, foto_odometer, keterangan, surat_tugas_id) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
                    $stmt->bind_param('isdidissssssi', $kendaraan_id, $tanggal_isi, $jumlah_liter, $km_saat_isi, $jarak_traccar_km, $spbu, $jenis_bbm, $current_user_id, $foto_sebelum, $foto_sesudah, $foto_odometer, $keterangan, $surat_tugas_id);
                } else {
                    $stmt = $mysqli->prepare("INSERT INTO log_bahan_bakar (kendaraan_id, tanggal_isi, jumlah_liter, km_saat_isi, jarak_traccar_km, spbu, jenis_bahan_bakar, user_id, foto_sebelum_isi, foto_sesudah_isi, foto_odometer, keterangan) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
                    $stmt->bind_param('isdidissssss', $kendaraan_id, $tanggal_isi, $jumlah_liter, $km_saat_isi, $jarak_traccar_km, $spbu, $jenis_bbm, $current_user_id, $foto_sebelum, $foto_sesudah, $foto_odometer, $keterangan);
                }

                if ($stmt->execute()) {
                    $new_log_id = $mysqli->insert_id;

                    // BBM aktual surat tugas = total isi BBM yang dikaitkan ke trip ini.
                    if ($surat_tugas_id !== null) {
                        recompute_surat_bbm_terpakai($mysqli, $surat_tugas_id);
                    }

                    // ── Post-INSERT: hitung kml_this_fill & anomali_flag ──────
                    $has_kml_col = function_exists('db_table_columns')
                        && in_array('kml_this_fill', (array)db_table_columns('log_bahan_bakar'), true);

                    if ($has_kml_col && $km_saat_isi !== null && $jumlah_liter > 0) {
                        // Ambil km_saat_isi dari isian sebelumnya untuk kendaraan ini
                        $stmt_prev_km = $mysqli->prepare(
                            "SELECT km_saat_isi FROM log_bahan_bakar
                             WHERE kendaraan_id = ? AND id != ? AND km_saat_isi IS NOT NULL
                             ORDER BY tanggal_isi DESC, id DESC LIMIT 1"
                        );
                        $kml_this = null;
                        $anomali  = 0;
                        if ($stmt_prev_km) {
                            $stmt_prev_km->bind_param('ii', $kendaraan_id, $new_log_id);
                            $stmt_prev_km->execute();
                            $prev_row = $stmt_prev_km->get_result()->fetch_assoc();
                            $stmt_prev_km->close();

                            if ($prev_row && $prev_row['km_saat_isi'] !== null) {
                                $km_diff = (float)$km_saat_isi - (float)$prev_row['km_saat_isi'];
                                if ($km_diff >= 1 && $km_diff <= 2000) {
                                    $kml_this = round($km_diff / $jumlah_liter, 2);

                                    // Ambil rata-rata historis dari cache
                                    $avg_kml = null;
                                    $tbl_cache = $mysqli->query("SHOW TABLES LIKE 'vehicle_efficiency_cache'");
                                    $_tbl_cache_ok = $tbl_cache && $tbl_cache->num_rows > 0;
                                    if ($tbl_cache) $tbl_cache->free();
                                    if ($_tbl_cache_ok) {
                                        $stmt_avg = $mysqli->prepare(
                                            "SELECT avg_kml FROM vehicle_efficiency_cache WHERE kendaraan_id = ? LIMIT 1"
                                        );
                                        if ($stmt_avg) {
                                            $stmt_avg->bind_param('i', $kendaraan_id);
                                            $stmt_avg->execute();
                                            $avg_row = $stmt_avg->get_result()->fetch_assoc();
                                            $stmt_avg->close();
                                            $avg_kml = isset($avg_row['avg_kml']) ? (float)$avg_row['avg_kml'] : null;
                                        }
                                    }

                                    // Flag anomali jika deviasi >30% dari rata-rata historis
                                    if ($avg_kml !== null && $avg_kml > 0) {
                                        $dev = abs($kml_this - $avg_kml) / $avg_kml;
                                        $anomali = $dev > 0.30 ? 1 : 0;
                                    }
                                }
                            }
                        }

                        // Tulis kml_this_fill & anomali_flag ke baris yang baru diinsert
                        $stmt_upd = $mysqli->prepare(
                            "UPDATE log_bahan_bakar SET kml_this_fill = ?, anomali_flag = ? WHERE id = ?"
                        );
                        if ($stmt_upd) {
                            $stmt_upd->bind_param('dii', $kml_this, $anomali, $new_log_id);
                            $stmt_upd->execute();
                            $stmt_upd->close();
                        }
                    }

                    // ── Invalidate cache kendaraan ini agar ter-refresh ───────
                    $tbl_cache2 = $mysqli->query("SHOW TABLES LIKE 'vehicle_efficiency_cache'");
                    $_tbl_cache2_ok = $tbl_cache2 && $tbl_cache2->num_rows > 0;
                    if ($tbl_cache2) $tbl_cache2->free();
                    if ($_tbl_cache2_ok) {
                        // Set last_computed ke masa lalu agar endpoint vehicle_efficiency.php recompute
                        $stmt_inv = $mysqli->prepare(
                            "UPDATE vehicle_efficiency_cache
                             SET last_computed = DATE_SUB(NOW(), INTERVAL 7 HOUR)
                             WHERE kendaraan_id = ?"
                        );
                        if ($stmt_inv) {
                            $stmt_inv->bind_param('i', $kendaraan_id);
                            $stmt_inv->execute();
                            $stmt_inv->close();
                        }
                    }

                    // Catat aktivitas dan kembali ke halaman daftar log BBM
                    log_activity("ADD_BBM_LOG", "Menambah log BBM untuk kendaraan ID $kendaraan_id: " . $jumlah_liter . " liter");
                    header('Location: index.php?page=log_bahan_bakar');
                    exit;
                } else {
                    $msg = '<div class="alert alert-danger">Error: ' . $stmt->error . '</div>';
                }
                $stmt->close();
                
            } catch (Exception $e) {
                $msg = '<div class="alert alert-danger">Error: ' . $e->getMessage() . '</div>';
            }
        } elseif ($action === 'edit' && $can_crud && $log_id) {
            try {
                $kendaraan_id = (int)$_POST['kendaraan_id'];
                if (!can_operate() && !can_access_vehicle($kendaraan_id)) {
                    throw new Exception('Anda hanya dapat mengelola BBM untuk kendaraan yang menjadi tanggung jawab Anda.');
                }
                $tanggal_isi = $_POST['tanggal_isi'];
                $jumlah_liter = (float)$_POST['jumlah_liter'];
                $km_saat_isi = $_POST['km_saat_isi'] ? (int)$_POST['km_saat_isi'] : null;
                $spbu = trim($_POST['spbu']);
                $jenis_bbm = $_POST['jenis_bbm'];
                $keterangan = trim($_POST['keterangan']);
                $jarak_traccar_km = isset($_POST['jarak_traccar_km']) && $_POST['jarak_traccar_km'] !== '' ? (float)$_POST['jarak_traccar_km'] : null;

                // Validate km is not smaller than any previous entry (excluding current record)
                if ($km_saat_isi !== null) {
                    $chk = $mysqli->prepare("SELECT MAX(km_saat_isi) AS max_km FROM log_bahan_bakar WHERE kendaraan_id = ? AND id != ? AND km_saat_isi IS NOT NULL");
                    $chk->bind_param('ii', $kendaraan_id, $log_id);
                    $chk->execute();
                    $prev_km_val = $chk->get_result()->fetch_assoc()['max_km'];
                    $chk->close();
                    if ($prev_km_val !== null && $km_saat_isi < (int)$prev_km_val) {
                        throw new Exception("KM tidak boleh lebih kecil dari KM sebelumnya (" . number_format((int)$prev_km_val) . " km).");
                    }
                }

                $stmt = $mysqli->prepare("UPDATE log_bahan_bakar SET kendaraan_id=?, tanggal_isi=?, jumlah_liter=?, km_saat_isi=?, spbu=?, jenis_bahan_bakar=?, keterangan=? WHERE id=?");
                $stmt->bind_param('isdisssi', $kendaraan_id, $tanggal_isi, $jumlah_liter, $km_saat_isi, $spbu, $jenis_bbm, $keterangan, $log_id);
                    // include traccar distance if column exists
                    $has_col = function_exists('db_table_columns') && in_array('jarak_traccar_km', (array)db_table_columns('log_bahan_bakar'), true);
                    if ($has_col) {
                        $stmt = $mysqli->prepare("UPDATE log_bahan_bakar SET kendaraan_id=?, tanggal_isi=?, jumlah_liter=?, km_saat_isi=?, jarak_traccar_km=?, spbu=?, jenis_bahan_bakar=?, keterangan=? WHERE id=?");
                        $stmt->bind_param('isdidsssi', $kendaraan_id, $tanggal_isi, $jumlah_liter, $km_saat_isi, $jarak_traccar_km, $spbu, $jenis_bbm, $keterangan, $log_id);
                    }

                if ($stmt->execute()) {
                    // Jika log ini terkait surat tugas, hitung ulang BBM aktual trip (jumlah_liter mungkin berubah).
                    if ($has_surat_link) {
                        $stmt_link = $mysqli->prepare("SELECT surat_tugas_id FROM log_bahan_bakar WHERE id = ? LIMIT 1");
                        if ($stmt_link) {
                            $stmt_link->bind_param('i', $log_id);
                            $stmt_link->execute();
                            $link_row = $stmt_link->get_result()->fetch_assoc();
                            $stmt_link->close();
                            if (!empty($link_row['surat_tugas_id'])) {
                                recompute_surat_bbm_terpakai($mysqli, (int)$link_row['surat_tugas_id']);
                            }
                        }
                    }
                    log_activity("EDIT_BBM_LOG", "Memperbarui log BBM ID $log_id untuk kendaraan ID $kendaraan_id");
                    header('Location: index.php?page=log_bahan_bakar_detail&kendaraan_id=' . $kendaraan_id);
                    exit;
                } else {
                    $msg = '<div class="alert alert-danger">Error: ' . $stmt->error . '</div>';
                }
                $stmt->close();
            } catch (Exception $e) {
                $msg = '<div class="alert alert-danger">Error: ' . $e->getMessage() . '</div>';
            }
        }
    }
}

// Get vehicle data based on user role
if (in_array($current_role, ['user', 'driver'], true)) {
    // User hanya bisa melihat kendaraan yang ditugaskan via surat_tugas (aktif window) atau dipinjam (aktif window)
    // Optional: juga tampilkan peminjaman_terjadwal (approved window) bila tabel ada
    $subqueries = [];
    $types_v = '';
    $params_v = [];

    // surat_tugas window (date-only)
    $subqueries[] = "SELECT s.kendaraan_id FROM surat_tugas s WHERE s.pengguna_id = ? AND s.status IN ('Disetujui','Dalam Perjalanan') AND s.tanggal_berangkat <= ? AND (s.tanggal_kembali IS NULL OR s.tanggal_kembali >= ?)";
    $types_v .= 'iss';
    array_push($params_v, $current_user_id, $today, $today);

    // peminjaman_kendaraan active (datetime)
    $subqueries[] = "SELECT p.kendaraan_id FROM peminjaman_kendaraan p WHERE p.peminjam_id = ? AND LOWER(p.status) IN ('approved','ongoing') AND p.tanggal_mulai <= ? AND p.tanggal_selesai >= ?";
    $types_v .= 'iss';
    array_push($params_v, $current_user_id, $now, $now);

    // peminjaman_terjadwal approved (datetime) if table exists
    $has_pt_tbl = false;
    try {
        $t = $mysqli->query("SHOW TABLES LIKE 'peminjaman_terjadwal'");
        $has_pt_tbl = $t && $t->num_rows > 0; if ($t) $t->free_result();
    } catch (mysqli_sql_exception $e) { $has_pt_tbl = false; }
    if (!empty($has_pt_tbl)) {
        $subqueries[] = "SELECT pt.kendaraan_id FROM peminjaman_terjadwal pt WHERE pt.pemohon_id = ? AND LOWER(pt.status) = 'approved' AND pt.tanggal_mulai <= ? AND (pt.tanggal_selesai IS NULL OR pt.tanggal_selesai >= ?)";
        $types_v .= 'iss';
        array_push($params_v, $current_user_id, $now, $now);
    }

    // include vehicles explicitly assigned via kendaraan.pengguna_id if column exists
    if (function_exists('db_table_columns') && in_array('pengguna_id', db_table_columns('kendaraan') ?: [], true)) {
        $subqueries[] = "SELECT id FROM kendaraan WHERE pengguna_id = ?";
        $types_v .= 'i';
        array_push($params_v, $current_user_id);
    }

    $in_sql = implode(' UNION ', $subqueries);
    $sql_v = "SELECT DISTINCT k.* FROM kendaraan k WHERE k.id IN ($in_sql) ORDER BY k.no_reg, k.no_polisi";
    $kendaraan_stmt = $mysqli->prepare($sql_v);
    if ($kendaraan_stmt) {
        // Bind dynamic params
        $bind = [];
        $bind[] = & $types_v;
        for ($i = 0; $i < count($params_v); $i++) { $bind[] = & $params_v[$i]; }
        call_user_func_array([$kendaraan_stmt, 'bind_param'], $bind);
    }
} else {
    // Admin-like roles bisa melihat semua kendaraan
    $kendaraan_stmt = $mysqli->prepare("SELECT * FROM kendaraan ORDER BY no_reg, no_polisi");
}
$kendaraan_stmt->execute();
$kendaraan_list = $kendaraan_stmt->get_result();

// Get BBM logs - Grouped by vehicle
$page = isset($_GET['page_num']) ? max(1, intval($_GET['page_num'])) : 1;
$limit = 10;
$offset = ($page - 1) * $limit;

if (in_array($current_role, ['user', 'driver'], true)) {
    // User hanya melihat kendaraan dari surat_tugas aktif / peminjaman aktif (dan jadwal approved jika ada)
    $subq = [];
    $types_l = '';
    $params_l = [];

    $subq[] = "SELECT s.kendaraan_id FROM surat_tugas s WHERE s.pengguna_id = ? AND s.status IN ('Disetujui','Dalam Perjalanan') AND s.tanggal_berangkat <= ? AND (s.tanggal_kembali IS NULL OR s.tanggal_kembali >= ?)";
    $types_l .= 'iss';
    array_push($params_l, $current_user_id, $today, $today);

    $subq[] = "SELECT p.kendaraan_id FROM peminjaman_kendaraan p WHERE p.peminjam_id = ? AND LOWER(p.status) IN ('approved','ongoing') AND p.tanggal_mulai <= ? AND p.tanggal_selesai >= ?";
    $types_l .= 'iss';
    array_push($params_l, $current_user_id, $now, $now);

    $has_pt_tbl = false;
    try {
        $t = $mysqli->query("SHOW TABLES LIKE 'peminjaman_terjadwal'");
        $has_pt_tbl = $t && $t->num_rows > 0; if ($t) $t->free_result();
    } catch (mysqli_sql_exception $e) { $has_pt_tbl = false; }
    if (!empty($has_pt_tbl)) {
        $subq[] = "SELECT pt.kendaraan_id FROM peminjaman_terjadwal pt WHERE pt.pemohon_id = ? AND LOWER(pt.status) = 'approved' AND pt.tanggal_mulai <= ? AND (pt.tanggal_selesai IS NULL OR pt.tanggal_selesai >= ?)";
        $types_l .= 'iss';
        array_push($params_l, $current_user_id, $now, $now);
    }

    // include vehicles explicitly assigned via kendaraan.pengguna_id if column exists
    if (function_exists('db_table_columns') && in_array('pengguna_id', db_table_columns('kendaraan') ?: [], true)) {
        $subq[] = "SELECT id FROM kendaraan WHERE pengguna_id = ?";
        $types_l .= 'i';
        array_push($params_l, $current_user_id);
    }

    $in_sql = implode(' UNION ', $subq);
    $sql_l = "
        SELECT 
            k.id as kendaraan_id,
            k.no_reg,
            k.no_polisi,
            k.merk,
            k.tipe,
            k.foto,
            COUNT(lb.id) as total_pengisian,
            SUM(lb.jumlah_liter) as total_liter,
            SUM(lb.jarak_traccar_km) as total_jarak_traccar,
            MAX(lb.tanggal_isi) as pengisian_terakhir,
            " . $avg_col . "
            MAX(lb.km_saat_isi) as km_terakhir,
            MIN(lb.km_saat_isi) as km_pertama
        FROM kendaraan k
        LEFT JOIN log_bahan_bakar lb ON k.id = lb.kendaraan_id
        WHERE k.id IN ($in_sql)
        GROUP BY k.id, k.no_reg, k.no_polisi, k.merk, k.tipe, k.foto
        ORDER BY pengisian_terakhir DESC, k.no_reg, k.no_polisi
        LIMIT ? OFFSET ?
    ";
    $log_stmt = $mysqli->prepare($sql_l);
    if ($log_stmt) {
        $types_lio = $types_l . 'ii';
        $params_lio = array_merge($params_l, [$limit, $offset]);
        $bind = [];
        $bind[] = & $types_lio;
        for ($i = 0; $i < count($params_lio); $i++) { $bind[] = & $params_lio[$i]; }
        call_user_func_array([$log_stmt, 'bind_param'], $bind);
    }
    
    // (user/driver branch) $log_stmt already prepared above as $sql_l with dynamic bindings
} else {
    // Admin-like roles melihat semua kendaraan dengan summary BBM
    $log_sql = "
        SELECT 
            k.id as kendaraan_id,
            k.no_reg,
            k.no_polisi,
            k.merk,
            k.tipe,
            k.foto,
            COUNT(lb.id) as total_pengisian,
            SUM(lb.jumlah_liter) as total_liter,
            SUM(lb.jarak_traccar_km) as total_jarak_traccar,
            MAX(lb.tanggal_isi) as pengisian_terakhir,
            " . $avg_col . "
            MAX(lb.km_saat_isi) as km_terakhir,
            MIN(lb.km_saat_isi) as km_pertama
        FROM kendaraan k
        LEFT JOIN log_bahan_bakar lb ON k.id = lb.kendaraan_id
        GROUP BY k.id, k.no_reg, k.no_polisi, k.merk, k.tipe, k.foto
        ORDER BY pengisian_terakhir DESC, k.no_reg, k.no_polisi
        LIMIT ? OFFSET ?
    ";
    $log_stmt = $mysqli->prepare($log_sql);
    $log_stmt->bind_param('ii', $limit, $offset);
}

$log_stmt->execute();
$log_list = $log_stmt->get_result();

// Get total count for pagination (count vehicles shown)
if (in_array($current_role, ['user', 'driver'], true)) {
    // Count distinct vehicles in the same union-filter set
    $subq = [];
    $types_c = '';
    $params_c = [];

    $subq[] = "SELECT s.kendaraan_id FROM surat_tugas s WHERE s.pengguna_id = ? AND s.status IN ('Disetujui','Dalam Perjalanan') AND s.tanggal_berangkat <= ? AND (s.tanggal_kembali IS NULL OR s.tanggal_kembali >= ?)";
    $types_c .= 'iss';
    array_push($params_c, $current_user_id, $today, $today);

    $subq[] = "SELECT p.kendaraan_id FROM peminjaman_kendaraan p WHERE p.peminjam_id = ? AND LOWER(p.status) IN ('approved','ongoing') AND p.tanggal_mulai <= ? AND p.tanggal_selesai >= ?";
    $types_c .= 'iss';
    array_push($params_c, $current_user_id, $now, $now);

    $has_pt_tbl = false;
    try {
        $t = $mysqli->query("SHOW TABLES LIKE 'peminjaman_terjadwal'");
        $has_pt_tbl = $t && $t->num_rows > 0; if ($t) $t->free_result();
    } catch (mysqli_sql_exception $e) { $has_pt_tbl = false; }
    if (!empty($has_pt_tbl)) {
        $subq[] = "SELECT pt.kendaraan_id FROM peminjaman_terjadwal pt WHERE pt.pemohon_id = ? AND LOWER(pt.status) = 'approved' AND pt.tanggal_mulai <= ? AND (pt.tanggal_selesai IS NULL OR pt.tanggal_selesai >= ?)";
        $types_c .= 'iss';
        array_push($params_c, $current_user_id, $now, $now);
    }

    $in_sql = implode(' UNION ', $subq);
    $sql_c = "SELECT COUNT(DISTINCT k.id) as total FROM kendaraan k WHERE k.id IN ($in_sql)";
    $count_stmt = $mysqli->prepare($sql_c);
    if ($count_stmt) {
        $bind = [];
        $bind[] = & $types_c;
        for ($i = 0; $i < count($params_c); $i++) { $bind[] = & $params_c[$i]; }
        call_user_func_array([$count_stmt, 'bind_param'], $bind);
    }
} else {
    $count_stmt = $mysqli->prepare("SELECT COUNT(*) as total FROM kendaraan");
}
$total_records = 0;
if ($count_stmt) {
    $count_stmt->execute();
    $total_records = (int)$count_stmt->get_result()->fetch_assoc()['total'];
    $count_stmt->close();
}
$total_pages = $limit > 0 ? (int)ceil($total_records / $limit) : 1;

// ── Rekap BBM: query hanya saat view=rekap ──────────────────────────────────
$view              = $_GET['view']             ?? 'list';
$rekap_bulan       = $_GET['bulan']            ?? date('Y-m');
$rekap_kend_filter = (int)($_GET['rekap_kend'] ?? 0);
$rekap_anomali_only = !empty($_GET['anomali_only']);
if (!preg_match('/^\d{4}-\d{2}$/', $rekap_bulan)) { $rekap_bulan = date('Y-m'); }
$rekap_awal  = $rekap_bulan . '-01';
$rekap_akhir = date('Y-m-t', strtotime($rekap_awal));

$rekap_rows      = [];
$rekap_eff_map   = [];
$rekap_anom_fills = [];
$grand = ['estimasi'=>0,'terpakai'=>0,'fill'=>0,'est_km'=>0,'actual_km'=>0,'anomali'=>0,'trips'=>0];

if ($view === 'rekap' && $action === 'list') {
    $has_anomali_col = function_exists('db_table_columns')
        && in_array('bbm_anomali', (array)db_table_columns('surat_tugas'), true);
    $r_tbl = $mysqli->query("SHOW TABLES LIKE 'vehicle_efficiency_cache'");
    $has_eff_cache = $r_tbl && $r_tbl->num_rows > 0;
    if ($r_tbl) $r_tbl->free();

    $anomali_col = $has_anomali_col ? "COUNT(CASE WHEN st.bbm_anomali=1 THEN 1 END)" : "0";
    $w_kend = $rekap_kend_filter > 0 ? "AND k.id = $rekap_kend_filter" : '';
    $w_role = '';
    if (in_array($current_role, ['user','driver'], true)) {
        $w_role = "AND (EXISTS (SELECT 1 FROM surat_tugas sx WHERE sx.kendaraan_id=k.id AND sx.pengguna_id=$current_user_id)
                    OR  EXISTS (SELECT 1 FROM log_bahan_bakar lx WHERE lx.kendaraan_id=k.id AND lx.user_id=$current_user_id))";
    }

    $sql_r = "SELECT k.id, k.no_reg, k.no_polisi,
                     TRIM(CONCAT(k.merk,' ',k.tipe)) AS nama_kendaraan,
                     COUNT(DISTINCT st.id)                                    AS total_trips,
                     COALESCE(SUM(st.estimasi_bbm),0)                        AS total_estimasi,
                     COALESCE(SUM(st.bbm_terpakai),0)                        AS total_terpakai,
                     COALESCE(SUM(st.estimasi_km),0)                         AS total_est_km,
                     COALESCE(SUM(st.km_kembali - st.km_berangkat),0)        AS total_actual_km,
                     {$anomali_col}                                           AS anomali_trip_count,
                     COALESCE(SUM(lb.jumlah_liter),0)                        AS total_fill,
                     COUNT(DISTINCT lb.id)                                    AS total_fill_count
              FROM kendaraan k
              LEFT JOIN surat_tugas st
                  ON st.kendaraan_id=k.id AND st.tanggal_berangkat BETWEEN ? AND ?
                  AND st.status IN ('Selesai','Dalam Perjalanan','Disetujui')
              LEFT JOIN log_bahan_bakar lb
                  ON lb.kendaraan_id=k.id AND DATE(lb.tanggal_isi) BETWEEN ? AND ?
              WHERE 1=1 $w_kend $w_role
              GROUP BY k.id
              HAVING total_trips>0 OR total_fill_count>0
              ORDER BY anomali_trip_count DESC, total_terpakai DESC";
    $stmt_r = $mysqli->prepare($sql_r);
    if ($stmt_r) {
        $stmt_r->bind_param('ssss', $rekap_awal, $rekap_akhir, $rekap_awal, $rekap_akhir);
        $stmt_r->execute();
        $rekap_rows = $stmt_r->get_result()->fetch_all(MYSQLI_ASSOC);
        $stmt_r->close();
    }
    if ($rekap_anomali_only) {
        $rekap_rows = array_values(array_filter($rekap_rows, fn($r)=>(int)$r['anomali_trip_count']>0));
    }

    // Efficiency cache
    if ($has_eff_cache && !empty($rekap_rows)) {
        $rvids = array_column($rekap_rows, 'id');
        $r_in  = implode(',', array_fill(0, count($rvids), '?'));
        $st_e  = $mysqli->prepare("SELECT kendaraan_id,avg_kml,sample_count FROM vehicle_efficiency_cache WHERE kendaraan_id IN ($r_in)");
        if ($st_e) {
            $st_e->bind_param(str_repeat('i', count($rvids)), ...$rvids);
            $st_e->execute();
            $res_e = $st_e->get_result();
            while ($er = $res_e->fetch_assoc()) { $rekap_eff_map[(int)$er['kendaraan_id']] = $er; }
            $st_e->close();
        }
    }

    // Anomali fills
    $has_kml_col = function_exists('db_table_columns')
        && in_array('kml_this_fill', (array)db_table_columns('log_bahan_bakar'), true);
    if ($has_kml_col) {
        $aw = $rekap_kend_filter > 0 ? "AND lb.kendaraan_id=$rekap_kend_filter" : '';
        $st_af = $mysqli->prepare(
            "SELECT lb.tanggal_isi,lb.jumlah_liter,lb.kml_this_fill,lb.kendaraan_id,
                    k.no_reg,k.no_polisi,TRIM(CONCAT(k.merk,' ',k.tipe)) AS nama_kendaraan
             FROM log_bahan_bakar lb JOIN kendaraan k ON k.id=lb.kendaraan_id
             WHERE lb.anomali_flag=1 AND DATE(lb.tanggal_isi) BETWEEN ? AND ? $aw
             ORDER BY lb.tanggal_isi DESC LIMIT 50"
        );
        if ($st_af) {
            $st_af->bind_param('ss', $rekap_awal, $rekap_akhir);
            $st_af->execute();
            $rekap_anom_fills = $st_af->get_result()->fetch_all(MYSQLI_ASSOC);
            $st_af->close();
        }
    }

    $grand = [
        'estimasi'  => array_sum(array_column($rekap_rows,'total_estimasi')),
        'terpakai'  => array_sum(array_column($rekap_rows,'total_terpakai')),
        'fill'      => array_sum(array_column($rekap_rows,'total_fill')),
        'est_km'    => array_sum(array_column($rekap_rows,'total_est_km')),
        'actual_km' => array_sum(array_column($rekap_rows,'total_actual_km')),
        'anomali'   => array_sum(array_column($rekap_rows,'anomali_trip_count')),
        'trips'     => array_sum(array_column($rekap_rows,'total_trips')),
    ];
}

// ── Estimasi vs Aktual: akumulasi sepanjang waktu per kendaraan ─────────────
$eav_rows  = [];
$eav_grand = ['jarak_est' => 0, 'bbm_est' => 0, 'bbm_driver' => 0, 'trips' => 0];

if ($view === 'estimasi_aktual' && $action === 'list') {
    $w_role_eav = '';
    $w_role_eav_lb = '';
    if (in_array($current_role, ['user', 'driver'], true)) {
        $w_role_eav    = "AND pengguna_id = $current_user_id";
        $w_role_eav_lb = "AND user_id = $current_user_id";
    }
    // Trip aggregates (estimasi) dan input driver (total liter dari Tambah Log BBM) dihitung
    // via subquery terpisah lalu digabung per kendaraan — join langsung ke dua tabel anak
    // sekaligus akan menggandakan (fanout) hasil SUM.
    $sql_eav = "SELECT k.id, k.no_reg, k.no_polisi,
                       TRIM(CONCAT(k.merk,' ',k.tipe)) AS nama_kendaraan,
                       COALESCE(st_agg.total_trips,0)         AS total_trips,
                       COALESCE(st_agg.total_estimasi_km,0)   AS total_estimasi_km,
                       COALESCE(st_agg.total_estimasi_bbm,0)  AS total_estimasi_bbm,
                       COALESCE(lb_agg.total_bbm_driver,0)    AS total_bbm_driver
                FROM kendaraan k
                JOIN (
                    SELECT kendaraan_id,
                           COUNT(id)          AS total_trips,
                           SUM(estimasi_km)   AS total_estimasi_km,
                           SUM(estimasi_bbm)  AS total_estimasi_bbm
                    FROM surat_tugas
                    WHERE status IN ('Disetujui','Dalam Perjalanan','Selesai') $w_role_eav
                    GROUP BY kendaraan_id
                ) st_agg ON st_agg.kendaraan_id = k.id
                LEFT JOIN (
                    SELECT kendaraan_id, SUM(jumlah_liter) AS total_bbm_driver
                    FROM log_bahan_bakar
                    WHERE 1=1 $w_role_eav_lb
                    GROUP BY kendaraan_id
                ) lb_agg ON lb_agg.kendaraan_id = k.id
                ORDER BY k.no_reg, k.no_polisi";
    $res_eav = $mysqli->query($sql_eav);
    if ($res_eav) {
        $eav_rows = $res_eav->fetch_all(MYSQLI_ASSOC);
        $res_eav->free();
    }

    foreach ($eav_rows as $r) {
        $eav_grand['jarak_est']  += (float)$r['total_estimasi_km'];
        $eav_grand['bbm_est']    += (float)$r['total_estimasi_bbm'];
        $eav_grand['bbm_driver'] += (float)$r['total_bbm_driver'];
        $eav_grand['trips']      += (int)$r['total_trips'];
    }
}

// Helper: badge km/L
function _kml_badge(float $v): string {
    if ($v <= 0) return '<span class="text-muted">—</span>';
    $c = $v >= 10 ? 'success' : ($v >= 7 ? 'warning text-dark' : 'danger');
    return '<span class="badge bg-'.$c.'">'.number_format($v,1).' km/L</span>';
}
// Helper: selisih liter badge
function _diff_badge(float $est, float $act): string {
    if ($est <= 0 || $act <= 0) return '<span class="text-muted">—</span>';
    $diff = $act - $est; $pct = round(abs($diff)/$est*100);
    $c = $pct > 30 ? 'danger' : ($pct > 15 ? 'warning text-dark' : 'success');
    $sign = $diff > 0 ? '+' : '';
    return '<span class="badge bg-'.$c.'">'.$sign.number_format($diff,1).'L ('.$pct.'%)</span>';
}
// Helper: status wajar/perlu-dicek/tidak-wajar dari deviasi persen (dipakai section Estimasi vs Aktual)
function _status_level(float $est, float $act): array {
    if ($est <= 0 || $act <= 0) return ['level' => 'unknown', 'label' => '—', 'class' => 'secondary'];
    $pct = abs($act - $est) / $est * 100;
    if ($pct > 30) return ['level' => 'tidak',  'label' => 'Tidak Wajar', 'class' => 'danger',  'pct' => $pct];
    if ($pct > 15) return ['level' => 'cek',    'label' => 'Perlu Dicek', 'class' => 'warning text-dark', 'pct' => $pct];
    return ['level' => 'wajar', 'label' => 'Wajar', 'class' => 'success', 'pct' => $pct];
}
?>

<div class="page-header">
    <div>
        <h1><i class="fas fa-gas-pump"></i> Log Bahan Bakar</h1>
    </div>
    <div class="d-flex gap-2 align-items-center flex-wrap">
        <?php if ($can_crud && $action !== 'add' && $action !== 'edit'): ?>
            <a href="?page=log_bahan_bakar&action=add<?= $kendaraan_id ? '&kendaraan_id='.$kendaraan_id : '' ?>" class="btn btn-success btn-sm">
                <i class="fas fa-plus me-1"></i>Tambah Log BBM
            </a>
        <?php endif; ?>
    </div>
</div>

<?= $msg ?>

<?php if (($action === 'add' || $action === 'edit') && $can_crud): ?>
    <!-- Form Tambah Log BBM -->
    <div class="form-container">
        <div class="form-card">
            <div class="form-header">
                <h3><i class="fas fa-plus-circle"></i> <?= $action === 'edit' ? 'Edit' : 'Tambah' ?> Log Bahan Bakar</h3>
                <a href="?page=log_bahan_bakar" class="btn btn-outline btn-sm">
                    <i class="fas fa-arrow-left" class="btn btn-secondary"></i> Kembali
                </a>
            </div>
            
            <form method="POST" enctype="multipart/form-data" class="bbm-form">
                <input type="hidden" name="csrf_token" value="<?= generate_csrf_token() ?>">
                
                <div class="form-grid">
                    <div class="form-group">
                        <label for="kendaraan_id">
                            <i class="fas fa-car"></i> Kendaraan <span class="required">*</span>
                        </label>
                        <select name="kendaraan_id" id="kendaraan_id" required class="form-control">
                            <option value="">-- Pilih Kendaraan --</option>
                            <?php while ($kendaraan = $kendaraan_list->fetch_assoc()): ?>
                                <option value="<?= $kendaraan['id'] ?>"
                                    data-bahan-bakar="<?= htmlspecialchars($kendaraan['bahan_bakar'] ?? '') ?>"
                                    <?= ((string)($kendaraan_id ?? '') === (string)$kendaraan['id'] || ((int)($edit_data['kendaraan_id'] ?? 0) === (int)$kendaraan['id'])) ? 'selected' : '' ?>>
                                    <?= htmlspecialchars(($kendaraan['no_reg'] ?? '') . ' - ' . $kendaraan['merk'] . ' ' . $kendaraan['tipe']) ?>
                                </option>
                            <?php endwhile; ?>
                        </select>
                    </div>
                    
                    <div class="form-group">
                        <label for="tanggal_isi">
                            <i class="fas fa-calendar"></i> Tanggal & Waktu Isi <span class="required">*</span>
                        </label>
                        <input type="datetime-local" name="tanggal_isi" id="tanggal_isi" required class="form-control" 
                               value="<?= htmlspecialchars(isset($edit_data['tanggal_isi']) ? date('Y-m-d\TH:i', strtotime((string)$edit_data['tanggal_isi'])) : date('Y-m-d\TH:i')) ?>">
                    </div>
                </div>
                
                <div class="form-grid">
                    <div class="form-group">
                        <label for="jumlah_liter">
                            <i class="fas fa-gas-pump"></i> Jumlah Liter <span class="required">*</span>
                        </label>
                        <input type="number" name="jumlah_liter" id="jumlah_liter" required class="form-control" 
                               step="0.01" min="0.01" placeholder="0.00" value="<?= htmlspecialchars($edit_data['jumlah_liter'] ?? '') ?>">
                    </div>
                    
                    <!-- Field 'biaya' dihapus dari formulir sesuai perubahan kebijakan harga -->
                </div>
                
                <div class="form-grid">
                    <div class="form-group">
                        <label for="km_saat_isi">
                            <i class="fas fa-tachometer-alt"></i> KM Saat Isi
                        </label>
                        <input type="number" name="km_saat_isi" id="km_saat_isi" class="form-control"
                               min="0" placeholder="Odometer saat pengisian" value="<?= htmlspecialchars($edit_data['km_saat_isi'] ?? '') ?>">
                        <div id="km_prev_hint" class="form-text text-muted mt-1">
                            <i class="fas fa-tachometer-alt me-1"></i>
                            KM terakhir pengisian: <strong id="km_prev_value">—</strong>
                            <span id="km_prev_date"></span>
                            &nbsp;&middot;&nbsp; Total odometer tercatat: <strong id="km_total_value">—</strong> km
                        </div>
                        <div id="km_err_hint" class="form-text text-danger mt-1" style="display:none">
                            <i class="fas fa-exclamation-triangle me-1"></i>
                            KM tidak boleh lebih kecil dari KM sebelumnya!
                        </div>
                    </div>
                    
                    <div class="form-group">
                        <label for="jenis_bbm">
                            <i class="fas fa-oil-can"></i> Jenis BBM
                        </label>
                        <select name="jenis_bbm" id="jenis_bahan_bakar" class="form-control">
                            <option value="Pertalite" <?= (($edit_data['jenis_bahan_bakar'] ?? '') === 'Pertalite') ? 'selected' : '' ?>>Pertalite</option>
                            <option value="Pertamax" <?= (($edit_data['jenis_bahan_bakar'] ?? '') === 'Pertamax') ? 'selected' : '' ?>>Pertamax</option>
                            <option value="Pertamax Turbo" <?= (($edit_data['jenis_bahan_bakar'] ?? '') === 'Pertamax Turbo') ? 'selected' : '' ?>>Pertamax Turbo</option>
                            <option value="Solar" <?= (($edit_data['jenis_bahan_bakar'] ?? '') === 'Solar') ? 'selected' : '' ?>>Solar</option>
                            <option value="Biosolar" <?= (($edit_data['jenis_bahan_bakar'] ?? '') === 'Biosolar') ? 'selected' : '' ?>>Biosolar</option>
                        </select>
                    </div>
                </div>
                
                <div class="form-grid">
                    <div class="form-group">
                        <label for="spbu">
                            <i class="fas fa-map-marker-alt"></i> SPBU
                        </label>
                        <input type="text" name="spbu" id="spbu" class="form-control" 
                               placeholder="Nama/lokasi SPBU" value="<?= htmlspecialchars($edit_data['spbu'] ?? '') ?>">
                    </div>
                    
                    <div class="form-group">
                        <!-- placeholder to keep layout consistent -->
                    </div>
                </div>
                

                
                <div class="form-group">
                    <label for="keterangan">
                        <i class="fas fa-sticky-note"></i> Keterangan
                    </label>
                    <textarea name="keterangan" id="keterangan" class="form-control" rows="3" 
                              placeholder="Keterangan tambahan (opsional)"><?= htmlspecialchars($edit_data['keterangan'] ?? '') ?></textarea>
                </div>
                
                <?php if ($action !== 'edit'): ?>
                <input type="hidden" name="surat_tugas_id" id="surat_tugas_id_input" value="">
                <div id="active_surat_box" class="alert alert-success py-2 px-3 small mb-2" style="display:none">
                    <i class="fas fa-route me-1"></i>
                    BBM ini akan dicatat sebagai <strong>BBM aktual</strong> Surat Tugas
                    <strong id="active_surat_nomor">—</strong> (sedang berjalan).
                </div>
                <div id="no_active_surat_box" class="small text-muted mb-2" style="display:none">
                    <i class="fas fa-info-circle me-1"></i>Tidak ada surat tugas yang sedang berjalan untuk kendaraan ini — dicatat sebagai isi BBM rutin.
                </div>
                <?php endif; ?>

                <div class="form-actions">
                    <button type="submit" class="btn btn-primary">
                        <i class="fas fa-save"></i> <?= $action === 'edit' ? 'Perbarui' : 'Simpan' ?> Log BBM
                    </button>
                    <a href="?page=log_bahan_bakar" class="btn btn-outline">
                        <i class="fas fa-times"></i> Batal
                    </a>
                </div>
                    <input type="hidden" name="jarak_traccar_km" id="jarak_traccar_km" value="<?= htmlspecialchars($edit_data['jarak_traccar_km'] ?? '') ?>">
                    <div id="traccar_since_box" class="alert alert-info py-2 px-3 small mt-2 mb-2" style="display:none">
                        <i class="fas fa-satellite-dish me-1"></i>
                        <strong>Jarak GPS sejak pengisian terakhir:</strong>
                        <span id="traccarDistanceDisplay">—</span> km
                        <span class="text-muted ms-1" id="traccar_since_date"></span>
                        <button type="button" id="btnUseTempuh" class="btn btn-sm btn-outline-primary py-0 ms-2" style="font-size:.8rem">
                            <i class="fas fa-check me-1"></i>Pakai Nilai
                        </button>
                    </div>
                    <div id="traccar_no_data" class="small text-muted mb-2" style="display:none">
                        <i class="fas fa-satellite-dish me-1 text-muted"></i>Data GPS tidak tersedia
                    </div>
            </form>
        </div>
    </div>

<?php else: ?>

<!-- ── Tab navigasi ─────────────────────────────────────────────────── -->
<ul class="nav nav-tabs mb-3">
    <li class="nav-item">
        <a class="nav-link <?= $view !== 'rekap' ? 'active fw-semibold' : '' ?>"
           href="?page=log_bahan_bakar">
            <i class="fas fa-list me-1"></i>Daftar Log
        </a>
    </li>
    <li class="nav-item">
        <a class="nav-link <?= $view === 'rekap' ? 'active fw-semibold' : '' ?>"
           href="?page=log_bahan_bakar&view=rekap&bulan=<?= htmlspecialchars($rekap_bulan) ?>">
            <i class="fas fa-chart-bar me-1"></i>Rekap BBM
        </a>
    </li>
    <li class="nav-item">
        <a class="nav-link <?= $view === 'estimasi_aktual' ? 'active fw-semibold' : '' ?>"
           href="?page=log_bahan_bakar&view=estimasi_aktual">
            <i class="fas fa-balance-scale me-1"></i>Estimasi vs Aktual
        </a>
    </li>
</ul>

<?php if ($view === 'rekap'): ?>
<!-- ══ REKAP BBM ══════════════════════════════════════════════════════════ -->

<!-- Filter -->
<div class="card mb-2 shadow-sm">
    <div class="card-body py-2">
        <form method="get" class="row g-2 align-items-end">
            <input type="hidden" name="page" value="log_bahan_bakar">
            <input type="hidden" name="view" value="rekap">
            <div class="col-auto">
                <label class="form-label small fw-bold mb-1">Periode</label>
                <input type="month" name="bulan" class="form-control form-control-sm"
                       value="<?= htmlspecialchars($rekap_bulan) ?>">
            </div>
            <?php if (can_operate()): ?>
            <div class="col-auto">
                <label class="form-label small fw-bold mb-1">Kendaraan</label>
                <select name="rekap_kend" class="form-select form-select-sm select-auto">
                    <option value="">Semua</option>
                    <?php
                    $kl_res = $mysqli->query("SELECT id,no_reg,no_polisi,merk,tipe FROM kendaraan ORDER BY no_reg");
                    while ($kl = $kl_res->fetch_assoc()):
                    ?>
                        <option value="<?= $kl['id'] ?>" <?= $rekap_kend_filter==$kl['id'] ? 'selected' : '' ?>>
                            <?= htmlspecialchars(($kl['no_reg']?:$kl['no_polisi']).' — '.trim($kl['merk'].' '.$kl['tipe'])) ?>
                        </option>
                    <?php endwhile; $kl_res->free(); ?>
                </select>
            </div>
            <?php endif; ?>
            <div class="col-auto d-flex align-items-end gap-2">
                <div class="form-check mb-0">
                    <input class="form-check-input" type="checkbox" name="anomali_only" id="anomali_only" value="1"
                           <?= $rekap_anomali_only ? 'checked' : '' ?>>
                    <label class="form-check-label small" for="anomali_only">Anomali saja</label>
                </div>
                <button type="submit" class="btn btn-primary btn-sm">
                    <i class="fas fa-filter me-1"></i>Tampilkan
                </button>
            </div>
        </form>
    </div>
</div>

<!-- Stat cards -->
<div class="row g-3 mb-3">
    <div class="col-6 col-md-3">
        <div class="card border-0 shadow-sm text-center py-3">
            <div class="text-muted small">Estimasi BBM</div>
            <div class="fs-4 fw-bold text-primary"><?= number_format($grand['estimasi'],1) ?> L</div>
            <div class="fs-xs text-muted"><?= $grand['trips'] ?> trip</div>
        </div>
    </div>
    <div class="col-6 col-md-3">
        <div class="card border-0 shadow-sm text-center py-3">
            <?php $dv = $grand['estimasi']>0 ? abs($grand['terpakai']-$grand['estimasi'])/$grand['estimasi'] : 0; ?>
            <div class="text-muted small">Total Terpakai</div>
            <div class="fs-4 fw-bold <?= $dv>0.30?'text-danger':($dv>0.15?'text-warning':'text-success') ?>">
                <?= number_format($grand['terpakai'],1) ?> L
            </div>
            <div class="fs-xs text-muted">
                <?= $grand['estimasi']>0 ? (($grand['terpakai']>$grand['estimasi']?'+':'').number_format($grand['terpakai']-$grand['estimasi'],1).'L') : '—' ?>
            </div>
        </div>
    </div>
    <div class="col-6 col-md-3">
        <div class="card border-0 shadow-sm text-center py-3">
            <div class="text-muted small">Isian Struk</div>
            <div class="fs-4 fw-bold text-info"><?= number_format($grand['fill'],1) ?> L</div>
            <div class="fs-xs text-muted">dari log BBM</div>
        </div>
    </div>
    <div class="col-6 col-md-3">
        <div class="card border-0 shadow-sm text-center py-3">
            <div class="text-muted small">Anomali Trip</div>
            <div class="fs-4 fw-bold <?= $grand['anomali']>0?'text-danger':'text-success' ?>">
                <?= $grand['anomali'] ?>
            </div>
            <div class="fs-xs text-muted"><?= $grand['anomali']>0 ? 'dari '.$grand['trips'].' trip' : 'Tidak ada' ?></div>
        </div>
    </div>
</div>

<!-- Tabel rekap -->
<div class="card shadow-sm mb-3">
    <div class="card-header d-flex justify-content-between align-items-center">
        <strong><i class="fas fa-table me-1"></i>Rincian per Kendaraan — <?= htmlspecialchars($rekap_bulan) ?></strong>
        <span class="badge bg-secondary"><?= count($rekap_rows) ?> kendaraan</span>
    </div>
    <div class="card-body p-0">
        <?php if (empty($rekap_rows)): ?>
            <div class="empty-state py-5 text-center">
                <i class="fas fa-chart-bar fa-3x text-muted opacity-50 mb-3 d-block"></i>
                <p class="text-muted">Tidak ada data trip/isian BBM untuk periode <strong><?= htmlspecialchars($rekap_bulan) ?></strong></p>
            </div>
        <?php else: ?>
        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0 fs-sm">
                <thead class="thead-navy">
                    <tr>
                        <th>Kendaraan</th>
                        <th class="text-center">Trip</th>
                        <th class="text-end">Est.(L)</th>
                        <th class="text-end">Terpakai(L)</th>
                        <th class="text-center">Selisih</th>
                        <th class="text-end">Isian(L)</th>
                        <th class="text-center">km/L Hist.</th>
                        <th class="text-center">km/L Trip</th>
                        <th class="text-center">Anomali</th>
                    </tr>
                </thead>
                <tbody>
                <?php foreach ($rekap_rows as $rr):
                    $eff_r   = $rekap_eff_map[(int)$rr['id']] ?? null;
                    $hist_kml = $eff_r ? (float)$eff_r['avg_kml'] : 0;
                    $terpakai = (float)$rr['total_terpakai'];
                    $act_km   = (float)$rr['total_actual_km'];
                    $trip_kml = ($terpakai>0 && $act_km>0) ? $act_km/$terpakai : 0;
                    $anomcnt  = (int)$rr['anomali_trip_count'];
                ?>
                    <tr class="<?= $anomcnt>0 ? 'table-warning' : '' ?>">
                        <td>
                            <div class="fw-semibold text-primary"><?= htmlspecialchars($rr['no_reg']?:$rr['no_polisi']) ?></div>
                            <div class="fs-xs text-muted"><?= htmlspecialchars($rr['nama_kendaraan']) ?></div>
                        </td>
                        <td class="text-center"><?= (int)$rr['total_trips'] ?></td>
                        <td class="text-end"><?= (float)$rr['total_estimasi']>0 ? number_format($rr['total_estimasi'],1) : '—' ?></td>
                        <td class="text-end"><?= $terpakai>0 ? number_format($terpakai,1) : '—' ?></td>
                        <td class="text-center"><?= _diff_badge((float)$rr['total_estimasi'], $terpakai) ?></td>
                        <td class="text-end"><?= (float)$rr['total_fill']>0 ? number_format($rr['total_fill'],1) : '—' ?></td>
                        <td class="text-center"><?= _kml_badge($hist_kml) ?></td>
                        <td class="text-center"><?= _kml_badge($trip_kml) ?></td>
                        <td class="text-center">
                            <?= $anomcnt>0
                                ? '<span class="badge bg-danger"><i class="fas fa-exclamation-triangle me-1"></i>'.$anomcnt.' trip</span>'
                                : '<span class="badge bg-success"><i class="fas fa-check me-1"></i>OK</span>' ?>
                        </td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
                <tfoot class="table-secondary fw-bold">
                    <tr>
                        <td>Total</td>
                        <td class="text-center"><?= $grand['trips'] ?></td>
                        <td class="text-end"><?= number_format($grand['estimasi'],1) ?></td>
                        <td class="text-end"><?= number_format($grand['terpakai'],1) ?></td>
                        <td class="text-center"><?= _diff_badge($grand['estimasi'], $grand['terpakai']) ?></td>
                        <td class="text-end"><?= number_format($grand['fill'],1) ?></td>
                        <td colspan="2"></td>
                        <td class="text-center">
                            <?= $grand['anomali']>0
                                ? '<span class="badge bg-danger">'.$grand['anomali'].' total</span>'
                                : '<span class="badge bg-success">OK</span>' ?>
                        </td>
                    </tr>
                </tfoot>
            </table>
        </div>
        <?php endif; ?>
    </div>
</div>

<!-- Anomali isian BBM -->
<?php if (!empty($rekap_anom_fills)): ?>
<div class="card shadow-sm mt-2">
    <div class="card-header bg-warning text-dark">
        <strong><i class="fas fa-exclamation-triangle me-2"></i>Anomali Isian BBM — <?= count($rekap_anom_fills) ?> kejadian</strong>
    </div>
    <div class="card-body p-0">
        <div class="table-responsive">
            <table class="table table-sm table-hover align-middle mb-0 fs-sm">
                <thead class="table-warning">
                    <tr>
                        <th>Tanggal</th><th>Kendaraan</th>
                        <th class="text-end">Liter</th>
                        <th class="text-center">km/L Ini</th>
                        <th class="text-center">km/L Hist.</th>
                    </tr>
                </thead>
                <tbody>
                <?php foreach ($rekap_anom_fills as $af):
                    $eff_af = $rekap_eff_map[(int)$af['kendaraan_id']] ?? null;
                    $hist_af = $eff_af ? (float)$eff_af['avg_kml'] : 0;
                ?>
                    <tr>
                        <td><?= date('d/m/Y', strtotime($af['tanggal_isi'])) ?></td>
                        <td>
                            <span class="fw-semibold text-primary"><?= htmlspecialchars($af['no_reg']?:$af['no_polisi']) ?></span>
                            <span class="fs-xs text-muted d-block"><?= htmlspecialchars($af['nama_kendaraan']) ?></span>
                        </td>
                        <td class="text-end"><?= number_format((float)$af['jumlah_liter'],2) ?> L</td>
                        <td class="text-center"><?= _kml_badge((float)$af['kml_this_fill']) ?></td>
                        <td class="text-center"><?= _kml_badge($hist_af) ?></td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>
<?php endif; ?>

<?php elseif ($view === 'estimasi_aktual'): ?>
<!-- ══ ESTIMASI VS AKTUAL (akumulasi sepanjang waktu) ═══════════════════════ -->

<div class="card shadow-sm mb-3">
    <div class="card-header d-flex justify-content-between align-items-center">
        <strong><i class="fas fa-balance-scale me-1"></i>Estimasi vs Aktual — Sepanjang Waktu</strong>
        <span class="badge bg-secondary"><?= count($eav_rows) ?> kendaraan</span>
    </div>
    <div class="card-body p-0">
        <?php if (empty($eav_rows)): ?>
            <div class="empty-state py-5 text-center">
                <i class="fas fa-balance-scale fa-3x text-muted opacity-50 mb-3 d-block"></i>
                <p class="text-muted">Belum ada data surat tugas dengan estimasi jarak/BBM.</p>
            </div>
        <?php else:
            $eav_bbm_grand_status = _status_level($eav_grand['bbm_est'], $eav_grand['bbm_driver']);
        ?>
        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0 fs-sm">
                <thead class="thead-navy">
                    <tr>
                        <th>Kendaraan</th>
                        <th class="text-center">Trip</th>
                        <th class="text-center">Jarak: Estimasi vs Traccar</th>
                        <th class="text-center">BBM: Estimasi vs Input Driver</th>
                        <th class="text-center">Status</th>
                    </tr>
                </thead>
                <tbody>
                <?php foreach ($eav_rows as $r):
                    $est_km   = (float)$r['total_estimasi_km'];
                    $est_bbm  = (float)$r['total_estimasi_bbm'];
                    $drv_bbm  = (float)$r['total_bbm_driver'];
                    $bbm_stat = _status_level($est_bbm, $drv_bbm);
                ?>
                    <tr data-eav-row data-kendaraan-id="<?= (int)$r['id'] ?>" data-est-km="<?= $est_km ?>" data-bbm-level="<?= $bbm_stat['level'] ?>">
                        <td>
                            <div class="fw-semibold text-primary"><?= htmlspecialchars($r['no_reg']?:$r['no_polisi']) ?></div>
                            <div class="fs-xs text-muted"><?= htmlspecialchars($r['nama_kendaraan']) ?></div>
                        </td>
                        <td class="text-center"><?= (int)$r['total_trips'] ?></td>
                        <td class="text-center">
                            <?= $est_km > 0 ? number_format($est_km,0) : '—' ?> km vs
                            <span class="eav-traccar-val text-muted">memuat...</span>
                        </td>
                        <td class="text-center">
                            <?= $est_bbm > 0 ? number_format($est_bbm,1) : '—' ?> L vs
                            <?= $drv_bbm > 0 ? number_format($drv_bbm,1) : '—' ?> L
                        </td>
                        <td class="text-center eav-status-cell">
                            <span class="badge bg-<?= $bbm_stat['class'] ?>"><?= $bbm_stat['label'] ?></span>
                        </td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
                <tfoot class="table-secondary fw-bold">
                    <tr>
                        <td>Total</td>
                        <td class="text-center"><?= $eav_grand['trips'] ?></td>
                        <td class="text-center">
                            <?= number_format($eav_grand['jarak_est'],0) ?> km vs
                            <span id="eav-traccar-grand" class="text-muted">memuat...</span>
                        </td>
                        <td class="text-center">
                            <?= number_format($eav_grand['bbm_est'],1) ?> L vs
                            <?= number_format($eav_grand['bbm_driver'],1) ?> L
                        </td>
                        <td class="text-center">
                            <span class="badge bg-<?= $eav_bbm_grand_status['class'] ?>"><?= $eav_bbm_grand_status['label'] ?></span>
                        </td>
                    </tr>
                </tfoot>
            </table>
        </div>
        <div class="px-3 py-2 fs-xs text-muted border-top">
            <i class="fas fa-info-circle me-1"></i>
            Jarak Traccar dimuat via GPS. BBM Input Driver adalah total liter dari seluruh riwayat Tambah Log BBM kendaraan ini. Status dihitung dari deviasi terbesar antara estimasi vs Traccar (jarak) dan estimasi vs input driver (BBM): &le;15% Wajar, 15–30% Perlu Dicek, &gt;30% Tidak Wajar.
        </div>
        <?php endif; ?>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function () {
    var rows = document.querySelectorAll('tr[data-eav-row]');
    if (!rows.length) return;

    var grandTraccar = 0, grandLoaded = 0, grandTotal = rows.length, grandAnyLoaded = false;

    function levelRank(level) {
        return { 'tidak': 3, 'cek': 2, 'wajar': 1, 'unknown': 0 }[level] || 0;
    }
    function levelBadge(level) {
        if (level === 'tidak') return '<span class="badge bg-danger">Tidak Wajar</span>';
        if (level === 'cek')   return '<span class="badge bg-warning text-dark">Perlu Dicek</span>';
        if (level === 'wajar') return '<span class="badge bg-success">Wajar</span>';
        return '<span class="badge bg-secondary">—</span>';
    }
    function jarakLevel(est, act) {
        if (!est || act === null || est <= 0 || act <= 0) return 'unknown';
        var pct = Math.abs(act - est) / est * 100;
        if (pct > 30) return 'tidak';
        if (pct > 15) return 'cek';
        return 'wajar';
    }

    rows.forEach(function (row) {
        var vid = row.getAttribute('data-kendaraan-id');
        var estKm = parseFloat(row.getAttribute('data-est-km')) || 0;
        var bbmLevel = row.getAttribute('data-bbm-level');
        var traccarCell = row.querySelector('.eav-traccar-val');
        var statusCell = row.querySelector('.eav-status-cell');

        fetch('ajax/traccar_total_distance.php?kendaraan_id=' + encodeURIComponent(vid))
            .then(function (r) { return r.json(); })
            .then(function (data) {
                var km = (data && data.success && data.distance_km !== undefined) ? parseFloat(data.distance_km) : null;
                if (traccarCell) {
                    traccarCell.textContent = km !== null ? Number(km.toFixed(0)).toLocaleString('id-ID') + ' km' : 'tidak ada data';
                }
                if (km !== null) { grandTraccar += km; grandAnyLoaded = true; }

                var jLevel = jarakLevel(estKm, km);
                var finalLevel = levelRank(jLevel) >= levelRank(bbmLevel) ? jLevel : bbmLevel;
                if (statusCell) statusCell.innerHTML = levelBadge(finalLevel);
            })
            .catch(function () {
                if (traccarCell) traccarCell.textContent = 'tidak ada data';
            })
            .then(function () {
                grandLoaded++;
                if (grandLoaded === grandTotal) {
                    var el = document.getElementById('eav-traccar-grand');
                    if (el) el.textContent = grandAnyLoaded
                        ? Number(grandTraccar.toFixed(0)).toLocaleString('id-ID') + ' km'
                        : 'tidak ada data';
                }
            });
    });
});
</script>

<?php else: ?>
<!-- ══ DAFTAR LOG BBM ══════════════════════════════════════════════════════ -->
    <!-- Daftar Log BBM Per Kendaraan -->
    <div id="log-bbm-page" class="log-container">
        <?php if ($log_list->num_rows > 0): ?>
            <div class="card">
                <div class="card-header d-flex justify-content-between align-items-center">
                    <h4 class="card-title">Log Bahan Bakar Per Kendaraan</h4>
                    <div class="card-actions">
                        <span class="badge badge-primary"><?= $log_list->num_rows ?> Kendaraan</span>
                    </div>
                </div>
                <div class="card-body">
                    <div class="table-responsive">
                        <table class="table table-hover">
                            <thead class="bg-light">
                                <tr>
                                    <th width="5%">No</th>
                                    <th width="30%">Kendaraan</th>
                                    <th width="15%">Total Pengisian</th>
                                    <th width="15%">Total Liter</th>
                                    <th width="15%">Jarak Total (km)</th>
                                    <th width="20%">Pengisian Terakhir</th>
                                    <th width="15%">Aksi</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php 
                                $no = $offset + 1;
                                while ($kendaraan = $log_list->fetch_assoc()):
                                ?>
                                <tr>
                                    <td><?= $no++ ?></td>
                                    <td>
                                        <div class="d-flex align-items-center">
                                            <div class="vehicle-avatar">
                                                <?php $photo = get_vehicle_photo_web_path((int)$kendaraan['kendaraan_id']); ?>
                                                <?php if ($photo): ?>
                                                    <img src="<?= htmlspecialchars($photo) ?>?v=<?= time() ?>"
                                                         alt="<?= htmlspecialchars($kendaraan['no_reg'] ?? $kendaraan['no_polisi']) ?>"
                                                         class="thumb-42">
                                                <?php else: ?>
                                                    <div class="thumb-placeholder-42">
                                                        <i class="fas fa-car"></i>
                                                    </div>
                                                <?php endif; ?>
                                            </div>
                                            <div>
                                                <strong class="text-primary"><?= htmlspecialchars($kendaraan['no_reg'] ?? $kendaraan['no_polisi']) ?></strong>
                                                <br><small class="text-muted"><?= htmlspecialchars($kendaraan['merk'] . ' ' . $kendaraan['tipe']) ?></small>
                                            </div>
                                        </div>
                                    </td>
                                    <td>
                                        <span class="badge badge-primary badge-lg"><?= $kendaraan['total_pengisian'] ?> kali</span>
                                    </td>
                                    <td>
                                        <strong><?= number_format($kendaraan['total_liter'] ?: 0, 2) ?> L</strong>
                                        <?php if ($kendaraan['km_terakhir']): ?>
                                            <br><small class="text-muted">KM terakhir: <?= number_format($kendaraan['km_terakhir']) ?></small>
                                        <?php endif; ?>
                                        <?php
                                            $km_tot = null;
                                            if ($kendaraan['km_terakhir'] && $kendaraan['km_pertama'] && (int)$kendaraan['km_terakhir'] > (int)$kendaraan['km_pertama']) {
                                                $km_tot = (int)$kendaraan['km_terakhir'] - (int)$kendaraan['km_pertama'];
                                            }
                                        ?>
                                        <?php if ($km_tot !== null): ?>
                                            <br><small class="text-success fw-bold"><i class="fas fa-road me-1"></i>Jarak odometer: <?= number_format($km_tot) ?> km</small>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <span class="traccar-total" data-kendaraan-id="<?= $kendaraan['kendaraan_id'] ?>">Memuat...</span>
                                    </td>
                                    <td>
                                        <?php if ($kendaraan['pengisian_terakhir']): ?>
                                            <strong><?= date('d/m/Y', strtotime($kendaraan['pengisian_terakhir'])) ?></strong>
                                            <br><small class="text-muted"><?= floor((time() - strtotime($kendaraan['pengisian_terakhir'])) / (60*60*24)) ?> hari lalu</small>
                                        <?php else: ?>
                                            <span class="text-muted">Belum ada</span>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <a class="btn btn-sm btn-primary" href="index.php?page=log_bahan_bakar_detail&kendaraan_id=<?= $kendaraan['kendaraan_id'] ?>" title="Lihat Detail BBM">
                                            <i class="fas fa-gas-pump"></i> Detail
                                        </a>
                                    </td>
                                </tr>
                                <?php endwhile; ?>
                            </tbody>
                        </table>
                    </div>
                    <?php if ($total_pages > 1): ?>
                        <div class="pagination-wrapper mt-3 d-flex justify-content-between align-items-center">
                            <div>
                                Menampilkan <?= min($total_records, $offset + 1) ?> - <?= min($total_records, $offset + $limit) ?> dari <?= $total_records ?> kendaraan
                            </div>
                            <nav aria-label="Pagination">
                                <ul class="pagination mb-0">
                                    <?php if ($page > 1): ?>
                                        <li class="page-item">
                                            <a class="page-link" href="?page=log_bahan_bakar&page_num=<?= $page - 1 ?>" aria-label="Previous">&laquo;</a>
                                        </li>
                                    <?php endif; ?>

                                    <?php for ($i = max(1, $page - 2); $i <= min($total_pages, $page + 2); $i++): ?>
                                        <li class="page-item <?= $i == $page ? 'active' : '' ?>">
                                            <a class="page-link" href="?page=log_bahan_bakar&page_num=<?= $i ?>"><?= $i ?></a>
                                        </li>
                                    <?php endfor; ?>

                                    <?php if ($page < $total_pages): ?>
                                        <li class="page-item">
                                            <a class="page-link" href="?page=log_bahan_bakar&page_num=<?= $page + 1 ?>" aria-label="Next">&raquo;</a>
                                        </li>
                                    <?php endif; ?>
                                </ul>
                            </nav>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        <?php else: ?>
            <div class="empty-state">
                <div class="empty-icon">
                    <i class="fas fa-gas-pump"></i>
                </div>
                <h3>Belum Ada Log BBM</h3>
                <p>Belum ada log bahan bakar yang tercatat.</p>
                <?php if ($can_crud): ?>
                    <div class="empty-actions">
                        <a href="?page=log_bahan_bakar&action=add" class="btn btn-primary">
                            <i class="fas fa-plus"></i> Tambah Log BBM Pertama
                        </a>
                    </div>
                <?php endif; ?>
            </div>
        <?php endif; ?>
    </div>
<?php endif; /* end view=rekap / daftar */ ?>

<?php endif; /* end action=add/edit vs list */ ?>

<!-- Modal untuk Detail BBM Kendaraan -->
<div class="modal fade" id="detailBbmModal" tabindex="-1" role="dialog" aria-labelledby="detailBbmModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-xl" role="document">
        <div class="modal-content">
            <div class="modal-header bg-primary text-white">
                <h5 class="modal-title" id="detailBbmModalLabel">
                    <i class="fas fa-gas-pump"></i> Detail Log BBM Kendaraan
                </h5>
                <button type="button" class="close text-white" data-dismiss="modal" aria-label="Close">
                    <span aria-hidden="true">&times;</span>
                </button>
            </div>
            <div class="modal-body">
                <!-- Content will be loaded via AJAX -->
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-dismiss="modal">Tutup</button>
            </div>
        </div>
    </div>
</div>

<!-- Modal untuk menampilkan foto -->
<div id="photoModal" class="photo-modal">
    <div class="modal-content">
        <span class="close" onclick="closePhotoModal()">&times;</span>
        <img id="modalImage" src="" alt="">
        <div class="modal-caption" id="modalCaption"></div>
    </div>
</div>


<script>
// Preview foto sebelum upload
function setupPhotoPreview(inputId, previewId) {
    document.getElementById(inputId).addEventListener('change', function(e) {
        const file = e.target.files[0];
        const preview = document.getElementById(previewId);
        
        if (file) {
            const reader = new FileReader();
            reader.onload = function(e) {
                preview.innerHTML = '<img src="' + e.target.result + '" alt="Preview" class="image-preview">';
            };
            reader.readAsDataURL(file);
        } else {
            preview.innerHTML = '';
        }
    });
}

// Setup preview untuk semua input foto
setupPhotoPreview('foto_sebelum_isi', 'preview_sebelum');
setupPhotoPreview('foto_sesudah_isi', 'preview_sesudah');
setupPhotoPreview('foto_odometer', 'preview_odometer');

// Modal foto
function showPhotoModal(src, caption) {
    const modal = document.getElementById('photoModal');
    const img = document.getElementById('modalImage');
    const captionText = document.getElementById('modalCaption');
    
    modal.style.display = 'block';
    img.src = src;
    captionText.textContent = caption;
}

function closePhotoModal() {
    document.getElementById('photoModal').style.display = 'none';
}

// Tutup modal jika klik di luar gambar
window.onclick = function(event) {
    const modal = document.getElementById('photoModal');
    if (event.target == modal) {
        modal.style.display = 'none';
    }
}

// Removed legacy auto-calculation of biaya/harga
</script>

<script>
// ── List view: Traccar lifetime totals ────────────────────────────
document.addEventListener('DOMContentLoaded', function () {
    var els = document.querySelectorAll('.traccar-total');
    els.forEach(function (el) {
        var vid = el.getAttribute('data-kendaraan-id');
        if (!vid) { el.innerHTML = '<span class="text-muted">-</span>'; return; }
        fetch('ajax/traccar_total_distance.php?kendaraan_id=' + encodeURIComponent(vid))
            .then(function (res) { return res.json(); })
            .then(function (data) {
                if (data && data.success && data.distance_km !== undefined) {
                    var km = parseFloat(data.distance_km);
                    var label = km >= 1000
                        ? (km / 1000).toFixed(2) + ' rb km'
                        : km.toLocaleString('id-ID', {minimumFractionDigits: 2, maximumFractionDigits: 2}) + ' km';
                    var src = data.source === 'odometer'
                        ? '<br><small class="text-muted"><i class="fas fa-satellite-dish me-1"></i>GPS odometer</small>'
                        : '<br><small class="text-muted"><i class="fas fa-satellite-dish me-1"></i>Traccar</small>';
                    el.innerHTML = '<strong>' + label + '</strong>' + src;
                } else {
                    el.innerHTML = '<span class="text-muted">-</span>';
                }
            }).catch(function () { el.innerHTML = '<span class="text-muted">-</span>'; });
    });
});
</script>

<script>
// ── Add/Edit form: prev km hint + Traccar since last log ─────────
document.addEventListener('DOMContentLoaded', function () {
    var selVeh   = document.getElementById('kendaraan_id');
    var inpKm    = document.getElementById('km_saat_isi');
    var hintBox  = document.getElementById('km_prev_hint');
    var errBox   = document.getElementById('km_err_hint');
    var hintVal  = document.getElementById('km_prev_value');
    var hintDate = document.getElementById('km_prev_date');
    var hintTot  = document.getElementById('km_total_value');
    var hidTraccar = document.getElementById('jarak_traccar_km');
    var traccarBox  = document.getElementById('traccar_since_box');
    var traccarNone = document.getElementById('traccar_no_data');
    var traccarDisp = document.getElementById('traccarDistanceDisplay');
    var traccarDate = document.getElementById('traccar_since_date');
    var btnUse   = document.getElementById('btnUseTempuh');

    if (!selVeh || !inpKm) return; // not on the form page

    var prevKmMin = 0; // minimum allowed km

    // ── Fetch prev km info for selected vehicle ──────────────────
    function fetchPrevKm() {
        var vid = selVeh.value;
        var excludeId = <?= isset($edit_data['id']) ? (int)$edit_data['id'] : 0 ?>;
        if (!vid) { hidePrevHint(); return; }

        // Tampilkan loading sementara menunggu data
        if (hintVal)  hintVal.textContent  = '...';
        if (hintDate) hintDate.textContent = '';
        if (hintTot)  hintTot.textContent  = '...';

        var url = 'index.php?page=log_bahan_bakar&action=get_prev_km&kendaraan_id=' + encodeURIComponent(vid)
                + (excludeId ? '&exclude_id=' + excludeId : '');
        fetch(url)
            .then(function(r){ return r.json(); })
            .then(function(data) {
                if (data && data.prev_km !== null) {
                    prevKmMin = data.prev_km;
                    inpKm.min = data.prev_km;
                    if (hintVal)  hintVal.textContent  = Number(data.prev_km).toLocaleString('id-ID') + ' km';
                    if (hintDate) hintDate.textContent = data.last_date ? '(' + data.last_date.substring(0,10) + ')' : '';
                    if (hintTot)  hintTot.textContent  = data.total_km !== null ? Number(data.total_km).toLocaleString('id-ID') : '—';
                    validateKm();
                } else {
                    hidePrevHint();
                }
                fetchTraccarSinceLog(vid);
            })
            .catch(function() { hidePrevHint(); });
    }

    function hidePrevHint() {
        prevKmMin = 0;
        inpKm.removeAttribute('min');
        if (hintVal)  hintVal.textContent  = '—';
        if (hintDate) hintDate.textContent = '';
        if (hintTot)  hintTot.textContent  = '—';
        if (errBox)   errBox.style.display = 'none';
    }

    // ── Validate km input ─────────────────────────────────────────
    function validateKm() {
        if (!errBox || prevKmMin === 0) return;
        var val = parseInt(inpKm.value, 10);
        if (!isNaN(val) && val < prevKmMin) {
            errBox.style.display = '';
            inpKm.setCustomValidity('KM tidak boleh lebih kecil dari KM sebelumnya (' + prevKmMin.toLocaleString('id-ID') + ' km)');
        } else {
            errBox.style.display = 'none';
            inpKm.setCustomValidity('');
        }
    }

    // ── Fetch Traccar distance since last log BBM ─────────────────
    function fetchTraccarSinceLog(vid) {
        if (!traccarBox && !traccarNone) return;
        fetch('ajax/traccar_total_distance.php?kendaraan_id=' + encodeURIComponent(vid) + '&since_log=1')
            .then(function(r){ return r.json(); })
            .then(function(data) {
                if (data && data.success && data.distance_km > 0) {
                    if (traccarBox)  traccarBox.style.display  = '';
                    if (traccarNone) traccarNone.style.display = 'none';
                    if (traccarDisp) traccarDisp.textContent   = parseFloat(data.distance_km).toFixed(2);
                    if (traccarDate && data.since_datetime) {
                        traccarDate.textContent = '(sejak ' + data.since_datetime.substring(0,10) + ')';
                    }
                    if (hidTraccar && !hidTraccar.value) {
                        hidTraccar.value = parseFloat(data.distance_km).toFixed(2);
                    }
                } else {
                    if (traccarBox)  traccarBox.style.display  = 'none';
                    if (traccarNone) traccarNone.style.display = '';
                }
            })
            .catch(function() {
                if (traccarBox)  traccarBox.style.display  = 'none';
                if (traccarNone) traccarNone.style.display = '';
            });
    }

    // ── "Pakai Nilai" button — copy Traccar km to hidden field ───
    if (btnUse) {
        btnUse.addEventListener('click', function() {
            if (traccarDisp && hidTraccar) {
                hidTraccar.value = traccarDisp.textContent.trim();
            }
        });
    }

    // ── Auto-select jenis BBM dari data kendaraan ─────────────────
    var selFuel = document.getElementById('jenis_bahan_bakar');

    function autoSelectFuelType() {
        if (!selFuel) return;
        var opt = selVeh.options[selVeh.selectedIndex];
        var bb = opt ? (opt.getAttribute('data-bahan-bakar') || '').toLowerCase().trim() : '';
        if (!bb) return;

        var targetVal = null;
        if (bb.includes('biosolar'))      targetVal = 'Biosolar';
        else if (bb.includes('solar'))    targetVal = 'Solar';
        else if (bb.includes('turbo'))    targetVal = 'Pertamax Turbo';
        else if (bb.includes('pertamax')) targetVal = 'Pertamax';
        else if (bb.includes('pertalite'))targetVal = 'Pertalite';

        if (targetVal) {
            for (var i = 0; i < selFuel.options.length; i++) {
                if (selFuel.options[i].value === targetVal) {
                    selFuel.value = targetVal;
                    break;
                }
            }
        }
    }

    // ── Cari surat tugas aktif (Dalam Perjalanan) untuk kendaraan terpilih ──
    var hidSuratTugas   = document.getElementById('surat_tugas_id_input');
    var activeSuratBox  = document.getElementById('active_surat_box');
    var activeSuratNo   = document.getElementById('active_surat_nomor');
    var noActiveBox     = document.getElementById('no_active_surat_box');

    function fetchActiveSurat() {
        if (!hidSuratTugas) return; // form edit tidak punya field ini
        var vid = selVeh.value;
        hidSuratTugas.value = '';
        if (activeSuratBox) activeSuratBox.style.display = 'none';
        if (noActiveBox) noActiveBox.style.display = 'none';
        if (!vid) return;

        fetch('index.php?page=log_bahan_bakar&action=get_active_surat&kendaraan_id=' + encodeURIComponent(vid))
            .then(function(r){ return r.json(); })
            .then(function(data) {
                if (data && data.surat_tugas_id) {
                    hidSuratTugas.value = data.surat_tugas_id;
                    if (activeSuratNo) activeSuratNo.textContent = data.nomor_surat || ('#' + data.surat_tugas_id);
                    if (activeSuratBox) activeSuratBox.style.display = '';
                } else if (noActiveBox) {
                    noActiveBox.style.display = '';
                }
            })
            .catch(function() { if (noActiveBox) noActiveBox.style.display = ''; });
    }

    // ── Event listeners ───────────────────────────────────────────
    selVeh.addEventListener('change', function() {
        autoSelectFuelType();
        fetchPrevKm();
        fetchActiveSurat();
    });
    inpKm.addEventListener('input', validateKm);

    // Initial load (edit case has a vehicle pre-selected)
    if (selVeh.value) {
        autoSelectFuelType();
        fetchPrevKm();
        fetchActiveSurat();
    }
});
</script>