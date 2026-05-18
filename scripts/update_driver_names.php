<?php
/**
 * Script untuk mengubah nama driver di tabel pengguna
 * Menghilangkan prefix Ajp, nomor, pangkat, dan status
 */

require_once __DIR__ . '/../config/db.php';
mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);

try {
    $conn->begin_transaction();

    // Pangkat dan status yang perlu dihilangkan
    $ranks = ['Serka', 'Sertu', 'Serma', 'Kopka', 'Peltu', 'Pelda', 'PPPK', 'Honorer', 'PNS'];
    $labels = ['Truk', 'Denwalsus'];
    
    // Ambil semua pengguna driver dengan nama yang perlu diupdate
    $query = "SELECT id, nama_lengkap
              FROM pengguna
              WHERE nama_lengkap LIKE 'Ajp%' OR nama_lengkap LIKE 'Truk%'
              ORDER BY id";
    
    $result = $conn->query($query);
    $updates = 0;
    
    echo "=== UPDATING DRIVER NAMES ===\n\n";
    
    while ($row = $result->fetch_assoc()) {
        $old_name = $row['nama_lengkap'];
        $cleaned = $old_name;
        
        // Hilangkan "Ajp XX " bagian awal
        $cleaned = preg_replace('/^Ajp\s+\d+\s+/', '', $cleaned);
        
        // Hilangkan "Truk Denwalsus " bagian awal
        $cleaned = preg_replace('/^Truk\s+Denwalsus\s+/', '', $cleaned);
        
        // Hilangkan "(Denwalsus) " 
        $cleaned = preg_replace('/\(Denwalsus\)\s+/', '', $cleaned);
        
        // Hilangkan pangkat/status di awal nama
        foreach ($ranks as $rank) {
            $cleaned = preg_replace('/^' . preg_quote($rank) . '\s+/i', '', $cleaned);
        }
        
        // Bersihkan spasi extra
        $new_name = trim($cleaned);
        
        // Update nama di database
        if ($old_name !== $new_name) {
            $update_query = "UPDATE pengguna SET nama_lengkap = ? WHERE id = ?";
            $stmt = $conn->prepare($update_query);
            $stmt->bind_param("si", $new_name, $row['id']);
            $stmt->execute();
            $stmt->close();
            
            echo "✓ {$old_name} → {$new_name}\n";
            $updates++;
        }
    }
    
    $conn->commit();
    
    echo "\n=== HASIL UPDATE ===\n";
    echo "Total nama driver diperbarui: {$updates}\n";
    echo "Nama driver berhasil diperbarui!\n";

} catch (Exception $e) {
    $conn->rollback();
    echo "ERROR: " . $e->getMessage() . "\n";
    exit(1);
} finally {
    $conn->close();
}
?>
