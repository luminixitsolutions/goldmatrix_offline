-- Optional manual migration: document header tables that store `branch_id` for branch-scoped transactions.
-- The application also adds missing columns at runtime via `auragold_ensure_table_branch_id_column()` when saving.

-- Already covered elsewhere: tbl_sale_invoices, settings tables, tbl_bill_series.

-- Quotations
-- ALTER TABLE tbl_sale_quotations ADD COLUMN branch_id INT NULL DEFAULT NULL COMMENT 'FK tbl_branches.id' AFTER id;
-- ALTER TABLE tbl_purchase_quotations ADD COLUMN branch_id INT NULL DEFAULT NULL COMMENT 'FK tbl_branches.id' AFTER id;

-- Returns / purchase invoice header (if not auto-added)
-- ALTER TABLE tbl_sale_returns ADD COLUMN branch_id INT NULL DEFAULT NULL COMMENT 'FK tbl_branches.id' AFTER id;
-- ALTER TABLE tbl_purchase_returns ADD COLUMN branch_id INT NULL DEFAULT NULL COMMENT 'FK tbl_branches.id' AFTER id;
-- ALTER TABLE tbl_purchase_invoices ADD COLUMN branch_id INT NULL DEFAULT NULL COMMENT 'FK tbl_branches.id' AFTER id;

-- Vouchers
-- ALTER TABLE tbl_payment_vouchers ADD COLUMN branch_id INT NULL DEFAULT NULL COMMENT 'FK tbl_branches.id' AFTER id;
-- ALTER TABLE tbl_receipt_vouchers ADD COLUMN branch_id INT NULL DEFAULT NULL COMMENT 'FK tbl_branches.id' AFTER id;
-- ALTER TABLE tbl_advance_payments ADD COLUMN branch_id INT NULL DEFAULT NULL COMMENT 'FK tbl_branches.id' AFTER id;

-- Job / material (examples)
-- ALTER TABLE tbl_material_issues ADD COLUMN branch_id INT NULL DEFAULT NULL COMMENT 'FK tbl_branches.id' AFTER id;
-- ALTER TABLE tbl_material_receives ADD COLUMN branch_id INT NULL DEFAULT NULL COMMENT 'FK tbl_branches.id' AFTER id;
-- ALTER TABLE tbl_jobwork_orders ADD COLUMN branch_id INT NULL DEFAULT NULL COMMENT 'FK tbl_branches.id' AFTER id;
-- ALTER TABLE tbl_jobwork_invoices ADD COLUMN branch_id INT NULL DEFAULT NULL COMMENT 'FK tbl_branches.id' AFTER id;

-- After adding columns, assign legacy rows to main branch (replace 1 with your main tbl_branches.id):
-- UPDATE tbl_sale_quotations SET branch_id = 1 WHERE branch_id IS NULL;
-- (repeat per table as needed)
