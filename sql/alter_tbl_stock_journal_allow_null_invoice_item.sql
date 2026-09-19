-- Allow NULL for invoice_id and item_id in tbl_stock_journal so Product Opening vouchers can be saved
-- (Product Opening has no purchase invoice or invoice item.)
-- Run this once on your database.

-- Drop FKs so we can change column definitions
ALTER TABLE `tbl_stock_journal` DROP FOREIGN KEY `fk_stock_journal_invoice`;
ALTER TABLE `tbl_stock_journal` DROP FOREIGN KEY `fk_stock_journal_item`;

-- Allow NULL for product opening vouchers
ALTER TABLE `tbl_stock_journal`
  MODIFY COLUMN `invoice_id` int(11) NULL COMMENT 'Reference to tbl_purchase_invoices.id (NULL for product opening)',
  MODIFY COLUMN `item_id` int(11) NULL COMMENT 'Reference to tbl_purchase_invoice_items.id (NULL for product opening)';

-- Convert any existing 0 to NULL so FKs can be re-added (0 is not a valid id in referenced tables)
UPDATE `tbl_stock_journal` SET `invoice_id` = NULL WHERE `invoice_id` = 0;
UPDATE `tbl_stock_journal` SET `item_id` = NULL WHERE `item_id` = 0;

-- Re-add FKs (NULL values are allowed and not validated)
ALTER TABLE `tbl_stock_journal`
  ADD CONSTRAINT `fk_stock_journal_invoice` FOREIGN KEY (`invoice_id`) REFERENCES `tbl_purchase_invoices` (`id`) ON DELETE RESTRICT;
ALTER TABLE `tbl_stock_journal`
  ADD CONSTRAINT `fk_stock_journal_item` FOREIGN KEY (`item_id`) REFERENCES `tbl_purchase_invoice_items` (`id`) ON DELETE RESTRICT;
