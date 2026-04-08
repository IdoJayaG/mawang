-- phpMyAdmin SQL Dump
-- version 5.2.1
-- https://www.phpmyadmin.net/
--
-- Host: 127.0.0.1
-- Waktu pembuatan: 30 Okt 2025 pada 07.00
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
  `estimasi_biaya` int(20) DEFAULT NULL COMMENT 'Estimasi biaya perawatan',
  `status` enum('Terjadwal','Dalam Proses','Selesai','Terlewat','Dibatalkan','Ditunda') DEFAULT 'Terjadwal',
  `prioritas` varchar(30) DEFAULT NULL COMMENT 'Tingkat prioritas: Normal, Tinggi, Urgent',
  `reminder_sent` tinyint(1) DEFAULT 0,
  `tanggal_selesai` date DEFAULT NULL,
  `biaya_aktual` int(20) DEFAULT NULL COMMENT 'Biaya aktual yang dikeluarkan untuk perawatan',
  `keterangan` text DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `created_by` int(11) DEFAULT NULL,
  `updated_by` int(11) DEFAULT NULL,
  `teknisi_id` int(11) DEFAULT NULL COMMENT 'ID pengguna yang bertindak sebagai teknisi',
  `banyaknya` int(10) DEFAULT NULL,
  `satuan` varchar(50) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--

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
  `penanggung_jawab` enum('Baur Spri Kapus','Baurku','Baurtarlat','Bidduk TI','Caraka','Kabidduk TI','Kabidinfomin','Kabidinfoops','Kabidpamsisfo','Kapusinfolahta TNI','Kasubbid Jarkomta & Duknis','Kasubbid Pam Aplikasi','Kasubbid Pam jarkomta','Kasubbid SDM TI','Kasubbid Sisfogarku','Kasubbid Sisfointel','Kasubbid Sisfoopslat','Kasubbid Sisfopers','Kasubbid Sisfoter','Kataud','Kaurdal','Kaurpers','Kaurtu','Tamudi Kapus','Wakapusinfolahta TNI') NOT NULL,
  `pengguna_id` int(11) DEFAULT NULL,
  `merk` varchar(50) NOT NULL,
  `tipe` varchar(50) DEFAULT NULL,
  `tahun_pembuatan` year(4) DEFAULT NULL,
  `warna` varchar(30) DEFAULT NULL,
  `jenis` enum('Roda 2','Roda 4','Truk','Bus','Lainnya') DEFAULT 'Roda 4',
  `bahan_bakar` enum('Pertalite','Pertamax','Solar','Listrik','Hybrid') NOT NULL DEFAULT 'Pertalite',
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

INSERT INTO `kendaraan` (`id`, `no_polisi`, `no_rangka`, `no_mesin`, `penanggung_jawab`, `pengguna_id`, `merk`, `tipe`, `tahun_pembuatan`, `warna`, `jenis`, `bahan_bakar`, `no_reg`, `no_stnk`, `no_bpkb`, `tanggal_berlaku_stnk`, `pemilik_stnk`, `status_kepemilikan`, `satker`, `kode_barang`, `kondisi`, `status_kendaraan`, `status_peminjaman`, `tanggal_servis_terakhir`, `last_service_km`, `odometer`, `foto`, `catatan`, `created_at`, `updated_at`) VALUES
(24, '8272-02', 'MHKABKA1GLK123456', 'GTNR-FE456782', 'Kabidinfomin', 3, 'Toyota', 'Avanza', '2020', 'Hitam', 'Roda 4', 'Pertalite', '8272-02', NULL, NULL, NULL, NULL, 'Satker', 'Pusinfo', NULL, 'Baik', 'Operasional', 'Tersedia', NULL, NULL, 0, NULL, 'Kendaraan dinas', '2025-09-01 05:20:53', '2025-10-29 07:47:29'),
(25, '0822-02', 'MFC5F1CC6KS123456', 'GTR18A789012', 'Kabidinfoops', 18, 'Honda', 'Civic', '2019', 'Hitam', 'Roda 4', 'Pertalite', '0822-02', NULL, NULL, NULL, NULL, 'Satker', 'Pusinfolahta', NULL, 'Baik', 'Operasional', 'Tersedia', NULL, NULL, 0, NULL, 'Kendaraan operasional', '2025-09-01 05:20:53', '2025-10-29 07:47:29'),
(26, '8811-01', 'MNK6J4WMH123456', 'NK4M41456789', 'Kapusinfolahta TNI', NULL, 'Mitsubishi', 'Pajero', '2021', 'Hitam', 'Roda 4', 'Pertalite', '8811-01', NULL, NULL, NULL, NULL, 'Satker', 'Pusinfolahta', NULL, 'Baik', 'Operasional', 'Tersedia', NULL, NULL, 0, NULL, 'Sedang dalam perbaikan', '2025-09-01 05:20:53', '2025-10-29 07:47:29'),
(27, '0200-08', 'NRK8712763', 'NM9712421', 'Kataud', NULL, 'Lexus', 'Sedan', '2022', 'Hitam', 'Roda 4', 'Pertalite', '0200-08', NULL, NULL, NULL, NULL, 'Satker', 'Pusinfolahta', NULL, 'Baik', 'Operasional', 'Dipinjam', NULL, NULL, 0, NULL, NULL, '2025-09-02 03:05:45', '2025-10-29 07:47:29'),
(77, '2272-19', 'KC-3117-DK-310675', 'KC31E13-09482', 'Kaurdal', 38, 'Honda', 'Spd Motor', '2014', 'HITAM', 'Roda 4', 'Pertalite', '2272-19', NULL, NULL, NULL, NULL, 'Satker', 'PUSINFOLAHTA', NULL, 'Baik', 'Operasional', 'Tersedia', NULL, NULL, 0, NULL, NULL, '2025-09-12 05:23:43', '2025-10-29 07:47:29'),
(78, '2270-19', 'KM4SF41A961203233', 'RT125-104572', 'Baurtarlat', 40, 'Hyosung', 'Spd Motor', '2007', 'HITAM', 'Roda 4', 'Pertalite', '2270-19', NULL, NULL, NULL, NULL, 'Satker', 'PUSINFOLAHTA', NULL, 'Baik', 'Operasional', 'Tersedia', NULL, NULL, 0, NULL, NULL, '2025-09-12 05:23:43', '2025-10-29 07:47:29'),
(79, '2271-19', 'MH8EN125A9J616413', 'F405-616505', 'Baur Spri Kapus', 41, 'Suzuki', 'Spd Motor', '2009', 'HITAM', 'Roda 2', 'Pertalite', '2271-19', NULL, NULL, NULL, NULL, 'Satker', 'PUSINFOLAHTA', NULL, 'Baik', 'Operasional', 'Tersedia', NULL, NULL, 0, NULL, NULL, '2025-09-12 05:23:43', '2025-10-29 07:47:29'),
(80, '2269-19', 'MH8NFAABJ104299', 'F4B1-ID103911', 'Kaurtu', 39, 'Suzuki', 'Spd Motor', '2011', 'HITAM', 'Roda 2', 'Pertalite', '2269-19', NULL, NULL, NULL, NULL, 'Satker', 'PUSINFOLAHTA', NULL, 'Baik', 'Operasional', 'Tersedia', NULL, NULL, 0, NULL, NULL, '2025-09-12 05:23:43', '2025-10-29 07:47:29'),
(81, '5677-19', 'MHFM1BA3J8KO81858', 'K3DC83138', 'Kasubbid Sisfogarku', 28, 'Toyota Avanza', 'Minibus', '2008', 'HITAM', 'Roda 4', 'Pertalite', '5677-19', NULL, NULL, NULL, NULL, 'Satker', 'PUSINFOLAHTA', NULL, 'Baik', 'Operasional', 'Tersedia', NULL, NULL, 0, NULL, NULL, '2025-09-12 05:23:43', '2025-10-29 07:47:29'),
(82, '5675-19', 'MHFM1BA3J8KO82684', 'K3DC84132', 'Kasubbid SDM TI', 22, 'Toyota Avanza', 'Minibus', '2008', 'HITAM', 'Roda 4', 'Pertalite', '5675-19', NULL, NULL, NULL, NULL, 'Satker', 'PUSINFOLAHTA', NULL, 'Baik', 'Operasional', 'Tersedia', NULL, NULL, 0, NULL, NULL, '2025-09-12 05:23:43', '2025-10-29 07:47:29'),
(83, '5676-19', 'MHFM1BA3J8KO82765', 'K3DC82966', 'Kasubbid Pam Aplikasi', 33, 'Toyota Avanza', 'Minibus', '2008', 'HITAM', 'Roda 4', 'Pertalite', '5676-19', NULL, NULL, NULL, NULL, 'Satker', 'PUSINFOLAHTA', NULL, 'Baik', 'Operasional', 'Tersedia', NULL, NULL, 0, NULL, NULL, '2025-09-12 05:23:43', '2025-10-29 07:47:29'),
(84, '5678-19', 'MHFM1BA3J8KO82912', 'K3DC84432', 'Kasubbid Sisfointel', 30, 'Toyota Avanza', 'Minibus', '2008', 'HITAM', 'Roda 4', 'Pertalite', '5678-19', NULL, NULL, NULL, NULL, 'Satker', 'PUSINFOLAHTA', NULL, 'Baik', 'Operasional', 'Tersedia', NULL, NULL, 0, NULL, NULL, '2025-09-12 05:23:43', '2025-10-29 07:47:29'),
(85, '5679-19', 'MHFM1BAJK103425', 'DD18542', 'Kasubbid Pam jarkomta', 34, 'Toyota Avanza', 'Minibus', '2008', 'HITAM', 'Roda 4', 'Pertalite', '5679-19', NULL, NULL, NULL, NULL, 'Satker', 'PUSINFOLAHTA', NULL, 'Baik', 'Operasional', 'Tersedia', NULL, NULL, 0, NULL, NULL, '2025-09-12 05:23:43', '2025-10-29 07:47:29'),
(86, '2266-19', 'MHIKC111-58K124255', 'KC11E11-26450', 'Baurku', 36, 'Honda', 'Spd Motor', '2007', 'HITAM', 'Roda 2', 'Pertalite', '2266-19', NULL, NULL, NULL, NULL, 'Satker', 'PUSINFOLAHTA', NULL, 'Baik', 'Operasional', 'Tersedia', NULL, NULL, 0, NULL, NULL, '2025-09-12 05:23:43', '2025-10-29 07:47:29'),
(87, '2265-19', 'MHIKC111-88K124315', 'KC11E11-26357', 'Tamudi Kapus', 35, 'Honda', 'Spd Motor', '2007', 'HITAM', 'Roda 2', 'Pertalite', '2265-19', NULL, NULL, NULL, NULL, 'Satker', 'PUSINFOLAHTA', NULL, 'Baik', 'Operasional', 'Tersedia', NULL, NULL, 0, NULL, NULL, '2025-09-12 05:23:43', '2025-10-29 08:06:41'),
(88, '2267-19', 'MHIKC111-88K126856', 'KC11E11-28902', 'Caraka', NULL, 'Honda', 'Spd Motor', '2007', 'HITAM', 'Roda 2', 'Pertalite', '2267-19', NULL, NULL, NULL, NULL, 'Satker', 'PUSINFOLAHTA', NULL, 'Baik', 'Operasional', 'Tersedia', NULL, NULL, 0, NULL, NULL, '2025-09-12 05:23:43', '2025-10-29 07:47:29'),
(89, '2268-19', 'MHIKC111-89K124321', 'KC11E11-29357', 'Kaurpers', 2, 'Honda', 'Spd Motor', '2007', 'HITAM', 'Roda 2', 'Pertalite', '2268-19', NULL, NULL, NULL, NULL, 'Satker', 'PUSINFOLAHTA', NULL, 'Baik', 'Operasional', 'Tersedia', NULL, NULL, 0, NULL, NULL, '2025-09-12 05:23:43', '2025-10-29 07:47:29'),
(90, '5681-19', 'MHKM1BA3JDK178953', 'K3MC62452', 'Wakapusinfolahta TNI', 42, 'Toyota Avanza', 'Minibus', '2014', 'HITAM', 'Roda 4', 'Pertalite', '5681-19', NULL, NULL, NULL, NULL, 'Satker', 'PUSINFOLAHTA', NULL, 'Baik', 'Operasional', 'Tersedia', NULL, NULL, 0, NULL, NULL, '2025-09-12 05:23:43', '2025-10-29 07:47:29'),
(91, '5682-19', 'MHKM5EA3JHK063624', '1NR-F268052', 'Kasubbid Sisfopers', 27, 'Toyota Avanza', 'Minibus', '2017', 'HITAM', 'Roda 4', 'Pertalite', '5682-19', NULL, NULL, NULL, NULL, 'Satker', 'PUSINFOLAHTA', NULL, 'Baik', 'Operasional', 'Tersedia', NULL, NULL, 0, NULL, NULL, '2025-09-12 05:23:43', '2025-10-29 07:47:29'),
(92, '5680-19', 'MHKV1BA1JDK024604', 'MB62605', 'Kasubbid Sisfoter', 29, 'Daihatsu Xenia', 'Minibus', '2013', 'HITAM', 'Roda 4', 'Pertalite', '5680-19', NULL, NULL, NULL, NULL, 'Satker', 'PUSINFOLAHTA', NULL, 'Baik', 'Operasional', 'Tersedia', NULL, NULL, 0, NULL, NULL, '2025-09-12 05:23:43', '2025-10-29 07:47:29'),
(93, '5913-19', 'MHMVAIWHR4K-004174', '4G18-471285', 'Kasubbid Jarkomta & Duknis', 23, 'Mitsubishi Kuda', 'Minibus', '2004', 'HITAM', 'Roda 4', 'Pertalite', '5913-19', NULL, NULL, NULL, NULL, 'Satker', 'PUSINFOLAHTA', NULL, 'Baik', 'Operasional', 'Tersedia', NULL, NULL, 0, NULL, NULL, '2025-09-12 05:23:43', '2025-10-29 07:47:29'),
(94, '5914-19', 'MHMVAIWHR4K-004196', '4G18-471273', 'Kasubbid Sisfoopslat', 31, 'Mitsubishi Kuda', 'Minibus', '2004', 'HITAM', 'Roda 4', 'Pertalite', '5914-19', NULL, NULL, NULL, NULL, 'Satker', 'PUSINFOLAHTA', NULL, 'Baik', 'Operasional', 'Tersedia', NULL, NULL, 0, NULL, NULL, '2025-09-12 05:23:43', '2025-10-29 07:47:29'),
(95, '3554-19', 'MHYERB415J503921', 'M15A-ID503491', 'Kabidduk TI', 21, 'Suzuki Baleno', 'Sedan', '2003', 'HITAM', 'Roda 4', 'Pertalite', '3554-19', NULL, NULL, NULL, NULL, 'Satker', 'PUSINFOLAHTA', NULL, 'Baik', 'Operasional', 'Tersedia', NULL, NULL, 0, NULL, NULL, '2025-09-12 05:23:43', '2025-10-29 07:47:29'),
(96, '7375-19', 'MJERK8JSKEJN 14101', 'JO8EUFJ 84101', 'Kataud', 25, 'Hino FB 13D', 'Bus', '2015', 'HITAM', 'Bus', 'Pertalite', '7375-19', NULL, NULL, NULL, NULL, 'Satker', 'PUSINFOLAHTA', NULL, 'Baik', 'Operasional', 'Tersedia', NULL, NULL, 0, NULL, NULL, '2025-09-12 05:23:43', '2025-10-29 07:47:29'),
(97, '5683-19', 'MK2NCWMANJJ000868', '4A91DK7482', 'Kataud', NULL, 'Mitsubishi Xpander', 'Minibus', '2018', 'HITAM', 'Roda 4', 'Pertalite', '5683-19', NULL, NULL, NULL, NULL, 'Satker', 'PUSINFOLAHTA', NULL, 'Baik', 'Operasional', 'Tersedia', NULL, NULL, 0, NULL, NULL, '2025-09-12 05:23:43', '2025-10-29 07:47:29'),
(98, '5684-19', 'MK2NCWMANJJ000870', '4A91DK7515', 'Kataud', NULL, 'Mitsubishi Xpander', 'Minibus', '2018', 'HITAM', 'Roda 4', 'Pertalite', '5684-19', NULL, NULL, NULL, NULL, 'Satker', 'PUSINFOLAHTA', NULL, 'Baik', 'Operasional', 'Tersedia', NULL, NULL, 0, NULL, NULL, '2025-09-12 05:23:43', '2025-10-29 07:47:29'),
(99, '9311-19', 'MMBJJL10LH030297', '4N15UGM4550', 'Bidduk TI', NULL, 'Mitsubishi Triton', 'Minibus', '2020', 'HITAM', 'Roda 4', 'Solar', '9311-19', NULL, NULL, NULL, NULL, 'Satker', 'PUSINFOLAHTA', NULL, 'Baik', 'Operasional', 'Tersedia', NULL, NULL, 0, NULL, NULL, '2025-09-12 05:23:43', '2025-09-12 05:23:43'),
(100, '3521-19', 'MR053HY9379007149', 'INZ-X6753819', 'Kabidinfomin', 26, 'Toyota Vios', 'Sedan', '2007', 'HITAM', 'Roda 4', 'Pertalite', '3521-19', NULL, NULL, NULL, NULL, 'Satker', 'PUSINFOLAHTA', NULL, 'Baik', 'Operasional', 'Tersedia', NULL, NULL, 0, NULL, NULL, '2025-09-12 05:23:43', '2025-10-29 07:47:29'),
(101, '3523-19', 'MR053HY9379007302', 'INZ-X676478', 'Kabidpamsisfo', 32, 'Toyota Vios', 'Sedan', '2007', 'HITAM', 'Roda 4', 'Pertalite', '3523-19', NULL, NULL, NULL, NULL, 'Satker', 'PUSINFOLAHTA', NULL, 'Baik', 'Operasional', 'Tersedia', NULL, NULL, 0, NULL, NULL, '2025-09-12 05:23:43', '2025-10-29 07:47:29'),
(102, '3522-19', 'MR053HY9389007938', 'INZ-X699449', 'Kabidinfoops', 24, 'Toyota Vios', 'Sedan', '2008', 'HITAM', 'Roda 4', 'Pertalite', '3522-19', NULL, NULL, NULL, NULL, 'Satker', 'PUSINFOLAHTA', NULL, 'Baik', 'Operasional', 'Tersedia', NULL, NULL, 0, NULL, NULL, '2025-09-12 05:23:43', '2025-10-29 07:47:29'),
(103, '3525-19', 'MR053REH2E4000538', '2ZR-Y039039', 'Kapusinfolahta TNI', 1, 'Toyota Altis', 'Sedan', '2014', 'HITAM', 'Roda 4', 'Pertalite', '3525-19', NULL, NULL, NULL, NULL, 'Satker', 'PUSINFOLAHTA', NULL, 'Baik', 'Operasional', 'Tersedia', NULL, NULL, 0, NULL, NULL, '2025-09-12 05:23:43', '2025-10-29 07:47:29'),
(104, '3527-19', 'NCP150R-CEMGKD', 'INZ-Z236226', 'Kabidinfomin', 26, 'Toyota Vios', 'Sedan', '2015', 'HITAM', 'Roda 4', 'Solar', '3527-19', NULL, NULL, NULL, NULL, 'Satker', 'PUSINFOLAHTA', NULL, 'Baik', 'Operasional', 'Tersedia', NULL, NULL, 0, NULL, NULL, '2025-09-12 05:23:43', '2025-09-15 02:20:06');

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
-- Struktur dari tabel `laporan_perjalanan`
--

CREATE TABLE `laporan_perjalanan` (
  `id` int(11) NOT NULL,
  `tanggal` date NOT NULL,
  `kendaraan_id` int(11) NOT NULL,
  `pengguna_id` int(11) DEFAULT NULL,
  `uraian_kegiatan` varchar(255) NOT NULL,
  `route` varchar(255) NOT NULL,
  `jarak_km` int(20) DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `updated_at` datetime DEFAULT NULL ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;


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
  `jumlah_liter` int(10) NOT NULL,
  `harga_per_liter` int(10) NOT NULL,
  `biaya` int(15) NOT NULL,
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
-- Struktur dari tabel `pengguna`
--

CREATE TABLE `pengguna` (
  `id` int(11) NOT NULL,
  `nrp_nip` varchar(30) DEFAULT NULL,
  `nama_lengkap` varchar(100) NOT NULL,
  `pangkat` varchar(50) DEFAULT NULL,
  `jabatan` enum('Baur Spri Kapus','Baurku','Baurtarlat','Bidduk TI','Caraka','Kabidduk TI','Kabidinfomin','Kabidinfoops','Kabidpamsisfo','Kapusinfolahta TNI','Kasubbid Jarkomta & Duknis','Kasubbid Pam Aplikasi','Kasubbid Pam jarkomta','Kasubbid SDM TI','Kasubbid Sisfogarku','Kasubbid Sisfointel','Kasubbid Sisfoopslat','Kasubbid Sisfopers','Kasubbid Sisfoter','Kataud','Kaurdal','Kaurpers','Kaurtu','Tamudi Kapus','Wakapusinfolahta TNI') DEFAULT NULL,
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
(1, '31010045870399', 'Admin', 'Brigadir Jenderal', 'Kapusinfolahta TNI', NULL, '08123467890', 'admin@tni.mil.id', 'Jakarta', 'Aktif', 'Aktif', 'TNI', 'AD', 'Infantri', 'Pusat Informasi Pengolah Data', '2025-08-13 01:26:52', '2025-10-15 01:22:12'),
(2, '31070366780481', 'WAHYU HIDAYAT', 'Kolonel', 'Kaurpers', NULL, '081234567891', 'wahyu.hidayat@tni.mil.id', 'Jakarta Timur', 'Aktif', 'Aktif', 'TNI', 'AL', 'Marinir', 'Pusat Informasi Pengolah Data', '2025-08-13 01:26:52', '2025-09-30 13:15:33'),
(3, '31070366800485', 'BUDI SANTOSO', 'Letnan Kolonel', 'Kabidinfomin', NULL, '081234567892', 'kol@gmail.com', 'Jakarta Selatan', 'Aktif', 'Aktif', 'TNI', 'AD', 'Inf', 'Pusat Informasi Pengolah Data', '2025-08-13 01:26:52', '2025-09-10 06:53:11'),
(14, '201948131', 'Dona D', 'Letnan Kolonel', 'Kasubbid Sisfopers', NULL, '8123212412', 'qws.sds@gmail.com', NULL, 'Aktif', 'Aktif', 'TNI', 'AD', 'INFANTERI', 'Pusinfolahta', '2025-09-10 08:19:19', '2025-09-10 08:19:19'),
(15, '207448392', 'Restu P', 'Kolonel', 'Kabidduk TI', NULL, '8234456734', 'res.tu@gmail.com', NULL, 'Aktif', 'Aktif', 'TNI', 'AD', 'INFANTERI', 'Pusinfolahta', '2025-09-10 08:19:19', '2025-09-10 08:19:19'),
(17, '2013023102', 'Brucle', 'Letnan Satu', 'Kasubbid SDM TI', NULL, '812342332', 'bruce.lee@gmail.com', NULL, 'Aktif', 'Aktif', 'TNI', 'AD', 'INFANTERI', 'Pusinfolahta', '2025-09-10 08:19:19', '2025-09-11 02:34:28'),
(18, '203123442', 'Ehud S', 'Kolonel', 'Kabidinfoops', NULL, '821232356', 'ehud@gmail.com', '', 'Aktif', 'Aktif', 'TNI', 'AD', 'INFANTERI', 'Pusinfolahta', '2025-09-10 08:19:19', '2025-10-07 07:38:14'),
(21, '13476/P', 'Restu Putra, S.Kom', 'kolonel', 'Kabidduk TI', NULL, '8123456789', 'restu.putra@idu.mil.id', 'Jl. Merdeka No. 3', 'Aktif', 'Aktif', 'TNI', 'AD', 'INFANTERI', 'Pusinfolahta', '2025-09-15 02:15:25', '2025-09-15 02:15:25'),
(22, '519047', 'Bruceele Bernard B, S.Kom.', 'kapten', 'Kasubbid SDM TI', NULL, '8123423322', 'bruceele.bernard@idu.mil.id', 'Jl. Merdeka No. 4', 'Aktif', 'Aktif', 'TNI', 'AD', 'INFANTERI', 'Pusinfolahta', '2025-09-15 02:15:25', '2025-09-15 02:15:25'),
(23, '532007', 'Muhammad Soleh, S.Ik', 'mayor', 'Kasubbid Jarkomta & Duknis', NULL, '8212323562', 'muhammad.soleh@idu.mil.id', 'Jl. Merdeka No. 5', 'Aktif', 'Aktif', 'TNI', 'AD', 'INFANTERI', 'Pusinfolahta', '2025-09-15 02:15:25', '2025-09-15 02:15:25'),
(24, '14631/P', 'Ehud Septano Mediana, S.T., M.Tr.Hanla., M.M.', 'kolonel', 'Kabidinfoops', NULL, '8213341223', 'ehud.mediana@idu.mil.id', 'Jl. Merdeka No. 6', 'Aktif', 'Aktif', 'TNI', 'AD', 'INFANTERI', 'Pusinfolahta', '2025-09-15 02:15:25', '2025-09-15 02:15:25'),
(25, '21930084510272', 'Memet Supriatna, S.E., M.M.', 'Letnan Kolonel', 'Kataud', NULL, '8210123448', 'memet.supriatna@idu.mil.id', 'Jl. Merdeka No. 7', 'Aktif', 'Aktif', 'TNI', 'AD', 'INFANTERI', 'Pusinfolahta', '2025-09-15 02:15:25', '2025-09-15 02:15:25'),
(26, '11950061410673', 'Efi Suhartono, S.E.', 'Kolonel Cke', 'Kabidinfomin', NULL, '8221100907', 'efi.suhartono@idu.mil.id', 'Jl. Merdeka No. 8', 'Aktif', 'Aktif', 'TNI', 'AD', 'INFANTERI', 'Pusinfolahta', '2025-09-15 02:15:25', '2025-09-15 02:15:25'),
(27, '636630', 'Dwi Setyo Utomo', 'Letnan Kolonel', 'Kasubbid Sisfopers', NULL, '8232078366', 'dwi.utomo@idu.mil.id', 'Jl. Merdeka No. 9', 'Aktif', 'Aktif', 'TNI', 'AD', 'INFANTERI', 'Pusinfolahta', '2025-09-15 02:15:26', '2025-09-15 02:15:26'),
(28, '516521', 'Mulyani, S.Kom., M.M', 'mayor', 'Kasubbid Sisfogarku', NULL, '8243055825', 'mulyani@idu.mil.id', 'Jl. Merdeka No. 10', 'Aktif', 'Aktif', 'TNI', 'AD', 'INFANTERI', 'Pusinfolahta', '2025-09-15 02:15:26', '2025-09-15 02:15:26'),
(29, '17834/P', 'Yudhi Akbar, S.Kom., MMSI., M.Tr. Opsla', 'Letnan Kolonel', 'Kasubbid Sisfoter', NULL, '8254033284', 'yudhi.akbar@idu.mil.id', 'Jl. Merdeka No. 11', 'Aktif', 'Aktif', 'TNI', 'AD', 'INFANTERI', 'Pusinfolahta', '2025-09-15 02:15:26', '2025-09-15 02:15:26'),
(30, '2920027550569', 'Ai Anisah Mochamad Zen', 'mayor', 'Kasubbid Sisfointel', NULL, '8265010744', 'ai.mochamad@idu.mil.id', 'Jl. Merdeka No. 12', 'Aktif', 'Aktif', 'TNI', 'AD', 'INFANTERI', 'Pusinfolahta', '2025-09-15 02:15:26', '2025-09-15 02:15:26'),
(31, '631510', 'Suhartana, S.A.P.', 'mayor', 'Kasubbid Sisfoopslat', NULL, '8275988203', 'suhartana@idu.mil.id', 'Jl. Merdeka No. 13', 'Aktif', 'Aktif', 'TNI', 'AD', 'INFANTERI', 'Pusinfolahta', '2025-09-15 02:15:26', '2025-09-15 02:15:26'),
(32, '1920027560569', 'Hari Purnomo, S.E.', 'kolonel', 'Kabidpamsisfo', NULL, '8286965662', 'hari.purnomo@idu.mil.id', 'Jl. Merdeka No. 14', 'Aktif', 'Aktif', 'TNI', 'AD', 'INFANTERI', 'Pusinfolahta', '2025-09-15 02:15:26', '2025-09-15 02:15:26'),
(33, '17580/P', 'Rudy Fitriansyah P.', 'Mayor', 'Kasubbid Pam Aplikasi', NULL, '8297943121', 'rudy.fitriansyah@idu.mil.id', 'Jl. Merdeka No. 15', 'Aktif', 'Aktif', 'TNI', 'AD', 'INFANTERI', 'Pusinfolahta', '2025-09-15 02:15:26', '2025-09-15 02:15:26'),
(34, '527142', 'Erwin Kurnia N.M., S.T., M.Si.,(Han)', 'Letnan Kolonel', 'Kasubbid Pam jarkomta', NULL, '8308920580', 'erwin.kurnia@idu.mil.id', 'Jl. Merdeka No. 16', 'Aktif', 'Aktif', 'TNI', 'AD', 'INFANTERI', 'Pusinfolahta', '2025-09-15 02:15:26', '2025-09-15 02:15:26'),
(35, '31150620481194', 'Frendi Nova Arianto', 'Prajurit Kepala', 'Tamudi Kapus', NULL, '8319898040', 'frendi.arianto@idu.mil.id', 'Jl. Merdeka No. 17', 'Aktif', 'Aktif', 'TNI', 'AD', 'INFANTERI', 'Pusinfolahta', '2025-09-15 02:15:26', '2025-09-15 02:15:26'),
(36, '31000639850580', 'Muhammad Daroji', 'Sersan Kepala', 'Baurku', NULL, '8330875499', 'muhammad.daroji@idu.mil.id', 'Jl. Merdeka No. 18', 'Aktif', 'Aktif', 'TNI', 'AD', 'INFANTERI', 'Pusinfolahta', '2025-09-15 02:15:26', '2025-09-15 02:15:26'),
(37, '31140433610995', 'Yusuf Angga Wahyudi', 'Prajurit Kepala', 'Caraka', NULL, '8341852958', 'yusuf.wahyudi@idu.mil.id', 'Jl. Merdeka No. 19', 'Aktif', 'Aktif', 'TNI', 'AD', 'INFANTERI', 'Pusinfolahta', '2025-09-15 02:15:26', '2025-09-15 02:15:26'),
(38, '23023/P', 'Wandry Wayuno Timor', 'Letnan Satu', 'Kaurdal', NULL, '8363807876', 'wandry.timor@idu.mil.id', 'Jl. Merdeka No. 21', 'Aktif', 'Aktif', 'TNI', 'AD', 'INFANTERI', 'Pusinfolahta', '2025-09-15 02:15:27', '2025-09-15 02:15:27'),
(39, '21050175740986', 'Minhajul Affan', 'Pembantu Letnan Dua', 'Kaurtu', NULL, '8374785336', 'minhajul.affan@idu.mil.id', 'Jl. Merdeka No. 22', 'Aktif', 'Aktif', 'TNI', 'AD', 'INFANTERI', 'Pusinfolahta', '2025-09-15 02:15:27', '2025-09-15 02:15:27'),
(40, '31030434220983', 'Tri Purwanto', 'Sersan Satu', 'Baurtarlat', NULL, '8385762795', 'tri.purwanto@idu.mil.id', 'Jl. Merdeka No. 23', 'Aktif', 'Aktif', 'TNI', 'AD', 'INFANTERI', 'Pusinfolahta', '2025-09-15 02:15:27', '2025-09-15 02:15:27'),
(41, '31120356660692', 'Dwi Yudo Putro Prasojo', 'Sersan Dua', 'Baur Spri Kapus', NULL, '8396740254', 'dwi.prasojo@idu.mil.id', 'Jl. Merdeka No. 24', 'Aktif', 'Aktif', 'TNI', 'AD', 'INFANTERI', 'Pusinfolahta', '2025-09-15 02:15:27', '2025-09-15 02:15:27'),
(42, '52342', 'S. Ginting, S.Kom., MMSI.,M.Tr.Hanla', 'Kolonel', 'Wakapusinfolahta TNI', NULL, '08366323', 'ginting@idu.mil.id', 'Jl.asdan dan', 'Aktif', 'Aktif', 'TNI', 'AD', 'Infantri', 'Pusinfolahta', '2025-09-15 02:21:50', '2025-09-15 02:21:50');

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
  `status` enum('Aktif','Tidak Aktif','Suspend') NOT NULL DEFAULT 'Aktif',
  `keterangan` text DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Tabel untuk mengelola akses pengguna terhadap kendaraan';

--
-- Dumping data untuk tabel `pengguna_kendaraan`
--

INSERT INTO `pengguna_kendaraan` (`id`, `pengguna_id`, `kendaraan_id`, `tanggal_mulai`, `tanggal_selesai`, `status`, `keterangan`, `created_at`, `updated_at`) VALUES
(1, 1, 1, '2025-01-01', NULL, 'Aktif', 'Administrator access', '2025-09-17 01:58:41', '2025-09-17 01:58:41'),
(2, 2, 1, '2025-01-01', NULL, 'Aktif', 'Operator access', '2025-09-17 01:58:41', '2025-09-17 01:58:41'),
(3, 35, 95, '2025-09-29', '2025-09-30', '', NULL, '2025-09-22 01:48:14', '2025-09-22 01:48:14'),
(4, 35, 95, '2025-09-29', '2025-09-30', '', NULL, '2025-09-22 01:48:36', '2025-09-22 01:48:36'),
(5, 39, 81, '2025-10-13', '2025-10-14', '', NULL, '2025-10-13 03:44:45', '2025-10-13 03:44:45'),
(6, 39, 81, '2025-10-13', '2025-10-14', '', NULL, '2025-10-13 03:44:57', '2025-10-13 03:44:57'),
(7, 39, 81, '2025-10-13', '2025-10-14', '', NULL, '2025-10-13 05:10:11', '2025-10-13 05:10:11'),
(8, 1, 92, '2025-10-14', '2025-10-16', '', NULL, '2025-10-13 05:10:52', '2025-10-13 05:10:52');

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


-- --------------------------------------------------------

--
-- Struktur dari tabel `riwayat_perbaikan_items`
--

CREATE TABLE `riwayat_perbaikan_items` (
  `id` int(11) NOT NULL,
  `perbaikan_id` int(11) NOT NULL,
  `nama_barang` varchar(150) NOT NULL,
  `qty` int(20) NOT NULL DEFAULT 0,
  `satuan` varchar(32) DEFAULT NULL,
  `harga` int(20) NOT NULL DEFAULT 0,
  `urutan` int(11) NOT NULL DEFAULT 0,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Struktur dari tabel `role`
--

CREATE TABLE `role` (
  `id` int(11) NOT NULL,
  `nama_role` varchar(50) NOT NULL,
  `kode_role` varchar(20) NOT NULL,
  `deskripsi` text DEFAULT NULL,
  `level_akses` tinyint(1) NOT NULL DEFAULT 1,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Master data Role User';

--
-- Dumping data untuk tabel `role`
--

INSERT INTO `role` (`id`, `nama_role`, `kode_role`, `deskripsi`, `level_akses`, `created_at`, `updated_at`) VALUES
(1, 'Administrator', 'ADMIN', 'Full akses dan persetujuan', 1, '2025-08-20 11:00:08', '2025-09-10 08:26:34'),
(2, 'Operator', 'OPERATOR', 'Manajemen kendaraan dan pembuatan surat', 2, '2025-08-20 11:00:08', '2025-09-04 03:41:01'),
(3, 'User', 'USER', 'Akses terbatas ', 3, '2025-08-20 11:00:08', '2025-09-10 08:26:40');

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
-- Struktur dari tabel `user_account`
--

CREATE TABLE `user_account` (
  `id` int(11) NOT NULL,
  `pengguna_id` int(11) NOT NULL,
  `username` varchar(50) NOT NULL,
  `password` varchar(255) NOT NULL,
  `role_id` int(11) NOT NULL DEFAULT 3,
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
(2, 2, 'operator', '$2y$10$/l7mI5CTc/Z4UJ4RylpXwecclVY8QK0ZMzUqdaw9ORLNHU9FQPuCW', 1, '2025-08-19 07:59:37', 'Aktif', '2025-08-13 01:28:52', '2025-09-30 13:15:41'),
(3, 3, 'user', '$2y$10$4BaUKMt.HNmDD6Z3zhzQ2eEQNvTbVlCGj4I6QuzJSZmIwWZ1bLjcy', 3, '2025-08-19 07:07:09', 'Aktif', '2025-08-13 01:28:52', '2025-08-21 05:17:29'),
(4, 4, 'teknisi', '$2y$10$fUKpldu9OqdgKbne6tkoeOi3.SJS9NZ0Ee8edekGEI4leiZN7sQ9e', 1, NULL, 'Aktif', '2025-08-13 01:28:52', '2025-09-30 13:15:49'),
(5, 5, 'driver', '$2y$10$fUKpldu9OqdgKbne6tkoeOi3.SJS9NZ0Ee8edekGEI4leiZN7sQ9e', 3, '2025-08-19 08:01:00', 'Aktif', '2025-08-13 01:28:52', '2025-09-15 23:45:21'),
(6, 10, 'pns', '$2y$10$uh/UTqoSK/8aoZcoR2mlA.4r2EB2AXP.jdXxU4Zfscru/IU9RX.VO', 3, NULL, 'Aktif', '2025-09-01 03:42:47', '2025-09-04 01:54:07'),
(7, 11, 'tni', '$2y$10$6JMxmebW.y4rSOIb66e2DexFgxh5NN1fFvALlqEpT8UpSRNmkRexC', 3, NULL, 'Aktif', '2025-09-01 03:58:15', '2025-09-01 03:58:15'),
(8, 12, 'asn', '$2y$10$Emkql.RE8Jbv6sVuTVcX3.8ZhbyH2Nj/DLtJ/llSviOSqd/3V/Xba', 3, NULL, 'Aktif', '2025-09-04 01:55:17', '2025-10-26 12:22:58'),
(9, 13, 'john.doe', '$2y$10$Bnqg5DwM9b3Svxa5Lg4GVeAObXvz1JiXpm.rfC0updRJWabKWHytC', 3, NULL, 'Aktif', '2025-09-10 08:19:19', '2025-10-26 12:24:00'),
(10, 14, 'qws.sds', '$2y$10$KH1jYKuFTicNEVOuZeoEdOiRxjcF0eVoQ.urg33ms9T1UD5Gk/9qe', 3, NULL, 'Aktif', '2025-09-10 08:19:19', '2025-09-10 08:19:19'),
(11, 15, 'res.tu', '$2y$10$23Ez4kBDrdZaeO8fGVx28OVNGg2uGTQwvbulgL8/T2fz78XIp1HEC', 3, NULL, 'Aktif', '2025-09-10 08:19:19', '2025-09-10 08:19:19'),
(12, 16, 'soleh', '$2y$10$628KosrRYcT80poiXD4HYeKpj/ZPmZwBeDwK2xNizqFWtXcRifHUG', 3, NULL, 'Aktif', '2025-09-10 08:19:19', '2025-09-11 02:20:59'),
(13, 17, 'bruce.lee', '$2y$10$fyhQxlaKTNUnKeJQsf1xJeYeJkJCnlbLZJJoV62vXX/3jL/iDJSRu', 3, NULL, 'Aktif', '2025-09-10 08:19:19', '2025-10-26 12:24:15'),
(14, 18, 'ehud', '$2y$10$1j85Z9O.f8WlqOJuYH6TpOwfHy9G2l5ld.Pvvi3AieXV8fDQvQZi.', 3, NULL, 'Aktif', '2025-09-10 08:19:19', '2025-09-10 08:19:19'),
(15, 21, 'user3', '$2y$10$3KbEauRW2moMXH7Lwgb4guL9y518/FnbiMgONMc7/3yjn60.M7g4S', 3, NULL, 'Aktif', '2025-09-15 02:15:25', '2025-09-15 02:15:25'),
(16, 22, 'user4', '$2y$10$toWtuObXQzR4R92v5bSWNePiJoPUmOzQaqLRLg1XdzcIqzULyRPO2', 3, NULL, 'Aktif', '2025-09-15 02:15:25', '2025-09-15 02:15:25'),
(17, 23, 'user5', '$2y$10$qfdaYCyl7dNFqqaRQiizh.9vHQHsxbVtQLjhdzHncrEAju9GykG86', 3, NULL, 'Aktif', '2025-09-15 02:15:25', '2025-09-15 02:15:25'),
(18, 24, 'user6', '$2y$10$u21dXPLcaAVP2Unh2YVVEuL2/eIZSt6BThKE8c5YX.CJz7RYDNJ1u', 3, NULL, 'Aktif', '2025-09-15 02:15:25', '2025-09-15 02:15:25'),
(19, 25, 'user7', '$2y$10$B1QT4omdjt8J0fFJHlS9.OeiKfD7HYZ9Ac9kfCRZ97yxGiSwTXemW', 3, NULL, 'Aktif', '2025-09-15 02:15:25', '2025-09-15 02:15:25'),
(20, 26, 'user8', '$2y$10$OgnJPGbJx3U7pYkH55IDhuEvSpkO91Sl0rZY.bDgDlaDJ8finN0Pq', 3, NULL, 'Aktif', '2025-09-15 02:15:26', '2025-09-15 02:15:26'),
(21, 27, 'user9', '$2y$10$7mrjLvV2RNSG6hytA9jERemmgt9whlWGJnwNmLnVLft91G0wRXo9a', 3, NULL, 'Aktif', '2025-09-15 02:15:26', '2025-09-15 02:15:26'),
(22, 28, 'user10', '$2y$10$rFKI5heHTP5ho5CW4jxjeeMJqBnAdNBlI0jDa/9tjuewIr2dyigQ2', 3, NULL, 'Aktif', '2025-09-15 02:15:26', '2025-09-15 02:15:26'),
(23, 29, 'user11', '$2y$10$/tuvxHuCta9QuAgl1cL.BOvAxj6imP38yo6WkX/CfXLs64YX3XgPS', 3, NULL, 'Aktif', '2025-09-15 02:15:26', '2025-09-15 02:15:26'),
(24, 30, 'user12', '$2y$10$GKUOi7Pl4EAgJB4GLc0KB.Dc3IFUN0MzTPsKxY8dCRAW9IalGX9YO', 3, NULL, 'Aktif', '2025-09-15 02:15:26', '2025-09-15 02:15:26'),
(25, 31, 'user13', '$2y$10$fzDybJMDxFCfkPFjiZUgmeC3LfW6H6tTfEA/zaAHbfTi.0BE2I89G', 3, NULL, 'Aktif', '2025-09-15 02:15:26', '2025-09-15 02:15:26'),
(26, 32, 'user14', '$2y$10$I3uV4snlFZzDtAi3YGMKw.UR6y9HIq2wizSI0IOSzDp/8./WOtgOm', 3, NULL, 'Aktif', '2025-09-15 02:15:26', '2025-09-15 02:15:26'),
(27, 33, 'user15', '$2y$10$6tDNTlC8OkQTtQxjhQK/s.y/olSk8tUZuZdFOR.s2HrybCZn0QVJ2', 3, NULL, 'Aktif', '2025-09-15 02:15:26', '2025-09-15 02:15:26'),
(28, 34, 'user16', '$2y$10$PQPn/hlegNi/oRzMW7QTQ.IjiRXPVqztG.kTVX.V4tEXUoqSqjoZW', 3, NULL, 'Aktif', '2025-09-15 02:15:26', '2025-09-15 02:15:26'),
(29, 35, 'user17', '$2y$10$smTa8HxwkI3Uh.K4gfk9EOKyFBGbKCAmptHVLf/kpzDLuqEIN7MSG', 3, NULL, 'Aktif', '2025-09-15 02:15:26', '2025-09-15 02:15:26'),
(30, 36, 'user18', '$2y$10$TVokwoXVBKL8JJT8qdi/HOCsLX0xAJvBRbLCM4kf2wLr40SaBo.Ke', 3, NULL, 'Aktif', '2025-09-15 02:15:26', '2025-09-15 02:15:26'),
(31, 37, 'user19', '$2y$10$TUJP0XahucvCgiIe9j.dt.Z.d.fCIapGmU.J0SDeoa9Dgx7PQlamW', 3, NULL, 'Aktif', '2025-09-15 02:15:27', '2025-09-15 02:15:27'),
(32, 38, 'user21', '$2y$10$wmGsl0Qs55wP1gXJOxPbGOfUgPYNiFrs1Bv8x0QPBq.yBSWm8ko8u', 3, NULL, 'Aktif', '2025-09-15 02:15:27', '2025-09-15 02:15:27'),
(33, 39, 'user22', '$2y$10$UD6STus8L5k3V/AZcIx1L.Zqj7NRHFudhDWuDVwx6d6evxzTrl1kq', 3, NULL, 'Aktif', '2025-09-15 02:15:27', '2025-10-14 09:06:02'),
(34, 40, 'user23', '$2y$10$x7Y2mpcWS6psCzuqp22BcOYxpdMDBzmFNdxqHPuMdUbNZIpYXfT2K', 3, NULL, 'Aktif', '2025-09-15 02:15:27', '2025-09-15 02:15:27'),
(35, 41, 'user24', '$2y$10$z0Re4WoHwEWq/bkvAdXJ/uL8CYAj4dObGOiFqBHoYhi9lPFq4ZJjy', 3, NULL, 'Aktif', '2025-09-15 02:15:27', '2025-09-15 02:15:27'),
(36, 42, 'ginting', '$2y$10$ks2G7LP3elvwTmcSJ.mRgu5dP4gFj5dQaxMPqqxRkTy1UI.1GvYYS', 1, NULL, 'Aktif', '2025-09-15 02:21:50', '2025-10-29 07:41:32');

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
  ADD UNIQUE KEY `no_rangka` (`no_rangka`),
  ADD UNIQUE KEY `no_mesin` (`no_mesin`),
  ADD UNIQUE KEY `no_reg` (`no_reg`),
  ADD UNIQUE KEY `no_stnk` (`no_stnk`),
  ADD KEY `idx_kendaraan_pengguna_id` (`pengguna_id`);

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
-- Indeks untuk tabel `laporan_perjalanan`
--
ALTER TABLE `laporan_perjalanan`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_laporan_kendaraan` (`kendaraan_id`),
  ADD KEY `idx_laporan_pengguna` (`pengguna_id`);

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
  ADD KEY `fk_pengguna_kendaraan_pengguna` (`pengguna_id`),
  ADD KEY `fk_pengguna_kendaraan_kendaraan` (`kendaraan_id`),
  ADD KEY `idx_pengguna_kendaraan_status` (`status`),
  ADD KEY `idx_pengguna_kendaraan_tanggal` (`tanggal_mulai`,`tanggal_selesai`);

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
-- Indeks untuk tabel `riwayat_perbaikan_items`
--
ALTER TABLE `riwayat_perbaikan_items`
  ADD PRIMARY KEY (`id`),
  ADD KEY `fk_rp_items_rp` (`perbaikan_id`);

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
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=68;

--
-- AUTO_INCREMENT untuk tabel `kendaraan`
--
ALTER TABLE `kendaraan`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=108;

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
-- AUTO_INCREMENT untuk tabel `laporan_perjalanan`
--
ALTER TABLE `laporan_perjalanan`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=20;

--
-- AUTO_INCREMENT untuk tabel `log_aktivitas`
--
ALTER TABLE `log_aktivitas`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=778;

--
-- AUTO_INCREMENT untuk tabel `log_bahan_bakar`
--
ALTER TABLE `log_bahan_bakar`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=24;

--
-- AUTO_INCREMENT untuk tabel `matra`
--
ALTER TABLE `matra`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=5;

--
-- AUTO_INCREMENT untuk tabel `notifikasi`
--
ALTER TABLE `notifikasi`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=238;

--
-- AUTO_INCREMENT untuk tabel `peminjaman_kendaraan`
--
ALTER TABLE `peminjaman_kendaraan`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=22;

--
-- AUTO_INCREMENT untuk tabel `pengguna`
--
ALTER TABLE `pengguna`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=43;

--
-- AUTO_INCREMENT untuk tabel `pengguna_kendaraan`
--
ALTER TABLE `pengguna_kendaraan`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=9;

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
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=20;

--
-- AUTO_INCREMENT untuk tabel `riwayat_perbaikan_items`
--
ALTER TABLE `riwayat_perbaikan_items`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=22;

--
-- AUTO_INCREMENT untuk tabel `role`
--
ALTER TABLE `role`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=4;

--
-- AUTO_INCREMENT untuk tabel `surat_tugas`
--
ALTER TABLE `surat_tugas`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=24;

--
-- AUTO_INCREMENT untuk tabel `user_account`
--
ALTER TABLE `user_account`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=37;

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
-- Ketidakleluasaan untuk tabel `kendaraan`
--
ALTER TABLE `kendaraan`
  ADD CONSTRAINT `fk_kendaraan_pengguna` FOREIGN KEY (`pengguna_id`) REFERENCES `pengguna` (`id`) ON DELETE SET NULL ON UPDATE CASCADE;

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
-- Ketidakleluasaan untuk tabel `laporan_perjalanan`
--
ALTER TABLE `laporan_perjalanan`
  ADD CONSTRAINT `fk_laporan_kendaraan` FOREIGN KEY (`kendaraan_id`) REFERENCES `kendaraan` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
  ADD CONSTRAINT `fk_laporan_pengguna` FOREIGN KEY (`pengguna_id`) REFERENCES `pengguna` (`id`) ON DELETE SET NULL ON UPDATE CASCADE;

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
-- Ketidakleluasaan untuk tabel `pengguna_kendaraan`
--
ALTER TABLE `pengguna_kendaraan`
  ADD CONSTRAINT `fk_pengguna_kendaraan_kendaraan` FOREIGN KEY (`kendaraan_id`) REFERENCES `kendaraan` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
  ADD CONSTRAINT `fk_pengguna_kendaraan_pengguna` FOREIGN KEY (`pengguna_id`) REFERENCES `pengguna` (`id`) ON DELETE CASCADE ON UPDATE CASCADE;

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
-- Ketidakleluasaan untuk tabel `riwayat_perbaikan_items`
--
ALTER TABLE `riwayat_perbaikan_items`
  ADD CONSTRAINT `fk_rp_items_rp` FOREIGN KEY (`perbaikan_id`) REFERENCES `riwayat_perbaikan` (`id`) ON DELETE CASCADE;

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
