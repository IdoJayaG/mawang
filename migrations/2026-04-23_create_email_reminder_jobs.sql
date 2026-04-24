-- Queue table for scheduled email reminders.
-- Safe to run multiple times.

CREATE TABLE IF NOT EXISTS email_reminder_jobs (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    recipient_email VARCHAR(255) NOT NULL,
    recipient_name VARCHAR(150) NULL,
    subject VARCHAR(255) NOT NULL,
    body_html MEDIUMTEXT NOT NULL,
    body_text TEXT NULL,
    send_at DATETIME NOT NULL,
    status ENUM('pending','sent','failed','cancelled') NOT NULL DEFAULT 'pending',
    attempt_count TINYINT UNSIGNED NOT NULL DEFAULT 0,
    max_attempts TINYINT UNSIGNED NOT NULL DEFAULT 3,
    last_error TEXT NULL,
    source_type VARCHAR(64) NULL,
    source_key VARCHAR(191) NULL,
    sent_at DATETIME NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    KEY idx_email_reminder_status_send_at (status, send_at),
    KEY idx_email_reminder_recipient (recipient_email),
    UNIQUE KEY uq_email_reminder_source (source_type, source_key, recipient_email)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
