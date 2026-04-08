<?php
// Move non-essential top-level files into archive/removed_YYYYMMDD for safe cleanup
require_once __DIR__ . '/../config.php';

header('Content-Type: text/plain; charset=utf-8');

$root = realpath(__DIR__ . '/..');
$today = date('Ymd');
$archiveDir = $root . DIRECTORY_SEPARATOR . 'archive' . DIRECTORY_SEPARATOR . 'removed_' . $today;
@mkdir($archiveDir, 0777, true);

// List of non-essential root files to remove (curated)
$toRemove = [
    'add_test_maintenance.php',
    'check_foreign_keys.php',
    'check_jadwal_table.php',
    'check_kendaraan_table.php',
    'check_structure.php',
    'check_surat.php',
    'check_table.php',
    'check_user_table.php',
    'complete_db_setup.php',
    'complete_db_update.php',
    'create_logo.php',
    'create_log_aktivitas.php',
    'create_surat_tugas.php',
    'create_surat_tugas_table.sql',
    'debug_login.php',
    'debug_print_logs.php',
    'FINAL_FIX_SUMMARY.php',
    'final_test.php',
    'final_vehicle_fix.php',
    'fix_database.php',
    'fix_foreign_keys.sql',
    'fix_manajemen_user.php',
    'fix_table_structure.php',
    'kendaraan (1).sql',
    'manual_update.php',
    'migrate_surat_tugas.php',
    'migration_script.sql',
    'MODERN_UI_SUMMARY.php',
    'normalization_script.sql',
    'randis (14).sql',
    'randis-backup.sql',
    'randis_backup_before_normalization.sql',
    'randis_import_ready.sql',
    'randis_normalized.sql',
    'run_db_updates.php',
    'setup_peminjaman_db.php',
    'setup_tni_hierarchy.php',
    'show_user_account.php',
    'sidebar_test.html',
    'system_status_final.php',
    'test_ajax_endpoint.php',
    'test_auth.php',
    'test_database.php',
    'test_db.php',
    'test_db_connection.php',
    'test_final_system.php',
    'test_login.php',
    'test_logout.php',
    'test_logout_complete.php',
    'test_logout_fix.php',
    'test_log_activity.php',
    'test_log_activity_manual.php',
    'test_page_load.php',
    'test_profil.php',
    'test_profil_simple.php',
    'test_surat_db.php',
    'test_surat_pdf.php',
    'test_template_conversion.php',
    'test_user_info.php',
    'test_vehicle_system.php',
    'update_database_structure.php',
    'update_dokumen_table.php',
    'update_passwords.php',
    'update_pengguna_structure.sql',
    'validate_params.php',
    'verify_fixes.php',
    // Dev utility scripts
    'clean_css.ps1',
    'comprehensive_css_cleanup.ps1'
];

// Keep safe whitelist (never move)
$whitelist = [
    'index.php','login.php','logout.php','config.php',
    'composer.json','composer.lock'
];

echo "Cleaning up non-essential root files...\n\n";
$moved = 0; $skipped = 0; $missing = 0; $errors = 0;
foreach ($toRemove as $file) {
    if (in_array($file, $whitelist, true)) { $skipped++; continue; }
    $src = $root . DIRECTORY_SEPARATOR . $file;
    if (!is_file($src)) { $missing++; continue; }
    $dest = $archiveDir . DIRECTORY_SEPARATOR . $file;
    // Ensure unique target if already archived
    if (file_exists($dest)) {
        $pathinfo = pathinfo($dest);
        $dest = $pathinfo['dirname'] . DIRECTORY_SEPARATOR . $pathinfo['filename'] . '_' . time() . (isset($pathinfo['extension']) ? '.' . $pathinfo['extension'] : '');
    }
    if (@rename($src, $dest)) {
        echo "Moved: {$file} -> archive\\removed_{$today}\\{$file}\n";
        $moved++;
    } else {
        if (@unlink($src)) {
            echo "Deleted (fallback): {$file}\n";
            $moved++;
        } else {
            echo "ERROR: Failed to move/delete {$file}\n";
            $errors++;
        }
    }
}

echo "\nSummary: moved={$moved}, missing={$missing}, skipped={$skipped}, errors={$errors}\n";
echo "Archive: " . str_replace($root . DIRECTORY_SEPARATOR, '', $archiveDir) . "\n";
?>
