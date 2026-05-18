<?php
/**
 * AJAX endpoint to load drivers (pengguna with role='driver')
 * Returns JSON array of drivers with id, nama_lengkap, pangkat
 */
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../includes/auth.php';

header('Content-Type: application/json');

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    http_response_code(401);
    echo json_encode(['error' => 'Unauthorized']);
    exit;
}

try {
        // Load drivers by joining user_account->role to be compatible with schemas
        $stmt = $mysqli->prepare("
            SELECT p.id, p.nama_lengkap, p.pangkat, p.jabatan
            FROM pengguna p
            JOIN user_account ua ON p.id = ua.pengguna_id
            JOIN role r ON ua.role_id = r.id
            WHERE (ua.status = 'Aktif' OR ua.status = 'aktif') AND UPPER(COALESCE(r.kode_role, '')) = 'DRIVER'
            ORDER BY p.nama_lengkap ASC
        ");
    
    if ($stmt === false) {
        throw new Exception('Prepare failed: ' . $mysqli->error);
    }
    
    $stmt->execute();
    $result = $stmt->get_result();
    $drivers = [];
    
    while ($row = $result->fetch_assoc()) {
        $drivers[] = [
            'id' => (int)$row['id'],
            'nama_lengkap' => $row['nama_lengkap'],
            'pangkat' => $row['pangkat'] ?? '',
            'jabatan' => $row['jabatan'] ?? ''
        ];
    }
    
    $stmt->close();
    
    echo json_encode(['success' => true, 'data' => $drivers]);
    
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['error' => $e->getMessage()]);
}
