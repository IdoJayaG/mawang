<?php
// Test get_user_info function
require_once 'config.php';
require_once 'includes/auth.php';

session_start();

// Simulate login as user 2 (operator)
$_SESSION['user_id'] = 2;
$_SESSION['role'] = 'operator';

echo "Testing get_user_info() function...\n\n";

$user_info = get_user_info();

if ($user_info) {
    echo "✓ User info retrieved successfully!\n\n";
    echo "Available data:\n";
    foreach ($user_info as $key => $value) {
        echo "- $key: " . ($value ? $value : '[empty]') . "\n";
    }
} else {
    echo "✗ Failed to retrieve user info\n";
    
    // Test the query manually
    echo "\nTesting query manually...\n";
    $stmt = $mysqli->prepare("
        SELECT p.*, ua.username, r.nama_role 
        FROM pengguna p 
        JOIN user_account ua ON p.id = ua.pengguna_id 
        JOIN role r ON ua.role_id = r.id 
        WHERE ua.id = ?
    ");
    $stmt->bind_param('i', $_SESSION['user_id']);
    $stmt->execute();
    $result = $stmt->get_result();
    $user = $result->fetch_assoc();
    $stmt->close();
    
    if ($user) {
        echo "✓ Manual query worked:\n";
        foreach ($user as $key => $value) {
            echo "- $key: " . ($value ? $value : '[empty]') . "\n";
        }
    } else {
        echo "✗ Manual query also failed\n";
        echo "Error: " . $mysqli->error . "\n";
    }
}

$mysqli->close();
?>
