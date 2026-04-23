<?php
require_once __DIR__ . '/../vendor/autoload.php';

class SuratTugasPDF extends TCPDF {
    
    // Page header
    public function Header() {
        // Intentionally empty: header is rendered in document body for Nota Dinas layout.
    }
    
    // Page footer
    public function Footer() {
        // Position at 15 mm from bottom
        $this->SetY(-15);
        // Set font
        $this->SetFont('helvetica', 'I', 8);
        // Page number
        $this->Cell(0, 10, 'Halaman ' . $this->getAliasNumPage() . '/' . $this->getAliasNbPages(), 0, false, 'C', 0, '', 0, false, 'T', 'M');
    }
    
    public function generateSuratTugas($surat_data) {
        // Add a page
        $this->AddPage();
        
        // Set font for content
        $this->SetFont('helvetica', '', 11);
        
        // Format tanggal Indonesia
        $bulan_indo = [
            1 => 'Januari', 2 => 'Februari', 3 => 'Maret', 4 => 'April',
            5 => 'Mei', 6 => 'Juni', 7 => 'Juli', 8 => 'Agustus',
            9 => 'September', 10 => 'Oktober', 11 => 'November', 12 => 'Desember'
        ];
        $hari_indo = ['Sunday'=>'Minggu','Monday'=>'Senin','Tuesday'=>'Selasa','Wednesday'=>'Rabu','Thursday'=>'Kamis','Friday'=>'Jumat','Saturday'=>'Sabtu'];

        $ts_surat = strtotime($surat_data['tanggal_surat']);
        $tanggal = date('j', $ts_surat);
        $bulan = $bulan_indo[(int)date('n', $ts_surat)];
        $tahun = date('Y', $ts_surat);
        
    $nama_unit = strtoupper(trim((string)($surat_data['nama_unit'] ?? 'BIRO UMUM SETJEN KEMHAN')));
    $nama_bagian = strtoupper(trim((string)($surat_data['nama_bagian'] ?? 'BAGIAN PENGAMANAN')));
    $jenis_naskah = strtoupper(trim((string)($surat_data['jenis_naskah'] ?? 'NOTA DINAS')));
    $surat_dari = trim((string)($surat_data['surat_dari'] ?? '-'));

    // Content
    $html = '';

    $html .= '<div style="text-align:center; font-size:12pt; font-weight:bold;">' . htmlspecialchars($nama_unit) . '</div>';
    $html .= '<div style="text-align:center; font-size:12pt; font-weight:bold;">' . htmlspecialchars($nama_bagian) . '</div>';
    $html .= '<div style="text-align:center; font-size:13pt; font-weight:bold; margin-top:10px;">' . htmlspecialchars($jenis_naskah) . '</div>';
    $html .= '<table style="width:100%; font-size:11pt; margin-top:14px;">';
    $html .= '<tr><td style="width:70px;">NOMOR</td><td style="width:10px;">:</td><td>' . htmlspecialchars($surat_data['nomor_surat'] ?? '-') . '</td></tr>';
    $html .= '<tr><td>Kepada</td><td>:</td><td>Yth. ' . htmlspecialchars($surat_data['kepada_jabatan'] ?? '-') . '</td></tr>';
    $html .= '<tr><td>Dari</td><td>:</td><td>' . htmlspecialchars($surat_dari) . '</td></tr>';
    $html .= '<tr><td>Hal</td><td>:</td><td>' . htmlspecialchars($surat_data['perihal'] ?? '-') . '</td></tr>';
    $html .= '</table>';
    $html .= '<br>';

    // Begin main numbered content
    $html .= '<p style="text-align: justify; line-height: 1.4;">';
    $html .= '1. &nbsp;&nbsp;Dasar:<br>';

        // Render dasar_list JSON if available
        $dasarItems = [];
        if (!empty($surat_data['dasar_list'])) {
            $decoded = json_decode($surat_data['dasar_list'], true);
            if (is_array($decoded)) $dasarItems = $decoded;
        }
        if (empty($dasarItems)) {
            if (!empty($surat_data['dasar_a'])) $dasarItems[] = $surat_data['dasar_a'];
            if (!empty($surat_data['dasar_b'])) $dasarItems[] = $surat_data['dasar_b'];
        }
        if (!empty($dasarItems)) {
            $html .= '<ol type="a" style="margin-top:6px; margin-left:40px;">';
            foreach ($dasarItems as $d) {
                $html .= '<li style="margin-bottom:4px;">' . htmlspecialchars($d) . '</li>';
            }
            $html .= '</ol>';
        } else {
            $html .= '<ol type="a" style="margin-top:6px; margin-left:40px;"><li>&nbsp;</li><li>&nbsp;</li></ol>';
        }

        $html .= '</p>';

        // Additional fixed paragraphs
        $html .= '<p style="text-align: justify; line-height: 1.4;">';
        $html .= '2. &nbsp;&nbsp;Sehubungan dasar di atas, dengan hormat diajukan permohonan dukungan sebagai berikut:</p>';
        $html .= '<div style="margin-left:18px; line-height:1.4;">' . nl2br(htmlspecialchars((string)($surat_data['keperluan'] ?? '-'))) . '</div>';

        $html .= '<table style="width: 100%; margin-left: 18px; font-size: 11pt; margin-top:8px;">';
        $ts_berangkat = strtotime($surat_data['tanggal_berangkat'] ?? '');
        $tgl_ber = $ts_berangkat ? (date('j', $ts_berangkat) . ' ' . ($bulan_indo[(int)date('n', $ts_berangkat)] ?? '') . ' ' . date('Y', $ts_berangkat)) : '-';
        $tgl_kembali = !empty($surat_data['tanggal_kembali']) ? date('j', strtotime($surat_data['tanggal_kembali'])) . ' ' . ($bulan_indo[(int)date('n', strtotime($surat_data['tanggal_kembali']))] ?? '') . ' ' . date('Y', strtotime($surat_data['tanggal_kembali'])) : '';
        $periode = $tgl_ber . ($tgl_kembali !== '' ? (' s.d. ' . $tgl_kembali) : '');
        $html .= '<tr><td style="width: 120px;">Periode</td><td style="width: 10px;">:</td><td>' . htmlspecialchars($periode) . '</td></tr>';
        $html .= '<tr><td>Waktu</td><td>:</td><td>' . htmlspecialchars((string)($surat_data['waktu_berangkat'] ?? '-')) . '</td></tr>';
        $html .= '<tr><td>Tujuan</td><td>:</td><td>' . htmlspecialchars((string)($surat_data['tujuan'] ?? '-')) . '</td></tr>';
        $html .= '</table>';

        $html .= '<p>3. &nbsp;&nbsp;Demikian mohon menjadikan periksa.</p>';
        $html .= '<br><br>';

        $html .= '<table style="width: 100%; font-size: 11pt;">';
        $html .= '<tr><td style="width: 50%;">&nbsp;</td><td style="text-align: center;">';
        $html .= 'Jakarta, ' . $tanggal . ' ' . $bulan . ' ' . $tahun . '<br>' . htmlspecialchars((string)($surat_data['pejabat_ttd_jabatan'] ?? ('Plh. Kepala ' . ($surat_data['nama_bagian'] ?? 'Bagian Pengamanan')))) . '<br>' . htmlspecialchars((string)($surat_data['pejabat_ttd_sebagai'] ?? '')) . '<br><br><br><br><strong>' . htmlspecialchars((string)($surat_data['pejabat_ttd'] ?? '')) . '</strong>';
        $html .= '</td></tr></table>';

        $html .= '<br><br>';

        // Tembusan (support JSON list)
        $tembusanArr = [];
        if (!empty($surat_data['tembusan_list'])) {
            $decodedT = json_decode($surat_data['tembusan_list'], true);
            if (is_array($decodedT)) $tembusanArr = $decodedT;
        }
        if (empty($tembusanArr)) {
            for ($i=1;$i<=4;$i++) {
                $k = 'tembusan_' . $i;
                if (!empty($surat_data[$k])) $tembusanArr[] = $surat_data[$k];
            }
        }

        if (!empty($tembusanArr)) {
            $html .= '<div><strong>Tembusan:</strong><ol style="margin-top:8px;">';
            foreach ($tembusanArr as $t) {
                $html .= '<li>' . htmlspecialchars($t) . '</li>';
            }
            $html .= '</ol></div>';
        }

        // Print text using writeHTML()
        $this->writeHTML($html, true, false, true, false, '');
    }
}
?>
