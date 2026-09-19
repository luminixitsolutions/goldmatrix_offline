-- ALTER TABLE statements for tbl_payment_vouchers
-- Add any missing fields that might be useful

-- 1. Add receipt_no field (if you want to store receipt number separately)
ALTER TABLE `tbl_payment_vouchers` 
ADD COLUMN `receipt_no` varchar(100) COLLATE utf8mb4_unicode_ci DEFAULT NULL AFTER `ref_no`;

-- 2. Add currency_rate field (if you want to store currency conversion rate)
ALTER TABLE `tbl_payment_vouchers` 
ADD COLUMN `currency_rate` decimal(15,6) DEFAULT '1.000000' AFTER `currency`;

-- Note: The current table structure already includes all fields used in the save script:
-- voucher_no, customer_id, customer_name, ref_no, voucher_type, against,
-- sales_person, against_of, currency, voucher_date, due_date, layaways_id,
-- fixing_type, previous_balance, previous_gold, previous_silver,
-- total_amount, total_gold, total_silver, comment, status, created_by, created_at, updated_at

-- IMPORTANT: Also check the save-payment-voucher.php file - it's using 
-- 'tbl_customer_advance_vouchers' for INSERT but 'tbl_payment_vouchers' for UPDATE.
-- You may need to fix this inconsistency in the code.
