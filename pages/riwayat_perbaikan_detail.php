<?php
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../lib/table_helpers.php';
require_login();

$current_role = get_current_role();
$current_user_id = get_current_user_id();
$can_crud = can_operate();

// Support either a single repair id or a kendaraan_id to show all repairs for a vehicle
$perbaikan_id = $_GET['id'] ?? null;
$vehicle_id = $_GET['kendaraan_id'] ?? null;

if ($vehicle_id) {
    // Vehicle-level page: load vehicle and all repairs
    $vid = (int)$vehicle_id;
    $vehicle_stmt = $conn->prepare("SELECT id, no_polisi, no_reg, merk, tipe, foto FROM kendaraan WHERE id = ? LIMIT 1");
    if ($vehicle_stmt) {
        $vehicle_stmt->bind_param('i', $vid);
        $vehicle_stmt->execute();
        $vehicle = $vehicle_stmt->get_result()->fetch_assoc();
        $vehicle_stmt->close();
    } else {
        $vehicle = null;
    }

    if (!$vehicle) {
        echo '<div class="page-header"><h1>Riwayat Perbaikan Kendaraan</h1></div>';
        echo '<div class="alert alert-warning">Kendaraan tidak ditemukan.</div>';
        echo '<a href="index.php?page=riwayat_perbaikan" class="btn btn-secondary">&larr; Kembali</a>';
        exit;
    }

    $rep_stmt = $conn->prepare("SELECT rp.*, p.nama_lengkap AS created_by_name FROM riwayat_perbaikan rp LEFT JOIN pengguna p ON rp.created_by = p.id WHERE rp.kendaraan_id = ? ORDER BY rp.tanggal_perbaikan DESC");
    $vehicle_repairs_all = [];
    if ($rep_stmt) {
        $rep_stmt->bind_param('i', $vid);
        $rep_stmt->execute();
        $res = $rep_stmt->get_result();
        if ($res) { $vehicle_repairs_all = $res->fetch_all(MYSQLI_ASSOC); }
        $rep_stmt->close();
    }
} else {
    // Single repair view: load repair details
    $rid = isset($perbaikan_id) ? (int)$perbaikan_id : 0;
    $repair = null;
    if ($rid > 0) {
        $stmt = $conn->prepare("SELECT rp.*, k.no_polisi, k.no_reg, k.merk, k.tipe FROM riwayat_perbaikan rp LEFT JOIN kendaraan k ON k.id = rp.kendaraan_id WHERE rp.id = ? LIMIT 1");
        if ($stmt) {
            $stmt->bind_param('i', $rid);
            $stmt->execute();
            $repair = $stmt->get_result()->fetch_assoc();
            $stmt->close();
        }
    }
    if (!$repair) {
        echo '<div class="page-header"><h1>Detail Perbaikan</h1></div>';
        echo '<div class="alert alert-warning">Data perbaikan tidak ditemukan.</div>';
        echo '<a href="index.php?page=riwayat_perbaikan" class="btn btn-secondary">&larr; Kembali</a>';
        exit;
    }
}
?>

<?php if (isset($vehicle)): ?>
    <div class="mb-3">
        <a href="index.php?page=riwayat_perbaikan" class="btn btn-outline-secondary">
            <i class="fas fa-arrow-left me-1"></i> Kembali
        </a>
    </div>
    <div class="card mb-4">
        <div class="card-body">
            <div class="vehicle-summary">
                <div><strong>Merk / Tipe:</strong> <?= htmlspecialchars((string)($vehicle['merk'] ?? '-')) ?> <?= htmlspecialchars((string)($vehicle['tipe'] ?? '')) ?></div>
                <div><strong>No. Registrasi:</strong> <?= htmlspecialchars((string)($vehicle['no_reg'] ?? '-')) ?></div>
            </div>
        </div>
    </div>

    <div class="card">
        <div class="card-header"><h4 class="card-title">Riwayat Perbaikan Kendaraan</h4></div>
        <div class="card-body">
            <div class="table-responsive">
                <table class="table table-striped table-hover">
                    <thead>
                        <tr>
                            <th>No</th>
                            <th>Tanggal</th>
                            <th>Jenis</th>
                            <th>Nama Barang</th>
                            <th>Banyaknya</th>
                            <th>Harga</th>
                            <th>Jumlah</th>
                            <th>Total (Rp)</th>
                            <th>Status</th>
                            <th>Aksi</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (count($vehicle_repairs_all) > 0): foreach ($vehicle_repairs_all as $i => $r): ?>
                            <?php
                                // compute first item and totals
                                $sumItem = 0.0; $firstNama='-'; $firstQty='-'; $firstHarga='-'; $firstJumlah='-';
                                if ($its = $conn->prepare('SELECT nama_barang, qty, satuan, harga FROM riwayat_perbaikan_items WHERE perbaikan_id = ? ORDER BY urutan ASC, id ASC')) {
                                    $its->bind_param('i', $r['id']);
                                    $its->execute();
                                    $res = $its->get_result();
                                    $rows = $res ? $res->fetch_all(MYSQLI_ASSOC) : [];
                                    $its->close();
                                    if (!empty($rows)) {
                                        $it0 = $rows[0];
                                        $qtyLabel = (string)($it0['qty'] ?? 0);
                                        if (!empty($it0['satuan'])) { $qtyLabel .= ' ' . (string)$it0['satuan']; }
                                        $firstNama = (string)$it0['nama_barang'];
                                        $firstQty = $qtyLabel;
                                        $firstHarga = (float)$it0['harga'] > 0 ? 'Rp ' . number_format((float)$it0['harga']) : '-';
                                        $firstJumlah = 'Rp ' . number_format(((float)$it0['qty']) * ((float)$it0['harga']));
                                        foreach ($rows as $it) { $sumItem += ((float)$it['qty']) * ((float)$it['harga']); }
                                    }
                                }
                                $total_tampil = $sumItem > 0 ? $sumItem : (float)($r['biaya'] ?? 0);
                            ?>
                            <tr>
                                <td><?= $i + 1 ?></td>
                                <td><?= !empty($r['tanggal_perbaikan']) ? date('d/m/Y', strtotime($r['tanggal_perbaikan'])) : '-' ?></td>
                                <td><?= htmlspecialchars((string)($r['jenis_perbaikan'] ?? '-')) ?></td>
                                <td><?= htmlspecialchars($firstNama) ?></td>
                                <td><?= htmlspecialchars($firstQty) ?></td>
                                <td><?= htmlspecialchars($firstHarga) ?></td>
                                <td><?= htmlspecialchars($firstJumlah) ?></td>
                                <td><?= ($total_tampil > 0) ? 'Rp ' . number_format($total_tampil) : '-' ?></td>
                                <td><?= htmlspecialchars((string)($r['status'] ?? '-')) ?></td>
                                <td>
                                    <a href="index.php?page=riwayat_perbaikan_detail&id=<?= $r['id'] ?>" class="btn btn-sm btn-primary"><i class="fas fa-eye"></i></a>
                                    <?php if ($can_crud): ?><a href="index.php?page=riwayat_perbaikan&action=edit&id=<?= $r['id'] ?>" class="btn btn-sm btn-warning"><i class="fas fa-edit"></i></a><?php endif; ?>
                                </td>
                            </tr>
                        <?php endforeach; else: ?>
                            <tr><td colspan="10" class="text-center">Belum ada riwayat perbaikan untuk kendaraan ini.</td></tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
<?php else: ?>
    <div class="mb-3">
        <a href="index.php?page=riwayat_perbaikan" class="btn btn-outline-secondary">
            <i class="fas fa-arrow-left me-1"></i> Kembali
        </a>
    </div>
    <div class="card mb-3">
        <div class="card-body">
            <div class="detail-grid">
                <div class="detail-group">
                    <label>Kendaraan</label>
                    <div class="detail-value">
                        <strong>
                            <?= htmlspecialchars((string)($repair['no_polisi'] ?? '')) ?>
                            <?php if (!empty($repair['no_reg'])): ?>
                                <small class="text-muted">(Reg: <?= htmlspecialchars((string)$repair['no_reg']) ?>)</small>
                            <?php endif; ?>
                        </strong>
                        <br>
                        <?= htmlspecialchars((string)($repair['merk'] ?? '')) ?> <?= htmlspecialchars((string)($repair['tipe'] ?? '')) ?>
                    </div>
                </div>

                <div class="detail-group">
                    <label>Tanggal Perbaikan</label>
                    <div class="detail-value"><?= !empty($repair['tanggal_perbaikan']) ? date('d/m/Y', strtotime($repair['tanggal_perbaikan'])) : '-' ?></div>
                </div>

                <div class="detail-group">
                    <label>Jenis Perbaikan</label>
                    <div class="detail-value">
                        <span class="badge badge-light text-dark"><?= htmlspecialchars((string)($repair['jenis_perbaikan'] ?? '')) ?></span>
                    </div>
                </div>

                <div class="detail-group">
                    <label>Status</label>
                    <div class="detail-value">
                        <span class="badge badge-<?= strtolower(str_replace(' ', '-', (string)($repair['status'] ?? ''))) ?>">
                            <?= htmlspecialchars((string)($repair['status'] ?? '')) ?>
                        </span>
                    </div>
                </div>

                <div class="detail-group">
                    <label>Deskripsi Kerusakan</label>
                    <div class="detail-value"><?= nl2br(htmlspecialchars((string)($repair['deskripsi_kerusakan'] ?? ''))) ?></div>
                </div>

                <div class="detail-group">
                    <label>Deskripsi Perbaikan</label>
                    <div class="detail-value"><?= nl2br(htmlspecialchars((string)($repair['catatan'] ?? $repair['deskripsi_perbaikan'] ?? ''))) ?></div>
                </div>
            </div>
        </div>
    </div>

    <div class="card">
        <div class="card-header">Rincian Barang</div>
        <div class="card-body">
            <?php
            $items = [];
            if ($stmt = $conn->prepare('SELECT urutan, nama_barang, qty, satuan, harga FROM riwayat_perbaikan_items WHERE perbaikan_id = ? ORDER BY urutan ASC, id ASC')) {
                $stmt->bind_param('i', $repair['id']);
                $stmt->execute();
                $res = $stmt->get_result();
                if ($res) { $items = $res->fetch_all(MYSQLI_ASSOC); }
                $stmt->close();
            }
            $grand = 0.0;
            foreach ($items as $it) { $grand += ((float)$it['qty']) * ((float)$it['harga']); }
            ?>
            <div class="table-responsive">
                <table class="table table-bordered table-sm mb-0">
                    <thead class="thead-light">
                        <tr>
                            <th style="width:40px;">NO</th>
                            <th>NAMA BARANG</th>
                            <th style="width:160px;">BANYAKNYA</th>
                            <th style="width:160px;">HARGA</th>
                            <th style="width:180px;">JUMLAH</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (!empty($items)): foreach ($items as $idx => $it): ?>
                            <?php $jumlah = ((float)$it['qty']) * ((float)$it['harga']); ?>
                            <tr>
                                <td><?= $idx + 1 ?></td>
                                <td><?= htmlspecialchars((string)($it['nama_barang'] ?? '-')) ?></td>
                                <td>
                                    <?= htmlspecialchars((string)($it['qty'] ?? 0)) ?>
                                    <?= !empty($it['satuan']) ? htmlspecialchars(' ' . (string)$it['satuan']) : '' ?>
                                </td>
                                <td><?= (float)$it['harga'] > 0 ? 'Rp ' . number_format((float)$it['harga']) : '-' ?></td>
                                <td><?= $jumlah > 0 ? 'Rp ' . number_format($jumlah) : '-' ?></td>
                            </tr>
                        <?php endforeach; else: ?>
                            <tr>
                                <td colspan="5" class="text-center text-muted">Tidak ada rincian barang</td>
                            </tr>
                        <?php endif; ?>
                    </tbody>
                    <tfoot>
                        <tr>
                            <th colspan="4" class="text-right">TOTAL</th>
                            <?php $tampilTotal = $grand > 0 ? $grand : (float)($repair['biaya'] ?? 0); ?>
                            <th><?= $tampilTotal > 0 ? 'Rp ' . number_format($tampilTotal) : '-' ?></th>
                        </tr>
                    </tfoot>
                </table>
            </div>
        </div>
    </div>
<?php endif; ?>

<?php
// End of file
