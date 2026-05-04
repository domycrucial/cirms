-- Run in the `cirms` database if audit_log is missing (restores logging; app works without it).
-- Foreign keys omitted so this runs even on non-standard `users` tables.

USE cirms;

CREATE TABLE IF NOT EXISTS audit_log (
    id          INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    user_id     INT UNSIGNED NULL,
    action      VARCHAR(100) NOT NULL,
    target_type VARCHAR(50) NULL,
    target_id   INT UNSIGNED NULL,
    details     JSON NULL,
    ip_address  VARCHAR(45) NULL,
    user_agent  VARCHAR(255) NULL,
    created_at  DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
