<?php
require_once '../config.php';
require_once '../config/db.php';

header('Content-Type: application/json');

try {
    if (!is_logged_in()) throw new Exception('User not logged in');
    $current_user = get_logged_in_user();
    $input = json_decode(file_get_contents('php://input'), true);
    if (!$input || empty($input['id'])) throw new Exception('Invalid input');
    $id = (int)$input['id'];
    $reason = isset($input['reason']) ? trim($input['reason']) : null;

    // Fetch the peminjaman record
    $stmt = $mysqli->prepare('SELECT * FROM peminjaman_kendaraan WHERE id = ?');
    $stmt->bind_param('i', $id);
    $stmt->execute();
    $res = $stmt->get_result();
    if ($res->num_rows === 0) throw new Exception('Peminjaman tidak ditemukan');
    $row = $res->fetch_assoc();
    $stmt->close();

    // Only owner or admin/operator may cancel; owners may cancel only when pending
    $is_owner = ((int)($row['pemohon_id'] ?? $row['peminjam_id'] ?? $row['created_by'] ?? 0) === (int)$current_user['id']);
    $role = get_current_role();

    if ($is_owner && $row['status'] === 'pending') {
        $new_status = 'cancelled';
    } elseif (in_array($role, ['admin', 'operator'])) {
        $new_status = 'cancelled';
    } else {
        throw new Exception('Tidak berwenang membatalkan peminjaman ini');
    }

    // Attempt to persist reason to a reasonable column if available
    $cols_res = $mysqli->query("SHOW COLUMNS FROM peminjaman_kendaraan");
    $cols = [];
    if ($cols_res) {
        foreach ($cols_res->fetch_all(MYSQLI_ASSOC) as $r) $cols[] = $r['Field'];
    }

    // Prefer dedicated cancel columns, then admin note
    $preferred = ['cancel_reason', 'alasan_batal', 'alasan_pembatalan', 'catatan_pembatalan', 'catatan_admin'];
    $target_col = null;
    foreach ($preferred as $c) { if (in_array($c, $cols)) { $target_col = $c; break; } }

    if ($target_col) {
        // include reason column in update
        $sql = "UPDATE peminjaman_kendaraan SET status = ?, updated_at = NOW(), `{$target_col}` = ? WHERE id = ?";
        $update = $mysqli->prepare($sql);
        $update->bind_param('ssi', $new_status, $reason, $id);
    } else {
        // no suitable column: perform status update only and return SQL suggestion
        $update = $mysqli->prepare('UPDATE peminjaman_kendaraan SET status = ?, updated_at = NOW() WHERE id = ?');
        $update->bind_param('si', $new_status, $id);
    }

    if (!$update->execute()) throw new Exception('Gagal membatalkan: ' . $mysqli->error);
    $update->close();

    // Notify the owner
    $message = 'Peminjaman Anda telah dibatalkan';
    if (function_exists('insert_notification')) {
        insert_notification($mysqli, (int)($row['pemohon_id'] ?? $row['peminjam_id'] ?? $row['created_by'] ?? 0), $message, 'Peminjaman Dibatalkan');
    } else {
        // best-effort insert
        if ($mysqli->query("SHOW TABLES LIKE 'notifikasi'")) {
            $uid = (int)($row['pemohon_id'] ?? $row['peminjam_id'] ?? $row['created_by'] ?? 0);
            $msg = $mysqli->real_escape_string($message);
            if ($mysqli->query("SHOW COLUMNS FROM notifikasi")) {
                $cols = array_column($mysqli->query("SHOW COLUMNS FROM notifikasi")->fetch_all(MYSQLI_ASSOC), 'Field');
                if (in_array('message', $cols)) {
                    $mysqli->query("INSERT INTO notifikasi (user_id, message, created_at) VALUES ({$uid}, '{$msg}', NOW())");
                }
            }
        }
    }

    echo json_encode(['success' => true, 'message' => 'Peminjaman dibatalkan']);

} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}

?>
