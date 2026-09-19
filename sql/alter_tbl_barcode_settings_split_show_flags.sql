-- Separate Product name / Price / Barcode no. checkboxes for Barcode vs QR layouts.
-- Run once on existing databases (safe to skip if columns already exist).

ALTER TABLE `tbl_barcode_settings`
  ADD COLUMN `show_product_name_barcode` tinyint(1) NOT NULL DEFAULT 1 COMMENT '1=show product name on barcode layout' AFTER `show_barcode_number`,
  ADD COLUMN `show_product_name_qr` tinyint(1) NOT NULL DEFAULT 1 COMMENT '1=show product name on QR layout' AFTER `show_product_name_barcode`,
  ADD COLUMN `show_price_barcode` tinyint(1) NOT NULL DEFAULT 1 COMMENT '1=show price on barcode layout' AFTER `show_product_name_qr`,
  ADD COLUMN `show_price_qr` tinyint(1) NOT NULL DEFAULT 1 COMMENT '1=show price on QR layout' AFTER `show_price_barcode`,
  ADD COLUMN `show_barcode_number_barcode` tinyint(1) NOT NULL DEFAULT 1 COMMENT '1=show barcode no. on barcode layout' AFTER `show_price_qr`,
  ADD COLUMN `show_barcode_number_qr` tinyint(1) NOT NULL DEFAULT 1 COMMENT '1=show barcode no. on QR layout' AFTER `show_barcode_number_barcode`;

UPDATE `tbl_barcode_settings` SET
  `show_product_name_barcode` = `show_product_name`,
  `show_product_name_qr` = `show_product_name`,
  `show_price_barcode` = `show_price`,
  `show_price_qr` = `show_price`,
  `show_barcode_number_barcode` = `show_barcode_number`,
  `show_barcode_number_qr` = `show_barcode_number`;
