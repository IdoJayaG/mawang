<?php
require_once dirname(__DIR__) . '/includes/auth.php';

if (!can_operate()) {
    header('HTTP/1.1 403 Forbidden');
    echo json_encode(['success' => false, 'message' => 'Tidak memiliki akses']);
    exit;
}

$user_id = isset($_GET['user_id']) ? (int)$_GET['user_id'] : null;
$start_date = $_GET['start_date'] ?? null;
$end_date = $_GET['end_date'] ?? null;
$format = strtolower($_GET['format'] ?? 'csv'); // csv | xlsx

try {
    // Build filter
    $where = [];$params=[];$types='';
    if ($user_id){$where[]='la.user_id = ?';$params[]=$user_id;$types.='i';}
    if ($start_date){$where[]='DATE(la.created_at) >= ?';$params[]=$start_date;$types.='s';}
    if ($end_date){$where[]='DATE(la.created_at) <= ?';$params[]=$end_date;$types.='s';}
    $where_clause = $where?('WHERE '.implode(' AND ',$where)) : '';

    $sql = "SELECT la.id,u.nama_lengkap nama_user,ua.role_id,r.nama_role role,la.activity_type aksi,la.description deskripsi,la.ip_address,la.user_agent,la.created_at
            FROM log_aktivitas la
            LEFT JOIN pengguna u ON la.user_id=u.id
            LEFT JOIN user_account ua ON u.id=ua.pengguna_id
            LEFT JOIN role r ON ua.role_id=r.id $where_clause ORDER BY la.created_at DESC";
    $stmt=$mysqli->prepare($sql);
    if($params){$stmt->bind_param($types,...$params);} $stmt->execute();
    $res=$stmt->get_result();$rows=[];while($r=$res->fetch_assoc()){$rows[]=$r;} $stmt->close();
    $rowCount=count($rows);

    // Base filename
    $filename='log_aktivitas_'.date('Y-m-d_H-i-s');
    if($user_id){$us=$mysqli->prepare('SELECT nama_lengkap nama FROM pengguna WHERE id=?');$us->bind_param('i',$user_id);$us->execute();$ur=$us->get_result();if($ud=$ur->fetch_assoc()){$filename.='_' . preg_replace('/[^a-zA-Z0-9]/','_',$ud['nama']);}$us->close();}

    if($format==='xlsx'){
        $autoload=dirname(__DIR__).'/vendor/autoload.php';
        if(file_exists($autoload)) require_once $autoload; else $format='xls-fallback';
        if($format==='xlsx' && !class_exists('PhpOffice\\PhpSpreadsheet\\Spreadsheet')) $format='xls-fallback';
    }

    if($format==='xlsx'){
        // Generate real XLSX
        if(class_exists('PhpOffice\\PhpSpreadsheet\\Cell\\Cell') && class_exists('PhpOffice\\PhpSpreadsheet\\Cell\\StringValueBinder')){
            \PhpOffice\PhpSpreadsheet\Cell\Cell::setValueBinder(new \PhpOffice\PhpSpreadsheet\Cell\StringValueBinder());
        }
        $spreadsheet=new \PhpOffice\PhpSpreadsheet\Spreadsheet();
        $sheet=$spreadsheet->getActiveSheet();
    $sheet->setTitle('Log Aktivitas');
    // Kolom untuk Excel tanpa ID, IP Address dan User Agent sesuai permintaan
    $headers=['Nama User','Role','Aksi','Deskripsi','Tanggal & Waktu'];
        // Helper to convert numeric column index to letter and write value (compatibility if setCellValueByColumnAndRow not available)
        $writeCell=function($colIndex,$rowIndex,$value) use ($sheet){
            $colLetter=\PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex($colIndex);
            $sheet->setCellValue($colLetter.$rowIndex,$value);
        };
        $c=1;foreach($headers as $h){$writeCell($c++,1,$h);} $rIdx=2;
        foreach($rows as $rw){
            $writeCell(1,$rIdx,$rw['nama_user']??'User Tidak Ditemukan');
            $writeCell(2,$rIdx,strtoupper($rw['role']??'UNKNOWN'));
            $writeCell(3,$rIdx,$rw['aksi']);
            $writeCell(4,$rIdx,$rw['deskripsi']);
            $writeCell(5,$rIdx,date('d/m/Y H:i:s',strtotime($rw['created_at'])));
            $rIdx++; }
        $sumStart=$rIdx+1; $sheet->setCellValue('A'.$sumStart,'=== RINGKASAN EXPORT ===');
        $sheet->setCellValue('A'.($sumStart+1),'Total Record:');$sheet->setCellValue('B'.($sumStart+1),$rowCount);
        $sheet->setCellValue('A'.($sumStart+2),'Tanggal Export:');$sheet->setCellValue('B'.($sumStart+2),date('d/m/Y H:i:s')); $ln=$sumStart+3;
        if($user_id){$sheet->setCellValue('A'.$ln,'Filter User ID:');$sheet->setCellValue('B'.$ln,$user_id);$ln++;}
        if($start_date){$sheet->setCellValue('A'.$ln,'Tanggal Mulai:');$sheet->setCellValue('B'.$ln,date('d/m/Y',strtotime($start_date)));$ln++;}
        if($end_date){$sheet->setCellValue('A'.$ln,'Tanggal Akhir:');$sheet->setCellValue('B'.$ln,date('d/m/Y',strtotime($end_date)));$ln++;}
        $sheet->setCellValue('A'.$ln,'Exported by:');$sheet->setCellValue('B'.$ln,$_SESSION['nama']??'System');
        foreach(range('A','E') as $col){$sheet->getColumnDimension($col)->setAutoSize(true);} $sheet->getStyle('A1:E1')->getFont()->setBold(true);
        if(class_exists('PhpOffice\\PhpSpreadsheet\\Style\\Fill')){
            $sheet->getStyle('A1:E1')->getFill()->setFillType(\PhpOffice\PhpSpreadsheet\Style\Fill::FILL_SOLID)->getStartColor()->setARGB('FFE0E0E0');
        }
        $filename.='.xlsx';
        header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
        header('Content-Disposition: attachment; filename="'.$filename.'"');
        header('Cache-Control: max-age=0');
        $writer=new \PhpOffice\PhpSpreadsheet\Writer\Xlsx($spreadsheet);$writer->save('php://output');
    } elseif($format==='xls-fallback') {
        // Simple HTML table fallback (saved as .html to avoid Excel format mismatch warning)
        $filename.='_fallback.html';
        header('Content-Type: text/html; charset=utf-8');
        header('Content-Disposition: attachment; filename="'.$filename.'"');
        echo "<table border='1'>";
        echo "<tr><th colspan='8' style='background:#eee;font-weight:bold;'>LOG AKTIVITAS (FALLBACK)</th></tr>";
        echo '<tr><th>ID</th><th>Nama User</th><th>Role</th><th>Aksi</th><th>Deskripsi</th><th>IP</th><th>User Agent</th><th>Tanggal & Waktu</th></tr>';
        $safe = function($v){ return htmlspecialchars((string)($v ?? '')); };
        foreach($rows as $rw){
            echo '<tr>'
                . '<td>'.$safe($rw['id']).'</td>'
                . '<td>'.$safe($rw['nama_user']??'User Tidak Ditemukan').'</td>'
                . '<td>'.$safe(strtoupper($rw['role']??'UNKNOWN')).'</td>'
                . '<td>'.$safe($rw['aksi']).'</td>'
                . '<td>'.$safe($rw['deskripsi']).'</td>'
                . '<td>'.$safe($rw['ip_address']).'</td>'
                . '<td>'.$safe($rw['user_agent']).'</td>'
                . '<td>'.$safe(date('d/m/Y H:i:s',strtotime($rw['created_at']))).'</td>'
            . '</tr>';
        }
        echo '<tr><td colspan="8"></td></tr>';
        echo '<tr><td colspan="8"><strong>=== RINGKASAN EXPORT ===</strong></td></tr>';
        echo '<tr><td>Total Record</td><td colspan="7">'.$rowCount.'</td></tr>';
        echo '<tr><td>Tanggal Export</td><td colspan="7">'.date('d/m/Y H:i:s').'</td></tr>';
        if($user_id) echo '<tr><td>Filter User ID</td><td colspan="7">'.$user_id.'</td></tr>';
        if($start_date) echo '<tr><td>Tanggal Mulai</td><td colspan="7">'.date('d/m/Y',strtotime($start_date)).'</td></tr>';
        if($end_date) echo '<tr><td>Tanggal Akhir</td><td colspan="7">'.date('d/m/Y',strtotime($end_date)).'</td></tr>';
        echo '<tr><td>Exported by</td><td colspan="7">'.htmlspecialchars($_SESSION['nama']??'System').'</td></tr>';
        echo '</table>';
    } else { // CSV
        $filename.='.csv';
        header('Content-Type: text/csv; charset=utf-8');
        header('Content-Disposition: attachment; filename="'.$filename.'"');
        header('Cache-Control: no-cache, no-store, must-revalidate');
        $out=fopen('php://output','w');
        fprintf($out, chr(0xEF).chr(0xBB).chr(0xBF));
        fputcsv($out,['ID','Nama User','Role','Aksi','Deskripsi','IP Address','User Agent','Tanggal & Waktu']);
        foreach($rows as $rw){
            fputcsv($out,[
                $rw['id'],
                $rw['nama_user']??'User Tidak Ditemukan',
                strtoupper($rw['role']??'UNKNOWN'),
                $rw['aksi'],
                $rw['deskripsi'],
                $rw['ip_address'],
                $rw['user_agent'],
                date('d/m/Y H:i:s',strtotime($rw['created_at']))
            ]);
        }
        fputcsv($out,[]);fputcsv($out,['=== RINGKASAN EXPORT ===']);
        fputcsv($out,['Total Record:',$rowCount]);
        fputcsv($out,['Tanggal Export:',date('d/m/Y H:i:s')]);
        if($user_id) fputcsv($out,['Filter User ID:',$user_id]);
        if($start_date) fputcsv($out,['Tanggal Mulai:',date('d/m/Y',strtotime($start_date))]);
        if($end_date) fputcsv($out,['Tanggal Akhir:',date('d/m/Y',strtotime($end_date))]);
        fputcsv($out,['Exported by:',$_SESSION['nama']??'System']);
        fclose($out);
    }

    // Log activity (not for fallback already logged?)
    $desc='Export log aktivitas format: '.strtoupper($format==='xls-fallback'?'XLS-FALLBACK':$format);
    if($user_id) $desc.=' untuk user ID: '.$user_id;
    if($start_date||$end_date) $desc.=' periode: '.($start_date?:'semua').' s/d '.($end_date?:'sekarang');
    $desc.=' (Total: '.$rowCount.' record)';
    $lg=$mysqli->prepare('INSERT INTO log_aktivitas (user_id, activity_type, description, ip_address, user_agent) VALUES (?,?,?,?,?)');
    $lg->bind_param('issss', $_SESSION['user_id'], 'EXPORT', $desc, $_SERVER['REMOTE_ADDR'], $_SERVER['HTTP_USER_AGENT']);
    $lg->execute();$lg->close();
    exit;
} catch(Exception $e){
    if(!headers_sent()) header('Content-Type: application/json');
    echo json_encode(['success'=>false,'message'=>'Error: '.$e->getMessage()]);
}
?>
