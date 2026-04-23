<?php
// ajax/traccar_positions.php
require_once __DIR__ . '/../config/db.php';
header('Content-Type: application/json');

function table_exists_mysqli(mysqli $db, $name) {
    $safe = $db->real_escape_string($name);
    $res = $db->query("SHOW TABLES LIKE '{$safe}'");
    return $res && $res->num_rows > 0;
}

function table_columns_mysqli(mysqli $db, $name) {
    $cols = [];
    $safe = $db->real_escape_string($name);
    $res = $db->query("SHOW COLUMNS FROM `{$safe}`");
    if ($res) {
        while ($r = $res->fetch_assoc()) {
            $cols[] = $r['Field'];
        }
    }
    return $cols;
}

$hasKendaraan = table_exists_mysqli($mysqli, 'kendaraan');
$kendaraanCols = $hasKendaraan ? table_columns_mysqli($mysqli, 'kendaraan') : [];
$hasLocator = in_array('locator', $kendaraanCols, true);
$hasPenggunaId = in_array('pengguna_id', $kendaraanCols, true);
$hasPengguna = table_exists_mysqli($mysqli, 'pengguna');

$select = "p.*, NULL AS vehicle_id, NULL AS no_reg, NULL AS no_polisi, NULL AS merk, NULL AS tipe, NULL AS locator, NULL AS pengguna_id, NULL AS user_name, NULL AS user_pangkat, NULL AS penanggung_jawab";
$from = " FROM traccar_positions_last p";

if ($hasKendaraan && $hasLocator) {
        // Locator matching supports uniqueId, numeric deviceId, and deviceName from Traccar.
        $select = "p.*, k.id AS vehicle_id, k.no_reg, k.no_polisi, k.merk, k.tipe, k.locator, k.penanggung_jawab, " .
                            ($hasPenggunaId ? "k.pengguna_id" : "NULL") . " AS pengguna_id, " .
                            (($hasPengguna && $hasPenggunaId) ? "u.nama_lengkap" : "NULL") . " AS user_name, " .
                            (($hasPengguna && $hasPenggunaId) ? "u.pangkat" : "NULL") . " AS user_pangkat";

    $from .= " LEFT JOIN kendaraan k
                             ON (
                                        CONVERT(TRIM(k.locator) USING utf8mb4) COLLATE utf8mb4_unicode_ci = CONVERT(TRIM(p.device_uid) USING utf8mb4) COLLATE utf8mb4_unicode_ci
                                        OR TRIM(k.locator) = CAST(p.device_id AS CHAR)
                                        OR CONVERT(TRIM(k.locator) USING utf8mb4) COLLATE utf8mb4_unicode_ci = CONVERT(TRIM(p.device_name) USING utf8mb4) COLLATE utf8mb4_unicode_ci
                                    )";

    if ($hasPengguna && $hasPenggunaId) {
        $from .= " LEFT JOIN pengguna u ON u.id = k.pengguna_id";
    }
}

$sql = "SELECT {$select}{$from} ORDER BY p.updated_at DESC";

$res = $mysqli->query($sql);
if (!$res) {
    http_response_code(500);
    echo json_encode(['error' => 'db_error', 'msg' => $mysqli->error]);
    exit;
}

$rows = [];
while ($r = $res->fetch_assoc()) {
    if (empty($r['user_name']) && !empty($r['penanggung_jawab'])) {
        $r['user_name'] = $r['penanggung_jawab'];
    }
    $labelParts = [];
    if (!empty($r['no_polisi'])) {
        $labelParts[] = $r['no_polisi'];
    } elseif (!empty($r['no_reg'])) {
        $labelParts[] = $r['no_reg'];
    } elseif (!empty($r['merk'])) {
        $labelParts[] = $r['merk'];
    } elseif (!empty($r['device_name'])) {
        $labelParts[] = $r['device_name'];
    }
    if (!empty($r['user_name'])) {
        $labelParts[] = $r['user_name'];
    }
    $r['display_label'] = !empty($labelParts) ? implode(' - ', $labelParts) : ($r['device_name'] ?? '');
    $r['user_label'] = trim(($r['user_pangkat'] ?? '') . (($r['user_pangkat'] ?? '') !== '' && !empty($r['user_name']) ? ' - ' : '') . ($r['user_name'] ?? ''));
    $rows[] = $r;
}

echo json_encode($rows);
