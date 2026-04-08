<?php
require_once 'config.php';

echo "=== TEST LOGIN PROCESS ===\n\n";

// Test credentials
$username = 'admin';
$password = 'admin';

echo "Testing login with username: $username, password: $password\n\n";

// Test the exact query from login.php
$stmt = $mysqli->prepare("SELECT ua.id, ua.username, ua.password, r.kode_role, r.nama_role FROM user_account ua JOIN role r ON ua.role_id = r.id WHERE ua.username=? LIMIT 1");
$stmt->bind_param('s', $username);
$stmt->execute();
$stmt->store_result();

echo "Query executed. Rows found: " . $stmt->num_rows . "\n";

if ($stmt->num_rows === 1) {
    $stmt->bind_result($id, $user, $hash, $kode_role, $role_name);
    $stmt->fetch();
    
    echo "User found:\n";
    echo "- ID: $id\n";
    echo "- Username: $user\n";
    echo "- Password hash: $hash\n";
    echo "- Role (kode): $kode_role\n";
    echo "- Role (nama): $role_name\n\n";
    
    // Test password verification
    echo "Testing password verification:\n";
    echo "- Direct comparison: " . ($password === $hash ? "MATCH" : "NO MATCH") . "\n";
    echo "- verify_password function: " . (verify_password($password, $hash) ? "MATCH" : "NO MATCH") . "\n\n";
    
    // Test if password matches either way
    $password_valid = ($password === $hash || verify_password($password, $hash));
    echo "Password is valid: " . ($password_valid ? "YES" : "NO") . "\n\n";
    
    if ($password_valid) {
        echo "Login would be successful. Testing session setup...\n";
    $_SESSION['user_id'] = $id;
    $_SESSION['username'] = $user;
    $role_slug = strtolower(trim($kode_role));
    $role_slug = preg_replace('/[^a-z0-9_\-]+/', '_', $role_slug);
    $role_slug = trim($role_slug, '_');
    $_SESSION['role'] = $role_slug;
    $_SESSION['role_name'] = $role_name;
        
        echo "Session variables set:\n";
        echo "- user_id: " . $_SESSION['user_id'] . "\n";
        echo "- username: " . $_SESSION['username'] . "\n";
        echo "- role: " . $_SESSION['role'] . "\n\n";
        
        echo "Testing redirect_to_dashboard function...\n";
        $role = get_current_role();
        echo "Current role from function: $role\n";
        
        switch ($role) {
            case 'admin':
                echo "Would redirect to: index.php?page=dashboard_admin\n";
                break;
            case 'operator':
                echo "Would redirect to: index.php?page=dashboard_operator\n";
                break;
            case 'user':
                echo "Would redirect to: index.php?page=dashboard_user\n";
                break;
            default:
                echo "Would redirect to: index.php?page=home\n";
                break;
        }
    }
} else {
    echo "User not found in database\n";
}

$stmt->close();
?>
