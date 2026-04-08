<?php
require_once 'config.php';

// Initialize database connection
$mysqli = new mysqli(DB_HOST, DB_USER, DB_PASS, DB_NAME);
if ($mysqli->connect_error) {
    die("Connection failed: " . $mysqli->connect_error);
}

echo "Membuat tabel log_aktivitas...\n";

// Create log_aktivitas table
$create_table_sql = "
CREATE TABLE IF NOT EXISTS `log_aktivitas` (
    `id` int(11) NOT NULL AUTO_INCREMENT,
    `user_id` int(11) NOT NULL,
    `activity_type` varchar(50) NOT NULL,
    `description` text NOT NULL,
    `ip_address` varchar(45) DEFAULT NULL,
    `user_agent` text DEFAULT NULL,
    `session_id` varchar(128) DEFAULT NULL,
    `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
    PRIMARY KEY (`id`),
    KEY `user_id` (`user_id`),
    KEY `activity_type` (`activity_type`),
    KEY `created_at` (`created_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
";

if ($mysqli->query($create_table_sql)) {
    echo "✓ Tabel log_aktivitas berhasil dibuat\n";
} else {
    echo "✗ Error membuat tabel log_aktivitas: " . $mysqli->error . "\n";
}

// Add some sample data if needed
$sample_data = "
INSERT IGNORE INTO `log_aktivitas` (`id`, `user_id`, `activity_type`, `description`, `ip_address`, `created_at`) VALUES
(1, 1, 'LOGIN', 'User login ke sistem', '127.0.0.1', NOW()),
(2, 1, 'VIEW_VEHICLE', 'Melihat daftar kendaraan', '127.0.0.1', NOW()),
(3, 1, 'CREATE_VEHICLE', 'Menambah kendaraan baru', '127.0.0.1', NOW());
";

if ($mysqli->multi_query($sample_data)) {
    echo "✓ Sample data log_aktivitas berhasil ditambahkan\n";
    // Clear any remaining results
    while ($mysqli->next_result()) {;}
} else {
    echo "✗ Error menambah sample data: " . $mysqli->error . "\n";
}

echo "Selesai!\n";
$mysqli->close();
?>
