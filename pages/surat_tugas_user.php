<?php
require_once 'includes/auth.php';
require_login();
require_role('user');

$user_id = get_current_user_id();
$msg = '';

// Helpers for defensive DB operations (table/column checks and notifications)
function table_exists($mysqli, $table) {
    $table = $mysqli->real_escape_string($table);
    $res = $mysqli->query("SHOW TABLES LIKE '{$table}'");
    return $res && $res->num_rows > 0;
}

function get_table_columns($mysqli, $table) {
    $cols = [];
    $table = $mysqli->real_escape_string($table);
    $res = $mysqli->query("SHOW COLUMNS FROM `{$table}`");
    if ($res) {
        while ($r = $res->fetch_assoc()) {
            $cols[] = $r['Field'];
        }
    }
    return $cols;
}

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
        return $mysqli->query("INSERT INTO {$table} (user_id, pesan) VALUES ({$user_id}, '{$msg_esc}')");
    }

    if (in_array('message', $cols)) {
        return $mysqli->query("INSERT INTO {$table} (user_id, message) VALUES ({$user_id}, '{$msg_esc}')");
    }

    return false;
}

// Handle user response to surat tugas
if ($_POST && isset($_POST['action'])) {
    if ($_POST['action'] === 'respond_surat_tugas') {
        $surat_tugas_id = (int)$_POST['surat_tugas_id'];
        $response = $_POST['response']; // 'accepted' or 'rejected'
        $response_notes = trim($_POST['response_notes']);
        
        if ($surat_tugas_id && $response) {
            $stmt = $mysqli->prepare("
                UPDATE surat_tugas 
                SET user_response = ?, user_response_notes = ?, user_response_at = NOW()
                WHERE id = ? AND penerima_id = ? AND status IN ('approved', 'pending_approval')
            ");
            $stmt->bind_param('ssii', $response, $response_notes, $surat_tugas_id, $user_id);
            
                if ($stmt->execute()) {
                if ($response === 'accepted') {
                    // Update status to ongoing if accepted
                    $mysqli->query("UPDATE surat_tugas SET status = 'ongoing' WHERE id = $surat_tugas_id");
                    // If jadwal_kendaraan exists, mark schedule active
                    if (table_exists($mysqli, 'jadwal_kendaraan')) {
                        $mysqli->query("UPDATE jadwal_kendaraan SET status = 'active' WHERE tipe_penggunaan = 'surat_tugas' AND referensi_id = $surat_tugas_id");
                    }
                    // Ensure kendaraan status becomes 'Dipinjam' for this surat tugas
                    try {
                        $resKid = $mysqli->query("SELECT kendaraan_id FROM surat_tugas WHERE id = $surat_tugas_id");
                        if ($resKid && ($rowKid = $resKid->fetch_assoc())) {
                            $kid = (int)($rowKid['kendaraan_id'] ?? 0);
                            if ($kid > 0 && table_exists($mysqli, 'kendaraan')) {
                                // Prefer status_peminjaman column if present
                                $cols = get_table_columns($mysqli, 'kendaraan');
                                if (in_array('status_peminjaman', $cols)) {
                                    $mysqli->query("UPDATE kendaraan SET status_peminjaman = 'Dipinjam' WHERE id = $kid");
                                } elseif (in_array('status', $cols)) {
                                    $mysqli->query("UPDATE kendaraan SET status = 'Dipinjam' WHERE id = $kid");
                                }
                            }
                        }
                    } catch (Exception $e) { /* ignore */ }
                    
                    $msg = '<div class="alert alert-success">Surat tugas berhasil diterima! Status tugas sekarang aktif.</div>';
                    log_user_activity("Menerima surat tugas ID: $surat_tugas_id");
                } else {
                    // If rejected, cancel the schedule if table present
                    if (table_exists($mysqli, 'jadwal_kendaraan')) {
                        $mysqli->query("UPDATE jadwal_kendaraan SET status = 'cancelled' WHERE tipe_penggunaan = 'surat_tugas' AND referensi_id = $surat_tugas_id");
                    }
                    
                    $msg = '<div class="alert alert-info">Surat tugas telah ditolak.</div>';
                    log_user_activity("Menolak surat tugas ID: $surat_tugas_id");
                }
                
                // Notify pembuat surat tugas
                // The DB dump uses `pengguna_id` and `perihal` instead of `pembuat_id`/`judul_tugas`.
                // Alias them so the rest of the code works without changing the database.
                $surat_info = $mysqli->query("SELECT pengguna_id AS pembuat_id, nomor_surat, perihal AS judul_tugas FROM surat_tugas WHERE id = $surat_tugas_id")->fetch_assoc();
                $current_user = get_logged_in_user();
                $response_text = $response === 'accepted' ? 'menerima' : 'menolak';
                $notification_msg = "{$current_user['nama_lengkap']} $response_text surat tugas {$surat_info['nomor_surat']} - {$surat_info['judul_tugas']}";
                
                // Insert notification defensively
                insert_notification($mysqli, (int)$surat_info['pembuat_id'], $notification_msg, 'Respon Surat Tugas');
                
            } else {
                $msg = '<div class="alert alert-danger">Gagal merespon surat tugas. Silakan coba lagi.</div>';
            }
            $stmt->close();
        }
    }
}

// Get user's surat tugas (guarded)
// Get user's surat tugas (guarded)
$surat_tugas = [];
$columns = [];
$cols_res = $mysqli->query("SHOW COLUMNS FROM surat_tugas");
if ($cols_res) {
    while ($c = $cols_res->fetch_assoc()) {
        $columns[] = $c['Field'];
    }
}

$recipient_col = null;
$candidates = ['penerima_id', 'pengguna_id', 'recipient_id', 'user_id', 'penerima'];
foreach ($candidates as $cand) {
    if (in_array($cand, $columns)) { $recipient_col = $cand; break; }
}

if ($recipient_col) {
    // detect possible approver/admin column names in surat_tugas and add JOIN/select only when present
    $cols_info = $mysqli->query("SHOW COLUMNS FROM surat_tugas")->fetch_all(MYSQLI_ASSOC);
    $cols = array_column($cols_info, 'Field');
    $approver_col = null;
    foreach (['approval_admin_id','approved_by','approval_by','approver_id','approved_by_id','approver','approved_admin_id'] as $c) {
        if (in_array($c, $cols)) { $approver_col = $c; break; }
    }
    $select_extra = '';
    $join_admin = '';
    if ($approver_col) {
        $select_extra = ', admin.nama_lengkap as approved_by_name';
        $join_admin = " LEFT JOIN pengguna admin ON surat_tugas.{$approver_col} = admin.id";
    }

    $sql = "SELECT surat_tugas.*, 
        pembuat.nama_lengkap as pembuat_name, pembuat.pangkat as pembuat_pangkat,
           k.no_polisi, k.merk, k.tipe, k.jenis" . $select_extra . "\n"
         . "    FROM surat_tugas\n"
         . "    LEFT JOIN pengguna pembuat ON surat_tugas.pengguna_id = pembuat.id\n"
         . "    LEFT JOIN kendaraan k ON surat_tugas.kendaraan_id = k.id" . $join_admin . "\n"
         . "    WHERE surat_tugas." . $recipient_col . " = ?\n"
         . "    ORDER BY surat_tugas.created_at DESC";

    $surat_tugas_stmt = $mysqli->prepare($sql);
    if ($surat_tugas_stmt) {
        $surat_tugas_stmt->bind_param('i', $user_id);
        $surat_tugas_stmt->execute();
        $result = $surat_tugas_stmt->get_result();
        $surat_tugas = $result->fetch_all(MYSQLI_ASSOC);
        $surat_tugas_stmt->close();
    } else {
        $surat_tugas = [];
    }
} else {
    // No recipient column available in the DB dump — cannot list per-user surat tugas
    $msg .= '<div class="alert alert-warning">Tabel <code>surat_tugas</code> tidak memiliki kolom penerima (contoh: <em>penerima_id</em>). Halaman ini tidak dapat menampilkan surat tugas khusus untuk pengguna saat ini.</div>';
    $surat_tugas = [];
}

// Count statistics
// Normalize status values coming from the DB (accept Indonesian/English variations)
function normalize_status($s) {
    if (!$s) return '';
    $s = trim(strtolower((string)$s));
    $map = [
        'draft' => 'draft',
        'pending_approval' => 'pending_approval',
        'pending approval' => 'pending_approval',
        'menunggu persetujuan' => 'pending_approval',
        'menunggu persetujuan admin' => 'pending_approval',
        'menunggu respon anda' => 'pending_approval',
        'approved' => 'approved',
        'disetujui' => 'approved',
        'rejected' => 'rejected',
        'ditolak' => 'rejected',
        'ongoing' => 'ongoing',
        'dalam perjalanan' => 'ongoing',
        'sedang berlangsung' => 'ongoing',
        'completed' => 'completed',
        'selesai' => 'completed',
        'cancelled' => 'cancelled',
        'dibatalkan' => 'cancelled'
    ];

    if (isset($map[$s])) return $map[$s];
    foreach ($map as $k => $v) {
        if (strpos($s, $k) !== false) return $v;
    }
    return $s;
}

// Build statistics using normalized statuses
$stats = [ 'total' => count($surat_tugas), 'pending' => 0, 'active' => 0, 'completed' => 0 ];
foreach ($surat_tugas as $st) {
    $ns = normalize_status($st['status'] ?? '');
    $ur = $st['user_response'] ?? 'pending';
    if ($ns === 'pending_approval' && $ur === 'pending') $stats['pending']++;
    if ($ns === 'ongoing') $stats['active']++;
    if ($ns === 'completed') $stats['completed']++;
}

// Status badges
function getStatusBadge($status, $user_response = 'pending') {
    if ($status === 'pending_approval' && $user_response === 'pending') {
        return '<span class="badge badge-warning">Menunggu Respon Anda</span>';
    }
    
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
        'pending_approval' => 'Menunggu Persetujuan Admin',
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

function getUserResponseBadge($response) {
    $badges = [
        'pending' => 'badge-warning',
        'accepted' => 'badge-success',
        'rejected' => 'badge-danger'
    ];
    
    $labels = [
        'pending' => 'Belum Merespon',
        'accepted' => 'Diterima',
        'rejected' => 'Ditolak'
    ];
    
    $badge_class = $badges[$response] ?? 'badge-secondary';
    $label = $labels[$response] ?? $response;
    
    return "<span class=\"badge $badge_class\">$label</span>";
}
?>

<div class="container-fluid">
    <div class="mb-4 pb-3 border-bottom">
        <h1><i class="fas fa-file-signature"></i> Surat Tugas Saya</h1>
        <p class="text-muted">Kelola surat tugas yang diterima dan lihat detail penugasan kendaraan</p>
    </div>

    <?= $msg ?>

    <!-- Statistics -->
    <div class="row mb-4">
        <div class="col-md-3">
            <div class="card shadow-sm rounded-3 mb-3 card-hover d-flex flex-row align-items-center gap-3 p-3 border-start border-secondary border-4">
                <div class="text-secondary opacity-75 fs-2-5">
                    <i class="fas fa-file-signature"></i>
                </div>
                <div>
                    <h3 class="mb-0 fw-bold fs-1 text-dark"><?= $stats['total'] ?></h3>
                    <p class="mb-0 text-muted small">Total Surat Tugas</p>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card shadow-sm rounded-3 mb-3 card-hover d-flex flex-row align-items-center gap-3 p-3 border-start border-warning border-4">
                <div class="text-warning opacity-75 fs-2-5">
                    <i class="fas fa-clock"></i>
                </div>
                <div>
                    <h3 class="mb-0 fw-bold fs-1 text-dark"><?= $stats['pending'] ?></h3>
                    <p class="mb-0 text-muted small">Menunggu Respon</p>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card shadow-sm rounded-3 mb-3 card-hover d-flex flex-row align-items-center gap-3 p-3 border-start border-info border-4">
                <div class="text-info opacity-75 fs-2-5">
                    <i class="fas fa-play"></i>
                </div>
                <div>
                    <h3 class="mb-0 fw-bold fs-1 text-dark"><?= $stats['active'] ?></h3>
                    <p class="mb-0 text-muted small">Sedang Berlangsung</p>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card shadow-sm rounded-3 mb-3 card-hover d-flex flex-row align-items-center gap-3 p-3 border-start border-success border-4">
                <div class="text-success opacity-75 fs-2-5">
                    <i class="fas fa-check-circle"></i>
                </div>
                <div>
                    <h3 class="mb-0 fw-bold fs-1 text-dark"><?= $stats['completed'] ?></h3>
                    <p class="mb-0 text-muted small">Selesai</p>
                </div>
            </div>
        </div>
    </div>

    <!-- Daftar Surat Tugas -->
    <div class="card">
        <div class="card-header">
            <h3><i class="fas fa-list"></i> Daftar Surat Tugas</h3>
        </div>
        <div class="card-body">
            <?php if (count($surat_tugas) > 0): ?>
                <div class="row">
                    <?php foreach ($surat_tugas as $st): ?>
                        <div class="col-md-6 col-lg-4 mb-4">
                            <?php
                                $ns = normalize_status($st['status'] ?? '');
                                $is_waiting = $ns === 'pending_approval' && ($st['user_response'] ?? '') === 'pending';
                                $card_extra = $is_waiting ? 'border-warning border-2' : '';
                                // Determine place and period fields from available DB columns
                                $place = $st['tempat_tugas'] ?? $st['berangkat_dari'] ?? $st['kepada_tempat'] ?? '';
                                $start = $st['tanggal_mulai'] ?? $st['tanggal_berangkat'] ?? $st['tanggal_surat'] ?? null;
                                $end = $st['tanggal_selesai'] ?? $st['tanggal_kembali'] ?? null;
                            ?>
                            <div class="card shadow-sm rounded-3 h-100 d-flex flex-column card-hover <?= $card_extra ?>">
                                <div class="gradient-header text-white p-3 d-flex justify-content-between align-items-center">
                                    <div class="surat-number">
                                        <strong><?= htmlspecialchars($st['nomor_surat'] ?? '') ?></strong>
                                    </div>
                                    <div class="priority">
                                        <?= getPriorityBadge($st['prioritas'] ?? '') ?>
                                    </div>
                                </div>
                                
                                <div class="card-body p-4 flex-fill">
                                    <h5 class="mb-3 text-dark fw-semibold"><?= htmlspecialchars($st['judul_tugas'] ?? $st['perihal'] ?? '') ?></h5>
                                    
                                    <div class="mb-3">
                                                     <p><i class="fas fa-user"></i> <strong>Pembuat:</strong><br>
                                                         <?= htmlspecialchars($st['pembuat_pangkat'] ?? '') ?> <?= htmlspecialchars($st['pembuat_name'] ?? '') ?></p>

                                                     <p><i class="fas fa-car"></i> <strong>Kendaraan:</strong><br>
                                                         <?= htmlspecialchars($st['no_polisi'] ?? '') ?> - <?= htmlspecialchars($st['merk'] ?? '') ?> <?= htmlspecialchars($st['tipe'] ?? '') ?></p>

                                                     <?php if (!empty($st['driver_name'])): ?>
                                                         <p><i class="fas fa-user-tie"></i> <strong>Driver:</strong><br>
                                                             <?= htmlspecialchars($st['driver_name']) ?></p>
                                                     <?php endif; ?>

                                                     <p><i class="fas fa-bullseye"></i> <strong>Tujuan:</strong><br>
                                                         <?= htmlspecialchars($st['tujuan'] ?? '') ?></p>

                                                     <p><i class="fas fa-map-marker-alt"></i> <strong>Tempat:</strong><br>
                                                         <?= htmlspecialchars($place) ?></p>

                                                     <p><i class="fas fa-calendar"></i> <strong>Periode:</strong><br>
                                       <?php
                                          $start_display = '-';
                                          $end_display = '-';
                                          if (!empty($start)) {
                                              $ts = @strtotime($start);
                                              if ($ts !== false) $start_display = date('d/m/Y H:i', $ts);
                                          }
                                          if (!empty($end)) {
                                              $ts2 = @strtotime($end);
                                              if ($ts2 !== false) $end_display = date('d/m/Y H:i', $ts2);
                                          }
                                       ?>
                                       <?= $start_display ?><br>
                                       s/d <?= $end_display ?></p>
                                    </div>
                                    
                                    <?php $user_response = isset($st['user_response']) ? $st['user_response'] : 'pending'; ?>
                                    <div class="mt-3 pt-3 border-top">
                                        <div class="status-row">
                                            <strong>Status Tugas:</strong>
                                            <?= getStatusBadge($ns, $user_response) ?>
                                        </div>
                                    </div>
                                </div>
                                
                                <div class="card-footer bg-light p-3 d-flex gap-2 flex-wrap">
                                    <button class="btn btn-sm btn-info" onclick="viewDetail(<?= $st['id'] ?>)">
                                        <i class="fas fa-eye"></i> Detail
                                    </button>
                                    
                                    <!-- No user response buttons in this deployment -->
                                    
                                    <button class="btn btn-sm btn-secondary" onclick="downloadSurat(<?= $st['id'] ?>)">
                                        <i class="fas fa-download"></i> PDF
                                    </button>
                                </div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php else: ?>
                <div class="text-center py-5">
                    <i class="fas fa-file-signature fa-3x text-muted mb-3"></i>
                    <h5>Belum ada surat tugas</h5>
                    <p class="text-muted">Anda belum menerima surat tugas apapun</p>
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
                        <button type="button" class="close modal-close" data-dismiss="modal" aria-label="Tutup">
                            <span aria-hidden="true">&times;</span>
                        </button>
                    </div>
            <div class="modal-body" id="detailContent">
                <!-- Content will be loaded via AJAX -->
            </div>
        </div>
    </div>
</div>

<!-- Modal Respond Surat Tugas -->
<div class="modal fade" id="responseModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="responseModalTitle">Respon Surat Tugas</h5>
                <button type="button" class="close" data-dismiss="modal">
                    <span>&times;</span>
                </button>
            </div>
            <form id="responseForm" method="POST">
                <div class="modal-body">
                    <input type="hidden" name="action" value="respond_surat_tugas">
                    <input type="hidden" id="response_surat_id" name="surat_tugas_id">
                    <input type="hidden" id="response_type" name="response">
                    
                    <div id="responseMessage"></div>
                    
                    <div class="form-group">
                        <label for="response_notes">Catatan (Opsional)</label>
                        <textarea class="form-control" id="response_notes" name="response_notes" rows="3" 
                                placeholder="Berikan catatan terkait keputusan Anda..."></textarea>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-dismiss="modal">Batal</button>
                    <button type="submit" class="btn" id="responseSubmitBtn">Konfirmasi</button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
function viewDetail(id) {
    try {
    // Avoid hiding an element that currently has focus (prevents aria-hidden warnings)
    try { if (document.activeElement && document.activeElement !== document.body) { document.activeElement.blur(); } } catch(e) {}

    // Ensure the modal element is a direct child of <body> so Bootstrap places the backdrop correctly
    // and remove any existing/backdrop remnants before showing.
    $('#detailModal').appendTo('body');
    // Hide any currently visible modals (targeted) and remove orphan backdrops
    $('.modal.show').each(function() { $(this).modal('hide'); });
        $('.modal-backdrop').remove();

        document.getElementById('detailContent').innerHTML = '<div class="text-center p-4">\n  <div class="spinner-border text-primary" role="status"><span class="sr-only">Loading...</span></div>\n  <div class="mt-2">Memuat detail...</div>\n</div>';
        // Show modal
        $('#detailModal').modal('show');
        // After showing, ensure a sensible element inside modal receives focus (close button)
        setTimeout(function() {
            try {
                var btn = document.querySelector('#detailModal .modal-close, #detailModal .close, #detailModal button');
                if (btn) btn.focus();
            } catch (e) {}
        }, 60);

        const controller = new AbortController();
        const signal = controller.signal;
        const timeoutId = setTimeout(() => { controller.abort(); }, 10000);

        fetch('ajax/get_surat_tugas_detail.php?id=' + encodeURIComponent(id), { signal })
            .then(response => {
                clearTimeout(timeoutId);
                if (!response.ok) throw new Error('Server error: ' + response.status);
                const ct = response.headers.get('content-type') || '';
                if (ct.indexOf('application/json') === -1) {
                    return response.text().then(t => { 
                        throw new Error('Unexpected response: ' + t.substring(0, 100)); 
                    });
                }
                return response.json();
            })
            .then(data => {
                if (!data.success) throw new Error(data.message || 'Gagal memuat detail');
                document.getElementById('detailContent').innerHTML = data.html;
                if (!$('#detailModal').hasClass('show')) $('#detailModal').modal('show');
            })
            .catch(error => {
                console.error('Error:', error);
                clearTimeout(timeoutId);

                let msg = 'Gagal memuat detail';
                if (error && error.name === 'AbortError') {
                    msg = 'Permintaan dibatalkan karena memakan waktu terlalu lama.';
                } else if (error && error.message) {
                    msg = error.message;
                }

                // Render the error inside the modal so user can close it
                const container = document.getElementById('detailContent');
                if (container) {
                    container.innerHTML = '<div class="alert alert-danger">' + safeHtml(msg) + '</div>';
                }
                if (!$('#detailModal').hasClass('show')) $('#detailModal').modal('show');

                // Defensive cleanup: remove any orphan backdrops and restore body state
                setTimeout(function() {
                    $('.modal-backdrop').remove();
                    $('body').removeClass('modal-open');
                }, 50);
            });
    } catch (err) {
        console.error('Unexpected error in viewDetail:', err);
        alert('Terjadi kesalahan tak terduga. Silakan coba lagi.');
        $('.modal').modal('hide'); 
        $('.modal-backdrop').remove();
        $('body').removeClass('modal-open');
    }
}

// Helper to escape text for insertion into innerHTML
function safeHtml(s) {
    if (!s) return '';
    return String(s).replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;').replace(/"/g, '&quot;');
}

function respondSurat(id, response) {
    document.getElementById('response_surat_id').value = id;
    document.getElementById('response_type').value = response;
    
    const title = response === 'accepted' ? 'Terima Surat Tugas' : 'Tolak Surat Tugas';
    const message = response === 'accepted' ? 
        '<div class="alert alert-success">Anda akan menerima surat tugas ini. Tugas akan menjadi aktif.</div>' :
        '<div class="alert alert-warning">Anda akan menolak surat tugas ini. Penugasan akan dibatalkan.</div>';
    const btnClass = response === 'accepted' ? 'btn-success' : 'btn-danger';
    const btnText = response === 'accepted' ? 'Terima Tugas' : 'Tolak Tugas';
    
    document.getElementById('responseModalTitle').textContent = title;
    document.getElementById('responseMessage').innerHTML = message;
    document.getElementById('responseSubmitBtn').className = 'btn ' + btnClass;
    document.getElementById('responseSubmitBtn').textContent = btnText;
    
    $('#responseModal').modal('show');
}

function downloadSurat(id) {
    window.open('ajax/generate_surat_tugas_pdf.php?id=' + encodeURIComponent(id), '_blank');
}

</script>

<script>
// Ensure modal focus management is applied consistently
$(document).ready(function(){
    try {
        $('#detailModal').on('shown.bs.modal', function() {
            try {
                var btn = this.querySelector('.modal-close, .close, button');
                if (btn) btn.focus();
            } catch (e) {}
        });
    } catch (e) {
        // ignore if bootstrap events not available
    }
});
</script>
