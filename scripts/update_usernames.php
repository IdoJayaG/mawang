<?php
/**
 * Script untuk mengubah username driver
 * Menghilangkan prefix Ajp, nomor, dan pangkat/status
 */

require_once __DIR__ . '/../config/db.php';
mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);

try {
    $conn->begin_transaction();

    // Pangkat dan status yang perlu dihilangkan
    $ranks = ['Serka', 'Sertu', 'Serma', 'Kopka', 'Peltu', 'Pelda', 'PPPK', 'Honorer', 'PNS', 'Truk', 'Denwalsus'];
    
    // Ambil semua pengguna driver dengan usernames yang perlu diupdate
    $query = "SELECT ua.id, p.nama_lengkap, ua.username
              FROM user_account ua 
              JOIN pengguna p ON ua.pengguna_id = p.id
              WHERE ua.username LIKE 'ajp.%' OR ua.username LIKE 'truk.%'
              ORDER BY p.id";
    
    $result = $conn->query($query);
    $updates = 0;
    
    echo "=== UPDATING USERNAMES ===\n\n";
    
    while ($row = $result->fetch_assoc()) {
        $old_username = $row['username'];
        $nama_lengkap = $row['nama_lengkap'];
        
        // Bersihkan nama: Ajp XX [RANK] [Name] → [Name]
        $cleaned = $nama_lengkap;
        
        // Hilangkan "Ajp XX " bagian awal
        $cleaned = preg_replace('/^Ajp\s+\d+\s+/', '', $cleaned);
        
        // Hilangkan "(Denwalsus) " bagian awal
        $cleaned = preg_replace('/^\(Denwalsus\)\s+/', '', $cleaned);
        
        // Hilangkan "Truk Denwalsus " bagian awal
        $cleaned = preg_replace('/^Truk\s+Denwalsus\s+/', '', $cleaned);
        
        // Hilangkan pangkat/status di awal nama
        foreach ($ranks as $rank) {
            $cleaned = preg_replace('/^' . preg_quote($rank) . '\s+/i', '', $cleaned);
        }
        
        // Format username: spasi menjadi titik, lowercase
        $new_username = strtolower(str_replace(' ', '.', trim($cleaned)));
        
        // Update username di database
        $update_query = "UPDATE user_account SET username = ? WHERE id = ?";
        $stmt = $conn->prepare($update_query);
        $stmt->bind_param("si", $new_username, $row['id']);
        $stmt->execute();
        $stmt->close();
        
        echo "✓ {$old_username} → {$new_username} (dari: {$nama_lengkap})\n";
        $updates++;
    }
    
    $conn->commit();
    
    echo "\n=== HASIL UPDATE ===\n";
    echo "Total username diperbarui: {$updates}\n";
    echo "Username update berhasil!\n";

} catch (Exception $e) {
    $conn->rollback();
    echo "ERROR: " . $e->getMessage() . "\n";
    exit(1);
} finally {
    $conn->close();
}
?>
