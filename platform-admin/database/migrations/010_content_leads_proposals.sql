-- Run once after migrations 002 through 009.
-- Adds website-managed product content, sales leads and customer proposals.

ALTER TABLE platform_products
  ADD COLUMN tagline VARCHAR(220) NOT NULL DEFAULT '' AFTER summary,
  ADD COLUMN description MEDIUMTEXT NULL AFTER tagline,
  ADD COLUMN features MEDIUMTEXT NULL AFTER description,
  ADD COLUMN benefits MEDIUMTEXT NULL AFTER features,
  ADD COLUMN target_audience VARCHAR(1000) NULL AFTER benefits,
  ADD COLUMN youtube_url VARCHAR(500) NULL AFTER target_audience,
  ADD COLUMN hero_image_url VARCHAR(500) NULL AFTER youtube_url,
  ADD COLUMN meta_title VARCHAR(190) NULL AFTER hero_image_url,
  ADD COLUMN meta_description VARCHAR(320) NULL AFTER meta_title,
  ADD COLUMN sort_order INT NOT NULL DEFAULT 0 AFTER meta_description,
  ADD COLUMN is_featured TINYINT(1) NOT NULL DEFAULT 0 AFTER sort_order;

CREATE TABLE platform_leads (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  customer_id BIGINT UNSIGNED NULL,
  name VARCHAR(160) NOT NULL,
  email VARCHAR(190) NOT NULL,
  phone VARCHAR(60) NULL,
  organisation VARCHAR(190) NULL,
  product_id BIGINT UNSIGNED NULL,
  source VARCHAR(80) NOT NULL DEFAULT 'admin',
  subject VARCHAR(240) NOT NULL,
  message TEXT NULL,
  status ENUM('new','contacted','qualified','proposal_sent','negotiation','won','lost') NOT NULL DEFAULT 'new',
  priority ENUM('low','normal','high') NOT NULL DEFAULT 'normal',
  assigned_admin_id BIGINT UNSIGNED NULL,
  next_follow_up DATETIME NULL,
  estimated_value DECIMAL(14,2) NULL,
  currency CHAR(3) NOT NULL DEFAULT 'KES',
  lost_reason VARCHAR(500) NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  KEY idx_lead_status_followup (status,next_follow_up),
  KEY idx_lead_email (email),
  KEY idx_lead_product (product_id),
  CONSTRAINT fk_lead_customer FOREIGN KEY (customer_id) REFERENCES platform_customers(id) ON DELETE SET NULL,
  CONSTRAINT fk_lead_product FOREIGN KEY (product_id) REFERENCES platform_products(id) ON DELETE SET NULL,
  CONSTRAINT fk_lead_admin FOREIGN KEY (assigned_admin_id) REFERENCES admin_users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE platform_lead_activities (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  lead_id BIGINT UNSIGNED NOT NULL,
  admin_user_id BIGINT UNSIGNED NULL,
  activity_type ENUM('note','call','email','meeting','status_change','follow_up') NOT NULL DEFAULT 'note',
  subject VARCHAR(240) NOT NULL,
  details TEXT NULL,
  follow_up_at DATETIME NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  KEY idx_lead_activity (lead_id,created_at),
  CONSTRAINT fk_lead_activity_lead FOREIGN KEY (lead_id) REFERENCES platform_leads(id) ON DELETE CASCADE,
  CONSTRAINT fk_lead_activity_admin FOREIGN KEY (admin_user_id) REFERENCES admin_users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE platform_proposals (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  lead_id BIGINT UNSIGNED NULL,
  customer_id BIGINT UNSIGNED NULL,
  proposal_number VARCHAR(40) NOT NULL,
  public_token CHAR(64) NOT NULL,
  title VARCHAR(240) NOT NULL,
  recipient_name VARCHAR(160) NOT NULL DEFAULT '',
  recipient_email VARCHAR(190) NOT NULL DEFAULT '',
  recipient_organisation VARCHAR(190) NOT NULL DEFAULT '',
  recipient_phone VARCHAR(60) NOT NULL DEFAULT '',
  company_details_json JSON NULL,
  introduction MEDIUMTEXT NULL,
  scope_of_work MEDIUMTEXT NULL,
  assumptions MEDIUMTEXT NULL,
  terms MEDIUMTEXT NULL,
  currency CHAR(3) NOT NULL DEFAULT 'KES',
  subtotal DECIMAL(14,2) NOT NULL DEFAULT 0,
  discount_amount DECIMAL(14,2) NOT NULL DEFAULT 0,
  vat_rate DECIMAL(6,2) NOT NULL DEFAULT 0,
  vat_amount DECIMAL(14,2) NOT NULL DEFAULT 0,
  total_amount DECIMAL(14,2) NOT NULL DEFAULT 0,
  valid_until DATE NULL,
  status ENUM('draft','sent','accepted','rejected','expired','withdrawn') NOT NULL DEFAULT 'draft',
  sent_at DATETIME NULL,
  accepted_at DATETIME NULL,
  created_by BIGINT UNSIGNED NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uq_proposal_number (proposal_number),
  UNIQUE KEY uq_proposal_token (public_token),
  KEY idx_proposal_lead (lead_id,status),
  CONSTRAINT fk_proposal_lead FOREIGN KEY (lead_id) REFERENCES platform_leads(id) ON DELETE SET NULL,
  CONSTRAINT fk_proposal_customer FOREIGN KEY (customer_id) REFERENCES platform_customers(id) ON DELETE SET NULL,
  CONSTRAINT fk_proposal_admin FOREIGN KEY (created_by) REFERENCES admin_users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE platform_proposal_items (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  proposal_id BIGINT UNSIGNED NOT NULL,
  product_id BIGINT UNSIGNED NULL,
  description VARCHAR(500) NOT NULL,
  quantity DECIMAL(12,2) NOT NULL DEFAULT 1,
  unit_price DECIMAL(14,2) NOT NULL DEFAULT 0,
  line_total DECIMAL(14,2) NOT NULL DEFAULT 0,
  sort_order INT NOT NULL DEFAULT 0,
  PRIMARY KEY (id),
  KEY idx_proposal_items (proposal_id,sort_order),
  CONSTRAINT fk_proposal_item_proposal FOREIGN KEY (proposal_id) REFERENCES platform_proposals(id) ON DELETE CASCADE,
  CONSTRAINT fk_proposal_item_product FOREIGN KEY (product_id) REFERENCES platform_products(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE platform_proposal_email_log (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  proposal_id BIGINT UNSIGNED NOT NULL,
  recipient_email VARCHAR(190) NOT NULL,
  subject VARCHAR(240) NOT NULL,
  sent_by BIGINT UNSIGNED NULL,
  smtp_accepted TINYINT(1) NOT NULL DEFAULT 0,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  KEY idx_proposal_email_log (proposal_id,created_at),
  CONSTRAINT fk_proposal_email_proposal FOREIGN KEY (proposal_id) REFERENCES platform_proposals(id) ON DELETE CASCADE,
  CONSTRAINT fk_proposal_email_admin FOREIGN KEY (sent_by) REFERENCES admin_users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
