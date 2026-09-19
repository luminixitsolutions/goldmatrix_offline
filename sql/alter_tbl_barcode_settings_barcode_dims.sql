-- Persist JsBarcode line width/height on the settings row (in addition to design_layout JSON).
-- Run once on existing databases.

ALTER TABLE `tbl_barcode_settings`
  ADD COLUMN `barcode_bar_width` tinyint(4) NOT NULL DEFAULT 2 COMMENT 'JsBarcode module width 1-10' AFTER `print_copies`,
  ADD COLUMN `barcode_bar_height` smallint(6) NOT NULL DEFAULT 28 COMMENT 'JsBarcode module height px' AFTER `barcode_bar_width`;
