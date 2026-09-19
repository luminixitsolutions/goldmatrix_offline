-- Add all missing fields for Diamond tab items
-- Run in phpMyAdmin. If a column already exists, that ALTER will error (ignore and continue).
-- Run add_diamond_fields_to_sale_invoice_items.sql and add_metal_rate_to_sale_invoice_items.sql first if not done.

-- calculation_type: Carat X Rate, Rate X Gross Wt, Metal Rate x Metal Weight, etc.
ALTER TABLE `tbl_sale_invoice_items` ADD COLUMN `calculation_type` VARCHAR(100) DEFAULT NULL AFTER `design_no`;

-- diamond_amount: Jewellery row sum of Diamond+GemStone amounts
ALTER TABLE `tbl_sale_invoice_items` ADD COLUMN `diamond_amount` DECIMAL(15,2) DEFAULT NULL AFTER `amount`;

-- stone_amount: Stone charge amount (run after diamond_amount exists)
ALTER TABLE `tbl_sale_invoice_items` ADD COLUMN `stone_amount` DECIMAL(15,2) DEFAULT NULL AFTER `diamond_amount`;

-- stone_weight: Carat for Diamond/GemStone (decimal)
ALTER TABLE `tbl_sale_invoice_items` ADD COLUMN `stone_weight` DECIMAL(10,3) DEFAULT NULL AFTER `carat`;
image.png
-- metal_value: Metal value
ALTER TABLE `tbl_sale_invoice_items` ADD COLUMN `metal_value` DECIMAL(15,2) DEFAULT NULL AFTER `rate`;
