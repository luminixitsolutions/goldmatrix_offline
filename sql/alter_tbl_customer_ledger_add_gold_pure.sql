-- Add gold pure weight columns to tbl_customer_ledger (purity weight for Gold Credit/Gold Debit)
-- Run once. Then ledger report "Gold Credit Pure" / "Gold Debit Pure" will show purity wt (e.g. 9.5) instead of gross (10).
-- Without these columns, the report falls back to showing gross weight in the Pure columns.

-- Run once. Skip if columns already exist (remove ADD for any column that already exists).
ALTER TABLE `tbl_customer_ledger`
  ADD COLUMN `debit_gold_pure` decimal(10,3) DEFAULT 0.000 COMMENT 'Gold pure weight (debit)' AFTER `credit_gold`,
  ADD COLUMN `credit_gold_pure` decimal(10,3) DEFAULT 0.000 COMMENT 'Gold pure weight (credit)' AFTER `debit_gold_pure`,
  ADD COLUMN `balance_gold_pure` decimal(10,3) DEFAULT 0.000 COMMENT 'Running balance gold pure' AFTER `balance_gold`;

-- After running this ALTER, either:
-- 1) Re-save the Hedging purchase invoice (e.g. PI-8) from the UI so new ledger rows get credit_gold_pure = 9.5, or
-- 2) Run backfill_customer_ledger_credit_gold_pure.sql to update existing ledger rows from invoice items.
