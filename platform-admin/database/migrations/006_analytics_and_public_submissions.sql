-- Run once after migration 005_subscription_enquiries.sql.
-- Raw IP addresses are never stored in the visitor event table.
CREATE TABLE platform_visitor_events (
 id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
 visitor_hash CHAR(64) NOT NULL,
 event_type ENUM('page_view') NOT NULL DEFAULT 'page_view',
 page_path VARCHAR(500) NOT NULL,
 referrer_host VARCHAR(190) NULL,
 device_type ENUM('mobile','tablet','desktop','unknown') NOT NULL DEFAULT 'unknown',
 browser_family VARCHAR(40) NOT NULL DEFAULT 'unknown',
 ip_hash CHAR(64) NOT NULL,
 created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
 PRIMARY KEY (id),
 KEY idx_visitor_event_created (created_at),
 KEY idx_visitor_hash_created (visitor_hash,created_at),
 KEY idx_visitor_page_created (page_path(191),created_at),
 KEY idx_visitor_referrer_created (referrer_host,created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE platform_public_submission_limits (
 id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
 email_hash CHAR(64) NOT NULL,
 ip_hash CHAR(64) NOT NULL,
 created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
 PRIMARY KEY (id),
 KEY idx_public_limit_email (email_hash,created_at),
 KEY idx_public_limit_ip (ip_hash,created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
