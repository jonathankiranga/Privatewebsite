-- Apply once after the existing platform-admin migrations.
ALTER TABLE admin_users
  ADD COLUMN password_reset_token_hash CHAR(64) NULL AFTER password_hash,
  ADD COLUMN password_reset_expires_at DATETIME NULL AFTER password_reset_token_hash,
  ADD COLUMN auth_version INT UNSIGNED NOT NULL DEFAULT 1 AFTER password_reset_expires_at,
  ADD UNIQUE KEY uq_admin_password_reset_token (password_reset_token_hash);

CREATE TABLE admin_password_reset_requests (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  email_hash CHAR(64) NOT NULL,
  ip_hash CHAR(64) NOT NULL,
  requested_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  KEY idx_admin_reset_email_time (email_hash, requested_at),
  KEY idx_admin_reset_ip_time (ip_hash, requested_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
