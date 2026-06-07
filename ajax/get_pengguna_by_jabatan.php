<?php
require_once '../includes/auth.php';
require_once '../config/db.php';

header('Content-Type: application/json');

// Only allow logged-in admin-like users to query
if (!is_logged_in() || !can_operate()) {
    echo json_encode(['success' => false, 'message' => 'Tidak diizinkan']);
    exit;
}

$jabatan = isset($_GET['jabatan']) ? trim((string)$_GET['jabatan']) : '';

// Ordered enum (keep in sync with kendaraan.php & pengguna.jabatan)
$JABATAN_ENUM = [
    'Kepala SPBT Kemhan Cawang',
    'Wakil Kepala SPBT Kemhan Cawang',
    'Kataud',
    'Bidduk TI',
    'Kabidduk TI',
    'Kasubbid SDM TI',
    'Kasubbid Jarkomta & Duknis',
    'Kabidinfomin',
    'Kasubbid Sisfopers',
    'Kasubbid Sisfogarku',
    'Kabidinfoops',
    'Kasubbid Sisfoter',
    'Kasubbid Sisfointel',
    'Kasubbid Sisfoopslat',
    'Kabidpamsisfo',
    'Kasubbid Pam Aplikasi',
    'Kasubbid Pam jarkomta',
    'Tamudi Kapus',
    'Baurku',
    'Caraka',
    'Kaurpers',
    'Kaurdal',
    'Kaurtu',
    'Baurtarlat',
    'Baur Spri Kapus'
];

if ($jabatan === '' || !in_array($jabatan, $JABATAN_ENUM, true)) {
    echo json_encode(['success' => true, 'users' => []]);
    exit;
}

// Fetch active users with matching jabatan
$stmt = $mysqli->prepare("SELECT id, nama_lengkap, pangkat, jabatan FROM pengguna WHERE status_aktif = 'Aktif' AND jabatan = ? ORDER BY nama_lengkap ASC");
if (!$stmt) {
    echo json_encode(['success' => false, 'message' => 'Gagal menyiapkan query']);
    exit;
}
$stmt->bind_param('s', $jabatan);
$stmt->execute();
$res = $stmt->get_result();
$users = [];
while ($row = $res->fetch_assoc()) {
    $users[] = [
        'id' => (int)$row['id'],
        'nama_lengkap' => $row['nama_lengkap'],
        'pangkat' => $row['pangkat'],
        'jabatan' => $row['jabatan']
    ];
}
$stmt->close();

echo json_encode(['success' => true, 'users' => $users]);
exit;
?>
