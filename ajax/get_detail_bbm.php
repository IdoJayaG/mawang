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
    $kendaraan = $stmt->get_result()->fetch_assoc();
    $stmt->close();
    
    if (!$kendaraan) {
        echo json_encode(['success' => false, 'message' => 'Kendaraan tidak ditemukan']);
        exit;
    }
    
    // Get BBM logs for this vehicle
    $stmt = $mysqli->prepare("
    SELECT lb.*, COALESCE(u.nama_lengkap, '') as user_nama
        FROM log_bahan_bakar lb
        LEFT JOIN pengguna u ON lb.user_id = u.id
        WHERE lb.kendaraan_id = ?
        ORDER BY lb.tanggal_isi DESC
    ");
    $stmt->bind_param('i', $kendaraan_id);
    $stmt->execute();
    $bbm_logs = $stmt->get_result();
    $stmt->close();
    
    // Calculate statistics (harga/biaya dihapus)
    $total_pengisian = $bbm_logs->num_rows;
    $total_liter = 0;
    $logs_array = [];

    while ($log = $bbm_logs->fetch_assoc()) {
        $total_liter += $log['jumlah_liter'];
        $logs_array[] = $log;
    }

    $rata_konsumsi = $total_pengisian > 0 ? $total_liter / $total_pengisian : 0;
    
    ob_start();
?>
<div class="vehicle-detail-container">
    <!-- Vehicle Summary Card -->
    <div class="vehicle-summary-card mb-4">
        <div class="row">
            <div class="col-md-3">
                <?php if ($kendaraan['foto']): ?>
                    <img src="assets/images/<?= htmlspecialchars($kendaraan['foto']) ?>" 
                         alt="<?= htmlspecialchars($kendaraan['no_polisi']) ?>" 
                         class="img-fluid rounded">
                <?php else: ?>
                    <div class="vehicle-placeholder bg-light d-flex align-items-center justify-content-center rounded" 
                         style="height: 150px;">
                        <i class="fas fa-car fa-3x text-muted"></i>
                    </div>
                <?php endif; ?>
            </div>
            <div class="col-md-9">
                <h4 class="text-primary"><?= htmlspecialchars($kendaraan['no_polisi']) ?></h4>
                <p class="text-muted mb-3"><?= htmlspecialchars($kendaraan['merk'] . ' ' . $kendaraan['tipe'] . ' - ' . $kendaraan['tahun_pembuatan']) ?></p>

                <div class="vehicle-meta mb-3">
                    <table class="table table-sm mb-0">
                        <tr>
                            <th style="width:160px">No. Rangka</th>
                            <td><?= htmlspecialchars($kendaraan['no_rangka'] ?: '-') ?></td>
                            <th style="width:160px">No. Mesin</th>
                            <td><?= htmlspecialchars($kendaraan['no_mesin'] ?: '-') ?></td>
                        </tr>
                        <tr>
                            <th>Bahan Bakar</th>
                            <td><?= htmlspecialchars($kendaraan['bahan_bakar'] ?: '-') ?></td>
                            <th>Odometer</th>
                            <td><?= $kendaraan['odometer'] !== null ? number_format($kendaraan['odometer']) : '-' ?></td>
                        </tr>
                        <tr>
                            <th>No. STNK</th>
                            <td><?= htmlspecialchars($kendaraan['no_stnk'] ?: '-') ?></td>
                            <th>Pemilik STNK</th>
                            <td><?= htmlspecialchars($kendaraan['pemilik_stnk'] ?: '-') ?></td>
                        </tr>
                    </table>
                </div>

                <div class="row">
                    <div class="col-md-3">
                        <div class="stat-item">
                            <h5 class="text-primary mb-1"><?= $total_pengisian ?></h5>
                            <small class="text-muted">Total Pengisian</small>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="stat-item">
                            <h5 class="text-success mb-1"><?= number_format($total_liter, 2) ?> L</h5>
                            <small class="text-muted">Total Liter</small>
                        </div>
                    </div>
                    <!-- Total biaya/harga dihapus dari tampilan -->
                    <div class="col-md-3">
                        <div class="stat-item">
                            <h5 class="text-info mb-1"><?= number_format($rata_konsumsi, 2) ?> L</h5>
                            <small class="text-muted">Rata-rata per Isi</small>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <!-- BBM Logs Table -->
    <div class="bbm-logs-table">
        <h5 class="mb-3">
            <i class="fas fa-list"></i> Riwayat Pengisian BBM
            <span class="badge badge-primary ml-2"><?= $total_pengisian ?> record</span>
        </h5>
        
        <?php if (!empty($logs_array)): ?>
            <div class="table-responsive">
                <table class="table table-striped table-sm">
                    <thead class="bg-light">
                        <tr>
                            <th>Tanggal</th>
                            <th>Jumlah (L)</th>
                            <th>KM</th>
                            <th>SPBU</th>
                            <th>Jenis BBM</th>
                            <th>User</th>
                            <th>Keterangan</th>
                            <th>Foto</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($logs_array as $idx => $log): ?>
                        <tr>
                            <td>
                                <strong><?= date('d/m/Y', strtotime($log['tanggal_isi'])) ?></strong><br>
                                <small class="text-muted"><?= date('H:i', strtotime($log['tanggal_isi'])) ?></small>
                            </td>
                            <td><strong class="text-primary"><?= number_format($log['jumlah_liter'], 2) ?></strong></td>
                            <td>
                                <?= $log['km_saat_isi'] ? number_format($log['km_saat_isi']) : '<span class="text-muted">-</span>' ?>
                            </td>
                            <td><?= $log['spbu'] ? htmlspecialchars($log['spbu']) : '<span class="text-muted">-</span>' ?></td>
                            <td><span class="badge badge-info"><?= htmlspecialchars($log['jenis_bbm']) ?></span></td>
                            <td><?= $log['user_nama'] ? htmlspecialchars($log['user_nama']) : '<span class="text-muted">-</span>' ?></td>
                            <td><?= $log['keterangan'] ? htmlspecialchars($log['keterangan']) : '<span class="text-muted">-</span>' ?></td>
                            <td>
                                <?php
                                $photo_count = 0;
                                $photoHtml = '';
                                if ($log['foto_sebelum_isi']) {
                                    $photo_count++;
                                    $src = 'uploads/bbm/' . htmlspecialchars($log['foto_sebelum_isi']);
                                    $photoHtml .= '<img src="' . $src . '" alt="sebelum" class="detail-thumb" onclick="showPhotoModal(\'' . $src . '\', \'' . addslashes('Foto Sebelum - ' . $kendaraan['no_polisi']) . '\')" /> ';
                                }
                                if ($log['foto_sesudah_isi']) {
                                    $photo_count++;
                                    $src = 'uploads/bbm/' . htmlspecialchars($log['foto_sesudah_isi']);
                                    $photoHtml .= '<img src="' . $src . '" alt="sesudah" class="detail-thumb" onclick="showPhotoModal(\'' . $src . '\', \'' . addslashes('Foto Sesudah - ' . $kendaraan['no_polisi']) . '\')" /> ';
                                }
                                if ($log['foto_odometer']) {
                                    $photo_count++;
                                    $src = 'uploads/bbm/' . htmlspecialchars($log['foto_odometer']);
                                    $photoHtml .= '<img src="' . $src . '" alt="odometer" class="detail-thumb" onclick="showPhotoModal(\'' . $src . '\', \'' . addslashes('Foto Odometer - ' . $kendaraan['no_polisi']) . '\')" /> ';
                                }
                                if ($photo_count > 0) {
                                    echo $photoHtml;
                                } else {
                                    echo '<span class="text-muted">-</span>';
                                }
                                ?>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php else: ?>
            <div class="text-center py-4">
                <i class="fas fa-gas-pump fa-3x text-muted mb-3"></i>
                <h5 class="text-muted">Belum ada log BBM</h5>
                <p class="text-muted">Kendaraan ini belum memiliki riwayat pengisian bahan bakar</p>
            </div>
        <?php endif; ?>
    </div>
</div>

<style>
.vehicle-summary-card {
    background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
    color: white;
    padding: 20px;
    border-radius: 10px;
}

.vehicle-summary-card h4 {
    color: white !important;
}

.stat-item h5 {
    color: white !important;
}

.stat-item small {
    color: rgba(255,255,255,0.8) !important;
}

.bbm-logs-table {
    background: white;
    border-radius: 8px;
    padding: 20px;
}

.table th {
    border-top: none;
    font-weight: 600;
    color: #495057;
}

.badge {
    font-size: 0.75rem;
}

.detail-thumb {
    width:48px;
    height:48px;
    object-fit:cover;
    border-radius:6px;
    margin-right:6px;
    cursor:pointer;
    border:1px solid #e9ecef;
}
</style>

<?php
    $html = ob_get_clean();
    echo json_encode(['success' => true, 'html' => $html]);
    
} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => 'Error: ' . $e->getMessage()]);
}
?>
