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

if (!can_operate() && !can_access_vehicle($kendaraan_id)) {
    echo '<div class="alert alert-danger">Anda tidak memiliki akses ke kendaraan ini.</div>';
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

// Detect optional columns
$has_harga = function_exists('db_table_columns') && in_array('harga_per_liter', (array)db_table_columns('log_bahan_bakar'), true);
$has_metode = function_exists('db_table_columns') && in_array('metode_bayar', (array)db_table_columns('log_bahan_bakar'), true);
?>

<div class="page-header">
    <h1><i class="fas fa-gas-pump"></i> Detail Log BBM - <?= htmlspecialchars($vehicle['no_polisi']) ?> </h1>
    <div class="header-actions">
        <a href="index.php?page=log_bahan_bakar" class="btn btn-outline">&larr; Kembali</a>
        <?php if (can_operate() || in_array($current_role, ['user', 'driver'], true)): ?>
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
                        <th>KM Saat Isi</th>
                        <th>SPBU</th>
                        <th>User</th>
                        <th>Keterangan</th>
                        <?php if (can_operate() || in_array($current_role, ['user', 'driver'], true)): ?>
                            <th>Aksi</th>
                        <?php endif; ?>
                    </tr>
                </thead>
                <tbody>
                    <?php if ($logs->num_rows > 0): $i = 1; while ($row = $logs->fetch_assoc()): ?>
                        <tr>
                            <td><?= $i++ ?></td>
                            <td><?= !empty($row['tanggal_isi']) ? date('d/m/Y H:i', strtotime($row['tanggal_isi'])) : '-' ?></td>
                            <td><?= number_format($row['jumlah_liter'], 2) ?></td>
                            <td><?= $row['km_saat_isi'] ? number_format($row['km_saat_isi']) : '-' ?></td>
                            <td><?= htmlspecialchars($row['spbu']) ?></td>
                            <td><?= htmlspecialchars($row['user_name'] ?? '-') ?></td>
                            <td><?= htmlspecialchars($row['keterangan']) ?></td>
                            <?php if (can_operate() || in_array($current_role, ['user', 'driver'], true)): ?>
                                <td>
                                    <a href="index.php?page=log_bahan_bakar&action=edit&id=<?= (int)$row['id'] ?>" class="btn btn-sm btn-outline-primary" title="Edit Log BBM">
                                        <i class="fas fa-edit"></i>
                                    </a>
                                </td>
                            <?php endif; ?>
                        </tr>
                    <?php endwhile; else: ?>
                        <tr><td colspan="<?= (can_operate() || in_array($current_role, ['user', 'driver'], true)) ? '8' : '7' ?>" class="text-center">Belum ada log BBM untuk kendaraan ini.</td></tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>
