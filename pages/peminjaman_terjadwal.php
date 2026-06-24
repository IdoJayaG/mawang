<?php
require_once 'includes/auth.php';
require_login();
require_role(['user','driver']);

$user_id = get_current_user_id();
$msg = '';

// Helper: check if a table exists
function table_exists($mysqli, $table) {
    $res = $mysqli->query("SHOW TABLES LIKE '" . $mysqli->real_escape_string($table) . "'");
    return $res && $res->num_rows > 0;
}

// Handle form submission (adapted from form_peminjaman)
if ($_POST) {
    if (!function_exists('validate_csrf_token') || !validate_csrf_token($_POST['csrf_token'] ?? '')) {
        $msg = '<div class="alert alert-danger">Token keamanan tidak valid!</div>';
    } else {
        $kendaraan_id = (int)($_POST['kendaraan_id'] ?? 0);
        $keperluan = trim($_POST['keperluan'] ?? '');
        $tujuan = trim($_POST['tujuan'] ?? '');
        $tanggal_mulai = $_POST['tanggal_mulai'] ?? '';
        $tanggal_selesai = $_POST['tanggal_selesai'] ?? '';
        // Always require a driver name
        $nama_sopir = isset($_POST['nama_sopir']) ? trim($_POST['nama_sopir']) : null;
        $kontak_darurat = trim($_POST['kontak_darurat'] ?? '');
        $estimasi_km = isset($_POST['estimasi_km']) && $_POST['estimasi_km'] !== '' ? (int)$_POST['estimasi_km'] : null;
        $estimasi_bbm = isset($_POST['estimasi_bbm']) && $_POST['estimasi_bbm'] !== '' ? (int)$_POST['estimasi_bbm'] : null;

        // Basic validation
        if (empty($keperluan) || empty($tujuan) || empty($tanggal_mulai) || empty($tanggal_selesai)) {
            $msg = '<div class="alert alert-danger">Semua field wajib harus diisi!</div>';
        } elseif (strtotime($tanggal_mulai) < strtotime(date('Y-m-d H:i'))) {
            $msg = '<div class="alert alert-danger">Tanggal mulai tidak boleh di masa lalu!</div>';
        } elseif (strtotime($tanggal_selesai) <= strtotime($tanggal_mulai)) {
            $msg = '<div class="alert alert-danger">Tanggal selesai harus setelah tanggal mulai!</div>';
        } else {
            // Kebijakan peminjaman: hanya kendaraan jenis Bus.
            $busChk = $mysqli->query("SELECT jenis FROM kendaraan WHERE id = " . (int)$kendaraan_id);
            $busRow = $busChk ? $busChk->fetch_assoc() : null;
            $jenisKendaraan = strtolower(trim((string)($busRow['jenis'] ?? '')));
            if ($jenisKendaraan !== 'bus') {
                $msg = '<div class="alert alert-danger">Pengajuan hanya diperbolehkan untuk kendaraan jenis Bus.</div>';
            }

            if (empty($msg)) {
            // Check conflicts using jadwal_kendaraan if available, otherwise try peminjaman_kendaraan
            $conflicts = 0;
            if (table_exists($mysqli, 'jadwal_kendaraan')) {
                $cols_info = $mysqli->query("SHOW COLUMNS FROM jadwal_kendaraan")->fetch_all(MYSQLI_ASSOC);
                $cols_names = array_column($cols_info, 'Field');
                $has_status = in_array('status', $cols_names);

                $sql = "SELECT COUNT(*) as conflicts FROM jadwal_kendaraan WHERE kendaraan_id = ? ";
                if ($has_status) {
                    $sql .= " AND status IN ('scheduled', 'active') ";
                }
                $sql .= " AND ((tanggal_mulai <= ? AND tanggal_selesai >= ?) OR (tanggal_mulai <= ? AND tanggal_selesai >= ?) OR (tanggal_mulai >= ? AND tanggal_selesai <= ?))";
                $check_stmt = $mysqli->prepare($sql);
                $check_stmt->bind_param('issssss', $kendaraan_id, $tanggal_mulai, $tanggal_mulai, $tanggal_selesai, $tanggal_selesai, $tanggal_mulai, $tanggal_selesai);
                $check_stmt->execute();
                $check_stmt->bind_result($conflicts);
                $check_stmt->fetch();
                $check_stmt->close();
            } else {
                // fallback to peminjaman_kendaraan table conflicts (if exists)
                if ($mysqli->query("SHOW TABLES LIKE 'peminjaman_kendaraan'")->num_rows > 0) {
                    $sql = "SELECT COUNT(*) as cnt FROM peminjaman_kendaraan WHERE kendaraan_id = ? AND status IN ('Approved','Ongoing') AND ((tanggal_mulai <= ? AND tanggal_selesai >= ?) OR (tanggal_mulai <= ? AND tanggal_selesai >= ?))";
                    $check_stmt = $mysqli->prepare($sql);
                    $check_stmt->bind_param('issss', $kendaraan_id, $tanggal_mulai, $tanggal_mulai, $tanggal_selesai, $tanggal_selesai);
                    $check_stmt->execute();
                    $conflicts = $check_stmt->get_result()->fetch_assoc()['cnt'];
                    $check_stmt->close();
                }
            }

            if ($conflicts > 0) {
                $msg = '<div class="alert alert-danger">Kendaraan sudah dipesan pada periode waktu tersebut!</div>';
            } else {
                // Insert into peminjaman_terjadwal using dynamic column mapping
                $columns = $mysqli->query("SHOW COLUMNS FROM peminjaman_terjadwal")->fetch_all(MYSQLI_ASSOC);
                $cols = array_column($columns, 'Field');

                // determine applicant column if any
                $applicant_col = null;
                foreach (['pemohon_id', 'peminjam_id', 'pengguna_id', 'user_id'] as $c) {
                    if (in_array($c, $cols)) { $applicant_col = $c; break; }
                }

                $desired_map = [];
                if ($applicant_col) { $desired_map[$applicant_col] = ['type'=>'i','value'=>$user_id]; }
                $desired_map['kendaraan_id'] = ['type'=>'i','value'=>$kendaraan_id];
                $desired_map['keperluan'] = ['type'=>'s','value'=>$keperluan];
                $desired_map['tujuan'] = ['type'=>'s','value'=>$tujuan];
                $desired_map['tanggal_mulai'] = ['type'=>'s','value'=>$tanggal_mulai];
                $desired_map['tanggal_selesai'] = ['type'=>'s','value'=>$tanggal_selesai];
                $desired_map['nama_sopir'] = ['type'=>'s','value'=>$nama_sopir];
                $desired_map['kontak_darurat'] = ['type'=>'s','value'=>$kontak_darurat];
                $desired_map['estimasi_km'] = ['type'=>'i','value'=>$estimasi_km];
                $desired_map['estimasi_bbm'] = ['type'=>'i','value'=>$estimasi_bbm];
                $desired_map['status'] = ['type'=>'s','value'=>'pending'];
                $desired_map['created_by'] = ['type'=>'i','value'=>$user_id];

                $insert_cols = [];
                $types = '';
                $values = [];
                foreach ($desired_map as $col => $meta) {
                    if (in_array($col, $cols)) {
                        $insert_cols[] = $col;
                        $types .= $meta['type'];
                        $values[] = $meta['value'];
                    }
                }

                if (empty($insert_cols)) {
                    $msg = '<div class="alert alert-danger">Tidak ada kolom valid untuk menyimpan data peminjaman terjadwal.</div>';
                } else {
                    $placeholders = array_fill(0, count($insert_cols), '?');
                    $sql = "INSERT INTO peminjaman_terjadwal (" . implode(', ', $insert_cols) . ") VALUES (" . implode(', ', $placeholders) . ")";
                    $stmt = $mysqli->prepare($sql);
                    if ($stmt) {
                        $bind_params = [];
                        $bind_params[] = & $types;
                        for ($i = 0; $i < count($values); $i++) { $bind_params[] = & $values[$i]; }
                        call_user_func_array([$stmt, 'bind_param'], $bind_params);

                        if ($stmt->execute()) {
                            $peminjaman_id = $mysqli->insert_id;

                            // add to jadwal_kendaraan if exists
                            if (table_exists($mysqli, 'jadwal_kendaraan')) {
                                $keterangan = "Peminjaman terjadwal: " . $keperluan;
                                $schedule_stmt = $mysqli->prepare("INSERT INTO jadwal_kendaraan (kendaraan_id, tipe_penggunaan, referensi_id, pengguna_id, tanggal_mulai, tanggal_selesai, keterangan) VALUES (?, 'peminjaman_terjadwal', ?, ?, ?, ?, ?)");
                                if ($schedule_stmt) {
                                    $schedule_stmt->bind_param('iiisss', $kendaraan_id, $peminjaman_id, $user_id, $tanggal_mulai, $tanggal_selesai, $keterangan);
                                    $schedule_stmt->execute();
                                    $schedule_stmt->close();
                                }
                            }

                            // notify admin-like roles
                            $kendaraan_info = $mysqli->query("SELECT no_polisi, merk, tipe FROM kendaraan WHERE id = $kendaraan_id")->fetch_assoc();
                            $current_user = get_logged_in_user();
                            $username = $current_user ? $current_user['nama_lengkap'] : 'Unknown User';
                            $notification_msg = "Pengajuan peminjaman kendaraan {$kendaraan_info['no_polisi']} ({$kendaraan_info['merk']} {$kendaraan_info['tipe']}) dari " . $username . " untuk keperluan: $keperluan";

                            $admin_role_ids = function_exists('get_admin_like_role_ids') ? get_admin_like_role_ids() : [];
                            $admin_like_users = null;
                            if (!empty($admin_role_ids)) {
                                $in_ids = implode(',', array_map('intval', $admin_role_ids));
                                $admin_like_users = $mysqli->query("SELECT p.id FROM pengguna p JOIN user_account ua ON p.id = ua.pengguna_id WHERE ua.role_id IN ({$in_ids})");
                            }
                            $escaped_msg = $mysqli->real_escape_string($notification_msg);
                            while ($admin_like_users && ($admin = $admin_like_users->fetch_assoc())) {
                                $mysqli->query("INSERT INTO notifikasi (user_id, message, title, type, category) VALUES ({$admin['id']}, '$escaped_msg', 'Pengajuan Peminjaman Terjadwal', 'info', 'vehicle')");
                            }

                            $msg = '<div class="alert alert-success">Pengajuan peminjaman terjadwal berhasil disubmit! Menunggu persetujuan admin/pimpinan.</div>';
                            log_user_activity("Mengajukan peminjaman terjadwal kendaraan ID: $kendaraan_id untuk keperluan: $keperluan");
                        } else {
                            $msg = '<div class="alert alert-danger">Error: ' . $stmt->error . '</div>';
                        }
                        $stmt->close();
                    } else {
                        $msg = '<div class="alert alert-danger">Error menyiapkan query: ' . $mysqli->error . '</div>';
                    }
                }
            }
            }
        }
    }
}

// Get available vehicles
$cols_info = $mysqli->query("SHOW COLUMNS FROM kendaraan")->fetch_all(MYSQLI_ASSOC);
$cols_names = array_column($cols_info, 'Field');
if (in_array('status', $cols_names)) {
    $vehicles = $mysqli->query("SELECT * FROM kendaraan WHERE status = 'Tersedia' AND LOWER(COALESCE(jenis,'')) = 'bus' ORDER BY merk, tipe")->fetch_all(MYSQLI_ASSOC);
} elseif (in_array('status_peminjaman', $cols_names)) {
    $vehicles = $mysqli->query("SELECT * FROM kendaraan WHERE status_peminjaman = 'Tersedia' AND LOWER(COALESCE(jenis,'')) = 'bus' ORDER BY merk, tipe")->fetch_all(MYSQLI_ASSOC);
} else {
    $vehicles = $mysqli->query("SELECT * FROM kendaraan WHERE LOWER(COALESCE(jenis,'')) = 'bus' ORDER BY merk, tipe")->fetch_all(MYSQLI_ASSOC);
}

// Get user's peminjaman from the same table used by the main form (peminjaman_kendaraan)
// Detect which applicant column exists and query accordingly
$peminjaman_list = [];
if ($mysqli->query("SHOW TABLES LIKE 'peminjaman_kendaraan'")->num_rows > 0) {
    $cols_info = $mysqli->query("SHOW COLUMNS FROM peminjaman_kendaraan")->fetch_all(MYSQLI_ASSOC);
    $cols = array_column($cols_info, 'Field');
    $applicant_col = null;
    foreach (['pemohon_id', 'peminjam_id', 'pengguna_id', 'user_id'] as $c) {
        if (in_array($c, $cols)) { $applicant_col = $c; break; }
    }

    // detect approver column variants to avoid unknown column errors
    $approver_col = null;
    foreach (['approved_by', 'approver_id', 'approved_by_id', 'approver'] as $c) {
        if (in_array($c, $cols)) { $approver_col = $c; break; }
    }

    $applicant_where = $applicant_col ? "p.$applicant_col = ?" : "p.created_by = ?";

    $select_extra = '';
    $join_approver = '';
    if ($approver_col) {
        $select_extra = ", approver.nama_lengkap as approved_by_name";
        $join_approver = "LEFT JOIN pengguna approver ON p." . $approver_col . " = approver.id\n";
    }

    $sql = "SELECT p.*, k.no_polisi, k.merk, k.tipe, k.jenis" . $select_extra . "\n"
         . "FROM peminjaman_kendaraan p\n"
         . "LEFT JOIN kendaraan k ON p.kendaraan_id = k.id\n"
         . $join_approver
         . "WHERE " . $applicant_where . "\n"
         . "ORDER BY p.created_at DESC";

    $stmt = $mysqli->prepare($sql);
    if ($stmt) {
        $stmt->bind_param('i', $user_id);
        $stmt->execute();
        $peminjaman_list = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
        $stmt->close();
    }
}

// Status badges
function getStatusBadge($status) {
    $badges = [
        'pending' => 'badge-warning',
        'approved' => 'badge-success',
        'rejected' => 'badge-danger',
        'ongoing' => 'badge-info',
        'completed' => 'badge-primary',
        'cancelled' => 'badge-secondary'
    ];
    
    $labels = [
        'pending' => 'Menunggu Persetujuan',
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
?>

<div class="container-fluid">
    <div class="page-header">
        <h1><i class="fas fa-calendar-check"></i> Peminjaman Terjadwal</h1>
            <div class="proposal-actions">
                <a href="index.php?page=form_peminjaman" class="btn btn-primary btn-md"><i class="fas fa-plus"></i> Buka Form</a>
            </div>
    </div>

    <?= $msg ?>

    <!-- Daftar Peminjaman Terjadwal User -->
    <div class="card">
        <div class="card-header">
            <h3><i class="fas fa-list"></i> Riwayat Peminjaman Terjadwal</h3>
        </div>
        <div class="card-body">
            <?php if (count($peminjaman_list) > 0): ?>
                <div class="table-responsive">
                    <table class="table table-striped">
                        <thead>
                            <tr>
                                <th>Kendaraan</th>
                                <th>Keperluan</th>
                                <th>Jadwal</th>
                                <th>Status</th>
                                <th>Tanggal Pengajuan</th>
                                <th>Aksi</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($peminjaman_list as $p): ?>
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
                                        <strong><?= date('d/m/Y', strtotime($p['tanggal_mulai'])) ?></strong><br>
                                        <?php
                                            $jam_mulai = !empty($p['jam_mulai']) ? date('H:i', strtotime($p['jam_mulai'])) : '';
                                            $jam_selesai = !empty($p['jam_selesai']) ? date('H:i', strtotime($p['jam_selesai'])) : '';
                                        ?>
                                        <small class="text-muted"><?= $jam_mulai || $jam_selesai ? htmlspecialchars($jam_mulai . ($jam_mulai || $jam_selesai ? ' - ' : '') . $jam_selesai) : '' ?></small><br>
                                        <?php if (!empty($p['tanggal_selesai']) && $p['tanggal_mulai'] != $p['tanggal_selesai']): ?>
                                            <small class="text-info">s/d <?= date('d/m/Y', strtotime($p['tanggal_selesai'])) ?></small>
                                        <?php endif; ?>
                                    </td>
                                    <td><?= getStatusBadge($p['status']) ?></td>
                                    <td><?= date('d/m/Y H:i', strtotime($p['created_at'])) ?></td>
                                    <td>
                                        <button type="button" class="btn btn-sm btn-info" onclick="viewDetail(<?= $p['id'] ?>)">
                                            <i class="fas fa-eye"></i> Detail
                                        </button>
                                        <?php if (isset($p['status']) && strtolower($p['status']) === 'pending'): ?>
                                            <button type="button" class="btn btn-sm btn-danger" onclick="openCancelModal(<?= $p['id'] ?>)">
                                                <i class="fas fa-times"></i> Batal
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
                    <i class="fas fa-calendar-times fa-3x text-muted mb-3"></i>
                    <h5>Belum ada peminjaman terjadwal</h5>
                    <p class="text-muted">Silakan ajukan peminjaman terjadwal pertama Anda</p>
                </div>
            <?php endif; ?>
        </div>
    </div>
</div>

<!-- Cancel Modal -->
<div class="modal fade" id="cancelModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header custom-header">
                <h5 class="modal-title">Alasan Pembatalan</h5>
                <button type="button" class="btn btn-sm btn-outline-dark modal-close" aria-label="Tutup" onclick="closeModal('#cancelModal')">
                    <i class="fas fa-times"></i>
                </button>
            </div>
            <div class="modal-body">
                <input type="hidden" id="cancel_id" value="">
                <div class="form-group">
                    <label for="cancel_reason">Silakan jelaskan alasan pembatalan</label>
                    <textarea id="cancel_reason" class="form-control" rows="4" placeholder="Alasan pembatalan..."></textarea>
                </div>
                <div id="cancel_error" class="text-danger small" style="display:none"></div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-light" onclick="closeModal('#cancelModal')">Tutup</button>
                <button type="button" id="cancel_submit" class="btn btn-danger">Kirim Pembatalan</button>
            </div>
        </div>
    </div>
</div>

<!-- Modal Detail Peminjaman -->
<div class="modal fade" id="detailModal" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header custom-header">
                <h5 class="modal-title">Detail Peminjaman Terjadwal</h5>
                <button type="button" class="btn btn-sm btn-outline-light modal-close" aria-label="Tutup" onclick="closeModal('#detailModal')">
                    <i class="fas fa-times"></i>
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
    const jam_mulai = document.getElementById('jam_mulai').value;
    const tanggal_selesai = document.getElementById('tanggal_selesai').value;
    const jam_selesai = document.getElementById('jam_selesai').value;
    
    if (!kendaraan_id || !tanggal_mulai || !jam_mulai || !tanggal_selesai || !jam_selesai) {
        alert('Harap lengkapi semua field terlebih dahulu');
        return;
    }
    
    // AJAX call to check availability
    fetch('ajax/check_vehicle_availability.php', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
        },
        body: JSON.stringify({
            kendaraan_id: kendaraan_id,
            datetime_mulai: tanggal_mulai + ' ' + jam_mulai,
            datetime_selesai: tanggal_selesai + ' ' + jam_selesai
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
    try {
        // ensure previous modals/backdrops are cleared
        $('.modal').modal('hide'); $('.modal-backdrop').remove();
        // show loading state immediately to avoid perceived freeze
        document.getElementById('detailContent').innerHTML = '<div class="text-center p-4">\n  <div class="spinner-border text-primary" role="status"><span class="sr-only">Loading...</span></div>\n  <div class="mt-2">Memuat detail...</div>\n</div>';
        $('#detailModal').modal('show');

        // use AbortController to guard long requests
        const controller = new AbortController();
        const signal = controller.signal;
        const timeoutId = setTimeout(() => { controller.abort(); }, 10000);

        fetch('ajax/get_peminjaman_detail.php?id=' + id, { signal })
            .then(response => {
                clearTimeout(timeoutId);
                if (!response.ok) throw new Error('Server error: ' + response.status);
                const ct = response.headers.get('content-type') || '';
                if (ct.indexOf('application/json') === -1) return response.text().then(t => { throw new Error('Unexpected response: ' + t); });
                return response.json();
            })
            .then(data => {
                if (!data.success) throw new Error(data.message || 'Gagal memuat detail');
                // populate and ensure modal visible
                document.getElementById('detailContent').innerHTML = data.html;
                if (!$('#detailModal').hasClass('show')) $('#detailModal').modal('show');
            })
            .catch(error => {
                console.error('Error:', error);
                let msg = error.name === 'AbortError' ? 'Permintaan dibatalkan karena memakan waktu terlalu lama.' : (error.message || 'Gagal memuat detail');
                // show error content inside modal so user can close it
                document.getElementById('detailContent').innerHTML = '<div class="alert alert-danger">' + safeHtml(msg) + '</div>';
                // cleanup any potential leftover modal/backdrop after short delay
                setTimeout(function() { $('.modal-backdrop').remove(); $('body').removeClass('modal-open'); }, 50);
            });
    } catch (err) {
        console.error('Unexpected error in viewDetail:', err);
        alert('Terjadi kesalahan tak terduga. Silakan coba lagi.');
        $('.modal').modal('hide'); $('.modal-backdrop').remove();
        $('body').removeClass('modal-open');
    }
}

// small helper to escape text for insertion into innerHTML
function safeHtml(s) {
    if (!s) return '';
    return s.replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;');
}

function closeModal(selector) {
    try {
        $(selector).modal('hide');
        setTimeout(function() {
            $('.modal-backdrop').remove();
            $('body').removeClass('modal-open');
            $(selector).removeClass('show').attr('aria-hidden', 'true').css('display', 'none');
        }, 50);
    } catch (e) {
        console.error('closeModal error', e);
    }
}

function openCancelModal(id) {
    try {
        // clear any existing modal state and show cancel modal
        $('.modal').modal('hide'); $('.modal-backdrop').remove();
        $('body').removeClass('modal-open');

        document.getElementById('cancel_id').value = id;
        document.getElementById('cancel_reason').value = '';
        document.getElementById('cancel_error').style.display = 'none';
        $('#cancelModal').modal('show');
    } catch (err) {
        console.error('Error opening cancel modal:', err);
        alert('Gagal membuka form pembatalan. Silakan muat ulang halaman dan coba lagi.');
        $('.modal').modal('hide'); $('.modal-backdrop').remove();
        $('body').removeClass('modal-open');
    }
}

// Ensure modal state is cleaned when modals hide to avoid stuck backdrop or disabled UI
$(document).ready(function() {
    function cleanupModalState() {
        try {
            // if no modal visible but a backdrop exists, remove it and restore body state
            if ($('.modal.show').length === 0 && $('.modal-backdrop').length > 0) {
                $('.modal-backdrop').remove();
                $('body').removeClass('modal-open');
                // ensure any modals are hidden
                $('.modal').each(function() { $(this).removeClass('show').attr('aria-hidden', 'true').css('display', 'none'); });
            }
        } catch (e) {
            // ignore
        }
    }

    $('#detailModal, #cancelModal').on('hidden.bs.modal', function() {
        setTimeout(cleanupModalState, 50);
    });

    // safety periodic cleanup: remove orphan backdrop if present
    setInterval(cleanupModalState, 3000);

    // quick cleanup after any button/link click to avoid briefly stuck overlay
    $(document).on('click', '.btn, button, a', function() {
        setTimeout(cleanupModalState, 10);
    });
});

document.getElementById('cancel_submit').addEventListener('click', function() {
    const btn = this;
    const id = document.getElementById('cancel_id').value;
    const reason = document.getElementById('cancel_reason').value.trim();
    const errorEl = document.getElementById('cancel_error');
    errorEl.style.display = 'none'; errorEl.textContent = '';

    if (!reason) {
        errorEl.textContent = 'Alasan pembatalan wajib diisi.'; errorEl.style.display = '';
        return;
    }

    btn.disabled = true; btn.textContent = 'Mengirim...';
    // timeout guard to re-enable button if request hangs (10s)
    const guard = setTimeout(() => {
        if (btn.disabled) {
            btn.disabled = false; btn.textContent = 'Kirim Pembatalan';
            errorEl.textContent = 'Permintaan memakan waktu terlalu lama, silakan coba lagi.'; errorEl.style.display = '';
            // ensure modal/backdrop state is correct
            $('.modal').modal('hide'); $('.modal-backdrop').remove();
        }
    }, 10000);

    fetch('ajax/cancel_peminjaman_terjadwal.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ id: id, reason: reason })
    })
    .then(r => r.json())
    .then(data => {
        clearTimeout(guard);
        btn.disabled = false; btn.textContent = 'Kirim Pembatalan';
        if (data.success) {
            $('#cancelModal').modal('hide');
            // close detail modal if open
            $('#detailModal').modal('hide');
            $('.modal-backdrop').remove();
            // reload to reflect status change
            location.reload();
        } else {
            errorEl.textContent = data.message || 'Gagal membatalkan'; errorEl.style.display = '';
            // ensure modal/backdrop state didn't get stuck
            $('.modal-backdrop').remove();
        }
    })
    .catch(err => {
        clearTimeout(guard);
        btn.disabled = false; btn.textContent = 'Kirim Pembatalan';
        errorEl.textContent = err.message || 'Terjadi kesalahan'; errorEl.style.display = '';
        $('.modal').modal('hide'); $('.modal-backdrop').remove();
    });
});

// Auto-update tanggal_selesai when tanggal_mulai changes
document.getElementById('tanggal_mulai').addEventListener('change', function() {
    const tanggal_selesai = document.getElementById('tanggal_selesai');
    if (!tanggal_selesai.value || tanggal_selesai.value < this.value) {
        tanggal_selesai.value = this.value;
    }
    tanggal_selesai.min = this.value;
});
</script>
