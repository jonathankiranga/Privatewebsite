-- Run once after migration 009_invoicing.sql.
-- Gives every recorded payment a permanent receipt number and records email attempts.
ALTER TABLE platform_invoice_payments
  ADD COLUMN receipt_number VARCHAR(40) NULL AFTER id;

UPDATE platform_invoice_payments
SET receipt_number = CONCAT('RCT-', YEAR(created_at), '-', LPAD(id, 8, '0'))
WHERE receipt_number IS NULL OR receipt_number = '';

ALTER TABLE platform_invoice_payments
  MODIFY receipt_number VARCHAR(40) NOT NULL,
  ADD UNIQUE KEY uq_platform_invoice_payment_receipt (receipt_number);

CREATE TABLE platform_invoice_receipt_email_log (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  payment_id BIGINT UNSIGNED NOT NULL,
  recipient_email VARCHAR(190) NOT NULL,
  subject VARCHAR(240) NOT NULL,
  sent_by BIGINT UNSIGNED NULL,
  smtp_accepted TINYINT(1) NOT NULL DEFAULT 0,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  KEY idx_invoice_receipt_email_payment (payment_id, created_at),
  CONSTRAINT fk_invoice_receipt_email_payment
    FOREIGN KEY (payment_id) REFERENCES platform_invoice_payments(id) ON DELETE RESTRICT,
  CONSTRAINT fk_invoice_receipt_email_admin
    FOREIGN KEY (sent_by) REFERENCES admin_users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
