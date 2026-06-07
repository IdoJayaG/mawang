<?php
// Include global template (anchored)
require_once __DIR__ . '/../templates/page_template.php';

require_once __DIR__ . '/../includes/auth.php';
require_login();

$kendaraan_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;

if (!$kendaraan_id) {
    header('Location: index.php?page=kendaraan');
    exit;
}

// Get vehicle details
$vehicle_result = $mysqli->query("SELECT * FROM kendaraan WHERE id = $kendaraan_id");
if (!$vehicle_result || $vehicle_result->num_rows === 0) {
    header('Location: index.php?page=kendaraan');
    exit;
}
$vehicle = $vehicle_result->fetch_assoc();

if (in_array(strtolower((string)get_current_role()), ['user', 'driver'], true) && !can_access_vehicle($kendaraan_id)) {
    header('Location: index.php?page=403');
    exit;
}

// Get fuel logs
    $fuel_result = $mysqli->query("
    SELECT lbb.*, COALESCE(u.nama_lengkap, '') as petugas_nama
    FROM log_bahan_bakar lbb
    LEFT JOIN pengguna u ON lbb.user_id = u.id
    WHERE lbb.kendaraan_id = $kendaraan_id
    ORDER BY lbb.tanggal_isi DESC
");

// Calculate fuel statistics
$stats_result = $mysqli->query("
    SELECT 
        COUNT(*) as total_isi,
        SUM(jumlah_liter) as total_liter,
        MAX(tanggal_isi) as last_refuel
    FROM log_bahan_bakar
    WHERE kendaraan_id = $kendaraan_id
");
$stats = $stats_result->fetch_assoc();

// Get monthly fuel consumption
$monthly_result = $mysqli->query("
    SELECT 
        DATE_FORMAT(tanggal_isi, '%Y-%m') as bulan,
        SUM(jumlah_liter) as total_liter,
        COUNT(*) as jumlah_isi
    FROM log_bahan_bakar
    WHERE kendaraan_id = $kendaraan_id
    AND tanggal_isi >= DATE_SUB(NOW(), INTERVAL 12 MONTH)
    GROUP BY DATE_FORMAT(tanggal_isi, '%Y-%m')
    ORDER BY bulan
");

// Calculate fuel efficiency
$efficiency_result = $mysqli->query("
    SELECT 
        lbb.*,
        rp.km_akhir - rp.km_awal as jarak_tempuh
    FROM log_bahan_bakar lbb
    LEFT JOIN riwayat_pemakaian rp ON lbb.kendaraan_id = rp.kendaraan_id 
        AND DATE(lbb.tanggal_isi) BETWEEN DATE(rp.tanggal_mulai) AND DATE(rp.tanggal_selesai)
    WHERE lbb.kendaraan_id = $kendaraan_id
    AND rp.km_akhir > rp.km_awal
    ORDER BY lbb.tanggal_isi DESC
    LIMIT 10
");
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Log BBM - <?php echo htmlspecialchars($vehicle['merk'] . ' ' . $vehicle['tipe'] . ' - ' . ($vehicle['no_reg'] ?? $vehicle['no_polisi'])); ?> - SI-KENDI</title>
    <!-- Bootstrap included in template -->
    <!-- FontAwesome included in template -->
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    
</head>
<body class="bg-light">
    <div class="container-fluid mt-4">
        <!-- Vehicle Header -->
        <div class="row mb-4">
            <div class="col-12">
                <div class="card shadow">
                    <div class="card-header">
                        <div class="d-flex justify-content-between align-items-center">
                            <div>
                                <h4 class="mb-0">
                                    <i class="fas fa-gas-pump me-2"></i>Log Bahan Bakar
                                </h4>
                                <h5 class="mb-0"><?php echo htmlspecialchars($vehicle['merk'] . ' ' . $vehicle['tipe']); ?> - <?php echo htmlspecialchars($vehicle['no_reg'] ?? $vehicle['no_polisi']); ?>
                                </h5>
                            </div>
                            <a href="index.php?page=kendaraan_detail&id=<?php echo $kendaraan_id; ?>" class="btn btn-light">
                                <i class="fas fa-arrow-left me-2"></i>Kembali
                            </a>
                        </div>
                    </div>
                    <div class="card-body">
                        <div class="row">
                            <div class="col-md-3">
                                <img src="../assets/images/<?php echo htmlspecialchars($vehicle['foto'] ?: 'default.jpg'); ?>" 
                                     class="img-fluid rounded" alt="Foto Kendaraan">
                            </div>
                            <div class="col-md-9">
                                <div class="row">
                                    <div class="col-md-3">
                                        <div class="stats-card text-center p-3 mb-3">
                                            <h3 class="mb-1"><?php echo number_format($stats['total_isi'] ?: 0); ?></h3>
                                            <small>Total Pengisian</small>
                                        </div>
                                    </div>
                                    <div class="col-md-3">
                                        <div class="stats-card text-center p-3 mb-3">
                                            <h3 class="mb-1"><?php echo number_format($stats['total_liter'] ?: 0, 1); ?></h3>
                                            <small>Total Liter</small>
                                        </div>
                                    </div>
                                    <!-- Biaya/harga dihapus dari tampilan -->
                                </div>
                                <?php if ($stats['last_refuel']): ?>
                                <div class="alert alert-info mb-0">
                                    <i class="fas fa-info-circle me-2"></i>
                                    Pengisian terakhir: <?php echo date('d/m/Y H:i', strtotime($stats['last_refuel'])); ?>
                                </div>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <div class="row">
            <!-- Monthly Consumption Chart -->
            <div class="col-md-8 mb-4">
                <div class="card shadow">
                    <div class="card-header">
                        <h5 class="mb-0"><i class="fas fa-chart-line me-2"></i>Konsumsi BBM Bulanan (12 Bulan)</h5>
                    </div>
                    <div class="card-body">
                        <canvas id="consumptionChart" height="300"></canvas>
                    </div>
                </div>
            </div>

            <!-- Fuel Efficiency -->
            <div class="col-md-4 mb-4">
                <div class="card shadow">
                    <div class="card-header">
                        <h5 class="mb-0"><i class="fas fa-tachometer-alt me-2"></i>Efisiensi BBM</h5>
                    </div>
                    <div class="card-body">
                        <?php if ($efficiency_result->num_rows > 0): ?>
                            <?php while ($eff = $efficiency_result->fetch_assoc()): ?>
                                <?php if ($eff['jarak_tempuh'] > 0): ?>
                                    <?php 
                                    $km_per_liter = $eff['jarak_tempuh'] / $eff['jumlah_liter'];
                                    $efficiency_class = '';
                                    if ($km_per_liter >= 15) $efficiency_class = 'bg-success';
                                    elseif ($km_per_liter >= 10) $efficiency_class = 'bg-warning text-dark';
                                    else $efficiency_class = 'bg-danger';
                                    ?>
                                    <div class="d-flex justify-content-between align-items-center mb-2">
                                        <small class="text-muted"><?php echo date('d/m/Y', strtotime($eff['tanggal_isi'])); ?></small>
                                        <span class="efficiency-badge <?php echo $efficiency_class; ?>">
                                            <?php echo number_format($km_per_liter, 1); ?> km/L
                                        </span>
                                    </div>
                                <?php endif; ?>
                            <?php endwhile; ?>
                        <?php else: ?>
                            <div class="text-center py-3">
                                <i class="fas fa-chart-line fa-2x text-muted mb-2"></i>
                                <p class="text-muted">Data efisiensi tidak tersedia</p>
                                <small class="text-muted">Perlu data riwayat pemakaian untuk menghitung efisiensi</small>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>

        <!-- Fuel Log Timeline -->
        <div class="row">
            <div class="col-12">
                <div class="card shadow">
                    <div class="card-header">
                        <h5 class="mb-0"><i class="fas fa-history me-2"></i>Riwayat Pengisian BBM</h5>
                    </div>
                    <div class="card-body">
                        <div class="fuel-timeline">
                            <?php if ($fuel_result->num_rows === 0): ?>
                                <div class="text-center py-5">
                                    <i class="fas fa-gas-pump fa-3x text-muted mb-3"></i>
                                    <h5 class="text-muted">Belum ada riwayat pengisian BBM untuk kendaraan ini</h5>
                                </div>
                            <?php else: ?>
                                <?php while ($fuel = $fuel_result->fetch_assoc()): ?>
                                    <div class="timeline-item">
                                        <div class="card">
                                            <div class="card-body">
                                                <div class="d-flex justify-content-between align-items-start mb-2">
                                                    <h6 class="mb-0">
                                                        <i class="fas fa-gas-pump text-danger me-2"></i>
                                                        Pengisian BBM <?php echo htmlspecialchars($fuel['jenis_bahan_bakar']); ?>
                                                    </h6>
                                                    <small class="text-muted">
                                                        <?php echo date('d/m/Y H:i', strtotime($fuel['tanggal_isi'])); ?>
                                                    </small>
                                                </div>
                                                
                                                <div class="row">
                                                    <div class="col-md-3">
                                                        <small class="text-muted">Jumlah:</small><br>
                                                        <strong class="text-primary"><?php echo number_format($fuel['jumlah_liter'], 1); ?> Liter</strong>
                                                    </div>
                                                    <div class="col-md-3">
                                                        <small class="text-muted">KM Saat Isi:</small><br>
                                                        <strong><?php echo number_format($fuel['km_saat_isi'] ?: 0, 0, ',', '.'); ?> km</strong>
                                                    </div>
                                                    <div class="col-md-3">
                                                        <small class="text-muted">Petugas:</small><br>
                                                        <strong><?php echo htmlspecialchars($fuel['petugas_nama'] ?: 'Tidak tercatat'); ?></strong>
                                                    </div>
                                                    <div class="col-md-3">
                                                        <small class="text-muted">SPBU:</small><br>
                                                        <strong><?php echo htmlspecialchars($fuel['spbu'] ?: 'Tidak tercatat'); ?></strong>
                                                    </div>
                                                </div>
                                                
                                                <?php if ($fuel['catatan']): ?>
                                                    <div class="mt-2">
                                                        <small class="text-muted">Catatan:</small><br>
                                                        <em><?php echo htmlspecialchars($fuel['catatan']); ?></em>
                                                    </div>
                                                <?php endif; ?>
                                                
                                                <?php if ($fuel['foto_struk']): ?>
                                                    <div class="mt-2">
                                                        <a href="../uploads/bbm/<?php echo htmlspecialchars($fuel['foto_struk']); ?>" 
                                                           target="_blank" class="btn btn-sm btn-outline-primary">
                                                            <i class="fas fa-receipt me-1"></i>Lihat Struk
                                                        </a>
                                                    </div>
                                                <?php endif; ?>
                                            </div>
                                        </div>
                                    </div>
                                <?php endwhile; ?>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        // Monthly Consumption Chart
        const ctx = document.getElementById('consumptionChart').getContext('2d');
        const chartData = {
            labels: [
                <?php 
                $monthly_result->data_seek(0);
                $months = [];
                while ($row = $monthly_result->fetch_assoc()) {
                    $months[] = "'" . date('M Y', strtotime($row['bulan'] . '-01')) . "'";
                }
                echo implode(',', $months);
                ?>
            ],
            datasets: [{
                label: 'Konsumsi (Liter)',
                data: [
                    <?php 
                    $monthly_result->data_seek(0);
                    $liters = [];
                    while ($row = $monthly_result->fetch_assoc()) {
                        $liters[] = $row['total_liter'];
                    }
                    echo implode(',', $liters);
                    ?>
                ],
                backgroundColor: 'rgba(255, 107, 107, 0.7)',
                borderColor: '#FF6B6B',
                borderWidth: 2,
                yAxisID: 'y'
            }]
        };

        new Chart(ctx, {
            type: 'bar',
            data: chartData,
            options: {
                responsive: true,
                maintainAspectRatio: false,
                interaction: {
                    mode: 'index',
                    intersect: false,
                },
                scales: {
                    x: {
                        display: true,
                        title: {
                            display: true,
                            text: 'Bulan'
                        }
                    },
                    y: {
                        type: 'linear',
                        display: true,
                        position: 'left',
                        title: {
                            display: true,
                            text: 'Liter'
                        }
                    }
                }
            }
        });
    </script>
</body>
</html>
