-- Stock journal: who created / last modified (username + user id) for audit trail.
-- Runtime also calls auragold_ensure_stock_journal_audit_columns() on save/update.

ALTER TABLE `tbl_stock_journal`
  ADD COLUMN `created_by_username` VARCHAR(191) NULL DEFAULT NULL COMMENT 'Login username at create' AFTER `created_by`;

ALTER TABLE `tbl_stock_journal`
  ADD COLUMN `modified_by` INT NULL DEFAULT NULL AFTER `updated_at`;

ALTER TABLE `tbl_stock_journal`
  ADD COLUMN `modified_by_username` VARCHAR(191) NULL DEFAULT NULL AFTER `modified_by`;
