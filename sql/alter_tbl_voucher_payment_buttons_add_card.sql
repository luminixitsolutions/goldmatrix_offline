-- Add card column if an older tbl_voucher_payment_buttons was created without it.
-- Safe to run once; ignore duplicate column error on MySQL 8+ use information_schema if needed.

ALTER TABLE `tbl_voucher_payment_buttons`
  ADD COLUMN `card` tinyint(1) NOT NULL DEFAULT 1 AFTER `add_stone`;
