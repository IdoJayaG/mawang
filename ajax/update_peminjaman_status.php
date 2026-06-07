<?php
// Prevent PHP warnings/notices from breaking JSON responses in AJAX endpoints
@ini_set('display_errors', '0');
@ini_set('display_startup_errors', '0');
error_reporting(0);
// Start output buffering early to capture any accidental output from includes
ob_start();

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

// Generate a nomor_surat using same pattern as surat_tugas page: ST/%03d/<ROMAN_MONTH>/<YEAR>
function generate_nomor_surat($conn) {
    $today = new DateTime();
    $month_idx = (int)$today->format('n');
    $month_map = ['I','II','III','IV','V','VI','VII','VIII','IX','X','XI','XII'];
    $month_roman = $month_map[$month_idx - 1];
    $year = $today->format('Y');

    $pattern = "ST/%/{$month_roman}/{$year}";
    $stmt = $conn->prepare("SELECT nomor_surat FROM surat_tugas WHERE nomor_surat LIKE ? ORDER BY id DESC LIMIT 1");
    if ($stmt) {
        $stmt->bind_param('s', $pattern);
        $stmt->execute();
        $res = $stmt->get_result();
        if ($res && $res->num_rows > 0) {
            $last = $res->fetch_assoc();
            $stmt->close();
            if (preg_match('/ST\/(\d+)\//', $last['nomor_surat'] ?? '', $m)) {
                $next = intval($m[1]) + 1;
            } else {
                $next = 1;
            }
        } else {
            if ($stmt) $stmt->close();
            $next = 1;
        }
    } else {
        // fallback
        $next = time() % 1000;
    }
    return sprintf('ST/%03d/%s/%s', $next, $month_roman, $year);
}

function vehicle_status_column($mysqli) {
    $cols = get_table_columns($mysqli, 'kendaraan');
    foreach (['status_peminjaman', 'status_kendaraan', 'status'] as $c) {
        if (in_array($c, $cols)) return $c;
    }
    return null;
}

// Ensure any buffered output (including from includes) is discarded before JSON
if (ob_get_level()) { ob_clean(); }
// Set JSON header
header('Content-Type: application/json');

try {
    // Check authorization
    if (!is_logged_in()) {
        throw new Exception('User not logged in');
    }
    
    $current_user = get_logged_in_user();
    $current_role = get_current_role();
    
    if (!is_admin_like()) {
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

    $pk_cols = get_table_columns($mysqli, 'peminjaman_kendaraan');
    $has_pimpinan_approval = in_array('approval_pimpinan_status', $pk_cols, true);
    if ($new_status === 'approved' && $has_pimpinan_approval && strtolower($current_role) !== 'pimpinan') {
        throw new Exception('Menunggu persetujuan pimpinan');
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
    $surat_tugas_id = null;
    
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
    $pk_cols = get_table_columns($mysqli, 'peminjaman_kendaraan');
    $update_query = "UPDATE peminjaman_kendaraan SET status = ?";
    $params = [$new_status];
    $types = 's';
    
    // Add approval info for certain statuses (detect approver/notes/approved_at columns)
    if (in_array($new_status, ['approved', 'rejected'])) {
        $cols = $pk_cols;

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

    // set updated_by/updated_at only when columns exist
    $has_updated_by = in_array('updated_by', $pk_cols, true);
    $has_updated_at = in_array('updated_at', $pk_cols, true);
    if ($has_updated_by) {
        $update_query .= ", updated_by = ?";
        $params[] = $current_user['id'];
        $types .= 'i';
    }
    if ($has_updated_at) {
        $update_query .= ", updated_at = NOW()";
    }

    $update_query .= " WHERE id = ?";
    $params[] = $peminjaman_id;
    $types .= 'i';
    
    $update_stmt = $mysqli->prepare($update_query);
    if (!$update_stmt) {
        throw new Exception('Gagal menyiapkan query update: ' . $mysqli->error);
    }
    $update_stmt->bind_param($types, ...$params);
    
    if (!$update_stmt->execute()) {
        throw new Exception('Gagal mengupdate status: ' . $mysqli->error);
    }

    // When a peminjaman is approved, create a corresponding surat_tugas only after pimpinan approval
    if (strtolower($new_status) === 'approved' && table_exists($mysqli, 'surat_tugas')) {
        $stCols = get_table_columns($mysqli, 'surat_tugas');
        $pkCols = get_table_columns($mysqli, 'peminjaman_kendaraan');
        $has_pimpinan_approval = in_array('approval_pimpinan_status', $pkCols, true);
        if (strtolower($current_role) !== 'pimpinan' && $has_pimpinan_approval) {
            // Skip surat_tugas creation until pimpinan final approval
        } else {

        // Determine applicant/pengguna for surat_tugas from peminjaman columns
        $applicant = 0;
        foreach (['pemohon_id','peminjam_id','pengguna_id','user_id','created_by'] as $c) {
            if (array_key_exists($c, $peminjaman) && !empty($peminjaman[$c])) { $applicant = (int)$peminjaman[$c]; break; }
        }

        // pick date fields
        $berangkat = null; $kembali = null;
        foreach (['tanggal_mulai','tanggal_berangkat','start_date'] as $c) { if (array_key_exists($c,$peminjaman) && !empty($peminjaman[$c])) { $berangkat = $peminjaman[$c]; break; } }
        foreach (['tanggal_selesai','tanggal_kembali','end_date'] as $c) { if (array_key_exists($c,$peminjaman) && !empty($peminjaman[$c])) { $kembali = $peminjaman[$c]; break; } }

        $desired = [];
        $desired['nomor_surat'] = ['t'=>'s','v'=>generate_nomor_surat($mysqli)];
        if (in_array('tanggal_surat', $stCols, true)) $desired['tanggal_surat'] = ['t'=>'s','v'=>date('Y-m-d')];
        if (in_array('kendaraan_id', $stCols, true)) $desired['kendaraan_id'] = ['t'=>'i','v'=> (int)($peminjaman['kendaraan_id'] ?? 0)];
        if (in_array('pengguna_id', $stCols, true) && $applicant > 0) $desired['pengguna_id'] = ['t'=>'i','v'=>$applicant];
        $tujuan = $peminjaman['tujuan'] ?? ($peminjaman['route'] ?? ($peminjaman['keterangan'] ?? ''));
        if (in_array('tujuan', $stCols, true)) $desired['tujuan'] = ['t'=>'s','v'=> $tujuan];
        $keperluan = $peminjaman['keperluan'] ?? ($peminjaman['purpose'] ?? 'Permohonan melalui peminjaman_kendaraan');
        if (in_array('keperluan', $stCols, true)) $desired['keperluan'] = ['t'=>'s','v'=>$keperluan];
        if (in_array('tanggal_berangkat', $stCols, true) && $berangkat) $desired['tanggal_berangkat'] = ['t'=>'s','v'=>$berangkat];
        if (in_array('tanggal_kembali', $stCols, true) && $kembali) $desired['tanggal_kembali'] = ['t'=>'s','v'=>$kembali];
        if (in_array('estimasi_km', $stCols, true) && array_key_exists('estimasi_km', $peminjaman)) $desired['estimasi_km'] = ['t'=>'i','v'=>($peminjaman['estimasi_km'] ?? null)];
        if (in_array('estimasi_bbm', $stCols, true) && array_key_exists('estimasi_bbm', $peminjaman)) $desired['estimasi_bbm'] = ['t'=>'d','v'=>($peminjaman['estimasi_bbm'] ?? null)];
        if (in_array('status', $stCols, true)) $desired['status'] = ['t'=>'s','v'=>'Disetujui'];
        if (in_array('created_by', $stCols, true)) $desired['created_by'] = ['t'=>'i','v'=> $current_user['id'] ?? 0];

        // Filter to existing columns (defensive)
        $insert_cols = [];
        $types = '';
        $values = [];
        foreach ($desired as $col => $meta) {
            if (in_array($col, $stCols, true)) {
                $insert_cols[] = $col;
                $types .= $meta['t'];
                $values[] = $meta['v'];
            }
        }

        if (!empty($insert_cols)) {
            // If the peminjaman already references an existing surat_tugas, update that surat instead of creating a duplicate
            $existing_surat_id = null;
            if (!empty($peminjaman['surat_tugas_id'])) {
                $existing_surat_id = (int)$peminjaman['surat_tugas_id'];
            }

            if ($existing_surat_id) {
                $stColsMap = array_flip($stCols);
                $upd_parts = [];
                $upd_types = '';
                $upd_vals = [];

                // Always set status = 'Disetujui' if column exists
                if (isset($stColsMap['status'])) {
                    $upd_parts[] = "status = 'Disetujui'";
                }

                // Handle pimpinan approval vs admin
                if (strtolower($current_role) === 'pimpinan') {
                    if (isset($stColsMap['approval_pimpinan_status'])) $upd_parts[] = "approval_pimpinan_status = 'Approved'";
                    if (isset($stColsMap['approval_pimpinan_by'])) $upd_parts[] = "approval_pimpinan_by = " . intval($current_user['id']);
                    if (isset($stColsMap['approval_pimpinan_at'])) $upd_parts[] = "approval_pimpinan_at = NOW()";
                } else {
                    if (isset($stColsMap['approval_pimpinan_status'])) $upd_parts[] = "approval_pimpinan_status = 'Pending'";
                    if (isset($stColsMap['approval_pimpinan_by'])) $upd_parts[] = "approval_pimpinan_by = NULL";
                    if (isset($stColsMap['approval_pimpinan_at'])) $upd_parts[] = "approval_pimpinan_at = NULL";
                }

                $upd_parts[] = "updated_at = NOW()";

                if (!empty($upd_parts)) {
                    $sqlu = "UPDATE surat_tugas SET " . implode(', ', $upd_parts) . " WHERE id = ?";
                    $updu = $mysqli->prepare($sqlu);
                    if ($updu) {
                        $updu->bind_param('i', $existing_surat_id);
                        $updu->execute();
                        $updu->close();
                        $surat_tugas_id = $existing_surat_id;
                    }
                }

                // Notify applicant about approval
                $notify_to = $applicant ?: (int)($peminjaman['pemohon_id'] ?? 0);
                if ($notify_to) insert_notification($mysqli, $notify_to, 'Peminjaman Anda telah disetujui dan terhubung ke Surat Tugas ID ' . $surat_tugas_id, 'Surat Tugas Diperbarui');
            } else {
                // Create a new surat_tugas as before
                $placeholders = implode(', ', array_fill(0, count($insert_cols), '?'));
                $sql = "INSERT INTO surat_tugas (" . implode(', ', $insert_cols) . ") VALUES (" . $placeholders . ")";
                $ins = $mysqli->prepare($sql);
                if ($ins) {
                    $bind = [];
                    $bind[] = & $types;
                    for ($i=0;$i<count($values);$i++) $bind[] = & $values[$i];
                    call_user_func_array([$ins, 'bind_param'], $bind);
                    if ($ins->execute()) {
                        $surat_tugas_id = $mysqli->insert_id;
                        // Link back to peminjaman if column exists
                        if (in_array('surat_tugas_id', $pkCols, true)) {
                            $upd = $mysqli->prepare("UPDATE peminjaman_kendaraan SET surat_tugas_id = ? WHERE id = ?");
                            if ($upd) { $upd->bind_param('ii', $surat_tugas_id, $peminjaman_id); $upd->execute(); $upd->close(); }
                        }
                        // If approver is pimpinan, mark approval_pimpinan as Approved if columns exist
                        if (strtolower($current_role) === 'pimpinan') {
                            $stUpdParts = [];
                            if (in_array('approval_pimpinan_status', $stCols, true)) $stUpdParts[] = "approval_pimpinan_status = 'Approved'";
                            if (in_array('approval_pimpinan_by', $stCols, true)) $stUpdParts[] = "approval_pimpinan_by = " . intval($current_user['id']);
                            if (in_array('approval_pimpinan_at', $stCols, true)) $stUpdParts[] = "approval_pimpinan_at = NOW()";
                            if (!empty($stUpdParts)) {
                                $upd2 = $mysqli->prepare("UPDATE surat_tugas SET " . implode(', ', $stUpdParts) . " WHERE id = ?");
                                if ($upd2) { $upd2->bind_param('i', $surat_tugas_id); $upd2->execute(); $upd2->close(); }
                            }
                        }
                        // Notify applicant
                        $notify_to = $applicant ?: (int)($peminjaman['pemohon_id'] ?? 0);
                        if ($notify_to) insert_notification($mysqli, $notify_to, 'Peminjaman Anda telah disetujui dan dibuat Surat Tugas nomor ' . ($desired['nomor_surat']['v'] ?? ''), 'Surat Tugas Dibuat');
                    }
                    $ins->close();
                }
            }
        }
    }
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
    if (function_exists('logActivity')) {
        logActivity($current_user['id'], 'update_peminjaman_status', $activity);
    } elseif (function_exists('log_user_activity')) {
        log_user_activity($activity);
    }
    
    // Return success response
    echo json_encode([
        'success' => true, 
        'message' => 'Status peminjaman berhasil diupdate',
        'new_status' => $new_status,
        'surat_tugas_id' => $surat_tugas_id
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
