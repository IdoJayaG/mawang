-- Migration: add cancel_reason to peminjaman_kendaraan
ALTER TABLE peminjaman_kendaraan
ADD COLUMN cancel_reason TEXT DEFAULT NULL;
