-- ALTER TABLE statements for tbl_payment_voucher_items
-- Add missing fields that are being collected in the form but not saved to database

-- 1. Add transfer_from field
ALTER TABLE `tbl_payment_voucher_items` 
ADD COLUMN `transfer_from` varchar(100) COLLATE utf8mb4_unicode_ci DEFAULT NULL AFTER `transaction_no`;

-- 2. Add rate field (for metal exchange rate)
ALTER TABLE `tbl_payment_voucher_items` 
ADD COLUMN `rate` decimal(15,2) DEFAULT '0.00' AFTER `purity_wt`;

-- 3. Add item_code field
ALTER TABLE `tbl_payment_voucher_items` 
ADD COLUMN `item_code` varchar(100) COLLATE utf8mb4_unicode_ci DEFAULT NULL AFTER `amount`;

-- 4. Add barcode_no field
ALTER TABLE `tbl_payment_voucher_items` 
ADD COLUMN `barcode_no` varchar(100) COLLATE utf8mb4_unicode_ci DEFAULT NULL AFTER `item_code`;

-- 5. Add card_no field (for card payment)
ALTER TABLE `tbl_payment_voucher_items` 
ADD COLUMN `card_no` varchar(50) COLLATE utf8mb4_unicode_ci DEFAULT NULL AFTER `barcode_no`;

-- Note: After running these ALTER statements, you'll need to update the 
-- save-payment-voucher.php file to include these fields in the INSERT statement.
