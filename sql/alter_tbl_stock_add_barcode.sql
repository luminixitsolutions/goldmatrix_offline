-- Add barcode field to tbl_stock table
-- This field will store the barcode number when stock entries are created from stock journal

ALTER TABLE `tbl_stock` 
ADD COLUMN `barcode` VARCHAR(100) NULL DEFAULT NULL 
COMMENT 'Barcode number for the stock entry' 
AFTER `product_characteristic_id`;

-- Add index for faster barcode lookups
ALTER TABLE `tbl_stock` 
ADD INDEX `idx_barcode` (`barcode`);
