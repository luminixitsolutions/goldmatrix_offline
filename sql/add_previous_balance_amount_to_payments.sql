-- Add previous_balance_amount column to tbl_sale_order_payments table
-- This column stores the amount paid towards previous balance in each payment

ALTER TABLE `tbl_sale_order_payments`
ADD COLUMN `previous_balance_amount` decimal(15,2) DEFAULT 0.00 COMMENT 'Amount paid towards previous balance' AFTER `amount`;

