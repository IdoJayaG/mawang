<?php
require_once '../config.php';
require_once '../config/db.php';

// Defensive wrapper: ensure any PHP warning/notice/fatal is returned as JSON
// Start output buffering so we can discard accidental output
ob_start();

function send_json_and_exit($arr) {
    if (ob_get_length()) ob_end_clean();
    header('Content-Type: application/json');
    echo json_encode($arr);
    exit();
}

set_error_handler(function($severity, $message, $file, $line) {
    // Convert PHP warnings/notices into exceptions to be handled below
    throw new ErrorException($message, 0, $severity, $file, $line);
});

register_shutdown_function(function() {
    $err = error_get_last();
    if ($err && in_array($err['type'], [E_ERROR, E_PARSE, E_CORE_ERROR, E_COMPILE_ERROR])) {
        if (ob_get_length()) ob_end_clean();
        header('Content-Type: application/json');
        echo json_encode(['success' => false, 'message' => 'Fatal error: ' . ($err['message'] ?? 'unknown')]);
    }
});

try {
    if (!is_logged_in()) {
        send_json_and_exit(['success' => false, 'message' => 'User not logged in']);
    }

    $id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
    if (!$id) {
        send_json_and_exit(['success' => false, 'message' => 'ID tidak valid']);
    }

    // Determine the correct creator column (created_by or pembuat_id), fallback to pengguna_id if neither exists
    $creatorJoinCol = 's.pengguna_id';
    try {
        if ($resCol = $mysqli->query("SHOW COLUMNS FROM surat_tugas LIKE 'created_by'")) {
            if ($resCol->num_rows > 0) { $creatorJoinCol = 's.created_by'; }
            $resCol->free();
        }
        if ($creatorJoinCol === 's.pengguna_id') {
            if ($resCol2 = $mysqli->query("SHOW COLUMNS FROM surat_tugas LIKE 'pembuat_id'")) {
                if ($resCol2->num_rows > 0) { $creatorJoinCol = 's.pembuat_id'; }
                $resCol2->free();
            }
        }
    } catch (Throwable $e) {
        // keep fallback
    }

    // Get surat_tugas with creator (pembuat) and kendaraan
    $sql = "SELECT s.*, k.no_polisi, k.merk, k.tipe,
                   pc.nama_lengkap as pembuat_name, pc.pangkat as pembuat_pangkat
            FROM surat_tugas s
            LEFT JOIN kendaraan k ON s.kendaraan_id = k.id
            LEFT JOIN pengguna pc ON $creatorJoinCol = pc.id
            WHERE s.id = ?";
    $stmt = $mysqli->prepare($sql);
    if (!$stmt) throw new Exception('DB prepare failed: ' . $mysqli->error);
    $stmt->bind_param('i', $id);
    $stmt->execute();
    $res = $stmt->get_result();
    if ($res->num_rows === 0) {
        send_json_and_exit(['success' => false, 'message' => 'Data tidak ditemukan']);
    }

    $row = $res->fetch_assoc();
    $stmt->close();

    // Authorization: recipient or creator or admin/operator
    $current = get_logged_in_user();
    $role = get_current_role();
    $owner_id = (int)($row['penerima_id'] ?? $row['pengguna_id'] ?? 0);
    if ($current['id'] !== $owner_id && !in_array($role, ['admin','operator'])) {
        send_json_and_exit(['success' => false, 'message' => 'Unauthorized']);
    }

    function safe($v) { return htmlspecialchars($v ?? ''); }
    function fmt($dt) { return $dt ? date('d/m/Y H:i', strtotime($dt)) : '-'; }

    // Normalize and localize status label
    $rawStatus = isset($row['status']) ? strtolower($row['status']) : '';
    $statusMap = [
        'draft' => 'Draft',
        'pending' => 'Menunggu',
        'approved' => 'Disetujui',
        'disetujui' => 'Disetujui',
        'rejected' => 'Ditolak',
        'ongoing' => 'Dalam Perjalanan',
        'dalam perjalanan' => 'Dalam Perjalanan',
        'completed' => 'Selesai',
        'selesai' => 'Selesai',
        'dibatalkan' => 'Dibatalkan'
    ];
    $labelStatus = $statusMap[$rawStatus] ?? ($row['status'] ?? '');

    // Fetch driver name if driver_id exists on the surat_tugas row
    $driverName = '';
    if (!empty($row['driver_id'])) {
        $drv = $mysqli->prepare("SELECT nama_lengkap FROM pengguna WHERE id = ? LIMIT 1");
        if ($drv) {
            $drv->bind_param('i', $row['driver_id']);
            $drv->execute();
            $dres = $drv->get_result();
            if ($dres && $dres->num_rows) {
                $drow = $dres->fetch_assoc();
                $driverName = $drow['nama_lengkap'] ?? '';
            }
            $drv->close();
        }
    }

    ob_start();
    ?>
    <div class="row">
        <div class="col-md-6">
            <div class="card mb-2">
                <div class="card-header bg-primary text-white"><strong>Pembuat</strong></div>
                <div class="card-body small">
                    <p class="mb-1"><strong>Nama:</strong> <?= safe($row['pembuat_name'] ?? '') ?></p>
                    <p class="mb-1"><strong>Pangkat:</strong> <?= safe($row['pembuat_pangkat'] ?? '') ?></p>
                </div>
            </div>
        </div>
        <div class="col-md-6">
            <div class="card mb-2">
                <div class="card-header bg-success text-white"><strong>Kendaraan</strong></div>
                <div class="card-body small">
                    <p class="mb-1"><strong>No. Polisi:</strong> <?= safe($row['no_polisi'] ?? '') ?></p>
                    <p class="mb-1"><strong>Merk/Model:</strong> <?= safe(($row['merk'] ?? '') . ' ' . ($row['tipe'] ?? '')) ?></p>
                    <?php if (!empty(
                        $driverName
                    )): ?>
                        <p class="mb-1"><strong>Driver:</strong> <?= safe($driverName) ?></p>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>

    <div class="card mb-2">
        <div class="card-header bg-info text-white"><strong>Detail Surat Tugas</strong></div>
        <div class="card-body small">
            <p class="mb-1"><strong>Nomor:</strong> <?= safe($row['nomor_surat']) ?></p>
            <p class="mb-1"><strong>Perihal:</strong> <?= safe($row['perihal'] ?? $row['judul_tugas'] ?? '') ?></p>
            <p class="mb-1"><strong>Tempat:</strong> <?= safe($row['tempat_tugas'] ?? '') ?></p>
            <p class="mb-1"><strong>Waktu Berangkat:</strong> <?= fmt($row['tanggal_berangkat'] ?? $row['tanggal_mulai'] ?? null) ?></p>
            <p class="mb-1"><strong>Waktu Selesai:</strong> <?= fmt($row['tanggal_kembali'] ?? $row['tanggal_selesai'] ?? null) ?></p>
            <p class="mb-1"><strong>Status:</strong> <?= safe($labelStatus) ?></p>
        </div>
        <div class="card-footer text-right">
            <a href="ajax/generate_surat_tugas_pdf.php?id=<?= (int)$row['id'] ?>" target="_blank" class="btn btn-sm btn-secondary"><i class="fas fa-download"></i> PDF</a>
        </div>
    </div>

    <?php
    $html = ob_get_clean();
    send_json_and_exit(['success' => true, 'html' => $html]);

} catch (Throwable $e) {
    // Return error message as JSON and ensure no stray output
    send_json_and_exit(['success' => false, 'message' => $e->getMessage()]);
}

