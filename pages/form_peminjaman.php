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
        $tujuan = trim($_POST['tujuan']);
        $tanggal_mulai = $_POST['tanggal_mulai'];
        $tanggal_selesai = $_POST['tanggal_selesai'];
    // Always require a driver name (remove 'sopir_sendiri' option)
    $sopir_sendiri = 0;
    $nama_sopir = isset($_POST['nama_sopir']) ? trim($_POST['nama_sopir']) : null;
        $kontak_darurat = trim($_POST['kontak_darurat']);
        $estimasi_km = $_POST['estimasi_km'] ? (int)$_POST['estimasi_km'] : null;
    $estimasi_bbm = isset($_POST['estimasi_bbm']) && $_POST['estimasi_bbm'] !== '' ? (int)$_POST['estimasi_bbm'] : null;
        
        // Validation
                // Check if vehicle is available
        if (empty($keperluan) || empty($tujuan) || empty($tanggal_mulai) || empty($tanggal_selesai)) {
            $msg = '<div class="alert alert-danger">Semua field wajib harus diisi!</div>';
        } elseif (strtotime($tanggal_mulai) < strtotime(date('Y-m-d H:i'))) {
            $msg = '<div class="alert alert-danger">Tanggal mulai tidak boleh di masa lalu!</div>';
        } elseif (strtotime($tanggal_selesai) <= strtotime($tanggal_mulai)) {
            $msg = '<div class="alert alert-danger">Tanggal selesai harus setelah tanggal mulai!</div>';
        } else {
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
                        // Treat COALESCE(tanggal_perawatan, jadwal_tanggal) as start; IFNULL(tanggal_selesai, start) as end
                        $sqlJP = "SELECT COUNT(*) c FROM jadwal_perawatan WHERE kendaraan_id = ? AND COALESCE(tanggal_perawatan, jadwal_tanggal) IS NOT NULL AND (status IS NULL OR status NOT IN ('Selesai','Dibatalkan')) AND ((COALESCE(tanggal_perawatan, jadwal_tanggal) <= ? AND IFNULL(tanggal_selesai, COALESCE(tanggal_perawatan, jadwal_tanggal)) >= ?) OR (COALESCE(tanggal_perawatan, jadwal_tanggal) <= ? AND IFNULL(tanggal_selesai, COALESCE(tanggal_perawatan, jadwal_tanggal)) >= ?) OR (COALESCE(tanggal_perawatan, jadwal_tanggal) >= ? AND IFNULL(tanggal_selesai, COALESCE(tanggal_perawatan, jadwal_tanggal)) <= ?))";
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

// Get available vehicles
$vehicles_query = "
    SELECT k.*, 
           CASE WHEN k.status_peminjaman = 'Tersedia' AND k.status_kendaraan = 'Operasional' 
                THEN 1 ELSE 0 END as available
    FROM kendaraan k
    ORDER BY available DESC, COALESCE(k.no_reg, k.no_polisi)
";
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
                                    data-fuel="<?= htmlspecialchars($vehicle['bahan_bakar']) ?>"
                                    data-no_reg="<?= htmlspecialchars($vehicle['no_reg'] ?? '') ?>"
                                    data-satker="<?= htmlspecialchars($vehicle['satker'] ?? '') ?>">
                                <?= htmlspecialchars(($vehicle['no_reg'] ?? '') !== '' ? $vehicle['no_reg'] : ($vehicle['no_polisi'] ?? '-')) ?><?= !empty($vehicle['no_polisi']) ? ' (Nopol: ' . htmlspecialchars($vehicle['no_polisi']) . ')' : '' ?> - 
                                <?= htmlspecialchars($vehicle['merk']) ?> <?= htmlspecialchars($vehicle['tipe']) ?>
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
                    <label for="tujuan" class="form-label">Tujuan *</label>
                    <input type="text" id="tujuan" name="tujuan" class="form-control" required 
                           placeholder="Contoh: Jakarta Pusat, Bandung, Surabaya">
                </div>
                
                <div class="form-group">
                    <label for="estimasi_km">Estimasi KM</label>
                    <input type="number" id="estimasi_km" name="estimasi_km" class="form-control" min="1" 
                           placeholder="Estimasi jarak tempuh (KM)">
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
                    <label for="estimasi_bbm">Estimasi BBM (L)</label>
                    <input type="number" id="estimasi_bbm" name="estimasi_bbm" class="form-control" min="0" step="1" 
                           placeholder="Estimasi bahan bakar (liter)">
                </div>

                <div class="form-group">
                    <label for="estimasi_bbm_rp">Estimasi BBM (Rp)</label>
                    <input type="text" id="estimasi_bbm_rp" class="form-control" readonly placeholder="Estimasi biaya bahan bakar (Rp)">
                </div>
                
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
    const estimasiBbmRp = document.getElementById('estimasi_bbm_rp');
    const kendaraanSelect = document.getElementById('kendaraan_id');
    const kendaraanFuel = document.getElementById('kendaraan_fuel');

    // Heuristics and assumptions (tunable): base efficiency (km per liter) by vehicle type
    const baseEfficiencyByType = {
        'city car': 17, // City Car / LCGC ~15-20
        'lcgc': 17,
        'sedan': 12.5,
        'suv': 10,
        'sport': 6.5,
        'hybrid': 24,
        'diesel': 11
    };

    // Fuel type multipliers and price per liter assumptions (Rp) — these are reasonable defaults and can be tuned
    const fuelMultipliers = {
        'pertamax turbo': 1.08,
        'pertamax': 1.05,
        'pertalite': 1.0,
        'dexlite': 1.12,
        'solar': 1.12
    };
    const fuelPrice = {
        'pertamax turbo': 18000,
        'pertamax': 16000,
        'pertalite': 10000,
        'dexlite': 9000,
        'solar': 9000
    };

    function normalize(str) {
        return (str || '').toString().trim().toLowerCase();
    }

    function computeEstimates() {
        const km = parseFloat(estimasiKm.value) || 0;
        const opt = kendaraanSelect.options[kendaraanSelect.selectedIndex];
        const jenis = opt ? normalize(opt.getAttribute('data-type')) : '';
        const fuel = opt ? normalize(opt.getAttribute('data-fuel')) : '';

        kendaraanFuel.value = opt ? (opt.getAttribute('data-fuel') || '') : '';

        // Determine base efficiency
        let baseEff = 10; // conservative default
        if (jenis) {
            // try exact match or contains
            for (const key in baseEfficiencyByType) {
                if (jenis.indexOf(key) !== -1) { baseEff = baseEfficiencyByType[key]; break; }
            }
        }

        // apply fuel multiplier
        let multiplier = 1.0;
        if (fuel) {
            for (const key in fuelMultipliers) {
                if (fuel.indexOf(key) !== -1) { multiplier = fuelMultipliers[key]; break; }
            }
        }

        const eff = baseEff * multiplier;
        const liters = km > 0 ? Math.max(1, Math.ceil(km / eff)) : '';
        estimasiBbm.value = liters;

        // compute cost
        let pricePerL = 15000; // default assumption
        if (fuel) {
            for (const key in fuelPrice) {
                if (fuel.indexOf(key) !== -1) { pricePerL = fuelPrice[key]; break; }
            }
        }
        if (liters) {
            const cost = liters * pricePerL;
            // format number with thousand separators
            estimasiBbmRp.value = cost.toString().replace(/\B(?=(\d{3})+(?!\d))/g, '.');
        } else {
            estimasiBbmRp.value = '';
        }
        // Sync hidden no_reg and satker inputs when vehicle changes
        const noRegInput = document.getElementById('no_reg');
        const satkerInput = document.getElementById('satker');
        if (opt) {
            const noReg = opt.getAttribute('data-no_reg') || '';
            const satk = opt.getAttribute('data-satker') || '';
            if (noRegInput) noRegInput.value = noReg;
            if (satkerInput) satkerInput.value = satk;
        }
    }

    estimasiKm.addEventListener('input', computeEstimates);
    kendaraanSelect.addEventListener('change', computeEstimates);
});
</script>
