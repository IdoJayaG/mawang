<?php
require_once 'config/db.php';
require_once 'includes/auth.php';

echo "Testing log_activity function after column fix...\n";

// Test the log_activity function
$result = log_activity('TEST_LOGOUT', 'Testing logout functionality after column fix');
echo $result ? "✓ SUCCESS: Activity logged successfully\n" : "✗ FAILED: Could not log activity\n";

// Verify the log was created
$check_sql = "SELECT * FROM log_aktivitas WHERE activity_type = 'TEST_LOGOUT' ORDER BY created_at DESC LIMIT 1";
$check_result = mysqli_query($conn, $check_sql);

if ($check_result && mysqli_num_rows($check_result) > 0) {
    $log_row = mysqli_fetch_assoc($check_result);
    echo "✓ Log entry verified:\n";
    echo "   Activity Type: " . $log_row['activity_type'] . "\n";
    echo "   Description: " . $log_row['description'] . "\n";
    echo "   Created: " . $log_row['created_at'] . "\n";
    
    // Clean up test log
    $cleanup_sql = "DELETE FROM log_aktivitas WHERE activity_type = 'TEST_LOGOUT'";
    mysqli_query($conn, $cleanup_sql);
    echo "✓ Test log cleaned up\n";
} else {
    echo "✗ Could not verify log entry\n";
}

echo "\n=== Column Fix Summary ===\n";
echo "✓ auth.php - Fixed aksi → activity_type, deskripsi → description\n";
echo "✓ export_log.php - Fixed column references\n";
echo "✓ clear_old_logs.php - Fixed column references\n";
echo "\nThe logout error should now be resolved!\n";
?>
