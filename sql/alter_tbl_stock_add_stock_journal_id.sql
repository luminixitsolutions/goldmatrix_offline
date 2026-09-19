-- Add stock_journal_id to tbl_stock to link stock rows to purchase invoice item (journal batch).
-- Used for Edit Mode: fetch rows where stock_journal_id = item_id.
-- Run once. Skip if column already exists.

ALTER TABLE `tbl_stock`
ADD COLUMN `stock_journal_id` INT NULL DEFAULT NULL COMMENT 'Purchase invoice item_id (journal batch)'
AFTER `transaction_date`;

-- Optional: Backfill existing stock rows from tbl_stock_journal (run after adding column)
-- UPDATE tbl_stock st
-- INNER JOIN tbl_stock_journal sj ON sj.barcode = st.barcode AND sj.product_id = st.product_id AND sj.status = 'active'
-- SET st.stock_journal_id = sj.item_id
-- WHERE st.stock_type IN ('purchase','opening') AND (st.stock_journal_id IS NULL OR st.stock_journal_id = 0);
