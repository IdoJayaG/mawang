<?php
require_once dirname(__DIR__) . '/includes/auth.php';

header('Content-Type: application/json');

if (!is_logged_in()) {
    echo json_encode(['success' => false, 'message' => 'Tidak memiliki akses']);
    exit;
}

$id = (int)($_GET['id'] ?? 0);

if (!$id) {
    echo json_encode(['success' => false, 'message' => 'ID tidak valid']);
    exit;
}

try {
    $stmt = $mysqli->prepare("
     SELECT rp.*, k.no_polisi, k.merk, k.tipe, k.tahun_pembuatan,
         u.nama_lengkap as created_by_name
     FROM riwayat_perawatan rp 
     LEFT JOIN kendaraan k ON rp.kendaraan_id = k.id 
     LEFT JOIN pengguna u ON rp.created_by = u.id
        WHERE rp.id = ?
    ");
    $stmt->bind_param('i', $id);
    $stmt->execute();
    $result = $stmt->get_result();
    $data = $result->fetch_assoc();
    $stmt->close();
    
    if (!$data) {
        echo json_encode(['success' => false, 'message' => 'Data tidak ditemukan']);
        exit;
    }
    
    ob_start();
?>
<div class="detail-content">
    <div class="row">
        <div class="col-md-6">
            <h6 class="text-muted mb-3">Informasi Kendaraan</h6>
            <div class="detail-group">
                <label>No. Polisi:</label>
                <div class="detail-value"><?= htmlspecialchars($data['no_polisi']) ?></div>
            </div>
            <div class="detail-group">
                <label>Kendaraan:</label>
                <div class="detail-value"><?= htmlspecialchars($data['merk'] . ' ' . $data['tipe'] . ' (' . $data['tahun_pembuatan'] . ')') ?></div>
            </div>
        </div>
        <div class="col-md-6">
            <h6 class="text-muted mb-3">Informasi Perawatan</h6>
            <div class="detail-group">
                <label>Tanggal Perawatan:</label>
                <div class="detail-value"><?= date('d F Y', strtotime($data['tanggal_perawatan'])) ?></div>
            </div>
            <div class="detail-group">
                <label>Jenis Perawatan:</label>
                <div class="detail-value"><?= htmlspecialchars($data['jenis_perawatan']) ?></div>
            </div>
        </div>
    </div>
    
    <?php if (!empty($data['deskripsi'])): ?>
    <div class="detail-group">
        <label>Deskripsi Perawatan:</label>
        <div class="detail-value"><?= nl2br(htmlspecialchars($data['deskripsi'])) ?></div>
    </div>
    <?php endif; ?>
    
    <div class="row">
        <div class="col-md-4">
            <div class="detail-group">
                <label>Biaya:</label>
                <div class="detail-value">
                    <?php if ($data['biaya'] > 0): ?>
                        <strong class="text-success">Rp <?= number_format($data['biaya']) ?></strong>
                    <?php else: ?>
                        <span class="text-muted">Tidak ada biaya</span>
                    <?php endif; ?>
                </div>
            </div>
        </div>
        <div class="col-md-4">
            <div class="detail-group">
                <label>KM Perawatan:</label>
                <div class="detail-value">
                    <?= $data['km_saat_perawatan'] ? number_format($data['km_saat_perawatan']) . ' KM' : '<span class="text-muted">Tidak dicatat</span>' ?>
                </div>
            </div>
        </div>
        <div class="col-md-4">
            <div class="detail-group">
                <label>Status:</label>
                <div class="detail-value">
                    <span class="badge badge-success">
                        <i class="fas fa-check-circle"></i> <?= htmlspecialchars($data['status']) ?>
                    </span>
                </div>
            </div>
        </div>
    </div>
    
    <?php if (!empty($data['keterangan'])): ?>
    <div class="detail-group">
        <label>Keterangan:</label>
        <div class="detail-value"><?= nl2br(htmlspecialchars($data['keterangan'])) ?></div>
    </div>
    <?php endif; ?>
    
    <div class="row">
        <div class="col-md-6">
            <div class="detail-group">
                <label>Bengkel:</label>
                <div class="detail-value"><?= htmlspecialchars($data['bengkel'] ?: 'Tidak dicatat') ?></div>
            </div>
        </div>
        <div class="col-md-6">
            <div class="detail-group">
                <label>Mekanik:</label>
                <div class="detail-value"><?= htmlspecialchars($data['mekanik'] ?: 'Tidak dicatat') ?></div>
            </div>
        </div>
    </div>
    
    <?php if (!empty($data['sparepart_diganti'])): ?>
    <div class="detail-group">
        <label>Sparepart yang Diganti:</label>
        <div class="detail-value"><?= nl2br(htmlspecialchars($data['sparepart_diganti'])) ?></div>
    </div>
    <?php endif; ?>
    
    <div class="row">
        <div class="col-md-4">
            <div class="detail-group">
                <label>Biaya:</label>
                <div class="detail-value">
                    <?php if ($data['biaya'] > 0): ?>
                        <strong class="text-success">Rp <?= number_format($data['biaya']) ?></strong>
                    <?php else: ?>
                        <span class="text-muted">Tidak ada biaya</span>
                    <?php endif; ?>
                </div>
            </div>
        </div>
        <div class="col-md-4">
            <div class="detail-group">
                <label>KM Perawatan:</label>
                <div class="detail-value">
                    <?= $data['km_saat_perawatan'] ? number_format($data['km_saat_perawatan']) . ' KM' : '<span class="text-muted">Tidak dicatat</span>' ?>
                </div>
            </div>
        </div>
        <div class="col-md-4">
            <div class="detail-group">
                <label>Kategori:</label>
                <div class="detail-value">
                    <span class="badge badge-info"><?= htmlspecialchars($data['kategori']) ?></span>
                </div>
            </div>
        </div>
    </div>
    
    <div class="detail-group">
        <label>Dikerjakan Oleh:</label>
        <div class="detail-value">
            <?php if ($data['created_by_name']): ?>
                <?= htmlspecialchars($data['created_by_name']) ?>
            <?php else: ?>
                <span class="text-muted">System</span>
            <?php endif; ?>
        </div>
    </div>
    
    <div class="detail-group">
        <label>Tanggal Input:</label>
        <div class="detail-value"><?= date('d F Y H:i', strtotime($data['created_at'])) ?></div>
    </div>
</div>

<style>
.detail-group {
    margin-bottom: 1rem;
}

.detail-group label {
    display: block;
    font-weight: 600;
    color: #495057;
    margin-bottom: 0.25rem;
    font-size: 0.9rem;
}

.detail-value {
    background: #f8f9fa;
    border: 1px solid #e9ecef;
    border-radius: 4px;
    padding: 0.5rem;
    font-size: 0.9rem;
    color: #495057;
}

.badge {
    font-size: 0.85em;
    padding: 0.5em 0.8em;
}

.badge i {
    margin-right: 0.3em;
}
</style>

<?php
    $html = ob_get_clean();
    echo json_encode(['success' => true, 'html' => $html]);
    
} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => 'Error: ' . $e->getMessage()]);
}
?>
