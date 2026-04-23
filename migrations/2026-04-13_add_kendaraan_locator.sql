-- Migration: add locator field for Traccar device UID on kendaraan
ALTER TABLE kendaraan
  ADD COLUMN locator VARCHAR(255) NULL AFTER penanggung_jawab,
  ADD UNIQUE KEY uq_kendaraan_locator (locator);
