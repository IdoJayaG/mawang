<?php
require_once 'includes/auth.php';
require_login();

$current_role = get_current_role();
if (!is_admin_like() && $current_role !== 'driver') {
    header('Location: pages/403.php');
    exit;
}

$doc_id = (int)($_GET['id'] ?? 0);
if (!$doc_id) {
    header('Location: index.php?page=dokumen_kendaraan');
    exit;
}

// fetch document
$stmt = $mysqli->prepare("SELECT dk.*, k.no_polisi, k.no_reg, k.merk, k.tipe FROM dokumen_kendaraan dk JOIN kendaraan k ON dk.kendaraan_id = k.id WHERE dk.id = ?");
$stmt->bind_param('i', $doc_id);
$stmt->execute();
$doc = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$doc) {
    $_SESSION['flash'] = 'Dokumen tidak ditemukan.';
    header('Location: index.php?page=dokumen_kendaraan');
    exit;
}

if ($current_role === 'driver' && !can_access_vehicle((int)$doc['kendaraan_id'])) {
    header('Location: index.php?page=403');
    exit;
}

// kendaraan list for display (optional)
$kendaraan = $mysqli->query("SELECT id, no_polisi, no_reg, merk, tipe FROM kendaraan WHERE id = " . (int)$doc['kendaraan_id'])->fetch_assoc();

?>
<div class="page-header gradient-header text-white p-4 mb-4 rounded">
    <div class="d-flex justify-content-between align-items-center">
        <div>
            <h1 class="mb-2"><i class="fas fa-edit me-2"></i>Edit Dokumen Kendaraan</h1>
            <p class="mb-0">Edit file dan metadata dokumen kendaraan</p>
        </div>
        <div>
            <a href="?page=dokumen_kendaraan" class="btn btn-light">Kembali ke Daftar</a>
        </div>
    </div>
</div>

<div class="container-fluid">
    <div class="card">
        <div class="card-body">
            <form method="POST" action="?page=dokumen_kendaraan&action=edit" enctype="multipart/form-data">
                <input type="hidden" name="csrf_token" value="<?= generate_csrf_token() ?>">
                <input type="hidden" name="id" value="<?= $doc['id'] ?>">

                <div class="row g-3">
                    <div class="col-md-6">
                        <label class="form-label">Kendaraan</label>
                        <input type="text" class="form-control" value="<?= htmlspecialchars((($kendaraan['no_reg'] ?? '') !== '' ? $kendaraan['no_reg'] : ($kendaraan['no_polisi'] ?? '-')) . ' - ' . $kendaraan['merk'] . ' ' . $kendaraan['tipe']) ?>" disabled>
                    </div>

                    <div class="col-md-6">
                        <label class="form-label">Jenis Dokumen</label>
                        <input type="text" name="jenis_dokumen" class="form-control" value="<?= htmlspecialchars($doc['jenis_dokumen']) ?>" required>
                    </div>

                    <div class="col-md-6">
                        <label class="form-label">Nomor Dokumen</label>
                        <input type="text" name="nomor_dokumen" class="form-control" value="<?= htmlspecialchars($doc['nomor_dokumen']) ?>" required>
                    </div>

                    <div class="col-md-6">
                        <label class="form-label">Instansi Penerbit</label>
                        <input type="text" name="instansi_penerbit" class="form-control" value="<?= htmlspecialchars($doc['instansi_penerbit']) ?>">
                    </div>

                    <div class="col-md-6">
                        <label class="form-label">Tanggal Terbit</label>
                        <input type="date" name="tanggal_terbit" class="form-control" value="<?= htmlspecialchars($doc['tanggal_terbit']) ?>">
                    </div>

                    <div class="col-md-6">
                        <label class="form-label">Tanggal Berlaku</label>
                        <input type="date" name="tanggal_berlaku" class="form-control" value="<?= htmlspecialchars($doc['tanggal_berlaku']) ?>">
                    </div>

                    <div class="col-md-6">
                        <label class="form-label">Status</label>
                        <select name="status" class="form-control">
                            <option value="Aktif" <?= $doc['status'] === 'Aktif' ? 'selected' : '' ?>>Aktif</option>
                            <option value="Kadaluarsa" <?= $doc['status'] === 'Kadaluarsa' ? 'selected' : '' ?>>Kadaluarsa</option>
                            <option value="Dalam Proses" <?= $doc['status'] === 'Dalam Proses' ? 'selected' : '' ?>>Dalam Proses</option>
                        </select>
                    </div>

                    <div class="col-md-6">
                        <label class="form-label">File Dokumen (biarkan kosong untuk mempertahankan file saat ini)</label>
                        <input type="file" name="file_dokumen" class="form-control" accept=".jpg,.jpeg,.png,.pdf">
                        <?php if (!empty($doc['file_dokumen'])): ?>
                            <small class="text-muted">File saat ini: <a href="uploads/dokumen/<?= htmlspecialchars($doc['file_dokumen']) ?>" target="_blank"><?= htmlspecialchars($doc['file_dokumen']) ?></a></small>
                        <?php endif; ?>
                    </div>

                    <div class="col-12">
                        <label class="form-label">Keterangan</label>
                        <textarea name="keterangan" class="form-control" rows="4"><?= htmlspecialchars($doc['keterangan']) ?></textarea>
                    </div>
                </div>

                <div class="mt-3">
                    <a href="?page=dokumen_kendaraan" class="btn btn-secondary">Batal</a>
                    <button type="submit" class="btn btn-primary">Simpan Perubahan</button>
                </div>
            </form>
        </div>
    </div>
</div>
