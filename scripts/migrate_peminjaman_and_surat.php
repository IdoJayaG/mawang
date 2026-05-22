<?php
/**
 * Migrasi gabungan:
 * 1) Temukan semua record di `peminjaman_kendaraan` dengan status 'approved'/'Disetujui' yang belum terhubung ke `surat_tugas` dan buat `surat_tugas` untuk mereka (idempotent).
 * 2) Temukan semua `surat_tugas` dengan status 'Selesai' dan buat `laporan_perjalanan` jika belum ada (idempotent).
 * Usage (CLI):
 *   php scripts/migrate_peminjaman_and_surat.php
 */

require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../config/db.php';

if (php_sapi_name() !== 'cli') {
    echo "This script is intended to be run from CLI only.\n";
    exit(1);
}

function table_exists($mysqli, $table) {
    $table = $mysqli->real_escape_string($table);
    $res = $mysqli->query("SHOW TABLES LIKE '{$table}'");
    return $res && $res->num_rows > 0;
}

function get_columns($mysqli, $table) {
    $cols = [];
    $table = $mysqli->real_escape_string($table);
    $res = $mysqli->query("SHOW COLUMNS FROM `{$table}`");
    if ($res) while ($r = $res->fetch_assoc()) $cols[] = $r['Field'];
    return $cols;
}

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
        $next = time() % 1000;
    }
    return sprintf('ST/%03d/%s/%s', $next, $month_roman, $year);
}

echo "Step 1: Migrasi peminjaman (status approved) -> surat_tugas\n";

if (!table_exists($mysqli, 'peminjaman_kendaraan')) {
    echo "Table peminjaman_kendaraan tidak ditemukan, lewati step 1.\n";
} else {
    $pkCols = get_columns($mysqli, 'peminjaman_kendaraan');
    $hasSuratIdCol = in_array('surat_tugas_id', $pkCols, true);

    $q = "SELECT * FROM peminjaman_kendaraan WHERE LOWER(TRIM(status)) IN ('approved','disetujui')";
    $res = $mysqli->query($q);
    if (!$res) { echo "Query failed: " . $mysqli->error . "\n"; exit(1); }

    $created = 0; $skipped = 0; $errors = 0;
    while ($row = $res->fetch_assoc()) {
        $pid = (int)($row['id'] ?? 0);
        if ($pid === 0) { $skipped++; continue; }

        // If already linked, skip
        if (!empty($row['surat_tugas_id'])) { $skipped++; echo "Peminjaman {$pid} sudah terhubung ke surat_tugas={$row['surat_tugas_id']}, lewati\n"; continue; }

        // Defensive: avoid creating duplicate surat_tugas for same kendaraan+date
        $kend = (int)($row['kendaraan_id'] ?? 0);
        $berangkat = null;
        foreach (['tanggal_mulai','tanggal_berangkat','start_date','tanggal'] as $c) { if (array_key_exists($c, $row) && !empty($row[$c])) { $berangkat = $row[$c]; break; } }
        if (!$kend || !$berangkat) { $skipped++; echo "Peminjaman {$pid} lewati: missing kendaraan or tanggal_mulai\n"; continue; }

        // check existing surat_tugas for same kendaraan+tanggal_berangkat
        $chk = $mysqli->prepare("SELECT id FROM surat_tugas WHERE kendaraan_id = ? AND DATE(tanggal_berangkat) = DATE(?) LIMIT 1");
        if ($chk) { $chk->bind_param('is', $kend, $berangkat); $chk->execute(); $ex = $chk->get_result()->fetch_assoc(); $chk->close(); }
        if (!empty($ex['id'])) { $skipped++; echo "Sudah ada surat_tugas id={$ex['id']} untuk kendaraan={$kend} tanggal={$berangkat}, lewati\n"; continue; }

        // build insert data similar to ajax/update_peminjaman_status.php
        $applicant = null;
        foreach (['pemohon_id','peminjam_id','pengguna_id','user_id','created_by'] as $c) { if (array_key_exists($c, $row) && !empty($row[$c])) { $applicant = (int)$row[$c]; break; } }

        $kembali = null;
        foreach (['tanggal_selesai','tanggal_kembali','end_date'] as $c) { if (array_key_exists($c, $row) && !empty($row[$c])) { $kembali = $row[$c]; break; } }

        $stCols = get_columns($mysqli, 'surat_tugas');
        $desired = [];
        $desired['nomor_surat'] = ['t'=>'s','v'=>generate_nomor_surat($mysqli)];
        if (in_array('tanggal_surat', $stCols, true)) $desired['tanggal_surat'] = ['t'=>'s','v'=>date('Y-m-d')];
        if (in_array('kendaraan_id', $stCols, true)) $desired['kendaraan_id'] = ['t'=>'i','v'=>$kend];
        if (in_array('pengguna_id', $stCols, true) && $applicant) $desired['pengguna_id'] = ['t'=>'i','v'=>$applicant];
        $tujuan = $row['tujuan'] ?? $row['route'] ?? $row['keterangan'] ?? '';
        if (in_array('tujuan', $stCols, true)) $desired['tujuan'] = ['t'=>'s','v'=> $tujuan];
        $keperluan = $row['keperluan'] ?? $row['purpose'] ?? 'Permohonan melalui peminjaman_kendaraan';
        if (in_array('keperluan', $stCols, true)) $desired['keperluan'] = ['t'=>'s','v'=>$keperluan];
        if (in_array('tanggal_berangkat', $stCols, true) && $berangkat) $desired['tanggal_berangkat'] = ['t'=>'s','v'=>$berangkat];
        if (in_array('tanggal_kembali', $stCols, true) && $kembali) $desired['tanggal_kembali'] = ['t'=>'s','v'=>$kembali];
        if (in_array('estimasi_km', $stCols, true) && array_key_exists('estimasi_km', $row)) $desired['estimasi_km'] = ['t'=>'i','v'=>($row['estimasi_km'] ?? null)];
        if (in_array('estimasi_bbm', $stCols, true) && array_key_exists('estimasi_bbm', $row)) $desired['estimasi_bbm'] = ['t'=>'d','v'=>($row['estimasi_bbm'] ?? null)];
        if (in_array('status', $stCols, true)) $desired['status'] = ['t'=>'s','v'=>'Disetujui'];

        // prepare insert
        $insert_cols = []; $types = ''; $values = [];
        foreach ($desired as $col => $meta) {
            if (in_array($col, $stCols, true)) {
                $insert_cols[] = $col; $types .= $meta['t']; $values[] = $meta['v'];
            }
        }

        if (empty($insert_cols)) { $errors++; echo "Tidak ada kolom untuk insert surat_tugas, lewati peminjaman {$pid}\n"; continue; }

        $placeholders = implode(', ', array_fill(0, count($insert_cols), '?'));
        $sql = "INSERT INTO surat_tugas (" . implode(', ', $insert_cols) . ") VALUES (" . $placeholders . ")";
        $ins = $mysqli->prepare($sql);
        if ($ins) {
            $bind = [];
            $bind[] = & $types;
            for ($i=0;$i<count($values);$i++) $bind[] = & $values[$i];
            call_user_func_array([$ins, 'bind_param'], $bind);
            if ($ins->execute()) {
                $new_id = $mysqli->insert_id;
                $created++;
                echo "Created surat_tugas id={$new_id} from peminjaman {$pid}\n";
                if ($hasSuratIdCol) {
                    $upd = $mysqli->prepare("UPDATE peminjaman_kendaraan SET surat_tugas_id = ? WHERE id = ?");
                    if ($upd) { $upd->bind_param('ii', $new_id, $pid); $upd->execute(); $upd->close(); }
                }
            } else {
                $errors++; echo "Insert surat_tugas gagal untuk peminjaman {$pid}: " . $ins->error . "\n";
            }
            $ins->close();
        } else {
            $errors++; echo "Prepare insert surat_tugas gagal: " . $mysqli->error . "\n";
        }
    }

    echo "Step 1 done. Created={$created}, Skipped={$skipped}, Errors={$errors}\n";
}

echo "Step 2: Migrasi surat_tugas (status='Selesai') -> laporan_perjalanan\n";

// Reuse the logic from migrate_surat_selesai_to_laporan.php
if (!table_exists($mysqli, 'surat_tugas') || !table_exists($mysqli, 'laporan_perjalanan')) {
    echo "Table surat_tugas atau laporan_perjalanan tidak ditemukan, lewati step 2.\n";
    exit(0);
}

$q2 = "SELECT id, kendaraan_id, pengguna_id, tanggal_berangkat, estimasi_km, nomor_surat, tujuan, keperluan, laporan_perjalanan FROM surat_tugas WHERE LOWER(TRIM(status)) = 'selesai'";
$res2 = $mysqli->query($q2);
if (!$res2) { echo "Query failed: " . $mysqli->error . "\n"; exit(1); }

$inserted = 0; $skipped2 = 0; $errors2 = 0;
while ($s = $res2->fetch_assoc()) {
    $sid = (int)$s['id'];
    $kend = isset($s['kendaraan_id']) ? (int)$s['kendaraan_id'] : 0;
    $tanggal = trim((string)($s['tanggal_berangkat'] ?? ''));
    if (!$kend || $tanggal === '') { $skipped2++; echo "Skip surat_id={$sid}: missing kendaraan or tanggal_berangkat\n"; continue; }

    $chk = $mysqli->prepare("SELECT id FROM laporan_perjalanan WHERE kendaraan_id = ? AND DATE(tanggal) = DATE(?) LIMIT 1");
    if (!$chk) { echo "Prepare failed: " . $mysqli->error . "\n"; $errors2++; continue; }
    $chk->bind_param('is', $kend, $tanggal);
    $chk->execute();
    $r = $chk->get_result()->fetch_assoc();
    $chk->close();
    if ($r && !empty($r['id'])) { $skipped2++; echo "Exists laporan for kendaraan={$kend} date={$tanggal}, skipping\n"; continue; }

    $uraian = trim($s['laporan_perjalanan'] ?? $s['keperluan'] ?? $s['nomor_surat'] ?? ('Surat Tugas ' . $sid));
    $route = $s['tujuan'] ?? '';
    $peng = isset($s['pengguna_id']) && $s['pengguna_id'] !== null && $s['pengguna_id'] !== '' ? (int)$s['pengguna_id'] : null;
    $estimasi = null;
    if (isset($s['estimasi_km']) && $s['estimasi_km'] !== '' && is_numeric($s['estimasi_km'])) $estimasi = (float)$s['estimasi_km'];

    if ($peng === null) {
        if ($estimasi === null) {
            $ins = $mysqli->prepare("INSERT INTO laporan_perjalanan (tanggal, kendaraan_id, pengguna_id, uraian_kegiatan, route, jarak_km, created_at) VALUES (?, ?, NULL, ?, ?, NULL, NOW())");
            if ($ins) { $ins->bind_param('siss', $tanggal, $kend, $uraian, $route); }
        } else {
            $ins = $mysqli->prepare("INSERT INTO laporan_perjalanan (tanggal, kendaraan_id, pengguna_id, uraian_kegiatan, route, jarak_km, created_at) VALUES (?, ?, NULL, ?, ?, ?, NOW())");
            if ($ins) { $ins->bind_param('sissd', $tanggal, $kend, $uraian, $route, $estimasi); }
        }
    } else {
        if ($estimasi === null) {
            $ins = $mysqli->prepare("INSERT INTO laporan_perjalanan (tanggal, kendaraan_id, pengguna_id, uraian_kegiatan, route, jarak_km, created_at) VALUES (?, ?, ?, ?, ?, NULL, NOW())");
            if ($ins) { $ins->bind_param('siiss', $tanggal, $kend, $peng, $uraian, $route); }
        } else {
            $ins = $mysqli->prepare("INSERT INTO laporan_perjalanan (tanggal, kendaraan_id, pengguna_id, uraian_kegiatan, route, jarak_km, created_at) VALUES (?, ?, ?, ?, ?, ?, NOW())");
            if ($ins) { $ins->bind_param('siissd', $tanggal, $kend, $peng, $uraian, $route, $estimasi); }
        }
    }

    if (!$ins) { echo "Prepare insert failed: " . $mysqli->error . "\n"; $errors2++; continue; }
    $ok = $ins->execute();
    if ($ok) {
        $inserted++;
        echo "Inserted laporan for surat_id={$sid} kendaraan={$kend} tanggal={$tanggal}\n";
    } else {
        echo "Failed insert for surat_id={$sid}: " . $ins->error . "\n";
        $errors2++;
    }
    $ins->close();
}

echo "Step 2 done. Inserted={$inserted}, Skipped={$skipped2}, Errors={$errors2}\n";

echo "Migration finished.\n";
exit(0);

?>
