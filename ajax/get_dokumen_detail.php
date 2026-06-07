<?php
require_once '../includes/auth.php';
require_once '../config/db.php';

header('Content-Type: application/json');

if (!isset($_GET['id']) || !is_numeric($_GET['id'])) {
    echo json_encode(['success' => false, 'message' => 'ID tidak valid']);
    exit;
}

$id = (int)$_GET['id'];

// Get document data
$stmt = $mysqli->prepare("SELECT dk.*, k.no_polisi, k.merk, k.tipe FROM dokumen_kendaraan dk LEFT JOIN kendaraan k ON dk.kendaraan_id = k.id WHERE dk.id = ?");
$stmt->bind_param('i', $id);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows === 0) {
    echo json_encode(['success' => false, 'message' => 'Dokumen tidak ditemukan']);
    exit;
}

$doc = $result->fetch_assoc();
$stmt->close();

// Get vehicles for dropdown
$vehicles = $mysqli->query("SELECT id, no_polisi, merk, tipe FROM kendaraan ORDER BY no_polisi")->fetch_all(MYSQLI_ASSOC);

// Get document types
$jenis_dokumen = ['Bukti Nomor Kendaraan Bermotor', 'Lainnya'];

// Generate form HTML
ob_start();
?>
<div class="row">
    <div class="col-md-6 mb-3">
        <label class="form-label">Kendaraan <span class="text-danger">*</span></label>
        <select name="kendaraan_id" class="form-control" required>
            <option value="">Pilih Kendaraan</option>
            <?php foreach ($vehicles as $vehicle): ?>
                <option value="<?= $vehicle['id'] ?>" <?= $doc['kendaraan_id'] == $vehicle['id'] ? 'selected' : '' ?>>
                    <?= htmlspecialchars($vehicle['no_polisi'] . ' - ' . $vehicle['merk'] . ' ' . $vehicle['tipe']) ?>
                </option>
            <?php endforeach; ?>
        </select>
    </div>
    
    <div class="col-md-6 mb-3">
        <label class="form-label">Jenis Dokumen <span class="text-danger">*</span></label>
        <select name="jenis_dokumen" class="form-control" required>
            <option value="">Pilih Jenis</option>
            <?php foreach ($jenis_dokumen as $jenis): ?>
                <option value="<?= $jenis ?>" <?= $doc['jenis_dokumen'] === $jenis ? 'selected' : '' ?>><?= $jenis ?></option>
            <?php endforeach; ?>
        </select>
    </div>
    
    <div class="col-md-6 mb-3">
        <label class="form-label">Nomor Dokumen <span class="text-danger">*</span></label>
        <input type="text" name="nomor_dokumen" class="form-control" value="<?= htmlspecialchars($doc['nomor_dokumen']) ?>" required>
    </div>
    
    <div class="col-md-6 mb-3">
        <label class="form-label">Instansi Penerbit</label>
        <input type="text" name="instansi_penerbit" class="form-control" value="<?= htmlspecialchars($doc['instansi_penerbit']) ?>">
    </div>
    
    <div class="col-md-6 mb-3">
        <label class="form-label">Tanggal Terbit <span class="text-danger">*</span></label>
        <input type="date" name="tanggal_terbit" class="form-control" value="<?= $doc['tanggal_terbit'] ?>" required>
    </div>
    
    <div class="col-md-6 mb-3">
        <label class="form-label">Tanggal Berlaku <span class="text-danger">*</span></label>
        <input type="date" name="tanggal_berlaku" class="form-control" value="<?= $doc['tanggal_berlaku'] ?>" required>
    </div>
    
    <div class="col-md-6 mb-3">
        <label class="form-label">Status <span class="text-danger">*</span></label>
        <select name="status" class="form-control" required>
            <option value="">Pilih Status</option>
            <option value="Aktif" <?= $doc['status'] === 'Aktif' ? 'selected' : '' ?>>Aktif</option>
            <option value="Kadaluarsa" <?= $doc['status'] === 'Kadaluarsa' ? 'selected' : '' ?>>Kadaluarsa</option>
            <option value="Dalam Proses" <?= $doc['status'] === 'Dalam Proses' ? 'selected' : '' ?>>Dalam Proses</option>
        </select>
    </div>
    
    <div class="col-md-6 mb-3">
        <label class="form-label">Upload File Baru</label>
        <?php if ($doc['file_dokumen']): ?>
            <div class="mb-2">
                <small class="text-muted">File saat ini: 
                    <a href="uploads/dokumen/<?= htmlspecialchars($doc['file_dokumen']) ?>" target="_blank">
                        <?= htmlspecialchars($doc['file_dokumen']) ?>
                    </a>
                </small>
            </div>
        <?php endif; ?>
        <input type="file" name="file_dokumen" class="form-control" accept=".jpg,.jpeg,.png,.pdf">
        <small class="text-muted">Format: JPG, PNG, PDF. Maksimal 10MB. Kosongkan jika tidak ingin mengubah file.</small>
    </div>
    
    <div class="col-12 mb-3">
        <label class="form-label">Keterangan</label>
        <textarea name="keterangan" class="form-control" rows="3"><?= htmlspecialchars($doc['keterangan']) ?></textarea>
    </div>
</div>
<?php
$html = ob_get_clean();

echo json_encode([
    'success' => true,
    'html' => $html
]);
?>
