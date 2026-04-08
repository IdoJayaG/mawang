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
    $stmt = $mysqli->prepare("SELECT no_polisi, merk, tipe FROM kendaraan WHERE id = ?");
    $stmt->bind_param('i', $kendaraan_id);
    $stmt->execute();
    $vehicle = $stmt->get_result()->fetch_assoc();
    $stmt->close();
    
    if (!$vehicle) {
        echo json_encode(['success' => false, 'message' => 'Kendaraan tidak ditemukan']);
        exit;
    }
    
    // Get combined timeline (maintenance + usage + fuel)
    $stmt = $mysqli->prepare("
        SELECT 'maintenance' as type, tanggal_perawatan as date, jenis_perawatan as title, 
               CONCAT('Bengkel: ', COALESCE(bengkel, '-'), ' | Mekanik: ', COALESCE(mekanik, '-')) as description,
               biaya, km_saat_perawatan as km_value, 'success' as status
        FROM riwayat_perawatan 
        WHERE kendaraan_id = ?
        
        UNION ALL
        
        SELECT 'usage' as type, tanggal as date, CONCAT('Pemakaian: ', tujuan) as title,
               CONCAT('Driver: ', COALESCE(driver, '-'), ' | KM: ', COALESCE(km_akhir - km_awal, 0)) as description,
               0 as biaya, km_akhir as km_value, 'primary' as status
        FROM riwayat_pemakaian 
        WHERE kendaraan_id = ?
        
        UNION ALL
        
        SELECT 'fuel' as type, tanggal_isi as date, CONCAT('Pengisian BBM: ', jumlah_liter, ' liter') as title,
               CONCAT('Jenis: ', jenis_bbm, ' | Harga: Rp ', FORMAT(harga_per_liter, 0)) as description,
               total_biaya as biaya, km_saat_isi as km_value, 'warning' as status
        FROM log_bahan_bakar 
        WHERE kendaraan_id = ?
        
        ORDER BY date DESC
        LIMIT 50
    ");
    $stmt->bind_param('iii', $kendaraan_id, $kendaraan_id, $kendaraan_id);
    $stmt->execute();
    $timeline_items = $stmt->get_result();
    $stmt->close();
    
    ob_start();
?>
<div class="timeline-container">
    <div class="vehicle-header mb-4">
        <h5 class="text-center">
            <i class="fas fa-car text-primary"></i>
            <?= htmlspecialchars($vehicle['no_polisi']) ?> - <?= htmlspecialchars($vehicle['merk'] . ' ' . $vehicle['tipe']) ?>
        </h5>
    </div>
    
    <?php if ($timeline_items->num_rows > 0): ?>
        <div class="timeline">
            <?php while ($item = $timeline_items->fetch_assoc()): ?>
                <div class="timeline-item <?= $item['status'] ?>">
                    <div class="timeline-content">
                        <div class="timeline-header d-flex justify-content-between align-items-start">
                            <div>
                                <h6 class="mb-1">
                                    <?php if ($item['type'] == 'maintenance'): ?>
                                        <i class="fas fa-wrench text-success"></i>
                                    <?php elseif ($item['type'] == 'usage'): ?>
                                        <i class="fas fa-route text-primary"></i>
                                    <?php elseif ($item['type'] == 'fuel'): ?>
                                        <i class="fas fa-gas-pump text-warning"></i>
                                    <?php endif; ?>
                                    <?= htmlspecialchars($item['title']) ?>
                                </h6>
                                <small class="text-muted">
                                    <i class="fas fa-calendar"></i> 
                                    <?= date('d F Y', strtotime($item['date'])) ?>
                                </small>
                            </div>
                            <div class="text-right">
                                <?php if ($item['biaya'] > 0): ?>
                                    <span class="badge badge-<?= $item['status'] ?>">
                                        Rp <?= number_format($item['biaya']) ?>
                                    </span>
                                <?php endif; ?>
                                <?php if ($item['km_value']): ?>
                                    <br><small class="text-muted">
                                        <?= number_format($item['km_value']) ?> KM
                                    </small>
                                <?php endif; ?>
                            </div>
                        </div>
                        <p class="mb-0 mt-2 small text-muted">
                            <?= htmlspecialchars($item['description']) ?>
                        </p>
                    </div>
                </div>
            <?php endwhile; ?>
        </div>
        
        <div class="text-center mt-4">
            <p class="text-muted">
                <i class="fas fa-info-circle"></i> 
                Menampilkan 50 aktivitas terakhir
            </p>
        </div>
    <?php else: ?>
        <div class="text-center py-5">
            <i class="fas fa-clock fa-3x text-muted mb-3"></i>
            <h5 class="text-muted">Belum ada aktivitas</h5>
            <p class="text-muted">Timeline akan muncul setelah ada aktivitas perawatan, pemakaian, atau pengisian BBM</p>
        </div>
    <?php endif; ?>
</div>

<style>
.timeline {
    position: relative;
    padding: 0;
    list-style: none;
}

.timeline-item {
    border-left: 3px solid #007bff;
    padding-left: 20px;
    margin-bottom: 25px;
    position: relative;
    background: #f8f9fa;
    border-radius: 8px;
    padding: 15px 15px 15px 25px;
}

.timeline-item:before {
    content: '';
    position: absolute;
    left: -8px;
    top: 15px;
    width: 12px;
    height: 12px;
    border-radius: 50%;
    background-color: #007bff;
    border: 2px solid #fff;
    box-shadow: 0 0 0 2px #007bff;
}

.timeline-item.success {
    border-left-color: #28a745;
    background: #f8fff9;
}

.timeline-item.success:before {
    background-color: #28a745;
    box-shadow: 0 0 0 2px #28a745;
}

.timeline-item.warning {
    border-left-color: #ffc107;
    background: #fffdf5;
}

.timeline-item.warning:before {
    background-color: #ffc107;
    box-shadow: 0 0 0 2px #ffc107;
}

.timeline-item.primary {
    border-left-color: #007bff;
    background: #f8f9ff;
}

.timeline-item.primary:before {
    background-color: #007bff;
    box-shadow: 0 0 0 2px #007bff;
}

.timeline-content {
    padding: 0;
}

.timeline-header h6 {
    color: #495057;
    font-weight: 600;
}

.vehicle-header {
    background: linear-gradient(135deg, #007bff, #6610f2);
    color: white;
    padding: 15px;
    border-radius: 8px;
    margin-bottom: 20px;
}
</style>

<?php
    $html = ob_get_clean();
    echo json_encode(['success' => true, 'html' => $html]);
    
} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => 'Error: ' . $e->getMessage()]);
}
?>
