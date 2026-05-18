<?php
// Enable error reporting for debugging
error_reporting(E_ALL);
ini_set('display_errors', 0); // Don't display errors in output
ini_set('log_errors', 1);

// Start output buffering to catch any unexpected output
ob_start();

require_once dirname(__DIR__) . '/includes/auth.php';
require_once dirname(__DIR__) . '/lib/table_helpers.php';

// Clear any output that might have been generated during includes
if (ob_get_length()) {
    ob_clean();
}

header('Content-Type: application/json');

// Check if user can operate
if (!can_operate()) {
    echo json_encode(['success' => false, 'message' => 'Akses ditolak']);
    exit;
}

// Validate CSRF token
if (!validate_csrf_token($_POST['csrf_token'] ?? '')) {
    echo json_encode(['success' => false, 'message' => 'Token keamanan tidak valid']);
    exit;
}

$id = (int)$_POST['id'];
$status = $_POST['status'];
$current_user_id = get_current_user_id();

if (!$id || !$status) {
    echo json_encode(['success' => false, 'message' => 'Parameter tidak lengkap']);
    exit;
}

// Validate status values
$valid_statuses = ['Terjadwal', 'Dalam Proses', 'Selesai', 'Terlewat', 'Dibatalkan'];
if (!in_array($status, $valid_statuses)) {
    echo json_encode(['success' => false, 'message' => 'Status tidak valid']);
    exit;
}

// Get current jadwal data
$stmt = $mysqli->prepare("SELECT j.*, k.no_polisi, k.merk, k.tipe FROM jadwal_perawatan j LEFT JOIN kendaraan k ON j.kendaraan_id = k.id WHERE j.id = ?");
$stmt->bind_param('i', $id);
$stmt->execute();
$result = $stmt->get_result();
$jadwal = $result->fetch_assoc();
$stmt->close();

if (!$jadwal) {
    echo json_encode(['success' => false, 'message' => 'Jadwal tidak ditemukan']);
    exit;
}

// Use central helper for column detection
$riwayat_has_kategori = table_has_columns($mysqli, 'riwayat_perawatan', ['kategori']);
$riwayat_has_bengkel = table_has_columns($mysqli, 'riwayat_perawatan', ['bengkel']);
$riwayat_has_mekanik = table_has_columns($mysqli, 'riwayat_perawatan', ['mekanik']);
$riwayat_has_teknisi_id = table_has_columns($mysqli, 'riwayat_perawatan', ['teknisi_id']);

try {
    $mysqli->begin_transaction();

    // prefer client-supplied tanggal_selesai if present (biaya fields removed)
    $tanggal_selesai_post = $_POST['tanggal_selesai'] ?? null;

    if ($status === 'Selesai') {
        // Mark the jadwal_perawatan record as Selesai (do not move/delete record)
        $use_tanggal = $tanggal_selesai_post ?: date('Y-m-d');
        // Update jadwal status and tanggal selesai (biaya fields removed)
        $stmt = $mysqli->prepare("UPDATE jadwal_perawatan SET status = ?, tanggal_selesai = ?, updated_by = ?, updated_at = NOW() WHERE id = ?");
        if (!$stmt) {
            throw new Exception('Gagal menyiapkan query update jadwal: ' . $mysqli->error);
        }
        $stmt->bind_param('ssii', $status, $use_tanggal, $current_user_id, $id);
        if (!$stmt->execute()) {
            throw new Exception('Gagal mengupdate jadwal menjadi selesai: ' . $stmt->error);
        }
        $stmt->close();

        // Sync kendaraan status: Operasional/Tersedia
        if (!empty($jadwal['kendaraan_id'])) {
            $vk = $mysqli->prepare("UPDATE kendaraan SET status_kendaraan = ?, status_peminjaman = ? WHERE id = ?");
            if (!$vk) {
                throw new Exception('Gagal menyiapkan update kendaraan: ' . $mysqli->error);
            }
            $status_kendaraan = 'Operasional';
            $status_peminjaman = 'Tersedia';
            $vk->bind_param('ssi', $status_kendaraan, $status_peminjaman, $jadwal['kendaraan_id']);
            if (!$vk->execute()) {
                throw new Exception('Gagal menyinkronkan status kendaraan (Selesai): ' . $vk->error);
            }
            $vk->close();
            log_activity("UPDATE_VEHICLE_STATUS_BY_MAINTENANCE", "Set kendaraan ID {$jadwal['kendaraan_id']} menjadi Operasional/Tersedia setelah perawatan selesai");
        }

        log_activity("COMPLETE_MAINTENANCE", "Menandai jadwal perawatan selesai: {$jadwal['jenis_perawatan']} untuk kendaraan {$jadwal['no_polisi']}");
        $message = 'Status jadwal perawatan: Selesai (tetap tersimpan di jadwal dan akan tampil di Riwayat)';

    } else {
        // Just update status (non-finish transitions)
        $tanggal_selesai = null;
        if ($status === 'Dalam Proses') {
            $tanggal_selesai = null;
        }

        $stmt = $mysqli->prepare("UPDATE jadwal_perawatan SET status = ?, tanggal_selesai = ?, updated_by = ?, updated_at = NOW() WHERE id = ?");
        if (!$stmt) {
            throw new Exception('Gagal menyiapkan query update jadwal: ' . $mysqli->error);
        }
        $stmt->bind_param('ssii', $status, $tanggal_selesai, $current_user_id, $id);

        if (!$stmt->execute()) {
            throw new Exception('Gagal mengupdate status: ' . $stmt->error);
        }
        $stmt->close();

        // If moving to Dalam Proses, set kendaraan to Perbaikan/Maintenance
        if ($status === 'Dalam Proses' && !empty($jadwal['kendaraan_id'])) {
            $vk = $mysqli->prepare("UPDATE kendaraan SET status_kendaraan = ?, status_peminjaman = ? WHERE id = ?");
            if (!$vk) {
                throw new Exception('Gagal menyiapkan update kendaraan: ' . $mysqli->error);
            }
            $status_kendaraan = 'Perbaikan';
            $status_peminjaman = 'Maintenance';
            $vk->bind_param('ssi', $status_kendaraan, $status_peminjaman, $jadwal['kendaraan_id']);
            if (!$vk->execute()) {
                throw new Exception('Gagal menyinkronkan status kendaraan (Dalam Proses): ' . $vk->error);
            }
            $vk->close();
            log_activity("UPDATE_VEHICLE_STATUS_BY_MAINTENANCE", "Set kendaraan ID {$jadwal['kendaraan_id']} menjadi Perbaikan/Maintenance saat perawatan dimulai");
        }

        log_activity("UPDATE_MAINTENANCE_STATUS", "Mengubah status jadwal perawatan {$jadwal['jenis_perawatan']} menjadi {$status}");

        $message = 'Status jadwal perawatan berhasil diperbarui';
    }

    $mysqli->commit();
    
    // Clear any output buffer and send clean JSON
    if (ob_get_length()) {
        ob_clean();
    }
    echo json_encode(['success' => true, 'message' => $message]);
    
} catch (Exception $e) {
    $mysqli->rollback();
    
    // Clear any output buffer and send clean JSON error
    if (ob_get_length()) {
        ob_clean();
    }
    // Append exception details to a debug log for investigation
    $logMsg = date('Y-m-d H:i:s') . " - update_jadwal_status error: " . $e->getMessage() . "\n";
    @file_put_contents(__DIR__ . '/../logs/update_jadwal_status.log', $logMsg, FILE_APPEND);
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}

// End output buffering
ob_end_flush();
?>
