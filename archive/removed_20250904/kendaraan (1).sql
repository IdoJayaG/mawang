-- phpMyAdmin SQL Dump
-- version 5.2.1
-- https://www.phpmyadmin.net/
--
-- Host: 127.0.0.1
-- Waktu pembuatan: 25 Agu 2025 pada 10.09
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
(1, 'B 1234 AB', 'RNG123456789', 'MSN123456789', 'Toyota', 'SUV', '2020', 'Hitam', 'Roda 4', 'Bensin', 'REG001', 'STNK001', NULL, '2025-12-31', 'LAKSDA TNI ARIANTYO CONDROWIBOWO', 'Satker', 'Pusinfolahta', 'KODE123', 'Baik', 'Operasional', 'Dipinjam', '2023-01-15', 20, 320, 'jeep.png', 'Kendaraan dinas TNI', '2025-08-13 01:14:11', '2025-08-25 08:07:54'),
(3, 'B 9101 EF', 'RNG456789123', 'MSN456789123', 'Suzuki', 'MPV', '2021', 'Biru', 'Roda 4', 'Bensin', 'REG003', 'STNK003', NULL, '2026-05-15', 'MAYJEN TNI TRI MARTONO', 'Satker', 'Pusinfolahta', 'KODE789', 'Baik', 'Operasional', 'Dipinjam', '2023-03-10', 20, 320, 'jeep.png', 'Kendaraan sewa TNI', '2025-08-13 01:14:11', '2025-08-25 08:07:57'),
(15, 'B 1234 CD', 'RNG12342345', 'MSN135563323', 'Avanza', 'SUV', '2002', 'Hitam', 'Roda 4', 'Bensin', NULL, NULL, NULL, NULL, NULL, 'Satker', 'Pusinfolahta', NULL, 'Baik', 'Operasional', 'Dipinjam', NULL, 20, 320, 'jeep.png', NULL, '2025-08-12 18:34:07', '2025-08-25 08:07:59');

--
-- Indexes for dumped tables
--

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
-- AUTO_INCREMENT untuk tabel yang dibuang
--

--
-- AUTO_INCREMENT untuk tabel `kendaraan`
--
ALTER TABLE `kendaraan`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=24;
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
