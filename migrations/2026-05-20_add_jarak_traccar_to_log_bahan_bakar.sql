-- Migration: add jarak_traccar_km to log_bahan_bakar
ALTER TABLE `log_bahan_bakar`
    ADD COLUMN `jarak_traccar_km` DECIMAL(8,2) NULL DEFAULT NULL AFTER `km_saat_isi`;

-- EOF
