-- phpMyAdmin SQL Dump
-- version 5.2.1
-- https://www.phpmyadmin.net/
--
-- Host: 127.0.0.1
-- Waktu pembuatan: 09 Sep 2025 pada 03.39
-- Versi server: 10.4.32-MariaDB
-- Versi PHP: 8.2.12

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
START TRANSACTION;
SET time_zone = "+00:00";


/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;

--
-- Database: `randis`
--

DELIMITER $$
--
-- Fungsi
--
CREATE DEFINER=`root`@`localhost` FUNCTION `CalculateFuelConsumption` (`p_fuel_liters` DECIMAL(8,2), `p_distance_km` INT) RETURNS DECIMAL(8,2) DETERMINISTIC READS SQL DATA BEGIN
    DECLARE consumption DECIMAL(8,2) DEFAULT 0;
    
    IF p_distance_km > 0 THEN
        SET consumption = (p_fuel_liters / p_distance_km) * 100;
    END IF;
    
    RETURN consumption;
END$$

DELIMITER ;

-- --------------------------------------------------------

--
-- Struktur dari tabel `dokumen_kendaraan`
--

CREATE TABLE `dokumen_kendaraan` (
  `id` int(11) NOT NULL,
  `kendaraan_id` int(11) NOT NULL,
  `jenis_dokumen` enum('STNK','BPKB','Surat Polisi','Asuransi','KIR','Lainnya') NOT NULL,
  `nomor_dokumen` varchar(50) DEFAULT NULL,
  `tanggal_terbit` date DEFAULT NULL,
  `tanggal_berlaku` date DEFAULT NULL,
  `instansi_penerbit` varchar(100) DEFAULT NULL,
  `file_dokumen` varchar(255) DEFAULT NULL,
  `status` enum('Aktif','Kadaluarsa','Dalam Proses') DEFAULT 'Aktif',
  `keterangan` text DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `created_by` int(11) DEFAULT NULL,
  `updated_by` int(11) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data untuk tabel `dokumen_kendaraan`
--

INSERT INTO `dokumen_kendaraan` (`id`, `kendaraan_id`, `jenis_dokumen`, `nomor_dokumen`, `tanggal_terbit`, `tanggal_berlaku`, `instansi_penerbit`, `file_dokumen`, `status`, `keterangan`, `created_at`, `updated_at`, `created_by`, `updated_by`) VALUES
(3, 1, 'STNK', 'TNI-STNK-001-2022', '2022-08-15', '2027-08-31', 'Polda Metro Jaya', NULL, 'Aktif', 'STNK atas nama MABES TNI', '2025-08-14 05:11:51', '2025-08-29 08:55:36', NULL, 2),
(4, 1, 'BPKB', 'TNI-BPKB-001-2022', '2025-09-03', '2025-09-18', 'Polda Metro Jaya', NULL, 'Aktif', 'BPKB disimpan', '2025-08-14 05:11:51', '2025-09-08 08:58:18', NULL, 1),
(6, 3, 'STNK', 'TNI-STNK-003-2021', '2021-12-31', '2026-12-31', 'Polda Metro Jaya', NULL, 'Aktif', 'STNK kendaraan operasional', '2025-08-14 05:11:51', '2025-08-14 05:11:51', NULL, NULL),
(8, 1, 'STNK', 'TNI-BPKB-001-2024', '2025-08-27', '2026-10-27', 'Ss', '1_STNK_68acfbe429c78_20250826071220.pdf', 'Aktif', '', '2025-08-26 00:12:20', '2025-08-26 00:12:20', 2, NULL),
(10, 1, 'STNK', 'TNI-STNK-002-2022', '2025-09-09', '2026-09-09', 'Mabes TNI', NULL, 'Aktif', '', '2025-09-08 04:49:30', '2025-09-08 04:49:30', 1, NULL);

-- --------------------------------------------------------

--
-- Struktur dari tabel `jadwal_perawatan`
--

CREATE TABLE `jadwal_perawatan` (
  `id` int(11) NOT NULL,
  `kendaraan_id` int(11) NOT NULL,
  `jenis_perawatan` varchar(100) NOT NULL,
  `deskripsi` text DEFAULT NULL,
  `bengkel` varchar(50) DEFAULT NULL COMMENT 'Nama bengkel atau lokasi perawatan',
  `tanggal_perawatan` date DEFAULT NULL,
  `km_kembali` int(11) DEFAULT NULL,
  `km_saat_perawatan` int(11) DEFAULT NULL,
  `estimasi_biaya` decimal(15,2) DEFAULT NULL COMMENT 'Estimasi biaya perawatan',
  `status` enum('Terjadwal','Dalam Proses','Selesai','Terlewat','Dibatalkan','Ditunda') DEFAULT 'Terjadwal',
  `prioritas` varchar(30) DEFAULT NULL COMMENT 'Tingkat prioritas: Normal, Tinggi, Urgent',
  `reminder_sent` tinyint(1) DEFAULT 0,
  `tanggal_selesai` date DEFAULT NULL,
  `biaya_aktual` decimal(15,2) DEFAULT NULL COMMENT 'Biaya aktual yang dikeluarkan untuk perawatan',
  `keterangan` text DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `created_by` int(11) DEFAULT NULL,
  `updated_by` int(11) DEFAULT NULL,
  `teknisi_id` int(11) DEFAULT NULL COMMENT 'ID pengguna yang bertindak sebagai teknisi'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data untuk tabel `jadwal_perawatan`
--

INSERT INTO `jadwal_perawatan` (`id`, `kendaraan_id`, `jenis_perawatan`, `deskripsi`, `bengkel`, `tanggal_perawatan`, `km_kembali`, `km_saat_perawatan`, `estimasi_biaya`, `status`, `prioritas`, `reminder_sent`, `tanggal_selesai`, `biaya_aktual`, `keterangan`, `created_at`, `updated_at`, `created_by`, `updated_by`, `teknisi_id`) VALUES
(12, 17, 'Tune Up', 'Tune up mesin rutin', 'asa', '2025-08-28', 1000, 14500, 127000.00, 'Selesai', 'Tinggi', 0, '2025-08-28', 127000.00, 'qw', '2025-08-20 06:52:48', '2025-08-27 02:55:41', 2, 1, 3),
(20, 21, 'wd', 'wd', 'wd', '2025-08-27', 3000, 322, 333.00, 'Selesai', 'Normal', 0, NULL, 127.00, '', '2025-08-27 01:42:10', '2025-08-27 01:42:32', 1, 1, NULL),
(21, 1, 'ac', 'sss', 'sss', '2025-08-27', 400, 322, 3000000.00, 'Selesai', 'Normal', 0, '2025-08-27', 3000000.00, '', '2025-08-27 01:50:58', '2025-08-27 02:55:41', 1, 1, NULL),
(29, 19, 'asd', 'sad', 'adas', '2025-08-29', NULL, NULL, 49999.00, 'Selesai', 'Normal', 0, '2025-08-29', 50000.00, '', '2025-08-29 07:46:30', '2025-08-29 08:01:28', 1, 2, NULL),
(33, 19, 'sss', 'sss', 'ss', '2025-08-29', NULL, NULL, 3000000.00, 'Selesai', 'Normal', 0, '2025-08-29', 4000000.00, '', '2025-08-29 09:54:30', '2025-08-29 09:54:51', 2, 2, NULL),
(35, 1, 'Service Rutin', 'Service rutin 5000km', 'Bengkel TNI', '2025-09-04', NULL, 25000, 500000.00, 'Selesai', 'Normal', 0, '2025-09-04', NULL, NULL, '2025-09-01 05:03:35', '2025-09-04 03:19:30', 1, NULL, NULL),
(39, 19, 'aa', 'aa', 'aa', '2025-09-03', NULL, 322, 700000.00, 'Selesai', 'Normal', 0, '2025-09-02', 2000000.00, '', '2025-09-02 04:54:43', '2025-09-02 04:55:06', 1, 1, NULL),
(44, 26, 's', 's', 's', '2025-09-02', NULL, 322, 500000.00, 'Selesai', 'Normal', 0, '2025-09-02', 500000.00, '', '2025-09-02 05:37:45', '2025-09-02 05:37:51', 1, 1, NULL),
(45, 24, 'as', 'qw', 'qw', '2025-09-02', NULL, 300, 300000.00, 'Selesai', 'Normal', 0, '2025-09-02', 400000.00, '', '2025-09-02 06:21:30', '2025-09-02 06:21:54', 1, 1, NULL),
(46, 24, 'ss', 'ss', 'ss', '2025-09-03', NULL, NULL, 30000.00, 'Selesai', 'Normal', 0, '2025-09-03', 30000.00, '', '2025-09-03 00:28:44', '2025-09-03 02:20:37', 1, 1, NULL),
(47, 26, 'as', 'as', 'as', '2025-09-03', NULL, 233, 300000.00, 'Selesai', 'Normal', 0, '2025-09-03', 300000.00, '', '2025-09-03 02:15:23', '2025-09-03 02:21:37', 1, 1, NULL),
(48, 26, 's', 's', 'ss', '2025-09-03', NULL, 322, 4000000.00, 'Selesai', 'Normal', 0, '2025-09-03', 4000000.00, '', '2025-09-03 02:41:03', '2025-09-03 02:46:34', 1, 1, NULL),
(49, 3, 'ddd', 'd', 'd', '2025-09-03', NULL, 2222, 400000.00, 'Selesai', 'Normal', 0, '2025-09-03', 400000.00, '', '2025-09-03 02:47:32', '2025-09-03 03:43:39', 1, 1, NULL),
(50, 19, 'aa', 'as', 'ad', '2025-09-05', NULL, 312, 400000.00, 'Selesai', 'Normal', 0, '2025-09-05', 400000.00, '2w', '2025-09-04 01:58:19', '2025-09-04 03:05:56', 1, 1, NULL),
(51, 17, 'Jjkkk', 'Xjdn', 'Hehe', '2025-09-09', NULL, 322, 500000.00, 'Terjadwal', 'Tinggi', 0, NULL, 500000.00, 'Sbsb', '2025-09-08 08:59:13', '2025-09-08 08:59:13', 1, NULL, 2);

--
-- Trigger `jadwal_perawatan`
--
DELIMITER $$
CREATE TRIGGER `tr_log_maintenance_completion` AFTER UPDATE ON `jadwal_perawatan` FOR EACH ROW BEGIN
    IF NEW.status = 'Selesai' AND OLD.status != 'Selesai' THEN
        INSERT INTO log_aktivitas (user_id, activity_type, description, created_at)
        VALUES (
            COALESCE(NEW.updated_by, NEW.created_by, 1),
            'COMPLETE_MAINTENANCE',
            CONCAT('Menyelesaikan jadwal perawatan ', NEW.jenis_perawatan, ' untuk kendaraan ', 
                   (SELECT no_polisi FROM kendaraan WHERE id = NEW.kendaraan_id)),
            CURRENT_TIMESTAMP
        );
    END IF;
END
$$
DELIMITER ;

-- --------------------------------------------------------

--
-- Struktur dari tabel `kendaraan`
--

CREATE TABLE `kendaraan` (
  `id` int(11) NOT NULL,
  `no_polisi` varchar(20) NOT NULL,
  `no_rangka` varchar(50) NOT NULL,
  `no_mesin` varchar(50) NOT NULL,
  `merk` varchar(50) NOT NULL,
  `tipe` varchar(50) DEFAULT NULL,
  `tahun_pembuatan` year(4) DEFAULT NULL,
  `warna` varchar(30) DEFAULT NULL,
  `jenis` enum('Roda 2','Roda 4','Truk','Bus','Lainnya') DEFAULT 'Roda 4',
  `bahan_bakar` enum('Bensin','Solar','Listrik','Hybrid') DEFAULT 'Bensin',
  `no_reg` varchar(30) DEFAULT NULL,
  `no_stnk` varchar(30) DEFAULT NULL,
  `no_bpkb` varchar(30) DEFAULT NULL,
  `tanggal_berlaku_stnk` date DEFAULT NULL,
  `pemilik_stnk` varchar(100) DEFAULT NULL,
  `status_kepemilikan` enum('Satker','Pinjam Pakai') DEFAULT 'Satker',
  `satker` varchar(50) NOT NULL,
  `kode_barang` varchar(50) DEFAULT NULL,
  `kondisi` enum('Baik','Rusak Ringan','Rusak Berat') DEFAULT 'Baik',
  `status_kendaraan` enum('Operasional','Perbaikan','Rusak','Tidak Aktif') DEFAULT 'Operasional',
  `status_peminjaman` enum('Tersedia','Dipinjam','Maintenance','Rusak') DEFAULT 'Tersedia',
  `tanggal_servis_terakhir` date DEFAULT NULL,
  `last_service_km` int(11) DEFAULT NULL,
  `odometer` int(11) DEFAULT 0,
  `foto` varchar(255) DEFAULT NULL,
  `catatan` text DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data untuk tabel `kendaraan`
--

INSERT INTO `kendaraan` (`id`, `no_polisi`, `no_rangka`, `no_mesin`, `merk`, `tipe`, `tahun_pembuatan`, `warna`, `jenis`, `bahan_bakar`, `no_reg`, `no_stnk`, `no_bpkb`, `tanggal_berlaku_stnk`, `pemilik_stnk`, `status_kepemilikan`, `satker`, `kode_barang`, `kondisi`, `status_kendaraan`, `status_peminjaman`, `tanggal_servis_terakhir`, `last_service_km`, `odometer`, `foto`, `catatan`, `created_at`, `updated_at`) VALUES
(1, 'B 1234 AC', 'RNG123456789', 'MSN123456789', 'Toyota', 'SUV', '2020', 'Hitam', 'Roda 4', 'Bensin', '8065-08', 'STNK001', NULL, '2025-12-31', 'LAKSDA TNI ARIANTYO CONDROWIBOWO', 'Satker', 'Pusinfolahta', 'BR423', 'Baik', 'Operasional', 'Tersedia', '2023-01-15', 20, 320, 'jeep.png', 'Kendaraan dinas TNI', '2025-08-13 01:14:11', '2025-09-08 01:37:34'),
(3, 'B 9101 EF', 'RNG456789123', 'MSN456789123', 'Suzuki', 'MPV', '2021', 'Biru', 'Roda 4', 'Bensin', '8658-08', 'STNK003', NULL, '2026-05-15', 'MAYJEN TNI TRI MARTONO', 'Satker', 'Pusinfolahta', 'BR764', 'Baik', 'Operasional', 'Tersedia', '2023-03-10', 20, 320, 'jeep.png', 'Kendaraan sewa TNI', '2025-08-13 01:14:11', '2025-09-03 03:43:39'),
(15, 'B 1234 CD', 'RNG12342345', 'MSN135563323', 'Avanza', 'SUV', '2002', 'Hitam', 'Roda 4', 'Bensin', '8755-08', NULL, NULL, NULL, NULL, 'Satker', 'Pusinfolahta', 'BR986', 'Baik', 'Operasional', 'Tersedia', NULL, 20, 320, 'jeep.png', NULL, '2025-08-12 18:34:07', '2025-08-28 00:31:08'),
(17, 'B 5678 CD', 'RNG987654321', 'MSN987654321', 'Honda', 'Sedan', '2019', 'Hitam', 'Roda 4', 'Bensin', '8545-08', 'STNK002', NULL, '2024-11-30', 'BRIGJEN TNI ROKHMAT', 'Pinjam Pakai', 'Pusinfolahta', 'BR655', 'Baik', 'Operasional', 'Tersedia', '2023-02-20', 20, 320, 'sedan.png', 'Kendaraan operasional TNI', '2025-08-12 18:14:11', '2025-09-01 00:21:37'),
(18, 'B 1234 CC', 'NRK4323423212', 'MSN12345678932', 'Toyota', 'SUV', '2022', 'Hitam', 'Roda 4', 'Bensin', '8232-08', NULL, NULL, NULL, NULL, 'Satker', 'Pusinfolahta', NULL, 'Baik', 'Operasional', 'Tersedia', NULL, 20, 320, 'sedan.png', NULL, '2025-08-21 04:31:09', '2025-08-29 09:33:56'),
(19, 'TEST123', 'RNG1234234', 'MSN13523445', 'Test Brand', 'Test Model', '2023', '', 'Roda 4', 'Bensin', '8667-08', NULL, NULL, NULL, NULL, 'Satker', 'Pusinfolahta', NULL, 'Baik', 'Operasional', 'Tersedia', NULL, 20, 320, 'sedan.png', NULL, '2025-08-21 04:40:41', '2025-09-04 08:07:39'),
(21, 'B1234CD', 'MHKA1BA1GLK123456', '2NR-FE456789', 'Toyota', 'Avanza', '2020', 'Hijau', 'Roda 4', 'Bensin', '8433-08', NULL, NULL, NULL, NULL, 'Satker', 'Pusinfolahta', NULL, 'Baik', 'Operasional', 'Tersedia', NULL, 20, 320, NULL, 'Kendaraan dinas', '2025-08-25 04:25:40', '2025-09-01 00:21:32'),
(22, 'B5678EF', 'JHMFC5F16KS123456', 'R18A789012', 'Honda', 'Civic', '2019', 'Hitam', 'Roda 4', 'Bensin', '8767-08', NULL, NULL, NULL, NULL, 'Satker', 'Pusinfolahta', NULL, 'Baik', 'Operasional', 'Tersedia', NULL, 20, 320, NULL, 'Kendaraan operasional', '2025-08-25 04:25:40', '2025-09-03 03:05:33'),
(23, 'B9012GH', 'MMBJNK64WMH123456', '4M41456789', 'Mitsubishi', 'Pajero', '2021', 'Biru', 'Roda 4', 'Solar', '8697-08', NULL, NULL, NULL, NULL, 'Satker', 'Pusinfolahta', NULL, 'Baik', 'Operasional', 'Tersedia', '0000-00-00', 20, 320, NULL, 'Sedang dalam perbaikan', '2025-08-25 04:25:40', '2025-09-01 00:21:24'),
(24, 'S 1234 CD', 'MHKABKA1GLK123456', 'GTNR-FE456782', 'Toyota', 'Avanza', '2020', 'Hitam', 'Roda 4', 'Bensin', '8272-02', NULL, NULL, NULL, NULL, 'Satker', 'Pusinfo', NULL, 'Baik', 'Operasional', 'Tersedia', NULL, NULL, 0, NULL, 'Kendaraan dinas', '2025-09-01 05:20:53', '2025-09-04 05:21:46'),
(25, 'F5888EF', 'MFC5F1CC6KS123456', 'GTR18A789012', 'Honda', 'Civic', '2019', 'Hitam', 'Roda 4', 'Bensin', '0822-02', NULL, NULL, NULL, NULL, 'Satker', '', NULL, 'Baik', 'Operasional', 'Tersedia', NULL, NULL, 0, NULL, 'Kendaraan operasional', '2025-09-01 05:20:53', '2025-09-03 12:36:41'),
(26, 'C9012GH', 'MNK6J4WMH123456', 'NK4M41456789', 'Mitsubishi', 'Pajero', '2021', 'Biru', 'Roda 4', 'Bensin', '8811-01', NULL, NULL, NULL, NULL, 'Satker', '', NULL, 'Baik', 'Operasional', 'Tersedia', NULL, NULL, 0, NULL, 'Sedang dalam perbaikan', '2025-09-01 05:20:53', '2025-09-03 02:46:34'),
(27, 'S 222 CD', 'NRK8712763', 'NM9712421', 'Lexus', 'Sedan', '2022', 'Hitam', 'Roda 4', 'Bensin', '0200-08', NULL, NULL, NULL, NULL, 'Satker', '', NULL, 'Baik', 'Operasional', 'Tersedia', NULL, NULL, 0, NULL, NULL, '2025-09-02 03:05:45', '2025-09-03 12:36:41');

-- --------------------------------------------------------

--
-- Struktur dari tabel `kesatuan`
--

CREATE TABLE `kesatuan` (
  `id` int(11) NOT NULL,
  `nama_kesatuan` varchar(100) NOT NULL,
  `kode_kesatuan` varchar(20) NOT NULL,
  `korps_id` int(11) DEFAULT NULL,
  `parent_id` int(11) DEFAULT NULL COMMENT 'For hierarchical organization',
  `level_kesatuan` enum('MABES','KOTAMA','KODAM','KOREM','KODIM','KORAMIL','SATKER') NOT NULL DEFAULT 'SATKER',
  `alamat` text DEFAULT NULL,
  `telepon` varchar(20) DEFAULT NULL,
  `email` varchar(100) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Master data Kesatuan TNI';

--
-- Dumping data untuk tabel `kesatuan`
--

INSERT INTO `kesatuan` (`id`, `nama_kesatuan`, `kode_kesatuan`, `korps_id`, `parent_id`, `level_kesatuan`, `alamat`, `telepon`, `email`, `created_at`, `updated_at`) VALUES
(1, 'Pusat Informasi Pengolah Data', 'PUSINFOLAHTA', 6, NULL, 'SATKER', NULL, NULL, NULL, '2025-08-20 11:00:08', '2025-08-20 11:00:08'),
(2, 'Batalyon Raider 1', 'YONRAIDER1', 1, NULL, 'SATKER', NULL, NULL, NULL, '2025-08-20 11:00:08', '2025-08-20 11:00:08'),
(3, 'Pasmar 1', 'PASMAR1', 4, NULL, 'SATKER', NULL, NULL, NULL, '2025-08-20 11:00:08', '2025-08-20 11:00:08'),
(4, 'Lanud Halim Perdanakusuma', 'LANUD_HLP', 5, NULL, 'SATKER', NULL, NULL, NULL, '2025-08-20 11:00:08', '2025-08-20 11:00:08');

-- --------------------------------------------------------

--
-- Struktur dari tabel `korps`
--

CREATE TABLE `korps` (
  `id` int(11) NOT NULL,
  `nama_korps` varchar(100) NOT NULL,
  `kode_korps` varchar(20) NOT NULL,
  `matra_id` int(11) NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Master data Korps TNI';

--
-- Dumping data untuk tabel `korps`
--

INSERT INTO `korps` (`id`, `nama_korps`, `kode_korps`, `matra_id`, `created_at`, `updated_at`) VALUES
(1, 'Infanteri', 'INF', 1, '2025-08-20 11:00:08', '2025-08-20 11:00:08'),
(2, 'Kavaleri', 'KAV', 1, '2025-08-20 11:00:08', '2025-08-20 11:00:08'),
(3, 'Artileri', 'ART', 1, '2025-08-20 11:00:08', '2025-08-20 11:00:08'),
(4, 'Korps Marinir', 'MAR', 2, '2025-08-20 11:00:08', '2025-08-20 11:00:08'),
(5, 'Korps Penerbang', 'PNB', 3, '2025-08-20 11:00:08', '2025-08-20 11:00:08'),
(6, 'Pusinfolahta', 'PUSINFO', 4, '2025-08-20 11:00:08', '2025-08-20 11:00:08');

-- --------------------------------------------------------

--
-- Struktur dari tabel `log_aktivitas`
--

CREATE TABLE `log_aktivitas` (
  `id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `activity_type` varchar(50) NOT NULL,
  `description` text NOT NULL,
  `ip_address` varchar(45) DEFAULT NULL,
  `user_agent` text DEFAULT NULL,
  `session_id` varchar(128) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data untuk tabel `log_aktivitas`
--

INSERT INTO `log_aktivitas` (`id`, `user_id`, `activity_type`, `description`, `ip_address`, `user_agent`, `session_id`, `created_at`) VALUES
(1, 1, 'LOGIN', 'User login ke sistem', '127.0.0.1', NULL, NULL, '2025-08-21 04:34:52'),
(2, 1, 'VIEW_VEHICLE', 'Melihat daftar kendaraan', '127.0.0.1', NULL, NULL, '2025-08-21 04:34:52'),
(3, 1, 'CREATE_VEHICLE', 'Menambah kendaraan baru', '127.0.0.1', NULL, NULL, '2025-08-21 04:34:52'),
(6, 3, 'LOGOUT', 'User keluar dari sistem', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/139.0.0.0 Safari/537.36', NULL, '2025-08-21 04:49:35'),
(7, 3, 'LOGIN', 'User berhasil login ke sistem', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/139.0.0.0 Safari/537.36', NULL, '2025-08-21 05:20:23'),
(8, 3, 'LOGOUT', 'User keluar dari sistem', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/139.0.0.0 Safari/537.36', NULL, '2025-08-21 05:24:00'),
(9, 3, 'LOGIN', 'User berhasil login ke sistem', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/139.0.0.0 Safari/537.36', NULL, '2025-08-21 05:24:13'),
(10, 3, 'LOGOUT', 'User keluar dari sistem', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/139.0.0.0 Safari/537.36', NULL, '2025-08-21 05:27:28'),
(11, 2, 'LOGIN', 'User berhasil login ke sistem', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/139.0.0.0 Safari/537.36', NULL, '2025-08-21 05:28:26'),
(12, 2, 'LOGOUT', 'User keluar dari sistem', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/139.0.0.0 Safari/537.36', NULL, '2025-08-21 05:40:26'),
(13, 2, 'LOGIN', 'User berhasil login ke sistem', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/139.0.0.0 Safari/537.36', NULL, '2025-08-21 05:40:45'),
(14, 2, 'UPDATE_VEHICLE', 'Mengupdate kendaraan: TEST123 - Test Brand', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/139.0.0.0 Safari/537.36', NULL, '2025-08-21 05:48:24'),
(15, 2, 'UPDATE_VEHICLE', 'Mengupdate kendaraan: TEST123 - Test Brand', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/139.0.0.0 Safari/537.36', NULL, '2025-08-21 05:48:38'),
(16, 2, 'LOGOUT', 'User keluar dari sistem', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/139.0.0.0 Safari/537.36', NULL, '2025-08-21 05:50:50'),
(17, 3, 'LOGIN', 'User berhasil login ke sistem', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/139.0.0.0 Safari/537.36', NULL, '2025-08-21 06:02:21'),
(18, 3, 'LOGOUT', 'User keluar dari sistem', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/139.0.0.0 Safari/537.36', NULL, '2025-08-21 06:05:04'),
(19, 2, 'LOGIN', 'User berhasil login ke sistem', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/139.0.0.0 Safari/537.36', NULL, '2025-08-21 06:05:14'),
(20, 2, 'LOGOUT', 'User keluar dari sistem', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/139.0.0.0 Safari/537.36', NULL, '2025-08-21 06:06:17'),
(21, 3, 'LOGIN', 'User berhasil login ke sistem', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/139.0.0.0 Safari/537.36', NULL, '2025-08-21 06:06:26'),
(22, 3, 'LOGOUT', 'User keluar dari sistem', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/139.0.0.0 Safari/537.36', NULL, '2025-08-21 06:07:12'),
(23, 3, 'LOGIN', 'User berhasil login ke sistem', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/139.0.0.0 Safari/537.36', NULL, '2025-08-21 06:09:22'),
(24, 3, 'LOGOUT', 'User keluar dari sistem', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/139.0.0.0 Safari/537.36', NULL, '2025-08-21 06:11:23'),
(25, 3, 'LOGIN', 'User berhasil login ke sistem', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/139.0.0.0 Safari/537.36', NULL, '2025-08-21 06:11:29'),
(26, 3, 'LOGOUT', 'User keluar dari sistem', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/139.0.0.0 Safari/537.36', NULL, '2025-08-21 06:11:41'),
(27, 3, 'LOGIN', 'User berhasil login ke sistem', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/139.0.0.0 Safari/537.36', NULL, '2025-08-21 06:11:48'),
(28, 3, 'LOGOUT', 'User keluar dari sistem', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/139.0.0.0 Safari/537.36', NULL, '2025-08-21 06:12:08'),
(29, 3, 'LOGIN', 'User berhasil login ke sistem', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/139.0.0.0 Safari/537.36', NULL, '2025-08-21 06:12:15'),
(30, 3, 'LOGOUT', 'User keluar dari sistem', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/139.0.0.0 Safari/537.36', NULL, '2025-08-21 06:12:54'),
(31, 1, 'LOGIN', 'User berhasil login ke sistem', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/139.0.0.0 Safari/537.36', NULL, '2025-08-21 06:13:03'),
(32, 1, 'LOGIN', 'User berhasil login ke sistem', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/139.0.0.0 Safari/537.36', NULL, '2025-08-21 06:13:10'),
(33, 1, 'LOGOUT', 'User keluar dari sistem', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/139.0.0.0 Safari/537.36', NULL, '2025-08-21 06:15:49'),
(34, 3, 'LOGIN', 'User berhasil login ke sistem', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/139.0.0.0 Safari/537.36', NULL, '2025-08-21 06:15:55'),
(35, 3, 'LOGOUT', 'User keluar dari sistem', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/139.0.0.0 Safari/537.36', NULL, '2025-08-21 06:16:55'),
(36, 2, 'LOGIN', 'User berhasil login ke sistem', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/139.0.0.0 Safari/537.36', NULL, '2025-08-21 06:17:04'),
(37, 2, 'LOGOUT', 'User keluar dari sistem', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/139.0.0.0 Safari/537.36', NULL, '2025-08-21 06:17:29'),
(38, 2, 'LOGIN', 'User berhasil login ke sistem', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/139.0.0.0 Safari/537.36', NULL, '2025-08-21 06:17:40'),
(39, 2, 'LOGOUT', 'User keluar dari sistem', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/139.0.0.0 Safari/537.36', NULL, '2025-08-21 06:18:02'),
(40, 3, 'LOGIN', 'User berhasil login ke sistem', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/139.0.0.0 Safari/537.36', NULL, '2025-08-21 06:18:13'),
(41, 3, 'LOGOUT', 'User keluar dari sistem', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/139.0.0.0 Safari/537.36', NULL, '2025-08-21 07:09:52'),
(42, 2, 'LOGIN', 'User berhasil login ke sistem', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/139.0.0.0 Safari/537.36', NULL, '2025-08-21 07:10:17'),
(43, 2, 'LOGOUT', 'User keluar dari sistem', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/139.0.0.0 Safari/537.36', NULL, '2025-08-21 07:14:15'),
(44, 1, 'LOGIN', 'User berhasil login ke sistem', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/139.0.0.0 Safari/537.36', NULL, '2025-08-21 07:14:28'),
(45, 1, 'LOGOUT', 'User keluar dari sistem', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/139.0.0.0 Safari/537.36', NULL, '2025-08-21 07:40:31'),
(46, 3, 'LOGIN', 'User berhasil login ke sistem', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/139.0.0.0 Safari/537.36', NULL, '2025-08-21 07:40:41'),
(47, 3, 'LOGOUT', 'User keluar dari sistem', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/139.0.0.0 Safari/537.36', NULL, '2025-08-21 13:26:26'),
(48, 3, 'LOGIN', 'User berhasil login ke sistem', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/139.0.0.0 Safari/537.36', NULL, '2025-08-21 13:36:48'),
(49, 3, 'LOGOUT', 'User keluar dari sistem', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/139.0.0.0 Safari/537.36', NULL, '2025-08-21 13:38:54'),
(50, 3, 'LOGIN', 'User berhasil login ke sistem', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/139.0.0.0 Safari/537.36', NULL, '2025-08-22 00:01:09'),
(51, 3, 'LOGOUT', 'User keluar dari sistem', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/139.0.0.0 Safari/537.36', NULL, '2025-08-22 01:24:37'),
(52, 3, 'LOGIN', 'User berhasil login ke sistem', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/139.0.0.0 Safari/537.36', NULL, '2025-08-22 01:24:42'),
(53, 3, 'LOGOUT', 'User keluar dari sistem', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/139.0.0.0 Safari/537.36', NULL, '2025-08-22 04:26:10'),
(54, 3, 'LOGIN', 'User berhasil login ke sistem', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/139.0.0.0 Safari/537.36', NULL, '2025-08-22 04:26:15'),
(55, 3, 'LOGOUT', 'User keluar dari sistem', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/139.0.0.0 Safari/537.36', NULL, '2025-08-22 04:32:07'),
(56, 3, 'LOGIN', 'User berhasil login ke sistem', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/139.0.0.0 Safari/537.36', NULL, '2025-08-22 04:32:13'),
(57, 3, 'LOGOUT', 'User keluar dari sistem', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/139.0.0.0 Safari/537.36', NULL, '2025-08-22 08:12:46'),
(58, 1, 'LOGIN', 'User berhasil login ke sistem', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/139.0.0.0 Safari/537.36', NULL, '2025-08-22 08:12:53'),
(59, 3, 'LOGIN', 'User berhasil login ke sistem', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/139.0.0.0 Safari/537.36 Edg/139.0.0.0', NULL, '2025-08-22 08:17:14'),
(60, 3, 'LOGOUT', 'User keluar dari sistem', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/139.0.0.0 Safari/537.36 Edg/139.0.0.0', NULL, '2025-08-22 08:29:34'),
(61, 3, 'LOGIN', 'User berhasil login ke sistem', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/139.0.0.0 Safari/537.36 Edg/139.0.0.0', NULL, '2025-08-22 08:29:41'),
(62, 3, 'LOGIN', 'User berhasil login ke sistem', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/139.0.0.0 Safari/537.36 Edg/139.0.0.0', NULL, '2025-08-22 08:32:52'),
(63, 3, 'LOGOUT', 'User keluar dari sistem', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/139.0.0.0 Safari/537.36 Edg/139.0.0.0', NULL, '2025-08-22 08:33:20'),
(64, 3, 'LOGIN', 'User berhasil login ke sistem', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/139.0.0.0 Safari/537.36 Edg/139.0.0.0', NULL, '2025-08-22 08:33:26'),
(65, 1, 'LOGOUT', 'User keluar dari sistem', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/139.0.0.0 Safari/537.36', NULL, '2025-08-22 08:33:45'),
(66, 3, 'LOGIN', 'User berhasil login ke sistem', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/139.0.0.0 Safari/537.36', NULL, '2025-08-22 08:33:52'),
(67, 3, 'LOGIN', 'User berhasil login ke sistem', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/139.0.0.0 Safari/537.36', NULL, '2025-08-24 23:58:27'),
(68, 3, 'LOGIN', 'User berhasil login ke sistem', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/139.0.0.0 Safari/537.36', NULL, '2025-08-24 23:59:37'),
(69, 3, 'LOGOUT', 'User keluar dari sistem', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/139.0.0.0 Safari/537.36', NULL, '2025-08-25 00:31:37'),
(70, 3, 'LOGIN', 'User berhasil login ke sistem', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/139.0.0.0 Safari/537.36', NULL, '2025-08-25 00:31:43'),
(71, 3, 'LOGIN', 'User berhasil login ke sistem', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/139.0.0.0 Safari/537.36', NULL, '2025-08-25 01:32:50'),
(72, 3, 'LOGOUT', 'User keluar dari sistem', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/139.0.0.0 Safari/537.36', NULL, '2025-08-25 03:32:19'),
(73, 3, 'LOGIN', 'User berhasil login ke sistem', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/139.0.0.0 Safari/537.36', NULL, '2025-08-25 03:33:44'),
(74, 3, 'LOGOUT', 'User keluar dari sistem', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/139.0.0.0 Safari/537.36', NULL, '2025-08-25 03:33:48'),
(75, 3, 'LOGIN', 'User berhasil login ke sistem', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/139.0.0.0 Safari/537.36', NULL, '2025-08-25 03:34:14'),
(76, 3, 'LOGOUT', 'User keluar dari sistem', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/139.0.0.0 Safari/537.36', NULL, '2025-08-25 03:35:23'),
(77, 3, 'LOGIN', 'User berhasil login ke sistem', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/139.0.0.0 Safari/537.36', NULL, '2025-08-25 03:35:29'),
(78, 3, 'LOGOUT', 'User keluar dari sistem', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/139.0.0.0 Safari/537.36', NULL, '2025-08-25 03:38:49'),
(79, 3, 'LOGIN', 'User berhasil login ke sistem', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/139.0.0.0 Safari/537.36', NULL, '2025-08-25 03:38:54'),
(80, 3, 'LOGOUT', 'User keluar dari sistem', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/139.0.0.0 Safari/537.36', NULL, '2025-08-25 03:39:53'),
(81, 2, 'LOGIN', 'User berhasil login ke sistem', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/139.0.0.0 Safari/537.36', NULL, '2025-08-25 03:40:00'),
(82, 2, 'UPDATE_MAINTENANCE_STATUS', 'Mengubah status jadwal perawatan Servis Berkala menjadi Terjadwal', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/139.0.0.0 Safari/537.36', NULL, '2025-08-25 04:23:59'),
(83, 2, 'UPDATE_VEHICLE', 'Mengupdate kendaraan: TEST123 - Test Brand', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/139.0.0.0 Safari/537.36', NULL, '2025-08-25 04:25:21'),
(84, 2, 'IMPORT_VEHICLE', 'Import kendaraan: B1234CD - Toyota', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/139.0.0.0 Safari/537.36', NULL, '2025-08-25 04:25:40'),
(85, 2, 'IMPORT_VEHICLE', 'Import kendaraan: B5678EF - Honda', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/139.0.0.0 Safari/537.36', NULL, '2025-08-25 04:25:40'),
(86, 2, 'IMPORT_VEHICLE', 'Import kendaraan: B9012GH - Mitsubishi', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/139.0.0.0 Safari/537.36', NULL, '2025-08-25 04:25:40'),
(87, 2, 'LOGOUT', 'User keluar dari sistem', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/139.0.0.0 Safari/537.36', NULL, '2025-08-25 04:26:35'),
(88, 1, 'LOGIN', 'User berhasil login ke sistem', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/139.0.0.0 Safari/537.36', NULL, '2025-08-25 04:26:47'),
(89, 1, 'LOGOUT', 'User keluar dari sistem', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/139.0.0.0 Safari/537.36', NULL, '2025-08-25 04:27:39'),
(90, 2, 'LOGIN', 'User berhasil login ke sistem', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/139.0.0.0 Safari/537.36', NULL, '2025-08-25 04:27:47'),
(91, 3, 'LOGOUT', 'User keluar dari sistem', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/139.0.0.0 Safari/537.36', NULL, '2025-08-25 06:27:59'),
(92, 2, 'LOGIN', 'User berhasil login ke sistem', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/139.0.0.0 Safari/537.36', NULL, '2025-08-25 06:28:08'),
(93, 2, 'UPDATE_MAINTENANCE_STATUS', 'Mengubah status jadwal perawatan Tune Up menjadi Dalam Proses', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/139.0.0.0 Safari/537.36', NULL, '2025-08-25 07:20:01'),
(94, 2, 'UPDATE_MAINTENANCE_STATUS', 'Mengubah status jadwal perawatan Ganti Ban menjadi Dalam Proses', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/139.0.0.0 Safari/537.36', NULL, '2025-08-25 07:20:07'),
(95, 2, 'UPDATE_MAINTENANCE_STATUS', 'Mengubah status jadwal perawatan Ganti Ban menjadi Terjadwal', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/139.0.0.0 Safari/537.36', NULL, '2025-08-25 07:20:13'),
(96, 2, 'UPDATE_MAINTENANCE_STATUS', 'Mengubah status jadwal perawatan Ganti Ban menjadi Dalam Proses', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/139.0.0.0 Safari/537.36', NULL, '2025-08-25 07:30:47'),
(97, 2, 'ADD_BBM_LOG', 'Menambah log BBM untuk kendaraan ID 18: 123 liter', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/139.0.0.0 Safari/537.36', NULL, '2025-08-25 07:49:59'),
(98, 2, 'ADD_BBM_LOG', 'Menambah log BBM untuk kendaraan ID 18: 12 liter', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/139.0.0.0 Safari/537.36', NULL, '2025-08-25 08:14:39'),
(99, 2, 'ADD_BBM_LOG', 'Menambah log BBM untuk kendaraan ID 18: 122 liter', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/139.0.0.0 Safari/537.36', NULL, '2025-08-25 08:19:23'),
(100, 2, 'UPDATE_MAINTENANCE_STATUS', 'Mengubah status jadwal perawatan Tune Up menjadi Terjadwal', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/139.0.0.0 Safari/537.36', NULL, '2025-08-26 00:06:02'),
(101, 2, 'LOGOUT', 'User keluar dari sistem', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/139.0.0.0 Safari/537.36', NULL, '2025-08-26 00:06:23'),
(102, 2, 'LOGIN', 'User berhasil login ke sistem', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/139.0.0.0 Safari/537.36', NULL, '2025-08-26 00:06:32'),
(103, 2, 'LOGOUT', 'User keluar dari sistem', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/139.0.0.0 Safari/537.36', NULL, '2025-08-26 00:36:35'),
(104, 2, 'LOGIN', 'User berhasil login ke sistem', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/139.0.0.0 Safari/537.36', NULL, '2025-08-26 00:36:42'),
(105, 2, 'LOGOUT', 'User keluar dari sistem', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/139.0.0.0 Safari/537.36', NULL, '2025-08-26 00:38:15'),
(106, 1, 'LOGIN', 'User berhasil login ke sistem', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/139.0.0.0 Safari/537.36', NULL, '2025-08-26 00:38:21'),
(107, 1, 'LOGOUT', 'User keluar dari sistem', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/139.0.0.0 Safari/537.36', NULL, '2025-08-26 00:41:28'),
(108, 2, 'LOGIN', 'User berhasil login ke sistem', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/139.0.0.0 Safari/537.36', NULL, '2025-08-26 00:41:36'),
(109, 2, 'LOGOUT', 'User keluar dari sistem', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/139.0.0.0 Safari/537.36', NULL, '2025-08-26 00:43:30'),
(110, 3, 'LOGIN', 'User berhasil login ke sistem', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/139.0.0.0 Safari/537.36', NULL, '2025-08-26 00:43:40'),
(111, 2, 'LOGIN', 'User berhasil login ke sistem', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/139.0.0.0 Safari/537.36 Edg/139.0.0.0', NULL, '2025-08-26 00:47:38'),
(112, 3, 'LOGOUT', 'User keluar dari sistem', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/139.0.0.0 Safari/537.36', NULL, '2025-08-26 00:57:12'),
(113, 1, 'LOGIN', 'User berhasil login ke sistem', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/139.0.0.0 Safari/537.36', NULL, '2025-08-26 00:57:22'),
(114, 3, 'LOGIN', 'User berhasil login ke sistem', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/139.0.0.0 Safari/537.36 Edg/139.0.0.0', NULL, '2025-08-26 08:53:28'),
(115, 1, 'UPDATE_MAINTENANCE_STATUS', 'Mengubah status jadwal perawatan Servis AC menjadi Dalam Proses', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/139.0.0.0 Safari/537.36', NULL, '2025-08-27 00:58:38'),
(116, 1, 'COMPLETE_MAINTENANCE', 'Menyelesaikan jadwal perawatan Servis AC untuk kendaraan B 9101 EF', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/139.0.0.0 Safari/537.36', NULL, '2025-08-27 01:07:44'),
(117, 1, 'UPDATE_MAINTENANCE_STATUS', 'Mengubah status jadwal perawatan Servis AC menjadi Dalam Proses', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/139.0.0.0 Safari/537.36', NULL, '2025-08-27 01:09:37'),
(118, 1, 'COMPLETE_MAINTENANCE', 'Menyelesaikan jadwal perawatan Servis AC untuk kendaraan B 1234 AB', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/139.0.0.0 Safari/537.36', NULL, '2025-08-27 01:09:43'),
(119, 1, 'UPDATE_MAINTENANCE_STATUS', 'Mengubah status jadwal perawatan Servis AC menjadi Dalam Proses', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/139.0.0.0 Safari/537.36', NULL, '2025-08-27 01:11:07'),
(120, 1, 'COMPLETE_MAINTENANCE', 'Menyelesaikan jadwal perawatan Servis AC untuk kendaraan B 1234 AB', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/139.0.0.0 Safari/537.36', NULL, '2025-08-27 01:11:11'),
(121, 1, 'COMPLETE_MAINTENANCE', 'Menyelesaikan jadwal perawatan Servis AC untuk kendaraan B 1234 AB', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/139.0.0.0 Safari/537.36', NULL, '2025-08-27 01:36:17'),
(122, 2, 'LOGIN', 'User berhasil login ke sistem', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/139.0.0.0 Safari/537.36', NULL, '2025-08-27 06:31:00'),
(123, 2, 'LOGOUT', 'User keluar dari sistem', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/139.0.0.0 Safari/537.36', NULL, '2025-08-27 07:53:05'),
(124, 3, 'LOGIN', 'User berhasil login ke sistem', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/139.0.0.0 Safari/537.36', NULL, '2025-08-27 07:54:43'),
(125, 3, 'LOGIN', 'User berhasil login ke sistem', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/139.0.0.0 Safari/537.36 Edg/139.0.0.0', NULL, '2025-08-27 23:53:16'),
(126, 3, 'LOGOUT', 'User keluar dari sistem', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/139.0.0.0 Safari/537.36 Edg/139.0.0.0', NULL, '2025-08-28 01:55:27'),
(127, 1, 'LOGIN', 'User berhasil login ke sistem', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/139.0.0.0 Safari/537.36 Edg/139.0.0.0', NULL, '2025-08-28 01:55:33'),
(128, 3, 'LOGOUT', 'User keluar dari sistem', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/139.0.0.0 Safari/537.36', NULL, '2025-08-28 04:05:07'),
(129, 1, 'LOGIN', 'User berhasil login ke sistem', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/139.0.0.0 Safari/537.36', NULL, '2025-08-28 04:05:20'),
(130, 1, 'UPDATE_MAINTENANCE_STATUS', 'Mengubah status jadwal perawatan xz menjadi Dalam Proses', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/139.0.0.0 Safari/537.36', NULL, '2025-08-28 06:26:19'),
(131, 1, 'UPDATE_MAINTENANCE_STATUS', 'Mengubah status jadwal perawatan Servis AC menjadi Dalam Proses', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/139.0.0.0 Safari/537.36', NULL, '2025-08-28 06:27:22'),
(132, 1, 'COMPLETE_MAINTENANCE', 'Menyelesaikan jadwal perawatan Servis AC untuk kendaraan TEST123', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/139.0.0.0 Safari/537.36', NULL, '2025-08-28 07:07:00'),
(133, 1, 'COMPLETE_MAINTENANCE', 'Menyelesaikan jadwal perawatan xz untuk kendaraan TEST123', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/139.0.0.0 Safari/537.36', NULL, '2025-08-28 07:25:04'),
(134, 3, 'LOGIN', 'User berhasil login ke sistem', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/139.0.0.0 Safari/537.36 Edg/139.0.0.0', NULL, '2025-08-28 08:29:37'),
(135, 3, 'ADD_BBM_LOG', 'Menambah log BBM untuk kendaraan ID 19: 22 liter', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/139.0.0.0 Safari/537.36 Edg/139.0.0.0', NULL, '2025-08-29 05:33:01'),
(136, 3, 'ADD_BBM_LOG', 'Menambah log BBM untuk kendaraan ID 19: 21 liter', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/139.0.0.0 Safari/537.36 Edg/139.0.0.0', NULL, '2025-08-29 05:49:26'),
(137, 3, 'ADD_BBM_LOG', 'Menambah log BBM untuk kendaraan ID 19: 12 liter', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/139.0.0.0 Safari/537.36 Edg/139.0.0.0', NULL, '2025-08-29 05:50:48'),
(138, 3, 'ADD_BBM_LOG', 'Menambah log BBM untuk kendaraan ID 19: 21 liter', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/139.0.0.0 Safari/537.36 Edg/139.0.0.0', NULL, '2025-08-29 06:57:54'),
(139, 3, 'ADD_BBM_LOG', 'Menambah log BBM untuk kendaraan ID 19: 21 liter', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/139.0.0.0 Safari/537.36 Edg/139.0.0.0', NULL, '2025-08-29 07:03:01'),
(140, 1, 'COMPLETE_MAINTENANCE', 'Menyelesaikan jadwal perawatan Servis AC untuk kendaraan B 1234 AB', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/139.0.0.0 Safari/537.36', NULL, '2025-08-29 07:45:58'),
(141, 2, 'LOGIN', 'User berhasil login ke sistem', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/139.0.0.0 Safari/537.36', NULL, '2025-08-29 07:48:19'),
(142, 1, 'COMPLETE_MAINTENANCE', 'Menyelesaikan jadwal perawatan asd untuk kendaraan TEST123', NULL, NULL, NULL, '2025-08-29 07:59:03'),
(143, 2, 'LOGIN', 'User berhasil login ke sistem', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/139.0.0.0 Safari/537.36', NULL, '2025-08-29 08:56:05'),
(144, 2, 'COMPLETE_MAINTENANCE', 'Menyelesaikan jadwal perawatan asd untuk kendaraan TEST123', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/139.0.0.0 Safari/537.36', NULL, '2025-08-29 09:12:46'),
(145, 2, 'COMPLETE_MAINTENANCE', 'Menyelesaikan jadwal perawatan qw untuk kendaraan TEST123', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/139.0.0.0 Safari/537.36', NULL, '2025-08-29 09:14:10'),
(146, 2, 'COMPLETE_MAINTENANCE', 'Menyelesaikan jadwal perawatan ddd untuk kendaraan TEST123', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/139.0.0.0 Safari/537.36', NULL, '2025-08-29 09:53:59'),
(147, 2, 'COMPLETE_MAINTENANCE', 'Menyelesaikan jadwal perawatan sss untuk kendaraan TEST123', NULL, NULL, NULL, '2025-08-29 09:54:51'),
(148, 2, 'UPDATE_VEHICLE', 'Mengupdate kendaraan: TEST123 - Test Brand', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/139.0.0.0 Safari/537.36', NULL, '2025-08-29 11:56:09'),
(149, 3, 'LOGIN', 'User berhasil login ke sistem', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/139.0.0.0 Safari/537.36 Edg/139.0.0.0', NULL, '2025-08-30 09:15:13'),
(150, 3, 'LOGOUT', 'User keluar dari sistem', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/139.0.0.0 Safari/537.36 Edg/139.0.0.0', NULL, '2025-08-30 09:30:10'),
(151, 3, 'LOGIN', 'User berhasil login ke sistem', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/139.0.0.0 Safari/537.36 Edg/139.0.0.0', NULL, '2025-08-30 09:30:16'),
(152, 3, 'LOGOUT', 'User keluar dari sistem', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/139.0.0.0 Safari/537.36 Edg/139.0.0.0', NULL, '2025-08-30 09:43:45'),
(153, 4, 'LOGIN', 'User berhasil login ke sistem', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/139.0.0.0 Safari/537.36 Edg/139.0.0.0', NULL, '2025-08-30 09:43:52'),
(154, 4, 'LOGOUT', 'User keluar dari sistem', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/139.0.0.0 Safari/537.36 Edg/139.0.0.0', NULL, '2025-08-30 09:44:22'),
(155, 5, 'LOGIN', 'User berhasil login ke sistem', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/139.0.0.0 Safari/537.36 Edg/139.0.0.0', NULL, '2025-08-30 09:44:53'),
(156, 5, 'LOGOUT', 'User keluar dari sistem', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/139.0.0.0 Safari/537.36 Edg/139.0.0.0', NULL, '2025-08-30 09:46:28'),
(157, 4, 'LOGIN', 'User berhasil login ke sistem', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/139.0.0.0 Safari/537.36 Edg/139.0.0.0', NULL, '2025-08-30 09:47:16'),
(159, 1, 'CHANGE_USER_STATUS', 'Admin mengubah status user menjadi: Nonaktif', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/139.0.0.0 Safari/537.36', NULL, '2025-08-30 13:59:40'),
(160, 1, 'CHANGE_USER_STATUS', 'Admin mengubah status user menjadi: Aktif', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/139.0.0.0 Safari/537.36', NULL, '2025-08-30 13:59:44'),
(161, 1, 'CHANGE_USER_STATUS', 'Admin mengubah status user menjadi: Nonaktif', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/139.0.0.0 Safari/537.36', NULL, '2025-08-30 13:59:47'),
(162, 1, 'CHANGE_USER_STATUS', 'Admin mengubah status user menjadi: Aktif', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/139.0.0.0 Safari/537.36', NULL, '2025-08-30 14:00:02'),
(163, 1, 'CHANGE_USER_STATUS', 'Admin mengubah status user menjadi: Nonaktif', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/139.0.0.0 Safari/537.36', NULL, '2025-08-30 14:00:22'),
(164, 1, 'CHANGE_USER_STATUS', 'Admin mengubah status user menjadi: Aktif', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/139.0.0.0 Safari/537.36', NULL, '2025-08-30 14:00:27'),
(165, 1, 'CHANGE_USER_STATUS', 'Admin mengubah status user menjadi: Aktif', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/139.0.0.0 Safari/537.36', NULL, '2025-08-30 14:00:40'),
(166, 1, 'CHANGE_USER_STATUS', 'Admin mengubah status user menjadi: Aktif', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/139.0.0.0 Safari/537.36', NULL, '2025-08-30 14:03:41'),
(167, 1, 'CHANGE_USER_STATUS', 'Admin mengubah status user menjadi: Nonaktif', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/139.0.0.0 Safari/537.36', NULL, '2025-08-30 14:03:48'),
(168, 1, 'CHANGE_USER_STATUS', 'Admin mengubah status user menjadi: Aktif', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/139.0.0.0 Safari/537.36', NULL, '2025-08-30 14:03:51'),
(169, 1, 'CHANGE_USER_STATUS', 'Admin mengubah status user menjadi: Aktif', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/139.0.0.0 Safari/537.36', NULL, '2025-08-30 14:06:33'),
(170, 1, 'CHANGE_USER_STATUS', 'Admin mengubah status user menjadi: Nonaktif', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/139.0.0.0 Safari/537.36', NULL, '2025-08-30 14:06:37'),
(171, 1, 'CHANGE_USER_STATUS', 'Admin mengubah status user menjadi: Aktif', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/139.0.0.0 Safari/537.36', NULL, '2025-08-30 14:06:37'),
(172, 1, 'CHANGE_USER_STATUS', 'Admin mengubah status user menjadi: Nonaktif', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/139.0.0.0 Safari/537.36', NULL, '2025-08-30 14:06:43'),
(173, 1, 'CHANGE_USER_STATUS', 'Admin mengubah status user menjadi: Aktif', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/139.0.0.0 Safari/537.36', NULL, '2025-08-30 14:06:43'),
(174, 1, 'CHANGE_USER_STATUS', 'Admin mengubah status user menjadi: Aktif', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/139.0.0.0 Safari/537.36', NULL, '2025-08-30 14:06:47'),
(175, 1, 'CHANGE_USER_STATUS', 'Admin mengubah status user menjadi: Nonaktif', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/139.0.0.0 Safari/537.36', NULL, '2025-08-30 14:06:50'),
(176, 1, 'CHANGE_USER_STATUS', 'Admin mengubah status user menjadi: Aktif', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/139.0.0.0 Safari/537.36', NULL, '2025-08-30 14:06:50'),
(177, 1, 'CHANGE_USER_STATUS', 'Admin mengubah status user menjadi: Nonaktif', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/139.0.0.0 Safari/537.36', NULL, '2025-08-30 14:13:48'),
(178, 1, 'CHANGE_USER_STATUS', 'Admin mengubah status user menjadi: Aktif', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/139.0.0.0 Safari/537.36', NULL, '2025-08-30 14:13:58'),
(179, 5, 'LOGIN', 'User berhasil login ke sistem', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/139.0.0.0 Safari/537.36 Edg/139.0.0.0', NULL, '2025-09-01 00:17:36'),
(180, 2, 'LOGIN', 'User berhasil login ke sistem', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/139.0.0.0 Safari/537.36', NULL, '2025-09-01 02:34:06'),
(181, 1, 'CREATE_USER', 'Admin membuat user: pns', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/139.0.0.0 Safari/537.36', NULL, '2025-09-01 03:42:47'),
(182, 10, 'LOGIN', 'User berhasil login ke sistem', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/139.0.0.0 Safari/537.36', NULL, '2025-09-01 03:43:01'),
(183, 1, 'RESET_PASSWORD', 'Admin mereset password user: pns', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/139.0.0.0 Safari/537.36', NULL, '2025-09-01 03:56:21'),
(184, 10, 'LOGOUT', 'User keluar dari sistem', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/139.0.0.0 Safari/537.36', NULL, '2025-09-01 03:56:33'),
(185, 10, 'LOGIN', 'User berhasil login ke sistem', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/139.0.0.0 Safari/537.36', NULL, '2025-09-01 03:56:41'),
(186, 1, 'CREATE_USER', 'Admin membuat user: tni', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/139.0.0.0 Safari/537.36', NULL, '2025-09-01 03:58:15'),
(187, 5, 'LOGOUT', 'User keluar dari sistem', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/139.0.0.0 Safari/537.36 Edg/139.0.0.0', NULL, '2025-09-01 04:20:19'),
(188, 2, 'LOGIN', 'User berhasil login ke sistem', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/139.0.0.0 Safari/537.36 Edg/139.0.0.0', NULL, '2025-09-01 04:20:27'),
(189, 1, 'COMPLETE_MAINTENANCE', 'Menyelesaikan jadwal perawatan Tune Up untuk kendaraan B1234CD', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/139.0.0.0 Safari/537.36', NULL, '2025-09-01 05:09:35'),
(190, 2, 'IMPORT_VEHICLE', 'Import kendaraan: S_1234_CD - Toyota', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/139.0.0.0 Safari/537.36 Edg/139.0.0.0', NULL, '2025-09-01 05:20:53'),
(191, 2, 'IMPORT_VEHICLE', 'Import kendaraan: F5888EF - Honda', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/139.0.0.0 Safari/537.36 Edg/139.0.0.0', NULL, '2025-09-01 05:20:53'),
(192, 2, 'IMPORT_VEHICLE', 'Import kendaraan: C9012GH - Mitsubishi', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/139.0.0.0 Safari/537.36 Edg/139.0.0.0', NULL, '2025-09-01 05:20:53'),
(193, 2, 'UPDATE_VEHICLE', 'Mengupdate kendaraan: S 1234 CD - Toyota', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/139.0.0.0 Safari/537.36 Edg/139.0.0.0', NULL, '2025-09-01 05:28:29'),
(194, 2, 'UPDATE_VEHICLE', 'Mengupdate kendaraan: F5888EF - Honda', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/139.0.0.0 Safari/537.36 Edg/139.0.0.0', NULL, '2025-09-01 05:28:48'),
(195, 2, 'UPDATE_VEHICLE', 'Mengupdate kendaraan: S 1234 CD - Toyota', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/139.0.0.0 Safari/537.36 Edg/139.0.0.0', NULL, '2025-09-01 05:28:58'),
(196, 2, 'UPDATE_VEHICLE', 'Mengupdate kendaraan: C9012GH - Mitsubishi', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/139.0.0.0 Safari/537.36 Edg/139.0.0.0', NULL, '2025-09-01 05:29:05'),
(197, 2, 'UPDATE_VEHICLE', 'Mengupdate kendaraan: C9012GH - Mitsubishi', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/139.0.0.0 Safari/537.36 Edg/139.0.0.0', NULL, '2025-09-01 05:29:33'),
(198, 10, 'LOGOUT', 'User keluar dari sistem', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/139.0.0.0 Safari/537.36', NULL, '2025-09-01 06:22:14'),
(199, 10, 'LOGIN', 'User berhasil login ke sistem', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/139.0.0.0 Safari/537.36', NULL, '2025-09-01 06:22:20'),
(200, 2, 'UPDATE_VEHICLE', 'Mengupdate kendaraan: C9012GH - Mitsubishi', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/139.0.0.0 Safari/537.36 Edg/139.0.0.0', NULL, '2025-09-01 06:24:51'),
(201, 2, 'UPDATE_VEHICLE', 'Mengupdate kendaraan: C9012GH - Mitsubishi', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/139.0.0.0 Safari/537.36 Edg/139.0.0.0', NULL, '2025-09-01 06:25:03'),
(202, 2, 'UPDATE_VEHICLE', 'Mengupdate kendaraan: F5888EF - Honda', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/139.0.0.0 Safari/537.36 Edg/139.0.0.0', NULL, '2025-09-01 06:25:18'),
(203, 2, 'UPDATE_VEHICLE', 'Mengupdate kendaraan: S 1234 CD - Toyota', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/139.0.0.0 Safari/537.36 Edg/139.0.0.0', NULL, '2025-09-01 06:25:29'),
(204, 1, 'EDIT_USER', 'Admin mengubah user id: 6', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/139.0.0.0 Safari/537.36', NULL, '2025-09-01 06:34:17'),
(205, 1, 'UPDATE_VEHICLE', 'Mengupdate kendaraan: S 1234 CD - Toyota', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/139.0.0.0 Safari/537.36', NULL, '2025-09-01 07:01:59'),
(206, 2, 'LOGIN', 'User berhasil login ke sistem', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/139.0.0.0 Safari/537.36 Edg/139.0.0.0', NULL, '2025-09-02 03:02:33'),
(207, 2, 'UPDATE_VEHICLE', 'Mengupdate kendaraan: S 1234 CD - Toyota', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/139.0.0.0 Safari/537.36 Edg/139.0.0.0', NULL, '2025-09-02 03:02:59'),
(208, 2, 'CREATE_VEHICLE', 'Menambahkan kendaraan baru: S 222 CD - Lexus', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/139.0.0.0 Safari/537.36 Edg/139.0.0.0', NULL, '2025-09-02 03:05:45'),
(209, 2, 'UPDATE_VEHICLE', 'Mengupdate kendaraan: F5888EF - Honda', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/139.0.0.0 Safari/537.36 Edg/139.0.0.0', NULL, '2025-09-02 03:06:04'),
(210, 2, 'UPDATE_VEHICLE', 'Mengupdate kendaraan: C9012GH - Mitsubishi', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/139.0.0.0 Safari/537.36 Edg/139.0.0.0', NULL, '2025-09-02 03:06:16'),
(211, 1, 'COMPLETE_MAINTENANCE', 'Menyelesaikan jadwal perawatan Servis AC untuk kendaraan TEST123', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/139.0.0.0 Safari/537.36', NULL, '2025-09-02 03:15:42'),
(212, 1, 'COMPLETE_MAINTENANCE', 'Menyelesaikan jadwal perawatan Service Rutin untuk kendaraan B 1234 AB', NULL, NULL, NULL, '2025-09-02 03:18:27'),
(213, 1, 'COMPLETE_MAINTENANCE', 'Menyelesaikan jadwal perawatan Ganti Oli untuk kendaraan B 5678 CD', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/139.0.0.0 Safari/537.36', NULL, '2025-09-02 04:06:25'),
(214, 1, 'COMPLETE_MAINTENANCE', 'Menyelesaikan jadwal perawatan Ganti Ban untuk kendaraan TEST123', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/139.0.0.0 Safari/537.36', NULL, '2025-09-02 04:52:58'),
(215, 1, 'COMPLETE_MAINTENANCE', 'Menyelesaikan jadwal perawatan aa untuk kendaraan TEST123', NULL, NULL, NULL, '2025-09-02 04:55:06'),
(216, 1, 'COMPLETE_MAINTENANCE', 'Menyelesaikan jadwal perawatan Servis AC untuk kendaraan TEST123', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/139.0.0.0 Safari/537.36', NULL, '2025-09-02 05:30:51'),
(217, 1, 'COMPLETE_MAINTENANCE', 'Menyelesaikan jadwal perawatan sss untuk kendaraan S 222 CD', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/139.0.0.0 Safari/537.36', NULL, '2025-09-02 05:30:56'),
(218, 1, 'COMPLETE_MAINTENANCE', 'Menyelesaikan jadwal perawatan a untuk kendaraan C9012GH', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/139.0.0.0 Safari/537.36', NULL, '2025-09-02 05:33:03'),
(219, 1, 'COMPLETE_MAINTENANCE', 'Menyelesaikan jadwal perawatan a untuk kendaraan C9012GH', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/139.0.0.0 Safari/537.36', NULL, '2025-09-02 05:33:51'),
(220, 1, 'COMPLETE_MAINTENANCE', 'Menyelesaikan jadwal perawatan s untuk kendaraan C9012GH', NULL, NULL, NULL, '2025-09-02 05:37:51'),
(221, 1, 'COMPLETE_MAINTENANCE', 'Menandai jadwal perawatan selesai: s untuk kendaraan C9012GH', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/139.0.0.0 Safari/537.36', NULL, '2025-09-02 05:37:51'),
(223, 1, 'TEST_CLI', 'Testing log_activity mapping', 'UNKNOWN', 'UNKNOWN', NULL, '2025-09-02 05:52:49'),
(224, 10, 'LOGOUT', 'User keluar dari sistem', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/139.0.0.0 Safari/537.36', NULL, '2025-09-02 05:53:11'),
(225, 11, 'LOGIN', 'User berhasil login ke sistem', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/139.0.0.0 Safari/537.36', NULL, '2025-09-02 05:53:28'),
(226, 1, 'COMPLETE_MAINTENANCE', 'Menyelesaikan jadwal perawatan as untuk kendaraan S 1234 CD', NULL, NULL, NULL, '2025-09-02 06:21:54'),
(227, 11, 'LOGOUT', 'User keluar dari sistem', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/139.0.0.0 Safari/537.36', NULL, '2025-09-02 06:44:36'),
(228, 10, 'LOGIN', 'User berhasil login ke sistem', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/139.0.0.0 Safari/537.36', NULL, '2025-09-02 06:44:44'),
(229, 10, 'LOGOUT', 'User keluar dari sistem', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/139.0.0.0 Safari/537.36', NULL, '2025-09-02 06:45:13'),
(230, 11, 'LOGIN', 'User berhasil login ke sistem', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/139.0.0.0 Safari/537.36', NULL, '2025-09-02 06:45:18'),
(231, 11, 'LOGOUT', 'User keluar dari sistem', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/139.0.0.0 Safari/537.36', NULL, '2025-09-02 06:45:21'),
(232, 2, 'LOGIN', 'User berhasil login ke sistem', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/139.0.0.0 Safari/537.36', NULL, '2025-09-02 06:45:28'),
(233, 2, 'LOGOUT', 'User keluar dari sistem', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/139.0.0.0 Safari/537.36', NULL, '2025-09-02 06:45:33'),
(234, 5, 'LOGIN', 'User berhasil login ke sistem', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/139.0.0.0 Safari/537.36', NULL, '2025-09-02 06:45:41'),
(235, 11, 'LOGIN', 'User berhasil login ke sistem', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/139.0.0.0 Safari/537.36', NULL, '2025-09-02 06:46:48'),
(236, 5, 'LOGOUT', 'User keluar dari sistem', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/139.0.0.0 Safari/537.36', NULL, '2025-09-02 07:03:03'),
(237, 5, 'LOGIN', 'User berhasil login ke sistem', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/139.0.0.0 Safari/537.36', NULL, '2025-09-02 07:03:11'),
(238, 11, 'ADD_BBM_LOG', 'Menambah log BBM untuk kendaraan ID 27: 11 liter', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/139.0.0.0 Safari/537.36', NULL, '2025-09-02 07:53:36'),
(239, 11, 'ADD_BBM_LOG', 'Menambah log BBM untuk kendaraan ID 27: 32 liter', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/139.0.0.0 Safari/537.36', NULL, '2025-09-02 07:56:13'),
(240, 11, 'ADD_BBM_LOG', 'Menambah log BBM untuk kendaraan ID 27: 10 liter', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/139.0.0.0 Safari/537.36', NULL, '2025-09-02 08:05:37');
INSERT INTO `log_aktivitas` (`id`, `user_id`, `activity_type`, `description`, `ip_address`, `user_agent`, `session_id`, `created_at`) VALUES
(241, 5, 'LOGOUT', 'User keluar dari sistem', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/139.0.0.0 Safari/537.36', NULL, '2025-09-03 00:24:49'),
(242, 5, 'LOGIN', 'User berhasil login ke sistem', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/139.0.0.0 Safari/537.36', NULL, '2025-09-03 00:24:59'),
(243, 1, 'UPDATE_MAINTENANCE_STATUS', 'Mengubah status jadwal perawatan ss menjadi Dalam Proses', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/139.0.0.0 Safari/537.36', NULL, '2025-09-03 00:42:43'),
(244, 2, 'LOGOUT', 'User keluar dari sistem', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/139.0.0.0 Safari/537.36 Edg/139.0.0.0', NULL, '2025-09-03 01:30:33'),
(245, 4, 'LOGIN', 'User berhasil login ke sistem', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/139.0.0.0 Safari/537.36 Edg/139.0.0.0', NULL, '2025-09-03 01:30:41'),
(246, 4, 'LOGOUT', 'User keluar dari sistem', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/139.0.0.0 Safari/537.36 Edg/139.0.0.0', NULL, '2025-09-03 01:30:45'),
(247, 10, 'LOGIN', 'User berhasil login ke sistem', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/139.0.0.0 Safari/537.36 Edg/139.0.0.0', NULL, '2025-09-03 01:31:11'),
(248, 11, 'LOGOUT', 'User keluar dari sistem', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/139.0.0.0 Safari/537.36', NULL, '2025-09-03 01:33:09'),
(249, 3, 'LOGIN', 'User berhasil login ke sistem', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/139.0.0.0 Safari/537.36', NULL, '2025-09-03 01:33:16'),
(250, 5, 'LOGOUT', 'User keluar dari sistem', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/139.0.0.0 Safari/537.36', NULL, '2025-09-03 01:42:45'),
(251, 2, 'LOGIN', 'User berhasil login ke sistem', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/139.0.0.0 Safari/537.36', NULL, '2025-09-03 01:42:54'),
(252, 1, 'UPDATE_MAINTENANCE_STATUS', 'Mengubah status jadwal perawatan ss menjadi Terjadwal', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/139.0.0.0 Safari/537.36', NULL, '2025-09-03 02:10:28'),
(253, 1, 'UPDATE_MAINTENANCE_STATUS', 'Mengubah status jadwal perawatan ss menjadi Dalam Proses', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/139.0.0.0 Safari/537.36', NULL, '2025-09-03 02:10:32'),
(254, 1, 'UPDATE_MAINTENANCE_STATUS', 'Mengubah status jadwal perawatan ss menjadi Terjadwal', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/139.0.0.0 Safari/537.36', NULL, '2025-09-03 02:12:37'),
(255, 1, 'UPDATE_MAINTENANCE_STATUS', 'Mengubah status jadwal perawatan ss menjadi Dalam Proses', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/139.0.0.0 Safari/537.36', NULL, '2025-09-03 02:12:42'),
(256, 1, 'COMPLETE_MAINTENANCE', 'Menyelesaikan jadwal perawatan ss untuk kendaraan S 1234 CD', NULL, NULL, NULL, '2025-09-03 02:20:37'),
(257, 1, 'UPDATE_VEHICLE_STATUS_BY_MAINTENANCE', 'Set kendaraan ID 24 menjadi Operasional/Tersedia setelah perawatan selesai', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/139.0.0.0 Safari/537.36', NULL, '2025-09-03 02:20:37'),
(258, 1, 'COMPLETE_MAINTENANCE', 'Menandai jadwal perawatan selesai: ss untuk kendaraan S 1234 CD', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/139.0.0.0 Safari/537.36', NULL, '2025-09-03 02:20:37'),
(259, 1, 'COMPLETE_MAINTENANCE', 'Menyelesaikan jadwal perawatan as untuk kendaraan C9012GH', NULL, NULL, NULL, '2025-09-03 02:21:37'),
(260, 1, 'UPDATE_VEHICLE_STATUS_BY_MAINTENANCE', 'Set kendaraan ID 26 menjadi Operasional/Tersedia setelah perawatan selesai', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/139.0.0.0 Safari/537.36', NULL, '2025-09-03 02:21:37'),
(261, 1, 'COMPLETE_MAINTENANCE', 'Menandai jadwal perawatan selesai: as untuk kendaraan C9012GH', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/139.0.0.0 Safari/537.36', NULL, '2025-09-03 02:21:37'),
(262, 1, 'UPDATE_VEHICLE_STATUS_BY_MAINTENANCE', 'Set kendaraan ID 26 menjadi Perbaikan/Maintenance saat perawatan dimulai', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/139.0.0.0 Safari/537.36', NULL, '2025-09-03 02:41:08'),
(263, 1, 'UPDATE_MAINTENANCE_STATUS', 'Mengubah status jadwal perawatan s menjadi Dalam Proses', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/139.0.0.0 Safari/537.36', NULL, '2025-09-03 02:41:08'),
(264, 2, 'LOGOUT', 'User keluar dari sistem', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/139.0.0.0 Safari/537.36', NULL, '2025-09-03 02:41:54'),
(265, 1, 'COMPLETE_MAINTENANCE', 'Menyelesaikan jadwal perawatan s untuk kendaraan C9012GH', NULL, NULL, NULL, '2025-09-03 02:46:34'),
(266, 1, 'UPDATE_VEHICLE_STATUS_BY_MAINTENANCE', 'Set kendaraan ID 26 menjadi Operasional/Tersedia setelah perawatan selesai', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/139.0.0.0 Safari/537.36', NULL, '2025-09-03 02:46:34'),
(267, 1, 'COMPLETE_MAINTENANCE', 'Menandai jadwal perawatan selesai: s untuk kendaraan C9012GH', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/139.0.0.0 Safari/537.36', NULL, '2025-09-03 02:46:34'),
(268, 1, 'UPDATE_VEHICLE_STATUS_BY_MAINTENANCE', 'Set kendaraan ID 3 menjadi Perbaikan/Maintenance saat perawatan dimulai', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/139.0.0.0 Safari/537.36', NULL, '2025-09-03 02:47:35'),
(269, 1, 'UPDATE_MAINTENANCE_STATUS', 'Mengubah status jadwal perawatan ddd menjadi Dalam Proses', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/139.0.0.0 Safari/537.36', NULL, '2025-09-03 02:47:35'),
(270, 1, 'COMPLETE_MAINTENANCE', 'Menyelesaikan jadwal perawatan ddd untuk kendaraan B 9101 EF', NULL, NULL, NULL, '2025-09-03 03:43:39'),
(271, 1, 'UPDATE_VEHICLE_STATUS_BY_MAINTENANCE', 'Set kendaraan ID 3 menjadi Operasional/Tersedia setelah perawatan selesai', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/139.0.0.0 Safari/537.36', NULL, '2025-09-03 03:43:39'),
(272, 1, 'COMPLETE_MAINTENANCE', 'Menandai jadwal perawatan selesai: ddd untuk kendaraan B 9101 EF', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/139.0.0.0 Safari/537.36', NULL, '2025-09-03 03:43:39'),
(273, 3, 'LOGIN', 'User berhasil login ke sistem', '::1', 'Mozilla/5.0 (Linux; Android 6.0; Nexus 5 Build/MRA58N) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/139.0.0.0 Mobile Safari/537.36', NULL, '2025-09-03 05:01:08'),
(274, 3, 'LOGIN', 'User berhasil login ke sistem', '192.168.11.251', 'Mozilla/5.0 (Linux; Android 13; K) AppleWebKit/537.36 (KHTML, like Gecko) Stargon/6.1.9 Chrome/139.0.7258.158 Mobile Safari/537.36', NULL, '2025-09-03 07:59:13'),
(275, 3, 'LOGOUT', 'User keluar dari sistem', '192.168.11.251', 'Mozilla/5.0 (Linux; Android 13; K) AppleWebKit/537.36 (KHTML, like Gecko) Stargon/6.1.9 Chrome/139.0.7258.158 Mobile Safari/537.36', NULL, '2025-09-03 08:12:36'),
(276, 3, 'LOGIN', 'User berhasil login ke sistem', '192.168.11.251', 'Mozilla/5.0 (Linux; Android 13; K) AppleWebKit/537.36 (KHTML, like Gecko) Stargon/6.1.9 Chrome/139.0.7258.158 Mobile Safari/537.36', NULL, '2025-09-03 08:12:44'),
(277, 1, 'LOGOUT', 'User keluar dari sistem', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/139.0.0.0 Safari/537.36', NULL, '2025-09-03 12:37:59'),
(278, 1, 'LOGIN', 'User berhasil login ke sistem', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/139.0.0.0 Safari/537.36', NULL, '2025-09-03 12:39:58'),
(279, 1, 'LOGOUT', 'User keluar dari sistem', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/139.0.0.0 Safari/537.36', NULL, '2025-09-03 12:42:45'),
(280, 3, 'LOGIN', 'User berhasil login ke sistem', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/139.0.0.0 Safari/537.36', NULL, '2025-09-03 12:42:52'),
(281, 3, 'LOGOUT', 'User keluar dari sistem', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/139.0.0.0 Safari/537.36', NULL, '2025-09-03 12:49:14'),
(282, 1, 'LOGIN', 'User berhasil login ke sistem', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/139.0.0.0 Safari/537.36', NULL, '2025-09-03 12:58:01'),
(283, 1, 'EDIT_USER', 'Admin mengubah user id: 6', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/139.0.0.0 Safari/537.36', NULL, '2025-09-03 12:58:22'),
(284, 3, 'LOGIN', 'User berhasil login ke sistem', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/139.0.0.0 Safari/537.36', NULL, '2025-09-04 00:34:38'),
(285, 1, 'LOGIN', 'User berhasil login ke sistem', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/139.0.0.0 Safari/537.36', NULL, '2025-09-04 00:34:49'),
(286, 2, 'LOGIN', 'User berhasil login ke sistem', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/139.0.0.0 Safari/537.36', NULL, '2025-09-04 00:35:01'),
(287, 10, 'LOGIN', 'User berhasil login ke sistem', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/139.0.0.0 Safari/537.36 Edg/139.0.0.0', NULL, '2025-09-04 00:35:12'),
(288, 2, 'LOGOUT', 'User keluar dari sistem', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/139.0.0.0 Safari/537.36', NULL, '2025-09-04 00:38:45'),
(289, 11, 'LOGIN', 'User berhasil login ke sistem', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/139.0.0.0 Safari/537.36', NULL, '2025-09-04 00:38:52'),
(290, 11, 'LOGOUT', 'User keluar dari sistem', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/139.0.0.0 Safari/537.36', NULL, '2025-09-04 00:39:33'),
(291, 3, 'LOGIN', 'User berhasil login ke sistem', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/139.0.0.0 Safari/537.36', NULL, '2025-09-04 00:39:41'),
(292, 3, 'LOGOUT', 'User keluar dari sistem', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/139.0.0.0 Safari/537.36', NULL, '2025-09-04 00:42:20'),
(293, 1, 'LOGIN', 'User berhasil login ke sistem', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/139.0.0.0 Safari/537.36', NULL, '2025-09-04 00:42:27'),
(294, 1, 'UPDATE_VEHICLE', 'Mengupdate kendaraan: TEST123 - Test Brand', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/139.0.0.0 Safari/537.36', NULL, '2025-09-04 01:08:07'),
(295, 1, 'UPDATE_VEHICLE', 'Mengupdate kendaraan: TEST123 - Test Brand', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/139.0.0.0 Safari/537.36', NULL, '2025-09-04 01:08:27'),
(296, 1, 'UPDATE_VEHICLE', 'Mengupdate kendaraan: TEST123 - Test Brand', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/139.0.0.0 Safari/537.36', NULL, '2025-09-04 01:09:07'),
(297, 10, 'LOGOUT', 'User keluar dari sistem', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/139.0.0.0 Safari/537.36 Edg/139.0.0.0', NULL, '2025-09-04 01:53:23'),
(298, 1, 'LOGIN', 'User berhasil login ke sistem', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/139.0.0.0 Safari/537.36 Edg/139.0.0.0', NULL, '2025-09-04 01:53:31'),
(299, 1, 'RESET_PASSWORD', 'Admin mereset password user: pns', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/139.0.0.0 Safari/537.36 Edg/139.0.0.0', NULL, '2025-09-04 01:54:07'),
(300, 1, 'CREATE_USER', 'Admin membuat user: asn', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/139.0.0.0 Safari/537.36 Edg/139.0.0.0', NULL, '2025-09-04 01:55:17'),
(301, 1, 'COMPLETE_MAINTENANCE', 'Menyelesaikan jadwal perawatan aa untuk kendaraan TEST123', NULL, NULL, NULL, '2025-09-04 03:05:56'),
(302, 1, 'UPDATE_VEHICLE', 'Mengupdate kendaraan: TEST123 - Test Brand', '::1', 'Mozilla/5.0 (Linux; Android 6.0; Nexus 5 Build/MRA58N) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/139.0.0.0 Mobile Safari/537.36', NULL, '2025-09-04 05:14:22'),
(303, 1, 'LOGOUT', 'User keluar dari sistem', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/139.0.0.0 Safari/537.36', NULL, '2025-09-04 05:19:09'),
(304, 1, 'UPDATE_VEHICLE', 'Mengupdate kendaraan: S 1234 CD - Toyota', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/139.0.0.0 Safari/537.36 Edg/139.0.0.0', NULL, '2025-09-04 05:21:46'),
(305, 1, 'UPDATE_VEHICLE', 'Mengupdate kendaraan: TEST123 - Test Brand', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/139.0.0.0 Safari/537.36 Edg/139.0.0.0', NULL, '2025-09-04 05:22:04'),
(306, 3, 'LOGIN', 'User berhasil login ke sistem', '192.168.11.236', 'Mozilla/5.0 (Linux; Android 10; K) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/121.0.0.0 Mobile Safari/537.36', NULL, '2025-09-04 06:18:43'),
(307, 3, 'LOGIN', 'User berhasil login ke sistem', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/139.0.0.0 Safari/537.36', NULL, '2025-09-04 06:20:35'),
(308, 1, 'EDIT_USER', 'Admin mengubah user id: 2', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/139.0.0.0 Safari/537.36 Edg/139.0.0.0', NULL, '2025-09-04 08:01:20'),
(309, 3, 'LOGOUT', 'User keluar dari sistem', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/139.0.0.0 Safari/537.36', NULL, '2025-09-04 08:30:18'),
(310, 10, 'LOGIN', 'User berhasil login ke sistem', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/139.0.0.0 Safari/537.36', NULL, '2025-09-04 08:30:28'),
(311, 1, 'EDIT_USER', 'Admin mengubah user id: 6', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/139.0.0.0 Safari/537.36 Edg/139.0.0.0', NULL, '2025-09-04 08:31:52'),
(312, 1, 'UPDATE_VEHICLE', 'Mengupdate kendaraan: B 1234 AC - Toyota', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/139.0.0.0 Safari/537.36', NULL, '2025-09-08 01:37:34'),
(313, 3, 'LOGIN', 'User berhasil login ke sistem', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/140.0.0.0 Safari/537.36 Edg/140.0.0.0', NULL, '2025-09-08 03:33:52'),
(314, 1, 'LOGIN', 'User berhasil login ke sistem', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/140.0.0.0 Safari/537.36', NULL, '2025-09-08 04:12:38'),
(315, 1, 'EDIT_USER', 'Admin mengubah user id: 1', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/139.0.0.0 Safari/537.36', NULL, '2025-09-08 06:45:53'),
(316, 3, 'LOGOUT', 'User keluar dari sistem', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/140.0.0.0 Safari/537.36 Edg/140.0.0.0', NULL, '2025-09-08 06:50:19'),
(317, 1, 'LOGOUT', 'User keluar dari sistem', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/140.0.0.0 Safari/537.36', NULL, '2025-09-08 07:27:20'),
(318, 12, 'LOGIN', 'User berhasil login ke sistem', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/140.0.0.0 Safari/537.36', NULL, '2025-09-08 07:27:36'),
(319, 1, 'LOGIN', 'User berhasil login ke sistem', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/140.0.0.0 Safari/537.36 Edg/140.0.0.0', NULL, '2025-09-08 07:29:57'),
(320, 1, 'LOGOUT', 'User keluar dari sistem', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/139.0.0.0 Safari/537.36', NULL, '2025-09-08 07:35:50'),
(321, 3, 'LOGIN', 'User berhasil login ke sistem', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/139.0.0.0 Safari/537.36', NULL, '2025-09-08 07:36:01'),
(322, 1, 'LOGIN', 'User berhasil login ke sistem', '192.168.11.33', 'Mozilla/5.0 (Linux; Android 10; K) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/121.0.0.0 Mobile Safari/537.36', NULL, '2025-09-08 08:54:18'),
(323, 2, 'LOGIN', 'User berhasil login ke sistem', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/140.0.0.0 Safari/537.36 Edg/140.0.0.0', NULL, '2025-09-09 00:54:26'),
(324, 3, 'LOGOUT', 'User keluar dari sistem', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/139.0.0.0 Safari/537.36', NULL, '2025-09-09 01:13:55'),
(325, 1, 'LOGIN', 'User berhasil login ke sistem', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/139.0.0.0 Safari/537.36', NULL, '2025-09-09 01:14:01'),
(326, 2, 'LOGOUT', 'User keluar dari sistem', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/140.0.0.0 Safari/537.36 Edg/140.0.0.0', NULL, '2025-09-09 01:14:48');

-- --------------------------------------------------------

--
-- Struktur dari tabel `log_bahan_bakar`
--

CREATE TABLE `log_bahan_bakar` (
  `id` int(11) NOT NULL,
  `kendaraan_id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `tanggal_isi` date NOT NULL,
  `jam_isi` time DEFAULT NULL,
  `km_saat_isi` int(11) NOT NULL,
  `jenis_bahan_bakar` enum('Pertalite','Pertamax','Pertamax Turbo','Solar','Bio Solar') NOT NULL,
  `jumlah_liter` decimal(8,2) NOT NULL,
  `harga_per_liter` decimal(10,2) NOT NULL,
  `biaya` decimal(15,2) NOT NULL,
  `spbu` varchar(100) DEFAULT NULL,
  `metode_bayar` varchar(20) NOT NULL,
  `alamat_spbu` varchar(255) DEFAULT NULL,
  `nomor_struk` varchar(50) DEFAULT NULL,
  `foto_struk` varchar(255) DEFAULT NULL,
  `foto_sebelum_isi` varchar(255) DEFAULT NULL,
  `foto_sesudah_isi` varchar(255) DEFAULT NULL,
  `foto_odometer` varchar(255) DEFAULT NULL,
  `keterangan` text DEFAULT NULL,
  `catatan` text DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data untuk tabel `log_bahan_bakar`
--

INSERT INTO `log_bahan_bakar` (`id`, `kendaraan_id`, `user_id`, `tanggal_isi`, `jam_isi`, `km_saat_isi`, `jenis_bahan_bakar`, `jumlah_liter`, `harga_per_liter`, `biaya`, `spbu`, `metode_bayar`, `alamat_spbu`, `nomor_struk`, `foto_struk`, `foto_sebelum_isi`, `foto_sesudah_isi`, `foto_odometer`, `keterangan`, `catatan`, `created_at`, `updated_at`) VALUES
(1, 17, 3, '2025-08-21', NULL, 12, 'Pertalite', 1212.00, 12212.00, 14800944.00, '12', 'Tunai', NULL, NULL, NULL, NULL, '0', '0', '0', NULL, '2025-08-21 03:49:54', '2025-08-25 08:02:19'),
(8, 1, 2, '2025-08-20', '14:00:00', 15000, 'Pertalite', 45.00, 10000.00, 450000.00, 'SPBU Pertamina 44.501.15', '', 'Jl. Raya Bogor KM. 21, Kramat Jati, Jakarta Timur', 'TXN20250820140001', NULL, '', '0', '0', '0', 'Pengisian BBM untuk operasional harian', '2025-08-20 07:00:23', '2025-08-20 07:00:23'),
(9, 3, 2, '2025-08-19', '09:30:00', 24800, 'Pertalite', 40.00, 10000.00, 400000.00, 'SPBU Shell 8796', '', 'Jl. MT. Haryono, Cawang, Jakarta Timur', 'SHL20250819093001', NULL, '', '0', '0', '0', 'Pengisian setelah perjalanan dinas', '2025-08-20 07:01:15', '2025-08-20 07:01:15'),
(10, 17, 3, '2025-08-18', '16:45:00', 14500, 'Pertamax', 35.00, 12500.00, 437500.00, 'SPBU Pertamina 44.501.22', '', 'Jl. Gatot Subroto, Kuningan, Jakarta Selatan', 'PTM20250818164501', NULL, '', '0', '0', '0', 'Pengisian BBM premium untuk kendaraan dinas', '2025-08-20 07:02:08', '2025-08-20 07:02:08'),
(11, 18, 2, '2025-08-25', NULL, 3200, 'Pertalite', 123.00, 12000.00, 1476000.00, 'san', 'Tunai', NULL, NULL, NULL, NULL, NULL, NULL, '', NULL, '2025-08-25 07:49:59', '2025-08-25 07:49:59'),
(12, 18, 2, '2025-08-25', NULL, 322, 'Pertalite', 12.00, 12000.00, 144000.00, 'ss', 'Tunai', NULL, NULL, NULL, NULL, NULL, NULL, '', NULL, '2025-08-25 08:14:39', '2025-08-25 08:14:39'),
(13, 18, 2, '2025-08-25', NULL, 322, 'Pertalite', 122.00, 12000.00, 1464000.00, '', 'Tunai', NULL, NULL, NULL, NULL, NULL, NULL, '', NULL, '2025-08-25 08:19:23', '2025-08-25 08:19:23'),
(15, 19, 3, '2025-08-29', NULL, 321, 'Pertalite', 22.00, 12000.00, 264000.00, '', 'Tunai', NULL, NULL, NULL, NULL, NULL, NULL, '', NULL, '2025-08-29 05:33:01', '2025-08-29 05:33:01'),
(16, 19, 3, '2025-08-29', NULL, 322, 'Pertalite', 21.00, 12000.00, 252000.00, '', 'Tunai', NULL, NULL, NULL, NULL, NULL, NULL, '', NULL, '2025-08-29 05:49:26', '2025-08-29 05:49:26'),
(17, 19, 3, '2025-08-29', NULL, 322, 'Pertalite', 12.00, 1200.00, 14400.00, '', 'Tunai', NULL, NULL, NULL, NULL, NULL, NULL, '', NULL, '2025-08-29 05:50:48', '2025-08-29 05:50:48'),
(18, 19, 3, '2025-08-29', NULL, 321, 'Pertalite', 21.00, 12000.00, 252000.00, '', 'Tunai', NULL, NULL, NULL, NULL, NULL, NULL, '', NULL, '2025-08-29 06:57:54', '2025-08-29 06:57:54'),
(19, 19, 3, '2025-08-29', NULL, 321, 'Pertalite', 21.00, 12000.00, 252000.00, '', 'Tunai', NULL, NULL, NULL, NULL, NULL, NULL, '', NULL, '2025-08-29 07:03:01', '2025-08-29 07:03:01'),
(20, 27, 11, '2025-09-02', NULL, 322, 'Pertalite', 11.00, 12000.00, 132000.00, '', 'Tunai', NULL, NULL, NULL, NULL, NULL, NULL, '', NULL, '2025-09-02 07:53:36', '2025-09-02 07:53:36'),
(21, 27, 11, '2025-09-02', NULL, 322, 'Pertalite', 32.00, 12000.00, 384000.00, '', 'Tunai', NULL, NULL, NULL, NULL, NULL, NULL, '', NULL, '2025-09-02 07:56:13', '2025-09-02 07:56:13'),
(22, 27, 11, '2025-09-02', NULL, 322, 'Pertalite', 10.00, 12000.00, 120000.00, 'ww', 'Tunai', NULL, NULL, NULL, NULL, NULL, NULL, '', NULL, '2025-09-02 08:05:37', '2025-09-02 08:05:37');

-- --------------------------------------------------------

--
-- Struktur dari tabel `matra`
--

CREATE TABLE `matra` (
  `id` int(11) NOT NULL,
  `nama_matra` varchar(50) NOT NULL,
  `kode_matra` varchar(10) NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Master data Matra TNI';

--
-- Dumping data untuk tabel `matra`
--

INSERT INTO `matra` (`id`, `nama_matra`, `kode_matra`, `created_at`, `updated_at`) VALUES
(1, 'TNI Angkatan Darat', 'AD', '2025-08-20 11:00:08', '2025-08-20 11:00:08'),
(2, 'TNI Angkatan Laut', 'AL', '2025-08-20 11:00:08', '2025-08-20 11:00:08'),
(3, 'TNI Angkatan Udara', 'AU', '2025-08-20 11:00:08', '2025-08-20 11:00:08'),
(4, 'TNI Mabes', 'MABES', '2025-08-20 11:00:08', '2025-08-20 11:00:08');

-- --------------------------------------------------------

--
-- Struktur dari tabel `notifikasi`
--

CREATE TABLE `notifikasi` (
  `id` int(11) NOT NULL,
  `user_id` int(11) DEFAULT NULL,
  `role_id` int(11) DEFAULT NULL,
  `title` varchar(200) NOT NULL,
  `message` text NOT NULL,
  `type` enum('info','warning','error','success') DEFAULT 'info',
  `category` enum('system','maintenance','document','vehicle','user') DEFAULT 'system',
  `priority` enum('low','normal','high','urgent') DEFAULT 'normal',
  `data` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL COMMENT 'Additional data in JSON format' CHECK (json_valid(`data`)),
  `read_at` timestamp NULL DEFAULT NULL,
  `action_url` varchar(255) DEFAULT NULL,
  `expires_at` timestamp NULL DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='System notifications';

--
-- Dumping data untuk tabel `notifikasi`
--

INSERT INTO `notifikasi` (`id`, `user_id`, `role_id`, `title`, `message`, `type`, `category`, `priority`, `data`, `read_at`, `action_url`, `expires_at`, `created_at`) VALUES
(1, 2, NULL, 'Pengajuan Peminjaman', 'Pengajuan peminjaman kendaraan B 1234 CC (Toyota SUV) dari MAYOR LUT (T) BUDI SANTOSO untuk keperluan: Kunjungan', 'info', 'vehicle', 'normal', NULL, NULL, NULL, NULL, '2025-08-22 01:40:46'),
(2, 3, NULL, 'Pengajuan Peminjaman', 'Pengajuan peminjaman kendaraan B 1234 CC (Toyota SUV) dari MAYOR LUT (T) BUDI SANTOSO untuk keperluan: Kunjungan', 'info', 'vehicle', 'normal', NULL, NULL, NULL, NULL, '2025-08-22 01:40:46'),
(3, 4, NULL, 'Pengajuan Peminjaman', 'Pengajuan peminjaman kendaraan B 1234 CC (Toyota SUV) dari MAYOR LUT (T) BUDI SANTOSO untuk keperluan: Kunjungan', 'info', 'vehicle', 'normal', NULL, NULL, NULL, NULL, '2025-08-22 01:40:46'),
(4, 5, NULL, 'Pengajuan Peminjaman', 'Pengajuan peminjaman kendaraan B 1234 CC (Toyota SUV) dari MAYOR LUT (T) BUDI SANTOSO untuk keperluan: Kunjungan', 'info', 'vehicle', 'normal', NULL, NULL, NULL, NULL, '2025-08-22 01:40:47'),
(5, 2, NULL, 'Pengajuan Peminjaman', 'Pengajuan peminjaman kendaraan B 1234 CC (Toyota SUV) dari MAYOR LUT (T) BUDI SANTOSO untuk keperluan: Kunjungan', 'info', 'vehicle', 'normal', NULL, NULL, NULL, NULL, '2025-08-22 02:04:39'),
(6, 3, NULL, 'Pengajuan Peminjaman', 'Pengajuan peminjaman kendaraan B 1234 CC (Toyota SUV) dari MAYOR LUT (T) BUDI SANTOSO untuk keperluan: Kunjungan', 'info', 'vehicle', 'normal', NULL, NULL, NULL, NULL, '2025-08-22 02:04:39'),
(7, 4, NULL, 'Pengajuan Peminjaman', 'Pengajuan peminjaman kendaraan B 1234 CC (Toyota SUV) dari MAYOR LUT (T) BUDI SANTOSO untuk keperluan: Kunjungan', 'info', 'vehicle', 'normal', NULL, NULL, NULL, NULL, '2025-08-22 02:04:39'),
(8, 5, NULL, 'Pengajuan Peminjaman', 'Pengajuan peminjaman kendaraan B 1234 CC (Toyota SUV) dari MAYOR LUT (T) BUDI SANTOSO untuk keperluan: Kunjungan', 'info', 'vehicle', 'normal', NULL, NULL, NULL, NULL, '2025-08-22 02:04:39'),
(9, 2, NULL, 'Pengajuan Peminjaman', 'Pengajuan peminjaman kendaraan B 1234 CC (Toyota SUV) dari MAYOR LUT (T) BUDI SANTOSO untuk keperluan: Dinas', 'info', 'vehicle', 'normal', NULL, NULL, NULL, NULL, '2025-08-22 03:46:44'),
(10, 3, NULL, 'Pengajuan Peminjaman', 'Pengajuan peminjaman kendaraan B 1234 CC (Toyota SUV) dari MAYOR LUT (T) BUDI SANTOSO untuk keperluan: Dinas', 'info', 'vehicle', 'normal', NULL, NULL, NULL, NULL, '2025-08-22 03:46:44'),
(11, 4, NULL, 'Pengajuan Peminjaman', 'Pengajuan peminjaman kendaraan B 1234 CC (Toyota SUV) dari MAYOR LUT (T) BUDI SANTOSO untuk keperluan: Dinas', 'info', 'vehicle', 'normal', NULL, NULL, NULL, NULL, '2025-08-22 03:46:44'),
(12, 5, NULL, 'Pengajuan Peminjaman', 'Pengajuan peminjaman kendaraan B 1234 CC (Toyota SUV) dari MAYOR LUT (T) BUDI SANTOSO untuk keperluan: Dinas', 'info', 'vehicle', 'normal', NULL, NULL, NULL, NULL, '2025-08-22 03:46:44'),
(13, 3, NULL, 'Peminjaman Disetujui', 'Pengajuan peminjaman kendaraan B 1234 CC telah disetujui', 'info', 'system', 'normal', NULL, NULL, NULL, NULL, '2025-08-29 04:48:42'),
(15, 3, NULL, 'Peminjaman Disetujui', 'Pengajuan peminjaman kendaraan B 1234 CC telah disetujui', 'info', 'system', 'normal', NULL, NULL, NULL, NULL, '2025-08-29 04:58:13'),
(16, 2, NULL, 'Pengajuan Peminjaman', 'Pengajuan peminjaman kendaraan Reg: 8667-08 - TEST123 (Test Brand Test Model) Satker: Pusinfolahta - dari MAYOR LUT (T) BUDI SANTOSO untuk keperluan: dd', 'info', 'vehicle', 'normal', NULL, NULL, NULL, NULL, '2025-08-29 05:00:12'),
(17, 3, NULL, 'Pengajuan Peminjaman', 'Pengajuan peminjaman kendaraan Reg: 8667-08 - TEST123 (Test Brand Test Model) Satker: Pusinfolahta - dari MAYOR LUT (T) BUDI SANTOSO untuk keperluan: dd', 'info', 'vehicle', 'normal', NULL, NULL, NULL, NULL, '2025-08-29 05:00:12'),
(18, 4, NULL, 'Pengajuan Peminjaman', 'Pengajuan peminjaman kendaraan Reg: 8667-08 - TEST123 (Test Brand Test Model) Satker: Pusinfolahta - dari MAYOR LUT (T) BUDI SANTOSO untuk keperluan: dd', 'info', 'vehicle', 'normal', NULL, NULL, NULL, NULL, '2025-08-29 05:00:12'),
(19, 5, NULL, 'Pengajuan Peminjaman', 'Pengajuan peminjaman kendaraan Reg: 8667-08 - TEST123 (Test Brand Test Model) Satker: Pusinfolahta - dari MAYOR LUT (T) BUDI SANTOSO untuk keperluan: dd', 'info', 'vehicle', 'normal', NULL, NULL, NULL, NULL, '2025-08-29 05:00:12'),
(20, 3, NULL, 'Peminjaman Disetujui', 'Pengajuan peminjaman kendaraan TEST123 telah disetujui', 'info', 'system', 'normal', NULL, NULL, NULL, NULL, '2025-08-29 05:01:04'),
(21, 2, NULL, 'Pengajuan Peminjaman', 'Pengajuan peminjaman kendaraan Reg: 8697-08 - B9012GH (Mitsubishi Pajero) Satker: Pusinfolahta - dari MAYOR LUT (T) BUDI SANTOSO untuk keperluan: as', 'info', 'vehicle', 'normal', NULL, NULL, NULL, NULL, '2025-08-29 07:38:37'),
(22, 3, NULL, 'Pengajuan Peminjaman', 'Pengajuan peminjaman kendaraan Reg: 8697-08 - B9012GH (Mitsubishi Pajero) Satker: Pusinfolahta - dari MAYOR LUT (T) BUDI SANTOSO untuk keperluan: as', 'info', 'vehicle', 'normal', NULL, NULL, NULL, NULL, '2025-08-29 07:38:37'),
(23, 4, NULL, 'Pengajuan Peminjaman', 'Pengajuan peminjaman kendaraan Reg: 8697-08 - B9012GH (Mitsubishi Pajero) Satker: Pusinfolahta - dari MAYOR LUT (T) BUDI SANTOSO untuk keperluan: as', 'info', 'vehicle', 'normal', NULL, NULL, NULL, NULL, '2025-08-29 07:38:37'),
(24, 5, NULL, 'Pengajuan Peminjaman', 'Pengajuan peminjaman kendaraan Reg: 8697-08 - B9012GH (Mitsubishi Pajero) Satker: Pusinfolahta - dari MAYOR LUT (T) BUDI SANTOSO untuk keperluan: as', 'info', 'vehicle', 'normal', NULL, NULL, NULL, NULL, '2025-08-29 07:38:37'),
(25, 2, NULL, 'Pengajuan Peminjaman', 'Pengajuan peminjaman kendaraan Reg: 8667-08 - TEST123 (Test Brand Test Model) Satker: Pusinfolahta - dari SERDA RIJAL SURYADI untuk keperluan: as', 'info', 'vehicle', 'normal', NULL, NULL, NULL, NULL, '2025-09-01 00:19:18'),
(26, 3, NULL, 'Pengajuan Peminjaman', 'Pengajuan peminjaman kendaraan Reg: 8667-08 - TEST123 (Test Brand Test Model) Satker: Pusinfolahta - dari SERDA RIJAL SURYADI untuk keperluan: as', 'info', 'vehicle', 'normal', NULL, NULL, NULL, NULL, '2025-09-01 00:19:18'),
(27, 4, NULL, 'Pengajuan Peminjaman', 'Pengajuan peminjaman kendaraan Reg: 8667-08 - TEST123 (Test Brand Test Model) Satker: Pusinfolahta - dari SERDA RIJAL SURYADI untuk keperluan: as', 'info', 'vehicle', 'normal', NULL, NULL, NULL, NULL, '2025-09-01 00:19:18'),
(28, 5, NULL, 'Pengajuan Peminjaman', 'Pengajuan peminjaman kendaraan Reg: 8667-08 - TEST123 (Test Brand Test Model) Satker: Pusinfolahta - dari SERDA RIJAL SURYADI untuk keperluan: as', 'info', 'vehicle', 'normal', NULL, NULL, NULL, NULL, '2025-09-01 00:19:18'),
(29, 2, NULL, 'Pengajuan Peminjaman', 'Pengajuan peminjaman kendaraan Reg: 0200-08 - S 222 CD (Lexus Sedan) Satker:  - dari tni untuk keperluan: nn', 'info', 'vehicle', 'normal', NULL, NULL, NULL, NULL, '2025-09-02 06:08:09'),
(30, 3, NULL, 'Pengajuan Peminjaman', 'Pengajuan peminjaman kendaraan Reg: 0200-08 - S 222 CD (Lexus Sedan) Satker:  - dari tni untuk keperluan: nn', 'info', 'vehicle', 'normal', NULL, NULL, NULL, NULL, '2025-09-02 06:08:09'),
(31, 4, NULL, 'Pengajuan Peminjaman', 'Pengajuan peminjaman kendaraan Reg: 0200-08 - S 222 CD (Lexus Sedan) Satker:  - dari tni untuk keperluan: nn', 'info', 'vehicle', 'normal', NULL, NULL, NULL, NULL, '2025-09-02 06:08:09'),
(32, 5, NULL, 'Pengajuan Peminjaman', 'Pengajuan peminjaman kendaraan Reg: 0200-08 - S 222 CD (Lexus Sedan) Satker:  - dari tni untuk keperluan: nn', 'info', 'vehicle', 'normal', NULL, NULL, NULL, NULL, '2025-09-02 06:08:09'),
(33, 10, NULL, 'Pengajuan Peminjaman', 'Pengajuan peminjaman kendaraan Reg: 0200-08 - S 222 CD (Lexus Sedan) Satker:  - dari tni untuk keperluan: nn', 'info', 'vehicle', 'normal', NULL, NULL, NULL, NULL, '2025-09-02 06:08:09'),
(34, 11, NULL, 'Pengajuan Peminjaman', 'Pengajuan peminjaman kendaraan Reg: 0200-08 - S 222 CD (Lexus Sedan) Satker:  - dari tni untuk keperluan: nn', 'info', 'vehicle', 'normal', NULL, NULL, NULL, NULL, '2025-09-02 06:08:09'),
(35, 11, NULL, 'Peminjaman Disetujui', 'Pengajuan peminjaman kendaraan S 222 CD telah disetujui', 'info', 'system', 'normal', NULL, NULL, NULL, NULL, '2025-09-02 06:08:25'),
(36, 5, NULL, 'Peminjaman Disetujui', 'Pengajuan peminjaman kendaraan TEST123 telah disetujui', 'info', 'system', 'normal', NULL, NULL, NULL, NULL, '2025-09-02 06:12:24'),
(37, 2, NULL, 'Pengajuan Peminjaman', 'Pengajuan peminjaman kendaraan Reg: 0822-02 - F5888EF (Honda Civic) Satker:  - dari SERDA RIJAL SURYADI untuk keperluan: s', 'info', 'vehicle', 'normal', NULL, NULL, NULL, NULL, '2025-09-02 07:05:17'),
(38, 3, NULL, 'Pengajuan Peminjaman', 'Pengajuan peminjaman kendaraan Reg: 0822-02 - F5888EF (Honda Civic) Satker:  - dari SERDA RIJAL SURYADI untuk keperluan: s', 'info', 'vehicle', 'normal', NULL, NULL, NULL, NULL, '2025-09-02 07:05:17'),
(39, 4, NULL, 'Pengajuan Peminjaman', 'Pengajuan peminjaman kendaraan Reg: 0822-02 - F5888EF (Honda Civic) Satker:  - dari SERDA RIJAL SURYADI untuk keperluan: s', 'info', 'vehicle', 'normal', NULL, NULL, NULL, NULL, '2025-09-02 07:05:17'),
(40, 5, NULL, 'Pengajuan Peminjaman', 'Pengajuan peminjaman kendaraan Reg: 0822-02 - F5888EF (Honda Civic) Satker:  - dari SERDA RIJAL SURYADI untuk keperluan: s', 'info', 'vehicle', 'normal', NULL, NULL, NULL, NULL, '2025-09-02 07:05:17'),
(41, 10, NULL, 'Pengajuan Peminjaman', 'Pengajuan peminjaman kendaraan Reg: 0822-02 - F5888EF (Honda Civic) Satker:  - dari SERDA RIJAL SURYADI untuk keperluan: s', 'info', 'vehicle', 'normal', NULL, NULL, NULL, NULL, '2025-09-02 07:05:17'),
(42, 11, NULL, 'Pengajuan Peminjaman', 'Pengajuan peminjaman kendaraan Reg: 0822-02 - F5888EF (Honda Civic) Satker:  - dari SERDA RIJAL SURYADI untuk keperluan: s', 'info', 'vehicle', 'normal', NULL, NULL, NULL, NULL, '2025-09-02 07:05:17'),
(43, 5, NULL, 'Peminjaman Disetujui', 'Pengajuan peminjaman kendaraan F5888EF telah disetujui', 'info', 'system', 'normal', NULL, NULL, NULL, NULL, '2025-09-02 07:05:29'),
(44, 2, NULL, 'Pengajuan Peminjaman', 'Pengajuan peminjaman kendaraan Reg: 8667-08 - TEST123 (Test Brand Test Model) Satker: Pusinfolahta - dari as untuk keperluan: 2', 'info', 'vehicle', 'normal', NULL, NULL, NULL, NULL, '2025-09-03 01:31:47'),
(45, 3, NULL, 'Pengajuan Peminjaman', 'Pengajuan peminjaman kendaraan Reg: 8667-08 - TEST123 (Test Brand Test Model) Satker: Pusinfolahta - dari as untuk keperluan: 2', 'info', 'vehicle', 'normal', NULL, NULL, NULL, NULL, '2025-09-03 01:31:47'),
(46, 4, NULL, 'Pengajuan Peminjaman', 'Pengajuan peminjaman kendaraan Reg: 8667-08 - TEST123 (Test Brand Test Model) Satker: Pusinfolahta - dari as untuk keperluan: 2', 'info', 'vehicle', 'normal', NULL, NULL, NULL, NULL, '2025-09-03 01:31:47'),
(47, 5, NULL, 'Pengajuan Peminjaman', 'Pengajuan peminjaman kendaraan Reg: 8667-08 - TEST123 (Test Brand Test Model) Satker: Pusinfolahta - dari as untuk keperluan: 2', 'info', 'vehicle', 'normal', NULL, NULL, NULL, NULL, '2025-09-03 01:31:47'),
(48, 10, NULL, 'Pengajuan Peminjaman', 'Pengajuan peminjaman kendaraan Reg: 8667-08 - TEST123 (Test Brand Test Model) Satker: Pusinfolahta - dari as untuk keperluan: 2', 'info', 'vehicle', 'normal', NULL, NULL, NULL, NULL, '2025-09-03 01:31:47'),
(49, 11, NULL, 'Pengajuan Peminjaman', 'Pengajuan peminjaman kendaraan Reg: 8667-08 - TEST123 (Test Brand Test Model) Satker: Pusinfolahta - dari as untuk keperluan: 2', 'info', 'vehicle', 'normal', NULL, NULL, NULL, NULL, '2025-09-03 01:31:47'),
(50, 2, NULL, 'Pengajuan Peminjaman', 'Pengajuan peminjaman kendaraan Reg: 8667-08 - TEST123 (Test Brand Test Model) Satker: Pusinfolahta - dari as untuk keperluan: e', 'info', 'vehicle', 'normal', NULL, NULL, NULL, NULL, '2025-09-04 01:52:04'),
(51, 3, NULL, 'Pengajuan Peminjaman', 'Pengajuan peminjaman kendaraan Reg: 8667-08 - TEST123 (Test Brand Test Model) Satker: Pusinfolahta - dari as untuk keperluan: e', 'info', 'vehicle', 'normal', NULL, NULL, NULL, NULL, '2025-09-04 01:52:04'),
(52, 4, NULL, 'Pengajuan Peminjaman', 'Pengajuan peminjaman kendaraan Reg: 8667-08 - TEST123 (Test Brand Test Model) Satker: Pusinfolahta - dari as untuk keperluan: e', 'info', 'vehicle', 'normal', NULL, NULL, NULL, NULL, '2025-09-04 01:52:04'),
(53, 5, NULL, 'Pengajuan Peminjaman', 'Pengajuan peminjaman kendaraan Reg: 8667-08 - TEST123 (Test Brand Test Model) Satker: Pusinfolahta - dari as untuk keperluan: e', 'info', 'vehicle', 'normal', NULL, NULL, NULL, NULL, '2025-09-04 01:52:04'),
(54, 10, NULL, 'Pengajuan Peminjaman', 'Pengajuan peminjaman kendaraan Reg: 8667-08 - TEST123 (Test Brand Test Model) Satker: Pusinfolahta - dari as untuk keperluan: e', 'info', 'vehicle', 'normal', NULL, NULL, NULL, NULL, '2025-09-04 01:52:04'),
(55, 11, NULL, 'Pengajuan Peminjaman', 'Pengajuan peminjaman kendaraan Reg: 8667-08 - TEST123 (Test Brand Test Model) Satker: Pusinfolahta - dari as untuk keperluan: e', 'info', 'vehicle', 'normal', NULL, NULL, NULL, NULL, '2025-09-04 01:52:04'),
(56, 10, NULL, 'Peminjaman Disetujui', 'Pengajuan peminjaman kendaraan TEST123 telah disetujui', 'info', 'system', 'normal', NULL, NULL, NULL, NULL, '2025-09-04 06:42:03'),
(57, 3, NULL, 'Peminjaman Ditolak', 'Pengajuan peminjaman kendaraan B9012GH ditolak: sudah lewat', 'info', 'system', 'normal', NULL, NULL, NULL, NULL, '2025-09-04 06:45:53'),
(58, 2, NULL, 'Pengajuan Peminjaman', 'Pengajuan peminjaman kendaraan Reg: 8065-08 - B 1234 AB (Toyota SUV) Satker: Pusinfolahta - dari as untuk keperluan: ss', 'info', 'vehicle', 'normal', NULL, NULL, NULL, NULL, '2025-09-04 08:36:16'),
(59, 3, NULL, 'Pengajuan Peminjaman', 'Pengajuan peminjaman kendaraan Reg: 8065-08 - B 1234 AB (Toyota SUV) Satker: Pusinfolahta - dari as untuk keperluan: ss', 'info', 'vehicle', 'normal', NULL, NULL, NULL, NULL, '2025-09-04 08:36:16'),
(60, 4, NULL, 'Pengajuan Peminjaman', 'Pengajuan peminjaman kendaraan Reg: 8065-08 - B 1234 AB (Toyota SUV) Satker: Pusinfolahta - dari as untuk keperluan: ss', 'info', 'vehicle', 'normal', NULL, NULL, NULL, NULL, '2025-09-04 08:36:16'),
(61, 5, NULL, 'Pengajuan Peminjaman', 'Pengajuan peminjaman kendaraan Reg: 8065-08 - B 1234 AB (Toyota SUV) Satker: Pusinfolahta - dari as untuk keperluan: ss', 'info', 'vehicle', 'normal', NULL, NULL, NULL, NULL, '2025-09-04 08:36:16'),
(62, 10, NULL, 'Pengajuan Peminjaman', 'Pengajuan peminjaman kendaraan Reg: 8065-08 - B 1234 AB (Toyota SUV) Satker: Pusinfolahta - dari as untuk keperluan: ss', 'info', 'vehicle', 'normal', NULL, NULL, NULL, NULL, '2025-09-04 08:36:16'),
(63, 11, NULL, 'Pengajuan Peminjaman', 'Pengajuan peminjaman kendaraan Reg: 8065-08 - B 1234 AB (Toyota SUV) Satker: Pusinfolahta - dari as untuk keperluan: ss', 'info', 'vehicle', 'normal', NULL, NULL, NULL, NULL, '2025-09-04 08:36:16'),
(64, 12, NULL, 'Pengajuan Peminjaman', 'Pengajuan peminjaman kendaraan Reg: 8065-08 - B 1234 AB (Toyota SUV) Satker: Pusinfolahta - dari as untuk keperluan: ss', 'info', 'vehicle', 'normal', NULL, NULL, NULL, NULL, '2025-09-04 08:36:16'),
(65, 10, NULL, 'Peminjaman Disetujui', 'Pengajuan peminjaman kendaraan B 1234 AB telah disetujui', 'info', 'system', 'normal', NULL, NULL, NULL, NULL, '2025-09-04 08:36:35');

-- --------------------------------------------------------

--
-- Struktur dari tabel `peminjaman_kendaraan`
--

CREATE TABLE `peminjaman_kendaraan` (
  `id` int(11) NOT NULL,
  `kendaraan_id` int(11) NOT NULL,
  `peminjam_id` int(11) NOT NULL,
  `approval_by` int(11) DEFAULT NULL,
  `nomor_surat` varchar(50) DEFAULT NULL,
  `tanggal_mulai` datetime NOT NULL,
  `tanggal_selesai` datetime NOT NULL,
  `tujuan` varchar(255) NOT NULL,
  `keperluan` text DEFAULT NULL,
  `rute` text DEFAULT NULL,
  `driver_id` int(11) DEFAULT NULL,
  `km_awal` int(11) DEFAULT NULL,
  `km_akhir` int(11) DEFAULT NULL,
  `bbm_awal` decimal(5,2) DEFAULT NULL,
  `bbm_akhir` decimal(5,2) DEFAULT NULL,
  `status` enum('Pending','Approved','Ongoing','Completed','Cancelled','Rejected') DEFAULT 'Pending',
  `tanggal_approval` timestamp NULL DEFAULT NULL,
  `tanggal_mulai_aktual` timestamp NULL DEFAULT NULL,
  `tanggal_selesai_aktual` timestamp NULL DEFAULT NULL,
  `catatan_approval` text DEFAULT NULL,
  `catatan_pengembalian` text DEFAULT NULL,
  `kondisi_kembali` enum('Baik','Rusak Ringan','Rusak Berat') DEFAULT 'Baik',
  `biaya_operasional` decimal(15,2) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `cancel_reason` text DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Transaksi peminjaman kendaraan';

--
-- Dumping data untuk tabel `peminjaman_kendaraan`
--

INSERT INTO `peminjaman_kendaraan` (`id`, `kendaraan_id`, `peminjam_id`, `approval_by`, `nomor_surat`, `tanggal_mulai`, `tanggal_selesai`, `tujuan`, `keperluan`, `rute`, `driver_id`, `km_awal`, `km_akhir`, `bbm_awal`, `bbm_akhir`, `status`, `tanggal_approval`, `tanggal_mulai_aktual`, `tanggal_selesai_aktual`, `catatan_approval`, `catatan_pengembalian`, `kondisi_kembali`, `biaya_operasional`, `created_at`, `updated_at`, `cancel_reason`) VALUES
(2, 18, 3, NULL, NULL, '2025-09-01 08:25:00', '2025-09-05 10:25:00', 'Bogor', 'Kunjungan', NULL, NULL, NULL, NULL, NULL, NULL, 'Pending', NULL, NULL, NULL, NULL, NULL, 'Baik', NULL, '2025-08-22 01:36:28', '2025-08-22 01:36:28', NULL),
(3, 18, 3, NULL, NULL, '2025-09-01 08:25:00', '2025-09-05 10:25:00', 'Bogor', 'Kunjungan', NULL, NULL, NULL, NULL, NULL, NULL, 'Pending', NULL, NULL, NULL, NULL, NULL, 'Baik', NULL, '2025-08-22 01:40:46', '2025-08-22 01:40:46', NULL),
(4, 18, 3, 1, NULL, '2025-09-05 08:00:00', '2025-09-05 10:00:00', 'Bogor', 'Kunjungan', NULL, NULL, NULL, NULL, NULL, NULL, 'Completed', NULL, NULL, '2025-09-08 01:32:24', '', NULL, 'Baik', NULL, '2025-08-22 02:04:39', '2025-09-08 01:32:24', NULL),
(5, 18, 3, 1, NULL, '2025-09-01 10:00:00', '2025-09-01 22:00:00', 'Bogor', 'Dinas', NULL, NULL, NULL, NULL, NULL, NULL, 'Completed', NULL, NULL, '2025-09-03 01:45:46', '', NULL, 'Baik', NULL, '2025-08-22 03:46:43', '2025-09-03 01:45:46', NULL),
(6, 18, 3, NULL, 'ST/007/VIII/2025', '2025-08-26 00:00:00', '2025-08-26 00:00:00', 'Bogor', 'jjjjjjjjas asjasmas sajasnasj asasnn', NULL, NULL, NULL, NULL, NULL, NULL, 'Completed', NULL, NULL, NULL, NULL, NULL, 'Baik', NULL, '2025-08-26 08:53:04', '2025-08-26 08:53:04', NULL),
(7, 23, 3, NULL, 'ST/009/VIII/2025', '2025-08-27 00:00:00', '2025-08-27 00:00:00', 'Bogor', 'Melaksanakan keberangkatan ke satker', NULL, NULL, NULL, NULL, NULL, NULL, 'Completed', NULL, NULL, NULL, NULL, NULL, 'Baik', NULL, '2025-08-27 00:23:43', '2025-08-28 00:29:46', NULL),
(9, 19, 3, 1, NULL, '2025-08-29 12:00:00', '2025-08-29 14:00:00', 'dd', 'dd', NULL, NULL, NULL, NULL, NULL, NULL, 'Completed', NULL, NULL, '2025-09-03 01:45:46', '', NULL, 'Baik', NULL, '2025-08-29 05:00:12', '2025-09-03 01:45:46', NULL),
(10, 23, 3, NULL, NULL, '2025-08-29 14:38:00', '2025-08-29 16:38:00', 'ds', 'as', NULL, NULL, NULL, NULL, NULL, NULL, 'Rejected', NULL, NULL, NULL, 'Alasan: sudah lewat', NULL, 'Baik', NULL, '2025-08-29 07:38:36', '2025-09-04 06:45:53', NULL),
(11, 19, 5, 1, NULL, '2025-09-24 07:20:00', '2025-09-24 09:20:00', 'ds', 'as', NULL, NULL, NULL, NULL, NULL, NULL, 'Completed', NULL, NULL, NULL, '', NULL, 'Baik', NULL, '2025-09-01 00:19:18', '2025-09-02 07:14:17', NULL),
(12, 27, 11, 1, NULL, '2025-09-02 13:10:00', '2025-09-03 15:10:00', 'nn', 'nn', NULL, NULL, NULL, NULL, NULL, NULL, 'Completed', NULL, '2025-09-03 01:45:46', '2025-09-03 12:36:41', '', NULL, 'Baik', NULL, '2025-09-02 06:08:09', '2025-09-03 12:36:41', NULL),
(13, 25, 5, 1, NULL, '2025-09-02 14:05:00', '2025-09-03 16:05:00', 's', 's', NULL, NULL, NULL, NULL, NULL, NULL, 'Completed', NULL, '2025-09-03 01:45:46', '2025-09-03 12:36:41', '', NULL, 'Baik', NULL, '2025-09-02 07:05:17', '2025-09-03 12:36:41', NULL),
(14, 19, 10, NULL, NULL, '2025-09-03 08:35:00', '2025-09-03 10:35:00', '2', '2', NULL, NULL, NULL, NULL, NULL, NULL, 'Pending', NULL, NULL, NULL, NULL, NULL, 'Baik', NULL, '2025-09-03 01:31:47', '2025-09-03 01:31:47', NULL),
(15, 19, 10, 1, NULL, '2025-09-05 08:55:00', '2025-09-05 20:55:00', 'e', 'e', NULL, NULL, NULL, NULL, NULL, NULL, 'Completed', NULL, NULL, '2025-09-08 01:32:24', '', NULL, 'Baik', NULL, '2025-09-04 01:52:04', '2025-09-08 01:32:24', NULL),
(16, 1, 10, 1, NULL, '2025-09-04 15:40:00', '2025-09-05 17:40:00', 'ss', 'ss', NULL, NULL, NULL, NULL, NULL, NULL, 'Completed', NULL, NULL, '2025-09-08 01:32:24', '', NULL, 'Baik', NULL, '2025-09-04 08:36:16', '2025-09-08 01:32:24', NULL);

--
-- Trigger `peminjaman_kendaraan`
--
DELIMITER $$
CREATE TRIGGER `UpdateVehicleStatusOnLoan` AFTER UPDATE ON `peminjaman_kendaraan` FOR EACH ROW BEGIN
    -- Update status when loan is approved
    IF NEW.status = 'Approved' AND OLD.status != 'Approved' THEN
        UPDATE kendaraan 
        SET status_peminjaman = 'Dipinjam'
        WHERE id = NEW.kendaraan_id;
    END IF;
    
    -- Update status when loan is completed
    IF NEW.status = 'Completed' AND OLD.status != 'Completed' THEN
        UPDATE kendaraan 
        SET status_peminjaman = 'Tersedia'
        WHERE id = NEW.kendaraan_id;
    END IF;
END
$$
DELIMITER ;

-- --------------------------------------------------------

--
-- Struktur dari tabel `peminjaman_terjadwal`
--

CREATE TABLE `peminjaman_terjadwal` (
  `id` int(11) NOT NULL,
  `pemohon_id` int(11) DEFAULT NULL,
  `kendaraan_id` int(11) NOT NULL,
  `keperluan` text DEFAULT NULL,
  `tujuan` varchar(255) DEFAULT NULL,
  `tanggal_mulai` datetime DEFAULT NULL,
  `tanggal_selesai` datetime DEFAULT NULL,
  `jam_mulai` time DEFAULT NULL,
  `jam_selesai` time DEFAULT NULL,
  `status` varchar(50) DEFAULT 'pending',
  `approved_by` int(11) DEFAULT NULL,
  `approved_at` datetime DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `updated_at` datetime NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data untuk tabel `peminjaman_terjadwal`
--

INSERT INTO `peminjaman_terjadwal` (`id`, `pemohon_id`, `kendaraan_id`, `keperluan`, `tujuan`, `tanggal_mulai`, `tanggal_selesai`, `jam_mulai`, `jam_selesai`, `status`, `approved_by`, `approved_at`, `created_at`, `updated_at`) VALUES
(1, 3, 17, 'Sehubungan dasar di atas, dengan hormat diajukan permohonan peminjaman 1 (satu) unit Avanza\r\n SUV', 'Bandung - Jawa Barat', '2025-09-01 00:00:00', '2025-09-01 00:00:00', NULL, NULL, 'approved', NULL, NULL, '2025-09-02 13:27:02', '2025-09-03 08:25:38'),
(2, 11, 19, 'ww', 'Bogor', '2025-09-02 00:00:00', '2025-09-03 00:00:00', NULL, NULL, 'approved', NULL, NULL, '2025-09-02 13:27:40', '2025-09-02 13:27:40'),
(3, 3, 17, 'Jalan', 'Kalimantan', '2025-08-28 00:00:00', '2025-08-28 00:00:00', NULL, NULL, 'approved', NULL, NULL, '2025-09-02 13:32:17', '2025-09-02 13:32:17'),
(4, 12, 19, 'e', 'dd', '2025-09-08 00:00:00', '2025-09-10 23:59:59', NULL, NULL, 'approved', NULL, NULL, '2025-09-04 08:57:46', '2025-09-04 08:57:46'),
(5, 12, 19, 'e', 'dd', '2025-09-08 00:00:00', '2025-09-10 00:00:00', NULL, NULL, 'approved', NULL, NULL, '2025-09-08 14:33:22', '2025-09-08 14:33:22');

-- --------------------------------------------------------

--
-- Struktur dari tabel `pengguna`
--

CREATE TABLE `pengguna` (
  `id` int(11) NOT NULL,
  `nrp_nip` varchar(30) DEFAULT NULL,
  `nama_lengkap` varchar(100) NOT NULL,
  `pangkat` varchar(50) DEFAULT NULL,
  `jabatan` varchar(100) DEFAULT NULL,
  `satuan_pns` varchar(200) DEFAULT NULL,
  `no_hp` varchar(20) DEFAULT NULL,
  `email` varchar(100) DEFAULT NULL,
  `alamat` text DEFAULT NULL,
  `status_pegawai` enum('Aktif','Pensiun','Mutasi','Non-Aktif') NOT NULL DEFAULT 'Aktif',
  `status_aktif` enum('Aktif','Tidak Aktif') NOT NULL DEFAULT 'Aktif',
  `jenis_personel` enum('TNI','PNS') DEFAULT NULL,
  `matra` varchar(100) DEFAULT NULL,
  `korps` varchar(100) DEFAULT NULL,
  `kesatuan` varchar(200) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data untuk tabel `pengguna`
--

INSERT INTO `pengguna` (`id`, `nrp_nip`, `nama_lengkap`, `pangkat`, `jabatan`, `satuan_pns`, `no_hp`, `email`, `alamat`, `status_pegawai`, `status_aktif`, `jenis_personel`, `matra`, `korps`, `kesatuan`, `created_at`, `updated_at`) VALUES
(1, '31010045870399', 'BRIGJEN TNI WAWAN PUJIATMOKO', 'LAKSDA TNI', 'KAPUSINFOLAHTA', NULL, '081234567890', 'ariantyo@tni.mil.id', 'Jakarta Pusat', 'Aktif', 'Aktif', 'TNI', 'AD', 'inf', 'Pusat Informasi Pengolah Data', '2025-08-13 01:26:52', '2025-09-08 06:45:53'),
(2, '31070366780481', 'KOLONEL LUT (T) WAHYU HIDAYAT', 'KOLONEL LUT (T)', 'WAKAPUSINFOLAHTA', NULL, '081234567891', 'wahyu.hidayat@tni.mil.id', 'Jakarta Timur', 'Aktif', 'Aktif', 'TNI', 'AL', 'Marinir', 'Pusat Informasi Pengolah Data', '2025-08-13 01:26:52', '2025-09-04 08:01:20'),
(3, '31070366800485', 'MAYOR LUT (T) BUDI SANTOSO', 'MAYOR LUT (T)', 'KASI RANMIN', NULL, '081234567892', 'kol@gmail.com', 'Jakarta Selatan', 'Aktif', 'Aktif', NULL, NULL, NULL, 'Pusat Informasi Pengolah Data', '2025-08-13 01:26:52', '2025-09-01 03:06:53'),
(4, '31070388920592', 'KAPTEN LUT (T) AHMAD FAUZI', 'KAPTEN LUT (T)', 'OPERATOR SISTEM', NULL, '081234567893', 'ahmad.fauzi@tni.mil.id', 'Depok', 'Aktif', 'Aktif', NULL, NULL, NULL, 'Pusat Informasi Pengolah Data', '2025-08-13 01:26:52', '2025-09-01 03:06:53'),
(5, '31050299880395', 'SERDA RIJAL SURYADI', 'SERDA', 'PENGEMUDI', NULL, '081234567894', 'rijal.suryadi@tni.mil.id', 'Bekasi', 'Aktif', 'Aktif', NULL, NULL, NULL, 'Pusat Informasi Pengolah Data', '2025-08-13 01:26:52', '2025-09-01 03:06:53'),
(10, '32100824', 'as', 'Pembina tk 3/a', 'Aspri', 'Pusinfolahta', '082312355', 'as@gmail.com', 'Jl.sweh', 'Aktif', 'Aktif', 'PNS', NULL, NULL, NULL, '2025-09-01 03:42:46', '2025-09-01 03:42:46'),
(11, '311129238', 'tni', 'Peltu', 'Anggota Pleton 2', NULL, '082144322', 'tni@gmail.com', 'Jl.shnm', 'Aktif', 'Aktif', 'TNI', 'AD', 'Infantri', 'Pusinfolahta', '2025-09-01 03:58:15', '2025-09-01 03:58:15'),
(12, '3212312', 'asn', '4a', 'Kasub', 'Pusinfo', '08123123', 'as@gmail.com', 'Jl.asa', 'Aktif', 'Aktif', 'PNS', NULL, NULL, NULL, '2025-09-04 01:55:17', '2025-09-04 01:55:17');

-- --------------------------------------------------------

--
-- Struktur dari tabel `pengguna_kendaraan`
--

CREATE TABLE `pengguna_kendaraan` (
  `id` int(11) NOT NULL,
  `pengguna_id` int(11) NOT NULL,
  `kendaraan_id` int(11) NOT NULL,
  `tanggal_mulai` date NOT NULL,
  `tanggal_selesai` date DEFAULT NULL,
  `status` enum('Aktif','Selesai','Ditangguhkan') DEFAULT 'Aktif',
  `keterangan` text DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data untuk tabel `pengguna_kendaraan`
--

INSERT INTO `pengguna_kendaraan` (`id`, `pengguna_id`, `kendaraan_id`, `tanggal_mulai`, `tanggal_selesai`, `status`, `keterangan`, `created_at`, `updated_at`) VALUES
(1, 3, 17, '2025-08-28', '2025-08-28', '', NULL, '2025-09-02 06:27:02', '2025-09-02 06:32:17'),
(2, 11, 19, '2025-09-02', '2025-09-03', '', NULL, '2025-09-02 06:27:40', '2025-09-02 06:27:40'),
(3, 12, 19, '2025-09-08', '2025-09-10', '', NULL, '2025-09-04 01:57:46', '2025-09-08 07:33:22');

-- --------------------------------------------------------

--
-- Struktur dari tabel `riwayat_pemakaian`
--

CREATE TABLE `riwayat_pemakaian` (
  `id` int(11) NOT NULL,
  `kendaraan_id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `driver_id` int(11) DEFAULT NULL,
  `tanggal` date NOT NULL,
  `jam_keluar` time DEFAULT NULL,
  `jam_kembali` time DEFAULT NULL,
  `km_awal` int(11) NOT NULL,
  `km_akhir` int(11) DEFAULT NULL,
  `tujuan` varchar(255) NOT NULL,
  `keperluan` text DEFAULT NULL,
  `rute` text DEFAULT NULL,
  `bbm_keluar` decimal(5,2) DEFAULT NULL,
  `bbm_kembali` decimal(5,2) DEFAULT NULL,
  `kondisi_keluar` enum('Baik','Rusak Ringan','Rusak Berat') DEFAULT 'Baik',
  `kondisi_kembali` enum('Baik','Rusak Ringan','Rusak Berat') DEFAULT 'Baik',
  `catatan` text DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data untuk tabel `riwayat_pemakaian`
--

INSERT INTO `riwayat_pemakaian` (`id`, `kendaraan_id`, `user_id`, `driver_id`, `tanggal`, `jam_keluar`, `jam_kembali`, `km_awal`, `km_akhir`, `tujuan`, `keperluan`, `rute`, `bbm_keluar`, `bbm_kembali`, `kondisi_keluar`, `kondisi_kembali`, `catatan`, `created_at`, `updated_at`) VALUES
(26, 1, 2, 5, '2025-08-19', '08:00:00', '17:00:00', 13500, 13650, 'Mabes TNI Cilangkap', 'Rapat koordinasi dengan Mabes TNI', 'Pusinfolahta - Mabes TNI - Pusinfolahta', 75.00, 65.00, 'Baik', 'Baik', 'Perjalanan lancar, kendaraan dalam kondisi baik', '2025-08-20 06:55:42', '2025-08-20 06:55:42'),
(27, 3, 3, 5, '2025-08-18', '09:30:00', '15:30:00', 24200, 24350, 'Kemhan RI', 'Pengambilan dokumen dan koordinasi', 'Pusinfolahta - Kemhan - Pusinfolahta', 80.00, 70.00, 'Baik', 'Baik', 'Dokumen berhasil diambil, koordinasi berjalan lancar', '2025-08-20 06:56:25', '2025-08-20 06:56:25'),
(28, 17, 2, 4, '2025-08-17', '13:00:00', '18:00:00', 14200, 14320, 'Bandara Halim', 'Penjemputan tamu VIP', 'Pusinfolahta - Bandara Halim - Hotel - Pusinfolahta', 70.00, 60.00, 'Baik', 'Baik', 'Penjemputan tamu berhasil, kendaraan bersih dan nyaman', '2025-08-20 06:57:12', '2025-08-20 06:57:12'),
(29, 1, 1, 5, '2025-08-20', '07:30:00', NULL, 13650, NULL, 'Istana Negara', 'Menghadiri upacara kenegaraan', 'Pusinfolahta - Istana Negara', 90.00, NULL, 'Baik', 'Baik', 'Berangkat untuk upacara kenegaraan, estimasi kembali sore', '2025-08-20 06:58:05', '2025-08-20 06:58:05'),
(30, 18, 3, 5, '2025-08-10', '08:00:00', '12:30:00', 5000, 5120, 'Kantor Pusinfolahta', 'Koordinasi internal', 'Pusinfolahta - Kantor - Pusinfolahta', 40.00, 35.00, 'Baik', 'Baik', 'Perjalanan singkat, tepat waktu', '2025-08-10 06:00:00', '2025-08-10 06:00:00'),
(31, 1, 3, 5, '2025-08-12', '09:00:00', '17:00:00', 13650, 13800, 'Mabes TNI', 'Rapat koordinasi', 'Pusinfolahta - Mabes TNI - Pusinfolahta', 75.00, 65.00, 'Baik', 'Baik', 'Rapat berjalan lancar', '2025-08-12 10:30:00', '2025-08-12 10:30:00'),
(32, 17, 3, 5, '2025-08-15', '07:30:00', '19:00:00', 14200, 14450, 'Bandara Halim', 'Penjemputan tamu VIP', 'Pusinfolahta - Bandara Halim - Hotel - Pusinfolahta', 70.00, 60.00, 'Baik', 'Baik', 'Penjemputan tamu VIP', '2025-08-15 12:15:00', '2025-08-15 12:15:00'),
(33, 3, 3, 5, '2025-08-18', '09:30:00', '15:30:00', 24200, 24350, 'Kemhan RI', 'Pengambilan dokumen', 'Pusinfolahta - Kemhan - Pusinfolahta', 80.00, 70.00, 'Baik', 'Baik', 'Dokumen diambil dan koordinasi selesai', '2025-08-18 09:00:00', '2025-08-18 09:00:00'),
(34, 18, 3, NULL, '2025-08-20', '07:30:00', '12:00:00', 5120, 5170, 'Jakarta Selatan', 'Kunjungan kerja wilayah', 'Pusinfolahta - Jakarta Selatan - Pusinfolahta', 50.00, 45.00, 'Baik', 'Baik', 'Perjalanan dinas singkat', '2025-08-20 05:30:00', '2025-08-20 05:30:00'),
(35, 19, 3, 5, '2025-08-22', '08:00:00', '11:30:00', 0, 120, 'Test Location', 'Uji coba kendaraan', 'Pusinfolahta - Test Location - Pusinfolahta', 10.00, 8.00, 'Baik', 'Baik', 'Uji coba dan pengecekan kendaraan', '2025-08-22 05:00:00', '2025-08-22 05:00:00');

--
-- Trigger `riwayat_pemakaian`
--
DELIMITER $$
CREATE TRIGGER `tr_update_vehicle_odometer` AFTER INSERT ON `riwayat_pemakaian` FOR EACH ROW BEGIN
    UPDATE kendaraan 
    SET odometer = NEW.km_akhir,
        last_service_km = NEW.km_akhir,
        updated_at = CURRENT_TIMESTAMP
    WHERE id = NEW.kendaraan_id 
    AND (odometer IS NULL OR NEW.km_akhir > odometer);
END
$$
DELIMITER ;

-- --------------------------------------------------------

--
-- Struktur dari tabel `riwayat_perawatan`
--

CREATE TABLE `riwayat_perawatan` (
  `id` int(11) NOT NULL,
  `kendaraan_id` int(11) NOT NULL,
  `tanggal_perawatan` date NOT NULL,
  `jenis_perawatan` varchar(100) NOT NULL,
  `deskripsi` text DEFAULT NULL,
  `km_saat_perawatan` int(11) DEFAULT NULL,
  `mekanik` varchar(100) DEFAULT NULL COMMENT 'Nama atau ID teknisi yang menangani perawatan',
  `biaya` decimal(15,2) DEFAULT NULL,
  `status` enum('Selesai','Ongoing','Pending','Cancelled') DEFAULT 'Selesai',
  `keterangan` text DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `created_by` int(11) DEFAULT NULL,
  `kategori` enum('Ringan','Sedang','Berat') DEFAULT 'Ringan' COMMENT 'Kategori perawatan berdasarkan tingkat kesulitan',
  `bengkel` varchar(100) DEFAULT NULL COMMENT 'Nama bengkel tempat perawatan',
  `teknisi_id` int(11) DEFAULT NULL COMMENT 'ID teknisi yang menangani'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data untuk tabel `riwayat_perawatan`
--

INSERT INTO `riwayat_perawatan` (`id`, `kendaraan_id`, `tanggal_perawatan`, `jenis_perawatan`, `deskripsi`, `km_saat_perawatan`, `mekanik`, `biaya`, `status`, `keterangan`, `created_at`, `updated_at`, `created_by`, `kategori`, `bengkel`, `teknisi_id`) VALUES
(6, 1, '2025-08-19', 'Servis Berkala', 'Servis 20.000 KM rutin - Ganti oli mesin, filter oli, filter udara, dan pemeriksaan sistem', 19500, 'KOLONEL LUT (T) WAHYU HIDAYAT', 475000.00, 'Selesai', 'Servis berkala 20.000 KM selesai dengan baik. Semua komponen dalam kondisi normal, oli dan filter telah diganti.', '2025-08-20 06:59:18', '2025-08-20 06:59:18', 3, 'Ringan', NULL, NULL),
(7, 3, '2025-08-18', 'Ganti Ban', 'Penggantian 4 ban kendaraan karena sudah tipis dan aus', 24800, 'KOLONEL LUT (T) WAHYU HIDAYAT', 1850000.00, 'Selesai', 'Ban berhasil diganti semua 4 buah dengan merk yang sama. Kondisi ban baru dan siap pakai untuk operasional.', '2025-08-20 07:00:02', '2025-08-20 07:00:02', 3, 'Ringan', NULL, NULL),
(8, 17, '2025-08-17', 'Tune Up', 'Tune up mesin lengkap meliputi ganti busi, pembersihan injektor, dan kalibrasi mesin', 14500, 'MAYOR LUT (T) BUDI SANTOSO', 750000.00, 'Selesai', 'Tune up mesin selesai dengan hasil yang memuaskan. Mesin lebih halus dan responsif setelah perawatan.', '2025-08-20 07:00:45', '2025-08-20 07:00:45', 3, 'Ringan', NULL, NULL),
(9, 3, '2025-08-27', 'Servis Berkala', NULL, NULL, 'Tim Maintenance', 400000.00, 'Selesai', 'Jenis: Servis AC\nDeskripsi: Bocor\nDipindahkan dari jadwal perawatan pada 27/08/2025 08:07', '2025-08-27 01:07:44', '2025-08-27 01:07:44', 1, 'Ringan', NULL, NULL),
(10, 1, '2025-08-27', 'Servis Berkala', NULL, NULL, 'Tim Maintenance', 0.00, 'Selesai', 'Jenis: Servis AC\nDeskripsi: Panas\nDipindahkan dari jadwal perawatan pada 27/08/2025 08:09', '2025-08-27 01:09:43', '2025-08-27 01:09:43', 1, 'Ringan', NULL, NULL),
(11, 1, '2025-08-27', 'Servis Berkala', NULL, NULL, 'Tim Maintenance', 0.00, 'Selesai', 'Jenis: Servis AC\nDeskripsi: ss\nDipindahkan dari jadwal perawatan pada 27/08/2025 08:11', '2025-08-27 01:11:11', '2025-08-27 01:11:11', 1, 'Ringan', NULL, NULL),
(12, 3, '2025-08-27', 'Penggantian Sparepart', NULL, 0, '2', 2000000.00, 'Selesai', 'Jenis: Ganti Ban\nDeskripsi: Ganti 4 ban kendaraan\nDipindahkan dari jadwal perawatan pada 27/08/2025 08:13', '2025-08-27 01:13:39', '2025-08-27 01:13:39', 3, 'Ringan', NULL, NULL),
(13, 19, '2025-08-27', 'Penggantian Sparepart', NULL, 0, 'Tim Maintenance', 20000.00, 'Selesai', 'Jenis: Ganti Ban\nDeskripsi: Ganti\nDipindahkan dari jadwal perawatan pada 27/08/2025 08:13', '2025-08-27 01:13:39', '2025-08-27 01:13:39', 1, 'Ringan', NULL, NULL),
(14, 1, '2025-08-27', 'Servis Berkala', NULL, NULL, 'Tim Maintenance', 0.00, 'Selesai', 'Jenis: Servis AC\nDeskripsi: sas\nDipindahkan dari jadwal perawatan pada 27/08/2025 08:36', '2025-08-27 01:36:17', '2025-08-27 01:36:17', 1, 'Ringan', NULL, NULL),
(15, 19, '2025-08-28', 'Servis Berkala', NULL, NULL, 'Tim Maintenance', 0.00, 'Selesai', 'Jenis: Servis AC\nDeskripsi: w\nDipindahkan dari jadwal perawatan pada 28/08/2025 14:07', '2025-08-28 07:07:00', '2025-08-28 07:07:00', 1, 'Ringan', 'Internal', NULL),
(16, 19, '2025-08-28', 'Lainnya', NULL, NULL, 'Tim Maintenance', 21.00, 'Selesai', 'Jenis: xz\nDipindahkan dari jadwal perawatan pada 28/08/2025 14:25', '2025-08-28 07:25:04', '2025-08-28 07:25:04', 1, 'Ringan', 'Internal', NULL),
(17, 1, '2025-08-29', 'Servis Berkala', NULL, NULL, 'Tim Maintenance', 200000.00, 'Selesai', 'Jenis: Servis AC\nDeskripsi: asd\nCatatan: wqwsa\nDipindahkan dari jadwal perawatan pada 29/08/2025 14:45', '2025-08-29 07:45:58', '2025-08-29 07:45:58', 1, 'Ringan', 'Internal', NULL),
(18, 19, '2025-08-29', 'Lainnya', NULL, NULL, 'Tim Maintenance', 100000.00, 'Selesai', 'Jenis: asd\nDeskripsi: qw\nDipindahkan dari jadwal perawatan pada 29/08/2025 16:12', '2025-08-29 09:12:46', '2025-08-29 09:12:46', 2, 'Ringan', 'Internal', NULL),
(19, 19, '2025-08-29', 'Lainnya', NULL, NULL, 'Tim Maintenance', 300000.00, 'Selesai', 'Jenis: qw\nDeskripsi: qww\nDipindahkan dari jadwal perawatan pada 29/08/2025 16:14', '2025-08-29 09:14:10', '2025-08-29 09:14:10', 2, 'Ringan', 'Internal', NULL),
(20, 19, '2025-08-29', 'Lainnya', NULL, NULL, 'Tim Maintenance', 3000000.00, 'Selesai', 'Jenis: ddd\nDeskripsi: dd\nDipindahkan dari jadwal perawatan pada 29/08/2025 16:53', '2025-08-29 09:53:59', '2025-08-29 09:53:59', 2, 'Ringan', 'Internal', NULL),
(21, 21, '2025-09-01', 'Lainnya', NULL, NULL, 'Tim Maintenance', 1500000.00, 'Selesai', 'Jenis: Tune Up\nDeskripsi: Tune up komprehensif\nDipindahkan dari jadwal perawatan pada 01/09/2025 12:09', '2025-09-01 05:09:35', '2025-09-01 05:09:35', 1, 'Ringan', 'Internal', NULL),
(22, 19, '2025-09-02', 'Servis Berkala', NULL, NULL, 'Tim Maintenance', 400000.00, 'Selesai', 'Jenis: Servis AC\nDeskripsi: dd\nDipindahkan dari jadwal perawatan pada 02/09/2025 10:15', '2025-09-02 03:15:42', '2025-09-02 03:15:42', 1, 'Ringan', 'Internal', NULL),
(23, 17, '2025-09-02', 'Penggantian Sparepart', NULL, NULL, 'Tim Maintenance', 300000.00, 'Selesai', 'Jenis: Ganti Oli\nDeskripsi: Penggantian oli mesin\nDipindahkan dari jadwal perawatan pada 02/09/2025 11:06', '2025-09-02 04:06:25', '2025-09-02 04:06:25', 1, 'Ringan', 'Internal', NULL),
(24, 19, '2025-09-02', 'Penggantian Sparepart', NULL, NULL, 'Tim Maintenance', 8000000.00, 'Selesai', 'Jenis: Ganti Ban\nDeskripsi: Ban\nDipindahkan dari jadwal perawatan pada 02/09/2025 11:52', '2025-09-02 04:52:58', '2025-09-02 04:52:58', 1, 'Ringan', 'Internal', NULL),
(25, 19, '2025-09-02', 'Servis Berkala', NULL, NULL, 'Tim Maintenance', 300000.00, 'Selesai', 'Jenis: Servis AC\nDeskripsi: aa\nDipindahkan dari jadwal perawatan pada 02/09/2025 12:30', '2025-09-02 05:30:51', '2025-09-02 05:30:51', 1, 'Ringan', 'Internal', NULL),
(26, 27, '2025-09-02', 'Lainnya', NULL, NULL, 'Tim Maintenance', 50000000.00, 'Selesai', 'Jenis: sss\nDeskripsi: sss\nDipindahkan dari jadwal perawatan pada 02/09/2025 12:30', '2025-09-02 05:30:56', '2025-09-04 03:17:19', 1, 'Ringan', 'Internal', NULL),
(27, 26, '2025-09-02', 'Lainnya', NULL, NULL, 'Tim Maintenance', 400000.00, 'Selesai', 'Jenis: a\nDeskripsi: a\nDipindahkan dari jadwal perawatan pada 02/09/2025 12:33', '2025-09-02 05:33:03', '2025-09-02 05:33:03', 1, 'Ringan', 'Internal', NULL),
(28, 26, '2025-09-02', 'Lainnya', NULL, NULL, 'Tim Maintenance', 400000.00, 'Selesai', 'Jenis: a\nDeskripsi: a\nDipindahkan dari jadwal perawatan pada 02/09/2025 12:33', '2025-09-02 05:33:51', '2025-09-02 05:33:51', 1, 'Ringan', 'Internal', NULL);

-- --------------------------------------------------------

--
-- Struktur dari tabel `riwayat_perbaikan`
--

CREATE TABLE `riwayat_perbaikan` (
  `id` int(11) NOT NULL,
  `kendaraan_id` int(11) NOT NULL,
  `tanggal_perbaikan` date NOT NULL,
  `jenis_perbaikan` varchar(100) NOT NULL,
  `deskripsi` text DEFAULT NULL,
  `deskripsi_perbaikan` varchar(50) NOT NULL,
  `keterangan` varchar(50) NOT NULL,
  `bengkel` varchar(100) DEFAULT NULL,
  `biaya` decimal(15,2) DEFAULT NULL,
  `teknisi` varchar(100) DEFAULT NULL,
  `status` enum('Dalam Proses','Selesai','Ditunda') DEFAULT 'Dalam Proses',
  `km_perbaikan` int(11) DEFAULT NULL,
  `spare_parts` text DEFAULT NULL,
  `catatan` text DEFAULT NULL,
  `bukti_nota` varchar(255) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `created_by` int(11) DEFAULT NULL,
  `updated_by` int(11) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data untuk tabel `riwayat_perbaikan`
--

INSERT INTO `riwayat_perbaikan` (`id`, `kendaraan_id`, `tanggal_perbaikan`, `jenis_perbaikan`, `deskripsi`, `deskripsi_perbaikan`, `keterangan`, `bengkel`, `biaya`, `teknisi`, `status`, `km_perbaikan`, `spare_parts`, `catatan`, `bukti_nota`, `created_at`, `updated_at`, `created_by`, `updated_by`) VALUES
(1, 1, '2025-08-15', 'Perbaikan Ban', 'Ganti oli mesin dan filter oli', '', '', 'Bengkel TNI', 350000.00, 'Bambang', 'Selesai', NULL, '', 'ww', NULL, '2025-08-21 03:29:17', '2025-08-29 09:57:23', NULL, 2),
(2, 3, '2025-08-10', 'Servis AC', 'Perbaikan sistem AC kendaraan', '', '', 'Bengkel Resmi', 750000.00, 'Andi', 'Selesai', 24000, NULL, NULL, NULL, '2025-08-21 03:29:17', '2025-08-21 03:29:17', NULL, NULL),
(3, 17, '2025-08-05', 'Perbaikan Rem', 'Ganti 4 ban kendaraan', '', '', 'Bengkel TNI', 1200000.00, 'Slamet', 'Selesai', NULL, '', 'we', NULL, '2025-08-21 03:29:17', '2025-08-29 09:57:58', NULL, 2),
(4, 19, '2025-08-29', 'Perbaikan Ban', 'bocor', '', '', '22', 30000.00, 'wad', 'Selesai', NULL, '', 'selesai', NULL, '2025-08-29 09:58:53', '2025-09-02 06:18:36', 2, 1),
(5, 1, '2025-09-01', 'Perbaikan Ban', 'bocor', '', '', 'saa', 30000.00, 'dan', 'Selesai', NULL, '', 'tambal', NULL, '2025-09-01 08:01:11', '2025-09-01 08:01:11', 1, NULL),
(6, 1, '2025-09-01', 'Perbaikan Transmisi', 'Persneling patah', '', '', 'dan', 3200000.00, 'das', 'Selesai', NULL, '', 'ganti baru', NULL, '2025-09-01 08:03:14', '2025-09-01 08:03:14', 1, NULL),
(7, 19, '2025-09-02', 'Perbaikan Mesin', 'sss', '', '', 'ww', 200000.00, 'ww', 'Selesai', 3000, '', 'Sbsb', NULL, '2025-09-02 06:19:33', '2025-09-08 08:57:18', 1, 1),
(8, 24, '2025-09-02', 'Perbaikan Ban', 'ww', '', '', 'ww', 300000.00, 'ww', 'Selesai', 22, '', 'ws', NULL, '2025-09-02 06:20:21', '2025-09-03 03:06:03', 1, 1),
(9, 24, '2025-09-02', 'Perbaikan Mesin', 'as', '', '', 'sasa', 290000.00, 'saa', 'Selesai', 322, '', 'as', NULL, '2025-09-02 06:25:56', '2025-09-03 03:06:15', 2, 1),
(10, 22, '2025-09-03', 'Perbaikan Mesin', 'ss', '', '', 'ss', 300000.00, 'ss', 'Selesai', 333, '', 'ss', NULL, '2025-09-03 02:37:36', '2025-09-03 03:05:33', 1, 1),
(11, 19, '2025-09-03', 'Perbaikan Ban', 'qdwfe', '', '', 'Sup', 344999.98, 'sdf', 'Selesai', 3220, '', 'as', NULL, '2025-09-03 13:50:00', '2025-09-04 08:07:39', 1, 1);

-- --------------------------------------------------------

--
-- Struktur dari tabel `role`
--

CREATE TABLE `role` (
  `id` int(11) NOT NULL,
  `nama_role` varchar(50) NOT NULL,
  `kode_role` varchar(20) NOT NULL,
  `deskripsi` text DEFAULT NULL,
  `level_akses` tinyint(1) NOT NULL DEFAULT 1 COMMENT '1=User, 2=Operator, 3=Admin',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Master data Role User';

--
-- Dumping data untuk tabel `role`
--

INSERT INTO `role` (`id`, `nama_role`, `kode_role`, `deskripsi`, `level_akses`, `created_at`, `updated_at`) VALUES
(1, 'Administrator', 'ADMIN', 'Full akses dan persetujuan', 3, '2025-08-20 11:00:08', '2025-09-04 03:41:34'),
(2, 'Operator', 'OPERATOR', 'Manajemen kendaraan dan pembuatan surat', 2, '2025-08-20 11:00:08', '2025-09-04 03:41:01'),
(3, 'User', 'USER', 'Akses terbatas ', 1, '2025-08-20 11:00:08', '2025-09-04 03:40:43');

-- --------------------------------------------------------

--
-- Struktur dari tabel `surat_tugas`
--

CREATE TABLE `surat_tugas` (
  `id` int(11) NOT NULL,
  `nomor_surat` varchar(50) NOT NULL,
  `tanggal_surat` date NOT NULL,
  `klasifikasi` varchar(20) DEFAULT 'Biasa',
  `lampiran` varchar(255) DEFAULT '-',
  `perihal` varchar(255) DEFAULT 'Permohonan peminjaman kendaraan dinas bus dan tenaga medis',
  `kepada_jabatan` varchar(100) DEFAULT 'Dandenma Mabes TNI',
  `kepada_tempat` varchar(50) DEFAULT 'Jakarta',
  `dasar_a` text DEFAULT 'Peraturan Panglima TNI Nomor 24 Tahun 2014 tentang Pengesahan Validasi Organisasi FKT dan;',
  `dasar_b` text DEFAULT 'Surat Perintah Kapusinfolahta TNI Nomor Sprin/97/XI/2023 tanggal 20 Oktober 2023 tentang Fungsi Pengadaan HUT ke-17 Pusinfolahta TNI dan;',
  `berangkat_dari` varchar(100) DEFAULT 'Pusinfolahta TNI',
  `waktu_berangkat` varchar(50) DEFAULT 'Pukul 05.00 WIB s.d selesai',
  `pejabat_ttd_jabatan` varchar(100) DEFAULT 'a.n Kepala Pusinfolahta TNI',
  `pejabat_ttd_sebagai` varchar(50) DEFAULT 'Waka,',
  `tembusan_1` varchar(100) DEFAULT 'Kapusinfolahta TNI',
  `tembusan_2` varchar(100) DEFAULT 'Asops Denma Mabes TNI',
  `tembusan_3` varchar(100) DEFAULT 'Dansetang Denma Mabes TNI',
  `tembusan_4` varchar(100) DEFAULT 'Dansakdok Denma Mabes TNI',
  `kendaraan_id` int(11) NOT NULL,
  `pengguna_id` int(11) NOT NULL,
  `tujuan` varchar(255) NOT NULL,
  `keperluan` text NOT NULL,
  `tanggal_berangkat` date NOT NULL,
  `tanggal_kembali` date DEFAULT NULL,
  `estimasi_km` int(11) DEFAULT NULL,
  `estimasi_bbm` decimal(8,2) DEFAULT NULL,
  `status` enum('Draft','Disetujui','Dalam Perjalanan','Selesai','Dibatalkan') NOT NULL DEFAULT 'Draft',
  `km_berangkat` int(11) DEFAULT NULL,
  `km_kembali` int(11) DEFAULT NULL,
  `bbm_terpakai` decimal(8,2) DEFAULT NULL,
  `laporan_perjalanan` text DEFAULT NULL,
  `pejabat_ttd` varchar(100) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `created_by` int(11) DEFAULT NULL,
  `updated_by` int(11) DEFAULT NULL,
  `dasar_list` text DEFAULT NULL,
  `tembusan_list` varchar(50) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci COMMENT='Tabel surat tugas perjalanan dinas';

--
-- Dumping data untuk tabel `surat_tugas`
--

INSERT INTO `surat_tugas` (`id`, `nomor_surat`, `tanggal_surat`, `klasifikasi`, `lampiran`, `perihal`, `kepada_jabatan`, `kepada_tempat`, `dasar_a`, `dasar_b`, `berangkat_dari`, `waktu_berangkat`, `pejabat_ttd_jabatan`, `pejabat_ttd_sebagai`, `tembusan_1`, `tembusan_2`, `tembusan_3`, `tembusan_4`, `kendaraan_id`, `pengguna_id`, `tujuan`, `keperluan`, `tanggal_berangkat`, `tanggal_kembali`, `estimasi_km`, `estimasi_bbm`, `status`, `km_berangkat`, `km_kembali`, `bbm_terpakai`, `laporan_perjalanan`, `pejabat_ttd`, `created_at`, `updated_at`, `created_by`, `updated_by`, `dasar_list`, `tembusan_list`) VALUES
(1, 'ST/001/VIII/2025', '2025-08-21', 'Biasa', '-', 'Permohonan', 'Dandenma Mabes TNI', 'Jakarta', NULL, NULL, 'Pusinfolahta TNI', 'Pukul 05.00 WIB s.d selesai', 'a.n Kepala Pusinfolahta TNI', 'Kepala,', 'Kapusinfolahta TNI', 'Asops Denma Mabes TNI', 'Dansetang Denma Mabes TNI', NULL, 1, 4, 'Bandung - Jawa Barat', 'Jalan jalan', '2025-08-21', '2025-08-21', 200, 30.00, 'Selesai', NULL, NULL, NULL, '0', 'Wawan Pujiatmoko', '2025-08-21 00:16:46', '2025-08-26 08:52:34', 3, 1, '[\"Peraturan Panglima TNI Nomor 24 Tahun 2014 tentang Pengesahan Validasi Organisasi FKT dan;\"]', '[\"Kapusinfolahta TNI\",\"Asops Denma Mabes TNI\",\"Dan'),
(4, 'ST/002/VIII/2025', '2025-08-21', 'Biasa', '-', 'Permohonan peminjaman kendaraan dinas bus dan tenaga medis', 'Dandenma Mabes TNI', 'Jakarta', NULL, NULL, 'Pusinfolahta TNI', 'Pukul 05.00 WIB s.d selesai', 'a.n Kepala Pusinfolahta TNI', 'Waka,', 'Kapusinfolahta TNI', 'Asops Denma Mabes TNI', 'Dansetang Denma Mabes TNI', 'Dan', 15, 2, 'Bandung - Jawa Barat', 'jalan', '2025-08-27', '2025-08-27', NULL, NULL, 'Selesai', NULL, NULL, NULL, '0', '0', '2025-08-21 01:08:47', '2025-08-28 00:26:44', 3, 1, '[\"Peraturan Panglima TNI Nomor 24 Tahun 2014 tentang Pengesahan Validasi Organisasi FKT dan;\"]', '[\"Kapusinfolahta TNI\",\"Asops Denma Mabes TNI\",\"Dan'),
(5, 'ST/003/VIII/2025', '2025-08-21', 'Biasa', '-', 'Pijam bus', 'Dandenma Mabes TNI', 'Jakarta', 'ss', 'adas', 'Pusinfolahta TNI', 'Pukul 05.00 WIB s.d selesai', 'a.n Kepala Pusinfolahta TNI', 'Waka,', 'Kapusinfolahta TNI', 'Asops Denma Mabes TNI', '', '', 17, 3, 'Kalimantan', 'Jalan', '2025-08-28', '2025-08-28', NULL, NULL, 'Selesai', NULL, NULL, NULL, 'sas', '0', '2025-08-21 02:03:49', '2025-09-03 00:39:15', 3, 1, '[\"Peraturan Panglima TNI Nomor 24 Tahun 2014 tentang Pengesahan Validasi Organisasi FKT dan;\",\"asda\",\"asdasdas\"]', '[\"Kapusinfolahta TNI\",\"Asops Denma Mabes TNI\",\"Dan'),
(8, 'ST/006/VIII/2025', '2025-08-26', 'Biasa', 'uploads/surat_tugas/1756185883_RANDIS_-_Sistem_Manajemen_Kendaraan_Dinas.pdf', 'Permohonan peminjaman kendaraan dinas bus dan tenaga medis', 'Dandenma Mabes TNI', 'Jakarta', '<br />; <b>Deprecated</b>:  htmlspecialchars(): Passing null to parameter #1 ($string) of type string is deprecated in <b>C:\\xampp\\htdocs\\randis\\pages\\surat_tugas.php</b> on line <b>843</b><br />', '<br />; <b>Deprecated</b>:  htmlspecialchars(): Passing null to parameter #1 ($string) of type string is deprecated in <b>C:\\xampp\\htdocs\\randis\\pages\\surat_tugas.php</b> on line <b>848</b><br />', 'Pusinfolahta TNI', 'Pukul 05.00 WIB s.d selesai', 'a.n Kepala Pusinfolahta TNI', 'Waka,', 'Kapusinfolahta TNI', 'Asops Denma Mabes TNI', '', '', 17, 3, 'Bogor', 'aaaaaaaaaaaaaasssssssss', '2025-08-30', '2025-08-30', NULL, NULL, 'Selesai', NULL, NULL, NULL, 's', 'Wawan Pujiatmoko', '2025-08-26 05:24:43', '2025-09-02 05:48:07', 1, 1, '[\"Peraturan Panglima TNI Nomor 24 Tahun 2014 tentang Pengesahan Validasi Organisasi FKT dan;\",\"sdada\",\"asdas\"]', ''),
(9, 'ST/007/VIII/2025', '2025-08-26', 'Biasa', '-', 'Pinjam Bus', 'Dandenma Mabes TNI', 'Jakarta', NULL, NULL, 'Pusinfolahta TNI', 'Pukul 05.00 WIB s.d selesai', 'a.n Kepala Pusinfolahta TNI', 'Waka,', NULL, NULL, NULL, NULL, 18, 3, 'Bogor', 'jjjjjjjjas asjasmas sajasnasj asasnn', '2025-08-26', '2025-08-26', NULL, NULL, 'Selesai', NULL, NULL, NULL, '', 'S. Ginting, S.Kom., MMSI., M.Tr.Hankam', '2025-08-26 05:26:45', '2025-08-27 00:04:32', 1, 1, '[\"Peraturan Panglima TNI Nomor 24 Tahun 2014 tentang Pengesahan Validasi Organisasi FKT dan;\",\"asdasd\",\"dasda\"]', ''),
(10, 'ST/008/VIII/2025', '2025-08-26', 'Biasa', '-', 'pijam', 'Dandenma Mabes TNI', 'Jakarta', 'Peraturan Panglima TNI Nomor 24 Tahun 2014 tentang Pengesahan Validasi Organisasi FKT dan', '', 'Pusinfolahta TNI', 'Pukul 05.00 WIB s.d selesai', 'a.n Kepala Pusinfolahta TNI', 'Waka,', 'Kapusinfolahta TNI', 'Asops Denma Mabes TNI', 'Dansetang Denma Mabes TNI', 'Dansakdok Denma Mabes TNI', 18, 3, 'Bogor', 'Makan', '2025-09-03', '2025-09-03', 300, 20.00, 'Selesai', NULL, NULL, NULL, '', 'S. Ginting, S.Kom., MMSI., M.Tr.Hankam', '2025-08-26 05:37:14', '2025-09-08 01:33:23', 1, 1, '[\"Peraturan Panglima TNI Nomor 24 Tahun 2014 tentang Pengesahan Validasi Organisasi FKT dan;\",\"sasasd asda\",\"asdas asdasd\",\"asd\"]', ''),
(11, 'ST/009/VIII/2025', '2025-08-26', 'Biasa', '-', 'Pnjam bus', 'Dandenma Mabes TNI', 'Jakarta', 'Peraturan Panglima TNI Nomor 24 Tahun 2014 tentang Pengesahan Validasi Organisasi FKT dan;', '', 'Pusinfolahta TNI', 'Pukul 05.00 WIB s.d selesai', 'a.n Kepala Pusinfolahta TNI', 'Waka,', 'Kapusinfolahta TNI', 'Dasns', 'adas', '', 23, 3, 'Bogor', 'Melaksanakan keberangkatan ke satker', '2025-08-27', '2025-08-27', 300, 20.00, 'Selesai', NULL, NULL, NULL, '', 'S. Ginting, S.Kom., MMSI., M.Tr.Hankam', '2025-08-26 07:31:49', '2025-08-28 06:50:56', 1, 1, '[\"Peraturan Panglima TNI Nomor 24 Tahun 2014 tentang Pengesahan Validasi Organisasi FKT dan;\",\"asdas\",\"dasdaw\"]', '[\"Kapusinfolahta TNI\",\"Dasns\",\"adas\"]'),
(12, 'ST/010/VIII/2025', '2025-08-26', 'Biasa', '-', 'Pinjam', 'Dandenma Mabes TNI', 'Jakarta', 'Peraturan Panglima TNI Nomor 24 Tahun 2014 tentang Pengesahan Validasi Organisasi FKT dan', '', 'Pusinfolahta TNI', 'Pukul 05.00 WIB s.d selesai', 'a.n Kepala Pusinfolahta TNI', 'Waka,', 'Kapusinfolahta TNI', '', '', '', 17, 3, 'Bandung - Jawa Barat', 'Sehubungan dasar di atas, dengan hormat diajukan permohonan peminjaman 1 (satu) unit Avanza\r\n SUV', '2025-09-01', '2025-09-01', NULL, NULL, 'Selesai', NULL, NULL, NULL, '', 'S. Ginting, S.Kom., MMSI., M.Tr.Hankam', '2025-08-26 08:14:11', '2025-09-02 06:27:26', 1, 2, '[\"Peraturan Panglima TNI Nomor 24 Tahun 2014 tentang Pengesahan Validasi Organisasi FKT dan;\"]', '[\"Kapusinfolahta TNI\"]'),
(13, 'ST/011/VIII/2025', '2025-08-28', 'Biasa', '-', 'as', 'Dandenma Mabes TNI', 'Jakarta', 'Peraturan Panglima TNI', 'Surat Perintah Kapusinfolahta', 'Pusinfolahta TNI', 'Pukul 05.00 WIB s.d selesai', 'a.n Kepala Pusinfolahta TNI', 'Waka,', 'Kapusinfolahta TNI', 'Asops Denma Mabes TNI', 'Dansetang Denma Mabes TNI', 'Dansakdok Denma Mabes TNI', 19, 5, 'Bandung - Jawa Barat', 'asadasda', '2025-08-27', '2025-08-27', NULL, NULL, 'Selesai', NULL, NULL, NULL, '', 'S. Ginting, S.Kom., MMSI., M.Tr.Hankam', '2025-08-28 02:40:52', '2025-09-02 05:48:31', 1, 1, NULL, ''),
(17, 'ST/001/IX/2025', '2025-09-01', 'Biasa', '-', 'Permohonan', 'Dandenma Mabes TNI', 'Jakarta', 'Peraturan Panglima TNI Nomor 24 Tahun 2014 tentang Pengesahan Validasi Organisasi FKT dan', '', 'Pusinfolahta TNI', 'Pukul 05.00 WIB s.d selesai', 'a.n Kepala Pusinfolahta TNI', 'Waka,', 'Kapusinfolahta TNI', 'Asops Denma Mabes TNI', 'Dansetang Denma Mabes TNI', '', 19, 5, 'Kemhan RI - Jakarta Pusat', 'Dengan surat ini', '2025-09-24', '2025-09-24', NULL, NULL, 'Selesai', NULL, NULL, NULL, '', 'S. Ginting, S.Kom., MMSI., M.Tr.Hankam', '2025-09-01 00:15:21', '2025-09-02 07:01:24', 1, 1, NULL, ''),
(18, 'ST/002/IX/2025', '2025-09-01', 'Biasa', '-', 'Permohonan bantuan medis', 'Dandenma Mabes TNI', 'Jakarta', 'Peraturan Panglima TNI Nomor 24 Tahun 2014 tentang Pengesahan Validasi Organisasi FKT dan', '', 'Pusinfolahta TNI', 'Pukul 05.00 WIB s.d selesai', 'a.n Kepala Pusinfolahta TNI', 'Waka,', 'Kapusinfolahta TNI', 'Asops Denma Mabes TNI', 'Dansakdok Denma Mabes TNI', '', 19, 5, 'Kemhan RI - Jakarta Pusat', 'Dengan surat ini', '2025-09-25', '2025-09-25', NULL, NULL, 'Selesai', NULL, NULL, NULL, '', 'S. Ginting, S.Kom., MMSI., M.Tr.Hankam', '2025-09-01 00:16:15', '2025-09-02 07:02:37', 1, 1, NULL, ''),
(19, 'ST/003/IX/2025', '2025-09-02', 'Biasa', '-', 'Permohonan peminjaman kendaraan dinas bus dan tenaga medis', 'Dandenma Mabes TNI', 'Jakarta', 'Peraturan Panglima TNI Nomor 24 Tahun 2014 tentang Pengesahan Validasi Organisasi FKT dan', '', 'Pusinfolahta TNI', 'Pukul 05.00 WIB s.d selesai', 'a.n Kepala Pusinfolahta TNI', 'Waka,', 'Kapusinfolahta TNI', 'Asops Denma Mabes TNI', 'Dansetang Denma Mabes TNI', '', 19, 11, 'Bogor', 'ww', '2025-09-02', '2025-09-03', 2222, 31.00, 'Selesai', 322, 443, 40.00, 'ww', 'S. Ginting, S.Kom., MMSI., M.Tr.Hankam', '2025-09-02 05:49:52', '2025-09-04 01:56:19', 1, 1, NULL, ''),
(20, 'ST/004/IX/2025', '2025-09-04', 'Biasa', '-', 'Permohonan', 'Dandenma TNI', 'Jakarta', 'Peraturan Panglima TNI Nomor 24 Tahun 2014 tentang Pengesahan Validasi Organisasi FKT dan', '', 'Pusinfolahta TNI', 'Pukul 05.00 WIB s.d selesai', 'a.n Kepala Pusinfolahta TNI', 'Waka,', 'Kapusinfolahta TNI', 'Asops Denma Mabes TNI', 'Dansakdok Denma Mabes TNI', '', 19, 12, 'dd', 'e', '2025-09-08', '2025-09-10', 2002, 300.00, 'Disetujui', NULL, NULL, NULL, '', 'S. Ginting, S.Kom., MMSI., M.Tr.Hankam', '2025-09-04 01:57:46', '2025-09-08 07:33:22', 1, 1, NULL, '');

-- --------------------------------------------------------

--
-- Struktur dari tabel `user_account`
--

CREATE TABLE `user_account` (
  `id` int(11) NOT NULL,
  `pengguna_id` int(11) NOT NULL,
  `username` varchar(50) NOT NULL,
  `password` varchar(255) NOT NULL,
  `role_id` int(11) NOT NULL,
  `last_login` timestamp NULL DEFAULT NULL,
  `status` enum('Aktif','Tidak Aktif','Suspended') NOT NULL DEFAULT 'Aktif',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data untuk tabel `user_account`
--

INSERT INTO `user_account` (`id`, `pengguna_id`, `username`, `password`, `role_id`, `last_login`, `status`, `created_at`, `updated_at`) VALUES
(1, 1, 'admin', '$2y$10$fUKpldu9OqdgKbne6tkoeOi3.SJS9NZ0Ee8edekGEI4leiZN7sQ9e', 1, '2025-08-19 01:56:03', 'Aktif', '2025-08-13 01:28:52', '2025-08-21 05:17:29'),
(2, 2, 'operator', '$2y$10$/l7mI5CTc/Z4UJ4RylpXwecclVY8QK0ZMzUqdaw9ORLNHU9FQPuCW', 2, '2025-08-19 07:59:37', 'Aktif', '2025-08-13 01:28:52', '2025-08-21 05:17:29'),
(3, 3, 'user', '$2y$10$4BaUKMt.HNmDD6Z3zhzQ2eEQNvTbVlCGj4I6QuzJSZmIwWZ1bLjcy', 3, '2025-08-19 07:07:09', 'Aktif', '2025-08-13 01:28:52', '2025-08-21 05:17:29'),
(4, 4, 'teknisi', '$2y$10$fUKpldu9OqdgKbne6tkoeOi3.SJS9NZ0Ee8edekGEI4leiZN7sQ9e', 2, NULL, 'Aktif', '2025-08-13 01:28:52', '2025-08-30 09:47:05'),
(5, 5, 'driver', '$2y$10$fUKpldu9OqdgKbne6tkoeOi3.SJS9NZ0Ee8edekGEI4leiZN7sQ9e', 3, '2025-08-19 08:01:00', 'Aktif', '2025-08-13 01:28:52', '2025-08-30 09:47:10'),
(6, 10, 'pns', '$2y$10$uh/UTqoSK/8aoZcoR2mlA.4r2EB2AXP.jdXxU4Zfscru/IU9RX.VO', 3, NULL, 'Aktif', '2025-09-01 03:42:47', '2025-09-04 01:54:07'),
(7, 11, 'tni', '$2y$10$6JMxmebW.y4rSOIb66e2DexFgxh5NN1fFvALlqEpT8UpSRNmkRexC', 3, NULL, 'Aktif', '2025-09-01 03:58:15', '2025-09-01 03:58:15'),
(8, 12, 'asn', '$2y$10$Emkql.RE8Jbv6sVuTVcX3.8ZhbyH2Nj/DLtJ/llSviOSqd/3V/Xba', 3, NULL, 'Aktif', '2025-09-04 01:55:17', '2025-09-04 01:55:17');

-- --------------------------------------------------------

--
-- Struktur dari tabel `user_activity`
--

CREATE TABLE `user_activity` (
  `id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `activity_type` varchar(50) NOT NULL,
  `description` text DEFAULT NULL,
  `ip_address` varchar(45) DEFAULT NULL,
  `user_agent` text DEFAULT NULL,
  `session_id` varchar(128) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='User activity audit log';

--
-- Dumping data untuk tabel `user_activity`
--

INSERT INTO `user_activity` (`id`, `user_id`, `activity_type`, `description`, `ip_address`, `user_agent`, `session_id`, `created_at`) VALUES
(1, 1, 'LOGOUT', 'User keluar dari sistem', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/138.0.0.0 Safari/537.36', NULL, '2025-08-14 01:11:40'),
(2, 3, 'LOGIN', 'User berhasil login ke sistem', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/138.0.0.0 Safari/537.36', NULL, '2025-08-14 01:12:07'),
(3, 3, 'LOGOUT', 'User keluar dari sistem', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/138.0.0.0 Safari/537.36', NULL, '2025-08-14 01:13:27'),
(4, 2, 'LOGIN', 'User berhasil login ke sistem', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/138.0.0.0 Safari/537.36', NULL, '2025-08-14 01:13:34'),
(5, 2, 'LOGOUT', 'User keluar dari sistem', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/138.0.0.0 Safari/537.36', NULL, '2025-08-14 01:14:58');

-- --------------------------------------------------------

--
-- Stand-in struktur untuk tampilan `v_active_loans`
-- (Lihat di bawah untuk tampilan aktual)
--
CREATE TABLE `v_active_loans` (
`id` int(11)
,`nomor_surat` varchar(50)
,`no_polisi` varchar(20)
,`peminjam` varchar(100)
,`tanggal_mulai` datetime
,`tanggal_selesai` datetime
,`tujuan` varchar(255)
,`status` enum('Pending','Approved','Ongoing','Completed','Cancelled','Rejected')
,`days_remaining` int(7)
);

-- --------------------------------------------------------

--
-- Struktur untuk view `v_active_loans`
--
DROP TABLE IF EXISTS `v_active_loans`;

CREATE ALGORITHM=UNDEFINED DEFINER=`root`@`localhost` SQL SECURITY DEFINER VIEW `v_active_loans`  AS SELECT `pk`.`id` AS `id`, `pk`.`nomor_surat` AS `nomor_surat`, `k`.`no_polisi` AS `no_polisi`, `p`.`nama_lengkap` AS `peminjam`, `pk`.`tanggal_mulai` AS `tanggal_mulai`, `pk`.`tanggal_selesai` AS `tanggal_selesai`, `pk`.`tujuan` AS `tujuan`, `pk`.`status` AS `status`, to_days(`pk`.`tanggal_selesai`) - to_days(curdate()) AS `days_remaining` FROM ((`peminjaman_kendaraan` `pk` join `kendaraan` `k` on(`pk`.`kendaraan_id` = `k`.`id`)) join `pengguna` `p` on(`pk`.`peminjam_id` = `p`.`id`)) WHERE `pk`.`status` in ('Approved','Ongoing') ;

--
-- Indexes for dumped tables
--

--
-- Indeks untuk tabel `dokumen_kendaraan`
--
ALTER TABLE `dokumen_kendaraan`
  ADD PRIMARY KEY (`id`),
  ADD KEY `kendaraan_id` (`kendaraan_id`),
  ADD KEY `created_by` (`created_by`),
  ADD KEY `updated_by` (`updated_by`);

--
-- Indeks untuk tabel `jadwal_perawatan`
--
ALTER TABLE `jadwal_perawatan`
  ADD PRIMARY KEY (`id`),
  ADD KEY `kendaraan_id` (`kendaraan_id`),
  ADD KEY `created_by` (`created_by`),
  ADD KEY `updated_by` (`updated_by`),
  ADD KEY `teknisi_id` (`teknisi_id`),
  ADD KEY `idx_jadwal_status_tanggal` (`status`,`tanggal_perawatan`),
  ADD KEY `idx_jadwal_kendaraan_status` (`kendaraan_id`,`status`);

--
-- Indeks untuk tabel `kendaraan`
--
ALTER TABLE `kendaraan`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `no_polisi` (`no_polisi`),
  ADD UNIQUE KEY `no_rangka` (`no_rangka`),
  ADD UNIQUE KEY `no_mesin` (`no_mesin`),
  ADD UNIQUE KEY `no_reg` (`no_reg`),
  ADD UNIQUE KEY `no_stnk` (`no_stnk`);

--
-- Indeks untuk tabel `kesatuan`
--
ALTER TABLE `kesatuan`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uk_kesatuan_kode` (`kode_kesatuan`),
  ADD KEY `fk_kesatuan_korps` (`korps_id`),
  ADD KEY `fk_kesatuan_parent` (`parent_id`),
  ADD KEY `idx_kesatuan_level` (`level_kesatuan`);

--
-- Indeks untuk tabel `korps`
--
ALTER TABLE `korps`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uk_korps_kode` (`kode_korps`),
  ADD KEY `fk_korps_matra` (`matra_id`),
  ADD KEY `idx_korps_nama` (`nama_korps`);

--
-- Indeks untuk tabel `log_aktivitas`
--
ALTER TABLE `log_aktivitas`
  ADD PRIMARY KEY (`id`),
  ADD KEY `user_id` (`user_id`),
  ADD KEY `activity_type` (`activity_type`),
  ADD KEY `created_at` (`created_at`);

--
-- Indeks untuk tabel `log_bahan_bakar`
--
ALTER TABLE `log_bahan_bakar`
  ADD PRIMARY KEY (`id`),
  ADD KEY `kendaraan_id` (`kendaraan_id`),
  ADD KEY `user_id` (`user_id`),
  ADD KEY `idx_bbm_kendaraan_tanggal` (`kendaraan_id`,`tanggal_isi`),
  ADD KEY `idx_bbm_tanggal` (`tanggal_isi`);

--
-- Indeks untuk tabel `matra`
--
ALTER TABLE `matra`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uk_matra_kode` (`kode_matra`),
  ADD KEY `idx_matra_nama` (`nama_matra`);

--
-- Indeks untuk tabel `notifikasi`
--
ALTER TABLE `notifikasi`
  ADD PRIMARY KEY (`id`),
  ADD KEY `fk_notifikasi_user` (`user_id`),
  ADD KEY `fk_notifikasi_role` (`role_id`),
  ADD KEY `idx_notifikasi_type` (`type`),
  ADD KEY `idx_notifikasi_category` (`category`),
  ADD KEY `idx_notifikasi_read` (`read_at`),
  ADD KEY `idx_notifikasi_created` (`created_at`);

--
-- Indeks untuk tabel `peminjaman_kendaraan`
--
ALTER TABLE `peminjaman_kendaraan`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uk_peminjaman_nomor` (`nomor_surat`),
  ADD KEY `fk_peminjaman_kendaraan` (`kendaraan_id`),
  ADD KEY `fk_peminjaman_peminjam` (`peminjam_id`),
  ADD KEY `fk_peminjaman_approval` (`approval_by`),
  ADD KEY `fk_peminjaman_driver` (`driver_id`),
  ADD KEY `idx_peminjaman_status` (`status`),
  ADD KEY `idx_peminjaman_tanggal` (`tanggal_mulai`,`tanggal_selesai`),
  ADD KEY `idx_peminjaman_date_range` (`tanggal_mulai`,`tanggal_selesai`,`status`);

--
-- Indeks untuk tabel `peminjaman_terjadwal`
--
ALTER TABLE `peminjaman_terjadwal`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_pemohon` (`pemohon_id`),
  ADD KEY `idx_kendaraan` (`kendaraan_id`),
  ADD KEY `idx_status` (`status`);

--
-- Indeks untuk tabel `pengguna`
--
ALTER TABLE `pengguna`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `nrp_nip` (`nrp_nip`);

--
-- Indeks untuk tabel `pengguna_kendaraan`
--
ALTER TABLE `pengguna_kendaraan`
  ADD PRIMARY KEY (`id`),
  ADD KEY `pengguna_id` (`pengguna_id`),
  ADD KEY `kendaraan_id` (`kendaraan_id`);

--
-- Indeks untuk tabel `riwayat_pemakaian`
--
ALTER TABLE `riwayat_pemakaian`
  ADD PRIMARY KEY (`id`),
  ADD KEY `kendaraan_id` (`kendaraan_id`),
  ADD KEY `user_id` (`user_id`),
  ADD KEY `driver_id` (`driver_id`);

--
-- Indeks untuk tabel `riwayat_perawatan`
--
ALTER TABLE `riwayat_perawatan`
  ADD PRIMARY KEY (`id`),
  ADD KEY `kendaraan_id` (`kendaraan_id`),
  ADD KEY `created_by` (`created_by`),
  ADD KEY `idx_riwayat_kendaraan_tanggal` (`kendaraan_id`,`tanggal_perawatan`),
  ADD KEY `idx_riwayat_status` (`status`),
  ADD KEY `fk_riwayat_teknisi` (`teknisi_id`);

--
-- Indeks untuk tabel `riwayat_perbaikan`
--
ALTER TABLE `riwayat_perbaikan`
  ADD PRIMARY KEY (`id`),
  ADD KEY `kendaraan_id` (`kendaraan_id`),
  ADD KEY `fk_perbaikan_created_by` (`created_by`),
  ADD KEY `fk_perbaikan_updated_by` (`updated_by`);

--
-- Indeks untuk tabel `role`
--
ALTER TABLE `role`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uk_role_kode` (`kode_role`),
  ADD KEY `idx_role_level` (`level_akses`);

--
-- Indeks untuk tabel `surat_tugas`
--
ALTER TABLE `surat_tugas`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `nomor_surat` (`nomor_surat`),
  ADD KEY `kendaraan_id` (`kendaraan_id`),
  ADD KEY `pengguna_id` (`pengguna_id`),
  ADD KEY `created_by` (`created_by`),
  ADD KEY `updated_by` (`updated_by`),
  ADD KEY `idx_tanggal_surat` (`tanggal_surat`),
  ADD KEY `idx_status` (`status`);

--
-- Indeks untuk tabel `user_account`
--
ALTER TABLE `user_account`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `username` (`username`),
  ADD UNIQUE KEY `pengguna_id` (`pengguna_id`),
  ADD KEY `role_id` (`role_id`);

--
-- Indeks untuk tabel `user_activity`
--
ALTER TABLE `user_activity`
  ADD PRIMARY KEY (`id`),
  ADD KEY `fk_activity_user` (`user_id`),
  ADD KEY `idx_activity_type` (`activity_type`),
  ADD KEY `idx_activity_date` (`created_at`);

--
-- AUTO_INCREMENT untuk tabel yang dibuang
--

--
-- AUTO_INCREMENT untuk tabel `dokumen_kendaraan`
--
ALTER TABLE `dokumen_kendaraan`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=11;

--
-- AUTO_INCREMENT untuk tabel `jadwal_perawatan`
--
ALTER TABLE `jadwal_perawatan`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=52;

--
-- AUTO_INCREMENT untuk tabel `kendaraan`
--
ALTER TABLE `kendaraan`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=28;

--
-- AUTO_INCREMENT untuk tabel `kesatuan`
--
ALTER TABLE `kesatuan`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=5;

--
-- AUTO_INCREMENT untuk tabel `korps`
--
ALTER TABLE `korps`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=7;

--
-- AUTO_INCREMENT untuk tabel `log_aktivitas`
--
ALTER TABLE `log_aktivitas`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=327;

--
-- AUTO_INCREMENT untuk tabel `log_bahan_bakar`
--
ALTER TABLE `log_bahan_bakar`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=23;

--
-- AUTO_INCREMENT untuk tabel `matra`
--
ALTER TABLE `matra`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=5;

--
-- AUTO_INCREMENT untuk tabel `notifikasi`
--
ALTER TABLE `notifikasi`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=66;

--
-- AUTO_INCREMENT untuk tabel `peminjaman_kendaraan`
--
ALTER TABLE `peminjaman_kendaraan`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=17;

--
-- AUTO_INCREMENT untuk tabel `peminjaman_terjadwal`
--
ALTER TABLE `peminjaman_terjadwal`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=6;

--
-- AUTO_INCREMENT untuk tabel `pengguna`
--
ALTER TABLE `pengguna`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=13;

--
-- AUTO_INCREMENT untuk tabel `pengguna_kendaraan`
--
ALTER TABLE `pengguna_kendaraan`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=4;

--
-- AUTO_INCREMENT untuk tabel `riwayat_pemakaian`
--
ALTER TABLE `riwayat_pemakaian`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=36;

--
-- AUTO_INCREMENT untuk tabel `riwayat_perawatan`
--
ALTER TABLE `riwayat_perawatan`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=29;

--
-- AUTO_INCREMENT untuk tabel `riwayat_perbaikan`
--
ALTER TABLE `riwayat_perbaikan`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=12;

--
-- AUTO_INCREMENT untuk tabel `role`
--
ALTER TABLE `role`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=4;

--
-- AUTO_INCREMENT untuk tabel `surat_tugas`
--
ALTER TABLE `surat_tugas`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=21;

--
-- AUTO_INCREMENT untuk tabel `user_account`
--
ALTER TABLE `user_account`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=9;

--
-- AUTO_INCREMENT untuk tabel `user_activity`
--
ALTER TABLE `user_activity`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=6;

--
-- Ketidakleluasaan untuk tabel pelimpahan (Dumped Tables)
--

--
-- Ketidakleluasaan untuk tabel `dokumen_kendaraan`
--
ALTER TABLE `dokumen_kendaraan`
  ADD CONSTRAINT `fk_dokumen_created_by` FOREIGN KEY (`created_by`) REFERENCES `pengguna` (`id`) ON DELETE SET NULL ON UPDATE CASCADE,
  ADD CONSTRAINT `fk_dokumen_kendaraan` FOREIGN KEY (`kendaraan_id`) REFERENCES `kendaraan` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
  ADD CONSTRAINT `fk_dokumen_updated_by` FOREIGN KEY (`updated_by`) REFERENCES `pengguna` (`id`) ON DELETE SET NULL ON UPDATE CASCADE;

--
-- Ketidakleluasaan untuk tabel `jadwal_perawatan`
--
ALTER TABLE `jadwal_perawatan`
  ADD CONSTRAINT `fk_jadwal_created_by` FOREIGN KEY (`created_by`) REFERENCES `pengguna` (`id`) ON DELETE SET NULL ON UPDATE CASCADE,
  ADD CONSTRAINT `fk_jadwal_kendaraan` FOREIGN KEY (`kendaraan_id`) REFERENCES `kendaraan` (`id`) ON UPDATE CASCADE,
  ADD CONSTRAINT `fk_jadwal_teknisi` FOREIGN KEY (`teknisi_id`) REFERENCES `pengguna` (`id`) ON DELETE SET NULL ON UPDATE CASCADE,
  ADD CONSTRAINT `fk_jadwal_updated_by` FOREIGN KEY (`updated_by`) REFERENCES `pengguna` (`id`) ON DELETE SET NULL ON UPDATE CASCADE;

--
-- Ketidakleluasaan untuk tabel `kesatuan`
--
ALTER TABLE `kesatuan`
  ADD CONSTRAINT `fk_kesatuan_korps` FOREIGN KEY (`korps_id`) REFERENCES `korps` (`id`) ON UPDATE CASCADE,
  ADD CONSTRAINT `fk_kesatuan_parent` FOREIGN KEY (`parent_id`) REFERENCES `kesatuan` (`id`) ON UPDATE CASCADE;

--
-- Ketidakleluasaan untuk tabel `korps`
--
ALTER TABLE `korps`
  ADD CONSTRAINT `fk_korps_matra` FOREIGN KEY (`matra_id`) REFERENCES `matra` (`id`) ON UPDATE CASCADE;

--
-- Ketidakleluasaan untuk tabel `log_aktivitas`
--
ALTER TABLE `log_aktivitas`
  ADD CONSTRAINT `fk_log_user` FOREIGN KEY (`user_id`) REFERENCES `pengguna` (`id`) ON DELETE CASCADE ON UPDATE CASCADE;

--
-- Ketidakleluasaan untuk tabel `log_bahan_bakar`
--
ALTER TABLE `log_bahan_bakar`
  ADD CONSTRAINT `fk_bbm_kendaraan` FOREIGN KEY (`kendaraan_id`) REFERENCES `kendaraan` (`id`) ON UPDATE CASCADE,
  ADD CONSTRAINT `fk_bbm_user` FOREIGN KEY (`user_id`) REFERENCES `pengguna` (`id`) ON UPDATE CASCADE;

--
-- Ketidakleluasaan untuk tabel `notifikasi`
--
ALTER TABLE `notifikasi`
  ADD CONSTRAINT `fk_notifikasi_pengguna` FOREIGN KEY (`user_id`) REFERENCES `pengguna` (`id`) ON DELETE SET NULL ON UPDATE CASCADE,
  ADD CONSTRAINT `fk_notifikasi_role` FOREIGN KEY (`role_id`) REFERENCES `role` (`id`) ON DELETE CASCADE ON UPDATE CASCADE;

--
-- Ketidakleluasaan untuk tabel `peminjaman_kendaraan`
--
ALTER TABLE `peminjaman_kendaraan`
  ADD CONSTRAINT `fk_peminjaman_approval_pengguna` FOREIGN KEY (`approval_by`) REFERENCES `pengguna` (`id`) ON DELETE SET NULL ON UPDATE CASCADE,
  ADD CONSTRAINT `fk_peminjaman_driver` FOREIGN KEY (`driver_id`) REFERENCES `pengguna` (`id`) ON UPDATE CASCADE,
  ADD CONSTRAINT `fk_peminjaman_kendaraan` FOREIGN KEY (`kendaraan_id`) REFERENCES `kendaraan` (`id`) ON UPDATE CASCADE,
  ADD CONSTRAINT `fk_peminjaman_peminjam` FOREIGN KEY (`peminjam_id`) REFERENCES `pengguna` (`id`) ON UPDATE CASCADE;

--
-- Ketidakleluasaan untuk tabel `riwayat_pemakaian`
--
ALTER TABLE `riwayat_pemakaian`
  ADD CONSTRAINT `fk_pemakaian_driver` FOREIGN KEY (`driver_id`) REFERENCES `pengguna` (`id`) ON DELETE SET NULL ON UPDATE CASCADE,
  ADD CONSTRAINT `fk_pemakaian_kendaraan` FOREIGN KEY (`kendaraan_id`) REFERENCES `kendaraan` (`id`) ON UPDATE CASCADE,
  ADD CONSTRAINT `fk_pemakaian_user` FOREIGN KEY (`user_id`) REFERENCES `pengguna` (`id`) ON UPDATE CASCADE;

--
-- Ketidakleluasaan untuk tabel `riwayat_perawatan`
--
ALTER TABLE `riwayat_perawatan`
  ADD CONSTRAINT `fk_riwayat_created_by` FOREIGN KEY (`created_by`) REFERENCES `pengguna` (`id`) ON DELETE SET NULL ON UPDATE CASCADE,
  ADD CONSTRAINT `fk_riwayat_kendaraan` FOREIGN KEY (`kendaraan_id`) REFERENCES `kendaraan` (`id`) ON UPDATE CASCADE,
  ADD CONSTRAINT `fk_riwayat_teknisi` FOREIGN KEY (`teknisi_id`) REFERENCES `pengguna` (`id`) ON DELETE SET NULL ON UPDATE CASCADE;

--
-- Ketidakleluasaan untuk tabel `riwayat_perbaikan`
--
ALTER TABLE `riwayat_perbaikan`
  ADD CONSTRAINT `fk_perbaikan_created_by` FOREIGN KEY (`created_by`) REFERENCES `pengguna` (`id`) ON DELETE SET NULL ON UPDATE CASCADE,
  ADD CONSTRAINT `fk_perbaikan_kendaraan` FOREIGN KEY (`kendaraan_id`) REFERENCES `kendaraan` (`id`) ON UPDATE CASCADE,
  ADD CONSTRAINT `fk_perbaikan_updated_by` FOREIGN KEY (`updated_by`) REFERENCES `pengguna` (`id`) ON DELETE SET NULL ON UPDATE CASCADE;

--
-- Ketidakleluasaan untuk tabel `surat_tugas`
--
ALTER TABLE `surat_tugas`
  ADD CONSTRAINT `fk_surat_created_by` FOREIGN KEY (`created_by`) REFERENCES `pengguna` (`id`) ON DELETE SET NULL ON UPDATE CASCADE,
  ADD CONSTRAINT `fk_surat_kendaraan` FOREIGN KEY (`kendaraan_id`) REFERENCES `kendaraan` (`id`) ON UPDATE CASCADE,
  ADD CONSTRAINT `fk_surat_pengguna` FOREIGN KEY (`pengguna_id`) REFERENCES `pengguna` (`id`) ON UPDATE CASCADE,
  ADD CONSTRAINT `fk_surat_updated_by` FOREIGN KEY (`updated_by`) REFERENCES `pengguna` (`id`) ON DELETE SET NULL ON UPDATE CASCADE;

--
-- Ketidakleluasaan untuk tabel `user_account`
--
ALTER TABLE `user_account`
  ADD CONSTRAINT `fk_account_pengguna` FOREIGN KEY (`pengguna_id`) REFERENCES `pengguna` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
  ADD CONSTRAINT `fk_account_role` FOREIGN KEY (`role_id`) REFERENCES `role` (`id`) ON UPDATE CASCADE;

--
-- Ketidakleluasaan untuk tabel `user_activity`
--
ALTER TABLE `user_activity`
  ADD CONSTRAINT `fk_activity_user` FOREIGN KEY (`user_id`) REFERENCES `user_account_old` (`id`) ON DELETE CASCADE ON UPDATE CASCADE;
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
