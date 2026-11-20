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

-- Seed the current public product catalogue so the existing offerings are editable immediately.
-- Existing rows are preserved; these inserts only add a product when its slug is absent.
INSERT INTO platform_products (slug,name,summary,tagline,description,features,benefits,target_audience,meta_title,meta_description,status,sort_order,is_featured)
SELECT 'supermarket-pos','Supermarket POS','Barcode-led supermarket checkout with stock, cash and shift controls.','Move supermarket queues faster without losing stock or cash control.','Supermarket POS combines barcode-first checkout with inventory controls and cashier shift reporting. The workflow is designed for quick scanning, accurate quantities, payment recording and traceable stock movement.','Auto-focused barcode scanning (F2)\nAisle quick-pick tiles\nDuplicate barcode quantity increment\nHold and recall transactions\nKES cash tendering and M-Pesa reference checks\nX and Z shift reports\nAtomic stockledger deduction and FIFO stockregister updates\nThermal receipt printing through the local print agent','Faster checkout, fewer manual stock adjustments, clearer cashier accountability and traceable stock deductions.','Supermarkets, minimarts, grocery shops and multi-counter retailers.','Supermarket POS Software | SmarterNow','Barcode supermarket POS with KES payments, M-Pesa references, FIFO stock control and cashier shift reports.', 'published',10,1
WHERE NOT EXISTS (SELECT 1 FROM platform_products WHERE slug='supermarket-pos');

INSERT INTO platform_products (slug,name,summary,tagline,description,features,benefits,target_audience,meta_title,meta_description,status,sort_order,is_featured)
SELECT 'hair-salon-pos','Hair Salon POS','Appointments, stylist assignments, till checkout and commission tracking.','Manage salon appointments, services and stylist commissions in one workflow.','Hair Salon POS connects client bookings, stylist skill mapping, till checkout and commission records. The service lifecycle moves appointments through booked, arrived, in-service and completed states, with controls that help prevent duplicate checkout.','Appointment lifecycle: booked, arrived, in_service and completed\nStylist skill mapping\nService checkout and till integration\nLocks booked service lines against duplicate checkouts\nPercentage commission calculated on VAT-excluded values\nFixed-value stylist commission rules\nSalon service commission ledger and payout tracking','Better appointment visibility, consistent service checkout and transparent stylist commission calculations.','Hair salons, barbershops, beauty studios and businesses paying staff commissions per service.','Hair Salon POS Software | SmarterNow','Salon POS with appointment tracking, stylist assignment, checkout and service commission records.', 'published',20,1
WHERE NOT EXISTS (SELECT 1 FROM platform_products WHERE slug='hair-salon-pos');

INSERT INTO platform_products (slug,name,summary,tagline,description,features,benefits,target_audience,meta_title,meta_description,status,sort_order,is_featured)
SELECT 'touch-pos-terminal','Touch POS Terminal','Touchscreen checkout with large controls, shift management and local receipt printing.','Run a full till on a touchscreen without depending on a mouse or keyboard.','Touch POS Terminal provides a tablet- and counter-ready checkout interface. Large touch targets, a full-screen catalogue and visible shift controls help cashiers operate the till using touch-first interactions.','44px–62px touch targets and quantity steppers\nSticky category chips and full-screen catalogue\nLive open-shift controls\nX-Report and Z-Close actions\nLocal Windows print agent for thermal receipts\nPrint-agent unavailability does not have to block a sale','Simpler cashier interaction, faster product selection and clear shift-end reporting at touch-enabled counters.','Retail counters, supermarkets, kiosks and service businesses using touch displays.','Touch POS Terminal | SmarterNow','Touchscreen POS terminal with large controls, shift reports and local thermal receipt printing.', 'published',30,1
WHERE NOT EXISTS (SELECT 1 FROM platform_products WHERE slug='touch-pos-terminal');

INSERT INTO platform_products (slug,name,summary,tagline,description,features,benefits,target_audience,meta_title,meta_description,status,sort_order,is_featured)
SELECT 'smart-lims','Smart LIMS','Laboratory sample lifecycle, testing, review, approval and reporting.','Track laboratory samples from intake through approved reports.','Smart LIMS manages laboratory sample workflows from registration and departmental receipt through analysis, result review, approval and reporting. It supports operational traceability across laboratory activities and controlled reporting.','Sample registration and departmental receipt\nChemical and microbiological result workflows\nSubcontractor allocation and custody trail\nTest result review and approval\nSample disposal and control samples\nLaboratory reports and Certificates of Assessment','More controlled sample handling, traceable laboratory activity and consistent approved reporting.','Testing laboratories, quality-control laboratories and organisations managing sample-based testing.','Smart LIMS Laboratory Information Management System | SmarterNow','Laboratory information management for sample registration, testing, result approval and reporting.', 'published',40,1
WHERE NOT EXISTS (SELECT 1 FROM platform_products WHERE slug='smart-lims');

INSERT INTO platform_products (slug,name,summary,tagline,description,features,benefits,target_audience,meta_title,meta_description,status,sort_order,is_featured)
SELECT 'school-management','School Management Suite','School administration workspaces for teachers, school leadership and parents.','Keep school leadership, teachers and parents aligned on one system.','School Management Suite provides separate teacher, headteacher and parent workspaces supported by a unified school API. It brings academic, student, staff and fee information into connected school administration workflows.','Attendance and class records\nExaminations, lesson plans and competencies\nStaff, classes and student records\nFee management and statements\nParent academic reports\nSchool marketplace and parent communication','Less fragmented school administration, clearer academic records and improved access to parent-facing information.','Primary and secondary schools, school administrators, teachers and parent communities.','School Management Software | SmarterNow','School administration suite for student records, attendance, exams, fees, teachers and parent access.', 'published',50,1
WHERE NOT EXISTS (SELECT 1 FROM platform_products WHERE slug='school-management');

INSERT INTO platform_products (slug,name,summary,tagline,description,features,benefits,target_audience,meta_title,meta_description,status,sort_order,is_featured)
SELECT 'chama-messaging','Chama Messaging','Android bulk SMS for contact groups, queued messages and delivery history.','Send group messages and keep track of communication activity.','Chama Messaging is an Android bulk SMS application for organising contacts and groups, validating message content, queuing sends and using the Africa''s Talking gateway. It is designed for group communication and repeatable messaging workflows.','Contact and group management\nGroup and all-contact messaging\nKenyan phone number normalisation\nMessage validation and queueing\nSending history\nAfrica''s Talking gateway settings for sandbox or production','Organised contact lists, more consistent group communication and a visible record of queued and sent messages.','Chamas, savings groups, associations, clubs and organisations that send SMS updates to members.','Chama Messaging Bulk SMS App | SmarterNow','Android bulk SMS for chamas and groups with contact management, message queues and Africa''s Talking gateway support.', 'published',60,1
WHERE NOT EXISTS (SELECT 1 FROM platform_products WHERE slug='chama-messaging');

INSERT INTO platform_products (slug,name,summary,tagline,description,features,benefits,target_audience,meta_title,meta_description,status,sort_order,is_featured)
SELECT 'smart-erp','Shared Accounting & Inventory ERP Backend','A shared accounting and inventory backend that supports connected SmarterNow applications; it is a backend module, not a standalone app.','One shared financial and inventory foundation for connected applications.','The SmarterNow Accounting & Inventory ERP Backend provides shared financial and stock records used by connected applications. It is positioned as a backend module rather than a separate standalone application. Confirm the modules and integrations enabled in the target deployment before implementation.','Double-entry General Ledger journals\nCash and M-Pesa bank transaction records\nDebtor ledger records\nFIFO stock register layers\nInventory and stock movement records\nAudit trail records\nShared backend services for connected POS and business applications','A common accounting and inventory record foundation across connected business applications, reducing fragmented financial and stock records.','Organisations deploying connected SmarterNow POS and business applications that need shared accounting and inventory records.','Accounting & Inventory ERP Backend | SmarterNow','Shared Accounting and Inventory ERP backend module for connected SmarterNow applications; not a standalone app.', 'published',70,0
WHERE NOT EXISTS (SELECT 1 FROM platform_products WHERE slug='smart-erp');
