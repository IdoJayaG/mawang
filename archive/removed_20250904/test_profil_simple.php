<?php
// Simplified profil.php for testing
require_once '../config.php';
require_once '../includes/auth.php';

// Start session if not started
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Simulate login for testing
if (!isset($_SESSION['user_id'])) {
    $_SESSION['user_id'] = 2; // operator user
    $_SESSION['role'] = 'operator';
}

echo "<!DOCTYPE html><html><head><title>Profil Test</title></head><body>";
echo "<h1>Profil Test</h1>";

try {
    // Test the database queries
    $user_account_id = get_current_user_id();
    echo "<p>User Account ID: $user_account_id</p>";
    
    // Get pengguna.id from user_account.id
    $stmt = $mysqli->prepare("SELECT pengguna_id FROM user_account WHERE id = ?");
    $stmt->bind_param('i', $user_account_id);
    $stmt->execute();
    $result = $stmt->get_result();
    $user_data = $result->fetch_assoc();
    $pengguna_id = $user_data['pengguna_id'] ?? 0;
    $stmt->close();
    echo "<p>Pengguna ID: $pengguna_id</p>";
    
    // Count user's vehicle usage
    $stmt = $mysqli->prepare("SELECT COUNT(*) as total_usage FROM riwayat_pemakaian WHERE user_id = ?");
    $stmt->bind_param('i', $user_account_id);
    $stmt->execute();
    $result = $stmt->get_result();
    $stats['total_usage'] = $result->fetch_assoc()['total_usage'];
    $stmt->close();
    echo "<p>Total Usage: " . $stats['total_usage'] . "</p>";
    
    // Count user's current assignments
    $stmt = $mysqli->prepare("SELECT COUNT(*) as current_assignments FROM peminjaman_kendaraan WHERE peminjam_id = ? AND status IN ('Approved', 'Ongoing')");
    $stmt->bind_param('i', $pengguna_id);
    $stmt->execute();
    $result = $stmt->get_result();
    $stats['current_assignments'] = $result->fetch_assoc()['current_assignments'];
    $stmt->close();
    echo "<p>Current Assignments: " . $stats['current_assignments'] . "</p>";
    
    // Get last login info (guarded)
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
    echo "<p>Last Login: " . ($last_login ? $last_login['created_at'] : 'None') . "</p>";
    
    echo "<div style='color: green;'><strong>✓ All queries executed successfully!</strong></div>";
    echo "<p><a href='../index.php?page=profil'>Go to real profil page</a></p>";
    
} catch (Exception $e) {
    echo "<div style='color: red;'><strong>Error:</strong> " . $e->getMessage() . "</div>";
    echo "<div style='color: red;'><strong>Line:</strong> " . $e->getLine() . "</div>";
}

echo "</body></html>";
?>
