-- Optional: add Requested Purity / Requested Wt. / Alloy Wt. to material issue line items.
-- save-material-issue.php also auto-adds these columns if missing.

ALTER TABLE `tbl_material_issue_items`
  ADD COLUMN `requested_purity` decimal(12,4) DEFAULT NULL AFTER `purity_weight`,
  ADD COLUMN `requested_wt` decimal(12,4) DEFAULT NULL AFTER `requested_purity`,
  ADD COLUMN `alloy_wt` decimal(12,4) DEFAULT NULL AFTER `requested_wt`;
