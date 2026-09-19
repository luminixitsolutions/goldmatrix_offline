-- Links each sale invoice to tbl_branches.id for branch-scoped lists (main vs sub-branch login).
-- Safe to run once; ignore duplicate column errors.

ALTER TABLE `tbl_sale_invoices`
  ADD COLUMN `branch_id` INT NULL DEFAULT NULL COMMENT 'FK tbl_branches.id' AFTER `created_by`;

-- Optional: assign existing rows to your main branch (replace 1 with tbl_branches.id for main):
-- UPDATE tbl_sale_invoices SET branch_id = 1 WHERE branch_id IS NULL;
