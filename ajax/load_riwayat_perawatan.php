<?php
header('Content-Type: application/json; charset=utf-8');

// Minimal AJAX endpoint to return riwayat perawatan table HTML
// Load application config (sets $mysqli and includes auth)
require_once __DIR__ . '/../config.php';
// auth already included by config.php but ensure functions exist
if (!function_exists('require_login')) {
    require_once __DIR__ . '/../includes/auth.php';
}
require_login();

// Ensure $mysqli is available
if (!isset($mysqli) || !$mysqli) {
    echo json_encode(['success' => false, 'message' => 'Database connection not available']);
    exit;
}

$page = isset($_GET['page']) ? max(1, (int)$_GET['page']) : 1;
$limit = 10;
$offset = ($page - 1) * $limit;

// Prepare query for riwayat_perawatan table
 $query = "SELECT rp.*, k.no_polisi, k.merk, k.tipe, COALESCE(u.nama_lengkap, rp.mekanik, '') as teknisi_nama
     FROM riwayat_perawatan rp
     LEFT JOIN kendaraan k ON rp.kendaraan_id = k.id
     LEFT JOIN pengguna u ON rp.created_by = u.id
         ORDER BY rp.tanggal_perawatan DESC
         LIMIT ? OFFSET ?";

$stmt = $mysqli->prepare($query);
if (!$stmt) {
    echo json_encode(['success' => false, 'message' => 'Query prepare failed']);
    exit;
}
$stmt->bind_param('ii', $limit, $offset);
$stmt->execute();
$result = $stmt->get_result();

// Build HTML
ob_start();
?>
<div class="table-responsive">
    <table class="table table-striped">
        <thead>
            <tr>
                <th>No</th>
                <th>Tanggal</th>
                <th>Kendaraan</th>
                <th>Jenis Perawatan</th>
                <th>Biaya</th>
                <th>KM</th>
                <th>Status</th>
                <th>Dikerjakan Oleh</th>
                <th>Aksi</th>
            </tr>
        </thead>
        <tbody>
<?php
$no = $offset + 1;
if ($result && $result->num_rows > 0):
    while ($row = $result->fetch_assoc()):
?>
            <tr>
                <td><?php echo $no++; ?></td>
                <td>
                    <strong><?php echo date('d/m/Y', strtotime($row['tanggal_perawatan'])); ?></strong>
                    <br><small class="text-muted"><?php echo date('H:i', strtotime($row['created_at'])); ?></small>
                </td>
                <td>
                    <strong><?php echo htmlspecialchars($row['no_polisi']); ?></strong><br>
                    <small class="text-muted"><?php echo htmlspecialchars($row['merk'] . ' ' . $row['tipe']); ?></small>
                </td>
                <td>
                    <strong><?php echo htmlspecialchars($row['jenis_perawatan']); ?></strong>
                    <?php if (!empty($row['deskripsi'])): ?>
                        <br><small class="text-muted"><?php echo htmlspecialchars($row['deskripsi']); ?></small>
                    <?php endif; ?>
                </td>
                <td>
                    <?php if ($row['biaya'] > 0): ?>
                        <strong>Rp <?php echo number_format($row['biaya']); ?></strong>
                    <?php else: ?>
                        <span class="text-muted">-</span>
                    <?php endif; ?>
                </td>
                <td>
                    <?php echo $row['km_saat_perawatan'] ? number_format($row['km_saat_perawatan']) . ' KM' : '<span class="text-muted">-</span>'; ?>
                </td>
                <td>
                    <span class="badge badge-success">
                        <i class="fas fa-check-circle"></i>
                        <?php echo htmlspecialchars($row['status']); ?>
                    </span>
                </td>
                <td>
                    <?php if ($row['mekanik']): ?>
                        <?php echo htmlspecialchars($row['mekanik']); ?>
                    <?php elseif ($row['teknisi_nama']): ?>
                        <?php echo htmlspecialchars($row['teknisi_nama']); ?>
                    <?php else: ?>
                        <span class="text-muted">Tidak tercatat</span>
                    <?php endif; ?>
                </td>
                <td>
                    <button class="btn btn-sm btn-outline-info btn-detail" data-id="<?php echo $row['id']; ?>" title="Lihat Detail">
                        <i class="fas fa-eye"></i>
                    </button>
                </td>
            </tr>
<?php
    endwhile;
else:
?>
            <tr>
                <td colspan="9" class="text-center text-muted">
                    <div class="py-4">
                        <i class="fas fa-history fa-3x mb-3 text-muted"></i>
                        <h5>Belum ada riwayat perawatan</h5>
                        <p>Riwayat akan muncul setelah jadwal perawatan diselesaikan</p>
                    </div>
                </td>
            </tr>
<?php
endif;
?>
        </tbody>
    </table>
</div>
<?php
$html = ob_get_clean();

// Free statement
$stmt->close();

echo json_encode(['success' => true, 'html' => $html]);
