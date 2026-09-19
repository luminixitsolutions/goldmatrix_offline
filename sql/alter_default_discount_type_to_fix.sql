-- Optional one-time migration: set default discount type to Fix for all voucher rows.
-- Run if you want existing databases to match new app defaults (new installs use create_tbl_voucher_settings.sql).

UPDATE `tbl_voucher_settings` SET `default_discount_type` = 'Fix' WHERE `default_discount_type` = 'On Amount';
