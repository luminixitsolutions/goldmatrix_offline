-- Add metal_type to existing tbl_barcode_settings (run if table was created before this column existed)
ALTER TABLE `tbl_barcode_settings`
  ADD COLUMN `metal_type` varchar(50) DEFAULT NULL COMMENT 'e.g. Gold, Silver, Platinum' AFTER `print_copies`;
