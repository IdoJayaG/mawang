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

$action = $_GET['action'] ?? 'list';
$jadwal_id = $_GET['id'] ?? null;
$msg = '';

// Handle form submissions
if ($_POST) {
    if (!validate_csrf_token($_POST['csrf_token'] ?? '')) {
        $msg = '<div class="alert alert-danger">Token keamanan tidak valid!</div>';
    } else {
        if ($action === 'add' && $can_crud) {
            $kendaraan_id = (int)$_POST['kendaraan_id'];
            $jenis_perawatan = trim($_POST['jenis_perawatan']);
            $deskripsi = trim($_POST['deskripsi']);
            $bengkel = trim($_POST['bengkel'] ?? '');
            // Use tanggal_perawatan as the main date; remove separate jadwal input
            $tanggal_perawatan = $_POST['tanggal_perawatan'] ?: null;
            // store tanggal_perawatan into jadwal_tanggal column
            $jadwal_tanggal = $tanggal_perawatan;
            $km_target = $_POST['km_target'] ? (int)$_POST['km_target'] : null;
            $km_saat_perawatan = $_POST['km_saat_perawatan'] ? (int)$_POST['km_saat_perawatan'] : null;
            $estimasi_biaya = $_POST['estimasi_biaya'] ? (float)$_POST['estimasi_biaya'] : 0;
            $biaya = $_POST['biaya'] !== '' ? (float)$_POST['biaya'] : null;
            $teknisi_id = isset($_POST['teknisi_id']) && $_POST['teknisi_id'] !== '' ? (int)$_POST['teknisi_id'] : null;
            $prioritas = $_POST['prioritas'];
            $keterangan = trim($_POST['keterangan']);
            // Persist both tanggal_perawatan and jadwal_tanggal so edits to the date field update the stored maintenance date
            $stmt = $mysqli->prepare("INSERT INTO jadwal_perawatan (kendaraan_id, jenis_perawatan, deskripsi, bengkel, jadwal_tanggal, tanggal_perawatan, km_target, km_saat_perawatan, estimasi_biaya, biaya, prioritas, keterangan, created_by, teknisi_id) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
            // Types: i,kendaraan_id; s,jenis; s,deskripsi; s,bengkel; s,jadwal_tanggal; s,tanggal_perawatan; i,km_target; i,km_saat_perawatan; d,estimasi; d,biaya; s,prioritas; s,keterangan; i,created_by; i,teknisi_id
            $stmt->bind_param('isssssiiddssii', $kendaraan_id, $jenis_perawatan, $deskripsi, $bengkel, $jadwal_tanggal, $tanggal_perawatan, $km_target, $km_saat_perawatan, $estimasi_biaya, $biaya, $prioritas, $keterangan, $current_user_id, $teknisi_id);
            
            if ($stmt->execute()) {
                $_SESSION['swal'] = [
                    'icon' => 'success',
                    'title' => 'Berhasil!',
                    'text' => 'Jadwal perawatan berhasil ditambahkan!'
                ];
                log_user_activity("Menambah jadwal perawatan: $jenis_perawatan untuk kendaraan ID $kendaraan_id");
                header('Location: index.php?page=jadwal_perawatan');
                exit;
            } else {
                $msg = '<div class="alert alert-danger">Error: ' . $stmt->error . '</div>';
            }
            $stmt->close();
            
        } elseif ($action === 'edit' && $can_crud && $jadwal_id) {
            $kendaraan_id = (int)$_POST['kendaraan_id'];
            $jenis_perawatan = trim($_POST['jenis_perawatan']);
            $deskripsi = trim($_POST['deskripsi']);
            $bengkel = trim($_POST['bengkel'] ?? '');
            $tanggal_perawatan = $_POST['tanggal_perawatan'] ?: null;
            // map tanggal_perawatan to jadwal_tanggal
            $jadwal_tanggal = $tanggal_perawatan;
            $km_target = $_POST['km_target'] ? (int)$_POST['km_target'] : null;
            $km_saat_perawatan = $_POST['km_saat_perawatan'] ? (int)$_POST['km_saat_perawatan'] : null;
            $estimasi_biaya = $_POST['estimasi_biaya'] ? (float)$_POST['estimasi_biaya'] : 0;
            $biaya = $_POST['biaya'] !== '' ? (float)$_POST['biaya'] : null;
            $teknisi_id = isset($_POST['teknisi_id']) && $_POST['teknisi_id'] !== '' ? (int)$_POST['teknisi_id'] : null;
            $status = $_POST['status'];
            $prioritas = $_POST['prioritas'];
            $tanggal_selesai = $_POST['tanggal_selesai'] ?: null;
            $biaya_aktual = $_POST['biaya_aktual'] ? (float)$_POST['biaya_aktual'] : null;
            $keterangan = trim($_POST['keterangan']);
            // Update both tanggal_perawatan and jadwal_tanggal so the editable date is saved
            $stmt = $mysqli->prepare("UPDATE jadwal_perawatan SET kendaraan_id=?, jenis_perawatan=?, deskripsi=?, bengkel=?, tanggal_perawatan=?, jadwal_tanggal=?, km_target=?, km_saat_perawatan=?, estimasi_biaya=?, biaya=?, status=?, prioritas=?, tanggal_selesai=?, biaya_aktual=?, keterangan=?, teknisi_id=?, updated_by=? WHERE id=?");
            // Types: i,kendaraan_id; s,jenis_perawatan; s,deskripsi; s,bengkel; s,tanggal_perawatan; s,jadwal_tanggal; i,km_target; i,km_saat_perawatan; d,estimasi_biaya; d,biaya; s,status; s,prioritas; s,tanggal_selesai; d,biaya_aktual; s,keterangan; i,teknisi_id; i,updated_by; i,id
            $stmt->bind_param('isssssiiddsssdsiii', $kendaraan_id, $jenis_perawatan, $deskripsi, $bengkel, $tanggal_perawatan, $jadwal_tanggal, $km_target, $km_saat_perawatan, $estimasi_biaya, $biaya, $status, $prioritas, $tanggal_selesai, $biaya_aktual, $keterangan, $teknisi_id, $current_user_id, $jadwal_id);
            
            if ($stmt->execute()) {
                $_SESSION['swal'] = [
                    'icon' => 'success',
                    'title' => 'Berhasil!',
                    'text' => 'Jadwal perawatan berhasil diperbarui!'
                ];
                log_user_activity("Memperbarui jadwal perawatan ID: $jadwal_id");
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
        log_user_activity("Menghapus jadwal perawatan ID: $jadwal_id");
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
$vehicles_query = "SELECT id, no_polisi, merk, tipe FROM kendaraan ORDER BY no_polisi";
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
        <div class="row align-items-center">
            <div class="col">
                <h3 class="page-title">
                    <i class="fas fa-calendar-check"></i>
                    Jadwal Perawatan
                </h3>
            </div>
            <div class="col-auto">
                <?php if ($can_crud): ?>
                    <a href="?page=jadwal_perawatan&action=add" class="btn btn-success">
                        <i class="fas fa-plus"></i> Tambah Jadwal
                    </a>
                <?php endif; ?>
            </div>
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
                                    <select name="kendaraan_id" id="kendaraan_id" class="form-control" required>
                                        <option value="">Pilih Kendaraan</option>
                                        <?php foreach ($vehicles as $vehicle): ?>
                                            <option value="<?= $vehicle['id'] ?>" 
                                                    <?= (isset($jadwal_data) && $jadwal_data['kendaraan_id'] == $vehicle['id']) ? 'selected' : '' ?>>
                                                <?= htmlspecialchars($vehicle['no_polisi'] . ' - ' . $vehicle['merk'] . ' ' . $vehicle['tipe']) ?>
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
                                           value="<?= isset($jadwal_data) ? htmlspecialchars($jadwal_data['tanggal_perawatan'] ?? $jadwal_data['jadwal_tanggal'] ?? '') : '' ?>">
                                </div>
                            </div>
                            <div class="col-md-4">
                                <div class="form-group">
                                    <label for="km_target">KM Target</label>
                                    <input type="number" name="km_target" id="km_target" 
                                           class="form-control" 
                                           value="<?= isset($jadwal_data) ? $jadwal_data['km_target'] : '' ?>"
                                           placeholder="Contoh: 50000">
                                </div>
                            </div>
                            <div class="col-md-4">
                                <div class="form-group">
                                    <label for="estimasi_biaya">Estimasi Biaya</label>
                     <input type="number" name="estimasi_biaya" id="estimasi_biaya" 
                         class="form-control" step="0.01"
                         value="<?= isset($jadwal_data) ? htmlspecialchars($jadwal_data['estimasi_biaya'] ?? '') : '' ?>"
                         placeholder="Contoh: 500000">
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
                         value="<?= isset($jadwal_data) ? htmlspecialchars($jadwal_data['biaya_aktual'] ?? '') : '' ?>">
                                </div>
                            </div>
                            <div class="col-md-3">
                            <?php else: ?>
                            <div class="col-md-12">
                            <?php endif; ?>
                                <div class="form-group">
                                    <label for="prioritas">Prioritas</label>
                                    <select name="prioritas" id="prioritas" class="form-control">
                                        <?php 
                                        $prioritas_options = ['Rendah', 'Normal', 'Tinggi', 'Urgent'];
                                        // Default prioritas for new entries
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
                        </div>

                        <div class="row">
                            <div class="col-md-6">
                                <div class="form-group">
                                    <label for="biaya">Biaya</label>
                                    <input type="number" name="biaya" id="biaya" class="form-control" step="0.01" value="<?= isset($jadwal_data) ? htmlspecialchars($jadwal_data['biaya'] ?? '') : '' ?>">
                                </div>
                            </div>
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

                        <div class="form-group">
                            <label for="keterangan">Keterangan</label>
                            <textarea name="keterangan" id="keterangan" class="form-control" rows="3"><?= isset($jadwal_data) ? htmlspecialchars($jadwal_data['keterangan'] ?? '') : '' ?></textarea>
                        </div>

                        <div class="form-group">
                            <input type="hidden" name="csrf_token" value="<?= generate_csrf_token() ?>">
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
                        <tr><th>Jenis Perawatan</th><td><?= htmlspecialchars($jadwal_data['jenis_perawatan']) ?></td></tr>
                        <tr><th>Tanggal Perawatan</th><td><?= !empty($jadwal_data['tanggal_perawatan']) ? date('d/m/Y', strtotime($jadwal_data['tanggal_perawatan'])) : ( !empty($jadwal_data['jadwal_tanggal']) ? date('d/m/Y', strtotime($jadwal_data['jadwal_tanggal'])) : '-' ) ?></td></tr>
                        <tr><th>Bengkel</th><td><?= htmlspecialchars($jadwal_data['bengkel'] ?? '-') ?></td></tr>
                                        <tr><th>Jadwal Tanggal</th><td><?= !empty($jadwal_data['jadwal_tanggal']) ? date('d/m/Y', strtotime($jadwal_data['jadwal_tanggal'])) : '-' ?></td></tr>
                                        <tr><th>Tanggal Perawatan</th><td><?= !empty($jadwal_data['tanggal_perawatan']) ? date('d/m/Y', strtotime($jadwal_data['tanggal_perawatan'])) : '-' ?></td></tr>
                                        <tr><th>Bengkel</th><td><?= htmlspecialchars($jadwal_data['bengkel'] ?? '-') ?></td></tr>
                                        <tr><th>KM Target</th><td><?= !empty($jadwal_data['km_target']) ? number_format($jadwal_data['km_target']) . ' KM' : '-' ?></td></tr>
                                        <tr><th>KM Saat Perawatan</th><td><?= !empty($jadwal_data['km_saat_perawatan']) ? number_format($jadwal_data['km_saat_perawatan']) . ' KM' : '-' ?></td></tr>
                                        <tr><th>Estimasi Biaya</th><td><?= !empty($jadwal_data['estimasi_biaya']) ? 'Rp ' . number_format($jadwal_data['estimasi_biaya']) : '-' ?></td></tr>
                                        <tr><th>Biaya</th><td><?= !empty($jadwal_data['biaya']) ? 'Rp ' . number_format($jadwal_data['biaya']) : '-' ?></td></tr>
                                        <tr><th>Biaya Aktual</th><td><?= !empty($jadwal_data['biaya_aktual']) ? 'Rp ' . number_format($jadwal_data['biaya_aktual']) : '-' ?></td></tr>
                                        <tr><th>Status</th><td><?= htmlspecialchars($jadwal_data['status'] ?? '-') ?></td></tr>
                                        <tr><th>Prioritas</th><td><?= htmlspecialchars($jadwal_data['prioritas'] ?? '-') ?></td></tr>
                                        <tr><th>Teknisi</th><td><?= htmlspecialchars($jadwal_data['teknisi_name'] ?? ($jadwal_data['teknisi_id'] ?? '-')) ?></td></tr>
                                        <tr><th>Reminder Sent</th><td><?= isset($jadwal_data['reminder_sent']) ? ($jadwal_data['reminder_sent'] ? 'Ya' : 'Tidak') : '-' ?></td></tr>
                                        <tr><th>Tanggal Selesai</th><td><?= !empty($jadwal_data['tanggal_selesai']) ? date('d/m/Y', strtotime($jadwal_data['tanggal_selesai'])) : '-' ?></td></tr>
                                        <tr><th>Keterangan</th><td><?= nl2br(htmlspecialchars($jadwal_data['keterangan'] ?? '-')) ?></td></tr>
                                        <tr><th>Dibuat</th><td><?= !empty($jadwal_data['created_at']) ? date('d/m/Y H:i', strtotime($jadwal_data['created_at'])) . ' oleh ' . htmlspecialchars($jadwal_data['created_by_name'] ?? $jadwal_data['created_by']) : '-' ?></td></tr>
                                        <tr><th>Terakhir diubah</th><td><?= !empty($jadwal_data['updated_at']) ? date('d/m/Y H:i', strtotime($jadwal_data['updated_at'])) . ' oleh ' . htmlspecialchars($jadwal_data['updated_by_name'] ?? $jadwal_data['updated_by']) : '-' ?></td></tr>
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
