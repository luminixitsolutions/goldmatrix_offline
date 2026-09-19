-- Add group_image column to tbl_purchase_invoice_items for storing product images
-- Run this script in phpMyAdmin or MySQL client

ALTER TABLE `tbl_purchase_invoice_items` 
ADD COLUMN `group_image` LONGTEXT NULL DEFAULT NULL 
COMMENT 'Base64 encoded image data or image path' 
AFTER `reverse`;

-- Also add to tbl_consignment_out_items if exists
ALTER TABLE `tbl_consignment_out_items` 
ADD COLUMN `group_image` LONGTEXT NULL DEFAULT NULL 
COMMENT 'Base64 encoded image data or image path' 
AFTER `net_amt_with_tax`;

-- Also add to tbl_consignment_in_items if exists
ALTER TABLE `tbl_consignment_in_items` 
ADD COLUMN `group_image` LONGTEXT NULL DEFAULT NULL 
COMMENT 'Base64 encoded image data or image path' 
AFTER `net_amt_with_tax`;
