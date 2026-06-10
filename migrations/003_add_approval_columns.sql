-- Migration: Add approval columns for pimpinan approval workflow
-- Date: 2026-06-07
-- Description: Add pimpinan approval status, approver, and timestamp columns to peminjaman_kendaraan and surat_tugas tables

-- ============================================
-- Add approval columns to peminjaman_kendaraan
-- ============================================

ALTER TABLE `peminjaman_kendaraan` ADD COLUMN `approval_pimpinan_status` ENUM('Pending','Approved','Rejected') NOT NULL DEFAULT 'Pending' AFTER `status`;
ALTER TABLE `peminjaman_kendaraan` ADD COLUMN `approval_pimpinan_by` INT NULL AFTER `approval_pimpinan_status`;
ALTER TABLE `peminjaman_kendaraan` ADD COLUMN `approval_pimpinan_at` DATETIME NULL AFTER `approval_pimpinan_by`;
ALTER TABLE `peminjaman_kendaraan` ADD COLUMN `rejected_reason` TEXT NULL AFTER `approval_pimpinan_at`;

-- Create index for faster queries
ALTER TABLE `peminjaman_kendaraan` ADD INDEX `idx_approval_pimpinan_status` (`approval_pimpinan_status`);

-- ============================================
-- Add approval columns to surat_tugas
-- ============================================

ALTER TABLE `surat_tugas` ADD COLUMN `approval_pimpinan_status` ENUM('Pending','Approved','Rejected') NOT NULL DEFAULT 'Pending' AFTER `status`;
ALTER TABLE `surat_tugas` ADD COLUMN `approval_pimpinan_by` INT NULL AFTER `approval_pimpinan_status`;
ALTER TABLE `surat_tugas` ADD COLUMN `approval_pimpinan_at` DATETIME NULL AFTER `approval_pimpinan_by`;
ALTER TABLE `surat_tugas` ADD COLUMN `rejected_reason` TEXT NULL AFTER `approval_pimpinan_at`;

-- Create index for faster queries
ALTER TABLE `surat_tugas` ADD INDEX `idx_approval_pimpinan_status` (`approval_pimpinan_status`);

-- ============================================
-- Notes
-- ============================================
-- These columns support the new approval workflow:
-- - approval_pimpinan_status: Status of pimpinan approval (Pending/Approved/Rejected)
-- - approval_pimpinan_by: ID of the pimpinan who approved/rejected
-- - approval_pimpinan_at: Timestamp when approval/rejection was made
-- - rejected_reason: Reason for rejection (if applicable)
