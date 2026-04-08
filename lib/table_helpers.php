<?php
// Central helper for checking optional columns across pages/scripts
if (!function_exists('table_has_columns')) {
    /**
     * Check that all columns in $cols exist in $table for the current database.
     * Accepts either a mysqli instance named $mysqli or $conn.
     *
     * @param mysqli $db
     * @param string $table
     * @param array $cols
     * @return bool
     */
    function table_has_columns($db, $table, array $cols) {
        // assume $db is a mysqli instance
        $mysqli = $db;
        if (!($mysqli instanceof mysqli)) {
            // best-effort: try to use global $mysqli or $conn if available
            if (isset($GLOBALS['mysqli']) && $GLOBALS['mysqli'] instanceof mysqli) $mysqli = $GLOBALS['mysqli'];
            elseif (isset($GLOBALS['conn']) && $GLOBALS['conn'] instanceof mysqli) $mysqli = $GLOBALS['conn'];
            else return false;
        }

        $table = $mysqli->real_escape_string($table);
        foreach ($cols as $col) {
            $colEsc = $mysqli->real_escape_string($col);
            $res = $mysqli->query("SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = '{$table}' AND COLUMN_NAME = '{$colEsc}' LIMIT 1");
            if (!$res || $res->num_rows === 0) return false;
        }
        return true;
    }
}

/**
 * Validate that a kendaraan ID exists in the database
 * @param mysqli $mysqli Database connection
 * @param int $kendaraan_id Kendaraan ID to validate
 * @return bool True if kendaraan exists, false otherwise
 */
if (!function_exists('validate_kendaraan_exists')) {
    function validate_kendaraan_exists($mysqli, $kendaraan_id) {
        if (!$mysqli instanceof mysqli || !is_numeric($kendaraan_id) || $kendaraan_id <= 0) {
            return false;
        }
        
        $stmt = $mysqli->prepare("SELECT 1 FROM kendaraan WHERE id = ? LIMIT 1");
        if (!$stmt) return false;
        
        $stmt->bind_param('i', $kendaraan_id);
        $stmt->execute();
        $result = $stmt->get_result();
        $exists = $result->num_rows > 0;
        $stmt->close();
        
        return $exists;
    }
}

/**
 * Validate that a pengguna ID exists in the database
 * @param mysqli $mysqli Database connection
 * @param int $pengguna_id Pengguna ID to validate
 * @return bool True if pengguna exists, false otherwise
 */
if (!function_exists('validate_pengguna_exists')) {
    function validate_pengguna_exists($mysqli, $pengguna_id) {
        if (!$mysqli instanceof mysqli || !is_numeric($pengguna_id) || $pengguna_id <= 0) {
            return false;
        }
        
        $stmt = $mysqli->prepare("SELECT 1 FROM pengguna WHERE id = ? LIMIT 1");
        if (!$stmt) return false;
        
        $stmt->bind_param('i', $pengguna_id);
        $stmt->execute();
        $result = $stmt->get_result();
        $exists = $result->num_rows > 0;
        $stmt->close();
        
        return $exists;
    }
}
