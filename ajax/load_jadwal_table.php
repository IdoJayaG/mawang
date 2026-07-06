<?php
require_once dirname(__DIR__) . '/includes/auth.php';

$current_role = get_current_role();
$can_crud = can_admin();
$jadwal_page  = max(1, (int)($_GET['jadwal_page'] ?? 1));
$limit_jp     = 10;
$offset_jp    = ($jadwal_page - 1) * $limit_jp;
$where = ["j.status != 'Selesai'"];
// Untuk jadwal rutin: hanya tampilkan entry paling dekat per grup (kendaraan + keterangan).
// Ketika entry selesai, entry berikutnya otomatis menjadi yang terdepan.
$where[] = "(j.keterangan IS NULL OR j.keterangan NOT LIKE 'Penjadwalan rutin%' OR j.id = (SELECT j2.id FROM jadwal_perawatan j2 WHERE j2.kendaraan_id = j.kendaraan_id AND j2.keterangan = j.keterangan AND j2.status NOT IN ('Selesai','Dibatalkan') ORDER BY j2.tanggal_perawatan ASC LIMIT 1))";
$params = []; $types = '';

// If current user is a driver, restrict to vehicles they are responsible for / have access to
if ($current_role === 'driver' && ($current_user_id = get_current_user_id())) {
    // Prefer kendaraan.pengguna_id when present
    if (function_exists('db_table_columns') && in_array('pengguna_id', db_table_columns('kendaraan') ?: [], true)) {
        $where[] = 'k.pengguna_id = ?';
        $params[] = $current_user_id;
        $types .= 'i';
    } else {
        // Fallback: allow if there's an active surat_tugas or peminjaman_kendaraan referencing this user, or if jadwal was created by the user
        $exists_clauses = [];
        if (function_exists('db_table_exists') && db_table_exists('surat_tugas')) {
            $exists_clauses[] = "EXISTS (SELECT 1 FROM surat_tugas s2 WHERE s2.kendaraan_id = k.id AND s2.pengguna_id = ? AND s2.status IN ('Disetujui','Dalam Perjalanan'))";
            $params[] = $current_user_id; $types .= 'i';
        }
        if (function_exists('db_table_exists') && db_table_exists('peminjaman_kendaraan')) {
            $appCol = function_exists('pk_applicant_column') ? pk_applicant_column() : null;
            $bindVal = function_exists('pk_applicant_bind_value') ? pk_applicant_bind_value($appCol, (int)$current_user_id) : null;
            if ($appCol && $bindVal) {
                $exists_clauses[] = "EXISTS (SELECT 1 FROM peminjaman_kendaraan pk2 WHERE pk2.kendaraan_id = k.id AND pk2.`{$appCol}` = ? AND LOWER(pk2.status) IN ('approved','ongoing'))";
                $params[] = $bindVal; $types .= 'i';
            }
        }
        if (!empty($exists_clauses)) {
            $where[] = '(' . implode(' OR ', $exists_clauses) . ')';
        } else {
            // Last resort: jadwal created by this pengguna
            $where[] = 'j.created_by = ?';
            $params[] = $current_user_id; $types .= 'i';
        }
    }
}

$where_sql = 'WHERE ' . implode(' AND ', $where);

// Count total records for pagination
$count_sql = "SELECT COUNT(*) AS total FROM jadwal_perawatan j LEFT JOIN kendaraan k ON j.kendaraan_id = k.id $where_sql";
$total_records = 0;
if (!empty($params)) {
    $params_copy = $params;
    $types_copy  = $types;
    $cs = $mysqli->prepare($count_sql);
    if ($cs) {
        $cbind = [&$types_copy];
        for ($i = 0; $i < count($params_copy); $i++) { $cbind[] = &$params_copy[$i]; }
        call_user_func_array([$cs, 'bind_param'], $cbind);
        $cs->execute();
        $total_records = (int)($cs->get_result()->fetch_assoc()['total'] ?? 0);
        $cs->close();
    }
} else {
    $cr = $mysqli->query($count_sql);
    if ($cr) $total_records = (int)($cr->fetch_assoc()['total'] ?? 0);
}
$total_pages = max(1, (int)ceil($total_records / $limit_jp));
$jadwal_page = min($jadwal_page, $total_pages);
$offset_jp   = ($jadwal_page - 1) * $limit_jp;

$sql = "SELECT j.*, k.no_polisi, k.no_reg, k.merk, k.tipe FROM jadwal_perawatan j LEFT JOIN kendaraan k ON j.kendaraan_id = k.id $where_sql ORDER BY j.tanggal_perawatan ASC, j.prioritas DESC LIMIT $limit_jp OFFSET $offset_jp";

if (!empty($params)) {
    $stmt = $mysqli->prepare($sql);
    if ($stmt) {
        $bind = [];
        $bind[] = & $types;
        for ($i = 0; $i < count($params); $i++) { $bind[] = & $params[$i]; }
        call_user_func_array([$stmt, 'bind_param'], $bind);
        $stmt->execute();
        $result = $stmt->get_result();
        $stmt->close();
    } else {
        $result = $mysqli->query("SELECT j.*, k.no_polisi, k.no_reg, k.merk, k.tipe FROM jadwal_perawatan j LEFT JOIN kendaraan k ON j.kendaraan_id = k.id WHERE j.status != 'Selesai' ORDER BY j.tanggal_perawatan ASC, j.prioritas DESC LIMIT $limit_jp OFFSET $offset_jp");
    }
} else {
    $result = $mysqli->query($sql);
}
$no = $offset_jp + 1;

if ($result && $result->num_rows > 0):
    while ($row = $result->fetch_assoc()):
        $status_class = [
            'Terjadwal' => 'primary',
            'Dalam Proses' => 'warning',
            'Selesai' => 'success',
            'Terlewat' => 'danger',
            'Dibatalkan' => 'secondary'
        ][$row['status']] ?? 'secondary';
        
        $status_icon = [
            'Terjadwal' => 'fas fa-clock',
            'Dalam Proses' => 'fas fa-cog fa-spin',
            'Selesai' => 'fas fa-check-circle',
            'Terlewat' => 'fas fa-times-circle',
            'Dibatalkan' => 'fas fa-ban'
        ][$row['status']] ?? 'fas fa-question-circle';
        
        // Ensure prioritas comes from DB and normalize it
        $prioritas_value = isset($row['prioritas']) ? trim((string)$row['prioritas']) : '';
        if ($prioritas_value === '') {
            $prioritas_value = 'Normal';
        }

        // Map known priority labels to badge classes and icons (case sensitive mapping expects exact labels)
        $prioritas_map = [
            'Urgent' => ['class' => 'danger', 'icon' => 'fas fa-exclamation-circle'],
            'Tinggi' => ['class' => 'warning', 'icon' => 'fas fa-chevron-up'],
            'Normal' => ['class' => 'info', 'icon' => 'fas fa-minus'],
            'Rendah' => ['class' => 'secondary', 'icon' => 'fas fa-chevron-down']
        ];

        $prioritas_class = $prioritas_map[$prioritas_value]['class'] ?? 'secondary';
        $prioritas_icon = $prioritas_map[$prioritas_value]['icon'] ?? 'fas fa-minus';
        
        // Check if maintenance is overdue (prefer tanggal_perawatan, fallback to jadwal_tanggal if present)
        $is_overdue = false;
        $schedule_date_str = $row['tanggal_perawatan'] ?? ($row['jadwal_tanggal'] ?? null);
        $today = new DateTime();
        if (!empty($schedule_date_str)) {
            try {
                $schedule_date = new DateTime($schedule_date_str);
                if ($schedule_date < $today && (($row['status'] ?? '') === 'Terjadwal')) {
                    $is_overdue = true;
                }
            } catch (Exception $e) {
                // ignore invalid date formats
            }
        }
?>
<tr <?= $is_overdue ? 'class="table-danger"' : '' ?>>
    <td><?= $no++ ?></td>
    <td>
        <strong><?= htmlspecialchars($row['no_reg']) ?> </strong><br>
        <small class="text-muted"><?= htmlspecialchars($row['merk'] . ' ' . $row['tipe']) ?></small>
    </td>
    <td>
        <strong><?= htmlspecialchars($row['jenis_perawatan']) ?></strong>
        <?php if (!empty($row['deskripsi'])): ?>
            <br><small class="text-muted"><?= htmlspecialchars($row['deskripsi'] ?? '') ?></small>
        <?php endif; ?>
    </td>
    <td>
        <?php
            $display_date = '-';
            if (!empty($row['tanggal_perawatan'])) {
                $display_date = date('d/m/Y', strtotime($row['tanggal_perawatan']));
            } elseif (!empty($row['jadwal_tanggal'])) {
                $display_date = date('d/m/Y', strtotime($row['jadwal_tanggal']));
            }
            echo $display_date;
        ?>
        <?php if ($is_overdue): ?>
            <br><small class="text-danger"><i class="fas fa-exclamation-triangle"></i> Terlambat</small>
        <?php endif; ?>
    </td>
    <td>
        <?= !empty($row['bengkel']) ? htmlspecialchars($row['bengkel']) : '<span class="text-muted">-</span>' ?>
    </td>
    <td>
        <?php
        if (preg_match('/Penjadwalan rutin setiap (\d+) bulan/i', $row['keterangan'] ?? '', $tm)) {
            echo '<span class="badge bg-info text-dark">Rutin &middot; ' . (int)$tm[1] . ' Bulanan</span>';
        } else {
            echo '<span class="badge bg-secondary">Satu Kali</span>';
        }
        ?>
    </td>
    <!-- Estimasi Biaya column removed per privacy request -->
    <td>
        <?php if ($can_crud): ?>
            <select class="form-control form-control-sm status-dropdown" data-id="<?= $row['id'] ?>">
                <option value="Terjadwal" <?= $row['status'] === 'Terjadwal' ? 'selected' : '' ?>>
                    <i class="fas fa-clock"></i> Terjadwal
                </option>
                <option value="Dalam Proses" <?= $row['status'] === 'Dalam Proses' ? 'selected' : '' ?>>
                    <i class="fas fa-cog"></i> Dalam Proses
                </option>
                <option value="Selesai" <?= $row['status'] === 'Selesai' ? 'selected' : '' ?>>
                    <i class="fas fa-check-circle"></i> Selesai
                </option>
                <option value="Terlewat" <?= $row['status'] === 'Terlewat' ? 'selected' : '' ?>>
                    <i class="fas fa-times-circle"></i> Terlewat
                </option>
                <option value="Dibatalkan" <?= $row['status'] === 'Dibatalkan' ? 'selected' : '' ?>>
                    <i class="fas fa-ban"></i> Dibatalkan
                </option>
            </select>
        <?php else: ?>
            <span class="badge badge-<?= $status_class ?>">
                <i class="<?= $status_icon ?>"></i> 
                <?= htmlspecialchars($row['status']) ?>
            </span>
        <?php endif; ?>
    </td>
    <td>
        <?php
        // Display the normalized prioritas value fetched from DB
        if ($prioritas_value) {
        ?>
            <span class="badge badge-light text-<?= $prioritas_class ?>">
                <i class="<?= $prioritas_icon ?> text-<?= $prioritas_class ?>"></i>
                <?= htmlspecialchars($prioritas_value) ?>
            </span>
        <?php
        } else {
            echo '<span class="text-muted">-</span>';
        }
        ?>
    </td>
    <?php if ($can_crud): ?>
    <td>
        <div class="btn-group" role="group">
            <a href="?page=jadwal_perawatan&action=view&id=<?= $row['id'] ?>" class="btn btn-sm btn-outline-info" title="Lihat Detail"><i class="fas fa-eye"></i></a>
            <?php if ($can_crud): ?>
                <a href="?page=jadwal_perawatan&action=edit&id=<?= $row['id'] ?>" class="btn btn-sm btn-outline-primary" title="Edit"><i class="fas fa-edit"></i></a>
                <?php if (can_admin()): ?>
                    <a href="index.php?page=jadwal_perawatan&action=delete&id=<?= $row['id'] ?>" class="btn btn-sm btn-outline-danger" title="Hapus" onclick="return confirm('Yakin ingin menghapus jadwal perawatan ini?')"><i class="fas fa-trash"></i></a>
                <?php endif; ?>
            <?php endif; ?>
        </div>
    </td>
    <?php endif; ?>
</tr>
<?php
    endwhile;
else:
?>
<tr>
    <td colspan="<?= $can_crud ? '9' : '8' ?>" class="text-center text-muted py-3">
        <i class="fas fa-calendar-times me-1"></i>Belum ada jadwal perawatan
    </td>
</tr>
<?php endif; ?>
<?php if ($total_pages > 1 || $total_records > 0): ?>
<tr class="table-light border-top">
    <td colspan="<?= $can_crud ? '9' : '8' ?>" class="py-2 px-3">
        <div class="d-flex align-items-center justify-content-between flex-wrap gap-2">
            <small class="text-muted">
                Menampilkan <strong><?= $total_records > 0 ? $offset_jp + 1 : 0 ?></strong>–<strong><?= min($offset_jp + $limit_jp, $total_records) ?></strong>
                dari <strong><?= $total_records ?></strong> data
            </small>
            <?php if ($total_pages > 1): ?>
            <nav aria-label="Paginasi jadwal">
                <ul class="pagination pagination-sm mb-0">
                    <li class="page-item <?= $jadwal_page <= 1 ? 'disabled' : '' ?>">
                        <a class="page-link" href="javascript:void(0)" onclick="JadwalPerawatan.goToPage(1)">«</a>
                    </li>
                    <li class="page-item <?= $jadwal_page <= 1 ? 'disabled' : '' ?>">
                        <a class="page-link" href="javascript:void(0)" onclick="JadwalPerawatan.goToPage(<?= $jadwal_page - 1 ?>)">‹</a>
                    </li>
                    <?php
                    $p_start = max(1, $jadwal_page - 2);
                    $p_end   = min($total_pages, $jadwal_page + 2);
                    if ($p_start > 1): ?>
                        <li class="page-item disabled"><span class="page-link">…</span></li>
                    <?php endif;
                    for ($p = $p_start; $p <= $p_end; $p++): ?>
                        <li class="page-item <?= $p === $jadwal_page ? 'active' : '' ?>">
                            <a class="page-link" href="javascript:void(0)" onclick="JadwalPerawatan.goToPage(<?= $p ?>)"><?= $p ?></a>
                        </li>
                    <?php endfor;
                    if ($p_end < $total_pages): ?>
                        <li class="page-item disabled"><span class="page-link">…</span></li>
                    <?php endif; ?>
                    <li class="page-item <?= $jadwal_page >= $total_pages ? 'disabled' : '' ?>">
                        <a class="page-link" href="javascript:void(0)" onclick="JadwalPerawatan.goToPage(<?= $jadwal_page + 1 ?>)">›</a>
                    </li>
                    <li class="page-item <?= $jadwal_page >= $total_pages ? 'disabled' : '' ?>">
                        <a class="page-link" href="javascript:void(0)" onclick="JadwalPerawatan.goToPage(<?= $total_pages ?>)">»</a>
                    </li>
                </ul>
            </nav>
            <?php endif; ?>
        </div>
    </td>
</tr>
<?php endif; ?>
