-- Migration: Add 'Menunggu Sparepart' to riwayat_perbaikan.status enum
-- Safe to run multiple times; only changes enum definition.

ALTER TABLE `riwayat_perbaikan`
  MODIFY `status` ENUM('Dalam Proses','Menunggu Sparepart','Ditunda','Selesai')
  DEFAULT 'Dalam Proses';
