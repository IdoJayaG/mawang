<?php
// Lightweight, self-contained maintenance runner for status transitions
// Can be required from pages or invoked from CLI via scripts/run_status_transitions.php
function run_status_transitions($conn, $current_user_id = null) {
    // Internal helper to check columns existence
    $has_cols = function($table, array $cols) use ($conn) {
        $res = $conn->query("SHOW COLUMNS FROM `" . $conn->real_escape_string($table) . "`");
        if (!$res) return false;
        $found = [];
        while ($r = $res->fetch_assoc()) $found[] = $r['Field'];
        foreach ($cols as $c) if (!in_array($c, $found)) return false;
        return true;
    };

    // Simple logger for debugging transitions
    $log = function($msg) {
        $path = __DIR__ . '/../logs/run_status_transitions_debug.log';
        @file_put_contents($path, date('c') . ' ' . $msg . PHP_EOL, FILE_APPEND);
    };

    // 1) Promote Disetujui -> Dalam Perjalanan when tanggal_berangkat <= NOW()
    if ($stmt = $conn->prepare("SELECT id, nomor_surat, kendaraan_id FROM surat_tugas WHERE status = 'Disetujui' AND tanggal_berangkat <= NOW()")) {
        $stmt->execute();
        $res = $stmt->get_result();
        while ($row = $res->fetch_assoc()) {
            $sid = (int)$row['id'];
            $upd = $conn->prepare("UPDATE surat_tugas SET status = 'Dalam Perjalanan', updated_by = ?, updated_at = NOW() WHERE id = ?");
            if ($upd) {
                $uBy = $current_user_id ?? 0;
                $upd->bind_param('ii', $uBy, $sid);
                $upd->execute();
                $upd->close();
                $log('Promoted surat_tugas id ' . $sid . ' nomor_surat ' . $row['nomor_surat'] . " to Dalam Perjalanan (by user: " . $uBy . ")");
            }

            // if peminjaman_kendaraan exists for this nomor_surat, mark it Ongoing
            if ($has_cols('peminjaman_kendaraan', ['nomor_surat','status'])) {
                $mark = $conn->prepare("UPDATE peminjaman_kendaraan SET status = 'Ongoing', updated_at = NOW() WHERE nomor_surat = ? AND status = 'Approved'");
                if ($mark) {
                    $mark->bind_param('s', $row['nomor_surat']);
                    $mark->execute();
                    $aff = $mark->affected_rows;
                    $log('Updated peminjaman_kendaraan -> Ongoing for nomor_surat ' . $row['nomor_surat'] . ' affected=' . $aff);
                    $mark->close();
                }
            }

            // update kendaraan status_peminjaman to 'Dipinjam' if column exists
            if ($has_cols('kendaraan', ['id','status_peminjaman'])) {
                $kupd = $conn->prepare("UPDATE kendaraan SET status_peminjaman = 'Dipinjam', updated_at = NOW() WHERE id = ?");
                if ($kupd) {
                    $kid = (int)$row['kendaraan_id'];
                    $kupd->bind_param('i', $kid);
                    $kupd->execute();
                    $affk = $kupd->affected_rows;
                    $log('Set kendaraan.id ' . $kid . ' status_peminjaman=Dipinjam (affected=' . $affk . ')');
                    $kupd->close();
                }
            }
        }
        $stmt->close();
    }

    // 2) Finish trips: mark approved or in-progress surat_tugas as Selesai when return time has passed
    // Compute effective end datetime from tanggal_kembali + optional waktu_kembali (if parseable); default to end-of-day
    if ($stmt2 = $conn->prepare("SELECT id, nomor_surat, kendaraan_id FROM surat_tugas WHERE status IN ('Dalam Perjalanan','Disetujui') AND tanggal_kembali IS NOT NULL")) {
        $stmt2->execute();
        $res2 = $stmt2->get_result();
        while ($r = $res2->fetch_assoc()) {
            $sid = (int)$r['id'];

            // fetch fields needed to compute an accurate end datetime
            $sd_check = null;
            // select only columns that exist to avoid SQL errors on older schemas
            $selectCols = ['tanggal_berangkat', 'tanggal_kembali'];
            if ($has_cols('surat_tugas', ['waktu_berangkat'])) { $selectCols[] = 'waktu_berangkat'; }
            if ($has_cols('surat_tugas', ['waktu_kembali'])) { $selectCols[] = 'waktu_kembali'; }
            $selectSql = "SELECT " . implode(', ', $selectCols) . " FROM surat_tugas WHERE id = ? LIMIT 1";
            if ($stc = $conn->prepare($selectSql)) {
                $stc->bind_param('i', $sid);
                $stc->execute();
                $sd_check = $stc->get_result()->fetch_assoc();
                $stc->close();
            }

            $end_date = $sd_check['tanggal_kembali'] ?? null;
            if (empty($end_date)) { $log('Skipping surat_tugas id ' . $sid . ': no tanggal_kembali'); continue; }

            $timePart = trim($sd_check['waktu_kembali'] ?? '');
            $end_time = '23:59:59';
            if ($timePart) {
                if (preg_match('/\b([0-2]?\d)[:\.]?([0-5]\d)(?:[:\.]?([0-5]\d))?\b/', $timePart, $m)) {
                    $hh = (int)$m[1]; $mi = (int)$m[2]; $ss = isset($m[3]) ? (int)$m[3] : 0;
                    $hh = max(0, min(23, $hh)); $mi = max(0, min(59, $mi)); $ss = max(0, min(59, $ss));
                    $end_time = sprintf('%02d:%02d:%02d', $hh, $mi, $ss);
                }
            }

            $end_dt_str = $end_date . ' ' . $end_time;
            $end_ts = @strtotime($end_dt_str);
            if ($end_ts === false || $end_ts === null) { $log('Skipping surat_tugas id ' . $sid . ': invalid end datetime ' . $end_dt_str); continue; }
            if ($end_ts > time()) { $log('Skipping surat_tugas id ' . $sid . ': end not reached ' . $end_dt_str); continue; }

            // now finalize -- end time reached
            $upd2 = $conn->prepare("UPDATE surat_tugas SET status = 'Selesai', updated_by = ?, updated_at = NOW() WHERE id = ?");
            if ($upd2) {
                $uBy = $current_user_id ?? 0;
                $upd2->bind_param('ii', $uBy, $sid);
                $upd2->execute();
                $upd2->close();
                $log('Finalized surat_tugas id ' . $sid . ' nomor_surat ' . $r['nomor_surat'] . " to Selesai (by user: " . $uBy . ")");
            }

            if ($has_cols('peminjaman_kendaraan', ['nomor_surat','status'])) {
                $mark2 = $conn->prepare("UPDATE peminjaman_kendaraan SET status = 'completed', updated_at = NOW() WHERE nomor_surat = ? AND status IN ('Approved','Ongoing')");
                if ($mark2) {
                    $mark2->bind_param('s', $r['nomor_surat']);
                    $mark2->execute();
                    $aff2 = $mark2->affected_rows;
                    $log('Updated peminjaman_kendaraan -> completed for nomor_surat ' . $r['nomor_surat'] . ' affected=' . $aff2);
                    $mark2->close();
                }
            }

            if ($has_cols('kendaraan', ['id','status_peminjaman'])) {
                $kupd2 = $conn->prepare("UPDATE kendaraan SET status_peminjaman = 'Tersedia', updated_at = NOW() WHERE id = ?");
                if ($kupd2) {
                    $kid = (int)$r['kendaraan_id'];
                    $kupd2->bind_param('i', $kid);
                    $kupd2->execute();
                    $affk2 = $kupd2->affected_rows;
                    $log('Set kendaraan.id ' . $kid . ' status_peminjaman=Tersedia (affected=' . $affk2 . ')');
                    $kupd2->close();
                }
            }

            // Insert a minimal riwayat_pemakaian record when a surat_tugas finishes (if table exists)
            try {
                $tbl = $conn->query("SHOW TABLES LIKE 'riwayat_pemakaian'");
                if ($tbl && $tbl->num_rows > 0) {
                    // Fetch surat_tugas data to map into riwayat_pemakaian
                    $st = $conn->prepare("SELECT kendaraan_id, pengguna_id, tanggal_berangkat, tanggal_kembali, created_by, keperluan, driver_id FROM surat_tugas WHERE id = ? LIMIT 1");
                    if ($st) {
                        $st->bind_param('i', $sid);
                        $st->execute();
                        $sd = $st->get_result()->fetch_assoc();
                        $st->close();

                        if ($sd) {
                            $chkDate = $sd['tanggal_kembali'] ?: $sd['tanggal_berangkat'];
                            if ($chkDate) {
                                // avoid duplicate
                                $dup = false;
                                $chk = $conn->prepare("SELECT COUNT(*) c FROM riwayat_pemakaian WHERE kendaraan_id = ? AND tanggal = ? LIMIT 1");
                                if ($chk) {
                                    $kid = (int)$sd['kendaraan_id'];
                                    $chk->bind_param('is', $kid, $chkDate);
                                    $chk->execute();
                                    $cres = $chk->get_result()->fetch_assoc();
                                    $dup = ((int)($cres['c'] ?? 0)) > 0;
                                    $chk->close();
                                }

                                if (!$dup) {
                                    $colsRes = $conn->query("SHOW COLUMNS FROM riwayat_pemakaian");
                                    $rpCols = [];
                                    while ($c = $colsRes->fetch_assoc()) { $rpCols[] = $c['Field']; }

                                    $insertCols = [];
                                    $types = '';
                                    $vals = [];

                                    if (in_array('kendaraan_id', $rpCols, true)) { $insertCols[] = 'kendaraan_id'; $types .= 'i'; $vals[] = (int)$sd['kendaraan_id']; }
                                    if (in_array('user_id', $rpCols, true)) { $insertCols[] = 'user_id'; $types .= 'i'; $vals[] = !empty($sd['pengguna_id']) ? (int)$sd['pengguna_id'] : (int)($sd['created_by'] ?? 0); }
                                    if (in_array('tanggal', $rpCols, true)) { $insertCols[] = 'tanggal'; $types .= 's'; $vals[] = $chkDate; }

                                    // Preserve keperluan from surat_tugas when riwayat_pemakaian supports it
                                    if (in_array('keperluan', $rpCols, true)) {
                                        $insertCols[] = 'keperluan';
                                        $types .= 's';
                                        $vals[] = isset($sd['keperluan']) ? $sd['keperluan'] : '';
                                    }

                                    // If the surat_tugas included a driver and the riwayat_pemakaian table has driver_id, persist it
                                    if (in_array('driver_id', $rpCols, true) && !empty($sd['driver_id'])) {
                                        $insertCols[] = 'driver_id';
                                        $types .= 'i';
                                        $vals[] = (int)$sd['driver_id'];
                                    }

                                    if (!empty($insertCols)) {
                                        $placeholders = array_fill(0, count($insertCols), '?');
                                        $sqlIns = "INSERT INTO riwayat_pemakaian (" . implode(', ', $insertCols) . ") VALUES (" . implode(', ', $placeholders) . ")";
                                        $ins = $conn->prepare($sqlIns);
                                        if ($ins) {
                                            $bindArgs = [];
                                            $bindArgs[] = & $types;
                                            for ($i = 0; $i < count($vals); $i++) { $bindArgs[] = & $vals[$i]; }
                                            call_user_func_array([$ins, 'bind_param'], $bindArgs);
                                            $ins->execute();
                                            $lastId = $conn->insert_id;
                                            $log('Inserted riwayat_pemakaian id=' . $lastId . ' kendaraan_id=' . ($sd['kendaraan_id'] ?? 'NULL'));
                                            $ins->close();
                                        }
                                    }
                                }
                            }
                        }
                    }
                }
            } catch (Throwable $e) {
                if (function_exists('file_put_contents')) {
                    @file_put_contents(__DIR__ . '/../logs/run_status_transitions_history_error.log', date('c') . ' ' . $e->getMessage() . "\n", FILE_APPEND);
                }
            }

            // Insert laporan_perjalanan record when surat_tugas finishes (if table exists)
            try {
                $tblLp = $conn->query("SHOW TABLES LIKE 'laporan_perjalanan'");
                if ($tblLp && $tblLp->num_rows > 0) {
                    // Fetch full surat_tugas row to map into laporan_perjalanan
                    $stlp = $conn->prepare("SELECT * FROM surat_tugas WHERE id = ? LIMIT 1");
                    $stdata = null;
                    if ($stlp) {
                        $stlp->bind_param('i', $sid);
                        $stlp->execute();
                        $stdata = $stlp->get_result()->fetch_assoc();
                        $stlp->close();
                    }

                    if ($stdata) {
                        $tanggal_lp = !empty($stdata['tanggal_kembali']) ? $stdata['tanggal_kembali'] : ($stdata['tanggal_berangkat'] ?? null);
                        if ($tanggal_lp) {
                            $kend_id = (int)($stdata['kendaraan_id'] ?? 0);
                            if ($kend_id > 0) {
                                // Determine pengguna (prefer surat_tugas.pengguna_id, fallback to created_by or kendaraan.pengguna_id)
                                $peng_id = !empty($stdata['pengguna_id']) ? (int)$stdata['pengguna_id'] : (!empty($stdata['created_by']) ? (int)$stdata['created_by'] : 0);
                                if ($peng_id <= 0) {
                                    $sv = $conn->prepare("SELECT pengguna_id FROM kendaraan WHERE id = ? LIMIT 1");
                                    if ($sv) {
                                        $sv->bind_param('i', $kend_id);
                                        $sv->execute();
                                        $pv = $sv->get_result()->fetch_assoc();
                                        $sv->close();
                                        $peng_id = !empty($pv['pengguna_id']) ? (int)$pv['pengguna_id'] : 0;
                                    }
                                }

                                $uraian = trim($stdata['laporan_perjalanan'] ?? $stdata['keperluan'] ?? $stdata['perihal'] ?? ('Surat Tugas ' . ($stdata['nomor_surat'] ?? $sid)));
                                $route = trim($stdata['tujuan'] ?? $stdata['tempat_tugas'] ?? '');
                                $jarak_km = null;
                                if (isset($stdata['estimasi_km']) && $stdata['estimasi_km'] !== '' && is_numeric($stdata['estimasi_km'])) {
                                    $jarak_km = (double)$stdata['estimasi_km'];
                                }

                                // avoid duplicate laporan for same kendaraan+tanggal
                                $chkLp = $conn->prepare("SELECT COUNT(*) c FROM laporan_perjalanan WHERE kendaraan_id = ? AND tanggal = ? LIMIT 1");
                                $dupLp = false;
                                if ($chkLp) {
                                    $chkLp->bind_param('is', $kend_id, $tanggal_lp);
                                    $chkLp->execute();
                                    $cres = $chkLp->get_result()->fetch_assoc();
                                    $dupLp = ((int)($cres['c'] ?? 0)) > 0;
                                    $chkLp->close();
                                }

                                if (!$dupLp) {
                                    if ($jarak_km === null) {
                                        if ($peng_id > 0) {
                                            $ins = $conn->prepare("INSERT INTO laporan_perjalanan (tanggal, kendaraan_id, pengguna_id, uraian_kegiatan, route, jarak_km, created_at) VALUES (?, ?, ?, ?, ?, NULL, NOW())");
                                            if ($ins) {
                                                $ins->bind_param('siiss', $tanggal_lp, $kend_id, $peng_id, $uraian, $route);
                                                $ins->execute();
                                                $lid = $conn->insert_id;
                                                $ins->close();
                                                $log('Inserted laporan_perjalanan id=' . $lid . ' kendaraan_id=' . $kend_id);
                                            }
                                        } else {
                                            $ins = $conn->prepare("INSERT INTO laporan_perjalanan (tanggal, kendaraan_id, pengguna_id, uraian_kegiatan, route, jarak_km, created_at) VALUES (?, ?, NULL, ?, ?, NULL, NOW())");
                                            if ($ins) {
                                                $ins->bind_param('siss', $tanggal_lp, $kend_id, $uraian, $route);
                                                $ins->execute();
                                                $lid = $conn->insert_id;
                                                $ins->close();
                                                $log('Inserted laporan_perjalanan (no pengguna) id=' . $lid . ' kendaraan_id=' . $kend_id);
                                            }
                                        }
                                    } else {
                                        if ($peng_id > 0) {
                                            $ins = $conn->prepare("INSERT INTO laporan_perjalanan (tanggal, kendaraan_id, pengguna_id, uraian_kegiatan, route, jarak_km, created_at) VALUES (?, ?, ?, ?, ?, ?, NOW())");
                                            if ($ins) {
                                                $kms = (double)$jarak_km;
                                                $ins->bind_param('siissd', $tanggal_lp, $kend_id, $peng_id, $uraian, $route, $kms);
                                                $ins->execute();
                                                $lid = $conn->insert_id;
                                                $ins->close();
                                                $log('Inserted laporan_perjalanan id=' . $lid . ' kendaraan_id=' . $kend_id . ' jarak=' . $kms);
                                            }
                                        } else {
                                            $ins = $conn->prepare("INSERT INTO laporan_perjalanan (tanggal, kendaraan_id, pengguna_id, uraian_kegiatan, route, jarak_km, created_at) VALUES (?, ?, NULL, ?, ?, ?, NOW())");
                                            if ($ins) {
                                                $kms = (double)$jarak_km;
                                                $ins->bind_param('sissd', $tanggal_lp, $kend_id, $uraian, $route, $kms);
                                                $ins->execute();
                                                $lid = $conn->insert_id;
                                                $ins->close();
                                                $log('Inserted laporan_perjalanan (no pengguna) id=' . $lid . ' kendaraan_id=' . $kend_id . ' jarak=' . $kms);
                                            }
                                        }
                                    }
                                }
                            }
                        }
                    }
                }
            } catch (Throwable $e) {
                @file_put_contents(__DIR__ . '/../logs/run_status_transitions_history_error.log', date('c') . ' ' . $e->getMessage() . "\n", FILE_APPEND);
            }
        }
        $stmt2->close();
    }
    // 3) Peminjaman Kendaraan: promote approved -> ongoing when start time reached
    if ($has_cols('peminjaman_kendaraan', ['id','status','tanggal_mulai','tanggal_selesai','kendaraan_id'])) {
        if ($stmt3 = $conn->prepare("SELECT id, kendaraan_id FROM peminjaman_kendaraan WHERE LOWER(status)='approved' AND tanggal_mulai IS NOT NULL AND tanggal_mulai <= NOW()")) {
            $stmt3->execute();
            $res3 = $stmt3->get_result();
            while ($row = $res3->fetch_assoc()) {
                $pid = (int)$row['id'];
                if ($upd3 = $conn->prepare("UPDATE peminjaman_kendaraan SET status='ongoing', updated_at = NOW(), updated_by = ? WHERE id = ?")) {
                    $uBy = $current_user_id ?? 0;
                    $upd3->bind_param('ii', $uBy, $pid);
                    $upd3->execute();
                    $upd3->close();
                    $log('Promoted peminjaman_kendaraan id ' . $pid . ' to ongoing (by user: ' . $uBy . ')');
                }
                // set kendaraan Dipinjam
                if ($has_cols('kendaraan',['id','status_peminjaman'])) {
                    if ($kupd3 = $conn->prepare("UPDATE kendaraan SET status_peminjaman='Dipinjam', updated_at = NOW() WHERE id = ?")) {
                        $kid = (int)$row['kendaraan_id'];
                        $kupd3->bind_param('i',$kid);
                        $kupd3->execute();
                        $affk3 = $kupd3->affected_rows;
                        $log('Set kendaraan.id ' . $kid . ' status_peminjaman=Dipinjam (affected=' . $affk3 . ')');
                        $kupd3->close();
                    }
                }
            }
            $stmt3->close();
        }

        // 4) Peminjaman Kendaraan: complete ongoing when end time passed
        if ($stmt4 = $conn->prepare("SELECT id, kendaraan_id FROM peminjaman_kendaraan WHERE LOWER(status)='ongoing' AND tanggal_selesai IS NOT NULL AND tanggal_selesai < NOW()")) {
            $stmt4->execute();
            $res4 = $stmt4->get_result();
            while ($row = $res4->fetch_assoc()) {
                $pid = (int)$row['id'];
                if ($upd4 = $conn->prepare("UPDATE peminjaman_kendaraan SET status='completed', updated_at = NOW(), updated_by = ? WHERE id = ?")) {
                    $uBy = $current_user_id ?? 0;
                    $upd4->bind_param('ii', $uBy, $pid);
                    $upd4->execute();
                    $upd4->close();
                    $log('Completed peminjaman_kendaraan id ' . $pid . ' (by user: ' . $uBy . ')');
                }
                // Free vehicle IF no other ongoing peminjaman OR surat_tugas still using it
                $kid = (int)$row['kendaraan_id'];
                $still_in_use = false;
                // check other ongoing peminjaman
                if ($chk = $conn->prepare("SELECT COUNT(*) c FROM peminjaman_kendaraan WHERE kendaraan_id=? AND LOWER(status) IN ('ongoing')")) {
                    $chk->bind_param('i',$kid);
                    $chk->execute();
                    $r = $chk->get_result()->fetch_assoc();
                    if (($r['c'] ?? 0) > 0) $still_in_use = true;
                    $log('peminjaman_kendaraan ongoing count for kendaraan_id ' . $kid . ' = ' . ($r['c'] ?? 0));
                    $chk->close();
                }
                // check surat_tugas dalam perjalanan
                if (!$still_in_use && $has_cols('surat_tugas',['kendaraan_id','status'])) {
                    if ($chk2 = $conn->prepare("SELECT COUNT(*) c FROM surat_tugas WHERE kendaraan_id=? AND status='Dalam Perjalanan'")) {
                        $chk2->bind_param('i',$kid);
                        $chk2->execute();
                        $r2 = $chk2->get_result()->fetch_assoc();
                        if (($r2['c'] ?? 0) > 0) $still_in_use = true;
                        $log('surat_tugas Dalam Perjalanan count for kendaraan_id ' . $kid . ' = ' . ($r2['c'] ?? 0));
                        $chk2->close();
                    }
                }
                if (!$still_in_use && $has_cols('kendaraan',['id','status_peminjaman'])) {
                    if ($kupd4 = $conn->prepare("UPDATE kendaraan SET status_peminjaman='Tersedia', updated_at = NOW() WHERE id = ?")) {
                        $kupd4->bind_param('i',$kid);
                        $kupd4->execute();
                        $affk4 = $kupd4->affected_rows;
                        $log('Set kendaraan.id ' . $kid . ' status_peminjaman=Tersedia (affected=' . $affk4 . ')');
                        $kupd4->close();
                    }
                }
            }
            $stmt4->close();
        }
    }

        // 5) Defensive sync: if any surat_tugas currently Disetujui/Dalam Perjalanan, ensure kendaraan is marked 'Dipinjam'
        try {
            if ($has_cols('surat_tugas', ['kendaraan_id','status']) && $has_cols('kendaraan', ['id','status_peminjaman'])) {
                // Set Dipinjam for any vehicle referenced by active surat_tugas
                $sqlSyncSt = "UPDATE kendaraan k
                               JOIN (SELECT DISTINCT kendaraan_id FROM surat_tugas WHERE status IN ('Disetujui','Dalam Perjalanan')) st
                                 ON st.kendaraan_id = k.id
                               SET k.status_peminjaman = 'Dipinjam', k.updated_at = NOW()";
                $conn->query($sqlSyncSt);
            }
        } catch (Exception $e) { /* ignore sync errors */ }

        // 6) Defensive sync: if any peminjaman_kendaraan Approved/Ongoing, ensure kendaraan is marked 'Dipinjam'
        try {
            if ($has_cols('peminjaman_kendaraan', ['kendaraan_id','status']) && $has_cols('kendaraan', ['id','status_peminjaman'])) {
                $sqlSyncPk = "UPDATE kendaraan k
                               JOIN (
                                   SELECT DISTINCT kendaraan_id 
                                   FROM peminjaman_kendaraan 
                                   WHERE LOWER(status) IN ('approved','ongoing')
                               ) p ON p.kendaraan_id = k.id
                               SET k.status_peminjaman = 'Dipinjam', k.updated_at = NOW()";
                $conn->query($sqlSyncPk);
            }
        } catch (Exception $e) { /* ignore sync errors */ }
}

?>
