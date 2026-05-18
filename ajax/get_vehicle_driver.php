<?php
/**
 * Get vehicle driver (pengguna_id) information
 * Returns JSON with driver details based on selected vehicle
 */
require_once '../includes/auth.php';

header('Content-Type: application/json');

// Check authorization
if (!is_logged_in()) {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit;
}

$kendaraan_id = isset($_GET['kendaraan_id']) ? (int)$_GET['kendaraan_id'] : 0;

if ($kendaraan_id <= 0) {
    echo json_encode(['success' => false, 'message' => 'Invalid vehicle ID']);
    exit;
}

// Check if pengguna_id column exists in kendaraan table
$col_check = $mysqli->query("SHOW COLUMNS FROM kendaraan LIKE 'pengguna_id'");
$has_pengguna_id = $col_check && $col_check->num_rows > 0;

if (!$has_pengguna_id) {
    echo json_encode(['success' => false, 'message' => 'Vehicle driver information not available']);
    exit;
}

// Get vehicle with driver info
$stmt = $mysqli->prepare("
    SELECT k.id, k.pengguna_id, p.id as pengguna_id_check, p.nama_lengkap, p.pangkat, p.nrp_nip
    FROM kendaraan k
    LEFT JOIN pengguna p ON k.pengguna_id = p.id
    WHERE k.id = ?
");
$stmt->bind_param('i', $kendaraan_id);
$stmt->execute();
$result = $stmt->get_result();
$vehicle = $result->fetch_assoc();
$stmt->close();

if (!$vehicle) {
    echo json_encode(['success' => false, 'message' => 'Vehicle not found']);
    exit;
}

if ($vehicle['pengguna_id'] === null || !$vehicle['pengguna_id_check']) {
    // No driver assigned to this vehicle
    echo json_encode([
        'success' => true,
        'data' => [
            'pengguna_id' => null,
            'nama_lengkap' => '(Belum ditentukan)',
            'pangkat' => '',
            'nrp_nip' => '',
            'has_driver' => false
        ]
    ]);
    exit;
}

// Return driver information
echo json_encode([
    'success' => true,
    'data' => [
        'pengguna_id' => (int)$vehicle['pengguna_id'],
        'nama_lengkap' => $vehicle['nama_lengkap'] ?? '',
        'pangkat' => $vehicle['pangkat'] ?? '',
        'nrp_nip' => $vehicle['nrp_nip'] ?? '',
        'has_driver' => true,
        'display_text' => (($vehicle['pangkat'] ?? '') !== '' ? $vehicle['pangkat'] . ' ' : '') . 
                         ($vehicle['nama_lengkap'] ?? '') . 
                         ' (' . ($vehicle['nrp_nip'] ?? '') . ')'
    ]
]);
