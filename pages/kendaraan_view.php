<?php
// Include global template (anchored to pages dir)
require_once __DIR__ . '/../templates/page_template.php';

// Separate view page for vehicle details
require_once __DIR__ . '/../includes/auth.php';

$current_role = get_current_role();
$current_user_id = get_current_user_id();

// Check permissions
if ($current_role === 'guest') {
    header('Location: index.php?page=kendaraan_publik');
    exit;
}

require_login();

// Vehicle ID
$id = (int)($_GET['id'] ?? 0);
if ($id <= 0) {
    echo '<div class="alert alert-danger">Parameter kendaraan tidak valid.</div>';
    exit;
}

// For end users and drivers, enforce access; elevated roles can view all
if (in_array(strtolower((string)$current_role), ['user', 'driver'], true) && !can_access_vehicle($id)) {
    echo '<div class="alert alert-danger">Kendaraan tidak ditemukan atau Anda tidak memiliki akses.</div>';
    exit;
}

// Load kendaraan by id
$edit_data = null;
if ($stmt = $mysqli->prepare("SELECT * FROM kendaraan WHERE id = ?")) {
    $stmt->bind_param('i', $id);
    $stmt->execute();
    $res = $stmt->get_result();
    $edit_data = $res ? $res->fetch_assoc() : null;
    $stmt->close();
}

if (!$edit_data) {
    echo '<div class="alert alert-danger">Kendaraan tidak ditemukan.</div>';
    exit;
}
?>

<div class="page-header">
    <h1><i class="fas fa-eye"></i> Detail Kendaraan</h1>
    <p>Informasi lengkap kendaraan <strong>No. Reg:</strong> <?= htmlspecialchars($edit_data['no_reg'] ?: '-') ?>
        <?php if (!empty($edit_data['satker'])): ?>
            &middot; <strong>Satker:</strong> <?= htmlspecialchars($edit_data['satker']) ?>
        <?php endif; ?>
    </p>
</div>

<div class="card">
    <div class="card-header">
        <h3><?= htmlspecialchars($edit_data['merk'] . ' ' . $edit_data['tipe']) ?></h3>
    </div>
    <div class="card-body">
        <div class="row">
            <div class="col-md-6">
                <div class="detail-group">
                    <label>Nomor Registrasi</label>
                    <div class="detail-value"><?= htmlspecialchars($edit_data['no_reg'] ?: '-') ?></div>
                </div>
                
                <div class="detail-group">
                    <label>Merk</label>
                    <div class="detail-value"><?= htmlspecialchars($edit_data['merk']) ?></div>
                </div>
                
                <div class="detail-group">
                    <label>Tipe</label>
                    <div class="detail-value"><?= htmlspecialchars($edit_data['tipe']) ?></div>
                </div>
                
                <div class="detail-group">
                    <label>Tahun Pembuatan</label>
                    <div class="detail-value"><?= htmlspecialchars($edit_data['tahun_pembuatan']) ?></div>
                </div>
                
                <div class="detail-group">
                    <label>Warna</label>
                    <div class="detail-value"><?= htmlspecialchars($edit_data['warna']) ?></div>
                </div>
            </div>
            
            <div class="col-md-6">
                <div class="detail-group">
                    <label>Nomor Rangka</label>
                    <div class="detail-value"><?= htmlspecialchars($edit_data['no_rangka']) ?></div>
                </div>
                
                <div class="detail-group">
                    <label>Nomor Mesin</label>
                    <div class="detail-value"><?= htmlspecialchars($edit_data['no_mesin']) ?></div>
                </div>
                
                <div class="detail-group">
                    <label>Kondisi</label>
                    <div class="detail-value">
                        <span class="badge badge-<?= $edit_data['kondisi'] === 'Baik' ? 'success' : ($edit_data['kondisi'] === 'Rusak Ringan' ? 'warning' : 'danger') ?>">
                            <?= htmlspecialchars($edit_data['kondisi']) ?>
                        </span>
                    </div>
                </div>
                
                <div class="detail-group">
                    <label>Status Kendaraan</label>
                    <div class="detail-value">
                        <span class="badge badge-<?= $edit_data['status_kendaraan'] === 'Operasional' ? 'success' : 'secondary' ?>">
                            <?= htmlspecialchars($edit_data['status_kendaraan']) ?>
                        </span>
                    </div>
                </div>
                
                <div class="detail-group">
                    <label>Kilometer Terakhir</label>
                    <div class="detail-value">
                        <?= $edit_data['km_terakhir'] ? number_format($edit_data['km_terakhir']) . ' KM' : '<span class="text-muted">Belum diset</span>' ?>
                    </div>
                </div>
            </div>
        </div>
        
        <?php if ($edit_data['foto']): ?>
        <div class="row mt-4">
            <div class="col-12">
                <div class="detail-group">
                    <label>Foto Kendaraan</label>
                    <div class="detail-value">
                        <img src="uploads/kendaraan/<?= htmlspecialchars($edit_data['foto']) ?>" 
                             alt="<?= htmlspecialchars($edit_data['merk'] . ' ' . $edit_data['tipe']) ?>"
                             class="img-fluid vehicle-photo">
                    </div>
                </div>
            </div>
        </div>
        <?php endif; ?>
        
        <div class="form-actions">
            <?php if (strtolower((string)$current_role) === 'user'): ?>
                <a href="index.php?page=dashboard_user" class="btn btn-secondary">
                    <i class="fas fa-arrow-left"></i> Kembali ke Dashboard
                </a>
            <?php else: ?>
                <a href="index.php?page=kendaraan" class="btn btn-secondary">
                    <i class="fas fa-arrow-left"></i> Kembali ke Daftar
                </a>
            <?php endif; ?>
            
            <?php if (can_operate()): ?>
                <a href="index.php?page=kendaraan&action=edit&id=<?= $edit_data['id'] ?>" class="btn btn-primary">
                    <i class="fas fa-edit"></i> Edit Kendaraan
                </a>
            <?php endif; ?>
        </div>
    </div>
</div>


