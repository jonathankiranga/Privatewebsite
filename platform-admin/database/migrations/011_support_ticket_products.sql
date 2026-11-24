-- Run once after migration 010.
-- Stores the affected product as structured data on customer bug tickets.
ALTER TABLE platform_support_tickets
  ADD COLUMN product_id BIGINT UNSIGNED NULL AFTER requester_email,
  ADD KEY idx_ticket_product (product_id),
  ADD CONSTRAINT fk_ticket_product FOREIGN KEY (product_id) REFERENCES platform_products(id) ON DELETE SET NULL;
