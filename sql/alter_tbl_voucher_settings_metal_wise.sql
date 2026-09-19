-- Migration: Make voucher settings one row per metal (Gold, Silver, etc.).
-- Run this if you already have tbl_voucher_settings with a single row.

-- Ensure one row per metal: add UNIQUE on metal_wise (run once; ignore error if key already exists)
ALTER TABLE `tbl_voucher_settings` ADD UNIQUE KEY `uk_metal_wise` (`metal_wise`);

-- Insert missing metals with defaults (INSERT IGNORE skips if metal_wise already exists)
INSERT IGNORE INTO `tbl_voucher_settings` (`metal_wise`, `minimum_amount_column`, `reverse_calculation_result_column`, `default_discount_type`, `default_calculation_type`, `stock_availability_check_by`, `updated_at`) VALUES
('Silver', 'Amount', 'MakingRate', 'Fix', 'Fix', 'Carat', NOW()),
('Platinum', 'Amount', 'MakingRate', 'Fix', 'Fix', 'Carat', NOW()),
('Diamond & Stones', 'Amount', 'MakingRate', 'Fix', 'Fix', 'Carat', NOW()),
('Imitation Or Watches', 'Amount', 'MakingRate', 'Fix', 'Fix', 'Carat', NOW()),
('Other Or Services', 'Amount', 'MakingRate', 'Fix', 'Fix', 'Carat', NOW());
