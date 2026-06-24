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
                
                $stmt = $mysqli->prepare("INSERT INTO log_bahan_bakar (kendaraan_id, tanggal_isi, jumlah_liter, km_saat_isi, jarak_traccar_km, spbu, jenis_bahan_bakar, user_id, foto_sebelum_isi, foto_sesudah_isi, foto_odometer, keterangan) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
                $stmt->bind_param('isdidissssss', $kendaraan_id, $tanggal_isi, $jumlah_liter, $km_saat_isi, $jarak_traccar_km, $spbu, $jenis_bbm, $current_user_id, $foto_sebelum, $foto_sesudah, $foto_odometer, $keterangan);
                
                if ($stmt->execute()) {
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
?>

<div class="page-header">
        <h1><i class="fas fa-gas-pump"></i> Log Bahan Bakar</h1>
        <?php if ($can_crud): ?>
            <div class="header-actions">
                <a href="?page=log_bahan_bakar&action=add<?= $kendaraan_id ? '&kendaraan_id=' . $kendaraan_id : '' ?>" class="btn btn-primary">
                    <i class="fas fa-plus" class = "btn btn-primary btn-md"></i> Tambah Log BBM
                </a>
            </div>
        <?php endif; ?>
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
                                <option value="<?= $kendaraan['id'] ?>" <?= ((string)($kendaraan_id ?? '') === (string)$kendaraan['id'] || ((int)($edit_data['kendaraan_id'] ?? 0) === (int)$kendaraan['id'])) ? 'selected' : '' ?>>
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
                        <div id="km_prev_hint" class="form-text mt-1" style="display:none">
                            <i class="fas fa-tachometer-alt text-secondary me-1"></i>
                            KM terakhir: <strong id="km_prev_value">—</strong>
                            <span class="text-muted" id="km_prev_date"></span>
                            &nbsp;|&nbsp; Jarak odometer total: <strong id="km_total_value">—</strong> km
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
<?php endif; ?>

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
        if (!vid) { el.textContent = '-'; return; }
        fetch('ajax/traccar_total_distance.php?kendaraan_id=' + encodeURIComponent(vid))
            .then(function (res) { return res.json(); })
            .then(function (data) {
                el.textContent = (data && data.success && data.distance_km !== undefined)
                    ? parseFloat(data.distance_km).toFixed(2) + ' km'
                    : '-';
            }).catch(function () { el.textContent = '-'; });
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
        var url = 'index.php?page=log_bahan_bakar&action=get_prev_km&kendaraan_id=' + encodeURIComponent(vid)
                + (excludeId ? '&exclude_id=' + excludeId : '');
        fetch(url)
            .then(function(r){ return r.json(); })
            .then(function(data) {
                if (data && data.prev_km !== null) {
                    prevKmMin = data.prev_km;
                    inpKm.min = data.prev_km;
                    if (hintBox)  hintBox.style.display  = '';
                    if (hintVal)  hintVal.textContent     = Number(data.prev_km).toLocaleString('id-ID') + ' km';
                    if (hintDate) hintDate.textContent    = data.last_date ? '(' + data.last_date.substring(0,10) + ')' : '';
                    if (hintTot)  hintTot.textContent     = data.total_km !== null ? Number(data.total_km).toLocaleString('id-ID') : '—';
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
        if (hintBox) hintBox.style.display = 'none';
        if (errBox)  errBox.style.display  = 'none';
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

    // ── Event listeners ───────────────────────────────────────────
    selVeh.addEventListener('change', fetchPrevKm);
    inpKm.addEventListener('input', validateKm);

    // Initial load (edit case has a vehicle pre-selected)
    if (selVeh.value) fetchPrevKm();
});
</script>