<?php
/**
 * Global HTML Template untuk RANDIS
 * Template ini akan digunakan di semua halaman untuk konsistensi
 */

function render_page_head($title = "RANDIS", $additional_css = [], $additional_js = []) {
    $base_url = '/randis';
    echo "<!DOCTYPE html>\n";
    echo "<html lang='id'>\n";
    echo "<head>\n";
    echo "    <meta charset='UTF-8'>\n";
    echo "    <meta name='viewport' content='width=device-width, initial-scale=1.0'>\n";
    echo "    <title>{$title} - Sistem Manajemen Kendaraan TNI</title>\n";
    
    // Favicon
    echo "    <link rel='icon' type='image/x-icon' href='{$base_url}/assets/images/favicon.ico'>\n";
    
    // CSS Framework & Icons
    echo "    <link href='https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css' rel='stylesheet'>\n";
    echo "    <link href='https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css' rel='stylesheet'>\n";
    
    // Global CSS
    echo "    <link href='{$base_url}/assets/css/global.css' rel='stylesheet'>\n";
    echo "    <link href='{$base_url}/assets/css/sidebar.css' rel='stylesheet'>\n";
    
    // Additional CSS
    foreach ($additional_css as $css) {
        if (strpos($css, 'http') === 0) {
            echo "    <link href='{$css}' rel='stylesheet'>\n";
        } else {
            echo "    <link href='{$base_url}/{$css}' rel='stylesheet'>\n";
        }
    }
    
    echo "</head>\n";
    echo "<body>\n";
}

function render_page_footer($additional_js = []) {
    $base_url = '/randis';
    
    // Core JavaScript
    echo "    <script src='https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js'></script>\n";
    echo "    <script src='https://code.jquery.com/jquery-3.6.0.min.js'></script>\n";
    echo "    <script src='{$base_url}/assets/js/sidebar.js'></script>\n";
    echo "    <script src='{$base_url}/assets/js/global.js'></script>\n";
    
    // Additional JavaScript
    foreach ($additional_js as $js) {
        if (strpos($js, 'http') === 0) {
            echo "    <script src='{$js}'></script>\n";
        } else {
            echo "    <script src='{$base_url}/{$js}'></script>\n";
        }
    }
    
    echo "</body>\n";
    echo "</html>\n";
}

function render_page_header($user_info = null) {
    echo "<div class='page-header'>\n";
    echo "    <div>\n";
    echo "        <h1><i class='fas fa-tachometer-alt'></i> Dashboard RANDIS</h1>\n";
    echo "        <nav class='breadcrumb-nav'>\n";
    echo "            <ol class='breadcrumb'>\n";
    echo "                <li class='breadcrumb-item'><a href='/randis/'>Home</a></li>\n";
    echo "                <li class='breadcrumb-item active'>Dashboard</li>\n";
    echo "            </ol>\n";
    echo "        </nav>\n";
    echo "    </div>\n";
    
    if ($user_info) {
        echo "    <div class='header-actions'>\n";
        echo "        <div class='current-user-info'>\n";
        echo "            <div class='user-avatar'>\n";
        echo "                <i class='fas fa-user-circle'></i>\n";
        echo "            </div>\n";
        echo "            <div class='user-details'>\n";
        echo "                <h6>{$user_info['nama_lengkap']}</h6>\n";
        echo "                <div class='user-meta'>\n";
        echo "                    <span class='rank'>{$user_info['pangkat']}</span>\n";
        echo "                    <span class='nrp'>{$user_info['nrp_nip']}</span>\n";
        echo "                </div>\n";
        echo "            </div>\n";
        echo "        </div>\n";
        echo "        <div class='btn-group'>\n";
        echo "            <a href='/randis/pages/profil.php' class='btn btn-secondary btn-sm'>\n";
        echo "                <i class='fas fa-user'></i> Profil\n";
        echo "            </a>\n";
        echo "            <a href='/randis/logout.php' class='btn btn-danger btn-sm'>\n";
        echo "                <i class='fas fa-sign-out-alt'></i> Logout\n";
        echo "            </a>\n";
        echo "        </div>\n";
        echo "    </div>\n";
    }
    
    echo "</div>\n";
}

function render_sidebar($current_page = '', $user_role = 'user') {
    // Gunakan markup sidebar terpusat untuk menghindari duplikasi
    $sidebarPath = __DIR__ . '/../includes/sidebar.php';
    if (file_exists($sidebarPath)) {
        include $sidebarPath;
    }
}

// Removed legacy render_mobile_menu_toggle(): use #sidebarToggle in sidebar header

function render_loading_spinner() {
    echo "<div class='loading-spinner' id='loadingSpinner' style='display: none;'>\n";
    echo "    <div class='spinner-border text-primary' role='status'>\n";
    echo "        <span class='visually-hidden'>Loading...</span>\n";
    echo "    </div>\n";
    echo "</div>\n";
}

function render_notification_container() {
    echo "<div class='notification-container' id='notificationContainer'></div>\n";
}

function render_confirmation_modal() {
    echo "<div class='modal fade' id='confirmationModal' tabindex='-1'>\n";
    echo "    <div class='modal-dialog'>\n";
    echo "        <div class='modal-content'>\n";
    echo "            <div class='modal-header'>\n";
    echo "                <h5 class='modal-title'>Konfirmasi</h5>\n";
    echo "                <button type='button' class='btn-close' data-bs-dismiss='modal'></button>\n";
    echo "            </div>\n";
    echo "            <div class='modal-body'>\n";
    echo "                <p id='confirmationMessage'>Apakah Anda yakin ingin melanjutkan?</p>\n";
    echo "            </div>\n";
    echo "            <div class='modal-footer'>\n";
    echo "                <button type='button' class='btn btn-secondary' data-bs-dismiss='modal'>Batal</button>\n";
    echo "                <button type='button' class='btn btn-primary' id='confirmationOk'>Ya</button>\n";
    echo "            </div>\n";
    echo "        </div>\n";
    echo "    </div>\n";
    echo "</div>\n";
}

if (!function_exists('get_status_badge')) {
    function get_status_badge($status, $type = 'general') {
        $status_lower = strtolower($status);
        
        switch ($type) {
            case 'vehicle':
                switch ($status_lower) {
                    case 'baik': return "<span class='badge badge-success'>Baik</span>";
                    case 'rusak ringan': return "<span class='badge badge-warning'>Rusak Ringan</span>";
                    case 'rusak berat': return "<span class='badge badge-danger'>Rusak Berat</span>";
                    case 'operasional': return "<span class='badge badge-success'>Operasional</span>";
                    case 'perbaikan': return "<span class='badge badge-warning'>Perbaikan</span>";
                    case 'tersedia': return "<span class='badge badge-success'>Tersedia</span>";
                    case 'dipinjam': return "<span class='badge badge-warning'>Dipinjam</span>";
                    default: return "<span class='badge badge-secondary'>{$status}</span>";
                }
                
            case 'maintenance':
                switch ($status_lower) {
                    case 'terjadwal': return "<span class='badge badge-info'>Terjadwal</span>";
                    case 'dalam proses': return "<span class='badge badge-warning'>Dalam Proses</span>";
                    case 'selesai': return "<span class='badge badge-success'>Selesai</span>";
                    case 'dibatalkan': return "<span class='badge badge-secondary'>Dibatalkan</span>";
                    default: return "<span class='badge badge-secondary'>{$status}</span>";
                }
                
            case 'loan':
                switch ($status_lower) {
                    case 'pending': return "<span class='badge badge-warning'>Pending</span>";
                    case 'approved': return "<span class='badge badge-success'>Disetujui</span>";
                    case 'ongoing': return "<span class='badge badge-info'>Berlangsung</span>";
                    case 'completed': return "<span class='badge badge-success'>Selesai</span>";
                    case 'cancelled': return "<span class='badge badge-secondary'>Dibatalkan</span>";
                    case 'rejected': return "<span class='badge badge-danger'>Ditolak</span>";
                    default: return "<span class='badge badge-secondary'>{$status}</span>";
                }
                
            default:
                switch ($status_lower) {
                    case 'aktif': return "<span class='badge badge-success'>Aktif</span>";
                    case 'tidak aktif': return "<span class='badge badge-secondary'>Tidak Aktif</span>";
                    case 'pending': return "<span class='badge badge-warning'>Pending</span>";
                    case 'approved': return "<span class='badge badge-success'>Disetujui</span>";
                    case 'rejected': return "<span class='badge badge-danger'>Ditolak</span>";
                    default: return "<span class='badge badge-secondary'>{$status}</span>";
                }
        }
    }
}

if (!function_exists('format_currency')) {
    function format_currency($amount) {
        return 'Rp ' . number_format($amount, 0, ',', '.');
    }
}

if (!function_exists('format_date')) {
    function format_date($date, $format = 'd/m/Y') {
        if (empty($date) || $date == '0000-00-00' || $date == '0000-00-00 00:00:00') {
            return '-';
        }
        return date($format, strtotime($date));
    }
}

if (!function_exists('format_datetime')) {
    function format_datetime($datetime, $format = 'd/m/Y H:i') {
        if (empty($datetime) || $datetime == '0000-00-00 00:00:00') {
            return '-';
        }
        return date($format, strtotime($datetime));
    }
}
?>
