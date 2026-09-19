-- Add diamond_category to tbl_sale_invoice_items for Diamond tab (Jewellery, Diamonds, GemStones)
-- Run once in phpMyAdmin. If column exists, ALTER will error (ignore).

ALTER TABLE `tbl_sale_invoice_items` ADD COLUMN `diamond_category` VARCHAR(50) DEFAULT NULL AFTER `design_no`;
