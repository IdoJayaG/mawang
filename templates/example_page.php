<?php
// Include global template
require_once '../templates/page_template.php';

// Page configuration
$page_title = "Contoh Halaman";
$current_page = "example";
$additional_css = []; // Add custom CSS files if needed
$additional_js = []; // Add custom JS files if needed

// Authentication check (add your auth logic here)
session_start();
// Check if user is logged in...

// Get user info
$user_info = [
    'nama_lengkap' => 'Nama User',
    'pangkat' => 'Pangkat',
    'nrp_nip' => '123456789'
];

// Render page head
render_page_head($page_title, $additional_css, $additional_js);

// Render sidebar
render_sidebar($current_page, 'user'); // user, operator, admin
?>

<main>
    <?php render_page_header($user_info); ?>
    
    <div class="card">
        <div class="card-header">
            <h3><i class="fas fa-example"></i> Judul Halaman</h3>
        </div>
        <div class="card-body">
            <!-- Content halaman di sini -->
            <p>Ini adalah contoh halaman menggunakan template global.</p>
            
            <div class="form-group">
                <label for="example">Contoh Input:</label>
                <input type="text" class="form-control" id="example" name="example">
            </div>
            
            <div class="form-actions">
                <button type="button" class="btn btn-primary">
                    <i class="fas fa-save"></i> Simpan
                </button>
                <button type="button" class="btn btn-secondary">
                    <i class="fas fa-times"></i> Batal
                </button>
            </div>
        </div>
    </div>
</main>

<?php
// Render page footer
render_page_footer($additional_js);
?>
