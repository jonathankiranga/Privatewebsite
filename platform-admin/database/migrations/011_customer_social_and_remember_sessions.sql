-- Persistent customer sessions and external identity links.
-- Run once after migration 003_customer_accounts.sql.
CREATE TABLE platform_customer_remember_tokens (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  account_id BIGINT UNSIGNED NOT NULL,
  token_hash CHAR(64) NOT NULL,
  expires_at DATETIME NOT NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uq_customer_remember_token_hash (token_hash),
  KEY idx_customer_remember_account (account_id),
  KEY idx_customer_remember_expiry (expires_at),
  CONSTRAINT fk_customer_remember_account FOREIGN KEY (account_id)
    REFERENCES platform_customer_accounts(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE platform_customer_identities (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  account_id BIGINT UNSIGNED NOT NULL,
  provider VARCHAR(24) NOT NULL,
  provider_subject VARCHAR(191) NOT NULL,
  provider_email VARCHAR(190) NOT NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  last_login_at DATETIME NULL,
  PRIMARY KEY (id),
  UNIQUE KEY uq_customer_identity_provider_subject (provider, provider_subject),
  UNIQUE KEY uq_customer_identity_provider_account (provider, account_id),
  KEY idx_customer_identity_account (account_id),
  CONSTRAINT fk_customer_identity_account FOREIGN KEY (account_id)
    REFERENCES platform_customer_accounts(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
