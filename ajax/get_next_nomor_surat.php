<?php
require_once '../config/db.php';

header('Content-Type: application/json');

$month = $_GET['month'] ?? '';
$year = $_GET['year'] ?? '';

if (empty($month) || empty($year)) {
    echo json_encode(['success' => false, 'error' => 'Month and year required']);
    exit;
}

try {
    // Get the last number for this month/year
    $stmt = $conn->prepare("SELECT nomor_surat FROM surat_tugas WHERE nomor_surat LIKE ? ORDER BY id DESC LIMIT 1");
    $pattern = "ST/%/{$month}/{$year}";
    $stmt->bind_param('s', $pattern);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows > 0) {
        $last_surat = $result->fetch_assoc();
        // Extract number from ST/XXX/VIII/2025 format
        preg_match('/ST\/(\d+)\//', $last_surat['nomor_surat'], $matches);
        $next_number = isset($matches[1]) ? intval($matches[1]) + 1 : 1;
    } else {
        $next_number = 1;
    }
    
    $nomor_surat = sprintf("ST/%03d/%s/%s", $next_number, $month, $year);
    
    echo json_encode([
        'success' => true,
        'nomor_surat' => $nomor_surat,
        'next_number' => $next_number
    ]);
    
} catch (Exception $e) {
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage()
    ]);
}

$conn->close();
?>
