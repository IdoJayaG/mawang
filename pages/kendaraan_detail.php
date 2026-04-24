<?php
require_once 'includes/auth.php';
require_login(); // Semua role yang sudah login bisa melihat detail

$id = (int)($_GET['id'] ?? 0);
if (!$id) {
    header('Location: index.php?page=kendaraan');
    exit;
}

$current_role = get_current_role();
$can_edit = can_operate() || can_admin();

// Small helper to check for optional columns in the current database schema.
if (!function_exists('table_has_columns')) {
    function table_has_columns($mysqli, $table, array $cols) {
        $table = $mysqli->real_escape_string($table);
        foreach ($cols as $col) {
            $colEsc = $mysqli->real_escape_string($col);
            $res = $mysqli->query("SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = '{$table}' AND COLUMN_NAME = '{$colEsc}' LIMIT 1");
            if (!$res || $res->num_rows === 0) return false;
        }
        return true;
    }
}

// Helper: cek keberadaan tabel
if (!function_exists('table_exists_generic')) {
    function table_exists_generic($mysqli, $table) {
        try {
            $t = $mysqli->real_escape_string($table);
            $res = $mysqli->query("SHOW TABLES LIKE '" . $t . "'");
            $exists = $res && $res->num_rows > 0;
            if ($res) $res->free_result();
            return $exists;
        } catch (mysqli_sql_exception $e) {
            return false;
        }
    }
}

// Get vehicle data
$stmt = $mysqli->prepare("SELECT * FROM kendaraan WHERE id = ?");
$stmt->bind_param("i", $id);
$stmt->execute();
$result = $stmt->get_result();
$kendaraan = $result->fetch_assoc();

if (!$kendaraan) {
    header('Location: index.php?page=kendaraan');
    exit;
}

// Check if user has access to this vehicle (schema-aware; no dependency on pengguna_kendaraan)
if (in_array(strtolower((string)$current_role), ['user', 'driver'], true)) {
    $has_access = false;
    $user_account_id = (int)($_SESSION['user_id'] ?? 0);
    $pengguna_id = 0;
    // Fetch pengguna_id from user_account
    if ($st = $mysqli->prepare("SELECT pengguna_id FROM user_account WHERE id = ?")) {
        $st->bind_param('i', $user_account_id);
        $st->execute();
        $row = $st->get_result()->fetch_assoc();
        $pengguna_id = (int)($row['pengguna_id'] ?? 0);
        $st->close();
    }

    // 1) Check peminjaman_kendaraan linkage
    if (table_exists_generic($mysqli, 'peminjaman_kendaraan')) {
        $cols = [];
        if ($resCols = $mysqli->query("SHOW COLUMNS FROM peminjaman_kendaraan")) {
            while ($r = $resCols->fetch_assoc()) { $cols[] = $r['Field']; }
            $resCols->free_result();
        }
        $borrower_col = null;
        foreach (['peminjam_id','pemohon_id','user_id','pengguna_id'] as $c) { if (in_array($c, $cols, true)) { $borrower_col = $c; break; } }
        if ($borrower_col && in_array('kendaraan_id', $cols, true)) {
            // Bind to correct id type
            $bind_id = in_array($borrower_col, ['pengguna_id'], true) ? $pengguna_id : $user_account_id;
            $sql = "SELECT 1 FROM peminjaman_kendaraan WHERE {$borrower_col} = ? AND kendaraan_id = ? LIMIT 1";
            if ($st = $mysqli->prepare($sql)) {
                $st->bind_param('ii', $bind_id, $id);
                $st->execute();
                if ($st->get_result()->num_rows > 0) { $has_access = true; }
                $st->close();
            }
        }
    }

    // 2) Check surat_tugas linkage
    if (!$has_access && table_exists_generic($mysqli, 'surat_tugas')) {
        $cols = [];
        if ($resCols = $mysqli->query("SHOW COLUMNS FROM surat_tugas")) {
            while ($r = $resCols->fetch_assoc()) { $cols[] = $r['Field']; }
            $resCols->free_result();
        }
        if (in_array('pengguna_id', $cols, true) && in_array('kendaraan_id', $cols, true)) {
            $sql = "SELECT 1 FROM surat_tugas WHERE pengguna_id = ? AND kendaraan_id = ? LIMIT 1";
            if ($st = $mysqli->prepare($sql)) {
                $st->bind_param('ii', $pengguna_id, $id);
                $st->execute();
                if ($st->get_result()->num_rows > 0) { $has_access = true; }
                $st->close();
            }
        }
    }

    if (!$has_access) {
        header('Location: index.php?page=403');
        exit;
    }
}

// Get usage history
$driver_join = table_has_columns($mysqli, 'riwayat_pemakaian', ['driver_id']);

$select_driver = $driver_join ? "COALESCE(p.nama_lengkap, '') as nama_pengguna, COALESCE(p.pangkat, '') as pangkat, COALESCE(p.nrp_nip, '') as nrp_nip," : "'' as nama_pengguna, '' as pangkat, '' as nrp_nip,";
$join_driver = $driver_join ? "LEFT JOIN pengguna p ON rp.driver_id = p.id" : "";

$usage_sql = "
    SELECT 
        rp.id,
        rp.kendaraan_id,
        rp.tanggal as tanggal_mulai,
        NULL as tanggal_selesai,
        " . $select_driver . "
        rp.tujuan,
        rp.km_awal,
        rp.km_akhir,
        COALESCE(rp.catatan, '') as keterangan
    FROM riwayat_pemakaian rp
    " . $join_driver . "
    WHERE rp.kendaraan_id = ?
    ORDER BY rp.tanggal DESC
    LIMIT 10
";

// We won't display riwayat pemakaian table; keep optional data load minimal
$usage_stmt = $mysqli->prepare($usage_sql);
$usage_stmt->bind_param("i", $id);
$usage_stmt->execute();
$usage_history = $usage_stmt->get_result()->fetch_all(MYSQLI_ASSOC);

// Get maintenance history  
$maintenance_stmt = $mysqli->prepare("
    SELECT rp.*, u.username as created_by_name
    FROM riwayat_perawatan rp
    LEFT JOIN user_account u ON rp.created_by = u.id
    WHERE rp.kendaraan_id = ? 
    ORDER BY rp.tanggal_perawatan DESC 
    LIMIT 10
");
$maintenance_stmt->bind_param("i", $id);
$maintenance_stmt->execute();
$maintenance_history = $maintenance_stmt->get_result()->fetch_all(MYSQLI_ASSOC);

// Get BBM history for this vehicle (recent 10)
$bbm_stmt = $mysqli->prepare("SELECT lb.*, p.nama_lengkap as dibuat_oleh FROM log_bahan_bakar lb LEFT JOIN pengguna p ON lb.user_id = p.id WHERE lb.kendaraan_id = ? ORDER BY lb.tanggal_isi DESC LIMIT 10");
$bbm_stmt->bind_param('i', $id);
$bbm_stmt->execute();
$bbm_history = $bbm_stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$bbm_stmt->close();

// Get current user assigned to this vehicle (schema-aware)
$current_user = null;
// Prefer ongoing peminjaman_kendaraan
if (table_exists_generic($mysqli, 'peminjaman_kendaraan')) {
    $cols = [];
    if ($rc = $mysqli->query("SHOW COLUMNS FROM peminjaman_kendaraan")) { while ($r=$rc->fetch_assoc()) $cols[]=$r['Field']; $rc->free_result(); }
    $borrower_col = null;
    foreach (['peminjam_id','pemohon_id','user_id'] as $c) { if (in_array($c, $cols, true)) { $borrower_col = $c; break; } }
    if ($borrower_col && in_array('status',$cols,true)) {
        $sql = "SELECT ua.pengguna_id, p.nama_lengkap, p.nrp_nip, p.pangkat, pk.tanggal_mulai, pk.tanggal_selesai
                FROM peminjaman_kendaraan pk
                LEFT JOIN user_account ua ON pk.{$borrower_col} = ua.id
                LEFT JOIN pengguna p ON ua.pengguna_id = p.id
                WHERE pk.kendaraan_id = ? AND pk.status IN ('approved','ongoing')
                ORDER BY pk.tanggal_mulai DESC LIMIT 1";
        if ($st = $mysqli->prepare($sql)) {
            $st->bind_param('i', $id);
            $st->execute();
            $current_user = $st->get_result()->fetch_assoc();
            $st->close();
        }
    }
}
// Fallback to surat_tugas
if (!$current_user && table_exists_generic($mysqli, 'surat_tugas')) {
    $sql = "SELECT p.nama_lengkap, p.nrp_nip, p.pangkat, st.tanggal_berangkat as tanggal_mulai, st.tanggal_kembali as tanggal_selesai
            FROM surat_tugas st
            LEFT JOIN pengguna p ON st.pengguna_id = p.id
            WHERE st.kendaraan_id = ? AND st.status IN ('Disetujui','Dalam Perjalanan')
            ORDER BY st.tanggal_berangkat DESC LIMIT 1";
    if ($st = $mysqli->prepare($sql)) {
        $st->bind_param('i', $id);
        $st->execute();
        $current_user = $st->get_result()->fetch_assoc();
        $st->close();
    }
}

// Prefer assigned pengguna via kendaraan.pengguna_id if available
$assigned_pengguna = null;
if (table_has_columns($mysqli, 'kendaraan', ['pengguna_id'])) {
    $assigned_id = (int)($kendaraan['pengguna_id'] ?? 0);
    if ($assigned_id > 0) {
        if ($st = $mysqli->prepare("SELECT id, nama_lengkap, pangkat, jabatan, nrp_nip, no_hp, email FROM pengguna WHERE id = ? LIMIT 1")) {
            $st->bind_param('i', $assigned_id);
            $st->execute();
            $assigned_pengguna = $st->get_result()->fetch_assoc();
            $st->close();
        }
    }
}

// Format kondisi badge
function get_kondisi_badge($kondisi) {
    $class = match($kondisi) {
        'Baik' => 'badge-baik',
        'Rusak Ringan' => 'badge-rusak-ringan', 
        'Rusak Berat' => 'badge-rusak-berat',
        default => 'badge-secondary'
    };
    return "<span class='badge $class'>$kondisi</span>";
}

// Format status badge (define only if not already declared)
if (!function_exists('get_status_badge')) {
    function get_status_badge($status) {
        $class = match($status) {
            'Operasional' => 'badge-operasional',
            'Perbaikan' => 'badge-perbaikan',
            'Rusak' => 'badge-rusak',
            'Tidak Aktif' => 'badge-tidak-aktif',
            default => 'badge-secondary'
        };
        return "<span class='badge $class'>$status</span>";
    }
}
?>
<div class="page-header">
    <h1><i class="fas fa-car"></i> Detail Kendaraan</h1>
    <p class="mb-0">No. Reg: <?= htmlspecialchars($kendaraan['no_reg'] ?: '-') ?></p>
    <div class="header-actions">
        <a href="index.php?page=kendaraan" class="btn btn-outline">
            <i class="fas fa-arrow-left"></i> Kembali ke Daftar
        </a>
        <?php if ($can_edit): ?>
            <a href="index.php?page=kendaraan&action=edit&id=<?= $kendaraan['id'] ?>" class="btn btn-warning">
                <i class="fas fa-edit"></i> Edit Data
            </a>
        <?php endif; ?>
    </div>
</div>

<div class="detail-container">
    <!-- Vehicle Photo -->
    <div class="card">
        <div class="card-body d-flex align-items-center justify-content-center" style="background:#f8f9fa; min-height: 220px;">
            <?php $photo = get_vehicle_photo_web_path((int)$kendaraan['id']); ?>
            <?php if ($photo): ?>
                <img src="<?= htmlspecialchars($photo) ?>?v=<?= urlencode($kendaraan['updated_at'] ?? $kendaraan['created_at'] ?? time()) ?>" alt="Foto Kendaraan" style="max-width:100%; max-height:300px; object-fit:contain; border-radius:8px;" />
            <?php else: ?>
                <div class="text-center text-muted">
                    <i class="fas fa-car fa-3x d-block mb-2"></i>
                    <span>Foto tidak tersedia</span>
                </div>
            <?php endif; ?>
        </div>
    </div>

    <!-- Vehicle Info Card -->
    <div class="card">
        <div class="card-header">
            <h3><i class="fas fa-info-circle"></i> Informasi Kendaraan</h3>
        </div>
        <div class="card-body">
            <div class="detail-grid">
                <div class="detail-group">
                    <label>Nomor Registrasi</label>
                    <div class="detail-value highlight">
                        <?= htmlspecialchars($kendaraan['no_reg'] ?: '-') ?>
                    </div>
                </div>
                
                <div class="detail-group">
                    <label>Merk & Tipe</label>
                    <div class="detail-value">
                        <strong><?= htmlspecialchars($kendaraan['merk']) ?></strong>
                        <?php if ($kendaraan['tipe']): ?>
                            <br><small class="text-muted"><?= htmlspecialchars($kendaraan['tipe']) ?></small>
                        <?php endif; ?>
                    </div>
                </div>
                
                <div class="detail-group">
                    <label>Tahun Pembuatan</label>
                    <div class="detail-value">
                        <?= htmlspecialchars($kendaraan['tahun_pembuatan'] ?: '-') ?>
                    </div>
                </div>
                
                <div class="detail-group">
                    <label>Warna</label>
                    <div class="detail-value">
                        <?= htmlspecialchars($kendaraan['warna'] ?: '-') ?>
                    </div>
                </div>
                
                <div class="detail-group">
                    <label>Jenis Kendaraan</label>
                    <div class="detail-value">
                        <span class="badge badge-info text-dark">
                            <?= htmlspecialchars($kendaraan['jenis']) ?>
                        </span>
                    </div>
                </div>
                
                <div class="detail-group">
                    <label>Bahan Bakar</label>
                    <div class="detail-value">
                        <?= htmlspecialchars($kendaraan['bahan_bakar'] ?: '-') ?>
                    </div>
                </div>
                
                <div class="detail-group">
                    <label>Nomor Rangka</label>
                    <div class="detail-value">
                        <?= htmlspecialchars($kendaraan['no_rangka'] ?: '-') ?>
                    </div>
                </div>
                
                <div class="detail-group">
                    <label>Nomor Mesin</label>
                    <div class="detail-value">
                        <?= htmlspecialchars($kendaraan['no_mesin'] ?: '-') ?>
                    </div>
                </div>
                
                <div class="detail-group">
                    <label>Kondisi</label>
                    <div class="detail-value">
                        <?= get_kondisi_badge($kendaraan['kondisi']) ?>
                    </div>
                </div>
                
                <div class="detail-group">
                    <label>Status</label>
                    <div class="detail-value">
                        <small class="ms-2 badge badge-<?= strtolower(str_replace(' ', '-', $kendaraan['status_kendaraan'])) ?>"><?= htmlspecialchars($kendaraan['status_kendaraan']) ?></small>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Current User Card -->
    <?php if ($current_user): ?>
        <div class="card">
            <div class="card-header">
                <h3><i class="fas fa-user"></i> Pengguna Saat Ini</h3>
            </div>
            <div class="card-body">
                <div class="current-user-info">
                    <div class="user-avatar">
                        <i class="fas fa-user-circle"></i>
                    </div>
                    <div class="user-details">
                        <h4><?= htmlspecialchars($current_user['nama_lengkap']) ?></h4>
                        <p class="user-meta">
                            <span class="rank"><?= htmlspecialchars($current_user['pangkat']) ?></span>
                            <span class="nrp">NRP: <?= htmlspecialchars($current_user['nrp_nip']) ?></span>
                        </p>
                        <p class="usage-period">
                            <i class="fas fa-calendar"></i>
                            Periode: <?= date('d/m/Y', strtotime($current_user['tanggal_mulai'])) ?>
                            <?php if ($current_user['tanggal_selesai']): ?>
                                - <?= date('d/m/Y', strtotime($current_user['tanggal_selesai'])) ?>
                            <?php else: ?>
                                - Sekarang
                            <?php endif; ?>
                        </p>
                    </div>
                </div>
            </div>
        </div>
    <?php endif; ?>

    <!-- Current Assignment (replacing Riwayat Pemakaian) -->
    <div class="card">
        <div class="card-header d-flex justify-content-between align-items-center">
            <h3><i class="fas fa-user-check"></i> Data Pengguna Saat Ini</h3>
        </div>
        <div class="card-body">
            <?php 
                // Prefer directly assigned pengguna, else fall back to ongoing peminjaman/surat_tugas result
                $active = $assigned_pengguna ?: $current_user; 
            ?>
            <?php if ($active): ?>
                <div class="row g-3 align-items-center">
                    <div class="col-auto">
                        <i class="fas fa-user-circle" style="font-size:48px;color:#6c757d"></i>
                    </div>
                    <div class="col">
                        <div class="fw-bold" style="font-size:1.1rem;"><?= htmlspecialchars($active['nama_lengkap'] ?? '-') ?></div>
                        <div class="text-muted">
                            <span><?= htmlspecialchars($active['pangkat'] ?? '-') ?></span>
                            <?php if (!empty($active['jabatan'])): ?>
                                <span class="ms-2">• <?= htmlspecialchars($active['jabatan']) ?></span>
                            <?php endif; ?>
                            <?php if (!empty($active['nrp_nip'])): ?>
                                <span class="ms-2">• NRP/NIP: <?= htmlspecialchars($active['nrp_nip']) ?></span>
                            <?php endif; ?>
                        </div>
                        <?php if (!empty($current_user['tanggal_mulai'])): ?>
                            <div class="mt-1 small text-muted">
                                Periode: <?= date('d/m/Y', strtotime($current_user['tanggal_mulai'])) ?>
                                <?= !empty($current_user['tanggal_selesai']) ? (' - ' . date('d/m/Y', strtotime($current_user['tanggal_selesai']))) : ' - Sekarang' ?>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            <?php else: ?>
                <div class="empty-state">
                    <i class="fas fa-user-slash"></i>
                    <h4>Tidak Ada Pengguna Aktif</h4>
                    <p>Kendaraan ini belum memiliki pengguna saat ini.</p>
                </div>
            <?php endif; ?>
        </div>
    </div>

    <!-- Maintenance History -->
    <div class="card">
        <div class="card-header">
            <h3><i class="fas fa-tools"></i> Riwayat Perawatan</h3>
        </div>
        <div class="card-body">
            <?php if (count($maintenance_history) > 0): ?>
                <div class="table-responsive">
                    <table class="table">
                        <thead>
                            <tr>
                                <th>Tanggal</th>
                                <th>Jenis Perawatan</th>
                                <th>Keterangan</th>
                                <th>Biaya</th>
                                <th>KM Service</th>
                                <th>Mekanik</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($maintenance_history as $maintenance): ?>
                                <tr>
                                    <td>
                                        <?= date('d/m/Y', strtotime($maintenance['tanggal_perawatan'])) ?>
                                    </td>
                                <td>
                                    <span class="badge bg-<?= strtolower(str_replace(' ', '-', $maintenance['jenis_perawatan'])) ?> text-dark">
                                        <?= htmlspecialchars($maintenance['jenis_perawatan']) ?>
                                    </span>
                                </td>
                                    <td><?= htmlspecialchars($maintenance['keterangan'] ?: '-') ?></td>
                                    <td>
                                        <?php if ($maintenance['biaya']): ?>
                                            Rp <?= number_format($maintenance['biaya']) ?>
                                        <?php else: ?>
                                            -
                                        <?php endif; ?>
                                    </td>
                                    <td><?= $maintenance['km_saat_perawatan'] ? number_format($maintenance['km_saat_perawatan']) . ' km' : '-' ?></td>
                                    <td><?= htmlspecialchars($maintenance['mekanik'] ?: '-') ?></td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php else: ?>
                <div class="empty-state">
                    <i class="fas fa-tools"></i>
                    <h4>Belum Ada Riwayat Perawatan</h4>
                    <p>Kendaraan ini belum pernah dilakukan perawatan</p>
                </div>
            <?php endif; ?>
        </div>
    </div>

    <!-- BBM History -->
    <!-- <div class="card">
        <div class="card-header">
            <h3><i class="fas fa-gas-pump"></i> Riwayat BBM</h3>
        </div>
        <div class="card-body">
            <?php if (!empty($bbm_history)): ?>
                <div class="table-responsive">
                    <table class="table">
                        <thead>
                            <tr>
                                <th>Tanggal</th>
                                <th>Jumlah (L)</th>
                                <th>Harga/L</th>
                                <th>Total</th>
                                <th>KM Saat Isi</th>
                                <th>SPBU</th>
                                <th>Dibuat Oleh</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($bbm_history as $log): ?>
                                <tr>
                                    <td><?= !empty($log['tanggal_isi']) ? date('d/m/Y H:i', strtotime($log['tanggal_isi'])) : '-' ?></td>
                                    <td><?= number_format((float)($log['jumlah_liter'] ?? 0), 2) ?> L</td>
                                    <td>Rp <?= number_format((float)($log['harga_per_liter'] ?? 0), 2) ?></td>
                                    <td>Rp <?= number_format((float)($log['biaya'] ?? 0), 2) ?></td>
                                    <td><?= !empty($log['km_saat_isi']) ? number_format($log['km_saat_isi']) : '-' ?></td>
                                    <td><?= htmlspecialchars($log['spbu'] ?? '-') ?></td>
                                    <td><?= htmlspecialchars($log['dibuat_oleh'] ?? '-') ?></td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php else: ?>
                <div class="empty-state">
                    <i class="fas fa-gas-pump"></i>
                    <h4>Belum Ada Riwayat BBM</h4>
                    <p>Belum ada catatan pengisian bahan bakar untuk kendaraan ini.</p>
                </div>
            <?php endif; ?>
        </div>
    </div> -->
</div>
