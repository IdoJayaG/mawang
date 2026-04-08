<?php
require_once '../config.php';
require_once '../config/db.php';

header('Content-Type: application/json');

if (!is_logged_in()) {
    echo json_encode(['success' => false, 'message' => 'User not logged in']);
    exit();
}

$peminjaman_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
if (!$peminjaman_id) {
    echo json_encode(['success' => false, 'message' => 'ID tidak valid']);
    exit();
}

// Detect columns and approver relationship dynamically
$cols_info = $mysqli->query("SHOW COLUMNS FROM peminjaman_kendaraan")->fetch_all(MYSQLI_ASSOC);
$cols_names = array_column($cols_info, 'Field');
$peminjam_col = in_array('pemohon_id', $cols_names) ? 'pemohon_id' : (in_array('peminjam_id', $cols_names) ? 'peminjam_id' : null);
$approver_col = null;
foreach (['approved_by','approval_by','approver_id','approved_by_id','approver'] as $c) {
    if (in_array($c, $cols_names)) { $approver_col = $c; break; }
}

$select_extra = '';
$join_approver = '';
if ($approver_col) {
    $select_extra = ', admin.nama_lengkap as admin_nama, p.' . $approver_col . ' as approver_col_val';
    $join_approver = ' LEFT JOIN pengguna admin ON p.' . $approver_col . ' = admin.id';
}

$join_user = '';
$user_select = '';
if ($peminjam_col) {
    $join_user = ' LEFT JOIN pengguna u ON p.' . $peminjam_col . ' = u.id';
    $user_select = ', u.nama_lengkap as nama, u.nrp_nip as nrp, u.pangkat, u.jabatan, u.email, u.no_hp as telepon';
}

$sql = "SELECT p.*, k.no_polisi, k.merk, k.tipe, k.tahun_pembuatan, k.jenis, k.warna, k.satker, k.no_reg"
    . $user_select . $select_extra .
    " FROM peminjaman_kendaraan p"
    . $join_user .
    " LEFT JOIN kendaraan k ON p.kendaraan_id = k.id"
    . $join_approver .
    " WHERE p.id = ?";

$stmt = $mysqli->prepare($sql);
$stmt->bind_param('i', $peminjaman_id);
$stmt->execute();
$res = $stmt->get_result();
if ($res->num_rows === 0) {
    echo json_encode(['success' => false, 'message' => 'Data tidak ditemukan']);
    exit();
}
$row = $res->fetch_assoc();
$stmt->close();

// Authorization: owner (pemohon/peminjam) or admin/operator
$current = get_logged_in_user();
$role = get_current_role();
$owner_id = (int)($row[$peminjam_col] ?? $row['created_by'] ?? 0);
if ($current['id'] !== $owner_id && !in_array($role, ['admin','operator'])) {
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit();
}

function h($v){ return htmlspecialchars($v ?? ''); }
function fmt($dt){ return !empty($dt) && $dt !== '0000-00-00 00:00:00' ? date('d/m/Y H:i', strtotime($dt)) : '-'; }

// Duration helper
function durasi($mulai, $selesai){
    if (empty($mulai) || empty($selesai)) return '-';
    try {
        $a = new DateTime($mulai); $b = new DateTime($selesai); $d = $a->diff($b);
        $out = [];
        if ($d->days) $out[] = $d->days . ' hari';
        if ($d->h) $out[] = $d->h . ' jam';
        if ($d->i) $out[] = $d->i . ' menit';
        return $out ? implode(' ', $out) : '0 menit';
    } catch (Throwable $e) { return '-'; }
}

ob_start();
?>
<div class="row">
    <div class="col-md-6">
        <div class="card mb-2">
            <div class="card-header bg-primary text-white"><strong>Peminjam</strong></div>
            <div class="card-body small">
                <p class="mb-1"><strong>Nama:</strong> <?= h($row['nama'] ?? '') ?></p>
                <p class="mb-1"><strong>NRP/NIP:</strong> <?= h($row['nrp'] ?? '') ?></p>
                <p class="mb-1"><strong>Pangkat/Jabatan:</strong> <?= h($row['pangkat'] ?? '') ?><?= !empty($row['jabatan']) ? ' | ' . h($row['jabatan']) : '' ?></p>
                <?php if (!empty($row['email']) || !empty($row['telepon'])): ?>
                <p class="mb-1"><strong>Kontak:</strong> <?= h($row['email'] ?? '') ?><?= !empty($row['telepon']) ? ' | ' . h($row['telepon']) : '' ?></p>
                <?php endif; ?>
            </div>
        </div>
    </div>
    <div class="col-md-6">
        <div class="card mb-2">
            <div class="card-header bg-success text-white"><strong>Kendaraan</strong></div>
            <div class="card-body small">
                <p class="mb-1"><strong>No. Polisi:</strong> <?= h($row['no_polisi']) ?></p>
                <p class="mb-1"><strong>Merk/Model:</strong> <?= h(($row['merk'] ?? '') . ' ' . ($row['tipe'] ?? '')) ?></p>
                <p class="mb-1"><strong>Jenis:</strong> <?= h($row['jenis'] ?? '') ?></p>
                <p class="mb-1"><strong>Satker:</strong> <?= h($row['satker'] ?? '') ?></p>
                <p class="mb-1"><strong>No. Reg:</strong> <?= h($row['no_reg'] ?? '') ?></p>
            </div>
        </div>
    </div>
</div>

<div class="card mb-2">
    <div class="card-header bg-info text-white"><strong>Detail Peminjaman</strong></div>
    <div class="card-body small">
        <p class="mb-1"><strong>Tanggal Mulai:</strong> <?= fmt($row['tanggal_mulai']) ?></p>
        <p class="mb-1"><strong>Tanggal Selesai:</strong> <?= fmt($row['tanggal_selesai']) ?></p>
        <p class="mb-1"><strong>Durasi:</strong> <?= durasi($row['tanggal_mulai'] ?? null, $row['tanggal_selesai'] ?? null) ?></p>
        <p class="mb-1"><strong>Status:</strong> <?= h($row['status']) ?></p>
        <p class="mb-1"><strong>Keperluan:</strong> <?= nl2br(h($row['keperluan'])) ?></p>
        <?php if (!empty($row['approved_at'])): ?>
        <p class="mb-1"><strong>Disetujui:</strong> <?= fmt($row['approved_at']) ?><?= !empty($row['admin_nama']) ? ' oleh ' . h($row['admin_nama']) : '' ?></p>
        <?php endif; ?>
    </div>
</div>

<?php if (!empty($row['catatan_admin'])): ?>
<div class="card mb-2">
    <div class="card-header bg-secondary text-white"><strong>Catatan Admin</strong></div>
    <div class="card-body small">
        <?= nl2br(h($row['catatan_admin'])) ?>
    </div>
</div>
<?php endif; ?>

<?php if (!empty($row['cancel_reason'])): ?>
<div class="card mb-2">
    <div class="card-header bg-danger text-white"><strong>Alasan Pembatalan</strong></div>
    <div class="card-body small text-danger">
        <?= nl2br(h($row['cancel_reason'])) ?>
    </div>
</div>
<?php endif; ?>

<?php if (in_array($role, ['admin','operator']) || $current['id'] === $owner_id): ?>
<div class="text-right mt-2">
    <?php if (strtolower($row['status']) === 'pending' && in_array($role, ['admin','operator'])): ?>
        <a href="index.php?page=persetujuan_peminjaman&action=approve&id=<?= (int)$row['id'] ?>" class="btn btn-sm btn-success">Setujui</a>
        <a href="index.php?page=persetujuan_peminjaman&action=reject&id=<?= (int)$row['id'] ?>" class="btn btn-sm btn-danger">Tolak</a>
    <?php endif; ?>
    <?php if (strtolower($row['status']) === 'ongoing' && in_array($role, ['admin','operator'])): ?>
        <button class="btn btn-sm btn-info" onclick="markCompleted(<?= (int)$row['id'] ?>)">Tandai Selesai</button>
    <?php endif; ?>
</div>
<?php endif; ?>

<?php
$html = ob_get_clean();
echo json_encode(['success' => true, 'html' => $html]);
exit();
?>
```
