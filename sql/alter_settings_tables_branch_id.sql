-- Branch-scoped Set Software settings (barcode, voucher, invoice print) + bill series backfill.
-- The app also applies these changes automatically via auragold_ensure_branch_id_on_settings_tables() on first use.

-- Barcode settings: one logical row per branch
-- ALTER TABLE tbl_barcode_settings ADD COLUMN branch_id INT NULL DEFAULT NULL COMMENT 'FK tbl_branches.id' AFTER id;
-- ALTER TABLE tbl_barcode_settings ADD KEY idx_barcode_settings_branch (branch_id);

-- Voucher settings: one row per (branch, metal_wise); replaces UNIQUE(metal_wise) only
-- ALTER TABLE tbl_voucher_settings ADD COLUMN branch_id INT NULL DEFAULT NULL COMMENT 'FK tbl_branches.id' AFTER id;
-- ALTER TABLE tbl_voucher_settings DROP INDEX uk_metal_wise;
-- ALTER TABLE tbl_voucher_settings ADD UNIQUE KEY uk_branch_metal (branch_id, metal_wise);

-- Invoice print: unique per (branch, setting_type, setting_key)
-- ALTER TABLE tbl_invoice_print_settings ADD COLUMN branch_id INT NULL DEFAULT NULL COMMENT 'FK tbl_branches.id' AFTER id;
-- ALTER TABLE tbl_invoice_print_settings DROP INDEX setting_type_key;
-- ALTER TABLE tbl_invoice_print_settings ADD UNIQUE KEY uk_branch_setting_type_key (branch_id, setting_type, setting_key);

-- After adding columns, backfill branch_id to your main branch (replace @main_id):
-- UPDATE tbl_barcode_settings SET branch_id = @main_id WHERE branch_id IS NULL;
-- UPDATE tbl_voucher_settings SET branch_id = @main_id WHERE branch_id IS NULL;
-- UPDATE tbl_invoice_print_settings SET branch_id = @main_id WHERE branch_id IS NULL;
-- UPDATE tbl_bill_series SET branch_id = @main_id WHERE branch_id IS NULL;
