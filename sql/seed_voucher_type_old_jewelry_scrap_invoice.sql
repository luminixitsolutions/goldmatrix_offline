-- Optional: voucher type for Old Jewellery Scrap Invoice (use in Bill Series at admin/bill-series.php).
-- Run once if the type does not already exist.

INSERT INTO `tbl_voucher_types` (`name`, `method_of_numbering`, `type_of_voucher`, `calculate_amount_by`, `calculate_wastage_by`, `fixing_type`, `calculate_loss_by`, `status`, `created_at`)
SELECT 'Old Jewellery Scrap Invoice', '1', 'Old Jewellery Scrap Invoice', 'Rate X Gross Wt', 'Net Wt', 'Standard', 'Net Wt', 1, NOW()
WHERE NOT EXISTS (
  SELECT 1 FROM `tbl_voucher_types` WHERE `status` = 1 AND LOWER(TRIM(`name`)) = 'old jewellery scrap invoice' LIMIT 1
);
