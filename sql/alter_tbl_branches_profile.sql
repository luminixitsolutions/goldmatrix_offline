-- Jewellery shop profile fields for tbl_branches (registry database).
-- Run each statement once. Skip any line that errors with "Duplicate column name".

ALTER TABLE `tbl_branches` ADD COLUMN `address` TEXT NULL;
ALTER TABLE `tbl_branches` ADD COLUMN `phone` VARCHAR(50) NULL DEFAULT NULL;
ALTER TABLE `tbl_branches` ADD COLUMN `email` VARCHAR(255) NULL DEFAULT NULL;
ALTER TABLE `tbl_branches` ADD COLUMN `gst_no` VARCHAR(50) NULL DEFAULT NULL;
ALTER TABLE `tbl_branches` ADD COLUMN `pan_no` VARCHAR(25) NULL DEFAULT NULL;
ALTER TABLE `tbl_branches` ADD COLUMN `authorized_person` VARCHAR(150) NULL DEFAULT NULL;
ALTER TABLE `tbl_branches` ADD COLUMN `bank_name` VARCHAR(150) NULL DEFAULT NULL;
ALTER TABLE `tbl_branches` ADD COLUMN `bank_account_no` VARCHAR(64) NULL DEFAULT NULL;
ALTER TABLE `tbl_branches` ADD COLUMN `bank_ifsc` VARCHAR(20) NULL DEFAULT NULL;
ALTER TABLE `tbl_branches` ADD COLUMN `bank_branch` VARCHAR(150) NULL DEFAULT NULL;
ALTER TABLE `tbl_branches` ADD COLUMN `location_area` VARCHAR(255) NULL DEFAULT NULL;
ALTER TABLE `tbl_branches` ADD COLUMN `logo_path` VARCHAR(500) NULL DEFAULT NULL;
ALTER TABLE `tbl_branches` ADD COLUMN `invoice_terms` TEXT NULL;
ALTER TABLE `tbl_branches` ADD COLUMN `website` VARCHAR(255) NULL DEFAULT NULL;
