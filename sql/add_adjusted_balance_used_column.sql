-- Add adjusted_balance_used column to tbl_sale_invoices table
-- This column tracks how much of the adjusted balance was used in each sale invoice

ALTER TABLE `tbl_sale_invoices` 
ADD COLUMN IF NOT EXISTS `adjusted_balance_used` decimal(15,2) DEFAULT 0.00 
COMMENT 'Amount of adjusted balance used in this invoice' 
AFTER `balance_amt`;
