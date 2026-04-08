<?php
// Detail kendaraan untuk guest - accept id, kendaraan_id, or no_reg
$id = 0;
if (isset($_GET['id'])) {
    $id = (int)$_GET['id'];
} elseif (isset($_GET['kendaraan_id'])) {
    $id = (int)$_GET['kendaraan_id'];
}
$no_reg = isset($_GET['no_reg']) ? trim($_GET['no_reg']) : '';

// If no numeric id provided, try to look up by registration number
if ($id <= 0 && $no_reg !== '') {
    $lookup = $mysqli->prepare("SELECT id FROM kendaraan WHERE no_reg = ? LIMIT 1");
    if ($lookup) {
        $lookup->bind_param('s', $no_reg);
        $lookup->execute();
        $res = $lookup->get_result()->fetch_assoc();
        $lookup->close();
        if ($res && !empty($res['id'])) {
            $id = (int)$res['id'];
        }
    }
}

if ($id <= 0) {
    echo '<div class="alert alert-danger">ID kendaraan tidak valid</div>';
    exit;
}

// Ambil data kendaraan dengan filter untuk guest
$stmt = $mysqli->prepare("
        SELECT k.*, 
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
                WHERE k.id = ? 
                    AND k.status_kendaraan = 'Operasional' 
                    AND k.kondisi IN ('Baik', 'Rusak Ringan')
");
$stmt->bind_param('i', $id);
$stmt->execute();
$result = $stmt->get_result();
$kendaraan = $result->fetch_assoc();
$stmt->close();

if (!$kendaraan) {
    echo '<div class="alert alert-danger">Kendaraan tidak ditemukan atau tidak dapat diakses</div>';
    exit;
}

// Ambil informasi pengguna kendaraan (prioritas: yang ditugaskan langsung di kendaraan.pengguna_id)
$pengguna = null;
try {
    // Cek apakah kolom pengguna_id ada di tabel kendaraan
    $hasPenggunaCol = false;
    if ($resCol = $mysqli->query("SHOW COLUMNS FROM kendaraan LIKE 'pengguna_id'")) {
        $hasPenggunaCol = $resCol->num_rows > 0;
        $resCol->close();
    }
    if ($hasPenggunaCol) {
        $assignedId = (int)($kendaraan['pengguna_id'] ?? 0);
        if ($assignedId > 0) {
            if ($st = $mysqli->prepare("SELECT nama_lengkap, pangkat, jabatan FROM pengguna WHERE id = ? LIMIT 1")) {
                $st->bind_param('i', $assignedId);
                $st->execute();
                $pengguna = $st->get_result()->fetch_assoc();
                $st->close();
            }
        }
    }
    // Fallback: ambil dari surat tugas terbaru jika belum ada yang ditugaskan langsung
    if (!$pengguna) {
        if ($st = $mysqli->prepare("SELECT p.nama_lengkap, p.pangkat, p.jabatan FROM surat_tugas st LEFT JOIN pengguna p ON st.pengguna_id = p.id WHERE st.kendaraan_id = ? AND st.status IN ('Disetujui','Dalam Perjalanan') ORDER BY st.tanggal_berangkat DESC LIMIT 1")) {
            $st->bind_param('i', $id);
            $st->execute();
            $pengguna = $st->get_result()->fetch_assoc();
            $st->close();
        }
    }
} catch (Throwable $e) {
    // abaikan kesalahan untuk tampilan publik
}
?>

<div class="gradient-header text-white p-4 rounded-3 mb-4">
    <div>
        <nav class="mb-3 opacity-75">
            <a href="index.php" class="text-white text-decoration-none">Beranda</a>
            <i class="fas fa-chevron-right mx-2 opacity-50"></i>
            <a href="index.php?page=kendaraan_publik" class="text-white text-decoration-none">Daftar Kendaraan</a>
            <i class="fas fa-chevron-right mx-2 opacity-50"></i>
            <span>Detail Kendaraan</span>
        </nav>
        <h1 class="mb-2 h2"><?= htmlspecialchars($kendaraan['merk'] . ' ' . $kendaraan['tipe']) ?></h1>
        <p class="mb-0 opacity-75">
            Informasi detail kendaraan dinas
            <?php if (!empty($kendaraan['no_reg'])): ?>
                &middot; <strong>No. Reg:</strong> <?= htmlspecialchars($kendaraan['no_reg']) ?>
            <?php endif; ?>
        </p>
    </div>

    <!-- Pengguna card diatas -->
    <!-- <?php if ($pengguna): ?>
    <div class="card shadow-sm rounded-3 overflow-hidden mt-3">
        <div class="card-body p-4">
            <h5 class="card-title text-primary mb-3"><i class="fas fa-user me-2"></i>Pengguna Kendaraan</h5>
            <div class="d-flex align-items-center gap-3">
                <i class="fas fa-user-circle" style="font-size:40px;color:#6c757d"></i>
                <div>
                    <div class="fw-semibold" style="font-size:1.05rem;"><?= htmlspecialchars($pengguna['nama_lengkap'] ?? '-') ?></div>
                    <div class="text-muted small">
                        <span><?= htmlspecialchars($pengguna['pangkat'] ?? '-') ?></span>
                        <?php if (!empty($pengguna['jabatan'])): ?>
                            <span class="ms-2">• <?= htmlspecialchars($pengguna['jabatan']) ?></span>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
    </div>
    <?php endif; ?> -->

</div>

<div class="row g-4 mb-4">
    <div class="col-lg-8">
        <div class="card shadow-sm rounded-3 overflow-hidden">
            <div class="vehicle-image-detail bg-light d-flex align-items-center justify-content-center" style="min-height:260px;">
                <?php $photo = get_vehicle_photo_web_path((int)$kendaraan['id']); ?>
                <?php if ($photo): ?>
                    <img src="<?= htmlspecialchars($photo) ?>?v=<?= urlencode($kendaraan['updated_at'] ?? $kendaraan['created_at'] ?? time()) ?>" class="w-100 h-100 object-fit-cover" alt="<?= htmlspecialchars($kendaraan['merk'] . ' ' . $kendaraan['tipe']) ?>">
                <?php else: ?>
                    <div class="text-center text-muted">
                        <i class="fas fa-car fa-4x d-block mb-3"></i>
                        <span>Foto tidak tersedia</span>
                    </div>
                <?php endif; ?>
            </div>
            
            <div class="card-body p-4">
                <h5 class="card-title text-primary mb-4">Informasi Kendaraan</h5>
                <div class="row g-4">
                    <div class="col-md-6 d-flex flex-column gap-2">
                        <label class="fw-semibold text-muted small text-uppercase">Nomor Registrasi</label>
                        <span class="text-dark fs-5"><?= htmlspecialchars($kendaraan['no_reg'] ?: '-') ?></span>
                    </div>

                    <div class="col-md-6 d-flex flex-column gap-2">
                        <label class="fw-semibold text-muted small text-uppercase">Merk & Tipe</label>
                        <span class="text-dark fs-5"><?= htmlspecialchars(trim(($kendaraan['merk'] ?? '') . ' ' . ($kendaraan['tipe'] ?? ''))) ?></span>
                    </div>

                    <div class="col-md-6 d-flex flex-column gap-2">
                        <label class="fw-semibold text-muted small text-uppercase">Tahun Pembuatan</label>
                        <span class="text-dark fs-5"><?= htmlspecialchars($kendaraan['tahun_pembuatan'] ?: '-') ?></span>
                    </div>

                    <div class="col-md-6 d-flex flex-column gap-2">
                        <label class="fw-semibold text-muted small text-uppercase">Warna</label>
                        <span class="text-dark"><?= htmlspecialchars($kendaraan['warna'] ?: '-') ?></span>
                    </div>

                    <div class="col-md-6 d-flex flex-column gap-2">
                        <label class="fw-semibold text-muted small text-uppercase">Jenis Kendaraan</label>
                        <span class="text-dark"><?= htmlspecialchars($kendaraan['jenis'] ?: '-') ?></span>
                    </div>

                    <div class="col-md-6 d-flex flex-column gap-2">
                        <label class="fw-semibold text-muted small text-uppercase">Bahan Bakar</label>
                        <span class="text-dark"><?= htmlspecialchars($kendaraan['bahan_bakar'] ?: '-') ?></span>
                    </div>

                    <div class="col-md-6 d-flex flex-column gap-2">
                        <label class="fw-semibold text-muted small text-uppercase">Kondisi</label>
                        <span>
                            <span class="status-badge status-<?= strtolower(str_replace(' ', '-', $kendaraan['kondisi'])) ?>"><?= htmlspecialchars($kendaraan['kondisi']) ?></span>
                        </span>
                    </div>

                    <div class="col-md-6 d-flex flex-column gap-2">
                        <label class="fw-semibold text-muted small text-uppercase">Status Penggunaan</label>
                        <span>
                            <span class="status-badge status-<?= strtolower(str_replace(' ', '-', $kendaraan['status_penggunaan'])) ?>"><?= htmlspecialchars($kendaraan['status_penggunaan']) ?></span>
                        </span>
                    </div>

                    <?php if ($pengguna): ?>
                    <div class="col-12 d-flex flex-column gap-2">
                        <label class="fw-semibold text-muted small text-uppercase">Pengguna Saat Ini</label>
                        <div class="d-flex align-items-center gap-3">
                            <i class="fas fa-user-circle" style="font-size:34px;color:#6c757d"></i>
                            <div>
                                <div class="fw-semibold"><?= htmlspecialchars($pengguna['nama_lengkap'] ?? '-') ?></div>
                                <div class="text-muted small">
                                    <span><?= htmlspecialchars($pengguna['pangkat'] ?? '-') ?></span>
                                    <?php if (!empty($pengguna['jabatan'])): ?>
                                        <span class="ms-2">• <?= htmlspecialchars($pengguna['jabatan']) ?></span>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </div>
                    </div>
                    <?php endif; ?>
                </div>
            </div>
    </div>
</div>

<div class="actions-bar">
    <a href="index.php?page=kendaraan_publik" class="btn btn-secondary">
        <i class="fas fa-arrow-left"></i> Kembali ke Daftar
    </a>
    <a href="index.php" class="btn btn-outline">
        <i class="fas fa-home"></i> Ke Beranda
    </a>
</div>


