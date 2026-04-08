<?php
require_once __DIR__ . '/../includes/auth.php';
require_login();
require_role('admin');

$user_id = get_current_user_id();
$msg = '';

// Helper: check if a table exists
function table_exists_admin($mysqli, $table) {
    $res = $mysqli->query("SHOW TABLES LIKE '" . $mysqli->real_escape_string($table) . "'");
    return $res && $res->num_rows > 0;
}

// New helper: adaptive notification inserter (works with notifikasi_advanced or notifikasi variants)
if (!function_exists('insert_notification')) {
    function insert_notification($mysqli, $user_id, $title, $message, $ref_id = null, $ref_type = null) {
        // prefer notifikasi_advanced
        $res = $mysqli->query("SHOW TABLES LIKE 'notifikasi_advanced'");
        if ($res && $res->num_rows > 0) {
            $stmt = $mysqli->prepare("INSERT INTO notifikasi_advanced (user_id, judul, pesan, jenis, ref_id, ref_type, created_at) VALUES (?, ?, ?, 'surat_tugas', ?, ?, NOW())");
            if ($stmt) {
                // types: i s s i s -> but ref_id/ref_type may be null; bind as integers/strings accordingly
                $rid = $ref_id ?? 0;
                $rtype = $ref_type ?? '';
                $stmt->bind_param('issi', $user_id, $title, $message, $rid);
                // bind_param cannot bind mixed optional easily for ref_type; use simple execute via escaped query as fallback
                $stmt->execute();
                $stmt->close();
            }
            return;
        }

        $res2 = $mysqli->query("SHOW TABLES LIKE 'notifikasi'");
        if ($res2 && $res2->num_rows > 0) {
            $cols = [];
            $r = $mysqli->query("SHOW COLUMNS FROM notifikasi");
            while ($row = $r->fetch_assoc()) {
                $cols[] = $row['Field'];
            }

            if (in_array('message', $cols) && in_array('title', $cols)) {
                $rid = $ref_id ?? 0;
                $rtype = $ref_type ?? '';
                $stmt = $mysqli->prepare("INSERT INTO notifikasi (user_id, title, message, ref_id, ref_type, created_at) VALUES (?, ?, ?, ?, ?, NOW())");
                if ($stmt) {
                    $stmt->bind_param('issis', $user_id, $title, $message, $rid, $rtype);
                    $stmt->execute();
                    $stmt->close();
                }
                return;
            }

            if (in_array('pesan', $cols)) {
                $stmt = $mysqli->prepare("INSERT INTO notifikasi (user_id, pesan, created_at) VALUES (?, ?, NOW())");
                if ($stmt) {
                    $stmt->bind_param('is', $user_id, $message);
                    $stmt->execute();
                    $stmt->close();
                }
                return;
            }
        }
        // nothing to do if no known table
    }
}

// Handle form submission
if ($_POST && isset($_POST['action'])) {
    if ($_POST['action'] === 'create_surat_tugas') {
        $penerima_id = (int)$_POST['penerima_id'];
        $kendaraan_id = (int)$_POST['kendaraan_id'];
        $judul_tugas = trim($_POST['judul_tugas']);
        $deskripsi_tugas = trim($_POST['deskripsi_tugas']);
        $tempat_tugas = trim($_POST['tempat_tugas']);
        $tanggal_mulai = $_POST['tanggal_mulai'];
        $tanggal_selesai = $_POST['tanggal_selesai'];
        $prioritas = $_POST['prioritas'];
        
        if ($penerima_id && $kendaraan_id && $judul_tugas && $deskripsi_tugas && $tempat_tugas && $tanggal_mulai && $tanggal_selesai && $prioritas) {
            // Generate nomor surat
            $tahun = date('Y');
            $bulan_romawi = ['I', 'II', 'III', 'IV', 'V', 'VI', 'VII', 'VIII', 'IX', 'X', 'XI', 'XII'][date('n') - 1];
            $count_result = $mysqli->query("SELECT COUNT(*) as count FROM surat_tugas WHERE YEAR(created_at) = YEAR(NOW())");
            $count = $count_result->fetch_assoc()['count'] + 1;
            $nomor_surat = sprintf("ST/%03d/%s/%s", $count, $bulan_romawi, $tahun);
            
            // Check vehicle availability
            // Defensive availability check: only if jadwal_kendaraan exists
            $conflicts = 0;
            $has_jadwal = table_exists_admin($mysqli, 'jadwal_kendaraan');
            if ($has_jadwal) {
                $check_availability = $mysqli->prepare("SELECT COUNT(*) as conflicts FROM jadwal_kendaraan jk WHERE jk.kendaraan_id = ? AND jk.status IN ('aktif', 'menunggu_konfirmasi') AND ((jk.tanggal_mulai <= ? AND jk.tanggal_selesai >= ?) OR (jk.tanggal_mulai <= ? AND jk.tanggal_selesai >= ?) OR (jk.tanggal_mulai >= ? AND jk.tanggal_selesai <= ?))");
                $check_availability->bind_param('issssss', $kendaraan_id, $tanggal_mulai, $tanggal_mulai, $tanggal_selesai, $tanggal_selesai, $tanggal_mulai, $tanggal_selesai);
                $check_availability->execute();
                $check_availability->bind_result($conflicts);
                $check_availability->fetch();
                $check_availability->close();
            }

            if ($conflicts > 0) {
                $msg = '<div class="alert alert-danger">Kendaraan tidak tersedia pada periode yang dipilih. Silakan pilih kendaraan lain atau ubah jadwal.</div>';
            } else {
                    $stmt = $mysqli->prepare("
                        INSERT INTO surat_tugas (nomor_surat, created_by, pengguna_id, kendaraan_id, perihal, keperluan, berangkat_dari, tanggal_berangkat, tanggal_kembali, status) 
                        VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, 'menunggu_konfirmasi')
                    ");
                    // types: s (nomor), i (created_by), i (pengguna_id), i (kendaraan_id), s (perihal), s (keperluan), s (berangkat_dari), s (tanggal_berangkat), s (tanggal_kembali)
                    $stmt->bind_param('siiisssss', $nomor_surat, $user_id, $penerima_id, $kendaraan_id, $judul_tugas, $deskripsi_tugas, $tempat_tugas, $tanggal_mulai, $tanggal_selesai);
                
                if ($stmt->execute()) {
                    $surat_tugas_id = $mysqli->insert_id;

                    // Create jadwal kendaraan entry (only if jadwal_kendaraan exists)
                    $keterangan = "Penugasan: " . $judul_tugas;
                    if (table_exists_admin($mysqli, 'jadwal_kendaraan')) {
                        $jadwal_stmt = $mysqli->prepare(
                            "INSERT INTO jadwal_kendaraan (kendaraan_id, user_id, tanggal_mulai, tanggal_selesai, jenis_penggunaan, status, keterangan, ref_id, ref_type) VALUES (?, ?, ?, ?, 'penugasan', 'menunggu_konfirmasi', ?, ?, 'surat_tugas')"
                        );
                        if ($jadwal_stmt) {
                            // bind types: kendaraan_id (i), penerima_id (i), tanggal_mulai (s), tanggal_selesai (s), keterangan (s), surat_tugas_id (i)
                            $jadwal_stmt->bind_param("iisssi", $kendaraan_id, $penerima_id, $tanggal_mulai, $tanggal_selesai, $keterangan, $surat_tugas_id);
                            $jadwal_stmt->execute();
                            $jadwal_stmt->close();
                        }
                    }

                    // Create notification (adaptive)
                    $pesan = "Anda mendapat surat tugas baru: " . $judul_tugas . " menggunakan kendaraan pada " . date('d/m/Y', strtotime($tanggal_mulai));
                    insert_notification($mysqli, $penerima_id, 'Surat Tugas Baru', $pesan, $surat_tugas_id, 'surat_tugas');

                    $msg = '<div class="alert alert-success">Surat tugas berhasil dibuat dengan nomor: ' . $nomor_surat . '</div>';
                } else {
                    $msg = '<div class="alert alert-danger">Gagal membuat surat tugas</div>';
                }
            }
        } else {
            $msg = '<div class="alert alert-danger">Semua field harus diisi</div>';
        }
    } elseif ($_POST['action'] === 'approve_surat_tugas') {
        $surat_id = (int)$_POST['surat_id'];
        $approval_status = $_POST['approval_status'];
        $catatan_admin = trim($_POST['catatan_admin']);

        // If approving, first validate no overlaps with peminjaman/surat tugas/maintenance
        if ($approval_status === 'disetujui') {
            $s = $mysqli->prepare("SELECT kendaraan_id, pengguna_id, tanggal_berangkat, COALESCE(tanggal_kembali, tanggal_berangkat) AS tanggal_kembali FROM surat_tugas WHERE id = ? LIMIT 1");
            if ($s) {
                $s->bind_param('i', $surat_id);
                $s->execute();
                $r = $s->get_result();
                $sur = $r->fetch_assoc();
                $s->close();
            } else { $sur = null; }

            if ($sur) {
                $kendaraan_id = (int)$sur['kendaraan_id'];
                $tgl_mulai_date = $sur['tanggal_berangkat'];
                $tgl_selesai_date = $sur['tanggal_kembali'];
                $start_dt = $tgl_mulai_date . ' 00:00:00';
                $end_dt = $tgl_selesai_date . ' 23:59:59';

                // 1) Check peminjaman_kendaraan Approved/Ongoing overlap
                $conf1 = 0;
                if ($stmt1 = $mysqli->prepare("SELECT COUNT(*) c FROM peminjaman_kendaraan WHERE kendaraan_id = ? AND LOWER(status) IN ('approved','ongoing') AND tanggal_mulai <= ? AND tanggal_selesai >= ?")) {
                    $stmt1->bind_param('iss', $kendaraan_id, $end_dt, $start_dt);
                    $stmt1->execute();
                    $stmt1->bind_result($conf1);
                    $stmt1->fetch();
                    $stmt1->close();
                }

                // 2) Check other surat_tugas (Disetujui/Dalam Perjalanan) overlap excluding current
                $conf2 = 0;
                if ($stmt2 = $mysqli->prepare("SELECT COUNT(*) c FROM surat_tugas WHERE id <> ? AND kendaraan_id = ? AND status IN ('Disetujui','Dalam Perjalanan') AND tanggal_berangkat <= ? AND (tanggal_kembali IS NULL OR tanggal_kembali >= ?)")) {
                    $stmt2->bind_param('iiss', $surat_id, $kendaraan_id, $tgl_selesai_date, $tgl_mulai_date);
                    $stmt2->execute();
                    $stmt2->bind_result($conf2);
                    $stmt2->fetch();
                    $stmt2->close();
                }

                // 3) Check active maintenance/perbaikan overlap
                $conf3 = 0; $conf4 = 0;
                if ($stmt3 = $mysqli->prepare("SELECT COUNT(*) c FROM jadwal_perawatan WHERE kendaraan_id = ? AND LOWER(status) IN ('terjadwal','dalam proses') AND tanggal_perawatan BETWEEN ? AND ?")) {
                    $stmt3->bind_param('iss', $kendaraan_id, $tgl_mulai_date, $tgl_selesai_date);
                    $stmt3->execute();
                    $stmt3->bind_result($conf3);
                    $stmt3->fetch();
                    $stmt3->close();
                }
                if ($stmt4 = $mysqli->prepare("SELECT COUNT(*) c FROM riwayat_perbaikan WHERE kendaraan_id = ? AND LOWER(status) <> 'selesai' AND tanggal_perbaikan BETWEEN ? AND ?")) {
                    $stmt4->bind_param('iss', $kendaraan_id, $tgl_mulai_date, $tgl_selesai_date);
                    $stmt4->execute();
                    $stmt4->bind_result($conf4);
                    $stmt4->fetch();
                    $stmt4->close();
                }

                if (($conf1 + $conf2 + $conf3 + $conf4) > 0) {
                    $msg = '<div class="alert alert-danger">Tidak dapat menyetujui: terdapat konflik jadwal (peminjaman/surat tugas lain/maintenance/perbaikan) pada periode tersebut.</div>';
                    goto skip_approval_execute;
                }
            }
        }

            // Map approval action to existing schema columns: update `status` and `updated_by`
            $new_status = $approval_status === 'disetujui' ? 'Disetujui' : 'Dibatalkan';
            $stmt = $mysqli->prepare("UPDATE surat_tugas SET status = ?, updated_by = ?, updated_at = NOW() WHERE id = ?");
            $stmt->bind_param("sii", $new_status, $user_id, $surat_id);
        
        if ($stmt->execute()) {
            // Update jadwal kendaraan status safely using prepared statement if table exists
            if (table_exists_admin($mysqli, 'jadwal_kendaraan')) {
                $upd = $mysqli->prepare("UPDATE jadwal_kendaraan SET status = ? WHERE ref_id = ? AND ref_type = 'surat_tugas'");
                $new_status = $approval_status === 'disetujui' ? 'aktif' : 'dibatalkan';
                $upd->bind_param('si', $new_status, $surat_id);
                $upd->execute();
                $upd->close();
            }

            // When approved, mark kendaraan assigned to penerima (update kendaraan status)
            if ($approval_status === 'disetujui') {
                // fetch surat details to know penerima_id and kendaraan_id
                // fetch using actual column names in schema
                $s = $mysqli->prepare("SELECT pengguna_id, kendaraan_id, tanggal_berangkat, tanggal_kembali FROM surat_tugas WHERE id = ? LIMIT 1");
                $s->bind_param('i', $surat_id);
                $s->execute();
                $res = $s->get_result();
                $sur = $res->fetch_assoc();
                $s->close();

                if ($sur) {
                    $penerima = (int)$sur['pengguna_id'];
                    $kendaraan = (int)$sur['kendaraan_id'];
                    $tgl_mulai = $sur['tanggal_berangkat'];
                    $tgl_selesai = $sur['tanggal_kembali'];

                    // Immediately mark vehicle as Dipinjam on approval
                    if ($kendaraan) {
                        $uk = $mysqli->prepare("UPDATE kendaraan SET status_peminjaman = 'Dipinjam' WHERE id = ?");
                        if ($uk) { $uk->bind_param('i', $kendaraan); $uk->execute(); $uk->close(); }
                    }

                    // Legacy pengguna_kendaraan upsert removed; UI derives from surat_tugas records.
                }
            } else {
                // Not approved: no legacy cleanup needed; vehicle status handled elsewhere if needed.
            }

            $msg = '<div class="alert alert-success">Status surat tugas berhasil diupdate</div>';
        } else {
            $msg = '<div class="alert alert-danger">Gagal mengupdate status surat tugas</div>';
        }
    skip_approval_execute:
    }
}

// Get users for dropdown (adapt to current schema: pengguna + user_account)
$users_result = $mysqli->query(
    "SELECT p.id, p.nama_lengkap as nama, COALESCE(r.nama_role, '') as role 
     FROM pengguna p 
     LEFT JOIN user_account ua ON p.id = ua.pengguna_id 
     LEFT JOIN role r ON ua.role_id = r.id 
     WHERE ua.status = 'Aktif' OR ua.status = 'aktif' 
     ORDER BY p.nama_lengkap"
);

// Get vehicles for dropdown
$vehicles_result = $mysqli->query("SELECT id, merk, tipe, no_polisi FROM kendaraan WHERE status_kendaraan = 'Operasional' ORDER BY merk, tipe");

// Get surat tugas list
$surat_tugas_result = $mysqli->query("
    SELECT st.*, 
           u_pembuat.nama_lengkap as pembuat_nama,
           u_penerima.nama_lengkap as penerima_nama,
          k.merk, k.tipe, k.no_polisi
    FROM surat_tugas st
    LEFT JOIN pengguna u_pembuat ON st.created_by = u_pembuat.id
    LEFT JOIN pengguna u_penerima ON st.pengguna_id = u_penerima.id
    LEFT JOIN kendaraan k ON st.kendaraan_id = k.id
    ORDER BY st.created_at DESC
");
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Penugasan Admin - RANDIS</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <style>
        .gradient-bg {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
        }
        .card-header {
            background: linear-gradient(135deg, #4CAF50 0%, #45a049 100%);
            color: white;
        }
        .btn-gradient {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            border: none;
            color: white;
        }
        .btn-gradient:hover {
            background: linear-gradient(135deg, #764ba2 0%, #667eea 100%);
            color: white;
        }
        .status-badge {
            padding: 0.25rem 0.75rem;
            border-radius: 50px;
            font-size: 0.875rem;
            font-weight: 500;
        }
        .modal-header {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
        }
    </style>
</head>
<body class="bg-light">
    <div class="container-fluid mt-4">
        <div class="row">
            <div class="col-12">
                <div class="card shadow">
                    <div class="card-header">
                        <h4 class="mb-0"><i class="fas fa-tasks me-2"></i>Penugasan Admin</h4>
                        <small>Kelola penugasan dan surat tugas untuk seluruh organisasi</small>
                    </div>
                    <div class="card-body">
                        <?php echo $msg; ?>
                        
                        <!-- Create New Assignment Button -->
                        <div class="mb-4">
                            <button type="button" class="btn btn-gradient btn-lg" data-bs-toggle="modal" data-bs-target="#createSuratTugasModal">
                                <i class="fas fa-plus me-2"></i>Buat Surat Tugas Baru
                            </button>
                        </div>

                        <!-- Surat Tugas List -->
                        <div class="table-responsive">
                            <table class="table table-hover">
                                <thead class="table-dark">
                                    <tr>
                                        <th>Nomor Surat</th>
                                        <th>Pembuat</th>
                                        <th>Penerima</th>
                                        <th>Kendaraan</th>
                                        <th>Judul Tugas</th>
                                        <th>Periode</th>
                                        <th>Status</th>
                                        <th>Aksi</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php while ($row = $surat_tugas_result->fetch_assoc()): ?>
                                    <tr>
                                        <td><strong><?php echo htmlspecialchars($row['nomor_surat']); ?></strong></td>
                                        <td><?php echo htmlspecialchars($row['pembuat_nama']); ?></td>
                                        <td><?php echo htmlspecialchars($row['penerima_nama']); ?></td>
                                        <td>
                                                <?php echo htmlspecialchars($row['merk'] . ' ' . $row['tipe']); ?><br>
                                                <small class="text-muted"><?php echo htmlspecialchars($row['no_polisi']); ?></small>
                                        </td>
                                        <td><?php echo htmlspecialchars($row['perihal']); ?></td>
                                        <td>
                                            <?php echo date('d/m/Y', strtotime($row['tanggal_berangkat'])); ?><br>
                                            <small class="text-muted">s/d <?php echo date('d/m/Y', strtotime($row['tanggal_kembali'])); ?></small>
                                        </td>
                                        <td>
                                            <?php
                                            $status_class = '';
                                            $status_text = '';
                                            switch($row['status']) {
                                                case 'menunggu_konfirmasi':
                                                    $status_class = 'bg-warning text-dark';
                                                    $status_text = 'Menunggu Konfirmasi';
                                                    break;
                                                case 'diterima':
                                                    if ($row['status_admin'] === 'disetujui') {
                                                        $status_class = 'bg-success';
                                                        $status_text = 'Disetujui Admin';
                                                    } elseif ($row['status_admin'] === 'ditolak') {
                                                        $status_class = 'bg-danger';
                                                        $status_text = 'Ditolak Admin';
                                                    } else {
                                                        $status_class = 'bg-info';
                                                        $status_text = 'Diterima, Menunggu Admin';
                                                    }
                                                    break;
                                                case 'ditolak':
                                                    $status_class = 'bg-secondary';
                                                    $status_text = 'Ditolak User';
                                                    break;
                                                case 'selesai':
                                                    $status_class = 'bg-dark';
                                                    $status_text = 'Selesai';
                                                    break;
                                            }
                                            ?>
                                            <span class="status-badge <?php echo $status_class; ?>"><?php echo $status_text; ?></span>
                                        </td>
                                        <td>
                                            <div class="btn-group btn-group-sm">
                                                <button class="btn btn-outline-primary" onclick="viewSuratTugas(<?php echo $row['id']; ?>)" title="Lihat Detail">
                                                    <i class="fas fa-eye"></i>
                                                </button>
                                                <?php if ($row['status'] === 'diterima' && empty($row['status_admin'])): ?>
                                                <button class="btn btn-outline-success" onclick="approveSuratTugas(<?php echo $row['id']; ?>)" title="Approve/Tolak">
                                                    <i class="fas fa-check"></i>
                                                </button>
                                                <?php endif; ?>
                                                <button class="btn btn-outline-info" onclick="printSuratTugas(<?php echo $row['id']; ?>)" title="Cetak PDF">
                                                    <i class="fas fa-print"></i>
                                                </button>
                                            </div>
                                        </td>
                                    </tr>
                                    <?php endwhile; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Create Surat Tugas Modal -->
    <div class="modal fade" id="createSuratTugasModal" tabindex="-1">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title"><i class="fas fa-file-alt me-2"></i>Buat Surat Tugas Baru</h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                </div>
                <form method="POST" id="createSuratTugasForm">
                    <input type="hidden" name="action" value="create_surat_tugas">
                    <div class="modal-body">
                        <div class="row">
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label class="form-label">Penerima Tugas *</label>
                                    <select name="penerima_id" class="form-select" required>
                                        <option value="">Pilih User</option>
                                        <?php 
                                        $users_result->data_seek(0);
                                        while ($user = $users_result->fetch_assoc()): 
                                        ?>
                                            <option value="<?php echo $user['id']; ?>">
                                                <?php echo htmlspecialchars($user['nama']); ?> (<?php echo ucfirst($user['role']); ?>)
                                            </option>
                                        <?php endwhile; ?>
                                    </select>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label class="form-label">Kendaraan *</label>
                                    <select name="kendaraan_id" class="form-select" required id="kendaraanSelect">
                                        <option value="">Pilih Kendaraan</option>
                                        <?php 
                                        $vehicles_result->data_seek(0);
                                        while ($vehicle = $vehicles_result->fetch_assoc()): 
                                        ?>
                                            <option value="<?php echo $vehicle['id']; ?>">
                                                <?php echo htmlspecialchars($vehicle['merk'] . ' ' . $vehicle['tipe']); ?> - <?php echo htmlspecialchars($vehicle['no_polisi']); ?>
                                            </option>
                                        <?php endwhile; ?>
                                    </select>
                                </div>
                            </div>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Judul Tugas *</label>
                            <input type="text" name="judul_tugas" class="form-control" required placeholder="Masukkan judul tugas">
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Deskripsi Tugas *</label>
                            <textarea name="deskripsi_tugas" class="form-control" rows="3" required placeholder="Jelaskan detail tugas yang akan dilakukan"></textarea>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Tempat Tugas *</label>
                            <input type="text" name="tempat_tugas" class="form-control" required placeholder="Lokasi pelaksanaan tugas">
                        </div>
                        <div class="row">
                            <div class="col-md-4">
                                <div class="mb-3">
                                    <label class="form-label">Tanggal Mulai *</label>
                                    <input type="datetime-local" name="tanggal_mulai" class="form-control" required id="tanggalMulai">
                                </div>
                            </div>
                            <div class="col-md-4">
                                <div class="mb-3">
                                    <label class="form-label">Tanggal Selesai *</label>
                                    <input type="datetime-local" name="tanggal_selesai" class="form-control" required id="tanggalSelesai">
                                </div>
                            </div>
                            <div class="col-md-4">
                                <div class="mb-3">
                                    <label class="form-label">Prioritas *</label>
                                    <select name="prioritas" class="form-select" required>
                                        <option value="">Pilih Prioritas</option>
                                        <option value="rendah">Rendah</option>
                                        <option value="normal">Normal</option>
                                        <option value="tinggi">Tinggi</option>
                                        <option value="urgent">Urgent</option>
                                    </select>
                                </div>
                            </div>
                        </div>
                        <div id="availabilityCheck" class="alert alert-info d-none">
                            <i class="fas fa-spinner fa-spin me-2"></i>Memeriksa ketersediaan kendaraan...
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Batal</button>
                        <button type="submit" class="btn btn-gradient">
                            <i class="fas fa-save me-2"></i>Buat Surat Tugas
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Approval Modal -->
    <div class="modal fade" id="approvalModal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title"><i class="fas fa-check-circle me-2"></i>Persetujuan Surat Tugas</h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                </div>
                <form method="POST" id="approvalForm">
                    <input type="hidden" name="action" value="approve_surat_tugas">
                    <input type="hidden" name="surat_id" id="approvalSuratId">
                    <div class="modal-body">
                        <div class="mb-3">
                            <label class="form-label">Keputusan *</label>
                            <select name="approval_status" class="form-select" required>
                                <option value="">Pilih Keputusan</option>
                                <option value="disetujui">Setujui</option>
                                <option value="ditolak">Tolak</option>
                            </select>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Catatan Admin</label>
                            <textarea name="catatan_admin" class="form-control" rows="3" placeholder="Berikan catatan atau alasan keputusan"></textarea>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Batal</button>
                        <button type="submit" class="btn btn-gradient">
                            <i class="fas fa-check me-2"></i>Simpan Keputusan
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        function checkVehicleAvailability() {
            const kendaraanId = document.getElementById('kendaraanSelect').value;
            const tanggalMulai = document.getElementById('tanggalMulai').value;
            const tanggalSelesai = document.getElementById('tanggalSelesai').value;
            
            if (kendaraanId && tanggalMulai && tanggalSelesai) {
                const checkDiv = document.getElementById('availabilityCheck');
                checkDiv.className = 'alert alert-info';
                checkDiv.innerHTML = '<i class="fas fa-spinner fa-spin me-2"></i>Memeriksa ketersediaan kendaraan...';
                
                fetch('../ajax/check_vehicle_availability.php', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/x-www-form-urlencoded',
                    },
                    body: `kendaraan_id=${kendaraanId}&tanggal_mulai=${tanggalMulai}&tanggal_selesai=${tanggalSelesai}`
                })
                .then(response => response.json())
                .then(data => {
                    if (data.available) {
                        checkDiv.className = 'alert alert-success';
                        checkDiv.innerHTML = '<i class="fas fa-check-circle me-2"></i>Kendaraan tersedia untuk periode yang dipilih';
                    } else {
                        checkDiv.className = 'alert alert-warning';
                        checkDiv.innerHTML = '<i class="fas fa-exclamation-triangle me-2"></i>Kendaraan tidak tersedia pada periode tersebut. ' + data.message;
                    }
                })
                .catch(error => {
                    checkDiv.className = 'alert alert-danger';
                    checkDiv.innerHTML = '<i class="fas fa-exclamation-circle me-2"></i>Error memeriksa ketersediaan kendaraan';
                });
            }
        }

        document.getElementById('kendaraanSelect').addEventListener('change', checkVehicleAvailability);
        document.getElementById('tanggalMulai').addEventListener('change', checkVehicleAvailability);
        document.getElementById('tanggalSelesai').addEventListener('change', checkVehicleAvailability);

        function approveSuratTugas(id) {
            document.getElementById('approvalSuratId').value = id;
            new bootstrap.Modal(document.getElementById('approvalModal')).show();
        }

        function viewSuratTugas(id) {
            window.open('surat_tugas_detail.php?id=' + id, '_blank');
        }

        function printSuratTugas(id) {
            window.open('surat_tugas_pdf.php?id=' + id, '_blank');
        }

        // Set minimum date to today
        const today = new Date().toISOString().slice(0, 16);
        document.getElementById('tanggalMulai').min = today;
        document.getElementById('tanggalSelesai').min = today;

        // Update minimum end date when start date changes
        document.getElementById('tanggalMulai').addEventListener('change', function() {
            document.getElementById('tanggalSelesai').min = this.value;
        });
    </script>
</body>
</html>
