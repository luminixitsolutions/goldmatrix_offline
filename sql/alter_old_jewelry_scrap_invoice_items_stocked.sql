-- Add stocked tracking to old jewelry scrap invoice items
-- Run once to support Stock In / Stocked tab / Revert Stock In

ALTER TABLE `tbl_old_jewelry_scrap_invoice_items`
  ADD COLUMN `is_stocked` tinyint(1) DEFAULT 0 COMMENT '1=stocked in',
  ADD COLUMN `stocked_at` datetime DEFAULT NULL,
  ADD COLUMN `stocked_branch_id` int(11) DEFAULT NULL;
