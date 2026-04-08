<?php
require_once '../config.php';
require_once '../config/db.php';

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
        return $mysqli->query("INSERT INTO {$table} (user_id, pesan, created_at) VALUES ({$user_id}, '{$msg_esc}', NOW())");
    }

    if (in_array('message', $cols)) {
        return $mysqli->query("INSERT INTO {$table} (user_id, message, created_at) VALUES ({$user_id}, '{$msg_esc}', NOW())");
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

// Start output buffering and clear any previous output
ob_start();
ob_clean();

// Set JSON header
header('Content-Type: application/json');

try {
    // Check authorization
    if (!is_logged_in()) {
        throw new Exception('User not logged in');
    }
    
    $current_user = get_logged_in_user();
    $current_role = get_current_role();
    
    if (!in_array($current_role, ['admin', 'operator'])) {
        throw new Exception('Unauthorized access');
    }

    // Get POST data
    $input = json_decode(file_get_contents('php://input'), true);
    
    if (!$input) {
        throw new Exception('Invalid input data');
    }
    
    $peminjaman_id = isset($input['id']) ? (int)$input['id'] : 0;
    $new_status = isset($input['status']) ? trim($input['status']) : '';
    
    if (!$peminjaman_id) {
        throw new Exception('ID peminjaman tidak valid');
    }
    
    $valid_statuses = ['pending', 'approved', 'rejected', 'ongoing', 'completed', 'cancelled'];
    if (!in_array($new_status, $valid_statuses)) {
        throw new Exception('Status tidak valid');
    }
    
    // Get current peminjaman data
    $check_query = "SELECT * FROM peminjaman_kendaraan WHERE id = ?";
    $check_stmt = $mysqli->prepare($check_query);
    $check_stmt->bind_param('i', $peminjaman_id);
    $check_stmt->execute();
    $result = $check_stmt->get_result();
    
    if ($result->num_rows === 0) {
        throw new Exception('Data peminjaman tidak ditemukan');
    }
    
    $peminjaman = $result->fetch_assoc();
    
    // Status transition validation
    $current_status = $peminjaman['status'];
    $valid_transitions = [
        'pending' => ['approved', 'rejected'],
        'approved' => ['ongoing', 'cancelled'],
        'ongoing' => ['completed'],
        'rejected' => [],
        'completed' => [],
        'cancelled' => []
    ];
    
    if (!in_array($new_status, $valid_transitions[$current_status])) {
        throw new Exception("Transisi dari status '$current_status' ke '$new_status' tidak diizinkan");
    }
    
    // If approving, perform conflict validation to prevent overlapping approved/ongoing usage
    if ($new_status === 'approved') {
        // Need start/end columns detection
        $start_col = null; $end_col = null;
        foreach (['tanggal_mulai','waktu_mulai','start_time'] as $c) if (array_key_exists($c,$peminjaman)) { $start_col = $c; break; }
        foreach (['tanggal_selesai','waktu_selesai','end_time'] as $c) if (array_key_exists($c,$peminjaman)) { $end_col = $c; break; }
        $start_val = $start_col ? $peminjaman[$start_col] : null;
        $end_val = $end_col ? $peminjaman[$end_col] : null;
        if ($start_val && $end_val) {
            $vid = (int)$peminjaman['kendaraan_id'];
            // check conflicts in peminjaman_kendaraan (other approved/ongoing for same vehicle overlapping)
            $conflict_sql = "SELECT COUNT(*) c FROM peminjaman_kendaraan WHERE id <> ? AND kendaraan_id = ? AND status IN ('approved','Approved','ongoing','Ongoing') AND ((tanggal_mulai <= ? AND tanggal_selesai >= ?) OR (tanggal_mulai <= ? AND tanggal_selesai >= ?) OR (tanggal_mulai >= ? AND tanggal_selesai <= ?))";
            if ($chk = $mysqli->prepare($conflict_sql)) {
                $chk->bind_param('iissssss', $peminjaman_id, $vid, $start_val, $start_val, $end_val, $end_val, $start_val, $end_val);
                $chk->execute();
                $c = $chk->get_result()->fetch_assoc();
                $chk->close();
                if (($c['c'] ?? 0) > 0) {
                    throw new Exception('Konflik jadwal: kendaraan sudah memiliki peminjaman disetujui/berlangsung pada periode tersebut');
                }
            }
            // optional: check surat_tugas overlap (Dalam Perjalanan / Disetujui) if table exists
            $res_st = $mysqli->query("SHOW TABLES LIKE 'surat_tugas'");
            if ($res_st && $res_st->num_rows > 0) {
                $surat_conf = $mysqli->prepare("SELECT COUNT(*) c FROM surat_tugas WHERE kendaraan_id = ? AND status IN ('Disetujui','Dalam Perjalanan') AND ((tanggal_berangkat <= ? AND IFNULL(tanggal_kembali, tanggal_berangkat) >= ?) OR (tanggal_berangkat <= ? AND IFNULL(tanggal_kembali, tanggal_berangkat) >= ?) OR (tanggal_berangkat >= ? AND IFNULL(tanggal_kembali, tanggal_berangkat) <= ?))");
                if ($surat_conf) {
                    $surat_conf->bind_param('issssss', $vid, $start_val, $start_val, $end_val, $end_val, $start_val, $end_val);
                    $surat_conf->execute();
                    $sc = $surat_conf->get_result()->fetch_assoc();
                    $surat_conf->close();
                    if (($sc['c'] ?? 0) > 0) {
                        throw new Exception('Konflik dengan surat tugas aktif untuk kendaraan ini pada periode tersebut');
                    }
                }
            }

            // check maintenance schedule overlap in jadwal_perawatan (not finished/cancelled)
            $res_jp = $mysqli->query("SHOW TABLES LIKE 'jadwal_perawatan'");
            if ($res_jp && $res_jp->num_rows > 0) {
                $sqlJP = "SELECT COUNT(*) c FROM jadwal_perawatan WHERE kendaraan_id = ? AND COALESCE(tanggal_perawatan, jadwal_tanggal) IS NOT NULL AND (status IS NULL OR status NOT IN ('Selesai','Dibatalkan')) AND ((COALESCE(tanggal_perawatan, jadwal_tanggal) <= ? AND IFNULL(tanggal_selesai, COALESCE(tanggal_perawatan, jadwal_tanggal)) >= ?) OR (COALESCE(tanggal_perawatan, jadwal_tanggal) <= ? AND IFNULL(tanggal_selesai, COALESCE(tanggal_perawatan, jadwal_tanggal)) >= ?) OR (COALESCE(tanggal_perawatan, jadwal_tanggal) >= ? AND IFNULL(tanggal_selesai, COALESCE(tanggal_perawatan, jadwal_tanggal)) <= ?))";
                if ($jp = $mysqli->prepare($sqlJP)) {
                    $jp->bind_param('issssss', $vid, $start_val, $start_val, $end_val, $end_val, $start_val, $end_val);
                    $jp->execute();
                    $jc = $jp->get_result()->fetch_assoc();
                    $jp->close();
                    if (($jc['c'] ?? 0) > 0) {
                        throw new Exception('Konflik dengan jadwal perawatan/perbaikan pada periode tersebut');
                    }
                }
            }

            // check maintenance window if kendaraan in Maintenance
            $vidColCheck = $mysqli->query("SHOW COLUMNS FROM kendaraan LIKE 'status_peminjaman'");
            if ($vidColCheck && $vidColCheck->num_rows > 0) {
                $vstatus = $mysqli->query("SELECT status_peminjaman FROM kendaraan WHERE id = $vid");
                if ($vstatus && ($rowVs = $vstatus->fetch_assoc())) {
                    if (in_array($rowVs['status_peminjaman'], ['Maintenance','Rusak'])) {
                        throw new Exception('Kendaraan sedang tidak tersedia (Maintenance/Rusak)');
                    }
                }
            }
        }
    }

    // Update peminjaman status
    $update_query = "UPDATE peminjaman_kendaraan SET status = ?";
    $params = [$new_status];
    $types = 's';
    
    // Add approval info for certain statuses (detect approver/notes/approved_at columns)
    if (in_array($new_status, ['approved', 'rejected'])) {
        $cols = get_table_columns($mysqli, 'peminjaman_kendaraan');

        // detect approver column variant
        $approver_col = null;
        foreach (['approved_by','approval_by','approver_id','approved_by_id','approver'] as $c) {
            if (in_array($c, $cols)) { $approver_col = $c; break; }
        }

        // detect notes-like column
        $notes_col = null;
        foreach (['notes','note','catatan_approval','catatan_pengembalian','catatan'] as $c) {
            if (in_array($c, $cols)) { $notes_col = $c; break; }
        }

        $has_approved_at = in_array('approved_at', $cols);

        // only allow writing the approver id when the current user is an admin
        if ($approver_col && strtolower($current_role) === 'admin') {
            $update_query .= ", {$approver_col} = ?";
            $params[] = $current_user['id'];
            $types .= 'i';
        }

        // include approved_at only if the column exists
        if ($has_approved_at) {
            $update_query .= ", approved_at = NOW()";
        }

        // include notes column if present and bind its value
        $notes = isset($input['notes']) ? trim($input['notes']) : '';
        if ($notes_col) {
            $update_query .= ", {$notes_col} = ?";
            $params[] = $notes;
            $types .= 's';
        }
    }

    // always set updated_by when updating status
    $update_query .= ", updated_by = ?, updated_at = NOW()";
    $params[] = $current_user['id'];
    $types .= 'i';

    $update_query .= " WHERE id = ?";
    $params[] = $peminjaman_id;
    $types .= 'i';
    
    $update_stmt = $mysqli->prepare($update_query);
    $update_stmt->bind_param($types, ...$params);
    
    if (!$update_stmt->execute()) {
        throw new Exception('Gagal mengupdate status: ' . $mysqli->error);
    }
    
    // Create notification for status change
    $status_messages = [
        'approved' => 'Peminjaman kendaraan Anda telah disetujui',
        'rejected' => 'Peminjaman kendaraan Anda ditolak',
        'ongoing' => 'Peminjaman kendaraan Anda sedang berlangsung',
        'completed' => 'Peminjaman kendaraan Anda telah selesai',
        'cancelled' => 'Peminjaman kendaraan Anda dibatalkan'
    ];
    
    if (isset($status_messages[$new_status])) {
        $user_id = (int)($peminjaman['pemohon_id'] ?? 0);
        $message = $status_messages[$new_status];
        insert_notification($mysqli, $user_id, $message, 'Status Peminjaman');
    }

    // if status changed to ongoing/completed, update kendaraan status column defensively
    $vcol = vehicle_status_column($mysqli);
    if ($vcol) {
        $vid = (int)$peminjaman['kendaraan_id'];
        $map = [
            'ongoing' => 'Dipinjam',
            'completed' => 'Tersedia',
            'cancelled' => 'Batal',
            'approved' => 'Dipinjam'
        ];
        if (isset($map[$new_status])) {
            $val = $mysqli->real_escape_string($map[$new_status]);
            $mysqli->query("UPDATE kendaraan SET `{$vcol}` = '{$val}' WHERE id = {$vid}");
        }
    }

    // Note: Legacy pengguna_kendaraan assignment removed; access derives from peminjaman_kendaraan/surat_tugas.
    
    // Log activity
    $activity = "Updated peminjaman status to '$new_status' for peminjaman ID: $peminjaman_id";
    log_user_activity($current_user['id'], 'update_peminjaman_status', $activity);
    
    // Return success response
    echo json_encode([
        'success' => true, 
        'message' => 'Status peminjaman berhasil diupdate',
        'new_status' => $new_status
    ]);

} catch (Exception $e) {
    echo json_encode([
        'success' => false, 
        'message' => $e->getMessage()
    ]);
}

// Flush output buffer
if (ob_get_level()) {
    ob_end_flush();
}
?>
