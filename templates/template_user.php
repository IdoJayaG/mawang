<?php
// Dynamic Excel template generator for user import
// Headers: nrp,nama_lengkap,jabatan,pangkat,no_hp,email,jenis_personel,matra,korps,kesatuan,username,password,alamat,role

// Try to load PhpSpreadsheet
$autoload = dirname(__DIR__) . '/vendor/autoload.php';
if (file_exists($autoload)) { require_once $autoload; }

if (!class_exists('PhpOffice\\PhpSpreadsheet\\Spreadsheet')) {
    // Fallback: output CSV if library missing
    header('Content-Type: text/csv');
    header('Content-Disposition: attachment; filename="template_user.csv"');
    $out = fopen('php://output', 'w');
    fputcsv($out, ['nrp','nama_lengkap','jabatan','pangkat','no_hp','email','jenis_personel','matra','korps','kesatuan','username','password','alamat','role']);
    // sample row (optional)
    fputcsv($out, ['123456','JOHN DOE','PENGEMUDI','KAPTEN','08123456789','john.doe@example.com','TNI','AD','INFANTERI','KODAM I','jdoe','Rahasia123','Jl. Merdeka No. 1','User']);
    fclose($out); exit;
}

use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Fill;

$spreadsheet = new Spreadsheet();
$sheet = $spreadsheet->getActiveSheet();
$headers = ['nrp','nama_lengkap','jabatan','pangkat','no_hp','email','jenis_personel','matra','korps','kesatuan','username','password','alamat','role'];
$col = 'A';
foreach ($headers as $h) { $sheet->setCellValue($col.'1', $h); $col++; }

// Style header
$sheet->getStyle('A1:N1')->getFont()->setBold(true);
$sheet->getStyle('A1:N1')->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
$sheet->getStyle('A1:N1')->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setARGB('FFE8EEF7');

// Sample row
$sheet->fromArray([
    ['123456','JOHN DOE','PENGEMUDI','KAPTEN','08123456789','john.doe@example.com','TNI','AD','INFANTERI','KODAM I','jdoe','Rahasia123','Jl. Merdeka No. 1','User']
], null, 'A2');

// Autosize columns
foreach (range('A','N') as $c) { $sheet->getColumnDimension($c)->setAutoSize(true); }

header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
// Ensure clean output before sending XLSX
if (function_exists('ini_get') && function_exists('ini_set') && ini_get('zlib.output_compression')) {
    @ini_set('zlib.output_compression', 'Off');
}
if (function_exists('ob_get_level')) {
    while (ob_get_level() > 0) { @ob_end_clean(); }
}
header('Content-Disposition: attachment; filename=template_user.xlsx');
header('Cache-Control: max-age=0');
header('Pragma: public');
header('Expires: 0');
$writer = new Xlsx($spreadsheet);
$writer->save('php://output');
exit;
