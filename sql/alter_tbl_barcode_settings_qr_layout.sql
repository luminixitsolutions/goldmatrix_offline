-- Separate saved layout for QR vs Barcode designer + default print preference.
-- Safe to re-run: skips columns that already exist (MySQL 8+ / MariaDB with IF NOT EXISTS).
-- App also auto-adds these via auragold_ensure_barcode_settings_qr_columns() in config.php.

-- Prefer these (MySQL 8.0.12+ / MariaDB 10.3.3+):
ALTER TABLE `tbl_barcode_settings`
  ADD COLUMN IF NOT EXISTS `design_layout_qr` LONGTEXT NULL COMMENT 'JSON label design for QR mode' AFTER `design_layout`;

ALTER TABLE `tbl_barcode_settings`
  ADD COLUMN IF NOT EXISTS `default_print_code_type` VARCHAR(10) NOT NULL DEFAULT 'barcode' COMMENT 'barcode|qr — used when print URL has no code= param' AFTER `design_layout_qr`;

-- If your MySQL does not support IF NOT EXISTS on ADD COLUMN, use instead:
-- ALTER TABLE `tbl_barcode_settings`
--   ADD COLUMN `design_layout_qr` LONGTEXT NULL COMMENT 'JSON label design for QR mode' AFTER `design_layout`,
--   ADD COLUMN `default_print_code_type` VARCHAR(10) NOT NULL DEFAULT 'barcode' COMMENT 'barcode|qr' AFTER `design_layout_qr`;

-- Seed QR layout from existing barcode layout (one-time copy for empty QR layouts)
UPDATE `tbl_barcode_settings`
SET `design_layout_qr` = `design_layout`
WHERE (`design_layout_qr` IS NULL OR `design_layout_qr` = '')
  AND `design_layout` IS NOT NULL AND `design_layout` != '';
