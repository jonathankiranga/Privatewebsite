-- Run once after schema.sql. Adds the fields needed for actual customer support operations.
ALTER TABLE platform_customers
  ADD COLUMN contact_name VARCHAR(160) NULL AFTER email,
  ADD COLUMN phone VARCHAR(40) NULL AFTER contact_name,
  ADD COLUMN country_code CHAR(2) NULL AFTER phone,
  ADD COLUMN notes TEXT NULL AFTER status,
  ADD COLUMN updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP AFTER created_at;

ALTER TABLE platform_support_tickets
  ADD COLUMN ticket_type ENUM('support','bug','feature_request') NOT NULL DEFAULT 'support' AFTER customer_id,
  ADD COLUMN requester_email VARCHAR(190) NULL AFTER ticket_type,
  ADD COLUMN description TEXT NULL AFTER subject,
  ADD COLUMN assigned_admin_id BIGINT UNSIGNED NULL AFTER priority,
  ADD KEY idx_ticket_type (ticket_type),
  ADD CONSTRAINT fk_ticket_assigned_admin FOREIGN KEY (assigned_admin_id) REFERENCES admin_users(id) ON DELETE SET NULL;

CREATE TABLE platform_ticket_messages (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  ticket_id BIGINT UNSIGNED NOT NULL,
  author_type ENUM('customer','admin','system') NOT NULL,
  author_admin_id BIGINT UNSIGNED NULL,
  message TEXT NOT NULL,
  is_internal TINYINT(1) NOT NULL DEFAULT 0,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  KEY idx_ticket_message_ticket (ticket_id, created_at),
  CONSTRAINT fk_ticket_message_ticket FOREIGN KEY (ticket_id) REFERENCES platform_support_tickets(id) ON DELETE CASCADE,
  CONSTRAINT fk_ticket_message_admin FOREIGN KEY (author_admin_id) REFERENCES admin_users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
