-- Add design_layout to save/restore barcode print design (canvas items)
ALTER TABLE `tbl_barcode_settings`
  ADD COLUMN `design_layout` text DEFAULT NULL COMMENT 'JSON: barcode label design (field, left, top, prefix, suffix, font, font_size)' AFTER `metal_type`;
