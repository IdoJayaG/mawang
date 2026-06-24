<?php
require_once 'includes/auth.php';
// HANYA PIMPINAN YANG BISA APPROVE/REJECT PEMINJAMAN
require_pimpinan();

$current_user_id = get_current_user_id();
$current_role = get_current_role();
$action = $_GET['action'] ?? 'list';
$peminjaman_id = $_GET['id'] ?? null;
$msg = '';

function log_peminjaman_role_activity($role, $message) {
    if (in_array($role, ['pimpinan', 'driver', 'user'], true) && function_exists('log_user_activity')) {
        log_user_activity('[' . strtoupper($role) . '] ' . $message);
    }
}

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
$has_surat_tugas_id = in_array('surat_tugas_id', $peminjaman_cols, true);

// Ensure two-step approval columns exist (admin -> pimpinan)
function ensure_peminjaman_approval_columns($mysqli) {
    $cols = get_table_columns($mysqli, 'peminjaman_kendaraan');
    $defs = [
        'approval_admin_status' => "ENUM('Pending','Approved','Rejected') NOT NULL DEFAULT 'Pending'",
        'approval_admin_by' => 'INT NULL',
        'approval_admin_at' => 'DATETIME NULL',
        'approval_pimpinan_status' => "ENUM('Pending','Approved','Rejected') NOT NULL DEFAULT 'Pending'",
        'approval_pimpinan_by' => 'INT NULL',
        'approval_pimpinan_at' => 'DATETIME NULL'
    ];
    $refresh = false;
    foreach ($defs as $col => $def) {
        if (!in_array($col, $cols, true)) {
            @$mysqli->query("ALTER TABLE peminjaman_kendaraan ADD COLUMN {$col} {$def}");
            $refresh = true;
        }
    }
    return $refresh ? get_table_columns($mysqli, 'peminjaman_kendaraan') : $cols;
}

$peminjaman_cols = ensure_peminjaman_approval_columns($mysqli);
$has_admin_approval = in_array('approval_admin_status', $peminjaman_cols, true);
$has_pimpinan_approval = in_array('approval_pimpinan_status', $peminjaman_cols, true);

// Ensure optional linkage columns exist to support surat_tugas -> peminjaman -> surat_tugas flow.
if (!$has_surat_tugas_id) {
    @$mysqli->query("ALTER TABLE peminjaman_kendaraan ADD COLUMN surat_tugas_id INT NULL AFTER kendaraan_id");
    @$mysqli->query("ALTER TABLE peminjaman_kendaraan ADD INDEX idx_pk_surat_tugas_id (surat_tugas_id)");
    $peminjaman_cols = get_table_columns($mysqli, 'peminjaman_kendaraan');
    $has_surat_tugas_id = in_array('surat_tugas_id', $peminjaman_cols, true);
}

// Handle approval/rejection/edit
if ($_POST && in_array($action, ['approve', 'reject', 'edit', 'approve_surat', 'reject_surat'])) {
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
                    $is_pimpinan = ($current_role === 'pimpinan');
                    $cols_now = get_table_columns($mysqli, 'peminjaman_kendaraan');
                    $fields_now = $cols_now;
                    $notes_col = null;
                    foreach (['notes','note','catatan_approval','catatan_pengembalian','catatan'] as $c) {
                        if (in_array($c, $fields_now, true)) { $notes_col = $c; break; }
                    }
                    $updated_by_exists = in_array('updated_by', $fields_now, true);

                    $update_parts = [];
                    $bind_types = '';
                    $bind_params = [];

                    if ($is_pimpinan) {
                        $update_parts[] = "status = 'Approved'";
                        if ($has_pimpinan_approval) {
                            $update_parts[] = "approval_pimpinan_status = 'Approved'";
                            if (in_array('approval_pimpinan_by', $fields_now, true)) {
                                $update_parts[] = "approval_pimpinan_by = ?";
                                $bind_types .= 'i';
                                $bind_params[] = $current_user_id;
                            }
                            if (in_array('approval_pimpinan_at', $fields_now, true)) {
                                $update_parts[] = "approval_pimpinan_at = NOW()";
                            }
                        }
                        // If pimpinan approves directly, mark admin approval as approved (without admin by)
                        if ($has_admin_approval) {
                            $update_parts[] = "approval_admin_status = 'Approved'";
                            if (in_array('approval_admin_at', $fields_now, true)) {
                                $update_parts[] = "approval_admin_at = NOW()";
                            }
                        }
                        if ($approver_col) {
                            $update_parts[] = "{$approver_col} = ?";
                            $bind_types .= 'i';
                            $bind_params[] = $current_user_id;
                        }
                        if ($has_approved_at) {
                            $update_parts[] = "approved_at = NOW()";
                        }
                    } else {
                        // Admin approval (step 1): keep status pending, forward to pimpinan
                        if ($has_admin_approval) {
                            $update_parts[] = "approval_admin_status = 'Approved'";
                            if (in_array('approval_admin_by', $fields_now, true)) {
                                $update_parts[] = "approval_admin_by = ?";
                                $bind_types .= 'i';
                                $bind_params[] = $current_user_id;
                            }
                            if (in_array('approval_admin_at', $fields_now, true)) {
                                $update_parts[] = "approval_admin_at = NOW()";
                            }
                        }
                        if ($has_pimpinan_approval) {
                            $update_parts[] = "approval_pimpinan_status = 'Pending'";
                        }
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

                    $sql_up = "UPDATE peminjaman_kendaraan SET " . implode(', ', $update_parts) . " WHERE id = ? AND status = 'Pending'";
                    $bind_types .= 'i';
                    $bind_params[] = $peminjaman_id;

                    $stmt = $mysqli->prepare($sql_up);
                    if ($bind_params) {
                        $stmt->bind_param($bind_types, ...$bind_params);
                    }

                    if ($stmt->execute()) {
                        if ($is_pimpinan) {
                            // Update vehicle status using available column
                            $vcol = vehicle_status_column($mysqli);
                            if ($vcol) {
                                $vid = (int)$peminjaman['kendaraan_id'];
                                $val = $mysqli->real_escape_string('Dipinjam');
                                $mysqli->query("UPDATE kendaraan SET `{$vcol}` = '{$val}' WHERE id = {$vid}");
                            }

                            // If there is no linked surat_tugas yet, create a minimal surat_tugas record
                            $has_st_table = table_exists($mysqli, 'surat_tugas');
                            $current_suratt_id = (int)($peminjaman['surat_tugas_id'] ?? 0);
                            if ($has_st_table && $current_suratt_id <= 0) {
                                $st_cols = get_table_columns($mysqli, 'surat_tugas');
                                $desired = [];
                                if (in_array('nomor_surat', $st_cols, true)) $desired['nomor_surat'] = ['type'=>'s','value'=>'AUTO-' . time()];
                                if (in_array('tanggal_surat', $st_cols, true)) $desired['tanggal_surat'] = ['type'=>'s','value'=>date('Y-m-d')];
                                if (in_array('berangkat_dari', $st_cols, true)) $desired['berangkat_dari'] = ['type'=>'s','value'=>'SPBT Kemhan Cawang'];
                                if (in_array('kendaraan_id', $st_cols, true)) $desired['kendaraan_id'] = ['type'=>'i','value'=> (int)$peminjaman['kendaraan_id']];
                                // determine pengguna/pemohon
                                $pengguna_id = null;
                                foreach (['peminjam_id','pemohon_id','pengguna_id','user_id'] as $c) {
                                    if (!empty($peminjaman[$c])) { $pengguna_id = (int)$peminjaman[$c]; break; }
                                }
                                if ($pengguna_id && in_array('pengguna_id', $st_cols, true)) $desired['pengguna_id'] = ['type'=>'i','value'=>$pengguna_id];
                                if (in_array('tujuan', $st_cols, true)) $desired['tujuan'] = ['type'=>'s','value'=>$peminjaman['tujuan'] ?? ''];
                                if (in_array('keperluan', $st_cols, true)) $desired['keperluan'] = ['type'=>'s','value'=>$peminjaman['keperluan'] ?? ''];
                                if (in_array('tanggal_berangkat', $st_cols, true)) $desired['tanggal_berangkat'] = ['type'=>'s','value'=>$peminjaman['tanggal_mulai'] ?? null];
                                if (in_array('tanggal_kembali', $st_cols, true)) $desired['tanggal_kembali'] = ['type'=>'s','value'=>$peminjaman['tanggal_selesai'] ?? null];
                                if (in_array('estimasi_km', $st_cols, true)) $desired['estimasi_km'] = ['type'=>'i','value'=> (int)($peminjaman['estimasi_km'] ?? 0)];
                                if (in_array('estimasi_bbm', $st_cols, true)) $desired['estimasi_bbm'] = ['type'=>'d','value'=> ($peminjaman['estimasi_bbm'] ?? 0)];
                                if (in_array('status', $st_cols, true)) $desired['status'] = ['type'=>'s','value'=>'Disetujui'];
                                if (in_array('created_by', $st_cols, true)) $desired['created_by'] = ['type'=>'i','value'=>$current_user_id];

                                if (!empty($desired)) {
                                    $insert_cols = array_keys($desired);
                                    $types = '';
                                    $vals = [];
                                    foreach ($desired as $meta) { $types .= $meta['type']; $vals[] = $meta['value']; }
                                    $placeholders = implode(', ', array_fill(0, count($insert_cols), '?'));
                                    $sql_ins = "INSERT INTO surat_tugas (" . implode(', ', $insert_cols) . ") VALUES (" . $placeholders . ")";
                                    $ins = $mysqli->prepare($sql_ins);
                                    if ($ins) {
                                        $bind_params = [];
                                        $bind_params[] = & $types;
                                        for ($i = 0; $i < count($vals); $i++) { $bind_params[] = & $vals[$i]; }
                                        call_user_func_array([$ins, 'bind_param'], $bind_params);
                                        if ($ins->execute()) {
                                            $new_st_id = $ins->insert_id;
                                            // link back to peminjaman_kendaraan if column exists
                                            if ($has_surat_tugas_id) {
                                                $upd = $mysqli->prepare("UPDATE peminjaman_kendaraan SET surat_tugas_id = ? WHERE id = ?");
                                                if ($upd) { $upd->bind_param('ii', $new_st_id, $peminjaman_id); $upd->execute(); $upd->close(); }
                                            }
                                        }
                                        $ins->close();
                                    }
                                }
                            }

                            // If this approval originated from surat_tugas flow, finalize surat_tugas for driver visibility.
                            $surat_tugas_id = (int)($peminjaman['surat_tugas_id'] ?? 0);
                            if ($surat_tugas_id > 0 && table_exists($mysqli, 'surat_tugas')) {
                                $st_cols = get_table_columns($mysqli, 'surat_tugas');
                                $set_parts = ["status = 'Disetujui'", "updated_at = NOW()"];
                                $types_st = '';
                                $vals_st = [];
                                if (in_array('approval_pimpinan_status', $st_cols, true)) {
                                    $set_parts[] = "approval_pimpinan_status = 'Approved'";
                                }
                                if (in_array('approval_pimpinan_at', $st_cols, true)) {
                                    $set_parts[] = "approval_pimpinan_at = NOW()";
                                }
                                if (in_array('approval_pimpinan_by', $st_cols, true)) {
                                    $set_parts[] = "approval_pimpinan_by = ?";
                                    $types_st .= 'i';
                                    $vals_st[] = $current_user_id;
                                }
                                if (in_array('updated_by', $st_cols, true)) {
                                    $set_parts[] = "updated_by = ?";
                                    $types_st .= 'i';
                                    $vals_st[] = $current_user_id;
                                }

                                $sql_st = "UPDATE surat_tugas SET " . implode(', ', $set_parts) . " WHERE id = ?";
                                $types_st .= 'i';
                                $vals_st[] = $surat_tugas_id;
                                $st_upd = $mysqli->prepare($sql_st);
                                if ($st_upd) {
                                    $st_upd->bind_param($types_st, ...$vals_st);
                                    $st_upd->execute();
                                    $st_upd->close();
                                }
                            }

                            // Notify user
                            $user_id = (int)($peminjaman['peminjam_id'] ?? 0);
                            if ($user_id) {
                                $label_kendaraan = $peminjaman['no_reg'] ?: ($peminjaman['no_polisi'] ?? '');
                                $message = "Pengajuan peminjaman kendaraan {$label_kendaraan} telah disetujui pimpinan";
                                insert_notification($mysqli, $user_id, $message, 'Peminjaman Disetujui');
                            }

                            $msg = '<div class="alert alert-success">Pengajuan peminjaman berhasil disetujui pimpinan!</div>';
                            log_user_activity("Menyetujui peminjaman kendaraan ID: {$peminjaman['kendaraan_id']} untuk user ID: {$peminjaman['peminjam_id']}");
                            log_peminjaman_role_activity($current_role, "Menyetujui peminjaman ID: {$peminjaman_id}");
                        } else {
                            // Admin approval: notify pimpinan for second approval
                            $pimpinan_role_id = function_exists('get_role_id_by_code') ? get_role_id_by_code('PIMPINAN') : null;
                            if ($pimpinan_role_id) {
                                $res = $mysqli->query("SELECT p.id FROM pengguna p JOIN user_account ua ON p.id = ua.pengguna_id WHERE ua.role_id = " . (int)$pimpinan_role_id);
                                $label_kendaraan = $peminjaman['no_reg'] ?: ($peminjaman['no_polisi'] ?? '');
                                $msg_note = "Pengajuan peminjaman kendaraan {$label_kendaraan} menunggu persetujuan pimpinan.";
                                while ($res && ($row = $res->fetch_assoc())) {
                                    insert_notification($mysqli, (int)$row['id'], $msg_note, 'Persetujuan Pimpinan');
                                }
                            }
                            $msg = '<div class="alert alert-success">Pengajuan disetujui admin. Menunggu persetujuan pimpinan.</div>';
                            log_user_activity("Menyetujui (admin) peminjaman ID: {$peminjaman_id}");
                            log_peminjaman_role_activity($current_role, "Menyetujui (admin) peminjaman ID: {$peminjaman_id}");
                        }
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

                    $bind_types = '';
                    $bind_params = [];
                    $update_parts = ["status = 'Rejected'"];
                    if ($current_role === 'admin' && in_array('approval_admin_status', $fields_now, true)) {
                        $update_parts[] = "approval_admin_status = 'Rejected'";
                        if (in_array('approval_admin_by', $fields_now, true)) {
                            $update_parts[] = "approval_admin_by = ?";
                            $bind_types .= 'i';
                            $bind_params[] = $current_user_id;
                        }
                        if (in_array('approval_admin_at', $fields_now, true)) {
                            $update_parts[] = "approval_admin_at = NOW()";
                        }
                    }
                    if ($current_role === 'pimpinan' && in_array('approval_pimpinan_status', $fields_now, true)) {
                        $update_parts[] = "approval_pimpinan_status = 'Rejected'";
                        if (in_array('approval_pimpinan_by', $fields_now, true)) {
                            $update_parts[] = "approval_pimpinan_by = ?";
                            $bind_types .= 'i';
                            $bind_params[] = $current_user_id;
                        }
                        if (in_array('approval_pimpinan_at', $fields_now, true)) {
                            $update_parts[] = "approval_pimpinan_at = NOW()";
                        }
                    }
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

                    // If this rejection originated from surat_tugas flow, mark surat_tugas waiting flow as rejected.
                    if (!empty($has_surat_tugas_id)) {
                        $qst = $mysqli->prepare("SELECT surat_tugas_id FROM peminjaman_kendaraan WHERE id = ? LIMIT 1");
                        if ($qst) {
                            $qst->bind_param('i', $peminjaman_id);
                            $qst->execute();
                            $r = $qst->get_result()->fetch_assoc();
                            $qst->close();
                            $surat_tugas_id = (int)($r['surat_tugas_id'] ?? 0);
                            if ($surat_tugas_id > 0 && table_exists($mysqli, 'surat_tugas')) {
                                $st_cols = get_table_columns($mysqli, 'surat_tugas');
                                $set_parts = ["updated_at = NOW()"];
                                $types_st = '';
                                $vals_st = [];
                                if (in_array('approval_pimpinan_status', $st_cols, true)) {
                                    $set_parts[] = "approval_pimpinan_status = 'Rejected'";
                                }
                                if (in_array('updated_by', $st_cols, true)) {
                                    $set_parts[] = "updated_by = ?";
                                    $types_st .= 'i';
                                    $vals_st[] = $current_user_id;
                                }
                                $sql_st = "UPDATE surat_tugas SET " . implode(', ', $set_parts) . " WHERE id = ?";
                                $types_st .= 'i';
                                $vals_st[] = $surat_tugas_id;
                                $st_upd = $mysqli->prepare($sql_st);
                                if ($st_upd) {
                                    $st_upd->bind_param($types_st, ...$vals_st);
                                    $st_upd->execute();
                                    $st_upd->close();
                                }
                            }
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
                    log_peminjaman_role_activity($current_role, "Menolak peminjaman ID: {$peminjaman_id}");
                } else {
                    $msg = '<div class="alert alert-danger">Gagal menolak pengajuan. Mungkin status sudah berubah.</div>';
                }
                $stmt->close();
            }
        } elseif ($action === 'edit') {
            $keperluan = trim((string)($_POST['keperluan'] ?? ''));
            $tujuan = trim((string)($_POST['tujuan'] ?? ''));
            $tanggal_mulai = trim((string)($_POST['tanggal_mulai'] ?? ''));
            $tanggal_selesai = trim((string)($_POST['tanggal_selesai'] ?? ''));

            if ($keperluan === '' || $tujuan === '' || $tanggal_mulai === '' || $tanggal_selesai === '') {
                $msg = '<div class="alert alert-danger">Keperluan, tujuan, tanggal mulai, dan tanggal selesai wajib diisi.</div>';
            } elseif (strtotime($tanggal_selesai) <= strtotime($tanggal_mulai)) {
                $msg = '<div class="alert alert-danger">Tanggal selesai harus lebih besar dari tanggal mulai.</div>';
            } else {
                // Edit only pending requests to keep approval history consistent.
                $cek = $mysqli->prepare("SELECT id FROM peminjaman_kendaraan WHERE id = ? AND status = 'Pending' LIMIT 1");
                $cek->bind_param('i', $peminjaman_id);
                $cek->execute();
                $ok_data = $cek->get_result()->fetch_assoc();
                $cek->close();

                if (!$ok_data) {
                    $msg = '<div class="alert alert-danger">Data tidak ditemukan atau status bukan Pending.</div>';
                } else {
                    $cols_now = get_table_columns($mysqli, 'peminjaman_kendaraan');
                    $has_updated_by = in_array('updated_by', $cols_now, true);

                    $sql = "UPDATE peminjaman_kendaraan SET keperluan = ?, tujuan = ?, tanggal_mulai = ?, tanggal_selesai = ?";
                    if ($has_updated_by) {
                        $sql .= ", updated_by = ?";
                    }
                    $sql .= ", updated_at = NOW() WHERE id = ? AND status = 'Pending'";

                    $stmt = $mysqli->prepare($sql);
                    if ($has_updated_by) {
                        $stmt->bind_param('ssssii', $keperluan, $tujuan, $tanggal_mulai, $tanggal_selesai, $current_user_id, $peminjaman_id);
                    } else {
                        $stmt->bind_param('ssssi', $keperluan, $tujuan, $tanggal_mulai, $tanggal_selesai, $peminjaman_id);
                    }

                    if ($stmt->execute() && $stmt->affected_rows >= 0) {
                        $msg = '<div class="alert alert-success">Data pengajuan peminjaman berhasil diperbarui.</div>';
                        log_user_activity("Mengubah detail pengajuan peminjaman ID: {$peminjaman_id}");
                        log_peminjaman_role_activity($current_role, "Mengubah detail peminjaman ID: {$peminjaman_id}");
                        $action = 'list';
                    } else {
                        $msg = '<div class="alert alert-danger">Gagal memperbarui data: ' . $stmt->error . '</div>';
                    }
                    $stmt->close();
                }
            }
        } elseif ($action === 'approve_surat') {
            $surat_id_post = (int)($_POST['surat_id'] ?? 0);
            if (!$surat_id_post) {
                $msg = '<div class="alert alert-danger">ID surat tugas tidak valid!</div>';
            } else {
                $st_stmt = $mysqli->prepare("SELECT st.*, k.no_reg, k.no_polisi FROM surat_tugas st LEFT JOIN kendaraan k ON st.kendaraan_id = k.id WHERE st.id = ? AND st.approval_pimpinan_status = 'Pending'");
                $st_stmt->bind_param('i', $surat_id_post);
                $st_stmt->execute();
                $surat_info = $st_stmt->get_result()->fetch_assoc();
                $st_stmt->close();

                if (!$surat_info) {
                    $msg = '<div class="alert alert-danger">Surat tugas tidak ditemukan atau sudah diproses!</div>';
                } else {
                    $st_cols = get_table_columns($mysqli, 'surat_tugas');
                    $set_parts = ["approval_pimpinan_status = 'Approved'", "status = 'Disetujui'", "updated_at = NOW()"];
                    $types_st = '';
                    $vals_st = [];
                    if (in_array('approval_pimpinan_by', $st_cols, true)) {
                        $set_parts[] = "approval_pimpinan_by = ?"; $types_st .= 'i'; $vals_st[] = $current_user_id;
                    }
                    if (in_array('approval_pimpinan_at', $st_cols, true)) {
                        $set_parts[] = "approval_pimpinan_at = NOW()";
                    }
                    if (in_array('updated_by', $st_cols, true)) {
                        $set_parts[] = "updated_by = ?"; $types_st .= 'i'; $vals_st[] = $current_user_id;
                    }
                    $types_st .= 'i'; $vals_st[] = $surat_id_post;

                    $st_upd = $mysqli->prepare("UPDATE surat_tugas SET " . implode(', ', $set_parts) . " WHERE id = ? AND approval_pimpinan_status = 'Pending'");
                    if ($st_upd) {
                        $st_upd->bind_param($types_st, ...$vals_st);
                        if ($st_upd->execute() && $st_upd->affected_rows > 0) {
                            // Approve linked peminjaman_kendaraan if pending
                            $pk_q = $mysqli->prepare("SELECT id, peminjam_id FROM peminjaman_kendaraan WHERE surat_tugas_id = ? AND status = 'Pending' LIMIT 1");
                            if ($pk_q) {
                                $pk_q->bind_param('i', $surat_id_post);
                                $pk_q->execute();
                                $pk_row = $pk_q->get_result()->fetch_assoc();
                                $pk_q->close();
                                if ($pk_row) {
                                    $pk_id = (int)$pk_row['id'];
                                    $pk_cols = get_table_columns($mysqli, 'peminjaman_kendaraan');
                                    $pk_parts = ["status = 'Approved'", "updated_at = NOW()"];
                                    $pk_types = ''; $pk_vals = [];
                                    if (in_array('approval_pimpinan_status', $pk_cols, true)) $pk_parts[] = "approval_pimpinan_status = 'Approved'";
                                    if (in_array('approval_pimpinan_by', $pk_cols, true)) { $pk_parts[] = "approval_pimpinan_by = ?"; $pk_types .= 'i'; $pk_vals[] = $current_user_id; }
                                    if (in_array('approval_pimpinan_at', $pk_cols, true)) $pk_parts[] = "approval_pimpinan_at = NOW()";
                                    if (in_array('approval_admin_status', $pk_cols, true)) $pk_parts[] = "approval_admin_status = 'Approved'";
                                    if (in_array('approval_admin_at', $pk_cols, true)) $pk_parts[] = "approval_admin_at = NOW()";
                                    $approver_col_pk = null;
                                    foreach (['approved_by','approval_by','approver_id','approved_by_id','approver'] as $c) {
                                        if (in_array($c, $pk_cols, true)) { $approver_col_pk = $c; break; }
                                    }
                                    if ($approver_col_pk) { $pk_parts[] = "{$approver_col_pk} = ?"; $pk_types .= 'i'; $pk_vals[] = $current_user_id; }
                                    if (in_array('approved_at', $pk_cols, true)) $pk_parts[] = "approved_at = NOW()";
                                    $pk_types .= 'i'; $pk_vals[] = $pk_id;
                                    $pk_upd = $mysqli->prepare("UPDATE peminjaman_kendaraan SET " . implode(', ', $pk_parts) . " WHERE id = ?");
                                    if ($pk_upd) { $pk_upd->bind_param($pk_types, ...$pk_vals); $pk_upd->execute(); $pk_upd->close(); }
                                }
                            }
                            // Mark kendaraan as Dipinjam
                            $kend_id = (int)($surat_info['kendaraan_id'] ?? 0);
                            $vcol = vehicle_status_column($mysqli);
                            if ($vcol && $kend_id > 0) {
                                $mysqli->query("UPDATE kendaraan SET `{$vcol}` = 'Dipinjam' WHERE id = {$kend_id}");
                            }
                            // Notify submitter
                            $notify_uid = (int)($surat_info['pengguna_id'] ?? 0);
                            if ($notify_uid) {
                                insert_notification($mysqli, $notify_uid, "Surat tugas {$surat_info['nomor_surat']} telah disetujui pimpinan.", 'Surat Tugas Disetujui');
                            }
                            $msg = '<div class="alert alert-success">Surat tugas berhasil disetujui pimpinan!</div>';
                            log_user_activity("Menyetujui surat tugas ID: {$surat_id_post}");
                            log_peminjaman_role_activity($current_role, "Menyetujui surat tugas ID: {$surat_id_post}");
                        } else {
                            $msg = '<div class="alert alert-danger">Gagal menyetujui surat tugas. Mungkin status sudah berubah.</div>';
                        }
                        $st_upd->close();
                    }
                }
            }

        } elseif ($action === 'reject_surat') {
            $surat_id_post = (int)($_POST['surat_id'] ?? 0);
            $rejected_reason = trim((string)($_POST['rejected_reason'] ?? ''));
            if (!$surat_id_post) {
                $msg = '<div class="alert alert-danger">ID surat tugas tidak valid!</div>';
            } elseif (empty($rejected_reason)) {
                $msg = '<div class="alert alert-danger">Alasan penolakan harus diisi!</div>';
            } else {
                $st_cols = get_table_columns($mysqli, 'surat_tugas');
                $set_parts = ["approval_pimpinan_status = 'Rejected'", "updated_at = NOW()"];
                $types_st = ''; $vals_st = [];
                if (in_array('approval_pimpinan_by', $st_cols, true)) { $set_parts[] = "approval_pimpinan_by = ?"; $types_st .= 'i'; $vals_st[] = $current_user_id; }
                if (in_array('approval_pimpinan_at', $st_cols, true)) $set_parts[] = "approval_pimpinan_at = NOW()";
                if (in_array('updated_by', $st_cols, true)) { $set_parts[] = "updated_by = ?"; $types_st .= 'i'; $vals_st[] = $current_user_id; }
                if (in_array('rejected_reason', $st_cols, true)) { $set_parts[] = "rejected_reason = ?"; $types_st .= 's'; $vals_st[] = $rejected_reason; }
                $types_st .= 'i'; $vals_st[] = $surat_id_post;

                $st_upd = $mysqli->prepare("UPDATE surat_tugas SET " . implode(', ', $set_parts) . " WHERE id = ? AND approval_pimpinan_status = 'Pending'");
                if ($st_upd) {
                    $st_upd->bind_param($types_st, ...$vals_st);
                    if ($st_upd->execute() && $st_upd->affected_rows > 0) {
                        // Reject linked peminjaman_kendaraan if pending
                        $pk_q2 = $mysqli->prepare("SELECT id FROM peminjaman_kendaraan WHERE surat_tugas_id = ? AND status = 'Pending' LIMIT 1");
                        if ($pk_q2) {
                            $pk_q2->bind_param('i', $surat_id_post);
                            $pk_q2->execute();
                            $pk_row2 = $pk_q2->get_result()->fetch_assoc();
                            $pk_q2->close();
                            if ($pk_row2) {
                                $pk_id2 = (int)$pk_row2['id'];
                                $pk_cols2 = get_table_columns($mysqli, 'peminjaman_kendaraan');
                                $rej_parts = ["status = 'Rejected'", "updated_at = NOW()"];
                                $rej_types = ''; $rej_vals = [];
                                if (in_array('approval_pimpinan_status', $pk_cols2, true)) $rej_parts[] = "approval_pimpinan_status = 'Rejected'";
                                if (in_array('approval_pimpinan_by', $pk_cols2, true)) { $rej_parts[] = "approval_pimpinan_by = ?"; $rej_types .= 'i'; $rej_vals[] = $current_user_id; }
                                if (in_array('approval_pimpinan_at', $pk_cols2, true)) $rej_parts[] = "approval_pimpinan_at = NOW()";
                                if (in_array('rejected_reason', $pk_cols2, true)) { $rej_parts[] = "rejected_reason = ?"; $rej_types .= 's'; $rej_vals[] = $rejected_reason; }
                                $rej_types .= 'i'; $rej_vals[] = $pk_id2;
                                $pk_upd2 = $mysqli->prepare("UPDATE peminjaman_kendaraan SET " . implode(', ', $rej_parts) . " WHERE id = ?");
                                if ($pk_upd2) { $pk_upd2->bind_param($rej_types, ...$rej_vals); $pk_upd2->execute(); $pk_upd2->close(); }
                            }
                        }
                        // Notify submitter
                        $surat_rej = $mysqli->query("SELECT nomor_surat, pengguna_id FROM surat_tugas WHERE id = {$surat_id_post} LIMIT 1")?->fetch_assoc();
                        if (!empty($surat_rej['pengguna_id'])) {
                            insert_notification($mysqli, (int)$surat_rej['pengguna_id'], "Surat tugas {$surat_rej['nomor_surat']} ditolak pimpinan: {$rejected_reason}", 'Surat Tugas Ditolak');
                        }
                        $msg = '<div class="alert alert-success">Surat tugas berhasil ditolak!</div>';
                        log_user_activity("Menolak surat tugas ID: {$surat_id_post} alasan: {$rejected_reason}");
                        log_peminjaman_role_activity($current_role, "Menolak surat tugas ID: {$surat_id_post}");
                    } else {
                        $msg = '<div class="alert alert-danger">Gagal menolak surat tugas. Mungkin status sudah berubah.</div>';
                    }
                    $st_upd->close();
                }
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

// Two-step approval filter for pending records
if ($status_filter === 'Pending') {
    if ($current_role === 'admin' && $has_admin_approval) {
        $where_conditions[] = "p.approval_admin_status = 'Pending'";
    }
    if ($current_role === 'pimpinan' && $has_pimpinan_approval) {
        $where_conditions[] = "p.approval_pimpinan_status = 'Pending'";
    }
}

// Note: allow pimpinan to see all peminjaman (not only those linked to surat_tugas)
// (Previously this filtered pimpinan to only see peminjaman with surat_tugas linkage.)

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

// Include surat_tugas drafts and approvals waiting for pimpinan if current view is pending or all
$surat_tugas_list = [];
if (table_exists($mysqli, 'surat_tugas') && in_array($status_filter, ['Pending', ''], true)) {
    $has_st_approval = false;
    $colQ = $mysqli->query("SHOW COLUMNS FROM surat_tugas LIKE 'approval_pimpinan_status'");
    if ($colQ && $colQ->num_rows > 0) {
        $has_st_approval = true;
    }
    if ($has_st_approval) {
        $stmt_st = $mysqli->prepare(
            "SELECT st.id, st.nomor_surat, st.tanggal_surat, st.tujuan, st.keperluan, st.tanggal_berangkat AS tanggal_mulai, st.tanggal_kembali AS tanggal_selesai, st.status, st.approval_pimpinan_status, k.no_reg, k.no_polisi, k.merk, k.tipe, u.nama_lengkap AS pemohon_name, u.nrp_nip AS pemohon_nip, st.created_at
             FROM surat_tugas st
             LEFT JOIN kendaraan k ON st.kendaraan_id = k.id
             LEFT JOIN pengguna u ON st.pengguna_id = u.id
             WHERE st.status = 'Draft' OR st.approval_pimpinan_status = 'Pending'
             ORDER BY st.created_at DESC
             LIMIT ?"
        );
        if ($stmt_st) {
            $stmt_st->bind_param('i', $limit);
            $stmt_st->execute();
            $surat_tugas_list = $stmt_st->get_result()->fetch_all(MYSQLI_ASSOC);
            $stmt_st->close();
        }
    }
}

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
if (in_array($action, ['approve', 'reject', 'edit']) && $peminjaman_id) {
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

// Get surat_tugas detail for surat approval/rejection actions
$surat_detail_data = null;
if (in_array($action, ['approve_surat', 'reject_surat']) && $peminjaman_id) {
    $stmt = $mysqli->prepare("
        SELECT st.*, k.no_reg, k.no_polisi, k.merk, k.tipe, k.jenis, k.warna,
               u.nama_lengkap AS pemohon_name, u.nrp_nip AS pemohon_nip,
               ua.username AS pemohon_username
        FROM surat_tugas st
        LEFT JOIN kendaraan k ON st.kendaraan_id = k.id
        LEFT JOIN pengguna u ON st.pengguna_id = u.id
        LEFT JOIN user_account ua ON u.id = ua.pengguna_id
        WHERE st.id = ? AND st.approval_pimpinan_status = 'Pending'
    ");
    $stmt->bind_param('i', $peminjaman_id);
    $stmt->execute();
    $surat_detail_data = $stmt->get_result()->fetch_assoc();
    $stmt->close();
}

// Status badge mapping
function getStatusBadge($status) {
    $badges = [
        'Pending' => 'badge-warning',
        'Draft' => 'badge-secondary',
        'Approved' => 'badge-success', 
        'Rejected' => 'badge-danger',
        'Ongoing' => 'badge-info',
        'Completed' => 'badge-primary',
        'Cancelled' => 'badge-secondary'
    ];
    
    $labels = [
        'Pending' => 'Menunggu',
        'Draft' => 'Draft',
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

function getDraftStatusBadge($status) {
    if ($status === 'Draft') {
        return '<span class="badge badge-secondary">Draft</span>';
    }
    return getStatusBadge($status);
}
?>

<div class="page-header">
    <h1><i class="fas fa-check-circle"></i> Persetujuan Peminjaman Kendaraan</h1>
</div>

<?= $msg ?>

<?php if ($action === 'edit' && $detail_data): ?>
    <div class="card">
        <div class="card-header">
            <h3><i class="fas fa-edit"></i> Edit Pengajuan Peminjaman</h3>
        </div>
        <div class="card-body">
            <form method="post">
                <input type="hidden" name="csrf_token" value="<?= generate_csrf_token() ?>">
                <input type="hidden" name="peminjaman_id" value="<?= (int)$detail_data['id'] ?>">

                <div class="detail-grid">
                    <div class="form-group">
                        <label for="keperluan">Keperluan *</label>
                        <textarea id="keperluan" name="keperluan" class="form-control" rows="3" required><?= htmlspecialchars((string)($detail_data['keperluan'] ?? '')) ?></textarea>
                    </div>
                    <div class="form-group">
                        <label for="tujuan">Tujuan *</label>
                        <input id="tujuan" name="tujuan" class="form-control" required value="<?= htmlspecialchars((string)($detail_data['tujuan'] ?? '')) ?>">
                    </div>
                    <div class="form-group">
                        <label for="tanggal_mulai">Tanggal Mulai *</label>
                        <input id="tanggal_mulai" name="tanggal_mulai" type="datetime-local" class="form-control" required value="<?= !empty($detail_data['tanggal_mulai']) ? date('Y-m-d\\TH:i', strtotime($detail_data['tanggal_mulai'])) : '' ?>">
                    </div>
                    <div class="form-group">
                        <label for="tanggal_selesai">Tanggal Selesai *</label>
                        <input id="tanggal_selesai" name="tanggal_selesai" type="datetime-local" class="form-control" required value="<?= !empty($detail_data['tanggal_selesai']) ? date('Y-m-d\\TH:i', strtotime($detail_data['tanggal_selesai'])) : '' ?>">
                    </div>
                </div>

                <div class="form-actions mt-3">
                    <button type="submit" class="btn btn-primary">
                        <i class="fas fa-save"></i> Simpan Perubahan
                    </button>
                    <a href="index.php?page=persetujuan_peminjaman" class="btn btn-secondary">
                        <i class="fas fa-arrow-left"></i> Kembali
                    </a>
                </div>
            </form>
        </div>
    </div>

<?php elseif (in_array($action, ['approve', 'reject']) && $detail_data): ?>
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

<?php elseif (in_array($action, ['approve_surat', 'reject_surat']) && $surat_detail_data): ?>
    <div class="card">
        <div class="card-header">
            <h3>
                <i class="fas fa-<?= $action === 'approve_surat' ? 'check' : 'times' ?>"></i>
                <?= $action === 'approve_surat' ? 'Setujui' : 'Tolak' ?> Surat Tugas
            </h3>
        </div>
        <div class="card-body">
            <div class="detail-section">
                <h4>Detail Surat Tugas</h4>
                <div class="detail-grid">
                    <div class="detail-group">
                        <label>Nomor Surat</label>
                        <div class="detail-value">
                            <strong><?= htmlspecialchars($surat_detail_data['nomor_surat'] ?? '-') ?></strong><br>
                            <small class="text-muted">Tanggal: <?= !empty($surat_detail_data['tanggal_surat']) ? date('d/m/Y', strtotime($surat_detail_data['tanggal_surat'])) : '-' ?></small>
                        </div>
                    </div>
                    <div class="detail-group">
                        <label>Pemohon / Driver</label>
                        <div class="detail-value">
                            <strong><?= htmlspecialchars($surat_detail_data['pemohon_name'] ?? '-') ?></strong><br>
                            <small class="text-muted">
                                NIP: <?= htmlspecialchars($surat_detail_data['pemohon_nip'] ?? '-') ?><br>
                                Username: <?= htmlspecialchars($surat_detail_data['pemohon_username'] ?? '-') ?>
                            </small>
                        </div>
                    </div>
                    <div class="detail-group">
                        <label>Kendaraan</label>
                        <div class="detail-value">
                            <strong><?= htmlspecialchars($surat_detail_data['no_reg'] ?: ($surat_detail_data['no_polisi'] ?? '-')) ?></strong><br>
                            <small class="text-muted"><?= htmlspecialchars(trim(($surat_detail_data['merk'] ?? '') . ' ' . ($surat_detail_data['tipe'] ?? ''))) ?></small><br>
                            <small class="text-muted"><?= htmlspecialchars($surat_detail_data['jenis'] ?? '') ?><?= !empty($surat_detail_data['warna']) ? ' — ' . htmlspecialchars($surat_detail_data['warna']) : '' ?></small>
                        </div>
                    </div>
                    <div class="detail-group">
                        <label>Tujuan & Keperluan</label>
                        <div class="detail-value">
                            <strong><?= htmlspecialchars($surat_detail_data['tujuan'] ?? '-') ?></strong><br>
                            <small class="text-muted"><?= htmlspecialchars($surat_detail_data['keperluan'] ?? '-') ?></small>
                        </div>
                    </div>
                    <div class="detail-group">
                        <label>Periode Perjalanan</label>
                        <div class="detail-value">
                            <strong>Berangkat:</strong> <?= !empty($surat_detail_data['tanggal_berangkat']) ? date('d/m/Y', strtotime($surat_detail_data['tanggal_berangkat'])) : '-' ?><br>
                            <strong>Kembali:</strong> <?= !empty($surat_detail_data['tanggal_kembali']) ? date('d/m/Y', strtotime($surat_detail_data['tanggal_kembali'])) : '-' ?>
                        </div>
                    </div>
                    <div class="detail-group">
                        <label>Estimasi</label>
                        <div class="detail-value">
                            <strong>KM:</strong> <?= is_numeric($surat_detail_data['estimasi_km'] ?? null) ? number_format($surat_detail_data['estimasi_km']) . ' km' : '-' ?><br>
                            <strong>BBM:</strong> <?= is_numeric($surat_detail_data['estimasi_bbm'] ?? null) ? 'Rp ' . number_format($surat_detail_data['estimasi_bbm']) : '-' ?>
                        </div>
                    </div>
                </div>
            </div>

            <div class="approval-section">
                <h4><?= $action === 'approve_surat' ? 'Konfirmasi Persetujuan' : 'Konfirmasi Penolakan' ?></h4>
                <form method="post">
                    <input type="hidden" name="csrf_token" value="<?= generate_csrf_token() ?>">
                    <input type="hidden" name="surat_id" value="<?= (int)$surat_detail_data['id'] ?>">
                    <div class="form-group">
                        <label for="notes_surat">Catatan (Opsional)</label>
                        <textarea id="notes_surat" name="notes" class="form-control" rows="2" placeholder="Catatan tambahan..."></textarea>
                    </div>
                    <?php if ($action === 'reject_surat'): ?>
                    <div class="form-group">
                        <label for="rejected_reason_surat">Alasan Penolakan <span class="text-danger">*</span></label>
                        <textarea id="rejected_reason_surat" name="rejected_reason" class="form-control" rows="2" required placeholder="Berikan alasan penolakan"></textarea>
                    </div>
                    <?php endif; ?>
                    <div class="form-actions">
                        <button type="submit" class="btn btn-<?= $action === 'approve_surat' ? 'success' : 'danger' ?>">
                            <i class="fas fa-<?= $action === 'approve_surat' ? 'check' : 'times' ?>"></i>
                            <?= $action === 'approve_surat' ? 'Setujui' : 'Tolak' ?> Surat Tugas
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
    
    <div class="row g-2 mb-3">
        <div class="col-6 col-md-3">
            <div class="card border-0 shadow-sm h-100" style="border-left:4px solid #ffc107!important">
                <div class="card-body py-2 px-3 d-flex align-items-center gap-3">
                    <i class="fas fa-clock fa-2x text-warning opacity-75"></i>
                    <div><div class="text-muted small">Menunggu</div><div class="fw-bold fs-5"><?= (int)$stats['pending'] ?></div></div>
                </div>
            </div>
        </div>
        <div class="col-6 col-md-3">
            <div class="card border-0 shadow-sm h-100" style="border-left:4px solid #28a745!important">
                <div class="card-body py-2 px-3 d-flex align-items-center gap-3">
                    <i class="fas fa-check fa-2x text-success opacity-75"></i>
                    <div><div class="text-muted small">Disetujui</div><div class="fw-bold fs-5"><?= (int)$stats['approved'] ?></div></div>
                </div>
            </div>
        </div>
        <div class="col-6 col-md-3">
            <div class="card border-0 shadow-sm h-100" style="border-left:4px solid #17a2b8!important">
                <div class="card-body py-2 px-3 d-flex align-items-center gap-3">
                    <i class="fas fa-car fa-2x text-info opacity-75"></i>
                    <div><div class="text-muted small">Berlangsung</div><div class="fw-bold fs-5"><?= (int)$stats['ongoing'] ?></div></div>
                </div>
            </div>
        </div>
        <div class="col-6 col-md-3">
            <div class="card border-0 shadow-sm h-100" style="border-left:4px solid #6c757d!important">
                <div class="card-body py-2 px-3 d-flex align-items-center gap-3">
                    <i class="fas fa-list fa-2x text-secondary opacity-75"></i>
                    <div><div class="text-muted small">Total</div><div class="fw-bold fs-5"><?= (int)$stats['total'] ?></div></div>
                </div>
            </div>
        </div>
    </div>

    <?php if (!empty($surat_tugas_list)): ?>
        <div class="card mb-4">
            <div class="card-header bg-dark text-white">
                <h5 class="mb-0"><i class="fas fa-file-alt me-2"></i>Surat Tugas Menunggu Persetujuan Pimpinan</h5>
            </div>
            <div class="card-body p-0">
                <div class="table-responsive">
                    <table class="table table-sm mb-0">
                        <thead>
                            <tr>
                                <th>No</th>
                                <th>Tanggal Surat</th>
                                <th>Nomor Surat</th>
                                <th>Pemohon</th>
                                <th>Kendaraan</th>
                                <th>Tujuan</th>
                                <th>Status</th>
                                <th>Aksi</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($surat_tugas_list as $idx => $surat): ?>
                                <tr>
                                    <td><?= $idx + 1 ?></td>
                                    <td><?= date('d/m/Y', strtotime($surat['tanggal_surat'] ?? $surat['created_at'])) ?></td>
                                    <td><?= htmlspecialchars($surat['nomor_surat']) ?></td>
                                    <td>
                                        <strong><?= htmlspecialchars($surat['pemohon_name']) ?></strong><br>
                                        <small class="text-muted"><?= htmlspecialchars($surat['pemohon_nip']) ?></small>
                                    </td>
                                    <td>
                                        <strong><?= htmlspecialchars($surat['no_reg'] ?? ($surat['no_polisi'] ?? '-')) ?></strong><br>
                                        <small class="text-muted"><?= htmlspecialchars(trim(($surat['merk'] ?? '') . ' ' . ($surat['tipe'] ?? ''))) ?></small>
                                    </td>
                                    <td>
                                        <div class="text-truncate text-truncate-custom" title="<?= htmlspecialchars($surat['tujuan']) ?>">
                                            <?= htmlspecialchars($surat['tujuan']) ?>
                                        </div>
                                    </td>
                                    <td><?= getStatusBadge($surat['status']) ?></td>
                                    <td>
                                        <a href="index.php?page=persetujuan_peminjaman&action=approve_surat&id=<?= (int)$surat['id'] ?>" class="btn btn-sm btn-outline-success" title="Setujui">
                                            <i class="fas fa-check"></i>
                                        </a>
                                        <a href="index.php?page=persetujuan_peminjaman&action=reject_surat&id=<?= (int)$surat['id'] ?>" class="btn btn-sm btn-outline-danger ms-1" title="Tolak">
                                            <i class="fas fa-times"></i>
                                        </a>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    <?php endif; ?>

    <!-- Data Table -->
    <div class="card">
        <div class="card-header bg-secondary text-white">
            <h5 class="mb-0"><i class="fas fa-clipboard-list me-2"></i>Permohonan Peminjaman Kendaraan</h5>
        </div>
        <div class="card-body">
            <div class="table-responsive">
                <table class="table">
                    <thead>
                        <tr>
                            <th>No</th>
                            <th>Tanggal Ajuan</th>
                            <th>Driver</th>
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
                                            <strong><?= htmlspecialchars($peminjaman['no_reg'] ?? ($peminjaman['no_polisi'] ?? '')) ?></strong>
                                                <?php if (!empty($peminjaman['no_reg'] ?? '')): ?>
                                                    <br><small class="text-muted">Reg: <?= htmlspecialchars($peminjaman['no_reg'] ?? '') ?></small>
                                                <?php endif; ?>
                                                <br><small class="text-muted"><?= htmlspecialchars($peminjaman['merk'] ?? '') ?> <?= htmlspecialchars($peminjaman['tipe'] ?? '') ?></small>
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
                                        <div class="btn-group btn-group-sm">
                                            <?php if ($peminjaman['status'] === 'Pending'): ?>
                                                <a href="index.php?page=persetujuan_peminjaman&action=edit&id=<?= $peminjaman['id'] ?>"
                                                   class="btn btn-outline-primary" title="Edit Pengajuan">
                                                    <i class="fas fa-edit"></i>
                                                </a>
                                                <a href="index.php?page=persetujuan_peminjaman&action=approve&id=<?= $peminjaman['id'] ?>"
                                                   class="btn btn-outline-success" title="Setujui">
                                                    <i class="fas fa-check"></i>
                                                </a>
                                                <a href="index.php?page=persetujuan_peminjaman&action=reject&id=<?= $peminjaman['id'] ?>"
                                                   class="btn btn-outline-danger" title="Tolak">
                                                    <i class="fas fa-times"></i>
                                                </a>
                                            <?php else: ?>
                                                <span class="text-muted">—</span>
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

