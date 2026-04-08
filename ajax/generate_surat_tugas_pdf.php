<?php
require_once '../config.php';
require_once '../config/db.php';
require_once __DIR__ . '/../lib/SuratTugasPDF.php';

// Defensive wrapper: buffer output and convert PHP warnings/notices to exceptions
ob_start();
set_error_handler(function($severity, $message, $file, $line) {
    throw new ErrorException($message, 0, $severity, $file, $line);
});
register_shutdown_function(function() {
    $err = error_get_last();
    if ($err && in_array($err['type'], [E_ERROR, E_PARSE, E_CORE_ERROR, E_COMPILE_ERROR])) {
        if (ob_get_length()) ob_end_clean();
        http_response_code(500);
        echo '<h1>Terjadi kesalahan server</h1><p>' . htmlspecialchars($err['message'] ?? 'Unknown fatal error') . '</p>';
        exit();
    }
});

try {
    // Only logged-in users
    if (!is_logged_in()) {
        if (ob_get_length()) ob_end_clean();
        header('HTTP/1.1 403 Forbidden');
        echo 'Unauthorized';
        exit();
    }

    $id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
if (!$id) {
    header('HTTP/1.1 400 Bad Request');
    echo 'ID tidak valid';
    exit();
}

// Load surat_tugas
$stmt = $mysqli->prepare("SELECT s.*, k.no_polisi, k.merk, k.tipe, p.nama_lengkap, p.pangkat
    FROM surat_tugas s
    LEFT JOIN kendaraan k ON s.kendaraan_id = k.id
    LEFT JOIN pengguna p ON s.pengguna_id = p.id
    WHERE s.id = ?");
$stmt->bind_param('i', $id);
$stmt->execute();
$res = $stmt->get_result();
if ($res->num_rows === 0) {
    header('HTTP/1.1 404 Not Found');
    echo 'Surat tugas tidak ditemukan';
    exit();
}

$surat = $res->fetch_assoc();
$stmt->close();

// Authorization: allow recipient, creator, admin/operator
$current = get_logged_in_user();
$role = get_current_role();
$owner_id = (int)($surat['penerima_id'] ?? $surat['pengguna_id'] ?? 0);
if ($current['id'] !== $owner_id && !in_array($role, ['admin','operator'])) {
    header('HTTP/1.1 403 Forbidden');
    echo 'Unauthorized';
    exit();
}

// Prepare surat data for PDF generator with expected keys
$pdf_data = [];
$pdf_data['nomor_surat'] = $surat['nomor_surat'] ?? '';
$pdf_data['tanggal_surat'] = $surat['tanggal_surat'] ?? date('Y-m-d');
$pdf_data['klasifikasi'] = $surat['klasifikasi'] ?? '';
$pdf_data['lampiran'] = $surat['lampiran'] ?? '';
$pdf_data['perihal'] = $surat['perihal'] ?? $surat['judul_tugas'] ?? '';
$pdf_data['kepada_jabatan'] = $surat['kepada_jabatan'] ?? '';
$pdf_data['kepada_tempat'] = $surat['kepada_tempat'] ?? '';
$pdf_data['dasar_a'] = $surat['dasar_a'] ?? '';
$pdf_data['dasar_b'] = $surat['dasar_b'] ?? '';
$pdf_data['kendaraan_id'] = $surat['kendaraan_id'] ?? '';
$pdf_data['merk'] = $surat['merk'] ?? '';
$pdf_data['tipe'] = $surat['tipe'] ?? '';
$pdf_data['keperluan'] = $surat['keperluan'] ?? '';
$pdf_data['tujuan'] = $surat['tujuan'] ?? '';
$pdf_data['tanggal_berangkat'] = $surat['tanggal_berangkat'] ?? $surat['tanggal_mulai'] ?? '';
$pdf_data['berangkat_dari'] = $surat['berangkat_dari'] ?? '';
$pdf_data['waktu_berangkat'] = $surat['waktu_berangkat'] ?? '';
$pdf_data['pejabat_ttd_jabatan'] = $surat['pejabat_ttd_jabatan'] ?? '';
$pdf_data['pejabat_ttd_sebagai'] = $surat['pejabat_ttd_sebagai'] ?? '';
$pdf_data['pejabat_ttd'] = $surat['pejabat_ttd'] ?? '';
$pdf_data['tembusan_1'] = $surat['tembusan_1'] ?? '';
$pdf_data['tembusan_2'] = $surat['tembusan_2'] ?? '';
$pdf_data['tembusan_3'] = $surat['tembusan_3'] ?? '';
$pdf_data['tembusan_4'] = $surat['tembusan_4'] ?? '';

$pdf = new SuratTugasPDF(PDF_PAGE_ORIENTATION, PDF_UNIT, PDF_PAGE_FORMAT, true, 'UTF-8', false);
$pdf->SetCreator(PDF_CREATOR);
$pdf->SetAuthor('Randis System');
$pdf->SetTitle('Surat Tugas - ' . ($pdf_data['nomor_surat'] ?? ''));
$pdf->SetMargins(PDF_MARGIN_LEFT, PDF_MARGIN_TOP + 10, PDF_MARGIN_RIGHT);
$pdf->SetAutoPageBreak(TRUE, PDF_MARGIN_BOTTOM);
$pdf->generateSuratTugas($pdf_data);

    // Output PDF to browser
    if (ob_get_length()) ob_end_clean();
    $filename = 'surat_tugas_' . ($pdf_data['nomor_surat'] ? preg_replace('/[^A-Za-z0-9-_\.]/','_', $pdf_data['nomor_surat']) : $id) . '.pdf';
    $pdf->Output($filename, 'I');
    exit();

} catch (Throwable $e) {
    if (ob_get_length()) ob_end_clean();
    http_response_code(500);
    echo '<h1>Terjadi kesalahan</h1><p>' . htmlspecialchars($e->getMessage()) . '</p>';
    exit();
}

?>
