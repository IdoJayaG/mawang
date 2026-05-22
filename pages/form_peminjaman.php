<?php
// Include global template (path anchored)
require_once __DIR__ . '/../templates/page_template.php';

// Include auth helpers
require_once __DIR__ . '/../includes/auth.php';
require_user(); // Only users can access this page

$current_user_id = get_current_user_id();
$msg = '';

// Determine return URL (origin) so we can show a "Kembali" button and redirect back after submit.
// Priority: explicit ?return_to= (prefer), then HTTP_REFERER (only if same host), else default dashboard.
$default_return = 'index.php?page=dashboard_user';
$return_to = $default_return;
// Helper: validate internal URL (relative or same-host absolute)
function is_internal_url($url) {
    if (empty($url)) return false;
    $parts = @parse_url($url);
    if ($parts === false) return false;
    // If host is present, require same host
    if (isset($parts['host'])) {
        return (isset($_SERVER['HTTP_HOST']) && $parts['host'] === $_SERVER['HTTP_HOST']);
    }
    // No host -> relative URL on same site
    return true;
}

// prefer explicit return_to GET param
if (!empty($_GET['return_to'])) {
    $candidate = rawurldecode($_GET['return_to']);
    if (is_internal_url($candidate)) {
        $return_to = $candidate;
    }
} elseif (!empty($_SERVER['HTTP_REFERER'])) {
    $ref = $_SERVER['HTTP_REFERER'];
    $parts = @parse_url($ref);
    if ($parts !== false && isset($parts['host']) && isset($_SERVER['HTTP_HOST']) && $parts['host'] === $_SERVER['HTTP_HOST']) {
        // use full ref (safe because same host)
        $return_to = $ref;
    }
}

// Handle form submission
    if ($_POST) {
    if (!validate_csrf_token($_POST['csrf_token'] ?? '')) {
        $msg = '<div class="alert alert-danger">Token keamanan tidak valid!</div>';
    } else {
        $kendaraan_id = (int)$_POST['kendaraan_id'];
        // Fetch authoritative no_reg and satker from kendaraan to avoid trusting client input
        $no_reg = null;
        $satker = null;
        if ($kendaraan_id) {
            $krow = $mysqli->query("SELECT no_reg, satker FROM kendaraan WHERE id = " . (int)$kendaraan_id);
            if ($krow) {
                $krow = $krow->fetch_assoc();
                if ($krow) {
                    $no_reg = $krow['no_reg'] ?? null;
                    $satker = $krow['satker'] ?? null;
                }
            }
        }
        $keperluan = trim($_POST['keperluan']);
        // Prefer tujuan_hidden (set by map search) over free-text
        $tujuan = trim($_POST['tujuan_hidden'] ?? '');
        $tanggal_mulai = $_POST['tanggal_mulai'];
        $tanggal_selesai = $_POST['tanggal_selesai'];
    // Always require a driver name (remove 'sopir_sendiri' option)
    $sopir_sendiri = 0;
    $nama_sopir = isset($_POST['nama_sopir']) ? trim($_POST['nama_sopir']) : null;
        $kontak_darurat = trim($_POST['kontak_darurat']);
        $estimasi_km = isset($_POST['estimasi_km']) && $_POST['estimasi_km'] !== '' ? (float)$_POST['estimasi_km'] : null;
    $estimasi_bbm = isset($_POST['estimasi_bbm']) && $_POST['estimasi_bbm'] !== '' ? (float)$_POST['estimasi_bbm'] : null;
        
        // Validation
                // Check if vehicle is available
        if (empty($keperluan) || empty($tujuan) || empty($tanggal_mulai) || empty($tanggal_selesai)) {
            $msg = '<div class="alert alert-danger">Semua field wajib harus diisi!</div>';
        } elseif (strtotime($tanggal_mulai) < strtotime(date('Y-m-d H:i'))) {
            $msg = '<div class="alert alert-danger">Tanggal mulai tidak boleh di masa lalu!</div>';
        } elseif (strtotime($tanggal_selesai) <= strtotime($tanggal_mulai)) {
            $msg = '<div class="alert alert-danger">Tanggal selesai harus setelah tanggal mulai!</div>';
        } else {
            // No fixed vehicle-type policy here: allow any jenis as long as vehicle is available.

            if (empty($msg)) {
            // Check if vehicle is available
            $check_stmt = $mysqli->prepare("\n                SELECT COUNT(*) as count FROM peminjaman_kendaraan \n                WHERE kendaraan_id = ? \n                AND status IN ('Approved', 'Ongoing', 'approved', 'ongoing') \n                AND ((tanggal_mulai <= ? AND tanggal_selesai >= ?) \n                     OR (tanggal_mulai <= ? AND tanggal_selesai >= ?)\n                     OR (tanggal_mulai >= ? AND tanggal_selesai <= ?))\n            ");
            $check_stmt->bind_param('issssss', $kendaraan_id, $tanggal_mulai, $tanggal_mulai, $tanggal_selesai, $tanggal_selesai, $tanggal_mulai, $tanggal_selesai);
            $check_stmt->execute();
            $conflict = $check_stmt->get_result()->fetch_assoc()['count'];
            $check_stmt->close();

            if ($conflict > 0) {
                $msg = '<div class="alert alert-danger">Kendaraan sudah dipinjam pada periode waktu tersebut!</div>';
            } else {
                // Also block if overlaps with an active/approved Surat Tugas for the same vehicle
                $has_st_table = $mysqli->query("SHOW TABLES LIKE 'surat_tugas'");
                if ($has_st_table && $has_st_table->num_rows > 0) {
                    $st = $mysqli->prepare("SELECT COUNT(*) c FROM surat_tugas WHERE kendaraan_id = ? AND status IN ('Disetujui','Dalam Perjalanan') AND ((tanggal_berangkat <= ? AND IFNULL(tanggal_kembali, tanggal_berangkat) >= ?) OR (tanggal_berangkat <= ? AND IFNULL(tanggal_kembali, tanggal_berangkat) >= ?) OR (tanggal_berangkat >= ? AND IFNULL(tanggal_kembali, tanggal_berangkat) <= ?))");
                    if ($st) {
                        $st->bind_param('issssss', $kendaraan_id, $tanggal_mulai, $tanggal_mulai, $tanggal_selesai, $tanggal_selesai, $tanggal_mulai, $tanggal_selesai);
                        $st->execute();
                        $c = $st->get_result()->fetch_assoc();
                        $st->close();
                        if (($c['c'] ?? 0) > 0) {
                            $msg = '<div class="alert alert-danger">Kendaraan terjadwal untuk Surat Tugas pada periode tersebut!</div>';
                        }
                    }
                }

                // Also block if overlaps with maintenance (jadwal_perawatan) not yet finished/cancelled
                if (empty($msg)) {
                    $has_jp_table = $mysqli->query("SHOW TABLES LIKE 'jadwal_perawatan'");
                    if ($has_jp_table && $has_jp_table->num_rows > 0) {
                        // Discover which date columns exist in this schema and build query accordingly
                        $cols = [];
                        if ($resCols = $mysqli->query("SHOW COLUMNS FROM jadwal_perawatan")) {
                            while ($r = $resCols->fetch_assoc()) { $cols[] = $r['Field']; }
                            $resCols->free_result();
                        }
                        $has_tanggal_perawatan = in_array('tanggal_perawatan', $cols, true);
                        $has_jadwal_tanggal = in_array('jadwal_tanggal', $cols, true);
                        $has_tanggal_selesai = in_array('tanggal_selesai', $cols, true);

                        // If neither start column exists, skip maintenance check
                        if ($has_tanggal_perawatan || $has_jadwal_tanggal) {
                            if ($has_tanggal_perawatan && $has_jadwal_tanggal) {
                                $startExpr = "COALESCE(tanggal_perawatan, jadwal_tanggal)";
                            } elseif ($has_tanggal_perawatan) {
                                $startExpr = "tanggal_perawatan";
                            } else {
                                $startExpr = "jadwal_tanggal";
                            }
                            $endExpr = $has_tanggal_selesai ? "IFNULL(tanggal_selesai, {$startExpr})" : $startExpr;

                            $sqlJP = "SELECT COUNT(*) c FROM jadwal_perawatan WHERE kendaraan_id = ? AND {$startExpr} IS NOT NULL AND (status IS NULL OR status NOT IN ('Selesai','Dibatalkan')) AND (({$startExpr} <= ? AND {$endExpr} >= ?) OR ({$startExpr} <= ? AND {$endExpr} >= ?) OR ({$startExpr} >= ? AND {$endExpr} <= ?))";
                            $jp = $mysqli->prepare($sqlJP);
                            if ($jp) {
                                $jp->bind_param('issssss', $kendaraan_id, $tanggal_mulai, $tanggal_mulai, $tanggal_selesai, $tanggal_selesai, $tanggal_mulai, $tanggal_selesai);
                                $jp->execute();
                                $jc = $jp->get_result()->fetch_assoc();
                                $jp->close();
                                if (($jc['c'] ?? 0) > 0) {
                                    $msg = '<div class="alert alert-danger">Kendaraan terjadwal untuk perawatan/perbaikan pada periode tersebut!</div>';
                                }
                            }
                        }
                    }
                }

                if (empty($msg)) {
                // Insert peminjaman (build INSERT dynamically to support different DB schemas)
                $columns = $mysqli->query("SHOW COLUMNS FROM peminjaman_kendaraan")->fetch_all(MYSQLI_ASSOC);
                $cols = array_column($columns, 'Field');

                // Find an applicant column if present in the schema
                $applicant_col = null;
                foreach (['pemohon_id', 'peminjam_id', 'pengguna_id', 'user_id'] as $c) {
                    if (in_array($c, $cols)) { $applicant_col = $c; break; }
                }

                // Build column=>type=>value map for desired columns, then filter against actual table columns
                $desired_map = [];
                $desired_map['kendaraan_id'] = ['type' => 'i', 'value' => $kendaraan_id];
                if ($applicant_col) {
                    $desired_map[$applicant_col] = ['type' => 'i', 'value' => $current_user_id];
                }
                // remaining desired columns with types
                $desired_map['keperluan'] = ['type' => 's', 'value' => $keperluan];
                $desired_map['tujuan'] = ['type' => 's', 'value' => $tujuan];
                $desired_map['tanggal_mulai'] = ['type' => 's', 'value' => $tanggal_mulai];
                $desired_map['tanggal_selesai'] = ['type' => 's', 'value' => $tanggal_selesai];
                $desired_map['sopir_sendiri'] = ['type' => 'i', 'value' => $sopir_sendiri];
                $desired_map['nama_sopir'] = ['type' => 's', 'value' => $nama_sopir];
                $desired_map['kontak_darurat'] = ['type' => 's', 'value' => $kontak_darurat];
                $desired_map['estimasi_km'] = ['type' => 'i', 'value' => $estimasi_km];
                $desired_map['estimasi_bbm'] = ['type' => 'i', 'value' => $estimasi_bbm];
                $desired_map['status'] = ['type' => 's', 'value' => 'Pending'];
                $desired_map['created_by'] = ['type' => 'i', 'value' => $current_user_id];
                // Include no_reg and satker if available (will be filtered out later if table has no such columns)
                $desired_map['no_reg'] = ['type' => 's', 'value' => $no_reg];
                $desired_map['satker'] = ['type' => 's', 'value' => $satker];
                // Ensure new submissions are marked pending for approval
                $desired_map['status'] = ['type' => 's', 'value' => 'Pending'];
                // created_at if table supports it
                $desired_map['created_at'] = ['type' => 's', 'value' => date('Y-m-d H:i:s')];

                // Filter desired_map to only columns that actually exist in the table
                $insert_cols = [];
                $types = '';
                $values = [];
                foreach ($desired_map as $col => $meta) {
                    if (in_array($col, $cols)) {
                        $insert_cols[] = $col;
                        $types .= $meta['type'];
                        $values[] = $meta['value'];
                    }
                }

                if (empty($insert_cols)) {
                    $msg = '<div class="alert alert-danger">Tidak ada kolom valid untuk menyimpan data peminjaman.</div>';
                    // skip insert
                    $stmt = false;
                } else {
                    $placeholders = array_fill(0, count($insert_cols), '?');
                    $sql = "INSERT INTO peminjaman_kendaraan (" . implode(', ', $insert_cols) . ") VALUES (" . implode(', ', $placeholders) . ")";
                    $stmt = $mysqli->prepare($sql);
                }
                $stmt = $mysqli->prepare($sql);

                if ($stmt) {
                    // bind_param requires references
                    $bind_params = [];
                    $bind_params[] = & $types;
                    for ($i = 0; $i < count($values); $i++) {
                        $bind_params[] = & $values[$i];
                    }
                    call_user_func_array([$stmt, 'bind_param'], $bind_params);


            if ($stmt->execute()) {
                $msg = '<div class="alert alert-success">Pengajuan peminjaman berhasil disubmit! Menunggu persetujuan operator/admin.</div>';
                log_user_activity("Mengajukan peminjaman kendaraan ID: $kendaraan_id untuk keperluan: $keperluan");

                        // Create notification for admins/operators
                        $kendaraan_info = $mysqli->query("SELECT no_polisi, merk, tipe, no_reg, satker FROM kendaraan WHERE id = $kendaraan_id")->fetch_assoc();
                        $current_user = get_logged_in_user();
                        $username = $current_user ? $current_user['nama_lengkap'] : 'Unknown User';
                        $display_reg = !empty($kendaraan_info['no_reg']) ? "Reg: {$kendaraan_info['no_reg']}" : '';
                        $display_nopol = !empty($kendaraan_info['no_polisi']) ? (empty($display_reg) ? "Nopol: {$kendaraan_info['no_polisi']}" : " (Nopol: {$kendaraan_info['no_polisi']})") : '';
                        $display_satker = !empty($kendaraan_info['satker']) ? " - Satker: {$kendaraan_info['satker']}" : '';
                        $notification_msg = "Pengajuan peminjaman kendaraan {$display_reg}{$display_nopol} ({$kendaraan_info['merk']} {$kendaraan_info['tipe']}){$display_satker} dari " . $username . " untuk keperluan: $keperluan";

                        // Notify all operators and admins (role_id 2 and 3)
                        $admin_operators = $mysqli->query("SELECT p.id FROM pengguna p JOIN user_account ua ON p.id = ua.pengguna_id WHERE ua.role_id IN (2, 3)");
                        $escaped_msg = $mysqli->real_escape_string($notification_msg);
                        while ($admin = $admin_operators->fetch_assoc()) {
                            // Use existing columns on `notifikasi` (message, title, type, category)
                            $mysqli->query("INSERT INTO notifikasi (user_id, message, title, type, category) VALUES ({$admin['id']}, '$escaped_msg', 'Pengajuan Peminjaman', 'info', 'vehicle')");
                        }
                        // If a return_to was provided via POST prefer it (validate same-host or relative), else fall back to earlier-detected $return_to
                        $post_return = $_POST['return_to'] ?? null;
                        if ($post_return && is_internal_url($post_return)) {
                            // If POST provided a relative path (no host) it's safe; if it's full URL ensure host matches
                            $loc = $post_return;
                        } else {
                            $loc = $return_to;
                        }

                        // Redirect back to origin if possible
                        if (!empty($loc)) {
                            header('Location: ' . $loc);
                            exit;
                        }
                    } else {
                        $msg = '<div class="alert alert-danger">Error: ' . $stmt->error . '</div>';
                    }
                    $stmt->close();
                } else {
                    $msg = '<div class="alert alert-danger">Error menyiapkan query: ' . $mysqli->error . '</div>';
                }
                }
            }
            }
        }
    }
}

// Get available vehicles: show only vehicles that are currently available.
$vehicles_where = [];
$vehicles_where[] = "LOWER(COALESCE(k.status_peminjaman, '')) = 'tersedia'";
$vehicles_where[] = "LOWER(COALESCE(k.status_kendaraan, '')) = 'operasional'";
$vehicles_where[] = "LOWER(COALESCE(k.kondisi, '')) NOT LIKE 'rusak%'";

// Exclude vehicles that currently have an active peminjaman (Approved/Ongoing)
$has_pk = $mysqli->query("SHOW TABLES LIKE 'peminjaman_kendaraan'");
if ($has_pk && $has_pk->num_rows > 0) {
    $vehicles_where[] = "NOT EXISTS (SELECT 1 FROM peminjaman_kendaraan pk WHERE pk.kendaraan_id = k.id AND pk.status IN ('Approved','approved','Ongoing','ongoing'))";
}

// Exclude vehicles currently assigned in an active surat_tugas
$has_st = $mysqli->query("SHOW TABLES LIKE 'surat_tugas'");
if ($has_st && $has_st->num_rows > 0) {
    $vehicles_where[] = "NOT EXISTS (SELECT 1 FROM surat_tugas st WHERE st.kendaraan_id = k.id AND st.status IN ('Disetujui','Dalam Perjalanan'))";
}

// Exclude vehicles with scheduled/ongoing maintenance
$has_jp = $mysqli->query("SHOW TABLES LIKE 'jadwal_perawatan'");
if ($has_jp && $has_jp->num_rows > 0) {
    $vehicles_where[] = "NOT EXISTS (SELECT 1 FROM jadwal_perawatan jp WHERE jp.kendaraan_id = k.id AND (jp.status IS NULL OR LOWER(COALESCE(jp.status,'')) NOT IN ('selesai','dibatalkan')))";
}

$where_sql = '';
if (!empty($vehicles_where)) {
    $where_sql = ' WHERE ' . implode(' AND ', $vehicles_where);
}

$cols_info = $mysqli->query("SHOW COLUMNS FROM kendaraan")->fetch_all(MYSQLI_ASSOC);
$cols_names = array_column($cols_info, 'Field');
$join_pengguna = '';
$select_extra = '';
if (in_array('pengguna_id', $cols_names, true)) {
    $join_pengguna = ' LEFT JOIN pengguna pg ON k.pengguna_id = pg.id';
    $select_extra = ', COALESCE(pg.nama_lengkap, "") AS nama_pengemudi';
}
$order_col = 'COALESCE(k.no_reg, k.no_polisi)';
$vehicles_query = "SELECT k.* " . $select_extra . ", 1 as available FROM kendaraan k " . $join_pengguna . " " . $where_sql . " ORDER BY " . $order_col;
$vehicles = $mysqli->query($vehicles_query)->fetch_all(MYSQLI_ASSOC);
?>

    <div class="page-header">
        <h1><i class="fas fa-calendar-check"></i> Pengajuan Peminjaman</h1>
    </div>

<?= $msg ?>

<div class="card shadow-sm">
    <div class="card-header bg-primary text-white">
        <h5 class="mb-0"><i class="fas fa-plus me-2"></i> Form Pengajuan Peminjaman</h5>
    </div>
    <div class="card-body">
        <form method="post" class="needs-validation" novalidate>
            <input type="hidden" name="csrf_token" value="<?= generate_csrf_token() ?>">
            <input type="hidden" name="return_to" value="<?= htmlspecialchars($return_to) ?>">
            
            <div class="row g-3">
                <div class="col-md-6">
                    <label for="kendaraan_id" class="form-label">Kendaraan *</label>
                    <select id="kendaraan_id" name="kendaraan_id" class="form-select" required>
                        <option value="">Pilih Kendaraan</option>
                        <?php foreach ($vehicles as $vehicle): ?>
                            <option value="<?= $vehicle['id'] ?>" 
                                    <?= !$vehicle['available'] ? 'disabled' : '' ?>
                                    data-type="<?= htmlspecialchars($vehicle['jenis']) ?>"
                                    data-merk="<?= htmlspecialchars($vehicle['merk']) ?>"
                                    data-fuel="<?= htmlspecialchars($vehicle['bahan_bakar']) ?>"
                                    data-no_reg="<?= htmlspecialchars($vehicle['no_reg'] ?? '') ?>"
                                    data-satker="<?= htmlspecialchars($vehicle['satker'] ?? '') ?>"
                                    data-driver="<?= htmlspecialchars($vehicle['nama_pengemudi'] ?? '') ?>">
                                <?php
                                    $display_reg = !empty($vehicle['no_reg']) ? $vehicle['no_reg'] : ($vehicle['no_polisi'] ?? '-');
                                    $driver_label = !empty($vehicle['nama_pengemudi']) ? $vehicle['nama_pengemudi'] : '(Belum ditentukan)';
                                ?>
                                <?= htmlspecialchars($display_reg) ?> - <?= htmlspecialchars($vehicle['merk']) ?> <?= htmlspecialchars($vehicle['tipe']) ?> - <?= htmlspecialchars($driver_label) ?>
                                <?= !$vehicle['available'] ? ' (Tidak Tersedia)' : '' ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                
                <div class="col-md-6">
                    <label for="keperluan" class="form-label">Keperluan *</label>
                    <input type="text" id="keperluan" name="keperluan" class="form-control" required 
                           placeholder="Contoh: Dinas, Rapat, Kunjungan Kerja">
                </div>
            </div>
            
            <div class="row g-3">
                <div class="col-md-6">
                    <label for="add_destination_input" class="form-label">Tambah Lokasi Tujuan *</label>
                    <div class="input-group">
                        <input type="text" id="add_destination_input" class="form-control" placeholder="Masukkan alamat tujuan...">
                        <button type="button" id="addDestinationBtn" class="btn btn-outline-primary">Tambah</button>
                    </div>
                    <ul id="destinationList" class="list-group mt-2"></ul>
                    <input type="hidden" name="tujuan_hidden" id="tujuan_hidden" value="">
                    <input type="hidden" name="estimasi_km" id="estimasi_km" value="">
                    <input type="hidden" name="estimasi_bbm" id="estimasi_bbm" value="">
                    <div id="miniMap" style="height:260px; margin-top:12px; border:1px solid #e6e6e6; border-radius:6px;"></div>
                    <div class="mt-2 small text-muted">Jarak pulang-pergi: <span id="routeDistance">-</span> km &middot; Estimasi BBM: <span id="routeFuel">-</span> L</div>
                </div>
            </div>
            
            <div class="form-row">
                <div class="form-group">
                    <label for="tanggal_mulai">Tanggal & Waktu Mulai *</label>
                    <input type="datetime-local" id="tanggal_mulai" name="tanggal_mulai" class="form-control" required 
                           min="<?= date('Y-m-d\TH:i') ?>">
                </div>
                
                <div class="form-group">
                    <label for="tanggal_selesai">Tanggal & Waktu Selesai *</label>
                    <input type="datetime-local" id="tanggal_selesai" name="tanggal_selesai" class="form-control" required>
                </div>
            </div>
            
            <div class="form-row">
                <div class="form-group">
                    <label for="kendaraan_fuel">Tipe Bahan Bakar</label>
                    <input type="text" id="kendaraan_fuel" class="form-control" readonly placeholder="Pilih kendaraan untuk melihat tipe bahan bakar">
                </div>

                <div class="form-group">
                    <label for="kontak_darurat">Kontak Darurat *</label>
                    <input type="tel" id="kontak_darurat" name="kontak_darurat" class="form-control" required 
                           placeholder="Nomor yang dapat dihubungi saat darurat">
                </div>
            </div>
            
            <!-- Removed option to drive self; always request driver name -->
            
            <div class="form-actions">
                <input type="hidden" name="no_reg" id="no_reg" value="<?= htmlspecialchars($no_reg ?? '') ?>">
                <input type="hidden" name="satker" id="satker" value="<?= htmlspecialchars($satker ?? '') ?>">
                <button type="submit" class="btn btn-primary">
                    <i class="fas fa-paper-plane"></i> Submit Pengajuan
                </button>
                <a href="<?= htmlspecialchars($return_to) ?>" class="btn btn-secondary">
                    <i class="fas fa-arrow-left"></i> Kembali
                </a>
            </div>
        </form>
    </div>
</div>



<script>
document.addEventListener('DOMContentLoaded', function() {
    const namaSopirInput = document.getElementById('nama_sopir');
    // Driver name is required by default (markup already sets required)
    
    // Auto-calculate end time (minimum 2 hours from start)
    const tanggalMulai = document.getElementById('tanggal_mulai');
    const tanggalSelesai = document.getElementById('tanggal_selesai');
    
    tanggalMulai.addEventListener('change', function() {
        if (this.value) {
            const startTime = new Date(this.value);
            const endTime = new Date(startTime.getTime() + (2 * 60 * 60 * 1000)); // Add 2 hours
            
            const year = endTime.getFullYear();
            const month = String(endTime.getMonth() + 1).padStart(2, '0');
            const day = String(endTime.getDate()).padStart(2, '0');
            const hours = String(endTime.getHours()).padStart(2, '0');
            const minutes = String(endTime.getMinutes()).padStart(2, '0');
            
            tanggalSelesai.value = `${year}-${month}-${day}T${hours}:${minutes}`;
            tanggalSelesai.min = this.value;
        }
    });
    
    // Auto estimate fuel in liters based on distance and show vehicle fuel type + cost in Rp
    const estimasiKm = document.getElementById('estimasi_km');
    const estimasiBbm = document.getElementById('estimasi_bbm');
    const kendaraanSelect = document.getElementById('kendaraan_id');
    const kendaraanFuel = document.getElementById('kendaraan_fuel');

    // Sync selected vehicle metadata (no_reg / satker / fuel) and clear prior estimates when vehicle changes
    kendaraanSelect.addEventListener('change', function() {
        const opt = this.selectedOptions && this.selectedOptions[0];
        const noRegInput = document.getElementById('no_reg');
        const satkerInput = document.getElementById('satker');
        const kendaraanFuel = document.getElementById('kendaraan_fuel');
        if (opt) {
            const noReg = opt.getAttribute('data-no_reg') || '';
            const satk = opt.getAttribute('data-satker') || '';
            if (noRegInput) noRegInput.value = noReg;
            if (satkerInput) satkerInput.value = satk;
            if (kendaraanFuel) kendaraanFuel.value = opt.getAttribute('data-fuel') || '';
        }
        // Clear previous estimations to avoid stale values
        const estimasiKmEl = document.getElementById('estimasi_km');
        const estimasiBbmEl = document.getElementById('estimasi_bbm');
        if (estimasiKmEl) estimasiKmEl.value = '';
        if (estimasiBbmEl) estimasiBbmEl.value = '';
        const routeDistanceEl = document.getElementById('routeDistance');
        const routeFuelEl = document.getElementById('routeFuel');
        if (routeDistanceEl) routeDistanceEl.textContent = '-';
        if (routeFuelEl) routeFuelEl.textContent = '-';
    });
});
</script>
<!-- Leaflet + Routing -->
<link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.3/dist/leaflet.css" integrity="" crossorigin="" />
<link rel="stylesheet" href="https://unpkg.com/leaflet-routing-machine@3.2.12/dist/leaflet-routing-machine.css" />
<script src="https://unpkg.com/leaflet@1.9.3/dist/leaflet.js"></script>
<script src="https://unpkg.com/leaflet-routing-machine@3.2.12/dist/leaflet-routing-machine.min.js"></script>
<script>
// Map, routing and geocoding for multi-destination route + BBM estimate
document.addEventListener('DOMContentLoaded', function(){
    if (!document.getElementById('miniMap')) return;

    const map = L.map('miniMap').setView([-6.200, 106.816], 12);
    L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
        maxZoom: 19,
        attribution: '&copy; OpenStreetMap contributors'
    }).addTo(map);

    let routingControl = null;
    let originMarker = null;
    const destMarkers = new Map(); // inputElem -> marker

    const kendaraanSelect = document.getElementById('kendaraan_id');
    const originInput = document.getElementById('berangkat_dari');
    const destinationList = document.getElementById('destinationList');
    const addDestinationBtn = document.getElementById('addDestinationBtn');
    const routeDistanceEl = document.getElementById('routeDistance');
    const routeFuelEl = document.getElementById('routeFuel');

    // Simple nominatim geocode
    function geocodeAddress(q){
        return fetch('https://nominatim.openstreetmap.org/search?format=json&limit=1&q=' + encodeURIComponent(q), { headers: { 'Accept': 'application/json' } })
            .then(r => r.json())
            .then(j => j && j.length ? j[0] : null)
            .catch(()=>null);
    }
    function reverseGeocode(lat, lon){
        return fetch('https://nominatim.openstreetmap.org/reverse?format=json&lat=' + encodeURIComponent(lat) + '&lon=' + encodeURIComponent(lon), { headers: { 'Accept': 'application/json' } })
            .then(r => r.json())
            .then(j => j && j.display_name ? j.display_name : null)
            .catch(()=>null);
    }

    function addDestinationInput(value){
        const li = document.createElement('li');
        li.className = 'list-group-item d-flex align-items-center';
        const input = document.createElement('input');
        input.type = 'text';
        input.className = 'form-control me-2 destination-item';
        input.value = value || '';
        input.placeholder = 'Alamat tujuan';
        // container for suggestions
        li.style.position = 'relative';
        const sugg = document.createElement('div');
        sugg.className = 'destination-suggestions list-group';
        sugg.style.position = 'absolute';
        sugg.style.left = '0';
        sugg.style.right = '0';
        sugg.style.top = '100%';
        sugg.style.zIndex = '1200';
        const btn = document.createElement('button');
        btn.type = 'button'; btn.className = 'btn btn-sm btn-danger remove-destination'; btn.innerHTML = '&times;';
        btn.addEventListener('click', function(){
            const m = destMarkers.get(input);
            if (m) { map.removeLayer(m); destMarkers.delete(input); }
            li.remove(); recalcRouteAndEstimates();
        });
        input.addEventListener('keydown', function(e){ if (e.key === 'Enter') { e.preventDefault(); geocodeInputAndSetMarker(input).then(recalcRouteAndEstimates); } });
        input.addEventListener('blur', function(){ setTimeout(()=>{ if (input.value.trim()) geocodeInputAndSetMarker(input).then(recalcRouteAndEstimates); sugg.innerHTML=''; }, 200); });
        // autocomplete (Nominatim) with debounce
        let acTimeout = null;
        input.addEventListener('input', function(){
            const q = input.value.trim();
            if (acTimeout) clearTimeout(acTimeout);
            if (q.length < 2) { sugg.innerHTML = ''; return; }
            acTimeout = setTimeout(function(){
                fetch('https://nominatim.openstreetmap.org/search?format=json&limit=5&q=' + encodeURIComponent(q))
                    .then(r => r.json())
                    .then(list => {
                        sugg.innerHTML = '';
                        if (!list || !list.length) return;
                        list.forEach(item => {
                            const a = document.createElement('a');
                            a.href = '#';
                            a.className = 'list-group-item list-group-item-action';
                            a.textContent = item.display_name;
                            a.addEventListener('click', function(ev){ ev.preventDefault(); input.value = item.display_name; input.dataset.lat = item.lat; input.dataset.lng = item.lon; input.dataset.display = item.display_name; // place marker immediately
                                const old = destMarkers.get(input); if (old) map.removeLayer(old);
                                const m = L.marker([parseFloat(item.lat), parseFloat(item.lon)]).addTo(map).bindPopup(item.display_name);
                                destMarkers.set(input, m);
                                sugg.innerHTML = '';
                                recalcRouteAndEstimates();
                            });
                            sugg.appendChild(a);
                        });
                    }).catch(()=>{ sugg.innerHTML=''; });
            }, 300);
        });
        li.appendChild(input); li.appendChild(btn); li.appendChild(sugg); destinationList.appendChild(li);
        return input;
    }

    function geocodeInputAndSetMarker(input){
        const q = input.value.trim();
        if (!q) return Promise.resolve(null);
        return geocodeAddress(q).then(res => {
            if (!res) return null;
            input.dataset.lat = res.lat; input.dataset.lng = res.lon; input.dataset.display = res.display_name || q;
            // place marker
            const latlng = L.latLng(parseFloat(res.lat), parseFloat(res.lon));
            const old = destMarkers.get(input);
            if (old) map.removeLayer(old);
            const m = L.marker(latlng).addTo(map).bindPopup(input.dataset.display || q);
            destMarkers.set(input, m);
            return {lat: latlng.lat, lng: latlng.lng};
        });
    }

    function setOriginMarker(latlng, display){
        if (originMarker) map.removeLayer(originMarker);
        originMarker = L.marker(latlng, {icon: L.icon({iconUrl: 'https://unpkg.com/leaflet@1.9.3/dist/images/marker-icon.png'})}).addTo(map).bindPopup(display || 'Asal');
    }

    function getConsumptionRateForSelectedVehicle(){
        if (!kendaraanSelect) return 4;
        const opt = kendaraanSelect.selectedOptions && kendaraanSelect.selectedOptions[0];
        const merk = (opt && (opt.dataset && opt.dataset.merk)) ? opt.dataset.merk.toLowerCase() : '';
        if (merk.includes('mercedes')) return 3;
        if (merk.includes('mitsubishi')) return 4;
        if (merk.includes('hino')) return 5;
        return 4; // default
    }

    function recalcRouteAndEstimates(){
        // Build promises to ensure lat/lng available for origin and all destinations
        // If an origin input exists but is empty, fall back to the fixed origin
        const originVal = (originInput && originInput.value && originInput.value.trim()) ? originInput.value.trim() : 'SPBT Kemhan Cawang';
        const destInputs = Array.from(document.querySelectorAll('.destination-item'));
        const originPromise = (originInput && originInput.dataset && originInput.dataset.lat && originInput.dataset.lng)
            ? Promise.resolve({lat: parseFloat(originInput.dataset.lat), lng: parseFloat(originInput.dataset.lng)})
            : (originVal ? geocodeAddress(originVal).then(r => {
                if (r) {
                    if (originInput) { originInput.dataset.lat = r.lat; originInput.dataset.lng = r.lon; originInput.dataset.display = r.display_name; }
                    setOriginMarker([parseFloat(r.lat), parseFloat(r.lon)], r.display_name);
                    return {lat: parseFloat(r.lat), lng: parseFloat(r.lon)};
                }
                // geocode failed -> fallback to fixed SPBT Kemhan Cawang coords so routing can proceed
                console.warn('geocodeAddress failed for origin, using fixed fallback coords');
                const fallback = { lat: -6.200, lng: 106.816 };
                if (originInput) { originInput.dataset.lat = fallback.lat; originInput.dataset.lng = fallback.lng; originInput.dataset.display = 'SPBT Kemhan Cawang'; }
                setOriginMarker([fallback.lat, fallback.lng], 'SPBT Kemhan Cawang');
                return fallback;
            }) : Promise.resolve(null));

        const destPromises = destInputs.map(inp => {
            const v = inp.value.trim();
            if (!v) return Promise.resolve(null);
            if (inp.dataset.lat && inp.dataset.lng) return Promise.resolve({lat: parseFloat(inp.dataset.lat), lng: parseFloat(inp.dataset.lng)});
            return geocodeAddress(v).then(r=>{ if (r){ inp.dataset.lat=r.lat; inp.dataset.lng=r.lon; inp.dataset.display=r.display_name; const old = destMarkers.get(inp); if (old) map.removeLayer(old); const m = L.marker([parseFloat(r.lat), parseFloat(r.lon)]).addTo(map).bindPopup(r.display_name); destMarkers.set(inp,m); return {lat: parseFloat(r.lat), lng: parseFloat(r.lon)} } return null; });
        });

        return Promise.all([originPromise].concat(destPromises)).then(results => {
            const originLatLng = results[0];
            const destLatLngs = results.slice(1).filter(Boolean);
            console.log('recalcRouteAndEstimates (form_peminjaman): origin=', originLatLng, 'dests=', destLatLngs);
            if (!originLatLng || destLatLngs.length === 0) {
                // Not enough points to route
                console.log('recalcRouteAndEstimates: not enough points to route');
                routeDistanceEl.textContent = '-'; routeFuelEl.textContent = '-';
                if (routingControl) { try{ map.removeControl(routingControl); } catch(e){} routingControl = null; }
                return;
            }

            // Build waypoints: origin -> dest1 -> dest2 ... -> origin (return)
            const waypoints = [L.latLng(originLatLng.lat, originLatLng.lng)].concat(destLatLngs.map(d=>L.latLng(d.lat, d.lng))).concat([L.latLng(originLatLng.lat, originLatLng.lng)]);

            if (routingControl) { try{ map.removeControl(routingControl); } catch(e){} routingControl = null; }
            routingControl = L.Routing.control({
                waypoints: waypoints,
                lineOptions: { styles: [{color: 'blue', opacity: 0.6, weight: 5}] },
                createMarker: function(i, wp) { return L.marker(wp.latLng); },
                addWaypoints: false,
                routeWhileDragging: false,
                fitSelectedRoutes: true,
                router: L.Routing.osrmv1({ serviceUrl: 'https://router.project-osrm.org/route/v1' })
            }).addTo(map);

            // routesfound handler with fallback
            let routesFoundHandled = false;
            routingControl.on('routesfound', function(e){
                routesFoundHandled = true;
                const summary = e.routes && e.routes[0] && e.routes[0].summary;
                if (!summary) return;
                console.log('OSRM routesfound summary=', summary);
                const distKmRaw = (summary.totalDistance/1000);
                const distKmCeil = Math.ceil(distKmRaw);
                routeDistanceEl.textContent = distKmCeil;
                // Estimasi BBM: ceil(liters) + 2
                const rate = getConsumptionRateForSelectedVehicle();
                const litersRaw = distKmRaw / rate;
                const litersCeilPlus = Math.ceil(litersRaw) + 2;
                routeFuelEl.textContent = litersCeilPlus;
                // Update form fields (prefer numeric values and trigger input)
                const kmEl = document.getElementById('estimasi_km');
                const bbmEl = document.getElementById('estimasi_bbm');
                if (kmEl) { kmEl.value = distKmCeil; kmEl.dispatchEvent(new Event('input')); }
                if (bbmEl) { bbmEl.value = litersCeilPlus; bbmEl.dispatchEvent(new Event('input')); }
            });

            // Immediate approximate (straight-line) distance so UI shows values quickly
            try {
                function haversine(a, b) {
                    const R = 6371; // km
                    const dLat = (b.lat - a.lat) * Math.PI / 180;
                    const dLon = (b.lng - a.lng) * Math.PI / 180;
                    const lat1 = a.lat * Math.PI / 180;
                    const lat2 = b.lat * Math.PI / 180;
                    const sinHalf = Math.sin(dLat/2) * Math.sin(dLat/2) + Math.cos(lat1) * Math.cos(lat2) * Math.sin(dLon/2) * Math.sin(dLon/2);
                    const c = 2 * Math.atan2(Math.sqrt(sinHalf), Math.sqrt(1 - sinHalf));
                    return R * c; // km
                }
                let totalKm = 0;
                for (let i = 0; i < waypoints.length - 1; i++) {
                    const a = waypoints[i];
                    const b = waypoints[i+1];
                    if (!a || !b) continue;
                    totalKm += haversine({lat: a.lat, lng: a.lng}, {lat: b.lat, lng: b.lng});
                }
                const distKmCeil = Math.ceil(totalKm);
                console.log('Immediate straight-line distance (km)=', distKmCeil);
                routeDistanceEl.textContent = distKmCeil;
                const rate = getConsumptionRateForSelectedVehicle();
                const litersCeilPlus = Math.ceil(totalKm / rate) + 2;
                console.log('Immediate liters (ceil +2)=', litersCeilPlus);
                routeFuelEl.textContent = litersCeilPlus;
                const kmEl = document.getElementById('estimasi_km');
                const bbmEl = document.getElementById('estimasi_bbm');
                if (kmEl) { kmEl.value = distKmCeil; kmEl.dispatchEvent(new Event('input')); }
                if (bbmEl) { bbmEl.value = litersCeilPlus; bbmEl.dispatchEvent(new Event('input')); }
            } catch(e) { console.warn('Immediate routing calculation failed', e); }

            // Fit map to route after short delay
            setTimeout(()=>{ try{ if (routingControl && routingControl.getPlan) routingControl.getPlan().setWaypoints(waypoints); } catch(e){} }, 300);
        });
    }

    // Initialize existing destination inputs (add autocomplete + remove handlers)
    Array.from(document.querySelectorAll('.destination-item')).forEach(inp => {
        const li = inp.closest('li') || inp.parentElement;
        // ensure suggestion container exists
        let sugg = li.querySelector('.destination-suggestions');
        if (!sugg) {
            sugg = document.createElement('div');
            sugg.className = 'destination-suggestions list-group';
            sugg.style.position = 'absolute';
            sugg.style.left = '0';
            sugg.style.right = '0';
            sugg.style.top = '100%';
            sugg.style.zIndex = '1200';
            li.style.position = 'relative';
            li.appendChild(sugg);
        }

        // wire remove button
        const removeBtn = li.querySelector('.remove-destination');
        if (removeBtn) {
            removeBtn.addEventListener('click', function(){ const m = destMarkers.get(inp); if (m) { try{ map.removeLayer(m); } catch(e){} destMarkers.delete(inp); } li.remove(); recalcRouteAndEstimates(); });
        } else {
            const btn = document.createElement('button'); btn.type='button'; btn.className='btn btn-sm btn-danger remove-destination'; btn.innerHTML='&times;'; btn.addEventListener('click', function(){ const m = destMarkers.get(inp); if (m) { try{ map.removeLayer(m); } catch(e){} destMarkers.delete(inp); } li.remove(); recalcRouteAndEstimates(); }); li.appendChild(btn);
        }

        // input handlers (enter, blur)
        inp.addEventListener('keydown', function(e){ if (e.key === 'Enter') { e.preventDefault(); geocodeInputAndSetMarker(inp).then(recalcRouteAndEstimates); } });
        inp.addEventListener('blur', function(){ setTimeout(()=>{ if (inp.value.trim()) geocodeInputAndSetMarker(inp).then(recalcRouteAndEstimates); sugg.innerHTML=''; }, 200); });

        // autocomplete for existing input (debounced)
        let acTimeout = null;
        inp.addEventListener('input', function(){ const q = inp.value.trim(); sugg.innerHTML = ''; if (acTimeout) clearTimeout(acTimeout); if (q.length < 2) return; acTimeout = setTimeout(function(){ fetch('https://nominatim.openstreetmap.org/search?format=json&limit=5&q=' + encodeURIComponent(q)).then(r=>r.json()).then(list=>{ sugg.innerHTML = ''; if (!list || !list.length) return; list.forEach(item=>{ const a = document.createElement('a'); a.href='#'; a.className='list-group-item list-group-item-action'; a.textContent = item.display_name; a.addEventListener('click', function(ev){ ev.preventDefault(); inp.value = item.display_name; inp.dataset.lat = item.lat; inp.dataset.lng = item.lon; inp.dataset.display = item.display_name; const old = destMarkers.get(inp); if (old) try{ map.removeLayer(old); }catch(e){} const m = L.marker([parseFloat(item.lat), parseFloat(item.lon)]).addTo(map).bindPopup(item.display_name); destMarkers.set(inp, m); sugg.innerHTML=''; recalcRouteAndEstimates(); }); sugg.appendChild(a); }); }).catch(()=>{ sugg.innerHTML=''; }); }, 250); });

        // if there's an initial value, geocode it to create a marker
        if (inp.value.trim()) {
            if (inp.dataset.lat && inp.dataset.lng) {
                try{
                    const m = L.marker([parseFloat(inp.dataset.lat), parseFloat(inp.dataset.lng)]).addTo(map).bindPopup(inp.dataset.display || inp.value);
                    destMarkers.set(inp, m);
                } catch(e) {}
            } else {
                geocodeInputAndSetMarker(inp).then(()=>{ try{ recalcRouteAndEstimates(); } catch(e){} });
            }
        }
    });

    // Add destination button: use the add_destination_input value if provided
    addDestinationBtn?.addEventListener('click', function(){
        try {
            const textInput = document.getElementById('add_destination_input');
            const val = textInput && textInput.value ? textInput.value.trim() : '';
            if (!val) {
                const newInp = addDestinationInput(''); newInp.focus();
                return;
            }
            const newInp = addDestinationInput(val);
            // geocode and set marker immediately
            geocodeInputAndSetMarker(newInp).then(()=>{ recalcRouteAndEstimates(); });
            if (textInput) textInput.value = '';
            newInp.focus();
        } catch(e) { console.error(e); }
    });

    // Map click: add destination at clicked point (origin is fixed to SPBT Kemhan Cawang)
    map.on('click', function(e){
        reverseGeocode(e.latlng.lat, e.latlng.lng).then(addr=>{
            const inp = addDestinationInput(addr || (e.latlng.lat + ',' + e.latlng.lng));
            inp.dataset.lat = e.latlng.lat; inp.dataset.lng = e.latlng.lng; const m = L.marker(e.latlng).addTo(map).bindPopup(inp.value); destMarkers.set(inp, m);
            recalcRouteAndEstimates();
        });
    });

    // Removed main search autocomplete — replacement is the add-destination input above

    // Pack destinations on submit
    const form = document.querySelector('form');
    if (form) {
        form.addEventListener('submit', function(e){
            const dests = Array.from(document.querySelectorAll('.destination-item')).map(i=>i.value.trim()).filter(Boolean);
            // Require at least one destination
            if (dests.length === 0) {
                e.preventDefault();
                alert('Harap tambahkan minimal satu lokasi tujuan sebelum submit.');
                const addInput = document.getElementById('add_destination_input'); if (addInput) addInput.focus();
                return;
            }
            document.getElementById('tujuan_hidden').value = dests.join('||');
            // ensure estimasi fields are set (prefer numeric values already computed)
            const kmEl = document.getElementById('estimasi_km');
            const bbmEl = document.getElementById('estimasi_bbm');
            if (kmEl) {
                const v = parseFloat(kmEl.value);
                if (isFinite(v)) kmEl.value = Math.round(v * 100) / 100;
                else if (routeDistanceEl && !isNaN(parseFloat(routeDistanceEl.textContent))) kmEl.value = Math.round(parseFloat(routeDistanceEl.textContent) * 100) / 100;
            }
            if (bbmEl) {
                const v2 = parseFloat(bbmEl.value);
                if (isFinite(v2)) bbmEl.value = Math.round(v2 * 100) / 100;
                else if (routeFuelEl && !isNaN(parseFloat(routeFuelEl.textContent))) bbmEl.value = Math.round(parseFloat(routeFuelEl.textContent) * 100) / 100;
            }
        });
    }

    // initial calc if possible
    setTimeout(()=>{ recalcRouteAndEstimates(); }, 800);
});
</script>
