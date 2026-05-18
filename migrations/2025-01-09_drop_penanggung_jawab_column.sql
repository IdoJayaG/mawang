-- Migration: Drop penanggung_jawab column from kendaraan table
-- Created: 2025-01-09
-- Purpose: Remove unused penanggung_jawab column and simplify vehicle management
-- Status: SAFE - Use after backing up kendaraan table

-- IMPORTANT: Run this after backing up your database!
-- Example backup command:
-- mysqldump -u root -p randis kendaraan > kendaraan_backup_$(date +%Y%m%d_%H%M%S).sql

-- Step 1: Verify column exists and backup structure
-- Run this first to confirm the column exists:
-- SHOW COLUMNS FROM kendaraan WHERE Field = 'penanggung_jawab';

-- Step 2: Drop the penanggung_jawab column
ALTER TABLE kendaraan DROP COLUMN IF EXISTS penanggung_jawab;

-- Step 3: Verify the column is removed
-- SHOW COLUMNS FROM kendaraan WHERE Field = 'penanggung_jawab';

-- Notes:
-- - The column was used to track vehicle responsibility but is now handled via pengguna_id (user relationship)
-- - All code references have been updated to stop using this field
-- - No data loss for active vehicles; penanggung_jawab is redundant with pengguna_id
