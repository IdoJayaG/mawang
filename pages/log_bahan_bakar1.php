<?php
session_start();
require_once '../config.php';
require_once '../includes/functions.php';
require_once '../templates/page_template.php';

// Check authentication
if (!isset($_SESSION['user_id'])) {
    header('Location: ../login.php');
    exit();
}

// Get user role and permissions
$user_role = $_SESSION['role'] ?? 'user';
$can_crud = in_array($user_role, ['admin', 'operator']);

// Handle form submissions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['action'])) {
        $action = $_POST['action'];
        
        if ($action === 'add' && $can_crud) {
            // Add new log BBM
            try {
                $stmt = $pdo->prepare("
                    INSERT INTO log_bahan_bakar 
                    (kendaraan_id, tanggal_isi, jumlah_liter, harga_per_liter, 
                     total_harga, jenis_bbm, odometer, catatan, created_by) 
                    VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)
                ");
                
                $stmt->execute([
                    $_POST['kendaraan_id'],
                    $_POST['tanggal_isi'],
                    $_POST['jumlah_liter'],
                    $_POST['harga_per_liter'],
                    $_POST['total_harga'],
                    $_POST['jenis_bbm'],
                    $_POST['odometer'],
                    $_POST['catatan'] ?? '',
                    $_SESSION['user_id']
                ]);
                
                $_SESSION['swal'] = [
                    'type' => 'success',
                    'title' => 'Berhasil!',
                    'text' => 'Log bahan bakar berhasil ditambahkan.'
                ];
            } catch (Exception $e) {
                $_SESSION['swal'] = [
                    'type' => 'error',
                    'title' => 'Error!',
                    'text' => 'Gagal menambahkan log: ' . $e->getMessage()
                ];
            }
        } elseif ($action === 'edit' && $can_crud && isset($_POST['id'])) {
            // Edit existing log
            try {
                $stmt = $pdo->prepare("
                    UPDATE log_bahan_bakar 
                    SET kendaraan_id = ?, tanggal_isi = ?, jumlah_liter = ?, 
                        harga_per_liter = ?, total_harga = ?, jenis_bbm = ?, 
                        odometer = ?, catatan = ?, updated_by = ?, updated_at = NOW()
                    WHERE id = ?
                ");
                
                $stmt->execute([
                    $_POST['kendaraan_id'],
                    $_POST['tanggal_isi'],
                    $_POST['jumlah_liter'],
                    $_POST['harga_per_liter'],
                    $_POST['total_harga'],
                    $_POST['jenis_bbm'],
                    $_POST['odometer'],
                    $_POST['catatan'] ?? '',
                    $_SESSION['user_id'],
                    $_POST['id']
                ]);
                
                $_SESSION['swal'] = [
                    'type' => 'success',
                    'title' => 'Berhasil!',
                    'text' => 'Log bahan bakar berhasil diperbarui.'
                ];
            } catch (Exception $e) {
                $_SESSION['swal'] = [
                    'type' => 'error',
                    'title' => 'Error!',
                    'text' => 'Gagal memperbarui log: ' . $e->getMessage()
                ];
            }
        }
    }
    
    // Redirect to prevent resubmission
    header('Location: log_bahan_bakar.php');
    exit();
}

// Get action parameter
$action = $_GET['action'] ?? 'list';
$log_id = $_GET['id'] ?? null;

// Get data for forms
$vehicles = [];
$log_data = null;

try {
    // Get vehicles for dropdown
    $stmt = $pdo->query("SELECT id, nomor_polisi, merk, model FROM kendaraan ORDER BY nomor_polisi");
    $vehicles = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Get specific log data if editing or viewing
    if (($action === 'edit' || $action === 'view') && $log_id) {
        $stmt = $pdo->prepare("
            SELECT lbb.*, k.nomor_polisi, k.merk, k.model,
                   u.nama_lengkap as created_by_name
            FROM log_bahan_bakar lbb
            LEFT JOIN kendaraan k ON lbb.kendaraan_id = k.id
            LEFT JOIN users u ON lbb.created_by = u.id
            WHERE lbb.id = ?
        ");
        $stmt->execute([$log_id]);
        $log_data = $stmt->fetch(PDO::FETCH_ASSOC);
    }
} catch (Exception $e) {
    error_log("Error in log_bahan_bakar.php: " . $e->getMessage());
}

// Page configuration
$page_title = "Log Bahan Bakar";
$current_page = "log_bahan_bakar";
$additional_css = [];
$additional_js = ['assets/js/log-bbm.js'];

$user_info = [
    'nama_lengkap' => $_SESSION['nama_lengkap'] ?? 'User',
    'pangkat' => $_SESSION['pangkat'] ?? '',
    'nrp_nip' => $_SESSION['nrp_nip'] ?? ''
];

// Render based on action
if ($action === 'list') {
    // List view
    render_page_head($page_title, $additional_css, $additional_js);
    render_sidebar($current_page, $user_role);
    ?>

    <main class="main-content">
        <?php render_page_header($user_info); ?>

        <div class="container-fluid">
            <!-- SweetAlert2 notification -->
            <?php if (!empty($_SESSION['swal'])): ?>
                <script>
                document.addEventListener('DOMContentLoaded', function() {
                    Swal.fire({
                        icon: '<?php echo $_SESSION['swal']['type']; ?>',
                        title: '<?php echo $_SESSION['swal']['title']; ?>',
                        text: '<?php echo $_SESSION['swal']['text']; ?>',
                        timer: 3000,
                        showConfirmButton: false
                    });
                });
                </script>
                <?php unset($_SESSION['swal']); ?>
            <?php endif; ?>

            <div class="row">
                <div class="col-12">
                    <div class="card">
                        <div class="card-header d-flex justify-content-between align-items-center">
                            <h5 class="card-title mb-0">
                                <i class="fas fa-gas-pump me-2"></i>Log Bahan Bakar
                            </h5>
                            <?php if ($can_crud): ?>
                                <a href="log_bahan_bakar.php?action=add" class="btn btn-primary">
                                    <i class="fas fa-plus me-1"></i>Tambah Log
                                </a>
                            <?php endif; ?>
                        </div>
                        <div class="card-body">
                            <div id="log-bbm-container">
                                <?php
                                // Display logs with basic table
                                try {
                                    $stmt = $pdo->query("
                                        SELECT lbb.*, k.nomor_polisi, k.merk, k.model
                                        FROM log_bahan_bakar lbb
                                        LEFT JOIN kendaraan k ON lbb.kendaraan_id = k.id
                                        ORDER BY lbb.tanggal_isi DESC, lbb.created_at DESC
                                        LIMIT 50
                                    ");
                                    $logs = $stmt->fetchAll(PDO::FETCH_ASSOC);
                                    
                                    if (!empty($logs)): ?>
                                        <div class="table-responsive">
                                            <table class="table table-striped">
                                                <thead>
                                                    <tr>
                                                        <th>Tanggal</th>
                                                        <th>Kendaraan</th>
                                                        <th>Jenis BBM</th>
                                                        <th>Jumlah (L)</th>
                                                        <th>Harga/L</th>
                                                        <th>Total</th>
                                                        <th>Odometer</th>
                                                        <th>Aksi</th>
                                                    </tr>
                                                </thead>
                                                <tbody>
                                                    <?php foreach ($logs as $log): ?>
                                                    <tr>
                                                        <td><?php echo date('d/m/Y', strtotime($log['tanggal_isi'])); ?></td>
                                                        <td><?php echo htmlspecialchars($log['nomor_polisi']); ?></td>
                                                        <td><?php echo htmlspecialchars($log['jenis_bbm']); ?></td>
                                                        <td><?php echo number_format($log['jumlah_liter'], 2); ?></td>
                                                        <td>Rp <?php echo number_format($log['harga_per_liter']); ?></td>
                                                        <td>Rp <?php echo number_format($log['total_harga']); ?></td>
                                                        <td><?php echo number_format($log['odometer']); ?> km</td>
                                                        <td>
                                                            <div class="btn-group btn-group-sm">
                                                                <a href="log_bahan_bakar.php?action=view&id=<?php echo $log['id']; ?>" 
                                                                   class="btn btn-outline-info">
                                                                    <i class="fas fa-eye"></i>
                                                                </a>
                                                                <?php if ($can_crud): ?>
                                                                <a href="log_bahan_bakar.php?action=edit&id=<?php echo $log['id']; ?>" 
                                                                   class="btn btn-outline-warning">
                                                                    <i class="fas fa-edit"></i>
                                                                </a>
                                                                <?php endif; ?>
                                                            </div>
                                                        </td>
                                                    </tr>
                                                    <?php endforeach; ?>
                                                </tbody>
                                            </table>
                                        </div>
                                    <?php else: ?>
                                        <p class="text-center">Belum ada log bahan bakar.</p>
                                    <?php endif;
                                } catch (Exception $e) {
                                    echo '<p class="text-center text-danger">Error loading data: ' . $e->getMessage() . '</p>';
                                }
                                ?>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </main>

    <?php
    render_page_footer();

} elseif ($action === 'add' || $action === 'edit') {
    // Form view
    render_page_head($page_title . ' - ' . ($action === 'add' ? 'Tambah' : 'Edit'), $additional_css, $additional_js);
    render_sidebar($current_page, $user_role);
    ?>

    <main class="main-content">
        <?php render_page_header($user_info); ?>

        <div class="container-fluid">
            <div class="row">
                <div class="col-12">
                    <div class="card">
                        <div class="card-header">
                            <h5 class="card-title">
                                <i class="fas fa-gas-pump me-2"></i>
                                <?php echo $action === 'add' ? 'Tambah' : 'Edit'; ?> Log Bahan Bakar
                            </h5>
                        </div>
                        <div class="card-body">
                            <form id="log-bbm-form" method="POST">
                                <input type="hidden" name="action" value="<?php echo $action; ?>">
                                <?php if ($action === 'edit'): ?>
                                    <input type="hidden" name="id" value="<?php echo $log_data['id'] ?? ''; ?>">
                                <?php endif; ?>

                                <div class="row">
                                    <div class="col-md-6 mb-3">
                                        <label for="kendaraan_id" class="form-label">Kendaraan *</label>
                                        <select class="form-select" id="kendaraan_id" name="kendaraan_id" required>
                                            <option value="">Pilih Kendaraan</option>
                                            <?php foreach ($vehicles as $vehicle): ?>
                                                <option value="<?php echo $vehicle['id']; ?>" 
                                                    <?php echo ($action === 'edit' && $log_data && $log_data['kendaraan_id'] == $vehicle['id']) ? 'selected' : ''; ?>>
                                                    <?php echo htmlspecialchars($vehicle['nomor_polisi'] . ' - ' . $vehicle['merk'] . ' ' . $vehicle['model']); ?>
                                                </option>
                                            <?php endforeach; ?>
                                        </select>
                                    </div>

                                    <div class="col-md-6 mb-3">
                                        <label for="tanggal_isi" class="form-label">Tanggal Isi *</label>
                                        <input type="date" class="form-control" id="tanggal_isi" name="tanggal_isi" 
                                               value="<?php echo $log_data['tanggal_isi'] ?? date('Y-m-d'); ?>" required>
                                    </div>

                                    <div class="col-md-6 mb-3">
                                        <label for="jenis_bbm" class="form-label">Jenis BBM *</label>
                                        <select class="form-select" id="jenis_bbm" name="jenis_bbm" required>
                                            <?php
                                            $bbm_options = ['Pertalite', 'Pertamax', 'Pertamax Turbo', 'Solar', 'Dexlite'];
                                            $selected_bbm = $log_data['jenis_bbm'] ?? 'Pertalite';
                                            foreach ($bbm_options as $bbm):
                                            ?>
                                                <option value="<?php echo $bbm; ?>" 
                                                    <?php echo ($selected_bbm === $bbm) ? 'selected' : ''; ?>>
                                                    <?php echo $bbm; ?>
                                                </option>
                                            <?php endforeach; ?>
                                        </select>
                                    </div>

                                    <div class="col-md-6 mb-3">
                                        <label for="jumlah_liter" class="form-label">Jumlah (Liter) *</label>
                                        <input type="number" class="form-control" id="jumlah_liter" name="jumlah_liter" 
                                               step="0.01" min="0" value="<?php echo $log_data['jumlah_liter'] ?? ''; ?>" required>
                                    </div>

                                    <div class="col-md-6 mb-3">
                                        <label for="harga_per_liter" class="form-label">Harga per Liter *</label>
                                        <input type="number" class="form-control" id="harga_per_liter" name="harga_per_liter" 
                                               min="0" value="<?php echo $log_data['harga_per_liter'] ?? ''; ?>" required>
                                    </div>

                                    <div class="col-md-6 mb-3">
                                        <label for="total_harga" class="form-label">Total Harga *</label>
                                        <input type="number" class="form-control" id="total_harga" name="total_harga" 
                                               min="0" value="<?php echo $log_data['total_harga'] ?? ''; ?>" required readonly>
                                    </div>

                                    <div class="col-md-6 mb-3">
                                        <label for="odometer" class="form-label">Odometer (km) *</label>
                                        <input type="number" class="form-control" id="odometer" name="odometer" 
                                               min="0" value="<?php echo $log_data['odometer'] ?? ''; ?>" required>
                                    </div>

                                    <div class="col-12 mb-3">
                                        <label for="catatan" class="form-label">Catatan</label>
                                        <textarea class="form-control" id="catatan" name="catatan" rows="3"><?php echo htmlspecialchars($log_data['catatan'] ?? ''); ?></textarea>
                                    </div>
                                </div>

                                <div class="d-flex gap-2">
                                    <button type="submit" class="btn btn-primary">
                                        <i class="fas fa-save me-1"></i>Simpan
                                    </button>
                                    <a href="log_bahan_bakar.php" class="btn btn-secondary">
                                        <i class="fas fa-arrow-left me-1"></i>Kembali
                                    </a>
                                </div>
                            </form>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </main>

    <script>
    // Auto calculate total price
    document.addEventListener('DOMContentLoaded', function() {
        const jumlahInput = document.getElementById('jumlah_liter');
        const hargaInput = document.getElementById('harga_per_liter');
        const totalInput = document.getElementById('total_harga');
        
        function calculateTotal() {
            const jumlah = parseFloat(jumlahInput.value) || 0;
            const harga = parseFloat(hargaInput.value) || 0;
            totalInput.value = Math.round(jumlah * harga);
        }
        
        jumlahInput.addEventListener('input', calculateTotal);
        hargaInput.addEventListener('input', calculateTotal);
    });
    </script>

    <?php
    render_page_footer();

} elseif ($action === 'view' && $log_id && $log_data) {
    // View detail
    render_page_head($page_title . ' - Detail', $additional_css, $additional_js);
    render_sidebar($current_page, $user_role);
    ?>

    <main class="main-content">
        <?php render_page_header($user_info); ?>

        <div class="container-fluid">
            <div class="row">
                <div class="col-12">
                    <div class="card">
                        <div class="card-header">
                            <h5 class="card-title">
                                <i class="fas fa-gas-pump me-2"></i>Detail Log Bahan Bakar
                            </h5>
                        </div>
                        <div class="card-body">
                            <div class="row">
                                <div class="col-md-6">
                                    <table class="table table-borderless">
                                        <tr>
                                            <td class="fw-bold">Kendaraan:</td>
                                            <td><?php echo htmlspecialchars($log_data['nomor_polisi'] . ' - ' . $log_data['merk'] . ' ' . $log_data['model']); ?></td>
                                        </tr>
                                        <tr>
                                            <td class="fw-bold">Tanggal Isi:</td>
                                            <td><?php echo date('d/m/Y', strtotime($log_data['tanggal_isi'])); ?></td>
                                        </tr>
                                        <tr>
                                            <td class="fw-bold">Jenis BBM:</td>
                                            <td><?php echo htmlspecialchars($log_data['jenis_bbm']); ?></td>
                                        </tr>
                                        <tr>
                                            <td class="fw-bold">Jumlah:</td>
                                            <td><?php echo number_format($log_data['jumlah_liter'], 2); ?> Liter</td>
                                        </tr>
                                        <tr>
                                            <td class="fw-bold">Harga per Liter:</td>
                                            <td>Rp <?php echo number_format($log_data['harga_per_liter']); ?></td>
                                        </tr>
                                        <tr>
                                            <td class="fw-bold">Total Harga:</td>
                                            <td>Rp <?php echo number_format($log_data['total_harga']); ?></td>
                                        </tr>
                                        <tr>
                                            <td class="fw-bold">Odometer:</td>
                                            <td><?php echo number_format($log_data['odometer']); ?> km</td>
                                        </tr>
                                    </table>
                                </div>
                                <div class="col-md-6">
                                    <?php if (!empty($log_data['catatan'])): ?>
                                    <div class="mb-3">
                                        <strong>Catatan:</strong>
                                        <p class="mt-2"><?php echo nl2br(htmlspecialchars($log_data['catatan'])); ?></p>
                                    </div>
                                    <?php endif; ?>
                                    
                                    <?php if (!empty($log_data['created_by_name'])): ?>
                                    <div class="mb-3">
                                        <strong>Dibuat oleh:</strong>
                                        <p class="mt-2"><?php echo htmlspecialchars($log_data['created_by_name']); ?></p>
                                        <small class="text-muted">
                                            <?php echo date('d/m/Y H:i', strtotime($log_data['created_at'])); ?>
                                        </small>
                                    </div>
                                    <?php endif; ?>
                                </div>
                            </div>

                            <div class="d-flex gap-2 mt-3">
                                <?php if ($can_crud): ?>
                                    <a href="log_bahan_bakar.php?action=edit&id=<?php echo $log_data['id']; ?>" class="btn btn-warning">
                                        <i class="fas fa-edit me-1"></i>Edit
                                    </a>
                                <?php endif; ?>
                                <a href="log_bahan_bakar.php" class="btn btn-secondary">
                                    <i class="fas fa-arrow-left me-1"></i>Kembali
                                </a>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </main>

    <?php
    render_page_footer();
} else {
    // Default to list if action not recognized
    header('Location: log_bahan_bakar.php');
    exit();
}
?>
