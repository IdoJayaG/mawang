<?php
// Test file untuk mengecek format PDF surat tugas
$surat = [
    'nomor_surat' => 'ST/001/VIII/2025',
    'tanggal_surat' => '2025-08-21',
    'merk' => 'Toyota',
    'tipe' => 'Hiace',
    'tujuan' => 'Wisma Majestic Cisarua Bogor',
    'tanggal_berangkat' => '2025-12-15'
];

// Format tanggal Indonesia
$bulan_indo = [
    1 => 'Januari', 2 => 'Februari', 3 => 'Maret', 4 => 'April',
    5 => 'Mei', 6 => 'Juni', 7 => 'Juli', 8 => 'Agustus',
    9 => 'September', 10 => 'Oktober', 11 => 'November', 12 => 'Desember'
];
?>
<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <title>Test Surat Tugas TNI</title>
    <style>
        @page { 
            size: A4; 
            margin: 2.5cm 2cm 2cm 2cm;
        }
        @media print {
            .no-print { display: none !important; }
            body { font-family: 'Times New Roman', serif; font-size: 12pt; line-height: 1.4; }
        }
        body { 
            font-family: 'Times New Roman', serif; 
            font-size: 12pt; 
            line-height: 1.4; 
            color: #000;
            margin: 0;
            padding: 15px;
            background: white;
        }
        .header { 
            text-align: center; 
            margin-bottom: 25px; 
            border-bottom: 2px solid #000;
            padding-bottom: 10px;
        }
        .header h2 { 
            margin: 2px 0; 
            font-weight: bold; 
            font-size: 14pt;
            letter-spacing: 1px;
        }
        .header h3 { 
            margin: 2px 0; 
            font-weight: bold; 
            font-size: 13pt;
        }
        .header p { 
            margin: 2px 0; 
            font-size: 11pt;
        }
        .content {
            text-align: justify;
            margin-bottom: 15px;
        }
        .content p {
            margin: 8px 0;
            text-indent: 30px;
        }
        .data-table { 
            width: 100%; 
            border-collapse: collapse; 
            margin: 10px 0;
        }
        .data-table td { 
            padding: 2px 5px; 
            vertical-align: top;
            border: none;
        }
        .data-table .label {
            width: 120px;
            font-weight: normal;
        }
        .data-table .colon {
            width: 15px;
            text-align: center;
        }
        .signature-section { 
            margin-top: 40px; 
        }
        .signature-table {
            width: 100%;
            border-collapse: collapse;
        }
        .signature-table td {
            vertical-align: top;
            padding: 5px;
        }
        .signature-left {
            width: 50%;
            text-align: left;
        }
        .signature-right {
            width: 50%;
            text-align: center;
        }
        .signature-space {
            height: 70px;
        }
        .tembusan {
            margin-top: 30px;
            font-size: 11pt;
        }
        .tembusan-list {
            margin-left: 20px;
            margin-top: 5px;
        }
    </style>
</head>
<body>
    <div class="no-print" style="margin-bottom: 20px; text-align: right;">
        <button onclick="window.print()" style="padding: 8px 15px; background: #dc3545; color: white; border: none; border-radius: 4px; cursor: pointer; font-size: 11pt;">
            🖨️ Print / Save as PDF
        </button>
        <button onclick="window.close()" style="padding: 8px 15px; background: #6c757d; color: white; border: none; border-radius: 4px; cursor: pointer; margin-left: 10px; font-size: 11pt;">
            ✖️ Tutup
        </button>
    </div>

    <!-- Header Surat -->
    <div class="header">
        <h2>MARKAS BESAR TENTARA NASIONAL INDONESIA</h2>
        <h3>PUSAT INFORMASI DAN PENGOLAHAN DATA</h3>
        <p>Jalan Medan Merdeka Barat No. 13-14, Jakarta Pusat 10110</p>
    </div>

    <!-- Kop Surat Info -->
    <table style="width: 100%; margin-bottom: 20px; font-size: 11pt;">
        <tr>
            <td style="width: 60px;">Nomor</td>
            <td style="width: 10px;">:</td>
            <td><?= htmlspecialchars($surat['nomor_surat']) ?></td>
            <td style="width: 100px; text-align: right;">Jakarta,</td>
            <td style="width: 120px; text-align: right;">
                <?php 
                $tgl_surat = date('j', strtotime($surat['tanggal_surat']));
                $bln_surat = (int)date('n', strtotime($surat['tanggal_surat']));
                $thn_surat = date('Y', strtotime($surat['tanggal_surat']));
                echo $tgl_surat . ' ' . $bulan_indo[$bln_surat] . ' ' . $thn_surat;
                ?>
            </td>
        </tr>
        <tr>
            <td>Klasifikasi</td>
            <td>:</td>
            <td>Biasa</td>
            <td colspan="2"></td>
        </tr>
        <tr>
            <td>Lampiran</td>
            <td>:</td>
            <td>-</td>
            <td colspan="2"></td>
        </tr>
        <tr>
            <td>Perihal</td>
            <td>:</td>
            <td>Permohonan peminjaman kendaraan<br>&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;dinas bus dan tenaga medis</td>
            <td style="text-align: right; vertical-align: top;">Kepada</td>
            <td></td>
        </tr>
        <tr>
            <td colspan="3"></td>
            <td colspan="2" style="text-align: right; vertical-align: top; padding-top: 10px;">
                Yth. Dandenma Mabes TNI<br>
                <span style="margin-left: 20px;">di</span><br>
                <span style="margin-left: 40px;">Jakarta</span>
            </td>
        </tr>
    </table>

    <!-- Isi Surat -->
    <div class="content">
        <p>1. &nbsp;&nbsp;Dasar:</p>
        <div style="margin-left: 30px;">
            <p style="text-indent: 0;">a. &nbsp;&nbsp;Peraturan Panglima TNI Nomor 24 Tahun 2014 tentang Pengesahan Validasi Organisasi FKT dan;</p>
            <p style="text-indent: 0;">b. &nbsp;&nbsp;Surat Perintah Kapusinfolahta TNI Nomor Sprin/97/XI/2023 tanggal 20 Oktober 2023 tentang Fungsi Pengadaan HUT ke-17 Pusinfolahta TNI dan;</p>
        </div>

        <p>2. &nbsp;&nbsp;Perlengkapan Pimpinan dan Staf Pusinfolahta TNI.</p>

        <p>3. &nbsp;&nbsp;Sehubungan dasar di atas, dengan hormat diajukan permohonan peminjaman 1 (satu) unit <?= htmlspecialchars($surat['merk'] . ' ' . $surat['tipe']) ?> beserta pengendara dan tenaga medis sebanyak 3 (tiga) orang untuk keperluan kendaraan HUT ke-17 Pusinfolahta TNI di <?= htmlspecialchars($surat['tujuan']) ?>, yang akan dilaksanakan pada:</p>

        <table class="data-table" style="margin-left: 30px;">
            <tr>
                <td class="label">a. hari, tanggal</td>
                <td class="colon">:</td>
                <td>
                    <?php 
                    $hari = ['Minggu', 'Senin', 'Selasa', 'Rabu', 'Kamis', 'Jumat', 'Sabtu'];
                    $hari_berangkat = $hari[date('w', strtotime($surat['tanggal_berangkat']))];
                    $tgl_berangkat = date('j', strtotime($surat['tanggal_berangkat']));
                    $bln_berangkat = (int)date('n', strtotime($surat['tanggal_berangkat']));
                    $thn_berangkat = date('Y', strtotime($surat['tanggal_berangkat']));
                    echo $hari_berangkat . ', ' . $tgl_berangkat . ' ' . $bulan_indo[$bln_berangkat] . ' ' . $thn_berangkat;
                    ?>
                </td>
            </tr>
            <tr>
                <td class="label">b. waktu</td>
                <td class="colon">:</td>
                <td>Pukul 05.00 WIB s.d selesai</td>
            </tr>
            <tr>
                <td class="label">c. berangkat dari</td>
                <td class="colon">:</td>
                <td>Pusinfolahta TNI</td>
            </tr>
            <tr>
                <td class="label">d. tujuan</td>
                <td class="colon">:</td>
                <td><?= htmlspecialchars($surat['tujuan']) ?></td>
            </tr>
        </table>

        <p>4. &nbsp;&nbsp;Demikian mohon dimaklumi.</p>
    </div>

    <!-- Tanda Tangan -->
    <div class="signature-section">
        <table class="signature-table">
            <tr>
                <td class="signature-left"></td>
                <td class="signature-right">
                    <p>a.n Kepala Pusinfolahta TNI</p>
                    <p>Waka,</p>
                </td>
            </tr>
            <tr>
                <td class="signature-left"></td>
                <td class="signature-right">
                    <div class="signature-space"></div>
                </td>
            </tr>
            <tr>
                <td class="signature-left"></td>
                <td class="signature-right">
                    <p><strong>S. Ginting, S.Kom., MMSI., M.Tr.Hankam</strong></p>
                    <p><strong>Kolonel Laut (E) NRP 13475/P</strong></p>
                </td>
            </tr>
        </table>
    </div>

    <!-- Tembusan -->
    <div class="tembusan">
        <p><strong>Tembusan:</strong></p>
        <div class="tembusan-list">
            <p>1. Kapusinfolahta TNI</p>
            <p>2. Asops Denma Mabes TNI</p>
            <p>3. Dansetang Denma Mabes TNI</p>
            <p>4. Dansakdok Denma Mabes TNI</p>
        </div>
    </div>

    <script>
        // Auto print ketika halaman dimuat (opsional)
        // window.onload = function() { window.print(); }
    </script>
</body>
</html>
