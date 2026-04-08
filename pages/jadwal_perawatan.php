<?php
require_once 'includes/auth.php';


end_post:

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
$jadwal_id = $_GET['id'] ?? null;
$msg = '';

// Handle form submissions
if ($_POST) {
    if (!validate_csrf_token($_POST['csrf_token'] ?? '')) {
        $msg = '<div class="alert alert-danger">Token keamanan tidak valid!</div>';
    } else {
        if ($action === 'add' && $can_crud) {
            $kendaraan_id = (int)($_POST['kendaraan_id'] ?? 0);
            // validate kendaraan selection
            if (empty($kendaraan_id) || $kendaraan_id <= 0) {
                $msg = '<div class="alert alert-danger">Silakan pilih kendaraan yang valid.</div>';
                goto end_post;
            }
            $jenis_perawatan = trim($_POST['jenis_perawatan']);
            $deskripsi = trim($_POST['deskripsi']);
            $bengkel = trim($_POST['bengkel'] ?? '');
            $tanggal_perawatan = $_POST['tanggal_perawatan'] ?: null;
            // map km_target input (if present) to existing km_kembali column
            $km_target = (isset($_POST['km_target']) && $_POST['km_target'] !== '') ? (int)$_POST['km_target'] : null;
            // KM Kembali: gunakan nilai dari form km_kembali; fallback ke legacy km_target bila ada
            $km_kembali = (isset($_POST['km_kembali']) && $_POST['km_kembali'] !== '')
                ? (int)$_POST['km_kembali']
                : ((isset($_POST['km_target']) && $_POST['km_target'] !== '') ? (int)$_POST['km_target'] : null);
            $km_saat_perawatan = $_POST['km_saat_perawatan'] ? (int)$_POST['km_saat_perawatan'] : null;
            $estimasi_biaya = (isset($_POST['estimasi_biaya']) && $_POST['estimasi_biaya'] !== '') ? (float)$_POST['estimasi_biaya'] : 0;
            // biaya_aktual defaults to estimasi_biaya if not explicitly provided/edited
            $biaya_aktual = (isset($_POST['biaya_aktual']) && $_POST['biaya_aktual'] !== '')
                ? (float)$_POST['biaya_aktual']
                : ((isset($_POST['estimasi_biaya']) && $_POST['estimasi_biaya'] !== '') ? (float)$_POST['estimasi_biaya'] : null);
            // Banyaknya & Satuan (qty and unit) — default banyaknya to 1 if empty for safer persistence
            $banyaknya = (isset($_POST['banyaknya']) && $_POST['banyaknya'] !== '') ? (float)$_POST['banyaknya'] : 1;
            $satuan = trim($_POST['satuan'] ?? '');
            $teknisi_id = isset($_POST['teknisi_id']) && $_POST['teknisi_id'] !== '' ? (int)$_POST['teknisi_id'] : null;
            $prioritas = $_POST['prioritas'];
            $keterangan = trim($_POST['keterangan']);
            // Server-side overlap checks to enforce availability rules
            if (!empty($tanggal_perawatan)) {
                $tgl_mulai = $tanggal_perawatan;
                $tgl_selesai = $tanggal_perawatan;

                // Block if overlaps with peminjaman_kendaraan Approved/Ongoing
                $c1 = 0;
                if ($st = $mysqli->prepare("SELECT COUNT(*) c FROM peminjaman_kendaraan WHERE kendaraan_id = ? AND LOWER(status) IN ('approved','ongoing') AND tanggal_mulai <= ? AND tanggal_selesai >= ?")) {
                    $q1_end = $tgl_selesai . ' 23:59:59';
                    $q1_start = $tgl_mulai . ' 00:00:00';
                    $st->bind_param('iss', $kendaraan_id, $q1_end, $q1_start);
                    $st->execute();
                    $st->bind_result($c1);
                    $st->fetch();
                    $st->close();
                }

                // Block if overlaps with surat_tugas Disetujui/Dalam Perjalanan
                $c2 = 0;
                if ($st2 = $mysqli->prepare("SELECT COUNT(*) c FROM surat_tugas WHERE kendaraan_id = ? AND status IN ('Disetujui','Dalam Perjalanan') AND tanggal_berangkat <= ? AND (tanggal_kembali IS NULL OR tanggal_kembali >= ?)")) {
                    $q2_end = $tgl_selesai;
                    $q2_start = $tgl_mulai;
                    $st2->bind_param('iss', $kendaraan_id, $q2_end, $q2_start);
                    $st2->execute();
                    $st2->bind_result($c2);
                    $st2->fetch();
                    $st2->close();
                }

                // Block if there is another maintenance scheduled/processing on the same date
                $c3 = 0;
                if ($st3 = $mysqli->prepare("SELECT COUNT(*) c FROM jadwal_perawatan WHERE kendaraan_id = ? AND LOWER(status) IN ('terjadwal','dalam proses') AND DATE(tanggal_perawatan) = ?")) {
                    $q3_date = $tgl_mulai;
                    $st3->bind_param('is', $kendaraan_id, $q3_date);
                    $st3->execute();
                    $st3->bind_result($c3);
                    $st3->fetch();
                    $st3->close();
                }

                // Block if riwayat_perbaikan has an active record on that date
                $c4 = 0;
                if ($st4 = $mysqli->prepare("SELECT COUNT(*) c FROM riwayat_perbaikan WHERE kendaraan_id = ? AND LOWER(status) <> 'selesai' AND DATE(tanggal_perbaikan) = ?")) {
                    $q4_date = $tgl_mulai;
                    $st4->bind_param('is', $kendaraan_id, $q4_date);
                    $st4->execute();
                    $st4->bind_result($c4);
                    $st4->fetch();
                    $st4->close();
                }

                if (($c1 + $c2 + $c3 + $c4) > 0) {
                    $msg = '<div class="alert alert-danger">Tidak dapat menjadwalkan perawatan: kendaraan sedang dipakai/ditugaskan/dirawat pada tanggal tersebut.</div>';
                    goto end_post;
                }
            }

            // Persist including quantity (banyaknya) and unit (satuan)
            $stmt = $mysqli->prepare("INSERT INTO jadwal_perawatan (kendaraan_id, jenis_perawatan, deskripsi, bengkel, tanggal_perawatan, km_kembali, km_saat_perawatan, estimasi_biaya, biaya_aktual, banyaknya, satuan, prioritas, keterangan, created_by, teknisi_id) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
            // Types: i,kendaraan_id; s,jenis; s,deskripsi; s,bengkel; s,tanggal_perawatan; i,km_kembali; i,km_saat_perawatan; d,estimasi; d,biaya_aktual; d,banyaknya; s,satuan; s,prioritas; s,keterangan; i,created_by; i,teknisi_id
            $stmt->bind_param('issssiidddsssii', $kendaraan_id, $jenis_perawatan, $deskripsi, $bengkel, $tanggal_perawatan, $km_kembali, $km_saat_perawatan, $estimasi_biaya, $biaya_aktual, $banyaknya, $satuan, $prioritas, $keterangan, $current_user_id, $teknisi_id);
            
            if ($stmt->execute()) {
                // If the scheduled date is today, update kendaraan status to Perbaikan/Maintenance
                $tp = $tanggal_perawatan ? date('Y-m-d', strtotime($tanggal_perawatan)) : null;
                if ($tp && $tp === date('Y-m-d')) {
                    $u = $mysqli->prepare("UPDATE kendaraan SET status_kendaraan = 'Perbaikan', status_peminjaman = 'Maintenance' WHERE id = ?");
                    if ($u) { $u->bind_param('i', $kendaraan_id); $u->execute(); $u->close(); }
                }

                $_SESSION['swal'] = [
                    'icon' => 'success',
                    'title' => 'Berhasil!',
                    'text' => 'Jadwal perawatan berhasil ditambahkan!'
                ];
                log_activity('ADD_JADWAL_PERAWATAN', "Menambah jadwal perawatan: $jenis_perawatan untuk kendaraan ID $kendaraan_id");
                header('Location: index.php?page=jadwal_perawatan');
                exit;
            } else {
                $msg = '<div class="alert alert-danger">Error: ' . $stmt->error . '</div>';
            }
            $stmt->close();
            
        } elseif ($action === 'edit' && $can_crud && $jadwal_id) {
            $kendaraan_id = (int)($_POST['kendaraan_id'] ?? 0);
            // validate kendaraan selection
            if (empty($kendaraan_id) || $kendaraan_id <= 0) {
                $msg = '<div class="alert alert-danger">Silakan pilih kendaraan yang valid.</div>';
                goto end_post;
            }
            $jenis_perawatan = trim($_POST['jenis_perawatan']);
            $deskripsi = trim($_POST['deskripsi']);
            $bengkel = trim($_POST['bengkel'] ?? '');
            $tanggal_perawatan = $_POST['tanggal_perawatan'] ?: null;
            // KM Kembali: gunakan nilai dari form km_kembali; fallback ke legacy km_target bila ada
            $km_kembali = (isset($_POST['km_kembali']) && $_POST['km_kembali'] !== '')
                ? (int)$_POST['km_kembali']
                : ((isset($_POST['km_target']) && $_POST['km_target'] !== '') ? (int)$_POST['km_target'] : null);
            $km_saat_perawatan = $_POST['km_saat_perawatan'] ? (int)$_POST['km_saat_perawatan'] : null;
            $estimasi_biaya = (isset($_POST['estimasi_biaya']) && $_POST['estimasi_biaya'] !== '') ? (float)$_POST['estimasi_biaya'] : 0;
            // biaya_aktual defaults to estimasi_biaya if not explicitly provided/edited
            $biaya_aktual = (isset($_POST['biaya_aktual']) && $_POST['biaya_aktual'] !== '')
                ? (float)$_POST['biaya_aktual']
                : ((isset($_POST['estimasi_biaya']) && $_POST['estimasi_biaya'] !== '') ? (float)$_POST['estimasi_biaya'] : null);
            // Banyaknya & Satuan (qty and unit) — default banyaknya to 1 if empty
            $banyaknya = (isset($_POST['banyaknya']) && $_POST['banyaknya'] !== '') ? (float)$_POST['banyaknya'] : 1;
            $satuan = trim($_POST['satuan'] ?? '');
            $teknisi_id = isset($_POST['teknisi_id']) && $_POST['teknisi_id'] !== '' ? (int)$_POST['teknisi_id'] : null;
            $status = $_POST['status'];
            $prioritas = $_POST['prioritas'];
            $tanggal_selesai = $_POST['tanggal_selesai'] ?: null;
            // If status is being set to Selesai, require tanggal_selesai and biaya_aktual
                if (strtolower($status) === 'selesai') {
                if (empty($tanggal_selesai) || $biaya_aktual === null) {
                    $msg = '<div class="alert alert-danger">Untuk menandai selesai, harap isi Tanggal Selesai dan Biaya Aktual.</div>';
                    goto end_post;
                }
            }
            $keterangan = trim($_POST['keterangan']);
            // Update using actual schema: tanggal_perawatan and km_kembali
            // Note: table has columns estimasi_biaya and biaya_aktual; there is no 'biaya' column
            // Server-side overlap checks when changing date/vehicle or marking active
            if (!empty($tanggal_perawatan)) {
                $tgl_mulai = $tanggal_perawatan; $tgl_selesai = $tanggal_perawatan;
                $cid = (int)$jadwal_id;
                $cc1 = 0;
                if ($st = $mysqli->prepare("SELECT COUNT(*) c FROM peminjaman_kendaraan WHERE kendaraan_id = ? AND LOWER(status) IN ('approved','ongoing') AND tanggal_mulai <= ? AND tanggal_selesai >= ?")) {
                    $q1_end_e = $tgl_selesai . ' 23:59:59';
                    $q1_start_e = $tgl_mulai . ' 00:00:00';
                    $st->bind_param('iss', $kendaraan_id, $q1_end_e, $q1_start_e);
                    $st->execute(); $st->bind_result($cc1); $st->fetch(); $st->close();
                }
                $cc2 = 0;
                if ($st2 = $mysqli->prepare("SELECT COUNT(*) c FROM surat_tugas WHERE kendaraan_id = ? AND status IN ('Disetujui','Dalam Perjalanan') AND tanggal_berangkat <= ? AND (tanggal_kembali IS NULL OR tanggal_kembali >= ?)")) {
                    $q2_end_e = $tgl_selesai;
                    $q2_start_e = $tgl_mulai;
                    $st2->bind_param('iss', $kendaraan_id, $q2_end_e, $q2_start_e);
                    $st2->execute(); $st2->bind_result($cc2); $st2->fetch(); $st2->close();
                }
                $cc3 = 0;
                if ($st3 = $mysqli->prepare("SELECT COUNT(*) c FROM jadwal_perawatan WHERE id <> ? AND kendaraan_id = ? AND LOWER(status) IN ('terjadwal','dalam proses') AND DATE(tanggal_perawatan) = ?")) {
                    $q3_date_e = $tgl_mulai;
                    $st3->bind_param('iis', $cid, $kendaraan_id, $q3_date_e);
                    $st3->execute(); $st3->bind_result($cc3); $st3->fetch(); $st3->close();
                }
                $cc4 = 0;
                if ($st4 = $mysqli->prepare("SELECT COUNT(*) c FROM riwayat_perbaikan WHERE kendaraan_id = ? AND LOWER(status) <> 'selesai' AND DATE(tanggal_perbaikan) = ?")) {
                    $q4_date_e = $tgl_mulai;
                    $st4->bind_param('is', $kendaraan_id, $q4_date_e);
                    $st4->execute(); $st4->bind_result($cc4); $st4->fetch(); $st4->close();
                }
                if (($cc1 + $cc2 + $cc3 + $cc4) > 0) {
                    $msg = '<div class="alert alert-danger">Tidak dapat memperbarui: kendaraan sedang dipakai/ditugaskan/dirawat pada tanggal tersebut.</div>';
                    goto end_post;
                }
            }

            // Update jadwal_perawatan (tetap gunakan kendaraan_id sebagai foreign key), termasuk banyaknya dan satuan
            $stmt = $mysqli->prepare("UPDATE jadwal_perawatan SET kendaraan_id=?, jenis_perawatan=?, deskripsi=?, bengkel=?, tanggal_perawatan=?, km_kembali=?, km_saat_perawatan=?, estimasi_biaya=?, status=?, prioritas=?, tanggal_selesai=?, biaya_aktual=?, banyaknya=?, satuan=?, keterangan=?, teknisi_id=?, updated_by=? WHERE id=?");
            // Types: i,s,s,s,s,i,i,d,s,s,s,d,d,s,s,i,i,i
            $stmt->bind_param('issssiidsssddssiii', $kendaraan_id, $jenis_perawatan, $deskripsi, $bengkel, $tanggal_perawatan, $km_kembali, $km_saat_perawatan, $estimasi_biaya, $status, $prioritas, $tanggal_selesai, $biaya_aktual, $banyaknya, $satuan, $keterangan, $teknisi_id, $current_user_id, $jadwal_id);
            
            if ($stmt->execute()) {
                // Ambil no_reg berdasarkan kendaraan_id agar operasi selanjutnya menggunakan no_reg
                $no_reg = null;
                if ($veh = $mysqli->prepare("SELECT no_reg FROM kendaraan WHERE id = ?")) {
                    $veh->bind_param('i', $kendaraan_id);
                    $veh->execute();
                    $veh->bind_result($no_reg);
                    $veh->fetch();
                    $veh->close();
                }

                $_SESSION['swal'] = [
                    'icon' => 'success',
                    'title' => 'Berhasil!',
                    'text' => 'Jadwal perawatan berhasil diperbarui!'
                ];
                log_activity('EDIT_JADWAL_PERAWATAN', "Memperbarui jadwal perawatan Kendaraan no_reg: $no_reg");
                
                // Set status kendaraan berdasarkan status perawatan menggunakan no_reg
                if ($no_reg && strtolower($status) === 'dalam proses') {
                    $u = $mysqli->prepare("UPDATE kendaraan SET status_kendaraan = 'Perbaikan', status_peminjaman = 'Maintenance' WHERE no_reg = ?");
                    if ($u) { $u->bind_param('s', $no_reg); $u->execute(); $u->close(); }
                }
                // Jika perawatan selesai, kembalikan status kendaraan (berdasarkan no_reg) dan redirect ke Riwayat
                if ($no_reg && strtolower($status) === 'selesai') {
                    $u = $mysqli->prepare("UPDATE kendaraan SET status_kendaraan = 'Operasional', status_peminjaman = 'Tersedia' WHERE no_reg = ?");
                    if ($u) { $u->bind_param('s', $no_reg); $u->execute(); $u->close(); }

                    $_SESSION['swal'] = [
                        'icon' => 'success',
                        'title' => 'Perawatan Selesai',
                        'text' => 'Jadwal perawatan telah ditandai selesai dan dipindahkan ke Riwayat.'
                    ];
                    header('Location: index.php?page=riwayat_perawatan');
                    exit;
                }
                header('Location: index.php?page=jadwal_perawatan');
                exit;
            } else {
                $msg = '<div class="alert alert-danger">Error: ' . $stmt->error . '</div>';
            }
            $stmt->close();
        }
    }
}

// Handle delete
if ($action === 'delete' && $can_crud && $jadwal_id) {
    $stmt = $mysqli->prepare("DELETE FROM jadwal_perawatan WHERE id = ?");
    $stmt->bind_param('i', $jadwal_id);
    if ($stmt->execute()) {
        $_SESSION['swal'] = [
            'icon' => 'success',
            'title' => 'Berhasil!',
            'text' => 'Jadwal perawatan berhasil dihapus!'
        ];
    log_activity('DELETE_JADWAL_PERAWATAN', "Menghapus jadwal perawatan ID: $jadwal_id");
        header('Location: index.php?page=jadwal_perawatan');
        exit;
    } else {
        $msg = '<div class="alert alert-danger">Error: ' . $stmt->error . '</div>';
    }
    $stmt->close();
    $action = 'list';
}

// Get schedule data for edit or view (include creator/updater/teknisi names)
$jadwal_data = null;
if (($action === 'edit' || $action === 'view') && $jadwal_id) {
    $stmt = $mysqli->prepare(
        "SELECT jp.*, k.no_polisi, k.merk, k.tipe, p.nama_lengkap as teknisi_name, cb.nama_lengkap AS created_by_name, ub.nama_lengkap AS updated_by_name
         FROM jadwal_perawatan jp
         LEFT JOIN kendaraan k ON jp.kendaraan_id = k.id
         LEFT JOIN pengguna p ON jp.teknisi_id = p.id
         LEFT JOIN pengguna cb ON jp.created_by = cb.id
         LEFT JOIN pengguna ub ON jp.updated_by = ub.id
         WHERE jp.id = ?"
    );
    $stmt->bind_param('i', $jadwal_id);
    $stmt->execute();
    $result = $stmt->get_result();
    $jadwal_data = $result->fetch_assoc();
    $stmt->close();
}

// Get vehicles for dropdown
$vehicles = [];
$vehicles_query = "SELECT id, no_polisi, no_reg, merk, tipe FROM kendaraan ORDER BY no_polisi";
$vehicles_result = $mysqli->query($vehicles_query);
if ($vehicles_result) {
    $vehicles = $vehicles_result->fetch_all(MYSQLI_ASSOC);
}

// Get teknisi/users for dropdown
$users = [];
$users_result = $mysqli->query("SELECT id, nama_lengkap FROM pengguna ORDER BY nama_lengkap");
if ($users_result) {
    $users = $users_result->fetch_all(MYSQLI_ASSOC);
}
?>

<div class="content-wrapper">
<?php
// SweetAlert2 notification
if (!empty($_SESSION['swal'])): ?>
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <script>
    document.addEventListener('DOMContentLoaded', function() {
        Swal.fire({
            icon: '<?= $_SESSION['swal']['icon'] ?>',
            title: '<?= $_SESSION['swal']['title'] ?>',
            text: '<?= $_SESSION['swal']['text'] ?>',
            confirmButtonColor: '#3085d6',
            timer: 2000
        });
    });
    </script>
<?php unset($_SESSION['swal']); endif; ?>
    <div class="page-header">
                <h3 class="page-title">
                    <i class="fas fa-calendar-check"></i>
                    Jadwal Perawatan
                </h3>
            <div class="header-actions">
                <?php if ($can_crud): ?>
                    <a href="?page=jadwal_perawatan&action=add" class="btn btn-success">
                        <i class="fas fa-plus"></i> Tambah Jadwal
                    </a>
                <?php endif; ?>
            </div>
    </div>

    <div class="content">
        <?= $msg ?>

        <?php if ($action === 'list'): ?>
            <!-- List View -->
            <div class="card">
                <div class="card-header">
                    <h4 class="card-title">Daftar Jadwal Perawatan</h4>
                </div>
                <div class="card-body">
                    <div class="table-responsive">
                        <table class="table table-striped">
                            <thead>
                                <tr>
                                    <th>No</th>
                                    <th>Kendaraan</th>
                                    <th>Jenis Perawatan</th>
                                    <th>Tanggal Perawatan</th>
                                    <th>Bengkel</th>
                                    <th>Estimasi Biaya</th>
                                    <th>Status</th>
                                    <th>Prioritas</th>
                                    <?php if ($can_crud): ?>
                                        <th>Aksi</th>
                                    <?php endif; ?>
                                </tr>
                            </thead>
                            <tbody id="jadwal-table-container">
                                <?php
                                // Fallback for non-JS users and initial render
                                if ($action === 'list') {
                                    include dirname(__DIR__) . '/ajax/load_jadwal_table.php';
                                }
                                ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>

        <?php elseif ($action === 'add' || $action === 'edit'): ?>
            <!-- Add/Edit Form -->
            <div class="card">
                <div class="card-header">
                    <h4 class="card-title">
                        <?= $action === 'add' ? 'Tambah' : 'Edit' ?> Jadwal Perawatan
                    </h4>
                </div>
                <div class="card-body">
                    <form id="jadwal-form" method="POST">
                        <?php if ($action === 'edit' && $jadwal_data): ?>
                            <input type="hidden" name="id" value="<?= $jadwal_data['id'] ?>">
                            <input type="hidden" name="action" value="edit">
                        <?php else: ?>
                            <input type="hidden" name="action" value="add">
                        <?php endif; ?>
                        <div class="row">
                            <div class="col-md-6">
                                <div class="form-group">
                                    <label for="kendaraan_id">Kendaraan *</label>
                                    <!-- Searchable kendaraan select (enhanced by Select2) -->
                                    <select name="kendaraan_id" id="kendaraan_id" class="form-control">
                                        <option value="">Pilih Kendaraan...</option>
                                        <?php foreach ($vehicles as $vehicle): ?>
                                            <?php $label = htmlspecialchars($vehicle['no_polisi'] . ' - ' . $vehicle['merk'] . ' ' . $vehicle['tipe']); ?>
                                            <option value="<?= $vehicle['id'] ?>" data-no_reg="<?= htmlspecialchars($vehicle['no_reg'] ?? '') ?>" <?= (isset($jadwal_data) && $jadwal_data['kendaraan_id'] == $vehicle['id']) ? 'selected' : '' ?>>
                                                <?= $label ?>
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="form-group">
                                    <label for="jenis_perawatan">Jenis Perawatan *</label>
                                    <input type="text" name="jenis_perawatan" id="jenis_perawatan" 
                                           class="form-control" required
                                           value="<?= isset($jadwal_data) ? htmlspecialchars($jadwal_data['jenis_perawatan']) : '' ?>"
                                           placeholder="Contoh: Servis Berkala 5000 KM">
                                </div>
                            </div>
                        </div>

                        <div class="form-group">
                            <label for="deskripsi">Deskripsi</label>
                            <textarea name="deskripsi" id="deskripsi" class="form-control" rows="2"
                                      placeholder="Detail pekerjaan yang akan dilakukan"><?= isset($jadwal_data) ? htmlspecialchars($jadwal_data['deskripsi'] ?? '') : '' ?></textarea>
                        </div>

                        <div class="row">
                            <div class="col-md-4">
                                <div class="form-group">
                                    <label for="bengkel">Bengkel</label>
                                    <input type="text" name="bengkel" id="bengkel" class="form-control" value="<?= isset($jadwal_data) ? htmlspecialchars($jadwal_data['bengkel'] ?? '') : '' ?>" placeholder="Nama bengkel">
                                </div>
                            </div>
                            <!-- tanggal_perawatan input moved below (required field) -->
                            <div class="col-md-4">
                                <div class="form-group">
                                    <label for="km_saat_perawatan">KM Saat Perawatan</label>
                                    <input type="number" name="km_saat_perawatan" id="km_saat_perawatan" class="form-control" min="0" value="<?= isset($jadwal_data) ? htmlspecialchars($jadwal_data['km_saat_perawatan'] ?? '') : '' ?>">
                                </div>
                            </div>
                        </div>

                        <div class="row">
                            <div class="col-md-4">
                                <div class="form-group">
                                    <label for="tanggal_perawatan">Tanggal Perawatan *</label>
                     <input type="date" name="tanggal_perawatan" id="tanggal_perawatan" 
                         class="form-control" required
                         value="<?= isset($jadwal_data) ? htmlspecialchars($jadwal_data['tanggal_perawatan'] ?? '') : '' ?>">
                                </div>
                            </div>
                            <div class="col-md-4">
                                <div class="form-group">
                     <label for="km_kembali">KM Kembali</label>
                     <input type="number" name="km_kembali" id="km_kembali" 
                         class="form-control" 
                         value="<?= isset($jadwal_data) ? htmlspecialchars($jadwal_data['km_kembali'] ?? '') : '' ?>"
                         placeholder="Contoh: 14500">
                                </div>
                            </div>
                            <div class="col-md-4">
                                <div class="form-group">
                                    <label for="estimasi_biaya">Estimasi Biaya</label>
                     <input type="number" name="estimasi_biaya" id="estimasi_biaya" 
                         class="form-control" step="1"
                         value="<?= isset($jadwal_data) ? htmlspecialchars($jadwal_data['estimasi_biaya'] ?? '') : '' ?>"
                         placeholder="Contoh: 500000">
                                </div>
                            </div>
                        </div>

                        <div class="row">
                            <div class="col-md-4">
                                <div class="form-group">
                                    <label for="banyaknya">Banyaknya</label>
                                    <input type="number" name="banyaknya" id="banyaknya"
                                           class="form-control" min="0"
                                           value="<?= isset($jadwal_data) ? htmlspecialchars($jadwal_data['banyaknya'] ?? '1') : '1' ?>"
                                           >
                                </div>
                            </div>
                            <div class="col-md-4">
                                <div class="form-group">
                                    <label for="satuan">Satuan</label>
                                    <input type="text" name="satuan" id="satuan" class="form-control"
                                           value="<?= isset($jadwal_data) ? htmlspecialchars($jadwal_data['satuan'] ?? '') : '' ?>"
                                           placeholder="Liter, pcs, botol">
                                </div>
                            </div>
                        </div>

                        <div class="row">
                            <?php if ($action === 'edit'): ?>
                                <div class="col-md-3">
                                    <div class="form-group">
                                        <label for="status">Status</label>
                                        <select name="status" id="status" class="form-control">
                                            <?php 
                                            $status_options = ['Terjadwal', 'Dalam Proses', 'Selesai', 'Terlewat', 'Dibatalkan'];
                                            foreach ($status_options as $status): 
                                            ?>
                                                <option value="<?= $status ?>" 
                                                        <?= (isset($jadwal_data) && $jadwal_data['status'] == $status) ? 'selected' : '' ?>>
                                                    <?= $status ?>
                                                </option>
                                            <?php endforeach; ?>
                                        </select>
                                    </div>
                                </div>
                                <div class="col-md-3">
                                    <div class="form-group">
                                        <label for="tanggal_selesai">Tanggal Selesai</label>
                                        <input type="date" name="tanggal_selesai" id="tanggal_selesai" 
                                               class="form-control" 
                                               value="<?= isset($jadwal_data) ? $jadwal_data['tanggal_selesai'] : '' ?>">
                                    </div>
                                </div>
                                <div class="col-md-3">
                                    <div class="form-group">
                                        <label for="biaya_aktual">Biaya Aktual</label>
                                        <input type="number" name="biaya_aktual" id="biaya_aktual" 
                                            class="form-control" step="0.01"
                                            value="<?= isset($jadwal_data) ? htmlspecialchars(($jadwal_data['biaya_aktual'] ?? ($jadwal_data['estimasi_biaya'] ?? ''))) : '' ?>">
                                    </div>
                                </div>
                                <div class="col-md-3">
                                    <div class="form-group">
                                        <label for="prioritas">Prioritas</label>
                                        <select name="prioritas" id="prioritas" class="form-control">
                                            <?php 
                                            $prioritas_options = ['Rendah', 'Normal', 'Tinggi', 'Urgent'];
                                            $selected_prioritas = isset($jadwal_data['prioritas']) ? $jadwal_data['prioritas'] : 'Normal';
                                            foreach ($prioritas_options as $prioritas): 
                                            ?>
                                                <option value="<?= $prioritas ?>" <?= ($selected_prioritas == $prioritas) ? 'selected' : '' ?>>
                                                    <?= $prioritas ?>
                                                </option>
                                            <?php endforeach; ?>
                                        </select>
                                    </div>
                                </div>
                            <?php else: ?>
                                <div class="col-md-12">
                                    <div class="form-group">
                                        <label for="prioritas">Prioritas</label>
                                        <select name="prioritas" id="prioritas" class="form-control">
                                            <?php 
                                            $prioritas_options = ['Rendah', 'Normal', 'Tinggi', 'Urgent'];
                                            $selected_prioritas = isset($jadwal_data['prioritas']) ? $jadwal_data['prioritas'] : 'Normal';
                                            foreach ($prioritas_options as $prioritas): 
                                            ?>
                                                <option value="<?= $prioritas ?>" <?= ($selected_prioritas == $prioritas) ? 'selected' : '' ?>>
                                                    <?= $prioritas ?>
                                                </option>
                                            <?php endforeach; ?>
                                        </select>
                                    </div>
                                </div>
                            <?php endif; ?>
                        </div>

                        <div class="row">
                            <div class="col-md-6">
                                <div class="form-group">
                                    <label for="teknisi_id">Teknisi</label>
                                    <select name="teknisi_id" id="teknisi_id" class="form-control">
                                        <option value="">-- Pilih Teknisi (opsional) --</option>
                                        <?php foreach ($users as $u): ?>
                                            <option value="<?= $u['id'] ?>" <?= (isset($jadwal_data) && ($jadwal_data['teknisi_id'] ?? '') == $u['id']) ? 'selected' : '' ?>><?= htmlspecialchars($u['nama_lengkap']) ?></option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                            </div>
                        </div>

                        <!-- <div class="form-group">
                            <label for="keterangan">Keterangan</label>
                            <textarea name="keterangan" id="keterangan" class="form-control" rows="3"><?= isset($jadwal_data) ? htmlspecialchars($jadwal_data['keterangan'] ?? '') : '' ?></textarea>
                        </div> -->

                        <div class="form-group">
                            <input type="hidden" name="csrf_token" value="<?= generate_csrf_token() ?>">
                            <!-- Availability alert will be shown here -->
                            <div id="availability-alert" class="mb-2"></div>
                            <button type="submit" href="?page=jadwal_perawatan" class="btn btn-primary">
                                <i class="fas fa-save"></i> Simpan
                            </button>
                            <a href="?page=jadwal_perawatan" class="btn btn-secondary">
                                <i class="fas fa-times"></i> Batal
                            </a>
                        </div>
                    </form>
                </div>
            </div>
        <?php endif; ?>

        <?php if ($action === 'view' && $jadwal_id && $jadwal_data): ?>
            <div class="card">
                <div class="card-header">
                    <h4 class="card-title">Detail Jadwal Perawatan</h4>
                </div>
                <div class="card-body">
                    <table class="table table-bordered">
                        <tr><th>ID</th><td><?= $jadwal_data['id'] ?></td></tr>
                        <tr><th>Kendaraan</th><td><?= htmlspecialchars($jadwal_data['no_polisi'] ?? '') . ' - ' . htmlspecialchars($jadwal_data['merk'] ?? '') ?></td></tr>
                        <tr><th>Jenis Perawatan</th><td><?= htmlspecialchars($jadwal_data['jenis_perawatan'] ?? '-') ?></td></tr>
                        <tr><th>Tanggal Perawatan</th><td><?= !empty($jadwal_data['tanggal_perawatan']) ? date('d/m/Y', strtotime($jadwal_data['tanggal_perawatan'])) : ( !empty($jadwal_data['jadwal_tanggal']) ? date('d/m/Y', strtotime($jadwal_data['jadwal_tanggal'])) : '-' ) ?></td></tr>
                        <tr><th>Tanggal Selesai</th><td><?= !empty($jadwal_data['tanggal_selesai']) ? date('d/m/Y', strtotime($jadwal_data['tanggal_selesai'])) : '-' ?></td></tr>
                        <tr><th>Bengkel</th><td><?= htmlspecialchars($jadwal_data['bengkel'] ?? '-') ?></td></tr>
                        <tr><th>Banyaknya</th><td><?= isset($jadwal_data['banyaknya']) && $jadwal_data['banyaknya'] !== null ? rtrim(rtrim(number_format((float)$jadwal_data['banyaknya'], 2, '.', ''), '0'), '.') : '-' ?></td></tr>
                        <tr><th>Satuan</th><td><?= htmlspecialchars($jadwal_data['satuan'] ?? '-') ?></td></tr>
                        <tr><th>KM Kembali</th><td><?= !empty($jadwal_data['km_kembali']) ? number_format($jadwal_data['km_kembali']) . ' KM' : '-' ?></td></tr>
                        <tr><th>KM Saat Perawatan</th><td><?= !empty($jadwal_data['km_saat_perawatan']) ? number_format($jadwal_data['km_saat_perawatan']) . ' KM' : '-' ?></td></tr>
                        <tr><th>Estimasi Biaya</th><td><?= !empty($jadwal_data['estimasi_biaya']) ? 'Rp ' . number_format($jadwal_data['estimasi_biaya']) : '-' ?></td></tr>
                        <tr><th>Biaya Aktual</th><td><?= !empty($jadwal_data['biaya_aktual']) ? 'Rp ' . number_format($jadwal_data['biaya_aktual']) : '-' ?></td></tr>
                        <tr><th>Status</th><td><?= htmlspecialchars($jadwal_data['status'] ?? '-') ?></td></tr>
                        <tr><th>Prioritas</th><td><?= htmlspecialchars($jadwal_data['prioritas'] ?? '-') ?></td></tr>
                        <tr><th>Teknisi</th><td><?= htmlspecialchars($jadwal_data['teknisi_name'] ?? ($jadwal_data['teknisi_id'] ?? '-')) ?></td></tr>
                        <tr><th>Reminder Sent</th><td><?= isset($jadwal_data['reminder_sent']) ? ($jadwal_data['reminder_sent'] ? 'Ya' : 'Tidak') : '-' ?></td></tr>
                        <tr><th>Dibuat</th><td><?= !empty($jadwal_data['created_at']) ? date('d/m/Y H:i', strtotime($jadwal_data['created_at'])) . ' oleh ' . htmlspecialchars($jadwal_data['created_by_name'] ?? ($jadwal_data['created_by'] ?? '-')) : '-' ?></td></tr>
                        <tr><th>Terakhir diubah</th><td><?= !empty($jadwal_data['updated_at']) ? date('d/m/Y H:i', strtotime($jadwal_data['updated_at'])) . ' oleh ' . htmlspecialchars($jadwal_data['updated_by_name'] ?? ($jadwal_data['updated_by'] ?? '-')) : '-' ?></td></tr>
                    </table>
                    <a href="?page=jadwal_perawatan" class="btn btn-secondary">
                        <i class="fas fa-arrow-left"></i> Kembali
                    </a>
                </div>
            </div>
        <?php endif; ?>
    </div>
</div>

<style>
.badge {
    display: inline-flex;
    align-items: center;
    gap: 0.25rem;
    padding: 0.375rem 0.75rem;
    font-size: 0.75rem;
    font-weight: 500;
    border-radius: 0.375rem;
}

.badge i {
    font-size: 0.8em;
}

.table-danger {
    background-color: #f8d7da !important;
}

.status-dropdown {
    width: auto;
    min-width: 120px;
}
</style>

<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
<script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
<!-- Select2 for searchable selects -->
<link href="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/css/select2.min.css" rel="stylesheet" />
<script src="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/js/select2.min.js"></script>
<script src="assets/js/main.js"></script>
<script>
document.addEventListener('DOMContentLoaded', function() {
    // Initialize Jadwal Perawatan module if on list page
    if (document.getElementById('jadwal-table-container')) {
        // Reload table content to enable AJAX features
        JadwalPerawatan.loadTable();
    }
    
    // Handle form submission: allow normal POST so server-side PHP will process add/edit and redirect.
    const jadwalForm = document.getElementById('jadwal-form');
    if (jadwalForm) {
        // Intentionally do not intercept the submit event here.
        // The form uses method="POST" and the top of this PHP file handles add/edit and redirects back to the jadwal page.
        // If you later want AJAX saving, implement JadwalPerawatan.saveJadwal to POST and handle the redirect on the client.
    }
});
</script>
<script>
// Initialize Select2 for kendaraan select
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
    }

    // Client-side: when status is Selesai, require tanggal_selesai and biaya_aktual
    function toggleSelesaiRequirements() {
        var status = $('#status').val();
        if (status && status.toLowerCase() === 'selesai') {
            $('#tanggal_selesai').prop('required', true);
            $('#biaya_aktual').prop('required', true);
        } else {
            $('#tanggal_selesai').prop('required', false);
            $('#biaya_aktual').prop('required', false);
        }
    }
    $('#status').on('change', toggleSelesaiRequirements);
    toggleSelesaiRequirements();
});
</script>
<script>
// Availability checks for kendaraan & teknisi
;(function(){
    const kendaraanEl = document.getElementById('kendaraan_id');
    const teknisiEl = document.getElementById('teknisi_id');
    const tanggalEl = document.getElementById('tanggal_perawatan');
    const tanggalSelesaiEl = document.getElementById('tanggal_selesai');
    const submitBtn = document.querySelector('#jadwal-form button[type=submit]');
    const alertContainer = document.getElementById('availability-alert');

    if (!alertContainer) return;

    function showAlert(html, level='danger'){
        alertContainer.innerHTML = `<div class="alert alert-${level}" role="alert">${html}</div>`;
    }

    function clearAlert(){ alertContainer.innerHTML = ''; }

    async function checkAvailability(){
        clearAlert();
        if (!kendaraanEl || !tanggalEl) return;
        const kendaraan = kendaraanEl.value;
        const teknisi = teknisiEl ? teknisiEl.value : 0;
        const tanggalMulai = tanggalEl.value;
        const tanggalKembali = tanggalSelesaiEl ? tanggalSelesaiEl.value : '';

        if (!kendaraan || !tanggalMulai) return;

        const params = new URLSearchParams({ kendaraaN_id: kendaraan }); // fallback overwritten below
        // build proper params
        const url = `ajax/check_availability.php?kendaraan_id=${encodeURIComponent(kendaraan)}&pengguna_id=${encodeURIComponent(teknisi)}&tanggal_berangkat=${encodeURIComponent(tanggalMulai)}&tanggal_kembali=${encodeURIComponent(tanggalKembali)}`;

        try {
            const res = await fetch(url, { credentials: 'same-origin' });
            if (!res.ok) throw new Error('HTTP ' + res.status);
            const data = await res.json();
            let problems = [];
            if (data.vehicle_available === false) {
                problems = problems.concat(data.vehicle_reasons || []);
            }
            if (data.user_available === false) {
                problems = problems.concat(data.user_reasons || []);
            }
            if (problems.length > 0) {
                showAlert('<strong>Konflik:</strong><br>' + problems.map(p => `- ${p}`).join('<br>'));
                if (submitBtn) submitBtn.disabled = true;
            } else {
                clearAlert();
                if (submitBtn) submitBtn.disabled = false;
            }
        } catch (err) {
            showAlert('Gagal memeriksa ketersediaan: ' + err.message, 'warning');
        }
    }

    [kendaraanEl, teknisiEl, tanggalEl, tanggalSelesaiEl].forEach(el => {
        if (!el) return;
        el.addEventListener('change', () => checkAvailability());
        el.addEventListener('blur', () => checkAvailability());
    });
})();
</script>
