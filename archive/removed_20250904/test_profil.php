<?php
// Simple test for profil.php issues
require_once 'config.php';

echo "Testing database queries from profil.php...\n\n";

// Start session to simulate login
session_start();
$_SESSION['user_id'] = 2; // operator user
$_SESSION['role'] = 'operator';

try {
    // Test 1: Get user account info
    $user_account_id = $_SESSION['user_id'];
    echo "1. Testing with user_account_id: $user_account_id\n";
    
    // Test 2: Get pengguna_id
    $stmt = $mysqli->prepare("SELECT pengguna_id FROM user_account WHERE id = ?");
    $stmt->bind_param('i', $user_account_id);
    $stmt->execute();
    $result = $stmt->get_result();
    $user_data = $result->fetch_assoc();
    $pengguna_id = $user_data['pengguna_id'] ?? 0;
    $stmt->close();
    echo "2. Retrieved pengguna_id: $pengguna_id\n";
    
    // Test 3: Count riwayat_pemakaian 
    $stmt = $mysqli->prepare("SELECT COUNT(*) as total_usage FROM riwayat_pemakaian WHERE user_id = ?");
    $stmt->bind_param('i', $user_account_id);
    $stmt->execute();
    $result = $stmt->get_result();
    $count = $result->fetch_assoc()['total_usage'];
    $stmt->close();
    echo "3. Riwayat pemakaian count: $count\n";
    
    // Test 4: Count peminjaman_kendaraan
    $stmt = $mysqli->prepare("SELECT COUNT(*) as current_assignments FROM peminjaman_kendaraan WHERE peminjam_id = ? AND status IN ('Approved', 'Ongoing')");
    $stmt->bind_param('i', $pengguna_id);
    $stmt->execute();
    $result = $stmt->get_result();
    $count = $result->fetch_assoc()['current_assignments'];
    $stmt->close();
    echo "4. Current assignments count: $count\n";
    
    // Test 5: Get last login (guarded)
    $last_login = null;
    $cols = [];
    $res = $mysqli->query("SHOW COLUMNS FROM user_activity");
    if ($res) {
        while ($r = $res->fetch_assoc()) { $cols[] = $r['Field']; }
    }
    if (in_array('user_id', $cols) && in_array('created_at', $cols) && in_array('activity_type', $cols)) {
        $stmt = $mysqli->prepare("SELECT created_at FROM user_activity WHERE user_id = ? AND activity_type = 'LOGIN' ORDER BY created_at DESC LIMIT 1");
        if ($stmt) {
            $stmt->bind_param('i', $user_account_id);
            $stmt->execute();
            $result = $stmt->get_result();
            $last_login = $result->fetch_assoc();
            $stmt->close();
        }
    }
    echo "5. Last login: " . ($last_login ? $last_login['created_at'] : 'None') . "\n";
    
    echo "\n✓ All database queries executed successfully!\n";
    echo "The profil.php error should now be resolved.\n";
    
} catch (Exception $e) {
    echo "✗ Error: " . $e->getMessage() . "\n";
    echo "Error line: " . $e->getLine() . "\n";
}

$mysqli->close();
?>
