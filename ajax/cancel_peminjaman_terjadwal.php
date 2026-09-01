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

    // Only owner or admin-like may cancel; owners may cancel only when pending
    $owner_id = (int)($row['pemohon_id'] ?? $row['peminjam_id'] ?? $row['created_by'] ?? 0);
    $is_owner = ($owner_id === (int)$current_user['id']);
    $role = get_current_role();

    if ($is_owner && $row['status'] === 'pending') {
        $new_status = 'cancelled';
    } elseif (is_admin_like()) {
        $new_status = 'cancelled';
    } else {
        throw new Exception('Tidak berwenang membatalkan peminjaman ini');
    }

    // Attempt to persist reason to a reasonable column if available
    $cols = db_table_columns('peminjaman_kendaraan');

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

    // Notify the owner (insert_notification() is provided by includes/auth.php, loaded via config.php)
    insert_notification($mysqli, $owner_id, 'Peminjaman Anda telah dibatalkan', 'Peminjaman Dibatalkan');

    echo json_encode(['success' => true, 'message' => 'Peminjaman dibatalkan']);

} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}

?>
