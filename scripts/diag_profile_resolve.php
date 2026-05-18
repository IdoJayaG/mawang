<?php
require_once __DIR__ . '/../config.php';
session_start();

// Test account id (adjust if needed)
$_SESSION['user_id'] = 44; // example: account id 44 maps to pengguna_id 50 in diagnostics

require_once __DIR__ . '/../includes/auth.php';

$requested_id = (int)($_SESSION['user_id'] ?? 0);
$profile_account_id = null;
$profile_pengguna_id = null;

$st = $mysqli->prepare("SELECT id, pengguna_id FROM user_account WHERE id = ? LIMIT 1");
if ($st) {
    $st->bind_param('i', $requested_id);
    $st->execute();
    $r = $st->get_result()->fetch_assoc();
    $st->close();
    if ($r) {
        $profile_account_id = (int)$r['id'];
        $profile_pengguna_id = !empty($r['pengguna_id']) ? (int)$r['pengguna_id'] : null;
    }
}

if (is_null($profile_account_id)) {
    $st2 = $mysqli->prepare("SELECT id FROM user_account WHERE pengguna_id = ? LIMIT 1");
    if ($st2) {
        $st2->bind_param('i', $requested_id);
        $st2->execute();
        $r2 = $st2->get_result()->fetch_assoc();
        $st2->close();
        if ($r2) {
            $profile_account_id = (int)$r2['id'];
            $profile_pengguna_id = $requested_id;
        } else {
            $st3 = $mysqli->prepare("SELECT id FROM pengguna WHERE id = ? LIMIT 1");
            if ($st3) {
                $st3->bind_param('i', $requested_id);
                $st3->execute();
                $r3 = $st3->get_result()->fetch_assoc();
                $st3->close();
                if ($r3) {
                    $profile_pengguna_id = $requested_id;
                }
            }
        }
    }
}

echo "Requested session user_id: {$requested_id}\n";
echo "Resolved account_id: " . ($profile_account_id ?? 'NULL') . "\n";
echo "Resolved pengguna_id: " . ($profile_pengguna_id ?? 'NULL') . "\n";
echo "get_current_user_id() -> " . (int)get_current_user_id() . "\n";

$check = $mysqli->query("SELECT ua.id, ua.pengguna_id, ua.username FROM user_account ua WHERE ua.id = 44 OR ua.pengguna_id = 44 LIMIT 1");
if ($check) {
    $row = $check->fetch_assoc();
    echo "Matching account row: ";
    print_r($row);
}

?>