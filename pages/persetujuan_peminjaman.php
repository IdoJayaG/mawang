<?php
require_once 'includes/auth.php';
require_admin(); // Only admins can approve peminjaman

$current_user_id = get_current_user_id();
$action = $_GET['action'] ?? 'list';
$peminjaman_id = $_GET['id'] ?? null;
$msg = '';

// Helper: check table/columns existence and safe notification/vehicle status helpers
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
    // Prefer advanced notifications table when available
    if (table_exists($mysqli, 'notifikasi_advanced')) {
        $table = 'notifikasi_advanced';
    } elseif (table_exists($mysqli, 'notifikasi')) {
        $table = 'notifikasi';
    } else {
        return false; // no notifications table present
    }

    $cols = get_table_columns($mysqli, $table);
    $msg_esc = $mysqli->real_escape_string($message);
    $title_esc = $title !== null ? $mysqli->real_escape_string($title) : null;

    // common variants
    if (in_array('message', $cols) && in_array('title', $cols)) {
        $title_sql = $title_esc !== null ? "'{$title_esc}'" : "''";
        return $mysqli->query("INSERT INTO {$table} (user_id, message, title, created_at) VALUES ({$user_id}, '{$msg_esc}', {$title_sql}, NOW())");
    }

    if (in_array('pesan', $cols)) {
        return $mysqli->query("INSERT INTO {$table} (user_id, pesan) VALUES ({$user_id}, '{$msg_esc}')");
    }

    // fallback: try inserting into message column if exists
    if (in_array('message', $cols)) {
        return $mysqli->query("INSERT INTO {$table} (user_id, message) VALUES ({$user_id}, '{$msg_esc}')");
    }

    return false;
}

function vehicle_status_column($mysqli) {
    $cols = get_table_columns($mysqli, 'kendaraan');
    foreach (['status_peminjaman', 'status_kendaraan', 'status'] as $c) {
        if (in_array($c, $cols)) return $c;
    }
    return null;
}

// detect approver column and whether approved_at exists (needed by approval flow)
$peminjaman_cols = [];
if (isset($mysqli)) {
    // Get columns from peminjaman_kendaraan table
    $peminjaman_cols = get_table_columns($mysqli, 'peminjaman_kendaraan');
}
$approver_col = null;
foreach (['approved_by','approval_by','approver_id','approved_by_id','approver'] as $c) {
    if (in_array($c, $peminjaman_cols)) { $approver_col = $c; break; }
}
$has_approved_at = in_array('approved_at', $peminjaman_cols);

// Handle approval/rejection
if ($_POST && in_array($action, ['approve', 'reject'])) {
    if (!validate_csrf_token($_POST['csrf_token'] ?? '')) {
        $msg = '<div class="alert alert-danger">Token keamanan tidak valid!</div>';
    } else {
        $peminjaman_id = (int)$_POST['peminjaman_id'];
        $notes = trim($_POST['notes'] ?? '');
        
        if ($action === 'approve') {
            // Check for conflicts again
            $check_stmt = $mysqli->prepare("
                SELECT p.*, k.no_reg, k.no_polisi, k.merk, k.tipe FROM peminjaman_kendaraan p
                LEFT JOIN kendaraan k ON p.kendaraan_id = k.id
                WHERE p.id = ? AND p.status = 'Pending'
            ");
            $check_stmt->bind_param('i', $peminjaman_id);
            $check_stmt->execute();
            $peminjaman = $check_stmt->get_result()->fetch_assoc();
            $check_stmt->close();
            
            if (!$peminjaman) {
                $msg = '<div class="alert alert-danger">Pengajuan tidak ditemukan atau status sudah berubah!</div>';
            } else {
                // Check for schedule conflicts
                $conflict_stmt = $mysqli->prepare("
                    SELECT COUNT(*) as count FROM peminjaman_kendaraan 
                    WHERE kendaraan_id = ? 
                    AND status IN ('Approved', 'Ongoing') 
                    AND id != ?
                    AND ((tanggal_mulai <= ? AND tanggal_selesai >= ?) 
                         OR (tanggal_mulai <= ? AND tanggal_selesai >= ?))
                ");
                $conflict_stmt->bind_param('iissss', 
                    $peminjaman['kendaraan_id'], $peminjaman_id,
                    $peminjaman['tanggal_mulai'], $peminjaman['tanggal_mulai'], 
                    $peminjaman['tanggal_selesai'], $peminjaman['tanggal_selesai']
                );
                $conflict_stmt->execute();
                $conflict = $conflict_stmt->get_result()->fetch_assoc()['count'];
                $conflict_stmt->close();
                
                if ($conflict > 0) {
                    $msg = '<div class="alert alert-danger">Tidak dapat menyetujui: Ada konflik jadwal dengan peminjaman lain!</div>';
                } else {
                    // Approve the request
                    // Detect available columns and only include notes-like column if present
                    $cols_now = $mysqli->query("SHOW COLUMNS FROM peminjaman_kendaraan");
                    $fields_now = $cols_now ? array_column($cols_now->fetch_all(MYSQLI_ASSOC), 'Field') : [];
                    $notes_col = null;
                    foreach (['notes','note','catatan_approval','catatan_pengembalian','catatan'] as $c) {
                        if (in_array($c, $fields_now)) { $notes_col = $c; break; }
                    }

                    $update_cols = "status = 'Approved'";
                    if ($approver_col) {
                        $update_cols .= ", {$approver_col} = ?";
                    }
                    if ($has_approved_at) {
                        $update_cols .= ", approved_at = NOW()";
                    }
                    // include notes column placeholder only when present
                    if ($notes_col) {
                        $update_cols .= ", {$notes_col} = ?";
                    }

                    // determine if updated_by column exists in this schema
                    $updated_by_exists = in_array('updated_by', $fields_now);
                    if ($updated_by_exists) {
                        $update_cols .= ", updated_by = ?, updated_at = NOW()";
                    } else {
                        $update_cols .= ", updated_at = NOW()";
                    }

                    $sql_up = "UPDATE peminjaman_kendaraan SET " . $update_cols . " WHERE id = ?";
                    $stmt = $mysqli->prepare($sql_up);

                    // Build params/types in the exact placeholder order: approver, notes, updated_by(if present), id
                    $bind_params = [];
                    $bind_types = '';
                    if ($approver_col) {
                        $bind_params[] = $current_user_id;
                        $bind_types .= 'i';
                    }
                    if ($notes_col) {
                        $bind_params[] = $notes;
                        $bind_types .= 's';
                    }
                    if ($updated_by_exists) {
                        $bind_params[] = $current_user_id;
                        $bind_types .= 'i';
                    }
                    // id param
                    $bind_params[] = $peminjaman_id;
                    $bind_types .= 'i';

                    if ($bind_params) {
                        $stmt->bind_param($bind_types, ...$bind_params);
                    }
                    
                    if ($stmt->execute()) {
                        // Update vehicle status using available column
                        $vcol = vehicle_status_column($mysqli);
                        if ($vcol) {
                            $vid = (int)$peminjaman['kendaraan_id'];
                            $val = $mysqli->real_escape_string('Dipinjam');
                            $mysqli->query("UPDATE kendaraan SET `{$vcol}` = '{$val}' WHERE id = {$vid}");
                        }
                        
                        // Create notification for user
                        // Create notification for user (defensive)
                        $user_id = (int)($peminjaman['peminjam_id'] ?? 0);
                        if ($user_id) {
                            $label_kendaraan = $peminjaman['no_reg'] ?: ($peminjaman['no_polisi'] ?? '');
                            $message = "Pengajuan peminjaman kendaraan {$label_kendaraan} telah disetujui";
                            insert_notification($mysqli, $user_id, $message, 'Peminjaman Disetujui');
                        }
                        
                        $msg = '<div class="alert alert-success">Pengajuan peminjaman berhasil disetujui!</div>';
                        log_user_activity("Menyetujui peminjaman kendaraan ID: {$peminjaman['kendaraan_id']} untuk user ID: {$peminjaman['peminjam_id']}");
                    } else {
                        $msg = '<div class="alert alert-danger">Error: ' . $stmt->error . '</div>';
                    }
                    $stmt->close();
                }
            }
            
        } elseif ($action === 'reject') {
            // Guard against missing key and null to avoid deprecation
            $rejected_reason = trim((string)($_POST['rejected_reason'] ?? ''));
            
            if (empty($rejected_reason)) {
                $msg = '<div class="alert alert-danger">Alasan penolakan harus diisi!</div>';
            } else {
                    // Build reject update dynamically to avoid unknown column errors
                    $cols_now = $mysqli->query("SHOW COLUMNS FROM peminjaman_kendaraan");
                    $fields_now = $cols_now ? array_column($cols_now->fetch_all(MYSQLI_ASSOC), 'Field') : [];
                    $notes_col = null;
                    foreach (['notes','note','catatan_approval','catatan_pengembalian','catatan'] as $c) {
                        if (in_array($c, $fields_now)) { $notes_col = $c; break; }
                    }

                    $updated_by_exists = in_array('updated_by', $fields_now);

                    $update_parts = ["status = 'Rejected'"];
                    $bind_types = '';
                    $bind_params = [];

                    // Include rejected_reason only if column exists; otherwise fold into notes
                    $rejected_reason_col_exists = in_array('rejected_reason', $fields_now);
                    if ($rejected_reason_col_exists) {
                        $update_parts[] = "rejected_reason = ?";
                        $bind_types .= 's';
                        $bind_params[] = $rejected_reason;
                    } else if ($notes_col) {
                        // Append reason into notes so it's not lost
                        $notes = trim($notes);
                        $notes = $notes !== '' ? ($notes . ' | Alasan: ' . $rejected_reason) : ('Alasan: ' . $rejected_reason);
                    }

                    if ($notes_col) {
                        $update_parts[] = "{$notes_col} = ?";
                        $bind_types .= 's';
                        $bind_params[] = $notes;
                    }

                    if ($updated_by_exists) {
                        $update_parts[] = "updated_by = ?";
                        $bind_types .= 'i';
                        $bind_params[] = $current_user_id;
                    }

                    $update_parts[] = "updated_at = NOW()";

                    $sql = "UPDATE peminjaman_kendaraan SET " . implode(', ', $update_parts) . " WHERE id = ? AND status = 'Pending'";
                    $stmt = $mysqli->prepare($sql);

                    // id param
                    $bind_types .= 'i';
                    $bind_params[] = $peminjaman_id;
                    if ($bind_params) $stmt->bind_param($bind_types, ...$bind_params);
                
                if ($stmt->execute() && $stmt->affected_rows > 0) {
                    // Get peminjaman info for notification (robust borrower column)
                    $p_cols = get_table_columns($mysqli, 'peminjaman_kendaraan');
                    $borrower_col = in_array('peminjam_id', $p_cols) ? 'peminjam_id' : (in_array('pemohon_id', $p_cols) ? 'pemohon_id' : null);
                    $peminjaman_info = ['borrower_id' => 0, 'no_polisi' => ''];
                    $peminjaman_info = ['borrower_id' => 0, 'no_reg' => '', 'no_polisi' => ''];
                    if ($borrower_col) {
                        $q = $mysqli->prepare("SELECT p.`{$borrower_col}` AS borrower_id, k.no_reg, k.no_polisi FROM peminjaman_kendaraan p LEFT JOIN kendaraan k ON p.kendaraan_id = k.id WHERE p.id = ?");
                        if ($q) {
                            $q->bind_param('i', $peminjaman_id);
                            $q->execute();
                            $peminjaman_info = $q->get_result()->fetch_assoc() ?: $peminjaman_info;
                            $q->close();
                        }
                    }

                    // Create notification for user (defensive)
                    $user_id = (int)($peminjaman_info['borrower_id'] ?? 0);
                    if ($user_id) {
                        $label_kendaraan = $peminjaman_info['no_reg'] ?: ($peminjaman_info['no_polisi'] ?? '');
                        $msg_text = "Pengajuan peminjaman kendaraan {$label_kendaraan} ditolak: {$rejected_reason}";
                        insert_notification($mysqli, $user_id, $msg_text, 'Peminjaman Ditolak');
                    }

                    $msg = '<div class="alert alert-success">Pengajuan peminjaman berhasil ditolak!</div>';
                    log_user_activity("Menolak peminjaman kendaraan ID: $peminjaman_id dengan alasan: $rejected_reason");
                } else {
                    $msg = '<div class="alert alert-danger">Gagal menolak pengajuan. Mungkin status sudah berubah.</div>';
                }
                $stmt->close();
            }
        }
        
    // notification: this was previously duplicated and could reference undefined vars; keep none here
        $action = 'list';
    }
}

// Get peminjaman list
$keyword = trim($_GET['q'] ?? '');
$status_filter = $_GET['status'] ?? 'Pending';
$limit = 20;
$page = max(1, (int)($_GET['p'] ?? 1));
$offset = ($page - 1) * $limit;

$where_conditions = [];
$params = [];
$param_types = '';

if ($keyword) {
    $where_conditions[] = "(k.no_reg LIKE ? OR k.no_polisi LIKE ? OR p.keperluan LIKE ? OR p.tujuan LIKE ? OR pemohon.nama_lengkap LIKE ?)";
    $search_term = "%$keyword%";
    $params = array_merge($params, [$search_term, $search_term, $search_term, $search_term]);
    $param_types .= 'ssss';
}

if ($status_filter) {
    $where_conditions[] = "p.status = ?";
    $params[] = $status_filter;
    $param_types .= 's';
}

$where_sql = $where_conditions ? 'WHERE ' . implode(' AND ', $where_conditions) : '';


// detect approver column and build optional select/join
$cols_info = $mysqli->query("SHOW COLUMNS FROM peminjaman_kendaraan")->fetch_all(MYSQLI_ASSOC);
$cols_names = array_column($cols_info, 'Field');
$approver_col = null;
foreach (['approved_by','approval_by','approver_id','approved_by_id','approver'] as $c) {
    if (in_array($c, $cols_names)) { $approver_col = $c; break; }
}
$select_extra = '';
$join_approver = '';
if ($approver_col) {
    $select_extra = ", approver.nama_lengkap as approved_by_name";
    $join_approver = " LEFT JOIN pengguna approver ON p.{$approver_col} = approver.id";
}

$sql = "
    SELECT p.*, k.no_polisi, k.merk, k.tipe, k.jenis,
           pemohon.nama_lengkap as pemohon_name, pemohon.nrp_nip as pemohon_nip" . $select_extra . "
    FROM peminjaman_kendaraan p
    LEFT JOIN kendaraan k ON p.kendaraan_id = k.id
    LEFT JOIN pengguna pemohon ON p.peminjam_id = pemohon.id" . $join_approver . "
    $where_sql
    ORDER BY 
        CASE 
            WHEN p.status = 'Pending' THEN 1
            WHEN p.status = 'Approved' THEN 2
            WHEN p.status = 'Ongoing' THEN 3
            ELSE 4
        END,
        p.created_at DESC
    LIMIT ? OFFSET ?
";

$stmt = $mysqli->prepare($sql);
if ($params) {
    $param_types .= 'ii';
    $params[] = $limit;
    $params[] = $offset;
    $stmt->bind_param($param_types, ...$params);
} else {
    $stmt->bind_param('ii', $limit, $offset);
}

$stmt->execute();
$peminjaman_list = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$stmt->close();

// Get total count for pagination
$count_sql = "
    SELECT COUNT(*) as total
    FROM peminjaman_kendaraan p
    LEFT JOIN kendaraan k ON p.kendaraan_id = k.id
    LEFT JOIN pengguna pemohon ON p.peminjam_id = pemohon.id
    $where_sql
";

$count_stmt = $mysqli->prepare($count_sql);
if ($params) {
    // Remove limit and offset from params for count
    $count_params = array_slice($params, 0, -2);
    $count_param_types = substr($param_types, 0, -2);
    if ($count_params) {
        $count_stmt->bind_param($count_param_types, ...$count_params);
    }
}
$count_stmt->execute();
$total_records = $count_stmt->get_result()->fetch_assoc()['total'];
$count_stmt->close();

$total_pages = ceil($total_records / $limit);

// Get detail data for approval/rejection
$detail_data = null;
if (in_array($action, ['approve', 'reject']) && $peminjaman_id) {
    $stmt = $mysqli->prepare("
    SELECT p.*, k.no_reg, k.no_polisi, k.merk, k.tipe, k.jenis, k.warna,
               pemohon.nama_lengkap as pemohon_name, pemohon.nrp_nip as pemohon_nip, pemohon.no_hp as pemohon_hp,
               ua.username as pemohon_username
    FROM peminjaman_kendaraan p
    LEFT JOIN kendaraan k ON p.kendaraan_id = k.id
    LEFT JOIN pengguna pemohon ON p.peminjam_id = pemohon.id
        LEFT JOIN user_account ua ON pemohon.id = ua.pengguna_id
        WHERE p.id = ? AND p.status = 'Pending'
    ");
    $stmt->bind_param('i', $peminjaman_id);
    $stmt->execute();
    $detail_data = $stmt->get_result()->fetch_assoc();
    $stmt->close();
}

// Status badge mapping
function getStatusBadge($status) {
    $badges = [
        'Pending' => 'badge-warning',
        'Approved' => 'badge-success', 
        'Rejected' => 'badge-danger',
        'Ongoing' => 'badge-info',
        'Completed' => 'badge-primary',
        'Cancelled' => 'badge-secondary'
    ];
    
    $labels = [
        'Pending' => 'Menunggu',
        'Approved' => 'Disetujui',
        'Rejected' => 'Ditolak', 
        'Ongoing' => 'Berlangsung',
        'Completed' => 'Selesai',
        'Cancelled' => 'Dibatalkan'
    ];
    
    $badge_class = $badges[$status] ?? 'badge-secondary';
    $label = $labels[$status] ?? $status;
    
    return "<span class=\"badge $badge_class\">$label</span>";
}
?>

<div class="page-header">
    <h1><i class="fas fa-check-circle"></i> Persetujuan Peminjaman Kendaraan</h1>
</div>

<?= $msg ?>

<?php if (in_array($action, ['approve', 'reject']) && $detail_data): ?>
    <div class="card">
        <div class="card-header">
            <h3>
                <i class="fas fa-<?= $action === 'approve' ? 'check' : 'times' ?>"></i> 
                <?= $action === 'approve' ? 'Setujui' : 'Tolak' ?> Pengajuan Peminjaman
            </h3>
        </div>
        <div class="card-body">
            <!-- Detail Pengajuan -->
            <div class="detail-section">
                <h4>Detail Pengajuan</h4>
                <div class="detail-grid">
                    <div class="detail-group">
                        <label>Pemohon</label>
                        <div class="detail-value">
                            <strong><?= htmlspecialchars($detail_data['pemohon_name']) ?></strong><br>
                            <small class="text-muted">
                                NIP: <?= htmlspecialchars($detail_data['pemohon_nip']) ?><br>
                                Username: <?= htmlspecialchars($detail_data['pemohon_username']) ?>
                            </small>
                        </div>
                    </div>
                    
                    <div class="detail-group">
                        <label>Kendaraan</label>
                        <div class="detail-value">
                            <strong><?= htmlspecialchars($detail_data['no_reg'] ?: ($detail_data['no_polisi'] ?? '')) ?></strong>
                            <?php if (!empty($detail_data['no_reg'])): ?>
                                <br><small class="text-muted">Reg: <?= htmlspecialchars($detail_data['no_reg']) ?></small>
                            <?php endif; ?>
                            <br>
                            <?= htmlspecialchars($detail_data['merk']) ?> <?= htmlspecialchars($detail_data['tipe']) ?><br>
                            <small class="text-muted"><?= htmlspecialchars($detail_data['jenis']) ?> - <?= htmlspecialchars($detail_data['warna']) ?></small>
                        </div>
                    </div>
                    
                    <div class="detail-group">
                        <label>Keperluan & Tujuan</label>
                        <div class="detail-value">
                            <strong><?= htmlspecialchars($detail_data['keperluan']) ?></strong><br>
                            <small class="text-muted">Tujuan: <?= htmlspecialchars($detail_data['tujuan']) ?></small>
                        </div>
                    </div>

                    <div class="detail-group">
                        <label>Periode Peminjaman</label>
                        <div class="detail-value">
                            <strong>Mulai:</strong> <?= date('d/m/Y H:i', strtotime($detail_data['tanggal_mulai'])) ?><br>
                            <strong>Selesai:</strong> <?= date('d/m/Y H:i', strtotime($detail_data['tanggal_selesai'])) ?><br>
                        </div>
                    <div class="detail-group">
                        <label>Detail Tambahan</label>
                        <div class="detail-value">
                            <?php
                                $sopir_sendiri = !empty($detail_data['sopir_sendiri']);
                                $nama_sopir = $detail_data['nama_sopir'] ?? '';
                                // prefer peminjaman-level emergency contact, fall back to pemohon phone if available
                                $kontak_darurat = $detail_data['kontak_darurat'] ?? ($detail_data['pemohon_hp'] ?? '');
                                $estimasi_km = $detail_data['estimasi_km'] ?? null;
                                $estimasi_bbm = $detail_data['estimasi_bbm'] ?? null;

                                // If estimasi values are missing on peminjaman, try latest surat_tugas for same kendaraan
                                if ((is_null($estimasi_km) || $estimasi_km === '') && isset($detail_data['kendaraan_id']) && table_exists($mysqli, 'surat_tugas')) {
                                    $sq = $mysqli->prepare("SELECT estimasi_km, estimasi_bbm FROM surat_tugas WHERE kendaraan_id = ? AND estimasi_km IS NOT NULL LIMIT 1");
                                    if ($sq) {
                                        $sq->bind_param('i', $detail_data['kendaraan_id']);
                                        $sq->execute();
                                        $row = $sq->get_result()->fetch_assoc();
                                        if ($row) {
                                            if (isset($row['estimasi_km']) && $row['estimasi_km'] !== '') $estimasi_km = $row['estimasi_km'];
                                            if (isset($row['estimasi_bbm']) && $row['estimasi_bbm'] !== '') $estimasi_bbm = $row['estimasi_bbm'];
                                        }
                                        $sq->close();
                                    }
                                }
                            ?>
                            <strong>Sopir:</strong> <?= $sopir_sendiri ? 'Sopir Sendiri' : ($nama_sopir !== '' ? htmlspecialchars($nama_sopir) : '-') ?><br>
                            <strong>Kontak Darurat:</strong> <?= $kontak_darurat !== '' ? htmlspecialchars($kontak_darurat) : '-' ?><br>
                            <strong>Estimasi KM:</strong> <?= is_numeric($estimasi_km) ? number_format($estimasi_km) . ' km' : '-' ?><br>
                            <strong>Estimasi BBM:</strong> <?= is_numeric($estimasi_bbm) ? 'Rp ' . number_format($estimasi_bbm) : '-' ?>
                        </div>
                    </div>
                    
                    <div class="detail-group">
                        <label>Tanggal Pengajuan</label>
                        <div class="detail-value">
                            <?= date('d/m/Y H:i', strtotime($detail_data['created_at'])) ?>
                        </div>
                    </div>
                </div>
            </div>
            
            <!-- Form Approval/Rejection -->
            <div class="approval-section">
                <h4><?= $action === 'approve' ? 'Persetujuan' : 'Penolakan' ?></h4>
                <form method="post">
                    <input type="hidden" name="csrf_token" value="<?= generate_csrf_token() ?>">
                    <input type="hidden" name="peminjaman_id" value="<?= $detail_data['id'] ?>">
                    
                    <div class="form-group">
                        <label for="notes">Catatan (Opsional)</label>
                        <textarea id="notes" name="notes" class="form-control" rows="2" 
                                  placeholder="Catatan tambahan..."></textarea>
                    </div>
                    <?php if ($action === 'reject'): ?>
                    <div class="form-group">
                        <label for="rejected_reason">Alasan Penolakan<span class="text-danger">*</span></label>
                        <textarea id="rejected_reason" name="rejected_reason" class="form-control" rows="2" required placeholder="Berikan alasan penolakan"></textarea>
                    </div>
                    <?php endif; ?>
                    
                    <div class="form-actions">
                        <button type="submit" class="btn btn-<?= $action === 'approve' ? 'success' : 'danger' ?>">
                            <i class="fas fa-<?= $action === 'approve' ? 'check' : 'times' ?>"></i> 
                            <?= $action === 'approve' ? 'Setujui' : 'Tolak' ?> Pengajuan
                        </button>
                        <a href="index.php?page=persetujuan_peminjaman" class="btn btn-secondary">
                            <i class="fas fa-arrow-left"></i> Kembali
                        </a>
                    </div>
                </form>
            </div>
        </div>
    </div>

<?php else: ?>
    <!-- Search and Filter -->
    <div class="actions-bar">
        <div class="search-box">
            <form method="get" class="search-form">
                <input type="hidden" name="page" value="persetujuan_peminjaman">
                <div class="input-group">
                    <input type="text" name="q" placeholder="Cari nomor polisi, pemohon, keperluan..." 
                    <input type="text" name="q" placeholder="Cari No. Reg, pemohon, keperluan..." 
                           value="<?= htmlspecialchars($keyword) ?>" class="form-control">
                    <select name="status" class="form-control">
                        <option value="Pending" <?= $status_filter === 'Pending' ? 'selected' : '' ?>>Menunggu Persetujuan</option>
                        <option value="Approved" <?= $status_filter === 'Approved' ? 'selected' : '' ?>>Disetujui</option>
                        <option value="Rejected" <?= $status_filter === 'Rejected' ? 'selected' : '' ?>>Ditolak</option>
                        <option value="Ongoing" <?= $status_filter === 'Ongoing' ? 'selected' : '' ?>>Berlangsung</option>
                        <option value="Completed" <?= $status_filter === 'Completed' ? 'selected' : '' ?>>Selesai</option>
                        <option value="" <?= $status_filter === '' ? 'selected' : '' ?>>Semua Status</option>
                    </select>
                    <button type="submit" class="btn btn-primary">
                        <i class="fas fa-search"></i>
                    </button>
                </div>
            </form>
            <?php if ($keyword !== ''): ?>
                <a href="index.php?page=persetujuan_peminjaman<?= $status_filter ? '&status=' . urlencode($status_filter) : '' ?>" class="btn btn-outline">
                    <i class="fas fa-times"></i> Reset
                </a>
            <?php endif; ?>
        </div>
    </div>

    <!-- Statistics Cards -->
    <?php
    $stats = $mysqli->query("
        SELECT 
            COUNT(*) as total,
            SUM(CASE WHEN status = 'Pending' THEN 1 ELSE 0 END) as pending,
            SUM(CASE WHEN status = 'Approved' THEN 1 ELSE 0 END) as approved,
            SUM(CASE WHEN status = 'Rejected' THEN 1 ELSE 0 END) as rejected,
            SUM(CASE WHEN status = 'Ongoing' THEN 1 ELSE 0 END) as ongoing
        FROM peminjaman_kendaraan
    ")->fetch_assoc();
    ?>
    
    <div class="stats-cards">
        <div class="stat-card pending">
            <div class="stat-icon"><i class="fas fa-clock"></i></div>
            <div class="stat-info">
                <h3><?= $stats['pending'] ?></h3>
                <p>Menunggu Persetujuan</p>
            </div>
        </div>
        <div class="stat-card approved">
            <div class="stat-icon"><i class="fas fa-check"></i></div>
            <div class="stat-info">
                <h3><?= $stats['approved'] ?></h3>
                <p>Disetujui</p>
            </div>
        </div>
        <div class="stat-card ongoing">
            <div class="stat-icon"><i class="fas fa-car"></i></div>
            <div class="stat-info">
                <h3><?= $stats['ongoing'] ?></h3>
                <p>Berlangsung</p>
            </div>
        </div>
        <div class="stat-card total">
            <div class="stat-icon"><i class="fas fa-list"></i></div>
            <div class="stat-info">
                <h3><?= $stats['total'] ?></h3>
                <p>Total Pengajuan</p>
            </div>
        </div>
    </div>

    <!-- Data Table -->
    <div class="card">
        <div class="card-body">
            <div class="table-responsive">
                <table class="table">
                    <thead>
                        <tr>
                            <th>No</th>
                            <th>Tanggal Ajuan</th>
                            <th>Pemohon</th>
                            <th>Kendaraan</th>
                            <th>Keperluan</th>
                            <th>Periode</th>
                            <th>Status</th>
                            <th>Aksi</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (count($peminjaman_list) > 0): ?>
                            <?php $no = ($page - 1) * $limit + 1; foreach ($peminjaman_list as $peminjaman): ?>
                                <tr class="<?= $peminjaman['status'] === 'Pending' ? 'table-warning' : '' ?>">
                                    <td><?= $no++ ?></td>
                                    <td><?= date('d/m/Y H:i', strtotime($peminjaman['created_at'])) ?></td>
                                    <td>
                                        <strong><?= htmlspecialchars($peminjaman['pemohon_name']) ?></strong>
                                        <br><small class="text-muted"><?= htmlspecialchars($peminjaman['pemohon_nip']) ?></small>
                                    </td>
                                    <td>
                                        <div class="vehicle-info">
                                            <strong><?= htmlspecialchars($peminjaman['no_reg'] ?: ($peminjaman['no_polisi'] ?? '')) ?></strong>
                                            <?php if (!empty($peminjaman['no_reg'])): ?>
                                                <br><small class="text-muted">Reg: <?= htmlspecialchars($peminjaman['no_reg']) ?></small>
                                            <?php endif; ?>
                                            <br><small class="text-muted"><?= htmlspecialchars($peminjaman['merk']) ?> <?= htmlspecialchars($peminjaman['tipe']) ?></small>
                                        </div>
                                    </td>
                                    <td>
                                        <div class="text-truncate text-truncate-custom" title="<?= htmlspecialchars($peminjaman['keperluan']) ?>">
                                            <?= htmlspecialchars($peminjaman['keperluan']) ?>
                                        </div>
                                        <small class="text-muted"><?= htmlspecialchars($peminjaman['tujuan']) ?></small>
                                    </td>
                                    <td>
                                        <small>
                                            <?= date('d/m/y H:i', strtotime($peminjaman['tanggal_mulai'])) ?><br>
                                            s/d<br>
                                            <?= date('d/m/y H:i', strtotime($peminjaman['tanggal_selesai'])) ?>
                                        </small>
                                    </td>
                                    <td><?= getStatusBadge($peminjaman['status']) ?></td>
                                    <td>
                                        <div class="action-buttons">
                                            <?php if ($peminjaman['status'] === 'Pending'): ?>
                                                <a href="index.php?page=persetujuan_peminjaman&action=approve&id=<?= $peminjaman['id'] ?>" 
                                                   class="btn btn-success btn-sm" title="Setujui">
                                                    <i class="fas fa-check"></i>
                                                </a>
                                                <a href="index.php?page=persetujuan_peminjaman&action=reject&id=<?= $peminjaman['id'] ?>" 
                                                   class="btn btn-danger btn-sm" title="Tolak">
                                                    <i class="fas fa-times"></i>
                                                </a>
                                            <?php else: ?>
                                                <span class="text-muted">-</span>
                                            <?php endif; ?>
                                        </div>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <tr>
                                <td colspan="8" class="text-center">
                                    <div class="empty-state">
                                        <i class="fas fa-inbox"></i>
                                        <h4>Tidak Ada Pengajuan</h4>
                                        <p>
                                            <?php if ($keyword || $status_filter): ?>
                                                Tidak ada pengajuan yang cocok dengan pencarian
                                            <?php else: ?>
                                                Belum ada pengajuan peminjaman yang masuk
                                            <?php endif; ?>
                                        </p>
                                    </div>
                                </td>
                            </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
            
            <!-- Pagination -->
            <?php if ($total_pages > 1): ?>
                <div class="pagination-wrapper">
                    <nav aria-label="Pagination">
                        <ul class="pagination">
                            <?php if ($page > 1): ?>
                                <li class="page-item">
                                    <a class="page-link" href="index.php?page=persetujuan_peminjaman&p=<?= $page - 1 ?><?= $keyword ? '&q=' . urlencode($keyword) : '' ?><?= $status_filter ? '&status=' . urlencode($status_filter) : '' ?>">
                                        <i class="fas fa-chevron-left"></i>
                                    </a>
                                </li>
                            <?php endif; ?>
                            
                            <?php for ($i = max(1, $page - 2); $i <= min($total_pages, $page + 2); $i++): ?>
                                <li class="page-item <?= $i == $page ? 'active' : '' ?>">
                                    <a class="page-link" href="index.php?page=persetujuan_peminjaman&p=<?= $i ?><?= $keyword ? '&q=' . urlencode($keyword) : '' ?><?= $status_filter ? '&status=' . urlencode($status_filter) : '' ?>">
                                        <?= $i ?>
                                    </a>
                                </li>
                            <?php endfor; ?>
                            
                            <?php if ($page < $total_pages): ?>
                                <li class="page-item">
                                    <a class="page-link" href="index.php?page=persetujuan_peminjaman&p=<?= $page + 1 ?><?= $keyword ? '&q=' . urlencode($keyword) : '' ?><?= $status_filter ? '&status=' . urlencode($status_filter) : '' ?>">
                                        <i class="fas fa-chevron-right"></i>
                                    </a>
                                </li>
                            <?php endif; ?>
                        </ul>
                    </nav>
                    
                    <div class="pagination-info">
                        Menampilkan <?= min($total_records, $offset + 1) ?> - <?= min($total_records, $offset + count($peminjaman_list)) ?> dari <?= $total_records ?> data
                    </div>
                </div>
            <?php endif; ?>
        </div>
    </div>
<?php endif; ?>

<style>
.stats-cards {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
    gap: 1rem;
    margin-bottom: 2rem;
}

.stat-card {
    background: white;
    border-radius: 8px;
    padding: 1.5rem;
    box-shadow: 0 2px 4px rgba(0,0,0,0.1);
    display: flex;
    align-items: center;
    gap: 1rem;
}

.stat-card.pending { border-left: 4px solid #ffc107; }
.stat-card.approved { border-left: 4px solid #28a745; }
.stat-card.ongoing { border-left: 4px solid #17a2b8; }
.stat-card.total { border-left: 4px solid #007bff; }

.stat-icon {
    font-size: 2rem;
    opacity: 0.7;
}

.stat-card.pending .stat-icon { color: #ffc107; }
.stat-card.approved .stat-icon { color: #28a745; }
.stat-card.ongoing .stat-icon { color: #17a2b8; }
.stat-card.total .stat-icon { color: #007bff; }

.stat-info h3 {
    margin: 0;
    font-size: 2rem;
    font-weight: bold;
}

.stat-info p {
    margin: 0;
    color: #666;
    font-size: 0.9rem;
}

.detail-section, .approval-section {
    margin-bottom: 2rem;
    padding-bottom: 2rem;
    border-bottom: 1px solid #e9ecef;
}

.detail-section:last-child, .approval-section:last-child {
    border-bottom: none;
    margin-bottom: 0;
    padding-bottom: 0;
}

.detail-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
    gap: 1.5rem;
    margin-top: 1rem;
}

.detail-group label {
    font-weight: 600;
    color: #333;
    margin-bottom: 0.5rem;
    display: block;
}

.detail-value {
    background: #f8f9fa;
    padding: 0.75rem;
    border-radius: 4px;
    border: 1px solid #e9ecef;
}

.text-truncate-custom {
    max-width: 150px;
    white-space: nowrap;
    overflow: hidden;
    text-overflow: ellipsis;
}

.vehicle-info strong {
    color: #007bff;
}

.badge {
    padding: 0.5rem 0.75rem;
    border-radius: 12px;
    font-size: 0.85rem;
}

.badge-warning { background-color: #ffc107; color: #212529; }
.badge-success { background-color: #28a745; color: white; }
.badge-danger { background-color: #dc3545; color: white; }
.badge-info { background-color: #17a2b8; color: white; }
.badge-primary { background-color: #007bff; color: white; }
.badge-secondary { background-color: #6c757d; color: white; }

.table-warning {
    background-color: rgba(255, 193, 7, 0.1);
}

.search-form .input-group {
    display: flex;
    gap: 0.5rem;
}

.search-form .input-group .form-control {
    flex: 1;
}

.search-form .input-group select.form-control {
    flex: 0 0 200px;
}

@media (max-width: 768px) {
    .detail-grid {
        grid-template-columns: 1fr;
    }
    
    .search-form .input-group {
        flex-direction: column;
    }
    
    .text-truncate-custom {
        max-width: 120px;
    }
    
    .stats-cards {
        grid-template-columns: repeat(2, 1fr);
    }
}
</style>
