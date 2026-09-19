-- Add metal_rate to tbl_sale_invoice_items for Diamond tab Metal group
-- Run once in phpMyAdmin. If column exists, ALTER will error (ignore).

ALTER TABLE `tbl_sale_invoice_items` ADD COLUMN `metal_rate` DECIMAL(15,2) DEFAULT NULL AFTER `rate`;
