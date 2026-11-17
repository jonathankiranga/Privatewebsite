-- Run once after schema.sql and migrations 002, 003 and 004.
-- This stores published plan information and customer enquiries only.
-- It does not create paid subscriptions or grant product access.
CREATE TABLE platform_subscription_plans (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  product_id BIGINT UNSIGNED NOT NULL,
  plan_code VARCHAR(100) NOT NULL,
  name VARCHAR(160) NOT NULL,
  summary VARCHAR(500) NOT NULL,
  features MEDIUMTEXT NOT NULL,
  price DECIMAL(12,2) NOT NULL,
  currency CHAR(3) NOT NULL DEFAULT 'KES',
  billing_interval ENUM('monthly','quarterly','annual','one_time') NOT NULL DEFAULT 'monthly',
  is_published TINYINT(1) NOT NULL DEFAULT 0,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uq_plan_product_code (product_id,plan_code),
  KEY idx_plan_published (is_published),
  CONSTRAINT fk_plan_product FOREIGN KEY (product_id) REFERENCES platform_products(id) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE platform_subscription_enquiries (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  customer_id BIGINT UNSIGNED NOT NULL,
  plan_id BIGINT UNSIGNED NOT NULL,
  request_message TEXT NULL,
  status ENUM('new','contacted','quoted','quote_accepted','declined','closed') NOT NULL DEFAULT 'new',
  admin_notes TEXT NULL,
  assigned_admin_id BIGINT UNSIGNED NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  KEY idx_subscription_enquiry_status (status,updated_at),
  KEY idx_subscription_enquiry_customer (customer_id,created_at),
  CONSTRAINT fk_subscription_enquiry_customer FOREIGN KEY (customer_id) REFERENCES platform_customers(id) ON DELETE CASCADE,
  CONSTRAINT fk_subscription_enquiry_plan FOREIGN KEY (plan_id) REFERENCES platform_subscription_plans(id) ON DELETE RESTRICT,
  CONSTRAINT fk_subscription_enquiry_admin FOREIGN KEY (assigned_admin_id) REFERENCES admin_users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
