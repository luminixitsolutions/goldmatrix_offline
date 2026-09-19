-- Fix Purity/Opening values to support decimals (e.g. 0.999 instead of rounding to 1)
-- Run this once to allow decimal precision for product opening purity and related fields.

-- tbl_product_characteristics: allow up to 4 decimal places for purity and weights
ALTER TABLE `tbl_product_characteristics`
MODIFY COLUMN `opening_purity` DECIMAL(10,4) NULL DEFAULT NULL COMMENT 'Purity e.g. 0.999 for 99.9%';

ALTER TABLE `tbl_product_characteristics`
MODIFY COLUMN `opening_weight` DECIMAL(15,4) NULL DEFAULT NULL;

ALTER TABLE `tbl_product_characteristics`
MODIFY COLUMN `opening_qty` DECIMAL(15,4) NULL DEFAULT NULL;

ALTER TABLE `tbl_product_characteristics`
MODIFY COLUMN `final_weight` DECIMAL(15,4) NULL DEFAULT NULL;

ALTER TABLE `tbl_product_characteristics`
MODIFY COLUMN `rate` DECIMAL(15,4) NULL DEFAULT NULL;

ALTER TABLE `tbl_product_characteristics`
MODIFY COLUMN `value` DECIMAL(15,4) NULL DEFAULT NULL;

-- tbl_stock: same for opening and current values
ALTER TABLE `tbl_stock`
MODIFY COLUMN `opening_purity` DECIMAL(10,4) NULL DEFAULT NULL;

ALTER TABLE `tbl_stock`
MODIFY COLUMN `opening_weight` DECIMAL(15,4) NULL DEFAULT NULL;

ALTER TABLE `tbl_stock`
MODIFY COLUMN `opening_qty` DECIMAL(15,4) NULL DEFAULT NULL;

ALTER TABLE `tbl_stock`
MODIFY COLUMN `final_weight` DECIMAL(15,4) NULL DEFAULT NULL;

ALTER TABLE `tbl_stock`
MODIFY COLUMN `current_weight` DECIMAL(15,4) NULL DEFAULT NULL;

ALTER TABLE `tbl_stock`
MODIFY COLUMN `current_qty` DECIMAL(15,4) NULL DEFAULT NULL;

ALTER TABLE `tbl_stock`
MODIFY COLUMN `rate` DECIMAL(15,4) NULL DEFAULT NULL;

ALTER TABLE `tbl_stock`
MODIFY COLUMN `value` DECIMAL(15,4) NULL DEFAULT NULL;
