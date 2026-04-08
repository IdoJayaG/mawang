<?php
require_once '../config.php';

// Check if user is logged in and has appropriate permissions
if (!is_logged_in() || !can_operate()) {
    echo json_encode(['success' => false, 'message' => 'Unauthorized access']);
    exit;
}

// Validate CSRF token
if (!validate_csrf_token($_POST['csrf_token'])) {
    echo json_encode(['success' => false, 'message' => 'Invalid CSRF token']);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $id = intval($_POST['id']);
    $prioritas = trim($_POST['prioritas']);
    
    // Validate priority values
    $valid_priorities = ['Rendah', 'Normal', 'Tinggi', 'Urgent'];
    if (!in_array($prioritas, $valid_priorities)) {
        echo json_encode(['success' => false, 'message' => 'Prioritas tidak valid']);
        exit;
    }
    
    try {
        $stmt = $mysqli->prepare("UPDATE jadwal_perawatan SET prioritas = ?, updated_at = NOW(), updated_by = ? WHERE id = ?");
        $stmt->bind_param('sii', $prioritas, $_SESSION['user_id'], $id);
        
        if ($stmt->execute()) {
            // Log activity
            log_activity("UPDATE_JADWAL_PRIORITAS", "Mengubah prioritas jadwal perawatan ID: $id menjadi $prioritas");
            
            echo json_encode([
                'success' => true, 
                'message' => "Prioritas berhasil diubah menjadi $prioritas",
                'prioritas' => $prioritas
            ]);
        } else {
            echo json_encode(['success' => false, 'message' => 'Gagal mengupdate prioritas: ' . $stmt->error]);
        }
        
        $stmt->close();
        
    } catch (Exception $e) {
        echo json_encode(['success' => false, 'message' => 'Error: ' . $e->getMessage()]);
    }
} else {
    echo json_encode(['success' => false, 'message' => 'Method not allowed']);
}
?>
