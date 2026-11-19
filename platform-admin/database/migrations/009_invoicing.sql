-- Run once after migrations 002 through 008.
CREATE TABLE platform_invoice_settings (
 id TINYINT UNSIGNED NOT NULL, legal_name VARCHAR(190) NOT NULL DEFAULT '', trading_name VARCHAR(190) NOT NULL DEFAULT '',
 company_registration_no VARCHAR(100) NOT NULL DEFAULT '', kra_pin VARCHAR(40) NOT NULL DEFAULT '', vat_pin VARCHAR(40) NOT NULL DEFAULT '',
 address TEXT NULL, city VARCHAR(120) NOT NULL DEFAULT '', country VARCHAR(100) NOT NULL DEFAULT 'Kenya',
 phone VARCHAR(60) NOT NULL DEFAULT '', email VARCHAR(190) NOT NULL DEFAULT '', website VARCHAR(255) NOT NULL DEFAULT '',
 mpesa_till_number VARCHAR(40) NOT NULL DEFAULT '', mpesa_till_name VARCHAR(190) NOT NULL DEFAULT '',
 mpesa_account_reference VARCHAR(100) NOT NULL DEFAULT '', payment_terms VARCHAR(500) NOT NULL DEFAULT 'Payment due within 7 days.',
 invoice_prefix VARCHAR(20) NOT NULL DEFAULT 'INV', default_vat_rate DECIMAL(5,2) NOT NULL DEFAULT 0.00,
 updated_by BIGINT UNSIGNED NULL, updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
 PRIMARY KEY(id), CONSTRAINT fk_invoice_settings_admin FOREIGN KEY(updated_by) REFERENCES admin_users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
INSERT INTO platform_invoice_settings(id) VALUES(1);
CREATE TABLE platform_invoices (
 id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT, invoice_number VARCHAR(40) NOT NULL, customer_id BIGINT UNSIGNED NOT NULL,
 invoice_date DATE NOT NULL, due_date DATE NOT NULL, currency CHAR(3) NOT NULL DEFAULT 'KES',
 subtotal DECIMAL(14,2) NOT NULL DEFAULT 0.00, vat_rate DECIMAL(5,2) NOT NULL DEFAULT 0.00,
 vat_amount DECIMAL(14,2) NOT NULL DEFAULT 0.00, total_amount DECIMAL(14,2) NOT NULL DEFAULT 0.00,
 amount_paid DECIMAL(14,2) NOT NULL DEFAULT 0.00, balance_due DECIMAL(14,2) NOT NULL DEFAULT 0.00,
 status ENUM('issued','part_paid','paid','overdue','void') NOT NULL DEFAULT 'issued', notes TEXT NULL,
 company_details_json JSON NOT NULL, customer_details_json JSON NOT NULL, created_by BIGINT UNSIGNED NULL,
 created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP, updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
 PRIMARY KEY(id), UNIQUE KEY uq_platform_invoice_number(invoice_number), KEY idx_platform_invoice_customer(customer_id,invoice_date),
 KEY idx_platform_invoice_status(status,due_date),
 CONSTRAINT fk_platform_invoice_customer FOREIGN KEY(customer_id) REFERENCES platform_customers(id) ON DELETE RESTRICT,
 CONSTRAINT fk_platform_invoice_admin FOREIGN KEY(created_by) REFERENCES admin_users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
CREATE TABLE platform_invoice_items (
 id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT, invoice_id BIGINT UNSIGNED NOT NULL, description VARCHAR(500) NOT NULL,
 quantity DECIMAL(12,3) NOT NULL, unit_price DECIMAL(14,2) NOT NULL, line_total DECIMAL(14,2) NOT NULL,
 sort_order SMALLINT UNSIGNED NOT NULL DEFAULT 0, PRIMARY KEY(id), KEY idx_platform_invoice_item_invoice(invoice_id,sort_order),
 CONSTRAINT fk_platform_invoice_item_invoice FOREIGN KEY(invoice_id) REFERENCES platform_invoices(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
CREATE TABLE platform_invoice_payments (
 id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT, invoice_id BIGINT UNSIGNED NOT NULL, payment_date DATE NOT NULL,
 amount DECIMAL(14,2) NOT NULL, method ENUM('mpesa','bank','cash','other') NOT NULL DEFAULT 'mpesa',
 reference VARCHAR(120) NULL, notes VARCHAR(500) NULL, recorded_by BIGINT UNSIGNED NULL, created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
 PRIMARY KEY(id), KEY idx_platform_invoice_payment_invoice(invoice_id,payment_date),
 CONSTRAINT fk_platform_invoice_payment_invoice FOREIGN KEY(invoice_id) REFERENCES platform_invoices(id) ON DELETE RESTRICT,
 CONSTRAINT fk_platform_invoice_payment_admin FOREIGN KEY(recorded_by) REFERENCES admin_users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;