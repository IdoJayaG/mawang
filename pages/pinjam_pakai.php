<?php
require_once 'includes/auth.php';
require_login();
require_role(['user','driver']);

$user_id = get_current_user_id();
$msg = '';

// reuse helpers if present; define minimal ones here for safety
if (!function_exists('table_exists')) {
    function table_exists($mysqli, $table) {
        $table = $mysqli->real_escape_string($table);
        $res = $mysqli->query("SHOW TABLES LIKE '{$table}'");
        return $res && $res->num_rows > 0;
    }
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

// Handle form submission (only attempt DB insert when table exists)
if ($_POST && isset($_POST['action'])) {
    if ($_POST['action'] === 'submit_pinjam_pakai') {
        if (!table_exists($mysqli, 'pinjam_pakai')) {
            $msg = '<div class="alert alert-danger">Fitur pengajuan pinjam pakai sedang tidak tersedia karena tabel <code>pinjam_pakai</code> tidak ditemukan di database. Hubungi administrator.</div>';
        } else {
            $kendaraan_id = (int)$_POST['kendaraan_id'];
            $keperluan = trim($_POST['keperluan']);
            $tujuan = trim($_POST['tujuan']);
            $estimasi_durasi = trim($_POST['estimasi_durasi']);
            $justifikasi = trim($_POST['justifikasi']);
            $tanggal_mulai = $_POST['tanggal_mulai'];

            if ($kendaraan_id && $keperluan && $tujuan && $estimasi_durasi && $justifikasi && $tanggal_mulai) {
                // Kebijakan peminjaman: hanya kendaraan jenis Bus.
                $busChk = $mysqli->query("SELECT jenis FROM kendaraan WHERE id = " . (int)$kendaraan_id);
                $busRow = $busChk ? $busChk->fetch_assoc() : null;
                $jenisKendaraan = strtolower(trim((string)($busRow['jenis'] ?? '')));
                if ($jenisKendaraan !== 'bus') {
                    $msg = '<div class="alert alert-danger">Pengajuan pinjam pakai hanya diperbolehkan untuk kendaraan jenis Bus.</div>';
                } else {
                // Insert pinjam pakai
                $stmt = $mysqli->prepare("INSERT INTO pinjam_pakai (pemohon_id, kendaraan_id, keperluan, tujuan, estimasi_durasi, justifikasi, tanggal_mulai) VALUES (?, ?, ?, ?, ?, ?, ?)");
                if ($stmt) {
                    $stmt->bind_param('iisssss', $user_id, $kendaraan_id, $keperluan, $tujuan, $estimasi_durasi, $justifikasi, $tanggal_mulai);
                    if ($stmt->execute()) {
                        $pinjam_pakai_id = $mysqli->insert_id;

                        // Notify admin-like roles
                        $kendaraan_info = $mysqli->query("SELECT no_polisi, merk, tipe FROM kendaraan WHERE id = $kendaraan_id")->fetch_assoc();
                        $current_user = get_logged_in_user();
                        $notification_msg = "Pengajuan pinjam pakai kendaraan {$kendaraan_info['no_polisi']} ({$kendaraan_info['merk']} {$kendaraan_info['tipe']}) dari {$current_user['nama_lengkap']} untuk keperluan: $keperluan. Estimasi durasi: $estimasi_durasi";

                        $admin_role_ids = function_exists('get_admin_like_role_ids') ? get_admin_like_role_ids() : [];
                        $admin_like_users = null;
                        if (!empty($admin_role_ids)) {
                            $in_ids = implode(',', array_map('intval', $admin_role_ids));
                            $admin_like_users = $mysqli->query("SELECT p.id FROM pengguna p JOIN user_account ua ON p.id = ua.pengguna_id WHERE ua.role_id IN ({$in_ids})");
                        }
                        while ($admin_like_users && ($admin = $admin_like_users->fetch_assoc())) {
                            $uid = (int)$admin['id'];
                            $escaped = $notification_msg;
                            insert_notification($mysqli, $uid, $escaped, 'Pengajuan Pinjam Pakai Baru');
                        }

                        $msg = '<div class="alert alert-success">Pengajuan pinjam pakai berhasil disubmit! Menunggu persetujuan untuk mendapatkan plat dinas.</div>';
                        log_user_activity("Mengajukan pinjam pakai kendaraan ID: $kendaraan_id untuk keperluan: $keperluan");
                    } else {
                        $msg = '<div class="alert alert-danger">Gagal mengajukan pinjam pakai. Silakan coba lagi.</div>';
                    }
                    $stmt->close();
                } else {
                    $msg = '<div class="alert alert-danger">Gagal menyiapkan query pengajuan: ' . htmlspecialchars($mysqli->error) . '</div>';
                }
                }
            } else {
                $msg = '<div class="alert alert-danger">Harap lengkapi semua field yang diperlukan.</div>';
            }
        }
    }
}

// Get available vehicles for pinjam pakai (be tolerant to schema differences)
$cols_info = $mysqli->query("SHOW COLUMNS FROM kendaraan")->fetch_all(MYSQLI_ASSOC);
$cols_names = array_column($cols_info, 'Field');
if (in_array('status', $cols_names)) {
    $vehicles = $mysqli->query("SELECT * FROM kendaraan WHERE status = 'Tersedia' AND LOWER(COALESCE(jenis,'')) = 'bus' ORDER BY merk, tipe")->fetch_all(MYSQLI_ASSOC);
} elseif (in_array('status_peminjaman', $cols_names)) {
    $vehicles = $mysqli->query("SELECT * FROM kendaraan WHERE status_peminjaman = 'Tersedia' AND LOWER(COALESCE(jenis,'')) = 'bus' ORDER BY merk, tipe")->fetch_all(MYSQLI_ASSOC);
} else {
    // fallback: no availability column detected, return all vehicles
    $vehicles = $mysqli->query("SELECT * FROM kendaraan WHERE LOWER(COALESCE(jenis,'')) = 'bus' ORDER BY merk, tipe")->fetch_all(MYSQLI_ASSOC);
}

// Get user's pinjam pakai (guard if table missing)
$pinjam_pakai_list = [];
if (table_exists($mysqli, 'pinjam_pakai')) {
    // detect approver column in pinjam_pakai
    $cols_info = $mysqli->query("SHOW COLUMNS FROM pinjam_pakai")->fetch_all(MYSQLI_ASSOC);
    $cols_names = array_column($cols_info, 'Field');
    $approver_col = null;
    foreach (['approved_by','approval_by','approver_id','approved_by_id','approver'] as $c) {
        if (in_array($c, $cols_names)) { $approver_col = $c; break; }
    }
    $select_extra = '';
    $join_approver = '';
    if ($approver_col) {
        $select_extra = ', approver.nama_lengkap as approved_by_name';
        $join_approver = " LEFT JOIN pengguna approver ON pp.{$approver_col} = approver.id";
    }

    $sql = "SELECT pp.*, k.no_polisi, k.merk, k.tipe, k.jenis" . $select_extra . "\n            FROM pinjam_pakai pp\n            LEFT JOIN kendaraan k ON pp.kendaraan_id = k.id" . $join_approver . "\n            WHERE pp.pemohon_id = ?\n            ORDER BY pp.created_at DESC";

    $user_pinjam_pakai = $mysqli->prepare($sql);
    if ($user_pinjam_pakai) {
        $user_pinjam_pakai->bind_param('i', $user_id);
        $user_pinjam_pakai->execute();
        $pinjam_pakai_list = $user_pinjam_pakai->get_result()->fetch_all(MYSQLI_ASSOC);
        $user_pinjam_pakai->close();
    } else {
        $msg .= '<div class="alert alert-warning">Gagal menyiapkan query riwayat pinjam pakai: ' . htmlspecialchars($mysqli->error) . '</div>';
    }
} else {
    $msg .= '<div class="alert alert-warning">Fitur riwayat pinjam pakai tidak tersedia karena tabel <code>pinjam_pakai</code> tidak ditemukan.</div>';
}

// Status badges
function getStatusBadge($status) {
    $badges = [
        'pending' => 'badge-warning',
        'approved' => 'badge-success',
        'rejected' => 'badge-danger',
        'active' => 'badge-info',
        'returned' => 'badge-primary',
        'overdue' => 'badge-danger'
    ];
    
    $labels = [
        'pending' => 'Menunggu Persetujuan',
        'approved' => 'Disetujui - Plat Dinas Tersedia',
        'rejected' => 'Ditolak',
        'active' => 'Sedang Meminjam',
        'returned' => 'Sudah Dikembalikan',
        'overdue' => 'Terlambat Kembali'
    ];
    
    $badge_class = $badges[$status] ?? 'badge-secondary';
    $label = $labels[$status] ?? $status;
    
    return "<span class=\"badge $badge_class\">$label</span>";
}
?>

<div class="container-fluid">
    <div class="page-header">
        <h1><i class="fas fa-id-card"></i> Pinjam Pakai Kendaraan</h1>
        <p class="text-muted">Ajukan permohonan untuk mendapatkan plat dinas kendaraan dalam jangka waktu tertentu</p>
    </div>

    <?= $msg ?>

    <!-- Form Pinjam Pakai -->
    <div class="card mb-4">
        <div class="card-header">
            <h3><i class="fas fa-plus"></i> Ajukan Pinjam Pakai</h3>
            <small class="text-light">Permohonan untuk mendapatkan kendaraan dengan plat dinas untuk keperluan resmi</small>
        </div>
        <div class="card-body">
            <form method="POST" id="form-pinjam-pakai">
                <input type="hidden" name="action" value="submit_pinjam_pakai">
                
                <div class="row">
                    <div class="col-md-6">
                        <div class="form-group">
                            <label for="kendaraan_id">Pilih Kendaraan <span class="text-danger">*</span></label>
                            <select name="kendaraan_id" id="kendaraan_id" class="form-control" required>
                                <option value="">-- Pilih Kendaraan --</option>
                                <?php foreach ($vehicles as $vehicle): ?>
                                    <option value="<?= $vehicle['id'] ?>" data-info="<?= htmlspecialchars($vehicle['merk'] . ' ' . $vehicle['tipe'] . ' (' . $vehicle['jenis'] . ')') ?>">
                                        <?= htmlspecialchars($vehicle['no_polisi']) ?> - <?= htmlspecialchars($vehicle['merk']) ?> <?= htmlspecialchars($vehicle['tipe']) ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                            <small class="form-text text-muted">Kendaraan yang akan Anda gunakan dengan plat dinas</small>
                        </div>
                        
                        <div class="form-group">
                            <label for="keperluan">Keperluan <span class="text-danger">*</span></label>
                            <textarea name="keperluan" id="keperluan" class="form-control" rows="3" required placeholder="Jelaskan keperluan penggunaan kendaraan dengan detail..."></textarea>
                            <small class="form-text text-muted">Jelaskan secara detail untuk apa kendaraan akan digunakan</small>
                        </div>
                        
                        <div class="form-group">
                            <label for="tujuan">Tujuan/Area Operasi <span class="text-danger">*</span></label>
                            <input type="text" name="tujuan" id="tujuan" class="form-control" required placeholder="Masukkan lokasi/area operasi...">
                            <small class="form-text text-muted">Area/wilayah dimana kendaraan akan dioperasikan</small>
                        </div>
                    </div>
                    
                    <div class="col-md-6">
                        <div class="form-group">
                            <label for="estimasi_durasi">Estimasi Durasi <span class="text-danger">*</span></label>
                            <select name="estimasi_durasi" id="estimasi_durasi" class="form-control" required>
                                <option value="">-- Pilih Estimasi Durasi --</option>
                                <option value="1-3 hari">1-3 hari</option>
                                <option value="1 minggu">1 minggu</option>
                                <option value="2 minggu">2 minggu</option>
                                <option value="1 bulan">1 bulan</option>
                                <option value="2-3 bulan">2-3 bulan</option>
                                <option value="lebih dari 3 bulan">Lebih dari 3 bulan</option>
                            </select>
                            <small class="form-text text-muted">Estimasi berapa lama akan meminjam kendaraan</small>
                        </div>
                        
                        <div class="form-group">
                            <label for="tanggal_mulai">Tanggal Mulai <span class="text-danger">*</span></label>
                            <input type="date" name="tanggal_mulai" id="tanggal_mulai" class="form-control" required min="<?= date('Y-m-d') ?>">
                            <small class="form-text text-muted">Tanggal mulai menggunakan kendaraan</small>
                        </div>
                        
                        <div class="form-group">
                            <label for="justifikasi">Justifikasi/Alasan <span class="text-danger">*</span></label>
                            <textarea name="justifikasi" id="justifikasi" class="form-control" rows="4" required placeholder="Jelaskan mengapa perlu meminjam kendaraan dengan plat dinas..."></textarea>
                            <small class="form-text text-muted">Berikan alasan kuat mengapa perlu mendapatkan plat dinas untuk keperluan ini</small>
                        </div>
                    </div>
                </div>
                
                <div class="alert alert-info">
                    <h6><i class="fas fa-info-circle"></i> Informasi Penting:</h6>
                    <ul class="mb-0">
                        <li>Pinjam pakai kendaraan memberikan Anda akses penuh menggunakan kendaraan dengan plat dinas</li>
                        <li>Anda bertanggung jawab penuh atas kendaraan selama masa peminjaman</li>
                        <li>Wajib mengembalikan kendaraan dalam kondisi baik sesuai jadwal</li>
                        <li>Pelanggaran akan dikenakan sanksi sesuai peraturan yang berlaku</li>
                    </ul>
                </div>
                
                <div class="form-group">
                    <button type="submit" class="btn btn-primary btn-lg">
                        <i class="fas fa-paper-plane"></i> Ajukan Pinjam Pakai
                    </button>
                    <a href="index.php?page=dashboard_user" class="btn btn-secondary">
                        <i class="fas fa-arrow-left"></i> Kembali
                    </a>
                </div>
            </form>
        </div>
    </div>

    <!-- Daftar Pinjam Pakai User -->
    <div class="card">
        <div class="card-header">
            <h3><i class="fas fa-list"></i> Riwayat Pinjam Pakai</h3>
        </div>
        <div class="card-body">
            <?php if (count($pinjam_pakai_list) > 0): ?>
                <div class="table-responsive">
                    <table class="table table-striped">
                        <thead>
                            <tr>
                                <th>Kendaraan</th>
                                <th>Keperluan</th>
                                <th>Durasi</th>
                                <th>Status</th>
                                <th>Tanggal Pengajuan</th>
                                <th>Aksi</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($pinjam_pakai_list as $p): ?>
                                <tr>
                                    <td>
                                        <strong><?= htmlspecialchars($p['no_polisi']) ?></strong><br>
                                        <small class="text-muted"><?= htmlspecialchars($p['merk']) ?> <?= htmlspecialchars($p['tipe']) ?></small>
                                    </td>
                                    <td>
                                        <strong><?= htmlspecialchars($p['keperluan']) ?></strong><br>
                                        <small class="text-muted"><i class="fas fa-map-marker-alt"></i> <?= htmlspecialchars($p['tujuan']) ?></small>
                                    </td>
                                    <td>
                                        <strong><?= htmlspecialchars($p['estimasi_durasi']) ?></strong><br>
                                        <small class="text-muted">Mulai: <?= date('d/m/Y', strtotime($p['tanggal_mulai'])) ?></small>
                                        <?php if ($p['tanggal_selesai']): ?>
                                            <br><small class="text-success">Selesai: <?= date('d/m/Y', strtotime($p['tanggal_selesai'])) ?></small>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <?= getStatusBadge($p['status']) ?>
                                        <?php if ($p['status'] === 'approved' && $p['plat_dinas_diberikan']): ?>
                                            <br><small class="text-success"><i class="fas fa-check"></i> Plat dinas diberikan</small>
                                        <?php endif; ?>
                                    </td>
                                    <td><?= date('d/m/Y H:i', strtotime($p['created_at'])) ?></td>
                                    <td>
                                        <button class="btn btn-sm btn-info" onclick="viewDetail(<?= $p['id'] ?>)">
                                            <i class="fas fa-eye"></i> Detail
                                        </button>
                                        <?php if ($p['status'] === 'active'): ?>
                                            <button class="btn btn-sm btn-warning" onclick="returnVehicle(<?= $p['id'] ?>)">
                                                <i class="fas fa-undo"></i> Kembalikan
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
                    <i class="fas fa-id-card fa-3x text-muted mb-3"></i>
                    <h5>Belum ada pengajuan pinjam pakai</h5>
                    <p class="text-muted">Silakan ajukan pinjam pakai pertama Anda untuk mendapatkan plat dinas</p>
                </div>
            <?php endif; ?>
        </div>
    </div>
</div>

<!-- Modal Detail Pinjam Pakai -->
<div class="modal fade" id="detailModal" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Detail Pinjam Pakai</h5>
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

<!-- Modal Return Vehicle -->
<div class="modal fade" id="returnModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Kembalikan Kendaraan</h5>
                <button type="button" class="close" data-dismiss="modal">
                    <span>&times;</span>
                </button>
            </div>
            <form id="returnForm">
                <div class="modal-body">
                    <input type="hidden" id="return_id" name="pinjam_pakai_id">
                    <div class="form-group">
                        <label for="kondisi_kembali">Kondisi Kendaraan Saat Dikembalikan</label>
                        <textarea class="form-control" id="kondisi_kembali" name="kondisi_kembali" rows="4" 
                                placeholder="Jelaskan kondisi kendaraan, kerusakan (jika ada), kilometer terakhir, dll..." required></textarea>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-dismiss="modal">Batal</button>
                    <button type="submit" class="btn btn-primary">Kembalikan Kendaraan</button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
function viewDetail(id) {
    fetch('ajax/get_pinjam_pakai_detail.php?id=' + id)
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

function returnVehicle(id) {
    document.getElementById('return_id').value = id;
    $('#returnModal').modal('show');
}

document.getElementById('returnForm').addEventListener('submit', function(e) {
    e.preventDefault();
    
    const formData = new FormData(this);
    
    fetch('ajax/return_pinjam_pakai.php', {
        method: 'POST',
        body: formData
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            alert('Kendaraan berhasil dikembalikan');
            $('#returnModal').modal('hide');
            location.reload();
        } else {
            alert('Gagal mengembalikan kendaraan: ' + data.message);
        }
    })
    .catch(error => {
        console.error('Error:', error);
        alert('Terjadi kesalahan saat mengembalikan kendaraan');
    });
});
</script>
