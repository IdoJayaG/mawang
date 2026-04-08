<?php
// Dynamic Excel template generator for kendaraan import
// Headers: no_rangka,no_mesin,no_reg,merk,tipe,tahun_pembuatan,warna,jenis,bahan_bakar,satker,penanggung_jawab,kondisi,status_kendaraan

$autoload = dirname(__DIR__) . '/vendor/autoload.php';
if (file_exists($autoload)) { require_once $autoload; }

if (!class_exists('PhpOffice\\PhpSpreadsheet\\Spreadsheet')) {
    // Fallback to CSV if library missing
    header('Content-Type: text/csv');
    header('Content-Disposition: attachment; filename="template_kendaraan.csv"');
    $out = fopen('php://output', 'w');
    fputcsv($out, ['no_rangka','no_mesin','no_reg','merk','tipe','tahun_pembuatan','warna','jenis','bahan_bakar','satker','penanggung_jawab','kondisi','status_kendaraan']);
    fputcsv($out, ['MH8XXX1234567890','1NZ-1234567','REG-001','TOYOTA','AVANZA','2022','HITAM','Roda 4','Pertalite','PUSINFOLAHTA','Kapusinfolahta TNI','Baik','Operasional']);
    fclose($out); exit;
}

use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Fill;

$spreadsheet = new Spreadsheet();
$sheet = $spreadsheet->getActiveSheet();
$headers = ['no_rangka','no_mesin','no_reg','merk','tipe','tahun_pembuatan','warna','jenis','bahan_bakar','satker','penanggung_jawab','kondisi','status_kendaraan'];
$col = 'A';
foreach ($headers as $h) { $sheet->setCellValue($col.'1', $h); $col++; }

$sheet->getStyle('A1:M1')->getFont()->setBold(true);
$sheet->getStyle('A1:M1')->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
$sheet->getStyle('A1:M1')->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setARGB('FFE8EEF7');

$sheet->fromArray([
    ['MH8XXX1234567890','1NZ-1234567','REG-001','TOYOTA','AVANZA','2022','HITAM','Roda 4','Pertalite','PUSINFOLAHTA','Kapusinfolahta TNI','Baik','Operasional']
], null, 'A2');

foreach (range('A','M') as $c) { $sheet->getColumnDimension($c)->setAutoSize(true); }

// Ensure clean output to prevent invalid extension warnings
if (function_exists('ini_get') && function_exists('ini_set') && ini_get('zlib.output_compression')) {
    @ini_set('zlib.output_compression', 'Off');
}
if (function_exists('ob_get_level')) {
    while (ob_get_level() > 0) { @ob_end_clean(); }
}
header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
header('Content-Disposition: attachment; filename=template_kendaraan.xlsx');
header('Cache-Control: max-age=0');
header('Pragma: public');
header('Expires: 0');
$writer = new Xlsx($spreadsheet);
$writer->save('php://output');
exit;
