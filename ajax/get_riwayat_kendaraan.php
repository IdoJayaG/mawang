<?php
require_once dirname(__DIR__) . '/includes/auth.php';

header('Content-Type: application/json');

if (!is_logged_in()) {
    echo json_encode(['success' => false, 'message' => 'Tidak memiliki akses']);
    exit;
}

$kendaraan_id = (int)($_GET['kendaraan_id'] ?? 0);

if (!$kendaraan_id) {
    echo json_encode(['success' => false, 'message' => 'ID kendaraan tidak valid']);
    exit;
}

try {
    // Get vehicle info
    $stmt = $mysqli->prepare("SELECT * FROM kendaraan WHERE id = ?");
    $stmt->bind_param('i', $kendaraan_id);
    $stmt->execute();
    $vehicle = $stmt->get_result()->fetch_assoc();
    $stmt->close();
    
    if (!$vehicle) {
        echo json_encode(['success' => false, 'message' => 'Kendaraan tidak ditemukan']);
        exit;
    }
    
    // Get maintenance history
    $stmt = $mysqli->prepare("
    SELECT rp.*, u.nama_lengkap as created_by_name
        FROM riwayat_perawatan rp 
        LEFT JOIN pengguna u ON rp.created_by = u.id
        WHERE rp.kendaraan_id = ?
        ORDER BY rp.tanggal_perawatan DESC
    ");
    $stmt->bind_param('i', $kendaraan_id);
    $stmt->execute();
    $maintenances = $stmt->get_result();
    $stmt->close();
    
    ob_start();
?>
<div class="vehicle-summary mb-4">
    <div class="row">
        <div class="col-md-4">
            <div class="card bg-light">
                <div class="card-body text-center">
                    <?php if (!empty($vehicle['foto'])): ?>
                        <img src="assets/images/<?= htmlspecialchars($vehicle['foto']) ?>" 
                             alt="<?= htmlspecialchars($vehicle['no_polisi']) ?>" 
                             class="img-fluid mb-3" style="max-height: 200px; border-radius: 8px;">
                    <?php else: ?>
                        <div class="bg-secondary text-white d-flex align-items-center justify-content-center mb-3" 
                             style="height: 150px; border-radius: 8px;">
                            <i class="fas fa-car fa-3x"></i>
                        </div>
                    <?php endif; ?>
                    <h5 class="text-primary"><?= htmlspecialchars($vehicle['no_polisi']) ?></h5>
                    <p class="mb-1"><?= htmlspecialchars($vehicle['merk'] . ' ' . $vehicle['tipe']) ?></p>
                    <small class="text-muted">Tahun <?= htmlspecialchars($vehicle['tahun_pembuatan']) ?></small>
                </div>
            </div>
        </div>
        <div class="col-md-8">
            <div class="row">
                <div class="col-md-6">
                    <div class="card border-primary mb-3">
                        <div class="card-body text-center">
                            <h4 class="text-primary"><?= $maintenances->num_rows ?></h4>
                            <p class="mb-0">Total Perawatan</p>
                        </div>
                    </div>
                </div>
                <div class="col-md-6">
                    <div class="card border-success mb-3">
                        <div class="card-body text-center">
                            <?php 
                            $total_biaya = 0;
                            $maintenances->data_seek(0);
                            while ($row = $maintenances->fetch_assoc()) {
                                $total_biaya += $row['biaya'];
                            }
                            $maintenances->data_seek(0);
                            ?>
                            <h4 class="text-success">Rp <?= number_format($total_biaya) ?></h4>
                            <p class="mb-0">Total Biaya</p>
                        </div>
                    </div>
                </div>
            </div>
            <div class="card border-info">
                <div class="card-body">
                    <h6 class="card-title"><i class="fas fa-info-circle text-info"></i> Informasi Kendaraan</h6>
                    <div class="row">
                        <div class="col-md-6">
                            <small class="text-muted">No. Rangka:</small><br>
                            <strong><?= htmlspecialchars($vehicle['no_rangka'] ?: '-') ?></strong>
                        </div>
                        <div class="col-md-6">
                            <small class="text-muted">No. Mesin:</small><br>
                            <strong><?= htmlspecialchars($vehicle['no_mesin'] ?: '-') ?></strong>
                        </div>
                    </div>
                    <div class="row mt-2">
                        <div class="col-md-6">
                            <small class="text-muted">Status:</small><br>
                            <span class="badge badge-<?= $vehicle['status_kendaraan'] == 'Operasional' ? 'success' : 'warning' ?>">
                                <?= htmlspecialchars($vehicle['status_kendaraan']) ?>
                            </span>
                        </div>
                        <div class="col-md-6">
                            <small class="text-muted">Kondisi:</small><br>
                            <span class="badge badge-<?= $vehicle['kondisi'] == 'Baik' ? 'success' : 'warning' ?>">
                                <?= htmlspecialchars($vehicle['kondisi']) ?>
                            </span>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<div class="maintenance-history">
    <h5 class="mb-3"><i class="fas fa-wrench text-primary"></i> Riwayat Perawatan Lengkap</h5>
    
    <?php if ($maintenances->num_rows > 0): ?>
        <div class="table-responsive">
            <table class="table table-bordered table-hover">
                <thead class="bg-primary text-white">
                    <tr>
                        <th width="12%">Tanggal</th>
                        <th width="18%">Jenis Perawatan</th>
                        <th width="15%">Bengkel</th>
                        <th width="15%">Mekanik</th>
                        <th width="12%">KM</th>
                        <th width="15%">Biaya</th>
                        <th width="8%">Status</th>
                        <th width="5%">Detail</th>
                    </tr>
                </thead>
                <tbody>
                    <?php while ($maintenance = $maintenances->fetch_assoc()): ?>
                    <tr>
                        <td>
                            <strong><?= date('d/m/Y', strtotime($maintenance['tanggal_perawatan'])) ?></strong><br>
                            <small class="text-muted"><?= date('H:i', strtotime($maintenance['created_at'])) ?></small>
                        </td>
                        <td>
                            <strong><?= htmlspecialchars($maintenance['jenis_perawatan']) ?></strong>
                            <?php if ($maintenance['kategori']): ?>
                                <br><span class="badge badge-sm badge-info"><?= htmlspecialchars($maintenance['kategori']) ?></span>
                            <?php endif; ?>
                        </td>
                        <td>
                            <?= htmlspecialchars($maintenance['bengkel'] ?: '-') ?>
                        </td>
                        <td>
                            <?= htmlspecialchars($maintenance['mekanik'] ?: '-') ?>
                        </td>
                        <td>
                            <?= $maintenance['km_saat_perawatan'] ? number_format($maintenance['km_saat_perawatan']) . ' KM' : '-' ?>
                        </td>
                        <td>
                            <?php if ($maintenance['biaya'] > 0): ?>
                                <strong class="text-success">Rp <?= number_format($maintenance['biaya']) ?></strong>
                            <?php else: ?>
                                <span class="text-muted">-</span>
                            <?php endif; ?>
                        </td>
                        <td>
                            <span class="badge badge-success badge-sm">
                                <?= htmlspecialchars($maintenance['status']) ?>
                            </span>
                        </td>
                        <td>
                            <button class="btn btn-sm btn-outline-info btn-maintenance-detail" 
                                    data-id="<?= $maintenance['id'] ?>" 
                                    title="Detail Perawatan">
                                <i class="fas fa-eye"></i>
                            </button>
                        </td>
                    </tr>
                    <?php if (!empty($maintenance['keterangan'])): ?>
                    <tr class="table-light">
                        <td colspan="8">
                            <small><strong>Keterangan:</strong> <?= nl2br(htmlspecialchars($maintenance['keterangan'])) ?></small>
                        </td>
                    </tr>
                    <?php endif; ?>
                    <?php if (!empty($maintenance['sparepart_diganti'])): ?>
                    <tr class="table-light">
                        <td colspan="8">
                            <small><strong>Sparepart:</strong> <?= nl2br(htmlspecialchars($maintenance['sparepart_diganti'])) ?></small>
                        </td>
                    </tr>
                    <?php endif; ?>
                    <?php endwhile; ?>
                </tbody>
            </table>
        </div>
    <?php else: ?>
        <div class="alert alert-info">
            <i class="fas fa-info-circle"></i> Belum ada riwayat perawatan untuk kendaraan ini.
        </div>
    <?php endif; ?>
</div>

<?php
    $html = ob_get_clean();
    echo json_encode(['success' => true, 'html' => $html]);
    
} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => 'Error: ' . $e->getMessage()]);
}
?>
