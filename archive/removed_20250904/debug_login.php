<?php
require_once 'config.php';

echo "=== DEBUG LOGIN SYSTEM ===\n\n";

// 1. Test database connection
echo "1. Testing database connection...\n";
try {
    $test_query = $mysqli->query("SELECT COUNT(*) as count FROM user_account");
    $result = $test_query->fetch_assoc();
    echo "✓ Database connected. Found {$result['count']} users in user_account table.\n\n";
} catch (Exception $e) {
    echo "✗ Database error: " . $e->getMessage() . "\n\n";
}

// 2. Check user_account table structure
echo "2. Checking user_account table structure...\n";
$structure = $mysqli->query("DESCRIBE user_account");
while ($row = $structure->fetch_assoc()) {
    echo "   {$row['Field']} ({$row['Type']})\n";
}
echo "\n";

// 3. Check sample user data
echo "3. Sample user data:\n";
$users = $mysqli->query("SELECT ua.id, ua.username, ua.password, r.nama_role 
                        FROM user_account ua 
                        JOIN role r ON ua.role_id = r.id 
                        LIMIT 3");
while ($user = $users->fetch_assoc()) {
    echo "   ID: {$user['id']}, Username: {$user['username']}, Role: {$user['nama_role']}\n";
}
echo "\n";

// 4. Test auth functions
echo "4. Testing auth functions...\n";
if (function_exists('validate_csrf_token')) {
    echo "✓ validate_csrf_token function exists\n";
} else {
    echo "✗ validate_csrf_token function missing\n";
}

if (function_exists('generate_csrf_token')) {
    echo "✓ generate_csrf_token function exists\n";
} else {
    echo "✗ generate_csrf_token function missing\n";
}

if (function_exists('verify_password')) {
    echo "✓ verify_password function exists\n";
} else {
    echo "✗ verify_password function missing\n";
}

if (function_exists('log_activity')) {
    echo "✓ log_activity function exists\n";
} else {
    echo "✗ log_activity function missing\n";
}

if (function_exists('redirect_to_dashboard')) {
    echo "✓ redirect_to_dashboard function exists\n";
} else {
    echo "✗ redirect_to_dashboard function missing\n";
}

echo "\n5. Session status:\n";
session_start();
echo "Session ID: " . session_id() . "\n";
echo "Session data: " . print_r($_SESSION, true) . "\n";
?>
