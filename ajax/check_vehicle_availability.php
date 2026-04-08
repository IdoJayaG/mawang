<?php
require_once '../config/db.php';
require_once '../includes/auth.php';

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['error' => 'Method not allowed']);
    exit;
}

$input = json_decode(file_get_contents('php://input'), true);

if (!isset($input['kendaraan_id']) || !isset($input['datetime_mulai']) || !isset($input['datetime_selesai'])) {
    echo json_encode(['error' => 'Missing required parameters']);
    exit;
}

$kendaraan_id = (int)$input['kendaraan_id'];
$datetime_mulai = $input['datetime_mulai'];
$datetime_selesai = $input['datetime_selesai'];

try {
    $conflict_descriptions = [];

    // 1) Conflicts from jadwal_kendaraan (if table exists)
    $has_jk = $mysqli->query("SHOW TABLES LIKE 'jadwal_kendaraan'");
    if ($has_jk && $has_jk->num_rows > 0) {
        $check_query = "
            SELECT jk.*, 
                   CASE 
                       WHEN jk.tipe_penggunaan = 'peminjaman_terjadwal' THEN CONCAT('Peminjaman Terjadwal: ', pt.keperluan)
                       WHEN jk.tipe_penggunaan = 'pinjam_pakai' THEN CONCAT('Pinjam Pakai: ', pp.keperluan)
                       WHEN jk.tipe_penggunaan = 'surat_tugas' THEN CONCAT('Surat Tugas: ', st.judul_tugas)
                       WHEN jk.tipe_penggunaan = 'maintenance' THEN 'Maintenance Kendaraan'
                       ELSE jk.keterangan
                   END as conflict_description,
                   p.nama_lengkap as user_name
            FROM jadwal_kendaraan jk
            LEFT JOIN peminjaman_terjadwal pt ON jk.tipe_penggunaan = 'peminjaman_terjadwal' AND jk.referensi_id = pt.id
            LEFT JOIN pinjam_pakai pp ON jk.tipe_penggunaan = 'pinjam_pakai' AND jk.referensi_id = pp.id
            LEFT JOIN surat_tugas st ON jk.tipe_penggunaan = 'surat_tugas' AND jk.referensi_id = st.id
            LEFT JOIN pengguna p ON jk.pengguna_id = p.id
            WHERE jk.kendaraan_id = ? 
            AND jk.status IN ('scheduled', 'active')
            AND (
                (jk.tanggal_mulai <= ? AND jk.tanggal_selesai >= ?) OR
                (jk.tanggal_mulai <= ? AND jk.tanggal_selesai >= ?) OR
                (jk.tanggal_mulai >= ? AND jk.tanggal_selesai <= ?)
            )
        ";
        $stmt = $mysqli->prepare($check_query);
        $stmt->bind_param('issssss', $kendaraan_id, $datetime_mulai, $datetime_mulai, $datetime_selesai, $datetime_selesai, $datetime_mulai, $datetime_selesai);
        $stmt->execute();
        $conflicts = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
        $stmt->close();
        foreach ($conflicts as $conflict) {
            $start = date('d/m/Y H:i', strtotime($conflict['tanggal_mulai']));
            $end = date('d/m/Y H:i', strtotime($conflict['tanggal_selesai']));
            $user = $conflict['user_name'] ? (' oleh ' . $conflict['user_name']) : '';
            $conflict_descriptions[] = $conflict['conflict_description'] . $user . " ($start - $end)";
        }
    }

    // 2) Conflicts from peminjaman_kendaraan (Approved/Ongoing)
    $has_pk = $mysqli->query("SHOW TABLES LIKE 'peminjaman_kendaraan'");
    if ($has_pk && $has_pk->num_rows > 0) {
        $sql = "SELECT pk.tanggal_mulai, pk.tanggal_selesai, u.nama_lengkap FROM peminjaman_kendaraan pk LEFT JOIN pengguna u ON u.id = pk.peminjam_id OR u.id = pk.pemohon_id WHERE pk.kendaraan_id = ? AND pk.status IN ('Approved','approved','Ongoing','ongoing') AND ((pk.tanggal_mulai <= ? AND pk.tanggal_selesai >= ?) OR (pk.tanggal_mulai <= ? AND pk.tanggal_selesai >= ?) OR (pk.tanggal_mulai >= ? AND pk.tanggal_selesai <= ?))";
        if ($stmt = $mysqli->prepare($sql)) {
            $stmt->bind_param('issssss', $kendaraan_id, $datetime_mulai, $datetime_mulai, $datetime_selesai, $datetime_selesai, $datetime_mulai, $datetime_selesai);
            $stmt->execute();
            $res = $stmt->get_result();
            while ($r = $res->fetch_assoc()) {
                $start = date('d/m/Y H:i', strtotime($r['tanggal_mulai']));
                $end = date('d/m/Y H:i', strtotime($r['tanggal_selesai']));
                $by = $r['nama_lengkap'] ? (' oleh ' . $r['nama_lengkap']) : '';
                $conflict_descriptions[] = 'Peminjaman kendaraan' . $by . " ($start - $end)";
            }
            $stmt->close();
        }
    }

    // 3) Conflicts from surat_tugas (Disetujui/Dalam Perjalanan)
    $has_st = $mysqli->query("SHOW TABLES LIKE 'surat_tugas'");
    if ($has_st && $has_st->num_rows > 0) {
        $sql = "SELECT st.tanggal_berangkat, COALESCE(st.tanggal_kembali, st.tanggal_berangkat) as tanggal_kembali, st.judul_tugas FROM surat_tugas st WHERE st.kendaraan_id = ? AND st.status IN ('Disetujui','Dalam Perjalanan') AND ((st.tanggal_berangkat <= ? AND COALESCE(st.tanggal_kembali, st.tanggal_berangkat) >= ?) OR (st.tanggal_berangkat <= ? AND COALESCE(st.tanggal_kembali, st.tanggal_berangkat) >= ?) OR (st.tanggal_berangkat >= ? AND COALESCE(st.tanggal_kembali, st.tanggal_berangkat) <= ?))";
        if ($stmt = $mysqli->prepare($sql)) {
            $stmt->bind_param('issssss', $kendaraan_id, $datetime_mulai, $datetime_mulai, $datetime_selesai, $datetime_selesai, $datetime_mulai, $datetime_selesai);
            $stmt->execute();
            $res = $stmt->get_result();
            while ($r = $res->fetch_assoc()) {
                $start = date('d/m/Y H:i', strtotime($r['tanggal_berangkat']));
                $end = date('d/m/Y H:i', strtotime($r['tanggal_kembali']));
                $title = $r['judul_tugas'] ? (': ' . $r['judul_tugas']) : '';
                $conflict_descriptions[] = 'Surat Tugas' . $title . " ($start - $end)";
            }
            $stmt->close();
        }
    }

    // 4) Conflicts from jadwal_perawatan (not finished/cancelled)
    $has_jp = $mysqli->query("SHOW TABLES LIKE 'jadwal_perawatan'");
    if ($has_jp && $has_jp->num_rows > 0) {
        $sql = "SELECT COALESCE(jp.tanggal_perawatan, jp.jadwal_tanggal) as start_at, IFNULL(jp.tanggal_selesai, COALESCE(jp.tanggal_perawatan, jp.jadwal_tanggal)) as end_at, jp.jenis_perawatan FROM jadwal_perawatan jp WHERE jp.kendaraan_id = ? AND COALESCE(jp.tanggal_perawatan, jp.jadwal_tanggal) IS NOT NULL AND (jp.status IS NULL OR jp.status NOT IN ('Selesai','Dibatalkan')) AND ((COALESCE(jp.tanggal_perawatan, jp.jadwal_tanggal) <= ? AND IFNULL(jp.tanggal_selesai, COALESCE(jp.tanggal_perawatan, jp.jadwal_tanggal)) >= ?) OR (COALESCE(jp.tanggal_perawatan, jp.jadwal_tanggal) <= ? AND IFNULL(jp.tanggal_selesai, COALESCE(jp.tanggal_perawatan, jp.jadwal_tanggal)) >= ?) OR (COALESCE(jp.tanggal_perawatan, jp.jadwal_tanggal) >= ? AND IFNULL(jp.tanggal_selesai, COALESCE(jp.tanggal_perawatan, jp.jadwal_tanggal)) <= ?))";
        if ($stmt = $mysqli->prepare($sql)) {
            $stmt->bind_param('issssss', $kendaraan_id, $datetime_mulai, $datetime_mulai, $datetime_selesai, $datetime_selesai, $datetime_mulai, $datetime_selesai);
            $stmt->execute();
            $res = $stmt->get_result();
            while ($r = $res->fetch_assoc()) {
                $start = date('d/m/Y H:i', strtotime($r['start_at']));
                $end = date('d/m/Y H:i', strtotime($r['end_at']));
                $jenis = $r['jenis_perawatan'] ? (': ' . $r['jenis_perawatan']) : '';
                $conflict_descriptions[] = 'Perawatan/Perbaikan Kendaraan' . $jenis . " ($start - $end)";
            }
            $stmt->close();
        }
    }

    if (count($conflict_descriptions) > 0) {
        echo json_encode([
            'available' => false,
            'conflicts' => $conflict_descriptions
        ]);
    } else {
        echo json_encode([
            'available' => true,
            'message' => 'Kendaraan tersedia pada waktu yang dipilih'
        ]);
    }
    
} catch (Exception $e) {
    echo json_encode(['error' => 'Database error: ' . $e->getMessage()]);
}
?>
