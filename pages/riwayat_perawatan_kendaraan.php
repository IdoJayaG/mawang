<?php
require_once '../includes/auth.php';
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

// Get maintenance history
$maintenance_result = $mysqli->query("
    SELECT jp.*, u.nama_lengkap as teknisi_nama
    FROM jadwal_perawatan jp
    LEFT JOIN pengguna u ON jp.teknisi_id = u.id
    WHERE jp.kendaraan_id = $kendaraan_id
    ORDER BY jp.tanggal_perawatan DESC
");

// Get repair history  
$repair_result = $mysqli->query("
    SELECT rp.*, u.nama_lengkap as teknisi_nama
    FROM riwayat_perbaikan rp
    LEFT JOIN pengguna u ON rp.teknisi_id = u.id
    WHERE rp.kendaraan_id = $kendaraan_id
    ORDER BY rp.tanggal_perbaikan DESC
");

// Calculate total maintenance cost
$cost_result = $mysqli->query("
    SELECT 
        COALESCE(SUM(jp.biaya), 0) as total_perawatan,
        COALESCE(SUM(rp.biaya_perbaikan), 0) as total_perbaikan
    FROM kendaraan k
    LEFT JOIN jadwal_perawatan jp ON k.id = jp.kendaraan_id
    LEFT JOIN riwayat_perbaikan rp ON k.id = rp.kendaraan_id
    WHERE k.id = $kendaraan_id
");
$costs = $cost_result->fetch_assoc();
$total_cost = $costs['total_perawatan'] + $costs['total_perbaikan'];

// Get monthly maintenance trend
$trend_result = $mysqli->query("
    SELECT 
        DATE_FORMAT(tanggal_perawatan, '%Y-%m') as bulan,
        COUNT(*) as jumlah_perawatan,
        SUM(biaya) as total_biaya
    FROM jadwal_perawatan
    WHERE kendaraan_id = $kendaraan_id
    AND tanggal_perawatan >= DATE_SUB(NOW(), INTERVAL 12 MONTH)
    GROUP BY DATE_FORMAT(tanggal_perawatan, '%Y-%m')
    ORDER BY bulan
");
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Riwayat Perawatan - <?php 
        $primary = ($vehicle['no_reg'] ?? '') !== '' ? $vehicle['no_reg'] : ($vehicle['no_polisi'] ?? '-');
        echo htmlspecialchars($vehicle['merk'] . ' ' . $vehicle['tipe'] . ' - ' . $primary);
    ?> - SI-KENDI</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <style>
        .gradient-bg {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
        }
        .card-header {
            background: linear-gradient(135deg, #4CAF50 0%, #45a049 100%);
            color: white;
        }
        .stats-card {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            border-radius: 15px;
        }
        .status-badge {
            padding: 0.25rem 0.75rem;
            border-radius: 50px;
            font-size: 0.875rem;
            font-weight: 500;
        }
        .maintenance-timeline {
            position: relative;
            padding-left: 30px;
        }
        .maintenance-timeline::before {
            content: '';
            position: absolute;
            left: 15px;
            top: 0;
            bottom: 0;
            width: 2px;
            background: #dee2e6;
        }
        .timeline-item {
            position: relative;
            margin-bottom: 20px;
        }
        .timeline-item::before {
            content: '';
            position: absolute;
            left: -23px;
            top: 10px;
            width: 12px;
            height: 12px;
            border-radius: 50%;
            background: #28a745;
            border: 3px solid white;
            box-shadow: 0 0 0 3px #dee2e6;
        }
        .repair-item::before {
            background: #dc3545;
        }
    </style>
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
                                    <i class="fas fa-wrench me-2"></i>Riwayat Perawatan
                                </h4>
                                <h5 class="mb-0"><?php echo htmlspecialchars($vehicle['merk'] . ' ' . $vehicle['tipe']); ?> - 
                                    <?php 
                                        $primary = ($vehicle['no_reg'] ?? '') !== '' ? $vehicle['no_reg'] : ($vehicle['no_polisi'] ?? '-');
                                        echo htmlspecialchars($primary);
                                    ?>
                                    <?php if (!empty($vehicle['no_polisi'])): ?>
                                        &middot; <small class="text-muted">Nopol: <?= htmlspecialchars($vehicle['no_polisi']) ?></small>
                                    <?php endif; ?>
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
                                            <h3 class="mb-1"><?php echo $maintenance_result->num_rows; ?></h3>
                                            <small>Total Perawatan</small>
                                        </div>
                                    </div>
                                    <div class="col-md-3">
                                        <div class="stats-card text-center p-3 mb-3">
                                            <h3 class="mb-1"><?php echo $repair_result->num_rows; ?></h3>
                                            <small>Total Perbaikan</small>
                                        </div>
                                    </div>
                                    <div class="col-md-3">
                                        <div class="stats-card text-center p-3 mb-3">
                                            <h3 class="mb-1">Rp <?php echo number_format($total_cost, 0, ',', '.'); ?></h3>
                                            <small>Total Biaya</small>
                                        </div>
                                    </div>
                                    <div class="col-md-3">
                                        <div class="stats-card text-center p-3 mb-3">
                                            <h3 class="mb-1">
                                                <?php 
                                                $condition_class = '';
                                                switch($vehicle['kondisi']) {
                                                    case 'baik': $condition_class = 'text-success'; break;
                                                    case 'rusak ringan': $condition_class = 'text-warning'; break;
                                                    case 'rusak berat': $condition_class = 'text-danger'; break;
                                                }
                                                echo ucfirst($vehicle['kondisi']);
                                                ?>
                                            </h3>
                                            <small>Kondisi Saat Ini</small>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <div class="row">
            <!-- Maintenance Chart -->
            <div class="col-md-6 mb-4">
                <div class="card shadow">
                    <div class="card-header">
                        <h5 class="mb-0"><i class="fas fa-chart-line me-2"></i>Trend Perawatan (12 Bulan)</h5>
                    </div>
                    <div class="card-body">
                        <canvas id="maintenanceChart" height="200"></canvas>
                    </div>
                </div>
            </div>

            <!-- Quick Stats -->
            <div class="col-md-6 mb-4">
                <div class="card shadow">
                    <div class="card-header">
                        <h5 class="mb-0"><i class="fas fa-chart-pie me-2"></i>Statistik Perawatan</h5>
                    </div>
                    <div class="card-body">
                        <canvas id="statsChart" height="200"></canvas>
                    </div>
                </div>
            </div>
        </div>

        <!-- Timeline -->
        <div class="row">
            <div class="col-12">
                <div class="card shadow">
                    <div class="card-header">
                        <h5 class="mb-0"><i class="fas fa-history me-2"></i>Timeline Perawatan & Perbaikan</h5>
                    </div>
                    <div class="card-body">
                        <div class="maintenance-timeline">
                            <?php
                            // Combine maintenance and repair data
                            $timeline = array();
                            
                            $maintenance_result->data_seek(0);
                            while ($row = $maintenance_result->fetch_assoc()) {
                                $timeline[] = array(
                                    'date' => $row['tanggal_perawatan'],
                                    'type' => 'maintenance',
                                    'data' => $row
                                );
                            }
                            
                            $repair_result->data_seek(0);
                            while ($row = $repair_result->fetch_assoc()) {
                                $timeline[] = array(
                                    'date' => $row['tanggal_perbaikan'],
                                    'type' => 'repair',
                                    'data' => $row
                                );
                            }
                            
                            // Sort by date descending
                            usort($timeline, function($a, $b) {
                                return strtotime($b['date']) - strtotime($a['date']);
                            });
                            
                            if (empty($timeline)):
                            ?>
                                <div class="text-center py-5">
                                    <i class="fas fa-tools fa-3x text-muted mb-3"></i>
                                    <h5 class="text-muted">Belum ada riwayat perawatan untuk kendaraan ini</h5>
                                </div>
                            <?php else: ?>
                                <?php foreach ($timeline as $item): ?>
                                    <div class="timeline-item <?php echo $item['type'] === 'repair' ? 'repair-item' : ''; ?>">
                                        <div class="card">
                                            <div class="card-body">
                                                <div class="d-flex justify-content-between align-items-start mb-2">
                                                    <h6 class="mb-0">
                                                        <?php if ($item['type'] === 'maintenance'): ?>
                                                            <i class="fas fa-tools text-success me-2"></i>
                                                            <?php echo htmlspecialchars($item['data']['jenis_perawatan']); ?>
                                                        <?php else: ?>
                                                            <i class="fas fa-hammer text-danger me-2"></i>
                                                            Perbaikan
                                                        <?php endif; ?>
                                                    </h6>
                                                    <small class="text-muted">
                                                        <?php echo date('d/m/Y', strtotime($item['date'])); ?>
                                                    </small>
                                                </div>
                                                <p class="mb-2 text-muted">
                                                    <?php 
                                                    if ($item['type'] === 'maintenance') {
                                                        echo htmlspecialchars($item['data']['deskripsi']);
                                                    } else {
                                                        echo htmlspecialchars($item['data']['deskripsi_kerusakan']);
                                                    }
                                                    ?>
                                                </p>
                                                <div class="row">
                                                    <div class="col-md-4">
                                                        <small class="text-muted">Teknisi:</small><br>
                                                        <strong><?php echo htmlspecialchars($item['data']['teknisi_nama'] ?: 'Tidak tercatat'); ?></strong>
                                                    </div>
                                                    <div class="col-md-4">
                                                        <small class="text-muted">Biaya:</small><br>
                                                        <strong class="text-primary">
                                                            Rp <?php 
                                                            $cost = $item['type'] === 'maintenance' ? $item['data']['biaya'] : $item['data']['biaya_perbaikan'];
                                                            echo number_format($cost ?: 0, 0, ',', '.'); 
                                                            ?>
                                                        </strong>
                                                    </div>
                                                    <div class="col-md-4">
                                                        <small class="text-muted">Status:</small><br>
                                                        <?php 
                                                        $status = $item['type'] === 'maintenance' ? $item['data']['status'] : $item['data']['status_perbaikan'];
                                                        $status_class = '';
                                                        switch($status) {
                                                            case 'selesai': $status_class = 'bg-success'; break;
                                                            case 'dalam_proses': $status_class = 'bg-warning text-dark'; break;
                                                            case 'terjadwal': $status_class = 'bg-info'; break;
                                                            default: $status_class = 'bg-secondary';
                                                        }
                                                        ?>
                                                        <span class="status-badge <?php echo $status_class; ?>">
                                                            <?php echo ucfirst(str_replace('_', ' ', $status)); ?>
                                                        </span>
                                                    </div>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        // Maintenance Trend Chart
        const trendCtx = document.getElementById('maintenanceChart').getContext('2d');
        const trendData = {
            labels: [
                <?php 
                $trend_result->data_seek(0);
                $months = [];
                while ($row = $trend_result->fetch_assoc()) {
                    $months[] = "'" . date('M Y', strtotime($row['bulan'] . '-01')) . "'";
                }
                echo implode(',', $months);
                ?>
            ],
            datasets: [{
                label: 'Jumlah Perawatan',
                data: [
                    <?php 
                    $trend_result->data_seek(0);
                    $counts = [];
                    while ($row = $trend_result->fetch_assoc()) {
                        $counts[] = $row['jumlah_perawatan'];
                    }
                    echo implode(',', $counts);
                    ?>
                ],
                borderColor: '#4CAF50',
                backgroundColor: 'rgba(76, 175, 80, 0.1)',
                tension: 0.4
            }]
        };

        new Chart(trendCtx, {
            type: 'line',
            data: trendData,
            options: {
                responsive: true,
                maintainAspectRatio: false,
                scales: {
                    y: {
                        beginAtZero: true
                    }
                }
            }
        });

        // Stats Pie Chart
        const statsCtx = document.getElementById('statsChart').getContext('2d');
        const statsData = {
            labels: ['Perawatan', 'Perbaikan'],
            datasets: [{
                data: [<?php echo $maintenance_result->num_rows; ?>, <?php echo $repair_result->num_rows; ?>],
                backgroundColor: ['#4CAF50', '#FF6384'],
                borderWidth: 0
            }]
        };

        new Chart(statsCtx, {
            type: 'doughnut',
            data: statsData,
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: {
                        position: 'bottom'
                    }
                }
            }
        });
    </script>
</body>
</html>
