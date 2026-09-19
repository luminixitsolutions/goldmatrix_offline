-- Add missing columns to tbl_product_characteristics table
-- These fields are used in product-opening.php form but were not being saved

-- Add unit_id column (foreign key to tbl_unit)
ALTER TABLE `tbl_product_characteristics` 
ADD COLUMN `unit_id` INT(11) NULL DEFAULT NULL 
COMMENT 'Reference to tbl_unit.id' 
AFTER `diamond_category`;

-- Add location_id column (foreign key to tbl_location)
ALTER TABLE `tbl_product_characteristics` 
ADD COLUMN `location_id` INT(11) NULL DEFAULT NULL 
COMMENT 'Reference to tbl_location.id' 
AFTER `unit_id`;

-- Add purity_sale column (decimal for sale purity percentage)
ALTER TABLE `tbl_product_characteristics` 
ADD COLUMN `purity_sale` DECIMAL(10,2) NULL DEFAULT NULL 
COMMENT 'Purity percentage for sale' 
AFTER `location_id`;

-- Add purity_purchase column (tinyint for checkbox - 1 if purchase purity is enabled)
ALTER TABLE `tbl_product_characteristics` 
ADD COLUMN `purity_purchase` TINYINT(1) NULL DEFAULT 0 
COMMENT 'Purchase purity enabled (1) or not (0)' 
AFTER `purity_sale`;

-- Add wastage_sale column (decimal for sale wastage percentage)
ALTER TABLE `tbl_product_characteristics` 
ADD COLUMN `wastage_sale` DECIMAL(10,2) NULL DEFAULT NULL 
COMMENT 'Wastage percentage for sale' 
AFTER `purity_purchase`;

-- Add wastage_purchase column (decimal for purchase wastage percentage)
ALTER TABLE `tbl_product_characteristics` 
ADD COLUMN `wastage_purchase` DECIMAL(10,2) NULL DEFAULT NULL 
COMMENT 'Wastage percentage for purchase' 
AFTER `wastage_sale`;

-- Add wt_per_piece column (decimal for weight per piece)
ALTER TABLE `tbl_product_characteristics` 
ADD COLUMN `wt_per_piece` DECIMAL(10,3) NULL DEFAULT NULL 
COMMENT 'Weight per piece' 
AFTER `wastage_purchase`;

-- Add indexes for foreign keys (optional but recommended for performance)
ALTER TABLE `tbl_product_characteristics` 
ADD INDEX `idx_unit_id` (`unit_id`);

ALTER TABLE `tbl_product_characteristics` 
ADD INDEX `idx_location_id` (`location_id`);
