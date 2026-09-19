-- ALTER TABLE statements for tbl_sale_order_items
-- Adding missing fields that are extracted from POST data but not being saved to database
-- Note: metal_value and reverse already exist in the table structure

-- 1. Add less_weight field (being inserted in code but missing from table)
ALTER TABLE `tbl_sale_order_items` 
ADD COLUMN `less_weight` decimal(10,3) DEFAULT '0.000' AFTER `gross_weight`;

-- 2. Add stone_charges field
ALTER TABLE `tbl_sale_order_items` 
ADD COLUMN `stone_charges` decimal(10,2) DEFAULT '0.00' AFTER `metal_value`;

-- 3. Add stone_amount field
ALTER TABLE `tbl_sale_order_items` 
ADD COLUMN `stone_amount` decimal(10,2) DEFAULT '0.00' AFTER `stone_charges`;

-- 4. Add other_charges field
ALTER TABLE `tbl_sale_order_items` 
ADD COLUMN `other_charges` decimal(10,2) DEFAULT '0.00' AFTER `stone_amount`;

-- 5. Add other_amount field
ALTER TABLE `tbl_sale_order_items` 
ADD COLUMN `other_amount` decimal(10,2) DEFAULT '0.00' AFTER `other_charges`;

-- 6. Add diamond_value field
ALTER TABLE `tbl_sale_order_items` 
ADD COLUMN `diamond_value` decimal(10,2) DEFAULT '0.00' AFTER `other_amount`;

-- 7. Add diamond_amount field
ALTER TABLE `tbl_sale_order_items` 
ADD COLUMN `diamond_amount` decimal(10,2) DEFAULT '0.00' AFTER `diamond_value`;

-- 8. Add gemstone_value field
ALTER TABLE `tbl_sale_order_items` 
ADD COLUMN `gemstone_value` decimal(10,2) DEFAULT '0.00' AFTER `diamond_amount`;

-- 9. Add discount field
ALTER TABLE `tbl_sale_order_items` 
ADD COLUMN `discount` decimal(10,2) DEFAULT '0.00' AFTER `gemstone_value`;

-- 10. Add purchase_amount field
ALTER TABLE `tbl_sale_order_items` 
ADD COLUMN `purchase_amount` decimal(10,2) DEFAULT '0.00' AFTER `net_amt_with_tax`;

-- 11. Add sale_amount field
ALTER TABLE `tbl_sale_order_items` 
ADD COLUMN `sale_amount` decimal(10,2) DEFAULT '0.00' AFTER `purchase_amount`;

-- 12. Add sale_amount_with field
ALTER TABLE `tbl_sale_order_items` 
ADD COLUMN `sale_amount_with` decimal(10,2) DEFAULT '0.00' AFTER `sale_amount`;

-- Note: Fields that already exist in table but are NOT being inserted in save-sale-order.php:
-- - metal_value (exists in table, extracted but not inserted)
-- - reverse (exists in table, extracted but not inserted)
-- These should be added to the INSERT statement in save-sale-order.php if needed

