<?php
require_once 'includes/auth.php';
require_login();
require_admin();

$user_id = get_current_user_id();
$msg = '';
$current_role = get_current_role();
$dashboard_page = ($current_role === 'pimpinan') ? 'dashboard_pimpinan' : 'dashboard_admin';

// Helper: check if a table exists in the current database
function table_exists($mysqli, $table) {
    $res = $mysqli->query("SHOW TABLES LIKE '" . $mysqli->real_escape_string($table) . "'");
    return $res && $res->num_rows > 0;
}

if (!function_exists('get_table_columns')) {
    function get_table_columns($mysqli, $table) {
        $cols = [];
        $table = $mysqli->real_escape_string($table);
        $res = $mysqli->query("SHOW COLUMNS FROM `{$table}`");
        if ($res) while ($r = $res->fetch_assoc()) $cols[] = $r['Field'];
        return $cols;
    }
}

if (!function_exists('insert_notification')) {
    function insert_notification($mysqli, $user_id, $message, $title = null) {
        if (table_exists($mysqli, 'notifikasi_advanced')) {
            $table = 'notifikasi_advanced';
        } elseif (table_exists($mysqli, 'notifikasi')) {
            $table = 'notifikasi';
        } else {
            return false;
        }
        $cols = get_table_columns($mysqli, $table);
        $msg_esc = $mysqli->real_escape_string($message);
        $title_esc = $title !== null ? $mysqli->real_escape_string($title) : null;

        if (in_array('message', $cols) && in_array('title', $cols)) {
            $title_sql = $title_esc !== null ? "'{$title_esc}'" : "''";
            return $mysqli->query("INSERT INTO {$table} (user_id, message, title, created_at) VALUES ({$user_id}, '{$msg_esc}', {$title_sql}, NOW())");
        }

        if (in_array('pesan', $cols)) {
            return $mysqli->query("INSERT INTO {$table} (user_id, pesan, created_at) VALUES ({$user_id}, '{$msg_esc}', NOW())");
        }

        if (in_array('message', $cols)) {
            return $mysqli->query("INSERT INTO {$table} (user_id, message, created_at) VALUES ({$user_id}, '{$msg_esc}', NOW())");
        }

        return false;
    }
}

// Handle form submission
if ($_POST && isset($_POST['action'])) {
    if ($_POST['action'] === 'create_surat_tugas') {
        $penerima_id = (int)$_POST['penerima_id'];
        $kendaraan_id = (int)$_POST['kendaraan_id'];
        $judul_tugas = trim($_POST['judul_tugas']);
        $deskripsi_tugas = trim($_POST['deskripsi_tugas']);
        $tempat_tugas = trim($_POST['tempat_tugas']);
        $tanggal_mulai = $_POST['tanggal_mulai'];
        $tanggal_selesai = $_POST['tanggal_selesai'];
        $prioritas = $_POST['prioritas'];
        
        if ($penerima_id && $kendaraan_id && $judul_tugas && $deskripsi_tugas && $tempat_tugas && $tanggal_mulai && $tanggal_selesai && $prioritas) {
            // Generate nomor surat
            $tahun = date('Y');
            $bulan_romawi = ['I', 'II', 'III', 'IV', 'V', 'VI', 'VII', 'VIII', 'IX', 'X', 'XI', 'XII'][date('n') - 1];
            $count_result = $mysqli->query("SELECT COUNT(*) as count FROM surat_tugas WHERE YEAR(created_at) = YEAR(NOW())");
            $count = $count_result->fetch_assoc()['count'] + 1;
            $nomor_surat = sprintf("ST/%03d/%s/%s", $count, $bulan_romawi, $tahun);
            
            // Check vehicle availability
            // Defensive availability check: only run if jadwal_kendaraan exists
            $conflicts = 0;
            $has_jadwal = table_exists($mysqli, 'jadwal_kendaraan');
            if ($has_jadwal) {
                $check_availability = $mysqli->prepare(
                    "SELECT COUNT(*) as conflicts FROM jadwal_kendaraan WHERE kendaraan_id = ? AND status IN ('scheduled', 'active') AND ((tanggal_mulai <= ? AND tanggal_selesai >= ?) OR (tanggal_mulai <= ? AND tanggal_selesai >= ?) OR (tanggal_mulai >= ? AND tanggal_selesai <= ?))"
                );
                $check_availability->bind_param('issssss', $kendaraan_id, $tanggal_mulai, $tanggal_mulai, $tanggal_selesai, $tanggal_selesai, $tanggal_mulai, $tanggal_selesai);
                $check_availability->execute();
                $check_availability->bind_result($conflicts);
                $check_availability->fetch();
                $check_availability->close();
            } else {
                $conflicts = 0;
            }
            
            if ($conflicts > 0) {
                $msg = '<div class="alert alert-danger">Kendaraan tidak tersedia pada waktu yang dipilih. Silakan pilih waktu lain atau kendaraan lain.</div>';
            } else {
                // Insert surat tugas
                $stmt = $mysqli->prepare("INSERT INTO surat_tugas (nomor_surat, pembuat_id, penerima_id, kendaraan_id, judul_tugas, deskripsi_tugas, tempat_tugas, tanggal_mulai, tanggal_selesai, prioritas, status) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 'pending_approval')");
                // s, i, i, i, s, s, s, s, s, s
                $stmt->bind_param('siiissssss', $nomor_surat, $user_id, $penerima_id, $kendaraan_id, $judul_tugas, $deskripsi_tugas, $tempat_tugas, $tanggal_mulai, $tanggal_selesai, $prioritas);
                
                if ($stmt->execute()) {
                    $surat_tugas_id = $mysqli->insert_id;
                    
                    // Add to schedule (tentative until approved)
                    // Add to schedule only if exists
                    if ($has_jadwal) {
                        $schedule_stmt = $mysqli->prepare("INSERT INTO jadwal_kendaraan (kendaraan_id, tipe_penggunaan, referensi_id, pengguna_id, tanggal_mulai, tanggal_selesai, status, keterangan) VALUES (?, 'surat_tugas', ?, ?, ?, ?, 'scheduled', ?)");
                        $keterangan = "Surat Tugas: " . $judul_tugas;
                        $schedule_stmt->bind_param('iiisss', $kendaraan_id, $surat_tugas_id, $penerima_id, $tanggal_mulai, $tanggal_selesai, $keterangan);
                        $schedule_stmt->execute();
                        $schedule_stmt->close();
                    }
                    
                    // Notify user about new assignment
                    $kendaraan_info = $mysqli->query("SELECT no_polisi, merk, tipe FROM kendaraan WHERE id = $kendaraan_id")->fetch_assoc();
                    $user_info = $mysqli->query("SELECT nama_lengkap FROM pengguna WHERE id = $penerima_id")->fetch_assoc();
                    $notification_msg = "Anda mendapat surat tugas baru: \"$judul_tugas\" menggunakan kendaraan " . ($kendaraan_info['no_polisi'] ?? '') . " (" . ($kendaraan_info['merk'] ?? '') . " " . ($kendaraan_info['tipe'] ?? '') . ") dari tanggal " . date('d/m/Y', strtotime($tanggal_mulai)) . " sampai " . date('d/m/Y', strtotime($tanggal_selesai));
                    insert_notification($mysqli, $penerima_id, $notification_msg, 'Surat Tugas Baru');
                    
                    $msg = '<div class="alert alert-success">Surat tugas berhasil dibuat dengan nomor: <strong>' . $nomor_surat . '</strong>. Langsung aktif.</div>';
                    log_user_activity("Membuat surat tugas $nomor_surat untuk user ID: $penerima_id");
                } else {
                    $msg = '<div class="alert alert-danger">Gagal membuat surat tugas. Silakan coba lagi.</div>';
                }
                $stmt->close();
            }
        } else {
            $msg = '<div class="alert alert-danger">Harap lengkapi semua field yang diperlukan.</div>';
        }
    }
}

// Get users for assignment
$users = $mysqli->query("
        SELECT p.id, p.nama_lengkap, p.pangkat, p.jabatan, p.nrp_nip
        FROM pengguna p 
        JOIN user_account ua ON p.id = ua.pengguna_id 
        JOIN role r ON ua.role_id = r.id
        WHERE UPPER(COALESCE(r.kode_role, '')) IN ('USER','DRIVER')
            AND (ua.status = 'Aktif' OR ua.status = 'aktif')
        ORDER BY p.nama_lengkap
")->fetch_all(MYSQLI_ASSOC);

// Get available vehicles
$kendaraan = $mysqli->query("SELECT * FROM kendaraan WHERE status_peminjaman = 'Tersedia' ORDER BY merk, tipe")->fetch_all(MYSQLI_ASSOC);

// Get surat tugas list
$current_role = get_current_role();
$surat_tugas_query = "
    SELECT st.*, 
           pembuat.nama_lengkap as pembuat_name,
           penerima.nama_lengkap as penerima_name, penerima.pangkat as penerima_pangkat,
           k.no_polisi, k.merk, k.tipe,
           admin.nama_lengkap as approved_by_name
    FROM surat_tugas st
    LEFT JOIN pengguna pembuat ON st.pembuat_id = pembuat.id
    LEFT JOIN pengguna penerima ON st.penerima_id = penerima.id
    LEFT JOIN kendaraan k ON st.kendaraan_id = k.id
    LEFT JOIN pengguna admin ON st.approval_admin_id = admin.id
";

$surat_tugas_query .= " ORDER BY st.created_at DESC";
$surat_tugas_list = $mysqli->query($surat_tugas_query)->fetch_all(MYSQLI_ASSOC);

// Status badges
function getStatusBadge($status) {
    $badges = [
        'draft' => 'badge-secondary',
        'pending_approval' => 'badge-warning',
        'approved' => 'badge-success',
        'rejected' => 'badge-danger',
        'ongoing' => 'badge-info',
        'completed' => 'badge-primary',
        'cancelled' => 'badge-dark'
    ];
    
    $labels = [
        'draft' => 'Draft',
        'pending_approval' => 'Menunggu Persetujuan',
        'approved' => 'Disetujui',
        'rejected' => 'Ditolak',
        'ongoing' => 'Sedang Berlangsung',
        'completed' => 'Selesai',
        'cancelled' => 'Dibatalkan'
    ];
    
    $badge_class = $badges[$status] ?? 'badge-secondary';
    $label = $labels[$status] ?? $status;
    
    return "<span class=\"badge $badge_class\">$label</span>";
}

function getPriorityBadge($prioritas) {
    $badges = [
        'Rendah' => 'badge-light',
        'Sedang' => 'badge-info',
        'Tinggi' => 'badge-warning',
        'Urgent' => 'badge-danger'
    ];
    
    $badge_class = $badges[$prioritas] ?? 'badge-secondary';
    return "<span class=\"badge $badge_class\">$prioritas</span>";
}
?>

<div class="container-fluid">
    <div class="page-header">
        <h1><i class="fas fa-file-signature"></i> Manajemen Surat Tugas</h1>
        <p class="text-muted">Buat dan kelola surat tugas untuk penugasan kendaraan kepada user</p>
    </div>

    <?= $msg ?>

    <!-- Form Create Surat Tugas -->
    <div class="card mb-4">
        <div class="card-header">
            <h3><i class="fas fa-plus"></i> Buat Surat Tugas Baru</h3>
            <small class="text-light">Tugaskan kendaraan kepada user dengan surat tugas resmi</small>
        </div>
        <div class="card-body">
            <form method="POST" id="form-surat-tugas">
                <input type="hidden" name="action" value="create_surat_tugas">
                
                <div class="row">
                    <div class="col-md-6">
                        <div class="form-group">
                            <label for="penerima_id">Penerima Tugas <span class="text-danger">*</span></label>
                            <select name="penerima_id" id="penerima_id" class="form-control" required>
                                <option value="">-- Pilih Penerima Tugas --</option>
                                <?php foreach ($users as $user): ?>
                                    <option value="<?= $user['id'] ?>">
                                        <?= htmlspecialchars($user['pangkat']) ?> <?= htmlspecialchars($user['nama_lengkap']) ?> (<?= htmlspecialchars($user['nrp_nip']) ?>)
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        
                        <div class="form-group">
                            <label for="kendaraan_id">Kendaraan yang Ditugaskan <span class="text-danger">*</span></label>
                            <select name="kendaraan_id" id="kendaraan_id" class="form-control" required>
                                <option value="">-- Pilih Kendaraan --</option>
                                <?php foreach ($vehicles as $vehicle): ?>
                                    <option value="<?= $vehicle['id'] ?>">
                                        <?= htmlspecialchars($vehicle['no_polisi']) ?> - <?= htmlspecialchars($vehicle['merk']) ?> <?= htmlspecialchars($vehicle['tipe']) ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        
                        <div class="form-group">
                            <label for="judul_tugas">Judul Tugas <span class="text-danger">*</span></label>
                            <input type="text" name="judul_tugas" id="judul_tugas" class="form-control" required placeholder="Contoh: Survey Lapangan Wilayah Timur">
                        </div>
                        
                        <div class="form-group">
                            <label for="tempat_tugas">Tempat Tugas <span class="text-danger">*</span></label>
                            <input type="text" name="tempat_tugas" id="tempat_tugas" class="form-control" required placeholder="Contoh: Wilayah Timur Kota">
                        </div>
                    </div>
                    
                    <div class="col-md-6">
                        <div class="form-group">
                            <label for="tanggal_mulai">Tanggal Mulai <span class="text-danger">*</span></label>
                            <input type="datetime-local" name="tanggal_mulai" id="tanggal_mulai" class="form-control" required>
                        </div>
                        
                        <div class="form-group">
                            <label for="tanggal_selesai">Tanggal Selesai <span class="text-danger">*</span></label>
                            <input type="datetime-local" name="tanggal_selesai" id="tanggal_selesai" class="form-control" required>
                        </div>
                        
                        <div class="form-group">
                            <label for="prioritas">Prioritas <span class="text-danger">*</span></label>
                            <select name="prioritas" id="prioritas" class="form-control" required>
                                <option value="">-- Pilih Prioritas --</option>
                                <option value="Rendah">Rendah</option>
                                <option value="Sedang" selected>Sedang</option>
                                <option value="Tinggi">Tinggi</option>
                                <option value="Urgent">Urgent</option>
                            </select>
                        </div>
                        
                        <div class="form-group">
                            <label for="deskripsi_tugas">Deskripsi Tugas <span class="text-danger">*</span></label>
                            <textarea name="deskripsi_tugas" id="deskripsi_tugas" class="form-control" rows="4" required placeholder="Jelaskan detail tugas yang harus dilaksanakan..."></textarea>
                        </div>
                    </div>
                </div>
                
                <div class="alert alert-info">
                    <h6><i class="fas fa-info-circle"></i> Informasi:</h6>
                    <ul class="mb-0">
                        <li>Surat tugas akan dikirim notifikasi ke user yang ditugaskan</li>
                        <li>Sebagai admin/pimpinan, surat tugas langsung aktif</li>
                        <li>Kendaraan akan terjadwal otomatis sesuai periode tugas</li>
                        <li>User dapat menerima atau menolak penugasan</li>
                    </ul>
                </div>
                
                <div class="form-group">
                    <button type="button" class="btn btn-info" onclick="checkAvailability()">
                        <i class="fas fa-search"></i> Cek Ketersediaan Kendaraan
                    </button>
                    <button type="submit" class="btn btn-primary btn-lg">
                        <i class="fas fa-file-signature"></i> Buat Surat Tugas
                    </button>
                    <a href="index.php?page=<?= $dashboard_page ?>" class="btn btn-secondary">
                        <i class="fas fa-arrow-left"></i> Kembali
                    </a>
                </div>
            </form>
        </div>
    </div>

    <!-- Daftar Surat Tugas -->
    <div class="card">
        <div class="card-header">
            <h3><i class="fas fa-list"></i> Daftar Surat Tugas</h3>
        </div>
        <div class="card-body">
            <?php if (count($surat_tugas_list) > 0): ?>
                <div class="table-responsive">
                    <table class="table table-striped">
                        <thead>
                            <tr>
                                <th>Nomor Surat</th>
                                <th>Penerima</th>
                                <th>Tugas</th>
                                <th>Kendaraan</th>
                                <th>Periode</th>
                                <th>Prioritas</th>
                                <th>Status</th>
                                <th>Aksi</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($surat_tugas_list as $st): ?>
                                <tr>
                                    <td>
                                        <strong><?= htmlspecialchars($st['nomor_surat']) ?></strong><br>
                                        <small class="text-muted">Dibuat: <?= date('d/m/Y', strtotime($st['created_at'])) ?></small>
                                    </td>
                                    <td>
                                        <strong><?= htmlspecialchars($st['penerima_pangkat']) ?> <?= htmlspecialchars($st['penerima_name']) ?></strong><br>
                                        <small class="text-muted">Pembuat: <?= htmlspecialchars($st['pembuat_name']) ?></small>
                                    </td>
                                    <td>
                                        <strong><?= htmlspecialchars($st['judul_tugas']) ?></strong><br>
                                        <small class="text-muted"><i class="fas fa-map-marker-alt"></i> <?= htmlspecialchars($st['tempat_tugas']) ?></small>
                                    </td>
                                    <td>
                                        <strong><?= htmlspecialchars($st['no_polisi']) ?></strong><br>
                                        <small class="text-muted"><?= htmlspecialchars($st['merk']) ?> <?= htmlspecialchars($st['tipe']) ?></small>
                                    </td>
                                    <td>
                                        <strong><?= date('d/m/Y H:i', strtotime($st['tanggal_mulai'])) ?></strong><br>
                                        <small class="text-muted">s/d <?= date('d/m/Y H:i', strtotime($st['tanggal_selesai'])) ?></small>
                                    </td>
                                    <td><?= getPriorityBadge($st['prioritas']) ?></td>
                                    <td>
                                        <?= getStatusBadge($st['status']) ?>
                                        <?php if ($st['user_response'] !== 'pending' && $st['status'] !== 'draft'): ?>
                                            <br><small class="text-muted">Respon: <?= ucfirst($st['user_response']) ?></small>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <button class="btn btn-sm btn-info" onclick="viewDetail(<?= $st['id'] ?>)">
                                            <i class="fas fa-eye"></i> Detail
                                        </button>
                                        <button class="btn btn-sm btn-success" onclick="generateSurat(<?= $st['id'] ?>)">
                                            <i class="fas fa-file-pdf"></i> PDF
                                        </button>
                                        <?php if ($st['status'] === 'draft' || ($st['status'] === 'pending_approval' && is_admin_like())): ?>
                                            <button class="btn btn-sm btn-warning" onclick="editSurat(<?= $st['id'] ?>)">
                                                <i class="fas fa-edit"></i> Edit
                                            </button>
                                        <?php endif; ?>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php else: ?>
                <div class="text-center py-5">
                    <i class="fas fa-file-signature fa-3x text-muted mb-3"></i>
                    <h5>Belum ada surat tugas</h5>
                    <p class="text-muted">Silakan buat surat tugas pertama untuk menugaskan kendaraan kepada user</p>
                </div>
            <?php endif; ?>
        </div>
    </div>
</div>

<!-- Modal Detail Surat Tugas -->
<div class="modal fade" id="detailModal" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Detail Surat Tugas</h5>
                <button type="button" class="close" data-dismiss="modal">
                    <span>&times;</span>
                </button>
            </div>
            <div class="modal-body" id="detailContent">
                <!-- Content will be loaded via AJAX -->
            </div>
        </div>
    </div>
</div>

<script>
function checkAvailability() {
    const kendaraan_id = document.getElementById('kendaraan_id').value;
    const tanggal_mulai = document.getElementById('tanggal_mulai').value;
    const tanggal_selesai = document.getElementById('tanggal_selesai').value;
    
    if (!kendaraan_id || !tanggal_mulai || !tanggal_selesai) {
        alert('Harap pilih kendaraan dan isi tanggal terlebih dahulu');
        return;
    }
    
    fetch('ajax/check_vehicle_availability.php', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
        },
        body: JSON.stringify({
            kendaraan_id: kendaraan_id,
            datetime_mulai: tanggal_mulai,
            datetime_selesai: tanggal_selesai
        })
    })
    .then(response => response.json())
    .then(data => {
        if (data.available) {
            alert('✅ Kendaraan tersedia pada waktu yang dipilih!');
        } else {
            alert('❌ Kendaraan tidak tersedia pada waktu yang dipilih. Konflik dengan: ' + data.conflicts.join(', '));
        }
    })
    .catch(error => {
        console.error('Error:', error);
        alert('Terjadi kesalahan saat mengecek ketersediaan');
    });
}

function viewDetail(id) {
    fetch('ajax/get_surat_tugas_detail.php?id=' + id)
        .then(response => response.text())
        .then(data => {
            document.getElementById('detailContent').innerHTML = data;
            $('#detailModal').modal('show');
        })
        .catch(error => {
            console.error('Error:', error);
            alert('Terjadi kesalahan saat memuat detail');
        });
}

function generateSurat(id) {
    window.open('ajax/generate_surat_tugas_pdf.php?id=' + id, '_blank');
}

function editSurat(id) {
    window.location.href = 'index.php?page=edit_surat_tugas&id=' + id;
}

// Auto-update tanggal_selesai when tanggal_mulai changes
document.getElementById('tanggal_mulai').addEventListener('change', function() {
    const tanggal_selesai = document.getElementById('tanggal_selesai');
    if (!tanggal_selesai.value || tanggal_selesai.value < this.value) {
        tanggal_selesai.value = this.value;
    }
    tanggal_selesai.min = this.value;
});
</script>

<style>
.page-header {
    margin-bottom: 2rem;
    padding-bottom: 1rem;
    border-bottom: 1px solid #e9ecef;
}

.card {
    box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1);
    border: none;
    border-radius: 12px;
}

.card-header {
    background: linear-gradient(135deg, #6f42c1 0%, #e83e8c 100%);
    color: white;
    border-radius: 12px 12px 0 0;
}

.badge {
    padding: 0.4rem 0.8rem;
    border-radius: 12px;
    font-size: 0.85rem;
}

.form-control {
    border-radius: 8px;
    border: 2px solid #e9ecef;
    transition: all 0.2s ease;
}

.form-control:focus {
    border-color: #6f42c1;
    box-shadow: 0 0 0 0.2rem rgba(111, 66, 193, 0.25);
}

.alert-info {
    background-color: #e7f3ff;
    border-color: #b8daff;
    color: #004085;
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
</style>
