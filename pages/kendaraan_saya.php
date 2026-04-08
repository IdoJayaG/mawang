<?php
require_once 'includes/auth.php';
require_login();
require_role('user');

$user_id = get_current_user_id();
$today = date('Y-m-d');

// Tampilkan kendaraan yang menjadi milik/penugasan langsung pada user (berdasarkan kolom kendaraan.pengguna_id)
if ($stmt = $mysqli->prepare("SELECT k.*, k.status_peminjaman AS status FROM kendaraan k WHERE k.pengguna_id = ? ORDER BY COALESCE(k.no_reg, k.no_polisi, k.merk, k.id)")) {
    $stmt->bind_param('i', $user_id);
    $stmt->execute();
    $kendaraan_list = $stmt->get_result();
    $stmt->close();
} else {
    // Jika query gagal, tampilkan kosong agar UI tetap aman
    $kendaraan_list = $mysqli->query("SELECT 1 WHERE 0");
}
?>

<div class="page-header">
        <h1><i class="fas fa-car"></i> Kendaraan Saya</h1>
</div>
        <div class="gradient-header text-white p-4 rounded-3 mb-4">
            <div class="d-flex justify-content-between align-items-center flex-wrap gap-3">
        <div class="header-actions">
            <a href="index.php?page=home" class="btn btn-outline-light btn-hover-light">
                <i class="fas fa-home me-1"></i> Kembali ke Beranda
            </a>
        </div>
    </div>

<div class="p-0">
    <?php if ($kendaraan_list->num_rows > 0): ?>
        <div class="row g-4">
            <?php while ($kendaraan = $kendaraan_list->fetch_assoc()): ?>
                <div class="col-lg-6">
                    <div class="card shadow-sm rounded-3 overflow-hidden card-hover">
                        <div class="card-header bg-light p-3 border-bottom">
                            <div class="d-flex justify-content-between align-items-start">
                                <div class="vehicle-info">
                                    <p class="mb-0 fw-bold text-gradient"><?= htmlspecialchars($kendaraan['merk'] . ' ' . $kendaraan['tipe']) ?></p>
                                    <p class="mb-0 fw-bold text-gradient">No. Reg: <?= htmlspecialchars($kendaraan['no_reg'] ?: '-') ?></p>
                                </div>
                                <div class="d-flex flex-column gap-2 align-items-end">
                                    <span class="badge status-badge bg-<?= strtolower($kendaraan['status_kendaraan']) == 'operasional' ? 'success' : 'warning' ?> rounded-pill">
                                        <?= htmlspecialchars($kendaraan['status_kendaraan']) ?>
                                    </span>
                                    <?php
                                        $status_label = strtolower($kendaraan['status'] ?? '');
                                        $badgeClass = 'secondary';
                                        if ($status_label === 'tersedia') {
                                            $badgeClass = 'success';
                                        } elseif ($status_label === 'dipinjam') {
                                            $badgeClass = 'warning';
                                        } elseif ($status_label === 'maintenance') {
                                            $badgeClass = 'info';
                                        } elseif ($status_label === 'rusak') {
                                            $badgeClass = 'danger';
                                        }
                                    ?>
                                    <span class="badge assignment-badge bg-<?= $badgeClass ?> rounded-pill">
                                        <?= htmlspecialchars($kendaraan['status'] ?? '-') ?>
                                    </span>
                                </div>
                            </div>
                        </div>
                        
                        <div class="card-body p-3">
                            <div class="mb-3">
                                <?php $photo = get_vehicle_photo_web_path((int)$kendaraan['id']); ?>
                                <?php if ($photo): ?>
                                    <img src="<?= htmlspecialchars($photo) ?>?v=<?= urlencode($kendaraan['updated_at'] ?? $kendaraan['created_at'] ?? time()) ?>" alt="Foto" style="width:100%; max-height:180px; object-fit:cover; border-radius:8px; border:1px solid #eee;" />
                                <?php else: ?>
                                    <div class="text-center text-muted" style="height:180px; display:flex; align-items:center; justify-content:center; background:#f8f9fa; border-radius:8px; border:1px dashed #e5e7eb;">
                                        <i class="fas fa-car fa-2x"></i>
                                    </div>
                                <?php endif; ?>
                            </div>
                            <div class="row g-3 mb-3">
                                <div class="col-md-4">
                                    <div class="d-flex align-items-center gap-2 text-muted small">
                                        <i class="fas fa-palette text-primary"></i>
                                        <span><?= htmlspecialchars($kendaraan['warna']) ?></span>
                                    </div>
                                </div>
                                <div class="col-md-4">
                                    <div class="d-flex align-items-center gap-2 text-muted small">
                                        <i class="fas fa-calendar text-primary"></i>
                                        <span><?= htmlspecialchars($kendaraan['tahun_pembuatan']) ?></span>
                                    </div>
                                </div>
                                <div class="col-md-4">
                                    <div class="d-flex align-items-center gap-2 text-muted small">
                                        <i class="fas fa-gas-pump text-primary"></i>
                                        <span><?= htmlspecialchars($kendaraan['bahan_bakar']) ?></span>
                                    </div>
                                </div>
                            </div>
                            
                            <?php if (!empty($kendaraan['tanggal_mulai']) || !empty($kendaraan['tanggal_selesai']) || !empty($kendaraan['keterangan'])): ?>
                                <div class="bg-light p-3 rounded-2 border-start border-primary border-4">
                                    <h6 class="mb-3 fw-bold text-dark"><i class="fas fa-calendar-alt me-2 text-primary"></i> Informasi Penugasan</h6>
                                    <div class="d-flex flex-column gap-2">
                                        <?php if (!empty($kendaraan['tanggal_mulai'])): ?>
                                        <div class="d-flex justify-content-between align-items-center small">
                                            <span class="fw-bold text-secondary">Tanggal Mulai:</span>
                                            <span class="text-muted"><?= date('d/m/Y', strtotime($kendaraan['tanggal_mulai'])) ?></span>
                                        </div>
                                        <?php endif; ?>
                                        <?php if (!empty($kendaraan['tanggal_selesai'])): ?>
                                            <div class="d-flex justify-content-between align-items-center small">
                                                <span class="fw-bold text-secondary">Tanggal Selesai:</span>
                                                <span class="text-muted"><?= date('d/m/Y', strtotime($kendaraan['tanggal_selesai'])) ?></span>
                                            </div>
                                        <?php endif; ?>
                                        <?php if (!empty($kendaraan['keterangan'])): ?>
                                            <div class="d-flex justify-content-between align-items-center small">
                                                <span class="fw-bold text-secondary">Keterangan:</span>
                                                <span class="text-muted"><?= htmlspecialchars($kendaraan['keterangan']) ?></span>
                                            </div>
                                        <?php endif; ?>
                                    </div>
                                </div>
                            <?php endif; ?>
                        </div>
                        
                        <div class="card-footer bg-light p-3 border-top d-flex gap-2">
                            <a href="index.php?page=kendaraan_detail&id=<?= $kendaraan['id'] ?>" 
                               class="btn btn-primary flex-fill">
                                <i class="fas fa-eye me-1"></i> Lihat Detail
                            </a>
                            <a href="index.php?page=pengajuan_perawatan&kendaraan_id=<?= $kendaraan['id'] ?>" 
                               class="btn btn-outline-warning flex-fill">
                                <i class="fas fa-tools me-1"></i> Perawatan
                            </a>
                            <a href="index.php?page=log_bahan_bakar&action=add&kendaraan_id=<?= $kendaraan['id'] ?>" 
                               class="btn btn-success flex-fill">
                                <i class="fas fa-gas-pump me-1"></i> Edit BBM
                            </a>
                        </div>
                    </div>
                </div>
            <?php endwhile; ?>
        </div>
    <?php else: ?>
        <div class="text-center py-5">
            <div class="card shadow-sm rounded-3 p-5">
                <div class="empty-icon mb-4">
                    <i class="fas fa-car text-muted display-1"></i>
                </div>
                <h3 class="text-muted mb-3">Belum Ada Kendaraan</h3>
                <p class="text-secondary mb-4">Anda belum memiliki kendaraan yang ditugaskan.</p>
                <div class="empty-actions">
                    <a href="index.php?page=home" class="btn btn-primary">
                        <i class="fas fa-home me-1"></i> Kembali ke Beranda
                    </a>
                </div>
            </div>
        </div>
    <?php endif; ?>
</div>

<?php
// Section: Penugasan via Surat Tugas (tampilkan semua yang aktif untuk user)
try {
    $tbl_st = $mysqli->query("SHOW TABLES LIKE 'surat_tugas'");
    $has_st_tbl = $tbl_st && $tbl_st->num_rows > 0; if ($tbl_st) $tbl_st->free_result();
} catch (mysqli_sql_exception $e) { $has_st_tbl = false; }

if (!empty($has_st_tbl)) {
    $st_list = false;
    $st_stmt_all = $mysqli->prepare("SELECT k.*, s.id AS surat_id, s.nomor_surat, s.tanggal_berangkat AS tanggal_mulai, s.tanggal_kembali AS tanggal_selesai, s.tujuan AS keterangan, s.status FROM surat_tugas s JOIN kendaraan k ON k.id = s.kendaraan_id WHERE s.pengguna_id = ? AND s.status IN ('Disetujui','Dalam Perjalanan') AND s.tanggal_berangkat <= ? AND (s.tanggal_kembali IS NULL OR s.tanggal_kembali >= ?) ORDER BY s.tanggal_berangkat DESC");
    if ($st_stmt_all) {
        $st_stmt_all->bind_param('iss', $user_id, $today, $today);
        $st_stmt_all->execute();
        $st_list = $st_stmt_all->get_result();
        $st_stmt_all->close();
    }
    if ($st_list && $st_list->num_rows > 0) {
        ?>
        <div class="mt-4">
            <div class="gradient-header text-white p-3 rounded-3 mb-3">
                <h5 class="mb-0"><i class="fas fa-file-signature me-2"></i> Penugasan via Surat Tugas (Aktif)</h5>
            </div>
            <div class="row g-4">
                <?php while ($row = $st_list->fetch_assoc()): ?>
                    <div class="col-lg-6">
                        <div class="card shadow-sm rounded-3 overflow-hidden card-hover">
                            <div class="card-header bg-light p-3 border-bottom d-flex justify-content-between align-items-start">
                                <div>
                                    <h6 class="mb-1 text-muted"><?= htmlspecialchars($row['merk'] . ' ' . $row['tipe']) ?></h6>
                                    <div class="small text-muted"><strong>No. Reg: <?= htmlspecialchars($row['no_reg'] ?: '-') ?></strong></div>
                                </div>
                                <div class="text-end">
                                    <span class="badge bg-dark">Surat Tugas</span>
                                    <div class="small mt-1">No: <strong><?= htmlspecialchars($row['nomor_surat'] ?? '-') ?></strong></div>
                                    <span class="badge ms-1 bg-<?= strtolower($row['status']) === 'dalam perjalanan' ? 'warning' : 'success' ?>"><?= htmlspecialchars($row['status']) ?></span>
                                </div>
                            </div>
                            <div class="px-3 pt-3">
                                <?php $photo2 = get_vehicle_photo_web_path((int)$row['id']); ?>
                                <?php if ($photo2): ?>
                                    <img src="<?= htmlspecialchars($photo2) ?>?v=<?= urlencode($row['updated_at'] ?? $row['created_at'] ?? time()) ?>" alt="Foto" style="width:100%; max-height:160px; object-fit:cover; border-radius:8px; border:1px solid #eee;" />
                                <?php endif; ?>
                            </div>
                            <div class="card-body p-3">
                                <div class="bg-light p-3 rounded-2 border-start border-primary border-4">
                                    <div class="d-flex justify-content-between small mb-1">
                                        <span class="fw-bold text-secondary">Tanggal Mulai:</span>
                                        <span class="text-muted"><?= date('d/m/Y', strtotime($row['tanggal_mulai'])) ?></span>
                                    </div>
                                    <?php if (!empty($row['tanggal_selesai'])): ?>
                                    <div class="d-flex justify-content-between small mb-1">
                                        <span class="fw-bold text-secondary">Tanggal Selesai:</span>
                                        <span class="text-muted"><?= date('d/m/Y', strtotime($row['tanggal_selesai'])) ?></span>
                                    </div>
                                    <?php endif; ?>
                                    <?php if (!empty($row['keterangan'])): ?>
                                    <div class="d-flex justify-content-between small">
                                        <span class="fw-bold text-secondary">Tujuan:</span>
                                        <span class="text-muted"><?= htmlspecialchars($row['keterangan']) ?></span>
                                    </div>
                                    <?php endif; ?>
                                </div>
                            </div>
                            <div class="card-footer bg-light p-3 border-top d-flex gap-2">
                                <a href="index.php?page=kendaraan_detail_user&id=<?= (int)$row['id'] ?>" class="btn btn-primary btn-sm flex-fill"><i class="fas fa-eye me-1"></i> Lihat Kendaraan</a>
                                <a href="index.php?page=surat_tugas&action=view&id=<?= (int)$row['surat_id'] ?>" class="btn btn-outline-secondary btn-sm flex-fill"><i class="fas fa-file-alt me-1"></i> Lihat Surat</a>
                            </div>
                        </div>
                    </div>
                <?php endwhile; ?>
            </div>
        </div>
        <?php
    }
}
?>


