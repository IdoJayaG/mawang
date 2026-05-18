<?php
/**
 * Update email driver menjadi @gmail.com dan isi no_hp random.
 */

require_once __DIR__ . '/../config/db.php';
mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);

$driverUsernames = [
    'edy.s','ikhwan','marsudi','tarman','joko.s','mer.agus.tinus.calvin','purwanto','puput.y','sigit.permana','aminudin','rusdi','suhendra','turimin','tukiman','erik','sophan','arie.setiawan','isak','kodim','nemin','m.budi.s','suradi','jejen','nurfadli','lingga','mulyanto','asep.r','parman','didi.sariman','ujang.sugiana','budi.utomo','yudi.martono','mulyadi','totok'
];

function random_phone_number($seed)
{
    // 08 + 10 digit => total 12 digit (contoh: 081234567890)
    mt_srand($seed + 2026);
    $number = '08';
    for ($i = 0; $i < 10; $i++) {
        $number .= (string)mt_rand(0, 9);
    }
    return $number;
}

try {
    $conn->begin_transaction();

    $findStmt = $conn->prepare(
        "SELECT p.id, ua.username
         FROM pengguna p
         JOIN user_account ua ON ua.pengguna_id = p.id
         WHERE ua.username = ?
         LIMIT 1"
    );

    $updateStmt = $conn->prepare(
        "UPDATE pengguna
         SET email = ?, no_hp = ?, updated_at = NOW()
         WHERE id = ?"
    );

    $updated = 0;

    foreach ($driverUsernames as $index => $username) {
        $findStmt->bind_param('s', $username);
        $findStmt->execute();
        $row = $findStmt->get_result()->fetch_assoc();

        if (!$row) {
            echo "⊘ Username tidak ditemukan: {$username}\n";
            continue;
        }

        $email = $username . '@gmail.com';
        $noHp = random_phone_number($index + 1);
        $id = (int)$row['id'];

        $updateStmt->bind_param('ssi', $email, $noHp, $id);
        $updateStmt->execute();

        echo "✓ {$username} -> {$email} | {$noHp}\n";
        $updated++;
    }

    $findStmt->close();
    $updateStmt->close();

    $conn->commit();

    echo "\n=== HASIL UPDATE EMAIL & NO HP ===\n";
    echo "Total diperbarui: {$updated}\n";

} catch (Exception $e) {
    $conn->rollback();
    echo 'ERROR: ' . $e->getMessage() . "\n";
    exit(1);
} finally {
    $conn->close();
}
