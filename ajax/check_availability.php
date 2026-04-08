<?php
require_once '../config/db.php';

header('Content-Type: application/json');

$kendaraan_id = (int)($_GET['kendaraan_id'] ?? 0);
$pengguna_id = (int)($_GET['pengguna_id'] ?? 0);
$tanggal_berangkat = $_GET['tanggal_berangkat'] ?? '';
$tanggal_kembali = $_GET['tanggal_kembali'] ?? '';
$surat_id = (int)($_GET['surat_id'] ?? 0);

if (!$kendaraan_id || !$pengguna_id || !$tanggal_berangkat) {
    echo json_encode([
        'success' => false,
        'error' => 'Missing required parameters'
    ]);
    exit;
}

try {
    $end_date = $tanggal_kembali ?: $tanggal_berangkat;
    
    // Check vehicle availability
    $vehicle_available = true;
    
    // Check in surat_tugas
    $sql = "SELECT COUNT(*) as count FROM surat_tugas 
            WHERE kendaraan_id = ? 
            AND status NOT IN ('Dibatalkan', 'Selesai')
            AND ((tanggal_berangkat <= ? AND (tanggal_kembali >= ? OR tanggal_kembali IS NULL))
                 OR (tanggal_berangkat <= ? AND (tanggal_kembali >= ? OR tanggal_kembali IS NULL)))";
    
    if ($surat_id) {
        $sql .= " AND id != ?";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param('issssi', $kendaraan_id, $tanggal_berangkat, $tanggal_berangkat, $end_date, $end_date, $surat_id);
    } else {
        $stmt = $conn->prepare($sql);
        $stmt->bind_param('issss', $kendaraan_id, $tanggal_berangkat, $tanggal_berangkat, $end_date, $end_date);
    }
    
    $stmt->execute();
    $result = $stmt->get_result()->fetch_assoc();
    
    if ($result['count'] > 0) {
        $vehicle_available = false;
    }
    
    // Check in jadwal_perawatan
    if ($vehicle_available) {
        $stmt = $conn->prepare("SELECT COUNT(*) as count FROM jadwal_perawatan 
                               WHERE kendaraan_id = ? 
                               AND status IN ('Dijadwalkan', 'Dalam Proses')
                               AND tanggal_perawatan BETWEEN ? AND ?");
        $stmt->bind_param('iss', $kendaraan_id, $tanggal_berangkat, $end_date);
        $stmt->execute();
        $result = $stmt->get_result()->fetch_assoc();
        
        if ($result['count'] > 0) {
            $vehicle_available = false;
        }
    }

    // Additional check: riwayat_perbaikan (ongoing repairs)
    if ($vehicle_available) {
        $repairStmt = $conn->prepare("SELECT COUNT(*) as count FROM riwayat_perbaikan 
                                     WHERE kendaraan_id = ? 
                                     AND status NOT IN ('Selesai')
                                     AND (tanggal_perbaikan BETWEEN ? AND ?)");
        if ($repairStmt) {
            $repairStmt->bind_param('iss', $kendaraan_id, $tanggal_berangkat, $end_date);
            $repairStmt->execute();
            $rres = $repairStmt->get_result()->fetch_assoc();
            if ($rres['count'] > 0) {
                $vehicle_available = false;
            }
        }
    }
    
    // Check user availability
    $user_available = true;
    
    $sql = "SELECT COUNT(*) as count FROM surat_tugas 
            WHERE pengguna_id = ? 
            AND status NOT IN ('Dibatalkan', 'Selesai')
            AND ((tanggal_berangkat <= ? AND (tanggal_kembali >= ? OR tanggal_kembali IS NULL))
                 OR (tanggal_berangkat <= ? AND (tanggal_kembali >= ? OR tanggal_kembali IS NULL)))";
    
    if ($surat_id) {
        $sql .= " AND id != ?";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param('issssi', $pengguna_id, $tanggal_berangkat, $tanggal_berangkat, $end_date, $end_date, $surat_id);
    } else {
        $stmt = $conn->prepare($sql);
        $stmt->bind_param('issss', $pengguna_id, $tanggal_berangkat, $tanggal_berangkat, $end_date, $end_date);
    }
    
    $stmt->execute();
    $result = $stmt->get_result()->fetch_assoc();
    
    if ($result['count'] > 0) {
        $user_available = false;
    }
    
    // Get detailed conflict information
    $conflicts = [];
    
    if (!$vehicle_available) {
        // Get conflicting assignments for vehicle
        $sql = "SELECT nomor_surat, tanggal_berangkat, tanggal_kembali, status 
                FROM surat_tugas 
                WHERE kendaraan_id = ? 
                AND status NOT IN ('Dibatalkan', 'Selesai')
                AND ((tanggal_berangkat <= ? AND (tanggal_kembali >= ? OR tanggal_kembali IS NULL))
                     OR (tanggal_berangkat <= ? AND (tanggal_kembali >= ? OR tanggal_kembali IS NULL)))";
        
        if ($surat_id) {
            $sql .= " AND id != ?";
            $stmt = $conn->prepare($sql);
            $stmt->bind_param('issssi', $kendaraan_id, $tanggal_berangkat, $tanggal_berangkat, $end_date, $end_date, $surat_id);
        } else {
            $stmt = $conn->prepare($sql);
            $stmt->bind_param('issss', $kendaraan_id, $tanggal_berangkat, $tanggal_berangkat, $end_date, $end_date);
        }
        
        $stmt->execute();
        $result = $stmt->get_result();
        
        while ($row = $result->fetch_assoc()) {
            $conflicts['vehicle'][] = $row;
        }
        
        // Also include maintenance conflicts (jadwal_perawatan)
        $stmt = $conn->prepare("SELECT id, tanggal_perawatan, jadwal_tanggal, status, CONCAT('Perawatan: ', jenis_perawatan) as note FROM jadwal_perawatan WHERE kendaraan_id = ? AND status IN ('Dijadwalkan', 'Dalam Proses') AND tanggal_perawatan BETWEEN ? AND ?");
        if ($stmt) {
            $stmt->bind_param('iss', $kendaraan_id, $tanggal_berangkat, $end_date);
            $stmt->execute();
            $res = $stmt->get_result();
            while ($r = $res->fetch_assoc()) {
                $conflicts['maintenance'][] = $r;
            }
        }

        // Include repair conflicts (riwayat_perbaikan)
        $stmt = $conn->prepare("SELECT id, tanggal_perbaikan, jenis_perbaikan, status, bengkel FROM riwayat_perbaikan WHERE kendaraan_id = ? AND status NOT IN ('Selesai') AND tanggal_perbaikan BETWEEN ? AND ?");
        if ($stmt) {
            $stmt->bind_param('iss', $kendaraan_id, $tanggal_berangkat, $end_date);
            $stmt->execute();
            $res = $stmt->get_result();
            while ($r = $res->fetch_assoc()) {
                $conflicts['repairs'][] = $r;
            }
        }

        // Check peminjaman_kendaraan (other booking systems) for overlapping requests
        $stmt = $conn->prepare("SELECT id, tanggal_mulai, tanggal_selesai, status FROM peminjaman_kendaraan WHERE kendaraan_id = ? AND status NOT IN ('Cancelled', 'Selesai', 'Dibatalkan', 'Completed') AND ((tanggal_mulai <= ? AND (tanggal_selesai >= ? OR tanggal_selesai IS NULL)) OR (tanggal_mulai <= ? AND (tanggal_selesai >= ? OR tanggal_selesai IS NULL)))");
        if ($stmt) {
            $stmt->bind_param('issss', $kendaraan_id, $tanggal_berangkat, $tanggal_berangkat, $end_date, $end_date);
            $stmt->execute();
            $res = $stmt->get_result();
            while ($r = $res->fetch_assoc()) {
                $conflicts['peminjaman'][] = $r;
            }
        }
    }

    // Vehicle master status check (status_kendaraan / status_peminjaman)
    $stmt = $conn->prepare("SELECT status_kendaraan, status_peminjaman FROM kendaraan WHERE id = ?");
    if ($stmt) {
        $stmt->bind_param('i', $kendaraan_id);
        $stmt->execute();
        $veh = $stmt->get_result()->fetch_assoc();
        if ($veh) {
            // If vehicle is under repair/rusak or marked not operasional, mark unavailable
            if (!empty($veh['status_kendaraan']) && !in_array($veh['status_kendaraan'], ['Operasional'])) {
                $vehicle_available = false;
                $conflicts['vehicle_status'] = $veh;
            }
            // If status_peminjaman indicates already lent/maintenance, treat as conflict
            if (!empty($veh['status_peminjaman']) && in_array($veh['status_peminjaman'], ['Dipinjam', 'Maintenance'])) {
                $vehicle_available = false;
                $conflicts['vehicle_status'] = array_merge($conflicts['vehicle_status'] ?? [], $veh);
            }
        }
    }
    
    if (!$user_available) {
        // Get conflicting assignments for user
        $sql = "SELECT nomor_surat, tanggal_berangkat, tanggal_kembali, status 
                FROM surat_tugas 
                WHERE pengguna_id = ? 
                AND status NOT IN ('Dibatalkan', 'Selesai')
                AND ((tanggal_berangkat <= ? AND (tanggal_kembali >= ? OR tanggal_kembali IS NULL))
                     OR (tanggal_berangkat <= ? AND (tanggal_kembali >= ? OR tanggal_kembali IS NULL)))";
        
        if ($surat_id) {
            $sql .= " AND id != ?";
            $stmt = $conn->prepare($sql);
            $stmt->bind_param('issssi', $pengguna_id, $tanggal_berangkat, $tanggal_berangkat, $end_date, $end_date, $surat_id);
        } else {
            $stmt = $conn->prepare($sql);
            $stmt->bind_param('issss', $pengguna_id, $tanggal_berangkat, $tanggal_berangkat, $end_date, $end_date);
        }
        
        $stmt->execute();
        $result = $stmt->get_result();
        
        while ($row = $result->fetch_assoc()) {
            $conflicts['user'][] = $row;
        }
    }
    
    echo json_encode([
        'success' => true,
        'vehicle_available' => $vehicle_available,
        'user_available' => $user_available,
        'conflicts' => $conflicts
    ]);
    
} catch (Exception $e) {
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage()
    ]);
}

$conn->close();
?>
