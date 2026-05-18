<?php
require_once dirname(__DIR__) . '/includes/auth.php';

header('Content-Type: application/json');

if (!can_operate()) {
    echo json_encode(['success' => false, 'message' => 'Tidak memiliki akses']);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'message' => 'Method tidak valid']);
    exit;
}

if (!validate_csrf_token($_POST['csrf_token'] ?? '')) {
    echo json_encode(['success' => false, 'message' => 'Token keamanan tidak valid']);
    exit;
}

$action = $_POST['action'] ?? '';
$id = (int)($_POST['id'] ?? 0);

try {
    if ($action === 'add') {
        $kendaraan_id = (int)$_POST['kendaraan_id'];
        $jenis_perawatan = trim($_POST['jenis_perawatan']);
        $deskripsi = trim($_POST['deskripsi']);
        $jadwal_tanggal = $_POST['jadwal_tanggal'];
        $km_target = $_POST['km_target'] ? (int)$_POST['km_target'] : null;
        $prioritas = $_POST['prioritas'];
        $keterangan = trim($_POST['keterangan']);
        
        $stmt = $mysqli->prepare("INSERT INTO jadwal_perawatan (kendaraan_id, jenis_perawatan, deskripsi, jadwal_tanggal, km_target, prioritas, keterangan, created_by) VALUES (?, ?, ?, ?, ?, ?, ?, ?)");
        $stmt->bind_param('isssisis', $kendaraan_id, $jenis_perawatan, $deskripsi, $jadwal_tanggal, $km_target, $prioritas, $keterangan, get_current_user_id());
        
        if ($stmt->execute()) {
            log_user_activity("Menambah jadwal perawatan: $jenis_perawatan untuk kendaraan ID $kendaraan_id");
            echo json_encode(['success' => true, 'message' => 'Jadwal perawatan berhasil ditambahkan!']);
        } else {
            echo json_encode(['success' => false, 'message' => 'Error: ' . $stmt->error]);
        }
        $stmt->close();
        
    } elseif ($action === 'edit' && $id) {
        $kendaraan_id = (int)$_POST['kendaraan_id'];
        $jenis_perawatan = trim($_POST['jenis_perawatan']);
        $deskripsi = trim($_POST['deskripsi']);
        $jadwal_tanggal = $_POST['jadwal_tanggal'];
        $km_target = $_POST['km_target'] ? (int)$_POST['km_target'] : null;
        $status = $_POST['status'];
        $prioritas = $_POST['prioritas'];
        $tanggal_selesai = $_POST['tanggal_selesai'] ?: null;
        $keterangan = trim($_POST['keterangan']);

        $stmt = $mysqli->prepare("UPDATE jadwal_perawatan SET kendaraan_id=?, jenis_perawatan=?, deskripsi=?, jadwal_tanggal=?, km_target=?, status=?, prioritas=?, tanggal_selesai=?, keterangan=?, updated_by=? WHERE id=?");
        $stmt->bind_param('isssissssii', $kendaraan_id, $jenis_perawatan, $deskripsi, $jadwal_tanggal, $km_target, $status, $prioritas, $tanggal_selesai, $keterangan, get_current_user_id(), $id);
        
        if ($stmt->execute()) {
            log_user_activity("Memperbarui jadwal perawatan ID: $id");
            echo json_encode(['success' => true, 'message' => 'Jadwal perawatan berhasil diperbarui!']);
        } else {
            echo json_encode(['success' => false, 'message' => 'Error: ' . $stmt->error]);
        }
        $stmt->close();
    } else {
        echo json_encode(['success' => false, 'message' => 'Action tidak valid']);
    }
    
} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => 'Error: ' . $e->getMessage()]);
}
?>
