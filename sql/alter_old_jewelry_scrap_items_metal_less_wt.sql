-- Add metal_id and less_wt to scrap invoice items (for Scrap Payment modal from sale invoice etc.)
-- Run once. Safe to run if columns already exist (will error; ignore or use IF NOT EXISTS per your MySQL version).

ALTER TABLE tbl_old_jewelry_scrap_invoice_items
  ADD COLUMN metal_id INT(11) DEFAULT NULL AFTER description,
  ADD COLUMN less_wt DECIMAL(15,4) DEFAULT 0.0000 AFTER gross_wt;
