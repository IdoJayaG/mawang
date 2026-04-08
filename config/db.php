<?php
$host = "localhost";
$user = "root";
$pass = "";
$db   = "randis";
$conn = mysqli_connect($host, $user, $pass, $db);
if (!$conn) {
    die("Koneksi gagal: " . mysqli_connect_error());
}

// Provide both variable names for compatibility
$mysqli = $conn;

// Also provide a PDO connection for pages that use PDO ($pdo)
try {
    $pdo = new PDO("mysql:host={$host};dbname={$db};charset=utf8mb4", $user, $pass, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES => false,
    ]);
} catch (PDOException $e) {
    // If PDO connection fails, keep $pdo as null and allow pages to fallback to mysqli
    $pdo = null;
    error_log('PDO connection failed: ' . $e->getMessage());
}
?>
