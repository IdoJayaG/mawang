<?php
require_once __DIR__ . '/../vendor/autoload.php';

class SuratTugasPDF extends TCPDF {
    
    // Page header
    public function Header() {
        // Set font
        $this->SetFont('helvetica', 'B', 14);
        
        // Title
        $this->Cell(0, 10, 'TENTARA NASIONAL INDONESIA', 0, 1, 'C');
        $this->SetFont('helvetica', 'B', 12);
        $this->Cell(0, 8, 'PUSAT INFORMASI DAN PENGOLAHAN DATA', 0, 1, 'C');
        $this->SetFont('helvetica', '', 10);
        $this->Cell(0, 6, 'Jalan Medan Merdeka Barat No. 13-14, Jakarta Pusat 10110', 0, 1, 'C');
        
        // Line break
        $this->Ln(5);
        
        // Line
        $this->Line(20, $this->GetY(), 190, $this->GetY());
        $this->Ln(10);
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
        
        // Set font
        $this->SetFont('helvetica', 'B', 14);
        
        // Title
        $this->Cell(0, 10, 'SURAT TUGAS', 0, 1, 'C');
        $this->SetFont('helvetica', '', 10);
        $this->Cell(0, 6, 'Nomor: ' . $surat_data['nomor_surat'], 0, 1, 'C');
        $this->Ln(10);
        
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
        
    // Content: two-column top area (left: klasifikasi/lampiran/perihal, right: Kepada)
    $html = '';

    // Top columns
    $html .= '<table style="width:100%; font-size:11pt;">';
    $html .= '<tr>';
    // left column: klasifikasi/lampiran/perihal
    $html .= '<td style="width:58%; vertical-align:top;">';
    $html .= '<table style="width:100%; font-size:11pt;">';
    $html .= '<tr><td style="width:120px; vertical-align:top;">Klasifikasi</td><td style="width:10px; vertical-align:top;">:</td><td>' . htmlspecialchars($surat_data['klasifikasi'] ?? '') . '</td></tr>';
    $html .= '<tr><td style="vertical-align:top;">Lampiran</td><td style="vertical-align:top;">:</td><td>' . htmlspecialchars($surat_data['lampiran'] ?? '') . '</td></tr>';
    $html .= '<tr><td style="vertical-align:top;">Perihal</td><td style="vertical-align:top;">:</td><td>' . htmlspecialchars($surat_data['perihal'] ?? '') . '</td></tr>';
    $html .= '</table>';
    $html .= '</td>';

    // right column: Kepada block
    $html .= '<td style="width:36%; vertical-align:top;">';
    $html .= '<div style="font-size:11pt;"><strong>Kepada</strong></div>';
    $html .= '<div style="margin-top:6px;">Yth.</div>';
    $html .= '<div style="margin-top:6px;">' . htmlspecialchars($surat_data['kepada_jabatan'] ?? '') . '</div>';
    $html .= '<div>di</div>';
    $html .= '<div>' . htmlspecialchars($surat_data['kepada_tempat'] ?? '') . '</div>';
    $html .= '</td>';

    $html .= '</tr>';
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
        $html .= '2. &nbsp;&nbsp;' . htmlspecialchars($surat_data['keperluan']) .
                 ', yang akan dilaksanakan pada:';
        $html .= '</p>';

        $html .= '<table style="width: 100%; margin-left: 30px; font-size: 11pt;">';
    // Hari/Tanggal in Indonesian
    $ts_berangkat = strtotime($surat_data['tanggal_berangkat']);
    $hari = $hari_indo[date('l', $ts_berangkat)] ?? date('l', $ts_berangkat);
    $tgl_ber = date('j', $ts_berangkat) . ' ' . $bulan_indo[(int)date('n', $ts_berangkat)] . ' ' . date('Y', $ts_berangkat);
    $html .= '<tr><td style="width: 100px;">Hari/Tanggal</td><td style="width: 10px;">:</td><td>' . $hari . ', ' . $tgl_ber . '</td></tr>';
        $html .= '<tr><td>Tempat</td><td>:</td><td>' . htmlspecialchars($surat_data['berangkat_dari']) . '</td></tr>';
        $html .= '<tr><td>Waktu</td><td>:</td><td>' . htmlspecialchars($surat_data['waktu_berangkat']) . '</td></tr>';
        $html .= '</table>';

        $html .= '<p>3. &nbsp;&nbsp;Demikian mohon dimaklumi.</p>';
        $html .= '<br><br>';

        $html .= '<table style="width: 100%; font-size: 11pt;">';
        $html .= '<tr><td style="width: 50%;">&nbsp;</td><td style="text-align: center;">';
        $html .= 'Jakarta, ' . $tanggal . ' ' . $bulan . ' ' . $tahun . '<br>' . htmlspecialchars($surat_data['pejabat_ttd_jabatan']) . '<br>' . htmlspecialchars($surat_data['pejabat_ttd_sebagai']) . '<br><br><br><br><strong>' . htmlspecialchars($surat_data['pejabat_ttd']) . '</strong>';
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
