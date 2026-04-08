<?php
require_once 'includes/auth.php';

$current_role = get_current_role();
$current_user_id = get_current_user_id();

// Role-based access control
if ($current_role === 'guest') {
    header('Location: index.php?page=kendaraan_publik');
    exit;
}

$can_crud = can_operate() || $current_role === 'user';
$can_view = is_logged_in();

$action = $_GET['action'] ?? 'list';
$log_id = $_GET['id'] ?? null;
$kendaraan_id = $_GET['kendaraan_id'] ?? null;
$msg = '';

// Time helpers for window filtering
$now = date('Y-m-d H:i:s');
$today = date('Y-m-d');

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
                $tanggal_isi = $_POST['tanggal_isi'];
                $jumlah_liter = (float)$_POST['jumlah_liter'];
                $harga_per_liter = (float)$_POST['harga_per_liter'];
                $km_saat_isi = $_POST['km_saat_isi'] ? (int)$_POST['km_saat_isi'] : null;
                $spbu = trim($_POST['spbu']);
                $jenis_bbm = $_POST['jenis_bbm'];
                $metode_bayar = $_POST['metode_bayar'];
                $keterangan = trim($_POST['keterangan']);
                $total_biaya = $jumlah_liter * $harga_per_liter;
                
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
                
                $stmt = $mysqli->prepare("INSERT INTO log_bahan_bakar (kendaraan_id, tanggal_isi, jumlah_liter, harga_per_liter, biaya, km_saat_isi, spbu, jenis_bahan_bakar, metode_bayar, user_id, foto_sebelum_isi, foto_sesudah_isi, foto_odometer, keterangan) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
                $stmt->bind_param('isddissssissss', $kendaraan_id, $tanggal_isi, $jumlah_liter, $harga_per_liter, $total_biaya, $km_saat_isi, $spbu, $jenis_bbm, $metode_bayar, $current_user_id, $foto_sebelum, $foto_sesudah, $foto_odometer, $keterangan);
                
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
        }
    }
}

// Get vehicle data based on user role
if ($current_role === 'user') {
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
    // Operator dan admin bisa melihat semua kendaraan
    $kendaraan_stmt = $mysqli->prepare("SELECT * FROM kendaraan ORDER BY no_reg, no_polisi");
}
$kendaraan_stmt->execute();
$kendaraan_list = $kendaraan_stmt->get_result();

// Get BBM logs - Grouped by vehicle
$page = isset($_GET['page_num']) ? max(1, intval($_GET['page_num'])) : 1;
$limit = 10;
$offset = ($page - 1) * $limit;

if ($current_role === 'user') {
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
            SUM(lb.biaya) as total_biaya,
            MAX(lb.tanggal_isi) as pengisian_terakhir,
            AVG(lb.harga_per_liter) as rata_harga,
            MAX(lb.km_saat_isi) as km_terakhir
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
} else {
    // Operator dan admin melihat semua kendaraan dengan summary BBM
    $log_stmt = $mysqli->prepare("
        SELECT 
            k.id as kendaraan_id,
            k.no_reg,
            k.no_polisi,
            k.merk,
            k.tipe,
            k.foto,
            COUNT(lb.id) as total_pengisian,
            SUM(lb.jumlah_liter) as total_liter,
            SUM(lb.biaya) as total_biaya,
            MAX(lb.tanggal_isi) as pengisian_terakhir,
            AVG(lb.harga_per_liter) as rata_harga,
            MAX(lb.km_saat_isi) as km_terakhir
        FROM kendaraan k
        LEFT JOIN log_bahan_bakar lb ON k.id = lb.kendaraan_id
        GROUP BY k.id, k.no_reg, k.no_polisi, k.merk, k.tipe, k.foto
        ORDER BY pengisian_terakhir DESC, k.no_reg, k.no_polisi
        LIMIT ? OFFSET ?
    ");
    $log_stmt->bind_param('ii', $limit, $offset);
}

$log_stmt->execute();
$log_list = $log_stmt->get_result();

// Get total count for pagination (count vehicles shown)
if ($current_role === 'user') {
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

<?php if ($action === 'add' && $can_crud): ?>
    <!-- Form Tambah Log BBM -->
    <div class="form-container">
        <div class="form-card">
            <div class="form-header">
                <h3><i class="fas fa-plus-circle"></i> Tambah Log Bahan Bakar</h3>
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
                                <option value="<?= $kendaraan['id'] ?>" <?= ($kendaraan_id == $kendaraan['id']) ? 'selected' : '' ?>>
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
                               value="<?= date('Y-m-d\TH:i') ?>">
                    </div>
                </div>
                
                <div class="form-grid">
                    <div class="form-group">
                        <label for="jumlah_liter">
                            <i class="fas fa-gas-pump"></i> Jumlah Liter <span class="required">*</span>
                        </label>
                        <input type="number" name="jumlah_liter" id="jumlah_liter" required class="form-control" 
                               step="0.01" min="0.01" placeholder="0.00">
                    </div>
                    
                    <div class="form-group">
                        <label for="harga_per_liter">
                            <i class="fas fa-money-bill"></i> Harga per Liter <span class="required">*</span>
                        </label>
                        <input type="number" name="harga_per_liter" id="harga_per_liter" required class="form-control" 
                               step="0.01" min="0.01" placeholder="0.00">
                    </div>
                </div>
                
                <div class="form-grid">
                    <div class="form-group">
                        <label for="km_saat_isi">
                            <i class="fas fa-tachometer-alt"></i> KM Saat Isi
                        </label>
                        <input type="number" name="km_saat_isi" id="km_saat_isi" class="form-control" 
                               min="0" placeholder="Odometer saat pengisian">
                    </div>
                    
                    <div class="form-group">
                        <label for="jenis_bbm">
                            <i class="fas fa-oil-can"></i> Jenis BBM
                        </label>
                        <select name="jenis_bbm" id="jenis_bahan_bakar" class="form-control">
                            <option value="Pertalite">Pertalite</option>
                            <option value="Pertamax">Pertamax</option>
                            <option value="Pertamax Turbo">Pertamax Turbo</option>
                            <option value="Solar">Solar</option>
                            <option value="Biosolar">Biosolar</option>
                        </select>
                    </div>
                </div>
                
                <div class="form-grid">
                    <div class="form-group">
                        <label for="spbu">
                            <i class="fas fa-map-marker-alt"></i> SPBU
                        </label>
                        <input type="text" name="spbu" id="spbu" class="form-control" 
                               placeholder="Nama/lokasi SPBU">
                    </div>
                    
                    <div class="form-group">
                        <label for="metode_bayar">
                            <i class="fas fa-credit-card"></i> Metode Bayar
                        </label>
                        <select name="metode_bayar" id="metode_bayar" class="form-control">
                            <option value="Tunai">Tunai</option>
                            <option value="Kartu">Kartu</option>
                            <option value="Transfer">Transfer</option>
                        </select>
                    </div>
                </div>
                

                
                <div class="form-group">
                    <label for="keterangan">
                        <i class="fas fa-sticky-note"></i> Keterangan
                    </label>
                    <textarea name="keterangan" id="keterangan" class="form-control" rows="3" 
                              placeholder="Keterangan tambahan (opsional)"></textarea>
                </div>
                
                <div class="form-actions">
                    <button type="submit" class="btn btn-primary">
                        <i class="fas fa-save"></i> Simpan Log BBM
                    </button>
                    <a href="?page=log_bahan_bakar" class="btn btn-outline">
                        <i class="fas fa-times"></i> Batal
                    </a>
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
                                    <th width="25%">Kendaraan</th>
                                    <th width="15%">Total Pengisian</th>
                                    <th width="15%">Total Liter</th>
                                    <th width="15%">Total Biaya</th>
                                    <th width="15%">Pengisian Terakhir</th>
                                    <th width="10%">Aksi</th>
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
                                                         class="vehicle-avatar img" style="width:42px; height:42px; object-fit:cover; border-radius:6px; border:1px solid #e0e0e0;">
                                                <?php else: ?>
                                                    <div class="vehicle-placeholder" style="width:42px; height:42px; display:flex; align-items:center; justify-content:center; background:#f3f4f6; color:#6b7280; border-radius:6px; border:1px solid #e0e0e0;">
                                                        <i class="fas fa-car"></i>
                                                    </div>
                                                <?php endif; ?>
                                            </div>
                                            <div>
                                                <strong class="text-primary"><?= htmlspecialchars($kendaraan['no_reg'] ?? $kendaraan['no_polisi']) ?></strong>
                                                <?php if (!empty($kendaraan['no_polisi'])): ?>
                                                    <br><small class="text-muted">Nopol: <?= htmlspecialchars($kendaraan['no_polisi']) ?></small>
                                                <?php endif; ?>
                                                <br><small class="text-muted"><?= htmlspecialchars($kendaraan['merk'] . ' ' . $kendaraan['tipe']) ?></small>
                                            </div>
                                        </div>
                                    </td>
                                    <td>
                                        <span class="badge badge-primary badge-lg"><?= $kendaraan['total_pengisian'] ?> kali</span>
                                    </td>
                                    <td>
                                        <strong><?= number_format($kendaraan['total_liter'] ?: 0, 2) ?> L</strong>
                                        <?php if ($kendaraan['rata_harga']): ?>
                                            <br><small class="text-muted">Rata-rata: Rp <?= number_format($kendaraan['rata_harga'] ?: 0) ?>/L</small>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <strong class="text-success">Rp <?= number_format($kendaraan['total_biaya'] ?: 0) ?></strong>
                                        <?php if ($kendaraan['km_terakhir']): ?>
                                            <br><small class="text-muted">KM: <?= number_format($kendaraan['km_terakhir']) ?></small>
                                        <?php endif; ?>
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

<!-- Custom styles untuk foto modal yang spesifik untuk halaman ini -->
<style>
.photo-modal {
    display: none;
    position: fixed;
    z-index: 1000;
    left: 0;
    top: 0;
    width: 100%;
    height: 100%;
    background-color: rgba(0, 0, 0, 0.9);
}

.photo-modal .modal-content {
    position: relative;
    margin: auto;
    display: block;
    width: 80%;
    max-width: 700px;
    top: 50%;
    transform: translateY(-50%);
}

.photo-modal .modal-content img {
    width: 100%;
    height: auto;
    border-radius: 8px;
}

.photo-modal .close {
    position: absolute;
    top: 15px;
    right: 35px;
    color: #f1f1f1;
    font-size: 40px;
    font-weight: bold;
    cursor: pointer;
}

.photo-modal .modal-caption {
    text-align: center;
    color: #ccc;
    padding: 10px 0;
    font-size: 16px;
}

.image-preview {
    max-width: 100%;
    max-height: 200px;
    border-radius: 8px;
    margin-top: 10px;
}
</style>

<script>
// Preview foto sebelum upload
.required {
    color: #dc3545;
}

.form-control {
    padding: 0.75rem;
    border: 1px solid #ced4da;
    border-radius: 6px;
    font-size: 0.9rem;
}

.form-control:focus {
    border-color: #667eea;
    box-shadow: 0 0 0 0.2rem rgba(102, 126, 234, 0.25);
}

.photo-section {
    background: #f8f9fa;
    padding: 1.5rem;
    border-radius: 8px;
    margin: 1.5rem 0;
    border-left: 4px solid #667eea;
}

.photo-section h4 {
    margin: 0 0 0.5rem 0;
    color: #2c3e50;
}

.photo-info {
    color: #6c757d;
    font-size: 0.9rem;
    margin: 0 0 1rem 0;
}

.photo-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
    gap: 1rem;
}

.photo-upload {
    background: white;
    padding: 1rem;
    border-radius: 6px;
    border: 2px dashed #dee2e6;
    text-align: center;
    transition: all 0.2s;
}

.photo-upload:hover {
    border-color: #667eea;
}

.photo-upload label {
    display: block;
    font-weight: 600;
    color: #495057;
    margin-bottom: 0.5rem;
    cursor: pointer;
}

.file-input {
    width: 100%;
    padding: 0.5rem;
    border: 1px solid #ced4da;
    border-radius: 4px;
    font-size: 0.8rem;
}

.file-info small {
    color: #6c757d;
    font-size: 0.8rem;
}

.preview-container {
    margin-top: 0.5rem;
    min-height: 60px;
}

.preview-container img {
    max-width: 100%;
    max-height: 100px;
    border-radius: 4px;
}

.form-actions {
    display: flex;
    gap: 1rem;
    justify-content: flex-end;
    margin-top: 2rem;
    padding-top: 1rem;
    border-top: 1px solid #e9ecef;
}

.log-container {
    padding: 0;
}

.log-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(400px, 1fr));
    gap: 1.5rem;
}

.log-card {
    background: white;
    border-radius: 12px;
    box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1);
    overflow: hidden;
    transition: transform 0.2s, box-shadow 0.2s;
}

.log-card:hover {
    transform: translateY(-2px);
    box-shadow: 0 8px 25px rgba(0, 0, 0, 0.15);
}

.log-header {
    background: #f8f9fa;
    padding: 1rem 1.5rem;
    border-bottom: 1px solid #e9ecef;
    display: flex;
    justify-content: space-between;
    align-items: center;
}

.log-date {
    color: #667eea;
    font-weight: 600;
    font-size: 0.9rem;
}

.log-vehicle {
    background: #667eea;
    color: white;
    padding: 0.25rem 0.75rem;
    border-radius: 20px;
    font-size: 0.8rem;
    font-weight: 600;
}

.log-body {
    padding: 1.5rem;
}

.log-details {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(120px, 1fr));
    gap: 0.75rem;
    margin-bottom: 1rem;
}

.detail-item {
    display: flex;
    flex-direction: column;
    gap: 0.25rem;
}

.detail-item .label {
    font-size: 0.8rem;
    color: #6c757d;
    font-weight: 500;
}

.detail-item span:last-child {
    font-size: 0.9rem;
    color: #2c3e50;
}

.total-cost {
    color: #667eea !important;
    font-weight: 600 !important;
}

.photo-documentation {
    border-top: 1px solid #e9ecef;
    padding-top: 1rem;
    margin-top: 1rem;
}

.photo-documentation h5 {
    margin: 0 0 0.75rem 0;
    color: #495057;
    font-size: 0.9rem;
}

.photo-thumbs {
    display: flex;
    gap: 0.5rem;
    flex-wrap: wrap;
}

.photo-thumb {
    position: relative;
    width: 60px;
    height: 60px;
    border-radius: 6px;
    overflow: hidden;
    cursor: pointer;
    border: 2px solid #e9ecef;
    transition: border-color 0.2s;
}

.photo-thumb:hover {
    border-color: #667eea;
}

.photo-thumb img {
    width: 100%;
    height: 100%;
    object-fit: cover;
}

.photo-thumb span {
    position: absolute;
    bottom: 0;
    left: 0;
    right: 0;
    background: rgba(0, 0, 0, 0.7);
    color: white;
    font-size: 0.7rem;
    text-align: center;
    padding: 0.25rem;
}

.log-footer {
    background: #f8f9fa;
    padding: 1rem 1.5rem;
    border-top: 1px solid #e9ecef;
    display: flex;
    justify-content: space-between;
    align-items: center;
    font-size: 0.8rem;
    color: #6c757d;
}

.log-footer i {
    color: #667eea;
    margin-right: 0.25rem;
}

.empty-state {
    text-align: center;
    padding: 4rem 2rem;
    background: white;
    border-radius: 12px;
    box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1);
}

.empty-icon {
    font-size: 4rem;
    color: #dee2e6;
    margin-bottom: 1rem;
}

.photo-modal {
    display: none;
    position: fixed;
    z-index: 1000;
    left: 0;
    top: 0;
    width: 100%;
    height: 100%;
    background-color: rgba(0, 0, 0, 0.9);
}

.modal-content {
    position: relative;
    margin: auto;
    display: block;
    width: 80%;
    max-width: 700px;
    top: 50%;
    transform: translateY(-50%);
}

.modal-content img {
    width: 100%;
    height: auto;
    border-radius: 8px;
}

.close {
    position: absolute;
    top: 15px;
    right: 35px;
    color: #f1f1f1;
    font-size: 40px;
    font-weight: bold;
    cursor: pointer;
}

.modal-caption {
    text-align: center;
    color: #ccc;
    padding: 10px 0;
    font-size: 1.1rem;
}

@media (max-width: 768px) {
    .header-content {
        flex-direction: column;
        align-items: flex-start;
    }
    
    .form-grid {
        grid-template-columns: 1fr;
    }
    
    .photo-grid {
        grid-template-columns: 1fr;
    }
    
    .log-grid {
        grid-template-columns: 1fr;
    }
    
    .log-details {
        grid-template-columns: 1fr;
    }
    
    .form-actions {
        flex-direction: column;
    }
}

/* Vehicle table styling for grouped view */
.vehicle-avatar {
    /* Fixed square container */
    width: 42px;
    height: 42px;
    border-radius: 6px;
    overflow: hidden;
    flex-shrink: 0;
    /* Center content and provide subtle background/border */
    display: inline-flex;
    align-items: center;
    justify-content: center;
    background: #f8f9fa;
    border: 1px solid #e0e0e0;
}

/* Ensure avatar images fill the container without distortion */
.vehicle-avatar > img {
    width: 100%;
    height: 100%;
    object-fit: cover;
    display: block;
}

/* Fallback box when no photo is available */
.vehicle-avatar .vehicle-fallback {
    width: 100%;
    height: 100%;
    display: flex;
    align-items: center;
    justify-content: center;
    color: #6c757d;
    background: linear-gradient(180deg, #f8f9fa, #e9ecef);
}

.badge-lg {
    font-size: 0.9rem;
    padding: 0.5rem 0.75rem;
}

.table-hover tbody tr:hover {
    background-color: rgba(0,123,255,.05);
}

.me-3 {
    margin-right: 1rem;
}

.card {
    border: none;
    box-shadow: 0 0.125rem 0.25rem rgba(0, 0, 0, 0.075);
    border-radius: 0.5rem;
}

.card-header {
    background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
    color: white;
    border-bottom: none;
    border-radius: 0.5rem 0.5rem 0 0 !important;
}

.card-title {
    margin: 0;
    font-weight: 600;
}

.card-actions .badge {
    background: rgba(255,255,255,0.2);
    color: white;
}
</style>

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

// Auto-calculate total biaya
document.addEventListener('DOMContentLoaded', function() {
    const jumlahLiter = document.getElementById('jumlah_liter');
    const hargaPerLiter = document.getElementById('harga_per_liter');
    
    function calculateTotal() {
        if (jumlahLiter && hargaPerLiter) {
            const total = parseFloat(jumlahLiter.value || 0) * parseFloat(hargaPerLiter.value || 0);
            // Bisa ditambahkan field total_biaya jika diperlukan
        }
    }
    
    if (jumlahLiter) jumlahLiter.addEventListener('input', calculateTotal);
    if (hargaPerLiter) hargaPerLiter.addEventListener('input', calculateTotal);
});
</script>


<style>
.page-header {
    margin-bottom: 2rem;
    padding-bottom: 1rem;
    border-bottom: 1px solid #e9ecef;
}

.page-header h1 {
    color: #333;
    margin-bottom: 0.5rem;
}

.card {
    box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1);
    border: none;
    border-radius: 12px;
}

.card-header {
    background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
    color: white;
    border-radius: 12px 12px 0 0;
}

.badge {
    padding: 0.4rem 0.8rem;
    border-radius: 12px;
    font-size: 0.85rem;
}

.badge-warning { background-color: #ffc107; color: #212529; }
.badge-success { background-color: #28a745; color: white; }
.badge-danger { background-color: #dc3545; color: white; }
.badge-info { background-color: #17a2b8; color: white; }
.badge-primary { background-color: #007bff; color: white; }
.badge-secondary { background-color: #6c757d; color: white; }

.table th {
    border-top: none;
    background-color: #f8f9fa;
    font-weight: 600;
}

.btn {
    border-radius: 8px;
    font-weight: 500;
    padding: 0.375rem 1rem;
}

.form-control {
    border-radius: 8px;
    border: 2px solid #e9ecef;
    transition: all 0.2s ease;
}

.form-control:focus {
    border-color: #667eea;
    box-shadow: 0 0 0 0.2rem rgba(102, 126, 234, 0.25);
}

.text-danger {
    color: #dc3545 !important;
}

@media (max-width: 768px) {
    .table-responsive {
        font-size: 0.9rem;
    }
    
    .btn-sm {
        padding: 0.25rem 0.5rem;
        font-size: 0.8rem;
    }
}

/* Move modals down a bit so fixed header doesn't cover modal header */
#cancelModal .modal-dialog,
#detailModal .modal-dialog {
    margin-top: 220px !important;
}

@media (max-width: 576px) {
    #cancelModal .modal-dialog,
    #detailModal .modal-dialog {
        margin-top: 60px !important;
    }
}

/* Custom modal header styling */
.custom-header { background: linear-gradient(90deg,#5a67d8,#9f7aea); color: #fff; align-items:center; }
.custom-header .modal-title { margin:0; font-weight:600; }
.modal-close { border: none; background: rgba(255,255,255,0.12); color: #fff; }
.modal-close:hover { background: rgba(255,255,255,0.18); }
</style>
