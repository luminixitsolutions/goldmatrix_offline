-- Order tracking: store line totals on each department transfer (after save).
-- Applied automatically on next Jobwork Queue save via mp-save-jobwork-queue.php; run manually if needed.

ALTER TABLE `tbl_jobwork_queue_activity`
  ADD COLUMN `total_wt_after` decimal(12,4) DEFAULT NULL COMMENT 'Sum line wt after transfer' AFTER `activity_action`,
  ADD COLUMN `total_qty_after` decimal(12,4) DEFAULT NULL AFTER `total_wt_after`;
