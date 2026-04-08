<?php
// Include global template (anchored)
require_once __DIR__ . '/../templates/page_template.php';

require_once __DIR__ . '/../includes/auth.php';
require_login();
require_role('user');

$user_id = get_current_user_id();
$kendaraan_id = isset($_GET['id']) ? intval($_GET['id']) : 0;

// Hilangkan ketergantungan pada pengguna_kendaraan (tabel dihapus)
$kendaraan = null;
// Helper: cek tabel exist
$tblExists = function($name) use ($mysqli) {
    try {
        $n = $mysqli->real_escape_string($name);
        $r = $mysqli->query("SHOW TABLES LIKE '".$n."'");
        $ok = $r && $r->num_rows > 0; if ($r) $r->free_result(); return $ok;
    } catch (mysqli_sql_exception $e) { return false; }
};

// Cek apakah kendaraan ditugaskan ke user ini via peminjaman_kendaraan atau surat_tugas
$kendaraan = null;
// Try peminjaman_kendaraan first (borrower may be user_account.id)
if ($tblExists('peminjaman_kendaraan')) {
    $cols = [];
    if ($res = $mysqli->query("SHOW COLUMNS FROM peminjaman_kendaraan")) { while ($c=$res->fetch_assoc()) $cols[]=$c['Field']; $res->free_result(); }
    $borrower = null; foreach (['peminjam_id','pemohon_id','user_id','pengguna_id'] as $c) { if (in_array($c,$cols,true)) { $borrower=$c; break; } }
    if ($borrower) {
        // borrower: if uses user_account id (peminjam_id/pemohon_id/user_id) then bind session user_account id; if 'pengguna_id' then map from user_account
        $bindId = in_array($borrower, ['peminjam_id','pemohon_id','user_id'], true)
            ? (int)($_SESSION['user_id'] ?? 0)
            : (function() use($mysqli){ $ua = (int)($_SESSION['user_id'] ?? 0); $st=$mysqli->prepare("SELECT pengguna_id FROM user_account WHERE id=?"); if($st){$st->bind_param('i',$ua);$st->execute();$row=$st->get_result()->fetch_assoc();$st->close(); return (int)($row['pengguna_id']??0);} return 0; })();

        // pilih kolom keterangan yang tersedia pada peminjaman_kendaraan (keterangan atau tujuan)
        $ketField = in_array('keterangan', $cols, true) ? 'keterangan' : (in_array('tujuan', $cols, true) ? 'tujuan' : null);
        $ketSelect = $ketField ? "COALESCE(p.$ketField,'') as keterangan" : "'' as keterangan";

        $sql = "SELECT k.*, p.tanggal_mulai, p.tanggal_selesai, $ketSelect, p.status as status_assignment FROM kendaraan k JOIN peminjaman_kendaraan p ON k.id=p.kendaraan_id WHERE k.id=? AND p.{$borrower}=?";
        if ($st = $mysqli->prepare($sql)) {
            $st->bind_param('ii', $kendaraan_id, $bindId);
            $st->execute();
            $kendaraan = $st->get_result()->fetch_assoc();
            $st->close();
        }
    }
}
// Fallback to surat_tugas
if (!$kendaraan && $tblExists('surat_tugas')) {
    // get pengguna_id from user_account
    $pengguna_id = 0; if ($st=$mysqli->prepare("SELECT pengguna_id FROM user_account WHERE id=?")) { $st->bind_param('i',$user_id); $st->execute(); $row=$st->get_result()->fetch_assoc(); $pengguna_id=(int)($row['pengguna_id']??0); $st->close(); }
    $sql = "SELECT k.*, st.tanggal_berangkat as tanggal_mulai, st.tanggal_kembali as tanggal_selesai, COALESCE(st.keperluan,'') as keterangan, st.status as status_assignment FROM kendaraan k JOIN surat_tugas st ON k.id = st.kendaraan_id WHERE k.id = ? AND st.pengguna_id = ?";
    if ($st2 = $mysqli->prepare($sql)) {
        $st2->bind_param('ii', $kendaraan_id, $pengguna_id);
        $st2->execute();
        $kendaraan = $st2->get_result()->fetch_assoc();
        $st2->close();
    }
}

// Fallback ke kepemilikan langsung: kendaraan.pengguna_id = pengguna saat ini
if (!$kendaraan) {
    // Ambil pengguna_id dari user_account (jika session menyimpan id user_account)
    $pengguna_id = 0;
    if ($st=$mysqli->prepare("SELECT pengguna_id FROM user_account WHERE id=?")) {
        $st->bind_param('i', $user_id);
        $st->execute();
        $row=$st->get_result()->fetch_assoc();
        $pengguna_id=(int)($row['pengguna_id']??0);
        $st->close();
    }
    if ($pengguna_id > 0) {
        $sql = "SELECT k.*, k.status_peminjaman AS status_assignment FROM kendaraan k WHERE k.id = ? AND k.pengguna_id = ? LIMIT 1";
        if ($st3 = $mysqli->prepare($sql)) {
            $st3->bind_param('ii', $kendaraan_id, $pengguna_id);
            $st3->execute();
            $kendaraan = $st3->get_result()->fetch_assoc();
            $st3->close();
            if ($kendaraan) {
                // Pastikan field penugasan tersedia agar template aman
                if (!array_key_exists('tanggal_mulai', $kendaraan)) { $kendaraan['tanggal_mulai'] = null; }
                if (!array_key_exists('tanggal_selesai', $kendaraan)) { $kendaraan['tanggal_selesai'] = null; }
                if (!array_key_exists('keterangan', $kendaraan)) { $kendaraan['keterangan'] = null; }
            }
        }
    }
}

if (!$kendaraan) {
    header('Location: index.php?page=kendaraan_saya');
    exit;
}

// Ambil riwayat pemakaian kendaraan - tanpa pengguna_id filter
$stmt = $mysqli->prepare("
    SELECT rp.* 
    FROM riwayat_pemakaian rp 
    WHERE rp.kendaraan_id = ?
    ORDER BY rp.tanggal DESC 
    LIMIT 10
");
$stmt->bind_param('i', $kendaraan_id);
$stmt->execute();
$riwayat_pemakaian = $stmt->get_result();

// Ambil log BBM untuk kendaraan ini oleh user
$stmt = $mysqli->prepare("
    SELECT lb.*, p.nama_lengkap as pengguna_nama 
    FROM log_bahan_bakar lb 
    LEFT JOIN pengguna p ON lb.pengguna_id = p.id
    WHERE lb.kendaraan_id = ? AND lb.pengguna_id = ?
    ORDER BY lb.tanggal_isi DESC 
    LIMIT 10
");
$stmt->bind_param('ii', $kendaraan_id, $user_id);
$stmt->execute();
$log_bbm = $stmt->get_result();
?>

<div class="page-header">
    <div class="header-content">
        <div class="header-info">
            <h1><i class="fas fa-car"></i> Detail Kendaraan</h1>
            <p>
                <?= htmlspecialchars($kendaraan['merk'] . ' ' . $kendaraan['tipe']) ?> - <strong>No. Reg:</strong> <?= htmlspecialchars($kendaraan['no_reg'] ?: '-') ?>
            </p>
        </div>
        <div class="header-actions">
            <a href="index.php?page=kendaraan_saya" class="btn btn-outline">
                <i class="fas fa-arrow-left"></i> Kembali
            </a>
        </div>
    </div>
</div>

<div class="detail-container">
    <!-- Informasi Kendaraan -->
    <div class="detail-card">
        <div class="card-header">
            <h3><i class="fas fa-info-circle"></i> Informasi Kendaraan</h3>
            <div class="status-badges">
                <span class="badge badge-<?= strtolower($kendaraan['status_kendaraan']) ?>">
                    <?= htmlspecialchars($kendaraan['status_kendaraan']) ?>
                </span>
                <span class="badge badge-<?= strtolower($kendaraan['status_assignment']) ?>">
                    <?= htmlspecialchars($kendaraan['status_assignment']) ?>
                </span>
            </div>
        </div>
        <div class="card-body">
            <div class="info-grid">
                <div class="info-item">
                    <label><i class="fas fa-hashtag"></i> Nomor Registrasi</label>
                    <span><?= htmlspecialchars($kendaraan['no_reg'] ?: '-') ?></span>
                </div>
                <div class="info-item">
                    <label><i class="fas fa-car"></i> Merk & Tipe</label>
                    <span><?= htmlspecialchars($kendaraan['merk'] . ' ' . $kendaraan['tipe']) ?></span>
                </div>
                <div class="info-item">
                    <label><i class="fas fa-calendar"></i> Tahun</label>
                    <span><?= htmlspecialchars($kendaraan['tahun_pembuatan']) ?></span>
                </div>
                <div class="info-item">
                    <label><i class="fas fa-palette"></i> Warna</label>
                    <span><?= htmlspecialchars($kendaraan['warna']) ?></span>
                </div>
                <div class="info-item">
                    <label><i class="fas fa-gas-pump"></i> Bahan Bakar</label>
                    <span><?= htmlspecialchars($kendaraan['bahan_bakar']) ?></span>
                </div>
                <div class="info-item">
                    <label><i class="fas fa-tools"></i> Kondisi</label>
                    <span class="badge badge-<?= strtolower($kendaraan['kondisi']) ?>">
                        <?= htmlspecialchars($kendaraan['kondisi']) ?>
                    </span>
                </div>
                <div class="info-item">
                    <label><i class="fas fa-cog"></i> Nomor Mesin</label>
                    <span><?= htmlspecialchars($kendaraan['no_mesin']) ?></span>
                </div>
                <div class="info-item">
                    <label><i class="fas fa-barcode"></i> Nomor Rangka</label>
                    <span><?= htmlspecialchars($kendaraan['no_rangka']) ?></span>
                </div>
            </div>
        </div>
    </div>

    <!-- Informasi Penugasan -->
    <div class="detail-card">
        <div class="card-header">
            <h3><i class="fas fa-calendar-alt"></i> Informasi Penugasan</h3>
        </div>
        <div class="card-body">
            <div class="assignment-grid">
                <div class="assignment-item">
                    <label>Tanggal Mulai:</label>
                    <span><?= date('d/m/Y', strtotime($kendaraan['tanggal_mulai'])) ?></span>
                </div>
                <?php if ($kendaraan['tanggal_selesai']): ?>
                    <div class="assignment-item">
                        <label>Tanggal Selesai:</label>
                        <span><?= date('d/m/Y', strtotime($kendaraan['tanggal_selesai'])) ?></span>
                    </div>
                <?php else: ?>
                    <div class="assignment-item">
                        <label>Status:</label>
                        <span class="badge badge-success">Sedang Berlangsung</span>
                    </div>
                <?php endif; ?>
                <?php if ($kendaraan['keterangan']): ?>
                    <div class="assignment-item full-width">
                        <label>Keterangan:</label>
                        <span><?= htmlspecialchars($kendaraan['keterangan']) ?></span>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <!-- Quick Actions -->
    <div class="detail-card">
        <div class="card-header">
            <h3><i class="fas fa-bolt"></i> Aksi Cepat</h3>
        </div>
        <div class="card-body">
            <div class="quick-actions">
                <a href="index.php?page=log_bahan_bakar&kendaraan_id=<?= $kendaraan['id'] ?>" class="action-btn">
                    <i class="fas fa-gas-pump"></i>
                    <span>Log BBM</span>
                </a>
                <a href="index.php?page=riwayat_kendaraan&kendaraan_id=<?= $kendaraan['id'] ?>" class="action-btn">
                    <i class="fas fa-history"></i>
                    <span>Riwayat Lengkap</span>
                </a>
            </div>
        </div>
    </div>

    <!-- Riwayat Pemakaian Terbaru -->
    <div class="detail-card">
        <div class="card-header">
            <h3><i class="fas fa-history"></i> Riwayat Pemakaian Terbaru</h3>
            <a href="index.php?page=riwayat_kendaraan&kendaraan_id=<?= $kendaraan['id'] ?>" class="btn btn-sm btn-outline">
                Lihat Semua
            </a>
        </div>
        <div class="card-body">
            <?php if ($riwayat_pemakaian->num_rows > 0): ?>
                <div class="riwayat-list">
                    <?php while ($riwayat = $riwayat_pemakaian->fetch_assoc()): ?>
                        <div class="riwayat-item">
                            <div class="riwayat-date">
                                <i class="fas fa-calendar"></i>
                                <?= date('d/m/Y', strtotime($riwayat['tanggal'])) ?>
                            </div>
                            <div class="riwayat-info">
                                <div class="riwayat-detail">
                                    <span class="label">Tujuan:</span>
                                    <span><?= htmlspecialchars($riwayat['tujuan']) ?></span>
                                </div>
                                <div class="riwayat-detail">
                                    <span class="label">KM:</span>
                                    <span><?= number_format($riwayat['km_awal']) ?> - <?= number_format($riwayat['km_akhir']) ?> 
                                        (<?= number_format($riwayat['km_akhir'] - $riwayat['km_awal']) ?> km)</span>
                                </div>
                            </div>
                        </div>
                    <?php endwhile; ?>
                </div>
            <?php else: ?>
                <div class="empty-state-small">
                    <i class="fas fa-history"></i>
                    <p>Belum ada riwayat pemakaian</p>
                </div>
            <?php endif; ?>
        </div>
    </div>

    <!-- Log BBM Terbaru -->
    <div class="detail-card">
        <div class="card-header">
            <h3><i class="fas fa-gas-pump"></i> Log BBM Terbaru</h3>
            <a href="index.php?page=log_bahan_bakar&kendaraan_id=<?= $kendaraan['id'] ?>" class="btn btn-sm btn-outline">
                Tambah Log
            </a>
        </div>
        <div class="card-body">
            <?php if ($log_bbm->num_rows > 0): ?>
                <div class="bbm-list">
                    <?php while ($bbm = $log_bbm->fetch_assoc()): ?>
                        <div class="bbm-item">
                            <div class="bbm-date">
                                <i class="fas fa-calendar"></i>
                                <?= date('d/m/Y', strtotime($bbm['tanggal_isi'])) ?>
                            </div>
                            <div class="bbm-info">
                                <div class="bbm-detail">
                                    <span class="label">Jumlah:</span>
                                    <span><?= number_format($bbm['jumlah_liter'], 2) ?> Liter</span>
                                </div>
                                <div class="bbm-detail">
                                    <span class="label">Biaya:</span>
                                    <span>Rp <?= number_format($bbm['total_biaya'] ?? 0) ?></span>
                                </div>
                                <div class="bbm-detail">
                                    <span class="label">KM:</span>
                                    <span><?= number_format($bbm['km_saat_isi'] ?? 0) ?></span>
                                </div>
                            </div>
                        </div>
                    <?php endwhile; ?>
                </div>
            <?php else: ?>
                <div class="empty-state-small">
                    <i class="fas fa-gas-pump"></i>
                    <p>Belum ada log BBM</p>
                </div>
            <?php endif; ?>
        </div>
    </div>
</div>


