-- Add Against Ledger and Against Invoice No columns to tbl_customer_ledger
-- Run this SQL script to add the missing columns for the account ledger report
-- If columns already exist, you'll get an error - that's okay, just ignore it

-- Add against_ledger column
ALTER TABLE `tbl_customer_ledger`
ADD COLUMN `against_ledger` varchar(255) DEFAULT NULL COMMENT 'Against Ledger name with balance (e.g., ABC(640.00Dr))' AFTER `reference_no`;

-- Add against_invoice_no column
ALTER TABLE `tbl_customer_ledger`
ADD COLUMN `against_invoice_no` varchar(100) DEFAULT NULL COMMENT 'Against Invoice/Order number' AFTER `against_ledger`;

-- Add index for faster lookups
ALTER TABLE `tbl_customer_ledger`
ADD INDEX `idx_against_invoice_no` (`against_invoice_no`);

