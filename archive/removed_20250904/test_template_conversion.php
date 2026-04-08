<?php
/**
 * Script untuk testing halaman yang telah dikonversi
 * Memverifikasi bahwa template dan CSS bekerja dengan baik
 */

echo "=== TESTING TEMPLATE CONVERSION ===\n\n";

// Test configuration
$base_url = 'http://localhost/randis';
$pages_to_test = [
    'pages/dashboard_admin.php',
    'pages/dashboard_user.php', 
    'pages/kendaraan.php',
    'pages/dokumen_kendaraan.php',
    'pages/persetujuan_peminjaman.php',
    'pages/monitoring_peminjaman.php',
    'pages/jadwal_perawatan.php',
    'pages/riwayat_perawatan.php',
    'pages/log_bahan_bakar.php',
    'pages/surat_tugas.php',
    'pages/profil.php',
    'pages/home.php',
    'pages/pinjam_pakai.php'
];

function testPageSyntax($file_path) {
    $output = shell_exec("php -l \"$file_path\" 2>&1");
    return strpos($output, 'No syntax errors') !== false;
}

function testFileExists($file_path) {
    return file_exists($file_path);
}

function testTemplateInclusion($file_path) {
    if (!file_exists($file_path)) return false;
    
    $content = file_get_contents($file_path);
    return strpos($content, 'page_template.php') !== false;
}

function testCSSReferences($file_path) {
    if (!file_exists($file_path)) return false;
    
    $content = file_get_contents($file_path);
    
    // Check for old style patterns that should be removed
    $has_inline_style = preg_match('/<style[^>]*>/', $content);
    $has_hardcoded_css = preg_match('/<link[^>]*href=["\'][^"\']*\.css["\']/', $content);
    
    return !$has_inline_style && !$has_hardcoded_css;
}

function testTemplateStructure($file_path) {
    if (!file_exists($file_path)) return false;
    
    $content = file_get_contents($file_path);
    
    $has_render_head = strpos($content, 'render_page_head') !== false;
    $has_render_sidebar = strpos($content, 'render_sidebar') !== false;
    $has_render_footer = strpos($content, 'render_page_footer') !== false;
    
    return $has_render_head && $has_render_sidebar && $has_render_footer;
}

// Test results
$test_results = [];
$total_tests = 0;
$passed_tests = 0;

echo "Testing file syntax and structure:\n";
echo str_repeat("-", 80) . "\n";

foreach ($pages_to_test as $page) {
    $file_path = "C:/xampp/htdocs/randis/$page";
    $page_name = basename($page);
    
    echo sprintf("%-30s", $page_name);
    
    $tests = [
        'exists' => testFileExists($file_path),
        'syntax' => testPageSyntax($file_path),
        'template' => testTemplateInclusion($file_path),
        'css_clean' => testCSSReferences($file_path),
        'structure' => testTemplateStructure($file_path)
    ];
    
    $page_passed = 0;
    $page_total = count($tests);
    
    foreach ($tests as $test => $result) {
        if ($result) {
            $page_passed++;
            $passed_tests++;
        }
        $total_tests++;
    }
    
    $test_results[$page] = $tests;
    
    // Display results
    $status = ($page_passed === $page_total) ? '✅ PASS' : '❌ FAIL';
    echo sprintf(" %s (%d/%d)\n", $status, $page_passed, $page_total);
    
    // Show details if failed
    if ($page_passed < $page_total) {
        foreach ($tests as $test => $result) {
            if (!$result) {
                echo "    ❌ $test\n";
            }
        }
    }
}

echo str_repeat("-", 80) . "\n";

// Test CSS files
echo "\nTesting CSS files:\n";
echo str_repeat("-", 80) . "\n";

$css_files = [
    'assets/css/global.css',
    'assets/css/sidebar.css', 
    'assets/css/login.css',
    'assets/css/extracted-styles.css'
];

foreach ($css_files as $css_file) {
    $file_path = "C:/xampp/htdocs/randis/$css_file";
    $file_name = basename($css_file);
    
    echo sprintf("%-30s", $file_name);
    
    if (file_exists($file_path)) {
        $size = filesize($file_path);
        echo sprintf(" ✅ EXISTS (%s)\n", formatBytes($size));
    } else {
        echo " ❌ MISSING\n";
    }
}

// Test JavaScript files
echo "\nTesting JavaScript files:\n";
echo str_repeat("-", 80) . "\n";

$js_files = [
    'assets/js/global.js'
];

foreach ($js_files as $js_file) {
    $file_path = "C:/xampp/htdocs/randis/$js_file";
    $file_name = basename($js_file);
    
    echo sprintf("%-30s", $file_name);
    
    if (file_exists($file_path)) {
        $size = filesize($file_path);
        echo sprintf(" ✅ EXISTS (%s)\n", formatBytes($size));
    } else {
        echo " ❌ MISSING\n";
    }
}

// Test template files
echo "\nTesting template files:\n";
echo str_repeat("-", 80) . "\n";

$template_files = [
    'templates/page_template.php',
    'templates/example_page.php'
];

foreach ($template_files as $template_file) {
    $file_path = "C:/xampp/htdocs/randis/$template_file";
    $file_name = basename($template_file);
    
    echo sprintf("%-30s", $file_name);
    
    if (file_exists($file_path)) {
        $syntax_ok = testPageSyntax($file_path);
        $status = $syntax_ok ? '✅ VALID' : '❌ SYNTAX ERROR';
        echo " $status\n";
    } else {
        echo " ❌ MISSING\n";
    }
}

// Summary
echo "\n" . str_repeat("=", 80) . "\n";
echo "SUMMARY\n";
echo str_repeat("=", 80) . "\n";

$pass_rate = round(($passed_tests / $total_tests) * 100, 1);
echo "Overall: $passed_tests/$total_tests tests passed ($pass_rate%)\n\n";

// Detailed results
echo "Test breakdown:\n";
$test_names = [
    'exists' => 'File Exists',
    'syntax' => 'PHP Syntax',
    'template' => 'Template Include',
    'css_clean' => 'CSS Cleaned',
    'structure' => 'Template Structure'
];

foreach ($test_names as $test_key => $test_name) {
    $test_passed = 0;
    $test_total = count($pages_to_test);
    
    foreach ($test_results as $page => $tests) {
        if ($tests[$test_key]) {
            $test_passed++;
        }
    }
    
    $test_rate = round(($test_passed / $test_total) * 100, 1);
    echo sprintf("  %-20s: %d/%d (%s%%)\n", $test_name, $test_passed, $test_total, $test_rate);
}

// Recommendations
echo "\nRECOMMENDATIONS:\n";
echo str_repeat("-", 80) . "\n";

$failed_pages = [];
foreach ($test_results as $page => $tests) {
    $page_failed = false;
    foreach ($tests as $test => $result) {
        if (!$result) {
            $failed_pages[] = $page;
            $page_failed = true;
            break;
        }
    }
}

if (empty($failed_pages)) {
    echo "✅ All pages passed! Ready for production.\n";
} else {
    echo "❌ Issues found in " . count($failed_pages) . " pages:\n";
    foreach ($failed_pages as $page) {
        echo "  - $page\n";
    }
    echo "\nRecommended actions:\n";
    echo "1. Review failed pages and fix syntax errors\n";
    echo "2. Ensure all template functions are properly called\n";
    echo "3. Remove any remaining hardcoded CSS/JS links\n";
    echo "4. Test pages in browser for functionality\n";
}

echo "\nNEXT STEPS:\n";
echo "1. Test pages in web browser\n";
echo "2. Verify database connections work\n";
echo "3. Check user authentication and sessions\n";
echo "4. Test responsive design on mobile\n";
echo "5. Validate form submissions and AJAX calls\n";

function formatBytes($size, $precision = 2) {
    $base = log($size, 1024);
    $suffixes = array('B', 'KB', 'MB', 'GB');
    return round(pow(1024, $base - floor($base)), $precision) . ' ' . $suffixes[floor($base)];
}

echo "\nTesting completed!\n";
?>
