<?php
// Include global template (anchored to pages dir)
require_once __DIR__ . '/../templates/page_template.php';

require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../lib/table_helpers.php';

$current_role = get_current_role();
$current_user_id = get_current_user_id();

// Role-based access control
if ($current_role === 'guest') {
    // Guest should use kendaraan_publik page instead
    header('Location: index.php?page=kendaraan_publik');
    exit;
} elseif ($current_role === 'user') {
    // User can only see their assigned vehicles
    require_login();
} else {
    // Operator and Admin need login and appropriate permissions
    require_login();
}

$can_crud = can_operate();
$action = $_GET['action'] ?? '';
$id = (int)($_GET['id'] ?? 0);
$keyword = trim($_GET['q'] ?? '');

$msg = '';

// Handle form submissions
if ($_POST) {
    if (!validate_csrf_token($_POST['csrf_token'] ?? '')) {
        $msg = '<div class="alert alert-danger">Token keamanan tidak valid!</div>';
    } else {
        if ($action === 'add' && $can_crud) {
            $no_polisi = trim($_POST['no_polisi']);
            $no_rangka = trim($_POST['no_rangka']);
            $no_mesin = trim($_POST['no_mesin']);
            $merk = trim($_POST['merk']);
            $tipe = trim($_POST['tipe']);
            $tahun_pembuatan = (int)($_POST['tahun_pembuatan'] ?: null);
            $warna = trim($_POST['warna']);
            $jenis = trim($_POST['jenis']);
            $bahan_bakar = trim($_POST['bahan_bakar']);
            $kondisi = trim($_POST['kondisi']);
            $status_kendaraan = trim($_POST['status_kendaraan']);
            
            if ($no_polisi && $merk) {
                $stmt = $mysqli->prepare("INSERT INTO kendaraan (no_polisi, no_rangka, no_mesin, merk, tipe, tahun_pembuatan, warna, jenis, bahan_bakar, kondisi, status_kendaraan, created_at) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())");
                $stmt->bind_param("sssssssssss", $no_polisi, $no_rangka, $no_mesin, $merk, $tipe, $tahun_pembuatan, $warna, $jenis, $bahan_bakar, $kondisi, $status_kendaraan);
                
                try {
                    if ($stmt->execute()) {
                        $vehicle_id = $mysqli->insert_id;
                        log_activity('CREATE_VEHICLE', "Menambahkan kendaraan baru: $no_polisi - $merk");
                        $msg = '<div class="alert alert-success">Kendaraan berhasil ditambahkan!</div>';
                        $action = ''; // Reset action
                    } else {
                        $msg = '<div class="alert alert-danger">Gagal menambahkan kendaraan: ' . $mysqli->error . '</div>';
                    }
                } catch (Exception $e) {
                    $msg = '<div class="alert alert-danger">Error: ' . $e->getMessage() . '</div>';
                }
                $stmt->close();
            } else {
                $msg = '<div class="alert alert-danger">Nomor polisi dan merk harus diisi!</div>';
            }
            
        } elseif ($action === 'import_csv' && $can_crud) {
            if (isset($_FILES['csv_file']) && $_FILES['csv_file']['error'] === UPLOAD_ERR_OK) {
                $csv_file = $_FILES['csv_file']['tmp_name'];
                $file_extension = pathinfo($_FILES['csv_file']['name'], PATHINFO_EXTENSION);
                
                if (strtolower($file_extension) !== 'csv') {
                    $msg = '<div class="alert alert-danger">File harus berformat CSV!</div>';
                } else {
                    $imported = 0;
                    $errors = [];
                    
                    if (($handle = fopen($csv_file, "r")) !== FALSE) {
                        $row = 0;

                        // Read and normalize header row (skip it) - handle possible BOM on first cell
                        $header = fgetcsv($handle);
                        $row++;
                        if ($header === FALSE) {
                            $msg = '<div class="alert alert-danger">File CSV kosong atau tidak dapat dibaca.</div>';
                            fclose($handle);
                        } else {
                            // optional: normalize header values
                            foreach ($header as &$h) {
                                $h = trim(preg_replace('/\x{FEFF}/u', '', $h));
                                $h = strtolower($h);
                            }

                            while (($data = fgetcsv($handle, 1000, ",")) !== FALSE) {
                                $row++;

                                // skip completely empty rows
                                $allEmpty = true;
                                foreach ($data as $cell) {
                                    if (trim($cell) !== '') { $allEmpty = false; break; }
                                }
                                if ($allEmpty) continue;

                                if (count($data) < 14) {
                                    $errors[] = "Baris $row: kolom tidak lengkap (ditemukan " . count($data) . ")";
                                    continue;
                                }

                                // map columns
                                $no_polisi = trim($data[0]);
                                $merk = trim($data[1]);
                                $tipe = trim($data[2]);
                                $tahun_pembuatan = ($data[3] === '' ? null : (int)$data[3]);
                                $warna = trim($data[4]);
                                $kapasitas_penumpang = ($data[5] === '' ? null : (int)$data[5]);
                                $kapasitas_angkut = ($data[6] === '' ? null : (int)$data[6]);
                                $jenis_bbm = trim($data[7]);
                                $status_kendaraan = trim($data[8]);
                                $no_rangka = trim($data[9]);
                                $no_mesin = trim($data[10]);
                                $no_bpkb = trim($data[11]);
                                $no_stnk = trim($data[12]);
                                $keterangan = trim($data[13]);

                                if ($no_polisi === '' || $merk === '') {
                                    $errors[] = "Baris $row: nomor polisi atau merk kosong";
                                    continue;
                                }

                                // Check if vehicle already exists
                                $check_stmt = $mysqli->prepare("SELECT id FROM kendaraan WHERE no_polisi = ?");
                                $check_stmt->bind_param("s", $no_polisi);
                                $check_stmt->execute();
                                $existing = $check_stmt->get_result()->fetch_assoc();
                                $check_stmt->close();

                                if ($existing) {
                                    $errors[] = "Baris $row: Kendaraan $no_polisi sudah ada";
                                    continue;
                                }

                                // Insert row - map CSV fields to kendaraan columns
                                $jenis = ''; // CSV doesn't supply vehicle 'jenis' (Roda 2/4 etc) so leave empty
                                $bahan_bakar = $jenis_bbm;

                                $stmt = $mysqli->prepare("INSERT INTO kendaraan (no_polisi, merk, tipe, tahun_pembuatan, warna, jenis, bahan_bakar, no_rangka, no_mesin, catatan, created_at) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())");
                                if ($stmt === false) {
                                    $errors[] = "Baris $row: gagal menyiapkan statement: " . $mysqli->error;
                                    continue;
                                }

                                // types: s (no_polisi), s (merk), s (tipe), i (tahun), s (warna), s (jenis), s (bahan_bakar), s (no_rangka), s (no_mesin), s (catatan)
                                $types = 'sssissssss';
                                $tahun_param = ($tahun_pembuatan === null ? 0 : $tahun_pembuatan);
                                $stmt->bind_param($types, $no_polisi, $merk, $tipe, $tahun_param, $warna, $jenis, $bahan_bakar, $no_rangka, $no_mesin, $keterangan);

                                if ($stmt->execute()) {
                                    $imported++;
                                    log_activity('IMPORT_VEHICLE', "Import kendaraan: $no_polisi - $merk");
                                } else {
                                    $errors[] = "Baris $row: Gagal import $no_polisi: " . $mysqli->error;
                                }
                                $stmt->close();
                            }

                            fclose($handle);

                            $msg = '<div class="alert alert-success">Berhasil import ' . $imported . ' kendaraan!</div>';
                            if (!empty($errors)) {
                                $msg .= '<div class="alert alert-warning">Beberapa data gagal diimport:<ul>';
                                foreach ($errors as $error) {
                                    $msg .= '<li>' . htmlspecialchars($error) . '</li>';
                                }
                                $msg .= '</ul></div>';
                            }
                            $action = ''; // Reset action
                        }
                    } else {
                        $msg = '<div class="alert alert-danger">Gagal membuka file CSV!</div>';
                    }
                }
            } else {
                $msg = '<div class="alert alert-danger">File CSV harus dipilih!</div>';
            }
        } elseif ($action === 'edit' && $can_crud && $id) {
            $no_polisi = trim($_POST['no_polisi']);
            $no_rangka = trim($_POST['no_rangka']);
            $no_mesin = trim($_POST['no_mesin']);
            $merk = trim($_POST['merk']);
            $tipe = trim($_POST['tipe']);
            $tahun_pembuatan = (int)($_POST['tahun_pembuatan'] ?: null);
            $warna = trim($_POST['warna']);
            $jenis = trim($_POST['jenis']);
            $bahan_bakar = trim($_POST['bahan_bakar']);
            $kondisi = trim($_POST['kondisi']);
            $status_kendaraan = trim($_POST['status_kendaraan']);
            
            if ($no_polisi && $merk) {
                $stmt = $mysqli->prepare("UPDATE kendaraan SET no_polisi=?, no_rangka=?, no_mesin=?, merk=?, tipe=?, tahun_pembuatan=?, warna=?, jenis=?, bahan_bakar=?, kondisi=?, status_kendaraan=?, updated_at=NOW() WHERE id=?");
                $stmt->bind_param("sssssssssssi", $no_polisi, $no_rangka, $no_mesin, $merk, $tipe, $tahun_pembuatan, $warna, $jenis, $bahan_bakar, $kondisi, $status_kendaraan, $id);
                
                if ($stmt->execute()) {
                    log_activity('UPDATE_VEHICLE', "Mengupdate kendaraan: $no_polisi - $merk");
                    $msg = '<div class="alert alert-success">Kendaraan berhasil diupdate!</div>';
                    $action = ''; // Reset action
                } else {
                    $msg = '<div class="alert alert-danger">Gagal mengupdate kendaraan!</div>';
                }
                $stmt->close();
            } else {
                $msg = '<div class="alert alert-danger">Nomor polisi dan merk harus diisi!</div>';
            }
        }
    }
}

// Handle delete action
if ($action === 'delete' && can_admin() && $id) {
    if (isset($_GET['confirm']) && $_GET['confirm'] === 'yes') {
        $stmt = $mysqli->prepare("SELECT no_polisi, merk FROM kendaraan WHERE id = ?");
        $stmt->bind_param("i", $id);
        $stmt->execute();
        $result = $stmt->get_result();
        $vehicle_info = $result->fetch_assoc();
        $stmt->close();
        
        if ($vehicle_info) {
            $stmt = $mysqli->prepare("DELETE FROM kendaraan WHERE id = ?");
            $stmt->bind_param("i", $id);
            
            if ($stmt->execute()) {
                log_activity('DELETE_VEHICLE', "Menghapus kendaraan: {$vehicle_info['no_polisi']} - {$vehicle_info['merk']}");
                $msg = '<div class="alert alert-success">Kendaraan berhasil dihapus!</div>';
            } else {
                $msg = '<div class="alert alert-danger">Gagal menghapus kendaraan!</div>';
            }
            $stmt->close();
        }
        $action = ''; // Reset action
    } else {
        $msg = '<div class="alert alert-warning">
            <strong>Konfirmasi Hapus:</strong> Yakin ingin menghapus kendaraan ini? 
            <a href="index.php?page=kendaraan&action=delete&id=' . $id . '&confirm=yes" class="btn btn-danger btn-sm">Ya, Hapus</a>
            <a href="index.php?page=kendaraan" class="btn btn-secondary btn-sm">Batal</a>
        </div>';
    }
}

// Get edit data if needed
$edit_data = null;
if ($action === 'edit' && $id) {
    $stmt = $mysqli->prepare("SELECT * FROM kendaraan WHERE id = ?");
    $stmt->bind_param("i", $id);
    $stmt->execute();
    $result = $stmt->get_result();
    $edit_data = $result->fetch_assoc();
    $stmt->close();
    
    if (!$edit_data) {
        header('Location: index.php?page=kendaraan');
        exit;
    }
}

// Get vehicles based on role
$vehicles = get_accessible_vehicles($current_role, $current_user_id, $keyword);

// Page title based on role
$page_title = match($current_role) {
    'user' => 'Kendaraan Saya',
    'operator' => 'Kelola Kendaraan',
    'admin' => 'Manajemen Kendaraan',
    default => 'Data Kendaraan'
};

$page_description = match($current_role) {
    'user' => 'Daftar kendaraan yang ditugaskan kepada Anda',
    'operator' => 'Kelola data kendaraan di unit kerja Anda',
    'admin' => 'Manajemen lengkap data kendaraan',
    default => 'Daftar kendaraan dinas'
};

if ($can_crud && !$action):

endif;

if ($can_crud && $action === 'add'):

elseif ($can_crud && $action === 'edit' && $edit_data):

else:

if ($keyword !== ''):

endif;

if ($current_role !== 'user'):

endif;

if (count($vehicles) > 0):

$no = 1; foreach ($vehicles as $vehicle):

if ($vehicle['tipe']):

endif;

if ($current_role !== 'user'):

if (isset($vehicle['current_user_name']) && $vehicle['current_user_name']):

else:

endif;

endif;

if ($can_crud):

endif;

if (can_admin()):

endif;

endforeach;

else:

if ($keyword):

elseif ($current_role === 'USER'):

else:

endif;

if ($can_crud && !$keyword):

endif;

endif;

endif;

// Page configuration
$page_title = "Kendaraan";
$current_page = "kendaraan";
$additional_css = [];
$additional_js = [];

// Get user info (implement your user data logic here)
$user_info = [
    'nama_lengkap' => $_SESSION['nama_lengkap'] ?? 'User',
    'pangkat' => $_SESSION['pangkat'] ?? '',
    'nrp_nip' => $_SESSION['nrp_nip'] ?? ''
];

// Render page head
render_page_head($page_title, $additional_css, $additional_js);

// Render sidebar
render_sidebar($current_page, 'operator');
?>

<main>
    <?php render_page_header($user_info); ?>

    <div class="container-fluid">
        ">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Batal</button>
                    <button type="submit" class="btn btn-primary">
                        <i class="fas fa-upload"></i> Import Data
                    </button>
                </div>
    <script>
    // Move import modal to body and enforce z-index so header/sidebar don't cover it
    ;(function(){
        try {
            if (typeof $ !== 'undefined') {
                var $modal = $('#importModal');
                if ($modal.length) {
                    $modal.appendTo(document.body);

                    $modal.on('show.bs.modal', function() {
                        setTimeout(function(){
                            $('.modal-backdrop').css({ 'z-index': 29999, 'position': 'fixed' });
                            $modal.css({ 'z-index': 30000, 'position': 'fixed' });
                            $modal.find('.modal-dialog').css('z-index', 30001);
                        }, 20);
                    });

                    $modal.on('shown.bs.modal', function() {
                        $('.modal-backdrop.show').css({ 'z-index': 29999, 'position': 'fixed' });
                        $modal.css({ 'z-index': 30000, 'position': 'fixed' });
                        $modal.find('.modal-dialog').css('z-index', 30001);
                    });
                }
            }
        } catch (e) {
            console && console.debug && console.debug('importModal script error', e);
        }
    })();
    </script>
            </form>
        </div>
    </div>
</div>
    </div>
</main>

<?php
// Render page footer
render_page_footer($additional_js);
?>
