<?php
// Migration script: copy approved surat_tugas into peminjaman_terjadwal
// Usage: php scripts/migrate_surat_to_peminjaman_terjadwal.php
require_once __DIR__ . '/../config/db.php';

if (!isset($conn) || !$conn) {
    echo "Database connection not available.\n";
    exit(1);
}

$statuses_to_migrate = [ 'Disetujui', 'Dalam Perjalanan' ];
$status_list = "'" . implode("','", array_map([$conn, 'real_escape_string'], $statuses_to_migrate)) . "'";

// Fetch surat_tugas rows to migrate
$sql = "SELECT * FROM surat_tugas WHERE status IN ($status_list) ORDER BY id ASC";
$res = $conn->query($sql);
if (!$res) {
    echo "Failed to query surat_tugas: " . $conn->error . "\n";
    exit(1);
}

$rows = $res->fetch_all(MYSQLI_ASSOC);
$total = count($rows);
if ($total === 0) {
    echo "No surat_tugas rows found with statuses: " . implode(', ', $statuses_to_migrate) . "\n";
    exit(0);
}

// Check target table
$has_pt = false;
$r = $conn->query("SHOW TABLES LIKE 'peminjaman_terjadwal'");
if ($r && $r->num_rows > 0) $has_pt = true;

$has_jk = false;
$r = $conn->query("SHOW TABLES LIKE 'jadwal_kendaraan'");
if ($r && $r->num_rows > 0) $has_jk = true;

$has_pk = false;
$r = $conn->query("SHOW TABLES LIKE 'pengguna_kendaraan'");
if ($r && $r->num_rows > 0) $has_pk = true;

$inserted = 0;
$skipped = 0;
$errors = 0;

foreach ($rows as $row) {
    $surat_id = (int)$row['id'];
    $kend = isset($row['kendaraan_id']) ? (int)$row['kendaraan_id'] : null;
    $penerima = isset($row['pengguna_id']) ? (int)$row['pengguna_id'] : null;
    $tgl_mulai = $row['tanggal_berangkat'] ?? null;
    $tgl_selesai = $row['tanggal_kembali'] ?? null;

    // Basic sanity
    if (!$kend || !$penerima || !$tgl_mulai) {
        echo "Skipping surat_id={$surat_id} due to missing kendaraan/pengguna/tanggal_berangkat\n";
        $skipped++;
        continue;
    }

    // Skip if a peminjaman_terjadwal seems to already exist for same kendaraan+user+date range
    $already = false;
    if ($has_pt) {
        // Inspect actual columns to avoid referencing non-existent columns
        $cols_info_q = $conn->query("SHOW COLUMNS FROM peminjaman_terjadwal");
        $cols_info = $cols_info_q ? $cols_info_q->fetch_all(MYSQLI_ASSOC) : [];
        $cols = array_column($cols_info, 'Field');

        $applicant_candidates = array_intersect(['pemohon_id','peminjam_id','pengguna_id','user_id'], $cols);

        // Build dynamic WHERE and types
        $where_parts = ["kendaraan_id = ?", "tanggal_mulai = ?", "tanggal_selesai = ?"];
        $types_chk = 'iss';
        $values_chk = [$kend, $tgl_mulai, $tgl_selesai];

        if (!empty($applicant_candidates)) {
            $or_parts = [];
            foreach ($applicant_candidates as $c) {
                $or_parts[] = "$c = ?";
                $types_chk .= 'i';
                $values_chk[] = $penerima;
            }
            $where_parts[] = '(' . implode(' OR ', $or_parts) . ')';
        }

        $sql_chk = "SELECT id FROM peminjaman_terjadwal WHERE " . implode(' AND ', $where_parts) . " LIMIT 1";
        $chk = $conn->prepare($sql_chk);
        if ($chk) {
            // bind dynamically
            $bind_params = [];
            $bind_params[] = & $types_chk;
            for ($i = 0; $i < count($values_chk); $i++) { $bind_params[] = & $values_chk[$i]; }
            call_user_func_array(array($chk, 'bind_param'), $bind_params);
            if ($chk->execute()) {
                $rchk = $chk->get_result();
                if ($rchk && $rchk->num_rows > 0) $already = true;
            }
            $chk->close();
        }
    }

    if ($already) {
        echo "Skipping surat_id={$surat_id} because matching peminjaman_terjadwal already exists\n";
        $skipped++;
        continue;
    }

    // If peminjaman_terjadwal table exists, build and insert
    $pt_id = null;
    if ($has_pt) {
        $cols_info_q = $conn->query("SHOW COLUMNS FROM peminjaman_terjadwal");
        $cols_info = $cols_info_q ? $cols_info_q->fetch_all(MYSQLI_ASSOC) : [];
        $cols = array_column($cols_info, 'Field');

        // Pick applicant column
        $applicant_col = null;
        foreach (['pemohon_id','peminjam_id','pengguna_id','user_id'] as $c) {
            if (in_array($c, $cols)) { $applicant_col = $c; break; }
        }

        $desired_map = [];
        if ($applicant_col) $desired_map[$applicant_col] = ['type'=>'i','value'=>$penerima];
        if (in_array('kendaraan_id', $cols)) $desired_map['kendaraan_id'] = ['type'=>'i','value'=>$kend];
        if (in_array('keperluan', $cols)) $desired_map['keperluan'] = ['type'=>'s','value'=>$row['keperluan'] ?? 'Surat Tugas: ' . ($row['nomor_surat'] ?? '')];
        if (in_array('tujuan', $cols)) $desired_map['tujuan'] = ['type'=>'s','value'=>$row['tujuan'] ?? ''];
        if (in_array('tanggal_mulai', $cols)) $desired_map['tanggal_mulai'] = ['type'=>'s','value'=>$tgl_mulai];
        if (in_array('tanggal_selesai', $cols)) $desired_map['tanggal_selesai'] = ['type'=>'s','value'=>$tgl_selesai];
        if (in_array('status', $cols)) $desired_map['status'] = ['type'=>'s','value'=>'approved'];
        if (in_array('created_by', $cols)) $desired_map['created_by'] = ['type'=>'i','value'=>$row['created_by'] ?? null];

        $insert_cols = [];
        $types_pt = '';
        $values_pt = [];
        foreach ($desired_map as $col => $meta) {
            if (in_array($col, $cols)) {
                $insert_cols[] = $col;
                $types_pt .= $meta['type'];
                $values_pt[] = $meta['value'];
            }
        }

        if (!empty($insert_cols)) {
            $placeholders = array_fill(0, count($insert_cols), '?');
            $sql_pt = "INSERT INTO peminjaman_terjadwal (" . implode(', ', $insert_cols) . ") VALUES (" . implode(', ', $placeholders) . ")";
            $ins_pt = $conn->prepare($sql_pt);
            if ($ins_pt) {
                // bind params by reference
                $bind_params = [];
                $bind_params[] = & $types_pt;
                for ($i = 0; $i < count($values_pt); $i++) { $bind_params[] = & $values_pt[$i]; }
                call_user_func_array(array($ins_pt, 'bind_param'), $bind_params);
                if ($ins_pt->execute()) {
                    $pt_id = $conn->insert_id;
                    echo "Inserted peminjaman_terjadwal id={$pt_id} for surat_id={$surat_id}\n";
                } else {
                    echo "Failed to insert peminjaman_terjadwal for surat_id={$surat_id}: " . $ins_pt->error . "\n";
                    $errors++;
                }
                $ins_pt->close();
            } else {
                echo "Prepare failed for peminjaman_terjadwal insert: " . $conn->error . "\n";
                $errors++;
            }
        } else {
            echo "No insertable columns found in peminjaman_terjadwal for surat_id={$surat_id}\n";
            $skipped++;
            continue;
        }
    }

    // Create jadwal_kendaraan entry referencing this peminjaman_terjadwal (if available)
    if ($has_jk) {
        if ($pt_id) {
            $keterangan = "Migrasi dari surat_tugas ID: {$surat_id}";
            $stmt_jk = $conn->prepare("INSERT INTO jadwal_kendaraan (kendaraan_id, tipe_penggunaan, referensi_id, pengguna_id, tanggal_mulai, tanggal_selesai, status, keterangan) VALUES (?, 'peminjaman_terjadwal', ?, ?, ?, ?, 'aktif', ?)");
            if ($stmt_jk) {
                $stmt_jk->bind_param('iiisss', $kend, $pt_id, $penerima, $tgl_mulai, $tgl_selesai, $keterangan);
                $stmt_jk->execute();
                $stmt_jk->close();
                echo "Inserted jadwal_kendaraan for peminjaman_terjadwal id={$pt_id}\n";
            } else {
                echo "Failed to prepare jadwal_kendaraan insert: " . $conn->error . "\n";
            }
        } else {
            // If we couldn't create peminjaman_terjadwal but jadwal_kendaraan for surat may already exist; skip
        }
    }

    // Upsert pengguna_kendaraan
    if ($has_pk) {
        $up = $conn->prepare("UPDATE pengguna_kendaraan SET status = 'Digunakan', tanggal_mulai = ?, tanggal_selesai = ? WHERE pengguna_id = ? AND kendaraan_id = ?");
        if ($up) {
            $up->bind_param('ssii', $tgl_mulai, $tgl_selesai, $penerima, $kend);
            $up->execute();
            $affected = $up->affected_rows;
            $up->close();
            if ($affected === 0) {
                $ins = $conn->prepare("INSERT INTO pengguna_kendaraan (pengguna_id, kendaraan_id, status, tanggal_mulai, tanggal_selesai, created_at) VALUES (?, ?, 'Digunakan', ?, ?, NOW())");
                if ($ins) {
                    $ins->bind_param('iiss', $penerima, $kend, $tgl_mulai, $tgl_selesai);
                    $ins->execute();
                    $ins->close();
                }
            }
        }
    }

    $inserted++;
}

echo "\nSummary:\n";
echo "Total surat considered: {$total}\n";
echo "Inserted: {$inserted}\n";
echo "Skipped: {$skipped}\n";
echo "Errors: {$errors}\n";

exit(0);
