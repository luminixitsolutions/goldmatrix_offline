-- Add ALL missing fields for Diamond tab to tbl_sale_invoice_items
-- Run each ALTER in phpMyAdmin. If a column already exists, that ALTER will error (ignore and continue with next).

-- 1. diamond_category (Jewellery, Diamonds, GemStones)
ALTER TABLE `tbl_sale_invoice_items` ADD COLUMN `diamond_category` VARCHAR(50) DEFAULT NULL AFTER `design_no`;

-- 2. metal_rate
ALTER TABLE `tbl_sale_invoice_items` ADD COLUMN `metal_rate` DECIMAL(15,2) DEFAULT NULL AFTER `rate`;

-- 3. calculation_type (Carat X Rate, Rate X Gross Wt, Metal Rate x Metal Weight, etc.)
ALTER TABLE `tbl_sale_invoice_items` ADD COLUMN `calculation_type` VARCHAR(100) DEFAULT NULL AFTER `design_no`;

-- 4. diamond_amount (Jewellery row sum of Diamond+GemStone amounts)
ALTER TABLE `tbl_sale_invoice_items` ADD COLUMN `diamond_amount` DECIMAL(15,2) DEFAULT NULL AFTER `amount`;

-- 5. stone_amount (Stone charge amount)
ALTER TABLE `tbl_sale_invoice_items` ADD COLUMN `stone_amount` DECIMAL(15,2) DEFAULT NULL AFTER `amount`;

-- 6. stone_weight (Carat for Diamond/GemStone as decimal)
ALTER TABLE `tbl_sale_invoice_items` ADD COLUMN `stone_weight` DECIMAL(10,3) DEFAULT NULL AFTER `carat`;

-- 7. metal_value
ALTER TABLE `tbl_sale_invoice_items` ADD COLUMN `metal_value` DECIMAL(15,2) DEFAULT NULL AFTER `rate`;
