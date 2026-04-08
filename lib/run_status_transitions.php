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
            }

            // if peminjaman_kendaraan exists for this nomor_surat, mark it Ongoing
            if ($has_cols('peminjaman_kendaraan', ['nomor_surat','status'])) {
                $mark = $conn->prepare("UPDATE peminjaman_kendaraan SET status = 'Ongoing', updated_at = NOW() WHERE nomor_surat = ? AND status = 'Approved'");
                if ($mark) {
                    $mark->bind_param('s', $row['nomor_surat']);
                    $mark->execute();
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
                    $kupd->close();
                }
            }
        }
        $stmt->close();
    }

    // 2) Finish trips: Dalam Perjalanan -> Selesai after the return day has finished
    if ($stmt2 = $conn->prepare("SELECT id, nomor_surat, kendaraan_id FROM surat_tugas WHERE status = 'Dalam Perjalanan' AND tanggal_kembali IS NOT NULL AND DATE(tanggal_kembali) < CURDATE()")) {
        $stmt2->execute();
        $res2 = $stmt2->get_result();
        while ($r = $res2->fetch_assoc()) {
            $sid = (int)$r['id'];
            $upd2 = $conn->prepare("UPDATE surat_tugas SET status = 'Selesai', updated_by = ?, updated_at = NOW() WHERE id = ?");
            if ($upd2) {
                $uBy = $current_user_id ?? 0;
                $upd2->bind_param('ii', $uBy, $sid);
                $upd2->execute();
                $upd2->close();
            }

            if ($has_cols('peminjaman_kendaraan', ['nomor_surat','status'])) {
                $mark2 = $conn->prepare("UPDATE peminjaman_kendaraan SET status = 'completed', updated_at = NOW() WHERE nomor_surat = ? AND status IN ('Approved','Ongoing')");
                if ($mark2) {
                    $mark2->bind_param('s', $r['nomor_surat']);
                    $mark2->execute();
                    $mark2->close();
                }
            }

            if ($has_cols('kendaraan', ['id','status_peminjaman'])) {
                $kupd2 = $conn->prepare("UPDATE kendaraan SET status_peminjaman = 'Tersedia', updated_at = NOW() WHERE id = ?");
                if ($kupd2) {
                    $kid = (int)$r['kendaraan_id'];
                    $kupd2->bind_param('i', $kid);
                    $kupd2->execute();
                    $kupd2->close();
                }
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
                }
                // set kendaraan Dipinjam
                if ($has_cols('kendaraan',['id','status_peminjaman'])) {
                    if ($kupd3 = $conn->prepare("UPDATE kendaraan SET status_peminjaman='Dipinjam', updated_at = NOW() WHERE id = ?")) {
                        $kid = (int)$row['kendaraan_id'];
                        $kupd3->bind_param('i',$kid);
                        $kupd3->execute();
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
                    $chk->close();
                }
                // check surat_tugas dalam perjalanan
                if (!$still_in_use && $has_cols('surat_tugas',['kendaraan_id','status'])) {
                    if ($chk2 = $conn->prepare("SELECT COUNT(*) c FROM surat_tugas WHERE kendaraan_id=? AND status='Dalam Perjalanan'")) {
                        $chk2->bind_param('i',$kid);
                        $chk2->execute();
                        $r2 = $chk2->get_result()->fetch_assoc();
                        if (($r2['c'] ?? 0) > 0) $still_in_use = true;
                        $chk2->close();
                    }
                }
                if (!$still_in_use && $has_cols('kendaraan',['id','status_peminjaman'])) {
                    if ($kupd4 = $conn->prepare("UPDATE kendaraan SET status_peminjaman='Tersedia', updated_at = NOW() WHERE id = ?")) {
                        $kupd4->bind_param('i',$kid);
                        $kupd4->execute();
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
