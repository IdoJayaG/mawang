<?php
require_once 'includes/auth.php';
require_login();
require_role(['user','driver']);

$user_id = get_current_user_id();
$kendaraan_id = isset($_GET['kendaraan_id']) ? intval($_GET['kendaraan_id']) : 0;

// Use central table helpers
require_once __DIR__ . '/../lib/table_helpers.php';

// Filter berdasarkan kendaraan tertentu atau semua kendaraan user
if ($kendaraan_id > 0) {
    // Ambil info kendaraan (jangan paksa tergantung pada pengguna_kendaraan)
    $stmt = $mysqli->prepare(
        "SELECT k.merk, k.tipe, k.no_reg FROM kendaraan k WHERE k.id = ?"
    );
    $stmt->bind_param('i', $kendaraan_id);
    $stmt->execute();
    $kendaraan_info = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    if (!$kendaraan_info) {
        // Kendaraan tidak ditemukan, kembali ke daftar
        header('Location: index.php?page=riwayat_kendaraan');
        exit;
    }
}

// Pagination
$page = isset($_GET['page_num']) ? max(1, intval($_GET['page_num'])) : 1;
$limit = 10;
$offset = ($page - 1) * $limit;

// Query untuk riwayat pemakaian
// If a specific vehicle is requested, show detailed history for that vehicle.
// Otherwise present a summary of distinct vehicles the user has used.
if ($kendaraan_id > 0) {
    // Riwayat untuk kendaraan tertentu - tampilkan riwayat yang terkait dengan pengguna saat ini
    // (filter oleh rp.user_id agar menampilkan apa yang pengguna lakukan)
    $count_stmt = $mysqli->prepare(
        "SELECT COUNT(*) as total
         FROM riwayat_pemakaian rp
         WHERE rp.kendaraan_id = ? AND rp.user_id = ?"
    );
    $count_stmt->bind_param('ii', $kendaraan_id, $user_id);

    $driver_join = table_has_columns($mysqli, 'riwayat_pemakaian', ['driver_id']);
    $select_driver = $driver_join ? ", d.nama_lengkap AS driver_name" : ", '' AS driver_name";
    $join_driver = $driver_join ? "LEFT JOIN pengguna d ON rp.driver_id = d.id" : "";

    $sql = "SELECT rp.*, k.merk, k.tipe, k.no_reg" . $select_driver . "\n         FROM riwayat_pemakaian rp\n         JOIN kendaraan k ON rp.kendaraan_id = k.id\n         " . $join_driver . "\n         WHERE rp.kendaraan_id = ? AND rp.user_id = ?\n         ORDER BY rp.tanggal DESC\n         LIMIT ? OFFSET ?";
    $stmt = $mysqli->prepare($sql);
    $stmt->bind_param('iiii', $kendaraan_id, $user_id, $limit, $offset);

    $count_stmt->execute();
    $total_rows = (int)$count_stmt->get_result()->fetch_assoc()['total'];
    $count_stmt->close();

    $stmt->execute();
    $riwayat_list = $stmt->get_result();
    $stmt->close();

    $total_pages = ceil($total_rows / $limit);

} else {
    // Summary of distinct vehicles the user has used (based on riwayat_pemakaian.user_id)
    $vehicles = [];
    $vehicles_stmt = $mysqli->prepare(
        "SELECT k.id, k.no_reg, k.merk, k.tipe, COUNT(rp.id) AS total_use, MAX(rp.tanggal) AS last_used
         FROM riwayat_pemakaian rp
         JOIN kendaraan k ON rp.kendaraan_id = k.id
         WHERE rp.user_id = ?
         GROUP BY k.id
         ORDER BY last_used DESC"
    );
    if ($vehicles_stmt) {
        $vehicles_stmt->bind_param('i', $user_id);
        $vehicles_stmt->execute();
        $vehicles = $vehicles_stmt->get_result()->fetch_all(MYSQLI_ASSOC);
        $vehicles_stmt->close();
    }

    $total_rows = count($vehicles);
    $total_pages = 1;
}
?>

        <div class="page-header">
            <h1><i class="fas fa-history me-2"></i> Riwayat Kendaraan</h1>
        </div>

<div class="content-container">
    <?php if ($total_rows > 0): ?>
        <!-- Filter & Info -->
        <div class="bg-white p-3 rounded-2 mb-4 d-flex justify-content-between align-items-center shadow-sm">
            <div class="text-muted small">
                <span class="fw-semibold">
                    <i class="fas fa-list text-primary me-2"></i>
                    Total: <?= $total_rows ?> riwayat
                </span>
            </div>
            <?php if ($kendaraan_id > 0): ?>
                <div class="mb-3">
                    <a href="index.php?page=riwayat_kendaraan" class="btn btn-secondary btn-sm">
                        <i class="fas fa-arrow-left me-1"></i> Kembali
                    </a>
                </div>
            <?php endif; ?>
        </div>

        <!-- Riwayat List -->
        <div class="d-flex flex-column gap-4">
            <?php if ($kendaraan_id > 0): ?>
                <?php while ($riwayat = $riwayat_list->fetch_assoc()): ?>
                    <div class="card shadow-sm rounded-3 overflow-hidden card-hover">
                        <div class="card-header bg-light p-3 border-bottom d-flex justify-content-between align-items-center">
                            <div class="text-dark fw-semibold">
                                <i class="fas fa-calendar me-2"></i>
                                <span><?= date('d/m/Y', strtotime($riwayat['tanggal'])) ?></span>
                            </div>
                            <div class="text-end">
                                <span class="d-block fw-semibold text-dark small"><?= htmlspecialchars($riwayat['merk'] . ' ' . $riwayat['tipe']) ?></span>
                                <span class="d-block text-dark small fw-medium"><?= htmlspecialchars($riwayat['no_reg']) ?></span>
                            </div>
                        </div>
                        
                        <div class="card-body p-4">
                            <div class="d-flex flex-column gap-3">
                                <div class="row g-3">
                                    <div class="col-md-6 d-flex flex-column gap-1">
                                        <label class="small fw-semibold text-muted mb-1"><i class="fas fa-map-marker-alt text-primary me-1"></i> Tujuan</label>
                                        <span class="text-dark"><?= htmlspecialchars($riwayat['tujuan']) ?></span>
                                    </div>
                                    <div class="col-md-6 d-flex flex-column gap-1">
                                        <label class="small fw-semibold text-muted mb-1"><i class="fas fa-clock text-primary me-1"></i> Waktu</label>
                                        <span class="text-dark">
                                            <?= !empty($riwayat['jam_keluar']) ? date('H:i', strtotime($riwayat['jam_keluar'])) : '07:00' ?> - 
                                            <?= !empty($riwayat['jam_kembali']) ? date('H:i', strtotime($riwayat['jam_kembali'])) : '17:00' ?>
                                        </span>
                                    </div>
                                </div>
                                
                                <div class="row g-3">
                                    <div class="col-md-6 d-flex flex-column gap-1">
                                        <label class="small fw-semibold text-muted mb-1"><i class="fas fa-user text-primary me-1"></i> Driver</label>
                                        <span class="text-dark"><?= htmlspecialchars($riwayat['driver_name'] ?? 'Tidak tercatat') ?></span>
                                    </div>
                                    <div class="col-md-6 d-flex flex-column gap-1">
                                        <label class="small fw-semibold text-muted mb-1"><i class="fas fa-sticky-note text-primary me-1"></i> Keperluan</label>
                                        <span class="text-dark"><?= htmlspecialchars($riwayat['keperluan'] ?? 'Tidak tercatat') ?></span>
                                    </div>
                                </div>
                                
                                <div class="detail-row">
                                    <div class="detail-item">
                                        <label><i class="fas fa-tachometer-alt"></i> KM Awal</label>
                                        <span><?= number_format($riwayat['km_awal']) ?> km</span>
                                    </div>
                                    <div class="detail-item">
                                        <label><i class="fas fa-tachometer-alt"></i> KM Akhir</label>
                                        <span><?= number_format($riwayat['km_akhir']) ?> km</span>
                                    </div>
                                    <div class="detail-item">
                                        <label><i class="fas fa-route"></i> Jarak Tempuh</label>
                                        <span class="distance"><?= number_format($riwayat['km_akhir'] - $riwayat['km_awal']) ?> km</span>
                                    </div>
                                </div>

                                <?php if (isset($riwayat['catatan']) && !empty($riwayat['catatan'])): ?>
                                    <div class="detail-row">
                                        <div class="detail-item full-width">
                                            <label><i class="fas fa-comment"></i> Keterangan</label>
                                            <span><?= htmlspecialchars($riwayat['catatan']) ?></span>
                                        </div>
                                    </div>
                                <?php endif; ?>
                            </div>
                        </div>
                        
                        <div class="card-footer">
                            <div class="footer-info">
                                <span class="duration">
                                    <i class="fas fa-clock"></i>
                                    <?php
                                        // Calculate duration using jam_keluar (departure) and jam_kembali (return)
                                        if (!empty($riwayat['jam_keluar']) && !empty($riwayat['jam_kembali'])) {
                                            $jam_keluar = new DateTime($riwayat['jam_keluar']);
                                            $jam_kembali = new DateTime($riwayat['jam_kembali']);
                                            $durasi = $jam_keluar->diff($jam_kembali);
                                            echo $durasi->format('%h jam %i menit');
                                        } else {
                                            echo 'Durasi tidak tercatat';
                                        }
                                    ?>
                                    </span>

                                    <?php
                                        // Display BBM fields from riwayat_pemakaian if present (bbm_keluar / bbm_kembali)
                                        $fuelParts = [];
                                        if (!empty($riwayat['bbm_keluar'])) {
                                            $fuelParts[] = number_format($riwayat['bbm_keluar'], 1) . ' L (keluar)';
                                        }
                                        if (!empty($riwayat['bbm_kembali'])) {
                                            $fuelParts[] = number_format($riwayat['bbm_kembali'], 1) . ' L (kembali)';
                                        }
                                        if (!empty($fuelParts)):
                                    ?>
                                        <span class="fuel-info">
                                            <i class="fas fa-gas-pump"></i>
                                            <?= implode(' - ', $fuelParts) ?>
                                        </span>
                                    <?php endif; ?>
                            </div>
                        </div>
                    </div>
                <?php endwhile; ?>
            <?php else: ?>
                <?php foreach ($vehicles as $veh): ?>
                    <div class="card shadow-sm rounded-3 overflow-hidden card-hover">
                        <div class="card-body d-flex justify-content-between align-items-center">
                            <div>
                                <h5 class="mb-1 fw-semibold"><?= htmlspecialchars($veh['merk'] . ' ' . $veh['tipe']) ?></h5>
                                <div class="text-muted small">No. reg: <?= htmlspecialchars($veh['no_reg']) ?></div>
                                <div class="text-muted small">Dipakai: <?= (int)$veh['total_use'] ?> kali, terakhir <?= (!empty($veh['last_used']) ? date('d/m/Y', strtotime($veh['last_used'])) : '-') ?></div>
                            </div>
                            <div>
                                <a href="index.php?page=riwayat_kendaraan&kendaraan_id=<?= $veh['id'] ?>" class="btn btn-outline-primary">Lihat Riwayat</a>
                            </div>
                        </div>
                    </div>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>

        <!-- Pagination -->
        <?php if ($total_pages > 1): ?>
            <div class="pagination-container">
                <div class="pagination">
                    <?php if ($page > 1): ?>
                        <a href="?page=riwayat_kendaraan<?= $kendaraan_id > 0 ? '&kendaraan_id=' . $kendaraan_id : '' ?>&page_num=<?= $page - 1 ?>" class="page-btn">
                            <i class="fas fa-chevron-left"></i>
                        </a>
                    <?php endif; ?>
                    
                    <?php for ($i = max(1, $page - 2); $i <= min($total_pages, $page + 2); $i++): ?>
                        <a href="?page=riwayat_kendaraan<?= $kendaraan_id > 0 ? '&kendaraan_id=' . $kendaraan_id : '' ?>&page_num=<?= $i ?>" 
                           class="page-btn <?= $i == $page ? 'active' : '' ?>">
                            <?= $i ?>
                        </a>
                    <?php endfor; ?>
                    
                    <?php if ($page < $total_pages): ?>
                        <a href="?page=riwayat_kendaraan<?= $kendaraan_id > 0 ? '&kendaraan_id=' . $kendaraan_id : '' ?>&page_num=<?= $page + 1 ?>" class="page-btn">
                            <i class="fas fa-chevron-right"></i>
                        </a>
                    <?php endif; ?>
                </div>
                <div class="pagination-info">
                    Halaman <?= $page ?> dari <?= $total_pages ?> (<?= $total_rows ?> total riwayat)
                </div>
            </div>
        <?php endif; ?>
        
    <?php else: ?>
        <div class="empty-state">
            <div class="empty-icon">
                <i class="fas fa-history"></i>
            </div>
            <h3>Belum Ada Riwayat</h3>
            <p>Belum ada riwayat penggunaan kendaraan.</p>
            <div class="empty-actions">
                <a href="index.php?page=kendaraan_saya" class="btn btn-primary">
                    <i class="fas fa-car"></i> Lihat Kendaraan Saya
                </a>
            </div>
        </div>
    <?php endif; ?>
</div>


