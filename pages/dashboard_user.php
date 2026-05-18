<?php
require_once 'includes/auth.php';
require_login();
require_role(['user','driver']);

// Note: session may store either user_account.id or pengguna.id; normalize below
$user_id = get_current_user_id();

// Ambil informasi user dari user_account dan pengguna
// Bangun SELECT dan JOIN secara dinamis berdasarkan kolom/tabel yang tersedia
$selects = "p.*, ua.id AS account_id, ua.username";
$joins = [];

// Helper: cek apakah kolom ada di tabel
function has_column($mysqli, $table, $column) {
    try {
        $res = $mysqli->query("SHOW COLUMNS FROM `" . $mysqli->real_escape_string($table) . "` LIKE '" . $mysqli->real_escape_string($column) . "'");
        if ($res && $res->num_rows > 0) {
            $res->free_result();
            return true;
        }
        if ($res) $res->free_result();
    } catch (mysqli_sql_exception $e) {
        return false;
    }
    return false;
}

// Helper: cek apakah tabel ada
function table_exists_db($mysqli, $table) {
    try {
        $t = $mysqli->real_escape_string($table);
        $res = $mysqli->query("SHOW TABLES LIKE '" . $t . "'");
        $exists = $res && $res->num_rows > 0;
        if ($res) $res->free_result();
        return $exists;
    } catch (mysqli_sql_exception $e) {
        return false;
    }
}

// Helper: ambil seluruh kolom dari sebuah tabel
function get_table_columns($mysqli, $table) {
    $cols = [];
    try {
        $t = $mysqli->real_escape_string($table);
        $res = $mysqli->query("SHOW COLUMNS FROM `{$t}`");
        if ($res) {
            while ($r = $res->fetch_assoc()) { $cols[] = $r['Field']; }
            $res->free_result();
        }
    } catch (mysqli_sql_exception $e) {}
    return $cols;
}

// build_upcoming_items() is now provided globally in includes/auth.php

// Jika ada kolom kesatuan pada pengguna, ambil matra/korps/kesatuan melalui join berantai
if (has_column($mysqli, 'pengguna', 'kesatuan_id')) {
    // Ambil kesatuan -> korps -> matra
    $selects .= ", kes.nama_kesatuan, korps.nama_korps, m.nama_matra";
    $joins[] = "LEFT JOIN kesatuan kes ON p.kesatuan_id = kes.id";
    $joins[] = "LEFT JOIN korps korps ON kes.korps_id = korps.id";
    $joins[] = "LEFT JOIN matra m ON korps.matra_id = m.id";
} else {
    // fallback: jika pengguna langsung menyimpan korps_id atau matra_id
    if (has_column($mysqli, 'pengguna', 'korps_id')) {
        $selects .= ", korps.nama_korps, m.nama_matra";
        $joins[] = "LEFT JOIN korps korps ON p.korps_id = korps.id";
        $joins[] = "LEFT JOIN matra m ON korps.matra_id = m.id";
    } elseif (has_column($mysqli, 'pengguna', 'matra_id')) {
        $selects .= ", m.nama_matra";
    // Legacy direct matra_id on pengguna may not exist in normalized schema.
    // We already join matra via kesatuan->korps->matra above when kesatuan_id exists.
    // Remove the direct join to avoid referencing p.matra_id which is absent in the provided dump.
    // (kept as comment for historical reference)
    // $joins[] = "LEFT JOIN matra m ON p.matra_id = m.id";
    } else {
        // tidak ada informasi hirarki; tambahkan alias kosong untuk menjaga template
        $selects .= ", '' as nama_kesatuan, '' as nama_korps, '' as nama_matra";
    }
}

// untuk satuan, cek baik kolom pengguna.satuan_id maupun tabel satuan
$has_satuan_col = has_column($mysqli, 'pengguna', 'satuan_id');
$has_satuan_table = false;
try {
    $res = $mysqli->query("SHOW TABLES LIKE 'satuan'");
    if ($res && $res->num_rows > 0) {
        $has_satuan_table = true;
    }
    if ($res) $res->free_result();
} catch (mysqli_sql_exception $e) {
    $has_satuan_table = false;
}
if ($has_satuan_col && $has_satuan_table) {
    $selects .= ", s.nama_satuan";
    $joins[] = "LEFT JOIN satuan s ON p.satuan_id = s.id";
} else {
    $selects .= ", '' as nama_satuan";
}


$sqlBase = "SELECT " . $selects . " FROM user_account ua JOIN pengguna p ON ua.pengguna_id = p.id ";
if (!empty($joins)) {
    $sqlBase .= " " . implode(' ', $joins);
}

// Prefer resolving the canonical account id for the session to avoid ambiguous OR matches
$account_id = get_current_account_id();
$pengguna_lookup = (int)get_current_user_id();

if (!empty($account_id)) {
    $sql = $sqlBase . " WHERE ua.id = ? LIMIT 1";
    $stmt = $mysqli->prepare($sql);
    if ($stmt === false) {
        throw new \mysqli_sql_exception('Gagal menyiapkan query user_info (by account): ' . $mysqli->error);
    }
    $stmt->bind_param('i', $account_id);
} else {
    $sql = $sqlBase . " WHERE ua.pengguna_id = ? LIMIT 1";
    $stmt = $mysqli->prepare($sql);
    if ($stmt === false) {
        throw new \mysqli_sql_exception('Gagal menyiapkan query user_info (by pengguna): ' . $mysqli->error);
    }
    $stmt->bind_param('i', $pengguna_lookup);
}

$stmt->execute();
$user_info = $stmt->get_result()->fetch_assoc();
$stmt->close();

// Normalize user_info values to avoid null/undefined array offset warnings
$user_info = $user_info ?? [];
$user_display_name = isset($user_info['nama_lengkap']) ? (string)$user_info['nama_lengkap'] : '';
$user_username = isset($user_info['username']) ? (string)$user_info['username'] : '';
$user_nama_kesatuan = isset($user_info['nama_kesatuan']) ? (string)$user_info['nama_kesatuan'] : '';

// Statistik peminjaman user
$peminjaman_stats = $mysqli->prepare("
    SELECT 
        COUNT(*) as total_pengajuan,
        SUM(CASE WHEN status = 'pending' THEN 1 ELSE 0 END) as pending,
        SUM(CASE WHEN status = 'approved' THEN 1 ELSE 0 END) as approved,
        SUM(CASE WHEN status = 'ongoing' THEN 1 ELSE 0 END) as ongoing,
        SUM(CASE WHEN status = 'completed' THEN 1 ELSE 0 END) as completed
    FROM peminjaman_kendaraan 
    WHERE peminjam_id = ?
" );
$peminjaman_stats->bind_param('i', $user_id);
$peminjaman_stats->execute();
$stats = $peminjaman_stats->get_result()->fetch_assoc();
$peminjaman_stats->close();

// Peminjaman aktif (approved atau ongoing)
$active_peminjaman = $mysqli->prepare("
    SELECT p.*, k.no_reg, k.merk, k.tipe 
    FROM peminjaman_kendaraan p
    LEFT JOIN kendaraan k ON p.kendaraan_id = k.id
    WHERE p.peminjam_id = ? AND p.status IN ('approved', 'ongoing')
    ORDER BY p.tanggal_mulai ASC
    LIMIT 3
");
$active_peminjaman->bind_param('i', $user_id);
$active_peminjaman->execute();
$active_loans = $active_peminjaman->get_result()->fetch_all(MYSQLI_ASSOC);
$active_peminjaman->close();

// Riwayat peminjaman terbaru
$recent_peminjaman = $mysqli->prepare("
    SELECT p.*, k.no_reg, k.merk, k.tipe 
    FROM peminjaman_kendaraan p
    LEFT JOIN kendaraan k ON p.kendaraan_id = k.id
    WHERE p.peminjam_id = ? 
    ORDER BY p.created_at DESC
    LIMIT 5
");
$recent_peminjaman->bind_param('i', $user_id);
$recent_peminjaman->execute();
$recent_loans = $recent_peminjaman->get_result()->fetch_all(MYSQLI_ASSOC);
$recent_peminjaman->close();

// Statistik kendaraan user (legacy dari pengguna_kendaraan) - schema-aware dengan fallback
$pengguna_id = $user_info['id'] ?? 0; // pengguna.id
$total_kendaraan = 0;
if ($pengguna_id) {
    if (table_exists_db($mysqli, 'pengguna_kendaraan')) {
        $legacy_stats = $mysqli->prepare("
            SELECT COUNT(*) as total_kendaraan 
            FROM pengguna_kendaraan pk 
            JOIN kendaraan k ON pk.kendaraan_id = k.id 
            WHERE pk.pengguna_id = ? AND pk.status = 'Digunakan'
        ");
        if ($legacy_stats) {
            $legacy_stats->bind_param('i', $pengguna_id);
            $legacy_stats->execute();
            $legacy_stats->bind_result($total_kendaraan);
            $legacy_stats->fetch();
            $legacy_stats->close();
        }
    } else {
        // Fallback: hitung kendaraan terkait user dari peminjaman_kendaraan dan surat_tugas
        $active_vehicle_ids = [];

        // 1) peminjaman_kendaraan dengan status approved/ongoing
        if (table_exists_db($mysqli, 'peminjaman_kendaraan')) {
            $cols = get_table_columns($mysqli, 'peminjaman_kendaraan');
            $borrower_col = null;
            foreach (['peminjam_id','pemohon_id','pengguna_id','user_id'] as $c) {
                if (in_array($c, $cols, true)) { $borrower_col = $c; break; }
            }
            if ($borrower_col && in_array('kendaraan_id', $cols, true) && in_array('status', $cols, true)) {
                // gunakan user_id jika kolom mengacu ke akun, jika tidak gunakan pengguna_id
                $bind_id = in_array($borrower_col, ['peminjam_id','pemohon_id','user_id'], true) ? $user_id : $pengguna_id;
                $sql = "SELECT DISTINCT kendaraan_id FROM peminjaman_kendaraan WHERE {$borrower_col} = ? AND LOWER(status) IN ('approved','ongoing')";
                if ($st = $mysqli->prepare($sql)) {
                    $st->bind_param('i', $bind_id);
                    $st->execute();
                    if ($res = $st->get_result()) {
                        while ($r = $res->fetch_assoc()) { $active_vehicle_ids[] = (int)$r['kendaraan_id']; }
                    }
                    $st->close();
                }
            }
        }

        // 2) surat_tugas status Disetujui/Dalam Perjalanan untuk pengguna
        if (table_exists_db($mysqli, 'surat_tugas')) {
            $cols = get_table_columns($mysqli, 'surat_tugas');
            if (in_array('pengguna_id', $cols, true) && in_array('kendaraan_id', $cols, true) && in_array('status', $cols, true)) {
                $sql2 = "SELECT DISTINCT kendaraan_id FROM surat_tugas WHERE pengguna_id = ? AND status IN ('Disetujui','Dalam Perjalanan')";
                if ($st2 = $mysqli->prepare($sql2)) {
                    $st2->bind_param('i', $pengguna_id);
                    $st2->execute();
                    if ($res2 = $st2->get_result()) {
                        while ($r2 = $res2->fetch_assoc()) { $active_vehicle_ids[] = (int)$r2['kendaraan_id']; }
                    }
                    $st2->close();
                }
            }
        }

        $total_kendaraan = count(array_unique($active_vehicle_ids));
    }
}

// Total riwayat pemakaian - schema-aware dengan fallback
$total_riwayat = 0;
if ($pengguna_id) {
    if (table_exists_db($mysqli, 'riwayat_pemakaian') && table_exists_db($mysqli, 'pengguna_kendaraan')) {
        $riwayat_stats = $mysqli->prepare("
            SELECT COUNT(*) as total_riwayat 
            FROM riwayat_pemakaian rp 
            JOIN pengguna_kendaraan pk ON rp.kendaraan_id = pk.kendaraan_id 
            WHERE pk.pengguna_id = ?
        ");
        if ($riwayat_stats) {
            $riwayat_stats->bind_param('i', $pengguna_id);
            $riwayat_stats->execute();
            $riwayat_stats->bind_result($total_riwayat);
            $riwayat_stats->fetch();
            $riwayat_stats->close();
        }
    } elseif (table_exists_db($mysqli, 'peminjaman_kendaraan')) {
        // Fallback: gunakan jumlah peminjaman milik user sebagai "total riwayat"
        $cols = get_table_columns($mysqli, 'peminjaman_kendaraan');
        $borrower_col = null;
        foreach (['peminjam_id','pemohon_id','user_id','pengguna_id'] as $c) { if (in_array($c, $cols, true)) { $borrower_col = $c; break; } }
        if ($borrower_col) {
            $use_user_account_id = in_array($borrower_col, ['peminjam_id','pemohon_id','user_id'], true);
            $bind_id = $use_user_account_id ? $user_id : $pengguna_id;
            $sql = "SELECT COUNT(*) as c FROM peminjaman_kendaraan WHERE {$borrower_col} = ?";
            if ($st = $mysqli->prepare($sql)) {
                $st->bind_param('i', $bind_id);
                $st->execute();
                if ($res = $st->get_result()) {
                    $row = $res->fetch_assoc();
                    $total_riwayat = (int)($row['c'] ?? 0);
                }
                $st->close();
            }
        }
    }
}

// Log BBM bulan ini - perbaiki sesuai nama kolom yang benar
$bbm_stats = $mysqli->prepare("
    SELECT COUNT(*) as log_bbm_bulan_ini
    FROM log_bahan_bakar lb
    WHERE lb.user_id = ? AND MONTH(lb.tanggal_isi) = MONTH(CURRENT_DATE()) AND YEAR(lb.tanggal_isi) = YEAR(CURRENT_DATE())
");
$bbm_stats->bind_param('i', $user_id);
$bbm_stats->execute();
$bbm_stats->bind_result($log_bbm_bulan_ini);
$bbm_stats->fetch();
$bbm_stats->close();

// Get user notifications
$user_notifications = [];
$notif_query = $mysqli->prepare("
    SELECT n.*, 
           CASE WHEN n.read_at IS NULL THEN 'info' ELSE 'success' END as type,
           COALESCE(n.title, 'Notifikasi') as title,
           COALESCE(n.message, '') as message
    FROM notifikasi n 
    WHERE n.user_id = ? 
    ORDER BY n.created_at DESC 
    LIMIT 5
");
$notif_query->bind_param('i', $user_id);
$notif_query->execute();
$user_notifications = $notif_query->get_result()->fetch_all(MYSQLI_ASSOC);
$notif_query->close();

// Status badge function
function getStatusBadge($status) {
    $badges = [
        'pending' => 'badge-warning',
        'approved' => 'badge-success', 
        'rejected' => 'badge-danger',
        'ongoing' => 'badge-info',
        'completed' => 'badge-primary',
        'cancelled' => 'badge-secondary'
    ];
    
    $labels = [
        'pending' => 'Menunggu',
        'approved' => 'Disetujui',
        'rejected' => 'Ditolak', 
        'ongoing' => 'Berlangsung',
        'completed' => 'Selesai',
        'cancelled' => 'Dibatalkan'
    ];
    
    $badge_class = $badges[$status] ?? 'badge-secondary';
    $label = $labels[$status] ?? $status;
    
    return "<span class=\"badge $badge_class\">$label</span>";
}
?>

<div id="dashboard-user-page">
    <div class="dashboard-header">
        <div class="welcome-section">
            <h1><i class="fas fa-tachometer-alt"></i> Dashboard User</h1>
            <p class="welcome-text">Selamat datang, <strong><?= htmlspecialchars($user_display_name) ?></strong></p>
            <div class="user-info">
                <span class="info-item"><i class="fas fa-user"></i> <?= htmlspecialchars($user_username) ?></span>
                <?php if (!empty($user_nama_kesatuan)): ?>
                    <span class="info-item"><i class="fas fa-building"></i> <?= htmlspecialchars($user_nama_kesatuan) ?></span>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <?php
    // Show H-1 reminders for logged-in pengguna (works for both user and driver)
    $pengguna_id = $user_info['id'] ?? (int)get_current_user_id();
    $upcoming_items = build_upcoming_items($pengguna_id);
    ?>
    <div class="row mb-3">
        <div class="col-md-6">
            <div class="card">
                <div class="card-header">
                    <h6 class="mb-0">Pengingat Besok</h6>
                </div>
                <div class="card-body">
                    <?php if (!empty($upcoming_items)): ?>
                        <ul class="list-unstyled mb-0">
                        <?php foreach ($upcoming_items as $it): ?>
                            <li class="mb-2">
                                <div class="fw-semibold"><?= htmlspecialchars($it['type']) ?> • <?= htmlspecialchars($it['label']) ?></div>
                                <div class="small text-muted"><?= date('d/m/Y', strtotime($it['date'])) ?> • <?= htmlspecialchars($it['note']) ?></div>
                            </li>
                        <?php endforeach; ?>
                        </ul>
                    <?php else: ?>
                        <div class="text-muted">Tidak ada pengingat untuk besok.</div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>

    <!-- Peminjaman Statistics -->
    <!-- <div class="dashboard-stats">
        <div class="stat-card pending">
            <div class="stat-icon">
                <i class="fas fa-clock"></i>
            </div>
            <div class="stat-content">
                <h3><?= $stats['pending'] ?? 0 ?></h3>
                <p>Pengajuan Menunggu</p>
            </div>
        </div>

        <div class="stat-card approved">
            <div class="stat-icon">
                <i class="fas fa-check"></i>
            </div>
            <div class="stat-content">
                <h3><?= $stats['approved'] ?? 0 ?></h3>
                <p>Disetujui</p>
            </div>
        </div>

        <div class="stat-card ongoing">
            <div class="stat-icon">
                <i class="fas fa-car"></i>
            </div>
            <div class="stat-content">
                <h3><?= $stats['ongoing'] ?? 0 ?></h3>
                <p>Sedang Dipinjam</p>
            </div>
        </div>

        <div class="stat-card completed">
            <div class="stat-icon">
                <i class="fas fa-check-circle"></i>
            </div>
            <div class="stat-content">
                <h3><?= $stats['completed'] ?? 0 ?></h3>
                <p>Selesai</p>
            </div>
        </div>
    </div> -->

    <div class="dashboard-content">
        <!-- Active Loans -->
        <div class="content-section">
            <div class="card">
                <div class="card-header">
                    <h3><i class="fas fa-car"></i> Peminjaman Aktif</h3>
                    <a href="index.php?page=riwayat_peminjaman" class="btn btn-outline-primary btn-sm">Lihat Semua</a>
                </div>
                <div class="card-body">
                    <?php if (count($active_loans) > 0): ?>
                        <?php foreach ($active_loans as $loan): ?>
                            <div class="loan-item active-loan">
                                <div class="loan-vehicle">
                                    <strong><?= htmlspecialchars($loan['no_reg'] ?? '-') ?></strong>
                                    <small><?= htmlspecialchars($loan['merk']) ?> <?= htmlspecialchars($loan['tipe']) ?></small>
                                </div>
                                <div class="loan-details">
                                    <div class="loan-purpose"><?= htmlspecialchars($loan['keperluan']) ?></div>
                                    <div class="loan-period">
                                        <?= date('d/m/Y H:i', strtotime($loan['tanggal_mulai'])) ?> - 
                                        <?= date('d/m/Y H:i', strtotime($loan['tanggal_selesai'])) ?>
                                    </div>
                                </div>
                                <div class="loan-status">
                                    <?= getStatusBadge($loan['status']) ?>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <div class="empty-state">
                            <i class="fas fa-car"></i>
                            <p>Tidak ada peminjaman aktif</p>
                            <a href="index.php?page=form_peminjaman" class="btn btn-primary btn-sm">
                                Ajukan Peminjaman
                            </a>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>

        <!-- Recent Loans History -->
        <div class="content-section">
            <div class="card">
                <div class="card-header">
                    <h3><i class="fas fa-history"></i> Riwayat Peminjaman Terbaru</h3>
                    <a href="index.php?page=riwayat_peminjaman" class="btn btn-outline-primary btn-sm">Lihat Semua</a>
                </div>
                <div class="card-body">
                    <?php if (count($recent_loans) > 0): ?>
                        <?php foreach ($recent_loans as $loan): ?>
                            <div class="loan-item">
                                <div class="loan-vehicle">
                                    <strong><?= htmlspecialchars($loan['no_reg'] ?? '-') ?></strong>
                                    <small><?= htmlspecialchars($loan['merk']) ?> <?= htmlspecialchars($loan['tipe']) ?></small>
                                </div>
                                <div class="loan-details">
                                    <div class="loan-purpose"><?= htmlspecialchars($loan['keperluan']) ?></div>
                                    <div class="loan-date">
                                        Diajukan: <?= date('d/m/Y H:i', strtotime($loan['created_at'])) ?>
                                    </div>
                                </div>
                                <div class="loan-status">
                                    <?= getStatusBadge($loan['status']) ?>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <div class="empty-state">
                            <i class="fas fa-history"></i>
                            <p>Belum ada riwayat peminjaman</p>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>

        <!-- Notifications -->
        <div class="content-section">
            <div class="card">
                <div class="card-header">
                    <h3><i class="fas fa-bell"></i> Notifikasi Terbaru</h3>
                </div>
                <div class="card-body">
                    <?php if (count($user_notifications) > 0): ?>
                        <?php foreach ($user_notifications as $notif): ?>
                                <?php $is_read = !empty($notif['read_at']); ?>
                                <div class="notification-item <?= $is_read ? 'read' : 'unread' ?>">
                                    <div class="notif-icon notif-<?= htmlspecialchars($notif['type'] ?? 'info') ?>">
                                        <i class="fas fa-<?= ($notif['type'] ?? '') === 'success' ? 'check' : (($notif['type'] ?? '') === 'danger' ? 'times' : 'info') ?>"></i>
                                    </div>
                                    <div class="notif-content">
                                        <h4><?= htmlspecialchars($notif['title'] ?? 'Notifikasi') ?></h4>
                                        <p><?= htmlspecialchars($notif['message'] ?? '') ?></p>
                                        <small><?= !empty($notif['created_at']) ? date('d/m/Y H:i', strtotime($notif['created_at'])) : '' ?></small>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                    <?php else: ?>
                        <div class="empty-state">
                            <i class="fas fa-bell-slash"></i>
                            <p>Tidak ada notifikasi</p>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>

    <!-- Legacy Stats (for backward compatibility) -->
    <div class="dashboard-stats legacy-stats">
        <div class="stat-card">
            <div class="stat-icon">
                <i class="fas fa-car"></i>
            </div>
            <div class="stat-content">
                <h3><?= $total_kendaraan ?></h3>
                <p>Kendaraan Aktif (Legacy)</p>
            </div>
        </div>
        
        <div class="stat-card">
            <div class="stat-icon">
                <i class="fas fa-history"></i>
            </div>
            <div class="stat-content">
                <h3><?= $total_riwayat ?></h3>
                <p>Total Riwayat</p>
            </div>
        </div>
        
        <div class="stat-card">
            <div class="stat-icon">
                <i class="fas fa-gas-pump"></i>
            </div>
            <div class="stat-content">
                <h3><?= $log_bbm_bulan_ini ?></h3>
                <p>Log BBM Bulan Ini</p>
            </div>
        </div>
    </div>

    <div class="quick-actions-section">
        <h2><i class="fas fa-bolt"></i> Menu Cepat</h2>
        <div class="actions-grid">
            <a href="index.php?page=kendaraan_saya" class="action-card">
                <div class="action-icon">
                    <i class="fas fa-car"></i>
                </div>
                <h3>Kendaraan Saya</h3>
                <p>Lihat kendaraan yang ditugaskan</p>
            </a>
            
            <a href="index.php?page=riwayat_kendaraan" class="action-card">
                <div class="action-icon">
                    <i class="fas fa-history"></i>
                </div>
                <h3>Riwayat Kendaraan</h3>
                <p>Lihat riwayat pemakaian kendaraan</p>
            </a>
            
            <a href="index.php?page=log_bahan_bakar" class="action-card">
                <div class="action-icon">
                    <i class="fas fa-gas-pump"></i>
                </div>
                <h3>Log BBM</h3>
                <p>Catat penggunaan bahan bakar</p>
            </a>
            
            <a href="index.php?page=profil" class="action-card">
                <div class="action-icon">
                    <i class="fas fa-user"></i>
                </div>
                <h3>Profil</h3>
                <p>Lihat dan edit profil</p>
            </a>
        </div>
    </div>
</div>

<style>
#dashboard-user-page .dashboard-header {
    background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
    color: white;
    padding: 2rem;
    border-radius: 12px;
    margin-bottom: 2rem;
    display: flex;
    justify-content: space-between;
    align-items: center;
}

#dashboard-user-page .welcome-section h1 {
    margin: 0 0 0.5rem 0;
    font-size: 2rem;
}

#dashboard-user-page .welcome-text {
    margin: 0 0 1rem 0;
    opacity: 0.9;
}

#dashboard-user-page .user-info {
    display: flex;
    gap: 1.5rem;
}

#dashboard-user-page .info-item {
    display: flex;
    align-items: center;
    gap: 0.5rem;
    opacity: 0.8;
}

#dashboard-user-page .quick-actions {
    display: flex;
    gap: 1rem;
}

#dashboard-user-page .dashboard-stats {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
    gap: 1.5rem;
    margin-bottom: 2rem;
}

#dashboard-user-page .stat-card {
    background: white;
    border-radius: 12px;
    padding: 1.5rem;
    box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1);
    display: flex;
    align-items: center;
    gap: 1rem;
    transition: transform 0.2s ease, box-shadow 0.2s ease;
}

#dashboard-user-page .stat-card:hover {
    transform: translateY(-2px);
    box-shadow: 0 8px 15px rgba(0, 0, 0, 0.15);
}

#dashboard-user-page .stat-card.pending { border-left: 4px solid #ffc107; }
#dashboard-user-page .stat-card.approved { border-left: 4px solid #28a745; }
#dashboard-user-page .stat-card.ongoing { border-left: 4px solid #17a2b8; }
#dashboard-user-page .stat-card.completed { border-left: 4px solid #007bff; }

#dashboard-user-page .stat-icon {
    font-size: 2.5rem;
    opacity: 0.7;
}

#dashboard-user-page .stat-card.pending .stat-icon { color: #ffc107; }
#dashboard-user-page .stat-card.approved .stat-icon { color: #28a745; }
#dashboard-user-page .stat-card.ongoing .stat-icon { color: #17a2b8; }
#dashboard-user-page .stat-card.completed .stat-icon { color: #007bff; }

#dashboard-user-page .stat-content h3 {
    margin: 0;
    font-size: 2.5rem;
    font-weight: bold;
    color: #333;
}

#dashboard-user-page .stat-content p {
    margin: 0;
    color: #666;
    font-size: 0.9rem;
}

#dashboard-user-page .dashboard-content {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(400px, 1fr));
    gap: 2rem;
    margin-bottom: 2rem;
}

#dashboard-user-page .content-section .card {
    background: white;
    border-radius: 12px;
    box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1);
    overflow: hidden;
}

#dashboard-user-page .card-header {
    background: #f8f9fa;
    padding: 1rem 1.5rem;
    border-bottom: 1px solid #e9ecef;
    display: flex;
    justify-content: space-between;
    align-items: center;
}

#dashboard-user-page .card-header h3 {
    margin: 0;
    font-size: 1.1rem;
    color: #333;
}

#dashboard-user-page .card-body {
    padding: 1.5rem;
}

#dashboard-user-page .loan-item {
    display: flex;
    align-items: center;
    gap: 1rem;
    padding: 1rem;
    border: 1px solid #e9ecef;
    border-radius: 8px;
    margin-bottom: 1rem;
    transition: background-color 0.2s ease;
}

#dashboard-user-page .loan-item:hover {
    background-color: #f8f9fa;
}

#dashboard-user-page .loan-item:last-child {
    margin-bottom: 0;
}

#dashboard-user-page .active-loan {
    border-left: 4px solid #17a2b8;
    background-color: rgba(23, 162, 184, 0.05);
}

#dashboard-user-page .loan-vehicle {
    flex: 0 0 150px;
}

#dashboard-user-page .loan-vehicle strong {
    color: #007bff;
    display: block;
}

#dashboard-user-page .loan-vehicle small {
    color: #666;
    font-size: 0.85rem;
}

#dashboard-user-page .loan-details {
    flex: 1;
}

#dashboard-user-page .loan-purpose {
    font-weight: 600;
    margin-bottom: 0.25rem;
}

#dashboard-user-page .loan-period, #dashboard-user-page .loan-date {
    font-size: 0.85rem;
    color: #666;
}

#dashboard-user-page .loan-status {
    flex: 0 0 auto;
}

#dashboard-user-page .notification-item {
    display: flex;
    gap: 1rem;
    padding: 1rem;
    border: 1px solid #e9ecef;
    border-radius: 8px;
    margin-bottom: 1rem;
}

#dashboard-user-page .notification-item:last-child {
    margin-bottom: 0;
}

#dashboard-user-page .notification-item.unread {
    background-color: #f8f9fa;
    border-left: 4px solid #007bff;
}

#dashboard-user-page .notif-icon {
    width: 40px;
    height: 40px;
    border-radius: 50%;
    display: flex;
    align-items: center;
    justify-content: center;
    flex-shrink: 0;
}

#dashboard-user-page .notif-success { background-color: #d4edda; color: #155724; }
#dashboard-user-page .notif-danger { background-color: #f8d7da; color: #721c24; }
#dashboard-user-page .notif-info { background-color: #d1ecf1; color: #0c5460; }

#dashboard-user-page .notif-content h4 {
    margin: 0 0 0.5rem 0;
    font-size: 1rem;
}

#dashboard-user-page .notif-content p {
    margin: 0 0 0.5rem 0;
    color: #666;
    font-size: 0.9rem;
}

#dashboard-user-page .notif-content small {
    color: #999;
    font-size: 0.8rem;
}

#dashboard-user-page .empty-state {
    text-align: center;
    padding: 2rem;
    color: #666;
}

#dashboard-user-page .empty-state i {
    font-size: 3rem;
    margin-bottom: 1rem;
    opacity: 0.5;
}

#dashboard-user-page .legacy-stats {
    border-top: 1px solid #e9ecef;
    padding-top: 2rem;
    margin-top: 2rem;
}

#dashboard-user-page .quick-actions-section {
    margin-top: 2rem;
}

#dashboard-user-page .quick-actions-section h2 {
    margin-bottom: 1.5rem;
    color: #333;
}

#dashboard-user-page .actions-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
    gap: 1.5rem;
}

#dashboard-user-page .action-card {
    background: white;
    border: 1px solid #e9ecef;
    border-radius: 12px;
    padding: 1.5rem;
    text-decoration: none;
    color: inherit;
    transition: all 0.2s ease;
    text-align: center;
}

#dashboard-user-page .action-card:hover {
    transform: translateY(-2px);
    box-shadow: 0 8px 15px rgba(0, 0, 0, 0.15);
    text-decoration: none;
    color: inherit;
}

#dashboard-user-page .action-icon {
    font-size: 2.5rem;
    margin-bottom: 1rem;
    color: #007bff;
}

#dashboard-user-page .action-card h3 {
    margin: 0 0 0.5rem 0;
    color: #333;
}

#dashboard-user-page .action-card p {
    margin: 0;
    color: #666;
    font-size: 0.9rem;
}

#dashboard-user-page .badge {
    padding: 0.4rem 0.8rem;
    border-radius: 12px;
    font-size: 0.85rem;
    font-weight: 500;
}

#dashboard-user-page .badge-warning { background-color: #ffc107; color: #212529; }
#dashboard-user-page .badge-success { background-color: #28a745; color: white; }
#dashboard-user-page .badge-danger { background-color: #dc3545; color: white; }
#dashboard-user-page .badge-info { background-color: #17a2b8; color: white; }
#dashboard-user-page .badge-primary { background-color: #007bff; color: white; }
#dashboard-user-page .badge-secondary { background-color: #6c757d; color: white; }

@media (max-width: 768px) {
    #dashboard-user-page .dashboard-header {
        flex-direction: column;
        text-align: center;
        gap: 1rem;
    }
    
    #dashboard-user-page .dashboard-content {
        grid-template-columns: 1fr;
    }
    
    #dashboard-user-page .loan-item {
        flex-direction: column;
        align-items: flex-start;
    }
    
    #dashboard-user-page .user-info {
        flex-direction: column;
        gap: 0.5rem;
    }
}

.user-info {
    display: flex;
    gap: 1.5rem;
    flex-wrap: wrap;
}

.info-item {
    display: flex;
    align-items: center;
    gap: 0.5rem;
    font-size: 0.9rem;
    background: rgba(255, 255, 255, 0.1);
    padding: 0.5rem 1rem;
    border-radius: 20px;
}

.dashboard-stats {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
    gap: 1.5rem;
    margin-bottom: 2rem;
}

.stat-card {
    background: white;
    padding: 1.5rem;
    border-radius: 12px;
    box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1);
    display: flex;
    align-items: center;
    gap: 1rem;
    transition: transform 0.2s;
}

.stat-card:hover {
    transform: translateY(-2px);
}

.stat-icon {
    background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
    color: white;
    width: 60px;
    height: 60px;
    border-radius: 12px;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 1.5rem;
}

.stat-info h3 {
    margin: 0 0 0.25rem 0;
    font-size: 2rem;
    color: #2c3e50;
}

.stat-info p {
    margin: 0;
    color: #7f8c8d;
    font-size: 0.9rem;
}

.quick-actions {
    background: white;
    padding: 2rem;
    border-radius: 12px;
    box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1);
}

.quick-actions h2 {
    margin: 0 0 1.5rem 0;
    color: #2c3e50;
    font-size: 1.5rem;
}

.actions-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
    gap: 1.5rem;
}

.action-card {
    background: #f8f9fa;
    padding: 1.5rem;
    border-radius: 12px;
    text-decoration: none;
    color: inherit;
    transition: all 0.2s;
    border: 2px solid transparent;
    text-align: center;
}

.action-card:hover {
    background: #667eea;
    color: white;
    transform: translateY(-4px);
    box-shadow: 0 8px 25px rgba(102, 126, 234, 0.3);
    text-decoration: none;
}

.action-icon {
    background: #667eea;
    color: white;
    width: 60px;
    height: 60px;
    border-radius: 50%;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 1.5rem;
    margin: 0 auto 1rem auto;
    transition: all 0.2s;
}

.action-card:hover .action-icon {
    background: white;
    color: #667eea;
    transform: scale(1.1);
}

.action-card h3 {
    margin: 0 0 0.5rem 0;
    font-size: 1.1rem;
}

.action-card p {
    margin: 0;
    font-size: 0.9rem;
    opacity: 0.8;
}

@media (max-width: 768px) {
    .dashboard-header {
        padding: 1.5rem;
    }
    
    .user-info {
        gap: 1rem;
    }
    
    .dashboard-stats {
        grid-template-columns: 1fr;
    }
    
    .actions-grid {
        grid-template-columns: repeat(auto-fit, minmax(150px, 1fr));
    }
    
    .quick-actions {
        padding: 1.5rem;
    }
}
</style>
