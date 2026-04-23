<?php
require_once dirname(__DIR__) . '/includes/auth.php';

$current_role = get_current_role();
$can_crud = can_admin();
$can_notify_driver = in_array($current_role, ['admin', 'pimpinan'], true);

$query = "SELECT j.*, k.no_polisi, k.no_reg, k.merk, k.tipe 
         FROM jadwal_perawatan j 
         LEFT JOIN kendaraan k ON j.kendaraan_id = k.id 
         WHERE j.status != 'Selesai' 
         ORDER BY j.tanggal_perawatan ASC, j.prioritas DESC";
$result = $mysqli->query($query);
$no = 1;

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
        <strong><?= htmlspecialchars($row['no_polisi']) ?> <?= !empty($row['no_reg']) ? "<small class=\"text-muted\">(Reg: " . htmlspecialchars($row['no_reg']) . ")</small>" : '' ?></strong><br>
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
        <?php if (isset($row['estimasi_biaya']) && floatval($row['estimasi_biaya']) > 0): ?>
            Rp <?= number_format($row['estimasi_biaya']) ?>
        <?php else: ?>
            <span class="text-muted">-</span>
        <?php endif; ?>
    </td>
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
    <?php if ($can_crud || $can_notify_driver): ?>
    <td>
        <div class="btn-group" role="group">
            <a href="?page=jadwal_perawatan&action=view&id=<?= $row['id'] ?>" class="btn btn-sm btn-outline-info" title="Detail">
                <i class="fas fa-eye"></i>
            </a>

            <?php if ($can_crud): ?>
                <a href="?page=jadwal_perawatan&action=edit&id=<?= $row['id'] ?>" class="btn btn-sm btn-outline-primary" title="Edit">
                    <i class="fas fa-edit"></i>
                </a>
                <a href="index.php?page=jadwal_perawatan&action=delete&id=<?= $row['id'] ?>" 
                   class="btn btn-sm btn-outline-danger" title="Hapus"
                   onclick="return confirm('Yakin ingin menghapus jadwal perawatan ini?')">
                    <i class="fas fa-trash"></i>
                </a>

            <?php endif; ?>

            <?php if ($can_notify_driver): ?>
                <form method="POST" action="?page=jadwal_perawatan" style="display:inline-block; margin:0;">
                    <input type="hidden" name="csrf_token" value="<?= generate_csrf_token() ?>">
                    <input type="hidden" name="action" value="notify_driver_routine">
                    <input type="hidden" name="jadwal_id" value="<?= (int)$row['id'] ?>">
                    <input type="hidden" name="interval_bulan" value="3">
                    <button type="submit" class="btn btn-sm btn-outline-warning" title="Notifikasi Driver 3 Bulanan">
                        <i class="fas fa-bell"></i>
                    </button>
                </form>
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
    <td colspan="<?= ($can_crud || $can_notify_driver) ? '9' : '8' ?>" class="text-center text-muted">
        Belum ada jadwal perawatan
    </td>
</tr>
<?php endif; ?>
