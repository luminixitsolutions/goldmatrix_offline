-- Optional: run once if columns are missing — supports purchase return pending/returned qty on quotation lines.
ALTER TABLE `tbl_purchase_quotation_items`
  ADD COLUMN `returned_qty` decimal(10,2) NOT NULL DEFAULT 0.00 AFTER `quantity`,
  ADD COLUMN `pending_qty` decimal(10,2) DEFAULT NULL AFTER `returned_qty`;

-- Backfill pending_qty from quantity where null
UPDATE `tbl_purchase_quotation_items`
SET `pending_qty` = IFNULL(`quantity`, 0)
WHERE `pending_qty` IS NULL;
