<?php
require_once 'config.php';

echo "=== UPDATING ADMIN PASSWORD ===\n\n";

// Generate new hash untuk password 'admin'
$new_password = 'admin';
$new_hash = password_hash($new_password, PASSWORD_BCRYPT);

echo "New password: $new_password\n";
echo "New hash: $new_hash\n\n";

// Update di database
$stmt = $mysqli->prepare("UPDATE user_account SET password = ? WHERE username = 'admin'");
$stmt->bind_param('s', $new_hash);

if ($stmt->execute()) {
    echo "✓ Password admin berhasil diupdate\n";
} else {
    echo "✗ Gagal update password: " . $stmt->error . "\n";
}

$stmt->close();

// Test login dengan password baru
echo "\nTesting login dengan password baru...\n";
$stmt = $mysqli->prepare("SELECT ua.id, ua.username, ua.password, r.nama_role FROM user_account ua JOIN role r ON ua.role_id = r.id WHERE ua.username='admin' LIMIT 1");
$stmt->execute();
$stmt->store_result();

if ($stmt->num_rows === 1) {
    $stmt->bind_result($id, $user, $hash, $role);
    $stmt->fetch();
    
    $password_valid = password_verify($new_password, $hash);
    echo "Password verification: " . ($password_valid ? "SUCCESS" : "FAILED") . "\n";
} else {
    echo "Admin user not found\n";
}

$stmt->close();

// Also update operator and user dengan password yang sama dengan username
echo "\nUpdating other users...\n";

$users = ['operator', 'user'];
foreach ($users as $username) {
    $hash = password_hash($username, PASSWORD_BCRYPT);
    $stmt = $mysqli->prepare("UPDATE user_account SET password = ? WHERE username = ?");
    $stmt->bind_param('ss', $hash, $username);
    
    if ($stmt->execute()) {
        echo "✓ Password $username updated\n";
    } else {
        echo "✗ Failed to update $username: " . $stmt->error . "\n";
    }
    
    $stmt->close();
}

echo "\n=== PASSWORD UPDATE COMPLETE ===\n";
echo "Credentials:\n";
echo "- admin / admin\n";
echo "- operator / operator\n";
echo "- user / user\n";
?>
