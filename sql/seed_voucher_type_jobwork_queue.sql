-- Voucher type for Jobwork Queue bill series (configure prefix in admin/bill-series.php)
INSERT INTO `tbl_voucher_types` (`name`, `method_of_numbering`, `type_of_voucher`, `calculate_amount_by`, `calculate_wastage_by`, `fixing_type`, `calculate_loss_by`, `status`, `created_at`)
SELECT 'Jobwork Queue', '1', 'Jobwork Queue', 'Rate X Gross Wt', 'Net Wt', 'Standard', 'Net Wt', 1, NOW()
WHERE NOT EXISTS (
  SELECT 1 FROM `tbl_voucher_types` WHERE `status` = 1 AND LOWER(TRIM(`name`)) = 'jobwork queue' LIMIT 1
);
