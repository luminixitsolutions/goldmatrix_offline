-- Add setting_type to support per-document print settings (default, sale_invoice, purchase_invoice, etc.)
-- Run this only if your table was created without the setting_type column (e.g. before this feature).
-- New installs: use create_tbl_invoice_print_settings.sql which already includes setting_type.

-- 1) Add column (skip if you already have setting_type)
ALTER TABLE `tbl_invoice_print_settings` ADD COLUMN `setting_type` varchar(50) NOT NULL DEFAULT 'default' COMMENT 'default, sale_invoice, purchase_invoice, ...' AFTER `id`;

-- 2) Ensure existing rows have setting_type = 'default'
UPDATE `tbl_invoice_print_settings` SET `setting_type` = 'default' WHERE `setting_type` = '' OR `setting_type` IS NULL;

-- 3) Drop old unique key on setting_key (index name may be setting_key)
ALTER TABLE `tbl_invoice_print_settings` DROP INDEX `setting_key`;

-- 4) Add new unique key and index
ALTER TABLE `tbl_invoice_print_settings` ADD UNIQUE KEY `setting_type_key` (`setting_type`, `setting_key`);
ALTER TABLE `tbl_invoice_print_settings` ADD KEY `idx_setting_type` (`setting_type`);
