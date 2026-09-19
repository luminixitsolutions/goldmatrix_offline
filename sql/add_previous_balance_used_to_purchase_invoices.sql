-- Add columns to save "Use previous balance" and "Amount used" from previous balance
-- Run this once to add the columns to tbl_purchase_invoices

ALTER TABLE `tbl_purchase_invoices`
ADD COLUMN `use_previous_balance` tinyint(1) DEFAULT 0 COMMENT '1=used previous balance on this invoice',
ADD COLUMN `previous_balance_used_amt` decimal(15,2) DEFAULT 0.00 COMMENT 'Amount used from previous balance (e.g. 500.00)';
