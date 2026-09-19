-- Add preview_image to store saved preview image path
ALTER TABLE `tbl_barcode_settings`
  ADD COLUMN `preview_image` VARCHAR(255) DEFAULT NULL COMMENT 'Path to saved preview image e.g. uploads/barcode_settings/preview_1234567890.png' AFTER `design_layout`;
