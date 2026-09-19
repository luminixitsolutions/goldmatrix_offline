-- Add images column to tbl_sale_invoice_items for storing saved image paths (primary + multiple)
-- Run this script in phpMyAdmin or MySQL client
-- Paths are stored as JSON: {"primary":"uploads/sale-invoice/1/item_5_0.png","images":["...","..."]}

ALTER TABLE `tbl_sale_invoice_items`
ADD COLUMN `images` TEXT NULL DEFAULT NULL
COMMENT 'JSON: primary path + array of image paths under admin/uploads/sale-invoice/' 
AFTER `location_id`;
