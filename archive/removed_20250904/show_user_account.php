<?php
require 'config.php';
$conn = new mysqli(DB_HOST, DB_USER, DB_PASS, DB_NAME);
if ($conn->connect_error) die('Connection failed: ' . $conn->connect_error);
$res = $conn->query("SHOW CREATE TABLE user_account");
if ($res && $row = $res->fetch_array()) {
    echo $row[1] . "\n";
} else {
    echo "No user_account table or error: " . $conn->error . "\n";
}
$conn->close();
?>
