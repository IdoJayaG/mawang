-- phpMyAdmin SQL Dump
-- version 5.2.1
-- https://www.phpmyadmin.net/
--
-- Host: 127.0.0.1
-- Waktu pembuatan: 31 Bulan Mei 2026 pada 18.15
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

-- --------------------------------------------------------

--
-- Struktur dari tabel `dokumen_kendaraan`
--

CREATE TABLE `dokumen_kendaraan` (
  `id` int(11) NOT NULL,
  `kendaraan_id` int(11) NOT NULL,
  `jenis_dokumen` varchar(50) NOT NULL,
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
(11, 124, 'Bukti Nomor Kendaraan Bermotor', 'BNKB-30021', '2026-05-26', '2027-05-26', 'Kemhan Ri', '124_Bukti Nomor Kendaraan Bermotor_6a16a857df3b7_20260527151623.jpg', 'Aktif', '', '2026-05-27 08:16:23', '2026-05-27 08:16:23', 1, NULL);

-- --------------------------------------------------------

--
-- Struktur dari tabel `email_reminder_jobs`
--

CREATE TABLE `email_reminder_jobs` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `recipient_email` varchar(255) NOT NULL,
  `recipient_name` varchar(150) DEFAULT NULL,
  `subject` varchar(255) NOT NULL,
  `body_html` mediumtext NOT NULL,
  `body_text` text DEFAULT NULL,
  `send_at` datetime NOT NULL,
  `status` enum('pending','sent','failed','cancelled') NOT NULL DEFAULT 'pending',
  `attempt_count` tinyint(3) UNSIGNED NOT NULL DEFAULT 0,
  `max_attempts` tinyint(3) UNSIGNED NOT NULL DEFAULT 3,
  `last_error` text DEFAULT NULL,
  `source_type` varchar(64) DEFAULT NULL,
  `source_key` varchar(191) DEFAULT NULL,
  `sent_at` datetime DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `updated_at` datetime NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

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
-- Dumping data untuk tabel `jadwal_perawatan`
--

INSERT INTO `jadwal_perawatan` (`id`, `kendaraan_id`, `jenis_perawatan`, `deskripsi`, `bengkel`, `tanggal_perawatan`, `km_kembali`, `km_saat_perawatan`, `estimasi_biaya`, `status`, `prioritas`, `reminder_sent`, `tanggal_selesai`, `biaya_aktual`, `keterangan`, `created_at`, `updated_at`, `created_by`, `updated_by`, `teknisi_id`, `banyaknya`, `satuan`) VALUES
(71, 134, 'Service', '', '', '2026-05-17', NULL, NULL, NULL, 'Selesai', 'Normal', 0, '2026-05-19', NULL, '', '2026-05-12 20:11:41', '2026-05-19 01:32:05', 1, 1, NULL, 1, ''),
(72, 138, 'servis berkala', 'servis', '', '2026-05-19', NULL, NULL, NULL, 'Selesai', 'Normal', 0, '2026-05-19', NULL, '', '2026-05-19 06:01:01', '2026-05-19 15:52:37', 1, 1, NULL, 1, ''),
(73, 138, 'as', 'as', '', '2026-05-19', NULL, NULL, NULL, 'Selesai', 'Normal', 0, '2026-05-20', NULL, '', '2026-05-19 15:53:02', '2026-05-20 00:43:18', 1, 1, NULL, 1, ''),
(74, 134, 'ac', 'ad', '', '2026-06-20', NULL, 3012, NULL, 'Terjadwal', 'Normal', 0, NULL, NULL, '', '2026-05-27 08:13:59', '2026-05-27 08:13:59', 1, NULL, NULL, 1, '');

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
  `locator` varchar(255) DEFAULT NULL,
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

INSERT INTO `kendaraan` (`id`, `no_polisi`, `no_rangka`, `no_mesin`, `penanggung_jawab`, `locator`, `pengguna_id`, `merk`, `tipe`, `tahun_pembuatan`, `warna`, `jenis`, `bahan_bakar`, `no_reg`, `no_stnk`, `no_bpkb`, `tanggal_berlaku_stnk`, `pemilik_stnk`, `status_kepemilikan`, `satker`, `kode_barang`, `kondisi`, `status_kendaraan`, `status_peminjaman`, `tanggal_servis_terakhir`, `last_service_km`, `odometer`, `foto`, `catatan`, `created_at`, `updated_at`) VALUES
(77, '7774-08', 'KC-3117-DK-310675', 'KC31E13-09482', 'Kaurdal', '888888', 83, 'Isuzu', 'Bus', '2014', 'HITAM', 'Roda 4', 'Solar', '7774-08', NULL, NULL, NULL, NULL, 'Satker', 'Unhan', NULL, 'Baik', 'Operasional', 'Tersedia', NULL, NULL, 0, NULL, NULL, '2025-09-12 05:23:43', '2026-05-31 16:09:21'),
(116, '7799-00', 'KK2000341', 'NM400123', 'Kaurtu', '889900', 55, 'Hino', 'Bus', '2014', 'Hitam', 'Roda 4', 'Solar', '7799-00', NULL, NULL, NULL, NULL, 'Satker', 'Setjen Kemhan', NULL, 'Baik', 'Operasional', 'Tersedia', NULL, NULL, 0, NULL, NULL, '2026-04-13 12:49:54', '2026-05-27 08:17:37'),
(119, '7775-0000', 'VINb64a7f3adb868fada9911777000375', 'ENGINE8a8b81aae33138504ef7', 'Baurku', NULL, 46, 'Hino', 'Bus', '2015', 'Hitam', 'Roda 4', 'Solar', '7775-00', NULL, NULL, NULL, NULL, 'Satker', 'Setjen Kemhan', NULL, 'Baik', 'Operasional', 'Tersedia', NULL, NULL, 0, NULL, NULL, '2026-04-24 03:12:55', '2026-05-13 09:37:56'),
(120, '7722-0000', 'VINdd38a8aaa2d8047098fc1777000375', 'ENGINE1d3a70d191a5b5a2b9fb', 'Baurku', NULL, 47, 'Mitsubishi', 'Bus', '2003', 'Hitam', 'Roda 4', 'Solar', '7722-00', NULL, NULL, NULL, NULL, 'Satker', 'Setjen Kemhan', NULL, 'Baik', 'Operasional', 'Tersedia', NULL, NULL, 0, NULL, NULL, '2026-04-24 03:12:55', '2026-05-20 00:46:44'),
(121, '7781-0000', 'VINf5c1d0371983f72bd6021777000375', 'ENGINEff52ab2a3f1f9498c318', 'Baurku', NULL, 48, 'Mitsubishi', 'Bus', '2007', 'Hitam', 'Roda 4', 'Solar', '7781-00', NULL, NULL, NULL, NULL, 'Satker', 'Setjen Kemhan', NULL, 'Baik', 'Operasional', 'Tersedia', NULL, NULL, 0, NULL, NULL, '2026-04-24 03:12:55', '2026-05-20 00:46:45'),
(122, '7700-0000', 'VIN9c33ccf2cbc8d6b18f691777000375', 'ENGINE1425d74d8398f0669580', 'Baurku', NULL, 49, 'Mitsubishi', 'Bus', '2011', 'Hitam', 'Roda 4', 'Solar', '7700-00', NULL, NULL, NULL, NULL, 'Satker', 'Setjen Kemhan', NULL, 'Baik', 'Operasional', 'Tersedia', NULL, NULL, 0, NULL, NULL, '2026-04-24 03:12:55', '2026-05-20 00:46:45'),
(123, '7701-00', 'VIN61777000375', 'Eb2450a962e9cfb93ed2', 'Baurku', '002', 50, 'Mitsubishi', 'Bus', '1996', 'Hitam', 'Roda 4', 'Solar', '7701-00', NULL, NULL, NULL, NULL, 'Satker', 'Setjen Kemhan', NULL, 'Baik', 'Operasional', 'Tersedia', NULL, NULL, 0, NULL, NULL, '2026-04-24 03:12:55', '2026-05-27 08:17:21'),
(124, '7702-0000', 'VIN322698bb4adf1e5c982f1777000375', 'ENGINEb8c5860d8ea76bfc513a', 'Baurku', NULL, 51, 'Hino', 'Bus', '2003', 'Hitam', 'Roda 4', 'Solar', '7702-00', NULL, NULL, NULL, NULL, 'Satker', 'Setjen Kemhan', NULL, 'Baik', 'Operasional', 'Tersedia', NULL, NULL, 0, NULL, NULL, '2026-04-24 03:12:55', '2026-05-13 09:37:56'),
(125, '7713-0000', 'VIN4413f140860f8ff9552e1777000376', 'ENGINE3d3e1c250de63133bed1', 'Baurku', NULL, 52, 'Hino', 'Bus', '2007', 'Hitam', 'Roda 4', 'Solar', '7713-00', NULL, NULL, NULL, NULL, 'Satker', 'Setjen Kemhan', NULL, 'Baik', 'Operasional', 'Tersedia', NULL, NULL, 0, NULL, NULL, '2026-04-24 03:12:56', '2026-05-13 09:37:56'),
(126, '7782-0000', 'VIN2c93763c5654774aa2b91777000376', 'ENGINE0313ffad5e8e1524cab8', 'Baurku', NULL, 53, 'Hino', 'Bus', '2011', 'Hitam', 'Roda 4', 'Solar', '7782-00', NULL, NULL, NULL, NULL, 'Satker', 'Setjen Kemhan', NULL, 'Baik', 'Operasional', 'Tersedia', NULL, NULL, 0, NULL, NULL, '2026-04-24 03:12:56', '2026-05-13 09:37:56'),
(127, '7703-0000', 'VIN46a74e8d23f4d5776fce1777000376', 'ENGINEd1344dbb19496020c145', 'Baurku', NULL, 54, 'Hino', 'Bus', '2015', 'Hitam', 'Roda 4', 'Solar', '7703-00', NULL, NULL, NULL, NULL, 'Satker', 'Setjen Kemhan', NULL, 'Baik', 'Operasional', 'Tersedia', NULL, NULL, 0, NULL, NULL, '2026-04-24 03:12:56', '2026-05-13 09:37:56'),
(129, '7707-0000', 'VIN7f50786883c6841512421777000376', 'ENGINE26bbc4ee1a6173ecf64d', 'Baurku', NULL, 56, 'Hino', 'Bus', '2007', 'Hitam', 'Roda 4', 'Solar', '7707-00', NULL, NULL, NULL, NULL, 'Satker', 'Setjen Kemhan', NULL, 'Baik', 'Operasional', 'Tersedia', NULL, NULL, 0, NULL, NULL, '2026-04-24 03:12:56', '2026-05-22 08:44:17'),
(130, '7708-0000', 'VIN38dad291b0b4d88fe8881777000376', 'ENGINE5c5b43b2ba4dd682c5bd', 'Baurku', NULL, 57, 'Hino', 'Bus', '2011', 'Hitam', 'Roda 4', 'Solar', '7708-00', NULL, NULL, NULL, NULL, 'Satker', 'Setjen Kemhan', NULL, 'Baik', 'Operasional', 'Tersedia', NULL, NULL, 0, NULL, NULL, '2026-04-24 03:12:56', '2026-05-13 09:37:56'),
(131, '7709-0000', 'VIN2d96fa03fd473b8ec0581777000376', 'ENGINE784238f4bb94cf69dc4a', 'Baurku', NULL, 58, 'Hino', 'Bus', '2015', 'Hitam', 'Roda 4', 'Solar', '7709-00', NULL, NULL, NULL, NULL, 'Satker', 'Setjen Kemhan', NULL, 'Baik', 'Operasional', 'Tersedia', NULL, NULL, 0, NULL, NULL, '2026-04-24 03:12:56', '2026-05-19 02:10:36'),
(132, '7720-0000', 'VINce949c5960c7ec61f7f31777000376', 'ENGINEa8e519486af2f010f863', 'Baurku', NULL, 59, 'Hino', 'Bus', '2003', 'Hitam', 'Roda 4', 'Solar', '7720-00', NULL, NULL, NULL, NULL, 'Satker', 'Setjen Kemhan', NULL, 'Baik', 'Operasional', 'Tersedia', NULL, NULL, 0, NULL, NULL, '2026-04-24 03:12:56', '2026-05-13 09:37:56'),
(133, '7711-0000', 'VINcf3f3cee0ff09b5ef87f1777000376', 'ENGINEf8c929dae4c41713e324', 'Baurku', NULL, 60, 'Hino', 'Bus', '2007', 'Hitam', 'Roda 4', 'Solar', '7711-00', NULL, NULL, NULL, NULL, 'Satker', 'Setjen Kemhan', NULL, 'Baik', 'Operasional', 'Tersedia', NULL, NULL, 0, NULL, NULL, '2026-04-24 03:12:56', '2026-05-13 09:37:56'),
(134, '7698-0000', 'VIN8198cb06991ac0b2ad771777000376', 'ENGINE62e1ee15ac78670c2783', 'Baurku', NULL, 61, 'Hino', 'Bus', '2011', 'Hitam', 'Roda 4', 'Solar', '7698-00', NULL, NULL, NULL, NULL, 'Satker', 'Setjen Kemhan', NULL, 'Baik', 'Operasional', 'Tersedia', NULL, NULL, 0, NULL, NULL, '2026-04-24 03:12:56', '2026-05-13 09:37:56'),
(135, '7710-0000', 'VIN6956dc19afed83440a211777000376', 'ENGINEa84d60ad3f85516b49f4', 'Baurku', NULL, 62, 'Hino', 'Bus', '2015', 'Hitam', 'Roda 4', 'Solar', '7710-00', NULL, NULL, NULL, NULL, 'Satker', 'Setjen Kemhan', NULL, 'Baik', 'Operasional', 'Tersedia', NULL, NULL, 0, NULL, NULL, '2026-04-24 03:12:56', '2026-05-13 09:37:56'),
(136, '7717-0000', 'VINf14094ceddf82b8fefe61777000376', 'ENGINE6a44cac54b1d312482d0', 'Baurku', NULL, 63, 'Hino', 'Bus', '2003', 'Hitam', 'Roda 4', 'Solar', '7717-00', NULL, NULL, NULL, NULL, 'Satker', 'Setjen Kemhan', NULL, 'Baik', 'Operasional', 'Tersedia', NULL, NULL, 0, NULL, NULL, '2026-04-24 03:12:56', '2026-05-13 09:37:56'),
(137, '7715-0000', 'VINb1802d61d461ed7a2e781777000377', 'ENGINEb01c225c81510f23bfae', 'Baurku', NULL, 64, 'Hino', 'Bus', '2007', 'Hitam', 'Roda 4', 'Solar', '7715-00', NULL, NULL, NULL, NULL, 'Satker', 'Setjen Kemhan', NULL, 'Baik', 'Operasional', 'Tersedia', NULL, NULL, 0, NULL, NULL, '2026-04-24 03:12:57', '2026-05-13 09:37:56'),
(138, '7696-0000', 'VIN2be50a980fe807aecb691777000377', 'ENGINEca977e1c08662638eb47', 'Baurku', NULL, 65, 'Hino', 'Bus', '2011', 'Hitam', 'Roda 4', 'Solar', '7696-00', NULL, NULL, NULL, NULL, 'Satker', 'Setjen Kemhan', NULL, 'Baik', 'Operasional', 'Tersedia', NULL, NULL, 0, NULL, NULL, '2026-04-24 03:12:57', '2026-05-20 00:43:18'),
(139, '7718-0000', 'VIN412dcbdd45ea021ce6911777000377', 'ENGINE5c6786d37ce94debcd17', 'Baurku', NULL, 66, 'Hino', 'Bus', '2015', 'Hitam', 'Roda 4', 'Solar', '7718-00', NULL, NULL, NULL, NULL, 'Satker', 'Setjen Kemhan', NULL, 'Baik', 'Operasional', 'Tersedia', NULL, NULL, 0, NULL, NULL, '2026-04-24 03:12:57', '2026-05-13 09:37:56'),
(140, '7719-0000', 'VIN1587d1363fb5bd6330d81777000377', 'ENGINE7be71f724ebd99883075', 'Baurku', NULL, 67, 'Hino', 'Bus', '2003', 'Hitam', 'Roda 4', 'Solar', '7719-00', NULL, NULL, NULL, NULL, 'Satker', 'Setjen Kemhan', NULL, 'Baik', 'Operasional', 'Tersedia', NULL, NULL, 0, NULL, NULL, '2026-04-24 03:12:57', '2026-05-13 09:37:56'),
(141, '7705-0000', 'VINe9f53cca20be390608bc1777000377', 'ENGINEd864e7b45e2a18906dc1', 'Baurku', NULL, 68, 'Hino', 'Bus', '2007', 'Hitam', 'Roda 4', 'Solar', '7705-00', NULL, NULL, NULL, NULL, 'Satker', 'Setjen Kemhan', NULL, 'Baik', 'Operasional', 'Tersedia', NULL, NULL, 0, NULL, NULL, '2026-04-24 03:12:57', '2026-05-13 09:37:56'),
(142, '7721-0000', 'VIN11b0dd1c049bf1df96461777000377', 'ENGINE987618a256ad292ac117', 'Baurku', NULL, 69, 'Hino', 'Bus', '2011', 'Hitam', 'Roda 4', 'Solar', '7721-00', NULL, NULL, NULL, NULL, 'Satker', 'Setjen Kemhan', NULL, 'Baik', 'Operasional', 'Tersedia', NULL, NULL, 0, NULL, NULL, '2026-04-24 03:12:57', '2026-05-13 09:37:56'),
(143, '7690-00', 'VIN0911546857000377', 'E8348ee26082e4b8cd4', 'Baurku', '004', 70, 'Mercedes benz', 'Big Bus', '1991', 'Hitam', 'Roda 4', 'Solar', '7690-00', NULL, NULL, NULL, NULL, 'Satker', 'Setjen Kemhan', NULL, 'Baik', 'Operasional', 'Tersedia', NULL, NULL, 0, NULL, NULL, '2026-04-24 03:12:57', '2026-05-13 07:25:17'),
(144, '7723-0000', 'VIN9dd734fc29df72dda6f41777000377', 'ENGINE0fa007a6b85439f359b2', 'Baurku', NULL, 71, 'Hino', 'Bus', '2003', 'Hitam', 'Roda 4', 'Solar', '7723-00', NULL, NULL, NULL, NULL, 'Satker', 'Setjen Kemhan', NULL, 'Baik', 'Operasional', 'Tersedia', NULL, NULL, 0, NULL, NULL, '2026-04-24 03:12:57', '2026-05-13 09:37:56'),
(145, '7803-0000', 'VINb33e99d92ac458ec19261777000377', 'ENGINEa7ce86823e2eb8f7858a', 'Baurku', NULL, 72, 'Hino', 'Bus', '2007', 'Hitam', 'Roda 4', 'Solar', '7803-00', NULL, NULL, NULL, NULL, 'Satker', 'Setjen Kemhan', NULL, 'Baik', 'Operasional', 'Tersedia', NULL, NULL, 0, NULL, NULL, '2026-04-24 03:12:57', '2026-05-13 09:37:56'),
(146, '7607-00', 'VI1777000378', 'E6f87552402932f84', 'Baurku', '006', 73, 'Mercedes benz', 'Big Bus', '1997', 'Hitam', 'Roda 4', 'Solar', '7607-00', NULL, NULL, NULL, NULL, 'Satker', 'Setjen Kemhan', NULL, 'Baik', 'Operasional', 'Tersedia', NULL, NULL, 0, NULL, NULL, '2026-04-24 03:12:58', '2026-05-19 02:10:36'),
(147, '7704-0000', 'VIN839138c739e704b32f861777000378', 'ENGINE793333bba2ea2fb431ed', 'Baurku', NULL, 74, 'Hino', 'Bus', '2015', 'Hitam', 'Roda 4', 'Solar', '7704-00', NULL, NULL, NULL, NULL, 'Satker', 'Setjen Kemhan', NULL, 'Baik', 'Operasional', 'Tersedia', NULL, NULL, 0, NULL, NULL, '2026-04-24 03:12:58', '2026-05-13 09:37:56'),
(148, '7804-0000', 'VIN86d7a4950fc01a1c0feb1777000378', 'ENGINEf23b23b39ebf60308ac6', 'Baurku', NULL, 75, 'Hino', 'Bus', '2003', 'Hitam', 'Roda 4', 'Solar', '7804-00', NULL, NULL, NULL, NULL, 'Satker', 'Setjen Kemhan', NULL, 'Baik', 'Operasional', 'Tersedia', NULL, NULL, 0, NULL, NULL, '2026-04-24 03:12:58', '2026-05-13 09:37:56'),
(149, '7724-0000', 'VINa081b2708a3013d9743c1777000378', 'ENGINE31ac63706897c13d5b32', 'Baurku', NULL, 76, 'Hino', 'Bus', '2007', 'Hitam', 'Roda 4', 'Solar', '7724-00', NULL, NULL, NULL, NULL, 'Satker', 'Setjen Kemhan', NULL, 'Baik', 'Operasional', 'Tersedia', NULL, NULL, 0, NULL, NULL, '2026-04-24 03:12:58', '2026-05-13 09:37:56'),
(150, '7726-00', 'V815343ed1777000378', 'E325253624e8738d556b1', 'Baurku', '005', 77, 'Hino', 'Bus', '2002', 'Hitam', 'Roda 4', 'Solar', '7726-00', NULL, NULL, NULL, NULL, 'Satker', 'Setjen Kemhan', NULL, 'Baik', 'Operasional', 'Tersedia', NULL, NULL, 0, NULL, NULL, '2026-04-24 03:12:58', '2026-05-07 10:10:28'),
(151, '7697-00', 'VIN92799300378', 'Ef3968b49910ed356e0', 'Baurku', '003', 78, 'Mitsubishi', 'Bus', '2000', 'Hitam', 'Roda 4', 'Solar', '7697-00', NULL, NULL, NULL, NULL, 'Satker', 'Setjen Kemhan', NULL, 'Baik', 'Operasional', 'Tersedia', NULL, NULL, 0, NULL, NULL, '2026-04-24 03:12:58', '2026-05-18 17:43:59'),
(152, '7807-00', 'VIN57a4a3c991777000378', 'Ed675de5be3ed08d852', 'Baurku', '01', 79, 'Hino', 'Bus', '2002', 'Hitam', 'Roda 4', 'Solar', '7807-00', NULL, NULL, NULL, NULL, 'Satker', 'Setjen Kemhan', NULL, 'Baik', 'Operasional', 'Tersedia', NULL, NULL, 0, NULL, NULL, '2026-04-24 03:12:58', '2026-05-06 09:21:43');

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
-- Dumping data untuk tabel `laporan_perjalanan`
--

INSERT INTO `laporan_perjalanan` (`id`, `tanggal`, `kendaraan_id`, `pengguna_id`, `uraian_kegiatan`, `route`, `jarak_km`, `created_at`, `updated_at`) VALUES
(20, '2026-05-15', 131, 58, 'Antar jemput pegawai', 'cikini||RW 07, Tomang, Grogol Petamburan, West Jakarta, Special Capital Region of Jakarta, Java, 11440, Indonesia', 9, '2026-05-16 04:50:00', '2026-05-19 10:20:33'),
(21, '2026-05-17', 123, 50, 'Antar jemput pegawai', 'Jalan Kramat Raya, RW 02, Senen, Central Jakarta, Special Capital Region of Jakarta, Java, 10410, Indonesia', 15, '2026-05-19 08:59:40', NULL),
(22, '2026-05-19', 116, 80, 'ajp', 'bogor', NULL, '2026-05-19 11:03:33', NULL),
(23, '2026-05-20', 77, 83, 'ajp', 'senen', 19, '2026-05-20 07:30:31', '2026-05-20 07:38:02'),
(24, '2026-05-07', 143, 70, '', 'Perumnas 3 Bekasi||Jalan Pulau Bangka 9, Wisma Jaya, Bulak Kapal, Duren Jaya, Bekasi, Jawa Barat, Jawa, 17111, Indonesia', NULL, '2026-05-20 08:10:19', NULL),
(25, '2026-05-20', 129, 80, 'ss', 'Bogor, West Java, Java, Indonesia', 112, '2026-05-22 15:44:17', NULL),
(26, '2026-05-22', 123, 80, 'as', 'cibinong', 87, '2026-05-27 15:17:21', NULL),
(27, '2026-05-22', 116, 80, 'sa', 'Bogor, Jawa Barat, Jawa, Indonesia', 112, '2026-05-27 15:17:37', NULL);

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
(31, 1, 'LOGIN', 'User berhasil login ke sistem', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/139.0.0.0 Safari/537.36', NULL, '2025-08-21 06:13:03'),
(32, 1, 'LOGIN', 'User berhasil login ke sistem', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/139.0.0.0 Safari/537.36', NULL, '2025-08-21 06:13:10'),
(33, 1, 'LOGOUT', 'User keluar dari sistem', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/139.0.0.0 Safari/537.36', NULL, '2025-08-21 06:15:49'),
(44, 1, 'LOGIN', 'User berhasil login ke sistem', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/139.0.0.0 Safari/537.36', NULL, '2025-08-21 07:14:28'),
(45, 1, 'LOGOUT', 'User keluar dari sistem', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/139.0.0.0 Safari/537.36', NULL, '2025-08-21 07:40:31'),
(58, 1, 'LOGIN', 'User berhasil login ke sistem', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/139.0.0.0 Safari/537.36', NULL, '2025-08-22 08:12:53'),
(65, 1, 'LOGOUT', 'User keluar dari sistem', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/139.0.0.0 Safari/537.36', NULL, '2025-08-22 08:33:45'),
(88, 1, 'LOGIN', 'User berhasil login ke sistem', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/139.0.0.0 Safari/537.36', NULL, '2025-08-25 04:26:47'),
(89, 1, 'LOGOUT', 'User keluar dari sistem', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/139.0.0.0 Safari/537.36', NULL, '2025-08-25 04:27:39'),
(106, 1, 'LOGIN', 'User berhasil login ke sistem', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/139.0.0.0 Safari/537.36', NULL, '2025-08-26 00:38:21'),
(107, 1, 'LOGOUT', 'User keluar dari sistem', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/139.0.0.0 Safari/537.36', NULL, '2025-08-26 00:41:28'),
(113, 1, 'LOGIN', 'User berhasil login ke sistem', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/139.0.0.0 Safari/537.36', NULL, '2025-08-26 00:57:22'),
(115, 1, 'UPDATE_MAINTENANCE_STATUS', 'Mengubah status jadwal perawatan Servis AC menjadi Dalam Proses', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/139.0.0.0 Safari/537.36', NULL, '2025-08-27 00:58:38'),
(116, 1, 'COMPLETE_MAINTENANCE', 'Menyelesaikan jadwal perawatan Servis AC untuk kendaraan B 9101 EF', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/139.0.0.0 Safari/537.36', NULL, '2025-08-27 01:07:44'),
(117, 1, 'UPDATE_MAINTENANCE_STATUS', 'Mengubah status jadwal perawatan Servis AC menjadi Dalam Proses', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/139.0.0.0 Safari/537.36', NULL, '2025-08-27 01:09:37'),
(118, 1, 'COMPLETE_MAINTENANCE', 'Menyelesaikan jadwal perawatan Servis AC untuk kendaraan B 1234 AB', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/139.0.0.0 Safari/537.36', NULL, '2025-08-27 01:09:43'),
(119, 1, 'UPDATE_MAINTENANCE_STATUS', 'Mengubah status jadwal perawatan Servis AC menjadi Dalam Proses', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/139.0.0.0 Safari/537.36', NULL, '2025-08-27 01:11:07'),
(120, 1, 'COMPLETE_MAINTENANCE', 'Menyelesaikan jadwal perawatan Servis AC untuk kendaraan B 1234 AB', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/139.0.0.0 Safari/537.36', NULL, '2025-08-27 01:11:11'),
(121, 1, 'COMPLETE_MAINTENANCE', 'Menyelesaikan jadwal perawatan Servis AC untuk kendaraan B 1234 AB', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/139.0.0.0 Safari/537.36', NULL, '2025-08-27 01:36:17'),
(127, 1, 'LOGIN', 'User berhasil login ke sistem', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/139.0.0.0 Safari/537.36 Edg/139.0.0.0', NULL, '2025-08-28 01:55:33'),
(129, 1, 'LOGIN', 'User berhasil login ke sistem', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/139.0.0.0 Safari/537.36', NULL, '2025-08-28 04:05:20'),
(130, 1, 'UPDATE_MAINTENANCE_STATUS', 'Mengubah status jadwal perawatan xz menjadi Dalam Proses', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/139.0.0.0 Safari/537.36', NULL, '2025-08-28 06:26:19'),
(131, 1, 'UPDATE_MAINTENANCE_STATUS', 'Mengubah status jadwal perawatan Servis AC menjadi Dalam Proses', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/139.0.0.0 Safari/537.36', NULL, '2025-08-28 06:27:22'),
(132, 1, 'COMPLETE_MAINTENANCE', 'Menyelesaikan jadwal perawatan Servis AC untuk kendaraan TEST123', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/139.0.0.0 Safari/537.36', NULL, '2025-08-28 07:07:00'),
(133, 1, 'COMPLETE_MAINTENANCE', 'Menyelesaikan jadwal perawatan xz untuk kendaraan TEST123', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/139.0.0.0 Safari/537.36', NULL, '2025-08-28 07:25:04'),
(140, 1, 'COMPLETE_MAINTENANCE', 'Menyelesaikan jadwal perawatan Servis AC untuk kendaraan B 1234 AB', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/139.0.0.0 Safari/537.36', NULL, '2025-08-29 07:45:58'),
(142, 1, 'COMPLETE_MAINTENANCE', 'Menyelesaikan jadwal perawatan asd untuk kendaraan TEST123', NULL, NULL, NULL, '2025-08-29 07:59:03'),
(153, 4, 'LOGIN', 'User berhasil login ke sistem', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/139.0.0.0 Safari/537.36 Edg/139.0.0.0', NULL, '2025-08-30 09:43:52'),
(154, 4, 'LOGOUT', 'User keluar dari sistem', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/139.0.0.0 Safari/537.36 Edg/139.0.0.0', NULL, '2025-08-30 09:44:22'),
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
(181, 1, 'CREATE_USER', 'Admin membuat user: pns', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/139.0.0.0 Safari/537.36', NULL, '2025-09-01 03:42:47'),
(183, 1, 'RESET_PASSWORD', 'Admin mereset password user: pns', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/139.0.0.0 Safari/537.36', NULL, '2025-09-01 03:56:21'),
(186, 1, 'CREATE_USER', 'Admin membuat user: tni', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/139.0.0.0 Safari/537.36', NULL, '2025-09-01 03:58:15'),
(189, 1, 'COMPLETE_MAINTENANCE', 'Menyelesaikan jadwal perawatan Tune Up untuk kendaraan B1234CD', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/139.0.0.0 Safari/537.36', NULL, '2025-09-01 05:09:35'),
(204, 1, 'EDIT_USER', 'Admin mengubah user id: 6', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/139.0.0.0 Safari/537.36', NULL, '2025-09-01 06:34:17'),
(205, 1, 'UPDATE_VEHICLE', 'Mengupdate kendaraan: S 1234 CD - Toyota', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/139.0.0.0 Safari/537.36', NULL, '2025-09-01 07:01:59'),
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
(226, 1, 'COMPLETE_MAINTENANCE', 'Menyelesaikan jadwal perawatan as untuk kendaraan S 1234 CD', NULL, NULL, NULL, '2025-09-02 06:21:54'),
(243, 1, 'UPDATE_MAINTENANCE_STATUS', 'Mengubah status jadwal perawatan ss menjadi Dalam Proses', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/139.0.0.0 Safari/537.36', NULL, '2025-09-03 00:42:43'),
(245, 4, 'LOGIN', 'User berhasil login ke sistem', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/139.0.0.0 Safari/537.36 Edg/139.0.0.0', NULL, '2025-09-03 01:30:41'),
(246, 4, 'LOGOUT', 'User keluar dari sistem', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/139.0.0.0 Safari/537.36 Edg/139.0.0.0', NULL, '2025-09-03 01:30:45'),
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
(265, 1, 'COMPLETE_MAINTENANCE', 'Menyelesaikan jadwal perawatan s untuk kendaraan C9012GH', NULL, NULL, NULL, '2025-09-03 02:46:34'),
(266, 1, 'UPDATE_VEHICLE_STATUS_BY_MAINTENANCE', 'Set kendaraan ID 26 menjadi Operasional/Tersedia setelah perawatan selesai', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/139.0.0.0 Safari/537.36', NULL, '2025-09-03 02:46:34'),
(267, 1, 'COMPLETE_MAINTENANCE', 'Menandai jadwal perawatan selesai: s untuk kendaraan C9012GH', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/139.0.0.0 Safari/537.36', NULL, '2025-09-03 02:46:34'),
(268, 1, 'UPDATE_VEHICLE_STATUS_BY_MAINTENANCE', 'Set kendaraan ID 3 menjadi Perbaikan/Maintenance saat perawatan dimulai', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/139.0.0.0 Safari/537.36', NULL, '2025-09-03 02:47:35'),
(269, 1, 'UPDATE_MAINTENANCE_STATUS', 'Mengubah status jadwal perawatan ddd menjadi Dalam Proses', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/139.0.0.0 Safari/537.36', NULL, '2025-09-03 02:47:35'),
(270, 1, 'COMPLETE_MAINTENANCE', 'Menyelesaikan jadwal perawatan ddd untuk kendaraan B 9101 EF', NULL, NULL, NULL, '2025-09-03 03:43:39'),
(271, 1, 'UPDATE_VEHICLE_STATUS_BY_MAINTENANCE', 'Set kendaraan ID 3 menjadi Operasional/Tersedia setelah perawatan selesai', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/139.0.0.0 Safari/537.36', NULL, '2025-09-03 03:43:39'),
(272, 1, 'COMPLETE_MAINTENANCE', 'Menandai jadwal perawatan selesai: ddd untuk kendaraan B 9101 EF', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/139.0.0.0 Safari/537.36', NULL, '2025-09-03 03:43:39'),
(277, 1, 'LOGOUT', 'User keluar dari sistem', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/139.0.0.0 Safari/537.36', NULL, '2025-09-03 12:37:59'),
(278, 1, 'LOGIN', 'User berhasil login ke sistem', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/139.0.0.0 Safari/537.36', NULL, '2025-09-03 12:39:58'),
(279, 1, 'LOGOUT', 'User keluar dari sistem', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/139.0.0.0 Safari/537.36', NULL, '2025-09-03 12:42:45'),
(282, 1, 'LOGIN', 'User berhasil login ke sistem', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/139.0.0.0 Safari/537.36', NULL, '2025-09-03 12:58:01'),
(283, 1, 'EDIT_USER', 'Admin mengubah user id: 6', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/139.0.0.0 Safari/537.36', NULL, '2025-09-03 12:58:22'),
(285, 1, 'LOGIN', 'User berhasil login ke sistem', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/139.0.0.0 Safari/537.36', NULL, '2025-09-04 00:34:49'),
(293, 1, 'LOGIN', 'User berhasil login ke sistem', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/139.0.0.0 Safari/537.36', NULL, '2025-09-04 00:42:27'),
(294, 1, 'UPDATE_VEHICLE', 'Mengupdate kendaraan: TEST123 - Test Brand', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/139.0.0.0 Safari/537.36', NULL, '2025-09-04 01:08:07'),
(295, 1, 'UPDATE_VEHICLE', 'Mengupdate kendaraan: TEST123 - Test Brand', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/139.0.0.0 Safari/537.36', NULL, '2025-09-04 01:08:27'),
(296, 1, 'UPDATE_VEHICLE', 'Mengupdate kendaraan: TEST123 - Test Brand', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/139.0.0.0 Safari/537.36', NULL, '2025-09-04 01:09:07'),
(298, 1, 'LOGIN', 'User berhasil login ke sistem', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/139.0.0.0 Safari/537.36 Edg/139.0.0.0', NULL, '2025-09-04 01:53:31'),
(299, 1, 'RESET_PASSWORD', 'Admin mereset password user: pns', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/139.0.0.0 Safari/537.36 Edg/139.0.0.0', NULL, '2025-09-04 01:54:07'),
(300, 1, 'CREATE_USER', 'Admin membuat user: asn', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/139.0.0.0 Safari/537.36 Edg/139.0.0.0', NULL, '2025-09-04 01:55:17'),
(301, 1, 'COMPLETE_MAINTENANCE', 'Menyelesaikan jadwal perawatan aa untuk kendaraan TEST123', NULL, NULL, NULL, '2025-09-04 03:05:56'),
(302, 1, 'UPDATE_VEHICLE', 'Mengupdate kendaraan: TEST123 - Test Brand', '::1', 'Mozilla/5.0 (Linux; Android 6.0; Nexus 5 Build/MRA58N) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/139.0.0.0 Mobile Safari/537.36', NULL, '2025-09-04 05:14:22'),
(303, 1, 'LOGOUT', 'User keluar dari sistem', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/139.0.0.0 Safari/537.36', NULL, '2025-09-04 05:19:09'),
(304, 1, 'UPDATE_VEHICLE', 'Mengupdate kendaraan: S 1234 CD - Toyota', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/139.0.0.0 Safari/537.36 Edg/139.0.0.0', NULL, '2025-09-04 05:21:46'),
(305, 1, 'UPDATE_VEHICLE', 'Mengupdate kendaraan: TEST123 - Test Brand', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/139.0.0.0 Safari/537.36 Edg/139.0.0.0', NULL, '2025-09-04 05:22:04'),
(308, 1, 'EDIT_USER', 'Admin mengubah user id: 2', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/139.0.0.0 Safari/537.36 Edg/139.0.0.0', NULL, '2025-09-04 08:01:20'),
(311, 1, 'EDIT_USER', 'Admin mengubah user id: 6', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/139.0.0.0 Safari/537.36 Edg/139.0.0.0', NULL, '2025-09-04 08:31:52'),
(312, 1, 'UPDATE_VEHICLE', 'Mengupdate kendaraan: B 1234 AC - Toyota', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/139.0.0.0 Safari/537.36', NULL, '2025-09-08 01:37:34'),
(314, 1, 'LOGIN', 'User berhasil login ke sistem', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/140.0.0.0 Safari/537.36', NULL, '2025-09-08 04:12:38'),
(315, 1, 'EDIT_USER', 'Admin mengubah user id: 1', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/139.0.0.0 Safari/537.36', NULL, '2025-09-08 06:45:53'),
(317, 1, 'LOGOUT', 'User keluar dari sistem', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/140.0.0.0 Safari/537.36', NULL, '2025-09-08 07:27:20'),
(319, 1, 'LOGIN', 'User berhasil login ke sistem', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/140.0.0.0 Safari/537.36 Edg/140.0.0.0', NULL, '2025-09-08 07:29:57'),
(320, 1, 'LOGOUT', 'User keluar dari sistem', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/139.0.0.0 Safari/537.36', NULL, '2025-09-08 07:35:50'),
(322, 1, 'LOGIN', 'User berhasil login ke sistem', '192.168.11.33', 'Mozilla/5.0 (Linux; Android 10; K) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/121.0.0.0 Mobile Safari/537.36', NULL, '2025-09-08 08:54:18'),
(325, 1, 'LOGIN', 'User berhasil login ke sistem', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/139.0.0.0 Safari/537.36', NULL, '2025-09-09 01:14:01'),
(327, 1, 'UPDATE_VEHICLE_STATUS_BY_MAINTENANCE', 'Set kendaraan ID 17 menjadi Perbaikan/Maintenance saat perawatan dimulai', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/139.0.0.0 Safari/537.36', NULL, '2025-09-09 01:58:01'),
(328, 1, 'UPDATE_MAINTENANCE_STATUS', 'Mengubah status jadwal perawatan Jjkkk menjadi Dalam Proses', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/139.0.0.0 Safari/537.36', NULL, '2025-09-09 01:58:01'),
(332, 1, 'UPDATE_VEHICLE', 'Mengupdate kendaraan: B 5678 CD - Honda', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/139.0.0.0 Safari/537.36', NULL, '2025-09-09 05:15:59'),
(333, 1, 'UPDATE_VEHICLE', 'Mengupdate kendaraan: 8545-08 - Inova', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/139.0.0.0 Safari/537.36', NULL, '2025-09-09 08:13:35'),
(334, 1, 'UPDATE_VEHICLE', 'Mengupdate kendaraan: 8811-01 - Mitsubishi', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/139.0.0.0 Safari/537.36', NULL, '2025-09-09 08:14:11'),
(335, 1, 'UPDATE_VEHICLE', 'Mengupdate kendaraan: 8811-01 - Mitsubishi', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/139.0.0.0 Safari/537.36', NULL, '2025-09-10 01:11:42'),
(339, 1, 'EDIT_USER', 'Admin mengubah user id: 1', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/139.0.0.0 Safari/537.36', NULL, '2025-09-10 02:35:28'),
(343, 1, 'LOGIN', 'User berhasil login ke sistem', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/139.0.0.0 Safari/537.36', NULL, '2025-09-10 04:31:10'),
(344, 1, 'COMPLETE_MAINTENANCE', 'Menyelesaikan jadwal perawatan Jjkkk untuk kendaraan 8545-08', NULL, NULL, NULL, '2025-09-10 04:31:22'),
(345, 1, 'UPDATE_VEHICLE', 'Mengupdate kendaraan: 8545-08 - Inova', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/139.0.0.0 Safari/537.36', NULL, '2025-09-10 04:32:26'),
(346, 1, 'UPDATE_VEHICLE', 'Mengupdate kendaraan: 8545-08 - Inova', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/139.0.0.0 Safari/537.36', NULL, '2025-09-10 04:40:49'),
(347, 1, 'EDIT_USER', 'Admin mengubah user id: 1', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/139.0.0.0 Safari/537.36', NULL, '2025-09-10 04:41:48'),
(348, 1, 'EDIT_USER', 'Admin mengubah user id: 2', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/139.0.0.0 Safari/537.36', NULL, '2025-09-10 04:42:21'),
(349, 1, 'EDIT_USER', 'Admin mengubah user id: 7', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/139.0.0.0 Safari/537.36', NULL, '2025-09-10 04:42:57'),
(350, 1, 'UPDATE_VEHICLE', 'Mengupdate kendaraan: 8545-08 - Inova', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/139.0.0.0 Safari/537.36', NULL, '2025-09-10 04:43:24'),
(351, 1, 'UPDATE_VEHICLE', 'Mengupdate kendaraan: 8545-08 - Inova', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/139.0.0.0 Safari/537.36', NULL, '2025-09-10 04:54:57'),
(352, 1, 'UPDATE_VEHICLE', 'Mengupdate kendaraan: 8811-01 - Mitsubishi', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/139.0.0.0 Safari/537.36', NULL, '2025-09-10 05:02:35'),
(356, 1, 'UPDATE_VEHICLE', 'Mengupdate kendaraan: 8667-08 - Test Brand', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/139.0.0.0 Safari/537.36', NULL, '2025-09-10 06:46:33'),
(357, 1, 'UPDATE_VEHICLE', 'Mengupdate kendaraan: 8667-08 - Test Brand', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/139.0.0.0 Safari/537.36', NULL, '2025-09-10 06:50:10'),
(358, 1, 'UPDATE_VEHICLE', 'Mengupdate kendaraan: 8667-08 - Test Brand', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/139.0.0.0 Safari/537.36', NULL, '2025-09-10 06:51:38'),
(359, 1, 'UPDATE_VEHICLE', 'Mengupdate kendaraan: 8667-08 - Test Brand', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/139.0.0.0 Safari/537.36', NULL, '2025-09-10 06:52:06'),
(360, 1, 'EDIT_USER', 'Admin mengubah user id: 3', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/139.0.0.0 Safari/537.36', NULL, '2025-09-10 06:53:11'),
(361, 1, 'UPDATE_VEHICLE', 'Mengupdate kendaraan: 8272-02 - Toyota', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/139.0.0.0 Safari/537.36', NULL, '2025-09-10 06:53:44'),
(363, 1, 'UPDATE_VEHICLE', 'Mengupdate kendaraan: 8272-02 - Toyota', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/139.0.0.0 Safari/537.36', NULL, '2025-09-10 06:58:28'),
(364, 1, 'UPDATE_VEHICLE', 'Mengupdate kendaraan: 0200-08 - Lexus', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/139.0.0.0 Safari/537.36', NULL, '2025-09-10 07:33:11'),
(365, 1, 'EDIT_USER', 'Admin mengubah user id: 4', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/139.0.0.0 Safari/537.36', NULL, '2025-09-10 07:34:15'),
(366, 1, 'EDIT_USER', 'Admin mengubah user id: 4', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/139.0.0.0 Safari/537.36', NULL, '2025-09-10 07:34:30'),
(367, 1, 'UPDATE_VEHICLE', 'Mengupdate kendaraan: 0822-02 - Honda', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/139.0.0.0 Safari/537.36', NULL, '2025-09-10 07:34:57'),
(368, 1, 'UPDATE_VEHICLE', 'Mengupdate kendaraan: 8272-02 - Toyota', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/139.0.0.0 Safari/537.36', NULL, '2025-09-10 07:35:30'),
(369, 1, 'UPDATE_VEHICLE', 'Mengupdate kendaraan: 8272-02 - Toyota', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/139.0.0.0 Safari/537.36', NULL, '2025-09-10 07:35:42'),
(370, 1, 'IMPORT_USER', 'Import user: john.doe', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/139.0.0.0 Safari/537.36', NULL, '2025-09-10 08:19:19'),
(371, 1, 'IMPORT_USER', 'Import user: qws.sds', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/139.0.0.0 Safari/537.36', NULL, '2025-09-10 08:19:19'),
(372, 1, 'IMPORT_USER', 'Import user: res.tu', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/139.0.0.0 Safari/537.36', NULL, '2025-09-10 08:19:19'),
(373, 1, 'IMPORT_USER', 'Import user: soleh.h', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/139.0.0.0 Safari/537.36', NULL, '2025-09-10 08:19:19'),
(374, 1, 'IMPORT_USER', 'Import user: bruce.lee', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/139.0.0.0 Safari/537.36', NULL, '2025-09-10 08:19:19'),
(375, 1, 'IMPORT_USER', 'Import user: ehud', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/139.0.0.0 Safari/537.36', NULL, '2025-09-10 08:19:19'),
(377, 1, 'RESET_PASSWORD', 'Admin mereset password user: soleh.h', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/139.0.0.0 Safari/537.36', NULL, '2025-09-10 08:24:03'),
(379, 1, 'UPDATE_VEHICLE', 'Mengupdate kendaraan: 0822-02 - Honda', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/139.0.0.0 Safari/537.36', NULL, '2025-09-10 08:32:42'),
(380, 1, 'CHANGE_USER_STATUS', 'Admin mengubah status user menjadi: Nonaktif', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/139.0.0.0 Safari/537.36', NULL, '2025-09-11 00:44:07'),
(381, 1, 'CHANGE_USER_STATUS', 'Admin mengubah status user menjadi: Aktif', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/139.0.0.0 Safari/537.36', NULL, '2025-09-11 00:44:12'),
(382, 1, 'CHANGE_USER_STATUS', 'Admin mengubah status user menjadi: Aktif', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/139.0.0.0 Safari/537.36', NULL, '2025-09-11 00:44:15'),
(384, 1, 'CHANGE_USER_STATUS', 'Admin mengubah status user menjadi: Nonaktif', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/139.0.0.0 Safari/537.36', NULL, '2025-09-11 02:04:00'),
(385, 1, 'EDIT_USER', 'Admin mengubah user id: 8', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/139.0.0.0 Safari/537.36', NULL, '2025-09-11 02:04:11'),
(386, 1, 'CHANGE_USER_STATUS', 'Admin mengubah status user menjadi: Aktif', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/139.0.0.0 Safari/537.36', NULL, '2025-09-11 02:04:19'),
(387, 1, 'EDIT_USER', 'Admin mengubah user id: 8', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/139.0.0.0 Safari/537.36', NULL, '2025-09-11 02:04:19'),
(388, 1, 'CHANGE_USER_STATUS', 'Admin mengubah status user menjadi: Aktif', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/139.0.0.0 Safari/537.36', NULL, '2025-09-11 02:04:24'),
(389, 1, 'EDIT_USER', 'Admin mengubah user id: 8', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/139.0.0.0 Safari/537.36', NULL, '2025-09-11 02:04:24'),
(390, 1, 'EDIT_USER', 'Admin mengubah user id: 8', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/139.0.0.0 Safari/537.36', NULL, '2025-09-11 02:04:33'),
(391, 1, 'CHANGE_USER_STATUS', 'Admin mengubah status user menjadi: Aktif', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/139.0.0.0 Safari/537.36', NULL, '2025-09-11 02:04:37'),
(392, 1, 'EDIT_USER', 'Admin mengubah user id: 8', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/139.0.0.0 Safari/537.36', NULL, '2025-09-11 02:04:37'),
(393, 1, 'EDIT_USER', 'Admin mengubah user id: 8', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/139.0.0.0 Safari/537.36', NULL, '2025-09-11 02:04:48'),
(394, 1, 'UPDATE_VEHICLE', 'Mengupdate kendaraan: 8545-08 - Inova', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/139.0.0.0 Safari/537.36', NULL, '2025-09-11 03:06:44'),
(395, 1, 'UPDATE_VEHICLE', 'Mengupdate kendaraan: 8811-01 - Mitsubishi', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/139.0.0.0 Safari/537.36', NULL, '2025-09-11 03:07:12'),
(399, 1, 'IMPORT_VEHICLE', 'Import kendaraan: 3554-19 - Suzuki Baleno', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/139.0.0.0 Safari/537.36', NULL, '2025-09-12 03:08:24'),
(400, 1, 'IMPORT_VEHICLE', 'Import kendaraan: 5913-19 - Kuda', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/139.0.0.0 Safari/537.36', NULL, '2025-09-12 03:08:24'),
(401, 1, 'IMPORT_VEHICLE', 'Import kendaraan: 5914-19 - Kuda', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/139.0.0.0 Safari/537.36', NULL, '2025-09-12 03:08:25'),
(402, 1, 'IMPORT_VEHICLE', 'Import kendaraan: 3521-19 - Hyosung', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/139.0.0.0 Safari/537.36', NULL, '2025-09-12 03:08:25'),
(403, 1, 'IMPORT_VEHICLE', 'Import kendaraan: 3523-19 - Honda', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/139.0.0.0 Safari/537.36', NULL, '2025-09-12 03:08:25'),
(404, 1, 'IMPORT_VEHICLE', 'Import kendaraan: 2266-19 - Honda', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/139.0.0.0 Safari/537.36', NULL, '2025-09-12 03:08:25'),
(405, 1, 'IMPORT_VEHICLE', 'Import kendaraan: 2267-19 - Honda', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/139.0.0.0 Safari/537.36', NULL, '2025-09-12 03:08:25'),
(406, 1, 'IMPORT_VEHICLE', 'Import kendaraan: 2268-19 - Honda', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/139.0.0.0 Safari/537.36', NULL, '2025-09-12 03:08:25'),
(407, 1, 'IMPORT_VEHICLE', 'Import kendaraan: 2272-19 - Honda', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/139.0.0.0 Safari/537.36', NULL, '2025-09-12 04:39:18'),
(408, 1, 'IMPORT_VEHICLE', 'Import kendaraan: 2270-19 - Hyosung', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/139.0.0.0 Safari/537.36', NULL, '2025-09-12 04:39:18'),
(409, 1, 'IMPORT_VEHICLE', 'Import kendaraan: 2271-19 - Suzuki', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/139.0.0.0 Safari/537.36', NULL, '2025-09-12 04:39:18'),
(410, 1, 'IMPORT_VEHICLE', 'Import kendaraan: 2269-19 - Suzuki', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/139.0.0.0 Safari/537.36', NULL, '2025-09-12 04:39:18'),
(411, 1, 'IMPORT_VEHICLE', 'Import kendaraan: 5677-19 - Toyota Avanza', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/139.0.0.0 Safari/537.36', NULL, '2025-09-12 04:39:18'),
(412, 1, 'IMPORT_VEHICLE', 'Import kendaraan: 5675-19 - Toyota Avanza', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/139.0.0.0 Safari/537.36', NULL, '2025-09-12 04:39:18'),
(413, 1, 'IMPORT_VEHICLE', 'Import kendaraan: 5676-19 - Toyota Avanza', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/139.0.0.0 Safari/537.36', NULL, '2025-09-12 04:39:18'),
(414, 1, 'IMPORT_VEHICLE', 'Import kendaraan: 5678-19 - Toyota Avanza', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/139.0.0.0 Safari/537.36', NULL, '2025-09-12 04:39:18'),
(415, 1, 'IMPORT_VEHICLE', 'Import kendaraan: 5679-19 - Toyota Avanza', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/139.0.0.0 Safari/537.36', NULL, '2025-09-12 04:39:18'),
(416, 1, 'IMPORT_VEHICLE', 'Import kendaraan: 2272-19 - Honda', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/139.0.0.0 Safari/537.36', NULL, '2025-09-12 04:43:45'),
(417, 1, 'IMPORT_VEHICLE', 'Import kendaraan: 2270-19 - Hyosung', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/139.0.0.0 Safari/537.36', NULL, '2025-09-12 04:43:45'),
(418, 1, 'IMPORT_VEHICLE', 'Import kendaraan: 2271-19 - Suzuki', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/139.0.0.0 Safari/537.36', NULL, '2025-09-12 04:43:45'),
(419, 1, 'IMPORT_VEHICLE', 'Import kendaraan: 2269-19 - Suzuki', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/139.0.0.0 Safari/537.36', NULL, '2025-09-12 04:43:45'),
(420, 1, 'IMPORT_VEHICLE', 'Import kendaraan: 5677-19 - Toyota Avanza', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/139.0.0.0 Safari/537.36', NULL, '2025-09-12 04:43:45'),
(421, 1, 'IMPORT_VEHICLE', 'Import kendaraan: 5675-19 - Toyota Avanza', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/139.0.0.0 Safari/537.36', NULL, '2025-09-12 04:43:45'),
(422, 1, 'IMPORT_VEHICLE', 'Import kendaraan: 5676-19 - Toyota Avanza', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/139.0.0.0 Safari/537.36', NULL, '2025-09-12 04:43:45'),
(423, 1, 'IMPORT_VEHICLE', 'Import kendaraan: 5678-19 - Toyota Avanza', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/139.0.0.0 Safari/537.36', NULL, '2025-09-12 04:43:45'),
(424, 1, 'IMPORT_VEHICLE', 'Import kendaraan: 5679-19 - Toyota Avanza', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/139.0.0.0 Safari/537.36', NULL, '2025-09-12 04:43:45'),
(425, 1, 'IMPORT_VEHICLE', 'Import kendaraan: 2266-19 - Honda', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/139.0.0.0 Safari/537.36', NULL, '2025-09-12 04:43:45'),
(426, 1, 'IMPORT_VEHICLE', 'Import kendaraan: 2265-19 - Honda', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/139.0.0.0 Safari/537.36', NULL, '2025-09-12 04:43:45'),
(427, 1, 'IMPORT_VEHICLE', 'Import kendaraan: 2267-19 - Honda', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/139.0.0.0 Safari/537.36', NULL, '2025-09-12 04:43:45'),
(428, 1, 'IMPORT_VEHICLE', 'Import kendaraan: 2268-19 - Honda', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/139.0.0.0 Safari/537.36', NULL, '2025-09-12 04:43:45'),
(429, 1, 'IMPORT_VEHICLE', 'Import kendaraan: 5681-19 - Toyota Avanza', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/139.0.0.0 Safari/537.36', NULL, '2025-09-12 04:43:45'),
(430, 1, 'IMPORT_VEHICLE', 'Import kendaraan: 5682-19 - Toyota Avanza', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/139.0.0.0 Safari/537.36', NULL, '2025-09-12 04:43:45'),
(431, 1, 'IMPORT_VEHICLE', 'Import kendaraan: 5680-19 - Daihatsu Xenia', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/139.0.0.0 Safari/537.36', NULL, '2025-09-12 04:43:45'),
(432, 1, 'IMPORT_VEHICLE', 'Import kendaraan: 5913-19 - Mitsubishi Kuda', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/139.0.0.0 Safari/537.36', NULL, '2025-09-12 04:43:45'),
(433, 1, 'IMPORT_VEHICLE', 'Import kendaraan: 5914-19 - Mitsubishi Kuda', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/139.0.0.0 Safari/537.36', NULL, '2025-09-12 04:43:45'),
(434, 1, 'IMPORT_VEHICLE', 'Import kendaraan: 3554-19 - Suzuki Baleno', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/139.0.0.0 Safari/537.36', NULL, '2025-09-12 04:43:45'),
(435, 1, 'IMPORT_VEHICLE', 'Import kendaraan: 7375-19 - Hino FB 13D', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/139.0.0.0 Safari/537.36', NULL, '2025-09-12 04:43:45'),
(436, 1, 'IMPORT_VEHICLE', 'Import kendaraan: 5683-19 - Mitsubishi Xpander', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/139.0.0.0 Safari/537.36', NULL, '2025-09-12 04:43:45'),
(437, 1, 'IMPORT_VEHICLE', 'Import kendaraan: 5684-19 - Mitsubishi Xpander', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/139.0.0.0 Safari/537.36', NULL, '2025-09-12 04:43:45'),
(438, 1, 'IMPORT_VEHICLE', 'Import kendaraan: 9311-19 - Mitsubishi Triton', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/139.0.0.0 Safari/537.36', NULL, '2025-09-12 04:43:45'),
(439, 1, 'IMPORT_VEHICLE', 'Import kendaraan: 3521-19 - Toyota Vios', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/139.0.0.0 Safari/537.36', NULL, '2025-09-12 04:43:45'),
(440, 1, 'IMPORT_VEHICLE', 'Import kendaraan: 3523-19 - Toyota Vios', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/139.0.0.0 Safari/537.36', NULL, '2025-09-12 04:43:45'),
(441, 1, 'IMPORT_VEHICLE', 'Import kendaraan: 3522-19 - Toyota Vios', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/139.0.0.0 Safari/537.36', NULL, '2025-09-12 04:43:46'),
(442, 1, 'IMPORT_VEHICLE', 'Import kendaraan: 3525-19 - Toyota Altis', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/139.0.0.0 Safari/537.36', NULL, '2025-09-12 04:43:46'),
(443, 1, 'IMPORT_VEHICLE', 'Import kendaraan: 3527-19 - Toyota Vios', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/139.0.0.0 Safari/537.36', NULL, '2025-09-12 04:43:46'),
(444, 1, 'UPDATE_VEHICLE', 'Mengupdate kendaraan: 7375-19 - Hino FB 13D', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/139.0.0.0 Safari/537.36', NULL, '2025-09-12 04:51:14'),
(445, 1, 'IMPORT_VEHICLE', 'Import kendaraan: 2272-19 - Honda', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/139.0.0.0 Safari/537.36', NULL, '2025-09-12 05:23:43'),
(446, 1, 'IMPORT_VEHICLE', 'Import kendaraan: 2270-19 - Hyosung', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/139.0.0.0 Safari/537.36', NULL, '2025-09-12 05:23:43'),
(447, 1, 'IMPORT_VEHICLE', 'Import kendaraan: 2271-19 - Suzuki', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/139.0.0.0 Safari/537.36', NULL, '2025-09-12 05:23:43'),
(448, 1, 'IMPORT_VEHICLE', 'Import kendaraan: 2269-19 - Suzuki', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/139.0.0.0 Safari/537.36', NULL, '2025-09-12 05:23:43'),
(449, 1, 'IMPORT_VEHICLE', 'Import kendaraan: 5677-19 - Toyota Avanza', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/139.0.0.0 Safari/537.36', NULL, '2025-09-12 05:23:43'),
(450, 1, 'IMPORT_VEHICLE', 'Import kendaraan: 5675-19 - Toyota Avanza', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/139.0.0.0 Safari/537.36', NULL, '2025-09-12 05:23:43'),
(451, 1, 'IMPORT_VEHICLE', 'Import kendaraan: 5676-19 - Toyota Avanza', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/139.0.0.0 Safari/537.36', NULL, '2025-09-12 05:23:43'),
(452, 1, 'IMPORT_VEHICLE', 'Import kendaraan: 5678-19 - Toyota Avanza', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/139.0.0.0 Safari/537.36', NULL, '2025-09-12 05:23:43');
INSERT INTO `log_aktivitas` (`id`, `user_id`, `activity_type`, `description`, `ip_address`, `user_agent`, `session_id`, `created_at`) VALUES
(453, 1, 'IMPORT_VEHICLE', 'Import kendaraan: 5679-19 - Toyota Avanza', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/139.0.0.0 Safari/537.36', NULL, '2025-09-12 05:23:43'),
(454, 1, 'IMPORT_VEHICLE', 'Import kendaraan: 2266-19 - Honda', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/139.0.0.0 Safari/537.36', NULL, '2025-09-12 05:23:43'),
(455, 1, 'IMPORT_VEHICLE', 'Import kendaraan: 2265-19 - Honda', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/139.0.0.0 Safari/537.36', NULL, '2025-09-12 05:23:43'),
(456, 1, 'IMPORT_VEHICLE', 'Import kendaraan: 2267-19 - Honda', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/139.0.0.0 Safari/537.36', NULL, '2025-09-12 05:23:43'),
(457, 1, 'IMPORT_VEHICLE', 'Import kendaraan: 2268-19 - Honda', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/139.0.0.0 Safari/537.36', NULL, '2025-09-12 05:23:43'),
(458, 1, 'IMPORT_VEHICLE', 'Import kendaraan: 5681-19 - Toyota Avanza', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/139.0.0.0 Safari/537.36', NULL, '2025-09-12 05:23:43'),
(459, 1, 'IMPORT_VEHICLE', 'Import kendaraan: 5682-19 - Toyota Avanza', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/139.0.0.0 Safari/537.36', NULL, '2025-09-12 05:23:43'),
(460, 1, 'IMPORT_VEHICLE', 'Import kendaraan: 5680-19 - Daihatsu Xenia', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/139.0.0.0 Safari/537.36', NULL, '2025-09-12 05:23:43'),
(461, 1, 'IMPORT_VEHICLE', 'Import kendaraan: 5913-19 - Mitsubishi Kuda', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/139.0.0.0 Safari/537.36', NULL, '2025-09-12 05:23:43'),
(462, 1, 'IMPORT_VEHICLE', 'Import kendaraan: 5914-19 - Mitsubishi Kuda', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/139.0.0.0 Safari/537.36', NULL, '2025-09-12 05:23:43'),
(463, 1, 'IMPORT_VEHICLE', 'Import kendaraan: 3554-19 - Suzuki Baleno', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/139.0.0.0 Safari/537.36', NULL, '2025-09-12 05:23:43'),
(464, 1, 'IMPORT_VEHICLE', 'Import kendaraan: 7375-19 - Hino FB 13D', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/139.0.0.0 Safari/537.36', NULL, '2025-09-12 05:23:43'),
(465, 1, 'IMPORT_VEHICLE', 'Import kendaraan: 5683-19 - Mitsubishi Xpander', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/139.0.0.0 Safari/537.36', NULL, '2025-09-12 05:23:43'),
(466, 1, 'IMPORT_VEHICLE', 'Import kendaraan: 5684-19 - Mitsubishi Xpander', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/139.0.0.0 Safari/537.36', NULL, '2025-09-12 05:23:43'),
(467, 1, 'IMPORT_VEHICLE', 'Import kendaraan: 9311-19 - Mitsubishi Triton', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/139.0.0.0 Safari/537.36', NULL, '2025-09-12 05:23:43'),
(468, 1, 'IMPORT_VEHICLE', 'Import kendaraan: 3521-19 - Toyota Vios', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/139.0.0.0 Safari/537.36', NULL, '2025-09-12 05:23:43'),
(469, 1, 'IMPORT_VEHICLE', 'Import kendaraan: 3523-19 - Toyota Vios', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/139.0.0.0 Safari/537.36', NULL, '2025-09-12 05:23:43'),
(470, 1, 'IMPORT_VEHICLE', 'Import kendaraan: 3522-19 - Toyota Vios', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/139.0.0.0 Safari/537.36', NULL, '2025-09-12 05:23:43'),
(471, 1, 'IMPORT_VEHICLE', 'Import kendaraan: 3525-19 - Toyota Altis', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/139.0.0.0 Safari/537.36', NULL, '2025-09-12 05:23:43'),
(472, 1, 'IMPORT_VEHICLE', 'Import kendaraan: 3527-19 - Toyota Vios', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/139.0.0.0 Safari/537.36', NULL, '2025-09-12 05:23:43'),
(473, 1, 'UPDATE_VEHICLE', 'Mengupdate kendaraan: 7375-19 - Hino FB 13D', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/139.0.0.0 Safari/537.36', NULL, '2025-09-12 05:24:58'),
(474, 1, 'LOGOUT', 'User keluar dari sistem', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/139.0.0.0 Safari/537.36', NULL, '2025-09-15 01:46:22'),
(475, 1, 'LOGIN', 'User berhasil login ke sistem', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/139.0.0.0 Safari/537.36', NULL, '2025-09-15 01:46:28'),
(476, 1, 'COMPLETE_MAINTENANCE', 'Menyelesaikan jadwal perawatan Ban untuk kendaraan 8545-08', NULL, NULL, NULL, '2025-09-15 01:47:27'),
(477, 1, 'UPDATE_VEHICLE_STATUS_BY_MAINTENANCE', 'Set kendaraan ID 17 menjadi Operasional/Tersedia setelah perawatan selesai', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/139.0.0.0 Safari/537.36', NULL, '2025-09-15 01:47:27'),
(478, 1, 'COMPLETE_MAINTENANCE', 'Menandai jadwal perawatan selesai: Ban untuk kendaraan 8545-08', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/139.0.0.0 Safari/537.36', NULL, '2025-09-15 01:47:27'),
(479, 1, 'IMPORT_USER', 'Import user: user3', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/139.0.0.0 Safari/537.36', NULL, '2025-09-15 02:15:25'),
(480, 1, 'IMPORT_USER', 'Import user: user4', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/139.0.0.0 Safari/537.36', NULL, '2025-09-15 02:15:25'),
(481, 1, 'IMPORT_USER', 'Import user: user5', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/139.0.0.0 Safari/537.36', NULL, '2025-09-15 02:15:25'),
(482, 1, 'IMPORT_USER', 'Import user: user6', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/139.0.0.0 Safari/537.36', NULL, '2025-09-15 02:15:25'),
(483, 1, 'IMPORT_USER', 'Import user: user7', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/139.0.0.0 Safari/537.36', NULL, '2025-09-15 02:15:25'),
(484, 1, 'IMPORT_USER', 'Import user: user8', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/139.0.0.0 Safari/537.36', NULL, '2025-09-15 02:15:26'),
(485, 1, 'IMPORT_USER', 'Import user: user9', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/139.0.0.0 Safari/537.36', NULL, '2025-09-15 02:15:26'),
(486, 1, 'IMPORT_USER', 'Import user: user10', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/139.0.0.0 Safari/537.36', NULL, '2025-09-15 02:15:26'),
(487, 1, 'IMPORT_USER', 'Import user: user11', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/139.0.0.0 Safari/537.36', NULL, '2025-09-15 02:15:26'),
(488, 1, 'IMPORT_USER', 'Import user: user12', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/139.0.0.0 Safari/537.36', NULL, '2025-09-15 02:15:26'),
(489, 1, 'IMPORT_USER', 'Import user: user13', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/139.0.0.0 Safari/537.36', NULL, '2025-09-15 02:15:26'),
(490, 1, 'IMPORT_USER', 'Import user: user14', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/139.0.0.0 Safari/537.36', NULL, '2025-09-15 02:15:26'),
(491, 1, 'IMPORT_USER', 'Import user: user15', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/139.0.0.0 Safari/537.36', NULL, '2025-09-15 02:15:26'),
(492, 1, 'IMPORT_USER', 'Import user: user16', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/139.0.0.0 Safari/537.36', NULL, '2025-09-15 02:15:26'),
(493, 1, 'IMPORT_USER', 'Import user: user17', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/139.0.0.0 Safari/537.36', NULL, '2025-09-15 02:15:26'),
(494, 1, 'IMPORT_USER', 'Import user: user18', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/139.0.0.0 Safari/537.36', NULL, '2025-09-15 02:15:26'),
(495, 1, 'IMPORT_USER', 'Import user: user19', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/139.0.0.0 Safari/537.36', NULL, '2025-09-15 02:15:27'),
(496, 1, 'IMPORT_USER', 'Import user: user21', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/139.0.0.0 Safari/537.36', NULL, '2025-09-15 02:15:27'),
(497, 1, 'IMPORT_USER', 'Import user: user22', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/139.0.0.0 Safari/537.36', NULL, '2025-09-15 02:15:27'),
(498, 1, 'IMPORT_USER', 'Import user: user23', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/139.0.0.0 Safari/537.36', NULL, '2025-09-15 02:15:27'),
(499, 1, 'IMPORT_USER', 'Import user: user24', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/139.0.0.0 Safari/537.36', NULL, '2025-09-15 02:15:27'),
(500, 1, 'UPDATE_VEHICLE', 'Mengupdate kendaraan: 7375-19 - Hino FB 13D', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/139.0.0.0 Safari/537.36', NULL, '2025-09-15 02:17:06'),
(501, 1, 'UPDATE_VEHICLE', 'Mengupdate kendaraan: 3527-19 - Toyota Vios', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/139.0.0.0 Safari/537.36', NULL, '2025-09-15 02:20:06'),
(502, 1, 'CREATE_USER', 'Admin membuat user: ginting', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/139.0.0.0 Safari/537.36', NULL, '2025-09-15 02:21:50'),
(503, 1, 'UPDATE_VEHICLE', 'Mengupdate kendaraan: 2265-19 - Honda', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/139.0.0.0 Safari/537.36', NULL, '2025-09-15 02:29:08'),
(504, 1, 'EDIT_USER', 'Admin mengubah user id: 1', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/139.0.0.0 Safari/537.36', NULL, '2025-09-15 02:35:59'),
(505, 1, 'EDIT_USER', 'Admin mengubah user id: 1', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/139.0.0.0 Safari/537.36', NULL, '2025-09-15 02:36:11'),
(506, 1, 'EDIT_USER', 'Admin mengubah user id: 1', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/139.0.0.0 Safari/537.36', NULL, '2025-09-15 02:38:00'),
(507, 1, 'EDIT_USER', 'Admin mengubah user id: 8', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/139.0.0.0 Safari/537.36', NULL, '2025-09-15 02:39:34'),
(508, 1, 'EDIT_USER', 'Admin mengubah user id: 8', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/139.0.0.0 Safari/537.36', NULL, '2025-09-15 02:39:45'),
(509, 1, 'UPDATE_VEHICLE', 'Mengupdate kendaraan: 2265-19 - Honda', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/139.0.0.0 Safari/537.36', NULL, '2025-09-15 02:42:09'),
(510, 1, 'CREATE_LAPORAN_PERJALANAN', 'Tambah laporan perjalanan tanggal 2025-09-15 untuk kendaraan ID 95 (pengguna ID 40)', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/139.0.0.0 Safari/537.36', NULL, '2025-09-15 02:43:14'),
(511, 1, 'CREATE_LAPORAN_PERJALANAN', 'Tambah laporan untuk kendaraan  (pengguna ID 34)', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/139.0.0.0 Safari/537.36', NULL, '2025-09-15 02:50:34'),
(512, 1, 'CREATE_LAPORAN_PERJALANAN', 'Tambah laporan untuk kendaraan  (pengguna ID 35)', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/139.0.0.0 Safari/537.36', NULL, '2025-09-15 02:58:05'),
(513, 1, 'CREATE_LAPORAN_PERJALANAN', 'Tambah laporan untuk kendaraan  (pengguna ID 24)', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/139.0.0.0 Safari/537.36', NULL, '2025-09-15 03:03:44'),
(514, 1, 'CREATE_LAPORAN_PERJALANAN', 'Tambah laporan untuk kendaraan  (pengguna ID 27)', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/139.0.0.0 Safari/537.36', NULL, '2025-09-15 03:05:04'),
(515, 1, 'UPDATE_VEHICLE', 'Mengupdate kendaraan: 3525-19 - Toyota Altis', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/139.0.0.0 Safari/537.36', NULL, '2025-09-15 23:34:20'),
(516, 1, 'UPDATE_VEHICLE', 'Mengupdate kendaraan: 3522-19 - Toyota Vios', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/139.0.0.0 Safari/537.36', NULL, '2025-09-15 23:34:29'),
(517, 1, 'UPDATE_VEHICLE', 'Mengupdate kendaraan: 3523-19 - Toyota Vios', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/139.0.0.0 Safari/537.36', NULL, '2025-09-15 23:34:37'),
(518, 1, 'UPDATE_VEHICLE', 'Mengupdate kendaraan: 3521-19 - Toyota Vios', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/139.0.0.0 Safari/537.36', NULL, '2025-09-15 23:34:45'),
(519, 1, 'UPDATE_VEHICLE', 'Mengupdate kendaraan: 3521-19 - Toyota Vios', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/139.0.0.0 Safari/537.36', NULL, '2025-09-15 23:34:57'),
(520, 1, 'UPDATE_VEHICLE', 'Mengupdate kendaraan: 3554-19 - Suzuki Baleno', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/139.0.0.0 Safari/537.36', NULL, '2025-09-15 23:35:10'),
(521, 1, 'UPDATE_VEHICLE', 'Mengupdate kendaraan: 5914-19 - Mitsubishi Kuda', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/139.0.0.0 Safari/537.36', NULL, '2025-09-15 23:35:22'),
(522, 1, 'UPDATE_VEHICLE', 'Mengupdate kendaraan: 5679-19 - Toyota Avanza', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/139.0.0.0 Safari/537.36', NULL, '2025-09-15 23:35:32'),
(523, 1, 'UPDATE_VEHICLE', 'Mengupdate kendaraan: 5682-19 - Toyota Avanza', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/139.0.0.0 Safari/537.36', NULL, '2025-09-15 23:35:43'),
(524, 1, 'UPDATE_VEHICLE', 'Mengupdate kendaraan: 5913-19 - Mitsubishi Kuda', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/139.0.0.0 Safari/537.36', NULL, '2025-09-15 23:35:58'),
(525, 1, 'UPDATE_VEHICLE', 'Mengupdate kendaraan: 5680-19 - Daihatsu Xenia', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/139.0.0.0 Safari/537.36', NULL, '2025-09-15 23:36:11'),
(526, 1, 'UPDATE_VEHICLE', 'Mengupdate kendaraan: 2268-19 - Honda', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/139.0.0.0 Safari/537.36', NULL, '2025-09-15 23:36:22'),
(527, 1, 'UPDATE_VEHICLE', 'Mengupdate kendaraan: 5678-19 - Toyota Avanza', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/139.0.0.0 Safari/537.36', NULL, '2025-09-15 23:36:38'),
(528, 1, 'UPDATE_VEHICLE', 'Mengupdate kendaraan: 5676-19 - Toyota Avanza', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/139.0.0.0 Safari/537.36', NULL, '2025-09-15 23:37:17'),
(529, 1, 'UPDATE_VEHICLE', 'Mengupdate kendaraan: 5677-19 - Toyota Avanza', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/139.0.0.0 Safari/537.36', NULL, '2025-09-15 23:37:34'),
(530, 1, 'UPDATE_VEHICLE', 'Mengupdate kendaraan: 2272-19 - Honda', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/139.0.0.0 Safari/537.36', NULL, '2025-09-15 23:37:46'),
(531, 1, 'UPDATE_VEHICLE', 'Mengupdate kendaraan: 5675-19 - Toyota Avanza', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/139.0.0.0 Safari/537.36', NULL, '2025-09-15 23:37:59'),
(532, 1, 'UPDATE_VEHICLE', 'Mengupdate kendaraan: 2271-19 - Suzuki', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/139.0.0.0 Safari/537.36', NULL, '2025-09-15 23:38:09'),
(533, 1, 'UPDATE_VEHICLE', 'Mengupdate kendaraan: 2270-19 - Hyosung', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/139.0.0.0 Safari/537.36', NULL, '2025-09-15 23:38:21'),
(534, 1, 'UPDATE_VEHICLE', 'Mengupdate kendaraan: 8545-08 - Inova', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/139.0.0.0 Safari/537.36', NULL, '2025-09-15 23:38:36'),
(535, 1, 'UPDATE_VEHICLE', 'Mengupdate kendaraan: 2269-19 - Suzuki', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/139.0.0.0 Safari/537.36', NULL, '2025-09-15 23:38:48'),
(536, 1, 'UPDATE_VEHICLE', 'Mengupdate kendaraan: 2266-19 - Honda', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/139.0.0.0 Safari/537.36', NULL, '2025-09-15 23:39:09'),
(537, 1, 'UPDATE_VEHICLE', 'Mengupdate kendaraan: 8667-08 - Test Brand', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/139.0.0.0 Safari/537.36', NULL, '2025-09-15 23:39:27'),
(538, 1, 'UPDATE_VEHICLE', 'Mengupdate kendaraan: 8667-08 - Test Brand', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/139.0.0.0 Safari/537.36', NULL, '2025-09-16 03:23:32'),
(539, 1, 'UPDATE_VEHICLE', 'Mengupdate kendaraan: 8811-01 - Mitsubishi', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/139.0.0.0 Safari/537.36', NULL, '2025-09-16 03:24:02'),
(540, 1, 'UPDATE_VEHICLE', 'Mengupdate kendaraan: 3521-19 - Toyota Vios', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/139.0.0.0 Safari/537.36', NULL, '2025-09-16 03:24:40'),
(541, 1, 'UPDATE_VEHICLE', 'Mengupdate kendaraan: 8545-08 - Inova', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/139.0.0.0 Safari/537.36', NULL, '2025-09-16 03:24:57'),
(542, 1, 'EDIT_USER', 'Admin mengubah user id: 8', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/139.0.0.0 Safari/537.36', NULL, '2025-09-16 07:43:42'),
(543, 1, 'EDIT_USER', 'Admin mengubah user id: 8', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/139.0.0.0 Safari/537.36', NULL, '2025-09-16 07:43:55'),
(544, 1, 'LOGOUT', 'User keluar dari sistem', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/139.0.0.0 Safari/537.36', NULL, '2025-09-16 16:06:33'),
(547, 1, 'LOGIN', 'User berhasil login ke sistem', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/139.0.0.0 Safari/537.36', NULL, '2025-09-16 16:08:17'),
(548, 1, 'LOGOUT', 'User keluar dari sistem', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/139.0.0.0 Safari/537.36', NULL, '2025-09-16 16:10:04'),
(551, 1, 'LOGIN', 'User berhasil login ke sistem', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/139.0.0.0 Safari/537.36', NULL, '2025-09-16 16:12:39'),
(552, 1, 'CHANGE_USER_STATUS', 'Admin mengubah status user menjadi: Aktif', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/140.0.0.0 Safari/537.36', NULL, '2025-09-17 02:04:43'),
(553, 1, 'LOGIN', 'User berhasil login ke sistem', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/140.0.0.0 Safari/537.36', NULL, '2025-09-17 03:00:47'),
(554, 1, 'EDIT_USER', 'Admin mengubah user id: 36', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/140.0.0.0 Safari/537.36', NULL, '2025-09-17 07:00:29'),
(557, 1, 'COMPLETE_MAINTENANCE', 'Menyelesaikan jadwal perawatan Ganti oli untuk kendaraan 3521-19', NULL, NULL, NULL, '2025-09-22 00:50:04'),
(558, 1, 'UPDATE_VEHICLE_STATUS_BY_MAINTENANCE', 'Set kendaraan ID 100 menjadi Operasional/Tersedia setelah perawatan selesai', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/140.0.0.0 Safari/537.36', NULL, '2025-09-22 00:50:04'),
(559, 1, 'COMPLETE_MAINTENANCE', 'Menandai jadwal perawatan selesai: Ganti oli untuk kendaraan 3521-19', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/140.0.0.0 Safari/537.36', NULL, '2025-09-22 00:50:04'),
(560, 1, 'LOGOUT', 'User keluar dari sistem', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/140.0.0.0 Safari/537.36', NULL, '2025-09-22 01:55:53'),
(561, 1, 'LOGIN', 'User berhasil login ke sistem', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/140.0.0.0 Safari/537.36', NULL, '2025-09-22 01:56:11'),
(562, 1, 'CHANGE_USER_STATUS', 'Admin mengubah status user menjadi: Nonaktif', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/140.0.0.0 Safari/537.36', NULL, '2025-09-22 02:09:17'),
(563, 1, 'CHANGE_USER_STATUS', 'Admin mengubah status user menjadi: Nonaktif', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/140.0.0.0 Safari/537.36', NULL, '2025-09-23 06:33:10'),
(564, 1, 'CHANGE_USER_STATUS', 'Admin mengubah status user menjadi: Aktif', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/140.0.0.0 Safari/537.36', NULL, '2025-09-23 06:34:10'),
(565, 1, 'CHANGE_USER_STATUS', 'Admin mengubah status user menjadi: Aktif', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/140.0.0.0 Safari/537.36', NULL, '2025-09-23 06:34:15'),
(566, 1, 'CHANGE_USER_STATUS', 'Admin mengubah status user menjadi: Tidak Aktif', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/140.0.0.0 Safari/537.36', NULL, '2025-09-23 07:09:12'),
(568, 1, 'LOGIN', 'User berhasil login ke sistem', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/140.0.0.0 Safari/537.36', NULL, '2025-09-23 07:11:25'),
(570, 1, 'COMPLETE_MAINTENANCE', 'Menyelesaikan jadwal perawatan mkk untuk kendaraan 2270-19', NULL, NULL, NULL, '2025-09-24 05:10:48'),
(571, 1, 'UPDATE_VEHICLE_STATUS_BY_MAINTENANCE', 'Set kendaraan ID 78 menjadi Operasional/Tersedia setelah perawatan selesai', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/140.0.0.0 Safari/537.36', NULL, '2025-09-24 05:10:48'),
(572, 1, 'COMPLETE_MAINTENANCE', 'Menandai jadwal perawatan selesai: mkk untuk kendaraan 2270-19', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/140.0.0.0 Safari/537.36', NULL, '2025-09-24 05:10:48'),
(573, 1, 'LOGOUT', 'User keluar dari sistem', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/140.0.0.0 Safari/537.36', NULL, '2025-09-24 12:17:06'),
(574, 1, 'LOGIN', 'User berhasil login ke sistem', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/140.0.0.0 Safari/537.36', NULL, '2025-09-24 12:17:23'),
(575, 1, 'LOGOUT', 'User keluar dari sistem', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/140.0.0.0 Safari/537.36', NULL, '2025-09-24 12:32:58'),
(577, 1, 'LOGIN', 'User berhasil login ke sistem', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/140.0.0.0 Safari/537.36', NULL, '2025-09-24 23:47:19'),
(584, 1, 'RESET_PASSWORD', 'Admin mereset password user: ginting', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/140.0.0.0 Safari/537.36', NULL, '2025-09-25 03:01:00'),
(588, 1, 'LOGIN', 'User berhasil login ke sistem', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/140.0.0.0 Safari/537.36', NULL, '2025-09-30 13:13:33'),
(589, 1, 'CHANGE_USER_STATUS', 'Admin mengubah status user menjadi: Aktif', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/140.0.0.0 Safari/537.36', NULL, '2025-09-30 13:15:33'),
(590, 1, 'EDIT_USER', 'Admin mengubah user id: 2', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/140.0.0.0 Safari/537.36', NULL, '2025-09-30 13:15:41'),
(591, 1, 'EDIT_USER', 'Admin mengubah user id: 4', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/140.0.0.0 Safari/537.36', NULL, '2025-09-30 13:15:49'),
(592, 1, 'LOGOUT', 'User keluar dari sistem', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/140.0.0.0 Safari/537.36', NULL, '2025-09-30 13:36:42'),
(595, 1, 'LOGIN', 'User berhasil login ke sistem', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/140.0.0.0 Safari/537.36', NULL, '2025-09-30 13:55:33'),
(596, 1, 'LOGOUT', 'User keluar dari sistem', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/140.0.0.0 Safari/537.36', NULL, '2025-09-30 14:27:43'),
(597, 1, 'LOGIN', 'User berhasil login ke sistem', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/140.0.0.0 Safari/537.36', NULL, '2025-09-30 15:28:48'),
(598, 1, 'COMPLETE_MAINTENANCE', 'Menyelesaikan jadwal perawatan Ss untuk kendaraan 0200-08', NULL, NULL, NULL, '2025-10-07 07:33:35'),
(599, 1, 'EDIT_USER', 'Admin mengubah user id: 14', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/141.0.0.0 Safari/537.36', NULL, '2025-10-07 07:38:14'),
(600, 1, 'EDIT_USER', 'Admin mengubah user id: 14', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/141.0.0.0 Safari/537.36', NULL, '2025-10-07 07:38:52'),
(601, 1, 'EDIT_USER', 'Admin mengubah user id: 8', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/141.0.0.0 Safari/537.36', NULL, '2025-10-07 07:39:31'),
(602, 1, 'LOGOUT', 'User keluar dari sistem', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/141.0.0.0 Safari/537.36', NULL, '2025-10-07 13:26:18'),
(605, 1, 'LOGIN', 'User berhasil login ke sistem', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/141.0.0.0 Safari/537.36', NULL, '2025-10-08 01:17:20'),
(606, 1, 'LOGOUT', 'User keluar dari sistem', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/141.0.0.0 Safari/537.36', NULL, '2025-10-08 04:11:15'),
(609, 1, 'LOGIN', 'User berhasil login ke sistem', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/141.0.0.0 Safari/537.36', NULL, '2025-10-08 05:11:39'),
(610, 1, 'LOGOUT', 'User keluar dari sistem', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/141.0.0.0 Safari/537.36', NULL, '2025-10-08 13:48:24'),
(613, 1, 'LOGIN', 'User berhasil login ke sistem', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/141.0.0.0 Safari/537.36', NULL, '2025-10-08 13:51:07'),
(614, 1, 'CREATE_LAPORAN_PERJALANAN', 'Tambah laporan untuk kendaraan ID 102 (pengguna ID 41)', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/141.0.0.0 Safari/537.36', NULL, '2025-10-13 00:42:46'),
(615, 1, 'EDIT_USER', 'Admin mengubah user id: 1', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/141.0.0.0 Safari/537.36', NULL, '2025-10-13 00:43:38'),
(616, 1, 'EDIT_USER', 'Admin mengubah user id: 1', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/141.0.0.0 Safari/537.36', NULL, '2025-10-13 00:43:56'),
(617, 1, 'EXPORT_KENDARAAN_CSV', 'Export data kendaraan CSV total: 41', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/141.0.0.0 Safari/537.36', NULL, '2025-10-13 01:02:08'),
(618, 1, 'UPDATE_VEHICLE', 'Mengupdate kendaraan: 3523-19 - Toyota Vios', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/141.0.0.0 Safari/537.36', NULL, '2025-10-13 01:06:03'),
(619, 1, 'UPDATE_VEHICLE', 'Mengupdate kendaraan: 3521-19 - Toyota Vios', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/141.0.0.0 Safari/537.36', NULL, '2025-10-13 01:06:17'),
(620, 1, 'UPDATE_VEHICLE', 'Mengupdate kendaraan: 5681-19 - Toyota Avanza', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/141.0.0.0 Safari/537.36', NULL, '2025-10-13 01:06:55'),
(621, 1, 'EXPORT_KENDARAAN_CSV', 'Export data kendaraan CSV total: 41', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/141.0.0.0 Safari/537.36', NULL, '2025-10-13 01:12:41'),
(622, 1, 'UPDATE_VEHICLE', 'Mengupdate kendaraan: 5681-19 - Toyota Avanza', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/141.0.0.0 Safari/537.36', NULL, '2025-10-13 01:30:28'),
(623, 1, 'UPDATE_VEHICLE', 'Mengupdate kendaraan: 5681-19 - Toyota Avanza', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/141.0.0.0 Safari/537.36', NULL, '2025-10-13 01:30:34'),
(624, 1, 'EXPORT_KENDARAAN_XLSX', 'Export data kendaraan XLSX total: 41', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/141.0.0.0 Safari/537.36', NULL, '2025-10-13 01:33:11'),
(625, 1, 'UPDATE_VEHICLE', 'Mengupdate kendaraan: 5681-19 - Toyota Avanza', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/141.0.0.0 Safari/537.36', NULL, '2025-10-13 01:33:19'),
(627, 1, 'LOGIN', 'User berhasil login ke sistem', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/141.0.0.0 Safari/537.36', NULL, '2025-10-14 02:24:48'),
(628, 1, 'COMPLETE_MAINTENANCE', 'Menyelesaikan jadwal perawatan aw untuk kendaraan 3523-19', NULL, NULL, NULL, '2025-10-14 02:25:08'),
(629, 1, 'UPDATE_VEHICLE_STATUS_BY_MAINTENANCE', 'Set kendaraan ID 101 menjadi Operasional/Tersedia setelah perawatan selesai', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/141.0.0.0 Safari/537.36', NULL, '2025-10-14 02:25:08'),
(630, 1, 'COMPLETE_MAINTENANCE', 'Menandai jadwal perawatan selesai: aw untuk kendaraan 3523-19', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/141.0.0.0 Safari/537.36', NULL, '2025-10-14 02:25:08'),
(631, 1, 'LOGOUT', 'User keluar dari sistem', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/141.0.0.0 Safari/537.36', NULL, '2025-10-14 05:16:54'),
(632, 1, 'LOGIN', 'User berhasil login ke sistem', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/141.0.0.0 Safari/537.36', NULL, '2025-10-14 06:12:58'),
(633, 1, 'LOGOUT', 'User keluar dari sistem', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/141.0.0.0 Safari/537.36', NULL, '2025-10-14 07:42:33'),
(636, 1, 'LOGIN', 'User berhasil login ke sistem', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/141.0.0.0 Safari/537.36', NULL, '2025-10-14 09:02:54'),
(637, 1, 'LOGOUT', 'User keluar dari sistem', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/141.0.0.0 Safari/537.36', NULL, '2025-10-14 09:03:07'),
(640, 1, 'LOGIN', 'User berhasil login ke sistem', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/141.0.0.0 Safari/537.36', NULL, '2025-10-14 09:04:26'),
(641, 1, 'LOGOUT', 'User keluar dari sistem', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/141.0.0.0 Safari/537.36', NULL, '2025-10-14 09:05:14'),
(642, 1, 'LOGIN', 'User berhasil login ke sistem', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/141.0.0.0 Safari/537.36', NULL, '2025-10-14 09:05:46'),
(643, 1, 'RESET_PASSWORD', 'Admin mereset password user: user22', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/141.0.0.0 Safari/537.36', NULL, '2025-10-14 09:06:02'),
(644, 1, 'LOGOUT', 'User keluar dari sistem', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/141.0.0.0 Safari/537.36', NULL, '2025-10-14 09:06:05'),
(649, 1, 'LOGIN', 'User berhasil login ke sistem', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/141.0.0.0 Safari/537.36', NULL, '2025-10-14 09:28:35'),
(650, 1, 'LOGOUT', 'User keluar dari sistem', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/141.0.0.0 Safari/537.36', NULL, '2025-10-14 09:31:44'),
(653, 1, 'LOGIN', 'User berhasil login ke sistem', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/141.0.0.0 Safari/537.36', NULL, '2025-10-14 23:53:34'),
(654, 1, 'CREATE_VEHICLE', 'Menambah kendaraan: 0412-03 - Toyota', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/141.0.0.0 Safari/537.36', NULL, '2025-10-15 00:16:52'),
(655, 1, 'UPDATE_VEHICLE', 'Mengupdate kendaraan: 0412-03 - Toyota', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/141.0.0.0 Safari/537.36', NULL, '2025-10-15 00:19:08'),
(656, 1, 'DELETE_VEHICLE', 'Menghapus kendaraan: 0412-03 - Toyota', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/141.0.0.0 Safari/537.36', NULL, '2025-10-15 00:19:15'),
(657, 1, 'EDIT_USER', 'Admin mengubah user id: 1', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/141.0.0.0 Safari/537.36', NULL, '2025-10-15 01:14:47'),
(658, 1, 'EDIT_USER', 'Admin mengubah user id: Admin', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/141.0.0.0 Safari/537.36', NULL, '2025-10-15 01:22:12'),
(659, 1, 'EDIT_JADWAL_PERAWATAN', 'Memperbarui jadwal perawatan ID: 58', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/141.0.0.0 Safari/537.36', NULL, '2025-10-15 01:33:08'),
(660, 1, 'EDIT_JADWAL_PERAWATAN', 'Memperbarui jadwal perawatan ID: ', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/141.0.0.0 Safari/537.36', NULL, '2025-10-15 01:36:10'),
(661, 1, 'EDIT_JADWAL_PERAWATAN', 'Memperbarui jadwal perawatan ID: ', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/141.0.0.0 Safari/537.36', NULL, '2025-10-15 01:40:01'),
(662, 1, 'EDIT_JADWAL_PERAWATAN', 'Memperbarui jadwal perawatan ID: 57', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/141.0.0.0 Safari/537.36', NULL, '2025-10-15 01:43:34'),
(663, 1, 'EDIT_JADWAL_PERAWATAN', 'Memperbarui jadwal perawatan ID: 5679-19', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/141.0.0.0 Safari/537.36', NULL, '2025-10-15 01:44:13'),
(664, 1, 'CREATE_LAPORAN_PERJALANAN', 'Tambah laporan untuk kendaraan ID 103 (pengguna ID 41)', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/141.0.0.0 Safari/537.36', NULL, '2025-10-15 01:45:35'),
(665, 1, 'CREATE_LAPORAN_PERJALANAN', 'Tambah laporan perjalanan untuk kendaraan No.Reg 3523-19 pada 2025-10-15 (pengguna ID 32)', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/141.0.0.0 Safari/537.36', NULL, '2025-10-15 01:56:17'),
(666, 1, 'CREATE_LAPORAN_PERJALANAN', 'Tambah laporan perjalanan untuk kendaraan No.Reg 5682-19 (pengguna Dwi Setyo Utomo)', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/141.0.0.0 Safari/537.36', NULL, '2025-10-15 02:03:55'),
(667, 1, 'EXPORT_LAPORAN_PERJALANAN', 'Export Laporan Perjalanan BULAN OKTOBER 2025 (4 baris)', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/141.0.0.0 Safari/537.36', NULL, '2025-10-15 02:04:27'),
(668, 1, 'EDIT_RIWAYAT_PERBAIKAN', 'Edit riwayat perbaikan ID 16 untuk kendaraan No.Reg 0822-02 pada 2025-10-15. Jenis: Perbaikan Ban. Status: Selesai. Total: Rp 540.000', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/141.0.0.0 Safari/537.36', NULL, '2025-10-15 02:16:28'),
(669, 1, 'LOGOUT', 'User keluar dari sistem', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/141.0.0.0 Safari/537.36', NULL, '2025-10-15 06:14:55'),
(670, 1, 'LOGIN', 'User berhasil login ke sistem', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/141.0.0.0 Safari/537.36', NULL, '2025-10-15 06:30:38'),
(671, 1, 'LOGOUT', 'User keluar dari sistem', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/141.0.0.0 Safari/537.36', NULL, '2025-10-15 06:37:25'),
(674, 1, 'LOGIN', 'User berhasil login ke sistem', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/141.0.0.0 Safari/537.36', NULL, '2025-10-15 06:53:53'),
(675, 1, 'LOGIN', 'User berhasil login ke sistem', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/141.0.0.0 Safari/537.36', NULL, '2025-10-15 06:55:15'),
(676, 1, 'ADD_JADWAL_PERAWATAN', 'Menambah jadwal perawatan: Ganti Ban untuk kendaraan ID 100', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/141.0.0.0 Safari/537.36', NULL, '2025-10-15 06:59:18'),
(677, 1, 'EXPORT_LAPORAN_PERJALANAN', 'Export Laporan Perjalanan BULAN OKTOBER 2025 (4 baris)', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/141.0.0.0 Safari/537.36', NULL, '2025-10-15 08:56:24'),
(678, 1, 'EXPORT_LAPORAN_PERJALANAN', 'Export Laporan Perjalanan BULAN OKTOBER 2025 (4 baris)', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/141.0.0.0 Safari/537.36', NULL, '2025-10-15 08:56:27'),
(679, 1, 'LOGIN', 'User berhasil login ke sistem', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/141.0.0.0 Safari/537.36', NULL, '2025-10-15 09:00:03'),
(680, 1, 'EXPORT_RIWAYAT_PERBAIKAN', 'Export Riwayat Perbaikan 18 baris [Tahun 2025; Status Selesai]', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/141.0.0.0 Safari/537.36', NULL, '2025-10-15 09:00:41'),
(681, 1, 'LOGOUT', 'User keluar dari sistem', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/141.0.0.0 Safari/537.36', NULL, '2025-10-16 13:46:05'),
(684, 1, 'LOGIN', 'User berhasil login ke sistem', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/141.0.0.0 Safari/537.36 Edg/141.0.0.0', NULL, '2025-10-25 09:48:48'),
(685, 1, 'ADD_RIWAYAT_PERBAIKAN', 'Tambah riwayat perbaikan Perbaikan Ban untuk kendaraan No.Reg 3522-19', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/141.0.0.0 Safari/537.36 Edg/141.0.0.0', NULL, '2025-10-25 10:17:05'),
(686, 1, 'EXPORT_RIWAYAT_PERAWATAN', 'Export Riwayat Perawatan 23 baris [Tahun 2025]', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/141.0.0.0 Safari/537.36 Edg/141.0.0.0', NULL, '2025-10-25 10:41:42'),
(687, 1, 'EXPORT_RIWAYAT_PERBAIKAN', 'Export Riwayat Perbaikan 19 baris [Tahun 2025]', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/141.0.0.0 Safari/537.36 Edg/141.0.0.0', NULL, '2025-10-25 10:45:11'),
(688, 1, 'ADD_JADWAL_PERAWATAN', 'Menambah jadwal perawatan: Ganti oli untuk kendaraan ID 97', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/141.0.0.0 Safari/537.36 Edg/141.0.0.0', NULL, '2025-10-25 11:44:06'),
(689, 1, 'COMPLETE_MAINTENANCE', 'Menyelesaikan jadwal perawatan Oli untuk kendaraan 3521-19', NULL, NULL, NULL, '2025-10-25 11:44:18'),
(690, 1, 'UPDATE_VEHICLE_STATUS_BY_MAINTENANCE', 'Set kendaraan ID 100 menjadi Operasional/Tersedia setelah perawatan selesai', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/141.0.0.0 Safari/537.36 Edg/141.0.0.0', NULL, '2025-10-25 11:44:18'),
(691, 1, 'COMPLETE_MAINTENANCE', 'Menandai jadwal perawatan selesai: Oli untuk kendaraan 3521-19', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/141.0.0.0 Safari/537.36 Edg/141.0.0.0', NULL, '2025-10-25 11:44:18'),
(692, 1, 'COMPLETE_MAINTENANCE', 'Menyelesaikan jadwal perawatan asdasss untuk kendaraan 5679-19', NULL, NULL, NULL, '2025-10-25 11:44:22'),
(693, 1, 'UPDATE_VEHICLE_STATUS_BY_MAINTENANCE', 'Set kendaraan ID 85 menjadi Operasional/Tersedia setelah perawatan selesai', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/141.0.0.0 Safari/537.36 Edg/141.0.0.0', NULL, '2025-10-25 11:44:22'),
(694, 1, 'COMPLETE_MAINTENANCE', 'Menandai jadwal perawatan selesai: asdasss untuk kendaraan 5679-19', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/141.0.0.0 Safari/537.36 Edg/141.0.0.0', NULL, '2025-10-25 11:44:22'),
(695, 1, 'COMPLETE_MAINTENANCE', 'Menyelesaikan jadwal perawatan Ganti Ban untuk kendaraan 3521-19', NULL, NULL, NULL, '2025-10-25 11:44:25'),
(696, 1, 'UPDATE_VEHICLE_STATUS_BY_MAINTENANCE', 'Set kendaraan ID 100 menjadi Operasional/Tersedia setelah perawatan selesai', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/141.0.0.0 Safari/537.36 Edg/141.0.0.0', NULL, '2025-10-25 11:44:25'),
(697, 1, 'COMPLETE_MAINTENANCE', 'Menandai jadwal perawatan selesai: Ganti Ban untuk kendaraan 3521-19', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/141.0.0.0 Safari/537.36 Edg/141.0.0.0', NULL, '2025-10-25 11:44:25'),
(698, 1, 'EDIT_JADWAL_PERAWATAN', 'Memperbarui jadwal perawatan Kendaraan no_reg: 5683-19', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/141.0.0.0 Safari/537.36 Edg/141.0.0.0', NULL, '2025-10-25 12:20:03'),
(699, 1, 'COMPLETE_MAINTENANCE', 'Menyelesaikan jadwal perawatan Ganti oli untuk kendaraan 5683-19', NULL, NULL, NULL, '2025-10-25 12:20:14'),
(700, 1, 'EDIT_JADWAL_PERAWATAN', 'Memperbarui jadwal perawatan Kendaraan no_reg: 5683-19', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/141.0.0.0 Safari/537.36 Edg/141.0.0.0', NULL, '2025-10-25 12:20:14'),
(701, 1, 'EXPORT_RIWAYAT_PERAWATAN', 'Export Riwayat Perawatan 24 baris [Tahun 2025]', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/141.0.0.0 Safari/537.36 Edg/141.0.0.0', NULL, '2025-10-25 12:20:30'),
(702, 1, 'EXPORT_RIWAYAT_PERAWATAN', 'Export Riwayat Perawatan 24 baris [Tahun 2025]', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/141.0.0.0 Safari/537.36 Edg/141.0.0.0', NULL, '2025-10-25 12:22:49'),
(703, 1, 'ADD_JADWAL_PERAWATAN', 'Menambah jadwal perawatan: wq untuk kendaraan ID 87', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/141.0.0.0 Safari/537.36 Edg/141.0.0.0', NULL, '2025-10-25 12:25:20'),
(704, 1, 'EDIT_JADWAL_PERAWATAN', 'Memperbarui jadwal perawatan Kendaraan no_reg: 2265-19', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/141.0.0.0 Safari/537.36 Edg/141.0.0.0', NULL, '2025-10-25 12:25:42'),
(705, 1, 'COMPLETE_MAINTENANCE', 'Menyelesaikan jadwal perawatan wq untuk kendaraan 2265-19', NULL, NULL, NULL, '2025-10-25 12:26:04'),
(706, 1, 'UPDATE_VEHICLE_STATUS_BY_MAINTENANCE', 'Set kendaraan ID 87 menjadi Operasional/Tersedia setelah perawatan selesai', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/141.0.0.0 Safari/537.36 Edg/141.0.0.0', NULL, '2025-10-25 12:26:04'),
(707, 1, 'COMPLETE_MAINTENANCE', 'Menandai jadwal perawatan selesai: wq untuk kendaraan 2265-19', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/141.0.0.0 Safari/537.36 Edg/141.0.0.0', NULL, '2025-10-25 12:26:04'),
(708, 1, 'EXPORT_RIWAYAT_PERAWATAN', 'Export Riwayat Perawatan 25 baris [Tahun 2025]', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/141.0.0.0 Safari/537.36 Edg/141.0.0.0', NULL, '2025-10-25 13:37:10'),
(709, 1, 'EXPORT_RIWAYAT_PERAWATAN', 'Export Riwayat Perawatan 25 baris [Tahun 2025]', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/141.0.0.0 Safari/537.36 Edg/141.0.0.0', NULL, '2025-10-25 13:40:20'),
(710, 1, 'ADD_JADWAL_PERAWATAN', 'Menambah jadwal perawatan: sa untuk kendaraan ID 25', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/141.0.0.0 Safari/537.36 Edg/141.0.0.0', NULL, '2025-10-25 13:41:12'),
(711, 1, 'COMPLETE_MAINTENANCE', 'Menyelesaikan jadwal perawatan sa untuk kendaraan 0822-02', NULL, NULL, NULL, '2025-10-25 13:41:45'),
(712, 1, 'EDIT_JADWAL_PERAWATAN', 'Memperbarui jadwal perawatan Kendaraan no_reg: 0822-02', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/141.0.0.0 Safari/537.36 Edg/141.0.0.0', NULL, '2025-10-25 13:41:45'),
(713, 1, 'EXPORT_RIWAYAT_PERAWATAN', 'Export Riwayat Perawatan 26 baris [Tahun 2025]', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/141.0.0.0 Safari/537.36 Edg/141.0.0.0', NULL, '2025-10-25 13:43:26'),
(714, 1, 'EXPORT_RIWAYAT_PERAWATAN', 'Export Riwayat Perawatan 26 baris [Tahun 2025]', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/141.0.0.0 Safari/537.36 Edg/141.0.0.0', NULL, '2025-10-25 13:45:51'),
(715, 1, 'EXPORT_RIWAYAT_PERBAIKAN', 'Export Riwayat Perbaikan 19 baris [Tahun 2025]', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/141.0.0.0 Safari/537.36 Edg/141.0.0.0', NULL, '2025-10-25 13:48:15'),
(716, 1, 'EXPORT_RIWAYAT_PERAWATAN', 'Export Riwayat Perawatan 26 baris [Tahun 2025]', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/141.0.0.0 Safari/537.36 Edg/141.0.0.0', NULL, '2025-10-25 13:50:19'),
(717, 1, 'LOGIN', 'User berhasil login ke sistem', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/141.0.0.0 Safari/537.36 Edg/141.0.0.0', NULL, '2025-10-26 12:19:39'),
(718, 1, 'EDIT_RIWAYAT_PERBAIKAN', 'Edit riwayat perbaikan untuk kendaraan No.Reg 3522-19. Jenis: Perbaikan Ban.', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/141.0.0.0 Safari/537.36 Edg/141.0.0.0', NULL, '2025-10-26 12:20:34'),
(719, 1, 'EDIT_USER', 'Admin mengubah user: JOHN DOE', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/141.0.0.0 Safari/537.36 Edg/141.0.0.0', NULL, '2025-10-26 12:23:50'),
(720, 1, 'EXPORT_RIWAYAT_PERAWATAN', 'Export Riwayat Perawatan 26 baris', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/141.0.0.0 Safari/537.36 Edg/141.0.0.0', NULL, '2025-10-26 12:29:17'),
(721, 1, 'EXPORT_USERS', 'Export Manajemen User 36 baris', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/141.0.0.0 Safari/537.36 Edg/141.0.0.0', NULL, '2025-10-26 12:32:25');
INSERT INTO `log_aktivitas` (`id`, `user_id`, `activity_type`, `description`, `ip_address`, `user_agent`, `session_id`, `created_at`) VALUES
(722, 1, 'LOGIN', 'User berhasil login ke sistem', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/141.0.0.0 Safari/537.36', NULL, '2025-10-27 12:32:50'),
(723, 1, 'LOGOUT', 'User keluar dari sistem', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/141.0.0.0 Safari/537.36 Edg/141.0.0.0', NULL, '2025-10-27 12:49:26'),
(725, 1, 'LOGOUT', 'User keluar dari sistem', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/141.0.0.0 Safari/537.36', NULL, '2025-10-27 13:31:15'),
(730, 1, 'LOGIN', 'User berhasil login ke sistem', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/141.0.0.0 Safari/537.36', NULL, '2025-10-28 15:30:19'),
(731, 1, 'UPDATE_VEHICLE', 'Mengupdate kendaraan: 3521-19 - Toyota Vios', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/141.0.0.0 Safari/537.36', NULL, '2025-10-29 03:32:01'),
(732, 1, 'UPDATE_VEHICLE', 'Mengupdate kendaraan: 8545-08 - Inova', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/141.0.0.0 Safari/537.36', NULL, '2025-10-29 03:32:18'),
(734, 1, 'RESET_PASSWORD', 'Admin mereset password user: ginting', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/141.0.0.0 Safari/537.36', NULL, '2025-10-29 03:36:22'),
(736, 1, 'EDIT_USER', 'Admin mengubah user: S. Ginting, S.Kom., MMSI.,M.Tr.Hanla', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/141.0.0.0 Safari/537.36', NULL, '2025-10-29 03:36:50'),
(739, 1, 'EDIT_USER', 'Admin mengubah user: S. Ginting, S.Kom., MMSI.,M.Tr.Hanla', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/141.0.0.0 Safari/537.36', NULL, '2025-10-29 03:37:15'),
(740, 1, 'EDIT_USER', 'Admin mengubah user: S. Ginting, S.Kom., MMSI.,M.Tr.Hanla', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/141.0.0.0 Safari/537.36', NULL, '2025-10-29 03:37:23'),
(741, 1, 'EDIT_USER', 'Admin mengubah user: S. Ginting, S.Kom., MMSI.,M.Tr.Hanla', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/141.0.0.0 Safari/537.36', NULL, '2025-10-29 03:40:44'),
(744, 1, 'UPDATE_VEHICLE', 'Mengupdate kendaraan: 5681-19 - Toyota Avanza', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/141.0.0.0 Safari/537.36', NULL, '2025-10-29 03:41:51'),
(745, 1, 'LOGOUT', 'User keluar dari sistem', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/141.0.0.0 Safari/537.36', NULL, '2025-10-29 03:45:28'),
(748, 1, 'LOGIN', 'User berhasil login ke sistem', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/141.0.0.0 Safari/537.36', NULL, '2025-10-29 03:46:57'),
(749, 1, 'LOGOUT', 'User keluar dari sistem', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/141.0.0.0 Safari/537.36', NULL, '2025-10-29 03:47:13'),
(754, 1, 'LOGIN', 'User berhasil login ke sistem', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/141.0.0.0 Safari/537.36', NULL, '2025-10-29 04:20:33'),
(755, 1, 'APPROVE_MAINTENANCE_REQUEST', 'Admin menyetujui pengajuan perawatan ID 65', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/141.0.0.0 Safari/537.36', NULL, '2025-10-29 05:11:06'),
(756, 1, 'APPROVE_MAINTENANCE_REQUEST', 'Admin menyetujui pengajuan perawatan ID 66', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/141.0.0.0 Safari/537.36', NULL, '2025-10-29 05:14:53'),
(757, 1, 'REJECT_MAINTENANCE_REQUEST', 'Admin menolak pengajuan perawatan ID 66', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/141.0.0.0 Safari/537.36', NULL, '2025-10-29 05:15:04'),
(758, 1, 'DELETE_JADWAL_PERAWATAN', 'Menghapus jadwal perawatan ID: 66', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/141.0.0.0 Safari/537.36', NULL, '2025-10-29 05:16:42'),
(759, 1, 'EDIT_USER', 'Admin mengubah user: S. Ginting, S.Kom., MMSI.,M.Tr.Hanla', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/141.0.0.0 Safari/537.36', NULL, '2025-10-29 07:41:32'),
(760, 1, 'EXPORT_USERS', 'Export Manajemen User 36 baris', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/141.0.0.0 Safari/537.36', NULL, '2025-10-29 07:41:55'),
(761, 1, 'EXPORT_LAPORAN_PERJALANAN', 'Export Laporan Perjalanan BULAN OKTOBER 2025 (4 baris)', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/141.0.0.0 Safari/537.36', NULL, '2025-10-29 07:55:54'),
(762, 1, 'EXPORT_RIWAYAT_PERAWATAN', 'Export Riwayat Perawatan 29 baris [Tahun 2025]', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/141.0.0.0 Safari/537.36', NULL, '2025-10-29 08:05:45'),
(763, 1, 'EXPORT_RIWAYAT_PERAWATAN', 'Export Riwayat Perawatan 29 baris', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/141.0.0.0 Safari/537.36', NULL, '2025-10-29 08:05:49'),
(764, 1, 'EXPORT_RIWAYAT_PERBAIKAN', 'Export Riwayat Perbaikan 19 baris [Tahun 2025]', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/141.0.0.0 Safari/537.36', NULL, '2025-10-29 08:05:59'),
(765, 1, 'EXPORT_RIWAYAT_PERBAIKAN', 'Export Riwayat Perbaikan 19 baris', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/141.0.0.0 Safari/537.36', NULL, '2025-10-29 08:06:02'),
(766, 1, 'ADD_JADWAL_PERAWATAN', 'Menambah jadwal perawatan: ss untuk kendaraan ID 87', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/141.0.0.0 Safari/537.36', NULL, '2025-10-29 08:06:34'),
(767, 1, 'COMPLETE_MAINTENANCE', 'Menyelesaikan jadwal perawatan ss untuk kendaraan 2265-19', NULL, NULL, NULL, '2025-10-29 08:06:41'),
(768, 1, 'UPDATE_VEHICLE_STATUS_BY_MAINTENANCE', 'Set kendaraan ID 87 menjadi Operasional/Tersedia setelah perawatan selesai', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/141.0.0.0 Safari/537.36', NULL, '2025-10-29 08:06:41'),
(769, 1, 'COMPLETE_MAINTENANCE', 'Menandai jadwal perawatan selesai: ss untuk kendaraan 2265-19', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/141.0.0.0 Safari/537.36', NULL, '2025-10-29 08:06:41'),
(770, 1, 'CREATE_LAPORAN_PERJALANAN', 'Tambah laporan untuk kendaraan ID 82 (pengguna ID 24)', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/141.0.0.0 Safari/537.36', NULL, '2025-10-29 08:07:00'),
(771, 1, 'EXPORT_LAPORAN_PERJALANAN', 'Export Laporan Perjalanan BULAN OKTOBER 2025 (5 baris)', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/141.0.0.0 Safari/537.36', NULL, '2025-10-29 08:11:15'),
(772, 1, 'EXPORT_LAPORAN_PERJALANAN', 'Export Laporan Perjalanan BULAN OKTOBER 2025 (5 baris)', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/141.0.0.0 Safari/537.36', NULL, '2025-10-29 08:17:37'),
(773, 1, 'EXPORT_LAPORAN_PERJALANAN', 'Export Laporan Perjalanan BULAN OKTOBER 2025 (5 baris)', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/141.0.0.0 Safari/537.36', NULL, '2025-10-30 05:31:17'),
(774, 1, 'CREATE_LAPORAN_PERJALANAN', 'Tambah laporan perjalanan untuk kendaraan No.Reg 3527-19 (pengguna Erwin Kurnia N.M., S.T., M.Si.,(Han))', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/141.0.0.0 Safari/537.36', NULL, '2025-10-30 05:38:09'),
(775, 1, 'EXPORT_LAPORAN_PERJALANAN', 'Export Laporan Perjalanan BULAN OKTOBER 2025 (6 baris)', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/141.0.0.0 Safari/537.36', NULL, '2025-10-30 05:38:27'),
(776, 1, 'CREATE_LAPORAN_PERJALANAN', 'Tambah laporan perjalanan untuk kendaraan No.Reg 3523-19 (pengguna Dwi Yudo Putro Prasojo)', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/141.0.0.0 Safari/537.36', NULL, '2025-10-30 05:52:42'),
(777, 1, 'CREATE_LAPORAN_PERJALANAN', 'Tambah laporan perjalanan untuk kendaraan No.Reg 3523-19 (pengguna Admin)', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/141.0.0.0 Safari/537.36', NULL, '2025-10-30 05:59:22'),
(778, 1, 'EXPORT_LAPORAN_PERJALANAN', 'Export Laporan Perjalanan BULAN OKTOBER 2025 (8 baris)', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/141.0.0.0 Safari/537.36', NULL, '2025-10-30 14:09:55'),
(780, 1, 'APPROVE_MAINTENANCE_REQUEST', 'Admin menyetujui pengajuan perawatan ID 68', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/141.0.0.0 Safari/537.36', NULL, '2025-11-03 23:07:33'),
(781, 1, 'COMPLETE_MAINTENANCE', 'Menyelesaikan jadwal perawatan Ganti Oli untuk kendaraan 8272-02', NULL, NULL, NULL, '2025-11-03 23:07:56'),
(782, 1, 'UPDATE_VEHICLE_STATUS_BY_MAINTENANCE', 'Set kendaraan ID 24 menjadi Operasional/Tersedia setelah perawatan selesai', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/141.0.0.0 Safari/537.36', NULL, '2025-11-03 23:07:56'),
(783, 1, 'COMPLETE_MAINTENANCE', 'Menandai jadwal perawatan selesai: Ganti Oli untuk kendaraan 8272-02', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/141.0.0.0 Safari/537.36', NULL, '2025-11-03 23:07:56'),
(784, 1, 'LOGOUT', 'User keluar dari sistem', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/141.0.0.0 Safari/537.36', NULL, '2025-11-05 05:35:50'),
(785, 1, 'LOGIN', 'User berhasil login ke sistem', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/141.0.0.0 Safari/537.36', NULL, '2025-11-05 05:38:14'),
(786, 1, 'RESET_PASSWORD', 'Admin mereset password user: john.doe', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/141.0.0.0 Safari/537.36', NULL, '2025-11-05 05:54:54'),
(789, 1, 'EDIT_USER', 'Admin mengubah user id: 13', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/141.0.0.0 Safari/537.36', NULL, '2025-11-05 06:00:24'),
(790, 1, 'DELETE_USER', 'Admin menghapus user: asn', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/142.0.0.0 Safari/537.36', NULL, '2025-11-06 02:39:20'),
(791, 1, 'ADD_JADWAL_PERAWATAN', 'Menambah jadwal perawatan: d untuk kendaraan ID 87', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/142.0.0.0 Safari/537.36', NULL, '2025-11-06 02:43:08'),
(792, 1, 'CREATE_VEHICLE', 'Menambahkan kendaraan baru: 123 - Toyota Vios', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/142.0.0.0 Safari/537.36', NULL, '2025-11-06 02:44:08'),
(793, 1, 'COMPLETE_MAINTENANCE', 'Menyelesaikan jadwal perawatan x untuk kendaraan 0822-02', NULL, NULL, NULL, '2025-11-06 02:47:04'),
(794, 1, 'UPDATE_VEHICLE_STATUS_BY_MAINTENANCE', 'Set kendaraan ID 25 menjadi Operasional/Tersedia setelah perawatan selesai', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/142.0.0.0 Safari/537.36', NULL, '2025-11-06 02:47:04'),
(795, 1, 'COMPLETE_MAINTENANCE', 'Menandai jadwal perawatan selesai: x untuk kendaraan 0822-02', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/142.0.0.0 Safari/537.36', NULL, '2025-11-06 02:47:04'),
(796, 1, 'COMPLETE_MAINTENANCE', 'Menyelesaikan jadwal perawatan Lainnya untuk kendaraan 5681-19', NULL, NULL, NULL, '2025-11-06 02:47:10'),
(797, 1, 'UPDATE_VEHICLE_STATUS_BY_MAINTENANCE', 'Set kendaraan ID 90 menjadi Operasional/Tersedia setelah perawatan selesai', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/142.0.0.0 Safari/537.36', NULL, '2025-11-06 02:47:10'),
(798, 1, 'COMPLETE_MAINTENANCE', 'Menandai jadwal perawatan selesai: Lainnya untuk kendaraan 5681-19', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/142.0.0.0 Safari/537.36', NULL, '2025-11-06 02:47:10'),
(801, 1, 'RESET_PASSWORD', 'Admin mereset password user: john.doe', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/142.0.0.0 Safari/537.36', NULL, '2025-11-06 02:50:25'),
(804, 1, 'DELETE_VEHICLE', 'Menghapus kendaraan: 123 - Toyota Vios', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/142.0.0.0 Safari/537.36', NULL, '2025-11-06 06:29:20'),
(805, 1, 'CREATE_VEHICLE', 'Menambahkan kendaraan baru: 123 - sjs', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/142.0.0.0 Safari/537.36', NULL, '2025-11-06 06:29:45'),
(807, 1, 'LOGIN', 'User berhasil login ke sistem', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/142.0.0.0 Safari/537.36', NULL, '2025-11-06 06:37:35'),
(808, 1, 'LOGOUT', 'User keluar dari sistem', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/142.0.0.0 Safari/537.36', NULL, '2025-11-06 06:40:43'),
(811, 1, 'LOGIN', 'User berhasil login ke sistem', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/142.0.0.0 Safari/537.36', NULL, '2025-11-06 06:43:06'),
(812, 1, 'LOGIN', 'User berhasil login ke sistem', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/142.0.0.0 Safari/537.36', NULL, '2025-12-16 06:33:17'),
(813, 1, 'COMPLETE_MAINTENANCE', 'Menyelesaikan jadwal perawatan Servis Berkala untuk kendaraan 5681-19', NULL, NULL, NULL, '2025-12-16 06:33:30'),
(814, 1, 'UPDATE_VEHICLE_STATUS_BY_MAINTENANCE', 'Set kendaraan ID 90 menjadi Operasional/Tersedia setelah perawatan selesai', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/142.0.0.0 Safari/537.36', NULL, '2025-12-16 06:33:30'),
(815, 1, 'COMPLETE_MAINTENANCE', 'Menandai jadwal perawatan selesai: Servis Berkala untuk kendaraan 5681-19', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/142.0.0.0 Safari/537.36', NULL, '2025-12-16 06:33:30'),
(816, 1, 'COMPLETE_MAINTENANCE', 'Menyelesaikan jadwal perawatan d untuk kendaraan 2265-19', NULL, NULL, NULL, '2025-12-16 06:33:34'),
(817, 1, 'UPDATE_VEHICLE_STATUS_BY_MAINTENANCE', 'Set kendaraan ID 87 menjadi Operasional/Tersedia setelah perawatan selesai', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/142.0.0.0 Safari/537.36', NULL, '2025-12-16 06:33:34'),
(818, 1, 'COMPLETE_MAINTENANCE', 'Menandai jadwal perawatan selesai: d untuk kendaraan 2265-19', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/142.0.0.0 Safari/537.36', NULL, '2025-12-16 06:33:34'),
(819, 1, 'EDIT_USER', 'Admin mengubah user: Ehud S', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/142.0.0.0 Safari/537.36', NULL, '2025-12-16 06:34:31'),
(820, 1, 'RESET_PASSWORD', 'Admin mereset password user: ehud', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/142.0.0.0 Safari/537.36', NULL, '2025-12-16 06:34:46'),
(821, 1, 'LOGIN', 'User berhasil login ke sistem', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36', NULL, '2026-03-28 07:38:07'),
(822, 1, 'LOGIN', 'User berhasil login ke sistem', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36', NULL, '2026-03-28 07:38:39'),
(823, 1, 'LOGOUT', 'User keluar dari sistem', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36', NULL, '2026-04-06 15:38:33'),
(824, 1, 'LOGIN', 'User berhasil login ke sistem', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36', NULL, '2026-04-06 15:38:39'),
(825, 1, 'UPDATE_VEHICLE', 'Mengupdate kendaraan: 123 - sjs', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36', NULL, '2026-04-06 15:41:14'),
(826, 1, 'UPDATE_VEHICLE', 'Mengupdate kendaraan: 123 - sjs', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36', NULL, '2026-04-08 13:20:03'),
(827, 1, 'LOGIN', 'User berhasil login ke sistem', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36', NULL, '2026-04-08 13:20:29'),
(828, 1, 'LOGOUT', 'User keluar dari sistem', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36', NULL, '2026-04-08 13:23:06'),
(829, 1, 'LOGIN', 'User berhasil login ke sistem', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36', NULL, '2026-04-08 13:23:25'),
(830, 1, 'RESET_PASSWORD', 'Admin mereset password user: ginting', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36', NULL, '2026-04-08 13:57:37'),
(834, 1, 'EDIT_USER', 'Admin mengubah user: S. Ginting, S.Kom., MMSI.,M.Tr.Hanla', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36', NULL, '2026-04-08 13:59:27'),
(837, 1, 'EDIT_USER', 'Admin mengubah user: Restu Putra, S.Kom', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36', NULL, '2026-04-08 14:12:05'),
(839, 1, 'RESET_PASSWORD', 'Admin mereset password user: user3', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36', NULL, '2026-04-08 14:12:48'),
(841, 1, 'LOGOUT', 'User keluar dari sistem', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36', NULL, '2026-04-08 14:25:36'),
(842, 1, 'LOGIN', 'User berhasil login ke sistem', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36', NULL, '2026-04-08 14:25:44'),
(843, 1, 'LOGOUT', 'User keluar dari sistem', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36', NULL, '2026-04-08 14:30:17'),
(844, 1, 'LOGIN', 'User berhasil login ke sistem', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36', NULL, '2026-04-08 14:30:24'),
(845, 1, 'LOGIN', 'User berhasil login ke sistem', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36', NULL, '2026-04-08 14:38:00'),
(846, 1, 'LOGOUT', 'User keluar dari sistem', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36', NULL, '2026-04-09 07:13:25'),
(847, 1, 'LOGIN', 'User berhasil login ke sistem', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36', NULL, '2026-04-09 07:13:49'),
(848, 1, 'LOGOUT', 'User keluar dari sistem', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36', NULL, '2026-04-09 07:14:03'),
(849, 1, 'LOGIN', 'User berhasil login ke sistem', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36', NULL, '2026-04-09 07:14:27'),
(850, 1, 'LOGIN', 'User berhasil login ke sistem', '192.168.1.109', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36', NULL, '2026-04-09 08:24:41'),
(851, 1, 'CREATE_VEHICLE', 'Menambah kendaraan: 3222-00 - Hino', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36', NULL, '2026-04-13 12:49:54'),
(852, 1, 'UPDATE_VEHICLE', 'Mengupdate kendaraan: 3222-00 - Hino', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36', NULL, '2026-04-13 13:11:03'),
(853, 1, 'LOGIN', 'User berhasil login ke sistem', '10.239.171.233', 'Mozilla/5.0 (Linux; Android 10; K) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Mobile Safari/537.36', NULL, '2026-04-20 09:52:37'),
(854, 1, 'CREATE_USER', 'Admin membuat user: pimpinan', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/147.0.0.0 Safari/537.36', NULL, '2026-04-23 11:52:37'),
(855, 43, 'LOGIN', 'User berhasil login ke sistem', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/147.0.0.0 Safari/537.36 Edg/147.0.0.0', NULL, '2026-04-23 11:52:57'),
(856, 43, 'LOGIN', 'User berhasil login ke sistem', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/147.0.0.0 Safari/537.36 Edg/147.0.0.0', NULL, '2026-04-23 11:53:09'),
(857, 1, 'LOGIN', 'User berhasil login ke sistem', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/147.0.0.0 Safari/537.36 Edg/147.0.0.0', NULL, '2026-04-23 11:54:40'),
(858, 1, 'LOGOUT', 'User keluar dari sistem', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/147.0.0.0 Safari/537.36 Edg/147.0.0.0', NULL, '2026-04-23 12:16:41'),
(859, 43, 'LOGIN', 'User berhasil login ke sistem', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/147.0.0.0 Safari/537.36 Edg/147.0.0.0', NULL, '2026-04-23 12:16:49'),
(860, 43, 'LOGIN', 'User berhasil login ke sistem', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/147.0.0.0 Safari/537.36 Edg/147.0.0.0', NULL, '2026-04-23 12:19:57'),
(861, 43, 'LOGOUT', 'User keluar dari sistem', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/147.0.0.0 Safari/537.36 Edg/147.0.0.0', NULL, '2026-04-23 12:26:14'),
(867, 43, 'LOGIN', 'User berhasil login ke sistem', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/147.0.0.0 Safari/537.36 Edg/147.0.0.0', NULL, '2026-04-23 12:37:30'),
(868, 43, 'LOGOUT', 'User keluar dari sistem', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/147.0.0.0 Safari/537.36 Edg/147.0.0.0', NULL, '2026-04-23 12:38:11'),
(869, 1, 'LOGIN', 'User berhasil login ke sistem', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/147.0.0.0 Safari/537.36 Edg/147.0.0.0', NULL, '2026-04-23 12:38:21'),
(870, 1, 'EDIT_USER', 'Admin mengubah user: pimpinan', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/147.0.0.0 Safari/537.36 Edg/147.0.0.0', NULL, '2026-04-23 12:38:48'),
(871, 1, 'EDIT_USER', 'Admin mengubah user: RIJAL SURYADI', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/147.0.0.0 Safari/537.36 Edg/147.0.0.0', NULL, '2026-04-23 12:40:11'),
(872, 1, 'LOGOUT', 'User keluar dari sistem', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/147.0.0.0 Safari/537.36 Edg/147.0.0.0', NULL, '2026-04-23 12:40:14'),
(873, 1, 'LOGIN', 'User berhasil login ke sistem', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/147.0.0.0 Safari/537.36 Edg/147.0.0.0', NULL, '2026-04-23 12:40:39'),
(874, 1, 'RESET_PASSWORD', 'Admin mereset password user: driver', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/147.0.0.0 Safari/537.36 Edg/147.0.0.0', NULL, '2026-04-23 12:40:56'),
(875, 1, 'LOGOUT', 'User keluar dari sistem', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/147.0.0.0 Safari/537.36 Edg/147.0.0.0', NULL, '2026-04-23 12:40:59'),
(877, 1, 'UPDATE_VEHICLE', 'Mengupdate kendaraan: 3525-19 - Toyota Altis', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/147.0.0.0 Safari/537.36', NULL, '2026-04-23 12:42:46'),
(882, 1, 'UPDATE_VEHICLE', 'Mengupdate kendaraan: 3525-19 - Toyota Altis', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/147.0.0.0 Safari/537.36', NULL, '2026-04-24 01:54:02'),
(883, 1, 'UPDATE_VEHICLE', 'Mengupdate kendaraan: 2272-19 - Hino', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/147.0.0.0 Safari/537.36', NULL, '2026-04-24 01:54:54'),
(884, 1, 'LOGOUT', 'User keluar dari sistem', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/147.0.0.0 Safari/537.36', NULL, '2026-04-24 01:55:25'),
(885, 43, 'LOGIN', 'User berhasil login ke sistem', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/147.0.0.0 Safari/537.36', NULL, '2026-04-24 01:55:35'),
(886, 43, 'LOGOUT', 'User keluar dari sistem', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/147.0.0.0 Safari/537.36', NULL, '2026-04-24 01:57:19'),
(887, 1, 'LOGIN', 'User berhasil login ke sistem', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/147.0.0.0 Safari/537.36', NULL, '2026-04-24 01:57:24'),
(888, 1, 'UPDATE_VEHICLE', 'Mengupdate kendaraan: 2272-19 - Hino', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/147.0.0.0 Safari/537.36', NULL, '2026-04-24 02:02:26'),
(889, 1, 'UPDATE_VEHICLE', 'Mengupdate kendaraan: 2272-19 - Hino', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/147.0.0.0 Safari/537.36', NULL, '2026-04-24 02:02:43'),
(890, 1, 'UPDATE_VEHICLE', 'Mengupdate kendaraan: 2272-19 - Hino', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/147.0.0.0 Safari/537.36', NULL, '2026-04-24 02:27:47'),
(891, 1, 'EDIT_USER', 'Admin mengubah user: Ikhwan', '::1', 'Mozilla/5.0 (Linux; Android 6.0; Nexus 5 Build/MRA58N) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/147.0.0.0 Mobile Safari/537.36', NULL, '2026-04-24 03:42:31'),
(892, 1, 'EDIT_USER', 'Admin mengubah user: Asep R', '::1', 'Mozilla/5.0 (Linux; Android 6.0; Nexus 5 Build/MRA58N) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/147.0.0.0 Mobile Safari/537.36', NULL, '2026-04-24 03:43:49'),
(893, 1, 'EDIT_USER', 'Admin mengubah user: Isak', '::1', 'Mozilla/5.0 (Linux; Android 6.0; Nexus 5 Build/MRA58N) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/147.0.0.0 Mobile Safari/537.36', NULL, '2026-04-24 04:39:20'),
(894, 1, 'LOGOUT', 'User keluar dari sistem', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/147.0.0.0 Safari/537.36', NULL, '2026-05-06 08:07:38'),
(895, 1, 'LOGIN', 'User berhasil login ke sistem', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/147.0.0.0 Safari/537.36', NULL, '2026-05-06 08:08:01'),
(896, 1, 'LOGOUT', 'User keluar dari sistem', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/147.0.0.0 Safari/537.36', NULL, '2026-05-06 08:08:25'),
(897, 1, 'LOGIN', 'User berhasil login ke sistem', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/147.0.0.0 Safari/537.36', NULL, '2026-05-06 08:08:42'),
(898, 1, 'RESET_PASSWORD', 'Admin mereset password user: aminudin', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/147.0.0.0 Safari/537.36', NULL, '2026-05-06 08:09:04'),
(899, 1, 'LOGOUT', 'User keluar dari sistem', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/147.0.0.0 Safari/537.36', NULL, '2026-05-06 08:09:07'),
(900, 55, 'LOGIN', 'User berhasil login ke sistem', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/147.0.0.0 Safari/537.36', NULL, '2026-05-06 08:09:14'),
(901, 55, 'LOGOUT', 'User keluar dari sistem', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/147.0.0.0 Safari/537.36', NULL, '2026-05-06 08:10:14'),
(902, 1, 'LOGIN', 'User berhasil login ke sistem', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/147.0.0.0 Safari/537.36', NULL, '2026-05-06 08:11:25'),
(903, 1, 'EDIT_USER', 'Admin mengubah user: pimpinan', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/147.0.0.0 Safari/537.36', NULL, '2026-05-06 08:11:41'),
(904, 1, 'RESET_PASSWORD', 'Admin mereset password user: pimpinan', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/147.0.0.0 Safari/537.36', NULL, '2026-05-06 08:11:59'),
(905, 1, 'LOGOUT', 'User keluar dari sistem', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/147.0.0.0 Safari/537.36', NULL, '2026-05-06 08:12:01'),
(906, 43, 'LOGIN', 'User berhasil login ke sistem', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/147.0.0.0 Safari/537.36', NULL, '2026-05-06 08:12:09'),
(907, 43, 'LOGOUT', 'User keluar dari sistem', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/147.0.0.0 Safari/537.36', NULL, '2026-05-06 08:46:52'),
(908, 1, 'LOGIN', 'User berhasil login ke sistem', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/147.0.0.0 Safari/537.36', NULL, '2026-05-06 08:53:06'),
(909, 1, 'CREATE_USER', 'Admin membuat user: user', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/147.0.0.0 Safari/537.36', NULL, '2026-05-06 09:00:54'),
(910, 1, 'LOGOUT', 'User keluar dari sistem', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/147.0.0.0 Safari/537.36', NULL, '2026-05-06 09:00:56'),
(911, 80, 'LOGIN', 'User berhasil login ke sistem', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/147.0.0.0 Safari/537.36', NULL, '2026-05-06 09:01:05'),
(912, 80, 'LOGOUT', 'User keluar dari sistem', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/147.0.0.0 Safari/537.36', NULL, '2026-05-06 09:05:10'),
(913, 1, 'LOGIN', 'User berhasil login ke sistem', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/147.0.0.0 Safari/537.36', NULL, '2026-05-06 09:05:18'),
(914, 1, 'UPDATE_VEHICLE', 'Mengupdate kendaraan: 8891-00 - Hino', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/147.0.0.0 Safari/537.36', NULL, '2026-05-06 09:14:58'),
(915, 1, 'UPDATE_VEHICLE', 'Mengupdate kendaraan: 8891-00 - Hino', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/147.0.0.0 Safari/537.36', NULL, '2026-05-06 09:14:59'),
(916, 1, 'UPDATE_VEHICLE', 'Mengupdate kendaraan: 7799-00 - Hino', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/147.0.0.0 Safari/537.36', NULL, '2026-05-06 09:18:42'),
(917, 1, 'UPDATE_VEHICLE', 'Mengupdate kendaraan: 7799-00 - Hino', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/147.0.0.0 Safari/537.36', NULL, '2026-05-06 09:20:01'),
(918, 1, 'UPDATE_VEHICLE', 'Mengupdate kendaraan: 7807-00 - Hino', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/147.0.0.0 Safari/537.36', NULL, '2026-05-06 09:21:43'),
(919, 1, 'UPDATE_VEHICLE', 'Mengupdate kendaraan: 7701-00 - Mitsubishi', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/147.0.0.0 Safari/537.36', NULL, '2026-05-06 09:23:54'),
(920, 1, 'UPDATE_VEHICLE', 'Mengupdate kendaraan: 7701-00 - Mitsubishi', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/147.0.0.0 Safari/537.36', NULL, '2026-05-06 09:23:54'),
(921, 1, 'UPDATE_VEHICLE', 'Mengupdate kendaraan: 7701-00 - Mitsubishi', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/147.0.0.0 Safari/537.36', NULL, '2026-05-06 10:29:28'),
(922, 1, 'UPDATE_VEHICLE', 'Mengupdate kendaraan: 7697-00 - Mitsubishi', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/147.0.0.0 Safari/537.36', NULL, '2026-05-06 10:32:01'),
(923, 1, 'UPDATE_VEHICLE', 'Mengupdate kendaraan: 7690-00 - Mercedes benz', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/147.0.0.0 Safari/537.36', NULL, '2026-05-06 10:38:39'),
(926, 1, 'TEST_CLI', 'Testing log_activity mapping', 'UNKNOWN', 'UNKNOWN', NULL, '2026-05-06 18:11:31'),
(927, 80, 'LOGIN', 'User berhasil login ke sistem', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/147.0.0.0 Safari/537.36 Edg/147.0.0.0', NULL, '2026-05-06 18:22:20'),
(928, 80, 'LOGOUT', 'User keluar dari sistem', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/147.0.0.0 Safari/537.36 Edg/147.0.0.0', NULL, '2026-05-06 18:22:43'),
(929, 43, 'LOGIN', 'User berhasil login ke sistem', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/147.0.0.0 Safari/537.36 Edg/147.0.0.0', NULL, '2026-05-06 18:22:52'),
(930, 1, 'EDIT_USER', 'Admin mengubah user: Lingga', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/147.0.0.0 Safari/537.36', NULL, '2026-05-06 18:25:26'),
(931, 1, 'RESET_PASSWORD', 'Admin mereset password user: lingga', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/147.0.0.0 Safari/537.36', NULL, '2026-05-06 18:25:44'),
(932, 70, 'LOGIN', 'User berhasil login ke sistem', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/147.0.0.0 Safari/537.36 Edg/147.0.0.0', NULL, '2026-05-06 18:25:53'),
(933, 1, 'UPDATE_VEHICLE', 'Mengupdate kendaraan: 7726-00 - Hino', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/147.0.0.0 Safari/537.36', NULL, '2026-05-07 10:11:11'),
(934, 1, 'UPDATE_VEHICLE', 'Mengupdate kendaraan: 7607-00 - Mercedes benz', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/147.0.0.0 Safari/537.36', NULL, '2026-05-07 10:19:01'),
(935, 1, 'LOGIN', 'User berhasil login ke sistem', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/147.0.0.0 Safari/537.36', NULL, '2026-05-12 11:19:08'),
(936, 43, 'LOGIN', 'User berhasil login ke sistem', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/147.0.0.0 Safari/537.36 Edg/147.0.0.0', NULL, '2026-05-12 11:19:41'),
(937, 70, 'LOGOUT', 'User keluar dari sistem', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/147.0.0.0 Safari/537.36 Edg/147.0.0.0', NULL, '2026-05-12 11:57:21'),
(938, 70, 'LOGIN', 'User berhasil login ke sistem', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/147.0.0.0 Safari/537.36 Edg/147.0.0.0', NULL, '2026-05-12 11:57:32'),
(939, 70, 'LOGOUT', 'User keluar dari sistem', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/147.0.0.0 Safari/537.36 Edg/147.0.0.0', NULL, '2026-05-12 12:07:43'),
(940, 70, 'LOGIN', 'User berhasil login ke sistem', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/147.0.0.0 Safari/537.36 Edg/147.0.0.0', NULL, '2026-05-12 12:07:50'),
(941, 70, 'LOGOUT', 'User keluar dari sistem', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/147.0.0.0 Safari/537.36 Edg/147.0.0.0', NULL, '2026-05-12 12:13:17'),
(942, 70, 'LOGIN', 'User berhasil login ke sistem', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/147.0.0.0 Safari/537.36 Edg/147.0.0.0', NULL, '2026-05-12 12:13:24'),
(943, 43, 'LOGOUT', 'User keluar dari sistem', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/147.0.0.0 Safari/537.36 Edg/147.0.0.0', NULL, '2026-05-12 12:14:26'),
(944, 1, 'LOGIN', 'User berhasil login ke sistem', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/147.0.0.0 Safari/537.36 Edg/147.0.0.0', NULL, '2026-05-12 12:17:34'),
(945, 1, 'LOGOUT', 'User keluar dari sistem', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/147.0.0.0 Safari/537.36', NULL, '2026-05-12 12:51:11'),
(946, 1, 'DELETE_VEHICLE', 'Menghapus kendaraan: 7706-0000 - Isuzu', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/147.0.0.0 Safari/537.36 Edg/147.0.0.0', NULL, '2026-05-12 19:46:48'),
(947, 1, 'ADD_JADWAL_PERAWATAN', 'Menambah jadwal perawatan: Service untuk kendaraan ID 134', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/147.0.0.0 Safari/537.36 Edg/147.0.0.0', NULL, '2026-05-12 20:11:41'),
(948, 70, 'LOGOUT', 'User keluar dari sistem', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/147.0.0.0 Safari/537.36 Edg/147.0.0.0', NULL, '2026-05-13 07:09:19'),
(949, 70, 'LOGIN', 'User berhasil login ke sistem', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/147.0.0.0 Safari/537.36 Edg/147.0.0.0', NULL, '2026-05-13 07:09:29'),
(950, 70, 'LOGOUT', 'User keluar dari sistem', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/147.0.0.0 Safari/537.36 Edg/147.0.0.0', NULL, '2026-05-13 07:09:58'),
(951, 43, 'LOGIN', 'User berhasil login ke sistem', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/147.0.0.0 Safari/537.36 Edg/147.0.0.0', NULL, '2026-05-13 07:10:05'),
(952, 43, 'LOGOUT', 'User keluar dari sistem', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/147.0.0.0 Safari/537.36 Edg/147.0.0.0', NULL, '2026-05-13 07:10:28'),
(953, 1, 'LOGIN', 'User berhasil login ke sistem', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/147.0.0.0 Safari/537.36 Edg/147.0.0.0', NULL, '2026-05-13 07:10:34'),
(954, 1, 'LOGOUT', 'User keluar dari sistem', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/147.0.0.0 Safari/537.36 Edg/147.0.0.0', NULL, '2026-05-13 07:46:03'),
(955, 80, 'LOGIN', 'User berhasil login ke sistem', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/147.0.0.0 Safari/537.36 Edg/147.0.0.0', NULL, '2026-05-13 07:46:12'),
(956, 1, 'EDIT_USER', 'Admin mengubah user: user', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/147.0.0.0 Safari/537.36 Edg/147.0.0.0', NULL, '2026-05-13 07:48:15'),
(957, 1, 'CREATE_USER', 'Admin membuat user: operator', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/147.0.0.0 Safari/537.36 Edg/147.0.0.0', NULL, '2026-05-13 07:50:59'),
(958, 1, 'LOGOUT', 'User keluar dari sistem', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/147.0.0.0 Safari/537.36 Edg/147.0.0.0', NULL, '2026-05-13 07:51:04'),
(959, 82, 'LOGIN', 'User berhasil login ke sistem', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/147.0.0.0 Safari/537.36 Edg/147.0.0.0', NULL, '2026-05-13 07:51:15'),
(960, 82, 'LOGOUT', 'User keluar dari sistem', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/147.0.0.0 Safari/537.36 Edg/147.0.0.0', NULL, '2026-05-13 07:53:37'),
(961, 70, 'LOGIN', 'User berhasil login ke sistem', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/147.0.0.0 Safari/537.36 Edg/147.0.0.0', NULL, '2026-05-13 07:53:45'),
(962, 70, 'ADD_RIWAYAT_PERBAIKAN', 'Tambah riwayat perbaikan Perbaikan Mesin untuk kendaraan No.Reg 7690-00', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/147.0.0.0 Safari/537.36 Edg/147.0.0.0', NULL, '2026-05-13 08:30:09'),
(963, 80, 'LOGOUT', 'User keluar dari sistem', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/147.0.0.0 Safari/537.36 Edg/147.0.0.0', NULL, '2026-05-13 08:30:21'),
(964, 1, 'LOGIN', 'User berhasil login ke sistem', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/147.0.0.0 Safari/537.36 Edg/147.0.0.0', NULL, '2026-05-13 08:30:27'),
(965, 70, 'ADD_RIWAYAT_PERBAIKAN', 'Tambah riwayat perbaikan Perbaikan Transmisi untuk kendaraan No.Reg 7690-00', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/147.0.0.0 Safari/537.36 Edg/147.0.0.0', NULL, '2026-05-13 08:34:53'),
(966, 70, 'LOGOUT', 'User keluar dari sistem', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/147.0.0.0 Safari/537.36 Edg/147.0.0.0', NULL, '2026-05-13 08:39:22'),
(967, 70, 'LOGIN', 'User berhasil login ke sistem', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/147.0.0.0 Safari/537.36 Edg/147.0.0.0', NULL, '2026-05-13 08:39:31'),
(968, 70, 'ADD_RIWAYAT_PERBAIKAN', 'Tambah riwayat perbaikan Perbaikan Mesin untuk kendaraan No.Reg 7690-00', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/147.0.0.0 Safari/537.36 Edg/147.0.0.0', NULL, '2026-05-13 08:40:10'),
(969, 1, 'CREATE_USER', 'Admin membuat user: driver', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/147.0.0.0 Safari/537.36 Edg/147.0.0.0', NULL, '2026-05-13 10:46:37'),
(970, 1, 'UPDATE_VEHICLE', 'Mengupdate kendaraan: 2272-19 - Hino', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/147.0.0.0 Safari/537.36 Edg/147.0.0.0', NULL, '2026-05-13 10:48:36'),
(971, 1, 'UPDATE_VEHICLE', 'Mengupdate kendaraan: 7774-08 - Isuzu', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/147.0.0.0 Safari/537.36 Edg/147.0.0.0', NULL, '2026-05-13 10:49:38'),
(972, 1, 'UPDATE_VEHICLE', 'Mengupdate kendaraan: 7774-08 - Isuzu', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/147.0.0.0 Safari/537.36 Edg/147.0.0.0', NULL, '2026-05-13 11:06:25'),
(973, 1, 'UPDATE_VEHICLE', 'Mengupdate kendaraan: 7774-08 - Isuzu', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/147.0.0.0 Safari/537.36 Edg/147.0.0.0', NULL, '2026-05-13 11:13:15'),
(974, 70, 'LOGIN', 'User berhasil login ke sistem', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/147.0.0.0 Safari/537.36 Edg/147.0.0.0', NULL, '2026-05-13 20:18:06'),
(975, 70, 'ADD_BBM_LOG', 'Menambah log BBM untuk kendaraan ID 143: 19.95 liter', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/147.0.0.0 Safari/537.36 Edg/147.0.0.0', NULL, '2026-05-13 20:18:59'),
(976, 70, 'LOGOUT', 'User keluar dari sistem', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/147.0.0.0 Safari/537.36 Edg/147.0.0.0', NULL, '2026-05-15 21:25:31'),
(977, 43, 'LOGIN', 'User berhasil login ke sistem', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/147.0.0.0 Safari/537.36 Edg/147.0.0.0', NULL, '2026-05-15 21:25:41'),
(978, 1, 'EDIT_USER', 'Admin mengubah user: Joko S', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/147.0.0.0 Safari/537.36 Edg/147.0.0.0', NULL, '2026-05-15 21:54:12'),
(979, 43, 'LOGOUT', 'User keluar dari sistem', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/147.0.0.0 Safari/537.36 Edg/147.0.0.0', NULL, '2026-05-15 21:54:55'),
(980, 1, 'RESET_PASSWORD', 'Admin mereset password user: joko.s', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/147.0.0.0 Safari/537.36 Edg/147.0.0.0', NULL, '2026-05-15 21:55:23'),
(981, 50, 'LOGIN', 'User berhasil login ke sistem', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/147.0.0.0 Safari/537.36 Edg/147.0.0.0', NULL, '2026-05-15 21:55:31'),
(982, 50, 'ADD_BBM_LOG', 'Menambah log BBM untuk kendaraan ID 123: 20 liter', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/147.0.0.0 Safari/537.36 Edg/147.0.0.0', NULL, '2026-05-15 21:57:34'),
(983, 1, 'RESET_PASSWORD', 'Admin mereset password user: rusdi', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/147.0.0.0 Safari/537.36 Edg/147.0.0.0', NULL, '2026-05-15 22:33:28'),
(984, 50, 'LOGOUT', 'User keluar dari sistem', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/147.0.0.0 Safari/537.36 Edg/147.0.0.0', NULL, '2026-05-15 23:53:18'),
(985, 50, 'LOGIN', 'User berhasil login ke sistem', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/147.0.0.0 Safari/537.36 Edg/147.0.0.0', NULL, '2026-05-15 23:53:38'),
(986, 50, 'ADD_RIWAYAT_PERBAIKAN', 'Tambah riwayat perbaikan Perbaikan Ban untuk kendaraan No.Reg 7701-00', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/147.0.0.0 Safari/537.36 Edg/147.0.0.0', NULL, '2026-05-16 00:26:41'),
(987, 50, 'LOGOUT', 'User keluar dari sistem', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/147.0.0.0 Safari/537.36 Edg/147.0.0.0', NULL, '2026-05-17 08:08:02'),
(988, 83, 'LOGIN', 'User berhasil login ke sistem', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/147.0.0.0 Safari/537.36 Edg/147.0.0.0', NULL, '2026-05-17 08:08:17'),
(989, 83, 'LOGOUT', 'User keluar dari sistem', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/147.0.0.0 Safari/537.36 Edg/147.0.0.0', NULL, '2026-05-17 08:09:08'),
(990, 50, 'LOGIN', 'User berhasil login ke sistem', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/147.0.0.0 Safari/537.36 Edg/147.0.0.0', NULL, '2026-05-17 08:09:18'),
(991, 50, 'LOGOUT', 'User keluar dari sistem', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/147.0.0.0 Safari/537.36 Edg/147.0.0.0', NULL, '2026-05-17 08:10:57'),
(992, 80, 'LOGIN', 'User berhasil login ke sistem', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/147.0.0.0 Safari/537.36 Edg/147.0.0.0', NULL, '2026-05-17 08:11:03'),
(993, 80, 'LOGOUT', 'User keluar dari sistem', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/147.0.0.0 Safari/537.36 Edg/147.0.0.0', NULL, '2026-05-17 08:42:52'),
(994, 83, 'LOGIN', 'User berhasil login ke sistem', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/147.0.0.0 Safari/537.36 Edg/147.0.0.0', NULL, '2026-05-17 08:43:02'),
(995, 83, 'LOGOUT', 'User keluar dari sistem', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/147.0.0.0 Safari/537.36 Edg/147.0.0.0', NULL, '2026-05-18 15:43:39'),
(996, 83, 'LOGIN', 'User berhasil login ke sistem', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/147.0.0.0 Safari/537.36 Edg/147.0.0.0', NULL, '2026-05-18 15:43:50'),
(997, 83, 'LOGOUT', 'User keluar dari sistem', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/147.0.0.0 Safari/537.36 Edg/147.0.0.0', NULL, '2026-05-18 15:43:56');
INSERT INTO `log_aktivitas` (`id`, `user_id`, `activity_type`, `description`, `ip_address`, `user_agent`, `session_id`, `created_at`) VALUES
(998, 80, 'LOGIN', 'User berhasil login ke sistem', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/147.0.0.0 Safari/537.36 Edg/147.0.0.0', NULL, '2026-05-18 15:44:02'),
(999, 80, 'LOGOUT', 'User keluar dari sistem', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/147.0.0.0 Safari/537.36 Edg/147.0.0.0', NULL, '2026-05-18 17:10:20'),
(1000, 1, 'LOGIN', 'User berhasil login ke sistem', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/147.0.0.0 Safari/537.36 Edg/147.0.0.0', NULL, '2026-05-18 17:12:41'),
(1001, 1, 'LOGOUT', 'User keluar dari sistem', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/147.0.0.0 Safari/537.36 Edg/147.0.0.0', NULL, '2026-05-18 17:37:24'),
(1002, 83, 'LOGIN', 'User berhasil login ke sistem', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/147.0.0.0 Safari/537.36 Edg/147.0.0.0', NULL, '2026-05-18 17:37:32'),
(1003, 80, 'LOGIN', 'User berhasil login ke sistem', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/147.0.0.0 Safari/537.36 Edg/147.0.0.0', NULL, '2026-05-18 17:38:32'),
(1004, 1, 'UPDATE_LAPORAN', 'Update laporan id=20 oleh pengguna 1', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/147.0.0.0 Safari/537.36 Edg/147.0.0.0', NULL, '2026-05-18 17:44:45'),
(1005, 1, 'LOGIN', 'User berhasil login ke sistem', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/148.0.0.0 Safari/537.36 Edg/148.0.0.0', NULL, '2026-05-18 20:04:07'),
(1006, 1, 'UPDATE_JADWAL_STATUS', 'Memperbarui status jadwal perawatan ID: 71 menjadi Dalam Proses', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/148.0.0.0 Safari/537.36 Edg/148.0.0.0', NULL, '2026-05-18 20:09:17'),
(1007, 1, 'LOGOUT', 'User keluar dari sistem', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/148.0.0.0 Safari/537.36 Edg/148.0.0.0', NULL, '2026-05-18 22:39:29'),
(1008, 1, 'LOGIN', 'User berhasil login ke sistem', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/148.0.0.0 Safari/537.36 Edg/148.0.0.0', NULL, '2026-05-18 22:39:38'),
(1009, 1, 'LOGIN', 'User berhasil login ke sistem', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/147.0.0.0 Safari/537.36', NULL, '2026-05-18 22:50:37'),
(1010, 1, 'LOGOUT', 'User keluar dari sistem', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/147.0.0.0 Safari/537.36', NULL, '2026-05-18 23:00:06'),
(1011, 43, 'LOGIN', 'User berhasil login ke sistem', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/147.0.0.0 Safari/537.36', NULL, '2026-05-18 23:00:23'),
(1012, 83, 'LOGIN', 'User berhasil login ke sistem', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/148.0.0.0 Safari/537.36 Edg/148.0.0.0', NULL, '2026-05-18 23:01:31'),
(1013, 83, 'ADD_RIWAYAT_PERBAIKAN', 'Tambah riwayat perbaikan Perbaikan Ban untuk kendaraan No.Reg 7774-08', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/148.0.0.0 Safari/537.36 Edg/148.0.0.0', NULL, '2026-05-18 23:15:23'),
(1014, 1, 'COMPLETE_MAINTENANCE', 'Menyelesaikan jadwal perawatan Service untuk kendaraan 7698-0000', NULL, NULL, NULL, '2026-05-19 01:32:05'),
(1015, 1, 'UPDATE_VEHICLE_STATUS_BY_MAINTENANCE', 'Set kendaraan ID 134 menjadi Operasional/Tersedia setelah perawatan selesai', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/148.0.0.0 Safari/537.36 Edg/148.0.0.0', NULL, '2026-05-19 01:32:06'),
(1016, 1, 'COMPLETE_MAINTENANCE', 'Menandai jadwal perawatan selesai: Service untuk kendaraan 7698-0000', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/148.0.0.0 Safari/537.36 Edg/148.0.0.0', NULL, '2026-05-19 01:32:06'),
(1017, 83, 'LOGOUT', 'User keluar dari sistem', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/148.0.0.0 Safari/537.36 Edg/148.0.0.0', NULL, '2026-05-19 01:34:33'),
(1018, 80, 'LOGIN', 'User berhasil login ke sistem', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/148.0.0.0 Safari/537.36 Edg/148.0.0.0', NULL, '2026-05-19 01:34:51'),
(1019, 80, 'LOGOUT', 'User keluar dari sistem', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/148.0.0.0 Safari/537.36 Edg/148.0.0.0', NULL, '2026-05-19 02:02:07'),
(1020, 83, 'LOGIN', 'User berhasil login ke sistem', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/148.0.0.0 Safari/537.36 Edg/148.0.0.0', NULL, '2026-05-19 02:02:13'),
(1021, 43, 'LOGOUT', 'User keluar dari sistem', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/147.0.0.0 Safari/537.36', NULL, '2026-05-19 02:05:28'),
(1022, 80, 'LOGIN', 'User berhasil login ke sistem', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/147.0.0.0 Safari/537.36', NULL, '2026-05-19 02:05:46'),
(1023, 43, 'LOGIN', 'User berhasil login ke sistem', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/147.0.0.0 Safari/537.36', NULL, '2026-05-19 02:29:53'),
(1024, 83, 'LOGOUT', 'User keluar dari sistem', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/148.0.0.0 Safari/537.36 Edg/148.0.0.0', NULL, '2026-05-19 02:45:38'),
(1025, 55, 'LOGIN', 'User berhasil login ke sistem', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/148.0.0.0 Safari/537.36 Edg/148.0.0.0', NULL, '2026-05-19 02:45:46'),
(1026, 55, 'LOGOUT', 'User keluar dari sistem', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/148.0.0.0 Safari/537.36 Edg/148.0.0.0', NULL, '2026-05-19 02:58:05'),
(1027, 83, 'LOGIN', 'User berhasil login ke sistem', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/148.0.0.0 Safari/537.36 Edg/148.0.0.0', NULL, '2026-05-19 02:58:14'),
(1028, 83, 'LOGOUT', 'User keluar dari sistem', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/148.0.0.0 Safari/537.36 Edg/148.0.0.0', NULL, '2026-05-19 03:00:38'),
(1029, 55, 'LOGIN', 'User berhasil login ke sistem', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/148.0.0.0 Safari/537.36 Edg/148.0.0.0', NULL, '2026-05-19 03:00:47'),
(1030, 55, 'LOGOUT', 'User keluar dari sistem', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/148.0.0.0 Safari/537.36 Edg/148.0.0.0', NULL, '2026-05-19 03:13:30'),
(1031, 55, 'LOGIN', 'User berhasil login ke sistem', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/148.0.0.0 Safari/537.36 Edg/148.0.0.0', NULL, '2026-05-19 03:13:38'),
(1032, 55, 'LOGOUT', 'User keluar dari sistem', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/148.0.0.0 Safari/537.36 Edg/148.0.0.0', NULL, '2026-05-19 03:14:33'),
(1033, 83, 'LOGIN', 'User berhasil login ke sistem', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/148.0.0.0 Safari/537.36 Edg/148.0.0.0', NULL, '2026-05-19 03:14:41'),
(1034, 1, 'EXPORT_LAPORAN_PERJALANAN', 'Export Laporan Perjalanan BULAN MEI 2026 (2 baris)', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/148.0.0.0 Safari/537.36 Edg/148.0.0.0', NULL, '2026-05-19 03:19:18'),
(1035, 1, 'UPDATE_LAPORAN', 'Update laporan id=20 oleh pengguna 1', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/148.0.0.0 Safari/537.36 Edg/148.0.0.0', NULL, '2026-05-19 03:20:33'),
(1036, 83, 'LOGOUT', 'User keluar dari sistem', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/148.0.0.0 Safari/537.36 Edg/148.0.0.0', NULL, '2026-05-19 03:26:10'),
(1037, 55, 'LOGIN', 'User berhasil login ke sistem', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/148.0.0.0 Safari/537.36 Edg/148.0.0.0', NULL, '2026-05-19 03:26:24'),
(1038, 43, 'LOGOUT', 'User keluar dari sistem', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/147.0.0.0 Safari/537.36', NULL, '2026-05-19 03:26:55'),
(1039, 55, 'LOGIN', 'User berhasil login ke sistem', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/147.0.0.0 Safari/537.36', NULL, '2026-05-19 03:27:02'),
(1040, 55, 'LOGOUT', 'User keluar dari sistem', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/147.0.0.0 Safari/537.36', NULL, '2026-05-19 03:36:26'),
(1041, 55, 'LOGIN', 'User berhasil login ke sistem', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/147.0.0.0 Safari/537.36', NULL, '2026-05-19 03:36:32'),
(1042, 55, 'LOGOUT', 'User keluar dari sistem', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/147.0.0.0 Safari/537.36', NULL, '2026-05-19 03:37:07'),
(1043, 43, 'LOGIN', 'User berhasil login ke sistem', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/147.0.0.0 Safari/537.36', NULL, '2026-05-19 03:37:18'),
(1044, 55, 'LOGOUT', 'User keluar dari sistem', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/148.0.0.0 Safari/537.36 Edg/148.0.0.0', NULL, '2026-05-19 03:51:29'),
(1045, 55, 'LOGIN', 'User berhasil login ke sistem', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/148.0.0.0 Safari/537.36 Edg/148.0.0.0', NULL, '2026-05-19 03:51:36'),
(1046, 55, 'SURAT_START', 'Surat tugas id=33 set to Dalam Perjalanan oleh pengguna 55', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/148.0.0.0 Safari/537.36 Edg/148.0.0.0', NULL, '2026-05-19 04:03:33'),
(1047, 55, 'SURAT_START', 'Surat tugas id=33 set to Dalam Perjalanan oleh pengguna 55', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/148.0.0.0 Safari/537.36 Edg/148.0.0.0', NULL, '2026-05-19 04:03:48'),
(1048, 55, 'LOGOUT', 'User keluar dari sistem', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/148.0.0.0 Safari/537.36 Edg/148.0.0.0', NULL, '2026-05-19 04:12:29'),
(1049, 83, 'LOGIN', 'User berhasil login ke sistem', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/148.0.0.0 Safari/537.36 Edg/148.0.0.0', NULL, '2026-05-19 04:12:37'),
(1050, 83, 'LOGOUT', 'User keluar dari sistem', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/148.0.0.0 Safari/537.36 Edg/148.0.0.0', NULL, '2026-05-19 04:21:28'),
(1051, 55, 'LOGIN', 'User berhasil login ke sistem', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/148.0.0.0 Safari/537.36 Edg/148.0.0.0', NULL, '2026-05-19 04:21:53'),
(1052, 55, 'SURAT_FREE_VEHICLE', 'Surat tugas id=33 selesai; kendaraan id=116 set to Tersedia', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/148.0.0.0 Safari/537.36 Edg/148.0.0.0', NULL, '2026-05-19 04:48:27'),
(1053, 55, 'SURAT_FINISH', 'Surat tugas id=33 set to Selesai oleh pengguna 55', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/148.0.0.0 Safari/537.36 Edg/148.0.0.0', NULL, '2026-05-19 04:48:27'),
(1054, 55, 'SURAT_START', 'Surat tugas id=33 set to Dalam Perjalanan oleh pengguna 55', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/148.0.0.0 Safari/537.36 Edg/148.0.0.0', NULL, '2026-05-19 04:48:27'),
(1055, 55, 'SURAT_FREE_VEHICLE', 'Surat tugas id=33 selesai; kendaraan id=116 set to Tersedia', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/148.0.0.0 Safari/537.36 Edg/148.0.0.0', NULL, '2026-05-19 04:48:36'),
(1056, 55, 'SURAT_FINISH', 'Surat tugas id=33 set to Selesai oleh pengguna 55', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/148.0.0.0 Safari/537.36 Edg/148.0.0.0', NULL, '2026-05-19 04:48:36'),
(1057, 55, 'SURAT_START', 'Surat tugas id=33 set to Dalam Perjalanan oleh pengguna 55', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/148.0.0.0 Safari/537.36 Edg/148.0.0.0', NULL, '2026-05-19 04:48:36'),
(1058, 55, 'SURAT_FREE_VEHICLE', 'Surat tugas id=33 selesai; kendaraan id=116 set to Tersedia', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/148.0.0.0 Safari/537.36 Edg/148.0.0.0', NULL, '2026-05-19 05:08:54'),
(1059, 55, 'SURAT_FINISH', 'Surat tugas id=33 set to Selesai oleh pengguna 55', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/148.0.0.0 Safari/537.36 Edg/148.0.0.0', NULL, '2026-05-19 05:08:54'),
(1060, 55, 'SURAT_START', 'Surat tugas id=33 set to Dalam Perjalanan oleh pengguna 55', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/148.0.0.0 Safari/537.36 Edg/148.0.0.0', NULL, '2026-05-19 05:08:54'),
(1061, 55, 'SURAT_FREE_VEHICLE', 'Surat tugas id=33 selesai; kendaraan id=116 set to Tersedia', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/148.0.0.0 Safari/537.36 Edg/148.0.0.0', NULL, '2026-05-19 05:13:06'),
(1062, 55, 'SURAT_FINISH', 'Surat tugas id=33 set to Selesai oleh pengguna 55', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/148.0.0.0 Safari/537.36 Edg/148.0.0.0', NULL, '2026-05-19 05:13:06'),
(1063, 55, 'SURAT_START', 'Surat tugas id=33 set to Dalam Perjalanan oleh pengguna 55', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/148.0.0.0 Safari/537.36 Edg/148.0.0.0', NULL, '2026-05-19 05:13:06'),
(1064, 55, 'SURAT_FREE_VEHICLE', 'Surat tugas id=33 selesai; kendaraan id=116 set to Tersedia', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/148.0.0.0 Safari/537.36 Edg/148.0.0.0', NULL, '2026-05-19 05:13:18'),
(1065, 55, 'SURAT_FINISH', 'Surat tugas id=33 set to Selesai oleh pengguna 55', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/148.0.0.0 Safari/537.36 Edg/148.0.0.0', NULL, '2026-05-19 05:13:18'),
(1066, 55, 'SURAT_START', 'Surat tugas id=33 set to Dalam Perjalanan oleh pengguna 55', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/148.0.0.0 Safari/537.36 Edg/148.0.0.0', NULL, '2026-05-19 05:13:18'),
(1067, 55, 'SURAT_FREE_VEHICLE', 'Surat tugas id=33 selesai; kendaraan id=116 set to Tersedia', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/148.0.0.0 Safari/537.36 Edg/148.0.0.0', NULL, '2026-05-19 05:13:43'),
(1068, 55, 'SURAT_FINISH', 'Surat tugas id=33 set to Selesai oleh pengguna 55', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/148.0.0.0 Safari/537.36 Edg/148.0.0.0', NULL, '2026-05-19 05:13:43'),
(1069, 55, 'SURAT_START', 'Surat tugas id=33 set to Dalam Perjalanan oleh pengguna 55', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/148.0.0.0 Safari/537.36 Edg/148.0.0.0', NULL, '2026-05-19 05:13:43'),
(1070, 1, 'ADD_JADWAL_PERAWATAN', 'Menambah jadwal perawatan: servis berkala untuk kendaraan ID 138', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/148.0.0.0 Safari/537.36 Edg/148.0.0.0', NULL, '2026-05-19 06:01:01'),
(1071, 1, 'EDIT_JADWAL_PERAWATAN', 'Memperbarui jadwal perawatan Kendaraan no_reg: 7696-00', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/148.0.0.0 Safari/537.36 Edg/148.0.0.0', NULL, '2026-05-19 06:01:10'),
(1072, 43, 'LOGOUT', 'User keluar dari sistem', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/147.0.0.0 Safari/537.36', NULL, '2026-05-19 06:26:50'),
(1073, 43, 'LOGIN', 'User berhasil login ke sistem', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/147.0.0.0 Safari/537.36', NULL, '2026-05-19 07:27:45'),
(1074, 43, 'LOGOUT', 'User keluar dari sistem', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/147.0.0.0 Safari/537.36', NULL, '2026-05-19 07:28:16'),
(1075, 1, 'LOGIN', 'User berhasil login ke sistem', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/147.0.0.0 Safari/537.36', NULL, '2026-05-19 08:25:14'),
(1076, 1, 'UPDATE_VEHICLE_STATUS_BY_MAINTENANCE', 'Set kendaraan ID 138 menjadi Perbaikan/Maintenance saat perawatan dimulai', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/147.0.0.0 Safari/537.36', NULL, '2026-05-19 15:52:32'),
(1077, 1, 'UPDATE_MAINTENANCE_STATUS', 'Mengubah status jadwal perawatan servis berkala menjadi Dalam Proses', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/147.0.0.0 Safari/537.36', NULL, '2026-05-19 15:52:32'),
(1078, 1, 'COMPLETE_MAINTENANCE', 'Menyelesaikan jadwal perawatan servis berkala untuk kendaraan 7696-0000', NULL, NULL, NULL, '2026-05-19 15:52:37'),
(1079, 1, 'UPDATE_VEHICLE_STATUS_BY_MAINTENANCE', 'Set kendaraan ID 138 menjadi Operasional/Tersedia setelah perawatan selesai', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/147.0.0.0 Safari/537.36', NULL, '2026-05-19 15:52:37'),
(1080, 1, 'COMPLETE_MAINTENANCE', 'Menandai jadwal perawatan selesai: servis berkala untuk kendaraan 7696-0000', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/147.0.0.0 Safari/537.36', NULL, '2026-05-19 15:52:37'),
(1081, 1, 'ADD_JADWAL_PERAWATAN', 'Menambah jadwal perawatan: as untuk kendaraan ID 138', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/147.0.0.0 Safari/537.36', NULL, '2026-05-19 15:53:02'),
(1082, 1, 'RESET_PASSWORD', 'Admin mereset password user: nemin', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/147.0.0.0 Safari/537.36', NULL, '2026-05-19 16:13:28'),
(1083, 1, 'LOGOUT', 'User keluar dari sistem', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/148.0.0.0 Safari/537.36 Edg/148.0.0.0', NULL, '2026-05-19 16:13:43'),
(1084, 65, 'LOGIN', 'User berhasil login ke sistem', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/148.0.0.0 Safari/537.36 Edg/148.0.0.0', NULL, '2026-05-19 16:13:55'),
(1085, 65, 'LOGOUT', 'User keluar dari sistem', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/148.0.0.0 Safari/537.36 Edg/148.0.0.0', NULL, '2026-05-19 16:14:16'),
(1086, 55, 'LOGIN', 'User berhasil login ke sistem', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/148.0.0.0 Safari/537.36 Edg/148.0.0.0', NULL, '2026-05-19 16:14:32'),
(1087, 55, 'SURAT_FREE_VEHICLE', 'Surat tugas id=33 selesai; kendaraan id=116 set to Tersedia', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/148.0.0.0 Safari/537.36 Edg/148.0.0.0', NULL, '2026-05-19 16:14:42'),
(1088, 55, 'SURAT_FINISH', 'Surat tugas id=33 set to Selesai oleh pengguna 55', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/148.0.0.0 Safari/537.36 Edg/148.0.0.0', NULL, '2026-05-19 16:14:42'),
(1089, 55, 'SURAT_START', 'Surat tugas id=33 set to Dalam Perjalanan oleh pengguna 55', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/148.0.0.0 Safari/537.36 Edg/148.0.0.0', NULL, '2026-05-19 16:14:42'),
(1090, 55, 'SURAT_FREE_VEHICLE', 'Surat tugas id=33 selesai; kendaraan id=116 set to Tersedia', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/148.0.0.0 Safari/537.36 Edg/148.0.0.0', NULL, '2026-05-19 16:16:30'),
(1091, 55, 'SURAT_FINISH', 'Surat tugas id=33 set to Selesai oleh pengguna 55', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/148.0.0.0 Safari/537.36 Edg/148.0.0.0', NULL, '2026-05-19 16:16:30'),
(1092, 55, 'SURAT_START', 'Surat tugas id=33 set to Dalam Perjalanan oleh pengguna 55', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/148.0.0.0 Safari/537.36 Edg/148.0.0.0', NULL, '2026-05-19 16:16:38'),
(1093, 55, 'SURAT_FREE_VEHICLE', 'Surat tugas id=33 selesai; kendaraan id=116 set to Tersedia', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/148.0.0.0 Safari/537.36 Edg/148.0.0.0', NULL, '2026-05-19 16:19:15'),
(1094, 55, 'SURAT_FINISH', 'Surat tugas id=33 set to Selesai oleh pengguna 55', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/148.0.0.0 Safari/537.36 Edg/148.0.0.0', NULL, '2026-05-19 16:19:15'),
(1095, 55, 'LOGOUT', 'User keluar dari sistem', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/148.0.0.0 Safari/537.36 Edg/148.0.0.0', NULL, '2026-05-20 00:23:20'),
(1096, 1, 'LOGIN', 'User berhasil login ke sistem', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/148.0.0.0 Safari/537.36 Edg/148.0.0.0', NULL, '2026-05-20 00:23:27'),
(1097, 55, 'LOGOUT', 'User keluar dari sistem', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/148.0.0.0 Safari/537.36 Edg/148.0.0.0', NULL, '2026-05-20 00:30:08'),
(1098, 83, 'LOGIN', 'User berhasil login ke sistem', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/148.0.0.0 Safari/537.36 Edg/148.0.0.0', NULL, '2026-05-20 00:30:19'),
(1099, 83, 'SURAT_START', 'Surat tugas id=32 set to Dalam Perjalanan oleh pengguna 83', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/148.0.0.0 Safari/537.36 Edg/148.0.0.0', NULL, '2026-05-20 00:30:31'),
(1100, 83, 'SURAT_FREE_VEHICLE', 'Surat tugas id=32 selesai; kendaraan id=77 set to Tersedia', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/148.0.0.0 Safari/537.36 Edg/148.0.0.0', NULL, '2026-05-20 00:36:36'),
(1101, 83, 'SURAT_FINISH', 'Surat tugas id=32 set to Selesai oleh pengguna 83', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/148.0.0.0 Safari/537.36 Edg/148.0.0.0', NULL, '2026-05-20 00:36:36'),
(1102, 1, 'UPDATE_LAPORAN', 'Update laporan id=23 oleh pengguna 1', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/148.0.0.0 Safari/537.36 Edg/148.0.0.0', NULL, '2026-05-20 00:38:02'),
(1103, 1, 'COMPLETE_MAINTENANCE', 'Menyelesaikan jadwal perawatan as untuk kendaraan 7696-0000', NULL, NULL, NULL, '2026-05-20 00:43:18'),
(1104, 1, 'UPDATE_VEHICLE_STATUS_BY_MAINTENANCE', 'Set kendaraan ID 138 menjadi Operasional/Tersedia setelah perawatan selesai', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/148.0.0.0 Safari/537.36 Edg/148.0.0.0', NULL, '2026-05-20 00:43:18'),
(1105, 1, 'COMPLETE_MAINTENANCE', 'Menandai jadwal perawatan selesai: as untuk kendaraan 7696-0000', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/148.0.0.0 Safari/537.36 Edg/148.0.0.0', NULL, '2026-05-20 00:43:18'),
(1106, 1, 'LOGOUT', 'User keluar dari sistem', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/147.0.0.0 Safari/537.36', NULL, '2026-05-20 00:50:01'),
(1107, 43, 'LOGIN', 'User berhasil login ke sistem', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/147.0.0.0 Safari/537.36', NULL, '2026-05-20 00:50:11'),
(1108, 83, 'SURAT_START', 'Surat tugas id=34 set to Dalam Perjalanan oleh pengguna 83', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/148.0.0.0 Safari/537.36 Edg/148.0.0.0', NULL, '2026-05-20 00:56:40'),
(1109, 83, 'SURAT_FREE_VEHICLE', 'Surat tugas id=34 selesai; kendaraan id=77 set to Tersedia', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/148.0.0.0 Safari/537.36 Edg/148.0.0.0', NULL, '2026-05-20 00:56:44'),
(1110, 83, 'SURAT_FINISH', 'Surat tugas id=34 set to Selesai oleh pengguna 83', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/148.0.0.0 Safari/537.36 Edg/148.0.0.0', NULL, '2026-05-20 00:56:44'),
(1111, 1, 'LOGOUT', 'User keluar dari sistem', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/148.0.0.0 Safari/537.36 Edg/148.0.0.0', NULL, '2026-05-20 01:18:38'),
(1112, 80, 'LOGIN', 'User berhasil login ke sistem', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/148.0.0.0 Safari/537.36 Edg/148.0.0.0', NULL, '2026-05-20 01:18:49'),
(1113, 43, 'LOGOUT', 'User keluar dari sistem', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/147.0.0.0 Safari/537.36', NULL, '2026-05-20 06:19:03'),
(1114, 1, 'LOGIN', 'User berhasil login ke sistem', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/147.0.0.0 Safari/537.36', NULL, '2026-05-20 06:19:10'),
(1115, 43, 'LOGIN', 'User berhasil login ke sistem', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/147.0.0.0 Safari/537.36', NULL, '2026-05-20 06:29:33'),
(1116, 1, 'LOGOUT', 'User keluar dari sistem', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/147.0.0.0 Safari/537.36', NULL, '2026-05-20 07:42:23'),
(1117, 55, 'LOGIN', 'User berhasil login ke sistem', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/147.0.0.0 Safari/537.36', NULL, '2026-05-20 07:42:30'),
(1118, 55, 'ADD_BBM_LOG', 'Menambah log BBM untuk kendaraan ID 116: 22 liter', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/147.0.0.0 Safari/537.36', NULL, '2026-05-20 07:43:26'),
(1119, 43, 'LOGOUT', 'User keluar dari sistem', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/148.0.0.0 Safari/537.36', NULL, '2026-05-22 02:11:24'),
(1120, 80, 'LOGIN', 'User berhasil login ke sistem', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/148.0.0.0 Safari/537.36', NULL, '2026-05-22 02:11:33'),
(1121, 80, 'LOGOUT', 'User keluar dari sistem', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/148.0.0.0 Safari/537.36', NULL, '2026-05-22 02:14:31'),
(1122, 43, 'LOGIN', 'User berhasil login ke sistem', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/148.0.0.0 Safari/537.36', NULL, '2026-05-22 02:14:39'),
(1123, 1, 'LOGIN', 'User berhasil login ke sistem', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/148.0.0.0 Safari/537.36', NULL, '2026-05-22 06:34:37'),
(1124, 1, 'LOGOUT', 'User keluar dari sistem', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/148.0.0.0 Safari/537.36', NULL, '2026-05-22 08:17:20'),
(1125, 43, 'LOGIN', 'User berhasil login ke sistem', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/148.0.0.0 Safari/537.36', NULL, '2026-05-22 08:17:27'),
(1126, 43, 'LOGOUT', 'User keluar dari sistem', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/148.0.0.0 Safari/537.36', NULL, '2026-05-22 08:17:55'),
(1127, 80, 'LOGIN', 'User berhasil login ke sistem', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/148.0.0.0 Safari/537.36', NULL, '2026-05-22 08:18:04'),
(1128, 80, 'LOGOUT', 'User keluar dari sistem', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/148.0.0.0 Safari/537.36', NULL, '2026-05-22 08:19:47'),
(1129, 83, 'LOGIN', 'User berhasil login ke sistem', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/148.0.0.0 Safari/537.36', NULL, '2026-05-22 08:19:53'),
(1130, 83, 'LOGOUT', 'User keluar dari sistem', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/148.0.0.0 Safari/537.36', NULL, '2026-05-22 08:20:35'),
(1131, 55, 'LOGIN', 'User berhasil login ke sistem', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/148.0.0.0 Safari/537.36', NULL, '2026-05-22 08:20:41'),
(1132, 55, 'LOGOUT', 'User keluar dari sistem', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/148.0.0.0 Safari/537.36', NULL, '2026-05-22 08:20:55'),
(1133, 1, 'LOGIN', 'User berhasil login ke sistem', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/148.0.0.0 Safari/537.36', NULL, '2026-05-22 08:21:00'),
(1134, 1, 'LOGOUT', 'User keluar dari sistem', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/148.0.0.0 Safari/537.36', NULL, '2026-05-22 08:32:47'),
(1135, 43, 'LOGIN', 'User berhasil login ke sistem', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/148.0.0.0 Safari/537.36', NULL, '2026-05-22 08:32:54'),
(1136, 43, 'LOGIN', 'User berhasil login ke sistem', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/148.0.0.0 Safari/537.36', NULL, '2026-05-22 08:33:09'),
(1137, 1, 'LOGIN', 'User berhasil login ke sistem', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/148.0.0.0 Safari/537.36', NULL, '2026-05-22 08:36:20'),
(1138, 1, 'LOGOUT', 'User keluar dari sistem', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/148.0.0.0 Safari/537.36', NULL, '2026-05-22 08:40:40'),
(1139, 43, 'LOGIN', 'User berhasil login ke sistem', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/148.0.0.0 Safari/537.36', NULL, '2026-05-22 08:40:49'),
(1140, 43, 'LOGIN', 'User berhasil login ke sistem', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/148.0.0.0 Safari/537.36', NULL, '2026-05-22 08:41:17'),
(1141, 43, 'LOGOUT', 'User keluar dari sistem', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/148.0.0.0 Safari/537.36', NULL, '2026-05-22 08:42:47'),
(1142, 1, 'LOGIN', 'User berhasil login ke sistem', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/148.0.0.0 Safari/537.36', NULL, '2026-05-22 08:42:53'),
(1143, 1, 'LOGOUT', 'User keluar dari sistem', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/148.0.0.0 Safari/537.36', NULL, '2026-05-22 08:43:17'),
(1144, 43, 'LOGIN', 'User berhasil login ke sistem', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/148.0.0.0 Safari/537.36', NULL, '2026-05-22 08:43:24'),
(1145, 43, 'EXPORT_USERS', 'Export Manajemen User 39 baris', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/148.0.0.0 Safari/537.36', NULL, '2026-05-25 04:30:57'),
(1146, 43, 'LOGOUT', 'User keluar dari sistem', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/148.0.0.0 Safari/537.36', NULL, '2026-05-25 04:38:25'),
(1147, 1, 'LOGIN', 'User berhasil login ke sistem', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/148.0.0.0 Safari/537.36', NULL, '2026-05-25 04:38:37'),
(1148, 1, 'LOGIN', 'User berhasil login ke sistem', '10.2.28.61', 'Mozilla/5.0 (Linux; Android 10; K) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Mobile Safari/537.36', NULL, '2026-05-27 03:42:47'),
(1149, 1, 'EDIT_USER', 'Admin mengubah user: Admin', '10.2.28.61', 'Mozilla/5.0 (Linux; Android 10; K) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Mobile Safari/537.36', NULL, '2026-05-27 03:46:04'),
(1150, 1, 'EDIT_USER', 'Admin mengubah user: AHMAD FAUZI', '10.2.28.61', 'Mozilla/5.0 (Linux; Android 10; K) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Mobile Safari/537.36', NULL, '2026-05-27 03:46:50'),
(1151, 1, 'EDIT_USER', 'Admin mengubah user: Admin', '10.2.28.61', 'Mozilla/5.0 (Linux; Android 10; K) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Mobile Safari/537.36', NULL, '2026-05-27 03:47:12'),
(1152, 1, 'EDIT_USER', 'Admin mengubah user: Erik', '10.2.28.61', 'Mozilla/5.0 (Linux; Android 10; K) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Mobile Safari/537.36', NULL, '2026-05-27 03:47:50'),
(1153, 1, 'LOGOUT', 'User keluar dari sistem', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/148.0.0.0 Safari/537.36', NULL, '2026-05-27 07:54:34'),
(1154, 1, 'LOGIN', 'User berhasil login ke sistem', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/148.0.0.0 Safari/537.36', NULL, '2026-05-27 08:01:21'),
(1155, 1, 'ADD_JADWAL_PERAWATAN', 'Menambah jadwal perawatan: ac untuk kendaraan ID 134', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/148.0.0.0 Safari/537.36', NULL, '2026-05-27 08:13:59'),
(1156, 1, 'EXPORT_USERS', 'Export Manajemen User 39 baris', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/148.0.0.0 Safari/537.36', NULL, '2026-05-27 08:18:22'),
(1157, 1, 'EXPORT_LAPORAN_PERJALANAN', 'Export Laporan Perjalanan BULAN MEI 2026 (8 baris)', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/148.0.0.0 Safari/537.36', NULL, '2026-05-27 08:18:45'),
(1158, 1, 'LOGOUT', 'User keluar dari sistem', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/148.0.0.0 Safari/537.36', NULL, '2026-05-31 16:08:05'),
(1159, 43, 'LOGIN', 'User berhasil login ke sistem', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/148.0.0.0 Safari/537.36', NULL, '2026-05-31 16:08:15');

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
  `jarak_traccar_km` decimal(8,2) DEFAULT NULL,
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

--
-- Dumping data untuk tabel `log_bahan_bakar`
--

INSERT INTO `log_bahan_bakar` (`id`, `kendaraan_id`, `user_id`, `tanggal_isi`, `jam_isi`, `km_saat_isi`, `jarak_traccar_km`, `jenis_bahan_bakar`, `jumlah_liter`, `harga_per_liter`, `biaya`, `spbu`, `metode_bayar`, `alamat_spbu`, `nomor_struk`, `foto_struk`, `foto_sebelum_isi`, `foto_sesudah_isi`, `foto_odometer`, `keterangan`, `catatan`, `created_at`, `updated_at`) VALUES
(25, 143, 70, '2026-05-14', NULL, 2101, NULL, '', 20, 0, 0, 'spbt kemhan', '', NULL, NULL, NULL, NULL, NULL, NULL, 'pengisian keberangkatan ajp', NULL, '2026-05-13 20:18:59', '2026-05-13 20:18:59'),
(26, 123, 50, '2026-05-16', NULL, 2001, NULL, '', 20, 0, 0, 'Spbt kemhan', '', NULL, NULL, NULL, NULL, NULL, NULL, '', NULL, '2026-05-15 21:57:34', '2026-05-15 21:57:34'),
(27, 116, 55, '2026-05-20', NULL, 2001, NULL, 'Solar', 22, 0, 0, '0', '', NULL, NULL, NULL, NULL, NULL, NULL, 'bogor', NULL, '2026-05-20 07:43:26', '2026-05-20 07:43:26');

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
(3, 4, NULL, 'Pengajuan Peminjaman', 'Pengajuan peminjaman kendaraan B 1234 CC (Toyota SUV) dari MAYOR LUT (T) BUDI SANTOSO untuk keperluan: Kunjungan', 'info', 'vehicle', 'normal', NULL, NULL, NULL, NULL, '2025-08-22 01:40:46'),
(7, 4, NULL, 'Pengajuan Peminjaman', 'Pengajuan peminjaman kendaraan B 1234 CC (Toyota SUV) dari MAYOR LUT (T) BUDI SANTOSO untuk keperluan: Kunjungan', 'info', 'vehicle', 'normal', NULL, NULL, NULL, NULL, '2025-08-22 02:04:39'),
(11, 4, NULL, 'Pengajuan Peminjaman', 'Pengajuan peminjaman kendaraan B 1234 CC (Toyota SUV) dari MAYOR LUT (T) BUDI SANTOSO untuk keperluan: Dinas', 'info', 'vehicle', 'normal', NULL, NULL, NULL, NULL, '2025-08-22 03:46:44'),
(18, 4, NULL, 'Pengajuan Peminjaman', 'Pengajuan peminjaman kendaraan Reg: 8667-08 - TEST123 (Test Brand Test Model) Satker: Pusinfolahta - dari MAYOR LUT (T) BUDI SANTOSO untuk keperluan: dd', 'info', 'vehicle', 'normal', NULL, NULL, NULL, NULL, '2025-08-29 05:00:12'),
(23, 4, NULL, 'Pengajuan Peminjaman', 'Pengajuan peminjaman kendaraan Reg: 8697-08 - B9012GH (Mitsubishi Pajero) Satker: Pusinfolahta - dari MAYOR LUT (T) BUDI SANTOSO untuk keperluan: as', 'info', 'vehicle', 'normal', NULL, NULL, NULL, NULL, '2025-08-29 07:38:37'),
(27, 4, NULL, 'Pengajuan Peminjaman', 'Pengajuan peminjaman kendaraan Reg: 8667-08 - TEST123 (Test Brand Test Model) Satker: Pusinfolahta - dari SERDA RIJAL SURYADI untuk keperluan: as', 'info', 'vehicle', 'normal', NULL, NULL, NULL, NULL, '2025-09-01 00:19:18'),
(31, 4, NULL, 'Pengajuan Peminjaman', 'Pengajuan peminjaman kendaraan Reg: 0200-08 - S 222 CD (Lexus Sedan) Satker:  - dari tni untuk keperluan: nn', 'info', 'vehicle', 'normal', NULL, NULL, NULL, NULL, '2025-09-02 06:08:09'),
(39, 4, NULL, 'Pengajuan Peminjaman', 'Pengajuan peminjaman kendaraan Reg: 0822-02 - F5888EF (Honda Civic) Satker:  - dari SERDA RIJAL SURYADI untuk keperluan: s', 'info', 'vehicle', 'normal', NULL, NULL, NULL, NULL, '2025-09-02 07:05:17'),
(46, 4, NULL, 'Pengajuan Peminjaman', 'Pengajuan peminjaman kendaraan Reg: 8667-08 - TEST123 (Test Brand Test Model) Satker: Pusinfolahta - dari as untuk keperluan: 2', 'info', 'vehicle', 'normal', NULL, NULL, NULL, NULL, '2025-09-03 01:31:47'),
(52, 4, NULL, 'Pengajuan Peminjaman', 'Pengajuan peminjaman kendaraan Reg: 8667-08 - TEST123 (Test Brand Test Model) Satker: Pusinfolahta - dari as untuk keperluan: e', 'info', 'vehicle', 'normal', NULL, NULL, NULL, NULL, '2025-09-04 01:52:04'),
(60, 4, NULL, 'Pengajuan Peminjaman', 'Pengajuan peminjaman kendaraan Reg: 8065-08 - B 1234 AB (Toyota SUV) Satker: Pusinfolahta - dari as untuk keperluan: ss', 'info', 'vehicle', 'normal', NULL, NULL, NULL, NULL, '2025-09-04 08:36:16'),
(68, 4, NULL, 'Pengajuan Peminjaman', 'Pengajuan peminjaman kendaraan Reg: 0200-08 - 0200-08 (Lexus Sedan) Satker: Pusinfolahta - dari BUDI SANTOSO untuk keperluan: Kunjungan', 'info', 'vehicle', 'normal', NULL, NULL, NULL, NULL, '2025-09-24 23:47:02'),
(102, 4, NULL, 'Pengajuan Peminjaman', 'Pengajuan peminjaman kendaraan Reg: 0200-08 - 0200-08 (Lexus Sedan) Satker: Pusinfolahta - dari BUDI SANTOSO untuk keperluan: Kunjungan', 'info', 'vehicle', 'normal', NULL, NULL, NULL, NULL, '2025-09-24 23:47:02'),
(138, 4, NULL, 'Pengajuan Peminjaman', 'Pengajuan peminjaman kendaraan Reg: 0822-02 - 0822-02 (Honda Civic) Satker: Pusinfolahta - dari asn untuk keperluan: adas', 'info', 'vehicle', 'normal', NULL, NULL, NULL, NULL, '2025-09-24 23:53:35'),
(271, 70, NULL, 'Surat Tugas Disetujui', 'Surat tugas ST/001/V/2026 sudah diverifikasi admin dan diteruskan sebagai permohonan peminjaman ke pimpinan.', 'success', 'document', 'normal', NULL, NULL, NULL, NULL, '2026-05-06 18:06:34'),
(272, 70, NULL, 'Peminjaman Disetujui', 'Pengajuan peminjaman kendaraan 7690-00 telah disetujui', 'info', 'system', 'normal', NULL, NULL, NULL, NULL, '2026-05-06 18:23:46'),
(273, 58, NULL, 'Surat Tugas Disetujui', 'Surat tugas ST/002/V/2026 sudah diverifikasi admin dan diteruskan sebagai permohonan peminjaman ke pimpinan.', 'success', 'document', 'normal', NULL, NULL, NULL, NULL, '2026-05-13 20:46:10'),
(274, 58, NULL, 'Peminjaman Disetujui', 'Pengajuan peminjaman kendaraan 7709-00 telah disetujui', 'info', 'system', 'normal', NULL, NULL, NULL, NULL, '2026-05-15 21:25:14'),
(275, 50, NULL, 'Surat Tugas Disetujui', 'Surat tugas ST/003/V/2026 sudah diverifikasi admin dan diteruskan sebagai permohonan peminjaman ke pimpinan.', 'success', 'document', 'normal', NULL, NULL, NULL, NULL, '2026-05-15 21:52:03'),
(276, 50, NULL, 'Peminjaman Disetujui', 'Pengajuan peminjaman kendaraan 7701-00 telah disetujui', 'info', 'system', 'normal', NULL, NULL, NULL, NULL, '2026-05-15 21:52:27'),
(277, 82, NULL, 'Pengajuan Peminjaman', 'Pengajuan peminjaman kendaraan Reg: 7697-00 (Nopol: 7697-00) (Mitsubishi Bus) - Satker: Setjen Kemhan dari user untuk keperluan: ss', 'info', 'vehicle', 'normal', NULL, NULL, NULL, NULL, '2026-05-17 08:38:37'),
(278, 80, NULL, 'Pengajuan Peminjaman', 'Pengajuan peminjaman kendaraan Reg: 7697-00 (Nopol: 7697-00) (Mitsubishi Bus) - Satker: Setjen Kemhan dari user untuk keperluan: ss', 'info', 'vehicle', 'normal', NULL, NULL, NULL, NULL, '2026-05-17 08:38:37'),
(279, 80, NULL, 'Peminjaman Disetujui', 'Pengajuan peminjaman kendaraan 7697-00 telah disetujui', 'info', 'system', 'normal', NULL, NULL, NULL, NULL, '2026-05-17 08:39:13'),
(280, 82, NULL, 'Pengajuan Peminjaman', 'Pengajuan peminjaman kendaraan Reg: 7607-00 (Nopol: 7607-00) (Mercedes benz Big Bus) - Satker: Setjen Kemhan dari user untuk keperluan: as', 'info', 'vehicle', 'normal', NULL, NULL, NULL, NULL, '2026-05-18 15:45:24'),
(281, 80, NULL, 'Pengajuan Peminjaman', 'Pengajuan peminjaman kendaraan Reg: 7607-00 (Nopol: 7607-00) (Mercedes benz Big Bus) - Satker: Setjen Kemhan dari user untuk keperluan: as', 'info', 'vehicle', 'normal', NULL, NULL, NULL, NULL, '2026-05-18 15:45:24'),
(282, 80, NULL, 'Peminjaman Disetujui', 'Pengajuan peminjaman kendaraan 7607-00 telah disetujui', 'info', 'system', 'normal', NULL, NULL, NULL, NULL, '2026-05-18 17:43:56'),
(283, 83, NULL, 'Surat Tugas Disetujui', 'Surat tugas ST/004/V/2026 sudah diverifikasi admin dan diteruskan sebagai permohonan peminjaman ke pimpinan.', 'success', 'document', 'normal', NULL, NULL, NULL, NULL, '2026-05-18 22:59:55'),
(284, 83, NULL, 'Peminjaman Disetujui', 'Pengajuan peminjaman kendaraan 7774-08 telah disetujui', 'info', 'system', 'normal', NULL, NULL, NULL, NULL, '2026-05-18 23:01:03'),
(285, 82, NULL, 'Permohonan Peminjaman', 'Pengajuan peminjaman dari user telah dibuat untuk Surat Tugas: ST/005/V/2026. Mohon persetujuan.', 'info', 'system', 'normal', NULL, NULL, NULL, NULL, '2026-05-19 02:44:01'),
(286, 80, NULL, 'Permohonan Peminjaman', 'Pengajuan peminjaman dari user telah dibuat untuk Surat Tugas: ST/005/V/2026. Mohon persetujuan.', 'info', 'system', 'normal', NULL, NULL, NULL, NULL, '2026-05-19 02:44:01'),
(287, 80, NULL, 'Peminjaman Disetujui', 'Pengajuan peminjaman kendaraan 7799-00 telah disetujui', 'info', 'system', 'normal', NULL, NULL, NULL, NULL, '2026-05-19 02:44:49'),
(288, 83, NULL, 'Surat Tugas Disetujui', 'Surat tugas ST/006/V/2026 sudah diverifikasi admin dan diteruskan sebagai permohonan peminjaman ke pimpinan.', 'success', 'document', 'normal', NULL, NULL, NULL, NULL, '2026-05-20 00:50:31'),
(289, 83, NULL, 'Peminjaman Disetujui', 'Pengajuan peminjaman kendaraan 7774-08 telah disetujui', 'info', 'system', 'normal', NULL, NULL, NULL, NULL, '2026-05-20 00:50:42'),
(290, 82, NULL, 'Permohonan Peminjaman', 'Pengajuan peminjaman dari user telah dibuat untuk Surat Tugas: ST/007/V/2026. Mohon persetujuan.', 'info', 'system', 'normal', NULL, NULL, NULL, NULL, '2026-05-20 01:20:02'),
(291, 80, NULL, 'Permohonan Peminjaman', 'Pengajuan peminjaman dari user telah dibuat untuk Surat Tugas: ST/007/V/2026. Mohon persetujuan.', 'info', 'system', 'normal', NULL, NULL, NULL, NULL, '2026-05-20 01:20:02'),
(292, 80, NULL, 'Peminjaman Disetujui', 'Pengajuan peminjaman kendaraan 7707-00 telah disetujui', 'info', 'system', 'normal', NULL, NULL, NULL, NULL, '2026-05-20 01:21:28'),
(293, 82, NULL, 'Pengajuan Peminjaman', 'Pengajuan peminjaman kendaraan Reg: 7774-08 (Nopol: 7774-08) (Isuzu Bus) - Satker: Unhan dari user untuk keperluan: asa', 'info', 'vehicle', 'normal', NULL, NULL, NULL, NULL, '2026-05-20 06:36:10'),
(294, 80, NULL, 'Pengajuan Peminjaman', 'Pengajuan peminjaman kendaraan Reg: 7774-08 (Nopol: 7774-08) (Isuzu Bus) - Satker: Unhan dari user untuk keperluan: asa', 'info', 'vehicle', 'normal', NULL, NULL, NULL, NULL, '2026-05-20 06:36:10'),
(295, 82, NULL, 'Pengajuan Peminjaman', 'Pengajuan peminjaman kendaraan Reg: 7701-00 (Nopol: 7701-00) (Mitsubishi Bus) - Satker: Setjen Kemhan dari user untuk keperluan: as', 'info', 'vehicle', 'normal', NULL, NULL, NULL, NULL, '2026-05-22 02:14:25'),
(296, 80, NULL, 'Pengajuan Peminjaman', 'Pengajuan peminjaman kendaraan Reg: 7701-00 (Nopol: 7701-00) (Mitsubishi Bus) - Satker: Setjen Kemhan dari user untuk keperluan: as', 'info', 'vehicle', 'normal', NULL, NULL, NULL, NULL, '2026-05-22 02:14:25'),
(297, 80, NULL, 'Peminjaman Disetujui', 'Pengajuan peminjaman kendaraan 7774-08 telah disetujui', 'info', 'system', 'normal', NULL, NULL, NULL, NULL, '2026-05-22 02:19:50'),
(298, 80, NULL, 'Peminjaman Disetujui', 'Pengajuan peminjaman kendaraan 7701-00 telah disetujui', 'info', 'system', 'normal', NULL, NULL, NULL, NULL, '2026-05-22 02:19:56'),
(299, 1, NULL, 'Pengajuan Peminjaman', 'Pengajuan peminjaman kendaraan Reg: 7799-00 (Nopol: 7799-00) (Hino Bus) - Satker: Setjen Kemhan dari user untuk keperluan: sa', 'info', 'vehicle', 'normal', NULL, NULL, NULL, NULL, '2026-05-22 08:19:44'),
(300, 4, NULL, 'Pengajuan Peminjaman', 'Pengajuan peminjaman kendaraan Reg: 7799-00 (Nopol: 7799-00) (Hino Bus) - Satker: Setjen Kemhan dari user untuk keperluan: sa', 'info', 'vehicle', 'normal', NULL, NULL, NULL, NULL, '2026-05-22 08:19:44'),
(301, 80, NULL, 'Peminjaman Disetujui', 'Pengajuan peminjaman kendaraan 7799-00 telah disetujui', 'info', 'system', 'normal', NULL, NULL, NULL, NULL, '2026-05-22 08:21:56'),
(302, 83, NULL, 'Surat Tugas Disetujui', 'Surat tugas ST/008/V/2026 sudah diverifikasi admin dan diteruskan sebagai permohonan peminjaman ke pimpinan.', 'success', 'document', 'normal', NULL, NULL, NULL, NULL, '2026-05-31 16:10:44');

-- --------------------------------------------------------

--
-- Struktur dari tabel `peminjaman_kendaraan`
--

CREATE TABLE `peminjaman_kendaraan` (
  `id` int(11) NOT NULL,
  `kendaraan_id` int(11) NOT NULL,
  `surat_tugas_id` int(11) DEFAULT NULL,
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
  `cancel_reason` text DEFAULT NULL,
  `approval_admin_status` enum('Pending','Approved','Rejected') NOT NULL DEFAULT 'Pending',
  `approval_admin_by` int(11) DEFAULT NULL,
  `approval_admin_at` datetime DEFAULT NULL,
  `approval_pimpinan_status` enum('Pending','Approved','Rejected') NOT NULL DEFAULT 'Pending',
  `approval_pimpinan_by` int(11) DEFAULT NULL,
  `approval_pimpinan_at` datetime DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Transaksi peminjaman kendaraan';

--
-- Dumping data untuk tabel `peminjaman_kendaraan`
--

INSERT INTO `peminjaman_kendaraan` (`id`, `kendaraan_id`, `surat_tugas_id`, `peminjam_id`, `approval_by`, `nomor_surat`, `tanggal_mulai`, `tanggal_selesai`, `tujuan`, `keperluan`, `rute`, `driver_id`, `km_awal`, `km_akhir`, `bbm_awal`, `bbm_akhir`, `status`, `tanggal_approval`, `tanggal_mulai_aktual`, `tanggal_selesai_aktual`, `catatan_approval`, `catatan_pengembalian`, `kondisi_kembali`, `biaya_operasional`, `created_at`, `updated_at`, `cancel_reason`, `approval_admin_status`, `approval_admin_by`, `approval_admin_at`, `approval_pimpinan_status`, `approval_pimpinan_by`, `approval_pimpinan_at`) VALUES
(23, 143, 29, 70, 43, 'ST/001/V/2026', '2026-05-07 00:00:00', '2026-05-07 00:00:00', 'Perumnas 3 Bekasi||Jalan Pulau Bangka 9, Wisma Jaya, Bulak Kapal, Duren Jaya, Bekasi, Jawa Barat, Jawa, 17111, Indonesia', 'Antar jemput pegawai', NULL, NULL, NULL, NULL, NULL, NULL, 'Completed', NULL, NULL, '2026-05-12 12:17:58', '', NULL, 'Baik', NULL, '2026-05-06 18:06:34', '2026-05-12 12:17:58', NULL, 'Pending', NULL, NULL, 'Pending', NULL, NULL),
(24, 131, 30, 58, 1, 'ST/002/V/2026', '2026-05-15 00:00:00', '2026-05-15 00:00:00', 'cikini||RW 07, Tomang, Grogol Petamburan, West Jakarta, Special Capital Region of Jakarta, Java, 11440, Indonesia', 'Antar jemput pegawai', NULL, NULL, NULL, NULL, NULL, NULL, 'Completed', NULL, NULL, '2026-05-15 21:25:16', '', NULL, 'Baik', NULL, '2026-05-13 20:46:10', '2026-05-15 21:25:16', NULL, 'Pending', NULL, NULL, 'Pending', NULL, NULL),
(25, 123, 31, 50, 43, 'ST/003/V/2026', '2026-05-17 00:00:00', '2026-05-17 00:00:00', 'Jalan Kramat Raya, RW 02, Senen, Central Jakarta, Special Capital Region of Jakarta, Java, 10410, Indonesia', 'Antar jemput pegawai', NULL, NULL, NULL, NULL, NULL, NULL, 'Completed', NULL, NULL, '2026-05-18 17:43:59', '', NULL, 'Baik', NULL, '2026-05-15 21:52:03', '2026-05-18 17:43:59', NULL, 'Pending', NULL, NULL, 'Pending', NULL, NULL),
(26, 151, NULL, 80, 1, NULL, '2026-05-18 15:38:00', '2026-05-18 17:38:00', 'bogor', 'ss', NULL, NULL, NULL, NULL, NULL, NULL, 'Completed', NULL, NULL, '2026-05-18 17:43:59', '', NULL, 'Baik', NULL, '2026-05-17 08:38:37', '2026-05-18 17:43:59', NULL, 'Pending', NULL, NULL, 'Pending', NULL, NULL),
(27, 146, NULL, 80, 1, NULL, '2026-05-20 05:50:00', '2026-05-20 07:50:00', 'bandung', 'as', NULL, NULL, NULL, NULL, NULL, NULL, 'Completed', NULL, '2026-05-20 00:40:23', '2026-05-20 01:04:44', '', NULL, 'Baik', NULL, '2026-05-18 15:45:24', '2026-05-20 01:04:44', NULL, 'Pending', NULL, NULL, 'Pending', NULL, NULL),
(28, 77, 32, 83, 43, 'ST/004/V/2026', '2026-05-20 00:00:00', '2026-05-20 00:00:00', 'senen', 'hh', NULL, NULL, NULL, NULL, NULL, NULL, 'Completed', NULL, NULL, '2026-05-20 00:40:23', '', NULL, 'Baik', NULL, '2026-05-18 22:59:55', '2026-05-20 00:40:23', NULL, 'Pending', NULL, NULL, 'Pending', NULL, NULL),
(29, 116, 33, 80, 43, 'ST/005/V/2026', '2026-05-19 00:00:00', '2026-05-19 23:59:59', 'bogor', 'ajp', NULL, NULL, NULL, NULL, NULL, NULL, 'Completed', NULL, NULL, '2026-05-20 00:40:23', '', NULL, 'Baik', NULL, '2026-05-19 02:44:01', '2026-05-20 00:40:23', NULL, 'Pending', NULL, NULL, 'Pending', NULL, NULL),
(30, 77, 34, 83, 43, 'ST/006/V/2026', '2026-05-20 00:00:00', '2026-05-20 00:00:00', 'Bogor, West Java, Java, Indonesia', 'ajp', NULL, NULL, NULL, NULL, NULL, NULL, 'Completed', NULL, NULL, '2026-05-20 01:04:44', '', NULL, 'Baik', NULL, '2026-05-20 00:50:31', '2026-05-20 01:04:44', NULL, 'Pending', NULL, NULL, 'Pending', NULL, NULL),
(31, 129, 35, 80, 43, 'ST/007/V/2026', '2026-05-20 00:00:00', '2026-05-20 23:59:59', 'Bogor, West Java, Java, Indonesia', 'ss', NULL, NULL, NULL, NULL, NULL, NULL, 'Completed', NULL, NULL, '2026-05-22 08:21:58', '', NULL, 'Baik', NULL, '2026-05-20 01:20:02', '2026-05-22 08:21:58', NULL, 'Pending', NULL, NULL, 'Pending', NULL, NULL),
(32, 77, 36, 80, 43, NULL, '2026-05-20 13:36:00', '2026-05-20 15:36:00', 'RW 02, Cilandak Timur, Pasar Minggu, South Jakarta, Special Capital Region of Jakarta, Java, 12560, Indonesia', 'asa', NULL, NULL, NULL, NULL, NULL, NULL, 'Completed', NULL, NULL, '2026-05-22 08:21:58', '', NULL, 'Baik', NULL, '2026-05-20 06:36:10', '2026-05-22 08:21:58', NULL, 'Pending', NULL, NULL, 'Pending', NULL, NULL),
(33, 123, 37, 80, 43, NULL, '2026-05-22 09:14:00', '2026-05-22 11:14:00', 'cibinong', 'as', NULL, NULL, NULL, NULL, NULL, NULL, 'Completed', NULL, NULL, '2026-05-22 08:21:58', '', NULL, 'Baik', NULL, '2026-05-22 02:14:25', '2026-05-22 08:21:58', NULL, 'Pending', NULL, NULL, 'Pending', NULL, NULL),
(34, 116, 38, 80, 1, NULL, '2026-05-22 15:19:00', '2026-05-22 17:19:00', 'Bogor, Jawa Barat, Jawa, Indonesia', 'sa', NULL, NULL, NULL, NULL, NULL, NULL, 'Completed', NULL, '2026-05-22 08:21:58', '2026-05-27 08:05:05', '', NULL, 'Baik', NULL, '2026-05-22 08:19:44', '2026-05-27 08:05:05', NULL, 'Pending', NULL, NULL, 'Pending', NULL, NULL),
(35, 77, 39, 83, NULL, 'ST/008/V/2026', '2026-05-31 00:00:00', '2026-05-31 00:00:00', 'bogor', 'ajp', NULL, NULL, NULL, NULL, NULL, NULL, 'Pending', NULL, NULL, NULL, 'Permohonan dari Surat Tugas #39 menunggu persetujuan pimpinan', NULL, 'Baik', NULL, '2026-05-31 16:10:44', '2026-05-31 16:10:44', NULL, 'Pending', NULL, NULL, 'Pending', NULL, NULL);

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
(1, 70, 143, 'Antar jemput pegawai', 'Perumnas 3 Bekasi||Jalan Pulau Bangka 9, Wisma Jaya, Bulak Kapal, Duren Jaya, Bekasi, Jawa Barat, Jawa, 17111, Indonesia', '2026-05-07 00:00:00', '2026-05-07 23:59:59', NULL, NULL, 'approved', NULL, NULL, '2026-05-07 01:06:03', '2026-05-07 01:06:03'),
(2, 58, 131, 'Antar jemput pegawai', 'cikini||RW 07, Tomang, Grogol Petamburan, West Jakarta, Special Capital Region of Jakarta, Java, 11440, Indonesia', '2026-05-15 00:00:00', '2026-05-15 23:59:59', NULL, NULL, 'approved', NULL, NULL, '2026-05-14 03:45:49', '2026-05-14 03:45:49'),
(3, 58, 131, 'Antar jemput pegawai', 'cikini||RW 07, Tomang, Grogol Petamburan, West Jakarta, Special Capital Region of Jakarta, Java, 11440, Indonesia', '2026-05-15 00:00:00', '2026-05-15 00:00:00', NULL, NULL, 'approved', NULL, NULL, '2026-05-16 04:26:07', '2026-05-16 04:26:07'),
(4, 50, 123, 'Antar jemput pegawai', 'Jalan Kramat Raya, RW 02, Senen, Central Jakarta, Special Capital Region of Jakarta, Java, 10410, Indonesia', '2026-05-17 00:00:00', '2026-05-17 23:59:59', NULL, NULL, 'approved', NULL, NULL, '2026-05-16 04:51:31', '2026-05-16 04:51:31'),
(5, 83, 77, 'hh', 'senen', '2026-05-20 00:00:00', '2026-05-20 23:59:59', NULL, NULL, 'approved', NULL, NULL, '2026-05-19 04:02:48', '2026-05-19 04:02:48'),
(6, 83, 77, 'ajp', 'Bogor, West Java, Java, Indonesia', '2026-05-20 00:00:00', '2026-05-20 23:59:59', NULL, NULL, 'approved', NULL, NULL, '2026-05-20 07:49:32', '2026-05-20 07:49:32'),
(7, 83, 77, 'ajp', 'bogor', '2026-05-31 00:00:00', '2026-05-31 23:59:59', NULL, NULL, 'approved', NULL, NULL, '2026-05-31 23:10:12', '2026-05-31 23:10:12');

-- --------------------------------------------------------

--
-- Struktur dari tabel `pengguna`
--

CREATE TABLE `pengguna` (
  `id` int(11) NOT NULL,
  `nrp_nip` varchar(30) DEFAULT NULL,
  `nama_lengkap` varchar(100) NOT NULL,
  `pangkat` varchar(50) DEFAULT NULL,
  `jabatan` varchar(50) DEFAULT NULL,
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
(1, '31010045870399', 'Admin', 'Brigadir Jenderal', '', NULL, '08123467890', 'admin@tni.mil.id', 'Jakarta', 'Aktif', 'Aktif', 'TNI', 'AD', 'Infantri', 'Setjen Kemhan', '2025-08-13 01:26:52', '2026-05-27 03:46:04'),
(4, '31070388920592', 'AHMAD FAUZI', 'Kapten', 'Kataud', NULL, '081234567893', 'ahmad.fauzi@tni.mil.id', 'Depok', 'Aktif', 'Aktif', 'TNI', 'AD', 'Infantri', 'Setjen Kemhan', '2025-08-13 01:26:52', '2026-05-27 03:46:50'),
(43, '322222', 'pimpinan', 'Kolonel', '', NULL, '08122213112', 'gainalidojaya@gmail.com', 'jl.sadad', 'Aktif', 'Aktif', 'TNI', 'AD', 'INFANTERI', 'Baranahan', '2026-04-23 11:52:37', '2026-04-23 11:52:37'),
(46, '3196000001', 'Edy S', 'Serka', 'Pengemudi', 'Sekretariat Jenderal Kementerian Pertahanan', NULL, 'edy.s@gmail.com', 'Jl. Mayjen Sutoyo No. 12, Cawang, Kramat Jati, Jakarta Timur', 'Aktif', 'Aktif', 'TNI', 'TNI AD', 'ROUM Kemhan', 'Sekretariat Jenderal Kementerian Pertahanan', '2026-04-24 03:12:55', '2026-05-13 10:28:07'),
(47, '', 'Ikhwan', 'Honorer', 'Pengemudi', 'Sekretariat Jenderal Kementerian Pertahanan', '', 'ikhwan@gmail.com', 'Jl. Dewi Sartika No. 44, Cawang, Kramat Jati, Jakarta Timur', 'Aktif', 'Aktif', 'PNS', NULL, NULL, NULL, '2026-04-24 03:12:55', '2026-05-13 10:28:07'),
(48, 'HON-00003', 'Marsudi', 'Honorer', 'Pengemudi', 'Sekretariat Jenderal Kementerian Pertahanan', NULL, 'marsudi@gmail.com', 'Jl. Raya Kalibata No. 71, Rawajati, Pancoran, Jakarta Selatan', 'Aktif', 'Aktif', 'PNS', 'ASN Kemhan', 'ROUM Kemhan', 'Sekretariat Jenderal Kementerian Pertahanan', '2026-04-24 03:12:55', '2026-05-13 10:28:07'),
(49, '1978042005011003', 'Tarman', 'Golongan II/c', 'Pengemudi', 'Sekretariat Jenderal Kementerian Pertahanan', NULL, 'tarman@gmail.com', 'Jl. MT Haryono No. 20, Cawang, Kramat Jati, Jakarta Timur', 'Aktif', 'Aktif', 'PNS', 'ASN Kemhan', 'ROUM Kemhan', 'Sekretariat Jenderal Kementerian Pertahanan', '2026-04-24 03:12:55', '2026-05-13 10:28:07'),
(50, 'Honorer', 'Joko S', 'Honorer', 'Pengemudi', 'Sekretariat Jenderal Kementerian Pertahanan', '', 'jagan99998888@gmail.com', 'Jl. UKI Cawang No. 8, Cawang, Kramat Jati, Jakarta Timur', 'Aktif', 'Aktif', 'PNS', NULL, NULL, NULL, '2026-04-24 03:12:55', '2026-05-15 21:54:12'),
(51, '3199000006', 'Mer Agus Tinus Calvin', 'Kopka', 'Pengemudi', 'Sekretariat Jenderal Kementerian Pertahanan', NULL, 'mer.agus.tinus.calvin@gmail.com', 'Jl. Cililitan Besar No. 15, Cililitan, Kramat Jati, Jakarta Timur', 'Aktif', 'Aktif', 'TNI', 'TNI AD', 'ROUM Kemhan', 'Sekretariat Jenderal Kementerian Pertahanan', '2026-04-24 03:12:55', '2026-05-13 10:28:07'),
(52, '3197000007', 'Purwanto', 'Sertu', 'Pengemudi', 'Sekretariat Jenderal Kementerian Pertahanan', NULL, 'purwanto@gmail.com', 'Jl. Otista Raya No. 98, Bidara Cina, Jatinegara, Jakarta Timur', 'Aktif', 'Aktif', 'TNI', 'TNI AD', 'ROUM Kemhan', 'Sekretariat Jenderal Kementerian Pertahanan', '2026-04-24 03:12:55', '2026-05-13 10:28:07'),
(53, '3197000008', 'Puput Y', 'Sertu', 'Pengemudi', 'Sekretariat Jenderal Kementerian Pertahanan', NULL, 'puput.y@gmail.com', 'Jl. DI Panjaitan No. 33, Cipinang Cempedak, Jatinegara, Jakarta Timur', 'Aktif', 'Aktif', 'TNI', 'TNI AD', 'ROUM Kemhan', 'Sekretariat Jenderal Kementerian Pertahanan', '2026-04-24 03:12:56', '2026-05-13 10:28:07'),
(54, '3198000009', 'Sigit Permana', 'Serma', 'Pengemudi', 'Sekretariat Jenderal Kementerian Pertahanan', NULL, 'sigit.permana@gmail.com', 'Jl. Tebet Barat Dalam No. 14, Tebet, Jakarta Selatan', 'Aktif', 'Aktif', 'TNI', 'TNI AD', 'ROUM Kemhan', 'Sekretariat Jenderal Kementerian Pertahanan', '2026-04-24 03:12:56', '2026-05-13 10:28:07'),
(55, '3199000010', 'Aminudin', 'Kopka', 'Pengemudi', 'Sekretariat Jenderal Kementerian Pertahanan', NULL, 'aminudin@gmail.com', 'Jl. Kebon Nanas Selatan No. 9, Cipinang Cempedak, Jakarta Timur', 'Aktif', 'Aktif', 'TNI', 'TNI AD', 'ROUM Kemhan', 'Sekretariat Jenderal Kementerian Pertahanan', '2026-04-24 03:12:56', '2026-05-13 10:28:07'),
(56, '3200000011', 'Rusdi', 'Peltu', 'Pengemudi', 'Sekretariat Jenderal Kementerian Pertahanan', NULL, 'rusdi@gmail.com', 'Jl. Kampung Melayu Besar No. 22, Kampung Melayu, Jakarta Timur', 'Aktif', 'Aktif', 'TNI', 'TNI AD', 'ROUM Kemhan', 'Sekretariat Jenderal Kementerian Pertahanan', '2026-04-24 03:12:56', '2026-05-13 10:28:07'),
(57, '1978122005011005', 'Suhendra', 'Golongan II/a', 'Pengemudi', 'Sekretariat Jenderal Kementerian Pertahanan', NULL, 'suhendra@gmail.com', 'Jl. PGC Cililitan No. 5, Cililitan, Kramat Jati, Jakarta Timur', 'Aktif', 'Aktif', 'PNS', 'ASN Kemhan', 'ROUM Kemhan', 'Sekretariat Jenderal Kementerian Pertahanan', '2026-04-24 03:12:56', '2026-05-13 10:28:07'),
(58, '1978132005011006', 'Turimin', 'Golongan II/b', 'Pengemudi', 'Sekretariat Jenderal Kementerian Pertahanan', NULL, 'turimin@gmail.com', 'Jl. Raya Condet No. 40, Balekambang, Kramat Jati, Jakarta Timur', 'Aktif', 'Aktif', 'PNS', 'ASN Kemhan', 'ROUM Kemhan', 'Sekretariat Jenderal Kementerian Pertahanan', '2026-04-24 03:12:56', '2026-05-13 10:28:07'),
(59, '3201000014', 'Tukiman', 'Pelda', 'Pengemudi', 'Sekretariat Jenderal Kementerian Pertahanan', NULL, 'tukiman@gmail.com', 'Jl. Haji Darip No. 18, Cawang, Kramat Jati, Jakarta Timur', 'Aktif', 'Aktif', 'TNI', 'TNI AD', 'ROUM Kemhan', 'Sekretariat Jenderal Kementerian Pertahanan', '2026-04-24 03:12:56', '2026-05-13 10:28:07'),
(60, 'PPPK', 'Erik', 'PPPK', 'Pengemudi', 'Sekretariat Jenderal Kementerian Pertahanan', '', 'erik@gmail.com', 'Jl. Batu Ampar III No. 21, Batu Ampar, Kramat Jati, Jakarta Timur', 'Aktif', 'Aktif', 'PNS', NULL, NULL, NULL, '2026-04-24 03:12:56', '2026-05-27 03:47:50'),
(61, '3196000016', 'Sophan', 'Serka', 'Pengemudi', 'Sekretariat Jenderal Kementerian Pertahanan', NULL, 'sophan@gmail.com', 'Jl. Pahlawan Revolusi No. 11, Pondok Bambu, Duren Sawit, Jakarta Timur', 'Aktif', 'Aktif', 'TNI', 'TNI AD', 'ROUM Kemhan', 'Sekretariat Jenderal Kementerian Pertahanan', '2026-04-24 03:12:56', '2026-05-13 10:28:07'),
(62, '1978172005011008', 'Arie Setiawan', 'Golongan II/d', 'Pengemudi', 'Sekretariat Jenderal Kementerian Pertahanan', NULL, 'arie.setiawan@gmail.com', 'Jl. RS Fatmawati Lama No. 3, Cawang Baru, Jakarta Timur', 'Aktif', 'Aktif', 'PNS', 'ASN Kemhan', 'ROUM Kemhan', 'Sekretariat Jenderal Kementerian Pertahanan', '2026-04-24 03:12:56', '2026-05-13 10:28:07'),
(63, 'HON-00018', 'Isak', 'Honorer', 'Pengemudi', 'Sekretariat Jenderal Kementerian Pertahanan', '', 'isak@gmail.com', 'Jl. SMPN 49 No. 26, Cililitan, Jakarta Timur', 'Aktif', 'Aktif', 'PNS', NULL, NULL, NULL, '2026-04-24 03:12:56', '2026-05-13 10:28:07'),
(64, 'HON-00019', 'Kodim', 'Honorer', 'Pengemudi', 'Sekretariat Jenderal Kementerian Pertahanan', NULL, 'kodim@gmail.com', 'Jl. Kramat Asem No. 34, Utan Kayu Selatan, Matraman, Jakarta Timur', 'Aktif', 'Aktif', 'PNS', 'ASN Kemhan', 'ROUM Kemhan', 'Sekretariat Jenderal Kementerian Pertahanan', '2026-04-24 03:12:56', '2026-05-13 10:28:07'),
(65, '1978202005011011', 'Nemin', 'Golongan II/c', 'Pengemudi', 'Sekretariat Jenderal Kementerian Pertahanan', NULL, 'nemin@gmail.com', 'Jl. Pramuka Sari III No. 12, Rawasari, Cempaka Putih, Jakarta Pusat', 'Aktif', 'Aktif', 'PNS', 'ASN Kemhan', 'ROUM Kemhan', 'Sekretariat Jenderal Kementerian Pertahanan', '2026-04-24 03:12:57', '2026-05-13 10:28:07'),
(66, '3197000021', 'M. Budi S', 'Sertu', 'Pengemudi', 'Sekretariat Jenderal Kementerian Pertahanan', NULL, 'm.budi.s@gmail.com', 'Jl. Gelong Baru Timur No. 27, Palmerah, Jakarta Barat', 'Aktif', 'Aktif', 'TNI', 'TNI AD', 'ROUM Kemhan', 'Sekretariat Jenderal Kementerian Pertahanan', '2026-04-24 03:12:57', '2026-05-13 10:28:07'),
(67, 'HON-00022', 'Suradi', 'Honorer', 'Pengemudi', 'Sekretariat Jenderal Kementerian Pertahanan', NULL, 'suradi@gmail.com', 'Jl. Cipinang Muara Raya No. 46, Jatinegara, Jakarta Timur', 'Aktif', 'Aktif', 'PNS', 'ASN Kemhan', 'ROUM Kemhan', 'Sekretariat Jenderal Kementerian Pertahanan', '2026-04-24 03:12:57', '2026-05-13 10:28:07'),
(68, '1978232005011013', 'Jejen', 'Golongan II/a', 'Pengemudi', 'Sekretariat Jenderal Kementerian Pertahanan', NULL, 'jejen@gmail.com', 'Jl. Penas Kalimalang No. 18, Cipinang Melayu, Makasar, Jakarta Timur', 'Aktif', 'Aktif', 'PNS', 'ASN Kemhan', 'ROUM Kemhan', 'Sekretariat Jenderal Kementerian Pertahanan', '2026-04-24 03:12:57', '2026-05-13 10:28:07'),
(69, '1978242005011014', 'Nurfadli', 'Golongan II/b', 'Pengemudi', 'Sekretariat Jenderal Kementerian Pertahanan', NULL, 'nurfadli@gmail.com', 'Jl. Cawang Baru Tengah No. 6, Cawang, Kramat Jati, Jakarta Timur', 'Aktif', 'Aktif', 'PNS', 'ASN Kemhan', 'ROUM Kemhan', 'Sekretariat Jenderal Kementerian Pertahanan', '2026-04-24 03:12:57', '2026-05-13 10:28:07'),
(70, '3199000025', 'Lingga', 'Kopka', 'Pengemudi', NULL, '', 'lingga@gmail.com', 'Jl. Raya Bogor KM 4 No. 10, Cawang, Kramat Jati, Jakarta Timur', 'Aktif', 'Aktif', 'TNI', 'AD', 'Infantri', 'Sekretariat Jenderal Kementerian Pertahanan', '2026-04-24 03:12:57', '2026-05-13 10:28:07'),
(71, '3198000026', 'Mulyanto', 'Serma', 'Pengemudi', 'Sekretariat Jenderal Kementerian Pertahanan', NULL, 'mulyanto@gmail.com', 'Jl. Halim Perdanakusuma No. 13, Makasar, Jakarta Timur', 'Aktif', 'Aktif', 'TNI', 'TNI AD', 'ROUM Kemhan', 'Sekretariat Jenderal Kementerian Pertahanan', '2026-04-24 03:12:57', '2026-05-13 10:28:07'),
(72, '-', 'Asep R', 'Honorer', 'Pengemudi', 'Sekretariat Jenderal Kementerian Pertahanan', '', 'asep.r@gmail.com', 'Jl. Balai Rakyat No. 25, Utan Kayu Utara, Matraman, Jakarta Timur', 'Aktif', 'Aktif', 'PNS', NULL, NULL, NULL, '2026-04-24 03:12:57', '2026-05-13 10:28:07'),
(73, '1978282005011016', 'Parman', 'Golongan II/d', 'Pengemudi', 'Sekretariat Jenderal Kementerian Pertahanan', NULL, 'parman@gmail.com', 'Jl. Jatinegara Barat No. 60, Bali Mester, Jatinegara, Jakarta Timur', 'Aktif', 'Aktif', 'PNS', 'ASN Kemhan', 'ROUM Kemhan', 'Sekretariat Jenderal Kementerian Pertahanan', '2026-04-24 03:12:57', '2026-05-13 10:28:07'),
(74, '1978292005011017', 'Didi Sariman', 'Golongan II/a', 'Pengemudi', 'Sekretariat Jenderal Kementerian Pertahanan', NULL, 'didi.sariman@gmail.com', 'Jl. Ciliwung I No. 7, Cawang, Kramat Jati, Jakarta Timur', 'Aktif', 'Aktif', 'PNS', 'ASN Kemhan', 'ROUM Kemhan', 'Sekretariat Jenderal Kementerian Pertahanan', '2026-04-24 03:12:58', '2026-05-13 10:28:07'),
(75, '3196000030', 'Ujang Sugiana', 'Serka', 'Pengemudi', 'Sekretariat Jenderal Kementerian Pertahanan', NULL, 'ujang.sugiana@gmail.com', 'Jl. Cililitan Kecil No. 19, Cililitan, Kramat Jati, Jakarta Timur', 'Aktif', 'Aktif', 'TNI', 'TNI AD', 'ROUM Kemhan', 'Sekretariat Jenderal Kementerian Pertahanan', '2026-04-24 03:12:58', '2026-05-13 10:28:07'),
(76, '3201000031', 'Budi Utomo', 'Pelda', 'Pengemudi', 'Sekretariat Jenderal Kementerian Pertahanan', NULL, 'budi.utomo@gmail.com', 'Jl. Angkasa Dalam No. 4, Halim Perdanakusuma, Jakarta Timur', 'Aktif', 'Aktif', 'TNI', 'TNI AD', 'ROUM Kemhan', 'Sekretariat Jenderal Kementerian Pertahanan', '2026-04-24 03:12:58', '2026-05-13 10:28:07'),
(77, '3197000032', 'Yudi Martono', 'Sertu', 'Pengemudi', 'Sekretariat Jenderal Kementerian Pertahanan', NULL, 'yudi.martono@gmail.com', 'Jl. Smesco Dalam No. 9, Pancoran, Jakarta Selatan', 'Aktif', 'Aktif', 'TNI', 'TNI AD', 'ROUM Kemhan', 'Sekretariat Jenderal Kementerian Pertahanan', '2026-04-24 03:12:58', '2026-05-13 10:28:07'),
(78, 'HON-00033', 'Mulyadi', 'Honorer', 'Pengemudi', 'Sekretariat Jenderal Kementerian Pertahanan', NULL, 'mulyadi@gmail.com', 'Jl. Percetakan Negara II No. 21, Johar Baru, Jakarta Pusat', 'Aktif', 'Aktif', 'PNS', 'ASN Kemhan', 'ROUM Kemhan', 'Sekretariat Jenderal Kementerian Pertahanan', '2026-04-24 03:12:58', '2026-05-13 10:28:07'),
(79, '1978342005011019', 'Totok', 'Golongan II/c', 'Pengemudi', 'Sekretariat Jenderal Kementerian Pertahanan', NULL, 'totok@gmail.com', 'Jl. Pintu Air Kalimalang No. 3, Cipinang Melayu, Jakarta Timur', 'Aktif', 'Aktif', 'PNS', 'ASN Kemhan', 'ROUM Kemhan', 'Sekretariat Jenderal Kementerian Pertahanan', '2026-04-24 03:12:58', '2026-05-13 10:28:07'),
(80, '32014812', 'user', 'Kapten', '', NULL, '0821443842', 'user@gmail.com', '', 'Aktif', 'Aktif', 'TNI', 'AD', 'infantri', 'Setjen kemhan', '2026-05-06 09:00:54', '2026-05-06 09:00:54'),
(82, '162567', 'operator', 'Golongan II/b', '', 'Setjen Kemhan', '', 'operator@gmail.com', '', 'Aktif', 'Aktif', 'PNS', NULL, NULL, NULL, '2026-05-13 07:50:59', '2026-05-13 07:50:59'),
(83, '3202220401008', 'driver', 'Kolonel', '', NULL, '08215204008', 'iammetalim12@gmail.com', 'UNHAN', 'Aktif', 'Aktif', 'TNI', 'AD', 'cke', 'Setjen kemhan', '2026-05-13 10:46:37', '2026-05-13 10:46:37');

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
(36, 143, 70, NULL, '2026-05-07', NULL, NULL, 0, NULL, '', NULL, NULL, NULL, NULL, 'Baik', 'Baik', NULL, '2026-05-13 07:25:17', '2026-05-13 07:25:17');

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
  `deskripsi_perbaikan` text DEFAULT NULL,
  `keterangan` text DEFAULT NULL,
  `bengkel` varchar(100) DEFAULT NULL,
  `biaya` decimal(15,2) NOT NULL DEFAULT 0.00,
  `teknisi` varchar(100) DEFAULT NULL,
  `status` enum('Dalam Proses','Menunggu Sparepart','Ditunda','Selesai') DEFAULT 'Dalam Proses',
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
(20, 143, '2026-05-13', 'Perbaikan Mesin', 'Patah', NULL, NULL, NULL, 0.00, NULL, 'Selesai', NULL, '', 'ganti', NULL, '2026-05-13 08:30:09', '2026-05-13 08:30:09', 70, NULL),
(21, 143, '2026-05-13', 'Perbaikan Transmisi', 'Patah', NULL, NULL, NULL, 0.00, NULL, 'Selesai', NULL, '', 'Ganti', NULL, '2026-05-13 08:34:53', '2026-05-13 08:34:53', 70, NULL),
(22, 143, '2026-05-13', 'Perbaikan Mesin', 'Patah', NULL, NULL, NULL, 0.00, NULL, 'Selesai', NULL, '', 'ganti', NULL, '2026-05-13 08:40:10', '2026-05-13 08:40:10', 70, NULL),
(23, 123, '2026-05-16', 'Perbaikan Ban', 'Ban botak', NULL, NULL, NULL, 0.00, NULL, 'Selesai', NULL, '', 'Ganti ban', NULL, '2026-05-16 00:26:41', '2026-05-16 00:26:41', 50, NULL),
(24, 77, '2026-05-19', 'Perbaikan Ban', 'Bocor', NULL, NULL, NULL, 0.00, NULL, 'Selesai', NULL, '', 'Ganti', NULL, '2026-05-18 23:15:21', '2026-05-18 23:15:21', 83, NULL);

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
-- Dumping data untuk tabel `riwayat_perbaikan_items`
--

INSERT INTO `riwayat_perbaikan_items` (`id`, `perbaikan_id`, `nama_barang`, `qty`, `satuan`, `harga`, `urutan`, `created_at`, `updated_at`) VALUES
(22, 20, 'Gardang', 1, 'buah', 0, 1, '2026-05-13 08:30:09', '2026-05-13 08:30:09'),
(23, 21, 'Gardang', 1, 'Buah', 0, 1, '2026-05-13 08:34:53', '2026-05-13 08:34:53'),
(24, 22, 'Gardang', 1, 'Buah', 0, 1, '2026-05-13 08:40:10', '2026-05-13 08:40:10'),
(25, 23, 'ban', 2, 'unit', 0, 1, '2026-05-16 00:26:41', '2026-05-16 00:26:41'),
(26, 24, 'ban', 1, 'unit', 0, 1, '2026-05-18 23:15:22', '2026-05-18 23:15:22');

-- --------------------------------------------------------

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
(3, 'User', 'USER', 'Akses terbatas ', 3, '2025-08-20 11:00:08', '2025-09-10 08:26:40'),
(4, 'Driver', 'DRIVER', 'Akses pengelolaan BBM kendaraan yang menjadi tanggung jawab', 3, '2026-04-23 11:47:54', '2026-04-23 11:47:54'),
(5, 'Pimpinan', 'PIMPINAN', 'Monitoring dan notifikasi operasional/perawatan', 2, '2026-04-23 11:47:54', '2026-04-23 11:47:54');

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
  `nama_unit` varchar(150) DEFAULT 'BIRO UMUM SETJEN KEMHAN',
  `nama_bagian` varchar(150) DEFAULT 'BAGIAN PENGAMANAN',
  `jenis_naskah` varchar(100) DEFAULT 'NOTA DINAS',
  `surat_dari` varchar(150) DEFAULT 'Kabag Pam Roum Setjen Kemhan',
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
  `approval_pimpinan_status` enum('Pending','Approved','Rejected') NOT NULL DEFAULT 'Pending',
  `approval_pimpinan_by` int(11) DEFAULT NULL,
  `approval_pimpinan_at` datetime DEFAULT NULL,
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

INSERT INTO `surat_tugas` (`id`, `nomor_surat`, `tanggal_surat`, `klasifikasi`, `lampiran`, `perihal`, `nama_unit`, `nama_bagian`, `jenis_naskah`, `surat_dari`, `kepada_jabatan`, `kepada_tempat`, `dasar_a`, `dasar_b`, `berangkat_dari`, `waktu_berangkat`, `pejabat_ttd_jabatan`, `pejabat_ttd_sebagai`, `tembusan_1`, `tembusan_2`, `tembusan_3`, `tembusan_4`, `kendaraan_id`, `pengguna_id`, `tujuan`, `keperluan`, `tanggal_berangkat`, `tanggal_kembali`, `estimasi_km`, `estimasi_bbm`, `status`, `approval_pimpinan_status`, `approval_pimpinan_by`, `approval_pimpinan_at`, `km_berangkat`, `km_kembali`, `bbm_terpakai`, `laporan_perjalanan`, `pejabat_ttd`, `created_at`, `updated_at`, `created_by`, `updated_by`, `dasar_list`, `tembusan_list`) VALUES
(29, 'ST/001/V/2026', '2026-05-07', 'Biasa', '-', 'Permohonan peminjaman kendaraan dinas bus dan tenaga medis', 'BIRO UMUM SETJEN KEMHAN', 'BAGIAN PENGAMANAN', 'NOTA DINAS', 'Kabag Pam Roum Setjen Kemhan', 'Dandenma Mabes TNI', 'Jakarta', 'Peraturan Sekretaris Jenderal Kemhan Nomor 4 Tahun 2026 tentang Pengamanan di Lingkungan Kemhan.', '', 'SPBT Kemhan Cawang', 'Pukul 05.00 WIB s.d selesai', 'a.n Kepala SPBT Kemhan Cawang', 'Waka,', 'Kepala SPBT Kemhan Cawang', 'Asops Denma Mabes TNI', 'Dansetang Denma Mabes TNI', 'Dansakdok Denma Mabes TNI', 143, 70, 'Perumnas 3 Bekasi||Jalan Pulau Bangka 9, Wisma Jaya, Bulak Kapal, Duren Jaya, Bekasi, Jawa Barat, Jawa, 17111, Indonesia', 'Antar jemput pegawai', '2026-05-07', '2026-05-07', NULL, NULL, 'Selesai', 'Approved', 43, '2026-05-07 01:23:46', NULL, NULL, NULL, '', 'S. Ginting, S.Kom., MMSI., M.Tr.Hankam', '2026-05-06 18:06:03', '2026-05-13 20:44:12', 1, 1, NULL, ''),
(30, 'ST/002/V/2026', '2026-05-14', 'Biasa', '-', 'Permohonan peminjaman kendaraan dinas bus dan tenaga medis', 'BIRO UMUM SETJEN KEMHAN', 'BAGIAN PENGAMANAN', 'NOTA DINAS', 'Kabag Pam Roum Setjen Kemhan', 'Dandenma Mabes TNI', 'Jakarta', 'Peraturan Sekretaris Jenderal Kemhan Nomor 4 Tahun 2026 tentang Pengamanan di Lingkungan Kemhan.', '', 'SPBT Kemhan Cawang', 'Pukul 05.00 WIB s.d selesai', 'a.n Kepala SPBT Kemhan Cawang', 'Waka,', 'Kepala SPBT Kemhan Cawang', 'Asops Denma Mabes TNI', 'Dansetang Denma Mabes TNI', 'Dansakdok Denma Mabes TNI', 131, 58, 'cikini||RW 07, Tomang, Grogol Petamburan, West Jakarta, Special Capital Region of Jakarta, Java, 11440, Indonesia', 'Antar jemput pegawai', '2026-05-15', '2026-05-15', NULL, NULL, 'Selesai', 'Approved', 1, '2026-05-16 04:25:14', NULL, NULL, NULL, '', 'S. Ginting, S.Kom., MMSI., M.Tr.Hankam', '2026-05-13 20:45:48', '2026-05-15 21:50:00', 1, 43, NULL, ''),
(31, 'ST/003/V/2026', '2026-05-16', 'Biasa', '-', 'Permohonan peminjaman kendaraan dinas bus dan tenaga medis', 'BIRO UMUM SETJEN KEMHAN', 'BAGIAN PENGAMANAN', 'NOTA DINAS', 'Kabag Pam Roum Setjen Kemhan', 'Dandenma Mabes TNI', 'Jakarta', 'Peraturan Sekretaris Jenderal Kemhan Nomor 4 Tahun 2026 tentang Pengamanan di Lingkungan Kemhan.', '', 'SPBT Kemhan Cawang', 'Pukul 05.00 WIB s.d selesai', 'a.n Kepala SPBT Kemhan Cawang', 'Waka,', 'Kepala SPBT Kemhan Cawang', 'Asops Denma Mabes TNI', 'Dansetang Denma Mabes TNI', 'Dansakdok Denma Mabes TNI', 123, 50, 'Jalan Kramat Raya, RW 02, Senen, Central Jakarta, Special Capital Region of Jakarta, Java, 10410, Indonesia', 'Antar jemput pegawai', '2026-05-17', '2026-05-17', 15, 6.00, 'Selesai', 'Approved', 43, '2026-05-16 04:52:27', NULL, NULL, NULL, '', 'S. Ginting, S.Kom., MMSI., M.Tr.Hankam', '2026-05-15 21:51:31', '2026-05-19 01:59:40', 43, 1, NULL, ''),
(32, 'ST/004/V/2026', '2026-05-19', 'Biasa', '-', 'Permohonan peminjaman kendaraan dinas bus dan tenaga medis', 'BIRO UMUM SETJEN KEMHAN', 'BAGIAN PENGAMANAN', 'NOTA DINAS', 'Kabag Pam Roum Setjen Kemhan', 'Dandenma Mabes TNI', 'Jakarta', 'Peraturan Sekretaris Jenderal Kemhan Nomor 4 Tahun 2026 tentang Pengamanan di Lingkungan Kemhan.', '', 'SPBT Kemhan Cawang', 'Pukul 05.00 WIB s.d selesai', 'a.n Kepala SPBT Kemhan Cawang', 'Waka,', 'Kepala SPBT Kemhan Cawang', 'Asops Denma Mabes TNI', 'Dansetang Denma Mabes TNI', 'Dansakdok Denma Mabes TNI', 77, 83, 'senen', 'hh', '2026-05-20', '2026-05-20', 19, 7.00, 'Selesai', 'Approved', 43, '2026-05-19 06:01:03', NULL, NULL, NULL, '', 'S. Ginting, S.Kom., MMSI., M.Tr.Hankam', '2026-05-18 21:02:48', '2026-05-20 00:36:36', 1, 43, NULL, ''),
(33, 'ST/005/V/2026', '2026-05-19', 'Biasa', '-', 'Permohonan peminjaman kendaraan dinas bus dan tenaga medis', 'BIRO UMUM SETJEN KEMHAN', 'BAGIAN PENGAMANAN', 'NOTA DINAS', 'Kabag Pam Roum Setjen Kemhan', 'Dandenma Mabes TNI', 'Jakarta', 'Peraturan Sekretaris Jenderal Kemhan Nomor 4 Tahun 2026 tentang Pengamanan di Lingkungan Kemhan.', '', 'SPBT Kemhan Cawang', 'Pukul 05.00 WIB s.d selesai', 'a.n Kepala SPBT Kemhan Cawang', 'Waka,', 'Kepala SPBT Kemhan Cawang', 'Asops Denma Mabes TNI', 'Dansetang Denma Mabes TNI', 'Dansakdok Denma Mabes TNI', 116, 80, 'bogor', 'ajp', '2026-05-19', '2026-05-19', 112, 25.00, 'Selesai', 'Approved', 43, '2026-05-19 09:44:49', NULL, NULL, NULL, NULL, 'S. Ginting, S.Kom., MMSI., M.Tr.Hankam', '2026-05-19 02:44:01', '2026-05-19 16:19:15', 80, 43, NULL, ''),
(34, 'ST/006/V/2026', '2026-05-20', 'Biasa', '-', 'Permohonan peminjaman kendaraan dinas bus dan tenaga medis', 'BIRO UMUM SETJEN KEMHAN', 'BAGIAN PENGAMANAN', 'NOTA DINAS', 'Kabag Pam Roum Setjen Kemhan', 'Dandenma Mabes TNI', 'Jakarta', 'Peraturan Sekretaris Jenderal Kemhan Nomor 4 Tahun 2026 tentang Pengamanan di Lingkungan Kemhan.', '', 'SPBT Kemhan Cawang', 'Pukul 05.00 WIB s.d selesai', 'a.n Kepala SPBT Kemhan Cawang', 'Waka,', 'Kepala SPBT Kemhan Cawang', 'Asops Denma Mabes TNI', 'Dansetang Denma Mabes TNI', 'Dansakdok Denma Mabes TNI', 77, 83, 'Bogor, West Java, Java, Indonesia', 'ajp', '2026-05-20', '2026-05-20', 112, 30.00, 'Selesai', 'Approved', 43, '2026-05-20 07:50:42', NULL, NULL, NULL, '', 'S. Ginting, S.Kom., MMSI., M.Tr.Hankam', '2026-05-20 00:49:32', '2026-05-20 00:56:44', 1, 43, NULL, ''),
(35, 'ST/007/V/2026', '2026-05-20', 'Biasa', '-', 'Permohonan peminjaman kendaraan dinas bus dan tenaga medis', 'BIRO UMUM SETJEN KEMHAN', 'BAGIAN PENGAMANAN', 'NOTA DINAS', 'Kabag Pam Roum Setjen Kemhan', 'Dandenma Mabes TNI', 'Jakarta', 'Peraturan Sekretaris Jenderal Kemhan Nomor 4 Tahun 2026 tentang Pengamanan di Lingkungan Kemhan.', '', 'SPBT Kemhan Cawang', 'Pukul 05.00 WIB s.d selesai', 'a.n Kepala SPBT Kemhan Cawang', 'Waka,', 'Kepala SPBT Kemhan Cawang', 'Asops Denma Mabes TNI', 'Dansetang Denma Mabes TNI', 'Dansakdok Denma Mabes TNI', 129, 80, 'Bogor, West Java, Java, Indonesia', 'ss', '2026-05-20', '2026-05-20', 112, 25.00, 'Selesai', 'Approved', 43, '2026-05-20 08:21:28', NULL, NULL, NULL, '', 'S. Ginting, S.Kom., MMSI., M.Tr.Hankam', '2026-05-20 01:20:02', '2026-05-22 08:44:17', 80, 43, NULL, ''),
(36, 'AUTO-1779416390', '2026-05-22', 'Biasa', '-', 'Permohonan peminjaman kendaraan dinas bus dan tenaga medis', 'BIRO UMUM SETJEN KEMHAN', 'BAGIAN PENGAMANAN', 'NOTA DINAS', 'Kabag Pam Roum Setjen Kemhan', 'Dandenma Mabes TNI', 'Jakarta', 'Peraturan Panglima TNI Nomor 24 Tahun 2014 tentang Pengesahan Validasi Organisasi FKT dan', 'Surat Perintah Kapusinfolahta TNI Nomor Sprin/97/XI/2023 tanggal 20 Oktober 2023 tentang Fungsi Pengadaan HUT ke-17 Pusinfolahta TNI dan', 'SPBT Kemhan Cawang', 'Pukul 05.00 WIB s.d selesai', 'a.n Kepala Pusinfolahta TNI', 'Waka,', 'Kapusinfolahta TNI', 'Asops Denma Mabes TNI', 'Dansetang Denma Mabes TNI', 'Dansakdok Denma Mabes TNI', 77, 80, 'RW 02, Cilandak Timur, Pasar Minggu, South Jakarta, Special Capital Region of Jakarta, Java, 12560, Indonesia', 'asa', '2026-05-20', '2026-05-20', 33, 11.00, 'Selesai', 'Pending', NULL, NULL, NULL, NULL, NULL, '', 'S. Ginting, S.Kom., MMSI., M.Tr.Hankam', '2026-05-22 02:19:50', '2026-05-31 16:09:21', 43, 43, NULL, ''),
(37, 'AUTO-1779416396', '2026-05-22', 'Biasa', '-', 'Permohonan peminjaman kendaraan dinas bus dan tenaga medis', 'BIRO UMUM SETJEN KEMHAN', 'BAGIAN PENGAMANAN', 'NOTA DINAS', 'Kabag Pam Roum Setjen Kemhan', 'Dandenma Mabes TNI', 'Jakarta', 'Peraturan Panglima TNI Nomor 24 Tahun 2014 tentang Pengesahan Validasi Organisasi FKT dan', 'Surat Perintah Kapusinfolahta TNI Nomor Sprin/97/XI/2023 tanggal 20 Oktober 2023 tentang Fungsi Pengadaan HUT ke-17 Pusinfolahta TNI dan', 'SPBT Kemhan Cawang', 'Pukul 05.00 WIB s.d selesai', 'a.n Kepala Pusinfolahta TNI', 'Waka,', 'Kapusinfolahta TNI', 'Asops Denma Mabes TNI', 'Dansetang Denma Mabes TNI', 'Dansakdok Denma Mabes TNI', 123, 80, 'cibinong', 'as', '2026-05-22', '2026-05-22', 87, 24.00, 'Selesai', 'Pending', NULL, NULL, NULL, NULL, NULL, '', 'S. Ginting, S.Kom., MMSI., M.Tr.Hankam', '2026-05-22 02:19:56', '2026-05-27 08:17:21', 43, 1, NULL, ''),
(38, 'AUTO-1779438116', '2026-05-22', 'Biasa', '-', 'Permohonan peminjaman kendaraan dinas bus dan tenaga medis', 'BIRO UMUM SETJEN KEMHAN', 'BAGIAN PENGAMANAN', 'NOTA DINAS', 'Kabag Pam Roum Setjen Kemhan', 'Dandenma Mabes TNI', 'Jakarta', 'Peraturan Panglima TNI Nomor 24 Tahun 2014 tentang Pengesahan Validasi Organisasi FKT dan', 'Surat Perintah Kapusinfolahta TNI Nomor Sprin/97/XI/2023 tanggal 20 Oktober 2023 tentang Fungsi Pengadaan HUT ke-17 Pusinfolahta TNI dan', 'SPBT Kemhan Cawang', 'Pukul 05.00 WIB s.d selesai', 'a.n Kepala Pusinfolahta TNI', 'Waka,', 'Kapusinfolahta TNI', 'Asops Denma Mabes TNI', 'Dansetang Denma Mabes TNI', 'Dansakdok Denma Mabes TNI', 116, 80, 'Bogor, Jawa Barat, Jawa, Indonesia', 'sa', '2026-05-22', '2026-05-22', 112, 25.00, 'Selesai', 'Pending', NULL, NULL, NULL, NULL, NULL, '', 'S. Ginting, S.Kom., MMSI., M.Tr.Hankam', '2026-05-22 08:21:56', '2026-05-27 08:17:37', 1, 1, NULL, ''),
(39, 'ST/008/V/2026', '2026-05-31', 'Biasa', '-', 'Permohonan peminjaman kendaraan dinas bus dan tenaga medis', 'BIRO UMUM SETJEN KEMHAN', 'BAGIAN PENGAMANAN', 'NOTA DINAS', 'Kabag Pam Roum Setjen Kemhan', 'Dandenma Mabes TNI', 'Jakarta', 'Peraturan Sekretaris Jenderal Kemhan Nomor 4 Tahun 2026 tentang Pengamanan di Lingkungan Kemhan.', '', 'SPBT Kemhan Cawang', 'Pukul 05.00 WIB s.d selesai', 'a.n Kepala SPBT Kemhan Cawang', 'Waka,', 'Kepala SPBT Kemhan Cawang', 'Asops Denma Mabes TNI', 'Dansetang Denma Mabes TNI', 'Dansakdok Denma Mabes TNI', 77, 83, 'bogor', 'ajp', '2026-05-31', '2026-05-31', 112, 30.00, 'Disetujui', 'Pending', NULL, NULL, NULL, NULL, NULL, '', 'S. Ginting, S.Kom., MMSI., M.Tr.Hankam', '2026-05-31 16:10:12', '2026-05-31 16:10:44', 43, 43, NULL, '');

-- --------------------------------------------------------

--
-- Struktur dari tabel `traccar_positions_last`
--

CREATE TABLE `traccar_positions_last` (
  `id` int(11) NOT NULL,
  `device_id` bigint(20) DEFAULT NULL,
  `device_uid` varchar(255) DEFAULT NULL,
  `device_name` varchar(255) DEFAULT NULL,
  `latitude` double DEFAULT NULL,
  `longitude` double DEFAULT NULL,
  `speed` double DEFAULT NULL,
  `course` double DEFAULT NULL,
  `accuracy` double DEFAULT NULL,
  `device_time` datetime DEFAULT NULL,
  `server_time` datetime DEFAULT current_timestamp(),
  `extra` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`extra`)),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data untuk tabel `traccar_positions_last`
--

INSERT INTO `traccar_positions_last` (`id`, `device_id`, `device_uid`, `device_name`, `latitude`, `longitude`, `speed`, `course`, `accuracy`, `device_time`, `server_time`, `extra`, `updated_at`) VALUES
(719, 1, '889900', 'Skripsi', -6.5225884, 106.8779789, 0, 0, 100, '2026-05-20 09:11:45', '2026-04-13 22:11:44', '{\"id\":11236,\"attributes\":{\"motion\":false,\"odometer\":107434,\"activity\":\"still\",\"batteryLevel\":92,\"distance\":0,\"totalDistance\":129229.69600823623},\"deviceId\":1,\"protocol\":\"osmand\",\"serverTime\":\"2026-05-20T07:11:53.750+00:00\",\"deviceTime\":\"2026-05-20T07:11:45.848+00:00\",\"fixTime\":\"2026-05-20T07:11:45.848+00:00\",\"valid\":true,\"latitude\":-6.5225884,\"longitude\":106.8779789,\"altitude\":237.3,\"speed\":0,\"course\":0,\"address\":null,\"accuracy\":100,\"network\":null,\"geofenceIds\":null,\"uniqueId\":\"889900\",\"deviceName\":\"Skripsi\"}', '2026-05-27 08:03:43'),
(938, 3, '888888', '001', -6.5225986, 106.8779881, 0, 0, 19.61, '2026-05-20 08:28:23', '2026-04-24 09:34:31', '{\"id\":11193,\"attributes\":{\"motion\":false,\"odometer\":7895,\"activity\":\"still\",\"batteryLevel\":37,\"distance\":2.299189867209388,\"totalDistance\":36439.55381755764},\"deviceId\":3,\"protocol\":\"osmand\",\"serverTime\":\"2026-05-20T06:28:24.443+00:00\",\"deviceTime\":\"2026-05-20T06:28:23.736+00:00\",\"fixTime\":\"2026-05-20T06:28:23.736+00:00\",\"valid\":true,\"latitude\":-6.5225986,\"longitude\":106.8779881,\"altitude\":239.3,\"speed\":0,\"course\":0,\"address\":null,\"accuracy\":19.61,\"network\":null,\"geofenceIds\":null,\"uniqueId\":\"888888\",\"deviceName\":\"001\"}', '2026-05-27 08:03:43');

-- --------------------------------------------------------

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
(4, 4, 'teknisi', '$2y$10$fUKpldu9OqdgKbne6tkoeOi3.SJS9NZ0Ee8edekGEI4leiZN7sQ9e', 1, NULL, 'Aktif', '2025-08-13 01:28:52', '2025-09-30 13:15:49'),
(37, 43, 'pimpinan', '$2y$10$ZHzDMXoRtjp7nZUmB3hOMuKr.2vnucKWLZVGNGnbbfT5S5h81B8/i', 5, NULL, 'Aktif', '2026-04-23 11:52:37', '2026-05-06 08:11:59'),
(40, 46, 'edy.s', '$2y$10$i9Qxo7CHltwweenzijboL.kDsXUN2IvEqPWM0QhIY5bEc/8bKvG0C', 4, NULL, 'Aktif', '2026-04-24 03:12:55', '2026-04-24 03:17:52'),
(41, 47, 'ikhwan', '$2y$10$Wu/0ogXBl0Sy87loFCRkiOKUs/qZFVaZj6kaigl/gNxhBZayJyDGm', 4, NULL, 'Aktif', '2026-04-24 03:12:55', '2026-04-24 03:17:52'),
(42, 48, 'marsudi', '$2y$10$FCkiOzWo.0z1RPt1N6wl0eA/vYzfnz2wQ2.Nxp7TSqOt6WqQQtopa', 4, NULL, 'Aktif', '2026-04-24 03:12:55', '2026-04-24 03:17:52'),
(43, 49, 'tarman', '$2y$10$mdX9YUo7r9dj41Q9.K.xROrCzINIn0gYwXS6MUtliYkycyC.5aBk2', 4, NULL, 'Aktif', '2026-04-24 03:12:55', '2026-04-24 03:17:52'),
(44, 50, 'joko.s', '$2y$10$9XtHiUdwaNYS7ulWF0aYHO3JM9CHwy1yNnoc/Fwm71Blbwbtuz6C.', 4, NULL, 'Aktif', '2026-04-24 03:12:55', '2026-05-15 21:55:23'),
(45, 51, 'mer.agus.tinus.calvin', '$2y$10$KzqqzvD5QKcp.Wgu4tMFROOeqdAKF5z1u1a3rLmSGmVut7Ylmzo36', 4, NULL, 'Aktif', '2026-04-24 03:12:55', '2026-04-24 03:17:52'),
(46, 52, 'purwanto', '$2y$10$XEEa.vUi/Mv0MQogmG4EvedJ1358Ngnn3H9nmbr2Res8KfuzZ8jL2', 4, NULL, 'Aktif', '2026-04-24 03:12:56', '2026-04-24 03:17:52'),
(47, 53, 'puput.y', '$2y$10$lYOq8yDM3fz/MR.fMkeW3uw/8oXIrxABZZOgF0ZMEGyM9FOlj5.Se', 4, NULL, 'Aktif', '2026-04-24 03:12:56', '2026-04-24 03:17:52'),
(48, 54, 'sigit.permana', '$2y$10$Yfj/PY4Nk7iSI2KHVXMuz.A3WQs7.1WahmVMJPRc5RUaPTpGPOEfG', 4, NULL, 'Aktif', '2026-04-24 03:12:56', '2026-04-24 03:17:52'),
(49, 55, 'aminudin', '$2y$10$XvM/Q9oqqs7/8CFaVDGN8O5K6hL5DAnBE9xJEUx3vgiblNFQZnLl6', 4, NULL, 'Aktif', '2026-04-24 03:12:56', '2026-05-06 08:09:04'),
(50, 56, 'rusdi', '$2y$10$eCY9Fa/QuAxz3amvp5fKV.Frzt6eyiOKUNYaNDTdRx4LNQmMlshly', 4, NULL, 'Aktif', '2026-04-24 03:12:56', '2026-05-15 22:33:28'),
(51, 57, 'suhendra', '$2y$10$yX/0Yad3s3qXYbxJG4qOQOA0QOuVOpDH3B9olMRL184mx4sWJXoC6', 4, NULL, 'Aktif', '2026-04-24 03:12:56', '2026-04-24 03:17:52'),
(52, 58, 'turimin', '$2y$10$h20JMU.w0pi6ft.qPDVrK.pFfXE7ctvF3u01vPUfR/lssHuZe4xt6', 4, NULL, 'Aktif', '2026-04-24 03:12:56', '2026-04-24 03:17:52'),
(53, 59, 'tukiman', '$2y$10$OMnuHYZoL7jkev69ieRE4OCYifl.bFMKghFS.LI3iN9OrzAmStVyi', 4, NULL, 'Aktif', '2026-04-24 03:12:56', '2026-04-24 03:17:52'),
(54, 60, 'erik', '$2y$10$.TzcaGmG.xJ7NJYSm1253u.Fu3PcOm6LH9liM.9vqQXgOts8HhyAK', 4, NULL, 'Aktif', '2026-04-24 03:12:56', '2026-04-24 03:17:52'),
(55, 61, 'sophan', '$2y$10$ebvIW2347.zotwUwyrBJoOAxODC.zrNw7IbXHnpgrkp81SxUegxb.', 4, NULL, 'Aktif', '2026-04-24 03:12:56', '2026-04-24 03:17:52'),
(56, 62, 'arie.setiawan', '$2y$10$EJHNnVbWMuHwbK8yiDAqMef0hmDmszbrn9f9pnQz1FlMnQtzLTEbG', 4, NULL, 'Aktif', '2026-04-24 03:12:56', '2026-04-24 03:17:52'),
(57, 63, 'isak', '$2y$10$VkhhT63iyNENJW8wurppg.4hE/GtcQyEYasC1XBhMON5vjrvMBmoO', 4, NULL, 'Aktif', '2026-04-24 03:12:56', '2026-04-24 03:17:52'),
(58, 64, 'kodim', '$2y$10$KpSkrTDGq3Q2eXTPAf77gOWhqn/4DoRffSaEdIJh6RbIAbWphRPFy', 4, NULL, 'Aktif', '2026-04-24 03:12:57', '2026-04-24 03:17:52'),
(59, 65, 'nemin', '$2y$10$umtwrGK8PDpvgfzhu33aPumrOx.eQQB2RhGc99Y9tYANVwzrVbMGe', 4, NULL, 'Aktif', '2026-04-24 03:12:57', '2026-05-19 16:13:28'),
(60, 66, 'm.budi.s', '$2y$10$qPsID1njNREvxZcgEn6hreBLg6vybN2j.EkICHGisv.7jMPDj0SpW', 4, NULL, 'Aktif', '2026-04-24 03:12:57', '2026-04-24 03:18:10'),
(61, 67, 'suradi', '$2y$10$wI3BTKRr1G6aSgMUED6oEOD6BgEhRwj/GLnAs2/jEI6.ytIho2Sa2', 4, NULL, 'Aktif', '2026-04-24 03:12:57', '2026-04-24 03:17:52'),
(62, 68, 'jejen', '$2y$10$1dGeIALoxVoJ4cYGK8wK2.ANMVO.O1TX22i4z2f2//5o7I.4f67zG', 4, NULL, 'Aktif', '2026-04-24 03:12:57', '2026-04-24 03:17:52'),
(63, 69, 'nurfadli', '$2y$10$X5kb22WEqo42HnWYmUkp1eid9.H8t7OxXkGN8MqRJLtCEfbWe3nka', 4, NULL, 'Aktif', '2026-04-24 03:12:57', '2026-04-24 03:17:52'),
(64, 70, 'lingga', '$2y$10$REZb6UYubHRCQRe1PV28COc2Rk3YKsb9nKy8FyUCNuQ/r7VagCE7i', 4, NULL, 'Aktif', '2026-04-24 03:12:57', '2026-05-06 18:25:44'),
(65, 71, 'mulyanto', '$2y$10$bnlt6HhqIi.MOXPh3ADLsOOcaP9TcA9cOWYvere3vqotvRGjDRPSC', 4, NULL, 'Aktif', '2026-04-24 03:12:57', '2026-04-24 03:17:52'),
(66, 72, 'asep.r', '$2y$10$1FYs7Bx6ZALTDoVPS7aAFu3d363U6pD7h.cZ69QhSbyTpJghKXSKG', 4, NULL, 'Aktif', '2026-04-24 03:12:57', '2026-04-24 03:17:52'),
(67, 73, 'parman', '$2y$10$aKE7ntFih3U0FH6o5dZ71.fPImnLuvaCx7yzWsXdHBQm6hDJyRIU.', 4, NULL, 'Aktif', '2026-04-24 03:12:58', '2026-04-24 03:17:52'),
(68, 74, 'didi.sariman', '$2y$10$Qr2Mogag6W/fJbpJn/w4IeYcM1n//Gc2tE6/nIe3..Tn/OsqpubYa', 4, NULL, 'Aktif', '2026-04-24 03:12:58', '2026-04-24 03:17:52'),
(69, 75, 'ujang.sugiana', '$2y$10$srmKAaOit6cL63WPpad5I.xk8swAYyylvJooxyRpRDV1aRqpesN/W', 4, NULL, 'Aktif', '2026-04-24 03:12:58', '2026-04-24 03:17:52'),
(70, 76, 'budi.utomo', '$2y$10$qM4B3p.XuEYRryKdcYHwWeX9Cq7ITJWu6cHBVsgOxycnXRT.qjp6.', 4, NULL, 'Aktif', '2026-04-24 03:12:58', '2026-04-24 03:17:52'),
(71, 77, 'yudi.martono', '$2y$10$6pYv0GBuhp2UMfdTcU7vn.I712Yll3sIcpb9rh2w3lR1furScw8C2', 4, NULL, 'Aktif', '2026-04-24 03:12:58', '2026-04-24 03:17:52'),
(72, 78, 'mulyadi', '$2y$10$gMWRodhH02ZgqjhcV4HLo.9w/GqkoM8WLPJjKxXR093glHfJBy/Ne', 4, NULL, 'Aktif', '2026-04-24 03:12:58', '2026-04-24 03:17:52'),
(73, 79, 'totok', '$2y$10$qtjV8nlBdgQbDhTG/kQKq.QdKWxmxdQRKkwjRCciYTEIsaqU1vF/K', 4, NULL, 'Aktif', '2026-04-24 03:12:58', '2026-04-24 03:17:52'),
(74, 80, 'user', '$2y$10$Gf7gXaVYZihEc0wGwRonTeLFmNDsTZPDDWjQ7l5b7gR7E8ZX0InkW', 3, NULL, 'Aktif', '2026-05-06 09:00:54', '2026-05-06 09:00:54'),
(75, 82, 'operator', '$2y$10$kQF8d8SPNGu3h9r2eT0nteGaraVAV7DIFga6PK04a8npdX7Oo7pz2', 2, NULL, 'Aktif', '2026-05-13 07:50:59', '2026-05-13 07:50:59'),
(76, 83, 'driver', '$2y$10$CIN4gDmbAdu9MC.WNujL0.p0ofqLIn6YVf0ZInygDRRlu65Lc7waG', 4, NULL, 'Aktif', '2026-05-13 10:46:37', '2026-05-13 10:46:37');

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
-- Indeks untuk tabel `email_reminder_jobs`
--
ALTER TABLE `email_reminder_jobs`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uq_email_reminder_source` (`source_type`,`source_key`,`recipient_email`),
  ADD KEY `idx_email_reminder_status_send_at` (`status`,`send_at`),
  ADD KEY `idx_email_reminder_recipient` (`recipient_email`);

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
  ADD UNIQUE KEY `uq_kendaraan_locator` (`locator`),
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
  ADD KEY `idx_peminjaman_date_range` (`tanggal_mulai`,`tanggal_selesai`,`status`),
  ADD KEY `idx_pk_surat_tugas_id` (`surat_tugas_id`);

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
  ADD KEY `fk_perbaikan_updated_by` (`updated_by`),
  ADD KEY `idx_rp_kendaraan_id` (`kendaraan_id`),
  ADD KEY `idx_rp_tanggal` (`tanggal_perbaikan`),
  ADD KEY `idx_rp_status` (`status`);

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
-- Indeks untuk tabel `traccar_positions_last`
--
ALTER TABLE `traccar_positions_last`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `device_id` (`device_id`),
  ADD UNIQUE KEY `uniq_traccar_positions_last_device_uid` (`device_uid`),
  ADD KEY `device_uid` (`device_uid`);

--
-- Indeks untuk tabel `user_account`
--
ALTER TABLE `user_account`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `username` (`username`),
  ADD UNIQUE KEY `pengguna_id` (`pengguna_id`),
  ADD KEY `role_id` (`role_id`);

--
-- AUTO_INCREMENT untuk tabel yang dibuang
--

--
-- AUTO_INCREMENT untuk tabel `dokumen_kendaraan`
--
ALTER TABLE `dokumen_kendaraan`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=12;

--
-- AUTO_INCREMENT untuk tabel `email_reminder_jobs`
--
ALTER TABLE `email_reminder_jobs`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT untuk tabel `jadwal_perawatan`
--
ALTER TABLE `jadwal_perawatan`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=75;

--
-- AUTO_INCREMENT untuk tabel `kendaraan`
--
ALTER TABLE `kendaraan`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=153;

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
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=28;

--
-- AUTO_INCREMENT untuk tabel `log_aktivitas`
--
ALTER TABLE `log_aktivitas`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=1160;

--
-- AUTO_INCREMENT untuk tabel `log_bahan_bakar`
--
ALTER TABLE `log_bahan_bakar`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=28;

--
-- AUTO_INCREMENT untuk tabel `matra`
--
ALTER TABLE `matra`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=5;

--
-- AUTO_INCREMENT untuk tabel `notifikasi`
--
ALTER TABLE `notifikasi`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=303;

--
-- AUTO_INCREMENT untuk tabel `peminjaman_kendaraan`
--
ALTER TABLE `peminjaman_kendaraan`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=36;

--
-- AUTO_INCREMENT untuk tabel `peminjaman_terjadwal`
--
ALTER TABLE `peminjaman_terjadwal`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=8;

--
-- AUTO_INCREMENT untuk tabel `pengguna`
--
ALTER TABLE `pengguna`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=84;

--
-- AUTO_INCREMENT untuk tabel `pengguna_kendaraan`
--
ALTER TABLE `pengguna_kendaraan`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=14;

--
-- AUTO_INCREMENT untuk tabel `riwayat_pemakaian`
--
ALTER TABLE `riwayat_pemakaian`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=37;

--
-- AUTO_INCREMENT untuk tabel `riwayat_perawatan`
--
ALTER TABLE `riwayat_perawatan`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=29;

--
-- AUTO_INCREMENT untuk tabel `riwayat_perbaikan`
--
ALTER TABLE `riwayat_perbaikan`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=25;

--
-- AUTO_INCREMENT untuk tabel `riwayat_perbaikan_items`
--
ALTER TABLE `riwayat_perbaikan_items`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=27;

--
-- AUTO_INCREMENT untuk tabel `role`
--
ALTER TABLE `role`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=6;

--
-- AUTO_INCREMENT untuk tabel `surat_tugas`
--
ALTER TABLE `surat_tugas`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=40;

--
-- AUTO_INCREMENT untuk tabel `traccar_positions_last`
--
ALTER TABLE `traccar_positions_last`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=3255;

--
-- AUTO_INCREMENT untuk tabel `user_account`
--
ALTER TABLE `user_account`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=77;

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
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
