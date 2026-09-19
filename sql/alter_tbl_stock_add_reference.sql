-- Add reference column to tbl_stock to link outward entries to sale invoice (for edit/reverse)
-- Run this once so sale invoice edit can reverse previous outward by reference = invoice_id

ALTER TABLE `tbl_stock`
ADD COLUMN `reference` VARCHAR(50) NULL DEFAULT NULL
COMMENT 'Sale invoice id for outward entries; used to reverse on edit'
AFTER `transaction_date`;

ALTER TABLE `tbl_stock`
ADD INDEX `idx_stock_type_reference` (`stock_type`, `reference`);
