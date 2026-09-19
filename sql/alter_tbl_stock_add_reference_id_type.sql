-- Add reference_id and reference_type to tbl_stock for tracking (e.g. stock journal edit/delete)
-- Run once. Skip if columns already exist.

ALTER TABLE `tbl_stock`
ADD COLUMN `reference_id` INT NULL DEFAULT NULL COMMENT 'e.g. stock_journal.id'
AFTER `transaction_date`;

ALTER TABLE `tbl_stock`
ADD COLUMN `reference_type` VARCHAR(50) NULL DEFAULT NULL COMMENT 'e.g. stock_journal'
AFTER `reference_id`;
