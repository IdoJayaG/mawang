-- Migration: add traccar_positions_last table
CREATE TABLE IF NOT EXISTS traccar_positions_last (
  id INT AUTO_INCREMENT PRIMARY KEY,
  device_id BIGINT NULL,
  device_uid VARCHAR(255) NULL,
  device_name VARCHAR(255) NULL,
  latitude DOUBLE NULL,
  longitude DOUBLE NULL,
  speed DOUBLE NULL,
  course DOUBLE NULL,
  accuracy DOUBLE NULL,
  device_time DATETIME NULL,
  server_time DATETIME DEFAULT CURRENT_TIMESTAMP,
  extra JSON NULL,
  updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  UNIQUE KEY (device_id),
  KEY (device_uid)
);
