<?php
// Auth
if (!function_exists('is_logged_in')) { require_once __DIR__ . '/../includes/auth.php'; }
if (!is_logged_in()) { header('Location: login.php'); exit(); }

// Allow admin-like roles
$role = get_current_role();
if (!is_admin_like()) {
    header('Location: dashboard_user.php');
    exit();
}

$current_user = get_logged_in_user();

// Page config
$page_title = 'Pengguna Kendaraan';
$current_page = 'pengguna_kendaraan';
$additional_css = [];
$additional_js = [];

render_page_head($page_title, $additional_css, $additional_js);
render_sidebar($current_page, $role);

// Filters
$filter_sumber = isset($_GET['sumber']) ? strtolower(trim($_GET['sumber'])) : 'all'; // all|peminjaman|surat_tugas
$filter_status = isset($_GET['status']) ? trim($_GET['status']) : 'ongoing'; // default only ongoing/perjalanan
$start_date = isset($_GET['mulai']) ? trim($_GET['mulai']) : '';
$end_date = isset($_GET['selesai']) ? trim($_GET['selesai']) : '';
$q = isset($_GET['q']) ? trim($_GET['q']) : '';
$sort = isset($_GET['sort']) ? strtolower($_GET['sort']) : 'asc'; // asc|desc on tanggal_mulai
$sort = in_array($sort, ['asc','desc']) ? $sort : 'asc';

// Validate dates (YYYY-MM-DD)
$is_valid_date = function($d) {
    if (!$d) return false;
    $t = strtotime($d);
    return $t && date('Y-m-d', $t) === $d;
};

// Build dynamic conditions
$cond_pk = [];
$cond_st = [];

// Status map
$active_pk = ["approved","ongoing","disetujui","berjalan","aktif","dipinjam","sedang_dipinjam"];
$active_st = ["disetujui","dalam perjalanan","aktif"]; // surat_tugas enums

if ($filter_status && strtolower($filter_status) !== 'all') {
    $fs = strtolower($filter_status);
    if ($fs === 'aktif') {
        $in_pk = "'" . implode("','", $active_pk) . "'";
        $cond_pk[] = "LOWER(pk.status) IN ($in_pk)";
        $in_st = "'" . implode("','", $active_st) . "'";
        $cond_st[] = "LOWER(s.status) IN ($in_st)";
    } elseif ($fs === 'ongoing' || $fs === 'dalam perjalanan' || $fs === 'berjalan' || $fs === 'sedang_dipinjam') {
        // Normalize to ongoing/perjalanan mapping between the two sources
        $cond_pk[] = "LOWER(pk.status) = 'ongoing'";
        $cond_st[] = "LOWER(s.status) = 'dalam perjalanan'";
    } elseif ($fs === 'approved' || $fs === 'disetujui') {
        $cond_pk[] = "LOWER(pk.status) = 'approved'";
        $cond_st[] = "LOWER(s.status) = 'disetujui'";
    } else {
        $esc = $mysqli->real_escape_string($fs);
        $cond_pk[] = "LOWER(pk.status) = '$esc'";
        $cond_st[] = "LOWER(s.status) = '$esc'";
    }
}

if ($is_valid_date($start_date)) {
    $sd = $mysqli->real_escape_string($start_date);
    $cond_pk[] = "DATE(pk.tanggal_mulai) >= '$sd'";
    $cond_st[] = "DATE(s.tanggal_berangkat) >= '$sd'";
}
if ($is_valid_date($end_date)) {
    $ed = $mysqli->real_escape_string($end_date);
    $cond_pk[] = "DATE(pk.tanggal_selesai) <= '$ed'";
    $cond_st[] = "(s.tanggal_kembali IS NULL OR DATE(s.tanggal_kembali) <= '$ed')";
}
if ($q !== '') {
    $escq = '%' . $mysqli->real_escape_string($q) . '%';
    $cond_pk[] = "(p.nama_lengkap LIKE '$escq' OR k.no_polisi LIKE '$escq' OR k.no_reg LIKE '$escq' OR pk.nomor_surat LIKE '$escq' OR pk.tujuan LIKE '$escq' OR pk.keperluan LIKE '$escq')";
    $cond_st[] = "(p.nama_lengkap LIKE '$escq' OR k.no_polisi LIKE '$escq' OR k.no_reg LIKE '$escq' OR s.nomor_surat LIKE '$escq' OR s.tujuan LIKE '$escq' OR s.keperluan LIKE '$escq')";
}

// Sumber filter: later applied by skipping one side
// If focusing on ongoing, we prioritize peminjaman source by default
if (strtolower($filter_status) === 'ongoing' && $filter_sumber === 'all') {
    $filter_sumber = 'peminjaman';
}
$skip_pk = ($filter_sumber === 'surat_tugas');
$skip_st = ($filter_sumber === 'peminjaman');

$where_pk = $cond_pk ? ('WHERE ' . implode(' AND ', $cond_pk)) : '';
$where_st = $cond_st ? ('WHERE ' . implode(' AND ', $cond_st)) : '';

$rows = [];
try {
    $parts = [];
    if (!$skip_pk) {
    $parts[] = "SELECT pk.id AS ref_id, 'peminjaman' AS sumber, p.nama_lengkap AS pengguna, p.nrp_nip,
                           k.no_polisi, k.no_reg, k.merk, k.tipe,
               pk.tanggal_mulai, pk.tanggal_selesai, pk.status, pk.nomor_surat, pk.tujuan AS tujuan, pk.keperluan AS keperluan
                    FROM peminjaman_kendaraan pk
                    LEFT JOIN pengguna p ON pk.peminjam_id = p.id
                    LEFT JOIN kendaraan k ON pk.kendaraan_id = k.id
                    $where_pk";
    }
    if (!$skip_st) {
    $parts[] = "SELECT s.id AS ref_id, 'surat_tugas' AS sumber, p.nama_lengkap AS pengguna, p.nrp_nip,
               k.no_polisi, k.no_reg, k.merk, k.tipe,
                           s.tanggal_berangkat AS tanggal_mulai, s.tanggal_kembali AS tanggal_selesai, s.status, s.nomor_surat, s.tujuan, s.keperluan
                    FROM surat_tugas s
                    LEFT JOIN pengguna p ON s.pengguna_id = p.id
                    LEFT JOIN kendaraan k ON s.kendaraan_id = k.id
                    $where_st";
    }

    if (!empty($parts)) {
        $union = implode("\nUNION ALL\n", $parts);
        $sql = "SELECT * FROM ( $union ) AS usage_all ORDER BY tanggal_mulai " . strtoupper($sort) . " LIMIT 100";
        $res = $mysqli->query($sql);
        if ($res) { $rows = $res->fetch_all(MYSQLI_ASSOC); }
    }
} catch (Throwable $e) {
    // swallow
}

// Section 2: Users having vehicles assigned via kendaraan.pengguna_id
$users_with_vehicles = [];
$vehicles_by_user = [];
try {
    $sqlUsers = "SELECT p.id, p.nama_lengkap, p.pangkat, p.jabatan, p.nrp_nip, p.no_hp, p.email, p.alamat, p.status_aktif, p.jenis_personel, p.matra, p.korps, p.kesatuan
                 FROM pengguna p
                 WHERE EXISTS (SELECT 1 FROM kendaraan k WHERE k.pengguna_id = p.id)
                 ORDER BY p.nama_lengkap ASC";
    if ($resU = $mysqli->query($sqlUsers)) {
        $users_with_vehicles = $resU->fetch_all(MYSQLI_ASSOC);
        $resU->close();
    }
    if (!empty($users_with_vehicles)) {
        $ids = array_map('intval', array_column($users_with_vehicles, 'id'));
        $ids_in = implode(',', $ids);
        $sqlVeh = "SELECT id, pengguna_id, no_polisi, no_reg, merk, tipe, tahun_pembuatan, jenis, status_kendaraan
                   FROM kendaraan
                   WHERE pengguna_id IN ($ids_in)
                   ORDER BY no_reg ASC";
        if ($resK = $mysqli->query($sqlVeh)) {
            while ($v = $resK->fetch_assoc()) {
                $uid = (int)$v['pengguna_id'];
                if (!isset($vehicles_by_user[$uid])) $vehicles_by_user[$uid] = [];
                $vehicles_by_user[$uid][] = $v;
            }
            $resK->close();
        }
    }
} catch (Throwable $e) {
    // swallow
}

?>

<div class="container-fluid">
    <div class="gradient-header">
        <div class="d-flex justify-content-between align-items-center">
            <div>
                <h1 class="mb-1"><i class="fas fa-users me-2"></i>Pengguna Kendaraan</h1>
                <p class="mb-0 opacity-75">Data dari Peminjaman</p>
            </div>
        </div>
    </div>


    <!-- Filter -->
    <!-- <div class="card shadow-sm mb-3">
        <div class="card-body">
            <form class="row g-2 align-items-end" method="get" action="index.php">
                <input type="hidden" name="page" value="pengguna_kendaraan" />
                <div class="col-md-3">
                    <label class="form-label">Sumber</label>
                    <select name="sumber" class="form-select">
                        <option value="all" <?= $filter_sumber==='all'?'selected':''; ?>>Semua</option>
                        <option value="peminjaman" <?= $filter_sumber==='peminjaman'?'selected':''; ?>>Peminjaman</option>
                        <option value="surat_tugas" <?= $filter_sumber==='surat_tugas'?'selected':''; ?>>Surat Tugas</option>
                    </select>
                </div>
                <div class="col-md-3">
                    <label class="form-label">Status</label>
                    <select name="status" class="form-select">
                        <?php $opts = ['ongoing'=>'Ongoing','all'=>'Semua','aktif'=>'Aktif','approved'=>'Approved','disetujui'=>'Disetujui','dalam perjalanan'=>'Dalam Perjalanan','selesai'=>'Selesai','dibatalkan'=>'Dibatalkan']; ?>
                        <?php foreach ($opts as $k => $v): ?>
                            <option value="<?= htmlspecialchars($k) ?>" <?= strtolower($filter_status)===strtolower($k)?'selected':''; ?>><?= htmlspecialchars($v) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-md-2">
                    <label class="form-label">Mulai</label>
                    <input type="date" name="mulai" class="form-control" value="<?= htmlspecialchars($start_date) ?>" />
                </div>
                <div class="col-md-2">
                    <label class="form-label">Selesai</label>
                    <input type="date" name="selesai" class="form-control" value="<?= htmlspecialchars($end_date) ?>" />
                </div>
                <div class="col-md-2">
                    <label class="form-label">Cari</label>
                    <input type="text" name="q" class="form-control" placeholder="Nama/No Polisi/No Reg/No Surat" value="<?= htmlspecialchars($q) ?>" />
                </div>
                <div class="col-md-2">
                    <label class="form-label">Urut</label>
                    <select name="sort" class="form-select">
                        <option value="desc" <?= $sort==='desc'?'selected':''; ?>>Terbaru</option>
                        <option value="asc" <?= $sort==='asc'?'selected':''; ?>>Terlama</option>
                    </select>
                </div>
                <div class="col-md-2">
                    <button class="btn btn-primary w-100"><i class="fas fa-filter me-1"></i>Filter</button>
                </div>
            </form>
        </div>
    </div> -->

    <div class="card shadow-sm">
        <div class="card-header bg-primary text-white">
            <div class="d-flex align-items-center justify-content-between">
                <h5 class="mb-0"><i class="fas fa-id-badge me-2"></i>Kepemilikan Kendaraan</h5>
                <div class="ms-3" style="min-width:280px;max-width:360px;width:100%;">
                    <div class="input-group input-group-sm">
                        <span class="input-group-text bg-white"><i class="fas fa-search text-muted"></i></span>
                        <input type="text" id="liveSearch" class="form-control" placeholder="Ketik untuk mencari..." autocomplete="off" />
                    </div>
                </div>
            </div>
        </div>
        <div class="card-body p-0">
            <?php if (!empty($users_with_vehicles)): ?>
                <div class="table-responsive" style="max-height: 600px;">
                    <table class="table table-sm mb-0" id="kepemilikanTable">
                        <thead class="bg-light sticky-top">
                            <tr>
                                <th>Pengguna</th>
                                <th>Jabatan / Pangkat</th>
                                <th>Kontak</th>
                                <th>Status</th>
                                <th>Kendaraan</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($users_with_vehicles as $u): ?>
                                <tr>
                                    <td>
                                        <div class="fw-semibold"><?= htmlspecialchars($u['nama_lengkap'] ?? '-') ?></div>
                                        <?php if (!empty($u['nrp_nip'])): ?><small class="text-muted">NRP/NIP: <?= htmlspecialchars($u['nrp_nip']) ?></small><?php endif; ?>
                                    </td>
                                    <td>
                                        <div><?= htmlspecialchars($u['jabatan'] ?? '-') ?></div>
                                        <small class="text-muted"><?= htmlspecialchars($u['pangkat'] ?? '-') ?></small>
                                    </td>
                                    <td>
                                        <?php if (!empty($u['no_hp'])): ?><div><i class="fas fa-phone me-1"></i><?= htmlspecialchars($u['no_hp']) ?></div><?php endif; ?>
                                        <?php if (!empty($u['email'])): ?><div><i class="fas fa-envelope me-1"></i><?= htmlspecialchars($u['email']) ?></div><?php endif; ?>
                                        <?php if (!empty($u['alamat'])): ?><small class="text-muted d-block"><i class="fas fa-map-marker-alt me-1"></i><?= htmlspecialchars($u['alamat']) ?></small><?php endif; ?>
                                    </td>
                                    <td>
                                        <span class="badge bg-<?= strtolower($u['status_aktif'] ?? '') === 'aktif' ? 'success' : 'secondary' ?>"><?= htmlspecialchars($u['status_aktif'] ?? '-') ?></span>
                                    </td>
                                    <td>
                                        <?php $list = $vehicles_by_user[(int)($u['id'] ?? 0)] ?? []; ?>
                                        <?php if (!empty($list)): ?>
                                            <?php foreach ($list as $v): ?>
                                                <div class="mb-1">
                                                    <span class="badge bg-light text-dark border">
                                                        <?= htmlspecialchars(($v['no_reg'] ?: '-')) ?>
                                                    </span>
                                                    <small class="text-muted"><?= htmlspecialchars(trim(($v['merk'] ?? '') . ' ' . ($v['tipe'] ?? ''))) ?></small>
                                                    <?php if (!empty($v['status_kendaraan'])): ?>
                                                        <small class="ms-2 badge badge-<?= strtolower(str_replace(' ', '-', $v['status_kendaraan'])) ?>"><?= htmlspecialchars($v['status_kendaraan']) ?></small>
                                                    <?php endif; ?>
                                                </div>
                                            <?php endforeach; ?>
                                        <?php else: ?>
                                            <span class="text-muted">-</span>
                                        <?php endif; ?>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php else: ?>
                <div class="text-center py-3">
                    <small class="text-muted">Belum ada kendaraan yang ditugaskan langsung ke pengguna.</small>
                </div>
            <?php endif; ?>
        </div>
    </div>


    <!-- pengguna kendaraan berdasarkan surat tugas -->
    <!-- <div class="card shadow-sm mt-3">
        <div class="card-header bg-dark text-white">
            <h5 class="mb-0"><i class="fas fa-list me-2"></i>Daftar Pengguna Kendaraan</h5>
        </div>
        <div class="card-body p-0">
            <?php if (!empty($rows)): ?>
                <div class="table-responsive" style="max-height: 600px;">
                    <table class="table table-sm mb-0">
                        <thead class="bg-light sticky-top">
                            <tr>
                                <th>Sumber</th>
                                <th>Pengguna</th>
                                <th>Kendaraan</th>
                                <th>Nomor Surat</th>
                                <th>Tujuan</th>
                                <th>Periode</th>
                                <th>Status</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($rows as $r): ?>
                                <?php 
                                    $sumber = $r['sumber'];
                                    $badge = $sumber==='peminjaman' ? 'primary' : 'warning';
                                    $status_l = strtolower($r['status'] ?? '');
                                    $status_cls = in_array($status_l, array_merge($active_pk, $active_st)) ? 'success' : (in_array($status_l, ['selesai','completed']) ? 'info' : (in_array($status_l, ['dibatalkan','rejected','ditolak']) ? 'danger' : 'secondary'));
                                ?>
                                <tr>
                                    <td><span class="badge bg-<?= $badge ?>"><?= htmlspecialchars(ucwords(str_replace('_',' ', $sumber))) ?></span></td>
                                    <td>
                                        <div class="fw-semibold"><?= htmlspecialchars($r['pengguna'] ?? 'N/A') ?></div>
                                        <?php if (!empty($r['nrp_nip'])): ?><small class="text-muted">NRP/NIP: <?= htmlspecialchars($r['nrp_nip']) ?></small><?php endif; ?>
                                    </td>
                                    <td>
                                        <div class="fw-semibold"><?= htmlspecialchars(($r['no_polisi'] ?? '-') . ' / ' . ($r['no_reg'] ?? '-')) ?></div>
                                        <small class="text-muted"><?= htmlspecialchars(($r['merk'] ?? '') . ' ' . ($r['tipe'] ?? '')) ?></small>
                                    </td>
                                    <td><?= htmlspecialchars($r['nomor_surat'] ?? '-') ?></td>
                                    <td>
                                        <?php if (!empty($r['tujuan'])): ?>
                                            <small class="text-muted"><?= htmlspecialchars($r['tujuan']) ?></small>
                                        <?php else: ?>
                                            <span class="text-muted">-</span>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <small class="text-muted">
                                            <?= !empty($r['tanggal_mulai']) ? date('d/m/Y', strtotime($r['tanggal_mulai'])) : '-' ?>
                                            <?= !empty($r['tanggal_selesai']) ? ' - ' . date('d/m/Y', strtotime($r['tanggal_selesai'])) : '' ?>
                                        </small>
                                    </td>
                                    <td><span class="badge bg-<?= $status_cls ?>"><?= htmlspecialchars(ucwords($r['status'] ?? '')) ?></span></td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php else: ?>
                <div class="text-center py-5">
                    <i class="fas fa-clipboard-list fa-3x text-muted mb-3"></i>
                    <p class="text-muted">Data tidak ditemukan</p>
                </div>
            <?php endif; ?>
        </div>
    </div> -->
</div>

<script>
// Live search filter for Kepemilikan Kendaraan table (vanilla JS)
(function(){
    function debounce(fn, wait){
        var t; return function(){
            clearTimeout(t);
            var args = arguments;
            t = setTimeout(function(){ fn.apply(null, args); }, wait);
        };
    }
    document.addEventListener('DOMContentLoaded', function(){
        var input = document.getElementById('liveSearch');
        var table = document.getElementById('kepemilikanTable');
        if(!input || !table) return;
        var tbody = table.tBodies[0];
        if(!tbody) return;
        var rows = Array.prototype.slice.call(tbody.querySelectorAll('tr'));
        // Append a no-results row (hidden by default)
        var nores = document.createElement('tr');
        nores.className = 'no-results d-none';
        var td = document.createElement('td');
        td.colSpan = 5;
        td.className = 'text-center text-muted py-3';
        td.textContent = 'Tidak ada hasil';
        nores.appendChild(td);
        tbody.appendChild(nores);

        var filter = debounce(function(){
            var q = String(input.value || '').toLowerCase().trim();
            var visible = 0;
            rows.forEach(function(r){
                if(r.classList.contains('no-results')) return; // skip placeholder
                var hay = (r.textContent || '').toLowerCase();
                var match = (q === '') || (hay.indexOf(q) !== -1);
                r.classList.toggle('d-none', !match);
                if(match) visible++;
            });
            nores.classList.toggle('d-none', visible !== 0);
        }, 120);

        input.addEventListener('input', filter);
        input.addEventListener('keydown', function(e){
            if(e.key === 'Escape'){
                input.value = '';
                filter();
            }
        });
    });
})();
</script>

<?php render_page_footer($additional_js); ?>

<?php /* End of file */ ?>
