-- Mark one label size per metal type as the default for barcode printing.
-- Run once on existing databases.

ALTER TABLE `tbl_barcode_settings`
  ADD COLUMN `is_default_print` tinyint(1) NOT NULL DEFAULT 0
  COMMENT '1 = default print layout for this metal_type (only one per metal+branch)'
  AFTER `metal_type`;
