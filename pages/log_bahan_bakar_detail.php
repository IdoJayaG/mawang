<?php
require_once 'includes/auth.php';

$current_role = get_current_role();
$current_user_id = get_current_user_id();

// Role check: guest are redirected
if ($current_role === 'guest') {
    header('Location: index.php?page=kendaraan_publik');
    exit;
}

$kendaraan_id = isset($_GET['kendaraan_id']) ? (int)$_GET['kendaraan_id'] : 0;
if ($kendaraan_id <= 0) {
    echo '<div class="alert alert-danger">ID kendaraan tidak valid</div>';
    exit;
}

// Fetch vehicle info
$stmt = $mysqli->prepare("SELECT id, no_polisi, no_reg, merk, tipe FROM kendaraan WHERE id = ?");
$stmt->bind_param('i', $kendaraan_id);
$stmt->execute();
$vehicle = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$vehicle) {
    echo '<div class="alert alert-danger">Kendaraan tidak ditemukan</div>';
    exit;
}

// Fetch all BBM logs for this vehicle
$logs_stmt = $mysqli->prepare("SELECT lb.*, u.nama_lengkap as user_name FROM log_bahan_bakar lb LEFT JOIN pengguna u ON lb.user_id = u.id WHERE lb.kendaraan_id = ? ORDER BY lb.tanggal_isi DESC");
$logs_stmt->bind_param('i', $kendaraan_id);
$logs_stmt->execute();
$logs = $logs_stmt->get_result();
$logs_stmt->close();
?>

<div class="page-header">
    <h1><i class="fas fa-gas-pump"></i> Detail Log BBM - <?= htmlspecialchars($vehicle['no_polisi']) ?> <?= !empty($vehicle['no_reg']) ? '<small class="text-muted">(Reg: ' . htmlspecialchars($vehicle['no_reg']) . ')</small>' : '' ?></h1>
    <div class="header-actions">
        <a href="index.php?page=log_bahan_bakar" class="btn btn-outline">&larr; Kembali</a>
        <?php if (can_operate() || $current_role === 'user'): ?>
            <a href="index.php?page=log_bahan_bakar&action=add&kendaraan_id=<?= $kendaraan_id ?>" class="btn btn-warning">Tambah Log BBM</a>
        <?php endif; ?>
    </div>
</div>

<div class="card">
    <div class="card-body">
        <div class="table-responsive">
            <table class="table table-striped table-hover">
                <thead>
                    <tr>
                        <th>#</th>
                        <th>Tanggal & Waktu</th>
                        <th>Jumlah (L)</th>
                        <th>Harga/L</th>
                        <th>Total Biaya</th>
                        <th>KM Saat Isi</th>
                        <th>SPBU</th>
                        <th>Metode</th>
                        <th>User</th>
                        <th>Keterangan</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if ($logs->num_rows > 0): $i = 1; while ($row = $logs->fetch_assoc()): ?>
                        <tr>
                            <td><?= $i++ ?></td>
                            <td><?= !empty($row['tanggal_isi']) ? date('d/m/Y H:i', strtotime($row['tanggal_isi'])) : '-' ?></td>
                            <td><?= number_format($row['jumlah_liter'], 2) ?></td>
                            <td>Rp <?= number_format($row['harga_per_liter'], 2) ?></td>
                            <td>Rp <?= number_format($row['biaya'], 2) ?></td>
                            <td><?= $row['km_saat_isi'] ? number_format($row['km_saat_isi']) : '-' ?></td>
                            <td><?= htmlspecialchars($row['spbu']) ?></td>
                            <td><?= htmlspecialchars($row['metode_bayar']) ?></td>
                            <td><?= htmlspecialchars($row['user_name'] ?? '-') ?></td>
                            <td><?= htmlspecialchars($row['keterangan']) ?></td>
                        </tr>
                    <?php endwhile; else: ?>
                        <tr><td colspan="10" class="text-center">Belum ada log BBM untuk kendaraan ini.</td></tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>
