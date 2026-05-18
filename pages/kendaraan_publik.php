<?php
// Halaman kendaraan publik untuk guest - hanya menampilkan kendaraan yang boleh dilihat publik
?>
<div class="gradient-header text-white p-4 rounded-3 mb-4">
    <h1 class="mb-2 h2"><i class="fas fa-car me-2"></i> Daftar Kendaraan Dinas</h1>
    <p class="mb-0 opacity-75">Informasi kendaraan dinas yang tersedia untuk umum</p>
</div>

<!-- Search Bar -->
<div class="actions-bar mb-3">
    <div class="search-box">
        <form class="d-flex" role="search">
            <input type="hidden" name="page" value="kendaraan_publik">
            <div class="input-group" style="max-width: 520px;">
                <input type="text" name="q" placeholder="Cari no. reg, merk, tipe, warna..." value="<?= htmlspecialchars((string)($_GET['q'] ?? '')) ?>" class="form-control">
                <button type="submit" class="btn btn-outline-success">
                    <i class="fas fa-search"></i>
                </button>
            </div>
            <?php if (!empty($_GET['q'])): ?>
                <a href="index.php?page=kendaraan_publik" class="btn btn-outline">
                    <i class="fas fa-times"></i> Reset
                </a>
            <?php endif; ?>
        </form>
    </div>
    <div class="clearfix"></div>
    <hr class="my-3">
</div>

<div class="row g-4 mb-4">
    <?php
    // Ambil data kendaraan yang boleh dilihat publik
    $keyword = trim($_GET['q'] ?? '');
    $sql = "SELECT k.*, 
                   CASE 
                       WHEN EXISTS (
                           SELECT 1 FROM peminjaman_kendaraan p
                           WHERE p.kendaraan_id = k.id AND LOWER(p.status) IN ('approved','ongoing')
                       ) THEN 'Sedang Digunakan'
                       WHEN EXISTS (
                           SELECT 1 FROM surat_tugas st
                           WHERE st.kendaraan_id = k.id AND st.status IN ('Disetujui','Dalam Perjalanan')
                       ) THEN 'Sedang Digunakan'
                       ELSE 'Tersedia'
                   END as status_penggunaan
            FROM kendaraan k
            WHERE k.status_kendaraan = 'Operasional' 
              AND k.kondisi IN ('Baik', 'Rusak Ringan')";

    $types = '';
    $params = [];
    if ($keyword !== '') {
        $sql .= " AND (k.no_polisi LIKE ? OR k.no_reg LIKE ? OR k.merk LIKE ? OR k.tipe LIKE ? OR k.warna LIKE ?)";
        $kw = "%" . $keyword . "%";
        $params = [$kw, $kw, $kw, $kw, $kw];
        $types = str_repeat('s', count($params));
    }

    $sql .= " ORDER BY k.merk, k.tipe";

    $result = false;
    if ($stmt = $mysqli->prepare($sql)) {
        if (!empty($params)) {
            $stmt->bind_param($types, ...$params);
        }
        $stmt->execute();
        $result = $stmt->get_result();
    }
    
    if ($result && $result->num_rows > 0):
        while ($kendaraan = $result->fetch_assoc()):
    ?>
        <div class="col-md-6 col-lg-4">
            <div class="card shadow-sm rounded-3 overflow-hidden card-hover h-100">
                <div class="vehicle-image-container bg-light d-flex align-items-center justify-content-center overflow-hidden" style="min-height:180px;">
                    <?php $photo = get_vehicle_photo_web_path((int)$kendaraan['id']); ?>
                    <?php if ($photo): ?>
                        <img src="<?= htmlspecialchars((string)$photo) ?>?v=<?= urlencode($kendaraan['updated_at'] ?? $kendaraan['created_at'] ?? time()) ?>" class="w-100 h-100 object-fit-cover" alt="<?= htmlspecialchars((($kendaraan['merk'] ?? '') . ' ' . ($kendaraan['tipe'] ?? ''))) ?>">
                    <?php else: ?>
                        <div class="text-center text-muted">
                            <i class="fas fa-car fa-3x d-block mb-2"></i>
                            <span>No Image</span>
                        </div>
                    <?php endif; ?>
                </div>
                
                <div class="card-body p-4">
                    <h5 class="card-title text-primary mb-3"><?= htmlspecialchars((($kendaraan['merk'] ?? '') . ' ' . ($kendaraan['tipe'] ?? ''))) ?></h5>
                    <div class="mb-3">
                        <div class="d-flex align-items-center mb-2 text-muted small">
                            <i class="fas fa-id-card text-primary me-2 icon-width"></i>
                            <span>No. Reg: <?= htmlspecialchars((string)($kendaraan['no_reg'] ?: '-')) ?></span>
                        </div>
                        <div class="d-flex align-items-center mb-2 text-muted small">
                            <i class="fas fa-calendar text-primary me-2 icon-width"></i>
                            <span><?= htmlspecialchars((string)($kendaraan['tahun_pembuatan'] ?? '-')) ?></span>
                        </div>
                        <div class="d-flex align-items-center mb-2 text-muted small">
                            <i class="fas fa-palette text-primary me-2 icon-width"></i>
                            <span><?= htmlspecialchars((string)($kendaraan['warna'] ?? '-')) ?></span>
                        </div>
                        <div class="d-flex align-items-center mb-2 text-muted small">
                            <i class="fas fa-gas-pump text-primary me-2 icon-width"></i>
                            <span><?= htmlspecialchars((string)($kendaraan['bahan_bakar'] ?? '-')) ?></span>
                        </div>
                    </div>
                    
                    <div class="mb-3">
                        <span class="badge bg-<?= strtolower($kendaraan['kondisi']) == 'baik' ? 'success' : 'warning' ?> me-2 mb-2">
                            <?= htmlspecialchars((string)($kendaraan['kondisi'] ?? '-')) ?>
                        </span>
                        <span class="badge bg-<?= strtolower($kendaraan['status_penggunaan']) == 'tersedia' ? 'info' : 'danger' ?> mb-2">
                            <?= htmlspecialchars((string)($kendaraan['status_penggunaan'] ?? '-')) ?>
                        </span>
                    </div>
                
                    <div class="text-center">
                        <a href="index.php?page=kendaraan_detail_publik&id=<?= $kendaraan['id'] ?>" 
                           class="btn btn-primary btn-sm">
                            <i class="fas fa-eye me-1"></i> Lihat Detail
                        </a>
                    </div>
                </div>
            </div>
        </div>
    <?php 
        endwhile;
    ?>
    <?php else: ?>
        <div class="col-12">
            <div class="text-center py-5 text-muted">
                <i class="fas fa-car fa-4x mb-3 d-block"></i>
                <h3>Belum Ada Data Kendaraan</h3>
                <p>Saat ini belum ada kendaraan yang tersedia untuk ditampilkan.</p>
            </div>
        </div>
    <?php endif; ?>
    <?php if (isset($stmt) && $stmt) { $stmt->close(); } ?>
</div>

<div class="alert alert-info mt-4">
    <div class="d-flex align-items-start gap-3">
        <i class="fas fa-info-circle text-info fs-4 flex-shrink-0 mt-1"></i>
        <div>
            <h6 class="alert-heading mb-2">Informasi Penting</h6>
            <p class="mb-0">Anda sedang melihat sebagai pengunjung. Untuk mengakses fitur lengkap dan melihat detail kendaraan yang lebih komprehensif, silakan <a href="login.php" class="alert-link">login ke sistem</a>.</p>
        </div>
    </div>
</div>


