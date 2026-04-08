<?php
// Move all jadwal_perawatan with status 'Selesai' into riwayat_perawatan
// Usage: php scripts/move_finished_jadwal.php

require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../lib/table_helpers.php';

// Ensure logs directory exists
@mkdir(__DIR__ . '/../logs', 0755, true);
$logFile = __DIR__ . '/../logs/move_finished_jadwal.log';

function log_line($msg) {
    global $logFile;
    $line = date('Y-m-d H:i:s') . ' - ' . $msg . "\n";
    file_put_contents($logFile, $line, FILE_APPEND);
}

// Use central table helpers
require_once __DIR__ . '/../lib/table_helpers.php';

$riwayat_has_kategori = table_has_columns($mysqli, 'riwayat_perawatan', ['kategori']);
$riwayat_has_bengkel = table_has_columns($mysqli, 'riwayat_perawatan', ['bengkel']);
$riwayat_has_mekanik = table_has_columns($mysqli, 'riwayat_perawatan', ['mekanik']);

// Fetch all jadwal_perawatan with status 'Selesai'
$stmt = $mysqli->prepare("SELECT j.* FROM jadwal_perawatan j WHERE j.status = 'Selesai'");
$stmt->execute();
$res = $stmt->get_result();
$rows = $res->fetch_all(MYSQLI_ASSOC);
$stmt->close();

$total = count($rows);
if ($total === 0) {
    echo "No finished schedules found.\n";
    exit(0);
}

echo "Found $total finished schedule(s). Processing...\n";

$processed = 0;
$failed = 0;

foreach ($rows as $jadwal) {
    $id = (int)$jadwal['id'];
    try {
        $mysqli->begin_transaction();

        $tanggal_selesai = date('Y-m-d');
        $biaya_aktual = $jadwal['biaya_aktual'] ?: $jadwal['estimasi_biaya'];
        $jenis_perawatan_enum = 'Lainnya';
        $jenis_original = strtolower($jadwal['jenis_perawatan'] ?? '');
        if (stripos($jenis_original, 'servis') !== false || stripos($jenis_original, 'service') !== false) {
            $jenis_perawatan_enum = 'Servis Berkala';
        } elseif (stripos($jenis_original, 'sparepart') !== false || stripos($jenis_original, 'part') !== false || stripos($jenis_original, 'ganti') !== false) {
            $jenis_perawatan_enum = 'Penggantian Sparepart';
        } elseif (stripos($jenis_original, 'modifikasi') !== false || stripos($jenis_original, 'upgrade') !== false) {
            $jenis_perawatan_enum = 'Modifikasi';
        } elseif (stripos($jenis_original, 'perbaikan') !== false || stripos($jenis_original, 'repair') !== false || stripos($jenis_original, 'rusak') !== false) {
            $jenis_perawatan_enum = 'Perbaikan';
        } else {
            $valid_enums = ['Servis Berkala', 'Perbaikan', 'Penggantian Sparepart', 'Modifikasi', 'Lainnya'];
            foreach ($valid_enums as $enum_val) {
                if (strcasecmp($jadwal['jenis_perawatan'] ?? '', $enum_val) === 0) {
                    $jenis_perawatan_enum = $enum_val;
                    break;
                }
            }
        }

        $bengkel = $jadwal['bengkel'] ?? 'Internal';
        $mekanik = $jadwal['teknisi_id'] ?? 'Tim Maintenance';
        $km_saat_perawatan = $jadwal['km_target'] ?? 0;
        $kategori = 'Ringan';
        $status_perawatan = 'Selesai';

        $keterangan_lengkap = "Jenis: " . ($jadwal['jenis_perawatan'] ?? '-');
        if (!empty($jadwal['deskripsi'])) $keterangan_lengkap .= "\nDeskripsi: " . $jadwal['deskripsi'];
        if (!empty($jadwal['keterangan'])) $keterangan_lengkap .= "\nCatatan: " . $jadwal['keterangan'];
        $keterangan_lengkap .= "\nDipindahkan dari jadwal perawatan pada " . date('d/m/Y H:i');

        // Build column list
        $cols = ['kendaraan_id', 'tanggal_perawatan', 'jenis_perawatan'];
        if ($riwayat_has_kategori) $cols[] = 'kategori';
        $cols[] = 'keterangan';
        $cols[] = 'km_saat_perawatan';
        if ($riwayat_has_bengkel) $cols[] = 'bengkel';
        if ($riwayat_has_mekanik) $cols[] = 'mekanik';
        $cols[] = 'biaya';
        $cols[] = 'status';
        $cols[] = 'created_by';

        $placeholders = implode(',', array_fill(0, count($cols), '?'));
        $sql = "INSERT INTO riwayat_perawatan (" . implode(', ', $cols) . ") VALUES ($placeholders)";
        $stmtIns = $mysqli->prepare($sql);
        if (!$stmtIns) {
            throw new Exception('Prepare failed: ' . $mysqli->error);
        }

        // Build params
        $types = '';
        $params = [];
        $types .= 'i'; $params[] = $jadwal['kendaraan_id'];
        $types .= 's'; $params[] = $tanggal_selesai;
        $types .= 's'; $params[] = $jenis_perawatan_enum;
        if ($riwayat_has_kategori) { $types .= 's'; $params[] = $kategori; }
        $types .= 's'; $params[] = $keterangan_lengkap;
        $types .= 'i'; $params[] = $km_saat_perawatan;
        if ($riwayat_has_bengkel) { $types .= 's'; $params[] = $bengkel; }
        if ($riwayat_has_mekanik) { $types .= 's'; $params[] = $mekanik; }
        $types .= 'd'; $params[] = $biaya_aktual;
        $types .= 's'; $params[] = $status_perawatan;
        // Use created_by from jadwal if available, otherwise 0
        $types .= 'i'; $params[] = isset($jadwal['created_by']) ? (int)$jadwal['created_by'] : 0;

        // bind by reference
        $bind_names = [];
        $bind_names[] = $types;
        for ($i = 0; $i < count($params); $i++) {
            ${"p$i"} = $params[$i];
            $bind_names[] = &${"p$i"};
        }
        call_user_func_array([$stmtIns, 'bind_param'], $bind_names);

        if (!$stmtIns->execute()) {
            throw new Exception('Execute failed: ' . $stmtIns->error);
        }
        $stmtIns->close();

        // Delete original jadwal
        $del = $mysqli->prepare("DELETE FROM jadwal_perawatan WHERE id = ?");
        $del->bind_param('i', $id);
        if (!$del->execute()) throw new Exception('Delete failed: ' . $del->error);
        $del->close();

        $mysqli->commit();
        log_line("Moved jadwal id={$id} to riwayat_perawatan");
        $processed++;
    } catch (Exception $e) {
        $mysqli->rollback();
        log_line("Failed to move jadwal id={$id}: " . $e->getMessage());
        $failed++;
    }
}

echo "Done. Processed: {$processed}, Failed: {$failed}\n";
log_line("Run complete. Processed={$processed}, Failed={$failed}");

exit(0);
