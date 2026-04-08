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

$id = (int)($_POST['id'] ?? 0);

if (!$id) {
    echo json_encode(['success' => false, 'message' => 'ID tidak valid']);
    exit;
}

try {
    $stmt = $mysqli->prepare("DELETE FROM jadwal_perawatan WHERE id = ?");
    $stmt->bind_param('i', $id);
    
    if ($stmt->execute()) {
        log_user_activity("Menghapus jadwal perawatan ID: $id");
        echo json_encode(['success' => true, 'message' => 'Jadwal berhasil dihapus']);
    } else {
        echo json_encode(['success' => false, 'message' => 'Gagal menghapus jadwal']);
    }
    
    $stmt->close();
    
} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => 'Error: ' . $e->getMessage()]);
}
?>
