<?php
if (!is_logged_in() || !is_admin_like()) {
    header('Location: index.php?page=403');
    exit;
}

// ── Status / source badge helpers ─────────────────────────────
function rp_status_badge($status, $sumber) {
    if ($sumber === 'surat_tugas') {
        $map = [
            'Disetujui'        => ['success',   'Disetujui'],
            'Dalam Perjalanan' => ['warning',   'Dalam Perjalanan'],
            'Selesai'          => ['primary',   'Selesai'],
        ];
    } else {
        $map = [
            'Approved'  => ['success',   'Disetujui'],
            'Ongoing'   => ['info',      'Berlangsung'],
            'Completed' => ['primary',   'Selesai'],
        ];
    }
    $b = $map[$status] ?? ['secondary', $status];
    return '<span class="badge bg-' . $b[0] . '">' . htmlspecialchars($b[1]) . '</span>';
}

function rp_sumber_badge($sumber) {
    return match($sumber) {
        'surat_tugas' => '<span class="badge bg-info text-dark"><i class="fas fa-file-signature me-1"></i>Surat Tugas</span>',
        'peminjaman'  => '<span class="badge bg-primary"><i class="fas fa-car me-1"></i>Peminjaman</span>',
        default       => '<span class="badge bg-secondary">' . htmlspecialchars($sumber) . '</span>',
    };
}

// ── Detail view ────────────────────────────────────────────────
$action = $_GET['action'] ?? 'list';
$rec_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
$source = $_GET['source'] ?? 'peminjaman';

if ($action === 'view' && $rec_id > 0) {
    if ($source === 'surat_tugas') {
        $stmt = $mysqli->prepare("
            SELECT st.*, k.no_reg, k.no_polisi, k.merk, k.tipe, k.jenis, k.warna,
                   p.nama_lengkap AS pemakai, p.nrp_nip AS pemakai_nip,
                   ua.username, apv.nama_lengkap AS approver_name
            FROM surat_tugas st
            JOIN kendaraan k ON st.kendaraan_id = k.id
            LEFT JOIN pengguna p ON st.pengguna_id = p.id
            LEFT JOIN user_account ua ON p.id = ua.pengguna_id
            LEFT JOIN pengguna apv ON st.approval_pimpinan_by = apv.id
            WHERE st.id = ?
        ");
    } else {
        $stmt = $mysqli->prepare("
            SELECT pk.*, k.no_reg, k.no_polisi, k.merk, k.tipe, k.jenis, k.warna,
                   p.nama_lengkap AS pemakai, p.nrp_nip AS pemakai_nip,
                   ua.username,
                   drv.nama_lengkap AS driver_name, drv.nrp_nip AS driver_nip,
                   apv.nama_lengkap AS approver_name
            FROM peminjaman_kendaraan pk
            JOIN kendaraan k ON pk.kendaraan_id = k.id
            LEFT JOIN pengguna p ON pk.peminjam_id = p.id
            LEFT JOIN user_account ua ON p.id = ua.pengguna_id
            LEFT JOIN pengguna drv ON pk.driver_id = drv.id
            LEFT JOIN pengguna apv ON pk.approval_by = apv.id
            WHERE pk.id = ?
        ");
    }
    $stmt->bind_param('i', $rec_id);
    $stmt->execute();
    $rec = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    if (!$rec) {
        header('Location: index.php?page=riwayat_pemakaian');
        exit;
    }

    $label_kend  = trim(($rec['no_reg'] ?? '') ?: ($rec['no_polisi'] ?? '-'));
    $tgl_mulai   = $source === 'surat_tugas' ? ($rec['tanggal_berangkat'] ?? '') : ($rec['tanggal_mulai'] ?? '');
    $tgl_selesai = $source === 'surat_tugas' ? ($rec['tanggal_kembali']  ?? '') : ($rec['tanggal_selesai'] ?? '');
    $nomor_ref   = $rec['nomor_surat'] ?? '-';
    $tgl_end_safe = $tgl_selesai ?: $tgl_mulai;
    // Fetch total km from laporan_perjalanan for this trip's kendaraan + date window
    $jarak_lp = null;
    if ($tgl_mulai && !empty($rec['kendaraan_id'])) {
        $lp_q = $mysqli->prepare("SELECT COALESCE(SUM(jarak_km), 0) AS total FROM laporan_perjalanan WHERE kendaraan_id = ? AND tanggal BETWEEN ? AND ?");
        if ($lp_q) {
            $kend_id_det = (int)$rec['kendaraan_id'];
            $tgl_s_det   = date('Y-m-d', strtotime($tgl_mulai));
            $tgl_e_det   = $tgl_end_safe ? date('Y-m-d', strtotime($tgl_end_safe)) : $tgl_s_det;
            $lp_q->bind_param('iss', $kend_id_det, $tgl_s_det, $tgl_e_det);
            $lp_q->execute();
            $lp_row = $lp_q->get_result()->fetch_assoc();
            if (isset($lp_row['total']) && (int)$lp_row['total'] > 0) {
                $jarak_lp = (int)$lp_row['total'];
            }
            $lp_q->close();
        }
    }
    ?>
    <div class="page-header">
        <h1><i class="fas fa-history me-2"></i>Detail Pemakaian — <?= htmlspecialchars($label_kend) ?></h1>
    </div>
    <div class="content-container">
        <div class="mb-3 d-flex gap-2 align-items-center">
            <a href="index.php?page=riwayat_pemakaian" class="btn btn-secondary btn-sm"><i class="fas fa-arrow-left me-1"></i>Kembali</a>
            <?= rp_sumber_badge($source) ?>
            <?php if ($source === 'surat_tugas'): ?>
                <a href="index.php?page=surat_tugas&action=view&id=<?= $rec_id ?>" class="btn btn-outline-primary btn-sm"><i class="fas fa-external-link-alt me-1"></i>Buka Surat Tugas</a>
            <?php else: ?>
                <a href="index.php?page=persetujuan_peminjaman" class="btn btn-outline-primary btn-sm"><i class="fas fa-external-link-alt me-1"></i>Persetujuan Peminjaman</a>
            <?php endif; ?>
        </div>

        <div class="card shadow-sm">
            <div class="card-header bg-dark text-white d-flex justify-content-between align-items-center">
                <strong><?= htmlspecialchars($rec['merk'] ?? '') ?> <?= htmlspecialchars($rec['tipe'] ?? '') ?> — <?= htmlspecialchars($label_kend) ?></strong>
                <span><?= rp_status_badge($rec['status'] ?? '', $source) ?></span>
            </div>
            <div class="card-body">
                <div class="row g-3">
                    <div class="col-md-4">
                        <label class="small text-muted fw-bold">Nomor Referensi</label>
                        <div><?= htmlspecialchars($nomor_ref) ?></div>
                    </div>
                    <div class="col-md-4">
                        <label class="small text-muted fw-bold">Pemakai / Pemohon</label>
                        <div><?= htmlspecialchars($rec['pemakai'] ?? '-') ?></div>
                        <?php if (!empty($rec['pemakai_nip'])): ?>
                            <div class="small text-muted">NIP: <?= htmlspecialchars($rec['pemakai_nip']) ?></div>
                        <?php endif; ?>
                    </div>
                    <?php if (!empty($rec['driver_name'])): ?>
                    <div class="col-md-4">
                        <label class="small text-muted fw-bold">Driver</label>
                        <div><?= htmlspecialchars($rec['driver_name']) ?></div>
                        <?php if (!empty($rec['driver_nip'])): ?>
                            <div class="small text-muted">NIP: <?= htmlspecialchars($rec['driver_nip']) ?></div>
                        <?php endif; ?>
                    </div>
                    <?php endif; ?>
                    <div class="col-md-4">
                        <label class="small text-muted fw-bold">Tanggal Mulai</label>
                        <div><?= $tgl_mulai ? date('d/m/Y', strtotime($tgl_mulai)) : '-' ?></div>
                    </div>
                    <div class="col-md-4">
                        <label class="small text-muted fw-bold">Tanggal Selesai</label>
                        <div><?= $tgl_selesai ? date('d/m/Y', strtotime($tgl_selesai)) : '-' ?></div>
                    </div>
                    <div class="col-md-4">
                        <label class="small text-muted fw-bold">Kendaraan</label>
                        <div><?= htmlspecialchars(($rec['merk'] ?? '') . ' ' . ($rec['tipe'] ?? '')) ?></div>
                        <div class="small text-muted">No. Reg: <?= htmlspecialchars($rec['no_reg'] ?? '-') ?> | <?= htmlspecialchars($rec['jenis'] ?? '') ?></div>
                    </div>
                    <div class="col-md-6">
                        <label class="small text-muted fw-bold">Tujuan</label>
                        <div><?= htmlspecialchars($rec['tujuan'] ?? '-') ?></div>
                    </div>
                    <div class="col-md-6">
                        <label class="small text-muted fw-bold">Keperluan</label>
                        <div><?= htmlspecialchars($rec['keperluan'] ?? '-') ?></div>
                    </div>
                    <div class="col-md-3">
                        <label class="small text-muted fw-bold">KM Awal</label>
                        <div><?= is_numeric($rec['km_awal'] ?? ($rec['km_berangkat'] ?? null)) ? number_format($rec['km_awal'] ?? $rec['km_berangkat']) . ' km' : '-' ?></div>
                    </div>
                    <div class="col-md-3">
                        <label class="small text-muted fw-bold">KM Akhir</label>
                        <div><?= is_numeric($rec['km_akhir'] ?? ($rec['km_kembali'] ?? null)) ? number_format($rec['km_akhir'] ?? $rec['km_kembali']) . ' km' : '-' ?></div>
                    </div>
                    <div class="col-md-3">
                        <label class="small text-muted fw-bold">Jarak Tempuh</label>
                        <?php if ($jarak_lp !== null): ?>
                            <div class="fw-semibold text-success"><?= number_format($jarak_lp) ?> km</div>
                            <div class="text-muted fs-xs">dari laporan perjalanan</div>
                        <?php else: ?>
                            <div class="text-muted">—</div>
                        <?php endif; ?>
                    </div>
                    <?php if ($source === 'surat_tugas'): ?>
                    <div class="col-md-3">
                        <label class="small text-muted fw-bold">Estimasi BBM</label>
                        <div><?= is_numeric($rec['estimasi_bbm'] ?? null) ? number_format($rec['estimasi_bbm'], 1) . ' L' : '-' ?></div>
                    </div>
                    <div class="col-md-3">
                        <label class="small text-muted fw-bold">BBM Terpakai</label>
                        <div><?= is_numeric($rec['bbm_terpakai'] ?? null) ? number_format($rec['bbm_terpakai'], 1) . ' L' : '-' ?></div>
                    </div>
                    <?php else: ?>
                    <div class="col-md-3">
                        <label class="small text-muted fw-bold">BBM Awal</label>
                        <div><?= is_numeric($rec['bbm_awal'] ?? null) ? number_format($rec['bbm_awal'], 1) . ' L' : '-' ?></div>
                    </div>
                    <div class="col-md-3">
                        <label class="small text-muted fw-bold">BBM Akhir</label>
                        <div><?= is_numeric($rec['bbm_akhir'] ?? null) ? number_format($rec['bbm_akhir'], 1) . ' L' : '-' ?></div>
                    </div>
                    <?php endif; ?>
                    <?php if (!empty($rec['approver_name'])): ?>
                    <div class="col-md-6">
                        <label class="small text-muted fw-bold">Disetujui Oleh</label>
                        <div><?= htmlspecialchars($rec['approver_name']) ?></div>
                        <?php if ($source === 'surat_tugas' && !empty($rec['approval_pimpinan_at'])): ?>
                            <div class="small text-muted"><?= date('d/m/Y H:i', strtotime($rec['approval_pimpinan_at'])) ?></div>
                        <?php elseif (!empty($rec['tanggal_approval'])): ?>
                            <div class="small text-muted"><?= date('d/m/Y H:i', strtotime($rec['tanggal_approval'])) ?></div>
                        <?php endif; ?>
                    </div>
                    <?php endif; ?>
                    <?php if ($source === 'surat_tugas' && !empty($rec['laporan_perjalanan'])): ?>
                    <div class="col-12">
                        <label class="small text-muted fw-bold">Laporan Perjalanan</label>
                        <div class="p-2 bg-light rounded"><?= nl2br(htmlspecialchars($rec['laporan_perjalanan'])) ?></div>
                    </div>
                    <?php endif; ?>
                    <?php if ($source !== 'surat_tugas' && !empty($rec['catatan_pengembalian'])): ?>
                    <div class="col-12">
                        <label class="small text-muted fw-bold">Catatan Pengembalian</label>
                        <div class="p-2 bg-light rounded"><?= nl2br(htmlspecialchars($rec['catatan_pengembalian'])) ?></div>
                    </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
    <?php
    return;
}

// ── List view ──────────────────────────────────────────────────
$pg       = max(1, (int)($_GET['page_num'] ?? 1));
$limit    = 25;
$offset   = ($pg - 1) * $limit;

$filter_q          = trim($_GET['q'] ?? '');
$filter_kendaraan  = (int)($_GET['kendaraan_id'] ?? 0);
$filter_sumber     = $_GET['sumber'] ?? '';
$filter_date_from  = $_GET['date_from'] ?? '';
$filter_date_to    = $_GET['date_to']   ?? '';

// ── Build inner UNION parts ────────────────────────────────────
// peminjaman_kendaraan: standalone only (surat_tugas_id IS NULL) to avoid duplicates with the surat_tugas branch
// All string expressions use COLLATE utf8mb4_unicode_ci to resolve the mismatch between
// peminjaman_kendaraan (utf8mb4_unicode_ci) and surat_tugas/kendaraan/pengguna (utf8mb4_general_ci).
$_c = 'COLLATE utf8mb4_unicode_ci';

$pk_sql = "SELECT 'peminjaman' {$_c} AS sumber, pk.id AS record_id, pk.kendaraan_id,
    COALESCE(pk.nomor_surat,'') {$_c} AS nomor_ref,
    DATE(pk.tanggal_mulai) AS tanggal,
    pk.tanggal_mulai,
    pk.tanggal_selesai,
    COALESCE(CONVERT(p.nama_lengkap USING utf8mb4),'-') {$_c} AS pemakai,
    COALESCE(CONVERT(p.nrp_nip USING utf8mb4),'') {$_c} AS pemakai_nip,
    COALESCE(CONVERT(drv.nama_lengkap USING utf8mb4),'') {$_c} AS driver_name,
    COALESCE(pk.tujuan,'') {$_c} AS tujuan,
    COALESCE(pk.keperluan,'') {$_c} AS keperluan,
    CONVERT(COALESCE(pk.status,'') USING utf8mb4) {$_c} AS status,
    pk.km_awal, pk.km_akhir,
    COALESCE(CONVERT(k.no_reg USING utf8mb4),'') {$_c} AS no_reg,
    COALESCE(CONVERT(k.no_polisi USING utf8mb4),'') {$_c} AS no_polisi,
    COALESCE(CONVERT(k.merk USING utf8mb4),'') {$_c} AS merk,
    COALESCE(CONVERT(k.tipe USING utf8mb4),'') {$_c} AS tipe,
    (SELECT SUM(lp.jarak_km) FROM laporan_perjalanan lp
     WHERE lp.kendaraan_id = pk.kendaraan_id
       AND lp.tanggal BETWEEN DATE(pk.tanggal_mulai) AND DATE(pk.tanggal_selesai)) AS jarak_lp
FROM peminjaman_kendaraan pk
JOIN kendaraan k ON pk.kendaraan_id = k.id
LEFT JOIN pengguna p ON pk.peminjam_id = p.id
LEFT JOIN pengguna drv ON pk.driver_id = drv.id
WHERE pk.status NOT IN ('Pending','Rejected','Cancelled') AND (pk.surat_tugas_id IS NULL OR pk.surat_tugas_id = 0)";

// surat_tugas: all non-draft/cancelled (includes linked peminjaman via the surat_tugas record)
$st_sql = "SELECT 'surat_tugas' {$_c} AS sumber, st.id AS record_id, st.kendaraan_id,
    COALESCE(CONVERT(st.nomor_surat USING utf8mb4),'') {$_c} AS nomor_ref,
    st.tanggal_berangkat AS tanggal,
    CAST(CONCAT(st.tanggal_berangkat,' 00:00:00') AS DATETIME) AS tanggal_mulai,
    CAST(CONCAT(COALESCE(st.tanggal_kembali,st.tanggal_berangkat),' 23:59:59') AS DATETIME) AS tanggal_selesai,
    COALESCE(CONVERT(p.nama_lengkap USING utf8mb4),'-') {$_c} AS pemakai,
    COALESCE(CONVERT(p.nrp_nip USING utf8mb4),'') {$_c} AS pemakai_nip,
    '' {$_c} AS driver_name,
    COALESCE(CONVERT(st.tujuan USING utf8mb4),'') {$_c} AS tujuan,
    COALESCE(CONVERT(st.keperluan USING utf8mb4),'') {$_c} AS keperluan,
    CONVERT(COALESCE(st.status,'') USING utf8mb4) {$_c} AS status,
    st.km_berangkat AS km_awal, st.km_kembali AS km_akhir,
    COALESCE(CONVERT(k.no_reg USING utf8mb4),'') {$_c} AS no_reg,
    COALESCE(CONVERT(k.no_polisi USING utf8mb4),'') {$_c} AS no_polisi,
    COALESCE(CONVERT(k.merk USING utf8mb4),'') {$_c} AS merk,
    COALESCE(CONVERT(k.tipe USING utf8mb4),'') {$_c} AS tipe,
    (SELECT SUM(lp.jarak_km) FROM laporan_perjalanan lp
     WHERE lp.kendaraan_id = st.kendaraan_id
       AND lp.tanggal BETWEEN st.tanggal_berangkat AND COALESCE(st.tanggal_kembali, st.tanggal_berangkat)) AS jarak_lp
FROM surat_tugas st
JOIN kendaraan k ON st.kendaraan_id = k.id
LEFT JOIN pengguna p ON st.pengguna_id = p.id
WHERE st.status NOT IN ('Draft','Dibatalkan')";

// Select union based on sumber filter
if ($filter_sumber === 'peminjaman') {
    $inner_sql = "($pk_sql)";
} elseif ($filter_sumber === 'surat_tugas') {
    $inner_sql = "($st_sql)";
} else {
    $inner_sql = "($pk_sql UNION ALL $st_sql)";
}

// ── Build outer WHERE ──────────────────────────────────────────
$outer_where  = [];
$outer_params = [];
$outer_types  = '';

if ($filter_q !== '') {
    $q = '%' . $filter_q . '%';
    $outer_where[] = "(no_reg LIKE ? OR no_polisi LIKE ? OR merk LIKE ? OR tipe LIKE ? OR pemakai LIKE ? OR nomor_ref LIKE ? OR tujuan LIKE ?)";
    for ($i = 0; $i < 7; $i++) { $outer_params[] = $q; }
    $outer_types .= 'sssssss';
}
if ($filter_kendaraan > 0) {
    $outer_where[] = "kendaraan_id = ?";
    $outer_params[] = $filter_kendaraan;
    $outer_types .= 'i';
}
if ($filter_date_from !== '') {
    $outer_where[] = "tanggal >= ?";
    $outer_params[] = $filter_date_from;
    $outer_types .= 's';
}
if ($filter_date_to !== '') {
    $outer_where[] = "tanggal <= ?";
    $outer_params[] = $filter_date_to;
    $outer_types .= 's';
}

$where_sql = !empty($outer_where) ? ' WHERE ' . implode(' AND ', $outer_where) : '';

// ── Count ──────────────────────────────────────────────────────
$count_sql = "SELECT COUNT(*) AS total FROM {$inner_sql} AS combined{$where_sql}";
$count_stmt = $mysqli->prepare($count_sql);
if ($outer_types !== '') {
    $count_stmt->bind_param($outer_types, ...$outer_params);
}
$count_stmt->execute();
$total = (int)$count_stmt->get_result()->fetch_assoc()['total'];
$count_stmt->close();

$total_pages = max(1, (int)ceil($total / $limit));

// ── Fetch page ─────────────────────────────────────────────────
$data_sql = "SELECT * FROM {$inner_sql} AS combined{$where_sql} ORDER BY tanggal DESC, record_id DESC LIMIT ? OFFSET ?";
$data_stmt = $mysqli->prepare($data_sql);
$data_params = array_merge($outer_params, [$limit, $offset]);
$data_types  = $outer_types . 'ii';
$data_stmt->bind_param($data_types, ...$data_params);
$data_stmt->execute();
$rows = $data_stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$data_stmt->close();

// Kendaraan dropdown for filter
$kendaraan_list = $mysqli->query("SELECT id, COALESCE(no_reg, no_polisi, '') AS label, merk, tipe FROM kendaraan ORDER BY COALESCE(no_reg, no_polisi)")?->fetch_all(MYSQLI_ASSOC) ?? [];

// ── Build pagination URL ───────────────────────────────────────
function rp_page_url($pg_num, $extra = []) {
    $params = array_merge(['page' => 'riwayat_pemakaian', 'page_num' => $pg_num], $extra);
    return 'index.php?' . http_build_query($params);
}

$url_extra = array_filter([
    'q'           => $filter_q,
    'kendaraan_id'=> $filter_kendaraan ?: '',
    'sumber'      => $filter_sumber,
    'date_from'   => $filter_date_from,
    'date_to'     => $filter_date_to,
]);
?>

<div class="page-header">
    <h1><i class="fas fa-history me-2"></i>Riwayat Pemakaian Kendaraan</h1>
</div>

<div class="content-container">

    <!-- Filter bar -->
    <div class="card shadow-sm mb-3">
        <div class="card-body py-2">
            <form method="get" class="row g-2 align-items-end">
                <input type="hidden" name="page" value="riwayat_pemakaian">
                <div class="col-md-3">
                    <input type="text" name="q" value="<?= htmlspecialchars($filter_q) ?>"
                           placeholder="Cari kendaraan / pengguna / nomor surat" class="form-control form-control-sm">
                </div>
                <div class="col-md-2">
                    <select name="kendaraan_id" class="form-select form-select-sm">
                        <option value="">Semua Kendaraan</option>
                        <?php foreach ($kendaraan_list as $k): ?>
                            <option value="<?= $k['id'] ?>" <?= $filter_kendaraan == $k['id'] ? 'selected' : '' ?>>
                                <?= htmlspecialchars($k['label'] ?: ($k['merk'] . ' ' . $k['tipe'])) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-md-2">
                    <select name="sumber" class="form-select form-select-sm">
                        <option value="" <?= $filter_sumber === '' ? 'selected' : '' ?>>Semua Sumber</option>
                        <option value="surat_tugas" <?= $filter_sumber === 'surat_tugas' ? 'selected' : '' ?>>Surat Tugas</option>
                        <option value="peminjaman" <?= $filter_sumber === 'peminjaman' ? 'selected' : '' ?>>Peminjaman</option>
                    </select>
                </div>
                <div class="col-md-2">
                    <input type="date" name="date_from" value="<?= htmlspecialchars($filter_date_from) ?>"
                           class="form-control form-control-sm" title="Dari tanggal">
                </div>
                <div class="col-md-2">
                    <input type="date" name="date_to" value="<?= htmlspecialchars($filter_date_to) ?>"
                           class="form-control form-control-sm" title="Sampai tanggal">
                </div>
                <div class="col-md-1 d-flex gap-1">
                    <button class="btn btn-primary btn-sm" type="submit"><i class="fas fa-filter"></i></button>
                    <?php if ($filter_q || $filter_kendaraan || $filter_sumber || $filter_date_from || $filter_date_to): ?>
                        <a href="index.php?page=riwayat_pemakaian" class="btn btn-outline-secondary btn-sm" title="Reset"><i class="fas fa-times"></i></a>
                    <?php endif; ?>
                </div>
            </form>
        </div>
    </div>

    <!-- Summary -->
    <div class="d-flex justify-content-between align-items-center mb-2">
        <span class="text-muted small"><i class="fas fa-list me-1"></i>Total: <strong><?= number_format($total) ?></strong> record</span>
        <span class="text-muted small">Halaman <?= $pg ?> / <?= $total_pages ?></span>
    </div>

    <?php if (!empty($rows)): ?>
    <div class="table-responsive">
        <table class="table table-hover table-sm align-middle">
            <thead class="table-dark">
                <tr>
                    <th width="35">No</th>
                    <th>Tanggal</th>
                    <th>Kendaraan</th>
                    <th>Pemakai</th>
                    <th>Tujuan / Keperluan</th>
                    <th>Periode</th>
                    <th>KM</th>
                    <th>Status</th>
                    <th>Sumber</th>
                    <th width="55">Aksi</th>
                </tr>
            </thead>
            <tbody>
                <?php $no = $offset + 1; foreach ($rows as $r): ?>
                <tr>
                    <td class="text-muted small"><?= $no++ ?></td>
                    <td class="small"><?= $r['tanggal'] ? date('d/m/Y', strtotime($r['tanggal'])) : '-' ?></td>
                    <td>
                        <div class="fw-semibold small"><?= htmlspecialchars($r['no_reg'] ?: $r['no_polisi']) ?></div>
                        <div class="text-muted fs-xs"><?= htmlspecialchars(trim($r['merk'] . ' ' . $r['tipe'])) ?></div>
                    </td>
                    <td>
                        <div class="small"><?= htmlspecialchars($r['pemakai']) ?></div>
                        <?php if ($r['driver_name'] !== ''): ?>
                            <div class="text-muted fs-xs"><i class="fas fa-id-badge me-1"></i><?= htmlspecialchars($r['driver_name']) ?></div>
                        <?php endif; ?>
                    </td>
                    <td>
                        <div class="small text-truncate text-truncate-180" title="<?= htmlspecialchars($r['tujuan']) ?>"><?= htmlspecialchars($r['tujuan'] ?: '-') ?></div>
                        <div class="text-muted fs-xs text-truncate text-truncate-180"><?= htmlspecialchars(mb_strimwidth($r['keperluan'], 0, 50, '…')) ?></div>
                    </td>
                    <td class="small">
                        <?= $r['tanggal_mulai'] ? date('d/m/y', strtotime($r['tanggal_mulai'])) : '-' ?>
                        <?php if (!empty($r['tanggal_selesai'])): ?>
                            <br><span class="text-muted">s/d <?= date('d/m/y', strtotime($r['tanggal_selesai'])) ?></span>
                        <?php endif; ?>
                    </td>
                    <td class="small">
                        <?php if (is_numeric($r['jarak_lp'] ?? null) && (int)$r['jarak_lp'] > 0): ?>
                            <span class="fw-semibold"><?= number_format($r['jarak_lp']) ?> km</span>
                            <!-- <div class="text-muted" style="font-size:.72rem">lap. perjalanan</div> -->
                        <?php elseif (is_numeric($r['km_awal'] ?? null) && is_numeric($r['km_akhir'] ?? null)): ?>
                            <?= number_format($r['km_awal']) ?> → <?= number_format($r['km_akhir']) ?> km
                        <?php elseif (is_numeric($r['km_awal'] ?? null) || is_numeric($r['km_akhir'] ?? null)): ?>
                            <?= is_numeric($r['km_awal'] ?? null) ? number_format($r['km_awal']) : '?' ?> → <?= is_numeric($r['km_akhir'] ?? null) ? number_format($r['km_akhir']) : '?' ?> km
                        <?php else: ?>
                            <span class="text-muted">—</span>
                        <?php endif; ?>
                    </td>
                    <td><?= rp_status_badge($r['status'], $r['sumber']) ?></td>
                    <td><?= rp_sumber_badge($r['sumber']) ?></td>
                    <td>
                        <a href="index.php?page=riwayat_pemakaian&action=view&source=<?= urlencode($r['sumber']) ?>&id=<?= (int)$r['record_id'] ?>"
                           class="btn btn-sm btn-outline-info" title="Lihat Detail">
                            <i class="fas fa-eye"></i>
                        </a>
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>

    <!-- Pagination -->
    <?php if ($total_pages > 1): ?>
    <div class="d-flex justify-content-between align-items-center mt-3 flex-wrap gap-2">
        <div class="text-muted small">
            Record <?= $offset + 1 ?>–<?= min($total, $offset + count($rows)) ?> dari <?= number_format($total) ?>
        </div>
        <div class="btn-group btn-group-sm">
            <?php if ($pg > 1): ?>
                <a class="btn btn-outline-secondary" href="<?= rp_page_url(1, $url_extra) ?>"><i class="fas fa-angle-double-left"></i></a>
                <a class="btn btn-outline-secondary" href="<?= rp_page_url($pg - 1, $url_extra) ?>"><i class="fas fa-angle-left"></i></a>
            <?php endif; ?>
            <?php
            $start_p = max(1, $pg - 2);
            $end_p   = min($total_pages, $pg + 2);
            for ($i = $start_p; $i <= $end_p; $i++): ?>
                <a class="btn btn-outline-secondary <?= $i == $pg ? 'active' : '' ?>" href="<?= rp_page_url($i, $url_extra) ?>"><?= $i ?></a>
            <?php endfor; ?>
            <?php if ($pg < $total_pages): ?>
                <a class="btn btn-outline-secondary" href="<?= rp_page_url($pg + 1, $url_extra) ?>"><i class="fas fa-angle-right"></i></a>
                <a class="btn btn-outline-secondary" href="<?= rp_page_url($total_pages, $url_extra) ?>"><i class="fas fa-angle-double-right"></i></a>
            <?php endif; ?>
        </div>
    </div>
    <?php endif; ?>

    <?php else: ?>
    <div class="text-center py-5">
        <i class="fas fa-history fa-3x text-muted mb-3"></i>
        <h5 class="text-muted">Tidak ada data pemakaian</h5>
        <p class="text-muted small">
            <?php if ($filter_q || $filter_kendaraan || $filter_sumber || $filter_date_from || $filter_date_to): ?>
                Tidak ada record yang cocok dengan filter yang dipilih.
                <a href="index.php?page=riwayat_pemakaian">Reset filter</a>
            <?php else: ?>
                Belum ada data pemakaian kendaraan (peminjaman atau surat tugas yang disetujui).
            <?php endif; ?>
        </p>
    </div>
    <?php endif; ?>

</div>
