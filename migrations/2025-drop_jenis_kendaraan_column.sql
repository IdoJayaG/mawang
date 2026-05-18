-- Migration: Drop jenis_kendaraan column from kendaraan table
-- Description: Removes the jenis (vehicle type) enum field and replaces it with pengguna_id driver selection
-- Date: 2025
-- Note: Ensure you have backed up the database before running this migration

ALTER TABLE kendaraan DROP COLUMN IF EXISTS jenis;
