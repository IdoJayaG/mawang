<?php
/**
 * Test script untuk mengecek semua functionality kendaraan
 */

require_once 'config/db.php';
require_once 'includes/auth.php';

echo "=== RANDIS - Test Vehicle Management System ===\n\n";

// 1. Test Database Connection
echo "1. Testing Database Connection...\n";
if ($mysqli->ping()) {
    echo "✓ Database connection: OK\n";
} else {
    echo "✗ Database connection: FAILED\n";
    exit(1);
}

// 2. Test Tables Existence
echo "\n2. Testing Required Tables...\n";
$required_tables = ['kendaraan', 'log_aktivitas', 'user_account', 'riwayat_perawatan'];

foreach ($required_tables as $table) {
    $result = $mysqli->query("SHOW TABLES LIKE '$table'");
    if ($result->num_rows > 0) {
        echo "✓ Table '$table': EXISTS\n";
    } else {
        echo "✗ Table '$table': NOT FOUND\n";
    }
}

// 3. Test Kendaraan Table Structure
echo "\n3. Testing Kendaraan Table Structure...\n";
$result = $mysqli->query("DESCRIBE kendaraan");
if ($result) {
    $columns = [];
    while ($row = $result->fetch_assoc()) {
        $columns[] = $row['Field'];
    }
    
    $required_columns = [
        'id_kendaraan', 'no_polisi', 'merk', 'tipe', 'tahun_pembuatan', 
        'warna', 'jenis', 'bahan_bakar', 'kondisi', 'status_kendaraan',
        'no_rangka', 'no_mesin', 'no_bpkb', 'no_stnk'
    ];
    
    foreach ($required_columns as $col) {
        if (in_array($col, $columns)) {
            echo "✓ Column '$col': EXISTS\n";
        } else {
            echo "✗ Column '$col': MISSING\n";
        }
    }
} else {
    echo "✗ Could not describe kendaraan table\n";
}

// 4. Test Sample Data
echo "\n4. Testing Sample Data...\n";
$result = $mysqli->query("SELECT COUNT(*) as count FROM kendaraan");
if ($result) {
    $row = $result->fetch_assoc();
    echo "✓ Kendaraan records: " . $row['count'] . "\n";
} else {
    echo "✗ Could not count kendaraan records\n";
}

// 5. Test Log Aktivitas Table
echo "\n5. Testing Log Aktivitas Table...\n";
$result = $mysqli->query("SELECT COUNT(*) as count FROM log_aktivitas");
if ($result) {
    $row = $result->fetch_assoc();
    echo "✓ Log aktivitas records: " . $row['count'] . "\n";
} else {
    echo "✗ Could not count log aktivitas records\n";
}

// 6. Test INSERT operation (simulated)
echo "\n6. Testing INSERT Operation...\n";
$test_data = [
    'no_polisi' => 'TEST123',
    'merk' => 'Test Brand',
    'tipe' => 'Test Model',
    'tahun_pembuatan' => 2023,
    'warna' => 'Test Color',
    'jenis' => 'Roda 4',
    'bahan_bakar' => 'Bensin',
    'kondisi' => 'Baik',
    'status_kendaraan' => 'Operasional'
];

$columns = implode(', ', array_keys($test_data));
$placeholders = str_repeat('?,', count($test_data) - 1) . '?';
$sql = "INSERT INTO kendaraan ($columns) VALUES ($placeholders)";

$stmt = $mysqli->prepare($sql);
if ($stmt) {
    $types = str_repeat('s', count($test_data));
    $stmt->bind_param($types, ...array_values($test_data));
    
    if ($stmt->execute()) {
        echo "✓ INSERT operation: SUCCESS\n";
        $test_id = $mysqli->insert_id;
        
        // Test UPDATE
        $update_sql = "UPDATE kendaraan SET kondisi = 'Rusak Ringan' WHERE id_kendaraan = ?";
        $update_stmt = $mysqli->prepare($update_sql);
        $update_stmt->bind_param('i', $test_id);
        
        if ($update_stmt->execute()) {
            echo "✓ UPDATE operation: SUCCESS\n";
        } else {
            echo "✗ UPDATE operation: FAILED\n";
        }
        
        // Clean up test data
        $delete_sql = "DELETE FROM kendaraan WHERE id_kendaraan = ?";
        $delete_stmt = $mysqli->prepare($delete_sql);
        $delete_stmt->bind_param('i', $test_id);
        $delete_stmt->execute();
        echo "✓ Test data cleaned up\n";
        
    } else {
        echo "✗ INSERT operation: FAILED - " . $stmt->error . "\n";
    }
} else {
    echo "✗ Could not prepare INSERT statement\n";
}

// 7. Test CSV Processing (logic test)
echo "\n7. Testing CSV Processing Logic...\n";
$csv_template = "C:\\xampp\\htdocs\\randis\\templates\\template_kendaraan.csv";
if (file_exists($csv_template)) {
    echo "✓ CSV template file: EXISTS\n";
    
    $handle = fopen($csv_template, 'r');
    if ($handle) {
        $header = fgetcsv($handle);
        echo "✓ CSV headers: " . implode(', ', $header) . "\n";
        
        $line_count = 1; // Header counted
        while (($data = fgetcsv($handle)) !== FALSE) {
            $line_count++;
        }
        fclose($handle);
        echo "✓ CSV total lines: $line_count\n";
    } else {
        echo "✗ Could not open CSV template\n";
    }
} else {
    echo "✗ CSV template file: NOT FOUND\n";
}

echo "\n=== Test Summary ===\n";
echo "All critical functionality tests completed.\n";
echo "If you see mostly ✓ marks, the system is ready for use.\n";
echo "Any ✗ marks indicate issues that need attention.\n\n";

$mysqli->close();
?>
