<?php
require_once dirname(__DIR__) . '/includes/auth.php';

header('Content-Type: application/json');

if (!is_admin()) {
    echo json_encode(['success' => false, 'message' => 'Hanya admin yang dapat menghapus log']);
    exit;
}

$action = $_POST['action'] ?? $_GET['action'] ?? '';
$days = (int)($_POST['days'] ?? $_GET['days'] ?? 90);

// Minimum 30 days for safety
if ($days < 30) {
    $days = 30;
}

try {
    if ($action === 'preview') {
        // Preview mode - count records that would be deleted
        $cutoff_date = date('Y-m-d H:i:s', strtotime("-{$days} days"));
        
        $stmt = $mysqli->prepare("
            SELECT 
                COUNT(*) as total_records,
                MIN(created_at) as oldest_record,
                MAX(created_at) as newest_record,
                COUNT(DISTINCT user_id) as affected_users
            FROM log_aktivitas 
            WHERE created_at < ?
        ");
        $stmt->bind_param('s', $cutoff_date);
        $stmt->execute();
        $preview = $stmt->get_result()->fetch_assoc();
        $stmt->close();
        
        // Get breakdown by action type
        $stmt = $mysqli->prepare("
            SELECT aksi, COUNT(*) as count 
            FROM log_aktivitas 
            WHERE created_at < ? 
            GROUP BY aksi 
            ORDER BY count DESC
        ");
        $stmt->bind_param('s', $cutoff_date);
        $stmt->execute();
        $action_breakdown = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
        $stmt->close();
        
        echo json_encode([
            'success' => true,
            'preview' => true,
            'cutoff_date' => $cutoff_date,
            'days' => $days,
            'total_records' => $preview['total_records'],
            'oldest_record' => $preview['oldest_record'],
            'newest_record' => $preview['newest_record'],
            'affected_users' => $preview['affected_users'],
            'action_breakdown' => $action_breakdown
        ]);
        
    } elseif ($action === 'execute') {
        // Execute deletion
        $cutoff_date = date('Y-m-d H:i:s', strtotime("-{$days} days"));
        
        // First, get count for logging
        $count_stmt = $mysqli->prepare("SELECT COUNT(*) as count FROM log_aktivitas WHERE created_at < ?");
        $count_stmt->bind_param('s', $cutoff_date);
        $count_stmt->execute();
        $count_result = $count_stmt->get_result()->fetch_assoc();
        $records_to_delete = $count_result['count'];
        $count_stmt->close();
        
        if ($records_to_delete > 0) {
            // Execute deletion
            $delete_stmt = $mysqli->prepare("DELETE FROM log_aktivitas WHERE created_at < ?");
            $delete_stmt->bind_param('s', $cutoff_date);
            $delete_stmt->execute();
            $deleted_count = $delete_stmt->affected_rows;
            $delete_stmt->close();
            
            // Log the cleanup activity
            $log_deskripsi = "Pembersihan log otomatis: Menghapus {$deleted_count} record log yang lebih lama dari {$days} hari (sebelum {$cutoff_date})";
            
            $log_stmt = $mysqli->prepare("INSERT INTO log_aktivitas (user_id, activity_type, description, ip_address, user_agent) VALUES (?, ?, ?, ?, ?)");
            $log_stmt->bind_param('issss', 
                $_SESSION['user_id'],
                'CLEANUP',
                $log_deskripsi,
                $_SERVER['REMOTE_ADDR'],
                $_SERVER['HTTP_USER_AGENT']
            );
            $log_stmt->execute();
            $log_stmt->close();
            
            echo json_encode([
                'success' => true,
                'executed' => true,
                'deleted_count' => $deleted_count,
                'cutoff_date' => $cutoff_date,
                'days' => $days,
                'message' => "Berhasil menghapus {$deleted_count} record log yang lebih lama dari {$days} hari"
            ]);
        } else {
            echo json_encode([
                'success' => true,
                'executed' => true,
                'deleted_count' => 0,
                'message' => "Tidak ada record log yang perlu dihapus"
            ]);
        }
        
    } elseif ($action === 'stats') {
        // Get general statistics
        $stats_query = "
            SELECT 
                COUNT(*) as total_records,
                COUNT(DISTINCT user_id) as total_users,
                COUNT(DISTINCT aksi) as total_actions,
                MIN(created_at) as oldest_record,
                MAX(created_at) as newest_record,
                COUNT(CASE WHEN created_at >= DATE_SUB(NOW(), INTERVAL 7 DAY) THEN 1 END) as last_7_days,
                COUNT(CASE WHEN created_at >= DATE_SUB(NOW(), INTERVAL 30 DAY) THEN 1 END) as last_30_days,
                COUNT(CASE WHEN created_at >= DATE_SUB(NOW(), INTERVAL 90 DAY) THEN 1 END) as last_90_days
            FROM log_aktivitas
        ";
        
        $result = $mysqli->query($stats_query);
        $stats = $result->fetch_assoc();
        
        // Get top actions
        $top_actions_result = $mysqli->query("
            SELECT aksi, COUNT(*) as count 
            FROM log_aktivitas 
            GROUP BY aksi 
            ORDER BY count DESC 
            LIMIT 10
        ");
        $top_actions = $top_actions_result->fetch_all(MYSQLI_ASSOC);
        
        // Get top users
        $top_users_result = $mysqli->query("
                SELECT u.nama_lengkap as nama, u.role, COUNT(la.id) as activity_count
                FROM log_aktivitas la
                LEFT JOIN pengguna u ON la.user_id = u.id
                GROUP BY la.user_id, u.nama_lengkap, u.role
            ORDER BY activity_count DESC
            LIMIT 10
        ");
        $top_users = $top_users_result->fetch_all(MYSQLI_ASSOC);
        
        echo json_encode([
            'success' => true,
            'stats' => $stats,
            'top_actions' => $top_actions,
            'top_users' => $top_users
        ]);
        
    } else {
        echo json_encode(['success' => false, 'message' => 'Action tidak valid']);
    }
    
} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => 'Error: ' . $e->getMessage()]);
}
?>
